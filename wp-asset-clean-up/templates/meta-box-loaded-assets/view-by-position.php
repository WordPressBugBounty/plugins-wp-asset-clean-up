<?php
// no direct access
use WpAssetCleanUp\Admin\MiscAdmin;

if (! isset($data)) {
	exit;
}

$listAreaStatus = $data['plugin_settings']['assets_list_layout_areas_status'];

/*
* -------------------------
* [START] BY EACH POSITION
* -------------------------
*/
if (! empty($data['all']['styles']) || ! empty($data['all']['scripts'])) {
	require_once __DIR__.'/_assets-top-area.php';

    $data['rows_build_array'] =
    $data['rows_by_position'] = true;

    $data['rows_assets'] = array();

    require_once __DIR__.'/_asset-rows.php';

    $positionsText = array(
        'head' => '<span class="dashicons dashicons-editor-code"></span>&nbsp; HEAD tag (.css &amp; .js)',
        'body' => '<span class="dashicons dashicons-editor-code"></span>&nbsp; BODY tag (.css &amp; .js)'
    );

    if (! empty($data['rows_assets'])) {
        // Sorting: head and body
        $rowsAssets = array('head' => array(), 'body' => array());

        foreach ($data['rows_assets'] as $positionMain => $values) {
            $rowsAssets[$positionMain] = $values;
        }

        foreach ($rowsAssets as $positionMain => $values) {
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
            <div class="wpacu-assets-collapsible-wrap wpacu-by-position wpacu-wrap-area wpacu-<?php echo esc_attr($positionMain); ?>">
                <a class="wpacu-assets-collapsible <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-assets-collapsible-active<?php } ?>" href="#wpacu-assets-collapsible-content-<?php echo esc_attr($positionMain); ?>">
	                <?php echo wp_kses($positionsText[$positionMain], array('span' => array('class' => array()))); ?> &#10141; Total files: <?php echo $totalFiles; ?>
                </a>

                <div class="wpacu-assets-collapsible-content <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-open<?php } ?>">
                    <?php if (count($values) > 0) { ?>
                        <div class="wpacu-area-toggle-all-assets wpacu-right">
                            <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="<?php echo esc_html($positionMain); ?>_assets" href="#">Contract</a>
                            |
                            <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="<?php echo esc_html($positionMain); ?>_assets" href="#">Expand</a>
                            All Assets
                        </div>
                    <?php } ?>

                    <?php if ($positionMain === 'head') { ?>
                        <p class="wpacu-assets-note">The files below (if any) are loaded within <em>&lt;head&gt;</em> and <em>&lt;/head&gt;</em> tags. The output is done through <em>wp_head()</em> WordPress function which should be located before the closing <em>&lt;/head&gt;</em> tag of your theme.</p>
                    <?php } elseif ($positionMain === 'body') { ?>
                        <p class="wpacu-assets-note">The files below (if any) are loaded within <em>&lt;body&gt;</em> and <em>&lt;/body&gt;</em> tags. The output is done through <em>wp_footer()</em> WordPress function which should be located before the closing <em>&lt;/body&gt;</em> tag of your theme.</p>
                    <?php } ?>

                    <?php if (count($values) > 0) { ?>
                        <table class="wpacu_list_table wpacu_list_by_position wpacu_widefat wpacu_striped"
                               data-wpacu-area="<?php echo esc_html($positionMain); ?>_assets">
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
* -----------------------
* [END] BY EACH POSITION
* -----------------------
*/

include_once __DIR__ . '/_view-common-footer.php';
