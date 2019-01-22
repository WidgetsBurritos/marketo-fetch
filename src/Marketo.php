<?php

namespace WidgetsBurritos\MarketoFetch;

class Marketo {

  protected $hostName;
  protected $debugMode;
  protected $sleepSeconds;
  private $accessToken;

  /**
   * Instantiates a new Marketo instance.
   */
  public function __construct($host_name, $debug_mode, $sleep_seconds) {
    $this->hostName = $host_name;
    $this->debugMode = $debug_mode;
    $this->sleepSeconds = $sleep_seconds;
    $this->accessToken = NULL;
    $this->cacheDir = NULL;
  }

  /**
   * Sets the caching directory to the specified directory.
   */
  public function setCacheDir($dir) {
    $this->cacheDir = $dir;
  }

  /**
   * Indicates whether or not caching is enabled based on presence of cache dir.
   */
  public function isCaching() {
    return !empty($this->cacheDir) && is_readable($this->cacheDir) && is_writeable($this->cacheDir);
  }

  /**
   * Warms cache by loading all the resources from Marketo.
   */
  public function warmCache() {
    $this->getAllLandingPageTemplates();
    $this->getAllLandingPages();
    $this->getVariablesDefinedOnPages();
    $this->getVariablesUsedInTemplates();
  }

  /**
   * Attempts to authenticate into marketo.
   */
  public function authenticate($client_id, $client_secret) {
    $client_id = urlencode($client_id);
    $client_secret = urlencode($client_secret);
    $url = "{$this->hostName}/identity/oauth/token?grant_type=client_credentials&client_id={$client_id}&client_secret={$client_secret}";
    $token_info = json_decode(file_get_contents($url));
    if (!empty($token_info->access_token)) {
      $this->accessToken = $token_info->access_token;
    }
    else {
      throw new \Exception('Authentication failed.');
    }
  }

  /**
   * Indicates whether or not a user is authenticated.
   */
  public function isAuthenticated() {
    return isset($this->accessToken);
  }

  /**
   * Retrieves cache by key.
   */
  private function getCacheByKey($cache_key) {
    $file = "{$this->cacheDir}/{$cache_key}.json";
    if (file_exists($file)) {
      return file_get_contents($file);
    }

    return NULL;
  }

  /**
   * Sets cache by key.
   */
  private function setCacheByKey($cache_key, $results) {
    $file = "{$this->cacheDir}/{$cache_key}.json";
    file_put_contents($file, $results);
  }

  /**
   * Makes a GET request on the specified endpoint.
   *
   * Makes use of response caching if caching is enabled.
   */
  private function makeGetRequest($endpoint, $options = []) {
    $url = $this->assembleEndpointUrl($endpoint, $options);
    $cache_key = md5($url);
    if ($this->isCaching()) {
      $cache_value = $this->getCacheByKey($cache_key);
      if (isset($cache_value)) {
        $ret = json_decode($cache_value);
        $ret->__loadedFromCache = TRUE;
        return $ret;
      }
    }
    $results = file_get_contents($url);
    if ($this->isCaching()) {
      $this->setCacheByKey($cache_key, $results);
    }
    $ret = json_decode($results);
    $ret->__loadedFromCache = FALSE;
    return $ret;
  }

  /**
   * Assembles a URL based on the specified endpoint and provided options.
   *
   * @todo Unit Test.
   */
  public function assembleEndpointUrl($endpoint, $options = []) {
    if (!$this->isAuthenticated()) {
      throw new \Exception('Authorization required.');
    }
    $url = "{$this->hostName}${endpoint}?access_token={$this->accessToken}";
    foreach ($options as $key => $value) {
      $value = urlencode($value);
      $url .= "&{$key}={$value}";
    }
    return $url;
  }

  /**
   * Returns all marketo variables referenced in the specified HTML.
   *
   * @todo UNIT TEST.
   */
  public static function parseHtmlForVariables($html) {
    if (preg_match_all('/\${([a-zA-Z0-9\.\$_\-]+)}/', $html, $matches)) {
      return array_unique($matches[1]);
    }
    return [];
  }

  /**
   * Retrieves all response content from a multi-page endpoint.
   */
  private function getAllFromPagedEndpoint($endpoint) {
    $keep_checking = TRUE;
    $max_return = 200;
    $all = [];
    for ($offset = 0; $keep_checking; $offset += $max_return) {
      if ($this->debugMode) {
        $page = 1 + floor($offset / $max_return);
        print "$endpoint: Page $page ... ";
      }
      $lp_options = [
        'maxReturn' => $max_return,
        'offset' => $offset,
      ];
      $response = $this->makeGetRequest($endpoint, $lp_options);
      $count = 0;
      if (!empty($response->result)) {
        $all = array_merge($all, $response->result);
        $count = count($response->result);
      }

      $keep_checking = ($count >= $max_return);

      if ($this->debugMode) {
        print "{$count} results." . PHP_EOL;
      }

      if (!$response->__loadedFromCache) {
        sleep($this->sleepSeconds);
      }
    }

    return $all;
  }

  /**
   * Retrieves all landing pages from Marketo.
   */
  public function getAllLandingPages() {
    return $this->getAllFromPagedEndpoint('/rest/asset/v1/landingPages.json');
  }

  /**
   * Retrieves all landing page templates from Marketo.
   */
  public function getAllLandingPageTemplates() {
    return $this->getAllFromPagedEndpoint('/rest/asset/v1/landingPageTemplates.json');
  }

  /**
   * Retrieves all variables set on individual landing pages.
   */
  public function getVariablesDefinedOnPages() {
    $landing_pages = $this->getAllLandingPages();
    foreach ($landing_pages as $landing_page) {
      $endpoint = "/rest/asset/v1/landingPage/{$landing_page->id}/variables.json";
      if ($this->debugMode) {
        print "{$endpoint}: {$landing_page->name} ... ";
      }
      $response = $this->makeGetRequest($endpoint);
      $variable_ct = 0;
      if (!empty($response->result)) {
        $variable_ct = count($response->result);
        foreach ($response->result as $variable) {
          $all[$variable->id][$landing_page->id] = $landing_page->name;
        }
      }
      if ($this->debugMode) {
        print "{$variable_ct} variables." . PHP_EOL;
      }
      if (!$response->__loadedFromCache) {
        sleep($this->sleepSeconds);
      }
    }

    ksort($all);
    return $all;
  }

  /**
   * Retrieves all variables referenced in landing page templates.
   */
  public function getVariablesUsedInTemplates() {
    $templates = $this->getAllLandingPageTemplates();
    $all = [];
    foreach ($templates as $template) {
      $endpoint = "/rest/asset/v1/landingPageTemplate/{$template->id}/content.json";
      if ($this->debugMode) {
        print "{$endpoint}: {$template->name} ... ";
      }
      $response = $this->makeGetRequest($endpoint);
      $variables = [];
      if (!empty($response->result[0]->content)) {
        $variables = static::parseHtmlForVariables($response->result[0]->content);
        if (!empty($variables)) {
          foreach ($variables as $variable) {
            $all[$variable][$template->id] = $template->name;
          }
        }
      }
      $variable_ct = count($variables);
      if ($this->debugMode) {
        print "{$variable_ct} variables." . PHP_EOL;
      }
      if (!$response->__loadedFromCache) {
        sleep($this->sleepSeconds);
      }
    }

    ksort($all);
    return $all;
  }

}
