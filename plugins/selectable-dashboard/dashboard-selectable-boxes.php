<?php
/*
 * Plugin Name: Course Box Manager
 * Description: A comprehensive plugin to manage and display selectable boxes for course post types with dashboard control, countdowns, start date selection, and WooCommerce integration.
 * Version: 1.4.1
 * Author: Carlos Murillo
 * Author URI: https://lucumaagency.com/
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register course_group taxonomy
add_action('init', 'register_course_group_taxonomy');
function register_course_group_taxonomy() {
    register_taxonomy('course_group', ['stm-courses', 'product', 'course'], [
        'labels' => [
            'name' => __('Course Groups'),
            'singular_name' => __('Course Group'),
        ],
        'hierarchical' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_admin_column' => true,
        'rewrite' => ['slug' => 'course-group'],
    ]);
}

// Register instructor CPT
add_action('init', 'register_instructor_cpt');
function register_instructor_cpt() {
    register_post_type('instructor', [
        'labels' => [
            'name' => __('Instructors'),
            'singular_name' => __('Instructor'),
        ],
        'public' => true,
        'supports' => ['title', 'editor', 'custom-fields'],
    ]);
}

// Enable FunnelKit Cart for course post type
add_filter('fkcart_disabled_post_types', function ($post_types) {
    $post_types = array_filter($post_types, function ($i) {
        return $i !== 'course';
    });
    return $post_types;
});

// Add admin menu for dashboard
add_action('admin_menu', 'course_box_manager_menu');
function course_box_manager_menu() {
    add_menu_page(
        'Course Box Manager',
        'Course Boxes',
        'edit_posts', // Instructors (with edit_posts capability) can view
        'course-box-manager',
        'course_box_manager_page',
        'dashicons-list-view',
        20
    );
}

// Dashboard page content
function course_box_manager_page() {
    // Get all courses
    $courses = get_posts([
        'post_type' => 'stm-courses',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <input type="text" id="course-search" placeholder="Search courses...">
        <?php if (!isset($_GET['course_id'])) : ?>
            <!-- Main View: Courses Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Instructors</th>
                        <th>Box State</th>
                        <th>Stock</th>
                        <th>Dates</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course_id) :
                        $title = get_the_title($course_id);
                        $instructors = get_post_meta($course_id, 'course_instructors', true) ?: [];
                        $instructor_names = array_map(function($id) { return get_the_title($id); }, $instructors);
                        $box_state = get_post_meta($course_id, 'box_state', true) ?: 'enroll-course';
                        $webinar_stock = get_field('course_stock', $course_id) ?: 0;
                        $dates = get_field('course_dates', $course_id) ?: [];
                        $is_group_course = preg_match('/( - G\d+|\(G\d+\))$/', $title);
                    ?>
                        <tr>
                            <td><a href="?page=course-box-manager&course_id=<?php echo esc_attr($course_id); ?>"><?php echo esc_html($title); ?></a></td>
                            <td><?php echo esc_html(implode(', ', $instructor_names)); ?></td>
                            <td><?php echo esc_html(ucfirst(str_replace('-', ' ', $box_state))); ?></td>
                            <td><?php echo $is_group_course ? esc_html($webinar_stock) : '-'; ?></td>
                            <td><?php echo $is_group_course && !empty($dates) ? esc_html(implode(', ', array_column($dates, 'date'))) : '-'; ?></td>
                            <td>
                                <button class="button button-primary edit-course-settings" data-course-id="<?php echo esc_attr($course_id); ?>">Edit</button>
                                <button class="button delete-course" data-course-id="<?php echo esc_attr($course_id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button class="button button-primary add-course" style="margin-top: 10px;">Add Course</button>
            <button class="button button-primary add-course-group" style="margin-top: 10px; margin-left: 10px;">Add Course Group</button>
        <?php else : ?>
            <!-- Detail View: Course Settings -->
            <?php
            $course_id = intval($_GET['course_id']);
            $title = get_the_title($course_id);
            $instructors = get_post_meta($course_id, 'course_instructors', true) ?: [];
            $box_state = get_post_meta($course_id, 'box_state', true) ?: 'enroll-course';
            $webinar_stock = get_field('course_stock', $course_id) ?: 0;
            $dates = get_field('course_dates', $course_id) ?: [];
            $is_group_course = preg_match('/( - G\d+|\(G\d+\))$/', $title);
            $terms = wp_get_post_terms($course_id, 'course_group');
            $group_id = !empty($terms) ? $terms[0]->term_id : 0;
            $group_name = $group_id ? get_term($group_id, 'course_group')->name : 'None';
            $selling_page = get_posts([
                'post_type' => 'course',
                'posts_per_page' => 1,
                'tax_query' => [
                    [
                        'taxonomy' => 'course_group',
                        'field' => 'term_id',
                        'terms' => $group_id,
                    ],
                ],
            ]);
            $selling_page_id = !empty($selling_page) ? $selling_page[0]->ID : 0;
            ?>
            <h2>Course: <?php echo esc_html($title); ?></h2>
            <a href="?page=course-box-manager" class="button">Back to Courses</a>
            <div style="margin-top: 20px;">
                <h3>Course Settings</h3>
                <table class="form-table">
                    <tr>
                        <th><label>Course Group</label></th>
                        <td>
                            <select id="course-group" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="0">None</option>
                                <?php
                                $groups = get_terms(['taxonomy' => 'course_group', 'hide_empty' => false]);
                                foreach ($groups as $group) {
                                    echo '<option value="' . esc_attr($group->term_id) . '"' . ($group_id == $group->term_id ? ' selected' : '') . '>' . esc_html($group->name) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Instructors</label></th>
                        <td>
                            <select class="instructor-select" data-course-id="<?php echo esc_attr($course_id); ?>" multiple>
                                <?php
                                $all_instructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1]);
                                foreach ($all_instructors as $instructor) {
                                    echo '<option value="' . esc_attr($instructor->ID) . '"' . (in_array($instructor->ID, $instructors) ? ' selected' : '') . '>' . esc_html($instructor->post_title) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Box State</label></th>
                        <td>
                            <select class="box-state-select" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="enroll-course" <?php echo $box_state === 'enroll-course' ? 'selected' : ''; ?>>Enroll in the Live Course</option>
                                <option value="buy-course" <?php echo $box_state === 'buy-course' ? 'selected' : ''; ?>>Buy This Course</option>
                                <option value="waitlist" <?php echo $box_state === 'waitlist' ? 'selected' : ''; ?>>Waitlist</option>
                                <option value="soldout" <?php echo $box_state === 'soldout' ? 'selected' : ''; ?>>Sold Out</option>
                            </select>
                        </td>
                    </tr>
                    <?php if ($is_group_course) : ?>
                        <tr>
                            <th><label>Stock</label></th>
                            <td>
                                <input type="number" class="webinar-stock" data-course-id="<?php echo esc_attr($course_id); ?>" value="<?php echo esc_attr($webinar_stock); ?>" min="0">
                            </td>
                        </tr>
                        <tr>
                            <th><label>Dates</label></th>
                            <td>
                                <div class="date-list" data-course-id="<?php echo esc_attr($course_id); ?>">
                                    <?php foreach ($dates as $index => $date) : ?>
                                        <div>
                                            <input type="text" class="course-date" value="<?php echo esc_attr($date['date']); ?>" data-index="<?php echo esc_attr($index); ?>" placeholder="Enter date (e.g., 2025-08-01)">
                                            <button class="remove-date" data-index="<?php echo esc_attr($index); ?>">Remove</button>
                                        </div>
                                    <?php endforeach; ?>
                                    <button class="add-date">Add Date</button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th><label>Selling Page</label></th>
                        <td>
                            <select id="selling-page" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="0">None</option>
                                <?php
                                $selling_pages = get_posts(['post_type' => 'course', 'posts_per_page' => -1]);
                                foreach ($selling_pages as $page) {
                                    echo '<option value="' . esc_attr($page->ID) . '"' . ($selling_page_id == $page->ID ? ' selected' : '') . '>' . esc_html($page->post_title) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <button class="button button-primary save-course-settings" data-course-id="<?php echo esc_attr($course_id); ?>">Save Settings</button>
            </div>
        <?php endif; ?>

        <!-- Modal for Adding Course -->
        <div id="add-course-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="modal-close">×</span>
                <h2>Add Course</h2>
                <label>Course Title (include suffix, e.g., VOD, G1, (G1)):</label>
                <input type="text" id="course-title" placeholder="e.g., How to do AI - VOD or How to do AI (G1)">
                <label>Course Group:</label>
                <select id="course-group">
                    <option value="0">None</option>
                    <?php
                    $groups = get_terms(['taxonomy' => 'course_group', 'hide_empty' => false]);
                    foreach ($groups as $group) {
                        echo '<option value="' . esc_attr($group->term_id) . '">' . esc_html($group->name) . '</option>';
                    }
                    ?>
                </select>
                <label>Instructors:</label>
                <select id="course-instructors" multiple>
                    <?php
                    $all_instructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1]);
                    foreach ($all_instructors as $instructor) {
                        echo '<option value="' . esc_attr($instructor->ID) . '">' . esc_html($instructor->post_title) . '</option>';
                    }
                    ?>
                </select>
                <button class="button button-primary" id="save-new-course">Create Course</button>
            </div>
        </div>

        <!-- Modal for Adding Course Group -->
        <div id="add-course-group-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="modal-close">×</span>
                <h2>Add Course Group</h2>
                <label>Group Name:</label>
                <input type="text" id="course-group-name" placeholder="e.g., How to do AI">
                <button class="button button-primary" id="save-new-course-group">Create Group</button>
            </div>
        </div>

        <style>
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }
            .modal-content {
                background-color: #fff;
                margin: 15% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 500px;
                border-radius: 5px;
            }
            .modal-close {
                color: #aaa;
                float: right;
                font-size: 28px;
                cursor: pointer;
            }
            .modal-close:hover {
                color: black;
            }
            .box-state-select, .instructor-select, #course-group, #selling-page {
                width: 200px;
                padding: 5px;
                font-size: 14px;
            }
            .webinar-stock {
                width: 100px;
                padding: 5px;
                font-size: 14px;
            }
            .date-list {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .course-date {
                padding: 5px;
                font-size: 14px;
                width: 150px;
            }
            .remove-date, .add-date {
                padding: 5px 10px;
                font-size: 12px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
            }
            .remove-date {
                background: #ff4444;
                color: white;
            }
            .remove-date:hover {
                background: #cc0000;
            }
            .add-date {
                background: #4CAF50;
                color: white;
            }
            .add-date:hover {
                background: #45a049;
            }
            #course-search, #course-group-name {
                margin-bottom: 10px;
                padding: 5px;
                width: 300px;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const addCourseModal = document.getElementById('add-course-modal');
                const addCourseGroupModal = document.getElementById('add-course-group-modal');
                const closeButtons = document.getElementsByClassName('modal-close');

                // Open Add Course Modal
                document.querySelectorAll('.add-course').forEach(button => {
                    button.addEventListener('click', function() {
                        addCourseModal.style.display = 'block';
                    });
                });

                // Open Add Course Group Modal
                document.querySelectorAll('.add-course-group').forEach(button => {
                    button.addEventListener('click', function() {
                        addCourseGroupModal.style.display = 'block';
                    });
                });

                // Close Modals
                Array.from(closeButtons).forEach(button => {
                    button.addEventListener('click', function() {
                        addCourseModal.style.display = 'none';
                        addCourseGroupModal.style.display = 'none';
                    });
                });
                window.addEventListener('click', function(event) {
                    if (event.target === addCourseModal || event.target === addCourseGroupModal) {
                        addCourseModal.style.display = 'none';
                        addCourseGroupModal.style.display = 'none';
                    }
                });

                // Save New Course
                document.getElementById('save-new-course').addEventListener('click', function() {
                    const groupId = document.getElementById('course-group').value;
                    const title = document.getElementById('course-title').value;
                    const instructors = Array.from(document.getElementById('course-instructors').selectedOptions).map(option => option.value);
                    fetch(ajaxurl + '?action=create_new_course', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'group_id=' + groupId + '&title=' + encodeURIComponent(title) + '&instructors=' + encodeURIComponent(JSON.stringify(instructors)) + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Course created successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.data);
                        }
                    });
                });

                // Save New Course Group
                document.getElementById('save-new-course-group').addEventListener('click', function() {
                    const groupName = document.getElementById('course-group-name').value;
                    if (!groupName.trim()) {
                        alert('Please enter a group name.');
                        return;
                    }
                    fetch(ajaxurl + '?action=create_new_course_group', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'group_name=' + encodeURIComponent(groupName) + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Course group created successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.data);
                        }
                    });
                });

                // Save Course Settings
                document.querySelectorAll('.save-course-settings').forEach(button => {
                    button.addEventListener('click', function() {
                        const courseId = this.getAttribute('data-course-id');
                        const groupId = document.querySelector(`#course-group[data-course-id="${courseId}"]`).value;
                        const boxState = document.querySelector(`.box-state-select[data-course-id="${courseId}"]`).value;
                        const instructors = Array.from(document.querySelector(`.instructor-select[data-course-id="${courseId}"]`).selectedOptions).map(option => option.value);
                        const stock = document.querySelector(`.webinar-stock[data-course-id="${courseId}"]`)?.value || '';
                        const dates = Array.from(document.querySelectorAll(`.date-list[data-course-id="${courseId}"] .course-date`)).map(input => input.value).filter(date => date.trim() !== '');
                        const sellingPageId = document.querySelector(`#selling-page[data-course-id="${courseId}"]`).value;
                        fetch(ajaxurl + '?action=save_course_settings', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&group_id=' + groupId + '&box_state=' + boxState + '&instructors=' + encodeURIComponent(JSON.stringify(instructors)) + '&stock=' + stock + '&dates=' + encodeURIComponent(JSON.stringify(dates)) + '&selling_page_id=' + sellingPageId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Settings saved successfully!');
                                location.href = '?page=course-box-manager';
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Delete Course
                document.querySelectorAll('.delete-course').forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to delete this course?')) return;
                        const courseId = this.getAttribute('data-course-id');
                        fetch(ajaxurl + '?action=delete_course', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Course deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Add/Remove Dates
                document.querySelectorAll('.date-list').forEach(container => {
                    const courseId = container.getAttribute('data-course-id');
                    const addDateButton = container.querySelector('.add-date');
                    addDateButton.addEventListener('click', function() {
                        const dateList = container;
                        const index = dateList.querySelectorAll('.course-date').length;
                        const wrapper = document.createElement('div');
                        const newDateInput = document.createElement('input');
                        newDateInput.type = 'text';
                        newDateInput.className = 'course-date';
                        newDateInput.setAttribute('data-index', index);
                        newDateInput.placeholder = 'Enter date (e.g., 2025-08-01)';
                        const removeButton = document.createElement('button');
                        removeButton.className = 'remove-date';
                        removeButton.setAttribute('data-index', index);
                        removeButton.textContent = 'Remove';
                        removeButton.addEventListener('click', function() {
                            wrapper.remove();
                        });
                        wrapper.appendChild(newDateInput);
                        wrapper.appendChild(removeButton);
                        dateList.insertBefore(wrapper, addDateButton);
                    });

                    container.querySelectorAll('.remove-date').forEach(button => {
                        button.addEventListener('click', function() {
                            button.parentElement.remove();
                        });
                    });
                });

                // Search Courses
                document.getElementById('course-search').addEventListener('input', function() {
                    const search = this.value.toLowerCase();
                    document.querySelectorAll('.wp-list-table tbody tr').forEach(row => {
                        const courseName = row.cells[0].textContent.toLowerCase();
                        row.style.display = courseName.includes(search) ? '' : 'none';
                    });
                });

                // Edit Course Settings
                document.querySelectorAll('.edit-course-settings').forEach(button => {
                    button.addEventListener('click', function() {
                        window.location.href = '?page=course-box-manager&course_id=' + this.getAttribute('data-course-id');
                    });
                });
            });
        </script>
    </div>
    <?php
}

// AJAX Handler for Creating Course Group
add_action('wp_ajax_create_new_course_group', 'create_new_course_group');
function create_new_course_group() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $group_name = sanitize_text_field($_POST['group_name']);
    $term = wp_insert_term($group_name, 'course_group');
    if (!is_wp_error($term)) {
        wp_send_json_success();
    } else {
        wp_send_json_error($term->get_error_message());
    }
}

// AJAX Handlers
add_action('wp_ajax_create_new_course', 'create_new_course');
function create_new_course() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $group_id = intval($_POST['group_id']);
    $title = sanitize_text_field($_POST['title']);
    $instructors = json_decode(stripslashes($_POST['instructors']), true);

    $course_id = wp_insert_post([
        'post_title' => $title,
        'post_type' => 'stm-courses',
        'post_status' => 'publish',
    ]);

    if ($course_id) {
        if ($group_id) {
            wp_set_post_terms($course_id, [$group_id], 'course_group');
        }
        update_post_meta($course_id, 'course_instructors', $instructors);

        // Create WooCommerce product
        $product = new WC_Product_Simple();
        $product->set_name($title);
        $product->set_status('publish');
        $product->set_virtual(true);
        $product->set_price(get_field('course_price', $course_id) ?: 749.99);
        $product_id = $product->save();
        if ($group_id) {
            wp_set_post_terms($product_id, [$group_id], 'course_group');
        }
        update_post_meta($course_id, 'linked_product_id', $product_id);

        // Update instructor meta
        foreach ($instructors as $instructor_id) {
            $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
            if (!in_array($course_id, $courses)) {
                $courses[] = $course_id;
                update_post_meta($instructor_id, 'instructor_courses', $courses);
            }
        }

        wp_send_json_success();
    } else {
        wp_send_json_error('Could not create course.');
    }
}

add_action('wp_ajax_save_course_settings', 'save_course_settings');
function save_course_settings() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $course_id = intval($_POST['course_id']);
    $group_id = intval($_POST['group_id']);
    $box_state = sanitize_text_field($_POST['box_state']);
    $instructors = json_decode(stripslashes($_POST['instructors']), true);
    $stock = sanitize_text_field($_POST['stock']);
    $dates = json_decode(stripslashes($_POST['dates']), true);
    $selling_page_id = intval($_POST['selling_page_id']);

    // Update course group
    if ($group_id) {
        wp_set_post_terms($course_id, [$group_id], 'course_group');
    } else {
        wp_set_post_terms($course_id, [], 'course_group');
    }

    // Update selling page
    $current_terms = wp_get_post_terms($course_id, 'course_group');
    $current_group_id = !empty($current_terms) ? $current_terms[0]->term_id : 0;
    if ($current_group_id) {
        $existing_page = get_posts([
            'post_type' => 'course',
            'posts_per_page' => 1,
            'tax_query' => [
                [
                    'taxonomy' => 'course_group',
                    'field' => 'term_id',
                    'terms' => $current_group_id,
                ],
            ],
        ]);
        if ($existing_page && $existing_page[0]->ID != $selling_page_id) {
            wp_set_post_terms($existing_page[0]->ID, [], 'course_group');
        }
        if ($selling_page_id) {
            wp_set_post_terms($selling_page_id, [$current_group_id], 'course_group');
        }
    }

    update_post_meta($course_id, 'box_state', $box_state);
    update_post_meta($course_id, 'course_instructors', $instructors);
    $product_id = get_post_meta($course_id, 'linked_product_id', true);
    if ($product_id && $stock !== '') {
        update_post_meta($product_id, '_stock', $stock);
        update_post_meta($product_id, '_manage_stock', 'yes');
        if ($group_id) {
            wp_set_post_terms($product_id, [$group_id], 'course_group');
        } else {
            wp_set_post_terms($product_id, [], 'course_group');
        }
    }
    if ($dates) {
        update_field('course_dates', array_map(function($date) { return ['date' => $date]; }, $dates), $course_id);
    } else {
        delete_field('course_dates', $course_id);
    }

    // Update instructor meta
    foreach ($instructors as $instructor_id) {
        $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
        if (!in_array($course_id, $courses)) {
            $courses[] = $course_id;
            update_post_meta($instructor_id, 'instructor_courses', $courses);
        }
    }

    wp_send_json_success();
}

add_action('wp_ajax_delete_course', 'delete_course');
function delete_course() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $course_id = intval($_POST['course_id']);
    $product_id = get_post_meta($course_id, 'linked_product_id', true);
    if ($product_id) {
        wp_delete_post($product_id, true);
    }
    wp_delete_post($course_id, true);
    wp_send_json_success();
}

// Shortcode to render boxes
function course_box_manager_shortcode() {
    global $post;
    $post_id = $post ? $post->ID : 0;
    $terms = wp_get_post_terms($post_id, 'course_group');
    $group_id = !empty($terms) ? $terms[0]->term_id : 0;
    if (!$group_id) return '';

    $courses = get_posts([
        'post_type' => 'stm-courses',
        'posts_per_page' => -1,
        'tax_query' => [
            [
                'taxonomy' => 'course_group',
                'field' => 'term_id',
                'terms' => $group_id,
            ],
        ],
    ]);

    ob_start();
    ?>
    <div class="selectable-box-container">
        <div class="box-container">
            <?php foreach ($courses as $course) :
                $course_id = $course->ID;
                $box_state = get_post_meta($course_id, 'box_state', true) ?: 'enroll-course';
                $course_product_id = get_post_meta($course_id, 'linked_product_id', true);
                $course_price = get_field('course_price', $course_id) ?: 749.99;
                $enroll_price = get_field('enroll_price', $course_id) ?: 1249.99;
                $available_dates = get_field('course_dates', $course_id) ?: [];
                $available_dates = array_column($available_dates, 'date');
                $is_out_of_stock = $course_product_id && function_exists('wc_get_product') && !wc_get_product($course_product_id)->is_in_stock();
                $launch_date = $course_product_id ? apply_filters('wc_launch_date_get', '', $course_product_id) : '';
                $show_countdown = !empty($launch_date) && strtotime($launch_date) > current_time('timestamp');
                $is_group_course = preg_match('/( - G\d+|\(G\d+\))$/', get_the_title($course_id));
            ?>
                <?php if ($box_state === 'soldout' && $is_out_of_stock) : ?>
                    <div class="box soldout-course">
                        <div class="soldout-header"><span>THE COURSE IS SOLD OUT</span></div>
                        <h3>Join Waitlist for Free</h3>
                        <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
                        [contact-form-7 id="c2b4e27" title="Course Sold Out"]
                        <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
                    </div>
                <?php elseif ($box_state === 'waitlist' && empty($available_dates) && !$is_out_of_stock) : ?>
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
                            <div class="countdown-timer" id="countdown-timer-<?php echo esc_attr($course_id); ?>" data-launch-date="<?php echo esc_attr($launch_date); ?>">
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
                                <?php } else { ?>
                                    <span class="launch-soon">Launched!</span>
                                <?php } ?>
                            </div>
                        </div>
                        <h3>Join Waitlist for Free</h3>
                        <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
                        [contact-form-7 id="255b390" title="Course Launch"]
                        <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
                    </div>
                <?php elseif ($box_state === 'buy-course' && !$is_out_of_stock && !$show_countdown) : ?>
                    <div class="box buy-course<?php echo $is_group_course ? '' : ' selected'; ?>" data-course-id="<?php echo esc_attr($course_id); ?>" onclick="selectBox(this, 'box1', <?php echo esc_attr($course_id); ?>)">
                        <div class="statebox">
                            <div class="circlecontainer" style="display: <?php echo $is_group_course ? 'none' : 'flex'; ?>;">
                                <div class="outer-circle">
                                    <div class="middle-circle">
                                        <div class="inner-circle"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="circle-container" style="display: <?php echo $is_group_course ? 'flex' : 'none'; ?>;">
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
                <?php elseif ($box_state === 'enroll-course' && !$is_out_of_stock && !$show_countdown && !empty($available_dates) && $is_group_course) : ?>
                    <div class="box enroll-course<?php echo !$is_group_course ? '' : ' selected'; ?>" data-course-id="<?php echo esc_attr($course_id); ?>" onclick="selectBox(this, 'box2', <?php echo esc_attr($course_id); ?>)">
                        <div class="statebox">
                            <div class="circlecontainer" style="display: <?php echo !$is_group_course ? 'none' : 'flex'; ?>;">
                                <div class="outer-circle">
                                    <div class="middle-circle">
                                        <div class="inner-circle"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="circle-container" style="display: <?php echo !$is_group_course ? 'flex' : 'none'; ?>;">
                                <div class="circle"></div>
                            </div>
                            <div>
                                <h3>Enroll in the Live Course</h3>
                                <p>$<?php echo esc_html(number_format($enroll_price, 2)); ?> USD</p>
                                <p class="description">Join weekly live sessions with feedback and expert mentorship.</p>
                            </div>
                        </div>
                        <hr class="divider">
                        <div class="start-dates" style="display: <?php echo !$is_group_course ? 'none' : 'block'; ?>;">
                            <p class="choose-label">Choose a starting date</p>
                            <div class="date-options">
                                <?php foreach ($available_dates as $date) : ?>
                                    <button class="date-btn" data-date="<?php echo esc_attr($date); ?>"><?php echo esc_html($date); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button class="add-to-cart-button" data-product-id="<?php echo esc_attr($course_product_id); ?>">
                            <span class="button-text">Enroll Now</span>
                            <span class="loader" style="display: none;"></span>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="text-outside-box">
            <p style="text-align: center; letter-spacing: 0.9px; margin-top: 30px; font-weight: 200; font-size: 12px;">
                <span style="font-weight: 500; font-size: 14px;">Missing a Class?</span>
                <br>No worries! All live courses will be recorded and made available on-demand to all students.
            </p>
        </div>
    </div>

    <style>
        .selectable-box-container { max-width: 1200px; margin: 0 auto; }
        .box-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }
        .box {
            max-width: 350px;
            width: 100%;
            padding: 15px;
            background: transparent;
            border: 2px solid #9B9FAA7A;
            border-radius: 15px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        .box.selected {
            background: linear-gradient(180deg, rgba(242, 46, 190, 0.2), rgba(170, 0, 212, 0.2));
            border: none;
            padding: 16px 12px;
        }
        .box:not(.selected) { opacity: 0.7; }
        .box h3 { color: #fff; margin-left: 10px; margin-top: 0; font-size: 1.5em; }
        .box .price { font-family: 'Poppins', sans-serif; font-weight: 500; font-size: 26px; }
        .box .description { font-size: 12px; color: rgba(255, 255, 255, 0.64); margin: 10px 0; }
        .box button {
            width: 100%;
            padding: 5px 12px;
            background-color: rgba(255, 255, 255, 0.08);
            border: none;
            border-radius: 4px;
            color: white;
            font-size: 12px;
            cursor: pointer;
        }
        .box button:hover { background-color: rgba(255, 255, 255, 0.2); }
        .divider { border-top: 1px solid rgba(255, 255, 255, 0.2); margin: 20px 0; }
        .soldout-course, .course-launch { background: #2a2a2a; text-align: center; }
        .soldout-header { background: #ff3e3e; padding: 10px; border-radius: 10px; margin-bottom: 10px; }
        .countdown { background: #800080; padding: 10px; border-radius: 10px; margin-bottom: 10px; display: flex; gap: 15px; justify-content: center; }
        .countdown-timer { display: flex; gap: 15px; }
        .time-unit { display: flex; flex-direction: column; align-items: center; }
        .time-value { font-size: 1.5em; font-weight: bold; }
        .time-label { font-size: 0.9em; color: rgba(255, 255, 255, 0.8); }
        .terms { font-size: 0.7em; color: #aaa; }
        .start-dates { display: none; margin-top: 15px; animation: fadeIn 0.4s ease; }
        .box.selected .start-dates { display: block; }
        .statebox { display: flex; }
        .outer-circle { width: 16px; height: 16px; border-radius: 50%; background-color: #DE04A4; border: 1.45px solid #DE04A4; display: flex; align-items: center; justify-content: center; }
        .middle-circle { width: 11.77px; height: 11.77px; border-radius: 50%; background-color: #050505; display: flex; align-items: center; justify-content: center; }
        .inner-circle { width: 6.16px; height: 6.16px; border-radius: 50%; background-color: #DE04A4; }
        .circlecontainer { margin: 6px 7px; }
        .circle-container { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
        .circle { width: 14px; height: 14px; border-radius: 50%; border: 2px solid rgba(155, 159, 170, 0.24); }
        .box:not(.selected) .circlecontainer { display: none; }
        .box:not(.selected) .circle-container { display: flex; }
        .box.selected .circle-container { display: none; }
        .box.selected .circlecontainer { display: flex; }
        .choose-label { font-size: 0.95em; margin-bottom: 10px; color: #fff; }
        .date-options { display: flex; flex-wrap: wrap; gap: 4px; }
        .date-btn { width: 68px; padding: 5px 8px; border: none; border-radius: 25px; background-color: rgba(255, 255, 255, 0.08); color: white; cursor: pointer; }
        .date-btn:hover, .date-btn.selected { background-color: #cc3071; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 767px) { .box { padding: 10px; } .box h3 { font-size: 1.2em; } }
        .add-to-cart-button {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 40px;
            padding: 5px 12px;
            background-color: rgba(255, 255, 255, 0.08);
            border: none;
            border-radius: 4px;
            color: white;
            font-size: 12px;
            cursor: pointer;
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
        }
        @keyframes spin { to { transform: translate(-50%, -50%) rotate(360deg); } }
    </style>

    <script>
        let selectedDates = {};
        let wasCartOpened = false;
        let wasCartManuallyClosed = false;

        function selectBox(element, boxId, courseId) {
            const boxes = element.closest('.box-container').querySelectorAll('.box');
            boxes.forEach(box => {
                box.classList.remove('selected');
                const circleContainer = box.querySelector('.circle-container');
                const circlecontainer = box.querySelector('.circlecontainer');
                const startDates = box.querySelector('.start-dates');
                if (circleContainer) circleContainer.style.display = 'flex';
                if (circlecontainer) circlecontainer.style.display = 'none';
                if (startDates) startDates.style.display = 'none';
            });
            element.classList.add('selected');
            const selectedCircleContainer = element.querySelector('.circle-container');
            const selectedCirclecontainer = element.querySelector('.circlecontainer');
            const selectedStartDates = element.querySelector('.start-dates');
            if (selectedCircleContainer) selectedCircleContainer.style.display = 'none';
            if (selectedCirclecontainer) selectedCirclecontainer.style.display = 'flex';
            if (selectedStartDates) selectedStartDates.style.display = 'block';
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });

            if (element.classList.contains('enroll-course')) {
                const firstDateBtn = selectedStartDates.querySelector('.date-btn');
                if (firstDateBtn && !selectedDates[courseId]) {
                    firstDateBtn.classList.add('selected');
                    selectedDates[courseId] = firstDateBtn.getAttribute('data-date') || firstDateBtn.textContent.trim();
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

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.date-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const courseId = this.closest('.box').getAttribute('data-course-id');
                    document.querySelectorAll('.date-btn').forEach(b => b.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedDates[courseId] = this.getAttribute('data-date') || this.textContent.trim();
                });
            });

            document.querySelectorAll('.add-to-cart-button').forEach(button => {
                button.addEventListener('click', async function(e) {
                    e.preventDefault();
                    const productId = this.getAttribute('data-product-id');
                    const courseId = this.closest('.box').getAttribute('data-course-id');
                    if (!productId || productId === '0') {
                        alert('Error: Invalid product.');
                        return;
                    }

                    const isEnrollButton = this.closest('.enroll-course') !== null;
                    if (isEnrollButton && !selectedDates[courseId]) {
                        alert('Please select a start date.');
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
                            }).fail(function(jqXHR, textStatus) {
                                reject(new Error('Error: ' + textStatus));
                            });
                        });
                    };

                    try {
                        const response = await addToCart(productId, isEnrollButton ? selectedDates[courseId] : null);
                        jQuery(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                        jQuery(document.body).trigger('wc_fragment_refresh');
                        setTimeout(() => {
                            jQuery(document.body).trigger('wc_fragment_refresh');
                            jQuery(document).trigger('fkcart_open_cart');
                        }, 1000);
                        const cartOpened = await openFunnelKitCart();
                        if (!cartOpened && !wasCartOpened && !wasCartManuallyClosed) {
                            alert('The cart may not have updated. Please check manually.');
                        }
                    } catch (error) {
                        alert('Error adding to cart: ' + error.message);
                    } finally {
                        this.classList.remove('loading');
                    }
                });
            });

            document.querySelectorAll('.fkcart-close, .fkcart-cart-close, .cart-close, .fkcart-close-btn, .fkcart-panel-close, [data-fkcart-close], .close-cart').forEach(close => {
                close.addEventListener('click', () => wasCartManuallyClosed = true);
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
                        countdown.querySelector('.time-unit[data-unit="days"] .time-value').textContent = String(Math.max(0, days)).padStart(2, '0');
                        countdown.querySelector('.time-unit[data-unit="hours"] .time-value').textContent = String(Math.max(0, hours)).padStart(2, '0');
                        countdown.querySelector('.time-unit[data-unit="minutes"] .time-value').textContent = String(Math.max(0, minutes)).padStart(2, '0');
                        countdown.querySelector('.time-unit[data-unit="seconds"] .time-value').textContent = String(Math.max(0, seconds)).padStart(2, '0');
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
        $output = do_shortcode('[course_box_manager]');
        return $content . $output;
    }
    return $content;
}

// Sync course creation with product only (no selling page)
add_action('save_post_stm-courses', 'sync_course_to_product_and_page', 10, 3);
function sync_course_to_product_and_page($post_id, $post, $update) {
    if (wp_is_post_revision($post_id)) return;

    $terms = wp_get_post_terms($post_id, 'course_group');
    $group_id = !empty($terms) ? $terms[0]->term_id : 0;

    // Create WooCommerce product if not exists
    $product_id = get_post_meta($post_id, 'linked_product_id', true);
    if (!$product_id) {
        $product = new WC_Product_Simple();
        $product->set_name($post->post_title);
        $product->set_status('publish');
        $product->set_virtual(true);
        $product->set_price(get_field('course_price', $post_id) ?: 749.99);
        $product_id = $product->save();
        if ($group_id) {
            wp_set_post_terms($product_id, [$group_id], 'course_group');
        }
        update_post_meta($post_id, 'linked_product_id', $product_id);
    }
}

// Sync instructors bidirectionally
add_action('acf/save_post', 'sync_course_instructors', 20);
function sync_course_instructors($post_id) {
    if (get_post_type($post_id) !== 'stm-courses') return;
    $instructors = get_field('course_instructors', $post_id) ?: [];
    update_post_meta($post_id, 'course_instructors', $instructors);

    // Clear existing instructor courses
    $all_instructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1, 'fields' => 'ids']);
    foreach ($all_instructors as $instructor_id) {
        $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
        $courses = array_filter($courses, function($id) use ($post_id) { return $id != $post_id; });
        update_post_meta($instructor_id, 'instructor_courses', $courses);
    }

    // Update instructor meta
    foreach ($instructors as $instructor_id) {
        $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
        if (!in_array($post_id, $courses)) {
            $courses[] = $post_id;
            update_post_meta($instructor_id, 'instructor_courses', $courses);
        }
    }
}
?>
