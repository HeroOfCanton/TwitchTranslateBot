<?php

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

// Start listener: spawns background process
$app->post('/start-listen', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $channel = $data['channel'] ?? null;
    $username = $data['username'] ?? null;
    $oauth = $data['oauth_token'] ?? null;
    $target = $data['target_lang'] ?? 'en';
    $google_key = $data['google_api_key'] ?? null;

    if (empty($channel) || empty($username) || empty($oauth) || empty($google_key)) {
        $response->getBody()->write(json_encode(['error' => 'Missing required parameters']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $pid_file = sys_get_temp_dir() . "/twitch_listener_" . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $channel) . '.pid';

    // Build command
    $php = PHP_BINARY;
    $script = __DIR__ . '/../lib/twitch_listener.php';
    $cmd = sprintf('%s %s %s %s %s %s %s > /dev/null 2>&1 & echo $!',
        escapeshellcmd($php),
        escapeshellarg($script),
        escapeshellarg($channel),
        escapeshellarg($username),
        escapeshellarg($oauth),
        escapeshellarg($target),
        escapeshellarg($google_key)
    );

    $output = [];
    exec($cmd, $output);
    $pid = trim($output[0] ?? '');

    if ($pid) {
        file_put_contents($pid_file, $pid);
        $response->getBody()->write(json_encode(['pid' => (int)$pid]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['error' => 'Failed to start listener']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
});

// Stop listener
$app->post('/stop-listen', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $channel = $data['channel'] ?? null;
    if (empty($channel)) {
        $response->getBody()->write(json_encode(['error' => 'Missing channel']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $pid_file = sys_get_temp_dir() . "/twitch_listener_" . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $channel) . '.pid';
    if (!file_exists($pid_file)) {
        $response->getBody()->write(json_encode(['error' => 'Not running']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $pid = (int)trim(file_get_contents($pid_file));
    if ($pid > 0) {
        exec('kill ' . $pid);
        unlink($pid_file);
        $response->getBody()->write(json_encode(['stopped' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['error' => 'Invalid PID']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
});

// Status
$app->get('/status', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $channel = $params['channel'] ?? null;
    if (empty($channel)) {
        $response->getBody()->write(json_encode(['error' => 'Missing channel']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $pid_file = sys_get_temp_dir() . "/twitch_listener_" . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $channel) . '.pid';
    $running = false;
    $pid = null;
    if (file_exists($pid_file)) {
        $pid = (int)trim(file_get_contents($pid_file));
        // check process exists
        if ($pid > 0) {
            exec('ps -p ' . $pid, $out);
            if (count($out) > 1) {
                $running = true;
            }
        }
    }

    $response->getBody()->write(json_encode(['running' => $running, 'pid' => $pid]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
