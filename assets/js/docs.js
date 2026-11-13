/*
 * Doc pages (single-themeify_doc.php):
 * - a copy button on every code block in the doc body;
 * - "Copy Markdown": the page title, intro and body as Markdown on the clipboard;
 * - "Open in ChatGPT / Claude": opens the assistant with a prompt that links to this page.
 */
(function () {
    'use strict';

    var i18n = window.themeifyDocs || {};
    var article = document.getElementById('docs-article');
    if (!article) {
        return;
    }

    var ICON_COPY = '<svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true" focusable="false"><rect x="5.5" y="5.5" width="8" height="8" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.3"/><path d="M10.5 3.5v-.5A1.5 1.5 0 0 0 9 1.5H4A1.5 1.5 0 0 0 2.5 3v5A1.5 1.5 0 0 0 4 9.5h.5" fill="none" stroke="currentColor" stroke-width="1.3"/></svg>';
    var ICON_DONE = '<svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true" focusable="false"><path d="m3.5 8.5 3 3 6-7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        // Fallback for http:// (e.g. a local site), where the Clipboard API is not available.
        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try {
                document.execCommand('copy') ? resolve() : reject();
            } catch (e) {
                reject(e);
            }
            document.body.removeChild(area);
        });
    }

    /* ---------- Copy buttons on code blocks ---------- */

    article.querySelectorAll('.tf-doc-body pre').forEach(function (pre) {
        if (pre.closest('.tf-codebox')) {
            return;
        }
        var box = document.createElement('div');
        box.className = 'tf-codebox';
        pre.parentNode.insertBefore(box, pre);
        box.appendChild(pre);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'tf-codebox__copy';
        btn.setAttribute('aria-label', i18n.copy || 'Copy code');
        btn.title = i18n.copy || 'Copy code';
        btn.innerHTML = ICON_COPY;
        box.appendChild(btn);

        btn.addEventListener('click', function () {
            copyText(pre.innerText.replace(/\n$/, '')).then(function () {
                btn.innerHTML = ICON_DONE;
                btn.classList.add('is-done');
                btn.setAttribute('aria-label', i18n.copied || 'Copied');
                setTimeout(function () {
                    btn.innerHTML = ICON_COPY;
                    btn.classList.remove('is-done');
                    btn.setAttribute('aria-label', i18n.copy || 'Copy code');
                }, 1600);
            });
        });
    });

    /* ---------- Page → Markdown ---------- */

    function inline(node) {
        var out = '';
        node.childNodes.forEach(function (n) {
            if (n.nodeType === 3) {
                out += n.nodeValue.replace(/\s+/g, ' ');
                return;
            }
            if (n.nodeType !== 1) {
                return;
            }
            var tag = n.tagName.toLowerCase();
            var inner = inline(n);
            if (tag === 'strong' || tag === 'b') {
                out += inner.trim() ? '**' + inner.trim() + '**' : '';
            } else if (tag === 'em' || tag === 'i') {
                out += inner.trim() ? '_' + inner.trim() + '_' : '';
            } else if (tag === 'code') {
                out += '`' + n.textContent + '`';
            } else if (tag === 'a') {
                out += '[' + inner.trim() + '](' + n.href + ')';
            } else if (tag === 'img') {
                out += '![' + (n.alt || '') + '](' + n.currentSrc + ')';
            } else if (tag === 'br') {
                out += '  \n';
            } else {
                out += inner;
            }
        });
        return out;
    }

    function list(el, depth) {
        var ordered = el.tagName.toLowerCase() === 'ol';
        var lines = [];
        Array.prototype.forEach.call(el.children, function (li, i) {
            if (li.tagName.toLowerCase() !== 'li') {
                return;
            }
            var clone = li.cloneNode(true);
            clone.querySelectorAll('ul, ol').forEach(function (sub) {
                sub.remove();
            });
            var pad = new Array(depth + 1).join('   ');
            lines.push(pad + (ordered ? (i + 1) + '. ' : '- ') + inline(clone).trim());
            li.querySelectorAll(':scope > ul, :scope > ol').forEach(function (sub) {
                lines.push(list(sub, depth + 1));
            });
        });
        return lines.join('\n');
    }

    function table(el) {
        var rows = Array.prototype.map.call(el.querySelectorAll('tr'), function (tr) {
            return '| ' + Array.prototype.map.call(tr.children, function (cell) {
                return inline(cell).trim().replace(/\|/g, '\\|');
            }).join(' | ') + ' |';
        });
        if (rows.length) {
            var cols = el.querySelector('tr').children.length;
            rows.splice(1, 0, '|' + new Array(cols + 1).join(' --- |'));
        }
        return rows.join('\n');
    }

    function blocks(node) {
        var parts = [];
        node.childNodes.forEach(function (n) {
            if (n.nodeType === 3) {
                if (n.nodeValue.trim()) {
                    parts.push(n.nodeValue.trim());
                }
                return;
            }
            if (n.nodeType !== 1 || n.matches('script, style, button, .tf-codebox__copy')) {
                return;
            }
            var tag = n.tagName.toLowerCase();
            var m = /^h([1-6])$/.exec(tag);
            if (m) {
                parts.push(new Array(+m[1] + 1).join('#') + ' ' + inline(n).trim());
            } else if (tag === 'p') {
                var p = inline(n).trim();
                if (p) {
                    parts.push(p);
                }
            } else if (tag === 'ul' || tag === 'ol') {
                parts.push(list(n, 0));
            } else if (tag === 'pre') {
                parts.push('```\n' + n.innerText.replace(/\n$/, '') + '\n```');
            } else if (tag === 'blockquote') {
                parts.push(blocks(n).split('\n').map(function (l) {
                    return '> ' + l;
                }).join('\n'));
            } else if (tag === 'table') {
                parts.push(table(n));
            } else if (tag === 'img') {
                parts.push(inline({ childNodes: [n] }));
            } else if (tag === 'hr') {
                parts.push('---');
            } else if (tag === 'details') {
                var sum = n.querySelector('summary');
                parts.push('**' + (sum ? inline(sum).trim() : '') + '**');
                var rest = n.cloneNode(true);
                var s = rest.querySelector('summary');
                if (s) {
                    s.remove();
                }
                parts.push(blocks(rest));
            } else {
                var inner = blocks(n);
                if (inner) {
                    parts.push(inner);
                }
            }
        });
        return parts.filter(Boolean).join('\n\n');
    }

    function pageMarkdown() {
        var title = article.querySelector('.tf-docs__header h1');
        var lead = article.querySelector('.tf-docs__header .tf-lead');
        var out = [];
        if (title) {
            out.push('# ' + title.textContent.trim());
        }
        if (lead) {
            out.push(lead.textContent.trim());
        }
        article.querySelectorAll('.tf-doc-body, .tf-sections').forEach(function (body) {
            out.push(blocks(body));
        });
        out.push('Source: ' + window.location.href.split('#')[0]);
        return out.filter(Boolean).join('\n\n').replace(/\n{3,}/g, '\n\n') + '\n';
    }

    /* ---------- Header actions ---------- */

    var actions = article.querySelector('[data-tf-doc-actions]');
    if (actions) {
        actions.hidden = false;

        var copyBtn = actions.querySelector('[data-tf-copy-page]');
        var copyLabel = actions.querySelector('[data-tf-copy-page-label]');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                copyText(pageMarkdown()).then(function () {
                    copyBtn.classList.add('is-done');
                    copyLabel.textContent = i18n.copied || 'Copied';
                    setTimeout(function () {
                        copyBtn.classList.remove('is-done');
                        copyLabel.textContent = i18n.copyPage || 'Copy Markdown';
                    }, 1600);
                });
            });
        }

        var url = window.location.href.split('#')[0];
        var prompt = (i18n.prompt || 'Read %s so I can ask questions about it.').replace('%s', url);
        var targets = {
            chatgpt: 'https://chatgpt.com/?q=' + encodeURIComponent(prompt),
            claude: 'https://claude.ai/new?q=' + encodeURIComponent(prompt)
        };
        actions.querySelectorAll('[data-tf-open]').forEach(function (a) {
            a.href = targets[a.dataset.tfOpen] || '#';
        });

        // Close the "Open" menu on outside click / Escape.
        var menu = actions.querySelector('.tf-doc-actions__open');
        if (menu) {
            document.addEventListener('click', function (e) {
                if (menu.open && !menu.contains(e.target)) {
                    menu.open = false;
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && menu.open) {
                    menu.open = false;
                    menu.querySelector('summary').focus();
                }
            });
        }
    }
})();
