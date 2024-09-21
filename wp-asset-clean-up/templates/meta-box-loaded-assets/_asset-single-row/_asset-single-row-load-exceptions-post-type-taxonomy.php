<?php
/*
 * The file is included from /templates/meta-box-loaded-assets/_asset-single-row/_asset-single-row-load-exceptions.php
 */

if ( ! isset($data, $assetType, $assetTypeS) ) {
    exit(); // no direct access
}

switch ($data['post_type']) {
    case 'product':
        $loadBulkTextViaTax = __('On all WooCommerce "Product" pages if these taxonomies (e.g. Category, Tag) are set', 'wp-asset-clean-up');
        break;
    case 'download':
        $loadBulkTextViaTax = __('On all Easy Digital Downloads "Download" pages if these taxonomies (e.g. Category, Tag) are set', 'wp-asset-clean-up');
        break;
    default:
        $loadBulkTextViaTax = sprintf(__('On all pages of "<strong>%s</strong>" post type if these taxonomies (e.g. Category, Tag) are set', 'wp-asset-clean-up'), $data['post_type']);
}
?>
<li>
    <label for="wpacu_load_it_post_type_via_tax_option_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">
        <input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
               data-handle-for="<?php echo $assetTypeS; ?>"
               id="wpacu_load_it_post_type_via_tax_option_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
               class="wpacu_load_it_post_type_via_tax_checkbox wpacu_load_exception wpacu_load_rule_input wpacu_bulk_load wpacu_lite_locked"
               type="checkbox"
               name="<?php echo WPACU_FORM_ASSETS_POST_KEY; ?>[<?php echo $assetType; ?>][load_it_post_type_via_tax][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][enable]"
               disabled="disabled"
               value="1"/>&nbsp;<span><?php echo $loadBulkTextViaTax; ?>:</span>
    </label>
    <!-- [wpacu_lite] -->
    <a class="go-pro-link-no-style"
       href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=manage_asset&utm_medium=load_'.$assetTypeS.'_in_post_type_via_tax_make_exception'); ?>">
        <span class="wpacu-tooltip wpacu-larger" style="left: -26px;">
            <?php echo str_replace('the premium', 'the<br />premium', wp_kses(__('This feature is available in the premium version of the plugin.', 'wp-asset-clean-up' ), array('br' => array()))); ?><br/>
            <?php _e( 'Click here to upgrade to Pro', 'wp-asset-clean-up' ); ?>!
        </span>
        <img style="margin: 0;" width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" valign="top" alt=""/>
    </a>
    <!-- [/wpacu_lite] -->
    <a style="text-decoration: none; color: inherit; vertical-align: middle;" target="_blank"
       href="https://www.assetcleanup.com/docs/?p=1415#load_exception"><span
                class="dashicons dashicons-editor-help"></span></a>
</li>
