<?php
/*
 * The file is included from /templates/meta-box-loaded-assets/_asset-single-row.php
*/

if (! isset($data, $assetType, $assetTypeS)) {
    exit(); // no direct access
}

if ($assetType === 'scripts') {
?>
    <!-- [wpacu_lite] -->
    <div class="wpacu-script-attributes-area wpacu-lite wpacu-only-when-kept-loaded">
        <div>Set the following attributes: <em><a class="go-pro-link-no-style" href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL); ?>">* <?php _e('Pro version', 'wp-asset-clean-up'); ?></a></em></div>
        <ul class="wpacu-script-attributes-settings wpacu-first">
            <li><a class="go-pro-link-no-style" href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL); ?>"><span class="wpacu-tooltip wpacu-larger"><?php echo str_replace('the premium', 'the<br />premium', wp_kses(__('This feature is available in the premium version of the plugin.', 'wp-asset-clean-up' ), array('br' => array()))); ?><br /> <?php _e('Click here to upgrade to Pro', 'wp-asset-clean-up'); ?>!</span><img style="margin: 0; vertical-align: bottom;" width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" valign="top" alt="" /></a>&nbsp; <strong>async</strong> &#10230;</li>
            <li><label><input disabled="disabled" type="checkbox" value="on_this_page" /><?php _e('on this page', 'wp-asset-clean-up'); ?></label></li>
            <li><label><input disabled="disabled" type="checkbox" value="everywhere" /><?php _e('everywhere', 'wp-asset-clean-up'); ?></label></li>
        </ul>
        <ul class="wpacu-script-attributes-settings">
            <li><a class="go-pro-link-no-style" href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL); ?>"><span class="wpacu-tooltip wpacu-larger"><?php echo str_replace('the premium', 'the<br />premium', wp_kses(__('This feature is available in the premium version of the plugin.', 'wp-asset-clean-up' ), array('br' => array()))); ?><br /> <?php _e('Click here to upgrade to Pro', 'wp-asset-clean-up'); ?>!</span><img style="margin: 0; vertical-align: bottom;" width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" valign="top" alt="" /></a>&nbsp; <strong>defer</strong> &#10230;</li>
            <li><label><input disabled="disabled" type="checkbox" value="on_this_page" /><?php _e('on this page', 'wp-asset-clean-up'); ?></label></li>
            <li><label><input disabled="disabled" type="checkbox" value="everywhere" /><?php _e('everywhere', 'wp-asset-clean-up'); ?></label></li>
        </ul>
        <div class="wpacu_clearfix"></div>
    </div>
    <div class="wpacu_clearfix"></div>
    <!-- [/wpacu_lite] -->
    <?php
}

$childHandles = isset($data['all_deps']['parent_to_child'][$assetType][$data['row']['obj']->handle]) ? $data['all_deps']['parent_to_child'][$assetType][$data['row']['obj']->handle] : array();

$handleAllStatuses = array();

if ( ! empty($childHandles) ) {
    $handleAllStatuses[] = 'is_parent';
}

if (isset($data['row']['obj']->deps) && ! empty($data['row']['obj']->deps)) {
    $handleAllStatuses[] = 'is_child';
}

if (empty($handleAllStatuses)) {
    $handleAllStatuses[] = 'is_independent';
}

if ($assetType === 'styles') {
    $showMatchMediaFeature = true;
} else {
    $showMatchMediaFeature = false;

    // Is "independent" or has "parents" (is "child") with nothing under it (no "children")
    if ( in_array('is_independent', $handleAllStatuses) || ( in_array('is_child', $handleAllStatuses) && ! in_array('is_parent', $handleAllStatuses) ) ) {
        // "extra" is fine, "after" and "before" are more tricky to accept (at least at this time)
        $wpacuHasExtraInline = ! empty($data['row']['extra_before_js']) || ! empty($data['row']['extra_after_js']);

        if ( ! $wpacuHasExtraInline ) {
            $showMatchMediaFeature = true;
        }
    }
}

if ( ! $showMatchMediaFeature ) {
    return;
}

// The media attribute is different from "all"
$assetHasDistinctiveMediaAttr    = isset($data['row']['obj']->args) && $data['row']['obj']->args && $data['row']['obj']->args !== 'all';
$showMatchMediaAlertForParentCss = $assetType === 'styles' && in_array('is_parent', $handleAllStatuses);
?>
<div class="wpacu-only-when-kept-loaded">
    <div style="margin: 0 0 15px;">
    <?php
    $wpacuDataForSelectId   = 'wpacu_handle_media_query_load_select_'.$assetTypeS.'_'.$data['row']['obj']->handle;
    $wpacuDataForTextAreaId = 'wpacu_handle_media_query_load_textarea_'.$assetTypeS.'_'.$data['row']['obj']->handle;
    ?>
        Make the browser download the file&nbsp;

        <select id="<?php echo esc_attr($wpacuDataForSelectId); ?>"
                data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                data-wpacu-input="media-query-select"
                name="<?php echo WPACU_FORM_ASSETS_POST_KEY; ?>[<?php echo $assetType; ?>][<?php echo esc_attr($data['row']['obj']->handle); ?>][media_query_load][enable]"
                class="wpacu-screen-size-load wpacu-for-<?php echo $assetTypeS; ?>">
            <option selected="selected" value="">on any screen size (default)</option>

            <?php if ( $assetHasDistinctiveMediaAttr ) { ?>
                <option disabled="disabled" value="2">only if its current media query is matched (Pro)</option>
            <?php } ?>

            <option disabled="disabled" value="1">only if this media query is matched: (Pro)</option>
        </select>
        <div style="display: inline-block; vertical-align: middle; margin-left: -2px;">
            <a class="go-pro-link-no-style wpacu-media-query-load-requires-pro-popup"
               href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=manage_asset&utm_medium=media_query_load_js'); ?>"><span
               class="wpacu-tooltip wpacu-larger" style="left: -26px;">
                <?php
                echo str_replace(
                    'the premium',
                    'the<br />premium',
                     wp_kses(__('This feature is available in the premium version of the plugin.', 'wp-asset-clean-up' ), array('br' => array()))
                );
                ?>
                <br/> <?php _e( 'Click here to upgrade to Pro', 'wp-asset-clean-up' ); ?>!</span> <img style="margin: 0; vertical-align: baseline;" width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" valign="top" alt="" />
            </a>
        </div>
        <div class="wpacu-helper-area"
             style="vertical-align: middle; margin-left: 2px;">
            <a style="text-decoration: none; color: inherit;" target="_blank" href="https://assetcleanup.com/docs/?p=1023"><span class="dashicons dashicons-editor-help"></span></a>
        </div>
    </div>
</div>
<div class="wpacu_clearfix"></div>
