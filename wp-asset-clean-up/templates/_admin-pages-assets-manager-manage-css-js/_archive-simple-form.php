<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}
?>
<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 0 0 15px;">
    <input type="hidden" name="page" value="<?php echo esc_attr(WPACU_PLUGIN_ID); ?>_assets_manager" />
    <input type="hidden" name="wpacu_for" value="<?php echo esc_attr($data['for']); ?>" />
    <?php if ($data['for'] === 'search') { ?>
        <?php esc_html_e('Search keyword used for the preview:', 'wp-asset-clean-up'); ?>
        <input type="text" name="wpacu_search_query" value="<?php echo esc_attr($data['archive_data']['search_query']); ?>" />
        <button type="submit" class="button button-secondary"><?php esc_html_e('Preview This Search', 'wp-asset-clean-up'); ?></button>
        <p><small><?php $wpacuAssetManagerFormContext = 'search'; require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/form-context-note.php'; ?></small></p>
    <?php } elseif ($data['for'] === 'date') { ?>
        <input type="hidden" name="wpacu_load_archive_assets" value="1" />
        <button type="submit" class="button button-secondary"><?php esc_html_e('Load CSS/JS Manager for Date Archives', 'wp-asset-clean-up'); ?></button>
        <p><small><?php esc_html_e('Asset CleanUp will use the latest valid monthly date archive available on this website as the sample URL for retrieving the loaded CSS/JS files.', 'wp-asset-clean-up'); ?></small></p>
    <?php } elseif ($data['for'] === '404_not_found') { ?>
        <input type="hidden" name="wpacu_load_archive_assets" value="1" />
        <button type="submit" class="button button-secondary"><?php esc_html_e('Load CSS/JS Manager for the 404 page', 'wp-asset-clean-up'); ?></button>
        <p><small><?php esc_html_e('Asset CleanUp will use a stable non-existing URL to trigger the active theme\'s 404 template.', 'wp-asset-clean-up'); ?></small></p>
    <?php } ?>
</form>
