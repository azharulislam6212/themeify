<?php get_header(); ?>

<?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('tf-page'); ?>>
        <header class="tf-container tf-page__header">
            <h1><?php the_title(); ?></h1>
        </header>
        <?php if (trim(get_the_content()) !== '') : ?>
            <div class="tf-container tf-narrow tf-prose"><?php the_content(); ?></div>
        <?php endif; ?>
        <?php themeify_render_sections(); ?>
    </article>
<?php endwhile; ?>

<?php get_footer(); ?>
