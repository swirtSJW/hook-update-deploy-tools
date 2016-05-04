Hook Update Deploy Tools
============

CONTENTS OF THIS FILE
---------------------
 * <a href="#introduction">Introduction</a>
 * <a href="#requirements">Requirements</a>
 * <a href="#installation">Installation</a>
 * <a href="#configuration">Configuration</a>
 * <a href="#methods">Methods / Uses</a>
    * <a href="#installation">Create a custom site_deploy module</a>
    * <a href="#enable">Enabling modules</a>
    * <a href="#disable">Disabling and Uninstalling modules</a>
    * <a href="#revert">Reverting Features or Feature Components</a>
    * <a href="#field-delete">Deleting Fields</a>
    * <a href="#import-menu">Importing Menus</a>
    * <a href="#import-page-manager-page">Importing Page Manager page</a> and <a href="#export-page-manager-page">Exporting Page Manager page</a>
    * <a href="#import-redirects">Importing Redirects</a>
    * <a href="#import-rule">Importing Rules</a> and <a href="#export-rule">Exporting Rules</a>
    * <a href="#update-node">Updating Node Values</a>
    * <a href="#update-alias">Updating Alias</a>
    * <a href="#variables">Setting Drupal Variables</a>
    * <a href="#messages">Hook Update Messages</a>
    * <a href="#lookup-set">Check and Change Last Run hook_update_N</a>
 * <a href="#bonus">Bonus Features</a>
 * <a href="#maintainers">Maintainers</a>

-------------------------------------------

## <a name="introduction"></a>Introduction


This module contains several HookUpdateDeployTools::methods to help manage programatically:

  * enabling / disabling / uninstalling modules
  * reverting of Features
  * importing (overwriting) menus
  * exporting/importing Page Manager pages
  * exporting/importing Rules
  * altering a path alias
  * updating node values (title, status, author, promoted...)
  * setting Drupal variables

Drupal provides its own functions for enabling modules or reverting features,
however, most of them run silently without feedback so they are inappropriate
for use in hook_update_N because they do not provide any feedback as to what is
happening and whether it was a success or failure.  This module gives voice to
many of those functions.

Every method that can be used within a hook_update_N() includes detailed
feedback and logging of what was attempted and what the results were.  Updates
are Failed if the requested operation was not successful so that they can be run
again, or re-worked.

To create a custom deploy module for your site, run 'drush site-deploy-init'.
It will create a starter deploy module 'site_deploy' in modules/custom.
The module site_deploy's .install should be used to place your hook_update_N()
that will handle sitewide deployment.

*BONUS:* This module has a class autoloader, so there is no need to do any module_includes or require_onces.

-------------------------------------------

## <a name="requirements"></a>Requirements


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
* (optional) run 'site-deploy-init' to create site_deploy module in modules/custom.

-------------------------------------------

## <a name="configuration"></a>Configuration


* Navigate to /admin/config/development/hook_update_deploy_tools and enter the
  name of your site's custom deploy module.
* If you have other Feature(s) that would be a better location for import files
  for menus, Page Manager pages, or rules, add those as well.  This is only needed if you will be
  using Hook Update Deploy Tools to import them.

  -------------------------------------------

## <a name="methods"></a>Method / Uses


### <a name="enable"></a>To Enable a Module(s) in an .install



* Any time you want to enable a module(s) add a hook_update_N() to the .install
  of your custom deployment module.

```php
/**
 * Enabling modules:
 *  * module_name1
 *  * module_name2
 */
function my_custom_deploy_update_7004() {
  $modules = array(
    'module_name1',
    'module_name2',
  );
  $message = HookUpdateDeployTools\Modules::enable($modules);
  return $message;
}
```
-------------------------------------------

### <a name="disable"></a>To Disable a Module(s) in an .install

CAUTION: This surgically disables module(s) without regard to any dependents.
It may make your site unstable if not used wisely.
In most cases you should make sure you disable dependent modules too, but this
leaves the option of disabling a single module, doing something, then enabling
it again without disabling dependents.

```php
/**
 * Disabling modules:
 *  * module_name1
 *  * module_name2
 */
function my_custom_deploy_update_7004() {
  $modules = array(
    'module_name1',
    'module_name2',
  );
  $message = HookUpdateDeployTools\Modules::disable($modules);
  return $message;
}
```
-------------------------------------------

### <a name="uninstall"></a>To Uninstall a Module(s) in an .install

```php
/**
 * Disabling modules:
 *  * module_name1
 *  * module_name2
 */
function my_custom_deploy_update_7004() {
  $modules = array(
    'module_name1',
    'module_name2',
  );
  $message = HookUpdateDeployTools\Modules::uninstall($modules);
  return $message;
}
```

-------------------------------------------

### To Disable and Uninstall a Module(s) in an .install


```php
/**
 * Disabling modules:
 *  * module_name1
 *  * module_name2
 */
function my_custom_deploy_update_7004() {
  $modules = array(
    'module_name1',
    'module_name2',
  );
  $message = HookUpdateDeployTools\Modules::disableAndUninstall($modules);
  return $message;
}
```

-------------------------------------------

### <a name="revert"></a>Revert a Feature(s) in a Feature's own .install


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
reverting one feature an another, you can use this example to concatinate the
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

To revert only specific components of a Feature you can add the component name
to the request like this:

```php
  $features = array(
    'FEATURE_NAME.COMPONENT_NAME',
  );
  $message = HookUpdateDeployTools\Features::revert($features);
```

In rare cases where you need to force revert all components of a Feature even though
they are not shown as overridden, you can add the optional second argument to the
revert like this:

```php
  $features = array(
    'FEATURE_NAME',
  );
  $message = HookUpdateDeployTools\Features::revert($features, TRUE);
```

-------------------------------------------

### <a name="field-delete"></a>To delete a field from an .install

Add something like this to a hook_update_N in your custom deploy module.install.

```php
  $message =  HookUpdateDeployTools\Fields::deleteInstance('field_name', 'bundle_name', 'content_type');
  return $message;
}

```

-------------------------------------------

###  <a name="import-menu"></a>To Import a Menu in a Feature's .install

Menus can be imported from a text file that matches the standard output of
the menu_import module.
https://www.drupal.org/project/menu_import

In order import menus on deployment, it is assumed/required that you have a
Feature that controls menus.  Within that Feature, add a directory 'menu_source'.
This is where you will place your menu import files.  The files will be named
the same way they would be if generated by menu_import
(menu-machine-name-export.txt) You will also need to make Hook Update Deploy
Tools aware of this custom menu Feature by going here
/admin/config/development/hook_update_deploy_tools and entering the machine name
of the menu Feature. Though for true deployment, this value should be assigned
through a hook_update_N using

```php
  $message =  HookUpdateDeployTools\Settings::set('hook_update_deploy_tools_menu_feature', 'MENU_FEATURE_MACHINE_NAME');
```

When you are ready to import a menu, add this to a hook_update_N in your menu
Feature

```php
  $message = HookUpdateDeployTools\Menus::import('menu-bureaus-and-offices');
  return $message;
```

-------------------------------------------

###  <a name="import-page-manager-page"></a>To Import a Page Manager page in a Feature's .install

Page Manager pages can be imported from a text file that matches the standard
output of the the Page Manager module.
https://www.drupal.org/project/ctools

In order import Page Manager pages on deployment, it is assumed/required that
you have a Feature that controls pages or a custom deploy module where the
import files can reside. Within that module, add a directory
'page_manager_source'. This is where you will place your page import files.
The files will be named using the machine name of the Page Manager page.
(machine-name-export.txt) You will also need to make Hook Update Deploy
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

or to do multiples

```php
  $pages = array('page-machine-name', 'page-machine-name-other');
  $message = HookUpdateDeployTools\PageManager::import($pages);
  return $message;
```
###  <a name="export-page-manager-page"></a>To export a Page Manager page to a text file using drush

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

-------------------------------------------

###  <a name="import-redirects"></a>To Import a list of redirects Feature's .install

Redirects can be imported from a text file that is a CSV following the pattern
of old-path, newpath on each line of the file.
https://www.drupal.org/project/redirect

In order import Redirects on deployment, it is assumed/required that you have a
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

-------------------------------------------

###  <a name="import-rule"></a>To Import a Rule in a Feature's .install

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
###  <a name="export-rule"></a>To export a Rule to a text file using drush

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

### <a name="update-node"></a>To update the value of a simple node field from a deploy's .install


Add this to a hook_update_N in your custom deploy module.install.

```php
  $message = HookUpdateDeployTools\Nodes::modifySimpleFieldValue($nid, $field, $value);
  return $message;
```

This will update simple fields (direct node properties) that have no cardinality
or language like:
comment, language, promote,  status, sticky, title, tnid, translate, uid


-----------------------------------------------

### <a name="update-alias"></a>To update an alias from a deploy's .install

Add this to a hook_update_N in your custom deploy module.install.

```php
  $message = HookUpdateDeployTools\Nodes::modifyAlias($old_alias, $new_alias, $language);
  return $message;

```

This will attempt to alter the alias if the old_alias exists.  The language has
to match the language of the original alias being modified (usually matches the
node that it is assigned to).

-------------------------------------------

### <a name="variable"></a>To set a Drupal variable from an .install

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

### <a name="messages"></a>To output safe messages and watchdog log in hook_update

If you are doing something custom and want to provide messages to drush terminal
or drupal message and Watchdog log the output, make use of this method:

Add something like this to a hook_update_N in your custom module.install.

```php

  // Simple message example:
  $msg = 'I did something cool I'm telling you about.';
  $return =  HookUpdateDeployTools\Message::make($msg);

  // A more robust example:
  // Watchdog style message.
  $msg = 'I did something cool during update and created !count new nodes.';
  // Optional Watchdog style variables array. Arrays or Objects are welcome
  // variable values.
  $variables = array('!count' => count($some_array_i_built)));
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

-------------------------------------------

###<a name="lookup-set">Lookup or set last run hook_update_n</a>

In developing hook_updates_N's it is often necessary know what the last run
update is on a server.

```
drush site-deploy-n-lookup MODULE_NAME
```

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

## <a name="bonus"></a>BONUS

The following modules are not required, but if you have them enabled they will
improve the experience:

  * Markdown - When the Markdown filter is enabled, display of the module help
    will be rendered with markdown.

-------------------------------------------

## <a name="maintainers"></a>MAINTAINERS

* Steve Wirt (swirt) - https://www.drupal.org/u/swirt

The repository for this site is available on Drupal.org or
https://github.com/swirtSJW/hook-update-deploy-tools
