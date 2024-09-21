<?php
// no direct access
use WpAssetCleanUp\Admin\MiscAdmin;

if (! isset($data)) {
	exit;
}

$listAreaStatus = $data['plugin_settings']['assets_list_layout_areas_status'];

/*
* --------------------------------------
* [START] BY PRELOAD STATUS (yes or no)
* --------------------------------------
*/
if (! empty($data['all']['styles']) || ! empty($data['all']['scripts'])) {
    require_once __DIR__.'/_assets-top-area.php';

	$data['rows_build_array'] =
	$data['rows_by_size'] = true;

	$data['rows_assets'] = $data['handles_sizes'] = array();

	require_once __DIR__.'/_asset-rows.php';

	$sizesText = array(
        'with_size'   => '<span class="dashicons dashicons-yes"></span>&nbsp; '.esc_html__('Local enqueued files ordered by their size (.css &amp; .js)', 'wp-asset-clean-up'),
        'external_na' => '<span class="dashicons dashicons-flag"></span>&nbsp; '.esc_html__('External enqueued files or non-existent (.css &amp; .js)', 'wp-asset-clean-up')
    );

	if (! empty($data['rows_assets'])) {
		// Sorting: With Size and External / No Size Detected
		$rowsAssets = array('with_size' => array(), 'external_na' => array());

		if (isset($data['rows_assets']['with_size']) && ! empty($data['handles_sizes'])) {
			$dataRowsAssetsWithSize = $data['rows_assets']['with_size'];
            unset($data['rows_assets']['with_size']); // re-built
			$data['rows_assets']['with_size'] = array();

			arsort($data['handles_sizes']);

			foreach ($data['handles_sizes'] as $uniqueHandle => $sizeInBytes) {
				$data['rows_assets']['with_size'][$uniqueHandle] = $dataRowsAssetsWithSize[$uniqueHandle];
			}
        }

		foreach ($data['rows_assets'] as $sizeStatus => $values) {
			$rowsAssets[$sizeStatus] = $values;
		}

		foreach ($rowsAssets as $sizeStatus => $values) {
            $values = \WpAssetCleanUp\Admin\Sorting::sortAreaAssetRowsValues($values, false);

			$assetRowsOutput = '';

			$totalFiles    = 0;
			$assetRowIndex = 1;

			foreach ($values as $assetRows) {
				foreach ($assetRows as $assetRow) {
					$assetRowsOutput .= $assetRow . "\n";

                    if (strpos($assetRow, 'wpacu_this_asset_row_area_is_hidden') === false) {
                        $totalFiles++;
                    }
				}
			}
			?>
            <div class="wpacu-assets-collapsible-wrap wpacu-by-preloads wpacu-wrap-area wpacu-<?php echo esc_attr($sizeStatus); ?>">
                <a class="wpacu-assets-collapsible <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-assets-collapsible-active<?php } ?>" href="#wpacu-assets-collapsible-content-<?php echo esc_attr($sizeStatus); ?>">
					<?php echo wp_kses($sizesText[$sizeStatus], array('span' => array('class' => array()))); ?> &#10141; Total files: <?php echo (int)$totalFiles; ?>
                </a>

                <div class="wpacu-assets-collapsible-content <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-open<?php } ?>">
	                <?php if (count($values) > 0) { ?>
                        <div class="wpacu-area-toggle-all-assets wpacu-right">
                            <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="<?php echo esc_html($sizeStatus); ?>_assets" href="#">Contract</a>
                            |
                            <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="<?php echo esc_html($sizeStatus); ?>_assets" href="#">Expand</a>
                            All Assets
                        </div>
	                <?php } ?>

					<?php if ($sizeStatus === 'with_size') { ?>
						<p class="wpacu-assets-note">This is the list of local files (if any) that had their size calculated and shown in descendent order, <strong>from the largest to the smallest</strong>.</p>
					    <?php
                        if (count($values) < 1) {
                        ?>
                            <p style="padding: 0 15px 15px;"><strong>There are no local files that could have their size calculated.</strong></p>
                        <?php
                        }
                        ?>
                    <?php } elseif ($sizeStatus === 'external_na') { ?>
                        <p class="wpacu-assets-note">This is the list of assets that are external and you can manually check their size via "Get File Size" link. This list also includes local files (most likely that do not exist and are loaded in your page) that couldn't have their size calculated.</p>
					<?php } ?>

					<?php if (count($values) > 0) { ?>
                        <table class="wpacu_list_table wpacu_list_by_size wpacu_widefat wpacu_striped"
                               data-wpacu-area="<?php echo esc_html($sizeStatus); ?>_assets">
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
* ------------------------------------
* [END] BY PRELOAD STATUS (yes or no)
* ------------------------------------
*/

include_once __DIR__ . '/_view-common-footer.php';
