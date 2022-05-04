<?php

namespace Drupal\d8_progress_entity\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Progress entity entities.
 */
class ProgressEntityViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    return $data;
  }

}
