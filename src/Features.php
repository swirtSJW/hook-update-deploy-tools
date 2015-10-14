<?php

/**
 * @file
 * File to declare Features class.
 */

namespace HookUpdateDeployTools;

/**
 * Public method for reverting Features only if needed.
 */
class Features {
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
   * @throws \DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function revert($features) {
    $features = (array) $features;
    self::canUseFeatures();
    $t = get_t();
    // See if the feature needs to be reverted.
    foreach ($features as $key => $feature_name) {
      if (module_exists($feature_name)) {
        // Check the status of each feature.
        if (self::isOverridden($feature_name)) {
          // It is overridden.  Attempt revert.
          $message = "Reverting: @feature_name.";
          $variables = array('@feature_name' => $feature_name);
          Message::make($message, $variables, WATCHDOG_INFO);
          features_revert_module($feature_name);
          // Now check to see if it actually reverted.
          if (self::isOverridden($feature_name)) {
            $message = '! Feature @feature_name remains overridden after being reverted.  Check for issues.';
            global $base_url;
            $link = $base_url . '/admin/structure/features';
            $variables = array('@feature_name' => $feature_name);
            $message = Message::make($message, $variables, WATCHDOG_WARNING, 1, $link);
          }
          else {
            $message = "Reverted @feature_name successfully.";
            $variables = array('@feature_name' => $feature_name);
            $message = Message::make($message, $variables, WATCHDOG_INFO);
          }
        }
        else {
          // Not overridden, no revert required.
          $message = "Revert request for @feature_name was skipped because it is not currently overridden.";
          $variables = array('@feature_name' => $feature_name);
          $message = Message::make($message, $variables, WATCHDOG_INFO);
        }
      }
      else {
        // Feature does not exist.  Throw exception.
        $message = "The request to revert '@feature_name' failed because it is not enabled on this site. Adjust your hook_update accordingly and re-run update.";
        $variables = array('@feature_name' => $feature_name);
        Message::make($message, $variables, WATCHDOG_ERROR);
      }
    }
    $message = Message::make('The requested reverts were processed successfully.', array(), WATCHDOG_INFO);
    return $message;
  }

  /**
   * Checks to see if Features in enabled.
   *
   * @throws \DrupalUpdateException
   *   Exception thrown if Features is not enabled.
   *
   * @return bool
   *   TRUE if enabled.
   */
  private static function canUseFeatures() {
    if (!module_exists('features')) {
      $t = get_t();
      // Features is not enabled on this site, so this this class is unuseable.
      $message = "Revert request denied because Features is not enabled on this site.";
      $variables = array();
      Message::make($message, array(), WATCHDOG_ERROR);
    }
    else {
      return TRUE;
    }
  }

  /**
   * Checks to see if a feature is overridden.
   *
   * @param string $feature_name
   *   The machine name of the feature to check the status of.
   *
   * @return bool
   *   - TRUE if overridden.
   *   - FALSE if not overidden.
   *   - NULL if not enabled / not found.
   */
  public static function isOverridden($feature_name) {
    if (module_exists($feature_name)) {
      // Get file not included during update.
      module_load_include('inc', 'features', 'features.export');
      // Refresh the Feature list so not cached.
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
        // Overridden.
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
  private static function uncachedFeaturesGetStorage($feature_name) {
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
   *   The $states array by ref (as created by features_get_component_states).
   */
  private static function fixLaggingFieldGroup(&$states) {
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
