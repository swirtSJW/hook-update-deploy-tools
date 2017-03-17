<?php

namespace HookUpdateDeployTools;

/**
 * Public methods for dealing with Vocabularies.
 */
class Vocabularies {

  /**
   * Add a vocabulary and description.
   *
   * @param string $name
   *   The name of the Vocabulary to create.
   * @param string $machine_name
   *   The machine name to use for the Vocabulary.  Must use underscores.
   * @param string $description
   *   The description to use for the Vocabulary.
   *
   * @return string
   *   A string message to return to the hook_update_N if no exceptions.
   *
   * @throws HudtException
   *   Message throwing exception if criteria is deemed unfit to declare the
   *   vocabulary creation a success.
   */
  public static function add($name, $machine_name, $description = '') {
    try {
      // Make sure we can use taxonomy and call the functions needed.
      Check::canUse('taxonomy');
      Check::canCall('taxonomy_vocabulary_machine_name_load');
      Check::canCall('taxonomy_vocabulary_save');
      Check::notEmpty('$vocabulary_name', $name);
      Check::notEmpty('$machine_name', $machine_name);

      $vars = array(
        '!name' => $name,
        '!machine_name' => $machine_name,
        '!description' => $description,
      );
      $saved_status = FALSE;
      $return_msg = '';

      // Does it already exist?  If it does, not a fatal, just skip the action.
      $vocabulary = taxonomy_vocabulary_machine_name_load($machine_name);
      if ($vocabulary !== FALSE) {
        // The Vocabulary exists.
        $vars['@saved_text'] = "exists";
        $msg = 'The Vocabulary !machine_name already exists.  Skipping Vocabularies::Add.';
        $return_msg .= Message::make($msg, $vars, WATCHDOG_INFO, 1);
      }
      else {
        // The Vocabulary does not exist.
        // Make the Vocabulary.
        $new_vocab = (object) array(
          'name' => $name,
          'description' => $description,
          'machine_name' => $machine_name,
        );
        $saved_status = taxonomy_vocabulary_save($new_vocab);
        // Was it made?
        if ($saved_status === SAVED_NEW) {
          // Saved, but let's verify it stuck in case something else altered it
          // after the fact.
          $vars['@saved_text'] = "was created";
          // The results are static cached, so may mislead us with old info.
          $vocabulary = taxonomy_vocabulary_machine_name_load($machine_name);
          if ($vocabulary === FALSE) {
            // Something went wrong.  It does not exist.  Throw exception.
            $message = 'Creating the Vocabulary !name did no go as expected. It does not exist.';
            throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
          }

        }
        else {
          // Failed to 'save new', throw an exception.
          // Something went wrong.  It does not exist.  Throw exception.
          $vars['!status'] = $saved_status;
          $message = 'Creating the Vocabulary !name did no go as expected. Status:!status';
          throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
        }
      }
      // Verify the saved data is what we put in.
      // Verify Name.
      if ($name !== $vocabulary->name) {
        // The name did not match. Throw exception.
        $vars['!saved_name'] = $vocabulary->name;
        $message = "The Vocabulary !machine_name @saved_text, but the requested name:'!name'  does not match saved name:'!saved_name'.";
        throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
      }
      // Verify Description.
      if ($description !== $vocabulary->description) {
        // The description does not match.  Throw exception.
        $vars['!saved_desc'] = $vocabulary->description;
        $message = "The Vocabulary !machine_name @saved_text, but requested description:'!description'  does not match saved description:'!saved_desc'.";
        throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
      }

    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Vocabularies::add !machine_name failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      // The update has failed.  If a new Vocabulary was created, delete it.
      if ($saved_status === SAVED_NEW) {
        // The Vocabulary was created new, so it should be deleted.
        self::delete($machine_name);
        $vars['!error'] .= " The Vocabulary {$machine_name} created by this attempt was deleted.";
      }

      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);

    }
    $return_msg .= Message::make("The Vocabulary !name (!machine_name) @saved_text with Description: !description", $vars, WATCHDOG_INFO, 1);

    return $return_msg;
  }

  /**
   * Delete a vocabulary.
   *
   * @param string $machine_name
   *   The machine name to use for the Vocabulary.  Must use underscores.
   *
   * @return string
   *   A string message to return to the hook_update_N if no exceptions.
   *
   * @throws HudtException
   *   Message throwing exception if criteria is deemed unfit to declare the
   *   vocabulary deletion a success.
   */
  public static function delete($machine_name) {
    try {
      // Make sure we can use taxonomy and call the functions needed.
      Check::canUse('taxonomy');
      Check::canCall('taxonomy_vocabulary_machine_name_load');
      Check::canCall('taxonomy_vocabulary_delete');
      Check::notEmpty('$machine_name', $machine_name);

      $vars = array(
        '!machine_name' => $machine_name,
      );

      // Does it  exist?  If it does, we can delete it.
      $vocabulary = taxonomy_vocabulary_machine_name_load($machine_name);
      if ($vocabulary === FALSE) {
        // The Vocabulary does not exist. Skip the update.
        $vars['@exists_text'] = "does not exist";
        $vars['@action_taken'] = "so was not deleted. Skipping Vocabularies::delete";
      }
      else {
        // The Vocabulary does exist.
        $vars['@exists_text'] = "exists";
        // Delete the Vocabulary.
        $delete_status = taxonomy_vocabulary_delete($vocabulary->vid);
        $vars['@deleted_status'] = $delete_status;

        // Was it deleted?
        if ($delete_status === SAVED_DELETED) {
          $vars['@action_taken'] = "was deleted";
          // Deleted, but verify it stayed delete.
          // The results are static cached, so may mislead us with old info.
          $vocabulary = taxonomy_vocabulary_machine_name_load($machine_name);
          if ($vocabulary !== FALSE) {
            // Something went wrong.  The Vocabulary did not stay deleted.
            // Throw exception.
            $message = "Deleting the Vocabulary '!machine_name' did no go as expected. It @action_taken but still @exists_text.";
            throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
          }
        }
        else {
          // Failed to delete, throw an exception.
          $message = "Deleting the Vocabulary '!machine_name' did not go as expected. Status:'@deleted_status'";
          throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
        }
      }
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Vocabularies::delete !machine_name failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);

    }
    $return_msg = Message::make("Vocabulary '!machine_name' @exists_text @action_taken.", $vars, WATCHDOG_INFO, 1);

    return $return_msg;
  }

  /**
   * Load a Vocabulary from its machine name.
   *
   * @param string $vocabulary_machine_name
   *   The human readable name of a Vocabulary.
   * @param bool $strict
   *   Flag to cause exception to be thrown if not able to load.
   *
   * @return object
   *   The Vocabulary object if found.
   *
   * @throws \HudtException if it can not be found.
   */
  public static function loadByMachineName($vocabulary_machine_name, $strict = FALSE) {
    Check::canUse('taxonomy');
    Check::canCall('taxonomy_vocabulary_machine_name_load');
    Check::notEmpty('vocabulary_machine_name', $vocabulary_machine_name);
    // Grab all the Vocabularies.
    $vocabulary = taxonomy_vocabulary_machine_name_load($vocabulary_machine_name);

    if (!empty($vocabulary)) {
      return $vocabulary;
    }
    else {
      // Vocabulary not found.
      if ($strict) {
        // The Vocabulary was not found, throw exception, call this a failure.
        $message = "There is no Vocabulary with machine name '@!name'. It could not be loaded.";
        $variables = array(
          '@name' => $vocabulary_machine_name,
        );

        throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
      }
      else {
        return FALSE;
      }
    }
  }


  /**
   * Load a Vocabulary from its human readable name.
   *
   * @param string $vocabulary_name
   *   The human readable name of a Vocabulary.
   * @param bool $strict
   *   Flag to cause exception to be thrown if not able to load.
   *
   * @return object
   *   The Vocabulary object if found.
   *
   * @throws \HudtException if it can not be found.
   */
  public static function loadByName($vocabulary_name, $strict = FALSE) {
    Check::canUse('taxonomy');
    Check::canCall('taxonomy_get_vocabularies');
    // Grab all the Vocabularies.
    $vocabularies = taxonomy_get_vocabularies(NULL);
    // Look for the vocabulary.
    foreach ($vocabularies as $vocabulary) {
      if (!empty($vocabulary) && ($vocabulary->name === $vocabulary_name)) {
        return $vocabulary;
      }
    }

    if ($strict) {
      // The Vocabulary was not found, throw exception, call this a failure.
      $message = "There is no Vocabulary '@!name'. It could not be loaded.";
      $variables = array(
        '@name' => $vocabulary_name,
      );

      throw new HudtException($message, $variables, WATCHDOG_ERROR, TRUE);
    }
    else {
      return FALSE;
    }
  }


}
