<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="tf-skip" href="#content"><?php esc_html_e('Skip to content', 'themeify'); ?></a>

<header class="tf-header">
    <div class="tf-header__bar">
        <div class="tf-brand">
            <?php if (has_custom_logo()) {
                the_custom_logo();
            } else { ?>
                <a class="tf-brand__link" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <?php echo themeify_wordmark(); ?>
                </a>
            <?php } ?>
        </div>

        <span class="tf-header__divider" aria-hidden="true"></span>

        <button type="button" class="tf-nav-toggle" aria-expanded="false" aria-controls="tf-nav">
            <span class="tf-nav-toggle__bars" aria-hidden="true"></span>
            <span class="tf-nav-toggle__label"><?php esc_html_e('Menu', 'themeify'); ?></span>
        </button>

        <nav id="tf-nav" class="tf-nav" aria-label="<?php esc_attr_e('Primary', 'themeify'); ?>">
            <?php wp_nav_menu([
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'tf-menu',
                'depth'          => 2,
                'fallback_cb'    => 'themeify_fallback_menu',
            ]); ?>
        </nav>
    </div>
</header>

<main id="content" class="tf-main">
