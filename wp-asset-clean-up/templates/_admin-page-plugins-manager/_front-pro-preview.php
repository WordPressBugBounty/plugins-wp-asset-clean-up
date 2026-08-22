<?php
use WpAssetCleanUpLite\Admin\PluginsManagerPreview;
use WpAssetCleanUpLite\Admin\ProPreview;

if ( ! isset($data, $plugins, $savedPluginRules)) {
    exit;
}

$wpacuLitePageData = $data;
$wpacuLitePageData['rules'] = is_array($savedPluginRules) ? $savedPluginRules : array();
$wpacuLitePageData['entity_label_maps'] = PluginsManagerPreview::prepareRuleEntityLabelMaps($wpacuLitePageData['rules']);
$wpacuPluginRows = array(
    'has_unload_rules' => array(),
    'always_loaded'    => array()
);

foreach ($plugins as $wpacuPluginIndex => $wpacuPluginData) {
    $wpacuPluginView = PluginsManagerPreview::preparePluginViewData(
        $wpacuLitePageData,
        $wpacuPluginData,
        $wpacuPluginIndex
    );

    ob_start();
    include __DIR__ . '/_front-pro-preview-plugin-row.php';
    $wpacuPluginRowOutput = ob_get_clean();

    if (empty($wpacuPluginView['plugin_status'])) {
        $wpacuPluginRows['always_loaded'][] = $wpacuPluginRowOutput;
    } else {
        $wpacuPluginRows['has_unload_rules'][] = $wpacuPluginRowOutput;
    }
}

$wpacuRenderPluginGroup = static function ($groupKey, $rows, $areaKey, $singularLabel, $pluralLabel) {
    if (empty($rows)) {
        return;
    }

    $totalRows = count($rows);
    $groupClass = $groupKey === 'unload' ? 'wpacu-pm-plugin-group--unload' : 'wpacu-pm-plugin-group--loaded';
    ?>
    <section class="wpacu-pm-plugin-group <?php echo esc_attr($groupClass); ?>" data-wpacu-pm-search-group>
        <div class="wpacu_contract_expand_plugins_area wpacu-pm-plugin-group-header">
            <div class="wpacu_col_left">
                <h3>
                    <span class="dashicons dashicons-admin-plugins wpacu-pm-plugin-group-icon" aria-hidden="true"></span>
                    <span class="wpacu-pm-plugin-group-count"><?php echo (int)$totalRows; ?></span>
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
        </div>

        <table data-wpacu-area="<?php echo esc_attr($areaKey); ?>"
               class="wp-list-table wpacu-list-table widefat plugins striped wpacu-pm-plugin-table">
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
<div id="wpacu-plugins-manager-rules-ui"
     class="wpacu-pm-rules-ui wpacu-lite-pm-pro-rules-ui"
     data-wpacu-plugins-manager-rules-ui>
    <div id="wpacu-plugins-manager-layout-picker"
         class="wpacu-pm-layout-picker wpacu-lite-pm-pro-layout-picker"
         data-wpacu-layout-picker
         data-wpacu-active-layout="compact-grid">
        <div class="wpacu-pm-layout-picker-copy">
            <div class="wpacu-pm-layout-picker-heading">
                <span class="dashicons dashicons-layout" aria-hidden="true"></span>
                <strong><?php esc_html_e('Rules layout', 'wp-asset-clean-up'); ?></strong>
                <?php ProPreview::renderBadge(__('READ-ONLY', 'wp-asset-clean-up')); ?>
            </div>
            <p id="wpacu-plugins-manager-layout-description"
               class="wpacu-pm-layout-picker-description">
                <?php esc_html_e('Compact, responsive rule cards with a dedicated Site Scope area and collapsible Load Exceptions.', 'wp-asset-clean-up'); ?>
            </p>
        </div>

        <div class="wpacu-pm-layout-picker-form" data-wpacu-lite-lock-controls="1">
            <div class="wpacu-pm-layout-picker-controls">
                <label for="wpacu-plugins-manager-layout-select">
                    <?php esc_html_e('Choose layout', 'wp-asset-clean-up'); ?>
                </label>

                <select id="wpacu-plugins-manager-layout-select"
                        disabled="disabled"
                        aria-disabled="true"
                        aria-describedby="wpacu-plugins-manager-layout-description wpacu-plugins-manager-layout-storage-note">
                    <option selected="selected"><?php esc_html_e('Compact Grid — Recommended', 'wp-asset-clean-up'); ?></option>
                    <option><?php esc_html_e('Grouped Sections', 'wp-asset-clean-up'); ?></option>
                    <option><?php esc_html_e('Classic Dense', 'wp-asset-clean-up'); ?></option>
                </select>
            </div>

            <small id="wpacu-plugins-manager-layout-storage-note"
                   class="wpacu-pm-layout-picker-storage-note">
                <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                <?php esc_html_e('Saved in the plugin settings after upgrading. Your plugin rules are shared by all layouts.', 'wp-asset-clean-up'); ?>
            </small>
        </div>
    </div>

    <div data-wpacu-sub-page-area="<?php echo esc_attr($data['wpacu_sub_page']); ?>"
         data-wpacu-input-style="standard"
         data-wpacu-layout="compact-grid"
         data-wpacu-lite-lock-controls="1"
         class="wpacu-wrap wpacu-pm-layout wpacu-pm-layout--compact-grid wpacu-input-style-standard wpacu-lite-pm-pro-layout-preview"
         id="wpacu-plugins-load-manager-wrap">
        <form method="post"
              action=""
              class="wpacu_settings_form"
              data-wpacu-lite-pro-preview-form="1">
            <?php if ( ! empty($plugins)) { ?>
                <div class="wpacu-pm-search-toolbar" data-wpacu-pm-search-toolbar>
                    <div class="wpacu-pm-search-copy">
                        <strong><?php esc_html_e('Find a plugin quickly', 'wp-asset-clean-up'); ?></strong>
                        <span><?php esc_html_e('Search by plugin title or path. Matching text is underlined below.', 'wp-asset-clean-up'); ?></span>
                    </div>
                    <label class="wpacu-pm-search-control" for="wpacu-pm-plugin-search">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e('Search plugins by title or path', 'wp-asset-clean-up'); ?></span>
                        <input id="wpacu-pm-plugin-search"
                               type="search"
                               autocomplete="off"
                               data-wpacu-pm-search
                               data-wpacu-lite-preview-allow="1"
                               placeholder="<?php echo esc_attr__('Search plugins…', 'wp-asset-clean-up'); ?>" />
                    </label>
                </div>

                <?php
                $wpacuRenderPluginGroup(
                    'unload',
                    $wpacuPluginRows['has_unload_rules'],
                    'plugins-with-unload-rules',
                    __('plugin with active unload rules', 'wp-asset-clean-up'),
                    __('plugins with active unload rules', 'wp-asset-clean-up')
                );
                $wpacuRenderPluginGroup(
                    'loaded',
                    $wpacuPluginRows['always_loaded'],
                    'plugins-loaded-by-default',
                    __('plugin with no active unload rules (loaded by default)', 'wp-asset-clean-up'),
                    __('plugins with no active unload rules (loaded by default)', 'wp-asset-clean-up')
                );
                ?>

                <div class="wpacu-pm-search-empty" data-wpacu-pm-search-empty hidden>
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <strong><?php esc_html_e('No matching plugin', 'wp-asset-clean-up'); ?></strong>
                    <span><?php esc_html_e('Try another title or plugin path.', 'wp-asset-clean-up'); ?></span>
                </div>
            <?php } else { ?>
                <div class="wpacu-pm-search-empty wpacu-lite-pm-pro-empty-state">
                    <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                    <strong><?php esc_html_e('No other active plugins were detected', 'wp-asset-clean-up'); ?></strong>
                    <span><?php esc_html_e('Activate another plugin to preview its request-level controls here.', 'wp-asset-clean-up'); ?></span>
                </div>
            <?php } ?>
        </form>
    </div>
</div>
