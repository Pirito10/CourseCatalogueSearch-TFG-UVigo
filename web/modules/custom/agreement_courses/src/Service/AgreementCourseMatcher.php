<?php

namespace Drupal\agreement_courses\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Isced\IscedFieldsOfStudy;

final class AgreementCourseMatcher {

  private const AGREEMENT_BUNDLE = 'agreement';
  private const COURSE_BUNDLE = 'individual_educational_component';
  private const PROGRAMME_BUNDLE = 'programme';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function getCompatibleCoursesForProgramme(NodeInterface $programme): array {
    if ($programme->bundle() !== self::PROGRAMME_BUNDLE) {
      return [];
    }

    $origin_institution_id = $this->getTargetId($programme, 'field_programme_institution');
    if (!$origin_institution_id) {
      return [];
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $origin_course_isced_values = $this->getProgrammeCourseIscedValues($node_storage, $programme);
    if (!$origin_course_isced_values) {
      return [];
    }

    $agreement_ids = $this->findAgreementIds($node_storage, $origin_institution_id);
    if (!$agreement_ids) {
      return [];
    }

    $origin_department_id = $this->getTargetId($programme, 'field_programme_ou');
    $rules = [];

    foreach ($node_storage->loadMultiple($agreement_ids) as $agreement) {
      if ($agreement instanceof NodeInterface) {
        $rule = $this->buildRuleFromAgreement($agreement, $origin_institution_id, $origin_department_id, $origin_course_isced_values);

        if ($rule) {
          $rules[] = $rule;
        }
      }
    }

    return $rules ? $this->findCoursesFromRules($node_storage, $rules) : [];
  }

  private function findAgreementIds(EntityStorageInterface $node_storage, int $origin_institution_id): array {
    $query = $node_storage->getQuery()
      ->condition('type', self::AGREEMENT_BUNDLE)
      ->condition('status', 1)
      ->accessCheck(TRUE);

    $institution_group = $query->orConditionGroup()
      ->condition('field_institution_1', $origin_institution_id)
      ->condition('field_institution_2', $origin_institution_id);

    return $query
      ->condition($institution_group)
      ->execute();
  }

  private function buildRuleFromAgreement(NodeInterface $agreement, int $origin_institution_id, ?int $origin_department_id, array $origin_course_isced_values): ?array {
    $institution_1 = $this->getTargetId($agreement, 'field_institution_1');
    $institution_2 = $this->getTargetId($agreement, 'field_institution_2');

    if (!$institution_1 || !$institution_2) {
      return NULL;
    }

    if ($institution_1 === $origin_institution_id) {
      $target_institution_id = $institution_2;
      $origin_department_field = 'field_department_partner_1';
      $target_department_field = 'field_department_partner_2';
    }
    elseif ($institution_2 === $origin_institution_id) {
      $target_institution_id = $institution_1;
      $origin_department_field = 'field_department_partner_2';
      $target_department_field = 'field_department_partner_1';
    }
    else {
      return NULL;
    }

    $agreement_origin_department_id = $this->getTargetId($agreement, $origin_department_field);
    if ($agreement_origin_department_id && $agreement_origin_department_id !== $origin_department_id) {
      return NULL;
    }

    $isced_values = $this->getIscedValues($agreement, 'field_field_of_education');
    $course_isced_values = $this->expandIscedValuesForCourseQuery($isced_values);

    if (!$course_isced_values) {
      return NULL;
    }

    $origin_matching_isced_values = array_values(array_intersect($origin_course_isced_values, $course_isced_values));
    if (!$origin_matching_isced_values) {
      return NULL;
    }

    return [
      'agreement_id' => $agreement->id(),
      'target_institution_id' => $target_institution_id,
      'target_department_id' => $this->getTargetId($agreement, $target_department_field),
      'isced_values' => $isced_values,
      'course_isced_values' => $course_isced_values,
      'origin_matching_isced_values' => $origin_matching_isced_values,
    ];
  }

  private function findCoursesFromRules(EntityStorageInterface $node_storage, array $rules): array {
    $results = [];

    foreach ($rules as $rule) {
      $programme_ids = $this->findTargetProgrammeIds($node_storage, $rule);
      if (!$programme_ids) {
        continue;
      }

      $course_ids = $node_storage->getQuery()
        ->condition('type', self::COURSE_BUNDLE)
        ->condition('status', 1)
        ->accessCheck(TRUE)
        ->condition('field_iec_programme', array_values($programme_ids), 'IN')
        ->condition('field_fields_of_study.value', $rule['course_isced_values'], 'IN')
        ->execute();

      if (!$course_ids) {
        continue;
      }

      foreach ($node_storage->loadMultiple($course_ids) as $course) {
        if ($course instanceof NodeInterface) {
          $matching_isced_values = $this->getMatchingIscedValues($course, $rule['course_isced_values']);

          if (!$matching_isced_values) {
            continue;
          }

          $course_id = $course->id();

          if (!isset($results[$course_id])) {
            $results[$course_id] = [
              'course' => $course,
              'agreement_ids' => [],
              'agreements' => [],
              'matching_isced_values' => [],
            ];
          }

          $results[$course_id]['agreement_ids'][$rule['agreement_id']] = $rule['agreement_id'];
          $results[$course_id]['agreements'][$rule['agreement_id']] = [
            'agreement_id' => $rule['agreement_id'],
            'target_institution_id' => $rule['target_institution_id'],
            'target_department_id' => $rule['target_department_id'],
            'isced_values' => $rule['isced_values'],
            'origin_matching_isced_values' => $rule['origin_matching_isced_values'],
            'matching_isced_values' => $matching_isced_values,
          ];
          $results[$course_id]['matching_isced_values'] = array_values(array_unique(array_merge(
            $results[$course_id]['matching_isced_values'],
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

  private function findTargetProgrammeIds(EntityStorageInterface $node_storage, array $rule): array {
    $query = $node_storage->getQuery()
      ->condition('type', self::PROGRAMME_BUNDLE)
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->condition('field_programme_institution', $rule['target_institution_id']);

    if (!empty($rule['target_department_id'])) {
      $query->condition('field_programme_ou', $rule['target_department_id']);
    }

    return $query->execute();
  }

  private function expandIscedValuesForCourseQuery(array $agreement_values): array {
    if (!$agreement_values) {
      return [];
    }

    $isced = new IscedFieldsOfStudy();
    $matches = [];

    foreach ($agreement_values as $agreement_value) {
      if (!$isced->exists($agreement_value)) {
        continue;
      }

      foreach ($isced->getList() as $course_value => $metadata) {
        if (
          $course_value === $agreement_value
          || $metadata[IscedFieldsOfStudy::BROAD] === $agreement_value
          || $metadata[IscedFieldsOfStudy::NARROW] === $agreement_value
          || $metadata[IscedFieldsOfStudy::DETAILED] === $agreement_value
        ) {
          $matches[] = (string) $course_value;
        }
      }
    }

    return array_values(array_unique($matches));
  }

  private function getTargetId(NodeInterface $node, string $field_name): ?int {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    return (int) $node->get($field_name)->target_id;
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

  private function getProgrammeCourseIscedValues(EntityStorageInterface $node_storage, NodeInterface $programme): array {
    $course_ids = $node_storage->getQuery()
      ->condition('type', self::COURSE_BUNDLE)
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->condition('field_iec_programme', $programme->id())
      ->exists('field_fields_of_study.value')
      ->execute();

    if (!$course_ids) {
      return [];
    }

    $values = [];

    foreach ($node_storage->loadMultiple($course_ids) as $course) {
      if ($course instanceof NodeInterface) {
        $values = array_merge($values, $this->getIscedValues($course, 'field_fields_of_study'));
      }
    }

    return array_values(array_unique($values));
  }

  private function getMatchingIscedValues(NodeInterface $course, array $allowed_isced_values): array {
    $course_isced_values = $this->getIscedValues($course, 'field_fields_of_study');

    if (!$course_isced_values) {
      return [];
    }

    return array_values(array_intersect($course_isced_values, $allowed_isced_values));
  }

}
