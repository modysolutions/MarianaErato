<?php

namespace App\Hooks;

use Automattic\WooCommerce\Enums\OrderStatus;

class WooCommerce {
    use \App\Features\WooCommerce;
    use \App\Features\Template_Parser;

    var array $items;
    var ?\WC_Order $order;

    public function __construct() {
        $this->items = array();
        $this->order = null;
    }

    public function init(): void {
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

        add_filter('woocommerce_email_enabled_new_order',                 [$this, 'suppress_access_order_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', [$this, 'suppress_access_order_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order',  [$this, 'suppress_access_order_email'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_invoice',          [$this, 'suppress_access_order_email'], 10, 2);
    }

    public function suppress_access_order_email(bool $enabled, $order): bool {
        if ($order instanceof \WC_Order && $order->get_meta('_mm_sponsorship_access_order') === 'yes') {
            return false;
        }
        return $enabled;
    }

    public function template_redirect(): void {
        if (is_cart() && WC()->cart->get_cart_contents_count() > 0) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        if (is_product() && $this->get_posts_linked_to_product(get_the_ID())) {
            wp_safe_redirect($this->get_product_permalink_by_lang(get_the_ID()));
            exit;
        }
    }

    public function woocommerce_add_to_cart_validation(bool $passed): bool {
        wc_empty_cart();
        return $passed;
    }

    public function woocommerce_checkout_fields(array $fields): array {
        global $woocommerce;
        if (!$woocommerce) {
            return $fields;
        }
        if (!$woocommerce->cart) {
            return $fields;
        }
        $only_virtual = !$woocommerce->cart->needs_shipping();
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

    public function woocommerce_checkout_order_created($order): void {
        $items = $order->get_items();
        $product_id = reset($items)->get_product_id();
        $product_url = get_permalink($product_id);
        $order->update_meta_data('product_url', $product_url);
        $order->update_meta_data('product_id', $product_id);
    }

    public function wc_cc_bill_order_redirect(\WC_Order $order): void {
        $redirect_url = $order->get_view_order_url();
        if ($order->is_paid()) {
            $redirect_url = $this->get_order_url($order);
        }
        wp_safe_redirect($redirect_url);
    }

    public function woocommerce_thankyou_wc(int $order_id): void {
        if (!$order_id) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $cache_hash = $_GET['ch'] ?? false;
        if ($cache_hash && $order->is_paid()) {
            do_action('wc_cc_bill_order_redirect', $order);
        }
    }

    public function woocommerce_order_details_before_order_table(\WC_Order $order): void {
        $cache_hash = $_REQUEST['ch'] ?? false;
        if ($cache_hash && $order->is_paid()) {
            apply_filters('wc_cc_bill_set_order_status', OrderStatus::COMPLETED, $order);
            wp_safe_redirect($this->get_order_url($order));
            exit;
        }
    }

    public function wc_mm_assign_purchased_posts(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($order->get_status() !== OrderStatus::COMPLETED) {
            return;
        }

        if ($order->get_meta('_mm_sponsorship_processed') === 'yes') {
            return;
        }

        $items = $order->get_items();
        $product_id = reset($items)->get_product_id();
        $sponsorship_product_category = get_field('subscription_sponsorship_product_category', 'option');
        $this->order = $order;
        if ($sponsorship_product_category && has_term($sponsorship_product_category, 'product_cat', $product_id)) {
            $order->update_meta_data('_mm_sponsorship_processed', 'yes');
            $order->save();
            $this->assign_sponsorship_to_user($order->get_user_id(), $product_id);
        }
    }

    public function assign_sponsorship_to_user(int $user_id, int $product_id): void {
        $fields = get_fields($product_id);
        extract($fields);
        if($type_of_access !== 'none') {
            $this->{"set_{$type_of_access}_access"}($user_id, $fields);
        }

        if($private_gallery_status !== 'none') {
            $this->set_pg_content_access($user_id, $fields, 'gallery');
        }

        if($behind_the_video_status !== 'none') {
            $this->{"set_bts_{$behind_the_video_status}_access"}($user_id, $fields, 'bts');
        }

        if($early_access_status !== 'inactive') {
            $this->set_early_access($user_id, $fields, 'early');
        }

        $this->items = array_filter(array_unique($this->items));

        $this->process_thank_you_message($user_id, $product_id, $this->order?->get_id());
        $this->create_order_for_products($user_id);
    }

    public function set_content_access(int $user_id, array $fields): void {
        extract($fields);

        $paid_post_category = get_field('paid_post_category', 'option');
        $video_post_tag = get_field('video_post_tag', 'option');
        $gallery_post_tag = get_field('gallery_post_tag', 'option');

        $videos = $this->_get_protected_post($amount_of_videos ?? 0, $video_post_tag, $paid_post_category);
        $this->_get_products_from_posts($videos);

        $galleries = $this->_get_protected_post($amount_of_galleries ?? 0, $gallery_post_tag, $paid_post_category);
        $this->_get_products_from_posts($galleries);
    }

    public function set_pg_content_access(int $user_id, array $fields): void {
        extract($fields);

        $private_gallery_post_tag = get_field('private_gallery_post_tag', 'option');

        $private_galleries = $this->_get_protected_post($private_gallery_amount_of_gallery_pictures, $private_gallery_post_tag);
        $this->_get_products_from_posts($private_galleries);
    }

    public function set_bts_content_access(int $user_id, array $fields): void {
        extract($fields);

        $bts_post_tag = get_field('bts_post_tag', 'option');

        $behind_the_scenes = $this->_get_protected_post($videos_to_allow, $bts_post_tag);
        $this->_get_products_from_posts($behind_the_scenes);
    }

    public function set_time_access(int $user_id, array $fields): void {
        extract($fields);

        $paid_post_category = get_field('paid_post_category', 'option');
        $today = time();
        $content_until = strtotime("-$previous_content_for months", $today);
        $posts = $this->_get_protected_post(-1, null, $paid_post_category, $content_until);
        $this->_get_products_from_posts($posts);
    }

    public function set_pg_time_access(int $user_id, array $fields): void {
        extract($fields);

        $private_gallery_post_tag = get_field('private_gallery_post_tag', 'option');
        $today = time();
        $content_until = strtotime("-$private_gallery_amount_of_time months", $today);
        $posts = $this->_get_protected_post(-1, $private_gallery_post_tag, null, $content_until);
        $this->_get_products_from_posts($posts);
    }

    public function set_bts_time_access(int $user_id, array $fields): void {
        extract($fields);

        $bts_post_tag = get_field('bts_post_tag', 'option');
        $today = time();
        $content_until = strtotime("-$bts_amount_of_time months", $today);
        $posts = $this->_get_protected_post(-1, $bts_post_tag, null, $content_until);
        $this->_get_products_from_posts($posts);
    }

    public function set_early_access($user_id, $fields) : void {
        extract($fields);
        update_user_meta($user_id, 'me_early_access_to_blog_posts', $early_access_time_amount);
    }

    public function process_thank_you_message(int $user_id, int $product_id, ?int $order_id): void {
        $status = get_field('thank_you_message_status', $product_id);

        if ($status === 'enabled') {
            $subject_raw = get_field('thank_you_message_subject', $product_id);
            $content_raw = get_field('thank_you_message_content', $product_id);
            $video_url   = get_field('thank_you_message_thank_you_video_url', $product_id);

            $user = get_userdata($user_id);
            $data = array(
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'product_name' => get_the_title($product_id),
                'order_id' => $order_id,
                'video_url' => $video_url,
            );
            $subject = $this->parse($subject_raw, $data);
            $content = $this->parse($content_raw, $data);

            $current_user = get_userdata($user_id);
            $this->send_email($current_user->user_email, $subject, $content);
        }
    }

    public function create_order_for_products(int $user_id) : void {
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
        int $post_tag = null,
        int $post_category = null,
        int $date = null
    ) : array {
        $args = array(
            'posts_per_page' => $post_per_page,
            'post_type' => 'post',
            'fields' => 'ids',
            'tax_query' => array(),
        );

        if($post_tag) {
            $args['tax_query'][] = array(
                'taxonomy' => 'post_tag',
                'terms' => $post_tag,
            );
        }

        if($post_category) {
            $args['tax_query'][] = array(
                'taxonomy' => 'category',
                'terms' => $post_category,
            );
        }
        if($date) {
            $args['date_query'] = array(
                array(
                    'after' => array(
                        'year' => date('Y', $date),
                        'month' => date('m', $date),
                        'day' => date('d', $date),
                    ),
                    'inclusive' => true,
                ),
            );
        }
        return get_posts($args);
    }

    private function _get_products_from_posts(array $posts): void {
        $items = array();
        if ($posts) {
            foreach ($posts as $post_id) {
                $products_data = \Woocommerce_Pay_Per_Post_Helper::get_product_ids_by_post_id($post_id);
                if ($products_data['product_ids']) {
                    $items = array_merge($items, $products_data['product_ids']);
                }
            }
        }
        $items = array_filter($items, function($item){
            $language_info = apply_filters('wpml_post_language_details', null, $item);
            if ($language_info) {
                $language_code = $language_info['language_code'];
            } else {
                $language_code = 'en';
            }
            $current_language = apply_filters('wpml_current_language', NULL);
            return $language_code === $current_language;
        });

        $items = array_filter($items, function($item){
            $sponsorship_product_category = get_field('subscription_sponsorship_product_category', 'option');
            return !has_term($sponsorship_product_category, 'product_cat', $item);
        });

        $items = array_unique($items);
        sort($items);
        $this->items = array_merge($this->items, $items);
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

    public function woocommerce_get_checkout_order_received_url(string $order_received_url, \WC_Order $order): string {
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

    public function woocommerce_add_to_cart_redirect(string $url): string {
        return wc_get_checkout_url();
    }

    public function woocommerce_get_checkout_url(string $url): string {
        return apply_filters('wpml_permalink', $url, apply_filters('wpml_current_language', null));
    }
}
