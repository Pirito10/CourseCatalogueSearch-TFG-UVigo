<?php

namespace Drupal\university_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\user\Entity\User;
use Drupal\file\Entity\File;



/**
 * Provides a 'University Menu' Block.
 *
 * @Block(
 *   id = "university_menu_block",
 *   admin_label = @Translation("University Menu Block"),
 *   category = @Translation("Custom")
 * )
 */
class UniversityMenuBlock extends BlockBase
{



  /**
   * {@inheritdoc}
   */
  public function build()
  {
    $translations = [
      'en' => [
        'INSTITUTIONAL INFORMATION' => 'INSTITUTIONAL INFORMATION',
        'CATALOGUE' => 'CATALOGUE',
        'RESOURCES AND SERVICES' => 'RESOURCES AND SERVICES',
        'UNIVERSITY LIFE' => 'UNIVERSITY LIFE',
      ],
      'es' => [
        'INSTITUTIONAL INFORMATION' => 'INFORMACIÓN INSTITUCIONAL',
        'CATALOGUE' => 'CATÁLOGO',
        'RESOURCES AND SERVICES' => 'RECURSOS Y SERVICIOS',
        'UNIVERSITY LIFE' => 'VIDA UNIVERSITARIA',
      ],
      'fr' => [
        'INSTITUTIONAL INFORMATION' => 'INFORMATIONS INSTITUTIONNELLES',
        'CATALOGUE' => 'CATALOGUE',
        'RESOURCES AND SERVICES' => 'RESSOURCES ET SERVICES',
        'UNIVERSITY LIFE' => 'VIE UNIVERSITAIRE',
      ],
      'pt-pt' => [
        'INSTITUTIONAL INFORMATION' => 'INFORMAÇÃO INSTITUCIONAL',
        'CATALOGUE' => 'CATÁLOGO',
        'RESOURCES AND SERVICES' => 'RECURSOS E SERVIÇOS',
        'UNIVERSITY LIFE' => 'VIDA UNIVERSITÁRIA',
      ],
      'gl' => [
        'INSTITUTIONAL INFORMATION' => 'INFORMACIÓN INSTITUCIONAL',
        'CATALOGUE' => 'CATÁLOGO',
        'RESOURCES AND SERVICES' => 'RECURSOS E SERVIZOS',
        'UNIVERSITY LIFE' => 'VIDA UNIVERSITARIA',
      ],
      'hu' => [
        'INSTITUTIONAL INFORMATION' => 'INTÉZMÉNYI INFORMÁCIÓK',
        'CATALOGUE' => 'KATALÓGUS',
        'RESOURCES AND SERVICES' => 'ERŐFORRÁSOK ÉS SZOLGÁLTATÁSOK',
        'UNIVERSITY LIFE' => 'EGYETEMI ÉLET',
      ],
      'sl' => [
        'INSTITUTIONAL INFORMATION' => 'INSTITUCIONALNE INFORMACIJE',
        'CATALOGUE' => 'KATALOG',
        'RESOURCES AND SERVICES' => 'VIRI IN STORITVE',
        'UNIVERSITY LIFE' => 'UNIVERZITETNO ŽIVLJENJE',
      ],
      'et' => [
        'INSTITUTIONAL INFORMATION' => 'ASUTUSE INFO',
        'CATALOGUE' => 'KATALOOG',
        'RESOURCES AND SERVICES' => 'RESSURSID JA TEENUSED',
        'UNIVERSITY LIFE' => 'ÜLIKOOLIELU',
      ],
      'el' => [
        'INSTITUTIONAL INFORMATION' => 'ΘΕΣΜΙΚΕΣ ΠΛΗΡΟΦΟΡΙΕΣ',
        'CATALOGUE' => 'ΚΑΤΑΛΟΓΟΣ',
        'RESOURCES AND SERVICES' => 'ΠΟΡΟΙ ΚΑΙ ΥΠΗΡΕΣΙΕΣ',
        'UNIVERSITY LIFE' => 'ΦΟΙΤΗΤΙΚΗ ΖΩΗ',
      ],

      // Agrega otros idiomas si es necesario
    ];




    $build = [];
    $current_node = \Drupal::routeMatch()->getParameter('node');

    if ($current_node instanceof NodeInterface) {
     
      $node_type = $current_node->bundle();
      $university = null;

      if ($node_type == 'universidad') {
        $university = $current_node;
      } elseif ($node_type == 'carrera') {
        $university = $current_node->get('field_universidad')->entity;
      } elseif ($node_type == 'asignatura') {
        $carrera = $current_node->get('field_carrera')->entity;
        if ($carrera) {
          $university = $carrera->get('field_universidad')->entity;
        }
      }

    } else {

   

      $current_route = \Drupal::routeMatch()->getRouteName();
      

      if ($current_route === 'view.general_information.page_1') {
        // Obtén el ID de la universidad desde el argumento de la URL.
        $university_id = \Drupal::routeMatch()->getParameter('arg_0');
        $university = \Drupal\node\Entity\Node::load($university_id);
      } elseif ($current_route === 'view.detalles_de_universidad.page_1') {
        // Si la ruta es de la vista, obtén la universidad desde el argumento.
        $university_id = \Drupal::routeMatch()->getParameter('arg_0');
        if ($university_id) {
          $university = \Drupal\node\Entity\Node::load($university_id);
        }
      }elseif ($current_route === 'view.resources_and_services.page_1') {
        $university_id = \Drupal::routeMatch()->getParameter('arg_0');
        $university = \Drupal\node\Entity\Node::load($university_id);
        }
    
    }



    if (!empty($university)) {



      $logo_url = '';
      
      if (!$university->get('field_logo')->isEmpty()) {
        $media = $university->get('field_logo')->entity;
        if ($media && $media->hasField('field_media_image')) {
          $file = $media->get('field_media_image')->entity;
          if ($file) {
            $logo_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          }
        }
      }



      if ($university instanceof NodeInterface && $university->hasField('field_primary_color') && !$university->get('field_primary_color')->isEmpty()) {
        
        $color_value = $university->get('field_primary_color')->value;
        
      } else {
        
      }

      // Obtener el idioma actual
      $language_manager = \Drupal::service('language_manager');
      //$current_language = $language_manager->getCurrentLanguage()->getId();
      $current_language = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

      // Generar la URL de la universidad en el idioma actual
      $university_url = $university->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage($current_language)])->toString();

      // Generar URLs de cambio de idioma
      $languages = $language_manager->getLanguages();
      $language_options = '';
     
      $flags = [
        'en' => '🇬🇧', // Inglés
        'es' => '🇪🇸', // Español
        'pt-pt' => '🇵🇹', // Portugués
        'fr' => '🇫🇷', // Francés
        'el' => '🇬🇷', // Griego
        'cs' => '🇨🇿', // Checo
        'sl' => '🇸🇮', // Esloveno
        'hu' => '🇭🇺', // Húngaro
        'et' => '🇪🇪', // Estonio
        'gl' => '🇪🇸', // Gallego
      ];

      foreach ($languages as $language) {
        $langcode = $language->getId();
        $abbreviation = strtoupper($langcode); // Convertir el código del idioma a mayúsculas

        //$url = Url::fromRoute('<current>', [], ['language' => $language]);
        $url = Url::fromRoute('<current>', [], ['language' => $language])->toString();

        $flag = $flags[$langcode] ?? ''; // Asegurarse de tener un icono

        $language_options .= '
              <li>
                <a class="dropdown-item" href="' . $url . '">' . $flag . ' ' . $abbreviation . '</a>
              </li>';

      }


      // Genera la URL con el idioma activo.
      $general_info_url = Url::fromRoute('view.general_information.page_1', [
        'arg_0' => $university->id(),
      ], [
        'language' => \Drupal::languageManager()->getLanguage($current_language),
      ])->toString();


      $alias_manager = \Drupal::service('path_alias.manager');
      // Obtén el alias del nodo en el idioma actual. Niste caso é asi porque temos que para que mostre unha universidad se mostre desta forma directamente.
      $catalogue_url = $alias_manager->getAliasByPath('/node/' . $university->id(), \Drupal::languageManager()->getCurrentLanguage()->getId());

      
      // Genera la URL con el idioma activo.
      $rs_url = Url::fromRoute('view.resources_and_services.page_1', [
        'arg_0' => $university->id(),
      ], [
        'language' => \Drupal::languageManager()->getLanguage($current_language),
      ])->toString();







      // Imagen y menú desplegable de usuario
      $current_user = \Drupal::currentUser();
      if ($current_user->isAuthenticated() && $current_user->id() != 0) {
        $profile_picture_url = '/themes/custom/b5subtheme/images/default-profile.jpg';
        //dump($current_user->id());      // Cargar la entidad del usuario
        $user = User::load($current_user->id());
  
        // Verificar si tiene una foto de perfil
        if ($user->hasField('user_picture') && !$user->get('user_picture')->isEmpty()) {
          $file = File::load($user->get('user_picture')->target_id);
          if ($file) {
            $profile_picture_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          }
        }
  
        // Si no hay imagen, asignar una imagen predeterminada
        if (!$profile_picture_url) {
          $profile_picture_url = '/themes/custom/b5subtheme/images/default-profile.jpg';
        }
  
        // Generar el HTML de la imagen
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
        // Si el usuario es anónimo, no se renderiza la imagen
        $profile_html = '';
      }








      $build = [
        '#markup' => $this->t('
              <nav class="navbar navbar-expand-lg university-navbar" style="margin: 0; padding: 0;">
                  <div class="container-fluid">
                      <!-- Logo -->
                      <a class="navbar-brand" href="' . $general_info_url . '">
                          <img src="@logo_url" alt="@university_name" class="university-logo d-inline-block align-text-top">
                      </a>
                      
                      <!-- Botón de colapso para móviles -->
                      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#universityNavbar" aria-controls="universityNavbar" aria-expanded="false" aria-label="Toggle navigation">
                          <span class="navbar-toggler-icon"></span>
                      </button>
                      
                      <!-- Menú colapsable -->
                      <div class="collapse navbar-collapse" id="universityNavbar">
                          <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                              <li class="nav-item">
                                  <a class="nav-link" href="' . $general_info_url . '">' . $translations[$current_language]['INSTITUTIONAL INFORMATION'] . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="' . $catalogue_url . '">' . $translations[$current_language]['CATALOGUE'] . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="' . $rs_url . '">' . $translations[$current_language]['RESOURCES AND SERVICES'] . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="/@university_path/vida-universitaria">' . $translations[$current_language]['UNIVERSITY LIFE'] . '</a>
                              </li>
                          </ul>
                          
                          <!-- Botón personalizado -->
                          <div class="d-flex align-items-center right-buttons-university-menu">
                              <a href="/" class="btn btn-outline-primary me-2 back-button">
                                  <strong>DACEM</strong>
                              </a>
                              
                              <!-- Botones de idioma -->
                              <div class="language-buttons" style="position: relative; z-index: 1050;">
                                  <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                                      <li class="nav-item dropdown">
                                          <a class="nav-link dropdown-toggle no-hover-bg" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            ' . strtoupper($current_language) . ' ' . $flags[$current_language] . '
                                          </a>
                                          <ul class="dropdown-menu" style="z-index: 1051;" aria-labelledby="languageDropdown">
                                            ' . $language_options . '
                                          </ul>
                                      </li>
                                  </ul>
                              </div>
                              ' . $profile_html . '
                          </div>
                      </div>
                  </div>
              </nav>',
            [
              '@logo_url' => $logo_url,
              '@university_name' => $university->getTitle(),
              '@university_path' => $university->toUrl()->getInternalPath(),
              '@university_url' => $university_url,
            ]
        ),
    ];
    




    }

    return $build;
  }
}
