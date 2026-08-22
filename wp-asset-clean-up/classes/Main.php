<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

/**
 * Class Main
 * @package WpAssetCleanUp
 */
class Main
{
	/**
	 *
	 */
	const START_DEL_ENQUEUED  = 'BEGIN WPACU PLUGIN JSON ENQUEUED';

	/**
	 *
	 */
	const END_DEL_ENQUEUED    = 'END WPACU PLUGIN JSON ENQUEUED';

	/**
	 *
	 */
	const START_DEL_HARDCODED = 'BEGIN WPACU PLUGIN JSON HARDCODED';

	/**
	 *
	 */
	const END_DEL_HARDCODED   = 'END WPACU PLUGIN JSON HARDCODED';

	/**
     * Option "Plugin Usage Preferences" -> "Manage in the Dashboard" -> "Select a retrieval way:" ('Direct' or 'WP Remote POST')
     *
	 * @var string
	 */
	public static $domGetType = 'direct';

	/**
	 * Record them for debugging purposes when using /?wpacu_debug
	 *
	 * @var array
	 */
	public $allUnloadedAssets = array( 'styles' => array(), 'scripts' => array() );

	/**
	 * @var array
	 */
	public $globalUnloaded = array();

    /**
     * @var array[]
     */
    public $unloadedAssetsPageLevel = array(
		// 'home' is the home page
		// If the number is higher, then it belongs to a post type ('post', 'page', etc.)
		'home' => array(
			'array' => array( 'styles' => array(), 'scripts' => array() ),
			'_json' => false,
			'_set'  => false
		)
	);

	/**
	 * @var array
	 */
	public $loadExceptionsPageLevel = array( 'styles' => array(), 'scripts' => array(), '_set' => false );

	/**
	 * @var array[]
	 */
	public $loadExceptionsPostType = array( 'styles' => array(), 'scripts' => array(), '_set' => false );

	/**
	 * Rule that applies site-wide: if the user is logged-in
	 *
	 * @var array
	 */
	public $loadExceptionsLoggedInGlobal = array( 'styles' => array(), 'scripts' => array(), '_set' => false );

	/**
	 * @var string
	 */
	public $fetchUrl;

    /**
     * Shared state used by the Lite add-on.
     * Kept in Main for backward compatibility with existing references to Main::instance()->isUpdateable.
     *
     * @var bool
     */
    public $isUpdateable = true;

	/**
	 * @var int
	 */
	public $currentPostId = 0;

    /**
     * @var \WP_Post|null
     */
    public $currentPost = null;

	/**
	 * @var array
	 */
	public static $vars = array( 'woo_url_not_match' => false, 'is_woo_shop_page' => false, 'for_type' => '', 'current_post_id' => 0, 'current_post_type' => '' );

	/**
	 * This is set to `true` only if "Manage in the Front-end?" is enabled in plugin's settings
	 * and the logged-in administrator with plugin activation privileges
	 * is outside the Dashboard viewing the pages like a visitor
	 *
	 * @var bool
	 */
	public $isFrontendEditView = false;

	/**
	 * @var array
	 */
	public $stylesInHead = array();

	/**
	 * @var array
	 */
	public $scriptsInHead = array();

	/**
	 * @var array
	 */
	public $assetsInFooter = array( 'styles' => array(), 'scripts' => array() );

	/**
	 * @var array
	 */
	public $wpAllScripts = array();

	/**
	 * @var array
	 */
	public $wpAllStyles = array();

	/**
	 * @var array
	 */
	public $ignoreChildren = array('_set' => false);

	/**
	 * @var array
	 */
	public $ignoreChildrenHandlesOnTheFly = array();

	/**
	 * @var int
	 */
	public static $wpStylesSpecialDelimiters = array(
		'start' => '<!--START-WPACU-SPECIAL-STYLES',
		'end'   => 'END-WPACU-SPECIAL-STYLES-->'
	);

	/**
	 * @var array
	 */
	public $postTypesUnloaded = array();

	/**
	 * @var array
	 */
	public $settings = array();

	/**
	 * @var bool
	 */
	public $isAjaxCall = false;

	/**
	 * Fetch CSS/JS list from the Dashboard
	 *
	 * @var bool
	 */
	public $isGetAssetsCall = false;

	/**
	 * @var Main|null
	 */
	private static $singleton;

	/**
	 * @return null|Main
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
	    // Filter before triggering the actual unloading through "wp_deregister_script", "wp_dequeue_script", "wp_deregister_style", "wp_dequeue_style"
	    $this->fallbacks();

		$this->isAjaxCall      = ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest' );
		$this->isGetAssetsCall = isset( $_REQUEST[ WPACU_LOAD_ASSETS_REQ_KEY ] ) && $_REQUEST[ WPACU_LOAD_ASSETS_REQ_KEY ];

		add_filter( 'duplicate_post_meta_keys_filter', static function( $metaKeys ) {
			// Get the original post ID
			$postId = isset( $_GET['post'] ) ? (int)$_GET['post'] : false;

			if ( ! $postId ) {
				$postId = isset( $_POST['post'] ) ? (int)$_POST['post'] : false;
			}

			if ( $postId ) {
				global $wpdb;

				$metaKeyLike = '_' . WPACU_PLUGIN_ID . '_%';

				$assetCleanUpMetaKeysQuery = <<<SQL
SELECT `meta_key` FROM {$wpdb->postmeta} WHERE meta_key LIKE '{$metaKeyLike}' AND post_id='{$postId}'
SQL;
				$assetCleanUpMetaKeys      = $wpdb->get_col( $assetCleanUpMetaKeysQuery );

				if ( ! empty( $assetCleanUpMetaKeys ) ) {
					$metaKeys = array_merge( $metaKeys, $assetCleanUpMetaKeys );
				}
			}

			return $metaKeys;
		} );

        add_action('wp', function () {
            $wpacuSettingsClass = new Settings();
            Main::instance()->settings = $wpacuSettingsClass->getAll(true);

            }, 0);
    }

    /**
     * @return bool
     */
    public static function isPluginClearCacheLinkAccessible()
    {
        $isAdminWithClearCacheLink = is_admin() && (Menu::isPluginPage() || (is_admin_bar_showing() && ! Main::instance()->settings['hide_from_admin_bar']));
        $isFrontWithClearCacheLink = ! is_admin() && is_admin_bar_showing() && ! Main::instance()->settings['hide_from_admin_bar'];

        return $isAdminWithClearCacheLink || $isFrontWithClearCacheLink;
    }

    /**
     * @return bool
     */
    public static function showAssetsManagerInFrontend()
    {
        if (is_admin()) {
            return false; // Only relevant in the front-end view
        }

        // The option is disabled
        if ( ! Main::instance()->settings['frontend_show'] ) {
            return false;
        }

        // The asset list is hidden via query string: /?wpacu_no_frontend_show
        if (isset($_REQUEST['wpacu_no_frontend_show'])) {
            return false;
        }

        // Page loaded via Yellow Pencil Editor within an iframe? Do not show it as it's irrelevant there
        if (isset($_GET['yellow_pencil_frame'], $_GET['yp_page_type'])) {
            return false;
        }

        // The option is enabled, but there are show exceptions, check if the list should be hidden
        if (Main::instance()->settings['frontend_show_exceptions']) {
            $frontendShowExceptions = trim(Main::instance()->settings['frontend_show_exceptions']);

            // We want to make sure the RegEx rules will be working fine if certain characters (e.g. Thai ones) are used
            $requestUriAsItIs = rawurldecode($_SERVER['REQUEST_URI']);

            if (strpos($frontendShowExceptions, "\n") !== false) {
                foreach (explode("\n", $frontendShowExceptions) as $frontendShowException) {
                    $frontendShowException = trim($frontendShowException);

                    // Ignore empty rows. An empty string is found in every URI and would
                    // otherwise hide the manager across the entire front end.
                    if ($frontendShowException === '') {
                        continue;
                    }

                    if (strpos($requestUriAsItIs, $frontendShowException) !== false) {
                        return false;
                    }
                }
            } elseif (strpos($requestUriAsItIs, $frontendShowExceptions) !== false) {
                return false;
            }
        }

        // Allows managing assets to chosen admins and the user is not in the list
        if ( ! AssetsManager::currentUserCanViewAssetsList() ) {
            return false;
        }

        return true;
    }

    /**
     * @return void
     */
    public function loadAllSettings()
    {
        $wpacuSettingsClass = new Settings();
        $this->settings     = $wpacuSettingsClass->getAll();

        if ( $this->settings['dashboard_show'] && $this->settings['dom_get_type'] ) {
            self::$domGetType = $this->settings['dom_get_type'];
        }

        // Menu::userCanAccessPlugin() has to be called in 'init' (not too early)
        add_action('init', function() {
            if ( ! is_user_logged_in() ) {
                return; // stop here, as any user with access to the plugin has to be logged in
            }

            // Conditions
            // 1) User has rights to manage the assets and the option is enabled in plugin's Settings
            // 2) Not an AJAX call from the Dashboard
            // 3) Not inside the Dashboard
            self::instance()->isFrontendEditView =
                Menu::userCanAccessPlugin() && self::showAssetsManagerInFrontend() // 1
                && ! self::instance()->isGetAssetsCall // 2
              && ! is_admin(); // 3
        }, 0);
    }

	/**
	 * @param $list (should never be empty when called)
	 * @param $assetType
	 * @param $ruleType
	 *
	 * @return mixed
	 */
	public function filterAssetsUnloadList($list, $assetType, $ruleType)
    {
	    // Remove any assets from the list in case there are any load exceptions detected
        if ($ruleType === 'load_exception') {
	        $this->loadExceptionsPageLevel      = $this->getLoadExceptionsPageLevel(self::$vars['for_type'], self::$vars['current_post_id']);
            $this->loadExceptionsLoggedInGlobal = is_user_logged_in() ? $this->getHandleLoadLoggedIn() : $this->loadExceptionsLoggedInGlobal;
            $this->loadExceptionsPostType       = $this->getLoadExceptionsPostType(self::$vars['current_post_type']);

	        $anyAssetsLoadExceptions = ( ! empty( $this->loadExceptionsPageLevel[ $assetType ] )
                || ( ! empty( $this->loadExceptionsLoggedInGlobal[ $assetType ] ) )
                || ( ! empty( $this->loadExceptionsPostType[$assetType] ) )
            );

	        if ( $anyAssetsLoadExceptions ) {
		        foreach ( $list as $handleKey => $handle ) {
			        $loadAssetAsException = in_array( $handle, $this->loadExceptionsPageLevel[ $assetType ] )  // 1) per page, per group pages OR
                        || in_array( $handle, $this->loadExceptionsPostType[ $assetType ] ) // 2) On all pages belonging to a specific post type
                        || ( in_array( $handle, $this->loadExceptionsLoggedInGlobal[ $assetType ] ) && is_user_logged_in() ); // 3) site-wide if the user is logged-in
			        if ( $loadAssetAsException ) {
				        unset( $list[ $handleKey ] );
			        }
		        }
	        }
        }

	    return $list;
    }

	/**
     * Alter CSS/JS list marked for dequeue
     *
	 * @param $for
	 * @return array
	 */
	public function unloadAssetOnTheFly($for)
    {
	    $assetIndex = 'wpacu_unload_'.$for;

        if (! ($unloadAsset = Misc::getVar('get', $assetIndex))) {
            return array();
        }

        $assetType = ($for === 'css') ? 'styles' : 'scripts';

	    $assetHandles = array();

        if (strpos($unloadAsset, ',') === false) { // No comma, just one asset targeted
            if (strpos($unloadAsset, '[ignore-deps]') !== false) {
                $unloadAsset = str_replace('[ignore-deps]', '', $unloadAsset);
                $this->ignoreChildrenHandlesOnTheFly[$assetType][] = $unloadAsset;
            }

            $assetHandles[] = $unloadAsset;
        } else { // There are commas, multiple assets targeted
            foreach (explode(',', $unloadAsset) as $unloadAsset) {
                $unloadAsset = trim($unloadAsset);

                if ($unloadAsset) {
                    if (strpos($unloadAsset, '[ignore-deps]') !== false) {
                        $unloadAsset = str_replace('[ignore-deps]', '', $unloadAsset);
                        $this->ignoreChildrenHandlesOnTheFly[$assetType][] = $unloadAsset;
                    }

                    $assetHandles[] = $unloadAsset;
                }
            }
        }

        return $assetHandles;
    }

	/**
	 * @param $exceptionsList
	 *
	 * @return array
	 */
	public function makeLoadExceptionOnTheFly($exceptionsList)
    {
	    $exceptionsListDebug = array('styles' => array(), 'scripts' => array());

        foreach (array('css', 'js') as $assetExt) {
            $assetKey = ($assetExt === 'css') ? 'styles' : 'scripts';
            $indexToCheck = 'wpacu_load_'.$assetExt;

            if ($loadAsset = Misc::getVar('get', $indexToCheck)) {
                if (strpos($loadAsset, ',') === false && ! in_array($loadAsset, $exceptionsList[$assetKey])) {
                    $exceptionsList[$assetKey][] = $loadAsset;
	                $exceptionsListDebug[$assetKey][] = $loadAsset;
                } else {
                    foreach (explode(',', $loadAsset) as $loadAsset) {
                        if (($loadAsset = trim($loadAsset)) && ! in_array($loadAsset, $exceptionsList[$assetKey])) {
                            $exceptionsList[$assetKey][] = $loadAsset;
	                        $exceptionsListDebug[$assetKey][] = $loadAsset;
                        }
                    }
                }
            }
	    }

        ObjectCache::wpacu_cache_add('wpacu_exceptions_list_page_request', $exceptionsListDebug);

        return $exceptionsList;
    }

    /**
     * This fetches the "Load it on this page" exceptions
     *
     * @param string $type ("post", "front_page")
     * @param string|int $postId
     *
     * @return array|array[]
     *
     * @noinspection NestedAssignmentsUsageInspection
     */
    public function getLoadExceptionsPageLevel($type = 'post', $postId = '')
    {
        if ( $this->loadExceptionsPageLevel['_set'] ) {
            return $this->loadExceptionsPageLevel; // it was set
        }

	    $exceptionsListDefault = $exceptionsList = array( 'styles' => array(), 'scripts' => array() );

	    if ( $type === 'post' && ! $postId ) {
            // $postId needs to have a value if $type is a 'post' type
            $this->loadExceptionsPageLevel = $exceptionsListDefault;
		    $this->loadExceptionsPageLevel['_set'] = true;
            return $this->loadExceptionsPageLevel;
        }

	    if ( ! $type ) {
            // Invalid request
            $this->loadExceptionsPageLevel = $exceptionsListDefault;
		    $this->loadExceptionsPageLevel['_set'] = true;
            return $this->loadExceptionsPageLevel;
        }

	    // Default
        $exceptionsListJson = null;

	    // Post or Post of the Homepage (if chosen in the Dashboard)
	    if ( $type === 'post' || ( Misc::getShowOnFront() === 'page' && $postId ) ) {
            $exceptionsListJson = get_post_meta( $postId, '_' . WPACU_PLUGIN_ID . '_load_exceptions', true );
        }

	    // The home page could also be the list of the latest blog posts
	    if ( $type === 'front_page' ) {
            $exceptionsListJson = get_option( WPACU_PLUGIN_ID . '_front_page_load_exceptions' );
        }

        $exceptionsListJson = apply_filters(
            'wpacu_internal_pro_load_exceptions_page_level_json',
            $exceptionsListJson,
            $type,
            $postId
        );

	    if ( $exceptionsListJson ) {
			$exceptionsList = wpacuJsonDecodeToArray($exceptionsListJson, $exceptionsListDefault);
        }

	    // Any exceptions on the fly added for debugging purposes? Make sure to grab them
        if (isset($_GET['wpacu_load_css']) || isset($_GET['wpacu_load_js'])) {
            $exceptionsList = $this->makeLoadExceptionOnTheFly($exceptionsList);
        }

	    // Avoid any notice errors
	    foreach ( array( 'styles', 'scripts' ) as $assetType ) {
            if ( ! isset( $exceptionsList[ $assetType ] ) ) {
                $exceptionsList[ $assetType ] = array();
            }
        }

	    $this->loadExceptionsPageLevel = $exceptionsList;
	    $this->loadExceptionsPageLevel['_set'] = true;

	    return $this->loadExceptionsPageLevel;
    }

	/**
     * Option: 'Make an exception from any unloading rule & always load it' -> 'On all pages of "post" post type'
     *
	 * @param $postType
	 *
	 * @return array
	 */
	public function getLoadExceptionsPostType($postType)
    {
        if ($this->loadExceptionsPostType['_set']) {
            return $this->loadExceptionsPostType;
        }

        // Not on a singular page
        if ($postType === '') {
	        $this->loadExceptionsPostType['_set'] = true;
	        return $this->loadExceptionsPostType;
        }

	    $exceptionsListDefault = array('styles' => array(), 'scripts' => array());

	    $exceptionsListJson = get_option(WPACU_PLUGIN_ID . '_post_type_load_exceptions');

	    $exceptionsList = wpacuJsonDecodeToArray($exceptionsListJson, $exceptionsListDefault);

	    // Issues with the stored value? Return an empty list
	    if (empty($exceptionsList)) {
            $this->loadExceptionsPostType = $exceptionsListDefault;
		    $this->loadExceptionsPostType['_set'] = true;
		    return $this->loadExceptionsPostType;
	    }

	    // Return any handles added as load exceptions for the requested $postType
	    if (isset($exceptionsList[$postType])) {
            foreach ( $exceptionsList[$postType] as $assetType => $assetList ) {
                foreach ( $assetList as $assetHandle => $assetValue ) {
                    if ( $assetValue ) {
                        $this->loadExceptionsPostType[ $assetType ][] = $assetHandle;
                    }
                }
            }
        }

	    $this->loadExceptionsPostType['_set'] = true;
	    return $this->loadExceptionsPostType;
    }

    /**
     * Option: Unload site-wide * everywhere
     *
     * @return array
     */
    public function getGlobalUnload()
    {
        $existingListEmpty = array('styles' => array(), 'scripts' => array());
        $existingListJson  = get_option( WPACU_PLUGIN_ID . '_global_unload');

        $existingListData = $this->existingList($existingListJson, $existingListEmpty);

        // No 'styles' or 'scripts' - Set them as empty to avoid any PHP warning errors
	    foreach ( array('styles', 'scripts') as $assetType ) {
		    if ( ! isset( $existingListData['list'][$assetType] ) || ! is_array( $existingListData['list'][$assetType] ) ) {
			    $existingListData['list'][$assetType] = array();
		    }
	    }

        return $existingListData['list'];
    }

	/**
	 * @param string $for (could be 'post_type', 'taxonomy' for the Pro version etc.)
	 * @param string $type
	 *
	 * @return array
	 */
	public function getBulkUnload($for, $type = 'all')
    {
        $existingListEmpty = array('styles' => array(), 'scripts' => array());

        $existingListAllJson = get_option( WPACU_PLUGIN_ID . '_bulk_unload');

        if (! $existingListAllJson) {
            return $existingListEmpty;
        }

        $existingListAll = wpacuJsonDecodeToArray($existingListAllJson);

        if (empty($existingListAll)) {
            return $existingListEmpty;
        }

        $existingList = $existingListEmpty;

        // Search, Date archives, 404 Not Found or Custom Post Type archive pages
	    if (in_array($for, array('search', 'date', '404')) || strpos($for, 'custom_post_type_archive_') !== false) {
	        if ( isset( $existingListAll['styles'][ $for ] )
	             && is_array( $existingListAll['styles'][ $for ] ) ) {
		        $existingList['styles'] = $existingListAll['styles'][ $for ];
	        }

	        if ( isset( $existingListAll['scripts'][ $for ] )
	             && is_array( $existingListAll['scripts'][ $for ] ) ) {
		        $existingList['scripts'] = $existingListAll['scripts'][ $for ];
	        }
        } else {
        	// has $type (could be 'post_type' for all singular pages of the targeted type)
		    foreach (array('styles', 'scripts') as $assetType) {
			    if ( isset( $existingListAll[$assetType][ $for ][ $type ] ) && is_array( $existingListAll[$assetType][ $for ][ $type ] ) ) {
				    $existingList[$assetType] = $existingListAll[$assetType][ $for ][ $type ];
			    }
		    }
        }

        return $existingList;
    }

	/**
     * Option: 'Make an exception from any unload rule & always load it' -> 'If the user is logged-in'
     *
	 * @return array
	 */
	public function getHandleLoadLoggedIn()
    {
    	if ($this->loadExceptionsLoggedInGlobal['_set']) {
			return $this->loadExceptionsLoggedInGlobal;
	    }

	    $targetGlobalKey = 'load_it_logged_in';

	    $handleData = array( 'styles' => array(), 'scripts' => array() );

        $handleDataList = wpacuGetGlobalData();

        // Are load exceptions set for styles and scripts?
        foreach ( array( 'styles', 'scripts' ) as $assetKey ) {
            if ( ! empty( $handleDataList[ $assetKey ][ $targetGlobalKey ] ) ) {
                $handleData[ $assetKey ] = array_keys($handleDataList[ $assetKey ][ $targetGlobalKey ]);
            }
        }

	    $this->loadExceptionsLoggedInGlobal = $handleData;

	    // Avoid any PHP notice errors
	    foreach (array('styles', 'scripts') as $assetType) {
	        if ( ! isset($this->loadExceptionsLoggedInGlobal[$assetType]) ) {
		        $this->loadExceptionsLoggedInGlobal[$assetType] = array();
            }
        }

	    $this->loadExceptionsLoggedInGlobal['_set'] = true;

	    return $this->loadExceptionsLoggedInGlobal;
    }

	/**
     * Option: 'Ignore dependency rule and keep the "children" loaded'
     *
	 * @return array
	 */
	public function getIgnoreChildren()
	{
        if ( wpacuIsDefinedConstant('WPACU_NO_IGNORE_CHILD_RULES_SET_FOR_ASSETS') ) {
            $this->ignoreChildren['_set'] = true;
            return $this->ignoreChildren;
        }

	    if ( $this->ignoreChildren['_set'] === false ) {
            $ignoreChildList = wpacuGetGlobalData();

		    if ( ! empty($ignoreChildList) ) {
			    // Are any "ignore children" rules set for styles and scripts?
			    foreach ( array('styles', 'scripts') as $assetKey ) {
				    if ( ! empty($ignoreChildList[$assetKey]['ignore_child']) ) {
					    $this->ignoreChildren[$assetKey] = $ignoreChildList[$assetKey]['ignore_child'];
				    }
			    }
		    }
	    }

        $this->ignoreChildren['_set'] = true;
		return $this->ignoreChildren;
	}

	/**
	 * @return array
	 */
	public static function getHandlesInfo()
    {
        $assetsInfo = array('styles' => array(), 'scripts' => array());

        $wpacuGlobalData = wpacuGetGlobalData();

        foreach (array('styles', 'scripts') as $assetKey) {
            if ( ! empty( $wpacuGlobalData[$assetKey]['assets_info'] ) ) {
                $assetsInfo[$assetKey] = MiscArray::filterList( $wpacuGlobalData[$assetKey]['assets_info'] );
            }
        }
        // Fallback for those who still use the old transient way of fetching the assets' info
	    if ($assetsInfoTransient = get_transient(WPACU_PLUGIN_ID . '_assets_info')) {
		    $assetsInfoTransientArray = @json_decode($assetsInfoTransient, ARRAY_A);

		    if (is_array($assetsInfoTransientArray) && ! empty($assetsInfoTransientArray)) {
			    foreach ($assetsInfoTransientArray as $assetKeyTransient => $handlesList) {
				    if (! in_array($assetKeyTransient, array('styles', 'scripts'))) {
					    continue;
				    }

				    foreach ($handlesList as $handleName => $handleData) {
					    if (! isset($assetsInfo[$assetKeyTransient][$handleName])) {
						    $assetsInfo[$assetKeyTransient][$handleName] = $handleData;
					    }
				    }
			    }
		    }
	    }

	    return $assetsInfo;
    }

    // [HANDLE UNIQUE NAME]
    /**
     * Sometimes, developers use non-unique handles by adding random unique IDs to the handle name
     *
     * Example: "GTranslate" adds "gt_widget_script_84887047" as the name of the name for the following script: "/wp-content/plugins/gtranslate/js/float.js"
     * In a next page load, the handle will be like "gt_widget_script_45402164" which would invalidate any rules added to that handle
     * Thus, it needs to have a unique name (an alias) in order to avoid lack any of functionality and more useless entries in the database
     *
     * The methods below are related to this.
     */
    /**
     * @param $handleOriginal
     * @param $assetType ('styles', 'scripts')
     *
     * @return array
     */
    public static function maybeAssignUniqueHandleName($handleOriginal, $assetType)
    {
        $refString = 'gt_widget_script_';

        $result = array('handle_ref' => $handleOriginal, 'handle_original' => $handleOriginal); // default

        if ($assetType === 'scripts' && strpos($handleOriginal, $refString) === 0) {
            $maybeRandNum = str_replace($refString, '', $handleOriginal);

            if (is_numeric($maybeRandNum)) {
                $result['handle_ref'] = $refString . 'gtranslate';
            }
        }

        return $result;
    }

    /**
     * @param $handle
     * @param $assetType
     *
     * @return mixed
     */
    public static function maybeGetOriginalNonUniqueHandleName($handle, $assetType)
    {
        $refString = 'gt_widget_script_';

        if ($assetType === 'scripts' && strpos($handle, $refString) === 0) {

            foreach (self::instance()->wpAllScripts['queue'] as $scriptHandle) {
                if (strpos($scriptHandle, $refString) === 0) {
                    $maybeRandNum = str_replace($refString, '', $scriptHandle);

                    if (is_numeric($maybeRandNum)) {
                        return $scriptHandle;
                    }
                }
            }
        }

        return $handle;
    }
    // [/HANDLE UNIQUE NAME]

    /**
     * @param string $pageContextKey
     * @param bool   $returnAsArray
     *
     * @return string|array|null
     */
    public function getCachedAssetsUnloadedPageLevel($pageContextKey, $returnAsArray = false)
    {
        if (empty($this->unloadedAssetsPageLevel[$pageContextKey]['_set'])) {
            return null;
        }

        return $returnAsArray ? $this->unloadedAssetsPageLevel[$pageContextKey]['array'] : $this->unloadedAssetsPageLevel[$pageContextKey]['json'];
    }

    /**
     * This method retrieves only the assets that are unloaded per page
     * Including 404, date and search pages (they are considered as ONE page with the same rules for any URL variation)
     *
     * @param int    $postId
     * @param string $fetchFrom | this can be called from the Dashboard (via an AJAX call) or directly in the front-end view
     * @param bool   $returnAsArray
     *
     * @return string|array (The returned value must be a JSON one, unless $returnAsArray is set to true)
     *
     * @noinspection NestedAssignmentsUsageInspection
     */
    public function getAssetsUnloadedPageLevel($postId = 0, $fetchFrom = 'front', $returnAsArray = false)
    {
        // Backward compatibility with the old signature: ($postId, $returnAsArray).
        if (is_bool($fetchFrom)) {
            $returnAsArray = $fetchFrom;
            $fetchFrom = 'front';
        }

        $fetchFrom = in_array($fetchFrom, array('front', 'dash'), true) ? $fetchFrom : 'front';

        $isFrontend = ($fetchFrom === 'front');

        $defaultEmptyArrayValue = array( 'styles' => array(), 'scripts' => array() );

        $postId = (int)$postId;

        $isSingularContext = false; // default
        $pageContextKey    = ''; // default

        if ($isFrontend) {
            if ($postId === 0) {
                $postId = (int)$this->getCurrentPostId();
            }

            if ($postId > 0 && MainFront::isSingularPage()) {
                $postType       = get_post_type($postId);
                $pageContextKey = 'post_' . $postType . '_' . $postId; // A singular post page
                $isSingularContext = true;
            } elseif (MainFront::isHomePage()) {
                $pageContextKey = 'home'; // Legacy (it's a home page, latest posts type)
            }
        } else {
            $pageType = sanitize_key(Misc::getVar('post', 'page_type'));

            if ($postId > 0) {
                $postType       = get_post_type($postId);
                $pageContextKey = 'post_' . $postType . '_' . $postId; // A singular post page
                $isSingularContext = true;
            } elseif ($pageType === '' && ! isset($_REQUEST['tag_id'])) {
                $pageContextKey = 'home'; // Legacy (it's a home page, latest posts type)
            }
        }

        if ( ! $pageContextKey ) {
            $pageContextKey = apply_filters('wpacu_internal_assets_unloaded_page_level_context_key', $postId);

            if ( ! $pageContextKey ) {
                // Something's funny (invalid request)
                return $returnAsArray ? $defaultEmptyArrayValue : wp_json_encode($defaultEmptyArrayValue);
            }
        }

        $cachedAssetsRemovedPageLevel = $this->getCachedAssetsUnloadedPageLevel($pageContextKey, $returnAsArray);

        if ($cachedAssetsRemovedPageLevel !== null) {
            return $cachedAssetsRemovedPageLevel;
        }

        $assetsRemovedPageLevel = wp_json_encode( $defaultEmptyArrayValue );

        if ($pageContextKey === 'home') {
            $assetsRemovedPageLevel = get_option(WPACU_PLUGIN_ID . '_front_page_no_load');

            if ($isFrontend) {
                self::addToAssetToUnloadReasonListFromPageLevel($assetsRemovedPageLevel, 'Unload on the home page');
            }
        } elseif ($isSingularContext) {
            $assetsRemovedPageLevel = get_post_meta($postId, '_' . WPACU_PLUGIN_ID . '_no_load', true);

            if ($isFrontend) {
                if (MainFront::isHomePage()) { // it's a post page, and at the same time the home page
                    $ruleLabel = 'Unload on the home page (Page ID: ' . $postId . ')';
                } else {
                    $postType = get_post_type($postId);

                    if (is_string($postType) && $postType !== '') {
                        $ruleLabel = 'Unload on this <strong>' . esc_html($postType) . '</strong> (ID: ' . $postId . ')';
                    } else {
                        $ruleLabel = 'Unload on this page (ID: ' . $postId . ')';
                    }
                }

                self::addToAssetToUnloadReasonListFromPageLevel($assetsRemovedPageLevel, $ruleLabel);
            }
        } else {
            $assetsRemovedPageLevel = apply_filters(
                'wpacu_internal_pro_get_assets_unloaded_page_level',
                $assetsRemovedPageLevel,
                $postId,
                $pageContextKey,
                $returnAsArray
            );
        }

        if (is_array($assetsRemovedPageLevel)) {
            $assetsRemovedDecoded = $assetsRemovedPageLevel;
        } elseif (is_string($assetsRemovedPageLevel)) {
            $assetsRemovedDecoded = json_decode($assetsRemovedPageLevel, true);

            if ( empty($assetsRemovedPageLevel) || $assetsRemovedPageLevel === '[]' || wpacuJsonLastError() !== JSON_ERROR_NONE || ! is_array($assetsRemovedDecoded) ) {
                $assetsRemovedDecoded = $defaultEmptyArrayValue;
            }
        } else {
            $assetsRemovedDecoded = $defaultEmptyArrayValue;
        }

        foreach (array('styles', 'scripts') as $assetType) {
            if ( ! isset($assetsRemovedDecoded[$assetType]) || ! is_array($assetsRemovedDecoded[$assetType]) ) {
                $assetsRemovedDecoded[$assetType] = array();
            }
        }

        if ($isFrontend) {
            /* [START] Unload CSS/JS on page request for debugging purposes */
            $assetsUnloadedOnTheFly = $defaultEmptyArrayValue;

            if (Misc::getVar('get', 'wpacu_unload_css')) {
                $cssOnTheFlyList = $this->unloadAssetOnTheFly('css');

                if ( ! empty($cssOnTheFlyList) ) {
                    $assetType = 'styles';

                    foreach ($cssOnTheFlyList as $cssHandle) {
                        if ( ! in_array($cssHandle, $assetsRemovedDecoded[$assetType], true) ) {
                            $assetsRemovedDecoded[$assetType][] = $assetsUnloadedOnTheFly[$assetType][] = $cssHandle;

                            Main::addAssetToUnloadReasonList($cssHandle, $assetType, array(
                                'rule_label' => 'Unload via "wpacu_unload_css" query string'
                            ));
                        }
                    }
                }
            }

            if (Misc::getVar('get', 'wpacu_unload_js')) {
                $jsOnTheFlyList = $this->unloadAssetOnTheFly('js');

                if ( ! empty($jsOnTheFlyList) ) {
                    $assetType = 'scripts';

                    foreach ($jsOnTheFlyList as $jsHandle) {
                        if ( ! in_array($jsHandle, $assetsRemovedDecoded[$assetType], true) ) {
                            $assetsRemovedDecoded[$assetType][] = $assetsUnloadedOnTheFly[$assetType][] = $jsHandle;

                            Main::addAssetToUnloadReasonList($jsHandle, $assetType, array(
                                'rule_label' => 'Unload via "wpacu_unload_js" query string'
                            ));
                        }
                    }
                }
            }

            if ( ! empty($assetsUnloadedOnTheFly['styles']) || ! empty($assetsUnloadedOnTheFly['scripts'])) {
                ObjectCache::wpacu_cache_add('wpacu_assets_unloaded_list_page_request', $assetsUnloadedOnTheFly);
            }
            /* [END] Unload CSS/JS on page request for debugging purposes */
        }

        $assetsRemovedPageLevelJson = wp_json_encode( $assetsRemovedDecoded );

        // For caching in case it's called several times
        $this->unloadedAssetsPageLevel[$pageContextKey] = array(
            'array' => $assetsRemovedDecoded,
            'json'  => $assetsRemovedPageLevelJson,
            '_set'  => true,
        );

        if ($pageContextKey !== 'home' && isset($this->unloadedAssetsPageLevel['home'])) {
            unset($this->unloadedAssetsPageLevel['home']); // remove the irrelevant empty array (better for debugging)
        }

        return $returnAsArray ? $assetsRemovedDecoded : $assetsRemovedPageLevelJson;
    }

    /**
     * @param $assetsRemovedPageLevelJson
     * @param $ruleLabel
     *
     * @return void
     */
    public static function addToAssetToUnloadReasonListFromPageLevel($assetsRemovedPageLevelJson, $ruleLabel)
    {
        $assetsRemovedPageLevelArray = wpacuJsonDecodeToArray($assetsRemovedPageLevelJson);

        foreach (array('styles', 'scripts') as $assetType) {
            if ( ! empty($assetsRemovedPageLevelArray[$assetType]) ) {
                foreach ($assetsRemovedPageLevelArray[$assetType] as $assetHandle) {
                    Main::addAssetToUnloadReasonList($assetHandle, $assetType, array(
                        'rule_label' => $ruleLabel
                    ));
                }
            }
        }
    }

	/**
	 * @return int
	 */
	public function getCurrentPostId()
    {
        if ($this->currentPostId > 0) {
            return $this->currentPostId;
        }

        if (defined('WPACU_IS_ELEMENTOR_MAINTENANCE_MODE_TEMPLATE_ID') && Misc::isElementorMaintenanceModeOnForCurrentAdmin()) {
            $this->currentPostId = (int)WPACU_IS_ELEMENTOR_MAINTENANCE_MODE_TEMPLATE_ID;
            return $this->currentPostId;
        }

        // Are we on the `Shop` page from WooCommerce?
        // Only check option if function `is_shop` exists
        $wooCommerceShopPageId = function_exists('is_shop') ? (int)get_option('woocommerce_shop_page_id') : 0;

        // Check if we are on the WooCommerce Shop Page
        // Do not mix the WooCommerce Search Page with the Shop Page
        if (function_exists('is_shop') && is_shop()) {
            $this->currentPostId = $wooCommerceShopPageId;

            if ($this->currentPostId > 0) {
                self::$vars['is_woo_shop_page'] = true;
            }
        } elseif ($wooCommerceShopPageId > 0 && MainFront::isHomePage() && strpos(get_site_url(), '://') !== false) {
            list($siteUrlAfterProtocol) = explode('://', get_site_url());
            $currentPageUrlAfterProtocol = parse_url(site_url(), PHP_URL_HOST) . $_SERVER['REQUEST_URI'];

            if ($siteUrlAfterProtocol !== $currentPageUrlAfterProtocol && (strpos($siteUrlAfterProtocol, '/shop') !== false)) {
                self::$vars['woo_url_not_match'] = true;
            }
        }

	    // Blog Home Page (aka: Posts page) is not a singular page, it's checked separately
        if (MainFront::isBlogPage()) {
        	$this->currentPostId = (int)get_option('page_for_posts');
        }

        // It has to be a single page (no "Posts page")
        if (($this->currentPostId < 1) && MainFront::isSingularPage()) {
            global $post;
            $this->currentPostId = isset($post->ID) ? $post->ID : 0;
        }

        do_action('wpacu_internal_current_post_id_set', $this->currentPostId, $this);

        return $this->currentPostId;
    }

    /**
     * @return \WP_Post|null
     */
    public function getCurrentPost()
    {
        if ($this->currentPost instanceof \WP_Post) {
            return $this->currentPost;
        }

        $currentPostId = $this->getCurrentPostId();

        if ($currentPostId <= 0) {
            return null;
        }

        $currentPost = get_post($currentPostId);

        if (! $currentPost instanceof \WP_Post) {
            return null;
        }

        $this->currentPost = $currentPost;

        return $this->currentPost;
    }

	/**
	 * @return bool
	 */
	public static function isWpDefaultSearchPage()
	{
		// It will not interfere with the WooCommerce search page
		// which is considered to be the "Shop" page that has its own unload rules
		return is_search() && (! (function_exists('is_shop') && is_shop()));
	}

	/**
	 * @param $existingListJson
	 * @param $existingListEmpty
	 *
	 * @return array
     * @noinspection NestedAssignmentsUsageInspection
     */
	public function existingList($existingListJson, $existingListEmpty)
	{
		$validJson = $notEmpty = true;

		if (! $existingListJson) {
			$existingList = $existingListEmpty;
			$notEmpty = false;
		} elseif (is_array($existingListJson)) {
			$existingList = $existingListJson;
		} else {
			$existingList = is_string($existingListJson) ? json_decode($existingListJson, true) : null;

			if (wpacuJsonLastError() !== JSON_ERROR_NONE || ! is_array($existingList)) {
				$validJson = false;
				$existingList = $existingListEmpty;
			}
		}

		return array(
			'list'       => $existingList,
			'valid_json' => $validJson,
			'not_empty'  => $notEmpty
		);
	}

	/**
	 * Situations when the assets will not be prevented from loading
	 * e.g. test mode and a visitor accessing the page, an AJAX request from the Dashboard to print all the assets
     *
	 * @param array $ignoreList
	 *
	 * @return bool
	 */
	public function preventAssetsSettings($ignoreList = array())
	{
		$keyToCheck = 'wpacu_prevent_assets_settings_'.implode('_', $ignoreList);

		if ( isset($GLOBALS[$keyToCheck]) ) {
			return $GLOBALS[$keyToCheck];
		}

		// This request specifically asks for all the assets to be loaded in order to print them in the assets management list
		// This is for the AJAX requests within the Dashboard, thus the admin needs to see all the assets,
		// including ones marked for unload, in case he/she decides to change their rules
		if ( $this->isGetAssetsCall && ! in_array( 'assets_call', $ignoreList ) ) {
			$GLOBALS[$keyToCheck] = true;
			return true;
		}

		// Is test mode enabled? Unload assets ONLY for the admin
		if (self::isTestModeActiveAndVisitorNonAdmin()) {
			$GLOBALS[$keyToCheck] = true;
			return true; // visitors (non-logged in) will view the pages with all the assets loaded
		}

		$isSingularPage = defined('WPACU_CURRENT_PAGE_ID') && WPACU_CURRENT_PAGE_ID > 0 && MainFront::isSingularPage();

		if ($isSingularPage || MainFront::isHomePage()) {
			if ($isSingularPage) {
				$pageOptions = MetaBoxes::getPageOptions( WPACU_CURRENT_PAGE_ID ); // Singular page
			} else {
				$pageOptions = MetaBoxes::getPageOptions(0, 'front_page'); // Home page
			}

			if (isset($pageOptions['no_assets_settings']) && $pageOptions['no_assets_settings']) {
				$GLOBALS[$keyToCheck] = true;
				return true;
			}
		}

		$GLOBALS[$keyToCheck] = false;
		return false;
	}

	/**
	 * @param array $settings
	 *
	 * @return bool
	 */
	public static function isTestModeActiveAndVisitorNonAdmin($settings = array())
    {
        if (defined('WPACU_IS_TEST_MODE_ACTIVE')) {
            return WPACU_IS_TEST_MODE_ACTIVE;
        }

        if (! $settings) {
            $settings = self::instance()->settings;
        }

        $wpacuIsTestModeActive = ! empty($settings['test_mode']) && ! Menu::userCanAccessPlugin();

        define('WPACU_IS_TEST_MODE_ACTIVE', $wpacuIsTestModeActive);

        return $wpacuIsTestModeActive;
    }

    /**
     * @param string $assetHandle
     * @param string $assetType
     * @param array  $ruleData
     *
     * @return void
     */
    public static function addAssetToUnloadReasonList($assetHandle, $assetType, $ruleData)
    {
        $globalsKeyInfo = 'wpacu_filtered_assets_reasons';

        if ( ! isset($GLOBALS[$globalsKeyInfo]) || ! is_array($GLOBALS[$globalsKeyInfo]) ) {
            $GLOBALS[$globalsKeyInfo] = array();
        }

        $GLOBALS[$globalsKeyInfo][$assetType][$assetHandle] = $ruleData;
    }

	/**
	 *
	 */
	public function fallbacks()
	{
		// Fallback for the old filters (e.g., Pro version below 1.2.0.7)
		add_filter('wpacu_filter_styles_list_unload',  function ($list) { return apply_filters('wpacu_filter_styles',  $list); });
		add_filter('wpacu_filter_scripts_list_unload', function ($list) { return apply_filters('wpacu_filter_scripts', $list); });
	}
}
