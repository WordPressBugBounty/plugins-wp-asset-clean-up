<?php
// no direct access
use WpAssetCleanUp\Admin\MiscAdmin;

if (! isset($data)) {
	exit;
}

$listAreaStatus = $data['plugin_settings']['assets_list_layout_areas_status'];

/*
* -------------------------------
* [START] BY Loaded or Unloaded
* -------------------------------
*/
if (! empty($data['all']['styles']) || ! empty($data['all']['scripts'])) {
    require_once __DIR__.'/_assets-top-area.php';

	$data['rows_build_array'] =
	$data['rows_by_loaded_unloaded'] = true;

	$data['rows_assets'] = array();

	require_once __DIR__.'/_asset-rows.php';

	if (! empty($data['rows_assets'])) {
		$handleStatusesText = array(
			'loaded'   => '<span style="color: green;" class="dashicons dashicons-yes"></span>&nbsp; '.esc_html__('All loaded (.css &amp; .js)', 'wp-asset-clean-up'),
			'unloaded' => '<span style="color: #cc0000;" class="dashicons dashicons-no-alt"></span>&nbsp; '.esc_html__('All unloaded (.css &amp; .js)', 'wp-asset-clean-up'),
		);

		// Sorting: loaded & unloaded
		$rowsAssets = array('loaded' => array(), 'unloaded' => array());

		foreach ($data['rows_assets'] as $handleStatus => $values) {
			$rowsAssets[$handleStatus] = $values;
		}

		foreach ($rowsAssets as $handleStatus => $values) {
            $values = \WpAssetCleanUp\Admin\Sorting::sortAreaAssetRowsValues($values);

            $assetRowsOutput = '';

			$totalFiles    = 0;
			$assetRowIndex = 1;

			foreach ($values as $assetType => $assetRows) {
				foreach ($assetRows as $assetRow) {
					$assetRowsOutput .= $assetRow . "\n";

                    if (strpos($assetRow, 'wpacu_this_asset_row_area_is_hidden') === false) {
                        $totalFiles++;
                    }
				}
			}
			?>
            <div class="wpacu-assets-collapsible-wrap wpacu-by-parents wpacu-wrap-area wpacu-<?php echo esc_attr($handleStatus); ?>">
                <a class="wpacu-assets-collapsible <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-assets-collapsible-active<?php } ?>" href="#wpacu-assets-collapsible-content-<?php echo esc_attr($handleStatus); ?>">
	                <?php echo wp_kses($handleStatusesText[$handleStatus], array('span' => array('class' => array(), 'style' => array()))); ?> &#10141; Total files: <?php echo (int)$totalFiles; ?>
                </a>

                <div class="wpacu-assets-collapsible-content <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-open<?php } ?>">
			        <?php if (count($values) > 0) { ?>
                        <div class="wpacu-area-toggle-all-assets wpacu-right">
                            <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="<?php echo esc_html($handleStatus); ?>_assets" href="#">Contract</a>
                            |
                            <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="<?php echo esc_html($handleStatus); ?>_assets" href="#">Expand</a>
                            All Assets
                        </div>
                    <?php } ?>

					<?php if ($handleStatus === 'loaded') {
                        if (count($values) > 0) {
                            $loadedFilesNote = esc_html__('The following files were not selected for unload in any way (e.g. per page, site-wide) on this page. The list also includes any load exceptions (e.g. a file can be unloaded site-wide, but loaded on this page).', 'wp-asset-clean-up');
                        } else {
	                        $loadedFilesNote = esc_html__('All the CSS/JS files were chosen to be unloaded on this page', 'wp-asset-clean-up');
                        }
					    ?>
                        <p class="wpacu-assets-note"><?php echo esc_html($loadedFilesNote); ?></p>
					<?php } elseif ($handleStatus === 'unloaded') {
					    if (count($values) > 0) {
						    $unloadedFilesNote = esc_html__('The following CSS/JS files are unloaded on this page due to the rules that took effect.', 'wp-asset-clean-up');
					    } else {
						    $unloadedFilesNote = esc_html__('There are no unloaded CSS/JS files on this page.', 'wp-asset-clean-up');
                        }
					    ?>
                        <p class="wpacu-assets-note"><?php echo esc_html($unloadedFilesNote); ?>.</p>
					<?php } ?>

                    <?php if (count($values) > 0) { ?>
                        <table class="wpacu_list_table wpacu_list_by_parents wpacu_widefat wpacu_striped"
                               data-wpacu-area="<?php echo esc_html($handleStatus); ?>_assets">
                            <tbody>
                            <?php
                            echo MiscAdmin::stripIrrelevantHtmlTags($assetRowsOutput);
                            ?>
                            </tbody>
                        </table>
                    <?php } ?>
                </div>
            </div>
			<?php
		}
	}
}
/*
* ----------------------------
* [END] BY Loaded or Unloaded
* ----------------------------
*/

include_once __DIR__ . '/_view-common-footer.php';
