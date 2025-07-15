<?php
if (!defined('ABSPATH')) {
    exit;
}

$action = $_GET['action'] ?? 'list';
$snippet_id = intval($_GET['snippet_id'] ?? 0);

if ($action === 'new' || ($action === 'edit' && $snippet_id)) {
    include 'snippet-form.php';
    return;
}

$current_page = intval($_GET['paged'] ?? 1);
$per_page = 20;

$args = [
    'search' => sanitize_text_field($_GET['search'] ?? ''),
    'category' => sanitize_key($_GET['filter_category'] ?? ''),
    'position' => sanitize_key($_GET['filter_position'] ?? ''),
    'status' => isset($_GET['filter_status']) ? intval($_GET['filter_status']) : null,
    'per_page' => $per_page,
    'page' => $current_page
];

$snippets = $this->snippet_manager->get_snippets($args);
$categories = $this->snippet_manager->get_categories();
$positions = $this->snippet_manager->get_positions();
?>

<div class="wrap wptag-admin-wrap">
    <div class="wptag-header">
        <h1>
            <?php _e('Code Snippets', 'wptag'); ?>
            <a href="<?php echo admin_url('admin.php?page=wptag-snippets&action=new'); ?>" class="page-title-action">
                <?php _e('Add New', 'wptag'); ?>
            </a>
        </h1>
    </div>

    <div class="wptag-content">
        <div class="wptag-filters">
            <div class="wptag-filter-item">
                <label for="filter-search"><?php _e('Search', 'wptag'); ?></label>
                <input type="text" id="filter-search" class="wptag-filter" value="<?php echo esc_attr($args['search']); ?>" placeholder="<?php esc_attr_e('Search snippets...', 'wptag'); ?>">
            </div>
            
            <div class="wptag-filter-item">
                <label for="filter-category"><?php _e('Category', 'wptag'); ?></label>
                <select id="filter-category" class="wptag-filter">
                    <option value=""><?php _e('All Categories', 'wptag'); ?></option>
                    <?php foreach ($categories as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($args['category'], $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="wptag-filter-item">
                <label for="filter-position"><?php _e('Position', 'wptag'); ?></label>
                <select id="filter-position" class="wptag-filter">
                    <option value=""><?php _e('All Positions', 'wptag'); ?></option>
                    <?php foreach ($positions as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($args['position'], $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="wptag-filter-item">
                <label for="filter-status"><?php _e('Status', 'wptag'); ?></label>
                <select id="filter-status" class="wptag-filter">
                    <option value=""><?php _e('All Status', 'wptag'); ?></option>
                    <option value="1" <?php selected($args['status'], 1); ?>><?php _e('Active', 'wptag'); ?></option>
                    <option value="0" <?php selected($args['status'], 0); ?>><?php _e('Inactive', 'wptag'); ?></option>
                </select>
            </div>
        </div>

        <?php if (empty($snippets)) : ?>
            <div class="wptag-empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                <h3><?php _e('No snippets found', 'wptag'); ?></h3>
                <p><?php _e('Get started by creating your first code snippet.', 'wptag'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=wptag-snippets&action=new'); ?>" class="button button-primary">
                    <?php _e('Create Snippet', 'wptag'); ?>
                </a>
            </div>
        <?php else : ?>
            <table class="wptag-table">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'wptag'); ?></th>
                        <th><?php _e('Position', 'wptag'); ?></th>
                        <th><?php _e('Category', 'wptag'); ?></th>
                        <th><?php _e('Priority', 'wptag'); ?></th>
                        <th><?php _e('Status', 'wptag'); ?></th>
                        <th><?php _e('Actions', 'wptag'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($snippets as $snippet) : ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=wptag-snippets&action=edit&snippet_id=' . $snippet['id']); ?>">
                                        <?php echo esc_html($snippet['name']); ?>
                                    </a>
                                </strong>
                                <?php if (!empty($snippet['description'])) : ?>
                                    <br><small><?php echo esc_html($snippet['description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($positions[$snippet['position']] ?? $snippet['position']); ?></td>
                            <td><?php echo esc_html($categories[$snippet['category']] ?? $snippet['category']); ?></td>
                            <td><?php echo esc_html($snippet['priority']); ?></td>
                            <td>
                                <span class="wptag-status-badge <?php echo $snippet['status'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $snippet['status'] ? __('Active', 'wptag') : __('Inactive', 'wptag'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="wptag-actions">
                                    <a href="<?php echo admin_url('admin.php?page=wptag-snippets&action=edit&snippet_id=' . $snippet['id']); ?>" class="wptag-action-link">
                                        <?php _e('Edit', 'wptag'); ?>
                                    </a>
                                    <a href="#" class="wptag-action-link wptag-toggle-status" data-snippet-id="<?php echo $snippet['id']; ?>">
                                        <?php echo $snippet['status'] ? __('Disable', 'wptag') : __('Enable', 'wptag'); ?>
                                    </a>
                                    <a href="#" class="wptag-action-link delete wptag-delete-snippet" data-snippet-id="<?php echo $snippet['id']; ?>">
                                        <?php _e('Delete', 'wptag'); ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
