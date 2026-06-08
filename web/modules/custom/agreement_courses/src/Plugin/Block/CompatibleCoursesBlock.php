<?php

namespace Drupal\agreement_courses\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\agreement_courses\Service\AgreementCourseMatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a compatible courses block.
 *
 * @Block(
 *   id = "compatible_courses_block",
 *   admin_label = @Translation("Compatible courses by agreements")
 * )
 */
final class CompatibleCoursesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AgreementCourseMatcher $matcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('agreement_courses.matcher'),
    );
  }

  public function build(): array {
    $node = $this->routeMatch->getParameter('node');

    if (!$node || $node->bundle() !== 'programme') {
      return [];
    }

    $results = $this->matcher->getCompatibleCoursesForProgramme($node);

    if (!$results) {
      return [
        '#markup' => $this->t('No compatible courses found.'),
      ];
    }

    $items = [];

    foreach ($results as $result) {
      $course = $result['course'];

      $items[] = [
        '#type' => 'link',
        '#title' => $course->label(),
        '#url' => $course->toUrl(),
      ];
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Compatible courses'),
      '#items' => $items,
      '#cache' => [
        'contexts' => ['route'],
        'tags' => ['node_list:agreement', 'node_list:individual_educational_component', 'node_list:programme'],
      ],
    ];
  }

}
