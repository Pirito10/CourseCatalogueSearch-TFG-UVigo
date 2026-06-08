document.addEventListener('DOMContentLoaded', function () {
    // Selecciona todos los botones de toggle
    const toggleButtons = document.querySelectorAll('.toggle-button');
  
    toggleButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        const targetId = button.getAttribute('data-bs-target');
        const targetElement = document.querySelector(targetId);
  
        // Cambia las clases según el estado del colapsable
        if (targetElement.classList.contains('show')) {
          // Si el contenido está desplegado, lo cerramos y el botón pierde el fondo
          button.classList.remove('btn-primary');
          button.classList.add('btn-outline-primary');
        } else {
          // Si el contenido está cerrado, lo desplegamos y el botón gana fondo
          button.classList.remove('btn-outline-primary');
          button.classList.add('btn-primary');
        }
      });
    });
  
    // Asegúrate de que los botones iniciales estén en estado "sin color" al cargar la página
    toggleButtons.forEach(function (button) {
      const targetId = button.getAttribute('data-bs-target');
      const targetElement = document.querySelector(targetId);
  
      if (targetElement && targetElement.classList.contains('show')) {
        // Si el contenido está desplegado al cargar, marca el botón con color
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-primary');
      } else {
        // Si el contenido no está desplegado, deja el botón sin color
        button.classList.remove('btn-primary');
        button.classList.add('btn-outline-primary');
      }
    });
  
    // Maneja el evento de cambio de estado del colapsable para sincronizar visualización
    const collapsibleElements = document.querySelectorAll('.collapse');
    collapsibleElements.forEach(function (collapsible) {
      collapsible.addEventListener('hidden.bs.collapse', function () {
        // Encuentra el botón asociado y remueve el color de fondo
        const button = document.querySelector(`[data-bs-target="#${collapsible.id}"]`);
        if (button) {
          button.classList.remove('btn-primary');
          button.classList.add('btn-outline-primary');
        }
      });
  
      collapsible.addEventListener('shown.bs.collapse', function () {
        // Encuentra el botón asociado y agrega el color de fondo
        const button = document.querySelector(`[data-bs-target="#${collapsible.id}"]`);
        if (button) {
          button.classList.remove('btn-outline-primary');
          button.classList.add('btn-primary');
        }
  
        // Desliza automáticamente hacia el contenido mostrado
        //DESCOMENTAR SI QUEREMOS QUE SE DESPLACE
        /*collapsible.scrollIntoView({
          behavior: 'smooth', // Desplazamiento suave
          block: 'start', // Alinea al inicio del contenedor
        });*/
      });
    });
  });
  