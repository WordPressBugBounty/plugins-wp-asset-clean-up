<?php
use WpAssetCleanUpLite\Admin\PluginsManagerPreview;

if ( ! isset($data, $plugins, $savedPluginRules)) {
    exit;
}

$wpacuLitePageData = $data;
$wpacuLitePageData['rules'] = is_array($savedPluginRules) ? $savedPluginRules : array();
$wpacuLitePageData['plugins_contracted_list'] = PluginsManagerPreview::getPluginsContractedList('dash');
$wpacuDashboardRoles = PluginsManagerPreview::getUserRoles();
$wpacuPluginRows = array(
    'has_unload_rules' => array(),
    'always_loaded'    => array()
);

foreach ($plugins as $wpacuPluginData) {
    $wpacuPluginView = PluginsManagerPreview::prepareDashboardPluginViewData(
        $wpacuLitePageData,
        $wpacuPluginData
    );

    ob_start();
    include __DIR__ . '/_dash-pro-preview-plugin-row.php';
    $wpacuPluginRowOutput = ob_get_clean();

    // Keep the production Pro grouping behaviour: any saved Dashboard status
    // moves the plugin into the rules group, including load exceptions.
    if (empty($wpacuPluginView['plugin_status'])) {
        $wpacuPluginRows['always_loaded'][] = $wpacuPluginRowOutput;
    } else {
        $wpacuPluginRows['has_unload_rules'][] = $wpacuPluginRowOutput;
    }
}

$wpacuRenderDashboardPluginGroup = static function (
    $rows,
    $areaKey,
    $iconColor,
    $singularLabel,
    $pluralLabel
) {
    if (empty($rows)) {
        return;
    }

    $totalRows = count($rows);
    ?>
    <section class="wpacu-pm-plugin-group" data-wpacu-pm-search-group>
        <div class="wpacu_contract_expand_plugins_area">
            <div class="wpacu_col_left">
                <h3>
                    <span style="color: <?php echo esc_attr($iconColor); ?>;"
                          class="dashicons dashicons-admin-plugins"
                          aria-hidden="true"></span>
                    <span class="wpacu-pm-plugin-group-count"
                          style="color: <?php echo esc_attr($iconColor); ?>;"><?php echo (int)$totalRows; ?></span>
                    <span data-wpacu-pm-search-group-label
                          data-singular="<?php echo esc_attr($singularLabel); ?>"
                          data-plural="<?php echo esc_attr($pluralLabel); ?>"><?php
                        echo esc_html($totalRows === 1 ? $singularLabel : $pluralLabel);
                    ?></span>
                </h3>
            </div>

            <div class="wpacu_plugins_groups_change_state_area wpacu_col_right">
                <span class="wpacu-pm-lite-group-action">
                <button type="button"
                        title="<?php echo esc_attr__('Expand all plugins in this area', 'wp-asset-clean-up'); ?>"
                        data-wpacu-for-area="<?php echo esc_attr($areaKey); ?>"
                        data-wpacu-target-state="expanded"
                        data-wpacu-lite-preview-allow="1"
                        class="wpacu_plugins_contract_expand_all wpacu_plugins_expand_all wpacu_wp_button wpacu_wp_button_secondary">
                    <span class="dashicons dashicons-editor-expand" aria-hidden="true"></span>
                    <span><?php esc_html_e('Expand All', 'wp-asset-clean-up'); ?></span>
                </button>
                </span>
                <span class="wpacu-pm-lite-group-action">
                <button type="button"
                        title="<?php echo esc_attr__('Contract all plugins in this area', 'wp-asset-clean-up'); ?>"
                        data-wpacu-for-area="<?php echo esc_attr($areaKey); ?>"
                        data-wpacu-target-state="contracted"
                        data-wpacu-lite-preview-allow="1"
                        class="wpacu_plugins_contract_expand_all wpacu_plugins_contract_all wpacu_wp_button wpacu_wp_button_secondary">
                    <span class="dashicons dashicons-editor-contract" aria-hidden="true"></span>
                    <span><?php esc_html_e('Contract All', 'wp-asset-clean-up'); ?></span>
                </button>
                </span>
            </div>
            <div class="wpacu_clearfix"></div>
        </div>

        <table data-wpacu-area="<?php echo esc_attr($areaKey); ?>"
               class="wp-list-table wpacu-list-table widefat plugins striped">
            <tbody>
            <?php foreach ($rows as $rowOutput) {
                echo $rowOutput;
            } ?>
            </tbody>
        </table>
    </section>
    <?php
};
?>
<div id="wpacu-plugins-manager-dashboard-rules-ui"
     class="wpacu-pm-rules-ui wpacu-lite-pm-pro-rules-ui wpacu-lite-pm-pro-rules-ui--dash"
     data-wpacu-plugins-manager-rules-ui
     data-wpacu-plugins-manager-preview-location="dash">
    <div data-wpacu-sub-page-area="<?php echo esc_attr($data['wpacu_sub_page']); ?>"
         data-wpacu-input-style="standard"
         data-wpacu-layout="dashboard"
         data-wpacu-lite-lock-controls="1"
         class="wpacu-wrap wpacu-pm-layout wpacu-pm-layout--dash wpacu-input-style-standard wpacu-lite-pm-pro-layout-preview wpacu-lite-pm-pro-layout-preview--dash"
         id="wpacu-plugins-load-manager-wrap">
        <form method="post"
              action=""
              class="wpacu_settings_form"
              data-wpacu-lite-pro-preview-form="1">
            <?php if ( ! empty($plugins)) { ?>
                <div class="wpacu-pm-search-toolbar">
                    <label class="wpacu-pm-search-control" for="wpacu-pm-dashboard-plugin-search">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e('Search plugins by title or slug', 'wp-asset-clean-up'); ?></span>
                        <input id="wpacu-pm-dashboard-plugin-search"
                               type="search"
                               autocomplete="off"
                               data-wpacu-pm-search
                               data-wpacu-lite-preview-allow="1"
                               placeholder="<?php echo esc_attr__('Search plugins by title or slug...', 'wp-asset-clean-up'); ?>" />
                    </label>
                    <span class="wpacu-pm-search-hint"><?php esc_html_e('The matching text is underlined in plugin titles and paths.', 'wp-asset-clean-up'); ?></span>
                </div>

                <?php
                $wpacuRenderDashboardPluginGroup(
                    $wpacuPluginRows['has_unload_rules'],
                    'plugins-with-unload-rules',
                    '#c00',
                    __('plugin with active unload rules', 'wp-asset-clean-up'),
                    __('plugins with active unload rules', 'wp-asset-clean-up')
                );

                if ( ! empty($wpacuPluginRows['has_unload_rules']) && ! empty($wpacuPluginRows['always_loaded'])) {
                    ?><div class="wpacu-lite-pm-dash-groups-separator" aria-hidden="true"></div><?php
                }

                $wpacuRenderDashboardPluginGroup(
                    $wpacuPluginRows['always_loaded'],
                    'plugins-loaded-by-default',
                    'green',
                    __('plugin with no active unload rules (loaded by default)', 'wp-asset-clean-up'),
                    __('plugins with no active unload rules (loaded by default)', 'wp-asset-clean-up')
                );
                ?>

                <div class="wpacu-pm-search-empty" data-wpacu-pm-search-empty hidden>
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <strong><?php esc_html_e('No matching plugins found', 'wp-asset-clean-up'); ?></strong>
                    <span><?php esc_html_e('Try another plugin title or slug.', 'wp-asset-clean-up'); ?></span>
                </div>
            <?php } else { ?>
                <div class="wpacu-pm-search-empty wpacu-lite-pm-pro-empty-state">
                    <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                    <strong><?php esc_html_e('No other active plugins were detected', 'wp-asset-clean-up'); ?></strong>
                    <span><?php esc_html_e('Activate another plugin to preview its Dashboard request controls here.', 'wp-asset-clean-up'); ?></span>
                </div>
            <?php } ?>
        </form>
    </div>
</div>
