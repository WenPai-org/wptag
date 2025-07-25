<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!current_user_can('activate_plugins')) {
    exit;
}

if (!defined('WPTAG_VERSION')) {
    define('WPTAG_VERSION', '1.0.0');
}

class WPTag_Uninstaller {
    
    private static $options_to_delete = array(
        'wptag_settings',
        'wptag_services',
        'wptag_version',
        'wptag_admin_preview',
        'wptag_excluded_urls',
        'wptag_excluded_post_types',
        'wptag_excluded_user_roles',
        'wptag_custom_css',
        'wptag_custom_js',
        'wptag_debug_mode',
        'wptag_cache_enabled',
        'wptag_last_cleanup'
    );

    private static $transients_to_delete = array(
        'wptag_admin_notices_',
        'wptag_validation_cache_',
        'wptag_output_cache_',
        'wptag_stats_cache'
    );

    private static $user_meta_to_delete = array(
        'wptag_dismissed_notices',
        'wptag_last_settings_view',
        'wptag_preferred_tab'
    );

    private static $capabilities_to_remove = array(
        'wptag_manage_codes',
        'wptag_manage_services'
    );

    public static function uninstall() {
        global $wpdb;

        do_action('wptag_before_uninstall');

        self::delete_options();
        self::delete_transients();
        self::delete_user_meta();
        self::remove_capabilities();
        self::clear_cache();
        self::cleanup_database();
        
        if (is_multisite()) {
            self::cleanup_multisite();
        }

        do_action('wptag_after_uninstall');

        self::log_uninstall();
    }

    private static function delete_options() {
        foreach (self::$options_to_delete as $option) {
            delete_option($option);
        }

        $wpdb = $GLOBALS['wpdb'];
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                'wptag_%'
            )
        );
    }

    private static function delete_transients() {
        $wpdb = $GLOBALS['wpdb'];

        foreach (self::$transients_to_delete as $transient_prefix) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    '_transient_' . $transient_prefix . '%',
                    '_transient_timeout_' . $transient_prefix . '%'
                )
            );
        }

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_wptag_%',
                '_transient_timeout_wptag_%'
            )
        );
    }

    private static function delete_user_meta() {
        $wpdb = $GLOBALS['wpdb'];

        foreach (self::$user_meta_to_delete as $meta_key) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
                    $meta_key
                )
            );
        }

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
                'wptag_%'
            )
        );
    }

    private static function remove_capabilities() {
        $roles = wp_roles();
        
        if (!$roles) {
            return;
        }

        foreach ($roles->roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role) {
                foreach (self::$capabilities_to_remove as $capability) {
                    $role->remove_cap($capability);
                }
            }
        }
    }

    private static function clear_cache() {
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        wp_cache_delete('wptag_available_services', 'wptag');
        wp_cache_delete('wptag_settings', 'wptag');
        wp_cache_delete('wptag_enabled_services', 'wptag');

        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        if (class_exists('WpFastestCache')) {
            $cache = new WpFastestCache();
            if (method_exists($cache, 'deleteCache')) {
                $cache->deleteCache(true);
            }
        }

        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        if (class_exists('LiteSpeed_Cache_API')) {
            LiteSpeed_Cache_API::purge_all();
        }
    }

    private static function cleanup_database() {
        $wpdb = $GLOBALS['wpdb'];

        $tables_to_check = array(
            $wpdb->options,
            $wpdb->usermeta,
            $wpdb->postmeta
        );

        foreach ($tables_to_check as $table) {
            if ($table === $wpdb->postmeta) {
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$table} WHERE meta_key LIKE %s",
                        'wptag_%'
                    )
                );
            }
        }

        $wpdb->query("OPTIMIZE TABLE {$wpdb->options}");
        $wpdb->query("OPTIMIZE TABLE {$wpdb->usermeta}");
        $wpdb->query("OPTIMIZE TABLE {$wpdb->postmeta}");
    }

    private static function cleanup_multisite() {
        if (!is_multisite()) {
            return;
        }

        $sites = get_sites(array(
            'number' => 0,
            'fields' => 'ids'
        ));

        foreach ($sites as $site_id) {
            switch_to_blog($site_id);

            foreach (self::$options_to_delete as $option) {
                delete_option($option);
            }

            self::delete_transients();
            self::delete_user_meta();
            self::remove_capabilities();

            restore_current_blog();
        }

        $wpdb = $GLOBALS['wpdb'];
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
                'wptag_%'
            )
        );
    }

    private static function log_uninstall() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_data = array(
            'plugin' => 'WPTag',
            'version' => WPTAG_VERSION,
            'uninstalled_at' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'site_url' => get_site_url(),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'is_multisite' => is_multisite()
        );

        error_log('WPTag Uninstall: ' . wp_json_encode($log_data));
    }

    public static function get_cleanup_summary() {
        $wpdb = $GLOBALS['wpdb'];
        
        $summary = array(
            'options_deleted' => 0,
            'transients_deleted' => 0,
            'user_meta_deleted' => 0,
            'capabilities_removed' => count(self::$capabilities_to_remove),
            'cache_cleared' => true,
            'database_optimized' => true
        );

        $options_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                'wptag_%'
            )
        );
        $summary['options_deleted'] = (int) $options_count;

        $transients_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_wptag_%',
                '_transient_timeout_wptag_%'
            )
        );
        $summary['transients_deleted'] = (int) $transients_count;

        $user_meta_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
                'wptag_%'
            )
        );
        $summary['user_meta_deleted'] = (int) $user_meta_count;

        return $summary;
    }
}

WPTag_Uninstaller::uninstall();