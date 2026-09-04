<?php

use Drupal\node\NodeInterface;

$storage = \Drupal::entityTypeManager()->getStorage('node');
$matcher = \Drupal::service('agreements_programmes.matcher');

/**
 * Returns simple scalar values for an ISCED field.
 */
function audit_isced_values(NodeInterface $node, string $field_name): array {
  if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
    return [];
  }

  $values = [];
  foreach ($node->get($field_name) as $item) {
    if (isset($item->value) && $item->value !== NULL && $item->value !== '') {
      $values[] = (string) $item->value;
    }
  }

  return $values;
}

/**
 * Returns the referenced entity label or '-'.
 */
function audit_ref_label(NodeInterface $node, string $field_name): string {
  if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
    return '-';
  }

  $target = $node->get($field_name)->entity;
  return $target ? $target->label() : '-';
}

print "=== PROGRAMMES ===\n";

$programme_ids = $storage->getQuery()
  ->condition('type', 'programme')
  ->accessCheck(FALSE)
  ->sort('title')
  ->execute();

foreach ($storage->loadMultiple($programme_ids) as $programme) {
  if (!$programme instanceof NodeInterface) {
    continue;
  }

  $institution = audit_ref_label($programme, 'field_programme_institution');
  $ou = audit_ref_label($programme, 'field_programme_ou');
  $isced = audit_isced_values($programme, 'field_isced_f');
  $matches = $matcher->getCompatibleProgrammesForProgramme($programme);

  $match_parts = [];
  foreach ($matches as $match) {
    $match_programme = $match['programme'];
    $match_parts[] = sprintf(
      '%s {agreement_ids=%s; shared_isced=%s}',
      $match_programme->label(),
      implode(',', $match['agreement_ids']),
      implode(',', $match['matching_isced_values']),
    );
  }

  print sprintf(
    "[%d] %s | institution=%s | ou=%s | isced=%s\n",
    $programme->id(),
    $programme->label(),
    $institution,
    $ou,
    $isced ? implode(',', $isced) : '-',
  );

  print '  matches: ' . ($match_parts ? implode(' || ', $match_parts) : '-') . "\n";
}

print "\n=== AGREEMENTS ===\n";

$agreement_ids = $storage->getQuery()
  ->condition('type', 'agreement')
  ->accessCheck(FALSE)
  ->sort('title')
  ->execute();

foreach ($storage->loadMultiple($agreement_ids) as $agreement) {
  if (!$agreement instanceof NodeInterface) {
    continue;
  }

  print sprintf(
    "[%d] %s | i1=%s | i2=%s | ou1=%s | ou2=%s | isced=%s\n",
    $agreement->id(),
    $agreement->label(),
    audit_ref_label($agreement, 'field_institution_1'),
    audit_ref_label($agreement, 'field_institution_2'),
    audit_ref_label($agreement, 'field_department_partner_1'),
    audit_ref_label($agreement, 'field_department_partner_2'),
    implode(',', audit_isced_values($agreement, 'field_field_of_education')) ?: '-',
  );
}
