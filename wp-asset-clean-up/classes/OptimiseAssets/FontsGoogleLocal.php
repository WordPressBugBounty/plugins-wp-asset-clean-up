<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;

/**
 * Runtime orchestration for the shared Google Fonts local-hosting engine.
 */
class FontsGoogleLocal
{
    const AUTOMATIC_CRON_HOOK = 'wpassetcleanup_google_fonts_local_process_queue_common';
    const AUTOMATIC_LOCK_OPTION = 'wpassetcleanup_google_fonts_local_common_lock';
    const AUTOMATIC_LOCK_TTL = 1800;
    const AUTOMATIC_BATCH_SIZE = 3;
    const AUTOMATIC_MAINTENANCE_TRANSIENT = 'wpacu_google_fonts_local_common_maintenance';
    const AUTOMATIC_MAINTENANCE_INTERVAL = 21600; // Six hours.

    /** @var bool */
    private static $frontendHooksRegistered = false;

    /** @var bool */
    private static $automaticHooksRegistered = false;

    /** @var bool */
    private static $automaticSpawnRequested = false;

    /** @var array */
    private static $processingNotifications = array();

    /**
     * @return bool
     */
    public static function isEnabled()
    {
        if (function_exists('wpacuIsDefinedConstant') && wpacuIsDefinedConstant('WPACU_ALLOW_ONLY_UNLOAD_RULES')) {
            return false;
        }

        if (! class_exists('WpAssetCleanUp\\Main')) {
            return false;
        }

        $settings = Main::instance()->settings;

        return ! empty($settings['google_fonts_local']) && empty($settings['google_fonts_remove']);
    }

    /**
     * Register a cheap early replacement for properly enqueued Google Fonts.
     * Hardcoded and inline references are still handled in the final content.
     *
     * @return void
     */
    public static function registerFrontendHooks()
    {
        if (self::$frontendHooksRegistered) {
            return;
        }

        self::$frontendHooksRegistered = true;
        add_filter('style_loader_src', array(__CLASS__, 'filterStyleLoaderSrc'), PHP_INT_MAX, 1);

        if (self::isEnabled()) {
            self::registerAutomaticProcessingHooks();
        }
    }

    /**
     * Lite/common automation processes newly discovered configurations through
     * one bounded WP-Cron queue. Pro disables these hooks and supplies its
     * richer queue, refresh, exclusions and browser-discovery implementation.
     *
     * @return void
     */
    private static function registerAutomaticProcessingHooks()
    {
        if (self::$automaticHooksRegistered || ! self::isCommonAutomaticProcessingEnabled()) {
            return;
        }

        self::$automaticHooksRegistered = true;

        add_action('wpacu_google_fonts_local_stylesheet_discovered', array(__CLASS__, 'onStylesheetDiscovered'), 20, 4);
        add_action(self::AUTOMATIC_CRON_HOOK, array(__CLASS__, 'processAutomaticQueue'));
        add_action('init', array(__CLASS__, 'maybeScheduleAutomaticMaintenance'), 100);
        add_action('shutdown', array(__CLASS__, 'spawnAutomaticQueueIfNeeded'), PHP_INT_MAX);
        add_filter('wpacu_internal_remove_all_data_for_uninstall_errors', array(__CLASS__, 'cleanupAutomaticQueueForUninstall'));
    }

    /**
     * @return bool
     */
    public static function isCommonAutomaticProcessingEnabled()
    {
        if (! self::isEnabled()) {
            return false;
        }

        return (bool) apply_filters('wpacu_google_fonts_local_use_common_automatic_processing', true);
    }

    /**
     * @param string $key
     * @param string $stylesheetUrl
     * @param string $context
     * @param array  $discovery
     *
     * @return void
     */
    public static function onStylesheetDiscovered($key, $stylesheetUrl, $context = '', $discovery = array())
    {
        unset($context, $discovery);

        if (! self::isCommonAutomaticProcessingEnabled()
            || ! preg_match('/^[a-f0-9]{64}$/', (string) $key)
            || FontsGoogleLocalUrl::canonicalizeStylesheetUrl($stylesheetUrl) === ''
        ) {
            return;
        }

        self::maybeScheduleAutomaticQueue(0);
    }

    /**
     * Schedule one queue event, regardless of how many pages discover the same
     * or different Google Fonts configurations at once.
     *
     * @param int $delay
     *
     * @return bool
     */
    public static function maybeScheduleAutomaticQueue($delay = 5)
    {
        if (! self::isCommonAutomaticProcessingEnabled()) {
            return false;
        }

        $delay = max(0, min(86400, (int) $delay));
        $now = self::now();
        $targetTimestamp = $now + $delay;
        $nextTimestamp = wp_next_scheduled(self::AUTOMATIC_CRON_HOOK);

        if ($nextTimestamp !== false) {
            $nextTimestamp = (int) $nextTimestamp;

            if ($nextTimestamp <= $targetTimestamp) {
                if ($nextTimestamp <= $now) {
                    self::$automaticSpawnRequested = true;
                }

                return false;
            }

            // A delayed retry or maintenance event must not hold newly
            // discovered pending work for minutes or hours.
            if (function_exists('wp_clear_scheduled_hook')) {
                wp_clear_scheduled_hook(self::AUTOMATIC_CRON_HOOK);
            } elseif (function_exists('wp_unschedule_event')) {
                wp_unschedule_event($nextTimestamp, self::AUTOMATIC_CRON_HOOK);
            }

            $remainingTimestamp = wp_next_scheduled(self::AUTOMATIC_CRON_HOOK);
            if ($remainingTimestamp !== false && (int) $remainingTimestamp > $targetTimestamp) {
                return false;
            }
        }

        $scheduled = (bool) wp_schedule_single_event($targetTimestamp, self::AUTOMATIC_CRON_HOOK);

        if (($scheduled || (int) wp_next_scheduled(self::AUTOMATIC_CRON_HOOK) <= $now)
            && $targetTimestamp <= $now
        ) {
            self::$automaticSpawnRequested = true;
        }

        return $scheduled;
    }

    /**
     * Wake WP-Cron after late output-buffer discovery. The request is
     * non-blocking and only runs when this request created or encountered a
     * due queue event.
     *
     * @return bool
     */
    public static function spawnAutomaticQueueIfNeeded()
    {
        if (! self::$automaticSpawnRequested
            || ! self::isCommonAutomaticProcessingEnabled()
            || (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON)
            || (function_exists('wp_doing_cron') && wp_doing_cron())
            || (defined('DOING_CRON') && DOING_CRON)
            || ! function_exists('spawn_cron')
        ) {
            return false;
        }

        self::$automaticSpawnRequested = false;
        $nextTimestamp = wp_next_scheduled(self::AUTOMATIC_CRON_HOOK);

        if ($nextTimestamp === false || (int) $nextTimestamp > self::now()) {
            return false;
        }

        return (bool) spawn_cron(self::now());
    }

    /**
     * Remove stale queue state after a cache reset or complete uninstall.
     *
     * @return void
     */
    public static function resetAutomaticQueueState()
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::AUTOMATIC_CRON_HOOK);
        }

        delete_option(self::AUTOMATIC_LOCK_OPTION);
        delete_transient(self::AUTOMATIC_MAINTENANCE_TRANSIENT);

        self::$automaticSpawnRequested = false;
        self::$processingNotifications = array();
    }

    /**
     * Recover configurations that were already pending when local hosting was
     * enabled or when an earlier discovery event was missed. The registry is
     * checked at most once every six hours; new discoveries schedule directly.
     *
     * @return bool
     */
    public static function maybeScheduleAutomaticMaintenance()
    {
        if (! self::isCommonAutomaticProcessingEnabled()
            || get_transient(self::AUTOMATIC_MAINTENANCE_TRANSIENT)
        ) {
            return false;
        }

        set_transient(
            self::AUTOMATIC_MAINTENANCE_TRANSIENT,
            1,
            self::AUTOMATIC_MAINTENANCE_INTERVAL
        );

        if (empty(FontsGoogleLocalRegistry::getProcessCandidates(1, 0))) {
            return false;
        }

        return self::maybeScheduleAutomaticQueue(0);
    }

    /**
     * Process a small queue batch outside the visitor response.
     *
     * @return bool
     */
    public static function processAutomaticQueue()
    {
        if (! self::isCommonAutomaticProcessingEnabled()) {
            return false;
        }

        $lockToken = self::acquireAutomaticLock();
        if ($lockToken === '') {
            return false;
        }

        $nextRetryDelay = 0;

        try {
            $candidateKeys = FontsGoogleLocalRegistry::getProcessCandidates(self::AUTOMATIC_BATCH_SIZE, 0);

            foreach ($candidateKeys as $key) {
                $entry = FontsGoogleLocalRegistry::getEntry($key);
                if (empty($entry) || empty($entry['url'])) {
                    continue;
                }

                $retryDelay = self::getAutomaticRetryDelay(isset($entry['attempts']) ? $entry['attempts'] : 0);

                try {
                    $result = FontsGoogleLocalCache::process($key, false, $retryDelay);
                } catch (\Exception $exception) {
                    FontsGoogleLocalRegistry::markFailed($key, $exception->getMessage(), $retryDelay);
                    $result = array('success' => false, 'error' => $exception->getMessage());
                }

                if (empty($result['success'])) {
                    $nextRetryDelay = $nextRetryDelay === 0
                        ? $retryDelay
                        : min($nextRetryDelay, $retryDelay);
                }
            }

            $followUpDelay = 0;

            if (! empty(FontsGoogleLocalRegistry::getProcessCandidates(1, 0))) {
                $followUpDelay = 15;
            }

            if ($nextRetryDelay > 0) {
                $followUpDelay = $followUpDelay === 0
                    ? $nextRetryDelay
                    : min($followUpDelay, $nextRetryDelay);
            }

            $deferredDelay = self::getNextDeferredAutomaticDelay();
            if ($deferredDelay > 0) {
                $followUpDelay = $followUpDelay === 0
                    ? $deferredDelay
                    : min($followUpDelay, $deferredDelay);
            }

            if ($followUpDelay > 0) {
                self::maybeScheduleAutomaticQueue($followUpDelay);
            }
        } finally {
            self::releaseAutomaticLock($lockToken);
        }

        return true;
    }

    /**
     * @param array $errors
     *
     * @return array
     */
    public static function cleanupAutomaticQueueForUninstall($errors)
    {
        self::resetAutomaticQueueState();

        return is_array($errors) ? $errors : array();
    }

    /**
     * @param string $src
     *
     * @return string
     */
    public static function filterStyleLoaderSrc($src)
    {
        if (! is_string($src) || stripos($src, 'fonts.googleapis.com') === false || self::preventRuntimeChange()) {
            return $src;
        }

        $settings = Main::instance()->settings;
        if (! empty($settings['google_fonts_combine'])) {
            return $src;
        }

        return self::alterContent($src, 'style_loader_src');
    }

    /**
     * Record canonical Google stylesheet references without changing delivery.
     * This remains active when Local Hosting is disabled so Remove Specific can
     * build an inventory from remote Google requests.
     */
    public static function discoverContent($content, $context = 'html')
    {
        if (! is_string($content) || $content === '' || stripos($content, 'fonts.googleapis.com') === false) {
            return $content;
        }

        $references = FontsGoogleLocalUrl::extractStylesheetReferences($content, $context === 'html');
        foreach ($references as $reference) {
            $stylesheetUrl = $reference['url'];
            $discovery = FontsGoogleLocalRegistry::discover(
                $stylesheetUrl,
                self::getCurrentUserAgent(),
                self::getCurrentRequestPath(),
                'php'
            );
            if (empty($discovery['key'])) {
                continue;
            }

            $requestedVariants = FontsGoogleRemove::extractVariantsFromStylesheetUrl($stylesheetUrl);
            if (! empty($requestedVariants)) {
                FontsGoogleLocalRegistry::storeValidatedVariants($discovery['key'], $requestedVariants, 'remote');
            }
        }

        return $content;
    }

    /**
     * Discover and replace Google Fonts stylesheet references in HTML, CSS or JS.
     * Unknown references remain untouched until a complete local cache exists.
     *
     * @param string $content
     * @param string $context
     *
     * @return string
     */
    public static function alterContent($content, $context = 'html')
    {
        $content = self::discoverContent($content, $context);

        if (! is_string($content)
            || $content === ''
            || stripos($content, 'fonts.googleapis.com') === false
            || ! self::isEnabled()
            || self::preventRuntimeChange()
        ) {
            return $content;
        }

        $references = FontsGoogleLocalUrl::extractStylesheetReferences($content, $context === 'html');
        if (empty($references)) {
            return $content;
        }

        $userAgent = self::getCurrentUserAgent();
        $requestPath = self::getCurrentRequestPath();

        foreach (array_reverse($references) as $reference) {
            $stylesheetUrl = $reference['url'];

            if (apply_filters('wpacu_google_fonts_local_should_skip_url', false, $stylesheetUrl, $context)) {
                if (isset($_GET['wpacu_debug'])) {
                    \WpAssetCleanUp\DebugOptimizationDetails::recordDebug('css', (object)array('src' => $stylesheetUrl), 'Local Google Fonts', 'Skipped',
                        __('Excluded from local hosting and automatic processing by a configured exclusion.', 'wp-asset-clean-up'));
                }
                continue;
            }

            $discovery = FontsGoogleLocalRegistry::discover($stylesheetUrl, $userAgent, $requestPath, 'php');
            $key = isset($discovery['key']) ? $discovery['key'] : '';
            $entry = $key !== '' ? FontsGoogleLocalRegistry::getEntry($key) : array();

            $localCssUrl = FontsGoogleLocalRegistry::getReadyLocalCssUrl($stylesheetUrl);

            // A ready registry row whose file was removed externally must recover
            // to pending once, not silently remain unusable forever.
            if ($localCssUrl === '' && ! empty($entry) && $entry['status'] === 'ready') {
                FontsGoogleLocalRegistry::markPending($key);
                $entry = FontsGoogleLocalRegistry::getEntry($key);
            }

            // Re-notify once per PHP request while an entry remains eligible.
            // This repairs a queue dispatch that was lost, blocked or cleared
            // without adding a browser AJAX request on every page view.
            if ($key !== ''
                && self::entryNeedsAutomaticProcessing($entry)
                && empty(self::$processingNotifications[$key])
            ) {
                self::$processingNotifications[$key] = true;

                do_action(
                    'wpacu_google_fonts_local_stylesheet_discovered',
                    $key,
                    $stylesheetUrl,
                    $context,
                    $discovery
                );
            }

            if ($localCssUrl !== '') {
                $content = FontsGoogleLocalUrl::replaceReference($content, $reference, $localCssUrl);
                if (isset($_GET['wpacu_debug'])) {
                    \WpAssetCleanUp\DebugOptimizationDetails::recordDebug('css', (object)array('src' => $stylesheetUrl), 'Local Google Fonts', 'Cached',
                        sprintf(__('The Google Fonts stylesheet was replaced with its ready local cache URL: %s', 'wp-asset-clean-up'), $localCssUrl));
                }
            } elseif (!empty($entry['last_error']) && isset($entry['status']) && $entry['status'] === 'error') {
                if (isset($_GET['wpacu_debug'])) {
                    \WpAssetCleanUp\DebugOptimizationDetails::recordDebug('css', (object)array('src' => $stylesheetUrl), 'Local Google Fonts', 'Failed',
                        sprintf(__('The last local-hosting attempt failed: %s The original Google Fonts URL is retained.', 'wp-asset-clean-up'), $entry['last_error']));
                }
            } else {
                if (isset($_GET['wpacu_debug'])) {
                    \WpAssetCleanUp\DebugOptimizationDetails::recordDebug('css', (object)array('src' => $stylesheetUrl), 'Local Google Fonts', 'Skipped',
                        __('A usable local cache file is not ready yet. The original Google Fonts URL is retained.', 'wp-asset-clean-up'));
                }
            }
        }

        return $content;
    }

    /**
     * Convert a manually configured remote font preload to its local file when
     * the file is already part of a ready manifest.
     *
     * @param string $fontUrl
     *
     * @return string
     */
    public static function mapReadyFontFileUrl($fontUrl)
    {
        if (! self::isEnabled() || ! class_exists(__NAMESPACE__ . '\\FontsGoogleLocalCache')) {
            return $fontUrl;
        }

        $localUrl = FontsGoogleLocalCache::getLocalFontUrl($fontUrl);
        return $localUrl !== '' ? $localUrl : $fontUrl;
    }

    /**
     * @return string
     */
    public static function getAssetOptimizationCacheSuffix()
    {
        if (! self::isEnabled()) {
            return '';
        }

        return '_gfl_' . FontsGoogleLocalRegistry::getReadyCacheVersion();
    }

    /**
     * @return string
     */
    public static function getCurrentUserAgent()
    {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
            ? $_SERVER['HTTP_USER_AGENT']
            : '';

        $userAgent = preg_replace('/[\x00-\x1f\x7f]+/', ' ', $userAgent);
        return substr(trim((string) $userAgent), 0, 500);
    }

    /**
     * @return string
     */
    public static function getCurrentRequestPath()
    {
        return isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
            ? $_SERVER['REQUEST_URI']
            : '';
    }

    /**
     * @param array $entry
     *
     * @return bool
     */
    private static function entryNeedsAutomaticProcessing($entry)
    {
        if (empty($entry) || empty($entry['status'])) {
            return false;
        }

        $now = self::now();

        if ($entry['status'] === 'pending') {
            return true;
        }

        if ($entry['status'] === 'error') {
            $nextRetryAt = isset($entry['next_retry_at']) ? (int) $entry['next_retry_at'] : 0;
            return $nextRetryAt === 0 || $nextRetryAt <= $now;
        }

        if ($entry['status'] === 'processing') {
            $processingAt = isset($entry['processing_at']) ? (int) $entry['processing_at'] : 0;
            return $processingAt === 0
                || $processingAt <= ($now - FontsGoogleLocalRegistry::PROCESSING_STALE_AFTER);
        }

        return false;
    }

    /**
     * Return the delay until an error retry or interrupted-processing recovery
     * becomes eligible. This keeps delayed work alive when urgent pending work
     * temporarily brings the single queue event forward.
     *
     * @return int
     */
    private static function getNextDeferredAutomaticDelay()
    {
        $now = self::now();
        $nextTimestamp = 0;

        foreach (FontsGoogleLocalRegistry::getEntries() as $entry) {
            if (! is_array($entry) || empty($entry['status'])) {
                continue;
            }

            $candidateTimestamp = 0;

            if ($entry['status'] === 'error') {
                $retryAt = isset($entry['next_retry_at']) ? (int) $entry['next_retry_at'] : 0;
                if ($retryAt > $now) {
                    $candidateTimestamp = $retryAt;
                }
            } elseif ($entry['status'] === 'processing') {
                $processingAt = isset($entry['processing_at']) ? (int) $entry['processing_at'] : 0;
                $staleAt = $processingAt > 0
                    ? $processingAt + FontsGoogleLocalRegistry::PROCESSING_STALE_AFTER
                    : 0;

                if ($staleAt > $now) {
                    $candidateTimestamp = $staleAt;
                }
            }

            if ($candidateTimestamp > 0
                && ($nextTimestamp === 0 || $candidateTimestamp < $nextTimestamp)
            ) {
                $nextTimestamp = $candidateTimestamp;
            }
        }

        return $nextTimestamp > $now ? $nextTimestamp - $now : 0;
    }

    /**
     * @param int $attempts
     *
     * @return int
     */
    private static function getAutomaticRetryDelay($attempts)
    {
        $attempts = max(0, min(8, (int) $attempts));
        return min(86400, 300 * (int) pow(2, $attempts));
    }

    /**
     * @return string
     */
    private static function acquireAutomaticLock()
    {
        $now = self::now();
        $current = get_option(self::AUTOMATIC_LOCK_OPTION, false);

        if ($current !== false) {
            $createdAt = is_array($current) && isset($current['created_at'])
                ? (int) $current['created_at']
                : (int) $current;

            if ($createdAt > ($now - self::AUTOMATIC_LOCK_TTL)) {
                return '';
            }

            delete_option(self::AUTOMATIC_LOCK_OPTION);
        }

        $token = hash('sha256', $now . '|' . uniqid('', true) . '|' . mt_rand());
        $lock = array('created_at' => $now, 'token' => $token);

        return add_option(self::AUTOMATIC_LOCK_OPTION, $lock, '', 'no') ? $token : '';
    }

    /**
     * @param string $token
     *
     * @return void
     */
    private static function releaseAutomaticLock($token)
    {
        $current = get_option(self::AUTOMATIC_LOCK_OPTION, false);

        if (is_array($current)
            && isset($current['token'])
            && hash_equals((string) $current['token'], (string) $token)
        ) {
            delete_option(self::AUTOMATIC_LOCK_OPTION);
        }
    }

    /**
     * @return int
     */
    private static function now()
    {
        return (int) apply_filters('wpacu_google_fonts_local_now', time());
    }

    /**
     * @return bool
     */
    private static function preventRuntimeChange()
    {
        if (class_exists(__NAMESPACE__ . '\\OptimizeCommon')
            && is_callable(array(__NAMESPACE__ . '\\OptimizeCommon', 'preventAnyFrontendOptimization'))
        ) {
            return (bool) OptimizeCommon::preventAnyFrontendOptimization();
        }

        return false;
    }
}
