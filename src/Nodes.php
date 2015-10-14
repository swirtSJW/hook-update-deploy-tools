<?php

/**
 * @file
 * File to declare Nodes class.
 */

namespace HookUpdateDeployTools;

/**
 * Public method for changing nodes programatically.
 */
class Nodes {
  /**
   * Programatically allows for the alteration of 'simple fields'.
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
   * @throws \DrupalUpdateException
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
          $vars = array(
            '!nid' => $node->nid,
            '!fieldname' => $field,
            '!value' => $value,
          );
          $message = $t("On Node !nid, the field value of '!fieldname' was changed to '!value'.\n", $vars);
          // Return the message.
          return $message;
        }
        else {
          // The field does not exist.
          $message = $t("\nUPDATE FAILED: The field '!fieldname' does not exist on the node !nid so it could not be alterred.", array('!fieldname' => $field, '!nid' => $nid));
          throw new \DrupalUpdateException($message);
        }
      }
      else {
        // The node does not exist.
        $message = $t("\nUPDATE FAILED: The node '!nid' does not exist.", array('!nid' => $nid));
        throw new \DrupalUpdateException($message);
      }
    }
    else {
      // The field is not a simple field so can not use this method.
      $message = $t("\nUPDATE FAILED: The field '!fieldname' is not a simple field and can not be changed by the method ::modifySimpleFieldValue.", array('!fieldname' => $field));
      throw new \DrupalUpdateException($message);
    }
  }
}
