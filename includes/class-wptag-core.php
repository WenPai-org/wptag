<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPTag_Core {
    private static $instance = null;
    private $snippet_manager;
    private $condition_engine;
    private $output_controller;
    private $template_manager;
    private $cache_manager;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init();
    }

    private function load_dependencies() {
        require_once WPTAG_PLUGIN_DIR . 'includes/class-wptag-snippet-manager.php';
        require_once WPTAG_PLUGIN_DIR . 'includes/class-wptag-condition-engine.php';
        require_once WPTAG_PLUGIN_DIR . 'includes/class-wptag-output-controller.php';
        require_once WPTAG_PLUGIN_DIR . 'includes/class-wptag-template-manager.php';
        require_once WPTAG_PLUGIN_DIR . 'includes/class-wptag-cache-manager.php';

        if (is_admin()) {
            require_once WPTAG_PLUGIN_DIR . 'admin/class-wptag-admin-controller.php';
            require_once WPTAG_PLUGIN_DIR . 'admin/class-wptag-ajax-handler.php';
            require_once WPTAG_PLUGIN_DIR . 'admin/class-wptag-admin-interface.php';
        }
    }

    private function init() {
        $this->snippet_manager = new WPTag_Snippet_Manager();
        $this->condition_engine = new WPTag_Condition_Engine();
        $this->template_manager = new WPTag_Template_Manager();
        $this->cache_manager = new WPTag_Cache_Manager();
        $this->output_controller = new WPTag_Output_Controller(
            $this->snippet_manager,
            $this->condition_engine,
            $this->cache_manager
        );

        if (is_admin()) {
            new WPTag_Admin_Controller($this->snippet_manager, $this->template_manager);
            new WPTag_Ajax_Handler($this->snippet_manager, $this->template_manager);
        } else {
            $this->register_output_hooks();
        }

        add_action('init', [$this, 'check_version']);
    }

    private function register_output_hooks() {
        add_action('wp_head', [$this->output_controller, 'render_head'], 1);
        add_action('wp_footer', [$this->output_controller, 'render_footer'], 999);
        add_filter('the_content', [$this->output_controller, 'filter_content'], 10);
    }

    public function check_version() {
        $installed_version = get_option('wptag_db_version');
        if ($installed_version !== WPTAG_DB_VERSION) {
            self::create_tables();
            update_option('wptag_db_version', WPTAG_DB_VERSION);
        }
    }

    public static function activate() {
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            deactivate_plugins(plugin_basename(WPTAG_PLUGIN_FILE));
            wp_die('WPTAG requires PHP 8.0 or higher.');
        }

        if (version_compare(get_bloginfo('version'), '6.8', '<')) {
            deactivate_plugins(plugin_basename(WPTAG_PLUGIN_FILE));
            wp_die('WPTAG requires WordPress 6.8 or higher.');
        }

        self::create_tables();
        self::create_default_templates();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('wptag_cleanup_logs');
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql_snippets = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wptag_snippets (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            code longtext NOT NULL,
            code_type varchar(50) DEFAULT 'html',
            position varchar(100) NOT NULL,
            category varchar(100) DEFAULT 'custom',
            priority int(11) DEFAULT 10,
            status tinyint(1) DEFAULT 1,
            conditions longtext,
            device_type varchar(50) DEFAULT 'all',
            load_method varchar(50) DEFAULT 'normal',
            created_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_modified_by bigint(20) unsigned,
            PRIMARY KEY (id),
            KEY idx_status_position (status, position),
            KEY idx_category (category),
            KEY idx_priority (priority)
        ) $charset_collate;";

        $sql_templates = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wptag_templates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            service_type varchar(100) NOT NULL,
            service_name varchar(255) NOT NULL,
            service_category varchar(100) NOT NULL,
            config_fields longtext,
            code_template longtext NOT NULL,
            default_position varchar(100) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            version varchar(20) DEFAULT '1.0',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_service_type (service_type),
            KEY idx_category (service_category)
        ) $charset_collate;";

        $sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wptag_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) unsigned,
            old_value longtext,
            new_value longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_action (user_id, action),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_snippets);
        dbDelta($sql_templates);
        dbDelta($sql_logs);
    }

    private static function create_default_templates() {
        global $wpdb;
        $table = $wpdb->prefix . 'wptag_templates';

        $templates = [
            [
                'service_type' => 'google_analytics_4',
                'service_name' => 'Google Analytics 4',
                'service_category' => 'analytics',
                'config_fields' => json_encode([
                    ['name' => 'measurement_id', 'label' => 'Measurement ID', 'type' => 'text', 'required' => true]
                ]),
                'code_template' => '<script async src="https://www.googletagmanager.com/gtag/js?id={{measurement_id}}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag("js", new Date());
gtag("config", "{{measurement_id}}");
</script>',
                'default_position' => 'head'
            ],
            [
                'service_type' => 'facebook_pixel',
                'service_name' => 'Facebook Pixel',
                'service_category' => 'marketing',
                'config_fields' => json_encode([
                    ['name' => 'pixel_id', 'label' => 'Pixel ID', 'type' => 'text', 'required' => true]
                ]),
                'code_template' => '<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,"script",
"https://connect.facebook.net/en_US/fbevents.js");
fbq("init", "{{pixel_id}}");
fbq("track", "PageView");
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id={{pixel_id}}&ev=PageView&noscript=1"
/></noscript>',
                'default_position' => 'head'
            ],
            [
                'service_type' => 'google_ads',
                'service_name' => 'Google Ads Conversion',
                'service_category' => 'marketing',
                'config_fields' => json_encode([
                    ['name' => 'conversion_id', 'label' => 'Conversion ID', 'type' => 'text', 'required' => true],
                    ['name' => 'conversion_label', 'label' => 'Conversion Label', 'type' => 'text', 'required' => true]
                ]),
                'code_template' => '<script async src="https://www.googletagmanager.com/gtag/js?id=AW-{{conversion_id}}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag("js", new Date());
gtag("config", "AW-{{conversion_id}}");
gtag("event", "conversion", {"send_to": "AW-{{conversion_id}}/{{conversion_label}}"});
</script>',
                'default_position' => 'head'
            ],
            [
                'service_type' => 'google_search_console',
                'service_name' => 'Google Search Console',
                'service_category' => 'seo',
                'config_fields' => json_encode([
                    ['name' => 'verification_code', 'label' => 'Verification Code', 'type' => 'text', 'required' => true]
                ]),
                'code_template' => '<meta name="google-site-verification" content="{{verification_code}}" />',
                'default_position' => 'head'
            ],
            [
                'service_type' => 'baidu_tongji',
                'service_name' => 'Baidu Tongji',
                'service_category' => 'analytics',
                'config_fields' => json_encode([
                    ['name' => 'site_id', 'label' => 'Site ID', 'type' => 'text', 'required' => true]
                ]),
                'code_template' => '<script>
var _hmt = _hmt || [];
(function() {
  var hm = document.createElement("script");
  hm.src = "https://hm.baidu.com/hm.js?{{site_id}}";
  var s = document.getElementsByTagName("script")[0]; 
  s.parentNode.insertBefore(hm, s);
})();
</script>',
                'default_position' => 'head'
            ]
        ];

        foreach ($templates as $template) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE service_type = %s",
                $template['service_type']
            ));

            if (!$exists) {
                $wpdb->insert($table, $template);
            }
        }
    }
}
