<?php
/**
 * @file
 * File for methods related to Vocabulary management.
 */

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
        $message = "The Vocabulary !machine_name @saved_text, but requested:'!name'  does not match saved:'!saved_name'.";
        throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
      }
      // Verify Description.
      if ($description !== $vocabulary->description) {
        // The description does not match.  Throw exception.
        $vars['!saved_desc'] = $vocabulary->description;
        $message = "The Vocabulary !machine_name @saved_text, but requested:'!description'  does not match saved:'!saved_desc'.";
        throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
      }

    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Vocabulary::add !machine_name failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      // The update has failed.  If a new vocabulary was created, delete it.
      if ($saved_status) {
        // The Vocabulary was created new.
        // @TODO Build a delete.
      }

      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);

    }
    $return_msg .= Message::make("The Vocabulary !name (!machine_name) @saved_text with Description: !description", $vars, WATCHDOG_INFO, 1);

    return $return_msg;
  }
}
