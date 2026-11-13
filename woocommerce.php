<?php
get_header();

$themeify_is_archive = (function_exists('is_shop') && is_shop())
    || (function_exists('is_product_taxonomy') && is_product_taxonomy())
    || (is_search() && is_post_type_archive('product'));

if ($themeify_is_archive) :
    // Shop / product archives: ring header, then the products directly (no breadcrumb, sorting, count or category tiles).
    $themeify_head = themeify_shop_header();
    ?>
    <div class="tf-shop">
        <?php get_template_part('template-parts/page-header', null, $themeify_head + ['id' => 'tf-shop-heading']); ?>

        <section class="tf-shop__products" aria-labelledby="tf-shop-heading">
            <div class="tf-fp__container">
                <?php if (function_exists('woocommerce_output_all_notices')) {
                    woocommerce_output_all_notices();
                } ?>

                <?php if (have_posts()) : ?>
                    <ul class="tf-shop__grid">
                        <?php while (have_posts()) :
                            the_post();
                            get_template_part('template-parts/product-tile', null, [
                                'product' => wc_get_product(get_the_ID()),
                                'tag'     => 'li',
                            ]);
                        endwhile; ?>
                    </ul>

                    <?php the_posts_pagination([
                        'class'     => 'tf-shop__pager',
                        'mid_size'  => 1,
                        'prev_text' => __('Previous', 'themeify'),
                        'next_text' => __('Next', 'themeify'),
                    ]); ?>
                <?php else : ?>
                    <p class="tf-shop__empty"><?php esc_html_e('No products found.', 'themeify'); ?></p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php themeify_render_global_sections(); // testimonials + FAQ from Theme Settings ?>
<?php elseif (function_exists('is_product') && is_product()) :
    // Single product: detail block (gallery + theme options + Buy Now), then testimonials and FAQ.
    // The default WooCommerce tabs, related products and builder sections are not shown.
    while (have_posts()) :
        the_post();
        $themeify_product = wc_get_product(get_the_ID());
        if ($themeify_product) {
            get_template_part('template-parts/product-detail', null, ['product' => $themeify_product]);
            themeify_render_global_sections(); // testimonials + FAQ from Theme Settings
        }
    endwhile;
    ?>
<?php else : ?>
    <div class="tf-container tf-woo">
        <?php woocommerce_content(); ?>
    </div>
<?php endif;

get_footer();
