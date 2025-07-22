<?php
/*
 * Plugin Name: Course Box Manager
 * Description: A comprehensive plugin to manage and display selectable boxes for course post types with dashboard control, countdowns, start date selection, and WooCommerce integration.
 * Version: 1.0.0
 * Author: Carlos Murillo
 * Author URI: https://lucumaagency.com/
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enable FunnelKit Cart for course post type
add_filter('fkcart_disabled_post_types', function ($post_types) {
    $post_types = array_filter($post_types, function ($i) {
        return $i !== 'course';
    });
    return $post_types;
});

// Add admin menu
add_action('admin_menu', 'course_box_manager_menu');
function course_box_manager_menu() {
    add_menu_page(
        'Course Box Manager',
        'Course Boxes',
        'manage_options',
        'course-box-manager',
        'course_box_manager_page',
        'dashicons-list-view',
        20
    );
}

// Dashboard page content
function course_box_manager_page() {
    $courses = get_posts([
        'post_type' => 'course',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Box State</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course_id) {
                    $title = get_the_title($course_id);
                    $show_buy_course = get_option('course_box_show_buy_' . $course_id, '0');
                    $show_enroll_course = get_option('course_box_show_enroll_' . $course_id, '0');
                    $show_waitlist = get_option('course_box_show_waitlist_' . $course_id, '0');
                    $show_soldout = get_option('course_box_show_soldout_' . $course_id, '0');
                    $current_state = 'enroll-course'; // Default to Enroll in the Live Course
                    if ($show_soldout === '1') $current_state = 'soldout';
                    elseif ($show_waitlist === '1') $current_state = 'waitlist';
                    elseif ($show_buy_course === '1') $current_state = 'buy-course';
                    ?>
                    <tr>
                        <td><?php echo esc_html($course_id); ?></td>
                        <td><?php echo esc_html($title); ?></td>
                        <td>
                            <select class="box-state-select" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="enroll-course" <?php echo $current_state === 'enroll-course' ? 'selected' : ''; ?>>Enroll in the Live Course</option>
                                <option value="buy-course" <?php echo $current_state === 'buy-course' ? 'selected' : ''; ?>>Buy This Course</option>
                                <option value="waitlist" <?php echo $current_state === 'waitlist' ? 'selected' : ''; ?>>Waitlist</option>
                                <option value="soldout" <?php echo $current_state === 'soldout' ? 'selected' : ''; ?>>Sold Out</option>
                            </select>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <button class="button button-primary" id="save-all-box-settings">Save All Settings</button>

        <div id="box-edit-modal" class="box-edit-modal" style="display:none;">
            <div class="box-edit-content">
                <span class="box-edit-close">×</span>
                <h2>Edit Selectable Boxes</h2>
                <input type="hidden" id="current-course-id">
                <label><input type="checkbox" id="show-buy-course"> Show "Buy This Course" Box</label><br>
                <label><input type="checkbox" id="show-enroll-course"> Show "Enroll in the Live Course" Box</label><br>
                <label><input type="checkbox" id="show-waitlist"> Show Waitlist Box</label><br>
                <label><input type="checkbox" id="show-soldout"> Show Sold Out Box</label><br>
                <button class="button button-primary" id="save-box-settings">Save Settings</button>
            </div>
        </div>
    </div>

    <style>
        .box-edit-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .box-edit-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
            position: relative;
        }
        .box-edit-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .box-edit-close:hover,
        .box-edit-close:focus {
            color: black;
            text-decoration: none;
        }
        .box-state-select {
            width: 200px;
            padding: 5px;
            font-size: 14px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('box-edit-modal');
            const span = document.getElementsByClassName('box-edit-close')[0];
            const saveButton = document.getElementById('save-box-settings');
            const courseIdInput = document.getElementById('current-course-id');
            const saveAllButton = document.getElementById('save-all-box-settings');

            document.querySelectorAll('.box-state-select').forEach(select => {
                select.addEventListener('change', function() {
                    const courseId = this.getAttribute('data-course-id');
                    courseIdInput.value = courseId;
                    const state = this.value;
                    fetch(ajaxurl + '?action=get_course_box_settings&course_id=' + courseId)
                        .then(response => response.json())
                        .then(data => {
                            document.getElementById('show-buy-course').checked = state === 'buy-course';
                            document.getElementById('show-enroll-course').checked = state === 'enroll-course';
                            document.getElementById('show-waitlist').checked = state === 'waitlist';
                            document.getElementById('show-soldout').checked = state === 'soldout';
                            modal.style.display = 'block';
                        });
                });
            });

            span.onclick = function() {
                modal.style.display = 'none';
            };

            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            };

            saveButton.onclick = function() {
                const courseId = courseIdInput.value;
                const showBuyCourse = document.getElementById('show-buy-course').checked ? '1' : '0';
                const showEnrollCourse = document.getElementById('show-enroll-course').checked ? '1' : '0';
                const showWaitlist = document.getElementById('show-waitlist').checked ? '1' : '0';
                const showSoldout = document.getElementById('show-soldout').checked ? '1' : '0';

                fetch(ajaxurl + '?action=save_course_box_settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'course_id=' + courseId + '&show_buy_course=' + showBuyCourse + '&show_enroll_course=' + showEnrollCourse + '&show_waitlist=' + showWaitlist + '&show_soldout=' + showSoldout + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modal.style.display = 'none';
                        alert('Settings saved successfully!');
                        location.reload();
                    } else {
                        alert('Error saving settings: ' + data.data);
                    }
                });
            };

            saveAllButton.onclick = function() {
                const updates = [];
                document.querySelectorAll('.box-state-select').forEach(select => {
                    const courseId = select.getAttribute('data-course-id');
                    const state = select.value;
                    updates.push({
                        course_id: courseId,
                        show_buy_course: state === 'buy-course' ? '1' : '0',
                        show_enroll_course: state === 'enroll-course' ? '1' : '0',
                        show_waitlist: state === 'waitlist' ? '1' : '0',
                        show_soldout: state === 'soldout' ? '1' : '0'
                    });
                });

                fetch(ajaxurl + '?action=save_all_course_box_settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'updates=' + encodeURIComponent(JSON.stringify(updates)) + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('All settings saved successfully!');
                        location.reload();
                    } else {
                        alert('Error saving settings: ' + data.data);
                    }
                });
            };
        });
    </script>
    <?php
}

// AJAX handlers
add_action('wp_ajax_get_course_box_settings', 'get_course_box_settings');
function get_course_box_settings() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $course_id = intval($_GET['course_id']);
    wp_send_json_success([
        'show_buy_course' => get_option('course_box_show_buy_' . $course_id, '0'),
        'show_enroll_course' => get_option('course_box_show_enroll_' . $course_id, '0'),
        'show_waitlist' => get_option('course_box_show_waitlist_' . $course_id, '0'),
        'show_soldout' => get_option('course_box_show_soldout_' . $course_id, '0'),
    ]);
}

add_action('wp_ajax_save_course_box_settings', 'save_course_box_settings');
function save_course_box_settings() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $course_id = intval($_POST['course_id']);
    $show_buy_course = sanitize_text_field($_POST['show_buy_course']);
    $show_enroll_course = sanitize_text_field($_POST['show_enroll_course']);
    $show_waitlist = sanitize_text_field($_POST['show_waitlist']);
    $show_soldout = sanitize_text_field($_POST['show_soldout']);

    update_option('course_box_show_buy_' . $course_id, $show_buy_course);
    update_option('course_box_show_enroll_' . $course_id, $show_enroll_course);
    update_option('course_box_show_waitlist_' . $course_id, $show_waitlist);
    update_option('course_box_show_soldout_' . $course_id, $show_soldout);

    wp_send_json_success();
}

add_action('wp_ajax_save_all_course_box_settings', 'save_all_course_box_settings');
function save_all_course_box_settings() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $updates = json_decode(stripslashes($_POST['updates']), true);
    foreach ($updates as $update) {
        $course_id = intval($update['course_id']);
        update_option('course_box_show_buy_' . $course_id, $update['show_buy_course']);
        update_option('course_box_show_enroll_' . $course_id, $update['show_enroll_course']);
        update_option('course_box_show_waitlist_' . $course_id, $update['show_waitlist']);
        update_option('course_box_show_soldout_' . $course_id, $update['show_soldout']);
    }
    wp_send_json_success();
}

// Shortcode to render boxes
function course_box_manager_shortcode() {
    global $post;
    $post_id = $post ? $post->ID : 0;

    // Fetch ACF fields
    $course_product_link = get_field('field_6821879221940', $post_id);
    $enroll_product_link = get_field('field_6821879e21941', $post_id);
    $course_price = get_field('field_681ccc6eb123a', $post_id) ?: '749.99';
    $available_dates = [];
    if (have_rows('field_6826dd2179231', $post_id)) {
        while (have_rows('field_6826dd2179231', $post_id)) {
            the_row();
            $date_text = get_sub_field('field_6826dfe2d7837');
            if (!empty($date_text)) {
                $available_dates[] = sanitize_text_field($date_text);
            }
        }
    }

    // Get visibility settings
    $show_buy_course = get_option('course_box_show_buy_' . $post_id, '0');
    $show_enroll_course = get_option('course_box_show_enroll_' . $post_id, '0');
    $show_waitlist = get_option('course_box_show_waitlist_' . $post_id, '0');
    $show_soldout = get_option('course_box_show_soldout_' . $post_id, '0');

    $course_product_id = $course_product_link ? intval(parse_url($course_product_link, PHP_URL_QUERY)) : 0;
    $enroll_product_id = $enroll_product_link ? intval(parse_url($enroll_product_link, PHP_URL_QUERY)) : 0;
    $is_out_of_stock = $course_product_id && function_exists('wc_get_product') && !wc_get_product($course_product_id)->is_in_stock();
    $launch_date = $course_product_id ? apply_filters('wc_launch_date_get', '', $course_product_id) : '';
    $show_countdown = !empty($launch_date) && strtotime($launch_date) > current_time('timestamp');

    ob_start();
    ?>
    <div class="selectable-box-container">
        <div class="box-container">
            <?php if ($show_soldout && $is_out_of_stock) : ?>
                <div class="box soldout-course">
                    <div class="soldout-header"><span>THE COURSE IS SOLD OUT</span></div>
                    <h3>Join Waitlist for Free</h3>
                    <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
                    [contact-form-7 id="c2b4e27" title="Course Sold Out"]
                    <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
                </div>
            <?php elseif ($show_waitlist && empty($available_dates) && !$is_out_of_stock) : ?>
                <div class="box course-launch">
                    <h3>Join Waitlist for Free</h3>
                    <p class="description">Be the first to know when the course launches. No Spam. We Promise!</p>
                    [contact-form-7 id="255b390" title="Course Launch"]
                    <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
                </div>
            <?php elseif ($show_countdown && $launch_date) : ?>
                <div class="box course-launch">
                    <div class="countdown">
                        <span>COURSE LAUNCH IN:</span>
                        <div class="countdown-timer" id="countdown-timer-<?php echo esc_attr($post_id); ?>" data-launch-date="<?php echo esc_attr($launch_date); ?>">
                            <?php
                            $time_diff = strtotime($launch_date) - current_time('timestamp');
                            if ($time_diff > 0) {
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
                                echo '<span class="launch-soon">Launched!</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <h3>Join Waitlist for Free</h3>
                    <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
                    [contact-form-7 id="255b390" title="Course Launch"]
                    <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
                </div>
            <?php endif; ?>
            <?php if ($show_buy_course && !$is_out_of_stock && !$show_countdown) : ?>
                <div class="box buy-course<?php echo !$show_enroll_course ? ' selected' : ''; ?>" onclick="selectBox(this, 'box1', <?php echo esc_attr($post_id); ?>)">
                    <div class="statebox">
                        <div class="circlecontainer" style="display: <?php echo !$show_enroll_course ? 'flex' : 'none'; ?>;">
                            <div class="outer-circle">
                                <div class="middle-circle">
                                    <div class="inner-circle"></div>
                                </div>
                            </div>
                        </div>
                        <div class="circle-container" style="display: <?php echo !$show_enroll_course ? 'none' : 'flex'; ?>;">
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
            <?php if ($show_enroll_course && !$is_out_of_stock && !$show_countdown && !empty($available_dates)) : ?>
                <div class="box enroll-course<?php echo $show_buy_course ? '' : ' selected'; ?>" onclick="selectBox(this, 'box2', <?php echo esc_attr($post_id); ?>)">
                    <div class="statebox">
                        <div class="circlecontainer" style="display: <?php echo $show_buy_course ? 'none' : 'flex'; ?>;">
                            <div class="outer-circle">
                                <div class="middle-circle">
                                    <div class="inner-circle"></div>
                                </div>
                            </div>
                        </div>
                        <div class="circle-container" style="display: <?php echo $show_buy_course ? 'flex' : 'none'; ?>;">
                            <div class="circle"></div>
                        </div>
                        <div>
                            <h3>Enroll in the Live Course</h3>
                            <p>$1249.99 USD</p>
                            <p class="description">Join weekly live sessions with feedback and expert mentorship. Pay Once.</p>
                        </div>
                    </div>
                    <hr class="divider">
                    <div class="start-dates" style="display: <?php echo $show_buy_course ? 'none' : 'block'; ?>;">
                        <p class="choose-label">Choose a starting date</p>
                        <div class="date-options">
                            <?php foreach ($available_dates as $date) {
                                echo '<button class="date-btn" data-date="' . esc_attr($date) . '">' . esc_html($date) . '</button>';
                            } ?>
                        </div>
                    </div>
                    <button class="add-to-cart-button" data-product-id="<?php echo esc_attr($enroll_product_id); ?>">
                        <span class="button-text">Enroll Now</span>
                        <span class="loader" style="display: none;"></span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-outside-box">
            <p style="text-align: center; letter-spacing: 0.9px; margin-top: 30px; font-weight: 200; font-size: 12px;">
                <span style="font-weight: 500; font-size: 14px;">Missing a Class?</span>
                <br>No worries! All live courses will be recorded and made available on-demand to all students.
            </p>
        </div>
    </div>

    <style>
        .selectable-box-container { max-width: 350px; }
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
        .box-container .box h3 { color: #fff; margin-left: 10px; margin-top: 0; font-size: 1.5em; }
        .box-container .box .price { font-family: 'Poppins', sans-serif; font-weight: 500; font-size: 26px; line-height: 135%; letter-spacing: 0.48px; }
        .box-container .box .description { font-size: 12px; color: rgba(255, 255, 255, 0.64); margin: 10px 0; }
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
        .box-container .box button:hover { background-color: rgba(255, 255, 255, 0.2); }
        .box-container .divider { border: none; border-top: 1px solid rgba(255, 255, 255, 0.2); margin: 20px 0; }
        .box-container .soldout-course, .box-container .course-launch { background: #2a2a2a; text-align: center; }
        .box-container .soldout-header { background: #ff3e3e; padding: 10px; border-radius: 10px; margin-bottom: 10px; }
        .box-container .countdown { background: #800080; padding: 10px; border-radius: 10px; margin-bottom: 10px; display: flex; justify-content: center; gap: 15px; }
        .box-container .countdown-timer { display: flex; gap: 15px; }
        .box-container .time-unit { display: flex; flex-direction: column; align-items: center; }
        .box-container .time-value { font-size: 1.5em; font-weight: bold; }
        .box-container .time-label { font-size: 0.9em; color: rgba(255, 255, 255, 0.8); }
        .box-container .terms { font-size: 0.7em; color: #aaa; }
        .box-container .start-dates { display: none; margin-top: 15px; animation: fadeIn 0.4s ease; }
        .box-container .box.selected .start-dates { display: block; }
        .box-container .statebox { display: flex; }
        .box-container .outer-circle { width: 16px; height: 16px; border-radius: 50%; background-color: #DE04A4; border: 1.45px solid #DE04A4; display: flex; align-items: center; justify-content: center; }
        .box-container .middle-circle { width: 11.77px; height: 11.77px; border-radius: 50%; background-color: #050505; display: flex; align-items: center; justify-content: center; }
        .box-container .inner-circle { width: 6.16px; height: 6.16px; border-radius: 50%; background-color: #DE04A4; }
        .box-container .circlecontainer { margin: 6px 7px; }
        .box-container .circle-container { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
        .box-container .circle { width: 14px; height: 14px; border-radius: 50%; border: 2px solid rgba(155, 159, 170, 0.24); }
        .box-container .box:not(.selected) .circlecontainer { display: none; }
        .box-container .box:not(.selected) .circle-container { display: flex; }
        .box-container .box.selected .circle-container { display: none; }
        .box-container .box.selected .circlecontainer { display: flex; }
        .box-container .choose-label { font-size: 0.95em; margin-bottom: 10px; color: #fff; }
        .box-container .date-options { display: flex; flex-wrap: wrap; gap: 4px; }
        .box-container .date-btn { width: 68px; padding: 5px 8px; border: none; border-radius: 25px; background-color: rgba(255, 255, 255, 0.08); color: white; cursor: pointer; }
        .box-container .date-btn:hover, .box-container .date-btn.selected { background-color: #cc3071; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 767px) { .box-container .box { padding: 10px; } .box-container .box h3 { font-size: 1.2em; } }
        .add-to-cart-button {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 40px;
            min-height: 40px;
            max-height: 40px;
            padding: 5px 12px;
            background-color: rgba(255, 255, 255, 0.08);
            border: none;
            border-radius: 4px;
            color: white;
            font-size: 12px;
            cursor: pointer;
            box-sizing: border-box;
            overflow: hidden;
        }
        .add-to-cart-button.loading .button-text { visibility: hidden; }
        .add-to-cart-button.loading .loader { display: inline-block; }
        .loader {
            width: 8px;
            height: 8px;
            border: 2px solid transparent;
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0;
        }
        @keyframes spin { to { transform: translate(-50%, -50%) rotate(360deg); } }
    </style>

    <script>
        let selectedDates = {};
        let wasCartOpened = false;
        let wasCartManuallyClosed = false;

        function selectBox(element, boxId, postId) {
            const boxes = element.closest('.box-container').querySelectorAll('.box');
            boxes.forEach(box => {
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

            if (element.classList.contains('enroll-course')) {
                const firstDateBtn = selectedStartDates.querySelector('.date-btn');
                if (firstDateBtn && !selectedDates[postId]) {
                    firstDateBtn.classList.add('selected');
                    selectedDates[postId] = firstDateBtn.getAttribute('data-date') || firstDateBtn.textContent.trim();
                }
            }
        }

        function openFunnelKitCart() {
            return new Promise((resolve) => {
                jQuery(document.body).trigger('wc_fragment_refresh');
                jQuery(document).trigger('fkcart_open_cart');
                const checkVisibility = () => {
                    const sidebar = document.querySelector('#fkcart-sidecart, .fkcart-sidebar, .fk-cart-panel, .fkcart-cart-sidebar, .cart-sidebar, .fkcart-panel');
                    return sidebar && (sidebar.classList.contains('fkcart-active') || sidebar.classList.contains('active') || sidebar.classList.contains('fkcart-open') || window.getComputedStyle(sidebar).display !== 'none');
                };
                if (checkVisibility()) {
                    wasCartOpened = true;
                    resolve(true);
                    return;
                }
                setTimeout(() => {
                    resolve(checkVisibility());
                }, 1000);
            });
        }

        function getCartContents() {
            return new Promise((resolve) => {
                jQuery.get('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=get_refreshed_fragments&_=' + new Date().getTime(), function(response) {
                    resolve(response);
                }).fail(function() {
                    resolve(null);
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.date-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const postId = this.closest('.enroll-course').getAttribute('data-post-id');
                    document.querySelectorAll('.date-btn').forEach(b => b.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedDates[postId] = this.getAttribute('data-date') || this.textContent.trim();
                });
            });

            document.querySelectorAll('.add-to-cart-button').forEach(button => {
                button.addEventListener('click', async function(e) {
                    e.preventDefault();
                    const productId = this.getAttribute('data-product-id');
                    const postId = this.closest('.box').getAttribute('data-post-id');
                    if (!productId || productId === '0') {
                        alert('Error: Invalid product. Please try again.');
                        return;
                    }

                    const isEnrollButton = this.closest('.enroll-course') !== null;
                    if (isEnrollButton && !selectedDates[postId]) {
                        alert('Please select a start date before adding to cart.');
                        return;
                    }

                    this.classList.add('loading');

                    const addToCart = (productId, startDate = null) => {
                        return new Promise((resolve, reject) => {
                            jQuery.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                                action: 'woocommerce_add_to_cart',
                                product_id: productId,
                                quantity: 1,
                                start_date: startDate,
                                security: '<?php echo wp_create_nonce('woocommerce_add_to_cart'); ?>'
                            }, function(response) {
                                if (response && response.fragments && response.cart_hash) {
                                    resolve(response);
                                } else {
                                    reject(new Error('Failed to add product to cart.'));
                                }
                            }).fail(function(jqXHR, textStatus, errorThrown) {
                                reject(new Error('Error: ' + textStatus));
                            });
                        });
                    };

                    try {
                        const response = await addToCart(productId, isEnrollButton ? selectedDates[postId] : null);
                        jQuery(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                        jQuery(document.body).trigger('wc_fragment_refresh');
                        setTimeout(() => {
                            jQuery(document.body).trigger('wc_fragment_refresh');
                            jQuery(document).trigger('fkcart_open_cart');
                        }, 1000);
                        const cartOpened = await openFunnelKitCart();
                        if (!cartOpened && !wasCartOpened && !wasCartManuallyClosed) {
                            alert('The cart may not have updated. Please check the cart manually.');
                        }
                    } catch (error) {
                        alert('Error adding product to cart: ' + error.message);
                    } finally {
                        this.classList.remove('loading');
                    }
                });
            });

            document.querySelectorAll('.fkcart-close, .fkcart-cart-close, .cart-close, .fkcart-close-btn, .fkcart-panel-close, [data-fkcart-close], .close-cart').forEach(close => {
                close.addEventListener('click', () => wasCartManuallyClosed = true);
            });

            document.querySelectorAll('.fkcart-cart-toggle, .cart-toggle').forEach(toggle => {
                toggle.addEventListener('click', () => {
                    jQuery(document.body).trigger('wc_fragment_refresh');
                    jQuery(document.body).trigger('wc_update_cart');
                });
            });

            document.querySelectorAll('.countdown-timer').forEach(countdown => {
                const launchDate = countdown.dataset.launchDate;
                if (launchDate) {
                    const updateCountdown = () => {
                        const now = new Date().getTime();
                        const timeDiff = new Date(launchDate).getTime() - now;
                        if (timeDiff <= 0) {
                            countdown.innerHTML = '<span class="launch-soon">Launched!</span>';
                            return;
                        }
                        const days = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
                        const hours = Math.floor((timeDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                        const seconds = Math.floor((timeDiff % (1000 * 60)) / 1000);
                        ['days', 'hours', 'minutes', 'seconds'].forEach(unit => {
                            const element = countdown.querySelector(`.time-unit[data-unit="${unit}"] .time-value`);
                            if (element) element.textContent = `${Math.max(0, Math.floor(eval(unit === 'days' ? days : unit === 'hours' ? hours : unit === 'minutes' ? minutes : seconds))).toString().padStart(2, '0')}`;
                        });
                    };
                    updateCountdown();
                    setInterval(updateCountdown, 1000);
                }
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

// Add start date to cart item data
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
        $cart_item_data['start_date'] = sanitize_text_field($_POST['start_date']);
    }
    return $cart_item_data;
}, 10, 3);

// Save start date to order item meta
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values) {
    if (isset($values['start_date']) && !empty($values['start_date'])) {
        $item->add_meta_data('Start Date', $values['start_date'], true);
    }
}, 10, 3);

// Display start date in admin order details
add_action('woocommerce_order_item_meta_end', function ($item_id, $item, $order) {
    $start_date = $item->get_meta('Start Date');
    if ($start_date) {
        echo '<p><strong>Start Date:</strong> ' . esc_html($start_date) . '</p>';
    }
}, 10, 3);

// Add start date to customer order email
add_action('woocommerce_email_order_meta', function ($order) {
    foreach ($order->get_items() as $item_id => $item) {
        $start_date = $item->get_meta('Start Date');
        if ($start_date) {
            echo '<p><strong>Start Date:</strong> ' . esc_html($start_date) . '</p>';
        }
    }
}, 10, 1);

add_shortcode('course_box_manager', 'course_box_manager_shortcode');
add_filter('the_content', 'inject_course_box_manager');
function inject_course_box_manager($content) {
    if (is_singular('course')) {
        $post_id = get_the_ID();
        $output = do_shortcode('[course_box_manager]');
        return $content . $output;
    }
    return $content;
}
