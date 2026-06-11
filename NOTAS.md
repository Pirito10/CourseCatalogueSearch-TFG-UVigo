# Notas de desarrollo — TFG DACEM

---

## Búsqueda difusa (FuzzySearch)

### Qué hace y por qué

El filtro `combine` estándar de Views hace un `LIKE '%término%'` en SQL — solo encuentra coincidencias exactas de subcadena. Un error tipográfico rompe la búsqueda. FuzzySearch reemplaza ese filtro con scoring por distancia de Levenshtein: "enginering" encuentra "Engineering", "mecanical" encuentra "Mechanical".

### Arquitectura

Dos piezas que trabajan juntas:

#### `web/modules/custom/custom_views_filters/src/FuzzySearch.php`
Clase PHP con toda la lógica de puntuación:
- **`scoreFromIndex()`** — punto de entrada. Recibe el texto buscado y el ID del índice, devuelve los NIDs ordenados por score.
- **`loadCandidatesFromIndex()`** — consulta el índice de Search API (no SQL directo) y devuelve todos los ítems indexados con sus campos de texto.
- **`scoreAndFilter()`** — para cada candidato, suma los scores palabra a palabra contra el término buscado. Usa el NID como clave para deduplicar (un mismo nodo puede aparecer en varios idiomas en el índice).
- **`wordScore()`** — compara dos palabras con distancia de Levenshtein: exacto=1.0, prefijo=0.85, 1 edición≈0.91, 2 ediciones≈menor, más diferencia=0.
- **`normalize()`** — minúsculas + elimina acentos (é→e).
- **`tokenize()`** — divide en palabras de mínimo 3 caracteres.
- **`$scores`** — array estático que comparte los scores entre los dos hooks.

#### `web/modules/custom/custom_views_filters/custom_views_filters.module`
- **`hook_views_query_alter`**: intercepta la query de `search_programme` o `search_iec`, llama a `FuzzySearch::scoreFromIndex()`, elimina la condición SQL del filtro combine (operator `formula`) y la sustituye por `WHERE nid IN (nids_con_score)`.
- **`hook_views_post_execute`**: reordena `$view->result` por score descendente, ya que SQL `IN` no garantiza orden. Se aplica a cualquier vista cuando `FuzzySearch::$scores` está relleno.

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
  → Se añade WHERE nid IN (NIDs fuzzy)
  → Drupal ejecuta la query → devuelve nodos fuzzy-matched
  → hook_views_post_execute reordena por score
  → El usuario ve los resultados más relevantes primero
```

---

### Configuración de Search API

**Módulos:** `search_api` + `search_api_db` (backend de base de datos, incluido como submódulo de `search_api`).

**Servidor (`Database Server`):**
- Minimum word length: **3** (coherente con `tokenize()`, que ya descarta palabras de menos de 3 caracteres)
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
- Campos: `title`

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
  $results[$nid] = ['nid' => $nid, 'score' => $score];
}
```

---

### Búsqueda en múltiples campos

`loadCandidatesFromIndex()` itera sobre un array de field names y concatena sus valores en un único texto que se pasa a `scoreAndFilter()`. Los campos que no existen en un índice concreto se saltan automáticamente (el `getField()` devuelve `null`), así que el mismo código funciona para `programmes` y `courses`.

```php
foreach (['title', 'institution'] as $fieldName) {
    $field = $item->getField($fieldName);
    if (!$field) { continue; }
    // ... concatenar valor a $text
}
```

**Cómo añadir un campo nuevo:**
1. Añadirlo al índice en el admin como Fulltext (`/admin/config/search/search-api/index/{id}/fields`)
2. Reindexar: `ddev drush search-api:index`
3. Exportar config: `ddev drush cex -y`
4. Añadir el machine name al array en `loadCandidatesFromIndex()`:
```php
foreach (['title', 'institution', 'nuevo_campo'] as $fieldName) {
```

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
