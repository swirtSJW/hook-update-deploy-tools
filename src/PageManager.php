<?php

/**
 * @file
 * File to declare PageManager class.
 */

namespace HookUpdateDeployTools;

/**
 * Public methods working with Panels.
 */
class PageManager implements ImportInterface, ExportInterface {

  /**
   * Imports Page Manager pages using the panels module & template.
   *
   * @param array $pages
   *   An array of hyphenated machine names of page manager pages to import.
   *
   * @throws HudtException
   */
  public static function import($pages) {
    $t = get_t();
    $completed = array();
    $pages = (array) $pages;
    $total_requested = count($pages);
    try {
      self::canImport();

      $page_manager_feature_path = HudtInternal::getStoragePath('page_manager');
      foreach ($pages as $key => $page_manager_file_prefix) {
        $filename = HudtInternal::normalizeFileName($page_manager_file_prefix);
        $page_machine_name = HudtInternal::normalizeMachineName($page_manager_file_prefix);
        // If the file is there, process it.
        if (HudtInternal::canReadFile($filename, 'page_manager')) {
          // Read the file.
          $file_contents = HudtInternal::readFileToString($filename, 'page_manager');
          $error_msg = '';

          $result = self::buildOne($file_contents, $page_machine_name);
          $operation = $result['operation'];

          // Verify that the save happened by reloading the page.
          $saved_page = page_manager_page_load($page_machine_name);

          if (!empty($saved_page)) {
            // Success.
            $message = '@operation: @machine_name - imported successfully.';
            global $base_url;
            $link = "{$base_url}/{$result['page']->path}";
            $vars = array(
              '@operation' => $operation,
              '@machine_name' => $page_machine_name,
            );
            Message::make($message, $vars, WATCHDOG_INFO, 1, $link);
            $completed[$page_machine_name] = $t('Imported') . ":$operation";
          }
          else {
            // The rule import failed.  Pass on the error message.
            $vars = array(
              '@machine_name' => $page_machine_name,
              '@file_prefix' => $page_manager_file_prefix,
            );
            $message = "The requested Page Mannager import '@machine_name' failed to create the page. Adjust your @file_prefix-export.txt text file accordingly and re-run update.";
            throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
          }
        }
      }
    }
    catch (\Exception $e) {
      $vars = array(
        '!error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
      );
      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Page Manager page import denied because: !error';
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
   * Validated Updates/Imports one page from the contents of an import file.
   *
   * @param string $contents
   *   The contents of an import file. (contains php code)
   * @param string $page_machine_name
   *   The machine name of the page to import.
   *
   * @return array
   *   Contains the elements page, operation, and edit_link.
   *
   * @throws HudtException
   *   In the event of something that fails the import.
   */
  private static function buildOne($contents, $page_machine_name) {
    // Adapted from page_manager_page_import_subtask_validate().
    ob_start();
    eval($contents);
    ob_end_clean();
    if (!isset($page) || !is_object($page)) {
      $errors = ob_get_contents();
      if (empty($errors)) {
        $errors = 'No handler found.';
      }
      $message = 'Unable to get a page from the import. Errors: @errors';
      throw new HudtException($message, array('@errors' => $errors), WATCHDOG_ERROR);
    }
    $task_name = page_manager_make_task_name('page', $page_machine_name);
    $cache = page_manager_get_page_cache($task_name);
    if (empty($cache)) {
      $cache = new \stdClass();
      $is_new = TRUE;
    }
    elseif ($cache->locked) {
      // The page is locked by a user, but that should not block the update.
      // Log a message and continue on.
      $message = "The page '@page' was in use and locked. However, the update overrode the lock. Code wins.";
      Message::make($message, array('@page' => $page_machine_name), WATCHDOG_NOTICE, 2);
    }
    $vars = array(
      '@machine' => $page_machine_name,
      '@page_name' => $page->name,
      '@path' => $page->path,
    );

    // Begin validation.
    // Adapted from page_manager_page_form_basic_validate().
    if (empty($page->name)) {
      $message = 'Import of @machine failed: The page has no name.';
      throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
    }

    if ($page_machine_name != $page->name) {
      $message = 'Import of @machine failed: The page has a name of @page_name which does not match the file.';
      throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
    }

    // Ensure name fits the rules:
    if (preg_match('/[^a-zA-Z0-9_]/', $page->name)) {
      $message = 'Import of @machine failed: Page name must be alphanumeric and underscores only.';
      throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
    }

    if (empty($page->path)) {
      $message = 'Import of @machine failed: A path is required.';
      throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
    }

    // Check for paths from other Page Manager pages.
    $existing_pages = page_manager_page_load_all();
    foreach ($existing_pages as $test) {
      if (($test->name != $page_machine_name) && ($test->path == $page->path) && empty($test->disabled)) {
        $message = "Import of @machine failed: The path of '@path' is already in use by @id.";
        $vars['@id'] = $test->admin_title;
        throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
      }
    }

    // Check if something that isn't a page manager page is using the path.
    $result = db_query('SELECT * FROM {menu_router} WHERE path = :path', array(':path' => $path));
    foreach ($result as $router) {
      if ($router->page_callback != 'page_manager_page_execute') {
        $message = "Import of @machine failed: The path of '@path' is already in use by the menu router.";
        throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
      }
    }

    // Ensure the path is not already an alias to something else.
    if (strpos($path, '%') === FALSE) {
      $alias = db_query('SELECT alias, source FROM {url_alias} WHERE alias = :path', array(':path' => $path))->fetchObject();
      if ($alias) {
        $message = "Import of @machine failed: The path of '@path' is already in use as an alias for @alias.";
        $vars['@alias'] = $alias->source;
        throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
      }
    }

    // Adapted from page_manager_page_import_subtask_submit().
    page_manager_page_new_page_cache($page, $cache);
    page_manager_set_page_cache($cache);

    // Validation passed so save it, and alter $page by reference.
    page_manager_page_save($page);

    $return = array(
      'page' => $page,
      'operation' => (!empty($is_new)) ? t('Created') : t('Updated'),
      'edit_link' => page_manager_edit_url($task_name),
    );

    return $return;
  }

  /**
   * Checks if Page Manager is enabled and import functions are available.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public static function canImport() {
    Check::canUse('ctools');

    Check::canUse('page_manager');
    ctools_include('page', 'page_manager', 'plugins/tasks');
    Check::canCall('page_manager_make_task_name');
    Check::canCall('page_manager_get_page_cache');
    Check::canCall('page_manager_page_load_all');
    Check::canCall('page_manager_page_new_page_cache');
    Check::canCall('page_manager_set_page_cache');
    Check::canCall('page_manager_page_save');
    Check::canCall('page_manager_edit_url');
    Check::canCall('page_manager_page_load');

    return TRUE;
  }

  /**
   * Checks to see if Panels pages can be exported.
   *
   * @return bool
   *   TRUE if can be exported.
   */
  public static function canExport() {
    Check::canUse('ctools');
    Check::canCall('ctools_export_load_object');

    Check::canUse('page_manager');
    ctools_include('page', 'page_manager', 'plugins/tasks');
    Check::canCall('page_manager_page_load');
    Check::canCall('page_manager_page_export');

    return TRUE;
  }

  /**
   * Exports a single PageManager page (typically called from Drush).
   *
   * @param string $page_machine_name
   *   The machine name of the Page Manager page to export.
   *
   * @return string
   *   The URI of the item exported, or a failure message.
   */
  public static function export($page_machine_name) {
    $t = get_t();
    try {
      Check::notEmpty('panel_machine_name', $page_machine_name, TRUE);
      $msg_return = '';
      $path = HudtInternal::getStoragePath('page_manager');
      $machine_name = HudtInternal::normalizeMachineName($page_machine_name);
      $file_name = HudtInternal::normalizeFileName($page_machine_name);
      $file_uri = DRUPAL_ROOT . '/' . $path . $file_name;
      self::canExport();
      // Load the page_manager page if it exists.
      $page = page_manager_page_load($machine_name);
      $handlers = page_manager_load_task_handlers(page_manager_get_task('page'), $machine_name);

      if (!empty($page)) {
        // It exists, so export it.
        $export_contents = page_manager_page_export($page, $handlers);

        // Save the file.
        $msg_return = HudtInternal::writeFile($file_uri, $export_contents);
      }
      else {
        // Could not be loaded, so nothing to export.  Error gracefully.
        $vars = array(
          '@machine_name' => $machine_name,
        );
        $msg_error = $t("The PageManager page '@machine_name' could not be loaded.  Please check the spelling of the machine name you are trying to export", $vars);

        $msg_return = $t('ERROR') . ': ';
        $msg_return .= $t("PageManager page not found.  Check the spelling of the machine name you are trying to export");
      }
    }
    catch (\Exception $e) {
      // Any errors from this command do not need to be watchdog logged.
      $e->logIt = FALSE;
      $vars = array(
        '!error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
      );
      $msg_error = $t("Caught exception:  !error", $vars);
    }
    if (!empty($msg_error)) {
      drush_log($msg_error, 'error');
    }
    return (!empty($msg_return)) ? $msg_return : $msg_error;
  }
}
