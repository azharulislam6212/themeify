<?php
/**
 * Doc page content: callout styles for the Quote block, previous / next doc pagination and the
 * docs script (copy buttons on code blocks, "Copy Markdown" and "Open in" for the page).
 * Styles: the "Doc content" part of assets/css/main.css (also loaded in the editor canvas).
 */
if (!defined('ABSPATH')) {
    exit;
}

/*
 * Quote block = callout box. The default Quote is a blue "note"; these styles add the other colours.
 * In the editor: select a Quote → Styles.
 */
add_action('init', function () {
    if (!function_exists('register_block_style')) {
        return;
    }
    register_block_style('core/quote', ['name' => 'tip', 'label' => __('Tip (green)', 'themeify')]);
    register_block_style('core/quote', ['name' => 'warning', 'label' => __('Warning (amber)', 'themeify')]);
    register_block_style('core/quote', ['name' => 'danger', 'label' => __('Danger (red)', 'themeify')]);
});

/**
 * Docs in reading order. For a doc in a theme: the theme sidebar order (each category's docs, then its
 * sub categories, then docs without a category). Otherwise: the docs of its first category.
 *
 * @return WP_Post[]
 */
function themeify_doc_sequence($doc_id)
{
    $theme = themeify_get_doc_theme($doc_id);
    if ($theme) {
        $docs = [];
        foreach (themeify_theme_doc_groups(themeify_theme_doc_tree($theme->term_id)) as $group) {
            foreach ($group['docs'] as $doc) {
                $docs[$doc->ID] = $doc; // a doc in two categories counts once, at its first place
            }
        }
        return array_values($docs);
    }

    $cats = get_the_terms($doc_id, 'doc_category');
    if (!$cats || is_wp_error($cats)) {
        return [];
    }
    return get_posts([
        'post_type'   => 'themeify_doc',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby'     => ['menu_order' => 'ASC', 'title' => 'ASC'],
        'tax_query'   => [['taxonomy' => 'doc_category', 'field' => 'term_id', 'terms' => $cats[0]->term_id, 'include_children' => false]],
    ]);
}

/** ['prev' => WP_Post|null, 'next' => WP_Post|null] around a doc. */
function themeify_doc_pager($doc_id)
{
    $ids = wp_list_pluck(themeify_doc_sequence($doc_id), 'ID');
    $at  = array_search((int) $doc_id, array_map('intval', $ids), true);
    if ($at === false) {
        return ['prev' => null, 'next' => null];
    }
    return [
        'prev' => $at > 0 ? get_post($ids[$at - 1]) : null,
        'next' => $at < count($ids) - 1 ? get_post($ids[$at + 1]) : null,
    ];
}

/** Short description of a doc for the pager cards: its excerpt, else the start of its text. */
function themeify_doc_summary($post, $words = 14)
{
    $text = has_excerpt($post) ? get_the_excerpt($post) : wp_strip_all_tags(strip_shortcodes($post->post_content));
    return wp_trim_words(trim(preg_replace('/\s+/', ' ', $text)), $words);
}

add_action('wp_enqueue_scripts', function () {
    if (is_singular('themeify_doc')) {
        wp_enqueue_script('themeify-docs', THEMEIFY_URI . '/assets/js/docs.js', [], THEMEIFY_VERSION, true);
        wp_localize_script('themeify-docs', 'themeifyDocs', [
            'copy'     => __('Copy code', 'themeify'),
            'copied'   => __('Copied', 'themeify'),
            'copyPage' => __('Copy Markdown', 'themeify'),
            'prompt'   => __('Read %s so I can ask questions about it.', 'themeify'),
        ]);
    }
});
