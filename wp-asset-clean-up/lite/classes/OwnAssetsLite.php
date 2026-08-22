<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUpLite;

use WpAssetCleanUp\OwnAssets;

/**
 * Lite-only additions for WpAssetCleanUp\OwnAssets.
 *
 * Load this only in the Lite package. Shared OwnAssets.php exposes wpacu_internal_* hooks
 * at the old Lite-only insertion points, and this class attaches the Lite behaviour there.
 */
class OwnAssetsLite
{
    /**
     * @param string $inlineStyle
     *
     * @return string
     */
    public static function sweetAlertInlineStyle($inlineStyle)
    {
        $inlineStyle .= <<<CSS
body.wp-admin.post-php .swal2-popup.swal2-modal,
body.wp-admin.asset-cleanup_page_wpassetcleanup_assets_manager .swal2-popup.swal2-modal {
    padding: 1.25em 1.25em 2em 1.25em;
}

body.wp-admin.post-php #swal2-content,
body.asset-cleanup_page_wpassetcleanup_assets_manager #swal2-content {
    line-height: 30px;
}
CSS;

        return $inlineStyle;
    }

    /**
     * @return void
     */
    public static function sweetAlertUpgradeToProPopups()
    {
        if ( ! defined('WPACU_PLUGIN_GO_PRO_URL') ) {
            return;
        }

        $upgradeToProLinkHardcodedAssets = OwnAssets::applyInternalFilterWithFallback(
            'wpacu_internal_go_pro_affiliate_link',
            'wpacu_go_pro_affiliate_link',
            WPACU_PLUGIN_GO_PRO_URL.'?utm_source=manage_hardcoded_assets&utm_medium=go_pro_modal'
        );

        $upgradeToProLinkMediaQueryLoad = OwnAssets::applyInternalFilterWithFallback(
            'wpacu_internal_go_pro_affiliate_link',
            'wpacu_go_pro_affiliate_link',
            WPACU_PLUGIN_GO_PRO_URL.'?utm_source=media_query_load&utm_medium=go_pro_modal'
        );

        $sweetAlertTwoScriptInline = <<<JS
jQuery(document).ready(function($) { 
    /* [Hardcoded Assets] */
    $(document).on('click', '.wpacu-manage-hardcoded-assets-requires-pro-popup', function(e) {
       e.preventDefault();
       wpacuSwal.fire({
            text: "Managing hardcoded (non-enqueued) LINK/STYLE/SCRIPT tags is a feature available for Pro users.",
            icon: "info",
            showCancelButton: true,
            confirmButtonText: 'Upgrade to the Pro version',
            cancelButtonText: 'Maybe later',
            width: '600px'
        }).then((result) => {
            if (result.isConfirmed) {
              window.location.replace("{$upgradeToProLinkHardcodedAssets}");
            }
        });
    });
    /* [/Hardcoded Assets] */
    
    /* [Media Query Load] */
    $(document).on('click', '.wpacu-media-query-load-requires-pro-popup', function(e) {
       e.preventDefault();
       wpacuSwal.fire({
            text: "Instructing the browser to load a file based on the screen size of the visitor's device (e.g. desktop or mobile view) is a feature available for Pro users.",
            icon: "info",
            showCancelButton: true,
            confirmButtonText: 'Upgrade to the Pro version',
            cancelButtonText: 'Maybe later',
            width: '600px'
        }).then((result) => {
            if (result.isConfirmed) {
              window.location.replace("{$upgradeToProLinkMediaQueryLoad}");
            }
        });
    });
    /* [/Media Query Load] */
});
JS;
        wp_add_inline_script(WPACU_PLUGIN_ID . '-sweetalert2-js', $sweetAlertTwoScriptInline);
    }
}
