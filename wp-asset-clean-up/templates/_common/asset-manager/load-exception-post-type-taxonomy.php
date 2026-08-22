<?php
if (! defined('ABSPATH') || ! isset($data, $assetType, $assetTypeS)) {
    exit;
}

switch ($data['post_type']) {
    case 'product':
        $loadBulkTextViaTax = __('On all WooCommerce "Product" pages if these taxonomies (e.g. Product Category, Product Tag) are set', 'wp-asset-clean-up');
        break;
    case 'download':
        $loadBulkTextViaTax = __('On all Easy Digital Downloads "Download" pages if these taxonomies (e.g. Download Category, Download Tag) are set', 'wp-asset-clean-up');
        break;
    default:
        $loadBulkTextViaTax = sprintf(__('On all pages of "<strong>%s</strong>" post type if these taxonomies (e.g. Category, Tag) are set', 'wp-asset-clean-up'), $data['post_type']);
}

$wpacuLoadViaTaxIsPro = defined('WPACU_PRO_PLUGIN_VERSION');
$handleLoadViaTax = array('enable' => false, 'values' => array());

if ($wpacuLoadViaTaxIsPro && ! empty($data['handle_load_post_type_via_tax'][$assetType][$data['row']['obj']->handle])) {
    $handleLoadViaTax = $data['handle_load_post_type_via_tax'][$assetType][$data['row']['obj']->handle];
    $handleLoadViaTax['enable'] = ! empty($handleLoadViaTax['enable']);
    $handleLoadViaTax['values'] = ! empty($handleLoadViaTax['values']) ? $handleLoadViaTax['values'] : array();
}

$isLoadViaTaxEnabledWithValues = $handleLoadViaTax['enable'] && ! empty($handleLoadViaTax['values']);
if ($isLoadViaTaxEnabledWithValues) {
    $data['row']['at_least_one_rule_set'] = true;
}
?>
<li<?php if (! $wpacuLoadViaTaxIsPro) { ?> class="wpacu-locked-checkbox-option"<?php } ?>>
    <label<?php if (! $wpacuLoadViaTaxIsPro) { ?> class="wpacu-locked-checkbox-trigger"<?php } ?> for="wpacu_load_it_post_type_via_tax_option_<?php echo esc_attr($assetTypeS); ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">
        <input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
               data-handle-for="<?php echo esc_attr($assetTypeS); ?>"
               id="wpacu_load_it_post_type_via_tax_option_<?php echo esc_attr($assetTypeS); ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
               class="wpacu_load_it_post_type_via_tax_checkbox wpacu_load_exception wpacu_load_rule_input wpacu_bulk_load<?php echo $wpacuLoadViaTaxIsPro ? '' : ' wpacu_lite_locked'; ?>"
               type="checkbox"
               name="<?php echo esc_attr(WPACU_FORM_ASSETS_POST_KEY); ?>[<?php echo esc_attr($assetType); ?>][load_it_post_type_via_tax][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][enable]"
            <?php if (! $wpacuLoadViaTaxIsPro) { ?> disabled="disabled"<?php } ?>
            <?php checked($isLoadViaTaxEnabledWithValues); ?>
               value="1" />&nbsp;<span><?php echo wp_kses($loadBulkTextViaTax, array('strong' => array())); ?>:</span>
    </label>
    <?php if (! $wpacuLoadViaTaxIsPro) { ?>
        <a class="go-pro-link-no-style wpacu-locked-checkbox-overlay" href="<?php echo esc_url(apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_asset&utm_medium=load_' . $assetTypeS . '_in_post_type_via_tax_make_exception')); ?>">
            <span class="wpacu-tooltip wpacu-larger" style="left: -26px;"><?php esc_html_e('This advanced rule is available in Asset CleanUp Pro. Upgrade to apply it to matching pages or requests.', 'wp-asset-clean-up'); ?></span>
            <img style="margin: 0;" width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg'); ?>" valign="top" alt="" />
        </a>
    <?php } ?>
    <a style="text-decoration: none; color: inherit; vertical-align: middle;" target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=1415#load_exception"><span class="dashicons dashicons-editor-help"></span></a>

    <?php if ($wpacuLoadViaTaxIsPro) { ?>
        <div class="wpacu_handle_manage_post_type_via_tax_input_wrap wpacu_handle_load_post_type_via_tax_input_wrap <?php if (! $isLoadViaTaxEnabledWithValues) { echo 'wpacu_hide'; } ?>">
            <div class="wpacu_manage_via_tax_rule_area" style="min-width: 300px;">
                <select name="<?php echo esc_attr(WPACU_FORM_ASSETS_POST_KEY); ?>[<?php echo esc_attr($assetType); ?>][load_it_post_type_via_tax][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][values][]"
                        class="wpacu_post_type_via_tax_dd wpacu_load_post_type_via_tax_dd <?php if ($isLoadViaTaxEnabledWithValues && $data['plugin_settings']['input_style'] === 'enhanced') { echo ' wpacu_chosen_select '; } echo $data['plugin_settings']['input_style'] === 'enhanced' ? ' wpacu_chosen_can_be_later_enabled ' : ''; ?>"
                        data-placeholder="<?php esc_attr_e('Select taxonomies added to the post type', 'wp-asset-clean-up'); ?>..."
                        multiple="multiple"
                        data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                        data-handle-for="<?php echo esc_attr($assetTypeS); ?>"><?php if ($isLoadViaTaxEnabledWithValues) { echo \WpAssetCleanUpPro\Admin\MainAdminPro::loadDDOptionsForAllSetTermsForPostType($data['post_type'], $assetType, $data['row']['obj']->handle, $handleLoadViaTax['values'], 'load_exception'); } ?></select>
            </div>
        </div>
        <?php if (! $isLoadViaTaxEnabledWithValues) { ?>
            <div data-wpacu-tax-terms-options-loader="1" style="display: none; margin: 10px 0 10px;">
                <img src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/loader-horizontal.svg'); ?>" align="top" width="90" alt="" />
            </div>
        <?php } ?>
    <?php } ?>
</li>
<?php unset($handleLoadViaTax, $isLoadViaTaxEnabledWithValues, $loadBulkTextViaTax, $wpacuLoadViaTaxIsPro); ?>
