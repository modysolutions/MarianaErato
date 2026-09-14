<?php

namespace App\Hooks;

use Woocommerce_Pay_Per_Post_Helper;
use WP_Post;
use WP_Query;

class AnticipatedAccess
{
    private const USER_META = 'me_early_access_to_blog_posts';

    public function init(): void
    {
        add_action('pre_get_posts', [$this, 'include_future_posts_for_eligible_users']);
        add_filter('the_posts', [$this, 'filter_out_ineligible_future_posts'], 10, 2);
        add_filter('map_meta_cap', [$this, 'grant_read_for_anticipated_post'], 10, 4);
    }

    public static function user_anticipated_hours(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        return max(0, (int) get_user_meta($user_id, self::USER_META, true));
    }

    public static function post_publish_timestamp(int $post_id): ?int
    {
        $post = get_post($post_id);
        if (! $post) {
            return null;
        }

        $timestamp = (int) get_post_time('U', true, $post);

        return $timestamp > 0 ? $timestamp : null;
    }

    public static function user_can_view_anticipated_post(int $user_id, int $post_id): bool
    {
        $hours = self::user_anticipated_hours($user_id);
        if ($hours <= 0) {
            return false;
        }

        $publish = self::post_publish_timestamp($post_id);
        if ($publish === null) {
            return false;
        }

        $now = time();
        if ($now >= $publish) {
            return false;
        }
        if ($now < $publish - $hours * HOUR_IN_SECONDS) {
            return false;
        }

        return self::post_has_paid_linked_product($post_id);
    }

    public static function resolve_release_timestamp(int $product_id): ?int
    {
        $linked = self::posts_linked_to_product_in_current_language($product_id);
        if (! $linked) {
            return null;
        }

        $post = reset($linked);

        return (int) get_post_time('U', true, $post) ?: null;
    }

    public static function post_has_paid_linked_product(int $post_id): bool
    {
        if (! class_exists(Woocommerce_Pay_Per_Post_Helper::class)) {
            return false;
        }

        $product_ids = Woocommerce_Pay_Per_Post_Helper::get_product_ids_by_post_id($post_id)['product_ids'] ?? [];
        $lang = apply_filters('wpml_current_language', null);

        foreach ((array) $product_ids as $product_id) {
            $product_id = (int) $product_id;
            if (! $product_id) {
                continue;
            }
            if (self::product_has_sponsorship_category($product_id)) {
                continue;
            }
            if ($lang) {
                $details = apply_filters('wpml_post_language_details', null, $product_id);
                $product_lang = $details['language_code'] ?? $lang;
                if ($product_lang !== $lang) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }

    public static function product_has_sponsorship_category(int $product_id): bool
    {
        $sponsorship_category = (int) get_field('subscription_sponsorship_product_category', 'option');
        if (! $sponsorship_category) {
            return false;
        }

        return has_term($sponsorship_category, 'product_cat', $product_id);
    }

    public function include_future_posts_for_eligible_users(WP_Query $query): void
    {
        if (is_admin()) {
            return;
        }
        if (! is_user_logged_in()) {
            return;
        }
        if (self::user_anticipated_hours(get_current_user_id()) <= 0) {
            return;
        }
        if (! $this->query_targets_posts($query)) {
            return;
        }

        $status = $query->get('post_status');
        if (empty($status)) {
            $query->set('post_status', ['publish', 'future']);

            return;
        }
        if ($status === 'any') {
            return;
        }

        $status = (array) $status;
        if (! in_array('future', $status, true)) {
            $status[] = 'future';
            $query->set('post_status', $status);
        }
    }

    public function filter_out_ineligible_future_posts(array $posts, WP_Query $query): array
    {
        if (is_admin() || ! $posts) {
            return $posts;
        }

        $user_id = is_user_logged_in() ? get_current_user_id() : 0;

        return array_values(array_filter($posts, static function ($post) use ($user_id) {
            if (! $post instanceof WP_Post) {
                return true;
            }
            if ($post->post_type !== 'post' || $post->post_status !== 'future') {
                return true;
            }

            return $user_id > 0 && self::user_can_view_anticipated_post($user_id, (int) $post->ID);
        }));
    }

    public function grant_read_for_anticipated_post(array $caps, string $cap, int $user_id, array $args): array
    {
        if ($cap !== 'read_post') {
            return $caps;
        }
        if (empty($args[0])) {
            return $caps;
        }

        $post = get_post((int) $args[0]);
        if (! $post || $post->post_type !== 'post' || $post->post_status !== 'future') {
            return $caps;
        }

        if (! self::user_can_view_anticipated_post($user_id, (int) $post->ID)) {
            return $caps;
        }

        return ['read'];
    }

    private function query_targets_posts(WP_Query $query): bool
    {
        $post_type = $query->get('post_type');
        if (empty($post_type)) {
            return true;
        }
        if ($post_type === 'any') {
            return true;
        }
        if (is_string($post_type)) {
            return $post_type === 'post';
        }
        if (is_array($post_type)) {
            return in_array('post', $post_type, true);
        }

        return false;
    }

    private static function posts_linked_to_product_in_current_language(int $product_id): array
    {
        $args = [
            'post_type' => 'post',
            'post_status' => ['publish', 'future'],
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'wc_pay_per_post_product_ids',
                    'value' => '"'.$product_id.'"',
                    'compare' => 'LIKE',
                ],
            ],
            'orderby' => 'date',
            'order' => 'ASC',
        ];

        $lang = apply_filters('wpml_current_language', null);
        if ($lang) {
            $args['lang'] = $lang;
        }

        $posts = get_posts($args);
        if ($lang) {
            $posts = array_values(array_filter($posts, static function ($post) use ($lang): bool {
                if (! $post instanceof WP_Post) {
                    return false;
                }
                $details = apply_filters('wpml_post_language_details', null, $post->ID);

                return ($details['language_code'] ?? $lang) === $lang;
            }));
        }

        return $posts;
    }
}
