<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Featured products for the home slider. Mark products as Featured in WooCommerce.
 */
function themeify_featured_products($limit = 6)
{
    if (!function_exists('wc_get_products')) {
        return [];
    }
    return wc_get_products([
        'status'   => 'publish',
        'featured' => true,
        'limit'    => max(1, (int) $limit),
        'orderby'  => 'date',
        'order'    => 'DESC',
    ]);
}

function themeify_latest_products($limit = 8)
{
    if (!function_exists('wc_get_products')) {
        return [];
    }
    return wc_get_products([
        'status'  => 'publish',
        'limit'   => max(1, (int) $limit),
        'orderby' => 'date',
        'order'   => 'DESC',
    ]);
}

function themeify_shop_url()
{
    return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
}

/**
 * Heading + intro text for the shop / product archive header.
 * Shop page: ACF "Page Header" group on the Shop page (section_heading, section_text).
 * Category / tag archives: the same field names on the term if present, else the term name and description.
 *
 * @return array{heading: string, text: string}
 */
function themeify_shop_header()
{
    $field = function ($name, $object) {
        return function_exists('get_field') ? get_field($name, $object) : null;
    };

    $heading = '';
    $text    = '';

    if (is_search()) {
        /* translators: %s: search query */
        $heading = sprintf(__('Search results: “%s”', 'themeify'), get_search_query(false));
    } elseif (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
        $term    = get_queried_object();
        $heading = (string) $field('section_heading', $term) ?: single_term_title('', false);
        $text    = (string) $field('section_text', $term) ?: term_description();
    } else {
        $page_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
        if ($page_id > 0) {
            $heading = function_exists('get_field')
                ? (string) get_field('section_heading', $page_id)
                : (string) get_post_meta($page_id, 'section_heading', true);
            $text = function_exists('get_field')
                ? (string) get_field('section_text', $page_id)
                : wpautop((string) get_post_meta($page_id, 'section_text', true));
        }
        if (trim($heading) === '') {
            $heading = $page_id > 0 ? get_the_title($page_id) : __('Shop', 'themeify');
        }
    }

    return [
        'heading' => trim(wp_strip_all_tags((string) $heading)),
        'text'    => trim((string) $text),
    ];
}

/*
 * Single product page: see woocommerce.php + template-parts/product-detail.php. It no longer runs the
 * default WooCommerce summary/tabs/related hooks, nor the section builder; only the product
 * detail block, then testimonials and FAQ from Theme Settings (themeify_render_global_sections()).
 */

/**
 * Everything the product detail block needs: gallery images (featured, gallery, then variation
 * images), variation attributes as pill options, variation data for the script and the initial selection.
 */
function themeify_product_detail_data(WC_Product $product)
{
    $ids = array_merge([(int) $product->get_image_id()], array_map('intval', $product->get_gallery_image_ids()));

    $options    = [];
    $variations = [];
    $selected   = [];
    $initial    = null;

    if ($product->is_type('variable')) {
        foreach ($product->get_variation_attributes() as $name => $values) {
            $key  = 'attribute_' . sanitize_title($name);
            $opts = [];
            if (taxonomy_exists($name)) {
                foreach (wc_get_product_terms($product->get_id(), $name, ['fields' => 'all']) as $term) {
                    if (in_array($term->slug, $values, true)) {
                        $opts[] = ['value' => $term->slug, 'label' => $term->name];
                    }
                }
            } else {
                foreach ($values as $value) {
                    $opts[] = ['value' => (string) $value, 'label' => (string) $value];
                }
            }
            if ($opts) {
                $options[] = ['key' => $key, 'label' => wc_attribute_label($name, $product), 'options' => $opts];
            }
        }

        // Every enabled variation, with or without a price: there is no cart, Buy Now is a link, so a
        // price is optional (WooCommerce's get_available_variations() would drop price-less variations).
        foreach ($product->get_children() as $child_id) {
            $variation = wc_get_product($child_id);
            if (!$variation || !$variation->exists() || $variation->get_status() !== 'publish') {
                continue;
            }
            $image_id = (int) $variation->get_image_id();
            if ($image_id) {
                $ids[] = $image_id; // duplicates are removed below
            }
            $link         = themeify_buy_now_link($variation);
            $variations[] = [
                'id'         => (int) $variation->get_id(),
                'attributes' => $variation->get_variation_attributes(), // attribute_{name} => value ('' = any)
                'image_id'   => $image_id,
                'price_html' => $variation->get_price() !== '' ? $variation->get_price_html() : '',
                'available'  => $link !== '', // Buy Now needs a link
                'link'       => $link,
            ];
        }

        // Initial choice: the product's default attributes, else (per attribute, in pill order) the first
        // option that has a buyable variation, else the first option.
        $defaults = [];
        foreach ($product->get_default_attributes() as $name => $value) {
            $defaults['attribute_' . sanitize_title($name)] = (string) $value;
        }
        foreach ($options as $group) {
            $value = $defaults[$group['key']] ?? '';
            if ($value === '') {
                foreach ($group['options'] as $opt) {
                    foreach ($variations as $v) {
                        $want = (string) ($v['attributes'][$group['key']] ?? '');
                        if ($v['available'] && ($want === '' || $want === $opt['value'])) {
                            $value = $opt['value'];
                            break 2;
                        }
                    }
                }
            }
            if ($value === '') {
                $value = $group['options'][0]['value'];
            }
            $selected[$group['key']] = $value;
        }
        foreach ($variations as $v) {
            $match = true;
            foreach ($selected as $key => $value) {
                $want = (string) ($v['attributes'][$key] ?? '');
                if ($want !== '' && $want !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $initial = $v;
                break;
            }
        }
    }

    $ids = array_values(array_unique(array_filter($ids)));

    $images = [];
    foreach ($ids as $id) {
        $src = wp_get_attachment_image_url($id, 'full');
        if (!$src) {
            continue;
        }
        $alt = trim((string) get_post_meta($id, '_wp_attachment_image_alt', true));
        $images[] = [
            'id'     => $id,
            'src'    => $src,
            'srcset' => (string) wp_get_attachment_image_srcset($id, 'full'),
            'alt'    => $alt !== '' ? $alt : $product->get_name(),
            'thumb'  => wp_get_attachment_image($id, 'medium_large', false, ['class' => 'tf-pd__thumb-img', 'alt' => '', 'loading' => 'lazy', 'sizes' => '48px']),
        ];
    }

    $active = 0;
    if ($initial && $initial['image_id']) {
        foreach ($images as $i => $image) {
            if ($image['id'] === $initial['image_id']) {
                $active = $i;
                break;
            }
        }
    }

    return [
        'images'     => $images,
        'active'     => $active,
        'options'    => $options,
        'variations' => $variations,
        'selected'   => $selected,
        'initial'    => $initial,
    ];
}

/*
 * Buy Now goes to a custom link (cart and checkout are switched off, see below).
 * Product data → General has "Buy Now link" and "Buy Now button text"; a variation can override the
 * link in its own panel. Without a link the button shows as unavailable.
 */
define('THEMEIFY_BUY_LINK_META', '_tf_buy_now_url');
define('THEMEIFY_BUY_TEXT_META', '_tf_buy_now_text');

/** The Buy Now link for a product or variation ('' = none). Variations fall back to their parent's link. */
function themeify_buy_now_link($product)
{
    $product = $product instanceof WC_Product ? $product : wc_get_product($product);
    if (!$product) {
        return '';
    }
    $link = trim((string) $product->get_meta(THEMEIFY_BUY_LINK_META));
    if ($link === '' && $product->is_type('variation')) {
        $parent = wc_get_product($product->get_parent_id());
        $link   = $parent ? trim((string) $parent->get_meta(THEMEIFY_BUY_LINK_META)) : '';
    }
    return $link !== '' ? esc_url_raw($link) : '';
}

/** The Buy Now button label for a product ("Buy Now" when empty). */
function themeify_buy_now_text($product)
{
    $product = $product instanceof WC_Product ? $product : wc_get_product($product);
    $text    = $product ? trim((string) $product->get_meta(THEMEIFY_BUY_TEXT_META)) : '';
    return $text !== '' ? $text : __('Buy Now', 'themeify');
}

// Product data → General: link + button text (hidden for external / grouped products, which have their own fields).
// Form field names (kept apart from the meta keys so the product field and the variation fields,
// which post as arrays, never share a name).
define('THEMEIFY_BUY_LINK_FIELD', 'themeify_buy_now_url');
define('THEMEIFY_BUY_TEXT_FIELD', 'themeify_buy_now_text');
define('THEMEIFY_VAR_LINK_FIELD', 'themeify_variation_buy_now_url');

// Product data → General: link + button text (hidden for external / grouped products, which have their own fields).
add_action('woocommerce_product_options_general_product_data', function () {
    global $product_object;
    $product = $product_object instanceof WC_Product ? $product_object : null;

    echo '<div class="options_group hide_if_external hide_if_grouped">';
    woocommerce_wp_text_input([
        'id'          => THEMEIFY_BUY_LINK_FIELD,
        'value'       => $product ? $product->get_meta(THEMEIFY_BUY_LINK_META) : '',
        'label'       => __('Buy Now link', 'themeify'),
        'placeholder' => 'https://',
        'type'        => 'url',
        'desc_tip'    => true,
        'description' => __('Where the button on the product page goes. Variations can set their own link in their variation panel; empty ones use this link.', 'themeify'),
    ]);
    woocommerce_wp_text_input([
        'id'          => THEMEIFY_BUY_TEXT_FIELD,
        'value'       => $product ? $product->get_meta(THEMEIFY_BUY_TEXT_META) : '',
        'label'       => __('Buy Now button text', 'themeify'),
        'placeholder' => __('Buy Now', 'themeify'),
        'desc_tip'    => true,
        'description' => __('Label of the button on the product page. Leave empty for "Buy Now".', 'themeify'),
    ]);
    echo '</div>';
});

/** A posted text value, or null when it is missing or not a string. */
function themeify_posted_string($value)
{
    return is_string($value) ? trim(wp_unslash($value)) : null;
}

add_action('woocommerce_admin_process_product_object', function ($product) {
    // phpcs:disable WordPress.Security.NonceVerification -- WooCommerce verifies the product save.
    $link = themeify_posted_string($_POST[THEMEIFY_BUY_LINK_FIELD] ?? null);
    if ($link !== null) {
        $product->update_meta_data(THEMEIFY_BUY_LINK_META, esc_url_raw($link));
    }
    $text = themeify_posted_string($_POST[THEMEIFY_BUY_TEXT_FIELD] ?? null);
    if ($text !== null) {
        $product->update_meta_data(THEMEIFY_BUY_TEXT_META, sanitize_text_field($text));
    }
    // phpcs:enable
});

// Variation panel field (overrides the product's link for that variation).
add_action('woocommerce_product_after_variable_attributes', function ($loop, $variation_data, $variation) {
    woocommerce_wp_text_input([
        'id'            => THEMEIFY_VAR_LINK_FIELD . "_{$loop}",
        'name'          => THEMEIFY_VAR_LINK_FIELD . "[{$loop}]",
        'value'         => get_post_meta($variation->ID, THEMEIFY_BUY_LINK_META, true),
        'label'         => __('Buy Now link', 'themeify'),
        'placeholder'   => __('Empty = the product\'s Buy Now link', 'themeify'),
        'type'          => 'url',
        'wrapper_class' => 'form-row form-row-full',
        'desc_tip'      => true,
        'description'   => __('Where Buy Now goes when this variation is selected.', 'themeify'),
    ]);
}, 10, 3);

add_action('woocommerce_admin_process_variation_object', function ($variation, $i) {
    // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verifies the variation save.
    $posted = isset($_POST[THEMEIFY_VAR_LINK_FIELD]) && is_array($_POST[THEMEIFY_VAR_LINK_FIELD]) ? $_POST[THEMEIFY_VAR_LINK_FIELD] : [];
    $link   = themeify_posted_string($posted[$i] ?? null);
    if ($link !== null) {
        $variation->update_meta_data(THEMEIFY_BUY_LINK_META, esc_url_raw($link));
    }
}, 10, 2);

/*
 * Cart and checkout are switched off: nothing can be added to a cart, the cart and checkout pages
 * redirect to the shop, and WooCommerce's cart-fragments script is not loaded.
 */
add_filter('woocommerce_is_purchasable', '__return_false');

add_action('template_redirect', function () {
    if ((function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout())) {
        wp_safe_redirect(themeify_shop_url());
        exit;
    }
});

add_action('wp_enqueue_scripts', function () {
    wp_dequeue_script('wc-cart-fragments');
}, 100);

add_filter('loop_shop_columns', function () {
    return 3;
});

add_filter('woocommerce_output_related_products_args', function ($args) {
    $args['posts_per_page'] = 4;
    $args['columns'] = 4;
    return $args;
});
