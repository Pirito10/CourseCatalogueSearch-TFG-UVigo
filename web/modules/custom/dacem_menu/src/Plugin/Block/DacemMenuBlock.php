<?php

namespace Drupal\dacem_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Site\Settings;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;

/**
 * Provides a 'Dacem Menu' Block.
 *
 * @Block(
 *   id = "dacem_menu_block",
 *   admin_label = @Translation("Dacem Menu Block"),
 *   category = @Translation("Custom")
 * )
 */
class DacemMenuBlock extends BlockBase
{

  /**
   * {@inheritdoc}
   */
  /**
   * {@inheritdoc}
   */
  public function build()
  {

    $route_match = \Drupal::routeMatch();
$route_name = $route_match->getRouteName();

// Lista de rutas donde el bloque debe aparecer.
$allowed_routes = [
  'view.main_page.page_1',
  'view.main_page.page_2',
  'view.alliances.page_1',
  'view.alliance_institutions.page_1',
  'view.search_programme.page_1',
  'view.search_iec.page_1',
];

$show_block = in_array($route_name, $allowed_routes, TRUE);

// Si no es una de las Views permitidas, comprueba si es la página /about.
if (!$show_block && $route_name === 'entity.node.canonical') {
  $current_path = \Drupal::service('path.current')->getPath();
  $alias = \Drupal::service('path_alias.manager')->getAliasByPath($current_path);

  if ($alias === '/about' || $alias === '/contact') {
    $show_block = TRUE;
  }
}

if (!$show_block) {
  return [];
}

    $site_branding = Settings::get('site_branding', []);
    $primary_color = $site_branding['primary_color'] ?? '#ff4949';
    $logo_url = $site_branding['menu_logo_image'] ?? NULL;

    if (!$logo_url) {
      $theme_path = \Drupal::theme()->getActiveTheme()->getPath();
      $logo_url = base_path() . $theme_path . '/images/dacem-imago.png';
    }

 
    $language_manager = \Drupal::service('language_manager');
    $languages = $language_manager->getLanguages();
    $switch_links = [];

    
    $language_manager = \Drupal::service('language_manager');
    //$current_language = $language_manager->getCurrentLanguage()->getId();
    $current_language = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    $language_options = '';
    $flags = [
      'en' => '🇬🇧',
      'es' => '🇪🇸', 
      'pt-pt' => '🇵🇹', 
      'fr' => '🇫🇷', 
      'it' => '🇮🇹',
      'de' => '🇩🇪',
      'lt' => '🇱🇹',
      'pl' => '🇵🇱',
      'el' => '🇬🇷',
      'cs' => '🇨🇿',
      'sl' => '🇸🇮', 
      'hu' => '🇭🇺', 
      'et' => '🇪🇪', 
      'gl' => '🇪🇸', 
    ];

    foreach ($languages as $language) {
      $langcode = $language->getId();
      $abbreviation = strtoupper($langcode); 

      //$url = Url::fromRoute('<current>', [], ['language' => $language]);
      $url = Url::fromRoute('<current>', [], ['language' => $language])->toString();

      $flag = $flags[$langcode] ?? '';

      //Language menu
      $language_options .= '
            <li>
              <a class="dropdown-item" href="' . $url . '">' . $flag . ' ' . $abbreviation . '</a>
            </li>';

    }

    $current_language_object = $language_manager->getLanguage($current_language);
    $language_options_url = $current_language_object ? ['language' => $current_language_object] : [];
    $main_page_url = Url::fromRoute('view.main_page.page_1', [], $language_options_url)->toString();
    $institutions_url = Url::fromRoute('view.main_page.page_2', [], $language_options_url)->toString();
    $alliances_url = Url::fromRoute('view.alliances.page_1', [], $language_options_url)->toString();
    $about_url = Url::fromUserInput('/about', $language_options_url)->toString();
    $contact_url = Url::fromUserInput('/contact', $language_options_url)->toString();

    $alliance_count = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'alliance')
      ->condition('status', 1)
      ->count()
      ->execute();

    $alliances_menu_item = '';
    if ($alliance_count > 1) {
      $alliances_menu_item = '<li class="nav-item dacem-menu-item"><a class="nav-link" href="'
        . $alliances_url
        . '">'
        . $this->t('ALLIANCES')
        . '</a></li>';
    }

    // Obtain the current user to display their profile picture
    $current_user = \Drupal::currentUser();
    $profile_picture_url = '/themes/custom/b5subtheme/images/default-profile.jpg';

    // Verificar si el usuario no es anónimo
    if ($current_user->isAuthenticated() && $current_user->id() != 0) {
      //dump($current_user->id());      // Cargar la entidad del usuario
      $user = User::load($current_user->id());

      // Verify if has profile picture
      if ($user->hasField('user_picture') && !$user->get('user_picture')->isEmpty()) {
        $file = File::load($user->get('user_picture')->target_id);
        if ($file) {
          $profile_picture_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        }
      }

      // If no image, set default image
      if (!$profile_picture_url) {
        $profile_picture_url = '/themes/custom/b5subtheme/images/default-profile.jpg';
      }

      
      $profile_html = '
      <div class="user-profile-container dropdown ms-3">
          <a href="#" id="userProfileDropdown" class="dropdown-toggle user-profile-link" data-bs-toggle="dropdown" aria-expanded="false">
              <img src="' . $profile_picture_url . '" alt="Profile Picture" class="user-profile-circle">
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userProfileDropdown">
              <li><a class="dropdown-item user-menu-item" href="/user">My Profile</a></li>
              <li><a class="dropdown-item user-menu-item" href="/my-area">My Area</a></li>
              <li><a class="dropdown-item user-menu-item" href="/user/logout">Logout</a></li>
          </ul>
      </div>';

    } else {
      // If the user is annonymous, dont render the image
      $profile_html = '';
    }




    return [
  '#markup' => $this->t('
    <nav class="navbar sticky-top navbar-expand-lg university-navbar" style="--university_primary_color: @site_primary_color; --university_emphasis_text_color: #fff;">
      <div class="container-fluid">
          <a class="navbar-brand" href="@main_page_url">
              <img src="@menu_image_url" alt="DACEM Logo" class="menu-logo d-inline-block align-text-top">
          </a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#universityNavbar" aria-controls="universityNavbar" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse justify-content-end" id="universityNavbar">
              <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                  <li class="nav-item dacem-menu-item"><a class="nav-link" href="@about_url">' . $this->t('ABOUT') . '</a></li>
                  <li class="nav-item dacem-menu-item"><a class="nav-link" href="@institutions_url">' . $this->t('INSTITUTIONS') . '</a></li>
                  ' . $alliances_menu_item . '
                  <li class="nav-item dacem-menu-item"><a class="nav-link" href="@contact_url">' . $this->t('CONTACT') . '</a></li>
              </ul>
              <div class="d-flex ms-lg-2 right-buttons-university-menu">
                  <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                      <a class="nav-link dropdown-toggle no-hover-bg" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                      ' . strtoupper($current_language) . ' ' . $flags[$current_language] . '
                      </a>
                      <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                        ' . $language_options . '
                      </ul>
                    </li>
                  </ul>
                  ' . $profile_html . '
              </div>
          </div>
      </div>
    </nav>',
    [
      '@main_page_url' => $main_page_url,
      '@about_url' => $about_url,
      '@institutions_url' => $institutions_url,
      '@contact_url' => $contact_url,
      '@menu_image_url' => $logo_url,
      '@site_primary_color' => $primary_color,
    ]
  ),
];

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
