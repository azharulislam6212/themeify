<?php
get_header();

global $wp_query;
$is_docs = get_query_var('post_type') === 'themeify_doc';
$theme   = $is_docs ? (string) get_query_var('doc_theme') : '';
$term    = $theme !== '' ? get_term_by('slug', $theme, 'doc_theme') : null;
?>
<div class="tf-container tf-docs-home tf-search">
    <?php if ($is_docs) : ?>
        <nav class="tf-crumb" aria-label="<?php esc_attr_e('Breadcrumb', 'themeify'); ?>">
            <a href="<?php echo esc_url(get_post_type_archive_link('themeify_doc')); ?>"><?php esc_html_e('Docs', 'themeify'); ?></a>
        </nav>
        <?php get_template_part('template-parts/docs-ask', null, ['query' => get_search_query(false), 'theme' => $theme, 'heading' => false, 'questions' => false]); ?>
    <?php endif; ?>

    <h1 class="tf-search__title">
        <?php
        $count = (int) $wp_query->found_posts;
        echo esc_html(
            $term instanceof WP_Term
                /* translators: 1: number of results, 2: search text, 3: theme name */
                ? sprintf(_n('%1$d result for “%2$s” in %3$s', '%1$d results for “%2$s” in %3$s', $count, 'themeify'), $count, get_search_query(false), $term->name)
                /* translators: 1: number of results, 2: search text */
                : sprintf(_n('%1$d result for “%2$s”', '%1$d results for “%2$s”', $count, 'themeify'), $count, get_search_query(false))
        );
        ?>
    </h1>

    <?php if (have_posts()) : ?>
        <ol class="tf-results">
            <?php while (have_posts()) : the_post();
                $doc_theme = get_post_type() === 'themeify_doc' ? themeify_get_doc_theme(get_the_ID()) : null;
                ?>
                <li class="tf-result">
                    <a class="tf-result__title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    <?php if ($doc_theme && !$term) : ?>
                        <span class="tf-result__theme"><?php echo esc_html($doc_theme->name); ?></span>
                    <?php endif; ?>
                    <?php if (get_the_excerpt() !== '') : ?>
                        <p class="tf-result__text"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 30)); ?></p>
                    <?php endif; ?>
                </li>
            <?php endwhile; ?>
        </ol>
        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p class="tf-empty">
            <?php
            echo $term instanceof WP_Term
                ? esc_html__('Nothing matches in this theme. Try other words, or choose All themes.', 'themeify')
                : esc_html__('Nothing matches. Try other words, or describe what you see on screen.', 'themeify');
            ?>
        </p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
