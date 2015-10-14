<?php

/**
 * @file
 * File to declare Paths class.
 */

namespace HookUpdateDeployTools;

/**
 * Public methods for altering aliases.
 */
class Paths {
  /**
   * Change the value of an alias.
   *
   * @param string $original_alias
   *   The old alias.
   * @param string $new_alias
   *   The new alias you are changing to.
   * @param string $language
   *   The language of the entity being modified.
   *
   * @return string
   *   Messsage indicating the modules are disabled
   *
   * @throws \DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function modifyAlias($original_alias, $new_alias, $language) {
    self::canUsePathauto();
    // t() might not be available at install.
    $t = get_t();
    // Bring in the pathauto.inc file.
    module_load_include('inc', 'pathauto', 'pathauto');
    // Use the old alias to get the source (drupal system path).
    $source = drupal_lookup_path('source', $original_alias);
    // Start building the $current_path variable.
    $current_path = _pathauto_existing_alias_data($source, $language);
    // Does the old alias exist?
    if (is_array($current_path)) {
      // Clone the current path array to make changes.
      $new_path = $current_path;
      // Clean the new alias and assign.
      $new_path['alias'] = pathauto_clean_alias($new_alias);
      // Make the changes.
      $saved_alias = _pathauto_set_alias($new_path, $current_path);
      // Was it successful?
      if (!empty($saved_alias)) {
        // It saved, set the success message.
        $message = $t("'!pathalias' has been set as the new alias for what used to be '!oldalias'.\n", array('!pathalias' => $new_path['alias'], '!oldalias' => $original_alias));
      }
      else {
        // For some reason the save failed.  Reason unknown.
        $message = $t("\nUPDATE FAILED: '!pathalias' save was not successful. Sorry, there is no hint of why.", array('!pathalias' => $new_path['alias']));
        throw new \DrupalUpdateException($message);
      }
    }
    else {
      // The old alias does not exist.
      $message = $t("\nUPDATE FAILED: '!pathalias' is not a current alias so could not be altered", array('!pathalias' => $original_alias));
      throw new \DrupalUpdateException($message);
    }
    // Return the message.
    return $message;
  }

  /**
   * Checks to see if pathauto in enabled.
   *
   * @throws \DrupalUpdateException
   *   Exception thrown if pathauto is not enabled.
   *
   * @return bool
   *   TRUE if enabled.
   */
  private static function canUsePathauto() {
    if (!module_exists('pathauto')) {
      $t = get_t();
      // menu_import is not enabled on this site, so this this is unuseable.
      $message = 'Path operation denied because pathauto is not enabled on this site.';
      watchdog('hook_update_deploy_tools', $message, array(), WATCHDOG_ERROR);
      $message = $t("\nUPDATE FAILED: Path operation denied because pathauto is not enabled on this site.", array());
      throw new \DrupalUpdateException($message);
    }
    else {
      return TRUE;
    }
  }
}
