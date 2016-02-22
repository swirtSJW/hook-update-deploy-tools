<?php
/**
 * @file
 * This file is used for all the hook_update_n() that will deploy the site.
 */

/**
 * Implements hook_install().
 */
function site_deploy_install() {
  // Provide friendly message to get started with permissions and configuration.
  $t = get_t();
  drupal_set_message($t('The site_deploy module has been successfully installed.  It has no configuration, just use its site_deploy.install to manage the site\'s deployments.', array()));
  $messages = array();
  $messages[] = $t("Running existing hook_update_N's for site_deploy.");
  // This is for initially standing up the production site which may come long
  // after the development site has been up and running.  Upon install it will
  // run through all the currently existing hook_update_N.
  // See http://dcycleproject.org/node/43
  for ($i = 7000; $i < 8000; $i++) {
    $existing_update_n = 'site_deploy_update_' . $i;
    $sandbox = array();
    if (function_exists($existing_update_n)) {
      $messages[$existing_update_n] = $existing_update_n($sandbox);
      $highest_run = $i;
    }
  }
  HookUpdateDeployTools\Message::make('Site_deploy_update_N from 7000 to !max have been run.', array('!max' => $highest_run), WATCHDOG_INFO);
  HookUpdateDeployTools\Message::varDumpToDrush($messages);
}

/**
 * Implements hook_disable().
 */
function site_deploy_disable() {
  $t = get_t();
  drupal_set_message($t('site_deploy has been disabled. No data or settings were altered.'));
}

/**
 * Implements hook_uninstall().
 */
function site_deploy_uninstall() {
  $t = get_t();
  drupal_set_message($t('site_deploy has been uninstalled. No data or settings were altered.'));
}

/*
 * Below here go all the hook_update_N() that will manage the deployment of
 * this site.
 * ///////////////////////////////////////////////////////////////////////////
 */

/**
 * Whatever it placed in this docblock gets displayed upon drush updb.
 */
function site_deploy_update_7000(&$sandbox) {
  // Whatever code is placed here gets run once upon 'drush updb' or update.php.
  return HookUpdateDeployTools\Message::make("Whatever is returned is displayed after this update runs.");
}
