<?php
namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\MainFront;
use WpAssetCleanUp\Misc;

/**
 *
 */
class CriticalCss
{
	/**
	 *
	 */
	const CRITICAL_CSS_MARKER = '<meta data-name=wpacu-delimiter data-content="ASSET CLEANUP CRITICAL CSS" />';

	/**
	 * CriticalCss constructor.
	 */
	public function __construct()
	{
        // Show any critical CSS signature in the front-end view?
        add_action('wp_head', static function() {
            if ( OptimizeCommon::preventAnyFrontendOptimization() || ( Main::instance()->settings['critical_css_status'] === 'off' ) || ! has_filter('wpacu_critical_css') ) {
                return;
            }

            echo self::CRITICAL_CSS_MARKER; // Add the marker that will be later replaced with the critical CSS
        }, -PHP_INT_MAX);

        // 1) Alter the HTML source to prepare it for the critical CSS
        add_filter('wpacu_alter_source_for_critical_css', array($this, 'alterHtmlSourceForCriticalCss'));

        // 2) Print the critical CSS
        // Only continue if critical CSS is globally deactivated
        if (Main::instance()->settings['critical_css_status'] !== 'off') {
            add_filter('wpacu_critical_css', array($this, 'showAnyCriticalCss'));
        }
	}

	/**
	 * @param $htmlSource
	 *
	 * @return mixed
	 */
	public static function alterHtmlSourceForCriticalCss($htmlSource)
	{
		// The marker needs to be there
		if (strpos($htmlSource, self::CRITICAL_CSS_MARKER) === false) {
			return $htmlSource;
		}

		// For debugging purposes, do not print any critical CSS, nor preload any of the LINk tags (with rel="stylesheet")
        // Since, there aren't any LINK tags to alter (for preloading), the method will stop here by returning the clean HTML source
		if ( isset($_GET['wpacu_no_critical_css_and_preload']) ) {
			return str_replace(self::CRITICAL_CSS_MARKER, '', $htmlSource);
		}

		$criticalCssData = apply_filters('wpacu_critical_css', array('content' => false, 'minify' => false));

		// If it's through the Dashboard it always has a location key (e.g. posts, pages, categories)
		// Otherwise, the "wpacu_critical_hook" was used via custom coding (e.g. in functions.php)
		if (! isset($criticalCssData['location_key'])) {
			$criticalCssData['location_key'] = 'custom_via_hook';
		}

		if ( ! (isset($criticalCssData['content']) && $criticalCssData['content']) ) {
			// No critical CSS set? Return the HTML source as it is with the critical CSS location marker stripped
			return str_replace(self::CRITICAL_CSS_MARKER, '', $htmlSource);
		}

		$keepRenderBlockingList = ( isset( $criticalCssData['keep_render_blocking'] ) && $criticalCssData['keep_render_blocking'] ) ? $criticalCssData['keep_render_blocking'] : array();

		// If just a string was added (one in the list), convert it as an array with one item
		if (! is_array($keepRenderBlockingList)) {
			$keepRenderBlockingList = array($keepRenderBlockingList);
		}

		$doCssMinify        = isset( $criticalCssData['minify'] ) && $criticalCssData['minify']; // leave no room for any user errors in case the 'minify' parameter is unset by mistake
		$criticalCssContent = OptimizeCss::maybeAlterContentForCssFile( $criticalCssData['content'], $doCssMinify, array( 'alter_font_face' ) );

		$criticalCssStyleTag = '<style '.Misc::getStyleTypeAttribute().' id="wpacu-critical-css" data-wpacu-critical-css-type="'.$criticalCssData['location_key'].'">'.$criticalCssContent.'</style>';

		/*
		 * By default the page will have the critical CSS applied as well as non-render blocking LINK tags (non-critical)
		 * For development purposes only, you can append:
		 * 1) /?wpacu_only_critical_css to ONLY load the critical CSS
		 * 2) /?wpacu_no_critical_css to ONLY load the non-render blocking LINK tags (non-critical)
		 * For a cleaner load, &wpacu_no_admin_bar can be added to avoid loading the top admin bar
		*/
		if ( isset($_GET['wpacu_only_critical_css']) )  {
			// For debugging purposes: preview how the page would load only with the critical CSS loaded (all LINK/STYLE CSS tags are stripped)
            // Do not remove the admin bar's (and other marked ones) CSS as it would make sense to keep it as it is if the admin is logged-in
			$htmlSource = preg_replace('#<link(.*?)data-wpacu-skip-preload#Umi', "<wpacu_link$1data-wpacu-skip-preload", $htmlSource);

			$htmlSource = preg_replace('#<link[^>]*(stylesheet|(as(\s+|)=(\s+|)(|"|\')style(|"|\')))[^>]*(>)#Umi', '', $htmlSource);
			$htmlSource = preg_replace('@(<style[^>]*?>).*?</style>@si', '', $htmlSource);
			$htmlSource = str_replace(Misc::preloadAsyncCssFallbackOutput(true), '', $htmlSource);

			// Restore any LINKs to admin-bar and others (if any)
			$htmlSource = preg_replace('#<wpacu_link(.*?)data-wpacu-skip-preload#Umi', "<link$1data-wpacu-skip-preload", $htmlSource);
		} else {
			// Convert render-blocking LINK CSS tags into non-render blocking ones
			$cleanerHtmlSource = preg_replace( '/<!--(.|\s)*?-->/', '', $htmlSource );
			$cleanerHtmlSource = preg_replace( '@<(noscript)[^>]*?>.*?</\\1>@si', '', $cleanerHtmlSource );

			preg_match_all( '#<link[^>]*(stylesheet|(as(\s+|)=(\s+|)(|"|\')style(|"|\')))[^>]*(>)#Umi', $cleanerHtmlSource, $matchesSourcesFromTags, PREG_SET_ORDER );

            if ( empty( $matchesSourcesFromTags ) ) {
				return $htmlSource;
			}

			foreach ( $matchesSourcesFromTags as $results ) {
				$matchedTag = $results[0];

				if (! empty($keepRenderBlockingList) && preg_match('#('.implode('|', $keepRenderBlockingList).')#Usmi', $matchedTag)) {
					continue;
				}

				// Marked for no alteration or for loading based on the media query match? Then, it's already non-render blocking, and it has to be skipped!
				if (strpos($matchedTag, 'data-wpacu-apply-media-query=') !== false || Misc::hasExactDataAttr($matchedTag, 'data-wpacu-skip')) {
					continue;
				}

				if ( strpos ($matchedTag, 'data-wpacu-skip-preload=\'1\'') !== false  ) {
					continue; // skip async preloaded (for debugging purposes or when it is not relevant)
				}

				if ( preg_match( '#rel(\s+|)=(\s+|)([\'"])preload([\'"])#i', $matchedTag ) && strpos( $matchedTag, 'data-wpacu-preload-css-basic=\'1\'' ) !== false ) {
                    $htmlSource = str_replace( $matchedTag, '', $htmlSource );
				} elseif ( preg_match( '#rel(\s+|)=(\s+|)([\'"])stylesheet([\'"])#i', $matchedTag ) ) {
                    // Already applied "async"
                    if (strpos($matchedTag, 'data-wpacu-preload-it-async') !== false) {
                        continue;
                    }

					$matchedTagAlteredForPreload = str_ireplace(
						array(
							'<link ',
							'rel=\'stylesheet\'',
							'rel="stylesheet"',
							'id=\'',
							'id="',
							'data-wpacu-to-be-preloaded-basic=\'1\''
						),
						array(
							'<link rel=\'preload\' as=\'style\' data-wpacu-preload-it-async=\'1\' ',
							'onload="this.onload=null;this.rel=\'stylesheet\'"',
							'onload="this.onload=null;this.rel=\'stylesheet\'"',
							'id=\'wpacu-preload-',
							'id="wpacu-preload-',
							''
						),
						$matchedTag
					);

					$htmlSource = str_replace( $matchedTag, $matchedTagAlteredForPreload, $htmlSource );
				}
			}
		}

		// For debugging purposes: preview how the page would load without critical CSS & all the non-render blocking CSS files loaded
		// It should show a flash of unstyled content: https://en.wikipedia.org/wiki/Flash_of_unstyled_content
		if ( isset($_GET['wpacu_no_critical_css']) ) {
            $replaceWith = '';
		} else {
            $replaceWith = $criticalCssStyleTag . Misc::preloadAsyncCssFallbackOutput();
        }

		return str_replace(self::CRITICAL_CSS_MARKER, $replaceWith, $htmlSource);
	}

	/**
	 * @param $args
	 *
	 * @return mixed
	 */
	public function showAnyCriticalCss($args)
	{
		/*
		 * An enabled object-level rule is independent and has priority.
		 * If it is absent, disabled or empty, continue with the original
		 * general Critical CSS flow below, unchanged.
		 */
		$granularCriticalCssData = self::getGranularCriticalCssDataForCurrentPage();

		if ( ! empty($granularCriticalCssData) ) {
			$storedData = $granularCriticalCssData['data'];

			if (isset($storedData['enable']) && $storedData['enable']) {
				$showMethod = isset($storedData['show_method']) ? $storedData['show_method'] : 'original';

				if ($showMethod === 'minified' && ! empty($storedData['content_minified'])) {
					$args['content'] = $storedData['content_minified'];
				} elseif ( ! empty($storedData['content_original']) ) {
					$args['content'] = $storedData['content_original'];
				}

				if (isset($args['content']) && is_string($args['content']) && trim($args['content']) !== '') {
					$args['location_key'] = $granularCriticalCssData['location_key'];
					return $args;
				}
			}
		}

		$criticalCssLocationKey = false; // default value until any location is detected (e.g. homepage)
		$customPostTypeArchiveLocationKey = false;
		$isPostTypeArchive = is_post_type_archive();

		/*
		 * A custom post type archive (e.g. /books/) is separate from both
		 * its singular entries (e.g. /book/title-here/) and taxonomies.
		 * Detect it before MainFront::isSingularPage(), as the WooCommerce
		 * shop archive is deliberately treated as singular-like elsewhere.
		 */
		if ($isPostTypeArchive) {
			$queriedObject = function_exists('get_queried_object') ? get_queried_object() : false;
			$archivePostType = ($queriedObject instanceof \WP_Post_Type && ! empty($queriedObject->name))
				? $queriedObject->name
				: get_query_var('post_type');

			if (is_array($archivePostType)) {
				$archivePostType = reset($archivePostType);
			}

			$archivePostType = is_string($archivePostType) ? sanitize_key($archivePostType) : '';
			$postTypeObject  = $archivePostType !== '' ? get_post_type_object($archivePostType) : false;

			if ($postTypeObject
			    && empty($postTypeObject->_builtin)
			    && ! empty($postTypeObject->public)
			    && ! empty($postTypeObject->publicly_queryable)
			    && ! empty($postTypeObject->has_archive)
			    && get_post_type_archive_link($archivePostType)) {
				$customPostTypeArchiveLocationKey = 'custom_post_type_archive_' . $archivePostType;
			}
		}

		if (MainFront::isHomePage()) {
			$criticalCssLocationKey = 'homepage'; // Main page of the website when just the default site URL is loaded
		} elseif ($isPostTypeArchive) {
			// Keep any post type archive out of the singular-page fallback.
			$criticalCssLocationKey = $customPostTypeArchiveLocationKey;
		} elseif (MainFront::isSingularPage()) {
			if (get_post_type() === 'post') { // "Posts" -> "All Posts" -> "View"
				$criticalCssLocationKey = 'posts';
			} elseif (get_post_type() === 'page') { // "Pages" -> "All Pages" -> "View"
				$criticalCssLocationKey = 'pages';
			} elseif (is_attachment()) {
				$criticalCssLocationKey = 'media'; // "Media" -> "Library" -> "View" (rarely used, but added it just in case)
			} else {
				global $post;

				if ( isset( $post->post_type ) && $post->post_type ) {
					$criticalCssLocationKey = 'custom_post_type_' . $post->post_type;
				}
			}
		} elseif (is_category()) {
		    $criticalCssLocationKey = 'category'; // "Posts" -> "Categories" -> "View"
		} elseif (is_tag()) {
		    $criticalCssLocationKey = 'tag'; // "Posts" -> "Tags" -> "View"
		} elseif (is_tax()) { // Custom Taxonomy (e.g. "product_cat" from WooCommerce, found in "Products" -> "Categories")
            global $wp_query;
            $object = $wp_query->get_queried_object();

            if ( isset( $object->taxonomy ) && $object->taxonomy ) {
                $criticalCssLocationKey = 'custom_taxonomy_' . $object->taxonomy;
            }
		} elseif (is_search()) {
			$criticalCssLocationKey = 'search'; // /?s=[keyword_here] in the front-end view
		} elseif (is_author()) {
			$criticalCssLocationKey = 'author'; // /author/demo/ in the front-end view
        } elseif (is_date()) {
			$criticalCssLocationKey = 'date'; // e.g. /2020/10/ in the front-end view
		} elseif (is_404()) {
			$criticalCssLocationKey = '404_not_found'; // e.g. /a-page-slug-that-is-non-existent/
		}

		if (! $criticalCssLocationKey) {
			return $args; // there's no critical CSS to apply on the current page as no critical CSS is set for it
		}

		$allCriticalCssOptions = self::getAllCriticalCssOptions($criticalCssLocationKey);

		if ( ! (isset($allCriticalCssOptions['enable']) && $allCriticalCssOptions['enable']) ) {
			return $args;  // there's no critical CSS to apply on the current page because it's disabled for the current page (location key)
		}

		$criticalCssContentJson = get_option(WPACU_PLUGIN_ID . '_critical_css_location_key_' . $criticalCssLocationKey);
		$criticalCssContentArray = @json_decode($criticalCssContentJson, true);

		// Issues with decoding the JSON content? Do not apply any critical CSS
		if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
			return $args;
		}

		if (isset($allCriticalCssOptions['show_method'], $criticalCssContentArray['content_minified']) && $allCriticalCssOptions['show_method'] === 'minified' && $criticalCssContentArray['content_minified']) {
			$args['content'] = stripslashes($criticalCssContentArray['content_minified']); // serve minified as instructed
		} elseif (isset($criticalCssContentArray['content_original']) && $criticalCssContentArray['content_original']) {
			$args['content'] = stripslashes($criticalCssContentArray['content_original']); // serve the original content which could be already minified
		}

		$args['location_key'] = $criticalCssLocationKey;

		return $args;
	}

	/**
	 * The same key is used in postmeta, termmeta and usermeta.
	 *
	 * @return string
	 */
	public static function getMetaKey()
	{
		return '_' . WPACU_PLUGIN_ID . '_critical_css';
	}

	/**
	 * @param mixed $storedValue
	 *
	 * @return array
	 */
	public static function decodeStoredCriticalCssData($storedValue)
	{
		if (is_array($storedValue)) {
			return $storedValue;
		}

		if ( ! (is_string($storedValue) && $storedValue !== '') ) {
			return array();
		}

		$storedData = @json_decode($storedValue, true);

		if (wpacuJsonLastError() !== JSON_ERROR_NONE || ! is_array($storedData)) {
			return array();
		}

		return $storedData;
	}

	/**
	 * @return array
	 */
	private static function getGranularCriticalCssDataForCurrentPage()
	{
		$metaKey = self::getMetaKey();

		$queriedObjectId = function_exists('get_queried_object_id') ? (int)get_queried_object_id() : 0;
		$queriedObject   = function_exists('get_queried_object') ? get_queried_object() : false;

		if ((is_category() || is_tag() || is_tax()) && $queriedObjectId > 0) {
			$storedData = self::decodeStoredCriticalCssData(get_term_meta($queriedObjectId, $metaKey, true));

			if ( ! empty($storedData) ) {
				return array(
					'data'         => $storedData,
					'location_key' => 'term_meta_' . $queriedObjectId
				);
			}
		}

		if (is_author()) {
			$authorId = $queriedObjectId;

			if ($authorId < 1) {
				$authorId = (int)get_query_var('author');
			}

			if ($authorId > 0) {
				$storedData = self::decodeStoredCriticalCssData(get_user_meta($authorId, $metaKey, true));

				if ( ! empty($storedData) ) {
					return array(
						'data'         => $storedData,
						'location_key' => 'user_meta_' . $authorId
					);
				}
			}
		}

		/*
		 * A queried post can represent a regular singular page, the static front page
		 * or the assigned posts page. CPT archives are intentionally excluded above.
		 */
		if ( ! is_post_type_archive()
		    && (MainFront::isSingularPage() || MainFront::isHomePage())
		    && $queriedObjectId > 0
		    && $queriedObject instanceof \WP_Post) {
			$storedData = self::decodeStoredCriticalCssData(get_post_meta($queriedObjectId, $metaKey, true));

			if ( ! empty($storedData) ) {
				return array(
					'data'         => $storedData,
					'location_key' => 'post_meta_' . $queriedObjectId
				);
			}
		}

		return array();
	}

	/**
	 * @param $criticalCssLocationKey
	 *
	 * @return array|mixed
	 */
	public static function getAllCriticalCssOptions($criticalCssLocationKey)
	{
		$criticalCssConfigDbListJson = get_option(WPACU_PLUGIN_ID . '_critical_css_config');

		if ($criticalCssConfigDbListJson) {
			$criticalCssConfigDbList = @json_decode($criticalCssConfigDbListJson, true);

			// Issues with decoding the JSON file? Return an empty list
			if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
				return array();
			}

			// Are there any critical CSS options for the targeted location?
			if ( ! empty( $criticalCssConfigDbList[$criticalCssLocationKey] ) ) {
				return $criticalCssConfigDbList[$criticalCssLocationKey];
			}
		}

		return array();
	}
}
