<?php
$chain = [];
if (is_singular('themeify_doc')) {
    $terms = get_the_terms(get_the_ID(), 'doc_category');
    if ($terms && !is_wp_error($terms)) {
        $chain = array_merge([$terms[0]->term_id], get_ancestors($terms[0]->term_id, 'doc_category'));
    }
} elseif (is_tax('doc_category')) {
    $term  = get_queried_object();
    $chain = array_merge([$term->term_id], get_ancestors($term->term_id, 'doc_category'));
}
$current = is_singular('themeify_doc') ? get_the_ID() : 0;
?>
<nav class="tf-docnav" aria-label="<?php esc_attr_e('Docs categories', 'themeify'); ?>">
    <p class="tf-label"><?php esc_html_e('Categories', 'themeify'); ?></p>
    <?php themeify_docnav(0, $chain, $current); ?>
</nav>
