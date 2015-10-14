<?php

/**
 * @file
 * File to declare Nodes class.
 */

namespace HookUpdateDeployTools;

/**
 * Public method for changing simple fields in nodes programatically.
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
   *   The name of the simple field.
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
    // If the field above is a simple field.
    if (in_array($field, $simple_fields)) {
      $node = node_load($nid);
      // If this is a node with that field.
      if (!empty($node) && isset($node->$field)) {
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
      // Else this is not a node with that field.
      else {
        $message = $t("The field '!fieldname' does not exist or the node does not exist so it could not be alterred.", array('!fieldname' => $field));
        throw new \DrupalUpdateException($message);
      }
    }
    // Else send error that this is not a simple field.
    else {
      $message = $t("The field '!fieldname' is not a simple field, can not be changed by the method ::modifySimpleFieldValue.", array('!fieldname' => $field));
      throw new \DrupalUpdateException($message);
    }
  }
}
