<?php
get_header();

$themes = get_terms(['taxonomy' => 'doc_theme', 'hide_empty' => true, 'orderby' => 'name']);
$themes = is_wp_error($themes) ? [] : $themes;
$intro  = themeify_docs_text('intro');
$footer = themeify_docs_text('footer');
?>
<div class="tf-container tf-docs-home">
    <?php get_template_part('template-parts/docs-ask'); ?>

    <?php if ($intro !== '') : ?>
        <p class="tf-docs-home__intro"><?php echo esc_html($intro); ?></p>
    <?php endif; ?>

    <section class="tf-docs-home__themes" aria-labelledby="tf-pick-theme">
        <h2 id="tf-pick-theme" class="tf-docs-home__title"><?php echo esc_html(themeify_docs_text('cards_title')); ?></h2>

        <?php if ($themes) : ?>
            <div class="tf-theme-cards">
                <?php foreach ($themes as $theme) :
                    $image = themeify_doc_theme_image($theme->term_id, 'full', [
                        'class'   => 'tf-theme-card__img',
                        'alt'     => '',
                        'sizes'   => '(max-width: 640px) 100vw, (max-width: 1100px) 50vw, 500px',
                        'loading' => 'lazy',
                    ]);
                    ?>
                    <a class="tf-theme-card" href="<?php echo esc_url(get_term_link($theme)); ?>">
                        <span class="tf-theme-card__media<?php echo $image ? '' : ' is-icon'; ?>">
                            <?php echo $image ?: themeify_icon(themeify_theme_icon($theme->term_id), 'tf-icon tf-theme-card__icon'); ?>
                        </span>
                        <span class="tf-theme-card__body">
                            <strong class="tf-theme-card__title">
                                <?php if ($image) : // without an image the icon already fills the picture area ?>
                                    <span class="tf-theme-card__badge"><?php echo themeify_icon(themeify_theme_icon($theme->term_id)); ?></span>
                                <?php endif; ?>
                                <span><?php echo esc_html($theme->name); ?></span>
                            </strong>
                            <?php if ($theme->description) : ?>
                                <span class="tf-theme-card__text"><?php echo esc_html(wp_trim_words($theme->description, 32)); ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="tf-empty"><?php esc_html_e('No themes yet.', 'themeify'); ?></p>
        <?php endif; ?>
    </section>

    <?php if ($footer !== '') : ?>
        <p class="tf-docs-home__foot"><?php echo wp_kses_post($footer); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
