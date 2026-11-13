<?php
get_header();

$theme   = get_queried_object();
$tree    = themeify_theme_doc_tree($theme->term_id);
$cards   = themeify_theme_start_cards($tree);
$content = themeify_theme_page_content($theme->term_id);
$image   = themeify_doc_theme_image($theme->term_id, 'full', ['sizes' => '(max-width: 960px) 100vw, 900px']);

// "On this page": the page content's headings, then "Where to start".
$toc = $content['items'];
if ($cards) {
    $toc[] = ['id' => 'tf-where-to-start', 'title' => __('Where to start', 'themeify'), 'level' => $toc ? min(array_column($toc, 'level')) : 2];
}
$has_docs = $tree['docs'] || $tree['other'];
?>
<div class="tf-docs">
    <aside class="tf-docs__sidebar">
        <?php get_template_part('template-parts/theme-sidebar', null, ['theme' => $theme, 'current_doc' => 0]); ?>
    </aside>

    <article class="tf-docs__content">
        <nav class="tf-crumb" aria-label="<?php esc_attr_e('Breadcrumb', 'themeify'); ?>">
            <a href="<?php echo esc_url(get_post_type_archive_link('themeify_doc')); ?>"><?php esc_html_e('Docs', 'themeify'); ?></a>
        </nav>

        <header class="tf-docs__header">
            <h1><?php echo esc_html(sprintf(__('%s documentation', 'themeify'), $theme->name)); ?></h1>
            <?php if ($theme->description) : ?>
                <p class="tf-lead"><?php echo esc_html($theme->description); ?></p>
            <?php endif; ?>
        </header>

        <?php if ($image) : ?>
            <figure class="tf-theme-hero"><?php echo $image; ?></figure>
        <?php endif; ?>

        <?php if ($content['html'] !== '') : ?>
            <div class="tf-prose tf-doc-body"><?php echo $content['html']; // filtered by the_content ?></div>
        <?php endif; ?>

        <?php if ($cards) : ?>
            <section id="tf-where-to-start" class="tf-start">
                <h2><?php esc_html_e('Where to start', 'themeify'); ?></h2>
                <div class="tf-xcards">
                    <?php foreach ($cards as $card) : ?>
                        <a class="tf-xcard" href="<?php echo esc_url($card['url']); ?>">
                            <strong class="tf-xcard__title"><?php echo esc_html($card['term']->name); ?></strong>
                            <span class="tf-xcard__text"><?php
                                echo esc_html($card['term']->description
                                    ? wp_trim_words($card['term']->description, 20)
                                    : sprintf(_n('%d doc', '%d docs', $card['count'], 'themeify'), $card['count']));
                            ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!$has_docs && $content['html'] === '') : ?>
            <p class="tf-empty"><?php esc_html_e('No docs for this theme yet.', 'themeify'); ?></p>
        <?php endif; ?>
    </article>

    <aside class="tf-docs__toc">
        <?php get_template_part('template-parts/doc-toc', null, ['items' => $toc]); ?>
    </aside>
</div>

<?php get_footer(); ?>
