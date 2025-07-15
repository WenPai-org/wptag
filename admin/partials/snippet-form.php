<?php
if (!defined('ABSPATH')) {
    exit;
}

$snippet = null;
$is_edit = false;

if ($action === 'edit' && $snippet_id) {
    $snippet = $this->snippet_manager->get_snippet($snippet_id);
    if (!$snippet) {
        wp_die(__('Snippet not found', 'wptag'));
    }
    $is_edit = true;
}

$categories = $this->snippet_manager->get_categories();
$positions = $this->snippet_manager->get_positions();
?>

<div class="wrap wptag-admin-wrap">
    <div class="wptag-header">
        <h1>
            <?php echo $is_edit ? __('Edit Snippet', 'wptag') : __('Add New Snippet', 'wptag'); ?>
            <a href="<?php echo admin_url('admin.php?page=wptag-snippets'); ?>" class="page-title-action">
                <?php _e('Back to Snippets', 'wptag'); ?>
            </a>
        </h1>
    </div>

    <div class="wptag-content">
        <form method="post" action="<?php echo admin_url('admin.php?page=wptag-snippets'); ?>" class="wptag-snippet-form">
            <?php wp_nonce_field($is_edit ? 'wptag_update_snippet' : 'wptag_create_snippet'); ?>
            <input type="hidden" name="wptag_action" value="<?php echo $is_edit ? 'update_snippet' : 'create_snippet'; ?>">
            <?php if ($is_edit) : ?>
                <input type="hidden" name="snippet_id" value="<?php echo $snippet_id; ?>">
            <?php endif; ?>

            <table class="wptag-form-table">
                <tr>
                    <th scope="row">
                        <label for="snippet-name"><?php _e('Name', 'wptag'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="snippet-name" name="name" value="<?php echo esc_attr($snippet['name'] ?? ''); ?>" required>
                        <p class="description"><?php _e('Give your snippet a descriptive name', 'wptag'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="snippet-description"><?php _e('Description', 'wptag'); ?></label>
                    </th>
                    <td>
                        <textarea id="snippet-description" name="description" rows="3"><?php echo esc_textarea($snippet['description'] ?? ''); ?></textarea>
                        <p class="description"><?php _e('Optional description to help you remember what this snippet does', 'wptag'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="code_type"><?php _e('Code Type', 'wptag'); ?></label>
                    </th>
                    <td>
                        <select id="code_type" name="code_type">
                            <option value="html" <?php selected($snippet['code_type'] ?? 'html', 'html'); ?>><?php _e('HTML', 'wptag'); ?></option>
                            <option value="javascript" <?php selected($snippet['code_type'] ?? '', 'javascript'); ?>><?php _e('JavaScript', 'wptag'); ?></option>
                            <option value="css" <?php selected($snippet['code_type'] ?? '', 'css'); ?>><?php _e('CSS', 'wptag'); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="snippet-code"><?php _e('Code', 'wptag'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <textarea id="snippet-code" name="code" class="wptag-code-editor" required><?php echo esc_textarea($snippet['code'] ?? ''); ?></textarea>
                        <p class="description"><?php _e('Enter your code here. It will be inserted exactly as you type it.', 'wptag'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="position"><?php _e('Position', 'wptag'); ?></label>
                    </th>
                    <td>
                        <select id="position" name="position">
                            <?php foreach ($positions as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($snippet['position'] ?? 'head', $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Where should this code be inserted?', 'wptag'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="category"><?php _e('Category', 'wptag'); ?></label>
                    </th>
                    <td>
                        <select id="category" name="category">
                            <?php foreach ($categories as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($snippet['category'] ?? 'custom', $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="priority"><?php _e('Priority', 'wptag'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="priority" name="priority" value="<?php echo esc_attr($snippet['priority'] ?? 10); ?>" min="1" max="999">
                        <p class="description"><?php _e('Lower numbers = higher priority. Default is 10.', 'wptag'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="device_type"><?php _e('Device Type', 'wptag'); ?></label>
                    </th>
                    <td>
                        <select id="device_type" name="device_type">
                            <option value="all" <?php selected($snippet['device_type'] ?? 'all', 'all'); ?>><?php _e('All Devices', 'wptag'); ?></option>
                            <option value="desktop" <?php selected($snippet['device_type'] ?? '', 'desktop'); ?>><?php _e('Desktop Only', 'wptag'); ?></option>
                            <option value="mobile" <?php selected($snippet['device_type'] ?? '', 'mobile'); ?>><?php _e('Mobile Only', 'wptag'); ?></option>
                            <option value="tablet" <?php selected($snippet['device_type'] ?? '', 'tablet'); ?>><?php _e('Tablet Only', 'wptag'); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="load_method"><?php _e('Load Method', 'wptag'); ?></label>
                    </th>
                    <td>
                        <select id="load_method" name="load_method">
                            <option value="normal" <?php selected($snippet['load_method'] ?? 'normal', 'normal'); ?>><?php _e('Normal', 'wptag'); ?></option>
                            <option value="async" <?php selected($snippet['load_method'] ?? '', 'async'); ?>><?php _e('Async', 'wptag'); ?></option>
                            <option value="defer" <?php selected($snippet['load_method'] ?? '', 'defer'); ?>><?php _e('Defer', 'wptag'); ?></option>
                        </select>
                        <p class="description"><?php _e('Only applies to JavaScript code', 'wptag'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <?php _e('Status', 'wptag'); ?>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="status" value="1" <?php checked($snippet['status'] ?? 1, 1); ?>>
                            <?php _e('Active', 'wptag'); ?>
                        </label>
                        <p class="description"><?php _e('Only active snippets will be displayed on your site', 'wptag'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <?php _e('Conditions', 'wptag'); ?>
                    </th>
                    <td>
                        <div class="wptag-conditions-builder">
                            <p><?php _e('Add conditions to control when this snippet should be displayed', 'wptag'); ?></p>
                            <div class="wptag-condition-group">
                                <div class="wptag-conditions-list">
                                    <?php if (!empty($snippet['conditions']['rules'])) : ?>
                                        <?php foreach ($snippet['conditions']['rules'] as $rule) : ?>
                                            <div class="wptag-condition-row">
                                                <select name="conditions[rules][][type]" class="condition-type">
                                                    <option value="">Select Type</option>
                                                    <option value="page_type" <?php selected($rule['type'], 'page_type'); ?>>Page Type</option>
                                                    <option value="user_status" <?php selected($rule['type'], 'user_status'); ?>>User Status</option>
                                                    <option value="device_type" <?php selected($rule['type'], 'device_type'); ?>>Device Type</option>
                                                </select>
                                                <select name="conditions[rules][][operator]" class="condition-operator">
                                                    <option value="equals" <?php selected($rule['operator'] ?? 'equals', 'equals'); ?>>Equals</option>
                                                    <option value="not_equals" <?php selected($rule['operator'] ?? '', 'not_equals'); ?>>Not Equals</option>
                                                </select>
                                                <input type="text" name="conditions[rules][][value]" class="condition-value" value="<?php echo esc_attr($rule['value'] ?? ''); ?>" placeholder="Value">
                                                <button type="button" class="button wptag-remove-condition">Remove</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button wptag-add-condition">Add Condition</button>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $is_edit ? __('Update Snippet', 'wptag') : __('Create Snippet', 'wptag'); ?>
                </button>
            </p>
        </form>
    </div>
</div>
