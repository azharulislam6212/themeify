<?php
/**
 * "On this page" list. Pass $args['items'] (id, title, optional level 2-4) to
 * list custom anchors; otherwise it lists the current post's titled sections.
 * Entries deeper than the top level present are indented.
 */
$items = $args['items'] ?? themeify_section_toc_items(get_the_ID());
if (!$items) {
    return;
}
$top = min(array_map(function ($item) {
    return (int) ($item['level'] ?? 2);
}, $items));
?>
<nav class="tf-toc" aria-label="<?php esc_attr_e('On this page', 'themeify'); ?>">
    <p class="tf-label"><?php echo themeify_icon('lines'); ?><?php esc_html_e('On this page', 'themeify'); ?></p>
    <ul>
        <?php foreach ($items as $item) :
            $depth = min(2, max(0, (int) ($item['level'] ?? 2) - $top));
            ?>
            <li><a href="#<?php echo esc_attr($item['id']); ?>" data-target="<?php echo esc_attr($item['id']); ?>"<?php echo $depth ? ' class="tf-toc__d' . $depth . '"' : ''; ?>><?php echo esc_html($item['title']); ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>
