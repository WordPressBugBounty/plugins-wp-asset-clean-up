<?php
namespace WpAssetCleanUp;

use WpAssetCleanUp\OptimiseAssets\MinifyCss;
use WpAssetCleanUp\OptimiseAssets\MinifyJs;

/**
 *
 */
class DebugOptimizationDetails
{
    /**
     * @var array
     */
    private static $rows = array();
    /**
     * @var bool
     */
    private static $cachedMinification = false;

    /**
     * @return bool
     */
    public static function enabled()
    {
        return isset($_GET['wpacu_debug']) && function_exists('is_user_logged_in') && Menu::userCanAccessPlugin();
    }

    /**
     * @param $type
     * @param $asset
     * @param $operation
     * @param $status
     * @param $reason
     *
     * @return void
     */
    public static function record($type, $asset, $operation, $status, $reason)
    {
        if ( ! self::enabled() ) {
            return;
        }
        $handle          = isset($asset->handle) ? (string)$asset->handle : '';
        $generatedHandle = ! empty($asset->wpacu_generated_handle);
        $url             = isset($asset->src) ? (string)$asset->src : '';

        if ( ! self::includeSource($url) ) {
            return;
        }

        $host = strtolower((string)parse_url($url, PHP_URL_HOST));

        if ($host === 'fonts.googleapis.com') {
            if ( ! \WpAssetCleanUp\OptimiseAssets\FontsGoogleLocal::isEnabled() ||
                 \WpAssetCleanUp\OptimiseAssets\FontsGoogleRemove::rewriteStylesheetUrlForSpecificRemoval($url) === '' ) {
                return;
            }

            if ($operation !== 'Local Google Fonts') {
                $status = 'Skipped';
                $reason = apply_filters('wpacu_google_fonts_local_should_skip_url', false, $url, 'html')
                    ? __('Excluded from local hosting and automatic processing by a configured exclusion.',
                        'wp-asset-clean-up')
                    : __('No local Google Fonts cache replacement was recorded for this stylesheet in this request.',
                        'wp-asset-clean-up');
            }

            $operation = 'Local Google Fonts';
            $key       = $type . '|' . self::googleFontsReportKey($url) . '|' . $operation;

            if (isset(self::$rows[$key])) {
                if ($handle === '') {
                    $handle          = self::$rows[$key]['handle'];
                    $generatedHandle = self::$rows[$key]['generatedHandle'];
                }

                if ( (self::$rows[$key]['status'] === 'Cached' && $status !== 'Cached') ||
                    (self::$rows[$key]['status'] === 'Failed' && $status === 'Skipped') ) {
                    self::$rows[$key]['handle']          = $handle;
                    self::$rows[$key]['generatedHandle'] = $generatedHandle;

                    return;
                }
            }
        } else {
            $key = $type . '|' . $handle . '|' . $url . '|' . $operation;
        }

        if (count(self::$rows) >= 500 && ! isset(self::$rows[$key])) {
            return;
        }

        self::$rows[$key] = compact('type', 'handle', 'url', 'operation', 'status', 'reason', 'generatedHandle');
    }

    /**
     * @param $type
     * @param $asset
     * @param $operation
     * @param $status
     * @param $reason
     *
     * @return void
     */
    public static function recordDebug($type, $asset, $operation, $status, $reason)
    {
        if (self::enabled()) {
            self::record($type, $asset, $operation, $status, $reason);
        }
    }

    /**
     * @return void
     */
    public static function markCachedMinification()
    {
        self::$cachedMinification = true;
    }

    /**
     * @param $url
     *
     * @return mixed|string
     */
    private static function googleFontsReportKey($url)
    {
        // Group display variants only in the report; keep cache identities untouched.
        $parts = parse_url(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));

        if ( ! is_array($parts)) {
            return $url;
        }

        $pairs = array();

        foreach (explode('&', isset($parts['query']) ? $parts['query'] : '') as $pair) {
            $name = explode('=', $pair, 2);

            if ($pair !== '' && strtolower(rawurldecode($name[0])) !== 'display') {
                $pairs[] = $pair;
            }
        }

        return 'fonts.googleapis.com' . (isset($parts['path']) ? $parts['path'] : '')
               . '?' . implode('&', $pairs);
    }

    /**
     * @param $type
     * @param $asset
     * @param $operation
     * @param $reason
     * @param $before
     * @param $after
     *
     * @return mixed
     */
    public static function observe($type, $asset, $operation, $reason, $before, $after)
    {
        if ($asset !== null && self::enabled() && $before !== $after) {
            self::record($type, $asset, $operation, 'Content changed', $reason);
        }

        return $after;
    }

    /**
     * @param $url
     *
     * @return bool
     */
    public static function includeSource($url)
    {
        $parts = parse_url(trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8')));

        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
            return false;
        }

        // Relative sources belong to this site.
        if (empty($parts['host'])) {
            return ! isset($parts['scheme']);
        }

        $host         = strtolower(rtrim($parts['host'], '.'));
        $allowedHosts = array('fonts.googleapis.com');

        foreach (array(site_url(), home_url()) as $localUrl) {
            $localHost = parse_url($localUrl, PHP_URL_HOST);

            if (is_string($localHost)) {
                $allowedHosts[] = strtolower(rtrim($localHost, '.'));
            }
        }

        return in_array($host, $allowedHosts, true);
    }

    /**
     * @param $type
     * @param $asset
     * @param $content
     *
     * @return string|string[]|null
     * @throws \Throwable
     */
    public static function minify($type, $asset, $content)
    {
        if ( ! self::enabled()) {
            return $type === 'css' ? MinifyCss::applyMinification($content) : MinifyJs::applyMinification($content);
        }

        self::$cachedMinification = false;

        try {
            $result = $type === 'css' ? MinifyCss::applyMinification($content) : MinifyJs::applyMinification($content);
        } catch (\Throwable $error) {
            self::record($type, $asset, 'Minification', 'Failed',
                __('The minifier raised an error.', 'wp-asset-clean-up'));
            throw $error;
        }

        $minifierClass = $type === 'css' ? '\\MatthiasMullieWpacu\\Minify\\CSS' : '\\MatthiasMullieWpacu\\Minify\\JS';

        if ( ! class_exists($minifierClass)) {
            self::record($type, $asset, 'Minification', 'Failed',
                __('The minification library was unavailable.', 'wp-asset-clean-up'));
        } elseif (trim($content) === trim((string)$result)) {
            self::record($type, $asset, 'Minification', 'No change needed', self::$cachedMinification
                ? __('A previous check of this exact content found no minification changes. That result was reused.',
                    'wp-asset-clean-up')
                : __('Minification produced no changes to this content. The filename does not determine whether minification is needed.',
                    'wp-asset-clean-up'));
        } else {
            self::record($type, $asset, 'Minification', 'Content changed',
                __('Minification changed the content during this request.', 'wp-asset-clean-up'));
        }

        return $result;
    }

    /**
     * @return array
     */
    public static function getRows()
    {
        return self::enabled() ? array_values(self::$rows) : array();
    }
}
