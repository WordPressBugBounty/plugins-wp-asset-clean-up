<?php
use WpAssetCleanUpLite\Admin\ProPreview;
use WpAssetCleanUpLite\Admin\UpgradeNotices;

if ( ! isset($data) ) {
    exit;
}

$wpacuPreviewType = ! empty($data['archive_data']['type'])
    ? $data['archive_data']['type']
    : ($data['for'] === 'custom_taxonomies' ? 'taxonomy' : $data['for']);

$wpacuDiscount  = UpgradeNotices::getUpgradeDiscount();
$wpacuDockLabel = __('Unlock rules with Pro', 'wp-asset-clean-up');
if ($wpacuDiscount > 0) {
    /* translators: %d is the active upgrade discount percentage. */
    $wpacuDockLabel = sprintf(__('Unlock rules with Pro · Save %d%%', 'wp-asset-clean-up'), $wpacuDiscount);
}
?>
<div<?php if ( ! empty($data['archive_data']['is_valid']) ) { ?> id="wpacu-update-button-area"<?php } ?> class="no-left-margin wpacu-assets-manager-save-dock" style="display: flex; flex-wrap: wrap; justify-content: space-between;">
    <div class="wpacu-assets-manager-save-actions">
        <p class="submit" style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px 8px;">
            <a class="button button-primary"
               target="_blank"
               rel="noopener noreferrer"
               href="<?php echo esc_url(ProPreview::getUpgradeUrl('css_js_manager_' . $wpacuPreviewType . '_update')); ?>">
                <img width="18" height="18" src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg'); ?>" style="margin: -2px 5px 0 0; vertical-align: middle; filter: brightness(0) invert(1);" alt="" />
                <?php echo esc_html($wpacuDockLabel); ?>
            </a>
            <?php if ($wpacuDiscount > 0) { ?>
                <small><?php esc_html_e('Black Friday Offer', 'wp-asset-clean-up'); ?></small>
            <?php } ?>
        </p>
    </div>
    <div class="wpacu-assets-manager-save-copy">
        <strong><?php esc_html_e('Rule management is available in Pro', 'wp-asset-clean-up'); ?></strong>
    </div>
</div>
