<?php
// no direct access
use WpAssetCleanUp\Admin\SettingsAdmin;

if (! isset($data)) {
	exit;
}

$showRetrievalMethod = true;

if (isset($data['is_frontend_view']) && $data['is_frontend_view']) {
    $showRetrievalMethod = false;
}

if (isset($data['post_type'], $data['post_id'])
    && $data['post_type'] && $data['post_id']
    && get_post_status($data['post_id']) === 'private') {
    $showRetrievalMethod = false;
}

?>
<p><?php echo sprintf(
        esc_html__('Please select the styles &amp; scripts that are %sNOT NEEDED%s from the list below. Not sure which ones to unload? %s Use "Test Mode" (to make the changes apply only to you), while you are going through the trial &amp; error process.', 'wp-asset-clean-up'),
	'<span style="color: #CC0000;"><strong>',
	'</strong></span>',
	'<img draggable="false" class="wpacu-emoji" style="max-width: 26px; max-height: 26px;" alt="" src="https://s.w.org/images/core/emoji/11.2.0/svg/1f914.svg">'
); ?></p>

<?php
if ($data['plugin_settings']['hide_core_files']) {
	?>
	<div class="wpacu_note"><span class="dashicons dashicons-info"></span> WordPress CSS &amp; JavaScript core files are hidden as requested in the plugin's settings. They are meant to be managed by experienced developers in special situations.</div>
	<div class="wpacu_clearfix" style="margin-top: 10px;"></div>
	<?php
}

if ( ( (isset($data['core_styles_loaded']) && $data['core_styles_loaded']) || (isset($data['core_scripts_loaded']) && $data['core_scripts_loaded']) ) && ! $data['plugin_settings']['hide_core_files']) {
	?>
	<div class="wpacu_note wpacu_warning">
		<em><?php echo sprintf(
				esc_html__('Assets that are marked with %s are part of WordPress core files. Be careful if you decide to unload them! If you are not sure what to do, just leave them loaded by default and consult with a developer.', 'wp-asset-clean-up'),
				'<span class="dashicons dashicons-wordpress-alt wordpress-core-file"></span>' );
			?>
		</em>
	</div>
	<?php
}
?>
<div style="margin-bottom: 10px;" class="wpacu-contract-expand-area">
	<div class="col-left">
        <small>* any new change will take effect after you use the "Update" button</small>
        <table style="width: auto; margin-top: 10px;">
            <tr>
                <td style="width: auto;"><label for="wpacu_assets_list_layout"><strong>Assets' List Layout:</strong></label></td>
                <td style="padding: 0 10px"><?php echo SettingsAdmin::generateAssetsListLayoutDropDown($data['plugin_settings']['assets_list_layout'], 'wpacu_assets_list_layout'); ?></td>
            </tr>
            <?php if ($showRetrievalMethod) { ?>
                <tr>
                    <td style="width: auto; text-align: right;"><strong>Retrieval Way:</strong></td>
                    <td style="display: inline-block; padding: 12px 0 6px 12px;">
                        <ul id="wpacu-dom-get-type-selections">
                            <li style="margin-right: 20px;">
                                <label>
                                    <input class="wpacu-dom-get-type-selection wpacu-dom-get-type-from-css-js-manager"
                                           style="margin-right: 1px;"
                                           data-target="wpacu-dom-get-type-direct-info"
                                           <?php if ($data['plugin_settings']['dom_get_type'] === 'direct') { ?>checked="checked"<?php } ?>
                                           type="radio" name="wpacu_dom_get_type"
                                           value="direct" /> <?php _e('Direct', 'wp-asset-clean-up'); ?> * <small>as if the admin visits the page</small>
                                </label>
                            </li>
                            <li>
                                <label>
                                    <input class="wpacu-dom-get-type-selection wpacu-dom-get-type-from-css-js-manager"
                                           style="margin-right: 1px;"
                                           data-target="wpacu-dom-get-type-wp-remote-post-info"
                                           <?php if ($data['plugin_settings']['dom_get_type'] === 'wp_remote_post') { ?>checked="checked"<?php } ?>
                                           type="radio" name="wpacu_dom_get_type"
                                           value="wp_remote_post" /> WP Remote POST * <small>as if a guest visits the page</small>
                                </label>
                                &nbsp;<a href="https://www.assetcleanup.com/docs/?p=1813" target="_blank" style="text-decoration: none; color: inherit;"><span class="dashicons dashicons-editor-help"></span></a>
                            </li>
                        </ul>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>

	<div id="wpacu-assets-groups-change-state-area" data-wpacu-groups-current-state="<?php echo esc_attr($data['plugin_settings']['assets_list_layout_areas_status']); ?>" class="col-right">
        <button id="wpacu-assets-contract-all" class="wpacu_wp_button wpacu_wp_button_secondary"><img class="wpacu_ajax_loader" align="top" src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" alt="" /> <span>Contract All Groups</span></button>&nbsp;
        <button id="wpacu-assets-expand-all" class="wpacu_wp_button wpacu_wp_button_secondary"><img class="wpacu_ajax_loader" align="top" src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" alt="" /> <span>Expand All Groups</span></button>
	</div>
	<div class="wpacu_clearfix"></div>
</div>
