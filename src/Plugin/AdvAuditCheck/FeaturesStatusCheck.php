<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

use Drupal\adv_audit\AuditReason;
use Drupal\adv_audit\AuditResultResponseInterface;
use Drupal\adv_audit\Plugin\AdvAuditCheckBase;
use Drupal\adv_audit\Plugin\AdvAuditCheckInterface;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @AdvAuditCheck(
 *   id = "features_status_check",
 *   label = @Translation("Features status"),
 *   category = "core_and_modules",
 *   severity = "low",
 *   requirements = {
 *     "module": {
 *       "features",
 *     },
 *   },
 *   enabled = TRUE,
 * )
 */
class FeaturesStatusCheck extends AdvAuditCheckBase implements AdvAuditCheckInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id,  $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function perform() {
    if (FALSE) {
      return new AuditReason(
        $this->id(),
        AuditResultResponseInterface::RESULT_FAIL,
        $this->t('Module "feature" is not enabled.')
      );
    }
    return new AuditReason($this->id(), AuditResultResponseInterface::RESULT_PASS);
  }

}
