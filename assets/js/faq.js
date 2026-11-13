/*
 * Home page FAQ accordion, see template-parts/faq.php. Each question is a <details>; this
 * animates the answer's height with Motion (https://motion.dev) when it is there, and keeps
 * only one question open at a time. Without Motion, or for visitors who ask for reduced motion,
 * the answers simply open and close.
 */
(function () {
    'use strict';

    var still = window.matchMedia('(prefers-reduced-motion: reduce)');
    var EASE = [0.22, 1, 0.36, 1];

    document.querySelectorAll('[data-tf-qa]').forEach(function (list) {
        var items = Array.prototype.slice.call(list.querySelectorAll('.tf-qa__item'));

        function animated() {
            return window.Motion && window.Motion.animate && !still.matches;
        }

        function open(item) {
            var panel = item.querySelector('.tf-qa__panel');
            item.open = true;
            item.classList.add('is-open');
            if (!panel || !animated()) return;

            var h = panel.scrollHeight;
            panel.style.overflow = 'hidden';
            Promise.resolve(window.Motion.animate(panel, {
                height: ['0px', h + 'px'],
                opacity: [0, 1]
            }, { duration: 0.5, ease: EASE })).then(function () {
                setTimeout(function () {
                    panel.style.height = '';
                    panel.style.opacity = '';
                    panel.style.overflow = '';
                }, 0);
            });
            var answer = panel.querySelector('.tf-qa__answer');
            if (answer) {
                window.Motion.animate(answer, { transform: ['translateY(-8px)', 'translateY(0px)'] }, { duration: 0.5, ease: EASE });
            }
        }

        function close(item) {
            var panel = item.querySelector('.tf-qa__panel');
            item.classList.remove('is-open');
            if (!panel || !animated()) {
                item.open = false;
                return;
            }

            panel.style.overflow = 'hidden';
            Promise.resolve(window.Motion.animate(panel, {
                height: [panel.offsetHeight + 'px', '0px'],
                opacity: [1, 0]
            }, { duration: 0.4, ease: EASE })).then(function () {
                // Still closing (not reopened in the meantime)?
                if (!item.classList.contains('is-open')) item.open = false;
                setTimeout(function () {
                    panel.style.height = '';
                    panel.style.opacity = '';
                    panel.style.overflow = '';
                }, 0);
            });
        }

        items.forEach(function (item) {
            if (item.open) item.classList.add('is-open');

            item.querySelector('.tf-qa__q').addEventListener('click', function (e) {
                e.preventDefault();
                if (item.classList.contains('is-open')) {
                    close(item);
                    return;
                }
                items.forEach(function (other) {
                    if (other !== item && other.classList.contains('is-open')) close(other);
                });
                open(item);
            });

            // Opened another way (e.g. the browser's find in page): keep the class in sync.
            item.addEventListener('toggle', function () {
                if (item.open && !item.classList.contains('is-open')) item.classList.add('is-open');
            });
        });
    });
})();
