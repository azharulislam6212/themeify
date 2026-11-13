<?php
if (!defined('ABSPATH')) {
    exit;
}

define('THEMEIFY_VERSION', '1.29.0');
define('THEMEIFY_DIR', get_template_directory());
define('THEMEIFY_URI', get_template_directory_uri());

require_once THEMEIFY_DIR . '/inc/setup.php';
require_once THEMEIFY_DIR . '/inc/post-types.php';
require_once THEMEIFY_DIR . '/inc/doc-themes.php';
require_once THEMEIFY_DIR . '/inc/doc-permalinks.php';
require_once THEMEIFY_DIR . '/inc/docs-content.php';
require_once THEMEIFY_DIR . '/inc/theme-pages.php';
require_once THEMEIFY_DIR . '/inc/blocks.php';
require_once THEMEIFY_DIR . '/inc/section-config.php';
require_once THEMEIFY_DIR . '/inc/section-render.php';
require_once THEMEIFY_DIR . '/inc/section-builder.php';
require_once THEMEIFY_DIR . '/inc/image-rows.php';
require_once THEMEIFY_DIR . '/inc/featured-cards.php';
require_once THEMEIFY_DIR . '/inc/testimonials.php';
require_once THEMEIFY_DIR . '/inc/faq.php';
require_once THEMEIFY_DIR . '/inc/woocommerce.php';
require_once THEMEIFY_DIR . '/inc/contact.php';
require_once THEMEIFY_DIR . '/inc/customizer.php';
require_once THEMEIFY_DIR . '/inc/footer.php';
require_once THEMEIFY_DIR . '/inc/docs-ai.php';
require_once THEMEIFY_DIR . '/inc/animations.php';
