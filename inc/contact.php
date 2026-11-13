<?php
/**
 * Contact form: front-end form (template-parts/contact-page.php), stored messages and settings.
 *
 * - Front end: page-contact.php (the page with slug "contact") or the "Contact page" template shows the
 *   page title, the page content as intro text, and the form (Name, Email, Theme, Message).
 * - Submissions are saved as "themeify_contact" posts and emailed to the address in the settings.
 * - Admin: a "Contacts" menu right below "Docs" with "Contacts" (the message list) and "Settings".
 */
if (!defined('ABSPATH')) {
    exit;
}

define('THEMEIFY_CONTACT_OPTION', 'themeify_contact_settings');

/* ------------------------------------------------------------------ */
/* Settings                                                           */
/* ------------------------------------------------------------------ */

function themeify_contact_defaults()
{
    return [
        'email'          => 'help@themeify.com',
        'notify'         => 1,
        'store'          => 1,
        'subject'        => __('New contact message from {name}', 'themeify'),
        'theme_field'    => 1,
        'theme_required' => 1,
        'button_text'    => __('Send Message', 'themeify'),
        'success'        => __('Thanks! Your message has been sent. We will get back to you soon.', 'themeify'),
    ];
}

/** All settings, or one when $key is given. */
function themeify_contact_settings($key = null)
{
    $saved    = get_option(THEMEIFY_CONTACT_OPTION, []);
    $settings = array_merge(themeify_contact_defaults(), is_array($saved) ? $saved : []);
    return $key === null ? $settings : ($settings[$key] ?? null);
}

/** Valid recipient addresses from the (comma separated) email setting. */
function themeify_contact_recipients()
{
    $list = array_filter(array_map('trim', explode(',', (string) themeify_contact_settings('email'))), 'is_email');
    return $list ?: [get_option('admin_email')];
}

/* ------------------------------------------------------------------ */
/* Stored messages: a private post type shown under "Contacts"        */
/* ------------------------------------------------------------------ */

add_action('init', function () {
    register_post_type('themeify_contact', [
        'labels' => [
            'name'               => __('Contacts', 'themeify'),
            'singular_name'      => __('Contact', 'themeify'),
            'menu_name'          => __('Contacts', 'themeify'),
            'all_items'          => __('Contacts', 'themeify'),
            'edit_item'          => __('Contact message', 'themeify'),
            'view_item'          => __('View message', 'themeify'),
            'search_items'       => __('Search contacts', 'themeify'),
            'not_found'          => __('No contact messages yet.', 'themeify'),
            'not_found_in_trash' => __('No contact messages in the Trash.', 'themeify'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 22, // right below Docs (21)
        'menu_icon'           => 'dashicons-email-alt',
        'supports'            => false,
        'capability_type'     => 'post',
        'capabilities'        => ['create_posts' => 'do_not_allow'], // messages only come from the form
        'map_meta_cap'        => true,
        'exclude_from_search' => true,
        'show_in_rest'        => false,
        'rewrite'             => false,
        'query_var'           => false,
    ]);
});

/** Save a message. Returns the post ID or 0. */
function themeify_contact_store(array $data)
{
    $id = wp_insert_post([
        'post_type'    => 'themeify_contact',
        'post_status'  => 'publish',
        'post_title'   => $data['name'],
        'post_content' => $data['message'],
    ], false, false);

    if (!$id || is_wp_error($id)) {
        return 0;
    }
    update_post_meta($id, '_tf_contact_email', $data['email']);
    update_post_meta($id, '_tf_contact_theme', $data['theme']);
    update_post_meta($id, '_tf_contact_theme_id', $data['theme_id']);
    update_post_meta($id, '_tf_contact_page', $data['page']);
    update_post_meta($id, '_tf_contact_unread', 1);
    return (int) $id;
}

/* ------------------------------------------------------------------ */
/* Admin: list columns, message view, unread badge, settings page     */
/* ------------------------------------------------------------------ */

add_filter('manage_themeify_contact_posts_columns', function () {
    return [
        'cb'                => '<input type="checkbox" />',
        'tf_contact_name'   => __('Name', 'themeify'),
        'tf_contact_email'  => __('Email', 'themeify'),
        'tf_contact_theme'  => __('Theme', 'themeify'),
        'tf_contact_msg'    => __('Message', 'themeify'),
        'date'              => __('Date', 'themeify'),
    ];
});

add_action('manage_themeify_contact_posts_custom_column', function ($column, $post_id) {
    switch ($column) {
        case 'tf_contact_name':
            $unread = get_post_meta($post_id, '_tf_contact_unread', true);
            printf(
                '<a class="row-title" href="%1$s">%2$s</a>%3$s',
                esc_url(get_edit_post_link($post_id)),
                esc_html(get_the_title($post_id) ?: __('(no name)', 'themeify')),
                $unread ? ' <span class="tf-contact-new">' . esc_html__('New', 'themeify') . '</span>' : ''
            );
            break;
        case 'tf_contact_email':
            $email = get_post_meta($post_id, '_tf_contact_email', true);
            if ($email) {
                printf('<a href="mailto:%1$s">%2$s</a>', esc_attr($email), esc_html($email));
            }
            break;
        case 'tf_contact_theme':
            echo esc_html(get_post_meta($post_id, '_tf_contact_theme', true) ?: '—');
            break;
        case 'tf_contact_msg':
            echo esc_html(wp_trim_words(get_post_field('post_content', $post_id), 18));
            break;
    }
}, 10, 2);

add_filter('list_table_primary_column', function ($default, $screen) {
    return $screen === 'edit-themeify_contact' ? 'tf_contact_name' : $default;
}, 10, 2);

// Row actions: View + Trash only.
add_filter('post_row_actions', function ($actions, $post) {
    if ($post->post_type !== 'themeify_contact') {
        return $actions;
    }
    $keep = array_intersect_key($actions, array_flip(['trash', 'untrash', 'delete']));
    if ($post->post_status !== 'trash') {
        $keep = ['view' => sprintf('<a href="%s">%s</a>', esc_url(get_edit_post_link($post->ID)), esc_html__('View', 'themeify'))] + $keep;
    }
    return $keep;
}, 10, 2);

add_filter('bulk_actions-edit-themeify_contact', function ($actions) {
    unset($actions['edit']);
    return $actions;
});

// Message screen: a read-only view instead of an editor.
add_action('add_meta_boxes_themeify_contact', function ($post) {
    remove_meta_box('submitdiv', 'themeify_contact', 'side');
    remove_meta_box('slugdiv', 'themeify_contact', 'normal');
    add_meta_box('tf-contact-message', __('Message', 'themeify'), 'themeify_contact_message_box', 'themeify_contact', 'normal', 'high');
    add_meta_box('tf-contact-actions', __('Actions', 'themeify'), 'themeify_contact_actions_box', 'themeify_contact', 'side', 'high');
    delete_post_meta($post->ID, '_tf_contact_unread'); // opened = read
});

function themeify_contact_message_box($post)
{
    $email = get_post_meta($post->ID, '_tf_contact_email', true);
    $theme = get_post_meta($post->ID, '_tf_contact_theme', true);
    $page  = (int) get_post_meta($post->ID, '_tf_contact_page', true);
    $rows  = [
        __('Name', 'themeify')  => esc_html($post->post_title),
        __('Email', 'themeify') => $email ? sprintf('<a href="mailto:%1$s">%2$s</a>', esc_attr($email), esc_html($email)) : '—',
        __('Theme', 'themeify') => $theme ? esc_html($theme) : '—',
        __('Date', 'themeify')  => esc_html(get_the_date('', $post) . ' ' . get_the_time('', $post)),
        __('Sent from', 'themeify') => $page ? sprintf('<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url(get_permalink($page)), esc_html(get_the_title($page))) : '—',
    ];
    echo '<table class="form-table" role="presentation"><tbody>';
    foreach ($rows as $label => $value) {
        printf('<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html($label), $value); // values escaped above
    }
    printf(
        '<tr><th scope="row">%1$s</th><td><div class="tf-contact-body">%2$s</div></td></tr>',
        esc_html__('Message', 'themeify'),
        nl2br(esc_html($post->post_content))
    );
    echo '</tbody></table>';
}

function themeify_contact_actions_box($post)
{
    $email = get_post_meta($post->ID, '_tf_contact_email', true);
    echo '<p>';
    if ($email) {
        $subject = rawurlencode(sprintf(__('Re: your message to %s', 'themeify'), get_bloginfo('name')));
        printf('<a class="button button-primary" href="mailto:%1$s?subject=%2$s">%3$s</a> ', esc_attr($email), esc_attr($subject), esc_html__('Reply by email', 'themeify'));
    }
    if (current_user_can('delete_post', $post->ID)) {
        printf('<a class="button-link-delete" href="%1$s">%2$s</a>', esc_url(get_delete_post_link($post->ID)), esc_html__('Move to Trash', 'themeify'));
    }
    echo '</p>';
    printf('<p><a href="%1$s">&larr; %2$s</a></p>', esc_url(admin_url('edit.php?post_type=themeify_contact')), esc_html__('All contacts', 'themeify'));
}

// Unread count badge on the "Contacts" menu.
add_action('admin_menu', function () {
    global $menu;
    $unread = (new WP_Query([
        'post_type'      => 'themeify_contact',
        'post_status'    => 'publish',
        'meta_key'       => '_tf_contact_unread',
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'no_found_rows'  => false,
    ]))->found_posts;
    if (!$unread) {
        return;
    }
    foreach ($menu as $i => $item) {
        if (($item[2] ?? '') === 'edit.php?post_type=themeify_contact') {
            $menu[$i][0] .= sprintf(' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $unread);
            break;
        }
    }
}, 20);

add_action('admin_head', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'themeify_contact') {
        return;
    }
    echo '<style>
        .tf-contact-new{display:inline-block;margin-left:6px;padding:1px 7px;border-radius:9px;background:#235c47;color:#fff;font-size:11px;font-weight:600;vertical-align:middle}
        .tf-contact-body{max-width:720px;padding:12px 14px;border:1px solid #dcdcde;border-radius:6px;background:#fff;white-space:normal;line-height:1.6}
        .post-type-themeify_contact #titlediv,.post-type-themeify_contact .page-title-action{display:none}
        .column-tf_contact_email{width:18%}.column-tf_contact_theme{width:14%}.column-date{width:14%}
    </style>';
});

// Settings submenu.
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=themeify_contact',
        __('Contact settings', 'themeify'),
        __('Settings', 'themeify'),
        'manage_options',
        'themeify-contact-settings',
        'themeify_contact_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('themeify_contact', THEMEIFY_CONTACT_OPTION, [
        'type'              => 'array',
        'sanitize_callback' => 'themeify_contact_sanitize_settings',
        'default'           => themeify_contact_defaults(),
    ]);
});

function themeify_contact_sanitize_settings($input)
{
    $input    = is_array($input) ? $input : [];
    $defaults = themeify_contact_defaults();

    $emails  = array_filter(array_map('trim', explode(',', (string) ($input['email'] ?? ''))));
    $valid   = array_filter($emails, 'is_email');
    $invalid = array_diff($emails, $valid);
    if ($invalid && function_exists('add_settings_error')) { // admin-only function; the option can also be saved elsewhere
        add_settings_error(THEMEIFY_CONTACT_OPTION, 'tf-contact-email', sprintf(
            /* translators: %s: invalid email addresses */
            __('These email addresses are not valid and were removed: %s', 'themeify'),
            implode(', ', array_map('sanitize_text_field', $invalid))
        ));
    }

    return [
        'email'          => $valid ? implode(', ', array_map('sanitize_email', $valid)) : $defaults['email'],
        'notify'         => empty($input['notify']) ? 0 : 1,
        'store'          => empty($input['store']) ? 0 : 1,
        'subject'        => sanitize_text_field($input['subject'] ?? '') ?: $defaults['subject'],
        'theme_field'    => empty($input['theme_field']) ? 0 : 1,
        'theme_required' => empty($input['theme_required']) ? 0 : 1,
        'button_text'    => sanitize_text_field($input['button_text'] ?? '') ?: $defaults['button_text'],
        'success'        => sanitize_textarea_field($input['success'] ?? '') ?: $defaults['success'],
    ];
}

function themeify_contact_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $s    = themeify_contact_settings();
    $name = THEMEIFY_CONTACT_OPTION;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Contact settings', 'themeify'); ?></h1>
        <?php settings_errors($name); ?>
        <form method="post" action="options.php">
            <?php settings_fields('themeify_contact'); ?>

            <h2 class="title"><?php esc_html_e('Email', 'themeify'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tf-contact-email"><?php esc_html_e('Send messages to', 'themeify'); ?></label></th>
                    <td>
                        <input type="text" id="tf-contact-email" class="regular-text" name="<?php echo esc_attr($name); ?>[email]" value="<?php echo esc_attr($s['email']); ?>">
                        <p class="description"><?php esc_html_e('Email address that receives contact messages. Separate several addresses with commas.', 'themeify'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Email notifications', 'themeify'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[notify]" value="1" <?php checked($s['notify']); ?>> <?php esc_html_e('Email every new message to the address above', 'themeify'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tf-contact-subject"><?php esc_html_e('Email subject', 'themeify'); ?></label></th>
                    <td>
                        <input type="text" id="tf-contact-subject" class="regular-text" name="<?php echo esc_attr($name); ?>[subject]" value="<?php echo esc_attr($s['subject']); ?>">
                        <p class="description"><?php esc_html_e('You can use {name}, {email}, {theme} and {site}.', 'themeify'); ?></p>
                    </td>
                </tr>
            </table>

            <h2 class="title"><?php esc_html_e('Messages', 'themeify'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Save messages', 'themeify'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[store]" value="1" <?php checked($s['store']); ?>> <?php esc_html_e('Keep a copy of every message under Contacts', 'themeify'); ?></label></td>
                </tr>
            </table>

            <h2 class="title"><?php esc_html_e('Form', 'themeify'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Theme field', 'themeify'); ?></th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[theme_field]" value="1" <?php checked($s['theme_field']); ?>> <?php esc_html_e('Show "Select a Theme" (lists your published products)', 'themeify'); ?></label><br>
                        <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[theme_required]" value="1" <?php checked($s['theme_required']); ?>> <?php esc_html_e('Make it required', 'themeify'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tf-contact-button"><?php esc_html_e('Button text', 'themeify'); ?></label></th>
                    <td><input type="text" id="tf-contact-button" class="regular-text" name="<?php echo esc_attr($name); ?>[button_text]" value="<?php echo esc_attr($s['button_text']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tf-contact-success"><?php esc_html_e('Success message', 'themeify'); ?></label></th>
                    <td><textarea id="tf-contact-success" class="large-text" rows="3" name="<?php echo esc_attr($name); ?>[success]"><?php echo esc_textarea($s['success']); ?></textarea></td>
                </tr>
            </table>

            <p class="description">
                <?php
                $contact = get_page_by_path('contact');
                printf(
                    /* translators: %s: link to the contact page */
                    esc_html__('The form appears on the page with the slug "contact" (%s) and on any page using the "Contact page" template. Its title and text come from the page itself.', 'themeify'),
                    $contact ? '<a href="' . esc_url(get_permalink($contact)) . '" target="_blank" rel="noopener">' . esc_html__('view', 'themeify') . '</a>' : esc_html__('not created yet', 'themeify')
                );
                ?>
            </p>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/* Front end: options for the theme select and the form submission    */
/* ------------------------------------------------------------------ */

/** [product_id => name] for the "Select a Theme" field. */
function themeify_contact_theme_options()
{
    if (!function_exists('wc_get_products')) {
        return [];
    }
    $out = [];
    foreach (wc_get_products(['status' => 'publish', 'limit' => -1, 'orderby' => 'title', 'order' => 'ASC']) as $product) {
        $out[$product->get_id()] = $product->get_name();
    }
    return $out;
}

/** State of the form on this request: errors + submitted values (set by the handler below). */
function themeify_contact_state($set = null)
{
    static $state = ['errors' => [], 'values' => []];
    if (is_array($set)) {
        $state = $set;
    }
    return $state;
}

// Handle a submission: validate, save, email, then redirect back (or re-show the form with errors).
add_action('template_redirect', function () {
    if (empty($_POST['tf_contact_submit']) || !is_singular('page')) { // phpcs:ignore WordPress.Security.NonceVerification -- checked below
        return;
    }

    $page_id = get_queried_object_id();
    $values  = [
        'name'    => sanitize_text_field(wp_unslash($_POST['tf_name'] ?? '')),
        'email'   => sanitize_email(wp_unslash($_POST['tf_email'] ?? '')),
        'theme'   => absint($_POST['tf_theme'] ?? 0),
        'message' => sanitize_textarea_field(wp_unslash($_POST['tf_message'] ?? '')),
    ];
    $errors = [];

    if (!isset($_POST['tf_contact_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tf_contact_nonce'])), 'tf_contact_' . $page_id)) {
        $errors['form'] = __('Your session expired. Please send the form again.', 'themeify');
    }
    // Honeypot: real visitors never see or fill this field.
    if (!empty($_POST['tf_website'])) {
        wp_safe_redirect(add_query_arg('contact', 'sent', get_permalink($page_id)) . '#tf-contact');
        exit;
    }

    if ($values['name'] === '') {
        $errors['name'] = __('Please enter your name.', 'themeify');
    }
    if (!is_email($values['email'])) {
        $errors['email'] = __('Please enter a valid email address.', 'themeify');
    }
    $themes     = themeify_contact_settings('theme_field') ? themeify_contact_theme_options() : [];
    $theme_name = $themes[$values['theme']] ?? '';
    if ($themes && themeify_contact_settings('theme_required') && $theme_name === '') {
        $errors['theme'] = __('Please choose a theme.', 'themeify');
    }
    if ($values['message'] === '') {
        $errors['message'] = __('Please write a message.', 'themeify');
    }

    if ($errors) {
        themeify_contact_state(['errors' => $errors, 'values' => $values]);
        return; // the page renders again with the errors and the visitor's input
    }

    $data = [
        'name'     => $values['name'],
        'email'    => $values['email'],
        'theme'    => $theme_name,
        'theme_id' => $theme_name !== '' ? $values['theme'] : 0,
        'message'  => $values['message'],
        'page'     => $page_id,
    ];

    $stored = themeify_contact_settings('store') ? themeify_contact_store($data) : 0;

    $sent = true;
    if (themeify_contact_settings('notify')) {
        $subject = strtr(themeify_contact_settings('subject'), [
            '{name}'  => $data['name'],
            '{email}' => $data['email'],
            '{theme}' => $data['theme'] ?: '—',
            '{site}'  => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
        ]);
        $body = implode("\n", [
            sprintf(__('Name: %s', 'themeify'), $data['name']),
            sprintf(__('Email: %s', 'themeify'), $data['email']),
            sprintf(__('Theme: %s', 'themeify'), $data['theme'] ?: '—'),
            '',
            $data['message'],
            '',
            '—',
            sprintf(__('Sent from %s', 'themeify'), get_permalink($page_id)),
        ]);
        if ($stored) {
            $body .= "\n" . sprintf(__('View in admin: %s', 'themeify'), admin_url('post.php?post=' . $stored . '&action=edit'));
        }
        $headers = ['Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>'];
        $sent    = wp_mail(themeify_contact_recipients(), $subject, $body, $headers);
    }

    if (!$sent && !$stored) {
        themeify_contact_state([
            'errors' => ['form' => __('Sorry, your message could not be sent right now. Please try again later.', 'themeify')],
            'values' => $values,
        ]);
        return;
    }

    wp_safe_redirect(add_query_arg('contact', 'sent', get_permalink($page_id)) . '#tf-contact');
    exit;
}, 1); // before other template_redirect handlers (canonical redirects, store visibility, …)
