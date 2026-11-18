<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}
?>
<div class="wpacu-assets-context-picker">
<form id="wpacu-search-form-assets-manager" class="wpacu-assets-entry-search">
    <span class="wpacu-assets-search-caption"><?php esc_html_e('Load assets manager for', 'wp-asset-clean-up'); ?>:</span>
    <span class="wpacu-assets-search-input-wrap">
    <input type="text"
           class="search-field"
           value=""
           aria-describedby="wpacu-assets-search-note"
           placeholder="<?php echo esc_attr__('Type a keyword or the user ID to search the author archive', 'wp-asset-clean-up'); ?>"
           style="max-width: 800px; width: 100%; padding-right: 15px;" />
    </span>
    <small id="wpacu-assets-search-note" class="wpacu-assets-search-note"><?php $wpacuAssetManagerFormContext = 'author'; require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/form-context-note.php'; ?></small>
    <div style="display: none; padding: 10px; color: #cc0000;" id="wpacu-search-form-assets-manager-no-results"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('There are no results based on your search.', 'wp-asset-clean-up'); ?></div>
</form>

<div style="display: none;" id="wpacu-post-chosen-loading-assets">
    <img style="margin: 2px 0 4px;"
         src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/loader-horizontal.svg?x=<?php echo time(); ?>"
         align="top"
         width="120"
         alt="" />
</div>
<?php if (empty($data['archive_data']['is_valid'])) { ?>
    <div class="wpacu-archive-preview-footer">
        <?php include __DIR__ . '/_archive-pro-preview-dock.php'; ?>
    </div>
<?php } ?>
</div>
