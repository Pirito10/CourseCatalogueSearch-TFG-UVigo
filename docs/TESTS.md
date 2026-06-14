# Tests — Búsqueda difusa (FuzzySearch)

## Contenido de prueba creado

| Título del nodo | Tipo |
|---|---|
| `Engineering and Computer Science` | Programme |
| `Medicine and Health Sciences` | Programme |
| `Biotechnology` | Programme |

---

## Batería de pruebas

| Término buscado | Resultado esperado | Escenario |
|---|---|---|
| `engineering` | ✅ aparece | Coincidencia exacta |
| `enginering` | ✅ aparece | 1 error tipográfico |
| `enginiring` | ✅ aparece | 2 errores tipográficos |
| `engnrng` | ❌ no aparece | Engineering muy mal escrito (vocales eliminadas) |
| `eng` | todos los resultados | Por debajo de la longitud mínima (3 chars < 4) |
| `engi` | ✅ aparece | Prefijo de 4 chars |
| `computer science` | ✅ aparece | Consulta multi-palabra |
| `helath` | ✅ aparece | 2 ediciones en palabra corta (typo de "health", 6 chars) |
| `biotenology` | ✅ aparece | 2 errores tipográficos (palabra larga) |
| `biotenolohy` | ✅ aparece | 3 errores tipográficos (palabra larga) |
| `zzzz` | ❌ no aparece | Término irrelevante (4 chars, sin coincidencias) |

---

## Configuraciones a comparar

| Parámetro | A | B (estricta) | C (laxa) | D (sin prefijo) | E (granular) |
|---|---|---|---|---|---|
| Edit threshold | `≤5→1, >5→2` | `≤5→1, >5→1` | `≤5→1, >5→3` | `≤5→1, >5→2` | `≤4→0, ≤7→1, >7→2` |
| Min score | `0.0` | `0.5` | `0.0` | `0.0` | `0.0` |
| Prefix score | `0.85` | `0.85` | `0.85` | `0.0` | `0.85` |

Caso discriminante por configuración:
- **B** → `enginiring` falla (2 edits en palabra larga, threshold baja a 1)
- **C** → `biotenolohy` pasa (3 edits, solo C lo tolera)
- **D** → `engi` falla (sin prefix matching)
- **E** → `helath` falla (2 edits en palabra de 6 chars, threshold=1 para ≤7)

**Implementación en `FuzzySearch.php`:**

**A:** `$threshold = $maxLen <= 5 ? 1 : 2;` · prefix score `0.85` · `if ($score > 0.0)`

**B:** `$threshold = 1;` · prefix score `0.85` · `if ($score > 0.5)`

**C:** `$threshold = $maxLen <= 5 ? 1 : 3;` · prefix score `0.85` · `if ($score > 0.0)`

**D:** `$threshold = $maxLen <= 5 ? 1 : 2;` · sin bloque de prefix match · `if ($score > 0.0)`

**E:** `$threshold = $maxLen <= 4 ? 0 : ($maxLen <= 7 ? 1 : 2);` · prefix score `0.85` · `if ($score > 0.0)`

---

## Resultados

> **Nota:** Consultas sin palabras de ≥4 caracteres (p.ej. `eng`) devuelven todos los resultados sin filtrar, porque el algoritmo devuelve `NULL` y el hook no aplica ningún filtro. No es un error: consulta sin palabras buscables = sin filtro.

### Config A

| Término | Esperado | Resultado | OK? |
|---|---|---|---|
| `engineering` | ✅ | ✅ | ✓ |
| `enginering` | ✅ | ✅ | ✓ |
| `enginiring` | ✅ | ✅ | ✓ |
| `engnrng` | ❌ | ❌ | ✓ |
| `eng` | todos los resultados | todos los resultados (sin filtro) | ✓ |
| `engi` | ✅ | ✅ | ✓ |
| `computer science` | ✅ | ✅ | ✓ |
| `helath` | ✅ | ✅ | ✓ |
| `biotenology` | ✅ | ✅ | ✓ |
| `biotenolohy` | ✅ | ❌ | ✗ |
| `zzzz` | ❌ | ❌ | ✓ |

### Config B (estricta)

| Término | Esperado | Resultado | OK? |
|---|---|---|---|
| `engineering` | ✅ | ✅ | ✓ |
| `enginering` | ✅ | ✅ | ✓ |
| `enginiring` | ✅ | ❌ | ✗ |
| `engnrng` | ❌ | ❌ | ✓ |
| `eng` | todos los resultados | todos los resultados | ✓ |
| `engi` | ✅ | ✅ | ✓ |
| `computer science` | ✅ | ✅ | ✓ |
| `helath` | ✅ | ❌ | ✗ |
| `biotenology` | ✅ | ❌ | ✗ |
| `biotenolohy` | ✅ | ❌ | ✗ |
| `zzzz` | ❌ | ❌ | ✓ |

### Config C (laxa)

| Término | Esperado | Resultado | OK? |
|---|---|---|---|
| `engineering` | ✅ | ✅ | ✓ |
| `enginering` | ✅ | ✅ | ✓ |
| `enginiring` | ✅ | ✅ | ✓ |
| `engnrng` | ❌ | ❌ | ✓ |
| `eng` | todos los resultados | todos los resultados | ✓ |
| `engi` | ✅ | ✅ | ✓ |
| `computer science` | ✅ | ✅ | ✓ |
| `helath` | ✅ | ✅ | ✓ |
| `biotenology` | ✅ | ✅ | ✓ |
| `biotenolohy` | ✅ | ✅ | ✓ |
| `zzzz` | ❌ | ❌ | ✓ |

### Config D (sin prefijo)

| Término | Esperado | Resultado | OK? |
|---|---|---|---|
| `engineering` | ✅ | ✅ | ✓ |
| `enginering` | ✅ | ✅ | ✓ |
| `enginiring` | ✅ | ✅ | ✓ |
| `engnrng` | ❌ | ❌ | ✓ |
| `eng` | todos los resultados | todos los resultados | ✓ |
| `engi` | ✅ | ❌ | ✗ |
| `computer science` | ✅ | ✅ | ✓ |
| `helath` | ✅ | ✅ | ✓ |
| `biotenology` | ✅ | ✅ | ✓ |
| `biotenolohy` | ✅ | ❌ | ✗ |
| `zzzz` | ❌ | ❌ | ✓ |

### Config E (granular)

| Término | Esperado | Resultado | OK? |
|---|---|---|---|
| `engineering` | ✅ | ✅ | ✓ |
| `enginering` | ✅ | ✅ | ✓ |
| `enginiring` | ✅ | ✅ | ✓ |
| `engnrng` | ❌ | ❌ | ✓ |
| `eng` | todos los resultados | todos los resultados | ✓ |
| `engi` | ✅ | ✅ | ✓ |
| `computer science` | ✅ | ✅ | ✓ |
| `helath` | ✅ | ❌ | ✗ |
| `biotenology` | ✅ | ✅ | ✓ |
| `biotenolohy` | ✅ | ❌ | ✗ |
| `zzzz` | ❌ | ❌ | ✓ |

---

## Resumen

| Término | A | B | C | D | E |
|---|---|---|---|---|---|
| `engineering` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `enginering` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `enginiring` | ✓ | ✗ | ✓ | ✓ | ✓ |
| `engnrng` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `eng` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `engi` | ✓ | ✓ | ✓ | ✗ | ✓ |
| `computer science` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `helath` | ✓ | ✗ | ✓ | ✓ | ✗ |
| `biotenology` | ✓ | ✗ | ✓ | ✓ | ✓ |
| `biotenolohy` | ✗ | ✗ | ✓ | ✗ | ✗ |
| `zzzz` | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Fallos** | **1** | **4** | **0** | **2** | **2** |
