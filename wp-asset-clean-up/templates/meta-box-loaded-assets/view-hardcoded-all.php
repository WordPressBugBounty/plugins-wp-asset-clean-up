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
$allHardcodedTagsList = array();

$totalStyles = $totalScripts = 0;

foreach ( $hardcodedTags as $targetKey => $listAssets) {
    if ( ! in_array($targetKey, array('link_and_style_tags', 'script_src_or_inline_and_noscript_inline_tags')) ) {
        // Go through the tags only; other information should not be included in the loop
        continue;
    }

    foreach ( $listAssets as $indexNo => $tagOutput ) {
        $contentUniqueStr = HardcodedAssets::determineHardcodedAssetSha1($tagOutput);

        $templateRowOutput = $assetType = ''; // default (will be filled in the inclusions)

        include __DIR__ . '/_asset-single-row-hardcoded-prepare-data.php';

        if ($templateRowOutput !== '') {
            if ($assetType === 'styles') {
                $totalStyles++;
            } elseif ($assetType === 'scripts') {
                $totalScripts++;
            }

            $offset = $hardcodedTags['offset'][$targetKey][$indexNo];
            $allHardcodedTagsList[$offset] = $templateRowOutput;
        }
    }
}

ksort($allHardcodedTagsList);

$totalHardcodedTags  = count($allHardcodedTagsList);
$afterHardcodedTitle = ' &#10141; Total: '. $totalHardcodedTags .' (Styles: '.$totalStyles.', Scripts: '.$totalScripts.')';

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
        ?>
        <div>
            <div class="wpacu-content-title wpacu-has-toggle-all-assets">
                <div class="wpacu-area-toggle-all-assets wpacu-right" style="padding: 0 16px 16px; margin-top: -10px;">
                    <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"
                       data-wpacu-area="hardcoded_all" href="#">Contract</a>
                    |
                    <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"
                       data-wpacu-area="hardcoded_all" href="#">Expand</a>
                    All Assets
                </div>
            </div>

            <div style="padding: 0 15px;">
                <table class="wpacu_list_table wpacu_striped"
                       data-wpacu-area="hardcoded_all">
                    <tbody>
                    <?php
                    foreach ($allHardcodedTagsList as $offset => $outputRow) {
                        echo $outputRow."\n";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php if (isset($data['print_outer_html']) && $data['print_outer_html']) { ?>
    </div>
</div>
<?php }
