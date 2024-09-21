<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\MainFront;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Misc;

/**
 * Class MinifyCss
 * @package WpAssetCleanUp\OptimiseAssets
 */
class MinifyCss
{
	/**
	 * @param $cssContent
	 * @param bool $forInlineStyle
     * *
	 * @return string
     * @noinspection PhpUnusedParameterInspection*/
	public static function applyMinification($cssContent, $forInlineStyle = false
		)
	{
		if ( ! class_exists('\MatthiasMullieWpacu\Minify\CSS') ) {
                require_once WPACU_PLUGIN_DIR . '/vendor/autoload.php';
            }

			if (class_exists('\MatthiasMullieWpacu\Minify\CSS')) {
				$sha1OriginalContent = sha1($cssContent);
				$checkForAlreadyMinifiedShaOne = mb_strlen($cssContent) > 40000;

				// Let's check if the content is already minified
				// Save resources as the minifying process can take time if the content is very large
				// Limit the total number of entries tp 100: if it's more than that, it's likely because there's dynamic JS altering on every page load
				if ($checkForAlreadyMinifiedShaOne && OptimizeCommon::originalContentIsAlreadyMarkedAsMinified($sha1OriginalContent, 'styles')) {
					return $cssContent;
				}

				$cssContentBeforeAnyBugChanges = $cssContent;

				// [CUSTOM BUG FIX]
				// Encode the special matched content to avoid any wrong minification from the minifier
				$hasVarWithZeroUnit = false;

				preg_match_all('#--([a-zA-Z0-9_-]+):(\s+)0(em|ex|%|px|cm|mm|in|pt|pc|ch|rem|vh|vw|vmin|vmax|vm)#', $cssContent, $cssVariablesMatches);

				if ( ! empty($cssVariablesMatches[0]) ) {
					$hasVarWithZeroUnit = true;

					foreach ($cssVariablesMatches[0] as $zeroUnitMatch) {
						$cssContent = str_replace( $zeroUnitMatch, '[wpacu]' . base64_encode( $zeroUnitMatch ) . '[/wpacu]', $cssContent );
					}
				}

				// Fix: If the content is something like "calc(50% - 22px) calc(50% - 22px);" then leave it as it is
				preg_match_all('#calc(|\s+)\((.*?)(;|})#si', $cssContent, $cssCalcMatches);

				$multipleOrSpecificCalcMatches = array(); // with multiple calc() or with at least one calc() that contains new lines

				if ( ! empty($cssCalcMatches[0]) ) {
					foreach ($cssCalcMatches[0] as $cssCalcMatch) {
						if (substr_count($cssCalcMatch, 'calc') > 1 || strpos($cssCalcMatch, "\n") !== false) {
							$cssContent = str_replace( $cssCalcMatch, '[wpacu]' . base64_encode( $cssCalcMatch ) . '[/wpacu]', $cssContent );
							$multipleOrSpecificCalcMatches[] = $cssCalcMatch;
						}
					}
				}

				// [/CUSTOM BUG FIX]

				$minifier = new \MatthiasMullieWpacu\Minify\CSS();
                $minifier->setParamType('content');
                $minifier->add($cssContent);

				if ( $forInlineStyle ) {
                    // If the minification is applied for inlined CSS (within STYLE) leave the background URLs unchanged as it sometimes lead to issues
                    $minifier->setImportExtensions(array());
                }

                $minifiedContent = trim( $minifier->minify() );

				// [CUSTOM BUG FIX]
				// Restore the original content
				if ($hasVarWithZeroUnit) {
					foreach ( $cssVariablesMatches[0] as $zeroUnitMatch ) {
						$zeroUnitMatchAlt = str_replace(': 0', ':0', $zeroUnitMatch); // remove the space
						$minifiedContent = str_replace( '[wpacu]' . base64_encode( $zeroUnitMatch ) . '[/wpacu]', $zeroUnitMatchAlt, $minifiedContent );
					}
				}

				if ( ! empty($multipleOrSpecificCalcMatches) ) {
					foreach ( $multipleOrSpecificCalcMatches as $cssCalcMatch ) {
						$originalCssCalcMatch = $cssCalcMatch;
						$cssCalcMatch = preg_replace(array('#calc\(\s+#', '#\s+\);#'), array('calc(', ');'), $originalCssCalcMatch);
						$cssCalcMatch = str_replace(' ) calc(', ') calc(', $cssCalcMatch);
						$minifiedContent = str_replace( '[wpacu]' . base64_encode( $originalCssCalcMatch ) . '[/wpacu]', $cssCalcMatch, $minifiedContent );
					}
				}
				// [/CUSTOM BUG FIX]

				// Is there any [wpacu] left? Hmm, the replacement wasn't alright. Make sure to use the original minified version
				if (strpos($minifiedContent, '[wpacu]') !== false && strpos($minifiedContent, '[/wpacu]') !== false) {
					$minifier = new \MatthiasMullieWpacu\Minify\CSS();
                    $minifier->setParamType('content');
                    $minifier->add($cssContentBeforeAnyBugChanges);

					if ( $forInlineStyle ) {
						// If the minification is applied for inlined CSS (within STYLE) leave the background URLs unchanged as it sometimes leads to issues
						$minifier->setImportExtensions( array() );
					}

					$minifiedContent = trim( $minifier->minify() );
				}

				if ($checkForAlreadyMinifiedShaOne && $minifiedContent === $cssContent) {
					// If the resulting content is the same, mark it as minified to avoid the minify process next time
					OptimizeCommon::originalContentMarkAsAlreadyMinified( $sha1OriginalContent, 'styles' );
				}

				return $minifiedContent;
			}

			return $cssContent;

		}

	/**
	 * @param $href
	 * @param string $handle
	 *
	 * @return bool
	 */
	public static function skipMinify($href, $handle = '')
	{
		// Things like WP Fastest Cache Toolbar CSS shouldn't be minified and take up space on the server
		if ($handle !== '' && in_array($handle, MainFront::instance()->getSkipAssets('styles'))) {
			return true;
		}

		// Some of these files (e.g. from Oxygen, WooCommerce) are already minified
		$regExps = array(
			'#/wp-content/plugins/wp-asset-clean-up(.*?).min.css#',

			// Formidable Forms
			'#/wp-content/plugins/formidable/css/formidableforms.css#',

			// Oxygen
			//'#/wp-content/plugins/oxygen/component-framework/oxygen.css#',

			// WooCommerce
			'#/wp-content/plugins/woocommerce/assets/css/woocommerce-layout.css#',
			'#/wp-content/plugins/woocommerce/assets/css/woocommerce.css#',
			'#/wp-content/plugins/woocommerce/assets/css/woocommerce-smallscreen.css#',
			'#/wp-content/plugins/woocommerce/assets/css/blocks/style.css#',

			// All the files from the "build" directory are already minified
			'#/woocommerce/packages/woocommerce-blocks/build/#',

			// Google Site Kit: the files are already optimized
			'#/wp-content/plugins/google-site-kit/#',

			// GiveWP: the files are already optimized
			'#/wp-content/plugins/give/assets/dist/css/#',

			// Other libraries from the core that end in .min.css
			'#/wp-includes/css/(.*?).min.css#',

			// Files within /wp-content/uploads/ or /wp-content/cache/
			// Could belong to plugins such as "Elementor", "Oxygen" etc.
			'#/wp-content/uploads/elementor/(.*?).css#',
			'#/wp-content/uploads/oxygen/css/(.*?)-(.*?).css#',
			'#/wp-content/cache/(.*?).css#',

			// Already minified, and it also has a random name making the cache folder make bigger
			'#/wp-content/bs-booster-cache/#',

			// Query Monitor
			'#/plugins/query-monitor/assets/query-monitor.css#'

			);

		$regExps = Misc::replaceRelPluginPath($regExps);

		if (Main::instance()->settings['minify_loaded_css_exceptions'] !== '') {
			$loadedCssExceptionsPatterns = trim(Main::instance()->settings['minify_loaded_css_exceptions']);

			if (strpos($loadedCssExceptionsPatterns, "\n")) {
				// Multiple values (one per line)
				foreach (explode("\n", $loadedCssExceptionsPatterns) as $loadedCssExceptionPattern) {
					$regExps[] = '#'.trim($loadedCssExceptionPattern).'#';
				}
			} else {
				// Only one value?
				$regExps[] = '#'.trim($loadedCssExceptionsPatterns).'#';
			}
		}

		foreach ($regExps as $regExp) {
			if ( preg_match( $regExp, $href ) || ( strpos($href, $regExp) !== false ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $htmlSource
	 *
	 * @return mixed|string
	 */
	public static function minifyInlineStyleTags($htmlSource)
	{
		if (stripos($htmlSource, '<style') === false) {
			return $htmlSource; // no STYLE tags
		}

		$skipTagsContaining = array(
			'data-wpacu-skip',
			'astra-theme-css-inline-css',
			'astra-edd-inline-css',
			'et-builder-module-design-cached-inline-styles',
			'fusion-stylesheet-inline-css',
			'woocommerce-general-inline-css',
			'woocommerce-inline-inline-css',
			'data-wpacu-own-inline-style',
			// Only shown to the admin, irrelevant for any optimization (save resources)
			'data-wpacu-inline-css-file'
			// already minified/optimized since the INLINE was generated from the cached file
		);

		$fetchType = 'regex';

		if ( $fetchType === 'regex' ) {
			preg_match_all( '@(<style[^>]*?>).*?</style>@si', $htmlSource, $matchesStyleTags, PREG_SET_ORDER );
			if ( $matchesStyleTags === null ) {
				return $htmlSource;
			}

			foreach ($matchesStyleTags as $matchedStyle) {
				if ( ! (isset($matchedStyle[0]) && $matchedStyle[0]) ) {
					continue;
				}

				$originalTag = $matchedStyle[0];

				if (substr($originalTag, -strlen('></style>')) === strtolower('></style>')) {
					// No empty STYLE tags
					continue;
				}

				// No need to use extra resources as the tag is already minified
				if ( preg_match( '(' . implode( '|', $skipTagsContaining ) . ')', $originalTag ) ) {
					continue;
				}

				$tagOpen = $matchedStyle[1];

				$withTagOpenStripped = substr($originalTag, strlen($tagOpen));
				$originalTagContents = substr($withTagOpenStripped, 0, -strlen('</style>'));

				if ( $originalTagContents ) {
					$newTagContents = OptimizeCss::maybeAlterContentForInlineStyleTag( $originalTagContents, true, array( 'just_minify' ) );

					// Only comments or no content added to the inline STYLE tag? Strip it completely to reduce the number of DOM elements
					if ( $newTagContents === '/**/' || ! $newTagContents ) {
						$htmlSource = str_replace( '>' . $originalTagContents . '</', '></', $htmlSource );

						preg_match( '#<style.*?>#si', $originalTag, $matchFromStyle );

						if ( isset( $matchFromStyle[0] ) && $styleTagWithoutContent = $matchFromStyle[0] ) {
							$styleTagWithoutContentAlt = str_ireplace( '"', '\'', $styleTagWithoutContent );
							$htmlSource                = str_ireplace( array(
								$styleTagWithoutContent . '</style>',
								$styleTagWithoutContentAlt . '</style>'
							), '', $htmlSource );
						}
					} else {
						// It has content; do the replacement
						$htmlSource = str_replace(
							'>' . $originalTagContents . '</style>',
							'>' . $newTagContents . '</style>',
							$htmlSource
						);
					}
				}
			}
		}

		return $htmlSource;
	}

	/**
	 * @return bool
	 */
	public static function isMinifyCssEnabled()
	{
		if (defined('WPACU_IS_MINIFY_CSS_ENABLED')) {
			return WPACU_IS_MINIFY_CSS_ENABLED;
		}

		// Request Minify On The Fly
		// It will preview the page with CSS minified
		// Only if the admin is logged in as it uses more resources (CPU / Memory)
		if ( isset($_GET['wpacu_css_minify']) && Menu::userCanAccessAssetCleanUp() ) {
			self::isMinifyCssEnabledChecked('true');
			return true;
		}

		if ( isset($_REQUEST['wpacu_no_css_minify']) || // not on query string request (debugging purposes)
		     is_admin() || // not for Dashboard view
		     (! Main::instance()->settings['minify_loaded_css']) || // Minify CSS has to be Enabled
		     (Main::instance()->settings['test_mode'] && ! Menu::userCanAccessAssetCleanUp()) ) { // Does not trigger if "Test Mode" is Enabled
			self::isMinifyCssEnabledChecked('false');
			return false;
		}

		$isSingularPage = (int)wpacuGetConstant('WPACU_CURRENT_PAGE_ID') > 0 && MainFront::isSingularPage();

		if ($isSingularPage || MainFront::isHomePage()) {
			// If "Do not minify CSS on this page" is checked in "Asset CleanUp: Options" side meta box
			if ($isSingularPage) {
				$pageOptions = MetaBoxes::getPageOptions( WPACU_CURRENT_PAGE_ID ); // Singular page
			} else {
				$pageOptions = MetaBoxes::getPageOptions(0, 'front_page'); // Home page
			}

			if ( isset( $pageOptions['no_css_minify'] ) && $pageOptions['no_css_minify'] ) {
				self::isMinifyCssEnabledChecked('false');
				return false;
			}
		}

		if (OptimizeCss::isOptimizeCssEnabledByOtherParty('if_enabled')) {
			self::isMinifyCssEnabledChecked('false');
			return false;
		}

		self::isMinifyCssEnabledChecked('true');
		return true;
	}

	/**
	 * @param $value
	 */
	public static function isMinifyCssEnabledChecked($value)
	{
		if ( ! defined('WPACU_IS_MINIFY_CSS_ENABLED') ) {
			if ( $value === 'true' ) {
				define( 'WPACU_IS_MINIFY_CSS_ENABLED', true );
			} elseif ( $value === 'false' ) {
				define( 'WPACU_IS_MINIFY_CSS_ENABLED', false );
			}
		}
	}
}
