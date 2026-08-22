<?php
/*
 * No direct access to this file
 */
use WpAssetCleanUp\Admin\Overview;
use WpAssetCleanUp\Admin\OverviewEdit;

if ( ! isset($data) ) {
    exit;
}

$criticalCssOverview = isset($data['critical_css_overview']) && is_array($data['critical_css_overview'])
    ? $data['critical_css_overview']
    : array();
$locations = isset($criticalCssOverview['locations']) && is_array($criticalCssOverview['locations'])
    ? $criticalCssOverview['locations']
    : array();
$rulesCount    = isset($criticalCssOverview['rules_count']) ? (int)$criticalCssOverview['rules_count'] : 0;
$generalCount  = isset($criticalCssOverview['general_count']) ? (int)$criticalCssOverview['general_count'] : 0;
$specificCount = isset($criticalCssOverview['specific_count']) ? (int)$criticalCssOverview['specific_count'] : 0;
$isGloballyDisabled = ! empty($data['critical_css_disabled']);
$isEditMode = Overview::isEditMode();

$manageCriticalCssUrl = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css');
$criticalCssStatusUrl = admin_url(
    'admin.php?page=' . WPACU_PLUGIN_ID . '_settings'
    . '&wpacu_selected_tab_area=wpacu-setting-plugin-usage-settings'
    . '&wpacu_selected_sub_tab_area=wpacu-plugin-usage-settings-assets-management'
    . '#wpacu-cssjs-critical-css'
);

$storageTypeLabels = array(
    'option'    => __('Options', 'wp-asset-clean-up'),
    'post_meta' => __('Post meta', 'wp-asset-clean-up'),
    'term_meta' => __('Term meta', 'wp-asset-clean-up'),
    'user_meta' => __('User meta', 'wp-asset-clean-up')
);
?>
<hr style="margin: 15px 0;"/>
<h3 id="wpacu-overview-section-critical-css" class="wpacu-overview-section-title">
    <span class="dashicons dashicons-admin-appearance"></span>
    <?php esc_html_e('Critical CSS', 'wp-asset-clean-up'); ?>
    <?php
    if ($rulesCount > 0) {
        echo ' &#10230; ' . esc_html(sprintf(
            _n('Total enabled rule: %d', 'Total enabled rules: %d', $rulesCount, 'wp-asset-clean-up'),
            $rulesCount
        ));
    }
    ?>
    <a class="wpacu-overview-back-to-navigation" href="#wpacu-overview-start" aria-label="<?php esc_attr_e('Back to Overview navigation', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></a>
</h3>

<div id="wpacu-critical-css-overview">
    <?php if ($isGloballyDisabled) { ?>
        <div class="wpacu-critical-css-overview-disabled-notice">
            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
            <div>
                <strong><?php esc_html_e('Critical CSS output is paused.', 'wp-asset-clean-up'); ?></strong>
                <?php
                echo wp_kses_post(sprintf(
                    __('The enabled rules listed below remain saved, but do not take effect on the front end. Resume them in %sSettings → Plugin Usage Preferences → CSS/JS Manager → Critical CSS rule delivery%s.', 'wp-asset-clean-up'),
                    '<a target="_blank" href="' . esc_url($criticalCssStatusUrl) . '">',
                    '</a>'
                ));
                ?>
            </div>
        </div>
    <?php } ?>

    <div class="wpacu-critical-css-overview-content <?php echo $isGloballyDisabled ? 'wpacu-critical-css-overview-content-disabled' : ''; ?>">
        <?php if ( ! empty($locations) ) { ?>
            <table class="wp-list-table wpacu-overview-list-table widefat fixed striped">
                <thead>
                    <tr class="wpacu-top">
                        <td class="wpacu-critical-css-overview-location-column">
                            <strong><?php esc_html_e('Page type / group', 'wp-asset-clean-up'); ?></strong>
                        </td>
                        <td>
                            <strong><?php esc_html_e('Enabled Critical CSS rules', 'wp-asset-clean-up'); ?></strong>
                            <span class="wpacu-critical-css-overview-summary">
                                <?php
                                echo esc_html(sprintf(
                                    __('General: %1$d / Specific: %2$d', 'wp-asset-clean-up'),
                                    $generalCount,
                                    $specificCount
                                ));
                                ?>
                            </span>
                        </td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $locationData) {
                        $locationRules = isset($locationData['rules']) && is_array($locationData['rules'])
                            ? $locationData['rules']
                            : array();
                        $locationRulesCount = count($locationRules);
                        ?>
                        <tr class="wpacu_global_rule_row">
                            <td class="wpacu-critical-css-overview-location" data-wpacu-item-data="1">
                                <strong class="wpacu-critical-css-overview-location-title">
                                    <?php echo esc_html($locationData['label']); ?>
                                </strong>

                                <?php if ( ! empty($locationData['location_type_label']) && ! empty($locationData['object_key']) ) { ?>
                                    <div class="wpacu-critical-css-overview-location-meta">
                                        <?php echo esc_html($locationData['location_type_label']); ?>:
                                        <code><?php echo esc_html($locationData['object_key']); ?></code>
                                    </div>
                                <?php } ?>

                                <div class="wpacu-critical-css-overview-location-count">
                                    <?php
                                    echo esc_html(sprintf(
                                        _n('%d enabled rule', '%d enabled rules', $locationRulesCount, 'wp-asset-clean-up'),
                                        $locationRulesCount
                                    ));
                                    ?>
                                </div>
                            </td>
                            <td class="wpacu-critical-css-overview-rules" data-wpacu-item-unload-load-rules="1">
                                <?php foreach ($locationRules as $ruleData) {
                                    $isSpecific = isset($ruleData['scope']) && $ruleData['scope'] === 'specific';
                                    $storageType = isset($ruleData['storage_type']) ? $ruleData['storage_type'] : '';
                                    $showMethodLabel = isset($ruleData['show_method']) && $ruleData['show_method'] === 'minified'
                                        ? __('Minified', 'wp-asset-clean-up')
                                        : __('As entered', 'wp-asset-clean-up');

                                    $tooltipParts = array();

                                    if ( ! empty($ruleData['type_label']) ) {
                                        $tooltipParts[] = sprintf(__('Type: %s', 'wp-asset-clean-up'), $ruleData['type_label']);
                                    }

                                    if ( ! empty($ruleData['object_slug']) ) {
                                        $tooltipParts[] = sprintf(__('Slug: %s', 'wp-asset-clean-up'), rawurldecode($ruleData['object_slug']));
                                    }

                                    $titleAttribute = ! empty($tooltipParts) ? implode(', ', $tooltipParts) : '';

                                    ob_start();
                                    ?>
                                    <span class="wpacu-critical-css-overview-rule-main">
                                        <span class="wpacu-critical-css-overview-rule-title-row">
                                            <span class="wpacu-critical-css-overview-scope wpacu-critical-css-overview-scope-<?php echo esc_attr($ruleData['scope']); ?>">
                                                <?php echo esc_html($ruleData['scope_label']); ?>
                                            </span>

                                            <strong<?php echo $titleAttribute !== '' ? ' class="wpacu-tooltip" title="' . esc_attr($titleAttribute) . '"' : ''; ?>>
                                                <?php echo esc_html($ruleData['label']); ?>
                                            </strong>

                                            <?php if ($isSpecific && ! empty($ruleData['object_id'])) { ?>
                                                <code>ID: <?php echo (int)$ruleData['object_id']; ?></code>
                                            <?php } ?>

                                            <?php if ($isSpecific && ! empty($ruleData['type_label'])) { ?>
                                                <span class="wpacu-critical-css-overview-object-type">
                                                    <?php echo esc_html($ruleData['type_label']); ?>
                                                </span>
                                            <?php } ?>
                                        </span>

                                        <span class="wpacu-critical-css-overview-rule-meta">
                                            <span>
                                                <?php esc_html_e('Front-end output:', 'wp-asset-clean-up'); ?>
                                                <strong><?php echo esc_html($showMethodLabel); ?></strong>
                                            </span>
                                        </span>

                                        <?php if ( ! empty($ruleData['url']) ) { ?>
                                            <span class="wpacu-critical-css-overview-rule-url">
                                                <?php echo esc_html($ruleData['url']); ?>
                                            </span>
                                        <?php } ?>
                                    </span>
                                    <?php
                                    $ruleMainOutput = ob_get_clean();

                                    if ($isEditMode) {
                                        $deleteCheckboxLabel = sprintf(
                                            __('Mark the Critical CSS rule "%s" for deletion', 'wp-asset-clean-up'),
                                            $ruleData['label']
                                        );

                                        $ruleMainOutput = OverviewEdit::renderMaybeEditSettingChangesWrapOutputRule(
                                            '<span class="screen-reader-text">' . esc_html($deleteCheckboxLabel) . '</span>' . $ruleMainOutput,
                                            array(
                                                'critical_css' => true,
                                                'scope'        => isset($ruleData['scope']) ? $ruleData['scope'] : '',
                                                'location_key' => isset($ruleData['location_key']) ? $ruleData['location_key'] : '',
                                                'storage_type' => $storageType,
                                                'object_id'    => isset($ruleData['object_id']) ? (int)$ruleData['object_id'] : 0,
                                                'no_wrap'      => true
                                            ),
                                            'critical_css_rule',
                                            1
                                        );
                                    }
                                    ?>
                                    <div class="wpacu-critical-css-overview-rule">
                                        <?php echo $ruleMainOutput; ?>

                                        <div class="wpacu-critical-css-overview-rule-actions">
                                            <a class="button button-small"
                                               target="_blank"
                                               href="<?php echo esc_url($ruleData['edit_url']); ?>">
                                                <?php esc_html_e('Edit rule', 'wp-asset-clean-up'); ?>
                                            </a>

                                            <?php if ( ! empty($ruleData['url']) ) { ?>
                                                <a target="_blank" href="<?php echo esc_url($ruleData['url']); ?>">
                                                    <?php esc_html_e('View', 'wp-asset-clean-up'); ?>
                                                    <span class="dashicons dashicons-external" aria-hidden="true"></span>
                                                </a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } else { ?>
            <div class="wpacu-critical-css-overview-empty">
                <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                <div>
                    <strong><?php esc_html_e('There are no enabled Critical CSS rules.', 'wp-asset-clean-up'); ?></strong>
                    <span><?php esc_html_e('Rules that contain CSS but are disabled are intentionally not listed in this Overview.', 'wp-asset-clean-up'); ?></span>
                </div>
            </div>
        <?php } ?>

        <p class="wpacu-critical-css-overview-manage-link">
            <a target="_blank" href="<?php echo esc_url($manageCriticalCssUrl); ?>">
                <?php esc_html_e('Manage Critical CSS', 'wp-asset-clean-up'); ?>
                <span class="dashicons dashicons-external" aria-hidden="true"></span>
            </a>
        </p>
    </div>
</div>
