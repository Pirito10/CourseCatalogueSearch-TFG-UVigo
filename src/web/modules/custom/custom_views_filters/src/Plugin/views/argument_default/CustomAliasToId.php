<?php

namespace Drupal\custom_views_filters\Plugin\views\argument_default;

use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;

/**
 * Provides a default argument based on alias-to-ID logic.
 *
 * @ViewsArgumentDefault(
 *   id = "custom_alias_to_id",
 *   title = @Translation("Custom Alias to ID")
 * )
 */
class CustomAliasToId extends ArgumentDefaultPluginBase {
  public function getArgument() {
    \Drupal::logger('custom_views_filters')->notice('Executing CustomAliasToId plugin');
    
    $alias_manager = \Drupal::service('path_alias.manager');
    $current_path = \Drupal::service('path.current')->getPath();

    // 🔹 Extraer solo la parte final de la URL (nombre de la institución)
    $path_parts = explode('/', trim($current_path, '/'));
    
    // 📌 Asegurar que haya al menos dos partes en la URL (/catalogue/university-vigo)
    if (count($path_parts) < 2) {
        \Drupal::logger('custom_views_filters')->warning('URL format incorrect, returning NULL');
        return NULL;
    }

    // 📌 Tomar el último segmento como el alias de la institución
    $alias_name = end($path_parts);

    \Drupal::logger('custom_views_filters')->notice('Extracted alias: ' . $alias_name);

    // 🔹 Resolver el alias a un nodo
    $path = $alias_manager->getPathByAlias('/' . $alias_name);
    \Drupal::logger('custom_views_filters')->notice('Resolved Path: ' . $path);

    if (preg_match('/^\/node\/(\d+)$/', $path, $matches)) {
        \Drupal::logger('custom_views_filters')->notice('Converted alias to node ID: ' . $matches[1]);
        return (int) $matches[1];
    }

    \Drupal::logger('custom_views_filters')->warning('Alias conversion failed, returning NULL');
    return NULL;
}

}
