<?php
/**
 * Plugin Name: WordPress Audit Plugin
 * Description: Audits site settings, SEO configs, and image metadata. Sends weekly reports to assigned users.
 * Version: 1.0.0
 * Author: Danny Botta
 * Author URI: https://imge.com
 * GitHub Plugin URI: https://github.com/teamigme/wordpress-audit-plugin
 * Primary Branch: main
 * License: GPL2
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php'; // Composer autoload

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/TeamIMGE/wordpress-audit-plugin',
    __FILE__,
    'wordpress-audit-plugin'
);

$updateChecker->setBranch('main');

define( 'WPA_VERSION', '1.0.0' );
define( 'WPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPA_PLUGIN_DIR . 'includes/class-wpa-core.php';
require_once WPA_PLUGIN_DIR . 'includes/class-wpa-ajax.php';

add_action( 'plugins_loaded', ['WPA_Core', 'init'] );

// Initialize the plugin
function wpa_init() {
    WPA_Admin::init();
    WPA_Auditor::init();
    WPA_Ajax::init();
}
add_action('plugins_loaded', 'wpa_init');

// Define the option name for the encryption key
define('WPA_ENCRYPTION_KEY_OPTION_NAME', 'wpa_encryption_key');

/**
 * Generates and stores a unique encryption key on plugin activation.
 * This key is stored as a protected option (autoload=false) in the database.
 */
function wpa_generate_and_store_encryption_key() {
    // Check if the key already exists
    $encryption_key = get_option(WPA_ENCRYPTION_KEY_OPTION_NAME, false);

    if (false === $encryption_key) {
        // Key does not exist, generate a new one
        // 32 bytes = 256 bits, suitable for aes-256-cbc
        $key_length_bytes = 32;
        try {
            // Generate a random key and convert to hexadecimal for storage
            $new_key = bin2hex(random_bytes($key_length_bytes));
             // Store the key as a protected option (autoload=false)
            add_option(WPA_ENCRYPTION_KEY_OPTION_NAME, $new_key, '', 'no'); // 'no' for autoload false
        } catch (Exception $e) {
             // Handle potential errors in random_bytes generation
             error_log('WPA Plugin: Failed to generate encryption key on activation - ' . $e->getMessage());
             // Optionally, you could display an admin notice here
        }
    }
    // If the key exists, do nothing on activation
}

// Register the activation hook to generate and store the encryption key
register_activation_hook(__FILE__, 'wpa_generate_and_store_encryption_key');


/**
 * Retrieves the encryption key from the database option.
 *
 * @return string|false The encryption key on success, or false if not found.
 */
function wpa_get_encryption_key() {
    return get_option(WPA_ENCRYPTION_KEY_OPTION_NAME, false);
}

/**
 * Cleans up plugin options upon uninstallation.
 */
function wpa_uninstall_cleanup() {
    // Delete the AWS settings option (contains encrypted credentials)
    delete_option('wpa_aws_settings');

    // Delete the encryption key option
    delete_option(WPA_ENCRYPTION_KEY_OPTION_NAME);

    // Add any other options or data created by your plugin that should be removed on uninstall
    // Example: delete_option('wpa_responsible_users');
}

// Register the uninstall hook
register_uninstall_hook(__FILE__, 'wpa_uninstall_cleanup');
