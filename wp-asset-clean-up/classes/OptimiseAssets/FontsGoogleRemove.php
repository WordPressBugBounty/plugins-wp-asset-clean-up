<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Misc;

/**
 * Class FontsGoogleRemove
 * @package WpAssetCleanUp\OptimiseAssets
 */
class FontsGoogleRemove
{
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
     * Extract one HTML attribute without considering text in other attributes.
     *
     * @param string $tag
     * @param string $attributeName
     *
     * @return string|null
     */
    private static function extractHtmlAttribute($tag, $attributeName)
    {
        $attributeName = preg_quote($attributeName, '#');
        $pattern = '#\b' . $attributeName . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))#i';

        if ( ! preg_match($pattern, $tag, $matches) ) {
            return null;
        }

        foreach (array(1, 2, 3) as $matchIndex) {
            if (isset($matches[$matchIndex]) && $matches[$matchIndex] !== '') {
                return html_entity_decode($matches[$matchIndex], ENT_QUOTES, 'UTF-8');
            }
        }

        return '';
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

        $cleanedHtmlSource = preg_replace_callback(
            '#<link\b[^>]*>#i',
            static function($matches) {
                $linkTag = $matches[0];

                // Extra validation: a LINK element should not leave any text
                // once tags are stripped.
                if (trim(strip_tags($linkTag)) !== '') {
                    return $linkTag;
                }

                // Explicit opt-out for integrations that must remain untouched.
                if (Misc::hasExactDataAttr($linkTag, 'data-wpacu-skip')) {
                    return $linkTag;
                }

                $href = self::extractHtmlAttribute($linkTag, 'href');

                return $href !== null && self::urlHasAllowedHost($href, self::$stringsToCheck)
                    ? ''
                    : $linkTag;
            },
            $htmlSource
        );

        return is_string($cleanedHtmlSource) ? $cleanedHtmlSource : $htmlSource;
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

        $cleanedHtmlSource = preg_replace_callback(
            '#(<\s*style\b[^>]*>)(.*?)(</\s*style\s*>)#is',
            static function($matches) {
                $openingTag  = $matches[1];
                $cssContent  = $matches[2];
                $closingTag  = $matches[3];
                $fullStyleTag = $matches[0];

                // Explicit opt-out for integrations that must remain untouched.
                if (Misc::hasExactDataAttr($openingTag, 'data-wpacu-skip')) {
                    return $fullStyleTag;
                }

                if (! self::containsAnyGoogleFontsReference($cssContent)) {
                    return $fullStyleTag;
                }

                // Remove only Google Fonts imports. Unrelated local or third-party
                // @import statements must remain available.
                $cleanedCssContent = self::stripGoogleFontImportsFromCss($cssContent);
                $cleanedCssContent = self::cleanFontFaceReferences($cleanedCssContent);

                // An empty STYLE element has no value after the matching rules are
                // removed, so avoid leaving it in the final HTML source.
                if (trim($cleanedCssContent) === '') {
                    return '';
                }

                return $openingTag . $cleanedCssContent . $closingTag;
            },
            $htmlSource
        );

        return is_string($cleanedHtmlSource) ? $cleanedHtmlSource : $htmlSource;
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
     * @return bool
     */
    public static function preventAnyChange()
    {
        return wpacuIsDefinedConstant('WPACU_ALLOW_ONLY_UNLOAD_RULES');
    }
}
