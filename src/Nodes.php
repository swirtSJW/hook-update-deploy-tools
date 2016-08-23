<?php

namespace HookUpdateDeployTools;

/**
 * Public method for changing nodes programatically.
 */
class Nodes implements ImportInterface, ExportInterface {

  /**
   * Performs the unique steps necessary to import node items from export files.
   *
   * @param string|array $node_paths
   *   The unique identifier(s) of the thing to import,
   *   usually the machine name or array of machine names.
   */
  public static function import($node_paths) {
    $t = get_t();
    $completed = array();
    $node_paths = (array) $node_paths;
    $total_requested = count($node_paths);
    try {
      self::canImport();

      foreach ($node_paths as $key => $node_path) {
        $filename = self::normalizeFileName($node_path);
        $path = self::normalizePathName($node_path);
        // If the file is there, process it.
        if (HudtInternal::canReadFile($filename, 'node')) {
          // Read the file.
          $file_contents = HudtInternal::readFileToString($filename, 'node');

          eval('$node_import = ' . $file_contents . ';');

          if (!is_object($node_import)) {
            if (empty($errors)) {
              $errors = 'Node build error on eval().';
            }
            $message = 'Unable to get a node from the import. Errors: @errors';
            throw new HudtException($message, array('@errors' => $errors), WATCHDOG_ERROR);
          }

          $error_msg = '';

          $result = self::processOne($node_import, $path);

          // No Exceptions so far, so it must be a success.
          $message = '@operation: @path - successful.';
          global $base_url;
          $link = "{$base_url}/{$result['edit_link']}";
          $vars = array(
            '@operation' => $result['operation'],
            '@path' => $path,
          );
          Message::make($message, $vars, WATCHDOG_INFO, 1, $link);
          $completed[$path] = $result['operation'];
        }
      }
    }
    catch (\Exception $e) {
      $vars = array(
        '!error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
      );
      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Node import denied because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      // Output a summary before shutting this down.
      $done = HudtInternal::getSummary($completed, $total_requested, 'Imported');
      Message::make($done, array(), FALSE, 1);

      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }

    $done = HudtInternal::getSummary($completed, $total_requested, 'Imported Nodes');
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
    // This relies on clean urls.
    $clean_urls = variable_get('clean_url', FALSE);
    if ($clean_urls) {
      return TRUE;
    }
    else {
      $message = "Node import from file, requires clean URLs, which are not enabled. Please enable Clean URLs.";
      throw new HudtException($message, array(), WATCHDOG_ERROR, TRUE);
    }
  }

  /**
   * Checks to see if nodes can be exported.
   *
   * @return bool
   *   TRUE if can be exported.
   */
  public static function canExport() {
    // Uses drupal_var_export which needs to be included.
    $file = DRUPAL_ROOT . '/includes/utility.inc';
    require_once $file;

    // This relies on clean urls.
    $clean_urls = variable_get('clean_url', FALSE);
    if ($clean_urls) {
      return TRUE;
    }
    else {
      $message = "Node export to a file, requires clean URLs, which are not enabled. Please enable Clean URLs.";
      throw new HudtException($message, array(), WATCHDOG_ERROR, TRUE);
    }
  }

  /**
   * Normalizes a path name to be the filename. Overrides HudtInternal method.
   *
   * @param string $quasi_name
   *   A path to normalize and create a filename from.
   *
   * @return string
   *   A string resembling a filename with hyphens and -export.txt.
   */
  public static function normalizeFileName($quasi_name) {
    // Remove non-drupal like leading slash.
    $quasi_name = trim($quasi_name, '/');
    $items = array(
      // Remove in case it is already present.
      '.txt' => '',
      // Convert path directories to filename friendly replacement slug.
      '/' => 'zZz',
    );
    $file_name = str_replace(array_keys($items), array_values($items), $quasi_name);
    $file_name = "{$file_name}.txt";
    return $file_name;
  }

  /**
   * Normalizes a path to have slashes and removes file appendage.
   *
   * @param string $quasi_path
   *   A path or a export file name to be normalized.
   *
   * @return string
   *   A string resembling a machine name with underscores.
   */
  public static function normalizePathName($quasi_path) {
    $items = array(
      // Remove file extension.
      '.txt' => '',
      // Convert slug back to directory slash.
      'zZz' => '/',
    );
    // @TODO Consider incorporating a limiter to make sure the path is not
    // longer than drupal supports. However this would be unable to export a
    // node from too long a path so it may be a non-issue.
    $path = str_replace(array_keys($items), array_values($items), $quasi_path);

    return $path;
  }

  /**
   * Validated Updates/Imports one page from the contents of an import file.
   *
   * @param string $node_import
   *   The node object to import.
   * @param string $path
   *   The path of the node to import/update.
   *
   * @return array
   *   Contains the elements page, operation, and edit_link.
   *
   * @throws HudtException
   *   In the event of something that fails the import.
   */
  private static function processOne($node_import, $path) {
    // Determine if a node exists at that path.
    $language = (!empty($node_import->language)) ? $node_import->language : LANGUAGE_NONE;
    $node_existing = self::getNodeFromPath($path, $language);
    $initial_vid = FALSE;
    $msg_vars = array(
      '@path' => $path,
      '@language' => $language,
    );

    if (!empty($node_existing)) {
      // A node already exists at this path.  Update it.
      $operation = t('Updated');
      $op = 'update';
      $initial_vid = $node_existing->vid;
      $saved_node = self::updateExistingNode($node_import, $node_existing);
    }
    else {
      // No node exists at this path, Check to see if the path is in use.
      $exists = self::pathExists($path, $language);
      if ($exists) {
        // The path exists.  Log and throw exception.
        $message = "The path belongs to something that is not a node.  Import of @language: @path failed.";
        throw new HudtException($message, $msg_vars, WATCHDOG_ERROR, TRUE);
      }

      // Create one.
      $operation = t('Created');
      $op = 'create';
      $saved_node = self::createNewNode($node_import);
    }
    $msg_vars['@operation'] = $operation;
    $saved_path = (!empty($saved_node->nid)) ? drupal_lookup_path('alias', "node/{$saved_node->nid}", $saved_node->language) : FALSE;

    // Begin validation.
    // Case race.  First to evaluate TRUE wins.
    switch (TRUE) {
      case (empty($saved_node->nid)):
        // Save did not complete.  No nid granted.
        $message = '@operation of @language: @path failed: The saved node ended up with no nid.';
        $valid = FALSE;
        break;

      case ($saved_path !== $path):
        // Path on newly saved node does not match the intended path.
        $msg_vars['@savedpath'] = $saved_path;
        $message = '@operation failure: The paths do not match. Intended Path: @path  Saved Path: @savedpath';
        $valid = FALSE;
        break;

      case ($saved_node->title !== $node_import->title):
        // Simple validation check to see if the saved title matches.
        $msg_vars['@intended_title'] = $node_import->title;
        $msg_vars['@saved_title'] = $saved_node->title;
        $message = '@operation failure: The titles do not match. Intended title: @intended_title  Saved Title: @saved_title';
        $valid = FALSE;
        break;

      // @TODO Consider other node properties that could be validated without
      // leading to false negatives.

      default:
        // Passed all the validations, likely it is valid.
        $valid = TRUE;

    }

    if (!$valid) {
      // Validation failed so perform rollback.
      self::rollbackImport($op, $saved_node, $initial_vid);
      throw new HudtException($message, $msg_vars, WATCHDOG_ERROR, TRUE);
    }

    $return = array(
      'node' => $saved_node,
      'operation' => "{$operation}: node/{$saved_node->nid}",
      'edit_link' => "node/{$saved_node->nid}/edit",
    );

    return $return;
  }

  /**
   * Rolls back a revision or node creation.
   *
   * @param string $op
   *   The crud op that was performed.
   * @param object $node
   *   The node object to be rolled back.
   * @param int $rollback_to_vid
   *   The revision id to roll back to.
   */
  public static function rollbackImport($op, $node, $rollback_to_vid) {
    if ($op === 'create') {
      // Op was a create, so delete the node if there was one created.
      if (!empty($node->nid)) {
        // The presence of nid indicates one was created, so delete it.
        node_delete($node->nid);
        $msg = "Node @nid created but failed validation and was deleted.";
        $variables = array(
          '@nid' => $node->nid,
        );
        Message::make($msg, $variables, WATCHDOG_INFO, 1);
      }
    }
    else {
      // Op was an update, so just delete the revision.
      $revision_list = node_revision_list($node);
      $revision_id_to_rollback = $node->vid;
      unset($revision_list[$revision_id_to_rollback]);
      if (count($revision_list) > 0) {
        $last_revision = max(array_keys($revision_list));
        $node_last_revision = node_load($node->nid, $rollback_to_vid);
        node_save($node_last_revision);
        node_revision_delete($revision_id_to_rollback);
        $msg = "Node @nid updated but failed validation, Revision @deleted deleted and rolled back to revision @rolled_to.";
        $variables = array(
          '@nid' => $node->nid,
          '@deleted' => $revision_id_to_rollback,
          '@rolled_to' => $rollback_to_vid,
        );
        Message::make($msg, $variables, WATCHDOG_INFO, 1);
      }
    }
  }


  /**
   * Create a node from the imported object.
   *
   * @param object $node
   *   The node object from the import file.
   *
   * @return object
   *   The resulting node from node_save, broken free of reference to $node.
   */
  public static function createNewNode($node) {
    // @TODO Need to add handling for field collections.
    // @TODO Need to add handling for entity reference.
    $saved_node = clone $node;
    unset($saved_node->nid);
    $saved_node->revision = 1;
    $saved_node->is_new = TRUE;
    unset($saved_node->vid);
    $log = (!empty($saved_node->log)) ? $saved_node->log : '';
    $message = t("Created from import file by hook_update_deploy_tools Node import.");
    // Concatenate the created record to the imported log message.
    $saved_node->log = "{$message}\n {$log}";
    node_save($saved_node);

    return $saved_node;
  }


  /**
   * Create a node from the imported object.
   *
   * @param object $node
   *   The node object from the import file.
   * @param object $node_existing
   *   The node object for the existing node.
   *
   * @return object
   *   The resulting node from node_save, broken free of reference to $node.
   */
  public static function updateExistingNode($node, $node_existing) {
    $saved_node = clone $node;
    $saved_node->nid = $node_existing->nid;
    $saved_node->revision = 1;
    // @TODO Need to add handling for field collections.
    // @TODO Need to add handling for entity reference.
    $saved_node->is_new = FALSE;
    $log = (!empty($saved_node->log)) ? $saved_node->log : '';
    $message = t("Updated from import file by hook_update_deploy_tools Node import.");
    // Concatenate the Updated log to the imported log message.
    $saved_node->log = "{$message}\n {$log}";
    node_save($saved_node);

    return $saved_node;
  }

  /**
   * Loads a node from a path.
   *
   * @param string $path
   *   The path of the node to import/update.
   * @param string $language
   *   The langage of the alias to look up (node->language).
   *
   * @return mixed
   *   (object) Node from that path.
   *   (bool) FALSE if there is no node to load from that path.
   */
  public static function getNodeFromPath($path, $language) {
    $node = FALSE;
    $source_path = drupal_lookup_path('source', $path, $language);
    $source_path_parts = explode('/', $source_path);
    if (!empty($source_path_parts[0]) && ($source_path_parts[0] === 'node') && !empty($source_path_parts[1])) {
      $nid = $source_path_parts[1];
      $nodes = entity_load('node', array($nid));
      $node = $nodes[$nid];
    }

    return $node;
  }

  /**
   * Check if path exists in drupal.
   *
   * @param string $path
   *   The path of the node to import/update.
   * @param string $language
   *   The langage of the alias to look up (node->language).
   *
   * @return bool
   *   TRUE if the path exists.
   *   FALSE if the path does not exist.
   */
  public static function pathExists($path, $language) {
    $exists = FALSE;
    $source_path = drupal_lookup_path('source', $path, $language);
    if (!empty($source_path)) {
      // Valid path found simply.
      $exists = TRUE;
    }
    else {
      // Must look deeper.
      $useable_path = (!empty($source_path)) ? $source_path : $path;
      $valid = drupal_valid_path($useable_path);
      if ($valid) {
        $exists = TRUE;
      }
    }

    return $exists;
  }

  /**
   * Exports a single Node based on its nid. (Typically called from Drush).
   *
   * @param string $nid
   *   The nid of the node to export.
   *
   * @return string
   *   The URI of the item exported, or a failure message.
   */
  public static function export($nid) {
    $t = get_t();
    try {
      Check::notEmpty('nid', $nid);
      Check::isNumeric('nid', $nid);
      self::canExport();
      $msg_return = '';

      // Load the node if it exists.
      $node = node_load($nid);
      Check::notEmpty('node', $node);

      $storage_path = HudtInternal::getStoragePath('node');
      $node_path = drupal_lookup_path('alias', "node/$nid");
      Check::notEmpty('node alias', $node_path);
      $node_path = self::normalizePathName($node_path);
      $file_name = self::normalizeFileName($node_path);
      $file_uri = DRUPAL_ROOT . '/' . $storage_path . $file_name;

      // Made it this far, it exists, so export it.
      $export_contents = drupal_var_export($node);

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
   * Programatically allows for the alteration of properties or 'simple fields'.
   *
   * These fields include:
   * 'title',
   * 'status',
   * 'language',
   * 'tnid',
   * 'sticky',
   * 'promote',
   * 'comment',
   * 'uid',
   * 'translate'
   *
   * @param int $nid
   *   The nid of the node.
   * @param string $field
   *   The machine name of the simple field.
   * @param string $value
   *   Value that you want to change to.
   *
   * @return string
   *   Messsage indicating the field has been changed
   *
   * @throws \HudtException
   *   Calls the update a failure, preventing it from registering the update_N.
   */
  public static function modifySimpleFieldValue($nid, $field, $value) {
    // t() might not be available during install, so get it reliably.
    $t = get_t();
    // Which fields are simple?
    $simple_fields = array(
      'title',
      'status',
      'language',
      'tnid',
      'sticky',
      'promote',
      'comment',
      'uid',
      'translate',
    );
    // Is it a simple field?
    if (in_array($field, $simple_fields)) {
      $node = node_load($nid);
      // Is there a node?
      if (!empty($node)) {
        // Does the field exist on the node?
        if (isset($node->$field)) {
          // Set the field value.
          $node->$field = $value;
          // Save the node.
          $node = node_save($node);
          // Set the message.
          $variables = array(
            '!nid' => $node->nid,
            '!fieldname' => $field,
            '!value' => $value,
          );
          $message = "On Node !nid, the field value of '!fieldname' was changed to '!value'.";
          // Success, return the message.
          return Message::make($message, $variables, WATCHDOG_INFO);;
        }
        else {
          // The field does not exist.
          $message = "The field '!fieldname' does not exist on the node !nid so it could not be altered.";
          $variables = array('!fieldname' => $field, '!nid' => $nid);
          Message::make($message, $variables, WATCHDOG_ERROR);
          throw new HudtException($message, $variables, WATCHDOG_ERROR, FALSE);
        }
      }
      else {
        // The node does not exist.
        $message = "The node '!nid' does not exist, so can not be updated.";
        $variables = array('!nid' => $nid);
        Message::make($message, $variables, WATCHDOG_ERROR);
        throw new HudtException($message, $variables, WATCHDOG_ERROR, FALSE);
      }
    }
    else {
      // The field is not a simple field so can not use this method.
      $message = "The field '!fieldname' is not a simple field and can not be changed by the method ::modifySimpleFieldValue.";
      $variables = array('!fieldname' => $field);
      Message::make($message, $variables, WATCHDOG_ERROR);
      throw new HudtException($message, $variables, WATCHDOG_ERROR, FALSE);
    }
  }
}
