<?php
namespace WpAssetCleanUp\OptimiseAssets;

/**
 * Local-font provider facade for the shared manual-preload audit engine.
 *
 * This class intentionally contains almost no scanning logic. It gives the
 * Settings screen and the Local Fonts runtime stable public entry points while
 * FontPreloadScanner owns token creation, representative-page discovery,
 * browser evidence collection, retry handling, classification, and UI data.
 *
 * Local-font-specific behaviour handled by the shared engine includes:
 *
 * - resolving relative/self-hosted URLs against the site's home URL;
 * - confirming whether a mapped file exists below the WordPress installation;
 * - comparing exact URLs separately from same-path query-string variants;
 * - mapping readable @font-face source chains to loaded FontFaceSet entries;
 * - treating WOFF/TTF/SVG fallback chains conservatively; and
 * - never equating sampled non-use with permission to delete the font itself.
 *
 * The constants below are retained for backwards compatibility with code that
 * integrated with the original Local-only scanner before the engine became
 * provider-neutral.
 *
 * @package WpAssetCleanUp\OptimiseAssets
 */
class FontsLocalPreloadScanner
{
    const AJAX_ACTION      = 'wpassetcleanup_prepare_local_fonts_preload_scan';
    const NONCE_ACTION     = 'wpacu_local_fonts_preload_scan';
    const QUERY_ARG        = 'wpacu_local_font_preload_scan';
    const VIEW_QUERY_ARG   = 'wpacu_local_font_preload_scan_view';
    const TRANSIENT_PREFIX = 'wpacu_lfps_';
    const MAX_FONT_URLS    = 50;
    const MAX_SCAN_PAGES   = 6;
    const MAX_EXTRA_URLS   = 2;
    const TOKEN_TTL        = 1800;

    /**
     * @return void
     */
    public static function registerAdminHooks()
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, array(__CLASS__, 'ajaxPrepareScan'));
    }

    /**
     * @return array
     */
    public static function getAdminConfig()
    {
        return FontPreloadScanner::getAdminConfig('local');
    }

    /**
     * @return void
     */
    public static function ajaxPrepareScan()
    {
        FontPreloadScanner::ajaxPrepareScan('local');
    }

    /**
     * Attach the collector only when the current front-end URL contains a
     * valid, transient-backed Local Fonts scan token. Ordinary visitors and
     * arbitrary copied query strings do not enter the scanner code path.
     *
     * @return void
     */
    public static function maybeInitFrontendCollector()
    {
        FontPreloadScanner::maybeInitFrontendCollector('local');
    }

    /**
     * Whether this is an authenticated Local Fonts audit/verification request.
     * Runtime preload suppression must always use this validated result rather
     * than trusting the presence of the public query argument alone.
     *
     * @return bool
     */
    public static function isActiveRequest()
    {
        return FontPreloadScanner::isActiveRequest('local');
    }

    /**
     * Backward-compatible public delegate retained for integrations that called
     * the original scanner class directly.
     *
     * @return void
     */
    public static function sendNoCacheHeaders()
    {
        FontPreloadScanner::sendNoCacheHeaders('local');
    }

    /**
     * Backward-compatible public delegate retained for integrations that called
     * the original scanner class directly.
     *
     * @return void
     */
    public static function printFrontendCollector()
    {
        FontPreloadScanner::printFrontendCollector('local');
    }
}
