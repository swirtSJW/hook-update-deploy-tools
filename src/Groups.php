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
   * Add users to an Organic Group.
   *
   * @param array $uids
   *   A flat array of user ids (uid) that should be added to the group.
   * @param int $gid
   *   The group id of the group.
   *
   * @return string
   *   A message related to what was done.
   *
   * @throws HudtException
   *   Message throwing exception if criteria is deemed unfit to declare the
   *   assignUsersToGroup a success.
   */
  public static function assignUsersToGroup($uids, $gid) {
    try {
      Check::canUse('og');
      Check::canCall('og_get_group_members_properties');
      Check::canCall('og_group');
      $uids = (array) $uids;
      Check::notEmpty('$uids', $uids);
      Check::notEmpty('$gid', $gid);
      Check::isNumeric('$gid', $gid);
      $vars = array(
        '@gid' => $gid,
        '@count_users_added' => 0,
        '@count_users_slated' => count($uids),
      );

      // Load the group to see if it is a group.
      $group = node_load($gid);
      Check::isGroup('$group', $group);
      $vars['@group_name'] = $group->title;
      $members_original = og_get_group_members_properties($group, array(), 'members', 'node');
      $vars['@count_original_members'] = count($members_original);
      // Remove any users that are already members.
      $users_to_add = array_diff($uids, $members_original);
      $vars['@count_users_to_add'] = count($users_to_add);
      $vars['@count_already members'] = $vars['@count_users_slated'] - $vars['@count_users_to_add'];

      // Load the members, but only if they have an active account. There is no
      // need to populate a group with blocked members.
      $users = user_load_multiple($users_to_add, array('status' => 1));
      $vars['@count_active_users'] = count($users);
      $vars['@count_blocked_users'] = $vars['@count_users_to_add'] - $vars['@count_active_users'];
      foreach ($users as $user) {
        $values = array(
          'entity' => $user,
        );
        og_group('node', $gid, $values);
        $vars['@count_users_added']++;
      }
      // Check to see if this worked.
      $group = node_load($gid, NULL, TRUE);
      drupal_static_reset('og_get_group_members_properties');
      $members_now = og_get_group_members_properties($group, array(), 'members', 'node');
      $vars['@count_members_now'] = count($members_now);
      $vars['@count_confirmed_added'] = $vars['@count_members_now'] - $vars['@count_original_members'];
      $msg_summary = "\n  Summary:  Slated=@count_users_slated, Added=@count_users_added, Already Members=@count_already members, Blocked Users=@count_blocked_users, Total Members=@count_members_now";

      // Verify the number of members in the group.
      if ($vars['@count_users_added'] === $vars['@count_confirmed_added']) {
        // All seems perfect.  Message and be done.
        $message = "Group:@group_name(@gid)  - @count_confirmed_added/@count_users_added were added as members.";
        $message .= $msg_summary;

        return Message::make($message, $vars, WATCHDOG_INFO, 1);
      }
      else {
        // Some discrepancy in the count.  Can't fail the update as there is no
        // way to roll it back.  Error Message and be done.
        $message = "Group:@group_name(@gid)  - @count_confirmed_added/@count_users_added were added as members.";
        $message .= "  Something went wrong: The confirmed added count does not match the number that should have been added.";
        $message .= $msg_summary;

        return Message::make($message, $vars, WATCHDOG_WARNING, 1);
      }
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Groups::assignUsersToGroup failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }
      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }
  }


  /**
   * Add members from one group, to another group.
   *
   * @param string $destination_group_name
   *   The name of the group to receive the new members.
   * @param string $source_group_name
   *   The name of the group to gather members from.
   *
   * @return string
   *   A string message to return to the hook_update_N if no exceptions.
   *
   * @throws HudtException
   *   Message throwing exception if criteria is deemed unfit to declare the
   *   cooptMembers a success.
   */
  public static function cooptMembers($destination_group_name, $source_group_name) {
    try {
      // Make sure we can use OG and call the functions needed.
      Check::canUse('og');
      Check::canCall('og_get_group_members_properties');
      Check::notEmpty('$destination_group_name', $destination_group_name);
      Check::notEmpty('$source_group_name', $source_group_name);
      $vars = array(
        '@destination_group_name' => $destination_group_name,
        '@source_group_name' => $source_group_name,
      );

      $group_destination = Groups::loadByName($destination_group_name);
      $group_source = Groups::loadByName($source_group_name);
      $vars['@nid_destination'] = $group_destination->nid;
      $vars['@nid_source'] = $group_source->nid;

      $members_source = og_get_group_members_properties($group_source, array(), 'members', 'node');
      $vars['@count_source'] = count($members_source);

      $message = Message::make("Preparing to coopt @count_source members from group:@source_group_name(@nid_source) into group:@destination_group_name(@nid_destination).", $vars, WATCHDOG_INFO, 2);

      $message .= self::assignUsersToGroup($members_source, $group_destination->nid);
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Groups::cooptMembers !destination_group_name failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }
      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }

    return $message;
  }


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
