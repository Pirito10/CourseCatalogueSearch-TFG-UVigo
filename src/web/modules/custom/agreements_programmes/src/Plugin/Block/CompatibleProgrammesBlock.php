<?php

namespace Drupal\agreements_programmes\Plugin\Block;

use Drupal\agreements_programmes\Service\AgreementProgrammeMatcher;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a compatible programmes block.
 *
 * @Block(
 *   id = "compatible_programmes_block",
 *   admin_label = @Translation("Compatible programmes by agreements")
 * )
 */
final class CompatibleProgrammesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AgreementProgrammeMatcher $matcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('agreements_programmes.matcher'),
    );
  }

  public function build(): array {
    $node = $this->routeMatch->getParameter('node');

    if (!$node || $node->bundle() !== 'programme') {
      return [];
    }

    $results = $this->matcher->getCompatibleProgrammesForProgramme($node);

    if (!$results) {
      return [
        '#markup' => $this->t('No compatible programmes found.'),
      ];
    }

    $items = [];

    foreach ($results as $result) {
      $programme = $result['programme'];

      $items[] = [
        '#type' => 'link',
        '#title' => $programme->label(),
        '#url' => $programme->toUrl(),
      ];
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Compatible programmes'),
      '#items' => $items,
      '#cache' => [
        'contexts' => ['route'],
        'tags' => ['node_list:agreement', 'node_list:programme'],
      ],
    ];
  }

}
