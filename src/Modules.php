<?php

namespace HookUpdateDeployTools;

/**
 * Public method for enabling modules that verifies it was actually enabled.
 */
class Modules {
  /**
   * Check to see if modules are actually disabled.
   *
   * @param array $modules
   *   An array of module machine names to check for being disabled.
   *
   * @return string
   *   Messsage indicating the modules are disabled
   *
   * @throws \HudtException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function checkDisabled($modules = array()) {
    $modules = (array) $modules;
    $enabled_modules = array();
    $t = get_t();
    $enabled = $t('enabled');
    $not_enabled = $t('disabled');

    $report = array();
    // Check to see if each module is disabled.
    foreach ($modules as $module) {
      if (module_exists($module)) {
        // This module is enabled.
        $report[$module] = $enabled;
      }
      else {
        $report[$module] = $not_enabled;
      }
    }

    if (in_array($enabled, $report)) {
      $message = 'The modules that were supposed to be disabled by this update, were not. Please investigate the problem and re-run this update. Report: !report';
      $variables = array('!report' => $report);
      throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
    }
    else {
      $message = "The requested modules are disabled. Report: !report";
      $variables = array('!report' => $report);
      return Message::make($message, $variables, WATCHDOG_INFO);
    }
  }

  /**
   * Check to see if the modules are actually enabled.
   *
   * @param array $modules
   *   An array of module machine names to check for being enabled.
   *
   * @return string
   *   Messsage indicating the modules are enabled
   *
   * @throws \HudtException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function checkEnabled($modules = array()) {
    $modules = (array) $modules;
    $t = get_t();
    $enabled = $t('enabled');
    $not_enabled = $t('not-enabled');
    $report = array();
    // Check to see if each module is enabled.
    foreach ($modules as $module) {
      if (!module_exists($module)) {
        // This module is not enabled.
        $report[$module] = $not_enabled;
      }
      else {
        $report[$module] = $enabled;
      }
    }

    if (in_array($not_enabled, $report)) {
      // Something was not enabled. Fail the update.
      $message = 'Some of the modules that were supposed to be enabled, are not showing as enabled. Please investigate the problem and re-run this update. Report: !report';
      $variables = array('!report' => $report);
      throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
    }

    if (in_array($not_enabled, $report)) {
      // Something was not enabled. Fail the update.
      $message = 'Some of the modules that were supposed to be enabled, are not showing as enabled. Please investigate the problem and re-run this update. Report: !report';
      $variables = array('!report' => $report);
      throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
    }
    $module_list = implode(', ', $modules);

    $message = "The requested modules were enabled successfully. Report: !report";
    $variables = array('!report' => $report);
    return Message::make($message, $variables, WATCHDOG_INFO);;
  }


  /**
   * Checks for requested modules are present and optionally dependencies.
   *
   * @param array $modules
   *   The module machine names to check for being present.
   * @param bool $check_dependencies
   *   TRUE [default] checks to see that module dependencies are present too.
   *   FALSE does not check for dependencies
   * @param array $module_data
   *   Only used internally! The Drupal system data about existing modules.
   * @param bool $failed_flag
   *   Only used internally!  A persistant flag (by reference) to indicate a
   *   required module is missing.
   *
   * @return array
   *   A array of the report of any missing modules.
   *
   * @throws \HudtException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function checkPresent($modules = array(), $check_dependencies = TRUE, $module_data = NULL, &$failed_flag = FALSE) {
    $modules = (array) $modules;
    $report = array();
    // If $module_data is NULL, then this is the first_run.
    $first_run = (is_null($module_data)) ? TRUE : FALSE;
    // Get all module data so we can see what we have, if we don't have it yet.
    $module_data = (empty($module_data)) ? \system_rebuild_module_data() : $module_data;
    // Create an associative array with weights as values.
    foreach ($modules as $module) {
      // Check for basic presence.
      if (!isset($module_data[$module])) {
        // This module is not found in the filesystem. Report it.
        $report[$module] = t('missing');
        $failed_flag = TRUE;
      }
      elseif ($check_dependencies === TRUE) {
        // Check for dependencies, recursively.
        if (!empty($module_data[$module]->requires)) {
          $dependencies = array_keys($module_data[$module]->requires);
          $missing_dependencies = self::checkPresent($dependencies, $check_dependencies, $module_data, $failed_flag);
          // Only report it if there are missing dependencies.
          if (!empty($missing_dependencies)) {
            $report[$module]['dependency'] = $missing_dependencies;
          }
        }
      }
    }

    if ($first_run && $failed_flag) {
      $message = 'The check for presence of modules failed. Please investigate the problem and re-run this update.  Report: !report';
      $variables = array(
        '!report' => $report,
      );
      throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
    }
    else {
      return $report;
    }
  }


  /**
   * Enables an array of modules and checks to make sure they were enabled.
   *
   * @param array $modules
   *   An array of module machine names to check for being enabled.
   * @param bool $enable_dependencies
   *   Switch for causing dependent modules to be enabled. (default: TRUE)
   *
   * @return string
   *   Messsage indicating the modules are enabled.
   *
   * @throws \HudtException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function enable($modules = array(), $enable_dependencies = TRUE) {
    try {
      $modules = (array) $modules;
      $t = get_t();
      $enabled = $t('enabled');
      $failed = $t('failed');
      self::checkPresent($modules, $enable_dependencies);
      $report = array();
      foreach ($modules as $module) {
        $enable_good = module_enable(array($module), $enable_dependencies);
        if ($enable_good) {
          $report[$module] = $enabled;
        }
        else {
          $report[$module] = $failed;
        }
      }
      $variables = array('!report' => $report);

      if (in_array($failed, $report)) {
        // Enable command failed.
        $message = 'The modules to be enabled by this update, were not. Please investigate the problem and re-run this update. Report: !report';
        throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
      }
      $success = self::checkEnabled($modules);
      return $success;
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Modules::enable failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }
      throw new HudtException('Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }
  }


  /**
   * Disables an array of modules and checks to make sure they were disabled.
   *
   * @param array $modules
   *   An array of module machine names to disable.
   * @param bool $disable_dependents
   *   Switch to disable dependent modules. (default: TRUE)
   *
   * @return string
   *   Messsage indicating the modules are disabled.
   */
  public static function disable($modules = array(), $disable_dependents = TRUE) {
    try {
      $modules = (array) $modules;
      $disable_dependents = !empty($disable_dependents) ? TRUE : FALSE;
      module_disable($modules, $disable_dependents);
      // Verify that the modules were disabled.
      $success = self::checkDisabled($modules);
      drupal_flush_all_caches();
      $success .= Message::make("Caches cleared and Registry Rebuilt.", array(), WATCHDOG_INFO);

      return $success;
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Modules::disable failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      throw new HudtException('Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }
  }


  /**
   * Uninstalls an array of modules there were previously disabled.
   *
   * @param array $modules
   *   An array of module machine names to uninstall that are already disabled.
   * @param bool $uninstall_dependents
   *   Switch to uninstall dependent modules. (default: TRUE)
   *
   * @return string
   *   Messsage indicating the modules are uninstalled.
   *
   * @throws \HudtException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function uninstall($modules = array(), $uninstall_dependents = TRUE) {
    try {
      $modules = (array) $modules;
      $uninstall_dependents = !empty($uninstall_dependents) ? TRUE : FALSE;
      $t = get_t();
      $uninstalled = $t('uninstalled');
      $not_uninstalled = $t('not-uninstalled');
      $enabled = $t('enabled');
      $report = array();

      // Scan to see if any of the modules are still enabled.
      foreach ($modules as $module) {
        if (module_exists($module)) {
          // The module is not disabled, so it can not be uninstalled.
          $report[$module] = $enabled;
        }

      }
      if (empty($report)) {
        // Made it this  far so it is safe to uninstall requested modules.
        drupal_uninstall_modules($modules, $uninstall_dependents);

        include_once DRUPAL_ROOT . '/includes/install.inc';
        $module_stati = drupal_get_installed_schema_version('', TRUE, TRUE);

        // Verify they were uninstalled.
        foreach ($modules as $module) {
          if (!isset($module_stati[$module])) {
            // The module was not found, which is acceptable as it is
            // without question, uninstalled.
            $report[$module] = t('not found');
          }
          elseif ($module_stati[$module] === '-1') {
            // It is not installed.
            $report[$module] = $uninstalled;
          }
          else {
            // The module is installed.
            $report[$module] = $not_uninstalled;
          }
        }
      }

      $variables = array('!report' => $report);
      if (in_array($enabled, $report) || in_array($not_uninstalled, $report)) {
        // Uninstalling the modules failed, can not be more specifc about why.
        $message = "The modules requested to uninstall were NOT uninstalled successfully.  Report: !report";
        throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
      }
      else {
        $message = "The requested modules were uninstalled successfully. Report: !report";
        return Message::make($message, $variables, WATCHDOG_INFO);
      }
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Modules::uninstall failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      throw new HudtException('Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }
  }

  /**
   * Disables and Uninstalls an array of modules. Will not process dependents.
   *
   * @param array $modules
   *   An array of module machine names to disable and uninstall.
   * @param bool $include_dependents
   *   Switch to disable and uninstall dependent modules. (default: TRUE)
   *
   * @return string
   *   Messsage indicating the modules are disabled and uninstalled.
   */
  public static function disableAndUninstall($modules = array(), $include_dependents = TRUE) {
    $modules = (array) $modules;
    $message = self::disable($modules, $include_dependents);
    $message .= self::uninstall($modules, $include_dependents);

    return $message;
  }
}
