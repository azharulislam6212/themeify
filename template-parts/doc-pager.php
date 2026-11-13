<?php
/**
 * Previous / next doc cards at the bottom of a doc (order: see themeify_doc_sequence()).
 *
 * @var array $args ['doc_id' => int]
 */
if (!defined('ABSPATH')) {
    exit;
}

$pager = themeify_doc_pager((int) ($args['doc_id'] ?? get_the_ID()));
if (!$pager['prev'] && !$pager['next']) {
    return;
}
$chev = function ($dir) {
    $d = $dir === 'prev' ? 'M10 4 6 8l4 4' : 'm6 4 4 4-4 4';
    return '<svg class="tf-pager__chev" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path d="' . $d . '" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
};
?>
<nav class="tf-pager" aria-label="<?php esc_attr_e('More docs', 'themeify'); ?>">
    <?php foreach (['prev', 'next'] as $dir) :
        $post = $pager[$dir];
        if (!$post) {
            echo '<span class="tf-pager__spacer" aria-hidden="true"></span>';
            continue;
        }
        $summary = themeify_doc_summary($post);
        ?>
        <a class="tf-pager__card tf-pager__card--<?php echo esc_attr($dir); ?>" href="<?php echo esc_url(get_permalink($post)); ?>" rel="<?php echo $dir === 'prev' ? 'prev' : 'next'; ?>">
            <span class="tf-pager__title">
                <?php if ($dir === 'prev') {
                    echo $chev('prev'); // static markup
                } ?>
                <span class="screen-reader-text"><?php echo $dir === 'prev' ? esc_html__('Previous:', 'themeify') : esc_html__('Next:', 'themeify'); ?> </span>
                <?php echo esc_html(get_the_title($post)); ?>
                <?php if ($dir === 'next') {
                    echo $chev('next'); // static markup
                } ?>
            </span>
            <?php if ($summary !== '') : ?>
                <span class="tf-pager__text"><?php echo esc_html($summary); ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
