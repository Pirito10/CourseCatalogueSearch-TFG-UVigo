(function (Drupal, once) {
  Drupal.behaviors.iecMobilityAutosubmit = {
    attach(context) {
      // Busca el form expuesto solo una vez por render
      once('iec-mobility-autosubmit', 'form.js-iec-exposed-form', context).forEach((form) => {
        // Checkbox del filtro (selector flexible por si Views cambia IDs/names)
        const cb =
          form.querySelector('input[type="checkbox"][name*="iec_avaliable_for_mobility"]') ||
          form.querySelector('input[type="checkbox"][id*="iec-avaliable-for-mobility"]');

        if (!cb) return;

        cb.addEventListener('change', () => {
          // Reset page a 0 para que no te quedes en page=2 al filtrar
          let page = form.querySelector('input[name="page"]');
          if (!page) {
            page = document.createElement('input');
            page.type = 'hidden';
            page.name = 'page';
            form.appendChild(page);
          }
          page.value = 0;

          // Si existe botón Apply, clic para respetar Views
          const apply =
            form.querySelector('input[type="submit"]') ||
            form.querySelector('button[type="submit"]');

          if (apply) apply.click();
          else form.submit();
        });
      });
    },
  };
})(Drupal, once);
