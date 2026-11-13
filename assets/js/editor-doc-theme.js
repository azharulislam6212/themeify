/**
 * Block editor: show the doc "Themes" taxonomy as radio buttons (one theme per
 * doc) and keep Publish locked until a theme is chosen.
 */
(function (wp) {
    'use strict';

    if (!wp || !wp.hooks || !wp.element || !wp.data || !wp.components) {
        return;
    }

    var el = wp.element.createElement;
    var useEffect = wp.element.useEffect;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var RadioControl = wp.components.RadioControl;
    var Spinner = wp.components.Spinner;
    var decode = (wp.htmlEntities && wp.htmlEntities.decodeEntities) || function (s) { return s; };
    var cfg = window.themeifyDocTheme || {};
    var LOCK = 'themeify-doc-theme';

    function ThemeRadio(props) {
        var tax = props.slug;

        var data = useSelect(function (select) {
            var taxonomy = select('core').getTaxonomy(tax);
            var restBase = (taxonomy && taxonomy.rest_base) || tax;
            return {
                restBase: restBase,
                terms: select('core').getEntityRecords('taxonomy', tax, { per_page: -1, orderby: 'name', order: 'asc' }),
                selected: select('core/editor').getEditedPostAttribute(restBase) || []
            };
        }, [tax]);

        var editor = useDispatch('core/editor');
        var current = data.selected.length ? data.selected[0] : 0;

        useEffect(function () {
            if (current) {
                editor.unlockPostSaving(LOCK);
            } else {
                editor.lockPostSaving(LOCK);
            }
        }, [current]);

        if (!data.terms) {
            return el(Spinner);
        }

        var children = [];
        if (!current) {
            children.push(el('p', { key: 'req', className: 'tf-doc-theme__required' }, cfg.required));
        }
        if (data.terms.length) {
            children.push(el(RadioControl, {
                key: 'radios',
                label: cfg.label,
                hideLabelFromVision: true,
                selected: String(current),
                options: data.terms.map(function (term) {
                    return { label: decode(term.name), value: String(term.id) };
                }),
                onChange: function (value) {
                    var edit = {};
                    edit[data.restBase] = [parseInt(value, 10)];
                    editor.editPost(edit);
                }
            }));
        } else {
            children.push(el('p', { key: 'none' }, cfg.noThemes));
        }
        if (cfg.manageUrl) {
            children.push(el('p', { key: 'manage' }, el('a', { href: cfg.manageUrl, target: '_blank', rel: 'noopener noreferrer' }, cfg.manage)));
        }

        return el('div', { className: 'tf-doc-theme' }, children);
    }

    wp.hooks.addFilter('editor.PostTaxonomyType', 'themeify/doc-theme-radio', function (OriginalComponent) {
        return function (props) {
            return props.slug === 'doc_theme' ? el(ThemeRadio, props) : el(OriginalComponent, props);
        };
    });
})(window.wp);
