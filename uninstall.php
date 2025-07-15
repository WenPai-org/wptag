<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings = get_option('wptag_settings', []);

if (empty($settings['cleanup_on_uninstall'])) {
    return;
}

global $wpdb;

$tables = [
    $wpdb->prefix . 'wptag_snippets',
    $wpdb->prefix . 'wptag_templates',
    $wpdb->prefix . 'wptag_logs'
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

$options = [
    'wptag_db_version',
    'wptag_settings',
    'wptag_activated',
    'wptag_cache_cleared'
];

foreach ($options as $option) {
    delete_option($option);
}

delete_transient('wptag_admin_notice');

wp_clear_scheduled_hook('wptag_cleanup_logs');
wp_clear_scheduled_hook('wptag_cache_cleanup');

wp_cache_flush();
