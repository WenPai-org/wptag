<?php

namespace WPTag;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend {
    private $config;
    private $output_manager;

    public function __construct($config) {
        $this->config = $config;
        $this->output_manager = new Output_Manager($config);
        
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_head', array($this, 'output_head_codes'), 1);
        add_action('wp_body_open', array($this, 'output_body_codes'), 1);
        add_action('wp_footer', array($this, 'output_footer_codes'), 1);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    public function output_head_codes() {
        if (!$this->should_output_codes()) {
            return;
        }
        
        $this->output_manager->output_codes('head');
    }

    public function output_body_codes() {
        if (!$this->should_output_codes()) {
            return;
        }
        
        $this->output_manager->output_codes('body');
    }

    public function output_footer_codes() {
        if (!$this->should_output_codes()) {
            return;
        }
        
        $this->output_manager->output_codes('footer');
    }

    private function should_output_codes() {
        if (is_admin()) {
            return false;
        }
        
        if (is_user_logged_in() && current_user_can('manage_options')) {
            $show_for_admin = apply_filters('wptag_show_for_admin', false);
            if (!$show_for_admin) {
                return false;
            }
        }
        
        return apply_filters('wptag_should_output_codes', true);
    }

    public function enqueue_frontend_assets() {
        do_action('wptag_enqueue_frontend_assets');
    }
}