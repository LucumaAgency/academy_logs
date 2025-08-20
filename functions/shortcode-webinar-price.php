<?php
/**
 * Webinar Price Shortcode
 * 
 * Displays webinar/course prices with support for ACF fields and WooCommerce products
 */

/**
 * Shortcode to display course or webinar prices
 * Priority: If course prices exist, show them. Otherwise, show webinar prices.
 */
function webinar_price_shortcode($atts) {
    $atts = shortcode_atts([
        'course_id' => 0
    ], $atts, 'webinar_price');
    
    $course_page_id = absint($atts['course_id']);

    // Use current post ID if not provided
    if (!$course_page_id && is_singular('course')) {
        $course_page_id = get_the_ID();
    }

    if (!$course_page_id || get_post_type($course_page_id) !== 'course') {
        error_log('webinar_price_shortcode: Invalid or missing course_page_id: ' . $course_page_id);
        return '';
    }

    // First check for course prices (higher priority)
    $course_regular_price = get_field('field_681ccc6eb123a', $course_page_id);  // course_price
    $course_sale_price = get_field('field_689f3a6f5b266', $course_page_id);     // course_sales_price
    
    // Then check for webinar prices
    $webinar_regular_price = get_field('field_6853a215dbd49', $course_page_id);  // webinar_regular_price
    $webinar_sale_price = get_field('field_6853a231dbd4a', $course_page_id);     // webinar_sale_price
    
    // Determine which prices to use (course prices have priority)
    if (!empty($course_regular_price)) {
        $regular_price = $course_regular_price;
        $sale_price = $course_sale_price;
        $price_type = 'course';
        $fallback_product_type = 'course';
    } elseif (!empty($webinar_regular_price)) {
        $regular_price = $webinar_regular_price;
        $sale_price = $webinar_sale_price;
        $price_type = 'webinar';
        $fallback_product_type = 'webinar';
    } else {
        // No ACF prices found, need to check WooCommerce products
        $regular_price = null;
        $sale_price = null;
        $price_type = null;
        $fallback_product_type = null;
    }
    
    // If no ACF prices found, check WooCommerce products
    if (empty($regular_price)) {
        // Get related stm_course_id
        $stm_course_id = get_post_meta($course_page_id, 'related_stm_course_id', true);
        if (!$stm_course_id) {
            error_log('webinar_price_shortcode: No related stm_course_id for course_page_id: ' . $course_page_id);
            return '';
        }

        // First try course product (priority)
        $course_product_id = get_post_meta($stm_course_id, 'related_course_product_id', true);
        if ($course_product_id && get_post_type($course_product_id) === 'product') {
            $course_product_regular = get_post_meta($course_product_id, '_regular_price', true);
            if (!empty($course_product_regular)) {
                $regular_price = $course_product_regular;
                $sale_price = get_post_meta($course_product_id, '_sale_price', true);
                $price_type = 'course';
            }
        }
        
        // If still no price, try webinar product
        if (empty($regular_price)) {
            $webinar_product_id = get_post_meta($stm_course_id, 'related_webinar_product_id', true);
            if ($webinar_product_id && get_post_type($webinar_product_id) === 'product') {
                $webinar_product_regular = get_post_meta($webinar_product_id, '_regular_price', true);
                if (!empty($webinar_product_regular)) {
                    $regular_price = $webinar_product_regular;
                    $sale_price = get_post_meta($webinar_product_id, '_sale_price', true);
                    $price_type = 'webinar';
                }
            }
        }
        
        // If still no price found
        if (empty($regular_price)) {
            error_log('webinar_price_shortcode: No prices found for course_page_id: ' . $course_page_id);
            return '';
        }
    }

    // Format prices using WooCommerce, appending USD
    $formatted_regular_price = wc_price($regular_price) . ' USD';
    $formatted_sale_price = $sale_price !== '' ? wc_price($sale_price) . ' USD' : '';

    // Determine the lowest price for mobile display
    $lowest_price = ($sale_price !== '' && $sale_price < $regular_price) ? $formatted_sale_price : $formatted_regular_price;

    // Build output
    $output = '<div class="webinar-price">';
    $output .= '<div class="lowest-price-mobile">' . $lowest_price . '</div>'; // Lowest price for mobile
    if ($sale_price !== '' && $sale_price < $regular_price) {
        $output .= '<div class="woocommerce-Price-amount amount regular-price desktop-price"><del>' . $formatted_regular_price . '</del></div>';
        $output .= '<div class="woocommerce-Price-amount amount sale-price desktop-price">' . $formatted_sale_price . '</div>';
    } else {
        $output .= '<div class="woocommerce-Price-amount amount regular-price desktop-price">' . $formatted_regular_price . '</div>';
    }
    $output .= '</div>';

    // Determine price source for logging
    $price_source = '';
    if ($price_type === 'course') {
        $price_source = !empty($course_regular_price) ? 'ACF Course' : 'Course Product';
    } else {
        $price_source = !empty($webinar_regular_price) ? 'ACF Webinar' : 'Webinar Product';
    }
    
    error_log('webinar_price_shortcode: Rendered for course_page_id: ' . $course_page_id . ', type: ' . $price_type . ', source: ' . $price_source . ', regular_price: ' . $regular_price . ', sale_price: ' . ($sale_price !== '' ? $sale_price : 'none'));
    return $output;
}
add_shortcode('webinar_price', 'webinar_price_shortcode');

/**
 * Enqueue styles for the shortcode
 */
function webinar_price_shortcode_styles() {
    if (is_singular('course')) {
        wp_enqueue_style('webinar-price-style', false);
        wp_add_inline_style('woocommerce-general', '
            .webinar-price .regular-price {
                color: #FFF;
                display: block;
                margin-top: 5px;
            }
            .webinar-price .regular-price del {
                color: #999;
                font-weight: normal;
            }
            .webinar-price .sale-price {
                color: #FFF;
                font-weight: normal;
                display: block;
            }
            .webinar-price .desktop-price {
                display: block;
            }
            .webinar-price .lowest-price-mobile {
                display: none;
            }
            @media (max-width: 767px) {
                .webinar-price .desktop-price {
                    display: none;
                }
                .webinar-price .lowest-price-mobile {
                    display: block;
                    color: #FFF;
                    font-weight: 400;
                }
            }
            /* Ensure webinar price has correct text color in boxes */
            .box-option .webinar-price .woocommerce-Price-amount {
                color: #FFF !important;
            }
            .box-option .webinar-price del .woocommerce-Price-amount {
                color: #999 !important;
            }
            /* Fix for Buy Course box */
            .box-buy-course .webinar-price .woocommerce-Price-amount {
                color: #FFF !important;
            }
            .box-buy-course .webinar-price del .woocommerce-Price-amount {
                color: #999 !important;
            }
        ');
    }
}
add_action('wp_enqueue_scripts', 'webinar_price_shortcode_styles');