<?php

namespace HookUpdateDeployTools;

/**
 * Public methods for altering Settings.
 */
class Settings {
  /**
   * Sets a Drupal variable but adds testing loop and feedback.
   *
   * @param string $name
   *   The name of the variable to set.
   * @param mixed $value
   *   The value to set. This can be any PHP data type; variabel_set takes care
   *   of serialization as necessary.
   *
   * @return string
   *   Message to report through hook_update_N.
   *
   * @throws \DrupalUpdateException
   *   If variable does not remain set, calls the update a failure, preventing
   *   it from registering the update_N.
   */
  public static function set($name, $value) {
    $original_value = variable_get($name, 'not set');
    if (($value === $original_value) || (is_object($value) && is_object($original_value) && $value == $original_value)) {
      // There is no requested change.  Skip making a change.
      $msg_vars = array(
        '!name' => $name,
        '!value' => $value,
      );
      $message = "The variable '!name' is already set to '!value'. Skipped variable_set.";
      $result = Message::make($message, $msg_vars, WATCHDOG_INFO, 1);
    }
    else {
      // Go ahead and make the set.
      variable_set($name, $value);
      $result = self::confirmSet($name, $value, $original_value);
    }
    return $result;
  }

  /**
   * Checks to see if the variable was not set.
   *
   * @param string $name
   *   The name of the variable that was set.
   * @param mixed $value
   *   The value that should have been set
   * @param mixed $original_value
   *   The original value of the variable.
   *
   * @return string
   *   String message if it was not set correctly. (Exception thrown by
   *   Message:make if error.)
   */
  private static function confirmSet($name, $value, $original_value) {
    $variables = self::reloadVars();
    $saved_value = (array_key_exists($name, $variables)) ? $variables[$name] : 'not set';
    $type = gettype($saved_value);
    $type_original = gettype($original_value);
    $msg_vars = array(
      '!name' => $name,
      '!value' => print_r($value, TRUE),
      '!saved_value' => print_r($saved_value, TRUE),
      '!original_value' => print_r($original_value, TRUE),
      '!type' => $type,
      '!typeorig' => $type_original,
    );
    // Switct race: First one to evaluate TRUE wins.
    switch (TRUE) {
      case ($saved_value === 'not set'):
        // There was no setting saved.  Fail update.
        $message = "The variable '!name' was not saved at all. It does not exist.";
        $return = Message::make($message, $msg_vars, WATCHDOG_ERROR, 1);
        break;

      case ($saved_value == $value):
        // The save worked correctly.
        if ($saved_value === 'not set' || $original_value === 'not set') {
          // The variable was NOT originally set.
          $message = "The variable '!name' was initiated and set to !type:'!value'.";
        }
        else {
          // The variable was originally set.
          $message = "The variable '!name' was changed from !typeorig:'!original_value' to !type:'!value'.";
        }

        $return = Message::make($message, $msg_vars, WATCHDOG_INFO, 1);
        break;

      default:
        // The value did not match what was saved. Fail update.
        $message = "The variable '!name' did not correctly set to '!value'.  Value of !type:'!saved_value' found instead.  Most likely caused by a \$conf override in settings.php.";
        $return = Message::make($message, $msg_vars, WATCHDOG_ERROR, 1);
        break;
    }

    return $return;
  }

  /**
   * Loads the variables from the db merged with any set in settings.php.
   *
   * @return array
   *   Returns the array drupal variables.
   *
   * @caution
   *   - This method will not load $conf specified in any settings files that
   *   are included in settings.php using include_once or require_once since
   *   they were already included on bootstrap.
   *   - If any function are defined in settings.php or any files included
   *   therein, a fatal error (un-catchable) will occur. settings files should
   *   not contain functions.
   */
  private static function reloadVars() {
    // Get the variables from the Database. Not Global.
    $conf = variable_initialize();
    $settings_file = DRUPAL_ROOT . '/' . conf_path() . '/settings.php';
    if (file_exists($settings_file)) {
      // This was already include_once'd in drupal_settings_initialize() so it
      // needs to be included in order to load it again.
      // @RISK if somebody declares a function in settings.php or any files
      // included to it, the include bellow will trigger a fatal error.
      include $settings_file;
    }
    else {
      // settings.php was not available for some reason.  Not  critical.
      // Worst case, we get a false positive because $conf overrides are not
      // present from settings.php.
      $message = "The settings file '!file' could not be loaded.  You may get a false positive that your variable set was not overridden.";
      Message::make($message, array('!file' => $settings_file), WATCHDOG_INFO, 1);
    }

    return $conf;
  }
}
