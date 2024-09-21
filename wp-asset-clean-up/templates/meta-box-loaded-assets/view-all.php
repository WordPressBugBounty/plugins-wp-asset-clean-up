<?php
// no direct access
use WpAssetCleanUp\Admin\MiscAdmin;

if (! isset($data)) {
	exit;
}

$listAreaStatus = $data['plugin_settings']['assets_list_layout_areas_status'];

/*
* ------------------------------
* [START] STYLES & SCRIPTS LIST
* ------------------------------
*/
require_once __DIR__.'/_assets-top-area.php';


$data['rows_build_array'] = true;
$data['rows_assets'] = $assetRowsOutputsArray = array();

require_once __DIR__.'/_asset-rows.php';

if ( ! empty($data['rows_assets']) ) {
    $values = \WpAssetCleanUp\Admin\Sorting::sortAreaAssetRowsValues($data['rows_assets']['all']);

    $totalStyles = $totalScripts = 0;

    foreach ($values as $assetRows) {
        $assetRowsOutput = '';

        foreach ($assetRows as $assetTypeS => $assetRow) {
            $assetRowsOutput .= $assetRow . "\n";

            if (strpos($assetRow, 'wpacu_this_asset_row_area_is_hidden') === false) {
                if ($assetTypeS === 'style') {
                    $totalStyles++;
                } else {
                    $totalScripts++;
                }
            }
        }

        $assetRowsOutputsArray[] = $assetRowsOutput;
    }
?>
    <div class="wpacu-assets-collapsible-wrap wpacu-wrap-all">
        <a style="padding: 15px;" class="wpacu-assets-collapsible <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-assets-collapsible-active<?php } ?>" href="#wpacu-assets-collapsible-content">
            <?php esc_html_e('Styles (.css files) &amp; Scripts (.js files)', 'wp-asset-clean-up'); ?> &#10141; <?php esc_html_e('Total enqueued (+ core files)', 'wp-asset-clean-up'); ?>: <?php echo $totalStyles + $totalScripts; ?> (Styles: <?php echo $totalStyles; ?>, Scripts: <?php echo $totalScripts; ?>)
        </a>

        <div id="wpacu-assets-collapsible-content"
             class="wpacu-assets-collapsible-content <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-open<?php } ?>">
            <div class="wpacu-area-toggle-all-assets wpacu-right">
                <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                   data-wpacu-area="all_assets" href="#">Contract</a>
                |
                <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                   data-wpacu-area="all_assets" href="#">Expand</a>
                All Assets
            </div>
            <div>
                <table class="wpacu_list_table wpacu_widefat wpacu_striped"
                       data-wpacu-area="all_assets">
                    <tbody>
                    <?php
                    foreach ($assetRowsOutputsArray as $assetRowsOutput) {
                        echo MiscAdmin::stripIrrelevantHtmlTags($assetRowsOutput);
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
}
/*
 * -----------------------------
 * [END] STYLES & SCRIPTS LIST
 * -----------------------------
 */

include_once __DIR__ . '/_view-common-footer.php';
