<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('wptag_settings', [
    'enable_cache' => 1,
    'cache_ttl' => 3600,
    'enable_debug' => 0,
    'cleanup_on_uninstall' => 0
]);

$cache_manager = new WPTag_Cache_Manager();
$cache_stats = $cache_manager->get_cache_stats();
?>

<div class="wrap wptag-admin-wrap">
    <div class="wptag-header">
        <h1><?php _e('WPTAG Settings', 'wptag'); ?></h1>
    </div>

    <div class="wptag-content">
        <form method="post" action="<?php echo admin_url('admin.php?page=wptag-settings'); ?>">
            <?php wp_nonce_field('wptag_save_settings'); ?>
            <input type="hidden" name="wptag_action" value="save_settings">

            <h2><?php _e('Cache Settings', 'wptag'); ?></h2>
            <table class="wptag-form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Cache', 'wptag'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_cache" value="1" <?php checked($settings['enable_cache'], 1); ?>>
                            <?php _e('Enable caching for better performance', 'wptag'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Caching stores processed snippets and conditions to improve page load times.', 'wptag'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="cache_ttl"><?php _e('Cache Duration', 'wptag'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="cache_ttl" name="cache_ttl" value="<?php echo esc_attr($settings['cache_ttl']); ?>" min="60" max="86400">
                        <span><?php _e('seconds', 'wptag'); ?></span>
                        <p class="description">
                            <?php _e('How long to keep cached data. Default is 3600 seconds (1 hour).', 'wptag'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Cache Status', 'wptag'); ?></th>
                    <td>
                        <div class="wptag-cache-stats">
                            <p>
                                <strong><?php _e('Cache Enabled:', 'wptag'); ?></strong> 
                                <?php echo $cache_stats['enabled'] ? __('Yes', 'wptag') : __('No', 'wptag'); ?>
                            </p>
                            <p>
                                <strong><?php _e('TTL:', 'wptag'); ?></strong> 
                                <?php echo number_format($cache_stats['ttl']); ?> <?php _e('seconds', 'wptag'); ?>
                            </p>
                        </div>
                        <p>
                            <button type="button" class="button" id="clear-cache">
                                <?php _e('Clear Cache Now', 'wptag'); ?>
                            </button>
                        </p>
                    </td>
                </tr>
            </table>

            <h2><?php _e('Debug Settings', 'wptag'); ?></h2>
            <table class="wptag-form-table">
                <tr>
                    <th scope="row"><?php _e('Debug Mode', 'wptag'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_debug" value="1" <?php checked($settings['enable_debug'], 1); ?>>
                            <?php _e('Enable debug mode', 'wptag'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Show additional information in HTML comments for troubleshooting.', 'wptag'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2><?php _e('Uninstall Settings', 'wptag'); ?></h2>
            <table class="wptag-form-table">
                <tr>
                    <th scope="row"><?php _e('Data Cleanup', 'wptag'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="cleanup_on_uninstall" value="1" <?php checked($settings['cleanup_on_uninstall'], 1); ?>>
                            <?php _e('Remove all data when uninstalling the plugin', 'wptag'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Warning: This will permanently delete all snippets, templates, and settings when the plugin is uninstalled.', 'wptag'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2><?php _e('Import/Export', 'wptag'); ?></h2>
            <table class="wptag-form-table">
                <tr>
                    <th scope="row"><?php _e('Export Snippets', 'wptag'); ?></th>
                    <td>
                        <p><?php _e('Export all your snippets to a JSON file for backup or migration.', 'wptag'); ?></p>
                        <button type="button" class="button" id="export-all-snippets">
                            <?php _e('Export All Snippets', 'wptag'); ?>
                        </button>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Import Snippets', 'wptag'); ?></th>
                    <td>
                        <p><?php _e('Import snippets from a JSON file.', 'wptag'); ?></p>
                        <input type="file" id="import-file" accept=".json">
                        <button type="button" class="button" id="import-snippets">
                            <?php _e('Import Snippets', 'wptag'); ?>
                        </button>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Save Settings', 'wptag'); ?>
                </button>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#clear-cache').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true);
        
        $.ajax({
            url: wptagAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wptag_clear_cache',
                nonce: wptagAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message || wptagAdmin.strings.error);
                }
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    $('#export-all-snippets').on('click', function() {
        $.ajax({
            url: wptagAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wptag_export_snippets',
                snippet_ids: [],
                nonce: wptagAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const blob = new Blob([response.data.data], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                }
            }
        });
    });

    $('#import-snippets').on('click', function() {
        const fileInput = document.getElementById('import-file');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Please select a file to import');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            $.ajax({
                url: wptagAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wptag_import_snippets',
                    import_data: e.target.result,
                    nonce: wptagAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        if (response.data.imported > 0) {
                            window.location.reload();
                        }
                    } else {
                        alert(response.data.message || wptagAdmin.strings.error);
                    }
                }
            });
        };
        reader.readAsText(file);
    });
});
</script>

<style>
.wptag-cache-stats {
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 10px 15px;
    margin-bottom: 15px;
}

.wptag-cache-stats p {
    margin: 5px 0;
}

#import-file {
    margin-right: 10px;
}
</style>
