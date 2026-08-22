<?php
use WpAssetCleanUpLite\Admin\PluginsManagerPreview;

if ( ! isset($wpacuPluginView, $wpacuDashboardRoles)) {
    exit;
}

$wpacuPluginData = $wpacuPluginView['plugin_data'];
$wpacuPluginPath = $wpacuPluginView['plugin_path'];
$wpacuPluginDir = $wpacuPluginView['plugin_dir'];
$wpacuPluginAreaState = $wpacuPluginView['plugin_area_state'];
$wpacuDashboardRuleData = $wpacuPluginView['data'];
$wpacuPluginHtmlId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $wpacuPluginPath);
$wpacuPluginHtmlId = trim((string)$wpacuPluginHtmlId, '-');
$wpacuIsAlwaysLoaded = $wpacuPluginView['unload_rules_count'] === 0
    && $wpacuPluginView['load_exceptions_count'] === 0;
?>
<tr data-wpacu-layout-plugin-row
    data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>">
    <td class="wpacu_plugin_icon" width="46">
        <?php if (isset($data['plugins_icons'][$wpacuPluginDir])) { ?>
            <img width="44" height="44" alt="" src="<?php echo esc_url($data['plugins_icons'][$wpacuPluginDir]); ?>" />
        <?php } else { ?>
            <div><span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span></div>
        <?php } ?>
    </td>

    <td class="wpacu_plugin_details"
        data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
        data-wpacu-status-area="<?php echo esc_attr($wpacuPluginAreaState); ?>"
        id="wpacu-dash-manage-<?php echo esc_attr($wpacuPluginHtmlId); ?>">
        <div class="wpacu_plugin_details_top_area">
            <div class="wpacu_plugin_expand_contract_area">
                <button type="button"
                        class="wpacu_wp_button wpacu_wp_button_secondary"
                        data-wpacu-lite-preview-allow="1"
                        aria-label="<?php echo esc_attr__('Expand or contract this plugin rule area', 'wp-asset-clean-up'); ?>"
                        aria-expanded="<?php echo $wpacuPluginAreaState === 'expanded' ? 'true' : 'false'; ?>">
                    <span class="dashicons" aria-hidden="true"></span>
                </button>
            </div>

            <span class="wpacu_plugin_title" data-wpacu-plugin-search-highlight><?php echo esc_html($wpacuPluginData['title']); ?></span>
            <span class="wpacu-pm-plugin-statuses" data-wpacu-layout-plugin-statuses aria-live="polite">
                <span data-wpacu-layout-always-loaded-status<?php echo $wpacuIsAlwaysLoaded ? '' : ' hidden'; ?>><?php
                    echo PluginsManagerPreview::getAlwaysLoadedStatus();
                ?></span>
                <span class="wpacu-pm-status-badge wpacu-pm-status-badge--unload"
                      data-wpacu-layout-header-count="unload"
                      data-wpacu-count-singular="<?php echo esc_attr__('unload rule', 'wp-asset-clean-up'); ?>"
                      data-wpacu-count-plural="<?php echo esc_attr__('unload rules', 'wp-asset-clean-up'); ?>"
                    <?php echo $wpacuIsAlwaysLoaded ? 'hidden' : ''; ?>><?php
                    printf(
                        esc_html(_n('%d unload rule', '%d unload rules', $wpacuPluginView['unload_rules_count'], 'wp-asset-clean-up')),
                        (int)$wpacuPluginView['unload_rules_count']
                    );
                ?></span>
                <span class="wpacu-pm-status-badge wpacu-pm-status-badge--load"
                      data-wpacu-layout-header-count="load"
                      data-wpacu-count-singular="<?php echo esc_attr__('exception', 'wp-asset-clean-up'); ?>"
                      data-wpacu-count-plural="<?php echo esc_attr__('exceptions', 'wp-asset-clean-up'); ?>"
                    <?php echo $wpacuIsAlwaysLoaded ? 'hidden' : ''; ?>><?php
                    printf(
                        esc_html(_n('%d exception', '%d exceptions', $wpacuPluginView['load_exceptions_count'], 'wp-asset-clean-up')),
                        (int)$wpacuPluginView['load_exceptions_count']
                    );
                ?></span>
            </span>
            <span class="wpacu_plugin_path" data-wpacu-plugin-search-highlight>&nbsp;<?php echo esc_html($wpacuPluginPath); ?></span>
        </div>

        <?php if ( ! empty($wpacuPluginData['network_activated'])) { ?>
            &nbsp;<span title="<?php echo esc_attr__('Network Activated', 'wp-asset-clean-up'); ?>"
                        class="dashicons dashicons-admin-multisite wpacu-tooltip"
                        aria-label="<?php echo esc_attr__('Network Activated', 'wp-asset-clean-up'); ?>"></span>
        <?php } ?>

        <div class="wpacu_clearfix"></div>

        <?php include __DIR__ . '/_dash-pro-preview-unloads.php'; ?>

        <div class="wpacu_clearfix"></div>

        <?php include __DIR__ . '/_dash-pro-preview-load-exceptions.php'; ?>

        <div class="wpacu_clearfix"></div>
    </td>
</tr>
