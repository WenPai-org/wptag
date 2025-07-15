<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPTag_Cache_Manager {
    private $cache_group = 'wptag';
    private $cache_enabled;
    private $ttl;

    public function __construct() {
        $this->cache_enabled = !defined('WPTAG_DISABLE_CACHE') || !WPTAG_DISABLE_CACHE;
        $this->ttl = defined('WPTAG_CACHE_TTL') ? WPTAG_CACHE_TTL : 3600;
    }

    public function get($key) {
        if (!$this->cache_enabled) {
            return false;
        }

        return wp_cache_get($key, $this->cache_group);
    }

    public function set($key, $value, $ttl = null) {
        if (!$this->cache_enabled) {
            return false;
        }

        $ttl = $ttl ?? $this->ttl;
        return wp_cache_set($key, $value, $this->cache_group, $ttl);
    }

    public function delete($key) {
        return wp_cache_delete($key, $this->cache_group);
    }

    public function flush() {
        wp_cache_flush();
    }

    public function clear_snippet_cache($snippet_id = null) {
        if ($snippet_id) {
            $this->delete('snippet_' . $snippet_id);
        }

        $this->delete('active_snippets');
        $this->clear_output_cache();
    }

    public function clear_output_cache() {
        $positions = ['head', 'footer', 'before_content', 'after_content'];
        
        foreach ($positions as $position) {
            $this->delete_by_prefix('output_' . $position);
        }
    }

    public function clear_condition_cache() {
        $this->delete_by_prefix('condition_');
    }

    private function delete_by_prefix($prefix) {
        global $wp_object_cache;

        if (method_exists($wp_object_cache, 'delete_by_group')) {
            $wp_object_cache->delete_by_group($this->cache_group);
        } else {
            $this->delete($prefix);
        }
    }

    public function warm_cache() {
        if (!$this->cache_enabled) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wptag_snippets';
        
        $active_snippets = $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 1 ORDER BY priority ASC",
            ARRAY_A
        );

        $by_position = [];
        foreach ($active_snippets as $snippet) {
            $position = $snippet['position'];
            if (!isset($by_position[$position])) {
                $by_position[$position] = [];
            }
            $by_position[$position][] = $snippet;
        }

        foreach ($by_position as $position => $snippets) {
            $this->set('snippets_' . $position, $snippets);
        }

        $this->set('active_snippets', $active_snippets);
    }

    public function get_cache_stats() {
        global $wp_object_cache;

        $stats = [
            'enabled' => $this->cache_enabled,
            'ttl' => $this->ttl,
            'hits' => 0,
            'misses' => 0,
            'size' => 0
        ];

        if (method_exists($wp_object_cache, 'stats')) {
            $cache_stats = $wp_object_cache->stats();
            $stats['hits'] = $cache_stats['hits'] ?? 0;
            $stats['misses'] = $cache_stats['misses'] ?? 0;
        }

        return $stats;
    }

    public function schedule_cleanup() {
        if (!wp_next_scheduled('wptag_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wptag_cache_cleanup');
        }

        add_action('wptag_cache_cleanup', [$this, 'cleanup_expired']);
    }

    public function cleanup_expired() {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'wptag_logs';
        
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $logs_table WHERE created_at < %s",
            $thirty_days_ago
        ));

        $this->clear_output_cache();
    }

    public function invalidate_on_save($post_id) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        $this->clear_output_cache();
    }

    public function init_hooks() {
        add_action('save_post', [$this, 'invalidate_on_save']);
        add_action('switch_theme', [$this, 'flush']);
        add_action('activated_plugin', [$this, 'flush']);
        add_action('deactivated_plugin', [$this, 'flush']);
        
        $this->schedule_cleanup();
    }
}
