<?php

namespace Drupal\import_from_excel\Service;

class UniversityDictionaries
{
    /**
     * Devuelve un diccionario de traducciones de nombres de universidades organizados por idioma.
     *
     * @return array
     */
    public static function getAllDictionaries()
    {
        return [
            'en' => [ // Diccionario para inglés a español
                'University of Vigo' => 'Universidad de Vigo',
                'University of Barcelona' => 'Universidad de Barcelona',
                'Complutense University of Madrid' => 'Universidad Complutense de Madrid',
            ],
            'fr' => [ // Diccionario para francés a español
                'Université de Vigo' => 'Universidad de Vigo',
                'Université de Barcelone' => 'Universidad de Barcelona',
                'Université Complutense de Madrid' => 'Universidad Complutense de Madrid',
            ],
            // Puedes añadir más diccionarios para otros idiomas aquí
            // 'de' => [ ... ],
            // 'it' => [ ... ],
        ];
    }

    /**
     * Obtiene la traducción del nombre de la universidad al español según el idioma proporcionado.
     *
     * @param string $university_name
     *   El nombre de la universidad en el idioma original.
     * @param string $lang_code
     *   El código del idioma original (por ejemplo, 'en', 'fr').
     *
     * @return string
     *   El nombre de la universidad traducido al español o el nombre original si no se encuentra la traducción.
     */
    public static function translateUniversityName($university_name, $lang_code)
    {
        $dictionaries = self::getAllDictionaries();
        return $dictionaries[$lang_code][$university_name] ?? $university_name;
    }
}
