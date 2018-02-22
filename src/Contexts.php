<?php

namespace HookUpdateDeployTools;

/**
 * Public method for managing Contexts that verifies the changes.
 */
class Contexts {
  /**
   * Check to see if contexts are actually disabled.
   *
   * @param array $contexts
   *   An array of context machine names to check for being disabled.
   *
   * @return string
   *   Messsage indicating the contexts are disabled
   *
   * @throws \HudtException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function checkDisabled($contexts = array()) {
    $contexts = (array) $contexts;
    $enabled_contexts = array();
    $t = get_t();
    $enabled = $t('enabled');
    $not_enabled = $t('disabled');
    $not_present = $t('not present');
    $report = array();
    self::canSwitch();

    $existing_contexts = context_load(NULL, TRUE);

    // Check to see if each context is disabled.
    foreach ($contexts as $context) {
      if (!empty($existing_contexts[$context])) {
        // The context is present.
        if (!empty($existing_contexts[$context]->disabled)) {
          // The context is disabled.
          $report[$context] = $not_enabled;
        }
        else {
          // This context is enabled.
          $report[$context] = $enabled;
        }
      }
      else {
        // The context does not exist, which is close enough to disabled.
        $report[$context] = "{$not_enabled} - {$not_present}";
      }
    }

    if (in_array($enabled, $report)) {
      $message = 'The contexts that were supposed to be disabled by this update, were not. Please investigate the problem and re-run this update. Report: !report';
      $variables = array('!report' => $report);
      throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
    }
    else {
      $message = "The requested contexts are disabled. Report: !report";
      $variables = array('!report' => $report);
      return Message::make($message, $variables, WATCHDOG_INFO);
    }
  }

  /**
   * Check to see if the contexts are actually enabled.
   *
   * @param array $contexts
   *   An array of context machine names to check for being enabled.
   *
   * @return string
   *   Messsage indicating the contexts are enabled
   *
   * @throws \HudtException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function checkEnabled($contexts = array()) {
    $contexts = (array) $contexts;
    $t = get_t();
    $enabled = $t('enabled');
    $not_enabled = $t('not-enabled');
    $report = array();
    self::canSwitch();

    // Get a list of enabled contexts.
    $enabled_contexts = context_enabled_contexts(TRUE);

    // Check to see if each context is enabled.
    foreach ($contexts as $context) {
      if (!empty($enabled_contexts[$context])) {
        // This context is enabled.
        $report[$context] = $enabled;
      }
      else {
        $report[$context] = $not_enabled;
      }
    }

    if (in_array($not_enabled, $report)) {
      // Something was not enabled. Fail the update.
      $message .= 'Some of the contexts that were supposed to be enabled, are not showing as enabled. Please investigate the problem and re-run this update. Report: !report';
      $variables = array('!report' => $report);
      throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
    }
    else {
      // It was a success.
      $message .= "The requested contexts were enabled successfully. Report: !report";
      $variables = array('!report' => $report);
      return Message::make($message, $variables, WATCHDOG_INFO);
    }
  }

  /**
   * Enables an array of contexts and checks to make sure they were enabled.
   *
   * @param mixed $contexts
   *   array: of context machine names to enable.
   *   string: single context machine name to enable.
   *
   * @return string
   *   Messsage indicating the contexts are enabled.
   *
   * @throws \HudtException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function enable($contexts = array()) {
    try {
      $contexts = (array) $contexts;
      $t = get_t();
      $enabled = $t('enabled');
      $failed = $t('failed');
      $report = array();
      self::canSwitch();
      self::checkContextsExist($contexts, TRUE);

      foreach ($contexts as $context) {
        $context_object = context_load($context);
        ctools_export_crud_enable('context', $context_object);
      }

      $success = self::checkEnabled($contexts);
      return $success;
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'contexts::enable failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }
      throw new HudtException('Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }
  }


  /**
   * Disables an array of contexts and checks to make sure they were disabled.
   *
   * @param mixed $contexts
   *   array: of context machine names to disable.
   *   string: single context machine name to disable
   *
   * @return string
   *   Messsage indicating the contexts are disabled.
   */
  public static function disable($contexts = array(), $disable_dependents = TRUE) {
    try {
      $contexts = (array) $contexts;
      self::canSwitch();
      // If a context is missing, technically it is already disabled, so there
      // is no need for this to be a strict check.
      self::checkContextsExist($contexts, FALSE);
      foreach ($contexts as $context) {
        $context_object = context_load($context);
        ctools_export_crud_disable('context', $context_object);
      }
      // Verify that the contexts were disabled.
      $success = self::checkDisabled($contexts);

      return $success;
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'contexts::disable failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      throw new HudtException('Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }
  }


  /**
   * Check availability of modules & methods needed to enable/disable a context.
   *
   * Any items called in here throw exceptions if they fail
   */
  private static function canSwitch() {
    Check::canUse('context');
    Check::canCall('context_load');
    Check::canCall('context_enabled_contexts');
    Check::canUse('ctools');
    ctools_include('export');
    Check::canCall('ctools_export_crud_disable');
    Check::canCall('ctools_export_crud_enable');
  }

  /**
   * Check that the contexts exist.
   *
   * @param array $contexts
   *   An array of context machine names to check.
   * @param bool $strict
   *   Flag for whether this should run strict and throw an exception.
   *
   * @return bool
   *   TRUE if the contexts exist.
   *   FALSE if not strict.
   *
   * @throws HudtException
   *   If a context does not exist and $strict = TRUE.
   */
  public static function checkContextsExist($contexts, $strict = TRUE) {
    $existing_contexts = context_load(NULL, TRUE);
    $report = array();
    $return = TRUE;
    $t = get_t();
    $exists = $t('exists');
    $missing = $t('missing');
    foreach ($contexts as $context) {
      $report[$context] = (!empty($existing_contexts[$context])) ? $exists : $missing;
    }

    $variables = array('!report' => $report);
    if (in_array($missing, $report)) {
      // A module is missing.
      if ($strict) {
        $message = "One or more contexts seem to be missing. Report: !report";
        throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
      }
      else {
        // This was not strict, so just issue a notice, but no Exception.
        $message = "One or more of the contexts is missing. Report: !report";
        Message::make($message, $variables, WATCHDOG_NOTICE);

        $return = FALSE;
      }
    }

    return $return;
  }
}
