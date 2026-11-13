<?php
/**
 * Home page "featured products" carousel (Swiper): heading, intro text and product cards
 * with screenshot, name | price, short description and an "Explore Now" button.
 * Data comes from the front page's ACF fields, see themeify_featured_carousel_acf().
 *
 * @var array $args ['section' => ['heading' => string, 'text' => html, 'products' => WC_Product[]]]
 */
if (!defined('ABSPATH')) {
    exit;
}

$s        = $args['section'] ?? [];
$heading  = trim((string) ($s['heading'] ?? ''));
$text     = trim((string) ($s['text'] ?? ''));
$products = $s['products'] ?? [];

if (!$products) {
    return;
}

$arrow = '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">'
    . '<path d="M2.5 8h11M9 3.5 13.5 8 9 12.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<section class="tf-fp" aria-labelledby="tf-fp-heading">
    <div class="tf-fp__container">
        <?php if ($heading || $text) : ?>
            <header class="tf-fp__head">
                <?php if ($heading) : ?>
                    <h2 class="tf-fp__title" id="tf-fp-heading"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($text) : ?>
                    <div class="tf-fp__text"><?php echo wp_kses_post($text); ?></div>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <div class="tf-fp__carousel swiper" data-tf-fp>
            <div class="swiper-wrapper">
                <?php foreach ($products as $product) {
                    get_template_part('template-parts/product-tile', null, ['product' => $product, 'class' => 'swiper-slide']);
                } ?>
            </div>
        </div>

        <?php if (count($products) > 1) : ?>
            <div class="tf-fp__nav">
                <button type="button" class="tf-fp__arrow tf-fp__arrow--prev" aria-label="<?php esc_attr_e('Previous products', 'themeify'); ?>"><?php echo $arrow; // static markup ?></button>
                <button type="button" class="tf-fp__arrow tf-fp__arrow--next" aria-label="<?php esc_attr_e('Next products', 'themeify'); ?>"><?php echo $arrow; // static markup ?></button>
            </div>
        <?php endif; ?>
    </div>
</section>
