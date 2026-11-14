<?php
if (! isset($data) || \WpAssetCleanUp\Main::instance()->areAssetRulesEnabled()) {
    return;
}
$cssJsRuleDeliveryUrl = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_settings&wpacu_selected_tab_area=wpacu-setting-plugin-usage-settings&wpacu_selected_sub_tab_area=wpacu-plugin-usage-settings-assets-management&wpacu_selected_sub_sub_tab_area=manage-css-js') . '#wpacu-cssjs-rule-delivery';
?>
<div class="notice notice-warning inline wpacu-cssjs-rules-paused-notice" style="border: 1px solid #f0b849; border-left-width: 4px; background: #fef8ee; padding: 1px 12px; margin: 10px 0 14px;">
    <p><strong><?php esc_html_e('CSS/JS asset rules are paused.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Your rules remain saved and can still be edited, but they do not take effect on the front end.', 'wp-asset-clean-up'); ?> <a href="<?php echo esc_url($cssJsRuleDeliveryUrl); ?>"><?php esc_html_e('Enable CSS/JS rules in Settings', 'wp-asset-clean-up'); ?></a></p>
</div>
