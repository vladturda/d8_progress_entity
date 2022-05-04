<?php

namespace Drupal\d8_progress_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Progress entity type entity.
 *
 * @ConfigEntityType(
 *   id = "progress_entity_type",
 *   label = @Translation("Progress entity type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\d8_progress_entity\ProgressEntityTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\d8_progress_entity\Form\ProgressEntityTypeForm",
 *       "edit" = "Drupal\d8_progress_entity\Form\ProgressEntityTypeForm",
 *       "delete" = "Drupal\d8_progress_entity\Form\ProgressEntityTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\d8_progress_entity\ProgressEntityTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "progress_entity_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "progress_entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/progress_entity_type/{progress_entity_type}",
 *     "add-form" = "/admin/structure/progress_entity_type/add",
 *     "edit-form" = "/admin/structure/progress_entity_type/{progress_entity_type}/edit",
 *     "delete-form" = "/admin/structure/progress_entity_type/{progress_entity_type}/delete",
 *     "collection" = "/admin/structure/progress_entity_type"
 *   }
 * )
 */
class ProgressEntityType extends ConfigEntityBundleBase implements ProgressEntityTypeInterface {

  /**
   * The Progress entity type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Progress entity type label.
   *
   * @var string
   */
  protected $label;

}
