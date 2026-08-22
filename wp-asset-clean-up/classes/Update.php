<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\SettingsAdmin;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

/**
 * Class Update
 * @package WpAssetCleanUp
 */
class Update
{
    /**
     *
     */
    const NONCE_ACTION_NAME = 'wpacu_data_update';
    /**
     *
     */
    const NONCE_FIELD_NAME = 'wpacu_data_nonce';

	/**
	 * @var bool
	 */
	public $frontEndUpdateTriggered = false;

	/**
	 * @var array
	 */
	public $frontEndUpdateFor = array(
        'homepage' => false,
        'page'     => false
    );

	/**
	 * @var array
	 */
	public $afterSubmitMsg = array();

	/**
	 * Update constructor.
	 */
	public function __construct()
	{
	    $homePageSettingsUpdatedText      = esc_html__('The homepage\'s settings were updated. Please make sure the homepage\'s cache is cleared (if you\'re using a caching plugin or a server-side caching solution) to immediately have the changes applied for every visitor.', 'wp-asset-clean-up');
		$this->afterSubmitMsg['homepage'] = <<<HTML
<span class="dashicons dashicons-yes"></span> {$homePageSettingsUpdatedText}
HTML;

		$pageSettingsUpdatedText      = esc_html__('This page\'s settings were updated. Please make sure the page\'s cache is cleared (if you\'re using a caching plugin or a server-side caching solution) to immediately have the changes applied for every visitor.', 'wp-asset-clean-up');
		$this->afterSubmitMsg['page'] = <<<HTML
<span class="dashicons dashicons-yes"></span> {$pageSettingsUpdatedText}
HTML;

		$invalidNonceText = sprintf(
            __('The changes were not saved because the security nonce has expired (it took over 24 hours since you loaded this page) or it was not sent for verification in the first place because the form was partially submitted due to the input fields being stripped.', 'wp-asset-clean-up'),
            ini_get('max_input_vars')
        );

		$maxInputVarsValue = (int)@ini_get('max_input_vars');

		if ($maxInputVarsValue === 1000) {
			$invalidNonceText .= ' '. sprintf(__('The value of <strong>max_input_vars</strong> is <strong>1000</strong> which is the default one in many hosting accounts. Increase it to a higher number, ideally over %d.'), 4000);
        } elseif ($maxInputVarsValue < 1000) {
			$invalidNonceText .= ' '. sprintf(__('The value of <strong>max_input_vars</strong> is <strong>%d</strong>. That\'s below <strong>1000</strong> (the default one in many hosting accounts). Increase it to a higher number, ideally over %d.'), $maxInputVarsValue, 4000);
		} else {
			$invalidNonceText .= ' '. sprintf(__('The value of <strong>max_input_vars</strong> is <strong>%d</strong>. You might need to increase it to a higher number.'), $maxInputVarsValue);
        }

		$invalidNonceText .= ' <a target="_blank" href="https://www.assetcleanup.com/docs/?p=1346">'.esc_html__('How to fix it?', 'wp-asset-clean-up').'</a>';

		$this->afterSubmitMsg['invalid_nonce_error'] = <<<HTML
<span style="color: #cc0000;" class="dashicons dashicons-dismiss"></span> {$invalidNonceText}
HTML;
	}

	/**
     *
     */
    public function init()
    {
    	// Triggers on front-end view
        add_action('init', array($this, 'triggersAfterInit'), 11);

        // After post/page is saved - update your styles/scripts lists
        // This triggers ONLY in the Dashboard after "Update" button is clicked (on Edit mode)
        add_action('save_post', array($this, 'savePosts'));

        // Are there any plugins such as "WPML Multilingual CMS" that have the same page translated in several languages
        // each having its own ID in the "posts" table? Make sure to sync all of them whenever a specific page setting is applied
        add_filter('wpacu_get_all_assoc_post_ids', array($this, 'getAllAssocPostIds'));

        // Clear cache (via AJAX) only if the user is logged-in (with the right privileges)
	    add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_clear_cache', array($this, 'ajaxClearCache'), PHP_INT_MAX);

	    // After an update, preload the page for the guest view; the preload for the admin is done within /assets/script.min.js
	    add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_preload',     array($this, 'ajaxPreloadGuest'), PHP_INT_MAX);

        // Clear cache (via AJAX) for other plugins after post/page update
	    add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_cache_enabler_clear_cache', array($this, 'ajaxCacheEnablerClearCache'), PHP_INT_MAX);

	    // e.g. when "+" or "-" is used within an asset's row (CSS/JS manager), the state is updated in the background to be remembered
	    add_action( 'wp_ajax_' . WPACU_PLUGIN_ID . '_update_asset_row_state',              array($this, 'ajaxUpdateAssetRowState') );
	    add_action( 'wp_ajax_' . WPACU_PLUGIN_ID . '_area_update_assets_row_state',        array($this, 'ajaxAreaUpdateAssetsRowState') );
    }

	/**
	 *
	 */
	public function triggersAfterInit()
    {
	    if ( ! is_admin() && Main::showAssetsManagerInFrontend()) {
		    if (! empty($_POST)) {
			    $wpacuAction = Misc::isElementorMaintenanceModeOn() ? 'template_redirect' : 'wp';

			    if ($wpacuAction === 'wp') {
				    add_action( 'wp', array( $this, 'frontendUpdate' ), 9 );
			    } else {
				    add_action( 'template_redirect', array( $this, 'frontendUpdate' ), 15 );
                }
		    }

		    add_action('template_redirect', array($this, 'redirectAfterFrontEndUpdate'), 16);
	    }
    }

    /**
     *
     */
    public function frontendUpdate()
    {
        $postId = 0;

        if (Main::instance()->currentPostId > 0) {
            $postId = Main::instance()->currentPostId;
        }

        // Check nonce
        $nonceName = self::NONCE_FIELD_NAME;
        $nonceAction = self::NONCE_ACTION_NAME;

        $updateAction = Misc::getVar('post', 'wpacu_update_asset_frontend');

        if ($updateAction != 1 || ! Main::showAssetsManagerInFrontend()) {
            return;
        }

        // only for admins
        if (! Menu::userCanAccessPlugin()) {
            return;
        }

        if ( ! wp_verify_nonce($_POST[$nonceName], $nonceAction) ) {
            wp_die(
	            $this->afterSubmitMsg['invalid_nonce_error'],
                __('Nonce is missing or has expired', 'wp-asset-clean-up')
            );
        }

        $this->frontEndUpdateTriggered = true;

        // Was the Assets List Layout changed?
        self::updateSettingsChangedOutsideTheMainArea();

        // Form submitted from the homepage
	    // e.g. from a page such as latest blog posts, not a static page that was selected as home page
        if (MainFront::isHomePage() && Misc::getShowOnFront() !== 'page') {
	        $wpacuNoLoadAssets = Misc::getVar('post', WPACU_PLUGIN_ID, array());

            $this->updateFrontPage($wpacuNoLoadAssets);
            return;
        }

	    // Form submitted from a Singular Page
	    // e.g. post, page, custom post type such as 'product' page from WooCommerce, home page (static page selected as front page)

        // Sometimes, there's a singular page set as 404 page (e.g. via "404page – your smart custom 404 error page" plugin)
        if (is_404() && Misc::getVar('post', 'wpacu_is_singular_page')) {
            $postId = (int)$_POST['wpacu_is_singular_page'];
        }

        if ($postId > 0) {
            $this->savePosts($postId);
            return;
        }

        do_action('wpacu_internal_frontend_update_before_common_updates');

        // Any preloads
	    self::updatePreloads();

        // Any handle notes?
	    self::updateHandleNotes();

	    // Any always load it if a user is logged in?
        self::saveGlobalLoadExceptions();

	    // Any ignore deps
	    self::updateIgnoreChild();

	    self::clearTransients();

	    do_action('wpacu_internal_frontend_update_after_common_updates');

        // In case Combine CSS/JS was enabled and there are traces of JSON files in the caching directory
        // Clear them if the caching timing expired as they are not relevant anymore and reduce the disk's space
        OptimizeCommon::clearItemStorageForPost($postId, true);

        self::afterPostUpdate($postId);

        // Note: Cache is cleared (except the JSON files related to CSS/JS combine option) after the post/page is updated via a separate AJAX call
        // To avoid the usage of too much memory (good for shared environments) and avoid any memory related errors showing up to the user which could be confusing
    }

	/**
	 * Specific changes can be applied to "Settings" outside its main area
     * e.g. "Assets List Layout" can be updated within a CSS/JS manager; Its location is in "Settings" -- "Plugin Usage Preferences" -- "Assets List Layout")
	 */
	public static function updateSettingsChangedOutsideTheMainArea()
    {
	    // Was the Assets' List Layout changed?
	    if ($assetsListLayout = Misc::getVar('post', 'wpacu_assets_list_layout')) {
		    $settingsAdminClass = new SettingsAdmin();
            $settingsAdminClass->updateOption('assets_list_layout', $assetsListLayout);
	    }

        // Was the retrieval way changed for the CSS/JS list?
        if ($domGetType = Misc::getVar('post', 'wpacu_dom_get_type')) {
            $settingsAdminClass = new SettingsAdmin();
            $settingsAdminClass->updateOption('dom_get_type', $domGetType);
        }
    }

	/**
	 *
	 */
	public function redirectAfterFrontEndUpdate()
    {
    	// It triggers ONLY on front-end view, when a valid POST request is made
    	if (! $this->frontEndUpdateTriggered || is_admin() || ! Misc::getVar('post', 'wpacu_unload_assets_area_loaded')) {
    		return;
	    }

	    $parseUrl = parse_url($_SERVER['REQUEST_URI']);

	    $location = $parseUrl['path'];

	    $paramsToAdd = array(
		    'wpacu_time'    => time(),
		    'nocache'       => 'true',
            'wpacu_updated' => 'true',
            'wpacu_ignore_no_load_option' => 1
	    );

	    $extraParamsSign = '?';

	    if (isset($parseUrl['query']) && $parseUrl['query']) {
		    parse_str($parseUrl['query'], $existingQueryParams);

		    foreach (array_keys($paramsToAdd) as $paramKey) {
			    if ( isset( $existingQueryParams[$paramKey] ) ) {
				    unset( $existingQueryParams[$paramKey] );
			    }
		    }

		    if (! empty($existingQueryParams)) {
			    $location .= '?'.http_build_query($existingQueryParams);
			    $extraParamsSign = '&';
		    }
	    }

	    $location .= $extraParamsSign . http_build_query($paramsToAdd) . '#wpacu_wrap_assets';

	    set_transient(WPACU_PLUGIN_ID . '_frontend_assets_manager_just_updated', 1, 30);

	    wp_safe_redirect($location);
	    exit();
    }

	/**
     * This could save the existent post and its associated posts if a plugin such as "WPML - The WordPress Multilingual Plugin" is used
     * and there are languages associated with this post
     *
	 * @param $postId
	 *
	 * @return void
	 */
	public function savePosts($postId)
    {
        $allPostIdsToUpdate = apply_filters('wpacu_get_all_assoc_post_ids', $postId);

        foreach ($allPostIdsToUpdate as $postIdToUpdate) {
		    $postObj = get_post( $postIdToUpdate );
		    $this->savePost( $postIdToUpdate, $postObj );
	    }
    }

	/**
	 * @param $postId
	 *
	 * @return array
	 */
	public function getAllAssocPostIds($postId)
    {
	    $allPostIds = array($postId); // default (original post ID)

        // "WPML Multilingual CMS" compatibility: Syncing post changes on all its associated translated posts
        // e.g. if a page in Spanish is having a CSS unloaded, then that unload will also apply for the German version of the page
        // If, for any reason, one wants to stop syncing on translated pages, the following snippet could be used in functions.php (Child Theme)
        // add_filter('wpacu_manage_assets_update_sync_wpml_translated_posts', '__return_false');
        if ( wpacuIsPluginActive('sitepress-multilingual-cms/sitepress.php')
             && apply_filters('wpacu_manage_assets_update_sync_wpml_translated_posts', true) ) {
            $translations = self::getAnyAssocTranslationsForWpml($postId);

            if ( ! empty($translations) ) {
	            foreach ( $translations as $translation ) {
                    if (isset($translation->element_id) && $translation->element_id) {
	                    $allPostIds[] = (int) $translation->element_id;
                    }
	            }
            }
        }

        return array_unique($allPostIds);
    }

	/**
	 * @param $elementId
     * @param string $elementRefType
	 *
	 * @return mixed|null
	 */
	public static function getAnyAssocTranslationsForWpml($elementId, $elementRefType = 'post')
    {
        global $wpdb;

        // This may or might not be the main post which has the default language (usually English)
        // and all other posts were created as an association with this language
        // We'll need to check that
        // Source: https://techoverflow.net/2017/03/08/get-the-original-language-post-id-for-wpml-translated-posts/
        $elementRefTypeLengthForMySql = strlen($elementRefType) + 1;

        $sqlQuery = <<<SQL
SELECT trans2.element_id, trans2.element_type
FROM `{$wpdb->prefix}icl_translations` AS trans1
INNER JOIN `{$wpdb->prefix}icl_translations` AS trans2 ON (trans2.trid = trans1.trid)
WHERE trans1.element_id = '%d' 
  AND SUBSTR(trans1.element_type, 1, {$elementRefTypeLengthForMySql}) = '%s' 
  AND trans2.source_language_code IS NULL
SQL;
	    $sqlQueryPrepared = $wpdb->prepare($sqlQuery, array($elementId, $elementRefType . '_'));

        $result = $wpdb->get_row($sqlQueryPrepared, ARRAY_A);

        if ( ! isset($result['element_type']) ) {
            return array();
        }

	    $type = apply_filters( 'wpml_element_type', $result['element_type'] );
	    $trId = apply_filters( 'wpml_element_trid', false, $elementId, $type );

	    return apply_filters( 'wpml_get_element_translations', array(), $trId, $type );
    }

    /**
     * Save post metadata when a post is saved (not for the "Latest Blog Posts" home page type)
     * Only for post type
     *
     * Dashboard view: triggered via hook
     * Front-end view: triggered by direct call
     *
     * @param $postId
     * @param mixed $post
     */
    public function savePost($postId, $post = '')
    {
	    if (empty($post) || $post === '') {
		    global $post;
	    }

	    if ( ! isset( $post->ID, $post->post_type ) ) {
		    return;
	    }

	    // Has to be a public post type
	    $obj = get_post_type_object($post->post_type);

	    if ( ! isset($obj->public) || $obj->public < 1 ) {
		    return;
	    }

	    // only for admins
	    if (! Menu::userCanAccessPlugin()) {
		    return;
	    }

	    // Any page options set? From the Side Meta Box "Asset CleanUp: Options"
	    // Could be just these fields available in the form (e.g. unavailable CSS/JS manager due to the page set to not load the plugin at all)
	    $this->updatePageOptions($post->ID);

	    // This is triggered only if the plugin's meta box was loaded with the list of assets (either in edit post/page or in "CSS & JS Manager" -> "Manage CSS/JS")
	    // Otherwise, $_POST[WPACU_PLUGIN_ID] will be taken as empty which might be not if there are values in the database
    	if (! Misc::getVar('post', 'wpacu_unload_assets_area_loaded')) {
    	    return;
	    }

	    do_action('wpacu_internal_asset_info_update', 'post', $postId);

	    // Unload it on pages that matches RegEx
	    do_action('wpacu_internal_update_regex_rules', 'unload');

	    // Load it on pages that matches RegEx (global rule, different from "load it on this page")
	    do_action('wpacu_internal_update_regex_rules', 'load_exception');

        $wpacuNoLoadAssets = Misc::getVar('post', WPACU_PLUGIN_ID, array());

        if (is_array($wpacuNoLoadAssets)) {
            global $wpdb;

            $noUpdate = false;

            // Is the list empty?
            if (empty($wpacuNoLoadAssets)) {
                // Remove any row with no results
                $wpdb->delete(
                    $wpdb->postmeta,
                    array('post_id' => $postId, 'meta_key' => '_' . WPACU_PLUGIN_ID . '_no_load')
                );
                $noUpdate = true;
            }

            if (! $noUpdate) {
                $jsonNoAssetsLoadList = wp_json_encode($wpacuNoLoadAssets);

	            if (! add_post_meta($postId, '_' . WPACU_PLUGIN_ID . '_no_load', $jsonNoAssetsLoadList, true)) {
                    update_post_meta($postId, '_' . WPACU_PLUGIN_ID . '_no_load', $jsonNoAssetsLoadList);
                }
            }
        }

        // Was the Assets' List Layout changed?
	    self::updateSettingsChangedOutsideTheMainArea();

        // If globally disabled, make an exception to load for submitted assets
        $this->saveLoadExceptions('post', $postId);
        $this->saveLoadExceptionsPostType($post->post_type);

	    // Add / Remove Site-wide Unloads
	    $this->updateEverywhereUnloads();

        // Any bulk unloads or removed? (e.g. all pages of a certain post type)
	    $this->saveToBulkUnloads($post);
	    $this->removeBulkUnloads(array(), array(), 'post_type', $post->post_type);

        do_action('wpacu_internal_post_update_after_bulk_changes', $post->post_type);

        // Any preloads
	    self::updatePreloads();

	    // Any handle notes
	    self::updateHandleNotes();

	    // Any always load it if user is logged in?
	    self::saveGlobalLoadExceptions();

	    // Any ignore deps
	    self::updateIgnoreChild();

	    add_action('wpacu_admin_notices', array($this, 'pageUpdated'));

	    self::clearTransients();

        // When any unload rule for a hardcoded asset is set in the form, the contents of the tag will be stored in the database
        // To be later viewed in places such as "Overview"
        // This one NEEDS to trigger AFTER all other updates have been made

        do_action('wpacu_internal_after_common_update_rules_stored');

	    // In case Combine CSS/JS was enabled and there are traces of JSON files in the caching directory
        // Clear them if the caching timing expired as they are not relevant anymore and reduce the disk's space
	    OptimizeCommon::clearItemStorageForPost($postId, true);

        self::afterPostUpdate($postId);

	    // Note: Cache is cleared (except the JSON files related to CSS/JS combine option) after the post/page is updated via a separate AJAX call
	    // To avoid the usage of too much memory (good for shared environments) and avoid any memory related errors showing up to the user which could be confusing
    }

	/**
     * This is to update the permalink for the post in "Page Options" if the following option was ever used for the post: "Do not load Asset CleanUp Pro on this page (this will disable any functionality of the plugin)"
     * This takes action when the CSS/JS manager is updated for a specific post (e.g. post, page, attachment, custom post type)
     *
	 * @param $postId
	 */
	public static function afterPostUpdate($postId)
    {
        global $wpdb;

        // The post might have the following page option: "Do not load Asset CleanUp Pro on this page (this will disable any functionality of the plugin)"
        // If the admin changed the slug, we need to update the page URI as well that is used very early in the triggering of the plugin
        // when get_permalink() is not available (e.g. outside any action hook or in the MU plugin)
	    $pageOptionJson = $wpdb->get_var( 'SELECT meta_value FROM `' . $wpdb->prefix . 'postmeta` WHERE post_id=\''.$postId.'\' && meta_key=\'_'.WPACU_PLUGIN_ID.'_page_options\' && meta_value LIKE \'%no_wpacu_load%\'' );

	    $postPageOptions = @json_decode($pageOptionJson, ARRAY_A);

	    if ( ! isset($postPageOptions['no_wpacu_load']) ) {
	        return;
        }

        $postPageOptions['_page_uri'] = Misc::getPageUri($postId);
        update_post_meta($postId, '_' . WPACU_PLUGIN_ID . '_page_options', wp_json_encode(MiscArray::filterList($postPageOptions)));
    }

    /**
     * @param $wpacuNoLoadAssets
     */
    public function updateFrontPage($wpacuNoLoadAssets)
    {
	    // Any page options set? From the Side Meta Box "Asset CleanUp: Options"
	    // Could be just these fields available in the form (e.g. unavailable CSS/JS manager due to the page set to not load the plugin at all)
	    $this->updatePageOptions(0, 'front_page');

	    // Needed in case the user clicks "Update" on a page without assets retrieved
	    // Avoid resetting the existing values
	    if (! Misc::getVar('post', 'wpacu_unload_assets_area_loaded')) {
		    return;
	    }

	    if (! is_array($wpacuNoLoadAssets)) {
            return; // only arrays (empty or not) should be used
        }

	    // Was the Assets List Layout changed?
	    self::updateSettingsChangedOutsideTheMainArea();

        do_action('wpacu_internal_front_page_update_before_common_updates');

        $jsonNoAssetsLoadList = wp_json_encode( $wpacuNoLoadAssets );
	    Misc::addUpdateOption( WPACU_PLUGIN_ID . '_front_page_no_load', $jsonNoAssetsLoadList );

	    // If globally disabled, make an exception to load for submitted assets
        $this->saveLoadExceptions('front_page');

        // Add / Remove Site-wide Unloads
		$this->updateEverywhereUnloads();

	    // Any preloads
	    self::updatePreloads();

		// Any handle notes
        self::updateHandleNotes();

	    // Any always load it if user is logged in?
	    self::saveGlobalLoadExceptions();

        // Any ignore deps
        self::updateIgnoreChild();

	    add_action('wpacu_admin_notices', array($this, 'homePageUpdated'));

	    do_action('wpacu_internal_front_page_update_after_common_updates');

        $this->frontEndUpdateFor['homepage'] = true;

	    self::clearTransients();

	    // Clear all cache
	    // Note: The cache is cleared after the page is saved
    }

	/**
	 *
	 */
	public function homePageUpdated()
    {
	?>
	    <div class="updated notice wpacu-notice is-dismissible">
            <p><?php echo wp_kses(
				    $this->afterSubmitMsg['homepage'],
				    array(
                       'style'  => array(),
                       'class'  => array(),
                       'strong' => array(),
                       'a' => array(
                           'target' => array(),
                           'href'   => array()
                       )
                    )
			    ); ?></p>
	    </div>
	<?php
    }

	/**
	 *
	 */
	public function pageUpdated()
	{
		?>
        <div class="updated notice wpacu-notice is-dismissible">
            <p><?php echo wp_kses(
                    $this->afterSubmitMsg['page'],
		            array(
			            'style'  => array(),
			            'class'  => array(),
			            'strong' => array(),
			            'a' => array(
				            'target' => array(),
				            'href'   => array()
			            )
		            )
                ); ?></p>
        </div>
		<?php
	}

	/**
	 *
	 */
	public function changesNotMadeInvalidNonce()
    {
        ?>
        <div class="notice wpacu-error is-dismissible">
            <p><?php echo wp_kses(
                    $this->afterSubmitMsg['invalid_nonce_error'],
		            array(
			            'style'  => array(),
			            'class'  => array(),
			            'strong' => array(),
			            'a' => array(
				            'target' => array(),
				            'href'   => array()
			            )
		            )
                ); ?></p>
        </div>
        <?php
    }

	/**
	 * Lite: For Singular Page (Post, Page, Custom Post Type), Front Page (Home Page), On All Pages of a specific post type (post, page or custom)
	 * Pro: 'for_pro' would trigger the actions from the premium extension (if available)
     * UPDATE: Since v1.2.9.5, no fallback for both the lite and pro version activated at the same time would work anymore
     * Users need to only keep the PRO version since it's standalone since v1.0.3
	 *
	 * This is the function that clears and updates the load exceptions for any of the requested pages
	 *
	 * This method SHOULD NOT be triggered within an AJAX call
	 *
	 * @param string $type
	 * @param string $postId
	 */
	public function saveLoadExceptions($type = 'post', $postId = '')
    {
        if ( $type === 'post' && ! $postId ) {
            // $postId needs to have a value if $type is a 'post' type
            return;
        }

        $loadExceptions = array('styles' => array(), 'scripts' => array());

        // [Start] Clear existing list first
        if ($type === 'post') {
            delete_post_meta($postId, '_' . WPACU_PLUGIN_ID . '_load_exceptions');
        } elseif ($type === 'front_page') {
            delete_option( WPACU_PLUGIN_ID . '_front_page_load_exceptions');
        }
        elseif ($type === 'for_pro') {
	        // Clear existing list for pages like: taxonomy, 404, search, date etc.
	        do_action( 'wpacu_internal_clear_load_exceptions' );
        }
	    // [End] Clear existing list first

        // Load Exception
        // On this page or page type such as 404, search, etc.
        foreach (array('styles', 'scripts') as $assetType) {
            $postKey    = ($assetType === 'styles') ? 'wpacu_styles_load_it' : 'wpacu_scripts_load_it';
            $optionsKey = ($assetType === 'styles') ? 'wpacu_options_styles' : 'wpacu_options_scripts';

	        if ( ! empty( $_POST[$postKey] ) ) {
		        foreach ( $_POST[$postKey] as $wpacuHandle ) {
			        // Do not append it if the global unload is removed
			        if ( isset( $_POST[$optionsKey][ $wpacuHandle ] ) && $_POST[$optionsKey][ $wpacuHandle ] === 'remove' ) {
				        continue;
			        }
			        $loadExceptions[$assetType][] = $wpacuHandle;
		        }
	        }
        }

        if (! empty($loadExceptions['styles']) || ! empty($loadExceptions['scripts'])) {
            // Default
            $list =  array('styles' => array(), 'scripts' => array());

            // Build list
            foreach ( array('styles', 'scripts') as $assetType ) {
	            if ( ! empty( $loadExceptions[$assetType] ) ) {
		            foreach ( $loadExceptions[$assetType] as $postHandle ) {
			            $list[$assetType][] = $postHandle;
		            }
	            }

	            if (is_array($list[$assetType])) {
		            $list[$assetType] = array_unique($list[$assetType]);
	            }
            }

            $jsonLoadExceptions = wp_json_encode(MiscArray::filterList($list));

            if ( $type === 'post' && (! add_post_meta($postId, '_' . WPACU_PLUGIN_ID . '_load_exceptions', $jsonLoadExceptions, true)) ) {
                update_post_meta( $postId, '_' . WPACU_PLUGIN_ID . '_load_exceptions', $jsonLoadExceptions );
            } elseif ($type === 'front_page') {
	            Misc::addUpdateOption( WPACU_PLUGIN_ID . '_front_page_load_exceptions', $jsonLoadExceptions );
            }
            elseif ($type === 'for_pro') {
	            // Update any load exceptions for pages like: custom poty type archive, taxonomy, 404, search, date etc.
	            do_action( 'wpacu_internal_update_load_exceptions', $jsonLoadExceptions );
            }
        }
    }

	/**
	 *
	 */
	public function saveLoadExceptionsPostType($wpacuPostType)
    {
	    // On all pages belonging to a (custom) post type (e.g. WooCommerce product page)
	    $referenceKey = WPACU_FORM_ASSETS_POST_KEY;

	    $loadExceptions = array('styles' => array(), 'scripts' => array());

	    // From v1.2.0.5
        if (isset($_POST[$referenceKey]['styles']) || isset($_POST[$referenceKey]['scripts'])) {
            foreach (array('styles', 'scripts') as $assetType) {
	            if ( ! empty( $_POST[ $referenceKey ][$assetType] ) ) {
		            foreach ( $_POST[ $referenceKey ][$assetType] as $assetHandle => $values ) {
			            $assetValue = isset( $values['load_it_post_type'] ) && $values['load_it_post_type'] ? $values['load_it_post_type'] : ''; // '1' or ''
			            $loadExceptions[$assetType][ $assetHandle ] = $assetValue;
		            }
	            }
            }
        } else {
	        // Prior to v1.2.0.5
	        foreach (array('styles', 'scripts') as $assetType) {
	            $indexKey = 'wpacu_'.$assetType.'_load_it_post_type';
		        if ( ! empty( $_POST[$indexKey] ) ) {
			        $wpacuPostType = key( $_POST[$indexKey] );
			        $loadExceptions[$assetType] = $_POST[$indexKey][ $wpacuPostType ];
		        }
	        }
        }

	    if ((! empty($loadExceptions['styles']) || ! empty($loadExceptions['scripts'])) && (isset($wpacuPostType) && $wpacuPostType)) {
		    // Default
		    $listToSave = array( 'styles' => array(), 'scripts' => array() );

		    // Build list
		    foreach (array('styles', 'scripts') as $assetType) {
		        $listToSave[$assetType] = ( ! empty( $loadExceptions[$assetType] ) ) ? $loadExceptions[$assetType] : array();
		    }

		    $jsonLoadExceptionsToAdd = wp_json_encode(array($wpacuPostType => $listToSave));

		    $optionToUpdate = WPACU_PLUGIN_ID . '_post_type_load_exceptions';

		    $existingListEmpty = array( $wpacuPostType => array( 'styles' => array(), 'scripts' => array() ) );
		    $existingListJson = get_option($optionToUpdate);

		    $existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
		    $existingList = $existingListData['list'];

		    if ( $existingListJson && is_array($existingList) && ! empty($existingList) ) {
                if (isset($existingList[$wpacuPostType])) {
                    foreach ($listToSave as $assetType => $assetValues) {
                        foreach ($assetValues as $assetHandle => $assetValue) {
	                        $existingList[ $wpacuPostType ][ $assetType ][ $assetHandle ] = $assetValue;
                        }
                    }
                } else {
                    $existingList[$wpacuPostType] = $listToSave;
                }

                // Clear empty (redundant) values
			    foreach ($existingList as $wpacuPostTypeDb => $assetTypes) {
				    foreach ($assetTypes as $assetType => $assetValues) {
				        if (empty($assetValues)) {
				            unset($existingList[$wpacuPostTypeDb][$assetType]);
				        }

				        foreach ($assetValues as $assetHandle => $assetValue) {
					        if ( $assetValue === '' ) {
						        unset( $existingList[ $wpacuPostTypeDb ][ $assetType ][ $assetHandle ] );
					        }

					        if (empty($existingList[ $wpacuPostTypeDb ][ $assetType ])) {
						        unset( $existingList[$wpacuPostTypeDb][$assetType] );
					        }

					        if (empty($existingList[$wpacuPostTypeDb])) {
						        unset($existingList[$wpacuPostTypeDb]);
					        }
				        }
				    }
			    }

			    Misc::addUpdateOption( $optionToUpdate, wp_json_encode($existingList) );
		    } else {
			    Misc::addUpdateOption( $optionToUpdate, $jsonLoadExceptionsToAdd );
		    }
	    }
    }

	/**
	 * e.g. Always load the handle (if unloaded by any rule) if the user is logged-in (applies site-wide)
	 */
	public static function saveGlobalLoadExceptions()
    {
	    $optionToUpdate  = WPACU_PLUGIN_ID . '_global_data';
	    $formTargetKey   = 'wpacu_load_it_logged_in';
	    $targetGlobalKey = 'load_it_logged_in';

	    $referenceKey = WPACU_FORM_ASSETS_POST_KEY;

	    if (! Misc::isValidRequest('post', $referenceKey)) {
		    return;
	    }

	    if (! isset($_POST[$referenceKey]['styles']) && ! isset($_POST[$referenceKey]['scripts'])) {
		    return;
	    }

	    $existingListEmpty = array('styles' => array($targetGlobalKey => array()), 'scripts' => array($targetGlobalKey => array()));
	    $existingListJson = get_option($optionToUpdate);

	    $existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
	    $existingList = $existingListData['list'];

	    foreach (array('styles', 'scripts') as $assetType) {
		    if ( ! empty( $_POST[ $referenceKey ][$assetType] ) ) {
			    foreach ( array_keys( $_POST[ $referenceKey ][$assetType] ) as $assetHandle ) {
			        // The checkbox was ticked (it's not empty)
				    $isSelected = isset( $_POST[$formTargetKey][$assetType][ $assetHandle ] ) && $_POST[$formTargetKey][$assetType][ $assetHandle ];

				    if ( $isSelected ) {
					    $existingList[$assetType][ $targetGlobalKey ][ $assetHandle ] = 1;
				    } else {
					    unset( $existingList[$assetType][ $targetGlobalKey ][ $assetHandle ] );

					    // Are there no values left? Remove the empty array (free space)
					    if (empty($existingList[$assetType][ $targetGlobalKey ])) {
					    	unset($existingList[$assetType][ $targetGlobalKey ]);
					    }
				    }
			    }
		    }
	    }

	    Misc::addUpdateOption($optionToUpdate, wp_json_encode(MiscArray::filterList($existingList)));
    }

	/**
     * This method should ONLY be triggered when the "Asset CleanUp: Options" area is visible
     *
	 * @param $postId
	 * @param string $type
	 */
	public function updatePageOptions($postId, $type = 'post')
	{
		// Is the "Asset CleanUp: Page Options" meta box not loaded?
		// Then do not perform any update below
		if ( ! Misc::getVar( 'post', 'wpacu_page_options_area_loaded', false ) ) {
			return;
		}

		$pageOptions = Misc::getVar( 'post', WPACU_PLUGIN_ID . '_page_options', array() );

	    if ($type === 'post' || $postId > 0) {
		    /*
			 * For posts, pages, custom post types
			 */
		    // No page options? Delete any entry from the database to free up space
		    // instead of updating it as an empty entry
		    if ( empty( $pageOptions ) ) {
			    delete_post_meta( $postId, '_' . WPACU_PLUGIN_ID . '_page_options' );
			    return;
		    }

		    // Save the page URI as it's needed instead of get_permalink() that can't be called too early (e.g. outside an action hook or in a MU plugin)
		    $pageOptions['_page_uri'] = Misc::getPageUri($postId);

		    $pageOptionsJson = wp_json_encode( MiscArray::filterList($pageOptions) );

		    if ( ! add_post_meta( $postId, '_' . WPACU_PLUGIN_ID . '_page_options', $pageOptionsJson, true ) ) {
			    update_post_meta( $postId, '_' . WPACU_PLUGIN_ID . '_page_options', $pageOptionsJson );
		    }
	    } elseif ($type === 'front_page') {
            /*
             * For the homepage (e.g. the latest posts), but not a page set as homepage
             */
		    $existingListJson = get_option(WPACU_PLUGIN_ID . '_global_data');
		    $existingListData = Main::instance()->existingList($existingListJson, array());
		    $existingList = $existingListData['list'];

		    $existingList['page_options']['homepage'] = $pageOptions;

		    Misc::addUpdateOption(WPACU_PLUGIN_ID . '_global_data', wp_json_encode(MiscArray::filterList($existingList)));
	    }
	}

	/**
	 * Triggers either "saveToEverywhereUnloads" or "removeEverywhereUnloads" methods
	 */
	public function updateEverywhereUnloads()
    {
	    /*
	     * Any global (all pages / everywhere) UNLOADS?
	     * Coming from a POST request
	     */
	    $reqStyles  = Misc::getVar('post', 'wpacu_global_unload_styles', array());
	    $reqScripts = Misc::getVar('post', 'wpacu_global_unload_scripts', array());

	    $this->saveToEverywhereUnloads($reqStyles, $reqScripts);

	    /*
	     * Any global (all pages / everywhere) REMOVED?
	     * Coming from a POST request
	     */
	    $this->removeEverywhereUnloads(array(), array(), 'post');
    }

	/**
	 * @param array $reqStyles
	 * @param array $reqScripts
	 */
	public function saveToEverywhereUnloads($reqStyles = array(), $reqScripts = array())
    {
        // Is there any entry already in JSON format?
        $existingListJson = get_option(WPACU_PLUGIN_ID . '_global_unload');

        // Default list as array
        $existingListEmpty = array('styles' => array(), 'scripts' => array());

	    $existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
	    $existingList = $existingListData['list'];

        // Append to the list anything from the POST (if any)
        if (! empty($reqStyles)) {
            foreach ($reqStyles as $reqStyleHandle) {
                $existingList['styles'][] = $reqStyleHandle;
            }
        }

        if (! empty($reqScripts)) {
            foreach ($reqScripts as $reqScriptHandle) {
                $existingList['scripts'][] = $reqScriptHandle;
            }
        }

        // Make sure all entries are unique (no handle duplicates)
        foreach (array('styles', 'scripts') as $assetType) {
	        if ( isset( $existingList[$assetType] ) && is_array( $existingList[$assetType] ) ) {
		        $existingList[$assetType] = array_unique( $existingList[$assetType] );
	        }
        }

        Misc::addUpdateOption(WPACU_PLUGIN_ID . '_global_unload', wp_json_encode(MiscArray::filterList($existingList)));
    }

	/**
	 * @param array $stylesList
	 * @param array $scriptsList
	 * @param string $checkType
	 *
	 * @return bool
     *
     * @noinspection NestedAssignmentsUsageInspection
     */
	public function removeEverywhereUnloads($stylesList = array(), $scriptsList = array(), $checkType = '')
    {
    	if ($checkType === 'post') {
		    $stylesList  = Misc::getVar('post', 'wpacu_options_styles', array());
		    $scriptsList = Misc::getVar('post', 'wpacu_options_scripts', array());
	    }

        $removeStylesList = $removeScriptsList = array();

        $isUpdated = false;

        if (! empty($stylesList)) {
            foreach ($stylesList as $handle => $action) {
                if ($action === 'remove') {
                    $removeStylesList[] = $handle;
                }
            }
        }

        if (! empty($scriptsList)) {
            foreach ($scriptsList as $handle => $action) {
                if ($action === 'remove') {
                    $removeScriptsList[] = $handle;
                }
            }
        }

        $existingListJson = get_option(WPACU_PLUGIN_ID . '_global_unload');

        if (! $existingListJson) {
            return false;
        }

        $existingList = json_decode($existingListJson, true);

        if (wpacuJsonLastError() === JSON_ERROR_NONE) {
            foreach (array('styles', 'scripts') as $assetType) {
                if ($assetType === 'styles') {
                    $list = $removeStylesList;
                } elseif ($assetType === 'scripts') {
                    $list = $removeScriptsList;
                }

                if (empty($list)) {
                    continue;
                }

                foreach ($list as $handle) {
                    $handleKey = isset($existingList[$assetType]) ? array_search($handle, $existingList[$assetType]) : false;

                    if ($handleKey !== false) {
                        unset($existingList[$assetType][$handleKey]);
                        $isUpdated = true;
                    }
                }
            }

            if ($isUpdated) {
                Misc::addUpdateOption(WPACU_PLUGIN_ID . '_global_unload', wp_json_encode(MiscArray::filterList($existingList)));
            }
        }

        return $isUpdated;
    }

	/**
	 * @param string $post
	 */
	public function saveToBulkUnloads($post = '')
    {
        if ($post === '') {
	        global $post;
        }

	    $postType = isset( $post->post_type ) ? $post->post_type : false;

	    // Free Version: It only deals with 'post_type' bulk unloads
	    if ( ! $postType ) {
		    return;
	    }

        $postStyles  = Misc::getVar('post', 'wpacu_bulk_unload_styles', array());
        $postScripts = Misc::getVar('post', 'wpacu_bulk_unload_scripts', array());

        // Is there any entry already in JSON format?
        $existingListJson = get_option( WPACU_PLUGIN_ID . '_bulk_unload');

        // Default list as array
        $existingListEmpty = array(
            'styles'  => array('post_type' => array($postType => array())),
            'scripts' => array('post_type' => array($postType => array()))
        );

	    $existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
	    $existingList = $existingListData['list'];

        // Append to the list anything from the POST (if any)
        // Make sure all entries are unique (no handle duplicates)
        $list = array();

        foreach (array('styles', 'scripts') as $assetType) {
            if ($assetType === 'styles') {
                $list = $postStyles;
            } elseif ($assetType === 'scripts') {
                $list = $postScripts;
            }

            if (empty($list)) {
                continue;
            }

            foreach ($list as $bulkType => $values) {
                if (empty($values)) {
                    continue;
                }

                if ($bulkType === 'post_type') {
                    foreach ($values as $postType => $handles) {
                        if (empty($handles)) {
                            continue;
                        }

                    	foreach (array_unique($handles) as $handle) {
		                    $existingList[ $assetType ]['post_type'][ $postType ][] = $handle;
	                    }

	                    $existingList[ $assetType ]['post_type'][ $postType ] = array_unique($existingList[ $assetType ]['post_type'][ $postType ]);
                    }
                }
            }
        }

	    Misc::addUpdateOption( WPACU_PLUGIN_ID . '_bulk_unload', wp_json_encode(MiscArray::filterList($existingList)));
    }

	/**
     * @param array $stylesList
     * @param array $scriptsList
	 * @param string $bulkType (e.g. 'post_type')
     * @param string $value (e.g. 'post')
     * @param string $checkType e.g. if it's 'post' the $stylesList and $scriptsList lists will be built from the $_POST
     * if $checkType is empty, then $stylesList or $scriptsList need to have values passed directly
	 *
	 * @return bool
     *
     * @noinspection NestedAssignmentsUsageInspection
     */
	public function removeBulkUnloads($stylesList = array(), $scriptsList = array(), $bulkType = '', $value = '', $checkType = '')
    {
        if ($checkType === 'post') {
            $stylesList  = Misc::getVar('post', 'wpacu_options_' . $bulkType . '_styles', array());
            $scriptsList = Misc::getVar('post', 'wpacu_options_' . $bulkType . '_scripts', array());
        }

        if (empty($stylesList) && empty($scriptsList)) {
        	return false;
        }

        $removeStylesList = $removeScriptsList = array();

        $isUpdated = false;

        if (! empty($stylesList)) {
            foreach ($stylesList as $handle => $action) {
                if ($action === 'remove') {
                    $removeStylesList[] = $handle;
                }
            }
        }

        if (! empty($scriptsList)) {
            foreach ($scriptsList as $handle => $action) {
                if ($action === 'remove') {
                    $removeScriptsList[] = $handle;
                }
            }
        }

        $existingListJson = get_option( WPACU_PLUGIN_ID . '_bulk_unload');

        if (! $existingListJson) {
            return false;
        }

        $existingList = json_decode($existingListJson, true);

        if (wpacuJsonLastError() === JSON_ERROR_NONE) {
            $list = array();

            foreach (array('styles', 'scripts') as $assetType) {
                if ($assetType === 'styles') {
                    $list = $removeStylesList;
                } elseif ($assetType === 'scripts') {
                    $list = $removeScriptsList;
                }

                if (empty($list)) {
                    continue;
                }

                if (in_array($bulkType, array('search', 'date', '404')) || (strpos($bulkType, 'custom_post_type_archive_') !== false)) {
                    if ( ! empty($existingList[$assetType][$bulkType]) ) {
                        foreach ($existingList[$assetType][$bulkType] as $handleKey => $handle) {
                            if (in_array($handle, $list)) {
                                unset($existingList[$assetType][$bulkType][$handleKey]);
                                $isUpdated = true;
                            }
                        }
                    }
                } elseif ($value) {
                	// has $value
                    if ( ! empty($existingList[$assetType][$bulkType][$value]) ) {
                        foreach ($existingList[$assetType][$bulkType][$value] as $handleKey => $handle) {
                            if (in_array($handle, $list)) {
                                unset($existingList[$assetType][$bulkType][$value][$handleKey]);
                                $isUpdated = true;
                            }
                        }
                    }
                }
            }

	        Misc::addUpdateOption(WPACU_PLUGIN_ID . '_bulk_unload', wp_json_encode(MiscArray::filterList($existingList)));
        }

        return $isUpdated;
    }

	/**
	 *
	 */
	public static function updatePreloads()
	{
		$useGlobalPost = false;

		if ( ( ! empty($_POST[WPACU_FORM_ASSETS_POST_KEY]['styles']) ) || ( ! empty($_POST[WPACU_FORM_ASSETS_POST_KEY]['scripts']) ) ) {
			$mainVarToUse = self::updatePreloadsAdapt($_POST[WPACU_FORM_ASSETS_POST_KEY]); // New form fields (starting from v1.1.9.9)
		} elseif (Misc::isValidRequest('post', 'wpacu_preloads')) {
			$useGlobalPost = true;
		} else {
			return;
		}

		if (! $useGlobalPost && isset($mainVarToUse['wpacu_preloads'])) {
			$bucketToUse = $mainVarToUse['wpacu_preloads'];
		} elseif (isset($_POST['wpacu_preloads'])) {
			$bucketToUse = $_POST['wpacu_preloads'];
		}

		if (! isset($bucketToUse['styles']) && ! isset($bucketToUse['scripts'])) {
			return;
		}

		$optionToUpdate = WPACU_PLUGIN_ID . '_global_data';
		$globalKey = 'preloads';

		$existingListEmpty = array('styles' => array($globalKey => array()), 'scripts' => array($globalKey => array()));
		$existingListJson = get_option($optionToUpdate);

		$existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
		$existingList = $existingListData['list'];

		foreach (array('styles', 'scripts') as $assetType) {
			if ( ! empty( $bucketToUse[$assetType] ) ) {
				foreach ( $bucketToUse[$assetType] as $assetHandle => $assetPreload ) {
					$assetPreload = trim( $assetPreload );

					if ( $assetPreload === '' && isset( $existingList[$assetType][ $globalKey ][ $assetHandle ] ) ) {
						unset( $existingList[$assetType][ $globalKey ][ $assetHandle ] );
					} elseif ( $assetPreload !== '' ) {
						$existingList[$assetType][ $globalKey ][ $assetHandle ] = $assetPreload;
					}
				}
			}
		}

		Misc::addUpdateOption($optionToUpdate, wp_json_encode(MiscArray::filterList($existingList)));
	}

	/**
	 * @param $mainFormArray
	 *
	 * @return array
	 */
	public static function updatePreloadsAdapt($mainFormArray)
	{
		$wpacuPreloadsList = array();

		foreach (array('styles', 'scripts') as $assetKey) {
			if ( ! empty($mainFormArray[$assetKey]) ) {
				foreach ($mainFormArray[$assetKey] as $assetHandle => $assetData) {
					$wpacuPreloadsList['wpacu_preloads'][$assetKey][$assetHandle] = ''; // default

					if (isset($assetData['preload']) && $assetData['preload']) {
						$wpacuPreloadsList['wpacu_preloads'][ $assetKey ][ $assetHandle ] = $assetData['preload']; // 'basic' or 'async'
					}
				}
			}
		}

		return $wpacuPreloadsList;
	}

	/**
	 *
	 */
	public static function updateHandleNotes()
    {
        if (! Misc::isValidRequest('post', 'wpacu_handle_notes')) {
            return;
        }

        if (! isset($_POST['wpacu_handle_notes']['styles']) && ! isset($_POST['wpacu_handle_notes']['scripts'])) {
            return;
        }

	    $optionToUpdate = WPACU_PLUGIN_ID . '_global_data';
	    $globalKey = 'notes';

	    $existingListEmpty = array('styles' => array($globalKey => array()), 'scripts' => array($globalKey => array()));
	    $existingListJson = get_option($optionToUpdate);

	    $existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
	    $existingList = $existingListData['list'];

	    foreach (array('styles', 'scripts') as $assetType) {
		    if ( ! empty( $_POST['wpacu_handle_notes'][$assetType] ) ) {
			    foreach ( $_POST['wpacu_handle_notes'][$assetType] as $assetHandle => $assetNote ) {
				    $assetNote = stripslashes( $assetNote );

				    if ( $assetNote === '' && isset( $existingList[$assetType][ $globalKey ][ $assetHandle ] ) ) {
					    unset( $existingList[$assetType][ $globalKey ][ $assetHandle ] );
				    } elseif ( $assetNote !== '' ) {
					    $existingList[$assetType][ $globalKey ][ $assetHandle ] = $assetNote;
				    }
			    }
		    }
	    }

	    Misc::addUpdateOption($optionToUpdate, wp_json_encode(MiscArray::filterList($existingList)));
    }

	/**
	 * @param array $mainVarToUse
	 */
	public static function updateIgnoreChild($mainVarToUse = array())
	{
		// No $mainVarToUse passed? Then it's a $_POST
		// Check if $_POST is empty via Misc::isValidRequest()
		$useGlobalPost = false;

		if (empty($mainVarToUse)) {
		    if ( ( ! empty($_POST[WPACU_FORM_ASSETS_POST_KEY]['styles']) ) || ( ! empty($_POST[WPACU_FORM_ASSETS_POST_KEY]['scripts']) ) ) {
			    $mainVarToUse = self::updateIgnoreChildAdapt($_POST[WPACU_FORM_ASSETS_POST_KEY]); // New form fields (starting from v1.1.9.9)
		    } elseif (Misc::isValidRequest('post', 'wpacu_ignore_child')) {
			    $useGlobalPost = true;
			} else {
		        return;
		    }
		}

		if (! $useGlobalPost && isset($mainVarToUse['wpacu_ignore_child'])) {
			$bucketToUse = $mainVarToUse['wpacu_ignore_child'];
		} elseif (isset($_POST['wpacu_ignore_child'])) {
			$bucketToUse = $_POST['wpacu_ignore_child'];
		}

		if (! isset($bucketToUse['styles']) && ! isset($bucketToUse['scripts'])) {
			return;
		}

		$optionToUpdate = WPACU_PLUGIN_ID . '_global_data';
		$globalKey = 'ignore_child';

		$existingListEmpty = array('styles' => array($globalKey => array()), 'scripts' => array($globalKey => array()));
		$existingListJson = get_option($optionToUpdate);

		$existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
		$existingList = $existingListData['list'];

		foreach (array('styles','scripts') as $assetType) {
			if ( ! empty( $bucketToUse[$assetType] ) ) {
				foreach ( $bucketToUse[$assetType] as $assetHandle => $assetVal ) {
					$assetVal = trim( $assetVal );

					if ( $assetVal === '' && isset( $existingList[$assetType][ $globalKey ][ $assetHandle ] ) ) {
						unset( $existingList[$assetType][ $globalKey ][ $assetHandle ] );
					} elseif ( $assetVal !== '' ) {
						$existingList[$assetType][ $globalKey ][ $assetHandle ] = $assetVal;
					}
				}
			}
		}

		Misc::addUpdateOption($optionToUpdate, wp_json_encode(MiscArray::filterList($existingList)));
	}

	/**
	 * @param $mainFormArray
	 *
	 * @return array
	 */
	public static function updateIgnoreChildAdapt($mainFormArray)
    {
	    $wpacuIgnoreChildList = array();

        foreach (array('styles', 'scripts') as $assetKey) {
            if ( ! empty($mainFormArray[$assetKey]) ) {
                foreach ($mainFormArray[$assetKey] as $assetHandle => $assetData) {
	                $wpacuIgnoreChildList['wpacu_ignore_child'][$assetKey][$assetHandle] = ''; // default

                    if (isset($assetData['ignore_child']) && $assetData['ignore_child']) {
	                    $wpacuIgnoreChildList['wpacu_ignore_child'][ $assetKey ][ $assetHandle ] = 1;
                    }
                }
            }
        }

        return $wpacuIgnoreChildList;
	}

	/**
     * This function is called via AJAX whenever "+" or "-" is used on an asset's row.
     *
	 * @param mixed $newState
	 * @param mixed $handle
	 * @param mixed $handleFor
	 *
	 * @return array|false
	 */
	public static function updateHandleRowStatus($newState, $handle, $handleFor)
	{
        $newState  = self::sanitizeAssetRowState($newState);
        $handle    = self::sanitizeAssetHandle($handle);
        $handleFor = self::sanitizeAssetHandleType($handleFor);

        if ($newState === '' || $handle === '' || $handleFor === '') {
            return false;
        }

        list($optionToUpdate, $globalKey, $existingList) = self::getAssetRowStatusStorage();

        self::applyAssetRowStatusToList($existingList, $newState, $handle, $handleFor, $globalKey);
        self::saveAssetRowStatusStorage($optionToUpdate, $existingList);

        return self::getContractedAssetRowsFromList($existingList, $globalKey);
	}

    /**
     * @return array
     */
    private static function getAssetRowStatusStorage()
    {
        $optionToUpdate    = WPACU_PLUGIN_ID . '_global_data';
        $globalKey         = 'handle_row_contracted';
        $existingListEmpty = array(
            'styles'  => array($globalKey => array()),
            'scripts' => array($globalKey => array()),
        );
        $existingListJson = get_option($optionToUpdate);
        $existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);

        return array($optionToUpdate, $globalKey, $existingListData['list']);
    }

    /**
     * @param array  $existingList
     * @param string $newState
     * @param string $handle
     * @param string $handleFor
     * @param string $globalKey
     *
     * @return void
     */
    private static function applyAssetRowStatusToList(&$existingList, $newState, $handle, $handleFor, $globalKey)
    {
        $keyList = ($handleFor === 'style') ? 'styles' : 'scripts';

        if ( ! isset($existingList[$keyList]) || ! is_array($existingList[$keyList]) ) {
            $existingList[$keyList] = array();
        }

        if ( ! isset($existingList[$keyList][$globalKey]) || ! is_array($existingList[$keyList][$globalKey]) ) {
            $existingList[$keyList][$globalKey] = array();
        }

        if ($newState === 'expanded') {
            unset($existingList[$keyList][$globalKey][$handle]);
        } else {
            $existingList[$keyList][$globalKey][$handle] = 1;
        }
    }

    /**
     * @param string $optionToUpdate
     * @param array  $existingList
     *
     * @return void
     */
    private static function saveAssetRowStatusStorage($optionToUpdate, $existingList)
    {
        Misc::addUpdateOption($optionToUpdate, wp_json_encode(MiscArray::filterList($existingList)));
    }

    /**
     * @param array  $existingList
     * @param string $globalKey
     *
     * @return array
     */
    private static function getContractedAssetRowsFromList($existingList, $globalKey)
    {
        $toReturn = array('styles' => array(), 'scripts' => array());

        if (isset($existingList['styles'][$globalKey]) && is_array($existingList['styles'][$globalKey])) {
            $toReturn['styles'] = $existingList['styles'][$globalKey];
        }

        if (isset($existingList['scripts'][$globalKey]) && is_array($existingList['scripts'][$globalKey])) {
            $toReturn['scripts'] = $existingList['scripts'][$globalKey];
        }

        return $toReturn;
    }

    /**
     * @param mixed $newState
     *
     * @return string
     */
    private static function sanitizeAssetRowState($newState)
    {
        $newState = is_scalar($newState) ? sanitize_key(wp_unslash((string)$newState)) : '';

        return in_array($newState, array('contracted', 'expanded'), true) ? $newState : '';
    }

    /**
     * @param mixed $handleFor
     *
     * @return string
     */
    private static function sanitizeAssetHandleType($handleFor)
    {
        $handleFor = is_scalar($handleFor) ? sanitize_key(wp_unslash((string)$handleFor)) : '';

        return in_array($handleFor, array('style', 'script'), true) ? $handleFor : '';
    }

    /**
     * @param mixed $handle
     *
     * @return string
     */
    private static function sanitizeAssetHandle($handle)
    {
        if ( ! is_scalar($handle) ) {
            return '';
        }

        $handle = trim(sanitize_text_field(wp_unslash((string)$handle)));

        if ($handle === '' || strlen($handle) > 500 || strpos($handle, "\0") !== false) {
            return '';
        }

        return $handle;
    }

	/**
     * This is triggered automatically and sets a transient with the handles info
     * It doesn't require any manual action from the user
     *
	 * @param $assetList
	 */
	public static function updateHandlesInfo($assetList)
	{
		$optionToUpdate = WPACU_PLUGIN_ID . '_global_data';
		$globalKey = 'assets_info';

		$existingListEmpty = array('styles' => array($globalKey => array()), 'scripts' => array($globalKey => array()));
		$existingListJson = get_option($optionToUpdate);

		$existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
		$existingList = $existingListData['list'];

		// $assetKey could be 'styles' or 'scripts'
		foreach ($assetList as $assetType => $assetDataHandleList) {
			if (empty($assetDataHandleList) || ! in_array($assetType, array('styles', 'scripts'))) {
				continue;
			}

			foreach ($assetDataHandleList as $assetObj) {
				$assetArray = (array)$assetObj;

                $handleMaybeAliases = Main::maybeAssignUniqueHandleName($assetArray['handle'], 'scripts');
				$assetHandle = $handleMaybeAliases['handle_ref'];

				// Strip other unused information including the 'handle' (no need to have it twice as it's already in one of the array's keys)
				unset( $assetArray['handle'], $assetArray['textdomain'], $assetArray['translations_path'] );

				// Some handles don't have a "src" value such as "woocommerce-inline"
				if (isset($assetArray['src']) && $assetArray['src']) {
					$assetArray['src'] = Misc::assetFromHrefToRelativeUri( $assetArray['src'], $assetType );
				}

                $assetArray = apply_filters(
					'wpacu_internal_update_handles_info_asset_array',
					$assetArray,
					$assetType,
					$globalKey,
					$assetHandle,
					$existingList
				);

				if ($assetArray === false) {
					continue;
				}

                $existingList[$assetType][$globalKey][$assetHandle] = $assetArray;
			}
		}

		update_option($optionToUpdate, wp_json_encode(MiscArray::filterList($existingList)));
	}

	/**
	 *
	 */
	public static function clearTransients()
	{
		delete_transient(WPACU_PLUGIN_ID. '_total_unloaded_assets_all');
		delete_transient(WPACU_PLUGIN_ID. '_total_unloaded_assets_per_page');
	}

	/**
	 * This is triggered when /admin/admin-ajax.php is called (default WordPress AJAX handler)
	 */
	public function ajaxClearCache()
	{
		if ( ! isset($_REQUEST['wpacu_nonce']) ) {
			echo 'Error: The security nonce was not sent for verification. Location: '.__METHOD__;
			return;
		}

		if ( ! wp_verify_nonce($_REQUEST['wpacu_nonce'], 'wpacu_ajax_clear_cache_nonce') ) {
			echo 'Error: The security check has failed. Location: '.__METHOD__;
			exit();
		}

	    if (! Menu::userCanAccessPlugin()) {
	        echo 'Error: Not enough privileges to clear the cache.';
	        exit();
        }

		OptimizeCommon::clearCache();

	    exit();
	}

	/**
	 * @return void
	 */
	public function ajaxCacheEnablerClearCache()
    {
	    if ( ! isset($_REQUEST['wpacu_nonce']) ) {
		    echo 'Error: The security nonce was not sent for verification. Location: '.__METHOD__;
		    return;
	    }

	    if ( ! wp_verify_nonce($_REQUEST['wpacu_nonce'], 'wpacu_ajax_clear_cache_enabler_cache_nonce') ) {
		    echo 'Error: The security check has failed. Location: '.__METHOD__;
		    exit();
	    }

	    if (! Menu::userCanAccessPlugin()) {
		    echo 'Error: Not enough privileges to clear the cache.';
		    exit();
	    }

        OptimizeCommon::clearCacheEnablerCache('ajax_call');
    }

	/**
	 * Return a normalized scheme/host/port key for an HTTP(S) URL.
	 *
	 * @param string $url
	 *
	 * @return string|false
	 */
	private static function getHttpUrlLocationKey($url)
	{
		$urlParts = wp_parse_url($url);

		if ( ! is_array($urlParts)
		     || empty($urlParts['scheme'])
		     || empty($urlParts['host'])
		     || isset($urlParts['user'])
		     || isset($urlParts['pass'])
		) {
			return false;
		}

		$scheme = strtolower($urlParts['scheme']);

		if ( ! in_array($scheme, array('http', 'https'), true) ) {
			return false;
		}

		$host = strtolower(rtrim($urlParts['host'], '.'));
		$port = isset($urlParts['port']) ? (int)$urlParts['port'] : ($scheme === 'https' ? 443 : 80);

		if ($host === '' || $port < 1 || $port > 65535) {
			return false;
		}

		return $scheme . '://' . $host . ':' . $port;
	}

	/**
	 * Guest preloading is allowed only for the exact HTTP(S) location configured
	 * as the WordPress or front-end site URL.
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	private static function isGuestPreloadUrlAllowed($url)
	{
		$targetLocationKey = self::getHttpUrlLocationKey($url);

		if ($targetLocationKey === false) {
			return false;
		}

		foreach (array(home_url('/'), site_url('/')) as $allowedSiteUrl) {
			$allowedSiteUrlVariants = array(
				$allowedSiteUrl,
				set_url_scheme($allowedSiteUrl, 'http'),
				set_url_scheme($allowedSiteUrl, 'https')
			);

			foreach ($allowedSiteUrlVariants as $allowedSiteUrlVariant) {
				if (self::getHttpUrlLocationKey($allowedSiteUrlVariant) === $targetLocationKey) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Fetch a same-site URL while validating every redirect. Redirect handling is
	 * kept inside the plugin so this remains safe on the minimum supported
	 * WordPress versions as well.
	 *
	 * @param string $url
	 * @param int    $maxRedirects
	 *
	 * @return array|\WP_Error
	 */
	private static function remoteGetGuestPreloadUrl($url, $maxRedirects = 3)
	{
		$currentUrl   = $url;
		$maxRedirects = max(0, (int)$maxRedirects);

		for ($redirectCount = 0; $redirectCount <= $maxRedirects; $redirectCount++) {
			if ( ! self::isGuestPreloadUrlAllowed($currentUrl) ) {
				return new \WP_Error(
					'wpacu_guest_preload_url_not_allowed',
					'The preload URL does not match the configured site URL.'
				);
			}

			$response = wp_remote_get($currentUrl, array(
				'timeout'     => 15,
				'redirection' => 0
			));

			if (is_wp_error($response)) {
				return $response;
			}

			$responseCode = (int)wp_remote_retrieve_response_code($response);

			if ($responseCode < 300 || $responseCode > 399) {
				return $response;
			}

			$redirectLocation = wp_remote_retrieve_header($response, 'location');

			if (is_array($redirectLocation)) {
				$redirectLocation = end($redirectLocation);
			}

			if ( ! is_string($redirectLocation) || trim($redirectLocation) === '' ) {
				return $response;
			}

			if ($redirectCount === $maxRedirects) {
				return new \WP_Error(
					'wpacu_guest_preload_too_many_redirects',
					'The preload request exceeded the allowed number of redirects.'
				);
			}

			$currentUrl = \WP_Http::make_absolute_url(trim($redirectLocation), $currentUrl);
		}

		return new \WP_Error(
			'wpacu_guest_preload_request_failed',
			'The preload request could not be completed.'
		);
	}

	/**
	 * This is triggered when /admin/admin-ajax.php is called (default WordPress AJAX handler)
	 */
	public function ajaxPreloadGuest()
	{
		// Check nonce
		if ( ! isset($_POST['wpacu_nonce']) || ! wp_verify_nonce($_POST['wpacu_nonce'], 'wpacu_ajax_preload_url_nonce') ) {
			echo 'Error: The security nonce is not valid.';
			exit();
		}

		// Check privileges
		if ( ! Menu::userCanAccessPlugin() ) {
			echo 'Error: Not enough privileges to perform this action.';
			exit();
		}

		$pageUrl = isset($_POST['page_url']) && is_string($_POST['page_url'])
			? esc_url_raw(wp_unslash($_POST['page_url']))
			: '';

		if ( ! self::isGuestPreloadUrlAllowed($pageUrl) ) {
			echo 'Error: The requested URL does not match the configured WordPress or Site Address URL.';
			exit();
		}

		$pageUrlPreload = add_query_arg(array('wpacu_preload' => 1), $pageUrl);

		if ( ! self::isGuestPreloadUrlAllowed($pageUrlPreload) ) {
			echo 'The preload URL is not valid.';
			exit();
		}

		$response = self::remoteGetGuestPreloadUrl($pageUrlPreload);

		if (is_wp_error($response)) {
			// Any error generated during the fetch? Print it
			echo 'Error: '.$response->get_error_code();
		} else {
			// No errors
			echo 'Status Code: '.wp_remote_retrieve_response_code($response).' /  Page URL (preload): ' . $pageUrlPreload . "\n\n";
			echo wp_remote_retrieve_body($response);
		}

		exit();
	}

	/**
	 * Persist the expanded/contracted state for one asset row.
	 *
	 * @return void
	 */
	public function ajaxUpdateAssetRowState()
    {
        if ( ! Menu::userCanAccessPlugin() ) {
            wp_send_json_error(array('message' => 'You are not allowed to update asset row states.'));
        }

        $nonce = isset($_POST['wpacu_nonce']) && is_string($_POST['wpacu_nonce'])
            ? wp_unslash($_POST['wpacu_nonce'])
            : '';

        if ( ! wp_verify_nonce($nonce, 'wpacu_update_asset_row_state_nonce') ) {
            wp_send_json_error(array('message' => 'The security check has failed.'));
        }

        $requestFlag = isset($_POST['wpacu_update_asset_row_state']) && is_string($_POST['wpacu_update_asset_row_state'])
            ? wp_unslash($_POST['wpacu_update_asset_row_state'])
            : '';

        if ($requestFlag !== 'yes') {
            wp_send_json_error(array('message' => 'The request is not valid.'));
        }

        $newContractedList = self::updateHandleRowStatus(
            isset($_POST['wpacu_asset_row_state']) ? $_POST['wpacu_asset_row_state'] : '',
            isset($_POST['wpacu_handle']) ? $_POST['wpacu_handle'] : '',
            isset($_POST['wpacu_handle_for']) ? $_POST['wpacu_handle_for'] : ''
        );

        if ($newContractedList === false) {
            wp_send_json_error(array('message' => 'The asset row state parameters are not valid.'));
        }

        wp_send_json_success(array('contracted' => $newContractedList));
    }

	/**
     * Persist the state for all asset rows in an area with one option write.
     *
	 * @return void
	 */
	public function ajaxAreaUpdateAssetsRowState()
    {
        if ( ! Menu::userCanAccessPlugin() ) {
            wp_send_json_error(array('message' => 'You are not allowed to update asset row states.'));
        }

        $nonce = isset($_POST['wpacu_nonce']) && is_string($_POST['wpacu_nonce'])
            ? wp_unslash($_POST['wpacu_nonce'])
            : '';

        if ( ! wp_verify_nonce($nonce, 'wpacu_area_update_assets_row_state_nonce') ) {
            wp_send_json_error(array('message' => 'The security check has failed.'));
        }

        $requestFlag = isset($_POST['wpacu_area_update_assets_row_state']) && is_string($_POST['wpacu_area_update_assets_row_state'])
            ? wp_unslash($_POST['wpacu_area_update_assets_row_state'])
            : '';

        if ($requestFlag !== 'yes') {
            wp_send_json_error(array('message' => 'The request is not valid.'));
        }

        $newState = self::sanitizeAssetRowState(
            isset($_POST['wpacu_area_assets_row_state']) ? $_POST['wpacu_area_assets_row_state'] : ''
        );
        $rawHandles = isset($_POST['wpacu_area_handles']) && is_array($_POST['wpacu_area_handles'])
            ? $_POST['wpacu_area_handles']
            : array();

        if ($newState === '' || empty($rawHandles)) {
            wp_send_json_error(array('message' => 'The asset row state parameters are not valid.'));
        }

        list($optionToUpdate, $globalKey, $existingList) = self::getAssetRowStatusStorage();
        $updated = 0;
        $seen    = array();

        foreach ($rawHandles as $areaAssetHandleFormat) {
            if ( ! is_scalar($areaAssetHandleFormat) ) {
                continue;
            }

            $areaAssetHandleFormat = sanitize_text_field(wp_unslash((string)$areaAssetHandleFormat));
            $separatorPosition     = strrpos($areaAssetHandleFormat, '_');

            if ($separatorPosition === false) {
                continue;
            }

            $handle    = self::sanitizeAssetHandle(substr($areaAssetHandleFormat, 0, $separatorPosition));
            $handleFor = self::sanitizeAssetHandleType(substr($areaAssetHandleFormat, $separatorPosition + 1));
            $uniqueKey = $handleFor . ':' . $handle;

            if ($handle === '' || $handleFor === '' || isset($seen[$uniqueKey])) {
                continue;
            }

            $seen[$uniqueKey] = true;
            self::applyAssetRowStatusToList($existingList, $newState, $handle, $handleFor, $globalKey);
            $updated++;
        }

        if ($updated < 1) {
            wp_send_json_error(array('message' => 'No valid asset rows were supplied.'));
        }

        self::saveAssetRowStatusStorage($optionToUpdate, $existingList);

        wp_send_json_success(array(
            'updated'    => $updated,
            'contracted' => self::getContractedAssetRowsFromList($existingList, $globalKey),
        ));
    }

}
