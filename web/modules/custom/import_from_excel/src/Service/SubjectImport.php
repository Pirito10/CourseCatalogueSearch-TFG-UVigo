<?php

namespace Drupal\import_from_excel\Service;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\group\Entity\Group;

class SubjectImport
{


  // Validación de campos de lista de texto.
  const VALID_COURSES_QUARTERS = [
    '1º' => '1o',
    '2º' => '2o',
    '3º' => '3o',
    '4º' => '4o',

  ];




  protected function getUniversityByName($university_name)
  {

    // Crear una consulta para buscar la universidad por su nombre (título).
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'universidad')  // Asumiendo que el tipo de nodo es "universidad".
      ->condition('title', $university_name)  // Buscar por título.
      ->accessCheck(FALSE)  // No verificar permisos de acceso.
      ->range(0, 1);  // Limitar la búsqueda a un resultado.

    $nids = $query->execute();  // Ejecutar la consulta.
    // Si encontramos la universidad, devolver su ID.
    if (!empty($nids)) {
      $nid = reset($nids);
      return \Drupal\node\Entity\Node::load($nid);  // Devolver el nodo de la universidad.
    }

    // Si no se encuentra la universidad, devolver NULL.
    return NULL;
  }





  protected function getDegreeByName($degree_name, $university_id)
  {

    // Crear una consulta para buscar la universidad por su nombre (título).
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'carrera')  // Asumiendo que el tipo de nodo es "carrera".
      ->condition('field_universidad', value: $university_id) // Buscar por universidad.
      ->condition('title', $degree_name)  // Buscar por título.
      ->accessCheck(FALSE)  // No verificar permisos de acceso.
      ->range(0, 1);  // Limitar la búsqueda a un resultado.

    $nids = $query->execute();  // Ejecutar la consulta.
    // Si encontramos la universidad, devolver su ID.
    if (!empty($nids)) {
      $nid = reset($nids);
      return \Drupal\node\Entity\Node::load($nid);  // Devolver el nodo de la universidad.
    }

    // Si no se encuentra la universidad, devolver NULL.
    return NULL;
  }

  protected function getSubjectByName($subject_name, $degree_id)
  {

    // Crear una consulta para buscar la universidad por su nombre (título).
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'asignatura')
      ->condition('field_carrera', value: $degree_id)
      ->condition('title', $subject_name)  // Buscar por título.
      ->accessCheck(FALSE)  // No verificar permisos de acceso.
      ->range(0, 1);  // Limitar la búsqueda a un resultado.

    $nids = $query->execute();  // Ejecutar la consulta.
    // Si encontramos la asignatura, devolver su ID.
    if (!empty($nids)) {
      $nid = reset($nids);
      return \Drupal\node\Entity\Node::load($nid);  // Devolver el nodo de la asignatura.
    }

    // Si no se encuentra la asignatura, devolver NULL.
    return NULL;
  }


  public function process($worksheet)
  {

    $header = [];  // Inicializar el array de encabezados.

    foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
      // Saltar la primera fila (encabezados).
      if ($rowIndex == 1) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE);
        foreach ($cellIterator as $cell) {
          $header[] = $cell->getValue(); // Almacenar los encabezados.
        }
        continue; // Saltar a la siguiente fila.
      }

      $cellIterator = $row->getCellIterator();
      $cellIterator->setIterateOnlyExistingCells(FALSE);
      $data = [];

      $headerCount = 0; // Contador para coincidir con las columnas del encabezado.

      foreach ($cellIterator as $cell) {
        if (isset($header[$headerCount])) {
          $headerValue = $header[$headerCount]; // Obtener el nombre de la columna.
          $data[$headerValue] = $cell->getValue(); // Asignar el valor a la clave correspondiente.
        }
        $headerCount++;
      }

      // Comprobar si los encabezados esperados están presentes en la fila.
      if (!isset($data['University'])) {
        \Drupal::messenger()->addError(t('La fila no contiene los datos requeridos.'));
        continue;
      }


      try {




        // Asignar cada columna a su respectivo campo.
        $university_name = $data['University'];
        $degree_name = $data['Degree'];
        $subject_name = $data['Subject'];
        $credits = $data['Credits'];
        $quarter = $data['Quarter'];
        $course = $data['Course'];
        $code = $data['Code'];
        $requirements = $data['Requirements']; // Campo de lista de texto.
        $contents = $data['Subject Contents']; // Campo de lista de texto.
        $evaluation = $data['Subject Evaluation'];
        $instructors = $data['Subject Instructors'];
        $introduction = $data['Subject Introduction']; // Campo de lista de texto.
        $language = $data['Subject Language'];
        $learning_outcomes = $data['Subject Learning Outcomes'];
        $modality = $data['Subject Modality'];
        $planned_activities = $data['Subject Planned Activities'];
        $recommendations = $data['Subject Recommendations'];
        $type = strtolower($data['Type']); // Campo de lista de texto.

        /*
        // Validar campos de lista de texto.
        if (!isset($valid_courses_quarters[$course]) && !isset($valid_courses_quarters[$quarter])) {
          \Drupal::messenger()->addError(\Drupal::translation()->translate('El curso @curso o el cuatrimestre @cuatrimestre no son válidos.', ['@curso' => $course, 'cuatrimestre' => $quarter]));
          continue;
        }
        */

        /*
  
        if (!isset($valid_modalitys[$modality])) {
          \Drupal::messenger()->addError($this->t('La modalidad @modality no es válida.', ['@modality' => $modality]));
          continue;
        }
        if (!isset($valid_levels[$level])) {
          \Drupal::messenger()->addError($this->t('El nivel @level no es válido.', ['@level' => $level]));
          continue;
        }
        if (!isset($valid_external_internships[$external_internships])) {
          \Drupal::messenger()->addError($this->t('El valor de prácticas profesionales @external_internships no es válido.', ['@external_internships' => $external_internships]));
          continue;
        }
  
        */

        $university = $this->getUniversityByName($university_name);
        $degree = $this->getDegreeByName($degree_name, $university->id());
        $subject = $this->getSubjectByName($subject_name, $degree->id());



        // Obtener el grupo de la universidad.
        $group_name = 'Group ' . $university_name;
        $query = \Drupal::entityQuery('group')
          ->condition('label', $group_name)
          ->condition('type', 'universitytypegroup')
          ->accessCheck(FALSE);

        $group_ids = $query->execute();
        if (empty($group_ids)) {
          \Drupal::messenger()->addError('No se encontró ningún grupo con ese nombre.');
          continue;
        }

        $group_id = reset($group_ids);
        $group = Group::load($group_id);
        if (!$group) {
          \Drupal::messenger()->addError('Error al cargar el grupo.');
          continue;
        }

        // Verificar si el usuario tiene permisos en el grupo.
        $current_user = \Drupal::currentUser();
        $user = User::load($current_user->id());
        $membership = $group->getMember($user);

        if ($membership) {
          // Obtener el ID del miembro y los roles que tiene en el grupo.
          $user_id = $membership->getUser()->id();
          $roles = $membership->getRoles();

          // Convertir los roles a una cadena de texto para imprimir.
          $roles_list = [];
          foreach ($roles as $role) {
            $roles_list[] = $role->label();
          }

          // Imprimir información sobre el usuario y sus roles.
          \Drupal::messenger()->addMessage('Usuario ID: ' . $user_id);
          \Drupal::messenger()->addMessage('Roles en el grupo: ' . implode(', ', $roles_list));
        } else {
          \Drupal::messenger()->addMessage('El usuario no pertenece a este grupo.');
        }


        if (!$membership) {
          \Drupal::messenger()->addError('No tienes permiso para crear o editar asignaturas en esta carrera.');
          continue;
        }

        if ($membership->hasPermission('create group_node:asignatura entity') || $membership->hasPermission('update own group_node:asignatura entity')) {

          // Verificar si la carrera ya existe.

          if ($subject) {
            if ($subject->getOwnerId() == $current_user->id() || $membership->hasPermission('update any group_node:asignatura entity')) {
              $this->updateSubject($subject, $data);
            } else {
              \Drupal::messenger()->addError('No tienes permiso para editar esta asignatura.');
            }

          } else {
            if ($degree->getOwnerId() == $current_user->id()) {
              \Drupal::messenger()->addMessage('Id del propietario: ' . $degree->getOwnerId() . ' e id del usuario: ' . $current_user->id());

              $this->createNewSubject($subject_name, $degree, $data, $group);
            } else {
              \Drupal::messenger()->addError('No tienes permiso para crear asignaturas en esta carrera.');
            }
          }
        } else {
          \Drupal::messenger()->addError('No tienes permiso para editar o crear asignaturas en esta carera.');
        }


      } catch (\Exception $e) {
        \Drupal::messenger()->addError(\Drupal::translation()->translate('Error al procesar la fila'));
        continue;  // Continuar con la siguiente fila en caso de error
      }

    }


  }


  protected function updateSubject($subject, $data)
  {

    $subject->set('field_creditos', $data['Credits']);
    $subject->set('field_cuatrimestre', self::VALID_COURSES_QUARTERS[$data['Quarter']]);
    $subject->set('field_curso', self::VALID_COURSES_QUARTERS[$data['Course']]);
    $subject->set('field_codigo', $data['Code']);
    $subject->set('field_requirements', $data['Requirements']);  // Validado
    $subject->set('field_subject_contents', $data['Subject Contents']);  // Validado
    $subject->set('field_subject_evaluation', $data['Subject Evaluation']);
    $subject->set('field_subject_instructors', $data['Subject Instructors']);
    $subject->set('field_subject_introduction', $data['Subject Introduction']);
    $subject->set('field_subject_language', $data['Language']);
    $subject->set('field_subject_learning_outcomes', $data['Subject Learning Outcomes']);
    $subject->set('field_subject_modality', $data['Subject Modality']);
    $subject->set('field_subject_planned_activities', $data['Subject Planned Activities']);
    $subject->set('field_subject_recommendations', $data['Subject Recommendations']);
    $subject->set('field_tipo', strtolower($data['Type']));

    $subject->save();

  }

  protected function createNewSubject($subject_name, $degree, $data, $group)
  {
    $subject = Node::create(values: [
      'type' => 'asignatura',
      'title' => $subject_name,
      'field_carrera' => ['target_id' => $degree->id()],  // Referencia a la carrera.
      'field_creditos' => $data['Credits'],
      'field_cuatrimestre' => self::VALID_COURSES_QUARTERS[$data['Quarter']],
      'field_curso' => self::VALID_COURSES_QUARTERS[$data['Course']],
      'field_codigo' => $data['Code'],
      'field_requirements' => $data['Requirements'],
      'field_subject_contents' => $data['Subject Contents'],
      'field_subject_evaluation' => $data['Subject Evaluation'],  // Validado
      'field_subject_instructors' => $data['Subject Instructors'],  // Validado
      'field_subject_introduction' => $data['Subject Introduction'],
      'field_subject_language' => $data['Language'],
      'field_subject_learning_outcomes' => $data['Subject Learning Outcomes'],  // Validado
      'field_subject_modality' => $data['Subject Modality'],
      'field_subject_planned_activities' => $data['Subject Planned Activities'],
      'field_subject_recommendations' => $data['Subject Recommendations'],
      'field_tipo' => strtolower($data['Type']),
      'status' => 1,  // Publicado
    ]);

    // Guardar el nodo en la base de datos.
    $subject->save();


    // Crear el administrador de la asignatura.
    $admin_email = $data['Admin Email'];
    $admin_username = $data['Admin Username'];
    $admin_password = $data['Admin Password'];

    $admin_user = User::create([
        'name' => $admin_username,
        'mail' => $admin_email,
        'pass' => $admin_password,
        'status' => 1,
    ]);

    $admin_user->save();
    $subject->setOwner($admin_user);
    $subject->save();

    // Relacionar la asignatura con el grupo de la universidad.
    $group->addRelationship($subject, 'group_node:asignatura');

    // Añadir el administrador de la carrera al grupo con el rol correspondiente.
    $group->addMember($admin_user, ['group_roles' => ['universitytypegroup-subject_admi']]);

    \Drupal::messenger()->addMessage('Asignatura y administrador creados con éxito.');






  }




}



