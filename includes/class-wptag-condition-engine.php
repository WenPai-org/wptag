<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPTag_Condition_Engine {
    private $context_cache = [];

    public function evaluate_conditions($conditions, $context = []) {
        if (empty($conditions) || !is_array($conditions)) {
            return true;
        }

        $cache_key = md5(json_encode($conditions) . json_encode($context));
        if (isset($this->context_cache[$cache_key])) {
            return $this->context_cache[$cache_key];
        }

        $result = $this->process_condition_group($conditions, $context);
        $this->context_cache[$cache_key] = $result;

        return $result;
    }

    private function process_condition_group($conditions, $context) {
        $logic = $conditions['logic'] ?? 'AND';
        $rules = $conditions['rules'] ?? [];
        
        if (empty($rules)) {
            return true;
        }

        $results = [];
        foreach ($rules as $rule) {
            if (isset($rule['rules'])) {
                $results[] = $this->process_condition_group($rule, $context);
            } else {
                $results[] = $this->evaluate_single_condition($rule, $context);
            }
        }

        if ($logic === 'OR') {
            return in_array(true, $results, true);
        } else {
            return !in_array(false, $results, true);
        }
    }

    private function evaluate_single_condition($rule, $context) {
        $type = $rule['type'] ?? '';
        $operator = $rule['operator'] ?? 'equals';
        $value = $rule['value'] ?? '';

        switch ($type) {
            case 'page_type':
                return $this->check_page_type($value, $operator);
            
            case 'user_status':
                return $this->check_user_status($value, $operator);
            
            case 'user_role':
                return $this->check_user_role($value, $operator);
            
            case 'device_type':
                return $this->check_device_type($value, $operator);
            
            case 'post_id':
                return $this->check_post_id($value, $operator);
            
            case 'category':
                return $this->check_category($value, $operator);
            
            case 'tag':
                return $this->check_tag($value, $operator);
            
            case 'url':
                return $this->check_url($value, $operator);
            
            case 'date_range':
                return $this->check_date_range($value, $operator);
            
            case 'time':
                return $this->check_time($value, $operator);
            
            case 'day_of_week':
                return $this->check_day_of_week($value, $operator);
            
            default:
                return apply_filters('wptag_custom_condition', true, $type, $value, $operator, $context);
        }
    }

    private function check_page_type($value, $operator) {
        $page_types = [
            'home' => is_home() || is_front_page(),
            'single' => is_single(),
            'page' => is_page(),
            'archive' => is_archive(),
            'category' => is_category(),
            'tag' => is_tag(),
            'search' => is_search(),
            '404' => is_404(),
            'author' => is_author(),
            'date' => is_date()
        ];

        $is_type = $page_types[$value] ?? false;

        return $operator === 'not_equals' ? !$is_type : $is_type;
    }

    private function check_user_status($value, $operator) {
        $is_logged_in = is_user_logged_in();
        
        if ($value === 'logged_in') {
            return $operator === 'not_equals' ? !$is_logged_in : $is_logged_in;
        } else {
            return $operator === 'not_equals' ? $is_logged_in : !$is_logged_in;
        }
    }

    private function check_user_role($value, $operator) {
        if (!is_user_logged_in()) {
            return $operator === 'not_equals';
        }

        $user = wp_get_current_user();
        $has_role = in_array($value, $user->roles);

        return $operator === 'not_equals' ? !$has_role : $has_role;
    }

    private function check_device_type($value, $operator) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_mobile = wp_is_mobile();
        
        $device_checks = [
            'mobile' => $is_mobile && !$this->is_tablet($user_agent),
            'tablet' => $this->is_tablet($user_agent),
            'desktop' => !$is_mobile
        ];

        $is_device = $device_checks[$value] ?? false;

        return $operator === 'not_equals' ? !$is_device : $is_device;
    }

    private function is_tablet($user_agent) {
        $tablet_patterns = '/iPad|Android.*Tablet|Tablet.*Android|Kindle|Silk|Galaxy Tab/i';
        return preg_match($tablet_patterns, $user_agent);
    }

    private function check_post_id($value, $operator) {
        $current_id = get_the_ID();
        $ids = array_map('intval', explode(',', $value));
        
        switch ($operator) {
            case 'equals':
                return in_array($current_id, $ids);
            case 'not_equals':
                return !in_array($current_id, $ids);
            case 'greater_than':
                return $current_id > $ids[0];
            case 'less_than':
                return $current_id < $ids[0];
            default:
                return false;
        }
    }

    private function check_category($value, $operator) {
        if (!is_single() && !is_category()) {
            return $operator === 'not_equals';
        }

        $categories = explode(',', $value);
        $has_category = false;

        if (is_single()) {
            foreach ($categories as $cat) {
                if (has_category($cat)) {
                    $has_category = true;
                    break;
                }
            }
        } elseif (is_category()) {
            $current_cat = get_queried_object();
            $has_category = in_array($current_cat->slug, $categories) || 
                           in_array($current_cat->term_id, $categories);
        }

        return $operator === 'not_equals' ? !$has_category : $has_category;
    }

    private function check_tag($value, $operator) {
        if (!is_single() && !is_tag()) {
            return $operator === 'not_equals';
        }

        $tags = explode(',', $value);
        $has_tag = false;

        if (is_single()) {
            foreach ($tags as $tag) {
                if (has_tag($tag)) {
                    $has_tag = true;
                    break;
                }
            }
        } elseif (is_tag()) {
            $current_tag = get_queried_object();
            $has_tag = in_array($current_tag->slug, $tags) || 
                      in_array($current_tag->term_id, $tags);
        }

        return $operator === 'not_equals' ? !$has_tag : $has_tag;
    }

    private function check_url($value, $operator) {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        
        switch ($operator) {
            case 'contains':
                return strpos($current_url, $value) !== false;
            case 'not_contains':
                return strpos($current_url, $value) === false;
            case 'equals':
                return $current_url === $value;
            case 'not_equals':
                return $current_url !== $value;
            case 'starts_with':
                return strpos($current_url, $value) === 0;
            case 'ends_with':
                return substr($current_url, -strlen($value)) === $value;
            default:
                return false;
        }
    }

    private function check_date_range($value, $operator) {
        $current_time = current_time('timestamp');
        $dates = explode('|', $value);
        
        if (count($dates) !== 2) {
            return false;
        }

        $start_date = strtotime($dates[0]);
        $end_date = strtotime($dates[1] . ' 23:59:59');

        $in_range = $current_time >= $start_date && $current_time <= $end_date;

        return $operator === 'not_in' ? !$in_range : $in_range;
    }

    private function check_time($value, $operator) {
        $current_time = current_time('H:i');
        $times = explode('|', $value);
        
        if (count($times) !== 2) {
            return false;
        }

        $in_range = $current_time >= $times[0] && $current_time <= $times[1];

        return $operator === 'not_in' ? !$in_range : $in_range;
    }

    private function check_day_of_week($value, $operator) {
        $current_day = strtolower(current_time('l'));
        $days = array_map('strtolower', explode(',', $value));
        
        $is_day = in_array($current_day, $days);

        return $operator === 'not_in' ? !$is_day : $is_day;
    }

    public function get_condition_types() {
        return [
            'page_type' => [
                'label' => __('Page Type', 'wptag'),
                'values' => [
                    'home' => __('Home Page', 'wptag'),
                    'single' => __('Single Post', 'wptag'),
                    'page' => __('Page', 'wptag'),
                    'archive' => __('Archive', 'wptag'),
                    'category' => __('Category', 'wptag'),
                    'tag' => __('Tag', 'wptag'),
                    'search' => __('Search', 'wptag'),
                    '404' => __('404 Page', 'wptag')
                ]
            ],
            'user_status' => [
                'label' => __('User Status', 'wptag'),
                'values' => [
                    'logged_in' => __('Logged In', 'wptag'),
                    'logged_out' => __('Logged Out', 'wptag')
                ]
            ],
            'device_type' => [
                'label' => __('Device Type', 'wptag'),
                'values' => [
                    'mobile' => __('Mobile', 'wptag'),
                    'tablet' => __('Tablet', 'wptag'),
                    'desktop' => __('Desktop', 'wptag')
                ]
            ]
        ];
    }
}
