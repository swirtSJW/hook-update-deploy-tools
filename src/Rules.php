<?php

/**
 * @file
 * File to declare Rules class.
 */

namespace HookUpdateDeployTools;

/**
 * Public method for importing Rules.
 */
class Rules implements ImportInterface, ExportInterface {
  /**
   * Imports rules using the rule_import module & template.
   *
   * @param array $rules
   *   An array of hyphenated machine names of rules to be imported.
   *
   * @throws HudtException
   */
  public static function import($rules) {
    $t = get_t();
    $completed = array();
    $rules = (array) $rules;
    $total_requested = count($rules);
    try {
      self::canImport();
      Check::canCall('rules_config_load');
      Check::canCall('rules_import');
      $rule_feature_path = HudtInternal::getStoragePath('rules');
      foreach ($rules as $rid => $rule_file_prefix) {
        $filename = HudtInternal::normalizeFileName($rule_file_prefix);
        $rule_machine_name = HudtInternal::normalizeMachineName($rule_file_prefix);
        // If the file is there, process it.
        if (HudtInternal::canReadFile($filename, 'rules')) {
          // Read the file.
          $file_contents = HudtInternal::readFileToString($filename, 'rules');
          $error_msg = '';

          // Use the maching name to see if it exists.
          $existing_rule = rules_config_load($rule_machine_name);
          $imported_rule = rules_import($file_contents, $error_msg);
          if (!empty($existing_rule)) {
            $operation = $t('Overwrote');
            $imported_rule->id = $existing_rule->id;
            unset($imported_rule->is_new);
          }
          else {
            $operation = $t('Created');
          }

          if ($imported_rule->integrityCheck()) {
            // Passed integrity check, save it.
            $imported_rule->save();
          }
          else {
            // Failed integrity check.
            $message = 'Rule @operation of @rule_machine_name - Failed integrity check. Not saved. Aborting update.';
            $vars = array(
              '@operation' => $operation,
              '@rule_machine_name' => $rule_machine_name,
            );
            throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
          }

          // Verify that the save happened by reloading the rule.
          $saved_rule = rules_config_load($rule_machine_name);

          if (!empty($imported_rule) && empty($error_msg) && !empty($saved_rule)) {
            // Success.
            $message = '@operation: @rule_machine_name - imported successfully.';
            global $base_url;
            $link = "{$base_url}/admin/config/workflow/rules/reaction/manage/{$rule_machine_name}";
            $vars = array(
              '@operation' => $operation,
              '@rule_machine_name' => $rule_machine_name,
            );
            Message::make($message, $vars, WATCHDOG_INFO, 1, $link);
            $completed[$rule_machine_name] = $t('Imported');
          }
          else {
            // The rule import failed.  Pass on the error message.
            $vars = array(
              '@error' => $error_msg,
              '@rule_machine_name' => $rule_machine_name,
              '@file_prefix' => $rule_file_prefix,
            );
            $message = "The requested rule import '@rule_machine_name' failed with the following error: '@error'. Adjust your @file_prefix-export.txt rule text file accordingly and re-run update.";
            throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
          }
        }
      }
    }
    catch (\Exception $e) {
      $vars = array(
        '!error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
      );
      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Rule import denied because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      // Output a summary before shutting this down.
      $done = HudtInternal::getSummary($completed, $total_requested, 'Imported');
      Message::make($done, array(), FALSE, 1);

      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }

    $done = HudtInternal::getSummary($completed, $total_requested, 'Imported');
    return $done;
  }

  /**
   * Checks to see if rules in enabled and functions are available.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public static function canImport() {
    Check::canUse('rules');
    Check::canCall('rules_import');
    return TRUE;
  }

  /**
   * Checks to see if rules can be exported.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public static function canExport() {
    Check::canUse('rules');
    return TRUE;
  }


  /**
   * Exports a single Rule when called (typically called from Drush).
   *
   * @param string $rule_machine_name
   *   The machine name of the Rule to export.
   *
   * @return string
   *   The URI of the item exported, or a failure message.
   */
  public static function export($rule_machine_name) {
    $t = get_t();
    try {
      Check::notEmpty('rule_machine_name', $rule_machine_name, TRUE);
      $msg_return = '';
      $path = HudtInternal::getStoragePath('rules');
      $machine_name = HudtInternal::normalizeMachineName($rule_machine_name);
      $file_name = HudtInternal::normalizeFileName($rule_machine_name);
      $file_uri = DRUPAL_ROOT . '/' . $path . $file_name;
      Check::canUse('rules');
      Check::canCall('rules_config_load');
      // Load the rule if it exists.
      $rule = rules_config_load($machine_name);
      if (!empty($rule)) {
        // It exists, so export it.
        $export_contents = $rule->export();
        // Save the file.
        $msg_return = HudtInternal::writeFile($file_uri, $export_contents);
      }
      else {
        // Could not be loaded, so nothing to export.  Error gracefully.
        $vars = array(
          '@machine_name' => $machine_name,
        );
        $msg_error = $t("The Rule '@machine_name' could not be loaded.  Please check the spelling of the machine name you are trying to export", $vars);

        $msg_return = $t('ERROR') . ': ';
        $msg_return .= $t("Rule not found.  Check the spelling of the machine name you are trying to export");
      }
    }
    catch (\Exception $e) {
      // Any errors from this command do not need to be watchdog logged.
      $e->logIt = FALSE;
      $vars = array(
        '!error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
      );
      $msg_error = $t("Caught exception:  !error", $vars);
    }
    if (!empty($msg_error)) {
      drush_log($msg_error, 'error');
    }
    return (!empty($msg_return)) ? $msg_return : $msg_error;
  }
}
