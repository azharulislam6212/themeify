<?php
/**
 * "Image with row" section: a stack of cards (heading, text, button and a screenshot on a
 * green panel). On wide screens the cards stick under the header while scrolling, so each
 * row slides over the one before it.
 *
 * Content comes from the front page's ACF repeater "image_with_row" (field group made in
 * the ACF admin) with the sub fields image, heading, text (WYSIWYG, a bullet list in it
 * shows with check marks), button_lebel (text) and button_link (link or URL).
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Section data from the page's ACF "image_with_row" repeater. Reads the raw post meta when ACF is not active. */
function themeify_image_rows_acf($post_id)
{
    $acf  = function_exists('get_field');
    $rows = $acf ? get_field('image_with_row', $post_id) : null;
    if (!is_array($rows)) {
        // Without ACF the repeater is stored as a row count plus image_with_row_{n}_{sub} meta.
        $rows = [];
        for ($n = 0, $count = (int) get_post_meta($post_id, 'image_with_row', true); $n < $count; $n++) {
            $row = [];
            foreach (['image', 'heading', 'text', 'button_lebel', 'button_link'] as $sub) {
                $row[$sub] = get_post_meta($post_id, "image_with_row_{$n}_{$sub}", true);
            }
            $rows[] = $row;
        }
    }

    $items = [];
    foreach ($rows as $row) {
        // Link field: array (url, title, target) or plain URL, depending on type / return format.
        $link   = $row['button_link'] ?? '';
        $url    = is_array($link) ? (string) ($link['url'] ?? '') : (string) $link;
        $target = is_array($link) ? (string) ($link['target'] ?? '') : '';
        $label  = trim((string) ($row['button_lebel'] ?? ''));
        if ($label === '' && is_array($link)) {
            $label = trim((string) ($link['title'] ?? ''));
        }

        $text = trim((string) ($row['text'] ?? ''));
        if (!$acf) {
            $text = wpautop($text);
        }

        $item = [
            'image'         => themeify_acf_image_id($row['image'] ?? 0),
            'heading'       => trim((string) ($row['heading'] ?? '')),
            'text'          => $text,
            'button_label'  => $label,
            'button_url'    => trim($url),
            'button_target' => $target,
        ];
        if ($item['image'] || $item['heading'] || $item['text']) {
            $items[] = $item;
        }
    }

    return [
        'type' => 'image_rows',
        'rows' => $items,
    ];
}

/** The "image with row" cards, see template-parts/image-rows.php. */
function themeify_render_section_image_rows($s)
{
    get_template_part('template-parts/image-rows', null, ['section' => $s]);
}
