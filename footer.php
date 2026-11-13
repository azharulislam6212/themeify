</main>

<?php
$tf_pages_title  = themeify_footer_text('pages_title');
$tf_themes_title = themeify_footer_text('themes_title');
$tf_email_label  = themeify_footer_text('email_label');
$tf_email        = sanitize_email(themeify_footer_text('email'));
$tf_socials      = themeify_footer_socials();
?>
<footer class="tf-footer">
    <div class="tf-footer__wrap">
        <div class="tf-footer__top">
            <nav class="tf-footer__cols" aria-label="<?php esc_attr_e('Footer', 'themeify'); ?>">
                <div class="tf-footer__col">
                    <?php if ($tf_pages_title) : ?>
                        <h2 class="tf-footer__title"><?php echo esc_html($tf_pages_title); ?></h2>
                    <?php endif; ?>
                    <?php themeify_footer_menu('footer', 'themeify_footer_pages_fallback'); ?>
                </div>
                <div class="tf-footer__col">
                    <?php if ($tf_themes_title) : ?>
                        <h2 class="tf-footer__title"><?php echo esc_html($tf_themes_title); ?></h2>
                    <?php endif; ?>
                    <?php themeify_footer_menu('footer_themes', 'themeify_footer_themes_fallback'); ?>
                </div>
            </nav>

            <a class="tf-footer__mark" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
                <?php echo themeify_footer_wordmark(); // static SVG from the theme ?>
            </a>

            <?php if ($tf_email) : ?>
                <div class="tf-footer__contact">
                    <?php if ($tf_email_label) : ?>
                        <p class="tf-footer__contact-label"><?php echo esc_html($tf_email_label); ?></p>
                    <?php endif; ?>
                    <a class="tf-footer__email" href="<?php echo esc_url('mailto:' . $tf_email); ?>"><?php echo esc_html(antispambot($tf_email)); ?></a>
                </div>
            <?php endif; ?>
        </div>

        <div class="tf-footer__bottom">
            <?php if ($tf_socials) : ?>
                <ul class="tf-footer__social">
                    <?php foreach ($tf_socials as $tf_social) : ?>
                        <li>
                            <a href="<?php echo esc_url($tf_social['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($tf_social['label']); ?>">
                                <?php echo $tf_social['icon']; // static SVG ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p class="tf-footer__copy">
                <?php
                /* translators: %s: current year */
                echo esc_html(sprintf(__('Copyright © %s', 'themeify'), date_i18n('Y')));
                ?>
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
            </p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
