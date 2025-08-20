<?php
/**
 * Plugin Name: Course Product Manager
 * Description: Manage relationships between STM Courses and WooCommerce Products
 * Version: 1.0.0
 * Author: Lucuma Agency
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CPM_VERSION', '1.0.0');
define('CPM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CPM_PLUGIN_URL', plugin_dir_url(__FILE__));

class CourseProductManager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_cpm_save_relationship', [$this, 'ajax_save_relationship']);
        add_action('wp_ajax_cpm_delete_relationship', [$this, 'ajax_delete_relationship']);
        add_action('wp_ajax_cpm_get_products', [$this, 'ajax_get_products']);
        add_action('wp_ajax_cpm_get_relationship_details', [$this, 'ajax_get_relationship_details']);
    }
    
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><strong>Course Product Manager:</strong> WooCommerce is required but not active.</p>
                </div>
                <?php
            });
            return;
        }
        
        if (!post_type_exists('stm-courses')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><strong>Course Product Manager:</strong> MasterStudy LMS is required but not active.</p>
                </div>
                <?php
            });
            return;
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Course Product Manager',
            'Course Products',
            'manage_options',
            'course-product-manager',
            [$this, 'render_admin_page'],
            'dashicons-cart',
            30
        );
        
        add_submenu_page(
            'course-product-manager',
            'All Relationships',
            'All Relationships',
            'manage_options',
            'course-product-manager',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'course-product-manager',
            'Add New Relationship',
            'Add New',
            'manage_options',
            'cpm-add-new',
            [$this, 'render_add_new_page']
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'course-product-manager') === false && strpos($hook, 'cpm-add-new') === false) {
            return;
        }
        
        wp_enqueue_style('cpm-admin', CPM_PLUGIN_URL . 'assets/admin.css', [], CPM_VERSION);
        wp_enqueue_script('cpm-admin', CPM_PLUGIN_URL . 'assets/admin.js', ['jquery'], CPM_VERSION, true);
        wp_localize_script('cpm-admin', 'cpm_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cpm_nonce')
        ]);
    }
    
    public function render_admin_page() {
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        ?>
        <div class="wrap cpm-wrap">
            <h1>Course Product Relationships</h1>
            
            <div class="cpm-header">
                <form method="get" class="cpm-search-form">
                    <input type="hidden" name="page" value="course-product-manager">
                    <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search courses...">
                    <input type="submit" class="button" value="Search">
                </form>
                <a href="<?php echo admin_url('admin.php?page=cpm-add-new'); ?>" class="button button-primary">Add New Relationship</a>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Course ID</th>
                        <th>Course Title</th>
                        <th>Course Product</th>
                        <th>Webinar Product</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $args = [
                        'post_type' => 'stm-courses',
                        'posts_per_page' => $per_page,
                        'paged' => $paged,
                        'post_status' => 'publish',
                        's' => $search
                    ];
                    
                    $query = new WP_Query($args);
                    
                    if ($query->have_posts()) {
                        while ($query->have_posts()) {
                            $query->the_post();
                            $course_id = get_the_ID();
                            $course_product_id = get_post_meta($course_id, 'related_course_product_id', true);
                            $webinar_product_id = get_post_meta($course_id, 'related_webinar_product_id', true);
                            
                            ?>
                            <tr data-course-id="<?php echo $course_id; ?>">
                                <td><?php echo $course_id; ?></td>
                                <td>
                                    <strong><?php echo get_the_title(); ?></strong>
                                    <br>
                                    <a href="<?php echo get_edit_post_link($course_id); ?>" target="_blank">Edit Course</a>
                                </td>
                                <td class="course-product-cell">
                                    <?php if ($course_product_id && get_post($course_product_id)) : ?>
                                        <span class="product-info">
                                            <?php echo get_the_title($course_product_id); ?> 
                                            (ID: <?php echo $course_product_id; ?>)
                                        </span>
                                        <br>
                                        <a href="<?php echo get_edit_post_link($course_product_id); ?>" target="_blank">Edit</a> |
                                        <a href="#" class="cpm-remove-product" data-type="course" data-course-id="<?php echo $course_id; ?>">Remove</a>
                                    <?php else : ?>
                                        <span class="no-product">No product linked</span>
                                        <br>
                                        <a href="#" class="cpm-add-product" data-type="course" data-course-id="<?php echo $course_id; ?>">Add Product</a>
                                    <?php endif; ?>
                                </td>
                                <td class="webinar-product-cell">
                                    <?php if ($webinar_product_id && get_post($webinar_product_id)) : ?>
                                        <span class="product-info">
                                            <?php echo get_the_title($webinar_product_id); ?> 
                                            (ID: <?php echo $webinar_product_id; ?>)
                                        </span>
                                        <br>
                                        <a href="<?php echo get_edit_post_link($webinar_product_id); ?>" target="_blank">Edit</a> |
                                        <a href="#" class="cpm-remove-product" data-type="webinar" data-course-id="<?php echo $course_id; ?>">Remove</a>
                                    <?php else : ?>
                                        <span class="no-product">No product linked</span>
                                        <br>
                                        <a href="#" class="cpm-add-product" data-type="webinar" data-course-id="<?php echo $course_id; ?>">Add Product</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="button cpm-quick-edit" data-course-id="<?php echo $course_id; ?>">Quick Edit</button>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="5">No courses found.</td>
                        </tr>
                        <?php
                    }
                    wp_reset_postdata();
                    ?>
                </tbody>
            </table>
            
            <?php
            if ($query->max_num_pages > 1) {
                echo '<div class="tablenav bottom"><div class="tablenav-pages">';
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $query->max_num_pages,
                    'current' => $paged
                ]);
                echo '</div></div>';
            }
            ?>
        </div>
        
        <div id="cpm-modal" class="cpm-modal" style="display:none;">
            <div class="cpm-modal-content">
                <span class="cpm-close">&times;</span>
                <h2 id="cpm-modal-title">Select Product</h2>
                <div id="cpm-modal-body">
                    <p>Loading...</p>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_add_new_page() {
        ?>
        <div class="wrap cpm-wrap">
            <h1>Add New Course-Product Relationship</h1>
            
            <form id="cpm-new-relationship-form" class="cpm-form">
                <table class="form-table">
                    <tr>
                        <th><label for="course-select">Select Course</label></th>
                        <td>
                            <select id="course-select" name="course_id" required>
                                <option value="">-- Select a Course --</option>
                                <?php
                                $courses = get_posts([
                                    'post_type' => 'stm-courses',
                                    'posts_per_page' => -1,
                                    'post_status' => 'publish',
                                    'orderby' => 'title',
                                    'order' => 'ASC'
                                ]);
                                
                                foreach ($courses as $course) {
                                    $course_product_id = get_post_meta($course->ID, 'related_course_product_id', true);
                                    $webinar_product_id = get_post_meta($course->ID, 'related_webinar_product_id', true);
                                    $has_products = ($course_product_id || $webinar_product_id) ? ' (has products)' : '';
                                    echo '<option value="' . $course->ID . '">' . $course->post_title . ' (ID: ' . $course->ID . ')' . $has_products . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="product-type">Product Type</label></th>
                        <td>
                            <select id="product-type" name="product_type" required>
                                <option value="">-- Select Type --</option>
                                <option value="course">Course Product (Buy Course)</option>
                                <option value="webinar">Webinar Product</option>
                                <option value="both">Both Products</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr id="course-product-row" style="display:none;">
                        <th><label for="course-product">Course Product</label></th>
                        <td>
                            <select id="course-product" name="course_product_id">
                                <option value="">-- Select or Create New --</option>
                                <option value="new">Create New Product</option>
                                <?php
                                $products = get_posts([
                                    'post_type' => 'product',
                                    'posts_per_page' => -1,
                                    'post_status' => 'publish',
                                    'orderby' => 'title',
                                    'order' => 'ASC'
                                ]);
                                
                                foreach ($products as $product) {
                                    echo '<option value="' . $product->ID . '">' . $product->post_title . ' (ID: ' . $product->ID . ')</option>';
                                }
                                ?>
                            </select>
                            <div id="new-course-product-fields" style="display:none; margin-top:10px;">
                                <input type="text" id="new-course-product-title" placeholder="Product Title" style="width:100%;">
                                <input type="number" id="new-course-product-price" placeholder="Price" step="0.01" min="0" style="width:100%; margin-top:5px;">
                            </div>
                        </td>
                    </tr>
                    
                    <tr id="webinar-product-row" style="display:none;">
                        <th><label for="webinar-product">Webinar Product</label></th>
                        <td>
                            <select id="webinar-product" name="webinar_product_id">
                                <option value="">-- Select or Create New --</option>
                                <option value="new">Create New Product</option>
                                <?php
                                foreach ($products as $product) {
                                    echo '<option value="' . $product->ID . '">' . $product->post_title . ' (ID: ' . $product->ID . ')</option>';
                                }
                                ?>
                            </select>
                            <div id="new-webinar-product-fields" style="display:none; margin-top:10px;">
                                <input type="text" id="new-webinar-product-title" placeholder="Product Title" style="width:100%;">
                                <input type="number" id="new-webinar-product-price" placeholder="Price" step="0.01" min="0" style="width:100%; margin-top:5px;">
                            </div>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Create Relationship</button>
                    <a href="<?php echo admin_url('admin.php?page=course-product-manager'); ?>" class="button">Cancel</a>
                </p>
            </form>
            
            <div id="cpm-message" class="notice" style="display:none;"></div>
        </div>
        <?php
    }
    
    public function ajax_save_relationship() {
        check_ajax_referer('cpm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $course_id = intval($_POST['course_id']);
        $product_type = sanitize_text_field($_POST['product_type']);
        $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : '';
        
        if (!$course_id || !$product_type) {
            wp_send_json_error('Missing required fields');
        }
        
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'stm-courses') {
            wp_send_json_error('Invalid course');
        }
        
        $results = [];
        
        if ($product_type === 'course' || $product_type === 'both') {
            $course_product_id = $this->handle_product_relationship(
                $course_id,
                isset($_POST['course_product_id']) ? $_POST['course_product_id'] : $product_id,
                'course',
                isset($_POST['new_course_product_title']) ? $_POST['new_course_product_title'] : '',
                isset($_POST['new_course_product_price']) ? $_POST['new_course_product_price'] : 0
            );
            
            if ($course_product_id) {
                $results['course_product'] = $course_product_id;
            }
        }
        
        if ($product_type === 'webinar' || $product_type === 'both') {
            $webinar_product_id = $this->handle_product_relationship(
                $course_id,
                isset($_POST['webinar_product_id']) ? $_POST['webinar_product_id'] : $product_id,
                'webinar',
                isset($_POST['new_webinar_product_title']) ? $_POST['new_webinar_product_title'] : '',
                isset($_POST['new_webinar_product_price']) ? $_POST['new_webinar_product_price'] : 0
            );
            
            if ($webinar_product_id) {
                $results['webinar_product'] = $webinar_product_id;
            }
        }
        
        if (!empty($results)) {
            wp_send_json_success([
                'message' => 'Relationship(s) created successfully',
                'results' => $results
            ]);
        } else {
            wp_send_json_error('Failed to create relationship');
        }
    }
    
    private function handle_product_relationship($course_id, $product_id, $type, $new_title = '', $new_price = 0) {
        $meta_key = $type === 'webinar' ? 'related_webinar_product_id' : 'related_course_product_id';
        
        if ($product_id === 'new') {
            $course = get_post($course_id);
            $product_title = !empty($new_title) ? $new_title : (
                $type === 'webinar' ? 'Webinar - ' . $course->post_title : $course->post_title
            );
            
            $product_data = [
                'post_title' => $product_title,
                'post_type' => 'product',
                'post_status' => 'publish',
                'post_author' => get_current_user_id()
            ];
            
            $product_id = wp_insert_post($product_data);
            
            if ($product_id && !is_wp_error($product_id)) {
                wp_set_object_terms($product_id, 'simple', 'product_type');
                update_post_meta($product_id, '_visibility', 'visible');
                update_post_meta($product_id, '_stock_status', 'instock');
                update_post_meta($product_id, '_price', $new_price);
                update_post_meta($product_id, '_regular_price', $new_price);
            }
        } else {
            $product_id = intval($product_id);
            $product = get_post($product_id);
            if (!$product || $product->post_type !== 'product') {
                return false;
            }
        }
        
        if ($product_id && !is_wp_error($product_id)) {
            update_post_meta($course_id, $meta_key, $product_id);
            update_post_meta($product_id, 'related_stm_course_id', $course_id);
            
            $this->update_course_page_links($course_id);
            
            return $product_id;
        }
        
        return false;
    }
    
    private function update_course_page_links($course_id) {
        $course_page_id = get_post_meta($course_id, 'related_course_id', true);
        
        if ($course_page_id && function_exists('update_field')) {
            $course_product_id = get_post_meta($course_id, 'related_course_product_id', true);
            $webinar_product_id = get_post_meta($course_id, 'related_webinar_product_id', true);
            
            if ($course_product_id) {
                $course_product_link = home_url("/?add-to-cart={$course_product_id}&quantity=1");
                update_field('field_6821879221940', $course_product_link, $course_page_id);
            }
            
            if ($webinar_product_id) {
                $webinar_product_link = home_url("/?add-to-cart={$webinar_product_id}&quantity=1");
                update_field('field_6821879e21941', $webinar_product_link, $course_page_id);
            }
        }
    }
    
    public function ajax_delete_relationship() {
        check_ajax_referer('cpm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $course_id = intval($_POST['course_id']);
        $product_type = sanitize_text_field($_POST['product_type']);
        
        if (!$course_id || !$product_type) {
            wp_send_json_error('Missing required fields');
        }
        
        $meta_key = $product_type === 'webinar' ? 'related_webinar_product_id' : 'related_course_product_id';
        $product_id = get_post_meta($course_id, $meta_key, true);
        
        if ($product_id) {
            delete_post_meta($course_id, $meta_key);
            delete_post_meta($product_id, 'related_stm_course_id');
            
            if ($product_type === 'course' && function_exists('update_field')) {
                $course_page_id = get_post_meta($course_id, 'related_course_id', true);
                if ($course_page_id) {
                    update_field('field_6821879221940', '', $course_page_id);
                }
            } elseif ($product_type === 'webinar' && function_exists('update_field')) {
                $course_page_id = get_post_meta($course_id, 'related_course_id', true);
                if ($course_page_id) {
                    update_field('field_6821879e21941', '', $course_page_id);
                }
            }
            
            wp_send_json_success('Relationship removed successfully');
        } else {
            wp_send_json_error('No relationship found');
        }
    }
    
    public function ajax_get_products() {
        check_ajax_referer('cpm_nonce', 'nonce');
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $args = [
            'post_type' => 'product',
            'posts_per_page' => 50,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ];
        
        if ($search) {
            $args['s'] = $search;
        }
        
        $products = get_posts($args);
        $product_list = [];
        
        foreach ($products as $product) {
            $related_course = get_post_meta($product->ID, 'related_stm_course_id', true);
            $product_list[] = [
                'id' => $product->ID,
                'title' => $product->post_title,
                'price' => get_post_meta($product->ID, '_price', true),
                'related_course' => $related_course
            ];
        }
        
        wp_send_json_success($product_list);
    }
    
    public function ajax_get_relationship_details() {
        check_ajax_referer('cpm_nonce', 'nonce');
        
        $course_id = intval($_POST['course_id']);
        
        if (!$course_id) {
            wp_send_json_error('Invalid course ID');
        }
        
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'stm-courses') {
            wp_send_json_error('Invalid course');
        }
        
        $course_product_id = get_post_meta($course_id, 'related_course_product_id', true);
        $webinar_product_id = get_post_meta($course_id, 'related_webinar_product_id', true);
        $course_page_id = get_post_meta($course_id, 'related_course_id', true);
        
        $details = [
            'course' => [
                'id' => $course_id,
                'title' => $course->post_title,
                'edit_link' => get_edit_post_link($course_id)
            ],
            'course_product' => null,
            'webinar_product' => null,
            'course_page' => null
        ];
        
        if ($course_product_id) {
            $product = get_post($course_product_id);
            if ($product) {
                $details['course_product'] = [
                    'id' => $course_product_id,
                    'title' => $product->post_title,
                    'price' => get_post_meta($course_product_id, '_price', true),
                    'edit_link' => get_edit_post_link($course_product_id)
                ];
            }
        }
        
        if ($webinar_product_id) {
            $product = get_post($webinar_product_id);
            if ($product) {
                $details['webinar_product'] = [
                    'id' => $webinar_product_id,
                    'title' => $product->post_title,
                    'price' => get_post_meta($webinar_product_id, '_price', true),
                    'edit_link' => get_edit_post_link($webinar_product_id)
                ];
            }
        }
        
        if ($course_page_id) {
            $page = get_post($course_page_id);
            if ($page) {
                $details['course_page'] = [
                    'id' => $course_page_id,
                    'title' => $page->post_title,
                    'edit_link' => get_edit_post_link($course_page_id)
                ];
            }
        }
        
        wp_send_json_success($details);
    }
}

CourseProductManager::get_instance();