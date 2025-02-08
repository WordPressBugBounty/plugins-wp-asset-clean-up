<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

/**
 * Class Misc
 * contains various common functions that are used by the plugin
 * @package WpAssetCleanUp
 */
class Misc
{
    /**
     * @var
     */
    public static $showOnFront;

    /**
     * @param $string
     * @param $start
     * @param $end
     * @return string
     */
    public static function extractBetween($string, $start, $end)
    {
        $pos = stripos($string, $start);

        $str = substr($string, $pos);

        $strTwo = substr($str, strlen($start));

        $secondPos = stripos($strTwo, $end);

        $strThree = substr($strTwo, 0, $secondPos);

        return trim($strThree); // remove whitespaces;
    }

	/**
	 * @param $string
	 * @param $endsWithString
	 * @return bool
	 */
	public static function endsWith($string, $endsWithString)
	{
		$stringLen = strlen($string);
		$endsWithStringLen = strlen($endsWithString);

		if ($endsWithStringLen > $stringLen) {
			return false;
		}

		return substr_compare(
			        $string,
			        $endsWithString,
			        $stringLen - $endsWithStringLen, $endsWithStringLen
		        ) === 0;
	}

	/**
	 * @return bool
	 */
    public static function isHttpsSecure()
	{
		if ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ) {
			return true;
		}

		if ( ( ! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' )
		     || ( ! empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on' ) ) {
			// Is it behind a load balancer?
			return true;
		}

		return false;
	}

    /**
     * @return bool
     */
    public static function maybeIsSiteGround()
    {
        // Bail if open_basedir restrictions are set, and we are not able to check certain directories.
        if ( ! empty( ini_get( 'open_basedir' ) ) ) {
            return false;
        }

        $fileExists = @file_exists( '/etc/yum.repos.d/baseos.repo' ) && @file_exists( '/Z' );

        if ( ! $fileExists ) {
            return false;
        }

        $possibleWpConfigFile = dirname(WP_CONTENT_DIR).'/wp-config.php';

        $hasSiteGroundSignatureInWpConfigFile = false; // default

        if ( $wpConfigContents = @file_get_contents($possibleWpConfigFile) ) {
            $hasSiteGroundSignatureInWpConfigFile = strpos($wpConfigContents, '// By SiteGround Optimizer') !== false; // 'true' or 'false'
        }

        if ( ! $hasSiteGroundSignatureInWpConfigFile ) {
            return false;
        }

        // All the conditions were passed
        // Finally, return true
        return true;
    }

	/**
	 * @param $postId
	 *
	 * @return string
	 */
	public static function getPageUrl($postId)
    {
        if (is_404() || is_search()) {
            return false;
        }

        // It's a singular page: post, page, custom post type (e.g. 'product' from WooCommerce)
        // Works for both front-end and Dashboard view
        if ($postId > 0) {
            return self::_filterPageUrl(get_permalink($postId));
        }


        if (is_admin()) {
            // If we're in the Dashboard area, and the page is not a taxonomy one
            // Then, it's either a homepage or a post one (post, page, custom post type)
            // If $postId equals 0, then it's a homepage
            if ($postId === 0) {
                if (get_site_url() !== get_home_url()) {
                    $pageUrl = get_home_url();
                } else {
                    $pageUrl = get_site_url();
                }

                return self::_filterPageUrl($pageUrl);
            }
        }

        // Front-end view
	    // It could be: Archive page (e.g. author, category, tag, date, custom taxonomy), Search page, 404 page etc.
        if ( ! is_admin() ) {
            global $wp;

            $permalinkStructure = get_option('permalink_structure');

            if ($permalinkStructure) {
                $pageUrl = home_url($wp->request);
            } else {
                $pageUrl = home_url($_SERVER['REQUEST_URI']);
            }

            if (strpos($_SERVER['REQUEST_URI'], '?') !== false) {
                list($cleanRequestUri) = explode('?', $_SERVER['REQUEST_URI']);
            } else {
                $cleanRequestUri = $_SERVER['REQUEST_URI'];
            }

            if (substr($cleanRequestUri, -1) === '/') {
                $pageUrl .= '/';
            }

            return self::_filterPageUrl($pageUrl);
        }

        return '';
    }

    /**
     * @param $postUrl
     * @return mixed
     */
    private static function _filterPageUrl($postUrl)
    {
        // If we are in the Dashboard on an HTTPS connection,
        // then we will make the AJAX call over HTTPS as well for the front-end
        // to avoid blocking
        if (self::isHttpsSecure() && strncmp($postUrl, 'http://', 7) === 0) {
            $postUrl = str_ireplace('http://', 'https://', $postUrl);
        }

        return $postUrl;
    }

	/**
	 * @param $postId
	 *
	 * @return string
	 */
	public static function getPageUri($postId)
    {
	    $parseUrl = parse_url(get_site_url());
	    $rootUrl = $parseUrl['scheme'].'://'.$parseUrl['host'];

	    $dbPageUrl = get_permalink($postId);

	    return str_replace( $rootUrl, '', $dbPageUrl );
    }

	/**
	 * @return void
     *
     * @noinspection PhpUndefinedFunctionInspection
     * @noinspection BadExceptionsProcessingInspection
     */
	public static function w3TotalCacheFlushObjectCache()
	{
		// Flush "W3 Total Cache" before printing the list as sometimes the old list shows after the CSS/JS manager is reloaded
		if (function_exists('w3tc_objectcache_flush') && wpacuIsPluginActive('w3-total-cache/w3-total-cache.php')) {
			try {
				w3tc_objectcache_flush();
			} catch(\Exception $e) {}
		}
	}

	/**
	 * @return bool
     *
     * @noinspection BadExceptionsProcessingInspection
     */
	public static function isElementorMaintenanceModeOn()
    {
	    // Elementor's maintenance or coming soon mode
	    if (class_exists('\Elementor\Maintenance_Mode') && wpacuIsPluginActive('elementor/elementor.php')) {
		    try {
			    $elementorMaintenanceMode = \Elementor\Maintenance_Mode::get( 'mode' ); // if any
			    if ( $elementorMaintenanceMode && in_array($elementorMaintenanceMode, array('maintenance', 'coming_soon')) ) {
					return true;
				    }
		    } catch (\Exception $err) {}
	    }

	    return false;
    }

	/**
	 * @return bool
     *
     * @noinspection BadExceptionsProcessingInspection
     */
	public static function isElementorMaintenanceModeOnForCurrentAdmin()
    {
    	if ( defined('WPACU_IS_ELEMENTOR_MAINTENANCE_MODE_TEMPLATE_ID') ) {
    		return true;
	    }

	    if (class_exists('\Elementor\Maintenance_Mode') && wpacuIsPluginActive('elementor/elementor.php')) {
		    try {
			    // Elementor Template ID (Chosen for maintenance or coming soon mode)
			    $elementorMaintenanceModeTemplateId = \Elementor\Maintenance_Mode::get( 'template_id' );

			    if ( isset( $GLOBALS['post']->ID ) && (int)$elementorMaintenanceModeTemplateId === (int)$GLOBALS['post']->ID ) {
				    define( 'WPACU_IS_ELEMENTOR_MAINTENANCE_MODE_TEMPLATE_ID', $elementorMaintenanceModeTemplateId );
				    return true;
			    }
		    } catch (\Exception $err) {}
	    }

	    return false;
    }

    /**
     * This function does not just detect the fact that the call is a local one,
     * But it will return true ONLY if the actual asset file (CSS/JS) actually exists
     *
	 * @param $src
	 *
	 * @return array
	 */
	public static function getLocalSrcIfExist($src)
    {
    	if (! $src) {
    	    return array();
	    }

    	// Clean it up first
	    if (strpos($src, '.css?') !== false) {
	    	list($src) = explode('.css?', $src);
		    $src .= '.css';
	    }

	    if (strpos($src, '.js?') !== false) {
		    list($src) = explode('.js?', $src);
		    $src .= '.js';
	    }

	    $paths = array('wp-includes/', 'wp-content/');

	    foreach ($paths as $path) {
	    	if (strpos($src, $path) !== false) {
	    		list ($baseUrl, $relSrc) = explode($path, $src);

                $fileFullRelPath = $path . $relSrc;

	    		$localPathToFile = self::getWpRootDirPathBasedOnPath($fileFullRelPath) . $fileFullRelPath;

	    		if (is_file($localPathToFile)) {
	    			return array('base_url' => $baseUrl, 'rel_src' => $fileFullRelPath, 'file_exists' => 1);
			    }
		    }
	    }

	    return array();
    }

    /**
     * This one checks if the "src" is local
     * Without the need for the CSS/JS file to exist
     * e.g. /index.php?value will return true if it's within the same domain
     * e.g. www.cdn-domain.com/wp-content/custom-file-name.css will return true
     * if the local file /wp-content/custom-file-name.css exists as some people use CDN
     *
     *
     * @param $src
     *
     * @return bool
     */
    public static function isLocalSrc($src)
    {
        if (self::getLocalSrcIfExist($src)) {
            return true;
        }

        if (strncmp(str_replace(site_url(), '', $src), '?', 1) === 0) {
            // Starts with ? right after the site url (it's a local URL)
            return true;
        }

        if (strpos($src, '?') !== false && strpos($src, site_url()) !== false) {
            // Dynamic Local URL (e.g. it could be something like /index.php?load_css=value
            return true;
        }

        return false;
    }

	/**
	 * @param bool $clean
	 *
	 * @return mixed|string
	 */
	public static function getCurrentPageUrl($clean = true)
    {
	    $currentPageUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . parse_url(site_url(), PHP_URL_HOST) . $_SERVER['REQUEST_URI'];

	    if ($clean && strpos($currentPageUrl, '?') !== false) {
		    list($currentPageUrl) = explode('?', $currentPageUrl);
	    }

	    return $currentPageUrl;
    }

	/**
	 * @param $src
	 * @param $assetKey
	 *
	 * @return string|string[]
	 */
	public static function assetFromHrefToRelativeUri($src, $assetKey)
    {
	    // Make the "src" relative in case the information will be imported from Staging to Live, it won't show the handle's link referencing to the staging URL in the "Overview" page and other similar pages, as it's confusing
	    $localAssetPath = OptimizeCommon::getLocalAssetPath($src, (($assetKey === 'styles') ? 'css' : 'js'));

	    $relSrc = $src;

	    if ($localAssetPath) {
		    $relSrc = str_replace(self::getWpRootDirPathBasedOnPath($relSrc), '', $relSrc);
	    }

	    $relSrc = str_replace(site_url(), '', $relSrc);

	    // Does it start with '//'? (protocol is missing) - the replacement above wasn't made
	    if (strncmp($relSrc, '//', 2) === 0) {
		    $siteUrlNoProtocol = str_replace(array('http:', 'https:'), '', site_url());
		    $relSrc = str_replace($siteUrlNoProtocol, '', $relSrc);
	    }

	    return $relSrc;
    }

    /**
     * This is a basic RegEx check that is used in case there's a match
     * This is to avoid further resources to detect the 'src' (e.g. via DOMDocument)
     *
     * @param $tagOutput
     *
     * @return bool
     */
    public static function isScriptTagWithSrcRegExCheck($tagOutput)
    {
        // This is good for tags such as <script > alert('<script src="">');</script>
        if (preg_match_all('#<script(\s+|)>#Umi', $tagOutput, $matches) && isset($matches[0][0]) && strpos($tagOutput, $matches[0][0]) === 0) {
            return false; // no "src"
        }

        if ( preg_match(
            '#<script(\s+)type=(\'|"|)text/javascript(\'|"|)(\s+|)>'.'|'.
            '<script(\s+)id=(\'|"|)(.*?)(\s+)type=(\'|"|)text/javascript(\'|"|)(\s+|)>'.'|'.
            '<script(\s+)type=(\'|"|)text/javascript(\'|"|)(\s+)id=(\'|"|)(.*?)(\'|"|)(\s+|)>#Usmi',
            $tagOutput
        ) ) {
            return false; // no "src"
        }

        if (preg_match('#src(\s+|)=(\s+|)("|\'|)(.*)("|\'|)|src(\s+|)=(\s+|)(.*)(\s+)#Usmi', $tagOutput)) {
            return true;
        }

        return false;
    }

	/**
	 * @param $tagOutput ('script', 'link')
	 * @param $attribute
	 *
	 * @return false|string
	 */
	public static function getValueFromTag($tagOutput, $attribute = '', $method = 'regex')
	{
		$tagOutput = trim($tagOutput);

		if (strncmp($tagOutput, '<script', 7) === 0 ) {
			$tagNameToCheck = 'script';

			if ($attribute === '') {
				$attribute = 'src';
			}

            // First, do some basic check (maybe there's no attribute there, and there's no need to continue)
            if ($attribute === 'src' && ! self::isScriptTagWithSrcRegExCheck($tagOutput)) {
                return false;
            }
		} elseif (strncmp($tagOutput, '<link', 5) === 0 ) {
			$tagNameToCheck = 'link';

			if ($attribute === '') {
				$attribute = 'href';
			}
		} elseif (strncmp($tagOutput, '<style', 6) === 0 ) {
			$tagNameToCheck = 'style';

			if ($attribute === '') {
				$attribute = 'type';
			}
		} else {
			return false; // the tag it neither 'script' nor 'link'
		}

		if ($method === 'dom_with_fallback') {
			if (self::isDOMDocumentOn()) {
				$domForTag = self::initDOMDocument();

				$domForTag->loadHTML( $tagOutput );

				$scriptTagObj = $domForTag->getElementsByTagName( $tagNameToCheck )->item( 0 );

				if ( $scriptTagObj === null ) {
					return false;
				}

				if ( $scriptTagObj->hasAttributes() ) {
					foreach ( $scriptTagObj->attributes as $attrObj ) {
                        if ( $attrObj->nodeName === $attribute ) {
							return trim( $attrObj->nodeValue );
						}
					}

                    return ''; // There's no $attribute
				}
			}

			return self::getValueFromTagViaRegEx($tagOutput, $attribute);
		}

		if ($method === 'regex') {
			return self::getValueFromTagViaRegEx($tagOutput, $attribute);
		}

		return false;
	}

	/**
	 * @param $tagOutput
	 * @param $attribute
	 *
	 * @return false|string
	 */
	public static function getValueFromTagViaRegEx($tagOutput, $attribute = '')
	{
		$tagOutput = trim( $tagOutput );

		if (strncmp($tagOutput, '<script', 7) === 0 ) {
			if ( $attribute === '' ) {
				$attribute = 'src';
			}

			// Perhaps the strung "src" is inside an inline JS tag which would make the source value irrelevant
			// We only need the "src" attribute from a SCRIPT tag that loads a .js file (without any inline JS code)
			$tagOutputNoTags = trim(strip_tags($tagOutput));

			if ($tagOutputNoTags !== '' && stripos($tagOutputNoTags, 'src') !== false) {
				// This is an inline tag such as the following:
				// <script>j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;</script>
				return false;
			}

			preg_match_all( '#<script.*?'.$attribute.'\s*=\s*(.*?)#Usmi', $tagOutput, $outputMatches );
		}

		if (strncmp($tagOutput, '<link', 5) === 0 ) {
			if ( $attribute === '' ) {
				$attribute = 'href';
			}

			preg_match_all( '#<link.*?'.$attribute.'\s*=\s*(.*?)#Usmi', $tagOutput, $outputMatches );
		}

		if (strncmp($tagOutput, '<style', 6) === 0 ) {
			if ( $attribute === '' ) {
				$attribute = 'type';
			}

			preg_match_all( '#<style.*?'.$attribute.'\s*=\s*(.*?)#Usmi', $tagOutput, $outputMatches );
		}

		if ( isset($outputMatches[1][0]) && $outputMatches[1][0] ) {
			$scriptPart = trim($outputMatches[1][0]);

			foreach ( array('"', "'") as $quoteType ) {
				if ( $scriptPart[0] === $quoteType ) {
					$scriptPartTwo = ltrim( $scriptPart, $quoteType );

					$posEndingQuote = strpos( $scriptPartTwo, $quoteType );

					if ( $posEndingQuote === false ) {
						return false;
					}

					return trim ( substr( $scriptPartTwo, 0, $posEndingQuote ) );
				}
			}

			if ( ! in_array($scriptPart[0], array('"', "'") ) ) { // no quotes, just space or no wrapper
				$scriptPartTwo = ltrim( $scriptPart );

				$posFirstSpace = strpos( $scriptPartTwo, ' ' );

				if ($posFirstSpace === false ) {
					return false;
				}

				return trim( substr( $scriptPartTwo, 0, $posFirstSpace ) );
			}
		}

		return false;
	}

	/**
	 * @param $postType
	 *
	 * @return false[]
	 */
	public static function isValidPostType($postType)
	{
		global $wpdb;

		$status = array('has_records' => false); // default

		$hasRecords = $wpdb->get_var('SELECT COUNT(*) FROM `'.$wpdb->posts.'` WHERE post_type=\''.$postType.'\'');

		if ($hasRecords) {
			$status['has_records'] = $hasRecords;
		}

		return $status;
	}

    /**
     * @return mixed
     */
    public static function getShowOnFront()
    {
        if (! self::$showOnFront) {
            self::$showOnFront = get_option('show_on_front');
        }

        return self::$showOnFront;
    }

	/**
	 * @return bool
	 */
	public static function isWpRocketMinifyHtmlEnabled()
    {
    	// Only relevant if WP Rocket's version is below 3.7
	    if (defined('WP_ROCKET_VERSION') && version_compare(WP_ROCKET_VERSION, '3.7') >= 0) {
	    	return false;
	    }

		if (wpacuIsPluginActive('wp-rocket/wp-rocket.php')) {
			if (function_exists('get_rocket_option')) {
				$wpRocketMinifyHtml = trim(get_rocket_option('minify_html')) ?: false;
			} else {
				$wpRocketSettings = get_option('wp_rocket_settings');
				$wpRocketMinifyHtml = (isset($wpRocketSettings['minify_html']) && $wpRocketSettings['minify_html']);
			}

			return $wpRocketMinifyHtml;
		}

		return false;
    }

	/**
	 * If it matches true, it's very likely there is no need for the Gutenberg CSS Block Library
	 * The user will be reminded about it
	 *
	 * @return bool
	 */
	public static function isClassicEditorUsed()
    {
    	if (wpacuIsPluginActive('classic-editor/classic-editor.php')) {
    		$ceReplaceOption = get_option('classic-editor-replace');
			$ceAllowUsersOption = get_option('classic-editor-allow-users');

    		if ($ceReplaceOption === 'classic' && $ceAllowUsersOption === 'disallow') {
    		    return true;
		    }
	    }

    	return false;
    }

	/**
	 *
	 * @return array|string|void
	 */
	public static function getWpCoreCssHandlesFromWpIncludesBlocks()
	{
		$transientName = 'wpacu_wp_core_css_handles_from_wp_includes_blocks';

		if ($transientValues = get_transient($transientName)) {
			return $transientValues;
		}

		$blocksDir = ABSPATH.'wp-includes/blocks/';

		$cssCoreHandlesList = array();

		if (is_dir($blocksDir)) {
			$list = scandir($blocksDir);

			if ( ! empty($list) && count($list) > 2 ) {
				foreach ($list as $fileOrDir) {
					$targetJsonFile = $blocksDir.$fileOrDir.'/block.json';

					if (is_dir($blocksDir.$fileOrDir) && is_file($targetJsonFile)) {
						$jsonToArray = function_exists('wp_json_file_decode') ? wp_json_file_decode($targetJsonFile, array('associative' => true))
						    : self::wpJsonFileDecode($targetJsonFile, array( 'associative' => true));

						if (isset($jsonToArray['style'])) {
							if ( is_array( $jsonToArray['style'] ) ) {
								foreach ( $jsonToArray['style'] as $style ) {
									$cssCoreHandlesList[] = $style;
								}
							} else {
								$cssCoreHandlesList[] = $jsonToArray['style'];
							}
						}

						if (isset($jsonToArray['editorStyle'])) {
							if ( is_array( $jsonToArray['editorStyle'] ) ) {
								foreach ( $jsonToArray['editorStyle'] as $editorStyle ) {
									$cssCoreHandlesList[] = $editorStyle;
								}
							} else {
								$cssCoreHandlesList[] = $jsonToArray['editorStyle'];
							}
						}
					}
				}

				foreach ($cssCoreHandlesList as $style) {
					if (self::endsWith($style, '-editor')) {
						$cssCoreHandlesList[] = substr($style, 0, -strlen('-editor'));
					}
				}

				$cssCoreHandlesList = array_unique($cssCoreHandlesList);
			}
		} else {
			// Different WordPress version, perhaps no longer using that directory
			set_transient($transientName, array(), 3600 * 24 * 7);

			return array();
		}

		if ( ! empty($cssCoreHandlesList) ) {
			set_transient($transientName, $cssCoreHandlesList, 3600 * 24 * 7);

			return $cssCoreHandlesList;
		}
	}

	/**
	 * Fallback in the case the WordPress version is below 5.9.0
	 *
	 * @param $filename
	 * @param $options
	 *
	 * @return mixed|null
	 */
	public static function wpJsonFileDecode( $filename, $options = array() )
	{
		$filename = wp_normalize_path( realpath( $filename ) );

		if ( ! is_file( $filename ) ) {
			trigger_error(
				sprintf(
					/* translators: %s: Path to the JSON file. */
					__( "File %s doesn't exist!" ),
					$filename
				)
			);
			return null;
		}

		$options      = wp_parse_args( $options, array( 'associative' => false ) );
		$decoded_file = json_decode( file_get_contents( $filename ), $options['associative'] );

		if ( JSON_ERROR_NONE !== wpacuJsonLastError() ) {
			trigger_error(
				sprintf(
				/* translators: 1: Path to the JSON file, 2: Error message. */
					__( 'Error when decoding a JSON file at path %1$s: %2$s' ),
					$filename,
					json_last_error_msg()
				)
			);
			return null;
		}

		return $decoded_file;
	}

	/**
	 * @return bool
	 */
	public static function isDOMDocumentOn()
	{
		return function_exists('libxml_use_internal_errors') && function_exists('libxml_clear_errors') && class_exists('\DOMDocument') && class_exists('\DOMXpath');
	}

	/**
	 * @return \DOMDocument
	 */
	public static function initDOMDocument()
	{
		$dom = new \DOMDocument();

		// Any document errors reported in the HTML source (lots of websites have them) are irrelevant for the functionality of the plugin
		libxml_use_internal_errors(true);

		return $dom;
	}

	/**
	 * @param $e
	 *
	 * @return string
	 */
	public static function getOuterHTML( $e )
	{
		$doc = self::initDOMDocument();

		$doc->appendChild( $doc->importNode( $e, true ) );

		return trim( $doc->saveHTML() );
	}

	/**
	 * @return array|string
	 */
	public static function getW3tcMasterConfig()
	{
		if (! ObjectCache::wpacu_cache_get('wpacu_w3tc_master_config')) {
			$w3tcConfigMasterFile = WP_CONTENT_DIR . '/w3tc-config/master.php';
			$w3tcMasterConfig = FileSystem::fileGetContents($w3tcConfigMasterFile);
			ObjectCache::wpacu_cache_set('wpacu_w3tc_master_config', trim($w3tcMasterConfig));
		} else {
			$w3tcMasterConfig = ObjectCache::wpacu_cache_get('wpacu_w3tc_master_config');
		}

		return $w3tcMasterConfig;
	}

	/**
	 * @param bool $forceReturn
	 *
	 * @return string
	 */
	public static function preloadAsyncCssFallbackOutput($forceReturn = false)
	{
		// Unless it has to be returned (e.g. for debugging purposes), check it if it was returned before
		// To avoid duplicated HTML code
		if (! $forceReturn) {
			if ( wpacuIsDefinedConstant( 'WPACU_PRELOAD_ASYNC_SCRIPT_SHOWN' ) ) {
				return '';
			}

			wpacuDefineConstant( 'WPACU_PRELOAD_ASYNC_SCRIPT_SHOWN', 1 ); // mark it as already printed
		}

		$scriptType = Misc::getScriptTypeAttribute();

		return <<<HTML
<script {$scriptType} id="wpacu-preload-async-css-fallback">
/*! LoadCSS. [c]2020 Filament Group, Inc. MIT License */
/* This file is meant as a standalone workflow for
- testing support for link[rel=preload]
- enabling async CSS loading in browsers that do not support rel=preload
- applying rel preload css once loaded, whether supported or not.
*/
(function(w){"use strict";var wpacuLoadCSS=function(href,before,media,attributes){var doc=w.document;var ss=doc.createElement('link');var ref;if(before){ref=before}else{var refs=(doc.body||doc.getElementsByTagName('head')[0]).childNodes;ref=refs[refs.length-1]}
var sheets=doc.styleSheets;if(attributes){for(var attributeName in attributes){if(attributes.hasOwnProperty(attributeName)){ss.setAttribute(attributeName,attributes[attributeName])}}}
ss.rel="stylesheet";ss.href=href;ss.media="only x";function ready(cb){if(doc.body){return cb()}
setTimeout(function(){ready(cb)})}
ready(function(){ref.parentNode.insertBefore(ss,(before?ref:ref.nextSibling))});var onwpaculoadcssdefined=function(cb){var resolvedHref=ss.href;var i=sheets.length;while(i--){if(sheets[i].href===resolvedHref){return cb()}}
setTimeout(function(){onwpaculoadcssdefined(cb)})};function wpacuLoadCB(){if(ss.addEventListener){ss.removeEventListener("load",wpacuLoadCB)}
ss.media=media||"all"}
if(ss.addEventListener){ss.addEventListener("load",wpacuLoadCB)}
ss.onwpaculoadcssdefined=onwpaculoadcssdefined;onwpaculoadcssdefined(wpacuLoadCB);return ss};if(typeof exports!=="undefined"){exports.wpacuLoadCSS=wpacuLoadCSS}else{w.wpacuLoadCSS=wpacuLoadCSS}}(typeof global!=="undefined"?global:this))
</script>
HTML;
	}

	/**
	 * @param $array
	 *
	 * @return string
	 */
	public static function arrayKeyFirst($array)
	{
		if (function_exists('array_key_first')) {
			return array_key_first($array);
		}

		$arrayKeys = array_keys($array);

		return $arrayKeys[0];
	}

	/**
	 * @param $requestMethod
	 * @param $key
	 * @param mixed $defaultValue
	 *
	 * @return mixed
	 */
	public static function getVar($requestMethod, $key, $defaultValue = '')
    {
	    if ($requestMethod === 'get' && $key && isset($_GET[$key])) {
		    return $_GET[$key];
	    }

		if ($requestMethod === 'post' && $key && isset($_POST[$key])) {
			return $_POST[$key];
		}

	    if ($requestMethod === 'request' && $key && isset($_REQUEST[$key])) {
		    return $_REQUEST[$key];
	    }

	    return $defaultValue;
    }

	/**
	 * @param $requestMethod
	 * @param $key
	 *
	 * @return bool
	 */
	public static function isValidRequest($requestMethod, $key)
    {
	    if ($requestMethod === 'post' && $key && ! empty($_POST[$key])) {
		    return true;
	    }

	    if ($requestMethod === 'get' && $key && ! empty($_GET[$key])) {
		    return true;
	    }

	    return false;
    }

	/**
	 * @param $pageId
	 */
	public static function doNotApplyOptimizationOnPage($pageId)
    {
    	// Do not trigger the code below if there is already a change in place
    	if (get_post_meta($pageId, '_' . WPACU_PLUGIN_ID . '_page_options', true)) {
    	    return;
	    }

	    $pageOptionsJson = wp_json_encode(array(
		    'no_css_minify'   => 1,
		    'no_css_optimize' => 1,
		    'no_js_minify'    => 1,
		    'no_js_optimize'  => 1
	    ));

	    if (! add_post_meta($pageId, '_' . WPACU_PLUGIN_ID . '_page_options', $pageOptionsJson, true)) {
		    update_post_meta($pageId, '_' . WPACU_PLUGIN_ID . '_page_options', $pageOptionsJson);
	    }
    }

	/**
	 * @param $optionName
	 * @param $optionValue
	 * @param string $autoload
     *
     * @return bool|void
	 */
	public static function addUpdateOption($optionName, $optionValue, $autoload = 'no')
    {
		$optionValue = is_string($optionValue) ? trim($optionValue) : $optionValue;

	    // Empty array encoded into JSON; No point in keeping the option in the database if it's already there
	    if ($optionValue === '[]') {
		    delete_option($optionName);
		    return;
	    }

    	// Nothing in the database? Since option does not exist, add it
    	if (get_option($optionName) === false) {
		    add_option($optionName, $optionValue, '', $autoload);
		    return;
	    }

		// get_option($optionName) didn't return false, thus the option is either an empty string or it has a value
	    // either way, it exists in the database, and the update will be triggered

    	// Value is in the database already | Update it
    	return update_option($optionName, $optionValue, $autoload);
    }

	/**
	 * @param string $get
	 *
	 * @return false|string
	 */
	public static function getPluginsDir($get = 'rel_path')
	{
		$return = '';
		$relPath = trim( str_replace( self::getWpRootDirPath(), '', WP_PLUGIN_DIR ), '/' );

		if ($get === 'rel_path') {
			$return = $relPath;
		} elseif ($get === 'dir_name') {
			$return = substr(strrchr($relPath, '/'), 1);
		}

		return $return;
	}

	/**
	 * @return string
	 */
	public static function getThemesDirRel()
	{
		$relPathCurrentTheme = str_replace( site_url(), '', get_template_directory_uri() );

		$posLastForwardSlash = strrpos($relPathCurrentTheme,'/');

		return substr($relPathCurrentTheme, 0, $posLastForwardSlash) . '/';
	}

    /**
     * @return array
     */
    public static function getUrlsToThemeDirs()
    {
        $urlsToThemeDirs = array();

        foreach (search_theme_directories() as $themeDir => $themeDirArray) {
            $themeUrl = '/'. str_replace(
                '//',
                '/',
                str_replace(self::getWpRootDirPath(), '', $themeDirArray['theme_root']) . '/'. $themeDir . '/'
            );

            $urlsToThemeDirs[] = $themeUrl;
        }

        return array_unique($urlsToThemeDirs);
    }

	/**
	 * Needed when the plugins' directory is different from the default one: /wp-content/plugins/
	 *
	 * @param $values
	 *
	 * @return array
	 */
	public static function replaceRelPluginPath($values)
	{
		$relPluginPath = self::getPluginsDir();

		if ($relPluginPath !== 'wp-content/plugins') {
			return array_filter( $values, function( $value ) use ( $relPluginPath ) {
				return str_replace( '/wp-content/plugins/', '/' . $relPluginPath . '/', $value );
			} );
		}

		return $values;
	}

    /**
     * @param $source
     *
     * @return mixed|string
     */
    public static function getHrefFromSource($source)
    {
        $siteUrl = get_site_url();

        // Determine source href, starting with '/' but not starting with '//'
        if (strncmp($source, '/', 1) === 0 && strncmp($source, '//', 2) !== 0) {
            $sourceHref = $siteUrl . $source;
        } else {
            $sourceHref = $source;
        }

        if (strpos($sourceHref, '/wp-content/plugins/') !== false && self::isLocalSrc($sourceHref)) {
            $sourceHref = str_replace('/wp-content/plugins/', '/' . self::getPluginsDir() . '/', $sourceHref);
        }

        return $sourceHref;
    }

	/**
	 * @return array
	 */
	public static function getActivePlugins($type = 'all')
	{
		$wpacuActivePlugins = array();

		if (in_array($type, array('site', 'all'))) {
			$wpacuActivePlugins = (array) get_option( 'active_plugins', array() );
		}

		// In case we're dealing with a MultiSite setup
		if (in_array($type, array('network', 'all')) && is_multisite()) {
			$wpacuActiveSiteWidePlugins = (array)get_site_option('active_sitewide_plugins', array());

			if ( ! empty($wpacuActiveSiteWidePlugins) ) {
				foreach (array_keys($wpacuActiveSiteWidePlugins) as $activeSiteWidePlugin) {
					$wpacuActivePlugins[] = $activeSiteWidePlugin;
				}
			}
		}

		return array_unique($wpacuActivePlugins);
	}

	/**
	 * @return string
	 */
	public static function getStyleTypeAttribute()
	{
		$typeAttr = '';

		if ( function_exists( 'is_admin' ) &&
             function_exists( 'current_theme_supports' ) &&
             ! is_admin() &&
             ! current_theme_supports( 'html5', 'style' )
		) {
			$typeAttr = " type='text/css'";
		}

		return wp_kses($typeAttr, array('type' => array()));
	}

	/**
	 * @return string
	 */
	public static function getScriptTypeAttribute()
    {
	    $typeAttr = '';

	    if ( function_exists( 'is_admin' ) &&
             function_exists( 'current_theme_supports' ) &&
             ! is_admin() &&
             ! current_theme_supports( 'html5', 'script' ) ) {
		    $typeAttr = " type='text/javascript'";
	    }

	    return $typeAttr;
    }

	/**
	 * @return bool
	 */
	public static function doingCron()
	{
		if (function_exists('wp_doing_cron') && wp_doing_cron()) {
			return true;
		}

		if (defined( 'DOING_CRON') && (true === DOING_CRON)) {
			return true;
		}

		// Default to false
		return false;
	}

	/**
	 * @return string
	 */
	public static function getWpRootDirPath()
	{
		if (isset($GLOBALS['wpacu_wp_root_dir_path']) && $GLOBALS['wpacu_wp_root_dir_path']) {
			return $GLOBALS['wpacu_wp_root_dir_path'];
		}

		$possibleWpConfigFile = dirname(WP_CONTENT_DIR).'/wp-config.php';
		$possibleIndexFile = dirname(WP_CONTENT_DIR).'/index.php';

		// This is good for hosting accounts under FlyWheel which have a different way of loading WordPress,
		// and we can't rely on ABSPATH; On most hosting accounts, the condition below would be a match and would work well
		if (is_file($possibleWpConfigFile) && is_file($possibleIndexFile)) {
			$GLOBALS['wpacu_wp_root_dir_path'] = dirname(WP_CONTENT_DIR).'/';
			return $GLOBALS['wpacu_wp_root_dir_path'];
		}

		// Default to the old ABSPATH
		$GLOBALS['wpacu_wp_root_dir_path'] = ABSPATH.'/';
		return $GLOBALS['wpacu_wp_root_dir_path'];
	}

    /**
     * @param $path
     *
     * @return string
     */
    public static function getWpRootDirPathBasedOnPath($path)
    {
        $lastDirWpContent = ltrim(strrchr(WP_CONTENT_DIR, '/'), '/');

        if (strpos($path, $lastDirWpContent) !== false && dirname(WP_CONTENT_DIR) !== ABSPATH) {
            return dirname(WP_CONTENT_DIR) . '/';
        }

        return self::getWpRootDirPath(); // default (most common)
    }

	/**
	 * @param array $targetDirs
	 * @param string $filterExt
	 *
	 * @return array
	 */
	public static function getSizeOfDirectoryRootFiles($targetDirs = array(), $filterExt = '')
	{
		if ( empty($targetDirs) ) {
			return array(); // no relevant target dirs set as a parameter
		}

		$totalSize = 0;

		foreach ( $targetDirs as $targetDir ) {
			if ( ! is_dir($targetDir) ) {
				continue; // skip it as the directory does not exist
			}

			$listOfFiles = scandir( $targetDir );

			if ( ! empty( $listOfFiles ) ) {
				foreach ( $listOfFiles as $fileName ) {
					// Only relevant root files matter
					if ( $fileName === '.' || $fileName === '..' || $fileName === 'index.php' || is_dir( $fileName ) ) {
						continue;
					}

					// If .js is specified, then do not consider any other extension
					if ( $filterExt !== '' && ! strrchr( $fileName, $filterExt ) ) {
						continue;
					}

					$totalSize += filesize( $targetDir . $fileName );
				}
			}
		}

		if ($totalSize > 0) {
			$totalSizeMb = MiscAdmin::formatBytes( $totalSize, 2, 'MB' );

			return array(
				'total_size'    => $totalSize,
				'total_size_mb' => $totalSizeMb
			);
		}

		return array(); // no relevant files
	}

	/**
	 * @param $targetDir
	 */
	public static function rmDir($targetDir)
	{
		if (! is_dir($targetDir)) {
			return;
		}

		$scanDirResult = @scandir($targetDir);

		if (! is_array($scanDirResult)) {
			return;
		}

		$totalFiles = count($scanDirResult) - 2; // exclude . and ..

		if ($totalFiles < 1) { // could be 0 or negative
			@rmdir($targetDir); // @ was appended just in case
		}
	}

	/**
	 * @param $targetVersion
	 *
	 * @return bool
	 */
	public static function isWpVersionAtLeast($targetVersion)
	{
		global $wp_version;
		return version_compare($wp_version, $targetVersion) >= 0;
	}

	/**
	 * @param $list
	 * @param string $for
	 *
	 * @return array
	 */
	public static function filterList($list, $for = 'empty_values')
	{
		if (! empty($list) && $for === 'empty_values') {
			$list = self::arrayUnsetRecursive($list);
		}

		return $list;
	}

	/**
	 * Source: https://stackoverflow.com/questions/7696548/php-how-to-remove-empty-entries-of-an-array-recursively
	 *
	 * @param $array
	 *
	 * @return array
	 */
	public static function arrayUnsetRecursive($array)
	{
		$array = (array)$array; // in case it's object, convert it to array

		foreach ($array as $key => $value) {
			if (is_array($value) || is_object($value)) {
				$array[$key] = self::arrayUnsetRecursive($value);
			}

			// Values such as '0' are not considered empty values
			if (is_string($value) && trim($value) === '0') {
				continue;
			}

			// Clear it if it's empty
			if (empty($array[$key])) {
				unset($array[$key]);
			}
		}

		return $array;
	}

    /**
	 * @param $name
	 * @param $action
	 *
	 * @return mixed|string
	 */
	public static function scriptExecTimer($name, $action = 'start')
	{
		if (! isset($_GET['wpacu_debug'])) {
			return ''; // only trigger it in debugging mode
		}

		$wpacuStartTimeName = 'wpacu_' . $name . '_start_time';
		$wpacuExecTimeName  = 'wpacu_' . $name . '_exec_time';

		if ($action === 'start') {
			$startTime = microtime(true) * 1000;

			ObjectCache::wpacu_cache_set($wpacuStartTimeName, $startTime, 'wpacu_exec_time');
		}

		if ($action === 'end' && ($startTime = ObjectCache::wpacu_cache_get($wpacuStartTimeName, 'wpacu_exec_time'))) {
			// End clock time
			$endTime = microtime(true) * 1000;
			$scriptExecTime = ( $endTime > $startTime ) ? ( $endTime - $startTime ) : 0;

			// Calculate script execution time
			// Is there an existing exec time (e.g. from a function called several times)?
			// Append it to the total execution time
			$scriptExecTimeExisting  = ObjectCache::wpacu_cache_get( $wpacuExecTimeName, 'wpacu_exec_time' ) ?: 0;
			$scriptExecTimeExisting += $scriptExecTime;
			ObjectCache::wpacu_cache_set($wpacuExecTimeName, $scriptExecTimeExisting, 'wpacu_exec_time');

			return $scriptExecTime;
		}

		return '';
	}
}
