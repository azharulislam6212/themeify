<?php
/**
 * "Testimonials" section: a centred heading and intro text above a full-width Swiper carousel of
 * review cards (star rating, quote, author name). The active card sits in the middle with the
 * neighbours peeking in from both screen edges; it loops and autoplays.
 *
 * Content comes from the ACF group "Testimonial cards" on the "Theme Settings" options page
 * (see themeify_render_global_sections()): testimonials_heading (text),
 * testimonials_text (text) and the repeater testimonials with the sub fields text (WYSIWYG, the
 * quote), heading (text, the author name) and review_rate (range: 1–5 stars, or 0–100 read as a
 * percentage). Nothing is shown for an empty field, and the whole section is hidden until a
 * testimonial has a quote.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Section data from the page's ACF "Testimonial cards" group. Reads the raw post meta when ACF is not active. */
function themeify_testimonials_acf($post_id)
{
    $acf = function_exists('get_field');
    $get = function ($name) use ($post_id, $acf) {
        return $acf ? get_field($name, $post_id) : themeify_raw_field($post_id, $name);
    };

    $heading = trim((string) $get('testimonials_heading'));
    $intro   = trim((string) $get('testimonials_text'));

    $rows = $acf ? get_field('testimonials', $post_id) : null;
    if (!is_array($rows)) {
        // Without ACF the repeater is stored as a row count plus testimonials_{n}_{sub} meta.
        $rows = [];
        for ($n = 0, $count = (int) themeify_raw_field($post_id, 'testimonials'); $n < $count; $n++) {
            $rows[] = [
                'text'        => wpautop((string) themeify_raw_field($post_id, "testimonials_{$n}_text")),
                'heading'     => themeify_raw_field($post_id, "testimonials_{$n}_heading"),
                'review_rate' => themeify_raw_field($post_id, "testimonials_{$n}_review_rate"),
            ];
        }
    }

    $items = [];
    foreach ($rows as $row) {
        $item = [
            'text'   => trim((string) ($row['text'] ?? '')),
            'author' => trim((string) ($row['heading'] ?? '')),
            'rating' => themeify_testimonial_stars($row['review_rate'] ?? ''),
        ];
        if ($item['text'] !== '') {
            $items[] = $item;
        }
    }

    return [
        'type'    => 'testimonials',
        'heading' => $heading,
        'text'    => $intro,
        'items'   => $items,
    ];
}

/**
 * Whole stars (0–5) from the "review rate" range field. ACF's range defaults to 0–100, so values
 * above 5 are read as a percentage (100 = 5 stars, 80 = 4). Empty means 5 stars.
 */
function themeify_testimonial_stars($value)
{
    if ($value === '' || $value === null) {
        return 5;
    }
    $value = (float) $value;
    if ($value > 5) {
        $value = $value / 20;
    }
    return (int) max(0, min(5, round($value)));
}

/** The testimonials carousel, see template-parts/testimonials.php. */
function themeify_render_section_testimonials($s)
{
    get_template_part('template-parts/testimonials', null, ['section' => $s]);
}
