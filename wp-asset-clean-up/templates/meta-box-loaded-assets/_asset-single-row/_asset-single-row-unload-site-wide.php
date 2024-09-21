<?php
/*
 * The file is included from /templates/meta-box-loaded-assets/_asset-single-row.php
*/

if ( ! isset($data, $assetType, $assetTypeS) ) {
    exit(); // no direct access
}
?>

<!-- [wpacu_lite] -->
<?php
if (isset($data['row']['is_hardcoded']) && $data['row']['is_hardcoded']) {
?>
    <div class="wpacu_asset_options_wrap">
        <ul class="wpacu_asset_options">
            <li>
                <label class="wpacu-manage-hardcoded-assets-requires-pro-popup">
                    <span style="color: #ccc;" class="wpacu-manage-hardcoded-assets-requires-pro-popup dashicons dashicons-lock"></span>
                    <?php _e('Unload site-wide', 'wp-asset-clean-up'); ?> <small>* <?php _e('everywhere', 'wp-asset-clean-up'); ?></small>
                </label>
            </li>
        </ul>
    </div>
<?php
    return; // Stop here as the code below is for not meant for hardcoded assets
}
?>
<!-- [/wpacu_lite] -->

<div class="wpacu_asset_options_wrap">
	<?php
	// Unloaded Everywhere
	if ($data['row']['global_unloaded']) {
	    if ($assetType === 'scripts') {
            $unloadedSiteWideText = __('This JS is unloaded site-wide (everywhere)', 'wp-asset-clean-up');

		    if (isset($data['row']['obj']->tag_output) && strncasecmp($data['row']['obj']->tag_output, '<noscript', 9) === 0) {
			    $unloadedSiteWideText = __('This NOSCRIPT tag is unloaded site-wide (everywhere)', 'wp-asset-clean-up');
		    }
		?>
		    <div style="display: inline-block; margin-right: 15px;"><strong style="color: #d54e21;"><?php echo $unloadedSiteWideText; ?>.</strong></div>
		<?php
        } else {
		    $unloadedSiteWideText = __('This CSS is unloaded site-wide (everywhere)', 'wp-asset-clean-up');
        ?>
            <div style="display: inline-block; margin-right: 15px;"><strong style="color: #d54e21;"><?php echo $unloadedSiteWideText; ?>.</strong></div>
        <?php
	    }
	}
	?>

	<ul class="wpacu_asset_options" <?php if ($data['row']['global_unloaded']) { echo 'style="display: block; margin: 10px 0;"'; } ?>>
		<?php
		if ($data['row']['global_unloaded']) {
			?>
			<li>
				<label>
					<input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
					       class="wpacu_bulk_option wpacu_<?php echo $assetTypeS; ?> wpacu_keep_site_wide_rule"
					       type="radio"
					       name="wpacu_options_<?php echo $assetType; ?>[<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>]"
					       checked="checked"
					       value="default" />
					<?php _e('Keep site-wide rule', 'wp-asset-clean-up'); ?></label>
			</li>

			<li style="margin-right: 0;">
				<label>
					<input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
					       class="wpacu_bulk_option wpacu_<?php echo $assetTypeS; ?> wpacu_remove_site_wide_rule"
					       type="radio"
					       name="wpacu_options_<?php echo $assetType; ?>[<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>]"
					       value="remove" />
					<?php _e('Remove site-wide rule', 'wp-asset-clean-up'); ?></label>
			</li>
			<?php
		} else {
			?>
			<li>
				<label><input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
				              data-handle-for="<?php echo $assetTypeS; ?>"
				              class="wpacu_global_unload wpacu_bulk_unload wpacu_global_<?php echo $assetTypeS; ?> wpacu_unload_rule_input wpacu_unload_rule_for_<?php echo $assetTypeS; ?>"
				              id="wpacu_global_unload_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
				              type="checkbox"
				              name="wpacu_global_unload_<?php echo $assetType; ?>[]"
				              value="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"/>
					<?php _e('Unload site-wide', 'wp-asset-clean-up'); ?> <small>* <?php _e('everywhere', 'wp-asset-clean-up'); ?></small></label>
			</li>
			<?php
		}
		?>
	</ul>
	<?php if ($data['row']['global_unloaded']) { ?>
		<div style="margin: 7px 0 -2px 0; font-weight: 500;">
			<small><span class="dashicons dashicons-warning"
			             style="color: inherit !important; opacity: 0.6; vertical-align: middle;"></span> <?php esc_html_e('All other unload rules (e.g. per page, RegEx) are overwritten by this site-wide rule.'); ?></small> <a style="text-decoration: none; color: inherit; vertical-align: middle;" target="_blank" href="https://www.assetcleanup.com/docs/?p=1421"><span class="dashicons dashicons-editor-help"></span></a>
		</div>
	<?php } ?>
</div>
