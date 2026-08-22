<?php
namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Menu;

/**
 * Google Fonts provider for the shared legacy manual-preload audit engine.
 *
 * Why Google needs a separate provider
 * ------------------------------------
 * A fonts.gstatic.com URL is a generated response file, not a durable semantic
 * definition such as "Roboto, 400, italic". Google may return another file for
 * a different browser User-Agent, subset, language, weight/style combination,
 * variable-font axis range, `text=` subset, or Material icon selection.
 *
 * Browser/server split
 * --------------------
 * - FontPreloadScanner runs the real page and records exact gstatic requests plus
 *   the fonts.googleapis.com stylesheets observed by that browser.
 * - This class resolves only allowlisted Google stylesheet endpoints with the
 *   same User-Agent, follows a small number of validated redirects, caps time
 *   and response size, and parses @font-face blocks into per-file metadata.
 * - The admin runner merges both sources. Exact browser requests are strongest;
 *   semantic FontFaceSet matches remain supporting/review evidence because the
 *   browser API does not expose which generated source URL actually won.
 *
 * Safety model
 * ------------
 * Stylesheet resolution is an SSRF-sensitive boundary. Never broaden the host,
 * scheme, port, redirect, byte, or timeout restrictions merely to make a scan
 * pass. An unresolved or ambiguous Google stylesheet protects affected entries
 * as Review rather than making an optimistic cleanup recommendation.
 *
 * @package WpAssetCleanUp\OptimiseAssets
 */
class FontsGooglePreloadScanner
{
    const AJAX_ACTION         = 'wpassetcleanup_prepare_google_fonts_preload_scan';
    const RESOLVE_AJAX_ACTION = 'wpassetcleanup_resolve_google_fonts_stylesheets';
    const NONCE_ACTION        = 'wpacu_google_fonts_preload_scan';
    const QUERY_ARG           = 'wpacu_google_font_preload_scan';
    const VIEW_QUERY_ARG      = 'wpacu_google_font_preload_scan_view';

    const MAX_STYLESHEETS          = 30;
    // Cross-browser probing is diagnostic only and intentionally capped. It
    // compares Google CSS responses for representative browser User-Agents; it
    // does not pretend to emulate page rendering in those browsers.
    const MAX_COMPARISON_STYLESHEETS = 8;
    const MAX_STYLESHEET_BYTES     = 262144;
    const STYLESHEET_CACHE_TTL     = 21600;
    const REMOTE_REQUEST_TIMEOUT    = 8;
    const REMOTE_REQUEST_ATTEMPTS   = 2;
    const REMOTE_RETRY_DELAY_US     = 200000;
    const MAX_STYLESHEET_REDIRECTS  = 2;
    const RESOLUTION_TIME_BUDGET    = 20;
    const MIN_REMOTE_REQUEST_TIMEOUT = 1.0;
    const REQUEST_BUDGET_GRACE       = 0.35;

    /**
     * Register Settings-page AJAX handlers.
     *
     * @return void
     */
    public static function registerAdminHooks()
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, array(__CLASS__, 'ajaxPrepareScan'));
        add_action('wp_ajax_' . self::RESOLVE_AJAX_ACTION, array(__CLASS__, 'ajaxResolveStylesheets'));
    }

    /**
     * @return array
     */
    public static function getAdminConfig()
    {
        $config = FontPreloadScanner::getAdminConfig('google');
        $config['resolveAction'] = self::RESOLVE_AJAX_ACTION;
        $config['maxStylesheets'] = self::MAX_STYLESHEETS;

        return $config;
    }

    /**
     * @return void
     */
    public static function ajaxPrepareScan()
    {
        FontPreloadScanner::ajaxPrepareScan('google');
    }

    /**
     * Attach the shared browser collector only to a validated Google Fonts scan
     * or clean-verification URL. The provider resolver itself runs later via an
     * authenticated Settings-page AJAX request, not inside the public iframe.
     *
     * @return void
     */
    public static function maybeInitFrontendCollector()
    {
        FontPreloadScanner::maybeInitFrontendCollector('google');
    }

    /**
     * Whether the current URL carries a valid transient-backed Google scan
     * token. This validated check is also what suppresses WPACU's own manual
     * Google font-file preload during the browser audit.
     *
     * @return bool
     */
    public static function isActiveRequest()
    {
        return FontPreloadScanner::isActiveRequest('google');
    }

    /**
     * Resolve the Google Fonts CSS returned to the browser running the scan.
     *
     * This endpoint receives only stylesheet URLs discovered by completed page
     * checks. It normalises/deduplicates them, enforces the provider allowlist,
     * fetches with the browser's User-Agent, parses @font-face descriptors, and
     * returns mappings keyed by exact gstatic source URL. Failed requests include
     * structured diagnostics so they can be retried without rerunning pages.
     *
     * @return void
     */
    public static function ajaxResolveStylesheets()
    {
        if ( ! Menu::userCanAccessPlugin() ) {
            wp_send_json_error(array(
                'message' => __('You are not allowed to resolve Google Fonts stylesheets.', 'wp-asset-clean-up')
            ));
        }

        if ( ! check_ajax_referer(self::NONCE_ACTION, 'nonce', false) ) {
            wp_send_json_error(array(
                'message' => __('The security check failed. Refresh the Settings page and try again.', 'wp-asset-clean-up')
            ));
        }

        $stylesheetUrls = isset($_POST['stylesheet_urls']) ? wp_unslash($_POST['stylesheet_urls']) : array();

        if (is_string($stylesheetUrls)) {
            $decodedUrls = json_decode($stylesheetUrls, true);
            $stylesheetUrls = is_array($decodedUrls) ? $decodedUrls : array();
        }

        if ( ! is_array($stylesheetUrls) ) {
            $stylesheetUrls = array();
        }

        $configuredFontUrls = isset($_POST['font_urls']) ? wp_unslash($_POST['font_urls']) : array();

        if (is_string($configuredFontUrls)) {
            $decodedFontUrls = json_decode($configuredFontUrls, true);
            $configuredFontUrls = is_array($decodedFontUrls) ? $decodedFontUrls : array();
        }

        $configuredFontUrls = self::normaliseConfiguredGoogleFontUrls($configuredFontUrls);
        $normalisedStylesheetUrls = array();

        foreach ($stylesheetUrls as $stylesheetUrl) {
            if ( ! is_scalar($stylesheetUrl) ) {
                continue;
            }

            $stylesheetUrl = self::normaliseAllowedStylesheetUrl((string) $stylesheetUrl);

            if ($stylesheetUrl === '' || in_array($stylesheetUrl, $normalisedStylesheetUrls, true)) {
                continue;
            }

            $normalisedStylesheetUrls[] = $stylesheetUrl;

            if (count($normalisedStylesheetUrls) >= self::MAX_STYLESHEETS) {
                break;
            }
        }

        if (empty($normalisedStylesheetUrls)) {
            wp_send_json_success(array(
                'fontFacesByUrl'      => array(),
                'resolvedStylesheets' => array(),
                'failedStylesheets'   => array(),
                'browserUserAgent'    => self::getBrowserUserAgent(),
                'browserCompatibilityByUrl' => array(),
                'comparisonProfiles' => array(),
                'budgetExhausted' => false
            ));
        }

        $browserUserAgent  = self::getBrowserUserAgent();
        $fontFacesByUrl    = array();
        $allFontFaces      = array();
        $resolved          = array();
        $failed            = array();
        $budgetExhausted   = false;
        $resolutionDeadline = microtime(true) + self::RESOLUTION_TIME_BUDGET;

        foreach ($normalisedStylesheetUrls as $stylesheetUrl) {
            $stylesheetResult = self::resolveStylesheet($stylesheetUrl, $browserUserAgent, $resolutionDeadline);

            if (empty($stylesheetResult['success'])) {
                $failed[] = array(
                    'url'            => $stylesheetUrl,
                    'finalUrl'       => isset($stylesheetResult['final_url']) ? $stylesheetResult['final_url'] : $stylesheetUrl,
                    'code'           => isset($stylesheetResult['code']) ? $stylesheetResult['code'] : 'request_failed',
                    'message'        => isset($stylesheetResult['message']) ? $stylesheetResult['message'] : __('The stylesheet could not be resolved.', 'wp-asset-clean-up'),
                    'attempts'       => isset($stylesheetResult['attempts']) ? (int) $stylesheetResult['attempts'] : 0,
                    'redirects'      => isset($stylesheetResult['redirects']) ? (int) $stylesheetResult['redirects'] : 0,
                    'httpStatus'     => isset($stylesheetResult['http_status']) ? (int) $stylesheetResult['http_status'] : 0,
                    'timeoutSeconds' => isset($stylesheetResult['timeout_seconds']) ? (float) $stylesheetResult['timeout_seconds'] : 0,
                    'retryable'      => self::isRetryableResolutionFailure($stylesheetResult)
                );
                if ( ! empty($stylesheetResult['budget_exhausted'])
                    || (isset($stylesheetResult['code']) && $stylesheetResult['code'] === 'time_budget_exhausted')) {
                    $budgetExhausted = true;
                }
                continue;
            }

            $fontFaces = self::parseFontFaces($stylesheetResult['body'], $stylesheetUrl);

            $resolved[] = array(
                'url'           => $stylesheetUrl,
                'finalUrl'      => isset($stylesheetResult['final_url']) ? $stylesheetResult['final_url'] : $stylesheetUrl,
                'fontFaces'     => count($fontFaces),
                'cached'        => ! empty($stylesheetResult['cached']),
                'attempts'      => isset($stylesheetResult['attempts']) ? (int) $stylesheetResult['attempts'] : 0,
                'redirects'     => isset($stylesheetResult['redirects']) ? (int) $stylesheetResult['redirects'] : 0,
                'httpStatus'    => isset($stylesheetResult['http_status']) ? (int) $stylesheetResult['http_status'] : 0,
                'responseBytes' => isset($stylesheetResult['response_bytes']) ? (int) $stylesheetResult['response_bytes'] : 0
            );

            foreach ($fontFaces as $fontFace) {
                $allFontFaces[] = $fontFace;
            }
        }

        $descriptorSources = array();

        foreach ($allFontFaces as $fontFace) {
            if (empty($fontFace['sourceUrls']) || ! is_array($fontFace['sourceUrls'])) {
                continue;
            }

            $descriptorKey = self::getFontFaceDescriptorKey($fontFace);

            if ($descriptorKey === '') {
                continue;
            }

            if ( ! isset($descriptorSources[$descriptorKey]) ) {
                $descriptorSources[$descriptorKey] = array();
            }

            foreach ($fontFace['sourceUrls'] as $sourceUrl) {
                // Count every remote source for ambiguity, even though only
                // Google-hosted font files are returned as audit candidates.
                $descriptorSources[$descriptorKey][$sourceUrl] = true;
            }
        }

        foreach ($allFontFaces as $fontFace) {
            if (empty($fontFace['sourceUrls']) || ! is_array($fontFace['sourceUrls'])) {
                continue;
            }

            $descriptorKey = self::getFontFaceDescriptorKey($fontFace);
            $descriptorSourceUrlCount = isset($descriptorSources[$descriptorKey])
                ? count($descriptorSources[$descriptorKey])
                : 0;

            foreach ($fontFace['sourceUrls'] as $sourceUrl) {
                if ( ! FontPreloadScanner::isAllowedGoogleFontFileUrl($sourceUrl) ) {
                    continue;
                }

                if ( ! isset($fontFacesByUrl[$sourceUrl]) ) {
                    $fontFacesByUrl[$sourceUrl] = array();
                }

                $faceForSource = $fontFace;
                $faceForSource['sourceUrl']                = $sourceUrl;
                $faceForSource['sourceUrlCount']           = count($fontFace['sourceUrls']);
                $faceForSource['descriptorSourceUrlCount'] = $descriptorSourceUrlCount;
                unset($faceForSource['sourceUrls']);

                $faceKey = md5(wp_json_encode($faceForSource));
                $fontFacesByUrl[$sourceUrl][$faceKey] = $faceForSource;
            }
        }

        foreach ($fontFacesByUrl as $sourceUrl => $sourceFaces) {
            $fontFacesByUrl[$sourceUrl] = array_values($sourceFaces);
        }

        $failedStylesheetUrls = array();
        foreach ($failed as $failedStylesheet) {
            if (isset($failedStylesheet['url']) && is_string($failedStylesheet['url'])) {
                $failedStylesheetUrls[] = $failedStylesheet['url'];
            }
        }

        $browserCompatibility = self::buildBrowserCompatibility(
            $normalisedStylesheetUrls,
            $configuredFontUrls,
            $browserUserAgent,
            $allFontFaces,
            $failedStylesheetUrls,
            $resolutionDeadline
        );
        $budgetExhausted = $budgetExhausted || ! empty($browserCompatibility['budgetExhausted']);

        wp_send_json_success(array(
            'fontFacesByUrl'      => $fontFacesByUrl,
            'resolvedStylesheets' => $resolved,
            'failedStylesheets'   => $failed,
            'browserUserAgent'    => $browserUserAgent,
            'browserCompatibilityByUrl' => $browserCompatibility['byUrl'],
            'comparisonProfiles' => $browserCompatibility['profiles'],
            'budgetExhausted' => $budgetExhausted
        ));
    }

    /**
     * @param string $stylesheetUrl
     * @param string $browserUserAgent
     *
     * @return array
     */
    private static function resolveStylesheet($stylesheetUrl, $browserUserAgent, $deadline = 0.0)
    {
        $cacheKey = 'wpacu_gfcss_' . md5($stylesheetUrl . "\n" . $browserUserAgent);
        $cached   = get_transient($cacheKey);

        // Cached responses are safe to use even after the network budget expires.
        if (is_array($cached) && isset($cached['body']) && is_string($cached['body'])) {
            return array(
                'success'          => true,
                'body'             => $cached['body'],
                'cached'           => true,
                'attempts'         => 0,
                'redirects'        => 0,
                'http_status'      => 0,
                'final_url'        => $stylesheetUrl,
                'response_bytes'   => strlen($cached['body']),
                'timeout_seconds'  => 0,
                'budget_exhausted' => false
            );
        }

        $currentUrl       = $stylesheetUrl;
        $totalAttempts    = 0;
        $lastTimeout      = 0;
        $budgetExhausted  = false;

        for ($redirectCount = 0; $redirectCount <= self::MAX_STYLESHEET_REDIRECTS; $redirectCount++) {
            $requestResult = self::requestStylesheetWithRetry($currentUrl, $browserUserAgent, $deadline);
            $response      = isset($requestResult['response']) ? $requestResult['response'] : null;
            $totalAttempts += isset($requestResult['attempts']) ? (int) $requestResult['attempts'] : 0;
            $lastTimeout = isset($requestResult['timeout_seconds']) ? (float) $requestResult['timeout_seconds'] : $lastTimeout;
            $budgetExhausted = $budgetExhausted || ! empty($requestResult['budget_exhausted']);

            if (is_wp_error($response)) {
                return array(
                    'success'          => false,
                    'code'             => $response->get_error_code(),
                    'message'          => $response->get_error_message(),
                    'attempts'         => $totalAttempts,
                    'redirects'        => $redirectCount,
                    'http_status'      => 0,
                    'final_url'        => $currentUrl,
                    'timeout_seconds'  => $lastTimeout,
                    'budget_exhausted' => $budgetExhausted
                );
            }

            $responseCode = (int) wp_remote_retrieve_response_code($response);

            if (in_array($responseCode, array(301, 302, 303, 307, 308), true)) {
                $location = wp_remote_retrieve_header($response, 'location');

                if ($redirectCount >= self::MAX_STYLESHEET_REDIRECTS || ! is_string($location)) {
                    return array(
                        'success'          => false,
                        'code'             => 'too_many_redirects',
                        'message'          => __('The Google Fonts stylesheet redirected too many times.', 'wp-asset-clean-up'),
                        'attempts'         => $totalAttempts,
                        'redirects'        => $redirectCount,
                        'http_status'      => $responseCode,
                        'final_url'        => $currentUrl,
                        'timeout_seconds'  => $lastTimeout,
                        'budget_exhausted' => $budgetExhausted
                    );
                }

                $redirectUrl = FontPreloadScanner::normaliseUrl($location, $currentUrl);
                $redirectUrl = self::normaliseAllowedStylesheetUrl($redirectUrl);

                if ($redirectUrl === '') {
                    return array(
                        'success'          => false,
                        'code'             => 'unsafe_redirect',
                        'message'          => __('The Google Fonts stylesheet redirected outside the allowed host.', 'wp-asset-clean-up'),
                        'attempts'         => $totalAttempts,
                        'redirects'        => $redirectCount,
                        'http_status'      => $responseCode,
                        'final_url'        => $currentUrl,
                        'timeout_seconds'  => $lastTimeout,
                        'budget_exhausted' => $budgetExhausted
                    );
                }

                $currentUrl = $redirectUrl;
                continue;
            }

            if ($responseCode < 200 || $responseCode >= 300) {
                return array(
                    'success'          => false,
                    'code'             => 'http_' . $responseCode,
                    'message'          => sprintf(
                        __('The Google Fonts stylesheet returned HTTP %d.', 'wp-asset-clean-up'),
                        $responseCode
                    ),
                    'attempts'         => $totalAttempts,
                    'redirects'        => $redirectCount,
                    'http_status'      => $responseCode,
                    'final_url'        => $currentUrl,
                    'timeout_seconds'  => $lastTimeout,
                    'budget_exhausted' => $budgetExhausted
                );
            }

            $body = wp_remote_retrieve_body($response);

            if ( ! is_string($body) || trim($body) === '' ) {
                return array(
                    'success'          => false,
                    'code'             => 'empty_response',
                    'message'          => __('The Google Fonts stylesheet response was empty.', 'wp-asset-clean-up'),
                    'attempts'         => $totalAttempts,
                    'redirects'        => $redirectCount,
                    'http_status'      => $responseCode,
                    'final_url'        => $currentUrl,
                    'timeout_seconds'  => $lastTimeout,
                    'budget_exhausted' => $budgetExhausted
                );
            }

            if (strlen($body) >= self::MAX_STYLESHEET_BYTES) {
                return array(
                    'success'          => false,
                    'code'             => 'response_too_large',
                    'message'          => __('The Google Fonts stylesheet response was larger than the scanner limit.', 'wp-asset-clean-up'),
                    'attempts'         => $totalAttempts,
                    'redirects'        => $redirectCount,
                    'http_status'      => $responseCode,
                    'final_url'        => $currentUrl,
                    'timeout_seconds'  => $lastTimeout,
                    'budget_exhausted' => $budgetExhausted
                );
            }

            if (stripos($body, '@font-face') === false) {
                return array(
                    'success'          => false,
                    'code'             => 'not_font_css',
                    'message'          => __('The response did not contain any @font-face rules.', 'wp-asset-clean-up'),
                    'attempts'         => $totalAttempts,
                    'redirects'        => $redirectCount,
                    'http_status'      => $responseCode,
                    'final_url'        => $currentUrl,
                    'timeout_seconds'  => $lastTimeout,
                    'budget_exhausted' => $budgetExhausted
                );
            }

            set_transient($cacheKey, array('body' => $body), self::STYLESHEET_CACHE_TTL);

            return array(
                'success'          => true,
                'body'             => $body,
                'cached'           => false,
                'attempts'         => $totalAttempts,
                'redirects'        => $redirectCount,
                'http_status'      => $responseCode,
                'final_url'        => $currentUrl,
                'response_bytes'   => strlen($body),
                'timeout_seconds'  => $lastTimeout,
                'budget_exhausted' => $budgetExhausted
            );
        }

        return array(
            'success'          => false,
            'code'             => 'request_failed',
            'message'          => __('The Google Fonts stylesheet could not be resolved.', 'wp-asset-clean-up'),
            'attempts'         => $totalAttempts,
            'redirects'        => self::MAX_STYLESHEET_REDIRECTS,
            'http_status'      => 0,
            'final_url'        => $currentUrl,
            'timeout_seconds'  => $lastTimeout,
            'budget_exhausted' => $budgetExhausted
        );
    }

    /**
     * Retry only transient transport failures, throttling and server errors.
     * Validation and redirect handling remain in resolveStylesheet().
     *
     * @param string $stylesheetUrl
     * @param string $browserUserAgent
     * @param float  $deadline Absolute microtime deadline; 0 disables the budget.
     *
     * @return array
     */
    private static function requestStylesheetWithRetry($stylesheetUrl, $browserUserAgent, $deadline = 0.0)
    {
        $response = null;
        $attempts = 0;
        $timeoutSeconds = 0;
        $budgetExhausted = false;

        for ($attempt = 1; $attempt <= self::REMOTE_REQUEST_ATTEMPTS; $attempt++) {
            if ($deadline > 0) {
                $remainingTime = $deadline - microtime(true);

                if ($remainingTime < self::MIN_REMOTE_REQUEST_TIMEOUT + self::REQUEST_BUDGET_GRACE) {
                    $budgetExhausted = true;
                    $response = new \WP_Error(
                        'time_budget_exhausted',
                        __('The Google Fonts resolver stopped before the PHP request timeout and returned partial results.', 'wp-asset-clean-up')
                    );
                    break;
                }

                $timeoutSeconds = min(
                    self::REMOTE_REQUEST_TIMEOUT,
                    max(self::MIN_REMOTE_REQUEST_TIMEOUT, $remainingTime - self::REQUEST_BUDGET_GRACE)
                );
                $timeoutSeconds = round($timeoutSeconds, 2);
            } else {
                $timeoutSeconds = self::REMOTE_REQUEST_TIMEOUT;
            }

            $attempts = $attempt;
            $response = wp_safe_remote_get($stylesheetUrl, array(
                'timeout'             => $timeoutSeconds,
                'redirection'         => 0,
                'reject_unsafe_urls'  => true,
                'limit_response_size' => self::MAX_STYLESHEET_BYTES,
                'headers'             => array(
                    'Accept'     => 'text/css,*/*;q=0.1',
                    'User-Agent' => $browserUserAgent
                )
            ));

            $shouldRetry = is_wp_error($response);

            if ( ! $shouldRetry ) {
                $responseCode = (int) wp_remote_retrieve_response_code($response);
                $shouldRetry = $responseCode === 429 || ($responseCode >= 500 && $responseCode <= 599);
            }

            if ( ! $shouldRetry || $attempt >= self::REMOTE_REQUEST_ATTEMPTS ) {
                break;
            }

            if ($deadline > 0) {
                $minimumTimeForRetry = (self::REMOTE_RETRY_DELAY_US / 1000000)
                    + self::MIN_REMOTE_REQUEST_TIMEOUT
                    + self::REQUEST_BUDGET_GRACE;

                if (($deadline - microtime(true)) < $minimumTimeForRetry) {
                    $budgetExhausted = true;
                    break;
                }
            }

            usleep(self::REMOTE_RETRY_DELAY_US);
        }

        return array(
            'response'         => $response,
            'attempts'         => $attempts,
            'timeout_seconds'  => $timeoutSeconds,
            'budget_exhausted' => $budgetExhausted
        );
    }

    /**
     * Whether repeating a failed resolver request has a realistic chance of
     * succeeding without changing the stylesheet URL or the remote response.
     *
     * Client/validation failures such as HTTP 400 are deterministic. Showing a
     * Retry button for those failures is misleading and simply repeats the same
     * invalid request. Transport failures, throttling, server failures and an
     * exhausted resolver time budget remain retryable.
     *
     * @param array $stylesheetResult
     *
     * @return bool
     */
    private static function isRetryableResolutionFailure($stylesheetResult)
    {
        if ( ! is_array($stylesheetResult) ) {
            return true;
        }

        if ( ! empty($stylesheetResult['budget_exhausted']) ) {
            return true;
        }

        $code = isset($stylesheetResult['code']) ? (string) $stylesheetResult['code'] : '';
        $httpStatus = isset($stylesheetResult['http_status']) ? (int) $stylesheetResult['http_status'] : 0;

        if (in_array($code, array(
            'unsafe_redirect',
            'too_many_redirects',
            'response_too_large',
            'not_font_css'
        ), true)) {
            return false;
        }

        if ($code === 'time_budget_exhausted') {
            return true;
        }

        if ($httpStatus === 408 || $httpStatus === 425 || $httpStatus === 429
            || ($httpStatus >= 500 && $httpStatus <= 599)) {
            return true;
        }

        if ($httpStatus >= 400) {
            return false;
        }

        // A missing HTTP response normally represents a network/transport
        // failure. An unexpectedly empty successful response can also be
        // temporary, so one explicit manual retry remains reasonable.
        return $httpStatus === 0 || in_array($code, array('empty_response', 'request_failed'), true);
    }

    /**
     * @param string $stylesheetUrl
     *
     * @return string
     */
    private static function normaliseAllowedStylesheetUrl($stylesheetUrl)
    {
        $stylesheetUrl = FontPreloadScanner::normaliseUrl($stylesheetUrl, 'https://fonts.googleapis.com/');

        if ($stylesheetUrl === '') {
            return '';
        }

        $parts  = parse_url($stylesheetUrl);
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $host   = isset($parts['host']) ? strtolower($parts['host']) : '';
        $port   = isset($parts['port']) ? (int) $parts['port'] : 0;
        $path   = isset($parts['path']) ? $parts['path'] : '';

        $validPort = ($scheme === 'https' && ($port === 0 || $port === 443))
            || ($scheme === 'http' && ($port === 0 || $port === 80));

        if ($host !== 'fonts.googleapis.com' || ! $validPort) {
            return '';
        }

        if ( ! preg_match('#^/(?:css2?|icon)(?:/|$)#i', $path) ) {
            return '';
        }

        if (strlen($stylesheetUrl) > 10000) {
            return '';
        }

        return $stylesheetUrl;
    }

    /**
     * Parse Google Fonts @font-face blocks without assuming a fixed API format.
     *
     * @param string $css
     * @param string $stylesheetUrl
     *
     * @return array
     */
    public static function parseFontFaces($css, $stylesheetUrl)
    {
        $fontFaces = array();
        $matches   = array();

        if ( ! is_string($css) || stripos($css, '@font-face') === false ) {
            return $fontFaces;
        }

        preg_match_all(
            '#(?:/\*\s*([^*]*?)\s*\*/\s*)?@font-face\s*\{([^}]*)\}#is',
            $css,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $fontFaceMatch) {
            $subset       = isset($fontFaceMatch[1]) ? self::normaliseSubsetComment($fontFaceMatch[1]) : '';
            $declarations = self::parseDeclarations(isset($fontFaceMatch[2]) ? $fontFaceMatch[2] : '');

            if (empty($declarations['src']) || empty($declarations['font-family'])) {
                continue;
            }

            $sourceUrls = self::extractSourceUrls($declarations['src'], $stylesheetUrl);

            if (empty($sourceUrls)) {
                continue;
            }

            $family = trim($declarations['font-family']);
            $family = trim($family, " \t\n\r\0\x0B\"'");

            $queryFlags = self::getStylesheetQueryFlags($stylesheetUrl);
            $weight     = isset($declarations['font-weight']) ? trim($declarations['font-weight']) : 'normal';
            $stretch    = isset($declarations['font-stretch']) ? trim($declarations['font-stretch']) : 'normal';
            $variation  = isset($declarations['font-variation-settings']) ? trim($declarations['font-variation-settings']) : '';

            $fontFaces[] = array(
                'family'            => $family,
                'style'             => isset($declarations['font-style']) ? trim($declarations['font-style']) : 'normal',
                'weight'            => $weight,
                'stretch'           => $stretch,
                'unicodeRange'      => isset($declarations['unicode-range']) ? trim($declarations['unicode-range']) : '',
                'variationSettings' => $variation,
                'featureSettings'   => isset($declarations['font-feature-settings']) ? trim($declarations['font-feature-settings']) : '',
                'display'           => isset($declarations['font-display']) ? trim($declarations['font-display']) : '',
                'hasLocalSource'    => (bool) preg_match('/\blocal\s*\(/i', $declarations['src']),
                'subset'            => $subset,
                'sourceStylesheet'  => $stylesheetUrl,
                'isVariable'        => self::looksVariableDescriptor($weight) || self::looksVariableDescriptor($stretch) || $variation !== '',
                'isIconFont'        => (bool) preg_match('/^Material (?:Icons|Symbols)/i', $family),
                'usesTextSubset'    => $queryFlags['usesTextSubset'],
                'usesIconNames'     => $queryFlags['usesIconNames'],
                'sourceUrls'        => $sourceUrls
            );
        }

        return $fontFaces;
    }

    /**
     * Build a source-ambiguity key for the descriptors exposed by FontFaceSet.
     * The source stylesheet is included because the browser page reports which
     * Google stylesheet it loaded for the current check.
     *
     * @param array $fontFace
     *
     * @return string
     */
    private static function getFontFaceDescriptorKey($fontFace)
    {
        if (empty($fontFace['family']) || empty($fontFace['sourceStylesheet'])) {
            return '';
        }

        $family = self::normaliseDescriptorValue($fontFace['family']);
        $style  = self::normaliseDescriptorValue(isset($fontFace['style']) ? $fontFace['style'] : 'normal');
        $weight = self::normaliseDescriptorValue(isset($fontFace['weight']) ? $fontFace['weight'] : 'normal');
        $stretch = self::normaliseDescriptorValue(isset($fontFace['stretch']) ? $fontFace['stretch'] : 'normal');
        $unicodeRange = self::normaliseDescriptorValue(isset($fontFace['unicodeRange']) ? $fontFace['unicodeRange'] : '', true);
        $variation = self::normaliseDescriptorValue(isset($fontFace['variationSettings']) ? $fontFace['variationSettings'] : '', true);
        $features = self::normaliseDescriptorValue(isset($fontFace['featureSettings']) ? $fontFace['featureSettings'] : '', true);

        if ($weight === 'normal' || $weight === 'regular') {
            $weight = '400';
        } elseif ($weight === 'bold') {
            $weight = '700';
        }

        if ($stretch === 'normal') {
            $stretch = '100%';
        }

        if ($unicodeRange === 'u+0-10ffff' || $unicodeRange === 'u+0000-10ffff') {
            $unicodeRange = '';
        }

        return implode("\0", array(
            FontPreloadScanner::normaliseUrl($fontFace['sourceStylesheet'], $fontFace['sourceStylesheet']),
            $family,
            $style,
            $weight,
            $stretch,
            $unicodeRange,
            $variation,
            $features
        ));
    }

    /**
     * @param string $value
     * @param bool   $removeWhitespace
     *
     * @return string
     */
    private static function normaliseDescriptorValue($value, $removeWhitespace = false)
    {
        $value = strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($removeWhitespace) {
            $value = preg_replace('/\s*,\s*/', ',', $value);
            $value = preg_replace('/\s+/', '', $value);
        }

        return $value;
    }

    /**
     * @param string $declarationBlock
     *
     * @return array
     */
    private static function parseDeclarations($declarationBlock)
    {
        $declarations = array();
        $parts        = self::splitCssAtTopLevel($declarationBlock, ';');

        foreach ($parts as $part) {
            $colonPosition = self::findTopLevelDelimiter($part, ':');

            if ($colonPosition === false) {
                continue;
            }

            $property = strtolower(trim(substr($part, 0, $colonPosition)));
            $value    = trim(substr($part, $colonPosition + 1));

            if ($property === '' || $value === '') {
                continue;
            }

            $declarations[$property] = $value;
        }

        return $declarations;
    }

    /**
     * @param string $srcValue
     * @param string $stylesheetUrl
     *
     * @return array
     */
    private static function extractSourceUrls($srcValue, $stylesheetUrl)
    {
        $urls    = array();
        $matches = array();

        preg_match_all('#url\(\s*(["\']?)(.*?)\1\s*\)#is', $srcValue, $matches, PREG_SET_ORDER);

        foreach ($matches as $sourceMatch) {
            $sourceValue = isset($sourceMatch[2]) ? trim($sourceMatch[2]) : '';
            $sourceUrl   = FontPreloadScanner::normaliseUrl($sourceValue, $stylesheetUrl);

            if ($sourceUrl !== '' && ! in_array($sourceUrl, $urls, true)) {
                $urls[] = $sourceUrl;
            }
        }

        return $urls;
    }

    /**
     * @param string $input
     * @param string $delimiter
     *
     * @return array
     */
    private static function splitCssAtTopLevel($input, $delimiter)
    {
        $parts        = array();
        $buffer       = '';
        $quote        = '';
        $escape       = false;
        $parentheses  = 0;
        $inputLength  = strlen($input);

        for ($index = 0; $index < $inputLength; $index++) {
            $character = $input[$index];

            if ($escape) {
                $buffer .= $character;
                $escape = false;
                continue;
            }

            if ($character === '\\') {
                $buffer .= $character;
                $escape = true;
                continue;
            }

            if ($quote !== '') {
                $buffer .= $character;

                if ($character === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $buffer .= $character;
                continue;
            }

            if ($character === '(') {
                $parentheses++;
            } elseif ($character === ')' && $parentheses > 0) {
                $parentheses--;
            }

            if ($character === $delimiter && $parentheses === 0) {
                $parts[] = $buffer;
                $buffer  = '';
                continue;
            }

            $buffer .= $character;
        }

        if (trim($buffer) !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    /**
     * @param string $input
     * @param string $delimiter
     *
     * @return int|false
     */
    private static function findTopLevelDelimiter($input, $delimiter)
    {
        $quote       = '';
        $escape      = false;
        $parentheses = 0;
        $length      = strlen($input);

        for ($index = 0; $index < $length; $index++) {
            $character = $input[$index];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($character === '\\') {
                $escape = true;
                continue;
            }

            if ($quote !== '') {
                if ($character === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                continue;
            }

            if ($character === '(') {
                $parentheses++;
                continue;
            }

            if ($character === ')' && $parentheses > 0) {
                $parentheses--;
                continue;
            }

            if ($character === $delimiter && $parentheses === 0) {
                return $index;
            }
        }

        return false;
    }

    /**
     * @param string $comment
     *
     * @return string
     */
    private static function normaliseSubsetComment($comment)
    {
        $comment = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($comment)));

        if ($comment === '' || strlen($comment) > 80) {
            return '';
        }

        return $comment;
    }

    /**
     * @param string $stylesheetUrl
     *
     * @return array
     */
    private static function getStylesheetQueryFlags($stylesheetUrl)
    {
        $query = parse_url(str_replace(array('&amp;', '&#038;'), '&', $stylesheetUrl), PHP_URL_QUERY);
        $query = is_string($query) ? $query : '';

        return array(
            'usesTextSubset' => (bool) preg_match('/(?:^|&)text(?:=|&|$)/i', $query),
            'usesIconNames'  => (bool) preg_match('/(?:^|&)icon_names(?:=|&|$)/i', $query)
        );
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private static function looksVariableDescriptor($value)
    {
        $value = trim((string) $value);

        return (bool) preg_match('/^-?(?:\d+(?:\.\d+)?|normal)\s+-?(?:\d+(?:\.\d+)?|normal)$/i', $value);
    }

    /**
     * Normalise only Google-hosted font files sent from the Settings UI.
     *
     * These values are used solely to scope the compatibility diagnostic. They
     * never become remote request destinations; only the already allowlisted
     * fonts.googleapis.com stylesheet URLs are fetched.
     *
     * @param mixed $fontUrls
     *
     * @return array
     */
    private static function normaliseConfiguredGoogleFontUrls($fontUrls)
    {
        if ( ! is_array($fontUrls) ) {
            return array();
        }

        $normalised = array();

        foreach ($fontUrls as $fontUrl) {
            if ( ! is_scalar($fontUrl) ) {
                continue;
            }

            $fontUrl = FontPreloadScanner::normaliseUrl((string) $fontUrl, 'https://fonts.gstatic.com/');

            if ($fontUrl === '' || ! FontPreloadScanner::isAllowedGoogleFontFileUrl($fontUrl)) {
                continue;
            }

            $normalised[$fontUrl] = true;
        }

        return array_keys($normalised);
    }

    /**
     * Compare Google stylesheet mappings across representative browser UAs.
     *
     * Google Fonts CSS can be User-Agent dependent. A manually copied gstatic
     * URL may therefore represent the requested family/weight in one browser but
     * map to another binary in another browser. This diagnostic compares CSS
     * mappings only; it does NOT claim that the representative browser would
     * actually request the file on a given page. Real usage still comes solely
     * from the browser-assisted page checks.
     *
     * To keep the legacy audit unobtrusive, only a small capped set of observed
     * stylesheets is probed and all responses use the existing transient cache.
     *
     * @param array  $stylesheetUrls
     * @param array  $configuredFontUrls
     * @param string $currentUserAgent
     * @param array  $currentFontFaces
     * @param array  $currentFailedStylesheetUrls
     * @param float  $deadline
     *
     * @return array
     */
    private static function buildBrowserCompatibility($stylesheetUrls, $configuredFontUrls, $currentUserAgent, $currentFontFaces, $currentFailedStylesheetUrls, $deadline = 0.0)
    {
        $empty = array('byUrl' => array(), 'profiles' => array(), 'budgetExhausted' => false);

        if (empty($configuredFontUrls) || empty($stylesheetUrls)) {
            return $empty;
        }

        $stylesheetUrls = array_values(array_unique($stylesheetUrls));
        $currentFontFaces = is_array($currentFontFaces) ? $currentFontFaces : array();
        $currentFailedStylesheetUrls = is_array($currentFailedStylesheetUrls)
            ? array_values(array_unique($currentFailedStylesheetUrls))
            : array();
        $profiles = self::getRepresentativeBrowserProfiles($currentUserAgent);

        // Scope each configured file to the stylesheet(s) that actually returned
        // it for the current browser and to any unresolved stylesheet that asks
        // Google for the same family. A broken test URL for Arial must not affect
        // Heebo or Playfair, while a failed Heebo stylesheet remains relevant and
        // keeps the diagnostic conservative.
        $fontStylesheetsByUrl = array();
        $fontFamiliesByUrl = array();
        foreach ($configuredFontUrls as $fontUrl) {
            $fontStylesheetsByUrl[$fontUrl] = array();
            $fontFamiliesByUrl[$fontUrl] = array();
        }

        foreach ($currentFontFaces as $face) {
            if (empty($face['sourceStylesheet']) || empty($face['sourceUrls']) || ! is_array($face['sourceUrls'])) {
                continue;
            }

            $sourceStylesheet = (string) $face['sourceStylesheet'];

            foreach ($configuredFontUrls as $fontUrl) {
                if ( ! in_array($fontUrl, $face['sourceUrls'], true) ) {
                    continue;
                }

                $fontStylesheetsByUrl[$fontUrl][$sourceStylesheet] = true;

                if ( ! empty($face['family']) ) {
                    $family = self::normaliseGoogleFontFamilyName($face['family']);
                    if ($family !== '') {
                        $fontFamiliesByUrl[$fontUrl][$family] = true;
                    }
                }
            }
        }

        $requestedFamiliesByStylesheet = array();
        foreach ($stylesheetUrls as $stylesheetUrl) {
            $requestedFamiliesByStylesheet[$stylesheetUrl] = self::getRequestedGoogleFontFamilies($stylesheetUrl);
        }

        $fontRelevantStylesheetsByUrl = array();
        $priorityStylesheets = array();

        foreach ($configuredFontUrls as $fontUrl) {
            $exactStylesheets = array_keys($fontStylesheetsByUrl[$fontUrl]);
            $knownFamilies = array_keys($fontFamiliesByUrl[$fontUrl]);
            $relevantStylesheets = array();

            foreach ($stylesheetUrls as $stylesheetUrl) {
                if (in_array($stylesheetUrl, $exactStylesheets, true)) {
                    $relevantStylesheets[$stylesheetUrl] = true;
                    continue;
                }

                // Without a reliable exact mapping/family, do not guess: every
                // observed stylesheet remains potentially relevant.
                if (empty($exactStylesheets) || empty($knownFamilies)) {
                    $relevantStylesheets[$stylesheetUrl] = true;
                    continue;
                }

                $requestedFamilies = isset($requestedFamiliesByStylesheet[$stylesheetUrl])
                    ? $requestedFamiliesByStylesheet[$stylesheetUrl]
                    : array();

                // Unknown/non-family endpoint syntax remains conservative. A
                // parsed, disjoint family list is the only case safe to ignore.
                if (empty($requestedFamilies) || array_intersect($knownFamilies, $requestedFamilies)) {
                    $relevantStylesheets[$stylesheetUrl] = true;
                }
            }

            $fontRelevantStylesheetsByUrl[$fontUrl] = array_keys($relevantStylesheets);
            $priorityStylesheets = array_merge($priorityStylesheets, $fontRelevantStylesheetsByUrl[$fontUrl]);
        }

        // Probe relevant stylesheets first, then use any remaining small
        // comparison budget for diagnostics that could not be semantically scoped.
        $probeStylesheets = array_values(array_unique(array_merge($priorityStylesheets, $stylesheetUrls)));
        $probeStylesheets = array_slice($probeStylesheets, 0, self::MAX_COMPARISON_STYLESHEETS);
        $probeStylesheetLookup = array_fill_keys($probeStylesheets, true);
        $currentFailedLookup = array_fill_keys($currentFailedStylesheetUrls, true);
        $budgetExhausted = false;

        $currentFacesByStylesheet = array();
        foreach ($currentFontFaces as $face) {
            if (empty($face['sourceStylesheet'])) {
                continue;
            }

            $sourceStylesheet = (string) $face['sourceStylesheet'];
            if ( ! isset($probeStylesheetLookup[$sourceStylesheet]) ) {
                continue;
            }

            if ( ! isset($currentFacesByStylesheet[$sourceStylesheet]) ) {
                $currentFacesByStylesheet[$sourceStylesheet] = array();
            }
            $currentFacesByStylesheet[$sourceStylesheet][] = $face;
        }

        $currentProbeFailures = count(array_intersect($probeStylesheets, $currentFailedStylesheetUrls));
        $profileData = array(
            'current' => array(
                'facesByStylesheet' => $currentFacesByStylesheet,
                'failedStylesheets' => $currentFailedLookup
            )
        );
        $profileMetadata = array(
            'current' => array(
                'id' => 'current',
                'label' => __('Current browser', 'wp-asset-clean-up'),
                'family' => self::detectBrowserFamily($currentUserAgent),
                'current' => true,
                'resolvedStylesheets' => max(0, count($probeStylesheets) - $currentProbeFailures),
                'failedStylesheets' => $currentProbeFailures
            )
        );

        foreach ($profiles as $profile) {
            $facesByStylesheet = array();
            $failedStylesheetLookup = array();
            $resolvedCount = 0;
            $failedCount = 0;

            foreach ($probeStylesheets as $stylesheetUrl) {
                $result = self::resolveStylesheet($stylesheetUrl, $profile['userAgent'], $deadline);

                if ( ! empty($result['budget_exhausted']) ) {
                    $budgetExhausted = true;
                }

                if (empty($result['success'])) {
                    $failedCount++;
                    $failedStylesheetLookup[$stylesheetUrl] = true;
                    continue;
                }

                $resolvedCount++;
                $facesByStylesheet[$stylesheetUrl] = self::parseFontFaces($result['body'], $stylesheetUrl);
            }

            $profileData[$profile['id']] = array(
                'facesByStylesheet' => $facesByStylesheet,
                'failedStylesheets' => $failedStylesheetLookup
            );
            $profileMetadata[$profile['id']] = array(
                'id' => $profile['id'],
                'label' => $profile['label'],
                'family' => $profile['family'],
                'current' => false,
                'resolvedStylesheets' => $resolvedCount,
                'failedStylesheets' => $failedCount
            );
        }

        $byUrl = array();

        foreach ($configuredFontUrls as $fontUrl) {
            $allRelevantStylesheets = isset($fontRelevantStylesheetsByUrl[$fontUrl])
                ? $fontRelevantStylesheetsByUrl[$fontUrl]
                : $stylesheetUrls;
            $scopeKnown = ! empty($fontStylesheetsByUrl[$fontUrl]) && ! empty($fontFamiliesByUrl[$fontUrl]);
            $relevantStylesheets = array_values(array_intersect($probeStylesheets, $allRelevantStylesheets));
            $scopeComplete = count($relevantStylesheets) === count($allRelevantStylesheets);

            $descriptorKeys = array();
            foreach ($profileData as $profile) {
                foreach ($relevantStylesheets as $stylesheetUrl) {
                    $profileFaces = isset($profile['facesByStylesheet'][$stylesheetUrl])
                        && is_array($profile['facesByStylesheet'][$stylesheetUrl])
                        ? $profile['facesByStylesheet'][$stylesheetUrl]
                        : array();

                    foreach ($profileFaces as $face) {
                        if (empty($face['sourceUrls']) || ! in_array($fontUrl, $face['sourceUrls'], true)) {
                            continue;
                        }

                        $descriptorKey = self::getFontFaceDescriptorKey($face);
                        if ($descriptorKey !== '') {
                            $descriptorKeys[$descriptorKey] = true;
                        }
                    }
                }
            }

            $profileResults = array();
            $exactProfileCount = 0;
            $reliableProfileCount = 0;
            $alternativeProfileCount = 0;
            $descriptorKeys = array_keys($descriptorKeys);

            foreach ($profileData as $profileId => $profile) {
                $exactReturned = false;
                $alternatives = array();
                $matchingLabels = array();
                $failedRelevantStylesheets = array();
                $profileFaces = array();

                foreach ($relevantStylesheets as $stylesheetUrl) {
                    if ( ! empty($profile['failedStylesheets'][$stylesheetUrl]) ) {
                        $failedRelevantStylesheets[] = $stylesheetUrl;
                        continue;
                    }

                    if ( ! empty($profile['facesByStylesheet'][$stylesheetUrl])
                        && is_array($profile['facesByStylesheet'][$stylesheetUrl]) ) {
                        $profileFaces = array_merge($profileFaces, $profile['facesByStylesheet'][$stylesheetUrl]);
                    }
                }

                foreach ($profileFaces as $face) {
                    $sourceUrls = ! empty($face['sourceUrls']) && is_array($face['sourceUrls'])
                        ? $face['sourceUrls']
                        : array();

                    if (in_array($fontUrl, $sourceUrls, true)) {
                        $exactReturned = true;
                        $matchingLabels[] = self::getCompatibilityFaceLabel($face);
                    }

                    if (empty($descriptorKeys)) {
                        continue;
                    }

                    $descriptorKey = self::getFontFaceDescriptorKey($face);
                    if ($descriptorKey === '' || ! in_array($descriptorKey, $descriptorKeys, true)) {
                        continue;
                    }

                    $matchingLabels[] = self::getCompatibilityFaceLabel($face);
                    foreach ($sourceUrls as $sourceUrl) {
                        if ($sourceUrl !== $fontUrl && FontPreloadScanner::isAllowedGoogleFontFileUrl($sourceUrl)) {
                            $alternatives[$sourceUrl] = true;
                        }
                    }
                }

                // Positive evidence is reliable by itself. A negative mapping is
                // reliable only when every stylesheet relevant to this exact font
                // file was included and resolved. Failures from unrelated test or
                // third-party stylesheets no longer downgrade this entry.
                $profileReliable = $exactReturned || ($scopeComplete && empty($failedRelevantStylesheets));

                if ($profileReliable) {
                    $reliableProfileCount++;
                    if ($exactReturned) {
                        $exactProfileCount++;
                    }
                }
                if ( ! empty($alternatives)) {
                    $alternativeProfileCount++;
                }

                $profileResults[] = array(
                    'id' => $profileId,
                    'label' => isset($profileMetadata[$profileId]['label']) ? $profileMetadata[$profileId]['label'] : $profileId,
                    'family' => isset($profileMetadata[$profileId]['family']) ? $profileMetadata[$profileId]['family'] : 'other',
                    'current' => ! empty($profileMetadata[$profileId]['current']),
                    'reliable' => $profileReliable,
                    'exactUrlReturned' => $exactReturned,
                    'alternativeUrls' => array_keys($alternatives),
                    'matchingVariations' => array_values(array_unique(array_filter($matchingLabels))),
                    'resolvedStylesheets' => max(0, count($relevantStylesheets) - count($failedRelevantStylesheets)),
                    'failedStylesheets' => count($failedRelevantStylesheets)
                );
            }

            $byUrl[$fontUrl] = array(
                'profiles' => $profileResults,
                'exactProfileCount' => $exactProfileCount,
                'testedProfileCount' => $reliableProfileCount,
                'alternativeProfileCount' => $alternativeProfileCount,
                'browserSpecific' => $reliableProfileCount > 1 && $exactProfileCount > 0 && $exactProfileCount < $reliableProfileCount,
                'descriptorKnown' => ! empty($descriptorKeys),
                'scopeKnown' => $scopeKnown,
                'scopeComplete' => $scopeComplete,
                'relevantStylesheets' => $allRelevantStylesheets,
                'probedRelevantStylesheets' => $relevantStylesheets
            );
        }

        return array(
            'byUrl' => $byUrl,
            'profiles' => array_values($profileMetadata),
            'budgetExhausted' => $budgetExhausted
        );
    }
    /**
     * Extract normalised family names from legacy and CSS2 Google Fonts URLs.
     * Repeated family query arguments are preserved (parse_str() would keep only
     * the last one), while axis/weight/style suffixes are intentionally ignored.
     *
     * @param string $stylesheetUrl
     *
     * @return array
     */
    private static function getRequestedGoogleFontFamilies($stylesheetUrl)
    {
        $parts = parse_url((string) $stylesheetUrl);
        $query = isset($parts['query']) ? (string) $parts['query'] : '';

        if ($query === '') {
            return array();
        }

        $families = array();

        foreach (explode('&', $query) as $queryPart) {
            $keyValue = explode('=', $queryPart, 2);
            $key = isset($keyValue[0]) ? strtolower(urldecode($keyValue[0])) : '';

            if ($key !== 'family' || ! isset($keyValue[1])) {
                continue;
            }

            $familyValue = urldecode($keyValue[1]);

            foreach (explode('|', $familyValue) as $familySpec) {
                // Both APIs append descriptors after the family name, e.g.
                // Roboto:400,700 or Roboto:wght@400;700.
                $colonPosition = strpos($familySpec, ':');
                if ($colonPosition !== false) {
                    $familySpec = substr($familySpec, 0, $colonPosition);
                }

                $family = self::normaliseGoogleFontFamilyName($familySpec);
                if ($family !== '') {
                    $families[$family] = true;
                }
            }
        }

        return array_keys($families);
    }

    /**
     * @param string $family
     *
     * @return string
     */
    private static function normaliseGoogleFontFamilyName($family)
    {
        $family = trim((string) $family, " \t\n\r\0\x0B\"'");
        $family = preg_replace('/\s+/', ' ', $family);

        return strtolower(trim((string) $family));
    }

    /**
     * Representative desktop UAs used only to compare Google CSS mappings.
     * The current browser family is omitted because its real UA was already
     * resolved above. These are diagnostic profiles, not browser emulation.
     *
     * @param string $currentUserAgent
     *
     * @return array
     */
    private static function getRepresentativeBrowserProfiles($currentUserAgent)
    {
        $currentFamily = self::detectBrowserFamily($currentUserAgent);
        $profiles = array(
            array(
                'id' => 'chrome',
                'label' => __('Representative Chrome', 'wp-asset-clean-up'),
                'family' => 'chrome',
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'
            ),
            array(
                'id' => 'firefox',
                'label' => __('Representative Firefox', 'wp-asset-clean-up'),
                'family' => 'firefox',
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0'
            ),
            array(
                'id' => 'safari',
                'label' => __('Representative Safari', 'wp-asset-clean-up'),
                'family' => 'safari',
                'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Safari/605.1.15'
            )
        );

        $output = array();
        foreach ($profiles as $profile) {
            if ($profile['family'] === $currentFamily) {
                continue;
            }
            $output[] = $profile;
            if (count($output) >= 2) {
                break;
            }
        }

        return $output;
    }

    /**
     * @param string $userAgent
     *
     * @return string
     */
    private static function detectBrowserFamily($userAgent)
    {
        $userAgent = strtolower((string) $userAgent);
        if (strpos($userAgent, 'firefox/') !== false) {
            return 'firefox';
        }
        if (strpos($userAgent, 'edg/') !== false || strpos($userAgent, 'chrome/') !== false || strpos($userAgent, 'chromium/') !== false) {
            return 'chrome';
        }
        if (strpos($userAgent, 'safari/') !== false && strpos($userAgent, 'chrome/') === false && strpos($userAgent, 'chromium/') === false) {
            return 'safari';
        }
        return 'other';
    }

    /**
     * @param array $face
     *
     * @return string
     */
    private static function getCompatibilityFaceLabel($face)
    {
        $parts = array();
        foreach (array('family', 'style', 'weight', 'stretch', 'subset') as $key) {
            if (isset($face[$key]) && trim((string) $face[$key]) !== '') {
                $parts[] = trim((string) $face[$key]);
            }
        }
        return implode(' · ', $parts);
    }

    /**
     * @return string
     */
    private static function getBrowserUserAgent()
    {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        if ($userAgent === '') {
            $userAgent = 'Mozilla/5.0 (compatible; WPAssetCleanUp-GoogleFontsScanner/' . (defined('WPACU_PLUGIN_VERSION') ? WPACU_PLUGIN_VERSION : '1.0') . ')';
        }

        return substr($userAgent, 0, 512);
    }
}
