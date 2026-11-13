<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('customize_register', function ($wp_customize) {
    $wp_customize->add_section('themeify_home', [
        'title'    => 'Themeify settings',
        'priority' => 30,
    ]);

    $wp_customize->add_setting('themeify_accent', [
        'default'           => '#0f766e',
        'sanitize_callback' => 'sanitize_hex_color',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'themeify_accent', [
        'label'   => 'Accent color',
        'section' => 'themeify_home',
    ]));

    $wp_customize->add_setting('themeify_slider_heading', [
        'default'           => 'Featured products',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('themeify_slider_heading', [
        'label'   => 'Slider heading',
        'section' => 'themeify_home',
        'type'    => 'text',
    ]);

    $wp_customize->add_setting('themeify_slider_count', [
        'default'           => 6,
        'sanitize_callback' => 'absint',
    ]);
    $wp_customize->add_control('themeify_slider_count', [
        'label'       => 'Number of slides',
        'section'     => 'themeify_home',
        'type'        => 'number',
        'input_attrs' => ['min' => 1, 'max' => 12],
    ]);
});

/* ---------- Docs page (Docs archive) texts ---------- */

/** Default texts of the docs page, keyed by setting name without the "themeify_docs_" prefix. */
function themeify_docs_defaults()
{
    $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
    return [
        'heading'     => __('What do you want to do today?', 'themeify'),
        'subtitle'    => function_exists('themeify_ai_enabled') && themeify_ai_enabled()
            ? __('Type it in your own words. Pages that match appear as you type; press Enter for an answer from the docs.', 'themeify')
            : __('Type it in your own words. Pages that match appear as you type; press Enter to see every result in the docs.', 'themeify'),
        'intro'       => __('Guides and a settings reference for every theme. Every setting is named exactly as the theme editor shows it, so you can search for the label you see on screen.', 'themeify'),
        'cards_title' => __('Pick your theme', 'themeify'),
        'support_url' => '',
        'questions'   => implode("\n", [
            __('How do I change the fonts and colours?', 'themeify'),
            __('How do I add a promo banner to the home page?', 'themeify'),
            __('Why is my logo blurry?', 'themeify'),
            __('How do I set up product badges?', 'themeify'),
            __('How do I update the theme without losing my settings?', 'themeify'),
        ]),
        'footer'      => sprintf(
            /* translators: %s: link to the shop */
            __('Can\'t find an answer? Ask in the search box above. Looking for a new theme? See our %s.', 'themeify'),
            '<a href="' . esc_url($shop) . '">' . esc_html__('premium themes', 'themeify') . '</a>'
        ),
    ];
}

/** A docs page text from the Customizer (Themeify settings → Docs page), or its default. */
function themeify_docs_text($key)
{
    $defaults = themeify_docs_defaults();
    return trim((string) get_theme_mod('themeify_docs_' . $key, $defaults[$key] ?? ''));
}

/** The question chips under the docs search, one per line in the Customizer. */
function themeify_docs_questions()
{
    return array_values(array_filter(array_map('trim', explode("\n", themeify_docs_text('questions')))));
}

add_action('customize_register', function ($wp_customize) {
    $wp_customize->add_section('themeify_docs', [
        'title'       => __('Docs page', 'themeify'),
        'description' => __('Texts of the main Docs page. Theme cards come from Docs → Themes (name, description and image).', 'themeify'),
        'priority'    => 31,
    ]);

    $fields = [
        'heading'     => [__('Search heading', 'themeify'), 'text', 'sanitize_text_field'],
        'subtitle'    => [__('Search help text', 'themeify'), 'textarea', 'sanitize_textarea_field'],
        'questions'   => [__('Question chips (one per line, leave empty to hide)', 'themeify'), 'textarea', 'sanitize_textarea_field'],
        'intro'       => [__('Intro text', 'themeify'), 'textarea', 'sanitize_textarea_field'],
        'cards_title' => [__('Theme cards heading', 'themeify'), 'text', 'sanitize_text_field'],
        'footer'      => [__('Footer note (links allowed)', 'themeify'), 'textarea', 'wp_kses_post'],
        'support_url' => [__('Support link under AI answers ("Still stuck? Contact support"), e.g. a contact page or mailto: address', 'themeify'), 'url', 'esc_url_raw'],
    ];
    $defaults = themeify_docs_defaults();
    foreach ($fields as $key => [$label, $type, $sanitize]) {
        $wp_customize->add_setting('themeify_docs_' . $key, [
            'default'           => $defaults[$key],
            'sanitize_callback' => $sanitize,
        ]);
        $wp_customize->add_control('themeify_docs_' . $key, [
            'label'   => $label,
            'section' => 'themeify_docs',
            'type'    => $type,
        ]);
    }
});

add_action('wp_enqueue_scripts', function () {
    $accent = sanitize_hex_color(get_theme_mod('themeify_accent', '#0f766e'));
    if ($accent) {
        wp_add_inline_style('themeify-main', ':root{--tf-accent:' . $accent . ';}');
    }
}, 20);
