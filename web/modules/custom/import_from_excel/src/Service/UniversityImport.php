<?php

namespace Drupal\import_from_excel\Service;
use Drupal\node\Entity\Node;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class UniversityImport
{

  protected function getUniversityByName($original_university_name, $university_name, $lang_code = 'es')
  {
    // Crear una consulta para buscar la universidad por su nombre (título).
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'universidad')  // Asumiendo que el tipo de nodo es "universidad".
      ->condition('title', $original_university_name)  // Buscar por título.
      ->accessCheck(FALSE)  // No verificar permisos de acceso.
      ->range(0, 1);  // Limitar la búsqueda a un resultado.

    $nids = $query->execute();  // Ejecutar la consulta.

    // Si encontramos la universidad, cargar el nodo original.
    if (!empty($nids)) {
      $nid = reset($nids);
      $node = \Drupal\node\Entity\Node::load($nid);  // Cargar el nodo de la universidad.

      // Verificar si el nodo tiene una traducción para el idioma especificado.
      if ($node->hasTranslation($lang_code)) {
        // Devolver el nodo traducido en el idioma solicitado.
        return $node->getTranslation($lang_code);
      } else {

            $translated_university = $node->addTranslation($lang_code);
            $translated_university->setTitle($university_name);
            $translated_university->save();

        return $translated_university;  // Devolver el nodo original en su idioma base.
      }
    }

    // Si no se encuentra la universidad, devolver NULL.
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

      // Obtener los valores de las columnas correspondientes.
      $original_university_name = $data['Original Name'];
      $lang_code =  $data['Lang code'];
      $university_name = $data['University']; // Acceder a la columna "University".
      $university_information = $data['Information'];
      $university_primary_color = $data['Primary Color'];
      $university_text_color = $data['Text Color'];
      $university_emphasis_color = $data['Emphasis Text Color'];

      try {
        $university = $this->getUniversityByName($original_university_name, $university_name, $lang_code);

        if ($university) {
          
          $university->set('field_informacion', $university_information);
          $university->set('field_informacion', $university_information);
          $university->set('field_primary_color', $university_primary_color);
          $university->set('field_text_color', $university_text_color);
          $university->set('field_emphasis_text_color', $university_emphasis_color);

          // Guardar los cambios.
          $university->save();
          \Drupal::messenger()->addMessage('Universidad actualizada con éxito.');
        } else {
          // Crear la entidad "Universidad".
          $node = Node::create([
            'type' => 'universidad',
            'title' => $university_name,
          ]);

          // Guardar el nodo en la base de datos.
          $node->save();
          \Drupal::messenger()->addMessage('Universidad creada con éxito.');
        }
      } catch (\Exception $e) {
        \Drupal::messenger()->addError(\Drupal::translation()->translate('Error al procesar la fila: @message', ['@message' => $e->getMessage()]));
        continue;  // Continuar con la siguiente fila en caso de error.
      }
    }
  }

}
