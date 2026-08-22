<?php
namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Menu;

/**
 * Shared browser-assisted audit engine for legacy manual font preload settings.
 *
 * Primary question
 * ----------------
 * This scanner does not try to prove that a font is unused everywhere on the
 * site. Its narrower and safer question is: "Does this exact URL deserve to be
 * preloaded by WPACU on every applicable page?" Removing an entry from the
 * field stops only WPACU's site-wide <link rel="preload">; it does not remove
 * an @font-face rule, a Google Fonts stylesheet, or the font itself.
 *
 * Request lifecycle
 * -----------------
 * 1. The Settings-page AJAX action creates a short-lived token and stores the
 *    configured URLs plus the allowed parent origin in a transient.
 * 2. The admin runner opens representative public pages sequentially in hidden
 *    desktop/mobile-sized iframes.
 * 3. FontPreloadScannerEarly validates the token during active-plugin loading,
 *    bypasses page caches, and removes Query Monitor noise only for that request.
 * 4. This class suppresses WPACU's matching manual preload and prints the
 *    evidence collector in <head>. The first pass stays untrimmed for fidelity;
 *    only a failed/incomplete retry may trim unrelated browser fetches.
 * 5. The collector watches exact resource requests, readable @font-face rules,
 *    FontFaceSet state, rendered usage, Google stylesheet activity, and other
 *    compatible preload tags. It returns evidence per configured URL rather
 *    than waiting for every resource or every font on the page.
 * 6. The Settings runner merges retry evidence, applies conservative coverage
 *    rules, and exposes deterministic cleanup only for cases such as duplicates,
 *    invalid URLs, missing local files, or a confirmed equivalent preload.
 *
 * Accuracy and performance principles
 * -----------------------------------
 * - An exact natural request is strong positive evidence and cannot be
 *   downgraded by a weaker later signal.
 * - Absence from a sampled page is advisory, not proof of global non-use.
 * - Incomplete or ambiguous evidence remains Review and is never auto-selected.
 * - Pages run sequentially to avoid hammering slow WordPress installations.
 * - Unrelated images, media, trackers, debug output, and global font-set state
 *   must not hold a check open once the target font evidence is stable.
 *
 * Provider responsibilities
 * -------------------------
 * FontsLocalPreloadScanner and FontsGooglePreloadScanner are deliberately thin
 * public wrappers. Local URL/file semantics live here; Google CSS resolution,
 * @font-face mapping, subset/axis metadata, and SSRF restrictions live in the
 * Google provider class.
 *
 * Maintenance map
 * ---------------
 * - early-triggers.php: loads the dependency-light early bootstrap.
 * - FontPreloadScannerEarly.php: validates scan tokens before normal plugin
 *   initialisation, bypasses caches, and suppresses Query Monitor per request.
 * - FontPreloadScanner.php: common scan preparation, front-end collector, HTML
 *   trimming, evidence aggregation inputs, representative pages, and settings.
 * - FontsLocalPreloadScanner.php: stable Local Fonts facade/compatibility API.
 * - FontsGooglePreloadScanner.php: Google CSS resolver, parser, diagnostics, and
 *   network safety boundary.
 * - assets/local-fonts-preload-scanner.js: Settings-page task runner, retries,
 *   classification, result rendering, and removal/undo interactions for both
 *   providers (the historical filename is retained for compatibility).
 *
 * @package WpAssetCleanUp\OptimiseAssets
 */
class FontPreloadScanner
{
    const RISK_ACK_AJAX_ACTION = 'wpassetcleanup_ack_manual_font_preload_risk';
    const RISK_ACK_NONCE_ACTION = 'wpacu_ack_manual_font_preload_risk';
    const MAX_FONT_URLS  = 50;
    const MAX_SCAN_PAGES = 6;
    const MAX_EXTRA_URLS = 2;

    // Coverage rules used by the shared Local Fonts / Google Fonts audit.
    // A likely site-wide candidate must be confirmed on every checked page
    // and on at least 80% of the page/viewport checks. Near-misses are kept in
    // a separate broad-usage review state rather than being called selective.
    const SITE_WIDE_CANDIDATE_MIN_CHECK_COVERAGE = 0.80;
    const BROAD_USAGE_MIN_PAGE_COVERAGE           = 0.75;
    const BROAD_USAGE_MIN_CHECK_COVERAGE          = 0.60;

    // The browser audit is deliberately sequential to avoid hammering slower
    // WordPress installations. Leave enough time for an uncached page and one
    // automatic retry without allowing the signed scan URL to live indefinitely.
    const TOKEN_TTL = 1800;

    // Parent-frame timeout phases. The bootstrap timer stops as soon as the
    // collector announces that it has started; the evidence timer then begins.
    const BOOTSTRAP_TIMEOUT_MS       = 25000;
    const EVIDENCE_TIMEOUT_MS        = 40000;
    const HARD_TIMEOUT_MS            = 65000;
    const COLLECTOR_MISSING_GRACE_MS = 1800;
    const MAX_TASK_ATTEMPTS          = 2;
    const RETRY_DELAY_MS             = 300;

    // Child-frame evidence collection limits. The collector watches only the
    // configured font URLs and their relevant stylesheets. Unrelated images,
    // scripts and fonts must not keep a page/viewport check open.
    const DOM_OBSERVATION_MIN_MS        = 1800;
    const LOAD_WAIT_TIMEOUT_MS          = 3500;
    const TARGET_QUIET_PERIOD_MS        = 1000;
    // A positive request can finish early. A negative result must survive a
    // longer observation window when window.load has not fired, otherwise a
    // slow template can be classified before its navigation or icon font is
    // introduced by late JavaScript.
    const NEGATIVE_OBSERVATION_MIN_MS   = 12000;
    // Fidelity pass: when window.load has not settled, do not accept a negative
    // until a much longer window has elapsed. This protects late font triggers on
    // very heavy WooCommerce/builder pages while still allowing positive evidence
    // to finish immediately. The optimized retry keeps the shorter 12s gate.
    const FIDELITY_NEGATIVE_OBSERVATION_MIN_MS = 32000;
    const NEGATIVE_POST_LOAD_QUIET_MS   = 1250;
    const NEGATIVE_POST_LOAD_MIN_MS     = 6000;
    const TARGET_OBSERVATION_MAX_MS     = 8000;
    const TARGET_OBSERVATION_EXTENDED_MAX_MS = 35000;
    const TARGET_POLL_INTERVAL_MS       = 250;
    const CSS_EVIDENCE_CACHE_MS         = 650;
    const RENDERED_USAGE_CACHE_MS       = 5000;
    const RENDERED_USAGE_MAX_ELEMENTS   = 2500;
    const COLLECTOR_FALLBACK_TIMEOUT_MS = 38000;

    // A short-lived, signed verification URL can be opened in a normal browser
    // tab. It keeps WPACU's manual preload suppressed but skips scan-only HTML
    // trimming, making DevTools comparisons faithful to the real page output.
    const MANUAL_VERIFICATION_QUERY_ARG = 'wpacu_font_scan_verify';
    // The first automatic pass is deliberately untrimmed for fidelity. Only a
    // retry may opt into scan-only HTML trimming; a negative result from that
    // optimized fallback is never accepted as proof that a font is unused.
    const OPTIMIZED_FALLBACK_QUERY_ARG = 'wpacu_font_scan_optimized';

    /**
     * Per-provider memoisation flag for transient validation. A scan page may
     * ask isActiveRequest() from several runtime classes; the transient should
     * be read and security-checked only once for each provider.
     *
     * @var array
     */
    private static $requestDataResolved = array();

    /**
     * Validated request payloads, keyed by `local` or `google`. A false value is
     * cached as well, preventing repeated work for an invalid/public query arg.
     *
     * @var array
     */
    private static $requestData = array();

    /**
     * Guard against printing the inline collector more than once when multiple
     * runtime components initialise the same provider on a front-end request.
     *
     * @var array
     */
    private static $collectorPrinted = array();

    /**
     * Whether the scan-only HTML output filter has already been started.
     *
     * @var bool
     */
    private static $scanOutputBufferStarted = false;

    /**
     * WPML language selected when the scan was prepared. Representative-page
     * discovery uses it so a multilingual site is not silently audited in an
     * unrelated language.
     *
     * @var string
     */
    private static $scanLanguageCode = '';

    /**
     * Home URL cached while normalising relative font and extra-page URLs.
     *
     * @var string
     */
    private static $scanHomeUrl = '';

    public static function registerAdminHooks()
    {
        add_action('wp_ajax_' . self::RISK_ACK_AJAX_ACTION, array(__CLASS__, 'ajaxAcknowledgeManualPreloadRisk'));
    }

    private static function getRiskAcknowledgementKey($provider)
    {
        return $provider === 'google'
            ? 'wpacu_ack_google_manual_font_preload_risk_v1'
            : 'wpacu_ack_local_manual_font_preload_risk_v1';
    }

    public static function ajaxAcknowledgeManualPreloadRisk()
    {
        if ( ! Menu::userCanAccessPlugin()) {
            wp_send_json_error(array('message' => __('You are not allowed to change this preference.', 'wp-asset-clean-up')), 403);
        }

        check_ajax_referer(self::RISK_ACK_NONCE_ACTION, 'nonce');

        $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : '';
        if ( ! in_array($provider, array('google', 'local'), true)) {
            wp_send_json_error(array('message' => __('The font provider is not valid.', 'wp-asset-clean-up')), 400);
        }

        update_user_option(
            get_current_user_id(),
            self::getRiskAcknowledgementKey($provider),
            array('version' => 1, 'acknowledged_at' => time()),
            false
        );

        wp_send_json_success();
    }

    /**
     * Data exposed to the Settings page JavaScript.
     *
     * @param string $provider local|google
     *
     * @return array
     */
    public static function getAdminConfig($provider)
    {
        $definition = self::getProviderDefinition($provider);

        return array(
            'provider'      => $definition['provider'],
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'action'        => $definition['ajax_action'],
            'nonce'         => wp_create_nonce($definition['nonce_action']),
            'riskAckAction' => self::RISK_ACK_AJAX_ACTION,
            'riskAckNonce'  => wp_create_nonce(self::RISK_ACK_NONCE_ACTION),
            'riskAcknowledged' => (bool) get_user_option(self::getRiskAcknowledgementKey($provider), get_current_user_id()),
            'maxPages'      => self::MAX_SCAN_PAGES,
            'maxExtraUrls'  => self::MAX_EXTRA_URLS,
            'maxFontUrls'   => self::MAX_FONT_URLS,
            'siteWideCandidateMinCheckCoverage' => self::SITE_WIDE_CANDIDATE_MIN_CHECK_COVERAGE,
            'broadUsageMinPageCoverage'          => self::BROAD_USAGE_MIN_PAGE_COVERAGE,
            'broadUsageMinCheckCoverage'         => self::BROAD_USAGE_MIN_CHECK_COVERAGE,
            'languageCode'  => self::getWpmlLanguageCodeFromRequest(),
            // Keep taskTimeout for integrations that read the legacy key.
            'taskTimeout'          => self::HARD_TIMEOUT_MS,
            'bootstrapTimeout'     => self::BOOTSTRAP_TIMEOUT_MS,
            'evidenceTimeout'      => self::EVIDENCE_TIMEOUT_MS,
            'hardTimeout'          => self::HARD_TIMEOUT_MS,
            'collectorMissingGrace'=> self::COLLECTOR_MISSING_GRACE_MS,
            'maxAttempts'          => self::MAX_TASK_ATTEMPTS,
            'retryDelay'           => self::RETRY_DELAY_MS,
            'viewQueryArg'        => $definition['view_query_arg'],
            'verificationQueryArg'=> self::MANUAL_VERIFICATION_QUERY_ARG,
            'optimizedFallbackQueryArg' => self::OPTIMIZED_FALLBACK_QUERY_ARG,
            'readyType'     => $definition['message_ready'],
            'resultType'    => $definition['message_result'],
            'errorType'     => $definition['message_error'],
            'strings'       => self::getAdminStrings($provider),
            'views'         => self::getDefaultViews()
        );
    }

    /**
     * Prepare a short-lived scan token and return representative public pages.
     *
     * @param string $provider local|google
     *
     * @return void
     */
    public static function ajaxPrepareScan($provider)
    {
        $definition = self::getProviderDefinition($provider);

        if ( ! Menu::userCanAccessPlugin() ) {
            wp_send_json_error(array(
                'message' => __('You are not allowed to run this check.', 'wp-asset-clean-up')
            ));
        }

        if ( ! check_ajax_referer($definition['nonce_action'], 'nonce', false) ) {
            wp_send_json_error(array(
                'message' => __('The security check failed. Refresh the Settings page and try again.', 'wp-asset-clean-up')
            ));
        }

        $requestedLanguageCode = isset($_POST['language_code']) && is_string($_POST['language_code'])
            ? sanitize_key(wp_unslash($_POST['language_code']))
            : '';

        self::setWpmlScanLanguage($requestedLanguageCode);

        $fontUrlsRaw = isset($_POST['font_urls']) ? wp_unslash($_POST['font_urls']) : '';
        $fontUrls    = self::parseNonEmptyLines($fontUrlsRaw, self::MAX_FONT_URLS, false);

        if (empty($fontUrls)) {
            wp_send_json_error(array(
                'message' => __('Add at least one font URL before running the check.', 'wp-asset-clean-up')
            ));
        }

        $extraUrlsRaw = isset($_POST['extra_scan_urls']) ? wp_unslash($_POST['extra_scan_urls']) : '';
        $extraUrls    = self::parseNonEmptyLines($extraUrlsRaw, self::MAX_EXTRA_URLS, true);
        $scanPages    = self::getRepresentativePages($extraUrls);

        if (empty($scanPages)) {
            wp_send_json_error(array(
                'message' => __('No public pages could be selected for this check.', 'wp-asset-clean-up')
            ));
        }

        $token       = wp_generate_password(32, false, false);
        $fontEntries = self::buildFontEntries($provider, $fontUrls);

        $requestData = array(
            'provider'      => $definition['provider'],
            'token'         => $token,
            'font_urls'     => array_values($fontUrls),
            'font_entries'  => $fontEntries,
            'parent_origin' => self::getUrlOrigin(admin_url('/')),
            'created_at'    => time(),
            'expires_at'    => time() + self::TOKEN_TTL
        );

        if ( ! set_transient(self::getTransientName($provider, $token), $requestData, self::TOKEN_TTL) ) {
            wp_send_json_error(array(
                'message' => __('The temporary scan session could not be created. Check the site object cache and try again.', 'wp-asset-clean-up')
            ));
        }

        $pagesForResponse = array();

        foreach ($scanPages as $scanPage) {
            $scanUrl = add_query_arg(array(
                $definition['query_arg'] => $token,
                'wpacu_no_frontend_show' => '1',
                'wpacu_font_scan_cb'      => wp_generate_password(8, false, false)
            ), $scanPage['url']);

            $pagesForResponse[] = array(
                'label'          => $scanPage['label'],
                'url'            => $scanPage['url'],
                'displayUrl'     => self::getDisplayUrl($scanPage['url']),
                'scanUrl'        => $scanUrl,
                'allowedOrigins' => self::getAllowedSiteOrigins()
            );
        }

        wp_send_json_success(array(
            'token'       => $token,
            'pages'       => $pagesForResponse,
            'fontEntries' => $fontEntries,
            'views'       => self::getDefaultViews(),
            'expiresIn'   => self::TOKEN_TTL
        ));
    }

    /**
     * Initialise the collector on an authenticated, short-lived scan request.
     *
     * @param string $provider local|google
     *
     * @return void
     */
    public static function maybeInitFrontendCollector($provider)
    {
        if (is_admin() || ! self::getActiveRequestData($provider)) {
            return;
        }

        if ( ! defined('DONOTCACHEPAGE') ) {
            define('DONOTCACHEPAGE', true);
        }

        add_filter('show_admin_bar', '__return_false', PHP_INT_MAX);

        // Fidelity comes first: the initial automatic pass uses the normal page
        // output with only WPACU's manual preload suppressed. If that pass cannot
        // settle, the Settings runner retries once with OPTIMIZED_FALLBACK_QUERY_ARG
        // and only then do we neutralize expensive unrelated browser fetches.
        // Positive evidence from the optimized retry is useful; negative evidence
        // from a modified page is intentionally left incomplete/review-only.
        if (self::isOptimizedFallbackRequest($provider) && ! self::isManualVerificationRequest($provider)) {
            self::maybeStartScanOutputBuffer();
        }

        add_action('send_headers', function() use ($provider) {
            FontPreloadScanner::sendNoCacheHeaders($provider);
        }, 0);

        add_action('wp_head', function() use ($provider) {
            FontPreloadScanner::printFrontendCollector($provider);
        }, 0);
    }

    /**
     * Start a scan-only HTML output buffer once per request.
     *
     * This is intentionally an output-stage optimization: WordPress still runs
     * normally, so template/plugin logic that decides which fonts are needed is
     * preserved. Only browser fetches that cannot help the font audit are
     * neutralized before the response is sent.
     *
     * @return void
     */
    private static function maybeStartScanOutputBuffer()
    {
        if (self::$scanOutputBufferStarted || headers_sent()) {
            return;
        }

        self::$scanOutputBufferStarted = true;
        ob_start(array(__CLASS__, 'filterScanHtml'));
    }

    /**
     * Reduce unrelated network work on a signed scanner page while preserving
     * DOM shape, CSS/font declarations and functional theme/plugin JavaScript.
     *
     * @param string $html
     *
     * @return string
     */
    public static function filterScanHtml($html)
    {
        if ( ! is_string($html) || $html === '' || stripos($html, '<html') === false ) {
            return $html;
        }

        $stats = array(
            'images'       => 0,
            'media'        => 0,
            'iframes'      => 0,
            'tracking'     => 0,
            'hints'        => 0,
            'own_preloads' => 0
        );

        // The runtime method already suppresses WPACU's own manual preload.
        // Remove a stale copy defensively as well, e.g. when an HTML cache layer
        // reuses head markup while still allowing WordPress to inject the scanner.
        $html = preg_replace_callback(
            '#<link\b(?=[^>]*\bdata-wpacu-preload-(?:local|google)-font(?:\s|=|/?>))[^>]*>#i',
            static function($match) use (&$stats) {
                $stats['own_preloads']++;
                return '<!-- WPACU font scan: stale manual preload removed -->';
            },
            $html
        );

        // Keep <img>/<source> elements and their classes/dimensions in the DOM,
        // but prevent their real image candidates from being fetched. A tiny
        // transparent data URI avoids broken-image UI without network traffic.
        $transparentPixel = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
        $html = preg_replace_callback('#<(img|source)\b[^>]*>#i', static function($match) use ($transparentPixel, &$stats) {
            $tag = $match[0];
            $name = strtolower($match[1]);

            // A <source> inside <video>/<audio> is handled by the media pass.
            // Image sources (e.g. <picture>) are safe to neutralize here.
            if ($name === 'source' && ! preg_match('#\b(?:srcset|data-srcset)\s*=#i', $tag)) {
                return $tag;
            }

            $changed = false;
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'srcset', '', $changed);
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'data-srcset', '', $changed);
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'data-lazy-srcset', '', $changed);
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'src', $transparentPixel, $changed);
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'data-src', $transparentPixel, $changed);
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'data-lazy-src', $transparentPixel, $changed);
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'data-original', $transparentPixel, $changed);

            if ($changed) {
                $stats['images']++;
            }

            return $tag;
        }, $html);

        // Preserve media elements in the DOM but remove network-bearing source
        // attributes. Controls/layout hooks can still exist for application JS.
        $html = preg_replace_callback('#<(video|audio)\b[^>]*>#i', static function($match) use (&$stats) {
            $tag = $match[0];
            $changed = false;
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'src', '', $changed);
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'poster', '', $changed);
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'preload', 'none', $changed, true);
            if ($changed) {
                $stats['media']++;
            }
            return $tag;
        }, $html);

        $html = preg_replace_callback('#<source\b[^>]*>#i', static function($match) use (&$stats) {
            $tag = $match[0];
            // Picture sources were already neutralized above. Here we only need
            // to catch remaining src-bearing media sources.
            if (preg_match('#\b(?:srcset|data-srcset)\s*=#i', $tag)) {
                return $tag;
            }
            $changed = false;
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'src', '', $changed);
            if ($changed) {
                $stats['media']++;
            }
            return $tag;
        }, $html);

        // Iframe documents can dominate window/load time and their subresource font
        // requests are not evidence for the parent document being audited. Keep
        // the iframe element itself so surrounding layout stays intact.
        $html = preg_replace_callback('#<iframe\b[^>]*>#i', static function($match) use (&$stats) {
            $tag = $match[0];
            $src = FontPreloadScanner::getHtmlAttributeValue($tag, 'src');
            if ($src === '' || strpos($src, 'about:') === 0 || strpos($src, 'data:') === 0 || strpos($src, 'javascript:') === 0) {
                return $tag;
            }
            $changed = false;
            $tag = FontPreloadScanner::replaceHtmlAttributeValue($tag, 'src', 'about:blank', $changed);
            if ($changed) {
                $stats['iframes']++;
            }
            return $tag;
        }, $html);

        // Remove only well-known, pure analytics/tracking script hosts. Theme,
        // plugin, consent and UI scripts are deliberately left untouched because
        // they can influence responsive markup and dynamically requested fonts.
        $trackingHosts = array(
            'www.googletagmanager.com', 'googletagmanager.com',
            'www.google-analytics.com', 'google-analytics.com',
            'connect.facebook.net', 'static.hotjar.com', 'script.hotjar.com',
            'www.clarity.ms', 'clarity.ms', 'mc.yandex.ru',
            'counter.rambler.ru', 'top-fwz1.mail.ru'
        );
        $html = preg_replace_callback('#<script\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>\s*</script\s*>#is', static function($match) use ($trackingHosts, &$stats) {
            $host = FontPreloadScanner::getUrlHostForTrim(html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'));
            if ($host === '' || ! in_array($host, $trackingHosts, true)) {
                return $match[0];
            }
            $stats['tracking']++;
            return '<!-- WPACU font scan: tracking script skipped -->';
        }, $html);

        // Browser hints that explicitly fetch non-font payloads are unnecessary
        // in a font audit. Keep preconnect/dns-prefetch and every style/font hint.
        $html = preg_replace_callback('#<link\b[^>]*>#i', static function($match) use (&$stats) {
            $tag = $match[0];
            $rel = strtolower(FontPreloadScanner::getHtmlAttributeValue($tag, 'rel'));
            $as  = strtolower(FontPreloadScanner::getHtmlAttributeValue($tag, 'as'));

            $skip = ($rel === 'prefetch' || $rel === 'prerender' ||
                ($rel === 'preload' && in_array($as, array('image', 'video', 'audio'), true)));

            if ( ! $skip ) {
                return $tag;
            }

            $stats['hints']++;
            return '<!-- WPACU font scan: unrelated resource hint skipped -->';
        }, $html);

        // Stop visual effects from unnecessarily extending a scan. Do not hide
        // elements or change font/layout declarations.
        $scanCss = '<style id="wpacu-font-scan-lightweight-page">'
            . '*,*::before,*::after{animation-duration:0s!important;animation-delay:0s!important;transition-duration:0s!important;transition-delay:0s!important;scroll-behavior:auto!important;background-image:none!important;list-style-image:none!important;}'
            . 'img,video{content-visibility:auto;}'
            . '</style>';

        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('#</head>#i', $scanCss . '</head>', $html, 1);
        }

        $summary = '<!-- WPACU font scan safe trimming: images=' . (int) $stats['images']
            . '; media=' . (int) $stats['media']
            . '; iframes=' . (int) $stats['iframes']
            . '; tracking_scripts=' . (int) $stats['tracking']
            . '; resource_hints=' . (int) $stats['hints']
            . '; stale_own_preloads=' . (int) $stats['own_preloads'] . ' -->';

        return $summary . "\n" . $html;
    }

    /**
     * Replace an HTML attribute without parsing/re-serializing the full document.
     *
     * @param string $tag
     * @param string $attribute
     * @param string $value
     * @param bool   $changed
     * @param bool   $addIfMissing
     *
     * @return string
     */
    public static function replaceHtmlAttributeValue($tag, $attribute, $value, &$changed, $addIfMissing = false)
    {
        $pattern = '#(\s' . preg_quote($attribute, '#') . '\s*=\s*)(["\'])(.*?)\2#is';
        if (preg_match($pattern, $tag)) {
            $replacement = '$1$2' . str_replace(array('\\', '$'), array('\\\\', '\\$'), $value) . '$2';
            $newTag = preg_replace($pattern, $replacement, $tag, 1);
            if ($newTag !== $tag) {
                $changed = true;
                return $newTag;
            }
            return $tag;
        }

        if ($addIfMissing) {
            $newTag = preg_replace('#\s*/?>$#', ' ' . $attribute . '="' . esc_attr($value) . '"$0', $tag, 1);
            if ($newTag !== $tag) {
                $changed = true;
                return $newTag;
            }
        }

        return $tag;
    }

    /**
     * @param string $tag
     * @param string $attribute
     *
     * @return string
     */
    public static function getHtmlAttributeValue($tag, $attribute)
    {
        $pattern = '#\s' . preg_quote($attribute, '#') . '\s*=\s*(["\'])(.*?)\1#is';
        if (preg_match($pattern, $tag, $matches)) {
            return html_entity_decode(trim($matches[2]), ENT_QUOTES, 'UTF-8');
        }
        return '';
    }

    /**
     * @return array
     */
    private static function getAllowedSiteHostsForTrim()
    {
        $hosts = array();
        foreach (array(home_url('/'), site_url('/')) as $url) {
            $host = self::getUrlHostForTrim($url);
            if ($host !== '') {
                $hosts[$host] = true;
            }
        }
        return array_keys($hosts);
    }

    /**
     * @param string $url
     *
     * @return string
     */
    public static function getUrlHostForTrim($url)
    {
        if (strpos($url, '//') === 0) {
            $url = (is_ssl() ? 'https:' : 'http:') . $url;
        }
        $host = wp_parse_url($url, PHP_URL_HOST);
        return is_string($host) ? strtolower(rtrim($host, '.')) : '';
    }

    /**
     * @param string $url
     * @param array  $siteHosts
     *
     * @return bool
     */
    public static function isExternalUrlForTrim($url, $siteHosts)
    {
        if ($url === '' || strpos($url, '#') === 0 || strpos($url, 'javascript:') === 0 || strpos($url, 'data:') === 0 || strpos($url, 'about:') === 0) {
            return false;
        }
        $host = self::getUrlHostForTrim($url);
        return ($host !== '' && ! in_array($host, $siteHosts, true));
    }

    /**
     * Whether the current request is a valid scanner request for the provider.
     *
     * @param string $provider local|google
     *
     * @return bool
     */
    public static function isActiveRequest($provider)
    {
        return (bool) self::getActiveRequestData($provider);
    }

    /**
     * Whether this is the signed, untrimmed page used for a manual DevTools
     * comparison. The active scan token is validated before this flag matters.
     *
     * @param string $provider local|google
     *
     * @return bool
     */
    private static function isManualVerificationRequest($provider)
    {
        if ( ! self::isActiveRequest($provider) ) {
            return false;
        }

        if ( ! isset($_GET[self::MANUAL_VERIFICATION_QUERY_ARG]) ||
             ! is_string($_GET[self::MANUAL_VERIFICATION_QUERY_ARG]) ) {
            return false;
        }

        return sanitize_key(wp_unslash($_GET[self::MANUAL_VERIFICATION_QUERY_ARG])) === '1';
    }

    /**
     * Whether this validated scan request is the second-pass optimized fallback.
     *
     * This flag is accepted only inside an already validated scanner request. It
     * never activates the scanner by itself. The first pass remains faithful
     * (apart from preload suppression and debug-noise controls), because trimming
     * can change layout timing or late JavaScript and create false negatives. The
     * optimized pass exists solely to recover positive evidence on unusually heavy
     * pages. Negative findings from this pass are kept incomplete/review-only.
     *
     * @param string $provider local|google
     *
     * @return bool
     */
    private static function isOptimizedFallbackRequest($provider)
    {
        if ( ! self::isActiveRequest($provider) ) {
            return false;
        }

        if ( ! isset($_GET[self::OPTIMIZED_FALLBACK_QUERY_ARG]) ||
             ! is_string($_GET[self::OPTIMIZED_FALLBACK_QUERY_ARG]) ) {
            return false;
        }

        return sanitize_key(wp_unslash($_GET[self::OPTIMIZED_FALLBACK_QUERY_ARG])) === '1';
    }

    /**
     * Send no-cache headers for a short-lived scanner page.
     *
     * @param string $provider local|google
     *
     * @return void
     */
    public static function sendNoCacheHeaders($provider)
    {
        $definition = self::getProviderDefinition($provider);

        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
        header($definition['response_header'] . ': 1', true);

        if (self::isManualVerificationRequest($provider)) {
            header('X-WPACU-Font-Scan-Verification: 1', true);
        }
    }

    /**
     * Print the browser-side evidence collector as early as possible in HEAD.
     *
     * @param string $provider local|google
     *
     * @return void
     */
    public static function printFrontendCollector($provider)
    {
        $definition = self::getProviderDefinition($provider);

        if ( ! empty(self::$collectorPrinted[$provider]) ) {
            return;
        }

        $requestData = self::getActiveRequestData($provider);

        if ( ! $requestData ) {
            return;
        }

        self::$collectorPrinted[$provider] = true;

        $view = isset($_GET[$definition['view_query_arg']])
            ? sanitize_key(wp_unslash($_GET[$definition['view_query_arg']]))
            : 'desktop';

        $config = array(
            'provider'            => $definition['provider'],
            'token'               => $requestData['token'],
            'fontUrls'            => $requestData['font_urls'],
            'fontEntries'         => isset($requestData['font_entries']) && is_array($requestData['font_entries'])
                ? $requestData['font_entries']
                : array(),
            'parentOrigin'        => isset($requestData['parent_origin']) ? $requestData['parent_origin'] : '',
            'view'                => $view,
            'pageUrl'             => self::getCurrentUrlWithoutScanArgs($provider),
            'messageReady'        => $definition['message_ready'],
            'messageResult'       => $definition['message_result'],
            'messageError'        => $definition['message_error'],
            'ownPreloadAttribute' => $definition['own_preload_attribute'],
            'manualPreloadSuppressed' => true,
            'scanTrimmed'          => self::isOptimizedFallbackRequest($provider),
            'verificationQueryArg' => self::MANUAL_VERIFICATION_QUERY_ARG,
            'observationMin'       => self::DOM_OBSERVATION_MIN_MS,
            'loadWaitTimeout'      => self::LOAD_WAIT_TIMEOUT_MS,
            'targetQuietPeriod'    => self::TARGET_QUIET_PERIOD_MS,
            'negativeObservationMin' => self::NEGATIVE_OBSERVATION_MIN_MS,
            'fidelityNegativeObservationMin' => self::FIDELITY_NEGATIVE_OBSERVATION_MIN_MS,
            'negativePostLoadQuiet' => self::NEGATIVE_POST_LOAD_QUIET_MS,
            'negativePostLoadMin'   => self::NEGATIVE_POST_LOAD_MIN_MS,
            'targetObservationMax' => self::TARGET_OBSERVATION_MAX_MS,
            'targetObservationExtendedMax' => self::TARGET_OBSERVATION_EXTENDED_MAX_MS,
            'targetPollInterval'   => self::TARGET_POLL_INTERVAL_MS,
            'cssEvidenceCache'     => self::CSS_EVIDENCE_CACHE_MS,
            'renderedUsageCache'   => self::RENDERED_USAGE_CACHE_MS,
            'renderedUsageMaxElements' => self::RENDERED_USAGE_MAX_ELEMENTS,
            'fallbackTimeout'      => self::COLLECTOR_FALLBACK_TIMEOUT_MS
        );

        $configJson = wp_json_encode(
            $config,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if ( ! $configJson ) {
            return;
        }

        ?>
<script type="text/javascript" data-wpacu-skip="1" data-wpacu-font-preload-scan="1">
(function () {
    'use strict';

    var config = <?php echo $configJson; ?>;
    var sent = false;
	var readySent = false;
	var fallbackTriggered = false;
	var observationStarted = false;
	var loadGateStarted = false;
    var observedResourceEntries = [];
    var observedResourceEntryIndex = Object.create(null);
    var resourceObserverMode = 'unsupported';
    var resourceObserver = null;
	var evidenceMutationObserver = null;
	var collectorStartedAt = (window.performance && typeof window.performance.now === 'function')
		? window.performance.now()
		: 0;
	var domReadyAt = document.readyState === 'loading' ? null : collectorStartedAt;
	var windowLoadObserved = document.readyState === 'complete';
	var windowLoadAt = windowLoadObserved ? collectorStartedAt : null;
	var loadWaitTimedOut = false;
	var loadGateSettled = windowLoadObserved;
	var lastRelevantActivityAt = collectorStartedAt;
	var lastTargetFingerprint = '';
	var targetObservationTimedOut = false;
	var observationCompletionReason = '';
	var observationTimerId = null;
	var fallbackTimerId = null;
	var cssEvidenceRevision = 0;
	var cachedCssEvidenceRevision = -1;
	var cachedCssEvidenceAt = 0;
	var cachedCssEvidence = null;
	var renderedUsageRevision = 0;
	var cachedRenderedUsageRevision = -1;
	var cachedRenderedUsageCssRevision = -1;
	var cachedRenderedUsageAt = 0;
	var cachedRenderedUsageIndex = null;
	var trackedGoogleStylesheets = [];
	var configuredTargets = null;

    if (!config || !config.token || !Array.isArray(config.fontUrls) || !window.parent || window.parent === window) {
        return;
    }

    if (window.performance && typeof window.performance.setResourceTimingBufferSize === 'function') {
        window.performance.setResourceTimingBufferSize(4000);
    }

    if (typeof window.PerformanceObserver === 'function') {
        try {
            resourceObserver = new PerformanceObserver(function (entryList) {
                Array.prototype.forEach.call(entryList.getEntries(), function (entry) {
                    recordObservedResourceEntry(entry, 'observer');
                });
            });

            try {
                resourceObserver.observe({ type: 'resource', buffered: true });
                resourceObserverMode = 'buffered';
            } catch (bufferedObserverError) {
                resourceObserver.observe({ entryTypes: ['resource'] });
                resourceObserverMode = 'live';
            }
        } catch (observerError) {
            resourceObserver = null;
            resourceObserverMode = 'failed';
        }
    }

    function normaliseUrl(value, baseUrl) {
        if (!value) {
            return '';
        }

        try {
            var parsed = new URL(String(value).replace(/&amp;|&#038;/g, '&'), baseUrl || document.baseURI);

            if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
                return '';
            }

            parsed.hash = '';
            return parsed.href;
        } catch (error) {
            return '';
        }
    }

    function getPathKey(value) {
        if (!value) {
            return '';
        }

        try {
            var parsed = new URL(value, document.baseURI);
            return parsed.origin.toLowerCase() + parsed.pathname;
        } catch (error) {
            return '';
        }
    }

    function getHost(value) {
        try {
            return new URL(value, document.baseURI).hostname.toLowerCase();
        } catch (error) {
            return '';
        }
    }

    function uniqueStrings(values) {
        var found = Object.create(null);
        var output = [];

        values.forEach(function (value) {
            if (!value || found[value]) {
                return;
            }

            found[value] = true;
            output.push(value);
        });

        return output;
    }

    function looksLikeFontUrl(value) {
        if (!value) {
            return false;
        }

        try {
            return /\.(?:woff2?|ttf|otf|eot)$/i.test(new URL(value, document.baseURI).pathname);
        } catch (error) {
            return false;
        }
    }

    function isGoogleFontFileUrl(value) {
        var host = getHost(value);
        return host === 'fonts.gstatic.com' || host === 'themes.googleusercontent.com';
    }

    function isGoogleStylesheetUrl(value) {
        return getHost(value) === 'fonts.googleapis.com';
    }

	function getLinkRelTokens(link) {
		return String(link && link.getAttribute ? (link.getAttribute('rel') || '') : '')
			.toLowerCase()
			.split(/\s+/)
			.filter(Boolean);
	}

	function isGoogleStylesheetLinkCandidate(link, stylesheetUrl) {
		if (!link || !isGoogleStylesheetUrl(stylesheetUrl) || link.disabled) {
			return false;
		}

		var relTokens = getLinkRelTokens(link);

		if (relTokens.indexOf('stylesheet') !== -1) {
			return true;
		}

		// Support the common asynchronous pattern that starts as
		// rel="preload" as="style" and switches to rel="stylesheet" on load.
		return relTokens.indexOf('preload') !== -1 &&
			String(link.getAttribute('as') || '').toLowerCase() === 'style';
	}

	function inlineStyleContainsGoogleStylesheet(styleElement) {
		if (config.provider !== 'google' || !styleElement) {
			return false;
		}

		var cssText = String(styleElement.textContent || '').replace(/\/\*[\s\S]*?\*\//g, '');
		return /(?:https?:)?\/\/fonts\.googleapis\.com\//i.test(cssText);
	}

	function nowMs() {
		return (window.performance && typeof window.performance.now === 'function')
			? window.performance.now()
			: Date.now();
	}

	function makeResourceEntryKey(resourceUrl, initiatorType, startTime, duration) {
		return [
			resourceUrl || '',
			initiatorType || '',
			typeof startTime === 'number' ? String(startTime) : '',
			typeof duration === 'number' ? String(duration) : ''
		].join('\u0000');
	}

	function serialiseResourceEntry(entry, captureSource) {
		var resourceUrl = normaliseUrl(entry && entry.name, document.baseURI);

		if (!resourceUrl || !isRelevantResourceUrl(resourceUrl)) {
			return null;
		}

		return {
			name: resourceUrl,
			initiatorType: entry && entry.initiatorType ? String(entry.initiatorType) : '',
			startTime: entry && typeof entry.startTime === 'number' ? entry.startTime : null,
			duration: entry && typeof entry.duration === 'number' ? entry.duration : null,
			responseEnd: entry && typeof entry.responseEnd === 'number' ? entry.responseEnd : null,
			transferSize: entry && typeof entry.transferSize === 'number' ? entry.transferSize : null,
			encodedBodySize: entry && typeof entry.encodedBodySize === 'number' ? entry.encodedBodySize : null,
			decodedBodySize: entry && typeof entry.decodedBodySize === 'number' ? entry.decodedBodySize : null,
			nextHopProtocol: entry && entry.nextHopProtocol ? String(entry.nextHopProtocol) : '',
			deliveryType: entry && entry.deliveryType ? String(entry.deliveryType) : '',
			captureSources: captureSource ? [captureSource] : []
		};
	}

	function recordObservedResourceEntry(entry, captureSource) {
		var snapshot = serialiseResourceEntry(entry, captureSource);

		if (!snapshot) {
			return false;
		}

		var key = makeResourceEntryKey(
			snapshot.name,
			snapshot.initiatorType,
			snapshot.startTime,
			snapshot.duration
		);
		var existingIndex = observedResourceEntryIndex[key];

		if (typeof existingIndex === 'number' && observedResourceEntries[existingIndex]) {
			observedResourceEntries[existingIndex].captureSources = uniqueStrings(
				(observedResourceEntries[existingIndex].captureSources || []).concat(snapshot.captureSources || [])
			);
			return false;
		}

		if (observedResourceEntries.length >= 4000) {
			return false;
		}

		observedResourceEntryIndex[key] = observedResourceEntries.length;
		observedResourceEntries.push(snapshot);

		if (isTargetActivityResourceUrl(snapshot.name)) {
			noteRelevantActivity();
		}

		if (isGoogleStylesheetUrl(snapshot.name)) {
			cssEvidenceRevision++;
		}

		return true;
	}

	function drainResourceObserver() {
		if (!resourceObserver || typeof resourceObserver.takeRecords !== 'function') {
			return;
		}

		try {
			Array.prototype.forEach.call(resourceObserver.takeRecords() || [], function (entry) {
				recordObservedResourceEntry(entry, 'observer_take_records');
			});
		} catch (error) {
			// A final Performance API snapshot is still collected below.
		}
	}

	function capturePerformanceSnapshot() {
		drainResourceObserver();

		if (!window.performance || typeof window.performance.getEntriesByType !== 'function') {
			return;
		}

		try {
			Array.prototype.forEach.call(window.performance.getEntriesByType('resource') || [], function (entry) {
				recordObservedResourceEntry(entry, 'performance_snapshot');
			});
		} catch (error) {
			// Observer evidence already captured remains usable.
		}
	}

	function getConfiguredTargets() {
		if (configuredTargets) {
			return configuredTargets;
		}

		var configuredEntries = Array.isArray(config.fontEntries) ? config.fontEntries : [];

		configuredTargets = config.fontUrls.map(function (original, index) {
			var normalised = normaliseUrl(original, document.baseURI);
			var entry = configuredEntries[index] && typeof configuredEntries[index] === 'object'
				? configuredEntries[index]
				: null;

			return {
				original: original,
				normalised: normalised,
				pathKey: getPathKey(normalised),
				localFileStatus: entry && entry.localFileStatus ? String(entry.localFileStatus) : 'unknown'
			};
		});

		return configuredTargets;
	}

	function isRelevantResourceUrl(value) {
		var resourceUrl = normaliseUrl(value, document.baseURI);

		if (!resourceUrl) {
			return false;
		}

		if (config.provider === 'google') {
			if (isGoogleStylesheetUrl(resourceUrl) || isGoogleFontFileUrl(resourceUrl)) {
				return true;
			}

			return getConfiguredTargets().some(function (target) {
				return target.normalised === resourceUrl;
			});
		}

		var resourcePathKey = getPathKey(resourceUrl);

		return looksLikeFontUrl(resourceUrl) || getConfiguredTargets().some(function (target) {
			return target.normalised === resourceUrl ||
				(target.pathKey && target.pathKey === resourcePathKey);
		});
	}

	function isTargetActivityResourceUrl(value) {
		var resourceUrl = normaliseUrl(value, document.baseURI);

		if (!resourceUrl) {
			return false;
		}

		if (config.provider === 'google') {
			return isGoogleStylesheetUrl(resourceUrl) || isGoogleFontFileUrl(resourceUrl) ||
				getConfiguredTargets().some(function (target) {
					return target.normalised === resourceUrl;
				});
		}

		var resourcePathKey = getPathKey(resourceUrl);

		return getConfiguredTargets().some(function (target) {
			return target.normalised === resourceUrl ||
				(target.pathKey && target.pathKey === resourcePathKey);
		});
	}

	function noteRelevantActivity() {
		lastRelevantActivityAt = nowMs();
	}

	function invalidateCssEvidence(markRelevant) {
		cssEvidenceRevision++;

		if (markRelevant) {
			noteRelevantActivity();
		}
	}

	function markRenderedUsageDirty() {
		renderedUsageRevision++;
	}

	function updateTrackedGoogleStylesheet(tracked, status) {
		if (!tracked || tracked.status === status) {
			return;
		}

		tracked.status = status;
		invalidateCssEvidence(true);
	}

	function trackGoogleStylesheetLink(link) {
		if (config.provider !== 'google' || !link || !link.getAttribute) {
			return;
		}

		var stylesheetUrl = normaliseUrl(link.getAttribute('href') || link.href, document.baseURI);
		var tracked = link.__wpacuFontPreloadScanTracked || null;

		if (!isGoogleStylesheetLinkCandidate(link, stylesheetUrl)) {
			if (tracked && tracked.status !== 'removed') {
				tracked.url = '';
				tracked.status = 'removed';
				invalidateCssEvidence(true);
			}

			return;
		}

		if (!tracked) {
			tracked = {
				element: link,
				url: stylesheetUrl,
				status: link.sheet ? 'loaded' : 'pending'
			};

			link.__wpacuFontPreloadScanTracked = tracked;
			trackedGoogleStylesheets.push(tracked);

			link.addEventListener('load', function () {
				updateTrackedGoogleStylesheet(tracked, 'loaded');
			}, { once: true });

			link.addEventListener('error', function () {
				updateTrackedGoogleStylesheet(tracked, 'error');
			}, { once: true });

			invalidateCssEvidence(true);
			return;
		}

		if (tracked.url !== stylesheetUrl) {
			tracked.url = stylesheetUrl;
			tracked.status = link.sheet ? 'loaded' : 'pending';
			invalidateCssEvidence(true);
		} else if (tracked.status === 'pending' && link.sheet) {
			updateTrackedGoogleStylesheet(tracked, 'loaded');
		}
	}

	function scanNodeForRelevantStyles(node) {
		if (!node || node.nodeType !== 1) {
			return;
		}

		markRenderedUsageDirty();
		var tagName = String(node.tagName || '').toLowerCase();

		if (tagName === 'link') {
			trackGoogleStylesheetLink(node);
			invalidateCssEvidence(false);
		} else if (tagName === 'style') {
			invalidateCssEvidence(inlineStyleContainsGoogleStylesheet(node));
		}

		if (typeof node.querySelectorAll === 'function') {
			Array.prototype.forEach.call(node.querySelectorAll('link[href], style'), function (styleNode) {
				var styleTagName = String(styleNode.tagName || '').toLowerCase();

				if (styleTagName === 'link') {
					trackGoogleStylesheetLink(styleNode);
				}
			});

			if (node.querySelector('link[href], style')) {
				var containsRelevantInlineStyle = Array.prototype.some.call(
					node.querySelectorAll('style'),
					inlineStyleContainsGoogleStylesheet
				);

				invalidateCssEvidence(containsRelevantInlineStyle);
			}
		}
	}

	function initialiseEvidenceObservers() {
		Array.prototype.forEach.call(document.querySelectorAll('link[href]'), trackGoogleStylesheetLink);

		if (typeof window.MutationObserver !== 'function' || !document.documentElement) {
			return;
		}

		try {
			evidenceMutationObserver = new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					if (mutation.type === 'attributes') {
						markRenderedUsageDirty();

						if (mutation.target && String(mutation.target.tagName || '').toLowerCase() === 'link') {
							trackGoogleStylesheetLink(mutation.target);
							invalidateCssEvidence(false);
						}

						return;
					}

					if (mutation.type === 'characterData') {
						markRenderedUsageDirty();
						var parentStyle = mutation.target && mutation.target.parentNode;

						if (parentStyle && String(parentStyle.tagName || '').toLowerCase() === 'style') {
							invalidateCssEvidence(inlineStyleContainsGoogleStylesheet(parentStyle));
						}

						return;
					}

					markRenderedUsageDirty();
					Array.prototype.forEach.call(mutation.addedNodes || [], scanNodeForRelevantStyles);

					if (mutation.target && String(mutation.target.tagName || '').toLowerCase() === 'style') {
						invalidateCssEvidence(inlineStyleContainsGoogleStylesheet(mutation.target));
					}
				});
			});

			evidenceMutationObserver.observe(document.documentElement, {
				subtree: true,
				childList: true,
				characterData: true,
				attributes: true,
				attributeFilter: ['href', 'rel', 'as', 'media', 'disabled', 'class', 'style', 'hidden']
			});
		} catch (observerError) {
			evidenceMutationObserver = null;
		}
	}

	function getPendingGoogleStylesheetCount() {
		if (config.provider !== 'google') {
			return 0;
		}

		var pending = 0;

		trackedGoogleStylesheets.forEach(function (tracked) {
			if (!tracked || !tracked.element || !document.documentElement.contains(tracked.element)) {
				return;
			}

			if (tracked.status === 'pending' && tracked.element.sheet) {
				tracked.status = 'loaded';
			}

			if (tracked.status === 'pending') {
				pending++;
			}
		});

		return pending;
	}

	function getGlobalFontSetStatus() {
		if (!document.fonts || !document.fonts.status) {
			return 'unsupported';
		}

		return String(document.fonts.status);
	}

    function extractFontSourcesFromSrc(srcValue, baseUrl) {
        var sources = [];
        var sourceText = String(srcValue || '');
        var regexp = /\b(local|url)\(\s*(?:(["'])(.*?)\2|([^)]*))\s*\)/gi;
        var match;

        while ((match = regexp.exec(sourceText)) !== null) {
            var sourceType = String(match[1] || '').toLowerCase();
            var rawValue = typeof match[3] === 'string' && match[3] !== '' ? match[3] : (match[4] || '');
            var format = '';

            if (sourceType === 'url') {
                var remainder = sourceText.substring(regexp.lastIndex);
                var formatMatch = /^\s*format\(\s*(?:(["'])(.*?)\1|([^)]*))\s*\)/i.exec(remainder);

                if (formatMatch) {
                    format = stripOuterQuotes(
                        typeof formatMatch[2] === 'string' && formatMatch[2] !== ''
                            ? formatMatch[2]
                            : (formatMatch[3] || '')
                    );
                }

                var normalised = normaliseUrl(rawValue, baseUrl);

                if (!normalised) {
                    // Keep an unresolved data:/blob:/invalid candidate in the
                    // ordered source chain. Dropping it could make a later HTTP
                    // URL look deterministic even though the browser may select
                    // this earlier source first.
                    sources.push({
                        type: 'url',
                        url: '',
                        rawUrl: stripOuterQuotes(rawValue),
                        format: collapseDescriptor(format),
                        unresolved: true,
                        order: sources.length
                    });
                    continue;
                }

                sources.push({
                    type: 'url',
                    url: normalised,
                    format: collapseDescriptor(format),
                    order: sources.length
                });
                continue;
            }

            sources.push({
                type: 'local',
                name: stripOuterQuotes(rawValue),
                order: sources.length
            });
        }

        return sources;
    }

    function extractUrlsFromSources(sources) {
        return uniqueStrings((sources || []).filter(function (source) {
            return source && source.type === 'url' && source.url;
        }).map(function (source) {
            return source.url;
        }));
    }

    function getFontSourceFormat(source) {
        var format = collapseDescriptor(source && source.format);

        if (format) {
            format = format.replace(/["']/g, '').split(',')[0].trim();
        }

        if (!format && source && source.url) {
            try {
                var pathname = new URL(source.url, document.baseURI).pathname.toLowerCase();
                var extensionMatch = /\.([a-z0-9-]+)$/.exec(pathname);
                format = extensionMatch ? extensionMatch[1] : '';
            } catch (error) {
                format = '';
            }
        }

        var aliases = {
            'ttf': 'truetype',
            'otf': 'opentype',
            'eot': 'embedded-opentype',
            'woff2-variations': 'woff2-variations',
            'woff-variations': 'woff-variations',
            'truetype-variations': 'truetype-variations',
            'opentype-variations': 'opentype-variations'
        };

        return aliases[format] || format;
    }

    function getLikelyFontSourceSupport(source) {
        var format = getFontSourceFormat(source);

        if ([
            'woff2', 'woff', 'truetype', 'opentype',
            'woff2-variations', 'woff-variations',
            'truetype-variations', 'opentype-variations'
        ].indexOf(format) !== -1) {
            return true;
        }

        if (['embedded-opentype', 'svg'].indexOf(format) !== -1) {
            return false;
        }

        return null;
    }

    function getDeterministicSelectedRemoteSource(sources) {
        var result = {
            url: '',
            format: '',
            reason: 'no_supported_remote_source'
        };

        for (var index = 0; index < (sources || []).length; index++) {
            var source = sources[index];

            // A local() candidate is tested before subsequent URLs. FontFaceSet
            // cannot reveal whether the local face or a remote fallback won.
            if (source && source.type === 'local') {
                result.reason = 'local_source_precedes_remote';
                return result;
            }

            if (!source || source.type !== 'url') {
                continue;
            }

            var support = getLikelyFontSourceSupport(source);

            if (!source.url) {
                if (support !== false) {
                    result.reason = 'unresolved_source_precedes_remote';
                    return result;
                }

                continue;
            }

            if (support === true) {
                result.url = source.url;
                result.format = getFontSourceFormat(source);
                result.reason = 'first_supported_remote_source';
                return result;
            }

            if (support === null) {
                // An unknown candidate before the configured file makes browser
                // source selection ambiguous; do not guess past it.
                result.reason = 'unknown_source_format_precedes_remote';
                return result;
            }
        }

        return result;
    }

    function stripOuterQuotes(value) {
        value = String(value || '').trim();

        if (value.length > 1 && ((value.charAt(0) === '"' && value.charAt(value.length - 1) === '"') ||
            (value.charAt(0) === "'" && value.charAt(value.length - 1) === "'"))) {
            return value.substring(1, value.length - 1);
        }

        return value;
    }

	function collapseDescriptor(value) {
		return String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
	}

	function normaliseFamily(value) {
		return collapseDescriptor(stripOuterQuotes(value));
	}

	function normaliseWeight(value) {
		value = collapseDescriptor(value || 'normal');

		if (value === 'normal' || value === 'regular') {
			return '400';
		}

		if (value === 'bold') {
			return '700';
		}

		return value;
	}

	function normaliseStretch(value) {
		value = collapseDescriptor(value || 'normal');
		return value === 'normal' ? '100%' : value;
	}

	function normaliseUnicodeRange(value) {
		value = collapseDescriptor(value).replace(/\s*,\s*/g, ',').replace(/\s+/g, '');

		if (value === 'u+0-10ffff' || value === 'u+0000-10ffff') {
			return '';
		}

		return value;
	}

	function normaliseVariationSettings(value) {
		return collapseDescriptor(value).replace(/\s*,\s*/g, ',');
	}

	function faceDescriptorsMatch(face, fontFace) {
		if (!face || !fontFace) {
			return false;
		}

		if (!normaliseFamily(face.family) || normaliseFamily(face.family) !== normaliseFamily(fontFace.family)) {
			return false;
		}

		if (collapseDescriptor(face.style || 'normal') !== collapseDescriptor(fontFace.style || 'normal')) {
			return false;
		}

		if (normaliseWeight(face.weight) !== normaliseWeight(fontFace.weight)) {
			return false;
		}

		if (normaliseStretch(face.stretch) !== normaliseStretch(fontFace.stretch)) {
			return false;
		}

		var faceRange = normaliseUnicodeRange(face.unicodeRange);
		var loadedRange = normaliseUnicodeRange(fontFace.unicodeRange);

		if (faceRange !== loadedRange) {
			return false;
		}

		var faceVariation = normaliseVariationSettings(face.variationSettings);
		var loadedVariation = normaliseVariationSettings(fontFace.variationSettings);

		if (faceVariation && faceVariation !== loadedVariation) {
			return false;
		}

		var faceFeatures = normaliseVariationSettings(face.featureSettings);
		var loadedFeatures = normaliseVariationSettings(fontFace.featureSettings);

		return !faceFeatures || faceFeatures === loadedFeatures;
	}

	function descriptorsMatch(face, loadedFace) {
		return !!loadedFace && collapseDescriptor(loadedFace.status) === 'loaded' &&
			faceDescriptorsMatch(face, loadedFace);
	}

	function getFaceDescriptorKey(face) {
		if (!face || !normaliseFamily(face.family)) {
			return '';
		}

		return [
			normaliseFamily(face.family),
			collapseDescriptor(face.style || 'normal'),
			normaliseWeight(face.weight),
			normaliseStretch(face.stretch),
			normaliseUnicodeRange(face.unicodeRange),
			normaliseVariationSettings(face.variationSettings),
			normaliseVariationSettings(face.featureSettings)
		].join('\u0000');
	}

	function buildDescriptorSourceIndex(faces) {
		var index = Object.create(null);

		(faces || []).forEach(function (face) {
			var key = getFaceDescriptorKey(face);

			if (!key) {
				return;
			}

			if (!index[key]) {
				index[key] = {
					sourceUrls: [],
					selectedSourceUrls: [],
					hasLocalSource: false,
					hasUnknownSourceSelection: false
				};
			}

			index[key].sourceUrls = uniqueStrings(index[key].sourceUrls.concat(face.sourceUrls || []));
			index[key].selectedSourceUrls = uniqueStrings(
				index[key].selectedSourceUrls.concat(face.selectedSourceUrl ? [face.selectedSourceUrl] : [])
			);
			index[key].hasLocalSource = index[key].hasLocalSource || !!face.hasLocalSource;
			index[key].hasUnknownSourceSelection = index[key].hasUnknownSourceSelection ||
				(!face.selectedSourceUrl && [
					'unknown_source_format_precedes_remote',
					'unresolved_source_precedes_remote'
				].indexOf(face.sourceSelectionReason) !== -1);
		});

		return index;
	}

	function getFontFaceSourceAttribution(face, descriptorSourceIndex) {
		if (!face || face.hasLocalSource || !Array.isArray(face.sourceUrls) || !face.sourceUrls.length) {
			return null;
		}

		var descriptorSources = descriptorSourceIndex[getFaceDescriptorKey(face)] || null;

		if (!descriptorSources || descriptorSources.hasLocalSource) {
			return null;
		}

		// The strongest fallback remains a descriptor signature that maps to one
		// remote URL in all readable CSS.
		if (!descriptorSources.hasUnknownSourceSelection &&
			face.sourceUrls.length === 1 && descriptorSources.sourceUrls.length === 1 &&
			descriptorSources.sourceUrls[0] === face.sourceUrls[0]) {
			return {
				url: face.sourceUrls[0],
				mode: 'single_remote_source'
			};
		}

		// Legacy icon fonts often declare an ordered fallback chain such as WOFF,
		// TTF and SVG. FontFaceSet does not expose the winning URL, but in the local
		// provider we can conservatively attribute a loaded face when every readable
		// rule for this descriptor selects the same first browser-supported remote
		// source and no local()/unknown candidate can win before it.
		if (config.provider === 'local' && face.selectedSourceUrl &&
			!descriptorSources.hasUnknownSourceSelection &&
			descriptorSources.selectedSourceUrls.length === 1 &&
			descriptorSources.selectedSourceUrls[0] === face.selectedSourceUrl) {
			return {
				url: face.selectedSourceUrl,
				mode: 'deterministic_supported_source'
			};
		}

		return null;
	}

	function getFontFaceMatchesForFace(face, fontFaces, descriptorSourceIndex) {
		if (!getFontFaceSourceAttribution(face, descriptorSourceIndex)) {
			return [];
		}

		return (fontFaces || []).filter(function (fontFace) {
			return faceDescriptorsMatch(face, fontFace);
		});
	}

	function getLoadedMatchesForFace(face, fontFaces, descriptorSourceIndex) {
		return getFontFaceMatchesForFace(face, fontFaces, descriptorSourceIndex).filter(function (fontFace) {
			return collapseDescriptor(fontFace.status) === 'loaded';
		});
	}

    function collectFontFaceRules() {
        var faces = [];
        var inaccessibleStyleSheets = 0;
        var visitedSheets = [];

        function hasVisited(styleSheet) {
            return visitedSheets.indexOf(styleSheet) !== -1;
        }

        function addFontFace(rule, fallbackStyleSheet) {
            var baseUrl = (rule.parentStyleSheet && rule.parentStyleSheet.href) ||
                (fallbackStyleSheet && fallbackStyleSheet.href) || document.baseURI;
			var srcValue = rule.style.getPropertyValue('src') || '';
				var sources = extractFontSourcesFromSrc(srcValue, baseUrl);
				var selectedRemoteSource = getDeterministicSelectedRemoteSource(sources);

            faces.push({
                family: stripOuterQuotes(rule.style.getPropertyValue('font-family')),
                style: rule.style.getPropertyValue('font-style') || 'normal',
                weight: rule.style.getPropertyValue('font-weight') || 'normal',
                stretch: rule.style.getPropertyValue('font-stretch') || 'normal',
                unicodeRange: rule.style.getPropertyValue('unicode-range') || '',
                variationSettings: rule.style.getPropertyValue('font-variation-settings') || '',
                featureSettings: rule.style.getPropertyValue('font-feature-settings') || '',
					sources: sources,
					sourceUrls: extractUrlsFromSources(sources),
					hasLocalSource: sources.some(function (source) { return source && source.type === 'local'; }),
					selectedSourceUrl: selectedRemoteSource.url,
					selectedSourceFormat: selectedRemoteSource.format,
					sourceSelectionReason: selectedRemoteSource.reason
            });
        }

        function walkRules(rules, fallbackStyleSheet) {
            if (!rules) {
                return;
            }

            Array.prototype.forEach.call(rules, function (rule) {
                if (!rule) {
                    return;
                }

                if (rule.type === 5 && rule.style) {
                    addFontFace(rule, fallbackStyleSheet);
                    return;
                }

                try {
                    if (rule.styleSheet) {
                        walkStyleSheet(rule.styleSheet);
                    }

                    if (rule.cssRules) {
                        walkRules(rule.cssRules, fallbackStyleSheet);
                    }
                } catch (error) {
                    // Resource Timing still provides the requested font URLs.
                }
            });
        }

        function walkStyleSheet(styleSheet) {
            if (!styleSheet || hasVisited(styleSheet)) {
                return;
            }

            visitedSheets.push(styleSheet);

            try {
                walkRules(styleSheet.cssRules || styleSheet.rules, styleSheet);
            } catch (error) {
                inaccessibleStyleSheets++;
            }
        }

        Array.prototype.forEach.call(document.styleSheets || [], walkStyleSheet);

        return {
            faces: faces,
            inaccessibleStyleSheets: inaccessibleStyleSheets
        };
    }

	function getFontFaceRules(forceRefresh) {
		var currentTime = nowMs();
		var cacheLifetime = Math.max(0, Number(config.cssEvidenceCache || 650));

		if (!forceRefresh && cachedCssEvidence && cachedCssEvidenceRevision === cssEvidenceRevision &&
			(currentTime - cachedCssEvidenceAt) < cacheLifetime) {
			return cachedCssEvidence;
		}

		cachedCssEvidence = collectFontFaceRules();
		cachedCssEvidenceRevision = cssEvidenceRevision;
		cachedCssEvidenceAt = currentTime;

		return cachedCssEvidence;
	}

    function collectLoadedFontFaces() {
        var loadedFaces = [];

        if (!document.fonts || typeof document.fonts.forEach !== 'function') {
            return loadedFaces;
        }

        document.fonts.forEach(function (fontFace) {
            if (loadedFaces.length >= 250) {
                return;
            }

            loadedFaces.push({
                family: stripOuterQuotes(fontFace.family),
                style: fontFace.style || 'normal',
                weight: fontFace.weight || 'normal',
                stretch: fontFace.stretch || 'normal',
                unicodeRange: fontFace.unicodeRange || '',
                variationSettings: fontFace.variationSettings || '',
                featureSettings: fontFace.featureSettings || '',
                status: fontFace.status || ''
            });
        });

        return loadedFaces;
    }


	function parseComputedFontFamilies(value) {
		var families = [];
		var current = '';
		var quote = '';
		var text = String(value || '');

		for (var i = 0; i < text.length; i++) {
			var character = text.charAt(i);

			if (quote) {
				current += character;
				if (character === quote && text.charAt(i - 1) !== '\\') {
					quote = '';
				}
				continue;
			}

			if (character === '"' || character === "'") {
				quote = character;
				current += character;
				continue;
			}

			if (character === ',') {
				if (normaliseFamily(current)) {
					families.push(normaliseFamily(current));
				}
				current = '';
				continue;
			}

			current += character;
		}

		if (normaliseFamily(current)) {
			families.push(normaliseFamily(current));
		}

		return uniqueStrings(families);
	}

	function computedWeightMatchesFace(faceWeight, computedWeight) {
		var faceValue = normaliseWeight(faceWeight || 'normal');
		var computedValue = parseInt(normaliseWeight(computedWeight || 'normal'), 10);
		var numericValues = String(faceValue).match(/\d+(?:\.\d+)?/g) || [];

		if (!isFinite(computedValue)) {
			return faceValue === normaliseWeight(computedWeight || 'normal');
		}

		if (numericValues.length >= 2) {
			return computedValue >= Number(numericValues[0]) && computedValue <= Number(numericValues[1]);
		}

		if (numericValues.length === 1) {
			return computedValue === Number(numericValues[0]);
		}

		return faceValue === normaliseWeight(computedWeight || 'normal');
	}

	function computedStyleMatchesFace(computedStyle, face) {
		if (!computedStyle || !face || !normaliseFamily(face.family)) {
			return false;
		}

		if (parseComputedFontFamilies(computedStyle.fontFamily).indexOf(normaliseFamily(face.family)) === -1) {
			return false;
		}

		var faceStyle = collapseDescriptor(face.style || 'normal').split(' ')[0];
		var computedFontStyle = collapseDescriptor(computedStyle.fontStyle || 'normal').split(' ')[0];

		if (faceStyle !== computedFontStyle && !(faceStyle === 'oblique' && computedFontStyle === 'italic')) {
			return false;
		}

		if (!computedWeightMatchesFace(face.weight, computedStyle.fontWeight)) {
			return false;
		}

		var faceStretch = normaliseStretch(face.stretch || 'normal');
		var computedStretch = normaliseStretch(computedStyle.fontStretch || 'normal');

		return faceStretch === computedStretch;
	}

	function elementHasOwnRenderableText(element) {
		if (!element || !element.childNodes) {
			return false;
		}

		var tagName = String(element.tagName || '').toLowerCase();

		if (['input', 'textarea', 'select'].indexOf(tagName) !== -1) {
			return !!String(element.value || element.getAttribute('placeholder') || '').trim();
		}

		for (var i = 0; i < element.childNodes.length; i++) {
			var childNode = element.childNodes[i];
			if (childNode && childNode.nodeType === 3 && String(childNode.nodeValue || '').trim()) {
				return true;
			}
		}

		return false;
	}

	function pseudoContentIsRenderable(value) {
		var content = String(value || '').trim();

		return content !== '' && content !== 'none' && content !== 'normal' &&
			content !== '""' && content !== "''";
	}

	function computedStyleCanRenderFont(computedStyle) {
		if (!computedStyle) {
			return false;
		}

		if (collapseDescriptor(computedStyle.display) === 'none' ||
			collapseDescriptor(computedStyle.visibility) === 'hidden' ||
			collapseDescriptor(computedStyle.contentVisibility) === 'hidden') {
			return false;
		}

		var fontSize = parseFloat(computedStyle.fontSize || '0');
		return !isFinite(fontSize) || fontSize > 0;
	}

	function describeRenderedUsageNode(element, pseudo) {
		var label = String(element && element.tagName ? element.tagName : 'element').toLowerCase();

		if (element && element.id) {
			label += '#' + String(element.id).replace(/[^A-Za-z0-9_-]/g, '').substring(0, 40);
		} else if (element && element.className && typeof element.className === 'string') {
			var classes = element.className.trim().split(/\s+/).filter(Boolean).slice(0, 2);
			if (classes.length) {
				label += '.' + classes.join('.');
			}
		}

		return label + (pseudo || '');
	}

	function collectRenderedUsageIndex(cssFaces, descriptorSourceIndex, loadedFontFaces, forceRefresh) {
		if (config.provider !== 'local' || typeof window.getComputedStyle !== 'function') {
			return {};
		}

		var currentTime = nowMs();
		var cacheLifetime = Math.max(250, Number(config.renderedUsageCache || 5000));

		// Computed-style walking is intentionally more expensive than the other
		// evidence sources. Keep a short time-based snapshot even when a slider,
		// builder or tracker mutates classes repeatedly. Negative checks already
		// observe for at least 12 seconds, so a late font application is still
		// picked up on the next bounded refresh without rescanning the DOM every
		// 250 milliseconds.
		if (!forceRefresh && cachedRenderedUsageIndex &&
			(currentTime - cachedRenderedUsageAt) < cacheLifetime) {
			return cachedRenderedUsageIndex;
		}

		var configuredByUrl = Object.create(null);
		getConfiguredTargets().forEach(function (target) {
			if (target && target.normalised) {
				configuredByUrl[target.normalised] = target;
			}
		});

		var facesByFamily = Object.create(null);
		var usageIndex = Object.create(null);
		var unresolvedUrls = Object.create(null);
		var unresolvedCount = 0;

		(cssFaces || []).forEach(function (face) {
			var attribution = getFontFaceSourceAttribution(face, descriptorSourceIndex);
			var target = attribution && configuredByUrl[attribution.url]
				? configuredByUrl[attribution.url]
				: null;
			var familyKey = normaliseFamily(face && face.family);

			if (!target || !familyKey) {
				return;
			}

			if (attribution.mode === 'deterministic_supported_source' && target.localFileStatus !== 'exists') {
				return;
			}

			if (!usageIndex[attribution.url]) {
				usageIndex[attribution.url] = {
					used: false,
					matches: [],
					families: []
				};
				unresolvedUrls[attribution.url] = true;
				unresolvedCount++;
			}

			usageIndex[attribution.url].families.push(face.family);

			if (!facesByFamily[familyKey]) {
				facesByFamily[familyKey] = [];
			}

			var renderedFaceLoaded = getLoadedMatchesForFace(
			face,
			loadedFontFaces || [],
			descriptorSourceIndex
		).length > 0;

		// A computed font-family declaration alone does not prove that this
		// particular face rendered the glyphs. Require the deterministically
		// attributed FontFace to be loaded as well. This keeps the cache-reuse
		// fallback positive without turning a CSS font stack into false usage.
		if (!renderedFaceLoaded) {
			return;
		}

		facesByFamily[familyKey].push({
				url: attribution.url,
				face: face,
				mode: attribution.mode
			});
		});

		if (!unresolvedCount || !document.documentElement) {
			cachedRenderedUsageIndex = usageIndex;
			cachedRenderedUsageRevision = renderedUsageRevision;
			cachedRenderedUsageCssRevision = cssEvidenceRevision;
			cachedRenderedUsageAt = currentTime;
			return usageIndex;
		}

		var maxElements = Math.max(100, Number(config.renderedUsageMaxElements || 2500));
		var inspected = 0;
		var walker = document.createTreeWalker
			? document.createTreeWalker(document.documentElement, 1, null, false)
			: null;
		var fallbackElements = walker ? null : document.querySelectorAll('*');
		var fallbackIndex = 0;
		var element = walker ? walker.currentNode : (fallbackElements[0] || null);

		function markUsage(computedStyle, currentElement, pseudo, requiresText) {
			if (!computedStyleCanRenderFont(computedStyle)) {
				return;
			}

			if (requiresText && !elementHasOwnRenderableText(currentElement)) {
				return;
			}

			if (!requiresText && !pseudoContentIsRenderable(computedStyle.content)) {
				return;
			}

			parseComputedFontFamilies(computedStyle.fontFamily).forEach(function (familyKey) {
				(facesByFamily[familyKey] || []).forEach(function (candidate) {
					if (!unresolvedUrls[candidate.url] || !computedStyleMatchesFace(computedStyle, candidate.face)) {
						return;
					}

					var usage = usageIndex[candidate.url];
					usage.used = true;
					if (usage.matches.length < 8) {
						usage.matches.push({
							node: describeRenderedUsageNode(currentElement, pseudo),
							pseudo: pseudo || '',
							family: candidate.face.family,
							style: candidate.face.style || 'normal',
							weight: candidate.face.weight || 'normal',
							attributionMode: candidate.mode
						});
					}

					delete unresolvedUrls[candidate.url];
					unresolvedCount--;
				});
			});
		}

		while (element && inspected < maxElements && unresolvedCount > 0) {
			inspected++;

			try {
				markUsage(window.getComputedStyle(element), element, '', true);
				if (unresolvedCount > 0) {
					markUsage(window.getComputedStyle(element, '::before'), element, '::before', false);
				}
				if (unresolvedCount > 0) {
					markUsage(window.getComputedStyle(element, '::after'), element, '::after', false);
				}
			} catch (computedStyleError) {
				// Continue with the remaining DOM. Resource Timing and FontFaceSet
				// evidence are still available if a browser rejects a pseudo query.
			}

			if (walker) {
				element = walker.nextNode();
			} else {
				fallbackIndex++;
				element = fallbackElements[fallbackIndex] || null;
			}
		}

		Object.keys(usageIndex).forEach(function (url) {
			usageIndex[url].families = uniqueStrings(usageIndex[url].families);
			usageIndex[url].inspectedElements = inspected;
		});

		cachedRenderedUsageIndex = usageIndex;
		cachedRenderedUsageRevision = renderedUsageRevision;
		cachedRenderedUsageCssRevision = cssEvidenceRevision;
		cachedRenderedUsageAt = currentTime;

		return usageIndex;
	}

    function collectGoogleStylesheetUrls(resourceEntries) {
        var urls = [];

        Array.prototype.forEach.call(document.querySelectorAll('link[href]'), function (link) {
            var linkUrl = normaliseUrl(link.href, document.baseURI);
			if (isGoogleStylesheetLinkCandidate(link, linkUrl)) {
                urls.push(linkUrl);
            }
        });

        Array.prototype.forEach.call(document.styleSheets || [], function (styleSheet) {
            var styleSheetUrl = normaliseUrl(styleSheet && styleSheet.href, document.baseURI);
            if (isGoogleStylesheetUrl(styleSheetUrl)) {
                urls.push(styleSheetUrl);
            }
        });

        resourceEntries.forEach(function (entry) {
            var resourceUrl = normaliseUrl(entry && entry.name, document.baseURI);
            if (isGoogleStylesheetUrl(resourceUrl)) {
                urls.push(resourceUrl);
            }
        });

        Array.prototype.forEach.call(document.querySelectorAll('style'), function (styleElement) {
			var cssText = String(styleElement.textContent || '').replace(/\/\*[\s\S]*?\*\//g, '');
            var regexp = /(?:https?:)?\/\/fonts\.googleapis\.com\/[^\s)'"<>]+/gi;
            var match;

            while ((match = regexp.exec(cssText)) !== null) {
                var inlineUrl = normaliseUrl(match[0], document.baseURI);
                if (inlineUrl) {
                    urls.push(inlineUrl);
                }
            }
        });

        return uniqueStrings(urls).slice(0, 30);
    }

    function collectResults(observationContext, forceCssRefresh) {
        var context = observationContext || {};
        var saved = getConfiguredTargets();
        var savedUrls = saved.map(function (item) { return item.normalised; });
        var savedPathKeys = saved.map(function (item) { return item.pathKey; });
        var resources = [];
        var resourceEntries = [];
        var seenResourceEntries = Object.create(null);

        // Drain queued observer records before reading the final Performance API
        // snapshot. Relevant entries are serialised immediately and survive a
        // later performance.clearResourceTimings() call from application code.
        capturePerformanceSnapshot();

        // Ask for every configured name explicitly as a second independent
        // snapshot. Some browsers are more reliable here after memory-cache
        // reuse or when another script has manipulated the resource buffer.
        if (window.performance && typeof window.performance.getEntriesByName === 'function') {
            saved.forEach(function (savedFont) {
                if (!savedFont.normalised) {
                    return;
                }

                try {
                    Array.prototype.forEach.call(
                        window.performance.getEntriesByName(savedFont.normalised, 'resource') || [],
                        function (entry) {
                            recordObservedResourceEntry(entry, 'performance_name_snapshot');
                        }
                    );
                } catch (error) {
                    // The observer and getEntriesByType snapshots remain available.
                }
            });
        }

        resourceEntries = observedResourceEntries.slice(0);

        resourceEntries.forEach(function (entry) {
            var resourceUrl = normaliseUrl(entry && entry.name, document.baseURI);
            var resourcePathKey = getPathKey(resourceUrl);
            var relevant = config.provider === 'google'
                ? (isGoogleFontFileUrl(resourceUrl) || isGoogleStylesheetUrl(resourceUrl) || savedUrls.indexOf(resourceUrl) !== -1)
                : (looksLikeFontUrl(resourceUrl) || savedUrls.indexOf(resourceUrl) !== -1 || savedPathKeys.indexOf(resourcePathKey) !== -1);

            if (!relevant || !resourceUrl) {
                return;
            }

            var initiatorType = entry.initiatorType || '';
            var startTime = typeof entry.startTime === 'number' ? Math.round(entry.startTime) : null;
            var duration = typeof entry.duration === 'number' ? Math.round(entry.duration) : null;
            var resourceKey = [resourceUrl, initiatorType, startTime, duration].join('\u0000');

            if (seenResourceEntries[resourceKey]) {
                return;
            }

            seenResourceEntries[resourceKey] = true;
            resources.push({
                url: resourceUrl,
                pathKey: resourcePathKey,
                initiatorType: initiatorType,
                transferSize: typeof entry.transferSize === 'number' ? entry.transferSize : null,
                encodedBodySize: typeof entry.encodedBodySize === 'number' ? entry.encodedBodySize : null,
                decodedBodySize: typeof entry.decodedBodySize === 'number' ? entry.decodedBodySize : null,
                duration: duration,
                startTime: startTime,
                responseEnd: typeof entry.responseEnd === 'number' ? Math.round(entry.responseEnd) : null,
                nextHopProtocol: entry.nextHopProtocol || '',
                deliveryType: entry.deliveryType || '',
                captureSources: uniqueStrings(entry.captureSources || [])
            });
        });

        function getExpectedFontMimeType(fontUrl) {
            var path = '';

            try {
                path = new URL(fontUrl, document.baseURI).pathname.toLowerCase();
            } catch (error) {
                return '';
            }

            if (/\.woff2$/.test(path)) {
                return 'font/woff2';
            }

            if (/\.woff$/.test(path)) {
                return 'font/woff';
            }

            if (/\.ttf$/.test(path)) {
                return 'font/ttf';
            }

            if (/\.otf$/.test(path)) {
                return 'font/otf';
            }

            if (/\.eot$/.test(path)) {
                return 'application/vnd.ms-fontobject';
            }

            return '';
        }

        var fontPreloadLinks = Array.prototype.slice.call(
            document.querySelectorAll('link[rel~="preload"][as="font"]')
        );
        var ownPreloadUrls = fontPreloadLinks.map(function (link) {
            if (!config.ownPreloadAttribute || !link.hasAttribute(config.ownPreloadAttribute)) {
                return '';
            }

            return normaliseUrl(link.href, document.baseURI);
        }).filter(Boolean);
        var preloads = fontPreloadLinks.map(function (link) {
            if (config.ownPreloadAttribute && link.hasAttribute(config.ownPreloadAttribute)) {
                return '';
            }

            var preloadUrl = normaliseUrl(link.href, document.baseURI);
            var crossOrigin = String(link.getAttribute('crossorigin') || '').toLowerCase();
            var mediaMatches = !link.media || !window.matchMedia || window.matchMedia(link.media).matches;
            var declaredType = String(link.getAttribute('type') || '').toLowerCase().split(';')[0].trim();
            var expectedType = getExpectedFontMimeType(preloadUrl);
            var typeMatches = !declaredType || !expectedType || declaredType === expectedType;
            var compatible = preloadUrl && !link.disabled && mediaMatches && typeMatches &&
                crossOrigin !== 'use-credentials' && link.hasAttribute('crossorigin');

            return compatible ? preloadUrl : '';
        }).filter(Boolean);

        var cssData = getFontFaceRules(!!forceCssRefresh);
        var descriptorSourceIndex = buildDescriptorSourceIndex(cssData.faces);
        var loadedFontFaces = collectLoadedFontFaces();
        var renderedUsageIndex = collectRenderedUsageIndex(
            cssData.faces,
            descriptorSourceIndex,
            loadedFontFaces,
            !!forceCssRefresh
        );
        var pendingGoogleStylesheets = getPendingGoogleStylesheetCount();

        var fontResults = saved.map(function (savedFont) {
            var exactResources = resources.filter(function (resource) {
                return resource.url === savedFont.normalised;
            });
            var samePathResources = resources.filter(function (resource) {
                return savedFont.pathKey && resource.pathKey === savedFont.pathKey && resource.url !== savedFont.normalised;
            });
            var exactFaces = cssData.faces.filter(function (face) {
                return face.sourceUrls.indexOf(savedFont.normalised) !== -1;
            });
            var samePathFaces = cssData.faces.filter(function (face) {
                return face.sourceUrls.some(function (sourceUrl) {
                    return savedFont.pathKey && getPathKey(sourceUrl) === savedFont.pathKey && sourceUrl !== savedFont.normalised;
                });
            });
            var matchingFontFaces = [];
            var loadedFaceMatches = [];
            var fontFaceAttributionModes = [];
            var attributedSourceUrls = [];
            var fontFaceAttributionRejections = [];

            exactFaces.forEach(function (face) {
                var attribution = getFontFaceSourceAttribution(face, descriptorSourceIndex);

                if (!attribution || attribution.url !== savedFont.normalised) {
                    return;
                }

                if (attribution.mode === 'deterministic_supported_source') {
                    // A multi-source FontFaceSet fallback is accepted only when
                    // WordPress mapped the configured first source to an existing
                    // local file. Otherwise the browser could have skipped a broken
                    // first source and loaded a later TTF/OTF fallback instead.
                    if (savedFont.localFileStatus !== 'exists') {
                        fontFaceAttributionRejections.push('target_file_not_confirmed');
                        return;
                    }

                    var alternateSourceRequested = (face.sourceUrls || []).some(function (sourceUrl) {
                        if (!sourceUrl || sourceUrl === attribution.url) {
                            return false;
                        }

                        return resources.some(function (resource) {
                            return resource.url === sourceUrl;
                        });
                    });

                    if (alternateSourceRequested) {
                        fontFaceAttributionRejections.push('alternate_source_requested');
                        return;
                    }
                }

                fontFaceAttributionModes.push(attribution.mode);
                attributedSourceUrls.push(attribution.url);

                matchingFontFaces = matchingFontFaces.concat(
                    getFontFaceMatchesForFace(face, loadedFontFaces, descriptorSourceIndex)
                );
                loadedFaceMatches = loadedFaceMatches.concat(
                    getLoadedMatchesForFace(face, loadedFontFaces, descriptorSourceIndex)
                );
            });

            var matchingFaceStatuses = uniqueStrings(matchingFontFaces.map(function (fontFace) {
                return collapseDescriptor(fontFace.status);
            }));
            var exactRequestObserved = exactResources.length > 0;
            var ownPreloadPresent = ownPreloadUrls.indexOf(savedFont.normalised) !== -1;
            var requested = exactRequestObserved && !ownPreloadPresent;
            var renderedUsage = renderedUsageIndex[savedFont.normalised] || null;
            var appliedViaComputedStyle = !!(renderedUsage && renderedUsage.used) && !ownPreloadPresent;
            // A Google Fonts stylesheet can map the same family/variant to a different
            // generated file between browsers or requests. FontFaceSet is therefore
            // semantic evidence only for Google, not proof that this exact URL loaded.
            var loadedViaFontFace = config.provider === 'local' && loadedFaceMatches.length > 0 && !ownPreloadPresent;
            var targetLoading = matchingFaceStatuses.indexOf('loading') !== -1;
			var targetErrored = matchingFaceStatuses.indexOf('error') !== -1;
            var evidenceComplete = true;
            var evidenceState = 'stable_not_requested';
            var incompleteReason = '';

            if (ownPreloadPresent) {
                evidenceComplete = false;
                evidenceState = 'own_preload_present';
                incompleteReason = 'own_preload_present';
            } else if (requested) {
                evidenceState = 'exact_request';
            } else if (appliedViaComputedStyle) {
                evidenceState = 'rendered_font_usage';
            } else if (loadedViaFontFace) {
                evidenceState = 'font_face_loaded';
            } else if (config.scanTrimmed) {
                // A trimmed fallback is useful for positive recovery only. The
                // modified page may omit or retime a late font trigger, so absence
                // here must never become a confident negative.
                evidenceComplete = false;
                evidenceState = 'optimized_fallback_negative';
                incompleteReason = 'optimized_fallback_negative';
            } else if (targetLoading) {
                evidenceComplete = false;
                evidenceState = 'target_font_loading';
                incompleteReason = 'target_font_loading';
			} else if (targetErrored) {
				evidenceComplete = false;
				evidenceState = 'target_font_error';
				incompleteReason = 'target_font_error';
            } else if (config.provider === 'google' && pendingGoogleStylesheets > 0) {
                evidenceComplete = false;
                evidenceState = 'google_stylesheet_pending';
                incompleteReason = 'google_stylesheet_pending';
            } else if (context.forceIncompleteNegatives) {
                evidenceComplete = false;
                evidenceState = context.incompleteReason || 'target_observation_incomplete';
                incompleteReason = evidenceState;
            }

            return {
                original: savedFont.original,
                normalised: savedFont.normalised,
                requested: requested,
                exactRequestObserved: exactRequestObserved,
                requestStartTimes: exactResources.map(function (resource) { return resource.startTime; }).filter(function (value) { return value !== null; }),
                initiatorTypes: uniqueStrings(exactResources.map(function (resource) { return resource.initiatorType; })),
                exactResourceEntries: exactResources.slice(0, 8),
                preloadedElsewhere: preloads.indexOf(savedFont.normalised) !== -1,
                ownPreloadPresent: ownPreloadPresent,
                cssReferenced: exactFaces.length > 0,
                loadedViaFontFace: loadedViaFontFace,
                appliedViaComputedStyle: appliedViaComputedStyle,
                computedStyleMatches: renderedUsage && Array.isArray(renderedUsage.matches)
                    ? renderedUsage.matches.slice(0, 8)
                    : [],
                loadedFaceMatches: loadedFaceMatches.slice(0, 8),
                fontFaceAttributionModes: uniqueStrings(fontFaceAttributionModes),
                attributedSourceUrls: uniqueStrings(attributedSourceUrls),
                matchingFaceStatuses: matchingFaceStatuses,
                targetLoading: targetLoading,
				targetErrored: targetErrored,
                evidenceComplete: evidenceComplete,
                evidenceState: evidenceState,
                incompleteReason: incompleteReason,
                stableNotRequested: evidenceComplete && !requested && !appliedViaComputedStyle && !loadedViaFontFace,
                diagnostic: {
                    exactRequestObserved: exactRequestObserved,
                    exactRequestAccepted: requested,
                    cssReferenceFound: exactFaces.length > 0,
                    appliedViaComputedStyle: appliedViaComputedStyle,
                    computedStyleMatchCount: renderedUsage && Array.isArray(renderedUsage.matches)
                        ? renderedUsage.matches.length
                        : 0,
                    loadedViaFontFace: loadedViaFontFace,
                    fontFaceAttributionModes: uniqueStrings(fontFaceAttributionModes),
                    attributedSourceUrls: uniqueStrings(attributedSourceUrls),
                    fontFaceAttributionRejections: uniqueStrings(fontFaceAttributionRejections),
                    matchingFaceStatuses: matchingFaceStatuses.slice(0, 8),
                    ownPreloadPresent: ownPreloadPresent,
                    preloadedElsewhere: preloads.indexOf(savedFont.normalised) !== -1,
                    pendingGoogleStylesheets: pendingGoogleStylesheets,
                    resourceObserverMode: resourceObserverMode,
                    exactResourceCaptureSources: uniqueStrings([].concat.apply([], exactResources.map(function (resource) {
                        return resource.captureSources || [];
                    })))
                },
                cssFaces: exactFaces.slice(0, 8),
                samePathRequestedUrls: uniqueStrings(samePathResources.map(function (resource) { return resource.url; })).slice(0, 8),
                samePathResourceEntries: samePathResources.slice(0, 8),
                samePathCssFaces: samePathFaces.slice(0, 8)
            };
        });

        return {
            type: config.messageResult,
            token: config.token,
            provider: config.provider,
            view: config.view,
            pageUrl: config.pageUrl || window.location.href,
            finalPageUrl: window.location.href,
            fonts: fontResults,
            loadedFontFaces: loadedFontFaces,
            inaccessibleStyleSheets: cssData.inaccessibleStyleSheets,
            googleStylesheets: config.provider === 'google' ? collectGoogleStylesheetUrls(resourceEntries) : [],
            googleFontResources: config.provider === 'google' ? uniqueStrings(resources.filter(function (resource) {
                return isGoogleFontFileUrl(resource.url);
            }).map(function (resource) { return resource.url; })).slice(0, 150) : [],
            pendingGoogleStylesheets: pendingGoogleStylesheets,
            manualPreloadSuppressed: !!config.manualPreloadSuppressed && ownPreloadUrls.length === 0,
            ownPreloadTagsPresent: ownPreloadUrls.length,
            scanTrimmed: !!config.scanTrimmed,
            resourceObserverMode: resourceObserverMode,
            globalFontSetStatus: getGlobalFontSetStatus(),
            resourceCount: resources.length
        };
    }

	function getTargetOrigin() {
		try {
			var parsedParentOrigin = new URL(config.parentOrigin);

			if (parsedParentOrigin.protocol === 'http:' || parsedParentOrigin.protocol === 'https:') {
				return parsedParentOrigin.origin;
			}
		} catch (error) {
			return '';
		}

		return '';
	}

	function postToParent(payload) {
		var targetOrigin = getTargetOrigin();

		// Never derive the destination from document.referrer. A leaked scan URL
		// must not be able to make the collector disclose results to another site.
		if (!targetOrigin) {
			return false;
		}

		window.parent.postMessage(payload, targetOrigin);
		return true;
	}

	function postReady() {
		if (readySent || !config.messageReady) {
			return;
		}

		readySent = postToParent({
			type: config.messageReady,
			token: config.token,
			provider: config.provider,
			view: config.view,
			pageUrl: config.pageUrl || window.location.href,
			readyState: document.readyState,
			collectorStartedAt: collectorStartedAt
		});
	}

	function postResult(payload) {
		if (sent) {
			return;
		}

		var incompleteTargetCount = payload && Array.isArray(payload.fonts)
			? payload.fonts.filter(function (fontResult) {
				return fontResult && fontResult.evidenceComplete === false;
			}).length
			: 0;
		var globalFontSetStatus = payload && payload.globalFontSetStatus
			? payload.globalFontSetStatus
			: getGlobalFontSetStatus();

		payload.collector = {
			readyState: document.readyState,
			collectorStartedAt: collectorStartedAt,
			domReadyAt: domReadyAt,
			windowLoadObserved: windowLoadObserved,
			windowLoadAt: windowLoadAt,
			loadWaitTimedOut: loadWaitTimedOut,
			globalFontSetStatus: globalFontSetStatus,
			globalFontsSettled: globalFontSetStatus !== 'loading',
			fallbackTriggered: fallbackTriggered,
			targetObservationTimedOut: targetObservationTimedOut,
			completionReason: observationCompletionReason,
			targetIncompleteCount: incompleteTargetCount,
			pendingGoogleStylesheets: payload && payload.pendingGoogleStylesheets
				? Number(payload.pendingGoogleStylesheets)
				: 0,
			lastRelevantActivityAt: lastRelevantActivityAt,
			collectedAt: nowMs()
		};

		if (!postToParent(payload)) {
			return;
		}

		sent = true;
		window.clearTimeout(observationTimerId);
		window.clearTimeout(fallbackTimerId);

		if (resourceObserver && typeof resourceObserver.disconnect === 'function') {
			resourceObserver.disconnect();
		}

		if (evidenceMutationObserver && typeof evidenceMutationObserver.disconnect === 'function') {
			evidenceMutationObserver.disconnect();
		}
	}

    function postCollectorError(error) {
        postResult({
            type: config.messageError,
            token: config.token,
            provider: config.provider,
            view: config.view,
            pageUrl: config.pageUrl || window.location.href,
            message: error && error.message ? error.message : 'The browser collector failed.'
        });
    }

    function collectAndPost(observationContext) {
        if (sent) {
            return;
        }

        try {
            postResult(collectResults(observationContext || {}, true));
        } catch (error) {
            postCollectorError(error);
        }
    }

	function waitForWindowLoadOrTimeout() {
		if (loadGateStarted) {
			return;
		}

		loadGateStarted = true;

		if (document.readyState === 'complete') {
			windowLoadObserved = true;
			windowLoadAt = nowMs();
			loadGateSettled = true;
			scheduleTargetObservation(0);
			return;
		}

		var gateSettled = false;
		var timeoutId = window.setTimeout(function () {
			if (gateSettled) {
				return;
			}

			gateSettled = true;
			loadWaitTimedOut = true;
			loadGateSettled = true;
			// Keep the load listener active. A slow page may still reach load later,
			// and that event gives negative evidence a much stronger completion gate.
			scheduleTargetObservation(0);
		}, Number(config.loadWaitTimeout || 3500));

		function onLoad() {
			windowLoadObserved = true;
			windowLoadAt = nowMs();

			if (!gateSettled) {
				gateSettled = true;
				loadGateSettled = true;
				window.clearTimeout(timeoutId);
			}

			scheduleTargetObservation(0);
		}

		window.addEventListener('load', onLoad, { once: true });
	}

	function markDomReady() {
		if (domReadyAt === null) {
			domReadyAt = nowMs();
			noteRelevantActivity();
		}

		waitForWindowLoadOrTimeout();
		scheduleTargetObservation(0);
	}

	function getTargetFingerprint(payload) {
		var parts = [];

		(payload.fonts || []).forEach(function (fontResult) {
			parts.push([
				fontResult.requested ? '1' : '0',
				fontResult.appliedViaComputedStyle ? '1' : '0',
				fontResult.loadedViaFontFace ? '1' : '0',
				fontResult.cssReferenced ? '1' : '0',
				fontResult.preloadedElsewhere ? '1' : '0',
                fontResult.ownPreloadPresent ? '1' : '0',
				(fontResult.matchingFaceStatuses || []).slice(0).sort().join(','),
				(fontResult.samePathRequestedUrls || []).slice(0).sort().join(',')
			].join(':'));
		});

		parts.push('pending-google:' + String(payload.pendingGoogleStylesheets || 0));
		parts.push('google-css:' + (payload.googleStylesheets || []).slice(0).sort().join(','));

		return parts.join('|');
	}

	function scheduleTargetObservation(delay) {
		if (sent || !observationStarted) {
			return;
		}

		window.clearTimeout(observationTimerId);
		observationTimerId = window.setTimeout(evaluateTargetObservation, Math.max(0, Number(delay || 0)));
	}

	function evaluateTargetObservation() {
		if (sent) {
			return;
		}

		var payload;

		try {
			payload = collectResults({}, false);
		} catch (error) {
			postCollectorError(error);
			return;
		}

		var currentTime = nowMs();
		var fingerprint = getTargetFingerprint(payload);

		if (fingerprint !== lastTargetFingerprint) {
			lastTargetFingerprint = fingerprint;
			noteRelevantActivity();
			currentTime = nowMs();
		}

		var observationMin = Math.max(0, Number(config.observationMin || 1800));
		var quietPeriod = Math.max(250, Number(config.targetQuietPeriod || 1000));
		var configuredNegativeMin = config.scanTrimmed
			? Number(config.negativeObservationMin || 12000)
			: Number(config.fidelityNegativeObservationMin || 32000);
		var negativeObservationMin = Math.max(observationMin, configuredNegativeMin);
		var negativePostLoadQuiet = Math.max(250, Number(config.negativePostLoadQuiet || 1250));
		var negativePostLoadMin = Math.max(negativePostLoadQuiet, Number(config.negativePostLoadMin || 6000));
		var observationMax = Math.max(observationMin, Number(config.targetObservationMax || 8000));
		var observationExtendedMax = Math.max(observationMax, Number(config.targetObservationExtendedMax || 35000));
		var observationBase = domReadyAt === null ? collectorStartedAt : domReadyAt;
		var elapsedSinceObservationBase = currentTime - observationBase;
		var minimumObserved = elapsedSinceObservationBase >= observationMin;
		var quietReached = (currentTime - lastRelevantActivityAt) >= quietPeriod;
		var allTargetsPositive = payload.fonts.length > 0 && payload.fonts.every(function (fontResult) {
			return !!(fontResult.requested || fontResult.appliedViaComputedStyle || fontResult.loadedViaFontFace);
		});
		var hasUnconfirmedTargets = payload.fonts.some(function (fontResult) {
			return !(fontResult.requested || fontResult.appliedViaComputedStyle || fontResult.loadedViaFontFace);
		});
		var targetStillLoading = payload.fonts.some(function (fontResult) {
			return !!fontResult.targetLoading;
		});
		var googleStylesheetPending = Number(payload.pendingGoogleStylesheets || 0) > 0;
		var postLoadQuietReached = windowLoadObserved && windowLoadAt !== null &&
			(currentTime - windowLoadAt) >= negativePostLoadQuiet;
		var postLoadMinimumReached = windowLoadObserved && windowLoadAt !== null &&
			(currentTime - windowLoadAt) >= negativePostLoadMin;
		var slowNegativeWindowReached = elapsedSinceObservationBase >= negativeObservationMin;
		var negativeObservationReady = (postLoadQuietReached && postLoadMinimumReached) || slowNegativeWindowReached;

		// Positive evidence is terminal. The browser can finish immediately once
		// every configured target has an exact request, a deterministic rendered
		// usage match, or a uniquely attributable loaded FontFace.
		if (allTargetsPositive && minimumObserved && quietReached) {
			observationCompletionReason = 'all_targets_confirmed';
			collectAndPost({});
			return;
		}

		// Negative evidence is intentionally slower. A short load-gate timeout is
		// not enough to say that a font was unused: late navigation, icon, builder,
		// or consent JavaScript can introduce it several seconds later. Accept the
		// negative only after window.load plus a quiet period, or after the longer
		// fallback observation window when load never settles.
		if (domReadyAt !== null && loadGateSettled && minimumObserved && negativeObservationReady &&
			quietReached && !targetStillLoading && !googleStylesheetPending) {
			observationCompletionReason = postLoadQuietReached
				? 'target_evidence_after_load'
				: 'target_evidence_negative_window';
			collectAndPost({});
			return;
		}

		if (domReadyAt !== null) {
			var effectiveObservationLimit = hasUnconfirmedTargets
				? Math.max(observationMax, negativeObservationMin)
				: observationMax;

			if ((currentTime - domReadyAt) >= effectiveObservationLimit) {
				var elapsedSinceDomReady = currentTime - domReadyAt;
				var unresolvedTargetInProgress = targetStillLoading || googleStylesheetPending || !quietReached;

				// Slow templates retain the existing 35-second safety window only while
				// target-specific evidence is active or while the conservative negative
				// observation gate has not yet been reached. Unrelated page resources do
				// not keep the frame open after the negative gate is satisfied.
				if ((unresolvedTargetInProgress || !negativeObservationReady) &&
					elapsedSinceDomReady < observationExtendedMax) {
					scheduleTargetObservation(Number(config.targetPollInterval || 250));
					return;
				}

				targetObservationTimedOut = unresolvedTargetInProgress;
				observationCompletionReason = elapsedSinceDomReady >= observationExtendedMax
					? 'target_observation_extended_limit'
					: 'target_observation_limit';

				var incompleteReason = '';

				if (targetStillLoading) {
					incompleteReason = 'target_font_loading';
				} else if (googleStylesheetPending) {
					incompleteReason = 'google_stylesheet_pending';
				} else if (!quietReached) {
					incompleteReason = 'target_activity_not_settled';
				}

				collectAndPost(incompleteReason ? {
					forceIncompleteNegatives: true,
					incompleteReason: incompleteReason
				} : {});
				return;
			}
		}

		scheduleTargetObservation(Number(config.targetPollInterval || 250));
	}

	function startTargetObservation() {
		if (observationStarted || sent) {
			return;
		}

		observationStarted = true;
		initialiseEvidenceObservers();

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', markDomReady, { once: true });
		} else {
			markDomReady();
		}

		// Start immediately so exact target requests can finish the check even when
		// an unrelated blocking script delays DOMContentLoaded.
		scheduleTargetObservation(0);
	}

	postReady();
	startTargetObservation();

	// Preserve a final safety margin before the parent evidence timer. Positive
	// targets remain complete; only unresolved negative targets are marked for
	// review and retried.
	fallbackTimerId = window.setTimeout(function () {
		if (!sent) {
			fallbackTriggered = true;
			observationCompletionReason = 'collector_fallback';
			collectAndPost({
				forceIncompleteNegatives: true,
				incompleteReason: domReadyAt === null ? 'dom_not_ready' : 'collector_fallback'
			});
		}
	}, Number(config.fallbackTimeout || 16500));

}());
</script>
        <?php
    }

    /**
     * Resolve and validate the short-lived scan request data.
     *
     * This is the runtime security boundary. The public query argument is only
     * a lookup key; no preload suppression, collector output, HTML trimming, or
     * no-cache behaviour is allowed until the token matches the provider and
     * transient payload created by ajaxPrepareScan(). Results are memoised per
     * provider because several front-end classes ask this question in one load.
     *
     * FontPreloadScannerEarly performs the same minimal validation during active
     * plugin loading so it can suppress Query Monitor before this full class is
     * available. Keep provider names, query args, and transient prefixes in sync.
     *
     * @param string $provider local|google
     *
     * @return array|false
     */
    private static function getActiveRequestData($provider)
    {
        $definition = self::getProviderDefinition($provider);

        if (isset(self::$requestDataResolved[$provider])) {
            return isset(self::$requestData[$provider]) ? self::$requestData[$provider] : false;
        }

        self::$requestDataResolved[$provider] = true;

        if ( ! isset($_GET[$definition['query_arg']]) || ! is_string($_GET[$definition['query_arg']]) ) {
            self::$requestData[$provider] = false;
            return false;
        }

        $token = sanitize_text_field(wp_unslash($_GET[$definition['query_arg']]));

        if ( ! preg_match('/^[A-Za-z0-9]{32}$/D', $token) ) {
            self::$requestData[$provider] = false;
            return false;
        }

        $requestData = get_transient(self::getTransientName($provider, $token));

        if ( ! is_array($requestData) || empty($requestData['token']) ||
             empty($requestData['provider']) || $requestData['provider'] !== $provider ||
             ! hash_equals((string) $requestData['token'], (string) $token) ||
             empty($requestData['font_urls']) || ! is_array($requestData['font_urls']) ) {
            self::$requestData[$provider] = false;
            return false;
        }

        if ( ! empty($requestData['expires_at']) && time() > (int) $requestData['expires_at'] ) {
            delete_transient(self::getTransientName($provider, $token));
            self::$requestData[$provider] = false;
            return false;
        }

        self::$requestData[$provider] = $requestData;

        return self::$requestData[$provider];
    }

    /**
     * @param string $provider
     * @param string $token
     *
     * @return string
     */
    private static function getTransientName($provider, $token)
    {
        $definition = self::getProviderDefinition($provider);
        return $definition['transient_prefix'] . md5((string) $token);
    }

    /**
     * @return array
     */
    private static function getDefaultViews()
    {
        return array(
            array(
                'id'     => 'desktop',
                'label'  => __('Desktop viewport', 'wp-asset-clean-up'),
                'width'  => 1366,
                'height' => 820
            ),
            array(
                'id'     => 'mobile',
                'label'  => __('Mobile viewport', 'wp-asset-clean-up'),
                'width'  => 390,
                'height' => 844
            )
        );
    }

    /**
     * @param string $provider
     *
     * @return array
     */
    private static function getAdminStrings($provider)
    {
        $strings = array(
            'noUrls'                    => __('Add at least one font URL before running the check.', 'wp-asset-clean-up'),
            'requestFailed'             => __('The check could not be prepared. Refresh the page and try again.', 'wp-asset-clean-up'),
            'preparing'                 => __('Preparing representative pages…', 'wp-asset-clean-up'),
            'auditing'                  => __('Auditing font preloads…', 'wp-asset-clean-up'),
            'resolvingGoogleStylesheets'=> __('Resolving the Google Fonts stylesheets returned to this browser…', 'wp-asset-clean-up'),
            'failedStylesheetDetails'   => __('View failed stylesheet details', 'wp-asset-clean-up'),
            'retryGoogleResolution'     => __('Retry Google stylesheet resolution', 'wp-asset-clean-up'),
            'retryingGoogleResolution'  => __('Retrying Google stylesheet resolution…', 'wp-asset-clean-up'),
            'googleResolutionRetrySucceeded' => __('Previously unresolved Google Fonts stylesheet requests were resolved. Results were recalculated without repeating the browser checks.', 'wp-asset-clean-up'),
            'googleResolutionRetryStillFailed' => __('Google stylesheet resolution was retried. {count} stylesheet(s) still could not be resolved. Entries with complete evidence from other stylesheets are evaluated normally; only dependent or unresolved entries remain protected as Review.', 'wp-asset-clean-up'),
            'googleResolutionRetryUnrelatedStillFailed' => __('Google stylesheet resolution was retried. {count} stylesheet(s) still could not be resolved, but none is needed for the mapped evidence of the configured font files below. Their recommendations were recalculated independently.', 'wp-asset-clean-up'),
            'googleResolutionRetrySucceededIgnoredPermanent' => __('The retryable Google stylesheet requests were resolved. Remaining unrelated permanent stylesheet errors: {count}. They are ignored and do not affect the font recommendations below.', 'wp-asset-clean-up'),
            'googleResolutionPermanentFailure' => __('The permanent stylesheet errors listed above will not change on retry. Correct or remove the invalid Google Fonts stylesheet URL on the site, then run the audit again.', 'wp-asset-clean-up'),
            'googleResolverRequestLabel'=> __('Google Fonts resolver request', 'wp-asset-clean-up'),
            'resolverErrorCode'         => __('Error code: {value}', 'wp-asset-clean-up'),
            'resolverHttpStatus'        => __('HTTP status: {value}', 'wp-asset-clean-up'),
            'resolverAttempts'          => __('Request attempts: {value}', 'wp-asset-clean-up'),
            'resolverRedirects'         => __('Redirects followed: {value}', 'wp-asset-clean-up'),
            'resolverTimeout'           => __('Timeout per attempt: {value}s', 'wp-asset-clean-up'),
            'resolverFinalUrlLabel'     => __('Final URL', 'wp-asset-clean-up'),
            'resolverBrowserUserAgent'  => __('Browser User-Agent used for the Google CSS request', 'wp-asset-clean-up'),
            'tooManyUrls'               => __('Check up to {max} font URLs at a time.', 'wp-asset-clean-up'),
            'listChanged'               => __('The font URL list changed after the last check. Run the check again for current results.', 'wp-asset-clean-up'),
            'checking'                  => __('Checking {current} of {total}: {page} — {view}', 'wp-asset-clean-up'),
            'retryChecking'             => __('Retrying {current} of {total}: {page} — {view}', 'wp-asset-clean-up'),
            'complete'                  => __('Page checks complete.', 'wp-asset-clean-up'),
            'completeWithOneWarning'    => __('Check completed with 1 warning. {success} of {total} checks succeeded.', 'wp-asset-clean-up'),
            'completeWithWarnings'      => __('Check completed with {failed} warnings. {success} of {total} checks succeeded.', 'wp-asset-clean-up'),
            'completeWithOneRetry'      => __('Check complete. One of {total} checks succeeded after an automatic retry.', 'wp-asset-clean-up'),
            'completeWithRetries'       => __('Check complete. {recovered} of {total} checks succeeded after an automatic retry.', 'wp-asset-clean-up'),
            'checksSummaryDefault'      => __('Page and viewport checks', 'wp-asset-clean-up'),
            'checksSummaryClean'        => __('{success} of {total} page checks completed', 'wp-asset-clean-up'),
            'checksSummaryRecovered'    => __('{success} of {total} page checks completed · {recovered} recovered by retry', 'wp-asset-clean-up'),
            'checksSummaryWarningOne'   => __('{success} of {total} page checks completed · 1 needs review', 'wp-asset-clean-up'),
            'checksSummaryWarnings'     => __('{success} of {total} page checks completed · {warnings} need review', 'wp-asset-clean-up'),
            'fontUrlsObserved'          => __('{observed} of {total} configured font URLs observed.', 'wp-asset-clean-up'),
            'fontUrlNotObserved'        => __('Exact configured font URL not observed in this page check.', 'wp-asset-clean-up'),
            'fontUrlsNotObserved'       => __('None of the {total} configured font URLs were observed in this page check.', 'wp-asset-clean-up'),
            'googleStylesheetWarningOne'=> __('One Google Fonts stylesheet could not be resolved; only entries whose evidence depends on it remain protected as Review.', 'wp-asset-clean-up'),
            'googleStylesheetWarnings'  => __('{count} Google Fonts stylesheets could not be resolved; only entries whose evidence depends on them remain protected as Review.', 'wp-asset-clean-up'),
            'cancelled'                 => __('The check was cancelled.', 'wp-asset-clean-up'),
            'safeStatus'                => __('Safe to remove', 'wp-asset-clean-up'),
            'keepStatus'                => __('Likely site-wide candidate', 'wp-asset-clean-up'),
            'broadStatus'               => __('Broad usage — Review', 'wp-asset-clean-up'),
            'broadBrowserSpecificStatus'=> __('Broad usage — browser-specific', 'wp-asset-clean-up'),
            'selectiveStatus'           => __('Used selectively', 'wp-asset-clean-up'),
            'reviewStatus'              => __('Review', 'wp-asset-clean-up'),
            'incompleteReviewStatus'    => __('Review — incomplete scan', 'wp-asset-clean-up'),
            'browserSpecificReviewStatus' => __('Review — browser-specific file', 'wp-asset-clean-up'),
            'unknownStatus'             => __('Could not verify', 'wp-asset-clean-up'),
            'compactCoverage'           => __('{usedPages}/{totalPages} pages · {usedChecks}/{totalChecks} checks', 'wp-asset-clean-up'),
            'coverageUnavailable'       => __('No usable browser evidence', 'wp-asset-clean-up'),
            'compactKeepReason'         => __('Confirmed broadly enough to protect this entry from browser-based cleanup.', 'wp-asset-clean-up'),
            'compactBroadReason'        => __('The font is used broadly, but the remaining exceptions still deserve review.', 'wp-asset-clean-up'),
            'compactBroadBrowserSpecificReason' => __('The font is used broadly in this browser, but Google maps the same variation to a different generated file in at least one representative browser. Keep the entry protected and review whether a static site-wide preload is appropriate.', 'wp-asset-clean-up'),
            'compactSelectiveReason'    => __('Usage is limited in the sample, but WPACU is not offering removal from this result.', 'wp-asset-clean-up'),
            'compactSelectiveBrowserSpecificReason' => __('Usage is limited in the sample, and the exact generated Google file also varies by browser. Review the site-wide preload rather than treating the resolver warning as something a retry can repair.', 'wp-asset-clean-up'),
            'compactSelectiveEligibleReason' => __('A strict one-page, low-coverage local-font case was found. Removal is available only as an explicit manual choice.', 'wp-asset-clean-up'),
            'compactSafeReason'         => __('A deterministic field or duplicate-preload issue makes this entry eligible for cleanup.', 'wp-asset-clean-up'),
            'compactIncompleteReason'   => __('At least one page still lacks complete evidence, so this URL is protected from removal.', 'wp-asset-clean-up'),
            'compactReviewReason'       => __('The audit found useful evidence but not enough for a safe cleanup recommendation.', 'wp-asset-clean-up'),
            'compactUnknownReason'      => __('The browser audit did not return enough usable evidence to classify this URL.', 'wp-asset-clean-up'),
            'duplicateEntryReason'      => __('This is a duplicate of an earlier URL in the same field.', 'wp-asset-clean-up'),
            'invalidReason'             => __('This is not a valid HTTP(S) URL.', 'wp-asset-clean-up'),
            'wrongGoogleHostReason'     => __('This URL is not hosted by the Google Fonts file service. Review it before removing it from this field.', 'wp-asset-clean-up'),
            'requestedAllReason'        => __('The exact configured URL (or a deterministic rendered/loaded font match) was confirmed without WPACU’s manual preload on every checked page and viewport ({count} of {total}). This exceeds the audit rule for a likely site-wide candidate: every checked page and at least {threshold}% of page/viewport checks. Usage alone does not prove that the font is critical during the initial render or that preloading improves performance.', 'wp-asset-clean-up'),
            'requestedLikelyReasonOneMissing' => __('The exact configured URL (or a deterministic rendered/loaded font match) was confirmed without WPACU’s manual preload on all {usedPages} checked pages and on {usedChecks} of {totalChecks} page/viewport checks ({percent}%). Natural usage was not confirmed on one remaining check, but this meets the audit rule for a likely site-wide candidate: every checked page and at least {threshold}% of checks. Usage alone does not prove that the font is critical during the initial render or that preloading improves performance.', 'wp-asset-clean-up'),
            'requestedLikelyReasonManyMissing' => __('The exact configured URL (or a deterministic rendered/loaded font match) was confirmed without WPACU’s manual preload on all {usedPages} checked pages and on {usedChecks} of {totalChecks} page/viewport checks ({percent}%). Natural usage was not confirmed on the remaining {missingChecks} checks, but this meets the audit rule for a likely site-wide candidate: every checked page and at least {threshold}% of checks. Usage alone does not prove that the font is critical during the initial render or that preloading improves performance.', 'wp-asset-clean-up'),
            'requestedBroadReasonOneMissing' => __('The font is used broadly: the exact configured URL (or a uniquely attributable loaded FontFace) was confirmed without WPACU’s manual preload on {usedPages} of {totalPages} checked pages and on {usedChecks} of {totalChecks} page/viewport checks ({percent}%). Natural usage was not confirmed on one remaining check. It does not meet the likely site-wide rule of every checked page and at least {threshold}% of checks, so review the exception before deciding.', 'wp-asset-clean-up'),
            'requestedBroadReasonManyMissing' => __('The font is used broadly: the exact configured URL (or a uniquely attributable loaded FontFace) was confirmed without WPACU’s manual preload on {usedPages} of {totalPages} checked pages and on {usedChecks} of {totalChecks} page/viewport checks ({percent}%). Natural usage was not confirmed on the remaining {missingChecks} checks. It does not meet the likely site-wide rule of every checked page and at least {threshold}% of checks, so review the exceptions before deciding.', 'wp-asset-clean-up'),
            'requestedSelectiveReason'  => __('The font is still used, but the exact configured URL (or a uniquely attributable loaded FontFace) was confirmed without WPACU’s manual preload on {usedPages} of {totalPages} checked pages and {usedChecks} of {totalChecks} page/viewport checks. This limited or template-specific coverage does not make it a clear site-wide preload candidate. On the remaining successful checks, natural use of this exact preload target was not confirmed.', 'wp-asset-clean-up'),
            'computedStyleEvidenceOne' => __('On one check, WPACU found the configured local font loaded and applied to rendered text or generated icon content through a deterministic @font-face mapping. This protects against false negatives when the browser reuses a cached font without exposing a fresh Resource Timing entry.', 'wp-asset-clean-up'),
            'computedStyleEvidenceMany'=> __('On {count} checks, WPACU found the configured local font loaded and applied to rendered text or generated icon content through deterministic @font-face mappings. This protects against false negatives when the browser reuses cached fonts without exposing fresh Resource Timing entries.', 'wp-asset-clean-up'),
            'fontFaceEvidenceOne'       => __('On one check, usage was confirmed through the browser FontFaceSet after WPACU mapped the loaded face to the configured local URL. This fallback is used when an exact Resource Timing entry is unavailable, for example after cache reuse.', 'wp-asset-clean-up'),
            'fontFaceEvidenceMany'      => __('On {count} checks, usage was confirmed through the browser FontFaceSet after WPACU mapped the loaded faces to the configured local URL. This fallback is used when exact Resource Timing entries are unavailable, for example after cache reuse.', 'wp-asset-clean-up'),
            'googleSemanticEvidenceOne'=> __('On one check, a loaded FontFace matched the descriptors from the relevant Google stylesheet. Because FontFaceSet does not expose the source URL, this is supporting review evidence rather than confirmation of the exact generated file.', 'wp-asset-clean-up'),
            'googleSemanticEvidenceMany'=> __('On {count} checks, loaded FontFace objects matched descriptors from the relevant Google stylesheet. Because FontFaceSet does not expose source URLs, these are supporting review signals rather than confirmation of the exact generated file.', 'wp-asset-clean-up'),
            'poorCandidateSuffix'       => __('This low coverage makes it a poor site-wide preload candidate.', 'wp-asset-clean-up'),
            'poorCandidateManualSuffix' => __('Every other successful check produced stable negative evidence. WPACU therefore allows manual removal, but does not preselect it.', 'wp-asset-clean-up'),
            'poorCandidateReviewSuffix' => __('Every other successful check produced stable negative evidence, so this looks like a poor site-wide preload candidate. Coverage findings remain advisory, however, and WPACU does not make this URL selectable for removal.', 'wp-asset-clean-up'),
            'selectiveReviewSuffix'     => __('This result is protected from removal. Removing a preload stops only WPACU’s site-wide preload and never removes the font or its @font-face rule.', 'wp-asset-clean-up'),
            'deterministicRemovalNote'  => __('Eligible for cleanup and preselected because the finding is deterministic.', 'wp-asset-clean-up'),
            'manualRemovalNote'         => __('Eligible only as a manual decision; WPACU did not preselect it.', 'wp-asset-clean-up'),
            'protectedRemovalNote'      => __('Protected from removal by this audit.', 'wp-asset-clean-up'),
            'selectForRemoval'          => __('Select for removal — {status}: {url}', 'wp-asset-clean-up'),
            'fontFaceSelectionReviewSuffix' => __('Because part of the positive evidence came from a conservative FontFaceSet fallback rather than an exact Resource Timing entry, this low-coverage result was not preselected.', 'wp-asset-clean-up'),
            'duplicateAllReason'        => __('Another source preloaded this exact URL on every successfully checked page and viewport.', 'wp-asset-clean-up'),
            'duplicateSomeReason'       => __('Another compatible source preloaded this exact URL on some checked pages. A request forced by that preload is not treated by itself as natural font usage. Review page-specific behaviour before removing the WPACU entry.', 'wp-asset-clean-up'),
            'missingReason'             => __('The local file could not be found and the exact URL was not requested on any checked page.', 'wp-asset-clean-up'),
            'replacedReason'            => __('The exact URL was not requested. The same font-file path was requested with a different version query parameter.', 'wp-asset-clean-up'),
            'queryVariantReason'        => __('A different URL with the same font-file path was detected, but its query parameters may identify another font variation. It is also a different browser cache key, so the configured preload is not automatically reusable. Review it manually.', 'wp-asset-clean-up'),
            'samePathRequestSuffix'     => __('On {count} of {total} checks, the browser requested the same file path with a different query string. DevTools may show the same filename, but the configured preload and the requested URL are different cache keys.', 'wp-asset-clean-up'),
            'cssReason'                 => __('The URL is still referenced by a loaded @font-face rule, but that variation was not requested on the sampled pages.', 'wp-asset-clean-up'),
            'googleCssReason'           => __('The exact file is referenced by a Google Fonts stylesheet returned to this browser, but it was not requested on the sampled pages. It may belong to another weight, italic style, character subset, variable-font range, or icon selection.', 'wp-asset-clean-up'),
            'notDetectedReason'         => __('The URL was not requested or found in readable @font-face rules on the pages checked. It could still be used on another template or after an interaction.', 'wp-asset-clean-up'),
            'googleNotDetectedReason'   => __('The URL was not requested and was not found in the resolved Google Fonts stylesheets used by the sampled pages. It could still belong to another browser, language, template, interaction, text subset, or icon selection.', 'wp-asset-clean-up'),
            'browserSpecificReviewReason' => __('This generated Google font file is not returned consistently by the relevant representative browser stylesheet checks ({exact} of {total} profiles returned the exact URL). Because a manual gstatic preload can therefore over-fetch or preload the wrong browser-specific binary, WPACU keeps it as Review even when the current browser uses it broadly.', 'wp-asset-clean-up'),
            'browserSpecificAdvisoryReason' => __('Cross-browser advisory: the exact generated file was returned by {exact} of {total} relevant browser profiles. This does not cancel the measured usage coverage, but a static gstatic preload can be unnecessary or mismatched in the other browser profile(s).', 'wp-asset-clean-up'),
            'googleResolvePartial'      => __('Some Google Fonts stylesheets could not be resolved, so the result remains conservative.', 'wp-asset-clean-up'),
            'failedReason'              => __('No page completed the browser check, so this entry was left unchanged.', 'wp-asset-clean-up'),
            'partialSuffix'             => __('{failed} of {total} page/viewport checks did not return complete evidence for this URL after the automatic retry. Other configured fonts can still use the successful evidence from those pages. Site-wide suitability for this URL could not be confirmed, so no browser-based removal was preselected.', 'wp-asset-clean-up'),
            'removeSelected'            => __('Remove selected from the field', 'wp-asset-clean-up'),
            'nothingSelected'           => __('Select at least one removable entry.', 'wp-asset-clean-up'),
            'fieldUpdated'              => __('The textarea was updated only. Click “Save Changes” to apply the cleanup.', 'wp-asset-clean-up'),
            'undo'                      => __('Undo', 'wp-asset-clean-up'),
            'scanFailed'                => __('This page did not return a scanner result before the timeout.', 'wp-asset-clean-up'),
            'bootstrapTimeout'          => __('The page did not reach the scanner collector within {seconds} seconds.', 'wp-asset-clean-up'),
            'collectorMissing'          => __('The page loaded, but the scanner collector did not start. Page cache, Content Security Policy, a missing wp_head() call, or another optimization layer may have removed or blocked it.', 'wp-asset-clean-up'),
            'collectorReady'            => __('Scanner collector ready; waiting for font evidence…', 'wp-asset-clean-up'),
            'evidenceTimeout'           => __('The collector started, but font evidence was not returned within {seconds} seconds.', 'wp-asset-clean-up'),
            'hardTimeout'               => __('The scan exceeded the maximum time allowed for this page.', 'wp-asset-clean-up'),
            'invalidResult'             => __('The page returned an invalid scanner result.', 'wp-asset-clean-up'),
            'iframeError'               => __('The hidden page frame could not be loaded.', 'wp-asset-clean-up'),
            'collectorError'            => __('The browser collector failed while gathering font evidence.', 'wp-asset-clean-up'),
            'targetEvidenceIncompleteOne'=> __('One configured font still needs target-specific evidence.', 'wp-asset-clean-up'),
            'targetEvidenceIncompleteMany'=> __('{count} configured fonts still need target-specific evidence.', 'wp-asset-clean-up'),
            'targetEvidenceAfterRetryOne'=> __('Evidence was collected, but one configured font still needs review after the automatic retry. Results for the other fonts on this page were kept.', 'wp-asset-clean-up'),
            'targetEvidenceAfterRetryMany'=> __('Evidence was collected, but {count} configured fonts still need review after the automatic retry. Results for the other fonts on this page were kept.', 'wp-asset-clean-up'),
            'targetEvidenceDiagnostic' => __('{font}: {reason} Exact request observed: {exact}; CSS reference found: {css}; matching FontFace status: {status}.', 'wp-asset-clean-up'),
            'targetReasonLoading'       => __('target font is still loading.', 'wp-asset-clean-up'),
            'targetReasonError'         => __('target font reported a loading error.', 'wp-asset-clean-up'),
            'targetReasonGooglePending' => __('relevant Google Fonts stylesheet is still pending.', 'wp-asset-clean-up'),
            'targetReasonActivity'      => __('target-specific activity did not settle.', 'wp-asset-clean-up'),
            'targetReasonOwnPreload'    => __('WPACU’s own manual preload was still present in the scanned document, so the request could not be treated as natural usage.', 'wp-asset-clean-up'),
            'targetReasonOptimizedFallbackNegative' => __('the optimized fallback did not observe this font, but a trimmed page is never used as proof of non-use.', 'wp-asset-clean-up'),
            'targetReasonUnknown'       => __('target evidence remained incomplete.', 'wp-asset-clean-up'),
            'retryingTask'              => __('The first attempt failed. Retrying automatically ({attempt} of {max})…', 'wp-asset-clean-up'),
            'confirmingZeroObservation' => __('No configured font URLs were observed. Confirming with one fresh page check…', 'wp-asset-clean-up'),
            'retryAttemptRunning'       => __('Automatic retry {attempt} of {max} is running…', 'wp-asset-clean-up'),
            'confirmationAttemptRunning'=> __('Confirmation check {attempt} of {max} is running…', 'wp-asset-clean-up'),
            'succeededAfterRetry'       => __('Succeeded after an automatic retry.', 'wp-asset-clean-up'),
            'observedAfterConfirmation' => __('Font evidence was recovered by the automatic confirmation check.', 'wp-asset-clean-up'),
            'failedAfterOneAttempt'     => __('Failed after one attempt.', 'wp-asset-clean-up'),
            'failedAfterAttempts'       => __('Failed after {attempts} attempts.', 'wp-asset-clean-up'),
            'retryOneFailedCheck'       => __('Retry the failed check', 'wp-asset-clean-up'),
            'retryFailedChecks'         => __('Retry {count} failed checks', 'wp-asset-clean-up'),
            'retryingOneFailedCheck'    => __('Retrying the failed check with a fresh page request…', 'wp-asset-clean-up'),
            'retryingFailedChecks'      => __('Retrying {count} failed checks with fresh page requests…', 'wp-asset-clean-up'),
            'manualRetryRunning'        => __('Retrying this previously failed check…', 'wp-asset-clean-up'),
            'manualRetrySucceeded'      => __('Succeeded when the failed check was retried.', 'wp-asset-clean-up'),
            'pagesChecked'              => __('Pages checked', 'wp-asset-clean-up'),
            'checksFailed'              => __('Incomplete checks', 'wp-asset-clean-up'),
            'pageCoverage'              => __('Page coverage', 'wp-asset-clean-up'),
            'checkCoverage'             => __('Page/viewport coverage', 'wp-asset-clean-up'),
            'desktopCoverage'           => __('Desktop viewport checks', 'wp-asset-clean-up'),
            'mobileCoverage'            => __('Mobile viewport checks', 'wp-asset-clean-up'),
            'detectedOn'                => __('Confirmed natural use on', 'wp-asset-clean-up'),
            'exactRequestOn'            => __('Exact URL request observed on', 'wp-asset-clean-up'),
            'exactNotRequestedOn'       => __('Exact URL not naturally requested on', 'wp-asset-clean-up'),
            'samePathRequestOn'         => __('Same path, different URL requested on', 'wp-asset-clean-up'),
            'ownPreloadUnexpectedOn'    => __('Unexpected WPACU preload remained on', 'wp-asset-clean-up'),
            'fontFaceEvidence'          => __('Local FontFaceSet fallback', 'wp-asset-clean-up'),
            'googleSemanticEvidence'    => __('Google FontFaceSet review signal', 'wp-asset-clean-up'),
            'incompleteOn'              => __('Needs review on', 'wp-asset-clean-up'),
            'preloadedElsewhereOn'      => __('Compatible preload found on', 'wp-asset-clean-up'),
            'requestProvenance'         => __('Exact request provenance', 'wp-asset-clean-up'),
            'browserCssCompatibility'   => __('Google CSS mapping across browsers', 'wp-asset-clean-up'),
            'browserCssCompatibilityHelp' => __('This compares the font-file URLs returned by Google Fonts CSS for the current browser and up to two representative desktop browser User-Agents. Only stylesheets mapped to this file or requesting the same family are considered; unrelated stylesheet failures are excluded. It is a stylesheet compatibility diagnostic only; it does not claim that those other browsers would request the font on this page.', 'wp-asset-clean-up'),
            'browserCssExactMatch'      => __('exact configured file is present in the returned CSS', 'wp-asset-clean-up'),
            'browserCssAlternativeMatch'=> __('the same font-face descriptor maps to {count} different Google file(s)', 'wp-asset-clean-up'),
            'browserCssNoMatch'         => __('this configured file/descriptor was not found in the returned CSS', 'wp-asset-clean-up'),
            'browserCssUnknown'         => __('comparison was inconclusive because a stylesheet relevant to this font file could not be resolved', 'wp-asset-clean-up'),
            'currentBrowser'            => __('Current browser', 'wp-asset-clean-up'),
            'manualPreloadSuppressionComplete' => __('WPACU’s manual preload was confirmed as suppressed on all {suppressed} of {total} successful checks.', 'wp-asset-clean-up'),
            'manualPreloadSuppressionIncomplete' => __('WPACU’s manual preload was confirmed as suppressed on only {suppressed} of {total} successful checks. Unsuppressed checks are not accepted as natural usage.', 'wp-asset-clean-up'),
            'requestSourceOwnPreload'   => __('WPACU manual preload', 'wp-asset-clean-up'),
            'requestSourceOtherPreload' => __('Another link preload', 'wp-asset-clean-up'),
            'requestSourceCss'          => __('CSS / @font-face', 'wp-asset-clean-up'),
            'requestSourceLink'         => __('Link preload or resource hint', 'wp-asset-clean-up'),
            'requestSourceScript'       => __('Script or Fetch API', 'wp-asset-clean-up'),
            'requestSourceOther'        => __('Browser / other', 'wp-asset-clean-up'),
            'naturalUseAccepted'        => __('Accepted as natural use', 'wp-asset-clean-up'),
            'naturalUseNotAccepted'     => __('Not accepted as natural use', 'wp-asset-clean-up'),
            'manualPreloadSuppressedYes'=> __('WPACU preload suppressed', 'wp-asset-clean-up'),
            'manualPreloadSuppressedNo' => __('WPACU preload not suppressed', 'wp-asset-clean-up'),
            'requestInitiatorValue'     => __('Initiator: {value}', 'wp-asset-clean-up'),
            'requestDeliveryValue'      => __('Delivery: {value}', 'wp-asset-clean-up'),
            'requestCacheReuse'         => __('Transfer size 0 B / possible cache reuse', 'wp-asset-clean-up'),
            'requestDurationValue'      => __('Duration: {value}ms', 'wp-asset-clean-up'),
            'requestCapturedViaValue'   => __('Captured via: {value}', 'wp-asset-clean-up'),
            'noExactRequestProvenance'  => __('No exact file request was captured on the successful checks. Other evidence and the negative verification links remain listed below.', 'wp-asset-clean-up'),
            'unresolvedStylesheets'     => __('Unresolved Google stylesheet(s)', 'wp-asset-clean-up'),
            'unresolvedStylesheetHelp'  => __('This exact file was not found in the Google stylesheets that WPACU successfully resolved. One of the stylesheets below may still define it, so the URL remains protected as Review.', 'wp-asset-clean-up'),
            'variant'                   => __('Detected variation', 'wp-asset-clean-up'),
            'sourceStylesheet'          => __('Resolved Google stylesheet for this file', 'wp-asset-clean-up'),
            'replacementUrl'            => __('Current URL detected', 'wp-asset-clean-up'),
            'openVerificationPage'      => __('Open clean verification page', 'wp-asset-clean-up'),
            'openVerificationPageTitle' => __('Open the real page in a new tab with WPACU’s manual preload temporarily suppressed and without scan-only HTML trimming. Match the audited viewport when checking it.', 'wp-asset-clean-up'),
            'manualVerificationSummary' => __('Verify the negative checks in DevTools', 'wp-asset-clean-up'),
            'manualVerificationHelp'    => __('These temporary links open the normal, untrimmed page with WPACU’s manual preload suppressed. Open DevTools first, enable Disable cache, match the audited viewport, then reload. A normal page opened without this link is not a valid comparison while the URL remains saved, because the setting itself forces the font request.', 'wp-asset-clean-up'),
            'viewEvidence'              => __('View evidence and technical details', 'wp-asset-clean-up'),
            'viewEvidenceHint'          => __('Coverage, page checks, variations and clean verification links', 'wp-asset-clean-up'),
            'computedStyleEvidence'     => __('Rendered local-font usage match', 'wp-asset-clean-up'),
            'globalIncompleteOne'       => __('One page/viewport check still needs review. Protected entries cannot be selected for removal.', 'wp-asset-clean-up'),
            'globalIncompleteMany'      => __('{count} page/viewport checks still need review. Protected entries cannot be selected for removal.', 'wp-asset-clean-up'),
            'globalGoogleResolveOne'    => __('One Google Fonts stylesheet could not be resolved. Entries with complete evidence from other stylesheets are still evaluated normally.', 'wp-asset-clean-up'),
            'globalGoogleResolveMany'   => __('{count} Google Fonts stylesheets could not be resolved. Entries with complete evidence from other stylesheets are still evaluated normally.', 'wp-asset-clean-up'),
            'globalGoogleResolveUnrelatedOne' => __('One Google Fonts stylesheet could not be resolved, but it is not needed for the mapped evidence of any configured font file below. Their recommendations were evaluated independently.', 'wp-asset-clean-up'),
            'globalGoogleResolveUnrelatedMany' => __('{count} Google Fonts stylesheets could not be resolved, but none is needed for the mapped evidence of the configured font files below. Their recommendations were evaluated independently.', 'wp-asset-clean-up'),
            'globalGoogleResolveIgnoredOne' => __('One invalid Google Fonts stylesheet was ignored because it does not map to any configured font file below. It does not affect these recommendations.', 'wp-asset-clean-up'),
            'globalGoogleResolveIgnoredMany' => __('{count} invalid Google Fonts stylesheets were ignored because they do not map to any configured font file below. They do not affect these recommendations.', 'wp-asset-clean-up'),
            'globalNoRemovalRecommendation' => __('The audit did not find an entry that should be offered for cleanup. Details remain available for review.', 'wp-asset-clean-up'),
            'selectedCount'             => __('{count} selected', 'wp-asset-clean-up'),
            'urlSingular'               => __('URL', 'wp-asset-clean-up'),
            'urlPlural'                 => __('URLs', 'wp-asset-clean-up'),
            'coverageValue'             => __('{used} of {total} ({percent}%)', 'wp-asset-clean-up'),
            'failedCoverageValue'       => __('{failed} of {total}', 'wp-asset-clean-up')
        );

        return apply_filters('wpacu_font_preload_scanner_admin_strings', $strings, $provider);
    }

    /**
     * @param string $provider
     * @param array  $fontUrls
     *
     * @return array
     */
    private static function buildFontEntries($provider, $fontUrls)
    {
        $fontEntries = array();
        $seen         = array();

        foreach ($fontUrls as $lineIndex => $fontUrl) {
            $normalisedUrl = self::normaliseUrl($fontUrl, self::getScanHomeUrl());
            $dedupeKey     = $normalisedUrl !== '' ? $normalisedUrl : 'raw:' . trim($fontUrl);
            $duplicateOf   = isset($seen[$dedupeKey]) ? (int) $seen[$dedupeKey] : null;

            if ($duplicateOf === null) {
                $seen[$dedupeKey] = $lineIndex;
            }

            $entry = array(
                'provider'        => $provider,
                'original'        => $fontUrl,
                'normalised'      => $normalisedUrl,
                'valid'           => (bool) $normalisedUrl,
                'invalidCode'     => $normalisedUrl ? '' : 'invalid_url',
                'lineIndex'       => (int) $lineIndex,
                'duplicateOf'     => $duplicateOf,
                'localFileStatus' => 'unknown'
            );

            if ($provider === 'local') {
                $entry['localFileStatus'] = self::getLocalFileStatus($normalisedUrl, $fontUrl);
            } elseif ($provider === 'google' && $normalisedUrl && ! self::isAllowedGoogleFontFileUrl($normalisedUrl)) {
                $entry['valid']       = false;
                $entry['invalidCode'] = 'wrong_google_host';
            }

            $fontEntries[] = $entry;
        }

        return $fontEntries;
    }

    /**
     * Canonical provider registry used by the full scanner lifecycle.
     *
     * The early bootstrap duplicates only `provider`, `query_arg`, and
     * `transient_prefix` to avoid loading this large orchestration class during
     * active-plugin bootstrap. Any change to those three values must also be
     * mirrored in FontPreloadScannerEarly::getProviderDefinitions().
     *
     * @param string $provider
     *
     * @return array
     */
    private static function getProviderDefinition($provider)
    {
        if ($provider === 'google') {
            return array(
                'provider'              => 'google',
                'ajax_action'           => 'wpassetcleanup_prepare_google_fonts_preload_scan',
                'nonce_action'          => 'wpacu_google_fonts_preload_scan',
                'query_arg'             => 'wpacu_google_font_preload_scan',
                'view_query_arg'        => 'wpacu_google_font_preload_scan_view',
                'transient_prefix'      => 'wpacu_gfps_',
                'response_header'       => 'X-WPACU-Google-Font-Scan',
                'message_ready'         => 'wpacu-google-font-preload-scan-ready',
                'message_result'        => 'wpacu-google-font-preload-scan-result',
                'message_error'         => 'wpacu-google-font-preload-scan-error',
                'own_preload_attribute' => 'data-wpacu-preload-google-font'
            );
        }

        return array(
            'provider'              => 'local',
            'ajax_action'           => 'wpassetcleanup_prepare_local_fonts_preload_scan',
            'nonce_action'          => 'wpacu_local_fonts_preload_scan',
            'query_arg'             => 'wpacu_local_font_preload_scan',
            'view_query_arg'        => 'wpacu_local_font_preload_scan_view',
            'transient_prefix'      => 'wpacu_lfps_',
            'response_header'       => 'X-WPACU-Local-Font-Scan',
            'message_ready'         => 'wpacu-local-font-preload-scan-ready',
            'message_result'        => 'wpacu-local-font-preload-scan-result',
            'message_error'         => 'wpacu-local-font-preload-scan-error',
            'own_preload_attribute' => 'data-wpacu-preload-local-font'
        );
    }

    /**
     * Resolve the WPML language selected for the current Settings request.
     *
     * @return string
     */
    private static function getWpmlLanguageCodeFromRequest()
    {
        $candidates = array();

        if (isset($_REQUEST['lang']) && is_string($_REQUEST['lang'])) {
            $candidates[] = wp_unslash($_REQUEST['lang']);
        }

        $candidates[] = apply_filters('wpml_current_language', null);

        if (defined('ICL_LANGUAGE_CODE')) {
            $candidates[] = ICL_LANGUAGE_CODE;
        }

        $candidates[] = apply_filters('wpml_default_language', null);

        $activeLanguageCodes = self::getWpmlActiveLanguageCodes();

        foreach ($candidates as $candidate) {
            if ( ! is_string($candidate)) {
                continue;
            }

            $candidate = sanitize_key($candidate);

            if ($candidate === '' || $candidate === 'all') {
                continue;
            }

            if (empty($activeLanguageCodes) || in_array($candidate, $activeLanguageCodes, true)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param string $requestedLanguageCode
     *
     * @return void
     */
    private static function setWpmlScanLanguage($requestedLanguageCode)
    {
        $requestedLanguageCode = sanitize_key((string) $requestedLanguageCode);
        $activeLanguageCodes    = self::getWpmlActiveLanguageCodes();

        if (
            $requestedLanguageCode === ''
            || $requestedLanguageCode === 'all'
            || ( ! empty($activeLanguageCodes) && ! in_array($requestedLanguageCode, $activeLanguageCodes, true))
        ) {
            $requestedLanguageCode = self::getWpmlLanguageCodeFromRequest();
        }

        self::$scanLanguageCode = $requestedLanguageCode;

        if (self::$scanLanguageCode !== '') {
            do_action('wpml_switch_language', self::$scanLanguageCode);
        }

        self::$scanHomeUrl = self::resolveWpmlHomeUrl(self::$scanLanguageCode);
    }

    /**
     * @return string[]
     */
    private static function getWpmlActiveLanguageCodes()
    {
        $languageCodes   = array();
        $activeLanguages = apply_filters('wpml_active_languages', null, array(
            'skip_missing' => 0
        ));

        if (is_array($activeLanguages)) {
            foreach ($activeLanguages as $languageKey => $languageData) {
                $languageCode = is_string($languageKey) ? $languageKey : '';

                if (is_array($languageData) && ! empty($languageData['language_code'])) {
                    $languageCode = $languageData['language_code'];
                } elseif (is_object($languageData) && ! empty($languageData->language_code)) {
                    $languageCode = $languageData->language_code;
                }

                $languageCode = sanitize_key((string) $languageCode);

                if ($languageCode !== '') {
                    $languageCodes[] = $languageCode;
                }
            }
        }

        $wpmlSettings = get_option('icl_sitepress_settings', array());

        if (is_array($wpmlSettings)) {
            if ( ! empty($wpmlSettings['active_languages']) && is_array($wpmlSettings['active_languages'])) {
                foreach ($wpmlSettings['active_languages'] as $languageCode) {
                    if ( ! is_scalar($languageCode)) {
                        continue;
                    }

                    $languageCode = sanitize_key((string) $languageCode);
                    if ($languageCode !== '') {
                        $languageCodes[] = $languageCode;
                    }
                }
            }

            if ( ! empty($wpmlSettings['default_language'])) {
                $languageCodes[] = sanitize_key((string) $wpmlSettings['default_language']);
            }
        }

        return array_values(array_unique(array_filter($languageCodes)));
    }

    /**
     * @param string $languageCode
     *
     * @return string
     */
    private static function resolveWpmlHomeUrl($languageCode)
    {
        $homeUrl = home_url('/');

        if ($languageCode === '') {
            return $homeUrl;
        }

        $wpmlHomeUrl = apply_filters('wpml_home_url', $homeUrl);

        if (is_string($wpmlHomeUrl) && esc_url_raw($wpmlHomeUrl) !== '') {
            $homeUrl = $wpmlHomeUrl;
        }

        $wpmlPermalink = apply_filters('wpml_permalink', $homeUrl, $languageCode, true);

        if (is_string($wpmlPermalink) && esc_url_raw($wpmlPermalink) !== '') {
            $homeUrl = $wpmlPermalink;
        }

        return trailingslashit($homeUrl);
    }

    /**
     * @return string
     */
    private static function getScanHomeUrl()
    {
        if (self::$scanHomeUrl !== '') {
            return self::$scanHomeUrl;
        }

        $languageCode = self::$scanLanguageCode !== ''
            ? self::$scanLanguageCode
            : self::getWpmlLanguageCodeFromRequest();

        self::$scanHomeUrl = self::resolveWpmlHomeUrl($languageCode);

        return self::$scanHomeUrl;
    }

    /**
     * @param array $extraUrls
     *
     * @return array
     */
    private static function getRepresentativePages($extraUrls)
    {
        $pages = array();
        $seen  = array();

        self::addScanPage($pages, $seen, __('Homepage', 'wp-asset-clean-up'), self::getScanHomeUrl());

        foreach ($extraUrls as $extraIndex => $extraUrl) {
            $normalisedExtraUrl = self::normaliseSameSiteScanUrl($extraUrl);

            if ($normalisedExtraUrl) {
                self::addScanPage(
                    $pages,
                    $seen,
                    sprintf(__('Important page %d', 'wp-asset-clean-up'), $extraIndex + 1),
                    $normalisedExtraUrl
                );
            }
        }

        self::addLatestPostTypePage($pages, $seen, 'post', __('Latest post', 'wp-asset-clean-up'));
        self::addLatestPostTypePage($pages, $seen, 'page', __('Latest page', 'wp-asset-clean-up'));

        $publicPostTypes = get_post_types(array('public' => true), 'objects');

        if (isset($publicPostTypes['product'])) {
            $productObject = $publicPostTypes['product'];
            self::addLatestPostTypePage(
                $pages,
                $seen,
                'product',
                sprintf(__('Latest %s', 'wp-asset-clean-up'), strtolower($productObject->labels->singular_name))
            );
            unset($publicPostTypes['product']);
        }

        $pageForPosts = (int) get_option('page_for_posts');
        if ($pageForPosts > 0 && get_post_status($pageForPosts) === 'publish') {
            self::addScanPage($pages, $seen, __('Posts page', 'wp-asset-clean-up'), get_permalink($pageForPosts));
        }

        foreach ($publicPostTypes as $postType => $postTypeObject) {
            if (count($pages) >= self::MAX_SCAN_PAGES) {
                break;
            }

            if (in_array($postType, array('post', 'page', 'attachment'), true) || empty($postTypeObject->publicly_queryable)) {
                continue;
            }

            self::addLatestPostTypePage(
                $pages,
                $seen,
                $postType,
                sprintf(__('Latest %s', 'wp-asset-clean-up'), strtolower($postTypeObject->labels->singular_name))
            );
        }

        return array_slice($pages, 0, self::MAX_SCAN_PAGES);
    }

    /**
     * @param array  $pages
     * @param array  $seen
     * @param string $postType
     * @param string $label
     *
     * @return void
     */
    private static function addLatestPostTypePage(&$pages, &$seen, $postType, $label)
    {
        if (count($pages) >= self::MAX_SCAN_PAGES || ! post_type_exists($postType)) {
            return;
        }

        $posts = get_posts(array(
            'post_type'              => $postType,
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'has_password'           => false,
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'suppress_filters'       => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ));

        if (empty($posts[0]) || ! empty($posts[0]->post_password)) {
            return;
        }

        self::addScanPage($pages, $seen, $label, get_permalink($posts[0]));
    }

    /**
     * @param array  $pages
     * @param array  $seen
     * @param string $label
     * @param string $url
     *
     * @return void
     */
    private static function addScanPage(&$pages, &$seen, $label, $url)
    {
        if (count($pages) >= self::MAX_SCAN_PAGES || ! $url) {
            return;
        }

        $normalisedUrl = self::normaliseSameSiteScanUrl($url);

        if ( ! $normalisedUrl ) {
            return;
        }

        $dedupeKey = strtolower(untrailingslashit(remove_query_arg(array(
            'wpacu_local_font_preload_scan',
            'wpacu_local_font_preload_scan_view',
            'wpacu_google_font_preload_scan',
            'wpacu_google_font_preload_scan_view',
            'wpacu_no_frontend_show',
            'wpacu_font_scan_cb',
            'wpacu_font_scan_task',
            'wpacu_font_scan_attempt',
            self::MANUAL_VERIFICATION_QUERY_ARG,
            self::OPTIMIZED_FALLBACK_QUERY_ARG
        ), $normalisedUrl)));

        if (isset($seen[$dedupeKey])) {
            return;
        }

        $seen[$dedupeKey] = true;
        $pages[] = array(
            'label' => $label,
            'url'   => $normalisedUrl
        );
    }

    /**
     * @param string $url
     *
     * @return string
     */
    private static function normaliseSameSiteScanUrl($url)
    {
        $normalisedUrl = self::normaliseUrl($url, self::getScanHomeUrl());

        if ( ! $normalisedUrl ) {
            return '';
        }

        $urlParts       = parse_url($normalisedUrl);
        $urlOrigin      = self::getUrlOrigin($normalisedUrl);
        $allowedOrigins = self::getAllowedSiteOrigins();

        if ( ! $urlOrigin || ! in_array($urlOrigin, $allowedOrigins, true) ) {
            return '';
        }

        if ( ! empty($urlParts['path']) ) {
            $pathForAdminCheck = str_replace('\\', '/', rawurldecode($urlParts['path']));

            if (preg_match('#(?:^|/)wp-admin(?:/|$)#i', $pathForAdminCheck)) {
                return '';
            }
        }

        return $normalisedUrl;
    }

    /**
     * @param string $raw
     * @param int    $limit
     * @param bool   $dedupe
     *
     * @return array
     */
    private static function parseNonEmptyLines($raw, $limit, $dedupe)
    {
        if (is_array($raw) || is_object($raw)) {
            return array();
        }

        $lines  = preg_split('/\r\n|\r|\n/', (string) $raw);
        $output = array();
        $seen   = array();

        foreach ($lines as $line) {
            $line = trim(wp_strip_all_tags($line));

            if ($line === '') {
                continue;
            }

            if ($dedupe && isset($seen[$line])) {
                continue;
            }

            $seen[$line] = true;
            $output[] = $line;

            if (count($output) >= $limit) {
                break;
            }
        }

        return $output;
    }

    /**
     * Resolve a URL in a browser-compatible way for comparison and remote CSS.
     *
     * @param string $url
     * @param string $baseUrl
     *
     * @return string
     */
    public static function normaliseUrl($url, $baseUrl)
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8'));
        $baseUrl = trim(html_entity_decode((string) $baseUrl, ENT_QUOTES, 'UTF-8'));

        if ($url === '') {
            return '';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && ! preg_match('#^https?://#i', $url)) {
            return '';
        }

        $isAbsoluteHttpUrl = (bool) preg_match('#^https?://#i', $url);
        $baseParts = array();
        $baseScheme = '';
        $baseHost = '';
        $baseOrigin = '';

        if ( ! $isAbsoluteHttpUrl ) {
            $baseParts = parse_url($baseUrl);
            $baseScheme = isset($baseParts['scheme']) ? strtolower($baseParts['scheme']) : '';
            $baseHost = isset($baseParts['host']) ? strtolower($baseParts['host']) : '';

            if (strpos($url, '//') === 0) {
                // Match browser behaviour: protocol-relative URLs can still be
                // normalised when the caller has no usable base URL.
                $url = (in_array($baseScheme, array('http', 'https'), true) ? $baseScheme : 'https') . ':' . $url;
            } else {
                if ( ! in_array($baseScheme, array('http', 'https'), true) || $baseHost === ''
                    || isset($baseParts['user']) || isset($baseParts['pass'])) {
                    return '';
                }

                $baseOrigin = $baseScheme . '://' . $baseHost;
                if ( ! empty($baseParts['port']) ) {
                    $baseOrigin .= ':' . (int) $baseParts['port'];
                }

                $basePath = isset($baseParts['path']) && $baseParts['path'] !== '' ? $baseParts['path'] : '/';

                if (strpos($url, '/') === 0) {
                    $url = $baseOrigin . $url;
                } elseif (strpos($url, '?') === 0 || strpos($url, '#') === 0) {
                    $url = $baseOrigin . $basePath . $url;
                } else {
                    $lastSlashPosition = strrpos($basePath, '/');
                    $baseDirectory = substr($basePath, -1) === '/'
                        ? $basePath
                        : substr($basePath, 0, $lastSlashPosition === false ? 0 : $lastSlashPosition + 1);
                    $url = $baseOrigin . $baseDirectory . $url;
                }
            }
        }

        $parts = parse_url($url);

        if (empty($parts['scheme']) || empty($parts['host'])
            || ! in_array(strtolower($parts['scheme']), array('http', 'https'), true)
            || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }

        $normalised = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);

        if ( ! empty($parts['port']) ) {
            $normalised .= ':' . (int) $parts['port'];
        }

        $path = isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';
        $normalised .= self::normaliseUrlPath($path);

        if (isset($parts['query']) && $parts['query'] !== '') {
            $normalised .= '?' . $parts['query'];
        }

        return esc_url_raw($normalised);
    }

    /**
     * Collapse dot segments without decoding or otherwise changing URL path data.
     *
     * @param string $path
     *
     * @return string
     */
    private static function normaliseUrlPath($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        $hasTrailingSlash = substr($path, -1) === '/';
        $segments = explode('/', $path);
        $normalisedSegments = array();

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($normalisedSegments);
                continue;
            }

            $normalisedSegments[] = $segment;
        }

        $normalisedPath = '/' . implode('/', $normalisedSegments);

        if ($hasTrailingSlash && $normalisedPath !== '/') {
            $normalisedPath .= '/';
        }

        return $normalisedPath;
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    public static function isAllowedGoogleFontFileUrl($url)
    {
        $normalisedUrl = self::normaliseUrl($url, 'https://fonts.gstatic.com/');

        if ( ! $normalisedUrl ) {
            return false;
        }

        $parts  = parse_url($normalisedUrl);
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $host   = isset($parts['host']) ? strtolower($parts['host']) : '';
        $port   = isset($parts['port']) ? (int) $parts['port'] : 0;

        if ( ! in_array($host, array('fonts.gstatic.com', 'themes.googleusercontent.com'), true) ) {
            return false;
        }

        if ($scheme === 'https') {
            return $port === 0 || $port === 443;
        }

        return $scheme === 'http' && ($port === 0 || $port === 80);
    }

    /**
     * @param string $normalisedUrl
     * @param string $originalUrl
     *
     * @return string exists|missing|unknown|invalid
     */
    private static function getLocalFileStatus($normalisedUrl, $originalUrl = '')
    {
        if ( ! $normalisedUrl ) {
            return 'invalid';
        }

        $originalUrl = trim((string) $originalUrl);
        if ($originalUrl !== '' && ! preg_match('#^(?:https?:)?//#i', $originalUrl) && strpos($originalUrl, '/') !== 0) {
            return 'unknown';
        }

        $urlPath = parse_url($normalisedUrl, PHP_URL_PATH);

        if ( ! $urlPath || ! preg_match('/\.(?:woff2?|ttf|otf|eot)$/i', $urlPath) ) {
            return 'unknown';
        }

        $mappings = array(
            array(content_url('/'), trailingslashit(WP_CONTENT_DIR)),
            array(plugins_url('/'), trailingslashit(WP_PLUGIN_DIR)),
            array(includes_url('/'), trailingslashit(ABSPATH . WPINC)),
            array(site_url('/'), trailingslashit(ABSPATH))
        );

        foreach ($mappings as $mapping) {
            $urlBase  = self::normaliseUrl($mapping[0], home_url('/'));
            $basePath = wp_normalize_path($mapping[1]);

            if ( ! $urlBase ) {
                continue;
            }

            $urlBaseParts = parse_url($urlBase);
            $urlParts     = parse_url($normalisedUrl);

            if (empty($urlBaseParts['host']) || empty($urlParts['host']) ||
                strtolower($urlBaseParts['host']) !== strtolower($urlParts['host'])) {
                continue;
            }

            $baseUrlPath = isset($urlBaseParts['path']) ? trailingslashit($urlBaseParts['path']) : '/';
            $targetPath  = isset($urlParts['path']) ? $urlParts['path'] : '';

            if (strpos($targetPath, $baseUrlPath) !== 0) {
                continue;
            }

            $relativePath = rawurldecode(ltrim(substr($targetPath, strlen($baseUrlPath)), '/'));

            if ($relativePath === '' || strpos($relativePath, '..') !== false || strpos($relativePath, "\0") !== false) {
                return 'unknown';
            }

            $candidatePath = wp_normalize_path($basePath . $relativePath);

            if (strpos($candidatePath, $basePath) !== 0) {
                return 'unknown';
            }

            return is_file($candidatePath) ? 'exists' : 'missing';
        }

        return 'unknown';
    }

    /**
     * @return array
     */
    private static function getAllowedSiteOrigins()
    {
        return array_values(array_filter(array_unique(array(
            self::getUrlOrigin(self::getScanHomeUrl()),
            self::getUrlOrigin(home_url('/')),
            self::getUrlOrigin(site_url('/'))
        ))));
    }

    /**
     * @param string $url
     *
     * @return string
     */
    private static function getUrlOrigin($url)
    {
        $parts = parse_url($url);

        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);

        if ( ! empty($parts['port']) ) {
            $origin .= ':' . (int) $parts['port'];
        }

        return $origin;
    }

    /**
     * @param string $url
     *
     * @return string
     */
    private static function getDisplayUrl($url)
    {
        $homeOrigin = self::getUrlOrigin(self::getScanHomeUrl());

        if ($homeOrigin && strpos($url, $homeOrigin) === 0) {
            $relative = substr($url, strlen($homeOrigin));
            return $relative !== '' ? $relative : '/';
        }

        return $url;
    }

    /**
     * @param string $provider
     *
     * @return string
     */
    private static function getCurrentUrlWithoutScanArgs($provider)
    {
        $definition = self::getProviderDefinition($provider);
        $scheme     = is_ssl() ? 'https' : 'http';
        $host       = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', $_SERVER['HTTP_HOST']) : '';
        $uri        = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';

        if ( ! $host ) {
            return self::getScanHomeUrl();
        }

        $url = $scheme . '://' . $host . $uri;

        return remove_query_arg(array(
            $definition['query_arg'],
            $definition['view_query_arg'],
            'wpacu_no_frontend_show',
            'wpacu_font_scan_cb',
            'wpacu_font_scan_task',
            'wpacu_font_scan_attempt',
            self::MANUAL_VERIFICATION_QUERY_ARG,
            self::OPTIMIZED_FALLBACK_QUERY_ARG
        ), $url);
    }
}
