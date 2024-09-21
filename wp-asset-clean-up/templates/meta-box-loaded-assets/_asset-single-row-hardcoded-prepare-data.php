<?php
use WpAssetCleanUp\Admin\MainAdmin;
use WpAssetCleanUp\HardcodedAssets;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

if ( ! isset($data, $targetKey, $tagOutput, $contentUniqueStr, $contentWithinConditionalComments, $indexNo, $hardcodedTags, $handlesInfo) ) {
    exit(); // no direct access
}

if ($targetKey === 'link_and_style_tags') {
    $assetType = 'styles';
} elseif ($targetKey === 'script_src_or_inline_and_noscript_inline_tags') {
    $assetType = 'scripts';
} else {
    return;
}

$data['asset_type'] = $assetType;

$assetTypeS        = substr($assetType, 0, -1); // "styles" to "style" & "scripts" to "script"
$assetTypeAbbr     = $assetType === 'styles' ? 'css' : 'js';

$templateRowOutput = $generatedHandle = $handlePrefix = '';

if ($assetType === 'styles') {
    /*
    * Hardcoded LINK (stylesheet) &amp; STYLE tags
    */
    // For LINK ("stylesheet")
    if (strncasecmp($tagOutput, '<link ', 6) === 0 ) {
        $handlePrefix     = HardcodedAssets::$handleLinkPrefix;
        $generatedHandle  = $handlePrefix . $contentUniqueStr;
        $linkHrefOriginal = '';

        // could be href="value_here" or href  = "value_here" (with extra spaces) / make sure it matches
        if ( preg_match('# href(\s+|)=(\s+|)#Umi', $tagOutput) ) {
            $linkHrefOriginal = Misc::getValueFromTag($tagOutput);

            // No room for any mistakes, do not print the cached files
            if (strpos($linkHrefOriginal, OptimizeCommon::getRelPathPluginCacheDir()) !== false) {
                return;
            }
        }

        $dataRowObj = (object) array(
            'handle'     => $generatedHandle,
            'src'        => $linkHrefOriginal,
            'tag_output' => $tagOutput
        );

        $dataRowObj->srcHref = Misc::getHrefFromSource($linkHrefOriginal);
    }

    // For STYLE (inline)
    elseif (strncasecmp($tagOutput, '<style', 6) === 0 ) {
        $handlePrefix    = HardcodedAssets::$handleStylePrefix;
        $generatedHandle = $handlePrefix . $contentUniqueStr;

        $dataRowObj = (object) array(
            'handle'     => $generatedHandle,
            'src'        => false,
            'tag_output' => $tagOutput
        );
    }
} else {
    /*
     * Hardcoded SCRIPT (with "src" attribute & inline) or Hardcoded NOSCRIPT inline tags
    */
    $generatedHandle = $srcHrefOriginal = false;

    if (strncasecmp($tagOutput, '<script', 7) === 0 ) {
        if ( preg_match( '# src(\s+|)=(\s+|)#Umi', $tagOutput ) ) {
            $srcHrefOriginal = Misc::getValueFromTag( $tagOutput, '', 'dom_with_fallback' );
        }

        if ( $srcHrefOriginal ) {
            // No room for any mistakes, do not print the cached files
            if ( strpos( $srcHrefOriginal, OptimizeCommon::getRelPathPluginCacheDir() ) !== false ) {
                return;
            }

            $handlePrefix    = HardcodedAssets::$handleScriptSrcPrefix;
            $generatedHandle = $handlePrefix . $contentUniqueStr;
        }

        // Is it a SCRIPT without "src" attribute? Then it's an inline one
        if ( ! $generatedHandle ) {
            $handlePrefix    = HardcodedAssets::$handleScriptInlinePrefix;
            $generatedHandle = $handlePrefix . $contentUniqueStr;
        }
    } elseif (strncasecmp($tagOutput, '<noscript', 9) === 0 ) {
        $handlePrefix    = HardcodedAssets::$handleNoScriptInlinePrefix;
        $generatedHandle = $handlePrefix . $contentUniqueStr;
    }

    if ( ! $generatedHandle ) {
        return;
    }

    $dataRowObj = (object)array(
        'handle'     => $generatedHandle,
        'tag_output' => $tagOutput
    );

    if ($srcHrefOriginal) {
        $dataRowObj->src = $srcHrefOriginal;
        $dataRowObj->srcHref = Misc::getHrefFromSource($srcHrefOriginal);
    }
}

if ( ! empty($dataRowObj) && $generatedHandle && $handlePrefix ) {
    $dataRowObj->position = isset($hardcodedTags['positions'][$targetKey]) ?
        HardcodedAssets::getTagPositionHeadOrBody($indexNo, $hardcodedTags['positions'][$targetKey])
        : ''; // In very rare cases, the position could not be determined, thus avoid any errors

    $dataRowObj = apply_filters('wpacu_pro_get_position_new', $dataRowObj, $assetType);

    // The $tagOutput will be minified ('output_min' key) only after submit (to save resources)
    $wpacuHardcodedInfoToStoreAfterSubmit = array(
        'handle' => $generatedHandle,
        'output' => $tagOutput
    );

    $dataRowObj->inside_conditional_comment = HardcodedAssets::isWithinConditionalComment($tagOutput, $contentWithinConditionalComments);

    if ($dataRowObj->inside_conditional_comment) {
        $wpacuHardcodedInfoToStoreAfterSubmit['cond_comm'] = $dataRowObj->inside_conditional_comment;
    }

    $dataRowObj->hardcoded_data = base64_encode(wp_json_encode($wpacuHardcodedInfoToStoreAfterSubmit));

    $dataHH = HardcodedAssets::wpacuGenerateHardcodedAssetData($dataRowObj, $data, $assetType);

    if ( ! empty($dataHH) ) {
        $parseTemplate = MainAdmin::instance()->parseTemplate(
            '/meta-box-loaded-assets/_asset-single-row-hardcoded',
            $dataHH,
            false,
            true
        );

        $templateRowOutput = $parseTemplate['output'];
        $returnData        = $parseTemplate['data'];
    }
}
