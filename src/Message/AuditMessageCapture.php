<?php

namespace Drupal\adv_audit\Message;

/**
 * Allows capturing messages rather than displaying them directly.
 */
class AuditMessageCapture implements AuditMessageInterface {

  /**
   * Array of recorded messages.
   *
   * @var array
   */
  protected $messages = [];

  /**
   * {@inheritdoc}
   */
  public function display($message, $type = 'status') {
    $this->messages[] = $message;
  }

  /**
   * Clears out any captured messages.
   */
  public function clear() {
    $this->messages = [];
  }

  /**
   * Returns any captured messages.
   *
   * @return array
   *   The captured messages.
   */
  public function getMessages() {
    return $this->messages;
  }

}
