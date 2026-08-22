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
        $maxDecodedSourceBytes = 2097152; // 2 MiB is sufficient for an admin source preview.
        $maxEncodedSourceBytes = (int) ceil(($maxDecodedSourceBytes * 4) / 3) + 4;
        $decodedSource = false;

        if (strlen($base64Encoded) <= $maxEncodedSourceBytes) {
            $decodedSource = base64_decode($base64Encoded, true);
        }

        if ($decodedSource === false || strlen($decodedSource) > $maxDecodedSourceBytes) {
            $decodedSource = __('The embedded source could not be decoded safely or is too large to preview.', 'wp-asset-clean-up');
        }

        $viewDecodedBase64Unique = 'wpacu-view-decoded-base64-format-' . $assetTypeS . '-' . sha1($data['row']['obj']->src) . '-'. Misc::uniqueId();
        ?>
            <?php if ($assetTypeS === 'style') { ?>
                The "href" attribute is not pointing to an actual file and contains CSS code in Base64 format (it starts with "<em><?php echo $dataToCheck; ?></em>").
            <?php } else { ?>
                The "src" attribute is not pointing to an actual file and contains JavaScript code in Base64 format (it starts with "<em><?php echo $dataToCheck; ?></em>").
            <?php } ?>
        <a data-wpacu-modal-target="<?php echo $viewDecodedBase64Unique; ?>-target" href="#<?php echo $viewDecodedBase64Unique; ?>"><?php echo $viewDecodedText; ?></a>

        <template id="<?php echo esc_attr($viewDecodedBase64Unique); ?>-template">
            <div id="<?php echo esc_attr($viewDecodedBase64Unique); ?>" class="wpacu-modal wpacu-source-viewer-modal">
                <div class="wpacu-modal-content wpacu-source-viewer-modal__content">
                    <button type="button" class="wpacu-close wpacu-source-viewer-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
                    <header class="wpacu-source-viewer-modal__header">
                        <span class="wpacu-source-viewer-modal__eyebrow"><?php esc_html_e('Decoded data URL', 'wp-asset-clean-up'); ?></span>
                        <div class="wpacu-source-viewer-modal__title-row">
                            <h2><?php echo $assetTypeS === 'style' ? esc_html__('Embedded CSS source', 'wp-asset-clean-up') : esc_html__('Embedded JavaScript source', 'wp-asset-clean-up'); ?></h2>
                            <span class="wpacu-source-viewer-modal__badge"><?php echo esc_html($assetTypeS === 'style' ? 'CSS' : 'JavaScript'); ?></span>
                        </div>
                        <p class="wpacu-source-viewer-modal__description"><?php esc_html_e('Readable source decoded from the Base64 data URL used by this asset.', 'wp-asset-clean-up'); ?></p>
                    </header>
                    <div class="wpacu-source-viewer-modal__body"><pre><code><?php echo esc_html($decodedSource); ?></code></pre></div>
                </div>
            </div>
        </template>
        <script type="text/javascript">
            (function () {
                var template = document.getElementById('<?php echo esc_js($viewDecodedBase64Unique); ?>-template');

                if (!template || !template.content || !document.body) {
                    return;
                }

                var modal = template.content.firstElementChild;
                if (modal) {
                    document.body.appendChild(modal);
                }

                if (template.parentNode) {
                    template.parentNode.removeChild(template);
                }
            }());
        </script>
    <?php
    }

    // Preload? Only applies to SRCs that do not have the JS code added in the "src" as a base64 format
    if ( ! $isBase64EncodedSrc ) {
        $isAssetPreload = false;
        // [wpacu_pro]
        if ($assetTypeS === 'style') {
            $isAssetPreload = (isset($data['preloads']['styles'][$data['row']['obj']->handle]) && $data['preloads']['styles'][$data['row']['obj']->handle])
                ? $data['preloads']['styles'][$data['row']['obj']->handle]
                : false;
        } elseif ($assetTypeS === 'script') {
            $isAssetPreload = (isset($data['preloads']['scripts'][$data['row']['obj']->handle]) && $data['preloads']['scripts'][$data['row']['obj']->handle])
                ? $data['preloads']['scripts'][$data['row']['obj']->handle]
                : false;
        }
        // [/wpacu_pro]

        require WPACU_PLUGIN_DIR . '/templates/meta-box-loaded-assets/_asset-single-row/_asset-single-row-preload.php';
    }

    // [wpacu_pro]
    $extraInfo            = array();
    $assetHandleHasSrc    = true;
    $assetPosition        = isset($data['row']['obj']->position)     ? $data['row']['obj']->position     : '';
    $assetPositionNew     = defined('WPACU_PRO_PLUGIN_VERSION') && isset($data['row']['obj']->position_new) ? $data['row']['obj']->position_new : $assetPosition;
    // [/wpacu_pro]

    require WPACU_PLUGIN_DIR . '/templates/meta-box-loaded-assets/_asset-single-row/_asset-single-row-position.php';

    // [wpacu_pro]
    $assetLocationChanged = defined('WPACU_PRO_PLUGIN_VERSION') && $assetPositionNew !== $assetPosition;

    if (isset($extraInfo[0]) && $extraInfo[0]) {
        echo '&nbsp;/&nbsp;' . $extraInfo[0];

        if ($assetLocationChanged) {
            ?>
            <div style="display: inline-block; color: #004567; font-style: italic; font-size: 90%; font-weight: 600; margin: 4px 0 10px;">
                <span class="dashicons dashicons-info" style="font-size: 19px; line-height: normal;"></span> <?php _e('This file has its initial location changed.', 'wp-asset-clean-up'); ?>
            </div>
            <?php
        }
    }
    // [/wpacu_pro]

    $tagOutput = trim($data['row']['obj']->tag_output);
    ?>
    <div class="wpacu_asset_size_area wpacu_for_hardcoded_tag wpacu_has_base64_encoded_src">&nbsp;HTML Tag Size: <?php echo apply_filters('wpacu_get_asset_size', $tagOutput, 'for_print', 'tag'); ?></div>
</div>
