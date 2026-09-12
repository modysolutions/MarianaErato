<?php

namespace App\Hooks;

use App\Features\Template_Parser;
use Automattic\WooCommerce\Enums\OrderStatus;

class WooCommerce
{
    use \App\Features\WooCommerce;
    use Template_Parser;

    private const SPONSORSHIP_GRANTED_POSTS_META = 'me_sponsorship_access_post_ids';

    private const SPONSORSHIP_GRANTED_POST_DATES_META = 'me_sponsorship_access_post_dates';

    public array $items;

    public ?\WC_Order $order;

    private ?string $sponsorship_language;

    public function __construct()
    {
        $this->items = [];
        $this->order = null;
        $this->sponsorship_language = null;
    }

    public function init(): void
    {
        add_action('template_redirect', [$this, 'template_redirect']);
        add_action('woocommerce_checkout_order_created', [$this, 'woocommerce_checkout_order_created'], 10, 2);
        add_action('wc_cc_bill_order_redirect', [$this, 'wc_cc_bill_order_redirect'], 10, 3);
        add_action('woocommerce_thankyou_wc_gateway_ccbill', [$this, 'woocommerce_thankyou_wc']);
        add_action('woocommerce_order_details_before_order_table', [$this, 'woocommerce_order_details_before_order_table']);
        add_action('wc_mm_assign_purchased_posts', [$this, 'wc_mm_assign_purchased_posts']);
        add_action('woocommerce_order_status_changed', [$this, 'wc_mm_assign_purchased_posts']);

        add_filter('woocommerce_add_to_cart_validation', [$this, 'woocommerce_add_to_cart_validation'], 9999);
        add_filter('woocommerce_checkout_fields', [$this, 'woocommerce_checkout_fields']);
        add_filter('wc_cc_bill_set_order_status', [$this, 'wc_cc_bill_set_order_status'], 10, 3);
        add_filter('woocommerce_get_checkout_order_received_url',
            [$this, 'woocommerce_get_checkout_order_received_url'], 10, 2);
        add_filter('woocommerce_add_to_cart_redirect', [$this, 'woocommerce_add_to_cart_redirect']);
        add_filter('woocommerce_get_checkout_url', [$this, 'woocommerce_get_checkout_url']);

        add_filter('woocommerce_email_enabled_new_order', [$this, 'suppress_access_order_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', [$this, 'suppress_access_order_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', [$this, 'suppress_access_order_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_invoice', [$this, 'suppress_access_order_email'], 10, 2);
        add_filter('wc_pay_per_post_force_bypass_paywall', [$this, 'wc_pay_per_post_force_bypass_paywall']);
    }

    public function suppress_access_order_email(bool $enabled, $order): bool
    {
        if ($order instanceof \WC_Order && $order->get_meta('_mm_sponsorship_access_order') === 'yes') {
            return false;
        }

        return $enabled;
    }

    public function template_redirect(): void
    {
        if (is_cart() && WC()->cart->get_cart_contents_count() > 0) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        if (is_product() && $this->get_posts_linked_to_product(get_the_ID())) {
            wp_safe_redirect($this->get_product_permalink_by_lang(get_the_ID()));
            exit;
        }
    }

    public function woocommerce_add_to_cart_validation(bool $passed): bool
    {
        wc_empty_cart();

        return $passed;
    }

    public function woocommerce_checkout_fields(array $fields): array
    {
        global $woocommerce;
        if (! $woocommerce) {
            return $fields;
        }
        if (! $woocommerce->cart) {
            return $fields;
        }
        $only_virtual = ! $woocommerce->cart->needs_shipping();
        if ($only_virtual) {
            unset($fields['billing']['billing_company']);
            unset($fields['billing']['billing_address_1']);
            unset($fields['billing']['billing_address_2']);
            unset($fields['billing']['billing_city']);
            unset($fields['billing']['billing_postcode']);
            unset($fields['billing']['billing_country']);
            unset($fields['billing']['billing_state']);
            unset($fields['billing']['billing_phone']);
        }

        return $fields;
    }

    public function woocommerce_checkout_order_created($order): void
    {
        $items = $order->get_items();
        $product_id = reset($items)->get_product_id();
        $product_url = get_permalink($product_id);
        $order->update_meta_data('product_url', $product_url);
        $order->update_meta_data('product_id', $product_id);
    }

    public function wc_cc_bill_order_redirect(\WC_Order $order): void
    {
        $redirect_url = $order->get_view_order_url();
        if ($order->is_paid()) {
            $redirect_url = $this->get_order_url($order);
        }
        wp_safe_redirect($redirect_url);
    }

    public function woocommerce_thankyou_wc(int $order_id): void
    {
        if (! $order_id) {
            return;
        }
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }
        $cache_hash = $_GET['ch'] ?? false;
        if ($cache_hash && $order->is_paid()) {
            do_action('wc_cc_bill_order_redirect', $order);
        }
    }

    public function woocommerce_order_details_before_order_table(\WC_Order $order): void
    {
        $cache_hash = $_REQUEST['ch'] ?? false;
        if ($cache_hash && $order->is_paid()) {
            apply_filters('wc_cc_bill_set_order_status', OrderStatus::COMPLETED, $order);
            wp_safe_redirect($this->get_order_url($order));
            exit;
        }
    }

    public function wc_mm_assign_purchased_posts(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        if ($order->get_status() !== OrderStatus::COMPLETED) {
            return;
        }

        if ($order->get_meta('_mm_sponsorship_access_order') === 'yes') {
            return;
        }

        if ($order->get_meta('_mm_sponsorship_processed') === 'yes') {
            return;
        }

        $items = $order->get_items();
        $product_id = reset($items)->get_product_id();
        $sponsorship_product_category = get_field('subscription_sponsorship_product_category', 'option');
        $this->order = $order;
        $this->sponsorship_language = $this->get_language_code($product_id);
        if ($sponsorship_product_category && $this->product_has_sponsorship_category($product_id)) {
            $order->update_meta_data('_mm_sponsorship_processed', 'yes');
            $order->save();
            $this->assign_sponsorship_to_user($order->get_user_id(), $product_id);
        }
    }

    public function assign_sponsorship_to_user(int $user_id, int $product_id): void
    {
        $fields = get_fields($product_id);
        extract($fields);
        if ($type_of_access !== 'none') {
            $this->{"set_{$type_of_access}_access"}($user_id, $fields);
        }

        if ($private_gallery_status !== 'none') {
            $this->set_pg_content_access($user_id, $fields, 'gallery');
        }

        if ($behind_the_video_status !== 'none') {
            $this->{"set_bts_{$behind_the_video_status}_access"}($user_id, $fields, 'bts');
        }

        if ($early_access_status !== 'inactive') {
            $this->set_early_access($user_id, $fields, 'early');
        }

        $this->items = array_filter(array_unique($this->items));

        $this->process_thank_you_message($user_id, $product_id, $this->order?->get_id());
        $this->create_order_for_products($user_id);
    }

    public function set_content_access(int $user_id, array $fields): void
    {
        extract($fields);

        $paid_post_category = get_field('paid_post_category', 'option');
        $video_post_tag = get_field('video_post_tag', 'option');
        $gallery_post_tag = get_field('gallery_post_tag', 'option');
        $lang = $this->get_sponsorship_language();

        $videos = $this->_get_protected_post($amount_of_videos ?? 0, $video_post_tag, $paid_post_category, null, $lang);
        $this->_get_products_from_posts($videos);

        $galleries = $this->_get_protected_post($amount_of_galleries ?? 0, $gallery_post_tag, $paid_post_category, null, $lang);
        $this->_get_products_from_posts($galleries);
    }

    public function set_pg_content_access(int $user_id, array $fields): void
    {
        extract($fields);

        $private_gallery_post_tag = get_field('private_gallery_post_tag', 'option');
        $lang = $this->get_sponsorship_language();

        $private_galleries = $this->_get_protected_post(
            $private_gallery_amount_of_gallery_pictures,
            $private_gallery_post_tag,
            null,
            null,
            $lang
        );
        $this->grant_sponsorship_post_access($user_id, $private_galleries);
        $this->_get_products_from_posts($private_galleries);
    }

    public function set_bts_content_access(int $user_id, array $fields): void
    {
        extract($fields);

        $bts_post_tag = get_field('bts_post_tag', 'option');
        $lang = $this->get_sponsorship_language();

        $behind_the_scenes = $this->_get_protected_post($videos_to_allow, $bts_post_tag, null, null, $lang);
        $this->grant_sponsorship_post_access($user_id, $behind_the_scenes);
        $this->_get_products_from_posts($behind_the_scenes);
    }

    public function set_time_access(int $user_id, array $fields): void
    {
        extract($fields);

        $paid_post_category = get_field('paid_post_category', 'option');
        $today = time();
        $content_until = strtotime("-$previous_content_for months", $today);
        $posts = $this->_get_protected_post(-1, null, $paid_post_category, $content_until, $this->get_sponsorship_language());
        $this->_get_products_from_posts($posts);
    }

    public function set_pg_time_access(int $user_id, array $fields): void
    {
        extract($fields);

        $private_gallery_post_tag = get_field('private_gallery_post_tag', 'option');
        $today = time();
        $content_until = strtotime("-$private_gallery_amount_of_time months", $today);
        $posts = $this->_get_protected_post(
            -1,
            $private_gallery_post_tag,
            null,
            $content_until,
            $this->get_sponsorship_language()
        );
        $this->grant_sponsorship_post_access($user_id, $posts);
        $this->_get_products_from_posts($posts);
    }

    public function set_bts_time_access(int $user_id, array $fields): void
    {
        extract($fields);

        $bts_post_tag = get_field('bts_post_tag', 'option');
        $today = time();
        $content_until = strtotime("-$bts_amount_of_time months", $today);
        $posts = $this->_get_protected_post(-1, $bts_post_tag, null, $content_until, $this->get_sponsorship_language());
        $this->grant_sponsorship_post_access($user_id, $posts);
        $this->_get_products_from_posts($posts);
    }

    public function set_early_access($user_id, $fields): void
    {
        extract($fields);
        update_user_meta($user_id, 'me_early_access_to_blog_posts', $early_access_time_amount);
    }

    public function process_thank_you_message(int $user_id, int $product_id, ?int $order_id): void
    {
        $status = get_field('thank_you_message_status', $product_id);

        if ($status === 'enabled') {
            $subject_raw = get_field('thank_you_message_subject', $product_id);
            $content_raw = get_field('thank_you_message_content', $product_id);
            $video_url = get_field('thank_you_message_thank_you_video_url', $product_id);

            $user = get_userdata($user_id);
            $data = [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'product_name' => get_the_title($product_id),
                'order_id' => $order_id,
                'video_url' => $video_url,
            ];
            $subject = $this->parse($subject_raw, $data);
            $content = $this->parse($content_raw, $data);

            $current_user = get_userdata($user_id);
            $this->send_email($current_user->user_email, $subject, $content);
        }
    }

    public function create_order_for_products(int $user_id): void
    {
        $new_order = wc_create_order();
        foreach ($this->items as $product_id) {
            $new_order->add_product(wc_get_product($product_id), 1);
        }

        $new_order->set_customer_id($user_id);
        $new_order->calculate_totals();

        $subtotal = $new_order->get_subtotal();

        $new_order->set_billing($this->order->get_address('billing'));
        $new_order->set_billing_first_name($this->order->get_billing_first_name());
        $new_order->set_billing_last_name($this->order->get_billing_last_name());

        $new_order->set_discount_total($subtotal);
        $new_order->set_discount_tax(0);
        $new_order->set_cart_tax(0);
        $new_order->set_total(0);
        $new_order->update_meta_data('_mm_sponsorship_access_order', 'yes');
        $new_order->update_meta_data('_mm_sponsorship_source_order_id', $this->order->get_id());
        $new_order->update_status(OrderStatus::COMPLETED, __('Manual 100% discount order.'));
        $new_order->save();
    }

    private function _get_protected_post(
        int $post_per_page,
        ?int $post_tag = null,
        ?int $post_category = null,
        ?int $date = null,
        ?string $lang = null
    ): array {
        if ($lang) {
            $post_tag = $this->translate_taxonomy_id($post_tag, 'post_tag', $lang);
            $post_category = $this->translate_taxonomy_id($post_category, 'category', $lang);
        }

        $args = [
            'posts_per_page' => -1,
            'post_type' => 'post',
            'post_status' => 'publish',
            'fields' => 'ids',
            'orderby' => [
                'date' => 'DESC',
                'ID' => 'DESC',
            ],
            'suppress_filters' => false,
            'tax_query' => [],
        ];

        if ($lang) {
            $args['lang'] = $lang;
        }

        if ($post_tag) {
            $args['tax_query'][] = [
                'taxonomy' => 'post_tag',
                'terms' => $post_tag,
            ];
        }

        if ($post_category) {
            $args['tax_query'][] = [
                'taxonomy' => 'category',
                'terms' => $post_category,
            ];
        }
        if ($date) {
            $args['date_query'] = [
                [
                    'after' => [
                        'year' => date('Y', $date),
                        'month' => date('m', $date),
                        'day' => date('d', $date),
                    ],
                    'inclusive' => true,
                ],
            ];
        }
        $posts = get_posts($args);
        if ($lang) {
            $posts = array_filter($posts, function ($post_id) use ($lang) {
                return $this->get_language_code((int) $post_id) === $lang;
            });
        }
        if ($post_per_page > 0) {
            $posts = array_slice($posts, 0, $post_per_page);
        }

        return array_values($posts);
    }

    private function _get_products_from_posts(array $posts): void
    {
        $items = [];
        if ($posts) {
            foreach ($posts as $post_id) {
                $products_data = \Woocommerce_Pay_Per_Post_Helper::get_product_ids_by_post_id($post_id);
                if ($products_data['product_ids']) {
                    $items = array_merge($items, $products_data['product_ids']);
                }
            }
        }
        $lang = $this->get_sponsorship_language();
        $items = array_filter($items, function ($item) use ($lang) {
            return $this->get_language_code((int) $item) === $lang;
        });

        $items = array_filter($items, function ($item) {
            return ! $this->product_has_sponsorship_category((int) $item);
        });

        $items = array_unique($items);
        sort($items);
        $this->items = array_merge($this->items, $items);
    }

    public function wc_pay_per_post_force_bypass_paywall(bool $show_paywall): bool
    {
        if (! $show_paywall || ! is_user_logged_in()) {
            return $show_paywall;
        }

        $post_id = get_the_ID();
        if (! $post_id) {
            return $show_paywall;
        }

        return $this->user_has_sponsorship_post_access(get_current_user_id(), (int) $post_id) ? false : $show_paywall;
    }

    private function grant_sponsorship_post_access(int $user_id, array $post_ids): void
    {
        $post_ids = array_values(array_unique(array_map('intval', array_filter($post_ids))));
        if (! $post_ids) {
            return;
        }

        $existing = get_user_meta($user_id, self::SPONSORSHIP_GRANTED_POSTS_META, true);
        $existing = is_array($existing) ? array_map('intval', $existing) : [];

        $dates = get_user_meta($user_id, self::SPONSORSHIP_GRANTED_POST_DATES_META, true);
        $dates = is_array($dates) ? $dates : [];
        $now = current_time('mysql');

        foreach ($post_ids as $post_id) {
            $existing[] = $post_id;
            if (empty($dates[$post_id])) {
                $dates[$post_id] = $now;
            }
        }

        update_user_meta($user_id, self::SPONSORSHIP_GRANTED_POSTS_META, array_values(array_unique($existing)));
        update_user_meta($user_id, self::SPONSORSHIP_GRANTED_POST_DATES_META, $dates);
    }

    private function user_has_sponsorship_post_access(int $user_id, int $post_id): bool
    {
        $post_ids = get_user_meta($user_id, self::SPONSORSHIP_GRANTED_POSTS_META, true);
        if (! is_array($post_ids)) {
            return false;
        }

        return in_array($post_id, array_map('intval', $post_ids), true);
    }

    private function get_sponsorship_language(): string
    {
        return $this->sponsorship_language ?: (string) apply_filters('wpml_current_language', null);
    }

    private function get_language_code(int $post_id): string
    {
        $language_info = apply_filters('wpml_post_language_details', null, $post_id);

        return $language_info['language_code'] ?? 'en';
    }

    private function translate_taxonomy_id(?int $term_id, string $taxonomy, string $lang): ?int
    {
        if (! $term_id) {
            return null;
        }

        return (int) apply_filters('wpml_object_id', $term_id, $taxonomy, true, $lang);
    }

    private function product_has_sponsorship_category(int $product_id): bool
    {
        $sponsorship_product_category = (int) get_field('subscription_sponsorship_product_category', 'option');
        if (! $sponsorship_product_category) {
            return false;
        }

        $category_ids = [$sponsorship_product_category];
        $product_ids = [$product_id];

        foreach ($this->get_language_codes() as $language_code) {
            $category_ids[] = $this->translate_taxonomy_id(
                $sponsorship_product_category,
                'product_cat',
                $language_code
            );
            $product_ids[] = (int) apply_filters('wpml_object_id', $product_id, 'product', true, $language_code);
        }

        $category_ids = array_filter(array_unique($category_ids));
        $product_ids = array_filter(array_unique($product_ids));

        foreach ($product_ids as $translated_product_id) {
            if (has_term($category_ids, 'product_cat', $translated_product_id)) {
                return true;
            }
        }

        return false;
    }

    private function get_language_codes(): array
    {
        $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
        if (! is_array($languages)) {
            return array_filter(array_unique([$this->get_sponsorship_language()]));
        }

        return array_keys($languages);
    }

    public function wc_cc_bill_set_order_status(
        bool $status,
        \WC_Order $order,
        ?string $transaction_id = '',
    ): bool {
        if ($status) {
            $order->add_order_note(__('Payment completed', 'marianaerato'));
            $order->payment_complete($transaction_id);
            $order->set_status(OrderStatus::COMPLETED);
            do_action('wc_mm_assign_purchased_posts', $order->get_id());
        }

        return $status;
    }

    public function woocommerce_get_checkout_order_received_url(string $order_received_url, \WC_Order $order): string
    {
        if ($order->get_status() === OrderStatus::COMPLETED) {
            wp_update_post([
                'ID' => $order->get_id(),
                'post_status' => OrderStatus::COMPLETED,
            ]);
        }
        $order_received_url =
            apply_filters('wpml_permalink', $order_received_url, apply_filters('wpml_current_language', null));

        return add_query_arg('ch', time(), $order_received_url);
    }

    public function woocommerce_add_to_cart_redirect(string $url): string
    {
        return wc_get_checkout_url();
    }

    public function woocommerce_get_checkout_url(string $url): string
    {
        return apply_filters('wpml_permalink', $url, apply_filters('wpml_current_language', null));
    }
}
