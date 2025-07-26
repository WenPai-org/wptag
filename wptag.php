<?php
/**
 * Plugin Name: WPTag
 * Plugin URI: https://wptag.com
 * Description: Professional tracking codes and analytics management plugin for WordPress
 * Version: 1.1.0
 * Author: WPTag.com
 * Author URI: https://wptag.com
 * Text Domain: wptag
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

namespace WPTag;

if (!defined('ABSPATH')) {
    exit;
}

define('WPTAG_VERSION', '1.1.0');
define('WPTAG_PLUGIN_FILE', __FILE__);
define('WPTAG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPTAG_PLUGIN_URL', plugin_dir_url(__FILE__));

final class WPTag {
    private static $instance = null;
    private $config;
    private $admin;
    private $frontend;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function define_constants() {
        if (!defined('WPTAG_ABSPATH')) {
            define('WPTAG_ABSPATH', dirname(WPTAG_PLUGIN_FILE) . '/');
        }
    }

    private function load_dependencies() {
        require_once WPTAG_PLUGIN_DIR . 'includes/class-config.php';
        require_once WPTAG_PLUGIN_DIR . 'includes/class-validator.php';
        require_once WPTAG_PLUGIN_DIR . 'includes/class-output-manager.php';
        
        $this->config = new Config();
        
        if (is_admin()) {
            require_once WPTAG_PLUGIN_DIR . 'includes/class-admin.php';
            $this->admin = new Admin($this->config);
        }
        
        if (!is_admin() || wp_doing_ajax()) {
            require_once WPTAG_PLUGIN_DIR . 'includes/class-frontend.php';
            $this->frontend = new Frontend($this->config);
        }
    }

    private function init_hooks() {
        register_activation_hook(WPTAG_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(WPTAG_PLUGIN_FILE, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'), 0);
        add_action('plugins_loaded', array($this, 'plugins_loaded'));
        add_action('wptag_clear_cache', array($this, 'clear_all_cache'));
    }

    public function activate() {
        if (!get_option('wptag_version')) {
            $this->config->install_default_settings();
            add_option('wptag_version', WPTAG_VERSION);
        }
        
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function init() {
        do_action('wptag_init');
    }

    public function plugins_loaded() {
        do_action('wptag_loaded');
    }

    public function get_config() {
        return $this->config;
    }

    public function get_version() {
        return WPTAG_VERSION;
    }

    public function clear_all_cache() {
        if ($this->frontend) {
            $this->frontend->clear_cache();
        }
        
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
}

function wptag() {
    return WPTag::instance();
}

wptag();