<?php
/**
 * @file
 * Drush commands for Hook Deploy Update Tools.
 */

/**
 * Implements hook_drush_command().
 */
function hook_update_deploy_tools_drush_command() {
  $items = array();
  // The 'init' command.
  $items['site-deploy-init'] = array(
    'description' => dt("Creates the site_deploy module"),
    'arguments' => array(
      'directory-path' => dt('The directory path for where to create site_deploy, relative to where hook_deploy_update_tools is installed.'),
    ),
    'options' => array(),
    'examples' => array(
      'drush site-deploy-init' => dt('Creates site_deploy module in !path', array('!path' => '../custom')),
      'drush site-deploy-init features' => dt('Creates site_deploy module in !path', array('!path' => '../features')),
    ),
    'scope' => 'site',
    'aliases' => array('site-deploy-init', 'hook-update-deploy-tools-init'),
  );

  $items['site-deploy-export-rule'] = array(
    'description' => dt("Exports a rule to an export file, saved in location defined in hook_update_deploy_tools config"),
    'arguments' => array(
      'rule-name' => dt('The machine name of the rule to export.  Example: rules_rule_name'),
    ),
    'options' => array(),
    'examples' => array(
      'drush site-deploy-export-rule rules_rule_name' => dt('Creates the export of rule "!path"', array('!path' => 'rules_rule_name')),
    ),
    'scope' => 'site',
    'aliases' => array('site-deploy-export-rule', 'hook-update-deploy-tools-export-rule'),
  );

  return $items;
}

/**
 * Implements hook_drush_help().
 */
function hook_update_deploy_tools_drush_help($section) {
  switch ($section) {
    case 'drush:site-deploy-init':
      return dt("Creates a custom site deploy module.");

    // The 'title' meta is used to name a group of commands in `drush help`.
    case 'meta:drush:site-deploy:title':
      return dt("Commands for making life easier for generating a deploy module.");

    // The 'summary' meta item is displayed in `drush help --filter`.
    case 'meta:site-deploy:summary':
      return dt("Assists in making a custom deploy module for this site.");

    case 'drush:site-deploy-export-rule':
      return dt("Exports a Rule to an export file in a location defined by hook_update_deploy_tools config.");

    // The 'title' meta is used to name a group of commands in `drush help`.
    case 'meta:drush:site-deploy-export-rule:title':
      return dt("Commands for exporting rules.");

    // The 'summary' meta item is displayed in `drush help --filter`.
    case 'meta:site-deploy-export-rule:summary':
      return dt("Exports rules for this site.");
  }
}

/**
 * Implements drush_hook_COMMAND_validate().
 */
function hook_update_deploy_tools_site_deploy_init_validate() {
  if (drush_is_windows()) {
    // $name = drush_get_username();
    // TODO: implement check for elevated process using w32api
    // as sudo is not available for Windows
    // http://php.net/manual/en/book.w32api.php
  }
  else {
    $name = posix_getpwuid(posix_geteuid());
    if ($name['name'] !== 'root') {
      return drush_set_error('HOOK_UPDATE_DEPLOY_TOOLS', dt('There was an error creating your site_deploy module.'));
    }
  }
}

/**
 * Drush command to create site_deploy module.
 *
 * @param string $directory_path
 *   The path and name of the directory  where site_deploy should be created.
 */
function drush_hook_update_deploy_tools_site_deploy_init($directory_path = '../custom') {
  // Check to see if site_deploy already exists in the site.
  $exists = drupal_get_filename('module', 'site_deploy');
  if ($exists) {
    // site_deploy already exists.  Error out.
    $error = dt("The module site_deploy already exists at !location.", array('!location' => $exists));
    drush_log($error, 'error');
  }
  else {
    // Site_deploy does not exist, so proceed with making it.
    $hudt_dir = drupal_get_path('module', 'hook_update_deploy_tools');
    $hudt_dir = drush_normalize_path($hudt_dir);
    $boilerplate_dir = "{$hudt_dir}/boilerplate";
    // Check destination directory exists.
    $destination = "{$hudt_dir}/../{$directory_path}";
    $destination = drush_normalize_path($destination);
    if (is_dir($destination)) {
      // Make the directory site_deploy.
      $made_dir = drush_mkdir("{$destination}/site_deploy", TRUE);
      if ($made_dir) {
        // Move the files.
        drush_copy_dir("{$boilerplate_dir}/info.txt", "{$destination}/site_deploy/site_deploy.info", FILE_EXISTS_ABORT);
        drush_copy_dir("{$boilerplate_dir}/install.php", "{$destination}/site_deploy/site_deploy.install", FILE_EXISTS_ABORT);
        drush_copy_dir("{$boilerplate_dir}/module.php", "{$destination}/site_deploy/site_deploy.module", FILE_EXISTS_ABORT);
        $success = dt("The module 'site_deploy' was created at '!location'", array('!location' => $destination));
        drush_log($success, 'success');
      }

    }
    else {
      $error = dt("The destination of !destination does not seem to exist as a directory.  It should be relative to where hook_update_deploy_tools is found.", array('!destination' => $destination));
      drush_log($error, 'error');
    }
  }
}

/**
 * Drush command to export a Rule.
 *
 * @param string $rule_name
 *   The machine name of the rule to export.
 */
function drush_hook_update_deploy_tools_site_deploy_export_rule($rule_name = '') {
  if (!empty($rule_name)) {
    // Determine location and filename.
    $path = HookUpdateDeployTools\HudtInternal::getStoragePath('rules');
    $machine_name = HookUpdateDeployTools\HudtInternal::normalizeMachineName($rule_name);
    $file_name = HookUpdateDeployTools\HudtInternal::normalizeFileName($rule_name);
    $file_uri = DRUPAL_ROOT . '/' . $path . $file_name;

    // Load the rule if it exists.
    if (module_exists('rules') && function_exists('rules_config_load')) {
      $rule = rules_config_load($machine_name);
      if (!empty($rule)) {
        // It exists, so export it.
        $export_contents = $rule->export();
        // Save the file.
        try {
          $fh = fopen($file_uri, 'w');
          if ($fh) {
            fwrite($fh, $export_contents);
            fclose($fh);
            $vars = array(
              '@machine_name' => $machine_name,
              '@file_uri' => $file_uri,
            );
            $msg = dt("The Rule @machine_name import file was created or updated here: @file_uri", $vars);
            drush_log($msg, 'success');
          }
          else {
            $vars = array(
              '@file_uri' => $file_uri,
            );
            $msg = dt("Error (likely permissions) creating the file: @file_uri", $vars);
            drush_log($msg, 'error');
          }
        }
        catch (Exception $e) {
          $vars = array(
            '@file_uri' => $file_uri,
            '@error' => $e->getMessage(),
          );
          $msg = dt("Failed writing to @file_uri.  Caught exception:  @error", $vars);
          drush_log($msg, 'error');
          // Output file to terminal so it is available to use.
          drush_print("The Rule file was not generated. Outputting Rule to terminal.\n\n");
          drush_print($export_contents);
        }
      }
      else {
        // Could not be loaded, so nothign to export.  Error gracefully.
        $vars = array(
          '@machine_name' => $machine_name,
        );
        $msg = dt("The Rule '@machine_name' could not be loaded.  Please check the spelling of the machine name you are trying to export", $vars);
        drush_log($msg, 'error');
      }
    }
    else {
      // Rules does not exist.  Error out gracefully.
      drush_log('The module "Rules" is not enabled.  There is nothing to export.', 'error');
    }
  }
}
