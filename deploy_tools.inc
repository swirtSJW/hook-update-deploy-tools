<?php
/**
 * @file
 * Functions for safer deployment.
 */


/**
 * Collection of methods for use in .install files.
 */
class DeployTools {
  /**
   * Check to see if the modules are actually enabled.
   *
   * @param array $modules
   *   An array of module machine names to check for being enabled.
   *
   * @return string
   *   Messsage indicating the modules are enabled
   *
   * @throws DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function checkModulesEnabled($modules = array()) {
    $modules = (array) $modules;
    $return = TRUE;
    $enabled_modules = array();
    $t = get_t();
    // Check to see if each module is enabled.
    foreach ($modules as $module) {
      if (!module_exists($module)) {
        // This module is not enabled, throw an exception.
        throw new DrupalUpdateException($t('The module @module was supposed to be enabled by this update, but was not. Please investigate the problem and re-run this update.',array('@module' => $module)));
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
   * @throws DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function enableModules($modules = array()) {
    $modules = (array) $modules;
    $enable_good = module_enable($modules);
    if (!$enable_good) {
      // Enable command failed.
      $module_list = implode(', ', $modules);
      $t = get_t();
      throw new DrupalUpdateException($t('The requested modules @modules to be enabled by this update, were not, because one of them does not exist in the codebase. Please investigate the problem and re-run this update.',array('@modules' => $module_list)));
    }
    $success = self::checkModulesEnabled($modules);

    return $success;
  }

  /**
   * Safely reverts an array of Features and provides feedback.
   *
   * The safety steps include:
   * a) Making sure the Feature exists (is enabled).
   * b) Checks to see if the Feature requires reversion (is overridden)
   *
   * @param array $features
   *   An array of features to be reverted, in order. (also accepts string)
   *
   * @return string
   *   Messsage indicating progress of feature reversions.
   *
   * @throws DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function revertFeatures($features) {
    $features = (array) $features;
    $t = get_t();
    // See if the feature needs to be reverted.
    foreach ($features as $key => $feature_name) {
      if (module_exists($feature_name)) {
        // Check the status of each feature.
        if (self::isOverridden($feature_name)) {
          // It is overridden.  Attempt revert.
          watchdog('deploy_tools', 'Reverting: @feature_name.', array('@feature_name' => $feature_name), WATCHDOG_WARNING);
          features_revert_module($feature_name);
          // Now check to see if it actually reverted.
          if (self::isOverridden($feature_name)) {
            $message = '! Feature @feature_name remains overridden after being reverted.  Check for issues.';
            global $base_url;
            $link = $base_url . '/admin/structure/features';
            watchdog('deploy_tools', $message, array('@feature_name' => $feature_name), WATCHDOG_WARNING, $link);
          }
          else {
            watchdog('deploy_tools', 'Reverted @feature_name successfully.', array('@feature_name' => $feature_name), WATCHDOG_WARNING);
          }
        }
        else {
          // Not overridden, no revert required.
          $message = 'Revert request for @feature_name was skipped because it is not currently overridden.';
          watchdog('deploy_tools', $message, array('@feature_name' => $feature_name), WATCHDOG_WARNING);
        }
      }
      else {
        // Feature does not exist.  Throw exception.
        $message = "UPDATE FAILED: The request to revert '@feature_name' failed because it is not enabled on this site. Adjust the hook_update accordingly and re-run update.";
        watchdog('deploy_tools', $message, array('@feature_name' => $feature_name), WATCHDOG_ERROR);
        throw new DrupalUpdateException($t("\nUPDATE FAILED: The request to revert '@feature_name' failed because it is not enabled on this site. Adjust your hook_update accordingly and re-run update.", array('@feature_name' => $feature_name)));
      }
    }
    return $t("The requested reverts were processed successfully.\n", array());
  }

  /**
   * Checks to see if a feature is overridden.
   *
   * @param string $feature_name
   *   The machine name of the feature to check the status of.
   * @return boolean
   *   - TRUE if overridden.
   *   - FALSE if not overidden.
   *   - NULL if not enabled / not found.
   */
  public static function isOverridden($feature_name) {
    if (module_exists($feature_name)) {
      // Get file not included during update.
      module_load_include('inc', 'features', 'features.export');
      //Refresh the Feature list so not cached.
      // Rebuild the list of features includes.
      features_include(TRUE);
      // Need to include any new files.
      features_include_defaults(NULL, TRUE);
      // Check the status of the feature.
      $status = self::uncachedFeaturesGetStorage($feature_name);
      if ($status === FEATURES_DEFAULT) {
        // Default.
        return FALSE;
      }
      else {
        //  Overridden.
        return TRUE;
      }
    }
    else {
      // Feature does not exist.
      return NULL;
    }
  }

  /**
   * Gets the un-static_cached version of features_get_storage().
   *
   * @param string $feature_name
   *   The machine name of the Feature to evaluate.
   *
   * @return int
   *   The number of Feature components not in default.
   */
  public static function uncachedFeaturesGetStorage($feature_name) {
    // Get component states, and array_diff against array(FEATURES_DEFAULT).
    // If the returned array has any states that don't match FEATURES_DEFAULT,
    // return the highest state.
    $states = features_get_component_states(array($feature_name), FALSE, TRUE);
    self::fixLaggingFieldGroup($states);
    $states = array_diff($states[$feature_name], array(FEATURES_DEFAULT));
    $storage = !empty($states) ? max($states) : FEATURES_DEFAULT;
    return $storage;
  }

  /**
   * FieldGroup is cached and shows as overridden immeditately after revert.
   *
   * Calling this method fixes this lagging state by ignoring it, IF it is the
   * only component that is showing as reverted.
   *
   * @param array $states
   *  The $states array by ref (as created by features_get_component_states).
   */
  public static function fixLaggingFieldGroup(&$states) {
    if (is_array($states)) {

      // Count the number of components out of default.
      foreach ($states as $featurename => $components) {
        $overridden_count = 0;
        foreach ($components as $component) {
          if ($component !== FEATURES_DEFAULT) {
            $overridden_count++;
          }
        }
        if (($overridden_count == 1) && (!empty($states[$featurename]['field_group']))) {
          // $states['field_group'] is the only one out of default, ignore it.
          $states[$featurename]['field_group'] = 0;
        }
      }
    }
  }
}
