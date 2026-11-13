/* Home page featured products carousel (Swiper), see template-parts/featured-carousel.php. */
(function () {
    if (typeof Swiper === 'undefined') {
        return;
    }

    document.querySelectorAll('[data-tf-fp]').forEach(function (el) {
        var section = el.closest('.tf-fp');

        new Swiper(el, {
            slidesPerView: 1.12,
            spaceBetween: 16,
            speed: 600,
            grabCursor: true,
            watchOverflow: true,
            keyboard: { enabled: true, onlyInViewport: true },
            a11y: {
                prevSlideMessage: 'Previous products',
                nextSlideMessage: 'Next products'
            },
            navigation: {
                prevEl: section.querySelector('.tf-fp__arrow--prev'),
                nextEl: section.querySelector('.tf-fp__arrow--next'),
                disabledClass: 'is-disabled'
            },
            breakpoints: {
                640: { slidesPerView: 2, spaceBetween: 24 },
                1024: { slidesPerView: 3, spaceBetween: 40 }
            }
        });
    });
})();
