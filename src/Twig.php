<?php

namespace WidgetsBurritos\MarketoFetch;

class Twig {

  /**
   * Constructs a new Twig instance.
   */
  public function __construct($twig_dir) {
    if (!$this->isValidTwigDirectory($twig_dir)) {
      throw new \Exception('Twig directory is invalid.');
    }
    $this->twigDir = $twig_dir;
  }

  /**
   * Indicates whether or not a cache directory is valid.
   */
  private function isValidTwigDirectory($twig_dir) {
    return !empty($twig_dir) && is_readable($twig_dir) && is_writeable($twig_dir);
  }

  /**
   * Generates a twig file name based on the specified template name.
   */
  public static function generateFileName($template_name) {
    return preg_replace('/\W+/', '-', strtolower(trim($template_name))) . '.html.twig';
  }

  /**
   * Converts a Marketo Template to Twig.
   */
  public static function convertMarketoTemplateToTwig($html) {
    return preg_replace('/\${([a-zA-Z0-9\.\$_\-]+)}/', '{{ $1 }}', $html);
  }

  /**
   * Saves a template based on a template array.
   */
  public function saveTemplate($template) {
    $file_name = static::generateFileName($template['template']->name);
    $file_path = "{$this->twigDir}/{$file_name}";
    if (!empty($template['response']->content)) {
      $content = static::convertMarketoTemplateToTwig($template['response']->content);
      file_put_contents($file_path, $content);
    }
    else {
      throw new \Exception("Invalid template content.");
    }
  }

}
