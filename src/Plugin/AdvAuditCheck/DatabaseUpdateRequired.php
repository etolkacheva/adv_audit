<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

use Drupal\adv_audit\Plugin\AdvAuditCheckBase;
use Drupal\adv_audit\Plugin\AdvAuditCheckInterface;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @AdvAuditCheck(
 *   id = "database_update_required",
 *   label = @Translation("No database updates required"),
 *   category = "core_and_modules",
 *   severity = "critical",
 *   requirements = {},
 *   enabled = TRUE,
 * )
 */
class DatabaseUpdateRequired extends AdvAuditCheckBase implements AdvAuditCheckInterface, ContainerFactoryPluginInterface {

  protected $systemManager;

  /**
   * Constructs Configuration Manager Status.
   */
  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    SystemManager $system_manager

  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->systemManager = $system_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('system.manager')

    );
  }

  /**
   * {@inheritdoc}
   */
  public function perform() {
    $requirements = $this->systemManager->listRequirements();
    if (isset($requirements['update']['severity'])) {
      $this->setProcessStatus($this::FAIL);
    }

    /*if ($is_overriden) {
      return new AuditReason(
        $this->id(),
        AuditResultResponseInterface::RESULT_FAIL,
        $this->t('There are differences between configurations stored in database and files.')
      );
    }
    else {
      return new AuditReason($this->id(), AuditResultResponseInterface::RESULT_PASS);
    }*/
  }

}

