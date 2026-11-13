(function ($) {
    'use strict';

    var cfg = window.themeifyAdmin || {};
    var $builder = $('.tf-builder');
    if (!$builder.length) return;

    var $list = $('#tf-builder-list');
    var postType = $builder.data('post-type');

    function post(action, data) {
        return $.post(cfg.ajax, $.extend({ action: action, nonce: cfg.nonce }, data));
    }

    $list.sortable({
        handle: '.tf-row__drag',
        placeholder: 'tf-row__placeholder',
        axis: 'y',
        forcePlaceholderSize: true
    });

    // Screenshot pairs inside a "slides" field can be reordered too.
    function initSlides() {
        $list.find('.tf-slides__list').not('.ui-sortable').sortable({
            handle: '.tf-slide-item__drag',
            placeholder: 'tf-row__placeholder',
            axis: 'y',
            forcePlaceholderSize: true
        });
    }
    initSlides();

    var slideCount = 0;
    $list.on('click', '.tf-slides__add', function () {
        var $wrap = $(this).closest('.tf-slides');
        var key = 'n' + Date.now() + '_' + (slideCount++);
        var html = $wrap.children('.tf-slides__tpl').html().replace(/__i__/g, key);
        $wrap.children('.tf-slides__list').append(html);
    });

    $list.on('click', '.tf-slide-item__remove', function () {
        $(this).closest('.tf-slide-item').remove();
    });

    $('.tf-builder__add-btn').on('click', function () {
        post('themeify_add_row', { type: $('.tf-builder__type').val() }).done(function (res) {
            if (res.success) {
                $list.append(res.data.html);
                initSlides();
            }
        });
    });

    $list.on('click', '.tf-row__remove', function () {
        if (window.confirm(cfg.i18n.removeConfirm)) {
            $(this).closest('.tf-row').remove();
        }
    });

    $list.on('click', '.tf-row__toggle', function () {
        var $row = $(this).closest('.tf-row');
        $row.find('.tf-row__body').slideToggle(150);
        $(this).text(function (i, t) { return t === 'Collapse' ? 'Expand' : 'Collapse'; });
    });

    $list.on('input', 'input[name$="[title]"], textarea[name$="[title]"]', function () {
        $(this).closest('.tf-row').find('.tf-row__title').text(this.value);
    });

    $list.on('click', '.tf-image__pick', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.tf-image');
        var frame = wp.media({
            title: cfg.i18n.chooseImage,
            library: { type: 'image' },
            multiple: false,
            button: { text: cfg.i18n.useImage }
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            var thumb = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
            $wrap.find('input[type="hidden"]').val(att.id);
            $wrap.find('.tf-image__preview').empty().append($('<img>').attr({ src: thumb, alt: '' }));
        });
        frame.open();
    });

    $list.on('click', '.tf-image__clear', function () {
        var $wrap = $(this).closest('.tf-image');
        $wrap.find('input[type="hidden"]').val('');
        $wrap.find('.tf-image__preview').empty();
    });

    $('.tf-builder__apply').on('click', function () {
        var key = $('.tf-builder__template').val();
        if (!key || !window.confirm(cfg.i18n.applyConfirm)) return;
        post('themeify_template_rows', { template: key, post_type: postType }).done(function (res) {
            if (res.success) {
                $list.html(res.data.html);
                initSlides();
            }
        });
    });

    $('.tf-builder__save-template').on('click', function () {
        var name = window.prompt(cfg.i18n.templateName);
        if (!name) return;
        post('themeify_save_template', {
            name: name,
            post_type: postType,
            data: $list.find(':input').serialize()
        }).done(function (res) {
            window.alert((res.data && res.data.message) ? res.data.message : 'Done');
            if (res.success) {
                $('.tf-builder__template').append($('<option>').val(res.data.key).text(res.data.name));
            }
        });
    });
})(jQuery);
