<?php

namespace Drupal\import_from_excel\Service;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class NewUniversityImport
{
    public function process($worksheet)
    {
        if (!\Drupal::currentUser()->hasPermission('administer site configuration')) {
            \Drupal::messenger()->addError(t('No tienes permisos para realizar esta acción.'));
            return;
        }


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

            // Obtener los valores de las columnas correspondientes.
            $university_name = $data['University']; // Acceder a la columna "University".
            $admin_name = $data['Admin Name'];
            $admin_email = $data['Admin Email'];
            $admin_username = $data['Admin Username'];
            $admin_password = $data['Admin Password'];

            try {
                // Crear la entidad "Universidad".
                $university = Node::create([
                    'type' => 'universidad',
                    'title' => $university_name,
                ]);
            
                // Crear el usuario administrador de la universidad.
                $admin_user = User::create([
                    'name' => $admin_username,
                    'mail' => $admin_email,
                    'pass' => $admin_password,
                    'status' => 1,
                ]);
            
                // Guardar el usuario y asignarlo como propietario de la universidad.
                $admin_user->save();
                $university->setOwner($admin_user);
                $university->save();
            
                // Crear el grupo para la universidad.
                $group_name = 'Group ' . $university_name;
                $group = \Drupal\group\Entity\Group::create([
                    'type' => 'universitytypegroup',
                    'label' => $group_name,
                ]);
                $group->save();
            
                // Crear la relación entre el grupo y la universidad.
                $group->addRelationship($university, 'group_node:universidad');
            
                // Añadir el administrador al grupo con el rol correspondiente.
                $group->addMember($admin_user, ['group_roles' => ['universitytypegroup-university_a']]);
            
                \Drupal::messenger()->addMessage('Universidad y grupo creados con éxito.');
            } catch (\Exception $e) {
                \Drupal::messenger()->addError('Error al crear la universidad: ' . $e->getMessage());
            }
            
        }




    }


}