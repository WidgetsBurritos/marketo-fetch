<?php
/**
 * Shows list of used variables.
 */

require_once('vendor/autoload.php');

use Dotenv\Dotenv;
use WidgetsBurritos\MarketoFetch\Marketo;
use WidgetsBurritos\MarketoFetch\Twig;

$dotenv = Dotenv::create(__DIR__);
$dotenv->load();

// Set config variables.
$host_name = getenv('MARKETO_HOST_NAME') ?: '';
$client_id = getenv('MARKETO_CLIENT_ID') ?: '';
$client_secret = getenv('MARKETO_CLIENT_SECRET') ?: '';
$sleep_seconds = getenv('MARKETO_SLEEP_SECONDS') ?: 10;
$debug_mode = getenv('MARKETO_DEBUG_MODE') ?: FALSE;
$cache_dir = getenv('MARKETO_CACHE_DIR');
$twig_dir = getenv('TWIG_DIR');

// Create our cache/twig directories if they don't already exist.
$dirs = [$cache_dir, $twig_dir];
foreach ($dirs as $dir) {
  if (!empty($dir) && !file_exists($dir)) {
    mkdir($dir, 0700);
  }
}

// Authenticate.
$marketo = new Marketo($host_name, $debug_mode, $sleep_seconds);
$marketo->setCacheDir($cache_dir);
$marketo->authenticate($client_id, $client_secret);

// Setup twig functionality.
$twig = new Twig($twig_dir);

// Argument evaluation.
$argument = sizeof($argv) >= 2 ? $argv[1] : NULL;
switch ($argument) {
  case 'vars-compare':
    print '=====================' . PHP_EOL;
    print 'Compare variables' . PHP_EOL;
    print '=====================' . PHP_EOL;
    $used_variables = $marketo->getVariablesUsedInTemplates();
    $defined_variables = $marketo->getVariablesDefinedOnPages();
    $combined = array_intersect_key($used_variables, $defined_variables);
    $diff = array_diff_key($used_variables, $defined_variables);
    ksort($combined);
    ksort($diff);
    print 'Shared variables:' . PHP_EOL;
    print_r(array_keys($combined));
    print 'Distinct variables:' . PHP_EOL;
    print_r(array_keys($diff));
    $used_ct = count($used_variables);
    $defined_ct = count($defined_variables);
    $combined_ct = count($combined);
    $diff_ct = count($diff);
    print "Used Variables: {$used_ct}" . PHP_EOL;
    print "Defined Variables: {$defined_ct}" . PHP_EOL;
    print "Intersecting Variables: {$combined_ct}" . PHP_EOL;
    print "Diff Variables: {$diff_ct}" . PHP_EOL;
    break;

  case 'vars-used':
    print '=====================' . PHP_EOL;
    print 'Used variables' . PHP_EOL;
    print '=====================' . PHP_EOL;
    $variables = $marketo->getVariablesUsedInTemplates();
    print_r($variables);
    $variable_ct = count($variables);
    print "Total Used Variables: {$variable_ct}" . PHP_EOL;
    break;

  case 'vars-assigned':
    print '=====================' . PHP_EOL;
    print 'Assigned variables' . PHP_EOL;
    print '=====================' . PHP_EOL;
    $variables = $marketo->getVariablesDefinedOnPages();
    print_r($variables);
    $variable_ct = count($variables);
    print "All Variables: {$variable_ct}" . PHP_EOL;
    break;

  case 'vars-groups':
    print '=====================' . PHP_EOL;
    print 'Variable Groups' . PHP_EOL;
    print '=====================' . PHP_EOL;
    $groups = $marketo->getVariableGroupsFromTemplates();
    print_r($groups);
    $group_ct = count($groups);
    print "Unique Variable Groups: {$group_ct}" . PHP_EOL;
    break;

  case 'vars-values':
    print '=====================' . PHP_EOL;
    print 'Variable Values' . PHP_EOL;
    print '=====================' . PHP_EOL;
    $variable_values = $marketo->getAllVariableValues();
    // We only care about variables that are actually used in templates.
    $template_variables = $marketo->getVariablesUsedInTemplates();
    $variables = array_intersect_key($variable_values, $template_variables);
    foreach ($variables as $variable => $details) {
      $ct = count($details);
      if ($ct >= 4) {
        $ct = '4+';
      }
      $count[$ct][] = $variable;
    }
    ksort($count);

    print_r($count);
    break;

  case 'landing-pages':
    print '=====================' . PHP_EOL;
    print 'Landing Pages' . PHP_EOL;
    print '=====================' . PHP_EOL;
    $landing_pages = $marketo->getAllLandingPages();
    print_r($landing_pages);
    $lp_ct = count($landing_pages);
    print "Landing Pages: {$lp_ct}" . PHP_EOL;
    $count = [];
    foreach ($landing_pages as $lp) {
      if (!isset($count[$lp->status])) {
        $count[$lp->status] = 0;
      }
      $count[$lp->status]++;
    }
    print_r($count);
    break;

  case 'landing-page-templates':
    print '=====================' . PHP_EOL;
    print 'Landing Page Templates' . PHP_EOL;
    print '=====================' . PHP_EOL;
    $landing_pages = $marketo->getAllLandingPageTemplates();
    $lpt_ct = count($landing_pages);
    print_r($landing_pages);
    print "Landing Page Templates: {$lpt_ct}" . PHP_EOL;
    $count = [];
    foreach ($landing_pages as $lp) {
      if (!isset($count[$lp->status])) {
        $count[$lp->status] = 0;
      }
      $count[$lp->status]++;
    }
    print_r($count);
    break;

  case 'unique-templates':
    $templates = $marketo->getAllLandingPageTemplateContent();

    break;

  case 'generate-twig-templates':
    $templates = $marketo->getAllLandingPageTemplateContent();
    foreach ($templates as $template) {
      $twig->saveTemplate($template);
    }
    break;

  case 'warm-cache':
    $marketo->warmCache();
    break;

  case 'purge-cache':
    // TODO: Rewrite this in a more PHP-friendly.
    // TODO: Write mechanism to purge only specific caches.
    if (!empty($cache_dir)) {
      `rm -f {$cache_dir}/*`;
    }
    break;

  default:
    die("Invalid argument '{$argument}'" . PHP_EOL);

}
