<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme blocks: "Cards" (themeify/cards) holding "Card" (themeify/card) blocks.
 * A card can link to a URL or to a heading / Details block on the same page;
 * on the front end that scrolls down and opens it (assets/js/main.js).
 */
add_action('init', function () {
    wp_register_script(
        'themeify-blocks',
        THEMEIFY_URI . '/assets/js/blocks.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-i18n', 'wp-html-entities'],
        THEMEIFY_VERSION,
        true
    );
    register_block_type('themeify/cards', ['api_version' => 3, 'editor_script' => 'themeify-blocks']);
    register_block_type('themeify/card', ['api_version' => 3, 'editor_script' => 'themeify-blocks']);
});

// Card and Details (accordion) styles, on the site and in the editor canvas.
add_action('enqueue_block_assets', function () {
    wp_enqueue_style('themeify-blocks', THEMEIFY_URI . '/assets/css/blocks.css', [], THEMEIFY_VERSION);
});
