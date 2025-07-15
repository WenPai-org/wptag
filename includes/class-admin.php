<?php

namespace WPTag;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    private $config;
    private $validator;
    private $output_manager;

    public function __construct($config) {
        $this->config = $config;
        $this->validator = new Validator($config);
        $this->output_manager = new Output_Manager($config);
        
        $this->init_hooks();
    }

    private function current_user_can_manage_codes() {
        return current_user_can('manage_options') || current_user_can('wptag_manage_codes');
    }

    private function current_user_can_manage_services() {
        return current_user_can('manage_options') || current_user_can('wptag_manage_services');
    }

    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_wptag_validate_code', array($this, 'ajax_validate_code'));
        add_action('wp_ajax_wptag_preview_code', array($this, 'ajax_preview_code'));
        add_action('wp_ajax_wptag_export_settings', array($this, 'ajax_export_settings'));
        add_action('wp_ajax_wptag_import_settings', array($this, 'ajax_import_settings'));
        add_action('wp_ajax_wptag_reset_settings', array($this, 'ajax_reset_settings'));
        add_filter('plugin_action_links_' . plugin_basename(WPTAG_PLUGIN_FILE), array($this, 'add_action_links'));
        add_filter('plugin_row_meta', array($this, 'add_row_meta'), 10, 2);
        add_action('init', array($this, 'add_custom_capabilities'));
    }

    public function add_custom_capabilities() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('wptag_manage_codes');
            $role->add_cap('wptag_manage_services');
        }
        
        $role = get_role('editor');
        if ($role) {
            $role->add_cap('wptag_manage_codes');
        }
    }

    public function add_admin_menu() {
        $main_hook = add_options_page(
            'WPTag Settings',
            'WPTag',
            'manage_options',
            'wptag-settings',
            array($this, 'display_admin_page')
        );
        
        add_action('load-' . $main_hook, array($this, 'handle_form_submission'));
    }

    public function admin_init() {
        register_setting(
            'wptag_settings_group',
            'wptag_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array()
            )
        );
        
        register_setting(
            'wptag_services_group',
            'wptag_services',
            array(
                'sanitize_callback' => array($this, 'sanitize_services'),
                'default' => array()
            )
        );
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_wptag-settings') {
            return;
        }

        wp_enqueue_style(
            'wptag-admin',
            WPTAG_PLUGIN_URL . 'assets/admin.css',
            array(),
            WPTAG_VERSION
        );

        wp_enqueue_script(
            'wptag-admin',
            WPTAG_PLUGIN_URL . 'assets/admin.js',
            array('jquery', 'wp-util'),
            WPTAG_VERSION,
            true
        );

        wp_localize_script('wptag-admin', 'wptagAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wptag_admin_nonce'),
            'strings' => array(
                'validating' => 'Validating...',
                'valid' => 'Valid',
                'invalid' => 'Invalid',
                'preview' => 'Preview',
                'close' => 'Close',
                'export_success' => 'Settings exported successfully',
                'import_success' => 'Settings imported successfully',
                'reset_success' => 'Settings reset successfully',
                'confirm_reset' => 'Are you sure you want to reset all settings? This cannot be undone.',
                'confirm_import' => 'This will overwrite your current settings. Continue?',
                'loading' => 'Loading...'
            )
        ));
    }

    public function display_admin_page() {
        if (!$this->current_user_can_manage_codes() && !$this->current_user_can_manage_services()) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $this->handle_form_submission();
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'analytics';
        $categories = $this->get_categories_with_services();
        
        ?>
        <div class="wrap wptag-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php $this->display_admin_notices(); ?>
            
            <div class="wptag-header">
                <div class="wptag-header-info">
                    <p>Manage your tracking codes and analytics services with ease.</p>
                </div>
                <div class="wptag-header-actions">
                    <?php if ($this->current_user_can_manage_codes()): ?>
                        <button type="button" class="button" id="wptag-export-btn">Export Settings</button>
                        <button type="button" class="button" id="wptag-import-btn">Import Settings</button>
                        <button type="button" class="button button-secondary" id="wptag-reset-btn">Reset All</button>
                    <?php endif; ?>
                </div>
            </div>

            <nav class="nav-tab-wrapper">
                <?php foreach ($categories as $category_key => $category_data): ?>
                    <a href="?page=wptag-settings&tab=<?php echo esc_attr($category_key); ?>" 
                       class="nav-tab <?php echo $active_tab === $category_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html(ucfirst($category_key)); ?>
                        <span class="count">(<?php echo count($category_data['services']); ?>)</span>
                    </a>
                <?php endforeach; ?>
                <?php if ($this->current_user_can_manage_services()): ?>
                    <a href="?page=wptag-settings&tab=services" 
                       class="nav-tab <?php echo $active_tab === 'services' ? 'nav-tab-active' : ''; ?>">
                        Services Management
                    </a>
                <?php endif; ?>
            </nav>

            <div class="tab-content">
                <?php if ($active_tab === 'services' && $this->current_user_can_manage_services()): ?>
                    <?php $this->display_services_tab(); ?>
                <?php else: ?>
                    <?php $this->display_category_tab($active_tab, $categories[$active_tab] ?? array()); ?>
                <?php endif; ?>
            </div>
        </div>

        <input type="file" id="wptag-import-file" accept=".json" style="display: none;">
        
        <div id="wptag-preview-modal" style="display: none;">
            <div class="wptag-modal-content">
                <div class="wptag-modal-header">
                    <h3>Code Preview</h3>
                    <button type="button" class="wptag-modal-close">&times;</button>
                </div>
                <div class="wptag-modal-body">
                    <pre id="wptag-preview-code"></pre>
                </div>
            </div>
        </div>
        <?php
    }

    private function display_services_tab() {
        if (!$this->current_user_can_manage_services()) {
            echo '<div class="wptag-no-services">';
            echo '<p>You do not have permission to manage services.</p>';
            echo '</div>';
            return;
        }
        
        $all_services = $this->config->get_all_services();
        $enabled_services = $this->config->get_enabled_services();
        $categories = array();
        
        foreach ($all_services as $service_key => $service_config) {
            $categories[$service_config['category']][$service_key] = $service_config;
        }
        
        ?>
        <div class="wptag-services-management">
            <div class="wptag-services-header">
                <p>Enable or disable tracking services. Only enabled services will appear in the category tabs.</p>
                <div class="wptag-services-actions">
                    <button type="button" class="button" id="wptag-enable-all">Enable All</button>
                    <button type="button" class="button" id="wptag-disable-all">Disable All</button>
                </div>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('wptag_save_services', 'wptag_services_nonce'); ?>
                
                <?php foreach ($categories as $category_name => $category_services): ?>
                    <div class="wptag-service-category">
                        <h2><?php echo esc_html(ucfirst($category_name)); ?></h2>
                        <div class="wptag-services-grid">
                            <?php foreach ($category_services as $service_key => $service_config): ?>
                                <div class="wptag-service-item service-<?php echo esc_attr($service_key); ?>">
                                    <div class="wptag-service-info">
                                        <div class="wptag-service-icon">
                                            <span class="dashicons <?php echo esc_attr($service_config['icon']); ?>"></span>
                                        </div>
                                        <div class="wptag-service-details">
                                            <h3><?php echo esc_html($service_config['name']); ?></h3>
                                            <p><?php echo esc_html($service_config['description']); ?></p>
                                        </div>
                                    </div>
                                    <div class="wptag-service-toggle">
                                        <label class="wptag-switch">
                                            <input type="checkbox" 
                                                   name="enabled_services[]" 
                                                   value="<?php echo esc_attr($service_key); ?>" 
                                                   <?php checked(in_array($service_key, $enabled_services)); ?>>
                                            <span class="wptag-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="wptag-form-actions">
                    <?php submit_button('Save Services', 'primary', 'save_services', false); ?>
                </div>
            </form>
        </div>
        <?php
    }

    private function get_categories_with_services() {
        $categories = array();
        $services_config = $this->config->get_services_config();
        
        foreach ($services_config as $service_key => $service_config) {
            $category = $service_config['category'];
            if (!isset($categories[$category])) {
                $categories[$category] = array(
                    'title' => ucfirst($category),
                    'services' => array()
                );
            }
            $categories[$category]['services'][$service_key] = $service_config;
        }
        
        return $categories;
    }

    private function display_category_tab($active_tab, $category_data) {
        if (empty($category_data['services'])) {
            echo '<div class="wptag-no-services">';
            echo '<p>No services enabled for this category. <a href="?page=wptag-settings&tab=services">Enable some services</a> to get started.</p>';
            echo '</div>';
            return;
        }

        ?>
        <form method="post" action="" class="wptag-settings-form">
            <?php wp_nonce_field('wptag_save_settings', 'wptag_nonce'); ?>
            
            <div class="wptag-services-grid">
                <?php foreach ($category_data['services'] as $service_key => $service_config): ?>
                    <?php $this->display_service_card($service_key, $service_config); ?>
                <?php endforeach; ?>
            </div>
            
            <div class="wptag-form-actions">
                <?php submit_button('Save Settings', 'primary', 'save_settings', false); ?>
            </div>
        </form>
        <?php
    }

    private function display_service_card($service_key, $service_config) {
        $service_settings = $this->config->get_service_settings($service_key);
        $field_key = $service_config['field'];
        $can_edit = $this->current_user_can_manage_codes();
        ?>
        <div class="wptag-service-card" data-service="<?php echo esc_attr($service_key); ?>">
            <div class="wptag-service-header">
                <div class="wptag-service-icon">
                    <span class="dashicons <?php echo esc_attr($service_config['icon']); ?>"></span>
                </div>
                <div class="wptag-service-title">
                    <h3><?php echo esc_html($service_config['name']); ?></h3>
                    <div class="wptag-service-toggle">
                        <label class="wptag-switch">
                            <input type="checkbox" 
                                   name="wptag_settings[<?php echo esc_attr($service_key); ?>][enabled]" 
                                   value="1" 
                                   <?php checked($service_settings['enabled']); ?>
                                   <?php disabled(!$can_edit); ?>>
                            <span class="wptag-slider <?php echo !$can_edit ? 'disabled' : ''; ?>"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wptag-service-content">
                <div class="wptag-form-row">
                    <div class="wptag-radio-group">
                        <label>
                            <input type="radio" 
                                   name="wptag_settings[<?php echo esc_attr($service_key); ?>][use_template]" 
                                   value="1" 
                                   <?php checked($service_settings['use_template']); ?>
                                   <?php disabled(!$can_edit); ?>>
                            Template
                        </label>
                        <label>
                            <input type="radio" 
                                   name="wptag_settings[<?php echo esc_attr($service_key); ?>][use_template]" 
                                   value="0" 
                                   <?php checked($service_settings['use_template'], false); ?>
                                   <?php disabled(!$can_edit); ?>>
                            Custom
                        </label>
                    </div>
                </div>

                <div class="wptag-template-fields" <?php echo !$service_settings['use_template'] ? 'style="display: none;"' : ''; ?>>
                    <div class="wptag-form-row">
                        <label class="wptag-form-label" for="<?php echo esc_attr($service_key . '_' . $field_key); ?>">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $field_key))); ?>
                        </label>
                        <div class="wptag-input-group">
                            <input type="text" 
                                   id="<?php echo esc_attr($service_key . '_' . $field_key); ?>"
                                   name="wptag_settings[<?php echo esc_attr($service_key); ?>][<?php echo esc_attr($field_key); ?>]" 
                                   value="<?php echo esc_attr($service_settings[$field_key]); ?>" 
                                   placeholder="<?php echo esc_attr($service_config['placeholder']); ?>" 
                                   class="wptag-input"
                                   <?php disabled(!$can_edit); ?>>
                            <?php if ($can_edit): ?>
                                <button type="button" class="button wptag-validate-btn" data-service="<?php echo esc_attr($service_key); ?>">
                                    Validate
                                </button>
                                <button type="button" class="button wptag-preview-btn" data-service="<?php echo esc_attr($service_key); ?>">
                                    Preview
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="wptag-validation-result"></div>
                    </div>
                </div>

                <div class="wptag-custom-fields" <?php echo $service_settings['use_template'] ? 'style="display: none;"' : ''; ?>>
                    <div class="wptag-form-row">
                        <label class="wptag-form-label" for="<?php echo esc_attr($service_key . '_custom_code'); ?>">
                            Custom Code
                        </label>
                        <div class="wptag-code-editor-wrapper">
                            <textarea id="<?php echo esc_attr($service_key . '_custom_code'); ?>"
                                      name="wptag_settings[<?php echo esc_attr($service_key); ?>][custom_code]" 
                                      rows="12" 
                                      placeholder="Paste your complete tracking code here..."
                                      class="wptag-code-editor"
                                      <?php disabled(!$can_edit); ?>><?php echo esc_textarea($service_settings['custom_code']); ?></textarea>
                            <div class="wptag-code-editor-toolbar">
                                <button type="button" class="button wptag-format-code" title="Format Code">
                                    <span class="dashicons dashicons-editor-code"></span>
                                </button>
                                <button type="button" class="button wptag-clear-code" title="Clear Code">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                        <?php if ($can_edit): ?>
                            <div class="wptag-input-group">
                                <button type="button" class="button wptag-validate-btn" data-service="<?php echo esc_attr($service_key); ?>">
                                    Validate
                                </button>
                            </div>
                        <?php endif; ?>
                        <div class="wptag-validation-result"></div>
                    </div>
                </div>

                <div class="wptag-advanced-settings" style="display: none;">
                    <div class="wptag-form-row">
                        <label class="wptag-form-label">Position</label>
                        <select name="wptag_settings[<?php echo esc_attr($service_key); ?>][position]" 
                                class="wptag-select" <?php disabled(!$can_edit); ?>>
                            <option value="head" <?php selected($service_settings['position'], 'head'); ?>>Head</option>
                            <option value="body" <?php selected($service_settings['position'], 'body'); ?>>Body</option>
                            <option value="footer" <?php selected($service_settings['position'], 'footer'); ?>>Footer</option>
                        </select>
                    </div>

                    <div class="wptag-form-row">
                        <label class="wptag-form-label">Priority</label>
                        <input type="number" 
                               name="wptag_settings[<?php echo esc_attr($service_key); ?>][priority]" 
                               value="<?php echo esc_attr($service_settings['priority']); ?>" 
                               min="1" 
                               max="100" 
                               class="wptag-input wptag-input-small"
                               <?php disabled(!$can_edit); ?>>
                    </div>

                    <div class="wptag-form-row">
                        <label class="wptag-form-label">Device</label>
                        <select name="wptag_settings[<?php echo esc_attr($service_key); ?>][device]" 
                                class="wptag-select" <?php disabled(!$can_edit); ?>>
                            <option value="all" <?php selected($service_settings['device'], 'all'); ?>>All Devices</option>
                            <option value="desktop" <?php selected($service_settings['device'], 'desktop'); ?>>Desktop Only</option>
                            <option value="mobile" <?php selected($service_settings['device'], 'mobile'); ?>>Mobile Only</option>
                        </select>
                    </div>
                </div>

                <div class="wptag-advanced-toggle">
                    <button type="button" class="button button-link wptag-toggle-advanced">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        Advanced Settings
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    private function display_admin_notices() {
        $notices = get_transient('wptag_admin_notices');
        if (!$notices) {
            return;
        }

        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }

        delete_transient('wptag_admin_notices');
    }

    private function add_admin_notice($type, $message) {
        $notices = get_transient('wptag_admin_notices') ?: array();
        $notices[] = array('type' => $type, 'message' => $message);
        set_transient('wptag_admin_notices', $notices, 30);
    }

    public function handle_form_submission() {
        if (isset($_POST['wptag_services_nonce']) && wp_verify_nonce($_POST['wptag_services_nonce'], 'wptag_save_services')) {
            $this->handle_services_form_submission();
            return;
        }

        if (!isset($_POST['wptag_nonce']) || !wp_verify_nonce($_POST['wptag_nonce'], 'wptag_save_settings')) {
            return;
        }

        if (!$this->current_user_can_manage_codes()) {
            $this->add_admin_notice('error', 'You do not have permission to manage tracking codes.');
            return;
        }

        $settings = $_POST['wptag_settings'] ?? array();
        
        if ($this->validator->validate_settings($settings)) {
            $result = $this->config->update_settings($settings);
            
            if ($result) {
                $this->add_admin_notice('success', 'Settings saved successfully.');
            } else {
                $this->add_admin_notice('error', 'Failed to save settings.');
            }
        } else {
            $errors = $this->validator->get_error_messages();
            $this->add_admin_notice('error', 'Validation failed: ' . implode(', ', $errors));
        }

        $redirect_url = add_query_arg(
            array(
                'page' => 'wptag-settings',
                'tab' => isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'analytics'
            ),
            admin_url('options-general.php')
        );
        
        wp_redirect($redirect_url);
        exit;
    }

    private function handle_services_form_submission() {
        if (!$this->current_user_can_manage_services()) {
            $this->add_admin_notice('error', 'You do not have permission to manage services.');
            return;
        }

        $enabled_services = isset($_POST['enabled_services']) ? array_map('sanitize_text_field', $_POST['enabled_services']) : array();
        
        $result = $this->config->update_enabled_services($enabled_services);
        
        if ($result) {
            $this->add_admin_notice('success', 'Services updated successfully.');
        } else {
            $this->add_admin_notice('error', 'Failed to update services.');
        }

        wp_redirect(add_query_arg(array('page' => 'wptag-settings', 'tab' => 'services'), admin_url('options-general.php')));
        exit;
    }

    public function sanitize_settings($settings) {
        return $this->config->sanitize_settings($settings);
    }

    public function sanitize_services($services) {
        return is_array($services) ? array_map('sanitize_text_field', $services) : array();
    }

    public function ajax_validate_code() {
        check_ajax_referer('wptag_admin_nonce', 'nonce');
        
        if (!$this->current_user_can_manage_codes()) {
            wp_send_json_error(array('message' => 'You do not have permission to validate codes.'));
            return;
        }
        
        $service_key = sanitize_text_field($_POST['service']);
        $use_template = $_POST['use_template'] === '1';
        
        $settings = array(
            'enabled' => true,
            'use_template' => $use_template
        );
        
        if ($use_template) {
            $service_config = $this->config->get_service_config($service_key);
            if ($service_config) {
                $field_key = $service_config['field'];
                $settings[$field_key] = sanitize_text_field($_POST['id_value']);
            }
        } else {
            $settings['custom_code'] = wp_kses_post($_POST['custom_code']);
        }
        
        $is_valid = $this->validator->validate_service_code($service_key, $settings);
        
        if ($is_valid) {
            wp_send_json_success(array('message' => 'Code is valid'));
        } else {
            $errors = $this->validator->get_error_messages();
            wp_send_json_error(array('message' => implode(', ', $errors)));
        }
    }

    public function ajax_preview_code() {
        check_ajax_referer('wptag_admin_nonce', 'nonce');
        
        if (!$this->current_user_can_manage_codes()) {
            wp_send_json_error(array('message' => 'You do not have permission to preview codes.'));
            return;
        }
        
        $service_key = sanitize_text_field($_POST['service']);
        $id_value = sanitize_text_field($_POST['id_value']);
        
        $preview = $this->output_manager->get_template_preview($service_key, $id_value);
        
        if (!empty($preview)) {
            wp_send_json_success(array('preview' => $preview));
        } else {
            wp_send_json_error(array('message' => 'Unable to generate preview'));
        }
    }

    public function ajax_export_settings() {
        check_ajax_referer('wptag_admin_nonce', 'nonce');
        
        if (!$this->current_user_can_manage_codes()) {
            wp_send_json_error(array('message' => 'You do not have permission to export settings.'));
            return;
        }
        
        $export_data = $this->config->export_settings();
        
        wp_send_json_success(array(
            'data' => $export_data,
            'filename' => 'wptag-settings-' . date('Y-m-d-H-i-s') . '.json'
        ));
    }

    public function ajax_import_settings() {
        check_ajax_referer('wptag_admin_nonce', 'nonce');
        
        if (!$this->current_user_can_manage_codes()) {
            wp_send_json_error(array('message' => 'You do not have permission to import settings.'));
            return;
        }
        
        $import_data = stripslashes($_POST['import_data']);
        
        if ($this->validator->validate_import_data($import_data)) {
            $result = $this->config->import_settings($import_data);
            
            if (!is_wp_error($result)) {
                wp_send_json_success(array('message' => 'Settings imported successfully'));
            } else {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
        } else {
            $errors = $this->validator->get_error_messages();
            wp_send_json_error(array('message' => implode(', ', $errors)));
        }
    }

    public function ajax_reset_settings() {
        check_ajax_referer('wptag_admin_nonce', 'nonce');
        
        if (!$this->current_user_can_manage_codes()) {
            wp_send_json_error(array('message' => 'You do not have permission to reset settings.'));
            return;
        }
        
        $this->config->reset_to_defaults();
        
        wp_send_json_success(array('message' => 'Settings reset successfully'));
    }

    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=wptag-settings') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_row_meta($links, $file) {
        if ($file === plugin_basename(WPTAG_PLUGIN_FILE)) {
            $links[] = '<a href="https://wptag.com/docs/" target="_blank">Documentation</a>';
            $links[] = '<a href="https://wptag.com/support/" target="_blank">Support</a>';
        }
        return $links;
    }
}