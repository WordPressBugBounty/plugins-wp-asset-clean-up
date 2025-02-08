<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\AssetsManager;
use WpAssetCleanUp\HardcodedAssets;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\MainFront;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\ObjectCache;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;
use WpAssetCleanUp\Preloads;
use WpAssetCleanUp\Settings;
use WpAssetCleanUp\Update;

/**
 * Class MainAdmin
 *
 * This class has functions that are only for the admin's concern
 *
 * @package WpAssetCleanUp
 */
class MainAdmin
{
	/**
	 * @var MainAdmin|null
	 */
	private static $singleton;

	/**
	 * @return null|MainAdmin
	 */
	public static function instance()
    {
		if ( self::$singleton === null ) {
			self::$singleton = new self();
		}

		return self::$singleton;
	}

    /**
     * Admin notices at the top of the Dashboard could be for annoucements, to ask the admin to allow analytics
     * Only show one notice at a time, never both to avoid annoying the admin
     * This will work only if both notices are shown the 'regular' way (no AJAX), otherwise, other measure were taken through the JavaScript code
     *
     * @var bool
     */
    private static $topAdminNoticeDisplayed = false;

    /**
     * For these handles, it's strongly recommended to use 'Ignore dependency rule and keep the "children" loaded'
     * if any of them are unloaded in any page
     *
     * @var string[][]
     */
    public $keepChildrenLoadedForHandles = array(
        'css' => array(
            'elementor-icons'
        ),
        'js'  => array(
            'swiper',
            'elementor-waypoints',
            'share-link'
        )
    );

	/**
	 * Parser constructor.
	 */
	public function __construct()
    {
	    add_action( 'admin_footer', array( $this, 'adminFooter' ) );
	    add_action( 'wp_ajax_' . WPACU_PLUGIN_ID . '_fetch_active_plugins_icons', array( $this, 'ajaxFetchActivePluginsIconsFromWordPressOrg' ) );

	    // This is triggered AFTER "saveSettings" from 'Settings' class
	    // In case the settings were just updated, the script will get the latest values
	    add_action( 'init', array( $this, 'triggersAfterInit' ), 11);

        if (Main::instance()->isGetAssetsCall) {
            $currentTheme = strtolower(wp_get_theme());
            $noRocketInit = true;

            if (strpos($currentTheme, 'uncode') !== false) {
                $noRocketInit = false; // make exception for the "Uncode" Theme as it doesn't check if the get_rocket_option() function exists
            }

            if ($noRocketInit) {
                add_filter('rocket_cache_reject_uri', function($urls) {
                    $urls[] = '/?wpassetcleanup_load=1';
                    return $urls;
                });
                }

            // Do not output Query Monitor's information as it's irrelevant in this context
            if ( class_exists( '\QueryMonitor' ) && class_exists( '\QM_Plugin' ) ) {
                add_filter( 'user_has_cap', static function( $userCaps ) {
                    $userCaps['view_query_monitor'] = false;
                    return $userCaps;
                } );
            }

            add_filter( 'style_loader_tag', static function( $styleTag, $tagHandle ) {
                // This is used to determine if the LINK is enqueued later on
                // If the handle name is not showing up, then the LINK stylesheet has been hardcoded (not enqueued the WordPress way)
                return str_replace( '<link ', '<link data-wpacu-style-handle=\'' . $tagHandle . '\' ', $styleTag );
            }, PHP_INT_MAX, 2 ); // Trigger it later in case plugins such as "Ronneby Core" plugin alters it

            add_filter( 'script_loader_tag', static function( $scriptTag, $tagHandle ) {
                // This is used to determine if the SCRIPT is enqueued later on
                // If the handle name is not showing up, then the SCRIPT has been hardcoded (not enqueued the WordPress way)
                $reps = array( '<script ' => '<script data-wpacu-script-handle=\'' . $tagHandle . '\' ' );

                return str_replace( array_keys( $reps ), array_values( $reps ), $scriptTag );
            }, PHP_INT_MAX, 2 );

            add_filter( 'show_admin_bar', '__return_false' );
        }

	    $this->wpacuHtmlNoticeForAdmin();

        // Fix: When using Query Monitor, the "Update" button from the CSS/JS manager was showing up on top of the bottom Query Monitor data
        if (Menu::isPluginPage() === 'assets_manager' && wpacuIsPluginActive('query-monitor/query-monitor.php')) {
            add_action('admin_head', static function() {
                ?>
                <style>
                    #query-monitor-main {
                        z-index: 1000000 !important;
                    }
                </style>
                <?php
            });
        }
    }

    /**
     * This is to avoid showing two notices such as "tracking" and "reviewing", to avoid annoying the admin
     *
     * Check if a notice has been displayed
     *
     * @return bool
     */
    public static function isTopAdminNoticeDisplayed()
    {
        return self::$topAdminNoticeDisplayed;
    }

    /**
     * Mark that a notice has been displayed
     *
     * @return void
     */
    public static function setTopAdminNoticeDisplayed()
    {
        self::$topAdminNoticeDisplayed = true;
    }

    /**
     * @param $postType
     *
     * @return array
     */
    public static function getAllSetTaxonomies($postType)
    {
        if ( ! $postType) {
            return array();
        }

        $postTaxonomies = get_object_taxonomies($postType);

        if ($postType === 'post') {
            $postFormatKey = array_search('post_format', $postTaxonomies);

            if ($postFormatKey !== false) {
                unset($postTaxonomies[$postFormatKey]);
            }
        }

        if (empty($postTaxonomies)) {
            // There are no taxonomies associate with the $postType or $postType is not valid
            return array();
        }

        $allPostTypeTaxonomyTerms = get_terms(array(
            'taxonomy'   => $postTaxonomies,
            'hide_empty' => true,
        ));

        $finalList = array();

        foreach ($allPostTypeTaxonomyTerms as $obj) {
            $taxonomyObj = get_taxonomy($obj->taxonomy);

            if ( ! $taxonomyObj->show_ui) {
                continue;
            }
            $finalList[$taxonomyObj->label][] = (array)$obj;
        }

        if ( ! empty($finalList)) {
            foreach (array_keys($finalList) as $taxonomyLabel) {
                usort($finalList[$taxonomyLabel], static function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
            }

            ksort($finalList);
        }

        return $finalList;
    }

    /**
	 * @return void
	 */
	public function ajaxFetchActivePluginsIconsFromWordPressOrg()
	{
		if ( ! isset($_POST['wpacu_nonce']) ) {
			echo 'Error: The security nonce was not sent for verification. Location: '.__METHOD__;
			return;
		}

		if ( ! wp_verify_nonce($_POST['wpacu_nonce'], 'wpacu_fetch_active_plugins_icons') ) {
			echo 'Error: The security check has failed. Location: '.__METHOD__;
			return;
		}

		if (! isset($_POST['action']) || ! Menu::userCanAccessAssetCleanUp()) {
			return;
		}

		echo 'POST DATA: '.print_r($_POST, true)."\n\n";

		echo '- Downloading from WordPress.org'."\n\n";

		$activePluginsIcons = MiscAdmin::fetchActiveFreePluginsIconsFromWordPressOrg();

		if (is_array($activePluginsIcons) && ! empty($activePluginsIcons)) {
			echo print_r($activePluginsIcons, true)."\n";
			exit;
		}
	}

    /**
     * @return void
     */
    public function adminFooter()
    {
        // Only trigger it within the Dashboard when an Asset CleanUp (Pro) page is accessed and the transient is non-existent or expired
        $this->ajaxFetchActivePluginsJsFooterCode();
    }

	/**
	 *
	 */
	public function ajaxFetchActivePluginsJsFooterCode()
	{
		if (! Menu::isPluginPage() || ! Menu::userCanAccessAssetCleanUp()) {
			return;
		}

		$forcePluginIconsDownload = isset($_GET['wpacu_force_plugin_icons_fetch']);

		$triggerPluginIconsDownload = $forcePluginIconsDownload || ! get_transient(WPACU_PLUGIN_ID . '_active_plugins_icons');

		if (! $triggerPluginIconsDownload) {
			return;
		}
		?>
		<script type="text/javascript" >
            jQuery(document).ready(function($) {
                let wpacuDataToSend = {
                    'action': '<?php echo WPACU_PLUGIN_ID.'_fetch_active_plugins_icons'; ?>',
                    'wpacu_nonce': '<?php echo wp_create_nonce('wpacu_fetch_active_plugins_icons'); ?>'
                };

                $.post(ajaxurl, wpacuDataToSend, function(response) {
                    console.log(response);
                });
            });
		</script>
		<?php
	}

	/**
	 *
	 */
	public function triggersAfterInit()
	{
        // Fetch the page in the background to see what scripts/styles are already loading
        // This applies only for front-end loading
        if ( Main::instance()->isGetAssetsCall || Main::instance()->isFrontendEditView ) {
            if ( Main::instance()->isGetAssetsCall ) {
                add_filter( 'show_admin_bar', '__return_false' );
            }

            // Save CSS handles list that is printed in the <HEAD>
            // No room for errors, some developers might enqueue (although not ideal) assets via "wp_head" or "wp_print_styles"/"wp_print_scripts"
            add_action( 'wp_enqueue_scripts', array( $this, 'saveHeadAssets' ), PHP_INT_MAX - 1 );

            // Save CSS/JS list that is printed in the <BODY>
            add_action( 'wp_print_footer_scripts', array( $this, 'saveFooterAssets' ), 100000000 );
            add_action( 'wp_footer', array( $this, 'printScriptsStyles' ), ( PHP_INT_MAX - 1 ) );
        }

        $metaboxes = new MetaBoxes;

        // Do not load the meta box nor do any AJAX calls
        // if the asset management is not enabled for the Dashboard
        if ( Main::instance()->settings['dashboard_show'] == 1 ) {
            // Send an AJAX request to get the list of loaded scripts and styles and print it nicely
            add_action( 'wp_ajax_' . WPACU_PLUGIN_ID . '_get_loaded_assets', array( $this, 'ajaxGetJsonListCallback' ) );

            // This is valid when the Gutenberg editor (not via "Classic Editor" plugin) is used and the user used the following option:
            // "Do not load Asset CleanUp Pro on this page (this will disable any functionality of the plugin)"
            add_action( 'wp_ajax_' . WPACU_PLUGIN_ID . '_load_page_restricted_area', array( $this, 'ajaxLoadRestrictedPageAreaCallback' ) );
        }

        // If assets management within the Dashboard is not enabled, an explanation message will be shown within the box unless the meta box is hidden completely
        if ( Main::instance()->settings['show_assets_meta_box'] ) {
            $metaboxes->initMetaBox( 'manage_page_assets' );
        }

        }

    /**
     *
     */
    public static function initBufferingForAjaxCallFromTheDashboard()
    {
        ob_start();

        add_action('shutdown', static function() {
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

            $anyHardCodedAssets = HardcodedAssets::getAll($htmlSource, false); // Fetch all for this type of request
            if ( ! empty($anyHardCodedAssets) && Main::instance()->isAjaxCall ) {
                HardcodedAssets::attachExternalHardcodedAssetsUrlsToCurrentExternalUrlsList($anyHardCodedAssets);
            }

            $reps = array('{wpacu_hardcoded_assets}' => base64_encode( wp_json_encode( $anyHardCodedAssets ) ));

            if ( isset($_GET['wpacu_print']) ) {
                $anyHardCodedAssetsPrinted = print_r($anyHardCodedAssets, true);
                $reps['{wpacu_hardcoded_assets_printed}'] = $anyHardCodedAssetsPrinted;
            }

            echo str_replace(array_keys($reps), array_values($reps), $htmlSource);
        }, 0);
    }

    /**
     * @return bool
     */
    public static function useBufferingForEditFrontEndView()
    {
        // The logged-in admin needs to be outside the Dashboard (in the front-end view)
        // "Manage in the Front-end" is enabled in "Settings" -> "Plugin Usage Preferences"
        return ! is_admin() && ! Main::instance()->isGetAssetsCall && Menu::userCanAccessAssetCleanUp() && AssetsManager::instance()->frontendShow();
    }

    /**
     *
     */
    public static function initBufferingForFrontendManagement()
    {
        // Used to print the hardcoded CSS/JS
        ob_start();

        add_action('shutdown', static function() {
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

            echo OptimizeCommon::alterHtmlSource($htmlSource);

            }, 0);
    }

    /**
     * @param $allAssets
     *
     * @return array
     * @noinspection NestedAssignmentsUsageInspection
     */
    public function getAllDeps($allAssets)
    {
        $allDepsParentToChild = $allDepsChildToParent = array('styles' => array(), 'scripts' => array());

        foreach (array('styles', 'scripts') as $assetType) {
            if ( empty( $allAssets[ $assetType ] ) ) {
                continue;
            }

            foreach ($allAssets[$assetType] as $assetObj) {
                if (! empty($assetObj->deps)) {
                    foreach ($assetObj->deps as $dep) {
                        $allDepsParentToChild[$assetType][$dep][] = $assetObj->handle;
                        $allDepsChildToParent[$assetType][$assetObj->handle][] = $dep;
                    }
                }
            }
        }

        return array(
            'parent_to_child'      => $allDepsParentToChild,
            'child_to_parent'      => $allDepsChildToParent
        );
    }

    /**
     * @param $data
     * @param bool $alterPosition
     *
     * @return mixed
     */
    public function alterAssetObj($data, $alterPosition = true)
    {
        if (! empty($data['all']['styles'])) {
            $data['core_styles_loaded'] = false;

            foreach ($data['all']['styles'] as $obj) {
                if ( ! isset($obj->handleOriginal) ) {
                    $handleMaybeAliases  = Main::maybeAssignUniqueHandleName($obj->handle, 'styles');
                    $obj->handle         = $handleMaybeAliases['handle_ref'];
                    $obj->handleOriginal = $handleMaybeAliases['handle_original'];
                }

                if (! isset($obj->handle)) {
                    unset($data['all']['styles']['']);
                    continue;
                }

                // From WordPress directories (false by default, unless it was set to true before: in Sorting.php for instance)
                if (! isset($obj->wp)) {
                    $obj->wp = false;
                }

                if ($alterPosition) {
                    if ( in_array( $obj->handle, Main::instance()->assetsInFooter['styles'] ) ) {
                        $obj->position = 'body';
                    } else {
                        $obj->position = 'head';
                    }
                }

                if (Sorting::matchesWpCoreCriteria($obj, 'styles')) {
                    $obj->wp                    = true;
                    $data['core_styles_loaded'] = true;
                }

                if (isset($obj->src) && $obj->src) {
                    $localSrc = Misc::getLocalSrcIfExist($obj->src);

                    if (! empty($localSrc)) {
                        $obj->baseUrl = $localSrc['base_url'];
                    }

                    $obj->srcHref = Misc::getHrefFromSource($obj->src);

                    $obj->size     = AssetsManager::getAssetSize($obj);
                    $obj->size_raw = AssetsManager::getAssetSize($obj, 'raw');
                }
            }
        }

        if (! empty($data['all']['scripts'])) {
            $data['core_scripts_loaded'] = false;

            foreach ($data['all']['scripts'] as $obj) {
                if ( ! isset($obj->handleOriginal) ) {
                    $handleMaybeAliases  = Main::maybeAssignUniqueHandleName($obj->handle, 'scripts');
                    $obj->handle         = $handleMaybeAliases['handle_ref'];
                    $obj->handleOriginal = $handleMaybeAliases['handle_original'];
                }

                if (! isset($obj->handle)) {
                    unset($data['all']['scripts']['']);
                    continue;
                }

                // From WordPress directories (false by default, unless it was set to true before: in Sorting.php for instance)
                if (! isset($obj->wp)) {
                    $obj->wp = false;
                }

                if ($alterPosition) {
                    $initialScriptPos = ObjectCache::wpacu_cache_get( $obj->handle, 'wpacu_scripts_initial_positions' );

                    if ( $initialScriptPos === 'body' || in_array( $obj->handle, Main::instance()->assetsInFooter['scripts'] ) ) {
                        $obj->position = 'body';
                    } else {
                        $obj->position = 'head';
                    }
                }

                if (isset($obj->src) && $obj->src) {
                    $localSrc = Misc::getLocalSrcIfExist($obj->src);

                    if (! empty($localSrc)) {
                        $obj->baseUrl = $localSrc['base_url'];

                        if (Sorting::matchesWpCoreCriteria($obj, 'scripts')) {
                            $obj->wp                     = true;
                            $data['core_scripts_loaded'] = true;
                        }
                    }

                    $obj->srcHref = Misc::getHrefFromSource($obj->src);
                }

                if (in_array($obj->handle,  array('jquery', 'jquery-core', 'jquery-migrate'))) {
                    $obj->wp                     = true;
                    $data['core_scripts_loaded'] = true;
                }

                $obj->size     = AssetsManager::getAssetSize($obj);
                $obj->size_raw = AssetsManager::getAssetSize($obj, 'raw');
            }
        }

        return $data;
    }

    /**
     *
     */
    public function saveHeadAssets()
    {
        global $wp_styles, $wp_scripts;

        if ( ! empty(Main::instance()->wpAllStyles['queue']) ) {
            Main::instance()->stylesInHead = Main::instance()->wpAllStyles['queue'];
        }

        if ( ! empty($wp_styles->queue) ) {
            foreach ($wp_styles->queue as $styleHandle) {
                Main::instance()->stylesInHead[] = $styleHandle;
            }
        }

        Main::instance()->stylesInHead = array_unique(Main::instance()->stylesInHead);

        if (isset(Main::instance()->wpAllScripts['queue']) && ! empty(Main::instance()->wpAllScripts['queue'])) {
            Main::instance()->scriptsInHead = Main::instance()->wpAllScripts['queue'];
        }

        if ( ! empty($wp_scripts->queue) ) {
            foreach ($wp_scripts->queue as $scriptHandle) {
                Main::instance()->scriptsInHead[] = $scriptHandle;
            }
        }

        Main::instance()->scriptsInHead = array_unique(Main::instance()->scriptsInHead);

        }

    /**
     *
     */
    public function saveFooterAssets()
    {
        global $wp_scripts, $wp_styles;

        // [Styles Collection]
        $footerStyles = array();

        if ( ! empty(Main::instance()->wpAllStyles['queue']) ) {
            foreach ( Main::instance()->wpAllStyles['queue'] as $handle ) {
                if ( ! in_array( $handle, Main::instance()->stylesInHead ) ) {
                    $footerStyles[] = $handle;
                }
            }
        }

        if ( ! empty($wp_styles->queue) ) {
            foreach ( $wp_styles->queue as $handle ) {
                if ( ! in_array( $handle, Main::instance()->stylesInHead ) ) {
                    $footerStyles[] = $handle;
                }
            }
        }

        Main::instance()->assetsInFooter['styles'] = array_unique($footerStyles);
        // [/Styles Collection]

        // [Scripts Collection]
        Main::instance()->assetsInFooter['scripts'] = ! empty($wp_scripts->in_footer) ? $wp_scripts->in_footer : array();

        if ( ! empty(Main::instance()->wpAllScripts['queue']) ) {
            foreach ( Main::instance()->wpAllScripts['queue'] as $handle ) {
                if ( ! in_array( $handle, Main::instance()->scriptsInHead ) ) {
                    Main::instance()->assetsInFooter['scripts'][] = $handle;
                }
            }
        }

        if ( ! empty($wp_scripts->queue) ) {
            foreach ( $wp_scripts->queue as $handle ) {
                if ( ! in_array( $handle, Main::instance()->scriptsInHead ) ) {
                    Main::instance()->assetsInFooter['scripts'][] = $handle;
                }
            }
        }

        Main::instance()->assetsInFooter['scripts'] = array_unique(Main::instance()->assetsInFooter['scripts']);
        // [/Scripts Collection]

        }

    /**
     * This output will be extracted and the JSON will be processed
     * in the WP Dashboard when editing a post
     *
     * It will also print the asset list in the front-end
     * if the option was enabled in the Settings
     * @noinspection NestedAssignmentsUsageInspection
     */
    public function printScriptsStyles()
    {
        // Not for WordPress AJAX calls
        if (Main::$domGetType === 'direct' && defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        $isFrontEndEditView = Main::instance()->isFrontendEditView;
        $isDashboardEditView = (! $isFrontEndEditView && Main::instance()->isGetAssetsCall);

        if (! $isFrontEndEditView && ! $isDashboardEditView) {
            return;
        }

        if ($isFrontEndEditView && isset($_GET['elementor-preview']) && $_GET['elementor-preview']) {
            return;
        }

        /* [wpacu_timing] */ $wpacuTimingName = 'output_css_js_manager'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */

        // Prevent plugins from altering the DOM
        add_filter('w3tc_minify_enable', '__return_false');

        Misc::w3TotalCacheFlushObjectCache();

        // This is the list of the scripts and styles that were eventually loaded
        // We have also the list of the ones that were unloaded
        // located in Main::instance()->wpScripts and Main::instance()->wpStyles
        // We will add it to the list as they will be marked

        $stylesBeforeUnload = Main::instance()->wpAllStyles;
        $scriptsBeforeUnload = Main::instance()->wpAllScripts;

        global $wp_scripts, $wp_styles;

        $list = array();

        // e.g. for "Loaded" and "Unloaded" statuses
        $currentUnloadedAll = isset(Main::instance()->allUnloadedAssets)
            ? Main::instance()->allUnloadedAssets
            : array('styles' => array(), 'scripts' => array());

        foreach (array('styles', 'scripts') as $assetType) {
            if ( isset( $currentUnloadedAll[$assetType] ) ) {
                $currentUnloadedAll[$assetType] = array_unique( $currentUnloadedAll[$assetType] );
            }
        }

        $manageStylesCore = isset($wp_styles->done) && is_array($wp_styles->done) ? $wp_styles->done : array();
        $manageStyles     = MainFront::instance()->wpStylesFilter($manageStylesCore, 'done');

        $manageScripts    = isset($wp_scripts->done) && is_array($wp_scripts->done) ? $wp_scripts->done : array();

        if ($isFrontEndEditView) {
            if ( ! empty(Main::instance()->wpAllStyles['queue']) ) {
                $manageStyles = MainFront::instance()->wpStylesFilter(Main::instance()->wpAllStyles['queue'], 'queue');
            }

            if ( !  empty(Main::instance()->wpAllScripts['queue']) ) {
                $manageScripts = Main::instance()->wpAllScripts['queue'];
            }

            if ( ! empty($currentUnloadedAll['styles']) ) {
                foreach ( $currentUnloadedAll['styles'] as $currentUnloadedStyleHandle ) {
                    if ( ! in_array( $currentUnloadedStyleHandle, $manageStyles ) ) {
                        $manageStyles[] = $currentUnloadedStyleHandle;
                    }
                }
            }

            if ( ! empty($manageStylesCore) ) {
                foreach ($manageStylesCore as $wpDoneStyle) {
                    if ( ! in_array( $wpDoneStyle, $manageStyles ) ) {
                        $manageStyles[] = $wpDoneStyle;
                    }
                }
            }

            $manageStyles = array_unique($manageStyles);

            if ( ! empty($currentUnloadedAll['scripts']) ) {
                foreach ( $currentUnloadedAll['scripts'] as $currentUnloadedScriptHandle ) {
                    if ( ! in_array( $currentUnloadedScriptHandle, $manageScripts ) ) {
                        $manageScripts[] = $currentUnloadedScriptHandle;
                    }
                }
            }

            if ( ! empty($wp_scripts->done) ) {
                foreach ($wp_scripts->done as $wpDoneScript) {
                    if ( ! in_array( $wpDoneScript, $manageScripts ) ) {
                        $manageScripts[] = $wpDoneScript;
                    }
                }
            }

            $manageScripts = array_unique($manageScripts);
        }

        /*
         * Style List
         */
        if ($isFrontEndEditView) { // "Manage in the Front-end"
            $stylesList = $stylesBeforeUnload['registered'];
        } else { // "Manage in the Dashboard"
            $stylesListFilterAll = MainFront::instance()->wpStylesFilter($wp_styles, 'registered');
            $stylesList = $stylesListFilterAll->registered;
        }

        if (! empty($stylesList)) {
            foreach ($manageStyles as $handle) {
                if (! isset($stylesList[$handle]) || in_array($handle, MainFront::instance()->getSkipAssets('styles'))) {
                    continue;
                }

                $list['styles'][] = $stylesList[$handle];
            }

            // Append unloaded ones (if any)
            if (! empty($stylesBeforeUnload) && ! empty($currentUnloadedAll['styles'])) {
                foreach ($currentUnloadedAll['styles'] as $sbuHandle) {
                    if (! in_array($sbuHandle, $manageStyles)) {
                        // Could be an old style that is not loaded anymore
                        // We have to check that
                        if (! isset($stylesBeforeUnload['registered'][$sbuHandle])) {
                            continue;
                        }

                        $sbuValue = $stylesBeforeUnload['registered'][$sbuHandle];
                        $list['styles'][] = $sbuValue;
                    }
                }
            }

            ksort($list['styles']);
        }

        /*
        * Scripts List
        */
        $scriptsList = $wp_scripts->registered;

        if ($isFrontEndEditView) {
            $scriptsList = $scriptsBeforeUnload['registered'];
        }

        if (! empty($scriptsList)) {
            /* These scripts below are used by this plugin (except admin-bar) and they should not show in the list
               as they are loaded only when you (or other admin) manage the assets, never for your website visitors */
            foreach ($manageScripts as $handle) {
                if (! isset($scriptsList[$handle]) || in_array($handle, MainFront::instance()->getSkipAssets('scripts'))) {
                    continue;
                }

                $list['scripts'][] = $scriptsList[$handle];
            }

            // Append unloaded ones (if any)
            if (! empty($scriptsBeforeUnload) && ! empty($currentUnloadedAll['scripts'])) {
                foreach ($currentUnloadedAll['scripts'] as $sbuHandle) {
                    if (! in_array($sbuHandle, $manageScripts)) {
                        // Could be an old script that is not loaded anymore
                        // We have to check that
                        if (! isset($scriptsBeforeUnload['registered'][$sbuHandle])) {
                            continue;
                        }

                        $sbuValue = $scriptsBeforeUnload['registered'][$sbuHandle];

                        $list['scripts'][] = $sbuValue;
                    }
                }
            }

            ksort($list['scripts']);

            }

        if (! empty($list)) {
            Update::updateHandlesInfo( $list );
        }

        // [wpacu_lite]
        if ( $isFrontEndEditView && ! Main::instance()->isUpdateable ) {
            $this->parseTemplate('__lite/settings-frontend-lite-locked', array(), true);
            /* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */
            return;
        }
        // [/wpacu_lite]

        // Front-end View while admin is logged in
        if ($isFrontEndEditView) {
            $wpacuSettings = new Settings();

            $data = array(
                'is_frontend_view'            => true,
                'post_type'                   => '',
                'bulk_unloaded'               => array( 'post_type' => array() ),
                'plugin_settings'             => $wpacuSettings->getAll(),
                'current_unloaded_all'        => $currentUnloadedAll,
                'current_unloaded_page_level' => Main::instance()->getAssetsUnloadedPageLevel( Main::instance()->getCurrentPostId(), true )
            );

            $data['wpacu_frontend_assets_manager_just_updated'] = false;

            if (isset($_GET['wpacu_time'], $_GET['nocache']) && get_transient(WPACU_PLUGIN_ID . '_frontend_assets_manager_just_updated')) {
                $data['wpacu_frontend_assets_manager_just_updated'] = true;
                delete_transient(WPACU_PLUGIN_ID . '_frontend_assets_manager_just_updated');
            }

            if ($currentDebug = ObjectCache::wpacu_cache_get('wpacu_assets_unloaded_list_page_request')) {
                foreach ( array( 'styles', 'scripts' ) as $assetType ) {
                    if ( ! empty( $data['current_unloaded_all'][ $assetType ] ) ) {
                        foreach ( $data['current_unloaded_all'][ $assetType ] as $handleKey => $handle ) {
                            if ( isset( $currentDebug[ $assetType ] ) && in_array( $handle, $currentDebug[ $assetType ] ) ) {
                                unset( $data['current_unloaded_all'][ $assetType ][ $handleKey ] );
                            }
                        }
                    }
                }
            }

            // e.g. /?wpacu_unload_(css|js)=
            $data['current_debug'] = ObjectCache::wpacu_cache_get('wpacu_assets_unloaded_list_page_request');

            $data['all']['scripts'] = $list['scripts'];
            $data['all']['styles']  = $list['styles'];

            if ($data['plugin_settings']['assets_list_layout'] === 'by-location') {
                $data['all'] = Sorting::appendLocation($data['all']);
            } else {
                $data['all'] = Sorting::sortListByAlpha($data['all']);
            }

            Main::instance()->fetchUrl = Misc::getPageUrl(Main::instance()->getCurrentPostId());

            $data['fetch_url']      = Main::instance()->fetchUrl;

            $data['nonce_action']   = Update::NONCE_ACTION_NAME;
            $data['nonce_name']     = Update::NONCE_FIELD_NAME;

            $data = self::instance()->alterAssetObj($data);

            $data['global_unload']  = Main::instance()->globalUnloaded;

            $type = false;

            if (MainFront::isHomePage() && get_option('show_on_front') === 'posts' && Main::instance()->getCurrentPostId() < 1) {
                $type = 'front_page';
            } elseif (Main::instance()->getCurrentPostId() > 0) {
                $type = 'post';
            }

            $data['wpacu_type'] = $type;

            $data['load_exceptions_per_page'] = Main::instance()->getLoadExceptionsPageLevel($type, Main::instance()->getCurrentPostId());

            // Avoid the /?wpacu_load_(css|js) to interfere with the form inputs
            if ($loadExceptionsDebug = ObjectCache::wpacu_cache_get( 'wpacu_exceptions_list_page_request' )) {
                foreach ( array( 'styles', 'scripts' ) as $assetType ) {
                    if ( isset( $loadExceptionsDebug[ $assetType ] ) && ! empty( $data['load_exceptions_per_page'][ $assetType ] ) ) {
                        foreach ( $data['load_exceptions_per_page'][ $assetType ] as $handleKey => $handle ) {
                            if ( in_array( $handle, $loadExceptionsDebug[ $assetType ] ) ) {
                                unset( $data['load_exceptions_per_page'][ $assetType ][ $handleKey ] );
                            }
                        }
                    }
                }

                // e.g. /?wpacu_load_(css|js)=
                $data['load_exceptions_debug'] = $loadExceptionsDebug;
            }

            // WooCommerce Shop Page?
            $data['is_woo_shop_page'] = Main::$vars['is_woo_shop_page'];

            $data['is_bulk_unloadable'] = $data['bulk_unloaded_type'] = false;

            $data['bulk_unloaded']['post_type'] = array('styles' => array(), 'scripts' => array());

            $data['load_exceptions_post_type'] = array();

            if (MainFront::isSingularPage()) {
                $post = Main::instance()->getCurrentPost();

                $data['post_id'] = $post->ID;

                // Current Post Type
                $data['post_type'] = $post->post_type;

                $data['load_exceptions_post_type']  = Main::instance()->getLoadExceptionsPostType($data['post_type']);

                // Are there any assets unloaded for this specific post type?
                // (e.g. page, post, product (from WooCommerce) or other custom post type)
                $data['bulk_unloaded']['post_type'] = Main::instance()->getBulkUnload('post_type', $data['post_type']);
                $data['bulk_unloaded_type']         = 'post_type';
                $data['is_bulk_unloadable']         = true;
                $data['post_type_has_tax_assoc']    = self::getAllSetTaxonomies($data['post_type']);

                $data = self::instance()->setPageTemplate($data);
            }

            $data['total_styles']  = ! empty($data['all']['styles'])  ? count($data['all']['styles'])  : false;
            $data['total_scripts'] = ! empty($data['all']['scripts']) ? count($data['all']['scripts']) : false;

            // is_archive() includes: Category, Tag, Author, Date, Custom Post Type or Custom Taxonomy based pages.
            // is_singular() includes: Post, Page, Custom Post Type
            $data['is_wp_recognizable'] = (is_archive() || MainFront::isSingularPage() || is_404() || is_search() || is_front_page() || is_home());

            $data['all_deps'] = self::instance()->getAllDeps($data['all']);

            $data['preloads'] = Preloads::instance()->getPreloads();

            // Load exception: If the user is logged in (applies globally)
            $data['handle_load_logged_in'] = Main::instance()->getHandleLoadLoggedIn();

            $data['handle_notes'] = AssetsManager::getHandleNotes();
            $data['handle_rows_contracted'] = AssetsManager::getHandleRowStatus();

            $data['ignore_child'] = Main::instance()->getIgnoreChildren();

            $data['external_srcs_ref'] = AssetsManager::setExternalSrcsRef($data['all']);

            switch (assetCleanUpHasNoLoadMatches($data['fetch_url'])) {
                case 'is_set_in_settings':
                    // The rules from "Settings" -> "Plugin Usage Preferences" -> "Do not load the plugin on certain pages" will be checked
                    $data['status'] = 5;
                    break;

                case 'is_set_in_page':
                    // The following option from "Page Options" (within the CSS/JS manager of the targeted page) is set: "Do not load Asset CleanUp Pro on this page (this will disable any functionality of the plugin)"
                    $data['status'] = 6;
                    break;

                default:
                    $data['status'] = 1;
            }

            $data['page_options'] = array();
            $data['show_page_options'] = false;

            if (in_array($type, array('post', 'front_page'))) {
                $data['show_page_options'] = true;
                $data['page_options'] = MetaBoxes::getPageOptions(Main::instance()->getCurrentPostId(), $type);
            }

            $data['post_id'] = ($type === 'front_page') ? 0 : Main::instance()->getCurrentPostId();
            ObjectCache::wpacu_cache_set('wpacu_settings_frontend_data', $data);

            self::instance()->parseTemplate('settings-frontend', $data, true);
        } elseif ($isDashboardEditView && ! isset($_GET['wpacu_just_hardcoded'])) {
            // AJAX call (not the classic WP one) from the WP Dashboard
            // Send the altered value that has the initial position too

            // Taken front the front-end view
            $data = array();
            $data['all']['scripts'] = $list['scripts'];
            $data['all']['styles'] = $list['styles'];

            $data = self::instance()->alterAssetObj($data);

            $list['styles']  = $data['all']['styles'];
            $list['scripts'] = $data['all']['scripts'];

            if ( (Main::$domGetType === 'direct' && Main::instance()->isAjaxCall) || Main::$domGetType === 'wp_remote_post' ) {
                $list['external_srcs_ref'] = AssetsManager::setExternalSrcsRef($data['all']);
            }

            // e.g. for "Loaded" and "Unloaded" statuses
            $list['current_unloaded_all'] = isset(Main::instance()->allUnloadedAssets)
                ? Main::instance()->allUnloadedAssets
                : array('styles' => array(), 'scripts' => array());

            if ( isset($_GET['wpacu_print']) ) {
                echo '<!-- Enqueued List: '."\n".print_r($list, true)."\n".' -->';
                echo '<!-- Hardcoded List: '."\n".'{wpacu_hardcoded_assets_printed}'."\n".' -->';
            }

            echo Main::START_DEL_ENQUEUED  . base64_encode(wp_json_encode($list)) . Main::END_DEL_ENQUEUED; // Loaded via wp_enqueue_scripts()
            echo Main::START_DEL_HARDCODED . '{wpacu_hardcoded_assets}' . Main::END_DEL_HARDCODED; // Make the user aware of any hardcoded CSS/JS (if any)

            add_action('shutdown', static function() {
                // Do not allow further processes as cache plugins such as W3 Total Cache could alter the source code,
                // and we need the non-minified version of the DOM (e.g. to determine the position of the elements)
                exit();
            });
        } elseif ($isDashboardEditView && isset($_GET['wpacu_just_hardcoded'])) {
            if ( isset($_GET['wpacu_print']) ) {
                echo '<!-- Hardcoded list: '."\n".'{wpacu_hardcoded_assets_printed}'."\n".' -->';
            }

            // AJAX call just for the hardcoded assets
            echo Main::START_DEL_HARDCODED . '{wpacu_hardcoded_assets}' . Main::END_DEL_HARDCODED; // Make the user aware of any hardcoded CSS/JS (if any)

            add_action('shutdown', static function() {
                // Do not allow further processes as cache plugins such as W3 Total Cache could alter the source code,
                // and we need the non-minified version of the DOM (e.g. to determine the position of the elements)
                exit();
            });
        }

        /* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */
    }

	/**
	 *
     * @noinspection PhpUndefinedVariableInspection
     * @noinspection NestedAssignmentsUsageInspection
     */
	public function ajaxGetJsonListCallback()
	{
		if ( ! isset($_POST['wpacu_nonce']) ) {
			echo 'Error: The security nonce was not sent for verification. Location: '.__METHOD__;
			return;
		}

		if ( ! wp_verify_nonce($_POST['wpacu_nonce'], 'wpacu_ajax_get_loaded_assets_nonce') ) {
			echo 'Error: The nonce security check has failed. Location: '.__METHOD__;
			return;
		}

		$postId  = (int)Misc::getVar('post', 'post_id'); // if any (could be home page for instance)
		$pageUrl = Misc::getVar('post', 'page_url'); // post, page, custom post type, home page etc.

		$postStatus = $postId > 0 ? get_post_status($postId) : false;

		// Not homepage, but a post/page? Check if it's published in case AJAX call
		// wasn't stopped due to JS errors or other reasons
		if ($postId > 0 && ! in_array($postStatus, array('publish', 'private'))) {
			exit(__('The CSS/JS files will be available to manage once the post/page is published.', 'wp-asset-clean-up'));
		}

		if ($postId > 0) {
			$type = 'post';
		}

        elseif ($postId == 0) {
			$type = 'front_page';
		}

		$wpacuListE = $wpacuListH = '';

		$settings = new Settings();

		// If the post status is 'private' only direct method can be used to fetch the assets
		// as the remote post one will return a 404 error since the page is accessed as a guest visitor
		if (Main::$domGetType === 'direct' || $postStatus === 'private') {
			$wpacuListE = Misc::getVar('post', 'wpacu_list_e');
			$wpacuListH = Misc::getVar('post', 'wpacu_list_h');
		} elseif (Main::$domGetType === 'wp_remote_post') {
			$wpRemotePost = wp_remote_post($pageUrl, array(
				'body' => array(
					WPACU_LOAD_ASSETS_REQ_KEY => 1
				)
				));

			$contents = (is_array($wpRemotePost) && isset($wpRemotePost['body']) && (! is_wp_error($wpRemotePost))) ? $wpRemotePost['body'] : '';

			// Enqueued List
			if ($contents
			    && ( strpos($contents, Main::START_DEL_ENQUEUED) !== false)
			    && ( strpos($contents, Main::END_DEL_ENQUEUED) !== false)) {
				// Enqueued CSS/JS (most of them or all)
				$wpacuListE = Misc::extractBetween(
					$contents,
					Main::START_DEL_ENQUEUED,
					Main::END_DEL_ENQUEUED
				);
			}

			// Hardcoded List
			if ($contents
			    && ( strpos($contents, Main::START_DEL_HARDCODED) !== false)
			    && ( strpos($contents, Main::END_DEL_HARDCODED) !== false)) {
				// Hardcoded (if any)
				$wpacuListH = Misc::extractBetween(
					$contents,
					Main::START_DEL_HARDCODED,
					Main::END_DEL_HARDCODED
				);
			}

			// The list of assets COULD NOT be retrieved via "WP Remote POST" for this server
			// EITHER the enqueued or hardcoded list of assets HAS TO BE RETRIEVED
			// Print out the 'error' response to make the user aware about it
			if ( ! ($wpacuListE || $wpacuListH) ) {
				// 'body' is set, and it's not an array
				if ( is_wp_error($wpRemotePost) ) {
                    $wpRemotePost['response']['message'] = $wpRemotePost->get_error_message();
				} elseif ( isset( $wpRemotePost['body']) ) {
					if ( trim( $wpRemotePost['body'] ) === '' ) {
						$wpRemotePost['body'] = '<strong>Error (blank page):</strong> It looks the targeted page is loading, but it has no content. The page seems to be blank. Please load it in incognito mode (when you are not logged-in) via your browser.';
					} elseif ( ! is_array( $wpRemotePost['body'] ) ) {
						$wpRemotePost['body'] = strip_tags( $wpRemotePost['body'], '<p><a><strong><b><em><i>' );
					}
				}

				$data = array(
					'is_dashboard_view' => true,
					'plugin_settings'   => $settings->getAll(),
					'wp_remote_post'    => $wpRemotePost
				);

				if (isset($type) && $type) {
					$data['page_options'] = MetaBoxes::getPageOptions( $postId, $type );
				}

				self::instance()->parseTemplate('meta-box-loaded', $data, true);
				exit();
			}
		}

		$data = array(
			'is_dashboard_view' => true,
			'post_id'           => $postId,
			'plugin_settings'   => $settings->getAll()
		);

		// [START] Enqueued CSS/JS (most of them or all)
		$jsonE = base64_decode($wpacuListE);
		$data['all'] = (array) json_decode($jsonE);

        // Make sure if there are no STYLES enqueued, the list will be empty to avoid any notice errors
		if ( ! isset($data['all']['styles']) ) {
			$data['all']['styles'] = array();
		}

		// Make sure if there are no SCRIPTS enqueued, the list will be empty to avoid any notice errors
		if ( ! isset($data['all']['scripts']) ) {
			$data['all']['scripts'] = array();
		}
		// [END] Enqueued CSS/JS (most of them or all)

		// [START] Hardcoded (if any)
		if ($wpacuListH) {
			// Only set the following variables if there is at least one hardcoded LINK/STYLE/SCRIPT
			$jsonH                    = base64_decode( $wpacuListH );
			$data['all']['hardcoded'] = (array) json_decode( $jsonH, ARRAY_A );

			if ( ! empty($data['all']['hardcoded']['within_conditional_comments']) ) {
				ObjectCache::wpacu_cache_set( 'wpacu_hardcoded_content_within_conditional_comments', $data['all']['hardcoded']['within_conditional_comments'] );
			}
		}
		// [END] Hardcoded (if any)

		$data['current_unloaded_page_level'] = Main::instance()->getAssetsUnloadedPageLevel( $postId, true );

		// e.g. for "Loaded" and "Unloaded" statuses
		$data['current_unloaded_all'] = isset($data['all']['current_unloaded_all']) ? (array)$data['all']['current_unloaded_all'] : array('styles' => array(), 'scripts' => array());

        $data['external_srcs_ref'] = '';

        // Any external sources?
        if ( isset($data['all']['external_srcs_ref']) && $data['all']['external_srcs_ref'] ) {
            $data['external_srcs_ref'] = $data['all']['external_srcs_ref'];
        }

        if ($data['plugin_settings']['assets_list_layout'] === 'by-location') {
			$data['all'] = Sorting::appendLocation($data['all']);
		} else {
			$data['all'] = Sorting::sortListByAlpha($data['all']);
		}

        $data['fetch_url'] = $pageUrl;
		$data['global_unload'] = Main::instance()->getGlobalUnload();

		$data['is_bulk_unloadable'] = $data['bulk_unloaded_type'] = false;

		$data['bulk_unloaded']['post_type'] = array('styles' => array(), 'scripts' => array());

		// Post Information
		if ($postId > 0) {
			$postData = get_post($postId);

			if (isset($postData->post_type) && $postData->post_type) {
				// Current Post Type
				$data['post_type']                  = $postData->post_type;

				// Are there any assets unloaded for this specific post type?
				// (e.g. page, post, product (from WooCommerce) or another custom post type)
				$data['bulk_unloaded']['post_type'] = Main::instance()->getBulkUnload('post_type', $data['post_type']);
				$data['bulk_unloaded_type']         = 'post_type';
				$data['is_bulk_unloadable']         = true;
				$data['post_type_has_tax_assoc']    = self::getAllSetTaxonomies($data['post_type']);
			}
		}

		// DO NOT alter any position as it's already verified and set
		// This AJAX call is for printing the assets that were already fetched
		$data = self::instance()->alterAssetObj($data, false);

		$data['wpacu_type'] = $type;

		// e.g. LITE rules: Load it on this page & on all pages of a specific post type
		$data['load_exceptions_per_page']  = Main::instance()->getLoadExceptionsPageLevel($type, $postId);
		$data['load_exceptions_post_type'] = ($type === 'post' && $data['post_type']) ? Main::instance()->getLoadExceptionsPostType($data['post_type']) : array();

		$data['handle_rows_contracted'] = AssetsManager::getHandleRowStatus();

		$data['total_styles']  = ! empty($data['all']['styles'])  ? count($data['all']['styles'])  : 0;
		$data['total_scripts'] = ! empty($data['all']['scripts']) ? count($data['all']['scripts']) : 0;

		$data['all_deps'] = self::instance()->getAllDeps($data['all']);

		$data['preloads'] = Preloads::instance()->getPreloads();

		$data['handle_load_logged_in'] = Main::instance()->getHandleLoadLoggedIn();

		$data['handle_notes'] = AssetsManager::getHandleNotes();
		$data['ignore_child'] = Main::instance()->getIgnoreChildren();

		$data['is_for_singular'] = (Misc::getVar('post', 'is_for_singular') === 'true');

		$data['page_options'] = array();
		$data['show_page_options'] = false;

		if (in_array($type, array('post', 'front_page'))) {
			$data['show_page_options'] = true;
			$data['page_options'] = MetaBoxes::getPageOptions($postId, $type);
		}

		self::instance()->parseTemplate('meta-box-loaded', $data, true);
		exit();
	}

	/**
	 *
     * @noinspection NestedAssignmentsUsageInspection
     */
	public function ajaxLoadRestrictedPageAreaCallback()
	{
		if ( ! isset( $_POST['wpacu_nonce'] ) || ! wp_verify_nonce( $_POST['wpacu_nonce'], 'wpacu_ajax_load_page_restricted_area_nonce' ) ) {
			echo 'Error: The security nonce is not valid.';
			exit();
		}

		$postId = (int)Misc::getVar('post', 'post_id'); // if any (could be home page for instance)

		$data = array();

		$data['post_id']   = Main::instance()->currentPostId = $postId;
		$data['fetch_url'] = Misc::getPageUrl($postId);

		$data['show_page_options'] = true;
		$data['page_options']      = MetaBoxes::getPageOptions($postId);

		$post = get_post($postId);

		// Current Post Type
		$data['post_type']          = $post->post_type;
		$data['bulk_unloaded_type'] = 'post_type';
		$data['is_bulk_unloadable'] = true;

		$data = self::instance()->setPageTemplate($data);

		switch (assetCleanUpHasNoLoadMatches($data['fetch_url'])) {
			case 'is_set_in_settings':
				// The rules from "Settings" -> "Plugin Usage Preferences" -> "Do not load the plugin on certain pages" will be checked
				$data['status']  = 5;
				break;

			case 'is_set_in_page':
				// The following option from "Page Options" (within the CSS/JS manager of the targeted page) is set: "Do not load Asset CleanUp Pro on this page (this will disable any functionality of the plugin)"
				$data['status']  = 6;
				break;

			default:
				$data['status'] = 1;
		}

		self::instance()->parseTemplate('meta-box-restricted-page-load', $data, true);
		exit();
	}

	/**
	 * Make administrator more aware if "TEST MODE" is enabled or not
	 */
	public function wpacuHtmlNoticeForAdmin()
	{
		add_action('wp_footer', static function() {
			if ((WPACU_GET_LOADED_ASSETS_ACTION === true) || (! apply_filters('wpacu_show_admin_console_notice', true)) || OptimizeCommon::preventAnyFrontendOptimization()) {
				return;
			}

			if ( ! (Menu::userCanAccessAssetCleanUp() && ! is_admin()) ) {
				return;
			}

			if (Main::instance()->settings['test_mode']) {
				$consoleMessage = sprintf(esc_html__('%s: "TEST MODE" ENABLED (any settings or unloads will be visible ONLY to you, the logged-in administrator)', 'wp-asset-clean-up'), WPACU_PLUGIN_TITLE);
				$testModeNotice = esc_html__('"Test Mode" is ENABLED. Any settings or unloads will be visible ONLY to you, the logged-in administrator.', 'wp-asset-clean-up');
			} else {
				$consoleMessage = sprintf(esc_html__('%s: "LIVE MODE" (test mode is not enabled, thus, all the plugin changes are visible for everyone: you, the logged-in administrator and the regular visitors)', 'wp-asset-clean-up'), WPACU_PLUGIN_TITLE);
				$testModeNotice = esc_html__('The website is in LIVE MODE as "Test Mode" is not enabled. All the plugin changes are visible for everyone: logged-in administrators and regular visitors.', 'wp-asset-clean-up');
			}
			?>
            <!--
            <?php echo sprintf(esc_html__('NOTE: These "%s: Page Speed Booster" messages are only shown to you, the HTML comment is not visible for the regular visitor.', 'wp-asset-clean-up'), WPACU_PLUGIN_TITLE); ?>

            <?php echo esc_html__($testModeNotice); ?>
            -->
            <script <?php echo Misc::getScriptTypeAttribute(); ?> data-wpacu-own-inline-script="true">
                console.log('<?php echo esc_js($consoleMessage); ?>');
            </script>
			<?php
		});
	}

    /**
     * @param $name
     * @param array $data (if present $data values are used within the included template)
     * @param bool|false $echo
     * @param bool|false $returnData (relevant when $echo is set to true)
     * @return string|array
     */
    public function parseTemplate($name, $data = array(), $echo = false, $returnData = false)
    {
        $pathToTemplateFile = WPACU_PLUGIN_DIR . '/templates/' . $name . '.php';

        $templateFile = apply_filters(
            'wpacu_template_file', // tag
            $pathToTemplateFile, // value
            $name // extra argument
        );

        if ( ! is_file($templateFile) ) {
            throw new \Exception(
            __('The following template file was not found:', 'wp-asset-clean-up') .
            ' <strong>'.$templateFile.'</strong>'
            );
        }

        ob_start();
        include $templateFile;
        $result = ob_get_clean();

        // $echo is set to true ($returnData is not relevant), thus print the output
        if ($echo) {
            echo $result;
            return true;
        }

        // $echo is set to false and $returnData to true
        if ($returnData) {
            return array(
                'output' => $result,
                'data' => $data
            );
        }

        /// $echo is set to false (default) and $returnData to $false (default)
        return $result;
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    public function setPageTemplate($data)
    {
        global $template;

        $getPageTpl = get_post_meta(Main::instance()->getCurrentPostId(), '_wp_page_template', true);

        // Could be a custom post type with no template set
        if (! $getPageTpl) {
            $getPageTpl = get_page_template();

            if (in_array(basename($getPageTpl), array('single.php', 'page.php'))) {
                $getPageTpl = 'default';
            }
        }

        if (! $getPageTpl) {
            return $data;
        }

        $data['page_template'] = $getPageTpl;

        $data['all_page_templates'] = wp_get_theme()->get_page_templates();

        // Is the default template shown? Most of the time it is!
        if ($data['page_template'] === 'default') {
            $pageTpl = (isset($template) && $template) ? $template : get_page_template();
            $data['page_template'] = basename( $pageTpl );
            $data['all_page_templates'][ $data['page_template'] ] = 'Default Template';
        }

        if (isset($template) && $template && defined('ABSPATH')) {
            $data['page_template_path'] = str_replace(
                array(Misc::getWpRootDirPath(), dirname(WP_CONTENT_DIR).'/'),
                '',
                '/'.$template
            );
        }

        return $data;
    }
}
