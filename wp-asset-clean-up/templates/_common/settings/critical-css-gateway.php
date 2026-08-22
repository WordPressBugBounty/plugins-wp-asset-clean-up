<?php
if (! defined('ABSPATH') || ! isset($data)) {
    exit;
}

$criticalCssGlobalPaused = isset($data['critical_css_status']) && $data['critical_css_status'] === 'off';
$criticalCssRuleStats = isset($data['critical_css_rule_stats']) && is_array($data['critical_css_rule_stats'])
    ? $data['critical_css_rule_stats']
    : array();
$criticalCssSavedRules = isset($criticalCssRuleStats['total_count']) ? (int)$criticalCssRuleStats['total_count'] : 0;
$criticalCssEnabledRules = isset($criticalCssRuleStats['enabled_count']) ? (int)$criticalCssRuleStats['enabled_count'] : 0;
$manageCriticalCssUrl = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css');

if ($criticalCssSavedRules < 1) {
    $criticalCssStatusText = $criticalCssGlobalPaused ? __('Paused', 'wp-asset-clean-up') : __('Not configured', 'wp-asset-clean-up');
    $criticalCssStatusClass = $criticalCssGlobalPaused ? 'wpacu-opt-badge--warning' : '';
    $criticalCssRulesText = __('No saved rules yet', 'wp-asset-clean-up');
    $criticalCssActionText = __('Set up Critical CSS', 'wp-asset-clean-up');
} else {
    $criticalCssStatusText = $criticalCssGlobalPaused ? __('Paused', 'wp-asset-clean-up') : __('Active', 'wp-asset-clean-up');
    $criticalCssStatusClass = $criticalCssGlobalPaused ? 'wpacu-opt-badge--warning' : 'wpacu-opt-badge--on';
    $criticalCssRulesText = sprintf(
        __('%1$s saved · %2$s enabled', 'wp-asset-clean-up'),
        number_format_i18n($criticalCssSavedRules),
        number_format_i18n($criticalCssEnabledRules)
    );
    $criticalCssActionText = __('Manage Critical CSS rules', 'wp-asset-clean-up');
}
?>
<section id="wpacu-critical-css-status"
         class="wpacu-opt-critical-css-gateway"
         aria-labelledby="wpacuCriticalCssGatewayTitle">
    <span class="wpacu-opt-critical-css-gateway__icon" aria-hidden="true">
        <span class="dashicons dashicons-admin-appearance"></span>
    </span>

    <span class="wpacu-opt-critical-css-gateway__copy">
        <span class="wpacu-opt-section-kicker"><?php esc_html_e('Critical CSS', 'wp-asset-clean-up'); ?></span>
        <strong id="wpacuCriticalCssGatewayTitle"><?php esc_html_e('Critical CSS rules', 'wp-asset-clean-up'); ?></strong>
        <span><?php esc_html_e('Inline first-viewport CSS using rules configured in CSS/JS Manager. Output can be paused without deleting those rules.', 'wp-asset-clean-up'); ?></span>
    </span>

    <span class="wpacu-opt-critical-css-gateway__meta">
        <span class="wpacu-opt-badge <?php echo esc_attr($criticalCssStatusClass); ?>"><?php echo esc_html($criticalCssStatusText); ?></span>
        <span class="wpacu-opt-critical-css-gateway__count"><?php echo esc_html($criticalCssRulesText); ?></span>
    </span>

    <a class="wpacu-opt-critical-css-gateway__action" href="<?php echo esc_url($manageCriticalCssUrl); ?>">
        <?php echo esc_html($criticalCssActionText); ?>
        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
    </a>
</section>
<?php
unset(
    $criticalCssGlobalPaused,
    $criticalCssRuleStats,
    $criticalCssSavedRules,
    $criticalCssEnabledRules,
    $manageCriticalCssUrl,
    $criticalCssStatusText,
    $criticalCssStatusClass,
    $criticalCssRulesText,
    $criticalCssActionText
);
?>
