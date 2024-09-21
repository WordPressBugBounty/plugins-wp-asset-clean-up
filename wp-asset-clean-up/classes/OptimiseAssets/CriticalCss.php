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
				if (preg_match('#data-wpacu-skip([=>/ ])#i', $matchedTag)
				    || strpos($matchedTag, 'data-wpacu-apply-media-query=') !== false) {
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
		$criticalCssLocationKey = false; // default value until any location is detected (e.g. homepage)

		if (MainFront::isHomePage()) {
			$criticalCssLocationKey = 'homepage'; // Main page of the website when just the default site URL is loaded
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
