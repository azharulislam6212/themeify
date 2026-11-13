<?php
$taxonomy = $args['taxonomy'] ?? 'doc_category';
$limit    = isset($args['limit']) ? (int) $args['limit'] : 6;
$terms    = get_terms([
    'taxonomy'   => $taxonomy,
    'parent'     => 0,
    'hide_empty' => !empty($args['hide_empty']),
    'number'     => $limit,
]);
if (is_wp_error($terms) || empty($terms)) {
    $message = $taxonomy === 'doc_theme' ? __('No themes yet.', 'themeify') : __('No doc categories yet.', 'themeify');
    echo '<p class="tf-empty">' . esc_html($message) . '</p>';
    return;
}
foreach ($terms as $term) : ?>
    <a class="tf-card tf-doc-card" href="<?php echo esc_url(get_term_link($term)); ?>">
        <span class="tf-doc-card__icon"><?php echo themeify_icon($taxonomy === 'doc_theme' ? themeify_theme_icon($term->term_id) : themeify_category_icon($term->term_id)); ?></span>
        <h3><?php echo esc_html($term->name); ?></h3>
        <?php if ($term->description) : ?>
            <p><?php echo esc_html(wp_trim_words($term->description, 18)); ?></p>
        <?php endif; ?>
        <span class="tf-doc-card__count"><?php echo esc_html(sprintf(_n('%d doc', '%d docs', (int) $term->count, 'themeify'), (int) $term->count)); ?></span>
    </a>
<?php endforeach; ?>
