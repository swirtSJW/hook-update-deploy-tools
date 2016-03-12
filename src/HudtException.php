<?php

/**
 * @file
 * Declares HudtException class.
 */

namespace HookUpdateDeployTools;

/**
 * Public method for importing Rules.
 */
class HudtException extends \DrupalUpdateException {
  public $watchdogMessage;
  public $vars = array();
  public $watchdogCode;
  public $logIt;

  /**
   * Exception with optional watchdog logging on calling $e->logMessage().
   *
   * @param string $watchdog_message
   *   Message ready for sending to t().
   * @param array $vars
   *   Variables for use in string replacement in t().
   * @param int $watchdog_code
   *   Watchdog error codes for proper watchdog logging.
   * @param bool $log_it
   *   TRUE (default) to make a watchdog entry when $e->logMessage() is called.
   *   FALSE prevents watchdog entry when $e->logMessage() is called.
   */
  public function __construct($watchdog_message, $vars, $watchdog_code, $log_it = TRUE) {
    $t = get_t();
    // Assign properties from params.
    $this->watchdogMessage = $watchdog_message;
    $this->vars = (array) $vars;
    $this->watchdogCode = $watchdog_code;
    $this->logIt = (!empty($log_it)) ? TRUE : FALSE;

    $this->message = $t($watchdog_message, $vars);
  }

  /**
   * Logs the message to Watchdog.
   *
   * @param string $pre
   *   A string to prepend to the message.
   * @param string $post
   *   A string to append to the message.
   *
   * @return string
   *   The return from Message::make() or getMessage() if logging was skipped.
   */
  public function logMessage($pre = '', $post = '') {
    if ($this->logIt) {
      // Log it to watchdog.
      $full_message = "{$pre}{$this->watchdogMessage}{$post}";
      return Message::make($full_message, $this->vars, $this->watchdogCode, 1);
    }
    return $this->getMessage();
  }
}
