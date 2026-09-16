<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

/**
 * A bounded, non-autoloaded registry of Google Fonts stylesheet configurations.
 */
class FontsGoogleLocalRegistry
{
    /** @var array|null */
    private static $runtimeData = null;

    /** @var string|null */
    private static $readyCacheVersion = null;

    /** @var int|null */
    private static $runtimeBlogId = null;

    const OPTION_NAME = 'wpassetcleanup_google_fonts_local_registry';
    const VERSION = 1;
    const MAX_ITEMS = 200;
    const MAX_PATH_SAMPLES = 10;
    const SEEN_WRITE_INTERVAL = 21600; // six hours
    const PROCESSING_STALE_AFTER = 900; // recover an interrupted process after 15 minutes

    /**
     * @return array
     */
    public static function getEntries()
    {
        $data = self::getData();
        return $data['items'];
    }

    /**
     * @param string $key
     *
     * @return array
     */
    public static function getEntry($key)
    {
        $entries = self::getEntries();
        return isset($entries[$key]) && is_array($entries[$key]) ? $entries[$key] : array();
    }

    /** @return array */
    public static function findByLocalCssUrl($localCssUrl)
    {
        if (! is_string($localCssUrl) || trim($localCssUrl) === '') {
            return array();
        }

        $needle = html_entity_decode(trim($localCssUrl), ENT_QUOTES, 'UTF-8');
        foreach (self::getEntries() as $entry) {
            if (! empty($entry['local_css_url'])
                && hash_equals((string) $entry['local_css_url'], $needle)
            ) {
                return $entry;
            }
        }
        return array();
    }

    /**
     * @param string $stylesheetUrl
     * @param string $userAgent
     * @param string $requestPath
     * @param string $source
     *
     * @return array
     */
    public static function discover($stylesheetUrl, $userAgent = '', $requestPath = '', $source = 'php')
    {
        $stylesheetUrl = FontsGoogleLocalUrl::canonicalizeStylesheetUrl($stylesheetUrl);

        if ($stylesheetUrl === '') {
            return array(
                'key'           => '',
                'created'       => false,
                'changed'       => false,
                'limit_reached' => false,
            );
        }

        $data = self::getData();
        $key = FontsGoogleLocalUrl::fingerprint($stylesheetUrl);
        $now = self::now();
        $path = self::sanitizePath($requestPath);
        $userAgent = self::sanitizeSingleLine($userAgent, 500);
        $source = in_array($source, array('php', 'browser', 'admin'), true) ? $source : 'php';

        if (! isset($data['items'][$key])) {
            if (count($data['items']) >= self::MAX_ITEMS) {
                return array(
                    'key'           => '',
                    'created'       => false,
                    'changed'       => false,
                    'limit_reached' => true,
                );
            }

            $paths = array();
            if ($path !== '') {
                $paths[] = $path;
            }

            $data['items'][$key] = array(
                'url'             => $stylesheetUrl,
                'status'          => 'pending',
                'source'          => $source,
                'user_agent'      => $userAgent,
                'paths'           => $paths,
                'first_seen'      => $now,
                'last_seen'       => $now,
                'attempts'        => 0,
                'processing_at'   => 0,
                'processed_at'    => 0,
                'next_retry_at'   => 0,
                'last_error'      => '',
                'local_css_url'   => '',
                'local_css_path'  => '',
                'manifest_path'   => '',
                'font_count'      => 0,
                'total_bytes'     => 0,
                'validated_variants' => array(),
                'variant_sources'    => array(),
                'variants_updated_at'=> 0,
            );

            self::saveData($data);

            return array(
                'key'           => $key,
                'created'       => true,
                'changed'       => true,
                'limit_reached' => false,
            );
        }

        $entry = self::normalizeEntry($data['items'][$key], $stylesheetUrl);
        $changed = false;

        if ($entry['user_agent'] === '' && $userAgent !== '') {
            $entry['user_agent'] = $userAgent;
            $changed = true;
        }

        if ($entry['source'] === '' && $source !== '') {
            $entry['source'] = $source;
            $changed = true;
        }

        if ($path !== '' && ! in_array($path, $entry['paths'], true) && count($entry['paths']) < self::MAX_PATH_SAMPLES) {
            $entry['paths'][] = $path;
            $changed = true;
        }

        if (($now - (int) $entry['last_seen']) >= self::SEEN_WRITE_INTERVAL) {
            $entry['last_seen'] = $now;
            $changed = true;
        }

        if ($changed) {
            $data['items'][$key] = $entry;
            self::saveData($data);
        }

        return array(
            'key'           => $key,
            'created'       => false,
            'changed'       => $changed,
            'limit_reached' => false,
        );
    }

    /**
     * @param string $stylesheetUrl
     *
     * @return string
     */
    public static function getReadyLocalCssUrl($stylesheetUrl)
    {
        $stylesheetUrl = FontsGoogleLocalUrl::canonicalizeStylesheetUrl($stylesheetUrl);
        if ($stylesheetUrl === '') {
            return '';
        }

        $key = FontsGoogleLocalUrl::fingerprint($stylesheetUrl);
        $entry = self::getEntry($key);

        if (empty($entry)
            || $entry['status'] !== 'ready'
        ) {
            return '';
        }

        if (empty($entry['local_css_url'])
            || empty($entry['local_css_path'])
            || ! is_file($entry['local_css_path'])
            || (class_exists(__NAMESPACE__ . '\\FontsGoogleLocalCache')
                && ! FontsGoogleLocalCache::isReadyEntryUsable($entry))
        ) {
            self::markPending($key);
            return '';
        }

        return (string) $entry['local_css_url'];
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public static function markPending($key)
    {
        return self::mutateEntry($key, static function ($entry) {
            $entry['status'] = 'pending';
            $entry['processing_at'] = 0;
            $entry['next_retry_at'] = 0;
            $entry['last_error'] = '';
            return $entry;
        });
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public static function markProcessing($key)
    {
        return self::mutateEntry($key, static function ($entry) {
            $entry['status'] = 'processing';
            $entry['processing_at'] = self::now();
            $entry['attempts'] = max(0, (int) $entry['attempts']) + 1;
            $entry['last_error'] = '';
            return $entry;
        });
    }

    /**
     * @param string $key
     * @param array  $metadata
     *
     * @return bool
     */
    public static function markReady($key, $metadata)
    {
        if (! is_array($metadata)) {
            $metadata = array();
        }

        return self::mutateEntry($key, static function ($entry) use ($metadata) {
            $entry['status'] = 'ready';
            $entry['processing_at'] = 0;
            $entry['next_retry_at'] = 0;
            $entry['last_error'] = '';
            $entry['processed_at'] = isset($metadata['processed_at']) ? max(0, (int) $metadata['processed_at']) : self::now();
            $entry['local_css_url'] = isset($metadata['local_css_url']) ? (string) $metadata['local_css_url'] : '';
            $entry['local_css_path'] = isset($metadata['local_css_path']) ? (string) $metadata['local_css_path'] : '';
            $entry['manifest_path'] = isset($metadata['manifest_path']) ? (string) $metadata['manifest_path'] : '';
            $entry['font_count'] = isset($metadata['font_count']) ? max(0, (int) $metadata['font_count']) : 0;
            $entry['total_bytes'] = isset($metadata['total_bytes']) ? max(0, (int) $metadata['total_bytes']) : 0;
            if (! empty($metadata['validated_variants']) && is_array($metadata['validated_variants'])) {
                $entry['validated_variants'] = FontsGoogleVariantInventory::normalize($metadata['validated_variants']);
                $entry['variant_sources'] = array_values(array_unique(array_merge($entry['variant_sources'], array('local'))));
                sort($entry['variant_sources']);
                $entry['variants_updated_at'] = self::now();
            }
            return $entry;
        });
    }

    /**
     * Persist validated variant metadata without changing local-copy status.
     *
     * @param string $key
     * @param array  $variants
     * @param string $provenance remote|local
     *
     * @return bool
     */
    public static function storeValidatedVariants($key, $variants, $provenance)
    {
        if (! is_string($key) || ! preg_match('/^[a-f0-9]{64}$/', $key)
            || ! in_array($provenance, array('remote', 'local'), true)
        ) {
            return false;
        }

        $variants = FontsGoogleVariantInventory::normalize($variants);
        if (empty($variants)) {
            return false;
        }

        $existing = self::getEntry($key);
        if (empty($existing)) {
            return false;
        }
        $merged = FontsGoogleVariantInventory::normalize(array_merge($existing['validated_variants'], $variants));
        if ($merged === $existing['validated_variants'] && in_array($provenance, $existing['variant_sources'], true)) {
            return true;
        }

        return self::mutateEntry($key, static function ($entry) use ($variants, $provenance) {
            $merged = FontsGoogleVariantInventory::normalize(array_merge($entry['validated_variants'], $variants));
            $sources = array_values(array_unique(array_merge($entry['variant_sources'], array($provenance))));
            sort($sources);

            $entry['validated_variants'] = $merged;
            $entry['variant_sources'] = $sources;
            $entry['variants_updated_at'] = self::now();
            return $entry;
        });
    }

    /**
     * @param string $key
     * @param string $error
     * @param int    $retryAfter
     *
     * @return bool
     */
    public static function markFailed($key, $error, $retryAfter = 0)
    {
        $error = self::sanitizeSingleLine($error, 1000);
        $retryAfter = max(0, (int) $retryAfter);

        return self::mutateEntry($key, static function ($entry) use ($error, $retryAfter) {
            $entry['status'] = 'error';
            $entry['processing_at'] = 0;
            $entry['last_error'] = $error;
            $entry['next_retry_at'] = $retryAfter > 0 ? self::now() + $retryAfter : 0;
            return $entry;
        });
    }

    /**
     * Keep the last complete local transaction online when a forced refresh
     * fails, while retaining the error and a bounded retry timestamp.
     *
     * @param string $key
     * @param array  $previousEntry
     * @param string $error
     * @param int    $retryAfter
     *
     * @return bool
     */
    public static function markRefreshFailed($key, $previousEntry, $error, $retryAfter = 0)
    {
        $previousEntry = self::normalizeEntry($previousEntry);
        $error = self::sanitizeSingleLine($error, 1000);
        $retryAfter = max(0, (int) $retryAfter);

        return self::mutateEntry($key, static function ($entry) use ($previousEntry, $error, $retryAfter) {
            $entry['status'] = 'ready';
            $entry['processing_at'] = 0;
            $entry['next_retry_at'] = $retryAfter > 0 ? self::now() + $retryAfter : 0;
            $entry['last_error'] = $error;

            foreach (array('processed_at', 'local_css_url', 'local_css_path', 'manifest_path', 'font_count', 'total_bytes') as $metadataKey) {
                $entry[$metadataKey] = $previousEntry[$metadataKey];
            }

            return $entry;
        });
    }

    /**
     * @param int $limit
     * @param int $refreshDays
     *
     * @return array
     */
    public static function getProcessCandidates($limit = 10, $refreshDays = 0)
    {
        $limit = max(1, min(50, (int) $limit));
        $refreshDays = max(0, (int) $refreshDays);
        $now = self::now();
        $refreshBefore = $refreshDays > 0 ? $now - ($refreshDays * 86400) : 0;
        $candidates = array();

        foreach (self::getEntries() as $key => $entry) {
            $entry = self::normalizeEntry($entry);
            $status = $entry['status'];
            $eligible = false;

            if ($status === 'pending') {
                $eligible = true;
            } elseif ($status === 'error' && ((int) $entry['next_retry_at'] === 0 || (int) $entry['next_retry_at'] <= $now)) {
                $eligible = true;
            } elseif ($status === 'processing' && ((int) $entry['processing_at'] === 0 || (int) $entry['processing_at'] <= ($now - self::PROCESSING_STALE_AFTER))) {
                $eligible = true;
            } elseif ($status === 'ready'
                && $refreshBefore > 0
                && (int) $entry['processed_at'] > 0
                && (int) $entry['processed_at'] <= $refreshBefore
                && ((int) $entry['next_retry_at'] === 0 || (int) $entry['next_retry_at'] <= $now)
            ) {
                $eligible = true;
            }

            if (! $eligible) {
                continue;
            }

            $candidates[] = $key;
            if (count($candidates) >= $limit) {
                break;
            }
        }

        return $candidates;
    }

    /**
     * @return array
     */
    public static function getKnownFingerprints()
    {
        return array_keys(self::getEntries());
    }

    /**
     * A compact fingerprint of complete local transactions. CSS/JS optimization
     * cache keys include it so a previously unchanged source is reconsidered as
     * soon as one of its Google Fonts configurations becomes ready.
     *
     * @return string
     */
    public static function getReadyCacheVersion()
    {
        self::syncRuntimeBlog();

        if (self::$readyCacheVersion !== null) {
            return self::$readyCacheVersion;
        }

        $readyItems = array();

        foreach (self::getEntries() as $key => $entry) {
            if (! is_array($entry)
                || ! isset($entry['status'])
                || $entry['status'] !== 'ready'
                || empty($entry['local_css_url'])
                || empty($entry['local_css_path'])
                || ! is_file($entry['local_css_path'])
            ) {
                continue;
            }

            $readyItems[$key] = (string) $entry['local_css_url'];
        }

        if (empty($readyItems)) {
            self::$readyCacheVersion = 'none';
            return self::$readyCacheVersion;
        }

        ksort($readyItems, SORT_STRING);
        $signature = '';
        foreach ($readyItems as $key => $localCssUrl) {
            $signature .= $key . '=' . $localCssUrl . "\n";
        }
        self::$readyCacheVersion = substr(hash('sha256', $signature), 0, 16);
        return self::$readyCacheVersion;
    }

    /**
     * @return array
     */
    public static function getSummary()
    {
        $summary = array(
            'total'       => 0,
            'pending'     => 0,
            'processing'  => 0,
            'ready'       => 0,
            'error'       => 0,
            'font_count'  => 0,
            'total_bytes' => 0,
        );

        foreach (self::getEntries() as $entry) {
            $entry = self::normalizeEntry($entry);
            $summary['total']++;

            if (isset($summary[$entry['status']])) {
                $summary[$entry['status']]++;
            }

            if ($entry['status'] === 'ready') {
                $summary['font_count'] += max(0, (int) $entry['font_count']);
                $summary['total_bytes'] += max(0, (int) $entry['total_bytes']);
            }
        }

        return $summary;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public static function remove($key)
    {
        $data = self::getData();
        if (! isset($data['items'][$key])) {
            return false;
        }

        unset($data['items'][$key]);
        self::saveData($data);
        return true;
    }

    /**
     * @return void
     */
    public static function clear()
    {
        self::syncRuntimeBlog();
        self::$runtimeData = null;
        self::$readyCacheVersion = null;
        delete_option(self::OPTION_NAME);
    }

    /**
     * @param string   $key
     * @param callable $callback
     *
     * @return bool
     */
    private static function mutateEntry($key, $callback)
    {
        $data = self::getData();

        if (! isset($data['items'][$key]) || ! is_callable($callback)) {
            return false;
        }

        $entry = call_user_func($callback, self::normalizeEntry($data['items'][$key]));
        if (! is_array($entry)) {
            return false;
        }

        $data['items'][$key] = self::normalizeEntry($entry);
        self::saveData($data);
        return true;
    }

    /**
     * @return array
     */
    private static function getData()
    {
        self::syncRuntimeBlog();

        if (self::$runtimeData !== null) {
            return self::$runtimeData;
        }

        $data = get_option(self::OPTION_NAME, null);

        if (! is_array($data)) {
            self::$runtimeData = array('version' => self::VERSION, 'items' => array());
            return self::$runtimeData;
        }

        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
        $normalizedItems = array();

        foreach (array_slice($items, 0, self::MAX_ITEMS, true) as $key => $entry) {
            if (! is_string($key) || ! is_array($entry)) {
                continue;
            }

            $normalizedEntry = self::normalizeEntry($entry);
            if ($normalizedEntry['url'] === '') {
                continue;
            }

            $normalizedItems[$key] = $normalizedEntry;
        }

        self::$runtimeData = array('version' => self::VERSION, 'items' => $normalizedItems);
        return self::$runtimeData;
    }

    /**
     * @param array $data
     *
     * @return void
     */
    private static function saveData($data)
    {
        self::syncRuntimeBlog();
        $data = is_array($data) ? $data : array();
        $data['version'] = self::VERSION;
        $data['items'] = isset($data['items']) && is_array($data['items'])
            ? array_slice($data['items'], 0, self::MAX_ITEMS, true)
            : array();

        self::$runtimeData = $data;
        self::$readyCacheVersion = null;

        if (get_option(self::OPTION_NAME, null) === null
            && add_option(self::OPTION_NAME, $data, '', 'no')
        ) {
            return;
        }

        update_option(self::OPTION_NAME, $data, 'no');
    }

    /**
     * Keep request-local caches scoped to the current site in Multisite.
     *
     * @return void
     */
    private static function syncRuntimeBlog()
    {
        $currentBlogId = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;

        if (self::$runtimeBlogId === null) {
            self::$runtimeBlogId = $currentBlogId;
            return;
        }

        if (self::$runtimeBlogId !== $currentBlogId) {
            self::$runtimeData = null;
            self::$readyCacheVersion = null;
            self::$runtimeBlogId = $currentBlogId;
        }
    }

    /**
     * @param array  $entry
     * @param string $fallbackUrl
     *
     * @return array
     */
    private static function normalizeEntry($entry, $fallbackUrl = '')
    {
        $defaults = array(
            'url'            => $fallbackUrl,
            'status'         => 'pending',
            'source'         => 'php',
            'user_agent'     => '',
            'paths'          => array(),
            'first_seen'     => 0,
            'last_seen'      => 0,
            'attempts'       => 0,
            'processing_at'  => 0,
            'processed_at'   => 0,
            'next_retry_at'  => 0,
            'last_error'     => '',
            'local_css_url'  => '',
            'local_css_path' => '',
            'manifest_path'  => '',
            'font_count'     => 0,
            'total_bytes'    => 0,
            'validated_variants' => array(),
            'variant_sources'    => array(),
            'variants_updated_at'=> 0,
        );

        $entry = is_array($entry) ? array_merge($defaults, $entry) : $defaults;
        $entry['url'] = FontsGoogleLocalUrl::canonicalizeStylesheetUrl($entry['url']);
        $entry['status'] = in_array($entry['status'], array('pending', 'processing', 'ready', 'error'), true) ? $entry['status'] : 'pending';
        $entry['source'] = in_array($entry['source'], array('php', 'browser', 'admin'), true) ? $entry['source'] : 'php';
        $entry['user_agent'] = self::sanitizeSingleLine($entry['user_agent'], 500);
        $entry['last_error'] = self::sanitizeSingleLine($entry['last_error'], 1000);

        $paths = array();
        foreach ((array) $entry['paths'] as $path) {
            $path = self::sanitizePath($path);
            if ($path !== '' && ! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
            if (count($paths) >= self::MAX_PATH_SAMPLES) {
                break;
            }
        }
        $entry['paths'] = $paths;

        foreach (array('first_seen', 'last_seen', 'attempts', 'processing_at', 'processed_at', 'next_retry_at', 'font_count', 'total_bytes', 'variants_updated_at') as $integerKey) {
            $entry[$integerKey] = max(0, (int) $entry[$integerKey]);
        }

        $entry['validated_variants'] = FontsGoogleVariantInventory::normalize($entry['validated_variants']);
        $variantSources = array();
        foreach ((array) $entry['variant_sources'] as $variantSource) {
            if (in_array($variantSource, array('local', 'remote'), true)) {
                $variantSources[$variantSource] = true;
            }
        }
        $entry['variant_sources'] = array_keys($variantSources);
        sort($entry['variant_sources']);

        foreach (array('local_css_url', 'local_css_path', 'manifest_path') as $stringKey) {
            $entry[$stringKey] = is_string($entry[$stringKey]) ? $entry[$stringKey] : '';
        }

        return $entry;
    }

    /**
     * @param string $requestPath
     *
     * @return string
     */
    private static function sanitizePath($requestPath)
    {
        if (! is_string($requestPath) || $requestPath === '') {
            return '';
        }

        $requestPath = html_entity_decode(trim($requestPath), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parsedPath = parse_url($requestPath, PHP_URL_PATH);

        if (! is_string($parsedPath) || $parsedPath === '') {
            return '';
        }

        $parsedPath = preg_replace('/[\x00-\x1f\x7f]/', '', $parsedPath);
        if ($parsedPath === '') {
            return '';
        }

        if (strpos($parsedPath, '/') !== 0) {
            $parsedPath = '/' . $parsedPath;
        }

        return substr($parsedPath, 0, 500);
    }

    /**
     * @param mixed $value
     * @param int   $maxLength
     *
     * @return string
     */
    private static function sanitizeSingleLine($value, $maxLength)
    {
        if (! is_string($value)) {
            return '';
        }

        $value = preg_replace('/[\x00-\x1f\x7f]+/', ' ', $value);
        $value = trim(preg_replace('/\s+/', ' ', $value));
        return substr($value, 0, max(0, (int) $maxLength));
    }

    /**
     * @return int
     */
    private static function now()
    {
        return (int) apply_filters('wpacu_google_fonts_local_now', time());
    }
}
