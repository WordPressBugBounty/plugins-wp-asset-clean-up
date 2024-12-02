<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Admin;
use WpAssetCleanUp\AssetsManager;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\Maintenance;
use WpAssetCleanUp\Misc;

/**
 *
 * Class Overview
 * @package WpAssetCleanUp
 */
class Overview
{
	/**
	 * @var array
	 */
	public $data = array(
        'page_options_to_text' => array()
    );

	/**
	 * Overview constructor.
	 */
	public function __construct()
    {
        $this->data = array(
            'page_options_to_text' => array(
                'no_css_minify'      => __('Do not minify CSS', 'wp-asset-clean-up'),
                'no_css_optimize'    => __('Do not combine CSS', 'wp-asset-clean-up'),
                'no_js_minify'       => __('Do not minify JS', 'wp-asset-clean-up'),
                'no_js_optimize'     => __('Do not combine JS', 'wp-asset-clean-up'),
                'no_assets_settings' => __('Do not apply any CSS &amp; JavaScript settings', 'wp-asset-clean-up'),
                'no_wpacu_load'      => __('Do not load %s on this page', 'wp-asset-clean-up')
            )
        );

        // The code initiated in this function is relevant only in the "Overview" page
        if (Misc::getVar('request', 'page') !== WPACU_PLUGIN_ID . '_overview') {
            return;
        }

        $this->data['page_options_to_text']['no_wpacu_load'] = sprintf(__($this->data['page_options_to_text']['no_wpacu_load'], 'wp-asset-clean-up'), WPACU_PLUGIN_TITLE);

        // [START] Clear load exceptions for a handle
	    $transientName = 'wpacu_load_exceptions_cleared';
	    if ( isset( $_POST['wpacu_action'], $_POST['wpacu_handle'], $_POST['wpacu_asset_type'] )
	         && ( $wpacuAction = $_POST['wpacu_action'] )
	         && ( $wpacuHandle = $_POST['wpacu_handle'] )
	         && ( $wpacuAssetType = $_POST['wpacu_asset_type'] ) && $wpacuAction === 'clear_load_exceptions'
        ) {
	        check_admin_referer('wpacu_clear_load_exceptions', 'wpacu_clear_load_exceptions_nonce');
            Maintenance::removeAllLoadExceptionsFor($wpacuHandle, $wpacuAssetType);
            set_transient($transientName, array('handle' => $wpacuHandle, 'type' => $wpacuAssetType));
            wp_redirect(admin_url('admin.php?page=wpassetcleanup_overview&wpacu_load_exceptions_cleared=1'));
            exit();
        }

	    if (Misc::getVar('get', 'wpacu_load_exceptions_cleared') && $transientData = get_transient($transientName)) {
	        add_action('admin_notices', static function() use ($transientData, $transientName) {
		        $wpacuAssetTypeToPrint = ($transientData['type'] === 'styles') ? 'CSS' : 'JavaScript';
	            ?>
                <div class="notice wpacu-notice-info is-dismissible">
                    <p><span style="color: #008f9c; font-size: 26px; margin-right: 4px; vertical-align: text-bottom; margin-bottom: 0;" class="dashicons dashicons-yes"></span> <?php echo sprintf(__('The load exception rules for the `<strong>%s</strong>` %s handle have been removed.', 'wp-asset-clean-up'), $transientData['handle'], $wpacuAssetTypeToPrint); ?></p>
                </div>
                <?php
                delete_transient($transientName);
            }, PHP_INT_MAX);
        }
	    // [END] Clear load exceptions for a handle

	    // [START] Clear all rules for the chosen "orphaned" handle
	    $transientName = 'wpacu_all_rules_cleared';
	    if ( isset( $_POST['wpacu_action'], $_POST['wpacu_handle'], $_POST['wpacu_asset_type'] )
	         && ( $wpacuAction = $_POST['wpacu_action'] )
	         && ( $wpacuHandle = $_POST['wpacu_handle'] )
	         && ( $wpacuAssetType = $_POST['wpacu_asset_type'] ) && $wpacuAction === 'clear_all_rules'
	    ) {
		    check_admin_referer('wpacu_clear_all_rules', 'wpacu_clear_all_rules_nonce');
		    Maintenance::removeAllRulesFor($wpacuHandle, $wpacuAssetType);
		    set_transient(WPACU_PLUGIN_ID . '_all_rules_cleared', array('handle' => $wpacuHandle, 'type' => $wpacuAssetType));
		    wp_redirect(admin_url('admin.php?page=wpassetcleanup_overview&wpacu_all_rules_cleared=1'));
		    exit();
	    }

	    if (Misc::getVar('get', 'wpacu_all_rules_cleared') && $transientData = get_transient($transientName)) {
		    add_action('admin_notices', static function() use ($transientData, $transientName) {
			    $wpacuAssetTypeToPrint = ($transientData['type'] === 'styles') ? 'CSS' : 'JavaScript';
			    ?>
                <div class="notice wpacu-notice-info is-dismissible">
                    <p><span style="color: #008f9c; font-size: 26px; margin-right: 4px; vertical-align: text-bottom; margin-bottom: 0;" class="dashicons dashicons-yes"></span> <?php echo sprintf(__('All the rules were cleared for the orphaned `<strong>%s</strong>` %s handle.', 'wp-asset-clean-up'), $transientData['handle'], $wpacuAssetTypeToPrint); ?></p>
                </div>
			    <?php
			    delete_transient($transientName);
		    }, PHP_INT_MAX);
	    }
	    // [END] Clear all rules for the chosen "orphaned" handle

	    // [START] Clear all redundant unloading rules (if the site-wide one is already enabled)
	    $transientName = 'wpacu_all_redundant_unload_rules_cleared';
	    if ( isset( $_POST['wpacu_action'], $_POST['wpacu_handle'], $_POST['wpacu_asset_type'] )
	         && ( $wpacuAction = $_POST['wpacu_action'] )
	         && ( $wpacuHandle = $_POST['wpacu_handle'] )
	         && ( $wpacuAssetType = $_POST['wpacu_asset_type'] ) && $wpacuAction === 'clear_all_redundant_unload_rules'
	    ) {
		    check_admin_referer('wpacu_clear_all_redundant_rules', 'wpacu_clear_all_redundant_rules_nonce');
		    Maintenance::removeAllRedundantUnloadRulesFor($wpacuHandle, $wpacuAssetType);
		    set_transient($transientName, array('handle' => $wpacuHandle, 'type' => $wpacuAssetType));
		    wp_redirect(admin_url('admin.php?page=wpassetcleanup_overview&wpacu_all_redundant_unload_rules_cleared=1'));
		    exit();
	    }

	    if (Misc::getVar('get', 'wpacu_all_redundant_unload_rules_cleared') && $transientData = get_transient($transientName)) {
		    add_action('admin_notices', static function() use ($transientData, $transientName) {
			    $wpacuAssetTypeToPrint = ($transientData['type'] === 'styles') ? 'CSS' : 'JavaScript';
			    ?>
                <div class="notice wpacu-notice-info is-dismissible">
                    <p><span style="color: #008f9c; font-size: 26px; margin-right: 4px; vertical-align: text-bottom; margin-bottom: 0;" class="dashicons dashicons-yes"></span> <?php echo sprintf(__('All the redundant unload rules were cleared for the `<strong>%s</strong>` %s handle, leaving the only one relevant: `site-wide (everywhere)`.', 'wp-asset-clean-up'), $transientData['handle'], $wpacuAssetTypeToPrint); ?></p>
                </div>
			    <?php
			    delete_transient($transientName);
		    }, PHP_INT_MAX);
	    }
	    // [END] Clear all redundant unloading rules (if the site-wide one is already enabled)
    }

    /**
	 * @return array
	 */
	public static function handlesWithAtLeastOneRule()
    {
        global $wpdb;

	    $wpacuPluginId = WPACU_PLUGIN_ID;

	    $allHandles = array();

	    /*
		 * Per page rules (unload, load exceptions if a bulk rule is enabled, async & defer for SCRIPT tags)
		 */
	    // Homepage (Unloads)
	    $wpacuFrontPageUnloads = get_option(WPACU_PLUGIN_ID . '_front_page_no_load');

	    if ($wpacuFrontPageUnloads) {
		    $wpacuFrontPageUnloadsArray = @json_decode( $wpacuFrontPageUnloads, ARRAY_A );

		    foreach (array('styles', 'scripts') as $assetType) {
			    if ( ! empty( $wpacuFrontPageUnloadsArray[$assetType] ) ) {
				    foreach ( $wpacuFrontPageUnloadsArray[$assetType] as $assetHandle ) {
					    $allHandles[$assetType][ $assetHandle ]['unload_on_home_page'] = 1;
					    }
			    }
		    }
	    }

	    // Homepage (Load Exceptions)
	    $wpacuFrontPageLoadExceptions = get_option(WPACU_PLUGIN_ID . '_front_page_load_exceptions');

	    if ($wpacuFrontPageLoadExceptions) {
		    $wpacuFrontPageLoadExceptionsArray = @json_decode( $wpacuFrontPageLoadExceptions, ARRAY_A );

		    foreach ( array('styles', 'scripts') as $assetType ) {
			    if ( ! empty( $wpacuFrontPageLoadExceptionsArray[$assetType] ) ) {
				    foreach ( $wpacuFrontPageLoadExceptionsArray[$assetType] as $assetHandle ) {
					    $allHandles[$assetType][ $assetHandle ]['load_exception_on_home_page'] = 1;
					    }
			    }
		    }
	    }

	    // Homepage (async, defer)
	    $wpacuFrontPageData = get_option(WPACU_PLUGIN_ID . '_front_page_data');

	    if ($wpacuFrontPageData) {
		    $wpacuFrontPageDataArray = @json_decode( $wpacuFrontPageData, ARRAY_A );
		    if ( ! empty($wpacuFrontPageDataArray['scripts']) ) {
			    foreach ($wpacuFrontPageDataArray['scripts'] as $assetHandle => $assetData) {
				    if ( ! empty($assetData['attributes']) ) {
					    // async, defer attributes
					    $allHandles['scripts'][ $assetHandle ]['script_attrs']['home_page'] = $assetData['attributes'];
					    }
			    }
		    }

		    // Do not apply "async", "defer" exceptions (e.g. "defer" is applied site-wide, except the home page)
		    if ( ! empty($wpacuFrontPageDataArray['scripts_attributes_no_load']) ) {
			    foreach ($wpacuFrontPageDataArray['scripts_attributes_no_load'] as $assetHandle => $assetAttrsNoLoad) {
				    $allHandles['scripts'][$assetHandle]['attrs_no_load']['home_page'] = $assetAttrsNoLoad;
				    }
		    }
	    }

	    // Custom Post Type Load Exceptions
        // e.g. the asset could be unloaded site-wide and loaded on all pages belonging to a post type such as WooCommerce single 'product' page
	    $wpacuPostTypeLoadExceptions = get_option(WPACU_PLUGIN_ID . '_post_type_load_exceptions');

	    if ($wpacuPostTypeLoadExceptions) {
		    $wpacuPostTypeLoadExceptionsArray = @json_decode( $wpacuPostTypeLoadExceptions, ARRAY_A );

            foreach ( $wpacuPostTypeLoadExceptionsArray as $wpacuPostType => $dbAssetHandles ) {
	            foreach ( array('styles', 'scripts') as $assetType ) {
	                if (isset($dbAssetHandles[$assetType]) && $dbAssetHandles[$assetType]) {
	                    foreach ($dbAssetHandles[$assetType] as $assetHandle => $assetValue) {
	                        if ($assetValue !== '') {
		                        $allHandles[ $assetType ][ $assetHandle ]['load_exception_post_type'][] = $wpacuPostType;
		                        }
	                    }
	                }
	            }
            }
	    }

	    // Get all Asset CleanUp (Pro) meta keys from all WordPress meta tables where it can be possibly used
	    foreach (array($wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta) as $tableName) {
		    $wpacuGetValuesQuery = <<<SQL
SELECT * FROM `{$tableName}`
WHERE meta_key IN('_{$wpacuPluginId}_no_load', '_{$wpacuPluginId}_data', '_{$wpacuPluginId}_load_exceptions')
SQL;
		    $wpacuMetaData = $wpdb->get_results( $wpacuGetValuesQuery, ARRAY_A );

		    foreach ( $wpacuMetaData as $wpacuValues ) {
			    $decodedValues = @json_decode( $wpacuValues['meta_value'], ARRAY_A );

			    if ( empty( $decodedValues ) ) {
				    continue;
			    }

			    // $refId is the ID for the targeted element from the meta table which could be: post, taxonomy ID or user ID
			    if ($tableName === $wpdb->postmeta) {
				    $refId = $wpacuValues['post_id'];
				    $refKey = 'post';
			    } elseif ($tableName === $wpdb->termmeta) {
				    $refId = $wpacuValues['term_id'];
				    $refKey = 'term';
			    } else {
				    $refId = $wpacuValues['user_id'];
				    $refKey = 'user';
			    }

			    if ( $wpacuValues['meta_key'] === '_' . $wpacuPluginId . '_no_load' ) {
				    foreach ( $decodedValues as $assetType => $assetHandles ) {
					    foreach ( $assetHandles as $assetHandle ) {
						    // Unload it on this page
						    $allHandles[ $assetType ][ $assetHandle ]['unload_on_this_page'][$refKey][] = $refId;
						    }
				    }
			    } elseif ( $wpacuValues['meta_key'] === '_' . $wpacuPluginId . '_load_exceptions' ) {
				    foreach ( $decodedValues as $assetType => $assetHandles ) {
					    foreach ( $assetHandles as $assetHandle ) {
						    // If bulk unloaded, 'Load it on this page'
						    $allHandles[ $assetType ][ $assetHandle ]['load_exception_on_this_page'][$refKey][] = $refId;
						    }
				    }
			    } elseif ( $wpacuValues['meta_key'] === '_' . $wpacuPluginId . '_data' ) {
				    if ( ! empty( $decodedValues['scripts'] ) ) {
					    foreach ( $decodedValues['scripts'] as $assetHandle => $scriptData ) {
						    if ( ! empty( $scriptData['attributes'] ) ) {
							    // async, defer attributes
							    $allHandles['scripts'][ $assetHandle ]['script_attrs'][$refKey][$refId] = $scriptData['attributes'];
							    }
					    }
				    }

				    if ( ! empty( $decodedValues['scripts_attributes_no_load'] ) ) {
					    foreach ( $decodedValues['scripts_attributes_no_load'] as $assetHandle => $scriptNoLoadAttrs ) {
						    $allHandles['scripts'][$assetHandle]['attrs_no_load'][$refKey][$refId] = $scriptNoLoadAttrs;
						    }
				    }
			    }
		    }
	    }

	    /*
		 * Global (Site-wide) Rules: Preloading, Position changing, Unload via RegEx, etc.
		 */
	    $wpacuGlobalDataArray = wpacuGetGlobalData();

	    $allPossibleDataTypes = array(
            'load_it_logged_in',
            'preloads',
            'notes',
            'ignore_child',
            'everywhere'
        );

	    foreach (array('styles', 'scripts') as $assetType) {
		    if ($assetType === 'scripts' && isset( $wpacuGlobalDataArray[ $assetType ])) {
                foreach (array_keys($wpacuGlobalDataArray[ $assetType ]) as $dataType) {
                    if ( strpos( $dataType, 'custom_post_type_archive_' ) !== false ) {
                        $allPossibleDataTypes[] = $dataType;
                    }
                }

			    }

		    foreach ($allPossibleDataTypes as $dataType) {
			    if ( ! empty( $wpacuGlobalDataArray[ $assetType ][$dataType] ) ) {
				    foreach ( $wpacuGlobalDataArray[ $assetType ][$dataType] as $assetHandle => $dataValue ) {
					    if ($dataType === 'everywhere' && $assetType === 'scripts' && isset($dataValue['attributes'])) {
						    if (count($dataValue['attributes']) === 0) {
							    continue;
						    }
						    // async/defer applied site-wide
						    $allHandles[ $assetType ][ $assetHandle ]['script_site_wide_attrs'] = $dataValue['attributes'];
						    } elseif ($dataType !== 'everywhere' && $assetType === 'scripts' && isset($dataValue['attributes'])) {
						    // For date, 404, search pages
						    $allHandles[ $assetType ][ $assetHandle ]['script_attrs'][$dataType] = $dataValue['attributes'];
						    } else {
						    $allHandles[ $assetType ][ $assetHandle ][ $dataType ] = $dataValue;
						    }
				    }
			    }
		    }
        }

	    /*
		 * Unload Site-Wide (Everywhere) Rules: Preloading, Position changing, Unload via RegEx, etc.
		 */
	    $wpacuGlobalUnloadData = get_option(WPACU_PLUGIN_ID . '_global_unload');
	    $wpacuGlobalUnloadDataArray = @json_decode($wpacuGlobalUnloadData, ARRAY_A);

	    foreach (array('styles', 'scripts') as $assetType) {
		    if ( ! empty($wpacuGlobalUnloadDataArray[$assetType]) ) {
			    foreach ($wpacuGlobalUnloadDataArray[$assetType] as $assetHandle) {
				    $allHandles[ $assetType ][ $assetHandle ]['unload_site_wide'] = 1;
				    }
		    }
	    }

	    /*
		* Bulk Unload Rules - post, page, custom post type (e.g. product, download), taxonomy (e.g. category), 404, date, archive (for custom post type) with pagination etc.
		*/
	    $wpacuBulkUnloadData = get_option(WPACU_PLUGIN_ID . '_bulk_unload');
	    $wpacuBulkUnloadDataArray = @json_decode($wpacuBulkUnloadData, ARRAY_A);

	    foreach (array('styles', 'scripts') as $assetType) {
		    if ( ! empty($wpacuBulkUnloadDataArray[$assetType]) ) {
			    foreach ($wpacuBulkUnloadDataArray[$assetType] as $unloadBulkType => $unloadBulkValues) {
				    if (empty($unloadBulkValues)) {
					    continue;
				    }

				    // $unloadBulkType could be 'post_type', 'post_type_via_tax', 'date', '404', 'taxonomy', 'search', 'custom_post_type_archive_[post_type_name_here]', etc.
				    if ($unloadBulkType === 'post_type') {
					    foreach ($unloadBulkValues as $postType => $assetHandles) {
						    foreach ($assetHandles as $assetHandle) {
							    $allHandles[ $assetType ][ $assetHandle ]['unload_bulk'][$unloadBulkType][] = $postType;
							    }
					    }
				    }
			    }
		    }
	    }

	    if (isset($allHandles['styles'])) {
		    ksort($allHandles['styles']);
	    }

	    if (isset($allHandles['scripts'])) {
		    ksort($allHandles['scripts']);
	    }

	    return $allHandles;
    }

	/**
	 *
	 */
	public function pageOverview()
	{
		$allHandles = self::handlesWithAtLeastOneRule();
		$this->data['handles'] = $allHandles;

		if (isset($this->data['handles']['styles']) || isset($this->data['handles']['scripts'])) {
			// Only fetch the assets' information if there is something to be shown
			// to avoid useless queries to the database
			$this->data['assets_info'] = Main::getHandlesInfo();
			$this->data['external_srcs_ref'] = AssetsManager::setExternalSrcsRef($this->data['assets_info'], 'overview');
		}

		// [PAGE OPTIONS]
		// 1) For posts, pages and custom post types
		global $wpdb;

		$this->data['page_options_results'] = array();

		$pageOptionsResults = $wpdb->get_results('SELECT post_id, meta_value FROM `'.$wpdb->postmeta."` WHERE meta_key='_".WPACU_PLUGIN_ID."_page_options' && meta_value !=''", ARRAY_A);

		foreach ($pageOptionsResults as $pageOptionResult) {
			$postId = $pageOptionResult['post_id'];
			$optionsDecoded = @json_decode( $pageOptionResult['meta_value'], ARRAY_A );

			if (is_array($optionsDecoded) && ! empty($optionsDecoded)) {
				$this->data['page_options_results']['posts'][] = array('post_id' => $postId, 'options' => $optionsDecoded);
			}
		}

		// 2) For the homepage set as latest posts (e.g. not a single page set as the front page, this is included in the previous check)
        $globalPageOptionsList = wpacuGetGlobalData();

        if ( ! empty( $globalPageOptionsList['page_options']['homepage'] ) ) {
            $this->data['page_options_results']['homepage'] = array('options' => $globalPageOptionsList['page_options']['homepage']);
        }
		// [/PAGE OPTIONS]

		// [CRITICAL CSS]
        $this->data['critical_css_disabled'] = $this->data['critical_css_config'] = false;

        if (Main::instance()->settings['critical_css_status'] === 'off') {
            $this->data['critical_css_disabled'] = true;
        }

        $criticalCssConfigOption = get_option(WPACU_PLUGIN_ID.'_critical_css_config');

        if ($criticalCssConfigOption) {
            $this->data['critical_css_config'] = json_decode( $criticalCssConfigOption, ARRAY_A );
        }
        // [/CRITICAL CSS]

		MainAdmin::instance()->parseTemplate('admin-page-overview', $this->data, true);
	}

	/**
	 * @param $handle
	 * @param $assetType
	 * @param $data
	 * @param string $for ('default': bulk unloads, regex unloads)
	 */
	public static function renderHandleTd($handle, $assetType, $data, $for = 'default')
	{
		global $wp_version;

		$handleData = '';
		$isCoreFile = false; // default

        $assetTypeS = substr($assetType, 0, -1); // "styles" to "style" & "scripts" to "script"

        if (isset($data['handles'][$assetType][$handle]) && $data['handles'][$assetType][$handle]) {
            $handleData = $data['handles'][$assetType][$handle];
        }

        if ( $for === 'default' ) {
            // Show the original "src" and "ver, not the altered one
            // (if filters such as "wpacu_{$handle}_(css|js)_handle_obj" were used to load alternative versions of the file, depending on the situation)
            $srcKey = isset($data['assets_info'][ $assetType ][ $handle ]['src_origin']) ? 'src_origin' : 'src';
	        $verKey = isset($data['assets_info'][ $assetType ][ $handle ]['ver_origin']) ? 'ver_origin' : 'ver';

            $src = (isset( $data['assets_info'][ $assetType ][ $handle ][$srcKey] ) && $data['assets_info'][ $assetType ][ $handle ][$srcKey]) ? $data['assets_info'][ $assetType ][ $handle ][$srcKey] : false;

            $conditionalComment = $conditionalCommentOutput = '';

            if (isset($data['assets_info'][$assetType][$handle]['extra']['conditional']) && $data['assets_info'][$assetType][$handle]['extra']['conditional']) {
                // Enqueued asset
                $conditionalComment = $data['assets_info'][$assetType][$handle]['extra']['conditional'];
            }

            if ($conditionalComment) {
                $conditionalCommentOutput = '<small>&nbsp;&nbsp;<span><img style="vertical-align: middle;" width="20" height="20" src="'.WPACU_PLUGIN_URL.'/assets/icons/icon-ie.svg" alt="" title="Microsoft / Public domain" />&nbsp;<span style="font-weight: 400; color: #1C87CF;">Loads only in Internet Explorer based on the following condition:</span> <em> if '.$conditionalComment.'</em></span></small>&nbsp;';
            }

            $isExternalSrc = true;

            if ($assetType === 'styles') {
                $isBase64EncodedSrc = stripos($src, 'data:text/css;base64,') !== false;
            } else {
                $isBase64EncodedSrc = stripos($src, 'data:text/javascript;base64,') !== false;
            }

            if ($isBase64EncodedSrc
                || Misc::getLocalSrcIfExist($src)
                || strpos($src, '/?') !== false // Dynamic Local URL
                || strncmp(str_replace(site_url(), '', $src), '?', 1) === 0 // Starts with ? right after the site url (it's a local URL)
            ) {
                $isExternalSrc = false;
                $isCoreFile = MiscAdmin::isCoreFile($data['assets_info'][$assetType][$handle]);
            }

            if ($isBase64EncodedSrc) {
                $src = Misc::getHrefFromSource($src);
            }

            $ver = $wp_version; // default
            if (isset($data['assets_info'][$assetType][$handle][$verKey]) && $data['assets_info'][$assetType][$handle][$verKey]) {
                $ver = is_array($data['assets_info'][$assetType][$handle][$verKey])
                    ? implode(',', $data['assets_info'][$assetType][$handle][$verKey])
                    : $data['assets_info'][$assetType][$handle][$verKey];
            }
	        ?>
            <strong><span style="color: green;"><?php echo esc_html($handle); ?></span></strong>
            <small><em>v<?php echo esc_html($ver); ?></em></small>
            <?php
            if ($isCoreFile) {
            ?>
                <span title="WordPress Core File" style="font-size: 15px; vertical-align: middle;" class="dashicons dashicons-wordpress-alt wpacu-tooltip"></span>
                <?php
            }

            // If called from "Bulk Changes" -> "Preloads"
            $preloadedStatus = isset($data['assets_info'][ $assetType ][ $handle ]['preloaded_status']) ? $data['assets_info'][ $assetType ][ $handle ]['preloaded_status'] : false;
            if ($preloadedStatus === 'async') { echo '&nbsp;(<strong><em>'.$preloadedStatus.'</em></strong>)'; }

            $handleExtras = array();

            // If called from "Overview"
	        if (isset($handleData['preloads']) && $handleData['preloads']) {
		        $handleExtras[0] = ' / &nbsp;<span style="font-weight: 600;">Preloaded</span>';

	            if ($handleData['preloads'] === 'async') {
		            $handleExtras[0] .= ' (async)';
                }
	        }

	        if (isset($handleData['positions']) && $handleData['positions']) {
                $handleExtras[1] = '<span style="color: #004567; font-weight: 600;">Moved to <code>&lt;'.esc_html($handleData['positions']).'&gt;</code></span>';
            }

	        /*
	         * 1) Per page (homepage, a post, a category, etc.)
	         * Async, Defer attributes
	         */
            // Per home page
	        if ( ! empty($handleData['script_attrs']['home_page']) ) {
		        ksort($handleData['script_attrs']['home_page']);
		        $handleExtras[2] = 'Homepage attributes: <strong>'.esc_html(implode(', ', $handleData['script_attrs']['home_page'])).'</strong>';
	        }

	        // Date archive pages
	        if ( ! empty($handleData['script_attrs']['date']) ) {
		        ksort($handleData['script_attrs']['date']);
		        $handleExtras[22] = 'Date archive attributes: <strong>'.esc_html(implode(', ', $handleData['script_attrs']['date'])).'</strong>';
	        }

	        // 404 page
	        if ( ! empty($handleData['script_attrs']['404']) ) {
		        ksort($handleData['script_attrs']['404']);
		        $handleExtras[23] = '404 Not Found attributes: <strong>'.esc_html(implode(', ', $handleData['script_attrs']['404'])).'</strong>';
	        }

	        // Search results page
	        if ( ! empty($handleData['script_attrs']['search']) ) {
		        ksort($handleData['script_attrs']['search']);
		        $handleExtras[24] = '404 Not Found attributes: <strong>'.esc_html(implode(', ', $handleData['script_attrs']['search'])).'</strong>';
	        }

            // Archive page for Custom Post Type (those created via theme editing or via plugins such as "Custom Post Type UI")
            $scriptAttrsStr = (isset($handleData['script_attrs']) && is_array($handleData['script_attrs'])) ? implode('', array_keys($handleData['script_attrs'])) : '';

	        if (strpos($scriptAttrsStr, 'custom_post_type_archive_') !== false) {
	            $keyNo = 225;
	            foreach ($handleData['script_attrs'] as $scriptAttrsKey => $scriptAttrsValue) {
	                $customPostTypeName = str_replace('custom_post_type_archive_', '', $scriptAttrsKey);
		            $handleExtras[$keyNo] = 'Archive custom post type page "'.$customPostTypeName.'" attributes: <strong>'.esc_html(implode(', ', $handleData['script_attrs'][$scriptAttrsKey])).'</strong>';
		            $keyNo++;
	            }
	        }

	        // Per post page
            if ( ! empty($handleData['script_attrs']['post']) ) {
	            $handleExtras[3] = 'Per post attributes: ';

		        $postsList = '';

		        ksort($handleData['script_attrs']['post']);

		        foreach ($handleData['script_attrs']['post'] as $postId => $attrList) {
			        $postData   = get_post($postId);

                    if (isset($postData->post_title, $postData->post_type)) {
	                    $postTitle = $postData->post_title;
	                    $postType  = $postData->post_type;
	                    $postsList .= '<a title="Post Title: ' . esc_attr( $postTitle ) . ', Post Type: ' . esc_attr( $postType ) . '" class="wpacu-tooltip" target="_blank" href="' . esc_url( admin_url( 'post.php?post=' . $postId . '&action=edit' ) ) . '">' . $postId . '</a> - <strong>' . esc_html( implode( ', ', $attrList ) ) . '</strong> / ';
                    } else {
	                    $postsList .= '<s class="wpacu-tooltip" title="N/A (post deleted)" style="color: #cc0000;">'.$postId.'</s> / ';
                    }
		        }

	            $handleExtras[3] .= rtrim($postsList, ' / ');
	        }

            // User archive page (specific author)
	        if ( ! empty($handleData['script_attrs']['user']) ) {
		        $handleExtras[31] = 'Per author page attributes: ';

		        $authorPagesList = '';

		        ksort($handleData['script_attrs']['user']);

		        foreach ($handleData['script_attrs']['user'] as $userId => $attrList) {
			        $authorLink = get_author_posts_url(get_the_author_meta('ID', $userId));
			        $authorRelLink = str_replace(site_url(), '', $authorLink);

			        $authorPagesList .= '<a target="_blank" href="'.$authorLink.'">'.$authorRelLink.'</a> - <strong>'.implode(', ', $attrList).'</strong> | ';
		        }

		        $authorPagesList = trim($authorPagesList, ' | ');

		        $handleExtras[31] .= rtrim($authorPagesList, ' / ');
	        }

            // Per category page
            if ( ! empty($handleData['script_attrs']['term']) ) {
	            $handleExtras[33] = 'Per taxonomy attributes: ';

                $taxPagesList = '';

	            foreach ($handleData['script_attrs']['term'] as $termId => $attrList) {
		            $taxData = term_exists( (int)$termId ) ? get_term( $termId ) : false;

		            if ( ! $taxData || ( isset($taxData->errors['invalid_taxonomy']) && ! empty($taxData->errors['invalid_taxonomy']) ) ) {
			            $taxPagesList .= '<span style="color: darkred; font-style: italic;">Error: Taxonomy with ID '.$termId.' does not exist anymore (rule does not apply)</span> | ';
		            } else {
			            $taxonomy    = $taxData->taxonomy;
			            $termLink    = get_term_link( $taxData, $taxonomy );
			            $termRelLink = str_replace( site_url(), '', $termLink );
			            $taxPagesList .= '<a href="' . esc_attr($termRelLink) . '">' . esc_html($termRelLink) . '</a> - <strong>' . esc_html(implode( ', ', $attrList )) . '</strong> | ';
		            }
	            }

	            $taxPagesList = trim($taxPagesList, ' | ');

	            $handleExtras[33] .= rtrim($taxPagesList, ' / ');
            }

            /*
             * 2) Site-wide type
             * Any async, defer site-wide attributes? Exceptions will be also shown
             */
	        if (isset($handleData['script_site_wide_attrs'])) {
		        $handleExtras[4] = 'Site-wide attributes: ';
		        foreach ( $handleData['script_site_wide_attrs'] as $attrValue ) {
			        $handleExtras[4] .= '<strong>' . esc_html($attrValue) . '</strong>';

			        // Are there any exceptions? e.g. async, defer unloaded site-wide, but loaded on the homepage
			        if ( ! empty( $handleData['attrs_no_load'] ) ) {
				        // $attrSetIn could be 'home_page', 'term', 'user', 'date', '404', 'search'
				        $handleExtras[4] .= ' <em>(with exceptions from applying added for these pages: ';

				        $handleAttrsExceptionsList = '';

				        foreach ( $handleData['attrs_no_load'] as $attrSetIn => $attrSetValues ) {
					        if ( $attrSetIn === 'home_page' && in_array($attrValue, $attrSetValues) ) {
						        $handleAttrsExceptionsList .= ' Homepage, ';
					        }

					        if ( $attrSetIn === 'date' && in_array($attrValue, $attrSetValues) ) {
						        $handleAttrsExceptionsList .= ' Date Archive, ';
					        }

					        if ( (int)$attrSetIn === 404 && in_array($attrValue, $attrSetValues) ) {
						        $handleAttrsExceptionsList .= ' 404 Not Found, ';
					        }

					        if ( $attrSetIn === 'search' && in_array($attrValue, $attrSetValues) ) {
						        $handleAttrsExceptionsList .= ' Search Results, ';
					        }

					        if (strpos($attrSetIn, 'custom_post_type_archive_') !== false) {
						        $customPostTypeName = str_replace('custom_post_type_archive_', '', $attrSetIn);
					            $handleAttrsExceptionsList .= ' Archive "'.$customPostTypeName.'" custom post type, ';
					        }

					        // Post pages such as posts, pages, product (WooCommerce), download (Easy Digital Downloads), etc.
					        if ( $attrSetIn === 'post' ) {
						        $postPagesList = '';

						        foreach ( $attrSetValues as $postId => $attrSetValuesTwo ) {
							        if (! in_array($attrValue, $attrSetValuesTwo)) {
								        continue;
							        }

							        $postData   = get_post($postId);

                                    if (isset($postData->post_title, $postData->post_type)) {
	                                    $postTitle = $postData->post_title;
	                                    $postType  = $postData->post_type;

	                                    $postPagesList .= '<a title="Post Title: ' . esc_attr( $postTitle ) . ', Post Type: ' . esc_attr( $postType ) . '" class="wpacu-tooltip" target="_blank" href="' . esc_url( admin_url( 'post.php?post=' . $postId . '&action=edit' ) ) . '">' . $postId . '</a> | ';
                                    } else {
	                                    $postPagesList .= '<s style="color: #cc0000;">'.$postId.'</s> <em>N/A (post deleted)</em> | ';
                                    }
						        }

						        if ($postPagesList) {
						            $postPagesList = trim( $postPagesList, ' | ' ).', ';
						            $handleAttrsExceptionsList .= $postPagesList;
						        }
					        }

					        // Taxonomy pages such as category archive, product category in WooCommerce
					        if ( $attrSetIn === 'term' ) {
						        $taxPagesList = '';

						        foreach ( $attrSetValues as $termId => $attrSetValuesTwo ) {
						            if (! in_array($attrValue, $attrSetValuesTwo)) {
						                continue;
                                    }

							        $taxData = term_exists((int)$termId) ? get_term( $termId ) : false;

							        if ( ! $taxData || (isset($taxData->errors['invalid_taxonomy']) && ! empty($taxData->errors['invalid_taxonomy'])) ) {
								        $taxPagesList .= '<span style="color: darkred; font-style: italic;">Error: Taxonomy with ID '.(int)$termId.' does not exist anymore (rule does not apply)</span> | ';
							        } else {
								        $taxonomy    = $taxData->taxonomy;
								        $termLink    = get_term_link( $taxData, $taxonomy );
								        $termRelLink = str_replace( site_url(), '', $termLink );

								        $taxPagesList .= '<a href="' . esc_url($termRelLink) . '">' . esc_url($termRelLink) . '</a> | ';
							        }
						        }

						        if ($taxPagesList) {
							        $taxPagesList = trim( $taxPagesList, ' | ' ) . ', ';
							        $handleAttrsExceptionsList .= $taxPagesList;
						        }
					        }

					        // Author archive pages (e.g. /author/john/page/2/)
					        if ($attrSetIn === 'user') {
						        $authorPagesList = '';

						        foreach ( $attrSetValues as $userId => $attrSetValuesTwo ) {
							        if (! in_array($attrValue, $attrSetValuesTwo)) {
								        continue;
							        }

							        $authorLink = get_author_posts_url(get_the_author_meta('ID', $userId));
							        $authorRelLink = str_replace(site_url(), '', $authorLink);

							        $authorPagesList .= '<a target="_blank" href="'.esc_url($authorLink).'">'.esc_html($authorRelLink).'</a> | ';
						        }

						        if ($authorPagesList) {
						            $authorPagesList = trim( $authorPagesList, ' | ' ).', ';
						            $handleAttrsExceptionsList .= $authorPagesList;
						        }
                            }
				        }

				        $handleAttrsExceptionsList = trim($handleAttrsExceptionsList, ', ');

				        $handleExtras[4] .= $handleAttrsExceptionsList;
				        $handleExtras[4] .= '</em>), ';
			        }

			        $handleExtras[4] .= ', ';
		        }

		        $handleExtras[4] = trim($handleExtras[4], ', ');
	        }

	        if (! empty($handleExtras)) {
		        echo '<small>' . implode( ' <span style="font-weight: 300; color: grey;">/</span> ', $handleExtras ) . '</small>';
	        }

            if ( $src ) {
                if ( ! $isBase64EncodedSrc ) {
                    $verDb = (isset($data['assets_info'][ $assetType ][ $handle ][$verKey]) && $data['assets_info'][ $assetType ][ $handle ][$verKey]) ? $data['assets_info'][ $assetType ][ $handle ][$verKey] : false;
                    $appendAfterSrc = (strpos($src, '?') === false) ? '?' : '&';

                    if ( $verDb ) {
                        if (is_array($verDb)) {
                            $appendAfterSrc .= http_build_query(array('ver' => $data['assets_info'][ $assetType ][ $handle ][$verKey]));
                        } else {
                            $appendAfterSrc .= 'ver='.$ver;
                        }
                    } else {
                        $appendAfterSrc .= 'ver='.$wp_version; // default
                    }
                    ?>
                    <div>
                        <a <?php if ($isExternalSrc) { ?> data-wpacu-external-source="<?php echo esc_attr($src . $appendAfterSrc); ?>" <?php } ?> href="<?php echo esc_attr(Misc::getHrefFromSource($src) . $appendAfterSrc); ?>" target="_blank">
                            <small><?php echo str_replace( site_url(), '', $src ); ?></small>
                        </a> <?php if ($isExternalSrc) { ?><span data-wpacu-external-source-status></span><?php } ?>
                    </div>
                    <?php
                    $maybeInactiveAsset = Admin\MiscAdmin::maybeIsInactiveAsset($src);

                    if (is_array($maybeInactiveAsset) && ! empty($maybeInactiveAsset)) {
                        $uniqueStr = md5($handle . $assetType);
                        $clearAllRulesConfirmMsg = sprintf(esc_attr(__('This will clear all rules (unloads, load exceptions and other settings) for the `%s` CSS handle', 'wp-asset-clean-up')), $handle) . ".\n\n" . esc_attr(__('Click `OK` to confirm the action', 'wp-asset-clean-up')).'!';
                        ?>
                        <div>
                            <?php if ($maybeInactiveAsset['from'] === 'plugin') { ?>
                                <small><strong>Note:</strong> <span style="color: darkred;">The plugin `<strong><?php echo esc_html($maybeInactiveAsset['name']); ?></strong>` seems to be inactive, thus any rules set are also inactive &amp; irrelevant, unless you re-activate the plugin.</span></small>
                            <?php } elseif ($maybeInactiveAsset['from'] === 'theme') { ?>
                                <small><strong>Note:</strong> <span style="color: darkred;">The theme `<strong><?php echo esc_html($maybeInactiveAsset['name']); ?></strong>` seems to be inactive, thus any rules set are also inactive &amp; irrelevant, unless you re-activate the theme.</span></small>
                            <?php } ?>
                            <form method="post" action="" style="display: inline-block;">
                                <input type="hidden" name="wpacu_action" value="clear_all_rules" />
                                <input type="hidden" name="wpacu_handle" value="<?php echo esc_attr($handle); ?>" />
                                <input type="hidden" name="wpacu_asset_type" value="<?php echo esc_attr($assetType); ?>" />
                                <?php echo wp_nonce_field('wpacu_clear_all_rules', 'wpacu_clear_all_rules_nonce'); ?>
                                <script type="text/javascript">
                                    var wpacuClearAllRulesConfirmMsg_<?php echo $uniqueStr; ?> = '<?php echo esc_js($clearAllRulesConfirmMsg); ?>';
                                </script>
                                <button onclick="return confirm(wpacuClearAllRulesConfirmMsg_<?php echo $uniqueStr; ?>);" type="submit" class="button button-secondary"><span class="dashicons dashicons-trash" style="vertical-align: text-bottom;"></span> Clear all rules for this "orphaned" handle</button>
                            </form>
                        </div>
                        <?php
                    }
                } else {
                    // Extract base64 encoded data and decode it
                    if ($assetTypeS === 'style') {
                        $dataToCheck = 'data:text/css;base64,';
                        $viewDecodedText = __('View Decoded CSS', 'wp-asset-clean-up');
                    } else {
                        $dataToCheck = 'data:text/javascript;base64,';
                        $viewDecodedText = __('View Decoded JS', 'wp-asset-clean-up');
                    }

                    $base64Encoded = str_replace($dataToCheck, '', $src);
                    $decodedSource = base64_decode($base64Encoded);

                    $viewDecodedBase64Unique = 'wpacu-view-decoded-base64-format-' . $assetTypeS . '-' . sha1($src) . '-'. wp_unique_id();
                    ?>
                    <div>
                        <small>
                            <?php if ($assetTypeS === 'style') { ?>
                                * The "href" attribute is not pointing to an actual file and contains CSS code in Base64 format (it starts with "<em><?php echo $dataToCheck; ?></em>").
                            <?php } else { ?>
                                * The "src" attribute is not pointing to an actual file and contains JavaScript code in Base64 format (it starts with "<em><?php echo $dataToCheck; ?></em>").
                            <?php } ?>
                            <a data-wpacu-modal-target="<?php echo $viewDecodedBase64Unique; ?>-target" href="#<?php echo $viewDecodedBase64Unique; ?>"><?php echo $viewDecodedText; ?></a>
                        </small>
                    </div>
                    <div id="<?php echo $viewDecodedBase64Unique; ?>" class="wpacu-modal" style="padding-top: 100px;">
                        <div class="wpacu-modal-content">
                            <span class="wpacu-close">&times;</span>
                            <pre><code><?php echo $decodedSource; ?></code></pre>
                        </div>
                    </div>
                <?php }
            }


            // Any note?
            if (isset($handleData['notes']) && $handleData['notes']) {
                ?>
                <div><small><span class="dashicons dashicons-welcome-write-blog" style="vertical-align: middle;"></span> Note: <em><?php echo ucfirst(htmlspecialchars($handleData['notes'])); ?></em></small></div>
                <?php
            }
            ?>
        <?php
        }
	}

	/**
	 * @param $handleData
	 *
	 * @return array
	 */
	public static function renderHandleChangesOutput($handleData)
	{
		$handleChangesOutput  = array();
		$anyUnloadRule        = false; // default (turns to true if at least an unload rule is set)
		$anyLoadExceptionRule = false; // default (turns to true if any load exception rule is set)

		// It could turn to "true" IF the site-wide rule is turned ON and there are other unload rules on top of it (useless ones in this case)
		$hasRedundantUnloadRules = false;

		// Site-wide
		if (isset($handleData['unload_site_wide'])) {
			$handleChangesOutput['site_wide'] = '<span style="color: #cc0000;">Unloaded site-wide (everywhere)</span>';
			$anyUnloadRule = true;
		}

		// Bulk unload (on all posts, categories, etc.)
		if (isset($handleData['unload_bulk'])) {
			$handleChangesOutput['bulk'] = '';

			if (isset($handleData['unload_bulk']['post_type'])) {
				foreach ($handleData['unload_bulk']['post_type'] as $postType) {
                    $textToShow = 'Unloaded on all pages of <strong>' . $postType . '</strong> post type';

                    $handleChangesOutput['bulk'] .= ' <span style="color: #cc0000;">'. $textToShow . self::anyNoPostTypeEntriesMsg($postType).'</span>, ';

					$anyUnloadRule = true;
				}
			}

			$handleChangesOutput['bulk'] = rtrim($handleChangesOutput['bulk'], ', ');

			if (isset($handleChangesOutput['site_wide'])) {
				$handleChangesOutput['bulk'] .= ' * <em>unnecessary, as it\'s already unloaded site-wide</em>';
				$hasRedundantUnloadRules = true;
			}
		}

		if (isset($handleData['unload_on_home_page']) && $handleData['unload_on_home_page']) {
			$handleChangesOutput['on_home_page'] = '<span style="color: #cc0000;">Unloaded</span> on the <a target="_blank" href="'.Misc::getPageUrl(0).'">homepage</a>';

			if (isset($handleChangesOutput['site_wide'])) {
				$handleChangesOutput['on_home_page'] .= ' * <em>unnecessary, as it\'s already unloaded site-wide</em>';
				$hasRedundantUnloadRules = true;
			}

			$anyUnloadRule = true;
        }

		if (isset($handleData['load_exception_on_home_page']) && $handleData['load_exception_on_home_page']) {
			$handleChangesOutput['load_exception_on_home_page'] = '<span style="color: green;">Loaded (as an exception) on the <a target="_blank" href="'.Misc::getPageUrl(0).'">homepage</a></span>';
			$anyLoadExceptionRule = true;
		}

		// On this page: post, page, custom post type
		if (isset($handleData['unload_on_this_page']['post'])) {
			$handleChangesOutput['on_this_post'] = '<span style="color: #cc0000;">Unloaded in the following posts: ';

			$postsList = '';

			sort($handleData['unload_on_this_page']['post']);

			foreach ($handleData['unload_on_this_page']['post'] as $postId) {
				$postData   = get_post($postId);

                if (isset($postData->post_title, $postData->post_type)) {
	                $postTitle  = $postData->post_title;
	                $postType   = $postData->post_type;
                    $postStatus = $postData->post_status;

	                $postsList .= '<a title="Post Title: ' . esc_attr( $postTitle ) . ', Post Type: ' . esc_attr( $postType ) . '" class="wpacu-tooltip" target="_blank" href="' . esc_url( admin_url( 'post.php?post=' . $postId . '&action=edit' ) ) . '">' . $postId . '</a>';

                    if ($postStatus === 'trash') {
                        $postsList .= '&nbsp;<span style="color: #cc0000;" title="The post is in the \'Trash\'. This rule is not relevant if the post URL is not accessible." class="wpacu-tooltip dashicons dashicons-warning"></span>';
                    }

                    $postsList .= ', ';
                } else {
	                $postsList .= '<s class="wpacu-tooltip" title="N/A (post deleted)" style="color: #cc0000;">'.$postId.'</s>, ';
                }
			}

			$handleChangesOutput['on_this_post'] .= rtrim($postsList, ', ') . '</span>';

			if (isset($handleChangesOutput['site_wide'])) {
				$handleChangesOutput['on_this_post'] .= ' * <em>unnecessary, as it\'s already unloaded site-wide</em>';
				$hasRedundantUnloadRules = true;
            }

			$anyUnloadRule = true;
		}

		// Maybe it has other unload rules on top of the site-wide one (which covers everything)
		if ($hasRedundantUnloadRules) {
			$uniqueDelimiter = md5($handleData['handle'] . $handleData['asset_type']);
			$clearRedundantUnloadRulesConfirmMsg = sprintf(esc_js(__('This will clear all redundant (useless) unload rules for the `%s` CSS handle as there\'s already a site-wide rule applied.', 'wp-asset-clean-up')), $handleData['handle']) . '\n\n' . esc_js(__('Click `OK` to confirm the action', 'wp-asset-clean-up')).'!';
			$wpacuNonceField = wp_nonce_field('wpacu_clear_all_redundant_rules', 'wpacu_clear_all_redundant_rules_nonce');
			$clearRedundantUnloadRulesArea = <<<HTML
<form method="post" action="" style="display: inline-block;">
<input type="hidden" name="wpacu_action" value="clear_all_redundant_unload_rules" />
<input type="hidden" name="wpacu_handle" value="{$handleData['handle']}" />
<input type="hidden" name="wpacu_asset_type" value="{$handleData['asset_type']}" />
{$wpacuNonceField}
<script type="text/javascript">
var wpacuClearRedundantUnloadRulesConfirmMsg_{$uniqueDelimiter} = "{$clearRedundantUnloadRulesConfirmMsg}";
</script>
<button onclick="return confirm(wpacuClearRedundantUnloadRulesConfirmMsg_{$uniqueDelimiter});" type="submit" class="button button-secondary"><span class="dashicons dashicons-trash" style="vertical-align: text-bottom;"></span> Clear all redundant unload rules</button>
</form>
HTML;
			$handleChangesOutput['has_redundant_rules'] = $clearRedundantUnloadRulesArea;
		}

		if (isset($handleData['ignore_child']) && $handleData['ignore_child']) {
            $handleChangesOutput['ignore_child'] = 'If unloaded by any rule, ignore dependencies and keep its "children" loaded';
		}

		// Load exceptions? Per page, via RegEx, if user is logged-in
		if ( ! empty($handleData['load_exception_on_this_page']['post']) ) {
			$handleChangesOutput['load_exception_on_this_post'] = '<span style="color: green;">Loaded (as an exception) in the following posts: ';

			$postsList = '';

			sort($handleData['load_exception_on_this_page']['post']);

			foreach ($handleData['load_exception_on_this_page']['post'] as $postId) {
				$postData   = get_post($postId);

                if (isset($postData->post_title, $postData->post_type)) {
				    $postTitle  = $postData->post_title;
				    $postType   = $postData->post_type;
				    $postsList .= '<a title="Post Title: '.esc_attr($postTitle).', Post Type: '.esc_attr($postType).'" class="wpacu-tooltip" target="_blank" href="'.esc_url(admin_url('post.php?post='.$postId.'&action=edit')).'">'.$postId.'</a>, ';
                } else {
	                $postsList .= '<s class="wpacu-tooltip" title="N/A (post deleted)" style="color: #cc0000;">'.$postId.'</s>, ';
                }
			}

			$handleChangesOutput['load_exception_on_this_post'] .= rtrim($postsList, ', ') . '</span>';
			$anyLoadExceptionRule = true;
		}

		// e.g. Unloaded site-wide, but loaded on all 'product' (WooCommerce) pages
		if (isset($handleData['load_exception_post_type'])) {
			$handleChangesOutput['load_exception_post_type'] = '<span style="color: green;">Loaded (as an exception)</span> in all pages of the following post types: ';

			$postTypesList = '';

			sort($handleData['load_exception_post_type']);

			foreach ($handleData['load_exception_post_type'] as $postType) {
				$postTypesList .= '<strong>'.$postType.'</strong>'.self::anyNoPostTypeEntriesMsg($postType).', ';
			}

			$handleChangesOutput['load_exception_post_type'] .= rtrim($postTypesList, ', ');
			$anyLoadExceptionRule = true;
		}

		if (isset($handleData['load_it_logged_in']) && $handleData['load_it_logged_in']) {
			if ($anyLoadExceptionRule) {
				$textToShow = ' <strong>or</strong> <span style="color: green;">if the user is logged-in</span>';
			} else {
				$textToShow = '<span style="color: green;">Loaded (as an exception)</span> if the user is logged-in';
			}

			$handleChangesOutput['load_it_logged_in'] = $textToShow;
			$anyLoadExceptionRule = true;
		}

		// Since more than one load exception rule is set, merge them on the same row to save space and avoid duplicated words
		if (isset($handleChangesOutput['load_exception_on_this_post'], $handleChangesOutput['load_exception_regex'])) {
			$handleChangesOutput['load_exception_all'] = $handleChangesOutput['load_exception_on_this_post'] . $handleChangesOutput['load_exception_regex'];
			unset($handleChangesOutput['load_exception_on_this_post'], $handleChangesOutput['load_exception_regex']);
        }

		if (! $anyUnloadRule && $anyLoadExceptionRule) {
		    $handleType = ($handleData['asset_type'] === 'styles') ? 'CSS' : 'JS';
		    $clearLoadExceptionsConfirmMsg = sprintf( esc_attr(__('This will clear all load exceptions for the `%s` %s handle', 'wp-asset-clean-up')), $handleData['handle'], $handleType).'.' . '\n\n' . esc_js(__('Click `OK` to confirm the action', 'wp-asset-clean-up')).'!';
		    $wpacuNonceField = wp_nonce_field('wpacu_clear_load_exceptions', 'wpacu_clear_load_exceptions_nonce');

		    $uniqueDelimiter = md5($handleData['handle'].$handleData['asset_type']);
		    $clearLoadExceptionsArea = <<<HTML
<form method="post" action="" style="display: inline-block;">
<input type="hidden" name="wpacu_action" value="clear_load_exceptions" />
<input type="hidden" name="wpacu_handle" value="{$handleData['handle']}" />
<input type="hidden" name="wpacu_asset_type" value="{$handleData['asset_type']}" />
{$wpacuNonceField}
<script type="text/javascript">
var wpacuClearLoadExceptionsConfirmMsg_{$uniqueDelimiter} = '{$clearLoadExceptionsConfirmMsg}';
</script>
<button onclick="return confirm(wpacuClearLoadExceptionsConfirmMsg_{$uniqueDelimiter});" type="submit" class="button button-secondary clear-load-exceptions"><span class="dashicons dashicons-trash" style="vertical-align: text-bottom;"></span> Clear load exceptions for this handle</button>
</form>
HTML;
			$handleChangesOutput['load_exception_notice'] = '<div><em><small><strong>Note:</strong> Although a load exception rule is added, it is not relevant as there are no rules that would work together with it (e.g. unloaded site-wide, on all posts). This exception can be removed as the file is loaded anyway in all pages.</small></em>&nbsp;'.
                ' '.$clearLoadExceptionsArea.'</div><div style="clear:both;"></div>';
		}

		return $handleChangesOutput;
	}

	/**
	 * @param $postType
	 *
	 * @return string
	 */
	public static function anyNoPostTypeEntriesMsg($postType)
    {
	    $appendAfter = '';
	    $postTypeStatus = Misc::isValidPostType($postType);

	    if ( ! $postTypeStatus['has_records'] ) {
		    $appendAfter = ' <span style="color: #cc0000;" title="There are no posts in the database having the following post type: ' . $postType . '" class="wpacu-tooltip dashicons dashicons-warning"></span>';
	    }

        return $appendAfter;
    }
}
