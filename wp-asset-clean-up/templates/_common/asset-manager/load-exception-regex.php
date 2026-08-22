<?php
if (! defined('ABSPATH')) {
    exit;
}

$wpacuLoadRegexIsPro = defined('WPACU_PRO_PLUGIN_VERSION');
$handleLoadRegex = array('enable' => false, 'value' => '');

if ($wpacuLoadRegexIsPro && ! empty($data['handle_load_regex'][$assetType][$data['row']['obj']->handle])) {
    $handleLoadRegex = $data['handle_load_regex'][$assetType][$data['row']['obj']->handle];
    $handleLoadRegex['enable'] = ! empty($handleLoadRegex['enable']);
    $handleLoadRegex['value'] = ! empty($handleLoadRegex['value']) ? $handleLoadRegex['value'] : '';
}

$isLoadRegExEnabledWithValue = $handleLoadRegex['enable'] && $handleLoadRegex['value'] !== '';
if ($isLoadRegExEnabledWithValue) {
    $data['row']['at_least_one_rule_set'] = true;
}
?>
<li<?php if (! $wpacuLoadRegexIsPro) { ?> class="wpacu-locked-checkbox-option"<?php } ?>>
    <label<?php if (! $wpacuLoadRegexIsPro) { ?> class="wpacu-locked-checkbox-trigger"<?php } ?> for="wpacu_load_it_regex_option_<?php echo esc_attr($assetTypeS); ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">
        <input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
               data-handle-for="<?php echo esc_attr($assetTypeS); ?>"
               id="wpacu_load_it_regex_option_<?php echo esc_attr($assetTypeS); ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
               class="wpacu_load_it_option_regex wpacu_<?php echo esc_attr($assetTypeS); ?> wpacu_load_exception<?php echo $wpacuLoadRegexIsPro ? '' : ' wpacu_lite_locked'; ?>"
               type="checkbox"
               name="wpacu_handle_load_regex[<?php echo esc_attr($assetType); ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][enable]"
            <?php if (! $wpacuLoadRegexIsPro) { ?> disabled="disabled"<?php } ?>
            <?php checked($isLoadRegExEnabledWithValue); ?>
               value="1" />&nbsp;<span><?php esc_html_e('If the request URI matches any of these rules:', 'wp-asset-clean-up'); ?></span>
    </label>
    <?php if (! $wpacuLoadRegexIsPro) { ?>
        <a class="go-pro-link-no-style wpacu-locked-checkbox-overlay" href="<?php echo esc_url(apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_asset&utm_medium=load_via_regex_make_exception')); ?>">
            <span style="left: -26px;" class="wpacu-tooltip wpacu-larger"><?php esc_html_e('This advanced rule is available in Asset CleanUp Pro. Upgrade to apply it to matching pages or requests.', 'wp-asset-clean-up'); ?></span>
            <img width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg'); ?>" valign="top" alt="" />
        </a>
    <?php } ?>
    <a style="text-decoration: none; color: inherit; vertical-align: middle;" target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=21#wpacu-method-2"><span class="dashicons dashicons-editor-help"></span></a>
    <?php if ($wpacuLoadRegexIsPro) { ?>
        <div class="wpacu_load_regex_input_wrap <?php if (! $isLoadRegExEnabledWithValue) { echo 'wpacu_hide'; } ?>">
            <div class="wpacu_regex_rule_area">
                <textarea <?php if (! $isLoadRegExEnabledWithValue) { echo 'disabled="disabled"'; } ?>
                    class="wpacu_regex_rule_textarea"
                    data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                    data-handle-for="<?php echo esc_attr($assetTypeS); ?>"
                    data-wpacu-adapt-height="1"
                    name="wpacu_handle_load_regex[<?php echo esc_attr($assetType); ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][value]"><?php echo esc_textarea($handleLoadRegex['value']); ?></textarea>
                <p style="margin-top: 0 !important;"><small><span style="font-weight: 500;"><?php esc_html_e('Note:', 'wp-asset-clean-up'); ?></span> <?php esc_html_e('Enter one rule per line. Plain URI strings and RegEx patterns are supported.', 'wp-asset-clean-up'); ?></small></p>
            </div>
        </div>
    <?php } ?>
</li>
<?php unset($handleLoadRegex, $isLoadRegExEnabledWithValue, $wpacuLoadRegexIsPro); ?>
