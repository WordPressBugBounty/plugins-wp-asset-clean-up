<?php
if (! isset($data, $cssJsThirdTab)) {
    exit;
}
$cssJsSettingsTabs    = array('manage-css-js' => __('Manage CSS/JS', 'wp-asset-clean-up'), 'critical-css' => __('Critical CSS', 'wp-asset-clean-up'), 'alternative-assets' => __('Alternative Assets', 'wp-asset-clean-up'));
$cssJsSettingsBaseUrl = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_settings&wpacu_selected_tab_area=wpacu-setting-plugin-usage-settings&wpacu_selected_sub_tab_area=wpacu-plugin-usage-settings-assets-management');
?>
<nav class="wpacu-cssjs-settings-tabs" data-wpacu-third-tabs-parent="wpacu-plugin-usage-settings-assets-management" role="tablist" aria-label="<?php esc_attr_e('CSS/JS Manager preferences', 'wp-asset-clean-up'); ?>">
    <?php foreach ($cssJsSettingsTabs as $key => $label) {
        $ruleStatusKey = $key === 'manage-css-js' ? 'css_js_manager_rules_status' : 'critical_css_status';
        $ruleInputId   = $key === 'manage-css-js' ? 'wpacu_css_js_manager_rules_status' : 'wpacu_plugin_usage_critical_css_status';
        if ($key === 'alternative-assets') {
            $ruleStatusKey = 'alternative_assets_status';
            $ruleInputId   = 'wpacu_alternative_assets_status';
        }
        $ruleTabPaused = isset($data[$ruleStatusKey]) && $data[$ruleStatusKey] === 'off';
        ?>
        <a id="wpacu-cssjs-tab-<?php echo esc_attr($key); ?>" role="tab" class="<?php echo $ruleTabPaused ? 'is-paused' : ''; ?>"
           data-wpacu-rules-input="<?php echo esc_attr($ruleInputId); ?>"
           href="<?php echo esc_url($cssJsSettingsBaseUrl . '&wpacu_selected_sub_sub_tab_area=' . $key); ?>"
           data-wpacu-cssjs-settings-tab="<?php echo esc_attr($key); ?>"
           data-wpacu-third-tab="<?php echo esc_attr($key); ?>"
           aria-controls="wpacu-cssjs-settings-<?php echo esc_attr($key); ?>"
           aria-selected="<?php echo $cssJsThirdTab === $key ? 'true' : 'false'; ?>"
           tabindex="<?php echo $cssJsThirdTab === $key ? '0' : '-1'; ?>"><span><?php echo esc_html($label); ?></span><span class="wpacu-cssjs-settings-tab-status" aria-live="polite" <?php if (!$ruleTabPaused) { echo 'hidden'; } ?>><?php esc_html_e('Paused', 'wp-asset-clean-up'); ?></span></a>
    <?php } ?>
</nav>
<script>
(function () {
    'use strict';
    function initRuleTabStatus() {
        document.querySelectorAll('#wpacu-css-js-manager-settings [data-wpacu-rules-input]').forEach(function (tab) {
            var input = document.getElementById(tab.getAttribute('data-wpacu-rules-input'));
            var status = tab.querySelector('.wpacu-cssjs-settings-tab-status');
            if (!input || !status) { return; }
            function syncStatus() {
                tab.classList.toggle('is-paused', !input.checked);
                status.hidden = input.checked;
            }
            input.addEventListener('change', syncStatus);
            syncStatus();
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initRuleTabStatus); } else { initRuleTabStatus(); }
}());
</script>
