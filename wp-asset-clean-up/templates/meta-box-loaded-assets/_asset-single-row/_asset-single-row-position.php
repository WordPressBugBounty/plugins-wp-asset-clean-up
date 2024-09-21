<?php
/*
 * The file is included from /templates/meta-box-loaded-assets/_asset-single-row.php
*/
if ( ! isset($data, $assetHandleHasSrc, $assetPosition, $assetPositionNew, $assetType, $assetTypeS) ) {
    exit(); // no direct access
}

// [wpacu_lite]
if ($assetTypeS === 'style') {
    if ($assetHandleHasSrc && isset($data['row']['obj']->position) && $data['row']['obj']->position !== '') {
        $extraInfo[] = __('Position:', 'wp-asset-clean-up') . ' ' . (( $data['row']['obj']->position === 'head') ? 'HEAD' : 'BODY') . '<a class="go-pro-link-no-style" href="' . apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_asset&utm_medium=change_css_position').'"><span class="wpacu-tooltip" style="width: 322px; margin-left: -146px;">Upgrade to Pro and change the location of the CSS file <br /> (e.g. to BODY to reduce render-blocking <br /> or to HEAD for very early triggering)</span><img width="20" height="20" src="' . WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg" valign="top" alt="" /> Change it?</a>';
    } elseif (! $assetHandleHasSrc) {
        if (isset($data['row']['obj']->extra->after) && ! empty($data['row']['obj']->extra->after)) {
            $noSrcLoadedIn = __('This inline CSS can be viewed using the "Show/Hide" button below and it is loaded in:', 'wp-asset-clean-up');
        } else {
            $noSrcLoadedIn = __( 'This handle is not for external stylesheet (most likely inline CSS) and it is loaded in:', 'wp-asset-clean-up' );
        }

        $extraInfo[] = $noSrcLoadedIn . ' '. (($data['row']['obj']->position === 'head') ? 'HEAD' : 'BODY');
    }

} elseif ($assetTypeS === 'script') {
    if ($assetHandleHasSrc && isset($data['row']['obj']->position) && $data['row']['obj']->position !== '') {
        $extraInfo[] = __( 'Position:', 'wp-asset-clean-up' ) . ' ' . ( ( $data['row']['obj']->position === 'head' ) ? 'HEAD' : 'BODY' ) . '<a class="go-pro-link-no-style" href="' . apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_asset&utm_medium=change_js_position').'"><span class="wpacu-tooltip" style="width: 322px; margin-left: -146px;">Upgrade to Pro and change the location of the JS file <br /> (e.g. to BODY to reduce render-blocking <br /> or to HEAD for very early triggering)</span><img width="20" height="20" src="' . WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg" valign="top" alt="" /> Change it?</a>';
    } elseif (! $assetHandleHasSrc) {
        $noSrcLoadedIn = __('This handle is not for external JS (most likely inline JS) and it is loaded in:', 'wp-asset-clean-up');

        $extraInfo[] = $noSrcLoadedIn . ' '. (($data['row']['obj']->position === 'head') ? 'HEAD' : 'BODY');
    }
}
// [/wpacu_lite]
