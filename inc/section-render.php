<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Outputs the section list for a post. Each section gets id="sec-N" so the
 * docs table of contents can link to it.
 */
function themeify_render_sections($post_id = 0, $exclude = [])
{
    $post_id  = $post_id ?: get_the_ID();
    $types    = themeify_section_types();
    $sections = array_filter(themeify_get_sections($post_id), function ($section) use ($exclude) {
        return !in_array($section['type'] ?? '', $exclude, true);
    });
    if (!$sections) {
        return;
    }

    echo '<div class="tf-sections">';
    foreach ($sections as $i => $section) {
        $type = $section['type'] ?? '';
        $fn   = 'themeify_render_section_' . $type;
        if (!function_exists($fn)) {
            continue;
        }
        if (($types[$type]['wrap'] ?? true) === false) {
            $fn($section);
            continue;
        }
        echo '<section id="sec-' . (int) $i . '" class="tf-section tf-section--' . esc_attr($type) . '">';
        $fn($section);
        echo '</section>';
    }
    echo '</div>';
}

/** The home hero (heading, button and scrolling screenshots), see template-parts/home-hero.php. */
function themeify_render_section_home_hero($s)
{
    get_template_part('template-parts/home-hero', null, ['section' => $s]);
}

/**
 * Home hero data from the page's ACF fields (heading, text, button, button_link and the
 * "images" repeater with image + mobile_image per row), in the same shape as a saved
 * home_hero section. Reads the raw post meta when ACF is not active.
 * In the heading, {icon} shows the Shopify badge and {br} starts a new line.
 */
function themeify_home_hero_acf($post_id)
{
    $get = function ($name) use ($post_id) {
        return function_exists('get_field') ? get_field($name, $post_id) : get_post_meta($post_id, $name, true);
    };

    $rows = $get('images');
    if (!is_array($rows)) {
        // Without ACF the repeater is stored as a row count plus images_{n}_{sub} meta.
        $rows = [];
        for ($n = 0, $count = (int) get_post_meta($post_id, 'images', true); $n < $count; $n++) {
            $rows[] = [
                'image'        => get_post_meta($post_id, "images_{$n}_image", true),
                'mobile_image' => get_post_meta($post_id, "images_{$n}_mobile_image", true),
            ];
        }
    }

    $slides = [];
    foreach ($rows as $row) {
        $pair = [
            'desktop' => themeify_acf_image_id($row['image'] ?? 0),
            'mobile'  => themeify_acf_image_id($row['mobile_image'] ?? 0),
            'product' => 0,
        ];
        if ($pair['desktop'] || $pair['mobile']) {
            $slides[] = $pair;
        }
    }

    return [
        'type'        => 'home_hero',
        'title'       => str_ireplace('{br}', "\n", (string) $get('heading')),
        'subtitle'    => (string) $get('text'),
        'button_text' => (string) $get('button'),
        'button_url'  => (string) $get('button_link'),
        'slides'      => $slides,
    ];
}

/** The home "featured products" carousel, see template-parts/featured-carousel.php. */
function themeify_render_section_featured_carousel($s)
{
    get_template_part('template-parts/featured-carousel', null, ['section' => $s]);
}

/**
 * Featured products carousel data from the page's ACF "Featured Products" group:
 * section_heading (text), section_text (WYSIWYG) and products (post object, single or
 * multiple, any return format). Reads the raw post meta when ACF is not active.
 * With no products picked it falls back to the WooCommerce "featured" products, then the latest ones.
 */
function themeify_featured_carousel_acf($post_id)
{
    $acf = function_exists('get_field');
    $get = function ($name) use ($post_id, $acf) {
        return $acf ? get_field($name, $post_id) : get_post_meta($post_id, $name, true);
    };

    $text = (string) $get('section_text');
    if (!$acf) {
        $text = wpautop($text);
    }

    $products = [];
    if (function_exists('wc_get_product')) {
        $picked = $get('products');
        $picked = is_array($picked) && !isset($picked['ID']) ? $picked : array_filter([$picked]);
        foreach ($picked as $item) {
            $id      = $item instanceof WP_Post ? $item->ID : absint(is_array($item) ? ($item['ID'] ?? 0) : $item);
            $product = $id ? wc_get_product($id) : null;
            if ($product && $product->get_status() === 'publish' && $product->is_visible()) {
                $products[$id] = $product;
            }
        }
        if (!$products) {
            $products = themeify_featured_products(8) ?: themeify_latest_products(8);
        }
    }

    return [
        'type'     => 'featured_carousel',
        'heading'  => (string) $get('section_heading'),
        'text'     => $text,
        'products' => array_values($products),
    ];
}

/** Attachment ID from an ACF image value, whatever its return format (array, ID or URL). */
function themeify_acf_image_id($value)
{
    if (is_array($value)) {
        return absint($value['ID'] ?? $value['id'] ?? 0);
    }
    if (is_numeric($value)) {
        return absint($value);
    }
    return is_string($value) && $value !== '' ? attachment_url_to_postid($value) : 0;
}

/**
 * The home hero's screenshot cards: each saved pair gives a desktop card then a mobile card.
 * An empty image in a pair shows a placeholder.
 */
function themeify_hero_cards($slides)
{
    $cards = [];
    foreach ((array) $slides as $slide) {
        $product_id = absint($slide['product'] ?? 0);
        $is_product = $product_id && get_post_type($product_id) === 'product' && get_post_status($product_id) === 'publish';
        $link       = $is_product ? get_permalink($product_id) : themeify_shop_url();
        $label      = $is_product ? get_the_title($product_id) : __('View our themes', 'themeify');

        foreach (['desktop', 'mobile'] as $type) {
            $id  = absint($slide[$type] ?? 0);
            $url = $id ? wp_get_attachment_image_url($id, 'full') : '';
            $cards[] = [
                'type'  => $type,
                'id'    => $url ? $id : 0,
                'url'   => $url ?: THEMEIFY_URI . '/assets/img/placeholder-' . $type . '.svg',
                'alt'   => $url ? (string) get_post_meta($id, '_wp_attachment_image_alt', true) : '',
                'link'  => $link,
                'label' => $label,
            ];
        }
    }
    return $cards;
}

/** "On this page" entries for a post's titled sections. */
function themeify_section_toc_items($post_id)
{
    $items = [];
    foreach (themeify_get_sections($post_id) as $i => $section) {
        if (!empty($section['title'])) {
            $items[] = ['id' => 'sec-' . $i, 'title' => $section['title'], 'level' => 2];
        }
    }
    return $items;
}

/** Anchor slug from plain text. Mirrors slugify() in assets/js/blocks.js. */
function themeify_anchor_slug($text)
{
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(remove_accents($text))), '-');
    return $slug !== '' ? $slug : 'section';
}

/**
 * Gives every <h2>-<h4> and <details> in rendered content an id (keeping ids
 * already set), in document order, and returns the updated HTML plus "On this
 * page" entries for the headings. The Card block's "Scroll to" list computes
 * the same ids in the editor (assets/js/blocks.js), so keep the two in sync.
 *
 * @return array{html: string, items: array}
 */
function themeify_content_anchors($html)
{
    $items   = [];
    $used    = [];
    $pattern = '/<(h[2-4])\b([^>]*)>(.*?)<\/\1>|<details\b([^>]*)>(\s*<summary\b[^>]*>)(.*?)(<\/summary>)/is';

    $html = preg_replace_callback($pattern, function ($m) use (&$items, &$used) {
        $is_heading = $m[1] !== '';
        $attrs      = $is_heading ? $m[2] : $m[4];
        $inner      = $is_heading ? $m[3] : $m[6];
        $title      = trim(html_entity_decode(wp_strip_all_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($title === '') {
            return $m[0];
        }

        if (preg_match('/\sid=["\']([^"\']+)["\']/i', $attrs, $id_match)) {
            $id = $id_match[1];
        } else {
            $base = themeify_anchor_slug($title);
            $id   = $base;
            for ($n = 2; isset($used[$id]); $n++) {
                $id = $base . '-' . $n;
            }
            $attrs = ' id="' . esc_attr($id) . '"' . $attrs;
        }
        $used[$id] = true;

        if ($is_heading) {
            $items[] = ['id' => $id, 'title' => $title, 'level' => (int) substr($m[1], 1)];
            return '<' . $m[1] . $attrs . '>' . $m[3] . '</' . $m[1] . '>';
        }
        return '<details' . $attrs . '>' . $m[5] . $m[6] . $m[7];
    }, $html);

    return ['html' => $html, 'items' => $items];
}

function themeify_heading($s, $class = 'tf-section__title')
{
    if (!empty($s['title'])) {
        echo '<h2 class="' . esc_attr($class) . '">' . esc_html($s['title']) . '</h2>';
    }
}

function themeify_button($text, $url, $class = 'tf-btn')
{
    if ($text && $url) {
        echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($text) . '</a>';
    }
}

function themeify_render_section_hero($s)
{
    $tone = $s['style'] ?? 'light';
    $bg   = !empty($s['image']) ? wp_get_attachment_image_url((int) $s['image'], 'themeify-hero') : '';
    $cls  = 'tf-hero tf-hero--' . $tone . ($bg ? ' has-image' : '');
    $attr = $bg ? ' style="' . esc_attr('background-image:url(' . esc_url($bg) . ')') . '"' : '';

    echo '<div class="' . esc_attr($cls) . '"' . $attr . '><div class="tf-container tf-hero__inner">';
    echo '<h2 class="tf-hero__title">' . esc_html($s['title'] ?? '') . '</h2>';
    if (!empty($s['subtitle'])) {
        echo '<p class="tf-hero__subtitle">' . esc_html($s['subtitle']) . '</p>';
    }
    themeify_button($s['button_text'] ?? '', $s['button_url'] ?? '');
    echo '</div></div>';
}

function themeify_render_section_text($s)
{
    echo '<div class="tf-container tf-narrow">';
    themeify_heading($s);
    echo '<div class="tf-prose">' . wpautop($s['content'] ?? '') . '</div>';
    echo '</div>';
}

function themeify_render_section_image_text($s)
{
    $img = !empty($s['image']) ? wp_get_attachment_image((int) $s['image'], 'large', false, ['class' => 'tf-split__img']) : '';
    $rev = ($s['layout'] ?? '') === 'image_left' ? ' tf-split--reverse' : '';

    echo '<div class="tf-container tf-split' . $rev . '">';
    echo '<div class="tf-split__media">' . $img . '</div>';
    echo '<div class="tf-split__body">';
    themeify_heading($s);
    echo '<div class="tf-prose">' . wpautop($s['content'] ?? '') . '</div>';
    themeify_button($s['button_text'] ?? '', $s['button_url'] ?? '');
    echo '</div></div>';
}

function themeify_render_section_features($s)
{
    echo '<div class="tf-container">';
    themeify_heading($s);
    if (!empty($s['intro'])) {
        echo '<p class="tf-section__intro">' . esc_html($s['intro']) . '</p>';
    }
    $items = themeify_parse_lines($s['items'] ?? '');
    if ($items) {
        echo '<div class="tf-features">';
        foreach ($items as $item) {
            echo '<div class="tf-feature"><h3>' . esc_html($item[0]) . '</h3>';
            if (!empty($item[1])) {
                echo '<p>' . esc_html($item[1]) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

function themeify_render_section_faq($s)
{
    echo '<div class="tf-container tf-narrow">';
    themeify_heading($s);
    foreach (themeify_parse_lines($s['items'] ?? '') as $item) {
        echo '<details class="tf-faq"><summary>' . esc_html($item[0]) . '</summary>';
        echo '<div class="tf-faq__answer">' . esc_html($item[1] ?? '') . '</div></details>';
    }
    echo '</div>';
}

function themeify_render_section_cta($s)
{
    $tone = $s['style'] ?? 'light';
    echo '<div class="tf-container"><div class="tf-cta tf-cta--' . esc_attr($tone) . '">';
    themeify_heading($s);
    if (!empty($s['content'])) {
        echo '<p>' . esc_html($s['content']) . '</p>';
    }
    themeify_button($s['button_text'] ?? '', $s['button_url'] ?? '', 'tf-btn tf-btn--light');
    echo '</div></div>';
}

function themeify_render_section_code($s)
{
    echo '<div class="tf-container tf-narrow tf-code">';
    themeify_heading($s);
    if (!empty($s['note'])) {
        echo '<p class="tf-code__note">' . esc_html($s['note']) . '</p>';
    }
    echo '<div class="tf-code__bar"><span>' . esc_html($s['language'] ?? 'code') . '</span>';
    echo '<button type="button" class="tf-code__copy">Copy</button></div>';
    echo '<pre><code>' . esc_html($s['code'] ?? '') . '</code></pre>';
    echo '</div>';
}

function themeify_render_section_callout($s)
{
    $style = $s['style'] ?? 'info';
    echo '<div class="tf-container tf-narrow"><div class="tf-callout tf-callout--' . esc_attr($style) . '">';
    themeify_heading($s, 'tf-callout__title');
    echo '<div class="tf-prose">' . wpautop($s['content'] ?? '') . '</div>';
    echo '</div></div>';
}

function themeify_render_section_video($s)
{
    echo '<div class="tf-container tf-narrow">';
    themeify_heading($s);
    if (!empty($s['url'])) {
        $html = wp_oembed_get($s['url']);
        if ($html) {
            echo '<div class="tf-video">' . $html . '</div>';
        } else {
            echo '<p><a href="' . esc_url($s['url']) . '">' . esc_html($s['url']) . '</a></p>';
        }
    }
    echo '</div>';
}
