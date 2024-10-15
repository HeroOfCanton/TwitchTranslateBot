<?php

namespace TranslateBot\lib;

class TranslateBot {

    public $channel;
    public $bot_config;

    public function __construct($config) {
        $this->bot_config = $config;
    }
}