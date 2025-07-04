<?php
/*
Plugin Name: Selectable Boxes Plugin
Description: A plugin to create selectable boxes for courses with live course date options from ACF, dynamic launch countdown, an admin dropdown in course post type to show/hide course box, and saves selected start date to order metadata, displayed in orders and emails. Supports multiple products in FunnelKit Cart.
Version: 1.32
Author: Carlos Murillo
*/

// Ensure FunnelKit Cart and Checkout are enabled on course post type
add_filter('fkcart_disabled_post_types', function ($post_types) {
    $post_types = array_filter($post_types, function ($i) {
        return $i !== 'course';
    });
    return $post_types;
});

function selectable_boxes_shortcode() {
    global $post;
    $post_id = $post ? $post->ID : 0;

    // Fetch ACF fields
    $course_product_link = get_field('field_6821879221940', $post_id);
    $enroll_product_link = get_field('field_6821879e21941', $post_id);
    $course_price = get_field('field_681ccc6eb123a', $post_id) ?: '749.99';
    $course_visibility = get_field('field_68314e3c26394', $post_id) ?: 'don\'t show buy course';

    // Get available start dates from ACF repeater field
    $available_dates = [];
    $has_dates = false;
    if (have_rows('field_6826dd2179231', $post_id)) {
        while (have_rows('field_6826dd2179231', $post_id)) {
            the_row();
            $date_text = get_sub_field('field_6826dfe2d7837');
            if (!empty($date_text)) {
                $available_dates[] = sanitize_text_field($date_text);
                $has_dates = true;
            }
        }
    }

    // Handle case when no dates are available
    if (empty($available_dates)) {
        error_log('Selectable Boxes Plugin: No start dates available for post ID ' . $post_id);
        ob_start();
        ?>
        <div class="box-container">
            <div class="course-launch">
                <h3>Join Waitlist for Free</h3>
                <p class="launch-subline">Be the first to know when the course launches. No Spam. We Promise!</p>
                <div class="contact-form">[contact-form-7 id="255b390" title="Course Launch"]</div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // Debug: Log field values
    error_log('Selectable Boxes Plugin: Post ID = ' . $post_id);
    error_log('Selectable Boxes Plugin: course_visibility = ' . $course_visibility);
    error_log('Selectable Boxes Plugin: course_product_link = ' . ($course_product_link ?: 'empty'));
    error_log('Selectable Boxes Plugin: enroll_product_link = ' . ($enroll_product_link ?: 'empty'));
    error_log('Selectable Boxes Plugin: course_price = ' . $course_price);
    error_log('Selectable Boxes Plugin: available_dates = ' . implode(', ', $available_dates));

    $enroll_price = '1249.99';
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
                $enroll_price = $product->get_price() ?: '1249.99';
            }
        }
    }

    // Get launch date
    $launch_date = $course_product_id ? apply_filters('wc_launch_date_get', '', $course_product_id) : '';
    $show_countdown = !empty($launch_date) && strtotime($launch_date) > current_time('timestamp');

    ob_start();
    ?>
    <div class="selectable-box-container">
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
                        <div class="countdown-timer" id="countdown-timer" data-launch-date="<?php echo esc_attr($launch_date); ?>">
                            <?php
                            if ($show_countdown) {
                                $time_diff = strtotime($launch_date) - current_time('timestamp');
                                $days = floor($time_diff / (60 * 60 * 24));
                                $hours = floor(($time_diff % (60 * 60 * 24)) / (60 * 60));
                                $minutes = floor(($time_diff % (60 * 60)) / 60);
                                $seconds = $time_diff % 60;
                                ?>
                                <div class="time-unit" data-unit="days">
                                    <span class="time-value"><?php echo esc_html(sprintf('%02d', $days)); ?></span>
                                    <span class="time-label">days</span>
                                </div>
                                <div class="time-unit" data-unit="hours">
                                    <span class="time-value"><?php echo esc_html(sprintf('%02d', $hours)); ?></span>
                                    <span class="time-label">hrs</span>
                                </div>
                                <div class="time-unit" data-unit="minutes">
                                    <span class="time-value"><?php echo esc_html(sprintf('%02d', $minutes)); ?></span>
                                    <span class="time-label">min</span>
                                </div>
                                <div class="time-unit" data-unit="seconds">
                                    <span class="time-value"><?php echo esc_html(sprintf('%02d', $seconds)); ?></span>
                                    <span class="time-label">sec</span>
                                </div>
                                <?php
                            } else {
                                echo '<span class="launch-soon">Launching Soon</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <h3>Join Waitlist for Free</h3>
                    <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
                    [contact-form-7 id="255b390" title="Course Launch"]
                    <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
                </div>
            <?php else : ?>
                <?php if ($course_visibility === 'show buy course' && !empty($course_product_link)) : ?>
                    <div class="box buy-course selected" onclick="selectBox(this, 'box1')">
                        <div class="statebox">
                            <div class="circlecontainer" style="display: flex;">
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
                <?php endif; ?>
                <div class="box enroll-course<?php echo $course_visibility === 'don\'t show buy course' ? ' selected' : ''; ?>" onclick="selectBox(this, 'box2')">
                    <div class="statebox">
                        <div class="circlecontainer" style="display: <?php echo $course_visibility === 'don\'t show buy course' ? 'flex' : 'none'; ?>;">
                            <div class="outer-circle">
                                <div class="middle-circle">
                                    <div class="inner-circle"></div>
                                </div>
                            </div>
                        </div>
                        <div class="circle-container" style="display: <?php echo $course_visibility === 'don\'t show buy course' ? 'none' : 'flex'; ?>;">
                            <div class="circle"></div>
                        </div>
                        <div>
                            <h3>Enroll in the Live Course</h3>
                            <p>[webinar_price]</p>
                            <p class="description">Join weekly live sessions with feedback and expert mentorship. Pay Once.</p>
                        </div>
                    </div>
                    <hr class="divider">
                    <div class="start-dates" style="display: <?php echo $course_visibility === 'don\'t show buy course' ? 'block' : 'none'; ?>;">
                        <p class="choose-label">Choose a starting date</p>
                        <div class="date-options">
                            <?php
                            foreach ($available_dates as $date) {
                                echo '<button class="date-btn" data-date="' . esc_attr($date) . '">' . esc_html($date) . '</button>';
                            }
                            ?>
                        </div>
                    </div>
                    <button class="add-to-cart-button" data-product-id="<?php echo esc_attr($enroll_product_id); ?>">
                        <span class="button-text">Enroll Now</span>
                        <span class="loader" style="display: none;"></span>
                    </button>
                    [seats_remaining]
                </div>
            <?php endif; ?>
        </div>
        <div class="text-outside-box">
            <p style="text-align: center; letter-spacing: 0.9px; margin-top: 30px; font-weight: 200; font-size: 12px;">
                <span style="font-weight: 500; font-size: 14px;">Missing a Class ?</span>
                <br>No worries! All live courses will be recorded and made available on-demand to all students.
            </p>
        </div>
    </div>

    <style>
        .selectable-box-container {
            max-width: 350px;
        }

        .box-container {
            padding: 0;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
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
            padding: 16px 12px;
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
            margin-top: 0;
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
            font-size: 12px;
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
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .box-container .countdown-timer {
            display: flex;
            gap: 15px;
        }

        .box-container .time-unit {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .box-container .time-value {
            font-size: 1.5em;
            font-weight: bold;
        }

        .box-container .time-label {
            font-size: 0.9em;
            color: rgba(255, 255, 255, 0.8);
        }

        .box-container .countdown span:first-child {
            display: none;
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
            flex-wrap: wrap;
            gap: 4px;
        }

        .box-container .date-btn {
            width: 68px;
            padding: 5px 8px;
            border: none;
            border-radius: 25px;
            background-color: rgba(255, 255, 255, 0.08);
            color: white;
            cursor: pointer;
        }

        .box-container .date-btn:hover,
        .box-container .date-btn.selected {
            background-color: #cc3071;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 767px) {
            .box-container .box {
                padding: 10px;
            }
            .box-container .box h3 {
                font-size: 1.2em;
            }
        }

.add-to-cart-button {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 40px; /* Fixed height to match original styling */
    min-height: 40px; /* Prevent shrinking */
    max-height: 40px; /* Prevent expansion */
    padding: 5px 12px;
    background-color: rgba(255, 255, 255, 0.08);
    border: none;
    border-radius: 4px;
    color: white;
    font-size: 12px;
    cursor: pointer;
    box-sizing: border-box;
    overflow: hidden; /* Ensure loader doesn't overflow */
}

.add-to-cart-button.loading .button-text {
    visibility: hidden;
}

.add-to-cart-button.loading .loader {
    display: inline-block;
}

.loader {
    width: 8px; /* Smaller size to prevent overflow */
    height: 8px; /* Smaller size to prevent overflow */
    border: 2px solid transparent;
    border-top-color: #fff; /* White color as requested */
    border-radius: 50%;
    animation: spin 1s linear infinite;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    margin: 0; /* Remove any default margins */
}
.loading:before{
height: 20px!important;
width: 20px!important;
border:2px solid rgb(255 255 255 / 50%)!important;
margin-left:0px!important;
top: 7px!important;
left: 45%!important;
right: 40%!important;
}

@keyframes spin {
    to { transform: translate(-50%, -50%) rotate(360deg); }
}
    </style>

    <script>
        let selectedDate = '';
        let wasCartOpened = false;
        let wasCartManuallyClosed = false;

        function selectBox(element, boxId) {
            document.querySelectorAll('.box').forEach(box => {
                box.classList.remove('selected');
                box.classList.add('no-button');
                const circleContainer = box.querySelector('.circle-container');
                const circlecontainer = box.querySelector('.circlecontainer');
                const startDates = box.querySelector('.start-dates');
                if (circleContainer) circleContainer.style.display = 'flex';
                if (circlecontainer) circlecontainer.style.display = 'none';
                if (startDates) startDates.style.display = 'none';
            });
            element.classList.add('selected');
            element.classList.remove('no-button');
            const selectedCircleContainer = element.querySelector('.circle-container');
            const selectedCirclecontainer = element.querySelector('.circlecontainer');
            const selectedStartDates = element.querySelector('.start-dates');
            if (selectedCircleContainer) selectedCircleContainer.style.display = 'none';
            if (selectedCirclecontainer) selectedCirclecontainer.style.display = 'flex';
            if (selectedStartDates) selectedStartDates.style.display = 'block';
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function openFunnelKitCart() {
            console.log('openFunnelKitCart called');
            wasCartOpened = false;
            wasCartManuallyClosed = false;

            return new Promise((resolve) => {
                console.log('Triggering wc_fragment_refresh');
                jQuery(document.body).trigger('wc_fragment_refresh');

                const checkVisibility = () => {
                    const sidebar = document.querySelector('#fkcart-sidecart, .fkcart-sidebar, .fk-cart-panel, .fkcart-cart-sidebar, .cart-sidebar, .fkcart-panel');
                    if (sidebar) {
                        const isVisible = sidebar.classList.contains('fkcart-active') ||
                                          sidebar.classList.contains('active') ||
                                          sidebar.classList.contains('fkcart-open') ||
                                          window.getComputedStyle(sidebar).display !== 'none' ||
                                          window.getComputedStyle(sidebar).visibility !== 'hidden';
                        console.log('Sidebar visibility check - Classes:', sidebar.classList, 'IsVisible:', isVisible);
                        return isVisible;
                    }
                    console.log('No sidebar element found');
                    return false;
                };

                try {
                    console.log('Triggering fkcart_open_cart');
                    jQuery(document).trigger('fkcart_open_cart');

                    const toggles = ['.fkcart-mini-open', '.fkcart-toggle', '[data-fkcart-open]', '.fkcart-cart-toggle', '.cart-toggle', '.fkcart-open'];
                    let toggleClicked = false;
                    toggles.forEach(selector => {
                        const toggle = document.querySelector(selector);
                        if (toggle && !toggleClicked) {
                            console.log('Clicking toggle:', selector);
                            toggle.click();
                            toggleClicked = true;
                        } else {
                            console.log('No toggle found for selector:', selector);
                        }
                    });

                    const sidebars = ['#fkcart-sidecart', '.fkcart-sidebar', '.fk-cart-panel', '.fkcart-cart-sidebar', '.cart-sidebar, .fkcart-panel'];
                    let sidebarActivated = false;
                    sidebars.forEach(selector => {
                        const sidebar = document.querySelector(selector);
                        if (sidebar && !sidebarActivated) {
                            console.log('Activating sidebar:', selector);
                            sidebar.classList.add('fkcart-active', 'active', 'fkcart-open');
                            sidebarActivated = true;
                        } else {
                            console.log('No sidebar found for selector:', selector);
                        }
                    });

                    if (checkVisibility()) {
                        console.log('Sidebar visible after initial attempt');
                        wasCartOpened = true;
                        resolve(true);
                        return;
                    }

                    setTimeout(() => {
                        if (checkVisibility()) {
                            console.log('Sidebar visible after delay');
                            wasCartOpened = true;
                            resolve(true);
                        } else if (wasCartManuallyClosed) {
                            console.log('Cart was manually closed, resolving');
                            resolve(true);
                        } else {
                            console.log('Sidebar not visible, resolving without alert');
                            resolve(wasCartOpened);
                        }
                    }, 1000);
                } catch (error) {
                    console.error('Error in openFunnelKitCart:', error);
                    resolve(wasCartOpened || wasCartManuallyClosed);
                }
            });
        }

        function getCartContents() {
            return new Promise((resolve) => {
                console.log('Fetching current cart contents');
                const ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=get_refreshed_fragments&_=' + new Date().getTime();
                console.log('Cart contents AJAX URL:', ajaxUrl);
                jQuery.get(ajaxUrl, function (response) {
                    console.log('Cart contents response:', response);
                    resolve(response);
                }).fail(function (jqXHR, textStatus, errorThrown) {
                    console.error('Failed to fetch cart contents:', textStatus, errorThrown);
                    resolve(null);
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOM loaded, initializing selectable boxes');
            const enrollBox = document.querySelector('.enroll-course');
            const courseBox = document.querySelector('.buy-course');
            const courseVisibility = '<?php echo esc_js($course_visibility); ?>';
            console.log('Course visibility:', courseVisibility);

            if (courseVisibility === 'don\'t show buy course' && enrollBox) {
                console.log('Selecting enroll box by default');
                selectBox(enrollBox, 'box2');
            } else if (courseVisibility === 'show buy course' && courseBox) {
                console.log('Selecting course box by default');
                selectBox(courseBox, 'box1');
            }

            const firstDateBtn = document.querySelector('.enroll-course .date-btn');
            if (firstDateBtn) {
                firstDateBtn.classList.add('selected');
                selectedDate = firstDateBtn.getAttribute('data-date') || firstDateBtn.textContent.trim();
                console.log('Default selected date:', selectedDate);
            }

            document.querySelectorAll('.date-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    document.querySelectorAll('.date-btn').forEach(b => b.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedDate = this.getAttribute('data-date') || this.textContent.trim();
                    console.log('Updated selected date:', selectedDate);
                });
            });

            document.querySelectorAll('.add-to-cart-button').forEach(button => {
                button.addEventListener('click', async function (e) {
                    e.preventDefault();
                    const productId = this.getAttribute('data-product-id');
                    console.log('Add to cart button clicked, Product ID:', productId);
            
                    if (!productId || productId === '0') {
                        console.error('Invalid product ID');
                        alert('Error: Invalid product. Please try again.');
                        return;
                    }
            
                    const isEnrollButton = this.closest('.enroll-course') !== null;
                    console.log('Is enroll button:', isEnrollButton);
                    if (isEnrollButton && !selectedDate) {
                        console.error('No start date selected for enroll course');
                        alert('Please select a start date before adding to cart.');
                        return;
                    }
            
                    // Show loader
                    this.classList.add('loading');
            
                    const addToCart = (productId, startDate = null) => {
                        console.log('addToCart called with Product ID:', productId, 'Start Date:', startDate);
                        return new Promise((resolve, reject) => {
                            const data = {
                                action: 'woocommerce_add_to_cart',
                                product_id: productId,
                                quantity: 1,
                                security: '<?php echo wp_create_nonce('woocommerce_add_to_cart'); ?>'
                            };
            
                            if (startDate) {
                                data.start_date = startDate;
                                console.log('Including start_date in AJAX data:', startDate);
                            }
            
                            console.log('Sending AJAX request with data:', data);
                            jQuery.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', data, function (response) {
                                console.log('AJAX response received:', response);
                                if (response && response.fragments && response.cart_hash) {
                                    console.log('Product added to cart successfully');
                                    resolve(response);
                                } else {
                                    console.error('Failed to add product to cart, response:', response);
                                    reject(new Error('Failed to add product to cart.'));
                                }
                            }).fail(function (jqXHR, textStatus, errorThrown) {
                                console.error('AJAX request failed:', textStatus, errorThrown);
                                reject(new Error('Error communicating with the server: ' + textStatus));
                            });
                        });
                    };
            
                    const addProduct = async () => {
                        console.log('Starting addProduct process');
                        try {
                            const cartContents = await getCartContents();
                            console.log('Current cart contents before adding:', cartContents);
            
                            const response = await addToCart(productId, isEnrollButton ? selectedDate : null);
                            console.log('Triggering added_to_cart with fragments and cart_hash');
                            jQuery(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                            jQuery(document.body).trigger('wc_fragment_refresh');
            
                            setTimeout(() => {
                                console.log('Forcing delayed cart refresh');
                                jQuery(document.body).trigger('wc_fragment_refresh');
                                jQuery(document).trigger('fkcart_open_cart');
                            }, 1000);
            
                            const updatedCartContents = await getCartContents();
                            console.log('Cart contents after adding product:', updatedCartContents);
            
                            console.log('Calling openFunnelKitCart');
                            const cartOpened = await openFunnelKitCart();
                            console.log('Cart opened successfully:', cartOpened);
                            if (!cartOpened && !wasCartOpened && !wasCartManuallyClosed) {
                                console.warn('Cart failed to open, notifying user to check manually');
                                alert('The cart may not have updated. Please check the cart manually.');
                            }
                        } catch (error) {
                            console.error('Error in addProduct:', error);
                            alert('Error adding product to cart: ' + error.message);
                        } finally {
                            // Hide loader
                            button.classList.remove('loading');
                        }
                    };
            
                    addProduct();
                });
            });

            jQuery(document).on('click', '.wfacp_mb_mini_cart_sec_accordion', function (e) {
                console.log('Order Summary toggle clicked');
                try {
                    const $this = jQuery(this);
                    const content = $this.next('.wfacp_mb_mini_cart_sec_accordion_content');
                    if (content.length) {
                        console.log('Order Summary content found, toggling display');
                        content.toggle();
                        jQuery(document.body).trigger('wc_fragment_refresh');
                        console.log('wc_fragment_refresh triggered for order summary');
                    } else {
                        console.warn('Order Summary content not found');
                    }
                } catch (error) {
                    console.error('Error toggling Order Summary:', error);
                    alert('Error loading order summary. Please refresh the page and try again.');
                }
            });

            document.addEventListener('click', function (e) {
                if (e.target.closest('.fkcart-close, .fkcart-cart-close, .cart-close, .fkcart-close-btn, .fkcart-panel-close, [data-fkcart-close], .close-cart')) {
                    console.log('Cart sidebar close button clicked');
                    wasCartManuallyClosed = true;
                }
            });

            document.querySelectorAll('.fkcart-cart-toggle, .cart-toggle').forEach(toggle => {
                toggle.addEventListener('click', () => {
                    console.log('Manual cart toggle clicked, forcing refresh');
                    jQuery(document.body).trigger('wc_fragment_refresh');
                    jQuery(document.body).trigger('wc_update_cart');
                });
            });

            const countdownElement = document.getElementById('countdown-timer');
            if (countdownElement && countdownElement.dataset.launchDate) {
                console.log('Initializing countdown timer with launch date:', countdownElement.dataset.launchDate);
                const launchDate = new Date(countdownElement.dataset.launchDate).getTime();
                const updateCountdown = () => {
                    const now = new Date().getTime();
                    const timeDiff = launchDate - now;
                    if (timeDiff <= 0) {
                        console.log('Countdown ended, displaying Launched!');
                        countdownElement.innerHTML = '<span class="launch-soon">Launched!</span>';
                        return;
                    }
                    const days = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((timeDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((timeDiff % (1000 * 60)) / 1000);
                    const timeUnits = [
                        { unit: 'days', value: days },
                        { unit: 'hours', value: hours },
                        { unit: 'minutes', value: minutes },
                        { unit: 'seconds', value: seconds }
                    ];
                    timeUnits.forEach(({ unit, value }) => {
                        const element = countdownElement.querySelector(`.time-unit[data-unit="${unit}"] .time-value`);
                        if (element) {
                            element.textContent = `${value.toString().padStart(2, '0')}`;
                        }
                    });
                };
                updateCountdown();
                setInterval(updateCountdown, 1000);
            }
        });
    </script>
    <?php
    return ob_get_clean();
}

// Add start date to cart item data
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id, $quantity) {
    if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
        $start_date = sanitize_text_field($_POST['start_date']);
        $cart_item_data['start_date'] = $start_date;
        error_log('Added start_date to cart item: ' . $start_date);
    }
    return $cart_item_data;
}, 10, 4);

// Save start date to order item meta
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (isset($values['start_date']) && !empty($values['start_date'])) {
        $item->add_meta_data('Start Date', $values['start_date'], true);
        error_log('Saved start_date to order item: ' . $values['start_date']);
    }
}, 10, 4);

// Display start date in admin order details
add_action('woocommerce_order_item_meta_end', function ($item_id, $item, $order, $plain_text) {
    $start_date = $item->get_meta('Start Date');
    if ($start_date) {
        if ($plain_text) {
            echo "Start Date: $start_date\n";
        } else {
            echo '<p><strong>Start Date:</strong> ' . esc_html($start_date) . '</p>';
        }
        error_log('Displayed start_date in admin order details: ' . $start_date);
    }
}, 10, 4);

// Add start date to customer order email
add_action('woocommerce_email_order_meta', function ($order, $sent_to_admin, $plain_text, $email) {
    foreach ($order->get_items() as $item_id => $item) {
        $start_date = $item->get_meta('Start Date');
        if ($start_date) {
            if ($plain_text) {
                echo "Start Date: $start_date\n";
            } else {
                echo '<p><strong>Start Date:</strong> ' . esc_html($start_date) . '</p>';
            }
            error_log('Added start_date to order email: ' . $start_date);
        }
    }
}, 10, 4);

add_shortcode('selectable_boxes', 'selectable_boxes_shortcode');
?>
