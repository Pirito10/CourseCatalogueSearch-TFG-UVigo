<?php

namespace Drupal\custom_views_filters;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface;

class FuzzySearch {

  /**
   * Scores keyed by nid, shared between hook_views_query_alter and
   * hook_views_post_execute via static state.
   */
  public static array $scores = [];

  /**
   * Entry point: loads candidates from the Search API index, scores them
   * against $input using Levenshtein distance, and returns the matches
   * sorted by descending score.
   *
   * Returns NULL when $input produces no tokenisable words (too short),
   * which the caller treats as "do not filter".
   *
   * @return array<int, array{nid: int, score: float}>|null
   */
  public static function scoreFromIndex(string $input, string $indexId = 'programmes'): ?array {
    $candidates = static::loadCandidatesFromIndex($indexId);
    return static::scoreAndFilter($input, $candidates);
  }

  /**
   * Queries the Search API index and returns all indexed items as
   * [['nid' => int, 'title' => string], ...].
   */
  protected static function loadCandidatesFromIndex(string $indexId): array {
    $index = Index::load($indexId);
    if (!$index) {
      return [];
    }

    $query = $index->query();
    $query->range(0, 10000);
    $results = $query->execute();

    $candidates = [];
    foreach ($results->getResultItems() as $itemId => $item) {
      // Search API item IDs have the format "entity:node/123:en".
      preg_match('/entity:node\/(\d+):/', $itemId, $m);
      if (empty($m[1])) {
        continue;
      }
      $nid = (int) $m[1];

      $title = '';
      $titleField = $item->getField('title');
      if ($titleField) {
        foreach ($titleField->getValues() as $value) {
          if ($value instanceof TextValueInterface) {
            $title = $value->getText();
          }
          elseif (is_string($value)) {
            $title = $value;
          }
          break;
        }
      }

      if ($nid && $title !== '') {
        $candidates[] = ['nid' => $nid, 'title' => $title];
      }
    }

    return $candidates;
  }

  /**
   * Scores each candidate against the query string using word-level
   * Levenshtein distance and returns matches sorted by descending score.
   *
   * @return array<int, array{nid: int, score: float}>|null
   */
  protected static function scoreAndFilter(string $input, array $candidates): ?array {
    $queryWords = static::tokenize(static::normalize($input));
    if (empty($queryWords)) {
      return NULL;
    }

    $results = [];
    foreach ($candidates as $row) {
      $textWords = array_unique(static::tokenize(static::normalize($row['title'])));

      $score = 0.0;
      foreach ($queryWords as $qword) {
        $best = 0.0;
        foreach ($textWords as $tword) {
          $s = static::wordScore($qword, $tword);
          if ($s > $best) {
            $best = $s;
          }
          if ($best >= 1.0) {
            break;
          }
        }
        $score += $best;
      }

      if ($score > 0.0) {
        $nid = (int) $row['nid'];
        if (!isset($results[$nid]) || $score > $results[$nid]['score']) {
          $results[$nid] = ['nid' => $nid, 'score' => $score];
        }
      }
    }

    $results = array_values($results);
    usort($results, static fn($a, $b) => $b['score'] <=> $a['score']);
    return $results;
  }

  /**
   * Returns a similarity score in [0, 1] between two normalised words.
   *
   * - Exact match            → 1.0
   * - Words shorter than 3   → 0.0 (too short to compare safely)
   * - Prefix match           → 0.85 ("mech" matches "mechanical")
   * - Levenshtein distance 1 → high score ("enginering" matches "engineering")
   * - Levenshtein distance 2 → lower score (longer words only)
   * - Beyond threshold       → 0.0
   */
  protected static function wordScore(string $a, string $b): float {
    if ($a === $b) {
      return 1.0;
    }
    $la = strlen($a);
    $lb = strlen($b);
    if ($la < 3 || $lb < 3) {
      return 0.0;
    }
    if ($la <= $lb && str_starts_with($b, $a)) {
      return 0.85;
    }
    $maxLen = max($la, $lb);
    $threshold = $maxLen <= 5 ? 1 : 2;
    $dist = levenshtein($a, $b);
    return $dist <= $threshold ? 1.0 - ($dist / $maxLen) : 0.0;
  }

  /**
   * Lowercases and strips diacritics from a UTF-8 string.
   */
  protected static function normalize(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    if (function_exists('transliterator_transliterate')) {
      $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
    }
    return $text;
  }

  /**
   * Splits a string into lowercase ASCII words of at least 3 characters.
   */
  protected static function tokenize(string $text): array {
    preg_match_all('/[a-z]{3,}/', $text, $matches);
    return $matches[0];
  }

}
