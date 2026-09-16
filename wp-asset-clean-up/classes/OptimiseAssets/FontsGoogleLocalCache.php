<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

/**
 * Downloads and atomically publishes complete local Google Fonts transactions.
 */
class FontsGoogleLocalCache
{
    /** @var array|null */
    private static $localFontUrlMap = null;

    const MAX_CSS_BYTES = 262144;
    const MAX_FONT_BYTES = 5242880;
    const MAX_FONT_FILES = 60;
    const MAX_TOTAL_FONT_BYTES = 31457280;

    const MANIFEST_VERSION = 3;
    const CACHE_KEY_LENGTH = 20;
    const CSS_HASH_LENGTH = 12;
    const MAX_DESCRIPTOR_LENGTH = 48;
    const MAX_DESCRIPTOR_FAMILIES = 3;
    const MAX_DESCRIPTOR_WEIGHTS = 5;

    /**
     * @param string $key
     * @param bool   $force
     * @param int    $retryAfter
     *
     * @return array
     */
    public static function process($key, $force = false, $retryAfter = 0)
    {
        $entry = FontsGoogleLocalRegistry::getEntry($key);

        if (empty($entry) || empty($entry['url'])) {
            return self::failureResult($key, 'The Google Fonts registry item no longer exists.');
        }

        $shouldMinifyCss = self::shouldMinifyCss();
        $hasReadyCopy = isset($entry['status'])
            && $entry['status'] === 'ready'
            && ! empty($entry['local_css_path'])
            && is_file($entry['local_css_path']);

        if (! $force && $hasReadyCopy && self::isReadyEntryCurrent($entry, $shouldMinifyCss)) {
            return array('success' => true, 'key' => $key, 'metadata' => $entry, 'error' => '');
        }

        // Treat an old cache format or a changed minification preference as a
        // safe refresh. If regeneration fails, the last complete copy remains live.
        if (! $force && $hasReadyCopy) {
            $force = true;
        }

        FontsGoogleLocalRegistry::markProcessing($key);
        $createdFiles = array();

        try {
            self::ensureCacheDirectories();

            $stylesheetUrl = FontsGoogleLocalUrl::canonicalizeStylesheetUrl($entry['url']);
            if ($stylesheetUrl === '') {
                throw new \RuntimeException('The Google Fonts stylesheet URL is not allowed.');
            }

            $stylesheetUrl = self::getConfiguredStylesheetUrl($stylesheetUrl);

            $userAgent = ! empty($entry['user_agent'])
                ? $entry['user_agent']
                : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

            $css = self::fetchRemoteBody($stylesheetUrl, self::MAX_CSS_BYTES, $userAgent, 'stylesheet');
            self::assertAllRemoteCssUrlsAllowed($css);
            $validatedVariants = FontsGoogleVariantInventory::extractFromCss($css);

            $fontReferences = FontsGoogleLocalUrl::extractFontFileReferences($css);
            if (empty($fontReferences)) {
                throw new \RuntimeException('The Google stylesheet did not declare any valid fonts.gstatic.com font files.');
            }

            $fontUrls = array();
            foreach ($fontReferences as $fontReference) {
                $fontUrls[$fontReference['url']] = $fontReference['url'];
            }

            if (count($fontUrls) > self::MAX_FONT_FILES) {
                throw new \RuntimeException('The Google stylesheet declares more than ' . self::MAX_FONT_FILES . ' font files.');
            }

            $fontFilesByUrl = array();
            $fontManifest = array();
            $totalBytes = 0;

            foreach ($fontUrls as $fontUrl) {
                $fontBody = self::fetchRemoteBody($fontUrl, self::MAX_FONT_BYTES, $userAgent, 'font');
                $fontBytes = strlen($fontBody);
                $totalBytes += $fontBytes;

                if ($totalBytes > self::MAX_TOTAL_FONT_BYTES) {
                    throw new \RuntimeException('The Google stylesheet exceeds the total local font byte limit.');
                }

                $extension = self::detectFontExtension($fontBody);
                if ($extension === '') {
                    throw new \RuntimeException('A downloaded Google font has an invalid or unsupported file signature.');
                }

                $filename = hash('sha256', $fontBody) . '.' . $extension;
                $fontPath = self::getCacheRootPath() . 'files/' . $filename;

                if (! is_file($fontPath)) {
                    if (! self::writeAtomic($fontPath, $fontBody)) {
                        throw new \RuntimeException('The local Google font file could not be written.');
                    }
                    $createdFiles[] = $fontPath;
                }

                $fontFilesByUrl[$fontUrl] = $filename;
                $fontManifest[] = array(
                    'source_url' => $fontUrl,
                    'file'       => $filename,
                    'bytes'      => $fontBytes,
                    'sha256'     => hash('sha256', $fontBody),
                );
            }

            $rewrittenCss = $css;
            foreach ($fontReferences as $fontReference) {
                if (! isset($fontFilesByUrl[$fontReference['url']])) {
                    throw new \RuntimeException('A declared Google font file was not downloaded.');
                }

                $rewrittenCss = str_replace(
                    $fontReference['raw'],
                    '../files/' . $fontFilesByUrl[$fontReference['url']],
                    $rewrittenCss
                );
            }

            if (stripos($rewrittenCss, 'fonts.gstatic.com') !== false) {
                throw new \RuntimeException('The local stylesheet still contains a remote Google font reference.');
            }

            $descriptor = self::buildCssDescriptor($css, $stylesheetUrl);
            $minified = false;

            if ($shouldMinifyCss) {
                $minifiedCss = self::minifyCss($rewrittenCss);

                if (is_string($minifiedCss) && trim($minifiedCss) !== '') {
                    $rewrittenCss = trim($minifiedCss);
                    $minified = true;
                }
            }

            $rewrittenCss = self::addSourceComment($rewrittenCss, $stylesheetUrl);
            $cssHash = substr(hash('sha256', $rewrittenCss), 0, self::CSS_HASH_LENGTH);
            $filenameStem = substr($key, 0, self::CACHE_KEY_LENGTH) . '-' . $cssHash . '-' . $descriptor;
            $cssFilename = $filenameStem . ($minified ? '.min.css' : '.css');
            $manifestFilename = $filenameStem . '.json';
            $cssPath = self::getCacheRootPath() . 'css/' . $cssFilename;
            $manifestPath = self::getCacheRootPath() . 'manifests/' . $manifestFilename;

            $manifest = array(
                'version'      => self::MANIFEST_VERSION,
                'source_url'   => $stylesheetUrl,
                'user_agent'   => $userAgent,
                'generated_at' => self::now(),
                'css_file'     => $cssFilename,
                'descriptor'   => $descriptor,
                'minified'     => $minified,
                'transformation_signature' => self::getTransformationSignature(),
                'font_count'   => count($fontManifest),
                'total_bytes'  => $totalBytes,
                'fonts'        => $fontManifest,
            );

            $jsonFlags = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0;
            $manifestJson = wp_json_encode($manifest, $jsonFlags);
            if (! is_string($manifestJson) || $manifestJson === '') {
                throw new \RuntimeException('The local Google Fonts manifest could not be encoded.');
            }

            if (! self::writeAtomic($cssPath, $rewrittenCss)) {
                throw new \RuntimeException('The local Google Fonts stylesheet could not be written.');
            }
            $createdFiles[] = $cssPath;

            if (! self::writeAtomic($manifestPath, $manifestJson)) {
                throw new \RuntimeException('The local Google Fonts manifest could not be written.');
            }
            $createdFiles[] = $manifestPath;

            $metadata = array(
                'local_css_url'  => self::getCacheRootUrl() . 'css/' . $cssFilename,
                'local_css_path' => $cssPath,
                'manifest_path'  => $manifestPath,
                'font_count'     => count($fontManifest),
                'total_bytes'    => $totalBytes,
                'processed_at'   => self::now(),
                'validated_variants' => $validatedVariants,
            );

            if (! FontsGoogleLocalRegistry::markReady($key, $metadata)) {
                throw new \RuntimeException('The completed Google Fonts cache could not be published in the registry.');
            }

            self::$localFontUrlMap = null;

            return array('success' => true, 'key' => $key, 'metadata' => $metadata, 'error' => '');
        } catch (\Exception $e) {
            self::removeCreatedFiles($createdFiles);

            if ($force
                && isset($entry['status'])
                && $entry['status'] === 'ready'
                && ! empty($entry['local_css_path'])
                && is_file($entry['local_css_path'])
            ) {
                FontsGoogleLocalRegistry::markRefreshFailed($key, $entry, $e->getMessage(), $retryAfter);
            } else {
                FontsGoogleLocalRegistry::markFailed($key, $e->getMessage(), $retryAfter);
            }

            return self::failureResult($key, $e->getMessage());
        }
    }

    /**
     * Local Google Fonts CSS is a generated href stylesheet. Apply the same
     * global minification intent used for loaded CSS files, while deliberately
     * ignoring request-only admin/page exceptions because this cache is shared.
     *
     * @return bool
     */
    public static function shouldMinifyCss()
    {
        $settings = array();
        $shouldMinify = false;

        if (class_exists('WpAssetCleanUp\\Main')) {
            $mainSettings = \WpAssetCleanUp\Main::instance()->settings;
            $settings = is_array($mainSettings) ? $mainSettings : array();
            $minifyFor = isset($settings['minify_loaded_css_for'])
                ? (string) $settings['minify_loaded_css_for']
                : 'href';

            $shouldMinify = ! empty($settings['minify_loaded_css'])
                && in_array($minifyFor, array('', 'href', 'all'), true);
        }

        return (bool) apply_filters(
            'wpacu_google_fonts_local_should_minify_css',
            $shouldMinify,
            $settings
        );
    }

    /**
     * @param array $entry
     *
     * @return bool
     */
    public static function readyEntryNeedsRefresh($entry)
    {
        if (! self::isReadyEntryUsable($entry)) {
            return true;
        }

        return ! self::isReadyEntryCurrent($entry, self::shouldMinifyCss());
    }

    /**
     * Confirm that a ready transaction can serve its CSS and every declared font.
     * An older manifest format may remain usable while a background refresh runs.
     *
     * @param array $entry
     *
     * @return bool
     */
    public static function isReadyEntryUsable($entry)
    {
        if (! is_array($entry)
            || empty($entry['status'])
            || $entry['status'] !== 'ready'
            || empty($entry['local_css_path'])
            || ! is_file($entry['local_css_path'])
            || empty($entry['manifest_path'])
            || ! is_file($entry['manifest_path'])
        ) {
            return false;
        }

        $manifest = json_decode((string) @file_get_contents($entry['manifest_path']), true);
        if (! is_array($manifest)
            || empty($manifest['css_file'])
            || basename((string) $manifest['css_file']) !== basename((string) $entry['local_css_path'])
            || empty($manifest['fonts'])
            || ! is_array($manifest['fonts'])
        ) {
            return false;
        }

        $filesRoot = self::getCacheRootPath() . 'files/';
        foreach ($manifest['fonts'] as $font) {
            $filename = isset($font['file']) ? (string) $font['file'] : '';
            if ($filename === ''
                || basename($filename) !== $filename
                || ! preg_match('/^[a-f0-9]{64}\.(?:woff2?|otf|ttf)$/', $filename)
                || ! is_file($filesRoot . $filename)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $entry
     * @param bool  $shouldMinifyCss
     *
     * @return bool
     */
    private static function isReadyEntryCurrent($entry, $shouldMinifyCss)
    {
        if (empty($entry['manifest_path']) || ! is_file($entry['manifest_path'])) {
            return false;
        }

        $manifest = json_decode((string) @file_get_contents($entry['manifest_path']), true);

        if (! is_array($manifest)
            || ! isset($manifest['version'])
            || (int) $manifest['version'] !== self::MANIFEST_VERSION
            || ! array_key_exists('minified', $manifest)
            || (bool) $manifest['minified'] !== (bool) $shouldMinifyCss
            || empty($manifest['transformation_signature'])
            || ! hash_equals((string) $manifest['transformation_signature'], self::getTransformationSignature())
            || empty($manifest['css_file'])
            || basename((string) $manifest['css_file']) !== basename((string) $entry['local_css_path'])
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param string $css
     *
     * @return string
     */
    private static function minifyCss($css)
    {
        if (! class_exists(__NAMESPACE__ . '\\MinifyCss')) {
            return '';
        }

        $minifiedCss = MinifyCss::applyMinification($css);
        return is_string($minifiedCss) && trim($minifiedCss) !== ''
            ? trim($minifiedCss)
            : '';
    }

    /**
     * Apply the same Google Fonts URL configuration used by the general CSS
     * optimizer before the remote stylesheet is downloaded into local cache.
     *
     * @param string $stylesheetUrl
     *
     * @return string
     */
    public static function getConfiguredStylesheetUrl($stylesheetUrl)
    {
        if (! is_string($stylesheetUrl)
            || $stylesheetUrl === ''
            || ! class_exists(__NAMESPACE__ . '\\FontsGoogle')
        ) {
            return $stylesheetUrl;
        }

        return FontsGoogle::alterGoogleFontLink($stylesheetUrl, false);
    }

    /**
     * Determine whether a stylesheet URL belongs to the immutable local
     * Google Fonts cache, including Remove Specific derivatives.
     *
     * @param string $stylesheetUrl
     *
     * @return bool
     */
    public static function isManagedStylesheetUrl($stylesheetUrl)
    {
        if (! is_string($stylesheetUrl) || $stylesheetUrl === '') {
            return false;
        }

        $candidate = wp_parse_url(html_entity_decode($stylesheetUrl, ENT_QUOTES));
        $root = wp_parse_url(self::getCacheRootUrl());

        if (! is_array($candidate) || ! is_array($root)) {
            return false;
        }

        foreach (array('scheme', 'host', 'port') as $component) {
            $candidateValue = isset($candidate[$component]) ? strtolower((string) $candidate[$component]) : '';
            $rootValue = isset($root[$component]) ? strtolower((string) $root[$component]) : '';
            if ($candidateValue !== $rootValue) {
                return false;
            }
        }

        $candidatePath = isset($candidate['path']) ? rawurldecode((string) $candidate['path']) : '';
        $rootPath = isset($root['path']) ? trailingslashit(rawurldecode((string) $root['path'])) : '';
        $cssDirectory = $rootPath . 'css/';

        if ($candidatePath === '' || dirname($candidatePath) . '/' !== untrailingslashit($cssDirectory) . '/') {
            return false;
        }

        return (bool) preg_match(
            '/^(?:filtered-[a-f0-9]{20}|[a-f0-9]{20}-[a-f0-9]{12}-[a-z0-9-]+)(?:\.min)?\.css$/',
            basename($candidatePath)
        );
    }

    /**
     * Settings that materially change the generated local stylesheet.
     *
     * @return string
     */
    private static function getTransformationSignature()
    {
        $settings = array();
        if (class_exists('WpAssetCleanUp\\Main')) {
            $mainSettings = \WpAssetCleanUp\Main::instance()->settings;
            $settings = is_array($mainSettings) ? $mainSettings : array();
        }

        return hash('sha256', wp_json_encode(array(
            'google_fonts_display' => isset($settings['google_fonts_display'])
                ? (string) $settings['google_fonts_display']
                : '',
            'google_fonts_display_overwrite' => ! empty($settings['google_fonts_display_overwrite']),
        )));
    }

    /**
     * Build a short human-readable suffix such as inter-400-700 or
     * open-sans-roboto-slab-400-700-italic. It is descriptive only; bounded
     * hashes remain the source of cache uniqueness.
     *
     * @param string $css
     * @param string $stylesheetUrl
     *
     * @return string
     */
    private static function buildCssDescriptor($css, $stylesheetUrl)
    {
        $families = array();
        $weights = array();
        $styles = array();
        $wasTruncated = false;

        if (preg_match_all('/@font-face\s*\{(.*?)\}/is', (string) $css, $blockMatches) && ! empty($blockMatches[1])) {
            foreach ($blockMatches[1] as $block) {
                if (preg_match('/font-family\s*:\s*(?:"([^"]+)"|\'([^\']+)\'|([^;}]+))/i', $block, $familyMatch)) {
                    $family = '';
                    foreach (array(1, 2, 3) as $familyIndex) {
                        if (isset($familyMatch[$familyIndex]) && trim($familyMatch[$familyIndex]) !== '') {
                            $family = self::slugifyDescriptorPart($familyMatch[$familyIndex]);
                            break;
                        }
                    }

                    if ($family !== '' && ! in_array($family, $families, true)) {
                        $families[] = $family;
                    }
                }

                if (preg_match('/font-weight\s*:\s*([^;}]+)/i', $block, $weightMatch)) {
                    $weight = self::normalizeDescriptorWeight($weightMatch[1]);
                    if ($weight !== '' && ! in_array($weight, $weights, true)) {
                        $weights[] = $weight;
                    }
                }

                if (preg_match('/font-style\s*:\s*([^;}]+)/i', $block, $styleMatch)) {
                    $style = strtolower(trim($styleMatch[1], " \t\n\r\0\x0B\"'"));
                    if (in_array($style, array('italic', 'oblique'), true) && ! in_array($style, $styles, true)) {
                        $styles[] = $style;
                    }
                }
            }
        }

        // Defensive fallback for an unusual response without readable
        // @font-face family declarations (e.g. a future endpoint variation).
        if (empty($families)) {
            self::addDescriptorPartsFromStylesheetUrl($stylesheetUrl, $families, $weights);
        }

        if (count($families) > self::MAX_DESCRIPTOR_FAMILIES) {
            $families = array_slice($families, 0, self::MAX_DESCRIPTOR_FAMILIES);
            $wasTruncated = true;
        }

        if (count($weights) > self::MAX_DESCRIPTOR_WEIGHTS) {
            $weights = array_slice($weights, 0, self::MAX_DESCRIPTOR_WEIGHTS);
            $wasTruncated = true;
        }

        $parts = array_merge($families, $weights, $styles);
        if (empty($parts)) {
            $parts = array('google-fonts');
        }

        $descriptor = '';
        foreach ($parts as $part) {
            $candidate = $descriptor === '' ? $part : $descriptor . '-' . $part;
            if (strlen($candidate) > self::MAX_DESCRIPTOR_LENGTH) {
                $wasTruncated = true;
                break;
            }
            $descriptor = $candidate;
        }

        if ($descriptor === '') {
            $descriptor = 'google-fonts';
        }

        if ($wasTruncated && substr($descriptor, -4) !== '-etc') {
            if (strlen($descriptor) + 4 <= self::MAX_DESCRIPTOR_LENGTH) {
                $descriptor .= '-etc';
            } else {
                $descriptor = substr($descriptor, 0, self::MAX_DESCRIPTOR_LENGTH - 4);
                $lastDelimiter = strrpos($descriptor, '-');
                if ($lastDelimiter !== false && $lastDelimiter >= 8) {
                    $descriptor = substr($descriptor, 0, $lastDelimiter);
                }
                $descriptor = rtrim($descriptor, '-') . '-etc';
            }
        }

        return $descriptor;
    }

    /**
     * @param string $stylesheetUrl
     * @param array  $families
     * @param array  $weights
     *
     * @return void
     */
    private static function addDescriptorPartsFromStylesheetUrl($stylesheetUrl, &$families, &$weights)
    {
        $query = parse_url((string) $stylesheetUrl, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return;
        }

        if (! preg_match_all('/(?:^|&)family=([^&]+)/i', $query, $familyMatches) || empty($familyMatches[1])) {
            return;
        }

        foreach ($familyMatches[1] as $encodedFamily) {
            $familyValue = urldecode($encodedFamily);
            $familyParts = explode(':', $familyValue, 2);
            $family = self::slugifyDescriptorPart(str_replace('+', ' ', $familyParts[0]));

            if ($family !== '' && ! in_array($family, $families, true)) {
                $families[] = $family;
            }

            if (! isset($familyParts[1])) {
                continue;
            }

            if (preg_match_all('/(?:^|[^0-9])([1-9]00)(?:[^0-9]|$)/', $familyParts[1], $weightMatches)) {
                foreach ($weightMatches[1] as $weight) {
                    if (! in_array($weight, $weights, true)) {
                        $weights[] = $weight;
                    }
                }
            }
        }
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private static function normalizeDescriptorWeight($value)
    {
        $value = strtolower(trim((string) $value, " \t\n\r\0\x0B\"'"));

        if ($value === 'normal') {
            return '400';
        }
        if ($value === 'bold') {
            return '700';
        }

        $value = preg_replace('/\s+/', '-', $value);
        $value = preg_replace('/[^0-9-]/', '', (string) $value);
        $value = trim((string) preg_replace('/-+/', '-', (string) $value), '-');

        return preg_match('/^[0-9]{1,4}(?:-[0-9]{1,4})?$/', $value) ? $value : '';
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private static function slugifyDescriptorPart($value)
    {
        $value = html_entity_decode(trim((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim(substr((string) $value, 0, 32), '-');
    }

    /**
     * @param string $body
     *
     * @return string
     */
    public static function detectFontExtension($body)
    {
        if (! is_string($body) || strlen($body) < 4) {
            return '';
        }

        $signature = substr($body, 0, 4);

        if ($signature === 'wOF2') {
            return 'woff2';
        }
        if ($signature === 'wOFF') {
            return 'woff';
        }
        if ($signature === 'OTTO') {
            return 'otf';
        }
        if ($signature === "\x00\x01\x00\x00" || $signature === 'true' || $signature === 'typ1') {
            return 'ttf';
        }

        return '';
    }

    /**
     * @return string
     */
    public static function getCacheRootPath()
    {
        $defaultPath = rtrim(WP_CONTENT_DIR, '/\\') . '/cache/asset-cleanup/one/google-fonts/';

        if (class_exists(__NAMESPACE__ . '\\OptimizeCommon')) {
            $defaultPath = rtrim(WP_CONTENT_DIR, '/\\') . OptimizeCommon::getRelPathPluginCacheDir() . 'google-fonts/';
        }

        $path = apply_filters('wpacu_google_fonts_local_cache_root_path', $defaultPath);
        return rtrim((string) $path, '/\\') . '/';
    }

    /**
     * @return string
     */
    public static function getCacheRootUrl()
    {
        $defaultUrl = content_url('/cache/asset-cleanup/one/google-fonts/');

        if (class_exists(__NAMESPACE__ . '\\OptimizeCommon')) {
            $defaultUrl = content_url(OptimizeCommon::getRelPathPluginCacheDir() . 'google-fonts/');
        }

        $url = apply_filters('wpacu_google_fonts_local_cache_root_url', $defaultUrl);
        return rtrim((string) $url, '/') . '/';
    }

    /**
     * Return a ready local file URL for a remote fonts.gstatic.com URL.
     *
     * @param string $fontUrl
     *
     * @return string
     */
    public static function getLocalFontUrl($fontUrl)
    {
        $fontUrl = FontsGoogleLocalUrl::canonicalizeFontFileUrl($fontUrl);
        if ($fontUrl === '') {
            return '';
        }

        if (self::$localFontUrlMap === null) {
            self::$localFontUrlMap = array();
            $rootPath = self::getCacheRootPath();
            $rootUrl = self::getCacheRootUrl();

            foreach (FontsGoogleLocalRegistry::getEntries() as $entry) {
                if ($entry['status'] !== 'ready' || empty($entry['manifest_path']) || ! is_file($entry['manifest_path'])) {
                    continue;
                }

                $manifest = json_decode((string) @file_get_contents($entry['manifest_path']), true);
                if (! isset($manifest['fonts']) || ! is_array($manifest['fonts'])) {
                    continue;
                }

                foreach ($manifest['fonts'] as $font) {
                    $sourceUrl = isset($font['source_url']) ? FontsGoogleLocalUrl::canonicalizeFontFileUrl($font['source_url']) : '';
                    $filename = isset($font['file']) ? basename((string) $font['file']) : '';

                    if ($sourceUrl === ''
                        || ! preg_match('/^[a-f0-9]{64}\.(?:woff2?|otf|ttf)$/', $filename)
                        || ! is_file($rootPath . 'files/' . $filename)
                    ) {
                        continue;
                    }

                    self::$localFontUrlMap[$sourceUrl] = $rootUrl . 'files/' . $filename;
                }
            }
        }

        return isset(self::$localFontUrlMap[$fontUrl]) ? self::$localFontUrlMap[$fontUrl] : '';
    }

    /**
     * Delete all local Google Fonts cache data and the registry.
     *
     * @return bool
     */
    public static function clearAll()
    {
        do_action('wpacu_google_fonts_local_cache_reset_before', 'immediate');

        $root = self::getCacheRootPath();
        $cleared = true;

        if (is_dir($root)) {
            $cleared = self::deleteTree($root);
        }

        FontsGoogleLocalRegistry::clear();
        self::$localFontUrlMap = null;

        // A cache reset also invalidates cron events, locks and maintenance
        // throttles that refer to the registry which has just been removed.
        if (class_exists(__NAMESPACE__ . '\\FontsGoogleLocal')) {
            FontsGoogleLocal::resetAutomaticQueueState();
        }

        do_action('wpacu_google_fonts_local_cache_cleared');
        do_action('wpacu_google_fonts_local_cache_reset_after', 'immediate', $cleared);

        return $cleared;
    }

    /**
     * Stop publishing active local copies while retaining immutable files for
     * static HTML until the normal generated-file retention period expires.
     *
     * @return bool
     */
    public static function resetActiveCopies()
    {
        do_action('wpacu_google_fonts_local_cache_reset_before', 'safe');

        $root = self::getCacheRootPath();
        $retentionStartedAt = self::now();
        foreach (array('css', 'manifests', 'files') as $subdir) {
            $paths = glob($root . $subdir . '/*');
            if (! is_array($paths)) {
                continue;
            }

            foreach ($paths as $path) {
                if (is_file($path)
                    && ! in_array(basename($path), array('index.php', '.htaccess'), true)
                    && ! @touch($path, $retentionStartedAt)
                ) {
                    do_action('wpacu_google_fonts_local_cache_reset_after', 'safe', false);
                    return false;
                }
            }
        }

        FontsGoogleLocalRegistry::clear();
        self::$localFontUrlMap = null;

        if (class_exists(__NAMESPACE__ . '\\FontsGoogleLocal')) {
            FontsGoogleLocal::resetAutomaticQueueState();
        }

        do_action('wpacu_google_fonts_local_cache_reset_after', 'safe', true);
        return true;
    }

    /**
     * Remove CSS, manifests and font files no longer referenced by ready entries.
     *
     * @return array
     */
    public static function cleanupOrphans()
    {
        $root = self::getCacheRootPath();
        $keep = array();
        $retentionDays = 14;

        if (class_exists('WpAssetCleanUp\\Main')) {
            $settings = \WpAssetCleanUp\Main::instance()->settings;
            if (is_array($settings) && isset($settings['clear_cached_files_after'])) {
                $retentionDays = max(1, (int) $settings['clear_cached_files_after']);
            }
        }

        $retentionDays = max(1, (int) apply_filters(
            'wpacu_google_fonts_local_retention_days',
            $retentionDays
        ));
        $removeIfModifiedAtOrBefore = self::now() - ($retentionDays * 86400);

        foreach (FontsGoogleLocalRegistry::getEntries() as $entry) {
            if ($entry['status'] !== 'ready') {
                continue;
            }

            foreach (array('local_css_path', 'manifest_path') as $pathKey) {
                if (! empty($entry[$pathKey])) {
                    $keep[self::normalizePath($entry[$pathKey])] = true;
                }
            }

            if (! empty($entry['manifest_path']) && is_file($entry['manifest_path'])) {
                $manifest = json_decode((string) @file_get_contents($entry['manifest_path']), true);
                if (isset($manifest['fonts']) && is_array($manifest['fonts'])) {
                    foreach ($manifest['fonts'] as $font) {
                        if (! empty($font['file'])) {
                            $keep[self::normalizePath($root . 'files/' . basename($font['file']))] = true;
                        }
                    }
                }
            }
        }

        $removed = array();
        foreach (array('css', 'manifests', 'files') as $subdir) {
            $paths = glob($root . $subdir . '/*');
            if (! is_array($paths)) {
                continue;
            }

            foreach ($paths as $path) {
                if (! is_file($path)
                    || in_array(basename($path), array('index.php', '.htaccess'), true)
                    || isset($keep[self::normalizePath($path)])
                ) {
                    continue;
                }

                $modifiedAt = @filemtime($path);
                if ($modifiedAt === false || $modifiedAt > $removeIfModifiedAtOrBefore) {
                    continue;
                }

                if (@unlink($path)) {
                    $removed[] = $path;
                }
            }
        }

        return $removed;
    }

    /**
     * @param string $url
     * @param int    $maxBytes
     * @param string $userAgent
     * @param string $type
     *
     * @return string
     */
    private static function fetchRemoteBody($url, $maxBytes, $userAgent, $type)
    {
        $args = array(
            'timeout'             => $type === 'stylesheet' ? 20 : 30,
            'redirection'         => 3,
            'reject_unsafe_urls'  => true,
            'sslverify'           => true,
            'limit_response_size' => $maxBytes + 1,
            'user-agent'          => $userAgent,
            'headers'             => array(
                'Accept' => $type === 'stylesheet' ? 'text/css,*/*;q=0.1' : 'font/woff2,font/woff,*/*;q=0.1',
            ),
        );

        if (function_exists('wp_safe_remote_get')) {
            $response = wp_safe_remote_get($url, $args);
        } else {
            $response = wp_remote_get($url, $args);
        }

        if (is_wp_error($response)) {
            throw new \RuntimeException('Could not download the Google ' . $type . ': ' . $response->get_error_message());
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            throw new \RuntimeException('The Google ' . $type . ' request returned HTTP ' . $statusCode . '.');
        }

        $contentLength = wp_remote_retrieve_header($response, 'content-length');
        if (is_numeric($contentLength) && (int) $contentLength > $maxBytes) {
            throw new \RuntimeException('The Google ' . $type . ' exceeds the allowed byte limit.');
        }

        $body = wp_remote_retrieve_body($response);
        if (! is_string($body) || $body === '') {
            throw new \RuntimeException('The Google ' . $type . ' response was empty.');
        }

        if (strlen($body) > $maxBytes) {
            throw new \RuntimeException('The Google ' . $type . ' exceeds the allowed byte limit.');
        }

        return $body;
    }

    /**
     * @param string $css
     *
     * @return void
     */
    private static function assertAllRemoteCssUrlsAllowed($css)
    {
        $cssWithoutComments = preg_replace('~/\*.*?\*/~s', '', (string) $css);

        if (preg_match('~@import\s+(?:url\(\s*)?["\']?(?:https?:)?//~i', $cssWithoutComments)) {
            throw new \RuntimeException('A nested remote CSS import declared by the Google stylesheet is not allowed.');
        }

        if (! preg_match_all('~url\(\s*["\']?((?:https?:)?//[^\s"\')]+)~i', $cssWithoutComments, $matches) || empty($matches[1])) {
            return;
        }

        foreach ($matches[1] as $remoteUrl) {
            if (FontsGoogleLocalUrl::canonicalizeFontFileUrl($remoteUrl) === '') {
                throw new \RuntimeException('A remote URL declared by the Google stylesheet is not allowed.');
            }
        }
    }

    /**
     * @return void
     */
    private static function ensureCacheDirectories()
    {
        $root = self::getCacheRootPath();

        foreach (array($root, $root . 'css/', $root . 'files/', $root . 'manifests/') as $directory) {
            if (! is_dir($directory) && ! wp_mkdir_p($directory)) {
                throw new \RuntimeException('The local Google Fonts cache directory could not be created.');
            }
        }

        foreach (array($root, $root . 'css/', $root . 'files/', $root . 'manifests/') as $directory) {
            $indexPath = $directory . 'index.php';
            if (! is_file($indexPath)) {
                self::writeAtomic($indexPath, "<?php\n// Silence is golden.\n");
            }
        }

        $htaccessPath = $root . '.htaccess';
        if (! is_file($htaccessPath)) {
            self::writeAtomic($htaccessPath, "Options -Indexes\n");
        }
    }

    /**
     * @param string $path
     * @param string $contents
     *
     * @return bool
     */
    private static function writeAtomic($path, $contents)
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! wp_mkdir_p($directory)) {
            return false;
        }

        $tempPath = $path . '.tmp-' . str_replace('.', '', uniqid('', true));
        $bytes = @file_put_contents($tempPath, $contents, LOCK_EX);

        if ($bytes === false || $bytes !== strlen($contents)) {
            @unlink($tempPath);
            return false;
        }

        @chmod($tempPath, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);

        if (! @rename($tempPath, $path)) {
            @unlink($tempPath);
            return false;
        }

        return true;
    }

    /**
     * @param string $css
     * @param string $sourceUrl
     *
     * @return string
     */
    private static function addSourceComment($css, $sourceUrl)
    {
        $sourceUrl = str_replace('*/', '* /', $sourceUrl);
        $comment = '/* WP Asset CleanUp local Google Fonts copy. Source: ' . $sourceUrl . ' */' . "\n";

        if (preg_match('/^((?:\xEF\xBB\xBF)?\s*@charset\s+["\'][^"\']+["\'];\s*)/i', $css, $match)) {
            return $match[1] . $comment . substr($css, strlen($match[1]));
        }

        return $comment . $css;
    }

    /**
     * @param array $paths
     *
     * @return void
     */
    private static function removeCreatedFiles($paths)
    {
        foreach (array_reverse(array_unique((array) $paths)) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    private static function deleteTree($path)
    {
        $path = rtrim($path, '/\\');

        if ($path === '' || strpos(self::normalizePath($path), '/google-fonts') === false || ! is_dir($path)) {
            return false;
        }

        $items = scandir($path);
        if (! is_array($items)) {
            return false;
        }

        $success = true;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . '/' . $item;
            if (is_dir($target) && ! is_link($target)) {
                if (! self::deleteTree($target)) {
                    $success = false;
                }
            } elseif (! @unlink($target)) {
                $success = false;
            }
        }

        if (! @rmdir($path)) {
            $success = false;
        }

        return $success;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private static function normalizePath($path)
    {
        return str_replace('\\', '/', (string) $path);
    }

    /**
     * @param string $key
     * @param string $error
     *
     * @return array
     */
    private static function failureResult($key, $error)
    {
        return array('success' => false, 'key' => $key, 'metadata' => array(), 'error' => (string) $error);
    }

    /**
     * @return int
     */
    private static function now()
    {
        return (int) apply_filters('wpacu_google_fonts_local_now', time());
    }
}
