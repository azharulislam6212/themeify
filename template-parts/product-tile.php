<?php
/**
 * Product card shared by the home carousel and the shop grid: screenshot, name | price,
 * short description and an "Explore Now" button.
 *
 * @var array $args ['product' => WC_Product, 'class' => string extra classes, 'tag' => wrapper tag]
 */
if (!defined('ABSPATH')) {
    exit;
}

$product = $args['product'] ?? null;
if (!$product instanceof WC_Product) {
    return;
}

$tag   = $args['tag'] ?? 'article';
$tag   = in_array($tag, ['article', 'li', 'div'], true) ? $tag : 'article';
$class = trim('tf-fp__card ' . ($args['class'] ?? ''));
$url   = $product->get_permalink();
$desc  = $product->get_short_description() ?: $product->get_description();

// The card frame is tall (395:456), so use the first tall image (featured, then gallery):
// a full-page screenshot fits it, a wide hero shot would be cropped. Falls back to the featured image.
$image_id = (int) $product->get_image_id();
foreach (array_merge([$image_id], $product->get_gallery_image_ids()) as $candidate) {
    $meta = wp_get_attachment_metadata($candidate);
    if (!empty($meta['width']) && !empty($meta['height']) && $meta['height'] / $meta['width'] >= .9) {
        $image_id = (int) $candidate;
        break;
    }
}
$image = $image_id
    ? wp_get_attachment_image($image_id, 'large', false, ['class' => 'tf-fp__img', 'loading' => 'lazy', 'alt' => $product->get_name()])
    : $product->get_image('large', ['class' => 'tf-fp__img', 'loading' => 'lazy']);
?>
<<?php echo $tag; ?> class="<?php echo esc_attr($class); ?>">
    <a class="tf-fp__media" href="<?php echo esc_url($url); ?>" tabindex="-1" aria-hidden="true">
        <?php echo $image; // escaped by wp_get_attachment_image / get_image ?>
    </a>
    <div class="tf-fp__body">
        <div class="tf-fp__meta">
            <h3 class="tf-fp__name"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($product->get_name()); ?></a></h3>
            <?php if ($product->get_price_html()) : ?>
                <span class="tf-fp__sep" aria-hidden="true"></span>
                <span class="tf-fp__price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
            <?php endif; ?>
        </div>
        <?php if ($desc) : ?>
            <p class="tf-fp__desc"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($desc), 22)); ?></p>
        <?php endif; ?>
        <a class="tf-fp__btn" href="<?php echo esc_url($url); ?>">
            <span class="tf-fp__btn-dot" aria-hidden="true"></span>
            <?php esc_html_e('Explore Now', 'themeify'); ?>
            <span class="screen-reader-text"><?php echo esc_html(': ' . $product->get_name()); ?></span>
        </a>
    </div>
</<?php echo $tag; ?>>
