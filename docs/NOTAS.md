# Notas de desarrollo — TFG DACEM

---

## Búsqueda difusa (FuzzySearch)

### Qué hace y por qué

El filtro `combine` estándar de Views hace un `LIKE '%término%'` en SQL — solo encuentra coincidencias exactas de subcadena. Un error tipográfico rompe la búsqueda. FuzzySearch reemplaza ese filtro con scoring por distancia de Levenshtein: "enginering" encuentra "Engineering", "mecanical" encuentra "Mechanical".

### Arquitectura

Dos piezas que trabajan juntas:

#### `web/modules/custom/custom_views_filters/src/FuzzySearch.php`
Clase PHP con toda la lógica de puntuación:
- **`scoreFromIndex()`** — punto de entrada. Recibe el texto buscado y el ID del índice, devuelve un array de `{nid, score}` ordenado por score descendente (o `NULL` si el término es demasiado corto).
- **`loadCandidatesFromIndex()`** — consulta el índice de Search API (no SQL directo) y devuelve todos los ítems indexados con sus campos de texto.
- **`scoreAndFilter()`** — para cada candidato, suma los scores palabra a palabra contra el término buscado. Usa el NID como clave para deduplicar (un mismo nodo puede aparecer en varios idiomas en el índice). Ordena los resultados por score descendente; en caso de empate, alfabéticamente por título.
- **`wordScore()`** — compara dos palabras con distancia de Levenshtein: exacto=1.0, prefijo=0.85, 1 edición≈0.91, 2 ediciones≈menor, más diferencia=0.
- **`normalize()`** — minúsculas + elimina acentos (é→e).
- **`tokenize()`** — divide en palabras de mínimo 4 caracteres.
- **`$orderedNids`** — array estático que transporta los NIDs ordenados por score desde `hook_views_query_alter` hasta `hook_views_pre_execute`, para construir el `FIELD()` SQL.

#### `web/modules/custom/custom_views_filters/custom_views_filters.module`
- **`hook_views_query_alter`**: intercepta la query de `search_programme`, `search_iec` e `institution_new_catalogue` (page_1 y page_2), llama a `FuzzySearch::scoreFromIndex()`, elimina la condición SQL del filtro combine (operator `formula`) y la sustituye por `WHERE nid IN (nids_con_score)`. Limpia `$query->orderby` y guarda los NIDs ordenados en `FuzzySearch::$orderedNids`.
- **`hook_views_pre_execute`**: se ejecuta tras construir el objeto `SelectInterface`. Lee `$orderedNids`, añade una expresión `FIELD(node_field_data.nid, n1, n2, ...)` con `addExpression()` y ordena por ella con `orderBy()`. Esto garantiza que la paginación SQL también respeta el orden por relevancia.

**Flujo completo:**
```
Usuario escribe "enginering"
  → hook_views_query_alter intercepta la query
  → FuzzySearch::scoreFromIndex("enginering", "programmes")
      → Search API devuelve todos los ítems indexados (título + institución)
      → Levenshtein compara "enginering" con cada palabra de cada texto
      → "engineering" tiene distancia 1 → score 0.91
      → Deduplicación por NID (mismo programa en varios idiomas → score más alto)
      → Devuelve NIDs ordenados por score
  → Se elimina el WHERE combine de la SQL
  → Se añade WHERE nid IN (NIDs fuzzy), se limpia orderby
  → hook_views_pre_execute añade ORDER BY FIELD(nid, n1, n2, ...)
  → Drupal ejecuta la query → devuelve nodos en orden de relevancia, paginación incluida
  → El usuario ve los resultados más relevantes primero
```

---

### Configuración de Search API

**Módulos:** `search_api` + `search_api_db` (backend de base de datos, incluido como submódulo de `search_api`).

**Servidor (`Database Server`):**
- Minimum word length: **4** (coherente con `tokenize()`, que ya descarta palabras de menos de 4 caracteres)
- Partial matching: **Match whole words only** (no usamos búsquedas nativas de Search API)
- Phrase indexing: **Disabled** (no usamos búsquedas de frase nativas de Search API)

**Índice `programmes`:**
- Data source: Content
- Bundles: `Only those selected`, `Programme`
- Languages: `All except those selected`
- Server: `Database Server`
- Campos: `title` + `field_programme_institution:entity:title`

**Índice `courses`:**
- Data source: Content
- Bundles: `Only those selected`, `Course`
- Languages: `All except those selected`
- Server: `Database Server`
- Campos: `title` + `field_iec_programme:entity:title` + `field_iec_programme:entity:field_programme_institution:entity:title`

**Comandos útiles:**
```bash
ddev drush search-api:index   # reindexar
ddev drush cex -y             # exportar config a config/sync/
ddev drush cim -y             # importar config desde config/sync/ a la BD
```

---

### Búsqueda multilingüe

Search API indexa cada traducción de un nodo como un ítem separado (formato `entity:node/123:en`). Sin restricción de idioma, el mismo NID puede aparecer varias veces en los resultados — una por cada traducción indexada.

**Cambios en `FuzzySearch.php`:**
1. Eliminada la restricción de idioma en `loadCandidatesFromIndex()` — se quitaron `use LanguageInterface`, la detección del idioma y `$query->setLanguages()`. La consulta ahora devuelve todos los ítems sin importar el idioma.
2. Deduplicación por NID en `scoreAndFilter()`: el array de resultados se indexa por NID en vez de numéricamente. Si el mismo NID aparece en varios idiomas, se conserva el score más alto:
```php
// Antes:
$results[] = ['nid' => $nid, 'score' => $score];

// Después:
if (!isset($results[$nid]) || $score > $results[$nid]['score']) {
  $results[$nid] = ['nid' => $nid, 'score' => $score, 'title' => $row['title']];
}
```

---

### Búsqueda en múltiples campos

`loadCandidatesFromIndex()` itera sobre un array de field names y concatena sus valores en un único texto que se pasa a `scoreAndFilter()`. Los campos que no existen en un índice concreto se saltan automáticamente (el `getField()` devuelve `null`), así que el mismo código funciona para `programmes` y `courses`.

```php
foreach (['title', 'institution', 'programme'] as $fieldName) {
    $field = $item->getField($fieldName);
    if (!$field) { continue; }
    // ... concatenar valor a $text
}
```

Campos activos por índice:

| Campo en el índice | `programmes` | `courses` |
|---|---|---|
| `title` | Título del programa | Título del curso |
| `institution` | `field_programme_institution:entity:title` | `field_iec_programme:entity:field_programme_institution:entity:title` |
| `programme` | — | `field_iec_programme:entity:title` |

**Cómo añadir un campo nuevo:**
1. Añadirlo al índice en el admin (`/admin/config/search/search-api/index/{id}/fields`)
2. Reindexar: `ddev drush search-api:index`
3. Exportar config: `ddev drush cex -y`
4. Añadir el machine name al array en `loadCandidatesFromIndex()`:
```php
foreach (['title', 'institution', 'programme', 'nuevo_campo'] as $fieldName) {
```

---

### Búsqueda multilingüe con traducción automática

#### Problema

Search API indexa cada nodo solo en los idiomas en los que tiene traducción. Si un usuario español busca "informática" y el programa de una universidad griega solo tiene título en griego e inglés ("Computer Science"), FuzzySearch nunca lo encuentra porque compara cadenas de texto, no significados — "informática" y "Computer Science" no tienen ninguna palabra en común.

#### Servicio LibreTranslate (DDEV)

Se añadió LibreTranslate como servicio Docker adicional en `.ddev/docker-compose.libretranslate.yml`:

```yaml
version: "3.6"
services:
  libretranslate:
    image: libretranslate/libretranslate
    restart: "no"
    ports:
      - "5000:5000"
    mem_limit: 1g
    environment:
      - LT_LOAD_ONLY=en,es,fr,cs,el,et,gl,hu,pt,sl
```

- **Por qué un docker-compose custom y no un add-on de `ddev get`**: es la forma oficial de DDEV de añadir servicios sin add-on disponible. Los servicios propios de DDEV (web, db...) se gestionan internamente a partir de `config.yaml`, no con ficheros docker-compose explícitos.
- **`LT_LOAD_ONLY`**: limita los modelos de idioma descargados a los 10 idiomas reales de DACEM (`cs, el, en, es, et, fr, gl, hu, pt-pt, sl` en Drupal; `pt-pt` se mapea a `pt` para LibreTranslate). Sin esta variable descargaría los ~30 idiomas disponibles. Los modelos son direccionales (es→en ≠ en→es), así que con 10 idiomas se cargan 20 modelos en total. No existe forma oficialmente soportada de cargar solo una dirección.
- **`mem_limit: 1g`**: la memoria del contenedor crece con el uso (modelos cargados por los workers de gunicorn + caché de resultados de traducción) y nunca se libera de forma proactiva. Sin límite, puede llegar a 4 GB con todos los modelos activos. Con el límite, cuando un worker supera la cuota, el OOM killer de Docker lo mata (`SIGKILL`), gunicorn lo reinicia automáticamente, y el servicio sigue disponible con los workers restantes. Desde el exterior es invisible.
- **Peso**: con los 10 idiomas, el contenedor en ejecución ocupa ~1.58 GB (modelos descargados + imagen base). Con solo 3 idiomas (en, es, fr) eran ~847 MB — cada idioma añade aproximadamente 100 MB.
- **Acceso**: desde el host, `http://localhost:5000`. Desde dentro de los contenedores DDEV (PHP), `http://libretranslate:5000` (nombre del servicio).
- **Persistencia**: los modelos se descargan al arrancar el contenedor y se guardan en su capa de escritura. Sobreviven a `ddev restart` pero se perderían con `ddev delete` (no hay volumen explícito).

#### `TranslationDictionary.php`

Clase de datos pura en `web/modules/custom/custom_views_filters/src/TranslationDictionary.php`. Contiene dos constantes:

- **`PROGRAMMES`**: términos de programas académicos (informática, ingeniería, medicina...), con cobertura en los 9 idiomas no ingleses del sitio.
- **`COURSES`**: términos de asignaturas (álgebra, cálculo, bases de datos...), separados de programas para evitar que términos de asignatura activen traducciones en búsquedas de programas y viceversa.

Las claves son los equivalentes en inglés y los valores son mapas `[lang => traducción]`. Ejemplo:

```php
'computer science' => [
    'es' => 'informática', 'fr' => 'informatique', 'cs' => 'informatika', ...
]
```

Este sentido (inglés como clave) facilita el mantenimiento: una sola entrada cubre todos los idiomas para un mismo concepto. Para buscar por el término del usuario se construye un índice invertido en `TranslationService`.

#### `TranslationService.php`

Nueva clase en `web/modules/custom/custom_views_filters/src/TranslationService.php`. Punto de entrada: `getSearchTerms(string $input, ?string $sourceLang = NULL, string $indexId = 'programmes'): array`.

```php
public static function getSearchTerms(string $input, ?string $sourceLang = NULL, string $indexId = 'programmes'): array {
    $source = $sourceLang ?? static::uiLanguage();

    if ($source === 'en') {
      return [$input];
    }

    $terms = [$input];
    $translated = static::dictionaryLookup($input, $source, $indexId)
      ?? static::translateTo($input, $source, 'en');

    if ($translated !== NULL && $translated !== $input) {
      $terms[] = $translated;
    }

    return $terms;
}
```

El operador `??` implementa la prioridad: diccionario primero, LibreTranslate solo si el diccionario devuelve `NULL`. `dictionaryLookup()` construye un índice invertido de `TranslationDictionary` la primera vez que se llama por petición (lazy initialization) y lo cachea en un array estático para reutilizarlo.

**Decisiones de diseño:**

1. **Idioma de origen = idioma de la UI de Drupal, nunca auto-detección.** Se probó `POST /detect` de LibreTranslate y resultó demasiado frágil para términos académicos cortos sin acentos:

   | Término | Detectado | Confianza |
   |---|---|---|
   | "ingenieria quimica" (sin acentos) | en | 0% |
   | "ingeniería química" | es | 90% |
   | "gestion empresarial" (sin acentos) | **fr** | 90% |
   | "matematicas aplicadas" (sin acentos) | **pt** | 100% |

   Incluso con confianza alta (90-100%) la detección puede ser incorrecta — los idiomas romances comparten demasiado vocabulario sin los acentos. Ningún umbral de confianza lo soluciona, así que se descartó la auto-detección por completo a favor de `\Drupal::languageManager()->getCurrentLanguage()->getId()`.

2. **Solo se traduce al inglés, no a todos los idiomas.** Todos los programas y cursos de DACEM tienen sus datos introducidos en inglés obligatoriamente (el contenido en otros idiomas es opcional). Por tanto, basta con traducir el término del usuario al inglés para cubrir el catálogo completo: si busca "ingeniería química" en español, se traduce a "chemical engineering" y FuzzySearch lo encuentra. Esto reduce la llamada a LibreTranslate de 9 peticiones (una por idioma) a 1 sola.

3. **El término original siempre se incluye en el array devuelto** (`$terms = [$input]`), independientemente de si se traduce o no. Esto cubre dos casos a la vez:
   - Typos: si la traducción falla o produce basura, FuzzySearch contra el original todavía encuentra coincidencias con distancia de Levenshtein baja.
   - Usuario que escribe en un idioma distinto al de la UI: un usuario con la web en español que escribe "chemical" (inglés) encuentra "Chemical Engineering" vía el término original, sin que la traducción (es→en de "chemical") tenga que acertar.

4. **Traducciones fallidas o vacías se descartan silenciosamente** (`$translated !== NULL && $translated !== $input`). Si LibreTranslate no está disponible o devuelve error, `translateTo()` captura la excepción y devuelve `NULL` — el sistema sigue funcionando solo con el término original, sin filtrar de más ni romper la búsqueda.

#### Integración con `FuzzySearch.php`

`scoreFromIndex()` ahora pide los términos a `TranslationService` y ejecuta `scoreAndFilter()` una vez por término, fusionando los resultados y quedándose con el score más alto por NID:

```php
public static function scoreFromIndex(string $input, string $indexId = 'programmes'): ?array {
    $candidates = static::loadCandidatesFromIndex($indexId);
    $terms = TranslationService::getSearchTerms($input, NULL, $indexId);

    $merged = [];
    $allNull = TRUE;

    foreach ($terms as $term) {
      $results = static::scoreAndFilter($term, $candidates);
      if ($results === NULL) {
        continue;
      }
      $allNull = FALSE;
      foreach ($results as $row) {
        $nid = $row['nid'];
        if (!isset($merged[$nid]) || $row['score'] > $merged[$nid]['score']) {
          $merged[$nid] = $row;
        }
      }
    }

    if ($allNull) {
      return NULL;
    }

    $results = array_values($merged);
    usort($results, static fn($a, $b) => $b['score'] <=> $a['score']);
    return $results;
}
```

`NULL` solo se devuelve (= "no filtrar") cuando *ningún* término produjo palabras tokenizables; si al menos uno tuvo resultados, se fusionan aunque sea con un array vacío de coincidencias.

#### Rendimiento

Al reducir la traducción a un único idioma destino (inglés), `TranslationService` hace una sola petición HTTP síncrona por búsqueda. La latencia medida en condiciones normales (modelos ya cargados en memoria) es de **25-40ms**, frente a los ~1s que costaba la implementación anterior con 9 peticiones en paralelo.

El único caso lento es el arranque en frío: LibreTranslate carga los modelos de forma perezosa la primera vez que cada worker de gunicorn los necesita. Con 4 workers, los primeros 4 usos de un idioma nuevo pueden tardar varios segundos hasta que todos los workers han cargado el par. Después la latencia se estabiliza en los 25-40ms mencionados.

**Memoria de la VM de WSL2.** Si el host corre cerca del límite de RAM, WSL2 puede empezar a usar swap (disco) y toda la pila se ralentiza. Solución: crear `%UserProfile%\.wslconfig` con `[wsl2] memory=12GB` y reiniciar WSL2 con `wsl --shutdown` desde PowerShell.

#### Limitaciones conocidas (líneas futuras)

- **Traducción de palabras sueltas sin contexto es poco fiable.** Probado con "informática" → traducido a inglés como "it" en vez de "Computer Science". El diccionario (`TranslationDictionary`) cubre los casos más comunes; LibreTranslate sigue siendo el fallback para términos no incluidos.
- **Parámetro `alternatives` de LibreTranslate sin usar.** El endpoint `/translate` admite devolver traducciones alternativas además de la principal (`alternatives: 3`). Una mejora futura sería añadir cada alternativa como término de búsqueda adicional, aumentando la cobertura cuando la traducción principal es de baja calidad. No implementado por ahora.
- **Sin caché de traducciones en Drupal.** Cada búsqueda nueva llama a LibreTranslate aunque el término ya se haya traducido antes. Cachear por `(idioma_origen, término)` en la caché de Drupal eliminaría la latencia de traducción para búsquedas repetidas. No implementado por ahora dado que la latencia actual (~25-40ms) es aceptable.
- **Sin precalentamiento de modelos al desplegar.** Los modelos se cargan de forma perezosa por cada worker de gunicorn en su primera petición. Quedaría como script de arranque o tarea posterior al `ddev start`.

---

## Botones de ordenación

### Mecanismo común (JS)

El JS escribe los valores en dos campos ocultos del formulario de Views (`sort_by` y `sort_order`) y pulsa el botón Submit. En `search_programme`, Drupal AJAX intercepta el envío y actualiza los resultados sin recargar la página. En `search_iec` no hay `use_ajax: true`, por lo que la página se recarga completa — comportamiento esperado.

**Toggle ASC/DESC:** si el campo activo ya es el mismo que se pulsó → invierte el orden. Si es un campo distinto → ordena ASC por defecto.

**Requisito en el YAML de la vista:** el sort debe tener `exposed: true`, un `field_identifier` que coincida con el `data-sort-by` del botón, y el BEF configurado con `plugin_id: default` (no `bef_links`) para que genere los `<select>` ocultos que el JS necesita.

---

### Botones search_programme

| Botón | `data-sort-by` | Campo YAML | Notas |
|---|---|---|---|
| A-Z | `title` | `title` | — |
| Field of Study | `field_isced_f` | `field_isced_f_value` | — |
| Level | `field_eqf_level` | `field_eqf_level_value` | El YAML original apuntaba a `node__field_programme_level` (tabla inexistente). Corregido a `node__field_eqf_level`. |
| ECTS | `field_credits` | `field_credits_value` | — |
| Country | `field_institution_country` | `field_institution_country_value` | Relación `field_programme_institution`. Ordena por código ISO (ES, FR, PT...), no por nombre traducido. |

---

### Botones search_iec

Los botones de campos propios del IEC son directos. Los que usan campos de nodos relacionados requieren `relationship` en el YAML:
- `field_isced_f` y `title_1` (título del programme) usan `relationship: field_iec_programme` (IEC → programme)
- `field_institution_country` usa `relationship: field_programme_institution` (IEC → programme → institution)

| Botón | `data-sort-by` | Campo YAML | Relación |
|---|---|---|---|
| A-Z | `title` | `title` | — |
| ECTS | `field_iec_credits` | `field_iec_credits_value` | — |
| Term | `field_iec_term` | `field_iec_term_value` | — |
| Field of Study | `field_isced_f` | `field_isced_f_value` | `field_iec_programme` |
| Programme | `field_iec_programme_title` | `title_1` | `field_iec_programme` |
| Country | `field_institution_country` | `field_institution_country_value` | `field_programme_institution` |

---

### Botones institution_new_catalogue (programas)

Vista del catálogo de una institución concreta (`/catalogue/{nid}/programmes`). Tiene `use_ajax: true`, igual que `search_programme`.

| Botón | `data-sort-by` | Campo YAML |
|---|---|---|
| A-Z | `title` | `title` |
| Field of Study | `field_isced_f` | `field_isced_f_value` |
| Level | `field_eqf_level` | `field_eqf_level_value` |
| ECTS | `field_credits` | `field_credits_value` |

---

### Botones institution_new_catalogue (cursos)

Vista del catálogo de cursos de una institución (`/catalogue/{nid}/iecs`). Page_2 del mismo view, con sus propios `sorts:` en `display_options` (sobreescribe los del default display).

Los sorts con relación usan `relationship: field_iec_programme` (IEC → programme).

| Botón | `data-sort-by` | Campo YAML | Relación |
|---|---|---|---|
| A-Z | `title` | `title` | — |
| Programme | `field_iec_programme_title` | `title_1` | `field_iec_programme` |
| Field of Study | `field_isced_f` | `field_isced_f_value` | `field_iec_programme` |
| ECTS | `field_iec_credits` | `field_iec_credits_value` | — |
| Term | `field_iec_term` | `field_iec_term_value` | — |
