<?php

/**
 * @file
 * File to declare Menus class.
 */

namespace HookUpdateDeployTools;

/**
 * Public method for importing menus.
 */
class Menus implements ImportInterface {
  /**
   * Imports menus using the menu_import module & template.
   *
   * @param array $menus
   *   An array of machine names of menus to be imported.
   *
   * @throws \DrupalUpdateException
   *   If any of the imports fail, the exception fails the update.
   */
  public static function import($menus) {
    $t = get_t();
    $completed = array();
    $menus = (array) $menus;
    $total_requested = count($menus);
    try {
      self::canImport();
      $menu_feature_storage_uri = HudtInternal::getStoragePath('menu');
      foreach ($menus as $mid => $menu_machine_name) {
        $filename = "{$menu_machine_name}-export.txt";
        $menu_uri = "{$menu_feature_storage_uri}{$filename}";

        if (HudtInternal::canReadFile($filename, 'menu')) {
          // Import the menu w/ options.
          $options = array(
            'link_to_content' => TRUE,
            'remove_menu_items' => TRUE,
          );
          $results = menu_import_file($menu_uri, $menu_machine_name, $options);

          // Display message about removal of deleted_menu_items.
          $message = '@menu_machine_name: @deleted_menu_items links deleted.';
          global $base_url;
          $link = "{$base_url}/admin/structure/menu/manage/{$menu_machine_name}";
          $vars = array(
            '@deleted_menu_items' => $results['deleted_menu_items'],
            '@menu_machine_name' => $menu_machine_name,
          );
          Message::make($message, $vars, WATCHDOG_INFO, 1, $link);

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
          Message::make($message, $vars, WATCHDOG_INFO, 1, $link);
          $completed[$menu_machine_name] = $t('Imported');

          // Display any errors.
          if (!empty($results['error'])) {
            $error = print_r($results['error'], TRUE);
            $variables = array(
              '@error' => $error,
              '@menu_machine_name' => $menu_machine_name,
            );
            $message = "The requested menu import '@menu_machine_name' failed with the following errors @error. Adjust your @menu_machine_name-export.txt menu text file accordingly and re-run update.";
            Message::make($message, $variables, WATCHDOG_ERROR, 1, $link);
            throw new HudtException($message, $vars, WATCHDOG_ERROR, FALSE);
          }
        }
        menu_cache_clear($menu_machine_name);
      }
    }
    catch (\Exception $e) {
      $message = 'Menu import failed because: !error';
      $variables = array(
        '!error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
      );

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        Message::make($message, $vars, WATCHDOG_ERROR);
      }

      // Output a summary before shutting this down.
      $done = $t("Menu imports NOT complete! \n");
      $done .= HudtInternal::getImportSummary($completed, $total_requested);
      Message::make($done, array(), FALSE, $indent);

      throw new \DrupalUpdateException($t('Caught Exception: Update aborted!  !error', $variables));
    }

    $done = $t('Menu imports complete');
    $done .= HudtInternal::getImportSummary($completed, $total_requested);

    return $done;
  }


  /**
   * Checks to see if menu_import in enabled.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public static function canImport() {
    Check::canUse('menu_import');
    Check::canCall('menu_import_file');
    return TRUE;
  }

}
