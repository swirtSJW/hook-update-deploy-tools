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
   * an array of menus to be imported.
   */
  public static function import($menus) {
    $menus = (array) $menus;
    foreach ($menus as $mid => $this_menu) {
      $menu_machine_name = 'menu-' . $this_menu;
      // TO DO: make dynamic V V
      $menu_uri = 'sites/all/modules/features/fcc_menu/menu_source/menu-' .
        $this_menu . '-export.txt';
      /*$exists = db_query(
        "SELECT title FROM {menu_custom} WHERE menu_name=:menu_name",
        array(':menu_name' => $menu['menu_name']))->fetchField();
      if (!$exists) {
        menu_delete_links($menu_machine_name);
        $message = 'Deleted all previously existing items in @menu_machine_name';
        global $base_url;
        $link = $base_url . '/admin/structure/menu/manage/menu-' . $this_menu;
        watchdog('deploy_tools', $message, array('@menu_machine_name' => $menu_machine_name), WATCHDOG_WARNING, $link);
      }*/
      //Removal deleted_nodes
      $reults = menu_import_file($menu_uri, $menu_machine_name,
        array('link_to_content' => TRUE, 'remove_menu_items' => TRUE)
      );
      $message = '@deleted_nodes links deleted.';
      global $base_url;
      $link = $base_url . '/admin/structure/menu/manage/menu-' . $this_menu;
      watchdog('deploy_tools', $message, array('@deleted_nodes' =>
       $deleted_nodes), WATCHDOG_WARNING, $link);

      // Display creation message including matched_nodes + unknown_links +
      // external_links = sum total
      $total = $matched_nodes + unknown_links + external_links;
      $message = '@total menu items created consisting of:\n@matched_nodes links
       with matching paths\n@unknown_links links without matching paths\n
       @external_links external links';
      watchdog('deploy_tools', $message, array('@total' => $total,
        '@matched_nodes' => $matched_nodes, '@unknown_links' => $unknown_links),
         WATCHDOG_WARNING, $link);

      // Display any errors
      if (!empty($results['error']) {
        $error = print_r($results['error'], TRUE);
        $message = 'Erros creating menu: @error';
        watchdog('deploy_tools', $message, array('@error' => $error), WATCHDOG_ERROR, $link);
      }
      /*$result = menu_import_file($file, $menu_name, $options);

      if (!empty($result['errors'])) {
        $rows = array(array(dt('Import failed:')));
        foreach ($result['errors'] as $error) {
          $rows[] = array($error);
        }
      }
      else {
        $rows = array(array(dt('--- Import results ---')));
        if (!empty($result['warnings'])) {
          foreach ($result['warnings'] as $warn) {
            $rows[] = array($warn);
          }
          unset($result['warnings']);
        }

        $msgs = menu_import_get_messages();
        $total_items = $result['new_nodes'] + $result['matched_nodes'] + $result['external_links'] + $result['unknown_links'];

        $rows[] = array(dt($msgs['items_imported'], array('@count' => $total_items)));
        foreach ($result as $type => $value) {
          $rows[] = array(dt($msgs[$type], array('@count' => $value)));
        }
      }

      global $base_url;
      $link = $base_url . '/admin/structure/menu/manage/menu-' . $this_menu;
      watchdog('deploy_tools', $message, array(), WATCHDOG_WARNING, $link);
    }*/

    cache_clear_all();
  }
}
