<?php

namespace HookUpdateDeployTools;

/**
 * Public methods for dealing with Vocabularies.
 */
class Terms implements ImportInterface, ExportInterface {

  /**
   * Perform the unique steps necessary to import terms items from export files.
   *
   * @param string|array $vocabulary_term_names
   *   The unique identifier(s) of the vocabulary terms to import, created from
   *   the "Vocabulary name|Term name" - use the human name not machine_name.
   */
  public static function import($vocabulary_term_names) {
    $t = get_t();
    $completed = array();
    $vocabulary_term_names = (array) $vocabulary_term_names;
    $total_requested = count($vocabulary_term_names);
    try {
      Check::notEmpty('$vocabulary_term_names', $vocabulary_term_names);
      self::canImport();

      foreach ($vocabulary_term_names as $key => $vocabulary_term_name) {
        // Get the elements out of the vocabulary term name mashup.
        $vocabulary_name = self::getVocabularyName($vocabulary_term_name);
        $term_name = self::getTermName($vocabulary_term_name);

        $filename = self::normalizeFileName($vocabulary_term_name);
        // If the file is there, process it.
        if (HudtInternal::canReadFile($filename, 'term')) {
          // Read the file.
          $file_contents = HudtInternal::readFileToString($filename, 'term');

          eval('$term_import = ' . $file_contents . ';');

          if (!is_object($term_import)) {
            if (empty($errors)) {
              $errors = 'Term build error on eval().';
            }
            $message = 'Unable to get a term from the import. Errors: @errors';
            throw new HudtException($message, array('@errors' => $errors), WATCHDOG_ERROR);
          }

          $error_msg = '';

          $result = self::importOne($term_import, $vocabulary_name, $term_name);

          // No Exceptions so far, so it must be a success.
          $message = '@operation: @vterm - successful.';
          global $base_url;
          $link = "{$base_url}/{$result['edit_link']}";
          $vars = array(
            '@operation' => $result['operation'],
            '@vterm' => "{$vocabulary_name}:{$term_name}",
          );
          Message::make($message, $vars, WATCHDOG_INFO, 1, $link);
          $completed["{$vocabulary_name}:{$term_name}"] = $result['operation'];
        }
      }
    }
    catch (\Exception $e) {
      $vars = array(
        '!error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
      );
      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Term import denied because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      // Output a summary before shutting this down.
      $done = HudtInternal::getSummary($completed, $total_requested, 'Imported');
      Message::make($done, array(), FALSE, 1);

      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }

    $done = HudtInternal::getSummary($completed, $total_requested, 'Imported Terms');
    return $done;
  }

  /**
   * Verifies that that import can be used based on available module.
   *
   * @return bool
   *   TRUE If the import can be run.
   *
   * @throws \DrupalUpdateException if it can not be run.
   */
  public static function canImport() {
    Check::canUse('taxonomy');

    return TRUE;
  }

  /**
   * Checks to see if Terms can be exported.
   *
   * @return bool
   *   TRUE if can be exported.
   */
  public static function canExport() {
    // Uses drupal_var_export which needs to be included.
    $file = DRUPAL_ROOT . '/includes/utility.inc';
    require_once $file;
    Check::canUse('taxonomy');

    return TRUE;
  }

  /**
   * Delete a Term.
   *
   * @param string $term_name
   *   The human term name to be deleted.
   * @param string $vocabulary_name
   *   The human or machine name of the Vocabulary the term resides in.
   *
   * @return string
   *   A string message to return to the hook_update_N if no exceptions.
   *
   * @throws HudtException
   *   Message throwing exception if criteria is deemed unfit to declare the
   *   term deletion a success.
   */
  public static function delete($term_name, $vocabulary_name) {
    try {
      // Make sure we can use taxonomy and call the functions needed.
      Check::canUse('taxonomy');
      Check::canCall('taxonomy_term_delete');
      Check::notEmpty('$term_name', $term_name);
      Check::notEmpty('$vocabulary_name', $vocabulary_name);

      $vars = array(
        '@term_name' => $term_name,
        '@vocabulary_name' => $vocabulary_name,
      );

      // Does it  exist?  If it does, we can delete it.
      $term = self::loadByName($term_name, $vocabulary_name, FALSE);

      if (empty($term)) {
        // The Term does not exist. Skip the delete.
        $vars['@exists_text'] = "does not exist";
        $vars['@action_taken'] = "so was not deleted. Skipping Terms::delete";
      }
      else {
        // The Term does exist.
        $vars['@tid'] = $term->tid;
        $vars['@exists_text'] = "exists";
        // Delete the Term.
        $delete_status = taxonomy_term_delete($term->tid);
        $vars['@deleted_status'] = $delete_status;

        // Was it deleted?
        if ($delete_status === SAVED_DELETED) {
          $vars['@action_taken'] = "was deleted";
          // Deleted, but verify it stayed deleted.
          // The results are static cached, so may mislead us with old info.
          $term = self::loadByName($term_name, $vocabulary_name, FALSE);
          if (!empty($term)) {
            // Something went wrong.  The Term did not stay deleted.
            // Throw exception.
            $message = "Deleting the Term '@vocabulary_name:@term_name' did no go as expected. It @action_taken but still @exists_text.";
            throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
          }
        }
        else {
          // Failed to delete, throw an exception.
          $message = "Deleting the Term '@vocabulary_name:@term_name' did not go as expected. Status:'@deleted_status'";
          throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
        }
      }
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = "Terms::delete Term '@vocabulary_name:@term_name' failed because: !error";
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);

    }
    $return_msg = Message::make("Term @vocabulary_name:@term_name(@tid) @exists_text @action_taken.", $vars, WATCHDOG_INFO, 1);

    return $return_msg;
  }


  /**
   * Gets the Term Name from the $vocabulary_term_names. Strict.
   *
   * @param string $vocabulary_term_name
   *   A string made up of "Vocabulary Name|Term Name"  (not machine names).
   *
   * @return string
   *   The Term Name.
   */
  private static function getTermName($vocabulary_term_name) {
    $parts = explode('|', $vocabulary_term_name);
    $term_name = (!empty($parts[1])) ? $parts[1] : '';
    Check::notEmpty('$term_name', $term_name);
    return $term_name;
  }

  /**
   * Gets the Vocabulary Name from the $vocabulary_term_names. Strict.
   *
   * @param string $vocabulary_term_name
   *   A string made up of "Vocabulary Name|Term Name"  (not machine names).
   *
   * @return string
   *   The Vocabulary Name.
   */
  private static function getVocabularyName($vocabulary_term_name) {
    $parts = explode('|', $vocabulary_term_name);
    $vocabulary_name = (!empty($parts[0])) ? $parts[0] : '';
    Check::notEmpty('$vocabulary_name', $vocabulary_name);
    return $vocabulary_name;
  }

  /**
   * Normalizes a path name to be the filename. Overrides HudtInternal method.
   *
   * @param string $quasi_name
   *   A path to normalize and create a filename from.
   *
   * @return string
   *   A string resembling a filename with hyphens and .txt.
   */
  private static function normalizeFileName($quasi_name) {
    $items = array(
      // Remove spaces.
      ' ' => 'sSs',
      // Convert to separator.
      '|' => 'zZz',
    );
    $file_name = str_replace(array_keys($items), array_values($items), $quasi_name);
    $file_name = "{$file_name}.txt";
    return $file_name;
  }

  /**
   * Normalizes unique string that identifies the vocabulary and term names.
   *
   * @param string $vocabulary_name
   *   The name of the vocabulary.
   * @param string $term_name
   *   The name of the term.
   *
   * @return string
   *   A string resembling a machine name with underscores.
   */
  private static function normalizeVocTermName($vocabulary_name, $term_name) {
    $items = array(
      // Remove spaces.
      ' ' => 'sSs',
    );
    $vocabulary_name = str_replace(array_keys($items), array_values($items), $vocabulary_name);
    $term_name = str_replace(array_keys($items), array_values($items), $term_name);
    return "{$vocabulary_name}zZz{$term_name}";
  }

  /**
   * Validated Updates/Imports one term from the contents of an import file.
   *
   * @param string $term_import
   *   The term object to import.
   * @param string $vocabulary_name
   *   The name of the vocabulary to import/update.
   * @param string $term_name
   *   The name of the term to import/update
   *
   * @return array
   *   Contains the elements page, operation, and edit_link.
   *
   * @throws HudtException
   *   In the event of something that fails the import.
   */
  private static function importOne($term_import, $vocabulary_name, $term_name) {
    // Determine if the term exists in that vocabulary.
    $term_existing = Terms::loadByName($term_name, $vocabulary_name);
    $msg_vars = array(
      '@vocabulary_name' => $vocabulary_name,
      '@term_name' => $term_name,
    );

    self::unpackParents($term_import);

    if (!empty($term_existing)) {
      // A term already exists.  Update it.
      $operation = t('Updated');
      $op = 'update';
      $saved_term = self::updateExistingTerm($term_import, $term_existing);
    }
    else {
      // No term exists in this Vocabulary.  Create It.
      $operation = t('Created');
      $op = 'create';
      $saved_term = self::createNewTerm($term_import);
    }
    $msg_vars['@operation'] = $operation;

    // Begin validation.
    // Case race.  First to evaluate TRUE wins.
    switch (TRUE) {
      case (empty($saved_term->tid)):
        // Save did not complete.  No tid granted.
        $message = '@operation of @language: @vocabulary_name : @vocabulary_term failed: The saved term ended up with no tid.';
        $valid = FALSE;
        break;

      case ($saved_term->name !== $term_import->name):
        // Simple validation check to see if the saved title matches.
        $msg_vars['@intended_title'] = $term_import->title;
        $msg_vars['@saved_title'] = $saved_term->title;
        $message = '@operation failure: The term names do not match. Intended title: @intended_title  Saved Title: @saved_title';
        $valid = FALSE;
        break;

      // @TODO Consider other Term properties that could be validated without
      // leading to false negatives.

      default:
        // Passed all the validations, likely it is valid.
        $valid = TRUE;

    }

    if (!$valid) {
      // Validation failed so perform rollback.
      self::rollbackImport($op, $saved_term);
      throw new HudtException($message, $msg_vars, WATCHDOG_ERROR, TRUE);
    }

    $return = array(
      'term' => $saved_term,
      'operation' => "{$operation}: term/{$saved_term->tid}",
      'edit_link' => "taxonomy/term/{$saved_term->tid}/edit",
    );

    return $return;
  }

  /**
   * Rolls back a term to its original state or deletes a creation.
   *
   * @param string $op
   *   The crud op that was performed.
   * @param object $term
   *   The term's original state to roll back to.
   */
  private static function rollbackImport($op, $term) {
    $variables = array(
      '@tid' => $term->tid,
      '@name' => $term->name,
      '@op' => $op,
    );
    if ($op === 'create') {
      // Op was a create, so delete the Term if there was one created.
      if (!empty($term->tid)) {
        // The presence of tid indicates one was created, so delete it.
        taxonomy_term_delete($term->tid);
        $msg = "Term @name(@tid)  was @opd but failed validation and was deleted.";
      }
      else {
        // Create was attempted but not completed.  Nothing to roll back.
        $msg = "The term:@name failed @op and had nothing to roll back.";
      }
    }
    else {
      // Op was an update, so attempt to swap in the original term.
      if (!empty($term->original)) {
        // The original values are available, so re-set them.
        taxonomy_term_save($term->original);
        $msg = "Term @name(@tid)  @opd but failed validation, The term was rolled back to its previous state. Verify manually.";
      }
      else {
        $msg = "Term @name(@tid)  @opd but failed validation. An original copy was not available to roll back.  Verify manually";
      }
    }

    Message::make($msg, $variables, WATCHDOG_INFO, 2);
  }


  /**
   * Create a Term from the imported object.
   *
   * @param object $term
   *   The Term object from the import file.
   *
   * @return object
   *   The resulting Term from term_save, broken free of reference to $term.
   */
  private static function createNewTerm($term) {

    $saved_term = clone $term;
    // Remove the tid as it will be assigned one upon save.
    unset($saved_term->tid);
    // Need to lookup the vocabulary since vid may be different.
    $vocabulary = Vocabularies::loadByMachineName($saved_term->vocabulary_machine_name, TRUE);
    $saved_term->vid = $vocabulary->vid;

    // @TODO Need to add handling for field collections.
    // @TODO Need to add handling for entity references.

    taxonomy_term_save($saved_term);

    return $saved_term;
  }


  /**
   * Update an existing term from the imported object.
   *
   * @param object $term_import
   *   The Term object from the import file.
   * @param object $term_existing
   *   The Term object for the existing Term.
   *
   * @return object
   *   The Term from term_save, broken free of reference to $term_import.
   */
  private static function updateExistingTerm($term_import, $term_existing) {
    $saved_term = clone $term_import;
    $saved_term->tid = $term_existing->tid;

    // @TODO Need to add handling for field collections.
    // @TODO Need to add handling for entity reference.
    // Entity reference works as long as the entity being referenced exists and
    // has the same entity id as the one being referenced.

    taxonomy_term_save($saved_term);

    return $saved_term;
  }

  /**
   * Load a term by name.
   *
   * @param string $term_name
   *   The human readable name of a Term.
   * @param string $vocabulary_name
   *   The human readable or machine name of a Vocabulary.
   * @param bool $strict
   *   Flag to cause exception to be thrown if not able to load.
   *
   * @return mixed
   *   (object) term object if found.
   *   (bool) FALSE.
   *
   * @throws HudtException
   *   In the event of something that fails the import.
   */
  public static function loadByName($term_name, $vocabulary_name, $strict = FALSE) {
    Check::canUse('taxonomy');
    Check::canCall('taxonomy_get_term_by_name');
    $variables = array(
      '@name' => $term_name,
      '@vocab' => $vocabulary_name,
    );
    // Convert vocabulary name to machine name.
    $vocabulary = Vocabularies::loadByName($vocabulary_name, FALSE);
    $vocabulary = (!empty($vocabulary)) ? $vocabulary : Vocabularies::loadByMachineName($vocabulary_name, FALSE);
    if (empty($vocabulary)) {
      // The Vocabulary was not found, throw exception, call this a failure.
      // Even if not $strict this should throw an exception because you need a
      // vocabulary to look for a term.,
      $message = "There is no Vocabulary '@vocab'. It could not be loaded.";
      throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
    }

    $term = array_shift(taxonomy_get_term_by_name($term_name, $vocabulary->machine_name));

    if (!empty($term)) {
      return $term;
    }
    else {
      if ($strict) {
        // The Term was not found, throw exception, call this a failure.
        $message = "There is no Term '@name'in @vocab. It could not be loaded.";
        throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
      }
      else {
        return FALSE;
      }
    }
  }


  /**
   * Exports a single term based on its tid. (Typically called from Drush).
   *
   * @param string $tid
   *   The tid of the Term to export.
   *
   * @return string
   *   The URI of the item exported, or a failure message.
   */
  public static function export($tid) {
    $t = get_t();
    try {
      Check::notEmpty('tid', $tid);
      Check::isNumeric('tid', $tid);
      self::canExport();
      $msg_return = '';

      // Load the term if it exists.
      $term = taxonomy_term_load($tid);
      Check::notEmpty('term', $term);
      Check::notEmpty('term name', $term->name);
      Check::notEmpty('vocabulary_machine_name', $term->vocabulary_machine_name);
      // Get the vocabulary name.
      $vocabulary = Vocabularies::loadByMachineName($term->vocabulary_machine_name, TRUE);
      Check::notEmpty('vocabulary_name', $vocabulary->name);

      $voc_term_name = self::normalizeVocTermName($vocabulary->name, $term->name);
      $file_name = self::normalizeFileName($voc_term_name);
      $storage_path = HudtInternal::getStoragePath('term');
      $file_uri = DRUPAL_ROOT . '/' . $storage_path . $file_name;

      // Add parents. These will be unpacked at import.
      $term->parents = taxonomy_get_parents($tid);

      // Get pathauto path.
      if (!empty($term->path) && isset($term->path['pathauto']) && ($term->path['pathauto'] === '0')) {
        // Lookup the alias and store it.
        $alias = drupal_get_path_alias("taxonomy/term/{$tid}");
        $term->path['alias'] = $alias;
      }

      // Made it this far, it exists, so export it.
      $export_contents = drupal_var_export($term);

      // Save the file.
      $msg_return = HudtInternal::writeFile($file_uri, $export_contents);

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


  /**
   * Unpacks import ->parents and creates ->parent for importing any parents.
   *
   * @param object $term_import
   *   The term object from the import file.
   *
   * @throws HudtException
   *   In the event that the parent does not exist.
   */
  private static function unpackParents(&$term_import) {
    $new_parents = array();
    if (!empty($term_import->parents)) {
      // We have parents to deal with.

      foreach ($term_import->parents as $parent) {
        // Look up the parent in this environment to get the tid.
        $local_parent = self::loadByName($parent->name, $parent->vocabulary_machine_name);
        if (empty($local_parent)) {
          // The parent does not exist locally, log an error.
          $variables = array(
            '@vocab_name' => $term_import->vocabulary_machine_name,
            '@term_name' => $term_import->name,
            '@parent_name' => $parent->name,
          );
          $message = "Import of @vocab_name:@term_name failed because the parent:@parent_name does not exist.";
          throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
        }
        else {
          // The parent exists locally, so use its tid.
          $new_parents[] = $local_parent->tid;
        }
      }
    }
    if (!empty($new_parents)) {
      // Add the local parents.
      $term_import->parent = $new_parents;
    }
    else {
      // Needs to be array(0) in order to set <root> as the parent.
      $term_import->parent = array(0);
    }
    unset($term_import->parents);
  }
}
