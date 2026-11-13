<?php
/**
 * Docs sidebar scoped to one theme: theme switcher, overview link, and only the
 * categories that hold this theme's docs.
 *
 * Args: theme (WP_Term), current_doc (int, 0 on the theme overview page).
 */
$theme   = $args['theme'] ?? null;
$current = (int) ($args['current_doc'] ?? 0);
if (!$theme instanceof WP_Term) {
    return;
}

$tree   = themeify_theme_doc_tree($theme->term_id);
$themes = get_terms(['taxonomy' => 'doc_theme', 'hide_empty' => true, 'orderby' => 'name']);
$themes = is_wp_error($themes) ? [] : $themes;

// Open the categories that lead to the current doc.
$open = [];
if ($current) {
    $cats = get_the_terms($current, 'doc_category');
    foreach (($cats && !is_wp_error($cats)) ? $cats : [] as $cat) {
        $open[] = (int) $cat->term_id;
        $open   = array_merge($open, array_map('intval', get_ancestors($cat->term_id, 'doc_category', 'taxonomy')));
    }
}
// The theme's image, or its icon when it has no image.
$thumb = function ($term_id) {
    $img = themeify_doc_theme_image($term_id, 'thumbnail', ['class' => 'tf-theme-switch__thumb', 'alt' => '']);
    return $img ?: '<span class="tf-theme-switch__thumb tf-theme-switch__icon">' . themeify_icon(themeify_theme_icon($term_id)) . '</span>';
};
?>
<details class="tf-theme-switch">
    <summary aria-label="<?php echo esc_attr(sprintf(__('Theme: %s. Switch theme', 'themeify'), $theme->name)); ?>">
        <?php echo $thumb($theme->term_id); ?>
        <span class="tf-theme-switch__name"><?php echo esc_html($theme->name); ?></span>
        <?php echo themeify_icon('chevrons'); ?>
    </summary>
    <ul class="tf-theme-switch__menu">
        <?php foreach ($themes as $item) : ?>
            <li>
                <a href="<?php echo esc_url(get_term_link($item)); ?>"<?php echo (int) $item->term_id === (int) $theme->term_id ? ' aria-current="true"' : ''; ?>>
                    <?php echo $thumb($item->term_id); ?>
                    <span><?php echo esc_html($item->name); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</details>

<nav class="tf-tnav" aria-label="<?php echo esc_attr(sprintf(__('%s docs', 'themeify'), $theme->name)); ?>">
    <ul class="tf-tnav__list">
        <li>
            <a class="tf-tnav__link<?php echo $current ? '' : ' is-current'; ?>" href="<?php echo esc_url(get_term_link($theme)); ?>"<?php echo $current ? '' : ' aria-current="page"'; ?>>
                <?php echo themeify_icon(themeify_theme_icon($theme->term_id)); ?>
                <?php echo esc_html(sprintf(__('%s documentation', 'themeify'), $theme->name)); ?>
            </a>
        </li>
        <?php
        themeify_theme_nav_branch($tree, 0, $current, $open);
        foreach ($tree['other'] as $doc) {
            themeify_theme_nav_doc($doc, $current);
        }
        ?>
    </ul>
</nav>
