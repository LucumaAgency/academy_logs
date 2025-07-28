

/**
 * Shortcode para mostrar la cantidad de productos vendidos por fecha seleccionada
 * @param array $atts Atributos del shortcode
 * @return string HTML con la cantidad de productos vendidos
 */
function seats_remaining_shortcode($atts) {
    global $post;
    $post_id = $post ? $post->ID : 0;
    if (!$post_id || get_post_type($post_id) !== 'course') {
        error_log('Seats Remaining Shortcode: Invalid post ID or not a course post, ID: ' . $post_id);
        return '<p>Error: This shortcode must be used on a course page.</p>';
    }

    // Get available start dates and stocks from ACF repeater field
    $available_dates = [];
    $date_stocks = [];
    if (have_rows('field_6826dd2179231', $post_id)) {
        while (have_rows('field_6826dd2179231', $post_id)) {
            the_row();
            $date_text = get_sub_field('field_6826dfe2d7837');
            $stock = get_sub_field('field_684ba360c13e2'); // webinar_stock
            if (!empty($date_text)) {
                $sanitized_date = sanitize_text_field($date_text);
                $available_dates[] = $sanitized_date;
                $date_stocks[$sanitized_date] = is_numeric($stock) ? intval($stock) : 10;
                error_log('Seats Remaining Shortcode: Available date added: ' . $sanitized_date . ', Stock: ' . $date_stocks[$sanitized_date]);
            }
        }
    }

    if (empty($available_dates)) {
        error_log('Seats Remaining Shortcode: No start dates available for post ID ' . $post_id);
        return '<p>No start dates available for this course.</p>';
    }

    // Default date: the first available date
    $default_date = $available_dates[0];
    error_log('Seats Remaining Shortcode: Default date set to ' . $default_date);

    // Get enroll product ID
    $enroll_product_link = get_field('field_6821879e21941', $post_id);
    $enroll_product_id = 0;
    if (!empty($enroll_product_link)) {
        $url_parts = parse_url($enroll_product_link, PHP_URL_QUERY);
        parse_str($url_parts, $query_params);
        $enroll_product_id = isset($query_params['add-to-cart']) ? intval($query_params['add-to-cart']) : 0;
    }

    if (!$enroll_product_id) {
        error_log('Seats Remaining Shortcode: No valid enroll product ID for post ID ' . $post_id);
        return '<p>Error: Enroll product not found.</p>';
    }
    error_log('Seats Remaining Shortcode: Enroll product ID ' . $enroll_product_id);

    // Get initial stock for default date
    $initial_seats = isset($date_stocks[$default_date]) ? $date_stocks[$default_date] : 10;
    error_log('Seats Remaining Shortcode: Initial stock for ' . $default_date . ': ' . $initial_seats);

    // Function to count sales and calculate remaining seats
    $calculate_seats_remaining = function($product_id, $selected_date) use ($available_dates, $date_stocks) {
        // Validate selected date is in available dates
        if (!in_array($selected_date, $available_dates)) {
            error_log('Seats Remaining: Invalid selected date ' . $selected_date . ' not in available dates: ' . implode(', ', $available_dates));
            return 10; // Fallback if date is invalid
        }

        // Get initial stock for selected date
        $initial_seats = isset($date_stocks[$selected_date]) ? $date_stocks[$selected_date] : 10;
        error_log('Seats Remaining Shortcode: Calculating seats for date ' . $selected_date . ', Initial stock: ' . $initial_seats);

        $args = [
            'status' => ['wc-completed'], // Only count completed orders
            'limit' => -1,
            'date_query' => [
                'after' => '2020-01-01', // Broad range to include all relevant orders
            ],
        ];

        $orders = wc_get_orders($args);
        $sales_count = 0;
        error_log('Seats Remaining Shortcode: Total completed orders found: ' . count($orders));

        foreach ($orders as $order) {
            $order_status = $order->get_status();
            foreach ($order->get_items() as $item) {
                $item_product_id = $item->get_product_id();
                $start_date = $item->get_meta('Start Date');
                $quantity = $item->get_quantity();
                error_log('Seats Remaining Shortcode: Order ID ' . $order->get_id() . ', Status: ' . $order_status . ', Item Product ID ' . $item_product_id . ', Start Date: ' . ($start_date ?: 'None') . ', Quantity: ' . $quantity);

                // Exact string match for product ID and start date
                if ($item_product_id == $product_id && $start_date === $selected_date) {
                    $sales_count += $quantity;
                    error_log('Seats Remaining Shortcode: Match found: Added ' . $quantity . ' to sales count for date ' . $selected_date . ', Order ID ' . $order->get_id());
                }
            }
        }

        $seats_remaining = $initial_seats - $sales_count;
        if ($seats_remaining < 0) {
            error_log('Seats Remaining Shortcode: Negative seats detected, setting to 0. Initial: ' . $initial_seats . ', Sold: ' . $sales_count);
            $seats_remaining = 0;
        }
        error_log('Seats Remaining Shortcode: Product ID ' . $product_id . ', Date ' . $selected_date . ', Sold: ' . $sales_count . ', Initial: ' . $initial_seats . ', Remaining: ' . $seats_remaining);

        return $seats_remaining;
    };

    // Calculate seats for default date
    $seats_remaining = $calculate_seats_remaining($enroll_product_id, $default_date);
    error_log('Seats Remaining Shortcode: Final seats remaining for default date: ' . $seats_remaining);
    $hide_seats_text = $seats_remaining <= 0;

    // Generate HTML for the shortcode
    ob_start();
    ?>
    <div class="seats-remaining-container" <?php if ($hide_seats_text) echo 'style="display: none;"'; ?>>
        <p class="seats-label"><span id="seats-remaining"><?php echo esc_html(max(0, $seats_remaining)); ?></span> Remaining seats</p>
    </div>

    <style>
        .seats-remaining-container {
            max-width: 1200px;
            margin: 20px auto;
            text-align: center;
        }
        .seats-remaining-container .seats-label {
            font-size: 1.1em;
            color: #fff;
            font-weight: 400;
        }
    </style>

    <script>
        // Debounce function to prevent multiple rapid AJAX calls
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Log initial state
            console.log('Seats Remaining Shortcode: Initial seats remaining displayed: <?php echo esc_js($seats_remaining); ?>');
            console.log('Seats Remaining Shortcode: Default date: <?php echo esc_js($default_date); ?>');
            console.log('Seats Remaining Shortcode: Enroll product ID: <?php echo esc_js($enroll_product_id); ?>');

            // Track last selected date to prevent redundant AJAX calls
            let lastSelectedDate = '<?php echo esc_js($default_date); ?>';

            // Update seats remaining when a date is selected
            const updateSeats = debounce(function(selectedDate) {
                if (selectedDate === lastSelectedDate) {
                    console.log('Seats Remaining Shortcode: Skipping AJAX call, same date selected: ' + selectedDate);
                    return;
                }
                console.log('Seats Remaining Shortcode: Updating seats for date: ' + selectedDate);
                lastSelectedDate = selectedDate;

                // Make AJAX request to get seats remaining
                jQuery.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    action: 'get_seats_remaining',
                    product_id: '<?php echo esc_js($enroll_product_id); ?>',
                    selected_date: selectedDate,
                    post_id: '<?php echo esc_js($post_id); ?>',
                    nonce: '<?php echo wp_create_nonce('get_seats_remaining_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        console.log('Seats Remaining Shortcode: AJAX success, seats remaining: ' + response.data.seats_remaining);
                        document.getElementById('seats-remaining').textContent = response.data.seats_remaining;
                        // Toggle seats text visibility based on seats
                        const seatsContainer = document.querySelector('.seats-remaining-container');
                        if (seatsContainer) {
                            console.log('Seats Remaining Shortcode: Setting seats container display to: ' + (response.data.seats_remaining <= 0 ? 'none' : ''));
                            seatsContainer.style.display = response.data.seats_remaining <= 0 ? 'none' : '';
                        }
                    } else {
                        console.error('Seats Remaining Shortcode: AJAX error: ' + response.data.message);
                        document.getElementById('seats-remaining').textContent = 'Error';
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('Seats Remaining Shortcode: AJAX request failed: ' + textStatus + ', ' + errorThrown);
                    document.getElementById('seats-remaining').textContent = 'Error';
                });
            }, 300);

            // Attach click event to date buttons
            document.querySelectorAll('.date-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const selectedDate = this.getAttribute('data-date') || this.textContent.trim();
                    updateSeats(selectedDate);
                });
            });

            // Skip forced AJAX call if default date is already selected
            const defaultDateBtn = document.querySelector('.date-btn.selected');
            if (defaultDateBtn && (defaultDateBtn.getAttribute('data-date') || defaultDateBtn.textContent.trim()) === '<?php echo esc_js($default_date); ?>') {
                console.log('Seats Remaining Shortcode: Skipping forced AJAX call, default date already selected');
            } else {
                console.log('Seats Remaining Shortcode: Attempting forced AJAX call for default date');
                updateSeats('<?php echo esc_js($default_date); ?>');
            }
        });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * AJAX action to get the number of seats remaining for a specific date
 */
add_action('wp_ajax_get_seats_remaining', function() {
    check_ajax_referer('get_seats_remaining_nonce', 'nonce');

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $selected_date = isset($_POST['selected_date']) ? sanitize_text_field($_POST['selected_date']) : '';
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!$product_id || !$selected_date || !$post_id) {
        error_log('AJAX get_seats_remaining: Invalid parameters, Product ID: ' . $product_id . ', Date: ' . $selected_date . ', Post ID: ' . $post_id);
        wp_send_json_error(['message' => 'Invalid parameters']);
    }

    // Get available start dates and stocks from ACF repeater field
    $available_dates = [];
    $date_stocks = [];
    if (have_rows('field_6826dd2179231', $post_id)) {
        while (have_rows('field_6826dd2179231', $post_id)) {
            the_row();
            $date_text = get_sub_field('field_6826dfe2d7837');
            $stock = get_sub_field('field_684ba360c13e2');
            if (!empty($date_text)) {
                $sanitized_date = sanitize_text_field($date_text);
                $available_dates[] = $sanitized_date;
                $date_stocks[$sanitized_date] = is_numeric($stock) ? intval($stock) : 10;
                error_log('AJAX get_seats_remaining: Available date: ' . $sanitized_date . ', Stock: ' . $date_stocks[$sanitized_date]);
            }
        }
    }

    if (!in_array($selected_date, $available_dates)) {
        error_log('AJAX get_seats_remaining: Invalid selected date ' . $selected_date);
        wp_send_json_error(['message' => 'Invalid date']);
    }

    // Get initial stock for selected date
    $initial_seats = isset($date_stocks[$selected_date]) ? $date_stocks[$selected_date] : 10;
    error_log('AJAX get_seats_remaining: Initial stock for ' . $selected_date . ': ' . $initial_seats);

    $args = [
        'status' => ['wc-completed'], // Only count completed orders
        'limit' => -1,
        'date_query' => ['after' => '2020-01-01'],
    ];

    $orders = wc_get_orders($args);
    $sales_count = 0;
    error_log('AJAX get_seats_remaining: Total completed orders found: ' . count($orders));

    foreach ($orders as $order) {
        $order_status = $order->get_status();
        foreach ($order->get_items() as $item) {
            $item_product_id = $item->get_product_id();
            $start_date = $item->get_meta('Start Date');
            $quantity = $item->get_quantity();
            error_log('AJAX get_seats_remaining: Order ID ' . $order->get_id() . ', Status: ' . $order_status . ', Item Product ID ' . $item_product_id . ', Start Date: ' . ($start_date ?: 'None') . ', Quantity: ' . $quantity);

            // Exact string match for product ID and start date
            if ($item_product_id == $product_id && $start_date === $selected_date) {
                $sales_count += $quantity;
                error_log('AJAX get_seats_remaining: Match found: Added ' . $quantity . ' to sales count for date ' . $selected_date . ', Order ID ' . $order->get_id());
            }
        }
    }

    $seats_remaining = $initial_seats - $sales_count;
    if ($seats_remaining < 0) {
        error_log('AJAX get_seats_remaining: Negative seats detected, setting to 0. Initial: ' . $initial_seats . ', Sold: ' . $sales_count);
        $seats_remaining = 0;
    }
    error_log('AJAX get_seats_remaining: Product ID ' . $product_id . ', Date ' . $selected_date . ', Sold: ' . $sales_count . ', Initial: ' . $initial_seats . ', Remaining: ' . $seats_remaining);

    wp_send_json_success(['seats_remaining' => max(0, $seats_remaining)]);
});

add_shortcode('seats_remaining', 'seats_remaining_shortcode');

