<?php

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
        self::hasComponent($feature_name, $components_needed);
        $variables = array('@feature_name' => $feature_name);
        // If the Feature exists, process it.
        if (Check::canUse($feature_name) && ($feature = features_load_feature($feature_name, TRUE))) {
          $components = array();
          if ($force) {
            // Forcefully revert all components of the Feature.
            // Gather the components to revert them all.
            foreach (array_keys($feature->info['features']) as $component) {
              if (features_hook($component, 'features_revert')) {
                $components[] = $component;
              }
            }
            $message = "Revert (forced): @feature_name.";
            Message::make($message, $variables, WATCHDOG_INFO, 2);
          }
          else {
            // Only revert components that are detected to be
            // Overridden || Needs review || Rebuildable.
            $message = "Revert: @feature_name.";
            $states = features_get_component_states(array($feature->name), FALSE);
            // Build list of components that can be reverted and need to be.
            foreach ($states[$feature->name] as $component => $state) {
              $revertable_states = array(
                FEATURES_OVERRIDDEN,
                FEATURES_NEEDS_REVIEW,
                FEATURES_REBUILDABLE,
              );
              // If they are revertable, add them to the list.
              if (in_array($state, $revertable_states) && features_hook($component, 'features_revert')) {
                $components[] = $component;
              }
            }
          }

          if (!empty($components_needed) && is_array($components_needed)) {
            // From list of components that need to be reverted, keep only the
            // components that were requested.
            $components = array_intersect($components, $components_needed);
          }

          if (empty($components)) {
            // Not overridden, no revert required.
            $message = 'Skipped - not currently overridden.';
            $completed[$feature_name] = $message;
          }

          // Process the revert on each component.
          foreach ($components as $component) {
            $variables['@component'] = $component;
            if (features_feature_is_locked($feature_name, $component)) {
              // Trying to revert a locked component should raise an exception,
              // but it may have been caused by a blanket revert, so just raise
              // a warning instead of failing the update.
              $message = 'Revert @feature_name.@component: Skipped - locked.';
              Message::make($message, $variables, WATCHDOG_WARNING);
              $completed[$feature_name][$component] = 'Skipped - locked.';
            }
            else {
              // Ok to proceed by reverting this component.
              features_revert(array($feature_name => array($component)));

              // Now check to see if it actually reverted.
              if (self::isOverridden($feature_name, $component)) {
                $message = '@feature_name.@component: Remains overridden. Check for issues.';
                global $base_url;
                $link = $base_url . '/admin/structure/features';
                Message::make($message, $variables, WATCHDOG_WARNING, 1, $link);
                $message = "Overridden - Check for issues.";
              }
              else {
                // Not shown as overridden so most likely a success.
                $message = "Success";
              }
              $completed[$feature_name][$component] = format_string($message, $variables);
            }
          }
          $variables['!completed'] = $completed[$feature_name];
          // Log a message about the entire Feature.
          $message = "Reverted @feature_name : !completed";
          Message::make($message, $variables, WATCHDOG_INFO);
        }
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

      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }
    // Log and output a summary of all the requests.
    $done = HudtInternal::getSummary($completed, $total_requested, 'Reverted');
    $message = Message::make('The requested reverts were processed. !done', array('!done' => $done), WATCHDOG_INFO);
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

    if (empty($states[$module][$component])) {
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
   * Check that a specific component exists in the Feature.
   *
   * @param string $feature_name
   *   The machine name of the Feature.
   * @param array $components
   *   The name of the component.
   *
   * @return bool
   *   TRUE if the Feature has the component.
   *
   * @throws HudtException
   *   If the Feature does not have the component.
   */
  public static function hasComponent($feature_name, $components) {
    $states = features_get_component_states(array($feature_name), FALSE, TRUE);
    if (is_array($components)) {
      foreach ($components as $component_name) {
        if (!isset($states[$feature_name][$component_name])) {
          // The component does not exist in this Feature.  Throw an exception.
          $message = 'The feature @feature has no component of @component.';
          $variables = array(
            '@feature' => $feature_name,
            '@component' => $component_name,
          );
          throw new HudtException($message, $variables, WATCHDOG_ERROR);
        }
      }
    }
    // The component exists.
    return TRUE;
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
