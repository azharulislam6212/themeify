<?php
get_header();

while (have_posts()) : the_post();
    $cats      = get_the_terms(get_the_ID(), 'doc_category');
    $doc_theme = themeify_get_doc_theme(get_the_ID());
    $cat       = ($cats && !is_wp_error($cats)) ? $cats[0] : null;

    // Editor content, with ids on its headings so "On this page" can link to them.
    $content = '';
    $toc     = [];
    if (trim(get_the_content()) !== '') {
        $anchored = themeify_content_anchors(str_replace(']]>', ']]&gt;', apply_filters('the_content', get_the_content())));
        $content  = $anchored['html'];
        $toc      = $anchored['items'];
    }
    $toc = array_merge($toc, themeify_section_toc_items(get_the_ID()));
    ?>
    <div class="tf-docs">
        <aside class="tf-docs__sidebar">
            <?php
            if ($doc_theme) {
                get_template_part('template-parts/theme-sidebar', null, ['theme' => $doc_theme, 'current_doc' => get_the_ID()]);
            } else {
                get_template_part('template-parts/docs-sidebar');
            }
            ?>
        </aside>

        <article class="tf-docs__content" id="docs-article">
            <nav class="tf-crumb" aria-label="<?php esc_attr_e('Breadcrumb', 'themeify'); ?>">
                <a href="<?php echo esc_url(get_post_type_archive_link('themeify_doc')); ?>"><?php esc_html_e('Docs', 'themeify'); ?></a>
                <?php if ($doc_theme) : ?>
                    <span aria-hidden="true">/</span>
                    <a href="<?php echo esc_url(get_term_link($doc_theme)); ?>"><?php echo esc_html($doc_theme->name); ?></a>
                <?php endif; ?>
                <?php if ($cat) :
                    // Within a theme, the category links to its first doc for that theme.
                    $cat_docs = $doc_theme ? (themeify_theme_doc_tree($doc_theme->term_id)['docs'][$cat->term_id] ?? []) : [];
                    $cat_url  = $cat_docs ? get_permalink($cat_docs[0]) : get_term_link($cat);
                    ?>
                    <span aria-hidden="true">/</span>
                    <a href="<?php echo esc_url($cat_url); ?>"><?php echo esc_html($cat->name); ?></a>
                <?php endif; ?>
            </nav>

            <header class="tf-docs__header">
                <h1><?php the_title(); ?></h1>
                <?php if (has_excerpt()) : ?>
                    <p class="tf-lead"><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php endif; ?>
                <div class="tf-doc-actions" data-tf-doc-actions hidden>
                    <button type="button" class="tf-doc-actions__btn" data-tf-copy-page>
                        <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><rect x="5.5" y="5.5" width="8" height="8" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.3"/><path d="M10.5 3.5v-.5A1.5 1.5 0 0 0 9 1.5H4A1.5 1.5 0 0 0 2.5 3v5A1.5 1.5 0 0 0 4 9.5h.5" fill="none" stroke="currentColor" stroke-width="1.3"/></svg>
                        <span data-tf-copy-page-label><?php esc_html_e('Copy Markdown', 'themeify'); ?></span>
                    </button>
                    <details class="tf-doc-actions__open">
                        <summary class="tf-doc-actions__btn">
                            <?php esc_html_e('Open', 'themeify'); ?>
                            <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </summary>
                        <ul class="tf-doc-actions__menu">
                            <li><a href="#" data-tf-open="chatgpt" target="_blank" rel="noopener"><?php esc_html_e('Open in ChatGPT', 'themeify'); ?></a></li>
                            <li><a href="#" data-tf-open="claude" target="_blank" rel="noopener"><?php esc_html_e('Open in Claude', 'themeify'); ?></a></li>
                        </ul>
                    </details>
                </div>
            </header>

            <?php if ($content !== '') : ?>
                <div class="tf-prose tf-doc-body"><?php echo $content; // already filtered by the_content ?></div>
            <?php endif; ?>

            <?php themeify_render_sections(); ?>

            <?php get_template_part('template-parts/doc-pager', null, ['doc_id' => get_the_ID()]); ?>
        </article>

        <aside class="tf-docs__toc">
            <?php get_template_part('template-parts/doc-toc', null, ['items' => $toc]); ?>
        </aside>
    </div>
<?php endwhile;

get_footer();
