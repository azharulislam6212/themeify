<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Each doc theme (doc_theme term) has one "theme page" post that holds the
 * block-editor content of its documentation page. The link is stored both ways:
 * term meta "themeify_page_id" and post meta "_themeify_term_id".
 */

function themeify_theme_page_id($term_id)
{
    $id = (int) get_term_meta($term_id, 'themeify_page_id', true);
    return ($id && get_post_type($id) === 'themeify_theme_page') ? $id : 0;
}

/** Admin URL that opens (and on first use creates) a theme's page in the block editor. */
function themeify_theme_page_edit_url($term_id)
{
    $term_id = (int) $term_id;
    return add_query_arg([
        'action'   => 'themeify_edit_theme_page',
        'term_id'  => $term_id,
        '_wpnonce' => wp_create_nonce('themeify_edit_theme_page_' . $term_id),
    ], admin_url('admin-post.php'));
}

function themeify_can_edit_theme_pages()
{
    return current_user_can(get_post_type_object('themeify_theme_page')->cap->edit_posts);
}

/** Theme linked to a theme page, or null. Only counts when the link holds both ways. */
function themeify_theme_page_term($page_id)
{
    $term = get_term((int) get_post_meta($page_id, '_themeify_term_id', true), 'doc_theme');
    return ($term instanceof WP_Term && themeify_theme_page_id($term->term_id) === (int) $page_id) ? $term : null;
}

/**
 * Link a theme to a theme page (0 unlinks it). A theme has one page and a page
 * one theme, so a page already used by another theme moves to this one.
 */
function themeify_link_theme_page($term_id, $page_id)
{
    $term_id = (int) $term_id;
    $page_id = (int) $page_id;
    $old     = themeify_theme_page_id($term_id);
    if ($old === $page_id) {
        return;
    }
    if ($old && (int) get_post_meta($old, '_themeify_term_id', true) === $term_id) {
        delete_post_meta($old, '_themeify_term_id');
    }
    if (!$page_id) {
        delete_term_meta($term_id, 'themeify_page_id');
        return;
    }
    $previous = themeify_theme_page_term($page_id);
    if ($previous) {
        delete_term_meta($previous->term_id, 'themeify_page_id');
    }
    update_term_meta($term_id, 'themeify_page_id', $page_id);
    update_post_meta($page_id, '_themeify_term_id', $term_id);
}

add_action('admin_post_themeify_edit_theme_page', function () {
    $term_id = absint($_GET['term_id'] ?? 0);
    check_admin_referer('themeify_edit_theme_page_' . $term_id);

    $term = get_term($term_id, 'doc_theme');
    if (!$term instanceof WP_Term) {
        wp_die(esc_html__('Theme not found.', 'themeify'));
    }

    $page_id = themeify_theme_page_id($term_id);
    if (!$page_id) {
        if (!current_user_can(get_post_type_object('themeify_theme_page')->cap->create_posts)) {
            wp_die(esc_html__('You are not allowed to create theme pages.', 'themeify'));
        }
        $page_id = wp_insert_post([
            'post_type'   => 'themeify_theme_page',
            'post_status' => 'publish',
            'post_title'  => sprintf(__('%s documentation', 'themeify'), $term->name),
        ], true);
        if (is_wp_error($page_id)) {
            wp_die(esc_html($page_id->get_error_message()));
        }
        themeify_link_theme_page($term_id, $page_id);
    }

    if (!current_user_can('edit_post', $page_id)) {
        wp_die(esc_html__('You are not allowed to edit this page.', 'themeify'));
    }
    wp_safe_redirect(get_edit_post_link($page_id, 'raw'));
    exit;
});

// Deleting a theme deletes its page.
add_action('pre_delete_term', function ($term_id, $taxonomy) {
    if ($taxonomy !== 'doc_theme') {
        return;
    }
    $page_id = themeify_theme_page_id($term_id);
    if ($page_id) {
        wp_delete_post($page_id, true);
    }
}, 10, 2);

/* ---------- Page content select on the add / edit theme screens ---------- */

/** Select of every theme page (not trashed); each shows the theme it is used by. */
function themeify_theme_page_select($term_id)
{
    $term_id  = (int) $term_id;
    $selected = $term_id ? themeify_theme_page_id($term_id) : 0;
    $pages    = get_posts([
        'post_type'   => 'themeify_theme_page',
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC',
    ]);

    echo '<select name="themeify_page_id" id="themeify-page-id">';
    echo '<option value="0">' . esc_html__('— None —', 'themeify') . '</option>';
    foreach ($pages as $page) {
        $label = $page->post_title !== '' ? $page->post_title : __('(no title)', 'themeify');
        if ($page->post_status !== 'publish') {
            $label .= ' (' . get_post_status_object($page->post_status)->label . ')';
        }
        $owner = themeify_theme_page_term($page->ID);
        if ($owner && (int) $owner->term_id !== $term_id) {
            /* translators: %s: theme name */
            $label .= ' — ' . sprintf(__('used by %s', 'themeify'), $owner->name);
        }
        printf('<option value="%d"%s>%s</option>', (int) $page->ID, selected($selected, $page->ID, false), esc_html($label));
    }
    echo '</select>';
}

function themeify_theme_page_field_help()
{
    $list = admin_url('edit.php?post_type=themeify_theme_page');
    return sprintf(
        /* translators: %s: link to Docs → Theme pages */
        __('The page whose content shows on this theme\'s docs page, under the title, description and image. Choosing a page used by another theme moves it to this one. Pages are listed under %s.', 'themeify'),
        '<a href="' . esc_url($list) . '">' . esc_html__('Docs → Theme pages', 'themeify') . '</a>'
    );
}

add_action('doc_theme_add_form_fields', function () {
    if (!themeify_can_edit_theme_pages()) {
        return;
    }
    ?>
    <div class="form-field">
        <label for="themeify-page-id"><?php esc_html_e('Page content', 'themeify'); ?></label>
        <?php themeify_theme_page_select(0); ?>
        <p class="description"><?php echo wp_kses(themeify_theme_page_field_help(), ['a' => ['href' => []]]); ?> <?php esc_html_e('Leave it on None to create a new page later with "Edit page content".', 'themeify'); ?></p>
    </div>
    <?php
}, 20);

add_action('doc_theme_edit_form_fields', function ($term) {
    if (!themeify_can_edit_theme_pages()) {
        return;
    }
    $has_page = (bool) themeify_theme_page_id($term->term_id);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="themeify-page-id"><?php esc_html_e('Page content', 'themeify'); ?></label></th>
        <td>
            <div class="tf-page-pick">
                <?php themeify_theme_page_select($term->term_id); ?>
                <a class="button button-primary" href="<?php echo esc_url(themeify_theme_page_edit_url($term->term_id)); ?>"><?php echo esc_html($has_page ? __('Edit page content', 'themeify') : __('Create page content', 'themeify')); ?></a>
            </div>
            <p class="description"><?php echo wp_kses(themeify_theme_page_field_help(), ['a' => ['href' => []]]); ?> <?php esc_html_e('After choosing another page, click Update before editing it.', 'themeify'); ?></p>
        </td>
    </tr>
    <?php
}, 20);

// WordPress checks its own nonce on these forms before the created/edited hooks run.
// Quick edit does not send this field, so it leaves the link unchanged.
function themeify_save_theme_page_field($term_id)
{
    if (!isset($_POST['themeify_page_id']) || !themeify_can_edit_theme_pages() || !current_user_can(get_taxonomy('doc_theme')->cap->edit_terms)) { // phpcs:ignore WordPress.Security.NonceVerification
        return;
    }
    $page_id = absint(wp_unslash($_POST['themeify_page_id'])); // phpcs:ignore WordPress.Security.NonceVerification
    if ($page_id && (get_post_type($page_id) !== 'themeify_theme_page' || !current_user_can('edit_post', $page_id))) {
        return;
    }
    themeify_link_theme_page($term_id, $page_id);
}
add_action('created_doc_theme', 'themeify_save_theme_page_field');
add_action('edited_doc_theme', 'themeify_save_theme_page_field');

/* ---------- Docs → Theme pages list: which theme uses each page ---------- */

add_filter('manage_themeify_theme_page_posts_columns', function ($columns) {
    return array_slice($columns, 0, 2, true) + ['themeify_theme' => __('Theme', 'themeify')] + array_slice($columns, 2, null, true);
});
add_action('manage_themeify_theme_page_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'themeify_theme') {
        return;
    }
    $term = themeify_theme_page_term($post_id);
    if (!$term) {
        echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__('Not used by a theme', 'themeify') . '</span>';
        return;
    }
    $url = get_edit_term_link($term, 'doc_theme');
    echo $url ? '<a href="' . esc_url($url) . '">' . esc_html($term->name) . '</a>' : esc_html($term->name);
}, 10, 2);

/* ---------- Links to the editor ---------- */

add_filter('doc_theme_row_actions', function ($actions, $term) {
    if (themeify_can_edit_theme_pages()) {
        $actions['themeify_page'] = '<a href="' . esc_url(themeify_theme_page_edit_url($term->term_id)) . '">' . esc_html__('Edit page content', 'themeify') . '</a>';
    }
    return $actions;
}, 10, 2);

add_action('admin_bar_menu', function ($bar) {
    if (is_admin() || !is_tax('doc_theme') || !themeify_can_edit_theme_pages()) {
        return;
    }
    $bar->add_node([
        'id'    => 'themeify-theme-page',
        'title' => __('Edit page content', 'themeify'),
        'href'  => themeify_theme_page_edit_url(get_queried_object_id()),
    ]);
}, 81);

/* ---------- Inside the block editor ---------- */

add_action('add_meta_boxes_themeify_theme_page', function () {
    add_meta_box('themeify-theme-page', __('Theme', 'themeify'), function ($post) {
        $term = themeify_theme_page_term($post->ID);
        if (!$term) {
            echo '<p>' . esc_html__('This page is not used by a theme yet. To show it, edit a theme under Docs → Themes and choose this page in "Page content".', 'themeify') . '</p>';
            return;
        }
        printf(
            '<p>%s</p>',
            sprintf(
                /* translators: %s: theme name */
                esc_html__('Content of the %s documentation page. The page title, description and image come from the theme.', 'themeify'),
                '<strong>' . esc_html($term->name) . '</strong>'
            )
        );
        echo '<p><a href="' . esc_url(get_term_link($term)) . '" target="_blank" rel="noopener">' . esc_html__('View page', 'themeify') . '</a>'
            . ' &middot; <a href="' . esc_url(get_edit_term_link($term, 'doc_theme')) . '">' . esc_html__('Edit theme', 'themeify') . '</a></p>';
    }, null, 'side', 'high');
});

// Keep Docs → Theme pages highlighted in the admin menu on the list and while editing a theme page.
add_filter('parent_file', function ($parent_file) {
    $screen = get_current_screen();
    return ($screen && $screen->post_type === 'themeify_theme_page') ? 'edit.php?post_type=themeify_doc' : $parent_file;
});
add_filter('submenu_file', function ($submenu_file) {
    $screen = get_current_screen();
    return ($screen && $screen->post_type === 'themeify_theme_page') ? 'edit.php?post_type=themeify_theme_page' : $submenu_file;
});

/* ---------- Front end ---------- */

/**
 * Rendered content of a theme's page with anchors on its headings and Details
 * blocks: ['html' => string, 'items' => toc entries]. Empty when there is none.
 */
function themeify_theme_page_content($term_id)
{
    $page_id = themeify_theme_page_id($term_id);
    $page    = $page_id ? get_post($page_id) : null;
    if (!$page || $page->post_status !== 'publish' || trim($page->post_content) === '') {
        return ['html' => '', 'items' => []];
    }

    // Blocks and embeds read the global post while rendering.
    $previous        = $GLOBALS['post'] ?? null;
    $GLOBALS['post'] = $page;
    setup_postdata($page);
    $html = str_replace(']]>', ']]&gt;', apply_filters('the_content', $page->post_content));
    $GLOBALS['post'] = $previous;
    if ($previous) {
        setup_postdata($previous);
    }

    return themeify_content_anchors($html);
}
