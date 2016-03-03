<?php

/**
 * @file
 * File to declare Rules class.
 */

namespace HookUpdateDeployTools;

/**
 * Public method for importing Rules.
 */
class Rules {
  /**
   * Imports rules using the rule_import module & template.
   *
   * @param array $rules
   *   An array of hyphenated machine names of rules to be imported.
   */
  public static function import($rules) {
    $rules = (array) $rules;
    self::canUseRuleImport();
    $t = get_t();
    $rule_feature_path = HudtInternal::getStoragePath('rules');
    $completed = array();
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
          Message::make($message, $vars, WATCHDOG_ERROR, 1);
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
          $variables = array(
            '@error' => $error_msg,
            '@rule_machine_name' => $rule_machine_name,
            '@file_prefix' => $rule_file_prefix,
          );
          $message = "The requested rule import '@rule_machine_name' failed with the following error: '@error'. Adjust your @file_prefix-export.txt rule text file accordingly and re-run update.";
          Message::make($message, $variables, WATCHDOG_ERROR, 1, $link);
        }
      }
    }
    $count = count($completed);
    $vars = array(
      '@count' => $count,
      '!completed' => print_r($completed, TRUE),
    );
    $done = $t('Summary: Imported @count rules: !completed', $vars);
    return $done;
  }

  /**
   * Checks to see if rules in enabled.
   *
   * @throws \DrupalUpdateException
   *   Exception thrown if rule_import is not enabled.
   *
   * @return bool
   *   TRUE if enabled.
   */
  private static function canUseRuleImport() {
    if (!module_exists('rules') && function_exists('rules_import')) {
      // Rules is not enabled on this site, so this is unuseable.
      $message = 'Rule import denied because rule_import is not enabled on this site.';
      $variables = array();
      Message::make($message, $variables, WATCHDOG_ERROR);
    }
    else {
      return TRUE;
    }
  }
}
