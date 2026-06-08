<?php

namespace Drupal\create_user_group\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
use Drupal\Core\Access\AccessResult;

class CreateUserGroupAccessCheck {

  /**
   * Función de control de acceso.
   *
   * @param \Drupal\group\Entity\Group $group
   *   El grupo en el que se verifica el acceso.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   La cuenta del usuario actual que está intentando acceder.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   El resultado de la verificación de acceso.
   */
  public function access(Group $group, AccountInterface $account) {
    // Paso 1: Cargar la membresía del usuario actual en el grupo.
    $membership = \Drupal::service('group.membership_loader')->load($group, $account);

    // Verificar si la membresía del usuario en el grupo existe.
    if ($membership) {
      // Paso 2: Obtener los roles del usuario en el grupo.
      $roles = $membership->getRoles();

      // Paso 3: Iterar sobre los roles y comprobar si el usuario es "University Admin".
      foreach ($roles as $role) {
        //\Drupal::messenger()->addMessage('Error al crear la universidad: ' . $role->id());
        if ($role->id() == 'universitytypegroup-university_a') {
          // Paso 4: Si tiene el rol, se permite el acceso.
          return AccessResult::allowed();
        }
      }
    }

    // Paso 5: Si no tiene el rol "university_admin", denegar el acceso.
    return AccessResult::forbidden();
  }
}
