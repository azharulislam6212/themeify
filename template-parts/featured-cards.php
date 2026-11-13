<?php
/**
 * Home page "featured cards" ("Why choose us?"): heading, intro text and a grid of numbered cards.
 * Data comes from the front page's ACF fields, see themeify_featured_cards_acf().
 *
 * @var array $args ['section' => ['heading' => string, 'text' => string, 'cards' => [['heading', 'text' (html)]]]]
 */
if (!defined('ABSPATH')) {
    exit;
}

$s       = $args['section'] ?? [];
$heading = trim((string) ($s['heading'] ?? ''));
$text    = trim((string) ($s['text'] ?? ''));
$cards   = $s['cards'] ?? [];

if (!$cards) {
    return;
}

// Four-point sparkle in each card's top corner.
$spark = '<svg class="tf-fc__spark" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true" focusable="false">'
    . '<path d="M6 0l1.621 4.379L12 6 7.621 7.621 6 12 4.379 7.621 0 6l4.379-1.621L6 0z"/></svg>';
?>
<section class="tf-fc"<?php echo $heading ? ' aria-labelledby="tf-fc-heading"' : ''; ?>>
    <div class="tf-fc__container">
        <?php if ($heading || $text) : ?>
            <header class="tf-fc__head">
                <?php if ($heading) : ?>
                    <h2 class="tf-fc__title" id="tf-fc-heading"><?php echo esc_html($heading); ?></h2>
                <?php endif; ?>
                <?php if ($text) : ?>
                    <p class="tf-fc__text"><?php echo esc_html($text); ?></p>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <ol class="tf-fc__grid">
            <?php foreach ($cards as $i => $card) :
                $wide = in_array($i % 6, [0, 4], true);
                ?>
                <li class="tf-fc__card<?php echo $wide ? ' tf-fc__card--wide' : ''; ?>">
                    <div class="tf-fc__top">
                        <span class="tf-fc__num" aria-hidden="true"><?php echo esc_html(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                        <?php echo $spark; // static markup ?>
                    </div>
                    <div class="tf-fc__body">
                        <?php if ($card['heading'] !== '') : ?>
                            <h3 class="tf-fc__name"><?php echo esc_html($card['heading']); ?></h3>
                        <?php endif; ?>
                        <?php if ($card['text'] !== '') : ?>
                            <div class="tf-fc__desc"><?php echo wp_kses_post($card['text']); ?></div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
