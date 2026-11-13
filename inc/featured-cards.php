<?php
/**
 * "Featured cards" section ("Why choose us?"): a centred heading and intro text above a grid of
 * numbered cards (heading + text). In each group of six cards the 1st and 5th are wide, so the
 * rows alternate wide | small | small and small | wide | small.
 *
 * Content comes from the front page's ACF group "Featured cards": cards_heading (text),
 * cards_text (text) and the repeater cards with the sub fields heading (text) and text (WYSIWYG).
 * Nothing is shown for an empty field, and the whole section is hidden until a card has content.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Section data from the page's ACF "Featured cards" group. Reads the raw post meta when ACF is not active. */
function themeify_featured_cards_acf($post_id)
{
    $acf = function_exists('get_field');
    $get = function ($name) use ($post_id, $acf) {
        return $acf ? get_field($name, $post_id) : get_post_meta($post_id, $name, true);
    };

    $rows = $acf ? get_field('cards', $post_id) : null;
    if (!is_array($rows)) {
        // Without ACF the repeater is stored as a row count plus cards_{n}_{sub} meta.
        $rows = [];
        for ($n = 0, $count = (int) get_post_meta($post_id, 'cards', true); $n < $count; $n++) {
            $rows[] = [
                'heading' => get_post_meta($post_id, "cards_{$n}_heading", true),
                'text'    => wpautop((string) get_post_meta($post_id, "cards_{$n}_text", true)),
            ];
        }
    }

    $cards = [];
    foreach ($rows as $row) {
        $card = [
            'heading' => trim((string) ($row['heading'] ?? '')),
            'text'    => trim((string) ($row['text'] ?? '')),
        ];
        if ($card['heading'] !== '' || $card['text'] !== '') {
            $cards[] = $card;
        }
    }

    return [
        'type'    => 'featured_cards',
        'heading' => trim((string) $get('cards_heading')),
        'text'    => trim((string) $get('cards_text')),
        'cards'   => $cards,
    ];
}

/** The "featured cards" grid, see template-parts/featured-cards.php. */
function themeify_render_section_featured_cards($s)
{
    get_template_part('template-parts/featured-cards', null, ['section' => $s]);
}
