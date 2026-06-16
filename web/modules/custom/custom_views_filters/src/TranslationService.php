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

    foreach ($targets as $lang) {
      $translated = static::translate($input, $source, $lang);
      if ($translated !== null && $translated !== $input) {
        $terms[] = $translated;
      }
    }

    return $terms;
  }

  protected static function uiLanguage(): string {
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    return $lang === 'pt-pt' ? 'pt' : $lang;
  }

  protected static function translate(string $input, string $source, string $target): ?string {
    $data = static::post('/translate', [
      'q' => $input,
      'source' => $source,
      'target' => $target,
    ]);
    return $data['translatedText'] ?? NULL;
  }

  protected static function post(string $endpoint, array $body): mixed {
    try {
      $response = \Drupal::httpClient()->post(static::URL . $endpoint, [
        'json' => $body,
        'timeout' => 5,
      ]);
      return json_decode((string) $response->getBody(), TRUE);
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}
