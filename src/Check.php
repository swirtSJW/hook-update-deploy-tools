<?php
/**
 * @file
 * File for methods related to strictly checking things.
 */

namespace HookUpdateDeployTools;

/**
 * Public methods for dealing with Checking things.
 *
 * Checkers need to be written so that they return TRUE or a  message string if
 * they are TRUE, or throw an exception if they are FALSE.
 */
class Check {

  /**
   * Evaluates if a function can be used.
   *
   * @param string $function_name
   *   The name of a function.
   *
   * @return bool
   *   TRUE if the function can be called.
   *
   * @throws HudtException if the function does not exist.
   */
  public static function canCall($function_name) {
    if (!empty($function_name) && function_exists($function_name)) {
      return TRUE;
    }
    else {
      $message = "The function '@name' does not exist and can not be used.";
      $vars = array('@name' => $function_name);
      throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
    }
  }


  /**
   * Evaluates if a module can be used.
   *
   * @param string $module_name
   *   The machine name of a module.
   *
   * @return bool
   *   TRUE if the module exists.
   *
   * @throws HudtException if the module does not exist.
   */
  public static function canUse($module_name) {
    if (!empty($module_name) && module_exists($module_name)) {
      return TRUE;
    }
    else {

      $message = "The module '@name' does not exist and can not be used.";
      $vars = array('@name' => $module_name);
      throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
    }
  }

  /**
   * Evaluates if a class exists.
   *
   * @param string $class
   *   The full namespaced name of a class.
   *
   * @return bool
   *   TRUE if the class exists.
   *
   * @throws HudtException non-logging if the class does not exist.
   */
  public static function classExists($class) {
    if (!empty($class) && class_exists($class)) {
      return TRUE;
    }
    else {
      $message = "The class @class does not exist and can not be used.";
      $vars = array('@class' => $class);
      throw new HudtException($message, $vars, WATCHDOG_ERROR, FALSE);
    }
  }

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
   * @throws HudtException if it is empty.
   */
  public static function notEmpty($name, $value) {
    if (!empty($value)) {
      $return = TRUE;
    }
    else {
      // This is strict, so make message and throw DrupalUpdateException.
      $message = 'The required !name was empty. Could not proceed.';
      $vars = array('!name' => $name);
      throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
    }

    return $return;
  }

  /**
   * A strict check for entity being a group.  Fails update if !group.
   *
   * @param string $name
   *   The name of a variable being checked for Group.
   * @param object $entity
   *   The enitity object being checked.
   *
   * @return bool
   *   TRUE if $value is a Group.
   *
   * @throws HudtException if it is not a group.
   */
  public static function isGroup($name, $entity) {
    Check::canUse('og');
    Check::canCall('og_is_group');
    if (og_is_group('node', $entity)) {
      $return = TRUE;
    }
    else {
      // This is strict, so make message and throw DrupalUpdateException.
      $message = 'The entity !name was not an Organic Group. Could not proceed.';
      $vars = array('!name' => $entity);
      throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
    }

    return $return;
  }

  /**
   * A strict check for numeric.  Fails update if $value is !numeric.
   *
   * @param string $name
   *   The name of a variable being checked for empty.
   * @param mixed $value
   *   The actual value of the variable being checked for empty.
   *
   * @return bool
   *   TRUE if $value is numeric.
   *
   * @throws HudtException if it is !numeric.
   */
  public static function isNumeric($name, $value) {
    if (is_numeric($value)) {
      $return = TRUE;
    }
    else {
      // This is strict, so make message and throw DrupalUpdateException.
      $message = 'The value !name was not numeric. Could not proceed.';
      $vars = array('!name' => $name);
      throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
    }

    return $return;
  }
}
