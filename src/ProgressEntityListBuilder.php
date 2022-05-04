<?php

namespace Drupal\d8_progress_entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Progress entity entities.
 *
 * @ingroup d8_progress_entity
 */
class ProgressEntityListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Progress entity ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\d8_progress_entity\Entity\ProgressEntity $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.progress_entity.edit_form',
      ['progress_entity' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
