<?php
/**
 * Centered page header over dotted concentric rings (shop, product archives).
 *
 * @var array $args ['heading' => string, 'text' => html, 'id' => heading id]
 */
if (!defined('ABSPATH')) {
    exit;
}

$heading = trim((string) ($args['heading'] ?? ''));
$text    = trim((string) ($args['text'] ?? ''));
$id      = $args['id'] ?? 'tf-ph-heading';

if ($heading === '' && $text === '') {
    return;
}
?>
<header class="tf-ph">
    <svg class="tf-ph__rings" viewBox="0 0 1440 908" aria-hidden="true" focusable="false">
        <defs>
            <!-- Bounding-box units: each ring fades from clear at its top to visible at its bottom. -->
            <linearGradient id="tf-ph-ring-fade" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#121212" stop-opacity="0"/>
                <stop offset="1" stop-color="#121212" stop-opacity=".21"/>
            </linearGradient>
        </defs>
        <g fill="none" stroke="url(#tf-ph-ring-fade)" stroke-width="1.2" stroke-linecap="round" stroke-dasharray="0 6">
            <circle cx="720" cy="208" r="700"/>
            <circle cx="720" cy="208" r="550"/>
            <circle cx="720" cy="208" r="450"/>
        </g>
    </svg>
    <div class="tf-ph__content">
        <?php if ($heading !== '') : ?>
            <h1 class="tf-ph__title" id="<?php echo esc_attr($id); ?>"><?php echo esc_html($heading); ?></h1>
        <?php endif; ?>
        <?php if ($text !== '') : ?>
            <div class="tf-ph__text"><?php echo wp_kses_post($text); ?></div>
        <?php endif; ?>
    </div>
</header>
