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
        // This is used only in the front-end view (bottom of the page)
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
    public static function currentUserCanViewAssetsList()
	{
        Main::instance()->settings = SettingsAdminOnlyForAdmin::filterAnySpecifiedAdminsForAccessToAssetsManager(Main::instance()->settings);

        if ( in_array(Main::instance()->settings['allow_manage_assets_to'], array('selected', 'selected_roles'), true) ) {
            $selectedRoles = ! empty(Main::instance()->settings['allow_manage_assets_to_roles']) && is_array(Main::instance()->settings['allow_manage_assets_to_roles'])
                ? Main::instance()->settings['allow_manage_assets_to_roles']
                : array();
            $currentUser = wp_get_current_user();

            $roleMatches = ! empty(array_intersect($selectedRoles, (array)$currentUser->roles));
            $userMatches = Main::instance()->settings['allow_manage_assets_to'] === 'selected'
                && ! empty(Main::instance()->settings['allow_manage_assets_to_list'])
                && in_array((int)$currentUser->ID, array_map('intval', Main::instance()->settings['allow_manage_assets_to_list']), true);

            return $roleMatches || $userMatches;
        }

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
        if ( ! Menu::userCanAccessPlugin() ) {
            wp_die('Error: You are not allowed to access this area.');
        }

		if ( ! isset( $_POST['wpacu_nonce'] ) || ! wp_verify_nonce( $_POST['wpacu_nonce'], 'wpacu_print_loaded_hardcoded_assets_nonce' ) ) {
			echo 'Error: The security nonce is not valid.';
			exit();
		}

		$wpacuListH        = Misc::getVar( 'post', 'wpacu_list_h' );
		$wpacuSettingsJson = base64_decode( Misc::getVar( 'post', 'wpacu_settings' ) );
		$wpacuSettings     = (array) json_decode( $wpacuSettingsJson, ARRAY_A ); // $data values are passed here

		// Only set the following variables if there is at least one hardcoded LINK/STYLE/SCRIPT
		$jsonH = base64_decode( $wpacuListH );

		echo $this->printHardcodedManagementListFromAjaxCall( $jsonH, $wpacuSettings );
		exit();
	}

    /**
     * @param $jsonH
     * @param $wpacuSettings
     *
     * @return false|string
     */
    private function printHardcodedManagementListFromAjaxCall( $jsonH, $wpacuSettings )
    {
        $data                      = $wpacuSettings ?: array();
        $data['do_not_print_list'] = true;
        $data['print_outer_html']  = false;
        $data['all']['hardcoded']  = (array) json_decode( $jsonH, ARRAY_A );

        // e.g. Unload on this page, Unload on this product page (depending on the page where the assets are managed)
        $data = self::textRulesToShowInCssJsManager($data);

        if ( ! empty( $data['all']['hardcoded']['within_conditional_comments'] ) ) {
            ObjectCache::wpacu_cache_set(
                'wpacu_hardcoded_content_within_conditional_comments',
                $data['all']['hardcoded']['within_conditional_comments']
            );
        }

        $afterHardcodedTitle = ''; // will be added in the inclusion
        $viewHardcodedMode   = HardcodedAssets::viewHardcodedModeLayout($wpacuSettings['plugin_settings']);

        ob_start();
        // $totalHardcodedTags is set here
        include_once WPACU_PLUGIN_DIR . '/templates/meta-box-loaded-assets/view-hardcoded-'.$viewHardcodedMode.'.php'; // generate $hardcodedTagsOutput
        $output = ob_get_clean();

        $response = array(
            'output'                => $output,
            'after_hardcoded_title' => $afterHardcodedTitle
        );

        // [START] Set reference for checking external URLs
        $anyExternalSrcsFromHardcodedAssets = HardcodedAssets::getAllExternalSrcsFromHardcodedAssets($data['all']['hardcoded']);

        $response['external_srcs_ref'] = self::setExternalSrcsRef($anyExternalSrcsFromHardcodedAssets, 'css_js_manager_hardcoded_frontend_view');
        // [END] Set reference for checking external URLs

        return wp_json_encode( $response );
    }

    /**
     *
     * @param array $array
     * @param string $for
     *
     * @return string
     */
    public static function setExternalSrcsRef($array, $for = 'css_js_manager')
    {
        $allExternalSrcs = array(); // default

        if ($for === 'css_js_manager') {
            $allExternalSrcs = self::getAllExternalSrcsFromAssetsList($array);
        } elseif (in_array($for, array('css_js_manager_hardcoded_frontend_view', 'bulk_changes'))) {
            $allExternalSrcs = $array;
        } elseif ($for === 'overview') {
            $arrayOriginal = $array;

            $array = array();

            foreach (array('styles', 'scripts') as $assetType) {
                if ( empty($arrayOriginal[$assetType]) ) {
                    continue;
                }

                foreach ($arrayOriginal[$assetType] as $assetHandle => $values) {
                    $isHardcoded = (strncmp($assetHandle, 'wpacu_hardcoded_', 16) === 0);

                    if ( $isHardcoded ) {
                        $output = isset($values['output']) ? $values['output'] : '';

                        if ($output && ($maybeSrc = Misc::getValueFromTag($output)) && filter_var($maybeSrc, FILTER_VALIDATE_URL)) {
                            $array[$assetType][] = (object)array('srcHref' => $maybeSrc);
                        }
                    } elseif (isset($values['src']) && filter_var($values['src'], FILTER_VALIDATE_URL)) {
                        $array[$assetType][] = (object)array('srcHref' => $values['src']);
                    }
                }
            }

            $allExternalSrcs = self::getAllExternalSrcsFromAssetsList($array);
        }

        $externalSrcsRef = MiscAdmin::generateUniqueString();
        set_transient(WPACU_PLUGIN_ID . '_external_srcs_ref_' . $externalSrcsRef, $allExternalSrcs, (60 * 15)); // expires in 15 minutes

        // Re-update it in the hardcoded list (to include them as well in case there are any external assets loading there)
        $GLOBALS[WPACU_PLUGIN_ID . '_external_srcs_ref'] = $externalSrcsRef;

        return $externalSrcsRef;
    }

    /**
     * Get all CSS/JS assets that are loaded from an external domain
     *
     * @param array $list
     *
     * @return array
     */
    public static function getAllExternalSrcsFromAssetsList($list)
    {
        $externalSrcs = array();

        foreach ($list as $values) {
            if ( empty($values) ) {
                continue;
            }

            foreach ($values as $value) {
                if ( ! isset($value->srcHref) ) {
                    continue;
                }

                if (MiscAdmin::isExternalSrc($value->srcHref)) {
                    $externalSrcs[] = $value->srcHref;
                }
            }
        }

        return $externalSrcs;
    }

	/**
	 * Perform an HTTP request while validating the initial URL and each redirect.
	 * Redirects are handled explicitly so the protection also applies on the
	 * minimum supported WordPress versions.
	 *
	 * @param string $url
	 * @param array  $args
	 * @param int    $maxRedirects
	 *
	 * @return array|\WP_Error
	 */
	private static function safeRemoteRequestWithValidatedRedirects($url, $args = array(), $maxRedirects = 3)
	{
		$currentUrl   = esc_url_raw($url);
		$maxRedirects = max(0, (int)$maxRedirects);
		$requestArgs  = array_merge(array('timeout' => 10), $args);

		// Redirects are followed manually and validated before each new request.
		$requestArgs['redirection']        = 0;
		$requestArgs['reject_unsafe_urls'] = true;

		for ($redirectCount = 0; $redirectCount <= $maxRedirects; $redirectCount++) {
			$validatedUrl = wp_http_validate_url($currentUrl);

			if ($validatedUrl === false) {
				return new \WP_Error(
					'wpacu_unsafe_remote_url',
					'The remote URL is not safe to request from the server.'
				);
			}

			$response = wp_remote_request($validatedUrl, $requestArgs);

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
					'wpacu_remote_too_many_redirects',
					'The remote request exceeded the allowed number of redirects.'
				);
			}

			$currentUrl = \WP_Http::make_absolute_url(trim($redirectLocation), $validatedUrl);
		}

		return new \WP_Error(
			'wpacu_remote_request_failed',
			'The remote request could not be completed.'
		);
	}

	/**
	 * Extract a reliable total file size from Content-Range or Content-Length.
	 *
	 * @param array|\WP_Error $response
	 *
	 * @return int|false
	 */
	private static function getRemoteFileSizeFromResponse($response)
	{
		if (is_wp_error($response) || ! is_array($response)) {
			return false;
		}

		$responseCode = (int)wp_remote_retrieve_response_code($response);

		if ($responseCode < 200 || $responseCode >= 400) {
			return false;
		}

		$contentRange = wp_remote_retrieve_header($response, 'content-range');

		if (is_array($contentRange)) {
			$contentRange = end($contentRange);
		}

		if (is_scalar($contentRange) && preg_match('/\/\s*(\d+)\s*$/', trim((string)$contentRange), $matches)) {
			$contentRangeSize = (int)$matches[1];

			if ($contentRangeSize > 0) {
				return $contentRangeSize;
			}
		}

		$contentLength = wp_remote_retrieve_header($response, 'content-length');

		if (is_array($contentLength)) {
			$contentLength = end($contentLength);
		}

		if (is_scalar($contentLength) && preg_match('/^\d+$/', trim((string)$contentLength))) {
			$contentLengthSize = (int)$contentLength;

			if ($contentLengthSize > 0) {
				return $contentLengthSize;
			}
		}

		return false;
	}

	/**
	 * Get the size from a complete response body when the server does not expose
	 * Content-Length and does not support byte range requests.
	 *
	 * @param array|\WP_Error $response
	 * @param int             $maxFileSize
	 *
	 * @return int|false
	 */
	private static function getRemoteFileSizeFromCompleteBody($response, $maxFileSize)
	{
		if (is_wp_error($response) || ! is_array($response)) {
			return false;
		}

		$responseCode = (int)wp_remote_retrieve_response_code($response);

		if ($responseCode < 200 || $responseCode >= 300) {
			return false;
		}

		$body       = wp_remote_retrieve_body($response);
		$bodyLength = is_string($body) ? strlen($body) : 0;

		// The request reads one byte beyond the limit. Reaching that extra byte
		// proves that the response was truncated and its total size is unknown.
		if ($bodyLength < 1 || $bodyLength > $maxFileSize) {
			return false;
		}

		return $bodyLength;
	}

	/**
	 *
	 */
	public function ajaxCheckExternalUrlsForStatusCode()
	{
		if ( ! isset( $_POST['wpacu_nonce'] ) || ! wp_verify_nonce( $_POST['wpacu_nonce'], 'wpacu_ajax_check_external_urls_nonce' ) ) {
			wp_send_json_error(array('message' => 'The security nonce is not valid.'), 403);
		}

		if ( ! isset($_POST['action'], $_POST['wpacu_external_srcs_ref']) ) {
			wp_send_json_error(array('message' => 'The post parameters are not the right ones.'), 400);
		}

		// Check privileges
		if ( ! Menu::userCanAccessPlugin() ) {
			wp_send_json_error(array('message' => 'Not enough privileges to perform this action.'), 403);
		}

        $externalSrcsRef = sanitize_text_field($_POST['wpacu_external_srcs_ref']);

        $checkUrls = get_transient(WPACU_PLUGIN_ID . '_external_srcs_ref_' . $externalSrcsRef);

        if ( empty($checkUrls) ) {
			wp_send_json(array());
        }

        // Remove it from the database, it as it's meant to be used only once
        delete_transient(WPACU_PLUGIN_ID . '_external_srcs_ref_' . $externalSrcsRef);

        foreach ($checkUrls as $index => $checkUrl) {
			$checkUrlToRequest = is_string($checkUrl) ? $checkUrl : '';

			if (strncmp($checkUrlToRequest, '//', 2) === 0) { // starts with // (append the right protocol)
				if (strpos($checkUrlToRequest, 'fonts.googleapis.com') !== false)  {
					$checkUrlToRequest = 'https:'.$checkUrlToRequest;
				} else {
					// either HTTP or HTTPS depending on the current page situation (that the admin has loaded)
					$checkUrlToRequest = (Misc::isHttpsSecure() ? 'https:' : 'http:') . $checkUrlToRequest;
				}
			}

			$checkUrlToRequest = esc_url_raw($checkUrlToRequest);

			if (wp_http_validate_url($checkUrlToRequest) === false) {
				// Do not make server-side requests to malformed, local or private destinations.
				unset($checkUrls[$index]);
				continue;
			}

			$response = self::safeRemoteRequestWithValidatedRedirects($checkUrlToRequest, array(
				'method'              => 'GET',
				'limit_response_size' => 1
			));

			if (is_wp_error($response) && $response->get_error_code() === 'wpacu_unsafe_remote_url') {
				// An unsafe redirect means the status could not be checked reliably.
				unset($checkUrls[$index]);
				continue;
			}

			// Remove 200 OK ones as the other ones will remain for highlighting
			if (wp_remote_retrieve_response_code($response) === 200) {
				unset($checkUrls[$index]);
			}
		}

        wp_send_json(array_values($checkUrls));
	}

	/**
	 * Get a remote asset's size without downloading the whole file.
	 */
	public function ajaxGetExternalFileSize()
	{
		// Check nonce
		if ( ! isset( $_POST['wpacu_nonce'] ) || ! wp_verify_nonce( $_POST['wpacu_nonce'], 'wpacu_ajax_check_remote_file_size_nonce' ) ) {
			wp_send_json_error(array('message' => 'The security nonce is not valid.'), 403);
		}

		// Check privileges
		if (! Menu::userCanAccessPlugin()) {
			wp_send_json_error(array('message' => 'Not enough privileges to perform this action.'), 403);
		}

		$remoteFile = Misc::getVar('post', 'wpacu_remote_file', false);

		if ( ! is_string($remoteFile) || $remoteFile === '' ) {
			echo 'N/A (external file)';
			exit;
		}

		$remoteFile = wp_unslash($remoteFile);

		// If it starts with //
		if (strncmp($remoteFile, '//', 2) === 0) {
			$remoteFile = (Misc::isHttpsSecure() ? 'https:' : 'http:') . $remoteFile;
		}

		$remoteFileToCheck = esc_url_raw($remoteFile);

		if (wp_http_validate_url($remoteFileToCheck) === false) {
			echo 'The asset\'s URL could not be validated for a safe server-side request.';
			exit();
		}

		$response = self::safeRemoteRequestWithValidatedRedirects($remoteFileToCheck, array(
			'method' => 'HEAD'
		));

		$result = self::getRemoteFileSizeFromResponse($response);

		if ($result === false) {
			// Some servers do not support HEAD. Request only the first byte and
			// use Content-Range (preferred) or Content-Length when available.
			$response = self::safeRemoteRequestWithValidatedRedirects($remoteFileToCheck, array(
				'method'              => 'GET',
				'headers'             => array('Range' => 'bytes=0-0'),
				'limit_response_size' => 1
			));

			$result = self::getRemoteFileSizeFromResponse($response);
		}

		if ($result === false) {
			// Some CDNs expose no size headers and ignore Range. As a final fallback,
			// download small assets with a hard limit and measure the complete body.
			$maxFileSize = 5 * MB_IN_BYTES;
			$response    = self::safeRemoteRequestWithValidatedRedirects($remoteFileToCheck, array(
				'method'              => 'GET',
				'headers'             => array('Accept-Encoding' => 'identity'),
				'decompress'          => false,
				'limit_response_size' => $maxFileSize + 1
			));

			$result = self::getRemoteFileSizeFromCompleteBody($response, $maxFileSize);
		}

		echo $result === false ? 'N/A (external file)' : MiscAdmin::formatBytes($result);

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

        if ( is_admin() && in_array(Misc::getVar('post', 'page_type'), array('post', 'page', 'attachment', 'custom_post_type')) ) {
            $postId   = Misc::getVar('post', 'post_id');
            $postType = get_post_type($postId);
        } elseif (MainFront::isSingularPage() && Main::instance()->getCurrentPostId() > 0) {
            $postType = get_post_type(Main::instance()->getCurrentPostId());
        }

        if ($postType === 'post') {
            $data['page_unload_text'] = __('Unload on this post', 'wp-asset-clean-up');
            $data['page_load_text']   = __('On this post', 'wp-asset-clean-up');
        } elseif ($postType === 'product') {
            $data['page_unload_text'] = __('Unload on this product page', 'wp-asset-clean-up');
            $data['page_load_text']   = __('On this product page', 'wp-asset-clean-up');
        } elseif ($postType === 'attachment') {
            $data['page_unload_text'] = __('Unload on this media page', 'wp-asset-clean-up');
            $data['page_load_text']   = __('On this media page', 'wp-asset-clean-up');
        }

        if (MainFront::isHomePage() && Main::instance()->getCurrentPostId() < 1 && get_option('show_on_front') === 'posts') {
            $data['page_unload_text'] = __('Unload on this homepage', 'wp-asset-clean-up');
            $data['page_load_text']   = __('On this homepage', 'wp-asset-clean-up');
        }

        $data = apply_filters('wpacu_internal_assets_manager_text_rules_to_show_in_css_js_manager', $data);

        return $data;
    }
}
