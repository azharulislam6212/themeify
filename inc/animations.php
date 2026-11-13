<?php
/**
 * Entrance animations for the whole site (header, every section, footer), powered by Motion
 * (https://motion.dev), see assets/js/animations.js.
 *
 * Headings, text, buttons, images and cards fade/slide in when the page loads and when they
 * are scrolled into view. The selectors live here only: they are printed as a small pre-hide
 * style in <head> (so nothing flashes before the script runs) and passed to the script.
 *
 * Off for visitors who ask for reduced motion. Turn off for everyone with:
 *     add_filter('themeify_animations_enabled', '__return_false');
 * Exclude any element (and its children) by adding the attribute data-tf-skip.
 */
if (!defined('ABSPATH')) {
    exit;
}

define('THEMEIFY_MOTION_VERSION', '14.12.0');

/**
 * Animation config. Groups are matched in order: an element is animated by the first group that
 * matches it, and is skipped when an ancestor or a descendant is already animated (a card moves
 * as one block, the hero heading animates line by line).
 */
function themeify_anim_config()
{
    $config = [
        // Areas never animated: interactive widgets, sliding tracks, forms, notices.
        'skip'   => [
            '[data-tf-skip]',
            '#wpadminbar',
            '.tf-docs__sidebar',
            '.tf-docs__toc',
            '.tf-toc',
            '.tf-docnav',
            '.tf-ask',
            '.tf-home-hero__cards',
            '.swiper-wrapper',
            '.tf-slider__track',
            'form',
            '.woocommerce-notices-wrapper',
            '.woocommerce-message',
            '.woocommerce-error',
            '.woocommerce-info',
        ],
        'groups' => [
            ['header', '.tf-brand, .tf-menu > li, .tf-nav-toggle'],
            ['line', '.tf-home-hero__line'],
            ['draw', '.tf-footer__mark-svg'],
            ['tile', '.tf-fc__card'],
            ['reviews', '.tf-tm__carousel'],
            ['faq', '.tf-qa__item'],
            ['rule', '.tf-qa__rule'],
            ['card', implode(', ', [
                '.tf-features > *',
                '.tf-grid > *',
                '.tf-doc-cards > *',
                '.tf-theme-cards > *',
                '.tf-results > *',
                '.tf-feature',
                '.tf-faq',
                '.tf-card',
                '.tf-product-card',
                '.tf-theme-card',
                '.tf-doc-card',
                '.tf-callout',
                '.woocommerce ul.products > li',
                '.tf-shop__grid > *',
            ])],
            ['media', implode(', ', [
                '.tf-home-hero__showcase',
                '.tf-fp__carousel',
                '.tf-slider',
                '.tf-ir__panel',
                '.tf-split__media',
                '.tf-video',
                '.woocommerce-product-gallery',
                '.tf-main figure',
                '.tf-main img',
                '.tf-main video',
                '.tf-main iframe',
            ])],
            ['button', implode(', ', [
                '.tf-home-hero__btn',
                '.tf-qa__btn',
                '.tf-ir__btn',
                '.tf-fp__nav',
                '.tf-main .tf-btn',
                '.tf-main .button',
                '.tf-main .wp-block-button',
                '.tf-main .tf-badge',
                '.tf-main .tf-chip',
                '.tf-footer__email',
                '.tf-footer__social li',
            ])],
            ['heading', implode(', ', [
                '.tf-main h1',
                '.tf-main h2',
                '.tf-main h3',
                '.tf-main h4',
                '.tf-main h5',
                '.tf-main h6',
                '.tf-footer__title',
            ])],
            ['text', implode(', ', [
                '.tf-fp__text',
                '.tf-ir__text',
                '.tf-qa__sub-text',
                '.tf-main p',
                '.tf-prose li',
                '.tf-doc-body li',
                '.tf-main blockquote',
                '.tf-main pre',
                '.tf-main table',
                '.tf-main .tf-price',
                '.tf-main .tf-meta',
                '.tf-main .tf-crumb',
                '.tf-footer__links li',
                '.tf-footer__contact-label',
                '.tf-footer__copy',
            ])],
        ],
    ];

    return apply_filters('themeify_anim_config', $config);
}

function themeify_animations_enabled()
{
    return !is_admin() && !is_customize_preview() && apply_filters('themeify_animations_enabled', true);
}

/*
 * Pre-hide: before the script runs, every element the script will animate is hidden, so the page
 * does not flash. The class is added only when the visitor allows motion, and is removed after a
 * few seconds if the script never starts (CDN blocked, JS error), so content is never lost.
 */
add_action('wp_head', function () {
    if (!themeify_animations_enabled()) {
        return;
    }

    $config = themeify_anim_config();
    $skip   = [];
    foreach ($config['skip'] as $zone) {
        $skip[] = $zone;
        $skip[] = $zone . ' *';
    }
    $not = ':not(' . implode(', ', $skip) . ')';

    $hide = [];
    foreach ($config['groups'] as [, $selectors]) {
        foreach (array_map('trim', explode(',', $selectors)) as $sel) {
            $hide[] = 'html.tf-anim ' . $sel . $not;
        }
    }
    ?>
    <script id="themeify-anim-init">
        (function (d, w) {
            if (!w.matchMedia || w.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            var h = d.documentElement;
            h.classList.add('tf-anim');
            setTimeout(function () { if (!w.tfAnimReady) h.classList.remove('tf-anim'); }, 3000);
        })(document, window);
    </script>
    <style id="themeify-anim-css">
        <?php echo implode(",\n", $hide); // selectors from the theme's own config ?> { opacity: 0 !important; }
        html.tf-anim-on [data-tf]:not(.tf-in) { opacity: 0 !important; }
    </style>
    <?php
}, 1);

add_action('wp_enqueue_scripts', function () {
    if (!themeify_animations_enabled()) {
        return;
    }

    // Motion ships with the theme (assets/js/vendor/motion.min.js, the UMD build of the version
    // above) so the page never waits on, or breaks with, a blocked or slow CDN.
    wp_enqueue_script(
        'motion',
        THEMEIFY_URI . '/assets/js/vendor/motion.min.js',
        [],
        THEMEIFY_MOTION_VERSION,
        true
    );
    wp_enqueue_script('themeify-animations', THEMEIFY_URI . '/assets/js/animations.js', ['motion'], THEMEIFY_VERSION, true);
    wp_add_inline_script('themeify-animations', 'window.themeifyAnim = ' . wp_json_encode(themeify_anim_config()) . ';', 'before');
});
