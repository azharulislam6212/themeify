<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', ['height' => 60, 'width' => 200, 'flex-width' => true, 'flex-height' => true]);
    // Product grid cards show ~240-300px wide and the product page ~600px: twice that keeps them sharp on retina screens.
    add_theme_support('woocommerce', ['thumbnail_image_width' => 600, 'single_image_width' => 1200]);
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    // Block editor content uses the theme's fonts and code/link styles.
    add_theme_support('editor-styles');
    add_editor_style([
        'https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600&family=Newsreader:opsz,wght@6..72,400;6..72,500&display=swap',
        'assets/css/editor.css',
    ]);

    register_nav_menus([
        'primary' => __('Primary menu', 'themeify'),
        'footer'        => __('Footer: Pages column', 'themeify'),
        'footer_themes' => __('Footer: Themes column', 'themeify'),
    ]);

    add_image_size('themeify-card', 600, 600, true);
    add_image_size('themeify-hero', 1600, 800, true);
});

/* ---------- Sharp images on high-resolution (retina) screens ---------- */

// Resized copies are saved at quality 90 instead of WordPress's 82 (applies to new uploads / regenerated sizes).
add_filter('wp_editor_set_quality', function () {
    return 90;
});

// srcset may offer files up to 2560px (WordPress's largest stored size) so 2x screens get a crisp file.
add_filter('max_srcset_image_width', function () {
    return 2560;
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'themeify-fonts',
        'https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600&family=Newsreader:opsz,wght@6..72,400;6..72,500&display=swap',
        [],
        null
    );
    wp_enqueue_style('themeify-main', THEMEIFY_URI . '/assets/css/main.css', ['themeify-fonts'], THEMEIFY_VERSION);
    wp_enqueue_script('themeify-main', THEMEIFY_URI . '/assets/js/main.js', [], THEMEIFY_VERSION, true);

    // Swiper for the home page featured products carousel.
    if (is_front_page()) {
        wp_enqueue_style('swiper', 'https://cdn.jsdelivr.net/npm/swiper@11.1.14/swiper-bundle.min.css', [], null);
        wp_enqueue_script('swiper', 'https://cdn.jsdelivr.net/npm/swiper@11.1.14/swiper-bundle.min.js', [], null, true);
        wp_enqueue_script('themeify-featured-carousel', THEMEIFY_URI . '/assets/js/featured-carousel.js', ['swiper'], THEMEIFY_VERSION, true);
        wp_enqueue_script('themeify-image-rows', THEMEIFY_URI . '/assets/js/image-rows.js', [], THEMEIFY_VERSION, true);
        wp_enqueue_script('themeify-testimonials', THEMEIFY_URI . '/assets/js/testimonials.js', ['swiper'], THEMEIFY_VERSION, true);
        wp_enqueue_script('themeify-faq', THEMEIFY_URI . '/assets/js/faq.js', [], THEMEIFY_VERSION, true);
    }

    // Shop / product archives show a testimonials carousel and FAQ (Shop page's ACF content) at the bottom.
    if (function_exists('is_shop') && (is_shop() || is_product_taxonomy() || is_product())) {
        wp_enqueue_style('swiper', 'https://cdn.jsdelivr.net/npm/swiper@11.1.14/swiper-bundle.min.css', [], null);
        wp_enqueue_script('swiper', 'https://cdn.jsdelivr.net/npm/swiper@11.1.14/swiper-bundle.min.js', [], null, true);
        wp_enqueue_script('themeify-testimonials', THEMEIFY_URI . '/assets/js/testimonials.js', ['swiper'], THEMEIFY_VERSION, true);
        wp_enqueue_script('themeify-faq', THEMEIFY_URI . '/assets/js/faq.js', [], THEMEIFY_VERSION, true);
    }

    // Product page: theme pills + gallery thumbnails.
    if (function_exists('is_product') && is_product()) {
        wp_enqueue_script('themeify-product-detail', THEMEIFY_URI . '/assets/js/product-detail.js', ['swiper'], THEMEIFY_VERSION, true);
    }
});

function themeify_fallback_menu()
{
    $items = [[home_url('/'), __('Home', 'themeify')]];
    if (function_exists('wc_get_page_permalink')) {
        $items[] = [wc_get_page_permalink('shop'), __('Themes', 'themeify')];
    }
    $items[] = [get_post_type_archive_link('themeify_doc'), __('Documentation', 'themeify')];
    $contact = get_page_by_path('contact');
    if ($contact) {
        $items[] = [get_permalink($contact), __('Contact', 'themeify')];
    }

    echo '<ul class="tf-menu">';
    foreach ($items as [$url, $label]) {
        echo '<li class="menu-item"><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
    }
    echo '</ul>';
}

/** The Themeify wordmark (inline SVG, coloured by CSS), shown when no custom logo is set. */
function themeify_wordmark()
{
    $svg = file_get_contents(THEMEIFY_DIR . '/assets/img/logo-wordmark.svg');
    return $svg ? str_replace('<svg ', '<svg class="tf-brand__mark" aria-hidden="true" focusable="false" ', trim($svg)) : esc_html(get_bloginfo('name'));
}

function themeify_parse_lines($text)
{
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $rows[] = array_map('trim', explode('|', $line, 2));
    }
    return $rows;
}
