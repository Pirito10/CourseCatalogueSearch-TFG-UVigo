<?php

namespace Drupal\euf_group_autojoin\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\ewp_institutions_user\Event\SetUserInstitutionEvent;
use Drupal\ewp_institutions_user\Event\UserInstitutionChangeEvent;

/**
 * Auto-joins a user to the institution Group after HEI is set,
 * and removes memberships from previous institution Groups.
 */
class EufInstitutionSubscriber implements EventSubscriberInterface {

  // Group bundle to attach users to.
  private const GROUP_TYPE      = 'universitytypegroup';
  // Group field (entity reference to HEI).
  private const GROUP_HEI_FIELD = 'field_institution_profile';
  // User base field that stores the HEI reference.
  private const USER_HEI_FIELD  = 'user_institution';

  public function __construct(
    protected EntityTypeManagerInterface $etm,
  ) {}

  /**
   * Run after the core EUF handlers.
   */
  public static function getSubscribedEvents(): array {
    return [
      SetUserInstitutionEvent::EVENT_NAME     => ['onSetUserInstitution', -1000],
      UserInstitutionChangeEvent::EVENT_NAME  => ['onUserInstitutionChange', -1000],
    ];
  }

  public function onSetUserInstitution(SetUserInstitutionEvent $event): void {
    $user = $event->user ?? null;
    if ($user instanceof UserInterface) {
      $this->syncInstitutionMemberships($user);
    }
  }

  public function onUserInstitutionChange(UserInstitutionChangeEvent $event): void {
    $user = $event->user ?? null;
    if ($user instanceof UserInterface) {
      $this->syncInstitutionMemberships($user);
    }
  }

  /**
   * Ensure the user belongs only to the Group of their current HEI.
   * If HEI is empty, remove the user from all institution Groups.
   */
  protected function syncInstitutionMemberships(UserInterface $user): void {
    $hei_id = $this->getHeiFromUser($user);

    if ($hei_id) {
      // Join the correct Group for the current HEI.
      $this->joinUserToHeiGroup($user, $hei_id);
      // Remove memberships from other institution Groups (different HEI).
      $this->pruneOtherInstitutionGroups($user, $hei_id);
    }
    else {
      // No HEI on user: remove from all institution Groups.
      $this->pruneOtherInstitutionGroups($user, null);
    }
  }

  /**
   * Return HEI id from the user's base field, or null if empty.
   */
  protected function getHeiFromUser(UserInterface $user): ?string {
    if ($user->hasField(self::USER_HEI_FIELD) && !$user->get(self::USER_HEI_FIELD)->isEmpty()) {
      $val = $user->get(self::USER_HEI_FIELD)->target_id ?? null;
      return $val !== null ? (string) $val : null;
    }
    return null;
  }

  /**
   * Add the user to the Group that references the given HEI (idempotent).
   */
  protected function joinUserToHeiGroup(UserInterface $user, string $hei_id): void {
    $gids = \Drupal::entityQuery('group')
      ->accessCheck(FALSE)
      ->condition('type', self::GROUP_TYPE)
      ->condition(self::GROUP_HEI_FIELD . '.target_id', $hei_id)
      ->range(0, 1)
      ->execute();

    if (empty($gids)) {
      return;
    }

    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = $this->etm->getStorage('group')->load(reset($gids));
    if (!$group instanceof GroupInterface) {
      return;
    }

    if ($group->getMember($user)) {
      return; // already a member (idempotent)
    }

    $group->addMember($user);
  }

  /**
   * Remove the user from institution Groups not matching $keep_hei_id.
   * If $keep_hei_id is null, remove from all institution Groups.
   */
  protected function pruneOtherInstitutionGroups(UserInterface $user, ?string $keep_hei_id): void {
    /** @var \Drupal\group\GroupMembershipLoaderInterface $loader */
    $loader = \Drupal::service('group.membership_loader');
    $memberships = $loader->loadByUser($user);

    foreach ($memberships as $membership) {
      /** @var \Drupal\group\Entity\GroupInterface $group */
      $group = $membership->getGroup();
      if (!$group instanceof GroupInterface) {
        continue;
      }
      if ($group->bundle() !== self::GROUP_TYPE) {
        continue; // only touch institution Groups
      }
      if (!$group->hasField(self::GROUP_HEI_FIELD) || $group->get(self::GROUP_HEI_FIELD)->isEmpty()) {
        continue; // no HEI reference on the Group
      }

      $group_hei = (string) ($group->get(self::GROUP_HEI_FIELD)->target_id ?? '');
      $should_remove =
        ($keep_hei_id === null) || // remove all if no HEI on user
        ($group_hei !== '' && $group_hei !== $keep_hei_id);

      if ($should_remove) {
        $group->removeMember($user);
      }
    }
  }
}
