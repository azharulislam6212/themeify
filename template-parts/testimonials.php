<?php
/**
 * Home page testimonials: heading, intro text and a looping Swiper carousel of review cards
 * (stars, quote, author name, quote mark). Script: assets/js/testimonials.js.
 * Data comes from the "Theme Settings" options page, see themeify_testimonials_acf().
 *
 * @var array $args ['section' => ['heading' => string, 'text' => string, 'items' => [['text' (html), 'author', 'rating' 0-5]]]]
 */
if (!defined('ABSPATH')) {
    exit;
}

$s       = $args['section'] ?? [];
$heading = trim((string) ($s['heading'] ?? ''));
$text    = trim((string) ($s['text'] ?? ''));
$items   = $s['items'] ?? [];

if (!$items) {
    return;
}

/*
 * The loop needs more cards than fit on a wide screen (about 4), so the set repeats until there
 * are at least 8. Only the first set is read by screen readers.
 */
$slides = [];
for ($r = 0, $reps = (int) ceil(8 / count($items)); $r < $reps; $r++) {
    foreach ($items as $item) {
        $slides[] = $item + ['copy' => $r > 0];
    }
}

$star = '<svg class="tf-tm__star" viewBox="540.5 304.5 11 11" width="11" height="11" aria-hidden="true" focusable="false">'
    . '<path d="M550.982 309.384L548.873 311.204L549.516 313.926C549.551 314.074 549.542 314.228 549.489 314.371C549.437 314.514 549.343 314.637 549.22 314.727C549.097 314.816 548.951 314.867 548.799 314.873C548.647 314.879 548.497 314.84 548.367 314.761L546 313.304L543.631 314.761C543.502 314.84 543.352 314.879 543.2 314.872C543.049 314.866 542.902 314.815 542.78 314.726C542.657 314.636 542.563 314.513 542.511 314.37C542.458 314.228 542.449 314.073 542.484 313.926L543.129 311.204L541.02 309.384C540.905 309.284 540.822 309.154 540.781 309.008C540.741 308.862 540.744 308.707 540.79 308.563C540.837 308.418 540.925 308.291 541.044 308.196C541.162 308.102 541.306 308.044 541.457 308.031L544.222 307.808L545.289 305.226C545.347 305.085 545.445 304.965 545.572 304.88C545.698 304.796 545.847 304.75 545.999 304.75C546.151 304.75 546.3 304.796 546.426 304.88C546.552 304.965 546.651 305.085 546.708 305.226L547.775 307.808L550.54 308.031C550.692 308.044 550.836 308.101 550.955 308.196C551.074 308.29 551.162 308.417 551.209 308.562C551.256 308.706 551.259 308.861 551.218 309.008C551.178 309.154 551.094 309.285 550.98 309.384Z"/></svg>';

$quote_mark = '<svg class="tf-tm__mark" viewBox="437 510.5 29 22" width="29" height="22" aria-hidden="true" focusable="false">'
    . '<path d="M456.8 532.22L455 529.82C455.96 529.26 456.88 528.54 457.76 527.66C458.72 526.7 459.56 525.58 460.28 524.3C461 523.02 461.44 521.58 461.6 519.98C460.56 520.3 459.28 520.14 457.76 519.5C456.24 518.86 455.48 517.46 455.48 515.3C455.48 513.94 455.92 512.82 456.8 511.94C457.76 510.98 459 510.5 460.52 510.5C462.52 510.5 463.88 511.22 464.6 512.66C465.4 514.02 465.8 515.5 465.8 517.1C465.8 519.26 465.4 521.3 464.6 523.22C463.88 525.14 462.84 526.86 461.48 528.38C460.2 529.9 458.64 531.18 456.8 532.22ZM438.8 532.22L437 529.82C437.96 529.26 438.88 528.54 439.76 527.66C440.72 526.7 441.56 525.58 442.28 524.3C443 523.02 443.44 521.58 443.6 519.98C442.56 520.3 441.28 520.14 439.76 519.5C438.24 518.86 437.48 517.46 437.48 515.3C437.48 513.94 437.92 512.82 438.8 511.94C439.76 510.98 441 510.5 442.52 510.5C444.52 510.5 445.88 511.22 446.6 512.66C447.4 514.02 447.8 515.5 447.8 517.1C447.8 519.26 447.4 521.3 446.6 523.22C445.88 525.14 444.84 526.86 443.48 528.38C442.2 529.9 440.64 531.18 438.8 532.22Z"/></svg>';
?>
<section class="tf-tm"<?php echo $heading ? ' aria-labelledby="tf-tm-heading"' : ''; ?>>
    <?php if ($heading || $text) : ?>
        <header class="tf-tm__head">
            <?php if ($heading) : ?>
                <h2 class="tf-tm__title" id="tf-tm-heading"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="tf-tm__text"><?php echo esc_html($text); ?></p>
            <?php endif; ?>
        </header>
    <?php endif; ?>

    <div class="tf-tm__carousel swiper" data-tf-tm>
        <div class="swiper-wrapper">
            <?php foreach ($slides as $slide) : ?>
                <div class="tf-tm__slide swiper-slide"<?php echo $slide['copy'] ? ' aria-hidden="true"' : ''; ?>>
                    <figure class="tf-tm__card">
                        <div class="tf-tm__stars" role="img" aria-label="<?php echo esc_attr(sprintf(
                            /* translators: %d: number of stars */
                            __('Rated %d out of 5', 'themeify'),
                            $slide['rating']
                        )); ?>">
                            <?php for ($n = 1; $n <= 5; $n++) : ?>
                                <span class="tf-tm__star-wrap<?php echo $n > $slide['rating'] ? ' is-off' : ''; ?>"><?php echo $star; // static markup ?></span>
                            <?php endfor; ?>
                        </div>
                        <blockquote class="tf-tm__quote"><?php echo wp_kses_post($slide['text']); ?></blockquote>
                        <figcaption class="tf-tm__foot">
                            <?php if ($slide['author'] !== '') : ?>
                                <cite class="tf-tm__author"><?php echo esc_html($slide['author']); ?></cite>
                            <?php endif; ?>
                            <?php echo $quote_mark; // static markup ?>
                        </figcaption>
                    </figure>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
