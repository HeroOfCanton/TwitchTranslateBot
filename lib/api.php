<?php

namespace TranslateBot\api;

use Exception;
use TranslateBot\curl\BotCurl;

class API {

    private static $clientID = 'gaoiz26gvinl2f223wvw6xus1qzgpd';
    private static $clientSecret = '';
    
    private $oauth_url = 'https://id.twitch.tv';

    private BotCurl $botcurl;

    public function __construct() {
        $this->botcurl = new BotCurl('https://api.twitch.tv');
    }

    /**
     * To get an access token, send an HTTP POST request
     * to https://id.twitch.tv/oauth2/token.
     * Set the following x-www-form-urlencoded parameters as appropriate for your app.
     *
     * @return array
     */
    private function access_token() {

        $endpoint = '/oauth2/token';

        $params = [
            'client_id'     => $this->clientID,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'client_credentials'
        ];

        try {
            $oauthCurl = new BotCurl($this->oauth_url);
            $response = $oauthCurl->post($endpoint, $params, [], []);
        } catch (Exception $e) {
            throw $e;
        }

        $response = json_decode($response);

        return $response;
    }

    /**
     * Translate text using Google Translate v2 REST API.
     * Requires an API key with Translate API enabled.
     *
     * @param string $text
     * @param string $target_lang
     * @param string $google_api_key
     * @return string|null Translated text on success, null on failure
     */
    public function translate_text(string $text, string $target_lang, string $google_api_key): ?string
    {
        if (empty($text) || empty($google_api_key) || empty($target_lang)) {
            return null;
        }

        $translateCurl = new BotCurl('https://translation.googleapis.com');

        $endpoint = '/language/translate/v2';

        $url_params = [ 'key' => $google_api_key ];

        $post_data = [
            'q' => $text,
            'target' => $target_lang,
        ];

        try {
            $response = $translateCurl->post($endpoint, $url_params, $post_data, ['Content-Type: application/json']);
        } catch (Exception $e) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (empty($decoded['data']['translations'][0]['translatedText'])) {
            return null;
        }

        return $decoded['data']['translations'][0]['translatedText'];
    }
}