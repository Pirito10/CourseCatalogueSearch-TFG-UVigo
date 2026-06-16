<?php

namespace Drupal\custom_views_filters;

class TranslationService {

  const URL = 'http://libretranslate:5000';

  const LANGUAGES = ['en', 'es', 'fr', 'cs', 'el', 'et', 'gl', 'hu', 'pt', 'sl'];

  /**
   * Returns the original term plus its translations to all other site languages.
   *
   * The source language is the current Drupal UI language (or $sourceLang,
   * used in tests), never auto-detected — LibreTranslate's detection is too
   * unreliable for short, accent-less academic terms. If the source is
   * English, returns just [$input], since every node is indexed in English
   * at minimum and no translation is needed.
   *
   * @return string[]
   */
  public static function getSearchTerms(string $input, ?string $sourceLang = NULL): array {
    $source = $sourceLang ?? static::uiLanguage();

    if ($source === 'en') {
      return [$input];
    }

    $targets = array_diff(static::LANGUAGES, [$source]);
    $terms = [$input];

    foreach (static::translateAll($input, $source, $targets) as $translated) {
      if ($translated !== NULL && $translated !== $input) {
        $terms[] = $translated;
      }
    }

    return $terms;
  }

  protected static function uiLanguage(): string {
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    return $lang === 'pt-pt' ? 'pt' : $lang;
  }

  /**
   * Fires one translation request per target language concurrently instead
   * of waiting for each to finish before starting the next.
   *
   * @return array<string, string|null> keyed by target language code
   */
  protected static function translateAll(string $input, string $source, array $targets): array {
    $client = \Drupal::httpClient();
    $promises = [];

    foreach ($targets as $lang) {
      $promises[$lang] = $client->postAsync(static::URL . '/translate', [
        'json' => ['q' => $input, 'source' => $source, 'target' => $lang],
        'timeout' => 5,
      ]);
    }

    $responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

    $translations = [];
    foreach ($responses as $lang => $result) {
      if ($result['state'] !== 'fulfilled') {
        $translations[$lang] = NULL;
        continue;
      }
      $data = json_decode((string) $result['value']->getBody(), TRUE);
      $translations[$lang] = $data['translatedText'] ?? NULL;
    }

    return $translations;
  }

}
