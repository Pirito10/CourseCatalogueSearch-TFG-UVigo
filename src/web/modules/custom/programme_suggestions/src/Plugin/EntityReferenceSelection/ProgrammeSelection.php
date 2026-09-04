<?php

namespace Drupal\programme_suggestions\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\node\NodeInterface;

/**
 * Selection handler for programme autocomplete with Institution and OU labels.
 *
 * @EntityReferenceSelection(
 *   id = "programme_suggestions_programme_selection",
 *   label = @Translation("Programme selection with institution and OU"),
 *   entity_types = {"node"},
 *   group = "programme_suggestions_programme_selection",
 * )
 */
class ProgrammeSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    // Solo nodos de tipo programme.
    $query->condition('type', 'programme');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $entities = parent::getReferenceableEntities($match, $match_operator, $limit);
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $result = [];

    foreach ($entities as $bundle => $items) {
      foreach ($items as $id => $label) {
        /** @var \Drupal\node\NodeInterface $node */
        $node = $storage->load($id);
        if (!$node instanceof NodeInterface) {
          continue;
        }

   
        // Campo referencia Institution en Programme.
        $institution = '';
        if (!$node->get('field_programme_institution')->isEmpty()) {
          $institution_entity = $node->get('field_programme_institution')->entity;
          $institution = $institution_entity ? $institution_entity->label() : '';
        }

        // Campo referencia Organizational Unit en Programme.
        $ou = '';
        if (!$node->get('field_programme_ou')->isEmpty()) {
          $ou_entity = $node->get('field_programme_ou')->entity;
          $ou = $ou_entity ? $ou_entity->label() : '';
        }

        $programme = $node->label();

        // Texto final: Institution--OU--Programme
        $custom_label = $institution . '--' . $ou . '--' . $programme;

        // Limpieza final del label para evitar comillas y espacios
        $custom_label = trim($custom_label);                     // quita espacios iniciales/finales
        $custom_label = trim($custom_label, "\"' ");             // quita comillas " y '
        $custom_label = preg_replace('/\s+/', ' ', $custom_label); // colapsa espacios dobles

        $result[$bundle][$id] = $custom_label;
      }
    }

    return $result;
  }

}
