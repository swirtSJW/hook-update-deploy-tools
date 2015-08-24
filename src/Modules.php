<?php
namespace DeployTools;

/**
 * Public method for enabling modules that verifies it was actually enabled.
 */
class Modules {
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
    $return = TRUE;
    $enabled_modules = array();
    $t = get_t();
    // Check to see if each module is enabled.
    foreach ($modules as $module) {
      if (!module_exists($module)) {
        // This module is not enabled, throw an exception.
        throw new \DrupalUpdateException($t('The module @module was supposed to be enabled by this update, but was not. Please investigate the problem and re-run this update.',array('@module' => $module)));
      }
    }
    $module_list = implode(', ', $modules);

    return $t("The modules @enabled were enabled successfully.\n", array('@enabled' => $module_list));
  }

  /**
   * Enables an array of modules and checks to make sure they were truly enabled.
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
      throw new \DrupalUpdateException($t('The requested modules @modules to be enabled by this update, were not, because one of them does not exist in the codebase. Please investigate the problem and re-run this update.',array('@modules' => $module_list)));
    }
    $success = self::checkEnabled($modules);

    return $success;
  }

}