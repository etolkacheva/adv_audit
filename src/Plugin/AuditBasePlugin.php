<?php

namespace Drupal\adv_audit\Plugin;

use Drupal\adv_audit\AuditReason;
use Drupal\adv_audit\Exception\RequirementsException;
use Drupal\adv_audit\AuditResultResponseInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for Advances audit check plugins.
 */
abstract class AuditBasePlugin extends PluginBase implements AuditPluginInterface, AuditResultInterface {

  use StringTranslationTrait;

  /**
   * The state service object.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $stateService;

  /**
   * The config factory.
   *
   * Subclasses should use the self::config() method, which may be overridden to
   * address specific needs when loading config, rather than this property
   * directly. See \Drupal\Core\Form\ConfigFormBase::config() for an example of
   * this.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The plugin config storage service.
   *
   * @var \Drupal\adv_audit\Service\AuditPluginConfigStorageService
   */
  protected $pluginSettingsStorage;

  /**
   * AuditBasePlugin constructor.
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginSettingsStorage = $this->container()
      ->get('adv_audit.plugin.config')
      ->setPluginId($plugin_id);
  }

  /**
   * Retrieves a configuration object.
   *
   * This is the main entry point to the configuration API. Calling.
   * @code $this->config('book.admin') @endcode will return a configuration
   * object in which the book module can store its administrative settings.
   *
   * @param string $name
   *   The name of the configuration object to retrieve. The name corresponds to
   *   a configuration file. For @code \Drupal::config('book.admin') @endcode,
   *   the config object returned will contain the contents of book.admin
   *   configuration file.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   A configuration object.
   */
  protected function config($name) {
    return $this->configFactory()->get($name);
  }

  /**
   * Gets the config factory for this form.
   *
   * When accessing configuration values, use $this->config(). Only use this
   * when the config factory needs to be manipulated directly.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   ConfigFactory.
   */
  protected function configFactory() {
    if (!$this->configFactory) {
      $this->configFactory = $this->container()->get('config.factory');
    }
    return $this->configFactory;
  }

  /**
   * Sets the config factory for this form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   *
   * @return $this
   */
  public function setConfigFactory(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    return $this;
  }

  /**
   * Resets the configuration factory.
   */
  public function resetConfigFactory() {
    $this->configFactory = NULL;
  }

  /**
   * Return category id from plugin definition.
   *
   * @return mixed
   *   The Plugin category ID.
   */
  public function getCategoryName() {
    return $this->pluginDefinition['category'];
  }

  /**
   * Get category label value from config storage.
   *
   * @return array|mixed|null
   *   Return category label value.
   */
  public function getCategoryLabel() {
    return $this->config('adv_audit.settings')->get('categories' . $this->getCategoryName() . '.label');
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->pluginDefinition['label'];
  }

  /**
   * Get plugin severity level from config.
   *
   * @return mixed
   *   The severity level of plugin.
   */
  public function getSeverityLevel() {
    return $this->pluginSettingsStorage->get('severity');
  }

  /**
   * Set plugin severity level.
   */
  public function setSeverityLevel($level) {
    $this->pluginSettingsStorage->set('severity', $level);
  }

  /**
   * Check requirements for audit plugins.
   */
  public function checkRequirements() {
    // Check whether the current test plugin
    // requirements are met or not.
    if (!($this instanceof RequirementsInterface)) {
      return;
    }

    foreach ($this->pluginDefinition['requirements'] as $requirement => $value) {
      switch ($requirement) {
        case 'module':
          $this->checkRequiredModules($value);
          break;

        case 'config':
          $this->checkRequiredConfigs($value);
          break;

        case 'library':
          $this->checkRequiredLibraries($value);
          break;

        default:
          $this->checkRequiredVersion($requirement, $value);
          break;
      }
    }
  }

  /**
   * Check required modules.
   *
   * @param array $module_list
   *   An array containing the list of modules to check.
   */
  private function checkRequiredModules(array $module_list) {
    $module_handler = $this->container()->get('module_handler');

    foreach ($module_list as $module) {
      if (!strpos($module, ':')) {
        $module_name = $module;
        $module_version = NULL;
      }
      else {
        list($module_name, $module_version) = explode(':', $module);
      }

      if (!$module_handler->moduleExists($module_name)) {
        throw new RequirementsException(
          $this->t('Module @module_name is not enabled.', ['@module_name' => $module_name]),
          $this->pluginDefinition['requirements']['module']
        );
      }

      if ($module_version) {
        // Check if can get current module version.
        $module_info = system_get_info('module', $module_name);
        if (empty($module_info['version'])) {
          throw new RequirementsException(
            $this->t('Version for module @module_name is not defined.', ['@module_name' => $module_name]),
            $this->pluginDefinition['requirements']['module']
          );
        }
        else {
          $this->checkRequiredVersion('module', $module_version, $module_info);
        }
      }
    }
  }

  /**
   * Check required configs.
   *
   * @param array $config_list
   *   An array containing the list of configss to check.
   */
  private function checkRequiredConfigs(array $config_list) {
    $config_factory = $this->container()->get('config.factory');

    foreach ($config_list as $config) {
      if (empty($config_factory->loadMultiple([$config]))) {
        throw new RequirementsException(
          $this->t('Config @config_name does not exist.', ['@config_name' => $config]),
          $this->pluginDefinition['requirements']['config']
        );
      }
    }
  }

  /**
   * Check required libraries.
   *
   * @param array $libraries_list
   *   An array containing the list of libraries to check.
   */
  private function checkRequiredLibraries(array $libraries_list) {
    $module_handler = $this->container()->get('module_handler');

    // Check if module Library API is enabled.
    if (!$module_handler->moduleExists('libraries')) {
      throw new RequirementsException(
        $this->t('Module Libraries API is not enabled.'),
        $this->pluginDefinition['requirements']['module']
      );
    }

    foreach ($libraries_list as $library) {
      if (empty(libraries_get_path($library))) {
        throw new RequirementsException(
          $this->t('Library @library_name does not exist.', ['@library_name' => $library]),
          $this->pluginDefinition['requirements']['library']
        );
      }
    }
  }

  /**
   * Check required version of module, php or Drupal core.
   *
   * @param string $type
   *   The type of requirements.
   * @param string $version
   *   Required version.
   * @param array $info
   *   Additional information.
   */
  private function checkRequiredVersion($type, $version, array $info = []) {

    // Build an array with requirements.
    $requirements = [$type => $this->pluginDefinition['requirements'][$type]];

    switch ($type) {
      case 'core':
        $current_version = \Drupal::VERSION;
        $name = 'Drupal core';
        break;

      case 'php':
        $current_version = phpversion();
        $name = 'PHP';
        break;

      default:
        $current_version = $info['version'];
        $version = "{$info['core']} - {$version}";
        $name = isset($info['name']) ? $info['name'] : NULL;
        $requirements = $this->pluginDefinition['requirements'][$type];
        break;
    }

    if (!version_compare($current_version, $version, '>=')) {
      throw new RequirementsException(
        $this->t('Current version of @name v@version is lower than required v@required_version.', [
          '@name' => $name,
          '@version' => $current_version,
          '@required_version' => $version,
        ]),
        $requirements
      );
    }
  }

  /**
   * Check what plugin is enabled.
   *
   * @return bool
   *   Return TRUE if plugin are enabled, otherwise FALSE.
   */
  public function isEnabled() {
    return $this->getStatus() == TRUE;
  }

  /**
   * Get status for plugin.
   *
   * @return bool
   *   Return status for plugin.
   */
  public function getStatus() {
    return $this->pluginSettingsStorage->get('enabled');
  }

  /**
   * Override plugin status from settings.
   *
   * @param bool $status
   *   New status for plugin.
   */
  public function setPluginStatus($status = TRUE) {
    $this->pluginSettingsStorage->set('enabled', $status);
  }

  /**
   * Get plugin weight value.
   *
   * @return mixed
   *   Weight value.
   */
  public function getWeight() {
    return $this->pluginSettingsStorage->get('weight', 0);
  }

  /**
   * Set weight value for plugin.
   *
   * @param int $weight
   *   Return module weight.
   */
  public function setWeight($weight) {
    $this->pluginSettingsStorage->set('weight', $weight);
  }

  /**
   * Returns the service container.
   *
   * This method is marked private to prevent sub-classes from retrieving
   * services from the container through it. Instead,
   * \Drupal\Core\DependencyInjection\ContainerInjectionInterface should be used
   * for injecting services.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The service container.
   */
  private function container() {
    return \Drupal::getContainer();
  }

  /**
   * {@inheritdoc}
   */
  public function success(array $issue_details = []): AuditReason {
    return new AuditReason($this->id(), AuditResultResponseInterface::RESULT_PASS, '', $issue_details);
  }

  /**
   * {@inheritdoc}
   */
  public function fail($msg, array $issue_details = []): AuditReason {
    return new AuditReason($this->id(), AuditResultResponseInterface::RESULT_FAIL, $msg, $issue_details);
  }

  /**
   * {@inheritdoc}
   */
  public function skip($msg): AuditReason {
    return new AuditReason($this->id(), AuditResultResponseInterface::RESULT_SKIP, $msg);
  }

  /**
   * Parses textarea lines into array.
   *
   * @param string $lines
   *   Textarea content.
   *
   * @return array
   *   The textarea lines.
   */
  protected function parseLines($lines) {
    $lines = explode("\n", $lines);

    if (!count($lines)) {
      return [];
    }
    $lines = array_filter($lines, 'trim');

    return str_replace("\r", "", $lines);
  }

  /**
   * Get settings for perform task.
   */
  protected function getSettings() {
    return $this->pluginSettingsStorage->get();
  }

}
