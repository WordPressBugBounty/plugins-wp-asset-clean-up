<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\AssetsManagerAdmin;
use WpAssetCleanUpLite\Admin\ProPreview;

if (! isset($data)) {
    exit;
}

$archiveData = isset($data['archive_data']) ? $data['archive_data'] : AssetsManagerAdmin::getArchivePageDataFromRequest($data['for']);

if (empty($archiveData['is_valid'])) {
    return;
}

$displayUrl = $archiveData['url'];
$fetchUrl   = isset($archiveData['fetch_url']) && $archiveData['fetch_url']
    ? $archiveData['fetch_url']
    : $displayUrl;

$data['fetch_url'] = $fetchUrl;
$wpacuNoLoadInTargetPage = false;

$wpacuArchiveHeaderSection = 'notice';
require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/archive-context-header.php';
?>
<form id="wpacu_dash_assets_manager_form"
      method="post"
      action=""
      data-wpacu-lite-archive-preview="1"
      data-wpacu-lite-pro-preview-form="1">
    <input type="hidden" id="wpacu_archive_page_type" value="<?php echo esc_attr($archiveData['type']); ?>" />
    <input type="hidden" id="wpacu_ajax_fetch_assets_list_dashboard_view" value="1" />

    <?php if ($archiveData['type'] === 'taxonomy') { ?>
        <input type="hidden" id="wpacu_archive_term_id" value="<?php echo (int)$archiveData['term_id']; ?>" />
        <input type="hidden" id="wpacu_archive_taxonomy" value="<?php echo esc_attr($archiveData['taxonomy']); ?>" />
    <?php } elseif ($archiveData['type'] === 'author') { ?>
        <input type="hidden" id="wpacu_archive_author_id" value="<?php echo (int)$archiveData['author_id']; ?>" />
    <?php } elseif ($archiveData['type'] === 'custom_post_type_archive') { ?>
        <input type="hidden" id="wpacu_archive_post_type" value="<?php echo esc_attr($archiveData['post_type']); ?>" />
    <?php } ?>

    <?php $wpacuArchiveHeaderSection = 'context'; require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/archive-context-header.php'; ?>

    <?php
    $wpacuNoLoadMatchesStatus = assetCleanUpHasNoLoadMatches($data['fetch_url'], true);

    if ($wpacuNoLoadInTargetPage = in_array($wpacuNoLoadMatchesStatus, array('is_set_in_settings', 'is_set_in_page'))) {
        ?>
        <?php require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/archive-no-load-warning.php'; ?>
        <?php
    } else {
        ?>
        <?php require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/archive-fetch-status.php'; ?>
        <?php
    }
    ?>
    <div id="wpacu-update-button-area" class="no-left-margin wpacu-assets-manager-save-dock">
        <div class="wpacu-assets-manager-save-actions">
        <p class="submit">
            <a class="button button-primary"
               target="_blank"
               rel="noopener noreferrer"
               href="<?php echo esc_url(ProPreview::getUpgradeUrl('css_js_manager_' . $archiveData['type'] . '_update')); ?>">
                <img width="18" height="18" src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg'); ?>" style="margin: -2px 5px 0 0; vertical-align: middle;" alt="" />
                <?php esc_html_e('Unlock rule management with Pro', 'wp-asset-clean-up'); ?>
            </a>
        </p>
        </div>
        <div class="wpacu-assets-manager-save-copy">
            <strong><?php esc_html_e('Rule management is available in Pro', 'wp-asset-clean-up'); ?></strong>
            <span><?php esc_html_e('CSS/JS unload and load rules take effect only after they are saved.', 'wp-asset-clean-up'); ?></span>
        </div>
    </div>
</form>
