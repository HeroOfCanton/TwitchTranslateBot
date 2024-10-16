<?php

namespace TranslateBot\lib;

use TranslateBot\api\API;

class TranslateBot {

    public $channel;
    public $bot_config;
    public $API;

    public function __construct($config) {
        $this->bot_config = $config;
        $this->API = new API();
    }

    public function authenticate() {

    }
}