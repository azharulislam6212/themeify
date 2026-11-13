<?php get_header(); ?>

<?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('tf-container tf-narrow tf-section'); ?>>
        <header class="tf-page__header">
            <h1><?php the_title(); ?></h1>
            <p class="tf-meta"><?php echo esc_html(get_the_date()); ?></p>
        </header>
        <?php if (has_post_thumbnail()) : ?>
            <?php the_post_thumbnail('themeify-hero', ['class' => 'tf-card__img']); ?>
        <?php endif; ?>
        <div class="tf-prose"><?php the_content(); ?></div>
    </article>
<?php endwhile; ?>

<?php get_footer(); ?>
