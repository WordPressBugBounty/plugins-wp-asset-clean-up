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
$hardcodedTagsOutputList = array('head' => array(), 'body' => array());

$totalHardcodedTags = 0;
$totalHardcodedTagsViaPosition = array('head' => 0, 'body' => 0);

foreach ( $hardcodedTags as $targetKey => $listAssets ) {
    if ( ! in_array($targetKey, array('link_and_style_tags', 'script_src_or_inline_and_noscript_inline_tags')) ) {
        // Go through the tags only; other information should not be included in the loop
        continue;
    }

    foreach ( $listAssets as $indexNo => $tagOutput ) {
        $contentUniqueStr  = HardcodedAssets::determineHardcodedAssetSha1($tagOutput);

        $templateRowOutput = ''; // default (will be updated in the inclusions)

        include __DIR__ . '/_asset-single-row-hardcoded-prepare-data.php';

        if ($templateRowOutput !== '' && isset($dataRowObj->position)) {
            $rowPosition = isset($dataRowObj->position_new) ? $dataRowObj->position_new : $dataRowObj->position;

            $offset = $hardcodedTags['offset'][$targetKey][$indexNo];

            $hardcodedTagsOutputList[$rowPosition][$offset] = $templateRowOutput;

            $totalHardcodedTagsViaPosition[$rowPosition]++;
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

$afterHardcodedTitle = ' &#10141; Total: '. $totalHardcodedTags;
$afterHardcodedTitle .= ' | HEAD tag: '.$totalHardcodedTagsViaPosition['head'].' | BODY tag: '.$totalHardcodedTagsViaPosition['body'];

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

			foreach ($hardcodedTagsOutputList as $position => $outputRows) {
                $totalTagsForTarget = count( $outputRows );
                ?>
					<div>
						<div class="wpacu-content-title wpacu-has-toggle-all-assets">
                            <h3 style="margin-top: 30px;" class="wpacu-title">
								<?php if ($position === 'head') { ?><span class="dashicons dashicons-editor-code"></span>&nbsp; HEAD tag (CSS &amp; JavaScript) &#10141; Total: <?php echo $totalTagsForTarget; ?><?php } ?>
								<?php if ($position === 'body') { ?><span class="dashicons dashicons-editor-code"></span>&nbsp; BODY tag (CSS &amp; JavaScript) &#10141; Total: <?php echo $totalTagsForTarget; ?><?php } ?>
							</h3>

                            <?php if ($totalTagsForTarget > 0) { ?>
                                <div class="wpacu-area-toggle-all-assets wpacu-absolute">
                                    <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                                       data-wpacu-area="hardcoded_<?php echo $position; ?>" href="#">Contract</a>
                                    |
                                    <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                                       data-wpacu-area="hardcoded_<?php echo $position; ?>" href="#">Expand</a>
                                    All Assets
                                </div>
                            <?php } ?>
						</div>
                        <?php if ($totalTagsForTarget > 0) { ?>
                            <div style="padding: 0 15px;">
                                <table class="wpacu_list_table wpacu_striped"
                                       data-wpacu-area="hardcoded_<?php echo $position; ?>">
                                    <tbody>
                                    <?php
                                    foreach ( $outputRows as $outputRow ) {
                                        echo $outputRow."\n";
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else {
                            if ($position === 'head') {
                                $otherPosition = 'body';
                            } else {
                                $otherPosition = 'head';
                            }
                            ?>
                            <p style="margin-top: 0;">There are no assets found within the <code>&lt;<?php echo $position; ?>&gt;</code> tag. Any existing ones might have been moved within the <code>&lt;<?php echo $otherPosition; ?>&gt;</code> tag.</p>
                        <?php }

                        if ($position === 'head') { ?>
                            <hr style="margin: 0;" />
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
