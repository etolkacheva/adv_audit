<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

use Drupal\adv_audit\AuditReason;
use Drupal\adv_audit\AuditResultResponseInterface;
use Drupal\adv_audit\Plugin\AdvAuditCheckBase;
use Drupal\adv_audit\Plugin\AdvAuditCheckInterface;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\features\FeaturesAssigner;
use Drupal\features\FeaturesManager;
use Drupal\features\FeaturesManagerInterface;
use Drupal\features\Package;
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

  protected $featuresManager;

  protected $featuresAssigner;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FeaturesManager $features_manager, FeaturesAssigner $features_assigner) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->featuresManager = $features_manager;
    $this->featuresAssigner = $features_assigner;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('features.manager'),
      $container->get('features_assigner')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function perform() {
    $current_bundle = $this->featuresAssigner->getBundle();
    $this->featuresAssigner->assignConfigPackages();
    $packages = $this->featuresManager->getPackages();
    $config_collection = $this->featuresManager->getConfigCollection();
    $this->addUnpackaged($packages, $config_collection);
    $packages = $this->featuresManager->filterPackages($packages, $current_bundle->getMachineName());
    foreach ($packages as $package) {
      if (!$package->getStatus() == FeaturesManagerInterface::STATUS_INSTALLED && $this->featuresManager->detectOverrides($package, TRUE)) {
        return new AuditReason($this->id(), AuditResultResponseInterface::RESULT_FAIL, '', ['testKey' => 'testValue']);
      }
    }
    return new AuditReason($this->id(), AuditResultResponseInterface::RESULT_PASS);
  }

  public function auditReportRender(AuditReason $reason, $type) {

    if ($type == AuditResultResponseInterface::RESULT_FAIL) {
      $argc = $reason->getArguments();
      return ['#markup' => 'My HTML result. '];
    }
    return [];

  }

  /**
   * Adds a pseudo-package to display unpackaged configuration.
   *
   * @param array $packages
   *   An array of package names.
   * @param \Drupal\features\ConfigurationItem[] $config_collection
   *   A collection of configuration.
   */
  protected function addUnpackaged(array &$packages, array $config_collection) {
    $packages['unpackaged'] = new Package('unpackaged', [
      'name' => $this->t('Unpackaged'),
      'description' => $this->t('Configuration that has not been added to any package.'),
      'config' => [],
      'status' => FeaturesManagerInterface::STATUS_NO_EXPORT,
      'version' => '',
    ]);
    foreach ($config_collection as $item_name => $item) {
      if (!$item->getPackage() && !$item->isExcluded() && !$item->isProviderExcluded()) {
        $packages['unpackaged']->appendConfig($item_name);
      }
    }
  }

}
