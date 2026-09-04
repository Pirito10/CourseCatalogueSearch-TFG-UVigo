(function () {
  'use strict';

  function createIndicator(carouselId, index) {
    const button = document.createElement('button');

    button.type = 'button';
    button.setAttribute('data-bs-target', `#${carouselId}`);
    button.setAttribute('data-bs-slide-to', index);

    if (index === 0) {
      button.classList.add('active');
      button.setAttribute('aria-current', 'true');
    }

    return button;
  }

  function createSlide(cards, isActive, shouldCenterCards) {
    const slide = document.createElement('div');
    const row = document.createElement('div');

    slide.classList.add('carousel-item');
    if (isActive) {
      slide.classList.add('active');
    }

    row.classList.add('row', 'gx-3');
    if (shouldCenterCards) {
      row.classList.add('justify-content-center');
    }
    cards.forEach((card) => row.appendChild(card.cloneNode(true)));

    slide.appendChild(row);
    return slide;
  }

  function rebuildBootstrapCarousel(carousel) {
    if (!window.bootstrap || !window.bootstrap.Carousel) {
      return;
    }

    const currentInstance = window.bootstrap.Carousel.getInstance(carousel);
    if (currentInstance) {
      currentInstance.dispose();
    }

    window.bootstrap.Carousel.getOrCreateInstance(carousel);
  }

  function initUniversityCarousel(carousel) {
    const carouselInner = carousel.querySelector('.carousel-inner');
    const indicators = carousel.querySelector('.carousel-indicators');

    if (!carouselInner || !indicators) {
      return;
    }

    const originalCards = Array.from(
      carouselInner.querySelectorAll('.carousel-item .row > .col-12')
    ).map((card) => card.cloneNode(true));

    if (!originalCards.length) {
      return;
    }

    let currentCardsPerSlide = null;

    function updateCarousel() {
      const cardsPerSlide = window.innerWidth <= 768 ? 1 : 3;

      if (cardsPerSlide === currentCardsPerSlide) {
        return;
      }

      currentCardsPerSlide = cardsPerSlide;
      carouselInner.innerHTML = '';
      indicators.innerHTML = '';

      for (let i = 0; i < originalCards.length; i += cardsPerSlide) {
        const slideIndex = i / cardsPerSlide;
        const cards = originalCards.slice(i, i + cardsPerSlide);

        carouselInner.appendChild(createSlide(cards, slideIndex === 0, cardsPerSlide === 1));
        indicators.appendChild(createIndicator(carousel.id, slideIndex));
      }

      rebuildBootstrapCarousel(carousel);
    }

    updateCarousel();
    window.addEventListener('resize', updateCarousel);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#universityCarousel').forEach(initUniversityCarousel);
  });
}());
