<?php

namespace Drupal\import_from_excel\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Drupal\node\Entity\Node;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\import_from_excel\Service\DegreeImport;
use Drupal\import_from_excel\Service\SubjectImport;
use Drupal\import_from_excel\Service\UniversityImport;
use Drupal\import_from_excel\Service\NewUniversityImport;

class ImportForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'importar_carrera_form';
  }

  protected $universityImport;
  protected $degreeImport;
  protected $subjectImport;
  protected $newUniversityImport;

  public function __construct(UniversityImport $university_import, DegreeImport $degree_import, SubjectImport $subject_import, NewUniversityImport $new_university_import)
  {
    $this->universityImport = $university_import;
    $this->degreeImport = $degree_import;
    $this->subjectImport = $subject_import;
    $this->newUniversityImport = $new_university_import;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('import_from_excel.university_import'),
      $container->get('import_from_excel.degree_import'),
      $container->get('import_from_excel.subject_import'),
      $container->get('import_from_excel.new_university_import')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $current_user = \Drupal::currentUser();
    $username = $current_user->getDisplayName();
    \Drupal::messenger()->addMessage('El usuario actual es: ' . $username);

    // Obtener el ID del usuario actual
    $uid = $current_user->id();

    // Inicializar array de opciones vacío.
    $options = [];

    // Cargar las membresías del usuario en los grupos
    $group_memberships = \Drupal::service('group.membership_loader')->loadByUser($current_user);

    if ($current_user->hasRole('administrator')) {
      // Si es administrador global, mostrar todas las opciones.
      $options['universidades'] = $this->t('Universidades');
      $options['carreras'] = $this->t('Carreras');
      $options['asignaturas'] = $this->t('Asignaturas');
    } else {


      // Verificar los roles dentro de los grupos.
      foreach ($group_memberships as $membership) {
        $roles = $membership->getRoles();
        \Drupal::messenger()->addMessage($roles);
        
        

        //\Drupal::messenger()->addMessage('Roles: ' . print_r($roles, TRUE));

        // Imprimir los roles del usuario
        foreach ($roles as $role) {
          // Obtener el ID del rol
          $role_label = $role->label();
          //\Drupal::messenger()->addMessage('Role label: ' . $role_label);

          // Si el usuario tiene el rol de "University Admin", mostrar universidades.
          if ($role_label == 'University Admin') {
            $options['universidades'] = $this->t('Universidades');
            $options['carreras'] = $this->t('Carreras');
            $options['asignaturas'] = $this->t('Asignaturas');
          }

          // Si el usuario tiene el rol de "Degree Admin", mostrar carreras.
          if ($role_label == 'Degree Admin') {
            $options['carreras'] = $this->t('Carreras');
            $options['asignaturas'] = $this->t('Asignaturas');
          }

          // Si el usuario tiene el rol de "Subject Admin", mostrar asignaturas.
          if ($role_label == 'Subject Admin') {
            $options['asignaturas'] = $this->t('Asignaturas');
          }
        }
      }
    }

    // Si no tiene roles asignados en los grupos, mostrará el mensaje de falta de permisos.
    if (empty($options)) {
      \Drupal::messenger()->addMessage($this->t('No tienes permisos para realizar ninguna importación.'), 'error');
    }

    // Definir el campo seleccionable con las opciones basadas en los roles.
    $form['import_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Selecciona el tipo de importación'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    // Definir el campo para subir el archivo Excel.
    $form['university_excel'] = [
      '#type' => 'file',
      '#title' => $this->t('Sube el archivo Excel'),
      '#required' => TRUE,
    ];

    // Definir el botón de submit.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Importar'),
    ];

    return $form;
  }



  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Obtener el archivo subido.
    $file = file_save_upload('university_excel', [
      'file_validate_extensions' => ['xls xlsx'],
    ]);

    if ($file) {
      // file_save_upload puede devolver un array o un objeto de archivo.
      if (is_array($file)) {
        // Si se devuelve un array, obtenemos el primer archivo.
        $file = reset($file);
      }

      // Asegurarse de que el archivo es un objeto antes de llamarlo.
      if ($file instanceof \Drupal\file\FileInterface) {
        // Verificar el tipo MIME.
        $mime = mime_content_type($file->getFileUri());
        if (!in_array($mime, ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])) {
          \Drupal::messenger()->addError($this->t('Error: El archivo no es un archivo Excel válido.'));
          return;
        }

        // Procesar el archivo Excel.
        $this->processExcel($file->getFileUri());

      } else {
        \Drupal::messenger()->addError($this->t('Error: No se pudo subir el archivo correctamente.'));
      }
    } else {
      \Drupal::messenger()->addError($this->t('Error: No se seleccionó ningún archivo o el archivo no es válido.'));
    }
  }


  protected function processExcel($file_path)
  {
    // Convertir la ruta de Drupal a una ruta de archivo real.
    $real_file_path = \Drupal::service('file_system')->realpath($file_path);

    // Verificar si el archivo existe.
    if (!file_exists($real_file_path)) {
      \Drupal::messenger()->addError($this->t('El archivo no existe en la ruta: @ruta', ['@ruta' => $real_file_path]));
      return;
    }

    try {
      // Cargar el archivo Excel.
      $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($real_file_path);
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
      \Drupal::messenger()->addError($this->t('Error al cargar el archivo Excel: @message', ['@message' => $e->getMessage()]));
      return;
    }

    // Procesar cada hoja del Excel por su nombre.
    foreach ($spreadsheet->getSheetNames() as $sheetName) {
      // Obtener la hoja de cálculo por su nombre.
      $sheet = $spreadsheet->getSheetByName($sheetName);

      // Convertir las filas de la hoja en un array.
      $rows = $sheet->toArray();

      // Verificar si la hoja está vacía (todas las filas están vacías).
      if ($this->isSheetEmpty($rows)) {
        \Drupal::messenger()->addMessage($this->t('La hoja "@sheet" está vacía y no se procesará.', ['@sheet' => $sheetName]));
        continue;  // Saltar a la siguiente hoja.
      }

      \Drupal::messenger()->addMessage('Valor: ' . print_r($sheetName, TRUE));

      // Procesar según el nombre de la hoja.
      switch ($sheetName) {
        case 'Degrees':
          $this->degreeImport->process($sheet);
          break;
        case 'Subjects':
          $this->subjectImport->process($sheet);
          break;
        case 'Universities':
          $this->universityImport->process($sheet);
          break;
        case 'New Universities':
          $this->newUniversityImport->process($sheet);
          break;
        default:
          \Drupal::messenger()->addMessage($this->t('Hoja desconocida "@sheet", no se procesará.', ['@sheet' => $sheetName]));
          break;
      }
    }
  }

  /**
   * Verificar si la hoja está vacía.
   *
   * @param array $rows
   *   Las filas de la hoja.
   *
   * @return bool
   *   TRUE si todas las filas están vacías, FALSE en caso contrario.
   */
  protected function isSheetEmpty(array $rows)
  {
    // Filtrar filas vacías y verificar si hay al menos una fila con datos.
    foreach ($rows as $row) {
      if (array_filter($row)) {
        return FALSE;  // Hay al menos una fila con datos.
      }
    }
    return TRUE;  // Todas las filas están vacías.
  }



}