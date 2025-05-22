/**
 * Redirect course product pages to their corresponding course pages
 */
add_action('template_redirect', function() {
    // Check if we are on a single product page
    if (!is_singular('product')) {
        return;
    }

    $product_id = get_the_ID();
    error_log('Checking redirection for product ID: ' . $product_id);

    // Check if the product is associated with an stm-courses post
    $stm_course_id = get_post_meta($product_id, 'related_stm_course_id', true);
    if (!$stm_course_id) {
        error_log('No related stm_course_id found for product ID: ' . $product_id);
        return;
    }

    // Check if the product is a course product (not a webinar product)
    $is_course_product = get_post_meta($stm_course_id, 'related_course_product_id', true) == $product_id;
    if (!$is_course_product) {
        error_log('Product ID ' . $product_id . ' is not a course product (possibly a webinar product)');
        return;
    }

    // Get the related course page ID
    $course_page_id = get_post_meta($stm_course_id, 'related_course_id', true);
    if (!$course_page_id) {
        error_log('No related course page ID found for stm_course_id: ' . $stm_course_id);
        return;
    }

    // Get the course page post
    $course_page = get_post($course_page_id);
    if (!$course_page || $course_page->post_type !== 'course' || $course_page->post_status !== 'publish') {
        error_log('Invalid or unpublished course page for course_page_id: ' . $course_page_id);
        return;
    }

    // Get the course page permalink
    $course_permalink = get_permalink($course_page_id);
    if (!$course_permalink) {
        error_log('Failed to get permalink for course_page_id: ' . $course_page_id);
        return;
    }

    error_log('Redirecting from product ID ' . $product_id . ' to course page: ' . $course_permalink);
    wp_safe_redirect($course_permalink, 301);
    exit;
});
