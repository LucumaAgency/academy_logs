<?php
/**
 * Plugin Name: Form Debug Logger
 * Plugin URI: https://academy.arcanalabs.ai
 * Description: Registra todos los envíos de formularios de Elementor y Royal Elementor Addons en debug.log y la consola del navegador para testing.
 * Version: 1.0.0
 * Author: Arcana Labs
 * Author URI: https://arcanalabs.ai
 * License: GPL v2 or later
 * Text Domain: form-debug-logger
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log Elementor form submissions to debug.log and browser console
 */
add_action('elementor_pro/forms/new_record', 'log_elementor_form_submission', 10, 2);
function log_elementor_form_submission($record, $handler) {
    // Get form data
    $form_name = $record->get_form_settings('form_name');
    $form_id = $record->get_form_settings('id');
    $raw_fields = $record->get('fields');
    
    // Prepare fields data for logging
    $fields_data = [];
    foreach ($raw_fields as $id => $field) {
        $fields_data[$id] = [
            'type' => $field['type'],
            'value' => $field['value']
        ];
    }
    
    // Create log message
    $log_message = [
        'timestamp' => current_time('mysql'),
        'form_name' => $form_name,
        'form_id' => $form_id,
        'fields' => $fields_data,
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ];
    
    // Log to debug.log
    error_log('=== ELEMENTOR FORM SUBMISSION ===');
    error_log('Form Name: ' . ($form_name ?: 'No name'));
    error_log('Form ID: ' . $form_id);
    error_log('Fields: ' . json_encode($fields_data, JSON_PRETTY_PRINT));
    error_log('IP: ' . $log_message['user_ip']);
    error_log('User Agent: ' . $log_message['user_agent']);
    error_log('================================');
    
    // Add JavaScript to log to browser console
    add_action('wp_footer', function() use ($log_message) {
        ?>
        <script>
        console.log('%c=== ELEMENTOR FORM SUBMISSION ===', 'color: #4CAF50; font-weight: bold;');
        console.log('Form submission data:', <?php echo json_encode($log_message); ?>);
        console.table(<?php echo json_encode($log_message['fields']); ?>);
        console.log('%c================================', 'color: #4CAF50; font-weight: bold;');
        </script>
        <?php
    });
}

/**
 * Log form submissions for Royal Elementor Addons forms
 */
add_action('wpr_form_builder_mail_sent', 'log_royal_elementor_form_submission', 10, 3);
function log_royal_elementor_form_submission($form_id, $form_data, $form_settings) {
    // Prepare form data for logging
    $log_message = [
        'timestamp' => current_time('mysql'),
        'form_type' => 'Royal Elementor Addon Form',
        'form_id' => $form_id,
        'form_data' => $form_data,
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ];
    
    // Log to debug.log
    error_log('=== ROYAL ELEMENTOR FORM SUBMISSION ===');
    error_log('Form ID: ' . $form_id);
    error_log('Form Data: ' . json_encode($form_data, JSON_PRETTY_PRINT));
    error_log('IP: ' . $log_message['user_ip']);
    error_log('User Agent: ' . $log_message['user_agent']);
    error_log('========================================');
    
    // Add JavaScript to log to browser console
    add_action('wp_footer', function() use ($log_message) {
        ?>
        <script>
        console.log('%c=== ROYAL ELEMENTOR FORM SUBMISSION ===', 'color: #9C27B0; font-weight: bold;');
        console.log('Form submission data:', <?php echo json_encode($log_message); ?>);
        console.table(<?php echo json_encode($log_message['form_data']); ?>);
        console.log('%c========================================', 'color: #9C27B0; font-weight: bold;');
        </script>
        <?php
    });
}

/**
 * Add AJAX handler to log forms submitted via AJAX
 */
add_action('wp_ajax_log_form_submission', 'handle_ajax_form_logging');
add_action('wp_ajax_nopriv_log_form_submission', 'handle_ajax_form_logging');
function handle_ajax_form_logging() {
    // Get form data from AJAX request
    $form_data = $_POST['form_data'] ?? [];
    $form_type = $_POST['form_type'] ?? 'Unknown';
    
    // Log to debug.log
    error_log('=== AJAX FORM SUBMISSION ===');
    error_log('Form Type: ' . $form_type);
    error_log('Form Data: ' . json_encode($form_data, JSON_PRETTY_PRINT));
    error_log('============================');
    
    wp_send_json_success(['message' => 'Form logged successfully']);
}

/**
 * Add global JavaScript to capture all form submissions
 */
add_action('wp_footer', function() {
    ?>
    <script>
    (function() {
        // Log form submissions for debugging
        document.addEventListener('DOMContentLoaded', function() {
            console.log('%cForm submission logging active', 'color: #FF9800; font-weight: bold;');
            
            // Intercept Elementor form submissions
            jQuery(document).on('submit_success', function(event, response) {
                console.log('%c✓ Elementor Form Submitted', 'color: #4CAF50; font-weight: bold;');
                console.log('Response:', response);
                
                // Send to server for logging
                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'log_form_submission',
                    form_type: 'Elementor',
                    form_data: response
                });
            });
            
            // Intercept Royal Elementor Addon form submissions
            jQuery(document).on('wpr_form_submit_success', function(event, data) {
                console.log('%c✓ Royal Elementor Form Submitted', 'color: #9C27B0; font-weight: bold;');
                console.log('Form Data:', data);
                
                // Send to server for logging
                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'log_form_submission',
                    form_type: 'Royal Elementor',
                    form_data: data
                });
            });
            
            // Generic form submission interceptor
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    // Check if it's an Elementor or Royal form
                    if (form.classList.contains('elementor-form') || 
                        form.classList.contains('wpr-form') ||
                        form.closest('.elementor-widget-form') ||
                        form.closest('.wpr-form-container')) {
                        
                        console.log('%cForm Submit Detected', 'color: #2196F3; font-weight: bold;');
                        console.log('Form:', form);
                        console.log('Form ID:', form.id || 'No ID');
                        console.log('Form Classes:', form.className);
                        
                        // Get form data
                        const formData = new FormData(form);
                        const data = {};
                        formData.forEach((value, key) => {
                            data[key] = value;
                        });
                        
                        console.log('Form Data:', data);
                    }
                });
            });
            
            // Monitor AJAX requests for form submissions
            const originalXHR = window.XMLHttpRequest;
            window.XMLHttpRequest = function() {
                const xhr = new originalXHR();
                const originalOpen = xhr.open;
                const originalSend = xhr.send;
                
                xhr.open = function(method, url) {
                    this._url = url;
                    this._method = method;
                    return originalOpen.apply(this, arguments);
                };
                
                xhr.send = function(data) {
                    // Check if it's a form submission
                    if (this._method === 'POST' && this._url && 
                        (this._url.includes('elementor') || this._url.includes('wpr') || this._url.includes('form'))) {
                        console.log('%cAJAX Form Request Detected', 'color: #FF5722; font-weight: bold;');
                        console.log('URL:', this._url);
                        console.log('Data:', data);
                    }
                    
                    return originalSend.apply(this, arguments);
                };
                
                return xhr;
            };
        });
    })();
    </script>
    <?php
}, 100);

/**
 * Log de Contact Form 7 (si está instalado)
 */
add_action('wpcf7_mail_sent', function($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    if ($submission) {
        $posted_data = $submission->get_posted_data();
        
        error_log('=== CONTACT FORM 7 SUBMISSION ===');
        error_log('Form ID: ' . $contact_form->id());
        error_log('Form Title: ' . $contact_form->title());
        error_log('Data: ' . json_encode($posted_data, JSON_PRETTY_PRINT));
        error_log('=================================');
    }
});

/**
 * Log de WPForms (si está instalado)
 */
add_action('wpforms_process_complete', function($fields, $entry, $form_data) {
    error_log('=== WPFORMS SUBMISSION ===');
    error_log('Form ID: ' . $form_data['id']);
    error_log('Form Name: ' . $form_data['settings']['form_title']);
    error_log('Fields: ' . json_encode($fields, JSON_PRETTY_PRINT));
    error_log('==========================');
}, 10, 3);

/**
 * Activar debug logging si no está activado
 */
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    @ini_set('log_errors', 1);
    @ini_set('error_log', ABSPATH . 'wp-content/debug.log');
}