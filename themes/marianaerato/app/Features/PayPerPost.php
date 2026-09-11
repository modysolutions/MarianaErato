<?php

namespace App\Features;

trait PayPerPost
{
    public function get_posts_linked_to_product($product_id): array
    {
        $args = [
            'post_type'  => 'post',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key'     => 'wc_pay_per_post_product_ids',
                    'value'   => '"' . $product_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
            'posts_per_page' => -1,
        ];

        return get_posts($args);
    }

    public function get_product_linked_to_post($post_id): array
    {
        $args = [
            'post_type'  => 'post',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key'     => 'wc_pay_per_post_product_ids',
                    'value'   => '"' . $post_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
            'posts_per_page' => -1,
        ];

        return get_posts($args);
    }

    public function get_product_permalink_by_lang($product_id): string
    {
        $lang = apply_filters('wpml_current_language', null);

        $sponsorship_product_category = get_field('subscription_sponsorship_product_category', 'option');
        if ($sponsorship_product_category && has_term($sponsorship_product_category, 'product_cat', $product_id)) {
            $sponsorship_page_id = get_field('sponsorship_purchase_redirect_page', 'option');
            if ($sponsorship_page_id) {
                $translated_page_id = apply_filters('wpml_object_id', $sponsorship_page_id, 'page', true, $lang);
                return get_permalink($translated_page_id ?? $sponsorship_page_id);
            }
            return wc_get_page_permalink('myaccount');
        }

        $linked_products = $this->get_posts_linked_to_product($product_id);
        $linked_post_id = count($linked_products) > 1 ? reset($linked_products)->ID : $product_id;

        $translated_post_id = apply_filters('wpml_object_id', $linked_post_id, 'post', true, $lang);
        return get_permalink($translated_post_id ?? $linked_post_id);
    }
}
