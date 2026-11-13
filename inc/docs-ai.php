<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI answers for the docs search ("What do you want to do today?"), through the
 * WordPress AI Client (WordPress 7.0+, wp_ai_client_prompt()).
 *
 * Nothing to set up in the theme: once the site has an AI provider connected
 * (Settings → Connectors) that can write text, the docs box shows "Ask" and
 * answers questions from the published docs. Without one it shows "Search" and
 * Enter opens the search results page.
 *
 * Filters:
 *  - themeify_docs_ai_enabled (bool)  turn AI answers off even when AI is available.
 *  - themeify_ai_per_hour     (int)   questions per visitor per hour, default 30.
 *  - themeify_ai_max_chars    (int)   size of the docs sent with a question.
 */

/** Whether the docs search answers with AI. Checked once per request. */
function themeify_ai_enabled()
{
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }
    $enabled = false;

    if (!function_exists('wp_ai_client_prompt') || !function_exists('wp_supports_ai') || !wp_supports_ai()) {
        return $enabled = (bool) apply_filters('themeify_docs_ai_enabled', false);
    }

    try {
        // Cheap check first: is any provider connected at all?
        $registry   = \WordPress\AiClient\AiClient::defaultRegistry();
        $configured = [];
        foreach ($registry->getRegisteredProviderIds() as $id) {
            if ($registry->isProviderConfigured($id)) {
                $configured[] = $id;
            }
        }
        if ($configured) {
            // Asking the providers which models can write text may need a request, so cache it
            // until the set of connected providers changes.
            $key    = 'themeify_ai_ok_' . md5(implode(',', $configured));
            $cached = get_transient($key);
            if ($cached === false) {
                $ok = wp_ai_client_prompt('Hello')->is_supported_for_text_generation();
                $cached = $ok === true ? 'yes' : 'no';
                set_transient($key, $cached, $cached === 'yes' ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS);
            }
            $enabled = $cached === 'yes';
        }
    } catch (\Throwable $e) {
        $enabled = false;
    }

    return $enabled = (bool) apply_filters('themeify_docs_ai_enabled', $enabled);
}

/* ---------- The docs the AI reads ---------- */

/** Plain text of block / HTML content, keeping headings and list items readable. */
function themeify_ai_plain_text($html)
{
    $html = do_blocks(strip_shortcodes((string) $html));
    $html = preg_replace('#<h([1-6])[^>]*>#i', "\n\n## ", $html);
    $html = preg_replace('#<li[^>]*>#i', "\n- ", $html);
    $html = preg_replace('#</(p|div|h[1-6]|ul|ol|table|tr|blockquote|details|summary|pre)>|<br\s*/?>#i', "\n", $html);
    $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8');
    $text = preg_replace("/[ \t]+/", ' ', $text);
    return trim(preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text));
}

/**
 * Every published doc in scope (one theme, or all themes) plus the theme pages,
 * as [id => [title, url, theme, path, text]]. Cached until a doc or theme page changes.
 */
function themeify_ai_docs($theme_id)
{
    global $wpdb;
    $theme_id = (int) $theme_id;
    $stamp    = $wpdb->get_var("SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type IN ('themeify_doc','themeify_theme_page')");
    $key      = 'themeify_ai_docs_' . md5($theme_id . '|' . $stamp . '|' . THEMEIFY_VERSION);
    $cached   = get_transient($key);
    if (is_array($cached)) {
        return $cached;
    }

    $themes = $theme_id ? array_filter([get_term($theme_id, 'doc_theme')], fn($t) => $t instanceof WP_Term)
        : get_terms(['taxonomy' => 'doc_theme', 'hide_empty' => false, 'orderby' => 'name']);
    $themes = is_wp_error($themes) ? [] : $themes;

    $docs = [];
    foreach ($themes as $theme) {
        $page_id = themeify_theme_page_id($theme->term_id);
        $page    = $page_id ? get_post($page_id) : null;
        if ($page && $page->post_status === 'publish' && trim($page->post_content) !== '') {
            $docs[$page->ID] = [
                'title' => sprintf(__('%s documentation', 'themeify'), $theme->name),
                'url'   => get_term_link($theme),
                'theme' => $theme->name,
                'path'  => '',
                'text'  => trim($theme->description . "\n\n" . themeify_ai_plain_text($page->post_content)),
            ];
        }

        $posts = get_posts([
            'post_type'   => 'themeify_doc',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => ['menu_order' => 'ASC', 'title' => 'ASC'],
            'tax_query'   => [['taxonomy' => 'doc_theme', 'field' => 'term_id', 'terms' => $theme->term_id]],
        ]);
        foreach ($posts as $post) {
            $text = themeify_ai_plain_text($post->post_content);
            if ($text === '') {
                continue;
            }
            $cats = get_the_terms($post, 'doc_category');
            $docs[$post->ID] = [
                'title' => get_the_title($post),
                'url'   => get_permalink($post),
                'theme' => $theme->name,
                'path'  => ($cats && !is_wp_error($cats)) ? implode(', ', wp_list_pluck($cats, 'name')) : '',
                'text'  => $text,
            ];
        }
    }

    set_transient($key, $docs, WEEK_IN_SECONDS);
    return $docs;
}

/**
 * The docs as one text block. When they don't all fit in the budget, the docs
 * that share the most words with the question are picked.
 */
function themeify_ai_docs_block($docs, $question)
{
    $budget = (int) apply_filters('themeify_ai_max_chars', 200000);
    $parts  = [];
    foreach ($docs as $id => $doc) {
        $attrs = sprintf('id="%d" title="%s" theme="%s"', $id, esc_attr($doc['title']), esc_attr($doc['theme']));
        if ($doc['path'] !== '') {
            $attrs .= ' category="' . esc_attr($doc['path']) . '"';
        }
        $parts[$id] = "<doc {$attrs}>\n{$doc['text']}\n</doc>";
    }
    if (array_sum(array_map('strlen', $parts)) <= $budget) {
        return implode("\n\n", $parts);
    }

    $words = array_filter(preg_split('/\W+/u', mb_strtolower($question)), fn($w) => mb_strlen($w) > 2);
    $score = [];
    foreach ($docs as $id => $doc) {
        $title      = mb_strtolower($doc['title']);
        $body       = mb_strtolower($doc['text']);
        $score[$id] = 0;
        foreach ($words as $w) {
            $score[$id] += substr_count($title, $w) * 5 + min(10, substr_count($body, $w));
        }
    }
    arsort($score);
    $out  = [];
    $used = 0;
    foreach (array_keys($score) as $id) {
        if ($used + strlen($parts[$id]) <= $budget) {
            $out[] = $parts[$id];
            $used += strlen($parts[$id]);
        }
    }
    return implode("\n\n", $out);
}

/* ---------- Asking the AI ---------- */

function themeify_ai_system_prompt()
{
    $site = wp_strip_all_tags(get_bloginfo('name'));
    return <<<PROMPT
You answer questions from customers of {$site} about its themes, on its documentation site. The documentation is in the <docs> element of the message; each <doc> has an id, a title, the theme it belongs to and sometimes a category.

How to answer:
- Answer only from these docs. If they do not cover the question, say so plainly in one or two sentences and suggest what to search for or to contact support. Do not guess settings, menu paths or features that the docs do not mention.
- Name settings and menu paths exactly as the docs write them, in bold, with arrows for paths (for example **Theme settings → Typography**).
- Lead with the direct answer. Use short sections with "##" or "###" headings only when the answer covers more than one area, bulleted lists for settings, and a "> **Note:**" or "> **Tip:**" line for an important caveat. Keep it as short as the question allows.
- When the visitor picked one theme, answer for that theme. When no theme is picked and the answer differs between themes, say which theme each part applies to, or ask which theme they use.
- Write Markdown without HTML and without links; the page lists the docs you used under the answer.
- End with one last line, exactly in this form, listing the ids of the docs you used, most useful first, at most five: SOURCES: 12, 34
  Write "SOURCES: none" when you used no doc.
PROMPT;
}

/**
 * Ask the site's AI. $history: earlier turns as [['q' => ..., 'a' => ...]].
 *
 * @return array{answer: string, sources: int[], docs: array}|WP_Error
 */
function themeify_ai_ask($question, $theme, $history = [])
{
    $docs = themeify_ai_docs($theme instanceof WP_Term ? $theme->term_id : 0);
    if (!$docs) {
        return new WP_Error('themeify_ai_no_docs', __('There are no published docs to answer from yet.', 'themeify'));
    }

    $prompt = "<docs>\n" . themeify_ai_docs_block($docs, $question) . "\n</docs>\n\n";
    if ($history) {
        $prompt .= "Earlier in this conversation:\n";
        foreach (array_slice($history, -3) as $turn) {
            $prompt .= "Visitor: {$turn['q']}\nYou: {$turn['a']}\n\n";
        }
    }
    $prompt .= ($theme instanceof WP_Term
        ? sprintf('The visitor picked the theme "%s".', $theme->name)
        : 'The visitor did not pick a theme (all themes).');
    $prompt .= "\n\nQuestion: " . $question;

    $text = wp_ai_client_prompt($prompt)
        ->using_system_instruction(themeify_ai_system_prompt())
        ->using_max_tokens(4000)
        ->generate_text();

    if (is_wp_error($text)) {
        error_log('[themeify AI] ' . $text->get_error_code() . ': ' . $text->get_error_message());
        return new WP_Error('themeify_ai_busy', __('The answer could not be written right now. Please try again.', 'themeify'));
    }

    // Split off the "SOURCES:" line.
    $sources = [];
    $answer  = trim((string) $text);
    if (preg_match('/^\s*\**SOURCES\**:\s*(.*)$/mi', $answer, $m, PREG_OFFSET_CAPTURE)) {
        preg_match_all('/\d+/', $m[1][0], $ids);
        $sources = array_values(array_unique(array_filter(array_map('intval', $ids[0]), fn($id) => isset($docs[$id]))));
        $answer  = trim(substr($answer, 0, $m[0][1]));
    }
    if ($answer === '') {
        return new WP_Error('themeify_ai_busy', __('The answer could not be written right now. Please try again.', 'themeify'));
    }

    return ['answer' => $answer, 'sources' => array_slice($sources, 0, 5), 'docs' => $docs];
}

/* ---------- Markdown → HTML for the answer ---------- */

function themeify_ai_inline($text)
{
    $text = esc_html($text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $text);
    return $text;
}

/** Small Markdown renderer for answers: headings, lists, quotes, code, rules, bold/italic/code. */
function themeify_ai_markdown($md)
{
    $lines = preg_split('/\R/', trim((string) $md));
    $html  = '';
    $para  = [];
    $list  = null; // 'ul' | 'ol'
    $quote = [];
    $code  = null;

    $flush = function () use (&$html, &$para, &$list, &$quote) {
        if ($para) {
            $html .= '<p>' . themeify_ai_inline(implode(' ', $para)) . '</p>';
            $para  = [];
        }
        if ($list) {
            $html .= "</{$list}>";
            $list  = null;
        }
        if ($quote) {
            $html .= '<blockquote><p>' . implode('<br>', array_map('themeify_ai_inline', $quote)) . '</p></blockquote>';
            $quote = [];
        }
    };

    foreach ($lines as $line) {
        if ($code !== null) {
            if (preg_match('/^\s*```/', $line)) {
                $html .= '<pre><code>' . esc_html(implode("\n", $code)) . '</code></pre>';
                $code  = null;
            } else {
                $code[] = $line;
            }
            continue;
        }
        if (preg_match('/^\s*```/', $line)) {
            $flush();
            $code = [];
        } elseif (trim($line) === '') {
            $flush();
        } elseif (preg_match('/^(#{1,4})\s+(.*)$/', $line, $m)) {
            $flush();
            $level = min(4, strlen($m[1]) + 1); // # → h2; the page already has an h1
            $html .= "<h{$level}>" . themeify_ai_inline($m[2]) . "</h{$level}>";
        } elseif (preg_match('/^\s*([-*_])(\s*\1){2,}\s*$/', $line)) {
            $flush();
            $html .= '<hr>';
        } elseif (preg_match('/^\s*>\s?(.*)$/', $line, $m)) {
            if ($para || $list) {
                $flush();
            }
            $quote[] = $m[1];
        } elseif (preg_match('/^\s*([-*+]|\d+[.)])\s+(.*)$/', $line, $m)) {
            $type = ctype_digit($m[1][0]) ? 'ol' : 'ul';
            if ($para || $quote || ($list && $list !== $type)) {
                $flush();
            }
            if (!$list) {
                $html .= "<{$type}>";
                $list  = $type;
            }
            $html .= '<li>' . themeify_ai_inline($m[2]) . '</li>';
        } elseif ($list && preg_match('/^\s{2,}\S/', $line)) {
            $html = preg_replace('#</li>$#', ' ' . themeify_ai_inline(trim($line)) . '</li>', $html); // continued list item
        } else {
            if ($list || $quote) {
                $flush();
            }
            $para[] = trim($line);
        }
    }
    if ($code !== null) {
        $html .= '<pre><code>' . esc_html(implode("\n", $code)) . '</code></pre>';
    }
    $flush();
    return wp_kses_post($html);
}

/* ---------- REST endpoint: POST /themeify/v1/ask ---------- */

add_action('rest_api_init', function () {
    register_rest_route('themeify/v1', '/ask', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true', // public, like the docs; limited per visitor below
        'callback'            => 'themeify_ai_rest_ask',
        'args'                => [
            'question' => ['type' => 'string', 'required' => true],
            'theme'    => ['type' => 'string', 'default' => ''],
            'history'  => ['type' => 'array', 'default' => []],
        ],
    ]);
});

function themeify_ai_rest_ask(WP_REST_Request $request)
{
    if (!themeify_ai_enabled()) {
        return new WP_Error('themeify_ai_off', __('AI answers are not available. Showing search results instead.', 'themeify'), ['status' => 404]);
    }

    $question = trim(sanitize_textarea_field($request['question']));
    if ($question === '' || mb_strlen($question) > 500) {
        return new WP_Error('themeify_ai_question', __('Ask a question of up to 500 characters.', 'themeify'), ['status' => 400]);
    }

    // Per-visitor limit (IP address, hashed) to keep AI costs in check.
    $limit  = max(1, (int) apply_filters('themeify_ai_per_hour', 30));
    $bucket = 'themeify_ai_rate_' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . wp_salt('nonce'));
    $count  = (int) get_transient($bucket);
    if ($count >= $limit) {
        return new WP_Error('themeify_ai_limit', __('You have asked a lot of questions. Please use the search results for now.', 'themeify'), ['status' => 429]);
    }
    set_transient($bucket, $count + 1, HOUR_IN_SECONDS);

    $theme = null;
    if ($request['theme'] !== '') {
        $theme = get_term_by('slug', sanitize_title($request['theme']), 'doc_theme') ?: null;
    }

    $history = [];
    foreach ((array) $request['history'] as $turn) {
        if (is_array($turn) && isset($turn['q'], $turn['a'])) {
            $history[] = [
                'q' => mb_substr(sanitize_textarea_field($turn['q']), 0, 500),
                'a' => mb_substr(sanitize_textarea_field($turn['a']), 0, 6000),
            ];
        }
    }

    $result = themeify_ai_ask($question, $theme, $history);
    if (is_wp_error($result)) {
        $result->add_data(['status' => 502]);
        return $result;
    }

    $sources = [];
    foreach ($result['sources'] as $id) {
        $doc       = $result['docs'][$id];
        $sources[] = ['title' => $doc['title'], 'url' => $doc['url'], 'theme' => $doc['theme']];
    }
    return rest_ensure_response([
        'html'     => themeify_ai_markdown($result['answer']),
        'markdown' => $result['answer'], // sent back as history for follow-up questions
        'sources'  => $sources,
    ]);
}
