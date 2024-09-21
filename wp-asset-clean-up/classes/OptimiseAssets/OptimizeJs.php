<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\FileSystem;
use WpAssetCleanUp\CleanUp;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\MainFront;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\ObjectCache;
use WpAssetCleanUp\Preloads;

/**
 * Class OptimizeJs
 * @package WpAssetCleanUp
 */
class OptimizeJs
{
	/**
	 *
	 */
	public function init()
	{
		add_action( 'wp_print_footer_scripts', static function() {
			/* [wpacu_timing] */ Misc::scriptExecTimer( 'prepare_optimize_files_js' ); /* [/wpacu_timing] */
            // Are both Minify and Cache Dynamic JS disabled? No point in continuing and using extra resources as there is nothing to change
            if ( ! OptimizeCommon::isWorthCheckingForJsOptimization() || OptimizeCommon::preventAnyFrontendOptimization() ) {
                return;
            }

			self::prepareOptimizeList();
			/* [wpacu_timing] */ Misc::scriptExecTimer( 'prepare_optimize_files_js', 'end' ); /* [/wpacu_timing] */
		}, PHP_INT_MAX );
	}

	/**
	 *
	 */
	public static function prepareOptimizeList()
	{
		global $wp_scripts;

		$jsOptimizeList = array();

		$wpScriptsDone  = isset( $wp_scripts->done ) && is_array( $wp_scripts->done ) ? $wp_scripts->done : array();
		$wpScriptsQueue = isset( $wp_scripts->queue ) && is_array( $wp_scripts->queue ) ? $wp_scripts->queue : array();

		$wpScriptsList = array_unique( array_merge( $wpScriptsDone, $wpScriptsQueue ) );

		// Collect all enqueued clean (no query strings) HREFs to later compare them against any hardcoded JS
		$allEnqueuedCleanScriptSrcs = array();

		// [Start] Collect for caching
		if ( ! empty( $wpScriptsList ) ) {
			$isMinifyJsFilesEnabled = in_array( Main::instance()->settings['minify_loaded_js_for'], array( 'src', 'all', '' ) )
			                          && MinifyJs::isMinifyJsEnabled();

			foreach ( $wpScriptsList as $index => $scriptHandle ) {
				if ( isset( Main::instance()->wpAllScripts['registered'][ $scriptHandle ]->src ) && ( $src = Main::instance()->wpAllScripts['registered'][ $scriptHandle ]->src ) ) {
					$localAssetPath = OptimizeCommon::getLocalAssetPath( $src, 'js' );

					if ( ! $localAssetPath ) {
						continue; // not a local file
					}

					if ( ! $scriptSourceTag = ObjectCache::wpacu_cache_get( 'wpacu_script_loader_tag_' . $scriptHandle ) ) {
						ob_start();
						$wp_scripts->do_item( $scriptHandle );
						$scriptSourceTag = trim( ob_get_clean() );
					}

					// Check if the JS has any 'data-wpacu-skip' attribute; if it does, do not alter it
					if ( preg_match( '#data-wpacu-skip([=>/ ])#i', $scriptSourceTag ) ) {
						unset( $wpScriptsList[ $index ] );
						continue;
					}

					$cleanScriptSrcFromTagArray = OptimizeCommon::getLocalCleanSourceFromTag( $scriptSourceTag );

					if ( isset( $cleanScriptSrcFromTagArray['source'] ) && $cleanScriptSrcFromTagArray['source'] ) {
						$allEnqueuedCleanScriptSrcs[] = $cleanScriptSrcFromTagArray['source'];
					}

					$optimizeValues = self::maybeOptimizeIt(
						Main::instance()->wpAllScripts['registered'][ $scriptHandle ],
						array(
							'local_asset_path'     => $localAssetPath,
							'is_minify_js_enabled' => $isMinifyJsFilesEnabled
						)
					);

					ObjectCache::wpacu_cache_set( 'wpacu_maybe_optimize_it_js_' . $scriptHandle, $optimizeValues );

					if ( ! empty( $optimizeValues ) ) {
						$jsOptimizeList[] = $optimizeValues;
					}
				}
			}

			ObjectCache::wpacu_cache_add( 'wpacu_js_enqueued_srcs', $allEnqueuedCleanScriptSrcs );
			ObjectCache::wpacu_cache_add( 'wpacu_js_optimize_list', $jsOptimizeList );
		}
		// [End] Collect for caching
	}

	/**
	 * @param $value
	 * @param array $fileAlreadyChecked
	 *
	 * @return array
	 */
	public static function maybeOptimizeIt($value, $fileAlreadyChecked = array())
	{
		if ($optimizeValues = ObjectCache::wpacu_cache_get('wpacu_maybe_optimize_it_js_'.$value->handle)) {
			return $optimizeValues;
		}

		global $wp_version;

		$src = $value->src; // it's always set at this point

		$doFileMinify = true;

		$isMinifyJsFilesEnabled = (isset($fileAlreadyChecked['is_minify_js_enabled']) && $fileAlreadyChecked['is_minify_js_enabled'])
			? $fileAlreadyChecked['is_minify_js_enabled']
			: in_array(Main::instance()->settings['minify_loaded_js_for'], array('src', 'all', '')) && MinifyJs::isMinifyJsEnabled();

		if ( ! $isMinifyJsFilesEnabled || MinifyJs::skipMinify($src, $value->handle) ) {
			$doFileMinify = false;
		}

		// Default (it will be later replaced with the last time the file was modified, which is more accurate)
		$dbVer = (isset($value->ver) && $value->ver) ? $value->ver : $wp_version;

		$isJsFile = false;

		// Already checked? Do not reuse OptimizeCommon::getLocalAssetPath() and is_file()
		if (isset($fileAlreadyChecked['local_asset_path']) && $fileAlreadyChecked['local_asset_path']) {
			$localAssetPath = $fileAlreadyChecked['local_asset_path'];
		} else {
			$localAssetPath = OptimizeCommon::getLocalAssetPath( $src, 'js' );
		}

		$checkCond = $localAssetPath;

		if ($checkCond) {
			if ($fileMTime = @filemtime($localAssetPath)) {
				$dbVer = $fileMTime;
			}
			$isJsFile = true;
		}

		if ($isJsFile) {
			// This is the safest one as handle names for specific static can change on every page load
			// as some developers have a habit of adding the UNIX time or other random string to a handle (e.g. for debugging)
			$uniqueAssetStr = md5 ( str_replace(Misc::getWpRootDirPathBasedOnPath($localAssetPath), '', $localAssetPath) );
		} else {
			$uniqueAssetStr = md5( $value->handle );
		}

		$transientName = 'wpacu_js_optimize_'.$uniqueAssetStr;

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
					$localPathToJsOptimized = str_replace( '//', '/', Misc::getWpRootDirPathBasedOnPath($savedValuesArray['optimize_uri']) . $savedValuesArray['optimize_uri'] );

					// Do not load any minified JS file (from the database transient cache) if it doesn't exist
					// It will fallback to the original JS file
					if ( isset( $savedValuesArray['source_uri'] ) && is_file( $localPathToJsOptimized ) ) {
						if ( Main::instance()->settings['fetch_cached_files_details_from'] === 'db_disk' ) {
							$GLOBALS['wpacu_from_location_inc']++;
						}

						return array(
							$savedValuesArray['source_uri'],
							$savedValuesArray['optimize_uri'],
							$src,
							$value->handle
						);
					}
				}

				// If nothing valid gets returned above, make sure the transient gets deleted as it's re-added later on
				OptimizeCommon::deleteTransient($transientName);
			}
		}

		// Check if it starts without "/" or a protocol; e.g. "wp-content/theme/script.js"
		if (strncmp($src, '/', 1) !== 0 &&
            strncmp($src, '//', 2) !== 0 &&
            strncasecmp($src, 'http://', 7) !== 0 &&
            strncasecmp($src, 'https://', 8) !== 0
		) {
			$src = '/'.$src; // append the forward slash to be processed as relative later on
		}

        $src = Misc::getHrefFromSource($src);

		/*
		 * [START] JS Content Optimization
		*/
		if (Main::instance()->settings['cache_dynamic_loaded_js'] &&
		    ((strpos($src, '/?') !== false) || strpos($src, '.php?') !== false || Misc::endsWith($src, '.php')) &&
		    (strpos($src, site_url()) !== false)
		) {
			$pathToAssetDir = '';
			$sourceBeforeOptimization = $value->src;

			if (! ($jsContent = DynamicLoadedAssets::getAssetContentFrom('dynamic', $value))) {
				return array();
			}
		} else {
			if (! $isJsFile) {
				return array();
			}

			/*
			 * This is a local .JS file
			 */
			$pathToAssetDir = OptimizeCommon::getPathToAssetDir($value->src);
			$sourceBeforeOptimization = str_replace(Misc::getWpRootDirPathBasedOnPath($localAssetPath), '/', $localAssetPath);

			$jsContent = FileSystem::fileGetContents($localAssetPath);
		}

		$hadToBeMinified = false;

		$jsContent = trim($jsContent);

		// If it stays like this, it means there is content there, even if only comments
		$jsContentBecomesEmptyAfterMin = false;

		if ( $doFileMinify && $jsContent ) { // only bother to minify it if it has any content, save resources
			// Minify this file?
			$jsContentBeforeMin = $jsContent;
			$jsContentAfterMin  = MinifyJs::applyMinification($jsContentBeforeMin);

			$jsContent = $jsContentAfterMin;

			if ( $jsContentAfterMin === '' ) {
				// It had content, but became empty after minification, most likely it had only comments (e.g. a default child theme's style)
				$jsContentBecomesEmptyAfterMin = true;
			} else {
				$jsContentCompare     = md5(trim( $jsContentBeforeMin, '; ' ));
				$jsContentCompareWith = md5(trim( $jsContentAfterMin, '; ' ));

				if ( $jsContentCompare !== $jsContentCompareWith ) {
					$hadToBeMinified = true;
				}
			}
		}

		if ( $jsContentBecomesEmptyAfterMin || $jsContent === '' ) {
			$jsContent = '/**/';
		} else {
			$jsContentArray = self::maybeAlterContentForJsFile( $jsContent );
			$jsContent = $jsContentArray['content']; // resulting content after alteration
			$jsContentAfterAlterToCompare = $jsContentArray['content_after_alter_to_compare'];

			if ( $isJsFile && ( ! $hadToBeMinified ) ) {
				$jsContentCompare     = md5(trim( $jsContent, '; ' ));
				$jsContentCompareWith = md5(trim( $jsContentAfterAlterToCompare, '; ' ));

				if ( $jsContentCompare === $jsContentCompareWith ) {
					// 1: The file was not minified
					// 2: It doesn't need any alteration (e.g. no Google Fonts to strip from its content)
					OptimizeCommon::setTransient($transientName, 'no_alter');
					return array();
				}
			}

			// Change the necessary relative paths before the file is copied to the caching directory (e.g. /wp-content/cache/asset-cleanup/)
			$jsContent = self::maybeDoJsFixes( $jsContent, $pathToAssetDir . '/' );
		}
		/*
		 * [END] JS Content Optimization
		*/

		if (isset($jsContentBeforeMin) && $jsContent === '/**/' && strpos($jsContentBeforeMin, '/*@cc_on') !== false && strpos($jsContentBeforeMin, '@*/') !== false) {
			OptimizeCommon::setTransient($transientName, 'no_alter');
			return array(); // Internet Explorer things, leave the file as it is
		}

		// Relative path to the new file
		// Save it to /wp-content/cache/js/{OptimizeCommon::$optimizedSingleFilesDir}/
		$fileVer = sha1($jsContent);

		$uniqueCachedAssetName = OptimizeCommon::generateUniqueNameForCachedAsset($isJsFile, $localAssetPath, $value->handle, $fileVer);

		$newFilePathUri  = self::getRelPathJsCacheDir() . OptimizeCommon::$optimizedSingleFilesDir . '/' . $uniqueCachedAssetName;
		$newFilePathUri .= '.js';

		if ($jsContent === '') {
			$jsContent = '/**/';
		}

		if ($jsContent === '/**/') {
			// Leave a signature that the file is empty, thus it would be faster to take further actions upon it later on, saving resources)
			$newFilePathUri = str_replace('.js', '-wpacu-empty-file.js', $newFilePathUri);
		}

		$newLocalPath    = WP_CONTENT_DIR . $newFilePathUri; // Ful Local path
		$newLocalPathUrl = WP_CONTENT_URL . $newFilePathUri; // Full URL path

		if ($jsContent && $jsContent !== '/**/' && apply_filters('wpacu_print_info_comments_in_cached_assets', true)) {
			$jsContent = '/*!' . $sourceBeforeOptimization . '*/' . "\n" . $jsContent;
		}

		$saveFile = FileSystem::filePutContents($newLocalPath, $jsContent);

		if (! $saveFile || ! $jsContent) {
			// Fallback to the original JS if the optimized version can't be created or updated
			return array();
		}

		$saveOutput = OptimizeCommon::getSourceRelPath($src) . "\n" .
		              OptimizeCommon::getSourceRelPath($newLocalPathUrl) . "\n" .
		              $dbVer;

		// Add / Re-add (with new version) transient
		OptimizeCommon::setTransient($transientName, $saveOutput);

		return array(
			OptimizeCommon::getSourceRelPath($value->src), // Original SRC (Relative path)
			OptimizeCommon::getSourceRelPath($newLocalPathUrl), // New SRC (Relative path)
			$value->src, // SRC (as it is)
			$value->handle
		);
	}

	/**
	 * This applies to both inline and static JS files contents
	 *
	 * @param $jsContent
	 * @param bool $doJsMinify (false by default as it could be already minified or non-minify type)
	 *
	 * @return array
	 */
	public static function maybeAlterContentForJsFile($jsContent, $doJsMinify = false)
	{
		if (! trim($jsContent)) { // No Content! Return it as it, no point in doing extra checks
			return array('content' => $jsContent);
		}

		$jsContentBefore = $jsContent;

		/* [START] Change JS Content */
		if ($doJsMinify) {
			$jsContent = MinifyJs::applyMinification($jsContent);
		}

		if (Main::instance()->settings['google_fonts_remove']) {
			$jsContent = FontsGoogleRemove::stripReferencesFromJsCode($jsContent);
		} elseif (Main::instance()->settings['google_fonts_display']) {
			// Perhaps "display" parameter has to be applied to Google Font Links if they are active
			$jsContent = FontsGoogle::alterGoogleFontUrlFromJsContent($jsContent);
		}
		/* [END] Change JS Content */

		// Does it have a source map? Strip it only if any optimization was already applied
		// As, otherwise, there's no point in creating a caching file, since there are no changes worth made to the file
		if (($jsContentBefore !== $jsContent) && (strpos($jsContent, '// #sourceMappingURL') !== false) && Misc::endsWith(trim($jsContent), '.map')) {
			$jsContent = OptimizeCommon::stripSourceMap($jsContent, 'js');
		}

		$jsContentAfterAlterToCompare = $jsContent; // new possible values
		return array('content' => $jsContent , 'content_after_alter_to_compare' => $jsContentAfterAlterToCompare);
	}

	/**
	 * @param $jsContent
	 * @param $doJsMinify
	 *
	 * @return false|mixed|string|string[]|null
	 */
	public static function maybeAlterContentForInlineScriptTag($jsContent, $doJsMinify)
	{
		if (! trim($jsContent)) { // No Content! Return it as it, no point in doing extra checks
			return $jsContent;
		}

		if (mb_strlen($jsContent) > 500000) { // Bigger then ~500KB? Skip alteration for this inline SCRIPT
			return $jsContent;
		}

		$useCacheForInlineScript = true;

		if (mb_strlen($jsContent) < 40000) { // Smaller than ~40KB? Do not cache it
			$useCacheForInlineScript = false;
		}

		// For debugging purposes
		if (isset($_GET['wpacu_no_cache']) || wpacuIsDefinedConstant('WPACU_NO_CACHE')) {
            $useCacheForInlineScript = false;
        }

		if ($useCacheForInlineScript) {
			// Anything in the cache? Take it from there and don't spend resources with the minification
			// (which in some environments uses the CPU, depending on the complexity of the JavaScript code) and any other alteration
			$jsContentBeforeHash = sha1( $jsContent );

			$pathToInlineJsOptimizedItem = WP_CONTENT_DIR . self::getRelPathJsCacheDir() . '/item/inline/' . $jsContentBeforeHash . '.js';

			// Check if the file exists before moving forward
			if ( is_file( $pathToInlineJsOptimizedItem ) ) {
				$cachedJsFileExpiresIn = OptimizeCommon::$cachedAssetFileExpiresIn;

				if ( filemtime( $pathToInlineJsOptimizedItem ) < ( time() - $cachedJsFileExpiresIn ) ) {
					// Has the caching period expired? Remove the file as a new one has to be generated
					@unlink( $pathToInlineJsOptimizedItem );
				} else {
					// Not expired / Return its content from the cache in a faster way
					$inlineJsStorageItemJsonContent = FileSystem::fileGetContents( $pathToInlineJsOptimizedItem );

					if ( $inlineJsStorageItemJsonContent !== '' ) {
						return $inlineJsStorageItemJsonContent;
					}
				}
			}
		}

		/* [START] Change JS Content */
		if ($doJsMinify) {
			$jsContent = MinifyJs::applyMinification($jsContent);
		}

		if (Main::instance()->settings['google_fonts_remove']) {
			$jsContent = FontsGoogleRemove::stripReferencesFromJsCode($jsContent);
		} elseif (Main::instance()->settings['google_fonts_display']) {
			// Perhaps "display" parameter has to be applied to Google Font Links if they are active
			$jsContent = FontsGoogle::alterGoogleFontUrlFromJsContent($jsContent);
		}
		/* [END] Change JS Content */

		if ( $useCacheForInlineScript && isset($pathToInlineJsOptimizedItem) ) {
			// Store the optimized content to the cached JS file which would be read quicker
			FileSystem::filePutContents( $pathToInlineJsOptimizedItem, $jsContent );
		}

		return $jsContent;
	}

	/**
	 * @param $htmlSource
	 *
	 * @return string
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function updateHtmlSourceOriginalToOptimizedJs($htmlSource)
	{
		$parseSiteUrlPath = (string)parse_url(site_url(), PHP_URL_PATH);

		$siteUrlNoProtocol = str_replace(array('http://', 'https://'), '//', site_url());

		$jsOptimizeList = ObjectCache::wpacu_cache_get('wpacu_js_optimize_list') ?: array();
		$allEnqueuedCleanSources = ObjectCache::wpacu_cache_get('wpacu_js_enqueued_srcs') ?: array();

		$allEnqueuedCleanSourcesIncludingTheirRelPaths = array();

		foreach ($allEnqueuedCleanSources as $allEnqueuedCleanSource) {
			$allEnqueuedCleanSourcesIncludingTheirRelPaths[] = $allEnqueuedCleanSource;

			if (strncmp($allEnqueuedCleanSource, 'http://', 7) === 0 || strncmp($allEnqueuedCleanSource, 'https://',
                    8) === 0) {
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
		$cdnUrlForJs = isset($cdnUrls['js']) ? $cdnUrls['js'] : false;

		preg_match_all('#(<script[^>]*src(|\s+)=(|\s+)[^>]*>)|(<link[^>]*(as(\s+|)=(\s+|)(|"|\')script(|"|\'))[^>]*>)#Umi', $htmlSource, $matchesSourcesFromTags, PREG_SET_ORDER);

		if (empty($matchesSourcesFromTags)) {
			return $htmlSource;
		}

		$jsOptimizeListHardcoded = $scriptTagsToUpdate = array();

		foreach ($matchesSourcesFromTags as $matches) {
			$scriptSourceTag = $matches[0];

			if ($scriptSourceTag === '' || strip_tags($scriptSourceTag) !== '') {
				// Hmm? Not a valid tag... Skip it...
				continue;
			}

			// Check if the JS has any 'data-wpacu-skip' attribute; if it does, do not alter it
			if (preg_match('#data-wpacu-skip([=>/ ])#i', $scriptSourceTag)) {
				continue;
			}

			$cleanScriptSrcFromTagArray = OptimizeCommon::getLocalCleanSourceFromTag($scriptSourceTag);

			// Skip external links, no point in carrying on
			if (! $cleanScriptSrcFromTagArray || ! is_array($cleanScriptSrcFromTagArray)) {
				continue;
			}

			$forAttr = 'src';

			// Any preloads for the optimized script?
			// e.g. <link rel='preload' as='script' href='...' />
			if (strpos($scriptSourceTag, '<link') !== false) {
				$forAttr = 'href';
			}

			// Is it a local JS? Check if it's hardcoded (not enqueued the WordPress way)
			$cleanScriptSrcFromTag      = $cleanScriptSrcFromTagArray['source'];
			$afterQuestionMark          = $cleanScriptSrcFromTagArray['after_question_mark'];

			$isHardcodedDetected = false;

			if (! in_array($cleanScriptSrcFromTag, $allEnqueuedCleanSourcesIncludingTheirRelPaths)) {
				// Not in the final enqueued list? Most likely hardcoded (not added via wp_enqueue_scripts())
				// Emulate the object value (as the enqueued scripts)
				$generatedHandle = md5($cleanScriptSrcFromTag);

				$value = (object)array(
					'handle' => $generatedHandle,
					'src'    => $cleanScriptSrcFromTag,
					'ver'    => md5($afterQuestionMark)
				);

				$optimizeValues = self::maybeOptimizeIt($value);
				ObjectCache::wpacu_cache_set('wpacu_maybe_optimize_it_js_'.$generatedHandle, $optimizeValues);

				if (! empty($optimizeValues)) {
					$isHardcodedDetected = true;
					$jsOptimizeListHardcoded[] = $optimizeValues;
				}
			}

			if ( ! $isHardcodedDetected ) {
				$listToParse = $jsOptimizeList;
			} else {
				$listToParse = $jsOptimizeListHardcoded;
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
				if (strpos($cleanScriptSrcFromTag, $listValues[0]) === false) {
					continue;
				}

				// The contents of the CSS file has been changed and thus, we will replace the source path from original tag with the cached (e.g. minified) one

				// If the minified files are deleted (e.g. /wp-content/cache/ is cleared)
				// do not replace the JS file path to avoid breaking the website
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

				if ($parseSiteUrlPath && (strpos($listValues[0], $parseSiteUrlPath) === 0 || strpos($cleanScriptSrcFromTag, $parseSiteUrlPath) === 0)) {
					$sourceUrlList[] = $cleanScriptSrcFromTag;
				}

				if ($parseSiteUrlPath && strpos($cleanScriptSrcFromTag, $parseSiteUrlPath) === 0 && strpos($cleanScriptSrcFromTag, $listValues[0]) !== false) {
					$sourceUrlList[] = str_replace('//', '/', $parseSiteUrlPath.'/'.$listValues[0]);
				}
				elseif ( $cleanScriptSrcFromTag === $listValues[0] ) {
					$sourceUrlList[] = $listValues[0];
				}

				if ($cdnUrlForJs) {
					// Does it have a CDN?
					$sourceUrlList[] = OptimizeCommon::cdnToUrlFormat($cdnUrlForJs, 'rel') . $listValues[0];
				}

				// Any rel tag? You never know
				// e.g. <script src="/wp-content/themes/my-theme/script.js"></script>
				if ( (strncmp($listValues[2], '/', 1) === 0 && strncmp($listValues[2], '//', 2) !== 0)
				     || (strncmp($listValues[2], '/', 1) !== 0 &&
                         strncmp($listValues[2], '//', 2) !== 0 &&
                         strncasecmp($listValues[2], 'http://', 7) !== 0 &&
                         strncasecmp($listValues[2], 'https://', 8) !== 0) ) {
					$sourceUrlList[] = $listValues[2];
				}

				if ( $cleanScriptSrcFromTag === $listValues[0] ) {
					$sourceUrlList[] = $cleanScriptSrcFromTag;
				}

				// If no CDN is set, it will return site_url() as a prefix
				$optimizeUrl = OptimizeCommon::cdnToUrlFormat($cdnUrlForJs, 'raw') . $listValues[1]; // string

				if ($scriptSourceTag !== str_replace($sourceUrlList, $optimizeUrl, $scriptSourceTag)) {
					// Extra measure: Check the file size which should be 4 bytes, but add some margin error in case some environments will report less
					$isEmptyOptimizedFile = (strpos($localPathOptimizedFile, '-wpacu-empty-file.js') !== false && filesize($localPathOptimizedFile) < 10);

					if ($isEmptyOptimizedFile) {
						// Strip it as its content (after optimization, for instance) is empty; no point in having extra HTTP requests
						if ($forAttr === 'src') {
							$scriptTagsToUpdate[ $scriptSourceTag . '</script>' ] = '';
						} else {
							$scriptTagsToUpdate[ $scriptSourceTag ] = ''; // LINK (e.g. preload of a JS file)
						}
						// Note: As for September 3, 2020, the inline JS associated with the handle is no longer removed if the main JS file is empty
						// There could be cases when the main JS file is empty, but the inline JS tag associated with it has code that is needed

						} else {
						$newScriptSourceTag = self::updateOriginalToOptimizedTag( $scriptSourceTag, $sourceUrlList, $optimizeUrl, $forAttr );
						$scriptTagsToUpdate[$scriptSourceTag] = $newScriptSourceTag;
					}

					break;
				}
			}
		}

		return strtr($htmlSource, $scriptTagsToUpdate);
	}

	/**
	 * @param $scriptSourceTag string
	 * @param $sourceUrlList array
	 * @param $optimizeUrl string
	 * @param string $forAttr ('src' (default), or 'href' if it's preloaded)
	 *
	 * @return string
	 */
	public static function updateOriginalToOptimizedTag($scriptSourceTag, $sourceUrlList, $optimizeUrl, $forAttr = 'src')
	{
		if (is_array($sourceUrlList) && ! empty($sourceUrlList)) {
			foreach ($sourceUrlList as $sourceUrl) {
				$newScriptSourceTag = str_replace($sourceUrl, $optimizeUrl, $scriptSourceTag);

				if ($newScriptSourceTag !== $scriptSourceTag) {
					break;
				}
			}
		} else {
			$newScriptSourceTag = str_replace( $sourceUrlList, $optimizeUrl, $scriptSourceTag );
		}

        if ( ! isset($newScriptSourceTag) ) {
            return $scriptSourceTag; // something's wrong with the params that were passed; return tghe original tag
        }

		$tagToCheck = ($forAttr === 'src') ? 'script' : 'link';

		$sourceUrlRel = is_array($sourceUrlList) ? OptimizeCommon::getSourceRelPath($sourceUrlList[0]) : OptimizeCommon::getSourceRelPath($sourceUrlList);
		$newScriptSourceTag = str_ireplace('<'.$tagToCheck.' ', '<'.$tagToCheck.' data-wpacu-script-rel-src-before="'.$sourceUrlRel.'" ', $newScriptSourceTag);

		$sourceValue = Misc::getValueFromTag($scriptSourceTag);

		// No space from the matching and ? should be there
		if ($sourceValue && ( strpos( $sourceValue, ' ' ) === false )) {
			if ( strpos( $sourceValue, '?' ) !== false ) {
				// Strip things like ?ver=
				list( , $toStrip ) = explode( '?', $sourceValue );
				$toStrip            = '?' . trim( $toStrip );
				$newScriptSourceTag = str_replace( $toStrip, '', $newScriptSourceTag );
			}

			if ( strpos( $sourceValue, '&#038;ver' ) !== false ) {
				// Replace any .js&#038;ver with .js
				$toStrip = strrchr($sourceValue, '&#038;ver');
				$newScriptSourceTag = str_replace( $toStrip, '', $newScriptSourceTag );
			}
		}

		global $wp_version;

		$newScriptSourceTag = str_replace('.js&#038;ver='.$wp_version, '.js', $newScriptSourceTag);
		$newScriptSourceTag = str_replace('.js&#038;ver=', '.js', $newScriptSourceTag);

		return preg_replace('!\s+!', ' ', $newScriptSourceTag); // replace multiple spaces with only one space
	}

	/**
	 * @param $htmlSource
	 *
	 * @return mixed|void
	 */
	public static function alterHtmlSource($htmlSource)
	{
		// There has to be at least one "<script", otherwise, it could be a feed request or something similar (not page, post, homepage etc.)
		if (isset($_GET['wpacu_no_optimize_js']) || stripos($htmlSource, '<script') === false ) {
			return $htmlSource;
		}

		/* [wpacu_timing] */ Misc::scriptExecTimer( 'alter_html_source_for_optimize_js' ); /* [/wpacu_timing] */

		if ( ! Main::instance()->preventAssetsSettings() ) {
			/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_unload_ignore_deps_js'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
			// Are there any assets unloaded where their "children" are ignored?
			// Since they weren't dequeued the WP way (to avoid unloading the "children"), they will be stripped here
			$htmlSource = self::ignoreDependencyRuleAndKeepChildrenLoaded($htmlSource);
			/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

			// Move any jQuery inline SCRIPT that is triggered before jQuery library is called through "jquery-core" handle
			if (Main::instance()->settings['move_inline_jquery_after_src_tag']) {
				/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_move_inline_jquery_after_src_tag'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
				$htmlSource = self::moveInlinejQueryAfterjQuerySrc($htmlSource);
				/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */
			}
		}

		/*
		 * The JavaScript files only get cached if they are minified or are loaded like /?custom-js=version - /script.php?ver=1 etc.
		 * #optimizing
		 * STEP 2: Load optimize-able caching list and replace the original source URLs with the new cached ones
		 */

		/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_original_to_optimized_js'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
		// At least minify or cache dynamically loaded JS has to be enabled to proceed
		if (OptimizeCommon::isWorthCheckingForJsOptimization()) {
			// 'wpacu_js_optimize_list' caching list is also checked; if it's empty, no optimization is made
			$htmlSource = self::updateHtmlSourceOriginalToOptimizedJs($htmlSource);
		}
		/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_preload_js'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
		if ( ! wpacuIsDefinedConstant('WPACU_NO_ASSETS_PRELOADED') && ! Main::instance()->preventAssetsSettings() ) {
			$preloads = Preloads::instance()->getPreloads();

			if ( ! empty($preloads['scripts']) ) {
				$htmlSource = Preloads::appendPreloadsForScriptsToHead($htmlSource);
			}
		}
		/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_combine_js'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
        if (self::proceedWithJsCombine()) {
            $proceedWithCombineOnThisPage = true;

            $isSingularPage = (int)wpacuGetConstant('WPACU_CURRENT_PAGE_ID') && MainFront::isSingularPage();

            // If "Do not combine JS on this page" is checked in "Asset CleanUp Options" side meta box
            // Works for posts, pages and custom post types
            if ($isSingularPage || MainFront::isHomePage()) {
                if ($isSingularPage) {
                    $pageOptions = MetaBoxes::getPageOptions(WPACU_CURRENT_PAGE_ID); // Singular page
                } else {
                    $pageOptions = MetaBoxes::getPageOptions(0, 'front_page'); // Home page
                }

                // 'no_js_optimize' refers to avoid the combination of JS files
                if ((isset($pageOptions['no_js_optimize']) && $pageOptions['no_js_optimize'])
                    || (isset($pageOptions['no_assets_settings']) && $pageOptions['no_assets_settings'])) {
                    $proceedWithCombineOnThisPage = false;
                }
            }

            if ($proceedWithCombineOnThisPage) {
                /* [wpacu_timing] */ // Note: Load timing is checked within the method /* [/wpacu_timing] */
                $htmlSource = CombineJs::doCombine($htmlSource);
                if (wpacuIsDefinedConstant('WPACU_REAPPLY_PRELOADING_FOR_COMBINED_JS')) {
                    $htmlSource = Preloads::appendPreloadsForScriptsToHead($htmlSource);
                }
            }
        }
		/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_minify_inline_script_tags'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
        if ( OptimizeCommon::isWorthCheckingForJsOptimization() &&
		     ! Main::instance()->preventAssetsSettings() ) {
			$htmlSource = MinifyJs::minifyOrAlterInlineScriptTags($htmlSource);
		}
		/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		// Final cleanups
		/* [wpacu_timing] */ $wpacuTimingName = 'alter_html_source_for_optimize_js_final_cleanups'; Misc::scriptExecTimer($wpacuTimingName); /* [/wpacu_timing] */
		if ( ! wpacuIsDefinedConstant('WPACU_NO_ASSETS_PRELOADED') ) {
            $htmlSource = str_replace(Preloads::DEL_SCRIPTS_PRELOADS, '', $htmlSource);
        }

        if (strpos($htmlSource, 'data-wpacu-script-rel-src-before') !== false) {
            $htmlSource = preg_replace('# data-wpacu-script-rel-src-before="' . '(.*)' . '" #Usm', ' ', $htmlSource);
        }

        $htmlSource = preg_replace(
            '#<script(|\s+)(data-wpacu-jquery-core-handle=1|data-wpacu-jquery-migrate-handle=1|)(|\s+)data-wpacu-script-handle=\'(.*)\'#Umi',
            '<script',
            $htmlSource
        );

		// Clear possible empty SCRIPT tags (e.g. left from associated 'before' and 'after' tags after their content was stripped)
		$htmlSource = preg_replace('#<script(\s+|)(type=\'text/javascript\'|)(\s+|)>(\s+|)</script>#Umi', '', $htmlSource);
		/* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

		/* [wpacu_timing] */ Misc::scriptExecTimer('alter_html_source_for_optimize_js', 'end'); /* [/wpacu_timing] */
		return $htmlSource;
	}

	/**
	 * @return string
	 */
	public static function getRelPathJsCacheDir()
	{
		return OptimizeCommon::getRelPathPluginCacheDir().'js/'; // keep trailing slash at the end
	}

	/**
	 * @param $scriptSrcs
	 * @param $htmlSource
	 *
	 * @return array
	 */
	public static function getScriptTagsFromSrcs($scriptSrcs, $htmlSource)
	{
		$scriptTags = array();

		foreach ($scriptSrcs as $scriptSrc) {
			$scriptSrc = str_replace('{site_url}', '', $scriptSrc);

			// Clean it up for the preg_quote() call
			if (strpos($scriptSrc, '.js?') !== false) {
				list($scriptSrc,) = explode('.js?', $scriptSrc);
				$scriptSrc .= '.js';
			}

			preg_match_all('#<script[^>]*src(|\s+)=(|\s+)[^>]*'. preg_quote($scriptSrc, '/'). '.*(>)(.*|)</script>#Usmi', $htmlSource, $matchesFromSrc, PREG_SET_ORDER);

			if (isset($matchesFromSrc[0][0]) && strip_tags($matchesFromSrc[0][0]) === '') {
				$scriptTags[] = trim($matchesFromSrc[0][0]);
			}
		}

		return $scriptTags;
	}

	/**
	 * @param $jsContent
	 * @param $appendBefore
	 *
	 * @return string
	 */
	public static function maybeDoJsFixes($jsContent, $appendBefore)
	{
		// Relative URIs for CSS Paths
		// For code such as:
		// $(this).css("background", "url('../images/image-1.jpg')");

		$jsContentPathReps = array(
			'url("../' => 'url("'.$appendBefore.'../',
			"url('../" => "url('".$appendBefore.'../',
			'url(../'  => 'url('.$appendBefore.'../',

			'url("./'  => 'url("'.$appendBefore.'./',
			"url('./"  => "url('".$appendBefore.'./',
			'url(./'   => 'url('.$appendBefore.'./'
		);

		$jsContent = str_replace(array_keys($jsContentPathReps), array_values($jsContentPathReps), $jsContent);

		$jsContent = trim($jsContent);

		if (substr($jsContent, -1) !== ';') {
			$jsContent .= "\n" . ';'; // add semicolon as the last character
		}

		return $jsContent;
	}

	/**
	 * @param $htmlSource
	 *
	 * @return false|mixed|string|void
	 */
	public static function moveInlinejQueryAfterjQuerySrc($htmlSource)
	{
		if (stripos($htmlSource, '<script') === false || ! Misc::isDOMDocumentOn()) {
			return $htmlSource;
		}

		$domTag = OptimizeCommon::getDomLoadedTag($htmlSource, 'moveInlinejQueryAfterjQuerySrc');

		$scriptTagsObj = $domTag->getElementsByTagName( 'script' );

		if ($scriptTagsObj === null) {
			return $htmlSource;
		}

		// Does it have the "src" attribute? Skip it as it's not an inline SCRIPT tag
		$jQueryPatternsToMatch = array(
			'jQuery',
			'\$(\s+|)\((\s+|)document(\s+|)\)(\s+|).(\s+|)ready(\s+|)\('
		);

		$jQueryRegExp = '#' . implode('|', $jQueryPatternsToMatch) . '#si';

		$jQueryCoreDel    = 'data-wpacu-jquery-core-handle=';
		$jQueryMigrateDel = 'data-wpacu-jquery-migrate-handle=';

		if (strpos($htmlSource, $jQueryMigrateDel) !== false) {
			$collectUntil = $jQueryMigrateDel;
		} elseif (strpos($htmlSource, $jQueryCoreDel) !== false) {
			$collectUntil = $jQueryCoreDel;
		} else {
			return $htmlSource; // No jQuery or jQuery Migrate? Just return the HTML source
		}

		$inlineBeforejQuerySrc = array();

		foreach ($scriptTagsObj as $scriptTagObj) {
			$tagContents = $scriptTagObj->nodeValue;

			if ( strpos( Misc::getOuterHTML( $scriptTagObj ), $collectUntil) !== false) {
				break;
			}

			if ($tagContents !== '' && preg_match($jQueryRegExp, $tagContents)) {
				preg_match('#<script[^>]*>'.preg_quote($tagContents, '/').'</script>#si', $htmlSource, $matchesExact);
				$exactMatchTag = isset($matchesExact[0]) ? $matchesExact[0] : '';

				// Replace the first match only in rare cases there are multiple SCRIPT tags with the same code
				if ($exactMatchTag && ($pos = strpos($htmlSource, $exactMatchTag)) !== false) {
					$inlineBeforejQuerySrc[] = $exactMatchTag;
					$htmlSource = substr_replace($htmlSource, '', $pos, strlen($exactMatchTag));
				}
			}
		}

		preg_match('#<script* '.$collectUntil.'*[^>]*>(.*?)</script>#si', $htmlSource, $matches);

		if (! empty($inlineBeforejQuerySrc) && $collectUntil && isset($matches[0])) {
			$htmlSource = preg_replace('#<script* '.$collectUntil.'*[^>]*>(.*?)</script>#si', $matches[0]."\n".implode("\n", $inlineBeforejQuerySrc), $htmlSource);
		}

		return $htmlSource;
	}

	/**
	 * @param string $returnType
	 * 'list' - will return the list of plugins that have JS optimization enabled
	 * 'if_enabled' - will stop when it finds the first one (any order) and return true
	 *
	 * @return array|bool
     * @noinspection NestedPositiveIfStatementsInspection
     */
	public static function isOptimizeJsEnabledByOtherParty($returnType = 'list')
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

		$jsOptimizeEnabledIn = array();

		foreach ($pluginsToCheck as $plugin => $pluginTitle) {
			// "Autoptimize" check
			if ($plugin === 'autoptimize/autoptimize.php' && wpacuIsPluginActive($plugin) && get_option('autoptimize_js')) {
				$jsOptimizeEnabledIn[] = $pluginTitle;

				if ($returnType === 'if_enabled') { return true; }
			}

			// "WP Rocket" check
			if ($plugin === 'wp-rocket/wp-rocket.php' && wpacuIsPluginActive($plugin)) {
				if (function_exists('get_rocket_option')) {
					$wpRocketMinifyJs = get_rocket_option('minify_js');
					$wpRocketMinifyConcatenateJs = get_rocket_option('minify_concatenate_js');
				} else {
					$wpRocketSettings  = get_option('wp_rocket_settings');
					$wpRocketMinifyJs = isset($wpRocketSettings['minify_js']) && $wpRocketSettings['minify_js'];
					$wpRocketMinifyConcatenateJs = isset($wpRocketSettings['minify_concatenate_js']) && $wpRocketSettings['minify_concatenate_js'];
				}

				if ($wpRocketMinifyJs || $wpRocketMinifyConcatenateJs) {
					$jsOptimizeEnabledIn[] = $pluginTitle;

					if ($returnType === 'if_enabled') { return true; }
				}
			}

			// "WP Fastest Cache" check
			if ($plugin === 'wp-fastest-cache/wpFastestCache.php' && wpacuIsPluginActive($plugin)) {
				$wpfcOptionsJson = get_option('WpFastestCache');
				$wpfcOptions = @json_decode($wpfcOptionsJson, ARRAY_A);

				if (isset($wpfcOptions['wpFastestCacheMinifyJs']) || isset($wpfcOptions['wpFastestCacheCombineJs'])) {
					$jsOptimizeEnabledIn[] = $pluginTitle;

					if ($returnType === 'if_enabled') { return true; }
				}
			}

			// "W3 Total Cache" check
			if ($plugin === 'w3-total-cache/w3-total-cache.php' && wpacuIsPluginActive($plugin)) {
				$w3tcConfigMaster = Misc::getW3tcMasterConfig();
				$w3tcEnableJs = (int)trim(Misc::extractBetween($w3tcConfigMaster, '"minify.js.enable":', ','), '" ');

				if ($w3tcEnableJs === 1) {
					$jsOptimizeEnabledIn[] = $pluginTitle;

					if ($returnType === 'if_enabled') { return true; }
				}
			}

			// "SG Optimizer" check
			if ($plugin === 'sg-cachepress/sg-cachepress.php' && wpacuIsPluginActive($plugin)) {
				if (class_exists('\SiteGround_Optimizer\Options\Options') && method_exists('\SiteGround_Optimizer\Options\Options', 'is_enabled')) {
					if (@\SiteGround_Optimizer\Options\Options::is_enabled( 'siteground_optimizer_optimize_javascript')) {
						$jsOptimizeEnabledIn[] = $pluginTitle;

						if ($returnType === 'if_enabled') { return true; }
					}
				}
			}

			// "Fast Velocity Minify" check
			if ($plugin === 'fast-velocity-minify/fvm.php' && wpacuIsPluginActive($plugin)) {
				// It's enough if it's active due to its configuration
				$jsOptimizeEnabledIn[] = $pluginTitle;

				if ($returnType === 'if_enabled') { return true; }
			}

			// "LiteSpeed Cache" check
			if ($plugin === 'litespeed-cache/litespeed-cache.php' && wpacuIsPluginActive($plugin) && ($liteSpeedCacheConf = apply_filters('litespeed_cache_get_options', get_option('litespeed-cache-conf')))) {
				if ( (isset($liteSpeedCacheConf['js_minify']) && $liteSpeedCacheConf['js_minify'])
				     || (isset($liteSpeedCacheConf['js_combine']) && $liteSpeedCacheConf['js_combine']) ) {
					$jsOptimizeEnabledIn[] = $pluginTitle;

					if ($returnType === 'if_enabled') { return true; }
				}
			}

			// "Swift Performance Lite" check
			if ($plugin === 'swift-performance-lite/performance.php' &&
                class_exists('Swift_Performance_Lite') &&
                method_exists('Swift_Performance_Lite', 'check_option') &&
                wpacuIsPluginActive($plugin)) {
				if ( @\Swift_Performance_Lite::check_option('merge-scripts', 1) ) {
					$jsOptimizeEnabledIn[] = $pluginTitle;
				}

				if ($returnType === 'if_enabled') { return true; }
			}

			// "Breeze – WordPress Cache Plugin"
			if ($plugin === 'breeze/breeze.php' && wpacuIsPluginActive($plugin)) {
				$breezeBasicSettings    = get_option('breeze_basic_settings');
				$breezeAdvancedSettings = get_option('breeze_advanced_settings');

				if (isset($breezeBasicSettings['breeze-minify-js'], $breezeAdvancedSettings['breeze-group-js'])
				    && $breezeBasicSettings['breeze-minify-js'] && $breezeAdvancedSettings['breeze-group-js']) {
					$jsOptimizeEnabledIn[] = $pluginTitle;

					if ($returnType === 'if_enabled') { return true; }
				}
			}
		}

		if ($returnType === 'if_enabled') { return false; }

		return $jsOptimizeEnabledIn;
	}

	/**
	 * @param $htmlSource
	 *
	 * @return mixed
	 */
	public static function ignoreDependencyRuleAndKeepChildrenLoaded($htmlSource)
	{
		$ignoreChild = Main::instance()->getIgnoreChildren();

		if ( ! empty($ignoreChild['scripts']) ) {
			foreach (array_keys($ignoreChild['scripts']) as $scriptHandle) {
				if (isset(Main::instance()->wpAllScripts['registered'][$scriptHandle]->src, Main::instance()->ignoreChildren['scripts'][$scriptHandle.'_has_unload_rule']) && ($scriptSrc = Main::instance()->wpAllScripts['registered'][$scriptHandle]->src) && Main::instance()->ignoreChildren['scripts'][$scriptHandle.'_has_unload_rule']) {
					$toReplaceTagList = array();

					// If the handle has any inline JavaScript associated with it (before or after the tag), make sure it's stripped as well
					if ($translationsContent = self::generateInlineAssocHtmlForHandle($scriptHandle, 'translations')) {
						$toReplaceTagList[] = $translationsContent;
					}

					if ($cDataContent = self::generateInlineAssocHtmlForHandle($scriptHandle, 'data')) {
						$toReplaceTagList[] = $cDataContent;
					}

					if ($beforeContent = self::generateInlineAssocHtmlForHandle($scriptHandle, 'before')) {
						$toReplaceTagList[] = $beforeContent;
					}

					$toReplaceTagList[] = self::getScriptTagFromHandle(array('data-wpacu-script-handle=[\'"]' . $scriptHandle . '[\'"]'), $htmlSource);

					if ($afterContent = self::generateInlineAssocHtmlForHandle($scriptHandle, 'after')) {
						$toReplaceTagList[] = $afterContent;
					}

					$htmlSource = str_replace($toReplaceTagList, '', $htmlSource);

					// Extra, in case the previous replacement didn't go through
					$listWithMatches   = array();
					$listWithMatches[] = 'data-wpacu-script-handle=[\'"]'.$scriptHandle.'[\'"]';
					$listWithMatches[] = OptimizeCommon::getSourceRelPath($scriptSrc);

					$htmlSource = CleanUp::cleanScriptTagFromHtmlSource($listWithMatches, $htmlSource);
				}
			}
		}

		return $htmlSource;
	}


	/**
	 * This would fetch the content of the SCRIPT tag (data, before & after)
	 *
	 * @param $scriptTagOrHandle
	 * @param $wpacuRegisteredScripts
	 * @param string $from
	 * @param string $return ("value": JS Inline Content / "html": JS Inline Content surrounded by tags)
	 *
	 * @return array
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function getInlineAssociatedWithScriptHandle($scriptTagOrHandle, $wpacuRegisteredScripts, $from = 'tag', $return = 'value')
	{
		if ($from === 'tag') {
			preg_match_all('#data-wpacu-script-handle=([\'])' . '(.*)' . '(\1)#Usmi', $scriptTagOrHandle, $outputMatches);
			$scriptHandle = (isset($outputMatches[2][0]) && $outputMatches[2][0]) ? trim($outputMatches[2][0], '"\'') : '';
		} else { // 'handle'
			$scriptHandle = $scriptTagOrHandle;
		}

		if ( $return === 'value' && $scriptHandle ) {
			$scriptExtraCdata = $scriptExtraBefore = $scriptExtraAfter = '';

			if (isset($wpacuRegisteredScripts[$scriptHandle]->extra)) {
				$scriptExtraArray = $wpacuRegisteredScripts[ $scriptHandle ]->extra;

				if ( isset( $scriptExtraArray['data'] ) && $scriptExtraArray['data'] ) {
					$scriptExtraCdata .= $scriptExtraArray['data'] . "\n";
				}

				if ( ! empty( $scriptExtraArray['before'] ) ) {
					foreach ( $scriptExtraArray['before'] as $beforeData ) {
						if ( ! is_bool( $beforeData ) ) {
							$scriptExtraBefore .= $beforeData . "\n";
						}
					}
				}

				if ( ! empty( $scriptExtraArray['after'] ) ) {
					foreach ( $scriptExtraArray['after'] as $afterData ) {
						if ( ! is_bool( $afterData ) ) {
							$scriptExtraAfter .= $afterData . "\n";
						}
					}
				}
			}

			$scriptTranslations = '';

			if (method_exists('wp_scripts', 'print_translations')) {
				$scriptTranslations = wp_scripts()->print_translations( $scriptHandle, false );
			}

			return array(
				'translations' => trim($scriptTranslations),
				'data'   => trim($scriptExtraCdata),
				'before' => trim($scriptExtraBefore),
				'after'  => trim($scriptExtraAfter)
			);
		}

		if ( $return === 'html' && $scriptHandle ) {
			return array(
				'translations' => self::generateInlineAssocHtmlForHandle($scriptHandle, 'translations'),
				'data'         => self::generateInlineAssocHtmlForHandle($scriptHandle, 'data'),
				'before'       => self::generateInlineAssocHtmlForHandle($scriptHandle, 'before'),
				'after'        => self::generateInlineAssocHtmlForHandle($scriptHandle, 'after')
			);
		}

		return array('translations' => '', 'data' => '', 'before' => '', 'after' => '');
	}

	/**
	 * @param string $handle
	 * @param string $position
	 * @param string $inlineScriptContent
	 *
	 * @return string
     * @noinspection CallableParameterUseCaseInTypeContextInspection
     */
	public static function generateInlineAssocHtmlForHandle($handle, $position, $inlineScriptContent = '')
	{
		global $wp_scripts;

		$output = '';

		if ($position === 'translations') {
			$translations = false;

			if (method_exists('wp_scripts', 'print_translations')) {
				$translations = $wp_scripts->print_translations( $handle, false );
			}

			if ( $translations ) {
				$output = sprintf( "<script%s id='%s-js-translations'>\n%s\n</script>\n", Misc::getScriptTypeAttribute(), esc_attr( $handle ), $translations );
			}
		}

		if ( $position === 'data' ) {
			if ( ! $inlineScriptContent ) {
				$inlineScriptContent = $wp_scripts->get_data( $handle, 'data' );

				if ( ! $inlineScriptContent ) {
					return '';
				}
			}

			$output .= sprintf("<script%s id='%s-js-extra'>\n", Misc::getScriptTypeAttribute(), esc_attr($handle));

			// CDATA is not needed for HTML 5.
			if ( Misc::getScriptTypeAttribute() ) {
				$output .= "/* <![CDATA[ */\n";
			}

			$output .= $inlineScriptContent."\n";

			if ( Misc::getScriptTypeAttribute() ) {
				$output .= "/* ]]> */\n";
			}

			$output .= '</script>';
		}

		if ( $position === 'before' || $position === 'after' ) {
			if ( ! $inlineScriptContent ) {
				if (method_exists($wp_scripts, 'get_inline_script_data')) {
					// WordPress >= 6.3
					$inlineScriptContent = $wp_scripts->get_inline_script_data( $handle, $position );
				} else {
					// WordPress < 6.3
					$inlineScriptContent = $wp_scripts->print_inline_script( $handle, $position, false );
				}

				if ( ! $inlineScriptContent ) {
					$output = '';
				}
			}

			if ( $inlineScriptContent ) {
				if (function_exists('wp_get_inline_script_tag')) {
					// WordPress >= 5.7.0
					$id = "{$handle}-js-{$position}";
					$output = wp_get_inline_script_tag( $inlineScriptContent, compact( 'id' ) );
				} else {
					// WordPress < 5.7.0
					$output = sprintf( "<script%s id='%s-js-%s'>\n%s\n</script>\n", Misc::getScriptTypeAttribute(), $handle, $position, $inlineScriptContent );
				}
			}
		}

		return $output;
	}

	/**
	 * @param $listWithPatterns
	 * @param $htmlSource
	 *
	 * @return string
	 */
	public static function getScriptTagFromHandle($listWithPatterns, $htmlSource)
	{
		if (empty($listWithPatterns)) {
			return '';
		}

		if (! is_array($listWithPatterns)) {
			$listWithPatterns = array($listWithPatterns);
		}

		preg_match_all(
			'#<script[^>]*('.implode('|', $listWithPatterns).')[^>].*(>)#Usmi',
			$htmlSource,
			$matchesSourcesFromTags
		);

		if (empty($matchesSourcesFromTags)) {
			return '';
		}

		if ( ! empty($matchesSourcesFromTags[0]) ) {
			foreach ($matchesSourcesFromTags[0] as $matchesFromTag) {
				if (stripos($matchesFromTag, ' src=') !== false && strip_tags($matchesFromTag) === '') {
					return $matchesFromTag.'</script>';
				}
			}
		}

		return '';
	}

    /**
     * @return bool
     * @noinspection NestedPositiveIfStatementsInspection
     */
    public static function proceedWithJsCombine()
    {
        // not on query string request (debugging purposes)
        if ( isset($_REQUEST['wpacu_no_js_combine']) ) {
            return false;
        }

        // No JS files are combined in the Dashboard
        // Always in the front-end view
        // Do not combine if there's a POST request as there could be assets loading conditionally
        // that might not be needed when the page is accessed without POST, making the final JS file larger
        if (! empty($_POST) || is_admin()) {
            return false; // Do not combine
        }

        // Only clean request URIs allowed (with few exceptions)
        if (strpos($_SERVER['REQUEST_URI'], '?') !== false) {
            // Exceptions
            if (! OptimizeCommon::loadOptimizedAssetsIfQueryStrings()) {
                return false;
            }
        }

        if (! OptimizeCommon::doCombineIsRegularPage()) {
            return false;
        }

        $pluginSettings = Main::instance()->settings;

        if ($pluginSettings['test_mode'] && ! Menu::userCanAccessAssetCleanUp()) {
            return false; // Do not combine anything if "Test Mode" is ON
        }

        if ($pluginSettings['combine_loaded_js'] === '') {
            return false; // Do not combine
        }

        if (self::isOptimizeJsEnabledByOtherParty('if_enabled')) {
            return false; // Do not combine (it's already enabled in other plugin)
        }

        // "Minify HTML" from WP Rocket is sometimes stripping combined SCRIPT tags
        // Better uncombined then missing essential SCRIPT files
        if (Misc::isWpRocketMinifyHtmlEnabled()) {
            return false;
        }

        /*
        if ( ($pluginSettings['combine_loaded_js'] === 'for_admin'
              || $pluginSettings['combine_loaded_js_for_admin_only'] == 1)
             && Menu::userCanAccessAssetCleanUp() ) {
            return true; // Do combine
        }
        */

        // "Apply it only for guest visitors (default)" is set; Do not combine if the user is logged in
        if ( $pluginSettings['combine_loaded_js_for'] === 'guests' && is_user_logged_in() ) {
            return false;
        }

        if ( in_array($pluginSettings['combine_loaded_js'], array('for_all', 1)) ) {
            return true; // Do combine
        }

        // Finally, return false as none of the checks above matched
        return false;
    }

	}
