<?php
/**
 * Single product detail: title, price, short description, theme options (variation pills) and a
 * "Buy Now" button on the left; the main image and a row of thumbnails on the right.
 * The gallery is a Swiper slider with Swiper thumbnails. Choosing a pill selects the matching
 * variation (price, Buy Now link, slides to its image); sliding to a variation's image selects its pill.
 * Buy Now is a plain link to the product's / variation's "Buy Now link", labelled with the product's
 * "Buy Now button text" (both in Product data → General; cart and checkout are off).
 * See assets/js/product-detail.js and themeify_product_detail_data().
 *
 * @var array $args ['product' => WC_Product]
 */
if (!defined('ABSPATH')) {
    exit;
}

$product = $args['product'] ?? null;
if (!$product instanceof WC_Product) {
    return;
}

$d        = themeify_product_detail_data($product);
$images   = $d['images'];
$active   = $d['active'];
$variable = $product->is_type('variable') && $d['options'];
$initial  = $d['initial'];
$price    = $variable && $initial ? $initial['price_html'] : $product->get_price_html();
$excerpt  = apply_filters('woocommerce_short_description', $product->get_short_description());
if (trim(wp_strip_all_tags((string) $excerpt)) === '') {
    // No short description: show the start of the main description instead.
    $long    = trim(wp_strip_all_tags(strip_shortcodes((string) $product->get_description())));
    $excerpt = $long !== '' ? '<p>' . esc_html(wp_trim_words($long, 40)) . '</p>' : '';
}
$buy_text = themeify_buy_now_text($product);

if ($variable) {
    $buy_link = $initial['link'] ?? '';
    $can_buy  = $initial && $initial['available'];
} else {
    $buy_link = $product->is_type('external') ? esc_url_raw($product->get_product_url()) : themeify_buy_now_link($product);
    $can_buy  = $buy_link !== '';
}
?>
<section class="tf-pd" aria-labelledby="tf-pd-title">
    <div class="tf-fp__container">
        <?php if (function_exists('woocommerce_output_all_notices')) {
            woocommerce_output_all_notices();
        } ?>

        <div class="tf-pd__grid" data-tf-pd>
            <div class="tf-pd__summary">
                <div class="tf-pd__summary-inner"><!-- sticky + vertically centred on screen -->
                    <h1 class="tf-pd__title" id="tf-pd-title"><?php echo esc_html($product->get_name()); ?></h1>

                    <?php if ($price || $variable) : // variable: always present so the script can fill it per variation ?>
                        <div class="tf-pd__price" data-tf-pd-price aria-live="polite"<?php echo $price ? '' : ' hidden'; ?>><?php echo wp_kses_post($price); ?></div>
                    <?php endif; ?>

                    <?php if (trim(wp_strip_all_tags((string) $excerpt)) !== '') : ?>
                        <div class="tf-pd__desc"><?php echo wp_kses_post($excerpt); ?></div>
                    <?php endif; ?>

                    <div class="tf-pd__form">
                        <?php foreach ($d['options'] as $group) : ?>
                            <fieldset class="tf-pd__options">
                                <legend class="screen-reader-text"><?php echo esc_html($group['label']); ?></legend>
                                <?php foreach ($group['options'] as $opt) : ?>
                                    <label class="tf-pd__pill">
                                        <input class="tf-pd__radio" type="radio"
                                               name="<?php echo esc_attr($group['key']); ?>"
                                               value="<?php echo esc_attr($opt['value']); ?>"
                                               <?php checked(($d['selected'][$group['key']] ?? '') === $opt['value']); ?>>
                                        <span><?php echo esc_html($opt['label']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>

                        <?php if ($variable) : ?>
                            <script type="application/json" data-tf-pd-variations><?php
                                echo wp_json_encode(array_map(function ($v) {
                                    return [
                                        'id'         => $v['id'],
                                        'attributes' => $v['attributes'],
                                        'image_id'   => $v['image_id'],
                                        'price_html' => $v['price_html'],
                                        'available'  => $v['available'],
                                        'link'       => $v['link'],
                                    ];
                                }, $d['variations']), JSON_HEX_TAG | JSON_HEX_AMP);
                            ?></script>
                        <?php endif; ?>

                        <a class="tf-pd__buy<?php echo $can_buy ? '' : ' is-disabled'; ?>" data-tf-pd-buy
                           <?php if ($can_buy) : ?>href="<?php echo esc_url($buy_link); ?>"<?php else : ?>aria-disabled="true"<?php endif; ?>
                           data-label-buy="<?php echo esc_attr($buy_text); ?>"
                           data-label-na="<?php esc_attr_e('Unavailable', 'themeify'); ?>"
                           <?php echo $product->is_type('external') ? 'rel="nofollow"' : ''; ?>>
                            <span class="tf-pd__buy-dot" aria-hidden="true"></span>
                            <span data-tf-pd-buy-label><?php echo $can_buy ? esc_html($buy_text) : esc_html__('Unavailable', 'themeify'); ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="tf-pd__main swiper" data-tf-pd-main aria-label="<?php esc_attr_e('Product images', 'themeify'); ?>">
                <div class="swiper-wrapper">
                    <?php if ($images) : ?>
                        <?php foreach ($images as $i => $image) : ?>
                            <div class="swiper-slide tf-pd__slide" data-image-id="<?php echo esc_attr($image['id']); ?>"
                                 role="group" aria-label="<?php echo esc_attr(sprintf(__('%1$d of %2$d', 'themeify'), $i + 1, count($images))); ?>">
                                <img class="tf-pd__main-img"
                                     src="<?php echo esc_url($image['src']); ?>"
                                     <?php if ($image['srcset']) : ?>srcset="<?php echo esc_attr($image['srcset']); ?>"<?php endif; ?>
                                     sizes="(min-width: 901px) 810px, 100vw"
                                     alt="<?php echo esc_attr($image['alt']); ?>"
                                     <?php echo $i === $active ? 'fetchpriority="high"' : 'loading="lazy"'; ?>>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="swiper-slide tf-pd__slide"><?php echo wc_placeholder_img('full', ['class' => 'tf-pd__main-img']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($images) > 1) : ?>
                <div class="tf-pd__thumbs swiper" data-tf-pd-thumbs data-active="<?php echo esc_attr($active); ?>">
                    <div class="swiper-wrapper">
                        <?php foreach ($images as $i => $image) : ?>
                            <button type="button" class="swiper-slide tf-pd__thumb<?php echo $i === $active ? ' is-active' : ''; ?>"
                                    data-image-id="<?php echo esc_attr($image['id']); ?>"
                                    aria-label="<?php echo esc_attr(sprintf(__('Show image %d', 'themeify'), $i + 1)); ?>"
                                    aria-pressed="<?php echo $i === $active ? 'true' : 'false'; ?>">
                                <?php echo $image['thumb']; // wp_get_attachment_image output ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
// Product structured data (normally added by the default summary hooks this page no longer runs).
if (function_exists('WC') && isset(WC()->structured_data)) {
    WC()->structured_data->generate_product_data($product);
}
