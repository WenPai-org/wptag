<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPTag_Admin_Controller {
    private $snippet_manager;
    private $template_manager;
    private $page_hook_suffix = [];

    public function __construct($snippet_manager, $template_manager) {
        $this->snippet_manager = $snippet_manager;
        $this->template_manager = $template_manager;
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_form_submissions']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
    }

    public function add_admin_menu() {
        $capability = 'manage_options';
        
        $this->page_hook_suffix['main'] = add_menu_page(
            __('WPTAG', 'wptag'),
            __('WPTAG', 'wptag'),
            $capability,
            'wptag',
            [$this, 'render_dashboard_page'],
            'dashicons-code-standards',
            85
        );
        
        $this->page_hook_suffix['dashboard'] = add_submenu_page(
            'wptag',
            __('Dashboard', 'wptag'),
            __('Dashboard', 'wptag'),
            $capability,
            'wptag',
            [$this, 'render_dashboard_page']
        );
        
        $this->page_hook_suffix['snippets'] = add_submenu_page(
            'wptag',
            __('Code Snippets', 'wptag'),
            __('Code Snippets', 'wptag'),
            $capability,
            'wptag-snippets',
            [$this, 'render_snippets_page']
        );
        
        $this->page_hook_suffix['templates'] = add_submenu_page(
            'wptag',
            __('Service Templates', 'wptag'),
            __('Service Templates', 'wptag'),
            $capability,
            'wptag-templates',
            [$this, 'render_templates_page']
        );
        
        $this->page_hook_suffix['settings'] = add_submenu_page(
            'wptag',
            __('Settings', 'wptag'),
            __('Settings', 'wptag'),
            $capability,
            'wptag-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, $this->page_hook_suffix)) {
            return;
        }
        
        wp_enqueue_style(
            'wptag-admin',
            WPTAG_PLUGIN_URL . 'assets/css/admin.css',
            ['wp-components'],
            WPTAG_VERSION
        );
        
        wp_enqueue_script(
            'wptag-admin',
            WPTAG_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api', 'wp-i18n', 'wp-components', 'wp-element'],
            WPTAG_VERSION,
            true
        );
        
        wp_localize_script('wptag-admin', 'wptagAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wptag_admin'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this snippet?', 'wptag'),
                'saved' => __('Settings saved successfully.', 'wptag'),
                'error' => __('An error occurred. Please try again.', 'wptag')
            ]
        ]);
        
        if (isset($_GET['action']) && ($_GET['action'] === 'new' || $_GET['action'] === 'edit')) {
            wp_enqueue_code_editor(['type' => 'text/html']);
        }
    }

    public function render_dashboard_page() {
        require_once WPTAG_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    public function render_snippets_page() {
        require_once WPTAG_PLUGIN_DIR . 'admin/partials/snippets.php';
    }

    public function render_templates_page() {
        require_once WPTAG_PLUGIN_DIR . 'admin/partials/templates.php';
    }

    public function render_settings_page() {
        require_once WPTAG_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    public function handle_form_submissions() {
        if (!isset($_POST['wptag_action'])) {
            return;
        }
        
        $action = sanitize_key($_POST['wptag_action']);
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wptag_' . $action)) {
            wp_die(__('Security check failed', 'wptag'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'wptag'));
        }
        
        switch ($action) {
            case 'create_snippet':
                $this->handle_create_snippet();
                break;
                
            case 'update_snippet':
                $this->handle_update_snippet();
                break;
                
            case 'delete_snippet':
                $this->handle_delete_snippet();
                break;
                
            case 'toggle_snippet':
                $this->handle_toggle_snippet();
                break;
                
            case 'save_settings':
                $this->handle_save_settings();
                break;
        }
    }

    private function handle_create_snippet() {
        $data = [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'code' => $_POST['code'] ?? '',
            'code_type' => $_POST['code_type'] ?? 'html',
            'position' => $_POST['position'] ?? 'head',
            'category' => $_POST['category'] ?? 'custom',
            'priority' => $_POST['priority'] ?? 10,
            'status' => isset($_POST['status']) ? 1 : 0,
            'device_type' => $_POST['device_type'] ?? 'all',
            'load_method' => $_POST['load_method'] ?? 'normal',
            'conditions' => $this->parse_conditions($_POST['conditions'] ?? [])
        ];
        
        $result = $this->snippet_manager->create_snippet($data);
        
        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $this->add_admin_notice(__('Snippet created successfully', 'wptag'), 'success');
            wp_redirect(admin_url('admin.php?page=wptag-snippets'));
            exit;
        }
    }

    private function handle_update_snippet() {
        $id = intval($_POST['snippet_id'] ?? 0);
        
        if (!$id) {
            $this->add_admin_notice(__('Invalid snippet ID', 'wptag'), 'error');
            return;
        }
        
        $data = [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'code' => $_POST['code'] ?? '',
            'code_type' => $_POST['code_type'] ?? 'html',
            'position' => $_POST['position'] ?? 'head',
            'category' => $_POST['category'] ?? 'custom',
            'priority' => $_POST['priority'] ?? 10,
            'status' => isset($_POST['status']) ? 1 : 0,
            'device_type' => $_POST['device_type'] ?? 'all',
            'load_method' => $_POST['load_method'] ?? 'normal',
            'conditions' => $this->parse_conditions($_POST['conditions'] ?? [])
        ];
        
        $result = $this->snippet_manager->update_snippet($id, $data);
        
        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $this->add_admin_notice(__('Snippet updated successfully', 'wptag'), 'success');
        }
    }

    private function handle_delete_snippet() {
        $id = intval($_GET['snippet_id'] ?? 0);
        
        if (!$id) {
            $this->add_admin_notice(__('Invalid snippet ID', 'wptag'), 'error');
            return;
        }
        
        $result = $this->snippet_manager->delete_snippet($id);
        
        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $this->add_admin_notice(__('Snippet deleted successfully', 'wptag'), 'success');
        }
        
        wp_redirect(admin_url('admin.php?page=wptag-snippets'));
        exit;
    }

    private function handle_toggle_snippet() {
        $id = intval($_GET['snippet_id'] ?? 0);
        
        if (!$id) {
            wp_die(json_encode(['success' => false, 'message' => 'Invalid ID']));
        }
        
        $result = $this->snippet_manager->toggle_status($id);
        
        if (is_wp_error($result)) {
            wp_die(json_encode(['success' => false, 'message' => $result->get_error_message()]));
        }
        
        wp_die(json_encode(['success' => true, 'status' => $result]));
    }

    private function handle_save_settings() {
        $settings = [
            'enable_cache' => isset($_POST['enable_cache']) ? 1 : 0,
            'cache_ttl' => intval($_POST['cache_ttl'] ?? 3600),
            'enable_debug' => isset($_POST['enable_debug']) ? 1 : 0,
            'cleanup_on_uninstall' => isset($_POST['cleanup_on_uninstall']) ? 1 : 0
        ];
        
        update_option('wptag_settings', $settings);
        
        $this->add_admin_notice(__('Settings saved successfully', 'wptag'), 'success');
        
        wp_redirect(admin_url('admin.php?page=wptag-settings'));
        exit;
    }

    private function parse_conditions($conditions_data) {
        if (empty($conditions_data) || !is_array($conditions_data)) {
            return null;
        }
        
        return $conditions_data;
    }

    private function add_admin_notice($message, $type = 'info') {
        set_transient('wptag_admin_notice', [
            'message' => $message,
            'type' => $type
        ], 30);
    }

    public function display_admin_notices() {
        $notice = get_transient('wptag_admin_notice');
        
        if (!$notice) {
            return;
        }
        
        delete_transient('wptag_admin_notice');
        
        $class = 'notice notice-' . esc_attr($notice['type']);
        printf(
            '<div class="%1$s is-dismissible"><p>%2$s</p></div>',
            $class,
            esc_html($notice['message'])
        );
    }
}
