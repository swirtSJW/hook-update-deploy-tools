<?php

namespace HookUpdateDeployTools;

/**
 * Public methods for working with Blocks.
 */
class Blocks {

  /**
   * Compares two block objects and returns a messages array.
   *
   * @param object $block_original
   *   The original block.
   * @param object $block_new
   *   The new block after the save.
   *
   * @return array
   *   An array of messages to be output indicating property value changes.
   */
  private static function diff($block_original, $block_new) {
    $properties_changed = array_keys(array_diff((array) $block_original, (array) $block_new));
    $messages = array();
    foreach ($properties_changed as $property) {
      $vars = array(
        '@original' => $block_original->$property,
        '@new' => $block_new->$property,
      );
      $messages[$property] = t('Changed from @original to @new', $vars);
    }
    $messages = (empty($messages)) ? 'nothing' : $messages;

    return $messages;
  }


  /**
   * Disables a block for a specific theme.
   *
   * @param string $module
   *   The machine name of the module that created the block.
   *   Use 'block' if was one created in the block UI.
   * @param string $block_delta
   *   the block delta (machine name of the block)
   * @param string $theme
   *   The name of the theme to target.  Defaults to default theme.
   *
   * @return string
   *   The message to output to the hook_update_N.
   *
   * @throws HudtException
   *   In the event that the disable was unssuccessful.
   */
  public static function disable($module, $block_delta, $theme = NULL) {
    try {
      // Gather the theme.
      $theme = (!empty($theme)) ? $theme : variable_get('theme_default', NULL);
      Check::notEmpty('$theme', $theme);
      Check::notEmpty('$module', $module);
      Check::notEmpty('$block_delta', $block_delta);
      $vars = array(
        '@module' => $module,
        '@block_delta' => $block_delta,
        '@theme' => $theme,
      );

      $block_original = self::load($module, $block_delta, $theme);
      if ($block_original->region === (string) \BLOCK_REGION_NONE) {
        $message = 'The block @module:@block_delta for theme:@theme was already disabled. No change.';
      }
      else {
        // The block is not disabled, so disable it.
        $fields = array(
          'region' => \BLOCK_REGION_NONE,
        );
        self::updateInstancePropertiesSilent($module, $block_delta, $theme, $fields);

        $block_new = self::load($module, $block_delta, $theme);
        // Verify that it stuck.
        if ($block_new->region === (string) \BLOCK_REGION_NONE) {
          $message = 'The block @module:@block_delta for theme:@theme was disabled.';
        }
        else {
          // The change did not stick.
          throw new HudtException('The block @module:@block_delta for theme:@theme was NOT disabled.', $vars, WATCHDOG_ERROR, TRUE);
        }
      }
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Blocks::disable @module:@block_delta for theme:@theme failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }
      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }
    $return_msg = Message::make($message, $vars, WATCHDOG_INFO, 2);

    return $return_msg;
  }


  /**
   * Enables and sets the region for a block in a specific theme.
   *
   * @param string $module
   *   The machine name of the module that created the block.
   *   Use 'block' if was one created in the block UI.
   * @param string $block_delta
   *   the block delta (machine name of the block)
   * @param string $region
   *   The name or number of the region to place the block
   * @param string $theme
   *   The name of the theme to target.  Defaults to default theme.
   *
   * @return string
   *   The message to output to the hook_update_N.
   *
   * @throws HudtException
   *   In the event that the enable was unssuccessful.
   */
  public static function enable($module, $block_delta, $region, $theme = NULL) {
    try {
      // Gather the theme.
      $theme = (!empty($theme)) ? $theme : variable_get('theme_default', NULL);
      Check::notEmpty('$theme', $theme);
      Check::notEmpty('$module', $module);
      Check::notEmpty('$block_delta', $block_delta);
      Check::notEmpty('$region', $region);
      $vars = array(
        '@module' => $module,
        '@block_delta' => $block_delta,
        '@theme' => $theme,
        '@region' => $region,
      );
      if ($region == -1) {
        // This is a disable attempt. Warn and fail.
        $message = 'Trying to set block @module:@block_delta to region:-1 would disable the block.  If that is the intention, use Blocks:disable instead.';
        throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
      }

      $block_original = self::load($module, $block_delta, $theme);
      if (($block_original->region === (string) $region) && ($block_original->status === '1')) {
        $message = 'The block @module:@block_delta for theme:@theme was already enabled in region:@region. No change.';
      }
      else {
        // The block needs to be updated.
        $fields = array(
          'region' => $region,
        );
        self::updateInstancePropertiesSilent($module, $block_delta, $theme, $fields);

        $block_new = self::load($module, $block_delta, $theme);
        // Verify that it stuck.
        if (($block_new->region === (string) $region) && ($block_new->status === '1')) {
          $message = 'The block @module:@block_delta for theme:@theme was enabled in region:@region.';
        }
        else {
          // The change did not stick.
          throw new HudtException('The block @module:@block_delta for theme:@theme was NOT enabled in region:@region.', $vars, WATCHDOG_ERROR, TRUE);
        }
      }
    }
    catch (\Exception $e) {
      $vars['!error'] = (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage();

      if (!method_exists($e, 'logMessage')) {
        // Not logged yet, so log it.
        $message = 'Blocks::enable @module:@block_delta for theme:@theme region:@region failed because: !error';
        Message::make($message, $vars, WATCHDOG_ERROR);
      }
      throw new HudtException('Caught Exception: Update aborted!  !error', $vars, WATCHDOG_ERROR, FALSE);
    }
    $return_msg = Message::make($message, $vars, WATCHDOG_INFO, 2);

    return $return_msg;
  }


  /**
   * Loads a block object.
   *
   * @param string $module
   *   The machine name of the module that created the block.
   *   Use 'block' if was one created in the block UI.
   * @param string $block_delta
   *   the block delta (machine name of the block)
   * @param string $theme
   *   The name of the theme to target.  Defaults to default theme.
   *
   * @return object
   *   The block object that was loaded.
   *
   * @throws HudtException
   *   If the block fails to load.
   */
  private static function load($module, $block_delta, $theme = NULL) {
    // Gather the theme.
    $theme = (!empty($theme)) ? $theme : variable_get('theme_default', NULL);
    Check::notEmpty('$theme', $theme);
    Check::notEmpty('$module', $module);
    Check::notEmpty('$delta', $block_delta);
    $params = array(
      ':module' => $module,
      ':delta' => $block_delta,
      ':theme' => $theme,
    );
    $block = db_query('SELECT * FROM {block} WHERE module = :module AND delta = :delta AND theme = :theme', $params)->fetchObject();
    if (!empty($block)) {
      return $block;
    }
    else {
      // Came up empty.
      $vars = array(
        '@module' => $module,
        '@delta' => $block_delta,
        '@theme' => $theme,
      );
      $message = 'Trying to load block @module:@delta from theme:@theme found no block.';
      throw new HudtException($message, $vars, WATCHDOG_ERROR, TRUE);
    }
  }


  /**
   * Updates a block with any specified properties.
   *
   * @param string $module
   *   The machine name of the module that created the block.
   *   Use 'block' if was one created in the block UI.
   * @param string $block_delta
   *   the block delta (machine name of the block)
   * @param string $theme
   *   The name of the theme to target.  Defaults to default theme.
   * @param array $block_properties
   *   An array keyed with one or more of the following elements.
   *   status: bool
   *   weight: pos or neg numbers
   *   region: the name or number of the region.
   *   visibility:
   *   pages: list of page URL(s) to place the block on.
   *   title: the administrative title of the block.
   *   cache:
   *
   * @return string
   *   message for hook_update_N().
   */
  public static function updateInstanceProperties($module, $block_delta, $theme, $block_properties) {
    $block_original = self::load($module, $block_delta, $theme);
    self::updateInstancePropertiesSilent($module, $block_delta, $theme, $block_properties);
    $block_new = self::load($module, $block_delta, $theme);
    $diff = self::diff($block_original, $block_new);
    $vars = array(
      '@module' => $module,
      '@delta' => $block_delta,
      '@theme' => $theme,
      '!diff' => $diff,
    );

    $message = 'The block @module:@delta in theme:@theme updated !diff';
    return Message::make($message, $vars, WATCHDOG_INFO, 1);
  }

  /**
   * Updates a block with any specified properties.
   *
   * @param string $module
   *   The machine name of the module that created the block.
   *   Use 'block' if was one created in the block UI.
   * @param string $block_delta
   *   the block delta (machine name of the block)
   * @param string $theme
   *   The name of the theme to target.  Defaults to default theme.
   * @param array $block_properties
   *   An array keyed with one or more of the following elements.
   *   status: bool
   *   weight: pos or neg numbers
   *   region: the name or number of the region.
   *   visibility:
   *   pages: list of page URL(s) to place the block on.
   *   title: the administrative title of the block.
   *   cache:
   */
  private static function updateInstancePropertiesSilent($module, $block_delta, $theme, $block_properties) {
    // Prepare any params.
    $theme = (!empty($theme)) ? $theme : variable_get('theme_default', NULL);
    Check::notEmpty('$theme', $theme);
    Check::notEmpty('$module', $module);
    Check::notEmpty('$delta', $block_delta);
    if (!empty($block_properties['region'])) {
      // If the region is set to none, then the status must be disabled.
      $block_properties['status'] = (int) ($block_properties['region'] != \BLOCK_REGION_NONE);
    }
    $keys = array(
      'theme' => $theme,
      'delta' => $block_delta,
      'module' => $module,
    );
    $query = db_merge('block')
      ->key($keys)
      ->fields($block_properties)
      ->execute();
  }
}
