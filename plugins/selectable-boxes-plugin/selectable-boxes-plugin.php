<?php
/*
Plugin Name: Selectable Boxes Plugin
Description: A plugin to create selectable boxes for courses with live course date options and dynamic launch countdown.
Version: 1.14
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

    // Get launch date for the course product
    $launch_date = $course_product_id ? apply_filters('wc_launch_date_get', '', $course_product_id) : '';
    $show_countdown = !empty($launch_date) && strtotime($launch_date) > current_time('timestamp');

    ob_start();
    ?>
    <div class="box-container">
        <?php if ($is_out_of_stock) : ?>
            <div class="box soldout-course">
                <div class="soldout-header"><span>THE COURSE IS SOLD OUT</span></div>
                <h3>Join Waitlist for Free</h3>
                <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
                [contact-form-7 id="c2b4e27" title="Course Sold Out"]
                <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
            </div>
        <?php elseif (empty($course_product_link) || $show_countdown) : ?>
            <div class="box course-launch">
                <div class="countdown">
                    <span>COURSE LAUNCH IN:</span>
                    <span id="countdown-timer" data-launch-date="<?php echo esc_attr($launch_date); ?>">
                        <?php
                        if ($show_countdown) {
                            $time_diff = strtotime($launch_date) - current_time('timestamp');
                            $days = floor($time_diff / (60 * 60 * 24));
                            $hours = floor(($time_diff % (60 * 60 * 24)) / (60 * 60));
                            $minutes = floor(($time_diff % (60 * 60)) / 60);
                            $seconds = $time_diff % 60;
                            echo esc_html(sprintf('%02d DAYS %02d HRS %02d MIN %02d SEC', $days, $hours, $minutes, $seconds));
                        } else {
                            echo 'Launching Soon';
                        }
                        ?>
                    </span>
                </div>
                <h3>Join Waitlist for Free</h3>
                <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
                [contact-form-7 id="255b390" title="Course Launch"]
                <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
            </div>
        <?php else : ?>
            <div class="box selected buy-course" onclick="selectBox(this, 'box1')">
                <div class="statebox">
                    <div class="circlecontainer">
                        <div class="outer-circle">
                            <div class="middle-circle">
                                <div class="inner-circle"></div>
                            </div>
                        </div>
                    </div>
                    <div class="circle-container" style="display: none;">
                        <div class="circle"></div>
                    </div>
                    <div>
                        <h3>Buy This Course</h3>
                        <p class="price">$<?php echo esc_html(number_format($course_price, 2)); ?> USD</p>
                        <p class="description">Pay once, own the course forever.</p>
                    </div>
                </div>
                <button class="add-to-cart-button" data-product-id="<?php echo esc_attr($course_product_id); ?>">Buy Course</button>
            </div>
            <div class="box enroll-course" onclick="selectBox(this, 'box2')">
                <div class="statebox">
                    <div class="circlecontainer" style="display: none;">
                        <div class="outer-circle">
                            <div class="middle-circle">
                                <div class="inner-circle"></div>
                            </div>
                        </div>
                    </div>
                    <div class="circle-container">
                        <div class="circle"></div>
                    </div>
                    <div>
                        <h3>Enroll in the Live Course</h3>
                        <p class="price">$<?php echo esc_html(number_format($enroll_price, 2)); ?> USD</p>
                        <p class="description">Take the live course over 6 weeks. Pay once.</p>
                    </div>
                </div>
                <hr class="divider">
                <div class="start-dates">
                    <p class="choose-label">Choose a starting date</p>
                    <div class="date-options">
                        <?php
                        if (have_rows('field_682a572f53f64')) {
                            while (have_rows('field_682a572f53f64')) {
                                the_row();
                                $date_text = get_sub_field('field_682a574e53f65');
                                if (!empty($date_text)) {
                                    echo '<button class="date-btn">' . esc_html($date_text) . '</button>';
                                }
                            }
                        } else {
                            error_log('El repeater field_682a572f53f64 está vacío o no existe.');
                            echo '
                                <button class="date-btn">5 Mayo</button>
                                <button class="date-btn">12 Mayo</button>
                                <button class="date-btn">19 Mayo</button>
                                <button class="date-btn">26 Mayo</button>
                                <button class="date-btn">2 Junio</button>
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

.box-container .box {
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

.box-container .box.selected {
    background: linear-gradient(180deg, rgba(242, 46, 190, 0.2), rgba(170, 0, 212, 0.2));
    border: none;
    padding: 16px 12px 16px 12px;
}

.box-container .box:not(.selected) {
    opacity: 0.7;
}

.box-container .box.no-button button {
    display: none;
}

.box-container .box h3 {
    color: #fff;
    margin-left: 10px;
    margin-top: 0px;
    font-size: 1.5em;
}

.box-container .box .price {
    font-family: 'Poppins', sans-serif;
    font-weight: 500;
    font-size: 26px;
    line-height: 135%;
    letter-spacing: 0.48px;
    text-transform: capitalize;
}

.box-container .box .description {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.64);
    margin: 10px 0;
}

.box-container .box button {
    width: 100%;
    padding: 5px 12px;
    background-color: rgba(255, 255, 255, 0.08);
    border: none;
    border-radius: 4px;
    color: white;
    font-size: 1em;
    cursor: pointer;
}

.box-container .box button:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

.box-container .divider {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    margin: 20px 0;
}

.box-container .box:not(.selected) button {
    background-color: #cc3071;
}

.box-container .soldout-course,
.box-container .course-launch {
    background: #2a2a2a;
    text-align: center;
}

.box-container .soldout-header {
    background: #ff3e3e;
    padding: 10px;
    border-radius: 10px;
    margin-bottom: 10px;
}

.box-container .countdown {
    background: #800080;
    padding: 10px;
    border-radius: 10px;
    margin-bottom: 10px;
}

.box-container .countdown span:first-child {
    font-size: 1.2em;
    display: block;
}

.box-container .countdown span:last-child {
    font-size: 1.5em;
    font-weight: bold;
}

.box-container .email-input {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 25px;
    background: #333;
    color: white;
}

.box-container .join-waitlist {
    background-color: #ff3e8e;
    border: none;
    padding: 10px;
    border-radius: 25px;
    color: white;
    font-size: 1em;
    cursor: pointer;
}

.box-container .terms {
    font-size: 0.7em;
    color: #aaa;
}

.box-container .start-dates {
    display: none;
    margin-top: 15px;
    animation: fadeIn 0.4s ease;
}

.box-container .box.selected .start-dates {
    display: block;
}

.box-container .statebox {
    display: flex;
}

.box-container .outer-circle {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background-color: #DE04A4;
    border: 1.45px solid #DE04A4;
    display: flex;
    align-items: center;
    justify-content: center;
}

.box-container .middle-circle {
    width: 11.77px;
    height: 11.77px;
    border-radius: 50%;
    background-color: #050505;
    display: flex;
    align-items: center;
    justify-content: center;
}

.box-container .inner-circle {
    width: 6.16px;
    height: 6.16px;
    border-radius: 50%;
    background-color: #DE04A4;
}

.box-container .circlecontainer {
    margin: 6px 7px;
}

.box-container .circle-container {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.box-container .circle {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid rgba(155, 159, 170, 0.24);
    box-shadow: 0 4px 4px rgba(0, 0, 0, 0.25);
}

.box-container .box:not(.selected) .circlecontainer {
    display: none;
}

.box-container .box:not(.selected) .circle-container {
    display: flex;
}

.box-container .box.selected .circle-container {
    display: none;
}

.box-container .box.selected .circlecontainer {
    display: flex;
}

.box-container .choose-label {
    font-size: 0.95em;
    margin-bottom: 10px;
    color: #fff;
}

.box-container .date-options {
    display: flex;
    gap: 4px;
}

.box-container .date-btn {
    padding: 8px 16px;
    font-size: 14px;
    border: none;
    border-radius: 25px;
    background-color: rgba(255, 255, 255, 0.08);
    color: white;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.box-container .date-btn:hover {
    background-color: #cc3071;
}

.box-container .date-btn.selected {
    background-color: #cc3071;
}

.box-container .buy-course,
.box-container .enroll-course {
    background: black;
}

.box-container .soldout-course,
.box-container .course-launch {
    background: black;
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

@media (min-width: 768px) {
    .box-container {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 20px;
    }
}

@media (max-width: 767px) {
    .box-container .box {
        padding: 10px;
    }
    .box-container .box h3 {
        font-size: 1.2em;
    }
    .box-container .box .price {
        font-size: 1em;
    }
    .box-container .box .description {
        font-size: 0.8em;
    }
    .box-container .box button {
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
            const circleContainer = box.querySelector('.circle-container');
            const circlecontainer = box.querySelector('.circlecontainer');
            circleContainer.style.display = 'flex';
            circlecontainer.style.display = 'none';
        });
        element.classList.add('selected');
        element.classList.remove('no-button');
        const selectedCircleContainer = element.querySelector('.circle-container');
        const selectedCirclecontainer = element.querySelector('.circlecontainer');
        selectedCircleContainer.style.display = 'none';
        selectedCirclecontainer.style.display = 'flex';
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

        // Dynamic countdown timer
        const countdownElement = document.getElementById('countdown-timer');
        if (countdownElement && countdownElement.dataset.launchDate) {
            const launchDate = new Date(countdownElement.dataset.launchDate).getTime();
            const updateCountdown = () => {
                const now = new Date().getTime();
                const timeDiff = launchDate - now;
                if (timeDiff <= 0) {
                    countdownElement.textContent = 'Launched!';
                    return;
                }
                const days = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((timeDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeDiff % (1000 * 60)) / 1000);
                countdownElement.textContent = `${days.toString().padStart(2, '0')} DAYS ${hours.toString().padStart(2, '0')} HRS ${minutes.toString().padStart(2, '0')} MIN ${seconds.toString().padStart(2, '0')} SEC`;
            };
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('selectable_boxes', 'selectable_boxes_shortcode');
?>
