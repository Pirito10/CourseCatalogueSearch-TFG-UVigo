<?php

namespace Drupal\custom_views_filters;

class TranslationService {

  const URL = 'http://libretranslate:5000';

  /**
   * Returns the original term plus its English translation (if the UI
   * language is not English).
   *
   * Checks TranslationDictionary first; falls back to LibreTranslate for
   * terms not covered by the dictionary. The source language is the current
   * Drupal UI language (or $sourceLang, used in tests), never auto-detected.
   *
   * @return string[]
   */
  public static function getSearchTerms(string $input, ?string $sourceLang = NULL, string $indexId = 'programmes'): array {
    $source = $sourceLang ?? static::uiLanguage();

    if ($source === 'en') {
      return [$input];
    }

    $terms = [$input];
    $translated = static::dictionaryLookup($input, $source, $indexId)
      ?? static::translateTo($input, $source, 'en');

    if ($translated !== NULL && $translated !== $input) {
      $terms[] = $translated;
    }

    return $terms;
  }

  protected static function uiLanguage(): string {
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    return $lang === 'pt-pt' ? 'pt' : $lang;
  }

  /**
   * Looks up $input in the dictionary for the given index type and source
   * language. Returns the English equivalent or NULL if not found.
   */
  protected static function dictionaryLookup(string $input, string $sourceLang, string $indexId): ?string {
    $index = static::buildInvertedIndex($indexId);
    return $index[$sourceLang][static::normalize($input)] ?? NULL;
  }

  /**
   * Builds (once per request) an inverted index from TranslationDictionary
   * keyed by [lang][normalizedTerm] → english.
   */
  protected static function buildInvertedIndex(string $indexId): array {
    $source = $indexId === 'courses' ? TranslationDictionary::COURSES : TranslationDictionary::PROGRAMMES;
    $index = [];
    foreach ($source as $english => $translations) {
      foreach ($translations as $lang => $translation) {
        $index[$lang][static::normalize($translation)] = $english;
      }
    }
    return $index;
  }

  protected static function normalize(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    if (function_exists('transliterator_transliterate')) {
      $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
    }
    return (string) preg_replace('/\s+/', ' ', trim($text));
  }

  protected static function translateTo(string $input, string $source, string $target): ?string {
    try {
      $response = \Drupal::httpClient()->post(static::URL . '/translate', [
        'json' => ['q' => $input, 'source' => $source, 'target' => $target],
        'timeout' => 5,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
      return $data['translatedText'] ?? NULL;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}
