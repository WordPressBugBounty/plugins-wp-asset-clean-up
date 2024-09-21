<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\Admin\SettingsAdminOnlyForAdmin;
use WpAssetCleanUp\OptimiseAssets\DynamicLoadedAssets;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

/**
 * Class AssetsManager
 * @package WpAssetCleanUp
 *
 * Actions related to the CSS/JS manager area both in the front-end and /wp-admin/ view
 * that only concerns the administrator; the code below should not be ever triggered for the regular (guest) visitor
 */
class AssetsManager
{
	/**
	 * @var AssetsManager|null
	 */
	private static $singleton;

	/**
	 * @return null|AssetsManager
	 */
	public static function instance()
	{
		if ( self::$singleton === null ) {
			self::$singleton = new self();
		}

		return self::$singleton;
	}

	/**
	 *
	 */
	public function __construct()
	{
		// Send an AJAX request to get the list of the loaded hardcoded scripts and styles and print it
		add_action( 'wp_ajax_' . WPACU_PLUGIN_ID . '_print_loaded_hardcoded_assets', array( $this, 'ajaxPrintLoadedHardcodedAssets' ) );

		// "File Size:" value from the asset row
		add_filter( 'wpacu_get_asset_size', array( $this, 'getAssetSize'), 10, 3);

		add_action( 'wp_ajax_' . WPACU_PLUGIN_ID . '_check_external_urls_for_status_code', array( $this, 'ajaxCheckExternalUrlsForStatusCode' ) );

		// Triggers only if the administrator is logged in ('wp_ajax_nopriv' is not required)
		// Used to determine the total size of an external loaded assets (e.g. a CSS file from Google APIs)
		add_action( 'wp_ajax_'.WPACU_PLUGIN_ID.'_get_external_file_size', array( $this, 'ajaxGetExternalFileSize' ) ) ;
	}

	/**
	 * @return bool
	 */
	public function frontendShow()
	{
        if (is_admin()) {
            return false; // Only relevant in the front-end view
        }

		// The option is disabled
		if (! Main::instance()->settings['frontend_show']) {
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
			$frontendShowExceptions = trim( Main::instance()->settings['frontend_show_exceptions'] );

			// We want to make sure the RegEx rules will be working fine if certain characters (e.g. Thai ones) are used
			$requestUriAsItIs = rawurldecode($_SERVER['REQUEST_URI']);

			if ( strpos( $frontendShowExceptions, "\n" ) !== false ) {
				foreach ( explode( "\n", $frontendShowExceptions ) as $frontendShowException ) {
					$frontendShowException = trim($frontendShowException);

					if ( strpos( $requestUriAsItIs, $frontendShowException ) !== false ) {
						return false;
					}
				}
			} elseif ( strpos( $requestUriAsItIs, $frontendShowExceptions ) !== false ) {
				return false;
			}
		}

		// Allows managing assets to chosen admins and the user is not in the list
		if ( ! self::currentUserCanViewAssetsList() ) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public static function currentUserCanViewAssetsList()
	{
        Main::instance()->settings = SettingsAdminOnlyForAdmin::filterAnySpecifiedAdminsForAccessToAssetsManager(Main::instance()->settings);

        if ( Main::instance()->settings['allow_manage_assets_to'] === 'chosen' && ! empty(Main::instance()->settings['allow_manage_assets_to_list']) ) {
			$wpacuCurrentUserId = get_current_user_id();

			if ( ! in_array( $wpacuCurrentUserId, Main::instance()->settings['allow_manage_assets_to_list'] ) ) {
				return false; // the current logged-in admin is not in the list of "Allow managing assets to:"
			}
		}

		return true;
	}

	/**
	 * @param object|string $objOrString
	 * @param string $format | 'for_print': Calculates the format in KB / MB  - 'raw': The actual size in bytes
     * @param string $for ("file" or "tag")
	 *
	 * @return string
     * @noinspection NestedAssignmentsUsageInspection
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
	public static function getAssetSize($objOrString, $format = 'for_print', $for = 'file')
	{
        if (is_object($objOrString)) {
            $obj = $objOrString;
        }

		if ( $for === 'file' && isset( $obj->src ) && $obj->src ) {
			$src     = $obj->src;
			$siteUrl = site_url();

			// Starts with / but not with //
			// Or starts with ../ (very rare cases)
			$isRelInternalPath = ( strncmp($src, '/', 1) === 0 && strncmp($src, '//', 2) !== 0 ) ||
                                 ( strncmp($src, '../', 3) === 0 );

			// Source starts with '//' - check if the file exists
			if ( strncmp($obj->src, '//', 2) === 0 ) {
				list ( $urlPrefix ) = explode( '//', $siteUrl );
				$srcToCheck = $urlPrefix . $obj->src;

				$hostSiteUrl = parse_url( $siteUrl, PHP_URL_HOST );
				$hostSrc     = parse_url( $obj->src, PHP_URL_HOST );

				$siteUrlAltered = str_replace( array( $hostSiteUrl, $hostSrc ), '{site_host}', $siteUrl );
				$srcAltered     = str_replace( array( $hostSiteUrl, $hostSrc ), '{site_host}', $srcToCheck );

				$srcMaybeRelPath = str_replace( $siteUrlAltered, '', $srcAltered );

				$possibleStrips = array( '?ver', '?cache=' );

				foreach ( $possibleStrips as $possibleStrip ) {
					if ( strpos( $srcMaybeRelPath, $possibleStrip ) !== false ) {
						list ( $srcMaybeRelPath ) = explode( $possibleStrip, $srcMaybeRelPath );
					}
				}

                $possibleFile = Misc::getWpRootDirPathBasedOnPath($srcMaybeRelPath) . $srcMaybeRelPath;

				if ( is_file( $possibleFile ) ) {
					$fileSize = filesize( $possibleFile );

					if ( $format === 'raw' ) {
						return (int) $fileSize;
					}

					return MiscAdmin::formatBytes( $fileSize );
				}
			}

			// e.g. /?scss=1 (Simple Custom CSS Plugin)
			if ( str_replace( $siteUrl, '', $src ) === '/?sccss=1' ) {
				$customCss   = DynamicLoadedAssets::getSimpleCustomCss();
				$sizeInBytes = strlen( $customCss );

				if ( $format === 'raw' ) {
					return $sizeInBytes;
				}

				return MiscAdmin::formatBytes( $sizeInBytes );
			}

			// External file? Use a different approach
			// Return an HTML code that will be parsed via AJAX through JavaScript
			$isExternalFile = ( ! $isRelInternalPath &&
			                    ( ! ( isset( $obj->wp ) && $obj->wp === 1 ) )
			                    && strpos( $src, $siteUrl ) !== 0 );

			// e.g. /?scss=1 (Simple Custom CSS Plugin) From External Domain
			// /?custom-css (JetPack Custom CSS)
			$isLoadedOnTheFly = ( strpos( $src, '?sccss=1' ) !== false )
			                    || ( strpos( $src, '?custom-css' ) !== false );

			if ( $isExternalFile || $isLoadedOnTheFly ) {
				return '<a class="wpacu-external-file-size" data-src="' . $src . '" href="#">🔗 Get File Size</a>' .
				       '<span style="display: none;"><img style="width: 20px; height: 20px;" alt="" align="top" width="20" height="20" src="' . includes_url( 'images/spinner-2x.gif' ) . '"></span>';
			}

			$forAssetType = $pathToFile = '';

			if ( stripos( $src, '.css' ) !== false ) {
				$forAssetType = 'css';
			} elseif ( stripos( $src, '.js' ) !== false ) {
				$forAssetType = 'js';
			}

			if ( $forAssetType ) {
				$pathToFile = OptimizeCommon::getLocalAssetPath( $src, $forAssetType );
			}

			if ( ! $pathToFile || ! is_file( $pathToFile ) ) { // Fallback, old code...
				// Local file? Core or from a plugin / theme?
				if ( strpos( $obj->src, $siteUrl ) !== false ) {
					// Local Plugin / Theme File
					// Could be a Staging site that is having the Live URL in the General Settings
					$src = ltrim( str_replace( $siteUrl, '', $obj->src ), '/' );
				} elseif ( ( isset( $obj->wp ) && $obj->wp === 1 ) || $isRelInternalPath ) {
					// Local WordPress Core File
					$src = ltrim( $obj->src, '/' );
				}

				$srcAlt = $src;

				if (strncmp($src, '../', 3) === 0 ) {
					$srcAlt = str_replace( '../', '', $srcAlt );
				}

				$pathToFile = Misc::getWpRootDirPathBasedOnPath($srcAlt) . $srcAlt;

				if ( strpos( $pathToFile, '?ver' ) !== false ) {
					list( $pathToFile ) = explode( '?ver', $pathToFile );
				}

				// It can happen that the CSS/JS has extra parameters (rare cases)
				foreach ( array( '.css?', '.js?' ) as $needlePart ) {
					if ( strpos( $pathToFile, $needlePart ) !== false ) {
						list( $pathToFile ) = explode( '?', $pathToFile );
					}
				}
			}

			if ( is_file( $pathToFile ) ) {
				$sizeInBytes = filesize( $pathToFile );

				if ( $format === 'raw' ) {
					return (int) $sizeInBytes;
				}

				return MiscAdmin::formatBytes( $sizeInBytes );
			}

			return '<em style="font-size: 85%;">Error / This file might not exist: /' . str_replace(ABSPATH, '', $pathToFile) . '</em>';
		}

        if ( isset( $obj->src, $obj->handle ) && $obj->handle === 'jquery' && ! $obj->src ) {
			return '"jquery-core" size';
        }

        if (is_string($objOrString) && $for === 'tag') {
            $sizeInBytes = strlen( $objOrString );

            if ( $format === 'raw' ) {
                return $sizeInBytes;
            }

            return MiscAdmin::formatBytes( $sizeInBytes );
        }

		// External or nothing to be shown (perhaps due to an error)
		return '';
	}

	/**
	 *
	 */
	public function ajaxPrintLoadedHardcodedAssets()
	{
		if ( ! isset( $_POST['wpacu_nonce'] ) || ! wp_verify_nonce( $_POST['wpacu_nonce'], 'wpacu_print_loaded_hardcoded_assets_nonce' ) ) {
			echo 'Error: The security nonce is not valid.';
			exit();
		}

		$wpacuListH        = Misc::getVar( 'post', 'wpacu_list_h' );
		$wpacuSettingsJson = base64_decode( Misc::getVar( 'post', 'wpacu_settings' ) );
		$wpacuSettings     = (array) json_decode( $wpacuSettingsJson, ARRAY_A ); // $data values are passed here

		// Only set the following variables if there is at least one hardcoded LINK/STYLE/SCRIPT
		$jsonH = base64_decode( $wpacuListH );

		function wpacuPrintHardcodedManagementList( $jsonH, $wpacuSettings ) {
			$data                      = $wpacuSettings ?: array();
			$data['do_not_print_list'] = true;
			$data['print_outer_html']  = false;
			$data['all']['hardcoded']  = (array) json_decode( $jsonH, ARRAY_A );

            // e.g. Unload on this page, Unload on this product page (depending on the page where the assets are managed)
            $data = AssetsManager::textRulesToShowInCssJsManager($data);

			if ( ! empty( $data['all']['hardcoded']['within_conditional_comments'] ) ) {
				ObjectCache::wpacu_cache_set(
					'wpacu_hardcoded_content_within_conditional_comments',
					$data['all']['hardcoded']['within_conditional_comments']
				);
			}

            $afterHardcodedTitle = ''; // will be added in the inclusion
            $viewHardcodedMode = HardcodedAssets::viewHardcodedModeLayout($wpacuSettings['plugin_settings']);

			ob_start();
			// $totalHardcodedTags is set here
			include_once WPACU_PLUGIN_DIR . '/templates/meta-box-loaded-assets/view-hardcoded-'.$viewHardcodedMode.'.php'; // generate $hardcodedTagsOutput
			$output = ob_get_clean();

			return wp_json_encode( array(
				'output'                => $output,
				'after_hardcoded_title' => $afterHardcodedTitle
			) );
		}

		echo wpacuPrintHardcodedManagementList( $jsonH, $wpacuSettings );

		exit();
	}

	/**
	 *
	 */
	public function ajaxCheckExternalUrlsForStatusCode()
	{
		if ( ! isset( $_POST['wpacu_nonce'] ) || ! wp_verify_nonce( $_POST['wpacu_nonce'], 'wpacu_ajax_check_external_urls_nonce' ) ) {
			echo 'Error: The security nonce is not valid.';
			exit();
		}

		if (! isset($_POST['action'], $_POST['wpacu_check_urls'])) {
			echo 'Error: The post parameters are not the right ones.';
			exit();
		}

		// Check privileges
		if (! Menu::userCanAccessAssetCleanUp()) {
			echo 'Error: Not enough privileges to perform this action.';
			exit();
		}

		$checkUrls = explode('-at-wpacu-at-', $_POST['wpacu_check_urls']);
		$checkUrls = array_filter(array_unique($checkUrls));

		foreach ($checkUrls as $index => $checkUrl) {
			if (strncmp($checkUrl, '//', 2) === 0) { // starts with // (append the right protocol)
				if (strpos($checkUrl, 'fonts.googleapis.com') !== false)  {
					$checkUrl = 'https:'.$checkUrl;
				} else {
					// either HTTP or HTTPS depending on the current page situation (that the admin has loaded)
					$checkUrl = (Misc::isHttpsSecure() ? 'https:' : 'http:') . $checkUrl;
				}
			}

			$response = wp_remote_get($checkUrl);

			// Remove 200 OK ones as the other ones will remain for highlighting
			if (wp_remote_retrieve_response_code($response) === 200) {
				unset($checkUrls[$index]);
			}
		}

		echo wp_json_encode($checkUrls);
		exit();
	}

	/**
	 * Source: https://stackoverflow.com/questions/2602612/remote-file-size-without-downloading-file
	 */
	public function ajaxGetExternalFileSize()
	{
		// Check nonce
		if ( ! isset( $_POST['wpacu_nonce'] ) || ! wp_verify_nonce( $_POST['wpacu_nonce'], 'wpacu_ajax_check_remote_file_size_nonce' ) ) {
			echo 'Error: The security nonce is not valid.';
			exit();
		}

		// Check privileges
		if (! Menu::userCanAccessAssetCleanUp()) {
			echo 'Error: Not enough privileges to perform this action.';
			exit();
		}

		// Assume failure.
		$result = -1;

		$remoteFile = Misc::getVar('post', 'wpacu_remote_file', false);

		if (! $remoteFile) {
			echo 'N/A (external file)';
			exit;
		}

		// If it starts with //
		if (strncmp($remoteFile, '//', 2) === 0) {
			$remoteFile = 'http:'.$remoteFile;
		}

		// Check if the URL is valid
        $remoteFileToCheck = filter_var($remoteFile, FILTER_SANITIZE_URL);

		if (! filter_var($remoteFileToCheck, FILTER_VALIDATE_URL)) {
			echo 'The asset\'s URL - '.$remoteFile.' - could not be validated.';
			exit();
		}

		$curl = curl_init($remoteFile);

		// Issue a HEAD request and follow any redirects.
		curl_setopt($curl, CURLOPT_NOBODY, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

		$data = curl_exec($curl);
		curl_close($curl);

		$contentLength = $status = 'unknown';

		if ($data) {
			if (preg_match( '/^HTTP\/1\.[01] (\d\d\d)/', $data, $matches ) ) {
				$status = (int)$matches[1];
			}

			if ( preg_match( '/Content-Length: (\d+)/', $data, $matches ) ) {
				$contentLength = (int)$matches[1];
			}

			// http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
			if ( $status === 200 || ($status > 300 && $status <= 308) ) {
				$result = $contentLength;
			}
		}

		if ($contentLength === 'unknown') {
			// One more try
			$response     = wp_remote_get($remoteFile);

			$responseCode = wp_remote_retrieve_response_code($response);

			if ($responseCode === 200) {
				$result = mb_strlen(wp_remote_retrieve_body($response));
			}
		}

		echo MiscAdmin::formatBytes($result);

		if (stripos($remoteFile, '//fonts.googleapis.com/') !== false) {
			// Google Font APIS CDN
			echo ' + the sizes of the loaded "Google Font" files (see "url" from @font-face within the Source file)';
		} elseif (stripos($remoteFile, '/font-awesome.css') || stripos($remoteFile, '/font-awesome.min.css')) {
			// FontAwesome CDN
			echo ' + the sizes of the loaded "FontAwesome" font files (see "url" from @font-face within the Source file)';
		}

		exit();
	}

	/**
	 * Option: Add Note
	 *
	 * @return array
	 */
	public static function getHandleNotes()
	{
		$handleNotes = array('styles' => array(), 'scripts' => array());

        $handleNotesList = wpacuGetGlobalData();

        // Are new positions set for styles and scripts?
        foreach (array('styles', 'scripts') as $assetKey) {
            if ( ! empty( $handleNotesList[$assetKey]['notes'] ) ) {
                $handleNotes[$assetKey] = $handleNotesList[$assetKey]['notes'];
            }
        }

		return $handleNotes;
	}

	/**
	 * Get all contracted rows
	 *
	 * @return array
	 */
	public static function getHandleRowStatus()
	{
		$handleRowStatus = array('styles' => array(), 'scripts' => array());

        $handleRowStatusList = wpacuGetGlobalData();
		$globalKey = 'handle_row_contracted';

        // Are new positions set for styles and scripts?
        foreach (array('styles', 'scripts') as $assetKey) {
            if ( ! empty( $handleRowStatusList[$assetKey][$globalKey] ) ) {
                $handleRowStatus[$assetKey] = $handleRowStatusList[$assetKey][$globalKey];
            }
        }


		return $handleRowStatus;
	}

    /**
     * This is triggered for managing the CSS/JS in both the Dashboard and the front-end view
     *
     * @return int
     */
    public static function getCurrentPostIdForCssJsManager($page, $pageRequestFor)
    {
        global $pagenow;

        $currentPostId = 0;

        // The admin is in a page such as /wp-admin/post.php?post=[POST_ID_HERE]&action=edit
        $isPostIdFromEditPostPage = (isset($_GET['post'], $_GET['action']) && $_GET['action'] === 'edit' && $pagenow === 'post.php') ? (int)$_GET['post'] : '';
        $isDashAssetsManagerPage  = ($page === WPACU_PLUGIN_ID . '_assets_manager');

        if ($isDashAssetsManagerPage) {
            if ( $pageRequestFor === 'homepage' ) {
                // Homepage tab / Check if the home page is one of the singular pages
                $pageOnFront = get_option( 'show_on_front' ) === 'page' ? (int) get_option( 'page_on_front' ) : 0;

                if ( $pageOnFront && $pageOnFront > 0 ) {
                    $currentPostId = $pageOnFront;
                }
            } elseif ( isset( $_GET['wpacu_post_id'] ) && $_GET['wpacu_post_id'] && in_array( $pageRequestFor, array( 'posts', 'pages', 'custom_post_types', 'media_attachment' ) ) ) {
                $currentPostId = (int)Misc::getVar( 'get', 'wpacu_post_id' ) ?: 0;
            }
        } elseif($isPostIdFromEditPostPage) {
            if ($isPostIdFromEditPostPage > 0 && $isPostIdFromEditPostPage !== $currentPostId) {
                $currentPostId = $isPostIdFromEditPostPage;
            }
        } elseif (MainFront::isSingularPage()) {
            $currentPostId = Main::instance()->getCurrentPostId();
        }

        return $currentPostId;
    }

    /**
     * @param $type
     *
     * @return array|bool
     */
    public static function textRulesToShowInCssJsManager($data)
    {
        $data['page_unload_text'] = __('Unload on this page', 'wp-asset-clean-up');
        $data['page_load_text']   = __('On this page', 'wp-asset-clean-up');

        $postType = false;

        if ( is_admin() && Misc::getVar('post', 'page_type') === 'singular' ) {
            $postType = Misc::getVar('post', 'current_post_type');
        } elseif (MainFront::isSingularPage() && Main::instance()->getCurrentPostId() > 0) {
            $postType = get_post_type(Main::instance()->getCurrentPostId());
        }

        if ($postType === 'post') {
            $data['page_unload_text'] = __('Unload on this post', 'wp-asset-clean-up');
            $data['page_load_text']   = __('On this post', 'wp-asset-clean-up');
        } elseif ($postType === 'product') {
            $data['page_unload_text'] = __('Unload on this product page', 'wp-asset-clean-up');
            $data['page_load_text']   = __('On this product page', 'wp-asset-clean-up');
        }

        if (MainFront::isHomePage() && Main::instance()->getCurrentPostId() < 1 && get_option('show_on_front') === 'posts') {
            $data['page_unload_text'] = __('Unload on this homepage', 'wp-asset-clean-up');
            $data['page_load_text']   = __('On this homepage', 'wp-asset-clean-up');
        }

        return $data;
    }
}
