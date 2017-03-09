<?php
/**
 * @file
 * File for methods related to Organic Groups management.
 */

namespace HookUpdateDeployTools;

/**
 * Public methods for dealing with Organic Groups.
 *
 * Currently these only are intended for node based groups.
 */
class Groups {

  /**
   * Load a Group by name.
   *
   * @param string $group_name
   *   The name (title) of the group to load.
   * @param string $bundle
   *   The name of the bundle to load (default: group).
   *
   * @return object
   *   The Group object if found.
   *
   * @throws HudtException
   *   Message throwing exception if criteria is deemed unfit to declare the
   *   group load a success.
   */
  public static function loadByName($group_name, $bundle = 'group') {
    try {
      Check::notEmpty('$group_name', $group_name);
      Check::notEmpty('$bundle', $bundle);

      $vars = array(
        '!group_name' => $group_name,
        '!bundle' => $bundle,
      );

      $query = new \EntityFieldQuery();
      $entities = $query->entityCondition('entity_type', 'node')
        ->propertyCondition('type', $bundle)
        ->propertyCondition('title', $group_name)
        ->range(0, 1)
        ->execute();

      if (!empty($entities['node'])) {
        $group = node_load(array_shift(array_keys($entities['node'])));
        Check::isGroup('$group', $group);
      }
      else {
        $message = "The Group '!group_name' Was not found so could not be loaded.";
        throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
      }
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Groups::loadByName @group_name failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }
      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }

    return $group;
  }
}
