<?php

namespace Drupal\dacem_footer\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Site\Settings;
use Drupal\dacem_footer\Form\DacemFooterFeedbackForm;

/**
 * Provides the DACEM footer block.
 *
 * @Block(
 *   id = "dacem_footer_block",
 *   admin_label = @Translation("DACEM Footer Block"),
 * )
 */
class DacemFooterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $routeMatch;
  protected $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  protected function getNodeFromAlias($alias, $langcode = NULL)
  {
    if (preg_match('/^\/(\d+)$/', $alias, $matches)) {
      $node = \Drupal\node\Entity\Node::load((int) $matches[1]);

      if (!$node) {
        return NULL;
      }

      $translated = $node;
      if ($langcode && $node->hasTranslation($langcode)) {
        $translated = $node->getTranslation($langcode);
      }

      return [
        'original' => $node,
        'translated' => $translated,
      ];
    }

    $alias_manager = \Drupal::service('path_alias.manager');
    $internal_path = $alias_manager->getPathByAlias($alias, $langcode);

    if (preg_match('/^\/node\/(\d+)$/', $internal_path, $matches)) {
      $node = \Drupal\node\Entity\Node::load((int) $matches[1]);

      $translated = $node;
      if ($langcode && $node->hasTranslation($langcode)) {
        $translated = $node->getTranslation($langcode);
      }



      // Devuelve ambas
      return [
        'original' => $node,
        'translated' => $translated,
      ];
    }

    return NULL;
  }






  public function build() {

  $institution = NULL;
  $site_branding = Settings::get('site_branding', []);
  $primary_color = $site_branding['primary_color'] ?? '#ff4949';
  $last_updated = NULL;
  $owner = NULL;
  $nodebundle = NULL;
  $hide_black_band = FALSE;

  // Idioma de contenido actual.
  $langcode = \Drupal::languageManager()
    ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
    ->getId();

  /**
   * 1) INSTITUTION (para logo y color)
   */
  $institution_machine = $this->routeMatch->getParameter('arg_0');
  if ($institution_machine) {
    if (is_numeric($institution_machine)) {
      $original_institution = \Drupal\node\Entity\Node::load((int) $institution_machine);
      $institution = $original_institution;

      if ($original_institution && $langcode && $original_institution->hasTranslation($langcode)) {
        $institution = $original_institution->getTranslation($langcode);
      }
    }
    else {
      $institution_data = $this->getNodeFromAlias('/' . $institution_machine, $langcode);

      $institution = $institution_data ? $institution_data['translated'] : NULL;
      $original_institution = $institution_data ? $institution_data['original'] : NULL;
    }

    if ($original_institution && $original_institution->hasField('field_primary_color') && !$original_institution->get('field_primary_color')->isEmpty()) {
      $primary_color = $original_institution->get('field_primary_color')->first()->color;
    }
  }

  /**
   * 2) NODO "ACTUAL" PARA last_updated / nodebundle
   */
  $current_path = \Drupal::service('path.current')->getPath(); // p.ej. /es/resources-and-services/uvigo
  $parts = explode('/', trim($current_path, '/'));             // ['es', 'resources-and-services', 'uvigo']

  $route_name = $this->routeMatch->getRouteName();
  $black_band_hidden_routes = [
    'view.main_page.page_1',
    'view.institution_new_catalogue.page_1',
    'view.institution_new_catalogue.page_2',
    'view.search_programme.page_1',
    'view.search_iec.page_1',
  ];
  $hide_black_band = in_array($route_name, $black_band_hidden_routes, TRUE);

  // --- NUEVO: eliminar prefijo de idioma si existe (en, es, etc.) ---
  if (!empty($parts)) {
    $lang_prefixes = ['en', 'es']; // Ajusta si añades más idiomas tipo 'fr', 'pt', etc.
    if (in_array($parts[0], $lang_prefixes, TRUE)) {
      array_shift($parts); // ahora ['resources-and-services', 'uvigo']
    }
  }

  $alias = NULL;

  if (!empty($parts)) {
    $first = $parts[0];

    // /catalogue/...
    if ($first === 'catalogue') {
      // /catalogue/institution_name
      if (isset($parts[1]) && !isset($parts[2])) {
        $alias = '/' . $parts[1]; // Institution
      }
      // /catalogue/institution_name/programmes or /catalogue/institution_name/iecs
      elseif (isset($parts[1], $parts[2]) && in_array($parts[2], ['programmes', 'iecs'], TRUE) && !isset($parts[3])) {
        $alias = '/' . $parts[1]; // Institution
      }
      // /catalogue/institution_name/programme_name
      elseif (isset($parts[1]) && isset($parts[2]) && !isset($parts[3])) {
        $alias = '/' . $parts[1] . '/' . $parts[2]; // Programme o Campus
      }
      // /catalogue/institution_name/programme_name/iec_name
      elseif (isset($parts[1]) && isset($parts[2]) && isset($parts[3])) {
        $alias = '/' . $parts[1] . '/' . $parts[2] . '/' . $parts[3]; // IEC
      }
    }

    // /general-information/institution_name
    elseif ($first === 'general-information') {
      if (isset($parts[1])) {
        $alias = '/' . $parts[1]; // Institution
      }
    }

    // /campus-information/institution_name/campus_name
    elseif ($first === 'campus-information') {
      if (isset($parts[1]) && isset($parts[2])) {
        $alias = '/' . $parts[1] . '/' . $parts[2]; // Campus
      }
    }

    // /organizational-unit-information/institution_name/organizational_unit_name
    elseif ($first === 'organizational-unit-information') {
      if (isset($parts[1]) && isset($parts[2])) {
        $alias = '/' . $parts[1] . '/' . $parts[2]; // Organizational Unit
      }
    }

    // /resources-and-services/...
    elseif ($first === 'resources-and-services') {
      // Alias de resources_and_services:
      //  /rs/Institution
      //  /rs/Institution/Campus

      // /resources-and-services/institution_name
      if (isset($parts[1]) && !isset($parts[2])) {
        $alias = '/rs/' . $parts[1]; // RS de institución
      }
      // /resources-and-services/institution_name/campus_name
      elseif (isset($parts[1]) && isset($parts[2])) {
        $alias = '/rs/' . $parts[1] . '/' . $parts[2]; // RS de campus
      }
    }
  }

  if ($alias) {
    $node_data = $this->getNodeFromAlias($alias, $langcode);

    if ($node_data && !empty($node_data['translated'])) {
      $current_entity = $node_data['translated'];

      // Fecha de última actualización.
      $changed = $current_entity->getChangedTime();
      $last_updated = \Drupal::service('date.formatter')
        ->format($changed, 'custom', 'd/m/Y');
      $owner_id = $current_entity->getOwnerId();
      if ($owner_id) {
        $owner_account = $this->entityTypeManager->getStorage('user')->load($owner_id);
        $owner = $owner_account ? $owner_account->getDisplayName() : NULL;
      }

      // Bundle (institution, programme, campus, individual_educational_component,
      // organizational_unit, resources_and_services, etc.).
      $nodebundle = $current_entity->bundle();
    }
  }

  return [
    '#theme'         => 'dacem_footer_block',
    '#institution'   => $institution,
    '#primary_color' => $primary_color,
    '#last_updated'  => $last_updated,
    '#owner'  => $owner,
    '#nodebundle'    => $nodebundle,
    '#feedback_form' => \Drupal::formBuilder()->getForm(DacemFooterFeedbackForm::class),
    '#hide_black_band' => $hide_black_band,
  ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }


}
