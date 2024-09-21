<?php
// no direct access
use WpAssetCleanUp\Admin\MiscAdmin;

if (! isset($data)) {
	exit;
}

$listAreaStatus = $data['plugin_settings']['assets_list_layout_areas_status'];

/*
* --------------------------------------
* [START] BY (ANY) RULES SET (yes or no)
* --------------------------------------
*/

if (! empty($data['all']['styles']) || ! empty($data['all']['scripts'])) {
    require_once __DIR__.'/_assets-top-area.php';

	$data['rows_build_array'] =
	$data['rows_by_rules'] = true;

	$data['rows_assets'] = array();

	require_once __DIR__.'/_asset-rows.php';

	$rulesText = array(
        'with_rules'    => '<span class="dashicons dashicons-star-filled"></span>&nbsp; '.esc_html__('Styles &amp; Scripts with at least one rule', 'wp-asset-clean-up'),
        'with_no_rules' => '<span class="dashicons dashicons-star-empty"></span>&nbsp; '.esc_html__('Styles &amp; Scripts without any rules', 'wp-asset-clean-up')
    );

	if (! empty($data['rows_assets'])) {
		// Sorting: With (any) rules and without rules (loaded and without alterations to the tags such as async/defer attributes)
		$rowsAssets = array('with_rules' => array(), 'with_no_rules' => array());

		foreach ($data['rows_assets'] as $rulesStatus => $values) {
			$rowsAssets[$rulesStatus] = $values;
		}

		foreach ($rowsAssets as $rulesStatus => $values) {
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
            <div class="wpacu-assets-collapsible-wrap wpacu-by-rules wpacu-wrap-area wpacu-<?php echo esc_attr($rulesStatus); ?>">
                <a class="wpacu-assets-collapsible <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-assets-collapsible-active<?php } ?>" href="#wpacu-assets-collapsible-content-<?php echo esc_attr($rulesStatus); ?>">
					<?php echo wp_kses($rulesText[$rulesStatus], array('span' => array('class' => array()))); ?> &#10141; <?php esc_html_e('Total enqueued files', 'wp-asset-clean-up'); ?>: <?php echo (int)$totalFiles; ?>
                </a>

                <div class="wpacu-assets-collapsible-content <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-open<?php } ?>">
	                <?php if (count($values) > 0) { ?>
                        <div class="wpacu-area-toggle-all-assets wpacu-right">
                            <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="<?php echo esc_html($rulesStatus); ?>_assets" href="#">Contract</a>
                            |
                            <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="<?php echo esc_html($rulesStatus); ?>_assets" href="#">Expand</a>
                            All Assets
                        </div>
	                <?php } ?>

					<?php if ($rulesStatus === 'with_rules') { ?>
                        <p class="wpacu-assets-note">This is the list of enqueued CSS &amp; JavaScript files that have AT LEAST ONE RULE applied to them on this page. The rule could be one of the following: unloaded, preloaded, async/defer attributes applied &amp; changed location (e.g. from HEAD to BODY or vice-versa).</p>
					    <?php
                        if (count($values) < 1) {
                        ?>
                            <p style="padding: 0 15px 15px;"><strong>No rules were applied to any of the enqueued CSS/JS files from this page.</strong></p>
                        <?php
                        }
                        ?>
                    <?php } elseif ($rulesStatus === 'with_no_rules') { ?>
                        <p class="wpacu-assets-note">This is the list of enqueued CSS &amp; JavaScript files that have NO RULES applied to them on this page. They are loaded by default in their original location (e.g. HEAD or BODY) without any attributes applied to them (e.g. async/defer).</p>
					<?php } ?>

					<?php if (count($values) > 0) { ?>
                        <table class="wpacu_list_table wpacu_list_by_rules wpacu_widefat wpacu_striped"
                               data-wpacu-area="<?php echo esc_html($rulesStatus); ?>_assets">
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
* -------------------------------------
* [END] BY (ANY) RULES SET (yes or no)
* -------------------------------------
*/

include_once __DIR__ . '/_view-common-footer.php';
