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
    }

    public function get_all_services() {
        return $this->services_config;
    }

    public function get_enabled_services() {
        if (null === $this->cached_services) {
            $this->cached_services = get_option($this->services_option, array('google_analytics', 'google_tag_manager', 'facebook_pixel', 'google_ads'));
        }
        return $this->cached_services;
    }

    public function update_enabled_services($services) {
        $this->cached_services = is_array($services) ? $services : array();
        $result = update_option($this->services_option, $this->cached_services);
        
        if ($result) {
            $this->cached_settings = null;
        }
        
        return $result;
    }

    public function get_available_services() {
        $enabled_services = $this->get_enabled_services();
        $available = array();
        
        foreach ($enabled_services as $service_key) {
            if (isset($this->services_config[$service_key])) {
                $available[$service_key] = $this->services_config[$service_key];
            }
        }
        
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
        
        foreach ($available_services as $service) {
            if (!in_array($service['category'], $categories)) {
                $categories[] = $service['category'];
            }
        }
        
        return $categories;
    }

    public function get_settings() {
        if (null === $this->cached_settings) {
            $this->cached_settings = get_option($this->option_name, $this->get_default_settings());
        }
        return $this->cached_settings;
    }

    public function get_service_settings($service_key) {
        $settings = $this->get_settings();
        return isset($settings[$service_key]) ? $settings[$service_key] : $this->get_default_service_settings($service_key);
    }

    public function update_settings($new_settings) {
        $sanitized_settings = $this->sanitize_settings($new_settings);
        $result = update_option($this->option_name, $sanitized_settings);
        
        if ($result) {
            $this->cached_settings = $sanitized_settings;
            do_action('wptag_settings_updated', $sanitized_settings);
        }
        
        return $result;
    }

    public function update_service_settings($service_key, $service_settings) {
        $all_settings = $this->get_settings();
        $all_settings[$service_key] = $this->sanitize_service_settings($service_settings);
        
        return $this->update_settings($all_settings);
    }

    private function get_default_settings() {
        $defaults = array();
        $available_services = $this->get_available_services();
        
        foreach ($available_services as $service_key => $service_config) {
            $defaults[$service_key] = $this->get_default_service_settings($service_key);
        }
        
        return $defaults;
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

        return $defaults;
    }

    public function sanitize_settings($settings) {
        $sanitized = array();
        
        if (!is_array($settings)) {
            return $this->get_default_settings();
        }
        
        foreach ($settings as $service_key => $service_settings) {
            if (isset($this->services_config[$service_key])) {
                $sanitized[$service_key] = $this->sanitize_service_settings($service_settings);
            }
        }
        
        return $sanitized;
    }

    private function sanitize_service_settings($settings) {
        $sanitized = array(
            'enabled' => !empty($settings['enabled']),
            'use_template' => isset($settings['use_template']) ? (bool)$settings['use_template'] : true,
            'custom_code' => wp_kses($settings['custom_code'] ?? '', array(
                'script' => array(
                    'type' => array(),
                    'src' => array(),
                    'async' => array(),
                    'defer' => array(),
                    'id' => array(),
                    'class' => array()
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
                    'style' => array()
                )
            )),
            'position' => sanitize_text_field($settings['position'] ?? 'head'),
            'priority' => intval($settings['priority'] ?? 10),
            'device' => sanitize_text_field($settings['device'] ?? 'all'),
            'conditions' => is_array($settings['conditions'] ?? array()) ? $settings['conditions'] : array(),
            'updated_at' => current_time('mysql')
        );
        
        foreach ($this->services_config as $service_key => $service_config) {
            $field_key = $service_config['field'];
            if (isset($settings[$field_key])) {
                $sanitized[$field_key] = sanitize_text_field($settings[$field_key]);
            }
        }
        
        return $sanitized;
    }

    public function install_default_settings() {
        $existing_settings = get_option($this->option_name, array());
        $existing_services = get_option($this->services_option, array());
        
        if (empty($existing_settings)) {
            add_option($this->option_name, $this->get_default_settings());
        }
        
        if (empty($existing_services)) {
            add_option($this->services_option, array('google_analytics', 'google_tag_manager', 'facebook_pixel', 'google_ads'));
        }
    }

    public function reset_to_defaults() {
        delete_option($this->option_name);
        delete_option($this->services_option);
        $this->cached_settings = null;
        $this->cached_services = null;
        $this->install_default_settings();
        
        do_action('wptag_settings_reset');
    }

    public function export_settings() {
        $settings = $this->get_settings();
        $services = $this->get_enabled_services();
        
        $export_data = array(
            'version' => WPTAG_VERSION,
            'exported_at' => current_time('mysql'),
            'services' => $services,
            'settings' => $settings
        );
        
        return wp_json_encode($export_data, JSON_PRETTY_PRINT);
    }

    public function import_settings($json_data) {
        $data = json_decode($json_data, true);
        
        if (!is_array($data) || !isset($data['settings'])) {
            return new \WP_Error('invalid_data', 'Invalid import data format');
        }
        
        $settings_result = $this->update_settings($data['settings']);
        
        if (isset($data['services'])) {
            $services_result = $this->update_enabled_services($data['services']);
        }
        
        if ($settings_result) {
            do_action('wptag_settings_imported', $data['settings']);
            return true;
        }
        
        return new \WP_Error('import_failed', 'Failed to import settings');
    }
}