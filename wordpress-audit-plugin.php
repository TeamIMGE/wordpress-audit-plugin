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

define( 'WPA_VERSION', '1.0.0' );
define( 'WPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPA_PLUGIN_DIR . 'includes/class-wpa-core.php';

add_action( 'plugins_loaded', ['WPA_Core', 'init'] );
