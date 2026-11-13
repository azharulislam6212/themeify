<?php
/**
 * Home page hero: heading with a Shopify badge, subtitle, button and a row of screenshot cards.
 * Texts and screenshots come from the front page's ACF fields, see themeify_home_hero_acf().
 *
 * @var array $args ['section' => home hero data (title, subtitle, button_text, button_url, slides)]
 */
if (!defined('ABSPATH')) {
    exit;
}

$s           = $args['section'] ?? [];
$heading     = trim((string) ($s['title'] ?? ''));
$subtitle    = trim((string) ($s['subtitle'] ?? ''));
$button_text = trim((string) ($s['button_text'] ?? ''));
$button_url  = trim((string) ($s['button_url'] ?? '')) ?: themeify_shop_url();

$badge = '<span class="tf-home-hero__badge" aria-hidden="true">'
    . file_get_contents(THEMEIFY_DIR . '/assets/img/shopify-bag.svg')
    . '</span>';

// Each heading line ({br} or a line break starts one) is escaped, then {icon} becomes the badge.
$lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $heading)), 'strlen');
$lines = array_map(function ($line) use ($badge) {
    return str_replace('{icon}', $badge, esc_html($line));
}, $lines);
?>
<section class="tf-home-hero">
    <svg class="tf-home-hero__rings" viewBox="0 0 1440 914" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="tf-ring-fade" x1="0" y1="-482" x2="0" y2="837" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#121212" stop-opacity="0"/>
                <stop offset="1" stop-color="#121212" stop-opacity=".7"/>
            </linearGradient>
        </defs>
        <g fill="none" stroke="url(#tf-ring-fade)" stroke-width="1" stroke-linecap="round" stroke-dasharray="0 9">
            <circle cx="720.5" cy="177.5" r="659.5"/>
            <circle cx="720.5" cy="177.5" r="558"/>
            <circle cx="720.5" cy="177.5" r="456.5"/>
        </g>
    </svg>

    <div class="tf-container tf-home-hero__content">
        <?php if ($lines) : ?>
            <h1 class="tf-home-hero__title">
                <?php foreach ($lines as $line) : ?>
                    <span class="tf-home-hero__line"><?php echo $line; // escaped above ?></span>
                <?php endforeach; ?>
            </h1>
        <?php endif; ?>

        <?php if ($subtitle) : ?>
            <p class="tf-home-hero__subtitle"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>

        <?php if ($button_text) : ?>
            <a class="tf-home-hero__btn" href="<?php echo esc_url($button_url); ?>">
                <span class="tf-home-hero__btn-dot" aria-hidden="true"></span>
                <?php echo esc_html($button_text); ?>
            </a>
        <?php endif; ?>
    </div>

    <?php
    /*
     * Marquee: each desktop + mobile pair is ≈ 830px wide on big screens, so the pairs repeat until
     * one run holds at least 4 pairs (wider than big screens). The run is printed twice and the
     * track slides by half its width for a seamless loop; --tf-cards keeps the speed the same
     * whatever the number of cards. Only the first set is reachable by keyboard and screen readers.
     */
    $cards = themeify_hero_cards($s['slides'] ?? []);
    if ($cards) :
        $run = [];
        for ($r = 0, $reps = (int) ceil(8 / count($cards)); $r < $reps; $r++) {
            $run = array_merge($run, $cards);
        }
        $loop = array_merge($run, $run);
    ?>
    <div class="tf-home-hero__showcase">
        <div class="tf-home-hero__cards" style="--tf-cards: <?php echo (int) count($run); ?>">
            <?php foreach ($loop as $i => $card) :
                $copy = $i >= count($cards);
                ?>
                <a class="tf-home-hero__card tf-home-hero__card--<?php echo esc_attr($card['type']); ?>"
                   href="<?php echo esc_url($card['link']); ?>"
                   <?php if ($copy) : ?>tabindex="-1" aria-hidden="true"<?php else : ?>aria-label="<?php echo esc_attr($card['label']); ?>"<?php endif; ?>>
                    <?php if ($card['id']) {
                        // Full-size src plus every size in srcset; sizes = the card's width (main.css: --h = clamp(220px, 27.78vw, 400px)).
                        echo wp_get_attachment_image($card['id'], 'full', false, [
                            'alt'       => $card['alt'],
                            'sizes'     => $card['type'] === 'mobile'
                                ? '(max-width: 792px) 121px, (min-width: 1440px) 220px, 15.3vw'
                                : '(max-width: 792px) 309px, (min-width: 1440px) 562px, 39vw',
                            'loading'   => 'eager',
                            'decoding'  => 'async',
                            'draggable' => 'false',
                        ]);
                    } else { ?>
                        <img src="<?php echo esc_url($card['url']); ?>" alt="" loading="eager" decoding="async" draggable="false">
                    <?php } ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
