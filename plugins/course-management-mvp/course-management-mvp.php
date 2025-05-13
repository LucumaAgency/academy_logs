<?php
/*
 * Plugin Name: Course Management
 * Description: Manages the creation and updating of course pages and associated products.
 * Version: 1.3
 * Author: Carlos Murillo
 * Author URI: https://lucumaagency.com/
 * License: GPL-2.0+
 */

// Define default image ID for fallback
define('COURSE_MGMT_DEFAULT_IMAGE_ID', 123); // Replace with actual attachment ID

// Include utilities if available
add_action('init', function() {
    $utilities_path = WP_PLUGIN_DIR . '/course-utilities/course-utilities.php';
    if (file_exists($utilities_path)) {
        require_once $utilities_path;
    } else {
        error_log('Course Utilities plugin not found at ' . $utilities_path);
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><strong>Course Management Error:</strong> The Course Utilities plugin is required but was not found. Please ensure it is installed and activated.</p>
            </div>
            <?php
        });
    }
});

/**
 * Register custom REST API endpoint for course data
 */
add_action('rest_api_init', function() {
    register_rest_route('custom/v1', '/courses/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => function(WP_REST_Request $request) {
            $course_id = $request->get_param('id');
            $course = get_post($course_id);

            if (!$course || $course->post_type !== 'stm-courses' || $course->post_status !== 'publish') {
                error_log('Invalid course ID ' . $course_id . ' or not an stm-courses post');
                return new WP_Error('invalid_course', 'Course not found or invalid', ['status' => 404]);
            }

            // MasterStudy LMS meta fields (adjust as needed)
            $meta = get_post_meta($course_id);
            $faqs = maybe_unserialize($meta['faqs'][0] ?? []);
            if (!is_array($faqs)) {
                $faqs = [];
            }

            $data = [
                'title' => $course->post_title,
                'content' => apply_filters('the_content', $course->post_content),
                'permalink' => get_permalink($course_id),
                'price' => floatval($meta['price'][0] ?? 0),
                'instructor' => sanitize_text_field($meta['course_instructor'][0] ?? ''),
                'categories' => wp_get_post_terms($course_id, 'stm_lms_course_taxonomy', ['fields' => 'names']) ?: [],
                'students' => absint($meta['current_students'][0] ?? 0),
                'views' => absint($meta['views'][0] ?? 0),
                'faqs' => array_map(function($faq) {
                    return [
                        'question' => sanitize_text_field($faq['question'] ?? ''),
                        'answer' => wp_kses_post($faq['answer'] ?? ''),
                    ];
                }, $faqs),
            ];

            error_log('Endpoint data for course_id ' . $course_id . ': ' . wp_json_encode(array_slice($data, 0, 100)));
            return $data;
        },
        'permission_callback' => '__return_true', // Public for testing
    ]);
});

/**
 * 1. Initial setup and retrieval of JSON data from the custom endpoint
 */
function get_course_json_data($course_id) {
    error_log('Fetching JSON data for course_id: ' . $course_id);
    $response = wp_remote_get(
        home_url("/wp-json/custom/v1/courses/{$course_id}"),
        [
            'timeout' => 10,
            'sslverify' => false,
        ]
    );

    if (is_wp_error($response)) {
        error_log('WP remote get error for course_id ' . $course_id . ': ' . $response->get_error_message());
        return false;
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
        error_log('Non-200 response for course_id ' . $course_id . ': ' . wp_remote_retrieve_response_code($response));
        return false;
    }

    $json = json_decode($response['body'], true);
    error_log('Full JSON data for course_id ' . $course_id . ': ' . print_r($json, true));
    error_log('Instructor value from JSON for course_id ' . $course_id . ': ' . (isset($json['instructor']) ? $json['instructor'] : 'Not set'));

    error_log('Instructor value from JSON for course_id ' . $course_id . ': ' . (isset($json['instructor']) ? $json['instructor'] : 'Not set'));

    // Define allowed HTML tags for WYSIWYG content
    $allowed_tags = [
        'h3' => ['class' => []],
        'p' => [],
        'ul' => [],
        'li' => [],
        'strong' => [],
        'br' => [],
        'em' => [],
        'b' => [],
        'i' => [],
        'span' => ['class' => []],
    ];

    $content = isset($json['content']) ? wp_kses($json['content'], $allowed_tags) : '';
    error_log('Sanitized content for course_id ' . $course_id . ': ' . substr($content, 0, 200) . '...');

    $faqs = isset($json['faqs']) && is_array($json['faqs']) ? array_map(function($faq) use ($allowed_tags) {
        return [
            'question' => isset($faq['question']) ? sanitize_text_field($faq['question']) : '',
            'answer' => isset($faq['answer']) ? wp_kses($faq['answer'], $allowed_tags) : '',
        ];
    }, $json['faqs']) : [];

    return [
        'title' => isset($json['title']) ? sanitize_text_field($json['title']) : '',
        'content' => $content,
        'permalink' => isset($json['permalink']) ? esc_url($json['permalink']) : '',
        'price' => isset($json['price']) ? floatval($json['price']) : 0,
        'instructor' => isset($json['instructor']) ? sanitize_text_field($json['instructor']) : '',
        'categories' => isset($json['categories']) && is_array($json['categories']) ? array_map('sanitize_text_field', $json['categories']) : [],
        'students' => isset($json['students']) ? absint($json['students']) : 0,
        'views' => isset($json['views']) ? absint($json['views']) : 0,
        'faqs' => $faqs,
        'background_image' => 0,
    ];
}

/**
 * 2. Retrieve the background image ID from the ACF field or use the featured image
 */
function get_background_image_from_course_page($course_page_id, $stm_course_id) {
    if (!function_exists('get_field')) {
        error_log('ACF get_field not available for course_page_id: ' . $course_page_id);
        return COURSE_MGMT_DEFAULT_IMAGE_ID;
    }

    $background_image_id = function_exists('get_cached_acf_field') ? get_cached_acf_field('field_682187522193c', $course_page_id) : get_field('field_682187522193c', $course_page_id);
    if ($background_image_id && is_numeric($background_image_id)) {
        return $background_image_id;
    }

    $raw_value = get_post_meta($course_page_id, 'course_background_image', true);
    if ($raw_value && filter_var($raw_value, FILTER_VALIDATE_URL)) {
        $background_image_id = function_exists('get_attachment_id_from_url') ? get_attachment_id_from_url($raw_value) : 0;
        if ($background_image_id) {
            update_field('field_682187522193c', $background_image_id, $course_page_id);
            return $background_image_id;
        }
    }

    $thumbnail_id = get_post_thumbnail_id($stm_course_id);
    $background_image_id = $thumbnail_id ?: COURSE_MGMT_DEFAULT_IMAGE_ID;
    update_field('field_682187522193c', $background_image_id, $course_page_id);
    return $background_image_id;
}

/**
 * 3. Create or update a course page and associated products
 */
function create_or_update_course_page($stm_course_id, $json) {
    error_log('Starting create_or_update_course_page for stm_course_id: ' . $stm_course_id);
    $existing_course_id = get_post_meta($stm_course_id, 'related_course_id', true);
    $course_page_id = $existing_course_id ?: 0;

    $slug = isset($json['permalink']) ? basename($json['permalink']) : '';
    $slug = sanitize_title($slug);

    $course_page_data = [
        'post_title' => $json['title'] ?: "Course {$stm_course_id}",
        'post_name' => $slug,
        'post_type' => 'course',
        'post_status' => 'publish',
        'post_author' => get_current_user_id(),
    ];

    if ($course_page_id) {
        $course_page_data['ID'] = $course_page_id;
        $course_page_id = wp_update_post($course_page_data, true);
        error_log('Updated course page ID: ' . (is_wp_error($course_page_id) ? 'Error: ' . $course_page_id->get_error_message() : $course_page_id));
    } else {
        $course_page_id = wp_insert_post($course_page_data, true);
        error_log('Inserted course page ID: ' . (is_wp_error($course_page_id) ? 'Error: ' . $course_page_id->get_error_message() : $course_page_id));
        if ($course_page_id && !is_wp_error($course_page_id)) {
            update_post_meta($stm_course_id, 'related_course_id', $course_page_id);
            update_post_meta($course_page_id, 'related_stm_course_id', $stm_course_id);
        }
    }

    if (!$course_page_id || is_wp_error($course_page_id)) {
        error_log('Failed to create/update course page for stm_course_id: ' . $stm_course_id);
        return false;
    }

    // Ensure ACF is loaded
    if (!function_exists('update_field')) {
        error_log('ACF plugin not found for course_page_id: ' . $course_page_id);
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><strong>Course Management Error:</strong> Advanced Custom Fields (ACF) plugin is required but not loaded. Please ensure it is installed and activated.</p>
            </div>
            <?php
        });
        return false;
    }

    // Verify field group
    $field_group = acf_get_field_group('group_681ccc3e039b0');
    if (!$field_group || !acf_get_field_group_visibility($field_group, ['post_id' => $course_page_id])) {
        error_log("Field group group_681ccc3e039b0 not assigned or not visible for course_page_id: $course_page_id");
        return $course_page_id;
    }

    $stm_course = get_post($stm_course_id);
    if ($stm_course && $stm_course->post_type === 'stm-courses') {
        $thumbnail_id = get_post_thumbnail_id($stm_course_id);
        if ($thumbnail_id) {
            set_post_thumbnail($course_page_id, $thumbnail_id);
        } else {
            set_post_thumbnail($course_page_id, COURSE_MGMT_DEFAULT_IMAGE_ID);
        }
    } else {
        error_log('Invalid stm_course for ID: ' . $stm_course_id);
    }

    if (class_exists('WooCommerce')) {
        $course_title = $json['title'] ?: "Course {$stm_course_id}";
        
        $course_product = [
            'post_title' => $course_title,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ];
        $course_product_id = wp_insert_post($course_product, true);
        if ($course_product_id && !is_wp_error($course_product_id)) {
            wp_set_object_terms($course_product_id, 'simple', 'product_type');
            update_post_meta($course_product_id, '_visibility', 'visible');
            update_post_meta($course_product_id, '_stock_status', 'instock');
            update_post_meta($course_product_id, '_price', $json['price'] ?? 0);
            update_post_meta($course_product_id, '_regular_price', $json['price'] ?? 0);
            $course_product_link = home_url("/?add-to-cart={$course_product_id}&quantity=1");
            update_field('field_6821879221940', $course_product_link, $course_page_id);
            error_log('Created course product ID: ' . $course_product_id);
        } else {
            error_log('Failed to create course product: ' . (is_wp_error($course_product_id) ? $course_product_id->get_error_message() : 'Unknown error'));
        }

        $webinar_product = [
            'post_title' => "Webinar - {$course_title}",
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ];
        $webinar_product_id = wp_insert_post($webinar_product, true);
        if ($webinar_product_id && !is_wp_error($webinar_product_id)) {
            wp_set_object_terms($webinar_product_id, 'simple', 'product_type');
            update_post_meta($webinar_product_id, '_visibility', 'visible');
            update_post_meta($webinar_product_id, '_stock_status', 'instock');
            update_post_meta($webinar_product_id, '_price', $json['price'] ?? 0);
            update_post_meta($webinar_product_id, '_regular_price', $json['price'] ?? 0);
            $webinar_product_link = home_url("/?add-to-cart={$webinar_product_id}&quantity=1");
            update_field('field_6821879e21941', $webinar_product_link, $course_page_id);
            error_log('Created webinar product ID: ' . $webinar_product_id);
        } else {
            error_log('Failed to create webinar product: ' . (is_wp_error($webinar_product_id) ? $webinar_product_id->get_error_message() : 'Unknown error'));
        }
    }

    $json['background_image'] = get_background_image_from_course_page($course_page_id, $stm_course_id);

    $acf_updates = [
        'field_681ccc5ab1238' => isset($json['title']) ? sanitize_text_field($json['title']) : '', // course_custom_title (Text)
        'field_682187522193c' => isset($json['background_image']) ? absint($json['background_image']) : 0, // course_background_image (Image)
        'field_681ccc66b1239' => isset($json['content']) ? wp_kses_post($json['content']) : '', // course_content (WYSIWYG)
        'field_681ccc6eb123a' => isset($json['price']) ? sanitize_text_field($json['price']) : '', // course_price (Text)
        'field_681ccc7eb123b' => isset($json['instructor']) ? sanitize_text_field($json['instructor']) : '', // course_instructor (Text)
        'field_681ccc91b123d' => isset($json['categories']) ? array_map('sanitize_text_field', $json['categories']) : [], // course_categories (Text)
        'field_681ccc96b123e' => isset($json['students']) ? absint($json['students']) : 0, // course_students (Number)
        'field_681ccc9db123f' => isset($json['views']) ? absint($json['views']) : 0, // course_views (Number)
        'field_681ccca5b1240' => isset($json['faqs']) && is_array($json['faqs']) ? $json['faqs'] : [], // course_faqs (Repeater)
        'field_682187682193d' => '', // instructor_position (Text, not in JSON)
        'field_6821877b2193e' => '', // instructor_bio (Text, not in JSON)
        'field_682187802193f' => '', // video_trailer (URL, not in JSON)
    ];

    error_log('Preparing to update course_instructor for course_page_id: ' . $course_page_id . ', Value: ' . (isset($json['instructor']) ? $json['instructor'] : 'Not set'));
    $test_result = update_field('field_681ccc7eb123b', 'Test Instructor', $course_page_id);
    error_log('Manual test update_field for field_681ccc7eb123b on course_page_id: ' . $course_page_id . ', Result: ' . ($test_result ? 'Success' : 'Failed'));


wp_cache_flush(); // Limpiar caché global antes de actualizar
error_log('Flushed WordPress cache before updating ACF fields for course_page_id: ' . $course_page_id);

foreach ($acf_updates as $field_key => $value) {
    $field_object = get_field_object($field_key, $course_page_id, false, false);
    if (!$field_object) {
        error_log('ACF field ' . $field_key . ' does not exist or is not registered for course_page_id: ' . $course_page_id . ' (post_type: course)');
        continue;
    }
    error_log('Attempting to update ACF field ' . $field_key . ' for course_page_id: ' . $course_page_id . ' with value: ' . (is_array($value) ? json_encode($value) : $value));
    wp_cache_delete("acf_{$field_key}_{$course_page_id}", 'acf_fields');
    $result = update_field($field_key, $value, $course_page_id);
    error_log('Updated ACF field ' . $field_key . ' for course_page_id: ' . $course_page_id . ', Result: ' . ($result ? 'Success' : 'Failed') . ', Value: ' . (is_array($value) ? json_encode($value) : $value));
}

    // Update post modified time if background image was updated
    if (isset($acf_updates['field_682187522193c']) && function_exists('wp_set_post_terms')) {
        wp_update_post([
            'ID' => $course_page_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1),
        ]);
    }

    error_log('Course page created/updated successfully: ' . $course_page_id);
    return $course_page_id;
}

/**
 * 4. Initial creation of pages for all existing stm-courses
 */
function create_initial_course_pages() {
    check_ajax_referer('create_initial_course_pages_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    error_log('Starting create_initial_course_pages');
    $batch_size = 5;
    $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

    $args = [
        'post_type' => 'stm-courses',
        'posts_per_page' => $batch_size,
        'post_status' => 'publish',
        'offset' => $offset,
    ];
    $stm_courses = get_posts($args);

    if (empty($stm_courses)) {
        error_log('No more stm-courses to process, batch complete');
        wp_send_json_success(['complete' => true, 'redirect' => admin_url('edit.php?post_type=course&message=pages_created')]);
    }

    $total_courses = wp_count_posts('stm-courses')->publish;
    $processed = $offset + count($stm_courses);

    foreach ($stm_courses as $stm_course) {
        error_log('Processing stm_course ID: ' . $stm_course->ID);
        $json = get_course_json_data($stm_course->ID);
        if ($json) {
            create_or_update_course_page($stm_course->ID, $json);
        } else {
            error_log('Failed to get JSON data for stm_course ID: ' . $stm_course->ID);
        }
    }

    wp_send_json_success([
        'complete' => false,
        'offset' => $offset + $batch_size,
        'progress' => min(100, round(($processed / $total_courses) * 100)),
    ]);
}
add_action('wp_ajax_create_initial_course_pages', 'create_initial_course_pages');

/**
 * 5. Manual update with ACF dropdown
 */
function update_course_page_on_save($post_id) {
    if (get_post_type($post_id) !== 'stm-courses' || wp_is_post_revision($post_id)) {
        error_log('Skipping update_course_page_on_save: Not an stm-courses post or is revision, post_id: ' . $post_id);
        return;
    }

    if (!function_exists('get_field')) {
        error_log('ACF get_field not available in update_course_page_on_save for post_id: ' . $post_id);
        return;
    }

    $update_action = function_exists('get_cached_acf_field') ? get_cached_acf_field('update_course_page', $post_id) : get_field('update_course_page', $post_id);
    error_log('update_course_page_on_save for post_id: ' . $post_id . ', update_action: ' . ($update_action ?: 'none'));
    if ($update_action !== 'update') {
        return;
    }

    $json = get_course_json_data($post_id);
    if ($json) {
        create_or_update_course_page($post_id, $json);
    } else {
        error_log('No JSON data returned for post_id: ' . $post_id);
    }

    update_field('update_course_page', 'no_update', $post_id);
}
add_action('acf/save_post', 'update_course_page_on_save', 20);

/**
 * 6. Add button in admin to trigger initial creation
 */
function add_create_course_pages_button() {
    $screen = get_current_screen();
    if ($screen->post_type !== 'stm-courses' || $screen->base !== 'edit') {
        return;
    }

    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.wrap h1').after('<a href="#" id="create-course-pages-btn" class="page-title-action">Create Course Pages</a><div id="course-creation-progress" style="margin-top:10px;display:none;">Processing: <span id="progress-percentage">0%</span></div>');

            $('#create-course-pages-btn').on('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to create Course pages for all courses?')) {
                    $('#create-course-pages-btn').prop('disabled', true);
                    $('#course-creation-progress').show();
                    processCourseBatch(0);
                }
            });

            function processCourseBatch(offset) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'create_initial_course_pages',
                        nonce: '<?php echo wp_create_nonce('create_initial_course_pages_nonce'); ?>',
                        offset: offset
                    },
                    success: function(response) {
                        if (response.success) {
                            if (response.data.complete) {
                                $('#progress-percentage').text('100%');
                                alert('Pages created. Redirecting...');
                                window.location = response.data.redirect;
                            } else {
                                $('#progress-percentage').text(response.data.progress + '%');
                                processCourseBatch(response.data.offset);
                            }
                        } else {
                            $('#course-creation-progress').hide();
                            $('#create-course-pages-btn').prop('disabled', false);
                            alert('Error: ' + (response.data.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#course-creation-progress').hide();
                        $('#create-course-pages-btn').prop('disabled', false);
                        alert('Error creating pages: ' + error);
                    }
                });
            }
        });
    </script>
    <?php
}
add_action('admin_footer', 'add_create_course_pages_button');

/**
 * 7. Verify nonce for AJAX action
 */
function verify_create_course_pages_nonce() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'create_initial_course_pages_nonce')) {
        wp_send_json_error(['message' => 'Security error']);
    }
}
add_action('wp_ajax_create_initial_course_pages', 'verify_create_course_pages_nonce', 1);
?>
