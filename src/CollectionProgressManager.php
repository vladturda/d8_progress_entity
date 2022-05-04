<?php

namespace Drupal\d8_progress_entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Class CollectionProgressManager.
 */
class CollectionProgressManager {

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\d8_progress_entity\ProgressEntityManager definition.
   *
   * @var \Drupal\d8_progress_entity\ProgressEntityManager
   */
  protected $progressEntityManager;

  /**
   * CollectionProgressManager constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\d8_progress_entity\ProgressEntityManager $progress_entity_manager
   */
  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager, ProgressEntityManager $progress_entity_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->progressEntityManager = $progress_entity_manager;
  }
  
  /**
   * Get the progress ent. associated with the Collection Progress's current item.
   *
   * @param EntityInterface $collection_progress
   *
   * @return EntityInterface|null
   */
  public function getCurrentCollectionItemProgress(EntityInterface $collection_progress) {
    try {
      $this->validateCollectionItemsField($collection_progress);
    }
    catch (\Exception $e) {
      throw new $e;
    }

    // The collection_item paragraph, not the associated progress entity.
    $current_collection_item_id = $collection_progress
      ->get('field_collection_current_item')
      ->target_id;

    return $this->progressEntityManager
      ->getCollectionItemProgressEntity($current_collection_item_id);
  }

  /**
   * Returns the first collection_item_progress on the entity.
   *
   * @param $collection_progress
   *   The collection_progress entity we are looking on.
   *
   * @return mixed
   */
  public function getFirstCollectionItemProgress($collection_progress) {
    try {
      $this->validateCollectionItemsField($collection_progress);
    }
    catch (\Exception $e) {
      throw new $e;
    }

    foreach ($collection_progress->get('field_collection_items') as $collection_progress_item) {
      return $collection_progress_item->entity;
    }

    throw new \LogicException('No First collection_item_progress found. This should have been caught by validateCollectionItemsField().');
  }

  /**
   * Gets the first item on the collection that isn't completed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $collection_progress
   *  The collection_progress entity.
   *
   * @return \Drupal\d8_progress_entity\Entity\ProgressEntityInterface
   *  The collection_item_progress entity.
   * @throws \InvalidArgumentException
   * @throws \Exception
   */
  public function getFirstIncompleteCollectionItemProgress(EntityInterface $collection_progress) {
    try {
      $this->validateCollectionItemsField($collection_progress);
    }
    catch (\Exception $e) {
      throw new $e;
    }

    foreach ($collection_progress->get('field_collection_items') as $collection_item_progress) {
      try {
        $collection_progress->get('field_collection_item_status');
      }
      catch (\InvalidArgumentException $e) {
        throw new $e;
      }

      $completed_term_id = $this->progressEntityManager
        ->getCollectionItemStatusTerm('completed');
      $collection_item_progress = $collection_item_progress->entity;

      if (!$collection_item_progress->get('field_collection_item_status')->isEmpty()
        && $collection_item_progress->get('field_collection_item_status')->target_id == $completed_term_id) {
        continue;
      }

      return $collection_item_progress;
    }

    // If we hit here then it means that all of the collection_progress'
    // items have been completed.
    return NULL;
  }

  /**
   * Gets the next collection item progress entity
   *
   * If the collection_progress entity's "current item" is the
   * final collection_item_progress on the collection then this
   * will return null. If you want to then look back over the
   * collection progress then you should use
   * $this->getFirstIncompleteCollectionItemProgress()
   *
   * @param EntityInterface $collection_progress
   *
   * @return \Drupal\d8_progress_entity\Entity\ProgressEntityInterface|null
   *   If null, current_item was last on the collection.
   */
  public function getNextCollectionItemProgress(EntityInterface $collection_progress) {
    try {
      $this->validateCollectionItemsField($collection_progress);
    }
    catch (\Exception $e) {
      throw new $e;
    }

    // If no current item, get the first one as current and bail.
    if ($collection_progress->get('field_collection_current_item')->isEmpty()) {
      return $this->getFirstCollectionItemProgress($collection_progress);
    }

    $current_item_paragraph_id = $collection_progress
      ->get('field_collection_current_item')
      ->target_id;

    $current_collection_item_progress = $this->progressEntityManager->getCollectionItemProgressEntity($current_item_paragraph_id);
    $current_collection_item_progress_id = $current_collection_item_progress->id();

    $found_current_item = FALSE;
    foreach ($collection_progress->get('field_collection_items') as $collection_progress_item) {
      if ($collection_progress_item->target_id == $current_collection_item_progress_id) {
        $found_current_item = TRUE;
        continue;
      }

      if (!$found_current_item) {
        continue;
      }

      // If we have $found_current_item, then the previous item was
      // the Current Item, so this one is the Next Item
      return $collection_progress_item->entity;
    }

    // The collection_progress' was the final one,
    // so there is no "next" item to return.
    return NULL;
  }

  /**
   * Make sure there are collection_item_progress referenced at all.
   *
   * @param \Drupal\Core\Entity\EntityInterface $collection_progress
   *
   * @return bool
   * @throws \Exception
   */
  private function validateCollectionItemsField(EntityInterface $collection_progress) {
    /** @var $collection_progress \Drupal\d8_progress_entity\Entity\ProgressEntityInterface */
    try {
      $field_collection_items = $collection_progress->get('field_collection_items');
    }
    catch (\InvalidArgumentException $e) {
      throw new $e;
    }

    if ($field_collection_items->isEmpty()) {
      throw new \Exception("No collection_item_progress entities found on collection_progress {$collection_progress->id()}");
    }

    return TRUE;
  }

}
