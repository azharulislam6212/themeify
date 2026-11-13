<?php
$product = $args['product'] ?? null;
if (!$product) {
    return;
}
?>
<article class="tf-card tf-product-card">
    <a class="tf-product-card__img" href="<?php echo esc_url($product->get_permalink()); ?>">
        <?php echo $product->get_image('woocommerce_thumbnail'); ?>
    </a>
    <h3><a href="<?php echo esc_url($product->get_permalink()); ?>"><?php echo esc_html($product->get_name()); ?></a></h3>
    <div class="tf-price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
    <?php if ($product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock()) : ?>
        <a class="tf-btn tf-btn--ghost" href="<?php echo esc_url($product->add_to_cart_url()); ?>"><?php esc_html_e('Add to cart', 'themeify'); ?></a>
    <?php else : ?>
        <a class="tf-btn tf-btn--ghost" href="<?php echo esc_url($product->get_permalink()); ?>"><?php esc_html_e('View details', 'themeify'); ?></a>
    <?php endif; ?>
</article>
