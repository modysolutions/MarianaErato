<?php

/** @noinspection PhpUndefinedVariableInspection */
//Sort by last purchase date
//usort($purchased, fn($a, $b) => strcmp($a->last_purchase_date, $b->last_purchase_date));


// Purchased
use Elementor\Core\Files\CSS\Post;
use Elementor\Plugin;

$private_gallery_post_tag = get_field('private_gallery_post_tag', 'option');
$bts_post_tag = get_field('bts_post_tag', 'option');
$field_purchased_page = get_field('field_purchased_page', 'option', false);
$field_exclusive_content_page = get_field('field_exclusive_content_page', 'option', false);
$css_file = new Post($template_id ?? null);
$css_file->enqueue();
?>
<div class="mm-purchased">
    <?php
    if (count($purchased) > 0):
        $page_id = get_the_ID();
        $posts = array_filter($purchased, function ($post) use ($private_gallery_post_tag, $bts_post_tag, $page_id,
            $field_purchased_page, $field_exclusive_content_page) {
            $id = (is_int($post)) ? $post : $post->ID;
            $language_info = apply_filters('wpml_post_language_details', null, $id);
            if ($language_info) {
                $language_code = $language_info['language_code'];
            } else {
                $language_code = 'en';
            }
            $current_language = apply_filters('wpml_current_language', NULL);
            if($language_code !== $current_language) {
                return false;
            }
            if($page_id === (int)$field_purchased_page) {
                if ( has_term($private_gallery_post_tag, 'post_tag', $id) ||
                    has_term($bts_post_tag, 'post_tag', $id) ) {
                    return false;
                }
            } else if($page_id === (int)$field_exclusive_content_page) {
                if ( !has_term($private_gallery_post_tag, 'post_tag', $id) &&
                    !has_term($bts_post_tag, 'post_tag', $id) ) {
                    return false;
                }
            }
            return true;
        });
      $classname = $posts ? 'mm-purchased__list' : ''; ?>
      <h3>
          <?php _e(
              sprintf('%s Content', $field_exclusive_content_page === $page_id ? 'Exclusive' : 'Purchased'),
              APP_THEME_DOMAIN
          ); ?>
      </h3>
      <div class='<?php echo $classname;?>'>
          <?php
          if ($posts):
              foreach ($posts as $post):
                  $permalink = get_permalink($post->ID); ?>
                <div class='mm-purchased__list__item <?php
                echo get_post_type($post->ID); ?>'>
                    <?php
                    $post_thumbnail = get_the_post_thumbnail_url($post->ID); ?>
                  <div class='thumbnail' style='background-image: url(<?php
                  echo $post_thumbnail ?>);'>
                  </div>
                  <div class='info'>
                    <a href="<?php
                    echo $permalink; ?>">
                        <?php
                        echo esc_html($post->post_title); ?>
                    </a>
                  </div>
                </div>
              <?php
              endforeach;
          else:
              $product_template = get_field('pay_per_post_product_template', 'option');
              if ($product_template && did_action('elementor/loaded')) {
//                echo Plugin::instance()->frontend->get_builder_content_for_display($product_template);
              }?>
          <?php endif; ?>
      </div>
    <?php
    else:
        $product_template = get_field('pay_per_post_product_template', 'option');
        if ($product_template && did_action('elementor/loaded')) {
            echo Plugin::instance()->frontend->get_builder_content_for_display($product_template);
        }?>
    <?php
    endif;
    ?>
</div>