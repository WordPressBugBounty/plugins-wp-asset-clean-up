<?php
/*
 * The file is included from /templates/meta-box-loaded-assets/_asset-single-row.php
*/

if ( ! isset($data, $assetType, $assetTypeS) ) {
    exit(); // no direct access
}

$isGroupUnloaded = $data['row']['is_group_unloaded'];

require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/load-exceptions-state.php';

$anyUnloadRuleSet = ($isGroupUnloaded ||
    $isMarkedForRegExUnload ||
    $isMarkedForPostTypeViaTaxUnload ||
    $isMarkedForTaxUnload ||
    $data['row']['checked']);

if ($anyUnloadRuleSet || $data['row']['is_load_exception_per_page']) {
    $data['row']['at_least_one_rule_set'] = true;
}

$loadExceptionOptionsAreaCss = '';

if ($data['row']['global_unloaded']) {
    // Move it to the right side or extend it to avoid so much empty space and a higher DIV
	$loadExceptionOptionsAreaCss = 'display: contents;';
}
?>
<div class="wpacu_exception_options_area_load_exception <?php if (! $anyUnloadRuleSet) { echo 'wpacu_hide'; } ?>" style="<?php echo $loadExceptionOptionsAreaCss; ?>">
    <div data-<?php echo $assetTypeS; ?>-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
         class="wpacu_exception_options_area_wrap">
        <fieldset>
            <legend>Make an exception from any unload rule &amp; <strong>always load it</strong>:</legend>
		    <ul class="wpacu_area_two wpacu_asset_options wpacu_exception_options_area">
                <li>
                    <label for="wpacu_load_it_option_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">
                        <input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                               data-handle-for="<?php echo $assetTypeS; ?>"
                                  id="wpacu_load_it_option_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                                  class="wpacu_load_it_option_on_this_page wpacu_<?php echo $assetTypeS; ?> wpacu_load_exception"
                                  type="checkbox"
                            <?php if ($data['row']['is_load_exception_per_page']) { ?> checked="checked" <?php } ?>
                                  name="wpacu_<?php echo $assetType; ?>_load_it[]"
                                  value="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>" />
                        <span><?php echo $data['page_load_text']; ?></span></label>
                </li>
                <?php
                if ($data['bulk_unloaded_type'] === 'post_type') {
                    // Only show it on edit post/page/custom post type
                    switch ($data['post_type']) {
                        case 'product':
                            $loadBulkText = __('On all WooCommerce "Product" pages', 'wp-asset-clean-up');
                            break;
                        case 'download':
                            $loadBulkText = __('On all Easy Digital Downloads "Download" pages', 'wp-asset-clean-up');
                            break;
                        default:
                            $loadBulkText = sprintf(__('On all pages of "<strong>%s</strong>" post type', 'wp-asset-clean-up'), $data['post_type']);
                    }
                    ?>
                    <li for="wpacu_load_it_option_post_type_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">
                        <label><input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                                      data-handle-for="<?php echo $assetTypeS; ?>"
                                      id="wpacu_load_it_option_post_type_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                                      class="wpacu_load_it_option_post_type wpacu_<?php echo $assetTypeS; ?> wpacu_load_exception"
                                      type="checkbox"
                                <?php if ($data['row']['is_load_exception_post_type']) { ?> checked="checked" <?php } ?>
                                      name="<?php echo WPACU_FORM_ASSETS_POST_KEY; ?>[<?php echo $assetType; ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][load_it_post_type]"
                                      value="1"/>
                            <span><?php echo wp_kses($loadBulkText, array('strong' => array())); ?></span></label>
                    </li>
                    <?php
                    if (isset($data['post_type']) && $data['post_type'] !== 'attachment' && ! empty($data['post_type_has_tax_assoc'])) {
                        require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/load-exception-post-type-taxonomy.php';
                    }
                }

                require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/load-exceptions-context-slot.php';
                ?>
                <?php require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/load-exception-regex.php'; ?>
                <?php
                $isLoadItLoggedIn = in_array($data['row']['obj']->handle, $data['handle_load_logged_in'][$assetType]);
                if ($isLoadItLoggedIn) { $data['row']['at_least_one_rule_set'] = true; }
                ?>
                <li id="wpacu_load_it_user_logged_in_option_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">
                    <label><input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                                  data-handle-for="<?php echo $assetTypeS; ?>"
                                  id="wpacu_load_it_user_logged_in_option_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                                  class="wpacu_load_it_option_user_logged_in wpacu_<?php echo $assetTypeS; ?> wpacu_load_exception"
                                  type="checkbox"
                            <?php if ($isLoadItLoggedIn) { ?> checked="checked" <?php } ?>
                                  name="wpacu_load_it_logged_in[<?php echo $assetType; ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>]"
                                  value="1"/>
                        <span><?php esc_html_e('If the user is logged-in', 'wp-asset-clean-up'); ?></span></label>
                </li>
		    </ul>
            <div class="wpacu_clearfix"></div>
        </fieldset>
	</div>
</div>
