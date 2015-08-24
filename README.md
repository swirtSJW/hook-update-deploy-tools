Deploy Tools
============

CONTENTS OF THIS FILE
---------------------
 * Introduction
 * Features
 * Requirements
 * Installation
 * Configuration
 * Enabling modules
 * Reverting Features
 * Importing Menus
 * Bonus Features
 * Maintainers

## Introduction
---------------

This module contains several DeployTools::methods to help manage programatically: 

  * enabling of modules
  * reverting of Features
  * importing (overwriting) menus

## Features
-----------


## Requirements
---------------

*  Reverting Features requires the Features module.
*  Importing menus requires the Menu Import module.


## Installation
---------------

* It is a good practice to add this module as a dependency to your custom 
  deployment module.
* Enable this module 

## Configuration
----------------

* Navigate to /admin/config/deploy_tools and enter the name of the Feature that
  is controlling the menu.  (optional:  This is only needed if you will using 
  Deploy Tools to import your menus programatically.

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
  $message = DeployTools\Modules::enable($modules);
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
  $message = DeployTools\Features::revert('$features');
  return $message;
}
````


## To Import a Menu in a Feature's .install
-------------------------------------------

  *  [Not worked out yet.]


## BONUS
--------
The following modules are not required, but if you have them enabled they will
improve the experience:

  * Devel - When Devel is enabled, output from codit_debug will use kpr() rather
    than print_r() to display arrays or objects with the help of krumo.
  * Markdown - When the Markdown filter is enabled, display of the module help
    for any of the Codit modules and submodules will be rendered with markdown.


## MAINTAINERS
--------------

* Steve Wirt (swirt) - https://drupal.org/user/138230