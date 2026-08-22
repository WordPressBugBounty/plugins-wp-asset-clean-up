<?php
namespace WpAssetCleanUp\Admin;

/**
 * Class PluginsManager
 * @package WpAssetCleanUp
 */
class PluginsManagerAdmin
{
    /**
     * @var array
     */
    public $data = array();

    /**
     * Make sure there is a status for the rule, otherwise it's likely set to "Load it",
     * thus the rule wouldn't count
     *
     * @param bool $checkIfPluginIsActive
     * @param bool $getRulesForAllLocations
     *
     * @return array
     */
    public static function getPluginRulesFiltered($checkIfPluginIsActive = true, $getRulesForAllLocations = false)
    {
        $pluginsWithRules = array();

        $pluginsAllDbRules = self::getAllRules($getRulesForAllLocations);

        // Are there any load exceptions / unload RegExes?
        if ( ! empty($pluginsAllDbRules)) {
            foreach ($pluginsAllDbRules as $locationKey => $pluginsRules) {
                foreach ($pluginsRules as $pluginPath => $pluginData) {
                    // Only the rules for the active plugins are retrieved
                    if ($checkIfPluginIsActive && ! wpacuIsPluginActive($pluginPath)) {
                        continue;
                    }

                    // Older edits can leave load-exception payloads behind after the
                    // parent unload status is removed. Derive a display-only status
                    // from those payloads so Overview can expose and clear them.
                    $inactiveRuleKeys = array();
                    $pluginStatus = self::getEffectiveRuleStatus($pluginData, $inactiveRuleKeys);

                    if ( ! empty($pluginStatus)) {
                        $pluginData['status'] = $pluginStatus;
                        $pluginData['_wpacu_overview_inactive_rule_keys'] = $inactiveRuleKeys;
                        $pluginsWithRules[$locationKey][$pluginPath] = $pluginData;
                    }
                }
            }

            }

        return $pluginsWithRules;
    }

    /**
     * Return stored statuses plus rules that still have a meaningful payload.
     *
     * @param array $pluginData
     * @param array $inactiveRuleKeys Populated with payloads whose checkbox/status is inactive.
     * @return array
     */
    private static function getEffectiveRuleStatus($pluginData, &$inactiveRuleKeys = array())
    {
        $inactiveRuleKeys = array();

        if ( ! is_array($pluginData)) {
            return array();
        }

        $status = isset($pluginData['status']) ? (array)$pluginData['status'] : array();
        $status = array_values(array_filter(array_map('strval', $status)));
        $storedStatus = array_fill_keys($status, true);

        foreach ($pluginData as $ruleKey => $ruleData) {
            if ($ruleKey === 'status'
                || ! is_string($ruleKey)
                || ! preg_match('/^(?:unload|load)_/', $ruleKey)
                || ! is_array($ruleData)) {
                continue;
            }

            $hasValues = ! empty($ruleData['values']) && is_array($ruleData['values']);
            $hasValue  = isset($ruleData['value']) && trim((string)$ruleData['value']) !== '';
            $isEnabled = ! empty($ruleData['enable']);

            if ($hasValues || $hasValue || $isEnabled) {
                $status[] = $ruleKey;

                if ( ! isset($storedStatus[$ruleKey]) && ! $isEnabled) {
                    $inactiveRuleKeys[] = $ruleKey;
                }
            }
        }

        $inactiveRuleKeys = array_values(array_unique($inactiveRuleKeys));

        return array_values(array_unique($status));
    }

    /**
     * @param false $fetchAllLocations (if set to true, it will return the rules for both the frontend and the backend
     *
     * @return array
     */
    public static function getAllRules($fetchAllLocations = false)
    {
        $pluginsRulesDbListJson = get_option(WPACU_PLUGIN_ID . '_global_data');

        if ($pluginsRulesDbListJson) {
            $pluginsRulesDbList = wpacuJsonDecodeToArray($pluginsRulesDbListJson);

            // Issues with decoding the JSON file? Return an empty list
            if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
                return array();
            }

            // 1) For listing them in "Overview"
            if ($fetchAllLocations) {
                $rulesList = array();

                if ( ! empty($pluginsRulesDbList['plugins'])) {
                    $rulesList['plugins'] = $pluginsRulesDbList['plugins'];
                }

                if ( ! empty($pluginsRulesDbList['plugins_dash'])) {
                    $rulesList['plugins_dash'] = $pluginsRulesDbList['plugins_dash'];
                }

                return $rulesList;
            }

            // 2) For listing them within "Plugins Manager" -> "In Frontend View" or "In the Dashboard" when the admin is managing the rules
            $wpacuSubPage = (isset($_GET['wpacu_sub_page']) && $_GET['wpacu_sub_page']) ? $_GET['wpacu_sub_page'] : 'manage_plugins_front';

            $mainGlobalKey = ($wpacuSubPage === 'manage_plugins_front') ? 'plugins' : 'plugins_dash';

            if ( ! empty($pluginsRulesDbList[$mainGlobalKey])) {
                return $pluginsRulesDbList[$mainGlobalKey];
            }
        }

        return array();
    }

    /**
     * Returns the archive page types available for Plugins Manager rules.
     *
     * The flat list is used in places such as the Overview page. When the
     * grouped list is requested, optgroup-ready data is returned only if at
     * least one public custom post type archive exists. This keeps the legacy
     * three-option dropdown unchanged on websites without CPT archives.
     *
     * @param bool $groupByArchiveType
     *
     * @return array
     */
    public static function generateArchivePageTypesList($groupByArchiveType = false)
    {
        static $cachedLists = array();

        $blogId   = function_exists('get_current_blog_id') ? (int)get_current_blog_id() : 0;
        $cacheKey = $blogId . ':' . ($groupByArchiveType ? 'grouped' : 'flat');
        $flatCacheKey = $blogId . ':flat';
        $groupedCacheKey = $blogId . ':grouped';

        if (array_key_exists($cacheKey, $cachedLists)) {
            return $cachedLists[$cacheKey];
        }

        $wordpressDefaultArchivePages = array(
            'search' => 'Search',
            'author' => 'Author',
            'date'   => 'Date'
        );

        $customPostTypeArchivePages = array();

        foreach (AssetsManagerAdmin::getCustomPostTypeArchives() as $postTypeKey => $archiveData) {
            $archiveLabel = isset($archiveData['label']) && $archiveData['label'] !== ''
                ? $archiveData['label']
                : $postTypeKey;

            $customPostTypeArchivePages['custom_post_type_archive_' . $postTypeKey] =
                $archiveLabel . ' (' . $postTypeKey . ')';
        }

        $flatList = array_merge(
            $wordpressDefaultArchivePages,
            $customPostTypeArchivePages
        );

        $cachedLists[$flatCacheKey] = $flatList;

        if ( ! $groupByArchiveType || empty($customPostTypeArchivePages)) {
            $cachedLists[$cacheKey] = $flatList;
            return $cachedLists[$cacheKey];
        }

        $cachedLists[$groupedCacheKey] = array(
            'WordPress default archive pages' => $wordpressDefaultArchivePages,
            'Custom Post Type archive pages'  => $customPostTypeArchivePages
        );

        return $cachedLists[$groupedCacheKey];
    }

    /**
	 *
	 */
	public function page()
    {
    	// Get active plugins and their basic information
	    $this->data['active_plugins'] = self::getActivePlugins();
	    $this->data['plugins_icons']  = MiscAdmin::getAllActivePluginsIcons();

        $wpacuSubPage = (isset($_GET['wpacu_sub_page']) && $_GET['wpacu_sub_page']) ? $_GET['wpacu_sub_page'] : 'manage_plugins_front';
	    $this->data['wpacu_sub_page'] = $wpacuSubPage;

        $this->data = apply_filters('wpacu_internal_plugins_manager_admin_page_data', $this->data);

        // Legacy/fallback filter, in case any developer already used it.
        $this->data = apply_filters('wpacu_plugins_manager_admin_page_data', $this->data);

	    MainAdmin::instance()->parseTemplate('admin-page-plugins-manager', $this->data, true);
    }

	/**
	 * @return array
	 */
	public static function getActivePlugins()
	{
		$activePluginsFinal = array();

		// Get active plugins and their basic information
        $activePlugins = wp_get_active_and_valid_plugins();

        // Also check any network activated plugins in case we're dealing with a MultiSite setup
		if ( is_multisite() ) {
			$activeNetworkPlugins = wp_get_active_network_plugins();

			if ( ! empty( $activeNetworkPlugins ) ) {
				foreach ( $activeNetworkPlugins as $activeNetworkPlugin ) {
					$activePlugins[] = $activeNetworkPlugin;
				}
			}
		}

		$activePlugins = array_unique($activePlugins);

		foreach ($activePlugins as $pluginPath) {
			// Skip Asset CleanUp as it's obviously needed for the functionality
			if (strpos($pluginPath, 'wp-asset-clean-up') !== false) {
				continue;
			}

			$networkActivated = isset($activeNetworkPlugins) && in_array($pluginPath, $activeNetworkPlugins);

			$pluginRelPath = trim(str_replace(WP_PLUGIN_DIR, '', $pluginPath), '/');

			$pluginData = get_plugin_data($pluginPath);

			$activePluginsFinal[] = array(
                'title'             => $pluginData['Name'],
                'path'              => $pluginRelPath,
                'network_activated' => $networkActivated
			);
		}

        if ( ! empty($activePluginsFinal) ) {
	        usort( $activePluginsFinal, static function( $a, $b ) {
		        return strcmp( $a['title'], $b['title'] );
	        } );
        }

		return $activePluginsFinal;
	}
}
