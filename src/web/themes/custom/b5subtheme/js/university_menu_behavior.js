(function (Drupal, once) {
  Drupal.behaviors.universityMenuBehavior = {
    attach(context) {
      const toggles = once('universityMenuBehavior', context.querySelectorAll('.mobile-menu-toggle'));
      toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
          const navbar = document.querySelector('.navbar-collapse');
          if (navbar && bootstrap) {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(navbar);
            bsCollapse.toggle();
          }
        });
      });
    }
  };
})(Drupal, once);
