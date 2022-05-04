<?php

namespace Drupal\d8_progress_entity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Progress entity entities.
 *
 * @ingroup d8_progress_entity
 */
interface ProgressEntityInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Progress entity name.
   *
   * @return string
   *   Name of the Progress entity.
   */
  public function getName();

  /**
   * Sets the Progress entity name.
   *
   * @param string $name
   *   The Progress entity name.
   *
   * @return \Drupal\d8_progress_entity\Entity\ProgressEntityInterface
   *   The called Progress entity entity.
   */
  public function setName($name);

  /**
   * Gets the Progress entity creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Progress entity.
   */
  public function getCreatedTime();

  /**
   * Sets the Progress entity creation timestamp.
   *
   * @param int $timestamp
   *   The Progress entity creation timestamp.
   *
   * @return \Drupal\d8_progress_entity\Entity\ProgressEntityInterface
   *   The called Progress entity entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the Progress entity revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Progress entity revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\d8_progress_entity\Entity\ProgressEntityInterface
   *   The called Progress entity entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Progress entity revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Progress entity revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\d8_progress_entity\Entity\ProgressEntityInterface
   *   The called Progress entity entity.
   */
  public function setRevisionUserId($uid);

}
