<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ---------- Admin assets ---------- */

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }
    wp_enqueue_style('themeify-admin', THEMEIFY_URI . '/assets/css/admin.css', [], THEMEIFY_VERSION);
    wp_enqueue_media();
    wp_enqueue_script('themeify-admin', THEMEIFY_URI . '/assets/js/admin.js', ['jquery', 'jquery-ui-sortable'], THEMEIFY_VERSION, true);
    wp_localize_script('themeify-admin', 'themeifyAdmin', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('themeify_builder'),
        'i18n'  => [
            'removeConfirm' => 'Remove this section?',
            'applyConfirm'  => 'Replace all current sections with this template?',
            'templateName'  => 'Name for this template:',
            'chooseImage'   => 'Choose image',
            'useImage'      => 'Use image',
        ],
    ]);
});

/* ---------- Meta box ---------- */

// "Page sections" meta box disabled — not shown on any post type.
// add_action('add_meta_boxes', function () {
//     foreach (themeify_builder_post_types() as $type) {
//         add_meta_box('themeify-sections', 'Page sections', 'themeify_sections_metabox', $type, 'normal', 'high');
//     }
// });

function themeify_sections_metabox($post)
{
    wp_nonce_field('themeify_save_sections', 'themeify_sections_nonce');
    $sections  = themeify_get_sections($post->ID);
    $types     = themeify_section_types();
    $templates = themeify_get_templates($post->post_type);
    ?>
    <div class="tf-builder" data-post-type="<?php echo esc_attr($post->post_type); ?>">
        <div class="tf-builder__toolbar">
            <select class="tf-builder__template">
                <option value="">Apply a template&hellip;</option>
                <?php foreach ($templates as $key => $tpl) : ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($tpl['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button tf-builder__apply">Apply</button>
            <button type="button" class="button tf-builder__save-template">Save current layout as template</button>
        </div>

        <div id="tf-builder-list" class="tf-builder__list">
            <?php foreach ($sections as $section) : ?>
                <?php echo themeify_section_row_html(uniqid('s'), $section); ?>
            <?php endforeach; ?>
        </div>

        <div class="tf-builder__add">
            <select class="tf-builder__type">
                <?php foreach ($types as $key => $def) : ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($def['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button button-primary tf-builder__add-btn">+ Add section</button>
            <span class="description">Drag the handle to reorder. Remove a section with the Remove link.</span>
        </div>
    </div>
    <?php
}

function themeify_section_row_html($key, $section)
{
    $types = themeify_section_types();
    $type  = $section['type'] ?? '';
    if (!isset($types[$type])) {
        return '';
    }
    $def    = $types[$type];
    $prefix = 'sections[' . $key . ']';

    ob_start(); ?>
    <div class="tf-row" data-type="<?php echo esc_attr($type); ?>">
        <div class="tf-row__head">
            <span class="dashicons dashicons-move tf-row__drag" title="Drag to reorder"></span>
            <strong class="tf-row__type"><?php echo esc_html($def['label']); ?></strong>
            <span class="tf-row__title"><?php echo esc_html($section['title'] ?? ''); ?></span>
            <span class="tf-row__actions">
                <button type="button" class="button-link tf-row__toggle">Collapse</button>
                <button type="button" class="button-link tf-row__remove">Remove</button>
            </span>
        </div>
        <div class="tf-row__body">
            <input type="hidden" name="<?php echo esc_attr($prefix); ?>[type]" value="<?php echo esc_attr($type); ?>">
            <?php foreach ($def['fields'] as $name => $field) {
                themeify_field_input($prefix, $name, $field, $section[$name] ?? '');
            } ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function themeify_field_input($prefix, $name, $field, $value)
{
    list($label, $type) = $field;
    $field_name = $prefix . '[' . $name . ']';

    echo '<div class="tf-field tf-field--' . esc_attr($type) . '">';
    echo '<span class="tf-field__label">' . esc_html($label) . '</span>';

    switch ($type) {
        case 'textarea':
        case 'html':
        case 'code':
            $rows = $type === 'code' ? 10 : ($type === 'html' ? 8 : 3);
            printf(
                '<textarea name="%1$s" rows="%2$d" class="large-text%3$s">%4$s</textarea>',
                esc_attr($field_name),
                $rows,
                $type === 'code' ? ' code' : '',
                esc_textarea($value)
            );
            break;

        case 'select':
            echo '<select name="' . esc_attr($field_name) . '">';
            foreach ($field[2] as $key => $text) {
                echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($text) . '</option>';
            }
            echo '</select>';
            break;

        case 'slides':
            $slides = is_array($value) ? array_values($value) : [];
            echo '<div class="tf-slides">';
            echo '<div class="tf-slides__list">';
            foreach ($slides as $i => $slide) {
                echo themeify_slide_row_html($field_name, 'i' . $i, $slide);
            }
            echo '</div>';
            // Blank item copied by admin.js when "Add screenshots" is clicked; __i__ becomes a unique key.
            echo '<script type="text/template" class="tf-slides__tpl">' . themeify_slide_row_html($field_name, '__i__', []) . '</script>';
            echo '<button type="button" class="button tf-slides__add">+ Add desktop + mobile screenshots</button>';
            echo '</div>';
            break;

        case 'image':
            $url = $value ? wp_get_attachment_image_url((int) $value, 'thumbnail') : '';
            echo '<span class="tf-image">';
            echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '">';
            echo '<span class="tf-image__preview">' . ($url ? '<img src="' . esc_url($url) . '" alt="">' : '') . '</span>';
            echo '<button type="button" class="button tf-image__pick">Choose image</button> ';
            echo '<button type="button" class="button-link tf-image__clear">Clear</button>';
            echo '</span>';
            break;

        default:
            printf(
                '<input type="%1$s" name="%2$s" value="%3$s" class="large-text">',
                $type === 'url' ? 'url' : 'text',
                esc_attr($field_name),
                esc_attr($value)
            );
    }

    echo '</div>';
}

/** One screenshot pair in a "slides" field: desktop image, mobile image and the page both cards open. */
function themeify_slide_row_html($field_name, $key, $slide)
{
    $prefix = $field_name . '[' . $key . ']';
    ob_start(); ?>
    <div class="tf-slide-item">
        <span class="dashicons dashicons-move tf-slide-item__drag" title="Drag to reorder"></span>
        <div class="tf-slide-item__fields">
            <?php
            themeify_field_input($prefix, 'desktop', ['Desktop image (1440 × 1800)', 'image'], $slide['desktop'] ?? '');
            themeify_field_input($prefix, 'mobile', ['Mobile image (1206 × 2622)', 'image'], $slide['mobile'] ?? '');
            themeify_field_input($prefix, 'product', ['Both cards link to', 'select', themeify_product_choices()], $slide['product'] ?? 0);
            ?>
        </div>
        <button type="button" class="button-link tf-slide-item__remove">Remove</button>
    </div>
    <?php
    return ob_get_clean();
}

/* ---------- Save ---------- */

add_action('save_post', function ($post_id, $post) {
    if (!isset($_POST['themeify_sections_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['themeify_sections_nonce'])), 'themeify_save_sections')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id) || !in_array($post->post_type, themeify_builder_post_types(), true)) {
        return;
    }

    $raw = isset($_POST['sections']) ? wp_unslash($_POST['sections']) : [];
    update_post_meta($post_id, '_themeify_sections', themeify_sanitize_sections($raw));
}, 10, 2);

/* ---------- AJAX ---------- */

add_action('wp_ajax_themeify_add_row', function () {
    check_ajax_referer('themeify_builder', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error();
    }
    $type  = sanitize_key(wp_unslash($_POST['type'] ?? ''));
    $types = themeify_section_types();
    if (!isset($types[$type])) {
        wp_send_json_error();
    }
    $section = array_merge($types[$type]['defaults'] ?? [], ['type' => $type]);
    wp_send_json_success(['html' => themeify_section_row_html(uniqid('s'), $section)]);
});

add_action('wp_ajax_themeify_template_rows', function () {
    check_ajax_referer('themeify_builder', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error();
    }
    $post_type = sanitize_key(wp_unslash($_POST['post_type'] ?? ''));
    $key       = sanitize_text_field(wp_unslash($_POST['template'] ?? ''));
    $templates = themeify_get_templates($post_type);
    if (!isset($templates[$key])) {
        wp_send_json_error();
    }

    $html = '';
    foreach ($templates[$key]['sections'] as $section) {
        $html .= themeify_section_row_html(uniqid('s'), $section);
    }
    wp_send_json_success(['html' => $html]);
});

add_action('wp_ajax_themeify_save_template', function () {
    check_ajax_referer('themeify_builder', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Not allowed.']);
    }

    $name      = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $post_type = sanitize_key(wp_unslash($_POST['post_type'] ?? ''));
    if ($name === '') {
        wp_send_json_error(['message' => 'Please enter a name.']);
    }

    parse_str(wp_unslash($_POST['data'] ?? ''), $parsed);
    $sections = themeify_sanitize_sections($parsed['sections'] ?? []);
    if (!$sections) {
        wp_send_json_error(['message' => 'There are no sections to save.']);
    }

    $id     = uniqid('t');
    $custom = get_option('themeify_custom_templates', []);
    $custom[$id] = ['name' => $name, 'post_type' => $post_type, 'sections' => $sections];
    update_option('themeify_custom_templates', $custom, false);

    wp_send_json_success([
        'message' => 'Template saved.',
        'key'     => 'custom:' . $id,
        'name'    => $name,
    ]);
});
