<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\MainFront;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\ObjectCache;
use WpAssetCleanUp\OwnAssets;
use WpAssetCleanUp\Preloads;
use WpAssetCleanUp\FileSystem;
use WpAssetCleanUp\CleanUp;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Misc;

/**
 * Class OptimizeCss
 * @package WpAssetCleanUp
 */
class OptimizeCss
{
	/**
	 *
	 */
	const MOVE_NOSCRIPT_TO_BODY_FOR_CERTAIN_LINK_TAGS = '<span style="display: none;" data-name=wpacu-delimiter data-content="ASSET CLEANUP NOSCRIPT FOR ASYNC PRELOADS"></span>';

	/**
	 *
	 */
	public function init()
	{
		add_action('init', array($this, 'triggersAfterInit'));

		add_action('wp_footer', static function() {
			if ( OptimizeCommon::preventAnyFrontendOptimization() || ! OptimizeCommon::isWorthCheckingForCssOptimization() ) { return; }

			/* [wpacu_timing] */ Misc::scriptExecTimer( 'prepare_optimize_files_css' ); /* [/wpacu_timing] */
			self::prepareOptimizeList();
			/* [wpacu_timing] */ Misc::scriptExecTimer( 'prepare_optimize_files_css', 'end' ); /* [/wpacu_timing] */

			echo self::MOVE_NOSCRIPT_TO_BODY_FOR_CERTAIN_LINK_TAGS;
		}, PHP_INT_MAX);

		add_filter('wpacu_html_source_after_optimization', static function($htmlSource) {
			// Are any the marks still there & weren't replaced? Strip them to have a clean HTML output!
			return str_replace(self::MOVE_NOSCRIPT_TO_BODY_FOR_CERTAIN_LINK_TAGS, '', $htmlSource);
		});

		add_filter('wpacu_add_noscript_certain_link_tags', array($this, 'appendNoScriptCertainLinkTags'));
	}

	/**
	 *
	 */
	public function triggersAfterInit()
	{
		if (self::isInlineCssEnabled()) {
			$allPatterns = self::getAllInlineChosenPatterns();

			if (! empty($allPatterns)) {
				// Make "Inline CSS Files" compatible with "Optimize CSS Delivery" from WP Rocket
				add_filter('rocket_async_css_regex_pattern', static function($regex) {
					return '/(?=<link(?!.*wpacu-to-be-inlined.*)[^>]*\s(rel\s*=\s*[\'"]stylesheet["\']))<link(?!.*wpacu-to-be-inlined.*)[^>]*\shref\s*=\s*[\'"]([^\'"]+)[\'"](.*)>/iU';
				});

				add_filter('style_loader_tag', static function($styleTag) use ($allPatterns) {
					foreach ($allPatterns as $patternToCheck) {
						preg_match_all( '#<link[^>]*stylesheet[^>]*('.$patternToCheck.').*(>)#Usmi', $styleTag, $matchesSourcesFromTags, PREG_SET_ORDER );

						if ( ! empty( $matchesSourcesFromTags ) ) {
							return str_replace( '<link ', '<link wpacu-to-be-inlined=\'1\' ', $styleTag );
						}
					}

					return $styleTag;
				});
			}
		}
	}

	/**
	 * @return array
	 */
	public static function getAllInlineChosenPatterns()
	{
		$inlineCssFilesPatterns = trim(Main::instance()->settings['inline_css_files_list']);

		$allPatterns = array();

		if (strpos($inlineCssFilesPatterns, "\n")) {
			// Multiple values (one per line)
			foreach (explode("\n", $inlineCssFilesPatterns) as $inlinePattern) {
				$allPatterns[] = trim($inlinePattern);
			}
		} else {
			// Only one value?
			$allPatterns[] = trim($inlineCssFilesPatterns);
		}

		// Strip any empty values
		return array_filter($allPatterns);
	}

    /**
     * @return array
     */
    public static function skipAssetsFromAnyOptimize()
    {
        if ( ! isset(OwnAssets::$ownAssets['styles']['style_core']) ) {
            OwnAssets::prepareVars();
        }

        return array(OwnAssets::$ownAssets['styles']['style_core']['handle'], 'rank-math');
    }

	/**
	 *
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function prepareOptimizeList()
	{
		global $wp_styles;

		$allStylesHandles = ObjectCache::wpacu_cache_get('wpacu_all_styles_handles');
		if (empty($allStylesHandles)) {
			return;
		}

		// [Start] Collect for caching
		$wpStylesDone       = isset($wp_styles->done)       && is_array($wp_styles->done)       ? $wp_styles->done       : array();
		$wpStylesRegistered = isset($wp_styles->registered) && is_array($wp_styles->registered) ? $wp_styles->registered : array();

		// Collect all enqueued clean (no query strings) HREFs to later compare them against any hardcoded CSS
		$allEnqueuedCleanLinkHrefs = $allLocalAssetPathsValues = array();

		if (! empty($wpStylesDone) && ! empty($wpStylesRegistered)) {
			foreach ( $wpStylesDone as $index => $styleHandle ) {
				if ( isset( Main::instance()->wpAllStyles['registered'][ $styleHandle ]->src ) && ( $src = Main::instance()->wpAllStyles['registered'][ $styleHandle ]->src ) && ! in_array($styleHandle, self::skipAssetsFromAnyOptimize()) ) {
					$localAssetPath = OptimizeCommon::getLocalAssetPath( $src, 'css' );

					if ( ! $localAssetPath ) {
						continue; // not a local file
					}

					$allLocalAssetPathsValues[$src] = $localAssetPath;

					if ( ! $linkSourceTag = ObjectCache::wpacu_cache_get('wpacu_style_loader_tag_'.$styleHandle) ) {
						ob_start();
						$wp_styles->do_item( $styleHandle );
						$linkSourceTag = trim( ob_get_clean() );
					}

					// Check if the CSS has any 'data-wpacu-skip' attribute; if it does, do not alter it
					if ( preg_match( '#data-wpacu-skip([=>/ ])#i', $linkSourceTag ) ) {
						unset( $wpStylesDone[ $index ] );
						continue;
					}

					$cleanLinkHrefFromTagArray = OptimizeCommon::getLocalCleanSourceFromTag( $linkSourceTag );

					if ( isset( $cleanLinkHrefFromTagArray['source'] ) && $cleanLinkHrefFromTagArray['source'] ) {
                        $allEnqueuedCleanLinkHrefs[$styleHandle] = $cleanLinkHrefFromTagArray['source'];
					}
				}
			}
		}

		$cssOptimizeList = array();

		if (! empty($wpStylesDone) && ! empty($wpStylesRegistered)) {
			$isMinifyCssFilesEnabled = in_array(Main::instance()->settings['minify_loaded_css_for'], array('href', 'all', ''))
			                           && MinifyCss::isMinifyCssEnabled();

			foreach ( $wpStylesDone as $styleHandle ) {
                if ( ! (isset($wpStylesRegistered[ $styleHandle ]->src) && $wpStylesRegistered[$styleHandle]->src) || in_array($styleHandle, self::skipAssetsFromAnyOptimize()) ) {
                    continue;
                }

				$value = $wpStylesRegistered[ $styleHandle ];

				// If it's already in the list of SRCs that were checked, skip it
				if ( ! array_key_exists($value->src, $allLocalAssetPathsValues) ) {
					// Not in the list? Check if it's a local asset and if it's not skip it
					$localAssetPath = OptimizeCommon::getLocalAssetPath( $value->src, 'css' );

					if ( ! $localAssetPath ) {
						continue; // not a local file
					}
				} else {
					$localAssetPath = $allLocalAssetPathsValues[$value->src];
				}

				$optimizeValues = self::maybeOptimizeIt(
					$value,
					array(
						'local_asset_path'      => $localAssetPath,
						'is_minify_css_enabled' => $isMinifyCssFilesEnabled
					)
				);

				ObjectCache::wpacu_cache_set( 'wpacu_maybe_optimize_it_css_' . $styleHandle, $optimizeValues );

				if ( ! empty( $optimizeValues ) ) {
					$cssOptimizeList[] = $optimizeValues;
				}

				}
		}

		if (empty($cssOptimizeList)) {
			return;
		}

		ObjectCache::wpacu_cache_add('wpacu_css_enqueued_hrefs', $allEnqueuedCleanLinkHrefs);
		ObjectCache::wpacu_cache_add('wpacu_css_optimize_list',  $cssOptimizeList);

		// [End] Collect for caching
	}

	/**
	 * @param $value
	 * @param array $fileAlreadyChecked
	 *
	 * @return mixed
	 */
	public static function maybeOptimizeIt($value, $fileAlreadyChecked = array())
	{
		if ($optimizeValues = ObjectCache::wpacu_cache_get('wpacu_maybe_optimize_it_css_'.$value->handle)) {
			return $optimizeValues;
		}

		global $wp_version;

		$src = isset($value->src) ? $value->src : false;

		if (! $src) {
			return array();
		}

		$doFileMinify = true;

		$isMinifyCssFilesEnabled = (isset($fileAlreadyChecked['is_minify_css_enabled']) && $fileAlreadyChecked['is_minify_css_enabled'])
			? $fileAlreadyChecked['is_minify_css_enabled']
			: in_array(Main::instance()->settings['minify_loaded_css_for'], array('href', 'all', '')) && MinifyCss::isMinifyCssEnabled();

		if ( ! $isMinifyCssFilesEnabled || MinifyCss::skipMinify($src, $value->handle) ) {
			$doFileMinify = false;
		}

		// Default (it will be later replaced with the last time the file was modified, which is more accurate)
		$dbVer = (isset($value->ver) && $value->ver) ? $value->ver : $wp_version;

		$isCssFile = false;

		// Already checked? Do not reuse OptimizeCommon::getLocalAssetPath() and is_file()
		if (isset($fileAlreadyChecked['local_asset_path']) && $fileAlreadyChecked['local_asset_path']) {
			$localAssetPath = $fileAlreadyChecked['local_asset_path'];
		} else {
			$localAssetPath = OptimizeCommon::getLocalAssetPath( $src, 'css' );
		}

		$checkCond = $localAssetPath;

		if ($checkCond) {
			if ($fileMTime = @filemtime($localAssetPath)) {
				$dbVer = $fileMTime;
			}
			$isCssFile = true;
		}

		if ($isCssFile) {
			// This is the safest one as handle names for specific static can change on every page load
			// as some developers have a habit of adding the UNIX time or other random string to a handle (e.g. for debugging)
			$uniqueAssetStr = md5 ( str_replace(Misc::getWpRootDirPathBasedOnPath($localAssetPath), '', $localAssetPath) );
		} else {
			$uniqueAssetStr = md5( $value->handle );
		}

		$transientName = 'wpacu_css_optimize_'.$uniqueAssetStr;

		$skipCache = false;

		if (isset($_GET['wpacu_no_cache']) || wpacuIsDefinedConstant('WPACU_NO_CACHE')) {
			$skipCache = true;
		}

	    if (! $skipCache) {
		    $savedValuesArray = OptimizeCommon::getTransient($transientName);

		    if (isset($savedValuesArray[0]) && $savedValuesArray[0] === 'no_alter') {
			    return array();
		    }

		    if ( ! empty($savedValuesArray) ) {
				if ( $savedValuesArray['ver'] === $dbVer ) {
					$localPathToCssOptimized = str_replace( '//', '/', Misc::getWpRootDirPathBasedOnPath($savedValuesArray['optimize_uri']) . $savedValuesArray['optimize_uri'] );

					// Read the file from its caching (that makes the processing faster)
					// It will fallback to the original CSS file
					if ( isset( $savedValuesArray['source_uri'] ) && is_file( $localPathToCssOptimized ) ) {
						if ( Main::instance()->settings['fetch_cached_files_details_from'] === 'db_disk' ) {
							$GLOBALS['wpacu_from_location_inc'] ++;
						}

						return array(
							$savedValuesArray['source_uri'],
							$savedValuesArray['optimize_uri'],
							$value->src,
							$value->handle
						);
					}
				}

				// If nothing valid gets returned above, make sure the transient gets deleted as it's re-added later on
				OptimizeCommon::deleteTransient($transientName);
			}
	    }

		// Check if it starts without "/" or a protocol; e.g. "wp-content/theme/style.css"
		if (strncmp($src, '/', 1) !== 0 &&
            strncmp($src, '//', 2) !== 0 &&
            strncasecmp($src, 'http://', 7) !== 0 &&
            strncasecmp($src, 'https://', 8) !== 0
		) {
			$src = '/'.$src; // append the forward slash to be processed as relative later on
		}

        $src = Misc::getHrefFromSource($src);

		if ($value->handle === 'sccss_style' &&
		    Main::instance()->settings['cache_dynamic_loaded_css'] &&
		    in_array('simple-custom-css/simple-custom-css.php', Misc::getActivePlugins())
		) {
			$pathToAssetDir = '';
			$sourceBeforeOptimization = $value->src;

			if (! ($cssContent = DynamicLoadedAssets::getAssetContentFrom('simple-custom-css', $value))) {
				return array();
			}
		} elseif (Main::instance()->settings['cache_dynamic_loaded_css'] &&
		          ((strpos($src, '/?') !== false) || (strpos($src, rtrim(site_url(),'/').'?') !== false) || (strpos($src, '.php?') !== false) || Misc::endsWith($src, '.php')) &&
		          (strpos($src, rtrim(site_url(), '/')) !== false)
		) {
			$pathToAssetDir = '';
			$sourceBeforeOptimization = str_replace('&#038;', '&', $value->src);

			if (! ($cssContent = DynamicLoadedAssets::getAssetContentFrom('dynamic', $value))) {
				return array();
			}
		} else {
			if (! $isCssFile) {
				return array();
			}

			/*
			 * This is a local .CSS file
			 */
			$pathToAssetDir = OptimizeCommon::getPathToAssetDir($src);

			$cssContent = FileSystem::fileGetContents($localAssetPath, 'combine_css_imports');

			$sourceBeforeOptimization = str_replace(Misc::getWpRootDirPathBasedOnPath($localAssetPath), '/', $localAssetPath);
		}

		$cssContent = trim($cssContent);

		/*
		 * [START] CSS Content Optimization
		*/
		// If there are no changes from this point, do not optimize (keep the file where it is)
		$cssContentBefore = $cssContent;

		if ($cssContent) { // only proceed with extra alterations if there is some content there (save resources)
			$cssContent = apply_filters( 'wpacu_local_fonts_display_css_output', $cssContent, Main::instance()->settings['local_fonts_display'] );

			if ( Main::instance()->settings['google_fonts_display'] ) {
				// Any "font-display" enabled in "Settings" - "Google Fonts"?
				$cssContent = FontsGoogle::alterGoogleFontUrlFromCssContent( $cssContent );
			}

			// Move any @imports to top; This also strips any @imports to Google Fonts if the option is chosen
			$cssContent = self::importsUpdate( $cssContent );
		}

		// If it stays like this, it means there is content there, even if only comments
		$cssContentBecomesEmptyAfterMin = false;

		if ($doFileMinify && $cssContent) { // only bother to minify it if it has any content, save resources
			// Minify this file?
			$cssContentBeforeMin = trim($cssContent);
			$cssContentAfterMin  = MinifyCss::applyMinification($cssContent);

			$cssContent = $cssContentAfterMin;

			if ($cssContentBeforeMin && $cssContentAfterMin === '') {
				// It had content, but became empty after minification, most likely it had only comments (e.g. a default child theme's style)
				$cssContentBecomesEmptyAfterMin = true;
			}
		}

		if ($cssContentBecomesEmptyAfterMin || $cssContent === '') {
			$cssContent = '/**/';
		} else {
			if ( Main::instance()->settings['google_fonts_remove'] ) {
				$cssContent = FontsGoogleRemove::cleanFontFaceReferences( $cssContent );
			}

			// No changes were made, thus, there's no point in changing the original file location
			if ( $isCssFile && trim( $cssContentBefore ) === trim( $cssContent ) ) {
				// There's no point in changing the original CSS (static) file location
				OptimizeCommon::setTransient($transientName, 'no_alter');
				return array();
			}

			// Does it have a source map? Strip it
			if (strpos($cssContent, '/*# sourceMappingURL=') !== false) {
				$cssContent = OptimizeCommon::stripSourceMap($cssContent, 'css');
			}

			$cssContent = self::maybeFixCssContent( $cssContent, $pathToAssetDir . '/' ); // Path
		}
		/*
         * [END] CSS Content Optimization
		*/

		// Relative path to the new file
		// Save it to /wp-content/cache/css/{OptimizeCommon::$optimizedSingleFilesDir}/
		/*
		if ($fileVer !== $wp_version) {
			if (is_array($fileVer)) {
				// Convert to string if it's an array (rare cases)
				$fileVer = implode('-', $fileVer);
			}
			$fileVer = trim(str_replace(' ', '_', preg_replace('/\s+/', ' ', $fileVer)));
			$fileVer = (strlen($fileVer) > 50) ? substr(md5($fileVer), 0, 20) : $fileVer; // don't end up with too long filenames
		}
		*/
		$fileVer = sha1($cssContent);

		$uniqueCachedAssetName = OptimizeCommon::generateUniqueNameForCachedAsset($isCssFile, $localAssetPath, $value->handle, $fileVer);

		$newFilePathUri  = self::getRelPathCssCacheDir() . OptimizeCommon::$optimizedSingleFilesDir . '/' . $uniqueCachedAssetName;
		$newFilePathUri .= '.css';

		if ($cssContent === '') {
			$cssContent = '/**/';
		}

		if ($cssContent === '/**/') {
			// Leave a signature that the file is empty, thus it would be faster to take further actions upon it later on, saving resources)
			$newFilePathUri = str_replace('.css', '-wpacu-empty-file.css', $newFilePathUri);
		}

		$newLocalPath    = WP_CONTENT_DIR . $newFilePathUri; // Ful Local path
		$newLocalPathUrl = WP_CONTENT_URL . $newFilePathUri; // Full URL path

		if ($cssContent && $cssContent !== '/**/' && apply_filters('wpacu_print_info_comments_in_cached_assets', true)) {
			$cssContent = '/*!' . $sourceBeforeOptimization . '*/' . $cssContent;
		}

		$saveFile = FileSystem::filePutContents($newLocalPath, $cssContent);

		if (! $saveFile && ! $cssContent) {
			// Fallback to the original CSS if the optimized version can't be created or updated
			return array();
		}

		$saveOutput = OptimizeCommon::getSourceRelPath($src) . "\n" .
		              OptimizeCommon::getSourceRelPath($newLocalPathUrl) . "\n" .
		              $dbVer;

		// Re-add transient
		OptimizeCommon::setTransient($transientName, $saveOutput);

		return array(
			OptimizeCommon::getSourceRelPath($src), // Original SRC (Relative path)
			OptimizeCommon::getSourceRelPath($newLocalPathUrl), // New SRC (Relative path)
			$value->src, // SRC (as it is)
			$value->handle
		);
	}

	/**
	 * @param $htmlSource
	 *
	 * @return mixed|void
	 */
	public static function alterHtmlSource($htmlSource)
	{
		// There has to be at least one "<link" or "<style", otherwise, it could be a feed request or something similar (not page, post, homepage etc.)
		if ( (stripos($htmlSource, '<link') === false && stripos($htmlSource, '<style') === false) || isset($_GET['wpacu_no_optimize_css']) ) {
			return $htmlSource;
		}

		/* [wpacu_timing] */ Misc::scriptExecTimer('alter_html_source_for_optimize_css'); /* [/wpacu_timing] */

		// Are there any assets unloaded where their "children" are ignored?
		// Since they weren't dequeued the WP way (to avoid unloading the "children"), they will be stripped here
        /* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_unload_ignore_deps_css'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
		if (! Main::instance()->preventAssetsSettings()) {
			$htmlSource = self::ignoreDependencyRuleAndKeepChildrenLoaded($htmlSource);
		}
        /* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_original_to_optimized_css'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
		if (OptimizeCommon::isWorthCheckingForCssOptimization()) {
			// 'wpacu_css_optimize_list' caching list is also checked; if it's empty, no optimization is made
			$htmlSource = self::updateHtmlSourceOriginalToOptimizedCss($htmlSource);
		}
		/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		if (! Main::instance()->preventAssetsSettings()) {
			/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_preload_css'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
            if ( ! wpacuIsDefinedConstant('WPACU_NO_ASSETS_PRELOADED') ) {
                $htmlSource = Preloads::instance()->doChanges($htmlSource);
            }
			/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */
		}

		if (self::isInlineCssEnabled()) {
			/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_inline_css'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
			$htmlSource = self::doInline($htmlSource);
			/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */
		}

		/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_combine_css'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
        if (self::proceedWithCssCombine()) {
            $proceedWithCombineOnThisPage = true;

            $isSingularPage = (int)wpacuGetConstant('WPACU_CURRENT_PAGE_ID') > 0 && MainFront::isSingularPage();

            // If "Do not combine CSS on this page" is checked in "Asset CleanUp: Options" side meta box
            // Works for posts, pages and custom post types
            if ($isSingularPage || MainFront::isHomePage()) {
                if ($isSingularPage) {
                    $pageOptions = MetaBoxes::getPageOptions(WPACU_CURRENT_PAGE_ID); // Singular page
                } else {
                    $pageOptions = MetaBoxes::getPageOptions(0, 'front_page'); // Home page
                }

                // 'no_css_optimize' refers to avoid the combination of CSS files
                if ((isset($pageOptions['no_css_optimize']) && $pageOptions['no_css_optimize'])
                    || (isset($pageOptions['no_assets_settings']) && $pageOptions['no_assets_settings'])) {
                    $proceedWithCombineOnThisPage = false;
                }
            }

            if ($proceedWithCombineOnThisPage) {
                $htmlSource = CombineCss::doCombine($htmlSource);
            }
        }
		/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_minify_inline_style_tags'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
        if ( OptimizeCommon::isWorthCheckingForCssOptimization() &&
             MinifyCss::isMinifyCssEnabled() &&
             in_array( Main::instance()->settings['minify_loaded_css_for'], array( 'inline', 'all' ) ) &&
		     ! Main::instance()->preventAssetsSettings() ) {
			$htmlSource = MinifyCss::minifyInlineStyleTags( $htmlSource );
		}
		/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

        /* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_local_fonts_display_style_inline'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
        if (Main::instance()->settings['local_fonts_display'] !== '') {
            $htmlSource = apply_filters('wpacu_local_fonts_display_style_inline', $htmlSource, Main::instance()->settings['local_fonts_display']);
        }
		/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		// Final cleanups
		/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_optimize_css_final_cleanups'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
            if ( ! wpacuIsDefinedConstant('WPACU_NO_ASSETS_PRELOADED') ) {
                $htmlSource = str_replace(Preloads::DEL_STYLES_PRELOADS, '', $htmlSource);
            }

            if (strpos($htmlSource, 'data-wpacu-link-rel-href-before') !== false) {
                $htmlSource = preg_replace('#(\s+|)data-wpacu-link-rel-href-before="' . '(.*)' . '"(\s+|)#Usm', ' ', $htmlSource);
            }

            if (strpos($htmlSource, 'data-wpacu-style-handle') !== false) {
                $htmlSource = preg_replace('#<link(.*)data-wpacu-style-handle=\'(.*)\'#Umi', '<link \\1', $htmlSource);
            }

            // Clear possible empty SCRIPT tags (e.g. left from associated 'before' and 'after' tags after their content was stripped)
            $htmlSource = preg_replace('#<style(\s+|)(type=\'text/css\'|)(\s+|)>(\s+|)</style>#Umi', '', $htmlSource);
        /* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_google_fonts_optimization_removal'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
		// Alter HTML Source for Google Fonts Optimization / Removal
        if (Main::instance()->settings['google_fonts_combine'] || Main::instance()->settings['google_fonts_display'] || Main::instance()->settings['google_fonts_remove']) {
            $htmlSource = FontsGoogle::alterHtmlSource($htmlSource);
        }
		/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		// Any critical CSS?
		$htmlSource = apply_filters('wpacu_alter_source_for_critical_css', $htmlSource);

		// NOSCRIPT fallback: Applies for Google Fonts (async) (Lite and Pro) /  Preloads (Async in the Pro version) / Critical CSS (as LINK "stylesheet" tags will be async preloaded)
		/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_add_async_preloads_noscript'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
        if ( ! wpacuIsDefinedConstant('WPACU_NO_ASSETS_PRELOADED') ) {
            $htmlSource = apply_filters('wpacu_add_noscript_certain_link_tags', $htmlSource);
        }
		/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		/* [wpacu_timing] */ Misc::scriptExecTimer('alter_html_source_for_optimize_css', 'end'); /* [/wpacu_timing] */

		return $htmlSource;
	}

	/**
	 * @return string
	 */
	public static function getRelPathCssCacheDir()
	{
		return OptimizeCommon::getRelPathPluginCacheDir().'css/'; // keep trailing slash at the end
	}

	/**
	 * @param $firstLinkHref
	 * @param $htmlSource
	 *
	 * @return string
	 */
	public static function getFirstLinkTag($firstLinkHref, $htmlSource)
	{
		preg_match_all('#<link[^>]*stylesheet[^>]*(>)#Umi', $htmlSource, $matches);
		foreach ($matches[0] as $matchTag) {
			if (strpos($matchTag, $firstLinkHref) !== false) {
				return trim($matchTag);
			}
		}

		return '';
	}

	/**
	 *
	 * @param $cssContent
	 * @param $appendBefore
	 * @param $fix
	 *
	 * @return mixed
	 */
	public static function maybeFixCssContent($cssContent, $appendBefore, $fix = 'path')
	{
		// Updates (background | font etc.) URLs to the right path and others
		if ($fix === 'path') {
			// Clear any extra spaces between @import and the single/double quotes
			$cssContent = preg_replace('/@import(\s+|)([\'"])/i', '@import \\2', $cssContent);

			$cssContentPathReps = array(
				// @import with url(), background-image etc.
				'url("../' => 'url("'.$appendBefore.'../',
				"url('../" => "url('".$appendBefore.'../',
				'url(../'  => 'url('.$appendBefore.'../',

				'url("./'  => 'url("'.$appendBefore.'./',
				"url('./"  => "url('".$appendBefore.'./',
				'url(./'   => 'url('.$appendBefore.'./',

				// @import without URL
				'@import "../' => '@import "'.$appendBefore.'../',
				"@import '../" => "@import '".$appendBefore.'../',

				'@import "./'  => '@import "'.$appendBefore.'./',
				"@import './"  => "@import '".$appendBefore.'./'
			);

			$cssContent = str_replace(array_keys($cssContentPathReps), array_values($cssContentPathReps), $cssContent);

			// Rare cases
			$cssContent = preg_replace('/url\((\s+)http/i', 'url(http', $cssContent);

			// Avoid Background URLs starting with "#", "data", "http" or "https" as they do not need to have a path updated
			preg_match_all('/url\((?![\'"]?(?:#|data|http|https):)[\'"]?([^\'")]*)[\'"]?\)/i', $cssContent, $matches);

			// If it starts with forward slash (/), it doesn't need fix, just skip it
			// Also skip ../ types as they were already processed
			$toSkipList = array("url('/", 'url("/', 'url(/');

			foreach ($matches[0] as $match) {
				$fullUrlMatch = trim($match);

				foreach ($toSkipList as $toSkip) {
					if (substr($fullUrlMatch, 0, strlen($toSkip)) === $toSkip) {
						continue 2; // doesn't need any fix, go to the next match
					}
				}

				// Go through all situations: with and without quotes, with traversal directory (e.g. ../../)
				$alteredMatch = str_replace(
					array('url("', "url('"),
					array('url("' . $appendBefore, "url('" . $appendBefore),
					$fullUrlMatch
				);

				$alteredMatch = trim($alteredMatch);

				if (! in_array($fullUrlMatch[4], array("'", '"', '/', '.', '#'))) {
					$alteredMatch = str_replace('url(', 'url(' . $appendBefore, $alteredMatch);
					$alteredMatch = str_replace(array('")', '\')'), ')', $alteredMatch);
				}

				// Finally, apply the changes
				$cssContent = str_replace($fullUrlMatch, $alteredMatch, $cssContent);

				// Bug fix
				$cssContent = str_replace(
					array($appendBefore . '"' . $appendBefore, $appendBefore . "'" . $appendBefore),
					$appendBefore,
					$cssContent
				);

				// Bug Fix 2
				$cssContent = str_replace($appendBefore . 'http', 'http', $cssContent);
				$cssContent = str_replace($appendBefore . '//', '//', $cssContent);
			}
		}

		return $cssContent;
	}

	/**
	 * Next: Alter the HTML source by updating the original link URLs with the just cached ones
	 *
	 * @param $htmlSource
	 *
	 * @return mixed
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function updateHtmlSourceOriginalToOptimizedCss($htmlSource)
	{
		$parseSiteUrlPath = (string)parse_url(site_url(), PHP_URL_PATH);

		$siteUrlNoProtocol = str_replace(array('http://', 'https://'), '//', site_url());

		$cssOptimizeList = ObjectCache::wpacu_cache_get('wpacu_css_optimize_list') ?: array();

		$allEnqueuedCleanSources = ObjectCache::wpacu_cache_get('wpacu_css_enqueued_hrefs') ?: array();

		$allEnqueuedCleanSourcesIncludingTheirRelPaths = array();

		foreach ($allEnqueuedCleanSources as $allEnqueuedCleanSource) {
			$allEnqueuedCleanSourcesIncludingTheirRelPaths[] = $allEnqueuedCleanSource;

			if (strncmp($allEnqueuedCleanSource, 'http://', 7) === 0 || strncmp($allEnqueuedCleanSource, 'https://', 8) === 0) {
				$allEnqueuedCleanSourcesIncludingTheirRelPaths[] = str_replace(array('http://', 'https://'), '//', $allEnqueuedCleanSource);

				// e.g. www.mysite.com/blog/
				if ($parseSiteUrlPath !== '/' && strlen($parseSiteUrlPath) > 1) {
					$allEnqueuedCleanSourcesIncludingTheirRelPaths[] = $parseSiteUrlPath . str_replace(site_url(), '', $allEnqueuedCleanSource);
				}

				// e.g. www.mysite.com/
				if ($parseSiteUrlPath === '/' || ! $parseSiteUrlPath) {
					$allEnqueuedCleanSourcesIncludingTheirRelPaths[] = str_replace(site_url(), '', $allEnqueuedCleanSource);
				}
			}
		}

		$cdnUrls = OptimizeCommon::getAnyCdnUrls();
		$cdnUrlForCss = isset($cdnUrls['css']) ? $cdnUrls['css'] : false;

		// Grabs both LINK "stylesheet" and those with as="style" which is for preloaded LINK tags
		preg_match_all('#<link[^>]*(stylesheet|(as(\s+|)=(\s+|)(|"|\')style(|"|\')))[^>]*>#Umi', OptimizeCommon::cleanerHtmlSource( $htmlSource, array( 'for_fetching_link_tags' ) ), $matchesSourcesFromTags, PREG_SET_ORDER);

		if (empty($matchesSourcesFromTags)) {
			return $htmlSource;
		}

		$cssOptimizeListHardcoded = $linkTagsToUpdate = array();

		foreach ($matchesSourcesFromTags as $matches) {
			$linkSourceTag = $matches[0];

			if ($linkSourceTag === '' || strip_tags($linkSourceTag) !== '') {
				// Hmm? Not a valid tag... Skip it...
				continue;
			}

			// Check if the CSS has any 'data-wpacu-skip' attribute; if it does, do not alter it
			if (preg_match('#data-wpacu-skip([=>/ ])#i', $linkSourceTag)) {
				continue;
			}

			$cleanLinkHrefFromTagArray = OptimizeCommon::getLocalCleanSourceFromTag($linkSourceTag);

			// Skip external links, no point in carrying on
			if (! $cleanLinkHrefFromTagArray || ! is_array($cleanLinkHrefFromTagArray)) {
				continue;
			}

			// Is it a local CSS? Check if it's hardcoded (not enqueued the WordPress way)
			$cleanLinkHrefFromTag = $cleanLinkHrefFromTagArray['source'];

			$afterQuestionMark = $cleanLinkHrefFromTagArray['after_question_mark'];

			$isHardcodedDetected = false;

			if (! in_array($cleanLinkHrefFromTag, $allEnqueuedCleanSourcesIncludingTheirRelPaths)) {
				// Not in the final enqueued list? Most likely hardcoded (not added via wp_enqueue_scripts())
				// Emulate the object value (as the enqueued styles)
				$generatedHandle = md5($cleanLinkHrefFromTag);

				$value = (object)array(
					'handle' => $generatedHandle,
					'src'    => $cleanLinkHrefFromTag,
					'ver'    => md5($afterQuestionMark)
				);

				$optimizeValues = self::maybeOptimizeIt($value);
				ObjectCache::wpacu_cache_set('wpacu_maybe_optimize_it_css_'.$generatedHandle, $optimizeValues);

				if (! empty($optimizeValues)) {
					$isHardcodedDetected = true;
					$cssOptimizeListHardcoded[] = $optimizeValues;
				}
			}

            if ( ! $isHardcodedDetected ) {
				$listToParse = $cssOptimizeList;
			} else {
				$listToParse = $cssOptimizeListHardcoded;
			}

			if (empty($listToParse)) {
				continue;
			}

			foreach ($listToParse as $listValues) {
				// Index 0: Source URL (relative)
				// Index 1: New Optimized URL (relative)
				// Index 2: Source URL (as it is)

				// if the relative path from the WP root does not match the value of the source from the tag, do not continue
				// e.g. '/wp-content/plugins/my-plugin/script.js' has to be inside '<script src="/wp-content/plugins/my-plugin/script.js?ver=1.1"></script>'
				if (strpos($cleanLinkHrefFromTag, $listValues[0]) === false) {
					continue;
				}

				// The contents of the CSS file has been changed and thus, we will replace the source path from the original tag with the cached (e.g. minified) one

				// If the minified files are deleted (e.g. /wp-content/cache/ is cleared)
				// do not replace the CSS file path to avoid breaking the website
				$localPathOptimizedFile = rtrim(Misc::getWpRootDirPathBasedOnPath($listValues[1]), '/') . $listValues[1];

				if (! is_file($localPathOptimizedFile)) {
					continue;
				}

				// Make sure the source URL gets updated even if it starts with // (some plugins/theme strip the protocol when enqueuing assets)
				// If the first value fails to be replaced, the next one will be attempted for replacement
				// the order of the elements in the array is very important
				$sourceUrlList = array(
					site_url() . $listValues[0], // with protocol
					$siteUrlNoProtocol . $listValues[0], // without protocol
				);

				if ($parseSiteUrlPath && (strpos($listValues[0], $parseSiteUrlPath) === 0 || strpos($cleanLinkHrefFromTag, $parseSiteUrlPath) === 0)) {
					$sourceUrlList[] = $cleanLinkHrefFromTag;
				}

				if ($parseSiteUrlPath && strpos($cleanLinkHrefFromTag, $parseSiteUrlPath) === 0 && strpos($cleanLinkHrefFromTag, $listValues[0]) !== false) {
					$sourceUrlList[] = str_replace('//', '/', $parseSiteUrlPath.'/'.$listValues[0]);
				}
				elseif ( $cleanLinkHrefFromTag === $listValues[0] ) {
					$sourceUrlList[] = $listValues[0];
				}

				if ($cdnUrlForCss) {
					// Does it have a CDN?
					$sourceUrlList[] = OptimizeCommon::cdnToUrlFormat($cdnUrlForCss, 'rel') . $listValues[0];
				}

				// Any rel tag? You never know
				// e.g. <link src="/wp-content/themes/my-theme/style.css"></script>
				if ( (strncmp($listValues[2], '/', 1) === 0 && strncmp($listValues[2], '//', 2) !== 0)
					|| (strncmp($listValues[2], '/', 1) !== 0 &&
                        strncmp($listValues[2], '//', 2) !== 0 &&
                        strncasecmp($listValues[2], 'http://', 7) !== 0 &&
                        strncasecmp($listValues[2], 'https://', 8) !== 0) ) {
					$sourceUrlList[] = $listValues[2];

					}

				if ( $cleanLinkHrefFromTag === $listValues[0] ) {
					$sourceUrlList[] = $cleanLinkHrefFromTag;
				}

				// If no CDN is set, it will return site_url() as a prefix
				$optimizeUrl = OptimizeCommon::cdnToUrlFormat($cdnUrlForCss, 'raw') . $listValues[1]; // string

				if ($linkSourceTag !== str_replace($sourceUrlList, $optimizeUrl, $linkSourceTag)) {
					// Extra measure: Check the file size which should be 4 bytes, but add some margin error in case some environments will report less
					$isEmptyOptimizedFile = (strpos($localPathOptimizedFile, '-wpacu-empty-file.css') !== false && filesize($localPathOptimizedFile) < 10);

					// Strip it as its content (after optimization, for instance) is empty; no point in having extra HTTP requests
					if ($isEmptyOptimizedFile) {
						// Note: As for September 3, 2020, the inline CSS associated with the handle is no longer removed if the main CSS file is empty
						// There could be cases when the main CSS file is empty (e.g. theme's styling), but the inline STYLE tag associated with it has syntax that is needed

						$htmlSource       = str_replace($linkSourceTag, '', $htmlSource);

						} else {
						// Do the replacement
						$newLinkSourceTag = self::updateOriginalToOptimizedTag( $linkSourceTag, $sourceUrlList, $optimizeUrl );
						$linkTagsToUpdate[$linkSourceTag] = $newLinkSourceTag;
						}

					break; // there was a match, stop here
				}
			}
		}

		return strtr($htmlSource, $linkTagsToUpdate);
	}

	/**
	 * @param $linkSourceTag string
	 * @param $sourceUrlList array
	 * @param $optimizeUrl string
	 *
	 * @return array|string|string[]|null
	 */
	public static function updateOriginalToOptimizedTag($linkSourceTag, $sourceUrlList, $optimizeUrl)
	{
		if (is_array($sourceUrlList) && ! empty($sourceUrlList)) {
			foreach ($sourceUrlList as $sourceUrl) {
				$newLinkSourceTag = str_replace($sourceUrl, $optimizeUrl, $linkSourceTag);

				if ($newLinkSourceTag !== $linkSourceTag) {
					break;
				}
			}
		} else {
			$newLinkSourceTag = str_replace( $sourceUrlList, $optimizeUrl, $linkSourceTag );
		}

        if ( ! isset ($newLinkSourceTag) ) {
            return $linkSourceTag; // something's wrong with the params that were passed; return tghe original tag
        }

		// Needed in case it's added to the Combine CSS exceptions list
		if (self::proceedWithCssCombine()) {
			$sourceUrlRel = is_array($sourceUrlList) ? OptimizeCommon::getSourceRelPath($sourceUrlList[0]) : OptimizeCommon::getSourceRelPath($sourceUrlList);
			$newLinkSourceTag = str_ireplace('<link ', '<link data-wpacu-link-rel-href-before="'.$sourceUrlRel.'" ', $newLinkSourceTag);
		}

		$hrefValue = Misc::getValueFromTag($newLinkSourceTag);

		// No space from the matching and ? should be there
		if ($hrefValue && ( strpos( $hrefValue, ' ' ) === false )) {
			if ( strpos( $hrefValue, '?' ) !== false ) {
				// Strip things like ?ver=
				list( , $toStrip ) = explode( '?', $hrefValue );
				$toStrip            = '?' . trim( $toStrip );
				$newLinkSourceTag = str_replace( $toStrip, '', $newLinkSourceTag );
			}

			if ( strpos( $hrefValue, '&#038;ver' ) !== false ) {
				// Replace any .js&#038;ver with .js
				$toStrip = strrchr($hrefValue, '&#038;ver');
				$newLinkSourceTag = str_replace( $toStrip, '', $newLinkSourceTag );
			}
		}

		global $wp_version;

		$newLinkSourceTag = str_replace('.css&#038;ver='.$wp_version, '.css', $newLinkSourceTag);
		$newLinkSourceTag = str_replace('.css&#038;ver=', '.css', $newLinkSourceTag);

		return preg_replace('!\s+!', ' ', $newLinkSourceTag); // replace multiple spaces with only one space
	}

	/**
	 * @return bool
	 */
	public static function isInlineCssEnabled()
	{
		$isEnabledInSettingsWithListOrAuto = (Main::instance()->settings['inline_css_files'] &&
            (trim(Main::instance()->settings['inline_css_files_list']) !== '' || self::isAutoInlineEnabled()));

		if (! $isEnabledInSettingsWithListOrAuto) {
			return false;
		}

		// Deactivate it for debugging purposes via query string /?wpacu_no_inline_js
		if ( isset($_GET['wpacu_no_inline_css']) ) {
			return false;
		}

		// Finally, return true
		return true;
	}

	/**
	 * From LINK to STYLE tag: it processes the contents of the LINK stylesheet and replaces the tag with a STYLE tag having the content inlined
	 *
	 * @param $htmlSource
	 *
	 * @return mixed
	 */
	public static function doInline($htmlSource)
	{
        $skipTagsContaining = array(
            // Do not inline the admin bar SCRIPT file, saving resources as it's shown for the logged-in user only
            '/wp-includes/css/admin-bar',

            // They were preloaded for a reason, leave them
            'data-wpacu-preload-it-async=',
            'data-wpacu-to-be-preloaded-basic='
        );

		$allPatterns = self::getAllInlineChosenPatterns();

		// Skip any LINK tags within conditional comments (e.g. Internet Explorer ones)
		preg_match_all(
			'#<link[^>]*stylesheet[^>]*>#Umsi',
			OptimizeCommon::cleanerHtmlSource( $htmlSource, array( 'strip_content_between_conditional_comments', 'for_fetching_link_tags' ) ),
			$matchesSourcesFromTags,
			PREG_SET_ORDER
		);

		// In case automatic inlining is used
		$belowSizeInput = (int)Main::instance()->settings['inline_css_files_below_size_input'];

		if ($belowSizeInput === 0) {
			$belowSizeInput = 1; // needs to have a minimum value
		}

		if (! empty($matchesSourcesFromTags)) {
			$cdnUrls = OptimizeCommon::getAnyCdnUrls();
			$cdnUrlForCss = isset($cdnUrls['css']) ? trim($cdnUrls['css']) : false;

			foreach ($matchesSourcesFromTags as $matchList) {
				$matchedTag = $matchList[0];

				if (strncasecmp($matchedTag, '<link', 5) !== 0 ) {
					continue;
				}

                foreach ($skipTagsContaining as $skipTagContaining) {
                    if (strpos($matchedTag, $skipTagContaining) !== false) {
                        continue 2;
                    }
                }

				if (strip_tags($matchedTag) !== '') {
					continue; // something is funny, don't mess with the HTML alteration, leave it as it was
				}

				$chosenInlineCssMatches = false;

				// Condition #1: Only chosen (via textarea) CSS get inlined
				if ( false !== strpos( $matchedTag, ' wpacu-to-be-inlined' ) ) {
					$chosenInlineCssMatches = true;
				} elseif ( ! empty( $allPatterns ) ) {
					// Fallback, in case "wpacu-to-be-inlined" was not already added to the tag
					foreach ($allPatterns as $patternToCheck) {
						if (preg_match('#'.$patternToCheck.'#si', $matchedTag) || strpos($matchedTag, $patternToCheck) !== false) {
							$chosenInlineCssMatches = true;
							break;
						}
					}
				}

				// Is auto inline disabled and the chosen CSS does not match? Continue to the next LINK tag
				if (! $chosenInlineCssMatches && ! self::isAutoInlineEnabled()) {
					continue;
				}

				$linkHrefOriginal = Misc::getValueFromTag($matchedTag);
				$localAssetPath = OptimizeCommon::getLocalAssetPath($linkHrefOriginal, 'css');

				if (! $localAssetPath) {
					continue; // Not on the same domain
				}

				// Condition #2: Auto inline is enabled and there's no match for any entry in the textarea
				if (! $chosenInlineCssMatches && self::isAutoInlineEnabled()) {
					$fileSizeKb = number_format(filesize($localAssetPath) / 1024, 2);

					// If it's not smaller than the value from the input, do not continue with the inlining
					if ($fileSizeKb >= $belowSizeInput) {
						continue;
					}
				}

				// Is there a media attribute? Make sure to add it to the STYLE tag
				$mediaAttrValue = Misc::getValueFromTag($matchedTag, 'media');

				$mediaAttr = ($mediaAttrValue && $mediaAttrValue !== 'all') ? 'media=\''.$mediaAttrValue.'\'' : '';

				$appendBeforeAnyRelPath = $cdnUrlForCss ? OptimizeCommon::cdnToUrlFormat($cdnUrlForCss, 'raw') : '';

				$cssContent = self::maybeFixCssContent(
					FileSystem::fileGetContents($localAssetPath, 'combine_css_imports'), // CSS content
					$appendBeforeAnyRelPath . OptimizeCommon::getPathToAssetDir($linkHrefOriginal) . '/'
				);

				// The CSS file is read from its original plugin/theme/cache location
				// If minify was enabled, then it's already minified, no point in re-minify it to save resources
				// Changing paths (relative) to fonts, images, etc. are relevant in this case
				$cssContent = self::maybeAlterContentForCssFile($cssContent, false);

				if ($cssContent && $cssContent !== '/**/') {
					$htmlSource = str_replace(
						$matchedTag,
						'<style '.Misc::getStyleTypeAttribute().' '.$mediaAttr.' data-wpacu-inline-css-file=\'1\'>'."\n".$cssContent."\n".'</style>',
						$htmlSource
					);
				} else {
					// After CSS alteration (e.g. minify), there's no content left; most likely the CSS file contained only comments, elements without any syntax or empty spaces
					// Strip the tag completely as there's no reason to print an empty SCRIPT tag to further add to the total DOM elements
					$htmlSource = str_replace($matchedTag, '', $htmlSource);
				}
			}
		}

		return $htmlSource;
	}

	/**
	 * This applies to both inline and static JS files contents
	 *
	 * @param $cssContent
	 * @param bool $doCssMinify (false by default as it could be already minified or non-minify type)
	 * @param array $extraParams
	 *
	 * @return mixed|string|string[]|null
	 */
	public static function maybeAlterContentForCssFile($cssContent, $doCssMinify = false, $extraParams = array())
	{
		if (! trim($cssContent)) {
			return $cssContent;
		}

		/* [START] Change CSS Content */
		// Move any @imports to the top of the content; This also strips any @imports to Google Fonts if the option is chosen
		$cssContent = self::importsUpdate( $cssContent );

		if (in_array('alter_font_face', $extraParams)) {
			$cssContent = apply_filters( 'wpacu_local_fonts_display_css_output', $cssContent, Main::instance()->settings['local_fonts_display'] );
		}

		if ( $doCssMinify ) {
			$cssContent = MinifyCss::applyMinification( $cssContent, $doCssMinify );
		}

		if ( Main::instance()->settings['google_fonts_remove'] ) {
			$cssContent = FontsGoogleRemove::cleanFontFaceReferences( $cssContent );
		}

		// Does it have a source map? Strip it
		if (strpos($cssContent, '/*# sourceMappingURL=') !== false) {
			$cssContent = OptimizeCommon::stripSourceMap($cssContent, 'css');
		}
		/* [END] Change CSS Content */

		return $cssContent;
	}

	/**
	 * @param $cssContent
	 * @param bool $doCssMinify
	 * @param array $extraParams
	 *
	 * @return mixed|string
	 */
	public static function maybeAlterContentForInlineStyleTag($cssContent, $doCssMinify = false, $extraParams = array())
	{
		if (! trim($cssContent)) {
			return $cssContent;
		}

		$useCacheForInlineStyle = true;

		if (mb_strlen($cssContent) > 500000) { // Bigger then ~500KB? Skip alteration
			return $cssContent;
		}

		if (mb_strlen($cssContent) < 40000) { // Smaller than ~40KB? Do not cache it
			$useCacheForInlineStyle = false;
		}

		// For debugging purposes
		if (isset($_GET['wpacu_no_cache']) || wpacuIsDefinedConstant('WPACU_NO_CACHE')) {
            $useCacheForInlineStyle = false;
        }

		if ($useCacheForInlineStyle) {
			// Anything in the cache? Take it from there and don't spend resources with the minification
			// (which in some environments uses the CPU, depending on the complexity of the JavaScript code) and any other alteration
			$cssContentBeforeHash = sha1( $cssContent );

			$pathToInlineCssOptimizedItem = WP_CONTENT_DIR . self::getRelPathCssCacheDir() . '/item/inline/' . $cssContentBeforeHash . '.css';

			// Check if the file exists before moving forward
			if ( is_file( $pathToInlineCssOptimizedItem ) ) {
				$cachedCssFileExpiresIn = OptimizeCommon::$cachedAssetFileExpiresIn;

				if ( filemtime( $pathToInlineCssOptimizedItem ) < ( time() - $cachedCssFileExpiresIn ) ) {
					// Has the caching period expired? Remove the file as a new one has to be generated
					@unlink( $pathToInlineCssOptimizedItem );
				} else {
					// Not expired / Return its content from the cache in a faster way
					$inlineCssStorageItemJsonContent = trim( FileSystem::fileGetContents( $pathToInlineCssOptimizedItem ) );

					if ( $inlineCssStorageItemJsonContent !== '' ) {
						return $inlineCssStorageItemJsonContent;
					}
				}
			}
		}

		/* [START] Change CSS Content */
		if ( $doCssMinify && in_array('just_minify', $extraParams) ) {
			$cssContent = MinifyCss::applyMinification( $cssContent, $useCacheForInlineStyle );
		} else {
			// Move any @imports to top; This also strips any @imports to Google Fonts if the option is chosen
			$cssContent = self::importsUpdate( $cssContent );

			if ( $doCssMinify ) {
				$cssContent = MinifyCss::applyMinification( $cssContent, $useCacheForInlineStyle );
			}

			if ( Main::instance()->settings['google_fonts_remove'] ) {
				$cssContent = FontsGoogleRemove::cleanFontFaceReferences( $cssContent );
			}
		}
		/* [END] Change CSS Content */

		if ($useCacheForInlineStyle && isset($pathToInlineCssOptimizedItem)) {
			// Store the optimized content to the cached CSS file which would be read quicker
			FileSystem::filePutContents( $pathToInlineCssOptimizedItem, $cssContent );
		}

		return $cssContent;
	}

	/**
	 * @return bool
	 */
	public static function isAutoInlineEnabled()
	{
		return Main::instance()->settings['inline_css_files'] &&
		       Main::instance()->settings['inline_css_files_below_size'] &&
		       (int)Main::instance()->settings['inline_css_files_below_size_input'] > 0;
	}

	/**
	 * Source: https://www.minifier.org/ | https://github.com/matthiasmullie/minify
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public static function importsUpdate($content)
	{
		if (preg_match_all('/(;?)(@import (?<url>url\()?(?P<quotes>["\']?).+?(?P=quotes)(?(url)\)));?/', $content, $matches)) {
			// Remove from content (they will be appended to the top if they qualify)
			foreach ($matches[0] as $import) {
				$content = str_replace($import, '', $content);
			}

			// Strip any @imports to Google Fonts if it's the case
			$importsAddToTop = Main::instance()->settings['google_fonts_remove'] ? FontsGoogleRemove::stripGoogleApisImport($matches[2]) : $matches[2];

			// Add it to the top if there are any imports left
			if (! empty($importsAddToTop)) {
				$content = implode(';', $importsAddToTop) . ';' . trim($content, ';');
			}
		}

		return $content;
	}

	/**
	 * @param string $returnType
	 *
	 * @return array|bool
     * @noinspection NestedPositiveIfStatementsInspection
     */
	public static function isOptimizeCssEnabledByOtherParty($returnType = 'list')
	{
		$pluginsToCheck = array(
			'autoptimize/autoptimize.php'            => 'Autoptimize',
			'wp-rocket/wp-rocket.php'                => 'WP Rocket',
			'wp-fastest-cache/wpFastestCache.php'    => 'WP Fastest Cache',
			'w3-total-cache/w3-total-cache.php'      => 'W3 Total Cache',
			'sg-cachepress/sg-cachepress.php'        => 'SG Optimizer',
			'fast-velocity-minify/fvm.php'           => 'Fast Velocity Minify',
			'litespeed-cache/litespeed-cache.php'    => 'LiteSpeed Cache',
			'swift-performance-lite/performance.php' => 'Swift Performance Lite',
			'breeze/breeze.php'                      => 'Breeze – WordPress Cache Plugin'
		);

		$cssOptimizeEnabledIn = array();

		foreach ($pluginsToCheck as $plugin => $pluginTitle) {
			// "Autoptimize" check
			if ($plugin === 'autoptimize/autoptimize.php' && wpacuIsPluginActive($plugin) && get_option('autoptimize_css')) {
				$cssOptimizeEnabledIn[] = $pluginTitle;

				if ($returnType === 'if_enabled') { return true; }
			}

			// "WP Rocket" check
			if ($plugin === 'wp-rocket/wp-rocket.php' && wpacuIsPluginActive($plugin)) {
				if (function_exists('get_rocket_option')) {
					$wpRocketMinifyCss = trim(get_rocket_option('minify_css')) ?: false;
					$wpRocketMinifyConcatenateCss = trim(get_rocket_option('minify_concatenate_css')) ?: false;
				} else {
					$wpRocketSettings  = get_option('wp_rocket_settings');
					$wpRocketMinifyCss = isset($wpRocketSettings['minify_css']) && trim($wpRocketSettings['minify_css']);
					$wpRocketMinifyConcatenateCss = isset($wpRocketSettings['minify_concatenate_css']) && trim($wpRocketSettings['minify_concatenate_css']);
				}

				if ($wpRocketMinifyCss || $wpRocketMinifyConcatenateCss) {
					$cssOptimizeEnabledIn[] = $pluginTitle;

					if ($returnType === 'if_enabled') { return true; }
				}
			}

			// "WP Fastest Cache" check
			if ($plugin === 'wp-fastest-cache/wpFastestCache.php' && wpacuIsPluginActive($plugin)) {
				$wpfcOptionsJson = get_option('WpFastestCache');
				$wpfcOptions = @json_decode($wpfcOptionsJson, ARRAY_A);

				if (isset($wpfcOptions['wpFastestCacheMinifyCss']) || isset($wpfcOptions['wpFastestCacheCombineCss'])) {
					$cssOptimizeEnabledIn[] = $pluginTitle;

					if ($returnType === 'if_enabled') { return true; }
				}
			}

			// "W3 Total Cache" check
			if ($plugin === 'w3-total-cache/w3-total-cache.php' && wpacuIsPluginActive($plugin)) {
				$w3tcConfigMaster = Misc::getW3tcMasterConfig();
				$w3tcEnableCss = (int)trim(Misc::extractBetween($w3tcConfigMaster, '"minify.css.enable":', ','), '" ');

				if ($w3tcEnableCss === 1) {
					$cssOptimizeEnabledIn[] = $pluginTitle;

					if ($returnType === 'if_enabled') { return true; }
				}
			}

			// "SG Optimizer" check
			if ($plugin === 'sg-cachepress/sg-cachepress.php' && wpacuIsPluginActive($plugin)) {
				if (class_exists('\SiteGround_Optimizer\Options\Options')
				    && method_exists('\SiteGround_Optimizer\Options\Options', 'is_enabled')
				    && @\SiteGround_Optimizer\Options\Options::is_enabled('siteground_optimizer_combine_css')) {
					$cssOptimizeEnabledIn[] = $pluginTitle;
					if ($returnType === 'if_enabled') { return true; }
				}
			}

			// "Fast Velocity Minify" check
			if ($plugin === 'fast-velocity-minify/fvm.php' && wpacuIsPluginActive($plugin)) {
				// It's enough if it's active due to its configuration
				$cssOptimizeEnabledIn[] = $pluginTitle;

				if ($returnType === 'if_enabled') { return true; }
			}

			// "LiteSpeed Cache" check
			if ($plugin === 'litespeed-cache/litespeed-cache.php' && wpacuIsPluginActive($plugin) && ($liteSpeedCacheConf = apply_filters('litespeed_cache_get_options', get_option('litespeed-cache-conf')))) {
				if ( (isset($liteSpeedCacheConf['css_minify']) && $liteSpeedCacheConf['css_minify'])
				     || (isset($liteSpeedCacheConf['css_combine']) && $liteSpeedCacheConf['css_combine']) ) {
					$cssOptimizeEnabledIn[] = $pluginTitle;

					if ($returnType === 'if_enabled') { return true; }
				}
			}

			// "Swift Performance Lite" check
			if ($plugin === 'swift-performance-lite/performance.php' &&
                class_exists('Swift_Performance_Lite') &&
                method_exists('Swift_Performance_Lite', 'check_option') &&
                wpacuIsPluginActive($plugin)) {
				if ( @\Swift_Performance_Lite::check_option('merge-styles', 1) ) {
					$cssOptimizeEnabledIn[] = $pluginTitle;
				}

				if ($returnType === 'if_enabled') { return true; }
			}

			// "Breeze – WordPress Cache Plugin"
			if ($plugin === 'breeze/breeze.php' && wpacuIsPluginActive($plugin)) {
				$breezeBasicSettings    = get_option('breeze_basic_settings');
				$breezeAdvancedSettings = get_option('breeze_advanced_settings');

				if (isset($breezeBasicSettings['breeze-minify-css'], $breezeAdvancedSettings['breeze-group-css'])
				    && $breezeBasicSettings['breeze-minify-css'] && $breezeAdvancedSettings['breeze-group-css']) {
					$cssOptimizeEnabledIn[] = $pluginTitle;

					if ($returnType === 'if_enabled') { return true; }
				}
			}
		}

		if ($returnType === 'if_enabled') { return false; }

		return $cssOptimizeEnabledIn;
	}

	/**
	 * @return bool
	 */
	public static function isWpRocketOptimizeCssDeliveryEnabled()
	{
		if (wpacuIsPluginActive('wp-rocket/wp-rocket.php')) {
			if (function_exists('get_rocket_option')) {
				$wpRocketAsyncCss = trim(get_rocket_option('async_css')) ?: false;
			} else {
				$wpRocketSettings  = get_option('wp_rocket_settings');
				$wpRocketAsyncCss = isset($wpRocketSettings['async_css']) && trim($wpRocketSettings['async_css']);
			}

			return $wpRocketAsyncCss;
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public static function wpfcMinifyCssEnabledOnly()
	{
		if (wpacuIsPluginActive('wp-fastest-cache/wpFastestCache.php')) {
			$wpfcOptionsJson = get_option('WpFastestCache');
			$wpfcOptions     = @json_decode($wpfcOptionsJson, ARRAY_A);

			// "Minify CSS" is enabled, "Combine CSS" is disabled
			return isset($wpfcOptions['wpFastestCacheMinifyCss']) && ! isset($wpfcOptions['wpFastestCacheCombineCss']);
		}

		return false;
	}

	/**
	 * @param $htmlSource
	 *
	 * @return mixed
	 */
	public static function ignoreDependencyRuleAndKeepChildrenLoaded($htmlSource)
	{
		$ignoreChild = Main::instance()->getIgnoreChildren();

		if ( ! empty($ignoreChild['styles']) ) {
			foreach (array_keys($ignoreChild['styles']) as $styleHandle) {
				// Always load the Dashicons if the top admin bar (toolbar) is shown
				if ($styleHandle === 'dashicons' && is_admin_bar_showing()) {
					continue;
				}

				if (isset(Main::instance()->wpAllStyles['registered'][$styleHandle]->src, Main::instance()->ignoreChildren['styles'][$styleHandle.'_has_unload_rule']) && Main::instance()->wpAllStyles['registered'][$styleHandle]->src && Main::instance()->ignoreChildren['styles'][$styleHandle.'_has_unload_rule']) {
					if ($scriptExtraAfterHtml = self::generateInlineAssocHtmlForHandle($styleHandle)) {
						$htmlSource = str_replace($scriptExtraAfterHtml, '', $htmlSource);
					}

					$listWithMatches   = array();
					$listWithMatches[] = 'data-wpacu-style-handle=[\'"]'.$styleHandle.'[\'"]';

					if ($styleSrc = Main::instance()->wpAllStyles['registered'][$styleHandle]->src) {
						$listWithMatches[] = OptimizeCommon::getSourceRelPath($styleSrc);
					}

					$htmlSource = CleanUp::cleanLinkTagFromHtmlSource($listWithMatches, $htmlSource);
				}
			}
		}

		return $htmlSource;
	}

	/**
	 * @param $styleTagOrHandle
	 * @param $wpacuRegisteredStyles
	 * @param $from
	 * @param string $return ("value": CSS Inline Content / "html": CSS Inline Content surrounded by tags)
	 *
	 * @return array
	 */
	public static function getInlineAssociatedWithLinkHandle($styleTagOrHandle, $wpacuRegisteredStyles, $from = 'tag', $return = 'value')
	{
		$styleExtraAfter = '';

		if ($from === 'tag') {
			preg_match_all('#data-wpacu-style-handle=([\'])' . '(.*)' . '(\1)#Usmi', $styleTagOrHandle, $outputMatches);
			$styleHandle = (isset($outputMatches[2][0]) && $outputMatches[2][0]) ? trim($outputMatches[2][0], '"\'') : '';
		} else {
			$styleHandle = $styleTagOrHandle;
		}

		if ($return === 'value' && $styleHandle && isset($wpacuRegisteredStyles[$styleHandle]->extra)) {
			$styleExtraArray = $wpacuRegisteredStyles[$styleHandle]->extra;

			if (isset($styleExtraArray['after']) && ! empty($styleExtraArray['after'])) {
				$styleExtraAfter .= "<style id='".$styleHandle."-inline-css' ".Misc::getStyleTypeAttribute().">\n";

				foreach ($styleExtraArray['after'] as $afterData) {
					if (! is_bool($afterData)) {
						$styleExtraAfter .= $afterData."\n";
					}
				}

				$styleExtraAfter .= '</style>';
			}

			return array('after' => $styleExtraAfter);
		}

		if ( $return === 'html' && $styleHandle ) {
			// 'after' is the only one for inline CSS; there's no 'data' or 'before' like in the inline JS
			return array('after' => self::generateInlineAssocHtmlForHandle($styleHandle));
		}

		return array('after' => array());
	}

	/**
	 * @param $handle
	 * @param $inlineStyleContent
	 *
	 * @return string
	 */
	public static function generateInlineAssocHtmlForHandle($handle, $inlineStyleContent = '')
	{
		global $wp_styles;

		if ( ! $inlineStyleContent ) {
			$inlineStyleContent = $wp_styles->print_inline_style( $handle, false );
		}

		$output = '';

		if ( $inlineStyleContent ) {
			$output = sprintf(
				"<style id='%s-inline-css'%s>\n%s\n</style>",
				esc_attr( $handle ),
				Misc::getStyleTypeAttribute(),
				$inlineStyleContent
			);
		}

		return $output;
	}

	/**
	 * @param $htmlSource
	 *
	 * @return array|string|string[]
	 */
	public function appendNoScriptCertainLinkTags($htmlSource)
	{
		preg_match_all('#<link[^>]*(data-wpacu-preload-it-async)[^>]*(>)#Umi', $htmlSource, $matchesSourcesFromTags, PREG_SET_ORDER);

		$noScripts = '';

		if (! empty($matchesSourcesFromTags)) {
			foreach ($matchesSourcesFromTags as $matchedValues) {
				$matchedTag = $matchedValues[0];

				$mediaAttrValue = Misc::getValueFromTag($matchedTag, 'media');
				$hrefAttrValue  = Misc::getValueFromTag($matchedTag);

				$noScripts .= '<noscript><link rel="stylesheet" href="'.$hrefAttrValue.'" media="'.$mediaAttrValue.'" /></noscript>'."\n";
			}
		}

		return str_replace(self::MOVE_NOSCRIPT_TO_BODY_FOR_CERTAIN_LINK_TAGS, $noScripts, $htmlSource);
	}

    /**
     * @return bool
     */
    public static function proceedWithCssCombine()
    {
        // Not on query string request (debugging purposes)
        if ( ! empty($_REQUEST) && array_key_exists('wpacu_no_css_combine', $_REQUEST) ) {
            return false;
        }

        // No CSS files are combined in the Dashboard
        // Always in the front-end view
        // Do not combine if there's a POST request as there could be assets loading conditionally
        // that might not be needed when the page is accessed without POST, making the final CSS file larger
        if (! empty($_POST) || is_admin()) {
            return false; // Do not combine
        }

        // Only clean request URIs allowed (with Exceptions)
        // Exceptions
        if ((strpos($_SERVER['REQUEST_URI'], '?') !== false) && ! OptimizeCommon::loadOptimizedAssetsIfQueryStrings()) {
            return false;
        }

        if (! OptimizeCommon::doCombineIsRegularPage()) {
            return false;
        }

        $pluginSettings = Main::instance()->settings;

        if ($pluginSettings['test_mode'] && ! Menu::userCanAccessAssetCleanUp()) {
            return false; // Do not combine anything if "Test Mode" is ON and the user is in guest mode (not logged-in)
        }

        if ($pluginSettings['combine_loaded_css'] === '') {
            return false; // Do not combine
        }

        if (self::isOptimizeCssEnabledByOtherParty('if_enabled')) {
            return false; // Do not combine (it's already enabled in other plugins)
        }

        // "Minify HTML" from WP Rocket is sometimes stripping combined LINK tags
        // Better uncombined then missing essential CSS files
        if (Misc::isWpRocketMinifyHtmlEnabled()) {
            return false;
        }

        /*
            // The option is no longer used since v1.1.7.3 (Pro) & v1.3.6.4 (Lite)
            if ( ($pluginSettings['combine_loaded_css'] === 'for_admin'
                  || $pluginSettings['combine_loaded_css_for_admin_only'] == 1)
                 && Menu::userCanAccessAssetCleanUp()) {

                return true; // Do combine
            }
        */

        // "Apply it only for guest visitors (default)" is set; Do not combine if the user is logged in
        if ( $pluginSettings['combine_loaded_css_for'] === 'guests' && is_user_logged_in() ) {
            return false;
        }

        if (in_array($pluginSettings['combine_loaded_css'], array('for_all', 1)) ) {
            return true; // Do combine
        }

        // Finally, return false as none of the verification above matched
        return false;
    }

	}
