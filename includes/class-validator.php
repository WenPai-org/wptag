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
        $this->errors = array();
        
        $service_config = $this->config->get_service_config($service_key);
        if (!$service_config) {
            $this->add_error('service_not_found', 'Service configuration not found');
            return false;
        }

        if (!$settings['enabled']) {
            return true;
        }

        if ($settings['use_template']) {
            return $this->validate_template_code($service_key, $settings, $service_config);
        } else {
            return $this->validate_custom_code($settings['custom_code']);
        }
    }

    private function validate_template_code($service_key, $settings, $service_config) {
        $field_key = $service_config['field'];
        $id_value = $settings[$field_key] ?? '';

        if (empty($id_value)) {
            $this->add_error('empty_id', 'ID field cannot be empty');
            return false;
        }

        if (!$this->validate_id_format($service_key, $id_value, $service_config)) {
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
            $this->add_error('invalid_id_format', sprintf('Invalid ID format for %s', $service_config['name']));
            return false;
        }

        if ($service_key === 'google_analytics') {
            return $this->validate_google_analytics_id($id_value);
        }

        return true;
    }

    private function validate_google_analytics_id($id_value) {
        if (strpos($id_value, 'G-') === 0) {
            if (!preg_match('/^G-[A-Z0-9]{10}$/', $id_value)) {
                $this->add_error('invalid_ga4_format', 'Invalid Google Analytics 4 ID format');
                return false;
            }
        } elseif (strpos($id_value, 'UA-') === 0) {
            if (!preg_match('/^UA-[0-9]+-[0-9]+$/', $id_value)) {
                $this->add_error('invalid_ua_format', 'Invalid Universal Analytics ID format');
                return false;
            }
        } else {
            $this->add_error('invalid_ga_format', 'Google Analytics ID must start with G- or UA-');
            return false;
        }

        return true;
    }

    private function validate_custom_code($custom_code) {
        if (empty($custom_code)) {
            $this->add_error('empty_custom_code', 'Custom code cannot be empty');
            return false;
        }

        if (strlen($custom_code) > 50000) {
            $this->add_error('code_too_long', 'Custom code is too long (max 50,000 characters)');
            return false;
        }

        if (!$this->validate_script_structure($custom_code)) {
            return false;
        }

        if (!$this->validate_code_security($custom_code)) {
            return false;
        }

        return true;
    }

    private function validate_script_structure($custom_code) {
        $has_script_tag = strpos($custom_code, '<script') !== false;
        $has_noscript_tag = strpos($custom_code, '<noscript') !== false;
        
        if ($has_script_tag) {
            $script_open_count = substr_count($custom_code, '<script');
            $script_close_count = substr_count($custom_code, '</script>');
            
            if ($script_open_count !== $script_close_count) {
                $this->add_error('mismatched_script_tags', 'Mismatched script tags');
                return false;
            }
        }

        if ($has_noscript_tag) {
            $noscript_open_count = substr_count($custom_code, '<noscript');
            $noscript_close_count = substr_count($custom_code, '</noscript>');
            
            if ($noscript_open_count !== $noscript_close_count) {
                $this->add_error('mismatched_noscript_tags', 'Mismatched noscript tags');
                return false;
            }
        }

        return true;
    }

    private function validate_code_security($custom_code) {
        $dangerous_patterns = array(
            '/\beval\s*\(/i' => 'eval() function is not allowed',
            '/\bFunction\s*\(/i' => 'Function() constructor is not allowed',
            '/\bsetTimeout\s*\(\s*["\']/' => 'setTimeout with string argument is not allowed',
            '/\bsetInterval\s*\(\s*["\']/' => 'setInterval with string argument is not allowed',
            '/\bdocument\.write\s*\(/i' => 'document.write() is discouraged',
            '/\bwindow\.location\s*=/' => 'Redirecting window.location is not allowed',
            '/\bwindow\.open\s*\(/i' => 'window.open() is not allowed',
            '/\balert\s*\(/i' => 'alert() is not allowed',
            '/\bconfirm\s*\(/i' => 'confirm() is not allowed',
            '/\bprompt\s*\(/i' => 'prompt() is not allowed',
            '/javascript\s*:/i' => 'javascript: protocol is not allowed',
            '/\<\s*iframe[^>]*src\s*=\s*["\']?javascript:/i' => 'javascript: in iframe src is not allowed',
            '/\<\s*object[^>]*data\s*=\s*["\']?javascript:/i' => 'javascript: in object data is not allowed'
        );

        foreach ($dangerous_patterns as $pattern => $message) {
            if (preg_match($pattern, $custom_code)) {
                $this->add_error('security_violation', $message);
                return false;
            }
        }

        if (preg_match_all('/https?:\/\/([^\/\s"\']+)/i', $custom_code, $matches)) {
            $domains = $matches[1];
            $suspicious_domains = array(
                'bit.ly', 'tinyurl.com', 'goo.gl', 't.co', 'ow.ly',
                'malware.com', 'virus.com', 'phishing.com'
            );
            
            foreach ($domains as $domain) {
                if (in_array(strtolower($domain), $suspicious_domains)) {
                    $this->add_error('suspicious_domain', 'Suspicious domain detected: ' . $domain);
                    return false;
                }
            }
        }

        return true;
    }

    public function validate_settings($settings) {
        $this->errors = array();
        
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
        $service_config = $this->config->get_service_config($service_key);
        if (!$service_config) {
            $this->add_error($service_key . '_not_found', 'Service configuration not found for ' . $service_key);
            return false;
        }

        $valid = true;

        if (!$this->validate_boolean($service_settings['enabled'] ?? false)) {
            $this->add_error($service_key . '_enabled_invalid', 'Enabled setting must be boolean');
            $valid = false;
        }

        if (!$this->validate_boolean($service_settings['use_template'] ?? true)) {
            $this->add_error($service_key . '_use_template_invalid', 'Use template setting must be boolean');
            $valid = false;
        }

        if (!$this->validate_position($service_settings['position'] ?? 'head')) {
            $this->add_error($service_key . '_position_invalid', 'Invalid position setting');
            $valid = false;
        }

        if (!$this->validate_priority($service_settings['priority'] ?? 10)) {
            $this->add_error($service_key . '_priority_invalid', 'Priority must be between 1 and 100');
            $valid = false;
        }

        if (!$this->validate_device($service_settings['device'] ?? 'all')) {
            $this->add_error($service_key . '_device_invalid', 'Invalid device setting');
            $valid = false;
        }

        return $valid;
    }

    private function validate_boolean($value) {
        return is_bool($value) || $value === '1' || $value === '0' || $value === 1 || $value === 0;
    }

    private function validate_position($position) {
        $valid_positions = array('head', 'body', 'footer');
        return in_array($position, $valid_positions);
    }

    private function validate_priority($priority) {
        $priority = intval($priority);
        return $priority >= 1 && $priority <= 100;
    }

    private function validate_device($device) {
        $valid_devices = array('all', 'desktop', 'mobile');
        return in_array($device, $valid_devices);
    }

    public function validate_import_data($json_data) {
        $this->errors = array();
        
        $data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->add_error('invalid_json', 'Invalid JSON format: ' . json_last_error_msg());
            return false;
        }

        if (!is_array($data)) {
            $this->add_error('invalid_data_type', 'Import data must be an array');
            return false;
        }

        if (!isset($data['settings'])) {
            $this->add_error('missing_settings', 'Import data missing settings');
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

        return $this->validate_settings($data['settings']);
    }

    private function validate_version_compatibility($version) {
        $current_version = WPTAG_VERSION;
        
        $import_version_parts = explode('.', $version);
        $current_version_parts = explode('.', $current_version);
        
        if (count($import_version_parts) !== 3 || count($current_version_parts) !== 3) {
            $this->add_error('invalid_version_format', 'Invalid version format');
            return false;
        }

        $import_major = intval($import_version_parts[0]);
        $current_major = intval($current_version_parts[0]);
        
        if ($import_major > $current_major) {
            $this->add_error('version_incompatible', 'Import data is from a newer version and may not be compatible');
            return false;
        }

        return true;
    }

    private function validate_services_list($services) {
        if (!is_array($services)) {
            $this->add_error('invalid_services_format', 'Services list must be an array');
            return false;
        }

        $all_services = $this->config->get_all_services();
        
        foreach ($services as $service_key) {
            if (!isset($all_services[$service_key])) {
                $this->add_error('unknown_service', 'Unknown service: ' . $service_key);
                return false;
            }
        }

        return true;
    }

    private function add_error($code, $message) {
        $this->errors[] = array(
            'code' => $code,
            'message' => $message
        );
    }

    public function get_errors() {
        return $this->errors;
    }

    public function get_error_messages() {
        return array_column($this->errors, 'message');
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

    public function validate_tracking_id($service_key, $id_value) {
        $service_config = $this->config->get_service_config($service_key);
        if (!$service_config) {
            return false;
        }

        $pattern = $service_config['validation_pattern'] ?? null;
        if (!$pattern) {
            return true;
        }

        return preg_match($pattern, $id_value);
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
}