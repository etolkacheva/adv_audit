<?php

namespace Drupal\adv_audit\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * An interface define basic structure for Advance Audit checkpoints.
 *
 * @package Drupal\adv_audit
 */
interface AdvAuditCheckpointInterface extends PluginInspectionInterface {

  /**
   * Return information about plugin according annotation.
   *
   * @return mixed
   *   Associated array.
   */
  public function getInformation();

  /**
   * Return string with check status.
   *
   * @return string
   *   Possible values: 'success', 'fail', 'process'.
   */
  public function getProcessStatus();

  /**
   * Get check title.
   *
   * @return string
   *   Return checking name.
   */
  public function getTitle();

  /**
   * Get check category.
   *
   * @return string
   *   Return category name.
   */
  public function getCategory();

  /**
   * Set check status.
   *
   * @param string $status
   *   Possible values: 'success', 'fail', 'process'.
   */
  public function setProcessStatus($status);

  /**
   * Process checkpoint review.
   */
  public function process();

  /**
   * Return description of current checkpoint.
   */
  public function getDescription();

  /**
   * Allow to expand default plugin settings form with specific fields.
   *
   * @param array $form
   *   Settings form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return mixed
   *   Additional elements.
   */
  public function settingsForm(array &$form, FormStateInterface &$form_state);

  /**
   * Provide plugin description for help page.
   *
   * @return mixed
   *   Return text for help.
   */
  public function help();

}