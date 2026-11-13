<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Doc URLs carry the doc's theme: /docs/{theme}/{doc}/. A slug only has to be unique
 * within its theme, so Boost10 and Glow can each have a "header" doc:
 *   /docs/boost10/header/   /docs/glow/header/
 * Old /docs/{doc}/ links still open the doc and are redirected to the new URL.
 *
 * Admin: every doc theme gets its own item under Docs → All docs that lists only that
 * theme's docs. Themes added later show up there on their own.
 */

/* ---------- Which theme a doc is being saved with ---------- */

/** Theme picked in the save request, keyed by post ID (0 while a doc is being created). */
function themeify_doc_pending_theme($post_id, $theme_id = null)
{
    static $pending = [];
    if ($theme_id !== null) {
        $pending[(int) $post_id] = (int) $theme_id;
    }
    return $pending[(int) $post_id] ?? 0;
}

// Block editor / REST: terms are saved after the post, so remember the chosen theme first.
add_filter('rest_pre_insert_themeify_doc', function ($prepared, $request) {
    $ids = (array) ($request['doc_theme'] ?? []);
    if ($ids) {
        themeify_doc_pending_theme($prepared->ID ?? 0, absint(reset($ids)));
    }
    return $prepared;
}, 10, 2);

/** Theme the doc will belong to after this save: from the request, else its current one (0 if none). */
function themeify_doc_slug_theme($post_id)
{
    $pending = themeify_doc_pending_theme($post_id);
    if ($pending) {
        return $pending;
    }

    // Classic edit screen. phpcs:ignore WordPress.Security.NonceVerification -- core checks the nonce before saving.
    if (isset($_POST['tax_input']['doc_theme']) && absint($_POST['post_ID'] ?? 0) === (int) $post_id) {
        $ids = array_filter(array_map('absint', (array) wp_unslash($_POST['tax_input']['doc_theme']))); // phpcs:ignore WordPress.Security.NonceVerification
        if ($ids) {
            return (int) reset($ids);
        }
    }

    $theme = $post_id ? themeify_get_doc_theme($post_id) : null;
    return $theme ? (int) $theme->term_id : 0;
}

/** ID of a doc with this slug in this theme (other than $exclude), or 0. Published docs win. */
function themeify_doc_id_in_theme($slug, $theme_id, $exclude = 0)
{
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
         INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
         WHERE p.post_type = 'themeify_doc' AND p.post_name = %s AND p.ID != %d
           AND tt.taxonomy = 'doc_theme' AND tt.term_id = %d
         ORDER BY p.post_status = 'publish' DESC, p.ID ASC
         LIMIT 1",
        $slug,
        (int) $exclude,
        (int) $theme_id
    ));
}

/* ---------- Slugs: unique per theme ---------- */

add_filter('wp_unique_post_slug', function ($slug, $post_id, $status, $type, $parent, $original) {
    if ($type !== 'themeify_doc' || $original === '' || in_array($original, ['embed', 'feed', 'page', 'attachment', 'trackback'], true)) {
        return $slug;
    }
    $theme_id = themeify_doc_slug_theme($post_id);
    if (!$theme_id) {
        return $slug;
    }

    $candidate = $original;
    for ($n = 2; themeify_doc_id_in_theme($candidate, $theme_id, $post_id); $n++) {
        $candidate = _truncate_post_slug($original, 200 - (strlen((string) $n) + 1)) . '-' . $n;
    }
    return $candidate;
}, 10, 6);

/* ---------- URLs ---------- */

add_filter('post_type_link', function ($link, $post, $leavename) {
    // Drafts without pretty links (?p=…) keep WordPress's URL.
    if ($post->post_type !== 'themeify_doc' || !get_option('permalink_structure') || strpos($link, '?') !== false) {
        return $link;
    }
    $theme = themeify_get_doc_theme($post->ID);
    $slug  = $leavename ? '%themeify_doc%' : $post->post_name;
    if (!$theme || $slug === '') {
        return $link;
    }
    return home_url(user_trailingslashit('docs/' . $theme->slug . '/' . $slug));
}, 10, 3);

add_action('init', function () {
    add_rewrite_tag('%tf_doc_theme%', '([^/]+)');
    // Skips the docs archive's own /docs/page/N/ and /docs/feed/… URLs and a doc's /embed/ or /feed/.
    add_rewrite_rule(
        '^docs/(?!page/|feed/)([^/]+)/(?!(?:embed|feed|trackback|attachment)/?$)([^/]+)/?$',
        'index.php?post_type=themeify_doc&themeify_doc=$matches[2]&tf_doc_theme=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^docs/(?!page/|feed/)([^/]+)/([^/]+)/embed/?$',
        'index.php?post_type=themeify_doc&themeify_doc=$matches[2]&tf_doc_theme=$matches[1]&embed=true',
        'top'
    );
});

// /docs/{theme}/{doc}/ → the doc with that slug in that theme, or a 404.
add_filter('request', function ($vars) {
    if (empty($vars['tf_doc_theme']) || empty($vars['themeify_doc'])) {
        return $vars;
    }
    $theme = get_term_by('slug', $vars['tf_doc_theme'], 'doc_theme');
    $id    = $theme ? themeify_doc_id_in_theme($vars['themeify_doc'], $theme->term_id) : 0;
    if (!$id) {
        return ['error' => '404'];
    }
    unset($vars['tf_doc_theme'], $vars['themeify_doc'], $vars['name']);
    $vars['post_type'] = 'themeify_doc';
    $vars['p']         = $id;
    return $vars;
});

// Old /docs/{doc}/ links (and a doc whose theme changed) move to the current URL.
add_action('template_redirect', function () {
    if (!is_singular('themeify_doc') || is_preview() || is_embed() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }
    $want = (string) wp_parse_url(get_permalink(), PHP_URL_PATH);
    $have = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    if ($want !== '' && strtolower(untrailingslashit($want)) !== strtolower(untrailingslashit($have))) {
        $query = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        wp_safe_redirect(get_permalink() . ($query !== '' ? '?' . $query : ''), 301);
        exit;
    }
});

/* ---------- Admin: Docs → one item per theme under "All docs" ---------- */

const THEMEIFY_DOCS_MENU = 'edit.php?post_type=themeify_doc';

add_action('admin_menu', function () {
    $terms = get_terms(['taxonomy' => 'doc_theme', 'hide_empty' => false, 'orderby' => 'name']);
    if (is_wp_error($terms)) {
        return;
    }
    $cap = get_post_type_object('themeify_doc')->cap->edit_posts;
    foreach (array_values($terms) as $i => $term) {
        add_submenu_page(
            THEMEIFY_DOCS_MENU,
            $term->name,
            '<span class="tf-menu-theme">' . esc_html($term->name) . '</span>',
            $cap,
            THEMEIFY_DOCS_MENU . '&doc_theme=' . $term->slug,
            '',
            1 + $i // right after "All docs"
        );
    }
});

// Highlight the theme's item (not "All docs") while its list is open.
add_filter('submenu_file', function ($submenu_file) {
    global $pagenow, $typenow;
    if ($pagenow === 'edit.php' && $typenow === 'themeify_doc' && !empty($_GET['doc_theme'])) { // phpcs:ignore WordPress.Security.NonceVerification
        $slug = sanitize_title(wp_unslash($_GET['doc_theme'])); // phpcs:ignore WordPress.Security.NonceVerification
        if (get_term_by('slug', $slug, 'doc_theme')) {
            return THEMEIFY_DOCS_MENU . '&doc_theme=' . $slug;
        }
    }
    return $submenu_file;
});

add_action('admin_head', function () {
    echo '<style>#adminmenu .tf-menu-theme{padding-left:12px;position:relative}#adminmenu .tf-menu-theme::before{content:"";position:absolute;left:2px;top:50%;width:6px;border-top:1px solid currentColor;opacity:.5}</style>';
});

// On a theme's doc list, "Add new" opens a new doc already in that theme.
add_filter('admin_url', function ($url, $path) {
    global $pagenow, $typenow;
    if ($path !== 'post-new.php?post_type=themeify_doc' || $pagenow !== 'edit.php' || $typenow !== 'themeify_doc' || empty($_GET['doc_theme'])) { // phpcs:ignore WordPress.Security.NonceVerification
        return $url;
    }
    return add_query_arg('doc_theme', sanitize_title(wp_unslash($_GET['doc_theme'])), $url); // phpcs:ignore WordPress.Security.NonceVerification
}, 10, 2);

// The list's title says which theme is shown.
add_action('load-edit.php', function () {
    global $typenow;
    if ($typenow !== 'themeify_doc' || empty($_GET['doc_theme'])) { // phpcs:ignore WordPress.Security.NonceVerification
        return;
    }
    $term = get_term_by('slug', sanitize_title(wp_unslash($_GET['doc_theme'])), 'doc_theme'); // phpcs:ignore WordPress.Security.NonceVerification
    if ($term) {
        $type = get_post_type_object('themeify_doc');
        /* translators: %s: theme name */
        $type->labels->name = sprintf(__('%s docs', 'themeify'), $term->name);
    }
});
