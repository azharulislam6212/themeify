/*
 * "Image with row" cards, see template-parts/image-rows.php. The cards are sticky, so each
 * one slides over the previous card, on every screen size:
 * - each card sticks just below the sticky header, a little lower than the card before it
 *   (--tf-ir-peek) so the covered cards' top edges stay visible;
 * - a card taller than the space under the header (a phone, a short window) sticks once
 *   its bottom reaches the bottom of the screen instead, so it is always seen in full
 *   before the next card covers it;
 * - a card shrinks and dims a little as the next one covers it, which gives the stack depth.
 */
(function () {
    var lists = document.querySelectorAll('[data-tf-ir]');
    if (!lists.length) {
        return;
    }

    var still = window.matchMedia('(prefers-reduced-motion: reduce)');
    var header = document.querySelector('.tf-header');
    var queued = false;

    function px(el, name, fallback) {
        var v = parseFloat(getComputedStyle(el).getPropertyValue(name));
        return isNaN(v) ? fallback : v;
    }

    // Sticky offset for each card.
    function layout() {
        var view = window.innerHeight;
        var below = header ? header.getBoundingClientRect().height : 0;

        lists.forEach(function (list) {
            var gap = px(list, '--tf-ir-gap-top', 12);
            var peek = px(list, '--tf-ir-peek', 14);
            var room = px(list, '--tf-ir-gap-bottom', 16);

            list.querySelectorAll('.tf-ir__card').forEach(function (card, i) {
                var ideal = below + gap + i * peek;
                var fit = view - card.offsetHeight - room;
                card.style.top = Math.round(Math.min(ideal, fit)) + 'px';
            });
            list.classList.add('is-stack');
        });
    }

    // How far each card is covered by the next one: 0 none, 1 fully.
    function progress() {
        queued = false;
        var active = !still.matches;

        lists.forEach(function (list) {
            var cards = list.querySelectorAll('.tf-ir__card');
            cards.forEach(function (card, i) {
                var next = cards[i + 1];
                var p = 0;
                if (active && next) {
                    // Layout height, not the box: the box already includes the shrink.
                    var h = card.offsetHeight;
                    p = (card.getBoundingClientRect().top + h - next.getBoundingClientRect().top) / h;
                    p = Math.min(1, Math.max(0, p));
                }
                card.style.setProperty('--tf-ir-p', p.toFixed(3));
            });
        });
    }

    function queue() {
        if (!queued) {
            queued = true;
            window.requestAnimationFrame(progress);
        }
    }

    function refresh() {
        layout();
        queue();
    }

    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', refresh);
    window.addEventListener('load', refresh); // fonts and images can change card heights
    if (still.addEventListener) {
        still.addEventListener('change', queue);
    }
    if (window.ResizeObserver) {
        var ro = new ResizeObserver(refresh);
        lists.forEach(function (list) {
            list.querySelectorAll('.tf-ir__card').forEach(function (card) {
                ro.observe(card);
            });
        });
    }
    refresh();
})();
