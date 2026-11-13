<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Docs "Themes" taxonomy (doc_theme): every doc belongs to exactly one theme.
 * There is no default theme: the editor keeps Save / Publish locked until one is
 * picked. "Add new" from a theme's doc list (?doc_theme=slug) starts with that theme.
 */

/**
 * Edit-screen box: radio buttons, so exactly one theme is picked. The doc's
 * theme is checked; a new doc has none until one is picked.
 */
function themeify_doc_theme_metabox($post)
{
    $tax   = get_taxonomy('doc_theme');
    $terms = get_terms(['taxonomy' => 'doc_theme', 'hide_empty' => false, 'orderby' => 'name']);
    if (is_wp_error($terms)) {
        $terms = [];
    }

    $current  = wp_get_object_terms($post->ID, 'doc_theme', ['fields' => 'ids']);
    $selected = (!is_wp_error($current) && $current) ? (int) $current[0] : 0;
    $can      = current_user_can($tax->cap->assign_terms);

    if (!$terms) {
        echo '<p>' . esc_html__('No themes yet.', 'themeify') . '</p>';
    } else {
        echo '<p class="description">' . esc_html__('Every doc belongs to one theme.', 'themeify') . '</p>';
        echo '<ul class="tf-theme-radios">';
        foreach ($terms as $term) {
            printf(
                '<li><label><input type="radio" name="tax_input[doc_theme][]" value="%1$d"%2$s%3$s required> %4$s</label></li>',
                (int) $term->term_id,
                checked($selected, (int) $term->term_id, false),
                disabled(!$can, true, false),
                esc_html($term->name)
            );
        }
        echo '</ul>';
    }

    if (current_user_can($tax->cap->edit_terms)) {
        $url = admin_url('edit-tags.php?taxonomy=doc_theme&post_type=themeify_doc');
        echo '<p><a href="' . esc_url($url) . '">' . esc_html__('Manage themes', 'themeify') . '</a></p>';
    }
}

/** Block editor: radio buttons for the Themes panel (see assets/js/editor-doc-theme.js). */
add_action('enqueue_block_editor_assets', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'themeify_doc') {
        return;
    }
    $tax = get_taxonomy('doc_theme');
    wp_enqueue_script(
        'themeify-editor-doc-theme',
        THEMEIFY_URI . '/assets/js/editor-doc-theme.js',
        ['wp-hooks', 'wp-element', 'wp-data', 'wp-components', 'wp-core-data', 'wp-editor', 'wp-html-entities'],
        THEMEIFY_VERSION,
        true
    );
    wp_localize_script('themeify-editor-doc-theme', 'themeifyDocTheme', [
        'label'     => __('Theme', 'themeify'),
        'required'  => __('Choose a theme. Every doc needs one before it can be published.', 'themeify'),
        'noThemes'  => __('No themes yet.', 'themeify'),
        'manage'    => __('Manage themes', 'themeify'),
        'manageUrl' => current_user_can($tax->cap->edit_terms) ? admin_url('edit-tags.php?taxonomy=doc_theme&post_type=themeify_doc') : '',
    ]);
});

/**
 * Keep at most one theme on every doc, however terms are set (edit screen,
 * REST, code): several are cut to the first.
 */
add_action('set_object_terms', function ($object_id, $terms, $tt_ids, $taxonomy) {
    if ($taxonomy !== 'doc_theme') {
        return;
    }
    $ids = wp_get_object_terms($object_id, 'doc_theme', ['fields' => 'ids']);
    if (!is_wp_error($ids) && count($ids) > 1) {
        wp_set_object_terms($object_id, [(int) $ids[0]], 'doc_theme'); // re-fires this hook once with a single term
    }
}, 10, 4);

// "Add new" from a theme's doc list: the new doc (the auto-draft the editor opens) starts in that theme.
add_action('wp_insert_post', function ($post_id, $post) {
    global $pagenow;
    if ($pagenow !== 'post-new.php' || $post->post_type !== 'themeify_doc' || $post->post_status !== 'auto-draft' || empty($_GET['doc_theme'])) { // phpcs:ignore WordPress.Security.NonceVerification
        return;
    }
    $term = get_term_by('slug', sanitize_title(wp_unslash($_GET['doc_theme'])), 'doc_theme'); // phpcs:ignore WordPress.Security.NonceVerification
    if ($term) {
        wp_set_object_terms($post_id, [(int) $term->term_id], 'doc_theme');
    }
}, 10, 2);

/* ---------- Front end: a theme's docs, grouped by category ---------- */

function themeify_get_doc_theme($post_id)
{
    $terms = get_the_terms($post_id, 'doc_theme');
    return ($terms && !is_wp_error($terms)) ? $terms[0] : null;
}

function themeify_doc_theme_image($term_id, $size = 'large', $attr = [])
{
    $id = (int) get_term_meta($term_id, 'themeify_image', true);
    return $id ? wp_get_attachment_image($id, $size, false, $attr) : '';
}

/**
 * The published docs of one theme, keyed by doc category. Only categories that
 * hold at least one of these docs (plus their parents) are included.
 *
 * @return array{children: array<int, WP_Term[]>, docs: array<int, WP_Post[]>, other: WP_Post[]}
 */
function themeify_theme_doc_tree($theme_id)
{
    static $cache = [];
    $theme_id = (int) $theme_id;
    if (isset($cache[$theme_id])) {
        return $cache[$theme_id];
    }

    $docs = get_posts([
        'post_type'   => 'themeify_doc',
        'numberposts' => -1,
        'post_status' => 'publish',
        'orderby'     => ['menu_order' => 'ASC', 'title' => 'ASC'],
        'tax_query'   => [[
            'taxonomy' => 'doc_theme',
            'field'    => 'term_id',
            'terms'    => $theme_id,
        ]],
    ]);

    $by_cat = [];
    $other  = [];
    foreach ($docs as $doc) {
        $cats = get_the_terms($doc, 'doc_category');
        if (!$cats || is_wp_error($cats)) {
            $other[] = $doc;
            continue;
        }
        foreach ($cats as $cat) {
            $by_cat[$cat->term_id][] = $doc;
        }
    }

    $ids = array_keys($by_cat);
    foreach (array_keys($by_cat) as $id) {
        $ids = array_merge($ids, get_ancestors($id, 'doc_category', 'taxonomy'));
    }

    $children = [];
    if ($ids) { // an empty "include" would return every category
        $terms = get_terms([
            'taxonomy'   => 'doc_category',
            'include'    => array_unique(array_map('intval', $ids)),
            'hide_empty' => false,
            'orderby'    => 'name',
        ]);
        foreach (is_wp_error($terms) ? [] : $terms as $term) {
            $children[(int) $term->parent][] = $term;
        }
    }

    return $cache[$theme_id] = ['children' => $children, 'docs' => $by_cat, 'other' => $other];
}

/**
 * Flat list of [term, docs] in sidebar order; docs without a category come last
 * with term = null.
 */
function themeify_theme_doc_groups($tree)
{
    $out  = [];
    $walk = function ($parent) use (&$walk, &$out, $tree) {
        foreach ($tree['children'][$parent] ?? [] as $term) {
            if (!empty($tree['docs'][$term->term_id])) {
                $out[] = ['term' => $term, 'docs' => $tree['docs'][$term->term_id]];
            }
            $walk((int) $term->term_id);
        }
    };
    $walk(0);
    if ($tree['other']) {
        $out[] = ['term' => null, 'docs' => $tree['other']];
    }
    return $out;
}

/**
 * "Where to start" cards for a theme page: categories ticked "Show in Where to
 * start" that hold at least one of the theme's docs. Each links to the
 * category's first doc.
 *
 * @return array<int, array{term: WP_Term, url: string, count: int}>
 */
function themeify_theme_start_cards($tree)
{
    $cards = [];
    foreach (themeify_theme_doc_groups($tree) as $group) {
        $term = $group['term'];
        if ($term && get_term_meta($term->term_id, 'themeify_featured', true)) {
            $cards[] = ['term' => $term, 'url' => get_permalink($group['docs'][0]), 'count' => count($group['docs'])];
        }
    }
    return $cards;
}

function themeify_theme_nav_doc($doc, $current)
{
    $is_current = (int) $doc->ID === (int) $current;
    echo '<li><a class="tf-tnav__link' . ($is_current ? ' is-current' : '') . '" href="' . esc_url(get_permalink($doc)) . '"'
        . ($is_current ? ' aria-current="page"' : '') . '>' . esc_html(get_the_title($doc)) . '</a></li>';
}

function themeify_theme_nav_branch($tree, $parent, $current, $open)
{
    foreach ($tree['children'][$parent] ?? [] as $term) {
        $is_open = in_array((int) $term->term_id, $open, true);
        echo '<li><details class="tf-tnav__group"' . ($is_open ? ' open' : '') . '>';
        echo '<summary class="tf-tnav__link">' . themeify_icon(themeify_category_icon($term->term_id))
            . '<span class="tf-tnav__text">' . esc_html($term->name) . '</span>'
            . themeify_icon('chevron', 'tf-icon tf-icon--chevron') . '</summary>';
        echo '<ul class="tf-tnav__sub">';
        foreach ($tree['docs'][$term->term_id] ?? [] as $doc) {
            themeify_theme_nav_doc($doc, $current);
        }
        themeify_theme_nav_branch($tree, (int) $term->term_id, $current, $open);
        echo '</ul></details></li>';
    }
}

/* ---------- Icons (24px grid, stroked) ---------- */

/** Icons an editor can pick for a doc category: key => [label, svg paths]. */
function themeify_category_icons()
{
    return [
        'folder'    => [__('Folder', 'themeify'), '<path d="M3 7.5A1.5 1.5 0 0 1 4.5 6H9l2 2h8.5A1.5 1.5 0 0 1 21 9.5v8a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5v-10Z"/>'],
        'book'      => [__('Book', 'themeify'), '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H10a2 2 0 0 1 2 2v13a1.5 1.5 0 0 0-1.5-1.5h-5A1.5 1.5 0 0 1 4 16V5.5Z"/><path d="M20 5.5A1.5 1.5 0 0 0 18.5 4H14a2 2 0 0 0-2 2v13a1.5 1.5 0 0 1 1.5-1.5h5a1.5 1.5 0 0 0 1.5-1.5V5.5Z"/>'],
        'rocket'    => [__('Getting started', 'themeify'), '<path d="M12.5 15.5 8.5 11.5C10.5 6.5 14.5 3.5 20.5 3.5c0 6-3 10-8 12Z"/><path d="M8.5 11.5H5L7.5 8H11M12.5 15.5V19l3.5-2.5V13"/><circle cx="15.5" cy="8.5" r="1.5"/><path d="M6 16c-1.5 1-2 4-2 4s3-.5 4-2"/>'],
        'copy'      => [__('Presets', 'themeify'), '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5.5A1.5 1.5 0 0 0 14.5 4h-9A1.5 1.5 0 0 0 4 5.5v9A1.5 1.5 0 0 0 5.5 16H8"/>'],
        'sliders'   => [__('Settings', 'themeify'), '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>'],
        'layout'    => [__('Header / footer', 'themeify'), '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M3 15h18"/>'],
        'rows'      => [__('Sections', 'themeify'), '<rect x="4" y="4" width="16" height="6" rx="1.5"/><rect x="4" y="14" width="16" height="6" rx="1.5"/>'],
        'template'  => [__('Page template', 'themeify'), '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 10h16M10 10v10"/>'],
        'columns'   => [__('Cards', 'themeify'), '<rect x="4" y="4" width="7" height="16" rx="1.5"/><rect x="13" y="4" width="7" height="16" rx="1.5"/>'],
        'grid'      => [__('Blocks', 'themeify'), '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>'],
        'help'      => [__('FAQ / help', 'themeify'), '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.4M12 17h.01"/>'],
        'code'      => [__('Developers', 'themeify'), '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>'],
        'clipboard' => [__('What\'s new', 'themeify'), '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/>'],
        'file'      => [__('Article', 'themeify'), '<path d="M14 3H6.5A1.5 1.5 0 0 0 5 4.5v15A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5V8l-5-5Z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>'],
        'image'     => [__('Media', 'themeify'), '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m21 16-5-5-9 9"/>'],
        'window'    => [__('Modal / popup', 'themeify'), '<rect x="3" y="4" width="18" height="16" rx="2"/><rect x="7" y="8" width="10" height="8" rx="1"/>'],
        'cart'      => [__('Shop / product', 'themeify'), '<path d="M3 4h2l2.4 11h10.6l2-8H6.3"/><circle cx="9" cy="19" r="1.3"/><circle cx="17" cy="19" r="1.3"/>'],
        'star'      => [__('Popular', 'themeify'), '<path d="m12 3 2.7 5.6 6.1.8-4.5 4.2 1.1 6.1L12 16.8l-5.4 2.9 1.1-6.1-4.5-4.2 6.1-.8L12 3Z"/>'],
        'zap'       => [__('Performance', 'themeify'), '<path d="M13 3 5 13.5h6L10 21l8-10.5h-6L13 3Z"/>'],
        'globe'     => [__('Translation', 'themeify'), '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9c-2.5-2.5-3.5-5.5-3.5-9s1-6.5 3.5-9Z"/>'],
        'user'      => [__('Account', 'themeify'), '<circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5"/>'],
        'download'  => [__('Install / download', 'themeify'), '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>'],
        'upload'    => [__('Import / upload', 'themeify'), '<path d="M12 15V4M7 9l5-5 5 5M5 20h14"/>'],
        'home'      => [__('Home', 'themeify'), '<path d="M4 11 12 4l8 7"/><path d="M6 9.5V20h12V9.5"/><path d="M10 20v-5h4v5"/>'],
        'palette'   => [__('Colors / design', 'themeify'), '<path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.5-.8 1.5-1.5 0-.9-.8-1.3-.8-2.2 0-.9.7-1.6 1.6-1.6H17a4 4 0 0 0 4-4C21 6.8 17 3 12 3Z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7.5" r="1"/><circle cx="14.5" cy="7.5" r="1"/>'],
        'type'      => [__('Typography', 'themeify'), '<path d="M5 7V5h14v2M12 5v14M9 19h6"/>'],
        'sun'       => [__('Appearance', 'themeify'), '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>'],
        'moon'      => [__('Dark mode', 'themeify'), '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z"/>'],
        'pen'       => [__('Editing / writing', 'themeify'), '<path d="M15.5 4.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4L15.5 4.5Z"/>'],
        'menu'      => [__('Menu / navigation', 'themeify'), '<path d="M4 7h16M4 12h16M4 17h16"/>'],
        'sitemap'   => [__('Structure', 'themeify'), '<rect x="9" y="3" width="6" height="5" rx="1"/><rect x="3" y="16" width="6" height="5" rx="1"/><rect x="15" y="16" width="6" height="5" rx="1"/><path d="M12 8v4M6 16v-4h12v4"/>'],
        'layers'    => [__('Layers / elements', 'themeify'), '<path d="m12 4 9 4.5-9 4.5-9-4.5L12 4Z"/><path d="m3 12.5 9 4.5 9-4.5M3 16.5l9 4.5 9-4.5"/>'],
        'slider'    => [__('Slider / carousel', 'themeify'), '<rect x="6" y="5" width="12" height="14" rx="1.5"/><path d="M3 8v8M21 8v8"/>'],
        'list'      => [__('List', 'themeify'), '<path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>'],
        'filter'    => [__('Filters', 'themeify'), '<path d="M4 5h16l-6 7.5V19l-4 1.5v-8L4 5Z"/>'],
        'search'    => [__('Search', 'themeify'), '<circle cx="11" cy="11" r="6"/><path d="m20 20-4.5-4.5"/>'],
        'monitor'   => [__('Desktop', 'themeify'), '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>'],
        'phone'     => [__('Mobile / responsive', 'themeify'), '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/>'],
        'eye'       => [__('Preview / demo', 'themeify'), '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>'],
        'video'     => [__('Video', 'themeify'), '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/>'],
        'play'      => [__('Tutorials', 'themeify'), '<circle cx="12" cy="12" r="9"/><path d="m10 8.5 5 3.5-5 3.5v-7Z"/>'],
        'music'     => [__('Audio', 'themeify'), '<path d="M9 18V5l11-2v13"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="16" r="2.5"/>'],
        'newspaper' => [__('Blog / news', 'themeify'), '<path d="M5 5h12v14a1 1 0 0 0 1 1H6a1 1 0 0 1-1-1V5Z"/><path d="M17 9h2v10a1 1 0 0 1-1 1M8 9h6M8 13h6M8 16h4"/>'],
        'archive'   => [__('Archive', 'themeify'), '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4"/>'],
        'bookmark'  => [__('Guides', 'themeify'), '<path d="M7 4h10a1 1 0 0 1 1 1v15l-6-4-6 4V5a1 1 0 0 1 1-1Z"/>'],
        'lightbulb' => [__('Tips', 'themeify'), '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.4 1 1.1 1 1.8V16h5v-.3c0-.7.4-1.4 1-1.8A6 6 0 0 0 12 3Z"/>'],
        'info'      => [__('Information', 'themeify'), '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>'],
        'check'     => [__('Requirements', 'themeify'), '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>'],
        'alert'     => [__('Troubleshooting', 'themeify'), '<path d="M12 4 2.5 20h19L12 4Z"/><path d="M12 10v4M12 17h.01"/>'],
        'wrench'    => [__('Tools', 'themeify'), '<path d="M15 4a5 5 0 0 0-4.6 6.9L4 17.3V20h2.7l6.4-6.4A5 5 0 0 0 20 9l-3 1-2.5-2.5 1-3A5 5 0 0 0 15 4Z"/>'],
        'puzzle'    => [__('Plugins / integrations', 'themeify'), '<path d="M9 4h3v2a1.5 1.5 0 0 0 3 0V4h3a2 2 0 0 1 2 2v3h-2a1.5 1.5 0 0 0 0 3h2v6a2 2 0 0 1-2 2h-3v-2a1.5 1.5 0 0 0-3 0v2H6a2 2 0 0 1-2-2v-3h2a1.5 1.5 0 0 0 0-3H4V6a2 2 0 0 1 2-2h3Z"/>'],
        'terminal'  => [__('Command line', 'themeify'), '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 9 3 3-3 3M13 15h4"/>'],
        'database'  => [__('Data', 'themeify'), '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v12c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3"/>'],
        'server'    => [__('Server', 'themeify'), '<rect x="4" y="4" width="16" height="7" rx="1.5"/><rect x="4" y="13" width="16" height="7" rx="1.5"/><path d="M8 7.5h.01M8 16.5h.01"/>'],
        'cloud'     => [__('Hosting / cloud', 'themeify'), '<path d="M7 18a4 4 0 0 1-.5-8A6 6 0 0 1 18 9a4.5 4.5 0 0 1-.5 9H7Z"/>'],
        'refresh'   => [__('Updates', 'themeify'), '<path d="M20 11a8 8 0 0 0-14.5-4.5L4 8M4 4v4h4M4 13a8 8 0 0 0 14.5 4.5L20 16M20 20v-4h-4"/>'],
        'link'      => [__('Links', 'themeify'), '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>'],
        'shield'    => [__('Security', 'themeify'), '<path d="M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>'],
        'lock'      => [__('Privacy', 'themeify'), '<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>'],
        'key'       => [__('License', 'themeify'), '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M16 7l2 2M14 9l2 2"/>'],
        'users'     => [__('Team / community', 'themeify'), '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c.8-3.3 3.2-5 6-5s5.2 1.7 6 5M16 4.5a3.5 3.5 0 0 1 0 7M18 15c1.5.6 2.6 2.3 3 5"/>'],
        'mail'      => [__('Email / contact', 'themeify'), '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>'],
        'chat'      => [__('Support', 'themeify'), '<path d="M5 5h14a1.5 1.5 0 0 1 1.5 1.5v9A1.5 1.5 0 0 1 19 17h-8l-4.5 3.5V17H5a1.5 1.5 0 0 1-1.5-1.5v-9A1.5 1.5 0 0 1 5 5Z"/>'],
        'megaphone' => [__('Announcements', 'themeify'), '<path d="M4 10v4a1 1 0 0 0 1 1h2l8 4V5L7 9H5a1 1 0 0 0-1 1Z"/><path d="M18.5 9a4 4 0 0 1 0 6M8 15l1 5h2.5l-1-4.5"/>'],
        'bell'      => [__('Notifications', 'themeify'), '<path d="M6 16v-5a6 6 0 0 1 12 0v5l1.5 2h-15L6 16Z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>'],
        'calendar'  => [__('Events / dates', 'themeify'), '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M8 3v4M16 3v4"/>'],
        'clock'     => [__('History', 'themeify'), '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        'chart'     => [__('Analytics', 'themeify'), '<path d="M4 4v16h16"/><path d="M8 15v-3M12 15V8M16 15v-5"/>'],
        'tag'       => [__('Pricing / tags', 'themeify'), '<path d="M3.5 12.5v-8a1 1 0 0 1 1-1h8l8 8-9 9-8-8Z"/><circle cx="8" cy="8" r="1.5"/>'],
        'credit'    => [__('Payments', 'themeify'), '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>'],
        'box'       => [__('Package', 'themeify'), '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="M4 7.5 12 12l8-4.5M12 12v9"/>'],
        'truck'     => [__('Shipping', 'themeify'), '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.5"/><circle cx="17" cy="17.5" r="1.5"/>'],
        'gift'      => [__('Extras', 'themeify'), '<rect x="4" y="8" width="16" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12M12 8s-1.5-4-3.5-4a2 2 0 0 0 0 4M12 8s1.5-4 3.5-4a2 2 0 0 1 0 4"/>'],
        'heart'     => [__('Favorites', 'themeify'), '<path d="M12 20s-7.5-4.5-7.5-10A4.5 4.5 0 0 1 12 7.5 4.5 4.5 0 0 1 19.5 10c0 5.5-7.5 10-7.5 10Z"/>'],
        'sparkles'  => [__('Features', 'themeify'), '<path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"/><path d="m19 15 .7 1.8 1.8.7-1.8.7L19 20l-.7-1.8-1.8-.7 1.8-.7L19 15Z"/>'],
        'flag'      => [__('Milestones', 'themeify'), '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>'],
        'map'       => [__('Map / location', 'themeify'), '<path d="M9 4 3.5 6v14L9 18l6 2 5.5-2V4L15 6 9 4Z"/><path d="M9 4v14M15 6v14"/>'],
        'compass'   => [__('Explore', 'themeify'), '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>'],
    ];
}

/** Icons used when a category / theme has none chosen. */
const THEMEIFY_DEFAULT_CATEGORY_ICON = 'folder';
const THEMEIFY_DEFAULT_THEME_ICON    = 'book';

/** Icon key stored on a term ("themeify_icon" meta), or $default. */
function themeify_term_icon($term_id, $default)
{
    $icon = get_term_meta($term_id, 'themeify_icon', true);
    return isset(themeify_category_icons()[$icon]) ? $icon : $default;
}

function themeify_category_icon($term_id)
{
    return themeify_term_icon($term_id, THEMEIFY_DEFAULT_CATEGORY_ICON);
}

function themeify_theme_icon($term_id)
{
    return themeify_term_icon($term_id, THEMEIFY_DEFAULT_THEME_ICON);
}

/** Save the icon picker of a term form; the default icon is stored as "none". */
function themeify_save_term_icon($term_id, $default)
{
    $icon = sanitize_key(wp_unslash($_POST['themeify_icon'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification -- checked by core before the term hooks
    if (isset(themeify_category_icons()[$icon]) && $icon !== $default) {
        update_term_meta($term_id, 'themeify_icon', $icon);
    } else {
        delete_term_meta($term_id, 'themeify_icon');
    }
}

function themeify_icon($name, $class = 'tf-icon')
{
    static $ui = [
        'chevron'  => '<path d="m9 6 6 6-6 6"/>',
        'chevrons' => '<path d="m8 9 4-4 4 4M8 15l4 4 4-4"/>',
        'lines'    => '<path d="M4 6h16M4 12h11M4 18h14"/>',
        'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
    ];
    $icons = themeify_category_icons();
    $paths = $ui[$name] ?? ($icons[$name][1] ?? '');
    if ($paths === '') {
        return '';
    }
    return '<svg class="' . esc_attr($class) . '" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $paths . '</svg>';
}

/* ---------- Admin: image + "default theme" on the add / edit theme screens ---------- */

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['edit-tags.php', 'term.php'], true)) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->taxonomy, ['doc_theme', 'doc_category'], true)) {
        return;
    }
    if ($screen->taxonomy === 'doc_theme') {
        wp_enqueue_media();
    }
    wp_enqueue_style('themeify-admin', THEMEIFY_URI . '/assets/css/admin.css', [], THEMEIFY_VERSION);
    wp_enqueue_script('themeify-admin-term', THEMEIFY_URI . '/assets/js/admin-term.js', ['jquery'], THEMEIFY_VERSION, true);
    wp_localize_script('themeify-admin-term', 'themeifyTerm', [
        'chooseImage' => __('Choose image', 'themeify'),
        'useImage'    => __('Use image', 'themeify'),
    ]);
});

function themeify_theme_image_input($value)
{
    $value = (int) $value;
    $url   = $value ? wp_get_attachment_image_url($value, 'thumbnail') : '';
    echo '<span class="tf-image">';
    echo '<input type="hidden" name="themeify_image" value="' . esc_attr($value ?: '') . '">';
    echo '<span class="tf-image__preview">' . ($url ? '<img src="' . esc_url($url) . '" alt="">' : '') . '</span>';
    echo '<button type="button" class="button tf-image__pick">' . esc_html__('Choose image', 'themeify') . '</button> ';
    echo '<button type="button" class="button-link tf-image__clear">' . esc_html__('Remove', 'themeify') . '</button>';
    echo '</span>';
    echo '<p class="description">' . esc_html__('Shown in the theme switcher and at the top of this theme\'s docs page.', 'themeify') . '</p>';
}

function themeify_theme_icon_description()
{
    return __('Shown next to the theme in the docs sidebar and on its card, and in the theme switcher when the theme has no image. Book is the default.', 'themeify');
}

add_action('doc_theme_add_form_fields', function () {
    ?>
    <div class="form-field">
        <label><?php esc_html_e('Icon', 'themeify'); ?></label>
        <?php themeify_icon_picker(THEMEIFY_DEFAULT_THEME_ICON, THEMEIFY_DEFAULT_THEME_ICON, themeify_theme_icon_description()); ?>
    </div>
    <div class="form-field">
        <label><?php esc_html_e('Image', 'themeify'); ?></label>
        <?php themeify_theme_image_input(0); ?>
    </div>
    <?php
});

add_action('doc_theme_edit_form_fields', function ($term) {
    ?>
    <tr class="form-field">
        <th scope="row"><?php esc_html_e('Icon', 'themeify'); ?></th>
        <td><?php themeify_icon_picker(themeify_theme_icon($term->term_id), THEMEIFY_DEFAULT_THEME_ICON, themeify_theme_icon_description()); ?></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><?php esc_html_e('Image', 'themeify'); ?></th>
        <td><?php themeify_theme_image_input(get_term_meta($term->term_id, 'themeify_image', true)); ?></td>
    </tr>
    <?php
});

// WordPress checks its own nonce on these forms before the created/edited hooks run.
// Quick edit does not send these fields, so it leaves them unchanged.
// phpcs:disable WordPress.Security.NonceVerification
function themeify_save_doc_theme_fields($term_id)
{
    $cap = get_taxonomy('doc_theme')->cap;

    if (isset($_POST['themeify_image']) && current_user_can($cap->edit_terms)) {
        $image = absint(wp_unslash($_POST['themeify_image']));
        if ($image) {
            update_term_meta($term_id, 'themeify_image', $image);
        } else {
            delete_term_meta($term_id, 'themeify_image');
        }
    }

    if (isset($_POST['themeify_icon']) && current_user_can($cap->edit_terms)) {
        themeify_save_term_icon($term_id, THEMEIFY_DEFAULT_THEME_ICON);
    }
}
// phpcs:enable
add_action('created_doc_theme', 'themeify_save_doc_theme_fields');
add_action('edited_doc_theme', 'themeify_save_doc_theme_fields');

/* ---------- Admin: icon picker on the add / edit doc category screens ---------- */

function themeify_icon_picker($selected, $default = THEMEIFY_DEFAULT_CATEGORY_ICON, $description = null)
{
    $selected = isset(themeify_category_icons()[$selected]) ? $selected : $default;
    echo '<div class="tf-icon-picker" role="radiogroup" aria-label="' . esc_attr__('Icon', 'themeify') . '">';
    foreach (themeify_category_icons() as $key => $icon) {
        printf(
            '<label class="tf-icon-picker__item" title="%1$s"><input type="radio" name="themeify_icon" value="%2$s"%3$s%4$s>%5$s<span class="screen-reader-text">%1$s</span></label>',
            esc_attr($icon[0]),
            esc_attr($key),
            checked($selected, $key, false),
            $key === $default ? ' data-default="1"' : '',
            themeify_icon($key)
        );
    }
    echo '</div>';
    echo '<p class="description">' . esc_html($description ?? __('Shown next to the category in the docs sidebar and on its card. Folder is the default.', 'themeify')) . '</p>';
}

function themeify_featured_checkbox($checked)
{
    echo '<label><input type="checkbox" name="themeify_featured" value="1"' . checked($checked, true, false) . '> '
        . esc_html__('Show this category as a card in "Where to start" on theme pages', 'themeify') . '</label>';
}

add_action('doc_category_add_form_fields', function () {
    ?>
    <div class="form-field">
        <label><?php esc_html_e('Icon', 'themeify'); ?></label>
        <?php themeify_icon_picker(THEMEIFY_DEFAULT_CATEGORY_ICON); ?>
    </div>
    <div class="form-field">
        <?php themeify_featured_checkbox(false); ?>
    </div>
    <?php
});

add_action('doc_category_edit_form_fields', function ($term) {
    ?>
    <tr class="form-field">
        <th scope="row"><?php esc_html_e('Icon', 'themeify'); ?></th>
        <td><?php themeify_icon_picker(themeify_category_icon($term->term_id)); ?></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><?php esc_html_e('Where to start', 'themeify'); ?></th>
        <td>
            <?php themeify_featured_checkbox((bool) get_term_meta($term->term_id, 'themeify_featured', true)); ?>
            <p class="description"><?php esc_html_e('The card shows the category description and opens the first doc of this category for that theme. It appears once the theme has a doc in this category.', 'themeify'); ?></p>
        </td>
    </tr>
    <?php
});

// Only the add / edit forms send "themeify_icon"; quick edit leaves these fields alone.
// phpcs:disable WordPress.Security.NonceVerification
function themeify_save_doc_category_fields($term_id)
{
    if (!isset($_POST['themeify_icon']) || !current_user_can(get_taxonomy('doc_category')->cap->edit_terms)) {
        return;
    }
    themeify_save_term_icon($term_id, THEMEIFY_DEFAULT_CATEGORY_ICON);

    if (!empty($_POST['themeify_featured'])) {
        update_term_meta($term_id, 'themeify_featured', 1);
    } else {
        delete_term_meta($term_id, 'themeify_featured');
    }
}
// phpcs:enable
add_action('created_doc_category', 'themeify_save_doc_category_fields');
add_action('edited_doc_category', 'themeify_save_doc_category_fields');

// Icon column in the Docs → Categories and Docs → Themes lists.
$themeify_icon_column = function ($columns) {
    return array_slice($columns, 0, 1, true) + ['themeify_icon' => __('Icon', 'themeify')] + array_slice($columns, 1, null, true);
};
add_filter('manage_edit-doc_category_columns', $themeify_icon_column);
add_filter('manage_edit-doc_theme_columns', $themeify_icon_column);
add_filter('manage_doc_category_custom_column', function ($content, $column, $term_id) {
    return $column === 'themeify_icon' ? themeify_icon(themeify_category_icon($term_id)) : $content;
}, 10, 3);
add_filter('manage_doc_theme_custom_column', function ($content, $column, $term_id) {
    return $column === 'themeify_icon' ? themeify_icon(themeify_theme_icon($term_id)) : $content;
}, 10, 3);
