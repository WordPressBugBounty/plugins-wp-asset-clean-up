<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Misc;

/**
 * Class FontsGoogleRemove
 * @package WpAssetCleanUp\OptimiseAssets
 */
class FontsGoogleRemove
{
    /** @var array */
    private static $validatedVariantsRuntimeCache = array();

    /**
     * Google-hosted stylesheet and font-file origins that can be emitted by
     * current or legacy Google Fonts integrations.
     *
     * @var array
     */
    public static $stringsToCheck = array(
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'themes.googleusercontent.com'
    );

    /**
     * @var array
     */
    public static $stylesheetHosts = array(
        'fonts.googleapis.com'
    );

    /**
     * @var array
     */
    public static $fontFileHosts = array(
        'fonts.gstatic.com',
        'themes.googleusercontent.com'
    );

    /**
     * Regex fragments for common Web Font Loader CDN locations.
     *
     * @var array
     */
    public static $possibleWebFontConfigCdnPatterns = array(
        'ajax\.googleapis\.com/ajax/libs/webfont/',
        'cdnjs\.cloudflare\.com/ajax/libs/webfont/',
        'cdn\.jsdelivr\.net/npm/webfontloader@'
    );

    /**
     * Called late from OptimizeCss after all other optimizations are done (e.g. minify, combine)
     *
     * @param $htmlSource
     *
     * @return mixed
     */
    public static function cleanHtmlSource($htmlSource)
    {
        $htmlSource = self::cleanLinkTags($htmlSource);
        $htmlSource = self::cleanFromInlineStyleTags($htmlSource);

        return str_replace(FontsGoogle::NOSCRIPT_WEB_FONT_LOADER, '', $htmlSource);
    }

    /**
     * @param mixed $content
     *
     * @return bool
     */
    public static function containsAnyGoogleFontsReference($content)
    {
        return self::containsAnyHost($content, self::$stringsToCheck);
    }

    /**
     * @param mixed $content
     *
     * @return bool
     */
    public static function containsGoogleFontsStylesheetReference($content)
    {
        return self::containsAnyHost($content, self::$stylesheetHosts);
    }

    /**
     * @param mixed $content
     *
     * @return bool
     */
    public static function containsGoogleFontFileReference($content)
    {
        return self::containsAnyHost($content, self::$fontFileHosts);
    }

    /**
     * @param mixed $content
     * @param array $hosts
     *
     * @return bool
     */
    private static function containsAnyHost($content, $hosts)
    {
        if (! is_string($content) || $content === '') {
            return false;
        }

        $decodedContent = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $trimmedContent = trim($decodedContent);

        // Resource-hint callbacks and several internal callers pass a standalone
        // URL. Parse that URL once and do not scan a nested URL from its query.
        if (preg_match('#^(?:https?:)?//#i', $trimmedContent)
            && ! preg_match('/[\s<>"\']/', $trimmedContent)) {
            return self::urlHasAllowedHost($trimmedContent, $hosts);
        }

        // For HTML/CSS/JS containers, inspect complete URL tokens. Stopping at
        // quotes, a closing parenthesis or a declaration separator keeps a URL
        // embedded in another URL's query from becoming a second false match.
        if ( ! preg_match_all('#(?:(?:https?:)?//)[^\s"\'<>),;]+#i', $decodedContent, $matches) ) {
            return false;
        }

        foreach ($matches[0] as $urlCandidate) {
            if (self::urlHasAllowedHost($urlCandidate, $hosts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate a standalone HTTP(S) URL against an exact host allowlist.
     *
     * @param mixed $url
     * @param array $hosts
     *
     * @return bool
     */
    private static function urlHasAllowedHost($url, $hosts)
    {
        if ( ! is_string($url) || $url === '' ) {
            return false;
        }

        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'), " \t\n\r\0\x0B\"\'");

        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        if ( ! preg_match('#^https?://#i', $url) ) {
            return false;
        }

        $parts = parse_url($url);

        if ( ! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        $host = rtrim(strtolower($parts['host']), '.');
        $port = isset($parts['port']) ? (int) $parts['port'] : 0;
        $validPort = ($scheme === 'https' && ($port === 0 || $port === 443))
            || ($scheme === 'http' && ($port === 0 || $port === 80));

        if ( ! $validPort ) {
            return false;
        }

        foreach ($hosts as $allowedHost) {
            if ($host === strtolower($allowedHost)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Replace the first exact attribute using its original source range.
     *
     * @param string $tag
     * @param array $ranges
     * @param string $attributeName
     * @param string $value
     *
     * @return string
     */
    private static function rewriteHtmlAttribute($tag, $ranges, $attributeName, $value)
    {
        foreach ($ranges as $range) {
            if ($range['name'] !== $attributeName) {
                continue;
            }
            $original = substr($tag, $range['offset'], $range['length']);
            $equal    = strpos($original, '=');
            if ($equal === false) {
                return $tag;
            }
            $rawValue = substr($original, $equal + 1);
            $trimmed  = ltrim($rawValue, "\x09\x0a\x0c\x0d\x20");
            $start    = $equal + 1 + strlen($rawValue) - strlen($trimmed);
            $quote    = isset($trimmed[0]) && $trimmed[0] === "'" ? "'" : '"';
            $encoded  = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

            return substr_replace($tag, $quote . $encoded . $quote,
                $range['offset'] + $start, strlen($trimmed));
        }

        return $tag;
    }

    /**
     * Collect actual HTML stylesheet elements while honoring opt-outs and the manager.
     *
     * @param string $htmlSource
     *
     * @return array
     */
    private static function getEditableStyleTokens($htmlSource)
    {
        $tokens = HtmlCleanup::getStyleTokens($htmlSource);
        $result = array();

        foreach (is_array($tokens) ? $tokens : array() as $token) {
            if ($token['manager'] || isset($token['attributes']['data-wpacu-skip'])) {
                continue;
            }
            if ($token['tag_name'] === 'LINK' && isset($token['attributes']['href'])) {
                $href = $token['attributes']['href'];
                // The scanner has already decoded HTML once. Legacy URL helpers
                // also decode entities: do not let a second pass reinterpret
                // literal entity text or a legacy-looking fragment as a query.
                if (html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8') !== $href
                    || strpos($href, '#038;') !== false) {
                    continue;
                }
            }
            if ($token['tag_name'] === 'STYLE' && isset($token['attributes']['type'])
                && ! in_array(strtolower(trim($token['attributes']['type'])), array('', 'text/css'), true)) {
                continue;
            }
            $result[] = $token;
        }

        return $result;
    }

    /**
     * @param array $hosts
     *
     * @return string
     */
    private static function getHostsRegex($hosts)
    {
        return implode('|', array_map(static function($host) {
            return preg_quote($host, '#');
        }, $hosts));
    }

    /**
     * @param $htmlSource
     *
     * @return mixed
     */
    public static function cleanLinkTags($htmlSource)
    {
        if (stripos($htmlSource, '<link') === false) {
            return $htmlSource;
        }

        foreach (array_reverse(self::getEditableStyleTokens($htmlSource)) as $token) {
            if ($token['tag_name'] === 'LINK' && isset($token['attributes']['href'])
                && self::shouldRemoveStylesheetUrl($token['attributes']['href'])) {
                $htmlSource = substr_replace($htmlSource, '', $token['offset'], $token['length']);
            }
        }

        return $htmlSource;
    }

    /**
     * Determine whether a stylesheet belongs to Google Fonts, including a
     * validated local copy published by the Local Hosting cache.
     *
     * @param string $url
     *
     * @return bool
     */
    public static function shouldRemoveStylesheetUrl($url)
    {
        if (self::urlHasAllowedHost($url, self::$stringsToCheck)) {
            return true;
        }

        return FontsGoogleLocalRegistry::findByLocalCssUrl($url) !== array();
    }

    /**
     * @param $htmlSource
     *
     * @return mixed
     */
    public static function cleanFromInlineStyleTags($htmlSource)
    {
        if (stripos($htmlSource, '<style') === false || ! self::containsAnyGoogleFontsReference($htmlSource)) {
            return $htmlSource;
        }

        foreach (array_reverse(self::getEditableStyleTokens($htmlSource)) as $token) {
            if ( $token['tag_name'] !== 'STYLE' || ! self::containsAnyGoogleFontsReference($token['content']) ) {
                continue;
            }
            $css = self::stripGoogleFontImportsFromCss($token['content']);
            $css = self::cleanFontFaceReferences($css);

            if ($css === $token['content']) {
                continue;
            }
            if (trim($css) === '') {
                $htmlSource = substr_replace($htmlSource, '', $token['offset'], $token['length']);
            } else {
                $htmlSource = substr_replace($htmlSource, $css,
                    $token['offset'] + strlen($token['opening']), strlen($token['content']));
            }
        }

        return $htmlSource;
    }

    /**
     * Remove Google Fonts @import statements while preserving every unrelated
     * import. Quoted URLs can safely contain semicolons (for example variable
     * font ranges in a CSS2 request).
     *
     * @param $cssContent
     *
     * @return mixed
     */
    public static function stripGoogleFontImportsFromCss($cssContent)
    {
        if (! self::containsGoogleFontsStylesheetReference($cssContent) || stripos($cssContent, '@import') === false) {
            return $cssContent;
        }

        $importPattern = '#@import\s+(?:(?<url_function>url)\(\s*)?(?<quote>["\']?)(?<target>.*?)(?P=quote)(?(url_function)\s*\))\s*[^;]*;#is';

        $cleanedCssContent = preg_replace_callback(
            $importPattern,
            static function($matches) {
                return self::urlHasAllowedHost($matches['target'], self::$stylesheetHosts)
                    ? ''
                    : $matches[0];
            },
            $cssContent
        );

        return is_string($cleanedCssContent) ? $cleanedCssContent : $cssContent;
    }

    /**
     * @param $importsAddToTop
     *
     * @return mixed
     */
    public static function stripGoogleApisImport($importsAddToTop)
    {
        // Remove only imports that point to the Google Fonts stylesheet origin.
        foreach ($importsAddToTop as $importKey => $importToPrepend) {
            if (self::containsGoogleFontsStylesheetReference($importToPrepend)) {
                unset($importsAddToTop[$importKey]);
            }
        }

        return $importsAddToTop;
    }

    /**
     * If "Google Font Remove" is active, strip its references from JavaScript code as well
     *
     * @param $jsContent
     *
     * @return string|string[]|null
     */
    public static function stripReferencesFromJsCode($jsContent)
    {
        if (self::preventAnyChange()) {
            return $jsContent;
        }

        $hasGoogleWebFontConfig = preg_match('/(?:WebFontConfig\.|WebFontConfig\s*=|[\'\"]google[\'\"]?\s*:)/i', $jsContent);

        if ($hasGoogleWebFontConfig) {
            $webFontLoaderHosts = implode('|', self::$possibleWebFontConfigCdnPatterns);
            $webFontLoaderPattern = '#(?P<prefix>\bsrc\s*=\s*)(?P<quote>["\'])(?:https?:)?//(?:' . $webFontLoaderHosts . ')[^"\']*(?P=quote)#i';

            $cleanedJsContent = preg_replace_callback(
                $webFontLoaderPattern,
                static function($matches) {
                    return $matches['prefix'] . $matches['quote'] . $matches['quote'] . '/* Stripped by ' . WPACU_PLUGIN_TITLE . ' */';
                },
                $jsContent
            );

            if (is_string($cleanedJsContent)) {
                $jsContent = $cleanedJsContent;
            }
        }

        // Remove direct, fully quoted URLs to the Google Fonts stylesheet/font
        // origins. This covers simple dynamic link/preload/preconnect builders.
        $directQuotedUrlPattern = '#(?P<quote>["\'])(?P<url>(?:https?:)?//[^"\']+)(?P=quote)#i';
        $cleanedJsContent = preg_replace_callback(
            $directQuotedUrlPattern,
            static function($matches) {
                return self::urlHasAllowedHost($matches['url'], self::$stringsToCheck)
                    ? $matches['quote'] . $matches['quote']
                    : $matches[0];
            },
            $jsContent
        );

        if (is_string($cleanedJsContent)) {
            $jsContent = $cleanedJsContent;
        }

        /*
            WebFont.load({
                google: {
                    families: [
                        'Oswald:400,400italic',
                        'Heebo:400,400italic'
                    ]
                }
            });
         */
        $webFontConfigReferenceThree = '#WebFont\.load(.*?)(google(.*?)\{(.*?)families(\s+|):(\s+|)\[(.*?)](\s+)})#si';
        if (preg_match($webFontConfigReferenceThree, $jsContent)) {
            preg_match_all($webFontConfigReferenceThree, $jsContent, $matches);
            if (isset($matches[2][0]) && $matches[2][0]) {
                $jsContent = str_replace($matches[2][0], '', $jsContent);
            }
        }

        /*
            WebFontConfig = {
                google: {
                    families: [
                        'Roboto',
                        'Open Sans:300,300italic'
                    ]
                },
                custom: {}
            }
         */
        $webFontConfigReferenceFour = '#WebFontConfig(\s+)=(\s+){(.*?)(google(.*?)\{(.*?)families(\s+|):(\s+|)\[(.*?)](\s+)}(\s+|)(,|))#si';
        if (preg_match($webFontConfigReferenceFour, $jsContent)) {
            preg_match_all($webFontConfigReferenceFour, $jsContent, $matches);
            if (isset($matches[4][0]) && $matches[4][0]) {
                $jsContent = str_replace($matches[4][0], '', $jsContent);
            }
        }

        return $jsContent;
    }

    /**
     * @param $cssContent
     *
     * @return array|mixed|string|string[]
     */
    public static function cleanFontFaceReferences($cssContent)
    {
        if (self::preventAnyChange()) {
            return $cssContent;
        }

        if (stripos($cssContent, '@font-face') === false || ! self::containsGoogleFontFileReference($cssContent)) {
            return $cssContent;
        }

        $cleanedCssContent = preg_replace_callback(
            '#@font-face\s*\{.*?}#is',
            static function($matches) {
                return self::containsGoogleFontFileReference($matches[0])
                    ? ''
                    : $matches[0];
            },
            $cssContent
        );

        return is_string($cleanedCssContent) ? $cleanedCssContent : $cssContent;
    }


    /**
     * Return the validated selective-removal rules shared by Lite and Pro.
     *
     * @return array
     */
    public static function getSpecificRules()
    {
        $rules = apply_filters('wpacu_google_fonts_remove_specific_rules', array());
        if (! is_array($rules)) {
            return array();
        }

        $valid = array();
        foreach ($rules as $rule) {
            $decoded = self::decodeSpecificRule($rule);
            if (! empty($decoded)) {
                $valid[self::encodeSpecificRule($decoded['family'], $decoded['weight'], $decoded['style'])] = true;
            }
        }
        return array_keys($valid);
    }

    /** @return bool */
    public static function hasSpecificRules()
    {
        return ! empty(self::getSpecificRules());
    }

    /**
     * Encode a family + weight + style tuple into a stable settings token.
     */
    public static function encodeSpecificRule($family, $weight, $style)
    {
        $family = trim((string) $family);
        $weight = trim((string) $weight);
        $style  = strtolower(trim((string) $style)) === 'italic' ? 'italic' : 'normal';
        if ($family === '' || $weight === '') {
            return '';
        }
        return rawurlencode($family) . '|' . rawurlencode($weight) . '|' . $style;
    }

    /**
     * Decode a settings token generated by encodeSpecificRule().
     *
     * @return array
     */
    public static function decodeSpecificRule($rule)
    {
        if (! is_string($rule) || substr_count($rule, '|') !== 2) {
            return array();
        }
        $parts = explode('|', $rule, 3);
        $family = trim(rawurldecode($parts[0]));
        $weight = trim(rawurldecode($parts[1]));
        $style = strtolower(trim($parts[2]));
        if ($family === '' || $weight === '' || ! in_array($style, array('normal', 'italic'), true)) {
            return array();
        }
        return array('family' => $family, 'weight' => $weight, 'style' => $style);
    }

    /**
     * Build a family-grouped inventory from all stylesheet configurations already
     * discovered by the shared Google Fonts registry.
     *
     * @return array
     */
    public static function getDiscoveredSpecificVariants()
    {
        if (! class_exists(__NAMESPACE__ . '\\FontsGoogleLocalRegistry')) {
            return array();
        }
        $inventoryEntries = array();
        foreach (FontsGoogleLocalRegistry::getEntries() as $entry) {
            if (empty($entry['url'])) {
                continue;
            }

            // The request URL is only an expression of intent. Themes/plugins can
            // ask Google for non-existent variants (e.g. Open Sans 100). Build the
            // UI inventory only from @font-face declarations Google actually
            // returned and WPACU validated/published locally.
            $validatedVariants = self::getValidatedVariantsForRegistryEntry($entry);
            if (empty($validatedVariants)) {
                continue;
            }
            $entry['validated_variants'] = $validatedVariants;
            if (empty($entry['variant_sources'])) {
                $entry['variant_sources'] = ! empty($entry['local_css_path']) ? array('local') : array('remote');
            }
            $inventoryEntries[] = $entry;
        }

        $aggregated = FontsGoogleVariantInventory::aggregate($inventoryEntries, self::getSpecificRules());
        $families = array();
        foreach ($aggregated as $family => $group) {
            $families[$family] = $group['variants'];
        }
        return $families;
    }

    /**
     * Build the Remove Specific inventory from known variants.
     * A tuple with at least one ready source is shown only in Ready; otherwise an
     * error source shows it in Failed. When local hosting is off, validated remote
     * variants are selectable even if their local cache has not been processed.
     * Saved selections remain represented while their sources are pending or
     * processing, so saving other settings cannot silently remove those rules.
     * This does not change cache status or trigger any font downloads.
     *
     * @return array
     */
    public static function getSpecificVariantInventoryByStatus()
    {
        $inventory = array(
            'ready'                      => array(),
            'failed'                     => array(),
            'failed_urls'                => array(),
            'failed_configuration_count' => 0,
        );

        if (! class_exists(__NAMESPACE__ . '\\FontsGoogleLocalRegistry')
            || ! class_exists(__NAMESPACE__ . '\\FontsGoogleVariantInventory')
        ) {
            return $inventory;
        }

        $variantStates           = array();
        $representedTupleKeys    = array();
        $failedConfigurationKeys = array();
        $failedUrlsByFamily      = array();
        $settings                = Main::instance()->settings;
        $includeRemotePending    = empty($settings['google_fonts_local']);

        foreach (FontsGoogleLocalRegistry::getEntries() as $entryKey => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $status = isset($entry['status']) ? $entry['status'] : '';
            if ($status === 'error') {
                $failedConfigurationKeys[(string) $entryKey] = true;
            }

            $validatedVariants = self::getValidatedVariantsForRegistryEntry($entry);
            if (empty($validatedVariants)) {
                continue;
            }

            $sources = ! empty($entry['variant_sources']) && is_array($entry['variant_sources'])
                ? $entry['variant_sources']
                : (! empty($entry['local_css_path']) ? array('local') : array('remote'));
            $paths = ! empty($entry['paths']) && is_array($entry['paths']) ? $entry['paths'] : array();
            $failedUrls = array();
            if ($status === 'error' && ! empty($entry['url']) && is_string($entry['url'])) {
                $failedUrl = trim(html_entity_decode($entry['url'], ENT_QUOTES, 'UTF-8'));
                $failedUrlParts = parse_url($failedUrl);
                if (is_array($failedUrlParts)
                    && isset($failedUrlParts['scheme'], $failedUrlParts['host'])
                    && strtolower($failedUrlParts['scheme']) === 'https'
                    && rtrim(strtolower($failedUrlParts['host']), '.') === 'fonts.googleapis.com'
                    && empty($failedUrlParts['user']) && empty($failedUrlParts['pass'])
                    && (! isset($failedUrlParts['port']) || (int) $failedUrlParts['port'] === 443)) {
                    $failedUrls[$failedUrl] = true;
                }
            }

            $remoteVariantAvailable = $includeRemotePending
                && in_array($status, array('pending', 'processing'), true)
                && in_array('remote', $sources, true);

            foreach ($validatedVariants as $variant) {
                $tupleKey = self::getSpecificVariantStateKey($variant);
                if ($tupleKey === '') {
                    continue;
                }

                if ($status === 'error' && ! empty($failedUrls)) {
                    $failedUrlFamily = isset($variant['family']) ? trim((string) $variant['family']) : '';
                    if ($failedUrlFamily !== '') {
                        $failedUrlFamily = strtolower($failedUrlFamily);
                        if (! isset($failedUrlsByFamily[$failedUrlFamily])) {
                            $failedUrlsByFamily[$failedUrlFamily] = array();
                        }
                        $failedUrlsByFamily[$failedUrlFamily] = array_merge($failedUrlsByFamily[$failedUrlFamily], $failedUrls);
                    }
                }

                if (! isset($variantStates[$tupleKey])) {
                    $variantStates[$tupleKey] = array(
                        'ready'   => false,
                        'error'   => false,
                        'variant' => $variant,
                        'sources' => $sources,
                        'paths'   => $paths,
                        'failed_urls' => $failedUrls,
                    );
                } else {
                    $variantStates[$tupleKey]['sources'] = array_merge(
                        $variantStates[$tupleKey]['sources'],
                        $sources
                    );
                    $variantStates[$tupleKey]['paths'] = array_merge(
                        $variantStates[$tupleKey]['paths'],
                        $paths
                    );
                    $variantStates[$tupleKey]['failed_urls'] = array_merge(
                        $variantStates[$tupleKey]['failed_urls'],
                        $failedUrls
                    );
                }

                if ($status === 'ready' || $remoteVariantAvailable) {
                    $variantStates[$tupleKey]['ready'] = true;
                } elseif ($status === 'error') {
                    $variantStates[$tupleKey]['error'] = true;
                }
            }
        }

        $readyEntries = array();
        $failedEntries = array();
        foreach ($variantStates as $tupleKey => $variantState) {
            if ( ! $variantState['ready'] && ! $variantState['error'] ) {
                continue;
            }

            $representedTupleKeys[$tupleKey] = true;
            $inventoryEntry = array(
                'validated_variants' => array($variantState['variant']),
                'variant_sources'    => $variantState['sources'],
                'paths'              => $variantState['paths'],
            );

            if ($variantState['ready']) {
                $readyEntries[] = $inventoryEntry;
            } else {
                $failedEntries[] = $inventoryEntry;
                $failedFamily = isset($variantState['variant']['family']) ? trim((string) $variantState['variant']['family']) : '';
                if ($failedFamily !== '' && ! empty($variantState['failed_urls'])) {
                    if (! isset($inventory['failed_urls'][$failedFamily])) {
                        $inventory['failed_urls'][$failedFamily] = array();
                    }
                    $inventory['failed_urls'][$failedFamily] = array_merge(
                        $inventory['failed_urls'][$failedFamily],
                        array_keys($variantState['failed_urls'])
                    );
                }
            }
        }

        $savedOnlyRules = array();
        foreach (self::getSpecificRules() as $savedRule) {
            $decodedRule = FontsGoogleVariantInventory::decodeTuple($savedRule);
            $tupleKey = self::getSpecificVariantStateKey($decodedRule);
            if ($tupleKey === '' || isset($representedTupleKeys[$tupleKey])) {
                continue;
            }
            $savedOnlyRules[] = FontsGoogleVariantInventory::encodeTuple(
                $decodedRule['family'],
                $decodedRule['weight'],
                $decodedRule['style']
            );
        }

        $ready = FontsGoogleVariantInventory::aggregate($readyEntries, $savedOnlyRules);
        $failed = FontsGoogleVariantInventory::aggregate($failedEntries);

        foreach ($ready as $family => $group) {
            $inventory['ready'][$family] = $group['variants'];
        }
        foreach ($failed as $family => $group) {
            $inventory['failed'][$family] = $group['variants'];
            $failedFamilyKey = strtolower($family);
            if (! empty($failedUrlsByFamily[$failedFamilyKey])) {
                $inventory['failed_urls'][$family] = array_values(array_keys($failedUrlsByFamily[$failedFamilyKey]));
            }
            if (! empty($inventory['failed_urls'][$family])) {
                $inventory['failed_urls'][$family] = array_values(array_unique($inventory['failed_urls'][$family]));
            }
        }
        $inventory['failed_configuration_count'] = count($failedConfigurationKeys);

        return $inventory;
    }

    /**
     * @param array $variant
     *
     * @return string
     */
    private static function getSpecificVariantStateKey($variant)
    {
        if (! is_array($variant)) {
            return '';
        }

        $family = isset($variant['family']) ? $variant['family'] : '';
        $weight = isset($variant['weight']) ? $variant['weight'] : '';
        $style = isset($variant['style']) ? $variant['style'] : '';
        $token = FontsGoogleVariantInventory::encodeTuple($family, $weight, $style);
        if ($token === '') {
            return '';
        }

        $decoded = FontsGoogleVariantInventory::decodeTuple($token);
        return strtolower($decoded['family']) . '|' . $decoded['weight'] . '|' . $decoded['style'];
    }

    /**
     * Extract family/weight/style tuples from Google Fonts CSS1 and CSS2 URLs.
     *
     * @return array
     */
    /**
     * Extract the variants Google actually served from @font-face declarations.
     * Multiple unicode-range blocks for the same family/weight/style are deduped.
     *
     * @param string $css
     *
     * @return array
     */
    public static function extractVariantsFromStylesheetCss($css)
    {
        return FontsGoogleVariantInventory::extractFromCss($css);
    }

    /**
     * Resolve served variants for one registry entry. If a future cache format
     * persists them directly, use that metadata; current entries are read from
     * their already validated local stylesheet and cached for this PHP request.
     *
     * @param array $entry
     *
     * @return array
     */
    private static function getValidatedVariantsForRegistryEntry($entry)
    {
        if (! is_array($entry) || empty($entry['url'])) {
            return array();
        }

        $runtimeKey = hash('sha256', (string) $entry['url']);
        if (isset(self::$validatedVariantsRuntimeCache[$runtimeKey])) {
            return self::$validatedVariantsRuntimeCache[$runtimeKey];
        }

        if (! empty($entry['validated_variants']) && is_array($entry['validated_variants'])) {
            self::$validatedVariantsRuntimeCache[$runtimeKey] = self::normalizeValidatedVariants($entry['validated_variants']);
            return self::$validatedVariantsRuntimeCache[$runtimeKey];
        }

        if (empty($entry['status']) || $entry['status'] !== 'ready'
            || empty($entry['local_css_path']) || ! is_file($entry['local_css_path'])) {
            self::$validatedVariantsRuntimeCache[$runtimeKey] = array();
            return array();
        }

        $css = @file_get_contents($entry['local_css_path']);
        self::$validatedVariantsRuntimeCache[$runtimeKey] = is_string($css)
            ? self::extractVariantsFromStylesheetCss($css)
            : array();

        return self::$validatedVariantsRuntimeCache[$runtimeKey];
    }

    /**
     * @param array $variants
     *
     * @return array
     */
    private static function normalizeValidatedVariants($variants)
    {
        return FontsGoogleVariantInventory::normalize($variants);
    }

    /**
     * Return a fast lookup map for variants Google actually served for this URL.
     * An empty map means there is no validated cache to constrain the rewrite.
     *
     * @param string $url
     *
     * @return array
     */
    private static function getValidatedVariantMapForStylesheetUrl($url)
    {
        if (! class_exists(__NAMESPACE__ . '\\FontsGoogleLocalRegistry')
            || ! class_exists(__NAMESPACE__ . '\\FontsGoogleLocalUrl')
        ) {
            return array();
        }

        $canonicalUrl = FontsGoogleLocalUrl::canonicalizeStylesheetUrl($url);
        if ($canonicalUrl === '') {
            return array();
        }

        $entry = FontsGoogleLocalRegistry::getEntry(FontsGoogleLocalUrl::fingerprint($canonicalUrl));
        $variants = self::getValidatedVariantsForRegistryEntry($entry);
        $map = array();

        foreach ($variants as $variant) {
            $map[strtolower($variant['family']) . '|' . $variant['weight'] . '|' . $variant['style']] = true;
        }

        return $map;
    }

    /**
     * @param string $family
     * @param string $weight
     * @param string $style
     * @param array  $validatedMap
     *
     * @return bool
     */
    private static function variantWasValidated($family, $weight, $style, $validatedMap)
    {
        if (empty($validatedMap)) {
            return true;
        }

        $key = strtolower(trim($family)) . '|' . trim($weight) . '|' . ($style === 'italic' ? 'italic' : 'normal');
        return isset($validatedMap[$key]);
    }

    public static function extractVariantsFromStylesheetUrl($url)
    {
        $url = html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8');
        if (! self::urlHasAllowedHost($url, self::$stylesheetHosts)) {
            return array();
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['query'])) {
            return array();
        }
        $isCss2 = isset($parts['path']) && preg_match('#/css2$#', $parts['path']);
        $variants = array();
        foreach (explode('&', $parts['query']) as $pair) {
            $pairParts = explode('=', $pair, 2);
            if (rawurldecode($pairParts[0]) !== 'family' || ! isset($pairParts[1])) {
                continue;
            }
            $value = rawurldecode(str_replace('+', '%20', $pairParts[1]));
            $specs = $isCss2 ? array($value) : explode('|', $value);
            foreach ($specs as $spec) {
                $parsed = $isCss2 ? self::parseCss2FamilySpec($spec) : self::parseCss1FamilySpec($spec);
                foreach ($parsed as $variant) {
                    $key = strtolower($variant['family']) . '|' . $variant['weight'] . '|' . $variant['style'];
                    $variants[$key] = $variant;
                }
            }
        }
        return array_values($variants);
    }

    /**
     * Rewrite only the selected variants out of a Google Fonts stylesheet URL.
     * An empty string means that every variant in the URL was selected.
     *
     * @param string $url
     * @param array|null $rules
     *
     * @return string
     */
    public static function rewriteStylesheetUrlForSpecificRemoval($url, $rules = null)
    {
        if ($rules === null) {
            $rules = self::getSpecificRules();
        }
        if (empty($rules) || ! self::urlHasAllowedHost($url, self::$stylesheetHosts)) {
            return $url;
        }
        $ruleMap = self::specificRuleMap($rules);
        if (empty($ruleMap)) {
            return $url;
        }
        $normalizedUrl = html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8');
        $normalizedUrl = str_replace('#038;', '&', $normalizedUrl);
        $validatedMap = self::getValidatedVariantMapForStylesheetUrl($normalizedUrl);
        $parts = parse_url($normalizedUrl);
        if (! is_array($parts) || empty($parts['query']) || empty($parts['host'])) {
            return $url;
        }
        $isCss2   = isset($parts['path']) && preg_match('#/css2$#', $parts['path']);
        $newPairs = array();
        $changed  = false;
        foreach (explode('&', $parts['query']) as $pair) {
            $pairParts = explode('=', $pair, 2);
            if (rawurldecode($pairParts[0]) !== 'family' || ! isset($pairParts[1])) {
                $newPairs[] = $pair;
                continue;
            }
            $value = rawurldecode(str_replace('+', '%20', $pairParts[1]));
            if ($isCss2) {
                $rewritten = self::rewriteCss2FamilySpec($value, $ruleMap, $validatedMap);
                if ($rewritten === $value) {
                    $newPairs[] = $pair;
                } elseif ($rewritten !== '') {
                    $newPairs[] = 'family=' . rawurlencode($rewritten);
                }
                $changed = $changed || $rewritten !== $value;
            } else {
                $keptSpecs   = array();
                $pairChanged = false;
                foreach (explode('|', $value) as $spec) {
                    if (trim($spec) === '') {
                        return $url;
                    }
                    $rewritten   = self::rewriteCss1FamilySpec($spec, $ruleMap, $validatedMap);
                    $pairChanged = $pairChanged || $rewritten !== $spec;
                    if ($rewritten !== '') {
                        $keptSpecs[] = $rewritten;
                    }
                }
                if ( ! $pairChanged ) {
                    $newPairs[] = $pair;
                } elseif ( ! empty($keptSpecs) ) {
                    $newPairs[] = 'family=' . rawurlencode(implode('|', $keptSpecs));
                }
                $changed = $changed || $pairChanged;
            }
        }
        if ( ! $changed ) {
            return $url;
        }

        $hasFamily = false;
        foreach ($newPairs as $pair) {
            $pairParts = explode('=', $pair, 2);
            if (rawurldecode($pairParts[0]) === 'family' && isset($pairParts[1])) {
                $hasFamily = true;
                break;
            }
        }
        if (! $hasFamily) {
            return '';
        }
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) . '://' : 'https://';
        $rebuilt = $scheme . $parts['host'] . (isset($parts['path']) ? $parts['path'] : '/css');
        return $rebuilt . '?' . implode('&', $newPairs);
    }

    /**
     * Rewrite a known WP Asset CleanUp local Google stylesheet to an immutable
     * derivative. Unknown local URLs are deliberately left unchanged.
     */
    public static function rewriteLocalStylesheetUrlForSpecificRemoval($url, $rules = null)
    {
        if ($rules === null) {
            $rules = self::getSpecificRules();
        }
        if (empty($rules) || ! is_string($url) || $url === '') {
            return $url;
        }

        $entry = FontsGoogleLocalRegistry::findByLocalCssUrl($url);
        if (empty($entry)) {
            return $url;
        }

        $result = FontsGoogleFilteredCss::resolve($entry, $rules);
        if ($result['status'] === 'empty') {
            return '';
        }
        return in_array($result['status'], array('ready', 'unchanged'), true) && ! empty($result['url'])
            ? $result['url']
            : $url;
    }

    /**
     * Rewrite Google Fonts stylesheet URLs found in arbitrary HTML/CSS/JS text.
     *
     * @param string $content
     * @param array|null $rules A snapshot for the current processing pass.
     *
     * @return string
     */
    public static function rewriteSpecificStylesheetReferences($content, $rules = null)
    {
        if ( ! is_string($content) || $content === ''
            || stripos($content, 'fonts.googleapis.com') === false) {
            return $content;
        }
        if ($rules === null) {
            $rules = self::getSpecificRules();
        }
        if (empty($rules)) {
            return $content;
        }
        $pattern = '#(?P<quote>["\'`])(?P<url>(?:https?:)?//fonts\.googleapis\.com/(?:css2?|icon)\?[^"\'`]+)(?P=quote)#i';
        $rewritten = preg_replace_callback($pattern, static function($matches) use ($rules) {
            $original = $matches['url'];
            $newUrl = self::rewriteSpecificMatchedUrl($original, $rules);
            if ($newUrl === '') {
                return $matches['quote'] . $matches['quote'];
            }
            return $matches['quote'] . $newUrl . $matches['quote'];
        }, $content);
        return is_string($rewritten) ? $rewritten : $content;
    }

    private static function rewriteSpecificMatchedUrl($url, $rules)
    {
        $absolute = strpos($url, '//') === 0 ? 'https:' . $url : $url;
        $rewritten = self::rewriteStylesheetUrlForSpecificRemoval($absolute, $rules);

        return strpos($url, '//') === 0 ? preg_replace('#^https:#', '', $rewritten) : $rewritten;
    }


    /**
     * Filter literal Web Font Loader lists as well as direct stylesheet URLs.
     *
     * @param string $content
     * @param array|null $rules A snapshot for the current processing pass.
     * @param array|null $ruleMap The corresponding family/weight/style lookup.
     *
     * @return string
     */
    public static function rewriteSpecificJavaScriptReferences($content, $rules = null, $ruleMap = null)
    {
        if ( ! is_string($content)
            || (strpos($content, 'WebFont') === false && stripos($content, 'fonts.googleapis.com') === false) ) {
            return $content;
        }

        if ($rules === null) {
            $rules = self::getSpecificRules();
        }
        if (empty($rules)) {
            return $content;
        }
        if ($ruleMap === null) {
            $ruleMap = self::specificRuleMap($rules);
        }

        $content = FontsGoogleWebFontConfig::rewrite($content, static function ($families) use ($rules, $ruleMap) {
            return self::rewriteWebFontFamilies($families, $rules, $ruleMap);
        });

        return FontsGoogleWebFontConfig::rewriteStylesheetUrls($content, static function ($url) use ($rules) {
            return self::rewriteSpecificMatchedUrl($url, $rules);
        });
    }

    /**
     * Keep non-targeted families intact and use validated Google variants when known.
     * The legacy display suffix belongs to the request, not to the removed family.
     * Subsets are request-wide, even when attached to a family being removed.
     *
     * @param array $families
     * @param array|null $rules A snapshot for the current processing pass.
     * @param array|null $ruleMap The corresponding family/weight/style lookup.
     *
     * @return array
     */
    public static function rewriteWebFontFamilies($families, $rules = null, $ruleMap = null)
    {
        $specs          = array();
        $subsets        = array();
        $suffix         = '';
        $suffixOnSubset = false;
        foreach ($families as $i => $family) {
            if ( ! preg_match('/^([a-zA-Z0-9 +_-]+)(?::((?:[1-9]00(?:italic|i)?|regular|bold|italic)(?:,(?:[1-9]00(?:italic|i)?|regular|bold|italic))*)?(?::([a-zA-Z][a-zA-Z0-9-]*(?:,[a-zA-Z][a-zA-Z0-9-]*)*))?)?(&display=(?:auto|block|swap|fallback|optional))?$/D', $family, $match) ) {
                return $families;
            }
            if (strpos($family, ':') !== false && empty($match[2]) && empty($match[3])) {
                return $families;
            }
            $specs[]     = $match[1] . ( ! empty($match[2]) ? ':' . $match[2] : '');
            $subsets[$i] = ! empty($match[3]) ? $match[3] : '';
            if ( ! empty($match[4]) ) {
                if ($i !== count($families) - 1) {
                    return $families;
                }
                $suffix         = $match[4];
                $suffixOnSubset = $subsets[$i] !== '';
            }
        }

        // Match the loader's original URL exactly for the validated registry lookup.
        // It extracts third colon sections into a single subset query parameter.
        $url = 'https://fonts.googleapis.com/css?family=' . str_replace(' ', '+', implode('%7C', $specs));
        if ( ! $suffixOnSubset ) {
            $url .= $suffix;
        }
        $requestedSubsets = array_filter($subsets, 'strlen');
        if ( ! empty($requestedSubsets) ) {
            $url .= '&subset=' . implode(',', $requestedSubsets);
        }
        if ($suffixOnSubset) {
            $url .= $suffix;
        }

        $validatedMap = self::getValidatedVariantMapForStylesheetUrl($url);
        if ($rules === null) {
            $rules = self::getSpecificRules();
        }
        if ($ruleMap === null) {
            $ruleMap = self::specificRuleMap($rules);
        }
        $targeted     = array();
        foreach ($rules as $rule) {
            $decoded = self::decodeSpecificRule($rule);
            $targeted[strtolower($decoded['family'])] = true;
        }

        $kept           = array();
        $removedSubsets = array();
        foreach ($specs as $i => $spec) {
            $parts = explode(':', $spec, 2);
            $key   = strtolower(trim(str_replace('+', ' ', $parts[0])));
            $new   = isset($targeted[$key]) ? self::rewriteCss1FamilySpec($spec, $ruleMap, $validatedMap) : $spec;
            if ($new !== '') {
                if ($subsets[$i] !== '') {
                    $new .= (strpos($new, ':') === false ? ':' : '') . ':' . $subsets[$i];
                }
                $kept[] = $new;
            } elseif ($subsets[$i] !== '') {
                $removedSubsets[] = $subsets[$i];
            }
        }
        if ( ! empty($kept) ) {
            $last = count($kept) - 1;
            if ( ! empty($removedSubsets) ) {
                // Retain each survivor's own first subset (the loader's test string)
                // and carry over subsets from removed families to the last survivor.
                $colonCount = substr_count($kept[$last], ':');
                $kept[$last] .= ($colonCount === 2 ? ',' : str_repeat(':', 2 - $colonCount))
                    . implode(',', $removedSubsets);
            }
            $kept[$last] .= $suffix;
        }

        return $kept;
    }

    /**
     * Selectively rewrite/remove Google Fonts LINK, STYLE and inline JS references.
     *
     * @param string $htmlSource
     *
     * @return string
     */
    public static function cleanHtmlSourceSpecific($htmlSource)
    {
        if ( ! is_string($htmlSource) || $htmlSource === '' ) {
            return $htmlSource;
        }
        $rules = self::getSpecificRules();
        if (empty($rules)) {
            return $htmlSource;
        }
        foreach (array_reverse(self::getEditableStyleTokens($htmlSource)) as $token) {
            if ($token['tag_name'] === 'LINK') {
                if ( ! isset($token['attributes']['href']) ) {
                    continue;
                }
                $href = $token['attributes']['href'];
                $newHref = self::urlHasAllowedHost($href, self::$stylesheetHosts)
                    ? self::rewriteStylesheetUrlForSpecificRemoval($href, $rules)
                    : self::rewriteLocalStylesheetUrlForSpecificRemoval($href, $rules);
                if ($newHref === $href) {
                    continue;
                }
                if ($newHref === '') {
                    $replacement = '';
                } else {
                    $replacement = self::rewriteHtmlAttribute($token['opening'],
                        $token['attribute_ranges'], 'href', $newHref);
                }
                $htmlSource = substr_replace($htmlSource, $replacement, $token['offset'], $token['length']);
            } else {
                $css = self::applySpecificRemovalToCss($token['content']);
                // Empty runtime placeholders and unrelated CSS must survive.
                // Only a style actually emptied by font removal can disappear.
                if ($css === $token['content']) {
                    continue;
                }
                if (trim($css) === '') {
                    $htmlSource = substr_replace($htmlSource, '', $token['offset'], $token['length']);
                } else {
                    $htmlSource = substr_replace($htmlSource, $css,
                        $token['offset'] + strlen($token['opening']), strlen($token['content']));
                }
            }
        }
        if (stripos($htmlSource, '<script') !== false
            && (stripos($htmlSource, 'fonts.googleapis.com') !== false || strpos($htmlSource, 'WebFont') !== false)) {
            $scripts = HtmlCleanup::getScriptTokens($htmlSource);
            if ($scripts === false) {
                return $htmlSource;
            }

            $ruleMap = null;
            foreach (array_reverse($scripts) as $script) {
                $attributes = $script['attributes'];
                $content    = $script['content'];
                if (isset($attributes['data-wpacu-skip']) || isset($attributes['src']) || $script['manager']
                    || (strpos($content, 'WebFont') === false && stripos($content, 'fonts.googleapis.com') === false)) {
                    continue;
                }

                // Honor the original type of known delayed JavaScript without
                // treating arbitrary data-script types as executable content.
                $type = isset($attributes['type']) ? strtolower(trim($attributes['type'])) : '';
                if ($type === 'rocketlazyloadscript') {
                    if (isset($attributes['data-rocket-src'])) {
                        continue;
                    }
                    $type = isset($attributes['data-rocket-type'])
                        ? strtolower(trim($attributes['data-rocket-type'])) : '';
                }
                if (($type !== 'module' && (strpos($type, ';') !== false || ! Misc::isClassicScriptType($type)))
                    || ($type === '' && isset($attributes['language'])
                        && ! in_array(strtolower(trim($attributes['language'])), array('', 'javascript', 'ecmascript'), true)) ) {
                    continue;
                }

                if ($ruleMap === null) {
                    $ruleMap = self::specificRuleMap($rules);
                }
                $rewritten = self::rewriteSpecificJavaScriptReferences($content, $rules, $ruleMap);
                if ($rewritten !== $content) {
                    $htmlSource = substr_replace($htmlSource, $rewritten,
                        $script['offset'] + strlen($script['opening']), strlen($content));
                }
            }
        }
        return $htmlSource;
    }

    /**
     * Apply selective rules to CSS imports, URL strings and Google @font-face blocks.
     */
    public static function applySpecificRemovalToCss($cssContent)
    {
        if (! self::hasSpecificRules() || ! is_string($cssContent) || $cssContent === '') {
            return $cssContent;
        }
        $rules = self::getSpecificRules();
        if (stripos($cssContent, '@import') !== false && stripos($cssContent, 'fonts.googleapis.com') !== false) {
            $pattern = '#@import\s+(?:(?<url_function>url)\(\s*)?(?<quote>["\']?)(?<target>(?:https?:)?//fonts\.googleapis\.com/.*?)(?P=quote)(?(url_function)\s*\))\s*[^;]*;#is';
            $changed = preg_replace_callback($pattern, static function($matches) use ($rules) {
                $target = strpos($matches['target'], '//') === 0 ? 'https:' . $matches['target'] : $matches['target'];
                $newUrl = self::rewriteStylesheetUrlForSpecificRemoval($target, $rules);
                if ($newUrl === '') {
                    return '';
                }
                return str_replace($matches['target'], strpos($matches['target'], '//') === 0 ? preg_replace('#^https:#', '', $newUrl) : $newUrl, $matches[0]);
            }, $cssContent);
            if (is_string($changed)) {
                $cssContent = $changed;
            }
        }
        $cssContent = self::rewriteSpecificStylesheetReferences($cssContent);
        return self::cleanFontFaceReferencesSpecific($cssContent);
    }

    /**
     * Remove matching Google-hosted @font-face blocks by family + weight + style.
     */
    public static function cleanFontFaceReferencesSpecific($cssContent)
    {
        if (! self::hasSpecificRules() || stripos((string) $cssContent, '@font-face') === false) {
            return $cssContent;
        }
        $ruleMap = self::specificRuleMap(self::getSpecificRules());
        $changed = preg_replace_callback('#@font-face\s*\{.*?}#is', static function($matches) use ($ruleMap) {
            $block = $matches[0];
            if (! self::containsGoogleFontFileReference($block)) {
                return $block;
            }
            if (! preg_match('/font-family\s*:\s*(?:["\']([^"\']+)["\']|([^;}]+))/i', $block, $familyMatch)) {
                return $block;
            }
            $family = trim(! empty($familyMatch[1]) ? $familyMatch[1] : $familyMatch[2]);
            $weight = '400';
            $style = 'normal';
            if (preg_match('/font-weight\s*:\s*([^;}]+)/i', $block, $m)) {
                $weight = trim($m[1]);
            }
            if (preg_match('/font-style\s*:\s*([^;}]+)/i', $block, $m) && strtolower(trim($m[1])) === 'italic') {
                $style = 'italic';
            }
            return self::variantIsSelected($family, $weight, $style, $ruleMap) ? '' : $block;
        }, $cssContent);
        return is_string($changed) ? $changed : $cssContent;
    }

    /** @return array */
    private static function specificRuleMap($rules)
    {
        $map = array();
        foreach ((array) $rules as $rule) {
            $decoded = self::decodeSpecificRule($rule);
            if (empty($decoded)) {
                continue;
            }
            $map[strtolower($decoded['family']) . '|' . $decoded['weight'] . '|' . $decoded['style']] = true;
        }
        return $map;
    }

    /** @return bool */
    private static function variantIsSelected($family, $weight, $style, $ruleMap)
    {
        return isset($ruleMap[strtolower(trim($family)) . '|' . trim($weight) . '|' . ($style === 'italic' ? 'italic' : 'normal')]);
    }

    /** @return array */
    private static function parseCss1FamilySpec($spec)
    {
        $parts = explode(':', trim($spec), 2);
        $family = trim(str_replace('+', ' ', $parts[0]));
        if ($family === '') {
            return array();
        }
        $tokens = isset($parts[1]) && trim($parts[1]) !== '' ? explode(',', $parts[1]) : array('400');
        $variants = array();
        foreach ($tokens as $token) {
            $token = strtolower(trim($token));
            $hasShortItalicSuffix = preg_match('/^[1-9]00i$/', $token) === 1;
            $style = strpos($token, 'italic') !== false || $hasShortItalicSuffix ? 'italic' : 'normal';
            $weightToken = $hasShortItalicSuffix ? substr($token, 0, -1) : str_replace('italic', '', $token);
            if ($weightToken === '' || $weightToken === 'regular') {
                $weight = '400';
            } elseif ($weightToken === 'bold') {
                $weight = '700';
            } else {
                $weight = $weightToken;
            }
            $variants[] = array('family' => $family, 'weight' => $weight, 'style' => $style, 'raw_variant' => trim($token));
        }
        return $variants;
    }

    /** @return array */
    private static function parseCss2FamilySpec($spec)
    {
        $parts = explode(':', trim($spec), 2);
        $family = trim(str_replace('+', ' ', $parts[0]));
        if ($family === '') {
            return array();
        }
        if (! isset($parts[1]) || strpos($parts[1], '@') === false) {
            return array(array('family' => $family, 'weight' => '400', 'style' => 'normal', 'raw_variant' => '400'));
        }
        list($axesRaw, $valuesRaw) = explode('@', $parts[1], 2);
        $axes = array_map('trim', explode(',', $axesRaw));
        $variants = array();
        foreach (explode(';', $valuesRaw) as $tupleRaw) {
            $values = array_map('trim', explode(',', $tupleRaw));
            $axisValues = array();
            foreach ($axes as $i => $axis) {
                if (isset($values[$i])) {
                    $axisValues[$axis] = $values[$i];
                }
            }
            $weight = isset($axisValues['wght']) ? $axisValues['wght'] : '400';
            $style = isset($axisValues['ital']) && (string) $axisValues['ital'] === '1' ? 'italic' : 'normal';
            $variants[] = array('family' => $family, 'weight' => $weight, 'style' => $style, 'raw_variant' => trim($tupleRaw));
        }
        return $variants;
    }

    /**
     * @param string $spec
     * @param array $ruleMap
     * @param array $validatedMap
     *
     * @return string
     */
    private static function rewriteCss1FamilySpec($spec, $ruleMap, $validatedMap = array())
    {
        $parts     = explode(':', trim($spec), 2);
        $familyRaw = trim($parts[0]);
        $family    = str_replace('+', ' ', $familyRaw);
        if ($family === '' || (isset($parts[1]) && trim($parts[1]) === '')) {
            return $spec;
        }

        $tokens = isset($parts[1]) && trim($parts[1]) !== '' ? explode(',', $parts[1]) : array('400');
        $kept   = array();
        foreach ($tokens as $tokenRaw) {
            if (trim($tokenRaw) === '') {
                return $spec;
            }
            $token = strtolower(trim($tokenRaw));
            $hasShortItalicSuffix = preg_match('/^[1-9]00i$/', $token) === 1;
            $style = strpos($token, 'italic') !== false || $hasShortItalicSuffix ? 'italic' : 'normal';
            $weightToken = $hasShortItalicSuffix ? substr($token, 0, -1) : str_replace('italic', '', $token);
            $weight = ($weightToken === '' || $weightToken === 'regular') ? '400' : ($weightToken === 'bold' ? '700' : $weightToken);
            if (self::variantWasValidated($family, $weight, $style, $validatedMap)
                && ! self::variantIsSelected($family, $weight, $style, $ruleMap)
            ) {
                $kept[] = trim($tokenRaw);
            }
        }
        if (empty($kept)) {
            return '';
        }
        if (count($kept) === count($tokens)) {
            return $spec;
        }

        return $familyRaw . ':' . implode(',', $kept);
    }

    /**
     * @param string $spec
     * @param array $ruleMap
     * @param array $validatedMap
     *
     * @return string
     */
    private static function rewriteCss2FamilySpec($spec, $ruleMap, $validatedMap = array())
    {
        $parts     = explode(':', trim($spec), 2);
        $familyRaw = trim($parts[0]);
        $family    = str_replace('+', ' ', $familyRaw);
        if ($family === '' || (isset($parts[1]) && strpos($parts[1], '@') === false)) {
            return $spec;
        }
        if ( ! isset($parts[1]) ) {
            return (! self::variantWasValidated($family, '400', 'normal', $validatedMap) || self::variantIsSelected($family, '400', 'normal', $ruleMap)) ? '' : $spec;
        }
        list($axesRaw, $valuesRaw) = explode('@', $parts[1], 2);
        $axes = array_map('trim', explode(',', $axesRaw));
        $kept = array();
        if (in_array('', $axes, true)) {
            return $spec;
        }

        foreach (explode(';', $valuesRaw) as $tupleRaw) {
            $values = array_map('trim', explode(',', $tupleRaw));
            if (count($values) !== count($axes) || in_array('', $values, true)) {
                return $spec;
            }
            $axisValues = array();
            foreach ($axes as $i => $axis) {
                if (isset($values[$i])) {
                    $axisValues[$axis] = $values[$i];
                }
            }
            $weight = isset($axisValues['wght']) ? $axisValues['wght'] : '400';
            $style = isset($axisValues['ital']) && (string) $axisValues['ital'] === '1' ? 'italic' : 'normal';
            if (self::variantWasValidated($family, $weight, $style, $validatedMap)
                && ! self::variantIsSelected($family, $weight, $style, $ruleMap)
            ) {
                $kept[] = trim($tupleRaw);
            }
        }
        if (empty($kept)) {
            return '';
        }
        if (count($kept) === count(explode(';', $valuesRaw))) {
            return $spec;
        }

        return $familyRaw . ':' . $axesRaw . '@' . implode(';', $kept);
    }

    /**
     * @return bool
     */
    public static function preventAnyChange()
    {
        return wpacuIsDefinedConstant('WPACU_ALLOW_ONLY_UNLOAD_RULES');
    }
}
