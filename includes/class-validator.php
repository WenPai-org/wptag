<?php

namespace WPTag;

if (!defined('ABSPATH')) {
    exit;
}

class Validator {
    private $config;
    private $errors = array();

    public function __construct($config = null) {
        $this->config = $config ?: new Config();
    }

    public function validate_service_code($service_key, $settings) {
        $this->clear_errors();
        
        if (empty($service_key)) {
            $this->add_error('empty_service_key', 'Service key cannot be empty');
            return false;
        }
        
        $service_config = $this->config->get_service_config($service_key);
        if (!$service_config) {
            $this->add_error('service_not_found', 'Service configuration not found for: ' . $service_key);
            return false;
        }

        if (!is_array($settings)) {
            $this->add_error('invalid_settings_format', 'Settings must be an array');
            return false;
        }

        if (empty($settings['enabled'])) {
            return true;
        }

        if (!empty($settings['use_template'])) {
            return $this->validate_template_code($service_key, $settings, $service_config);
        } else {
            return $this->validate_custom_code($settings['custom_code'] ?? '');
        }
    }

    private function validate_template_code($service_key, $settings, $service_config) {
        $field_key = $service_config['field'];
        $id_value = trim($settings[$field_key] ?? '');

        if (empty($id_value)) {
            $this->add_error('empty_id', sprintf('The %s field cannot be empty', ucfirst(str_replace('_', ' ', $field_key))));
            return false;
        }

        if (!$this->validate_id_format($service_key, $id_value, $service_config)) {
            return false;
        }

        if (!$this->validate_id_accessibility($service_key, $id_value)) {
            return false;
        }

        return true;
    }

    private function validate_id_format($service_key, $id_value, $service_config) {
        $pattern = $service_config['validation_pattern'] ?? null;
        
        if (!$pattern) {
            return true;
        }

        if (!preg_match($pattern, $id_value)) {
            $this->add_error(
                'invalid_id_format', 
                sprintf(
                    'Invalid %s format. Expected format: %s', 
                    $service_config['name'],
                    $service_config['placeholder']
                )
            );
            return false;
        }

        return $this->validate_service_specific_rules($service_key, $id_value);
    }

    private function validate_service_specific_rules($service_key, $id_value) {
        switch ($service_key) {
            case 'google_analytics':
                return $this->validate_google_analytics_id($id_value);
            case 'google_tag_manager':
                return $this->validate_gtm_id($id_value);
            case 'facebook_pixel':
                return $this->validate_facebook_pixel_id($id_value);
            case 'google_ads':
                return $this->validate_google_ads_id($id_value);
            case 'matomo':
                return $this->validate_matomo_id($id_value);
            default:
                return true;
        }
    }

    private function validate_google_analytics_id($id_value) {
        if (strpos($id_value, 'G-') === 0) {
            if (!preg_match('/^G-[A-Z0-9]{10}$/', $id_value)) {
                $this->add_error('invalid_ga4_format', 'Invalid Google Analytics 4 ID format. Should be G- followed by 10 alphanumeric characters.');
                return false;
            }
        } elseif (strpos($id_value, 'UA-') === 0) {
            if (!preg_match('/^UA-[0-9]+-[0-9]+$/', $id_value)) {
                $this->add_error('invalid_ua_format', 'Invalid Universal Analytics ID format. Should be UA-XXXXXX-X.');
                return false;
            }
        } else {
            $this->add_error('invalid_ga_format', 'Google Analytics ID must start with G- (GA4) or UA- (Universal Analytics).');
            return false;
        }

        return true;
    }

    private function validate_gtm_id($id_value) {
        if (!preg_match('/^GTM-[A-Z0-9]{7}$/', $id_value)) {
            $this->add_error('invalid_gtm_format', 'Invalid Google Tag Manager ID format. Should be GTM- followed by 7 alphanumeric characters.');
            return false;
        }
        return true;
    }

    private function validate_facebook_pixel_id($id_value) {
        if (!preg_match('/^[0-9]{15}$/', $id_value)) {
            $this->add_error('invalid_fb_pixel_format', 'Invalid Facebook Pixel ID format. Should be 15 digits.');
            return false;
        }
        return true;
    }

    private function validate_google_ads_id($id_value) {
        if (!preg_match('/^AW-[0-9]{10}$/', $id_value)) {
            $this->add_error('invalid_gads_format', 'Invalid Google Ads ID format. Should be AW- followed by 10 digits.');
            return false;
        }
        return true;
    }

    private function validate_matomo_id($id_value) {
        if (!preg_match('/^[0-9]+$/', $id_value)) {
            $this->add_error('invalid_matomo_format', 'Invalid Matomo Site ID format. Should be numeric.');
            return false;
        }
        
        $site_id = intval($id_value);
        if ($site_id < 1 || $site_id > 999999) {
            $this->add_error('invalid_matomo_range', 'Matomo Site ID should be between 1 and 999999.');
            return false;
        }
        
        return true;
    }

    private function validate_id_accessibility($service_key, $id_value) {
        $blocked_ids = $this->get_blocked_ids($service_key);
        
        if (in_array($id_value, $blocked_ids)) {
            $this->add_error('blocked_id', 'This ID is not allowed for security reasons.');
            return false;
        }

        return true;
    }

    private function get_blocked_ids($service_key) {
        $blocked_ids = array();
        
        switch ($service_key) {
            case 'google_analytics':
                $blocked_ids = array('G-XXXXXXXXXX', 'UA-XXXXXXX-X', 'G-TEST123456', 'UA-123456-1');
                break;
            case 'google_tag_manager':
                $blocked_ids = array('GTM-XXXXXXX', 'GTM-TEST123');
                break;
            case 'facebook_pixel':
                $blocked_ids = array('123456789012345', '000000000000000');
                break;
        }

        return apply_filters('wptag_blocked_ids', $blocked_ids, $service_key);
    }

    private function validate_custom_code($custom_code) {
        $custom_code = trim($custom_code);
        
        if (empty($custom_code)) {
            $this->add_error('empty_custom_code', 'Custom code cannot be empty when enabled');
            return false;
        }

        if (strlen($custom_code) > 50000) {
            $this->add_error('code_too_long', 'Custom code is too long (maximum 50,000 characters)');
            return false;
        }

        if (strlen($custom_code) < 10) {
            $this->add_error('code_too_short', 'Custom code seems too short to be valid tracking code');
            return false;
        }

        if (!$this->validate_script_structure($custom_code)) {
            return false;
        }

        if (!$this->validate_code_security($custom_code)) {
            return false;
        }

        if (!$this->validate_code_syntax($custom_code)) {
            return false;
        }

        return true;
    }

    private function validate_script_structure($custom_code) {
        $has_script_tag = (strpos($custom_code, '<script') !== false);
        $has_noscript_tag = (strpos($custom_code, '<noscript') !== false);
        
        if ($has_script_tag) {
            $script_open_count = substr_count($custom_code, '<script');
            $script_close_count = substr_count($custom_code, '</script>');
            
            if ($script_open_count !== $script_close_count) {
                $this->add_error('mismatched_script_tags', 'Mismatched script tags detected. Each <script> must have a closing </script>');
                return false;
            }

            if (preg_match('/<script[^>]*>.*?<script/is', $custom_code)) {
                $this->add_error('nested_script_tags', 'Nested script tags are not allowed');
                return false;
            }
        }

        if ($has_noscript_tag) {
            $noscript_open_count = substr_count($custom_code, '<noscript');
            $noscript_close_count = substr_count($custom_code, '</noscript>');
            
            if ($noscript_open_count !== $noscript_close_count) {
                $this->add_error('mismatched_noscript_tags', 'Mismatched noscript tags detected. Each <noscript> must have a closing </noscript>');
                return false;
            }
        }

        return true;
    }

    private function validate_code_security($custom_code) {
        $dangerous_patterns = array(
            '/\beval\s*\(/i' => 'eval() function is not allowed for security reasons',
            '/\bFunction\s*\(/i' => 'Function() constructor is not allowed for security reasons',
            '/\bsetTimeout\s*\(\s*["\'][^"\']*["\']/i' => 'setTimeout with string argument is not allowed for security reasons',
            '/\bsetInterval\s*\(\s*["\'][^"\']*["\']/i' => 'setInterval with string argument is not allowed for security reasons',
            '/\bdocument\.write\s*\(/i' => 'document.write() is discouraged and may not work properly',
            '/\bwindow\.location\s*=\s*["\'][^"\']*["\']/i' => 'Redirecting window.location is not allowed',
            '/\bwindow\.open\s*\(/i' => 'window.open() is not allowed for security reasons',
            '/\balert\s*\(/i' => 'alert() is not allowed in tracking codes',
            '/\bconfirm\s*\(/i' => 'confirm() is not allowed in tracking codes',
            '/\bprompt\s*\(/i' => 'prompt() is not allowed in tracking codes',
            '/javascript\s*:/i' => 'javascript: protocol is not allowed',
            '/\<\s*iframe[^>]*src\s*=\s*["\']?javascript:/i' => 'javascript: protocol in iframe src is not allowed',
            '/\<\s*object[^>]*data\s*=\s*["\']?javascript:/i' => 'javascript: protocol in object data is not allowed',
            '/\<\s*embed[^>]*src\s*=\s*["\']?javascript:/i' => 'javascript: protocol in embed src is not allowed',
            '/\bExecScript\s*\(/i' => 'ExecScript is not allowed for security reasons',
            '/\bexecCommand\s*\(/i' => 'execCommand is not allowed for security reasons'
        );

        foreach ($dangerous_patterns as $pattern => $message) {
            if (preg_match($pattern, $custom_code)) {
                $this->add_error('security_violation', $message);
                return false;
            }
        }

        if (!$this->validate_external_domains($custom_code)) {
            return false;
        }

        return true;
    }

    private function validate_external_domains($custom_code) {
        if (preg_match_all('/https?:\/\/([^\/\s"\'<>]+)/i', $custom_code, $matches)) {
            $domains = array_unique($matches[1]);
            $allowed_domains = $this->get_allowed_domains();
            $suspicious_domains = $this->get_suspicious_domains();
            
            foreach ($domains as $domain) {
                $domain = strtolower(trim($domain));
                
                if (in_array($domain, $suspicious_domains)) {
                    $this->add_error('suspicious_domain', 'Suspicious domain detected: ' . $domain);
                    return false;
                }
                
                if (!empty($allowed_domains) && !$this->is_domain_allowed($domain, $allowed_domains)) {
                    $this->add_error('unauthorized_domain', 'Domain not in allowed list: ' . $domain);
                    return false;
                }
            }
        }

        return true;
    }

    private function get_allowed_domains() {
        $default_allowed = array(
            'googletagmanager.com',
            'google-analytics.com',
            'googleadservices.com',
            'facebook.com',
            'facebook.net',
            'connect.facebook.net',
            'hotjar.com',
            'clarity.ms',
            'tiktok.com',
            'linkedin.com',
            'twitter.com',
            'pinterest.com',
            'snapchat.com',
            'googleoptimize.com',
            'crazyegg.com',
            'mixpanel.com',
            'amplitude.com',
            'ads-twitter.com',
            'pinimg.com',
            'licdn.com',
            'sc-static.net',
            'snap.licdn.com',
            'cdn.amplitude.com',
            'cdn4.mxpnl.com',
            'script.crazyegg.com'
        );

        return apply_filters('wptag_allowed_domains', $default_allowed);
    }

    private function get_suspicious_domains() {
        $suspicious = array(
            'bit.ly',
            'tinyurl.com',
            'goo.gl',
            't.co',
            'ow.ly',
            'malware.com',
            'virus.com',
            'phishing.com',
            'suspicious.com',
            'malicious.com',
            'hack.com',
            'exploit.com'
        );

        return apply_filters('wptag_suspicious_domains', $suspicious);
    }

    private function is_domain_allowed($domain, $allowed_domains) {
        foreach ($allowed_domains as $allowed) {
            if ($domain === $allowed || strpos($domain, '.' . $allowed) !== false) {
                return true;
            }
        }
        return false;
    }

    private function validate_code_syntax($custom_code) {
        if (preg_match('/<script[^>]*>/i', $custom_code)) {
            $js_content = preg_replace('/<script[^>]*>(.*?)<\/script>/is', '$1', $custom_code);
            
            if (!empty($js_content) && !$this->is_valid_javascript_syntax($js_content)) {
                $this->add_error('invalid_js_syntax', 'JavaScript syntax appears to be invalid');
                return false;
            }
        }

        if (!$this->validate_html_structure($custom_code)) {
            return false;
        }

        return true;
    }

    private function is_valid_javascript_syntax($js_code) {
        $js_code = trim($js_code);
        
        if (empty($js_code)) {
            return true;
        }

        $basic_checks = array(
            'balanced_parentheses' => $this->check_balanced_chars($js_code, '(', ')'),
            'balanced_brackets' => $this->check_balanced_chars($js_code, '[', ']'),
            'balanced_braces' => $this->check_balanced_chars($js_code, '{', '}'),
            'no_unclosed_strings' => $this->check_unclosed_strings($js_code)
        );

        foreach ($basic_checks as $check => $result) {
            if (!$result) {
                return false;
            }
        }

        return true;
    }

    private function check_balanced_chars($code, $open, $close) {
        $open_count = substr_count($code, $open);
        $close_count = substr_count($code, $close);
        return $open_count === $close_count;
    }

    private function check_unclosed_strings($code) {
        $single_quotes = substr_count($code, "'");
        $double_quotes = substr_count($code, '"');
        
        return ($single_quotes % 2 === 0) && ($double_quotes % 2 === 0);
    }

    private function validate_html_structure($custom_code) {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        
        $result = $doc->loadHTML('<div>' . $custom_code . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        if (!$result || !empty($errors)) {
            $severe_errors = array_filter($errors, function($error) {
                return $error->level === LIBXML_ERR_ERROR || $error->level === LIBXML_ERR_FATAL;
            });
            
            if (!empty($severe_errors)) {
                $this->add_error('invalid_html_structure', 'HTML structure contains errors');
                return false;
            }
        }

        return true;
    }

    public function validate_settings($settings) {
        $this->clear_errors();
        
        if (!is_array($settings)) {
            $this->add_error('invalid_settings_format', 'Settings must be an array');
            return false;
        }

        $valid = true;
        
        foreach ($settings as $service_key => $service_settings) {
            if (!$this->validate_service_settings($service_key, $service_settings)) {
                $valid = false;
            }
        }

        return $valid;
    }

    private function validate_service_settings($service_key, $service_settings) {
        if (!is_array($service_settings)) {
            $this->add_error($service_key . '_invalid_format', 'Service settings must be an array for ' . $service_key);
            return false;
        }

        $service_config = $this->config->get_service_config($service_key);
        if (!$service_config) {
            $this->add_error($service_key . '_not_found', 'Service configuration not found for ' . $service_key);
            return false;
        }

        $valid = true;

        $checks = array(
            'enabled' => array($service_settings['enabled'] ?? false, 'boolean'),
            'use_template' => array($service_settings['use_template'] ?? true, 'boolean'),
            'position' => array($service_settings['position'] ?? 'head', 'position'),
            'priority' => array($service_settings['priority'] ?? 10, 'priority'),
            'device' => array($service_settings['device'] ?? 'all', 'device')
        );

        foreach ($checks as $field => $check_data) {
            list($value, $type) = $check_data;
            
            if (!$this->validate_field_type($value, $type)) {
                $this->add_error(
                    $service_key . '_' . $field . '_invalid', 
                    sprintf('Invalid %s setting for %s', $field, $service_key)
                );
                $valid = false;
            }
        }

        return $valid;
    }

    private function validate_field_type($value, $type) {
        switch ($type) {
            case 'boolean':
                return is_bool($value) || in_array($value, array('1', '0', 1, 0), true);
            case 'position':
                return in_array($value, array('head', 'body', 'footer'));
            case 'priority':
                $priority = intval($value);
                return $priority >= 1 && $priority <= 100;
            case 'device':
                return in_array($value, array('all', 'desktop', 'mobile'));
            default:
                return true;
        }
    }

    public function validate_import_data($json_data) {
        $this->clear_errors();
        
        if (empty($json_data)) {
            $this->add_error('empty_import_data', 'Import data cannot be empty');
            return false;
        }
        
        $data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->add_error('invalid_json', 'Invalid JSON format: ' . json_last_error_msg());
            return false;
        }

        if (!is_array($data)) {
            $this->add_error('invalid_data_type', 'Import data must be an object');
            return false;
        }

        if (!isset($data['settings'])) {
            $this->add_error('missing_settings', 'Import data missing settings section');
            return false;
        }

        if (!is_array($data['settings'])) {
            $this->add_error('invalid_settings_format', 'Settings must be an object');
            return false;
        }

        if (isset($data['version'])) {
            if (!$this->validate_version_compatibility($data['version'])) {
                return false;
            }
        }

        if (isset($data['services'])) {
            if (!$this->validate_services_list($data['services'])) {
                return false;
            }
        }

        return $this->validate_imported_settings($data['settings']);
    }

    private function validate_version_compatibility($version) {
        if (!is_string($version) || empty($version)) {
            $this->add_error('invalid_version_format', 'Version must be a non-empty string');
            return false;
        }

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            $this->add_error('invalid_version_format', 'Version must be in format X.Y.Z');
            return false;
        }
        
        $current_version = WPTAG_VERSION;
        
        if (version_compare($version, $current_version, '>')) {
            $this->add_error(
                'version_incompatible', 
                sprintf(
                    'Import data is from a newer version (%s) and may not be compatible with current version (%s)',
                    $version,
                    $current_version
                )
            );
            return false;
        }

        $min_compatible_version = '1.0.0';
        if (version_compare($version, $min_compatible_version, '<')) {
            $this->add_error(
                'version_too_old',
                sprintf(
                    'Import data is from an incompatible version (%s). Minimum supported version is %s',
                    $version,
                    $min_compatible_version
                )
            );
            return false;
        }

        return true;
    }

    private function validate_services_list($services) {
        if (!is_array($services)) {
            $this->add_error('invalid_services_format', 'Services list must be an array');
            return false;
        }

        if (empty($services)) {
            $this->add_error('empty_services_list', 'Services list cannot be empty');
            return false;
        }

        $all_services = $this->config->get_all_services();
        
        foreach ($services as $service_key) {
            if (!is_string($service_key) || empty($service_key)) {
                $this->add_error('invalid_service_key', 'Service key must be a non-empty string');
                return false;
            }
            
            if (!isset($all_services[$service_key])) {
                $this->add_error('unknown_service', 'Unknown service: ' . $service_key);
                return false;
            }
        }

        return true;
    }

    private function validate_imported_settings($settings) {
        $imported_services = array_keys($settings);
        $max_services = 50;
        
        if (count($imported_services) > $max_services) {
            $this->add_error('too_many_services', sprintf('Cannot import more than %d services', $max_services));
            return false;
        }

        return $this->validate_settings($settings);
    }

    private function add_error($code, $message) {
        $this->errors[] = array(
            'code' => $code,
            'message' => $message,
            'timestamp' => current_time('mysql')
        );
    }

    public function get_errors() {
        return $this->errors;
    }

    public function get_error_messages() {
        return array_column($this->errors, 'message');
    }

    public function get_error_codes() {
        return array_column($this->errors, 'code');
    }

    public function has_errors() {
        return !empty($this->errors);
    }

    public function get_last_error() {
        if (empty($this->errors)) {
            return null;
        }
        return end($this->errors);
    }

    public function clear_errors() {
        $this->errors = array();
    }

    public function get_error_count() {
        return count($this->errors);
    }

    public function get_errors_by_code($code) {
        return array_filter($this->errors, function($error) use ($code) {
            return $error['code'] === $code;
        });
    }

    public function has_error_code($code) {
        return !empty($this->get_errors_by_code($code));
    }

    public function validate_tracking_id($service_key, $id_value) {
        $service_config = $this->config->get_service_config($service_key);
        if (!$service_config) {
            return false;
        }

        $pattern = $service_config['validation_pattern'] ?? null;
        if (!$pattern) {
            return true;
        }

        return preg_match($pattern, $id_value) === 1;
    }

    public function sanitize_tracking_id($service_key, $id_value) {
        $id_value = trim($id_value);
        $id_value = sanitize_text_field($id_value);
        $id_value = wp_kses($id_value, array());
        
        return $id_value;
    }

    public function get_validation_pattern($service_key) {
        $service_config = $this->config->get_service_config($service_key);
        return $service_config['validation_pattern'] ?? null;
    }

    public function get_validation_help($service_key) {
        $service_config = $this->config->get_service_config($service_key);
        if (!$service_config) {
            return '';
        }

        $help_texts = array(
            'google_analytics' => 'Enter your Google Analytics tracking ID. GA4 IDs start with "G-" followed by 10 characters. Universal Analytics IDs start with "UA-" followed by numbers.',
            'google_tag_manager' => 'Enter your Google Tag Manager container ID. It starts with "GTM-" followed by 7 characters.',
            'facebook_pixel' => 'Enter your Facebook Pixel ID. It should be a 15-digit number.',
            'google_ads' => 'Enter your Google Ads conversion ID. It starts with "AW-" followed by 10 digits.',
            'microsoft_clarity' => 'Enter your Microsoft Clarity project ID. It should be 10 alphanumeric characters.',
            'hotjar' => 'Enter your Hotjar site ID. It should be a 7-digit number.',
            'matomo' => 'Enter your Matomo site ID. It should be a positive number.'
        );

        return $help_texts[$service_key] ?? sprintf('Enter your %s ID in the correct format.', $service_config['name']);
    }
}