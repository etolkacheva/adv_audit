<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

use Drupal\adv_audit\Plugin\AdvAuditCheckBase;
use Drupal\adv_audit\AuditReason;
use Drupal\adv_audit\AuditResultResponseInterface;

use Drupal\Core\Link;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Unsafe extensions Check plugin class.
 *
 * @AdvAuditCheck(
 *   id = "unsafe_extensions_check",
 *   label = @Translation("Unsafe extensions"),
 *   category = "security",
 *   requirements = {},
 *   enabled = true,
 *   severity = "high"
 * )
 */
class UnsafeExtensionsCheck extends AdvAuditCheckBase {

  /**
   * {@inheritdoc}
   */
  public function perform() {
    $fields = [];
    $params = [];
    $unsafe_ext = [
      'swf',
      'exe',
      'html',
      'htm',
      'php',
      'phtml',
      'py',
      'js',
      'vb',
      'vbe',
      'vbs',
    ];
    $status = AuditResultResponseInterface::RESULT_PASS;

    // Check field configuration entities.
    foreach (FieldConfig::loadMultiple() as $entity) {
      $extensions = $entity->getSetting('file_extensions');
      if ($extensions != NULL) {
        $extensions = explode(' ', $extensions);
        $unsafe_exts = array_intersect($extensions, $unsafe_ext);
        foreach ($unsafe_exts as $unsafe_extension) {
          $fields[$entity->id()][] = $unsafe_extension;
        }
      }
    }

    if (!empty($fields)) {
      $items = [];
      $status = AuditResultResponseInterface::RESULT_FAIL;

      foreach ($fields as $entity_id => $unsafe_extensions) {
        $entity = FieldConfig::load($entity_id);

        foreach ($unsafe_extensions as $extension) {
          $item = $this->t(
            'Review @type in <em>@field</em> field on @bundle',
            [
              '@type' => $extension,
              '@field' => $entity->label(),
              '@bundle' => $entity->getTargetBundle(),
            ]
          );

          // Try to get an edit url.
          try {
            $url_params = ['field_config' => $entity->id()];
            if ($entity->getTargetEntityTypeId() == 'node') {
              $url_params['node_type'] = $entity->getTargetBundle();
            }
            $items[] = Link::createFromRoute(
              $item,
              sprintf('entity.field_config.%s_field_edit_form', $entity->getTargetEntityTypeId()),
              $url_params
            );
          }
          catch (RouteNotFoundException $e) {
            $items[] = $item;
          }
        }
      }

      $params = ['fields' => $items];
    }

    return new AuditReason($this->id(), $status, NULL, $params);
  }

  /**
   * {@inheritdoc}
   */
  public function auditReportRender(AuditReason $reason, $type) {
    if ($type == AuditMessagesStorageInterface::MSG_TYPE_FAIL) {
      $arguments = $reason->getArguments();
      if (!empty($arguments['fields'])) {
        $build['unsafe_extensions'] = [
          '#theme' => 'item_list',
          '#title' => $this->t('Fields with unsafe extensions:'),
          '#list_type' => 'ul',
          '#items' => $arguments['fields'],
        ];
        return $build;
      }
    }

    return [];
  }

}
