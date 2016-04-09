<?php

/**
 * @file
 * Contains \HookUpdateDeployTools\Features.
 */

namespace HookUpdateDeployTools;

/**
 * Public method for reverting Features only if needed.
 */
class Features {
  /**
   * Safely revert an array of Features and provide feedback.
   *
   * The safety steps include:
   * a) Making sure the Feature exists (is enabled).
   * b) Checks to see if the Feature is overridden.
   *
   * @param string[]|string $feature_names
   *   One or more features or feature.component pairs. (in order)
   * @param bool $force
   *   Force revert even if Features assumes components' state are default.
   *
   * @return string
   *   Messsage indicating progress of feature reversions.
   *
   * @throws \DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function revert($feature_names, $force = FALSE) {
    $feature_names = (array) $feature_names;
    $completed = array();
    $message = '';
    $total_requested = count($feature_names);
    $t = get_t();

    try {
      Check::canUse('features');
      module_load_include('inc', 'features', 'features.export');
      // Check all functions that we plan to call are available.
      // Exceptions are preferable to fatal errors.
      Check::canCall('features_include');
      Check::canCall('features_load_feature');
      Check::canCall('features_hook');
      // Pick up new files that may have been added to existing Features.
      features_include(TRUE);
      $feature_names = self::parseFeatureNames($feature_names);
      // See if the feature needs to be reverted.
      foreach ($feature_names as $feature_name => $components_needed) {
        $variables = array('@feature_name' => $feature_name);
        if (Check::canUse($feature_name)&& ($feature = features_load_feature($feature_name, TRUE))) {

          $components = array();
          if ($force) {
            // Forcefully revert all components of a feature.
            foreach (array_keys($feature->info['features']) as $component) {
              if (features_hook($component, 'features_revert')) {
                $components[] = $component;

              }
            }
            $message = "Reverting FORCE: @feature_name.";
          }
          else {
            // Only revert components that are detected to be
            // Overridden/Needs review/rebuildable.
            $message = "Reverting: @feature_name.";
            $states = features_get_component_states(array($feature->name), FALSE);
            foreach ($states[$feature->name] as $component => $state) {
              $revertable_states = array(
                FEATURES_OVERRIDDEN,
                FEATURES_NEEDS_REVIEW,
                FEATURES_REBUILDABLE,
              );
              if (in_array($state, $revertable_states) && features_hook($component, 'features_revert')) {
                $components[] = $component;
              }
            }
          }

          if (!empty($components_needed) && is_array($components_needed)) {
            $components = array_intersect($components, $components_needed);
          }

          if (empty($components)) {
            // Not overridden, no revert required.
            $message = 'Revert @feature_name was skipped because it is not currently overridden.';
          }
          Message::make($message, $variables, WATCHDOG_INFO);

          foreach ($components as $component) {
            $variables['@component'] = $component;
            if (features_feature_is_locked($feature_name, $component)) {
              $message = 'Skipping locked @feature_name.@component.';
              Message::make($message, $variables, WATCHDOG_INFO);
              $completed[$feature_name] = format_string($message, $variables);
            }
            else {
              features_revert(array($feature_name => array($component)));

              // Now check to see if it actually reverted.
              if (self::isOverridden($feature_name, $component)) {
                $message = 'Feature @feature_name remains overridden after being reverted.  Check for issues.';
                global $base_url;
                $link = $base_url . '/admin/structure/features';
                $message_out = Message::make($message, $variables, WATCHDOG_WARNING, 1, $link);
              }
              else {
                $message = "Reverted @feature_name.@component successfully.";
                $message_out = Message::make($message, $variables, WATCHDOG_INFO);
              }

            }
          }
        }

        $completed[$feature_name] = format_string($message, $variables);
      }
    }
    catch(\Exception $e) {
      $vars = array(
        '!error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
      );
      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Feature revert denied because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      // Output a summary before shutting this down.
      $done = HudtInternal::getSummary($completed, $total_requested, 'Reverted');
      Message::make($done, array(), FALSE, 1);

      throw new \DrupalUpdateException($t('Caught Exception: Update aborted!  !error', $vars));
    }

    $done = HudtInternal::getSummary($completed, $total_requested, 'Reverted');
    $message = Message::make('The requested reverts were processed successfully. !done', array('!done' => $done), WATCHDOG_INFO);
    return $message;
  }


  /**
   * Check to see if a feature component is overridden.
   *
   * @param string $feature_name
   *   The machine name of the feature to check the status of.
   * @param string $component_name
   *   The name of the component being checked.
   *
   * @return bool
   *   - TRUE if overridden.
   *   - FALSE if not overidden (at default).
   */
  public static function isOverridden($feature_name, $component_name) {
    // Get file not included during update.
    module_load_include('inc', 'features', 'features.export');
    // Refresh the Feature list so not cached.
    // Rebuild the list of features includes.
    features_include(TRUE);
    // Need to include any new files.
    features_include_defaults(NULL, TRUE);
    // Check the status of the feature component.
    $states = features_get_component_states(array($feature_name), FALSE, TRUE);
    self::fixLaggingFieldGroup($states);
    if ((!empty($states[$module][$component])) && ($states[$module][$component] !== FEATURES_DEFAULT)) {
      // Default, not overidden.
      $status = FEATURES_DEFAULT;
    }
    else {
      // Overridden.
      $status = TRUE;
    }

    return $status;
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

  /**
   * Parse requested feature names and components.
   *
   * @param array $feature_names
   *   Array of feature names and/or feature names.component names
   *
   * @return array
   *   Array structure of
   *   array(
   *     featurename => TRUE,
   *     featurename2 => array(component1, component2...),
   *   )
   */
  private static function parseFeatureNames($feature_names) {
    // Parse list of feature names.
    $modules = array();
    foreach ($feature_names as $feature_name) {
      $feature_name = explode('.', $feature_name);
      $module = array_shift($feature_name);
      $component = array_shift($feature_name);

      if (isset($module)) {
        if (empty($component)) {
          // Just a feature name, we need all of it's components.
          $modules[$module] = TRUE;
        }
        elseif ($modules[$module] !== TRUE) {
          // Requested a component be reverted, build array in case of multiple.
          if (!isset($modules[$module])) {
            $modules[$module] = array();
          }
          $modules[$module][] = $component;
        }
      }
    }

    return $modules;
  }
}
