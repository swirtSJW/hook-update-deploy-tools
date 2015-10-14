<?php

/**
 * @file
 * File to declare Paths class.
 */

namespace HookUpdateDeployTools;

/**
 * Public method for changing the value of a node alias.
 */
class Paths {
  /**
   * Change the value of the node alias.
   *
   * @param string $oldalias
   *   The old alias.
   * @param string $newalias
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
  public static function modifyAlias($oldalias, $newalias, $language) {
    // We invoke the t() vis a vis $t = get_t();.
    $t = get_t();
    // If path auto does not exist, then there are no aliases to change.
    if (module_exists('pathauto')) {
      // Just in case we invoke the pathauto.inc file.
      module_load_include('inc', 'pathauto', 'pathauto');
      // Using the old alias we get the source (the primary address of the
      // content resource).
      $source = drupal_lookup_path('source', $oldalias);
      // We also start building the $path variable.
      $path = _pathauto_existing_alias_data($source, $language);
      // If the old alias actually exists.
      if (is_array($path)) {
        // Clean the new alias.
        $clean_alias = pathauto_clean_alias($newalias);
        // Make the $path array the $existing_alias.
        $existing_alias = $path;
        // Set the 'alias' element as the new cleaned alias.
        $path['alias'] = $clean_alias;
        // And now using the $existing_alias and the $path, set the
        // new alias up.
        _pathauto_set_alias($path, $existing_alias);
        // Set the message.
        $message = $t("'!pathalias' has been set as the new alias for what used to be '!oldalias'.\n", array('!pathalias' => $path['alias'], '!oldalias' => $oldalias));
      }
      else {
        // If the old alias does not exist.
        $message = $t("'!pathalias' is not a current alias.", array('!pathalias' => $oldalias));
        throw new \DrupalUpdateException($message);
      }
      // Return the message.
      return $message;
    }
    else {
      // If pathauto is not a module on the site.
      $message = 'Change of alias denied because pathauto is not enabled on this site.';
      watchdog('hook_update_deploy_tools', $message, array(), WATCHDOG_ERROR);
      $message = $t("\nUPDATE FAILED: Alias change denied because pathauto is not enabled on this site.", array());
      throw new \DrupalUpdateException($message);
    }
  }
}
