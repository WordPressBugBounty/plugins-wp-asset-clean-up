<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\OptimiseAssets\CombineCssImports;
use WpAssetCleanUp\OptimiseAssets\OptimizeCss;
use WpAssetCleanUp\OptimiseAssets\OptimizeJs;

/**
 * Class FileSystem
 * @package WpAssetCleanUp
 */
class FileSystem
{
	/**
	 * @return bool|\WP_Filesystem_Direct
	 */
	public static function init()
	{
		if ( ! defined('WPACU_FS_USED') && ! class_exists('\WP_Filesystem_Base') && ! class_exists('\WP_Filesystem_Direct') ) {
			$wpFileSystemBase   = ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			$wpFileSystemDirect = ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

			if (is_file($wpFileSystemBase) && is_file($wpFileSystemDirect)) {
				// Make sure to use the 'direct' method as it's the most effective in this scenario
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
				define('WPACU_FS_USED', true);
			} else {
				// Do not use WordPress FileSystem Direct (fallback to default PHP functions)
				define('WPACU_FS_USED', false);
			}
		}

		if (wpacuIsDefinedConstant('WPACU_FS_USED')) {
			return new \WP_Filesystem_Direct( new \StdClass() );
		}

		return false;
	}

	/**
	 * @param string $localPathToFile
	 * @param string $alter
	 *
	 * @return false|string
	 */
	public static function fileGetContents($localPathToFile, $alter = '')
	{
		// ONLY relevant for CSS files
		if ($alter === 'combine_css_imports') {
			$cssContent = self::fileJustGetContents($localPathToFile);

			if (stripos($cssContent, '@import') !== false) {
				// This custom class does not minify as it's custom-made for combining @import
                return (new CombineCssImports($localPathToFile))->minify();
			}

			return $cssContent; // No '@import' found? Just return it
		}

		return self::fileJustGetContents($localPathToFile);
	}

	/**
	 * Fetch the contents of the targeted file without any alteration
	 *
	 * @param $localPathToFile
	 *
	 * @return false|string
	 */
	public static function fileJustGetContents($localPathToFile)
	{
		// Fallback
		if (! self::init()) {
			return @file_get_contents($localPathToFile);
		}

		return self::init()->get_contents($localPathToFile);
	}

	/**
	 * @param $localPathToFile
	 * @param $contents
	 *
	 * @return bool
	 */
	public static function filePutContents($localPathToFile, $contents)
	{
        $return = false; // default

		if (  (strpos($localPathToFile, WP_CONTENT_DIR . OptimizeCss::getRelPathCssCacheDir()) !== false && ! is_dir(dirname($localPathToFile)))
			|| (strpos($localPathToFile, WP_CONTENT_DIR . OptimizeJs::getRelPathJsCacheDir())  !== false && ! is_dir(dirname($localPathToFile)))
			) {
			$dirToCreate = dirname( $localPathToFile );
			try {
				mkdir( $dirToCreate, FS_CHMOD_DIR, true );
			} catch (\Exception $e) {
				error_log( WPACU_PLUGIN_TITLE . ': Could not make directory ' . $dirToCreate . ' / Error: '.$e->getMessage() );
			}
		}

		// Fallback
		try {
			if ( ! self::init() ) {
				$return = file_put_contents( $localPathToFile, $contents );
			} else {
				$return = self::init()->put_contents( $localPathToFile, $contents, FS_CHMOD_FILE );
			}
		} catch ( \Exception $e ) {
			error_log( WPACU_PLUGIN_TITLE . ': Could not write to ' . $localPathToFile . ' / Error: '.$e->getMessage() );
		}

		return $return;
	}
}
