<?php

namespace TranslateBot\curl;

class BotCurl {

    /**
	 * @var int
	 */
	private $last_http_code;
	private $last_headers_sent = null;
	private $last_request = null;
	private $last_request_type = null;
    private $last_response = null;
	private $last_curl_error = null;
    
    private $base_url;
    private $current_url;
    private $ch;

    private $global_get_params = [];
	private $global_headers = [];

    /** @var array $response_headers Key value list of headers in response. Key always lowercase */
	private $response_headers = [];

    public function __construct($base_url) {

        $this->base_url = trim($base_url);

        $default_curl_params = [
			CURLOPT_TIMEOUT => 60,
			CURLOPT_CONNECTTIMEOUT => 60,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_USERAGENT => '',
			CURLOPT_HEADERFUNCTION => [$this, 'parse_header_line'],
		];

		$this->set_curl_params($default_curl_params);
    }

    /**
	 * Get the current Base URL
	 * @return string
	 */
	public function get_base_url() {
		return $this->base_url;
	}
	
	/**
	 * Change base_url for this curl object
	 * @param int $base_url
	 */
	public function change_base_url($base_url){
		$this->base_url = $base_url;
	}
	
	/**
	 * Return the http status code for the most recent API call
	 * @return int
	 */
	public function get_last_http_code(){
		return $this->last_http_code;
	}
	
	/**
	 * Return raw response string
	 * @return string
	 */
	public function get_last_raw_response(){
		return $this->last_response;
	}
	
	/**
	 * Return raw request string
	 * @return string
	 */
	public function get_last_raw_request(){
		return $this->last_request;
	}
	
	/**
	 * Get HTTP type of last request. EG. GET, POST, DELETE, etc
	 * @return string
	 */
	public function get_last_request_type(){
		return $this->last_request_type;
	}

	/**
	 * How many seconds for API response to be received and parsed
	 * @return float Seconds. Eg: 1.9389482
	 */
	public function get_last_run_time(){
		return $this->runTime;
	}

    /**
     * Curl Init. Does not need to be called unless you close curl.
     *
     * @return void
     */
	public function curl_init(){
		if(!empty($this->ch)){
			unset($this->ch);
		}
		$this->current_url = null;
		$this->ch = curl_init();
	}

    /**
     * Undocumented function
     *
     * @param [type] $curl_params
     * @return void
     */
    public function set_curl_params($curl_params) {
        if(!empty($curl_params)){
			curl_setopt_array($this->ch, $curl_params);
		}
    }
}