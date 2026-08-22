<?php
namespace WpAssetCleanUp\OptimiseAssets;

/**
 * Very-early bootstrap for signed front-end font-preload audit requests.
 *
 * Why this class exists separately from FontPreloadScanner:
 *
 * - FontPreloadScanner is the full admin/browser orchestration layer and is
 *   loaded later, together with the normal WPACU runtime classes.
 * - Cache/debug tooling must be neutralised while WordPress is still loading
 *   active plugins, before those tools attach collectors and output handlers.
 * - No request behaviour is changed until the short-lived scanner token has
 *   been matched against its server-side transient. A copied or invented query
 *   string therefore cannot disable caching or developer tools on public pages.
 *
 * This class intentionally does NOT alter the active-plugins option. Query
 * Monitor is suppressed only for the current PHP request by using its public
 * QM_DISABLED constant when load order permits, its collector/dispatcher
 * filters as a load-order-independent fallback, and its public `qm/cease`
 * action to discard data that may already have begun collecting.
 *
 * @package WpAssetCleanUp\OptimiseAssets
 */
class FontPreloadScannerEarly
{
    /**
     * Scanner tokens are deliberately fixed-length and URL-safe. Their actual
     * lifetime is defined by the full scanner and revalidated through the
     * transient's stored `expires_at` value below.
     */
    const TOKEN_PATTERN = '/^[A-Za-z0-9]{32}$/D';

    /**
     * Prevent duplicate bootstrap work if both Pro/Lite bootstrap paths or an
     * integration include early-triggers.php more than once.
     *
     * @var bool
     */
    private static $bootstrapped = false;

    /**
     * Detect a valid Local or Google Fonts scanner request and apply only the
     * request-scoped infrastructure needed by that hidden front-end page.
     *
     * @return void
     */
    public static function bootstrap()
    {
        if (self::$bootstrapped || is_admin()) {
            return;
        }

        self::$bootstrapped = true;

        $requestData = self::resolveValidatedRequest();

        if ( ! $requestData ) {
            return;
        }

        // These constants are diagnostic/request-context markers only. They
        // live for this PHP request and do not persist to another page load.
        if ( ! defined('WPACU_FONT_PRELOAD_SCAN_REQUEST') ) {
            define('WPACU_FONT_PRELOAD_SCAN_REQUEST', true);
        }

        if ( ! defined('WPACU_FONT_PRELOAD_SCAN_PROVIDER') ) {
            define('WPACU_FONT_PRELOAD_SCAN_PROVIDER', $requestData['provider']);
        }

        // Scanner pages must bypass full-page caches; otherwise a cached copy
        // may contain WPACU's regular manual preload or omit the collector.
        if ( ! defined('DONOTCACHEPAGE') ) {
            define('DONOTCACHEPAGE', true);
        }

        self::suppressQueryMonitorForCurrentRequest();

        add_action('send_headers', array(__CLASS__, 'sendNoCacheHeaders'), 0);
    }

    /**
     * Send the same conservative no-store headers for Local and Google scans.
     * The full scanner adds its provider-specific response header later.
     *
     * @return void
     */
    public static function sendNoCacheHeaders()
    {
        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
    }

    /**
     * Return an empty list to Query Monitor's collector/dispatcher filters.
     * Kept as a named callback for PHP 5.6 compatibility and easy inspection.
     *
     * @return array
     */
    public static function returnEmptyArray()
    {
        return array();
    }

    /**
     * Ask Query Monitor and its database drop-in to stop collecting data.
     *
     * Calling the action is safe when Query Monitor is absent. Calling it once
     * immediately marks the request as ceased for QM_DB, while calling it again
     * after all plugins have loaded lets Query Monitor tear down any components
     * that were initialised before WPACU due to active-plugin load order.
     *
     * @return void
     */
    public static function ceaseQueryMonitor()
    {
        do_action('qm/cease');
    }

    /**
     * Disable Query Monitor only for the currently validated scanner request.
     *
     * There are three intentionally overlapping safeguards because WordPress
     * does not guarantee whether Query Monitor or WPACU is included first:
     *
     * 1. QM_DISABLED prevents Query Monitor's main plugin bootstrap when WPACU
     *    is loaded first and the constant was not already set by the site.
     * 2. Empty collector/dispatcher filters prevent runtime instrumentation
     *    when Query Monitor was included first, or QM_DISABLED was predefined
     *    as false in wp-config.php.
     * 3. `qm/cease` discards data already collected, including query data from
     *    Query Monitor's optional db.php drop-in, which loads before plugins.
     *
     * This deliberately avoids `option_active_plugins` and does not deactivate,
     * reorder, or otherwise mutate the site's plugin configuration.
     *
     * @return void
     */
    private static function suppressQueryMonitorForCurrentRequest()
    {
        if ( ! defined('QM_DISABLED') ) {
            define('QM_DISABLED', true);
        }

        $emptyCallback = array(__CLASS__, 'returnEmptyArray');

        // If Query Monitor has not loaded yet, this also prevents its built-in
        // collector files from being included when QM_DISABLED was explicitly
        // predefined as false by the site owner.
        add_filter('qm/built-in-collectors', $emptyCallback, PHP_INT_MAX);

        // Query Monitor applies these filters on plugins_loaded. Registering at
        // PHP_INT_MAX ensures no earlier add-on can re-add a collector or
        // dispatcher after WPACU has decided this is a signed scanner request.
        add_filter('qm/collectors', $emptyCallback, PHP_INT_MAX);
        add_filter('qm/dispatchers', $emptyCallback, PHP_INT_MAX);

        // Mark the request as ceased immediately. QM_DB checks did_action() and
        // clears its query log after every later query, even when Query Monitor's
        // main plugin has not yet been included.
        self::ceaseQueryMonitor();

        // If Query Monitor was loaded before WPACU, its cease listener already
        // ran above. If it loads after WPACU because QM_DISABLED was predefined
        // false, this second call tears down anything registered during plugin
        // bootstrap. It remains confined to this signed scanner request.
        add_action('plugins_loaded', array(__CLASS__, 'ceaseQueryMonitor'), PHP_INT_MAX);
    }

    /**
     * Resolve either provider's token using only data available during active
     * plugin loading. This is the security boundary for all early side effects.
     *
     * @return array|false Validated request data plus provider metadata, or false.
     */
    private static function resolveValidatedRequest()
    {
        foreach (self::getProviderDefinitions() as $definition) {
            $queryArg = $definition['query_arg'];

            if ( ! isset($_GET[$queryArg]) || ! is_string($_GET[$queryArg]) ) {
                continue;
            }

            $token = sanitize_text_field(wp_unslash($_GET[$queryArg]));

            if ( ! preg_match(self::TOKEN_PATTERN, $token) ) {
                continue;
            }

            $transientName = $definition['transient_prefix'] . md5($token);
            $requestData   = get_transient($transientName);

            if ( ! self::isStoredRequestValid($requestData, $definition['provider'], $token) ) {
                continue;
            }

            $requestData['provider']       = $definition['provider'];
            $requestData['query_arg']      = $queryArg;
            $requestData['transient_name'] = $transientName;

            return $requestData;
        }

        return false;
    }

    /**
     * Validate the transient generated by FontPreloadScanner::ajaxPrepareScan().
     * The provider check prevents a valid token for one scanner being replayed
     * through the other provider's query argument.
     *
     * @param mixed  $requestData
     * @param string $provider
     * @param string $token
     *
     * @return bool
     */
    private static function isStoredRequestValid($requestData, $provider, $token)
    {
        if ( ! is_array($requestData) ||
             empty($requestData['token']) ||
             empty($requestData['provider']) ||
             (string) $requestData['provider'] !== (string) $provider ||
             ! hash_equals((string) $requestData['token'], (string) $token) ||
             empty($requestData['font_urls']) ||
             ! is_array($requestData['font_urls']) ) {
            return false;
        }

        if ( ! empty($requestData['expires_at']) && time() > (int) $requestData['expires_at'] ) {
            return false;
        }

        return true;
    }

    /**
     * Minimal provider registry duplicated intentionally from the full scanner.
     * Loading FontPreloadScanner here would pull the normal runtime stack into
     * every plugin bootstrap and would defeat the purpose of this tiny early
     * compatibility layer.
     *
     * Keep query arguments and transient prefixes synchronized with
     * FontPreloadScanner::getProviderDefinition().
     *
     * @return array
     */
    private static function getProviderDefinitions()
    {
        return array(
            array(
                'provider'         => 'local',
                'query_arg'        => 'wpacu_local_font_preload_scan',
                'transient_prefix' => 'wpacu_lfps_'
            ),
            array(
                'provider'         => 'google',
                'query_arg'        => 'wpacu_google_font_preload_scan',
                'transient_prefix' => 'wpacu_gfps_'
            )
        );
    }
}
