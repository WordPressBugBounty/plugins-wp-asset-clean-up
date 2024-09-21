<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\SettingsAdminOnlyForAdmin;

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
	 * @var
	 */
	public $fetchUrl;

    // [wpacu_lite]
    /**
     * @var
     */
    public $isUpdateable = true;
    // [/wpacu_lite]

	/**
	 * @var int
	 */
	public $currentPostId = 0;

	/**
	 * @var array
	 */
	public $currentPost = array();

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

        // Menu::userCanAccessAssetCleanUp() has to be called in 'init' (not too early)
        add_action('init', function() {
            // Conditions
            // 1) User has rights to manage the assets and the option is enabled in plugin's Settings
            // 2) Not an AJAX call from the Dashboard
            // 3) Not inside the Dashboard
            self::instance()->isFrontendEditView =
                Menu::userCanAccessAssetCleanUp() && AssetsManager::instance()->frontendShow() // 1
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
	 * @param $for
	 * @return array
	 */
	public function unloadAssetOnTheFly($for)
    {
	    $assetType = ($for === 'css') ? 'styles' : 'scripts';
	    $assetIndex = 'wpacu_unload_'.$for;

        if (! ($unloadAsset = Misc::getVar('get', $assetIndex))) {
            return array();
        }

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
	    $exceptionsListJson = '';

	    // Post or Post of the Homepage (if chosen in the Dashboard)
	    if ( $type === 'post' || ( Misc::getShowOnFront() === 'page' && $postId ) ) {
            $exceptionsListJson = get_post_meta( $postId, '_' . WPACU_PLUGIN_ID . '_load_exceptions', true );
        }

	    // The home page could also be the list of the latest blog posts
	    if ( $type === 'front_page' ) {
            $exceptionsListJson = get_option( WPACU_PLUGIN_ID . '_front_page_load_exceptions' );
        }

	    if ( $exceptionsListJson ) {
            $exceptionsList = json_decode( $exceptionsListJson, true );

            if (wpacuJsonLastError() !== JSON_ERROR_NONE ) {
                $exceptionsList = $exceptionsListDefault;
            }
        }

	    // Any exceptions on the fly added for debugging purposes? Make sure to grab them
        if (isset($_GET['wpacu_load_styles']) || isset($_GET['wpacu_load_scripts'])) {
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
	 * @return \array[][]
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

	    $exceptionsList = @json_decode($exceptionsListJson, true);

	    // Issues with decoding the JSON file? Return an empty list
	    if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
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

        $existingListAll = json_decode($existingListAllJson, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
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
                $assetsInfo[$assetKey] = Misc::filterList( $wpacuGlobalData[$assetKey]['assets_info'] );
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
     * This method retrieves only the assets that are unloaded per page
     * Including 404, date and search pages (they are considered as ONE page with the same rules for any URL variation)
     *
     * @param int $postId
     * @param bool $returnAsArray
     *
     * @return string|array (The returned value must be a JSON one)
     * @noinspection NestedAssignmentsUsageInspection
     */
    public function getAssetsUnloadedPageLevel($postId = 0, $returnAsArray = false)
    {
	    // Post Type (Overwrites 'front' - home page - if we are in a singular post)
	    $postIdRef = $postId;

	    if ($postId === 0) {
		    $postId = (int)$this->getCurrentPostId();

			if ($postId === 0) {
				$postIdRef = 'home';
			}
	    }

		if (isset($this->unloadedAssetsPageLevel[$postIdRef]['_set']) && $this->unloadedAssetsPageLevel[$postIdRef]['_set']) {
			if ($returnAsArray) {
				return $this->unloadedAssetsPageLevel[$postIdRef]['array']; // Array
			}

			return $this->unloadedAssetsPageLevel[$postIdRef]['json']; // JSON format
		}

        $defaultEmptyArrayValue = array( 'styles' => array(), 'scripts' => array() );

        $isInAdminPageViaAjax = is_admin() && wpacuIsDefinedConstant('DOING_AJAX');

	    $assetsRemovedPageLevel = wp_json_encode( $defaultEmptyArrayValue );

        // For Home Page (latest blog posts)
        if ( $postId < 1 && ! isset($_REQUEST['tag_id']) && ($isInAdminPageViaAjax || MainFront::isHomePage()) ) {
            $assetsRemovedPageLevel = get_option( WPACU_PLUGIN_ID . '_front_page_no_load' );
        } elseif ( $postId > 0 ) { // Singular Page
            $assetsRemovedPageLevel = get_post_meta( $postId, '_' . WPACU_PLUGIN_ID . '_no_load', true );
        }

        @json_decode( $assetsRemovedPageLevel );

        if ( empty( $assetsRemovedPageLevel ) || $assetsRemovedPageLevel === '[]' || wpacuJsonLastError() !== JSON_ERROR_NONE ) {
            // Reset value to a JSON formatted one
	        $assetsRemovedPageLevel = wp_json_encode( $defaultEmptyArrayValue );
        }

        $assetsRemovedDecoded = json_decode( $assetsRemovedPageLevel, ARRAY_A );

        if (! isset($assetsRemovedDecoded['styles'])) {
            $assetsRemovedDecoded['styles'] = array();
        }

        if (! isset($assetsRemovedDecoded['scripts'])) {
            $assetsRemovedDecoded['scripts'] = array();
        }

        /* [START] Unload CSS/JS on page request for debugging purposes */
        $assetsUnloadedOnTheFly = $defaultEmptyArrayValue;

        if ( Misc::getVar( 'get', 'wpacu_unload_css' ) ) {
            $cssOnTheFlyList = $this->unloadAssetOnTheFly( 'css' );

            if ( ! empty( $cssOnTheFlyList ) ) {
                foreach ( $cssOnTheFlyList as $cssHandle ) {
                    if ( ! in_array( $cssHandle, $assetsRemovedDecoded['styles'] ) ) {
                        $assetsRemovedDecoded['styles'][] = $assetsUnloadedOnTheFly['styles'][] = $cssHandle;
                    }
                }
            }
        }

        if ( Misc::getVar( 'get', 'wpacu_unload_js' ) ) {
            $jsOnTheFlyList = $this->unloadAssetOnTheFly( 'js' );

            if ( ! empty( $jsOnTheFlyList ) ) {
                foreach ( $jsOnTheFlyList as $jsHandle ) {
                    if ( ! in_array( $jsHandle, $assetsRemovedDecoded['scripts'] ) ) {
                        $assetsRemovedDecoded['scripts'][] = $assetsUnloadedOnTheFly['scripts'][] = $jsHandle;
                    }
                }
            }
        }

		if ( ! empty($assetsUnloadedOnTheFly['styles']) || ! empty($assetsUnloadedOnTheFly['scripts']) ) {
			ObjectCache::wpacu_cache_add( 'wpacu_assets_unloaded_list_page_request', $assetsUnloadedOnTheFly );
		}
        /* [END] Unload CSS/JS on page request for debugging purposes */

	    $assetsRemovedPageLevelJson = wp_json_encode( $assetsRemovedDecoded );
        $assetsRemovedPageLevel = (array)@json_decode($assetsRemovedPageLevelJson);

        // Make sure there are no objects in the array to avoid any PHP errors later on in PHP 8+
        foreach ( array( 'styles', 'scripts' ) as $assetType ) {
	        if ( isset( $assetsRemovedPageLevel[ $assetType ] ) ) {
		        $assetsRemovedPageLevel[ $assetType ] = (array)$assetsRemovedPageLevel[ $assetType ];
	        }
        }

	    $this->unloadedAssetsPageLevel[$postIdRef]['array'] = $assetsRemovedPageLevel;
	    $this->unloadedAssetsPageLevel[$postIdRef]['json']  = $assetsRemovedPageLevelJson;
	    $this->unloadedAssetsPageLevel[$postIdRef]['_set']  = true;

	    if ($returnAsArray) {
			return $this->unloadedAssetsPageLevel[$postIdRef]['array'];
		}

	    return $this->unloadedAssetsPageLevel[$postIdRef]['json'];
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

        // [wpacu_lite]
        // Undetectable? The page is not a singular one nor the home page
        // It's likely an archive, category page (WooCommerce), 404 page manageable in the Pro version etc.
        if ( ! $this->currentPostId && ! MainFront::isHomePage() ) {
            $this->isUpdateable = false;
        }
        // [/wpacu_lite]

        return $this->currentPostId;
    }

    /**
     * @return array|null|\WP_Post
     */
    public function getCurrentPost()
    {
        // Already set? Return it
        if (! empty($this->currentPost)) {
            return $this->currentPost;
        }

        // Not set? Create and return it
        if (! $this->currentPost && $this->getCurrentPostId() > 0) {
            $this->currentPost = get_post($this->getCurrentPostId());
            return $this->currentPost;
        }

        // Empty
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
		} else {
			$existingList = json_decode($existingListJson, true);

			if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
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

        $wpacuIsTestModeActive = ! empty($settings['test_mode']) && ! Menu::userCanAccessAssetCleanUp();

        define('WPACU_IS_TEST_MODE_ACTIVE', $wpacuIsTestModeActive);

        return $wpacuIsTestModeActive;
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
