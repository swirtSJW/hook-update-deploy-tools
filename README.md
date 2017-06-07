Hook Update Deploy Tools
============

CONTENTS OF THIS FILE
---------------------
 * <a href="#introduction">Introduction</a>
 * <a href="#methods">Methods / Uses</a>
 * <a href="#requirements">Requirements</a>
 * <a href="#installation">Installation</a>
 * <a href="#configuration">Configuration</a>
 * <a href="#hook-update">Anatomy of a hook_update_N()</a>
 * <a href="#maintainers">Maintainers</a>

-------------------------------------------

## <a name="introduction"></a>Introduction

Drupal provides its own functions for enabling modules or reverting features ...
however, most of them run silently without feedback so they are inappropriate
for use in hook_update_N because they do not provide any feedback as to what is
happening and whether it was a success or failure.  This module gives voice to
many of those functions.

Every method provided that can be used within a hook_update_N() includes detailed
feedback and logging of what was attempted and what the results were.  Updates
are Failed if the requested operation was not successful.  This so that they can be run
again, or re-worked.

If you already have a custom deploy module for your site, you can continue using
it. If you don't have one, create a custom deploy module for your site,
run `'drush site-deploy-init'`.
It will create a starter deploy module 'site_deploy' in modules/custom.
The module site_deploy's .install should be used to place your hook_update_N()
that will handle automated sitewide deployment.

This module contains several HookUpdateDeployTools::methods to help manage a site
releases programmatically through hook_update_N():

## <a name="methods"></a>Methods / Uses
* **Deploy Utilities**
    * <a href="#site-deploy-create">Create a custom site_deploy module</a>
    * <a href="#messages">Hook Update Messages</a>
    * <a href="#lookup-get">Get Last Run hook_update_N</a>
    * <a href="#lookup-set">Set Last Run hook_update_N</a>
* **Aliases** (pathauto)
    * <a href="#update-alias">Update Alias</a>
* **Blocks**
    * <a href="#block-disable">Disable Block</a>
    * <a href="#block-enable">Enable a Block to a region</a>
    * <a href="#block-update-instance">Update an instance of a Block</a>
* **Features**
    * <a href="#revert">Revert Features</a>
    * <a href="#revert-forced">Revert Features Forced</a>
    * <a href="#revert-component">Revert Specific Feature Components</a>
* **Groups** (Organic Groups)
    * <a href="#group-add-users">Add Users to a Group</a>
    * <a href="#group-add-members">Add members from another Group</a>
    * <a href="#group-load-group">Load a Group (by name)</a>
* **Menus**
    * <a href="#menu-export">Export Menus with Drush</a>
    * <a href="#import-menu">Import Menus</a>
    * <a href="#import-menu-drush">Import Menus with Drush</a>
* **Modules**
    * <a href="#disable">Disable modules</a>
    * <a href="#disable-uninstall">Disable and Uninstall modules</a>
    * <a href="#enable">Enable modules</a>
    * <a href="#uninstall">Uninstall modules</a>
* **Nodes**
    * <a href="#field-delete">Delete Fields</a>
    * <a href="#export-node">Export Nodes</a>
    * <a href="#import-node">Import Nodes</a>
    * <a href="#update-node">Update Node Values / Properties</a>
* **Page Manager**
    * <a href="#export-page-manager-page">Export Page Manager pages</a>
    * <a href="#import-page-manager-page">Import Page Manager pages</a>
* **Redirects**
    * <a href="#import-redirects">Import Redirects</a>
* **Rules**
    * <a href="#export-rule">Export Rules</a>
    * <a href="#import-rule">Import Rules</a>
* **Variables**
    * <a href="#setting-variable">Set Drupal Variables</a>
    * <a href="#setting-variable">Change Drupal Variables</a>
* **Views**
    * <a href="#views-enable">Enable a View</a>
    * <a href="#views-delete">Delete a View</a>
    * <a href="#views-disable">Disable a View</a>
* **Vocabularies**
    * <a href="#vocabulary-add">Add Vocabulary</a>
    * <a href="#vocabulary-delete">Delete Vocabulary</a>
    * <a href="#vocabulary-load">Load a Vocabulary</a>
    * **Terms**
        * <a href="#vocabulary-terms-delete">Delete Terms</a>
        * <a href="#vocabulary-terms-export">Export Terms</a>
        * <a href="#vocabulary-terms-import">Import/Update Terms</a>
        * <a href="#vocabulary-terms-load">Load Terms</a>




*BONUS:* This module has a class autoloader, so there is no need to do any module_includes or require_onces.

-------------------------------------------

## <a name="requirements"></a>Requirements

This module has several soft requirements.
*  Reverting Features requires the Features module.
*  Importing menus requires the Menu Import module.
*  Importing/Exporting Rules requires the Rules module.
*  Importing/Exporting Page Manager pages requires ctools & page_manager modules.
*  Altering a path requires the Pathauto module.

-------------------------------------------

## <a name="installation"></a>Installation


* It is a good practice to add this module as a dependency to your custom
  deployment module.
* Enable this module
* (optional) run 'drush site-deploy-init' to create site_deploy module in modules/custom.

-------------------------------------------

## <a name="configuration"></a>Configuration


* Navigate to /admin/config/development/hook_update_deploy_tools and enter the
  name of your site's custom deploy module.
* If you have other Feature(s) that would be a better location for import files
  for menus, Page Manager pages, or rules, add those as well.  This is only needed if you will be
  using Hook Update Deploy Tools to import them.

  -------------------------------------------

## Method / Uses

-------------------------------------------

## <a name="deploy-utilities"></a>Deploy Utilities

### <a name="site-deploy-create"></a>Create a custom site_deploy module

1. run 'drush site-deploy-init' to create site_deploy module in modules/custom.
2. Add update_hook_N to the site_deploy.install
3. Unlike every other module that will not run any existing update hooks when
   you enable it, this module is special.  I WILL run any existing hook_update_N
   when you enable it.




### <a name="messages"></a>Output safe messages and watchdog log in hook_update

If you are doing something custom and want to provide messages to drush terminal
or Drupal message and Watchdog log the output, make use of this method:

Add something like this to a hook_update_N in your custom module.install.

```php

  // Simple message example:
  $msg = "I did something cool I'm telling you about.";
  $return =  HookUpdateDeployTools\Message::make($msg);

  // A more robust example:
  // Watchdog style message.
  $msg = "I did something cool during update and created @count new nodes.";
  // Optional Watchdog style variables array. Arrays or Objects are welcome
  // variable values.
  $variables = array('@count' => count($some_array_i_built)));
  // Optional Watchdog level. If FALSE, it will output the message
  // but not log it to watchdog. (Default: WATCHDOG_NOTICE)
  $watchdog_level = WATCHDOG_WARNING
  // Optional value to indent the message. (Default: 1)
  $indent = 2;
  // Optional link to to pass to watchdog. (Default: NULL)
  $link = ''
  $return .=  HookUpdateDeployTools\Message::make($msg, $variables, $watchdog_level, $indent, $link);

  return $return;

```

If you are logging something as WATCHDOG_ERROR or more serious, you should
immediately follow that with an Exception to declare the update a failure.

```php

// Throw an exception to declare this hook_update_N a failure.
throw new HookUpdateDeployTools\HudtException($msg, $variables, WATCHDOG_ERROR, FALSE);

```



###<a name="lookup-get">Lookup the last run hook_update_N</a>

In developing hook_updates_N's it is often necessary know what the last run
update is on a server.

```
drush site-deploy-n-lookup MODULE_NAME
```



###<a name="lookup-set">Set last run hook_update_N</a>
Sometimes locally it is necessary to keep running the same hook_update_N
locally, until you get it right.  These two commands can be helpful for
development use locally.

```
// Sets the N to whatever it was, minus 1. It's a 'rollback'.
drush site-deploy-n-set MODULE_NAME

// Sets the N for the module to 7032
drush site-deploy-n-set MODULE_NAME 7032
```

-------------------------------------------

## <a name="alias"></a>Alias (Pathauto)


### <a name="update-alias"></a>Update Alias

This method does require the pathauto module.
Add this to a hook_update_N in your custom deploy module.install.

```php
  $message = HookUpdateDeployTools\Nodes::modifyAlias($old_alias, $new_alias, $language);
  return $message;

```

This will attempt to alter the alias if the old_alias exists.  The language has
to match the language of the original alias being modified (usually matches the
node that it is assigned to).


-------------------------------------------

## <a name="blocks"></a>Blocks


### <a name="block-disable"></a>Disable a Block

To disable a block (move it to region 'none')
Add this to a hook_update_N in your custom deploy module.install.

```php
  // $theme is optional.  If not specified it will apply it to the default theme.
  $message = HookUpdateDeployTools\Blocks::disable($module, $block_delta, $theme = NULL);
  return $message;

```

### <a name="block-enable"></a>Enable a Block Instance to a Region

To enable a block instance (move it to a region)
Add this to a hook_update_N in your custom deploy module.install.

```php
  // $theme is optional.  If not specified it will apply it to the default theme.
  $message = HookUpdateDeployTools\Blocks::enable($module, $block_delta, $region_name, $theme = NULL);
  return $message;

```

### <a name="block-update-instance"></a>Update an instance of a Block (not block content)

To update properties of a block instance you need to specify the properties you
wish to update.  All $block properties are optional.  Only those that are
included will be altered.  The others will be unaffected.
Add this to a hook_update_N in your custom deploy module.install.

```php
  // $theme is optional.  If not specified it will apply it to the default theme.
  // $block properties are limited to the following:
  //  status: bool
  //  weight: pos or neg numbers
  //  region: the name or number of the region.
  //  visibility:
  //  pages: list of page URL(s) to place the block on.
  //  title: the administrative title of the block.
  //  cache:
  $block_properties = array(
    'pages' => '<front>',
    'weight' => 23,
  );
  $message = HookUpdateDeployTools\Blocks:: updateInstanceProperties($module, $block_delta, $theme, $block_properties);
  return $message;

```

-------------------------------------------

## <a name="features"></a>Features


### <a name="revert"></a>Revert a Feature


* Any time you want to revert a Feature(s) add a hook_update_N() to the .install
  of that Feature.

```php
/**
 * Add some fields to content type Page
 */
function custom_basic_page_update_7002() {
  $features = array(
    'FEATURE_NAME',
  );
  $message = HookUpdateDeployTools\Features::revert($features);
  return $message;
}
```

In the odd situation where you need to revert features in
some particular order, you can add them to the $features array in order.

In the even more odd situation where you need to do some operation in between
reverting one feature an another, you can use this example to concatenate the
messages into one.

```php
/**
 * Add some fields to content type Page
 */
function custom_basic_page_update_7002() {
  $features = array(
    'custom_fields',
    'custom_views',
  );
  $message = HookUpdateDeployTools\Features::revert($features);
  // Do some other process like clear cache or set some settings.
   $features = array(
    'custom_basic_page',
  );
  $message .= HookUpdateDeployTools\Features::revert($features);

  return $message;
}
```

### <a name="revert-forced"></a>Revert a Feature Forced

In rare cases where you need to force revert all components of a Feature even though
they are not shown as overridden, you can add the optional second argument to the
revert like this:

```php
  $features = array(
    'FEATURE_NAME',
  );
  $message = HookUpdateDeployTools\Features::revert($features, TRUE);
```

### <a name="revert-component"></a>Revert Specific Feature Components


To revert only specific components of a Feature you can add the component name
to the request like this:

```php
  $features = array(
    'FEATURE_NAME.COMPONENT_NAME',
  );
  $message = HookUpdateDeployTools\Features::revert($features);
```

-------------------------------------------

## <a name="groups"></a>Groups (Organic Groups)

### <a name="group-add-users"></a>Add Users to a Group

You can add users to a group by adding the following in a hook_update_N() to the .install

```php
  // A flat array of user ids for the members to add to the group.
  $uids = array(2, 34, 333);
  // The node id of the group you wish to add members to.
  $gid = 721
  $message = HookUpdateDeployTools\Groups::assignUsersToGroup($uids, $gid);
```


### <a name="group-add-members"></a>Add members from another Group (co-opt)

Sometimes you need to take the members from one group and co-opt them into
another group.  You can add users from one group to another group by adding the
following in a hook_update_N() to the .install

```php
  // This will take all the non-blocked members from $source_group_name and
  // add them to $destination_group_name if they are not already members.
  $message = HookUpdateDeployTools\Groups::cooptMembers($destination_group_name, $source_group_name);
```



### <a name="group-load-group"></a>Load a Group (by name)

Sometimes you need to load a group object within an update.  You can do this by
adding the  following in a hook_update_N() to the .install

```php
  // $group_name Is the node title on the group node.
  $group = HookUpdateDeployTools\Groups::loadByName($group_name);
```

-------------------------------------------

## <a name="menus"></a>Menus

###  <a name="menu-export"></a>Export a Menu

Export is done using the Menu Export/Import module https://www.drupal.org/project/menu_import
This module has an export method and is required by Hook Update Deploy Tools for
importing menus, so use it to export your menus.

```
  // The first parameter is the path and filename to be created.  The second
  // parameter is the machine name of the menu.
  drush menu-export path_to_menu_feature/menu_source/menu-MACHINE-NAME-export.txt machine-name --line-ending=unix
```

###  <a name="import-menu"></a>Import a Menu

Menus can be imported from a text file that matches the standard output of
the menu_import module.
https://www.drupal.org/project/menu_import

In order to import menus on deployment, it is assumed/required that you have a
Feature that controls menus (could be site_deploy instead).  Within that Feature,
add a directory 'menu_source'.  This is where you will place your menu import
files.  The files will be named the same way they would be if generated by menu_import
(menu-{machine-name)-export.txt) You will also need to make Hook Update Deploy
Tools aware of this custom menu Feature by going here
/admin/config/development/hook_update_deploy_tools and entering the machine name
of the menu Feature or leave it alone to default to site_deploy. Though for true
deployment, this value should be assigned through a hook_update_N using

```php
  $message =  HookUpdateDeployTools\Settings::set('hook_update_deploy_tools_menu_feature', 'MENU_FEATURE_MACHINE_NAME');
```

When you are ready to import a menu, add this to a hook_update_N in your menu
Feature

```php
  $message = HookUpdateDeployTools\Menus::import('menu-machine-name');
  return $message;
```



###  <a name="import-menu-drush"></a>Import Menu (with Drush)

```
  drush site-deploy-import Menus menu_machine_name
```


-------------------------------------------

## <a name="modules"></a>Modules


### <a name="disable"></a>Disable a Module(s)

This disables dependent modules by default.  Passing it an optional  second
parameter of FALSE will cause it to not disable dependent modules (RISKY).

```php
  /**
   * Disabling modules:
   *  * module_name1
   *  * module_name2
   */
  function site_deploy_update_7004() {
    $modules = array(
      'module_name1',
      'module_name2',
    );
    $message = HookUpdateDeployTools\Modules::disable($modules);
    return $message;
  }
```

### <a name="disable-uninstall"></a>To Disable and Uninstall Module(s)

Any time you want to un-install a module(s) add a hook_update_N() to the .install
of your custom deployment module.

This disables and uninstalls dependent modules by default.  Passing it an
optional second parameter of FALSE will cause it to not touch dependent modules.
(RISKY)

```php
  /**
   * Disabling modules:
   *  * module_name1
   *  * module_name2
   */
  function site_deploy_update_7004() {
    $modules = array(
      'module_name1',
      'module_name2',
    );
    $message = HookUpdateDeployTools\Modules::disableAndUninstall($modules);
    return $message;
  }
```

### <a name="enable"></a>Enable Module(s)

Any time you want to enable a module(s) add a hook_update_N() to the .install
  of your custom deployment module.

This enables dependent modules by default.  Passing it an optional second
parameter of FALSE will cause it to not enable dependent modules (RISKY).

```php
  /**
   * Enabling modules:
   *  * module_name1
   *  * module_name2
   */
  function site_deploy_update_7004() {
    $modules = array(
      'module_name1',
      'module_name2',
    );
    $message = HookUpdateDeployTools\Modules::enable($modules);
    return $message;
  }
```



### <a name="uninstall"></a>Uninstall Module(s)

Any time you want to uninstall a module(s) add a hook_update_N() to the .install
of your custom deployment module.

This uninstalls dependent modules by default.  Passing it an
optional second parameter of FALSE will cause it to not touch dependent modules.
(RISKY)

```php
  /**
   * Disabling modules:
   *  * module_name1
   *  * module_name2
   */
  function site_deploy_update_7004() {
    $modules = array(
      'module_name1',
      'module_name2',
    );
    $message = HookUpdateDeployTools\Modules::uninstall($modules);
    return $message;
  }
```

-------------------------------------------

## <a name="nodes"></a>Nodes


### <a name="field-delete"></a>Delete Fields

Add something like this to a hook_update_N in your custom deploy module.install.

```php
  $message =  HookUpdateDeployTools\Fields::deleteInstance('field_name', 'bundle_name', 'entity_type');
  return $message;
}

```



###  <a name="export-node"></a>Export Node to a Text File (using drush)

You can use drush to export a node to a text file. The file will
be created in the module or Feature that you identified for use with Nodes here:
/admin/config/development/hook_update_deploy_tools
Enter the machine name of the Node Feature or let it default to your custom
deploy module. Though for true deployment, this value should be assigned
through a hook_update_N using

```php
  $message =  HookUpdateDeployTools\Settings::set('hook_update_deploy_tools_node_feature', 'NODE_FEATURE_MACHINE_NAME');
```

Within that module, add a directory 'node_source'. This is where your node
export files will be saved. The files will be named using the alias of the node
being exported. (node-alias.txt)

To export the node look up the node id of the node in the content UI.
Then go to your terminal and type

```
  drush site-deploy-export Node NID
```
Feedback from the drush command will tell you where the file has been created,
or if there were any issues.


###  <a name="import-node"></a>Import a Node

Nodes can be imported from a text file that was exported by the drush command.

When you are ready to import the node, add this to a hook_update_N():

```php
  $message = HookUpdateDeployTools\Nodes::import('node-path-alias');
  return $message;
```

or to import multiple nodes:

```php
  $node_aliases = array('node-alias-1', 'node-alias-2');
  $message = HookUpdateDeployTools\Nodes::import($node_aliases);
  return $message;
```



###  <a name="import-node-drush"></a>Import Node (with Drush)

```
  drush site-deploy-import Node node-alias

  -or-

  drush site-deploy-import Node node-export-filename
```

### <a name="update-node"></a>Update Node Values / Properties


Add this to a hook_update_N in your custom deploy module.install:

```php
  $message = HookUpdateDeployTools\Nodes::modifySimpleFieldValue($nid, $field_name, $new_value);
  return $message;
```

This will update simple fields (direct node properties) that have no cardinality
or language like:
comment, language, promote,  status, sticky, title, tnid, translate, uid



-------------------------------------------

## <a name="page-manager">Page Manager

###  <a name="export-page-manager-page"></a>Export a Page Manager page to a Text File (using drush)

You can use drush to export a Page Manager page to a text file. The file will
be created in the module or feature that you identified for use with Page
Manager here:
/admin/config/development/hook_update_deploy_tools
Look up the machine name of your Page in the Page Manager UI.
Then go to your terminal and type

```
drush site-deploy-export PageManager MACHINE_NAME_OF_PAGE
```
Feedback from the drush command will tell you where the file has been created,
or if there were any issues.

### <a name="import-page-manager-page"></a>Import a Page Manager Page

Page Manager pages can be imported from a text file that matches the standard
output of the the Page Manager module.
https://www.drupal.org/project/ctools

In order to import Page Manager pages on deployment, it is assumed/required that
you have a Feature that controls pages or a custom deploy module where the
import files can reside or a custom site_deploy module. Within that module, add a directory
'page_manager_source'. This is where you will place your page import files.
The files will be named using the machine name of the Page Manager page.
(page-machine-name-export.txt) You will also need to make Hook Update Deploy
Tools aware of this custom menu Feature by going here
/admin/config/development/hook_update_deploy_tools and entering the machine name
of the Page Manager Feature or let it default to your custom deploy module.
Though for true deployment, this value should be assigned
through a hook_update_N using

```php
  $message =  HookUpdateDeployTools\Settings::set('hook_update_deploy_tools_page_manager_feature', 'PAGE_MANAGER_FEATURE_MACHINE_NAME');
```

When you are ready to import a page, add this to a hook_update_N in your Page
Manager Feature:

```php
  $message = HookUpdateDeployTools\PageManager::import('page-machine-name');
  return $message;
```

or to do multiples:

```php
  $pages = array('page-machine-name', 'page-machine-name-other');
  $message = HookUpdateDeployTools\PageManager::import($pages);
  return $message;
```


###  <a name="import-pagemanager-drush"></a>Import PageManager page (with Drush)

```
  drush site-deploy-import PageManager page-machine-name
```


-------------------------------------------

## <a name="redirects">Redirects

###  <a name="import-redirects"></a>To Import a list of redirects Feature's .install

Redirects can be imported from a text file that is a CSV following the pattern
of old-path, newpath on each line of the file.  This requires the redirect module
be enabled. https://www.drupal.org/project/redirect

In order import redirects on deployment, it is assumed/required that you have a
Feature that controls redirects or a custom deploy module where the import files
can reside. Within that Feature, add a directory 'redirect_source'.
This is where you will place your Redirect import files.  The files will be named
(filename-export.txt) You will also need to make Hook Update Deploy
Tools aware of this custom menu Feature by going here
/admin/config/development/hook_update_deploy_tools and entering the machine name
of the Redirect Feature or let it default to your custom deploy module.
Though for true deployment, this value should be assigned
through a hook_update_N using

```php
  $message =  HookUpdateDeployTools\Settings::set('hook_update_deploy_tools_redirect_feature', 'REDIRECT_FEATURE_MACHINE_NAME');
```

When you are ready to import a list of Redirects, add this to a hook_update_N in
your redirect Feature

```php
  $message = HookUpdateDeployTools\Redirects::import('redirect-list-filename');
  return $message;
```

or to do multiples

```php
  $redirect_lists = array('redirect-list-filename', 'redirect-list-other-filename');
  $message = HookUpdateDeployTools\Redirects::import($redirect_lists);
  return $message;
```

*Bonus* There is an admin UI to import a list of redirects by visiting
/admin/config/search/redirect/hudt_import


###  <a name="import-redirects-drush"></a>Import Redirects (with Drush)

```
  drush site-deploy-import Redirects redirect-list-filename
```
-------------------------------------------

## <a name="rules">Rules


###  <a name="import-rule"></a>Import a Rule

Rules can be imported from a text file that matches the standard output of
the the Rules module.
https://www.drupal.org/project/rules

In order import Rules on deployment, it is assumed/required that you have a
Feature that controls rules or a custom deploy module where the import files
can reside. Within that Feature, add a directory 'rules_source'.
This is where you will place your Rule import files.  The files will be named
(rule-machine-name-export.txt) You will also need to make Hook Update Deploy
Tools aware of this custom menu Feature by going here
/admin/config/development/hook_update_deploy_tools and entering the machine name
of the Rules Feature or let it default to your custom deploy module.
Though for true deployment, this value should be assigned
through a hook_update_N using

```php
  $message =  HookUpdateDeployTools\Settings::set('hook_update_deploy_tools_rules_feature', 'RULES_FEATURE_MACHINE_NAME');
```

When you are ready to import a Rule, add this to a hook_update_N in your rules
Feature

```php
  $message = HookUpdateDeployTools\Rules::import('rules-machine-name');
  return $message;
```

or to do multiples

```php
  $rules = array('rules-machine-name', 'rules-machine-name-other');
  $message = HookUpdateDeployTools\Rules::import($rules);
  return $message;
```

###  <a name="import-rule-drush"></a>Import a Rule (with Drush)

```
  drush site-deploy-import Rule rules-machine-name
```


###  <a name="export-rule"></a>Export a Rule to a Text File(with drush)

You can use drush to export a rule to a text file. The file will be created in
the module or feature that you identified for use with Rules here
/admin/config/development/hook_update_deploy_tools
Look up the machine name of your Rule in the Rules UI.
Then go to your terminal and type

```
  drush site-deploy-export Rules MACHINE_NAME_OF_RULE
```
Feedback from the drush command will tell you where the file has been created,
or if there were any issues.


-------------------------------------------

## <a name="variables">Variables


### <a name="setting-variable"></a>Set or Change a Drupal Variable

Add something like this to a hook_update_N in your custom deploy module.install.

```php
  $message =  HookUpdateDeployTools\Settings::set('test_var_a', 'String A');
  $message .=  HookUpdateDeployTools\Settings::set('test_var_b', 'String B');
  return $message;

```

Variable values can be of any type supported by variable_set().
*Caution:* If your settings.php contains other files that are brought in by
include_once or require_once, they will not be used to check for overridden
values.  As a result you may get a false positive that your variable was
changed, when it really is overridden by an include in settings.php.

-------------------------------------------

## <a name="views">Views

### <a name="views-enable"></a>Enable a View

Add something like this to a hook_update_N in your custom deploy module.install
to enable some Views.

```php

  $views = array(
    'some_view_machine_name',
    'another_view_machine_name'
  );
  $message =  HookUpdateDeployTools\Views::enable('$views');

  return $message;

```


### <a name="views-delete"></a>Delete a View

Add something like this to a hook_update_N in your custom deploy module.install
to delete some Views.

```php

  $views = array(
    'some_view_machine_name',
    'another_view_machine_name'
  );
  $message =  HookUpdateDeployTools\Views::delete('$views');

  return $message;

```

### <a name="views-disable"></a>Disable a View

Add something like this to a hook_update_N in your custom deploy module.install
to disable some Views.

```php

  $views = array(
    'some_view_machine_name',
    'another_view_machine_name'
  );
  $message =  HookUpdateDeployTools\Views::disable('$views');

  return $message;

```

-------------------------------------------

## <a name="vocabularies">Vocabularies (Taxonomy)

### <a name="vocabulary-add"></a>Add a Vocabulary

Add something like this to a hook_update_N in your Feature or custom deploy module.install
to add a Vocabulary to Taxonomy.

```php

  $message =  HookUpdateDeployTools\Vocabularies::add('Vocabulary Name', 'vocab_machine_name', 'A text description for the Vocabulary.');
  return $message;

```

### <a name="vocabulary-delete"></a>Delete a Vocabulary

Add something like this to a hook_update_N in your Feature or custom deploy module.install
to delete a Vocabulary from Taxonomy.

```php

  $message =  HookUpdateDeployTools\Vocabularies::delete('vocab_machine_name');
  return $message;

```


### <a name="vocabulary-load"></a>Load a Vocabulary

Vocabularies can be loaded a few ways in a hook_update_N for use in other
custom processing.

By Human Name:

```php

  // $vocabulary_name = 'Human Name',
  // $strict TRUE or FALSE  (if TRUE, it will fail the update if the vocabulary is not found)."
  $vocabulary =  HookUpdateDeployTools\Vocabularies::loadByName($vocabulary_name, $strict);

```

By machine_name:

```php

  // $vocabulary_machine_name = 'the_vocabulary_machine_name',
  // $strict TRUE or FALSE  (if TRUE, it will fail the update if the vocabulary is not found)."
  $vocabulary =  HookUpdateDeployTools\Vocabularies::loadByMachineName($vocabulary_machine_name, $strict);

```


## <a name="terms">Terms (Taxonomy)

HookUpdateDeployTools\Terms::delete('Postsecondary Completion', 'Related Groups')
### <a name="vocabulary-terms-delete"></a>Delete a Term

To delete a Term you can put this in a hook_update_N():

```php
  $message = HookUpdateDeployTools\Terms::delete('Group Name', 'Vocabulary Name');

  example: $message = HookUpdateDeployTools\Terms::delete('Peach', 'Icecream Flavors');
```


### <a name="vocabulary-terms-export"></a>Export Terms to a txt file

You can export a term to a txt file for import to another environment with the
drush command:

```
  drush site-deploy-export Terms {TID}


  example: drush site-deploy-export Terms 4466
```

If needed, it could be scripted like this:

```php
  $message = HookUpdateDeployTools\Terms::export(TID);
```



### <a name="vocabulary-terms-import"></a>Import Terms from a txt file

You can import a term from a txt file for import to another environment with the
drush command:

```
  drush site-deploy-import Terms 'Vocabulary Name|Term Name'

  example: drush site-deploy-import Terms 'Icecream Flavors|Chocolate'
```

If needed, it could be placed in a hook_update_N():

```php
  $vocab_terms = array(
    // 'Vocabulary Name|Term Name',
    'Icecream Flavors|Chocolate',
    'Icecream Flavors|Vanilla',
    'Icecream Flavors|Peach',
  );
  $message = HookUpdateDeployTools\Terms::import($vocab_terms);
  return $message;
```


### <a name="vocabulary-terms-load"></a>Load a Term

A Term can be loaded in a hook_update_N for use in other
custom processing.

By Human Name:

```php
  // $term_name = "Term Human Name"
  // $vocabulary_name = 'Vocabulary Human Name'  OR 'vocabulary_machine_name'
  // $strict TRUE or FALSE  (If TRUE, it will fail the update if the Term is not found)."
  $term =  HookUpdateDeployTools\Vocabularies::loadByName($term_name, $vocabulary_name, $strict = FALSE);

```

-------------------------------------------


## <a name="hook-update"></a>Anatomy of a hook_update_N()

* <a href="https://api.drupal.org/api/drupal/modules%21system%21system.api.php/function/hook_update_N/7.x">hook_update_N definition on Drupal.org</a>
* Any module or Feature can have a .install file where hook_update_N functions live.
* Each successive hook_update_N increments the N to the next number.
* When a module is first enabled, none of the hook_update_N will run.  The
  system will record the highest N present at the time, and then only newly added
  Ns will run.


```php
  /**
   * Whatever is written here will be displayed in the list of updates that need
   * run, whenever drush updb is run.  Make it meaningful.
   */
   function site_deploy_update_7002(&$sandbox) {
     // Do some magic here.
     $message = "Message describing what was done. Be specific, be clear, because
     this is what appears as the feedback to the person who runs drush updb.";
     return $message;
   }
```

-------------------------------------------

## <a name="maintainers"></a>MAINTAINERS

* Steve Wirt (swirt) - https://www.drupal.org/u/swirt

The repository for this site is available on Drupal.org or
https://github.com/swirtSJW/hook-update-deploy-tools
