<?php
get_header();

$term     = get_queried_object();
$children = get_terms(['taxonomy' => 'doc_category', 'parent' => $term->term_id, 'hide_empty' => false]);
?>
<div class="tf-docs">
    <aside class="tf-docs__sidebar">
        <?php get_template_part('template-parts/docs-sidebar'); ?>
    </aside>

    <main class="tf-docs__content">
        <header class="tf-docs__header">
            <p class="tf-crumb"><a href="<?php echo esc_url(get_post_type_archive_link('themeify_doc')); ?>"><?php esc_html_e('Docs', 'themeify'); ?></a></p>
            <h1><?php echo esc_html($term->name); ?></h1>
            <?php if ($term->description) : ?>
                <p class="tf-lead"><?php echo esc_html($term->description); ?></p>
            <?php endif; ?>
        </header>

        <?php if (!is_wp_error($children) && $children) : ?>
            <div class="tf-doc-cards tf-doc-cards--sub">
                <?php foreach ($children as $child) : ?>
                    <a class="tf-card tf-doc-card" href="<?php echo esc_url(get_term_link($child)); ?>">
                        <h3><?php echo esc_html($child->name); ?></h3>
                        <span class="tf-doc-card__count"><?php echo (int) $child->count; ?> <?php esc_html_e('docs', 'themeify'); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (have_posts()) : ?>
            <ul class="tf-doc-list">
                <?php while (have_posts()) : the_post(); ?>
                    <li class="tf-doc-list__item">
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <p><?php echo esc_html(get_the_excerpt()); ?></p>
                    </li>
                <?php endwhile; ?>
            </ul>
            <?php the_posts_pagination(); ?>
        <?php else : ?>
            <p class="tf-empty"><?php esc_html_e('No docs in this category yet.', 'themeify'); ?></p>
        <?php endif; ?>
    </main>
</div>

<?php get_footer(); ?>
