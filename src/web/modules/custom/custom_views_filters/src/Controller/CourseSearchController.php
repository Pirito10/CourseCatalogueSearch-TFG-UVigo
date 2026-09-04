<?php

namespace Drupal\custom_views_filters\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\custom_views_filters\FuzzySearch;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CourseSearchController extends ControllerBase {

  public function search(Request $request): JsonResponse {
    $body = json_decode($request->getContent(), TRUE);
    $terms = array_filter(array_map('trim', $body['terms'] ?? []));

    if (empty($terms)) {
      return new JsonResponse(['programmes' => []]);
    }

    $totalTerms = count($terms);
    $programmeScores = [];

    foreach ($terms as $term) {
      $matches = FuzzySearch::scoreFromIndex($term, 'courses');
      if ($matches === NULL || empty($matches)) {
        continue;
      }

      $iecNids = array_column($matches, 'nid');

      $rows = \Drupal::database()
        ->select('node__field_iec_programme', 'ip')
        ->fields('ip', ['field_iec_programme_target_id'])
        ->condition('entity_id', $iecNids, 'IN')
        ->execute()
        ->fetchCol();

      foreach (array_unique($rows) as $programmeNid) {
        $programmeScores[$programmeNid] = ($programmeScores[$programmeNid] ?? 0) + 1;
      }
    }

    if (empty($programmeScores)) {
      return new JsonResponse(['programmes' => []]);
    }

    arsort($programmeScores);
    $topNids = array_slice(array_keys($programmeScores), 0, 20, TRUE);
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($topNids);

    $programmes = [];
    foreach ($topNids as $nid) {
      $node = $nodes[$nid] ?? NULL;
      if (!$node || !$node->isPublished()) {
        continue;
      }

      $institution = $node->get('field_programme_institution')->entity;

      $programmes[] = [
        'nid'         => $nid,
        'title'       => $node->label(),
        'score'       => $programmeScores[$nid],
        'total'       => $totalTerms,
        'institution' => $institution ? $institution->label() : NULL,
        'url'         => $node->toUrl()->setAbsolute(FALSE)->toString(),
      ];
    }

    return new JsonResponse(['programmes' => $programmes]);
  }

}
