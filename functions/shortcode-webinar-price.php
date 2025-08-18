<?php
/**
 * Webinar Price Shortcode
 * 
 * Displays webinar/course prices with support for ACF fields and WooCommerce products
 */

/**
 * Shortcode to display webinar product prices with sale price below regular price
 */
function webinar_price_shortcode($atts) {
    $atts = shortcode_atts(['course_id' => 0], $atts, 'webinar_price');
    $course_page_id = absint($atts['course_id']);

    // Use current post ID if not provided
    if (!$course_page_id && is_singular('course')) {
        $course_page_id = get_the_ID();
    }

    if (!$course_page_id || get_post_type($course_page_id) !== 'course') {
        error_log('webinar_price_shortcode: Invalid or missing course_page_id: ' . $course_page_id);
        return '';
    }

    // First check ACF fields on the course page
    $regular_price = get_field('course_price', $course_page_id);
    $sale_price = get_field('course_sales_price', $course_page_id);
    
    // If ACF fields are empty, fall back to webinar product prices
    if (empty($regular_price)) {
        // Get related stm_course_id
        $stm_course_id = get_post_meta($course_page_id, 'related_stm_course_id', true);
        if (!$stm_course_id) {
            error_log('webinar_price_shortcode: No related stm_course_id for course_page_id: ' . $course_page_id);
            return '';
        }

        // Get related webinar product
        $webinar_product_id = get_post_meta($stm_course_id, 'related_webinar_product_id', true);
        if (!$webinar_product_id || get_post_type($webinar_product_id) !== 'product') {
            error_log('webinar_price_shortcode: Invalid or missing webinar_product_id for stm_course_id: ' . $stm_course_id);
            return '';
        }

        // Get prices from product
        $regular_price = get_post_meta($webinar_product_id, '_regular_price', true) ?: 0;
        $sale_price = get_post_meta($webinar_product_id, '_sale_price', true);
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

    $price_source = !empty(get_field('course_price', $course_page_id)) ? 'ACF' : 'Webinar Product';
    error_log('webinar_price_shortcode: Rendered for course_page_id: ' . $course_page_id . ', source: ' . $price_source . ', regular_price: ' . $regular_price . ', sale_price: ' . ($sale_price !== '' ? $sale_price : 'none'));
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
                font-weight: bold;
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
                    font-weight: bold;
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