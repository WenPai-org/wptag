<?php

namespace WPTag;

if (!defined('ABSPATH')) {
    exit;
}

class Loader {
    private $actions = array();
    private $filters = array();
    private $shortcodes = array();
    private $registered = false;

    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
        return $this;
    }

    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
        return $this;
    }

    public function add_shortcode($tag, $component, $callback) {
        $this->shortcodes = $this->add($this->shortcodes, $tag, $component, $callback, 10, 1);
        return $this;
    }

    private function add($hooks, $hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        if (!is_string($hook) || empty($hook)) {
            return $hooks;
        }

        if (!is_object($component) && !is_string($component)) {
            return $hooks;
        }

        if (!is_string($callback) || empty($callback)) {
            return $hooks;
        }

        if (!is_int($priority)) {
            $priority = 10;
        }

        if (!is_int($accepted_args) || $accepted_args < 1) {
            $accepted_args = 1;
        }

        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
            'registered'    => false
        );

        return $hooks;
    }

    public function run() {
        if ($this->registered) {
            return false;
        }

        $this->register_filters();
        $this->register_actions();
        $this->register_shortcodes();

        $this->registered = true;
        return true;
    }

    private function register_filters() {
        foreach ($this->filters as &$hook) {
            if ($hook['registered']) {
                continue;
            }

            if ($this->is_valid_hook($hook)) {
                $callback = $this->get_callback($hook);
                if ($callback) {
                    add_filter(
                        $hook['hook'], 
                        $callback, 
                        $hook['priority'], 
                        $hook['accepted_args']
                    );
                    $hook['registered'] = true;
                }
            }
        }
    }

    private function register_actions() {
        foreach ($this->actions as &$hook) {
            if ($hook['registered']) {
                continue;
            }

            if ($this->is_valid_hook($hook)) {
                $callback = $this->get_callback($hook);
                if ($callback) {
                    add_action(
                        $hook['hook'], 
                        $callback, 
                        $hook['priority'], 
                        $hook['accepted_args']
                    );
                    $hook['registered'] = true;
                }
            }
        }
    }

    private function register_shortcodes() {
        foreach ($this->shortcodes as &$hook) {
            if ($hook['registered']) {
                continue;
            }

            if ($this->is_valid_hook($hook)) {
                $callback = $this->get_callback($hook);
                if ($callback) {
                    add_shortcode($hook['hook'], $callback);
                    $hook['registered'] = true;
                }
            }
        }
    }

    private function is_valid_hook($hook) {
        if (!is_array($hook)) {
            return false;
        }

        $required_keys = array('hook', 'component', 'callback');
        foreach ($required_keys as $key) {
            if (!isset($hook[$key])) {
                return false;
            }
        }

        return true;
    }

    private function get_callback($hook) {
        $component = $hook['component'];
        $callback = $hook['callback'];

        if (is_object($component)) {
            if (method_exists($component, $callback)) {
                return array($component, $callback);
            }
        } elseif (is_string($component)) {
            if (class_exists($component)) {
                $instance = new $component();
                if (method_exists($instance, $callback)) {
                    return array($instance, $callback);
                }
            } elseif (function_exists($component)) {
                return $component;
            }
        }

        if (function_exists($callback)) {
            return $callback;
        }

        return false;
    }

    public function remove_action($hook, $component, $callback, $priority = 10) {
        return $this->remove_hook($this->actions, $hook, $component, $callback, $priority, 'action');
    }

    public function remove_filter($hook, $component, $callback, $priority = 10) {
        return $this->remove_hook($this->filters, $hook, $component, $callback, $priority, 'filter');
    }

    public function remove_shortcode($tag) {
        foreach ($this->shortcodes as $key => $hook) {
            if ($hook['hook'] === $tag) {
                if ($hook['registered']) {
                    remove_shortcode($tag);
                }
                unset($this->shortcodes[$key]);
                return true;
            }
        }
        return false;
    }

    private function remove_hook(&$hooks, $hook_name, $component, $callback, $priority, $type) {
        $removed = false;

        foreach ($hooks as $key => $hook) {
            if ($hook['hook'] === $hook_name && 
                $hook['component'] === $component && 
                $hook['callback'] === $callback && 
                $hook['priority'] === $priority) {
                
                if ($hook['registered']) {
                    $wp_callback = $this->get_callback($hook);
                    if ($wp_callback) {
                        if ($type === 'action') {
                            remove_action($hook_name, $wp_callback, $priority);
                        } else {
                            remove_filter($hook_name, $wp_callback, $priority);
                        }
                    }
                }
                
                unset($hooks[$key]);
                $removed = true;
            }
        }

        return $removed;
    }

    public function get_actions() {
        return $this->actions;
    }

    public function get_filters() {
        return $this->filters;
    }

    public function get_shortcodes() {
        return $this->shortcodes;
    }

    public function get_registered_actions() {
        return array_filter($this->actions, function($hook) {
            return $hook['registered'];
        });
    }

    public function get_registered_filters() {
        return array_filter($this->filters, function($hook) {
            return $hook['registered'];
        });
    }

    public function get_registered_shortcodes() {
        return array_filter($this->shortcodes, function($hook) {
            return $hook['registered'];
        });
    }

    public function is_registered() {
        return $this->registered;
    }

    public function get_hook_count() {
        return array(
            'actions' => count($this->actions),
            'filters' => count($this->filters),
            'shortcodes' => count($this->shortcodes),
            'total' => count($this->actions) + count($this->filters) + count($this->shortcodes)
        );
    }

    public function get_registered_count() {
        $registered_actions = count($this->get_registered_actions());
        $registered_filters = count($this->get_registered_filters());
        $registered_shortcodes = count($this->get_registered_shortcodes());

        return array(
            'actions' => $registered_actions,
            'filters' => $registered_filters,
            'shortcodes' => $registered_shortcodes,
            'total' => $registered_actions + $registered_filters + $registered_shortcodes
        );
    }

    public function has_hook($hook_name, $type = 'all') {
        $hooks_to_check = array();

        switch ($type) {
            case 'action':
                $hooks_to_check = $this->actions;
                break;
            case 'filter':
                $hooks_to_check = $this->filters;
                break;
            case 'shortcode':
                $hooks_to_check = $this->shortcodes;
                break;
            case 'all':
            default:
                $hooks_to_check = array_merge($this->actions, $this->filters, $this->shortcodes);
                break;
        }

        foreach ($hooks_to_check as $hook) {
            if ($hook['hook'] === $hook_name) {
                return true;
            }
        }

        return false;
    }

    public function get_hooks_by_priority($priority = 10) {
        $all_hooks = array_merge($this->actions, $this->filters, $this->shortcodes);
        
        return array_filter($all_hooks, function($hook) use ($priority) {
            return isset($hook['priority']) && $hook['priority'] === $priority;
        });
    }

    public function get_hooks_by_component($component) {
        $all_hooks = array_merge($this->actions, $this->filters, $this->shortcodes);
        
        return array_filter($all_hooks, function($hook) use ($component) {
            return $hook['component'] === $component;
        });
    }

    public function clear_all_hooks() {
        $this->unregister_all();
        
        $this->actions = array();
        $this->filters = array();
        $this->shortcodes = array();
        $this->registered = false;
        
        return true;
    }

    private function unregister_all() {
        foreach ($this->actions as $hook) {
            if ($hook['registered']) {
                $callback = $this->get_callback($hook);
                if ($callback) {
                    remove_action($hook['hook'], $callback, $hook['priority']);
                }
            }
        }

        foreach ($this->filters as $hook) {
            if ($hook['registered']) {
                $callback = $this->get_callback($hook);
                if ($callback) {
                    remove_filter($hook['hook'], $callback, $hook['priority']);
                }
            }
        }

        foreach ($this->shortcodes as $hook) {
            if ($hook['registered']) {
                remove_shortcode($hook['hook']);
            }
        }
    }

    public function debug_info() {
        if (!current_user_can('manage_options')) {
            return array();
        }

        return array(
            'registered' => $this->registered,
            'hook_counts' => $this->get_hook_count(),
            'registered_counts' => $this->get_registered_count(),
            'actions' => $this->format_hooks_for_debug($this->actions),
            'filters' => $this->format_hooks_for_debug($this->filters),
            'shortcodes' => $this->format_hooks_for_debug($this->shortcodes)
        );
    }

    private function format_hooks_for_debug($hooks) {
        $formatted = array();

        foreach ($hooks as $hook) {
            $component_name = is_object($hook['component']) ? get_class($hook['component']) : $hook['component'];
            
            $formatted[] = array(
                'hook' => $hook['hook'],
                'component' => $component_name,
                'callback' => $hook['callback'],
                'priority' => $hook['priority'],
                'accepted_args' => $hook['accepted_args'],
                'registered' => $hook['registered']
            );
        }

        return $formatted;
    }

    public function validate_hooks() {
        $issues = array();

        $all_hooks = array_merge(
            array_map(function($h) { $h['type'] = 'action'; return $h; }, $this->actions),
            array_map(function($h) { $h['type'] = 'filter'; return $h; }, $this->filters),
            array_map(function($h) { $h['type'] = 'shortcode'; return $h; }, $this->shortcodes)
        );

        foreach ($all_hooks as $index => $hook) {
            if (!$this->is_valid_hook($hook)) {
                $issues[] = array(
                    'index' => $index,
                    'type' => $hook['type'],
                    'issue' => 'Invalid hook structure',
                    'hook' => $hook
                );
                continue;
            }

            if (!$this->get_callback($hook)) {
                $issues[] = array(
                    'index' => $index,
                    'type' => $hook['type'],
                    'issue' => 'Invalid callback or component',
                    'hook' => $hook['hook'],
                    'component' => is_object($hook['component']) ? get_class($hook['component']) : $hook['component'],
                    'callback' => $hook['callback']
                );
            }

            if ($hook['priority'] < 1 || $hook['priority'] > 999) {
                $issues[] = array(
                    'index' => $index,
                    'type' => $hook['type'],
                    'issue' => 'Priority out of recommended range (1-999)',
                    'hook' => $hook['hook'],
                    'priority' => $hook['priority']
                );
            }
        }

        return $issues;
    }

    public function __destruct() {
        if ($this->registered) {
            $this->unregister_all();
        }
    }
}