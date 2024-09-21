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
$hardcodedTagsOutputList = array('styles' => array(), 'scripts' => array());

foreach ( $hardcodedTags as $targetKey => $listAssets) {
    if ( ! in_array($targetKey, array('link_and_style_tags', 'script_src_or_inline_and_noscript_inline_tags')) ) {
        // Go through the tags only; other information should not be included in the loop
        continue;
    }

    foreach ( $listAssets as $indexNo => $tagOutput ) {
        $contentUniqueStr = HardcodedAssets::determineHardcodedAssetSha1($tagOutput);

        $templateRowOutput = $assetType = ''; // default (will be filled in the inclusions)

        include __DIR__ . '/_asset-single-row-hardcoded-prepare-data.php';

        if ($templateRowOutput !== '' && $assetType !== '') {
            $hardcodedTagsOutputList[$assetType][] = $templateRowOutput;
        }
    }
}

$totalStyles = count($hardcodedTagsOutputList['styles']);
$totalScripts = count($hardcodedTagsOutputList['scripts']);

$totalHardcodedTags = $totalStyles + $totalScripts;
$afterHardcodedTitle = ' &#10141; Total: '. $totalHardcodedTags.' (Styles: '.$totalStyles.', Scripts: '.$totalScripts.')';

if (isset($data['print_outer_html']) && $data['print_outer_html']) { ?>
<div class="wpacu-assets-collapsible-wrap wpacu-wrap-area wpacu-hardcoded">
    <a class="wpacu-assets-collapsible wpacu-assets-collapsible-active" href="#" style="padding: 15px 15px 15px 44px;">
        <span class="dashicons dashicons-code-standards"></span> Hardcoded (non-enqueued) Styles &amp; Scripts<?php echo $afterHardcodedTitle; ?>
    </a>
    <div class="wpacu-assets-collapsible-content" style="max-height: inherit;">
<?php } ?>
        <div style="padding: 0;">
            <?php
            include_once __DIR__ . '/_common/_harcoded-top-notice.php';

			foreach ($hardcodedTagsOutputList as $assetType => $outputRows) {
					$totalTagsForTarget  = count( $outputRows );
					?>
					<div>
						<div class="wpacu-content-title wpacu-has-toggle-all-assets">
							<h3 class="wpacu-title">
								<?php if ($assetType === 'styles') { ?><span class="dashicons dashicons-admin-appearance"></span> Hardcoded LINK (stylesheet) &amp; STYLE tags &#10141; Total: <?php echo $totalStyles; ?><?php } ?>
								<?php if ($assetType === 'scripts') { ?><span class="dashicons dashicons-media-code"></span> Hardcoded SCRIPT (with "src" attribute &amp; inline) and NOSCRIPT inline tags &#10141; Total: <?php echo $totalScripts; ?><?php } ?>
							</h3>

                            <div class="wpacu-area-toggle-all-assets wpacu-absolute">
                                <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                                   data-wpacu-area="hardcoded_<?php echo $assetType; ?>" href="#">Contract</a>
                                |
                                <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                                   data-wpacu-area="hardcoded_<?php echo $assetType; ?>" href="#">Expand</a>
                                All Assets
                            </div>
						</div>
						<table style="padding: 0 10px;"
                               class="wpacu_list_table wpacu_striped"
                               data-wpacu-area="hardcoded_<?php echo $assetType; ?>">
							<tbody>
							<?php
							foreach ( $outputRows as $outputRow ) {
								echo $outputRow."\n";
							}
							?>
							</tbody>
						</table>
					</div>
                    <?php if ($assetType === 'styles') { ?>
                        <hr style="margin: 12px 0 10px;" />
					<?php } else { ?>
                        <div style="margin: 12px 0;"></div>
                    <?php }
				}
			?>
        </div>
<?php if (isset($data['print_outer_html']) && $data['print_outer_html']) { ?>
    </div>
</div>
<?php }
