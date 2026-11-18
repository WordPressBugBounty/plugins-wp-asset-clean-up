<?php
use WpAssetCleanUpLite\Admin\ProPreview;

if ( ! isset($data) ) {
    exit;
}

$wpacuPreviewType = ! empty($data['archive_data']['type'])
    ? $data['archive_data']['type']
    : ($data['for'] === 'custom_taxonomies' ? 'taxonomy' : $data['for']);
?>
<div<?php if ( ! empty($data['archive_data']['is_valid']) ) { ?> id="wpacu-update-button-area"<?php } ?> class="no-left-margin wpacu-assets-manager-save-dock" style="display: flex; flex-wrap: wrap; justify-content: space-between;">
    <div class="wpacu-assets-manager-save-actions">
        <p class="submit">
            <a class="button button-primary"
               target="_blank"
               rel="noopener noreferrer"
               href="<?php echo esc_url(ProPreview::getUpgradeUrl('css_js_manager_' . $wpacuPreviewType . '_update')); ?>">
                <img width="18" height="18" src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg'); ?>" style="margin: -2px 5px 0 0; vertical-align: middle; filter: brightness(0) invert(1);" alt="" />
                <?php esc_html_e('Unlock rule management with Pro', 'wp-asset-clean-up'); ?>
            </a>
        </p>
    </div>
    <div class="wpacu-assets-manager-save-copy">
        <strong><?php esc_html_e('Rule management is available in Pro', 'wp-asset-clean-up'); ?></strong>
        <span><?php esc_html_e('CSS/JS unload and load rules take effect only after they are saved.', 'wp-asset-clean-up'); ?></span>
    </div>
</div>
