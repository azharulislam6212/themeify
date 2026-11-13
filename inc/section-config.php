<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Section types and their fields.
 * Field spec: [label, type, options (select only)]
 * Types: text, textarea, html, url, image, select, code, slides (desktop + mobile image pairs)
 * Optional per type: 'defaults' (prefilled when the section is added), 'wrap' => false (no <section> wrapper).
 */
function themeify_section_types()
{
    $tones = ['light' => 'Light', 'brand' => 'Brand color', 'dark' => 'Dark'];

    return [
        'home_hero' => [
            'label'    => 'Home hero (scrolling screenshots)',
            'wrap'     => false, // prints its own <section>; on the front page it is moved to the top
            'fields'   => [
                'title'       => ['Heading (a new line starts a new line, {icon} shows the Shopify badge)', 'textarea'],
                'subtitle'    => ['Subtitle', 'textarea'],
                'button_text' => ['Button text (leave empty to hide)', 'text'],
                'button_url'  => ['Button link (default: the shop page)', 'url'],
                'slides'      => ['Scrolling screenshots (each item adds one desktop and one mobile card)', 'slides'],
            ],
            'defaults' => [
                'title'       => "Premium {icon} Shopify Theme\nTo Redefine Brand",
                'subtitle'    => 'A premium Shopify theme designed to redefine how your brand looks and sells.',
                'button_text' => 'View Our Themes',
                'slides'      => [['desktop' => 0, 'mobile' => 0, 'product' => 0]],
            ],
        ],
        'hero' => [
            'label'  => 'Hero banner',
            'fields' => [
                'title'       => ['Heading', 'text'],
                'subtitle'    => ['Subheading', 'textarea'],
                'button_text' => ['Button text', 'text'],
                'button_url'  => ['Button link', 'url'],
                'image'       => ['Background image', 'image'],
                'style'       => ['Tone', 'select', $tones],
            ],
        ],
        'text' => [
            'label'  => 'Text',
            'fields' => [
                'title'   => ['Heading', 'text'],
                'content' => ['Content (HTML allowed)', 'html'],
            ],
        ],
        'image_text' => [
            'label'  => 'Image + text',
            'fields' => [
                'title'       => ['Heading', 'text'],
                'content'     => ['Content (HTML allowed)', 'html'],
                'image'       => ['Image', 'image'],
                'layout'      => ['Image position', 'select', ['image_right' => 'Image right', 'image_left' => 'Image left']],
                'button_text' => ['Button text', 'text'],
                'button_url'  => ['Button link', 'url'],
            ],
        ],
        'features' => [
            'label'  => 'Feature grid',
            'fields' => [
                'title' => ['Heading', 'text'],
                'intro' => ['Intro', 'textarea'],
                'items' => ['Items (one per line: Title | Description)', 'textarea'],
            ],
        ],
        'faq' => [
            'label'  => 'FAQ',
            'fields' => [
                'title' => ['Heading', 'text'],
                'items' => ['Questions (one per line: Question | Answer)', 'textarea'],
            ],
        ],
        'cta' => [
            'label'  => 'Call to action',
            'fields' => [
                'title'       => ['Heading', 'text'],
                'content'     => ['Message', 'textarea'],
                'button_text' => ['Button text', 'text'],
                'button_url'  => ['Button link', 'url'],
                'style'       => ['Tone', 'select', $tones],
            ],
        ],
        'code' => [
            'label'  => 'Code block',
            'fields' => [
                'title'    => ['Heading', 'text'],
                'note'     => ['Note above code', 'textarea'],
                'language' => ['Language label', 'text'],
                'code'     => ['Code', 'code'],
            ],
        ],
        'callout' => [
            'label'  => 'Callout / note',
            'fields' => [
                'title'   => ['Heading', 'text'],
                'content' => ['Content (HTML allowed)', 'html'],
                'style'   => ['Type', 'select', ['info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Danger']],
            ],
        ],
        'video' => [
            'label'  => 'Video embed',
            'fields' => [
                'title' => ['Heading', 'text'],
                'url'   => ['Video URL (YouTube, Vimeo, etc.)', 'url'],
            ],
        ],
    ];
}

function themeify_builder_post_types()
{
    return ['page', 'product', 'themeify_doc'];
}

/** Product choices for "links to" selects: 0 = shop page. */
function themeify_product_choices()
{
    static $choices = null;
    if ($choices === null) {
        $choices = [0 => 'Shop page'];
        foreach (get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC']) as $product) {
            $choices[$product->ID] = $product->post_title;
        }
    }
    return $choices;
}

/** Screenshot pairs of the home hero: [{desktop, mobile, product}], empty pairs dropped. */
function themeify_sanitize_slides($value)
{
    $clean = [];
    if (!is_array($value)) {
        return $clean;
    }
    foreach ($value as $slide) {
        if (!is_array($slide)) {
            continue;
        }
        $item = [
            'desktop' => absint($slide['desktop'] ?? 0),
            'mobile'  => absint($slide['mobile'] ?? 0),
            'product' => absint($slide['product'] ?? 0),
        ];
        if ($item['desktop'] || $item['mobile']) {
            $clean[] = $item;
        }
    }
    return $clean;
}

function themeify_sanitize_field($value, $field)
{
    if ($field[1] === 'slides') {
        return themeify_sanitize_slides($value);
    }
    if (is_array($value)) {
        return '';
    }
    $value = (string) $value;

    switch ($field[1]) {
        case 'textarea':
            return sanitize_textarea_field($value);
        case 'html':
            return wp_kses_post($value);
        case 'url':
            return esc_url_raw($value);
        case 'image':
            return absint($value);
        case 'select':
            return array_key_exists($value, $field[2]) ? $value : array_key_first($field[2]);
        case 'code':
            return $value; // stored as-is, escaped on output
        default:
            return sanitize_text_field($value);
    }
}

/**
 * Cleans the posted section list. Input must already be unslashed.
 */
function themeify_sanitize_sections($raw)
{
    $types = themeify_section_types();
    $clean = [];
    if (!is_array($raw)) {
        return $clean;
    }

    foreach ($raw as $section) {
        if (!is_array($section)) {
            continue;
        }
        $type = sanitize_key($section['type'] ?? '');
        if (!isset($types[$type])) {
            continue;
        }
        $item = ['type' => $type];
        foreach ($types[$type]['fields'] as $name => $field) {
            $item[$name] = themeify_sanitize_field($section[$name] ?? '', $field);
        }
        $clean[] = $item;
    }
    return $clean;
}

function themeify_get_sections($post_id)
{
    $sections = get_post_meta($post_id, '_themeify_sections', true);
    return is_array($sections) ? array_values($sections) : [];
}

/**
 * Built-in starter templates. Custom templates are stored in the
 * "themeify_custom_templates" option when a user saves a layout.
 */
function themeify_builtin_templates()
{
    return [
        'builtin:product-landing' => [
            'name'      => 'Product landing page',
            'post_type' => 'product',
            'sections'  => [
                ['type' => 'hero', 'title' => 'Meet your new favourite product', 'subtitle' => 'A short summary of what this product does for the customer.', 'button_text' => 'Shop now', 'button_url' => '#', 'style' => 'brand'],
                ['type' => 'features', 'title' => 'Why you will love it', 'intro' => 'The key benefits at a glance.', 'items' => "Premium quality | Built with materials that last.\nEasy to use | Simple setup, no tools needed.\nFast delivery | Shipped within 48 hours."],
                ['type' => 'text', 'title' => 'The details', 'content' => '<p>Describe materials, size, care instructions and anything else the customer should know.</p>'],
                ['type' => 'faq', 'title' => 'Frequently asked questions', 'items' => "Is there a warranty? | Yes, every order includes a 12-month warranty.\nHow long is shipping? | Usually 2 to 5 business days."],
                ['type' => 'cta', 'title' => 'Ready to order?', 'content' => 'Join thousands of happy customers.', 'button_text' => 'Add to cart', 'button_url' => '#', 'style' => 'dark'],
            ],
        ],
        'builtin:docs-article' => [
            'name'      => 'Docs article',
            'post_type' => 'themeify_doc',
            'sections'  => [
                ['type' => 'callout', 'title' => 'Before you begin', 'content' => '<p>List any prerequisites here.</p>', 'style' => 'info'],
                ['type' => 'text', 'title' => 'Overview', 'content' => '<p>Explain what this topic covers and who it is for.</p>'],
                ['type' => 'code', 'title' => 'Quick start', 'note' => 'Run these commands in your project folder.', 'language' => 'bash', 'code' => "npm install\nnpm run start"],
                ['type' => 'callout', 'title' => 'Tip', 'content' => '<p>Share a useful tip or a common mistake to avoid.</p>', 'style' => 'success'],
                ['type' => 'faq', 'title' => 'Troubleshooting', 'items' => "Something is not working | Check the steps above and confirm your versions match."],
            ],
        ],
        'builtin:page-basic' => [
            'name'      => 'Basic page',
            'post_type' => 'page',
            'sections'  => [
                ['type' => 'hero', 'title' => 'Welcome', 'subtitle' => 'Add a short introduction here.', 'button_text' => 'Contact us', 'button_url' => '#', 'style' => 'light'],
                ['type' => 'text', 'title' => 'About us', 'content' => '<p>Tell your story.</p>'],
                ['type' => 'cta', 'title' => 'Let us talk', 'content' => 'We would love to hear from you.', 'button_text' => 'Get in touch', 'button_url' => '#', 'style' => 'brand'],
            ],
        ],
    ];
}

function themeify_get_templates($post_type)
{
    $all = [];
    foreach (themeify_builtin_templates() as $key => $tpl) {
        if ($tpl['post_type'] === $post_type) {
            $all[$key] = $tpl;
        }
    }
    foreach (get_option('themeify_custom_templates', []) as $id => $tpl) {
        if (($tpl['post_type'] ?? '') === $post_type) {
            $all['custom:' . $id] = $tpl;
        }
    }
    return $all;
}
