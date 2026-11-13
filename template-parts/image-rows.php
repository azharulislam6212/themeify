<?php
/**
 * "Image with row" section: one card per ACF repeater row with heading, text and button on
 * the left and a screenshot on a green panel on the right. On wide screens the cards are
 * sticky, so each one slides over the previous card (assets/js/image-rows.js adds the
 * slight shrink of covered cards).
 * Data comes from the front page's ACF fields, see themeify_image_rows_acf().
 *
 * @var array $args ['section' => ['rows' => [['image', 'heading', 'text', 'button_label', 'button_url', 'button_target'], ...]]]
 */
if (!defined('ABSPATH')) {
    exit;
}

$rows = $args['section']['rows'] ?? [];
if (!$rows) {
    return;
}
?>
<section class="tf-ir" aria-label="<?php esc_attr_e('Our themes', 'themeify'); ?>">
    <div class="tf-ir__container">
        <div class="tf-ir__list" data-tf-ir>
            <?php foreach ($rows as $i => $row) :
                $url = $row['button_url'] ?: themeify_shop_url();
                ?>
                <article class="tf-ir__card" style="--tf-ir-i: <?php echo (int) $i; ?>">
                    <div class="tf-ir__body">
                        <?php if ($row['heading']) : ?>
                            <h2 class="tf-ir__title"><?php echo esc_html($row['heading']); ?></h2>
                        <?php endif; ?>

                        <?php if ($row['text']) : ?>
                            <div class="tf-ir__text"><?php echo wp_kses_post($row['text']); ?></div>
                        <?php endif; ?>

                        <?php if ($row['button_label']) : ?>
                            <a class="tf-ir__btn" href="<?php echo esc_url($url); ?>"<?php
                                echo $row['button_target'] === '_blank' ? ' target="_blank" rel="noopener"' : ''; ?>>
                                <span class="tf-ir__btn-dot" aria-hidden="true"></span>
                                <?php echo esc_html($row['button_label']); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="tf-ir__panel">
                        <?php if ($row['image']) : ?>
                            <?php echo wp_get_attachment_image($row['image'], 'full', false, [
                                'class'   => 'tf-ir__img',
                                'loading' => 'lazy',
                                'sizes'   => '(min-width: 1024px) 580px, calc(100vw - 80px)',
                            ]); ?>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
