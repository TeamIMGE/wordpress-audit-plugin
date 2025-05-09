<?php

class WPA_Ajax {
    public static function init() {
        add_action('wp_ajax_wpa_load_inline_edit', [__CLASS__, 'load_inline_edit']);
        add_action('wp_ajax_wpa_save_inline_edit', [__CLASS__, 'save_inline_edit']);
        add_action('wp_ajax_wpa_save_alt_text', [__CLASS__, 'save_alt_text']);
        add_action('wp_ajax_wpa_check_image_status', [__CLASS__, 'check_image_status']);
    }

    public static function load_inline_edit() {
        check_ajax_referer('wpa_inline_edit', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $setting = sanitize_text_field($_POST['setting']);
        $current_value = get_option($setting);

        ob_start();
        ?>
        <div class="wpa-form-row">
            <label for="wpa-setting-value">Value:</label>
            <input type="text" 
                   id="wpa-setting-value" 
                   name="wpa_setting_value" 
                   value="<?php echo esc_attr($current_value); ?>" 
                   class="regular-text">
        </div>
        <?php
        $html = ob_get_clean();
        wp_send_json_success($html);
    }

    public static function save_inline_edit() {
        check_ajax_referer('wpa_inline_edit', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $setting = sanitize_text_field($_POST['setting']);
        $value = sanitize_text_field($_POST['value']);

        $result = update_option($setting, $value);

        if ($result) {
            wp_send_json_success('Setting updated successfully');
        } else {
            wp_send_json_error('Failed to update setting');
        }
    }

    public static function save_alt_text() {
        check_ajax_referer('wpa_inline_edit', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $image_id = intval($_POST['image_id']);
        $alt_text = isset($_POST['alt_text']) ? trim($_POST['alt_text']) : '';

        // Validate input
        if (empty($alt_text)) {
            wp_send_json_error('Alternative text cannot be empty');
        }

        // Sanitize input
        $alt_text = sanitize_text_field($alt_text);
        
        // Additional sanitization for alt text
        $alt_text = wp_strip_all_tags($alt_text);
        $alt_text = esc_attr($alt_text);

        // Validate image exists
        if (!get_post($image_id)) {
            wp_send_json_error('Image not found');
        }

        $result = update_post_meta($image_id, '_wp_attachment_image_alt', $alt_text);

        if ($result) {
            wp_send_json_success('Alt text updated successfully');
        } else {
            wp_send_json_error('Failed to update alt text');
        }
    }

    public static function check_image_status() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Insufficient permissions');
        }

        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        if (!$image_id) {
            wp_send_json_error('Invalid image ID');
        }

        // Get the image data
        $image = get_post($image_id);
        if (!$image || $image->post_type !== 'attachment') {
            wp_send_json_error('Image not found');
        }

        // Get image metadata
        $metadata = wp_get_attachment_metadata($image_id);
        $file_path = get_attached_file($image_id);
        
        $issues = [];
        $warnings = [];
        $details = [];
        $status = 'passed';

        // Check dimensions and file size
        if (!empty($metadata['width']) && !empty($metadata['height'])) {
            $width = $metadata['width'];
            $height = $metadata['height'];
            
            // Add dimension details regardless of status
            $details[] = sprintf('Dimensions: %dpx × %dpx', $width, $height);
            
            // Get file size first to determine dimension thresholds
            $size_mb = 0;
            if ($file_path && file_exists($file_path)) {
                $size = filesize($file_path);
                $size_mb = $size / 1024 / 1024; // Convert to MB
                $details[] = sprintf('File size: %.2f MB', $size_mb);
            }
            
            // Only check dimensions if file size is above 500KB
            if ($size_mb >= 0.5) {
                // Width checks
                if ($width >= 2001) {
                    // If file size is below 1MB, only warn for large dimensions
                    if ($size_mb < 1) {
                        $warnings[] = sprintf('Image width (%dpx) exceeds maximum allowed (2000px)', $width);
                        if ($status !== 'failed') {
                            $status = 'warning';
                        }
                    } else {
                        $issues[] = sprintf('Image width (%dpx) exceeds maximum allowed (2000px)', $width);
                        $status = 'failed';
                    }
                } elseif ($width >= 1500) {
                    $warnings[] = sprintf('Image width (%dpx) exceeds recommended size (1500px)', $width);
                    if ($status !== 'failed') {
                        $status = 'warning';
                    }
                }
                
                // Height checks
                if ($height >= 2001) {
                    // If file size is below 1MB, only warn for large dimensions
                    if ($size_mb < 1) {
                        $warnings[] = sprintf('Image height (%dpx) exceeds maximum allowed (2000px)', $height);
                        if ($status !== 'failed') {
                            $status = 'warning';
                        }
                    } else {
                        $issues[] = sprintf('Image height (%dpx) exceeds maximum allowed (2000px)', $height);
                        $status = 'failed';
                    }
                } elseif ($height >= 1500) {
                    $warnings[] = sprintf('Image height (%dpx) exceeds recommended size (1500px)', $height);
                    if ($status !== 'failed') {
                        $status = 'warning';
                    }
                }
            }
        }
        
        // File size checks
        if ($file_path && file_exists($file_path)) {
            $size = filesize($file_path);
            $size_mb = $size / 1024 / 1024; // Convert to MB
            
            if ($size_mb >= 1.5) {
                $issues[] = sprintf('File size (%.2f MB) exceeds maximum allowed (1.5 MB)', $size_mb);
                $status = 'failed';
            } elseif ($size_mb >= 0.5) {
                $warnings[] = sprintf('File size (%.2f MB) exceeds recommended size (500 KB)', $size_mb);
                if ($status !== 'failed') {
                    $status = 'warning';
                }
            }
        }

        wp_send_json_success([
            'status' => $status,
            'issues' => $issues,
            'warnings' => $warnings,
            'details' => $details
        ]);
    }
} 