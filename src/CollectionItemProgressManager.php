<?php

namespace Drupal\d8_progress_entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Class CollectionItemProgressManager.
 */
class CollectionItemProgressManager {

  /**
   * @var \Drupal\d8_progress_entity\CollectionProgressManager
   */
  protected $collectionProgressManager;

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
   * CollectionItemProgressManager constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\d8_progress_entity\ProgressEntityManager $progress_entity_manager
   * @param \Drupal\d8_progress_entity\CollectionProgressManager $collection_progress_manager
   */
  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager, ProgressEntityManager $progress_entity_manager, CollectionProgressManager $collection_progress_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->progressEntityManager = $progress_entity_manager;
    $this->collectionProgressManager = $collection_progress_manager;
  }

  /**
   * Mark a Collection Item Progress entity as completed.
   *
   * Since the parent Collection Progress entity needs to track
   * the currently active Collection Item, this method also
   * fires off checks for updating that entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $collection_item_progress
   *   The Collection Item Progress entity being updated.
   * @param string $completion_method
   *   Should be one of 'manual' or 'viewed'.
   *
   * @return EntityInterface
   *   The updated $modified_collection_item_progress
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function markCompleted(EntityInterface $collection_item_progress, $completion_method) {

    // @TODO: Need to get initial vals so that we can revert on errors.

    if (!$collection_item_progress->hasField('field_collection_item_status')) {
      throw new EntityStorageException('Collection_item_progress entity type is missing field_collection_item_status');
    }

    $completed_term_id = $this->progressEntityManager
      ->getCollectionItemStatusTerm('completed');

    // If this item is already marked as completed, then we can assume
    // that this operation, and associated logic, has all been run already.
    if (!$collection_item_progress->get('field_collection_item_status')->isEmpty()
      && $collection_item_progress->get('field_collection_item_status') == $completed_term_id) {
      return $collection_item_progress;
    }

    // Make sure to grab this before making any modifications.
    // If we were in hook_entity_presave, this would be $entity->original.
    $orig_field_info = $this->getFallbackFieldData($collection_item_progress);

    try {
      $collection_item_progress
        ->set('field_collection_item_status', $completed_term_id)
        ->set('field_completion_method', $completion_method)
        ->save();
    }
    catch (\Exception $e) {
      throw new $e;
    }

    try {
      $this->updateVideoCompletion($collection_item_progress, TRUE);
    }
    catch (\Exception $e) {
      $this->revertCollectionItemProgress($collection_item_progress, $orig_field_info);
      throw new $e;
    }

    try {
      $collection_progress_id = $collection_item_progress
        ->get('field_parent_collection_progress')
        ->target_id;
      $collection_progress = $this->entityTypeManager
        ->getStorage('progress_entity')
        ->load($collection_progress_id);

      $collection_current_item_progress = $this->collectionProgressManager
        ->getCurrentCollectionItemProgress($collection_progress);

      // e.g.
      // Current Item: 2
      // User just checkmarked: 3
      // Updated current Item: unchanged
      if ($collection_item_progress->id() != $collection_current_item_progress->id()) {
        return $collection_item_progress;
      }

      // e.g.
      // Current Item: Last
      // User just checkmarked: Last
      // - If the user skipped an earlier item, set that as $next
      // - If all other items are completed then mark collection complete
      if (!$next_collection_item_progress = $this->collectionProgressManager
        ->getNextCollectionItemProgress($collection_progress)
        ?: $this->collectionProgressManager
          ->getFirstIncompleteCollectionItemProgress($collection_progress)) {

        $collection_progress
          ->set(
            'field_collection_date_completed',
            date('Y-m-d\TH:i:s', time())
          )->set('field_collection_current_item', [])
          ->save();
        return $collection_item_progress;
      }

      // e.g.
      // Current Item: 2
      // User just checkmarked: 2
      // Updated current Item: 3
      $this->setAsCurrent($next_collection_item_progress);
    }
    catch (\Exception $e) {
      $this->revertCollectionItemProgress($collection_item_progress, $orig_field_info);
      $this->updateVideoCompletion($collection_item_progress, FALSE);
      throw new $e;
    }

    try {
      $this->markInProgress($next_collection_item_progress);
      return $next_collection_item_progress;
    }
    catch (\Exception $e) {
      $this->setAsCurrent($collection_item_progress);
      $this->revertCollectionItemProgress($collection_item_progress, $orig_field_info);
      $this->updateVideoCompletion($collection_item_progress, FALSE);
      throw new $e;
    }
  }

  /**
   * Unmark a Collection Item Progress entity from completed.
   *
   * This needn't update the parent_collection_progress'
   * current item.
   *
   * @param \Drupal\Core\Entity\EntityInterface $collection_item_progress
   *   The Collection Item Progress entity being updated.
   */
  public function unmarkCompleted(EntityInterface $collection_item_progress) {
    try {
      $this->markInProgress($collection_item_progress);
    }
    catch (\Exception $e) {
      throw new $e;
    }

    try {
      $this->updateVideoCompletion($collection_item_progress, FALSE);
    }
    catch (\Exception $e) {
      /*
       * We don't need to go through all of the logic associate with
       * the markCompleted method, since markInProgresS() above does not
       * affect any other entities. Simply reverting that field should suffice.
       */
      $collection_item_progress
        ->set(
          'field_collection_item_status',
          $this->progressEntityManager
            ->getCollectionItemStatusTerm('completed')
        )->save();
      // We don't need to go through all of the logic associated with
      // markCompleted method, since markInProgress() above does not
      // affect
      throw new $e;
    }

    return $collection_item_progress;
  }

  /**
   * Mark the collection item progress as 'in-progress'
   *
   * @param \Drupal\Core\Entity\EntityInterface $collection_item_progress
   *
   * @return
   *   The updated collection item progress entity.
   */
  public function markInProgress(EntityInterface $collection_item_progress) {
    $collection_item_progress
      ->set(
        'field_collection_item_status',
        $this->progressEntityManager
          ->getCollectionItemStatusTerm('in-progress')
      )->save();

    return $collection_item_progress;
  }

  /**
   * Marks given collection item as current on it's parent.
   *
   * @param \Drupal\Core\Entity\EntityInterface $collection_item_progress
   *
   * @return EntityInterface
   *   The updated parent collection progress entity.
   */
  public function setAsCurrent(EntityInterface $collection_item_progress) {
    $parent_collection_progress = $collection_item_progress
      ->get('field_parent_collection_progress')
      ->entity;

    $collection_item_paragraph_ref = $collection_item_progress
      ->get('field_collection_item')
      ->getValue();

    $parent_collection_progress
      ->set(
        'field_collection_current_item',
        $collection_item_paragraph_ref
      )->save();

    return $parent_collection_progress;
  }

  /**
   * Get progress status of a collection item progress.
   *
   * @param EntityInterface $collection_item_progress
   *   A collection_item_progress entity.
   *
   * @return int
   *   The collection_item_progress entity's progress status key.
   */
  public function getStatus($collection_item_progress) {
    $status_term = $collection_item_progress
      ->get('field_collection_item_status')
      ->entity;

    return $status_term->get('field_collection_item_status_key')->value;
  }

  /**
   * Update the associated video_progress completed state.
   *
   * @param \Drupal\Core\Entity\EntityInterface $collection_item_progress
   *   The collection_item_progress being modified.
   * @param bool $completion_state
   *   Whether the video should be complete or incomplete.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function updateVideoCompletion(EntityInterface $collection_item_progress, bool $completion_state) {
    if ($completion_state === NULL) {
      throw new \InvalidArgumentException('You must specify $completion_state as TRUE or FALSE');
    }

    // The video
    $associated_content_nid = $collection_item_progress
      ->get('field_node_reference')
      ->target_id;

    $associated_content_node = $this->entityTypeManager
      ->getStorage('node')
      ->load($associated_content_nid);

    // Could also be a meditation, but those don't have
    // their own progress tracking if on bandcamp.
    if ($associated_content_node->bundle() === 'instructional_video') {
      $video_progress = $this->progressEntityManager->getActiveProgressEntity(
        $associated_content_nid,
        $collection_item_progress
      );
      if ($completion_state) {
        $video_progress->set(
          'field_video_date_completed',
          date('Y-m-d\TH:i:s', time())
        );
      }
      else {
        $video_progress->set(
          'field_video_date_completed',
          ''
        );
      }

      $video_progress->save();
    }
  }

  /**
   * Gets relevant field data from the collection_item_progress.
   *
   * Useful for temporarily storing the state prior to executing
   * various operations on other entities associated with
   * updating the state of the given item.
   *
   * @param \Drupal\Core\Entity\EntityInterface $collection_item_progress
   *   The collection_item_progress entity.
   *
   * @return array
   *   The fallback field info.
   */
  private function getFallbackFieldData(EntityInterface $collection_item_progress) {
    $orig_field_info = [];
    $orig_field_info['field_collection_item_status'] =
      $collection_item_progress->get('field_collection_item_status')->isEmpty()
        ? [] : $collection_item_progress->get('field_collection_item_status')->target_id;
    $orig_field_info['field_completion_method'] =
      $collection_item_progress->get('field_completion_method')->isEmpty()
        ? '' : $collection_item_progress->get('field_completion_method')->value;

    return $orig_field_info;
  }

  /**
   * Reverts a collection item to it's previous state.
   *
   * Just a wrapper for $this->progressEntityManager->update().
   * Provides not additional functionality but helps with
   * readability for this particular use case in our
   * error handling cases above.
   *
   * @param \Drupal\Core\Entity\EntityInterface $collection_item_progress
   *   The collection_item_progress entity.
   * @param $orig_field_data
   *   An array of field data, keyed by field_name
   */
  private function revertCollectionItemProgress(EntityInterface $collection_item_progress, $orig_field_data) {
    $this->progressEntityManager
      ->update($collection_item_progress, $orig_field_data);
  }

}
