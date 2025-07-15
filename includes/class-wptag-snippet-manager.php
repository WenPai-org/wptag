<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPTag_Snippet_Manager {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wptag_snippets';
    }

    public function get_snippet($id) {
        global $wpdb;
        $snippet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if ($snippet && !empty($snippet['conditions'])) {
            $snippet['conditions'] = json_decode($snippet['conditions'], true);
        }
        
        return $snippet;
    }

    public function get_snippets($args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => null,
            'position' => null,
            'category' => null,
            'search' => '',
            'orderby' => 'priority',
            'order' => 'ASC',
            'per_page' => 20,
            'page' => 1
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['1=1'];
        $where_values = [];
        
        if ($args['status'] !== null) {
            $where[] = 'status = %d';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['position'])) {
            $where[] = 'position = %s';
            $where_values[] = $args['position'];
        }
        
        if (!empty($args['category'])) {
            $where[] = 'category = %s';
            $where_values[] = $args['category'];
        }
        
        if (!empty($args['search'])) {
            $where[] = '(name LIKE %s OR description LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $where_values[] = $args['per_page'];
        $where_values[] = $offset;
        
        $results = $wpdb->get_results($wpdb->prepare($query, $where_values), ARRAY_A);
        
        foreach ($results as &$result) {
            if (!empty($result['conditions'])) {
                $result['conditions'] = json_decode($result['conditions'], true);
            }
        }
        
        return $results;
    }

    public function get_active_snippets_by_position($position) {
        global $wpdb;
        
        $snippets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE status = 1 AND position = %s 
            ORDER BY priority ASC, id ASC",
            $position
        ), ARRAY_A);
        
        foreach ($snippets as &$snippet) {
            if (!empty($snippet['conditions'])) {
                $snippet['conditions'] = json_decode($snippet['conditions'], true);
            }
        }
        
        return $snippets;
    }

    public function create_snippet($data) {
        global $wpdb;
        
        $snippet_data = $this->prepare_snippet_data($data);
        $snippet_data['created_by'] = get_current_user_id();
        $snippet_data['created_at'] = current_time('mysql');
        
        $result = $wpdb->insert($this->table_name, $snippet_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create snippet');
        }
        
        $snippet_id = $wpdb->insert_id;
        $this->log_action('create', $snippet_id, null, $snippet_data);
        
        return $snippet_id;
    }

    public function update_snippet($id, $data) {
        global $wpdb;
        
        $old_snippet = $this->get_snippet($id);
        if (!$old_snippet) {
            return new WP_Error('not_found', 'Snippet not found');
        }
        
        $snippet_data = $this->prepare_snippet_data($data);
        $snippet_data['last_modified_by'] = get_current_user_id();
        $snippet_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table_name,
            $snippet_data,
            ['id' => $id]
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update snippet');
        }
        
        $this->log_action('update', $id, $old_snippet, $snippet_data);
        $this->clear_cache();
        
        return true;
    }

    public function delete_snippet($id) {
        global $wpdb;
        
        $old_snippet = $this->get_snippet($id);
        if (!$old_snippet) {
            return new WP_Error('not_found', 'Snippet not found');
        }
        
        $result = $wpdb->delete($this->table_name, ['id' => $id]);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete snippet');
        }
        
        $this->log_action('delete', $id, $old_snippet, null);
        $this->clear_cache();
        
        return true;
    }

    public function toggle_status($id) {
        global $wpdb;
        
        $snippet = $this->get_snippet($id);
        if (!$snippet) {
            return new WP_Error('not_found', 'Snippet not found');
        }
        
        $new_status = $snippet['status'] ? 0 : 1;
        
        $result = $wpdb->update(
            $this->table_name,
            ['status' => $new_status],
            ['id' => $id]
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update status');
        }
        
        $this->clear_cache();
        
        return $new_status;
    }

    private function prepare_snippet_data($data) {
        $prepared = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'code' => $data['code'] ?? '',
            'code_type' => sanitize_key($data['code_type'] ?? 'html'),
            'position' => sanitize_key($data['position'] ?? 'head'),
            'category' => sanitize_key($data['category'] ?? 'custom'),
            'priority' => intval($data['priority'] ?? 10),
            'status' => isset($data['status']) ? intval($data['status']) : 1,
            'device_type' => sanitize_key($data['device_type'] ?? 'all'),
            'load_method' => sanitize_key($data['load_method'] ?? 'normal')
        ];
        
        if (!empty($data['conditions']) && is_array($data['conditions'])) {
            $prepared['conditions'] = json_encode($data['conditions']);
        } else {
            $prepared['conditions'] = null;
        }
        
        return $prepared;
    }

    private function log_action($action, $object_id, $old_value = null, $new_value = null) {
        global $wpdb;
        
        $log_data = [
            'user_id' => get_current_user_id(),
            'action' => $action,
            'object_type' => 'snippet',
            'object_id' => $object_id,
            'old_value' => $old_value ? json_encode($old_value) : null,
            'new_value' => $new_value ? json_encode($new_value) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($wpdb->prefix . 'wptag_logs', $log_data);
    }

    private function clear_cache() {
        wp_cache_delete('wptag_active_snippets', 'wptag');
        wp_cache_delete('wptag_snippet_conditions', 'wptag');
    }

    public function get_categories() {
        return [
            'statistics' => __('Statistics', 'wptag'),
            'marketing' => __('Marketing', 'wptag'),
            'advertising' => __('Advertising', 'wptag'),
            'seo' => __('SEO', 'wptag'),
            'custom' => __('Custom', 'wptag')
        ];
    }

    public function get_positions() {
        return [
            'head' => __('Site Header', 'wptag'),
            'footer' => __('Site Footer', 'wptag'),
            'before_content' => __('Before Post Content', 'wptag'),
            'after_content' => __('After Post Content', 'wptag')
        ];
    }
}
