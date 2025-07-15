<?php
if (!defined('ABSPATH')) {
    exit;
}

$selected_category = sanitize_key($_GET['category'] ?? '');
$templates = $this->template_manager->get_templates($selected_category);
$categories = $this->template_manager->get_categories();

$templates_by_category = [];
foreach ($templates as $template) {
    $cat = $template['service_category'];
    if (!isset($templates_by_category[$cat])) {
        $templates_by_category[$cat] = [];
    }
    $templates_by_category[$cat][] = $template;
}
?>

<div class="wrap wptag-admin-wrap">
    <div class="wptag-header">
        <h1><?php _e('Service Templates', 'wptag'); ?></h1>
    </div>

    <div class="wptag-content">
        <div class="wptag-notice info">
            <span class="dashicons dashicons-info"></span>
            <p><?php _e('Service templates help you quickly add popular services to your site. Simply select a template, enter your configuration details, and a snippet will be created automatically.', 'wptag'); ?></p>
        </div>

        <div class="wptag-tabs">
            <a href="<?php echo admin_url('admin.php?page=wptag-templates'); ?>" class="wptag-tab <?php echo empty($selected_category) ? 'active' : ''; ?>">
                <?php _e('All Templates', 'wptag'); ?>
            </a>
            <?php foreach ($categories as $key => $label) : ?>
                <a href="<?php echo admin_url('admin.php?page=wptag-templates&category=' . $key); ?>" 
                   class="wptag-tab <?php echo $selected_category === $key ? 'active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($templates)) : ?>
            <div class="wptag-empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <line x1="9" y1="9" x2="15" y2="9"/>
                    <line x1="9" y1="12" x2="15" y2="12"/>
                    <line x1="9" y1="15" x2="13" y2="15"/>
                </svg>
                <h3><?php _e('No templates found', 'wptag'); ?></h3>
                <p><?php _e('No templates available in this category.', 'wptag'); ?></p>
            </div>
        <?php else : ?>
            <?php foreach ($templates_by_category as $category => $cat_templates) : ?>
                <?php if ($selected_category && $selected_category !== $category) continue; ?>
                
                <h2><?php echo esc_html($categories[$category] ?? $category); ?></h2>
                
                <div class="wptag-templates-grid">
                    <?php foreach ($cat_templates as $template) : ?>
                        <div class="wptag-template-card">
                            <h3><?php echo esc_html($template['service_name']); ?></h3>
                            <p><?php echo esc_html(get_template_description($template['service_type'])); ?></p>
                            
                            <div class="template-meta">
                                <span class="template-version">v<?php echo esc_html($template['version']); ?></span>
                                <span class="template-position"><?php echo esc_html(ucfirst($template['default_position'])); ?></span>
                            </div>
                            
                            <button type="button" 
                                    class="button button-primary wptag-template-use" 
                                    data-service-type="<?php echo esc_attr($template['service_type']); ?>">
                                <?php _e('Use This Template', 'wptag'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.template-meta {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    font-size: 12px;
    color: #666;
}

.template-version,
.template-position {
    background: #f0f0f1;
    padding: 2px 8px;
    border-radius: 3px;
}
</style>

<?php
// Helper method to get template descriptions
function get_template_description($service_type) {
    $descriptions = [
        'google_analytics_4' => __('Track website traffic and user behavior with Google Analytics 4', 'wptag'),
        'google_analytics_universal' => __('Track website traffic with Universal Analytics (legacy)', 'wptag'),
        'facebook_pixel' => __('Track conversions and build audiences for Facebook ads', 'wptag'),
        'google_ads' => __('Track conversions for Google Ads campaigns', 'wptag'),
        'google_search_console' => __('Verify site ownership for Google Search Console', 'wptag'),
        'baidu_tongji' => __('Track website traffic with Baidu Analytics', 'wptag'),
        'cnzz' => __('Track website traffic with CNZZ Analytics', 'wptag'),
        '51la' => __('Track website traffic with 51.la Analytics', 'wptag'),
        'baidu_push' => __('Submit URLs to Baidu for faster indexing', 'wptag'),
        'toutiao_pixel' => __('Track conversions for Toutiao/TikTok ads', 'wptag'),
    ];
    
    return $descriptions[$service_type] ?? __('Configure and add this service to your site', 'wptag');
}
?>
