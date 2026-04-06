<?php

require __DIR__ . '/../vendor/autoload.php';

use TranslateBot\api\API;

set_time_limit(0);

// argv: script.php channel username oauth_token target_lang google_api_key
if ($argc < 6) {
    fwrite(STDERR, "Usage: php twitch_listener.php <channel> <username> <oauth_token> <target_lang> <google_api_key>\n");
    exit(2);
}

$channel = $argv[1];
$username = $argv[2];
$oauth = $argv[3];
$target = $argv[4];
$google_key = $argv[5];

// Ensure oauth token starts with oauth:
if (strpos($oauth, 'oauth:') !== 0) {
    $oauth = 'oauth:' . $oauth;
}

$server = 'irc.chat.twitch.tv';
$port = 6667;

$api = new API();

$reconnectDelay = 5;

while (true) {
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client("{$server}:{$port}", $errno, $errstr, 10);
    if (!$fp) {
        sleep($reconnectDelay);
        continue;
    }

    stream_set_blocking($fp, false);

    fwrite($fp, "PASS {$oauth}\r\n");
    fwrite($fp, "NICK {$username}\r\n");
    fwrite($fp, "JOIN #{$channel}\r\n");

    $buffer = '';

    while (!feof($fp)) {
        $line = fgets($fp);
        if ($line === false) {
            usleep(100000);
            continue;
        }
        $line = trim($line);
        if ($line === '') continue;

        // Respond to PINGs
        if (preg_match('/^PING :(.*)$/i', $line, $m)) {
            fwrite($fp, "PONG :{$m[1]}\r\n");
            continue;
        }

        // PRIVMSG parsing
        // Example: :someuser!someuser@someuser.tmi.twitch.tv PRIVMSG #channel :the message
        if (preg_match('/^:([^!]+)!.* PRIVMSG #[^ ]+ :(.*)$/', $line, $m)) {
            $user = $m[1];
            $message = $m[2];

            // Translate
            $translated = $api->translate_text($message, $target, $google_key);

            $out = [
                'channel' => $channel,
                'user' => $user,
                'original' => $message,
                'translated' => $translated,
                'target_lang' => $target,
                'time' => date('c'),
            ];

            // Emit JSON line to stdout for logging / consumer
            echo json_encode($out, JSON_UNESCAPED_UNICODE) . "\n";
            flush();
        }
    }

    fclose($fp);
    sleep($reconnectDelay);
}
