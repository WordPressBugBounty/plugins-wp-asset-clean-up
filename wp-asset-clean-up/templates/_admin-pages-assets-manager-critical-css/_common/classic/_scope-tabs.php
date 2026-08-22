<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\CriticalCssAdmin;

if ( ! isset($data, $locationKey, $criticalCssConfig) ) {
    exit;
}

if ( ! CriticalCssAdmin::supportsGranularManagement($data['for']) ) {
    return;
}

$storageContext = isset($data['critical_css_storage']) ? $data['critical_css_storage'] : array();
$scope          = isset($data['critical_css_scope']) ? $data['critical_css_scope'] : 'general';
$specificRules  = isset($data['critical_css_specific_rules']) && is_array($data['critical_css_specific_rules'])
    ? $data['critical_css_specific_rules']
    : array();
$viewData       = isset($data['critical_css_granular_view']) && is_array($data['critical_css_granular_view'])
    ? $data['critical_css_granular_view']
    : array();

$pluralLabel        = isset($viewData['plural_label']) ? $viewData['plural_label'] : '';
$singularLabel      = isset($viewData['singular_label']) ? $viewData['singular_label'] : '';
$generalLabel       = isset($viewData['general_label']) ? $viewData['general_label'] : '';
$generalUrl         = isset($viewData['general_url']) ? $viewData['general_url'] : '';
$specificUrl        = isset($viewData['specific_url']) ? $viewData['specific_url'] : '';
$rulesCount         = isset($viewData['rules_count']) ? (int)$viewData['rules_count'] : count($specificRules);
$enabledCount       = isset($viewData['enabled_count']) ? (int)$viewData['enabled_count'] : 0;
$isSelectedObject   = ! empty($viewData['is_selected_object']);
$selectedObjectId   = isset($viewData['selected_object_id']) ? (int)$viewData['selected_object_id'] : 0;
$selectedRuleExists = ! empty($viewData['selected_rule_exists']);
$generalIsEnabled   = isset($criticalCssConfig[$locationKey]['enable']) && $criticalCssConfig[$locationKey]['enable'];
$specificIsEnabled  = $enabledCount > 0;
?>
<nav class="wpacu-nav-tab-wrapper wpacu-nav-critical-css-manager wpacu-critical-css-classic-scope-tabs"
     aria-label="<?php echo esc_attr__('Critical CSS rule scope', 'wp-asset-clean-up'); ?>">
    <a class="wpacu-nav-tab <?php echo $scope === 'general' ? 'wpacu-nav-tab-active' : ''; ?> <?php echo $generalIsEnabled ? 'wpacu-on' : 'wpacu-off'; ?>"
       <?php if ($scope === 'general') { echo 'aria-current="page"'; } ?>
       href="<?php echo esc_url($generalUrl); ?>">
        <?php esc_html_e('General', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <a class="wpacu-nav-tab <?php echo $scope === 'specific' ? 'wpacu-nav-tab-active' : ''; ?> <?php echo $specificIsEnabled ? 'wpacu-on' : 'wpacu-off'; ?>"
       <?php if ($scope === 'specific') { echo 'aria-current="page"'; } ?>
       href="<?php echo esc_url($specificUrl); ?>">
        <?php echo esc_html(sprintf(__('Specific (%d)', 'wp-asset-clean-up'), $rulesCount)); ?>
        <span class="wpacu-circle-status"></span>
    </a>
</nav>
