<?php

/**
 * @file
 * Contains progress_entity.page.inc.
 *
 * Page callback for Progress entity entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Progress entity templates.
 *
 * Default template: progress_entity.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_progress_entity(array &$variables) {
  // Fetch ProgressEntity Entity Object.
  $progress_entity = $variables['elements']['#progress_entity'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
