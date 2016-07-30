<?php

namespace HookUpdateDeployTools;

/**
 * Public methods for dealing with Fields.
 */
class Fields {
  /**
   * Deletes an instance of a field from and entity bundle.
   *
   * @param string $field_name
   *   The machine name of the field to remove.
   * @param string $bundle_name
   *   The name of the bundle to remove the field from.
   * @param string $entity_type
   *   The type of entity to remove the field from.
   *
   * @return string
   *   A string message to return to the hook_update_N if no exceptions.
   *
   * @throws DrupalUpdateException
   *   Message throwing exception if criteria is deemed unfit to declare the
   *   update a success.
   */
  public static function deleteInstance($field_name, $bundle_name, $entity_type) {
    Check::notEmpty('$field_name', $field_name);
    Check::notEmpty('$bundle_name', $bundle_name);
    Check::notEmpty('$entity_type', $entity_type);
    $msg_vars = array(
      '!field_name' => $field_name,
      '!bundle_name' => $bundle_name,
      '!entity_type' => $entity_type,
    );
    // Made it this far, safe to proceed.
    $instance = field_info_instance($entity_type, $field_name, $bundle_name);
    if ($instance) {
      $msg = 'The field instance for field:!field_name in bundle:!bundle_name in entity:!entity_type is being deleted.';
      $return = Message::make($msg, $msg_vars, WATCHDOG_INFO, 1);
      field_delete_instance($instance, TRUE);
      // Batch process the actual removals.
      field_purge_batch(10);
      // Check to see if the instance was removed.
      $instance = field_info_instance($entity_type, $field_name, $bundle_name);
      if ($instance) {
        // Something went wrong, the instance still exists. Fail the update.
        $msg = 'The field instance for field:!field_name in bundle:!bundle_name in entity:!entity_type seems to still exist.';
        $return .= Message::make($msg, $msg_vars, WATCHDOG_ERROR, 1);
      }
      else {
        // It worked.  The instance was removed.
        $msg = 'The field instance for field:!field_name was successfully removed from bundle:!bundle_name in entity:!entity_type.';
        $return .= Message::make($msg, $msg_vars, WATCHDOG_INFO, 1);
        // Check to see if there are no more instances so the field was removed.
        $active_fields = field_info_field_map();
        if (!empty($active_fields[$field_name])) {
          // The field is still in use.
          $msg = 'The field:!field_name is still in use in other entities, so the other instances were not deleted.';
          $return .= Message::make($msg, $msg_vars, WATCHDOG_INFO, 1);
        }
        else {
          // The field is not used by any other entity so it was deleted.
          $msg = 'The field:!field_name is not in use in other entities, so it was fully removed.';
          $return .= Message::make($msg, $msg_vars, WATCHDOG_INFO, 1);
        }
      }
    }
    else {
      // There is no instance.  Assume the end result is already achieved.
      // Note it, but do not fail the update.
      $msg = 'The field instance for field:!field_name in bundle:!bundle_name in entity:!entity_type does not exist.  Assuming this as already successful.';
      $return = Message::make($msg, $msg_vars, WATCHDOG_INFO, 1);
    }

    return $return;
  }

}
