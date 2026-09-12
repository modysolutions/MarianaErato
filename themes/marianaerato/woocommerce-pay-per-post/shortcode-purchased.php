<?php

/** @noinspection PhpUndefinedVariableInspection */

use Elementor\Core\Files\CSS\Post as ElementorCssPost;
use Elementor\Plugin as ElementorPlugin;

$private_gallery_post_tag = get_field('private_gallery_post_tag', 'option');
$bts_post_tag = get_field('bts_post_tag', 'option');

$lang = apply_filters('wpml_current_language', null);
$purchased_page_id = (int) apply_filters(
    'wpml_object_id',
    (int) get_field('field_purchased_page', 'option'),
    'page',
    true,
    $lang
);
$exclusive_page_id = (int) apply_filters(
    'wpml_object_id',
    (int) get_field('field_exclusive_content_page', 'option'),
    'page',
    true,
    $lang
);

$page_id = get_the_ID();

$render_upsell = static function (): void {
    $product_template = (int) get_field('pay_per_post_product_template', 'option');
    if (! $product_template || ! did_action('elementor/loaded')) {
        return;
    }
    (new ElementorCssPost($product_template))->enqueue();
    echo ElementorPlugin::instance()->frontend->get_builder_content_for_display($product_template);
};

$posts = array_filter($purchased, function ($post) use (
    $private_gallery_post_tag,
    $bts_post_tag,
    $page_id,
    $purchased_page_id,
    $exclusive_page_id,
    $lang
) {
    $id = $post->ID;

    $language_info = apply_filters('wpml_post_language_details', null, $id);
    $language_code = $language_info['language_code'] ?? 'en';
    if ($language_code !== $lang) {
        return false;
    }

    $is_exclusive_content = has_term($private_gallery_post_tag, 'post_tag', $id)
        || has_term($bts_post_tag, 'post_tag', $id);

    if ($page_id === $purchased_page_id) {
        return ! $is_exclusive_content;
    }
    if ($page_id === $exclusive_page_id) {
        return $is_exclusive_content;
    }

    return true;
});

usort($posts, static fn ($a, $b) => strcmp($b->last_purchase_date ?? '', $a->last_purchase_date ?? ''));
?>
<div class="mm-purchased">
    <?php if ($posts) { ?>
        <h3>
            <?php if ($page_id === $exclusive_page_id) {
                esc_html_e('Exclusive Content', APP_THEME_DOMAIN);
            } else {
                esc_html_e('Purchased Content', APP_THEME_DOMAIN);
            } ?>
        </h3>
        <div class="mm-purchased__list">
            <?php foreach ($posts as $post) {
                $permalink = get_permalink($post->ID);
                $post_thumbnail = get_the_post_thumbnail_url($post->ID);
                ?>
                <div class="mm-purchased__list__item <?php echo esc_attr(get_post_type($post->ID)); ?>">
                    <div class="thumbnail" style="background-image: url(<?php echo esc_url($post_thumbnail); ?>);"></div>
                    <div class="info">
                        <a href="<?php echo esc_url($permalink); ?>">
                            <?php echo esc_html($post->post_title); ?>
                        </a>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } else {
        $render_upsell();
    } ?>
</div>
