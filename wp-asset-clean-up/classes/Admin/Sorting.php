<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\HardcodedAssets;
use WpAssetCleanUp\Misc;

/**
 * Class Sorting
 * @package WpAssetCleanUp
 */
class Sorting
{
	/**
	 * Sorts styles or scripts list in alphabetical ascending order (from A to Z) by the handle name
	 *
	 * @param $list
	 *
	 * @return mixed
	 */
	public static function sortListByAlpha($list)
	{
		if ( ! empty($list['styles']) ) {
			$newStyles = array();

			foreach ($list['styles'] as $styleObj) {
				if (! isset($styleObj->handle)) {
					continue;
				}

				if ($assetAlt = self::matchesWpCoreCriteria($styleObj, 'styles')) {
					if (isset($assetAlt->wp)) {
						$styleObj->wp = true;
					}

					if (isset($assetAlt->ver)) {
						$styleObj->ver = $assetAlt->ver;
					}
				}

				$newStyles[$styleObj->handle] = $styleObj;
			}

			$list['styles'] = $newStyles;

			sort($list['styles']);
		}

		if ( ! empty($list['scripts']) ) {
			$newScripts = array();

			foreach ($list['scripts'] as $scriptObj) {
				if (! isset($scriptObj->handle)) {
					continue;
				}

				if ($assetAlt = self::matchesWpCoreCriteria($scriptObj, 'scripts')) {
					if (isset($assetAlt->wp)) {
						$scriptObj->wp = true;
					}

					if (isset($assetAlt->ver)) {
						$scriptObj->ver = $assetAlt->ver;
					}
				}

				$newScripts[$scriptObj->handle] = $scriptObj;
			}

			$list['scripts'] = $newScripts;

			sort($list['scripts']);
		}

		return $list;
	}

	/**
	 * The appended location values will be used to sort the list of assets
	 *
	 * @param $list
	 *
	 * @return mixed
	 */
	public static function appendLocation($list)
	{
		foreach (array('styles', 'scripts') as $assetType) {
			if ( empty( $list[ $assetType ] ) ) {
				continue;
			}

			foreach ( $list[$assetType] as $indexNo => $asset ) {
				$asset = self::appendLocationToAsset($asset, $assetType);
				$list[$assetType][$indexNo] = $asset;
			}
		}

		return $list;
	}

    /**
     * @param $asset object
     * @param $assetType string
     * @param string $forType ("enqueued": default, "hardcoded")
     *
     * @return object
     */
    public static function appendLocationToAsset($asset, $assetType, $forType = 'enqueued')
    {
        $src = isset($asset->src) ? $asset->src : '';
        $miscLocalSrc = Misc::getLocalSrcIfExist($src);

        if ($assetAlt = self::matchesWpCoreCriteria($asset, $assetType)) {
            // Core Files
            $asset->locationMain  = 'wp_core';
            $asset->locationChild = 'none';

            if (isset($assetAlt->wp)) {
                $asset->wp = true;
            }

            if (isset($assetAlt->ver)) {
                $asset->ver = true;
            }
        } elseif ($pluginDir = self::matchesPluginCriteria($asset)) {
            // From plugins directory (usually /wp-content/plugins/)
            if ($pluginDir === 'n/a' && $src) {
                if (strpos($src, '/'.Misc::getPluginsDir().'/') !== false) {
                    $srcParts = explode('/'.Misc::getPluginsDir().'/', $src);
                    list ($pluginDir) = explode('/', $srcParts[1]);
                } else {
                    $relSrc = str_replace(plugins_url(), '', $src);

                    if ($relSrc[0] === '/') {
                        $relSrc = substr($relSrc, 1);
                    }

                    list ($pluginDir) = explode('/', $relSrc);
                }
            }

            $asset->locationMain  = 'plugins';
            $asset->locationChild = $pluginDir;

            } elseif ( ( ! empty($miscLocalSrc) && strpos($src, '/wp-content/uploads/') !== false )
                || strncmp($src, '/wp-content/uploads/', 20) === 0 ) {
            $asset->locationMain  = 'uploads';
            $asset->locationChild = 'none';
        } else {
            $isWithinThemes = false;

            foreach (Misc::getUrlsToThemeDirs() as $urlToThemeDir) {
                $srcRel = str_replace(site_url(), '', $src);

                if (strpos($srcRel, $urlToThemeDir) !== false) {
                    $isWithinThemes = true;

                    $themeDir = substr(strrchr(trim($urlToThemeDir, '/'), '/'), 1);

                    $asset->locationMain  = 'themes';
                    $asset->locationChild = $themeDir;
                    break;
                    }
            }

            // Default: "External" for enqueued or "External", "Undetectable", etc. for hardcoded
            if ( ! $isWithinThemes ) {
                $asset->locationChild = 'none'; // at this stage, there's no "child" location

                if ( $forType === 'enqueued' ) {
                    // Outside "themes", "plugins" and "wp-includes"
                    $asset->locationMain  = 'external';
                    }

                // Hardcoded assets often do not have something within them to detect the plugin/theme they are loading from
                // Some of them will be added to a category such as "Miscellaneous"
                if ( $forType === 'hardcoded' ) {
                    if ($src) {
                        if (Misc::isLocalSrc($src)) {
                            $asset->locationMain = 'misc';
                        } else {
                            $asset->locationMain = 'external';
                        }
                    } else {
                        $asset->locationMain  = 'other';
                    }
                }
            }
        }

        return $asset;
    }

	/**
	 * @param $asset
	 * @param $assetType
	 *
	 * @return bool
	 */
	public static function matchesWpCoreCriteria($asset, $assetType)
	{
		global $wp_version;

		$src = isset($asset->src) ? $asset->src : '';

		$localSrc = $src ? Misc::getLocalSrcIfExist($src) : '';

		$srcToUse = $src;

		if (! empty($localSrc) && isset($localSrc['rel_src']) && $localSrc['rel_src']) {
			$srcToUse = $localSrc['rel_src']; // the relative path
		}

		$isJQueryHandle       = ($assetType === 'scripts') && in_array($asset->handle, array('jquery', 'jquery-core', 'jquery-migrate'));
		$isJQueryUpdater      = ($assetType === 'scripts' && $src !== '') && strpos($src, '/' . Misc::getPluginsDir( 'dir_name' ) . '/jquery-updater/js/jquery-') !== false;

		$startsWithWpIncludes = strncmp($srcToUse, 'wp-includes/', 12) === 0;
		$startsWithWpAdmin    = strncmp($srcToUse, 'wp-admin/', 9) === 0;
		$wpCoreOnJetpackCdn   = strpos($src, '.wp.com/c/'.$wp_version.'/wp-includes/') !== false;

		$coreCssHandlesList = <<<LIST
classic-theme-styles
core-block-supports
core-block-supports-duotone
global-styles
global-styles-css-custom-properties
wp-block-directory
wp-block-library
wp-block-styles
wp-block-library-theme
wp-block-pattern
wp-webfonts
wp-block-post-date
wp-emoji-styles
LIST;
		$cssCoreHandles = array_merge(
			explode("\n", $coreCssHandlesList),
			Misc::getWpCoreCssHandlesFromWpIncludesBlocks() // Source: /wp-includes/blocks/
		);

		$coreJsHandlesList = <<<LIST
admin-bar
code-editor
jquery-ui-datepicker
LIST;
		$jsCoreHandles = explode("\n", $coreJsHandlesList);

		$isCssCoreHandleFromWpIncludesBlocks = ($assetType === 'styles')  && in_array($asset->handle, $cssCoreHandles);
		$isJsCoreHandleFromWpIncludesBlocks  = ($assetType === 'scripts') && in_array($asset->handle, $jsCoreHandles);

		if ( ! ($isJQueryHandle || $isJQueryUpdater || $startsWithWpIncludes || $startsWithWpAdmin || $isCssCoreHandleFromWpIncludesBlocks || $isJsCoreHandleFromWpIncludesBlocks || $wpCoreOnJetpackCdn) ) {
			return false; // none of the above conditions matched, thus, this is not a WP core file
		}

		$assetAlt = $asset;

		if ($wpCoreOnJetpackCdn) {
			$assetAlt->wp  = true;
			$assetAlt->ver = $wp_version;
		}

		return $assetAlt;
	}

	/**
	 * @param $asset
	 *
	 * @return bool|string
	 */
	public static function matchesPluginCriteria($asset)
	{
		$src = isset($asset->src) ? $asset->src : '';

        // Hardcoded without "src"? It could match a specific pattern
        if (! $src && (isset($asset->tag_output) && $tagBelongsToArray = HardcodedAssets::belongsTo($asset->tag_output))) {
            return $tagBelongsToArray['dir'];
        }

		$isOxygenBuilderPlugin = strpos( $src, '/wp-content/uploads/oxygen/css/' ) !== false;
		$isElementorPlugin     = strpos( $src, '/wp-content/uploads/elementor/css/' ) !== false;
		$isWooCommerceInline   = $asset->handle === 'woocommerce-inline';
		$miscLocalSrc          = Misc::getLocalSrcIfExist($src);

		$isPlugin = $isOxygenBuilderPlugin ||
		            $isElementorPlugin     ||
		            $isWooCommerceInline   ||
		            strpos( $src, plugins_url() ) !== false ||
		            ((! empty($miscLocalSrc) && strpos($src, '/'.Misc::getPluginsDir().'/') !== false) || strpos($src, '/'.Misc::getPluginsDir().'/') === 0);

		if (! $isPlugin) {
			return false;
		}

		$pluginDir = 'n/a'; // default

		if ($isOxygenBuilderPlugin) {
			$pluginDir = 'oxygen';
		} elseif ($isElementorPlugin) {
			$pluginDir = 'elementor';
		} elseif ($isWooCommerceInline) {
			$pluginDir = 'woocommerce';
		}

		return $pluginDir;
	}

    /**
     * @param array $assetRows
     * @@param bool $sortByKey
     *
     * @return array
     */
    public static function sortAreaAssetRowsValues($assetRowsValues, $sortByKey = true)
    {
        $newAssetRowsValues = $hiddenAssetRowsValues = array();

        foreach ($assetRowsValues as $assetHandle => $assetRowOutputs) {
            foreach ($assetRowOutputs as $assetTypeS => $assetRowOutput) {
                if (strpos($assetRowOutput, 'wpacu_this_asset_row_area_is_hidden') === false) {
                    $newAssetRowsValues[$assetHandle][$assetTypeS] = $assetRowOutput;
                } else {
                    $hiddenAssetRowsValues[$assetHandle][$assetTypeS] = $assetRowOutput; // build the hidden list to append to the final list below
                }
            }
        }

        if ($sortByKey) {
            ksort($newAssetRowsValues);
        }

        // Sometimes, there are Core assets (TABLE TRs) that are printed as HTML, but are not shown (.wpacu_hide)
        // Put them to the bottom of the list; This improves CSS matching the first visible row,
        // as well as makes sure the background colors will always be alternative (one row after another)
        if ( ! empty($hiddenAssetRowsValues) ) {
            foreach ($hiddenAssetRowsValues as $hiddenAssetHandle => $hiddenAssetRowOutputs) {
                foreach ($hiddenAssetRowOutputs as $hidenAssetTypeS => $hiddenAssetRowOutput) {
                    $newAssetRowsValues[$hiddenAssetHandle][$hidenAssetTypeS] = $hiddenAssetRowOutput;
                }
            }
        }

        return $newAssetRowsValues;
    }
}
