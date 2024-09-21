<?php

use WpAssetCleanUp\Admin\MiscAdmin;

// no direct access
if (! isset($data)) {
	exit();
}

$listAreaStatus = $data['plugin_settings']['assets_list_layout_areas_status'];

require_once __DIR__.'/_assets-top-area.php';

/*
*
* --------------------------------------
* [START] BY (ANY) RULES SET (yes or no)
* --------------------------------------
*/

if (! empty($data['all']['styles']) || ! empty($data['all']['scripts'])) {
    require_once __DIR__.'/_assets-top-area.php';

    $data['rows_build_array'] =
    $data['rows_by_asset_type'] = true;

    $data['rows_assets'] = array();

    require_once __DIR__.'/_asset-rows.php';

    $rulesText = array(
        'styles'  => '<span class="dashicons dashicons-admin-appearance"></span>&nbsp; '.esc_html__('Styles (.css files)', 'wp-asset-clean-up'),
        'scripts' => '<span class="dashicons dashicons-media-code"></span>&nbsp; '.esc_html__('Scripts (.js files)', 'wp-asset-clean-up')
    );

    if ( ! empty($data['rows_assets']) ) {
        // Sorting: With (any) rules and without rules (loaded and without alterations to the tags such as async/defer attributes)
        $rowsAssets = array('styles' => array(), 'scripts' => array());

        foreach ($data['rows_assets'] as $assetType => $values) {
            $rowsAssets[$assetType] = $values;
        }

        foreach ($rowsAssets as $assetType => $values) {
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
            <div class="wpacu-assets-collapsible-wrap wpacu-by-rules wpacu-wrap-area wpacu-<?php echo esc_attr($assetType); ?>">
                <a class="wpacu-assets-collapsible <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-assets-collapsible-active<?php } ?>" href="#wpacu-assets-collapsible-content-<?php echo esc_attr($assetType); ?>">
                    <?php echo wp_kses($rulesText[$assetType], array('span' => array('class' => array()))); ?> &#10141; <?php esc_html_e('Total enqueued files', 'wp-asset-clean-up'); ?>: <?php echo (int)$totalFiles; ?>
                </a>

                <div id="wpacu-assets-styles-collapsible-content"
                     class="wpacu-assets-collapsible-content <?php if ($listAreaStatus !== 'contracted') { ?>wpacu-open<?php } ?>">
                    <?php if ( ! empty($data['all'][$assetType]) ) { ?>
                        <div class="wpacu-area-toggle-all-assets wpacu-right">
                            <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="all_<?php echo $assetType; ?>_assets" href="#">Contract</a>
                            |
                            <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                               data-wpacu-area="all_<?php echo $assetType; ?>_assets" href="#">Expand</a>
                            All Assets
                        </div>
                    <?php } ?>

                    <div>
                        <?php
                        if ( ! empty($data['all'][$assetType]) ) {
                            ?>
                            <table class="wpacu_list_table wpacu_widefat wpacu_striped"
                                   data-wpacu-area="all_<?php echo $assetType; ?>_assets">
                                <tbody>
                                <?php
                                echo MiscAdmin::stripIrrelevantHtmlTags($assetRowsOutput);
                                ?>
                                </tbody>
                            </table>
                            <?php
                        } else {
                            if ($assetType === 'styles') {
                                echo __('It looks like there are no public .css files loaded or the ones visible do not follow <a href="https://codex.wordpress.org/Function_Reference/wp_enqueue_style">the WordPress way of enqueuing styles</a>.', 'wp-asset-clean-up');
                            }

                            if ($assetType === 'scripts') {
                                echo __('It looks like there are no public .js files loaded or the ones visible do not follow <a href="https://codex.wordpress.org/Function_Reference/wp_enqueue_script">the WordPress way of enqueuing scripts</a>.', 'wp-asset-clean-up');
                            }
                        }
                        ?>
                    </div>
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
