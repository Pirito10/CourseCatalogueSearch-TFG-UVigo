(function (Drupal, once) {
  Drupal.behaviors.rsSubmenuScroll = {
    attach(context) {
      console.log('Librería rs_submenu_scroll cargada');

      const sections = once('rs-sections', context.querySelectorAll('.rs-section'));
      const navLinks = context.querySelectorAll('.rs-nav .nav-link');
      const dropdownItems = context.querySelectorAll('.dropdown-item');
      const dropdownButton = document.getElementById('rsDropdownButton');

      function updateDropdownButton(activeText) {
        if (window.innerWidth <= 992 && dropdownButton) {
          dropdownButton.textContent = activeText;
        }
      }

      navLinks.forEach((link) => {
        link.addEventListener('click', () => {
          navLinks.forEach((nav) => nav.classList.remove('active'));
          link.classList.add('active');
          updateDropdownButton(link.textContent);
        });
      });

      dropdownItems.forEach((item) => {
        item.addEventListener('click', () => {
          dropdownItems.forEach((i) => i.classList.remove('active'));
          item.classList.add('active');
          updateDropdownButton(item.textContent);
        });
      });

      const sectionObserver = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              const id = entry.target.getAttribute('id');
              navLinks.forEach((link) => {
                link.classList.remove('active');
                if (link.getAttribute('href')?.substring(1) === id) {
                  link.classList.add('active');
                  updateDropdownButton(link.textContent);
                }
              });
            }
          });
        },
        { rootMargin: '-100px 0px -50% 0px', threshold: [0.1, 0.5, 0.9] }
      );

      sections.forEach((section) => sectionObserver.observe(section));

      window.addEventListener('resize', () => {
        const activeLink =
          document.querySelector('.rs-nav .nav-link.active') ||
          document.querySelector('.rs-dropdown .dropdown-item.active');
        if (activeLink) updateDropdownButton(activeLink.textContent);
      });

      // ✅ Evita error de null en collapseElement
      const collapseElement = document.getElementById('campusCollapse');
      if (collapseElement) {
        collapseElement.addEventListener('show.bs.collapse', () => {
          const scrollY = window.scrollY;
          setTimeout(() => window.scrollTo({ top: scrollY }), 10);
        });
      }
    }
  };
})(Drupal, once);
