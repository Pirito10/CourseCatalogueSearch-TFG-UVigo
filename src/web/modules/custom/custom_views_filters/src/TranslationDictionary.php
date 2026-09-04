<?php

namespace Drupal\custom_views_filters;

class TranslationDictionary {

  /**
   * Terms relevant to degree programmes, keyed by English and mapped to
   * per-language translations.
   */
  const PROGRAMMES = [
    'computer science'        => ['es' => 'informática',                'fr' => 'informatique',               'cs' => 'informatika',              'hu' => 'informatika',        'sl' => 'informatika',              'et' => 'informaatika',           'gl' => 'informática',              'pt' => 'informática',               'el' => 'πληροφορική'],
    'computer engineering'    => ['es' => 'ingeniería informática',     'fr' => 'génie informatique',         'cs' => 'počítačové inženýrství',   'hu' => 'számítástechnika',   'sl' => 'računalniško inženirstvo', 'et' => 'arvutitehnika',          'gl' => 'enxeñaría informática',    'pt' => 'engenharia informática'],
    'chemical engineering'    => ['es' => 'ingeniería química',         'fr' => 'génie chimique',             'cs' => 'chemické inženýrství',     'hu' => 'vegyészmérnöki',     'sl' => 'kemijsko inženirstvo',     'et' => 'keemiatehnika',          'gl' => 'enxeñaría química',        'pt' => 'engenharia química'],
    'civil engineering'       => ['es' => 'ingeniería civil',           'fr' => 'génie civil',                'cs' => 'stavební inženýrství',     'hu' => 'építőmérnöki',       'sl' => 'gradbeništvo',             'et' => 'tsiviilehitus',          'gl' => 'enxeñaría civil',          'pt' => 'engenharia civil'],
    'mechanical engineering'  => ['es' => 'ingeniería mecánica',        'fr' => 'génie mécanique',            'cs' => 'strojní inženýrství',      'hu' => 'gépészmérnöki',      'sl' => 'strojništvo',              'et' => 'masinaehitus',           'gl' => 'enxeñaría mecánica',       'pt' => 'engenharia mecânica'],
    'biomedical engineering'  => ['es' => 'ingeniería biomédica',       'fr' => 'génie biomédical',           'cs' => 'biomedicínské inženýrství','hu' => 'biomérnöki',         'sl' => 'biomedicinsko inženirstvo','et' => 'biomeditsiiniline inseneeria', 'gl' => 'enxeñaría biomédica', 'pt' => 'engenharia biomédica'],
    'industrial engineering'  => ['es' => 'ingeniería industrial',      'fr' => 'génie industriel',           'cs' => 'průmyslové inženýrství',   'hu' => 'ipari mérnöki',      'sl' => 'industrijsko inženirstvo', 'et' => 'tootmisinseneeria',      'gl' => 'enxeñaría industrial',     'pt' => 'engenharia industrial'],
    'mathematics'             => ['es' => 'matemáticas',                'fr' => 'mathématiques',              'cs' => 'matematika',               'hu' => 'matematika',         'sl' => 'matematika',               'et' => 'matemaatika',            'gl' => 'matemáticas',              'pt' => 'matemática',                'el' => 'μαθηματικά'],
    'physics'                 => ['es' => 'física',                     'fr' => 'physique',                   'cs' => 'fyzika',                   'hu' => 'fizika',             'sl' => 'fizika',                   'et' => 'füüsika',                'gl' => 'física',                   'pt' => 'física',                    'el' => 'φυσική'],
    'chemistry'               => ['es' => 'química',                    'fr' => 'chimie',                     'cs' => 'chemie',                   'hu' => 'kémia',              'sl' => 'kemija',                   'et' => 'keemia',                 'gl' => 'química',                  'pt' => 'química',                   'el' => 'χημεία'],
    'biology'                 => ['es' => 'biología',                   'fr' => 'biologie',                   'cs' => 'biologie',                 'hu' => 'biológia',           'sl' => 'biologija',                'et' => 'bioloogia',              'gl' => 'bioloxía',                 'pt' => 'biologia',                  'el' => 'βιολογία'],
    'medicine'                => ['es' => 'medicina',                   'fr' => 'médecine',                   'cs' => 'medicína',                 'hu' => 'orvostudomány',      'sl' => 'medicina',                 'et' => 'meditsiin',              'gl' => 'medicina',                 'pt' => 'medicina',                  'el' => 'ιατρική'],
    'pharmacy'                => ['es' => 'farmacia',                   'fr' => 'pharmacie',                  'cs' => 'farmacie',                 'hu' => 'gyógyszerészet',     'sl' => 'farmacija',                'et' => 'farmaatsia',             'gl' => 'farmacia',                 'pt' => 'farmácia',                  'el' => 'φαρμακευτική'],
    'nursing'                 => ['es' => 'enfermería',                 'fr' => 'soins infirmiers',           'cs' => 'ošetřovatelství',          'hu' => 'ápolás',             'sl' => 'zdravstvena nega',         'et' => 'õendus',                 'gl' => 'enfermaría',               'pt' => 'enfermagem',                'el' => 'νοσηλευτική'],
    'psychology'              => ['es' => 'psicología',                 'fr' => 'psychologie',                'cs' => 'psychologie',              'hu' => 'pszichológia',       'sl' => 'psihologija',              'et' => 'psühholoogia',           'gl' => 'psicoloxía',               'pt' => 'psicologia',                'el' => 'ψυχολογία'],
    'law'                     => ['es' => 'derecho',                    'fr' => 'droit',                      'cs' => 'právo',                    'hu' => 'jog',                'sl' => 'pravo',                    'et' => 'õigus',                  'gl' => 'dereito',                  'pt' => 'direito',                   'el' => 'νομική'],
    'economics'               => ['es' => 'economía',                   'fr' => 'économie',                   'cs' => 'ekonomika',                'hu' => 'közgazdaságtan',     'sl' => 'ekonomija',                'et' => 'majandusteadus',         'gl' => 'economía',                 'pt' => 'economia',                  'el' => 'οικονομικά'],
    'architecture'            => ['es' => 'arquitectura',               'fr' => 'architecture',               'cs' => 'architektura',             'hu' => 'építészet',          'sl' => 'arhitektura',              'et' => 'arhitektuur',            'gl' => 'arquitectura',             'pt' => 'arquitetura',               'el' => 'αρχιτεκτονική'],
    'business administration' => ['es' => 'administración de empresas', 'fr' => 'administration des affaires','cs' => 'obchodní administrativa',  'hu' => 'üzleti adminisztráció','sl' => 'poslovna administracija', 'et' => 'ärijuhtimine',           'gl' => 'administración de empresas','pt' => 'administração de empresas'],
  ];

  /**
   * Terms relevant to individual courses, keyed by English and mapped to
   * per-language translations.
   */
  const COURSES = [
    'algebra'           => ['es' => 'álgebra',              'fr' => 'algèbre',                      'cs' => 'algebra',              'hu' => 'algebra',              'sl' => 'algebra',              'et' => 'algebra',              'gl' => 'álxebra',              'pt' => 'álgebra',              'el' => 'άλγεβρα'],
    'calculus'          => ['es' => 'cálculo',              'fr' => 'calcul',                       'cs' => 'kalkulus',             'hu' => 'analízis',             'sl' => 'kalkulus',             'et' => 'kalkulus',             'gl' => 'cálculo',              'pt' => 'cálculo',              'el' => 'λογισμός'],
    'statistics'        => ['es' => 'estadística',          'fr' => 'statistiques',                 'cs' => 'statistika',           'hu' => 'statisztika',          'sl' => 'statistika',           'et' => 'statistika',           'gl' => 'estatística',          'pt' => 'estatística',          'el' => 'στατιστική'],
    'thermodynamics'    => ['es' => 'termodinámica',        'fr' => 'thermodynamique',              'cs' => 'termodynamika',        'hu' => 'termodinamika',        'sl' => 'termodinamika',        'et' => 'termodünaamika',       'gl' => 'termodinámica',        'pt' => 'termodinâmica',        'el' => 'θερμοδυναμική'],
    'databases'         => ['es' => 'bases de datos',       'fr' => 'bases de données',             'cs' => 'databáze',             'hu' => 'adatbázisok',          'sl' => 'podatkovne baze',      'et' => 'andmebaasid',          'gl' => 'bases de datos',       'pt' => 'bases de dados',       'el' => 'βάσεις δεδομένων'],
    'programming'       => ['es' => 'programación',         'fr' => 'programmation',                'cs' => 'programování',         'hu' => 'programozás',          'sl' => 'programiranje',        'et' => 'programmeerimine',     'gl' => 'programación',         'pt' => 'programação',          'el' => 'προγραμματισμός'],
    'operating systems' => ['es' => 'sistemas operativos',  'fr' => 'systèmes d\'exploitation',     'cs' => 'operační systémy',     'hu' => 'operációs rendszerek', 'sl' => 'operacijski sistemi',  'et' => 'operatsioonisüsteemid','gl' => 'sistemas operativos',  'pt' => 'sistemas operativos',  'el' => 'λειτουργικά συστήματα'],
    'networks'          => ['es' => 'redes',                'fr' => 'réseaux',                      'cs' => 'počítačové sítě',      'hu' => 'hálózatok',            'sl' => 'omrežja',              'et' => 'võrgud',               'gl' => 'redes',                'pt' => 'redes',                'el' => 'δίκτυα'],
    'data structures'   => ['es' => 'estructuras de datos', 'fr' => 'structures de données',        'cs' => 'datové struktury',     'hu' => 'adatstruktúrák',       'sl' => 'podatkovne strukture', 'et' => 'andmestruktuurid',     'gl' => 'estruturas de datos',  'pt' => 'estruturas de dados',  'el' => 'δομές δεδομένων'],
    'organic chemistry' => ['es' => 'química orgánica',     'fr' => 'chimie organique',             'cs' => 'organická chemie',     'hu' => 'szerves kémia',        'sl' => 'organska kemija',      'et' => 'orgaaniline keemia',   'gl' => 'química orgánica',     'pt' => 'química orgânica',     'el' => 'οργανική χημεία'],
  ];

}
