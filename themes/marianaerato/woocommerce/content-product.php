<?php

use App\Hooks\AnticipatedAccess;

defined('ABSPATH') || exit;

global $product, $post;
$product_id = get_the_ID();
// Check if the product is a valid WooCommerce product and ensure its visibility before proceeding.
if (! is_a($product, WC_Product::class) || ! $product->is_visible()) {
    return;
}
$regular_price = get_post_meta($product_id, '_regular_price', true);
$sale_price = get_post_meta($product_id, '_sale_price', true);
$current_price = number_format(get_post_meta($product_id, '_price', true), 2, ',', '.');
$currency = get_woocommerce_currency();
$currency_symbol = get_woocommerce_currency_symbol($currency) ?? '';
$image = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'single-post-thumbnail');
if (str_contains($image[0] ?? '', 'marianaerato.com')) {
    $image = str_replace('wordpress/', '', $image);
}
$text = get_field('buy_this_post_text', 'option') ?? 'Buy this post for %s';
$color = get_field('sales_text_color', $product_id);
$current_time = time();
$is_available = true;
$release_timestamp = null;

$linked_release_timestamp = AnticipatedAccess::resolve_release_timestamp((int) $product_id);
if ($linked_release_timestamp !== null) {
    $release_timestamp = $linked_release_timestamp;
} else {
    $non_sponsors_available_date = get_field('non_sponsors_available_date', $product_id, false);
    if ($non_sponsors_available_date) {
        $release_timestamp = strtotime($non_sponsors_available_date);
    }
}

if ($release_timestamp) {
    $early_access_hours = AnticipatedAccess::user_anticipated_hours(get_current_user_id());
    if ($early_access_hours > 0) {
        $user_allowed_timestamp = $release_timestamp - $early_access_hours * HOUR_IN_SECONDS;
        $is_available = $current_time >= $user_allowed_timestamp;
    } else {
        $is_available = $current_time >= $release_timestamp;
    }
} else {
    $release_timestamp = $current_time;
}
?>
<style>.mm-background { background-image: url(<?php echo $image[0] ?? ''; ?>); }</style>
<div <?php wc_product_class('mm--product_content', $product); ?>>
  <div class='mm-background'>&nbsp;</div>
  <div class='mm-background-overlay'>&nbsp;</div>
  <h4 class='mm-buy_this'>
      <?php
      echo sprintf(__($text, APP_THEME_DOMAIN), $currency_symbol.$current_price);

if ($is_available) {
    $checkout_url = get_field('link_buy_now_button_url', $product_id);
    $checkout_text = __('Buy now', APP_THEME_DOMAIN);
} else {
    $checkout_url = 'javascript:void(0)';
    $formatted_date = date_i18n('F jS, Y', $release_timestamp);
    $available_on_text = get_field('non_sponsor_sales_text', 'option') ?? 'Available on %s';
    $checkout_text = sprintf(__($available_on_text, APP_THEME_DOMAIN), $formatted_date);
}
?>
  </h4>
  <a href="<?php echo $checkout_url ?>" class="mm-add_to_cart"><?php echo $checkout_text ?></a>
</div>
<div class='mm-excerpt'>
  <?php echo $post->post_excerpt; ?>
</div>
<?php
$product_template = get_field('pay_per_post_product_template', 'option');
if ($product_template) {
    echo do_shortcode('[elementor-template id="'.$product_template.'"]');
}
?>
<script>
    (function ($) {
        $('body').on('click', '.mm-add_to_cart', function () {
            localStorage.setItem('mm_redirect_to', '<?php echo get_permalink($post->ID); ?>');
        })
    })(jQuery)
</script>
