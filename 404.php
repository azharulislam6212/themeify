<?php get_header(); ?>

<div class="tf-container tf-narrow tf-section tf-404">
    <h1><?php esc_html_e('Page not found', 'themeify'); ?></h1>
    <p><?php esc_html_e('The page you are looking for may have moved. Try the menu, or start from the home page.', 'themeify'); ?></p>
    <a class="tf-btn" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Back to home', 'themeify'); ?></a>
</div>

<?php get_footer(); ?>
