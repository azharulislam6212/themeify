<?php
/**
 * "What do you want to do today?" search card on the docs page and docs search
 * results. Matching docs appear as you type (REST API, see assets/js/main.js);
 * Enter opens the search results page.
 *
 * Args: query (string), theme (slug of the selected theme, '' = all themes),
 *       heading (bool, default true), questions (bool, default true).
 */
$query    = (string) ($args['query'] ?? '');
$selected = (string) ($args['theme'] ?? '');
$themes   = get_terms(['taxonomy' => 'doc_theme', 'hide_empty' => true, 'orderby' => 'name']);
$themes   = is_wp_error($themes) ? [] : $themes;

$current = null;
foreach ($themes as $item) {
    if ($item->slug === $selected) {
        $current = $item;
    }
}
// "Ask" with AI answers when the site has WordPress AI connected, otherwise "Search".
$ai = themeify_ai_enabled();
if ($ai) {
    $all_placeholder = __('Ask anything about the docs', 'themeify');
    /* translators: %s: theme name */
    $theme_placeholder = __('Ask anything about %s', 'themeify');
} else {
    $all_placeholder = __('Search the docs', 'themeify');
    /* translators: %s: theme name */
    $theme_placeholder = __('Search the %s docs', 'themeify');
}
$questions = !empty($args['questions'] ?? true) ? themeify_docs_questions() : [];
$support   = themeify_docs_text('support_url');
?>
<section class="tf-ask" aria-labelledby="tf-ask-title">
    <?php if ($args['heading'] ?? true) : ?>
        <h1 id="tf-ask-title" class="tf-ask__title"><?php echo esc_html(themeify_docs_text('heading')); ?></h1>
        <?php if (themeify_docs_text('subtitle') !== '') : ?>
            <p class="tf-ask__lead"><?php echo esc_html(themeify_docs_text('subtitle')); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <h2 id="tf-ask-title" class="screen-reader-text"><?php esc_html_e('Search the docs', 'themeify'); ?></h2>
    <?php endif; ?>

    <form class="tf-ask__form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>"
        data-rest="<?php echo esc_url(rest_url('wp/v2/themeify_doc')); ?>"
        data-empty="<?php esc_attr_e('No pages match yet. Press Enter to search all the docs.', 'themeify'); ?>"
        data-error="<?php esc_attr_e('Search is not available right now. Press Enter to try again.', 'themeify'); ?>"
        data-all="<?php esc_attr_e('Press Enter to see all results', 'themeify'); ?>"
        data-count="<?php /* translators: %d: number of matching pages; keep %d */ esc_attr_e('%d pages match', 'themeify'); ?>"
        <?php if ($ai) : ?>
        data-ai="<?php echo esc_url(rest_url('themeify/v1/ask')); ?>"
        data-reading="<?php esc_attr_e('Reading the docs…', 'themeify'); ?>"
        data-followup="<?php esc_attr_e('Ask a follow-up or search again', 'themeify'); ?>"
        data-sources="<?php esc_attr_e('From the docs', 'themeify'); ?>"
        data-results="<?php esc_attr_e('See all search results', 'themeify'); ?>"
        data-failed="<?php esc_attr_e('Something went wrong. Please try again.', 'themeify'); ?>"
        <?php endif; ?>>
        <input type="hidden" name="post_type" value="themeify_doc">
        <input type="hidden" name="doc_theme" value="<?php echo esc_attr($current ? $current->slug : ''); ?>"<?php echo $current ? '' : ' disabled'; ?>>
        <div class="tf-ask__field">
            <label class="screen-reader-text" for="tf-ask-input"><?php esc_html_e('Search the docs', 'themeify'); ?></label>
            <input id="tf-ask-input" class="tf-ask__input" type="search" name="s" autocomplete="off"
                value="<?php echo esc_attr($query); ?>"
                placeholder="<?php echo esc_attr($current ? sprintf($theme_placeholder, $current->name) : $all_placeholder); ?>"
                aria-controls="tf-ask-results" aria-expanded="false" aria-describedby="tf-ask-status">
            <button class="tf-ask__submit" type="submit">
                <?php echo themeify_icon($ai ? 'arrow-up' : 'search'); ?>
                <span><?php echo esc_html($ai ? __('Ask', 'themeify') : __('Search', 'themeify')); ?></span>
            </button>
        </div>
        <div id="tf-ask-results" class="tf-ask__results" hidden></div>
        <p id="tf-ask-status" class="screen-reader-text" aria-live="polite"></p>
    </form>

    <?php if (count($themes) > 1) : ?>
        <div class="tf-ask__chips" role="group" aria-label="<?php esc_attr_e('Search in', 'themeify'); ?>">
            <button type="button" class="tf-chip" data-theme="" data-placeholder="<?php echo esc_attr($all_placeholder); ?>" aria-pressed="<?php echo $current ? 'false' : 'true'; ?>"><?php esc_html_e('All themes', 'themeify'); ?></button>
            <?php foreach ($themes as $item) : ?>
                <button type="button" class="tf-chip" data-theme="<?php echo esc_attr($item->slug); ?>" data-id="<?php echo (int) $item->term_id; ?>"
                    data-placeholder="<?php echo esc_attr(sprintf($theme_placeholder, $item->name)); ?>"
                    aria-pressed="<?php echo $current && $current->term_id === $item->term_id ? 'true' : 'false'; ?>"><?php echo esc_html($item->name); ?></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($questions) : ?>
        <div class="tf-ask__questions" role="group" aria-label="<?php esc_attr_e('Common questions', 'themeify'); ?>">
            <?php foreach ($questions as $question) : ?>
                <button type="button" class="tf-chip tf-chip--question" data-question="<?php echo esc_attr($question); ?>"><?php echo esc_html($question); ?></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($ai) : ?>
        <div class="tf-ask__chat" hidden>
            <div class="tf-ask__turns" aria-live="polite"></div>
            <div class="tf-ask__chat-foot">
                <?php if ($support !== '') : ?>
                    <p><?php esc_html_e('Still stuck?', 'themeify'); ?> <a href="<?php echo esc_url($support); ?>"><?php esc_html_e('Contact support', 'themeify'); ?></a></p>
                <?php else : ?>
                    <span></span>
                <?php endif; ?>
                <button type="button" class="tf-ask__reset"><?php echo themeify_icon('refresh'); ?><?php esc_html_e('Start over', 'themeify'); ?></button>
            </div>
        </div>
    <?php endif; ?>
</section>
