<?php
/**
 * Site footer: two link columns (menus "Footer: Pages column" and "Footer: Themes column"),
 * a large outlined wordmark, a support email, social links and the copyright line.
 * Texts and links are set in Customizer → Footer.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Social networks shown in the footer, in order: key => [label, icon SVG]. */
function themeify_footer_networks()
{
    $svg = function ($inner) {
        return '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $inner . '</svg>';
    };
    return [
        'x'         => ['X', '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true" focusable="false"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>'],
        'instagram' => ['Instagram', $svg('<rect x="2" y="2" width="12" height="12" rx="3.5"/><circle cx="8" cy="8" r="2.75"/><circle cx="11.6" cy="4.4" r=".5" fill="currentColor" stroke="none"/>')],
        'facebook'  => ['Facebook', $svg('<circle cx="8" cy="8" r="6.25"/><path d="M9.6 5.3H8.9c-.8 0-1.3.5-1.3 1.3v7.6M6.3 8.6h3.1"/>')],
        'linkedin'  => ['LinkedIn', $svg('<rect x="2" y="2" width="12" height="12" rx="1.5"/><path d="M5.2 7v4M5.2 5v.01M7.7 11V7M7.7 8.8c0-1 .7-1.8 1.6-1.8s1.5.7 1.5 1.7V11"/>')],
    ];
}

/** Default footer texts, keyed by setting name without the "themeify_footer_" prefix. */
function themeify_footer_defaults()
{
    $defaults = [
        'pages_title'  => __('Pages', 'themeify'),
        'themes_title' => __('Themes', 'themeify'),
        'email_label'  => __('Email us for support', 'themeify'),
        'email'        => 'hello@themefy.com',
    ];
    foreach (array_keys(themeify_footer_networks()) as $key) {
        $defaults['social_' . $key] = '';
    }
    return $defaults;
}

/** A footer text from the Customizer (Footer section), or its default. */
function themeify_footer_text($key)
{
    $defaults = themeify_footer_defaults();
    return trim((string) get_theme_mod('themeify_footer_' . $key, $defaults[$key] ?? ''));
}

/** Social links that have a URL set: [['url', 'label', 'icon'], ...]. */
function themeify_footer_socials()
{
    $links = [];
    foreach (themeify_footer_networks() as $key => [$label, $icon]) {
        $url = themeify_footer_text('social_' . $key);
        if ($url) {
            $links[] = ['url' => $url, 'label' => $label, 'icon' => $icon];
        }
    }
    return $links;
}

/** A footer column's links: the menu assigned to $location, else the fallback list. */
function themeify_footer_menu($location, $fallback)
{
    if (has_nav_menu($location)) {
        wp_nav_menu([
            'theme_location' => $location,
            'container'      => false,
            'menu_class'     => 'tf-footer__links',
            'depth'          => 1,
            'fallback_cb'    => false,
        ]);
        return;
    }
    $items = $fallback();
    if (!$items) {
        return;
    }
    echo '<ul class="tf-footer__links">';
    foreach ($items as [$url, $label]) {
        echo '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
    }
    echo '</ul>';
}

/** Pages column without a menu: Home, Themes (shop), Contacts and Documentations. */
function themeify_footer_pages_fallback()
{
    $items = [[home_url('/'), __('Home', 'themeify')]];
    if (function_exists('themeify_shop_url')) {
        $items[] = [themeify_shop_url(), __('Themes', 'themeify')];
    }
    $contact = get_page_by_path('contact') ?: get_page_by_path('contacts');
    if ($contact) {
        $items[] = [get_permalink($contact), __('Contacts', 'themeify')];
    }
    $docs = get_post_type_archive_link('themeify_doc');
    if ($docs) {
        $items[] = [$docs, __('Documentations', 'themeify')];
    }
    return $items;
}

/** Themes column without a menu: the published products, newest first. */
function themeify_footer_themes_fallback()
{
    if (!function_exists('themeify_latest_products')) {
        return [];
    }
    return array_map(function ($product) {
        return [$product->get_permalink(), $product->get_name()];
    }, themeify_latest_products(6));
}

/** The Themeify wordmark as a large outline (stroke only), for the footer. */
function themeify_footer_wordmark()
{
    $svg = file_get_contents(THEMEIFY_DIR . '/assets/img/logo-wordmark.svg');
    if (!$svg) {
        return esc_html(get_bloginfo('name'));
    }
    return str_replace('<svg ', '<svg class="tf-footer__mark-svg" aria-hidden="true" focusable="false" ', trim($svg));
}

add_action('customize_register', function ($wp_customize) {
    $wp_customize->add_section('themeify_footer', [
        'title'       => __('Footer', 'themeify'),
        'description' => __('The column links come from Appearance → Menus ("Footer: Pages column" and "Footer: Themes column"). Without a menu, the Pages column lists the main pages and the Themes column lists your products. Social icons show only when a link is set.', 'themeify'),
        'priority'    => 32,
    ]);

    $fields = [
        'pages_title'  => [__('First column heading', 'themeify'), 'text', 'sanitize_text_field'],
        'themes_title' => [__('Second column heading', 'themeify'), 'text', 'sanitize_text_field'],
        'email_label'  => [__('Email label', 'themeify'), 'text', 'sanitize_text_field'],
        'email'        => [__('Support email (leave empty to hide)', 'themeify'), 'email', 'sanitize_email'],
    ];
    foreach (themeify_footer_networks() as $key => [$label]) {
        /* translators: %s: social network name */
        $fields['social_' . $key] = [sprintf(__('%s link', 'themeify'), $label), 'url', 'esc_url_raw'];
    }

    $defaults = themeify_footer_defaults();
    foreach ($fields as $key => [$label, $type, $sanitize]) {
        $wp_customize->add_setting('themeify_footer_' . $key, [
            'default'           => $defaults[$key],
            'sanitize_callback' => $sanitize,
        ]);
        $wp_customize->add_control('themeify_footer_' . $key, [
            'label'   => $label,
            'section' => 'themeify_footer',
            'type'    => $type,
        ]);
    }
});
