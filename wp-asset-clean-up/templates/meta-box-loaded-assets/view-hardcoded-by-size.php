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
$hardcodedTagsOutputList = array('with_size' => array(), 'external_na' => array());

$totalHardcodedTags = 0;
$totalHardcodedTagsViaSize = array('with_size' => 0, 'external_na' => 0);

foreach ( $hardcodedTags as $targetKey => $listAssets) {
    if ( ! in_array($targetKey, array('link_and_style_tags', 'script_src_or_inline_and_noscript_inline_tags')) ) {
        // Go through the tags only; other information should not be included in the loop
        continue;
    }

    foreach ( $listAssets as $indexNo => $tagOutput ) {
        $contentUniqueStr  = HardcodedAssets::determineHardcodedAssetSha1($tagOutput);

        $templateRowOutput = ''; // default (will be updated in the inclusions)

        include __DIR__ . '/_asset-single-row-hardcoded-prepare-data.php';

        if ($templateRowOutput !== '' && isset($returnData, $dataRowObj->handle)) {
            $sizeStatus = 'with_size'; // default

            $forType = isset($dataRowObj->src) && $dataRowObj->src  ? 'file' : 'tag';

            if ($forType === 'file') {
                $objOrString = $dataRowObj;
            } else {
                $objOrString = $tagOutput;
            }

            $sizeRaw = (int)apply_filters('wpacu_get_asset_size', $objOrString, 'raw', $forType);

            if ( $sizeRaw === 0 ) {
                $sizeStatus = 'external_na';
            }

            $hardcodedTagsOutputList[$sizeStatus][$sizeRaw][] = $templateRowOutput;

            $totalHardcodedTagsViaSize[$sizeStatus]++;
            $totalHardcodedTags++;
        }
    }
}

if ( ! empty($hardcodedTagsOutputList['with_size']) ) {
    krsort($hardcodedTagsOutputList['with_size']);
}

if ( ! empty($hardcodedTagsOutputList['external_na']) ) {
    krsort($hardcodedTagsOutputList['external_na']);
}

$afterHardcodedTitle  = ' &#10141; Total: '. $totalHardcodedTags;
$afterHardcodedTitle .= '';

if (isset($data['print_outer_html']) && $data['print_outer_html']) {
    ?>
<div class="wpacu-assets-collapsible-wrap wpacu-wrap-area wpacu-hardcoded">
    <a class="wpacu-assets-collapsible wpacu-assets-collapsible-active" href="#" style="padding: 15px 15px 15px 44px;">
        <span class="dashicons dashicons-code-standards"></span> Hardcoded (non-enqueued) Styles &amp; Scripts<?php echo $afterHardcodedTitle; ?>
    </a>
    <div class="wpacu-assets-collapsible-content" style="max-height: inherit;">
<?php } ?>
        <div style="padding: 0;">
            <?php
            include_once __DIR__ . '/_common/_harcoded-top-notice.php';

			foreach ($hardcodedTagsOutputList as $sizeStatus => $outputRowsMain) {
                $totalTagsForTarget = count( $outputRowsMain, COUNT_RECURSIVE ) - count( $outputRowsMain );
                ?>
					<div>
						<div class="wpacu-content-title wpacu-has-toggle-all-assets">
							<h3 class="wpacu-title">
								<?php if ($sizeStatus === 'with_size') { ?>&#8600; &nbsp;Ordered by size in descending order (CSS &amp; JavaScript) &#10141; Total: <?php echo $totalTagsForTarget; ?><?php } ?>
								<?php if ($sizeStatus === 'external_na') { ?>&#8600; &nbsp;External (CSS &amp; JavaScript) &#10141; Total: <?php echo $totalTagsForTarget; ?><?php } ?>
							</h3>

                            <?php if ($totalTagsForTarget > 0) { ?>
                                <div class="wpacu-area-toggle-all-assets wpacu-absolute">
                                    <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                                       data-wpacu-area="hardcoded_<?php echo $sizeStatus; ?>" href="#">Contract</a>
                                    |
                                    <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                                       data-wpacu-area="hardcoded_<?php echo $sizeStatus; ?>" href="#">Expand</a>
                                    All Assets
                                </div>
                            <?php } ?>
						</div>
                        <?php if ($totalTagsForTarget > 0) { ?>
                            <div style="padding: 0 15px;">
                                <table class="wpacu_list_table wpacu_striped"
                                       data-wpacu-area="hardcoded_<?php echo $sizeStatus; ?>">
                                    <tbody>
                                    <?php
                                    foreach ($outputRowsMain as $outputRows) {
                                        foreach ($outputRows as $outputRow) {
                                            echo $outputRow . "\n";
                                        }
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } elseif ($sizeStatus === 'with_size') { ?>
                            <p style="margin-top: 0;">There are no hardcoded tags with their actual size detectable (e.g. they are external, from a different domain name).</p>
                        <?php } else { ?>
                            <p style="margin-top: 0;">There are no external hardcoded tags having their size undetectable.</p>
                        <?php }

                        if ($sizeStatus === 'with_size') { ?>
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
