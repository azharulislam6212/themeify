/*
 * Home page testimonials carousel (Swiper), see template-parts/testimonials.php.
 * The active card is centred with its neighbours peeking in from both edges; it loops and
 * autoplays, pausing while hovered or focused. No autoplay for visitors who ask for reduced motion.
 */
(function () {
    if (typeof Swiper === 'undefined') {
        return;
    }

    var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-tf-tm]').forEach(function (el) {
        var swiper = new Swiper(el, {
            slidesPerView: 'auto',
            centeredSlides: true,
            loop: true,
            spaceBetween: 10,
            speed: 900,
            grabCursor: true,
            keyboard: { enabled: true, onlyInViewport: true },
            a11y: {
                prevSlideMessage: 'Previous testimonial',
                nextSlideMessage: 'Next testimonial'
            },
            autoplay: still ? false : {
                delay: 4000,
                disableOnInteraction: false,
                pauseOnMouseEnter: true
            }
        });

        // Keyboard users: hold still while focus is inside.
        if (swiper.autoplay && !still) {
            el.addEventListener('focusin', function () { swiper.autoplay.stop(); });
            el.addEventListener('focusout', function () { swiper.autoplay.start(); });
        }
    });
})();
