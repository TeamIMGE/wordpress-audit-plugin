<?php

class WPA_Ajax {
    public static function init() {
        add_action('wp_ajax_wpa_load_inline_edit', [__CLASS__, 'load_inline_edit']);
        add_action('wp_ajax_wpa_save_inline_edit', [__CLASS__, 'save_inline_edit']);
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
} 