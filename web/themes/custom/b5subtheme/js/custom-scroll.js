

/*
document.addEventListener('DOMContentLoaded', function () {
    const links = document.querySelectorAll('.nav-tabs .nav-link');
    links.forEach(link => {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector('.nav-tabs .nav-link.active').classList.remove('active');
        this.classList.add('active');
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          window.scrollTo({
            top: target.offsetTop - document.querySelector('.degree-nav').offsetHeight,
            behavior: 'smooth'
          });
        }
      });
    });
  
    // Marcar la sección activa al hacer scroll
    window.addEventListener('scroll', function () {
      const sections = document.querySelectorAll('.degree-section');
      let scrollPosition = document.documentElement.scrollTop || document.body.scrollTop;
  
      sections.forEach(section => {
        if (scrollPosition >= section.offsetTop - document.querySelector('.degree-nav').offsetHeight && 
            scrollPosition < section.offsetTop + section.offsetHeight) {
          document.querySelector('.nav-tabs .nav-link.active').classList.remove('active');
          document.querySelector(`.nav-tabs .nav-link[href="#${section.id}"]`).classList.add('active');
        }
      });
    });
  });
  */