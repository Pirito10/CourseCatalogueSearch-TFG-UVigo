(function (Drupal, once) {
  Drupal.behaviors.catalogueButtons = {
    attach(context) {
      const titulacionesButtons = once('catalogueButtons-titulaciones', context.querySelectorAll('#titulaciones-button'));
      const asignaturasButtons = once('catalogueButtons-asignaturas', context.querySelectorAll('#asignaturas-button'));
      const searchButtons = once('catalogueButtons-search', context.querySelectorAll('#search-button'));

      titulacionesButtons.forEach((btn) => {
        const asignaturasButton = document.getElementById('asignaturas-button');
        if (!btn || !asignaturasButton) return;
        btn.addEventListener('click', () => {
          btn.classList.add('selected');
          asignaturasButton.classList.remove('selected');
        });
      });

      asignaturasButtons.forEach((btn) => {
        const titulacionesButton = document.getElementById('titulaciones-button');
        if (!btn || !titulacionesButton) return;
        btn.addEventListener('click', () => {
          btn.classList.add('selected');
          titulacionesButton.classList.remove('selected');
        });
      });

      searchButtons.forEach((btn) => {
        const searchInput = document.getElementById('search-input');
        if (!btn || !searchInput) return;
        btn.addEventListener('click', () => {
          const searchTerm = (searchInput.value || '').toLowerCase();
          const carreras = document.querySelectorAll('.program-list li');
          carreras.forEach((carrera) => {
            const title = carrera.textContent.toLowerCase();
            carrera.style.display = title.includes(searchTerm) ? 'list-item' : 'none';
          });
        });
      });
    }
  };
})(Drupal, once);
