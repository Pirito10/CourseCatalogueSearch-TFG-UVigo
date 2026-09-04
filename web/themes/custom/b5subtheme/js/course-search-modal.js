(function (Drupal, once) {
  Drupal.behaviors.courseSearchModal = {
    attach: function (context) {
      once('course-search-modal', '#courseSearchModal', context).forEach(function (modal) {
        const courseList = modal.querySelector('#course-search-list');
        const addBtn    = modal.querySelector('#course-search-add-btn');
        const searchBtn = modal.querySelector('#course-search-btn');
        const results   = modal.querySelector('#course-search-results');

        function makeRow(index) {
          const row = document.createElement('div');
          row.className = 'course-search-row d-flex gap-2 mb-2';
          row.innerHTML =
            '<input type="text" class="form-control" placeholder="Course ' + index + '">' +
            '<button type="button" class="btn btn-outline-secondary btn-sm course-search-remove">✕</button>';
          row.querySelector('.course-search-remove').addEventListener('click', function () {
            row.remove();
          });
          return row;
        }

        function rowCount() {
          return courseList.querySelectorAll('.course-search-row').length;
        }

        addBtn.addEventListener('click', function () {
          courseList.appendChild(makeRow(rowCount() + 1));
        });

        searchBtn.addEventListener('click', function () {
          const terms = Array.from(courseList.querySelectorAll('input'))
            .map(function (i) { return i.value.trim(); })
            .filter(function (v) { return v !== ''; });

          if (!terms.length) {
            results.innerHTML = '<p class="text-muted small">Introduce al menos un curso.</p>';
            return;
          }

          results.innerHTML = '<p class="text-muted small">Buscando...</p>';
          searchBtn.disabled = true;

          fetch('/api/course-search', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ terms: terms }),
          })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              searchBtn.disabled = false;
              if (!data.programmes || !data.programmes.length) {
                results.innerHTML = '<p class="text-muted small">No se encontraron programas.</p>';
                return;
              }
              results.innerHTML = data.programmes.map(function (p) {
                return (
                  '<div class="card mb-2 border-0 shadow-sm rounded-0">' +
                    '<div class="card-body p-3">' +
                      '<h6 class="mb-1">' +
                        '<a href="' + p.url + '" target="_blank" class="text-decoration-none text-reset">' + p.title + '</a>' +
                      '</h6>' +
                      (p.institution ? '<small class="text-muted">' + p.institution + '</small>' : '') +
                      '<div class="mt-2">' +
                        '<span class="badge bg-dark rounded-0">' + p.score + '/' + p.total + ' courses</span>' +
                      '</div>' +
                    '</div>' +
                  '</div>'
                );
              }).join('');
            })
            .catch(function () {
              searchBtn.disabled = false;
              results.innerHTML = '<p class="text-danger small">Error al realizar la búsqueda.</p>';
            });
        });
      });
    },
  };
})(Drupal, once);
