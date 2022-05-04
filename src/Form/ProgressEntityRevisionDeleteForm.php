<?php

namespace Drupal\d8_progress_entity\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Progress entity revision.
 *
 * @ingroup d8_progress_entity
 */
class ProgressEntityRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The Progress entity revision.
   *
   * @var \Drupal\d8_progress_entity\Entity\ProgressEntityInterface
   */
  protected $revision;

  /**
   * The Progress entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $progressEntityStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->progressEntityStorage = $container->get('entity_type.manager')->getStorage('progress_entity');
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'progress_entity_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', [
      '%revision-date' => format_date($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.progress_entity.version_history', ['progress_entity' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $progress_entity_revision = NULL) {
    $this->revision = $this->ProgressEntityStorage->loadRevision($progress_entity_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->ProgressEntityStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Progress entity: deleted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    $this->messenger()->addMessage(t('Revision from %revision-date of Progress entity %title has been deleted.', ['%revision-date' => format_date($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()]));
    $form_state->setRedirect(
      'entity.progress_entity.canonical',
       ['progress_entity' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {progress_entity_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.progress_entity.version_history',
         ['progress_entity' => $this->revision->id()]
      );
    }
  }

}
