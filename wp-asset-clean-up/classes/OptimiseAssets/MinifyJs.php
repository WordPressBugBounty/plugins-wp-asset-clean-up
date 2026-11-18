<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\MainFront;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\Regex;

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
        // Legacy HTML line comments and script escape states depend on line
        // breaks. The minifier can join a comment to executable code or expose
        // a closing script token. Preserve such sources, including literal
        // examples, rather than guessing whether removing whitespace is safe.
        if (strpos($jsContent, '<!--') !== false || strpos($jsContent, '-->') !== false) {
            return $jsContent;
        }

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
						if (isset($_GET['wpacu_debug'])) {
							if (\WpAssetCleanUp\DebugOptimizationDetails::enabled()) {
								\WpAssetCleanUp\DebugOptimizationDetails::markCachedMinification();
							}
						}
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
        $src = trim((string)$src);

        if ($src === '') {
            return false;
        }

        // Things like WP Fastest Cache Toolbar JS shouldn't be minified and take up space on the server
        if ($handle !== '' && in_array($handle, MainFront::instance()->getSkipAssets('scripts'))) {
            return true;
        }

        $rules = array(
            '#/wp-content/plugins/wp-asset-clean-up(.*?)\.js#',

            // Other libraries from the core that end in .min.js
            '#/wp-includes/(.*?).min\.js#',

            // jQuery & jQuery Migrate
            '#/wp-includes/js/jquery/jquery\.js#',
            '#/wp-includes/js/jquery/jquery-migrate\.js#',

            // Files within /wp-content/cache/
            // Could belong to plugins such as "Elementor", "Oxygen" etc.
            '#/wp-content/cache/(.*?)\.js#',

            // Already minified, and it also has a random name making the cache folder make bigger
            '#/wp-content/bs-booster-cache/#',

            // Elementor .min.js
            '#/wp-content/plugins/elementor/assets/(.*?)\.min\.js#',

            // WooCommerce Assets
            '#/wp-content/plugins/woocommerce/assets/js/(.*?)\.min\.js#',

            // Google Site Kit
            // The files are already optimized (they just have comments once in a while)
            // Minifying them causes some errors, so better to leave them load as they are
            '#/wp-content/plugins/google-site-kit/#',

            // GiveWP: the files are already optimized (they just have comments once in a while)
            '#/wp-content/plugins/give/assets/dist/js/#',

            // TranslatePress Multilingual
            '#/translatepress-multilingual/assets/js/trp-editor\.js#',

            // Query Monitor
            '#/query-monitor/assets/query-monitor\.js#'
        );

        $rules = Misc::replaceRelPluginPath($rules);

        if (Main::instance()->settings['minify_loaded_js_exceptions'] !== '') {
            $rules = array_merge(
                $rules,
                Regex::splitRules(Main::instance()->settings['minify_loaded_js_exceptions'])
            );
        }

        return Regex::matchesAnyRule($rules, $src);
    }

    /**
     * Alter executable inline scripts without changing identical text in HTML
     * literals, templates, attributes or data scripts. Keep source byte offsets
     * so only each eligible script body is replaced.
     *
     * @param string $htmlSource
     *
     * @return string
     */
    public static function minifyOrAlterInlineScriptTags($htmlSource)
    {
        if (stripos($htmlSource, '<script') === false) {
            return $htmlSource;
        }

        $scripts = HtmlCleanup::getScriptTokens($htmlSource);
        if ($scripts === false || empty($scripts)) {
            return $htmlSource;
        }

        $doMinifyInlineTag  = in_array(Main::instance()->settings['minify_loaded_js_for'], array('inline', 'all')) && self::isMinifyJsEnabled();
        $skipTagsContaining = apply_filters('wpacu_internal_minify_js_inline_script_skip_tags_containing', array(
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
        $skipTagsContaining = array_map(static function ($toMatch) {
            return preg_quote($toMatch, '/');
        }, $skipTagsContaining);

        // Work backwards so replacing a body cannot invalidate earlier offsets.
        foreach (array_reverse($scripts) as $script) {
            $attributes = $script['attributes'];
            if (isset($attributes['src']) || isset($attributes['data-wpacu-skip']) || $script['manager']) {
                continue;
            }

            // Preserve this pass's classic text/javascript eligibility. Other
            // executable types may have their fonts rewritten by the earlier
            // selective font pass, without opting them into this minifier.
            $scriptType    = isset($attributes['type']) ? strtolower(trim($attributes['type'])) : '';
            $scriptAltType = isset($attributes['data-alt-type']) ? strtolower(trim($attributes['data-alt-type'])) : '';
            if (($scriptType !== '' && $scriptType !== 'text/javascript')
                || ($scriptAltType !== '' && $scriptAltType !== 'text/javascript')
                || ($scriptType === '' && isset($attributes['language'])
                    && ! in_array(strtolower(trim($attributes['language'])), array('', 'javascript', 'ecmascript'), true)) ) {
                continue;
            }

            $originalTag = substr($htmlSource, $script['offset'], $script['length']);
            if (preg_match('/(' . implode('|', $skipTagsContaining) . ')/', $originalTag) !== 0) {
                continue;
            }

            $originalTagContents = $script['content'];
            $newTagContents      = OptimizeJs::maybeAlterContentForInlineScriptTag($originalTagContents, $doMinifyInlineTag);
            if (is_string($newTagContents) && $newTagContents !== $originalTagContents) {
                $htmlSource = substr_replace($htmlSource, $newTagContents,
                    $script['offset'] + strlen($script['opening']), strlen($originalTagContents));
            }
        }

        return $htmlSource;
    }

	/**
	 * @return bool
	 */
	public static function isMinifyJsEnabled()
	{
        if (!empty($GLOBALS['wpacu_debug_page_options']['no_js_minify'])) {
            return false;
        }
		if (defined('WPACU_IS_MINIFY_JS_ENABLED')) {
			return WPACU_IS_MINIFY_JS_ENABLED;
		}

		// Request Minify On The Fly
		// It will preview the page with JS minified
		// Only if the admin is logged-in as it uses more resources (CPU / Memory)
		if ( isset($_GET['wpacu_js_minify']) && Menu::userCanAccessPlugin()) {
			self::isMinifyJsEnabledChecked('true');
			return true;
		}

		if ( isset($_REQUEST['wpacu_no_js_minify']) || // not on query string request (debugging purposes)
		     is_admin() || // not for Dashboard view
		     (! Main::instance()->settings['minify_loaded_js']) || // Minify JS has to be Enabled
		     (Main::instance()->settings['test_mode'] && ! Menu::userCanAccessPlugin()) ) { // Does not trigger if "Test Mode" is Enabled
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
		// Early checks (e.g. on init) do not yet know the current page's options.
		if ( ! defined('WPACU_CURRENT_PAGE_ID') ) {
			return;
		}

		if ( ! defined('WPACU_IS_MINIFY_JS_ENABLED') ) {
			if ( $value === 'true' ) {
				define( 'WPACU_IS_MINIFY_JS_ENABLED', true );
			} elseif ( $value === 'false' ) {
				define( 'WPACU_IS_MINIFY_JS_ENABLED', false );
			}
		}
	}
}
