<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

use Drupal\adv_audit\Plugin\AdvAuditCheckBase;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\UrlHelper;
use GuzzleHttp\Client;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks if access to admin pages is forbidden for anonymous users.
 *
 * @AdvAuditCheck(
 *   id = "admin_pages_access",
 *   label = @Translation("Admin pages access check"),
 *   category = "security",
 *   requirements = {},
 *   enabled = true,
 *   severity = "high"
 * )
 */
class AdminPagesAccessCheck extends AdvAuditCheckBase implements ContainerFactoryPluginInterface, PluginFormInterface {

  /**
   * Predefined URLs list.
   */
  private const URLS = [
    '/node',
    '/node/add',
    '/taxonomy/term/{entity:taxonomy_term}',
    '/admin/structure/taxonomy/add',
    '/admin/structure/taxonomy/manage/{entity:taxonomy_vocabulary}/add',
    '/admin/people/create',
  ];

  /**
   * The state service object.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The Entity type manegr.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Returns the default http client.
   *
   * @var \GuzzleHttp\Client
   *   A guzzle http client instance.
   */
  protected $httpClient;

  /**
   * Request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state, EntityTypeManagerInterface $etm, Client $client, Request $request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
    $this->entityTypeManager = $etm;
    $this->httpClient = $client;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('http_client'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Form constructor.
   *
   * Plugin forms are embedded in other forms. In order to know where the plugin
   * form is located in the parent form, #parents and #array_parents must be
   * known, but these are not available during the initial build phase. In order
   * to have these properties available when building the plugin form's
   * elements, let this method return a form element that has a #process
   * callback and build the rest of the form in the callback. By the time the
   * callback is executed, the element's #parents and #array_parents properties
   * will have been set by the form API. For more documentation on #parents and
   * #array_parents, see \Drupal\Core\Render\Element\FormElement.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   *
   * @return array
   *   The form structure.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $current_urls = $this->state->get($this->buildStateConfigKey());
    if (!isset($current_urls)) {
      $current_urls = implode("\n", self::URLS);
    }
    $form['urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('URLs for access checking'),
      '#description' => t(
        'Place one URL(relative) per line as relative with preceding slash, i.e /path/to/page.
         <br/>
         <br />Entity id placeholder(one per URL) can be used in format {entity:<entity_type>}, i.e. /taxonomy/term/{entity:taxonomy_term}',
        ['@urls' => implode(', ', self::URLS)]
      ),
      '#default_value' => $current_urls,
    ];

    return $form;
  }

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $urls = $this->parseLines($form_state->getValue(['urls']));

    if (empty($urls)) {
      // Nothing to do here.
      $error_msg = $this->t('At least one URL is required. Otherwise disable the plugin.');
      $form_state->setError($form['urls'], $error_msg);
      return;
    }

    foreach ($urls as $url) {
      $url = $this->replaceEntityPlaceholder($url);
      if (!UrlHelper::isValid($url) || substr($url, 0, 1) !== '/') {
        $error_msg = $this->t('Please provide valid URLs, one per line. Each URL should be given as relative with preceding slash.');
        $form_state->setError($form['urls'], $url);
        $form_state->setError($form['urls'], $error_msg);
        break;
      }
    }
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through
   *   \Drupal\Core\Form\SubformState::createForSubform().
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValue(['urls']);
    $this->state->set($this->buildStateConfigKey(), $value);
  }

  /**
   * Process checkpoint review.
   */
  public function perform() {
    $params = [];

    $user_urls = $this->parseLines($this->state->get($this->buildStateConfigKey()));
    $urls = empty($user_urls) ? self::URLS : $user_urls;

    foreach ($urls as $url) {
      $url = $this->replaceEntityPlaceholder($url);

      try {
        $response = $this->httpClient->get($this->request->getSchemeAndHttpHost() . $url);
        if ($response->getStatusCode() == 200) {
          // Secure check fail: the page should not be accessible.
          $params['failed_urls'][] = $url;
        }
      }
      catch (\Exception $e) {
        $code = $e->getCode();
        if (!empty($code) && in_array($code, [401, 403, 404])) {
          // It's good code.
          $params['failed_urls'][] = $url;
          continue;
        }
        if ($code > 500) {
          // Log pages that produce server error.
          $params['failed_urls'][] = $url;
        }
      }
    }

    if (!empty($params['failed_urls'])) {
      $issues = $this->getIssues($params['failed_urls']);
      return $this->fail(NULL, ['issues' => $issues]);
    }
    return $this->success();
  }

  /**
   * Build key string for access to stored value from config.
   *
   * @return string
   *   The generated key.
   */
  private function buildStateConfigKey() {
    return 'adv_audit.plugin.' . $this->id() . '.config.urls';
  }

  /**
   * Replace entity placeholder.
   *
   * @param string $url
   *   URL to be processed.
   *
   * @return string
   *   Processed URL.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  private function replaceEntityPlaceholder($url) {
    try {
      preg_match_all('/{entity:(.*?)}/', $url, $entity_type);
      if (empty($entity_type[1][0])) {
        return $url;
      }

      $storage = $this->entityTypeManager->getStorage($entity_type[1][0]);
      $query = $storage->getQuery();
      $query->range(0, 1);
      $res = $query->execute();

      $entity_id = count($res) ? reset($res) : NULL;
      if (empty($entity_id)) {
        return $url;
      }

      return preg_replace('/{entity:.*?}/', $entity_id, $url);
    }
    catch (\Exception $e) {
      return $url;
    }

  }

  /**
   * Get list of issues.
   *
   * @param array $params
   *   List failed URLs.
   *
   * @return array
   *   List of issues.
   */
  private function getIssues(array $params) {
    $issues = [];
    foreach ($params as $failed_url) {
      $issues[$failed_url] = [
        '@issue_title' => 'Url "@url" should not be available for anonymous user',
        '@url' => $failed_url,
      ];
    }

    return $issues;
  }

}
