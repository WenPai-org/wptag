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
        add_filter('wptag_should_output_codes', '__return_false', 999);
        
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
                'loading' => 'Loading...',
                'fill_required' => 'Please fill in required fields for enabled services.',
                'advanced_settings' => 'Advanced Settings',
                'hide_advanced' => 'Hide Advanced Settings'
            )
        ));
    }

    public function display_admin_page() {
        if (!$this->current_user_can_manage_codes() && !$this->current_user_can_manage_services()) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        add_filter('wptag_should_output_codes', '__return_false', 999);
        remove_action('wp_head', array($this->frontend ?? null, 'output_head_codes'), 1);
        remove_action('wp_body_open', array($this->frontend ?? null, 'output_body_codes'), 1);
        remove_action('wp_footer', array($this->frontend ?? null, 'output_footer_codes'), 1);
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'analytics';
        $categories = $this->get_categories_with_services();
        
        ?>
        <div class="wrap wptag-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php $this->display_admin_notices(); ?>
            
            <div class="wptag-header">
                <div class="wptag-header-info">
                    <p>Manage your tracking codes and analytics services with ease. Enable services in the Services Management tab, then configure them in their respective category tabs.</p>
                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                        <p><small><strong>Debug Mode:</strong> Check your website's source code for WPTag debug comments if codes are not appearing.</small></p>
                    <?php endif; ?>
                </div>
                <div class="wptag-header-actions">
                    <?php if ($this->current_user_can_manage_codes()): ?>
                        <button type="button" class="button" id="wptag-export-btn">
                            Export Settings
                        </button>
                        <button type="button" class="button" id="wptag-import-btn">
                            Import Settings
                        </button>
                        <button type="button" class="button button-secondary" id="wptag-reset-btn">
                            Reset All
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <nav class="nav-tab-wrapper">
                <?php foreach ($categories as $category_key => $category_data): ?>
                    <a href="?page=wptag-settings&tab=<?php echo esc_attr($category_key); ?>" 
                       class="nav-tab <?php echo $active_tab === $category_key ? 'nav-tab-active' : ''; ?>"
                       data-tab="<?php echo esc_attr($category_key); ?>">
                        <?php echo esc_html(ucfirst($category_key)); ?>
                        <span class="count">(<?php echo count($category_data['services']); ?>)</span>
                    </a>
                <?php endforeach; ?>
                <?php if ($this->current_user_can_manage_services()): ?>
                    <a href="?page=wptag-settings&tab=services" 
                       class="nav-tab <?php echo $active_tab === 'services' ? 'nav-tab-active' : ''; ?>"
                       data-tab="services">
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
        
        <div id="wptag-preview-modal" class="wptag-modal" style="display: none;">
            <div class="wptag-modal-backdrop"></div>
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
                <div class="wptag-services-info">
                    <p>Enable or disable tracking services. Only enabled services will appear in the category tabs.</p>
                    <p><small><strong>Custom Code Support:</strong> When using custom code, JavaScript will be preserved without filtering - perfect for gtag(), fbq(), and other tracking functions.</small></p>
                </div>
                <div class="wptag-services-actions">
                    <button type="button" class="button" id="wptag-enable-all">Enable All</button>
                    <button type="button" class="button" id="wptag-disable-all">Disable All</button>
                </div>
            </div>

            <form method="post" action="" id="wptag-services-form">
                <?php wp_nonce_field('wptag_save_services', 'wptag_services_nonce'); ?>
                
                <?php foreach ($categories as $category_name => $category_services): ?>
                    <div class="wptag-service-category">
                        <h2><?php echo esc_html(ucfirst($category_name)); ?></h2>
                        <div class="wptag-services-grid">
                            <?php foreach ($category_services as $service_key => $service_config): ?>
                                <div class="wptag-service-item <?php echo in_array($service_key, $enabled_services) ? 'enabled' : 'disabled'; ?>" 
                                     data-service="<?php echo esc_attr($service_key); ?>">
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
            echo '<div class="wptag-no-services-icon">';
            echo '<span class="dashicons dashicons-admin-settings"></span>';
            echo '</div>';
            echo '<h3>No Services Available</h3>';
            echo '<p>No services are enabled for this category. <a href="?page=wptag-settings&tab=services">Enable some services</a> to get started.</p>';
            echo '</div>';
            return;
        }

        ?>
        <form method="post" action="" class="wptag-settings-form" id="wptag-settings-form">
            <?php wp_nonce_field('wptag_save_settings', 'wptag_nonce'); ?>
            
            <div class="wptag-services-grid">
                <?php foreach ($category_data['services'] as $service_key => $service_config): ?>
                    <?php $this->display_service_card($service_key, $service_config); ?>
                <?php endforeach; ?>
            </div>
            
            <div class="wptag-form-actions">
                <?php submit_button('Save Settings', 'primary', 'save_settings', false, array('id' => 'wptag-save-btn')); ?>
            </div>
        </form>
        <?php
    }

    private function display_service_card($service_key, $service_config) {
        $service_settings = $this->config->get_service_settings($service_key);
        $field_key = $service_config['field'];
        $can_edit = $this->current_user_can_manage_codes();
        $is_enabled = !empty($service_settings['enabled']);
        ?>
        <div class="wptag-service-card <?php echo $is_enabled ? 'enabled' : 'disabled'; ?>" 
             data-service="<?php echo esc_attr($service_key); ?>">
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
                            <span>Template</span>
                        </label>
                        <label>
                            <input type="radio" 
                                   name="wptag_settings[<?php echo esc_attr($service_key); ?>][use_template]" 
                                   value="0" 
                                   <?php checked($service_settings['use_template'], false); ?>
                                   <?php disabled(!$can_edit); ?>>
                            <span>Custom</span>
                        </label>
                    </div>
                </div>

                <div class="wptag-template-fields" <?php echo !$service_settings['use_template'] ? 'style="display: none;"' : ''; ?>>
                    <div class="wptag-form-row">
                        <label class="wptag-form-label" for="<?php echo esc_attr($service_key . '_' . $field_key); ?>">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $field_key))); ?>
                            <span class="required">*</span>
                        </label>
                        <div class="wptag-input-group">
                            <input type="text" 
                                   id="<?php echo esc_attr($service_key . '_' . $field_key); ?>"
                                   name="wptag_settings[<?php echo esc_attr($service_key); ?>][<?php echo esc_attr($field_key); ?>]" 
                                   value="<?php echo esc_attr($service_settings[$field_key]); ?>" 
                                   placeholder="<?php echo esc_attr($service_config['placeholder']); ?>" 
                                   class="wptag-input"
                                   <?php disabled(!$can_edit); ?>
                                   data-required="true">
                            <?php if ($can_edit): ?>
                                <button type="button" class="button wptag-validate-btn" data-service="<?php echo esc_attr($service_key); ?>">
                                    <span class="dashicons dashicons-yes"></span>
                                    Validate
                                </button>
                                <button type="button" class="button wptag-preview-btn" data-service="<?php echo esc_attr($service_key); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    Preview
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="wptag-validation-result"></div>
                        <div class="wptag-field-help">
                            <small><?php echo esc_html($this->get_template_help_text($service_key, $service_config)); ?></small>
                            <small><a href="<?php echo esc_url($this->get_service_docs_url($service_key)); ?>" target="_blank" rel="noopener">View <?php echo esc_html($service_config['name']); ?> documentation</a></small>
                        </div>
                    </div>
                </div>

                <div class="wptag-custom-fields" <?php echo $service_settings['use_template'] ? 'style="display: none;"' : ''; ?>>
                    <div class="wptag-form-row">
                        <label class="wptag-form-label" for="<?php echo esc_attr($service_key . '_custom_code'); ?>">
                            Custom Code
                            <span class="required">*</span>
                        </label>
                        <div class="wptag-code-editor-wrapper">
                            <textarea id="<?php echo esc_attr($service_key . '_custom_code'); ?>"
                                      name="wptag_settings[<?php echo esc_attr($service_key); ?>][custom_code]" 
                                      rows="12" 
                                      placeholder="Paste your complete tracking code here..."
                                      class="wptag-code-editor"
                                      <?php disabled(!$can_edit); ?>
                                      data-required="true"><?php echo esc_textarea($service_settings['custom_code']); ?></textarea>
                            <?php if ($can_edit): ?>
                                <div class="wptag-code-editor-toolbar">
                                    <button type="button" class="button wptag-format-code" title="Format Code">
                                        <span class="dashicons dashicons-editor-code"></span>
                                    </button>
                                    <button type="button" class="button wptag-clear-code" title="Clear Code">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($can_edit): ?>
                            <div class="wptag-input-group">
                                <button type="button" class="button wptag-validate-btn" data-service="<?php echo esc_attr($service_key); ?>">
                                    <span class="dashicons dashicons-yes"></span>
                                    Validate
                                </button>
                            </div>
                        <?php endif; ?>
                        <div class="wptag-validation-result"></div>
                        <div class="wptag-field-help">
                            <small><?php echo esc_html($this->get_service_help_text($service_key, $service_config)); ?></small>
                            <small><a href="<?php echo esc_url($this->get_service_docs_url($service_key)); ?>" target="_blank" rel="noopener">View <?php echo esc_html($service_config['name']); ?> documentation</a></small>
                        </div>
                    </div>
                </div>

                <div class="wptag-advanced-settings">
                    <div class="wptag-advanced-grid">
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
                </div>

                <div class="wptag-advanced-toggle">
                    <button type="button" class="wptag-toggle-advanced">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        Advanced Settings
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    private function display_admin_notices() {
        $notices = get_transient('wptag_admin_notices_' . get_current_user_id());
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

        delete_transient('wptag_admin_notices_' . get_current_user_id());
    }

    private function add_admin_notice($type, $message) {
        $notices = get_transient('wptag_admin_notices_' . get_current_user_id()) ?: array();
        $notices[] = array('type' => $type, 'message' => $message);
        set_transient('wptag_admin_notices_' . get_current_user_id(), $notices, 30);
    }

    public function handle_form_submission() {
        if (!isset($_POST['wptag_nonce']) && !isset($_POST['wptag_services_nonce'])) {
            return;
        }

        if (isset($_POST['wptag_services_nonce']) && wp_verify_nonce($_POST['wptag_services_nonce'], 'wptag_save_services')) {
            $this->handle_services_form_submission();
            return;
        }

        if (isset($_POST['wptag_nonce']) && wp_verify_nonce($_POST['wptag_nonce'], 'wptag_save_settings')) {
            $this->handle_settings_form_submission();
            return;
        }
    }

    private function handle_settings_form_submission() {
        if (!$this->current_user_can_manage_codes()) {
            $this->add_admin_notice('error', 'You do not have permission to manage tracking codes.');
            return;
        }

        $settings = $_POST['wptag_settings'] ?? array();
        
        if ($this->validator->validate_settings($settings)) {
            $result = $this->config->update_settings($settings);
            
            if ($result) {
                do_action('wptag_settings_saved', $settings);
                
                $has_custom_code = false;
                foreach ($settings as $service_settings) {
                    if (!empty($service_settings['enabled']) && empty($service_settings['use_template']) && !empty($service_settings['custom_code'])) {
                        $has_custom_code = true;
                        break;
                    }
                }
                
                if ($has_custom_code) {
                    $this->add_admin_notice('success', 'Settings saved successfully. Custom JavaScript codes have been preserved correctly.');
                } else {
                    $this->add_admin_notice('success', 'Settings saved successfully.');
                }
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
            do_action('wptag_services_updated', $enabled_services);
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
        
        $service_key = sanitize_text_field($_POST['service'] ?? '');
        $use_template = ($_POST['use_template'] ?? '0') === '1';
        
        if (empty($service_key)) {
            wp_send_json_error(array('message' => 'Service key is required.'));
            return;
        }
        
        $settings = array(
            'enabled' => true,
            'use_template' => $use_template
        );
        
        if ($use_template) {
            $service_config = $this->config->get_service_config($service_key);
            if ($service_config) {
                $field_key = $service_config['field'];
                $settings[$field_key] = sanitize_text_field($_POST['id_value'] ?? '');
            }
        } else {
            $raw_custom_code = $_POST['custom_code'] ?? '';
            $settings['custom_code'] = $this->sanitize_custom_code_for_validation($raw_custom_code);
        }
        
        $is_valid = $this->validator->validate_service_code($service_key, $settings);
        
        if ($is_valid) {
            wp_send_json_success(array('message' => 'Code is valid and ready to use.'));
        } else {
            $errors = $this->validator->get_error_messages();
            wp_send_json_error(array('message' => implode(', ', $errors)));
        }
    }

    private function sanitize_custom_code_for_validation($custom_code) {
        if (empty($custom_code)) {
            return '';
        }
        
        $custom_code = stripslashes($custom_code);
        $custom_code = wp_check_invalid_utf8($custom_code);
        
        return trim($custom_code);
    }

    public function ajax_preview_code() {
        check_ajax_referer('wptag_admin_nonce', 'nonce');
        
        if (!$this->current_user_can_manage_codes()) {
            wp_send_json_error(array('message' => 'You do not have permission to preview codes.'));
            return;
        }
        
        $service_key = sanitize_text_field($_POST['service'] ?? '');
        $id_value = sanitize_text_field($_POST['id_value'] ?? '');
        
        if (empty($service_key)) {
            wp_send_json_error(array('message' => 'Service key is required.'));
            return;
        }
        
        if (empty($id_value)) {
            wp_send_json_error(array('message' => 'ID value is required for preview.'));
            return;
        }
        
        $preview = $this->output_manager->get_template_preview($service_key, $id_value);
        
        if (!empty($preview)) {
            $safe_preview = $this->sanitize_preview_output($preview);
            wp_send_json_success(array('preview' => $safe_preview));
        } else {
            wp_send_json_error(array('message' => 'Unable to generate preview. Please check your ID format.'));
        }
    }

    private function sanitize_preview_output($preview) {
        $preview = htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');
        
        $preview = str_replace(array('&lt;script&gt;', '&lt;/script&gt;'), array('<script>', '</script>'), $preview);
        $preview = str_replace(array('&lt;noscript&gt;', '&lt;/noscript&gt;'), array('<noscript>', '</noscript>'), $preview);
        
        return $preview;
    }

    public function ajax_export_settings() {
        check_ajax_referer('wptag_admin_nonce', 'nonce');
        
        if (!$this->current_user_can_manage_codes()) {
            wp_send_json_error(array('message' => 'You do not have permission to export settings.'));
            return;
        }
        
        try {
            $export_data = $this->config->export_settings();
            
            wp_send_json_success(array(
                'data' => $export_data,
                'filename' => 'wptag-settings-' . date('Y-m-d-H-i-s') . '.json'
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Export failed: ' . $e->getMessage()));
        }
    }

    public function ajax_import_settings() {
        check_ajax_referer('wptag_admin_nonce', 'nonce');
        
        if (!$this->current_user_can_manage_codes()) {
            wp_send_json_error(array('message' => 'You do not have permission to import settings.'));
            return;
        }
        
        $import_data = wp_unslash($_POST['import_data'] ?? '');
        
        if (empty($import_data)) {
            wp_send_json_error(array('message' => 'Import data is required.'));
            return;
        }
        
        if ($this->validator->validate_import_data($import_data)) {
            $result = $this->config->import_settings($import_data);
            
            if (!is_wp_error($result)) {
                do_action('wptag_settings_imported');
                wp_send_json_success(array('message' => 'Settings imported successfully'));
            } else {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
        } else {
            $errors = $this->validator->get_error_messages();
            wp_send_json_error(array('message' => 'Import validation failed: ' . implode(', ', $errors)));
        }
    }

    public function ajax_reset_settings() {
        check_ajax_referer('wptag_admin_nonce', 'nonce');
        
        if (!$this->current_user_can_manage_codes()) {
            wp_send_json_error(array('message' => 'You do not have permission to reset settings.'));
            return;
        }
        
        try {
            $this->config->reset_to_defaults();
            do_action('wptag_settings_reset');
            wp_send_json_success(array('message' => 'Settings reset successfully'));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Reset failed: ' . $e->getMessage()));
        }
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

    private function get_template_help_text($service_key, $service_config) {
        return '';
    }

    private function get_service_help_text($service_key, $service_config) {
        return '';
    }

    private function get_service_docs_url($service_key) {
        $service_docs_mapping = array(
            'google_analytics' => 'google-analytics',
            'google_tag_manager' => 'google-tag-manager',
            'facebook_pixel' => 'facebook-pixel',
            'google_ads' => 'google-ads',
            'microsoft_clarity' => 'microsoft-clarity',
            'hotjar' => 'hotjar',
            'tiktok_pixel' => 'tiktok-pixel',
            'linkedin_insight' => 'linkedin-insight',
            'twitter_pixel' => 'twitter-pixel',
            'pinterest_pixel' => 'pinterest-pixel',
            'snapchat_pixel' => 'snapchat-pixel',
            'google_optimize' => 'google-optimize',
            'crazyegg' => 'crazy-egg',
            'mixpanel' => 'mixpanel',
            'amplitude' => 'amplitude',
            'matomo' => 'matomo'
        );

        $docs_slug = isset($service_docs_mapping[$service_key]) ? $service_docs_mapping[$service_key] : $service_key;
        return 'https://wptag.com/document/' . $docs_slug . '/';
    }
}