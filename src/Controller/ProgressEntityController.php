<?php

namespace Drupal\d8_progress_entity\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\d8_progress_entity\Entity\ProgressEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProgressEntityController.
 *
 *  Returns responses for Progress entity routes.
 */
class ProgressEntityController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays a Progress entity revision.
   *
   * @param int $progress_entity_revision
   *   The Progress entity revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($progress_entity_revision) {
    $progress_entity = $this->entityTypeManager()->getStorage('progress_entity')
      ->loadRevision($progress_entity_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('progress_entity');

    return $view_builder->view($progress_entity);
  }

  /**
   * Page title callback for a Progress entity revision.
   *
   * @param int $progress_entity_revision
   *   The Progress entity revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($progress_entity_revision) {
    $progress_entity = $this->entityTypeManager()->getStorage('progress_entity')
      ->loadRevision($progress_entity_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $progress_entity->label(),
      '%date' => $this->dateFormatter->format($progress_entity->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Progress entity.
   *
   * @param \Drupal\d8_progress_entity\Entity\ProgressEntityInterface $progress_entity
   *   A Progress entity object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(ProgressEntityInterface $progress_entity) {
    $account = $this->currentUser();
    $progress_entity_storage = $this->entityTypeManager()->getStorage('progress_entity');

    $langcode = $progress_entity->language()->getId();
    $langname = $progress_entity->language()->getName();
    $languages = $progress_entity->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $progress_entity->label()]) : $this->t('Revisions for %title', ['%title' => $progress_entity->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all progress entity revisions") || $account->hasPermission('administer progress entity entities')));
    $delete_permission = (($account->hasPermission("delete all progress entity revisions") || $account->hasPermission('administer progress entity entities')));

    $rows = [];

    $vids = $progress_entity_storage->revisionIds($progress_entity);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\d8_progress_entity\ProgressEntityInterface $revision */
      $revision = $progress_entity_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $progress_entity->getRevisionId()) {
          $link = $this->l($date, new Url('entity.progress_entity.revision', [
            'progress_entity' => $progress_entity->id(),
            'progress_entity_revision' => $vid,
          ]));
        }
        else {
          $link = $progress_entity->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.progress_entity.translation_revert', [
                'progress_entity' => $progress_entity->id(),
                'progress_entity_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.progress_entity.revision_revert', [
                'progress_entity' => $progress_entity->id(),
                'progress_entity_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.progress_entity.revision_delete', [
                'progress_entity' => $progress_entity->id(),
                'progress_entity_revision' => $vid,
              ]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['progress_entity_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
