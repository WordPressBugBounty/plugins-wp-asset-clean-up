<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Misc;

/**
 * Class DynamicLoadedAssets
 * @package WpAssetCleanUp
 */
class DynamicLoadedAssets
{
	/**
	 * @param $from
	 * @param $value
	 *
	 * @return bool|mixed|string
     * @noinspection NestedPositiveIfStatementsInspection
     */
	public static function getAssetContentFrom($from, $value)
	{
		$assetContent = '';

		if ($from === 'simple-custom-css') {
			/*
			 * Special Case: "Simple Custom CSS" Plugin
			 *
			 * /?sccss=1
			 *
			 * As it is (no minification or optimization), it adds extra load time to the page
			 * as the CSS is read via PHP and all the WP environment is loading
			 */
			if (! $assetContent = self::getSimpleCustomCss()) {
				return false;
			}
		}

		if ($from === 'dynamic') { // /? .php? etc.
			$assetUrl = isset($value->src) ? self::getAllowedDynamicAssetUrl($value->src) : false;

			if (! $assetUrl) {
				return array();
			}

            // Do not follow redirects: a same-host endpoint could redirect the server
            // request to a private or link-local destination. Redirected assets are
            // simply left unoptimized.
            $requestArgs = array(
                'redirection' => 0,
                'timeout'     => 10,
            );

            $assetHost = strtolower((string) wp_parse_url($assetUrl, PHP_URL_HOST));
            $siteHost  = strtolower((string) wp_parse_url(site_url('/'), PHP_URL_HOST));

            // Basic-auth credentials are meant for a password-protected local site.
            // Never forward them to a configured CDN origin.
            if ($assetHost !== '' && $assetHost === $siteHost
                && defined('WPACU_HEADERS_AUTH_BASIC_USR') && defined('WPACU_HEADERS_AUTH_BASIC_PWD')) {
                $requestArgs['headers'] = array(
                    'Authorization' => 'Basic ' . base64_encode(
                        constant('WPACU_HEADERS_AUTH_BASIC_USR') . ':' . constant('WPACU_HEADERS_AUTH_BASIC_PWD')
                    ),
                );
            }

            $response = wp_remote_get($assetUrl, $requestArgs);

			if (wp_remote_retrieve_response_code($response) !== 200) {
				return false;
			}

			if (! $assetContent = wp_remote_retrieve_body($response)) {
				return false;
			}
		}

		return $assetContent;
	}

	/**
	 * Dynamic assets are fetched server-side, so a host-only comparison is not
	 * sufficient. Restrict requests to a configured local/CDN origin and to
	 * standard web ports, plus an explicitly configured site/CDN port.
	 *
	 * @param mixed $url
	 *
	 * @return string|false Normalized, allowed URL or false.
	 */
	private static function getAllowedDynamicAssetUrl($url)
	{
		if (! is_string($url) || trim($url) === '') {
			return false;
		}

		$url = trim($url);

		if (strncmp($url, '//', 2) === 0) {
			$url = (Misc::isHttpsSecure() ? 'https:' : 'http:') . $url;
		}

		$urlParts = wp_parse_url($url);

		if (! is_array($urlParts) || empty($urlParts['scheme']) || empty($urlParts['host'])) {
			return false;
		}

		$scheme = strtolower($urlParts['scheme']);

		if (! in_array($scheme, array('http', 'https'), true) || isset($urlParts['user']) || isset($urlParts['pass'])) {
			return false;
		}

		if (! OptimizeCommon::isSourceFromSameHost($url)) {
			return false;
		}

		$assetHost   = strtolower(rtrim($urlParts['host'], '.'));
		$assetPort   = isset($urlParts['port']) ? (int) $urlParts['port'] : ($scheme === 'https' ? 443 : 80);
		$allowedPorts = array(80, 443);
		$trustedUrls  = array(site_url('/'), home_url('/'));

		foreach (OptimizeCommon::getAnyCdnUrls() as $cdnUrl) {
			$cdnUrl = trim($cdnUrl);

			if ($cdnUrl === '') {
				continue;
			}

			if (strncmp($cdnUrl, '//', 2) === 0) {
				$cdnUrl = $scheme . ':' . $cdnUrl;
			} elseif (strpos($cdnUrl, '://') === false) {
				$cdnUrl = $scheme . '://' . ltrim($cdnUrl, '/');
			}

			$trustedUrls[] = $cdnUrl;
		}

		foreach ($trustedUrls as $trustedUrl) {
			$trustedParts = wp_parse_url($trustedUrl);

			if (! is_array($trustedParts) || empty($trustedParts['host'])) {
				continue;
			}

			$trustedHost = strtolower(rtrim($trustedParts['host'], '.'));

			if ($trustedHost === $assetHost && isset($trustedParts['port'])) {
				$allowedPorts[] = (int) $trustedParts['port'];
			}
		}

		$allowedPorts = apply_filters('wpacu_dynamic_asset_allowed_ports', array_values(array_unique($allowedPorts)), $url);
		$allowedPorts = array_filter(array_map('absint', is_array($allowedPorts) ? $allowedPorts : array()));

		return in_array($assetPort, $allowedPorts, true) ? $url : false;
	}

	/**
	 * "Simple Custom CSS" (better retrieval, especially for localhost and password-protected sites)
	 *
	 * @return string
	 */
	public static function getSimpleCustomCss()
	{
		$sccssOptions    = get_option('sccss_settings');
		$sccssRawContent = isset($sccssOptions['sccss-content']) ? $sccssOptions['sccss-content'] : '';
		$cssContent      = wp_kses($sccssRawContent, array('\'', '\"'));
		$cssContent      = str_replace('&gt;', '>', $cssContent);

		return trim($cssContent);
	}
}
