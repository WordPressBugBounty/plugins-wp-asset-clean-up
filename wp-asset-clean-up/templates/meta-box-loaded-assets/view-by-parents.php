<?php
// no direct access
use WpAssetCleanUp\Admin\MiscAdmin;

if (! isset($data)) {
	exit;
}

$listAreaStatus = $data['plugin_settings']['assets_list_layout_areas_status'];

/*
* ----------------------------------------------
* [START] BY EACH HANDLE STATUS (Parent or Not)
* ----------------------------------------------
*/
if (! empty($data['all']['styles']) || ! empty($data['all']['scripts'])) {
    require_once __DIR__.'/_assets-top-area.php';

	$data['rows_build_array'] =
	$data['rows_by_parents'] = true;

	$data['rows_assets'] = array();

	require_once __DIR__.'/_asset-rows.php';

    $handleStatusesText = array(
        'parent'      => '<span class="dashicons dashicons-groups"></span>&nbsp; \'Parents\' with \'children\' (.css &amp; .js)',
        'child'       => '<span class="dashicons dashicons-admin-users"></span>&nbsp; \'Children\' of \'parents\' (.css &amp; .js)',
        'independent' => '<span class="dashicons dashicons-admin-users"></span>&nbsp; Independent (.css &amp; .js)'
    );

	if (! empty($data['rows_assets'])) {
		// Sorting: parent & non_parent
		$rowsAssets = array('parent' => array(), 'child' => array(), 'independent' => array());

		foreach ($data['rows_assets'] as $handleStatus => $values) {
			$rowsAssets[$handleStatus] = $values;
		}

		foreach ($rowsAssets as $handleStatus => $values) {
            $values = \WpAssetCleanUp\Admin\Sorting::sortAreaAssetRowsValues($values);

			$assetRowIndex = 1;

			$assetRowsOutput = '';

			$totalFiles = 0;

			foreach ($values as $assetRows) {
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
	                <?php echo wp_kses($handleStatusesText[$handleStatus], array('span' => array('class' => array()))); ?> &#10141; <?php esc_html_e('Total files', 'wp-asset-clean-up'); ?>: <?php echo (int)$totalFiles; ?>
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

					<?php if ($handleStatus === 'parent') { ?>
                        <p class="wpacu-assets-note">If you unload any of the files below (if any listed), their 'children' (as listed in green bold font below the handle) will also be unloaded.</p>
					<?php } elseif ($handleStatus === 'child') { ?>
                        <p class="wpacu-assets-note">The following files (if any listed) are 'children' linked to the 'parent' files.</p>
					<?php } elseif ($handleStatus === 'independent') { ?>
                        <p class="wpacu-assets-note">The following files (if any listed) are independent as they are not 'children' or 'parents'.</p>
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
* --------------------------------------------
* [END] BY EACH HANDLE STATUS (Parent or Not)
* --------------------------------------------
*/

include_once __DIR__ . '/_view-common-footer.php';
