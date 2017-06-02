<?php

namespace HookUpdateDeployTools;

/**
 * Public methods for working with Views.
 */
class Views {

  /**
   * Enable requested Views.
   *
   * @param mixed $views_names
   *   string - a single machine name of a View to enable.
   *   array - an array of View machine names to enable.
   *
   * @return string
   *   Message returned to display.
   */
  public static function enable($views_names = array()) {
    $message = self::viewSwitch($views_names, 'enable');

    return $message;
  }

  /**
   * Disable requested Views.
   *
   * @param mixed $views_names
   *   string - a single machine name of a View to disable.
   *   array - an array of View machine names to disable.
   *
   * @return string
   *   Message returned to display.
   */
  public static function disable($views_names = array()) {
    $message = self::viewSwitch($views_names, 'disable');

    return $message;
  }

  /**
   * Delete requested Views.
   *
   * @param mixed $views_names
   *   string - a single machine name of a View to delete.
   *   array - an array of View machine names to delete.
   *
   * @return string
   *   Message returned to display.
   * @throws HudtException
   */
  public static function delete($views_names = array()) {
    $message = '';
    $views_names = (array) $views_names;

    if (count($views_names)) {
      foreach ($views_names as $view_name) {
        if ($view = views_get_view($view_name)) {
          views_delete_view($view);

          // Check if view was deleted.
          if (views_get_view($view_name, TRUE)) {
            // View still exists.
            throw new HudtException('View @viewname still exists after being deleted.  Possibly in code?', array('@viewname' => $view_name), WATCHDOG_ERROR, TRUE);
          }
          else {
            // View successfully deleted.
            $message .= Message::make('View @viewname successfully deleted', array('@viewname' => $view_name), WATCHDOG_INFO);
          }
        }
        else {
          // Output already deleted or not found message.
          $message .= Message::make('View @viewname already deleted or could not be found.', array('@viewname' => $view_name), WATCHDOG_INFO);
        }
      }
    }

    return $message;
  }

  /**
   * Enable or disable requested Views.
   *
   * @param mixed $views_names
   *   string - a single machine name of a View to enable/disable.
   *   array - an array of View machine names to enable/disable.
   * @param string $operation
   *   'enable' to enable the views.
   *   'disable' to disable the views.
   *
   * @return string
   *   Message returned to display.
   */
  private static function viewSwitch($views_names, $operation) {
    $t = get_t();
    $views_names = (array) $views_names;
    $op_friendly = ($operation === 'disable') ? $t('Disabled') : $t('Enabled');
    $enable = ($operation === 'disable') ? FALSE : TRUE;

    // Prepare the summary.
    $summary_description = $t('Views @op', array('@op' => $op_friendly));
    $completed = array();
    $total_requested = count($views_names);

    try {
      self::canSwitch();

      // Enable the Views.
      foreach ($views_names as $view_name) {
        $variables = array(
          '@view' => $view_name,
          '@op' => $op_friendly,
        );
        $view = views_get_view($view_name, TRUE);

        // Check to see if it exists.
        if (empty($view)) {
          // This View does not exist.
          $message = 'The View:@view does not exist so it could not be @op.';
          throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
        }

        // Does the status already match?
        if ((property_exists($view, 'disabled')) && ($view->disabled == $enable)) {
          // The View does not match the requested state.
          // Run the operation on the View.
          ctools_export_crud_set_status('views_view', $view, !$enable);

          // Verify that it worked.
          $view = views_get_view($view_name, TRUE);
          if ((property_exists($view, 'disabled')) && ($view->disabled == $enable)) {
            // The status does not match.  Message and fail the update.
            $message = 'The View:@view status does not match the requested state of "@op".';
            $completed[$view_name] = $t($message, $variables);
            throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
          }
          else {
            // The requested operation matches the current state so, success.
            $completed[$view_name] = $op_friendly;
          }
        }
        else {
          // Status matches requested operation, so skip.
          $completed[$view_name] = $t("Skipped. Already @op.", array('@op' => $op_friendly));
        }
      }
    }
    catch (\Exception $e) {
      $vars = array(
        '!error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
      );
      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'View enable/disable denied because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      // Output a summary before shutting this down.
      $done = HudtInternal::getSummary($completed, $total_requested, $summary_description);
      Message::make($done, array(), FALSE, 1);

      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }

    $done = HudtInternal::getSummary($completed, $total_requested, $summary_description);

    $done = Message::make($done, array(), WATCHDOG_INFO, 1);
    return $done;
  }

  /**
   * Check availability of modules and methods needed to enable/disable a View.
   *
   * Any items called in here throw exceptions if they fail
   */
  private static function canSwitch() {
    Check::canUse('views');
    Check::canCall('views_get_view');
    Check::canUse('ctools');
    ctools_include('export');
    Check::canCall('ctools_export_crud_set_status');
  }
}
