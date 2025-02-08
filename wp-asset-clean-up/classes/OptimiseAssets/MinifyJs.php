<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\MainFront;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Misc;

/**
 * Class MinifyJs
 * @package WpAssetCleanUp\OptimiseAssets
 */
class MinifyJs
{
	/**
	 * @param $jsContent
	 *
	 * @return string|string[]|null
	 */
	public static function applyMinification($jsContent)
	{
		if ( ! class_exists('\MatthiasMullieWpacu\Minify\JS') ) {
                require_once WPACU_PLUGIN_DIR . '/vendor/autoload.php';
            }

			if (class_exists('\MatthiasMullieWpacu\Minify\JS')) {
				$sha1OriginalContent = sha1($jsContent);
				$checkForAlreadyMinifiedShaOne = mb_strlen($jsContent) > 40000;

				// Let's check if the content is already minified
				// Save resources as the minify process can take time if the content is very large
				// Limit the total number of entries to 100: if it's more than that, it's likely because there's dynamic JS altering on every page load
				if ($checkForAlreadyMinifiedShaOne && OptimizeCommon::originalContentIsAlreadyMarkedAsMinified($sha1OriginalContent, 'scripts')) {
					return $jsContent;
				}

				// Minify it
				$alreadyMinified = false; // default

				$minifier = new \MatthiasMullieWpacu\Minify\JS();
                $minifier->setParamType('content');
                $minifier->add($jsContent);

				$minifiedContent = trim($minifier->minify());

				if (trim($minifiedContent) === trim(trim($jsContent, ';'))) {
					$minifiedContent = $jsContent; // consider them the same if only the ';' at the end was stripped (it doesn't worth the resources that would be used)
					$alreadyMinified = true;
				}

				// If the resulting content is the same, mark it as minified to avoid the minify process next time
				if ($checkForAlreadyMinifiedShaOne && $alreadyMinified) {
					// If the resulting content is the same, mark it as minified to avoid the minify process next time
					OptimizeCommon::originalContentMarkAsAlreadyMinified( $sha1OriginalContent, 'scripts' );
				}

				return $minifiedContent;
			}

			return $jsContent;
		}

	/**
	 * @param $src
	 * @param string $handle
	 *
	 * @return bool
	 */
	public static function skipMinify($src, $handle = '')
	{
		// Things like WP Fastest Cache Toolbar JS shouldn't be minified and take up space on the server
		if ($handle !== '' && in_array($handle, MainFront::instance()->getSkipAssets('scripts'))) {
			return true;
		}

		$regExps = array(
			'#/wp-content/plugins/wp-asset-clean-up(.*?).js#',

			// Other libraries from the core that end in .min.js
			'#/wp-includes/(.*?).min.js#',

			// jQuery & jQuery Migrate
			'#/wp-includes/js/jquery/jquery.js#',
			'#/wp-includes/js/jquery/jquery-migrate.js#',

			// Files within /wp-content/uploads/
			// Files within /wp-content/uploads/ or /wp-content/cache/
			// Could belong to plugins such as "Elementor, "Oxygen" etc.
			//'#/wp-content/uploads/(.*?).js#',
			'#/wp-content/cache/(.*?).js#',

			// Already minified, and it also has a random name making the cache folder make bigger
			'#/wp-content/bs-booster-cache/#',

			// Elementor .min.js
			'#/wp-content/plugins/elementor/assets/(.*?).min.js#',

			// WooCommerce Assets
			'#/wp-content/plugins/woocommerce/assets/js/(.*?).min.js#',

			// Google Site Kit
			// The files are already optimized (they just have comments once in a while)
			// Minifying them causes some errors, so better to leave them load as they are
			'#/wp-content/plugins/google-site-kit/#',

			// GiveWP: the files are already optimized (they just have comments once in a while)
			'#/wp-content/plugins/give/assets/dist/js/#',

            // TranslatePress Multilingual
            '#/translatepress-multilingual/assets/js/trp-editor.js#',

			// Query Monitor
			'#/query-monitor/assets/query-monitor.js#'

			);

		$regExps = Misc::replaceRelPluginPath($regExps);

		if (Main::instance()->settings['minify_loaded_js_exceptions'] !== '') {
			$loadedJsExceptionsPatterns = trim(Main::instance()->settings['minify_loaded_js_exceptions']);

			if (strpos($loadedJsExceptionsPatterns, "\n") !== false) {
				// Multiple values (one per line)
				foreach (explode("\n", $loadedJsExceptionsPatterns) as $loadedJsExceptionPattern) {
					$regExps[] = '#'.trim($loadedJsExceptionPattern).'#';
				}
			} else {
				// Only one value?
				$regExps[] = '#'.trim($loadedJsExceptionsPatterns).'#';
			}
		}

		foreach ($regExps as $regExp) {
			if ( preg_match( $regExp, $src ) || ( strpos($src, $regExp) !== false ) ) {
				return true;
			}
		}

		return false;
	}

	/**
     * This is for minifying the inline tag and/or alter it in case the following options were enabled:
     * 1) "font-display" to be set in case Google Fonts are loading in JS content
     * 2) Strip any references to Google Fonts in case the option remove the Google Fonts was enabled
     *
	 * @param $htmlSource
	 *
	 * @return mixed|string
	 */
	public static function minifyOrAlterInlineScriptTags($htmlSource)
	{
		if (stripos($htmlSource, '<script') === false) {
			return $htmlSource; // no SCRIPT tags, hmm
		}

        $doMinifyInlineTag = in_array(Main::instance()->settings['minify_loaded_js_for'], array('inline', 'all')) && self::isMinifyJsEnabled();

		$skipTagsContaining = array_map( static function ( $toMatch ) {
			return preg_quote($toMatch, '/');
		}, array(
			'data-wpacu-skip',
			'/* <![CDATA[ */', // added via wp_localize_script()
			'wpacu-google-fonts-async-load',
			'wpacu-preload-async-css-fallback',
			'document.body.prepend(wpacuLinkTag',
			'var wc_product_block_data = JSON.parse( decodeURIComponent(',
			'/(^|\s)(no-)?customize-support(?=\s|$)/', // WP Core
			'b[c] += ( window.postMessage && request ? \' \' : \' no-\' ) + cs;', // WP Core
			'data-wpacu-own-inline-script' // Only shown to the admin, irrelevant for any optimization (save resources)
		));

		// Do not perform another \DOMDocument call if it was done already somewhere else (e.g. CombineJs)
		$fetchType = 'regex'; // 'regex' or 'dom'

		if ($fetchType === 'regex') {
			preg_match_all( '@(<script[^>]*?>).*?</script>@si', $htmlSource, $matchesScriptTags, PREG_SET_ORDER );

			if ( $matchesScriptTags === null ) {
				return $htmlSource;
			}

			foreach ($matchesScriptTags as $matchedScript) {
				if (isset($matchedScript[0]) && $matchedScript[0]) {
					$originalTag = $matchedScript[0];

                    if (strncasecmp($originalTag, '<script src=', 12) === 0 || strncasecmp($originalTag, '<script type="application', 25) === 0) {
                        continue;
                    }

					// Only 'text/javascript' type is allowed for minification
                    // If the tag starts with <script> there's no point to do further checks
                    if (strncasecmp($originalTag, '<script>', 8) !== 0) {
                        $scriptType = 'text/javascript'; // default

                        if (strpos($originalTag, ' type') !== false) {
                            $scriptType = Misc::getValueFromTag($originalTag, 'type', 'dom_with_fallback') ?: 'text/javascript';
                        }

                        if ($scriptType !== 'text/javascript') {
                            continue;
                        }

                        $scriptAltType = '';

                        if (strpos($originalTag, 'data-alt-type') !== false) {
                            $scriptAltType = Misc::getValueFromTag($originalTag, 'data-alt-type', 'dom_with_fallback');
                        }

                        if ($scriptAltType && $scriptAltType !== 'text/javascript') {
                            continue;
                        }
                    }

                    if (strpos($originalTag, 'src=') !== false && Misc::endsWith($originalTag, '-js\'></script>')) {
                        continue;
                    }

                    // No need to use extra resources as the tag is already minified
                    if ( preg_match( '/(' . implode( '|', $skipTagsContaining ) . ')/', $originalTag ) ) {
                        continue;
                    }

                    if (Misc::getValueFromTag($originalTag, 'src', 'dom_with_fallback') && strtolower(substr($originalTag, -strlen('></script>'))) === strtolower('></script>')) {
                        // Only inline SCRIPT tags allowed
                        continue;
                    }

					$tagOpen = $matchedScript[1];
					$withTagOpenStripped = substr($originalTag, strlen($tagOpen));
					$originalTagContents = substr($withTagOpenStripped, 0, -strlen('</script>'));

					$newTagContents = OptimizeJs::maybeAlterContentForInlineScriptTag( $originalTagContents, $doMinifyInlineTag );

					if ( $newTagContents !== $originalTagContents ) {
						$htmlSource = str_ireplace( '>' . $originalTagContents . '</script', '>' . $newTagContents . '</script', $htmlSource );
					}
				}
			}
		}

		return $htmlSource;
	}

	/**
	 * @return bool
	 */
	public static function isMinifyJsEnabled()
	{
		if (defined('WPACU_IS_MINIFY_JS_ENABLED')) {
			return WPACU_IS_MINIFY_JS_ENABLED;
		}

		// Request Minify On The Fly
		// It will preview the page with JS minified
		// Only if the admin is logged-in as it uses more resources (CPU / Memory)
		if ( isset($_GET['wpacu_js_minify']) && Menu::userCanAccessAssetCleanUp()) {
			self::isMinifyJsEnabledChecked('true');
			return true;
		}

		if ( isset($_REQUEST['wpacu_no_js_minify']) || // not on query string request (debugging purposes)
		     is_admin() || // not for Dashboard view
		     (! Main::instance()->settings['minify_loaded_js']) || // Minify JS has to be Enabled
		     (Main::instance()->settings['test_mode'] && ! Menu::userCanAccessAssetCleanUp()) ) { // Does not trigger if "Test Mode" is Enabled
			self::isMinifyJsEnabledChecked('false');
			return false;
		}

		$isSingularPage = (int)wpacuGetConstant('WPACU_CURRENT_PAGE_ID') > 0 && MainFront::isSingularPage();

		if ($isSingularPage || MainFront::isHomePage()) {
			// If "Do not minify JS on this page" is checked in "Asset CleanUp: Options" side meta box
			if ($isSingularPage) {
				$pageOptions = MetaBoxes::getPageOptions( WPACU_CURRENT_PAGE_ID ); // Singular page
			} else {
				$pageOptions = MetaBoxes::getPageOptions(0, 'front_page'); // Home page
			}

			if ( isset( $pageOptions['no_js_minify'] ) && $pageOptions['no_js_minify'] ) {
				self::isMinifyJsEnabledChecked('false');
				return false;
			}
		}

		if (OptimizeJs::isOptimizeJsEnabledByOtherParty('if_enabled')) {
			self::isMinifyJsEnabledChecked('false');
			return false;
		}

		self::isMinifyJsEnabledChecked('true');
		return true;
	}

	/**
	 * @param $value
	 */
	public static function isMinifyJsEnabledChecked($value)
	{
		if ( ! defined('WPACU_IS_MINIFY_JS_ENABLED') ) {
			if ( $value === 'true' ) {
				define( 'WPACU_IS_MINIFY_JS_ENABLED', true );
			} elseif ( $value === 'false' ) {
				define( 'WPACU_IS_MINIFY_JS_ENABLED', false );
			}
		}
	}
}
