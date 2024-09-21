<?php
/*
 * The file is included from /templates/meta-box-loaded-assets/_asset-single-row.php
*/

if ( ! isset($data, $assetType, $assetTypeS) ) {
    exit(); // no direct access
}

// Only show it if "Unload site-wide" is NOT enabled
// Otherwise, there's no point to use an unload regex if the asset is unloaded site-wide
if (isset($data['row']['global_unloaded']) && $data['row']['global_unloaded']) {
    return;
}

if ($assetType === 'scripts') {
    if (isset($data['row']['obj']->tag_output) && strncasecmp($data['row']['obj']->tag_output, '<noscript', 9) === 0) {
        switch ( $data['post_type'] ) {
            case 'product':
                $unloadViaPostTypeTaxText = __( 'Unload NOSCRIPT tag on all WooCommerce "Product" pages if these taxonomies (e.g. Category, Tag) are set', 'wp-asset-clean-up' );
                break;
            case 'download':
                $unloadViaPostTypeTaxText = __( 'Unload NOSCRIPT tag on all Easy Digital Downloads "Download" pages if these taxonomies (e.g. Category, Tag) are set', 'wp-asset-clean-up' );
                break;
            default:
                $unloadViaPostTypeTaxText = sprintf( __( 'Unload NOSCRIPT tag on all pages of "<strong>%s</strong>" post type if these taxonomies (category, tag, etc.) are set', 'wp-asset-clean-up' ), $data['post_type'] );
        }
    } else {
        switch ( $data['post_type'] ) {
            case 'product':
                $unloadViaPostTypeTaxText = __( 'Unload JS on all WooCommerce "Product" pages if these taxonomies (e.g. Category, Tag) are set', 'wp-asset-clean-up' );
                break;
            case 'download':
                $unloadViaPostTypeTaxText = __( 'Unload JS on all Easy Digital Downloads "Download" pages if these taxonomies (e.g. Category, Tag) are set', 'wp-asset-clean-up' );
                break;
            default:
                $unloadViaPostTypeTaxText = sprintf( __( 'Unload JS on all pages of "<strong>%s</strong>" post type if these taxonomies (category, tag, etc.) are set', 'wp-asset-clean-up' ), $data['post_type'] );
        }
    }
} else {
    switch ( $data['post_type'] ) {
        case 'product':
            $unloadViaPostTypeTaxText = __( 'Unload CSS on all WooCommerce "Product" pages if these taxonomies (e.g. Category, Tag) are set', 'wp-asset-clean-up' );
            break;
        case 'download':
            $unloadViaPostTypeTaxText = __( 'Unload CSS on all Easy Digital Downloads "Download" pages if these taxonomies (e.g. Category, Tag) are set', 'wp-asset-clean-up' );
            break;
        default:
            $unloadViaPostTypeTaxText = sprintf( __( 'Unload CSS on all pages of "<strong>%s</strong>" post type if these taxonomies (category, tag, etc.) are set', 'wp-asset-clean-up' ), $data['post_type'] );
    }
}

// Unload it if the post has a certain "Category", "Tag" or other taxonomy associated with it.
?>
<!-- [wpacu_lite] -->
<div class="wpacu_asset_options_wrap wpacu_manage_via_tax_area_wrap">
    <ul class="wpacu_asset_options">
        <li>
            <?php
            if (isset($data['row']['is_hardcoded']) && $data['row']['is_hardcoded']) {
            ?>
                <label class="wpacu-manage-hardcoded-assets-requires-pro-popup">
                    <span style="color: #ccc;" class="wpacu-manage-hardcoded-assets-requires-pro-popup dashicons dashicons-lock"></span>
                    <?php echo wp_kses($unloadViaPostTypeTaxText, array('strong' => array())); ?>
                </label>
            <?php
            } else {
            ?>
                <label for="wpacu_unload_it_post_type_via_tax_option_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">
                    <input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                           data-handle-for="<?php echo $assetTypeS; ?>"
                           id="wpacu_unload_it_post_type_via_tax_option_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                           class="wpacu_unload_it_post_type_via_tax_checkbox wpacu_unload_rule_input wpacu_bulk_unload"
                           type="checkbox"
                           disabled="disabled"
                           name="<?php echo WPACU_FORM_ASSETS_POST_KEY; ?>[<?php echo $assetType; ?>][unload_post_type_via_tax][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][enable]"
                           value="1"/>&nbsp;
                    <span><?php echo wp_kses($unloadViaPostTypeTaxText, array('strong' => array())); ?>:</span>
                </label>
                <a class="go-pro-link-no-style"
                   href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=manage_asset&utm_medium=unload_'.$assetTypeS.'_in_post_type_via_tax'); ?>">
                    <span class="wpacu-tooltip wpacu-larger" style="left: -26px;">
                        <?php echo str_replace('the premium', 'the<br />premium', wp_kses(__('This feature is available in the premium version of the plugin.', 'wp-asset-clean-up' ), array('br' => array()))); ?><br/>
                        <?php _e( 'Click here to upgrade to Pro', 'wp-asset-clean-up' ); ?>!
                    </span>
                    <img style="margin: 0;" width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" valign="top" alt=""/>
                </a>
            <?php
            }
            ?>
            <a style="text-decoration: none; color: inherit; vertical-align: middle;" target="_blank"
               href="https://www.assetcleanup.com/docs/?p=1415#unload"><span
                        class="dashicons dashicons-editor-help"></span></a>
        </li>
    </ul>
</div>
<!-- [/wpacu_lite] -->