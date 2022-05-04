<?php

namespace Drupal\d8_progress_entity;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface ProgressEntityStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Progress entity revision IDs for a specific Progress entity.
   *
   * @param \Drupal\d8_progress_entity\Entity\ProgressEntityInterface $entity
   *   The Progress entity entity.
   *
   * @return int[]
   *   Progress entity revision IDs (in ascending order).
   */
  public function revisionIds(ProgressEntityInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Progress entity author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Progress entity revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\d8_progress_entity\Entity\ProgressEntityInterface $entity
   *   The Progress entity entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(ProgressEntityInterface $entity);

  /**
   * Unsets the language for all Progress entity with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
