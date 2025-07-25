<?php

namespace WPTag;

if (!defined('ABSPATH')) {
    exit;
}

class Output_Manager {
    private $config;
    private $templates = array();
    private $cached_codes = array();

    public function __construct($config) {
        $this->config = $config;
        $this->init_templates();
    }

    private function init_templates() {
        $this->templates = array(
            'google_analytics' => array(
                'G-' => '<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={ID}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag("js", new Date());
  gtag("config", "{ID}");
</script>',
                'UA-' => '<!-- Universal Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id={ID}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag("js", new Date());
  gtag("config", "{ID}");
</script>'
            ),
            'google_tag_manager' => array(
                'head' => '<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":
new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src=
"https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,"script","dataLayer","{ID}");</script>',
                'body' => '<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={ID}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>'
            ),
            'facebook_pixel' => '<!-- Facebook Pixel -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,"script",
"https://connect.facebook.net/en_US/fbevents.js");
fbq("init", "{ID}");
fbq("track", "PageView");
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id={ID}&ev=PageView&noscript=1"
/></noscript>',
            'google_ads' => '<!-- Google Ads -->
<script async src="https://www.googletagmanager.com/gtag/js?id={ID}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag("js", new Date());
  gtag("config", "{ID}");
</script>',
            'microsoft_clarity' => '<!-- Microsoft Clarity -->
<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "{ID}");
</script>',
            'hotjar' => '<!-- Hotjar -->
<script>
    (function(h,o,t,j,a,r){
        h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
        h._hjSettings={hjid:{ID},hjsv:6};
        a=o.getElementsByTagName("head")[0];
        r=o.createElement("script");r.async=1;
        r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
        a.appendChild(r);
    })(window,document,"https://static.hotjar.com/c/hotjar-",".js?sv=");
</script>',
            'tiktok_pixel' => '<!-- TikTok Pixel -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
  ttq.load("{ID}");
  ttq.page();
}(window, document, "ttq");
</script>',
            'linkedin_insight' => '<!-- LinkedIn Insight -->
<script type="text/javascript">
_linkedin_partner_id = "{ID}";
window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
window._linkedin_data_partner_ids.push(_linkedin_partner_id);
</script><script type="text/javascript">
(function(){var s = document.getElementsByTagName("script")[0];
var b = document.createElement("script");
b.type = "text/javascript";b.async = true;
b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js";
s.parentNode.insertBefore(b, s);})();
</script>
<noscript>
<img height="1" width="1" style="display:none;" alt="" src="https://px.ads.linkedin.com/collect/?pid={ID}&fmt=gif" />
</noscript>',
            'twitter_pixel' => '<!-- Twitter Pixel -->
<script>!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments);
},s.version="1.1",s.queue=[],u=t.createElement(n),u.async=!0,u.src="//static.ads-twitter.com/uwt.js",
a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(window,document,"script");
twq("init","{ID}");
twq("track","PageView");
</script>',
            'pinterest_pixel' => '<!-- Pinterest Pixel -->
<script>
!function(e){if(!window.pintrk){window.pintrk = function () {
window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var
n=window.pintrk;n.queue=[],n.version="3.0";var
t=document.createElement("script");t.async=!0,t.src=e;var
r=document.getElementsByTagName("script")[0];
r.parentNode.insertBefore(t,r)}}("https://s.pinimg.com/ct/core.js");
pintrk("load", "{ID}");
pintrk("page");
</script>
<noscript>
<img height="1" width="1" style="display:none;" alt=""
src="https://ct.pinterest.com/v3/?tid={ID}&event=init&noscript=1" />
</noscript>',
            'snapchat_pixel' => '<!-- Snapchat Pixel -->
<script type="text/javascript">
(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function()
{a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};
a.queue=[];var s="script";r=t.createElement(s);r.async=!0;
r.src=n;var u=t.getElementsByTagName(s)[0];
u.parentNode.insertBefore(r,u);})(window,document,
"https://sc-static.net/scevent.min.js");
snaptr("init", "{ID}");
snaptr("track", "PAGE_VIEW");
</script>',
            'google_optimize' => '<!-- Google Optimize -->
<script src="https://www.googleoptimize.com/optimize.js?id={ID}"></script>',
            'crazyegg' => '<!-- Crazy Egg -->
<script type="text/javascript">
setTimeout(function(){var a=document.createElement("script");
var b=document.getElementsByTagName("script")[0];
a.src=document.location.protocol+"//script.crazyegg.com/pages/scripts/{ID}.js?"+Math.floor(new Date().getTime()/3600000);
a.async=true;a.type="text/javascript";b.parentNode.insertBefore(a,b)}, 1);
</script>',
            'mixpanel' => '<!-- Mixpanel -->
<script type="text/javascript">(function(c,a){if(!a.__SV){var b=window;try{var d,m,j,k=b.location,f=k.hash;d=function(a,b){return(m=a.match(RegExp(b+"=([^&]*)")))?m[1]:null};f&&d(f,"state")&&(j=JSON.parse(decodeURIComponent(d(f,"state"))),"mpeditor"===j.action&&(b.sessionStorage.setItem("_mpcehash",f),history.replaceState(j.desiredHash||"",c.title,k.pathname+k.search)))}catch(n){}var l,h;window.mixpanel=a;a._i=[];a.init=function(b,d,g){function c(b,i){var a=i.split(".");2==a.length&&(b=b[a[0]],i=a[1]);b[i]=function(){b.push([i].concat(Array.prototype.slice.call(arguments,0)))}}var e=a;"undefined"!==typeof g?e=a[g]=[]:g="mixpanel";e.people=e.people||[];e.toString=function(b){var a="mixpanel";"mixpanel"!==g&&(a+="."+g);b||(a+=" (stub)");return a};e.people.toString=function(){return e.toString(1)+".people (stub)"};l="disable time_event track track_pageview track_links track_forms register register_once alias unregister identify name_tag set_config reset opt_in_tracking opt_out_tracking has_opted_in_tracking has_opted_out_tracking clear_opt_in_out_tracking people.set people.set_once people.unset people.increment people.append people.union people.track_charge people.clear_charges people.delete_user".split(" ");for(h=0;h<l.length;h++)c(e,l[h]);a._i.push([b,d,g])};a.__SV=1.2;b=c.createElement("script");b.type="text/javascript";b.async=!0;b.src="undefined"!==typeof MIXPANEL_CUSTOM_LIB_URL?MIXPANEL_CUSTOM_LIB_URL:"file:"===c.location.protocol&&"//cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js".match(/^\\/\\//)?"https://cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js":"//cdn4.mxpnl.com/libs/mixpanel-2-latest.min.js";d=c.getElementsByTagName("script")[0];d.parentNode.insertBefore(b,d)}})(document,window.mixpanel||[]);
mixpanel.init("{ID}");</script>',
            'amplitude' => '<!-- Amplitude -->
<script type="text/javascript">
(function(e,t){var n=e.amplitude||{_q:[],_iq:{}};var r=t.createElement("script")
;r.type="text/javascript"
;r.integrity="sha384-vYYnQ3LPdp/RkQjoKBTGSq0X5F73gXU3G2QopHaVDVgTmCFocxCNFh3e8bD5gOo9"
;r.crossOrigin="anonymous";r.async=true
;r.src="https://cdn.amplitude.com/libs/amplitude-8.17.0-min.gz.js"
;r.onload=function(){if(!e.amplitude.runQueuedFunctions){console.log("[Amplitude] Error: could not load SDK")}}
;var i=t.getElementsByTagName("script")[0];i.parentNode.insertBefore(r,i)
;function s(e,t){e.prototype[t]=function(){this._q.push([t].concat(Array.prototype.slice.call(arguments,0)));return this}}
var o=function(){this._q=[];return this}
;var a=["add","append","clearAll","prepend","set","setOnce","unset","preInsert","postInsert","remove"]
;for(var c=0;c<a.length;c++){s(o,a[c])} n.Identify=o;var u=function(){this._q=[]
;return this}
;var l=["setProductId","setQuantity","setPrice","setRevenueType","setEventProperties"]
;for(var p=0;p<l.length;p++){s(u,l[p])} n.Revenue=u
;var d=["init","logEvent","logRevenue","setUserId","setUserProperties","setOptOut","setVersionName","setDomain","setDeviceId","enableTracking","setGlobalUserProperties","identify","clearUserProperties","setGroup","logRevenueV2","regenerateDeviceId","groupIdentify","onInit","logEventWithTimestamp","logEventWithGroups","setSessionId","resetSessionId"]
;function v(e){function t(t){e[t]=function(){e._q.push([t].concat(Array.prototype.slice.call(arguments,0)))}}for(var n=0;n<d.length;n++){t(d[n])}}v(n);n.getInstance=function(e){e=(!e||e.length===0?"$default_instance":e).toLowerCase()
;if(!Object.prototype.hasOwnProperty.call(n._iq,e)){n._iq[e]={_q:[]};v(n._iq[e])} return n._iq[e]};e.amplitude=n})(window,document);
amplitude.getInstance().init("{ID}");
</script>',
            'matomo' => '<!-- Matomo -->
<script type="text/javascript">
  var _paq = window._paq = window._paq || [];
  _paq.push(["trackPageView"]);
  _paq.push(["enableLinkTracking"]);
  (function() {
    var u="//your-matomo-domain.com/";
    _paq.push(["setTrackerUrl", u+"matomo.php"]);
    _paq.push(["setSiteId", "{ID}"]);
    var d=document, g=d.createElement("script"), s=d.getElementsByTagName("script")[0];
    g.type="text/javascript"; g.async=true; g.src=u+"matomo.js"; s.parentNode.insertBefore(g,s);
  })();
</script>',
        );
    }

    public function get_codes_for_position($position) {
        $cache_key = $position . '_' . wp_cache_get_last_changed('wptag_codes');
        
        if (isset($this->cached_codes[$cache_key])) {
            return $this->cached_codes[$cache_key];
        }

        $codes = array();
        $all_settings = $this->config->get_settings();

        if (empty($all_settings)) {
            return array();
        }

        foreach ($all_settings as $service_key => $service_settings) {
            if (!$this->should_output_service($service_key, $service_settings, $position)) {
                continue;
            }

            $service_codes = $this->get_service_codes($service_key, $service_settings, $position);
            if (!empty($service_codes)) {
                $priority = intval($service_settings['priority'] ?? 10);
                if (!isset($codes[$priority])) {
                    $codes[$priority] = array();
                }
                
                foreach ($service_codes as $code) {
                    if (!empty(trim($code))) {
                        $codes[$priority][] = array(
                            'service' => $service_key,
                            'code' => $code,
                            'priority' => $priority
                        );
                    }
                }
            }
        }

        ksort($codes);
        
        $output_codes = array();
        foreach ($codes as $priority_codes) {
            foreach ($priority_codes as $code_data) {
                $output_codes[] = $code_data['code'];
            }
        }

        $this->cached_codes[$cache_key] = $output_codes;
        return $output_codes;
    }

    private function should_output_service($service_key, $service_settings, $position) {
        if (empty($service_settings['enabled'])) {
            return false;
        }

        if (!$this->check_position_condition($service_key, $service_settings, $position)) {
            return false;
        }

        if (!$this->check_device_condition($service_settings['device'] ?? 'all')) {
            return false;
        }

        if (!$this->check_page_conditions($service_settings['conditions'] ?? array())) {
            return false;
        }

        $service_config = $this->config->get_service_config($service_key);
        if (!$service_config) {
            return false;
        }

        if (!empty($service_settings['use_template'])) {
            $field_key = $service_config['field'];
            $id_value = trim($service_settings[$field_key] ?? '');
            if (empty($id_value)) {
                return false;
            }
        } else {
            $custom_code = trim($service_settings['custom_code'] ?? '');
            if (empty($custom_code)) {
                return false;
            }
        }

        return apply_filters('wptag_should_output_service', true, $service_key, $service_settings, $position);
    }

    private function check_position_condition($service_key, $service_settings, $position) {
        if ($service_key === 'google_tag_manager') {
            return ($position === 'head' || $position === 'body');
        }
        
        return $service_settings['position'] === $position;
    }

    private function check_device_condition($device_setting) {
        if ($device_setting === 'all') {
            return true;
        }

        $is_mobile = wp_is_mobile();
        
        if ($device_setting === 'mobile' && $is_mobile) {
            return true;
        }
        
        if ($device_setting === 'desktop' && !$is_mobile) {
            return true;
        }

        return false;
    }

    private function check_page_conditions($conditions) {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->check_single_condition($condition)) {
                return false;
            }
        }

        return true;
    }

    private function check_single_condition($condition) {
        $type = $condition['type'] ?? '';
        $value = $condition['value'] ?? '';
        $operator = $condition['operator'] ?? 'is';

        switch ($type) {
            case 'page_type':
                return $this->check_page_type_condition($value, $operator);
            case 'post_type':
                return $this->check_post_type_condition($value, $operator);
            case 'category':
                return $this->check_category_condition($value, $operator);
            case 'tag':
                return $this->check_tag_condition($value, $operator);
            case 'user_role':
                return $this->check_user_role_condition($value, $operator);
            default:
                return true;
        }
    }

    private function check_page_type_condition($value, $operator) {
        $current_page_type = $this->get_current_page_type();
        
        if ($operator === 'is') {
            return $current_page_type === $value;
        } elseif ($operator === 'is_not') {
            return $current_page_type !== $value;
        }

        return true;
    }

    private function get_current_page_type() {
        if (is_home()) return 'home';
        if (is_front_page()) return 'front_page';
        if (is_single()) return 'single';
        if (is_page()) return 'page';
        if (is_category()) return 'category';
        if (is_tag()) return 'tag';
        if (is_archive()) return 'archive';
        if (is_search()) return 'search';
        if (is_404()) return '404';
        
        return 'unknown';
    }

    private function check_post_type_condition($value, $operator) {
        $post_type = get_post_type();
        
        if ($operator === 'is') {
            return $post_type === $value;
        } elseif ($operator === 'is_not') {
            return $post_type !== $value;
        }

        return true;
    }

    private function check_category_condition($value, $operator) {
        if (is_category($value)) {
            return $operator === 'is';
        } elseif (is_single()) {
            $has_category = has_category($value);
            return $operator === 'is' ? $has_category : !$has_category;
        }

        return $operator === 'is_not';
    }

    private function check_tag_condition($value, $operator) {
        if (is_tag($value)) {
            return $operator === 'is';
        } elseif (is_single()) {
            $has_tag = has_tag($value);
            return $operator === 'is' ? $has_tag : !$has_tag;
        }

        return $operator === 'is_not';
    }

    private function check_user_role_condition($value, $operator) {
        $user = wp_get_current_user();
        $has_role = in_array($value, $user->roles);
        
        return $operator === 'is' ? $has_role : !$has_role;
    }

    private function get_service_codes($service_key, $service_settings, $position) {
        if (!empty($service_settings['use_template'])) {
            return $this->get_template_codes($service_key, $service_settings, $position);
        } else {
            $custom_code = trim($service_settings['custom_code'] ?? '');
            if (!empty($custom_code)) {
                return array($custom_code);
            }
            return array();
        }
    }

    private function get_template_codes($service_key, $service_settings, $position) {
        $service_config = $this->config->get_service_config($service_key);
        if (!$service_config) {
            return array();
        }

        $templates = $this->get_templates_for_service($service_key, $service_settings, $position);
        if (empty($templates)) {
            return array();
        }

        $field_key = $service_config['field'];
        $id_value = $service_settings[$field_key] ?? '';
        
        if (empty($id_value)) {
            return array();
        }

        $codes = array();
        foreach ($templates as $template) {
            $code = str_replace('{ID}', esc_attr($id_value), $template);
            $code = apply_filters('wptag_template_code', $code, $service_key, $service_settings, $position);
            if (!empty($code)) {
                $codes[] = $code;
            }
        }
        
        return $codes;
    }

    private function get_templates_for_service($service_key, $service_settings, $position) {
        if (!isset($this->templates[$service_key])) {
            return array();
        }

        $template_data = $this->templates[$service_key];

        if ($service_key === 'google_analytics') {
            $service_config = $this->config->get_service_config($service_key);
            $field_key = $service_config['field'];
            $id_value = $service_settings[$field_key] ?? '';
            
            if (strpos($id_value, 'G-') === 0) {
                return array($template_data['G-']);
            } elseif (strpos($id_value, 'UA-') === 0) {
                return array($template_data['UA-']);
            }
            
            return array();
        }

        if ($service_key === 'google_tag_manager') {
            if ($position === 'head' && isset($template_data['head'])) {
                return array($template_data['head']);
            } elseif ($position === 'body' && isset($template_data['body'])) {
                return array($template_data['body']);
            }
            
            return array();
        }

        if (is_array($template_data)) {
            if (isset($template_data['default'])) {
                return array($template_data['default']);
            }
            return array();
        }

        return array($template_data);
    }

    public function output_codes($position) {
        $codes = $this->get_codes_for_position($position);
        
        if (empty($codes)) {
            if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
                echo "\n<!-- WPTag Debug: No codes found for position: {$position} -->\n";
            }
            return;
        }

        $output = "\n<!-- WPTag Codes - Position: {$position} -->\n";
        
        foreach ($codes as $index => $code) {
            $output .= $code;
            if ($index < count($codes) - 1) {
                $output .= "\n";
            }
        }
        
        $output .= "\n<!-- End WPTag Codes -->\n";
        
        $final_output = apply_filters('wptag_output_codes', $output, $position, $codes);
        
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            $debug_info = sprintf(
                "\n<!-- WPTag Debug: %d codes output for position: %s -->\n",
                count($codes),
                $position
            );
            echo $debug_info;
        }
        
        echo $final_output;
    }

    public function clear_cache() {
        $this->cached_codes = array();
        wp_cache_set_last_changed('wptag_codes');
    }

    public function get_template_preview($service_key, $id_value) {
        $service_config = $this->config->get_service_config($service_key);
        if (!$service_config) {
            return '';
        }

        $fake_settings = array(
            $service_config['field'] => $id_value,
            'use_template' => true,
            'enabled' => true,
            'position' => 'head'
        );

        $templates = $this->get_templates_for_service($service_key, $fake_settings, 'head');
        if (empty($templates)) {
            return '';
        }

        $preview_parts = array();
        foreach ($templates as $template) {
            $code = str_replace('{ID}', esc_attr($id_value), $template);
            if (!empty($code)) {
                $preview_parts[] = $code;
            }
        }

        return implode("\n\n", $preview_parts);
    }

    public function get_service_stats() {
        $stats = array(
            'enabled_services' => 0,
            'total_codes' => 0,
            'by_position' => array(
                'head' => 0,
                'body' => 0,
                'footer' => 0
            ),
            'by_type' => array(
                'template' => 0,
                'custom' => 0
            )
        );

        $all_settings = $this->config->get_settings();
        
        foreach ($all_settings as $service_key => $service_settings) {
            if (!empty($service_settings['enabled'])) {
                $stats['enabled_services']++;
                $stats['total_codes']++;
                
                $position = $service_settings['position'] ?? 'head';
                if (isset($stats['by_position'][$position])) {
                    $stats['by_position'][$position]++;
                }
                
                if (!empty($service_settings['use_template'])) {
                    $stats['by_type']['template']++;
                } else {
                    $stats['by_type']['custom']++;
                }
            }
        }

        return $stats;
    }

    public function validate_template($service_key, $id_value) {
        $service_config = $this->config->get_service_config($service_key);
        if (!$service_config) {
            return false;
        }

        $fake_settings = array(
            $service_config['field'] => $id_value,
            'use_template' => true
        );

        $templates = $this->get_templates_for_service($service_key, $fake_settings, 'head');
        return !empty($templates);
    }
}