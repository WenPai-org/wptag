<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPTag_Ajax_Handler {
    private $snippet_manager;
    private $template_manager;

    public function __construct($snippet_manager, $template_manager) {
        $this->snippet_manager = $snippet_manager;
        $this->template_manager = $template_manager;
        
        $this->register_ajax_handlers();
    }

    private function register_ajax_handlers() {
        $actions = [
            'wptag_toggle_snippet',
            'wptag_delete_snippet',
            'wptag_search_snippets',
            'wptag_validate_code',
            'wptag_preview_snippet',
            'wptag_get_template',
            'wptag_process_template',
            'wptag_export_snippets',
            'wptag_import_snippets',
            'wptag_clear_cache'
        ];
        
        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, [$this, 'handle_' . str_replace('wptag_', '', $action)]);
        }
    }

    public function handle_toggle_snippet() {
        $this->verify_ajax_request();
        
        $snippet_id = intval($_POST['snippet_id'] ?? 0);
        
        if (!$snippet_id) {
            wp_send_json_error(['message' => __('Invalid snippet ID', 'wptag')]);
        }
        
        $result = $this->snippet_manager->toggle_status($snippet_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success([
            'status' => $result,
            'message' => $result ? __('Snippet enabled', 'wptag') : __('Snippet disabled', 'wptag')
        ]);
    }

    public function handle_delete_snippet() {
        $this->verify_ajax_request();
        
        $snippet_id = intval($_POST['snippet_id'] ?? 0);
        
        if (!$snippet_id) {
            wp_send_json_error(['message' => __('Invalid snippet ID', 'wptag')]);
        }
        
        $result = $this->snippet_manager->delete_snippet($snippet_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => __('Snippet deleted successfully', 'wptag')]);
    }

    public function handle_search_snippets() {
        $this->verify_ajax_request();
        
        $args = [
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'category' => sanitize_key($_POST['category'] ?? ''),
            'position' => sanitize_key($_POST['position'] ?? ''),
            'status' => isset($_POST['status']) ? intval($_POST['status']) : null,
            'per_page' => intval($_POST['per_page'] ?? 20),
            'page' => intval($_POST['page'] ?? 1)
        ];
        
        $snippets = $this->snippet_manager->get_snippets($args);
        
        wp_send_json_success(['snippets' => $snippets]);
    }

    public function handle_validate_code() {
        $this->verify_ajax_request();
        
        $code = $_POST['code'] ?? '';
        $code_type = sanitize_key($_POST['code_type'] ?? 'html');
        
        $errors = [];
        
        switch ($code_type) {
            case 'javascript':
                $errors = $this->validate_javascript($code);
                break;
            
            case 'css':
                $errors = $this->validate_css($code);
                break;
            
            case 'html':
                $errors = $this->validate_html($code);
                break;
        }
        
        if (empty($errors)) {
            wp_send_json_success(['message' => __('Code is valid', 'wptag')]);
        } else {
            wp_send_json_error(['errors' => $errors]);
        }
    }

    public function handle_preview_snippet() {
        $this->verify_ajax_request();
        
        $snippet_id = intval($_POST['snippet_id'] ?? 0);
        
        if (!$snippet_id) {
            wp_send_json_error(['message' => __('Invalid snippet ID', 'wptag')]);
        }
        
        $snippet = $this->snippet_manager->get_snippet($snippet_id);
        
        if (!$snippet) {
            wp_send_json_error(['message' => __('Snippet not found', 'wptag')]);
        }
        
        $preview_url = add_query_arg([
            'wptag_preview' => 1,
            'snippet_id' => $snippet_id
        ], home_url());
        
        wp_send_json_success(['preview_url' => $preview_url]);
    }

    public function handle_get_template() {
        $this->verify_ajax_request();
        
        $service_type = sanitize_key($_POST['service_type'] ?? '');
        
        if (!$service_type) {
            wp_send_json_error(['message' => __('Invalid service type', 'wptag')]);
        }
        
        $template = $this->template_manager->get_template($service_type);
        
        if (!$template) {
            wp_send_json_error(['message' => __('Template not found', 'wptag')]);
        }
        
        wp_send_json_success(['template' => $template]);
    }

    public function handle_process_template() {
        $this->verify_ajax_request();
        
        $service_type = sanitize_key($_POST['service_type'] ?? '');
        $config_data = $_POST['config'] ?? [];
        
        if (!$service_type) {
            wp_send_json_error(['message' => __('Invalid service type', 'wptag')]);
        }
        
        $result = $this->template_manager->process_template_config($service_type, $config_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        $snippet_data = array_merge($result, [
            'description' => sprintf(__('Generated from %s template', 'wptag'), $result['name']),
            'status' => 1
        ]);
        
        $snippet_id = $this->snippet_manager->create_snippet($snippet_data);
        
        if (is_wp_error($snippet_id)) {
            wp_send_json_error(['message' => $snippet_id->get_error_message()]);
        }
        
        wp_send_json_success([
            'snippet_id' => $snippet_id,
            'message' => __('Snippet created successfully from template', 'wptag')
        ]);
    }

    public function handle_export_snippets() {
        $this->verify_ajax_request();
        
        $snippet_ids = array_map('intval', $_POST['snippet_ids'] ?? []);
        
        $export_data = [
            'version' => WPTAG_VERSION,
            'exported_at' => current_time('mysql'),
            'snippets' => []
        ];
        
        foreach ($snippet_ids as $id) {
            $snippet = $this->snippet_manager->get_snippet($id);
            if ($snippet) {
                unset($snippet['id'], $snippet['created_by'], $snippet['last_modified_by']);
                $export_data['snippets'][] = $snippet;
            }
        }
        
        wp_send_json_success([
            'filename' => 'wptag-export-' . date('Y-m-d') . '.json',
            'data' => json_encode($export_data, JSON_PRETTY_PRINT)
        ]);
    }

    public function handle_import_snippets() {
        $this->verify_ajax_request();
        
        $import_data = json_decode(stripslashes($_POST['import_data'] ?? ''), true);
        
        if (!$import_data || !isset($import_data['snippets'])) {
            wp_send_json_error(['message' => __('Invalid import data', 'wptag')]);
        }
        
        $imported = 0;
        $errors = [];
        
        foreach ($import_data['snippets'] as $snippet) {
            $result = $this->snippet_manager->create_snippet($snippet);
            
            if (is_wp_error($result)) {
                $errors[] = sprintf(
                    __('Failed to import "%s": %s', 'wptag'),
                    $snippet['name'],
                    $result->get_error_message()
                );
            } else {
                $imported++;
            }
        }
        
        if ($imported > 0) {
            $message = sprintf(
                _n('%d snippet imported successfully', '%d snippets imported successfully', $imported, 'wptag'),
                $imported
            );
            
            if (!empty($errors)) {
                $message .= ' ' . __('Some snippets failed to import.', 'wptag');
            }
            
            wp_send_json_success([
                'message' => $message,
                'imported' => $imported,
                'errors' => $errors
            ]);
        } else {
            wp_send_json_error([
                'message' => __('No snippets were imported', 'wptag'),
                'errors' => $errors
            ]);
        }
    }

    public function handle_clear_cache() {
        $this->verify_ajax_request();
        
        $cache_manager = new WPTag_Cache_Manager();
        $cache_manager->flush();
        
        wp_send_json_success(['message' => __('Cache cleared successfully', 'wptag')]);
    }

    private function verify_ajax_request() {
        if (!check_ajax_referer('wptag_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'wptag')]);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'wptag')]);
        }
    }

    private function validate_javascript($code) {
        $errors = [];
        
        if (preg_match('/\bdocument\.write\b/i', $code)) {
            $errors[] = __('document.write is not recommended', 'wptag');
        }
        
        if (preg_match('/\beval\s*\(/i', $code)) {
            $errors[] = __('eval() is potentially dangerous', 'wptag');
        }
        
        return $errors;
    }

    private function validate_css($code) {
        $errors = [];
        
        if (preg_match('/@import\s+url/i', $code)) {
            $errors[] = __('@import may affect performance', 'wptag');
        }
        
        return $errors;
    }

    private function validate_html($code) {
        $errors = [];
        
        if (preg_match('/<script[^>]*src=["\'](?!https?:\/\/)/i', $code)) {
            $errors[] = __('Use absolute URLs for external scripts', 'wptag');
        }
        
        return $errors;
    }
}
