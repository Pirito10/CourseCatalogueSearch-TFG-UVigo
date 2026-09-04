# Course Catalogue Search

_Course Catalogue Search_ is an **Online Course Catalogue Search and Filtering System** developed as the Bachelor's Thesis for the course "[Trabajo de Fin de Grado](https://secretaria.uvigo.gal/docnet-nuevo/guia_docent/?ensenyament=V05G301V01&assignatura=V05G301V01991&any_academic=2025_26)" in the Telecommunications Engineering Degree at the Universidad de Vigo (2025 - 2026).

## About The Project
This project implements a set of search and filtering improvements for the course catalogue of the [DACEM](https://dacem.eu) platform (_Digitising Academic Catalogues for Enhanced Mobility_), an Erasmus+ course-catalogue system built on Drupal 10 for European higher education institutions and their exchange students. It replaces the platform's exact-substring title search with a relevance-ranked engine, and adds configurable result ordering and cross-language querying. The system integrates concepts such as information retrieval, approximate string matching with the Levenshtein distance, content indexing with Search API, neural machine translation, and interception of Drupal's Views query pipeline through hooks.

The project features:
- Multi-criteria result ordering across the search and institution-catalogue views.
- One-click toggle between ascending and descending order.
- Typo-tolerant fuzzy search based on per-word Levenshtein distance.
- Empirically calibrated scoring with exact, prefix, and edit-distance levels.
- Search over multiple indexed fields, across all indexed languages.
- Relevance ranking applied at the SQL level, consistent with pagination and user-selected ordering.
- Query normalisation that ignores case and diacritics.
- Two-tier query translation to English: static academic dictionary plus self-hosted LibreTranslate engine.
- Original query term always kept as a translation fallback.
- Transparent integration via Views hooks and Search API, with no data-model or interface changes.
- Design for an advanced search ranking programmes by educational-component affinity.

## How To Run
### Requirements
Make sure you have [Docker](https://www.docker.com) and [DDEV](https://ddev.readthedocs.io/en/stable/#installation) installed on your system.

### Usage
Start the environment and install the dependencies with:
```bash
cd src
ddev start
ddev composer install
```
Once the dependencies are installed, import the bundled test database and rebuild the cache with:
```bash
ddev import-db --file=../db/test-data.sql.gz
ddev drush cache:rebuild
```

Then, open your web browser, navigate to `https://prueba.ddev.site` and browse to `/search-programme`, `/search-iec`, or an institution's catalogue.

To sign in as administrator, generate a one-time login link with:
```bash
ddev drush user:login
```

## About The Code
Refer to [`Memoria.pdf`](docs/Memoria.pdf) for an in-depth explanation of the project, the starting platform, the functional and non-functional requirements, the design and implementation of each feature, the fuzzy-search calibration process, and the validation results.

Refer to [`Presentación.pdf`](docs/Presentación.pdf) for a high-level overview of the project.

The [`src/`](src) directory holds a snapshot of the DACEM platform; the contribution of this thesis is limited to a few custom modules and the site theme.

The LaTeX source of the report is available in [`memoria/`](memoria).
