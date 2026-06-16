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
    environment:
      - LT_LOAD_ONLY=en,es,fr,cs,el,et,gl,hu,pt,sl
```

- **Por qué un docker-compose custom y no un add-on de `ddev get`**: es la forma oficial de DDEV de añadir servicios sin add-on disponible. Los servicios propios de DDEV (web, db...) se gestionan internamente a partir de `config.yaml`, no con ficheros docker-compose explícitos.
- **`LT_LOAD_ONLY`**: limita los modelos de idioma descargados a los 10 idiomas reales de DACEM (`cs, el, en, es, et, fr, gl, hu, pt-pt, sl` en Drupal; `pt-pt` se mapea a `pt` para LibreTranslate). Sin esta variable descargaría los ~30 idiomas disponibles.
- **Peso**: con los 10 idiomas, el contenedor en ejecución ocupa ~1.58 GB (modelos descargados + imagen base). Con solo 3 idiomas (en, es, fr) eran ~847 MB — cada idioma añade aproximadamente 100 MB.
- **Acceso**: desde el host, `http://localhost:5000`. Desde dentro de los contenedores DDEV (PHP), `http://libretranslate:5000` (nombre del servicio).
- **Persistencia**: los modelos se descargan al arrancar el contenedor y se guardan en su capa de escritura. Sobreviven a `ddev restart` pero se perderían con `ddev delete` (no hay volumen explícito).

#### `TranslationService.php`

Nueva clase en `web/modules/custom/custom_views_filters/src/TranslationService.php`. Punto de entrada: `getSearchTerms(string $input, ?string $sourceLang = NULL): array`.

```php
public static function getSearchTerms(string $input, ?string $sourceLang = NULL): array {
    $source = $sourceLang ?? static::uiLanguage();

    if ($source === 'en') {
      return [$input];
    }

    $targets = array_diff(static::LANGUAGES, [$source]);
    $terms = [$input];

    foreach (static::translateAll($input, $source, $targets) as $translated) {
      if ($translated !== NULL && $translated !== $input) {
        $terms[] = $translated;
      }
    }

    return $terms;
}
```

**Decisiones de diseño:**

1. **Idioma de origen = idioma de la UI de Drupal, nunca auto-detección.** Se probó `POST /detect` de LibreTranslate y resultó demasiado frágil para términos académicos cortos sin acentos:

   | Término | Detectado | Confianza |
   |---|---|---|
   | "ingenieria quimica" (sin acentos) | en | 0% |
   | "ingeniería química" | es | 90% |
   | "gestion empresarial" (sin acentos) | **fr** | 90% |
   | "matematicas aplicadas" (sin acentos) | **pt** | 100% |

   Incluso con confianza alta (90-100%) la detección puede ser incorrecta — los idiomas romances comparten demasiado vocabulario sin los acentos. Ningún umbral de confianza lo soluciona, así que se descartó la auto-detección por completo a favor de `\Drupal::languageManager()->getCurrentLanguage()->getId()`.

2. **Si el idioma de origen es inglés, no se traduce.** Todos los programas y cursos de DACEM están indexados como mínimo en inglés (lengua franca), así que una búsqueda en inglés ya cubre el catálogo completo sin necesidad de traducción.

3. **El término original siempre se incluye en el array devuelto** (`$terms = [$input]`), independientemente de si se traduce o no. Esto cubre dos casos a la vez:
   - Typos: si la traducción falla o produce basura, FuzzySearch contra el original todavía encuentra coincidencias con distancia de Levenshtein baja.
   - Usuario que escribe en un idioma distinto al de la UI: un usuario con la web en español que escribe "chemical" (inglés) encuentra "Chemical Engineering" vía el término original, sin que la traducción (es→en de "chemical") tenga que acertar.

4. **Traducciones fallidas o vacías se descartan silenciosamente** (`$translated !== NULL && $translated !== $input`). Si LibreTranslate no está disponible o devuelve error, la promesa correspondiente queda en estado `rejected` y `translateAll()` la trata como `NULL` — el sistema sigue funcionando solo con el término original, sin filtrar de más ni romper la búsqueda.

5. **Las 9 traducciones se piden en paralelo, no secuencialmente** (`translateAll()`, ver siguiente apartado). Es la optimización con más impacto sobre la latencia percibida.

#### Integración con `FuzzySearch.php`

`scoreFromIndex()` ahora pide los términos a `TranslationService` y ejecuta `scoreAndFilter()` una vez por término, fusionando los resultados y quedándose con el score más alto por NID:

```php
public static function scoreFromIndex(string $input, string $indexId = 'programmes'): ?array {
    $candidates = static::loadCandidatesFromIndex($indexId);
    $terms = TranslationService::getSearchTerms($input);

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

#### Rendimiento: por qué las búsquedas tardaban ~3s y cómo se redujo

**Diagnóstico.** Cada búsqueda en un idioma distinto al inglés dispara hasta 9 llamadas a LibreTranslate (una por idioma destino). Midiendo cada llamada por separado se encontraron tres causas distintas, todas reales y acumulativas:

1. **Memoria de la VM de WSL2 agotada y en swap.** Por defecto WSL2 solo reserva la mitad de la RAM del host. Con 7.7 GB asignados, la VM estaba usando 6.8 GB y 1.2 GB de swap (disco). Solución: crear `%UserProfile%\.wslconfig` con `memory=12GB` y reiniciar WSL2 (`wsl --shutdown` desde PowerShell, no desde dentro de WSL). Tras el cambio, la VM pasó a tener 11 GB disponibles sin uso de swap. Esto por sí solo no explicaba toda la lentitud, pero eliminaba un factor de ruido.

2. **Carga de modelos por worker de gunicorn, no compartida.** LibreTranslate corre con 4 procesos gunicorn (`worker: sync`), cada uno con su propia memoria. El modelo de un par de idiomas se carga de forma perezosa la primera vez que *ese worker concreto* lo necesita — no se comparte entre procesos. Confirmado experimentalmente monitorizando `/proc/<pid>/status` (`VmRSS`) de cada worker mientras se repetía la misma traducción: en cada intento lento, la memoria de un worker distinto subía ~90-100 MB, hasta que los 4 habían cargado el par y las peticiones pasaban a tardar 8-15ms de forma consistente.

   Un primer intento de precalentar con peticiones en paralelo (`&`) para los 9 idiomas a la vez **no funcionó** — con ráfagas simultáneas gunicorn no reparte las conexiones de forma equitativa entre los 4 workers, así que no hay garantía de que todos lleguen a cargar cada par. Repitiendo las peticiones **secuencialmente** (4-6 veces por idioma, una tras otra) sí se consiguió calentar los 4 workers de forma fiable.

3. **Coste de inferencia por petición, incluso con el modelo ya caliente.** Aún con todos los workers calentados para un idioma, traducir un texto nuevo (no visto antes) seguía costando 100-250ms por idioma — es el coste real de ejecutar el modelo de traducción, no solo de cargarlo. Con 9 llamadas **secuenciales**, eso suma ~1-1.3s solo en inferencia, más el overhead de conexión HTTP de cada llamada por separado.

**Solución aplicada: paralelizar las 9 llamadas HTTP.** En lugar de esperar cada traducción antes de pedir la siguiente, se lanzan las 9 a la vez con `postAsync()` de Guzzle (el cliente HTTP que usa `\Drupal::httpClient()`) y se espera a que todas terminen con `GuzzleHttp\Promise\Utils::settle()`:

```php
protected static function translateAll(string $input, string $source, array $targets): array {
    $client = \Drupal::httpClient();
    $promises = [];

    foreach ($targets as $lang) {
      $promises[$lang] = $client->postAsync(static::URL . '/translate', [
        'json' => ['q' => $input, 'source' => $source, 'target' => $lang],
        'timeout' => 5,
      ]);
    }

    $responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

    $translations = [];
    foreach ($responses as $lang => $result) {
      if ($result['state'] !== 'fulfilled') {
        $translations[$lang] = NULL;
        continue;
      }
      $data = json_decode((string) $result['value']->getBody(), TRUE);
      $translations[$lang] = $data['translatedText'] ?? NULL;
    }

    return $translations;
}
```

`Utils::settle()` (a diferencia de `unwrap()`) no lanza excepción si una promesa falla — cada resultado llega con `state: 'fulfilled'|'rejected'`, así que un fallo puntual de un idioma no rompe los demás.

**Resultado medido**, comparando siempre con términos nunca traducidos antes (para que la comparación sea justa) y usando el mismo cliente HTTP en ambos casos:

| Ronda | Secuencial | Paralelo | Mejora |
|---|---|---|---|
| 1 | 2.371s | 1.098s | 2.2× |
| 2 | 2.371s | 1.019s | 2.3× |
| 3 | 4.405s | 0.980s | 4.5× |
| 4 | 1.869s | 0.890s | 2.1× |

El tiempo total pasa de ser la suma de las 9 llamadas a ser aproximadamente el de la más lenta de las 9 — mejora consistente de 2-4.5× en todas las pruebas.

#### Limitaciones conocidas (líneas futuras)

- **Traducción de palabras sueltas sin contexto es poco fiable.** Probado con "informática" → traducido a inglés como "it" (Information Technology) en vez de "Computer Science". Con más contexto ("informática y telecomunicaciones") el modelo traduce correctamente ("information technology and telecommunications"). Posible mejora: diccionario manual de términos académicos comunes (informática, ingeniería, medicina...) como paso previo a LibreTranslate, usado solo para esos términos puntuales.
- **No se usa pivotado manual vía inglés.** LibreTranslate pivota automáticamente cuando no hay modelo directo entre dos idiomas, pero si el primer salto (origen→inglés) ya produce un resultado pobre, el pivote hereda el error (p. ej. es→en "informática"→"it", luego en→el de "it" da "το", el artículo griego "el", en vez de una traducción de "Information Technology").
- **Parámetro `alternatives` de LibreTranslate sin usar.** El endpoint `/translate` admite devolver traducciones alternativas además de la principal (`alternatives: 3`). Una mejora futura sería añadir cada alternativa como término de búsqueda adicional, aumentando la cobertura cuando la traducción principal es de baja calidad. No implementado por ahora — más llamadas a la API y más pasadas de FuzzySearch por el mismo beneficio incierto.
- **Idiomas sin modelo directo.** Con los 10 idiomas de DACEM cargados (`LT_LOAD_ONLY`), no todos los pares tienen modelo directo (p. ej. es→el); LibreTranslate pivota por inglés de forma transparente, heredando las limitaciones del punto anterior.
- **Sin caché de traducciones.** Cada búsqueda repetida del mismo término vuelve a llamar a LibreTranslate 9 veces, aunque el resultado sea siempre el mismo. Cachear por `(idioma_origen, término)` en la cache de Drupal eliminaría por completo la latencia de traducción para términos ya buscados antes, que en un buscador con autocompletado/escritura en vivo es el caso más común. No implementado por decidir primero si la paralelización era suficiente.
- **Sin precalentamiento de modelos al desplegar.** Confirmado que es viable si se hace bien (peticiones secuenciales, no en paralelo, 4-6 por idioma para cubrir los 4 workers de gunicorn), pero no se ha automatizado. Quedaría como script de arranque del contenedor o como tarea posterior al `ddev start`.

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
