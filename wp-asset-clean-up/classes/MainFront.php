<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;
use WpAssetCleanUp\OptimiseAssets\OptimizeJs;

/**
 * Class MainFront
 *
 * This class has functions that are only for the front-end view
 * for both the admin and guest visits (nothing within the /wp-admin/ area)
 *
 * @package WpAssetCleanUp
 */
class MainFront
{
	/**
	 * Populated in the Parser constructor
	 *
	 * @var array
	 */
	public $skipAssets = array( 'styles' => array(), 'scripts' => array() );

	/**
	 * @var MainFront|null
	 */
	private static $singleton;

	/**
	 * @return null|MainFront
	 */
	public static function instance()
    {
		if ( self::$singleton === null ) {
			self::$singleton = new self();
		}

		return self::$singleton;
	}

	/**
	 * Parser constructor.
	 */
	public function __construct()
    {
        add_action('init', array($this, 'triggersAfterInitFrontendView'));

	    // Early Triggers
	    $wpacuAction = class_exists('\Elementor\Maintenance_Mode') && Misc::isElementorMaintenanceModeOn() ? 'template_redirect' : 'wp';

	    if ($wpacuAction === 'wp') {
		    add_action( 'wp', array( $this, 'setVarsBeforeUpdate' ), 8 );
		    add_action( 'wp', array( $this, 'setVarsAfterAnyUpdate' ) );
	    } else {
		    add_action( 'template_redirect', array( $this, 'setVarsBeforeUpdate' ), 12 ); // over 11 which is set in Elementor's maintenance-mode.php
		    add_action( 'template_redirect', array( $this, 'setVarsAfterAnyUpdate' ), 13 );
	    }

	    // Fetch Assets AJAX Call? Make sure the output is as clean as possible (no plugins interfering with it)
	    // It can also be used for debugging purposes (via /?wpacu_clean_load) when you want to view all the CSS/JS
	    // that are loaded in the HTML source code before they are unloaded or altered in any way
	    if ( array_key_exists('wpacu_clean_load', $_GET) || Main::instance()->isGetAssetsCall ) {
		    $wpacuCleanUp = new CleanUp();
		    $wpacuCleanUp->cleanUpHtmlOutputForAssetsCall();
	    }

	    // "Direct" AJAX call or "WP Remote POST" method used?
	    // Do not trigger the admin bar as it's not relevant
	    if ( Main::instance()->isAjaxCall ) {
		    add_filter( 'show_admin_bar', '__return_false' );
	    }

	    // Front-end View - Unload the assets
	    // If there are reasons to prevent the unloading in case 'test mode' is enabled,
	    // then the prevention will trigger within filterStyles() and filterScripts()

	    /*
		 * [START] /?wpassetcleanup_load=1 is called
		 */
	    if ( Main::instance()->isGetAssetsCall ) {
		    // These actions are also called when the page is loaded without query string (regular load)
		    // This time, the CSS/JS will not be unloaded, but the CSS/JS marked for unload will be collected
		    // and passed to the AJAX call for the option "Group by loaded or unloaded status"
		    if ( get_option( 'siteground_optimizer_combine_css' ) ) {
			    add_action( 'wp_print_styles',     array( $this, 'filterStyles' ), 9 ); // priority should be below 10
		    }
		    add_action( 'wp_print_styles',         array( $this, 'filterStyles' ), 100000 );
		    add_action( 'wp_print_scripts',        array( $this, 'filterScripts' ), 100000 );
		    add_action( 'wp_print_footer_scripts', array( $this, 'onPrintFooterScriptsStyles' ), 1 );
	    }
	    /*
		 * [END] /?wpassetcleanup_load=1 is called
		 */

	    /*
	     * [START] Front-end page visited (e.g. by the admin or a guest visitor)
	     */
	    else {
		    // [START] Unload CSS/JS on page request (for debugging)
		    add_filter( 'wpacu_ignore_child_parent_list', array( $this, 'filterIgnoreChildParentList' ) );
		    // [END] Unload CSS/JS on page request (for debugging)

		    // SG Optimizer Compatibility: Unload Styles - HEAD (Before pre_combine_header_styles() from Combinator)
		    if ( get_option( 'siteground_optimizer_combine_css' ) ) {
			    add_action( 'wp_print_styles',     array( $this, 'filterStyles' ), 9 ); // priority should be below 10
		    }

		    self::filterStylesSpecialCases(); // e.g. CSS enqueued in a different way via Oxygen Builder

		    add_action( 'wp_print_styles',         array( $this, 'filterStyles' ), 100000 ); // Unload Styles  - HEAD
		    add_action( 'wp_print_scripts',        array( $this, 'filterScripts' ), 100000 ); // Unload Scripts - HEAD

		    add_action( 'wp_print_styles',         array( $this, 'printAnySpecialCss' ), PHP_INT_MAX );

		    // Unload Styles & Scripts - FOOTER
		    // Needs to be triggered very soon as some old plugins/themes use wp_footer() to enqueue scripts
		    // Sometimes styles are loaded in the BODY section of the page
		    add_action( 'wp_print_footer_scripts', array( $this, 'onPrintFooterScriptsStyles' ), 1 );

            add_filter('init', function() {
                if (OptimizeCommon::preventAnyFrontendOptimization()) {
                    return;
                }

                if (OptimizeCommon::isWorthCheckingForCssOptimization()) {
                    add_filter('style_loader_tag', function ($tag, $handle) {
                        ObjectCache::wpacu_cache_set('wpacu_style_loader_tag_' . $handle, $tag);
                        return $tag;
                    }, 10, 2);
                }

                if (OptimizeCommon::isWorthCheckingForJsOptimization()) {
                    add_filter('script_loader_tag', function ($tag, $handle) {
                        ObjectCache::wpacu_cache_set('wpacu_script_loader_tag_' . $handle, $tag);
                        return $tag;
                    }, 10, 2);
                }
            });

		    // Preloads
		    add_action( 'wp_head', static function() {
			    if ( wpacuIsDefinedConstant('WPACU_ALLOW_ONLY_UNLOAD_RULES') || OptimizeCommon::preventAnyFrontendOptimization() ) {
				    return;
			    }

                if ( ! wpacuIsDefinedConstant('WPACU_NO_ASSETS_PRELOADED') ) {
                    // Only place the market IF there's at least one preload OR combine JS is activated
                    $preloadsClass = new Preloads();
                    $preloadsClass->initFront();

                    if ( ! empty($preloadsClass::instance()->preloads['styles']) ) {
                        echo Preloads::DEL_STYLES_PRELOADS;
                    }

                    if ( ! empty($preloadsClass::instance()->preloads['scripts']) || OptimizeJs::proceedWithJsCombine() ) {
                        echo Preloads::DEL_SCRIPTS_PRELOADS;
                    }
                }
		    }, 1 );

		    add_filter( 'style_loader_tag', static function( $styleTag, $tagHandle ) {
			    /* [wpacu_timing] */ $wpacuTimingName = 'style_loader_tag'; Misc::scriptExecTimer( $wpacuTimingName ); /* [/wpacu_timing] */

			    if ( OptimizeCommon::preventAnyFrontendOptimization() ) {
				    return $styleTag;
			    }

			    // Preload the plugin's CSS for assets management layout (for faster content paint if the user is logged-in and manages the assets in the front-end)
			    // For a better admin experience
			    if ( $tagHandle === WPACU_PLUGIN_ID . '-style' ) {
                    $styleTag = apply_filters('wpacu_preload_css_async_tag', $styleTag);
			    }

			    // Irrelevant for Critical CSS as the top admin bar is for logged-in users
			    // and if it's not included in the critical CSS it would cause a flash of unstyled content which is not pleasant for the admin
			    if ( $tagHandle === 'admin-bar' ) {
				    $styleTag = str_replace( '<link ', '<link data-wpacu-skip-preload=\'1\' ', $styleTag );
			    }

			    if ( OptimizeCommon::preventAnyFrontendOptimization() ) {
				    /* [wpacu_timing] */ Misc::scriptExecTimer( $wpacuTimingName, 'end' ); /* [/wpacu_timing] */
				    return $styleTag;
			    }

			    // Alter for debugging purposes; triggers before anything else
			    // e.g. you're working on a website and there is no Dashboard access, and you want to determine the handle name
			    // if the handle name is not showing up, then the LINK stylesheet has been hardcoded (not enqueued the WordPress way)
			    if ( isset($_GET['wpacu_show_handle_names']) ) {
				    $styleTag = str_replace( '<link ', '<link data-wpacu-debug-style-handle=\'' . $tagHandle . '\' ', $styleTag );
			    }

			    if ( strpos( $styleTag, 'data-wpacu-style-handle' ) === false ) {
				    $styleTag = str_replace( '<link ', '<link data-wpacu-style-handle=\'' . $tagHandle . '\' ', $styleTag );
			    }

			    /* [wpacu_timing] */ Misc::scriptExecTimer( $wpacuTimingName, 'end' ); /* [/wpacu_timing] */
			    return $styleTag;
		    }, PHP_INT_MAX, 2 ); // Trigger it later in case plugins such as "Ronneby Core" plugin alters it

		    add_filter( 'script_loader_tag', static function( $scriptTag, $tagHandle ) {
			    /* [wpacu_timing] */ $wpacuTimingName = 'script_loader_tag'; Misc::scriptExecTimer( $wpacuTimingName ); /* [/wpacu_timing] */

			    if ( OptimizeCommon::preventAnyFrontendOptimization() ) {
				    /* [wpacu_timing] */ Misc::scriptExecTimer( $wpacuTimingName, 'end' ); /* [/wpacu_timing] */
				    return $scriptTag;
			    }

			    // Alter for debugging purposes; triggers before anything else
			    // e.g. you're working on a website and there is no Dashboard access, and you want to determine the handle name
			    // if the handle name is not showing up, then the SCRIPT has been hardcoded (not enqueued the WordPress way)
			    if ( isset($_GET['wpacu_show_handle_names']) ) {
				    $scriptTag = str_replace( '<script ', '<script data-wpacu-debug-script-handle=\'' . $tagHandle . '\' ', $scriptTag );
			    }

			    if ( strpos( $scriptTag, 'data-wpacu-script-handle' ) === false && Main::instance()->isFrontendEditView ) {
				    $scriptTag = str_replace( '<script ', '<script data-wpacu-script-handle=\'' . $tagHandle . '\' ', $scriptTag );
			    }

			    if ( OptimizeCommon::preventAnyFrontendOptimization() ) {
				    /* [wpacu_timing] */ Misc::scriptExecTimer( $wpacuTimingName, 'end' ); /* [/wpacu_timing] */
				    return $scriptTag;
			    }

			    if ( strpos( $scriptTag, 'data-wpacu-script-handle' ) === false ) {
				    $scriptTag = str_replace( '<script ', '<script data-wpacu-script-handle=\'' . $tagHandle . '\' ', $scriptTag );
			    }

			    if ( $tagHandle === 'jquery-core' ) {
				    $scriptTag = str_replace( '<script ', '<script data-wpacu-jquery-core-handle=1 ', $scriptTag );
			    }

			    if ( $tagHandle === 'jquery-migrate' ) {
				    $scriptTag = str_replace( '<script ', '<script data-wpacu-jquery-migrate-handle=1 ', $scriptTag );
			    }

			    /* [wpacu_timing] */ Misc::scriptExecTimer( $wpacuTimingName, 'end' ); /* [/wpacu_timing] */
			    return $scriptTag;
		    }, PHP_INT_MAX, 2 );

		    if ( ! wpacuIsDefinedConstant('WPACU_NO_ASSETS_PRELOADED') ) {
                Preloads::instance()->initFront();
            }
	    }
	    /*
	     * [END] Front-end page visited (e.g. by the admin or a guest visitor)
	     */

        $this->alterWpStylesScriptsObj();
    }

    /**
	 * @return void
	 */
	public function triggersAfterInitFrontendView()
    {
        /*
           DO NOT disable the features below if the following apply:
           - The option is not enabled
           - Test Mode Enabled & Admin Logged in
           - The user is in the Dashboard (any changes are applied in the front-end view)
        */
	    if ( ! Main::instance()->preventAssetsSettings() ) {
		    if ( Main::instance()->settings['disable_emojis'] == 1 ) {
			    $wpacuCleanUp = new CleanUp();
			    $wpacuCleanUp->doDisableEmojis();
		    }

		    if ( Main::instance()->settings['disable_oembed'] == 1 ) {
			    $wpacuCleanUp = new CleanUp();
			    $wpacuCleanUp->doDisableOembed();
		    }
	    }
    }

	/**
	 * Priority: 8 (earliest)
	 */
	public function setVarsBeforeUpdate()
	{
		if ( Main::instance()->isFrontendEditView ) {
			$wpacuCleanUp = new CleanUp();
			$wpacuCleanUp->cleanUpHtmlOutputForAssetsCall();
		}

		Main::instance()->getCurrentPostId();

		define( 'WPACU_CURRENT_PAGE_ID', Main::instance()->getCurrentPostId() );
	}

	/**
	 * Priority: 10 (latest)
     *
     * @noinspection NestedAssignmentsUsageInspection
     */
	public function setVarsAfterAnyUpdate()
	{
        Main::instance()->globalUnloaded = Main::instance()->getGlobalUnload();

        // [wpacu_lite]
        if ( ! Main::instance()->isUpdateable ) {
            return;
        }
        // [/wpacu_lite]

        $getCurrentPost = Main::instance()->getCurrentPost();

        $post = $type = false;

        if (empty($getCurrentPost) && self::isHomePage()) {
            $type = 'front_page';
        } elseif ( ! empty($getCurrentPost) )  {
            $type = 'post';
            $post = $getCurrentPost;
            Main::instance()->postTypesUnloaded = ! empty($post->post_type)
                ? Main::instance()->getBulkUnload('post_type', $post->post_type)
                : array();
            }

        Main::$vars['for_type'] = $type;
        Main::$vars['current_post_id'] = Main::instance()->currentPostId;

        if ($post && $type === 'post' && isset($post->post_type) && $post->post_type) {
            Main::$vars['current_post_type'] = $post->post_type;
        }

        }

	/**
	 * In case there were assets enqueued within "wp_footer" action hook, instead of the standard "wp_enqueue_scripts"
	 */
	public function onPrintFooterScriptsStyles()
	{
		self::instance()->filterStyles();
		self::instance()->filterScripts();
	}

	/**
	 * This is useful to change via hooks the "src", "ver" or other values of the loaded handle
	 * Example: You have your theme's main style.css that is needed on every page
	 * On some pages, you only need 20% of it to load, and you can manually trim the other 80% (if you're sure you know which CSS is not used)
	 * You can use a filter hook such as 'wpacu_{main_theme_handle_name_here}_css_handle_obj' to filter the "src" of the object and load an alternative purified CSS file
	 */
	public function alterWpStylesScriptsObj()
	{
        if ( isset($_GET['wpacu_clean_load']) || isset($_GET['wpacu_load_original']) ) {
            return; // this is for debugging purposes, load the original sources
        }

		add_action('wp_print_styles', function() {
			global $wp_styles;

            $assetsToLoop = array();

            if ( ! empty($wp_styles->queue) ) {
                $assetsToLoop = $wp_styles->queue;
            } elseif ( ! empty($wp_styles->registered) ) {
                $assetsToLoop = array_keys($wp_styles->registered);
            }

			if ( ! empty($assetsToLoop) ) {
				foreach ($assetsToLoop as $assetHandle) {
                    if ( ! isset($wp_styles->registered[$assetHandle]) ) {
                        // They were in the queue, but not registered yet; Do not continue
                        continue;
                    }

					$wp_styles->registered[$assetHandle] = $this->maybeFilterAssetObject($wp_styles->registered[$assetHandle], 'css');
				}
			}
		}, 1);

		foreach (array('wp_print_scripts', 'wp_print_footer_scripts') as $actionToAdd) {
			add_action( $actionToAdd, function() {
				global $wp_scripts;

                $assetsToLoop = array();

                if ( ! empty($wp_scripts->queue) ) {
                    $assetsToLoop = $wp_scripts->queue;
                } elseif ( ! empty($wp_scripts->registered) ) {
                    $assetsToLoop = array_keys($wp_scripts->registered);
                }

				if ( ! empty($assetsToLoop) ) {
                    foreach ($assetsToLoop as $assetHandle) {
                        if ( ! isset($wp_scripts->registered[$assetHandle]) ) {
                            // It was in the queue, but not registered yet; Do not continue
                            continue;
                        }

                        $wp_scripts->registered[$assetHandle] = $this->maybeFilterAssetObject($wp_scripts->registered[$assetHandle], 'js');
                    }
				}
			}, 1);
		}
	}

	/**
	 * @param $object | as returned from $wp_styles or $wp_scripts
	 * @param $fileType | "css" or "js"
	 *
	 * @return mixed
	 */
	public function maybeFilterAssetObject($object, $fileType)
	{
		if ( ! isset($object->handle, $object->src) ) {
			return $object;
		}

        $object->handleRef = $object->handle;

        $refString = 'gt_widget_script_';

        // Special case (GTranslate plugin | 'gt_widget_script_' + random unique number added to it)
        if (strpos($object->handle, $refString) === 0) {
            $maybeRandNum = str_replace($refString, '', $object->handle);

            if (is_numeric($maybeRandNum)) {
                $object->handleRef = $refString . 'gtranslate';
            }
        }

		$filterTagName = 'wpacu_'.$object->handleRef.'_' . $fileType . '_handle_data';

        if ( has_filter($filterTagName) ) {
			$originData = (array)$object;
			$newData = apply_filters( $filterTagName, $originData );

			if ( isset($originData['src'], $newData['src']) && $newData['src'] !== $originData['src'] ) {
				$object->src = $newData['src'];
				$object->src_origin = $originData['src'];

				$object->ver = $newData['ver'] ?: null;
				$object->ver_origin = isset($originData['ver']) ? $originData['ver'] : null;
			}
		}

		return $object;
	}

	/**
	 * @param $ignoreChildParentList
	 *
	 * @return array
	 */
	public function filterIgnoreChildParentList($ignoreChildParentList)
	{
		if ( ! empty(Main::instance()->ignoreChildrenHandlesOnTheFly['styles']) ) {
			foreach (Main::instance()->ignoreChildrenHandlesOnTheFly['styles'] as $cssHandle) {
				$ignoreChildParentList['styles'][$cssHandle] = 1;
			}
		}

		if ( ! empty(Main::instance()->ignoreChildrenHandlesOnTheFly['scripts']) ) {
			foreach (Main::instance()->ignoreChildrenHandlesOnTheFly['scripts'] as $jsHandle) {
				$ignoreChildParentList['scripts'][$jsHandle] = 1;
			}
		}

		return $ignoreChildParentList;
	}

    /**
     * @return mixed|null
     * @noinspection PhpUndefinedVariableInspection
     */
    public static function buildUnloadList($assetType)
    {
        // [wpacu_lite]
        $nonAssetConfigPage = ! Main::instance()->isUpdateable && ! Misc::getShowOnFront();
        // [/wpacu_lite]

        /*
         * [All unloaded styles]
         */
        if ($assetType === 'styles') {
            $globalUnload = Main::instance()->globalUnloaded;

            // [wpacu_lite]
            if ( $nonAssetConfigPage && ! empty( $globalUnload['styles'] ) ) {
                $list = $globalUnload['styles'];
            } else {
            // [/wpacu_lite]

            // Post, Page, Front-page and more
            $toRemove = Main::instance()->getAssetsUnloadedPageLevel();

            $jsonList = @json_decode($toRemove);

            $list = array();

            if (isset($jsonList->styles)) {
                $list = (array)$jsonList->styles;
            }

            // Any global unloaded styles? Append them
            if ( ! empty($globalUnload['styles'])) {
                foreach ($globalUnload['styles'] as $handleStyle) {
                    $list[] = $handleStyle;
                }
            }

            if (MainFront::isSingularPage()) {
                // Any bulk unloaded styles (e.g. for all pages belonging to a post type)? Append them
                if (empty(Main::instance()->postTypesUnloaded)) {
                    $post                               = Main::instance()->getCurrentPost();
                    Main::instance()->postTypesUnloaded = (isset($post->post_type) && $post->post_type)
                        ? Main::instance()->getBulkUnload('post_type', $post->post_type)
                        : array();
                }

                if ( ! empty(Main::instance()->postTypesUnloaded['styles']) ) {
                    foreach (Main::instance()->postTypesUnloaded['styles'] as $handleStyle) {
                        $list[] = $handleStyle;
                    }
                }
            }

            // [wpacu_lite]
            }
            // [/wpacu_lite]

            // Site-Wide Unload for "Dashicons" if user is not logged-in
            if (Main::instance()->settings['disable_dashicons_for_guests'] && ! is_user_logged_in()) {
                $list[] = 'dashicons';
            }

            // Any bulk unloaded styles for 'category', 'post_tag' and more?
            // If the Pro version is enabled, any of the unloaded CSS will be added to the list
            $list = apply_filters('wpacu_filter_styles_list_unload', array_unique($list));

            }
        /*
         * [/All unloaded styles]
         */

        /*
         * [All unloaded scripts]
         */
        if ($assetType === 'scripts') {
            $globalUnload = Main::instance()->globalUnloaded;

            // [wpacu_lite]
            if ( $nonAssetConfigPage && ! empty( $globalUnload['styles'] ) ) {
                $list = $globalUnload['styles'];
            } else {
            // [/wpacu_lite]

            // Post, Page or Front-page?
            $toRemove = Main::instance()->getAssetsUnloadedPageLevel();

            $jsonList = @json_decode( $toRemove );

            $list = array();

            if ( isset( $jsonList->scripts ) ) {
                $list = (array) $jsonList->scripts;
            }

            // Any global unloaded styles? Append them
            if ( ! empty( $globalUnload['scripts'] ) ) {
                foreach ( $globalUnload['scripts'] as $handleScript ) {
                    $list[] = $handleScript;
                }
            }

            if ( MainFront::isSingularPage() ) {
                // Any bulk unloaded styles (e.g. for all pages belonging to a post type)? Append them
                if ( empty( Main::instance()->postTypesUnloaded ) ) {
                    $post = Main::instance()->getCurrentPost();

                    // Make sure the post_type is set; it's not in specific pages (e.g. BuddyPress ones)
                    Main::instance()->postTypesUnloaded = ( isset( $post->post_type ) && $post->post_type )
                        ? Main::instance()->getBulkUnload( 'post_type', $post->post_type )
                        : array();
                }

                if ( ! empty( Main::instance()->postTypesUnloaded['scripts'] ) ) {
                    foreach ( Main::instance()->postTypesUnloaded['scripts'] as $handleStyle ) {
                        $list[] = $handleStyle;
                    }
                }
            }

            // [wpacu_lite]
            }
            // [/wpacu_lite]

            // Any bulk unloaded styles for 'category', 'post_tag' and more?
            // These are PRO rules or rules added via custom coding
            $list = apply_filters( 'wpacu_filter_scripts_list_unload', array_unique( $list ) );

            global $wp_scripts;

            $allScripts = $wp_scripts;

            if ( $allScripts !== null && ! empty( $allScripts->registered ) ) {
                foreach ( $allScripts->registered as $handle => $value ) {
                    // This could be triggered several times, check if the script already exists
                    if ( ! isset( Main::instance()->wpAllScripts['registered'][ $handle ] ) ) {
                        Main::instance()->wpAllScripts['registered'][ $handle ] = $value;
                        if ( in_array( $handle, $allScripts->queue ) ) {
                            Main::instance()->wpAllScripts['queue'][] = $handle;
                        }
                    }
                }

                if ( ! empty( Main::instance()->wpAllScripts['queue'] ) ) {
                    Main::instance()->wpAllScripts['queue'] = array_unique( Main::instance()->wpAllScripts['queue'] );
                }
            }
        }
        /*
         * [/All unloaded scripts]
         */

        return $list;
    }

    /**
     * @param $assetType
     *
     * @return mixed|null
     */
    public static function buildLoadExceptionList($list, $assetType)
    {
        /*
         * [All load exception styles]
         */
        if ($assetType === 'styles') {
            // Load exception rules ALWAYS have priority over the unloading ones
            // Let's see if there are load exceptions for this page or site-wide (e.g. for logged-in users)
            // Only check for any load exceptions if the unloading list has at least one item
            // Otherwise the action is irrelevant since the assets are loaded anyway by default

            // These are common rules triggered in both LITE & PRO plugins
            $list = ! empty($list) ? Main::instance()->filterAssetsUnloadList($list, 'styles', 'load_exception') : $list;

            // These are pro rules OR rules added via custom coding
            $list = ! empty($list) ? apply_filters('wpacu_filter_styles_list_load_exception', $list) : $list;
        }
        /*
         * [/All load exception styles]
         */

        /*
         * [All load exception scripts]
         */
        if ($assetType === 'scripts') {
            // Load exception rules ALWAYS have priority over the unloading ones
            // Thus, if an exception is found, the handle will be removed from the unloading list
            // Let's see if there are load exceptions for this page or site-wide (e.g. for logged-in users)

            // These are common rules triggered in both LITE & PRO plugins
            $list = ! empty($list) ? Main::instance()->filterAssetsUnloadList($list, 'scripts', 'load_exception') : $list;

            // These are pro rules OR rules added via custom coding
            // Only check for any load exceptions if the unloading list has at least one item
            // Otherwise the action is irrelevant since the assets are loaded anyway by default
            $list = ! empty($list) ? apply_filters('wpacu_filter_scripts_list_load_exception', $list) : $list;
        }
        /*
         * [/All load exception scripts]
         */

        return $list;
    }

	/* [START] Styles Dequeue */
	/**
	 * See if there is any list with styles to be removed in JSON format
	 * Only the handles (the ID of the styles) is stored
	 */
	public function filterStyles()
	{
		/* [wpacu_timing] */ Misc::scriptExecTimer( 'filter_dequeue_styles' );/* [/wpacu_timing] */

		global $wp_styles;

		if (current_action() === 'wp_print_styles') {
			ObjectCache::wpacu_cache_set('wpacu_styles_object_after_wp_print_styles', $wp_styles);
		}

		$list = array();

		if (current_action() === 'wp_print_footer_scripts') {
			$cachedWpStyles = ObjectCache::wpacu_cache_get('wpacu_styles_object_after_wp_print_styles');
			if (isset($cachedWpStyles->registered) && count($cachedWpStyles->registered) === count($wp_styles->registered)) {
				// The list was already generated in "wp_print_styles" and the number of registered assets are the same
				// Save resources and do not re-generate it
				$list = ObjectCache::wpacu_cache_get('wpacu_styles_handles_marked_for_unload');
			}
		}

		if ( empty($list) || ! is_array($list) ) {
            // [wpacu_lite]
            $nonAssetConfigPage = ! Main::instance()->isUpdateable && ! Misc::getShowOnFront();
            // [/wpacu_lite]

			/*
			* [START] Build unload list
			*/
			$list = self::buildUnloadList('styles');
			/*
			* [END] Build unload list
			*/

			// Add handles such as the Oxygen Builder CSS ones that are missing and added differently to the queue
			$allStyles = $this->wpStylesFilter( $wp_styles, 'registered', $list );

			if ( $allStyles !== null && ! empty( $allStyles->registered ) ) {
				// Going through all the registered styles
				foreach ( $allStyles->registered as $handle => $value ) {
					// This could be triggered several times, check if the style already exists
					if ( ! isset( Main::instance()->wpAllStyles['registered'][ $handle ] ) ) {
						Main::instance()->wpAllStyles['registered'][ $handle ] = $value;
						if ( in_array( $handle, $allStyles->queue ) ) {
							Main::instance()->wpAllStyles['queue'][] = $handle;
						}
					}
				}

				if ( ! empty( Main::instance()->wpAllStyles['queue'] ) ) {
					Main::instance()->wpAllStyles['queue'] = array_unique( Main::instance()->wpAllStyles['queue'] );
				}
			}

			if ( ! empty( Main::instance()->wpAllStyles['registered'] ) ) {
				ObjectCache::wpacu_cache_set( 'wpacu_all_styles_handles', array_keys( Main::instance()->wpAllStyles['registered'] ) );
			}

			// e.g. for test/debug mode or AJAX calls (where all assets have to load)
			if ( isset($_REQUEST['wpacu_no_css_unload']) ) {
				/* [wpacu_timing] */Misc::scriptExecTimer( 'filter_dequeue_styles', 'end' ); /* [/wpacu_timing] */
				return;
			}

			if ( Main::instance()->preventAssetsSettings(array('assets_call')) ) {
				/* [wpacu_timing] */Misc::scriptExecTimer( 'filter_dequeue_styles', 'end' ); /* [/wpacu_timing] */
				return;
			}

			/*
			* [START] Load Exception Check
			* */
            $list = ! empty($list) ? self::buildLoadExceptionList($list, 'styles') : $list;
            /*
			 * [END] Load Exception Check
			 * */

			// Is $list still empty? Nothing to unload? Stop here
			if (empty($list)) {
				/* [wpacu_timing] */ Misc::scriptExecTimer( 'filter_dequeue_styles', 'end' ); /* [/wpacu_timing] */
				return;
			}
		}

		$ignoreChildParentList = apply_filters('wpacu_ignore_child_parent_list', Main::instance()->getIgnoreChildren());

		foreach ($list as $handle) {
			if (isset($ignoreChildParentList['styles'], Main::instance()->wpAllStyles['registered'][$handle]->src)
			    && is_array($ignoreChildParentList['styles']) && array_key_exists($handle, $ignoreChildParentList['styles'])) {
				// Do not dequeue it as it's "children" will also be dequeued (ignore rule is applied)
				// It will be stripped by cleaning its LINK tag from the HTML Source
				Main::instance()->ignoreChildren['styles'][$handle] = Main::instance()->wpAllStyles['registered'][$handle]->src;
				Main::instance()->ignoreChildren['styles'][$handle.'_has_unload_rule'] = 1;
				Main::instance()->allUnloadedAssets['styles'][] = $handle;
				continue;
			}

			$handle = trim($handle);

			// Ignore auto generated handles for the hardcoded CSS as they were added for reference purposes
			// They will get stripped later on via OptimizeCommon.php
			if (strncmp($handle, 'wpacu_hardcoded_link_', 21) === 0) {
				continue; // the handle is used just for reference for later stripping via altering the DOM
			}

			if (strncmp($handle, 'wpacu_hardcoded_style_', 22) === 0) {
				continue; // the handle is used just for reference for later stripping via altering the DOM
			}

			// Do not unload "dashicons" if the top WordPress admin bar is showing up
			if ($handle === 'dashicons' && is_admin_bar_showing()) {
				continue;
			}

			Main::instance()->allUnloadedAssets['styles'][] = $handle;

			// Only trigger the unloading on regular page load, not when the assets list is collected
			if ( ! Main::instance()->isGetAssetsCall ) {
				wp_deregister_style( $handle );
				wp_dequeue_style( $handle );
			}
		}

		if (current_action() === 'wp_print_styles') {
			ObjectCache::wpacu_cache_set( 'wpacu_styles_handles_marked_for_unload', $list );
		}

		/* [wpacu_timing] */ Misc::scriptExecTimer( 'filter_dequeue_styles', 'end' ); /* [/wpacu_timing] */
	}

	/**
	 * @param $wpStylesFilter
	 * @param string $listType
	 * @param array $unloadedList
	 *
	 * @return mixed
	 */
	public function wpStylesFilter($wpStylesFilter, $listType, $unloadedList = array())
	{
		global $wp_styles, $oxygen_vsb_css_styles;

		if ( ( $listType === 'registered' ) && is_object( $oxygen_vsb_css_styles ) && ! empty( $oxygen_vsb_css_styles->registered ) ) {
			$stylesSpecialCases = array();

			foreach ($oxygen_vsb_css_styles->registered as $oxygenHandle => $oxygenValue) {
				if (! array_key_exists($oxygenHandle, $wp_styles->registered)) {
					$wpStylesFilter->registered[$oxygenHandle] = $oxygenValue;
					$stylesSpecialCases[$oxygenHandle] = $oxygenValue->src;
				}
			}

			$unloadedSpecialCases = array();

			foreach ($unloadedList as $unloadedHandle) {
				if (array_key_exists($unloadedHandle, $stylesSpecialCases)) {
					$unloadedSpecialCases[$unloadedHandle] = $stylesSpecialCases[$unloadedHandle];
				}
			}

			if (! empty($unloadedSpecialCases)) {
				// This will be later used in 'wp_loaded' below to extract the special styles
				echo Main::$wpStylesSpecialDelimiters['start'] . wp_json_encode($unloadedSpecialCases) . Main::$wpStylesSpecialDelimiters['end'];
			}
		}

		if ( ( $listType === 'done' ) && isset( $oxygen_vsb_css_styles->done ) && is_object( $oxygen_vsb_css_styles ) ) {
			foreach ($oxygen_vsb_css_styles->done as $oxygenHandle) {
				if (! in_array($oxygenHandle, $wp_styles->done)) {
					$wpStylesFilter[] = $oxygenHandle;
				}
			}
		}

		if ( ( $listType === 'queue' ) && isset( $oxygen_vsb_css_styles->queue ) && is_object( $oxygen_vsb_css_styles ) ) {
			foreach ($oxygen_vsb_css_styles->queue as $oxygenHandle) {
				if (! in_array($oxygenHandle, $wp_styles->queue)) {
					$wpStylesFilter[] = $oxygenHandle;
				}
			}
		}

		return $wpStylesFilter;
	}

	/**
	 *
	 */
	public static function filterStylesSpecialCases()
	{
		if ( isset($_REQUEST['wpacu_no_css_unload']) ) {
			return;
		}

		add_action('wp_loaded', static function() {
			ob_start(static function($htmlSource) {
				if (strpos($htmlSource, Main::$wpStylesSpecialDelimiters['start']) === false && strpos($htmlSource, Main::$wpStylesSpecialDelimiters['end']) === false) {
					return $htmlSource;
				}

				$jsonStylesSpecialCases = Misc::extractBetween($htmlSource, Main::$wpStylesSpecialDelimiters['start'], Main::$wpStylesSpecialDelimiters['end']);

				$stylesSpecialCases = json_decode($jsonStylesSpecialCases, ARRAY_A);

				if ( ! empty($stylesSpecialCases) && wpacuJsonLastError() === JSON_ERROR_NONE) {
					foreach ($stylesSpecialCases as $styleSrc) {
						$styleLocalSrc = Misc::getLocalSrcIfExist($styleSrc);
						$styleRelSrc = isset($styleLocalSrc['rel_src']) ? $styleLocalSrc['rel_src'] : $styleSrc;
						$htmlSource = CleanUp::cleanLinkTagFromHtmlSource($styleRelSrc, $htmlSource);
					}

					// Strip the info HTML comment
					$htmlSource = str_replace(
						Main::$wpStylesSpecialDelimiters['start'] . $jsonStylesSpecialCases . Main::$wpStylesSpecialDelimiters['end'],
						'',
						$htmlSource
					);
				}

				return $htmlSource;
			});
		}, 1);
	}

	/**
	 *
	 */
	public function printAnySpecialCss()
	{
		if ( ! empty(Main::instance()->allUnloadedAssets['styles']) &&
		    in_array('photoswipe', Main::instance()->allUnloadedAssets['styles'])) {
			?>
			<?php if (Menu::userCanAccessAssetCleanUp()) { ?><!-- Asset CleanUp: "photoswipe" unloaded (avoid printing useless HTML) --><?php } ?>
			<style <?php echo Misc::getStyleTypeAttribute(); ?>>.pswp { display: none; }</style>
			<?php
		}
	}
	/* [END] Styles Dequeue */

	/* [START] Scripts Dequeue */
	/**
	 * See if there is any list with scripts to be removed in JSON format
	 * Only the handles (the ID of the scripts) are saved
	 */
	public function filterScripts()
	{
		/* [wpacu_timing] */ Misc::scriptExecTimer( 'filter_dequeue_scripts' );/* [/wpacu_timing] */

		global $wp_scripts;

		if (current_action() === 'wp_print_scripts') {
			ObjectCache::wpacu_cache_set('wpacu_scripts_object_after_wp_print_scripts', $wp_scripts);
		}

		$list = array();

		if (current_action() === 'wp_print_footer_scripts') {
			$cachedWpScripts = ObjectCache::wpacu_cache_get('wpacu_scripts_object_after_wp_print_scripts');
			if (isset($cachedWpScripts->registered) && count($cachedWpScripts->registered) === count($wp_scripts->registered)) {
				// The list was already generated in "wp_print_scripts" and the number of registered assets are the same
				// Save resources and do not re-generate it
				$list = ObjectCache::wpacu_cache_get('wpacu_scripts_handles_marked_for_unload');
			}
		}

		if ( empty($list) ) {
			/*
			* [START] Build unload list
			*/
            $list = self::buildUnloadList('scripts');
			/*
			* [END] Build unload list
			*/

			/*
			* [START] Load Exception Check
			* */
            $list = ! empty($list) ? self::buildLoadExceptionList($list, 'scripts') : $list;
            /*
			 * [END] Load Exception Check
			 * */

			// Nothing to unload
			if ( empty( $list ) ) {
				/* [wpacu_timing] */Misc::scriptExecTimer( 'filter_dequeue_scripts', 'end' ); /* [/wpacu_timing] */
				return;
			}

			// e.g. for test/debug mode or AJAX calls (where all assets have to load)
			if ( isset($_REQUEST['wpacu_no_js_unload']) || Main::instance()->preventAssetsSettings(array('assets_call')) ) {
				/* [wpacu_timing] */Misc::scriptExecTimer( 'filter_dequeue_scripts', 'end' ); /* [/wpacu_timing] */
				return;
			}
		}

		$ignoreChildParentList = apply_filters('wpacu_ignore_child_parent_list', Main::instance()->getIgnoreChildren());

		foreach ($list as $handle) {
			$handle = trim($handle);

			// Ignore auto generated handles for the hardcoded CSS as they were added for reference purposes
			// They will get stripped later on via OptimizeCommon.php
			// The handle is used just for reference for later stripping via altering the DOM
			if (strpos($handle, 'wpacu_hardcoded_script_inline_') !== false || strpos($handle, 'wpacu_hardcoded_noscript_inline_') !== false) {
				continue;
			}

			if (strpos($handle, 'wpacu_hardcoded_script_src_') !== false) {
				continue;
			}

			// Special Action for 'jquery-migrate' handler as it's tied to 'jquery'
			if ($handle === 'jquery-migrate' && isset(Main::instance()->wpAllScripts['registered']['jquery'])) {
				$jQueryRegScript = Main::instance()->wpAllScripts['registered']['jquery'];

				if (isset($jQueryRegScript->deps)) {
					$jQueryRegScript->deps = array_diff($jQueryRegScript->deps, array('jquery-migrate'));
				}

				if (wpacuIsPluginActive('jquery-updater/jquery-updater.php')) {
					wp_dequeue_script($handle);
				}
				continue;
			}

			if (isset($ignoreChildParentList['scripts'], Main::instance()->wpAllScripts['registered'][$handle]->src) && is_array($ignoreChildParentList['scripts']) && array_key_exists($handle, $ignoreChildParentList['scripts'])) {
				// Do not dequeue it as it's "children" will also be dequeued (ignore rule is applied)
				// It will be stripped by cleaning its SCRIPT tag from the HTML Source
				Main::instance()->ignoreChildren['scripts'][$handle] = Main::instance()->wpAllScripts['registered'][$handle]->src;
				Main::instance()->ignoreChildren['scripts'][$handle.'_has_unload_rule'] = 1;
				Main::instance()->allUnloadedAssets['scripts'][] = $handle;
				continue;
			}

			Main::instance()->allUnloadedAssets['scripts'][] = $handle;

			// Only trigger the unloading on regular page load, not when the assets list is collected
			if ( ! Main::instance()->isGetAssetsCall ) {
                $handle = Main::maybeGetOriginalNonUniqueHandleName($handle, 'scripts');

				wp_deregister_script( $handle );
				wp_dequeue_script( $handle );
			}
		}

		if (current_action() === 'wp_print_scripts') {
			ObjectCache::wpacu_cache_set( 'wpacu_scripts_handles_marked_for_unload', $list );
		}

		/* [wpacu_timing] */ Misc::scriptExecTimer( 'filter_dequeue_scripts', 'end' ); /* [/wpacu_timing] */
	}
	/* [END] Scripts Dequeue */

	/**
	 * @param $getForAssetsType ("styles", "scripts")
	 *
	 * @return array|array[]
	 */
	public function getSkipAssets($getForAssetsType)
	{
		if ( ! empty($this->skipAssets[$getForAssetsType]) ) {
			return $this->skipAssets[$getForAssetsType];
		}

		$ownScriptsIfAdminIsLoggedIn = Menu::userCanAccessAssetCleanUp() && AssetsManager::instance()->frontendShow()
			? OwnAssets::getOwnAssetsHandles( $getForAssetsType )
			: array();

		if ($getForAssetsType === 'styles') {
			$this->skipAssets[$getForAssetsType] = array_merge(
				array(
					'admin-bar',
					// The top admin bar
					'yoast-seo-adminbar',
					// Yoast "WordPress SEO" plugin
					'autoptimize-toolbar',
					'query-monitor',
					'wp-fastest-cache-toolbar',
					// WP Fastest Cache plugin toolbar CSS
					'litespeed-cache',
					// LiteSpeed toolbar
					'siteground-optimizer-combined-styles-header'
					// Combine CSS in SG Optimiser (irrelevant as it made from the combined handles)
				),
				// Own Scripts (for admin use only)
				$ownScriptsIfAdminIsLoggedIn
			);
		}

		if ($getForAssetsType === 'scripts') {
			$this->skipAssets[$getForAssetsType] = array_merge(
				array(
					'admin-bar',            // The top admin bar
					'autoptimize-toolbar',
					'query-monitor',
					'wpfc-toolbar'          // WP Fastest Cache plugin toolbar JS
				),
				// Own Scripts (for admin use only)
				$ownScriptsIfAdminIsLoggedIn
			);
		}

		return $this->skipAssets[$getForAssetsType];
	}

    /**
     * @return bool
     */
    public static function isSingularPage()
    {
        return Main::$vars['is_woo_shop_page'] || is_singular() || self::isBlogPage();
    }

    /**
     * @return bool
     */
    public static function isAnyTaxPage()
    {
        return is_category() || is_tag() || is_tax();
    }

    /**
     * @return bool
     */
    public static function isBlogPage()
    {
        return is_home() && ! is_front_page();
    }

    /**
     * @return bool
     */
    public static function isHomePage()
    {
        // Docs: https://codex.wordpress.org/Conditional_Tags

        // Elementor's Maintenance Mode is ON
        if (defined('WPACU_IS_ELEMENTOR_MAINTENANCE_MODE_TEMPLATE_ID')) {
            return false;
        }

        // "Your latest posts" -> sometimes it works as is_front_page(), sometimes as is_home())
        // "A static page (select below)" -> In this case is_front_page() should work

        // Sometimes neither of these two options are selected
        // (it happens with some themes that have an incorporated page builder)
        // and is_home() tends to work fine

        // Both will be used to be sure the home page is detected

        // VARIOUS SCENARIOS for "Your homepage displays" option from Settings -> Reading

        // 1) "Your latest posts" is selected
        if (Misc::getShowOnFront() === 'posts' && is_front_page()) {
            // Default homepage
            return true;
        }

        // 2) "A static page (select below)" is selected

        // Note: Either "Homepage:" or "Posts page:" need to have a value set
        // Otherwise, it will default to "Your latest posts", the other choice from "Your homepage displays"

        if (Misc::getShowOnFront() === 'page') {
            $pageOnFront  = get_option('page_on_front');
            $pageForPosts = get_option('page_for_posts');

            // "Homepage:" has a value
            if ($pageOnFront > 0 && is_front_page()) {
                // Static Homepage
                return true;
            }

            // "Homepage:" has no value
            if ( ! $pageOnFront && self::isBlogPage()) {
                // Blog page
                return true;
            }

            // Both have values
            if ($pageOnFront && $pageForPosts && ($pageOnFront !== $pageForPosts) && self::isBlogPage()) {
                return false; // Blog posts page (but not home page)
            }

            // Another scenario is when both 'Homepage:' and 'Posts page:' have values
            // If we are on the blog page (which is "Posts page:" value), then it will return false
            // As it's not the main page of the website
            // e.g. Main page: www.yoursite.com - Blog page: www.yoursite.com/blog/
        }

        // Some WordPress themes such as "Extra" have their own custom value
        return (Misc::getShowOnFront() !== '' || Misc::getShowOnFront() === 'layout')
               &&
               ((is_home() || self::isBlogPage()) || self::isRootUrl());
    }

    /**
     * @return bool
     */
    public static function isRootUrl()
    {
        $siteUrl = get_bloginfo('url');

        $urlPath = (string)parse_url($siteUrl, PHP_URL_PATH);

        $requestURI = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        $urlPathNoForwardSlash    = $urlPath;
        $requestURINoForwardSlash = $requestURI;

        if ($urlPath && substr($urlPath, -1) === '/') {
            $urlPathNoForwardSlash = substr($urlPath, 0, -1);
        }

        if ($requestURI && substr($requestURI, -1) === '/') {
            $requestURINoForwardSlash = substr($requestURI, 0, -1);
        }

        return $urlPathNoForwardSlash === $requestURINoForwardSlash;
    }
}
