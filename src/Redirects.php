<?php

/**
 * @file
 * File to declare Redirects class.
 */

namespace HookUpdateDeployTools;

/**
 * Public methods for importing redirects.
 */
class Redirects implements ImportInterface {

  /**
   * Imports a set of redirects from an import csv file.
   */
  public static function import($redirect_lists) {
    $t = get_t();
    $redirect_lists = (array) $redirect_lists;
    $completed = array();
    $total_requested = count($redirect_lists);
    try {
      self::canImport();

      foreach ($redirect_lists as $key => $redirect_import_file_prefix) {
        $filename = HudtInternal::normalizeFileName($redirect_import_file_prefix);
        $page_machine_name = HudtInternal::normalizeMachineName($redirect_import_file_prefix);
        // If the file is there, process it.
        if (HudtInternal::canReadFile($filename, 'redirect')) {
          // Read the file.
          $file_contents = HudtInternal::readFileToString($filename, 'redirect');
          self::parseList($file_contents);

          // Verification usually happens here.  In this case though, there
          // are likely too many redirects to verify, and they may go external.
          // So verification will not be done on redirect imports.
          // Success assumed.
          $message = 'Redirects from: @machine_name - imported.';
          global $base_url;
          $link = "{$base_url}/{$result['page']->path}";
          $vars = array(
            '@machine_name' => $page_machine_name,
          );
          Message::make($message, $vars, WATCHDOG_INFO, 1, $link);
          $completed[$page_machine_name] = $t('Imported');
        }
      }
    }
    catch (\Exception $e) {
      $vars = array(
        '!error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
      );
      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Redirects import denied because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      // Output a summary before shutting this down.
      $done = HudtInternal::getSummary($completed, $total_requested, 'Imported');
      Message::make($done, array(), FALSE, 1);
      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }

    $done = HudtInternal::getSummary($completed, $total_requested, 'Imported');
    return $done;
  }


  /**
   * Check if Redirect is enabled and import functions are available.
   *
   * @return bool
   *   TRUE if useable.
   */
  public static function canImport() {
    // Check that the redirect module exists.
    Check::canUse('redirect');
    Check::canCall('redirect_hash');
    Check::canCall('redirect_load_by_hash');
    Check::canCall('redirect_save');

    return TRUE;
  }

  /**
   * Create an empty redirect object.
   *
   * @return object
   *   Empty redirect object.
   */
  private static function newRedirect() {
    $empty_redirect = new \stdClass();
    $empty_redirect->type = 'redirect';
    $empty_redirect->redirect = '';
    $empty_redirect->redirect_options = array();
    $empty_redirect->source = '';
    $empty_redirect->source_options = array();
    $empty_redirect->language = LANGUAGE_NONE;
    $empty_redirect->rid = NULL;
    $empty_redirect->uid = $GLOBALS['user']->uid;
    $empty_redirect->status_code = 0;
    $empty_redirect->count = 0;
    $empty_redirect->access = 0;
    $empty_redirect->hash = '';

    return $empty_redirect;
  }

  /**
   * Create redirects from a list of redirects.
   *
   * @param string $redirect_list
   *   List of comma separated redirects in the form of request, destination \n.
   */
  private static function parseList($redirect_list) {
    self::canImport();
    $output = array(
      'created' => array(),
      'infinite' => array(),
      'existing' => array(),
      'home' => array(),
      'summary' => '',
    );

    // Convert the lines from the list into array elements.
    // Each element should be old-location(source), new-location(redirect).
    // source urls can have ? but no #.  redirect urls can have both ? and #.
    $redirects = explode("\n", $redirect_list);
    // Count the number of lines in the import list.
    $line_count = count($redirects);
    $output['summary'] = t('@line_count redirects to be processed.', array('@line_count' => $line_count));

    // Cycle through the redirects.
    foreach ($redirects as $index_redirects => $row) {
      $original_import_row = self::cleanLineEnds($row);
      $line_to_process = explode(',', $row);
      // Get old URL (request).
      $old_url = (!empty($line_to_process[0])) ? $line_to_process[0] : '';
      self::cleanUrlEnds($old_url);

      // Get new URL (destination).
      $new_url = (!empty($line_to_process[1])) ? $line_to_process[1] : '';
      self::cleanUrlEnds($new_url);
      // Process the redirect (new url) - can contain both ? and #,
      // can also be a new domain.
      $new_url_parsed = self::parseCompleteUrl($new_url);
      $new_url_full = $new_url_parsed['completeURL'];

      // Generate the $redirect_object as needed by redirect_save function.
      // @see Redirect API.
      $redirect_object = self::newRedirect();
      $redirect_object->redirect = $new_url_parsed['fullURI'];
      // Account for both hash and query presense.
      if (!empty($new_url_parsed['query']) || !empty($new_url_parsed['fragment'])) {
        // There is either a query(?) or fragment(#) to process.
        // Check for fragments (#).
        if (!empty($new_url_parsed['fragment'])) {
          // There is a fragment to process.
          $redirect_object->redirect_options['fragment'] = $new_url_parsed['fragment'];
        }
        // Check for query parameters(?).
        if (!empty($new_url_parsed['query'])) {
          // There are query parameters to process.
          $query = self::extractUrlParameters($new_url_parsed['query']);
          $redirect_object->redirect_options['query'] = $query['query'];
        }
      }

      // Process the source (old url and turn it into a path).
      // It can have query(?) but can not have a fragment(#).
      $old_url_parsed = self::parseCompleteUrl($old_url);
      // Full version of old url should not include scheme, host or fragment.
      $old_url_full  = (!empty($old_url_parsed['path'])) ? $old_url_parsed['path'] : '';
      $old_url_full .= (!empty($old_url_parsed['query'])) ? '?' . $old_url_parsed['query'] : '';

      $variables = array(
        '@old' => $old_url_full,
        '@new' => $new_url_full,
      );

      $redirect_object->source = $old_url_parsed['path'];

      // Check for query parameters.
      if (!empty($old_url_parsed['query'])) {
        $query = self::extractUrlParameters($old_url_parsed['query']);
        $redirect_object->source_options['query'] = $query['query'];
      }

      // Check for hash fragment - if it has one, proceed but set a message
      // that it is being dropped.
      $modified = '';
      if (!empty($old_url_parsed['fragment'])) {
        $modified = t(': DISCARDED fragment from original path: @fragment', array('@fragment' => $old_url_parsed['fragment']));
      }

      // Check if the the original path is the home page.
      if ((!empty($old_url_full)) && ($old_url_full !== '<front>')) {
        // Check old url and new url are not the same, prevent an infinite loop.
        if ($old_url_full != $new_url_full) {
          // Verify that the redirect does not exist already.
          $hash = redirect_hash($redirect_object);
          if (!redirect_load_by_hash($hash)) {
            // Redirect does not exist, so we are clear to save it.
            redirect_save($redirect_object);
            $new_url_full = (empty($new_url_full)) ? 'ROOT' : $new_url_full;
            $variables['@modified'] = (empty($modified)) ? '' : $modified;
            $output['created'][] = t('@old redirects to @new @modified', $variables);
          }
          else {
            // Redirect does exist.  Do not overwrite.
            $output['existing'][] = $old_url_full;
          }

        }
        else {
          // Old and new are identical.  Would be an infinite loop.
          $output['infinite'][] = t("@old loops to itself at @new", $variables);
        }
      }
      else {
        // This is a home page redirect with no parameters.
        $output['home'][] = t("@old is a home page redirect", $variables);
      }
    }

    self::outputReport($output);
    self::outputReportSummary($output);

  }


  /**
   * Submit handler for processing the import form.
   */
  public static function parseForm($form, &$form_state) {
    $redirect_list = $form_state['values']['redirect_import_txt'];
    self::parseList($redirect_list);

  }

  /**
   * Outputs a detailed report to screen or terminal of has been processed.
   *
   * @param array $output
   *   Array of arrays as compiled by parseList.
   */
  private static function outputReport(&$output) {
    $messsage = '';
    // Created.
    if (!empty($output['created'])) {
      $messsage .= t('Created:') . "\n";
      foreach ($output['created'] as $created) {
        $messsage .= "+ {$created}\n";
      }
    }

    // Existing.
    if (!empty($output['existing'])) {
      $messsage .= t('Existing redirects skipped:') . "\n";
      foreach ($output['existing'] as $exists) {
        $messsage .= "- {$exists}\n";
      }
    }

    // Infinite.
    if (!empty($output['infinite'])) {
      $messsage .= t('Infinite redirects skipped:') . "\n";
      foreach ($output['infinite'] as $infinite) {
        $messsage .= "- {$infinite}\n";
      }
    }

    // Home Page.
    if (!empty($output['home'])) {
      $messsage .= t('Home page redirects skipped:') . "\n";
      foreach ($output['home'] as $home) {
        $messsage .= "- {$home}\n";
      }
    }

    hudt_squeal($messsage);
  }

  /**
   * Outputs a summary report to screen or terminal of has been processed.
   *
   * @param array $output
   *   Array of arrays as compiled by parseList.
   */
  private static function outputReportSummary(&$output) {
    $summary = t("Redirect Import Summary:");
    $summary .= "\n";
    $summary .= $output['summary'];
    $summary .= "\n";

    $text = t("@count Redirects written to the 'redirects' table.", array('@count' => count($output['created'])));
    $summary .= "  +  {$text}\n";

    $text = t("@count already existed and skipped.", array('@count' => count($output['existing'])));
    $summary .= "  -  {$text}\n";

    $text = t("@count infinite redirects skipped.", array('@count' => count($output['infinite'])));
    $summary .= "  -  {$text}\n";

    $text = t("@count redirects from the home page skipped.", array('@count' => count($output['home'])));
    $summary .= "  -  {$text}\n";

    $messsage = \HookUpdateDeployTools\Message::make($summary, array(), WATCHDOG_INFO);
    $breaker = "\n\n------------------------------------------------------------------------\n";
    hudt_squeal($breaker . $messsage);
  }

  /**
   * Trim the ends of the string.
   *
   * @param string $string
   *   The string to be trimmed.
   *
   * @return string
   *   The trimmed string.
   */
  private static function cleanLineEnds($string) {
    $string = trim($string);
    $string = rtrim($string, "\n");

    return $string;
  }

  /**
   * Remove spaces and slashes.
   *
   * @param string $string
   *   The string to be trimmed. (by reference)
   */
  private static function cleanUrlEnds(&$string) {
    // Trim initial /.
    $chars_to_trim = '/ ';
    $string = ltrim($string, $chars_to_trim);
    // Trim trailing slashes.
    $string = rtrim($string, $chars_to_trim);
    // Trim any new extra spaces from the end of the urls.
    $string = rtrim($string);
  }


  /**
   * Parses the url and creates some variations needed for redirects.
   *
   * @param string $url
   *   The url to be parsed (by reference)
   *
   * @return array
   *   The url fully parsed.
   */
  private static function parseCompleteUrl(&$url) {
    self::fixBadFragment($url);
    $parsed_url = parse_url($url);
    self::fixMissingScheme($url, $parsed_url);

    // Trim left and right  slashes and empty spaces from path.
    $parsed_url['path'] = (!empty($parsed_url['path'])) ? trim($parsed_url['path']) : '';
    $parsed_url['path'] = (!empty($parsed_url['path'])) ? trim($parsed_url['path'], '/') : '';
    // Assemble fullURI which lacks query and fragment.
    $parsed_url['fullURI'] = (!empty($parsed_url['scheme'])) ? $parsed_url['scheme'] . '://' : '';
    $parsed_url['fullURI'] .= (!empty($parsed_url['host'])) ? $parsed_url['host'] . '/' : '';
    $parsed_url['fullURI'] .= (!empty($parsed_url['path'])) ? $parsed_url['path'] : '';
    // Assemble completeURL which includes query and fragment.
    $parsed_url['completeURL']  = $parsed_url['fullURI'];
    $parsed_url['completeURL'] .= (!empty($parsed_url['query'])) ? '?' . $parsed_url['query'] : '';
    $parsed_url['completeURL'] .= (!empty($parsed_url['fragment'])) ? '#' . $parsed_url['fragment'] : '';

    return $parsed_url;
  }

  /**
   * Alters  url in the case where fragment incorrectly comes before the query.
   *
   * @param string $url
   *   A URL to be checked and fixed (by reference).
   */
  private static function fixBadFragment(&$url) {
    $fragment_location = strpos($url, '#');
    $query_location = strpos($url, '?');
    // Fragment can not come before query or it breaks parse_url().
    if ((!empty($fragment_location)) && (!empty($query_location)) && ($query_location > $fragment_location)) {
      // Fragment is in the wrong location, so move it.
      $frag_length = $query_location - $fragment_location;
      $fragment = substr($url, $fragment_location, $frag_length);
      // Remove the fragment from the url.
      $url = str_replace($fragment, '', $url);
      // Put the fragment on the end.
      $url = $url . $fragment;
    }
  }


  /**
   * Adds in a scheme if it is missing.
   *
   * @param string $url
   *   The url to fix the scheme on. (by reference)
   *
   * @param array $parsed_url
   *   The fully parsed url (by reference).
   */
  private static function fixMissingScheme(&$url, &$parsed_url) {
    // Check for URL scheme.  If the scheme is missing, the host will be too,
    // but may have been incorrectly pushed into the path.
    if (empty($parsed_url['scheme'])) {
      // Means it may not be a full domain or is just missing a scheme.
      $tld_check = array(
        '.com',
        '.edu',
        '.gov',
        '.net',
        '.org',
        '.us',
      );

      // See if it resembles a full url.
      foreach ($tld_check as $tld) {
        if ((!empty($parsed_url['path'])) && (stripos($parsed_url['path'], $tld) > 1)) {
          // Seems to contain a domain name but is missing scheme so add it.
          // Assuming http is safer than assuming https.
          $url = 'http://' . $url;
          // Remake it with the newly formed url.
          $parsed_url = parse_url($url);
        }
      }
    }
  }


  /**
   * Breaks url query string into an array.
   *
   * @param string $parameters_string
   *   A text string consisting of everything to the right of the ? in the url.
   *
   * @return array
   *   Array of params in param => value pairs.
   */
  private static function extractUrlParameters($parameters_string) {
    $params_array = array();
    if (!empty($parameters_string)) {
      // Means there is something to process.
      $param_elements = explode('&', $parameters_string);
      foreach ($param_elements as $param_element) {
        $param = explode('=', $param_element);
        $params_array['query'][$param[0]] = $param[1];
      }
    }

    return $params_array;
  }


  /**
   * Gets the import form array.
   *
   * @return array
   *   Drupal form array.
   */
  public static function getImportForm() {
    $form = array();
    $form['#prefix'] = t('<p>Hook Update Deploy Tools module allows for import of a CSV list of redirects in the order "old-path, new-path" where the path should be root relative without using the initial "/".  The new-path can also support a full (http://somedomain.com/somepage.htm) URL if a redirect needs to go off-site.</p><p>Redirects to the home page should be listed as <i>&lt;front&gt;</i> or <b>/</b></p><p>Large imports should be broken down into 1000 or fewer per import.</p>');
    $form['redirect_import_txt'] = array(
      '#title' => t('csv-list-import'),
      '#type' => 'textarea',
      '#default_value' => 'old-path, newpath',
    );
    $form['submit'] = array('#type' => 'submit', '#value' => t('Import These Redirects'));
    $form['#submit'][] = 'hook_update_deploy_tools_redirect_import_parse_form';

    return $form;
  }
}
