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

  $items['site-deploy-export'] = array(
    'description' => dt("Exports an exportable to an export file, saved in location defined in hook_update_deploy_tools config"),
    'arguments' => array(
      'exportable' => dt('The name type of thing to export.  Example: Rules'),
      'rule-name' => dt('The machine name of the exportable item.  Example: rules_rule_name'),
    ),
    'required-arguments' => TRUE,
    'options' => array(),
    'examples' => array(
      'drush site-deploy-export Rules rules_rule_name' => dt('Creates the export of rule "!path"', array('!path' => 'rules_rule_name')),
    ),
    'scope' => 'site',
    'aliases' => array('site-deploy-export', 'hook-update-deploy-tools-export'),
  );

  $items['site-deploy-import'] = array(
    'description' => dt("Import an item from an export file, found in location defined in hook_update_deploy_tools config"),
    'arguments' => array(
      'importable' => dt('The name type of thing to import.  Example: Rules'),
      'item-name' => dt('The machine name of the importable item.  Example: rules_rule_name'),
    ),
    'required-arguments' => TRUE,
    'options' => array(),
    'examples' => array(
      'drush site-deploy-import Rule rules_rule_name' => dt('Imports  rule "!path"', array('!path' => 'rules_rule_name')),
    ),
    'scope' => 'site',
    'aliases' => array('site-deploy-import', 'hook-update-deploy-tools-import'),
  );

  $items['site-deploy-n-lookup'] = array(
    'description' => dt("Returns the update_n number for a given module"),
    'arguments' => array(
      'module-name' => dt("The machine name of the module's update to lookup."),
    ),
    'required-arguments' => TRUE,
    'options' => array(),
    'examples' => array(
      'drush site-deploy-n-lookup MY_MODULE' => dt('Returns the last run hook_update_N for the module "!module".', array('!module' => 'MY_MODULE')),
    ),
    'scope' => 'site',
    'aliases' => array('site-deploy-n-lookup'),
  );

  $items['site-deploy-n-set'] = array(
    'description' => dt("Rollsback a module's last run number back one.  WARNING! Use only for development."),
    'arguments' => array(
      'module-name' => dt("The machine name of the module's update to rollback."),
      'set-to' => dt("The number of the update to set back to."),
    ),
    'required-arguments' => 1,
    'options' => array(),
    'examples' => array(
      'drush site-deploy-n-set MY_MODULE' => dt('Alters the system setting for the "!module" hook_update_n to be one less than it was.', array('!module' => 'MY_MODULE')),
      'drush site-deploy-n-set MY_MODULE 7005' => dt('Sets the current update number for  "!module" to 7005', array('!module' => 'MY_MODULE')),
    ),
    'scope' => 'site',
    'aliases' => array('site-deploy-n-set'),
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

    case 'drush:site-deploy-export':
      return dt("Exports an exportable to an export file in a location defined by hook_update_deploy_tools config.");

    // The 'title' meta is used to name a group of commands in `drush help`.
    case 'meta:drush:site-deploy-export:title':
      return dt("Command for exporting an exportable to a file.");

    // The 'summary' meta item is displayed in `drush help --filter`.
    case 'meta:site-deploy-export:summary':
      return dt("Exports any exportable for this site.");

    case 'drush:site-deploy-import':
      return dt("Imports an item from a file in a location defined by hook_update_deploy_tools config.");

    // The 'title' meta is used to name a group of commands in `drush help`.
    case 'meta:drush:site-deploy-import:title':
      return dt("Command for importing an item from a file.");

    // The 'summary' meta item is displayed in `drush help --filter`.
    case 'meta:site-deploy-import:summary':
      return dt("Imports any HUDT importable for this site.");

    case 'drush:site-deploy-n-lookup':
      return dt("Looks up the last run number for hook_update_N for a module.");

    // The 'title' meta is used to name a group of commands in `drush help`.
    case 'meta:drush:site-deploy-n-lookup:title':
      return dt("Command for the last run number for hook_update_N for a module.");

    // The 'summary' meta item is displayed in `drush help --filter`.
    case 'meta:site-deploy-n-lookup:summary':
      return dt("Get the last run number for hook_update_N for a module.");

    case 'drush:site-deploy-n-set':
      return dt("Looks up the last run number for hook_update_N for a module and reduces it by one.");

    // The 'title' meta is used to name a group of commands in `drush help`.
    case 'meta:drush:site-deploy-n-set:title':
      return dt("Command for the last run number for hook_update_N for a module to be rolled back one.");

    // The 'summary' meta item is displayed in `drush help --filter`.
    case 'meta:site-deploy-n-set:summary':
      return dt("Set the last run number for hook_update_N for a module to the one before it.");
  }
}

/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_hook_update_deploy_tools_site_deploy_init_validate() {
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
 * Drush command to export anything that implements the ExportInterface.
 *
 * @param string $type
 *   The type of thing to export Rules, Menus... must match HUDT class name.
 * @param string $machine_name
 *   The machine name or unique identifier of the item to export.
 */
function drush_hook_update_deploy_tools_site_deploy_export($type, $machine_name) {
  try {
    $msg = array();
    $alter_plurals = array(
      'Rule' => 'Rules',
      'Node' => 'Nodes',
    );
    $type = (!empty($alter_plurals[$type])) ? $alter_plurals[$type] : $type;
    // Check to see if the class exists.
    $class = '\HookUpdateDeployTools\\' . $type;
    \HookUpdateDeployTools\Check::classExists($class);
    // Check that we are dealing with an exportable type (an ExportInterface).
    $implements = class_implements($class);
    if ((is_array($implements)) && in_array('HookUpdateDeployTools\ExportInterface', $implements)) {
      // Check that we have what it needs to export.
      $class::canExport();
      // It can export so can safely proceed.
      $msg = dt('Exported') . " $type: [$machine_name] => " . $class::export($machine_name);
    }
    else {
      // It does not implement ExportInterface.  Throw an exception.
      $msg = '\HookUpdateDeployTools\@type does not implement ExportInterface so this command can not be called.';
      $vars = array('@type' => $type);
      throw new \HookUpdateDeployTools\HudtException($msg, $vars, WATCHDOG_ERROR, FALSE);
    };
  }
  catch (\Exception $e) {
    // Any errors from this drush command do not need to be watchdog logged.
    $e->logIt = FALSE;
    $vars = array(
      '@error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
    );

    $msg = dt("site-deploy-export Caught exception:  @error", $vars);
    drush_log($msg, 'error');
    // Clear out the $msg so it is not duplicated in the return.
    $msg = '';
  }

  return $msg;
}

/**
 * Drush command to import anything that implements the ImportInterface.
 *
 * @param string $type
 *   The type of thing to import Rules, Menus... must match HUDT class name.
 * @param string $machine_name
 *   The machine name or unique identifier of the item to import.
 */
function drush_hook_update_deploy_tools_site_deploy_import($type, $machine_name) {
  try {
    $msg = array();
    $alter_plurals = array(
      'Rule' => 'Rules',
      'Node' => 'Nodes',
    );
    $type = (!empty($alter_plurals[$type])) ? $alter_plurals[$type] : $type;
    // Check to see if the class exists.
    $class = '\HookUpdateDeployTools\\' . $type;
    \HookUpdateDeployTools\Check::classExists($class);
    // Check that we are dealing with an importable type (an ImportInterface).
    $implements = class_implements($class);
    if ((is_array($implements)) && in_array('HookUpdateDeployTools\ImportInterface', $implements)) {
      // Check that we have what it needs to import.
      $class::canImport();
      // It can import so can safely proceed.
      $msg = dt('Imported') . " $type: [$machine_name] => " . $class::import($machine_name);
    }
    else {
      // It does not implement ImportInterface.  Throw an exception.
      $msg = '\HookUpdateDeployTools\@type does not implement ImportInterface so this command can not be called.';
      $vars = array('@type' => $type);
      throw new \HookUpdateDeployTools\HudtException($msg, $vars, WATCHDOG_ERROR, FALSE);
    };
  }
  catch (\Exception $e) {
    // Any errors from this drush command do not need to be watchdog logged.
    $e->logIt = FALSE;
    $vars = array(
      '@error' => (method_exists($e, 'logMessage')) ? $e->logMessage() : $e->getMessage(),
    );

    $msg = dt("site-deploy-import Caught exception:  @error", $vars);
    drush_log($msg, 'error');
    // Clear out the $msg so it is not duplicated in the return.
    $msg = '';
  }

  return $msg;
}


/**
 * Returns the last run update hook number N for a module.
 *
 * @param string $module_name
 *   Machine name for the module to lookup.
 *
 * @return mixed
 *   bool FALSE if there was no N found.
 *   int N that was found.
 */
function drush_hook_update_deploy_tools_site_deploy_n_lookup($module_name) {
  if (!empty($module_name)) {
    $vars = array(
      ':module' => $module_name,
    );
    $result = db_query('SELECT schema_version FROM system WHERE name = :module', $vars);
    $record = $result->fetchAssoc();
    $vars['@module'] = $module_name;
    if (!empty($record['schema_version'])) {
      $vars['!number'] = $record['schema_version'];

      $message = dt("@module: Last run hook_update_N = !number", $vars);
      drush_log($message, 'success');
      return $record['schema_version'];
    }
    else {
      // Module schema is not present.
      $message = dt("@module: had no update N registered.  Is it enabled?", $vars);
      drush_log($message, 'error');
      return FALSE;
    }
  }
  else {
    // Sorry the module_name can't be empty.\
    $message = dt("A module's machine_name must be specified.", $vars);
    drush_log($message, 'error');
    return FALSE;
  }
}


/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_hook_update_deploy_tools_site_deploy_n_set_validate($module_name, $set_to = FALSE) {
  // $set_to must be a number and not more than 4 characters.
  if (!empty($set_to)) {
    $lowest_version = floor(VERSION) * 1000;
    $highest_version = $lowest_version + 999;
    switch (TRUE) {
      // First evaluate to TRUE wins.
      case (!is_numeric($set_to)):
        drush_set_error('HOOK_UPDATE_DEPLOY_TOOLS', dt("The argument 'set_to' must be a number. ex: 7023"));
        break;

      case (strlen((string) $set_to) !== 4):
        drush_set_error('HOOK_UPDATE_DEPLOY_TOOLS', dt("The argument 'set_to' must be a 4 digit number. ex: 7023"));
        break;

      case ($set_to < $lowest_version):
        drush_set_error('HOOK_UPDATE_DEPLOY_TOOLS', dt("For this site, the argument 'set_to' can not be less than !number.", array('!number' => $lowest_version)));
        break;

      case ($set_to > $highest_version):
        drush_set_error('HOOK_UPDATE_DEPLOY_TOOLS', dt("For this site, the argument 'set_to' can not be more than !number.", array('!number' => $highest_version)));
        break;
    }
  }
}


/**
 * Set the update_N for a module.
 *
 * @param string $module_name
 *   Machine name for the module to set.
 * @param int $set_to
 *   (optional) the number of the _update_N to set.  If left blank it will set
 *   it to the current value -1.
 */
function drush_hook_update_deploy_tools_site_deploy_n_set($module_name, $set_to = FALSE) {
  $hook_n = drush_hook_update_deploy_tools_site_deploy_n_lookup($module_name);
  $vars = array(
    '!hook_n' => $hook_n,
    '@module' => $module_name,
  );
  if (!empty($hook_n)) {
    $lowest_version = floor(VERSION) * 1000;
    $highest_version = $lowest_version + 999;
    $set_to = (!empty($set_to)) ? $set_to : $hook_n - 1;
    $vars['!set_to'] = $set_to;
    // All other testing was done by site-deploy_n_lookup and _validate so go.
    if (($set_to >= $lowest_version) && ($set_to <= $highest_version)) {
      $num_updated = db_update('system')
        ->expression('schema_version', $set_to)
        ->condition('name', $module_name)
        ->execute();
      $vars['!num_updated'] = $num_updated;
      if ($num_updated === 1) {
        $message = dt("@module: Last run hook_update_N was set from !hook_n to !set_to", $vars);
        drush_log($message, 'success');
      }
      else {
        $message = dt("@module: db_update returned !num_updated  set from !hook_n to !set_to. Should only have been 1.", $vars);
        drush_log($message, 'error');
      }
    }
    else {
      // Can not be set to a value that goes outside the current drupal version.
      $message = dt("@module: was at !hook_n and could not be set to !set_to because it would change versions.", $vars);
      drush_log($message, 'error');
    }
  }
  else {
    // There is no module to set the update_N on..
    $message = dt("There is nothing to set because @module has no recorded value..", $vars);
    drush_log($message, 'error');
  }
}
