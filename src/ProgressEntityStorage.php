<?php

namespace Drupal\d8_progress_entity;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\d8_progress_entity\Entity\ProgressEntityInterface;

/**
 * Defines the storage handler class for Progress entity entities.
 *
 * This extends the base storage class, adding required special handling for
 * Progress entity entities.
 *
 * @ingroup d8_progress_entity
 */
class ProgressEntityStorage extends SqlContentEntityStorage implements ProgressEntityStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(ProgressEntityInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {progress_entity_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {progress_entity_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(ProgressEntityInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {progress_entity_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('progress_entity_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
