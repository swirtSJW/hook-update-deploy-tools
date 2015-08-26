<?php
namespace DeployTools;

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
    $t = get_t();
    $menu_feature = check_plain(variable_get('deploy_tools_menu_feature', ''));
    $menu_feature_uri = drupal_get_path('module', $menu_feature);
    foreach ($menus as $mid => $menu_machine_name) {
      $menu_uri = "{$menu_feature_uri}/menu_source/{$menu_machine_name}-export.txt";

      if (file_exists($menu_uri)) {
        // Import the menu with 'remove menu items' and 'link to content' options.
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
        watchdog('deploy_tools', $message, $vars, WATCHDOG_WARNING, $link);

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
        watchdog('deploy_tools', $message, $vars, WATCHDOG_WARNING, $link);

        // Display any errors.
        if (!empty($results['error'])) {
          $error = print_r($results['error'], TRUE);
          $message = '@menu_machine_name: Errors creating menu: @error';
          $vars = array(
            '@error' => $error,
            '@menu_machine_name' => $menu_machine_name,
           );
          watchdog('deploy_tools', $message, $vars, WATCHDOG_ERROR, $link);
          throw new \DrupalUpdateException($t("\nUPDATE FAILED: The requested menu import '@menu_machine_name' failed with the following errors @error. Adjust your @menu_machine_name-export.txt menu text file accordingly and re-run update.", $vars));
        }
      } else {
        $vars = array(
          '@error' => $error,
          '@menu_machine_name' => $menu_machine_name,
          '@menu_uri' => $menu_uri,
         );
      throw new \DrupalUpdateException($t("\nUPDATE FAILED: The requested menu import '@menu_machine_name' failed because the requested file '@menu_uri' was not found. Adjust your @menu_machine_name-export.txt menu text file accordingly and re-run update.", $vars));
      }
      menu_cache_clear($menu_machine_name);
    }
    $done = $t('Menu imports complete');
    return $done;
  }
}

