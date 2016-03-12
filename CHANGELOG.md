hook_update_deploy_tools 7.x-1.x **-**-****
-----------------------------------------------
* Removed Exception throwing from Message::make().
* Add HudtException class that optionally handles logging on catch by calling
$e->logMessage;
* Added ExportInterface
* Added ImportInterface
* Abstracted Drush command to export anything that implements ExportInterface.
* Wrapped most big actions in try catch to better handle errors.
* Added CHANGELOG.md and populated it.


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
