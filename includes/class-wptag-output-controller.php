<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPTag_Output_Controller {
    private $snippet_manager;
    private $condition_engine;
    private $cache_manager;
    private $rendered_snippets = [];

    public function __construct($snippet_manager, $condition_engine, $cache_manager) {
        $this->snippet_manager = $snippet_manager;
        $this->condition_engine = $condition_engine;
        $this->cache_manager = $cache_manager;
    }

    public function render_head() {
        $this->render_snippets('head');
    }

    public function render_footer() {
        $this->render_snippets('footer');
    }

    public function filter_content($content) {
        if (!in_the_loop() || !is_main_query()) {
            return $content;
        }

        $before = $this->get_rendered_snippets('before_content');
        $after = $this->get_rendered_snippets('after_content');

        return $before . $content . $after;
    }

    private function render_snippets($position) {
        echo $this->get_rendered_snippets($position);
    }

    private function get_rendered_snippets($position) {
        $cache_key = 'wptag_output_' . $position . '_' . $this->get_cache_context();
        $cached = $this->cache_manager->get($cache_key);

        if ($cached !== false && !$this->is_preview_mode()) {
            return $cached;
        }

        $snippets = $this->snippet_manager->get_active_snippets_by_position($position);
        $output = '';

        foreach ($snippets as $snippet) {
            if ($this->should_render_snippet($snippet)) {
                $output .= $this->render_single_snippet($snippet);
                $this->rendered_snippets[] = $snippet['id'];
            }
        }

        if (!empty($output)) {
            $output = "\n<!-- WPTAG Start -->\n" . $output . "<!-- WPTAG End -->\n";
        }

        $this->cache_manager->set($cache_key, $output, 3600);

        return $output;
    }

    private function should_render_snippet($snippet) {
        if (in_array($snippet['id'], $this->rendered_snippets)) {
            return false;
        }

        if ($this->is_preview_mode() && !current_user_can('manage_options')) {
            return false;
        }

        if (!empty($snippet['device_type']) && $snippet['device_type'] !== 'all') {
            $device_check = $this->condition_engine->evaluate_conditions([
                'rules' => [[
                    'type' => 'device_type',
                    'operator' => 'equals',
                    'value' => $snippet['device_type']
                ]]
            ]);

            if (!$device_check) {
                return false;
            }
        }

        if (!empty($snippet['conditions'])) {
            return $this->condition_engine->evaluate_conditions($snippet['conditions']);
        }

        return true;
    }

    private function render_single_snippet($snippet) {
        $code = $snippet['code'];

        if ($snippet['load_method'] === 'async' && $snippet['code_type'] === 'javascript') {
            $code = $this->wrap_async_script($code);
        } elseif ($snippet['load_method'] === 'defer' && $snippet['code_type'] === 'javascript') {
            $code = $this->wrap_defer_script($code);
        }

        $code = apply_filters('wptag_snippet_output', $code, $snippet);

        if ($this->is_preview_mode() && current_user_can('manage_options')) {
            $code = $this->wrap_preview_mode($code, $snippet);
        }

        return $code . "\n";
    }

    private function wrap_async_script($code) {
        if (strpos($code, '<script') === false) {
            $code = '<script>' . $code . '</script>';
        }

        return str_replace('<script', '<script async', $code);
    }

    private function wrap_defer_script($code) {
        if (strpos($code, '<script') === false) {
            $code = '<script>' . $code . '</script>';
        }

        return str_replace('<script', '<script defer', $code);
    }

    private function wrap_preview_mode($code, $snippet) {
        $name = esc_html($snippet['name']);
        $id = esc_attr($snippet['id']);
        
        return "<!-- WPTAG Preview: {$name} (ID: {$id}) -->\n{$code}\n<!-- /WPTAG Preview -->\n";
    }

    private function get_cache_context() {
        $context = [
            'type' => $this->get_page_type(),
            'id' => get_the_ID(),
            'user' => is_user_logged_in() ? 'logged_in' : 'logged_out'
        ];

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $context['roles'] = $user->roles;
        }

        return md5(json_encode($context));
    }

    private function get_page_type() {
        if (is_home() || is_front_page()) return 'home';
        if (is_single()) return 'single';
        if (is_page()) return 'page';
        if (is_category()) return 'category';
        if (is_tag()) return 'tag';
        if (is_archive()) return 'archive';
        if (is_search()) return 'search';
        if (is_404()) return '404';
        return 'other';
    }

    private function is_preview_mode() {
        return isset($_GET['wptag_preview']) && $_GET['wptag_preview'] === '1';
    }

    public function clear_output_cache() {
        $positions = ['head', 'footer', 'before_content', 'after_content'];
        
        foreach ($positions as $position) {
            wp_cache_delete('wptag_output_' . $position, 'wptag');
        }
    }
}
