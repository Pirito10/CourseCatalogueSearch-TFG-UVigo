<?php

namespace Drupal\custom_views_filters;

class TranslationService {

  const URL = 'http://libretranslate:5000';

  /**
   * Returns the original term plus its English translation (if the UI
   * language is not English).
   *
   * The source language is the current Drupal UI language (or $sourceLang,
   * used in tests), never auto-detected — LibreTranslate's detection is too
   * unreliable for short, accent-less academic terms. If the source is
   * English, returns just [$input], since all content is indexed in English
   * and no translation is needed.
   *
   * @return string[]
   */
  public static function getSearchTerms(string $input, ?string $sourceLang = NULL): array {
    $source = $sourceLang ?? static::uiLanguage();

    if ($source === 'en') {
      return [$input];
    }

    $terms = [$input];
    $translated = static::translateTo($input, $source, 'en');

    if ($translated !== NULL && $translated !== $input) {
      $terms[] = $translated;
    }

    return $terms;
  }

  protected static function uiLanguage(): string {
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    return $lang === 'pt-pt' ? 'pt' : $lang;
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
