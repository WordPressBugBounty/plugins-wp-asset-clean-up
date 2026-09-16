<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

/**
 * Pure URL parsing and replacement helpers used by Google Fonts local hosting.
 *
 * This class deliberately has no WordPress dependencies so its security rules
 * can be exercised by the standalone test suite.
 */
class FontsGoogleLocalUrl
{
    const MAX_STYLESHEET_URL_LENGTH = 10000;

    /**
     * @param string $url
     *
     * @return string
     */
    public static function canonicalizeStylesheetUrl($url)
    {
        return self::canonicalizeGoogleUrl($url, 'fonts.googleapis.com', array('/css', '/css2', '/icon'), true);
    }

    /**
     * @param string $url
     *
     * @return string
     */
    public static function canonicalizeFontFileUrl($url)
    {
        return self::canonicalizeGoogleUrl($url, 'fonts.gstatic.com', array(), false);
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    public static function isAllowedFontFileUrl($url)
    {
        return self::canonicalizeFontFileUrl($url) !== '';
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public static function fingerprint($value)
    {
        return hash('sha256', (string) $value);
    }

    /**
     * Find Google Fonts stylesheet URLs while retaining the exact source token
     * needed to replace HTML entities or slash-escaped JavaScript safely.
     *
     * @param string $content
     * @param bool   $ignoreHtmlComments
     *
     * @return array<int,array<string,mixed>>
     */
    public static function extractStylesheetReferences($content, $ignoreHtmlComments = false)
    {
        if (! is_string($content) || $content === '' || stripos($content, 'fonts.googleapis.com') === false) {
            return array();
        }

        $slash = '(?:/|\\\\/)';
        $pattern = '~(?:https?:)?' . $slash . $slash
            . 'fonts\.googleapis\.com' . $slash
            . '(?:css2?|icon)\?[^\s<>"\'\)]+~i';

        if (! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) || empty($matches[0])) {
            return array();
        }

        $references = array();
        $commentRanges = $ignoreHtmlComments ? self::getHtmlCommentRanges($content) : array();

        foreach ($matches[0] as $match) {
            $rawReference = self::trimTrailingEscapes((string) $match[0]);
            $offset = (int) $match[1];

            if ($ignoreHtmlComments && self::offsetIsWithinRanges($offset, $commentRanges)) {
                continue;
            }

            $canonicalUrl = self::canonicalizeStylesheetUrl($rawReference);

            if ($canonicalUrl === '') {
                continue;
            }

            $references[] = array(
                'raw'             => $rawReference,
                'url'             => $canonicalUrl,
                'fingerprint'     => self::fingerprint($canonicalUrl),
                'offset'          => $offset,
                'escaped_slashes' => strpos($rawReference, '\\/') !== false,
                'html_encoded'    => stripos($rawReference, '&amp;') !== false,
            );
        }

        return $references;
    }

    /**
     * @param string $content
     * @param array  $reference
     * @param string $localUrl
     *
     * @return string
     */
    public static function replaceReference($content, $reference, $localUrl)
    {
        if (! is_string($content) || ! is_array($reference) || empty($reference['raw']) || ! is_string($localUrl) || $localUrl === '') {
            return $content;
        }

        $replacement = $localUrl;

        if (! empty($reference['html_encoded'])) {
            $replacement = str_replace('&', '&amp;', $replacement);
        }

        if (! empty($reference['escaped_slashes'])) {
            $replacement = str_replace('/', '\\/', $replacement);
        }

        if (isset($reference['offset'])) {
            $offset = (int) $reference['offset'];
            $rawLength = strlen($reference['raw']);

            if ($offset >= 0 && substr($content, $offset, $rawLength) === $reference['raw']) {
                return substr_replace($content, $replacement, $offset, $rawLength);
            }
        }

        return str_replace($reference['raw'], $replacement, $content);
    }

    /**
     * @param string $content
     *
     * @return array<int,array{0:int,1:int}>
     */
    private static function getHtmlCommentRanges($content)
    {
        $ranges = array();
        $cursor = 0;
        $contentLength = strlen($content);

        while (($start = strpos($content, '<!--', $cursor)) !== false) {
            $close = strpos($content, '-->', $start + 4);
            $end = $close === false ? $contentLength : $close + 3;
            $ranges[] = array($start, $end);

            if ($close === false) {
                break;
            }

            $cursor = $end;
        }

        return $ranges;
    }

    /**
     * @param int   $offset
     * @param array $ranges
     *
     * @return bool
     */
    private static function offsetIsWithinRanges($offset, $ranges)
    {
        foreach ($ranges as $range) {
            if ($offset < $range[0]) {
                return false;
            }

            if ($offset < $range[1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $css
     *
     * @return array<int,array<string,mixed>>
     */
    public static function extractFontFileReferences($css)
    {
        if (! is_string($css) || $css === '' || stripos($css, 'fonts.gstatic.com') === false) {
            return array();
        }

        $slash = '(?:/|\\\\/)';
        $pattern = '~(?:https?:)?' . $slash . $slash
            . 'fonts\.gstatic\.com' . $slash
            . '[^\s<>"\'\)]+~i';

        if (! preg_match_all($pattern, $css, $matches) || empty($matches[0])) {
            return array();
        }

        $references = array();
        $seen = array();

        foreach ($matches[0] as $rawReference) {
            $rawReference = self::trimTrailingEscapes((string) $rawReference);
            $canonicalUrl = self::canonicalizeFontFileUrl($rawReference);

            $dedupeKey = $rawReference . "\n" . $canonicalUrl;
            if ($canonicalUrl === '' || isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $references[] = array(
                'raw'             => $rawReference,
                'url'             => $canonicalUrl,
                'escaped_slashes' => strpos($rawReference, '\\/') !== false,
            );
        }

        return $references;
    }

    /**
     * @param string $url
     * @param string $allowedHost
     * @param array  $allowedPaths
     * @param bool   $limitLength
     *
     * @return string
     */
    private static function canonicalizeGoogleUrl($url, $allowedHost, $allowedPaths, $limitLength)
    {
        if (! is_string($url)) {
            return '';
        }

        $url = trim($url, " \t\n\r\0\x0B\"'");

        if ($url === '' || ($limitLength && strlen($url) > self::MAX_STYLESHEET_URL_LENGTH)) {
            return '';
        }

        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = preg_replace('/\\\\u0*026/i', '&', $url);
        $url = str_replace('\\/', '/', $url);

        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        if (stripos($url, 'http://') === 0) {
            $url = 'https://' . substr($url, 7);
        }

        if (stripos($url, 'https://') !== 0 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return '';
        }

        if ($limitLength && strlen($url) > self::MAX_STYLESHEET_URL_LENGTH) {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts)
            || empty($parts['scheme'])
            || strtolower($parts['scheme']) !== 'https'
            || empty($parts['host'])
            || strtolower($parts['host']) !== $allowedHost
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            return '';
        }

        $path = isset($parts['path']) ? $parts['path'] : '/';

        if (! empty($allowedPaths)) {
            $normalizedPath = rtrim($path, '/');
            if (! in_array($normalizedPath, $allowedPaths, true)) {
                return '';
            }
            $path = $normalizedPath;
        } elseif ($path === '' || strpos($path, '/') !== 0) {
            return '';
        }

        $canonical = 'https://' . $allowedHost . $path;

        if (isset($parts['query']) && $parts['query'] !== '') {
            $canonical .= '?' . $parts['query'];
        }

        return $canonical;
    }

    /**
     * A regex that stops before an escaped quote can leave the escape slash as
     * the final byte. It is syntax, not part of the URL.
     *
     * @param string $value
     *
     * @return string
     */
    private static function trimTrailingEscapes($value)
    {
        while ($value !== '' && substr($value, -1) === '\\') {
            $value = substr($value, 0, -1);
        }

        return $value;
    }
}
