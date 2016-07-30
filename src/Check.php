<?php

namespace HookUpdateDeployTools;

/**
 * Public methods for dealing with Checking things.
 *
 * Checkers need to be written so that they return TRUE or a  message string if
 * they are TRUE, or throw an exception if they are FALSE.
 */
class Check {

  /**
   * A strict check for !empty.  Fails update if $value is empty.
   *
   * @param string $name
   *   The name of a variable being checked for empty.
   * @param mixed $value
   *   The actual value of the variable being checked for empty.
   *
   * @return bool
   *   TRUE if $value is not empty.
   *
   * @throws DrupalUpdateException
   *   Message throwing exception if empty.
   */
  public static function notEmpty($name, $value) {
    if (!empty($value)) {
      $return = TRUE;
    }
    else {
      // This is strict, so throw an exception.
      $msg = 'The required !name was empty. Could not proceed.';
      $vars = array('!name' => $name);
      Message::make($msg, $vars, WATCHDOG_ERROR, 1);
    }

    return $return;
  }
}
