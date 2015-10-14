<?php

/**
 * @file
 * File to declare Modules class.
 */

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
   * @throws \DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function checkDisabled($modules = array()) {
    $modules = (array) $modules;
    $enabled_modules = array();
    $t = get_t();
    // Check to see if each module is disabled.
    foreach ($modules as $module) {
      if (module_exists($module)) {
        // This module is enabled, throw an exception.
        $message = 'The module @module was supposed to be disabled by this update, but was not. Please investigate the problem and re-run this update.';
        $variables = array('@module' => $module);
        Message::make($message, $variables, WATCHDOG_ERROR);
      }
    }
    $module_list = implode(', ', $modules);

    $message = "The modules @disabled are disabled.";
    $variables = array('@disabled' => $module_list);
    return Message::make($message, $variables, WATCHDOG_INFO);;
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
   * @throws \DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function checkEnabled($modules = array()) {
    $modules = (array) $modules;
    $enabled_modules = array();
    $t = get_t();
    // Check to see if each module is enabled.
    foreach ($modules as $module) {
      if (!module_exists($module)) {
        // This module is not enabled, throw an exception.
        $message = 'The module @module was supposed to be enabled by this update, but was not. Please investigate the problem and re-run this update.';
        $variables = array('@module' => $module);
        Message::make($message, $variables, WATCHDOG_ERROR);
      }
    }
    $module_list = implode(', ', $modules);

    $message = "The modules @enabled were enabled successfully.";
    $variables = array('@enabled' => $module_list);
    return Message::make($message, $variables, WATCHDOG_INFO);;
  }


  /**
   * Enables an array of modules and checks to make sure they were enabled.
   *
   * @param array $modules
   *   An array of module machine names to check for being enabled.
   *
   * @return string
   *   Messsage indicating the modules are enabled.
   *
   * @throws \DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function enable($modules = array()) {
    $modules = (array) $modules;
    $enable_good = module_enable($modules);
    if (!$enable_good) {
      // Enable command failed.
      $module_list = implode(', ', $modules);
      $t = get_t();
      $message = 'The requested modules @modules to be enabled by this update, were not, because one of them does not exist in the codebase. Please investigate the problem and re-run this update.';
      $variables = array('@modules' => $module_list);
      Message::make($message, $variables, WATCHDOG_ERROR);
    }
    $success = self::checkEnabled($modules);

    return $success;
  }


  /**
   * Disables an array of modules and checks to make sure they were disabled.
   *
   * @param array $modules
   *   An array of module machine names to disable.
   *
   * @return string
   *   Messsage indicating the modules are disabled.
   *
   * @throws \DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function disable($modules = array()) {
    $modules = (array) $modules;
    $disable_dependents = FALSE;
    module_disable($modules, $disable_dependents);
    // Verify that the modules were disabled.
    $success = self::checkDisabled($modules);

    return $success;
  }


  /**
   * Uninstalls an array of modules there were previously disabled.
   *
   * @param array $modules
   *   An array of module machine names to uninstall that are already disabled.
   *
   * @return string
   *   Messsage indicating the modules are uninstalled.
   *
   * @throws \DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function uninstall($modules = array()) {
    $modules = (array) $modules;
    $t = get_t();
    module_disable($modules);
    foreach ($modules as $module) {
      if (module_exists($module)) {
        // The module is not disabled, so it can not be uninstalled.
        $variables = array('@module' => $module);
        Message::make($message, $variables, WATCHDOG_ERROR);
      }
    }
    // Made it this far.Safe to uninstall.
    // Uninstall only the specified modules.  Do not uninstall dependents
    // unless the are specified as well.
    $uninstall_dependents = FALSE;
    $success = drupal_uninstall_modules($modules, $uninstall_dependents);

    $module_list = implode(', ', $modules);
    if ($success) {
      $message = "The modules @uninstalled were uninstalled successfully.";
      $variables = array('@uninstalled' => $module_list);
      return Message::make($message, $variables, WATCHDOG_INFO);;
    }
    else {
      // Uninstalling the modules failed, can not be more specifc about why.
      $message = "The modules @uninstalled were NOT uninstalled successfully.  Check to see that the modules you are attempting to uninstall include any dependents in the correct order.";
      $variables = array('@uninstalled' => $module_list);
      Message::make($message, $variables, WATCHDOG_ERROR);
    }
  }

  /**
   * Disables and Uninstalls an array of modules. Will not process dependents.
   *
   * @param array $modules
   *   An array of module machine names to disable and uninstall.
   *
   * @return string
   *   Messsage indicating the modules are disabled and uninstalled.
   *
   * @throws \DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function disableAndUninstall($modules = array()) {
    $modules = (array) $modules;
    $message = self::disable($modules);
    $message .= self::uninstall($modules);

    return $message;
  }
}
