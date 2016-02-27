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

  return $items;
}

/**
 * Implements hook_drush_help().
 */
function hook_update_deploy_tools_drush_help($section) {
  switch ($section) {
    case 'drush:site-deploy-init':
      return dt("Creates a dite deploy module.");

    // The 'title' meta is used to name a group of commands in `drush help`.
    case 'meta:drush:site-deploy:title':
      return dt("Commands for making life easier for generating a deploy module.");

    // The 'summary' meta item is displayed in `drush help --filter`.
    case 'meta:site-deploy:summary':
      return dt("Assists in making a custom deploy module for this site.");
  }
}

/**
 * Implements drush_hook_COMMAND_validate().
 */
function hook_update_deploy_tools_validate() {
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
  $module_name = 'site_deploy';
  // Check to see if site_deploy already exists in the site.
  $exists = drupal_get_filename('module', $module_name);
  if ($exists) {
    // site_deploy already exists.  Error out.
    $error = dt("The module @name already exists at !location.", array('@name' => $module_name, '!location' => $exists));
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
      $made_dir = drush_mkdir("{$destination}/{$module_name}", TRUE);
      if ($made_dir) {
        $files = array(
          // Source filename => Destination filename.
          'comp.json' => 'composer.json',
          'info.yml' => "{$module_name}.info.yml",
          'install.php' => "{$module_name}.install",
          'module.php' => "{$module_name}.module",
          'post_update.php' => "{$module_name}.post_update.php",
        );
        // Move the files.
        foreach ($files as $src => $dest) {
          drush_copy_dir("{$boilerplate_dir}/{$src}", "{$destination}/{$module_name}/{$dest}", FILE_EXISTS_ABORT);
        }
        $success = dt("The module '@name' was created at '!location'", array('@name' => $module_name, '!location' => $destination));
        drush_log($success, 'success');
      }

    }
    else {
      $error = dt("The destination of !destination does not seem to exist as a directory.  It should be relative to where hook_update_deploy_tools is found.", array('!destination' => $destination));
      drush_log($error, 'error');
    }
  }
}
