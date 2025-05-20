<?php
/*
Plugin Name: Selectable Boxes Plugin
Description: A plugin to create selectable boxes for courses with live course date options.
Version: 1.10
Author: Carlos Murillo
*/

function selectable_boxes_shortcode() {
    $course_product_link = get_field('field_6821879221940');
    $enroll_product_link = get_field('field_6821879e21941');
    $course_price = get_field('field_681ccc6eb123a') ?: '749.99'; // Fetch course price directly from ACF field, fallback to 749.99
    $enroll_price = '1249.99'; // Default fallback price for enroll course

    $is_out_of_stock = false;
    $course_product_id = 0;
    $enroll_product_id = 0;

    // Extract course product ID
    if (!empty($course_product_link)) {
        $url_parts = parse_url($course_product_link, PHP_URL_QUERY);
        parse_str($url_parts, $query_params);
        $course_product_id = isset($query_params['add-to-cart']) ? intval($query_params['add-to-cart']) : 0;

        if ($course_product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($course_product_id);
            if ($product && !$product->is_in_stock()) {
                $is_out_of_stock = true;
            }
        }
    }

    // Extract enroll product ID and price
    if (!empty($enroll_product_link)) {
        $url_parts = parse_url($enroll_product_link, PHP_URL_QUERY);
        parse_str($url_parts, $query_params);
        $enroll_product_id = isset($query_params['add-to-cart']) ? intval($query_params['add-to-cart']) : 0;

        if ($enroll_product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($enroll_product_id);
            if ($product) {
                $enroll_price = $product->get_price() ?: '1249.99'; // Fetch price from WooCommerce product, fallback to 1249.99
            }
        }
    }

    ob_start();
    ?>
    <div class="box-container">
        <?php if ($is_out_of_stock) : ?>
            <div class="box soldout-course">
                <div class="soldout-header"><span>THE COURSE IS SOLD OUT</span></div>
                <h3>Join Waitlist for Free</h3>
                <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
                <input type="email" placeholder="Your email address" class="email-input">
                <button class="join-waitlist">Join Waitlist</button>
                <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
            </div>
        <?php elseif (empty($course_product_link)) : ?>
            <div class="box course-launch">
                <div class="countdown">
                    <span>COURSE LAUNCH IN:</span>
                    <span>05 DAYS 12 HRS 55 MIN 12 SEC</span>
                </div>
                <h3>Join Waitlist for Free</h3>
                <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
                [contact-form-7 id="255b390" title="Course Launch"]
                <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
            </div>
        <?php else : ?>
            <div class="box selected buy-course" onclick="selectBox(this, 'box1')">
                <h3>Buy This Course</h3>
                <p class="price">$<?php echo esc_html(number_format($course_price, 2)); ?> USD</p>
                <p class="description">Pay once, own the course forever.</p>
                <button class="add-to-cart-button" data-product-id="<?php echo esc_attr($course_product_id); ?>">Buy Course</button>
            </div>

            <div class="box no-button enroll-course" onclick="selectBox(this, 'box2')">
                <h3>Enroll in the Live Course</h3>
                <p class="price">$<?php echo esc_html(number_format($enroll_price, 2)); ?> USD</p>
                <p class="description">Take the live course over 6 weeks. Pay once.</p>

                <hr class="divider">

                <div class="start-dates">
                    <p class="choose-label">Choose a starting date</p>
                    <div class="date-options">
                        <?php
                        if (have_rows('field_6826dd2179231')) {
                            while (have_rows('field_6826dd2179231')) {
                                the_row();
                                $date = get_sub_field('date'); // Assuming 'date' is the subfield name in the repeater
                                if ($date) {
                                    $formatted_date = date_i18n('j M', strtotime($date));
                                    echo '<button class="date-btn">' . esc_html($formatted_date) . '</button>';
                                }
                            }
                        } else {
                            // Fallback static dates if repeater is empty
                            echo '
                                <button class="date-btn">5 May</button>
                                <button class="date-btn">12 May</button>
                                <button class="date-btn">19 May</button>
                                <button class="date-btn">26 May</button>
                                <button class="date-btn">2 June</button>
                            ';
                        }
                        ?>
                    </div>
                    <p class="description">All live courses will be recorded and available as VOD.</p>
                </div>

                <button class="add-to-cart-button" data-product-id="<?php echo esc_attr($enroll_product_id); ?>">Register Now</button>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .box-container {
        padding: 0px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .box {
        position: relative;
        max-width: 350px;
        width: 100%;
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
        background: linear-gradient(180deg, rgba(242, 46, 190, 0.2), rgba(170, 0, 212, 0.2));
        border: none;
        padding: 16px 12px 16px 12px; /* top right bottom left */
    }

    .box:not(.selected) {
        opacity: 0.7;
    }

    .box.no-button button {
        display: none;
    }

    .box h3 {
        color: #fff;
        margin: 5px 0 10px;
        font-size: 1.5em;
    }

    .box .price {
        font-family: 'Poppins', sans-serif !important;
        font-weight: 500 !important;
        font-size: 26px !important;
        line-height: 135% !important;
        letter-spacing: 0.48px !important;
        text-transform: capitalize !important;
    }

    .box .description {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.64) !important;
        margin: 10px 0;
    }

    .box button {
		width: 100%;
		padding: 5px 12px !important;
		background-color: rgba(255, 255, 255, 0.08) !important;
		border: none;
		border-radius: 4px !important;
		color: white;
		font-size: 1em;
		cursor: pointer;
    }

	.box button:hover {
		background-color: rgba(255, 255, 255, 0.2) !important;
	}
    .divider {
        border: none;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        margin: 20px 0;
    }

    .box:not(.selected) button {
        background-color: #cc3071;
    }

    .soldout-course, .course-launch {
        background: #2a2a2a;
        text-align: center;
    }

    .soldout-header {
        background: #ff3e3e;
        padding: 10px;
        border-radius: 10px;
        margin-bottom: 10px;
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

    .start-dates {
        display: none;
        margin-top: 15px;
        animation: fadeIn 0.4s ease;
    }

    .box.selected .start-dates {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .choose-label {
        font-size: 0.95em;
        margin-bottom: 10px;
        color: #fff;
    }

    .date-options {
        display: flex;
        gap: 4px;
        /* Removed flex-wrap: wrap to enforce horizontal layout */
    }

    .date-btn {
        padding: 8px 16px; /* Increased padding for larger capsules */
        font-size: 14px; /* Slightly larger font for readability */
        border: none;
        border-radius: 25px; /* Increased for capsule shape */
        background-color: rgba(255, 255, 255, 0.08) ; /* Matching the pinkish-purple from the image */
        color: white;
        cursor: pointer;
        transition: background-color 0.3s ease; /* Smooth transition */
    }

    .date-btn:hover {
        background-color: #cc3071; /* Darker shade on hover */
    }

    .date-btn.selected {
        background-color: #cc3071; /* Same as hover for consistency when selected */
    }

    @media (min-width: 768px) {
        .box-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
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
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const firstDateBtn = document.querySelector('.enroll-course .date-btn');
        if (firstDateBtn) {
            firstDateBtn.classList.add('selected');
        }

        document.querySelectorAll('.date-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                document.querySelectorAll('.date-btn').forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
            });
        });

        document.querySelectorAll('.add-to-cart-button').forEach(button => {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                const productId = this.getAttribute('data-product-id');
                if (!productId || productId === '0') {
                    alert('Error: Invalid product. Please try again.');
                    return;
                }

                const data = {
                    action: 'woocommerce_add_to_cart',
                    product_id: productId,
                    quantity: 1,
                    security: '<?php echo wp_create_nonce('woocommerce-add_to_cart'); ?>'
                };

                jQuery.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', data, function (response) {
                    if (response && response.error) {
                        alert('Error adding product to cart.');
                    } else {
                        jQuery(document.body).trigger('wc_fragment_refresh');
                        setTimeout(() => {
                            try {
                                jQuery(document).trigger('fkcart_open_cart');
                                if (jQuery('.fkcart-mini-open').length) {
                                    jQuery('.fkcart-mini-open').trigger('click');
                                }
                                if (jQuery('#fkcart-sidecart').length) {
                                    jQuery('#fkcart-sidecart').addClass('fkcart-active');
                                }
                                jQuery(document.body).trigger('added_to_cart', [response.fragments, response.catalog_hash]);
                            } catch (error) {
                                alert('Added to cart, but cart didn’t open.');
                            }
                        }, 500);
                    }
                });
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('selectable_boxes', 'selectable_boxes_shortcode');
?>
