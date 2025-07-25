<?php

namespace WPTag;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend {
    private $config;
    private $output_manager;
    private $should_load = true;

    public function __construct($config) {
        $this->config = $config;
        $this->output_manager = new Output_Manager($config);
        
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('template_redirect', array($this, 'template_redirect'));
        
        if (!is_admin()) {
            add_action('wp_head', array($this, 'output_head_codes'), 1);
            add_action('wp_body_open', array($this, 'output_body_codes'), 1);
            add_action('wp_footer', array($this, 'output_footer_codes'), 1);
        }
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_filter('wptag_should_output_codes', array($this, 'filter_should_output_codes'), 10, 1);
    }

    public function template_redirect() {
        $this->should_load = $this->determine_if_should_load();
    }

    public function output_head_codes() {
        if (!$this->should_output_codes()) {
            $this->output_debug_info('head');
            return;
        }
        
        try {
            $this->output_manager->output_codes('head');
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WPTag Head Code Output Error: ' . $e->getMessage());
            }
        }
    }

    public function output_body_codes() {
        if (!$this->should_output_codes()) {
            $this->output_debug_info('body');
            return;
        }
        
        try {
            $this->output_manager->output_codes('body');
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WPTag Body Code Output Error: ' . $e->getMessage());
            }
        }
    }

    public function output_footer_codes() {
        if (!$this->should_output_codes()) {
            $this->output_debug_info('footer');
            return;
        }
        
        try {
            $this->output_manager->output_codes('footer');
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WPTag Footer Code Output Error: ' . $e->getMessage());
            }
        }
    }

    private function output_debug_info($position) {
        if (!defined('WP_DEBUG') || !WP_DEBUG || !current_user_can('manage_options')) {
            return;
        }
        
        $debug_info = $this->get_debug_info();
        $enabled_services = $this->config->get_enabled_services();
        $settings = $this->config->get_settings();
        
        $active_services = array();
        foreach ($settings as $service_key => $service_settings) {
            if (!empty($service_settings['enabled'])) {
                $active_services[] = $service_key;
            }
        }
        
        echo "\n<!-- WPTag Debug Info for {$position} -->\n";
        echo "<!-- Enabled Services: " . implode(', ', $enabled_services) . " -->\n";
        echo "<!-- Active Services: " . implode(', ', $active_services) . " -->\n";
        echo "<!-- Should Load: " . ($this->should_load ? 'Yes' : 'No') . " -->\n";
        echo "<!-- Should Output: " . ($this->should_output_codes() ? 'Yes' : 'No') . " -->\n";
        echo "<!-- Is Admin: " . (is_admin() ? 'Yes' : 'No') . " -->\n";
        echo "<!-- Is User Logged In: " . (is_user_logged_in() ? 'Yes' : 'No') . " -->\n";
        echo "<!-- Current User Can Manage: " . (current_user_can('manage_options') ? 'Yes' : 'No') . " -->\n";
        echo "<!-- End WPTag Debug Info -->\n";
    }

    private function should_output_codes() {
        global $pagenow;
        
        if (is_admin() || $pagenow === 'wp-admin/admin.php' || strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-admin/') !== false) {
            return false;
        }
        
        if (!$this->should_load) {
            return false;
        }
        
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return false;
        }
        
        if (defined('DOING_CRON') && DOING_CRON) {
            return false;
        }
        
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        
        if (wp_doing_ajax()) {
            return false;
        }
        
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return false;
        }
        
        if (is_user_logged_in() && current_user_can('manage_options')) {
            $show_for_admin = apply_filters('wptag_show_for_admin', true);
            if (!$show_for_admin) {
                return false;
            }
        }
        
        if ($this->is_bot_or_crawler()) {
            $show_for_bots = apply_filters('wptag_show_for_bots', true);
            if (!$show_for_bots) {
                return false;
            }
        }
        
        return apply_filters('wptag_should_output_codes', true);
    }

    private function determine_if_should_load() {
        global $wp_query;
        
        if (is_404()) {
            return apply_filters('wptag_load_on_404', true);
        }
        
        if (is_search() && !have_posts()) {
            return apply_filters('wptag_load_on_empty_search', true);
        }
        
        if (is_feed()) {
            return apply_filters('wptag_load_on_feed', false);
        }
        
        if (is_trackback()) {
            return apply_filters('wptag_load_on_trackback', false);
        }
        
        if (is_robots()) {
            return apply_filters('wptag_load_on_robots', false);
        }
        
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return apply_filters('wptag_load_on_xmlrpc', false);
        }
        
        return apply_filters('wptag_should_load_frontend', true);
    }

    private function get_admin_preview_setting() {
        return get_option('wptag_admin_preview', false);
    }

    private function is_bot_or_crawler() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        
        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        
        $bot_patterns = array(
            'googlebot',
            'bingbot',
            'slurp',
            'duckduckbot',
            'baiduspider',
            'yandexbot',
            'facebookexternalhit',
            'twitterbot',
            'linkedinbot',
            'whatsapp',
            'telegrambot',
            'applebot',
            'crawler',
            'spider',
            'robot',
            'bot/'
        );
        
        foreach ($bot_patterns as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    public function filter_should_output_codes($should_output) {
        if (!$should_output) {
            return false;
        }
        
        $excluded_urls = $this->get_excluded_urls();
        if (!empty($excluded_urls)) {
            $current_url = $this->get_current_url();
            foreach ($excluded_urls as $excluded_url) {
                if ($this->url_matches_pattern($current_url, $excluded_url)) {
                    return false;
                }
            }
        }
        
        $excluded_post_types = $this->get_excluded_post_types();
        if (!empty($excluded_post_types) && is_singular()) {
            $post_type = get_post_type();
            if (in_array($post_type, $excluded_post_types)) {
                return false;
            }
        }
        
        $excluded_user_roles = $this->get_excluded_user_roles();
        if (!empty($excluded_user_roles) && is_user_logged_in()) {
            $user = wp_get_current_user();
            $user_roles = $user->roles;
            
            foreach ($excluded_user_roles as $excluded_role) {
                if (in_array($excluded_role, $user_roles)) {
                    return false;
                }
            }
        }
        
        return $should_output;
    }

    private function get_excluded_urls() {
        $excluded = get_option('wptag_excluded_urls', array());
        return is_array($excluded) ? $excluded : array();
    }

    private function get_excluded_post_types() {
        $excluded = get_option('wptag_excluded_post_types', array());
        return is_array($excluded) ? $excluded : array();
    }

    private function get_excluded_user_roles() {
        $excluded = get_option('wptag_excluded_user_roles', array());
        return is_array($excluded) ? $excluded : array();
    }

    private function get_current_url() {
        return home_url(add_query_arg(array(), $_SERVER['REQUEST_URI']));
    }

    private function url_matches_pattern($url, $pattern) {
        $pattern = trim($pattern);
        
        if (empty($pattern)) {
            return false;
        }
        
        if ($pattern === $url) {
            return true;
        }
        
        if (strpos($pattern, '*') !== false) {
            $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
            return preg_match('/^' . $pattern . '$/i', $url);
        }
        
        if (strpos($url, $pattern) !== false) {
            return true;
        }
        
        return false;
    }

    public function enqueue_frontend_assets() {
        if (!$this->should_output_codes()) {
            return;
        }
        
        do_action('wptag_enqueue_frontend_assets');
        
        $custom_css = get_option('wptag_custom_css', '');
        if (!empty($custom_css)) {
            wp_add_inline_style('wp-block-library', $custom_css);
        }
        
        $custom_js = get_option('wptag_custom_js', '');
        if (!empty($custom_js)) {
            wp_add_inline_script('jquery', $custom_js);
        }
    }

    public function get_output_manager() {
        return $this->output_manager;
    }

    public function clear_cache() {
        if ($this->output_manager) {
            $this->output_manager->clear_cache();
        }
        
        wp_cache_set_last_changed('wptag_codes');
        
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('wptag');
        }
    }

    public function get_debug_info() {
        if (!current_user_can('manage_options')) {
            return array();
        }
        
        return array(
            'should_load' => $this->should_load,
            'should_output_codes' => $this->should_output_codes(),
            'is_admin' => is_admin(),
            'is_ajax' => wp_doing_ajax(),
            'is_user_logged_in' => is_user_logged_in(),
            'current_user_can_manage' => current_user_can('manage_options'),
            'is_bot' => $this->is_bot_or_crawler(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'current_url' => $this->get_current_url(),
            'page_type' => $this->get_page_type_debug(),
            'enabled_services' => count($this->config->get_enabled_services()),
            'active_codes' => $this->count_active_codes()
        );
    }

    private function get_page_type_debug() {
        $types = array();
        
        if (is_home()) $types[] = 'home';
        if (is_front_page()) $types[] = 'front_page';
        if (is_single()) $types[] = 'single';
        if (is_page()) $types[] = 'page';
        if (is_category()) $types[] = 'category';
        if (is_tag()) $types[] = 'tag';
        if (is_archive()) $types[] = 'archive';
        if (is_search()) $types[] = 'search';
        if (is_404()) $types[] = '404';
        if (is_feed()) $types[] = 'feed';
        
        return empty($types) ? 'unknown' : implode(', ', $types);
    }

    private function count_active_codes() {
        $count = 0;
        $settings = $this->config->get_settings();
        
        foreach ($settings as $service_settings) {
            if (!empty($service_settings['enabled'])) {
                $count++;
            }
        }
        
        return $count;
    }

    public function add_debug_output() {
        if (!current_user_can('manage_options') || !apply_filters('wptag_show_debug_output', false)) {
            return;
        }
        
        $debug_info = $this->get_debug_info();
        
        echo "\n<!-- WPTag Debug Info -->\n";
        echo "<!-- " . wp_json_encode($debug_info, JSON_PRETTY_PRINT) . " -->\n";
        echo "<!-- End WPTag Debug Info -->\n";
    }

    public function handle_amp_compatibility() {
        if (function_exists('is_amp_endpoint') && is_amp_endpoint()) {
            remove_action('wp_head', array($this, 'output_head_codes'), 1);
            remove_action('wp_body_open', array($this, 'output_body_codes'), 1);
            remove_action('wp_footer', array($this, 'output_footer_codes'), 1);
            
            add_action('amp_post_template_head', array($this, 'output_amp_codes'));
        }
    }

    public function output_amp_codes() {
        $settings = $this->config->get_settings();
        $amp_compatible_services = array('google_analytics', 'google_tag_manager');
        
        foreach ($settings as $service_key => $service_settings) {
            if (!empty($service_settings['enabled']) && in_array($service_key, $amp_compatible_services)) {
                $this->output_amp_service_code($service_key, $service_settings);
            }
        }
    }

    private function output_amp_service_code($service_key, $service_settings) {
        if ($service_key === 'google_analytics' && !empty($service_settings['tracking_id'])) {
            $tracking_id = $service_settings['tracking_id'];
            
            if (strpos($tracking_id, 'G-') === 0) {
                echo '<amp-analytics type="gtag" data-credentials="include">';
                echo '<script type="application/json">';
                echo wp_json_encode(array(
                    'gtag_id' => $tracking_id,
                    'config' => array(
                        $tracking_id => array(
                            'groups' => 'default'
                        )
                    )
                ));
                echo '</script>';
                echo '</amp-analytics>';
            }
        }
    }
}