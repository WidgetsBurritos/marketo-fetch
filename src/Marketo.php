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
    if ($this->isValidCacheDirectory($dir)) {
      $this->cacheDir = $dir;
    }
    else {
      throw new \Exception('Cache directory is invalid.');
    }
  }

  /**
   * Indicates whether or not caching is enabled based on presence of cache dir.
   */
  public function isCaching() {
    return $this->isValidCacheDirectory($this->cacheDir);
  }

  /**
   * Indicates whether or not a cache directory is valid.
   */
  private function isValidCacheDirectory($cache_dir) {
    return !empty($cache_dir) && is_readable($cache_dir) && is_writeable($cache_dir);
  }

  /**
   * Warms cache by loading all the resources from Marketo.
   */
  public function warmCache() {
    if (!$this->isCaching()) {
      throw new \Exception('Caching currently disabled.');
    }
    $this->getAllLandingPageTemplates();
    $this->getAllLandingPages();
    $this->getVariablesUsedInTemplates();
    $this->getVariablesDefinedOnPages();
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
    // We need to strip out the access token to prevent the cache from becoming
    // irrelevant. Also probably is good from a security posture.
    $cache_key = md5(str_replace($this->accessToken, '', $url));
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
   * Retrieves all landing page templates.
   */
  public function getAllLandingPageTemplateContent() {
    $templates = $this->getAllLandingPageTemplates();
    $all = [];
    foreach ($templates as $template) {
      $endpoint = "/rest/asset/v1/landingPageTemplate/{$template->id}/content.json";
      if ($this->debugMode) {
        print "{$endpoint}: {$template->name} ... ";
      }
      $response = $this->makeGetRequest($endpoint);
      $all[$template->id] = [
        'template' => $template,
        'response' => $response->result[0],
      ];

      if ($this->debugMode) {
        print 'done' . PHP_EOL;
      }
      if (!$response->__loadedFromCache) {
        sleep($this->sleepSeconds);
      }
    }

    return $all;
  }

  public function getVariableGroupsFromTemplates() {
    $templates = $this->getAllLandingPageTemplateContent();
    $groups = [];
    foreach ($templates as $template) {
      if (!empty($template['response']->content)) {
        $variables = static::parseHtmlForVariables($template['response']->content);
        $variable_list = implode(':', $variables);
        $group_id = md5($variable_list);
        $groups[$group_id]['variable_list'] = $variable_list;
        $groups[$group_id]['variable_ct'] = count($variables);
        $groups[$group_id]['templates'][$template['template']->id] = $template['template']->name;
      }
    }
    return $groups;
  }

  /**
   * Retrieves all variables referenced in landing page templates.
   */
  public function getVariablesUsedInTemplates() {
    $templates = $this->getAllLandingPageTemplateContent();
    $all = [];
    foreach ($templates as $template) {
      if (!empty($template['response']->content)) {
        $variables = static::parseHtmlForVariables($template['response']->content);
        if (!empty($variables)) {
          foreach ($variables as $variable) {
            $all[$variable][$template['template']->id] = $template['template']->name;
          }
        }
      }
    }
    ksort($all);
    return $all;
  }

}
