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

if (defined('WPACU_PRO_PLUGIN_VERSION')) {
$handleUnloadRegex = ( isset( $data['handle_unload_regex'][$assetType][ $data['row']['obj']->handle ] ) && $data['handle_unload_regex'][$assetType][ $data['row']['obj']->handle ] )
    ? $data['handle_unload_regex'][$assetType][ $data['row']['obj']->handle ]
    : array();

$handleUnloadRegex['enable'] = isset( $handleUnloadRegex['enable'] ) && $handleUnloadRegex['enable'];
$handleUnloadRegex['value']  = ( isset( $handleUnloadRegex['value'] ) && $handleUnloadRegex['value'] ) ? $handleUnloadRegex['value'] : '';

$isUnloadRegExEnabledWithValue = $handleUnloadRegex['enable'] && $handleUnloadRegex['value'];
if ($isUnloadRegExEnabledWithValue) { $data['row']['at_least_one_rule_set'] = true; }
?>
<div data-<?php echo $assetTypeS; ?>-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>" class="wpacu_asset_options_wrap wpacu_unload_regex_area_wrap">
    <ul class="wpacu_asset_options">
        <li>
            <label for="wpacu_unload_it_regex_option_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                <?php if ( $isUnloadRegExEnabledWithValue ) {
                    echo ' class="wpacu_unload_checked"';
                } ?>>
                <input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                       data-handle-for="<?php echo $assetTypeS; ?>"
                       id="wpacu_unload_it_regex_option_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                       class="wpacu_unload_it_regex_checkbox wpacu_unload_rule_input wpacu_bulk_unload"
                       type="checkbox"
                       name="wpacu_handle_unload_regex[<?php echo $assetType; ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][enable]"
                    <?php if ( $handleUnloadRegex['enable'] ) { ?> checked="checked" <?php } ?>
                       value="1"/>&nbsp;<span><?php
                    if ($assetType === 'styles') {
                        $assetTypeText = 'CSS';
                    } else {
                        $assetTypeText = 'JS';

                        if (isset($data['row']['obj']->tag_output) && strncasecmp($data['row']['obj']->tag_output, '<noscript', 9) === 0) {
                            $assetTypeText = 'NOSCRIPT tag';
                        }
                    }

                    echo sprintf(__('Unload %s if the request URI matches any of these rules', 'wp-asset-clean-up'), $assetTypeText);
                    ?>:</span></label>
            <a style="text-decoration: none; color: inherit; vertical-align: middle;" target="_blank"
               href="https://www.assetcleanup.com/docs/?p=313#wpacu-unload-by-regex"><span
                    class="dashicons dashicons-editor-help"></span></a>
            <div class="wpacu_handle_unload_regex_input_wrap <?php if (! $isUnloadRegExEnabledWithValue) { echo 'wpacu_hide'; } ?>">
                <div class="wpacu_regex_rule_area">
                    <textarea <?php if (! $isUnloadRegExEnabledWithValue) { echo 'disabled="disabled"'; } ?>
                        class="wpacu_regex_rule_textarea"
                        data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                        data-handle-for="<?php echo $assetTypeS; ?>"
                        data-wpacu-adapt-height="1"
                        name="wpacu_handle_unload_regex[<?php echo $assetType; ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][value]"><?php echo esc_textarea($handleUnloadRegex['value']); ?></textarea>
                    <p style="margin-top: 0;"><small><span style="font-weight: 500;">Note:</span> Enter one rule per line. Plain URI strings and RegEx patterns are supported.</small></p>
                </div>
            </div>
        </li>
    </ul>
</div>
<?php
return;
}

$assetTypeText = ($assetType === 'styles') ? 'CSS' : 'JS';
if ($assetType === 'scripts' && isset($data['row']['obj']->tag_output) && strncasecmp($data['row']['obj']->tag_output, '<noscript', 9) === 0) {
    $assetTypeText = 'NOSCRIPT tag';
}
$unloadViaRegExText = sprintf(__('Unload %s if the request URI matches any of these rules', 'wp-asset-clean-up'), $assetTypeText);
?>
<div class="wpacu_asset_options_wrap wpacu_unload_regex_area_wrap">
    <ul class="wpacu_asset_options"><li class="wpacu-locked-checkbox-option">
        <label class="wpacu-locked-checkbox-trigger<?php echo ! empty($data['row']['is_hardcoded']) ? ' wpacu-manage-hardcoded-assets-requires-pro-popup' : ''; ?>">
            <input class="wpacu_unload_it_regex_checkbox wpacu_unload_rule_input wpacu_bulk_unload" type="checkbox" disabled="disabled" />
            <span><?php echo esc_html($unloadViaRegExText); ?>:</span>
        </label>
        <a class="go-pro-link-no-style wpacu-locked-checkbox-overlay" href="<?php echo esc_url(apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=manage_asset&utm_medium=unload_'.$assetTypeS.'_by_regex')); ?>"><span class="wpacu-tooltip wpacu-larger"><?php esc_html_e('This advanced rule is available in Asset CleanUp Pro. Upgrade to apply it to matching pages or requests.', 'wp-asset-clean-up'); ?></span><img style="margin: 0;" width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" alt="" /></a>
        <a style="text-decoration: none; color: inherit; vertical-align: middle;" target="_blank" href="https://www.assetcleanup.com/docs/?p=313#wpacu-unload-by-regex"><span class="dashicons dashicons-editor-help"></span></a>
    </li></ul>
</div>
