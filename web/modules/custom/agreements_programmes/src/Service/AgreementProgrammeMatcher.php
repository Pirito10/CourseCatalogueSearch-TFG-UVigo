<?php

namespace Drupal\agreements_programmes\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Isced\IscedFieldsOfStudy;

final class AgreementProgrammeMatcher {

  private const AGREEMENT_BUNDLE = 'agreement';
  private const PROGRAMME_BUNDLE = 'programme';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function getCompatibleProgrammesForProgramme(NodeInterface $origin_programme): array {
    if ($origin_programme->bundle() !== self::PROGRAMME_BUNDLE) {
      return [];
    }

    $origin_institution_id = $this->getTargetId($origin_programme, 'field_programme_institution');
    if (!$origin_institution_id) {
      return [];
    }

    $origin_isced_values = $this->getIscedValues($origin_programme, 'field_isced_f');
    $origin_eqf_level = $this->getFieldValue($origin_programme, 'field_eqf_level');

    $node_storage = $this->entityTypeManager->getStorage('node');
    $agreement_ids = $this->findAgreementIds($node_storage, $origin_institution_id);
    if (!$agreement_ids) {
      return [];
    }

    $origin_department_id = $this->getTargetId($origin_programme, 'field_programme_ou');
    $rules = [];

    foreach ($node_storage->loadMultiple($agreement_ids) as $agreement) {
      if ($agreement instanceof NodeInterface) {
        $rule = $this->buildRuleFromAgreement($agreement, $origin_institution_id, $origin_department_id, $origin_isced_values, $origin_eqf_level);

        if ($rule) {
          $rules[] = $rule;
        }
      }
    }

    return $rules ? $this->findProgrammesFromRules($node_storage, $rules, (int) $origin_programme->id()) : [];
  }

  private function findAgreementIds(EntityStorageInterface $node_storage, int $origin_institution_id): array {
    return $node_storage->getQuery()
      ->condition('type', self::AGREEMENT_BUNDLE)
      ->condition('status', 1)
      ->condition('field_institution_1', $origin_institution_id)
      ->accessCheck(FALSE)
      ->execute();
  }

  private function buildRuleFromAgreement(NodeInterface $agreement, int $origin_institution_id, ?int $origin_department_id, array $origin_isced_values, ?string $origin_eqf_level): ?array {
    $institution_1 = $this->getTargetId($agreement, 'field_institution_1');
    $institution_2 = $this->getTargetId($agreement, 'field_institution_2');

    if (!$institution_1 || !$institution_2) {
      return NULL;
    }

    if ($institution_1 !== $origin_institution_id) {
      return NULL;
    }

    $target_institution_id = $institution_2;
    $origin_department_field = 'field_department_partner_1';
    $target_department_field = 'field_department_partner_2';

    $agreement_origin_department_id = $this->getTargetId($agreement, $origin_department_field);
    $agreement_target_department_id = $this->getTargetId($agreement, $target_department_field);
    if ($agreement_origin_department_id && $agreement_origin_department_id !== $origin_department_id) {
      return NULL;
    }

    $agreement_eqf_level = $this->getFieldValue($agreement, 'field_level_of_education');
    if ($agreement_eqf_level !== NULL && $agreement_eqf_level !== $origin_eqf_level) {
      return NULL;
    }

    $agreement_isced_values = $this->getIscedValues($agreement, 'field_field_of_education');
    $is_department_scoped_agreement = $agreement_origin_department_id && $agreement_target_department_id;
    $matches_all_isced = FALSE;
    $agreement_hierarchy_values = [];
    $origin_matching_isced_values = [];

    if (!$agreement_isced_values) {
      if (!$is_department_scoped_agreement) {
        return NULL;
      }

      $matches_all_isced = TRUE;
    }
    elseif (!$origin_isced_values) {
      return NULL;
    }
    else {
      $agreement_hierarchy_values = $this->expandIscedHierarchy($agreement_isced_values);
      $origin_hierarchy_values = $this->expandIscedHierarchy($origin_isced_values);
      $origin_matching_isced_values = array_values(array_intersect($origin_hierarchy_values, $agreement_hierarchy_values));

      if (!$origin_matching_isced_values) {
        return NULL;
      }
    }

    return [
      'agreement_id' => $agreement->id(),
      'target_institution_id' => $target_institution_id,
      'target_department_id' => $agreement_target_department_id,
      'eqf_level' => $agreement_eqf_level,
      'matches_all_isced' => $matches_all_isced,
      'isced_values' => $agreement_isced_values,
      'agreement_hierarchy_values' => $agreement_hierarchy_values,
      'origin_matching_isced_values' => $origin_matching_isced_values,
    ];
  }

  private function findProgrammesFromRules(EntityStorageInterface $node_storage, array $rules, int $origin_programme_id): array {
    $results = [];

    foreach ($rules as $rule) {
      $query = $node_storage->getQuery()
        ->condition('type', self::PROGRAMME_BUNDLE)
        ->condition('status', 1)
        ->accessCheck(TRUE)
        ->condition('nid', $origin_programme_id, '<>')
        ->condition('field_programme_institution', $rule['target_institution_id']);

      if (empty($rule['matches_all_isced'])) {
        $query->exists('field_isced_f.value');
      }

      if (!empty($rule['target_department_id'])) {
        $query->condition('field_programme_ou', $rule['target_department_id']);
      }

      if (!empty($rule['eqf_level'])) {
        $query->condition('field_eqf_level.value', $rule['eqf_level']);
      }

      $programme_ids = $query->execute();
      if (!$programme_ids) {
        continue;
      }

      foreach ($node_storage->loadMultiple($programme_ids) as $programme) {
        if ($programme instanceof NodeInterface) {
          $matching_isced_values = !empty($rule['matches_all_isced'])
            ? []
            : $this->getMatchingIscedValues($programme, $rule['origin_matching_isced_values']);

          if (empty($rule['matches_all_isced']) && !$matching_isced_values) {
            continue;
          }

          $programme_id = $programme->id();

          if (!isset($results[$programme_id])) {
            $results[$programme_id] = [
              'programme' => $programme,
              'agreement_ids' => [],
              'agreements' => [],
              'matching_isced_values' => [],
            ];
          }

          $results[$programme_id]['agreement_ids'][$rule['agreement_id']] = $rule['agreement_id'];
          $results[$programme_id]['agreements'][$rule['agreement_id']] = [
            'agreement_id' => $rule['agreement_id'],
            'target_institution_id' => $rule['target_institution_id'],
            'target_department_id' => $rule['target_department_id'],
            'eqf_level' => $rule['eqf_level'],
            'matches_all_isced' => $rule['matches_all_isced'],
            'isced_values' => $rule['isced_values'],
            'agreement_hierarchy_values' => $rule['agreement_hierarchy_values'],
            'origin_matching_isced_values' => $rule['origin_matching_isced_values'],
            'matching_isced_values' => $matching_isced_values,
          ];
          $results[$programme_id]['matching_isced_values'] = array_values(array_unique(array_merge(
            $results[$programme_id]['matching_isced_values'],
            $matching_isced_values,
          )));
        }
      }
    }

    foreach ($results as &$result) {
      $result['agreement_ids'] = array_values($result['agreement_ids']);
      $result['agreements'] = array_values($result['agreements']);
      $result['agreement_id'] = reset($result['agreement_ids']) ?: NULL;
    }
    unset($result);

    return $results;
  }

  private function getTargetId(NodeInterface $node, string $field_name): ?int {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    return (int) $node->get($field_name)->target_id;
  }

  private function getFieldValue(NodeInterface $node, string $field_name): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    $value = $node->get($field_name)->value;

    return $value !== NULL && $value !== '' ? (string) $value : NULL;
  }

  private function getIscedValues(NodeInterface $node, string $field_name): array {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return [];
    }

    $values = [];

    foreach ($node->get($field_name) as $item) {
      $value = $item->get('value')->getValue();

      if ($value !== NULL && $value !== '') {
        $values[] = (string) $value;
      }
    }

    return array_values(array_unique($values));
  }

  private function getMatchingIscedValues(NodeInterface $programme, array $allowed_isced_values): array {
    $programme_isced_values = $this->getIscedValues($programme, 'field_isced_f');

    if (!$programme_isced_values) {
      return [];
    }

    return array_values(array_intersect(
      $this->expandIscedHierarchy($programme_isced_values),
      $allowed_isced_values,
    ));
  }

  private function expandIscedHierarchy(array $codes): array {
    if (!$codes) {
      return [];
    }

    $isced = new IscedFieldsOfStudy();
    $list = $isced->getList();
    $expanded = [];

    foreach (array_unique($codes) as $code) {
      if (!$isced->exists($code)) {
        continue;
      }

      foreach ($list as $candidate_code => $metadata) {
        if (
          $candidate_code === $code
          || $metadata[IscedFieldsOfStudy::BROAD] === $code
          || $metadata[IscedFieldsOfStudy::NARROW] === $code
        ) {
          $expanded[] = (string) $candidate_code;
        }
      }
    }

    return array_values(array_unique($expanded));
  }

}
