<?php
/**
 * Home page FAQ: intro column (heading, text, rule, "still have questions?" and a contact
 * button) next to an accordion of questions. Each question is a <details>, so it works without
 * JavaScript; assets/js/faq.js animates opening/closing and keeps one open at a time.
 * Data comes from the "Theme Settings" options page, see themeify_home_faq_acf().
 *
 * @var array $args ['section' => ['heading', 'text', 'items' => [['question', 'answer' (html)]], 'sub_heading', 'sub_text' (html), 'button_text', 'button_url']]
 */
if (!defined('ABSPATH')) {
    exit;
}

$s     = $args['section'] ?? [];
$items = $s['items'] ?? [];

if (!$items) {
    return;
}

$heading     = trim((string) ($s['heading'] ?? ''));
$text        = trim((string) ($s['text'] ?? ''));
$sub_heading = trim((string) ($s['sub_heading'] ?? ''));
$sub_text    = trim((string) ($s['sub_text'] ?? ''));
$button_text = trim((string) ($s['button_text'] ?? ''));
$button_url  = trim((string) ($s['button_url'] ?? ''));
$has_intro   = $heading || $text || $sub_heading || $sub_text || $button_text;
?>
<section class="tf-qa<?php echo $has_intro ? '' : ' tf-qa--no-intro'; ?>"<?php echo $heading ? ' aria-labelledby="tf-qa-heading"' : ''; ?>>
    <div class="tf-qa__container">
        <?php if ($has_intro) : ?>
        <div class="tf-qa__intro">
            <?php if ($heading) : ?>
                <h2 class="tf-qa__title" id="tf-qa-heading"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="tf-qa__text"><?php echo esc_html($text); ?></p>
            <?php endif; ?>

            <?php if ($sub_heading || $sub_text || $button_text) : ?>
                <span class="tf-qa__rule" aria-hidden="true"></span>
                <?php if ($sub_heading) : ?>
                    <h3 class="tf-qa__sub-title"><?php echo esc_html($sub_heading); ?></h3>
                <?php endif; ?>
                <?php if ($sub_text) : ?>
                    <div class="tf-qa__sub-text"><?php echo wp_kses_post($sub_text); ?></div>
                <?php endif; ?>
                <?php if ($button_text && $button_url) : ?>
                    <a class="tf-qa__btn" href="<?php echo esc_url($button_url); ?>">
                        <span class="tf-qa__btn-dot" aria-hidden="true"></span>
                        <?php echo esc_html($button_text); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="tf-qa__list" data-tf-qa>
            <?php foreach ($items as $i => $item) : ?>
                <details class="tf-qa__item"<?php echo $i === 0 ? ' open' : ''; ?>>
                    <summary class="tf-qa__q">
                        <span class="tf-qa__q-text"><?php echo esc_html($item['question']); ?></span>
                        <span class="tf-qa__icon" aria-hidden="true">
                            <svg viewBox="0 0 28 28" width="28" height="28" focusable="false"><path d="M14 10.5v7M10.5 14h7" fill="none" stroke="currentColor" stroke-linecap="round"/></svg>
                        </span>
                    </summary>
                    <?php if ($item['answer'] !== '') : ?>
                        <div class="tf-qa__panel">
                            <div class="tf-qa__answer"><?php echo wp_kses_post($item['answer']); ?></div>
                        </div>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
