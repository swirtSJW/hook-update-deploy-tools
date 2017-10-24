hook_update_deploy_tools 7.x-1.x **-**-****
-----------------------------------------------
* [New items go at the top of this list.]


hook_update_deploy_tools 7.x-1.31 October 24, 2017
-----------------------------------------------
* Issue #2911444: Fix Installing the Markdown filter module causes an error'
  https://www.drupal.org/node/2911444
* Issue #2909365: Blocks Variable typo in return message.
  https://www.drupal.org/node/2909365
* Issue #2897703: Documentation fix modifyAlias.

hook_update_deploy_tools 7.x-1.30 June 07, 2017
-----------------------------------------------
* Issue #2884507: Modules: disableAndUninstall sometimes fails. Added cache clear.
  https://www.drupal.org/node/2884507
* Issue #2884498: Modules: Make disable and uninstall methods include dependents by default
  https://www.drupal.org/node/2884498


hook_update_deploy_tools 7.x-1.29 June 02, 2017
-----------------------------------------------
* Issue #2883507 by kducharm: Method to delete a View
  https://www.drupal.org/node/2883507
* Issue #2730457 by JayDarnell: Empty lines in redirect import should be ignored.
  https://www.drupal.org/node/2730457

hook_update_deploy_tools 7.x-1.27 Mar 24, 2017
-----------------------------------------------
* Issue #2863711: Blocks: Add Update block properties method.
* https://www.drupal.org/node/2863711
* Issue #2863690: Blocks: Add enable and disable methods.
  https://www.drupal.org/node/2863690
* Issue #2863074: Terms: Unused vars referenced creating e-notice.
  https://www.drupal.org/node/2863074


hook_update_deploy_tools 7.x-1.25 Mar 20, 2017
-----------------------------------------------
* Issue #2862338: bugfix Terms on import if an update, the wrong term may get updated.
  https://www.drupal.org/node/2862338
* Issue #2862337: bugfix Terms: incorrectly set wrong parent if no parent.
  https://www.drupal.org/node/2862337


hook_update_deploy_tools 7.x-1.24 Mar 17, 2017
-----------------------------------------------
* Issue #2861596: Create method to delete a Vocabulary Term
  https://www.drupal.org/node/2861596
* Issue #2603886: Import and export taxonomy term from a txt file'
  https://www.drupal.org/node/2603886
* Add Vocabulary load methods.


hook_update_deploy_tools 7.x-1.23 Mar 10, 2017
-----------------------------------------------
* Issue #2859596: Make Documentation more approachable.
* Issue #2859388: Using Nodes::modifySimpleFieldValue the message is not outputting the node id.
* Issue #2858631: OG: Assign members of one Group to another Group (co-opt).
* Issue #2859243: OG: Add user id's to a Group.
* Issue #2859240: OG: Load a Group by name, with strict checking.
* Issue #2859333: Add Check::isGroup to strictly check for something being an Organic Group.


hook_update_deploy_tools 7.x-1.22 Mar 3, 2017
-----------------------------------------------
* Issue #2857507: Add Vocabularies::add method.
* Issue #2857625: Add Vocabularies::delete method.

hook_update_deploy_tools 7.x-1.21 Jan 12, 2017
-----------------------------------------------
* Issue #2838516: Make Views check clear the view info cache.

hook_update_deploy_tools 7.x-1.20 Nov 22, 2016
-----------------------------------------------
* Issue #2829468: properly evaluate dpm() prior to using in debug
* Issue #2689145: by extralooping, Pryrios: require_once path incorrect on some
  environments

hook_update_deploy_tools 7.x-1.19 Sept 2, 2016
-----------------------------------------------
* Removed validation for drush site-deploy-init to reduce false negatives.
* Add better handling of workbench moderation states on Nodes import.

hook_update_deploy_tools 7.x-1.18 Aug 23, 2016
-----------------------------------------------
* Issue #2776575: PHP Fatal error:  Call to undefined function posix_getpwuid()
  when running drush site-deploy-init
* Issue #2788765: drush import command to use importInterface.


hook_update_deploy_tools 7.x-1.17 Aug 23, 2016
-----------------------------------------------
* Issue #2788045: Node import rollback/delete if invalid
* Issue #2597269: Import nodes from node export files
* Issue #2774773: Remove @file tag docblock from all the namespaced .php files.
* Remove unused variables in Redirect class.
* Add support for importing redirects from csv text files.
  https://www.drupal.org/node/2717339


hook_update_deploy_tools 7.x-1.16 May 6, 2016
-----------------------------------------------
* Add method to enable/disable a View.
  https://www.drupal.org/node/2720315
* Remove unused variables in Redirect class.


hook_update_deploy_tools 7.x-1.15 May 4, 2016
-----------------------------------------------
* Add support for importing redirects from csv text files.
  https://www.drupal.org/node/2717339


hook_update_deploy_tools 7.x-1.14 April 11, 2016
-----------------------------------------------
* Add support for Force reverting Features and reverting specific components.
https://www.drupal.org/node/2647148
* Improved reporting of Features revert operations.
* Add call to features_include(TRUE) to pick up newly added files.
  https://www.drupal.org/node/2667268
* Add check for page_manager_load_task_handlers to canExport.


hook_update_deploy_tools 7.x-1.13 April 6, 2016
-----------------------------------------------
* Fix too narrow look for Page manager handlers on export.
* Fix summary message variables in Features::revert.
* README cleanup.


hook_update_deploy_tools 7.x-1.12 March 31, 2016
-----------------------------------------------
* Added PageManager page export and import.
https://www.drupal.org/node/2619258
* Adjusted HudtInternal::getSummary() to report operation.
* Adjusted HudtInternal::getStoragePath to throw an exception.



hook_update_deploy_tools 7.x-1.11 March 14, 2016
------------------------------------------------
* Fixed bug that did not allow hook_update_deploy_tools to be uninstalled.
https://www.drupal.org/node/2687161
* Removed Exception throwing from Message::make().
* Add HudtException class that optionally handles logging on catch by calling
$e->logMessage;
* Added ExportInterface
* Added ImportInterface
* Abstracted Drush command to export anything that implements ExportInterface.
* Wrapped most big actions in try catch to better handle errors.
* Added CHANGELOG.md and populated it.
* Added drush commands to get the last hook_update_n and to set the last N.
    drush site-deploy-n-lookup MODULE_NAME
    drush site-deploy-n-set MODULE_NAME  (reduces the number of the last update)
    drush site-deploy-n-set MODULE_NAME 7034 (sets the last run N to 7034)


hook_update_deploy_tools 7.x-1.10 March 3, 2016
-----------------------------------------------
* Added support for exporting Rules to files, using drush.
https://www.drupal.org/node/2679052
* Added support for importing Rules from files.
* Minor Documentation cleanup.
* Refactored how internal processes are handled.

http://web-dev.wirt.us/info/drupal-7-drush/d7-export-and-import-rules-hook-update-deploy-tools



hook_update_deploy_tools 7.x-1.9 February 22, 2016
--------------------------------------------------
* Update to Message class to better identify the source of the message.
* Add Drush command 'side-deploy-init' to generate a custom site_deploy module
for use on the site.
'drush site-deploy-init' will create the module site-deploy in modules/custom.
'drush site-deploy-init "../features" will create the module site-deploy in
modules/features



hook_update_deploy_tools 8.x-1.0-unstable1 February 6, 2016
-----------------------------------------------------
* Unstable branch for D8.
* Not stable.
* Full or Errors
* DO NOT USE.  For continued development purposes only.



hook_update_deploy_tools 7.x-1.8 November 12, 2015
--------------------------------------------------
* Issue #2613918: Setting a variable to array value issue
https://www.drupal.org/node/2613918
* Added check for more accurate object comparison.
* Improved accuracy of basic check for success by making
it use an exact match.
* Added a set of test update hooks to make future testing more complete.
* Issue #2613728: Move settings menu location
https://www.drupal.org/node/2613728
* Moved the location from admin/config/hook_update_deploy_tools
to admin/config/development/hook_update_deploy_tools



hook_update_deploy_tools 7.x-1.7 November 5, 2015
-------------------------------------------------
* Issue #2609056: Repair setting variable to integer 0 bug.
https://www.drupal.org/node/2609056
* Repaired the bug that causes a variable set request to set integer 0
incorrectly identified that the variable was already set and
did not exist.
* Added varaible value type to the output messages for greater clarity.
* Removed a misplaced bracket from the README example.



hook_update_deploy_tools 7.x-1.6 November 1, 2015
-------------------------------------------------
* Adding the ability to remove field instances from entity bundles.
* Response to Feature Request https://www.drupal.org/node/2603126
* Explains how to use it and demonstrates the results:
http://web-dev.wirt.us/info/drupal-7/hook-update-deploy-tools-delete-fields



hook_update_deploy_tools 7.x-1.5 October 22, 2015
-------------------------------------------------
* Adding method to handle Drupal variable setting in a hook_update_N. This method
gives feedback on what the variable value was, and what it is now.
Fails the update if the new value was not saved.



hook_update_deploy_tools 7.x-1.4 October 19, 2015
-------------------------------------------------
* Improved checking of cases for existing aliases.
* HookUpdateDeployTools\Nodes::modifyAlias($old_alias, $new_alias, $language);
now makes the following decisions

| original | new    | action                     | Assumption                                            |
| !exist   | !exist | Fail update.               | Bad directive.                                        |
| !exist   | exist  | Do nothing. Pass update.   | Change already made.                                  |
| exist    | !exist | Change alias. Pass update. | Conditions clear for alias change.                    |
| exist    | exist  | Fail update                | Alias conflict.                                       |
| same     =  same  | Do nothing, Pass update    | Silly request, but nothing to do.  No reason to fail. |




hook_update_deploy_tools 7.x-1.3 October 14, 2015
-------------------------------------------------
* New messaging system to make sure older versions of drush still perform output.
* Paths: a path can now be modified easily through a hook_update_N.
* Node: a node can have its simple values altered through a hook_update_N.


hook_update_deploy_tools 7.x-1.2 October 7, 2015
------------------------------------------------
* Adds support for module disable, uninstall and disableAndUninstall.
* Updates to readme / help.


hook_update_deploy_tools 7.x-1.1  September 16, 2015
----------------------------------------------------
?


hook_update_deploy_tools 7.x-1.0 8-27-2015
------------------------------------------
Intial release.
