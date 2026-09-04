document.addEventListener('DOMContentLoaded', function () {
  const sections = document.querySelectorAll('.degree-section');
  const navLinks = document.querySelectorAll('.nav-link'); // Para pantallas grandes
  const dropdownItems = document.querySelectorAll('.dropdown-item'); // Para pantallas pequeñas
  const dropdownButton = document.getElementById('degreeDropdownButton');

  // Función para actualizar el texto del botón con la sección actual
  function updateDropdownButton(activeText) {
    console.log('Updating dropdown button with text:', activeText); // Debugging
    if (window.innerWidth <= 992) { // Solo en pantallas pequeñas
      dropdownButton.textContent = activeText;
      console.log('Dropdown button text updated to:', dropdownButton.textContent); // Debugging
    }
  }

  // Añadir evento de clic a cada enlace del menú (pantallas grandes)
  /*
  navLinks.forEach(link => {
    link.addEventListener('click', function () {
      console.log('Clicked link:', this.textContent); // Debugging
      navLinks.forEach(nav => nav.classList.remove('active'));
      this.classList.add('active');

      // Actualizar el texto del botón cuando se selecciona una sección
      updateDropdownButton(this.textContent);
    });
  });*/

  // Añadir evento de clic a los elementos del menú desplegable (pantallas pequeñas)
  dropdownItems.forEach(item => {
    item.addEventListener('click', function () {
      console.log('Clicked dropdown item:', this.textContent); // Debugging
      dropdownItems.forEach(item => item.classList.remove('active'));
      this.classList.add('active');

      // Actualizar el texto del botón con la sección seleccionada
      updateDropdownButton(this.textContent);
    });
  });

  // Función para detectar la sección activa con IntersectionObserver (aplica para pantallas grandes y pequeñas)
  const sectionObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.getAttribute('id');
        console.log('Section is intersecting:', id); // Debugging
        navLinks.forEach(link => {
          link.classList.remove('active');
          if (link.getAttribute('href').substring(1) === id) {
            link.classList.add('active');
            updateDropdownButton(link.textContent); // Actualizar el botón con la sección visible
          }
        });
      }
    });
  }, {
    rootMargin: '-100px 0px -50% 0px', // Ajuste el valor según la altura del encabezado fijo
    threshold: [0.1, 0.5, 0.9] // Varias intersecciones para mejor detección
  });

  // Observar cada sección
  sections.forEach(section => {
    console.log('Observing section:', section.getAttribute('id')); // Debugging
    sectionObserver.observe(section);
  });

  // Actualizar el texto del botón cuando se redimensiona la ventana
  window.addEventListener('resize', function () {
    console.log('Window resized. Current width:', window.innerWidth); // Debugging
    const activeLink = document.querySelector('.nav-link.active') || document.querySelector('.dropdown-item.active');
    if (activeLink) {
      updateDropdownButton(activeLink.textContent);
    }
    
  });
});

