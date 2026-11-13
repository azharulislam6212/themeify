/**
 * Themeify blocks: Cards + Card.
 *
 * A card's "Scroll to" list shows the headings (H2-H4) and Details blocks on
 * the page. Their anchors are computed exactly like themeify_content_anchors()
 * in inc/section-render.php, so keep the two in sync.
 */
(function (wp) {
    'use strict';

    if (!wp || !wp.blocks || !wp.blockEditor) {
        return;
    }

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useMemo = wp.element.useMemo;
    var be = wp.blockEditor;
    var c = wp.components;
    var useSelect = wp.data.useSelect;
    var __ = wp.i18n.__;
    var decode = (wp.htmlEntities && wp.htmlEntities.decodeEntities) || function (s) { return s; };

    function plainText(html) {
        return decode(String(html == null ? '' : html).replace(/<[^>]*>/g, '')).trim();
    }

    function slugify(text) {
        var t = String(text);
        if (t.normalize) {
            t = t.normalize('NFD').replace(/[̀-ͯ]/g, '');
        }
        t = t.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return t || 'section';
    }

    // Same order and de-duplication as the server: document order, "-2", "-3"...
    function collectAnchors(blocks, out, used) {
        (blocks || []).forEach(function (block) {
            var a = block.attributes || {};
            var text = null;
            var kind = '';
            if (block.name === 'core/heading' && (a.level || 2) >= 2 && (a.level || 2) <= 4) {
                text = a.content;
                kind = __('Heading', 'themeify');
            } else if (block.name === 'core/details') {
                text = a.summary;
                kind = __('Expandable', 'themeify');
            }
            if (text !== null) {
                var title = plainText(text);
                if (title) {
                    var id = a.anchor;
                    if (!id) {
                        var base = slugify(title);
                        id = base;
                        for (var n = 2; used[id]; n++) {
                            id = base + '-' + n;
                        }
                    }
                    used[id] = true;
                    out.push({ id: id, label: kind + ': ' + title });
                }
            }
            collectAnchors(block.innerBlocks, out, used);
        });
    }

    wp.blocks.registerBlockType('themeify/cards', {
        apiVersion: 3,
        title: __('Cards', 'themeify'),
        description: __('A grid of cards. Each card links to a page, or scrolls down to a section on this page and opens it.', 'themeify'),
        category: 'design',
        icon: 'grid-view',
        keywords: ['card', 'grid', 'links', 'where to start'],
        supports: { html: false },
        edit: function () {
            var blockProps = be.useBlockProps({ className: 'tf-xcards' });
            var innerProps = be.useInnerBlocksProps(blockProps, {
                allowedBlocks: ['themeify/card'],
                template: [['themeify/card'], ['themeify/card']],
                orientation: 'horizontal'
            });
            return el('div', innerProps);
        },
        save: function () {
            return el('div', be.useInnerBlocksProps.save(be.useBlockProps.save({ className: 'tf-xcards' })));
        }
    });

    function CardEdit(props) {
        var a = props.attributes;
        var set = props.setAttributes;
        var blockProps = be.useBlockProps({ className: 'tf-xcard' });
        var blocks = useSelect(function (select) {
            return select('core/block-editor').getBlocks();
        }, []);
        var anchors = useMemo(function () {
            var out = [];
            collectAnchors(blocks, out, {});
            return out;
        }, [blocks]);

        var isHash = a.href.charAt(0) === '#';
        var options = [{ label: __('— None —', 'themeify'), value: '' }].concat(anchors.map(function (item) {
            return { label: item.label, value: '#' + item.id };
        }));
        if (isHash && !anchors.some(function (item) { return '#' + item.id === a.href; })) {
            options.push({ label: __('Missing section', 'themeify') + ' (' + a.href + ')', value: a.href });
        }

        return el(Fragment, null,
            el(be.InspectorControls, null,
                el(c.PanelBody, { title: __('Card link', 'themeify') },
                    el(c.SelectControl, {
                        label: __('Scroll to a section on this page', 'themeify'),
                        help: __('Clicking the card scrolls down to this heading or Details block and opens it.', 'themeify'),
                        value: isHash ? a.href : '',
                        options: options,
                        onChange: function (value) { set({ href: value }); }
                    }),
                    el(c.TextControl, {
                        label: __('Or link to a page (URL)', 'themeify'),
                        value: isHash ? '' : a.href,
                        onChange: function (value) { set({ href: value.trim() }); }
                    })
                )
            ),
            el('div', blockProps,
                el(be.RichText, {
                    tagName: 'strong',
                    className: 'tf-xcard__title',
                    value: a.title,
                    allowedFormats: [],
                    placeholder: __('Card title', 'themeify'),
                    onChange: function (value) { set({ title: value }); }
                }),
                el(be.RichText, {
                    tagName: 'span',
                    className: 'tf-xcard__text',
                    value: a.text,
                    allowedFormats: ['core/bold', 'core/italic'],
                    placeholder: __('Short description', 'themeify'),
                    onChange: function (value) { set({ text: value }); }
                }),
                el('span', { className: 'tf-xcard__link' }, a.href ? '→ ' + a.href : __('No link yet: set one in the block settings', 'themeify'))
            )
        );
    }

    wp.blocks.registerBlockType('themeify/card', {
        apiVersion: 3,
        title: __('Card', 'themeify'),
        description: __('One card: a title, a short description and a link.', 'themeify'),
        category: 'design',
        icon: 'index-card',
        parent: ['themeify/cards'],
        supports: { html: false, reusable: false },
        attributes: {
            title: { type: 'string', source: 'html', selector: '.tf-xcard__title', default: '' },
            text: { type: 'string', source: 'html', selector: '.tf-xcard__text', default: '' },
            href: { type: 'string', default: '' }
        },
        edit: CardEdit,
        save: function (props) {
            var a = props.attributes;
            var blockProps = be.useBlockProps.save(a.href ? { className: 'tf-xcard', href: a.href } : { className: 'tf-xcard' });
            return el(a.href ? 'a' : 'div', blockProps,
                el(be.RichText.Content, { tagName: 'strong', className: 'tf-xcard__title', value: a.title }),
                el(be.RichText.Content, { tagName: 'span', className: 'tf-xcard__text', value: a.text })
            );
        }
    });
})(window.wp);
