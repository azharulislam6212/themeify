(function () {
    'use strict';

    /* Mobile navigation */
    var toggle = document.querySelector('.tf-nav-toggle');
    var nav = document.getElementById('tf-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!open));
            nav.classList.toggle('is-open', !open);
        });
    }

    /* Featured product slider */
    function initSlider(root) {
        var track = root.querySelector('.tf-slider__track');
        var slides = root.querySelectorAll('.tf-slide');
        var dotsWrap = root.querySelector('.tf-slider__dots');
        var prev = root.querySelector('.tf-slider__btn--prev');
        var next = root.querySelector('.tf-slider__btn--next');
        var delay = parseInt(root.dataset.autoplay, 10) || 0;
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var index = 0;
        var timer = null;
        var startX = null;

        if (!track || !slides.length) {
            return;
        }

        slides.forEach(function (_, i) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'tf-slider__dot';
            dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
            dot.addEventListener('click', function () { go(i); restart(); });
            dotsWrap.appendChild(dot);
        });

        function go(i) {
            index = (i + slides.length) % slides.length;
            track.style.transform = 'translateX(-' + index * 100 + '%)';
            dotsWrap.querySelectorAll('.tf-slider__dot').forEach(function (d, n) {
                d.classList.toggle('is-active', n === index);
                d.setAttribute('aria-current', n === index ? 'true' : 'false');
            });
            slides.forEach(function (s, n) {
                s.setAttribute('aria-hidden', n === index ? 'false' : 'true');
            });
        }

        function restart() {
            if (!delay || reduceMotion) {
                return;
            }
            clearInterval(timer);
            timer = setInterval(function () { go(index + 1); }, delay);
        }

        if (prev) prev.addEventListener('click', function () { go(index - 1); restart(); });
        if (next) next.addEventListener('click', function () { go(index + 1); restart(); });

        track.addEventListener('touchstart', function (e) {
            startX = e.touches[0].clientX;
        }, { passive: true });

        track.addEventListener('touchend', function (e) {
            if (startX === null) return;
            var dx = e.changedTouches[0].clientX - startX;
            if (Math.abs(dx) > 40) {
                go(index + (dx < 0 ? 1 : -1));
                restart();
            }
            startX = null;
        });

        root.addEventListener('mouseenter', function () { clearInterval(timer); });
        root.addEventListener('mouseleave', restart);

        go(0);
        restart();
    }

    document.querySelectorAll('.tf-slider').forEach(initSlider);

    /* Docs table of contents: highlight the section in view */
    var tocLinks = document.querySelectorAll('.tf-toc a[data-target]');
    if (tocLinks.length && 'IntersectionObserver' in window) {
        var linkMap = {};
        tocLinks.forEach(function (a) { linkMap[a.dataset.target] = a; });

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                tocLinks.forEach(function (a) {
                    a.classList.remove('is-active');
                    a.removeAttribute('aria-current');
                });
                var link = linkMap[entry.target.id];
                if (link) {
                    link.classList.add('is-active');
                    link.setAttribute('aria-current', 'true');
                }
            });
        }, { rootMargin: '-20% 0px -70% 0px' });

        Object.keys(linkMap).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) observer.observe(el);
        });
    }

    /* Docs theme switcher: close on outside click or Escape */
    var switchers = document.querySelectorAll('.tf-theme-switch');
    if (switchers.length) {
        document.addEventListener('click', function (e) {
            switchers.forEach(function (d) {
                if (d.open && !d.contains(e.target)) d.open = false;
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            switchers.forEach(function (d) {
                if (!d.open) return;
                d.open = false;
                d.querySelector('summary').focus();
            });
        });
    }

    /*
     * In-page links (cards, "On this page", breadcrumbs): scroll to the target
     * and open it if it is, or sits inside, a closed <details>.
     */
    function revealTarget(id, smooth) {
        var target = id ? document.getElementById(id) : null;
        if (!target) return false;
        for (var node = target; node; node = node.parentElement) {
            if (node.tagName === 'DETAILS') node.open = true;
        }
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        target.scrollIntoView({ behavior: smooth && !reduce ? 'smooth' : 'auto', block: 'start' });
        target.classList.remove('is-target');
        void target.offsetWidth; // restart the highlight animation
        target.classList.add('is-target');
        setTimeout(function () { target.classList.remove('is-target'); }, 1900);
        return true;
    }

    function hashId(hash) {
        try {
            return decodeURIComponent((hash || '').replace(/^#/, ''));
        } catch (err) {
            return '';
        }
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('a[href*="#"]') : null;
        if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (link.host !== location.host || link.pathname !== location.pathname) return;
        var id = hashId(link.hash);
        if (!id || !document.getElementById(id)) return;
        e.preventDefault();
        if (location.hash !== link.hash) history.pushState(null, '', link.hash);
        revealTarget(id, true);
    });

    window.addEventListener('hashchange', function () { revealTarget(hashId(location.hash), true); });
    if (location.hash) {
        window.addEventListener('load', function () { revealTarget(hashId(location.hash), false); });
    }

    /*
     * Docs search ("What do you want to do today?"): matching docs appear as you
     * type, theme chips narrow the search, question chips fill it in. Enter
     * submits the form to the search results page.
     */
    function initAsk(root) {
        var form = root.querySelector('.tf-ask__form');
        var input = root.querySelector('.tf-ask__input');
        var box = root.querySelector('.tf-ask__results');
        var status = root.querySelector('#tf-ask-status');
        var themeField = form && form.querySelector('input[name="doc_theme"]');
        var chips = root.querySelectorAll('.tf-chip[data-theme]');
        if (!form || !input || !box || !themeField) return;

        var names = {};
        chips.forEach(function (c) { if (c.dataset.id) names[c.dataset.id] = c.textContent; });
        var timer = null;
        var request = 0;

        function themeId() {
            var on = root.querySelector('.tf-chip[data-theme][aria-pressed="true"]');
            return on && on.dataset.id ? on.dataset.id : '';
        }

        function decode(html) {
            return new DOMParser().parseFromString(html, 'text/html').body.textContent || '';
        }

        function close() {
            box.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        }

        function show(nodes) {
            box.textContent = '';
            nodes.forEach(function (n) { box.appendChild(n); });
            box.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function note(text) {
            var p = document.createElement('p');
            p.className = 'tf-ask__note';
            p.textContent = text;
            return p;
        }

        function search() {
            var q = input.value.trim();
            root.classList.toggle('has-value', q !== '');
            if (q.length < 2) { close(); status.textContent = ''; return; }

            var id = ++request;
            var url = form.dataset.rest + (form.dataset.rest.indexOf('?') === -1 ? '?' : '&')
                + 'search=' + encodeURIComponent(q) + '&per_page=6&_fields=id,title,link,doc_theme';
            if (themeId()) url += '&doc_theme=' + themeId();

            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                .then(function (docs) {
                    if (id !== request) return; // a newer search is running
                    if (!docs.length) {
                        show([note(form.dataset.empty)]);
                        status.textContent = form.dataset.empty;
                        return;
                    }
                    var list = document.createElement('ul');
                    list.className = 'tf-ask__list';
                    docs.forEach(function (doc) {
                        var li = document.createElement('li');
                        var a = document.createElement('a');
                        a.href = doc.link;
                        a.className = 'tf-ask__hit';
                        var t = document.createElement('span');
                        t.textContent = decode(doc.title.rendered);
                        a.appendChild(t);
                        var theme = doc.doc_theme && names[doc.doc_theme[0]];
                        if (theme && !themeId()) {
                            var s = document.createElement('small');
                            s.textContent = theme;
                            a.appendChild(s);
                        }
                        li.appendChild(a);
                        list.appendChild(li);
                    });
                    show([list, note(form.dataset.all)]);
                    status.textContent = form.dataset.count.replace('%d', docs.length);
                })
                .catch(function () {
                    if (id !== request) return;
                    show([note(form.dataset.error)]);
                });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(search, 180);
        });
        input.addEventListener('focus', function () { if (box.childNodes.length && input.value.trim().length > 1) { box.hidden = false; input.setAttribute('aria-expanded', 'true'); } });

        // Arrow keys move between the input and the matches; Escape closes them.
        root.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !box.hidden) { close(); input.focus(); return; }
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            var links = Array.prototype.slice.call(box.querySelectorAll('a'));
            if (box.hidden || !links.length) return;
            var i = links.indexOf(document.activeElement);
            if (document.activeElement !== input && i === -1) return;
            e.preventDefault();
            var next = e.key === 'ArrowDown' ? i + 1 : i - 1;
            if (next < 0) input.focus();
            else links[Math.min(next, links.length - 1)].focus();
        });

        document.addEventListener('click', function (e) {
            if (!form.contains(e.target)) close();
        });

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                chips.forEach(function (c) { c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
                themeField.value = chip.dataset.theme;
                themeField.disabled = !chip.dataset.theme; // no empty doc_theme in the URL
                input.placeholder = chat && !chat.hidden ? form.dataset.followup : chip.dataset.placeholder;
                search();
            });
        });

        /* AI answers: Enter asks Claude (see inc/docs-ai.php) and shows the answer here. */
        var aiUrl = form.dataset.ai;
        var chat = root.querySelector('.tf-ask__chat');
        var turns = root.querySelector('.tf-ask__turns');
        var questions = root.querySelector('.tf-ask__questions');
        var history = [];
        var busy = false;

        function el(tag, cls, text) {
            var n = document.createElement(tag);
            if (cls) n.className = cls;
            if (text) n.textContent = text;
            return n;
        }

        function resultsUrl(q) {
            var url = form.getAttribute('action') + (form.getAttribute('action').indexOf('?') === -1 ? '?' : '&')
                + 's=' + encodeURIComponent(q) + '&post_type=themeify_doc';
            if (!themeField.disabled && themeField.value) url += '&doc_theme=' + encodeURIComponent(themeField.value);
            return url;
        }

        function askAI(q) {
            if (busy) return;
            busy = true;
            clearTimeout(timer);
            request++; // drop live matches still on the way
            close();
            root.classList.add('is-busy');
            chat.hidden = false;
            if (questions) questions.hidden = true;

            var on = root.querySelector('.tf-chip[data-theme][aria-pressed="true"]');
            var turn = el('article', 'tf-turn');
            var head = el('h2', 'tf-turn__q', q);
            if (on && on.dataset.id) head.appendChild(el('span', 'tf-turn__theme', on.textContent));
            var body = el('div', 'tf-turn__a');
            var loading = el('p', 'tf-turn__loading');
            loading.appendChild(el('span', 'tf-spinner'));
            loading.appendChild(document.createTextNode(form.dataset.reading));
            body.appendChild(loading);
            turn.appendChild(head);
            turn.appendChild(body);
            turns.appendChild(turn);
            turn.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

            var searchLink = resultsUrl(q);
            input.value = '';
            input.placeholder = form.dataset.followup;
            root.classList.remove('has-value');

            fetch(aiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: q, theme: themeField.disabled ? '' : themeField.value, history: history })
            })
                .then(function (r) {
                    return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; });
                })
                .then(function (res) {
                    if (!res.ok || typeof res.d.html !== 'string') throw res;
                    body.innerHTML = res.d.html; // built and sanitized on the server (wp_kses_post)
                    if (res.d.sources && res.d.sources.length) {
                        var src = el('div', 'tf-turn__sources');
                        src.appendChild(el('p', 'tf-turn__label', form.dataset.sources));
                        var ul = el('ul');
                        res.d.sources.forEach(function (s) {
                            var li = el('li');
                            var a = el('a', '', s.title);
                            a.href = s.url;
                            li.appendChild(a);
                            if (!(on && on.dataset.id) && s.theme) li.appendChild(el('small', '', s.theme));
                            ul.appendChild(li);
                        });
                        src.appendChild(ul);
                        body.appendChild(src);
                    }
                    history.push({ q: q, a: res.d.markdown || '' });
                    history = history.slice(-3);
                })
                .catch(function (res) {
                    body.textContent = '';
                    body.appendChild(el('p', 'tf-turn__error', (res && res.d && res.d.message) || form.dataset.failed));
                    var p = el('p');
                    var a = el('a', '', form.dataset.results);
                    a.href = searchLink;
                    p.appendChild(a);
                    body.appendChild(p);
                })
                .then(function () {
                    busy = false;
                    root.classList.remove('is-busy');
                    input.focus({ preventScroll: true });
                });
        }

        var resetBtn = root.querySelector('.tf-ask__reset');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (busy) return;
                history = [];
                turns.textContent = '';
                chat.hidden = true;
                if (questions) questions.hidden = false;
                var on = root.querySelector('.tf-chip[data-theme][aria-pressed="true"]');
                if (on) input.placeholder = on.dataset.placeholder;
                input.value = '';
                root.classList.remove('has-value');
                input.focus();
            });
        }

        root.querySelectorAll('.tf-chip[data-question]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                if (aiUrl) { askAI(chip.dataset.question); return; }
                input.value = chip.dataset.question;
                input.focus();
                search();
            });
        });

        form.addEventListener('submit', function (e) {
            var q = input.value.trim();
            if (!q) { e.preventDefault(); input.focus(); return; }
            if (aiUrl && chat && window.fetch) { e.preventDefault(); askAI(q); }
        });

        root.classList.toggle('has-value', input.value.trim() !== '');
    }

    document.querySelectorAll('.tf-ask').forEach(initAsk);

    /* Copy buttons on code blocks */
    document.querySelectorAll('.tf-code').forEach(function (block) {
        var btn = block.querySelector('.tf-code__copy');
        var code = block.querySelector('code');
        if (!btn || !code || !navigator.clipboard) return;
        btn.addEventListener('click', function () {
            navigator.clipboard.writeText(code.textContent).then(function () {
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
            });
        });
    });
})();
