<?php

namespace Drupal\my_contact_module\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides the Contact page.
 */
class ContactController extends ControllerBase {

  /**
   * Render the Contact page.
   */
  public function content() {
    return [
      '#theme' => 'contact_page',
      '#title' => $this->t('Contact us'),
      '#variables' => [
        'coordinator_name' => 'Manuel Caeiro',
        'coordinator_role' => 'Project Coordinator',
        'coordinator_email' => 'manuel.caeiro@det.uvigo.es',
      ],
    ];
  }
}
