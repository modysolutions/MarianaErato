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
        $linked_products = $this->get_posts_linked_to_product($product_id);
        $linked_post_id = count($linked_products) > 1 ? reset($linked_products)->ID : $product_id;

        $lang = apply_filters('wpml_current_language', null);

        $translated_post_id = apply_filters('wpml_object_id', $linked_post_id, 'post', true, $lang);
        return get_permalink($translated_post_id ?? $linked_post_id);
    }
}
