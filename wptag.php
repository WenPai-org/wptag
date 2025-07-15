<?php
/**
 * Plugin Name: WPTAG
 * Plugin URI: https://wptag.com
 * Description: Professional WordPress code management plugin for statistics, marketing tracking, and third-party scripts
 * Version: 1.0.0
 * Author: WPTAG Team
 * License: GPL v2 or later
 * Text Domain: wptag
 * Domain Path: /languages
 * Requires at least: 6.8
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPTAG_VERSION', '1.0.0');
define('WPTAG_PLUGIN_FILE', __FILE__);
define('WPTAG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPTAG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPTAG_DB_VERSION', '1.0.0');

require_once WPTAG_PLUGIN_DIR . 'includes/class-wptag-core.php';

register_activation_hook(__FILE__, ['WPTag_Core', 'activate']);
register_deactivation_hook(__FILE__, ['WPTag_Core', 'deactivate']);

add_action('plugins_loaded', function() {
    load_plugin_textdomain('wptag', false, dirname(plugin_basename(__FILE__)) . '/languages');
    WPTag_Core::get_instance();
});
