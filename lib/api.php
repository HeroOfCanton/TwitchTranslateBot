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

        // {
        //     "access_token": "jostpf5q0uzmxmkba9iyug38kjtgh",
        //     "expires_in": 5011271,
        //     "token_type": "bearer"
        // }
        try {
            $response = $this->botcurl->post($endpoint, $params, [], []);
        }
        catch (Exception $e) {

        }

        $response = json_decode($response);

        return $response;
    }
}