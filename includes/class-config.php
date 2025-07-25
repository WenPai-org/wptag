<?php

namespace WPTag;

if (!defined('ABSPATH')) {
    exit;
}

class Config {
    private $option_name = 'wptag_settings';
    private $services_option = 'wptag_services';
    private $services_config = array();
    private $cached_settings = null;
    private $cached_services = null;

    public function __construct() {
        $this->init_services_config();
    }

    private function init_services_config() {
        $this->services_config = array(
            'google_analytics' => array(
                'name' => 'Google Analytics',
                'category' => 'analytics',
                'field' => 'tracking_id',
                'placeholder' => 'G-XXXXXXXXXX or UA-XXXXXXXXX-X',
                'validation_pattern' => '/^(G-[A-Z0-9]{10}|UA-[0-9]+-[0-9]+)$/',
                'default_position' => 'head',
                'template' => 'google_analytics',
                'icon' => 'dashicons-chart-area',
                'description' => 'Track website traffic and user behavior with Google Analytics'
            ),
            'google_tag_manager' => array(
                'name' => 'Google Tag Manager',
                'category' => 'analytics',
                'field' => 'container_id',
                'placeholder' => 'GTM-XXXXXXX',
                'validation_pattern' => '/^GTM-[A-Z0-9]{7}$/',
                'default_position' => 'head',
                'template' => 'google_tag_manager',
                'icon' => 'dashicons-tag',
                'description' => 'Manage all your website tags through Google Tag Manager'
            ),
            'facebook_pixel' => array(
                'name' => 'Facebook Pixel',
                'category' => 'advertising',
                'field' => 'pixel_id',
                'placeholder' => '123456789012345',
                'validation_pattern' => '/^[0-9]{15}$/',
                'default_position' => 'head',
                'template' => 'facebook_pixel',
                'icon' => 'dashicons-facebook',
                'description' => 'Track conversions and build audiences for Facebook ads'
            ),
            'google_ads' => array(
                'name' => 'Google Ads',
                'category' => 'advertising',
                'field' => 'conversion_id',
                'placeholder' => 'AW-123456789',
                'validation_pattern' => '/^AW-[0-9]{10}$/',
                'default_position' => 'head',
                'template' => 'google_ads',
                'icon' => 'dashicons-googleplus',
                'description' => 'Track conversions for Google Ads campaigns'
            ),
            'microsoft_clarity' => array(
                'name' => 'Microsoft Clarity',
                'category' => 'analytics',
                'field' => 'project_id',
                'placeholder' => 'abcdefghij',
                'validation_pattern' => '/^[a-z0-9]{10}$/',
                'default_position' => 'head',
                'template' => 'microsoft_clarity',
                'icon' => 'dashicons-visibility',
                'description' => 'Free user behavior analytics with heatmaps and session recordings'
            ),
            'hotjar' => array(
                'name' => 'Hotjar',
                'category' => 'analytics',
                'field' => 'site_id',
                'placeholder' => '1234567',
                'validation_pattern' => '/^[0-9]{7}$/',
                'default_position' => 'head',
                'template' => 'hotjar',
                'icon' => 'dashicons-video-alt3',
                'description' => 'Understand user behavior with Hotjar heatmaps and recordings'
            ),
            'tiktok_pixel' => array(
                'name' => 'TikTok Pixel',
                'category' => 'advertising',
                'field' => 'pixel_id',
                'placeholder' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ123456',
                'validation_pattern' => '/^[A-Z0-9]{26}$/',
                'default_position' => 'head',
                'template' => 'tiktok_pixel',
                'icon' => 'dashicons-smartphone',
                'description' => 'Track conversions for TikTok advertising campaigns'
            ),
            'linkedin_insight' => array(
                'name' => 'LinkedIn Insight Tag',
                'category' => 'advertising',
                'field' => 'partner_id',
                'placeholder' => '1234567',
                'validation_pattern' => '/^[0-9]{7}$/',
                'default_position' => 'footer',
                'template' => 'linkedin_insight',
                'icon' => 'dashicons-linkedin',
                'description' => 'Track conversions and retarget visitors for LinkedIn ads'
            ),
            'twitter_pixel' => array(
                'name' => 'Twitter Pixel',
                'category' => 'advertising',
                'field' => 'pixel_id',
                'placeholder' => 'o1234',
                'validation_pattern' => '/^o[0-9]{4}$/',
                'default_position' => 'head',
                'template' => 'twitter_pixel',
                'icon' => 'dashicons-twitter',
                'description' => 'Track conversions for Twitter advertising campaigns'
            ),
            'pinterest_pixel' => array(
                'name' => 'Pinterest Pixel',
                'category' => 'advertising',
                'field' => 'pixel_id',
                'placeholder' => '1234567890123456',
                'validation_pattern' => '/^[0-9]{16}$/',
                'default_position' => 'head',
                'template' => 'pinterest_pixel',
                'icon' => 'dashicons-format-image',
                'description' => 'Track conversions for Pinterest advertising campaigns'
            ),
            'snapchat_pixel' => array(
                'name' => 'Snapchat Pixel',
                'category' => 'advertising',
                'field' => 'pixel_id',
                'placeholder' => 'abcdefgh-1234-5678-9012-abcdefghijkl',
                'validation_pattern' => '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
                'default_position' => 'head',
                'template' => 'snapchat_pixel',
                'icon' => 'dashicons-camera',
                'description' => 'Track conversions for Snapchat advertising campaigns'
            ),
            'google_optimize' => array(
                'name' => 'Google Optimize',
                'category' => 'analytics',
                'field' => 'container_id',
                'placeholder' => 'GTM-XXXXXXX',
                'validation_pattern' => '/^GTM-[A-Z0-9]{7}$/',
                'default_position' => 'head',
                'template' => 'google_optimize',
                'icon' => 'dashicons-admin-settings',
                'description' => 'A/B testing and website optimization tool'
            ),
            'crazyegg' => array(
                'name' => 'Crazy Egg',
                'category' => 'analytics',
                'field' => 'account_id',
                'placeholder' => '12345678',
                'validation_pattern' => '/^[0-9]{8}$/',
                'default_position' => 'head',
                'template' => 'crazyegg',
                'icon' => 'dashicons-admin-tools',
                'description' => 'Heatmap and user session recording tool'
            ),
            'mixpanel' => array(
                'name' => 'Mixpanel',
                'category' => 'analytics',
                'field' => 'project_token',
                'placeholder' => 'abcdefghijklmnopqrstuvwxyz123456',
                'validation_pattern' => '/^[a-z0-9]{32}$/',
                'default_position' => 'head',
                'template' => 'mixpanel',
                'icon' => 'dashicons-chart-pie',
                'description' => 'Advanced analytics to understand user behavior'
            ),
            'amplitude' => array(
                'name' => 'Amplitude',
                'category' => 'analytics',
                'field' => 'api_key',
                'placeholder' => 'abcdefghijklmnopqrstuvwxyz123456',
                'validation_pattern' => '/^[a-z0-9]{32}$/',
                'default_position' => 'head',
                'template' => 'amplitude',
                'icon' => 'dashicons-chart-bar',
                'description' => 'Product analytics for mobile and web'
            ),
            'matomo' => array(
                'name' => 'Matomo',
                'category' => 'analytics',
                'field' => 'site_id',
                'placeholder' => '1',
                'validation_pattern' => '/^[0-9]+$/',
                'default_position' => 'head',
                'template' => 'matomo',
                'icon' => 'dashicons-chart-line',
                'description' => 'Privacy-focused web analytics platform'
            )
        );

        $this->services_config = apply_filters('wptag_services_config', $this->services_config);
    }

    public function get_all_services() {
        return $this->services_config;
    }

    public function get_enabled_services() {
        if (null === $this->cached_services) {
            $default_services = array('google_analytics', 'google_tag_manager', 'facebook_pixel', 'google_ads');
            $this->cached_services = get_option($this->services_option, $default_services);
            
            if (!is_array($this->cached_services)) {
                $this->cached_services = $default_services;
            }
        }
        return $this->cached_services;
    }

    public function update_enabled_services($services) {
        $services = is_array($services) ? array_filter($services) : array();
        
        $valid_services = array();
        foreach ($services as $service_key) {
            if (isset($this->services_config[$service_key])) {
                $valid_services[] = $service_key;
            }
        }
        
        $this->cached_services = $valid_services;
        $result = update_option($this->services_option, $this->cached_services);
        
        if ($result) {
            $this->cached_settings = null;
            wp_cache_delete('wptag_available_services', 'wptag');
            do_action('wptag_enabled_services_updated', $this->cached_services);
        }
        
        return $result;
    }

    public function get_available_services() {
        $cache_key = 'wptag_available_services';
        $cached = wp_cache_get($cache_key, 'wptag');
        
        if (false !== $cached) {
            return $cached;
        }
        
        $enabled_services = $this->get_enabled_services();
        $available = array();
        
        foreach ($enabled_services as $service_key) {
            if (isset($this->services_config[$service_key])) {
                $available[$service_key] = $this->services_config[$service_key];
            }
        }
        
        wp_cache_set($cache_key, $available, 'wptag', 3600);
        return $available;
    }

    public function get_services_config() {
        return $this->get_available_services();
    }

    public function get_service_config($service_key) {
        return isset($this->services_config[$service_key]) ? $this->services_config[$service_key] : null;
    }

    public function get_services_by_category($category) {
        $available_services = $this->get_available_services();
        return array_filter($available_services, function($service) use ($category) {
            return $service['category'] === $category;
        });
    }

    public function get_categories() {
        $available_services = $this->get_available_services();
        $categories = array();
        
        foreach ($available_services as $service_key => $service) {
            if (!in_array($service['category'], $categories)) {
                $categories[] = $service['category'];
            }
        }
        
        return $categories;
    }

    public function get_settings() {
        if (null === $this->cached_settings) {
            $this->cached_settings = get_option($this->option_name, array());
            
            if (!is_array($this->cached_settings)) {
                $this->cached_settings = array();
            }
            
            $this->cached_settings = $this->merge_with_defaults($this->cached_settings);
        }
        return $this->cached_settings;
    }

    private function merge_with_defaults($settings) {
        $available_services = $this->get_available_services();
        $merged_settings = array();
        
        foreach ($available_services as $service_key => $service_config) {
            if (isset($settings[$service_key]) && is_array($settings[$service_key])) {
                $merged_settings[$service_key] = array_merge(
                    $this->get_default_service_settings($service_key),
                    $settings[$service_key]
                );
                
                $merged_settings[$service_key]['updated_at'] = current_time('mysql');
            } else {
                $merged_settings[$service_key] = $this->get_default_service_settings($service_key);
            }
        }
        
        return $merged_settings;
    }

    public function get_service_settings($service_key) {
        $settings = $this->get_settings();
        return isset($settings[$service_key]) ? $settings[$service_key] : $this->get_default_service_settings($service_key);
    }

    public function update_settings($new_settings) {
        if (!is_array($new_settings)) {
            return false;
        }
        
        $sanitized_settings = $this->sanitize_settings($new_settings);
        $result = update_option($this->option_name, $sanitized_settings);
        
        if ($result) {
            $this->cached_settings = null;
            wp_cache_delete('wptag_available_services', 'wptag');
            wp_cache_set_last_changed('wptag_codes');
            
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('wptag');
            }
            
            do_action('wptag_settings_updated', $sanitized_settings);
            do_action('wptag_clear_cache');
        }
        
        return $result;
    }

    public function update_service_settings($service_key, $service_settings) {
        $all_settings = $this->get_settings();
        $all_settings[$service_key] = $this->sanitize_service_settings($service_settings, $service_key);
        
        return $this->update_settings($all_settings);
    }

    private function get_default_service_settings($service_key) {
        $service_config = $this->get_service_config($service_key);
        if (!$service_config) {
            return array();
        }

        $defaults = array(
            'enabled' => false,
            'use_template' => true,
            'custom_code' => '',
            'position' => $service_config['default_position'],
            'priority' => 10,
            'device' => 'all',
            'conditions' => array(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $defaults[$service_config['field']] = '';

        return apply_filters('wptag_default_service_settings', $defaults, $service_key);
    }

    public function sanitize_settings($settings) {
        if (!is_array($settings)) {
            return array();
        }
        
        $sanitized = array();
        
        foreach ($settings as $service_key => $service_settings) {
            if (isset($this->services_config[$service_key]) && is_array($service_settings)) {
                $sanitized[$service_key] = $this->sanitize_service_settings($service_settings, $service_key);
            }
        }
        
        return $sanitized;
    }

    private function sanitize_service_settings($settings, $service_key = '') {
        if (!is_array($settings)) {
            return $this->get_default_service_settings($service_key);
        }
        
        $service_config = $this->get_service_config($service_key);
        
        $sanitized = array(
            'enabled' => !empty($settings['enabled']),
            'use_template' => isset($settings['use_template']) ? (bool)$settings['use_template'] : true,
            'custom_code' => $this->sanitize_custom_code($settings['custom_code'] ?? ''),
            'position' => $this->sanitize_position($settings['position'] ?? 'head'),
            'priority' => $this->sanitize_priority($settings['priority'] ?? 10),
            'device' => $this->sanitize_device($settings['device'] ?? 'all'),
            'conditions' => $this->sanitize_conditions($settings['conditions'] ?? array()),
            'updated_at' => current_time('mysql')
        );
        
        if ($service_config && isset($service_config['field'])) {
            $field_key = $service_config['field'];
            $sanitized[$field_key] = sanitize_text_field($settings[$field_key] ?? '');
        }
        
        if (isset($settings['created_at'])) {
            $sanitized['created_at'] = sanitize_text_field($settings['created_at']);
        }
        
        return apply_filters('wptag_sanitize_service_settings', $sanitized, $service_key, $settings);
    }

    private function get_allowed_html() {
        return array(
            'script' => array(
                'type' => array(),
                'src' => array(),
                'async' => array(),
                'defer' => array(),
                'id' => array(),
                'class' => array(),
                'crossorigin' => array(),
                'integrity' => array()
            ),
            'noscript' => array(),
            'img' => array(
                'src' => array(),
                'alt' => array(),
                'width' => array(),
                'height' => array(),
                'style' => array()
            ),
            'iframe' => array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'style' => array(),
                'frameborder' => array()
            )
        );
    }

    private function sanitize_position($position) {
        $valid_positions = array('head', 'body', 'footer');
        return in_array($position, $valid_positions) ? $position : 'head';
    }

    private function sanitize_priority($priority) {
        $priority = intval($priority);
        return ($priority >= 1 && $priority <= 100) ? $priority : 10;
    }

    private function sanitize_device($device) {
        $valid_devices = array('all', 'desktop', 'mobile');
        return in_array($device, $valid_devices) ? $device : 'all';
    }

    private function sanitize_conditions($conditions) {
        if (!is_array($conditions)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($conditions as $condition) {
            if (is_array($condition)) {
                $sanitized[] = array(
                    'type' => sanitize_text_field($condition['type'] ?? ''),
                    'operator' => sanitize_text_field($condition['operator'] ?? 'is'),
                    'value' => sanitize_text_field($condition['value'] ?? '')
                );
            }
        }
        
        return $sanitized;
    }

    private function sanitize_custom_code($custom_code) {
        if (empty($custom_code)) {
            return '';
        }
        
        $custom_code = stripslashes($custom_code);
        
        $custom_code = wp_check_invalid_utf8($custom_code);
        
        $allowed_tags = array(
            'script' => array(
                'type' => array(),
                'src' => array(),
                'async' => array(),
                'defer' => array(),
                'id' => array(),
                'class' => array(),
                'crossorigin' => array(),
                'integrity' => array(),
                'charset' => array()
            ),
            'noscript' => array(),
            'img' => array(
                'src' => array(),
                'alt' => array(),
                'width' => array(),
                'height' => array(),
                'style' => array(),
                'border' => array()
            ),
            'iframe' => array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'style' => array(),
                'frameborder' => array(),
                'scrolling' => array(),
                'marginheight' => array(),
                'marginwidth' => array()
            ),
            'div' => array(
                'id' => array(),
                'class' => array(),
                'style' => array()
            ),
            'span' => array(
                'id' => array(),
                'class' => array(),
                'style' => array()
            )
        );
        
        if (!current_user_can('unfiltered_html')) {
            $custom_code = wp_kses($custom_code, $allowed_tags);
        }
        
        $custom_code = $this->remove_dangerous_patterns($custom_code);
        
        return trim($custom_code);
    }

    private function remove_dangerous_patterns($code) {
        $dangerous_patterns = array(
            '/<script[^>]*>\s*eval\s*\(/i',
            '/<script[^>]*>\s*Function\s*\(/i',
            '/javascript\s*:\s*void/i',
            '/data:text\/html/i',
            '/vbscript:/i'
        );
        
        foreach ($dangerous_patterns as $pattern) {
            $code = preg_replace($pattern, '', $code);
        }
        
        $code = preg_replace_callback(
            '/<script[^>]*>(.*?)<\/script>/is',
            function($matches) {
                $script_content = $matches[1];
                
                $blocked_functions = array(
                    'eval(',
                    'Function(',
                    'execScript(',
                    'setTimeout("',
                    "setTimeout('",
                    'setInterval("',
                    "setInterval('",
                    'document.write(',
                    'document.writeln(',
                    'window.location=',
                    'location.href=',
                    'location.replace('
                );
                
                foreach ($blocked_functions as $blocked) {
                    if (stripos($script_content, $blocked) !== false) {
                        return '<!-- Blocked potentially dangerous script -->';
                    }
                }
                
                return $matches[0];
            },
            $code
        );
        
        return $code;
    }

    public function install_default_settings() {
        $existing_settings = get_option($this->option_name);
        $existing_services = get_option($this->services_option);
        
        if (false === $existing_settings) {
            $default_settings = array();
            $default_services = array('google_analytics', 'google_tag_manager', 'facebook_pixel', 'google_ads');
            
            foreach ($default_services as $service_key) {
                $default_settings[$service_key] = $this->get_default_service_settings($service_key);
            }
            
            add_option($this->option_name, $default_settings);
        }
        
        if (false === $existing_services) {
            add_option($this->services_option, array('google_analytics', 'google_tag_manager', 'facebook_pixel', 'google_ads'));
        }
        
        do_action('wptag_settings_installed');
    }

    public function reset_to_defaults() {
        delete_option($this->option_name);
        delete_option($this->services_option);
        
        $this->cached_settings = null;
        $this->cached_services = null;
        
        wp_cache_delete('wptag_available_services', 'wptag');
        
        $this->install_default_settings();
        
        do_action('wptag_settings_reset');
        
        return true;
    }

    public function export_settings() {
        $settings = $this->get_settings();
        $services = $this->get_enabled_services();
        
        $export_data = array(
            'version' => WPTAG_VERSION,
            'exported_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'services' => $services,
            'settings' => $settings,
            'plugin_info' => array(
                'name' => 'WPTag',
                'version' => WPTAG_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION
            )
        );
        
        return wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function import_settings($json_data) {
        $data = json_decode($json_data, true);
        
        if (!is_array($data)) {
            return new \WP_Error('invalid_json', 'Invalid JSON format');
        }
        
        if (!isset($data['settings']) || !is_array($data['settings'])) {
            return new \WP_Error('invalid_data', 'Invalid import data format - missing settings');
        }
        
        if (isset($data['version'])) {
            $compatibility_check = $this->check_version_compatibility($data['version']);
            if (is_wp_error($compatibility_check)) {
                return $compatibility_check;
            }
        }
        
        $settings_result = false;
        $services_result = false;
        
        if (isset($data['services']) && is_array($data['services'])) {
            $services_result = $this->update_enabled_services($data['services']);
        }
        
        $settings_result = $this->update_settings($data['settings']);
        
        if ($settings_result) {
            do_action('wptag_settings_imported', $data);
            return true;
        }
        
        return new \WP_Error('import_failed', 'Failed to import settings');
    }

    private function check_version_compatibility($import_version) {
        $current_version = WPTAG_VERSION;
        
        if (version_compare($import_version, $current_version, '>')) {
            return new \WP_Error('version_incompatible', 'Import data is from a newer version (' . $import_version . ') and may not be compatible with the current version (' . $current_version . ')');
        }
        
        $min_compatible_version = '1.0.0';
        if (version_compare($import_version, $min_compatible_version, '<')) {
            return new \WP_Error('version_too_old', 'Import data is from an incompatible version (' . $import_version . '). Minimum supported version is ' . $min_compatible_version);
        }
        
        return true;
    }

    public function get_plugin_stats() {
        $settings = $this->get_settings();
        $enabled_services = $this->get_enabled_services();
        
        $stats = array(
            'total_services' => count($this->services_config),
            'enabled_services' => count($enabled_services),
            'active_codes' => 0,
            'categories' => array(),
            'last_updated' => ''
        );
        
        foreach ($settings as $service_key => $service_settings) {
            if (!empty($service_settings['enabled'])) {
                $stats['active_codes']++;
            }
            
            if (!empty($service_settings['updated_at'])) {
                if (empty($stats['last_updated']) || $service_settings['updated_at'] > $stats['last_updated']) {
                    $stats['last_updated'] = $service_settings['updated_at'];
                }
            }
        }
        
        $available_services = $this->get_available_services();
        foreach ($available_services as $service_key => $service_config) {
            $category = $service_config['category'];
            if (!isset($stats['categories'][$category])) {
                $stats['categories'][$category] = 0;
            }
            $stats['categories'][$category]++;
        }
        
        return $stats;
    }

    public function cleanup_orphaned_settings() {
        $settings = get_option($this->option_name, array());
        $enabled_services = $this->get_enabled_services();
        
        $cleaned_settings = array();
        foreach ($settings as $service_key => $service_settings) {
            if (in_array($service_key, $enabled_services) && isset($this->services_config[$service_key])) {
                $cleaned_settings[$service_key] = $service_settings;
            }
        }
        
        if (count($cleaned_settings) !== count($settings)) {
            update_option($this->option_name, $cleaned_settings);
            $this->cached_settings = null;
            return true;
        }
        
        return false;
    }

    public function migrate_settings($from_version) {
        do_action('wptag_before_settings_migration', $from_version, WPTAG_VERSION);
        
        $migrated = false;
        
        do_action('wptag_after_settings_migration', $from_version, WPTAG_VERSION, $migrated);
        
        return $migrated;
    }
}