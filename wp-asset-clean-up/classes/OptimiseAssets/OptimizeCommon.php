<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Admin\MainAdmin;
use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\Admin\Plugin;
use WpAssetCleanUp\Admin\Tools;
use WpAssetCleanUp\CleanUp;
use WpAssetCleanUp\Debug;
use WpAssetCleanUp\FileSystem;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\MainFront;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\Settings;

/**
 * Class OptimizeCommon
 * @package WpAssetCleanUp
 */
class OptimizeCommon
{
	/**
	 * @var string
	 */
	public static $relPathPluginCacheDirDefault = '/cache/asset-cleanup/'; // keep forward slash at the end

	/**
	 * @var string
	 */
	public static $optimizedSingleFilesDir = 'item';

	/**
	 * @var float|int
	 */
	public static $cachedAssetFileExpiresIn = 86400; // 1 day in seconds

	/**
	 * @var array
	 */
	public static $wellKnownExternalHosts = array(
		'googleapis.com',
		'bootstrapcdn.com',
		'cloudflare.com',
		'jsdelivr.net'
	);

    /**
	 *
     * @noinspection PhpUndefinedConstantInspection
     */
	public function init()
	{
        // The transient is checked in "OwnAssets" -- "generateObjectData"
        // If it's still set, the AJAX call will be triggered to clear the cache
		add_action('switch_theme',       function() { set_transient(WPACU_PLUGIN_ID.'_clear_assets_cache', true); });
		add_action('after_switch_theme', function() { set_transient(WPACU_PLUGIN_ID.'_clear_assets_cache', true); });

		// Is WP Rocket's page cache cleared? Clear Asset CleanUp's CSS cache files too
		if ( isset($_GET['action']) && $_GET['action'] === 'purge_cache' ) {
			// Leave its default parameters, no redirect needed
			add_action('init', static function() {
                if ( ! Menu::userCanAccessAssetCleanUp() ) {
                    return; // in this case, only the admin can clear the cache
                }

				OptimizeCommon::clearCache();
			}, PHP_INT_MAX);
		}

		add_action('admin_post_assetcleanup_clear_assets_cache', static function() {
			set_transient(WPACU_PLUGIN_ID . '_clear_assets_cache_via_link', true);
			self::clearCache(true);
		});

		// In the admin area, show a notice that the caching has been cleared along with the clearing date and time
		if (isset($_GET['wpacu_cache_cleared']) && $_GET['wpacu_cache_cleared'] && is_admin() && get_transient(WPACU_PLUGIN_ID . '_cache_just_cleared_via_link_dash_area')) {
			add_action('admin_notices', array($this, 'noticeCacheCleared'));
			delete_transient(WPACU_PLUGIN_ID . '_cache_just_cleared_via_link_dash_area');
		}

		// When a post is moved to the trash / deleted
		// clear its cache as its useless and there's no point in having extra files/directories in the caching directory
		add_action('wp_trash_post', array($this, 'clearItemStorageForPost' )); // $postId is passed as a parameter
		add_action('delete_post',   array($this, 'clearItemStorageForPost' )); // $postId is passed as a parameter

		// When a post is edited are within the Dashboard
		add_action('admin_init', static function() {
			if (($postId = Misc::getVar('get', 'post')) && Misc::getVar('get', 'action') === 'edit') {
				self::clearItemStorageForPost($postId, true);
			}
		});

		// Keep used resources to the minimum and trigger any clearing of the page's CSS/JS caching
		// for the admin while he has the right privileges and a single "post" page is visited
		add_action('wp', static function() {
			if ( ! is_admin() && Menu::userCanAccessAssetCleanUp() && MainFront::isSingularPage() ) {
				global $post;

				if (isset($post->ID) && $post->ID) {
					self::clearItemStorageForPost($post->ID, true);
				}
			}
		});

		// Autoptimize Compatibility: Make sure Asset CleanUp's changes are applied
		add_filter('autoptimize_filter_html_before_minify', static function($htmlSource) {
			return self::alterHtmlSource($htmlSource, true);
		});

		if (wpacuIsPluginActive('cache-enabler/cache-enabler.php')) {
			if (defined('CE_VERSION') && version_compare(CE_VERSION, '1.6.0', '<')) {
				// Cache Enabler: BEFORE 1.6.0 (1.5.5 and below)
				// Make sure HTML changes are applied to cached pages from "Cache Enabler" plugin
				add_filter( 'cache_enabler_before_store', static function( $htmlSource ) {
					return self::alterHtmlSource( $htmlSource ); // deprecated, include it in case other users have an older version of "Cache Enabler"
				}, 1, 1 );
			} else {
				// Cache Enabler: 1.6.0+
				global $cache_enabler_constants;

				if (isset($cache_enabler_constants['CACHE_ENABLER_VERSION']) && version_compare($cache_enabler_constants['CACHE_ENABLER_VERSION'], '1.6.0', '>=')) {
					add_filter( 'cache_enabler_page_contents_before_store', static function( $htmlSource ) {
						return self::alterHtmlSource( $htmlSource );
					}, 1, 1 );
				}
			}
		}

		// In case HTML Minify is enabled in W3 Total Cache, make sure any settings (e.g. JS combine) in Asset CleanUp will be applied
		add_filter('w3tc_minify_before', static function ($htmlSource) {
			return self::alterHtmlSource($htmlSource);
		}, 1, 1);

		// LiteSpeed Cache (partial compatibility)
		add_filter('litespeed_optm_html_head', static function($htmlHead) {
			if (! Main::instance()->preventAssetsSettings()) {
				$htmlHead = OptimizeCss::ignoreDependencyRuleAndKeepChildrenLoaded($htmlHead);
				$htmlHead = OptimizeJs::ignoreDependencyRuleAndKeepChildrenLoaded($htmlHead);
			}
			return $htmlHead;
		});
		add_filter('litespeed_optm_html_foot', static function($htmlFoot) {
			if (! Main::instance()->preventAssetsSettings()) {
				$htmlFoot = OptimizeCss::ignoreDependencyRuleAndKeepChildrenLoaded($htmlFoot);
				$htmlFoot = OptimizeJs::ignoreDependencyRuleAndKeepChildrenLoaded($htmlFoot);
			}
			return $htmlFoot;
		});

		// Make sure HTML changes, especially rules such as the ones from "Ignore dependency rules and keep 'children' loaded"
		// are applied to cached pages from "WP Rocket" plugin
		if (wpacuIsPluginActive('wp-rocket/wp-rocket.php')) {
			add_filter('rocket_buffer', static function($htmlSource) {
				return self::alterHtmlSource($htmlSource, true);
			});
		}

		// "Hide My WP Ghost – Security Plugin" - Make sure the alter the HTML (some files might be cached) before the security plugin proceeds with the alteration of the paths
		// This way, "Hide My WP Ghost – Security Plugin" would process the paths to the CSS/JS files that are already cached from /wp-content/cache/
		if ( ! wpacuIsDefinedConstant('DOING_AJAX') // not when /wp-admin/admin-ajax.php is called
             && class_exists('\HMWP_Classes_ObjController')
		    && method_exists('\HMWP_Models_Rewrite', 'find_replace')
		    && apply_filters('hmwp_process_buffer', true) // only when processing the buffer is turned ON
		) {
			add_filter('hmwp_process_buffer', '__return_false');
			add_filter('wpacu_print_info_comments_in_cached_assets', '__return_false'); // hide comments revealing the paths to serve the purpose of "Hide My WP Ghost – Security Plugin"

			add_filter('wpacu_html_source_after_optimization', function($htmlSource) {
				return \HMWP_Classes_ObjController::getClass('HMWP_Models_Rewrite')->find_replace($htmlSource);
			});
		}

		$this->maybeHtmlSourceAlterActions();

        // [START] Initiate Hardcoded Assets
		add_action( 'init', static function() {
			if (Main::instance()->isGetAssetsCall) {
				// Case 1: An AJAX call is made from the Dashboard
				MainAdmin::initBufferingForAjaxCallFromTheDashboard();
			} elseif (MainAdmin::useBufferingForEditFrontEndView()) {
				// Case 2: The logged-in admin manages the assets from the front-end view
				MainAdmin::initBufferingForFrontendManagement();
			}
		});
		// [END] Initiate Hardcoded Assets
	}

    /**
     * @return void
     */
    public function maybeHtmlSourceAlterActions()
    {
        if (in_array(Main::instance()->settings['alter_html_source_method'], array('wp_loaded', ''))) {
            // "wp_loaded" (legacy): This has been used since the release of the plugin
            // However, it's often not the most effective due to the fact that functions such as is_front_page()
            // are not available, due to early processing of the HTML source (before specific queries are triggered)
            add_action('wp_loaded', array($this, 'maybeAlterHtmlSourceViaWpLoaded'), 1);
        } else {
            // This has proven to be more effective in many environments, and users should test this one as well
            // As it usually waits until other optimization plugins did their changes, and then if it's something that is still updateable
            // it will update it (e.g. minify CSS files, if a plugin hasn't already done that)
            add_action('init', array($this, 'maybeStartBufferForAlterHtmlSource'), 0);

            if ( ! Main::instance()->isGetAssetsCall ) {
                // Trigger it on a regular page load
                add_action('shutdown', static function () {
                    /** @global bool $wpacuTriggerShutdownForHtmlAlter */
                    global $wpacuTriggerShutdownForHtmlAlter;

                    if ( ! $wpacuTriggerShutdownForHtmlAlter ) {
                        return;
                    }

                    if (ob_get_level() > 1) {
                        ob_end_flush();
                    }

                    $htmlSource = '';

                    // We'll need to get the number of ob levels we're in, so that we can iterate over each, collecting
                    // that buffer's output into the final output.
                    $htmlSourceLevel = ob_get_level();

                    for ($wpacuI = 0; $wpacuI < $htmlSourceLevel; $wpacuI++) {
                        $htmlSource .= ob_get_clean();
                    }

                    echo self::alterHtmlSource($htmlSource);
                }, -PHP_INT_MAX);
            }
        }
    }

    /**
     * This works like /?wpacu_no_load with a fundamental difference:
     * It needs to be triggered through a very early 'init' / 'setup_theme' action hook after all plugins are loaded, thus it can't be used in /early-triggers.php
     * e.g. in situations when the page is an AMP one, prevent any changes to the HTML source by Asset CleanUp (Pro)
     *
     * @param string $tagActionName
     * @param string $htmlSource
     *
     * @return bool
     * @noinspection PhpUndefinedFunctionInspection
     * @noinspection NestedAssignmentsUsageInspection
     */
    public static function preventAnyFrontendOptimization($tagActionName = '', $htmlSource = '')
    {
        if (isset($GLOBALS['wpacu_prevent_any_frontend_optimization'])) {
            return $GLOBALS['wpacu_prevent_any_frontend_optimization'];
        }

        // Not relevant in the Dashboard
        if (is_admin()) {
            $GLOBALS['wpacu_prevent_any_frontend_optimization'] = false;
            return false;
        }

        // Perhaps the editor from "Pro" (theme.co) is on OR a page such as /?amp is in use
        if (apply_filters('wpacu_prevent_any_frontend_optimization', false)) {
            $GLOBALS['wpacu_prevent_any_frontend_optimization'] = true;
            return true;
        }

        // e.g. /amp/ - /amp? - /amp/? - /?amp or ending in /amp
        $isAmpInRequestUri = ( (isset($_SERVER['REQUEST_URI']) && (preg_match('/(\/amp$|\/amp\?)|(\/amp\/|\/amp\/\?)/', $_SERVER['REQUEST_URI'])))
                               || isset($_GET['amp']) );

        // Is it an AMP endpoint?
        if ( ($isAmpInRequestUri && wpacuIsPluginActive('accelerated-mobile-pages/accelerated-mobile-pages.php')) // "AMP for WP – Accelerated Mobile Pages"
             || ($isAmpInRequestUri && wpacuIsPluginActive('amp/amp.php')) // "AMP – WordPress plugin"
             || (function_exists('is_wp_amp') && wpacuIsPluginActive('wp-amp/wp-amp.php') && is_wp_amp()) // "WP AMP — Accelerated Mobile Pages for WordPress and WooCommerce" (Premium plugin)
        ) {
            $GLOBALS['wpacu_prevent_any_frontend_optimization'] = true;
            return true; // do not print anything on an AMP page
        }

        // Some pages are AMP but their URI does not end in /amp
        if ( ! defined('WPACU_DO_EXTRA_CHECKS_FOR_AMP') &&
             ( wpacuIsPluginActive('accelerated-mobile-pages/accelerated-mobile-pages.php')
               || wpacuIsPluginActive('amp/amp.php')
               || wpacuIsPluginActive('wp-amp/wp-amp.php') )
        ) {
            define('WPACU_DO_EXTRA_CHECKS_FOR_AMP', true);
        }

        if (Main::isTestModeActiveAndVisitorNonAdmin()) {
            $GLOBALS['wpacu_prevent_any_frontend_optimization'] = true;
            return true;
        }

        // $tagActionName needs to be different from 'parse_query' because is_singular() would trigger too soon and cause notice errors
        // Has the following page option set: "Do not apply any front-end optimization on this page (this includes any changes related to CSS/JS files)"
        if ($tagActionName !== 'parse_query' && MetaBoxes::hasNoFrontendOptimizationPageOption()) {
            $GLOBALS['wpacu_prevent_any_frontend_optimization'] = true;
            return true;
        }

        // Only relevant if all the plugins are already loaded and in the front-end view
        if ( ! defined('WPACU_ALL_ACTIVE_PLUGINS_LOADED') ) {
            $GLOBALS['wpacu_prevent_any_frontend_optimization'] = false;
            return false;
        }

        // e.g. it could be JS content loaded dynamically such as /?wpml-app=ate-widget
        // in this case, any Asset CleanUp alteration of the content should be avoided
        // often, the code below is not reached as extra measures are taken before if well known plugins are used
        if ($htmlSource !== '') {
            $startsWithPassed = $endsWithPassed = false;

            // More common delimiters can be added with time
            // This is just an extra measure to prevent possible empty pages due to lots of memory used in case a possible JavaScript output is too large
            $startsWithAnyFromTheList = array(
                '/*!',
                '(function()'
            );

            $endsWithAnyFromTheList = array(
                ');',
                ')'
            );

            // Possible JS content
            foreach ($startsWithAnyFromTheList as $startsWithString) {
                if (substr(trim($htmlSource), 0, strlen($startsWithString)) === $startsWithString) {
                    $startsWithPassed = true;
                    break;
                }
            }

            if ($startsWithPassed) {
                foreach ($endsWithAnyFromTheList as $endsWithString) {
                    if (substr(trim($htmlSource), -strlen($endsWithString)) === $endsWithString) {
                        $endsWithPassed = true;
                        break;
                    }
                }
            }

            if ($startsWithPassed && $endsWithPassed) {
                $GLOBALS['wpacu_prevent_any_frontend_optimization'] = true;
                return true;
            }
        }

        $GLOBALS['wpacu_prevent_any_frontend_optimization'] = false;
        return false;
    }

    /**
     * Triggers only in the front-end view (e.g. Homepage URL, /contact/, /about/ etc.)
     * Except the situations below: no page builder in edit mode etc.
     *
     * @return bool
     */
    public static function triggerFrontendOptimization()
    {
        // Not when the CSS/JS is fetched
        if (WPACU_GET_LOADED_ASSETS_ACTION === true) {
            return false;
        }

        // "Elementor" Edit Mode
        if (isset($_GET['elementor-preview']) && $_GET['elementor-preview']) {
            return false;
        }

        // "Divi" Edit Mode
        if (isset($_GET['et_fb']) && $_GET['et_fb']) {
            return false;
        }

        // Not within the Dashboard
        if (is_admin()) {
            return false;
        }

        // Default (triggers in most cases)
        return true;
    }

    /**
     * @return bool
     */
    public static function isWorthCheckingForCssOptimization()
    {
        // At least one of these options has to be enabled
        // Otherwise, we will not perform specific useless actions and save resources
        if (isset($GLOBALS['wpacu_optimize_css_is_worth_checking_for_optimization'])) {
            return $GLOBALS['wpacu_optimize_css_is_worth_checking_for_optimization'];
        }

        $userCanManageAssets = Menu::userCanAccessAssetCleanUp();

        if (isset($_GET['wpacu_css_minify']) && $userCanManageAssets) {
            $isMinifyCssEnabled = true;
        } elseif ( isset($_REQUEST['wpacu_no_css_minify']) || // not on query string request (debugging purposes)
                is_admin() || // not for Dashboard view
                ( ! Main::instance()->settings['minify_loaded_css'] ) || // Minify CSS has to be Enabled
                ( Main::instance()->settings['test_mode'] && ! $userCanManageAssets ) ) { // Does not trigger if "Test Mode" is Enabled
            $isMinifyCssEnabled = false;
        } else {
            $isMinifyCssEnabled = MinifyCss::isMinifyCssEnabled(); // finally, call it
        }

        $GLOBALS['wpacu_optimize_css_is_worth_checking_for_optimization'] =
            Main::instance()->settings['cache_dynamic_loaded_css'] ||
            Main::instance()->settings['local_fonts_display'] ||
            Main::instance()->settings['google_fonts_display'] ||
            Main::instance()->settings['google_fonts_remove'] ||
            $isMinifyCssEnabled;

        return $GLOBALS['wpacu_optimize_css_is_worth_checking_for_optimization'];
    }

    /**
     * @return bool
     */
    public static function isWorthCheckingForJsOptimization()
    {
        // At least one of these options has to be enabled
        // Otherwise, we will not perform specific useless actions and save resources
        if (isset($GLOBALS['wpacu_optimize_js_is_worth_checking_for_optimization'])) {
            return $GLOBALS['wpacu_optimize_js_is_worth_checking_for_optimization'];
        }

        $userCanManageAssets = Menu::userCanAccessAssetCleanUp();

        if (isset($_GET['wpacu_js_minify']) && $userCanManageAssets) {
            $isMinifyJsEnabled = true;
        } elseif ( isset($_REQUEST['wpacu_no_js_minify']) || // not on query string request (debugging purposes)
                   is_admin() || // not for Dashboard view
                   ( ! Main::instance()->settings['minify_loaded_js'] ) || // Minify JS has to be Enabled
                   ( Main::instance()->settings['test_mode'] && ! $userCanManageAssets ) ) { // Does not trigger if "Test Mode" is Enabled
            $isMinifyJsEnabled = false;
        } else {
            $isMinifyJsEnabled = MinifyJs::isMinifyJsEnabled(); // finally, call it
        }

        $GLOBALS['wpacu_optimize_js_is_worth_checking_for_optimization'] =
            Main::instance()->settings['cache_dynamic_loaded_js'] ||
            Main::instance()->settings['google_fonts_display'] ||
            Main::instance()->settings['google_fonts_remove'] ||
            $isMinifyJsEnabled;

        return $GLOBALS['wpacu_optimize_js_is_worth_checking_for_optimization'];
    }

	/**
	 * @return void
	 */
	public function noticeCacheCleared()
	{
		?>
		<div class="updated notice is-dismissible">
			<p><span class="dashicons dashicons-yes"></span>
				<?php
				echo WPACU_PLUGIN_TITLE . ': '. __('The caching of the optimized assets has just been cleared', 'wp-asset-clean-up')
				     . ' @  '. date_i18n( get_option( 'date_format' ), strtotime( get_transient(WPACU_PLUGIN_ID . '_last_clear_cache') ) )
				        . ' '.date_i18n( get_option( 'time_format' ), strtotime( get_transient(WPACU_PLUGIN_ID . '_last_clear_cache') ) );
				?>
			</p>
		</div>
		<?php
	}

	/**
	 *
	 */
	public static function maybeAlterHtmlSourceViaWpLoaded()
	{
		if (is_admin()) {
			// Don't apply any changes if not in the front-end view (e.g. Dashboard view)
			return;
		}

		if (is_feed()) {
			// The plugin should be inactive for feed URLs
			return;
		}

		/*
		 * The admin is logged in and manages the assets in the front-end view
		 * */
		if (MainAdmin::useBufferingForEditFrontEndView()) {
			// Alter the HTML via "shutdown" action hook to catch hardcoded CSS/JS that is added via output buffering such as the ones in "Smart Slider 3"
			// via HardcodedAssets.php
			return;
		}

		/*
		 * The visitor is just a guest (most common), OR the admin is logged in, but "Manage in the front-end" is deactivated
		 * */
		ob_start(static function($htmlSource) {
			// Do not do any optimization if "Test Mode" is Enabled
			if ( ! Menu::userCanAccessAssetCleanUp() && Main::instance()->settings['test_mode'] ) {
				return $htmlSource;
			}

            return self::alterHtmlSource($htmlSource);
		});
	}

    /**
     *
     */
    public static function maybeStartBufferForAlterHtmlSource()
    {
        /*
         * The admin is logged in and manages the assets in the front-end view
         * */
        if (MainAdmin::useBufferingForEditFrontEndView()) {
            // Alter the HTML via "shutdown" action hook to catch hardcoded CSS/JS that is added via output buffering such as the ones in "Smart Slider 3"
            // via HardcodedAssets.php
            return;
        }

        /** @global bool $wpacuTriggerShutdownForHtmlAlter */
        global $wpacuTriggerShutdownForHtmlAlter;

        // Set the variable to true (or any value) when this hook fires.
        $wpacuTriggerShutdownForHtmlAlter = true;

        /*
         * The visitor is just a guest (most common), OR the admin is logged in, but "Manage in the front-end" is deactivated
         * */
        // Start buffering only if it hasn't already been started
        if (ob_get_level() === 0) {
            ob_start();
        }
    }

	/**
	 * @param $htmlSource
	 * @param $triggerOnlyOnce bool
	 *
	 * @return mixed|string|string[]|void|null
	 */
	public static function alterHtmlSource($htmlSource, $triggerOnlyOnce = false)
	{
        // e.g. if it was called from "autoptimize_filter_html_before_minify", then there's no point in triggering it again from a different hook
		if (defined('WPACU_ALTER_HTML_SOURCE_DONE')) {
			return $htmlSource;
		}

		if ($triggerOnlyOnce && ! defined('WPACU_ALTER_HTML_SOURCE_DONE')) {
			define('WPACU_ALTER_HTML_SOURCE_DONE', 1);
		}

		if (is_feed()) {
			// The plugin should not do any alterations for the feed content
			return $htmlSource;
		}

		// Dashboard View
		// Return the HTML as it is without performing any optimisations to save resources
		// Since the page has to be as clean as possible when fetching the assets
		if (Main::instance()->isGetAssetsCall) {
			return $htmlSource;
		}

		/* [wpacu_timing] */ Misc::scriptExecTimer( 'alter_html_source' ); /* [/wpacu_timing] */

		// Front-end View
		// The printing of the hardcoded assets is made via "wpacu_final_frontend_output" filter hook
		// located within "shutdown" action hook only if the user is logged-in and has the right permissions

		// This is useful to avoid changing the DOM via no matter which of the following actions are used: "wp_loaded", "template_redirect", "shutdown"
		//  to check how fast the page loads without the DOM changes (for debugging purposes)
		$wpacuNoHtmlChanges = isset($_REQUEST['wpacu_no_html_changes']) || wpacuIsDefinedConstant('WPACU_NO_HTML_CHANGES');

		// Not a normal WordPress page load
		// e.g. it could be JS content loaded dynamically such as /?wpml-app=ate-widget
		if ( self::preventAnyFrontendOptimization('', $htmlSource) && ! (did_action('wp_head') && did_action('wp_footer')) ) {
			/* [wpacu_timing] */ Misc::scriptExecTimer( 'alter_html_source', 'end' ); /* [/wpacu_timing] */
			return $htmlSource;
		}

		if ( $wpacuNoHtmlChanges || self::preventAnyFrontendOptimization() ) {
			/* [wpacu_timing] */ Misc::scriptExecTimer( 'alter_html_source', 'end' ); /* [/wpacu_timing] */
			return $htmlSource;
		}

		$htmlSource = apply_filters( 'wpacu_html_source_before_optimization', $htmlSource );

        /* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_strip_any_references_for_unloaded_assets'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
        $htmlSource = self::stripAnyReferencesForUnloadedAssets($htmlSource);
        /* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		$htmlSource = OptimizeCss::alterHtmlSource( $htmlSource );
		$htmlSource = OptimizeJs::alterHtmlSource( $htmlSource );

		/* [wpacu_timing] */ Misc::scriptExecTimer( 'alter_html_source_cleanup' ); /* [/wpacu_timing] */

		/* [wpacu_timing] */ Misc::scriptExecTimer('alter_html_source_for_remove_html_comments'); /* [/wpacu_timing] */
		$htmlSource = Main::instance()->settings['remove_html_comments'] ? CleanUp::removeHtmlComments( $htmlSource ) : $htmlSource;
		/* [wpacu_timing] */ Misc::scriptExecTimer('alter_html_source_for_remove_html_comments', 'end'); /* [/wpacu_timing] */

		/* [wpacu_timing] */ Misc::scriptExecTimer('alter_html_source_for_remove_meta_generators'); /* [/wpacu_timing] */
		$htmlSource = Main::instance()->settings['remove_generator_tag'] ? CleanUp::removeMetaGenerators( $htmlSource ) : $htmlSource;
		/* [wpacu_timing] */ Misc::scriptExecTimer('alter_html_source_for_remove_meta_generators', 'end'); /* [/wpacu_timing] */

        if (strpos($htmlSource, 'data-wpacu-style-handle') !== false) {
            $htmlSource = preg_replace('#<link(.*)data-wpacu-style-handle=\'(.*)\'#Umi', '<link \\1', $htmlSource);
        }

		$htmlSource = preg_replace('#<link(\s+)rel=\'stylesheet\' id=\'#Umi', '<link rel=\'stylesheet\' id=\'', $htmlSource);

		/* [wpacu_timing] */ Misc::scriptExecTimer( 'alter_html_source_cleanup', 'end' ); /* [/wpacu_timing] */

		if ( in_array( Main::instance()->settings['disable_xmlrpc'], array( 'disable_all', 'disable_pingback' ) ) ) {
			// Also clean it up from the <head> in case it's hardcoded
			$htmlSource = CleanUp::cleanPingbackLinkRel( $htmlSource );
		}

		// A script like this one shouldn't be in an AMP page
		if (defined('WPACU_DO_EXTRA_CHECKS_FOR_AMP') && strpos($htmlSource, '<style amp-boilerplate>') !== false && (strpos($htmlSource, '<style amp-custom>') !== false || strpos($htmlSource, '<html amp ') !== false)) {
			$htmlSource = str_replace(Misc::preloadAsyncCssFallbackOutput(true), '', $htmlSource);
		}

		$htmlSource = apply_filters( 'wpacu_html_source', $htmlSource ); // legacy

		/* [wpacu_timing] */ Misc::scriptExecTimer( 'alter_html_source', 'end' ); /* [/wpacu_timing] */

		// [wpacu_debug]
		if ( isset($_GET['wpacu_debug']) ) {
			$htmlSource = Debug::applyDebugTiming($htmlSource);
		}
		// [wpacu_debug]

		return apply_filters( 'wpacu_html_source_after_optimization', $htmlSource );
	}

	/**
	 * @param $htmlSource
	 * @param string $for
	 *
	 * @return \DOMDocument
	 */
	public static function getDomLoadedTag($htmlSource, $for = '')
	{
		$htmlSourceBefore = $htmlSource;

		$domTag = Misc::initDOMDocument();

		$cleanerDomRegEx = '';

		// [HTML CleanUp]
		if ($for === 'removeHtmlComments') {
			// They could contain anything
			$cleanerDomRegEx = '';
		}

		if ($for === 'removeMetaGenerators') {
			$cleanerDomRegEx = array('@<(noscript|style|script)[^>]*?>.*?</\\1>@si', '#<(link|img)([^<>]+)/?>#iU');
		}
		// [/HTML CleanUp]

		// [CSS Optimisation]
		if ($for === 'combineCss') {
			$cleanerDomRegEx = array('@<(noscript|style|script)[^>]*?>.*?</\\1>@si', '#<(meta|img)([^<>]+)/?>#iU');
		}

		if ($for === 'minifyInlineStyleTags') {
			$cleanerDomRegEx = array('@<(noscript|script)[^>]*?>.*?</\\1>@si', '#<(meta|link|img)([^<>]+)/?>#iU');
		}
		// [/CSS Optimisation]

		// [JS Optimisation]
		if ($for === 'moveInlinejQueryAfterjQuerySrc') {
			$cleanerDomRegEx = '@<(noscript|style)[^>]*?>.*?</\\1>@si';
		}

		if ($for === 'minifyOrAlterInlineScriptTags') {
			$cleanerDomRegEx = array('@<(noscript|style)[^>]*?>.*?</\\1>@si', '#<(meta|link|img)([^<>]+)/?>#iU');
		}

		if ($for === 'combineJs') {
			$cleanerDomRegEx = '@<(noscript|style)[^>]*?>.*?</\\1>@si';
		}
		// [/JS Optimisation]

		if ($cleanerDomRegEx !== '') {
			$htmlSource = preg_replace( $cleanerDomRegEx, '', $htmlSource );
		}

        if ($for === 'removeMetaGenerators') {
            preg_match_all('#<head[^>]*>(.*?)</head>(.*?)<body#Umsi', $htmlSource, $matchesContentsInHeadTag);
            preg_match_all('#<body[^>]*>(.*?)</body#Umsi', $htmlSource, $matchesContentsInBodyTag);

            $headTagContent = isset($matchesContentsInHeadTag[1][0]) ? $matchesContentsInHeadTag[1][0] : '';
            $bodyTagContent = isset($matchesContentsInBodyTag[1][0]) ? $matchesContentsInBodyTag[1][0] : '';

            $htmlSourceToCheck = '';

            if (preg_match('#name(\s+|)=(\s+|)("|\'|)generator("|\'|)#Umi', $headTagContent)) {
                $htmlSourceToCheck .= $headTagContent;
            }

            if (preg_match('#name(\s+|)=(\s+|)("|\'|)generator("|\'|)#Umi', $bodyTagContent)) {
                $htmlSourceToCheck .= $bodyTagContent;
            }

            if ($htmlSourceToCheck) {
                $htmlSource = $htmlSourceToCheck;
            }
        }

		if (Main::instance()->isFrontendEditView) {
            $htmlSource = self::clearFrontendFormFromHtmlSourceForProcessing($htmlSource);
        }

		// Avoid "Warning: DOMDocument::loadHTML(): Empty string supplied as input"
		// Just in case $htmlSource has been altered incorrectly for any reason, fallback to the original $htmlSource value ($htmlSourceBefore)
		if ( ! $htmlSource ) {
			$domTag->loadHTML($htmlSourceBefore);
			return $domTag;
		}

		$domTag->loadHTML($htmlSource);
		return $domTag;
	}

    /**
     * e.g. if an asset is unloaded, strip any LINK/SCRIPT tag that preloads that asset (e.g. added by other plugins)
     *
     * @param $htmlSource
     *
     * @return array|mixed|string|string[]
     * @noinspection NestedAssignmentsUsageInspection
     */
    public static function stripAnyReferencesForUnloadedAssets($htmlSource)
    {
        // Gather all HREFs of the unloaded styles (if any) that have "preload"
        $unloadedStyleRelHrefs = $unloadedScriptRelSrcs = $unloadAssetsRelSources = array();

        if ( ! empty( Main::instance()->allUnloadedAssets['styles'] ) ) {
            foreach ( array_unique( Main::instance()->allUnloadedAssets['styles'] ) as $styleHandle ) {
                if ( ! (isset(Main::instance()->wpAllStyles['registered'][ $styleHandle ]->src) && Main::instance()->wpAllStyles['registered'][ $styleHandle ]->src) ) {
                    continue; // does not have a "src" (e.g. inline CSS)
                }

                $sourceRelPath = self::getSourceRelPath( Main::instance()->wpAllStyles['registered'][ $styleHandle ]->src );

                if ( strpos($htmlSource, $sourceRelPath) !== false ) {
                    $unloadedStyleRelHrefs[] = $sourceRelPath;
                    $unloadAssetsRelSources[] = $sourceRelPath;
                }
            }
        }

        if ( ! empty( Main::instance()->allUnloadedAssets['scripts'] ) ) {
            foreach ( array_unique( Main::instance()->allUnloadedAssets['scripts'] ) as $scriptHandle ) {
                if ( ! (isset(Main::instance()->wpAllScripts['registered'][ $scriptHandle ]->src) && Main::instance()->wpAllScripts['registered'][ $scriptHandle ]->src) ) {
                    continue; // does not have a "src" (e.g. inline JS)
                }

                $sourceRelPath = self::getSourceRelPath( Main::instance()->wpAllScripts['registered'][ $scriptHandle ]->src );

                if ( strpos($htmlSource, $sourceRelPath) !== false ) {
                    $unloadedScriptRelSrcs[] = self::getSourceRelPath( Main::instance()->wpAllScripts['registered'][ $scriptHandle ]->src );
                    $unloadAssetsRelSources[] = $sourceRelPath;
                }
            }
        }

        if ( ! empty($unloadAssetsRelSources) ) {
            if ( ! empty($unloadedStyleRelHrefs) && ! empty($unloadedScriptRelSrcs) ) {
                $checkTagsFor = 'styles_scripts';
            } elseif ( ! empty($unloadedStyleRelHrefs) ) {
                $checkTagsFor = 'styles';
            } else {
                $checkTagsFor = 'scripts';
            }

            return self::stripTagsWithAsAttributeRelatedToUnloadAssets($htmlSource, $checkTagsFor);
        }

        return $htmlSource;
    }

    /**
     * @param $htmlSource
     * @param string $checkTagsFor ("styles_scripts", "styles",  "scripts")
     *
     * @return string
     */
    public static function stripTagsWithAsAttributeRelatedToUnloadAssets($htmlSource, $checkTagsFor = 'styles_scripts')
    {
        $matchesSourcesFromTags = array();

        $regExPartAsToGet = 'style|script';
        $domTagSelectorExp = '//link[@as="style"]|//link[@as="script"]';

        if ($checkTagsFor === 'styles') {
            $regExPartAsToGet = 'style';
            $domTagSelectorExp = '//link[@as="style"]';
        } elseif ($checkTagsFor === 'scripts') {
            $regExPartAsToGet = 'script';
            $domTagSelectorExp = '//link[@as="script"]';
        }

	    /*
		  * Option 1: DOM + Regular Expression (Best)
		 */
	    if ( Misc::isDOMDocumentOn() ) {
            $dom = Misc::initDOMDocument();

            preg_match_all('#<head[^>]*>(.*?)</head>(.*?)<body#Umsi', $htmlSource, $matchesContentsInHeadTag);
            preg_match_all('#<body[^>]*>(.*?)</body#Umsi', $htmlSource, $matchesContentsInBodyTag);

            $headTagContent = isset($matchesContentsInHeadTag[1][0]) ? $matchesContentsInHeadTag[1][0] : '';
            $bodyTagContent = isset($matchesContentsInBodyTag[1][0]) ? $matchesContentsInBodyTag[1][0] : '';

            $htmlSourceToCheck = '';

            if ( preg_match('#<link[^>]*(as(\s+|)=(\s+|)(|"|\')'.$regExPartAsToGet.'(|"|\'))[^>]*>#Umi', $headTagContent) ) {
                $htmlSourceToCheck .= $headTagContent;
            }

            if ( preg_match('#<link[^>]*(as(\s+|)=(\s+|)(|"|\')'.$regExPartAsToGet.'(|"|\'))[^>]*>#Umi', $bodyTagContent) ) {
                $htmlSourceToCheck .= $bodyTagContent;
            }

            if ($htmlSourceToCheck === '') {
                $htmlSourceToCheck = $htmlSource;
            }

            $dom->loadHTML( self::cleanerHtmlSource( $htmlSourceToCheck, array( 'for_fetching_link_as_tags' ) ) );
            $selector = new \DOMXPath( $dom );

            $domTagQuery = $selector->query( $domTagSelectorExp );

		    if (count($domTagQuery) < 1) {
			    // No LINK tags found with the specified "as" attribute? Stop here!
			    return $htmlSource;
		    }

            foreach ($domTagQuery as $link) {
			    $linkTagParts   = array('<link ');

			    foreach ($link->attributes as $attr) {
				    $attrName  = $attr->nodeName;
				    $attrValue = $attr->nodeValue;

				    if ($attrName) {
					    if ($attrValue !== '') {
						    $linkTagParts[] = '(\s+|)' . preg_quote($attrName, '/') . '(\s+|)=(\s+|)(|"|\')' . preg_quote($attrValue, '/') . '(|"|\')(|\s+)';
					    } else {
						    $linkTagParts[] = '(\s+|)' . preg_quote($attrName, '/') . '(|((\s+|)=(\s+|)(|"|\')(|"|\')))';
					    }
				    }
			    }

			    $linkTagParts[] = '(|\s+)(|/)>';

			    $linkTagFinalRegExPart = implode('', $linkTagParts);

                preg_match_all(
				    '#'.$linkTagFinalRegExPart.'#Umi',
				    $htmlSource,
				    $matchSourceFromTag,
				    PREG_SET_ORDER
			    );

                // It always has to be a match from the DOM generated tag
			    // Otherwise, default it to RegEx
			    if ( empty($matchSourceFromTag[0]) ) {
				    $fallbackToRegex = true;
				    break;
			    }

                $matchesSourcesFromTags[] = $matchSourceFromTag[0];
		    }
	    }

        /*
		  * Option 2: Regular Expression (Fallback)
		 */
	    if ( (isset($fallbackToRegex) && $fallbackToRegex) || ! Misc::isDOMDocumentOn() ) {
		    preg_match_all( '#<link[^>]*(as(\s+|)=(\s+|)(|"|\')'.$regExPartAsToGet.'(|"|\'))[^>]*>#Umi', self::cleanerHtmlSource( $htmlSource, array( 'for_fetching_link_as_tags' ) ), $matchesSourcesFromTags, PREG_SET_ORDER );
	    }

        if ( empty($matchesSourcesFromTags) ) {
            return $htmlSource;
        }

        $matchedLinkTags = array();

        foreach ( $matchesSourcesFromTags as $matchedTagArr ) {
            $matchedLinkTag = isset( $matchedTagArr[0] ) ? $matchedTagArr[0] : '';
            $matchedLinkTags[$matchedLinkTag] = '';
        }

        return strtr($htmlSource, $matchedLinkTags);
    }

	/**
	 * @return string
	 */
	public static function getRelPathPluginCacheDir()
	{
		// In some cases, hosting companies put restriction for writable folders
		// Pantheon, for instance, allows only /wp-content/uploads/ to be writable
		// For security reasons, do not allow ../
		return defined('WPACU_CACHE_DIR') && strpos(WPACU_CACHE_DIR, '../') === false
			? WPACU_CACHE_DIR
			: self::$relPathPluginCacheDirDefault;
	}

    /**
     * @param $htmlSource
     *
     * @return array|string|string[]
     */
    public static function clearFrontendFormFromHtmlSourceForProcessing($htmlSource)
    {
        $wpacuFrontendFormOutput = Misc::extractBetween(
                $htmlSource,
                '<span data-wpacu-delimiter-frontend-form-start="1" style="display:none;"></span>',
                '<span data-wpacu-delimiter-frontend-form-end="1" style="display:none;"></span>'
        );

        return str_replace( $wpacuFrontendFormOutput, '', $htmlSource );
    }

	/**
	 * The following output is ONLY used for fetching purposes
	 * It will not be part of the final output
	 *
	 * @param $htmlSourceToFetchFrom
	 * @param $params
	 *
	 * @return string|string[]|null
	 */
	public static function cleanerHtmlSource($htmlSourceToFetchFrom, $params = array('strip_content_between_conditional_comments'))
	{
        if ( ! $htmlSourceToFetchFrom ) {
            return $htmlSourceToFetchFrom;
        }

        $htmlSourceToFetchFromUnchanged = $htmlSourceToFetchFrom;

        if (Main::instance()->isFrontendEditView) {
            $htmlSourceToFetchFrom = self::clearFrontendFormFromHtmlSourceForProcessing($htmlSourceToFetchFrom);
        }

        if (in_array('for_fetching_link_tags', $params)) {
			$htmlSourceToFetchFrom = preg_replace( array(
				'@<(style|script|noscript|a)[^>]*?>.*?</\\1>@si',
				'#<(meta|img)([^<>]+)/?>#iU'
			), '', $htmlSourceToFetchFrom );
		} elseif (in_array('for_fetching_link_as_tags', $params)) {
            $htmlSourceToFetchFrom = preg_replace( array(
                '@<(style|script|noscript|a)[^>]*?>.*?</\\1>@si',
                '#<(meta|img)([^<>]+)/?>#iU',
                '#<link[^>]*(rel(\s+|)=(\s+|)(|"|\')stylesheet|profile|alternate(|"|\'))[^>]*(>)#Umi'
            ), '', $htmlSourceToFetchFrom );
        } else {
			// Strip NOSCRIPT tags
			$htmlSourceToFetchFrom = preg_replace( '@<(noscript)[^>]*?>.*?</\\1>@si', '', $htmlSourceToFetchFrom );
		}

		// Case: Return the HTML source without any conditional comments and the content within them
		if ( in_array('strip_content_between_conditional_comments', $params) && ! empty($htmlSourceToFetchFrom) ) {
			preg_match_all('#<!--\[if(.*?)]>(<!-->|-->|\s|)(.*?)(<!--<!|<!)\[endif]-->#si', $htmlSourceToFetchFrom, $matchedContent);

			if ( ! empty($matchedContent[0]) ) {
				foreach ($matchedContent[0] as $conditionalHtmlContent) {
					$htmlSourceToFetchFrom = str_replace($conditionalHtmlContent, '', $htmlSourceToFetchFrom);
				}
			}
		}

        if (empty($htmlSourceToFetchFrom)) {
            return $htmlSourceToFetchFromUnchanged;
        }

		return trim($htmlSourceToFetchFrom);
	}

	/**
	 * Is this a regular WordPress page (not feed, REST API etc.)?
	 * If not, do not proceed with any CSS/JS combine
	 *
	 * @return bool
	 */
	public static function doCombineIsRegularPage()
	{
		// In particular situations, do not process this
		if (strpos($_SERVER['REQUEST_URI'], '/wp-content/themes/') !== false && strpos($_SERVER['REQUEST_URI'], '/'.Misc::getPluginsDir().'/') !== false) {
			return false;
		}

		if (Misc::endsWith($_SERVER['REQUEST_URI'], '/comments/feed/')) {
			return false;
		}

		if (str_replace('//', '/', site_url() . '/feed/') === $_SERVER['REQUEST_URI']) {
			return false;
		}

		if (is_feed()) { // any kind of feed page
			return false;
		}

		return true;
	}

	/**
	 * @param $isFile
	 * @param $localAssetPath
	 * @param $assetHandle
	 * @param $fileVer
	 *
	 * @return string
	 */
	public static function generateUniqueNameForCachedAsset($isFile, $localAssetPath, $assetHandle, $fileVer)
	{
		if ($isFile) {
			$relPathToFileFiltered = str_replace(Misc::getWpRootDirPathBasedOnPath($localAssetPath), '', $localAssetPath);

			// Some people might use plugins to hide the fact that they are using WordPress
			// Strip such information from the cached asset names as it's irrelevant for the visitor anyway
			// if the cached file name loaded in the browser contain references to WordPress
			foreach (array('wp-content/plugins', 'wp-content/themes', 'wp-', 'wordpress') as $toStripFromFileName) {
				$relPathToFileFiltered = str_replace( $toStripFromFileName, '', $relPathToFileFiltered );
			}

			$relPathToFileFiltered = ltrim($relPathToFileFiltered, '/');

			$sanitizedRelPathToFileFiltered = str_replace('/', '__', $relPathToFileFiltered);
			$sanitizedRelPathToFileFiltered = sanitize_title($sanitizedRelPathToFileFiltered);
			$uniqueOptimizedAssetName = $sanitizedRelPathToFileFiltered;
		} else {
			$uniqueOptimizedAssetName = sanitize_title( $assetHandle );
		}

		$uniqueOptimizedAssetName .= '-v' . $fileVer;

		return $uniqueOptimizedAssetName;
	}

	/**
	 * @param $href
	 * @param $assetType
	 *
	 * @return bool|string
	 */
	public static function getLocalAssetPath($href, $assetType)
	{
		// Check if it starts without "/" or a protocol; e.g. "wp-content/theme/style.css", "wp-content/theme/script.js"
		if (strncmp($href, '/', 1) !== 0 &&
            strncmp($href, '//', 2) !== 0 &&
            strncasecmp($href, 'http://', 7) !== 0 &&
            strncasecmp($href, 'https://', 8) !== 0
		) {
			$href = '/'.$href; // append the forward slash to be processed as relative later on
		}

		// starting with "/", but not with "//"
		$isRelHref = (strncmp($href, '/', 1) === 0 && strncmp($href, '//', 2) !== 0);

		if (! $isRelHref) {
			$href = self::isSourceFromSameHost($href);

			if (! $href) {
				return false;
			}
		}

		$hrefRelPath = self::getSourceRelPath($href);

		if (strncmp($hrefRelPath, '/', 1) === 0) {
			$hrefRelPath = substr($hrefRelPath, 1);
		}

        $localPathToUseForRoot = Misc::getWpRootDirPathBasedOnPath($hrefRelPath);

		$localAssetPossiblePaths = array($localPathToUseForRoot . $hrefRelPath);

		// Perhaps the URL starts with / (not //) and site_url() was not used
		$parseSiteUrlPath = (string)parse_url(site_url(), PHP_URL_PATH);

		// This is in case we have something like this in the source (hardcoded or generated through a plugin)
		// /blog/wp-content/plugins/custom-plugin-slug/script.js
		// and the site_url() is equal with https://www.mysite.com/blog
		if ($parseSiteUrlPath !== '/' && strlen($parseSiteUrlPath) > 1 && strpos($href, $parseSiteUrlPath) === 0) {
			$relPathFromWpRootDir = str_replace($parseSiteUrlPath, '', $href);
			$altHrefRelPath = str_replace('//', '/', $localPathToUseForRoot . $relPathFromWpRootDir);
			$localAssetPossiblePaths[] = $altHrefRelPath;
		}

		foreach ($localAssetPossiblePaths as $localAssetPath) {
			if ( strpos( $localAssetPath, '?ver' ) !== false ) {
				list( $localAssetPathAlt, ) = explode( '?ver', $localAssetPath );
				$localAssetPath = $localAssetPathAlt;
			}

			// Not using "?ver="
			if ( strpos( $localAssetPath, '.' . $assetType . '?' ) !== false ) {
				list( $localAssetPathAlt, ) = explode( '.' . $assetType . '?', $localAssetPath );
				$localAssetPath = $localAssetPathAlt . '.' . $assetType;
			}

			if ( strrchr( $localAssetPath, '.' ) === '.' . $assetType && is_file( $localAssetPath ) ) {
				return $localAssetPath;
			}
		}

		return false;
	}

	/**
	 * @param $assetHref
	 *
	 * @return array|false|string|string[]
	 */
	public static function getPathToAssetDir($assetHref)
	{
		$posLastSlash   = strrpos($assetHref, '/');
		$pathToAssetDir = substr($assetHref, 0, $posLastSlash);

		$parseUrl = parse_url($pathToAssetDir);

		if (isset($parseUrl['scheme']) && $parseUrl['scheme'] !== '') {
			$pathToAssetDir = str_replace(
				array('http://'.$parseUrl['host'], 'https://'.$parseUrl['host']),
				'',
				$pathToAssetDir
			);
		} elseif (strncmp($pathToAssetDir, '//', 2) === 0) {
			$pathToAssetDir = str_replace(
				array('//'.$parseUrl['host'], '//'.$parseUrl['host']),
				'',
				$pathToAssetDir
			);
		}

		return $pathToAssetDir;
	}

	/**
	 * @param $sourceTag
	 *
	 * @return array|bool
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function getLocalCleanSourceFromTag($sourceTag)
	{
		$sourceFromTag = Misc::getValueFromTag($sourceTag);

		if (! $sourceFromTag) {
			return false;
		}

		// Check if it starts without "/" or a protocol; e.g. "wp-content/theme/style.css", "wp-content/theme/script.js"
		if (strncmp($sourceFromTag, '/', 1) !== 0 &&
            strncmp($sourceFromTag, '//', 2) !== 0 &&
            strncasecmp($sourceFromTag, 'http://', 7) !== 0 &&
            strncasecmp($sourceFromTag, 'https://', 8) !== 0
		) {
			$sourceFromTag = '/'.$sourceFromTag; // append the forward slash to be processed as relative later on
		}

		// Perhaps the URL starts with / (not //) and site_url() was not used
		$altFilePathForRelSource = $isRelPath = false;
		$parseSiteUrlPath = (string)parse_url(site_url(), PHP_URL_PATH);

		// This is in case we have something like this in the HTML source (hardcoded or generated through a plugin)
		// <link href="/blog/wp-content/plugins/custom-plugin-slug/script.js" rel="preload" as="script" type="text/javascript">
		// and the site_url() is equal with https://www.mysite.com/blog
		if ($parseSiteUrlPath !== '/' && strlen($parseSiteUrlPath) > 1 && strpos($sourceFromTag, $parseSiteUrlPath) !== false) {
			$relPathFromRootDir = str_replace($parseSiteUrlPath, '', $sourceFromTag);
			$altFilePathForRelSource = str_replace('//', '/', Misc::getWpRootDirPathBasedOnPath($relPathFromRootDir) . $relPathFromRootDir);
		} elseif (strncmp($sourceFromTag, '/', 1) === 0 && strncmp($sourceFromTag, '//', 2) !== 0) {
			$altFilePathForRelSource = str_replace('//', '/', Misc::getWpRootDirPathBasedOnPath($sourceFromTag) . $sourceFromTag);
		}

		if ($altFilePathForRelSource && (strpos($altFilePathForRelSource, '.css?') !== false || strpos($altFilePathForRelSource, '.js?') !== false)) {
			list($altFilePathForRelSource) = explode('?', $altFilePathForRelSource);
		}

		if ( $altFilePathForRelSource && (is_file(Misc::getWpRootDirPathBasedOnPath($sourceFromTag) . $sourceFromTag) || is_file($altFilePathForRelSource)) ) {
			$isRelPath = true;
		}

		// In case the match was something like "src='//mydomain.com/file.js'"
		// Leave nothing to chance as often the prefix is stripped
		$cleanSiteUrl = str_replace(array('http://', 'https://'), '//', site_url());

		if ($isRelPath || (stripos($sourceFromTag, $cleanSiteUrl) !== false) || (stripos($sourceFromTag, site_url()) !== false)) {
			$cleanSourceUrlFromTag = trim($sourceFromTag, '?&');
			$afterQuestionMark = WPACU_PLUGIN_VERSION;

			// Is it a dynamic URL? Keep the full path
			if (strpos($cleanSourceUrlFromTag, '.php') !== false ||
			    strpos($cleanSourceUrlFromTag, '/?') !== false ||
			    strpos($cleanSourceUrlFromTag, rtrim(site_url(), '/').'?') !== false) {
				list(,$afterQuestionMark) = explode('?', $sourceFromTag);
			} elseif (strpos($sourceFromTag, '?') !== false) {
				list($cleanSourceUrlFromTag, $afterQuestionMark) = explode('?', $sourceFromTag);
			}

			if (! $afterQuestionMark) {
				return false;
			}

			return array('source' => $cleanSourceUrlFromTag, 'after_question_mark' => $afterQuestionMark);
		}

		return false;
	}

	/**
	 * @param $href
	 *
	 * @return bool
	 */
	public static function isSourceFromSameHost($href)
	{
		// Check the host name
		$siteDbUrl   = get_option('siteurl');
		$siteUrlHost = strtolower(parse_url($siteDbUrl, PHP_URL_HOST));

		$cdnUrls = self::getAnyCdnUrls();

		// Are there any CDN urls set? Check them out
		if (! empty($cdnUrls)) {
			$hrefAlt = $href;

			foreach ($cdnUrls as $cdnUrl) {
				$hrefCleanedArray = self::getCleanHrefAfterCdnStrip(trim($cdnUrl), $hrefAlt);
				$cdnNoPrefix = $hrefCleanedArray['cdn_no_prefix'];
				$hrefAlt = $hrefCleanedArray['rel_href'];

				if ($hrefAlt !== $href && stripos($href, '//'.$cdnNoPrefix) !== false) {
					return $href;
				}
			}
		}

		if (strncmp($href, '//', 2) === 0) {
			list ($urlPrefix) = explode('//', $siteDbUrl);
			$href = $urlPrefix . $href;
		}

		/*
		 * Validate it first
		 */
		$assetHost = strtolower(parse_url($href, PHP_URL_HOST));

		if (preg_match('#'.$assetHost.'#si', implode('', self::$wellKnownExternalHosts))) {
			return false;
		}

		// Different host name (most likely 3rd party one such as fonts.googleapis.com or an external CDN)
		// Do not add it to the combine list
		if ($assetHost !== $siteUrlHost) {
			return false;
		}

		return $href;
	}

	/**
	 * @param $href
     * @param bool $stripVerIfLocal
	 *
	 * @return mixed
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function getSourceRelPath($href, $stripVerIfLocal = false)
	{
		// Already starts with / but not with //
		// Path is relative, just return it
		if (strncmp($href, '/', 1) === 0 && strncmp($href, '//', 2) !== 0) {
			return $href;
		}

		// Starts with // (protocol is missing)
		// Add a dummy one to validate the whole URL and get the host
		if (strncmp($href, '//', 2) === 0) {
			$href = (Misc::isHttpsSecure() ? 'https:' : 'http:') . $href;
		}

		$parseUrl = parse_url($href);
		$hrefHost = isset($parseUrl['host']) ? $parseUrl['host'] : false;

		if (! $hrefHost) {
			return $href;
		}

		// Sometimes host is different on Staging websites such as the ones from Siteground
		// e.g. staging1.domain.com and domain.com
		// We need to make sure that the URI path is fetched correctly based on the host value from the $href
		$siteDbUrl     = get_option('siteurl');

        $dbSiteUrlHost = parse_url($siteDbUrl, PHP_URL_HOST);
		$finalBaseUrl  = str_replace($dbSiteUrlHost, $hrefHost, $siteDbUrl);

		$hrefAlt = $finalRelPath = $href;

		$cdnUrls = self::getAnyCdnUrls();

		// Are there any CDN urls set? Filter them out in order to retrieve the relative path
		if (! empty($cdnUrls)) {
			foreach ($cdnUrls as $cdnUrl) {
				$hrefCleanArray = self::getCleanHrefAfterCdnStrip(trim($cdnUrl), $hrefAlt);
				$cdnNoPrefix = $hrefCleanArray['cdn_no_prefix'];

				$finalRelPath = str_replace(
					array('http://'.$cdnNoPrefix, 'https://'.$cdnNoPrefix, '//'.$cdnNoPrefix),
					'',
					$finalRelPath
				);
			}
		}

		if (strncmp($finalRelPath, 'http', 4) === 0) {
			list(,$noProtocol) = explode('://', $finalBaseUrl);
			$finalBaseUrls = array(
				'http://'.$noProtocol,
				'https://'.$noProtocol
			);
		} else {
			$finalBaseUrls = array($finalBaseUrl);
		}

		$finalRelPath = str_replace($finalBaseUrls, '', $finalRelPath);

		if (defined('WP_ROCKET_CACHE_BUSTING_URL') && function_exists('get_current_blog_id') && get_current_blog_id()) {
			$finalRelPath = str_replace(
				array(WP_ROCKET_CACHE_BUSTING_URL . get_current_blog_id(), WP_ROCKET_CACHE_BUSTING_URL),
				'',
				$finalRelPath
			);
		}

        if ($stripVerIfLocal) {
            $possiblePathToLocalFile = str_replace('//', '/', Misc::getWpRootDirPathBasedOnPath($finalRelPath) . $finalRelPath);

            if (strpos($possiblePathToLocalFile, '?') !== false) {
                list ($pathToLocalFile) = explode('?', $possiblePathToLocalFile);

                if (is_file($pathToLocalFile)) {
                    // Strip anything after '?'
                    // e.g. 'file.min.css?ver=10' -- 'file.min.css'
                    // e.g. 'other-file.min.js?version=11' -- 'other-file.min.js'
                    list ($finalRelPath) = explode('?', $finalRelPath);
                }
            }
        }

		return $finalRelPath;
	}

	/**
	 * @param $cdnUrl
	 * @param $hrefAlt
	 *
	 * @return array
	 */
	public static function getCleanHrefAfterCdnStrip($cdnUrl, $hrefAlt)
	{
		if (strpos($cdnUrl, '//') !== false) {
			$parseUrl = parse_url($cdnUrl);
			$cdnNoPrefix = $parseUrl['host'];

			if (isset($parseUrl['path']) && $parseUrl['path'] !== '') {
				$cdnNoPrefix .= $parseUrl['path'];
			}
		} else {
			$cdnNoPrefix = $cdnUrl; // CNAME
		}

		$hrefAlt = str_ireplace(array('http://' . $cdnNoPrefix, 'https://' . $cdnNoPrefix, '//'.$cdnNoPrefix), '', $hrefAlt);

		return array('cdn_no_prefix' => $cdnNoPrefix, 'rel_href' => $hrefAlt);
	}

	/**
	 * @param $jsonStorageFile
	 * @param $relPathAssetCacheDir
	 * @param $assetType
	 * @param $forType
	 *
	 * @return array|mixed|object
	 */
	public static function getAssetCachedData($jsonStorageFile, $relPathAssetCacheDir, $assetType, $forType = 'combine')
	{
		if ($forType === 'combine') {
			// Only clean request URIs allowed
			if (strpos($_SERVER['REQUEST_URI'], '?') !== false) {
				list($requestUri) = explode('?', $_SERVER['REQUEST_URI']);
			} else {
				$requestUri = $_SERVER['REQUEST_URI'];
			}

			$requestUriPart = $requestUri;

			// Same results for Homepage (any pagination), 404 Not Found & Date archive pages
			// The JSON files will get stored in the root directory of the targeted website
			if ($requestUri === '/' || is_404() || is_date() || MainFront::isHomePage()) {
				$requestUriPart = '';
			}

			// Treat the pagination pages the same as the main page (same it's done for the unloading rules)
			if (($currentPageNo = get_query_var('paged')) && (is_archive() || MainFront::isSingularPage())) {
				$paginationBase = isset($GLOBALS['wp_rewrite']->pagination_base) ? $GLOBALS['wp_rewrite']->pagination_base : 'page';
				$requestUriPart = str_replace('/'.$paginationBase.'/'.$currentPageNo.'/', '', $requestUriPart);
			}

			$dirToFilename = WP_CONTENT_DIR . dirname($relPathAssetCacheDir) . '/_storage/'
			                 . parse_url(site_url(), PHP_URL_HOST) .
			                 $requestUriPart . '/';

			$dirToFilename = str_replace('//', '/', $dirToFilename);

			$assetsFile = $dirToFilename . self::filterStorageFileName($jsonStorageFile);
		} elseif ($forType === 'item') {
			$dirToFilename = WP_CONTENT_DIR . dirname($relPathAssetCacheDir) . '/_storage/'.self::$optimizedSingleFilesDir.'/';
			$assetsFile = $dirToFilename . $jsonStorageFile;
		}

		if ( ! isset($assetsFile) || ! is_file($assetsFile) ) {
			return array();
		}

		if ($assetType === 'css' || $assetType === 'js') {
			$cachedAssetsFileExpiresIn = self::$cachedAssetFileExpiresIn;
		} else {
			return array();
		}

		// Delete cached file after it expired as it will be regenerated
		if (filemtime($assetsFile) < (time() - $cachedAssetsFileExpiresIn)) {
			self::clearAssetCachedData($jsonStorageFile);
			return array();
		}

		$optionValue = FileSystem::fileGetContents($assetsFile);

		if ($optionValue) {
			$optionValueArray = @json_decode($optionValue, ARRAY_A);

			if ($forType === 'combine') {
				if (! empty($optionValueArray)) {
					foreach ($optionValueArray as $assetsValues) {
						foreach ($assetsValues as $finalValues) {
							// Check if the combined CSS file exists (e.g. maybe it was removed by mistake from the caching directory
							// Or it wasn't created in the first place due to an error
							if ($assetType === 'css' && isset($finalValues['uri_to_final_css_file'], $finalValues['link_hrefs'])
							    && is_file(WP_CONTENT_DIR . OptimizeCss::getRelPathCssCacheDir() . $finalValues['uri_to_final_css_file'])) {
								return $optionValueArray;
							}

							// Check if the combined JS file exists (e.g. maybe it was removed by mistake from the caching directory
							// Or it wasn't created in the first place due to an error
							if ($assetType === 'js' && isset($finalValues['uri_to_final_js_file'], $finalValues['script_srcs'])
							    && is_file(WP_CONTENT_DIR . OptimizeJs::getRelPathJsCacheDir() . $finalValues['uri_to_final_js_file'])) {
								return $optionValueArray;
							}
						}
					}
				}
			} elseif ($forType === 'item') {
				return $optionValueArray;
			}
		}

		// File exists, but it's invalid or outdated; Delete it as it has to be re-generated
		self::clearAssetCachedData($jsonStorageFile);

		return array();
	}

	/**
	 * @param $jsonStorageFile
	 * @param $relPathAssetCacheDir
	 * @param $list
	 * @param $forType
	 */
	public static function setAssetCachedData($jsonStorageFile, $relPathAssetCacheDir, $list, $forType = 'combine')
	{
		// Combine CSS/JS JSON Storage
		if ($forType === 'combine') {
			// Only clean request URIs allowed
			if (strpos($_SERVER['REQUEST_URI'], '?') !== false) {
				list($requestUri) = explode('?', $_SERVER['REQUEST_URI']);
			} else {
				$requestUri = $_SERVER['REQUEST_URI'];
			}

			$requestUriPart = $requestUri;

			// Same results for Homepage (any pagination), 404 Not Found & Date archive pages
			if ($requestUri === '/' || is_404() || is_date() || MainFront::isHomePage()) {
				$requestUriPart = '';
			}

			// Treat the pagination pages the same as the main page (same it's done for the unloading rules)
			if (($currentPage = get_query_var('paged')) && (is_archive() || MainFront::isSingularPage())) {
				$paginationBase = isset($GLOBALS['wp_rewrite']->pagination_base) ? $GLOBALS['wp_rewrite']->pagination_base : 'page';
				$requestUriPart = str_replace('/'.$paginationBase.'/'.$currentPage.'/', '', $requestUriPart);
			}

			$dirToFilename = WP_CONTENT_DIR . dirname($relPathAssetCacheDir) . '/_storage/'
			                 . parse_url(site_url(), PHP_URL_HOST) .
			                 $requestUriPart . '/';

			$dirToFilename = str_replace('//', '/', $dirToFilename);

			if (! is_dir($dirToFilename)) {
				$makeFileDir = @mkdir($dirToFilename, FS_CHMOD_DIR, true);

				if (! $makeFileDir) {
					return;
				}
			}

			$assetsFile = $dirToFilename . self::filterStorageFileName($jsonStorageFile);

			// CSS/JS JSON FILE DATA
			$assetsValue = $list;
		}

		// Optimize single CSS/JS item JSON Storage
		if ($forType === 'item') {
			$dirToFilename = WP_CONTENT_DIR . dirname($relPathAssetCacheDir) . '/_storage/'.self::$optimizedSingleFilesDir.'/';

			$dirToFilename = str_replace('//', '/', $dirToFilename);

			if (! is_dir($dirToFilename)) {
				$makeFileDir = @mkdir($dirToFilename, FS_CHMOD_DIR, true);

				if (! $makeFileDir) {
					return;
				}
			}

			$assetsFile = $dirToFilename . $jsonStorageFile;
			$assetsValue = $list;
		}

        if (isset($assetsFile, $assetsValue)) {
            FileSystem::filePutContents($assetsFile, $assetsValue);
        }
	}

	/**
	 * @param $jsonStorageFile
     *
     * @return array
	 */
	public static function clearAssetCachedData($jsonStorageFile)
	{
		if (strpos($jsonStorageFile, '-combined') !== false) {
			/*
	        * #1: Combined CSS/JS JSON
	        */
			// Only clean request URIs allowed
			if (strpos($_SERVER['REQUEST_URI'], '?') !== false) {
				list($requestUri) = explode('?', $_SERVER['REQUEST_URI']);
			} else {
				$requestUri = $_SERVER['REQUEST_URI'];
			}

			$requestUriPart = $requestUri;

			// Same results for Homepage (any pagination), 404 Not Found & Date archive pages
			if ($requestUri === '/' || is_404() || is_date() || MainFront::isHomePage()) {
				$requestUriPart = '';
			}

			// Treat the pagination pages the same as the main page (same it's done for the unloading rules)
			if (($currentPage = get_query_var('paged')) && (is_archive() || MainFront::isSingularPage())) {
				$paginationBase = isset($GLOBALS['wp_rewrite']->pagination_base) ? $GLOBALS['wp_rewrite']->pagination_base : 'page';
				$requestUriPart = str_replace('/'.$paginationBase.'/'.$currentPage.'/', '', $requestUriPart);
			}

			$dirToFilename = WP_CONTENT_DIR . self::getRelPathPluginCacheDir() . '_storage/'
			                 . parse_url(site_url(), PHP_URL_HOST) .
			                 $requestUriPart;

			// If it doesn't have "/" at the end, append it (it will prevent double forward slashes)
			if (substr($dirToFilename, - 1) !== '/') {
				$dirToFilename .= '/';
			}

			$assetsFile = $dirToFilename . self::filterStorageFileName($jsonStorageFile);
		} elseif (strpos($jsonStorageFile, '_optimize_') !== false) {
			/*
			 * #2: Optimized CSS/JS JSON
			 */
			$dirToFilename = WP_CONTENT_DIR . self::getRelPathPluginCacheDir() . '_storage/'.self::$optimizedSingleFilesDir.'/';
			$assetsFile = $dirToFilename . $jsonStorageFile;
		}

        if ( ! isset($assetsFile) ) {
            return;
        }

        $clearedFile = false;

		if (is_file($assetsFile)) { // avoid E_WARNING errors | check if it exists first
			$clearedFile = @unlink($assetsFile);
		}

        return array('file' => $assetsFile, 'cleared' => $clearedFile);
	}

	/**
	 * Clears all CSS & JS cache
	 *
	 * @param bool $redirectAfter
     *
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function clearCache($redirectAfter = false)
	{
        if (self::doNotClearCache()) {
			return;
		}

		// Any actions before clearing the cache?
		do_action('wpacu_clear_cache_before');

		// No settings available? Must be triggered very early before 'init' action hook; Get the settings!
		if ( ! isset(Main::instance()->settings['clear_cached_files_after']) ) {
			$wpacuSettingsClass = new Settings();
			Main::instance()->settings = $wpacuSettingsClass->getAll();
		}

		$isUriRequest = isset($_GET['wpacu_clear_cache_print']);
		$isAjaxCallOrUriRequest = (isset($_REQUEST['action']) && $_REQUEST['action'] === WPACU_PLUGIN_ID . '_clear_cache' && is_admin()) || $isUriRequest;
		$clearedOutput = $keptOutput = array();

		/*
		 * STEP 1: Clear all JSON/TEXT & all assets (.css & .js) files older than $clearFilesOlderThan days
		 * Clear any transients from the database and the disk
		 */
		$skipFiles       = array('index.php', '.htaccess');
		$fileExtToRemove = array('.txt', '.json', '.css', '.js');

		$clearFilesOlderThanXDays = (int)Main::instance()->settings['clear_cached_files_after']; // days

		$assetCleanUpCacheDir = WP_CONTENT_DIR . self::getRelPathPluginCacheDir();
		$storageDir           = $assetCleanUpCacheDir . '_storage';

		/*
		 * Targeted directories:
		 *
		 * $storageDir.'/item/'
		 * $assetCleanUpCacheDir.'/css/'
		 * $assetCleanUpCacheDir.'/js/'
		 *
		 * SKIP anything else from $storageDir apart from "item"
		 * If a lot of posts are on the website and combine CSS/JS it could lead to memory errors (to be cleared later on)
		 */

		$userIdDirs = array();

		if ( is_dir($assetCleanUpCacheDir) ) {
			$storageEmptyDirs = $allClearableAssets = array();

			$siteHost = (string)parse_url(site_url(), PHP_URL_HOST);
			$siteUri  = (string)parse_url(site_url(), PHP_URL_PATH);

			$relPathToPossibleDir = $storageDir.'/'.$siteHost . $siteUri;

			$targetedDirs = array(
				$storageDir . '/item/',

				$assetCleanUpCacheDir . 'css/',
				$assetCleanUpCacheDir . 'js/',

				// Possible common directories with fewer files
				$relPathToPossibleDir . '/category/',
				$relPathToPossibleDir . '/author/',
				$relPathToPossibleDir . '/tag/'
			);

			foreach ( $targetedDirs as $targetedDir ) {
				$targetedDir = rtrim(str_replace('//', '/', $targetedDir), '/'); // clean it

				if ( ! is_dir($targetedDir) ) { continue; }

				$dirItems = new \RecursiveDirectoryIterator( $targetedDir, \RecursiveDirectoryIterator::SKIP_DOTS );

				foreach (
					new \RecursiveIteratorIterator(
						$dirItems,
						\RecursiveIteratorIterator::SELF_FIRST,
						\RecursiveIteratorIterator::CATCH_GET_CHILD
					) as $item
				) {
					$fileBaseName = trim( strrchr( $item, '/' ), '/' );
					$fileExt      = strrchr( $fileBaseName, '.' );

					if ( is_file( $item ) &&
                         ( $fileMtime = filemtime($item) ) &&
                         in_array( $fileExt, $fileExtToRemove ) &&
                         ( ! in_array( $fileBaseName, $skipFiles ) ) ) {
						$isStorageFile = ( $fileExt === '.txt' || $fileExt === '.json' );
						$isAssetFile   = in_array( $fileExt, array( '.css', '.js' ) );

						// Remove all JSONs & .css & .js (depending on other things as well) ONLY if they are older than $clearFilesOlderThanXDays days (at least one day)
						$clearOlderThanInSeconds = self::$cachedAssetFileExpiresIn; // minimum

						if ($clearFilesOlderThanXDays > 0) {
							$clearOlderThanInSeconds = (86400 * $clearFilesOlderThanXDays); // 1 day = 86400 seconds
						}

						// Condition to delete the cached CSS/JS file: It's older than $clearOlderThanInSeconds since its content was modified
						// $clearFilesOlderThanXDays is taken from
						// "Settings" -> "Plugin Usage Preferences" -> "Clear cached CSS/JS files older than (x) days"
						$isAssetFileToClear = ( $isAssetFile &&
					                          ( strtotime( '-' . $clearOlderThanInSeconds . ' seconds' ) > $fileMtime ) );

						if ( $isStorageFile || $isAssetFileToClear ) {
							if ( $isStorageFile ) {
								// Clear the text files as new ones will be generated
								@unlink($item);

								// [clear output]
								if ($isAjaxCallOrUriRequest && ! is_file($item)) { $clearedOutput[] = $item. ' (storage file)'; }
								// [/clear output]
							}

							if ( $isAssetFileToClear ) {
								$allClearableAssets[] = $item;
							}
						}
					} elseif ( is_dir( $item ) && ( strpos( $item, '/css/logged-in/' ) !== false || strpos( $item, '/js/logged-in/' ) !== false ) ) {
						$userIdDirs[] = $item;
					} elseif ( ($item !== $storageDir) && strpos( $item, $storageDir ) !== false ) {
						$storageEmptyDirs[] = $item;
					}
				}

				Misc::rmDir($targetedDir); // if it's empty, remove it
			}

            wpacuDefineConstant( 'WPACU_SITE_URL_HOST', parse_url(site_url(), PHP_URL_HOST) );

			// Clear all .txt files separately from the storage directory as it will be rebuilt
			self::rmNonEmptyItemsStorageDir($storageDir);

			// Finally clear the assets
			foreach ($allClearableAssets as $assetFile) {
				@unlink($assetFile);
                /* [clear output] */if ($isAjaxCallOrUriRequest && ! is_file($assetFile)) { $clearedOutput[] = $assetFile. ' (cached asset file)'; }/* [/clear output] */
			}

			foreach (array_reverse($storageEmptyDirs) as $storageEmptyDir) {
				Misc::rmDir($storageEmptyDir);
				/* [clear output] */if ($isAjaxCallOrUriRequest && ! is_dir($storageEmptyDir)) { $clearedOutput[] = $storageEmptyDir. ' (storage file cleared and its directory)'; }/* [/clear output] */
			}

			// Remove empty dirs from /css/logged-in/ and /js/logged-in/
			if (! empty($userIdDirs)) {
				foreach ($userIdDirs as $userIdDir) {
					Misc::rmDir($userIdDir); // it needs to be empty, otherwise, it will not be removed
					/* [clear output] */if ($isAjaxCallOrUriRequest && ! is_dir($userIdDir)) { $clearedOutput[] = $userIdDir. ' (user empty directory)'; }/* [/clear output] */
				}
			}
		}

		self::clearAllCacheOldLegacyDirs();
		self::clearAllCacheInlineContentFromTagsNonStatic();

		/*
		 * STEP 2: Remove all transients related to the Minify CSS/JS files feature
		 */
		$toolsClass = new Tools();
		$toolsClass->clearAllCacheTransients();

		// Make sure all the caching files/folders are there in case the plugin was upgraded
		Plugin::createCacheFoldersFiles(array('css', 'js'));

		if ($isAjaxCallOrUriRequest) {
			if (! empty($clearedOutput)) {
				echo 'The following files/directories have been cleared:'."\n";
				if ($isUriRequest) { echo '<br />'; }

				foreach ($clearedOutput as $clearedInfo) {
					echo esc_html($clearedInfo)."\n";
					if ($isUriRequest) { echo '<br />'; }
				}
			}

			if (! empty($keptOutput)) {
				echo "\n".'The following files have been kept:'."\n";
				if ($isUriRequest) { echo '<br />'; }

				foreach ($keptOutput as $keptInfo) {
					echo esc_html($keptInfo)."\n";
					if ($isUriRequest) { echo '<br />'; }
				}
			}
		}

        set_transient(WPACU_PLUGIN_ID . '_last_clear_cache', time());

		// Any actions after clearing the cache?
		do_action('wpacu_clear_cache_after');

		// [START - Clear cache for other plugins if they are enabled]
		// If, for any reason, someone uses Cache Enabler and want to prevent clearing its cache after Asset CleanUp Pro clears its own cache
		// they can do so via the following code (e.g. in functions.php of their Child theme):
		// add_filter('wpacu_clear_cache_enabler_cache', '__return_false');
		if (assetCleanUpClearCacheEnablerCache()) {
			if ($isAjaxCallOrUriRequest) {
				echo '<br />"Cache Enabler" plugin is active. The following action was called: "cache_enabler_clear_complete_cache"';
			}
			do_action('cache_enabler_clear_complete_cache'); // Cache Enabler
		}

		// [END - Clear cache for other plugins if they are enabled]

		if ($isUriRequest) {
			exit();
		}

		if ($redirectAfter && wp_get_referer()) {
            if (isset($_GET['wpacu_dash_area']) && is_admin()) {
	            // it will be used later (after the admin reaches the redirected page)
                // to show the admin notice that the cache was cleared
	            set_transient( WPACU_PLUGIN_ID . '_cache_just_cleared_via_link_dash_area', true );
            }

			wp_safe_redirect(add_query_arg(array('wpacu_cache_cleared' => 1), wp_get_referer()));
			exit();
		}
	}

	/**
     * This link is available in the top admin bar and the top right side of the plugin's pages
     *
	 * @return string
	 */
	public static function generateClearCachingUrl()
    {
	    $clearAssetsCachePath  = 'admin-post.php?action=assetcleanup_clear_assets_cache';

	    if ( is_admin() ) {
		    // If the admin clears the cache within the Dashboard
		    // Use the query string below as a way to trigger an admin notice
		    // that the caching has been cleared
		    $clearAssetsCachePath .= '&wpacu_dash_area';
	    }

	    $clearAssetsCachePath .= '&_wp_http_referer=' . urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) );

        return wp_nonce_url( admin_url( $clearAssetsCachePath ), 'assetcleanup_clear_assets_cache' );
    }

	/**
	 * Special Case: Any CSS/JS files from /wp-content/cache//asset-cleanup/(css|js)/item/inline/
	 * These files are never loaded as static, externally (from LINK or SCRIPT tag);
	 * Their content is just pulled (if not expired) into the STYLE/SCRIPT inline tag
	 * If there are any expired files there, remove them
	 *
	 * @return void
	 */
	public static function clearAllCacheInlineContentFromTagsNonStatic()
	{
		foreach (array('.css', '.js') as $assetExt) {
			$assetTypeDir = ($assetExt === '.css') ? OptimizeCss::getRelPathCssCacheDir() : OptimizeJs::getRelPathJsCacheDir();

			$assetsInlineTagsContentDir = WP_CONTENT_DIR . $assetTypeDir . self::$optimizedSingleFilesDir . '/inline/';

			if ( is_dir( $assetsInlineTagsContentDir ) ) {
				$assetInlineTagsContentDirFiles = scandir( $assetsInlineTagsContentDir );

				foreach ( $assetInlineTagsContentDirFiles as $assetFile ) {
					if ( strpos( $assetFile, $assetExt ) === false ) {
						continue;
					}

					$fullPathToFile = $assetsInlineTagsContentDir . $assetFile;

					$isExpired = ( ( time() - self::$cachedAssetFileExpiresIn ) > filemtime( $fullPathToFile ) );

					if ( $isExpired ) {
						@unlink( $fullPathToFile );
					}
				}

				}
		}
	}

	/**
	 * @return void
	 */
	public static function clearAllCacheOldLegacyDirs()
	{
		if (is_dir(WP_CONTENT_DIR . OptimizeCss::getRelPathCssCacheDir() .'min')) { Misc::rmDir( WP_CONTENT_DIR . OptimizeCss::getRelPathCssCacheDir() .'min' ); }
		if (is_dir(WP_CONTENT_DIR . OptimizeJs::getRelPathJsCacheDir()   .'min')) { Misc::rmDir( WP_CONTENT_DIR . OptimizeJs::getRelPathJsCacheDir()   .'min' ); }
		if (is_dir(WP_CONTENT_DIR . OptimizeCss::getRelPathCssCacheDir() .'one')) { Misc::rmDir( WP_CONTENT_DIR . OptimizeCss::getRelPathCssCacheDir() .'one' ); }
		if (is_dir(WP_CONTENT_DIR . OptimizeJs::getRelPathJsCacheDir()   .'one')) { Misc::rmDir( WP_CONTENT_DIR . OptimizeJs::getRelPathJsCacheDir()   .'one' ); }
	}

	/**
	 * Alias for clearCache() - some developers might have implemented the old clearAllCache()
	 *
	 * @param bool $redirectAfter
	 */
	public static function clearAllCache($redirectAfter = false)
	{
		self::clearCache($redirectAfter);
	}

	/**
	 * This is usually done when the plugin is deactivated
	 * e.g. if you use Autoptimize, and it remains active, you will likely want to have its caching cleared with traces from Asset CleanUp
	 */
	public static function clearOtherPluginsCache()
	{
		self::clearAutoptimizeCache();
		self::clearCacheEnablerCache();
	}

	/**
	 * @return void
	 */
	public static function clearAutoptimizeCache()
	{
		if ( assetCleanUpClearAutoptimizeCache() &&
             class_exists('\autoptimizeCache') &&
             method_exists('\autoptimizeCache', 'clearall') &&
             wpacuIsPluginActive('autoptimize/autoptimize.php') ) {
			\autoptimizeCache::clearall();
		}
	}

	/**
	 * @param string $triggeredFrom (e.g. ajax_call)
	 *
	 * @return void
	 */
	public static function clearCacheEnablerCache($triggeredFrom = '')
	{
		$isCacheEnablerActive = wpacuIsPluginActive('cache-enabler/cache-enabler.php');

		// [IF AJAX CALL]
		if ($triggeredFrom === 'ajax_call') {
			if ($isCacheEnablerActive) {
				echo '"Cache Enabler" plugin is active.<br />';
			} else {
				echo '"Cache Enabler" plugin is NOT active.<br />';
				exit();
			}

			if (assetCleanUpClearCacheEnablerCache()) {
				echo '"Cache Enabler" plugin is set to have its cache cleared.<br />';
			} else {
				echo '"Cache Enabler" plugin is set to not have its caching cleared via "WPACU_DO_NOT_ALSO_CLEAR_CACHE_ENABLER_CACHE" constant';
				exit();
			}
		}
		// [/IF AJAX CALL]

		if ($isCacheEnablerActive && assetCleanUpClearCacheEnablerCache()) {
			do_action('cache_enabler_clear_complete_cache');
		}

		// [IF AJAX CALL]
		if ($triggeredFrom === 'ajax_call') {
			if (did_action('cache_enabler_clear_complete_cache')) {
				echo '"Cache Enabler" plugin had its "cache_enabler_clear_complete_cache" action triggered.<br />';
			}

			exit();
		}
		// [/IF AJAX CALL]
	}

	/**
	 * @param bool $includeHtmlTags
	 *
	 * @return array
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function getStorageStats($includeHtmlTags = true)
	{
		$assetCleanUpCacheDir = WP_CONTENT_DIR . self::getRelPathPluginCacheDir();

		if (is_dir($assetCleanUpCacheDir)) {
			$dirItems = new \RecursiveDirectoryIterator($assetCleanUpCacheDir, \RecursiveDirectoryIterator::SKIP_DOTS);
			$fileDirs = $fileDirsWithCssJs = array();

			// All files
			$totalFiles = 0;
			$totalSize = 0;

			// Just .css & .js
			$totalSizeAssets = 0;
			$totalFilesAssets = 0;

			foreach (new \RecursiveIteratorIterator($dirItems, \RecursiveIteratorIterator::SELF_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD) as $item) {
				$fileBaseName = trim(strrchr($item, '/'), '/');
				$fileExt = strrchr($fileBaseName, '.');

				if ($item->isFile()) {
					$fileSize = $item->getSize();

					$fileDir = trim(dirname($item));
					$fileDirs[$fileDir][] = $fileSize;

					$totalSize += $fileSize;
					$totalFiles ++;

					if (in_array($fileExt, array('.css', '.js'))) {
						$fileDirsWithCssJs[] = $fileDir;
						$totalSizeAssets += $fileSize;
						$totalFilesAssets ++;
					}
				}
			}

			ksort($fileDirs, SORT_ASC);

			return array(
				'total_size'         => MiscAdmin::formatBytes($totalSize, 2, '', $includeHtmlTags),
				'total_files'        => $totalFiles,

				'total_size_assets'  => MiscAdmin::formatBytes($totalSizeAssets, 2, '', $includeHtmlTags),
				'total_files_assets' => $totalFilesAssets,

				'dirs_files_sizes'   => $fileDirs,
				'dirs_css_js'        => array_unique($fileDirsWithCssJs)
			);
		}

		return array();
	}

	/**
	 * Prevent clear cache function in the following situations
	 *
	 * @return bool
	 */
	public static function doNotClearCache()
	{
		// WooCommerce GET or AJAX call
		if (isset($_GET['wc-ajax']) && $_GET['wc-ajax']) {
			return true;
		}

		if (wpacuIsDefinedConstant('WC_DOING_AJAX')) {
			return true;
		}

		return false;
	}

	/**
	 * @param $fileName
	 *
	 * @return array|string|string[]
	 */
	public static function filterStorageFileName($fileName)
	{
		$filterString = '';

		if (is_404()) {
			$filterString = '-404-not-found';
		} elseif (is_date()) {
			$filterString = '-date';
		} elseif (MainFront::isHomePage()) {
			$filterString = '-homepage';
		}

		$current_user = wp_get_current_user();

		if (isset($current_user->ID) && $current_user->ID > 0) {
			$fileName = str_replace(
				'{maybe-extra-info}',
				$filterString.'-logged-in',
				$fileName
			);
		} else {
			// Just clear {maybe-extra-info}
			$fileName = str_replace('{maybe-extra-info}', $filterString, $fileName);
		}

		return $fileName;
	}

	/**
	 * @param string $anyCdnUrl
	 *
	 * @return array|string|string[]
	 */
	public static function filterWpContentUrl($anyCdnUrl = '')
	{
		$wpContentUrl = WP_CONTENT_URL;

		$parseContentUrl = parse_url($wpContentUrl);
		$parseBaseUrl = parse_url(site_url());

		// Perhaps WPML plugin is used and the content URL is different from the current domain which might be for a different language
		if ( ($parseContentUrl['host'] !== $parseBaseUrl['host']) &&
		     (isset($_SERVER['HTTP_HOST'], $parseContentUrl['path']) && $_SERVER['HTTP_HOST'] !== $parseContentUrl['host']) &&
			 is_dir(rtrim(ABSPATH, '/') . $parseContentUrl['path']) ) {
			$wpContentUrl = str_replace($parseContentUrl['host'], $parseBaseUrl['host'], $wpContentUrl);
		}

		// Is the page loaded via SSL, but the site url from the database starts with 'http://'
		// Then use '//' in front of CSS/JS generated via Asset CleanUp
		if (Misc::isHttpsSecure() && strpos($wpContentUrl, 'http://') !== false) {
			$wpContentUrl = str_replace('http://', '//', $wpContentUrl);
		}

		if ($anyCdnUrl) {
			$wpContentUrl = str_replace(site_url(), self::cdnToUrlFormat($anyCdnUrl, 'raw'), $wpContentUrl);
		}

		return $wpContentUrl;
	}

	/**
	 * @param $assetContent
	 * @param $forAssetType
	 *
	 * @return string|string[]
	 */
	public static function stripSourceMap($assetContent, $forAssetType)
	{
		if ($forAssetType === 'css') {
			$sourceMappingURLStr = '/*# sourceMappingURL=';
			$sourceMappingURLStrReplaceStart = '/*';
		} else {
			$sourceMappingURLStr = '//# sourceMappingURL=';
			$sourceMappingURLStrReplaceStart = '//';
		}

		$assetContent = trim($assetContent);

		if (strpos($assetContent, "\n") !== false) {
			$allContentLines = explode("\n", $assetContent);
			$lastContentLine = end($allContentLines);

			if (strpos($lastContentLine, $sourceMappingURLStr) !== false) {
				return str_replace( $sourceMappingURLStr, $sourceMappingURLStrReplaceStart.'# Current File Updated by '.WPACU_PLUGIN_TITLE.' - Original Source Map: ', $assetContent );
			}
		}

		return $assetContent;
	}

	/**
	 * @param $for ("css" or "js")
	 *
	 * @return bool
	 */
	public static function appendInlineCodeToCombineAssetType($for)
	{
		$settingsIndex = '_combine_loaded_'.$for.'_append_handle_extra';
		return Misc::isWpVersionAtLeast('5.5') &&
		        isset(Main::instance()->settings[$settingsIndex]) &&
	            Main::instance()->settings[$settingsIndex];
	}

	/**
	 * URLs with query strings are not loading Optimised Assets (e.g. combine CSS files into one file)
	 * However, there are exceptions such as the ones below (preview, debugging purposes)
	 *
	 * @return bool
	 */
	public static function loadOptimizedAssetsIfQueryStrings()
	{
		$isPreview = (isset($_GET['preview_id'], $_GET['preview_nonce'], $_GET['preview'])
		              || isset($_GET['preview'])); // show the CSS/JS as combined IF the option is enabled despite the query string (for debugging purposes)

		if ($isPreview) {
			return true;
		}

		$ignoreQueryStrings = array(
			'wpacu_no_css_minify',
			'wpacu_no_js_minify',
			'wpacu_no_css_combine',
			'wpacu_no_js_combine',
			'wpacu_debug',
			'wpacu_preload',
			'wpacu_skip_test_mode',
		);

		$queryStringsToIgnoreFromTheURIForOptimizingAssets = array(
			'_ga',
			'_ke',
			'adgroupid',
			'adid',
			'age-verified',
			'ao_noptimize',
			'campaignid',
			'ck_subscriber_id', // ConvertKit's query parameter
			'cn-reloaded',
			'dclid',
			'dm_i', // dotdigital
			'dm_t', // dotdigital
			'ef_id',
			'epik', // Pinterest
			'fb_action_ids',
			'fb_action_types',
			'fb_source',
			'fbclick',
			'fbclid',
			'gclid',
			'gclsrc',
			'mc_cid',
			'mc_eid',
			'mkt_tok',      // Marketo (tracking users)
			'msclkid',      // Microsoft Click ID
			'mtm_campaign',
			'mtm_cid',
			'mtm_content',
			'mtm_keyword',
			'mtm_medium',
			'mtm_source',
			'pk_campaign',  // Piwik PRO URL builder
			'pk_cid',       // Piwik PRO URL builder
			'pk_content',   // Piwik PRO URL builder
			'pk_keyword',   // Piwik PRO URL builder
			'pk_medium',    // Piwik PRO URL builder
			'pk_source',    // Piwik PRO URL builder
			'ref',
			'SSAID',
			'sscid',
			'usqp',
			'utm_campaign',
			'utm_content',
			'utm_expid',
			'utm_expid',
			'utm_medium',
			'utm_referrer',
			'utm_source',
			'utm_term',
		);

		$isQueryString = false;

		foreach (array_merge($ignoreQueryStrings, $queryStringsToIgnoreFromTheURIForOptimizingAssets) as $ignoreQueryString) {
			if (isset($_GET[$ignoreQueryString])) {
				$isQueryString = true;
				break;
			}
		}

		return $isQueryString;
	}

	/**
	 * Possible values returned: 'db', 'disk'
	 *
	 * @return mixed|string
	 */
	public static function fetchCachedFilesFrom()
	{
		if (Main::instance()->settings['fetch_cached_files_details_from'] === 'db_disk') {
			if ( ! isset( $GLOBALS['wpacu_from_location_inc'] ) ) {
				$GLOBALS['wpacu_from_location_inc'] = 1;
			}
			$fromLocation = ( $GLOBALS['wpacu_from_location_inc'] % 2 ) ? 'db' : 'disk';
		} else {
			$fromLocation = Main::instance()->settings['fetch_cached_files_details_from'];
		}

		return $fromLocation;
	}

	/**
	 * The following custom methods of transients work for both (MySQL) database and local storage
	 * By default, the data is stored in the disk only
	 *
	 * @param $transient
	 *
	 * @return array|string
	 */
	public static function getTransient($transient)
	{
		$fromLocation = self::fetchCachedFilesFrom();

		// Stored in the "Disk": Local record
		if ($fromLocation === 'disk') {
			$dirToFilename = WP_CONTENT_DIR . self::getRelPathPluginCacheDir() . '_storage/'.self::$optimizedSingleFilesDir.'/';
			$assetsFile = $dirToFilename . $transient.'.txt';

			if (is_file($assetsFile)) {
				$contents = trim( FileSystem::fileGetContents( $assetsFile ) );

                if ($contents === 'no_alter') {
                    return array('no_alter');
                }

				if ( ! $contents || strpos( $contents, "\n" ) === false ) {
					// The file is empty, or the contents could not be retrieved
					// If a PHP reading error was triggered, it should be logged in the "error_log" file
					return array();
				}

                $savedValuesArray = explode( "\n", $contents );

                if ( isset( $savedValuesArray[0], $savedValuesArray[1], $savedValuesArray[2] ) ) {
                    return array(
                        'source_uri'   => $savedValuesArray[0],
                        'optimize_uri' => $savedValuesArray[1],
                        'ver'          => (int)$savedValuesArray[2]
                    );
                }
			}

			return array();
		}

		// Stored in the "Database"
		// MySQL record: $fromLocation default 'db'
		$dbTransientValue = trim(get_transient($transient));

		if ($dbTransientValue === 'no_alter') {
			return array('no_alter');
		}

        if (strpos($dbTransientValue, "\n") !== false) {
	        $savedValuesArray = explode( "\n", $dbTransientValue );

	        if ( isset( $savedValuesArray[0], $savedValuesArray[1], $savedValuesArray[2] ) ) {
		        return array(
			        'source_uri'   => $savedValuesArray[0],
			        'optimize_uri' => $savedValuesArray[1],
			        'ver'          => (int)$savedValuesArray[2]
		        );
	        }
        }

        return array(); // nothing fetched so far? return an empty array
	}

	/**
	 * @param $transient
	 * @param $value
	 * @param int $expiration
	 */
	public static function setTransient($transient, $value, $expiration = 0)
	{
		$fetchFrom = Main::instance()->settings['fetch_cached_files_details_from'];

		if (in_array($fetchFrom, array('db', 'db_disk'))) {
			// MySQL record
			set_transient( $transient, $value, $expiration );
		}

		if (in_array($fetchFrom, array('disk', 'db_disk'))) {
			// File record
			self::setAssetCachedData(
				$transient . '.txt',
				OptimizeCss::getRelPathCssCacheDir(),
				$value,
				'item'
			);
		}

		}

	/**
	 * @param $transientName
	 */
	public static function deleteTransient($transientName)
	{
		$fetchFrom = Main::instance()->settings['fetch_cached_files_details_from'];

		if (in_array($fetchFrom, array('db', 'db_disk'))) {
			// MySQL record
			delete_transient( $transientName );
		}

		if (in_array($fetchFrom, array('disk', 'db_disk'))) {
			// File record (in case there is any)
			self::clearAssetCachedData( $transientName . '.txt' );
		}
	}

	/**
	 * @return array
	 */
	public static function getAnyCdnUrls()
	{
		if (! Main::instance()->settings['cdn_rewrite_enable']) {
			return array();
		}

		$cdnUrls = array();

		$cdnCssUrl = trim(Main::instance()->settings['cdn_rewrite_url_css']) ?: '';
		$cdnJsUrl  = trim(Main::instance()->settings['cdn_rewrite_url_js'])  ?: '';

		if ($cdnCssUrl) {
			$cdnUrls['css'] = $cdnCssUrl;
		}

		if ($cdnJsUrl) {
			$cdnUrls['js'] = $cdnJsUrl;
		}

		return $cdnUrls;
	}

	/**
	 * @param $cdnUrl
	 * @param $getType
	 *
	 * @return string
	 */
	public static function cdnToUrlFormat($cdnUrl, $getType)
	{
		if (! $cdnUrl) {
			return site_url();
		}

		$cdnUrlFinal = $cdnUrl;

		// CNAME (not URL) was added
		if (strpos($cdnUrl, '//') === false) {
			$cdnUrlFinal = '//'.$cdnUrl;
		}

		// The URL will start with //
		if ($getType === 'rel') {
			$cdnUrlFinal = trim(str_ireplace(array('http://', 'https://'), '//', $cdnUrl));
		}

		return rtrim($cdnUrlFinal, '/'); // no trailing slash after the CDN URL
	}

	/**
	 * This is related to the cached CSS/JS combined files from _storage directory located within getRelPathPluginCacheDir() caching directory
	 *
	 * @param $postId
	 * @param bool $checkTiming | if set to "true" it will check if the caching timing expires and if it did, then delete the file
	 */
	public static function clearItemStorageForPost($postId, $checkTiming = false)
	{
		$postPermalink  = get_permalink($postId);
		$requestUriPath = (string)parse_url($postPermalink, PHP_URL_PATH);

		$dirToFilename = WP_CONTENT_DIR . self::getRelPathPluginCacheDir() . '/_storage/'
		                 . parse_url(site_url(), PHP_URL_HOST) . '/'. $requestUriPath;

		$dirToFilename = str_replace('//', '/', $dirToFilename);

		$clearOlderThanInSeconds = self::$cachedAssetFileExpiresIn;

        if ( ! isset(Main::instance()->settings['clear_cached_files_after']) ) {
            $wpacuSettingsClass = new Settings();
            Main::instance()->settings = $wpacuSettingsClass->getAll();
        }

		$clearFilesOlderThanXDays = Main::instance()->settings['clear_cached_files_after'];

		if ($clearFilesOlderThanXDays > 0) {
			$clearOlderThanInSeconds += (86400 * $clearFilesOlderThanXDays);
		}

		if (is_dir($dirToFilename)) {
			$filesInDir = scandir($dirToFilename);

			if (! empty($filesInDir)) {
				foreach ($filesInDir as $wpacuFile) {
					if ( $wpacuFile === '.' || $wpacuFile === '..' ) {
						continue;
					}

					$pathToFile = $dirToFilename . $wpacuFile;

					if (strrchr($wpacuFile, '.') === '.json' && is_file($pathToFile)) {
						if ($checkTiming) {
							$isExpired = ( strtotime( '-' . $clearOlderThanInSeconds . ' seconds' ) > filemtime($pathToFile) );

							if (! $isExpired) {
								// Not expired yet, do not remove it by skipping this loop
								continue;
							}
						}

						@unlink($dirToFilename . $wpacuFile);
					}
				}

				Misc::rmDir($dirToFilename);
			}
		}
	}

	/**
	 * @param $targetDir
     * @noinspection PhpUndefinedConstantInspection
     * */
	public static function rmNonEmptyItemsStorageDir($targetDir)
	{
		$dirFiles = glob($targetDir . '/*');

		foreach ($dirFiles as $targetFile) {
			if (is_dir($targetFile)) {
				self::rmNonEmptyItemsStorageDir($targetFile);
			} elseif(strrchr($targetFile, '.') === '.txt') {
				@unlink($targetFile);
			}
		}

		if (defined('WPACU_SITE_URL_HOST') && strpos($targetDir, WPACU_SITE_URL_HOST) !== false) {
			Misc::rmDir($targetDir);
		}
	}

	/**
	 * @param $assetContentSha1
	 * @param $assetType
	 *
	 * @return bool
	 */
	public static function originalContentIsAlreadyMarkedAsMinified($assetContentSha1, $assetType)
	{
		$optionToCheck = WPACU_PLUGIN_ID . '_global_data';
		$globalKey = 'already_minified'; // HEAD or BODY

		$existingListEmpty = array('styles' => array($globalKey => array()), 'scripts' => array($globalKey => array()));
		$existingListJson = get_option($optionToCheck);

		$existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
		$existingList = $existingListData['list'];

		return isset( $existingList[ $assetType ]['already_minified'] ) && in_array( $assetContentSha1, $existingList[ $assetType ]['already_minified'] );
	}

	/**
	 * @param $assetContentSha1
	 * @param $assetType
	 */
	public static function originalContentMarkAsAlreadyMinified($assetContentSha1, $assetType)
	{
		$optionToUpdate = WPACU_PLUGIN_ID . '_global_data';
		$globalKey = 'already_minified'; // HEAD or BODY

		$existingListEmpty = array('styles' => array($globalKey => array()), 'scripts' => array($globalKey => array()));
		$existingListJson = get_option($optionToUpdate);

		$existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
		$existingList = $existingListData['list'];

		// Limit it to 100 maximum entries
		$totalEntries = isset($existingList[$assetType]['already_minified']) ? count($existingList[$assetType]['already_minified']) : 0;

		if ($totalEntries === 100) {
			return; // stop here
		}

		if ($totalEntries < 1) { // declare the array if no entries are there
			$existingList[$assetType]['already_minified'] = array();
		} else if ($totalEntries < 100) { // append to the array
			$existingList[$assetType]['already_minified'][] = $assetContentSha1;
		} else if ($totalEntries > 100) { // already passed the number, trim the list
			$existingList[$assetType]['already_minified'] = array_slice($existingList[$assetType]['already_minified'], 0, 100);
		}

		update_option($optionToUpdate, wp_json_encode(Misc::filterList($existingList)));
	}

	// [START] For debugging purposes
	/**
	 * @return array
	 */
	public static function getAlreadyMarkedAsMinified()
	{
		$alreadyMinified = array();

		$optionToUpdate = WPACU_PLUGIN_ID . '_global_data';
		$globalKey = 'already_minified';

		$existingListEmpty = array('styles' => array($globalKey => array()), 'scripts' => array($globalKey => array()));
		$existingListJson = get_option($optionToUpdate);

		$existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
		$existingList = $existingListData['list'];

		if (isset($existingList['styles']['already_minified'])) {
			$alreadyMinified['styles'] = $existingList['styles']['already_minified'];
		}

		if (isset($existingList['scripts']['already_minified'])) {
			$alreadyMinified['scripts'] = $existingList['scripts']['already_minified'];
		}

		return $alreadyMinified;
	}

	/**
	 *
	 */
	public static function removeAlreadyMarkedAsMinified()
	{
		$optionToUpdate = WPACU_PLUGIN_ID . '_global_data';
		$globalKey = 'already_minified';

		$existingListEmpty = array('styles' => array($globalKey => array()), 'scripts' => array($globalKey => array()));
		$existingListJson = get_option($optionToUpdate);

		$existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
		$existingList = $existingListData['list'];

		if (isset($existingList['styles']['already_minified'])) {
			unset($existingList['styles']['already_minified']);
		}

		if (isset($existingList['scripts']['already_minified'])) {
			unset($existingList['scripts']['already_minified']);
		}

		update_option($optionToUpdate, wp_json_encode(Misc::filterList($existingList)));
	}

	/**
	 *
	 */
	public static function limitAlreadyMarkedAsMinified()
	{
		$optionToUpdate = WPACU_PLUGIN_ID . '_global_data';
		$globalKey = 'already_minified';

		$existingListEmpty = array('styles' => array($globalKey => array()), 'scripts' => array($globalKey => array()));
		$existingListJson = get_option($optionToUpdate);

		$existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
		$existingList = $existingListData['list'];

		$maxEntries = 100;

		// Limit it to $maxEntries maximum entries
		foreach (array('styles', 'scripts') as $assetType) {
			$totalEntries = isset( $existingList[ $assetType ]['already_minified'] ) ? count( $existingList[ $assetType ]['already_minified'] ) : 0;
			if ($totalEntries > $maxEntries) {
				$existingList[ $assetType ]['already_minified'] = array_slice( $existingList[ $assetType ]['already_minified'], 0, $maxEntries );
			}
		}

		update_option($optionToUpdate, wp_json_encode(Misc::filterList($existingList)));
	}
	// [END] For debugging purposes

	}
