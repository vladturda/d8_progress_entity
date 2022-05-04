<?php

namespace Drupal\d8_progress_entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\d8_progress_entity\Entity\ProgressEntityInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;

/**
 * Class ProgressEntityManager.
 *
 * @see Drupal\votingapiVoteResultFunctionManager for example cache handlers
 * @see https://drupal-up.com/blog/how-create-drupal-8-service-module
 * @todo Add cache handling
 * @package Drupal\d8_progress_entity
 */
class ProgressEntityManager {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * The progress entity storage.
   *
   * @var \Drupal\d8_progress_entity\ProgressEntityStorageInterface
   */
  protected $progressEntityStorage;

  /**
   * Media Storage.
   *
   * @var \Drupal\media_entity\MediaStorageInterface
   */
  protected $mediaEntityStorage;

  /**
   * Taxonomy Storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $taxonomyTermStorage;

  /**
   * Constructs a new ProgressEntityManager object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->progressEntityStorage = $entity_type_manager->getStorage('progress_entity');
    $this->mediaEntityStorage = $entity_type_manager->getStorage('media');
    $this->taxonomyTermStorage = $entity_type_manager->getStorage('taxonomy_term');
  }

  /**
   * Generate a video_progress entity for the given entity.
   *
   * @param int $video_nid
   *   The nid of the video whose progress is being tracked.
   * @param bool $save
   *   Whether or not to save the entity.
   * @param int $collection_item_progress_id
   *   (Optional)
   *   If the video is related to a collection, the collection nid.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Initial video progress entity for user's first viewing.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createVideoProgress($video_nid, $save = TRUE, $collection_item_progress_id = NULL) {
    $progress_entity_parameters = [
      'type' => 'video_progress',
      'name' => "video_progress : {$this->currentUser->getDisplayName()} : $video_nid",
      'user_id' => $this->currentUser->id(),
      'field_video_viewer' => $this->currentUser->id(),
      'field_video_viewing_type' => 'standalone',
      'field_video_node' => $video_nid,
      'field_video_playhead_position' => 0,
      'field_video_progress' => 0,
      'field_video_percentage_viewed' => 0,
      'field_video_date_viewed' => time(),
    ];

    if ($collection_item_progress_id) {
      $progress_entity_parameters['field_video_viewing_type'] = 'collection';
      $progress_entity_parameters['field_video_collection_item_prog'] = $collection_item_progress_id;
    }

    $progress_entity = $this->progressEntityStorage
      ->create($progress_entity_parameters);

    if ($save) {
      $progress_entity->save();
    }

    return $progress_entity;
  }

  /**
   * Generate a collection_progress entity for the given entity.
   *
   * @param int $collection_id
   *   The nid of the collection whose progress is being tracked.
   * @param bool $determine_items
   *   Whether or not we should automatically generate/populate the associated
   *   collection_item_progress entities.
   * @param array $collection_items
   *   (Optional)
   *   An array of collection_item_progress entities to be referenced on the
   *   collection_progress entity.
   * @param EntityInterface $current_item_paragraph
   *   A collection_item paragraph that should be set as the current_item
   *   in lieu of the first entry in $collection_items.
   *   (NOTE: handling for this does not exist).
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Progress entity, indicatinting user's initial engagement with collection.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createCollectionProgress(int $collection_id, $determine_items = TRUE, array $collection_items = [], EntityInterface $current_item_paragraph = NULL) {
    $progress_entity_parameters = [
      'type' => 'collection_progress',
      'name' => "collection_progress : {$this->currentUser->getDisplayName()} : $collection_id",
      'user_id' => $this->currentUser->id(),
      'field_collection_node' => $collection_id,
      'field_collection_items' => [],
    ];


    // Create the Collection Progress entity before the
    // Collection Item Progress entities so that we can
    // provide its ID for their parent ref fields.
    $collection_progress_entity = $this->progressEntityStorage
      ->create($progress_entity_parameters);
    $collection_progress_entity->save();

    if (!$determine_items) {
      return $collection_progress_entity;
    }

    if (!$collection_items = $this->generateCollectionProgressItems($collection_id, $collection_progress_entity->id())) {
      return $collection_progress_entity;
    }

    $collection_progress_entity->set('field_collection_items', $collection_items);

    if (!$current_item_paragraph) {
      $first_item = reset($collection_items);
      $collection_item_progress_id = $first_item->get('field_collection_item')->getValue()[0];
      $collection_progress_entity->set('field_collection_current_item', $collection_item_progress_id);
    }

    $collection_progress_entity->save();


    if ($current_item_paragraph) {
      throw new \Exception('$current_item_paragraph param is untested.');
      // Verify this works if needed, not very tested.
      //      $progress_entity_parameters['field_collection_current_item'] = [
      //        'target_id' => $current_item_paragraph->id(),
      //        'target_revision_id' => $current_item_paragraph->getRevisionId(),
      //      ];
    }

    return $collection_progress_entity;
  }

  /**
   * Generate a collection_progress entity for the given entity.
   *
   * @param EntityReferenceRevisionsItem|EntityInterface $collection_item_paragraph
   *   The collection_item paragraph entity that this progress item relates to.
   * @param int $collection_progress_entity_id
   *   The ID of the parent Collection Progress Entity onto which
   *   this item is being attached.
   * @param bool $save
   *   Whether or not to save the entity.
   * @param int $node_reference_id
   *   The nid of the node (likely a video) that this item is related to.
   *   If not set manually, this will default to field_node reference
   *   on the collection_item paragraph being referenced.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Progress entity, indicatinting user's initial engagement with collection.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createCollectionItemProgress($collection_item_paragraph, $collection_progress_entity_id, $save = TRUE, $node_reference_id = NULL) {
    $collection_item_ref = [
      'target_id' => $collection_item_paragraph->entity->id(),
      'target_revision_id' => $collection_item_paragraph->entity->getRevisionId(),
    ];

    $progress_entity_parameters = [
      'type' => 'collection_item_progress',
      'name' => "collection_item_progress : {$this->currentUser->getDisplayName()} : {$collection_item_ref['target_id']}",
      'user_id' => $this->currentUser->id(),
      'field_collection_item' => [$collection_item_ref],
      'field_node_reference' => ($node_reference_id) ? $node_reference_id : $collection_item_paragraph->entity->get('field_node_reference')->entity->id(),
      'field_collection_item_status' => $this->getCollectionItemStatusTerm('initial'),
      'field_parent_collection_progress' => ['target_id' => $collection_progress_entity_id],
    ];

    $progress_entity = $this->progressEntityStorage
      ->create($progress_entity_parameters);

    if ($save) {
      $progress_entity->save();
    }

    return $progress_entity;
  }

  /**
   * Update a Progress Entity with the given data.
   *
   * @param EntityInterface $progress_entity
   *   Progress entity to be updated.
   * @param array $properties
   *   Array of data to be updated in the format $field_name => $updated_value.
   *
   * @return mixed
   *   Updated progress entity
   */
  public function update(EntityInterface $progress_entity, array $properties) {
    // Note that date fields cannot be passed directly with time()
    // @see https://gorannikolovski.com/blog/set-date-field-programmatically
    foreach ($properties as $key => $value) {
      $progress_entity->set($key, $value);
    }

    // Probably want to try/catch.
    $progress_entity->save();

    return $progress_entity;
  }

  /**
   * Determines values for field_video_progress and field_video_percentage.
   *
   * Based on a progress entity and the current playhead position.
   *
   * @param \Drupal\d8_progress_entity\Entity\ProgressEntityInterface $progress_entity
   *   Progress enitity being checked.
   * @param float $playhead_position
   *   The current playhead position in seconds.
   *
   * @return array
   *   An array of progress info.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function determineProgressPercentages(ProgressEntityInterface $progress_entity, $playhead_position) {
    /** @var EntityInterfce */
    $video_node = $this->getReferencedNonprogressEntity($progress_entity);

    if ($video_node->get('field_media_video')->isEmpty()) {
      return NULL;
    }

    $mid = $video_node->get('field_media_video')->target_id;

    /** @var EntityInterfce */
    $video_media = $this->mediaEntityStorage->load($mid);

    if ($video_media->get('field_media_duration')->isEmpty()) {
      return NULL;
    }

    $video_duration = $video_media->get('field_media_duration')->value;
    $playhead_percentage = round($playhead_position / $video_duration * 100);

    $progress_percentages = $progress_entity->get('field_video_progress')->value;
    $progress_percentages_arr = explode(',', $progress_percentages);

    if (!in_array($playhead_percentage, $progress_percentages_arr)) {
      $progress_percentages_arr[] = $playhead_percentage;
    }

    return [
      'field_video_progress' => implode(',', $progress_percentages_arr),
      'field_video_percentage_viewed' => count($progress_percentages_arr) - 1,
    ];
  }

  /**
   * Get all progress entities that reference the provided entity_id.
   *
   * @param int $referenced_entity_id_primary
   *   The entity_id (probably nid) for which we are checking progress.
   * @param EntityInterface $referenced_entity_secondary
   *   The secondary reference entity. Currently only used for relating
   *   video_progress with specific collection_item_progress.
   * @param bool $active_only
   *   If true, will only load progress entities that aren't
   *   marked as completed.
   * @param int|bool $uid
   *   NULL (default): Falls back to current user
   *   INT: Uses the given int $uid for the query
   *   FALSE: Discard UID condition, return results for all users.
   *
   * @return array
   *   An array of progress entities referencing a particular item.
   */
  public function getReferencingProgressEntities($referenced_entity_id_primary, $referenced_entity_secondary = NULL, $active_only = FALSE, $uid = NULL) {
    $node = $this->nodeStorage->load($referenced_entity_id_primary);

    // Will be used to limit our entity query.
    // To be set $field_name => $field_value.
    $field_conditions = [];

    // Should be refactored to a property/utility class.
    switch ($node->bundle()) {
      case 'collection';
        $primary_reference_field_name = 'field_collection_node';
        $completion_field_name = 'field_collection_date_completed';
        break;

      case 'instructional_video':
        $primary_reference_field_name = 'field_video_node';
        $completion_field_name = 'field_video_date_completed';

        $field_conditions['field_video_viewing_type'] = 'standalone';

        if (!$referenced_entity_secondary
          || $referenced_entity_secondary->bundle() !== 'collection_item_progress') {
          break;
        }

        $field_conditions['field_video_viewing_type'] = 'collection';
        $field_conditions['field_video_collection_item_prog'] = $referenced_entity_secondary->id();

        break;

      default:
        // We want to explicitly handle our bundles,
        // so any other cases return NULL.
        return NULL;
    }

    // Set condition for primary node reference field.
    $field_conditions[$primary_reference_field_name] = $referenced_entity_id_primary;

    // Init our query object.
    $query = $this->progressEntityStorage->getQuery();

    // Set field conditions from arr.
    foreach ($field_conditions as $field_name => $field_value) {
      $query->condition($field_name, $field_value);
    }

    // If false, no UID is queried.
    if ($uid !== FALSE) {
      // By default ($uid = NULL) we use the current user's UID.
      $uid = $uid ? $uid : $this->currentUser->id();
      $query->condition('user_id', $uid);
    }

    if ($active_only) {
      $query->notExists($completion_field_name)->range(0, 1);
    }

    $progress_entity_ids = $query->execute();
    $progress_entities = [];

    foreach ($progress_entity_ids as $id) {
      $progress_entities[$id] = $this->progressEntityStorage->load($id);
    }

    return $progress_entities;
  }

  /**
   * Get the time remaining for the tracked entity.
   *
   * @param \Drupal\Core\Entity\ProgressEntityInterface $progress_entity
   *   The progress entity.
   *
   * @return string
   *   String represention of remaining time.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTimeRemainingString(ProgressEntityInterface $progress_entity) {
    switch ($progress_entity->bundle()) {
      case 'video_progress':
        $percent_viewed = $progress_entity
          ->get('field_video_percentage_viewed')
          ->value;
        $playhead_position = $progress_entity
          ->get('field_video_playhead_position')
          ->value;

        if ($percent_viewed == 0) {
          // Avoid divide by zero error.
          return '';
        }

        $total_duration_secs = $playhead_position / $percent_viewed;
        $percent_remaining = 100 - $percent_viewed;
        $time_remaining_secs = $total_duration_secs * $percent_remaining;
        $time_remaining_mins = round($time_remaining_secs / 60);
        $unit = ($time_remaining_mins == 1) ? "min" : "mins";
        return "$time_remaining_mins $unit left";

      case 'collection_progress':
        // @todo: this should probably be tracked in a field an updated as needed.
        // This is a somewhat expensive operation so ideally we could avoid it.
        if (!$completion_arr = $this->getTimeRemainingCollectionProgressData($progress_entity)) {
          return '';
        }

        $remaining = $completion_arr['items_total'] - $completion_arr['items_completed'];
        $unit = $remaining == 1 ? 'session' : 'sessions';
        return "$remaining $unit left";
    }

    return '';
  }

  /**
   * Returns the active progress entity for the provided ID.
   *
   * @param int $referenced_entity_id_primary
   *   The ID of the node/entity whose progress is being tracked.
   * @param EntityInterface $referenced_entity_secondary
   *   A secondary reference entity. In this case of a "standalone"
   *   Video Progress this would be left null. In the case of a "collection"
   *   Video Progress, this would be the collection_item_progress entity.
   * @param int $uid
   *   The uid whose progress entity we are fetching. This only
   *   needs to be set if we don't want to use the current user.
   *
   * @return \Drupal\d8_progress_entity\Entity\ProgressEntityInterface|null
   *   Get active progress entity if it exists.
   */
  public function getActiveProgressEntity($referenced_entity_id_primary, EntityInterface $referenced_entity_secondary = NULL, $uid = NULL) {
    $progress_entities = $this->getReferencingProgressEntities($referenced_entity_id_primary, $referenced_entity_secondary, TRUE, $uid);

    return reset($progress_entities);
  }

  /**
   * Get the entity (node) referenced by the Progress.
   *
   * @param \Drupal\d8_progress_entity\Entity\ProgressEntityInterface $progress_entity
   *   The progress_entity that we need to get the node enitity from.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The referenced entity,
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getReferencedNonprogressEntity(ProgressEntityInterface $progress_entity) {
    // Should be refactored to a property/utility class.
    switch ($progress_entity->bundle()) {
      case 'collection_progress';
        $reference_field_name = 'field_collection_node';
        break;

      case 'video_progress';
        $reference_field_name = 'field_video_node';
        break;

      default:
        // Failure, invalid/unhandled bundle.
        return NULL;
    }

    // No entity referenced by progress entity.
    if ($progress_entity->get($reference_field_name)->isEmpty()) {
      return NULL;
    }

    $referenced_entity_id = $progress_entity->get($reference_field_name)->target_id;

    return $this->nodeStorage->load($referenced_entity_id);;
  }

  /**
   * Get the current user's progress entity for a given collection_item.
   *
   * @param int $collection_item_paragraph_id
   *   ID of colllection item.
   *
   * @return EntityInterface
   *   The progress entity for a user and collection.
   */
  public function getCollectionItemProgressEntity($collection_item_paragraph_id) {
    $progress_query = $this->progressEntityStorage->getQuery();
    $progress_query->condition('type', 'collection_item_progress');
    $progress_query->condition('user_id', $this->currentUser->id());
    $progress_query->condition('field_collection_item', $collection_item_paragraph_id);

    $progress_id = array_values($progress_query->execute())[0];

    return $this->progressEntityStorage->load($progress_id);
  }


  /**
   * Get the completion percentage for the given progress_entity.
   *
   * @param \Drupal\d8_progress_entity\Entity\ProgressEntityInterface $progress_entity
   *   The progress entity.
   *
   * @return int
   *   (0 - 100) range integer representing
   */
  public function getPercentCompleted(ProgressEntityInterface $progress_entity) {
    switch ($progress_entity->bundle()) {
      case 'video_progress':
        if (!$progress_entity->hasField('field_video_percentage_viewed')
          || $progress_entity->get('field_video_percentage_viewed')->isEmpty()) {
          return 0;
        }

        return $progress_entity->get('field_video_percentage_viewed')->value;

      case 'collection_progress':
        if (!$collection_data_arr = $this->getTimeRemainingCollectionProgressData($progress_entity)) {
          return 0;
        }

        if ($collection_data_arr['items_total'] == 0) {
          // Probably won't happen but avoid divide by zero.
          return 0;
        }

        return round($collection_data_arr['items_completed'] / $collection_data_arr['items_total'] * 100);
    }

    // No match, easier to read than in switch default.
    return 0;
  }

  /**
   * Get the percentage completion for the given tracked entity_id.
   *
   * @param int $referenced_entity_id
   *   The ID of the referenced entity being tracked.
   *
   * @return int
   *   (0 - 100) range percentage.
   */
  public function getPercentCompletedFromTrackedEntity($referenced_entity_id) {
    if (!$progress_entity = $this->getActiveProgressEntity($referenced_entity_id)) {
      return 0;
    }

    return $this->getPercentCompleted($progress_entity);
  }

  /**
   * Determine correct collection item status term.
   *
   * @param string $key
   *   The key of the the vocabulary term.
   *
   * @return string
   *   The term_id
   */
  public function getCollectionItemStatusTerm($key) {
    if (!$term_query_results = $this->taxonomyTermStorage->getQuery()
      ->condition('field_collection_item_status_key', $key)
      ->execute()) {
      return '';
    }

    return reset($term_query_results);
  }

  /**
   * Generate an array of collection_item_progress entities for a collection.
   *
   * @param int $collection_id
   *   The nid of the collection whose items we are generating
   *   progress entities for.
   * @param int $collection_progress_entity_id
   *   The ID of the Collection Progress Entity on which these
   *   items are being attached.
   *
   * @return array
   *   The generated collection_item_progress entities.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function generateCollectionProgressItems($collection_id, $collection_progress_entity_id) {
    if (!$collection_progress_entity_id) {
      throw new \Exception('Parent entity ID not provided.');
    }

    /** @var EntityInterface */
    $collection = $this->nodeStorage->load($collection_id);

    $collection_item_set = $collection->get('field_schedule_collections');
    $collection_item_progress_entities = [];

    foreach ($collection_item_set as $collection_set_key => $collection_set) {
      $collection_items = $collection_set->entity->get('field_collection_items');
      foreach ($collection_items as $collection_item_key => $collection_item) {
        $collection_item_progress_entities[] = $this->createCollectionItemProgress($collection_item, $collection_progress_entity_id);
      }
    }

    return $collection_item_progress_entities;
  }

  /**
   * Get the remaining collection items.
   *
   * @param \Drupal\d8_progress_entity\Entity\ProgressEntityInterface $progress_entity
   *   The prgress entitty of interest.
   *
   * @return array
   *   Total items, Completed items.
   */
  private function getTimeRemainingCollectionProgressData(ProgressEntityInterface $progress_entity) {
    // @todo: this should probably be tracked in a field an updated as needed.
    // This is a somewhat expensive operation so ideally we could avoid it.
    if ($progress_entity->get('field_collection_items')->isEmpty()) {
      return [];
    }

    if (!$completed_term_id = $this->getCollectionItemStatusTerm('completed')) {
      return [];
    }

    $collection_progress_items = $progress_entity
      ->get('field_collection_items')
      ->getValue();

    $total = 0;
    $completed = 0;

    foreach ($collection_progress_items as $item_ref) {
      $total++;
      $item = $this->progressEntityStorage
        ->load($item_ref['target_id']);

      // If the field name changes to something that makes more sense.
      if (!$item->hasField('field_collection_item_status')
        || $item->get('field_collection_item_status')->isEmpty()
        || $item->get('field_collection_item_status')->target_id != $completed_term_id) {
        continue;
      }

      $completed++;
    }

    return [
      'items_total' => $total,
      'items_completed' => $completed,
    ];
  }

}
