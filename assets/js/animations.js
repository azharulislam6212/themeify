/*
 * Entrance animations with Motion (https://motion.dev). Config and pre-hide: inc/animations.php.
 *
 * Every matched element (heading, text, button, image, card, header and footer items) starts
 * hidden and animates in the first time it enters the screen: on page load for what is already
 * visible, and while scrolling for the rest. Elements that enter together are staggered in page
 * order. When an animation ends its inline styles are removed, so hover effects and the theme's
 * own transforms (sticky image rows, carousels) work as before.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var cfg = window.themeifyAnim;
    var M = window.Motion;

    if (!root.classList.contains('tf-anim')) {
        return; // reduced motion, or the fail-safe already showed the page
    }
    if (!cfg || !M || !M.animate || !M.inView) {
        root.classList.remove('tf-anim');
        return;
    }
    window.tfAnimReady = true;

    // Fail-safe: if anything below throws, show the whole page without animations.
    function showAll() {
        root.classList.remove('tf-anim', 'tf-anim-on');
    }

    try {

    var EASE = [0.22, 1, 0.36, 1];
    var STEP = 0.08;     // stagger between elements that enter together (s)
    var MAX_STEPS = 10;  // cap so long lists don't wait forever

    /*
     * Presets: start state for each kind of element. The end state is the element's own CSS
     * (no transform, no blur, its normal opacity).
     */
    var PRESETS = {
        header:  { y: -18, duration: 0.8 },
        line:    { y: 60, blur: 10, duration: 1.1 },
        heading: { y: 40, blur: 8, duration: 1 },
        text:    { y: 26, duration: 0.85 },
        button:  { y: 20, scale: 0.9, duration: 0.8 },
        media:   { y: 60, scale: 0.94, duration: 1.2 },
        card:    { y: 50, duration: 0.95 },
        // Featured cards: the card rises, then its number pops, the sparkle spins in and the
        // heading and text follow.
        tile:    {
            y: 70, scale: 0.95, duration: 1.1,
            inner: [
                { sel: '.tf-fc__num', after: 0.3, duration: 0.7, transform: 'scale(0.4)', to: 'scale(1)' },
                { sel: '.tf-fc__spark', after: 0.4, duration: 1, transform: 'rotate(-180deg) scale(0)', to: 'rotate(0deg) scale(1)' },
                { sel: '.tf-fc__name', after: 0.35, duration: 0.9, transform: 'translateY(24px)', to: 'translateY(0px)', blur: 6 },
                { sel: '.tf-fc__desc', after: 0.45, duration: 0.9, transform: 'translateY(18px)', to: 'translateY(0px)' }
            ]
        },
        // Testimonials carousel: the cards rise in one after another, then their stars pop in.
        reviews: {
            y: 0, duration: 0.6,
            inner: [
                { sel: '.tf-tm__card', after: 0, stagger: 0.12, duration: 1.1, transform: 'translateY(60px) scale(0.94)', to: 'translateY(0px) scale(1)' },
                { sel: '.tf-tm__star-wrap', after: 0.45, stagger: 0.05, duration: 0.6, transform: 'scale(0) rotate(-90deg)', to: 'scale(1) rotate(0deg)' },
                { sel: '.tf-tm__mark', after: 0.6, stagger: 0.12, duration: 0.9, transform: 'translateX(-14px)', to: 'translateX(0px)' }
            ]
        },
        // FAQ: each question slides in from the right, then its +/× icon spins in.
        faq:     {
            x: 70, duration: 1,
            inner: [
                { sel: '.tf-qa__icon svg', after: 0.35, duration: 0.8, transform: 'rotate(-180deg) scale(0.3)', to: 'rotate(0deg) scale(1)' }
            ]
        },
        rule:    { clip: true, duration: 1.2 },
        draw:    { clip: true, duration: 1.8 }
    };

    var skipSel = cfg.skip.join(', ');
    var targets = [];

    function inSkipZone(el) {
        return !!el.closest(skipSel);
    }

    // First matching group wins; nested matches are skipped so blocks move as one.
    cfg.groups.forEach(function (group) {
        var type = group[0];
        document.querySelectorAll(group[1]).forEach(function (el) {
            if (el.hasAttribute('data-tf') || inSkipZone(el)) return;
            if (el.parentElement && el.parentElement.closest('[data-tf]')) return;
            if (el.querySelector('[data-tf]')) return;
            el.setAttribute('data-tf', type);
            targets.push(el);
        });
    });

    // Hand over from the pre-hide selectors to the [data-tf] markers.
    root.classList.add('tf-anim-on');
    root.classList.remove('tf-anim');

    if (!targets.length) {
        return;
    }

    function startTransform(p) {
        return 'translate(' + (p.x || 0) + 'px, ' + (p.y || 0) + 'px) scale(' + (p.scale || 1) + ')';
    }

    /*
     * Animates el from opacity 0 (plus the given start transform / blur / clip) to its own CSS
     * state. The start state is applied right away so nothing shows during the delay; inline
     * styles are put back when it ends.
     */
    function play(el, from, duration, delay) {
        var style = el.style;
        var saved = {
            opacity: style.opacity,
            transform: style.transform,
            filter: style.filter,
            clipPath: style.clipPath
        };

        // The element's own opacity (e.g. .9), read before the inline start state is set.
        var opacity = parseFloat(getComputedStyle(el).opacity);
        if (isNaN(opacity) || opacity <= 0) opacity = 1;

        var keyframes = { opacity: [0, opacity] };
        if (from.clip) {
            keyframes.clipPath = ['inset(0 100% 0 0)', 'inset(0 0% 0 0)'];
        }
        if (from.transform) {
            keyframes.transform = [from.transform, from.to];
        }
        if (from.blur) {
            keyframes.filter = ['blur(' + from.blur + 'px)', 'blur(0px)'];
        }
        Object.keys(keyframes).forEach(function (prop) {
            style[prop] = typeof keyframes[prop][0] === 'number' ? String(keyframes[prop][0]) : keyframes[prop][0];
        });

        Promise.resolve(M.animate(el, keyframes, {
            duration: duration,
            delay: delay,
            ease: EASE
        })).then(function () {
            // Motion writes the end values inline right after the promise settles, so clear them
            // one tick later; otherwise they would block the theme's hover transforms.
            setTimeout(function () {
                style.opacity = saved.opacity;
                style.transform = saved.transform;
                style.filter = saved.filter;
                style.clipPath = saved.clipPath;
            }, 0);
        }, function () {});
    }

    function reveal(el, delay) {
        if (el.classList.contains('tf-in')) return;
        try {
            animateIn(el, delay);
        } catch (err) {
            // Never leave an element hidden: show it as it is.
            el.classList.add('tf-in');
            el.style.opacity = '';
            el.style.transform = '';
            el.style.filter = '';
            el.style.clipPath = '';
        }
    }

    function animateIn(el, delay) {
        var p = PRESETS[el.getAttribute('data-tf')] || PRESETS.text;

        // No paint happens between showing the element and setting its start state, so nothing flashes.
        el.classList.add('tf-in');
        play(el, p.clip ? { clip: true, blur: p.blur } : {
            transform: startTransform(p),
            to: 'translate(0px, 0px) scale(1)',
            blur: p.blur
        }, p.duration, delay);

        // Pieces inside the element that follow it in.
        (p.inner || []).forEach(function (step) {
            el.querySelectorAll(step.sel).forEach(function (child, i) {
                play(child, step, step.duration, delay + step.after + Math.min(i, MAX_STEPS) * (step.stagger || 0));
            });
        });
    }

    /*
     * Elements entering in the same frame are revealed together, staggered in page order.
     * The very first batch (page load) waits a moment so the page settles before it plays.
     */
    var queue = [];
    var queued = false;
    var firstBatch = true;
    var pending = targets.length;
    var stopWatching;

    function flush() {
        queued = false;
        var batch = queue.splice(0).filter(function (el, i, all) {
            return !el.classList.contains('tf-in') && all.indexOf(el) === i;
        });
        batch.sort(function (a, b) {
            return a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
        });

        var base = firstBatch ? 0.1 : 0;
        firstBatch = false;

        batch.forEach(function (el, i) {
            reveal(el, base + Math.min(i, MAX_STEPS) * STEP);
            pending--;
        });

        if (pending <= 0) {
            if (stopWatching) stopWatching();
            window.removeEventListener('scroll', onScroll);
        }
    }

    function enqueue(el) {
        queue.push(el);
        if (!queued) {
            queued = true;
            requestAnimationFrame(flush);
        }
    }

    stopWatching = M.inView(targets, function (el) {
        enqueue(el); // Motion stops watching an element once this returns
    }, { margin: '0px 0px -60px 0px', amount: 0 });

    /*
     * At the very bottom of a page the -60px margin above can keep the last line (footer
     * copyright) from ever counting as "in view", so reveal whatever is visible there.
     */
    var scrollQueued = false;
    function onScroll() {
        if (scrollQueued) return;
        scrollQueued = true;
        requestAnimationFrame(function () {
            scrollQueued = false;
            var bottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
            if (!bottom) return;
            targets.forEach(function (el) {
                if (el.classList.contains('tf-in')) return;
                var r = el.getBoundingClientRect();
                if (r.bottom > 0 && r.top < window.innerHeight && (r.width || r.height)) {
                    enqueue(el);
                }
            });
        });
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    } catch (err) {
        showAll();
        if (window.console) console.error('Themeify animations disabled:', err);
    }
})();
