<?php
get_header();

$slider_count = (int) get_theme_mod('themeify_slider_count', 6);
$featured     = themeify_featured_products($slider_count);
$front_id     = (int) get_option('page_on_front');
$slider_title = get_theme_mod('themeify_slider_heading', 'Featured products');

// Home hero from the front page's ACF fields, always at the top.
if ($front_id) {
    themeify_render_section_home_hero(themeify_home_hero_acf($front_id));
    themeify_render_section_featured_carousel(themeify_featured_carousel_acf($front_id));
    themeify_render_section_image_rows(themeify_image_rows_acf($front_id));
    themeify_render_section_featured_cards(themeify_featured_cards_acf($front_id));
    themeify_render_global_sections(); // testimonials + FAQ from Theme Settings
}
?>

<?php if ($featured) : ?>
<section class="tf-section tf-slider-section">
    <div class="tf-container">
        <h2 class="tf-section-heading"><?php echo esc_html($slider_title); ?></h2>

        <?php if ($featured) : ?>
            <div class="tf-slider" data-autoplay="6000">
                <div class="tf-slider__viewport">
                    <div class="tf-slider__track">
                        <?php $total = count($featured);
                        foreach ($featured as $i => $product) :
                            $desc = $product->get_short_description() ?: $product->get_description();
                            ?>
                            <div class="tf-slide" role="group" aria-roledescription="slide"
                                 aria-label="<?php echo esc_attr(sprintf('%1$d of %2$d', $i + 1, $total)); ?>">
                                <a class="tf-slide__media" href="<?php echo esc_url($product->get_permalink()); ?>">
                                    <?php echo $product->get_image('large'); ?>
                                </a>
                                <div class="tf-slide__body">
                                    <span class="tf-badge"><?php esc_html_e('Featured', 'themeify'); ?></span>
                                    <h3><a href="<?php echo esc_url($product->get_permalink()); ?>"><?php echo esc_html($product->get_name()); ?></a></h3>
                                    <div class="tf-price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
                                    <p><?php echo esc_html(wp_trim_words(wp_strip_all_tags($desc), 26)); ?></p>
                                    <div class="tf-slide__actions">
                                        <a class="tf-btn" href="<?php echo esc_url($product->get_permalink()); ?>"><?php esc_html_e('View details', 'themeify'); ?></a>
                                        <?php if ($product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock()) : ?>
                                            <a class="tf-btn tf-btn--ghost" href="<?php echo esc_url($product->add_to_cart_url()); ?>"><?php esc_html_e('Add to cart', 'themeify'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="button" class="tf-slider__btn tf-slider__btn--prev" aria-label="<?php esc_attr_e('Previous slide', 'themeify'); ?>">&#8249;</button>
                <button type="button" class="tf-slider__btn tf-slider__btn--next" aria-label="<?php esc_attr_e('Next slide', 'themeify'); ?>">&#8250;</button>
                <div class="tf-slider__dots"></div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($front_id) {
    themeify_render_sections($front_id, ['home_hero']);
} ?>

<?php get_footer(); ?>
