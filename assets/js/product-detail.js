/*
 * Product detail page, see template-parts/product-detail.php.
 * - Gallery: a Swiper slider for the main image with a Swiper thumbnail row (thumbs module).
 * - Theme pills (variation attributes) pick the matching variation: its price replaces the shown
 *   price, the Buy Now link points to its link, and the gallery slides to its image.
 * - Sliding (or clicking a thumbnail) to an image that belongs to exactly one variation selects that
 *   variation's pills too.
 * - Buy Now is a plain link (cart and checkout are off); without a link it shows as unavailable.
 */
(function () {
    'use strict';

    var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-tf-pd]').forEach(function (root) {
        var mainEl = root.querySelector('[data-tf-pd-main]');
        var thumbsEl = root.querySelector('[data-tf-pd-thumbs]');
        var slides = mainEl ? Array.prototype.slice.call(mainEl.querySelectorAll('.tf-pd__slide')) : [];
        var thumbs = thumbsEl ? Array.prototype.slice.call(thumbsEl.querySelectorAll('.tf-pd__thumb')) : [];
        var form = root.querySelector('.tf-pd__form');
        var price = root.querySelector('[data-tf-pd-price]');
        var buy = root.querySelector('[data-tf-pd-buy]');
        var buyLabel = root.querySelector('[data-tf-pd-buy-label]');
        var dataEl = root.querySelector('[data-tf-pd-variations]');
        var labels = buy ? { buy: buy.dataset.labelBuy || 'Buy Now', na: buy.dataset.labelNa || 'Unavailable' } : null;
        var start = thumbsEl ? parseInt(thumbsEl.dataset.active, 10) || 0 : 0;
        var variations = [];
        var syncing = false; // true while a pill change moves the gallery

        try {
            variations = dataEl ? JSON.parse(dataEl.textContent) : [];
        } catch (e) {
            variations = [];
        }

        /* ---------- Sticky summary: centred on screen ---------- */

        var inner = root.querySelector('.tf-pd__summary-inner');
        var narrow = window.matchMedia('(max-width: 900px)');
        var HEADER = 96; // floating site header
        var BOTTOM = 32;

        function placeSummary() {
            if (!inner) {
                return;
            }
            if (narrow.matches) {
                inner.style.removeProperty('--tf-pd-top');
                return;
            }
            var free = window.innerHeight - HEADER - BOTTOM - inner.offsetHeight;
            inner.style.setProperty('--tf-pd-top', Math.round(HEADER + Math.max(0, free / 2)) + 'px');
        }

        placeSummary();
        window.addEventListener('resize', placeSummary);
        if (inner && 'ResizeObserver' in window) {
            new ResizeObserver(placeSummary).observe(inner); // e.g. price shown / hidden per variation
        }

        /* ---------- Gallery ---------- */

        var main = null;
        var current = start;

        function markThumbs(index) {
            thumbs.forEach(function (t, i) {
                t.classList.toggle('is-active', i === index);
                t.setAttribute('aria-pressed', i === index ? 'true' : 'false');
            });
        }

        function indexOfImage(imageId) {
            imageId = String(imageId || '');
            for (var i = 0; i < slides.length; i++) {
                if (slides[i].dataset.imageId === imageId) {
                    return i;
                }
            }
            return -1;
        }

        function goTo(index) {
            if (index < 0 || index >= slides.length) {
                return;
            }
            if (main) {
                main.slideTo(index);
            } else {
                showStatic(index);
            }
        }

        // Fallback without Swiper: show one slide at a time.
        function showStatic(index) {
            current = index;
            slides.forEach(function (s, i) {
                s.hidden = i !== index;
            });
            markThumbs(index);
            onImage(index);
        }

        if (mainEl && typeof Swiper !== 'undefined') {
            var thumbSwiper = null;
            if (thumbsEl) {
                thumbSwiper = new Swiper(thumbsEl, {
                    slidesPerView: 'auto', // thumbnails are a fixed 48 x 58 (CSS)
                    spaceBetween: 8,
                    watchSlidesProgress: true,
                    slideToClickedSlide: false,
                    speed: still ? 0 : 500
                });
            }
            main = new Swiper(mainEl, {
                initialSlide: start,
                spaceBetween: 10,
                speed: still ? 0 : 600,
                grabCursor: true,
                keyboard: { enabled: true, onlyInViewport: true },
                a11y: { prevSlideMessage: 'Previous image', nextSlideMessage: 'Next image' },
                thumbs: thumbSwiper ? { swiper: thumbSwiper, slideThumbActiveClass: 'is-active' } : undefined,
                on: {
                    slideChange: function (s) {
                        current = s.activeIndex;
                        markThumbs(current);
                        onImage(current);
                    }
                }
            });
            markThumbs(start);
            // Swiper's thumbs module only reacts to pointer taps; this also covers keyboard (Enter / Space).
            thumbs.forEach(function (t, i) {
                t.addEventListener('click', function () {
                    goTo(i);
                });
            });
        } else if (slides.length) {
            thumbs.forEach(function (t, i) {
                t.addEventListener('click', function () {
                    showStatic(i);
                });
            });
            showStatic(start);
        }

        /* ---------- Variations ---------- */

        function selected() {
            var out = {};
            if (form) {
                form.querySelectorAll('.tf-pd__radio:checked').forEach(function (r) {
                    out[r.name] = r.value;
                });
            }
            return out;
        }

        function findVariation(sel) {
            for (var i = 0; i < variations.length; i++) {
                var attrs = variations[i].attributes || {};
                var ok = true;
                for (var key in sel) {
                    if (Object.prototype.hasOwnProperty.call(sel, key)) {
                        var want = attrs[key] || '';
                        if (want !== '' && want !== sel[key]) {
                            ok = false;
                            break;
                        }
                    }
                }
                if (ok) {
                    return variations[i];
                }
            }
            return null;
        }

        function applyVariation(v, moveGallery) {
            if (price && v) {
                price.innerHTML = v.price_html || ''; // server-rendered price markup
                price.hidden = !v.price_html;
            }
            var can = !!(v && v.available && v.link);
            if (buy) {
                if (can) {
                    buy.href = v.link;
                    buy.removeAttribute('aria-disabled');
                } else {
                    buy.removeAttribute('href');
                    buy.setAttribute('aria-disabled', 'true');
                }
                buy.classList.toggle('is-disabled', !can);
            }
            if (buyLabel && labels) {
                buyLabel.textContent = can ? labels.buy : labels.na;
            }
            if (moveGallery && v && v.image_id) {
                var index = indexOfImage(v.image_id);
                if (index > -1 && index !== current) {
                    syncing = true;
                    goTo(index);
                    syncing = false;
                }
            }
        }

        // The gallery landed on an image: if it is exactly one variation's image, select that variation.
        function onImage(index) {
            if (syncing || !form || !variations.length || !slides[index]) {
                return;
            }
            var id = slides[index].dataset.imageId;
            var owners = variations.filter(function (v) {
                return String(v.image_id) === id;
            });
            if (owners.length !== 1) {
                return;
            }
            var attrs = owners[0].attributes || {};
            Object.keys(attrs).forEach(function (key) {
                if (!attrs[key]) {
                    return;
                }
                form.querySelectorAll('.tf-pd__radio').forEach(function (r) {
                    if (r.name === key && r.value === attrs[key]) {
                        r.checked = true;
                    }
                });
            });
            applyVariation(findVariation(selected()), false);
        }

        if (form && variations.length) {
            form.addEventListener('change', function (e) {
                if (e.target.classList.contains('tf-pd__radio')) {
                    applyVariation(findVariation(selected()), true);
                }
            });
        }
    });
})();
