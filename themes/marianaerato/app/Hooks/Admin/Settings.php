<?php

namespace App\Hooks\Admin;

class Settings {
    public function init(): void {
        add_action('acf/init', array($this, 'acf_init'));
        add_action('acf/include_fields', array($this, 'acf_include_fields'));
    }

    public function acf_init(): void {
        if (!function_exists('acf_add_options_page')) {
            return;
        }
        acf_add_options_page(array(
            'page_title' => __('Mariana Erato', APP_THEME_DOMAIN),
            'menu_slug' => 'mariana-erato',
            'parent_slug' => 'options-general.php',
            'position' => 0,
            'redirect' => false,
        ));
    }

    public function acf_include_fields(): void {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        extract($this->_get_store_tab());
        extract($this->_get_pages_tab());
        extract($this->_get_blog_tab());
        extract($this->_get_categories_tags_tab());
        extract($this->_get_elementor_tab());

        acf_add_local_field_group(array(
            'key' => 'group_67910896cb2dc',
            'title' => __('Settings', APP_THEME_DOMAIN),
            'fields' => array(
                $store_tab,
                $buy_post_text_field,
                $non_sponsor_text_field,
                $pages_tab,
                $purchased_page_field,
                $exclusive_content_page_field,
                $blog_tab,
                $sponsorship_purchase_redirect_page_field,
                $appeal_text_field,
                $complaints_text_field,
                $categories_tab,
                $subscription_sponsorship_product_category,
                $free_post_category,
                $paid_post_category,
                $video_post_tag,
                $gallery_post_tag,
                $bts_post_tag,
                $private_gallery_post_tag,
                $elementor_tab,
                $pay_per_post_product_template,
                $my_account_buttons_template,
            ),
            'location' => [
                [
                    [
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'mariana-erato',
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_rest' => 0,
            'acfml_field_group_mode' => 'translation',
        ));
    }

    private function _get_store_tab() : array {
        $store_tab = array(
            'key' => 'field_67aa48f441f4a',
            'label' => __('Store', APP_THEME_DOMAIN),
            'name' => '',
            'aria-label' => '',
            'type' => 'tab',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'wpml_cf_preferences' => 1,
            'placement' => 'left',
            'endpoint' => 0,
            'selected' => 0,
        );
        $buy_post_text_field = array(
            'key' => 'field_67910897f53f7',
            'label' => __('Buy post text', APP_THEME_DOMAIN),
            'name' => 'buy_this_post_text',
            'aria-label' => '',
            'type' => 'textarea',
            'instructions' => __('Wildcard %s will be replaced by product price.', APP_THEME_DOMAIN),
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'wpml_cf_preferences' => 2,
            'default_value' => __('Compra este post por %s', APP_THEME_DOMAIN),
            'maxlength' => 100,
            'allow_in_bindings' => 0,
            'rows' => 3,
            'placeholder' => __('Compra este post por %s', APP_THEME_DOMAIN),
            'new_lines' => '',
        );
        $non_sponsor_text_field = array(
            'key' => 'field_679pOSnsdjk',
            'label' => __('Non sponsor sales text', APP_THEME_DOMAIN),
            'name' => 'non_sponsor_sales_text',
            'aria-label' => '',
            'type' => 'textarea',
            'instructions' => __('Wildcard %s will be replaced by release date.', APP_THEME_DOMAIN),
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'wpml_cf_preferences' => 2,
            'default_value' => __('Available on %s', APP_THEME_DOMAIN),
            'maxlength' => 100,
            'allow_in_bindings' => 0,
            'rows' => 3,
            'placeholder' => __('Available on %s', APP_THEME_DOMAIN),
            'new_lines' => '',
        );
        return array(
            'store_tab' => $store_tab,
            'buy_post_text_field' => $buy_post_text_field,
            'non_sponsor_text_field' => $non_sponsor_text_field,
        );
    }

    private function _get_pages_tab() : array {
        $pages_tab = [
            'key' => 'field_67aa4906412378',
            'label' => __('Pages', APP_THEME_DOMAIN),
            'name' => '',
            'aria-label' => '',
            'type' => 'tab',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'wpml_cf_preferences' => 1,
            'placement' => 'left',
            'endpoint' => 0,
            'selected' => 0,
        ];
        $purchased_page_field = [
            'key' => 'field_2938472939834',
            'label' => __('Purchased page', APP_THEME_DOMAIN),
            'name' => 'field_purchased_page',
            'aria-label' => '',
            'type' => 'post_object',
            'wpml_cf_preferences' => 1,
            'post_type' => [
                0 => 'page',
            ],
        ];
        $exclusive_content_page_field = [
            'key' => 'field_2938474395',
            'label' => __('Exclusive content page', APP_THEME_DOMAIN),
            'name' => 'field_exclusive_content_page',
            'aria-label' => '',
            'type' => 'post_object',
            'wpml_cf_preferences' => 1,
            'post_type' => [
                0 => 'page',
            ],
        ];
        return array(
            'pages_tab' => $pages_tab,
            'purchased_page_field' => $purchased_page_field,
            'exclusive_content_page_field' => $exclusive_content_page_field,
        );
    }

    private function _get_blog_tab() : array {
        $blog_tab = [
            'key' => 'field_67aa490641f4b',
            'label' => __('Blog', APP_THEME_DOMAIN),
            'name' => '',
            'aria-label' => '',
            'type' => 'tab',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'wpml_cf_preferences' => 1,
            'placement' => 'left',
            'endpoint' => 0,
            'selected' => 0,
        ];
        $sponsorship_purchase_redirect_page_field = [
            'key' => 'field_68c34b1f0a1c1',
            'label' => __('Sponsorships after purchase redirect to', APP_THEME_DOMAIN),
            'name' => 'sponsorship_purchase_redirect_page',
            'aria-label' => '',
            'type' => 'post_object',
            'post_type' => ['page'],
            'return_format' => 'id',
            'allow_null' => 1,
            'wpml_cf_preferences' => 1,
            'wrapper' => [
                'width' => '100%',
                'class' => '',
                'id' => '',
            ],
        ];
        $appeal_text_field = [
            'key' => 'field_67aa491241f4c',
            'label' => __('Appeals text', APP_THEME_DOMAIN),
            'name' => 'appeals_text',
            'aria-label' => '',
            'type' => 'wysiwyg',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '50%',
                'class' => '',
                'id' => '',
            ],
            'wpml_cf_preferences' => 2,
            'default_value' => '',
            'allow_in_bindings' => 0,
            'tabs' => 'all',
            'toolbar' => 'full',
            'media_upload' => 1,
            'delay' => 0,
        ];
        $complaints_text_field = [
            'key' => 'field_67aa493141f4d',
            'label' => __('Complaints text', APP_THEME_DOMAIN),
            'name' => 'complaints_text',
            'aria-label' => '',
            'type' => 'wysiwyg',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '50%',
                'class' => '',
                'id' => '',
            ],
            'wpml_cf_preferences' => 2,
            'default_value' => '',
            'allow_in_bindings' => 0,
            'tabs' => 'all',
            'toolbar' => 'full',
            'media_upload' => 1,
            'delay' => 0,
        ];
        return array(
            'blog_tab' => $blog_tab,
            'sponsorship_purchase_redirect_page_field' => $sponsorship_purchase_redirect_page_field,
            'appeal_text_field' => $appeal_text_field,
            'complaints_text_field' => $complaints_text_field,
        );
    }

    private function _get_categories_tags_tab() : array {
        $categories_tab = [
            'key' => 'field_63hdndf949068si3b6',
            'label' => __('Categories & tags', APP_THEME_DOMAIN),
            'name' => '',
            'aria-label' => '',
            'type' => 'tab',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'wpml_cf_preferences' => 1,
            'placement' => 'left',
            'endpoint' => 0,
            'selected' => 0,
        ];
        $subscription_sponsorship_product_category = [
            'key' => 'field_8493h92kshufd7',
            'label' => __('Sponsorship product category', APP_THEME_DOMAIN),
            'name' => 'subscription_sponsorship_product_category',
            'aria-label' => '',
            'type' => 'taxonomy',
            'taxonomy' => 'product_cat',
            'return_format' => 'id',
            'add_term' => 1,
            'instructions' => __('Products in this category will be used as sponsorship.', APP_THEME_DOMAIN),
            'save_terms' => 0,
            'load_terms' => 0,
            'field_type' => 'select',
            'allow_null' => 0,
            'allow_in_bindings' => 0,
            'bidirectional' => 0,
            'multiple' => 0,
            'wpml_cf_preferences' => 1,
            'wrapper' => [
                'width' => '50%',
                'class' => '',
                'id' => '',
            ],
        ];
        $free_post_category = [
            'key' => 'field_64jh47892kshufd7',
            'label' => __('Free post category', APP_THEME_DOMAIN),
            'name' => 'free_post_category',
            'aria-label' => '',
            'type' => 'taxonomy',
            'taxonomy' => 'category',
            'instructions' => __('Posts in this category will be free and not require purchase.',
                APP_THEME_DOMAIN),
            'return_format' => 'id',
            'add_term' => 1,
            'save_terms' => 0,
            'load_terms' => 0,
            'field_type' => 'select',
            'allow_null' => 0,
            'allow_in_bindings' => 0,
            'bidirectional' => 0,
            'multiple' => 0,
            'wpml_cf_preferences' => 1,
            'wrapper' => [
                'width' => '50%',
                'class' => '',
                'id' => '',
            ],
        ];
        $paid_post_category = [
            'key' => 'field_64jh4jfhg8643hfd7',
            'label' => __('Paid post category', APP_THEME_DOMAIN),
            'name' => 'paid_post_category',
            'aria-label' => '',
            'type' => 'taxonomy',
            'taxonomy' => 'category',
            'instructions' => __('Posts in this category will require purchase to access.', APP_THEME_DOMAIN),
            'return_format' => 'id',
            'add_term' => 1,
            'save_terms' => 0,
            'load_terms' => 0,
            'field_type' => 'select',
            'allow_null' => 0,
            'allow_in_bindings' => 0,
            'bidirectional' => 0,
            'multiple' => 0,
            'wpml_cf_preferences' => 1,
            'wrapper' => [
                'width' => '50%',
                'class' => '',
                'id' => '',
            ],
        ];
        $video_post_tag = [
            'key' => 'field_64jhfhjjg9863bi',
            'label' => __('Video post tag', APP_THEME_DOMAIN),
            'name' => 'video_post_tag',
            'aria-label' => '',
            'type' => 'taxonomy',
            'taxonomy' => 'post_tag',
            'instructions' => __('Posts with this tag will be considered video posts.', APP_THEME_DOMAIN),
            'return_format' => 'id',
            'add_term' => 1,
            'save_terms' => 0,
            'load_terms' => 0,
            'field_type' => 'select',
            'allow_null' => 0,
            'allow_in_bindings' => 0,
            'bidirectional' => 0,
            'multiple' => 0,
            'wpml_cf_preferences' => 1,
            'wrapper' => [
                'width' => '50%',
                'class' => '',
                'id' => '',
            ],
        ];
        $gallery_post_tag = [
            'key' => 'field_64jhflapod094563bi',
            'label' => __('Gallery post tag', APP_THEME_DOMAIN),
            'name' => 'gallery_post_tag',
            'aria-label' => '',
            'type' => 'taxonomy',
            'instructions' => __('Posts with this tag will be considered gallery posts.', APP_THEME_DOMAIN),
            'taxonomy' => 'post_tag',
            'return_format' => 'id',
            'add_term' => 1,
            'save_terms' => 0,
            'load_terms' => 0,
            'field_type' => 'select',
            'allow_null' => 0,
            'allow_in_bindings' => 0,
            'bidirectional' => 0,
            'multiple' => 0,
            'wpml_cf_preferences' => 1,
            'wrapper' => [
                'width' => '50%',
                'class' => '',
                'id' => '',
            ],
        ];
        $bts_post_tag = [
            'key' => 'field_94ik094563bi',
            'label' => __('Behind the scenes tag', APP_THEME_DOMAIN),
            'name' => 'bts_post_tag',
            'aria-label' => '',
            'type' => 'taxonomy',
            'instructions' => __('Posts with this tag will be considered behind the scenes posts.',
                APP_THEME_DOMAIN),
            'taxonomy' => 'post_tag',
            'return_format' => 'id',
            'add_term' => 1,
            'save_terms' => 0,
            'load_terms' => 0,
            'field_type' => 'select',
            'allow_null' => 0,
            'allow_in_bindings' => 0,
            'bidirectional' => 0,
            'multiple' => 0,
            'wpml_cf_preferences' => 1,
            'wrapper' => [
                'width' => '50%',
                'class' => '',
                'id' => '',
            ],
        ];
        $private_gallery_post_tag = [
            'key' => 'field_94iklfo8849',
            'label' => __('Private gallery tag', APP_THEME_DOMAIN),
            'name' => 'private_gallery_post_tag',
            'aria-label' => '',
            'type' => 'taxonomy',
            'instructions' => __('Posts with this tag will be considered private galleries.', APP_THEME_DOMAIN),
            'taxonomy' => 'post_tag',
            'return_format' => 'id',
            'add_term' => 1,
            'save_terms' => 0,
            'load_terms' => 0,
            'field_type' => 'select',
            'allow_null' => 0,
            'allow_in_bindings' => 0,
            'bidirectional' => 0,
            'multiple' => 0,
            'wpml_cf_preferences' => 1,
            'wrapper' => [
                'width' => '50%',
                'class' => '',
                'id' => '',
            ],
        ];
        return array(
            'categories_tab' => $categories_tab,
            'subscription_sponsorship_product_category' => $subscription_sponsorship_product_category,
            'free_post_category' => $free_post_category,
            'paid_post_category' => $paid_post_category,
            'video_post_tag' => $video_post_tag,
            'gallery_post_tag' => $gallery_post_tag,
            'bts_post_tag' => $bts_post_tag,
            'private_gallery_post_tag' => $private_gallery_post_tag,
        );
    }

    private function _get_elementor_tab() : array {
        $elementor_tab = [
            'key' => 'field_67aa49068si3b6',
            'label' => __('Elementor', APP_THEME_DOMAIN),
            'name' => '',
            'aria-label' => '',
            'type' => 'tab',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'wpml_cf_preferences' => 1,
            'placement' => 'left',
            'endpoint' => 0,
            'selected' => 0,
        ];
        $product_template = [
            'key' => 'field_67aa492kshufd7',
            'label' => __('Product template', APP_THEME_DOMAIN),
            'name' => 'pay_per_post_product_template',
            'aria-label' => '',
            'type' => 'post_object',
            'post_type' => [
                0 => 'elementor_library',
            ],
            'return_format' => 'id',
        ];
        $my_account_buttons_template = [
            'key' => 'field_67aa49jf894j',
            'label' => __('My account buttons template', APP_THEME_DOMAIN),
            'name' => 'my_account_buttons_template',
            'aria-label' => '',
            'type' => 'post_object',
            'post_type' => [
                0 => 'elementor_library',
            ],
            'return_format' => 'id',
        ];
        return array(
            'elementor_tab' => $elementor_tab,
            'pay_per_post_product_template' => $product_template,
            'my_account_buttons_template' => $my_account_buttons_template,
        );
    }
}
