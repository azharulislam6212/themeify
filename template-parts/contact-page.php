<?php
/**
 * Contact page: the page title, the page content as intro text, and the contact form.
 * Submissions are handled in inc/contact.php; settings live under Contacts → Settings.
 */
if (!defined('ABSPATH')) {
    exit;
}

$page_id  = get_the_ID();
$state    = themeify_contact_state();
$errors   = $state['errors'];
$values   = $state['values'] + ['name' => '', 'email' => '', 'theme' => 0, 'message' => ''];
$sent     = isset($_GET['contact']) && $_GET['contact'] === 'sent' && !$errors; // phpcs:ignore WordPress.Security.NonceVerification
$themes   = themeify_contact_settings('theme_field') ? themeify_contact_theme_options() : [];
$required = (bool) themeify_contact_settings('theme_required');
$content  = trim((string) get_the_content());

$field_error = function ($key) use ($errors) {
    return isset($errors[$key])
        ? '<p class="tf-cf__error" id="tf-cf-' . esc_attr($key) . '-error">' . esc_html($errors[$key]) . '</p>'
        : '';
};
$invalid = function ($key) use ($errors) {
    return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="tf-cf-' . esc_attr($key) . '-error"' : '';
};
?>
<section class="tf-cf" id="tf-contact" aria-labelledby="tf-cf-title">
    <div class="tf-cf__container">
        <header class="tf-cf__head">
            <h1 class="tf-cf__title" id="tf-cf-title"><?php the_title(); ?></h1>
            <?php if ($content !== '') : ?>
                <div class="tf-cf__text"><?php the_content(); ?></div>
            <?php endif; ?>
        </header>

        <?php if ($sent) : ?>
            <div class="tf-cf__notice tf-cf__notice--ok" role="status">
                <?php echo esc_html(themeify_contact_settings('success')); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['form'])) : ?>
            <div class="tf-cf__notice tf-cf__notice--error" role="alert"><?php echo esc_html($errors['form']); ?></div>
        <?php elseif ($errors) : ?>
            <div class="tf-cf__notice tf-cf__notice--error" role="alert"><?php esc_html_e('Please check the highlighted fields.', 'themeify'); ?></div>
        <?php endif; ?>

        <form class="tf-cf__form" action="<?php echo esc_url(get_permalink($page_id)); ?>#tf-contact" method="post" novalidate>
            <?php wp_nonce_field('tf_contact_' . $page_id, 'tf_contact_nonce'); ?>
            <input type="hidden" name="tf_contact_submit" value="1">
            <div class="tf-cf__hp" aria-hidden="true">
                <label for="tf-cf-website"><?php esc_html_e('Leave this field empty', 'themeify'); ?></label>
                <input type="text" id="tf-cf-website" name="tf_website" value="" tabindex="-1" autocomplete="off">
            </div>

            <div class="tf-cf__row">
                <div class="tf-cf__field<?php echo isset($errors['name']) ? ' has-error' : ''; ?>">
                    <label class="tf-cf__label" for="tf-cf-name"><?php esc_html_e('Name', 'themeify'); ?> <span aria-hidden="true">*</span></label>
                    <input class="tf-cf__input" type="text" id="tf-cf-name" name="tf_name" required autocomplete="name"
                           placeholder="<?php esc_attr_e('Jordan Walk', 'themeify'); ?>" value="<?php echo esc_attr($values['name']); ?>"<?php echo $invalid('name'); ?>>
                    <?php echo $field_error('name'); ?>
                </div>
                <div class="tf-cf__field<?php echo isset($errors['email']) ? ' has-error' : ''; ?>">
                    <label class="tf-cf__label" for="tf-cf-email"><?php esc_html_e('Email', 'themeify'); ?> <span aria-hidden="true">*</span></label>
                    <input class="tf-cf__input" type="email" id="tf-cf-email" name="tf_email" required autocomplete="email"
                           placeholder="<?php esc_attr_e('jhon@email.com', 'themeify'); ?>" value="<?php echo esc_attr($values['email']); ?>"<?php echo $invalid('email'); ?>>
                    <?php echo $field_error('email'); ?>
                </div>
            </div>

            <?php if ($themes) : ?>
                <div class="tf-cf__field<?php echo isset($errors['theme']) ? ' has-error' : ''; ?>">
                    <label class="tf-cf__label" for="tf-cf-theme">
                        <?php esc_html_e('Select a Theme', 'themeify'); ?><?php if ($required) : ?> <span aria-hidden="true">*</span><?php endif; ?>
                    </label>
                    <div class="tf-cf__select">
                        <select class="tf-cf__input" id="tf-cf-theme" name="tf_theme"<?php echo $required ? ' required' : ''; ?><?php echo $invalid('theme'); ?>>
                            <option value=""><?php esc_html_e('Choose a theme', 'themeify'); ?></option>
                            <?php foreach ($themes as $id => $name) : ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected((int) $values['theme'], (int) $id); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="tf-cf__chevron" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <?php echo $field_error('theme'); ?>
                </div>
            <?php endif; ?>

            <div class="tf-cf__field<?php echo isset($errors['message']) ? ' has-error' : ''; ?>">
                <label class="tf-cf__label" for="tf-cf-message"><?php esc_html_e('Message', 'themeify'); ?> <span aria-hidden="true">*</span></label>
                <textarea class="tf-cf__input tf-cf__textarea" id="tf-cf-message" name="tf_message" rows="6" required
                          placeholder="<?php esc_attr_e('Write a message', 'themeify'); ?>"<?php echo $invalid('message'); ?>><?php echo esc_textarea($values['message']); ?></textarea>
                <?php echo $field_error('message'); ?>
            </div>

            <button type="submit" class="tf-cf__btn">
                <span class="tf-cf__btn-dot" aria-hidden="true"></span>
                <?php echo esc_html(themeify_contact_settings('button_text')); ?>
            </button>
        </form>
    </div>
</section>
