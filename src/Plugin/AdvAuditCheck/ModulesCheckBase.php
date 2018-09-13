<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

use Drupal\adv_audit\Plugin\AdvAuditCheckBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Base class for Advances audit modules updates check plugins.
 */
abstract class ModulesCheckBase extends AdvAuditCheckBase {

  /**
   * Store modules list.
   *
   * @var array
   *   Updates list.
   */
  protected $updates = [];

  /**
   * Number of updates.
   *
   * @var mixed
   *   Modules count.
   */
  protected $count;

  /**
   * Drupal\update\UpdateManagerInterface definition.
   *
   * @var \Drupal\update\UpdateManagerInterface
   */
  protected $updateManager;

  /**
   * Drupal\Core\Extension\ModuleHandlerInterface definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Defines whether to check For Security updates or not.
   *
   * @var bool
   */
  const CHECK_FOR_SECURITY_UPDATES = FALSE;

  /**
   * {@inheritdoc}
   */
  public function perform() {
    $projects = update_get_available(TRUE);
    $this->moduleHandler->loadInclude('update', 'inc', 'update.compare');
    $projects = update_calculate_project_data($projects);

    $manager = $this->updateManager;

    foreach ($projects as $project) {
      if ($project['status'] == $manager::CURRENT || $project['project_type'] != 'module') {
        continue;
      }

      if (!static::CHECK_FOR_SECURITY_UPDATES && !empty($project['security updates'])) {
        continue;
      }
      if (static::CHECK_FOR_SECURITY_UPDATES && empty($project['security updates'])) {
        continue;
      }

      $this->updates[] = [
        'label' => !empty($project['link']) ? Link::fromTextAndUrl($project['title'], Url::fromUri($project['link'])) : $project['title'],
        'current_v' => $project['existing_version'],
        'recommended_v' => $project['recommended'],
      ];
    }

    if (!empty($this->updates)) {
      $issues = [];
      foreach ($this->updates as $item) {
        $issues[] = [
          '@issue_title' => "Module's @label current version is @current_v. Recommended: @recommended_v",
          '@label' => $item['label'],
          '@current_v' => $item['current_v'],
          '@recommended_v' => $item['recommended_v'],
        ];
      }

      return $this->fail(NULL, ['issues' => $issues]);
    }

    return $this->success();
  }

}
