<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\Overview;

if (! isset($data)) {
	exit;
}

//echo '<pre>'; print_r($data['handles']['scripts']); echo '</pre>'; exit();
?>
<hr style="margin: 15px 0;"/>

<h3 id="wpacu-overview-section-scripts" class="wpacu-overview-section-title"><span class="dashicons dashicons-media-code"></span> <?php _e('Scripts (.js)', 'wp-asset-clean-up'); ?>
	<?php
	if (isset($data['handles']['scripts']) && count($data['handles']['scripts']) > 0) {
		echo ' &#10230; Total handles with rules: '.count($data['handles']['scripts']);
	}
	?> <a class="wpacu-overview-back-to-navigation" href="#wpacu-overview-start" aria-label="<?php esc_attr_e('Back to Overview navigation', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></a></h3>
<?php
if ( ! empty($data['handles']['scripts']) ) {
	?>
	<table class="wp-list-table wpacu-overview-list-table widefat fixed striped">
		<thead>
		<tr class="wpacu-top">
			<td><strong>Handle</strong></td>
			<td><strong>Unload &amp; Load Exception Rules</strong></td>
		</tr>
		</thead>
		<?php
		foreach ($data['handles']['scripts'] as $handle => $handleData) {
			?>
			<tr id="wpacu-overview-js-<?php echo esc_attr($handle); ?>" class="wpacu_global_rule_row wpacu_bulk_change_row">
                <td data-wpacu-item-data="1">
					<?php
                    Overview::renderHandleTd($handle, 'scripts', $data);
                    ?>
				</td>
                <td data-wpacu-item-unload-load-rules="1">
					<?php
					$handleData['handle']     = $handle;
					$handleData['asset_type'] = 'scripts';
					$handleChangesOutput      = Overview::renderHandleChangesOutput($handleData);

					if ( ! empty($handleChangesOutput) ) {
						foreach ( $handleChangesOutput as $handleChangesOutputPart ) {
							echo $handleChangesOutputPart;
						}
					} else {
						echo '<em style="color: #6d6d6d;">'.__('No unload/load exception rules of any kind are set for this JavaScript file', 'wp-asset-clean-up').'</em>.';
					}
					?>
				</td>
			</tr>
			<?php
		}
		?>
	</table>
	<?php
} else {
	?>
	<p><?php _e('There is no data added to (e.g. unload, load exceptions, notes, async/defer attributes, changing of location, preloading, etc.) to any SCRIPT tag.', 'wp-asset-clean-up'); ?></p>
	<?php
}
