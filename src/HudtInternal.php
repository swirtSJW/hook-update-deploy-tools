<?php

/**
 * @file
 * File to declare HudInternal class.
 */

namespace HookUpdateDeployTools;

/**
 * Methods for processes internal to Hook Deploy Update Tools.
 */
class HudtInternal {

  /**
   * Checks to see if a storagefile can be read.
   *
   * @param string $filename
   *   The filename of the file.
   *
   * @param string $storage_type
   *   The type of storage (menu, panel, rules...).
   *
   * @return bool
   *   TRUE if the file can be read.
   *
   * @throws HudtException
   *   When the file can not be read.
   */
  public static function canReadFile($filename, $storage_type) {
    $path = self::getStoragePath($storage_type);
    $file = "{$path}{$filename}";
    if (file_exists($file)) {
      // The file is present.
      return TRUE;
    }
    else {
      // The file is not there.
      $variables = array(
        '@path' => $path,
        '!filename' => $filename,
        '!storage' => $storage_type,
      );
      $message = "The requested !storage read failed because the file '!filename' was not found in '@path'. \nRe-run update when the file has been placed there and is readable.";
      Message::make($message, $variables, WATCHDOG_ERROR);
      throw new HudtException($message, $variables, WATCHDOG_ERROR, FALSE);
    }
  }

  /**
   * Read the contents of a file into an array of one element per line.
   *
   * @param string $filename
   *   The filename of the file.
   *
   * @param string $storage_type
   *   The type of storage (menu, panel, rule...).
   *
   * @return array
   *   One element per line.
   */
  public static function readFileToArray($filename, $storage_type) {
    $path = self::getStoragePath($storage_type);
    $file = "{$path}{$filename}";
    if (self::canReadFile($filename, $storage_type)) {
      // Get the contents as an array.
      $file_contents = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    else {
      // Should not reach here due to exception from canReadFile.
      $file_contents = array();
    }
    return $file_contents;
  }

  /**
   * Read the contents of a file into a string for the entire contents.
   *
   * @param string $filename
   *   The filename of the file.
   *
   * @param string $storage_type
   *   The type of storage (menu, panel, rule...).
   *
   * @return string
   *   The contents of the file read.
   */
  public static function readFileToString($filename, $storage_type) {
    $path = self::getStoragePath($storage_type);
    $file = "{$path}{$filename}";
    if (self::canReadFile($filename, $storage_type)) {
      // Get the contents as one string.
      $file_contents = file_get_contents($file);
    }
    else {
      // Should not reach here due to exception from canReadFile.
      $file_contents = FALSE;
    }
    return $file_contents;
  }

  /**
   * Generate the import summary.
   *
   * @param array $completed
   *   Array of completed imports.
   * @param int $total_requested
   *   The number to be processed.
   *
   * @return string
   *   The report of what was completed.
   */
  public static function getSummary($completed, $total_requested, $operation) {
    $t = get_t();
    $count = count($completed);
    $vars = array(
      '@count' => $count,
      '!completed' => print_r($completed, TRUE),
      '@total' => $total_requested,
      '@operation' => $operation,
    );

    return $t("Summary: @operation @count/@total.  Completed the following:\n !completed", $vars);
  }

  /**
   * Gets the path for where import files are stored for a given storage type.
   *
   * @param string $storage_type
   *   The type of storage (menu, panel, rule...).
   *
   * @param bool $safe_check
   *   Determines whether getting the path should be safe:
   *   - FALSE (default) :  An \Exception will be thrown if no path.
   *   - TRUE : No exception will be thrown and message will be returned.
   *
   * @return string
   *   The path to the storage module for the storage type.
   *
   * @throws HudtException
   *   If the path is not available and it is not a $safe_check
   */
  public static function getStoragePath($storage_type, $safe_check = FALSE) {
    $var_storage = self::getStorageVars();
    $t = get_t();
    if (!empty($var_storage[$storage_type])) {
      // Storage is known.  Look for specific storage module.
      $storage_module  = variable_get($var_storage[$storage_type], '');
      // Might have come up empty so look for default storage.
      $storage_module  = (!empty($storage_module)) ? $storage_module : variable_get($var_storage['default'], '');
      $storage_module  = check_plain($storage_module);
      // Might still have come up empty, so look for site_deploy.
      $storage_module = (!empty($storage_module)) ? $storage_module : 'site_deploy';
      if (module_exists($storage_module)) {
        // Get the path from the storage.
        $module_path = drupal_get_path('module', $storage_module);
        $storage_path = "{$module_path}/{$storage_type}_source/";
        return $storage_path;
      }
      elseif ($safe_check) {
        return $t('The module "@module" does not exits, please add it or adjust accordingly.', array('@module' => $storage_module));
      }
      else {
        // Storage module does not exist, throw exception, fail update.
        $variables = array(
          '!path' => '/admin/config/development/hook_update_deploy_tools',
          '!storage' => $storage_type,
          '%module' => $storage_module,
        );
        $message = "The storage module '%module'  does not exist. Visit !path to set the correct module for !storage import.";
        throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
      }
    }
    else {
      // No storage of this type, throw exception, call this a failure.
      $message = 'There is no storage of type !type to read/write. Internal Hook Update Deploy Tools error.';
      $variables = array(
        '!type' => $storage_type,
      );

      throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
    }
  }


  /**
   * Defines the array that connects import type to drupal variable.
   *
   * @return array
   *   Keyed by import type => drupal variable containing feature name.
   */
  public static function getStorageVars() {
    $storage_map = array(
      'default' => 'hook_update_deploy_tools_deploy_module',
      'menu' => 'hook_update_deploy_tools_menu_feature',
      'node' => 'hook_update_deploy_tools_node_feature',
      'page_manager' => 'hook_update_deploy_tools_page_manager_feature',
      'rules' => 'hook_update_deploy_tools_rules_feature',
    );
    return $storage_map;
  }


  /**
   * Normalizes a machine name to be underscores and removes file appendage.
   *
   * @param string $quasi_machinename
   *   An machine name with hyphens or a export file name to be normalized.
   *
   * @return string
   *   A string resembling a machine name with underscores.
   */
  public static function normalizeMachineName($quasi_machinename) {
    $items = array(
      '-export.txt' => '',
      '-' => '_',
    );
    $machine_name = str_replace(array_keys($items), array_values($items), $quasi_machinename);
    return $machine_name;
  }


  /**
   * Normalizes a machine  or file name to be the filename.
   *
   * @param string $quasi_name
   *   An machine name or a export file name to be normalized.
   *
   * @return string
   *   A string resembling a filename with hyphens and -export.txt.
   */
  public static function normalizeFileName($quasi_name) {
    $items = array(
      '-export.txt' => '',
      '_' => '-',
    );
    $file_name = str_replace(array_keys($items), array_values($items), $quasi_name);
    $file_name = "{$file_name}-export.txt";
    return $file_name;
  }


  /**
   * Writes a file to a path with contents.
   *
   * @param string $file_uri
   *   The full root path to the contents and the filename with extension.
   * @param string $file_contents
   *   The contents of the file.
   *
   * @return mixed
   *   string of the path is writing was successful.
   *   FALSE if otherwise.
   *
   * @throws \Exception
   *   If writing is unsuccessful an Exception is thrown and caught.
   */
  public static function writeFile($file_uri, $file_contents) {
    $variables = array(
      '@file_uri' => $file_uri,
    );
    $msg_return = FALSE;
    try {
      $fh = fopen($file_uri, 'w');
      if ($fh) {
        fwrite($fh, $file_contents);
        fclose($fh);
        // Successful, so return the uri of the file.
        $msg_return = $file_uri;
      }
      else {
        $message = "Error (likely permissions) creating the file: @file_uri";
        throw new HudtException($message, $variables, WATCHDOG_ERROR, FALSE);
      }
    }
    catch (\Exception $e) {
      $variables['@error'] = $e->getMessage();
      $msg = dt("Failed writing to @file_uri.  Caught exception:  @error", $variables);
      drush_log($msg, 'error');
      // Output file to terminal so it is available to use.
      drush_print(dt("The file was not generated. Outputting contents to terminal.\n"));
      drush_print('------------------------------------------------------------');
      drush_print($file_contents);
      drush_print("------------------------------------------------------------\n\n");
    }

    return $msg_return;
  }
}
