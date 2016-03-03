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
   *   TRUE if the file can be read.  FALSE if it can not.  Throws and exception
   *   through Message::make().
   *
   * @throws DrupalUpdateException
   *   Via Message::make().
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
      // Should not get here due to Message::make() throwing an exception.
      return FALSE;
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
   * Gets the path for where import files are stored for a given storage type.
   *
   * @param string $storage_type
   *   The type of storage (menu, panel, rule...).
   *
   * @param bool $safe_check
   *   Determines whether getting the path should be safe:
   *   - FALSE (default) :  An update hook exception will be thrown if no path.
   *   - TRUE : No exception will be thrown and message will be returned.
   *
   * @return string
   *   The path to the storage module for the storage type.
   */
  public static function getStoragePath($storage_type, $safe_check = FALSE) {
    $var_storage = self::getStorageVars();
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
        $t = get_t();
        return $t('The module "@module" does not exits, please add it or adjust accordingly.', array('@module' => $storage_module));
      }
      else {
        // Storage module does not exist, throw exception, fail update.
        $msg = "The storage module '%module'  does not exist. Visit !path to set the correct module for !storage import.";
        $vars = array(
          '!path' => '/admin/config/development/hook_update_deploy_tools',
          '!storage' => $storage_type,
          '%module' => $storage_module,
        );
        Message::make($msg, $vars, WATCHDOG_ERROR, 1);
      }
    }
    else {
      // No storage of this type, throw exception, call this update a failure.
      $msg = 'There is no storage of type !type to import from. Internal Hook Update Deploy Tools error.';
      $vars = array(
        '!type' => $storage_type,
      );
      Message::make($msg, $vars, WATCHDOG_ERROR, 1);
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
      'panels' => 'hook_update_deploy_tools_panels_feature',
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
}
