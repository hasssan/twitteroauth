<?php

namespace TwitterOAuth;

use TwitterOAuth\OAuth;

/*
 * Abraham Williams (abraham@abrah.am) http://abrah.am
 *
 * The first PHP Library to support OAuth for Twitter's REST API.
 */

/**
 * Twitter OAuth class
 */
class Api {
  /* Contains the last HTTP status code returned. */
  public $http_code;
  /* Contains the last API call. */
  public $url;
  /* Set up the API root URL. */
  public $host = "https://api.twitter.com/1.1/";
  /* Set timeout default. */
  public $timeout = 30;
  /* Set connect timeout. */
  public $connecttimeout = 30; 
  /* Verify SSL Cert. */
  public $ssl_verifypeer = FALSE;
  /* Respons format. */
  public $format = 'json';
  /* Decode returned json data. */
  public $decode_json = TRUE;
  /* Contains information regarding the last transfer. */
  public $http_info;
  /* Contains the last HTTP headers returned. */
  public $response_headers;
  /* Set the useragnet. */
  public $useragent = 'TwitterOAuth v0.2.0-beta2';
  /* Immediately retry the API call if the response was not successful. */
  //public $retry = TRUE;
  public $token;
  /* Give access to setting the headers */
  public $http_headers = array('Expect:');

    /** @var  string Login Page Language Choice */
    protected $lang;



  /**
   * Set API URLS
   */
  function accessTokenURL()  { return 'https://api.twitter.com/oauth/access_token'; }
  function authenticateURL() { return 'https://twitter.com/oauth/authenticate'; }
  function authorizeURL()    { return 'https://twitter.com/oauth/authorize'; }
  function requestTokenURL() { return 'https://api.twitter.com/oauth/request_token'; }

  /**
   * Debug helpers
   */
  function lastStatusCode() { return $this->http_status; }
  function lastAPICall() { return $this->last_api_call; }

  /**
   * construct TwitterOAuth object
   */
  function __construct($consumer_key, $consumer_secret, $oauth_token = NULL, $oauth_token_secret = NULL) {
    $this->sha1_method = new OAuth\SignatureMethod\HMACSHA1();
    $this->consumer = new OAuth\Consumer($consumer_key, $consumer_secret);
    if (!empty($oauth_token) && !empty($oauth_token_secret)) {
      $this->token = new OAuth\Consumer($oauth_token, $oauth_token_secret);
    } else {
      $this->token = NULL;
    }
  }

  function setTokens($oauth_token, $oauth_token_secret)
  {
	if (!empty($oauth_token) && !empty($oauth_token_secret)) {
      $this->token = new OAuth\Consumer($oauth_token, $oauth_token_secret);
    } else {
      $this->token = NULL;
    }
  }


  /**
   * Get a request_token from Twitter
   *
   * @returns a key/value array containing oauth_token and oauth_token_secret
   */
  function getRequestToken($oauth_callback = NULL) {
    $parameters = array();
    if (!empty($oauth_callback)) {
      $parameters['oauth_callback'] = $oauth_callback;
    } 
    $request = $this->oAuthRequest($this->requestTokenURL(), 'GET', $parameters);
    $token = OAuth\Util::parse_parameters($request);
    $this->token = new OAuth\Consumer($token['oauth_token'], $token['oauth_token_secret']);
    return $token;
  }

  /**
   * Get the authorize URL
   *
   * @returns a string
   */
  function getAuthorizeURL($token, $sign_in_with_twitter = TRUE) {
    if (is_array($token)) {
      $token = $token['oauth_token'];
    }
    if (empty($sign_in_with_twitter)) {
      return $this->authorizeURL() . "?oauth_token={$token}" . $this->getLang();
    } else {
       return $this->authenticateURL() . "?oauth_token={$token}" . $this->getLang();
    }
  }

  /**
   * Exchange request token and secret for an access token and
   * secret, to sign API calls.
   *
   * @returns array("oauth_token" => "the-access-token",
   *                "oauth_token_secret" => "the-access-secret",
   *                "user_id" => "9436992",
   *                "screen_name" => "abraham")
   */
  function getAccessToken($oauth_verifier = FALSE) {
    $parameters = array();
    if (!empty($oauth_verifier)) {
      $parameters['oauth_verifier'] = $oauth_verifier;
    }
    $request = $this->oAuthRequest($this->accessTokenURL(), 'POST', $parameters);
    $token = OAuth\Util::parse_parameters($request);
	if(empty($token['oauth_token']) || empty($token['oauth_token_secret']))
		return array();
	
    $this->token = new OAuth\Consumer($token['oauth_token'], $token['oauth_token_secret']);
    return $token;
  }

  /**
   * One time exchange of username and password for access token and secret.
   *
   * @returns array("oauth_token" => "the-access-token",
   *                "oauth_token_secret" => "the-access-secret",
   *                "user_id" => "9436992",
   *                "screen_name" => "abraham",
   *                "x_auth_expires" => "0")
   */  
  function getXAuthToken($username, $password) {
    $parameters = array();
    $parameters['x_auth_username'] = $username;
    $parameters['x_auth_password'] = $password;
    $parameters['x_auth_mode'] = 'client_auth';
    $request = $this->oAuthRequest($this->accessTokenURL(), 'POST', $parameters);
    $token = OAuth\Util::parse_parameters($request);
    $this->token = new OAuth\Consumer($token['oauth_token'], $token['oauth_token_secret']);
    return $token;
  }

  /**
   * GET wrapper for oAuthRequest.
   */
  function get($url, $parameters = array()) {
    $response = $this->oAuthRequest($url, 'GET', $parameters);
    if ($this->format === 'json' && $this->decode_json) {
      return json_decode($response);
    }
    return $response;
  }
  
  /**
   * POST wrapper for oAuthRequest.
   */
  function post($url, $parameters = array()) {
    $response = $this->oAuthRequest($url, 'POST', $parameters);
    if ($this->format === 'json' && $this->decode_json) {
      return json_decode($response);
    }
    return $response;
  }

  /**
   * DELETE wrapper for oAuthReqeust.
   */
  function delete($url, $parameters = array()) {
    $response = $this->oAuthRequest($url, 'DELETE', $parameters);
    if ($this->format === 'json' && $this->decode_json) {
      return json_decode($response);
    }
    return $response;
  }

  /**
   * Format and sign an OAuth / API request
   */
  function oAuthRequest($url, $method, $parameters) {
    if (strrpos($url, 'https://') !== 0 && strrpos($url, 'http://') !== 0) {
      $url = "{$this->host}{$url}.{$this->format}";
    }
    $request = OAuth\Request::from_consumer_and_token($this->consumer, $this->token, $method, $url, $parameters);
    $request->sign_request($this->sha1_method, $this->consumer, $this->token);
    switch ($method) {
    case 'GET':
      return $this->http($request->to_url(), 'GET');
    default:
      return $this->http($request->get_normalized_http_url(), $method, $request->to_postdata());
    }
  }

  /**
   * Make an HTTP request
   *
   * @return API results
   */
  function http($url, $method, $postfields = NULL) {
    $this->http_info = array();
    $ci = curl_init();
    /* Curl settings */
    curl_setopt($ci, CURLOPT_USERAGENT, $this->useragent);
    curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
    curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout);
    curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ci, CURLOPT_HTTPHEADER, $this->http_headers);
    curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
    curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
    curl_setopt($ci, CURLOPT_HEADER, TRUE);

    switch ($method) {
      case 'POST':
        curl_setopt($ci, CURLOPT_POST, TRUE);
        if (!empty($postfields)) {
          curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
        }
        break;
      case 'DELETE':
        curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if (!empty($postfields)) {
          $url = "{$url}?{$postfields}";
        }
    }

    curl_setopt($ci, CURLOPT_URL, $url);
    $response = curl_exec($ci);
    $this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
    $this->http_info = array_merge($this->http_info, curl_getinfo($ci));
    $header_string = substr($response, 0, $this->http_info['header_size']);
    $body = substr($response, $this->http_info['header_size']);
    $this->response_headers = $this->parseHeaders($header_string);

    $this->url = $url;
    curl_close ($ci);
    return $body;
  }

  /**
   * Parse header string into array
   * see: http://stackoverflow.com/questions/6368574/how-to-get-the-functionality-of-http-parse-headers-without-pecl
   */
  private function parseHeaders($raw_headers) {
    $headers = array();
    $key = '';

    foreach(explode("\n", $raw_headers) as $i => $h) {
      $h = explode(':', $h, 2);

      if (isset($h[1])) {
        $key = strtolower($h[0]);
        if (!isset($headers[$key])) {
          $headers[$key] = trim($h[1]);
        } elseif (is_array($headers[$key])) {
          $headers[$key] = array_merge($headers[$key], array(trim($h[1])));
        } else {
          $headers[$key] = array_merge(array($headers[$key]), array(trim($h[1])));
        }
      } else {
        if (substr($h[0], 0, 1) == "\t") {
          $headers[$key] .= "\r\n\t".trim($h[0]);
        } elseif (!$key) {
          $headers[0] = trim($h[0]);
        }
      }
    }
    return $headers;
  }

  /**
   * Get the header info to store.
   */
  function getHeader($ch, $header) {
    $i = strpos($header, ':');
    if (!empty($i)) {
      $key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
      $value = trim(substr($header, $i + 2));
      $this->http_header[$key] = $value;
    }
    return strlen($header);
  }

    /**
     * Setter for Language
     *
     * @param $lang string
     */
    public function setLang($lang)
    {
        $this->lang = '&lang=' . $lang;
    }

    /**
     * Getter for Language
     *
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }
}
