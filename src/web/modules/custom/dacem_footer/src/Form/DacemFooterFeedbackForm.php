<?php

namespace Drupal\dacem_footer\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Datetime\TimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Feedback form rendered inside the DACEM footer block.
 */
class DacemFooterFeedbackForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Module logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs the form.
   */
  public function __construct(Connection $database, RequestStack $request_stack, LoggerInterface $logger, TimeInterface $time) {
    $this->database = $database;
    $this->requestStack = $request_stack;
    $this->logger = $logger;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('request_stack'),
      $container->get('logger.factory')->get('dacem_footer'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dacem_footer_feedback_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_request = $this->requestStack->getCurrentRequest();

    $form['#attributes']['class'][] = 'dacem-footer-feedback-form';

    $form['row'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['row'],
      ],
    ];

    $form['row']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#placeholder' => $this->t('Name'),
      '#wrapper_attributes' => [
        'class' => ['col-6', 'mb-3'],
      ],
      '#attributes' => [
        'class' => ['form-control'],
      ],
    ];

    $form['row']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Email'),
      '#wrapper_attributes' => [
        'class' => ['col-6', 'mb-3'],
      ],
      '#attributes' => [
        'class' => ['form-control'],
      ],
    ];

    $form['row']['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Your message'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Message'),
      '#rows' => 3,
      '#wrapper_attributes' => [
        'class' => ['col-12', 'mb-3'],
      ],
      '#attributes' => [
        'class' => ['form-control'],
      ],
    ];

    $form['context_page'] = [
      '#type' => 'hidden',
      '#value' => $current_request ? $current_request->getPathInfo() : '',
    ];

    $form['row']['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'class' => ['col-12', 'text-end'],
      ],
    ];

    $form['row']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'class' => ['btn', 'btn-dark', 'px-4'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach (['email', 'message'] as $field) {
      $value = trim((string) $form_state->getValue($field));
      $form_state->setValue($field, $value);

      if ($value === '') {
        $form_state->setErrorByName($field, $this->t('This field is required.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    $message_text = $form_state->getValue('message');
    $page_path = (string) $form_state->getValue('context_page');

    try {
      $this->database->insert('dacem_footer_feedback')
        ->fields([
          'email' => $email,
          'message' => $message_text,
          'page_path' => $page_path,
          'created' => $this->time->getRequestTime(),
        ])
        ->execute();

      $this->messenger()->addStatus($this->t('Thanks. Your feedback has been saved.'));
      $form_state->setRedirect('<current>');
      return;
    }
    catch (DatabaseExceptionWrapper $exception) {
      $this->logger->error('Failed to save footer feedback for %mail on path %path. Error: @message', [
        '%mail' => $email,
        '%path' => $page_path,
        '@message' => $exception->getMessage(),
      ]);
      $this->messenger()->addError($this->t('The feedback could not be saved right now. Please try again later.'));
    }
  }

}
