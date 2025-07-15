<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPTag_Admin_Interface {
    
    public static function render_field($field) {
        $type = $field['type'] ?? 'text';
        $name = $field['name'] ?? '';
        $label = $field['label'] ?? '';
        $value = $field['value'] ?? '';
        $options = $field['options'] ?? [];
        $description = $field['description'] ?? '';
        $required = $field['required'] ?? false;
        
        if ($label) {
            echo '<label for="' . esc_attr($name) . '">' . esc_html($label);
            if ($required) {
                echo ' <span class="required">*</span>';
            }
            echo '</label>';
        }
        
        switch ($type) {
            case 'text':
            case 'email':
            case 'url':
            case 'number':
                self::render_input_field($type, $name, $value, $required);
                break;
                
            case 'textarea':
                self::render_textarea_field($name, $value, $required);
                break;
                
            case 'select':
                self::render_select_field($name, $value, $options, $required);
                break;
                
            case 'checkbox':
                self::render_checkbox_field($name, $value, $label);
                break;
                
            case 'radio':
                self::render_radio_field($name, $value, $options);
                break;
        }
        
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }
    
    private static function render_input_field($type, $name, $value, $required) {
        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="regular-text" %s />',
            esc_attr($type),
            esc_attr($name),
            esc_attr($name),
            esc_attr($value),
            $required ? 'required' : ''
        );
    }
    
    private static function render_textarea_field($name, $value, $required) {
        printf(
            '<textarea id="%s" name="%s" rows="5" cols="50" class="large-text" %s>%s</textarea>',
            esc_attr($name),
            esc_attr($name),
            $required ? 'required' : '',
            esc_textarea($value)
        );
    }
    
    private static function render_select_field($name, $value, $options, $required) {
        printf(
            '<select id="%s" name="%s" %s>',
            esc_attr($name),
            esc_attr($name),
            $required ? 'required' : ''
        );
        
        foreach ($options as $option_value => $option_label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }
        
        echo '</select>';
    }
    
    private static function render_checkbox_field($name, $value, $label) {
        printf(
            '<label><input type="checkbox" id="%s" name="%s" value="1" %s /> %s</label>',
            esc_attr($name),
            esc_attr($name),
            checked($value, 1, false),
            esc_html($label)
        );
    }
    
    private static function render_radio_field($name, $value, $options) {
        foreach ($options as $option_value => $option_label) {
            printf(
                '<label><input type="radio" name="%s" value="%s" %s /> %s</label><br>',
                esc_attr($name),
                esc_attr($option_value),
                checked($value, $option_value, false),
                esc_html($option_label)
            );
        }
    }
    
    public static function render_modal($id, $title, $content, $footer = '') {
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="wptag-modal" style="display: none;">
            <div class="wptag-modal-content">
                <div class="wptag-modal-header">
                    <h2><?php echo esc_html($title); ?></h2>
                    <button type="button" class="wptag-modal-close" aria-label="<?php esc_attr_e('Close', 'wptag'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="wptag-modal-body">
                    <?php echo $content; ?>
                </div>
                <?php if ($footer) : ?>
                    <div class="wptag-modal-footer">
                        <?php echo $footer; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public static function render_notice($message, $type = 'info') {
        $classes = [
            'info' => 'notice-info',
            'success' => 'notice-success',
            'warning' => 'notice-warning',
            'error' => 'notice-error'
        ];
        
        $class = $classes[$type] ?? 'notice-info';
        
        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr($class),
            esc_html($message)
        );
    }
    
    public static function render_tabs($tabs, $current_tab) {
        echo '<h2 class="nav-tab-wrapper">';
        
        foreach ($tabs as $tab_key => $tab_label) {
            $class = ($tab_key === $current_tab) ? ' nav-tab-active' : '';
            printf(
                '<a href="?page=%s&tab=%s" class="nav-tab%s">%s</a>',
                esc_attr($_GET['page']),
                esc_attr($tab_key),
                esc_attr($class),
                esc_html($tab_label)
            );
        }
        
        echo '</h2>';
    }
    
    public static function render_action_buttons($actions) {
        echo '<div class="wptag-action-buttons">';
        
        foreach ($actions as $action) {
            $class = $action['primary'] ?? false ? 'button-primary' : 'button-secondary';
            $url = $action['url'] ?? '#';
            $onclick = $action['onclick'] ?? '';
            
            printf(
                '<a href="%s" class="button %s" %s>%s</a> ',
                esc_url($url),
                esc_attr($class),
                $onclick ? 'onclick="' . esc_attr($onclick) . '"' : '',
                esc_html($action['label'])
            );
        }
        
        echo '</div>';
    }
}
