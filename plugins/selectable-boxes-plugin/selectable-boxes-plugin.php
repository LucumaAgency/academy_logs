<?php
/*
Plugin Name: Selectable Boxes Plugin
Description: A simple plugin to create selectable boxes with course launch and sold-out options based on ACF field and stock status.
Version: 1.2
Author: Carlos Murillo
Author URI: https://lucumaagency.com/
License: GPL-2.0+
*/

function selectable_boxes_shortcode() {
    // Get the ACF field value for the current page
    $course_product_link = get_field('field_6821879221940');

    // Initialize variables
    $is_out_of_stock = false;

    // Check if the product is out of stock if the ACF field has a value
    if (!empty($course_product_link)) {
        // Extract product ID from the URL
        $url_parts = parse_url($course_product_link, PHP_URL_QUERY);
        parse_str($url_parts, $query_params);
        $product_id = isset($query_params['add-to-cart']) ? intval($query_params['add-to-cart']) : 0;

        // Check stock status if product ID is valid
        if ($product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product && !$product->is_in_stock()) {
                $is_out_of_stock = true;
            }
        }
    }

    ob_start();
    ?>
    <div class="box-container">
        <?php if ($is_out_of_stock) : ?>
            <div class="box soldout-course">
                <div class="soldout-header">
                    <span>THE COURSE SOLD OUT</span>
                </div>
                <h3>Join Waitlist For Free</h3>
                <p class="description">Gain access to live streams, free credits for Arcana & more.</p>
                <input type="email" placeholder="Your Email address" class="email-input">
                <button class="join-waitlist">Join Waitlist</button>
                <p class="terms">By signing up you agree to Terms & Conditions</p>
            </div>
        <?php elseif (empty($course_product_link)) : ?>
            <div class="box course-launch">
                <div class="countdown">
                    <span>COURSE LAUNCH IN:</span>
                    <span>05 DAYS 12 HRS 55 MIN 12 SEC</span>
                </div>
                <h3>Join Waitlist For Free</h3>
                <p class="description">Gain access to live streams, free credits for Arcana & more.</p>
                <input type="email" placeholder="Your Email address" class="email-input">
                <button class="join-waitlist">Join Waitlist</button>
                <p class="terms">By signing up you agree to Terms & Conditions</p>
            </div>
        <?php else : ?>
            <div class="box selected buy-course" onclick="selectBox(this, 'box1')">
                <h3>Buy This Course</h3>
                <p class="price">$749.99 USD</p>
                <p class="description">Pay once, Have the course forever</p>
                <button>Buy Course</button>
            </div>
            <div class="box no-button enroll-course" onclick="selectBox(this, 'box2')">
                <h3>Enroll in the Live Course</h3>
                <p class="price">$1249.99 USD</p>
                <p class="description">Take the live course over 6 weeks</p>
                <button>Buy Course</button>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .box-container {
        display: block;
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }
    .box {
        position: relative;
        width: 100%;
        max-width: 350px;
        padding: 15px;
        background: transparent;
        border: 2px solid #9B9FAA7A;
        border-radius: 15px;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-bottom: 20px;
        box-sizing: border-box;
    }
    .box.selected {
        background: linear-gradient(180deg, rgba(242, 46, 190, 0.2) 0%, rgba(170, 0, 212, 0.2) 100%);
        border: none;
    }
    .box:not(.selected) {
        opacity: 0.7;
    }
    .box.no-button button {
        display: none;
    }
    .box h3 {
        color: #fff !important;
        margin: 5px 0 10px;
        font-size: 1.5em;
    }
    .box .price {
        font-size: 1.2em;
        font-weight: bold;
    }
    .box .description {
        font-size: 0.9em;
        margin: 10px 0;
    }
    .box button {
        width: 100%;
        padding: 10px;
        background-color: #ff3e8e;
        border: none;
        border-radius: 25px;
        color: white;
        font-size: 1em;
        cursor: pointer;
    }
    .box:not(.selected) button {
        background-color: #cc3071;
    }
    .course-launch, .soldout-course {
        background: #2a2a2a;
        text-align: center;
    }
    .countdown {
        background: #800080;
        padding: 10px;
        border-radius: 10px;
        margin-bottom: 10px;
    }
    .countdown span:first-child {
        font-size: 1.2em;
        display: block;
    }
    .countdown span:last-child {
        font-size: 1.5em;
        font-weight: bold;
    }
    .soldout-header {
        background: #ff3e3e;
        padding: 10px;
        border-radius: 10px;
        margin-bottom: 10px;
    }
    .soldout-header span {
        font-size: 1.2em;
        display: block;
    }
    .email-input {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border: 1px solid #ccc;
        border-radius: 25px;
        background: #333;
        color: white;
    }
    .join-waitlist {
        background-color: #ff3e8e;
        border: none;
        padding: 10px;
        border-radius: 25px;
        color: white;
        font-size: 1em;
        cursor: pointer;
    }
    .terms {
        font-size: 0.7em;
        color: #aaa;
    }

    /* Media Queries para Responsive */
    @media (min-width: 768px) {
        .box-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .box {
            width: 100%;
            max-width: 350px;
            margin-bottom: 0;
        }
    }
    @media (max-width: 767px) {
        .box {
            padding: 10px;
        }
        .box h3 {
            font-size: 1.2em;
        }
        .box .price {
            font-size: 1em;
        }
        .box .description {
            font-size: 0.8em;
        }
        .box button {
            padding: 8px;
            font-size: 0.9em;
        }
    }
    </style>

    <script>
    function selectBox(element, boxId) {
        document.querySelectorAll('.box').forEach(box => {
            box.classList.remove('selected');
            box.classList.add('no-button');
        });
        element.classList.remove('no-button');
        element.classList.add('selected');
        document.getElementById('selected-box')?.setValue(boxId);
    }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('selectable_boxes', 'selectable_boxes_shortcode');
?>
