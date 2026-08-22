<?php
if (! defined('ABSPATH') || $assetType !== 'scripts') {
    return;
}

$wpacuScriptAttrsIsPro = defined('WPACU_PRO_PLUGIN_VERSION');
?>
<div class="wpacu-script-attributes-area <?php echo $wpacuScriptAttrsIsPro ? 'wpacu-pro' : 'wpacu-lite'; ?> wpacu-only-when-kept-loaded">
    <div><?php esc_html_e('Set the following attributes:', 'wp-asset-clean-up'); ?><?php if (! $wpacuScriptAttrsIsPro) { ?> <em><a class="go-pro-link-no-style" href="<?php echo esc_url(apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL)); ?>">* <?php esc_html_e('Pro version', 'wp-asset-clean-up'); ?></a></em><?php } ?></div>
    <?php
    foreach (array('async', 'defer') as $wpacuScriptAttrIndex => $wpacuScriptAttr) {
        $wpacuScriptAttrOnPage = $wpacuScriptAttrsIsPro && in_array($data['row']['obj']->handle, $data['scripts_attributes']['on_this_page'][$wpacuScriptAttr]);
        $wpacuScriptAttrNotOnPage = $wpacuScriptAttrsIsPro && in_array($data['row']['obj']->handle, $data['scripts_attributes']['not_on_this_page'][$wpacuScriptAttr]);
        $wpacuScriptAttrGlobal = $wpacuScriptAttrsIsPro && in_array($data['row']['obj']->handle, $data['scripts_attributes']['everywhere'][$wpacuScriptAttr]);

        if ($wpacuScriptAttrOnPage || $wpacuScriptAttrGlobal) {
            $data['row']['at_least_one_rule_set'] = true;
        }
        ?>
        <ul class="wpacu-script-attributes-settings <?php echo $wpacuScriptAttrIndex === 0 ? 'wpacu-first' : ''; ?>">
            <li>
                <?php if (! $wpacuScriptAttrsIsPro) { ?>
                    <a class="go-pro-link-no-style wpacu-script-attribute-pro-lock" href="<?php echo esc_url(apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL)); ?>"><span class="wpacu-tooltip wpacu-larger"><?php esc_html_e('Script attribute rules are available in Pro.', 'wp-asset-clean-up'); ?><br /><?php esc_html_e('Click here to upgrade', 'wp-asset-clean-up'); ?>.</span><img style="margin: 0; vertical-align: bottom;" width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg'); ?>" valign="top" alt="" /></a>
                <?php } ?>
                <strong><?php if ($wpacuScriptAttrsIsPro) { ?><u><?php } echo esc_html($wpacuScriptAttr); if ($wpacuScriptAttrsIsPro) { ?></u><?php } ?></strong> <span class="wpacu-script-attribute-arrow" aria-hidden="true">&#10230;</span>
            </li>
            <li>
                <label for="<?php echo esc_attr($wpacuScriptAttr); ?>_on_this_page_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">
                    <input id="<?php echo esc_attr($wpacuScriptAttr); ?>_on_this_page_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                           class="wpacu_script_attr_rule_input"
                           type="checkbox"
                        <?php if (! $wpacuScriptAttrsIsPro || $wpacuScriptAttrGlobal) { ?> disabled="disabled"<?php } ?>
                        <?php checked($wpacuScriptAttrOnPage); ?>
                           name="wpacu_<?php echo esc_attr($wpacuScriptAttr); ?>[<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>]"
                           value="on_this_page" /><?php esc_html_e('on this page', 'wp-asset-clean-up'); ?>
                    <?php if ($wpacuScriptAttrGlobal) { ?><br /><small>* <?php esc_html_e('locked by site-wide rule', 'wp-asset-clean-up'); ?></small><?php } ?>
                </label>
            </li>
            <li>
                <?php if ($wpacuScriptAttrGlobal) { ?>
                    <div><strong><?php esc_html_e('Set everywhere', 'wp-asset-clean-up'); ?></strong> <small>* <?php esc_html_e('site-wide', 'wp-asset-clean-up'); ?></small></div>
                    <div>
                        <label><input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>" type="radio" name="wpacu_options_global_attribute_scripts[<?php echo esc_attr($wpacuScriptAttr); ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>]" checked="checked" value="default" /> <?php esc_html_e('Keep rule', 'wp-asset-clean-up'); ?></label>
                        &nbsp;&nbsp;&nbsp;&nbsp;
                        <label><input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>" type="radio" name="wpacu_options_global_attribute_scripts[<?php echo esc_attr($wpacuScriptAttr); ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>]" value="remove" /> <?php esc_html_e('Remove rule', 'wp-asset-clean-up'); ?></label>
                    </div>
                <?php } else { ?>
                    <label for="<?php echo esc_attr($wpacuScriptAttr); ?>_everywhere_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">
                        <input id="<?php echo esc_attr($wpacuScriptAttr); ?>_everywhere_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                               class="wpacu_script_attr_rule_input wpacu_script_attr_rule_global"
                               type="checkbox"
                            <?php if (! $wpacuScriptAttrsIsPro) { ?> disabled="disabled"<?php } ?>
                               name="wpacu_<?php echo esc_attr($wpacuScriptAttr); ?>[<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>]"
                               value="everywhere" /><?php esc_html_e('everywhere', 'wp-asset-clean-up'); ?>
                    </label>
                <?php } ?>
            </li>
            <?php if ($wpacuScriptAttrsIsPro) { ?>
                <li class="wpacu-script-attr-make-exception <?php if (! $wpacuScriptAttrGlobal) { ?>wpacu_hide<?php } ?>">
                    <label for="<?php echo esc_attr($wpacuScriptAttr); ?>_none_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">
                        <input id="<?php echo esc_attr($wpacuScriptAttr); ?>_none_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                               type="checkbox"
                               name="wpacu_<?php echo esc_attr($wpacuScriptAttr); ?>[no_load][]"
                            <?php checked($wpacuScriptAttrNotOnPage); ?>
                               value="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>" /><?php esc_html_e('not here (exception)', 'wp-asset-clean-up'); ?>
                    </label>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
    <div class="wpacu_clearfix"></div>
</div>
<div class="wpacu_clearfix"></div>
<?php unset($wpacuScriptAttr, $wpacuScriptAttrGlobal, $wpacuScriptAttrIndex, $wpacuScriptAttrNotOnPage, $wpacuScriptAttrOnPage, $wpacuScriptAttrsIsPro); ?>
