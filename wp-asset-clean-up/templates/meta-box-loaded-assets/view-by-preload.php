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
	$data['rows_by_preload'] = true;

	$data['rows_assets'] = array();

	require_once __DIR__.'/_asset-rows.php';

	$preloadsText = array(
        'preloaded'     => '<span class="dashicons dashicons-upload"></span>&nbsp; '.esc_html__('Preloaded assets (.css &amp; .js)', 'wp-asset-clean-up'),
        'not_preloaded' => '<span class="dashicons dashicons-download"></span>&nbsp; '.esc_html__('Not-preloaded (default status) assets (.css &amp; .js)', 'wp-asset-clean-up')
    );

	if (! empty($data['rows_assets'])) {
		// Sorting: Preloaded and Not Preloaded (standard loading)
		$rowsAssets = array('preloaded' => array(), 'not_preloaded' => array());

		foreach ($data['rows_assets'] as $preloadStatus => $values) {
			$rowsAssets[$preloadStatus] = $values;
		}

		foreach ($rowsAssets as $preloadStatus => $values) {
            $values = \WpAssetCleanUp\Admin\Sorting::sortAreaAssetRowsValues($values);

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
            <div class="wpacu-assets-collapsible-wrap wpacu-by-preloads wpacu-wrap-area wpacu-<?php echo esc_attr($preloadStatus); ?>">
                <a class="wpacu-assets-collapsible <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-assets-collapsible-active<?php } ?>" href="#wpacu-assets-collapsible-content-<?php echo esc_attr($preloadStatus); ?>">
	                <?php echo wp_kses($preloadsText[$preloadStatus], array('span' => array('class' => array()))); ?> &#10141; Total files: <?php echo (int)$totalFiles; ?>
                </a>

                <div class="wpacu-assets-collapsible-content <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-open<?php } ?>">
	                <?php if (count($values) > 0) { ?>
                        <div class="wpacu-area-toggle-all-assets wpacu-right">
                            <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="<?php echo esc_html($preloadStatus); ?>_assets" href="#">Contract</a>
                            |
                            <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="<?php echo esc_html($preloadStatus); ?>_assets" href="#">Expand</a>
                            All Assets
                        </div>
	                <?php } ?>

					<?php if ($preloadStatus === 'preloaded') { ?>
                        <p class="wpacu-assets-note">This is the list of assets (if any) that were chosen to be preloaded through the <span style="background: #e8e8e8; padding: 2px;">&lt;link rel="preload"&gt;</span> tag (any valid option from "Preload?" drop-down). Note that the preload option is obviously irrelevant if the asset was chosen to be unloaded. The preload option is ONLY relevant for the assets that are loading in the page.</p>
					    <?php
                        if (count($values) < 1) {
                        ?>
                            <p style="padding: 0 15px 15px;"><strong>There are no assets chosen to be preloaded.</strong></p>
                        <?php
                        }
                        ?>
                    <?php } elseif ($preloadStatus === 'not_preloaded') { ?>
                        <p class="wpacu-assets-note">This is the list of assets that do not have any preload option added to them which is the default way of showing up on the page.</p>
					<?php } ?>

					<?php if (count($values) > 0) { ?>
                        <table class="wpacu_list_table wpacu_list_by_preload wpacu_widefat wpacu_striped"
                               data-wpacu-area="<?php echo esc_html($preloadStatus); ?>_assets">
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
