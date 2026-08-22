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
$savedPluginRules = PluginsManagerAdmin::getAllRules();
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

    <section class="wpacu-lite-pm-overview" aria-labelledby="wpacuLitePmOverviewTitle">
        <div class="wpacu-lite-pm-overview-copy">
            <span class="wpacu-lite-pm-eyebrow"><?php esc_html_e('Interactive Pro preview', 'wp-asset-clean-up'); ?></span>
            <h2 id="wpacuLitePmOverviewTitle"><?php echo $isDashboardPreview
                    ? esc_html__('Control plugin execution on selected Dashboard requests', 'wp-asset-clean-up')
                    : esc_html__('Load each plugin only where its functionality is needed', 'wp-asset-clean-up'); ?></h2>
            <p><?php echo $isDashboardPreview
                    ? esc_html__('Explore how Pro can conditionally skip complete plugins on matching admin pages, while preserving explicit exceptions for trusted users and workflows.', 'wp-asset-clean-up')
                    : esc_html__('Explore how Pro can prevent a complete plugin from running on selected public requests—not only its CSS and JavaScript—and add precise exceptions where it must remain active.', 'wp-asset-clean-up'); ?></p>
        </div>

        <div class="wpacu-lite-pm-overview-stats" aria-label="<?php echo esc_attr__('Preview summary', 'wp-asset-clean-up'); ?>">
            <div><strong><?php echo (int)$totalPlugins; ?></strong><span><?php esc_html_e('active plugins', 'wp-asset-clean-up'); ?></span></div>
            <div><strong><?php echo (int)$pluginsWithSavedRules; ?></strong><span><?php esc_html_e('with preserved Pro rules', 'wp-asset-clean-up'); ?></span></div>
            <div><strong>0</strong><span><?php esc_html_e('rules executed by Lite', 'wp-asset-clean-up'); ?></span></div>
        </div>
    </section>

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
                <strong><?php echo esc_html($wpacuLitePreviewCtaLabel); ?></strong>
                <small><?php esc_html_e('View pricing and choose the right plan', 'wp-asset-clean-up'); ?></small>
            </span>
        </a>
    </section>
</div>
