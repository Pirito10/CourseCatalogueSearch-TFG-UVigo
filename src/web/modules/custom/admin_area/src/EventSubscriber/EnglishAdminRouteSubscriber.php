<?php

namespace Drupal\admin_area\EventSubscriber;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the custom administration area in English.
 */
class EnglishAdminRouteSubscriber implements EventSubscriberInterface {

  /**
   * Constructs an EnglishAdminRouteSubscriber object.
   */
  public function __construct(
    protected LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Redirects custom admin routes to their English URL.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');
    if (!$route_name || !$this->isCustomAdminRoute($route_name)) {
      return;
    }

    $current_language = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE);
    if ($current_language->getId() === 'en') {
      return;
    }

    $english = $this->languageManager->getLanguage('en');
    if (!$english) {
      return;
    }

    $raw_variables = $request->attributes->get('_raw_variables');
    $route_parameters = $raw_variables ? $raw_variables->all() : ($request->attributes->get('_route_params') ?? []);
    $url = Url::fromRoute($route_name, $route_parameters, [
      'language' => $english,
      'query' => $request->query->all(),
    ])->toString();

    $event->setResponse(new RedirectResponse($url));
  }

  /**
   * Returns TRUE for routes owned by the custom administration area.
   */
  protected function isCustomAdminRoute(string $route_name): bool {
    return str_starts_with($route_name, 'admin_area.')
      || str_starts_with($route_name, 'view.admin_')
      || $route_name === 'create_user_group.create_user_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after routing has populated the current route attributes.
    $events[KernelEvents::REQUEST][] = ['onRequest', 20];
    return $events;
  }

}
