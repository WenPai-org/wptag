<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$snippets_table = $wpdb->prefix . 'wptag_snippets';
$logs_table = $wpdb->prefix . 'wptag_logs';

$total_snippets = $wpdb->get_var("SELECT COUNT(*) FROM $snippets_table");
$active_snippets = $wpdb->get_var("SELECT COUNT(*) FROM $snippets_table WHERE status = 1");
$categories_count = $wpdb->get_results("SELECT category, COUNT(*) as count FROM $snippets_table GROUP BY category", ARRAY_A);

$recent_activity = $wpdb->get_results(
    "SELECT l.*, u.display_name 
    FROM $logs_table l 
    LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
    ORDER BY l.created_at DESC 
    LIMIT 10",
    ARRAY_A
);

$categories = $this->snippet_manager->get_categories();
?>

<div class="wrap wptag-admin-wrap">
    <div class="wptag-header">
        <h1><?php _e('WPTAG Dashboard', 'wptag'); ?></h1>
    </div>

    <div class="wptag-content">
        <div class="wptag-stats-grid">
            <div class="wptag-stat-card">
                <h3><?php _e('Total Snippets', 'wptag'); ?></h3>
                <div class="stat-value"><?php echo number_format($total_snippets); ?></div>
            </div>
            
            <div class="wptag-stat-card">
                <h3><?php _e('Active Snippets', 'wptag'); ?></h3>
                <div class="stat-value"><?php echo number_format($active_snippets); ?></div>
            </div>
            
            <div class="wptag-stat-card">
                <h3><?php _e('Inactive Snippets', 'wptag'); ?></h3>
                <div class="stat-value"><?php echo number_format($total_snippets - $active_snippets); ?></div>
            </div>
            
            <div class="wptag-stat-card">
                <h3><?php _e('Success Rate', 'wptag'); ?></h3>
                <div class="stat-value">
                    <?php 
                    $success_rate = $total_snippets > 0 ? round(($active_snippets / $total_snippets) * 100) : 0;
                    echo $success_rate . '%';
                    ?>
                </div>
            </div>
        </div>

        <div class="wptag-dashboard-row">
            <div class="wptag-dashboard-col">
                <h2><?php _e('Snippets by Category', 'wptag'); ?></h2>
                <table class="wptag-table">
                    <thead>
                        <tr>
                            <th><?php _e('Category', 'wptag'); ?></th>
                            <th><?php _e('Count', 'wptag'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories_count)) : ?>
                            <tr>
                                <td colspan="2"><?php _e('No snippets yet', 'wptag'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($categories_count as $cat) : ?>
                                <tr>
                                    <td><?php echo esc_html($categories[$cat['category']] ?? $cat['category']); ?></td>
                                    <td><?php echo number_format($cat['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="wptag-dashboard-col">
                <h2><?php _e('Quick Actions', 'wptag'); ?></h2>
                <div class="wptag-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=wptag-snippets&action=new'); ?>" class="button button-primary">
                        <?php _e('Create New Snippet', 'wptag'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wptag-templates'); ?>" class="button">
                        <?php _e('Browse Templates', 'wptag'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wptag-settings'); ?>" class="button">
                        <?php _e('Settings', 'wptag'); ?>
                    </a>
                </div>

                <h3><?php _e('Getting Started', 'wptag'); ?></h3>
                <ol>
                    <li><?php _e('Create a new snippet or use a template', 'wptag'); ?></li>
                    <li><?php _e('Configure where and when it should appear', 'wptag'); ?></li>
                    <li><?php _e('Activate the snippet to make it live', 'wptag'); ?></li>
                    <li><?php _e('Monitor performance and adjust as needed', 'wptag'); ?></li>
                </ol>
            </div>
        </div>

        <h2><?php _e('Recent Activity', 'wptag'); ?></h2>
        <table class="wptag-table">
            <thead>
                <tr>
                    <th><?php _e('User', 'wptag'); ?></th>
                    <th><?php _e('Action', 'wptag'); ?></th>
                    <th><?php _e('Object', 'wptag'); ?></th>
                    <th><?php _e('Date', 'wptag'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_activity)) : ?>
                    <tr>
                        <td colspan="4"><?php _e('No recent activity', 'wptag'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($recent_activity as $activity) : ?>
                        <tr>
                            <td><?php echo esc_html($activity['display_name'] ?? __('Unknown', 'wptag')); ?></td>
                            <td><?php echo esc_html(ucfirst($activity['action'])); ?></td>
                            <td>
                                <?php 
                                echo esc_html(ucfirst($activity['object_type']));
                                if ($activity['object_id']) {
                                    echo ' #' . $activity['object_id'];
                                }
                                ?>
                            </td>
                            <td><?php echo human_time_diff(strtotime($activity['created_at']), current_time('timestamp')) . ' ' . __('ago', 'wptag'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.wptag-dashboard-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin: 30px 0;
}

.wptag-dashboard-col h2 {
    margin-top: 0;
}

.wptag-quick-actions {
    display: flex;
    gap: 10px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.wptag-quick-actions .button {
    flex: 1;
    text-align: center;
    min-width: 150px;
}

@media (max-width: 782px) {
    .wptag-dashboard-row {
        grid-template-columns: 1fr;
    }
}
</style>
