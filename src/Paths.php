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
   *   Messsage indicating success.  Failure messages come from Watchdog.
   *
   * @throws \DrupalUpdateException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function modifyAlias($original_alias, $new_alias, $language) {
    $return = self::checkSame($original_alias, $new_alias);
    if (empty($return)) {
      // They are not the same. Proceed.
      $alias = self::getAliasObject($original_alias, $new_alias, $language);
      if (self::checkAliasesExist($alias)) {
        // Made it this far without exception, clear for change.
        $return = self::changeAlias($alias);
      }
    }
    return $return;
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
      // menu_import is not enabled on this site, so this this is unuseable.
      $message = 'Path operation denied because pathauto is not enabled on this site.';
      $variables = array();
      Message::make($message, $variables, WATCHDOG_ERROR);
    }
    else {
      return TRUE;
    }
  }

  /**
   * Assuming all alias prechecks have passed, this method makes the save.
   *
   * @param object $alias
   *   The alias object for passing around
   */
  private static function changeAlias($alias) {
    $alias->original->path = _pathauto_existing_alias_data($alias->original->source, $alias->original->language);
    $variables = array('!new_alias' => $alias->new->alias, '!old_alias' => $alias->original->alias);
    if (!empty($alias->original->path)) {
      // Clone the current path array to make changes.
      $alias->new->path = $alias->original->path;
      // Clean the new alias and assign.
      $alias->new->path['alias'] = $alias->new->alias;
    }
    else {
      // Original alias could not be loaded.  Blame language.
      $message = "The original alias of could not be loaded.  This is most likely due to specifying the wrong language in the call to modifyAlias. Adjust your update hook and try again.";
      Message::make($message, $variables, WATCHDOG_ERROR);
      $return = FALSE;
    }
    // Make the changes.
    $saved_alias = _pathauto_set_alias($alias->new->path, $alias->original->path);
    // Was it successful?
    if (!empty($saved_alias)) {
      // It saved, set the success message.
      $message = "'!new_alias' has been set as the new alias for what used to be '!old_alias'.";
      $return = Message::make($message, $variables, WATCHDOG_INFO);
    }
    else {
      // For some reason the save failed.  Reason unknown.
      $message = "Alias save of '!new_alias' was not successful in replacing '!old_alias'. Sorry, unable to say why. Adjust your update hook and try again.";
      Message::make($message, $variables, WATCHDOG_ERROR);
      $return = FALSE;
    }
    return $return;
  }

  /**
   * Check to see if two alias values are the same.
   *
   * @param string $original_alias
   *   The original alias.
   * @param string $new_alias
   *   The new alias requested
   *
   * @return mixed
   *   string message if they the same
   *   bool FALSE if the are not the same.
   */
  private static function checkSame($original_alias, $new_alias) {
    if ($original_alias == $new_alias) {
      // They are the same, no need to do anything  Set a message but no error.
      $message = "The requested alias change of '!original' to '!new' is the same.  No alteration required.";
      $variables = array('!original' => $original_alias, '!new' => $new_alias);
      $return = Message::make($message, $variables, WATCHDOG_INFO);
      $return = (!empty($return)) ? $return : TRUE;
    }
    else {
      $return = FALSE;
    }
    return $return;
  }

  /**
   * Checks to see if the aliases exist in an alterable combination.
   *
   * @param object $alias
   *   An alias object containing $alias->original and $alias->new
   *
   * @return bool
   *   TRUE if alterable combination
   *   FALSE if not an alterable combination (WATCHDOG ERRORs throw exception.)
   */
  public static function checkAliasesExist($alias) {
    $variables = array('!original_alias' => $alias->original->alias, '!new_alias' => $alias->new->alias);
    if (empty($alias->original->source) && empty($alias->new->source)) {
      // The original alias does not exist.
      $message = "Alias '!original_alias' does not exist so could not be altered, and '!new_alias' does not already exist.  Adjust your update hook and try again.";
      Message::make($message, $variables, WATCHDOG_ERROR);
      $return = FALSE;
    }
    elseif (empty($alias->original->source) && !empty($alias->new->source)) {
      // The original alias does not exist and the new one does.  Make the
      // assumption that the change has already been made, so warn, but don't
      // fail the update.
      $message = "'!new_alias' already exists and '!original_alias' does not.  Assuming this change has already been made.";
      $return = Message::make($message, $variables, WATCHDOG_INFO);
      $return = FALSE;
    }
    elseif (!empty($alias->original->source) && !empty($alias->new->source)) {
      // The new alias is already in use, so can not be reassigned.
      // Fail the update.
      $message = "'!new_alias' already exists and '!original_alias' does too.  The pre-existance of the new alias blocks the change.";
      Message::make($message, $variables, WATCHDOG_ERROR);
      $return = FALSE;
    }
    else {
      // This is the case where original alias is not empty and the new alias
      // is empty.  Good to go.
      $return = TRUE;
    }
    return $return;
  }

  /**
   * Build and get the $alias object to be passed around.
   *
   * @param string $original_alias
   *   The original alias.
   * @param string $new_alias
   *   The new alias to set.
   * @param string $language
   *   Drupal language value.
   *
   * @return \stdClass
   *   An object containing original and new alias properties.
   */
  private static function getAliasObject($original_alias, $new_alias, $language) {
    $alias = new \stdClass();
    self::canUsePathauto();
    $alias->original = new \stdClass();
    $alias->new = new \stdClass();
    $alias->original->alias = $original_alias;
    // Bring in the pathauto.inc file.
    module_load_include('inc', 'pathauto', 'pathauto');
    $alias->new->alias = pathauto_clean_alias($new_alias);
    $alias->original->language = $language;
    $alias->new->language = $language;
    // Use the old alias to get the source (drupal system path).
    $alias->original->source = drupal_lookup_path('source', $original_alias);
    // Build the new path only to see if it already exists.
    $alias->new->source = drupal_lookup_path('source', $new_alias);

    return $alias;
  }
}
