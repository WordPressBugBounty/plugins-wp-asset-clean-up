<?php
/** @noinspection OffsetOperationsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\Overview;
use WpAssetCleanUp\Admin\SettingsAdmin;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

/**
 * Class Maintenance
 * @package WpAssetCleanUp
 */
class Maintenance
{
	/**
	 * Maintenance constructor.
	 */
	public function __construct()
	{
		add_action('wpacu_daily_scheduled_events',     array($this, 'triggerDailyScheduleEvents'));
        add_action('wpacu_daily_scheduled_events_two', array($this, 'triggerDailyScheduleEventsTwo'));

        add_action('init', array($this, 'scheduleTriggerForDebugByAdmin'));
		add_action('init', array($this, 'scheduleDailyEvents'));

		if ( is_admin() && Menu::isPluginPage() ) {
			add_action('admin_init', static function() {
				Maintenance::cleanUnusedAssetsFromInfoArea();
				Maintenance::combineNewOptionUpdate(); // Since v1.1.7.3 (Pro) & v1.3.6.4 (Lite)

				OptimizeCommon::limitAlreadyMarkedAsMinified(); // Since v1.1.7.4 (Pro) & v1.3.6.6 (Lite)
			});
		}

		}

	/**
	 * Schedule events
	 *
	 * @access private
	 * @since 1.6
	 * @return void
	 */
	public function scheduleDailyEvents()
	{
		// Daily events
        $hookOne = 'wpacu_daily_scheduled_events';

		if ( ! wp_next_scheduled($hookOne) ) {
			wp_schedule_event($this->getNextRun('03:00'), 'daily', $hookOne);
		}

        $hookTwo = 'wpacu_daily_scheduled_events_two';

        if ( ! wp_next_scheduled($hookTwo) ) {
            wp_schedule_event($this->getNextRun('04:00'), 'daily', $hookTwo);
        }
	}

    /**
     * Returns a timestamp
     *
     * @param $triggerTime
     *
     * @return float|int|string
     * @throws \Exception
     */
    public function getNextRun($triggerTime)
    {
        $nextRun = $this->wpLocalTimeToTimestamp($triggerTime); // today at {$triggerTime} in WP local time

        if ($nextRun <= current_time('timestamp')) {
            $nextRun = $this->wpLocalTimeToTimestamp($triggerTime, 'tomorrow');
        }

        return $nextRun;
    }

    /**
     * @param $timeString
     * @param $day
     *
     * @return float|int|string
     * @throws \Exception
     */
    public function wpLocalTimeToTimestamp($timeString = '03:00', $day = 'today')
    {
        if (class_exists('\DateTime') && function_exists('wp_timezone')) {
            // Get the WordPress timezone (string like 'Europe/Bucharest', 'UTC', etc.)
            $timezone = wp_timezone(); // WP 5.3+

            // Create the full time string like 'today 03:00' or 'tomorrow 03:00'
            $dateTimeStr = "$day $timeString";

            // Create the DateTime object with WP timezone
            $dt = new \DateTime($dateTimeStr, $timezone);

            // Return UNIX timestamp
            return $dt->getTimestamp();
        } else {
            $timestamp = strtotime("$day $timeString");
            $offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

            return $timestamp - date('Z') + $offset;
        }
    }

	/**
	 * Trigger scheduled events
	 *
	 * @return void
	 */
	public function scheduleTriggerForDebugByAdmin()
	{
		// Debugging purposes: trigger directly the code meant to be scheduled
		if (Menu::userCanAccessPlugin()) {
			if (isset($_GET['wpacu_toggle_inline_code_to_combined_assets'])) {
				self::updateAppendOrNotInlineCodeToCombinedAssets(true);
			}

			if (isset($_GET['wpacu_clear_cache_conditionally'])) {
				self::updateAppendOrNotInlineCodeToCombinedAssets(true);
			}

            if ( is_user_logged_in() ) {
                Maintenance::combineNewOptionUpdate(); // Since v1.1.7.3 (Pro) & v1.3.6.4 (Lite)
            }
		}
	}

	/**
	 *
	 */
	public static function triggerDailyScheduleEvents()
	{
		self::updateAppendOrNotInlineCodeToCombinedAssets();
		self::clearCacheConditionally();

        // Fix v1.2.5.6 (duplicates in "usermeta" table)
        self::removeAnyDuplicateMetaKeysFromUsersTable();

		}

    /**
     *
     */
    public function triggerDailyScheduleEventsTwo()
    {
        // MultiSite update: from v1.2.6.8 (Pro) and v1.4.0.4 (Lite)
        $this->lazyCleanCacheOldSubdirsBeforeMultiSiteUpdate();
    }

    /**
     * @param $maxFilesPerRun
     *
     * @return int
     */
    public function lazyCleanCacheOldSubdirsBeforeMultiSiteUpdate($maxFilesPerRun = 200)
    {
        $rootDir = WP_CONTENT_DIR . OptimizeCommon::getRelPathPluginCacheDir(false);

        if ( ! is_dir($rootDir) ) {
            return 0;
        }

        $maxAgeInSeconds = 30 * 24 * 60 * 60; // 30 days

        $currentTime = time();

        $existingBlogIds = array();

        $deletedCount = 0;
        $dirIterator = new \FilesystemIterator($rootDir, \FilesystemIterator::SKIP_DOTS);

        $allAsItShouldBe = true; // default

        // Check if all the root files are in place, and if they are, do not continue, since there are no leftovers
        if ( is_multisite() ) {
            global $wpdb;

            $existingBlogIds = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs} WHERE archived = '0' AND deleted = '0'");

            foreach ($dirIterator as $item) {
                if ( $item->isFile() && ! in_array($item->getBasename(), array('.htaccess', 'index.php')) ) {
                    $allAsItShouldBe = false;
                    break;
                }

                if ($item->isDir()) {
                    if ( ! ctype_digit($item->getBasename()) ) {
                        // e.g. old ones such as "_storage", "css", "js"
                        $allAsItShouldBe = false;
                        break;
                    } elseif ( ctype_digit($item->getBasename()) ) {
                        $dirName = (int)$item->getBasename();

                        // A site ID that was likely deleted and it has leftovers
                        if ( ! in_array($dirName, $existingBlogIds) ) {
                            $allAsItShouldBe = false;
                            break;
                        }
                    }
                }

                }
        } else {
            // Single site (most cases)
            $expected = array('.htaccess' => 'file', 'index.php' => 'file', 'one' => 'dir');
            $found    = array();

            $allAsItShouldBe = true;
            $dirIterator     = new \FilesystemIterator($rootDir, \FilesystemIterator::SKIP_DOTS);

            foreach ($dirIterator as $item) {
                $name = $item->getBasename();

                if ( ! isset($expected[$name]) ) {
                    $allAsItShouldBe = false;
                    break;
                }

                $typeOk = ($expected[$name] === 'file' && $item->isFile()) ||
                          ($expected[$name] === 'dir'  && $item->isDir());

                if ( ! $typeOk ) {
                    $allAsItShouldBe = false;
                    break;
                }

                $found[$name] = true;
            }

            if ( $allAsItShouldBe && count($found) !== count($expected) ) {
                $allAsItShouldBe = false;
            }
        }

        // All in its place without any leftovers? Stop here!
        if ( $allAsItShouldBe ) {
            return 0;
        }

        // Root old files (if any left)
        foreach ($dirIterator as $item) {
            if ( ! $item->isFile() ) {
                continue;
            }

            if ( in_array($item->getBasename(), array('.htaccess', 'index.php')) ) {
                continue; // leave these ones
            }

            // Root files detected
            if ($this->maybeDeleteUselessFile($item, $currentTime, $maxAgeInSeconds)) {
                $deletedCount++;
            }

            if ($deletedCount >= $maxFilesPerRun) {
                return $deletedCount;
            }
        }

        // Sub-directories (irrelevant old ones)
        foreach ($dirIterator as $item) {
            if ( ! $item->isDir() ) {
                continue;
            }

            $subDirPath = $item->getPathname();

            // Sub-directory detected (e.g. "css", "js", "_storage")
            $dirName = $item->getBasename();

            if (is_multisite() && ctype_digit($dirName)) {
                $dirInt = (int)$dirName;

                // If it's an existing blog ID or the main site ID, skip it
                if (in_array($dirInt, $existingBlogIds)) {
                    continue;
                }
            } elseif ($dirName === OptimizeCommon::$cacheDirNameSingleNoMultiSite) {
                continue;
            }

            if ( ! is_dir($subDirPath) ) {
                continue; // perhaps deleted from the code below
            }

            if (iterator_count(new \FilesystemIterator($item, \FilesystemIterator::SKIP_DOTS)) === 0) {
                $this->maybeDeleteUselessDir($item, $currentTime, $maxAgeInSeconds);
            }

            try {
                $fileOrSubdirIterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($subDirPath,
                        \FilesystemIterator::SKIP_DOTS |
                        \FilesystemIterator::CURRENT_AS_FILEINFO |
                        \FilesystemIterator::KEY_AS_PATHNAME
                    ),
                    \RecursiveIteratorIterator::CHILD_FIRST // <<< Important difference!
                );

                foreach ($fileOrSubdirIterator as $fileOrDirInfo) {
                    if ($fileOrDirInfo->isDir()) {
                        // Directory: delete it (only after contents are already processed)
                        $this->maybeDeleteUselessDir($fileOrDirInfo, $currentTime, $maxAgeInSeconds);
                    } else {
                        // File: delete if old enough
                        if ($this->maybeDeleteUselessFile($fileOrDirInfo, $currentTime, $maxAgeInSeconds)) {
                            $deletedCount++;
                        }

                        if ($deletedCount >= $maxFilesPerRun) {
                            return $deletedCount;
                        }
                    }
                }
            } catch (\Exception $e) {}
        }

        return $deletedCount;
    }

    /**
     * @param $dirInfo
     * @param $currentTime
     * @param $maxAgeInSeconds
     *
     * @return false|void
     */
    public function maybeDeleteUselessDir($dirInfo, $currentTime, $maxAgeInSeconds)
    {
        if ( ! $dirInfo->isFile() ) {
            return false;
        }

        $fileMTime = $dirInfo->getMTime();

        if (($currentTime - $fileMTime) >= $maxAgeInSeconds) {
            @rmdir($dirInfo->getPathname());
        }
    }

    /**
     * Deletes a file if it's older than the given age.
     *
     * @param \SplFileInfo $fileInfo
     * @param int $currentTime
     * @param int $maxAgeInSeconds
     * @return bool True if the file was deleted, false otherwise
     */
    public function maybeDeleteUselessFile($fileInfo, $currentTime, $maxAgeInSeconds)
    {
        if ( ! $fileInfo->isFile() ) {
            return false;
        }

        $fileMTime = $fileInfo->getMTime();

        if (($currentTime - $fileMTime) >= $maxAgeInSeconds) {
            $filePath = $fileInfo->getPathname();

            @unlink($filePath);

            return true;
        }

        return false;
    }

    /**
     * @return void
     */
    public static function removeAnyDuplicateMetaKeysFromUsersTable()
    {
        global $wpdb;

        $pluginId = WPACU_PLUGIN_ID;

        $sqlQuery = <<<SQL
SELECT umeta_id, user_id, COUNT(CONCAT(user_id, meta_key)) as total_count
FROM `{$wpdb->usermeta}`
WHERE meta_key='{$pluginId}_user_chosen_for_access_to_assets_manager'
GROUP BY CONCAT(user_id, meta_key)
HAVING total_count > 1;
SQL;
        $rows = $wpdb->get_results($sqlQuery, ARRAY_A);

        if ( ! empty($rows) ) {
            foreach ($rows as $row) {
                $deleteSqlQuery = <<<SQL
DELETE FROM `{$wpdb->usermeta}`
WHERE umeta_id != '%d' AND user_id='%d' AND meta_key='%s'
SQL;

                $deleteSqlQueryPrepared = $wpdb->prepare(
                    $deleteSqlQuery,
                    (int)$row['umeta_id'],
                    (int)$row['user_id'],
                    $pluginId . '_user_chosen_for_access_to_assets_manager'
                );

                $wpdb->query($deleteSqlQueryPrepared);
            }
        }
    }

	/**
	 * @param false $isDebug
	 */
	public static function updateAppendOrNotInlineCodeToCombinedAssets($isDebug = false)
	{
		// WordPress Version below 5.5? Skip appending CSS/JS code to the combine CSS/JS list
		if ( ! Misc::isWpVersionAtLeast('5.5') ) {
			$settingsAdminClass = new SettingsAdmin();
			$optionsToUpdate = array(
				'_combine_loaded_css_append_handle_extra' => '',
				'_combine_loaded_js_append_handle_extra'  => ''
			);
            $settingsAdminClass->updateOption(array_keys($optionsToUpdate), array_values($optionsToUpdate));

			if ($isDebug) {
				echo 'The WordPress version is below 5.5, thus there is no appending of the inline CSS/JS code to the combine CSS/JS list';
			}
		} else {
			// Check if there are too many .css /.js combined files in the caching directory and change settings
			// to prevent the appending of the inline CSS/JS code that is likely the culprit of so many files
			$settingsClass = new Settings();
			$settings      = $settingsClass->getAll( true );

            $settingsAdminClass = new SettingsAdmin();
            $settingsAdminClass::toggleAppendInlineAssocCodeHiddenSettings( $settings, true, $isDebug );
		}

		if ($isDebug) {
			exit();
		}
	}

	/**
	 * @param false $isDebug
	 */
	public static function clearCacheConditionally($isDebug = false)
	{
		// Clear caching if it wasn't cleared in the past 24 hours (e.g. the admin hasn't used the plugin for a while)
		$wpacuLastClearCache = get_transient(WPACU_PLUGIN_ID . '_last_clear_cache');

		if ($isDebug) {
			echo 'Cache cleared last time: '.$wpacuLastClearCache.'<br />';
		}

		if ($wpacuLastClearCache && (strtotime( '-1 days' ) > $wpacuLastClearCache)) {
			OptimizeCommon::clearCache();

			if ($isDebug) {
				echo 'The cache was just cleared as it was not cleared in the past 24 hours.<br />';
			}
		}
	}

	/**
	 *
	 */
	public static function cleanUnusedAssetsFromInfoArea()
	{
		$allAssetsWithAtLeastOneRule = Overview::handlesWithAtLeastOneRule();

		if (empty($allAssetsWithAtLeastOneRule)) {
			return;
		}

		// Stored in the "assets_info" key from "wpassetcleanup_global_data" option name (from `{$wpdb->prefix}options` table)
		$allAssetsFromInfoArea = Main::getHandlesInfo();

		$handlesToClearFromInfo = array('styles' => array(), 'scripts' => array());

		foreach (array('styles', 'scripts') as $assetType) {
			if ( ! empty( $allAssetsFromInfoArea[$assetType] ) ) {
				foreach ( array_keys( $allAssetsFromInfoArea[$assetType] ) as $assetHandle ) {
					if ( ! isset($allAssetsWithAtLeastOneRule[$assetType][$assetHandle]) ) { // not found in $allAssetsWithAtLeastOneRule? Add it to the clear list
						$handlesToClearFromInfo[$assetType][] = $assetHandle;
					}
				}
			}
		}

		if (! empty($handlesToClearFromInfo['styles']) || ! empty($handlesToClearFromInfo['scripts'])) {
			self::removeHandlesInfoFromGlobalDataOption($handlesToClearFromInfo);
		}
	}

	/**
	 *
	 */
	public static function combineNewOptionUpdate()
	{
		$settingsClass = new Settings();
		$pluginSettings = $settingsClass->getAll();

		if ( ($pluginSettings['combine_loaded_css'] === 'for_admin' ||
		     (isset($pluginSettings['combine_loaded_css_for_admin_only']) && $pluginSettings['combine_loaded_css_for_admin_only'] == 1) )
		    && Menu::userCanAccessPlugin() ) {
            $settingsAdminClass = new SettingsAdmin();
            $settingsAdminClass->updateOption('combine_loaded_css', '');
            $settingsAdminClass->updateOption('combine_loaded_css_for_admin_only', '');
		}

		if ( ($pluginSettings['combine_loaded_js'] === 'for_admin' ||
		     (isset($pluginSettings['combine_loaded_js_for_admin_only']) && $pluginSettings['combine_loaded_js_for_admin_only'] == 1) )
		    && Menu::userCanAccessPlugin() ) {
            $settingsAdminClass = new SettingsAdmin();
            $settingsAdminClass->updateOption('combine_loaded_js', '');
            $settingsAdminClass->updateOption('combine_loaded_js_for_admin_only', '');
		}
	}

	/**
	 * @param $handlesToClearFromInfo
	 */
	public static function removeHandlesInfoFromGlobalDataOption($handlesToClearFromInfo)
	{
		$optionToUpdate = WPACU_PLUGIN_ID . '_global_data';
		$globalKey = 'assets_info';

		$existingListEmpty = array('styles' => array($globalKey => array()), 'scripts' => array($globalKey => array()));
		$existingListJson = get_option($optionToUpdate);

		$existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
		$existingList = $existingListData['list'];

		// $assetType could be 'styles' or 'scripts'
		foreach ($handlesToClearFromInfo as $assetType => $handles) {
			foreach ($handles as $handle) {
				if ( isset( $existingList[ $assetType ][ $globalKey ][ $handle ] ) ) {
					unset( $existingList[ $assetType ][ $globalKey ][ $handle ] );
				}
			}
		}

		Misc::addUpdateOption($optionToUpdate, wp_json_encode(Misc::filterList($existingList)));
	}

	/**
	 * When is this needed? Sometimes, you have rules such as "Unload site-wide (everywhere)" and load exceptions associated to it
	 * However, if you remove the unload rule, then the load exceptions associated with it will become useless as they worth together
	 * If no unload rule is added, the file is loaded anyway, it doesn't need any load exception obviously
	 *
	 * @param $assetHandle
	 * @param $assetType | it belongs to "styles" or "scripts"
	 */
	public static function removeAllLoadExceptionsFor($assetHandle, $assetType)
	{
		/*
		 * Any in the front-page?
		 */
		$wpacuFrontPageLoadExceptions = get_option(WPACU_PLUGIN_ID . '_front_page_load_exceptions');

		if ($wpacuFrontPageLoadExceptions) {
			$wpacuFrontPageLoadExceptionsArray = @json_decode( $wpacuFrontPageLoadExceptions, ARRAY_A );

			$targetArray = isset($wpacuFrontPageLoadExceptionsArray[$assetType]) && is_array($wpacuFrontPageLoadExceptionsArray[$assetType])
				? $wpacuFrontPageLoadExceptionsArray[$assetType]
				: array();

			if ( in_array($assetHandle, $targetArray) ) {
				$targetKey = array_search($assetHandle, $targetArray);
				unset($wpacuFrontPageLoadExceptionsArray[$assetType][$targetKey]); // clear the exception

				Misc::addUpdateOption(
					WPACU_PLUGIN_ID . '_front_page_load_exceptions',
					wp_json_encode(Misc::filterList($wpacuFrontPageLoadExceptionsArray))
				);
			}
		}

		/*
		 * Any for all pages of a certain post type? (e.g., in all WooCommerce "product" pages)
		 */
		$wpacuPostTypeLoadExceptions = get_option(WPACU_PLUGIN_ID . '_post_type_load_exceptions');

		if ($wpacuPostTypeLoadExceptions) {
			$wpacuPostTypeLoadExceptionsArray = @json_decode( $wpacuPostTypeLoadExceptions, ARRAY_A );

			if (! empty($wpacuPostTypeLoadExceptionsArray)) {
				foreach ($wpacuPostTypeLoadExceptionsArray as $dbPostType => $dbList) {
					foreach ($dbList as $dbAssetType => $dbValues) {
						if ($assetType === $dbAssetType && array_key_exists( $assetHandle, $dbValues ) ) {
							unset($wpacuPostTypeLoadExceptionsArray[$dbPostType][$dbAssetType][$assetHandle]);
						}
					}
				}
			}

			Misc::addUpdateOption(
				WPACU_PLUGIN_ID . '_post_type_load_exceptions',
				wp_json_encode(Misc::filterList($wpacuPostTypeLoadExceptionsArray))
			);
		}

		global $wpdb;

		$wpacuPluginId = WPACU_PLUGIN_ID;

		/*
		 * Any for posts (any kind), pages, taxonomies or users?
		 */
		foreach (array($wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta) as $tableName) {
			$wpacuGetValuesQuery = <<<SQL
SELECT * FROM `{$tableName}` WHERE meta_key='_{$wpacuPluginId}_load_exceptions'
SQL;
			$wpacuMetaData = $wpdb->get_results( $wpacuGetValuesQuery, ARRAY_A );

			foreach ( $wpacuMetaData as $wpacuValues ) {
				$decodedValues = @json_decode( $wpacuValues['meta_value'], ARRAY_A );

				if ( empty( $decodedValues ) ) {
					continue;
				}

				if ( isset( $decodedValues[ $assetType ] ) &&
				     is_array( $decodedValues[ $assetType ] ) &&
				     in_array( $assetHandle, $decodedValues[ $assetType ] ) ) {
					$targetKey = array_search( $assetHandle, $decodedValues[ $assetType ] );
					unset( $decodedValues[ $assetType ][ $targetKey ] ); // clear the exception
				} else {
					continue; // no point in re-updating the database with the same values
				}

				$newList = wp_json_encode(Misc::filterList($decodedValues));

				if ( $tableName === $wpdb->postmeta ) {
					update_post_meta($wpacuValues['post_id'], '_'.$wpacuPluginId.'_load_exceptions', $newList);
				} elseif ( $tableName === $wpdb->termmeta ) {
					update_term_meta($wpacuValues['term_id'], '_'.$wpacuPluginId.'_load_exceptions', $newList);
				} else {
					update_user_meta($wpacuValues['user_id'], '_'.$wpacuPluginId.'_load_exceptions', $newList);
				}
			}
		}

		/*
		 * Any load exceptions for 404, search, date pages?
		 */
		$wpacuExtrasLoadExceptions = get_option(WPACU_PLUGIN_ID . '_extras_load_exceptions');

		if ($wpacuExtrasLoadExceptions) {
			$wpacuExtrasLoadExceptionsArray = @json_decode( $wpacuExtrasLoadExceptions, ARRAY_A );

			// $forKey could be '404', 'search', 'date', etc.
			foreach ($wpacuExtrasLoadExceptionsArray as $forKey => $values) {
				$targetArray = isset( $values[ $assetType ] ) && is_array( $values[ $assetType ] ) ? $values[ $assetType ] : array();

				if ( in_array( $assetHandle, $targetArray ) ) {
					$targetKey = array_search( $assetHandle, $targetArray );
					unset( $wpacuExtrasLoadExceptionsArray[ $forKey ][ $assetType ][ $targetKey ] ); // clear the exception

					Misc::addUpdateOption(
						WPACU_PLUGIN_ID . '_extras_load_exceptions',
						wp_json_encode( Misc::filterList( $wpacuExtrasLoadExceptionsArray ) )
					);
				}
			}
		}

		/*
		 * Any (RegEx / Logged-in User) load exceptions?
		*/
		$globalKeys = array('load_regex', 'load_it_logged_in');
        $dbList = wpacuGetGlobalData();

		foreach ($globalKeys as $globalKey) {
            $targetArray = isset( $dbList[ $assetType ][ $globalKey ] ) &&
                           is_array( $dbList[ $assetType ][ $globalKey ] )
                ? $dbList[ $assetType ][ $globalKey ] : array();

            if ( array_key_exists( $assetHandle, $targetArray ) ) {
                unset( $dbList[ $assetType ][ $globalKey ][ $assetHandle ] ); // clear the exception

                Misc::addUpdateOption(
                    WPACU_PLUGIN_ID . '_global_data',
                    wp_json_encode( Misc::filterList( $dbList ) )
                );
            }
		}
	}

	/**
	 * @param $assetHandle
	 * @param $assetType
	 */
	public static function removeAllRulesFor($assetHandle, $assetType)
	{
		// Clear any load exception rules
		self::removeAllLoadExceptionsFor($assetHandle, $assetType);

		/*
		 * Table: WPACU_PLUGIN_ID . '_global_data'
		 * Global (Site-wide) Rules: Preloading, Position changing, Unload via RegEx, etc.
		 */
		$wpacuGlobalDataArray = wpacuGetGlobalData();

		if ( ! empty( $wpacuGlobalDataArray[ $assetType ] ) && is_array( $wpacuGlobalDataArray[ $assetType ] ) ) {
            foreach ( $wpacuGlobalDataArray[ $assetType ] as $dataType => $dataTypeData ) {
                if ( ! is_array( $dataTypeData ) ) {
                    continue;
                }

                if ( array_key_exists( $assetHandle, $dataTypeData ) ) {
                    unset( $wpacuGlobalDataArray[ $assetType ][ $dataType ][ $assetHandle ] );
                }

                }
        }

		Misc::addUpdateOption(
			WPACU_PLUGIN_ID . '_global_data',
			wp_json_encode( Misc::filterList( $wpacuGlobalDataArray ) )
		);

		/*
		 * Table: WPACU_PLUGIN_ID . '_global_unload'
		 * Unload Site-Wide (Everywhere)
		 */
		$wpacuGlobalUnloadData = get_option(WPACU_PLUGIN_ID . '_global_unload');
		$wpacuGlobalUnloadDataArray = @json_decode($wpacuGlobalUnloadData, ARRAY_A);

		if ( ! empty($wpacuGlobalUnloadDataArray[$assetType]) && in_array($assetHandle, $wpacuGlobalUnloadDataArray[$assetType]) ) {
			$targetKey = array_search($assetHandle, $wpacuGlobalUnloadDataArray[$assetType]);
			unset($wpacuGlobalUnloadDataArray[$assetType][$targetKey]);

			Misc::addUpdateOption(
				WPACU_PLUGIN_ID . '_global_unload',
				wp_json_encode( Misc::filterList( $wpacuGlobalUnloadDataArray ) )
			);
		}

		/*
		 * Table: WPACU_PLUGIN_ID . '_bulk_unload'
		 * Bulk Unload
		 */
		$wpacuBulkUnloadData = get_option(WPACU_PLUGIN_ID . '_bulk_unload');
		$wpacuBulkUnloadDataArray = @json_decode($wpacuBulkUnloadData, ARRAY_A);

		if ( ! empty($wpacuBulkUnloadDataArray[$assetType]) ) {
			foreach ($wpacuBulkUnloadDataArray[$assetType] as $unloadBulkType => $unloadBulkValues) {
				// $unloadBulkType could be 'post_type', 'date', '404', 'taxonomy', 'search', 'custom_post_type_archive_[custom_post_type]'
				if ($unloadBulkType === 'post_type') {
					foreach ($unloadBulkValues as $postType => $assetHandles) {
						if (in_array($assetHandle, $assetHandles)) {
							$targetKey = array_search($assetHandle, $assetHandles);
							unset($wpacuBulkUnloadDataArray[$assetType][$unloadBulkType][$postType][$targetKey]);
						}
					}
				}
			}

			Misc::addUpdateOption(
				WPACU_PLUGIN_ID . '_bulk_unload',
				wp_json_encode( Misc::filterList( $wpacuBulkUnloadDataArray ) )
			);
		}

		/*
		 * Table: WPACU_PLUGIN_ID . '_front_page_no_load'
		 * Homepage (Unloads)
		 */
		$wpacuFrontPageUnloads = get_option(WPACU_PLUGIN_ID . '_front_page_no_load');

		if ($wpacuFrontPageUnloads) {
			$wpacuFrontPageUnloadsArray = @json_decode( $wpacuFrontPageUnloads, ARRAY_A );

			if ( ! empty( $wpacuFrontPageUnloadsArray[$assetType] ) && in_array( $assetHandle, $wpacuFrontPageUnloadsArray[$assetType] ) ) {
				$targetKey = array_search($assetHandle, $wpacuFrontPageUnloadsArray[$assetType]);
				unset($wpacuFrontPageUnloadsArray[$assetType][$targetKey]);
			}

			Misc::addUpdateOption(
				WPACU_PLUGIN_ID . '_front_page_no_load',
				wp_json_encode( Misc::filterList( $wpacuFrontPageUnloadsArray ) )
			);
		}

		/*
		 * Table: WPACU_PLUGIN_ID . '_front_page_data'
		 * Homepage (async, defer)
		 */
		$wpacuFrontPageData = ($assetType === 'scripts') && get_option(WPACU_PLUGIN_ID . '_front_page_data');

		if  ($wpacuFrontPageData) {
			$wpacuFrontPageDataArray = @json_decode( $wpacuFrontPageData, ARRAY_A );

			if ( isset( $wpacuFrontPageDataArray[$assetType][$assetHandle] ) ) {
				unset( $wpacuFrontPageDataArray[$assetType][$assetHandle] );
			}

			if ( isset( $wpacuFrontPageDataArray['scripts_attributes_no_load'][$assetHandle] ) ) {
				unset( $wpacuFrontPageDataArray['scripts_attributes_no_load'][$assetHandle] );
			}

			Misc::addUpdateOption(
				WPACU_PLUGIN_ID . '_front_page_data',
				wp_json_encode( Misc::filterList( $wpacuFrontPageDataArray ) )
			);
		}

		/*
		 * Tables: $wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta (all part of the standard WordPress tables)
		 * Plugin meta keys: _{$wpacuPluginId}_no_load', _{$wpacuPluginId}_data
		 * Get all Asset CleanUp (Pro) meta keys from all WordPress meta tables where it can be possibly used
		 *
		 */
		global $wpdb;

		$wpacuPluginId = WPACU_PLUGIN_ID;

		foreach (array($wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta) as $tableName) {
			$wpacuGetValuesQuery = <<<SQL
SELECT * FROM `{$tableName}`
WHERE meta_key IN('_{$wpacuPluginId}_no_load', '_{$wpacuPluginId}_data')
SQL;
			$wpacuMetaData       = $wpdb->get_results( $wpacuGetValuesQuery, ARRAY_A );

			foreach ( $wpacuMetaData as $wpacuValues ) {
				$decodedValues = @json_decode( $wpacuValues['meta_value'], ARRAY_A );

				// No load rules
				if ( $wpacuValues['meta_key'] === '_' . $wpacuPluginId . '_no_load' && isset( $decodedValues[$assetType] ) && in_array($assetHandle, $decodedValues[$assetType]) ) {
					$targetKey = array_search($assetHandle, $decodedValues[$assetType]);
					unset($decodedValues[$assetType][$targetKey]);

					if ($tableName === $wpdb->postmeta) {
						update_post_meta($wpacuValues['post_id'], '_' . $wpacuPluginId . '_no_load', wp_json_encode( Misc::filterList( $decodedValues ) ) );
					} elseif ($tableName === $wpdb->termmeta) {
						update_term_meta($wpacuValues['term_id'], '_' . $wpacuPluginId . '_no_load', wp_json_encode( Misc::filterList( $decodedValues ) ) );
					} elseif ($tableName === $wpdb->usermeta) {
						update_user_meta($wpacuValues['user_id'], '_' . $wpacuPluginId . '_no_load', wp_json_encode( Misc::filterList( $decodedValues ) ) );
					}
				}

				// Other rules such as script attribute (e.g. async, defer)
				if ( $wpacuValues['meta_key'] === '_' . $wpacuPluginId . '_data' ) {
					if ( isset( $decodedValues[$assetType][$assetHandle] ) ) {
						unset( $decodedValues[ $assetType ][ $assetHandle ] );
					}

					// Load exceptions for script attributes
					if ( $assetType === 'scripts' && isset( $decodedValues['scripts_attributes_no_load'][$assetHandle] ) ) {
						unset($decodedValues['scripts_attributes_no_load'][$assetHandle]);
					}

					if ($tableName === $wpdb->postmeta) {
						update_post_meta($wpacuValues['post_id'], '_' . $wpacuPluginId . '_data', wp_json_encode( Misc::filterList( $decodedValues ) ) );
					} elseif ($tableName === $wpdb->termmeta) {
						update_term_meta($wpacuValues['term_id'], '_' . $wpacuPluginId . '_data', wp_json_encode( Misc::filterList( $decodedValues ) ) );
					} elseif ($tableName === $wpdb->usermeta) {
						update_user_meta($wpacuValues['user_id'], '_' . $wpacuPluginId . '_data', wp_json_encode( Misc::filterList( $decodedValues ) ) );
					}
				}
			}
		}
	}

	/**
	 * Remove unloading rules apart from the site-wide one
	 *
	 * @param $assetHandle
	 * @param $assetType
	 */
	public static function removeAllRedundantUnloadRulesFor($assetHandle, $assetType)
	{
		/*
		* Table: WPACU_PLUGIN_ID . '_front_page_no_load'
		* Homepage (Unloads)
		*/
		$wpacuFrontPageUnloads = get_option(WPACU_PLUGIN_ID . '_front_page_no_load');

		if ($wpacuFrontPageUnloads) {
			$wpacuFrontPageUnloadsArray = @json_decode( $wpacuFrontPageUnloads, ARRAY_A );

			if ( isset( $wpacuFrontPageUnloadsArray[$assetType] ) && ! empty( $wpacuFrontPageUnloadsArray[$assetType] ) && in_array( $assetHandle, $wpacuFrontPageUnloadsArray[$assetType] ) ) {
				$targetKey = array_search($assetHandle, $wpacuFrontPageUnloadsArray[$assetType]);
				unset($wpacuFrontPageUnloadsArray[$assetType][$targetKey]);
			}

			Misc::addUpdateOption(
				WPACU_PLUGIN_ID . '_front_page_no_load',
				wp_json_encode( Misc::filterList( $wpacuFrontPageUnloadsArray ) )
			);
		}

		/*
		 * Table: WPACU_PLUGIN_ID . '_bulk_unload'
		 * Bulk Unload
		 */
		$wpacuBulkUnloadData = get_option(WPACU_PLUGIN_ID . '_bulk_unload');
		$wpacuBulkUnloadDataArray = @json_decode($wpacuBulkUnloadData, ARRAY_A);

		if ( ! empty($wpacuBulkUnloadDataArray[$assetType]) ) {
			foreach ($wpacuBulkUnloadDataArray[$assetType] as $unloadBulkType => $unloadBulkValues) {
				// $unloadBulkType could be 'post_type', 'date', '404', 'taxonomy', 'search', 'custom_post_type_archive_[custom_post_type]'
				if ($unloadBulkType === 'post_type') {
					foreach ($unloadBulkValues as $postType => $assetHandles) {
						if (in_array($assetHandle, $assetHandles)) {
							$targetKey = array_search($assetHandle, $assetHandles);
							unset($wpacuBulkUnloadDataArray[$assetType][$unloadBulkType][$postType][$targetKey]);
						}
					}
				}
			}

			Misc::addUpdateOption(
				WPACU_PLUGIN_ID . '_bulk_unload',
				wp_json_encode( Misc::filterList( $wpacuBulkUnloadDataArray ) )
			);
		}

		/*
		 * Tables: $wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta (all part of the standard WordPress tables)
		 * Plugin meta key: _{$wpacuPluginId}_no_load'
		 *
		 */
		global $wpdb;

		$wpacuPluginId = WPACU_PLUGIN_ID;

		foreach (array($wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta) as $tableName) {
			$wpacuGetValuesQuery = <<<SQL
SELECT * FROM `{$tableName}` WHERE meta_key='_{$wpacuPluginId}_no_load'
SQL;
			$wpacuMetaData = $wpdb->get_results( $wpacuGetValuesQuery, ARRAY_A );

			foreach ( $wpacuMetaData as $wpacuValues ) {
				$decodedValues = @json_decode( $wpacuValues['meta_value'], ARRAY_A );

				// No load rules
				if ( isset( $decodedValues[$assetType] ) && in_array($assetHandle, $decodedValues[$assetType]) ) {
					$targetKey = array_search($assetHandle, $decodedValues[$assetType]);
					unset($decodedValues[$assetType][$targetKey]);

					if ($tableName === $wpdb->postmeta) {
						update_post_meta($wpacuValues['post_id'], '_' . $wpacuPluginId . '_no_load', wp_json_encode( Misc::filterList( $decodedValues ) ) );
					} elseif ($tableName === $wpdb->termmeta) {
						update_term_meta($wpacuValues['term_id'], '_' . $wpacuPluginId . '_no_load', wp_json_encode( Misc::filterList( $decodedValues ) ) );
					} elseif ($tableName === $wpdb->usermeta) {
						update_user_meta($wpacuValues['user_id'], '_' . $wpacuPluginId . '_no_load', wp_json_encode( Misc::filterList( $decodedValues ) ) );
					}
				}
			}
		}

		/*
		 * Table: WPACU_PLUGIN_ID . '_global_data'
		 * Global (Site-wide) Rules: Unload via RegEx
		 */
		$wpacuGlobalDataArray = wpacuGetGlobalData();

		foreach ( array( 'unload_regex' ) as $dataType ) {
			if ( ! empty( $wpacuGlobalDataArray[ $assetType ][ $dataType ] ) && array_key_exists($assetHandle,  $wpacuGlobalDataArray[ $assetType ][ $dataType ]) ) {
				unset( $wpacuGlobalDataArray[ $assetType ][ $dataType ][ $assetHandle ]);
			}
		}

		Misc::addUpdateOption(
			WPACU_PLUGIN_ID . '_global_data',
			wp_json_encode( Misc::filterList( $wpacuGlobalDataArray ) )
		);
	}
}
