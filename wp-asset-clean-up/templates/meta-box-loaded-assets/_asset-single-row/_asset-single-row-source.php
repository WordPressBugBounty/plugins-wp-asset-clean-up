<?php
/*
 * The file is included from /templates/meta-box-loaded-assets/_asset-single-row.php
*/

use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\FontsGoogle;

if ( ! isset($data, $ver, $assetHandleHasSrc, $assetType) ) {
    exit(); // no direct access
}

if ( ! (isset($data['row']['obj']->src, $data['row']['obj']->srcHref) && $data['row']['obj']->src && $data['row']['obj']->srcHref) ) {
    $hasNoSrc = true;
    return;
}

$hasNoSrc = false;

// Styles: If there is a source (in rare cases, there are handles such as "woocommerce-inline" that do not have a source)
$assetHandleHasSrc = $isExternalSrc = true; // default

if (Misc::getLocalSrcIfExist($data['row']['obj']->src)
    || strpos($data['row']['obj']->src, '/?') !== false // Dynamic Local URL
    || strncmp(str_replace(site_url(), '', $data['row']['obj']->src), '?', 1) === 0 // Starts with ? right after the site url (it's a local URL)
) {
    $isExternalSrc = false;
}

$isGoogleFontLink = false; // default

if ($assetType === 'styles') {
    $isGoogleFontLink = stripos($data['row']['obj']->srcHref, '//fonts.googleapis.com/') !== false;

    // Formatting for Google Fonts
    if ($isGoogleFontLink) {
        $data['row']['obj']->src     = urldecode(FontsGoogle::alterGoogleFontLink($data['row']['obj']->src));
        $data['row']['obj']->srcHref = urldecode(FontsGoogle::alterGoogleFontLink($data['row']['obj']->srcHref));
    }
}

$data['row']['obj']->src     = str_replace(' ', '+', $data['row']['obj']->src);
$data['row']['obj']->srcHref = str_replace(' ', '+', $data['row']['obj']->srcHref);

$srcHref = $data['row']['obj']->srcHref;

// If the source starts with '../' mark it as external to be checked via the AJAX call (special case)
if (strncmp($srcHref, '../', 3) === 0) {
    $currentPageUrl = Misc::getCurrentPageUrl();
    $srcHref = trim($currentPageUrl, '/') . '/'. $data['row']['obj']->srcHref;
    $isExternalSrc = true; // simulation
}

$relSrc = str_replace(site_url(), '', $data['row']['obj']->src);

if (isset($data['row']['obj']->baseUrl)) {
    $relSrc = str_replace($data['row']['obj']->baseUrl, '/', $relSrc);
}

if ($assetType === 'styles') {
    // "font-display" CSS Property for Google Fonts - underline the URL parameter
    $toUnderline = 'display=' . $data['plugin_settings']['google_fonts_display'];
    $relSrc      = str_replace($toUnderline, '<u style="background: #f2faf2;">' . $toUnderline . '</u>', $relSrc);
}

if ($isExternalSrc) {
    $verToAppend = ''; // no need for any "ver"
} else {
    $appendAfterSrcHref = ( strpos( $srcHref, '?' ) === false ) ? '?' : '&';

    if ( isset( $data['row']['obj']->ver ) && $data['row']['obj']->ver ) {
        $verToAppend = $appendAfterSrcHref .
                       (is_array( $data['row']['obj']->ver )
                           ? http_build_query( array( 'ver' => $data['row']['obj']->ver ) )
                           : 'ver=' . $ver);
    } else {
        global $wp_version;
        $verToAppend = $appendAfterSrcHref . 'ver=' . $wp_version;
    }
}

if ( $assetType === 'styles' && $isGoogleFontLink && $data['plugin_settings']['google_fonts_remove'] ) {
    echo '<p><span style="color:#c00;">This resource is not loaded as "Remove Google Fonts" is enabled in "Settings" -&gt; "Google Fonts".</span></p>';
}

$isAssetPreload = (isset($data['preloads'][$assetType][$data['row']['obj']->handle]) && $data['preloads'][$assetType][$data['row']['obj']->handle])
    ? $data['preloads'][$assetType][$data['row']['obj']->handle]
    : false;

if ($isAssetPreload) {
    $data['row']['obj']->preload_status = 'preloaded';
    $data['row']['at_least_one_rule_set'] = true;
}
?>
<div class="wpacu-source-row">
    <?php
    if (isset($data['row']['obj']->src_origin, $data['row']['obj']->ver_origin) && $data['row']['obj']->src_origin) {
        $sourceText = esc_html__('Source (updated):', 'wp-asset-clean-up');

        if ($assetType === 'styles') {
            $messageToAlert = sprintf(
                esc_html__('On this page, the `%s` CSS handle had its source updated via `%s` filter tag.' . "\n\n" . 'Original Source: %s (version: %s)'),
                $data['row']['obj']->handle,
                'wpacu_' . $data['row']['obj']->handle . '_css_handle_data',
                $data['row']['obj']->src_origin,
                ($data['row']['obj']->ver_origin ?: esc_html__('null', 'wp-asset-clean-up'))
            );
        } else {
            $messageToAlert = sprintf(
                esc_html__('On this page, the `%s` JavaScript handle had its source updated via `%s` filter tag.' . "\n\n" . 'Original Source: %s (version: %s)'),
                $data['row']['obj']->handle,
                'wpacu_' . $data['row']['obj']->handle . '_js_handle_data',
                $data['row']['obj']->src_origin,
                ($data['row']['obj']->ver_origin ?: esc_html__('null', 'wp-asset-clean-up'))
            );
        }
        ?>
        <a style="text-decoration: none; display: inline-block;"
           href="#"
           class="wpacu-filter-handle"
           data-wpacu-filter-handle-message="<?php echo esc_attr($messageToAlert); ?>"
        ><span class="dashicons dashicons-filter"></span></a>
    <?php } else {
        $sourceText = esc_html__('Source:', 'wp-asset-clean-up'); // as it is, no replacement
    }
    echo esc_html($sourceText);
    ?>
    <a target="_blank" style="color: green;" <?php if ($isExternalSrc) { ?> data-wpacu-external-source="<?php echo esc_attr($srcHref . $verToAppend); ?>" <?php } ?> href="<?php echo esc_attr($srcHref . $verToAppend); ?>"><?php echo wp_kses($relSrc, array('u' => array('style' => array()))); ?></a>

    <?php
    if ($assetType === 'styles' && isset($data['row']['obj']->args) && $data['row']['obj']->args && $data['row']['obj']->args !== 'all') {
        $wpacuMediaSpanStyle = 'color: #004f74; font-style: italic;';
        $wpacuMediaSvgIcon = <<<SVG
<!-- Generated by IcoMoon.io -->
<svg style="vertical-align: middle; width: 22px; height: 22px; margin-left: 1px; margin-right: 1px;" version="1.1" xmlns="http://www.w3.org/2000/svg" width="18" height="28" viewBox="0 0 18 28">
<title>The media attribute specifies what media/device the target resource is optimized for.</title>
<path style="fill: #004f74;" d="M10 22c0-0.547-0.453-1-1-1s-1 0.453-1 1 0.453 1 1 1 1-0.453 1-1zM16 19.5v-15c0-0.266-0.234-0.5-0.5-0.5h-13c-0.266 0-0.5 0.234-0.5 0.5v15c0 0.266 0.234 0.5 0.5 0.5h13c0.266 0 0.5-0.234 0.5-0.5zM18 4.5v17c0 1.375-1.125 2.5-2.5 2.5h-13c-1.375 0-2.5-1.125-2.5-2.5v-17c0-1.375 1.125-2.5 2.5-2.5h13c1.375 0 2.5 1.125 2.5 2.5z"></path>
</svg>
SVG;
        $wpacuLinkToMediaDoc = 'https://www.w3schools.com/css/css_rwd_mediaqueries.asp';

        echo ' <span title="media" style="'.esc_attr($wpacuMediaSpanStyle).'"><a target="_blank" href="'.esc_url($wpacuLinkToMediaDoc).'">'.$wpacuMediaSvgIcon.'</a>'.$data['row']['obj']->args.'</span> ';
    }
    ?>

    <?php if ($isExternalSrc) { ?><span data-wpacu-external-source-status></span><?php } ?>

    <?php
    include __DIR__ . '/_asset-single-row-preload.php';
    ?>
</div>
