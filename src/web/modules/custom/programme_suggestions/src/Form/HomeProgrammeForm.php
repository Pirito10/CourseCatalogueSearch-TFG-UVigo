<?php

namespace Drupal\programme_suggestions\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

class HomeProgrammeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'programme_suggestions_home_programme_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['home_programme'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Home programme'),
      '#target_type' => 'node',
      '#selection_handler' => 'programme_suggestions_programme_selection',
      '#required' => TRUE,
      '#description' => $this->t('Start typing the programme you are studying.'),
       '#maxlength' => 255, 
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => ['btn', 'btn-primary'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   * Solo permite enviar si se ha seleccionado un Programme válido.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValue('home_programme');

    // 1) Vacío → error.
    if ($value === NULL || $value === '' || $value === 0 || $value === '0') {
      $form_state->setErrorByName('home_programme', $this->t('Please select a programme from the list.'));
      return;
    }

    // 2) No numérico → seguramente texto escrito a mano.
    if (!is_numeric($value)) {
      $form_state->setErrorByName('home_programme', $this->t('The selected programme is not valid.'));
      return;
    }

    // 3) Debe ser un nodo programme.
    $nid = (int) $value;
    /** @var \Drupal\node\Entity\Node|null $node */
    $node = Node::load($nid);

    if (!$node || $node->bundle() !== 'programme') {
      $form_state->setErrorByName('home_programme', $this->t('The selected programme is not valid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nid = (int) $form_state->getValue('home_programme');

    $form_state->setRedirect('view.search_programme.page_1', [], [
      'query' => [
        'home_programme' => $nid,
      ],
    ]);
  }

}
