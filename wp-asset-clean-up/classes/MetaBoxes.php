<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\MainAdmin;

/**
 * Class MetaBoxes
 * @package WpAssetCleanUp
 */
class MetaBoxes
{
	/**
	 * @var array
	 */
	public static $noMetaBoxesForPostTypes = array(
		// Oxygen Page Builder
		'ct_template',
		'oxy_user_library',

		// Themify Page Builder (Layout & Layout Part)
		'tbuilder_layout',
		'tbuilder_layout_part',

		// "Popup Maker" plugin
		'popup',
		'popup_theme',

		// "Popup Builder" plugin
		'popupbuilder',

		// "Datafeedr Product Sets" plugin
		'datafeedr-productset',

		// Elementor
		'elementor_library'
	);

	/**
	 *
	 */
	public function initMetaBox($type)
	{
		if ( ! Menu::userCanAccessAssetCleanUp() ) {
			return;
		}

		if ( Main::instance()->settings['allow_manage_assets_to'] === 'chosen' && ! empty(Main::instance()->settings['allow_manage_assets_to_list']) ) {
			$wpacuCurrentUserId = get_current_user_id();

			if ( ! in_array( $wpacuCurrentUserId, Main::instance()->settings['allow_manage_assets_to_list'] ) ) {
				return; // the current logged-in admin is not in the list of "Allow managing assets to:"
			}
		}

		if ($type === 'manage_page_assets') {
			add_action( 'add_meta_boxes', array( $this, 'addAssetManagerMetaBox' ), 11 );
			add_action( 'add_meta_boxes', array( $this, 'keepAssetManagerMetaBoxOnTheLeftSide' ), 1 );
		}

		}

	/**
	 * @param $postType
	 */
	public function addAssetManagerMetaBox($postType)
	{
		$obj = $this->showMetaBoxes($postType);

		if (isset($obj->public) && $obj->public > 0) {
			add_meta_box(
				WPACU_PLUGIN_ID . '_asset_list',
				WPACU_PLUGIN_TITLE.': '.__('CSS &amp; JavaScript Manager / Page Options', 'wp-asset-clean-up'),
				array($this, 'renderAssetManagerMetaBoxContent'),
				$postType,
				apply_filters('wpacu_asset_list_meta_box_context',  'normal'),
				apply_filters('wpacu_asset_list_meta_box_priority', 'high')
			);
		}
	}

	/**
	 * Sometimes, users are moving by mistake the meta box to the right side which is not desirable
	 * and have difficulties moving it back, thus, this method moves it back to the left (normal) side
	 *
	 * @param $postType
	 */
	public function keepAssetManagerMetaBoxOnTheLeftSide($postType)
	{
		$user = wp_get_current_user();

		if (isset($user->ID) && $user->ID) {
			$userMetaBoxOption = get_user_option('meta-box-order_'.$postType,  $user->ID );

			if (isset($userMetaBoxOption['side'], $userMetaBoxOption['normal']) && strpos($userMetaBoxOption['side'], WPACU_PLUGIN_ID . '_asset_list') !== false) {
				// Remove it from the side list
				if (strpos($userMetaBoxOption['side'], ',') !== false) {
					$allSideMetaBoxes = explode(',', $userMetaBoxOption['side']);

					foreach ($allSideMetaBoxes as $sideMetaBoxIndex => $sideMetaBoxName) {
						if ($sideMetaBoxName === WPACU_PLUGIN_ID . '_asset_list') {
							unset($allSideMetaBoxes[$sideMetaBoxIndex]);
						}
					}

					$userMetaBoxOption['side'] = implode(',', array_unique($allSideMetaBoxes));
				} else {
					$userMetaBoxOption['side'] = str_replace(WPACU_PLUGIN_ID . '_asset_list', '', $userMetaBoxOption['side']);
				}

				// Move it back to the normal one
				if (strpos($userMetaBoxOption['normal'], ',') !== false) {
					$allNormalMetaBoxes = explode( ',', $userMetaBoxOption['normal'] );
					$allNormalMetaBoxes[] = WPACU_PLUGIN_ID . '_asset_list';
					$userMetaBoxOption['normal'] = implode(',', array_unique($allNormalMetaBoxes));
				} elseif ($userMetaBoxOption['normal'] !== '') {
					$userMetaBoxOption['normal'] .= ','.WPACU_PLUGIN_ID . '_asset_list';
				} else {
					$userMetaBoxOption['normal'] .= WPACU_PLUGIN_ID . '_asset_list';
				}

				update_user_option($user->ID, 'meta-box-order_'.$postType, $userMetaBoxOption, true);
			}
		}
	}

	/**
	 * This is triggered only in the Edit Mode Dashboard View
	 */
	public function renderAssetManagerMetaBoxContent()
	{
		global $post;

		if ($post->ID === null) {
			return;
		}

		$data = array('status' => 1);

		$postId = ($post->ID > 0) ? $post->ID : 0;

		$isListFetchable = true;

		if (! Main::instance()->settings['dashboard_show']) {
			$isListFetchable = false;
			$data['status'] = 2; // "Manage within Dashboard" is disabled in plugin's settings
		} elseif ($postId < 1 || ! in_array(get_post_status($postId), array('publish', 'private'))) {
			$data['status'] = 3; // "draft", "auto-draft" post (it has to be published)
			$isListFetchable = false;
		}

		if (self::isMediaWithPermalinkDeactivated($post)) {
			$isListFetchable = false;
			$data['status'] = 4; // "Redirect attachment URLs to the attachment itself?" is enabled in "Yoast SEO" -> "Media"
		}

		if ($isListFetchable) {
			$data['fetch_url'] = Misc::getPageUrl($postId);

			switch (assetCleanUpHasNoLoadMatches($data['fetch_url'])) {
				case 'is_set_in_settings':
					// The rules from "Settings" -> "Plugin Usage Preferences" -> "Do not load the plugin on certain pages" will be checked
					$data['status']  = 5;
					$isListFetchable = false;
					break;

				case 'is_set_in_page':
					// The following option from "Page Options" (within the CSS/JS manager of the targeted page) is set: "Do not load Asset CleanUp Pro on this page (this will disable any functionality of the plugin)"
					$data['status']  = 6;
					$isListFetchable = false;
					break;
			}
		}

		$data['is_list_fetchable']     = $isListFetchable;
		$data['fetch_assets_on_click'] = false;

		if ($isListFetchable) {
			if (Main::instance()->settings['assets_list_show_status'] === 'fetch_on_click') {
				$data['fetch_assets_on_click'] = true;
			}

			$data['dom_get_type'] = Main::instance()->settings['dom_get_type'];
		}

		$data['post_id'] = $postId;

		MainAdmin::instance()->parseTemplate('meta-box', $data, true);
	}

	/**
	 * @param int $postId
	 * @param string $type
	 * @return array|mixed|object
	 */
	public static function getPageOptions($postId = 0, $type = 'post')
	{
		if ($type === 'post' || $postId > 0) {
			if ( $postId < 1 ) {
				global $post;
				$postId = (int) $post->ID;
			}

			if ( $postId > 1 ) {
				$metaPageOptionsJson = get_post_meta( $postId, '_' . WPACU_PLUGIN_ID . '_page_options', true );
				return @json_decode( $metaPageOptionsJson, ARRAY_A );
			}
		} elseif ($type === 'front_page') { // e.g. the latest posts, not a chosen page ID (that's when $type as 'post' is used)
			if (isset($GLOBALS['wpacu_get_page_options_front_page'])) {
				return $GLOBALS['wpacu_get_page_options_front_page'];
			}

            $globalPageOptionsList = wpacuGetGlobalData();

            if ( ! empty( $globalPageOptionsList['page_options']['homepage'] ) ) {
                $GLOBALS['wpacu_get_page_options_front_page'] = $globalPageOptionsList['page_options']['homepage'];
                return $globalPageOptionsList['page_options']['homepage'];
            }

			$GLOBALS['wpacu_get_page_options_front_page'] = array();
		}

		return array();
	}

	/**
	 * @return bool
	 */
	public static function hasNoFrontendOptimizationPageOption()
	{
		$isSingularPage = (int)wpacuGetConstant('WPACU_CURRENT_PAGE_ID') > 0 && MainFront::isSingularPage();

		if ($isSingularPage || MainFront::isHomePage()) {
			if ($isSingularPage) {
				$pageOptions = self::getPageOptions( WPACU_CURRENT_PAGE_ID ); // Singular page
			} else {
				$pageOptions = self::getPageOptions(0, 'front_page'); // Home page
			}

			if (isset($pageOptions['no_assets_settings']) && $pageOptions['no_assets_settings']) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array
	 */
	public static function hideMetaBoxesForPostTypes()
	{
		$allValues = self::$noMetaBoxesForPostTypes;

		$hideForChosenPostTypes = Main::instance()->settings['hide_meta_boxes_for_post_types'];

		if (! empty($hideForChosenPostTypes)) {
			foreach ($hideForChosenPostTypes as $chosenPostType) {
				$allValues[] = trim($chosenPostType);
			}
		}

		return $allValues;
	}

	/**
	 * Determine whether to show any Asset CleanUp (Pro) meta boxes, depending on the post type
	 *
	 * @param $postType
	 * @return bool|object
	 */
	public function showMetaBoxes($postType)
	{
		$obj = get_post_type_object($postType);

		// These are not public pages that are loading CSS/JS
		// e.g. URI request ending in '/ct_template/inner-content/'
		if (isset($obj->name) && $obj->name && in_array($obj->name, self::hideMetaBoxesForPostTypes())) {
			return false;
		}

		if (isset($_GET['post'], $obj->name) && $_GET['post'] && $obj->name) {
			$permalinkStructure = get_option( 'permalink_structure' );
			$postPermalink      = get_permalink( $_GET['post'] );

			if (strpos($permalinkStructure, '%postname%') !== false && strpos($postPermalink, '/?'.$obj->name.'=')) {
				// Doesn't have the right permalink; Showing any Asset CleanUp (Lite or Pro) options is not relevant
				return false;
			}
		}

		return $obj;
	}

	/**
	 * @param string $post
	 *
	 * @return bool
     *
     * @noinspection BadExceptionsProcessingInspection
     */
	public static function isMediaWithPermalinkDeactivated($post = '')
	{
		if ($post === '') {
			$postTypeToCheck = 'attachment';
		} else {
            if ( ! isset($post->ID) ) {
                return false;
            }

			$postTypeToCheck = get_post_type($post->ID);
		}

		if ('attachment' === $postTypeToCheck && method_exists('WPSEO_Options', 'get')) {
			try {
				if (\WPSEO_Options::get( 'disable-attachment' ) === true) {
					return true;
				}
			} catch (\Exception $e) {}
		}

		return false;
	}
}
