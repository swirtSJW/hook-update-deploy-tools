<?php
/**
 * @file
 * Helper class to manage watchdog and commandline messaging during update.
 */

namespace HookUpdateDeployTools;

class Message {
  /**
   * Logs a system message and outputs it to drush terminal if run from drush.
   *
   * @param string $message
   *   The message to store in the log. Keep $message translatable
   *   by not concatenating dynamic values into it! Variables in the
   *   message should be added by using placeholder strings alongside
   *   the variables argument to declare the value of the placeholders.
   *   See t() for documentation on how $message and $variables interact.
   * @param array $variables
   *   Array of variables to replace in the message on display or
   *   NULL if message is already translated or not possible to
   *   translate.
   * @param int $severity
   *   The severity of the message; one of the following values as defined by
   *   watchdog.  WATCHDOG_ERROR and up throws a notice and fails the update.
   *   - WATCHDOG_EMERGENCY: Emergency, system is unusable.
   *   - WATCHDOG_ALERT: Alert, action must be taken immediately.
   *   - WATCHDOG_CRITICAL: Critical conditions.
   *   - WATCHDOG_ERROR: Error conditions.
   *   - WATCHDOG_WARNING: Warning conditions.
   *   - WATCHDOG_NOTICE: (default) Normal but significant conditions.
   *   - WATCHDOG_INFO: Informational messages.
   *   - WATCHDOG_DEBUG: Debug-level messages.
   *   - FALSE: Outputs the message to drush without calling Watchdog.
   * @param int $indent
   *   (optional). Sets indentation for drush output. Defaults to 1.
   * @param string $link
   *   (optional) A url to serve as the link in Watchdog.
   *
   * @return string
   *   - Returns an empty string to support legacy messaging style when run by
   *     drush.
   *   - Returns the full message if run by update.php so Drupal can handle the
   *     message.
   *
   * @throws DrupalUpdateException
   *   Exception thrown fails update if watchdog level is WATCHDOG_ERROR,
   *   WATCHDOG_CRITICAL, WATCHDOG_ALERT, or WATCHDOG_EMERGENCY
   */
  public static function make($message, $variables = array(), $severity = WATCHDOG_NOTICE, $indent = 1, $link = NULL) {
    $variables = self::stringifyValues($variables);
    // Determine what instantiated this message's parent.
    $trace = debug_backtrace();
    $called_by = '';
    if (isset($trace[2])) {
      // $trace[2] is usually the hook update that instantiated this message.
      if (!empty($trace[2]['class'])) {
        $called_by = $trace[2]['class'];
      }
      elseif (!empty($trace[2]['function'])  && (stripos($trace[2]['function'], 'update_do_one') === FALSE)) {
        $called_by = $trace[2]['function'];
      }
      elseif (!empty($trace[1]['function']) && (stripos($trace[1]['function'], 'update_do_one') === FALSE)) {
        $called_by = $trace[1]['function'];
      }
    }
    // Try to determine the module caller to pass to Watchdog.
    $wd_type = (empty($called_by)) ? 'hook_update_deploy_tools' : current(explode('_update_', $called_by));
    // Clean the watchdog of any types that are known nonsense from backtrace.
    $wd_type_giberish = array(
      // Bad type => Good type.
      'call_user_func_array' => 'hook_update_deploy_tools',
      'site_deploy_install' => 'site_deploy',
    );
    $wd_type = (!empty($wd_type_giberish[$wd_type])) ? $wd_type_giberish[$wd_type] : $wd_type;
    // t() might not be available at .install.
    $t = get_t();
    $fail_header = (($severity <= WATCHDOG_ERROR) && $severity !== FALSE) ? $t('UPDATE FAILED:') . ' ' : '';
    $formatted_message = format_string($message, $variables);

    if ($severity !== FALSE) {
      watchdog($wd_type, $fail_header . $message, $variables, $severity, $link);
    }
    // Check to see if this is run by drush and no WD error was
    // already sent.  WATCHDOG_ERROR and up get sent to terminal by WD.
    if (drupal_is_cli() && (($severity > WATCHDOG_WARNING) || $severity === FALSE)) {
      // Being run through drush, so output feedback to drush, and not already
      // output to terminal so output it.
      drush_print("{$fail_header}{$wd_type}: {$formatted_message}", $indent);
    }
    else {
      // Being run by update.php so translate and return.
      $return_message = $t($message, $variables) . " \n";
    }
    // Error or more serious? Fail the hook_update_N.
    if (($severity <= WATCHDOG_ERROR) && $severity !== FALSE) {
      throw new \DrupalUpdateException("{$fail_header}{$wd_type}: {$formatted_message}");
    }
    return (!empty($return_message)) ? "{$fail_header}{$wd_type}: {$return_message}" : '';
  }


  /**
   * Dumps a var to drush terminal if run by drush.
   *
   * @param mixed $var
   *   Any variable value to output.
   * @param string $var_id
   *   An optional string to identify what is being output.
   */
  public static function varDumpToDrush($var, $var_id = 'VAR DUMPs') {
    // Check to see if this is run by drush and output is desired.
    if (drupal_is_cli()) {
      drush_print("{$var_id}: \n", 0);
      if (!empty($var)) {
        drush_print_r($var);
      }
      else {
        drush_print("This variable was EMPTY. \n", 1);
      }
    }
  }

  /**
   * Loops through an array and pretty prints any values that are arrays or obj.
   *
   * @param array $variables
   *   A variables array ready for watchdog, but possibly containing arrays or
   *   objects as first level array values..
   *
   * @return array
   *   A single level array where all values are pretty printed.
   */
  public static function stringifyValues($variables) {
    if (is_array($variables) || is_object($variables)) {
      foreach ($variables as $key => $value) {
        $variables[$key] = (is_array($value) || is_object($value)) ? '<pre>' . print_r($value, TRUE) . '</pre>' : $value;
      }
    }
    return $variables;
  }
}
