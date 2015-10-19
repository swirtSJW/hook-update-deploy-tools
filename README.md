Hook Update Deploy Tools
============

CONTENTS OF THIS FILE
---------------------
 * Introduction
 * Features
 * Requirements
 * Installation
 * Configuration
 * Enabling modules
 * Disabling and Uninstalling modules
 * Reverting Features
 * Importing Menus
 * Updating Node Values
 * Updating Alias
 * Bonus Features
 * Maintainers

## Introduction
---------------

This module contains several HookUpdateDeployTools::methods to help manage programatically:

  * enabling / disabling / uninstalling modules
  * reverting of Features
  * importing (overwriting) menus
  * altering a path alias
  * updating node values (title, status, author, promoted...)

Drupal provides its own functions for enabling modules or reverting features,
however, most of them run silently without feedback so they are inappropriate
for use in hook_update_N because they do not provide any feedback as to what is
happening and whether it was a success or failure.  This module gives voice to
many of those functions.

## Features
-----------
Every method that can be used within a hook_update_N() includes detailed
feedback and logging of what was attempted and what the results were.  Updates
are Failed if the requested operation was not successful so that they can be run
again, or re-worked.


## Requirements
---------------

*  Reverting Features requires the Features module.
*  Importing menus requires the Menu Import module.
*  Altering a path requires the Pathauto module.


## Installation
---------------

* It is a good practice to add this module as a dependency to your custom
  deployment module.
* Enable this module

## Configuration
----------------

* Navigate to /admin/config/hook_update_deploy_tools and enter the name of the Feature that
  is controlling the menu.  (optional:  This is only needed if you will be using
  Hook Update Deploy Tools to import your menus programatically.

## To Enable a Module(s) in an .install
---------------------------------------


* Any time you want to enable a module(s) add a hook_update_N() to the .install
  of your custom deployment module.

````
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
````

## To Disable a Module(s) in an .install
---------------------------------------

````
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
````

## To Uninstall a Module(s) in an .install
---------------------------------------

````
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
````

## To Disable and Uninstall a Module(s) in an .install
---------------------------------------

````
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
````


## Revert a Feature(s) in a Feature's own .install
--------------------------------------------------

* Any time you want to revert a Feature(s) add a hook_update_N() to the .install
  of that Feature.

````
/**
 * Add some fields to content type Page
 */
function custom_basic_page_update_7002() {
  $features = array(
    'custom_basic_page',
  );
  $message = HookUpdateDeployTools\Features::revert($features);
  return $message;
}
````

In the odd situation where you need one feature to revert other features in
some particular order, you can add them to the $features array in order.

In the even more odd situation where you need to do some operation inbetween
reverting one feature an another, you can use this example to concat the
messages in to one.

````
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
````


## To Import a Menu in a Feature's .install
-------------------------------------------

Menus can be imported from a text file that matches the standard output of
the menu_import module.
https://www.drupal.org/project/menu_import

In order import menus on deployment, it is assumed/required that you have a
Feature that controls menus.  Within that Feature, add a directory 'menu_source'.
This is where you will place your menu import files.  The files will be named
the same way they would be if generated by menu_import
(menu-machine-name-export.txt) You will also need to make Hook Update Deploy
Tools aware of this custom menu Feature by going here
/admin/config/hook_update_deploy_tools and entering the machine name of the menu
Feature. Though for true deployment, this value should be assigned through an
hook_update_N using

````
variable_set('hook_update_deploy_tools_menu_feature', '<menu_feature_machine_name>');
````

When you are ready to import a menu, add this to a hook_update_N in your menu
Feature

````
$message = HookUpdateDeployTools\Menus::import('menu-bureaus-and-offices');
return $message;
````

## To update the value of a simple node field from a deploy's .install
-------------------------------------------
Add this to a hook_update_N in your custom deploy module.install.

````
$message = HookUpdateDeployTools\Nodes::modifySimpleFieldValue($nid, $field, $value);
return $message;
````

This will update simple fields (direct node properties) that have no cardinality
or language like:
comment, language, promote,  status, sticky, title, tnid, translate, uid


## To update an alias from a deploy's .install
-------------------------------------------
Add this to a hook_update_N in your custom deploy module.install.

````
$message = HookUpdateDeployTools\Nodes::modifyAlias($old_alias, $new_alias, $language);
return $message;
````

This will attempt to alter the alias if the old_alias exists.  The language has
to match the language of the original alias being modified (usually matches the
node that it is assigned to).


## BONUS
--------
The following modules are not required, but if you have them enabled they will
improve the experience:

  * Markdown - When the Markdown filter is enabled, display of the module help
    will be rendered with markdown.


## MAINTAINERS
--------------

* Steve Wirt (swirt) - https://drupal.org/user/138230