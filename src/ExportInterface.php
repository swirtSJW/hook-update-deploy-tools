<?php
/**
 * @file
 * Defines the iExport interface for exporting stuff from Drupal.
 */

namespace HookUpdateDeployTools;

/**
 * iExport is the interface for all Hook Update Deploy Tools that export items.
 *
 * Primarilly all methods in iExport are run through Drush commands so any
 * Exceptions being thrown should be \Exception.
 */
interface ExportInterface {
  /**
   * Exports the exportable type to a text file.
   *
   * @param string $export_item
   *   The unique identifier of the thing to export,
   *   usually the machine name.
   *
   * @return string
   *   A string showing the full uri of the exported item, or a failure message.
   *
   * @throws \Exception if it fails.
   */
  public static function export($export_item);


  /**
   * Verifies that that import can be used based on available module.
   *
   * @return bool
   *   TRUE If the import can be run.
   *
   * @throws \Exception if it can not be run.
   */
  public static function canExport();
}
