<?php
/*
 * The file is included from /templates/meta-box-loaded-assets/_asset-single-row/_asset-single-row-hardcoded.php
*/

use WpAssetCleanUp\Misc;

if ( ! isset($data, $assetType, $assetTypeS) ) {
    exit(); // no direct access
}

if ( ! (isset($data['row']['obj']->src, $data['row']['obj']->srcHref) && $data['row']['obj']->srcHref && trim($data['row']['obj']->src) !== '') ) {
    return;
}

$isExternalSrc      = true;
$isBase64EncodedSrc = false; // default

if ($assetTypeS === 'style') {
    $isBase64EncodedSrc = stripos($data['row']['obj']->src, 'data:text/css;base64,') !== false;
} else {
    $isBase64EncodedSrc = stripos($data['row']['obj']->src, 'data:text/javascript;base64,') !== false;
}

if ($isBase64EncodedSrc // the actual code is added as a base64 encoded way in the "src" / "href" (there's no .js or .css file)
    || Misc::getLocalSrcIfExist($data['row']['obj']->src)
    || strpos($data['row']['obj']->src, '/?') !== false // Dynamic Local URL
    || strncmp(str_replace(site_url(), '', $data['row']['obj']->src), '?', 1) === 0 // Starts with ? right after the site url (it's a local URL)
) {
    $isExternalSrc = false;
}

if ( ! $isBase64EncodedSrc ) {
    $srcHref = $data['row']['obj']->srcHref;

    // If the source starts with '../' mark it as external to be checked via the AJAX call (special case)
    if (strncmp($srcHref, '../', 3) === 0) {
        $currentPageUrl = Misc::getCurrentPageUrl();
        $srcHref        = trim($currentPageUrl, '/') . '/' . $data['row']['obj']->srcHref;
        $isExternalSrc  = true; // simulation
    }

    $relSrc = str_replace(site_url(), '', $data['row']['obj']->src);

    if (isset($data['row']['obj']->baseUrl)) {
        $relSrc = str_replace($data['row']['obj']->baseUrl, '/', $relSrc);
    }
}
?>
<div class="wpacu-source-row">
    <?php _e( 'Source:', 'wp-asset-clean-up' ); ?>
    <?php
    if ( ! $isBase64EncodedSrc ) {
    ?>
        <a target="_blank"
           style="color: green;" <?php if ( $isExternalSrc ) { ?> data-wpacu-external-source="<?php echo esc_attr($srcHref); ?>" <?php } ?>
           href="<?php echo esc_attr($data['row']['obj']->src); ?>"><?php echo esc_html($relSrc); ?></a>
        <?php if ( $isExternalSrc ) { ?><span data-wpacu-external-source-status></span><?php } ?>
    <?php } else {
        // Extract base64 encoded data and decode it
        if ($assetTypeS === 'style') {
            $dataToCheck = 'data:text/css;base64,';
            $viewDecodedText = __('View Decoded CSS', 'wp-asset-clean-up');
        } else {
            $dataToCheck = 'data:text/javascript;base64,';
            $viewDecodedText = __('View Decoded JS', 'wp-asset-clean-up');
        }

        $base64Encoded = str_replace($dataToCheck, '', $data['row']['obj']->src);
        $decodedSource = base64_decode($base64Encoded);

        $viewDecodedBase64Unique = 'wpacu-view-decoded-base64-format-' . $assetTypeS . '-' . sha1($data['row']['obj']->src) . '-'. wp_unique_id();
        ?>
            <?php if ($assetTypeS === 'style') { ?>
                The "href" attribute is not pointing to an actual file and contains CSS code in Base64 format (it starts with "<em><?php echo $dataToCheck; ?></em>").
            <?php } else { ?>
                The "src" attribute is not pointing to an actual file and contains JavaScript code in Base64 format (it starts with "<em><?php echo $dataToCheck; ?></em>").
            <?php } ?>
        <a data-wpacu-modal-target="<?php echo $viewDecodedBase64Unique; ?>-target" href="#<?php echo $viewDecodedBase64Unique; ?>"><?php echo $viewDecodedText; ?></a>

        <!-- For reference (to copy data from it) -->
        <div style="display: none;" id="<?php echo $viewDecodedBase64Unique; ?>-decoded-source"><?php echo $decodedSource; ?></div>

        <script type="text/javascript">
            setTimeout(function() {
                document.getElementsByTagName('body')[0].innerHTML += '<div id="<?php echo $viewDecodedBase64Unique; ?>" class="wpacu-modal" style="padding-top: 100px;">' +
                    '<div class="wpacu-modal-content">' +
                    '<span class="wpacu-close">&times;</span>' +
                    '<pre><code id="<?php echo $viewDecodedBase64Unique; ?>-decoded-source-final"></code></pre>' +
                    '</div>' +
                    '</div>';

                    setTimeout(function() {
                        let wpacuInnerHtmlToCopy = document.getElementById('<?php echo $viewDecodedBase64Unique; ?>-decoded-source').innerHTML;
                        document.getElementById('<?php echo $viewDecodedBase64Unique; ?>-decoded-source-final').innerHTML = wpacuInnerHtmlToCopy;
                    }, 50);
            }, 1000);
        </script>
    <?php
    }

    // Preload? Only applies to SRCs that do not have the JS code added in the "src" as a base64 format
    if ( ! $isBase64EncodedSrc ) {
        $isAssetPreload = false;
        include dirname(__DIR__) . '/_asset-single-row/_asset-single-row-preload.php';
    }

    // [wpacu_lite]
    $extraInfo            = array();
    $assetHandleHasSrc    = true;
    $assetPosition        = isset($data['row']['obj']->position)     ? $data['row']['obj']->position     : '';
    $assetPositionNew     = $assetPosition;
    // [/wpacu_lite]

    include dirname(__DIR__) . '/_asset-single-row/_asset-single-row-position.php';

    $tagOutput = trim($data['row']['obj']->tag_output);
    ?>
    <div class="wpacu_asset_size_area wpacu_for_hardcoded_tag wpacu_has_base64_encoded_src">&nbsp;HTML Tag Size: <?php echo apply_filters('wpacu_get_asset_size', $tagOutput, 'for_print', 'tag'); ?></div>
</div>
