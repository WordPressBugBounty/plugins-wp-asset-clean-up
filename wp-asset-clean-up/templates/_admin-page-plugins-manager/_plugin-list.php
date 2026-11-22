<?php
/*
 * Shared read-only Plugins Manager presentation for Asset CleanUp Lite.
 */

use WpAssetCleanUp\Admin\PluginsManagerAdmin;
use WpAssetCleanUpLite\Admin\ProPreview;

if (
    ! isset($data, $wpacuLitePreviewLocation, $wpacuLitePreviewContext, $wpacuLitePreviewCtaLabel)
    || ! in_array($wpacuLitePreviewLocation, array('front', 'dash'), true)
) {
    exit;
}

$isDashboardPreview = $wpacuLitePreviewLocation === 'dash';
$plugins = isset($data['active_plugins']) && is_array($data['active_plugins'])
    ? $data['active_plugins']
    : array();
$savedPluginRules = isset($data['plugins_preview_rules']) && is_array($data['plugins_preview_rules'])
    ? $data['plugins_preview_rules'] : PluginsManagerAdmin::getAllRules();
$totalPlugins = count($plugins);
$pluginsWithSavedRules = 0;

foreach ($plugins as $pluginData) {
    $pluginPath = isset($pluginData['path']) ? $pluginData['path'] : '';
    $savedStatus = isset($savedPluginRules[$pluginPath]['status'])
        ? (array)$savedPluginRules[$pluginPath]['status']
        : array();

    if ( ! empty(array_filter($savedStatus))) {
        $pluginsWithSavedRules++;
    }
}
?>
<div data-wpacu-sub-page-area="<?php echo esc_attr($data['wpacu_sub_page']); ?>"
     data-wpacu-lite-pm-root="1"
     class="wpacu-wrap wpacu-lite-pm <?php echo $isDashboardPreview ? 'wpacu-lite-pm--dash' : 'wpacu-lite-pm--front'; ?>">

    <?php include __DIR__ . '/_preview-overview.php'; ?>

    <?php
    if ($isDashboardPreview) {
        include __DIR__ . '/_dash-pro-preview.php';
    } else {
        include __DIR__ . '/_front-pro-preview.php';
    }
    ?>

    <section class="wpacu-lite-pm-upgrade" aria-label="<?php echo esc_attr__('Asset CleanUp Pro upgrade benefits', 'wp-asset-clean-up'); ?>">
        <div class="wpacu-lite-pm-upgrade-copy">
            <span class="wpacu-lite-pm-eyebrow"><?php esc_html_e('Available in Asset CleanUp Pro', 'wp-asset-clean-up'); ?></span>
            <h2><?php esc_html_e('Turn this preview into request-level plugin control', 'wp-asset-clean-up'); ?></h2>
            <ul>
                <li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Skip complete plugin execution on matching requests.', 'wp-asset-clean-up'); ?></li>
                <li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Target pages, post types, taxonomies, archives, users, roles, and URI patterns.', 'wp-asset-clean-up'); ?></li>
                <li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e('Create explicit load exceptions and test changes before exposing them broadly.', 'wp-asset-clean-up'); ?></li>
            </ul>
        </div>
        <a target="_blank"
           rel="noopener noreferrer"
           class="wpacu-lite-pm-upgrade-button"
           href="<?php echo esc_url(ProPreview::getUpgradeUrl($wpacuLitePreviewContext)); ?>">
            <span class="dashicons dashicons-unlock" aria-hidden="true"></span>
            <span class="wpacu-lite-pm-upgrade-button-copy">
                <strong><?php echo esc_html(apply_filters('wpacu_internal_upgrade_label', $wpacuLitePreviewCtaLabel)); ?></strong>
                <small><?php esc_html_e('View pricing and choose the right plan', 'wp-asset-clean-up'); ?></small>
            </span>
        </a>
    </section>
</div>
