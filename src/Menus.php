<?php

/**
 * @file
 * File to declare Menus class.
 */

namespace HookUpdateDeployTools;

/**
 * Public method for importing menus.
 */
class Menus {
  /**
   * Imports menus using the menu_import module & template.
   *
   * @param array $menus
   *   An array of machine names of menus to be imported.
   */
  public static function import($menus) {
    $menus = (array) $menus;
    self::canUseMenuImport();
    $t = get_t();
    $menu_feature = check_plain(variable_get('hook_update_deploy_tools_menu_feature', ''));
    $menu_feature_uri = drupal_get_path('module', $menu_feature);
    foreach ($menus as $mid => $menu_machine_name) {
      $menu_uri = "{$menu_feature_uri}/menu_source/{$menu_machine_name}-export.txt";

      if (file_exists($menu_uri)) {
        // Import the menu w/ 'remove menu items' and 'link to content' options.
        $results = menu_import_file($menu_uri, $menu_machine_name,
          array('link_to_content' => TRUE, 'remove_menu_items' => TRUE)
        );

        // Display message about removal of deleted_menu_items.
        $message = '@menu_machine_name: @deleted_menu_items links deleted.';
        global $base_url;
        $link = "{$base_url}/admin/structure/menu/manage/{$menu_machine_name}";
        $vars = array(
          '@deleted_menu_items' => $results['deleted_menu_items'],
          '@menu_machine_name' => $menu_machine_name,
        );
        watchdog('hook_update_deploy_tools', $message, $vars, WATCHDOG_WARNING, $link);

        // Display creation message including matched_nodes + unknown_links +
        // external_links = sum total.
        $total = $results['matched_nodes'] + $results['unknown_links'] + $results['external_links'];
        $message = '@menu_machine_name: @total total menu items created consisting of:
        @matched_nodes links with matching paths
        @unknown_links links without matching paths
        @external_links external links';
        $vars = array(
          '@total' => $total,
          '@matched_nodes' => $results['matched_nodes'],
          '@unknown_links' => $results['unknown_links'],
          '@external_links' => $results['external_links'],
          '@menu_machine_name' => $menu_machine_name,
        );
        watchdog('hook_update_deploy_tools', $message, $vars, WATCHDOG_WARNING, $link);

        // Display any errors.
        if (!empty($results['error'])) {
          $error = print_r($results['error'], TRUE);
          $message = '@menu_machine_name: Errors creating menu: @error';
          $vars = array(
            '@error' => $error,
            '@menu_machine_name' => $menu_machine_name,
          );
          watchdog('hook_update_deploy_tools', $message, $vars, WATCHDOG_ERROR, $link);
          throw new \DrupalUpdateException($t("\nUPDATE FAILED: The requested menu import '@menu_machine_name' failed with the following errors @error. Adjust your @menu_machine_name-export.txt menu text file accordingly and re-run update.", $vars));
        }
      }
      else {

        $vars = array(
          '@error' => $error,
          '@menu_machine_name' => $menu_machine_name,
          '@menu_uri' => $menu_uri,
        );
        $message = $t("\nUPDATE FAILED: The requested menu import '@menu_machine_name' failed because the requested file '@menu_uri' was not found. Re-run update when the file has been placed there.", $vars);
        throw new \DrupalUpdateException($message);
      }
      menu_cache_clear($menu_machine_name);
    }
    $done = $t('Menu imports complete');
    return $done;
  }

  /**
   * Checks to see if menu_import in enabled.
   *
   * @throws \DrupalUpdateException
   *   Exception thrown if menu_import is not enabled.
   *
   * @return bool
   *   TRUE if enabled.
   */
  private static function canUseMenuImport() {
    if (!module_exists('menu_import')) {
      $t = get_t();
      // menu_import is not enabled on this site, so this this is unuseable.
      $message = 'Menu import denied because menu_import is not enabled on this site.';
      watchdog('hook_update_deploy_tools', $message, array(), WATCHDOG_ERROR);
      throw new \DrupalUpdateException($t("\nUPDATE FAILED: Menu import denied because menu_import is not enabled on this site.", array()));
    }
    else {
      return TRUE;
    }
  }

}
