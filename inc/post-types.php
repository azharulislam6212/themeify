<?php
if (!defined('ABSPATH')) {
    exit;
}

function themeify_register_post_types()
{
    register_post_type('themeify_doc', [
        'labels' => [
            'name'          => __('Docs', 'themeify'),
            'singular_name' => __('Doc', 'themeify'),
            'add_new_item'  => __('Add new doc', 'themeify'),
            'edit_item'     => __('Edit doc', 'themeify'),
            'all_items'     => __('All docs', 'themeify'),
            'menu_name'     => __('Docs', 'themeify'),
        ],
        'public'        => true,
        'has_archive'   => true,
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-book-alt',
        'menu_position' => 21,
        'supports'      => ['title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions'],
        'rewrite'       => ['slug' => 'docs', 'with_front' => false],
    ]);

    register_taxonomy('doc_category', ['themeify_doc'], [
        'labels' => [
            'name'          => __('Doc categories', 'themeify'),
            'singular_name' => __('Doc category', 'themeify'),
            'add_new_item'  => __('Add new category', 'themeify'),
            'edit_item'     => __('Edit category', 'themeify'),
            'menu_name'     => __('Categories', 'themeify'),
        ],
        'hierarchical'      => true,
        'public'            => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => ['slug' => 'doc-category', 'hierarchical' => true],
    ]);

    // One theme per doc (see inc/doc-themes.php). Hierarchical only so the
    // edit form posts term IDs; themes are meant to be a flat list.
    register_taxonomy('doc_theme', ['themeify_doc'], [
        'labels' => [
            'name'          => __('Themes', 'themeify'),
            'singular_name' => __('Theme', 'themeify'),
            'all_items'     => __('All themes', 'themeify'),
            'add_new_item'  => __('Add new theme', 'themeify'),
            'edit_item'     => __('Edit theme', 'themeify'),
            'search_items'  => __('Search themes', 'themeify'),
            'not_found'     => __('No themes found.', 'themeify'),
            'menu_name'     => __('Themes', 'themeify'),
        ],
        'hierarchical'       => true,
        'public'             => true,
        'show_admin_column'  => true,
        'show_in_quick_edit' => false,
        'show_in_rest'       => true,
        'meta_box_cb'        => 'themeify_doc_theme_metabox',
        'rewrite'            => ['slug' => 'doc-theme', 'with_front' => false],
    ]);

    // Block-editor content for each theme's documentation page (see inc/theme-pages.php).
    register_post_type('themeify_theme_page', [
        'labels' => [
            'name'          => __('Theme pages', 'themeify'),
            'singular_name' => __('Theme page', 'themeify'),
            'edit_item'     => __('Edit theme page', 'themeify'),
            'add_new_item'  => __('Add new theme page', 'themeify'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'edit.php?post_type=themeify_doc', // Docs → Theme pages
        'show_in_admin_bar'   => false,
        'show_in_nav_menus'   => false,
        'exclude_from_search' => true,
        'show_in_rest'        => true,
        'rewrite'             => false,
        'query_var'           => false,
        'supports'            => ['title', 'editor', 'revisions'],
        'capability_type'     => 'page',
        'map_meta_cap'        => true,
    ]);
}
add_action('init', 'themeify_register_post_types');

add_action('after_switch_theme', function () {
    themeify_register_post_types();
    flush_rewrite_rules();
});

// Flush permalinks once after a theme update that adds new URLs.
add_action('init', function () {
    if (get_option('themeify_rewrite_version') !== THEMEIFY_VERSION) {
        flush_rewrite_rules(false);
        update_option('themeify_rewrite_version', THEMEIFY_VERSION);
    }
}, 20);

/**
 * Category tree for the docs sidebar. Docs are listed under the active category.
 */
function themeify_docnav($parent, $chain, $current_doc)
{
    $terms = get_terms([
        'taxonomy'   => 'doc_category',
        'parent'     => $parent,
        'hide_empty' => false,
    ]);
    if (is_wp_error($terms) || empty($terms)) {
        return;
    }

    echo '<ul class="tf-docnav__list">';
    foreach ($terms as $term) {
        $active = in_array($term->term_id, $chain, true);
        echo '<li class="' . ($active ? 'is-active' : '') . '">';
        echo '<a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a>';

        if ($active) {
            $docs = get_posts([
                'post_type'   => 'themeify_doc',
                'numberposts' => -1,
                'post_status' => 'publish',
                'orderby'     => ['menu_order' => 'ASC', 'title' => 'ASC'],
                'tax_query'   => [[
                    'taxonomy' => 'doc_category',
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ]],
            ]);

            if ($docs) {
                echo '<ul class="tf-docnav__docs">';
                foreach ($docs as $doc) {
                    $is_current = ((int) $doc->ID === (int) $current_doc);
                    echo '<li><a href="' . esc_url(get_permalink($doc)) . '"'
                        . ($is_current ? ' class="is-current" aria-current="page"' : '') . '>'
                        . esc_html(get_the_title($doc)) . '</a></li>';
                }
                echo '</ul>';
            }

            themeify_docnav($term->term_id, $chain, $current_doc);
        }

        echo '</li>';
    }
    echo '</ul>';
}
