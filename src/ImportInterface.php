<?php
/**
 * @file
 * Defines the iImport interface for importing stuff into Drupal.
 */

namespace HookUpdateDeployTools;

/**
 * iImport is the interface for all Hook Update Deploy Tools that import items.
 *
 * Primarilly all methods in iExport are run through hook_update_N so any
 * Exceptions being thrown should be \DrupalUpdateException.
 */
interface ImportInterface {

  /**
   * The import method performs the unique steps necessary to impor the item.
   *
   * @param string|array $import_items
   *   The unique identifier(s) of the thing to import,
   *   usually the machine name or array of machine names.
   */
  public static function import($import_items);

  /**
   * Verifies that that import can be used based on available module.
   *
   * @return bool
   *   TRUE If the import can be run.
   *
   * @throws \DrupalUpdateException if it can not be run.
   */
  public static function canImport();
}
