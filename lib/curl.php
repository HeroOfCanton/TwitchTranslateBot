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

    /** @var array $response_headers Key value list of headers in response. */
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
	 * Get key/value list of response headers. All Keys are ALWAYS lowercase regardless of how they were received (HTTP2 standard)
	 * @return array Header name as lowercase Key. Value as value
	 */
	public function get_response_headers(){
		return $this->response_headers;
	}
	
	/**
	 * Get a specific response header
	 * @param string $header Response Header to get value for. Case insensitive
	 * @return string|null Response Header value
	 */
	public function get_response_header($header){
		if(empty($header)){
			return null;
		}
		
		return $this->response_headers[strtolower($header)] ?? null;
	}
	
	/**
	 * Get key/value list of request headers sent
	 * @return array Header name as Key. Value as value
	 */
	public function get_request_headers(){
		return $this->last_headers_sent;
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
     * @param array $curl_params
     * @return void
     */
    public function set_curl_params($curl_params) {
        if(!empty($curl_params)){
			curl_setopt_array($this->ch, $curl_params);
		}
    }

    /**
	 * Perform GET call.
     * 
	 * @param string $endpoint
	 * @param array $url_params key=>value pairs. "?" will be added automatically
	 * @param array $header numerically indexed array of header strings
	 * @return mixed Parsed Response
	 */
	public function get($endpoint='', $url_params=array(), $header=array()){
		return $this->make_curl_call('GET', $endpoint, $url_params, array(), $header);
	}
	
	/**
	 * Perform POST call. If object initialized as JSON type, post_data will be converted from array to JSON
     * 
	 * @param string $endpoint
	 * @param array $url_params key=>value pairs. "?" will be added automatically
	 * @param mixed $post_data string or array
	 * @param array $header numerically indexed array of header strings
	 * @return mixed Parsed Response
	 */
	public function post($endpoint='', $url_params=array(), $post_data=array(), $header=array()) {
		return $this->make_curl_call('POST', $endpoint, $url_params, $post_data, $header);
	}
	
	/**
	 * Perform PUT call. If object initialized as JSON type, post_data will be converted from array to JSON
     * 
	 * @param string $endpoint
	 * @param array $url_params key=>value pairs. "?" will be added automatically
	 * @param mixed $post_data string or array
	 * @param array $header numerically indexed array of header strings
	 * @param boolean $log_response
	 * @return mixed Parsed Response
	 */
	public function put($endpoint='', $url_params=array(), $post_data=array(), $header=array()) {
		return $this->make_curl_call('PUT', $endpoint, $url_params, $post_data, $header);
	}
	
	/**
	 * Perform PATCH call. If object initialized as JSON type, post_data will be converted from array to JSON
     * 
	 * @param string $endpoint
	 * @param array $url_params key=>value pairs. "?" will be added automatically
	 * @param mixed $post_data string or array
	 * @param array $header numerically indexed array of header strings
	 * @param boolean $log_response
	 * @return mixed Parsed Response
	 */
	public function patch($endpoint='', $url_params=array(), $post_data=array(), $header=array()){
		return $this->make_curl_call('PATCH', $endpoint, $url_params, $post_data, $header);
	}

	/**
	 * Perform DELETE call. If object initialized as JSON type, post_data will be converted from array to JSON
	 * @param string $endpoint
	 * @param array $url_params key=>value pairs. "?" will be added automatically
	 * @param mixed $post_data string or array
	 * @param array $header numerically indexed array of header strings
	 * @param boolean $log_response
	 * @return mixed Parsed Response
	 */
	public function delete($endpoint='', $url_params=array(), $post_data=array(), $header=array()){
		return $this->make_curl_call('DELETE', $endpoint, $url_params, $post_data, $header);
	}

    /**
	 * Internal function to perform all curl call steps: prepare call, make call, parse result, and handle errors & retry
	 * @param string $request_type GET|POST|PUT
	 * @param string $endpoint
	 * @param array $url_params "?" will be added automatically
	 * @param mixed $post_data string or array
	 * @param array $header numerically indexed array of header strings
	 * @param boolean $log_response If false and datalogger is used then the body of the response isn't logged
	 * @return mixed Response
	 * @throws Exception
	 */
	private function make_curl_call($request_type, $endpoint, $url_params=array(), $post_data=array(), $header=array()){

		$num_retries = $this->num_auto_retries;
		$this->last_request_type = $request_type;
		
		do{
			// Reset any stored request data
			$this->last_request = $this->last_headers_sent =
					$this->last_response = $this->last_curl_error =
					$this->last_http_code = null;
			
			$num_retries--;
			$retry = false;
			try {
				$this->pre_call($endpoint, $url_params, $post_data, $header, $request_type);

				$this->last_response = curl_exec($this->ch);

				$result = $this->post_call($log_response, $request_type);

			} catch(Exception $e) {
				$retry = true;
				
				// Always sleep for over rate limit, even if not retrying
				if(!empty($this->rate_limit_http_code) && !empty($this->rate_limit_sleep) && in_array($e->getHTTPCode(), $this->rate_limit_http_code) ){
					$this->log->logWarn("Over rate limit. Sleeping {$this->rate_limit_sleep} seconds");
					sleep($this->rate_limit_sleep);
				}
				
				// Detect and refresh oauth access token
				if(
					// Detect HTTP code indicating Auth is expired
					(!empty($this->oauth_refresh_params['refresh_http_status']) && in_array($this->last_http_code, $this->oauth_refresh_params['refresh_http_status']))
					// OR if Exception explicitely requests a refresh
					|| (!empty($this->oauth_refresh_params['callable_function']) && $e->getAuthExpired())
				){

					
					//Returns new access_token if changed
					$ret = call_user_func_array($this->oauth_refresh_params['callable_function'],
						$this->oauth_refresh_params['function_params']);
					
					//If access_token is returned, update the oauth header in the object!
					if($ret){
						$this->oauth_refresh_params['oauth_header'] = call_user_func_array($this->oauth_refresh_params['oauth_header_update_function'],
										[$ret]);
						
					}
				}
				
				if($num_retries < 0 || $e->getRetry() == false){
					$this->temp_base_url = null;
					$this->temp_oauth_header = null;
					$this->last_graphql_type = null;

					throw $e;
				}
				
				sleep($this->auto_retry_sleep);
			}
		} while($retry && $num_retries >= 0);

		return $result;
	}
}