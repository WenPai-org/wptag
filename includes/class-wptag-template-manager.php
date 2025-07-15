<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPTag_Template_Manager {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wptag_templates';
    }

    public function get_template($service_type) {
        global $wpdb;
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE service_type = %s AND is_active = 1",
            $service_type
        ), ARRAY_A);
        
        if ($template && !empty($template['config_fields'])) {
            $template['config_fields'] = json_decode($template['config_fields'], true);
        }
        
        return $template;
    }

    public function get_templates($category = null) {
        global $wpdb;
        
        $query = "SELECT * FROM {$this->table_name} WHERE is_active = 1";
        $params = [];
        
        if ($category) {
            $query .= " AND service_category = %s";
            $params[] = $category;
        }
        
        $query .= " ORDER BY service_category, service_name";
        
        if (!empty($params)) {
            $templates = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        } else {
            $templates = $wpdb->get_results($query, ARRAY_A);
        }
        
        foreach ($templates as &$template) {
            if (!empty($template['config_fields'])) {
                $template['config_fields'] = json_decode($template['config_fields'], true);
            }
        }
        
        return $templates;
    }

    public function get_categories() {
        return [
            'analytics' => __('Analytics & Statistics', 'wptag'),
            'marketing' => __('Marketing & Tracking', 'wptag'),
            'seo' => __('SEO Tools', 'wptag'),
            'support' => __('Customer Support', 'wptag'),
            'other' => __('Other Services', 'wptag')
        ];
    }

    public function process_template_config($service_type, $config_data) {
        $template = $this->get_template($service_type);
        
        if (!$template) {
            return new WP_Error('template_not_found', 'Service template not found');
        }
        
        $validated_config = $this->validate_config($template['config_fields'], $config_data);
        
        if (is_wp_error($validated_config)) {
            return $validated_config;
        }
        
        $code = $this->render_template($template['code_template'], $validated_config);
        
        return [
            'code' => $code,
            'position' => $template['default_position'],
            'name' => $template['service_name'] . ' - ' . ($validated_config['measurement_id'] ?? $validated_config['pixel_id'] ?? 'Config'),
            'category' => $template['service_category']
        ];
    }

    private function validate_config($fields, $data) {
        $validated = [];
        
        foreach ($fields as $field) {
            $field_name = $field['name'];
            $field_value = $data[$field_name] ?? '';
            
            if (!empty($field['required']) && empty($field_value)) {
                return new WP_Error('missing_field', sprintf('Field %s is required', $field['label']));
            }
            
            if (!empty($field_value)) {
                switch ($field['type']) {
                    case 'text':
                        $validated[$field_name] = sanitize_text_field($field_value);
                        break;
                    
                    case 'textarea':
                        $validated[$field_name] = sanitize_textarea_field($field_value);
                        break;
                    
                    case 'url':
                        $validated[$field_name] = esc_url_raw($field_value);
                        break;
                    
                    case 'number':
                        $validated[$field_name] = intval($field_value);
                        break;
                    
                    case 'select':
                        if (isset($field['options'][$field_value])) {
                            $validated[$field_name] = $field_value;
                        }
                        break;
                    
                    default:
                        $validated[$field_name] = sanitize_text_field($field_value);
                }
            }
        }
        
        return $validated;
    }

    private function render_template($template, $variables) {
        $code = $template;
        
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $code = str_replace($placeholder, $value, $code);
        }
        
        $code = preg_replace('/\{\{[^}]+\}\}/', '', $code);
        
        return trim($code);
    }

    public function create_template($data) {
        global $wpdb;
        
        $template_data = [
            'service_type' => sanitize_key($data['service_type']),
            'service_name' => sanitize_text_field($data['service_name']),
            'service_category' => sanitize_key($data['service_category']),
            'config_fields' => json_encode($data['config_fields']),
            'code_template' => $data['code_template'],
            'default_position' => sanitize_key($data['default_position']),
            'is_active' => 1,
            'version' => sanitize_text_field($data['version'] ?? '1.0')
        ];
        
        $result = $wpdb->insert($this->table_name, $template_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create template');
        }
        
        return $wpdb->insert_id;
    }

    public function update_template($service_type, $data) {
        global $wpdb;
        
        $template_data = [];
        
        if (isset($data['service_name'])) {
            $template_data['service_name'] = sanitize_text_field($data['service_name']);
        }
        
        if (isset($data['service_category'])) {
            $template_data['service_category'] = sanitize_key($data['service_category']);
        }
        
        if (isset($data['config_fields'])) {
            $template_data['config_fields'] = json_encode($data['config_fields']);
        }
        
        if (isset($data['code_template'])) {
            $template_data['code_template'] = $data['code_template'];
        }
        
        if (isset($data['default_position'])) {
            $template_data['default_position'] = sanitize_key($data['default_position']);
        }
        
        if (isset($data['version'])) {
            $template_data['version'] = sanitize_text_field($data['version']);
        }
        
        $template_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table_name,
            $template_data,
            ['service_type' => $service_type]
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update template');
        }
        
        return true;
    }

    public function delete_template($service_type) {
        global $wpdb;
        
        $result = $wpdb->delete($this->table_name, ['service_type' => $service_type]);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete template');
        }
        
        return true;
    }

    public function export_templates($service_types = []) {
        global $wpdb;
        
        if (empty($service_types)) {
            $templates = $wpdb->get_results("SELECT * FROM {$this->table_name}", ARRAY_A);
        } else {
            $placeholders = array_fill(0, count($service_types), '%s');
            $query = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE service_type IN (" . implode(',', $placeholders) . ")",
                $service_types
            );
            $templates = $wpdb->get_results($query, ARRAY_A);
        }
        
        return json_encode($templates, JSON_PRETTY_PRINT);
    }

    public function import_templates($json_data) {
        $templates = json_decode($json_data, true);
        
        if (!is_array($templates)) {
            return new WP_Error('invalid_format', 'Invalid template format');
        }
        
        $imported = 0;
        
        foreach ($templates as $template) {
            if (!isset($template['service_type']) || !isset($template['code_template'])) {
                continue;
            }
            
            $existing = $this->get_template($template['service_type']);
            
            if ($existing) {
                $this->update_template($template['service_type'], $template);
            } else {
                $this->create_template($template);
            }
            
            $imported++;
        }
        
        return $imported;
    }
}
