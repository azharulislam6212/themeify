<?php get_header(); ?>

<div class="tf-container tf-section">
    <?php if (have_posts()) : ?>
        <div class="tf-grid">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('tf-card'); ?>>
                    <?php if (has_post_thumbnail()) : ?>
                        <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('themeify-card', ['class' => 'tf-card__img']); ?></a>
                    <?php endif; ?>
                    <h2 class="tf-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <p><?php echo esc_html(get_the_excerpt()); ?></p>
                </article>
            <?php endwhile; ?>
        </div>
        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p><?php esc_html_e('Nothing found.', 'themeify'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
