<?php


namespace Drupal\create_user_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\group\Entity\Group;

/**
 * Formulario para crear un usuario y agregarlo a un grupo.
 */
class CreateUserInGroupForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'create_user_in_group_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Obtener el grupo desde la URL.
    $group = \Drupal::routeMatch()->getParameter('group');
    
    // Verificar que estamos en un grupo válido.
    if (!$group || !$group instanceof Group) {
      return ['#markup' => $this->t('Invalid group.')];
    }

    // Obtener el usuario actual.
    $current_user = \Drupal::currentUser();

    // Cargar la membresía del usuario en el grupo.
    $membership = \Drupal::service('group.membership_loader')->load($group, $current_user);

    // Verificar si la membresía existe y obtener sus roles.
    if ($membership) {
      $roles = $membership->getRoles();
      
      // Comprobar si el usuario tiene el rol "university_admin".
      foreach ($roles as $role) {
        //\Drupal::messenger()->addMessage('Roool: ' . $role->id());
        if ($role->id() == 'universitytypegroup-university_a') {
          // Permitir acceso al formulario si tiene el rol "university_admin".
          $form['username'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Username'),
            '#required' => TRUE,
          ];

          $form['email'] = [
            '#type' => 'email',
            '#title' => $this->t('Email'),
            '#required' => TRUE,
          ];

          $form['password'] = [
            '#type' => 'password',
            '#title' => $this->t('Password'),
            '#required' => TRUE,
          ];

          $form['role'] = [
            '#type' => 'select',
            '#title' => $this->t('Role'),
            '#options' => [
              'university_a' => $this->t('Higher Education Institution (HEI) Administrator'),
              'campus_edito' => $this->t('Campus Editor'),
              'ou_administr' => $this->t('Organizational Unit (OU) Administrator'),
              'degree_admin' => $this->t('Programme Administrator'),
              'subject_admi' => $this->t('Course Editor'),
               
            ],
            '#required' => TRUE,
          ];
          

          $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Create User and Add to Group'),
          ];

          return $form;
        }
      }
    }

    // Si el usuario no tiene permisos, mostrar un mensaje de error.
    return ['#markup' => $this->t('You do not have permission to create users in this group.')];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Obtener los valores del formulario.
    $username = $form_state->getValue('username');
    $email = $form_state->getValue('email');
    $password = $form_state->getValue('password');
    $role = $form_state->getValue('role');

    // Crear el usuario.
    $user = User::create([
      'name' => $username,
      'mail' => $email,
      'pass' => $password,
      'status' => 1,
    ]);
    $user->save();


    // Obtener el grupo actual desde la URL.
    $group = \Drupal::routeMatch()->getParameter('group');
    if ($group instanceof Group) {
      // Añadir el usuario al grupo con un rol específico.
      $group->addMember($user, ['group_roles' => ['universitytypegroup-'.$role]]);
    }

    // Mostrar mensaje de confirmación.
    //\Drupal::messenger()->addMessage($this->t('User %username has been created and added to the group.', ['%username' => $user->getAccountName()]));

    $destination = \Drupal::request()->query->get('destination');

    if ($destination) {
      // Redirigir a la página desde donde se inició el proceso de creación del usuario.
      $form_state->setRedirectUrl(\Drupal\Core\Url::fromUserInput($destination));
    } else {
      // Si no se proporciona un destino, redirigir a la página de miembros del grupo.
      $group = \Drupal::routeMatch()->getParameter('group');
      $form_state->setRedirect('view.group_members.page_1', ['group' => $group->id()]);
    }
    
  }


  /**
 * {@inheritdoc}
 */
public function validateForm(array &$form, FormStateInterface $form_state) {
  $username = $form_state->getValue('username');
  $email = $form_state->getValue('email');

  // Verificar si el nombre de usuario ya existe.
  if (user_load_by_name($username)) {
    $form_state->setErrorByName('username', $this->t('The username %name is already taken.', ['%name' => $username]));
  }

  // Verificar si el correo electrónico ya está en uso.
  if (user_load_by_mail($email)) {
    $form_state->setErrorByName('email', $this->t('The email %email is already registered.', ['%email' => $email]));
  }
}



}
