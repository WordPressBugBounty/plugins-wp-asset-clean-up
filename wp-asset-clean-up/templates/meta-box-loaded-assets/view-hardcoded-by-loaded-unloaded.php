<?php
use WpAssetCleanUp\HardcodedAssets;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\ObjectCache;

if (! isset($data)) {
	exit; // no direct access
}

$totalFoundHardcodedTags = 0;
$hardcodedTags = $data['all']['hardcoded'];

$contentWithinConditionalComments = ObjectCache::wpacu_cache_get('wpacu_hardcoded_content_within_conditional_comments');

$totalFoundHardcodedTags  = isset($hardcodedTags['link_and_style_tags']) ? count($hardcodedTags['link_and_style_tags']) : 0;
$totalFoundHardcodedTags += isset($hardcodedTags['script_src_or_inline_and_noscript_inline_tags'])
                            ? count($hardcodedTags['script_src_or_inline_and_noscript_inline_tags']) : 0;

if ($totalFoundHardcodedTags === 0) {
	return; // Don't print anything if there are no hardcoded tags available
}

$handlesInfo = Main::getHandlesInfo();

// Fetch all output rows under an array
$hardcodedTagsOutputList = array('load' => array(), 'unload' => array());

$totalHardcodedTags = 0;
$totalHardcodedTagsViaLoadUnload = array('load' => 0, 'unload' => 0);

foreach ( $hardcodedTags as $targetKey => $listAssets) {
    if ( ! in_array($targetKey, array('link_and_style_tags', 'script_src_or_inline_and_noscript_inline_tags')) ) {
        // Go through the tags only; other information should not be included in the loop
        continue;
    }

    foreach ( $listAssets as $indexNo => $tagOutput ) {
        $contentUniqueStr  = HardcodedAssets::determineHardcodedAssetSha1($tagOutput);

        $templateRowOutput = $assetType = ''; // default (will be updated in the inclusions)

        include __DIR__ . '/_asset-single-row-hardcoded-prepare-data.php';

        if ($templateRowOutput !== '' && isset($returnData, $dataRowObj->handle)) {
            $loadUnloadStatus = 'load'; // default

            if (isset($returnData['current_unloaded_all'][$assetType]) && in_array($dataRowObj->handle, $data['current_unloaded_all'][$assetType])) {
                $loadUnloadStatus = 'unload';
            } else {
                $handleStatus = ( strpos( $returnData['row']['class'], 'wpacu_not_load' ) !== false ) ? 'unloaded' : 'loaded';
            }

            $offset = $hardcodedTags['offset'][$targetKey][$indexNo];

            $hardcodedTagsOutputList[$loadUnloadStatus][$offset] = $templateRowOutput;

            $totalHardcodedTagsViaLoadUnload[$loadUnloadStatus]++;
            $totalHardcodedTags++;
        }
    }
}

if ( ! empty($hardcodedTagsOutputList['head']) ) {
    ksort($hardcodedTagsOutputList['head']);
}

if ( ! empty($hardcodedTagsOutputList['body']) ) {
    ksort($hardcodedTagsOutputList['body']);
}

$dashIconLoaded   = '<span style="color: green;" class="dashicons dashicons-yes"></span>';
$dashIconUnloaded = '<span style="color: #cc0000;" class="dashicons dashicons-no-alt"></span>';

$afterHardcodedTitle = ' &#10141; Total: '. $totalHardcodedTags;
$afterHardcodedTitle .= ' | '.$dashIconLoaded.'&nbsp;Loaded: '.$totalHardcodedTagsViaLoadUnload['load'].' | '.$dashIconUnloaded.'&nbsp;Unloaded: '.$totalHardcodedTagsViaLoadUnload['unload'];

if (isset($data['print_outer_html']) && $data['print_outer_html']) {
    ?>
<div class="wpacu-assets-collapsible-wrap wpacu-wrap-area wpacu-hardcoded">
    <a class="wpacu-assets-collapsible wpacu-assets-collapsible-active" href="#" style="padding: 15px 15px 15px 44px;">
        <span class="dashicons dashicons-code-standards"></span> Hardcoded (non-enqueued) Styles &amp; Scripts<?php echo $afterHardcodedTitle; ?>
    </a>
    <div class="wpacu-assets-collapsible-content" style="max-height: inherit;">
<?php } ?>
        <div style="padding: 0">
            <?php
            include_once __DIR__ . '/_common/_harcoded-top-notice.php';

			foreach ($hardcodedTagsOutputList as $loadUnloadStatus => $outputRows) {
                $totalTagsForTarget = count( $outputRows );
                ?>
					<div>
						<div class="wpacu-content-title wpacu-has-toggle-all-assets">
							<h3 class="wpacu-title">
								<?php if ($loadUnloadStatus === 'load') { ?><?php echo $dashIconLoaded; ?> Loaded (CSS &amp; JavaScript) &#10141; Total: <?php echo $totalTagsForTarget; ?><?php } ?>
								<?php if ($loadUnloadStatus === 'unload') { ?><?php echo $dashIconUnloaded; ?> Unloaded (CSS &amp; JavaScript) &#10141; Total: <?php echo $totalTagsForTarget; ?><?php } ?>
							</h3>

                            <?php if ($totalTagsForTarget > 0) { ?>
                                <div class="wpacu-area-toggle-all-assets wpacu-absolute">
                                    <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                                       data-wpacu-area="hardcoded_<?php echo $loadUnloadStatus; ?>" href="#">Contract</a>
                                    |
                                    <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                                       data-wpacu-area="hardcoded_<?php echo $loadUnloadStatus; ?>" href="#">Expand</a>
                                    All Assets
                                </div>
                            <?php } ?>
						</div>
                        <?php if ($totalTagsForTarget > 0) { ?>
                            <div style="padding: 0 15px;">
                                <table class="wpacu_list_table wpacu_striped"
                                       data-wpacu-area="hardcoded_<?php echo $loadUnloadStatus; ?>">
                                    <tbody>
                                    <?php
                                    foreach ( $outputRows as $outputRow ) {
                                        echo $outputRow."\n";
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } elseif ($loadUnloadStatus === 'load') { ?>
                            <p style="margin-top: 0; margin-left: 10px;">There are no loaded hardcoded tags as they are either all unloaded, or there was none loaded in the first place.</p>
                        <?php } else { ?>
                            <p style="margin-top: 0; margin-left: 10px;">There are no unloaded hardcoded tags.</p>
                        <?php }

                        if ($loadUnloadStatus === 'load') { ?>
                            <hr style="margin: 12px 0 10px;" />
                        <?php } else { ?>
                            <div style="margin: 12px 0;"></div>
                        <?php } ?>
					</div>
                <?php
				}
			?>
        </div>
<?php if (isset($data['print_outer_html']) && $data['print_outer_html']) { ?>
    </div>
</div>
<?php }
