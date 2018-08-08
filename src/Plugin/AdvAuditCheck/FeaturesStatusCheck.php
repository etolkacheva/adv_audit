<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

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
 *     "module" = {"features"}
 *   },
 *   enabled = TRUE,
 * )
 */
class FeaturesStatusCheck extends AdvAuditCheckBase {

  public function perform() {
    //$this->checkRequirements();
    return new AuditReason($this->id(), AuditResultResponseInterface::RESULT_PASS);
  }

}
