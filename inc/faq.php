<?php
/**
 * "FAQ" section: on the left a heading, intro text, a short rule, a "Still have questions?"
 * sub heading with text and a contact button; on the right an accordion of questions (one open
 * at a time, opened and closed with Motion, see assets/js/faq.js).
 *
 * Content comes from the ACF group "Faq section" on the "Theme Settings" options page
 * (see themeify_render_global_sections()):
 * - faq_heading (text), faq_text (text)
 * - accordions (repeater) with the sub fields question (text) and answer (WYSIWYG)
 * - sub_heading (text), sub_text (WYSIWYG), button_text (text), faq_button_link (text or URL)
 * Nothing is shown for an empty field, and the whole section is hidden until a question is
 * filled in. A button with text but no link goes to the Contact page.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Section data from the page's ACF "Faq section" group. Reads the raw post meta when ACF is not active. */
function themeify_home_faq_acf($post_id)
{
    $acf = function_exists('get_field');
    $get = function ($name) use ($post_id, $acf) {
        return $acf ? get_field($name, $post_id) : themeify_raw_field($post_id, $name);
    };

    $rows = $acf ? get_field('accordions', $post_id) : null;
    if (!is_array($rows)) {
        // Without ACF the repeater is stored as a row count plus accordions_{n}_{sub} meta.
        $rows = [];
        for ($n = 0, $count = (int) themeify_raw_field($post_id, 'accordions'); $n < $count; $n++) {
            $rows[] = [
                'question' => themeify_raw_field($post_id, "accordions_{$n}_question"),
                'answer'   => wpautop((string) themeify_raw_field($post_id, "accordions_{$n}_answer")),
            ];
        }
    }

    $items = [];
    foreach ($rows as $row) {
        $item = [
            'question' => trim((string) ($row['question'] ?? '')),
            'answer'   => trim((string) ($row['answer'] ?? '')),
        ];
        if ($item['question'] !== '') {
            $items[] = $item;
        }
    }

    $sub_text = trim((string) $get('sub_text'));
    if ($sub_text !== '' && !$acf) {
        $sub_text = wpautop($sub_text);
    }

    $link = $get('faq_button_link');
    $link = trim(is_array($link) ? (string) ($link['url'] ?? '') : (string) $link);
    if ($link === '') {
        $contact = get_page_by_path('contact');
        $link    = $contact ? get_permalink($contact) : '';
    }

    return [
        'type'        => 'faq',
        'heading'     => trim((string) $get('faq_heading')),
        'text'        => trim((string) $get('faq_text')),
        'items'       => $items,
        'sub_heading' => trim((string) $get('sub_heading')),
        'sub_text'    => $sub_text,
        'button_text' => trim((string) $get('button_text')),
        'button_url'  => $link,
    ];
}

/** The FAQ section, see template-parts/faq.php. */
function themeify_render_section_home_faq($s)
{
    get_template_part('template-parts/faq', null, ['section' => $s]);
}

/**
 * Raw (non-ACF) value of a field: post meta for a post ID, or the wp_options row ACF uses for its
 * options pages ("options_{name}") when $post_id is 'option' / 'options'.
 */
function themeify_raw_field($post_id, $name)
{
    if ($post_id === 'option' || $post_id === 'options') {
        return get_option('options_' . $name, '');
    }
    return get_post_meta($post_id, $name, true);
}

/**
 * Testimonials + FAQ, used on the home page, shop / product archives and product pages.
 * Content is managed in one place: the ACF options page "Theme Settings" (groups "Testimonial cards"
 * and "Faq section"). Each section is hidden until it has at least one testimonial / question there.
 */
function themeify_render_global_sections()
{
    themeify_render_section_testimonials(themeify_testimonials_acf('option'));
    themeify_render_section_home_faq(themeify_home_faq_acf('option'));
}
