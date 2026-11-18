<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\AssetsManagerAdmin;

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
$wpacuArchiveEditable = $archiveData['type'] === 'taxonomy'
    && \WpAssetCleanUpLite\ArchiveRules::supports($archiveData['taxonomy']);

$wpacuArchiveHeaderSection = 'notice';
require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/archive-context-header.php';
?>
<form id="wpacu_dash_assets_manager_form"
      method="post"
      action=""
      <?php if ( ! $wpacuArchiveEditable ) { ?>data-wpacu-lite-archive-preview="1" data-wpacu-lite-pro-preview-form="1"<?php } ?>>
    <?php if ($wpacuArchiveEditable) { ?>
        <input type="hidden" name="wpacu_manage_archive_page_assets" value="1" />
        <input type="hidden" name="wpacu_archive_page_type" value="taxonomy" />
        <input type="hidden" name="wpacu_archive_term_id" value="<?php echo (int)$archiveData['term_id']; ?>" />
        <input type="hidden" name="wpacu_archive_taxonomy" value="<?php echo esc_attr($archiveData['taxonomy']); ?>" />
    <?php } ?>
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
    <?php if ($wpacuArchiveEditable) {
        wp_nonce_field($data['nonce_action'], $data['nonce_name']);
        include __DIR__ . '/_save-dock.php';
    } else {
        include __DIR__ . '/_archive-pro-preview-dock.php';
    } ?>
</form>
