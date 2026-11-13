(function ($) {
    'use strict';

    var cfg = window.themeifyTerm || {};

    $(document).on('click', '.tf-image__pick', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.tf-image');
        var frame = wp.media({
            title: cfg.chooseImage,
            library: { type: 'image' },
            multiple: false,
            button: { text: cfg.useImage }
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            var thumb = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
            $wrap.find('input[type="hidden"]').val(att.id);
            $wrap.find('.tf-image__preview').empty().append($('<img>').attr({ src: thumb, alt: '' }));
        });
        frame.open();
    });

    $(document).on('click', '.tf-image__clear', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.tf-image');
        $wrap.find('input[type="hidden"]').val('');
        $wrap.find('.tf-image__preview').empty();
    });

    // The "Add new theme" form is submitted with AJAX and only resets core fields.
    $(document).ajaxSuccess(function (e, xhr, settings) {
        if (typeof settings.data === 'string' && settings.data.indexOf('action=add-tag') !== -1) {
            $('#addtag .tf-image input[type="hidden"]').val('');
            $('#addtag .tf-image__preview').empty();
            $('#addtag input[name="themeify_featured"]').prop('checked', false);
            $('#addtag input[name="themeify_icon"][data-default]').prop('checked', true);
            $('#addtag select[name="themeify_page_id"]').val('0');
        }
    });
})(jQuery);
