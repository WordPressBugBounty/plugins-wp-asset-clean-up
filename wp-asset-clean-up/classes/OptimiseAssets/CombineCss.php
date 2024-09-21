<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\FileSystem;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\ObjectCache;
use WpAssetCleanUp\Preloads;

/**
 * Class CombineCss
 * @package WpAssetCleanUp\OptimiseAssets
 */
class CombineCss
{
	/**
	 * @var string
	 */
	public static $jsonStorageFile = 'css-combined{maybe-extra-info}.json';

	/**
	 * @param $htmlSource
	 *
	 * @return mixed
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function doCombine($htmlSource)
	{
		if ( ! Misc::isDOMDocumentOn() ) {
			return $htmlSource;
		}

		global $wp_styles;
		$wpacuRegisteredStyles = $wp_styles->registered;

		$storageJsonContents = array();
		$skipCache = false; // default

		if (isset($_GET['wpacu_no_cache']) || wpacuIsDefinedConstant('WPACU_NO_CACHE')) {
			$skipCache = true;
		}

		// If the cache is not skipped, read the information from the cache as it's much faster
		if (! $skipCache) {
			// Speed up processing by getting the already existing final CSS file URI
			// This will avoid parsing the HTML DOM and determine the combined URI paths for all the CSS files
			$storageJsonContents = OptimizeCommon::getAssetCachedData( self::$jsonStorageFile, OptimizeCss::getRelPathCssCacheDir(), 'css' );
		}

		// $uriToFinalCssFile will always be relative ONLY within WP_CONTENT_DIR . self::getRelPathCssCacheDir()
		// which is usually "wp-content/cache/asset-cleanup/css/"

		if ( $skipCache || empty($storageJsonContents) ) {
			$storageJsonContentsToSave = array();

			/*
			 * NO CACHING? Parse the DOM
			*/
			// Nothing in the database records or the retrieved cached file does not exist?
			OptimizeCommon::clearAssetCachedData(self::$jsonStorageFile);

			$storageJsonContents = array();

			$domTag = OptimizeCommon::getDomLoadedTag($htmlSource, 'combineCss');

			foreach (array('head', 'body') as $docLocationTag) {
				$combinedUriPathsGroup = $localAssetsPathsGroup = $linkHrefsGroup = array();
				$localAssetsExtraGroup = array();

				$docLocationElements = $domTag->getElementsByTagName($docLocationTag)->item(0);

				if ($docLocationElements === null) { continue; }

				$xpath = new \DOMXpath($domTag);
				$linkTags = $xpath->query('/html/'.$docLocationTag.'/link[@rel="stylesheet"] | /html/'.$docLocationTag.'/link[@rel="preload"]');
				if ($linkTags === null) { continue; }

				foreach ($linkTags as $tagObject) {
					$linkAttributes = array();
					foreach ($tagObject->attributes as $attrObj) { $linkAttributes[$attrObj->nodeName] = trim($attrObj->nodeValue); }

					// Only rel="stylesheet" (with no rel="preload" associated with it) gets prepared for combining as links with rel="preload" (if any) are never combined into a standard render-blocking CSS file
					// rel="preload" is there for a reason to make sure the CSS code is made available earlier prior to the one from rel="stylesheet" which is render-blocking
					if (isset($linkAttributes['rel'], $linkAttributes['href']) && $linkAttributes['href']) {
						$href = (string) $linkAttributes['href'];

						if (self::skipCombine($linkAttributes['href'])) {
							continue;
						}

						// e.g. for 'admin-bar' (keep it as standalone when critical CSS is used)
						if (isset($linkAttributes['data-wpacu-skip-preload']) && has_filter('wpacu_critical_css')) {
							continue;
						}

						// Check if the CSS file has any 'data-wpacu-skip' attribute; if it does, do not alter it
						if (isset($linkAttributes['data-wpacu-skip'])
                        ) {
							continue;
						}

						// Separate each combined group by the "media" attribute; e.g. we don't want "all" and "print" mixed
						$mediaValue = (array_key_exists('media', $linkAttributes) && $linkAttributes['media']) ? $linkAttributes['media'] : 'all';

						// Check if there is any rel="preload" (Basic) connected to the rel="stylesheet"
						// making sure the file is not added to the final CSS combined file
						if (isset($linkAttributes['data-wpacu-style-handle']) &&
						    $linkAttributes['data-wpacu-style-handle'] &&
						    ObjectCache::wpacu_cache_get($linkAttributes['data-wpacu-style-handle'], 'wpacu_basic_preload_handles')) {
							$mediaValue = 'wpacu_preload_basic_' . $mediaValue;
						}

						// Make the right reference for later use
						if ($linkAttributes['rel'] === 'preload') {
							if (isset($linkAttributes['data-wpacu-preload-css-basic'])) {
								$mediaValue = 'wpacu_preload_basic_' . $mediaValue;
							} else {
								continue;
							}
						}

						// Was it optimized and has the URL updated? Check the Source URL to determine if it should be skipped from combining
						if (isset($linkAttributes['data-wpacu-link-rel-href-before']) && $linkAttributes['data-wpacu-link-rel-href-before'] && self::skipCombine($linkAttributes['data-wpacu-link-rel-href-before'])) {
							continue;
						}

						// Avoid combining own plugin's CSS (irrelevant) as it takes extra useless space in the caching directory
						if (isset($linkAttributes['id']) && $linkAttributes['id'] === WPACU_PLUGIN_ID.'-style-css') {
							continue;
						}

						$localAssetPath = OptimizeCommon::getLocalAssetPath($href, 'css');

						// It will skip external stylesheets (from a different domain)
						if ( $localAssetPath ) {
							$styleExtra = array();

							if (isset($linkAttributes['data-wpacu-style-handle'], $wpacuRegisteredStyles[$linkAttributes['data-wpacu-style-handle']]->extra) && OptimizeCommon::appendInlineCodeToCombineAssetType('css')) {
								$styleExtra = $wpacuRegisteredStyles[$linkAttributes['data-wpacu-style-handle']]->extra;
							}

							$sourceRelPath = OptimizeCommon::getSourceRelPath($href);

							$alreadyAddedSourceRelPath = isset($combinedUriPathsGroup[$mediaValue]) && in_array($sourceRelPath, $combinedUriPathsGroup[$mediaValue]);
							if (! $alreadyAddedSourceRelPath) {
								$combinedUriPathsGroup[$mediaValue][] = $sourceRelPath;
							}

							$localAssetsPathsGroup[$mediaValue][$href] = $localAssetPath;

							$alreadyAddedHref = isset($linkHrefsGroup[$mediaValue]) && in_array($href, $linkHrefsGroup[$mediaValue]);
							if (! $alreadyAddedHref) {
								$linkHrefsGroup[$mediaValue][] = $href;
							}

							$localAssetsExtraGroup[$mediaValue][$href] = $styleExtra;
						}
					}
				}

				// No Link Tags or only one tag in the combined group? Do not proceed with any combining
				if ( empty( $combinedUriPathsGroup ) ) {
					continue;
				}

				foreach ($combinedUriPathsGroup as $mediaValue => $combinedUriPaths) {
					// There have to be at least two CSS files to create a combined CSS file
					if (count($combinedUriPaths) < 2) {
						continue;
					}

					$localAssetsPaths = $localAssetsPathsGroup[$mediaValue];
					$linkHrefs = $linkHrefsGroup[$mediaValue];
					$localAssetsExtra = array_filter($localAssetsExtraGroup[$mediaValue]);

					$maybeDoCssCombine = self::maybeDoCssCombine(
						$localAssetsPaths,
						$linkHrefs,
						$localAssetsExtra,
						$docLocationTag
					);

					// Local path to combined CSS file
					$localFinalCssFile = $maybeDoCssCombine['local_final_css_file'];

					// URI (e.g. /wp-content/cache/asset-cleanup/[file-name-here.css]) to the combined CSS file
					$uriToFinalCssFile = $maybeDoCssCombine['uri_final_css_file'];

					// Any link hrefs removed, perhaps if the file wasn't combined?
					$linkHrefs = $maybeDoCssCombine['link_hrefs'];

					if (is_file($localFinalCssFile)) {
                        foreach ($linkHrefs as $originalIndexKey => $href) {
                            $indexKey = $originalIndexKey; // default

                            if (strpos($href, OptimizeCommon::getRelPathPluginCacheDir()) === false) {
                                $localFilePath = OptimizeCommon::getLocalAssetPath($href, 'css');

                                // Unique Mark: SHA1 of the file contents + a unique ID (in rare cases when a different file has the same content)
                                $indexKey      = sha1_file($localFilePath) . '_'. uniqid('', true);
                            }

                            $href = str_replace('{site_url}', '', OptimizeCommon::getSourceRelPath($href, true));

                            unset($linkHrefs[$originalIndexKey]);
                            $linkHrefs[$indexKey] = $href;
                        }

                        $storageJsonContents[$docLocationTag][$mediaValue] = array(
							'uri_to_final_css_file' => $uriToFinalCssFile,
							'link_hrefs'            => $linkHrefs
						);

						$storageJsonContentsToSave[$docLocationTag][$mediaValue] = array(
							'uri_to_final_css_file' => $uriToFinalCssFile,
							'link_hrefs'            => $linkHrefs
						);
					}
				}
			}

			libxml_clear_errors();

			OptimizeCommon::setAssetCachedData(
				self::$jsonStorageFile,
				OptimizeCss::getRelPathCssCacheDir(),
				wp_json_encode($storageJsonContentsToSave)
			);
		}

		$cdnUrls = OptimizeCommon::getAnyCdnUrls();
		$cdnUrlForCss = isset($cdnUrls['css']) ? $cdnUrls['css'] : false;

		if ( ! empty($storageJsonContents) ) {
			foreach ($storageJsonContents as $docLocationTag => $mediaValues) {
				$groupLocation = 1;

				foreach ($mediaValues as $mediaValue => $storageJsonContentLocation) {
					if (empty($storageJsonContentLocation['link_hrefs'])) {
                        continue;
					}

					// Irrelevant to have only one CSS file in a combine CSS group
					if (count($storageJsonContentLocation['link_hrefs']) < 2) {
						continue;
					}

					$storageJsonContentLocation['link_hrefs'] = array_map(static function($href) {
						return str_replace('{site_url}', '', $href);
					}, $storageJsonContentLocation['link_hrefs']);

					$finalTagUrl = OptimizeCommon::filterWpContentUrl($cdnUrlForCss) . OptimizeCss::getRelPathCssCacheDir() . $storageJsonContentLocation['uri_to_final_css_file'];

					$finalCssTagAttrs = array();

                    if (strncmp($mediaValue, 'wpacu_preload_basic_', 20) === 0) {
						// Put the right "media" value after cleaning the reference
						$mediaValueClean = str_replace('wpacu_preload_basic_', '', $mediaValue);

						// Basic Preload
						$finalCssTag = <<<HTML
<link rel='stylesheet' data-wpacu-to-be-preloaded-basic='1' id='wpacu-combined-css-{$docLocationTag}-{$groupLocation}-stylesheet' href='{$finalTagUrl}' type='text/css' media='{$mediaValueClean}' />
HTML;
						$finalCssTagRelPreload = <<<HTML
<link rel='preload' as='style' id='wpacu-combined-css-{$docLocationTag}-{$groupLocation}-preload' href='{$finalTagUrl}' type='text/css' media='{$mediaValueClean}' />
HTML;

						$finalCssTagAttrs['rel']   = 'preload';
						$finalCssTagAttrs['media'] = $mediaValueClean;

						$htmlSource = str_replace(Preloads::DEL_STYLES_PRELOADS, $finalCssTagRelPreload."\n" . Preloads::DEL_STYLES_PRELOADS, $htmlSource);
					} else {
						// Render-blocking CSS
						$finalCssTag = <<<HTML
<link rel='stylesheet' id='wpacu-combined-css-{$docLocationTag}-{$groupLocation}' href='{$finalTagUrl}' type='text/css' media='{$mediaValue}' />
HTML;
						$finalCssTagAttrs['rel']   = 'stylesheet';
						$finalCssTagAttrs['media'] = $mediaValue;
					}

					// In case one (e.g. usually a developer) needs to alter it
					$finalCssTag = apply_filters(
						'wpacu_combined_css_tag',
						$finalCssTag,
						array(
							'attrs'        => $finalCssTagAttrs,
							'doc_location' => $docLocationTag,
							'group_no'     => $groupLocation,
							'href'         => $finalTagUrl
						)
					);

					// Reference: https://stackoverflow.com/questions/2368539/php-replacing-multiple-spaces-with-a-single-space
					$finalCssTag = preg_replace('!\s+!', ' ', $finalCssTag);

					$htmlSourceBeforeAnyLinkTagReplacement = $htmlSource;

					// Detect the first LINK tag from the <$locationTag> and replace it with the final combined LINK tag
					$firstLinkTag = OptimizeCss::getFirstLinkTag(reset($storageJsonContentLocation['link_hrefs']), $htmlSource);

					if ($firstLinkTag) {
						// 1) Strip inline code before/after it (if any)
						// 2) Finally, strip the actual tag
						$htmlSource = self::stripTagAndAnyInlineAssocCode( $firstLinkTag, $wpacuRegisteredStyles, $finalCssTag, $htmlSource );
					}

					if ($htmlSource !== $htmlSourceBeforeAnyLinkTagReplacement) {
						$htmlSource = self::stripJustCombinedLinkTags(
							$storageJsonContentLocation['link_hrefs'],
							$wpacuRegisteredStyles,
							$htmlSource
						); // Strip the combined files to avoid duplicate code

						// There should be at least two replacements made, AND all the tags should have been replaced
						// Leave no room for errors, otherwise the page could end up with extra files loaded, leading to a slower website
						if ($htmlSource === 'do_not_combine') {
							$htmlSource = $htmlSourceBeforeAnyLinkTagReplacement;
						} else {
							$groupLocation++;
						}
					} else {
                        OptimizeCommon::clearAssetCachedData(self::$jsonStorageFile);
                        }
				}
			}
		}

		return $htmlSource;
	}

	/**
	 * @param $filesSources
	 * @param $wpacuRegisteredStyles
	 * @param $htmlSource
	 *
	 * @return mixed
	 */
	public static function stripJustCombinedLinkTags($filesSources, $wpacuRegisteredStyles, $htmlSource)
	{
		preg_match_all('#<link[^>]*(stylesheet|preload)[^>]*(>)#Umi', $htmlSource, $matchesSourcesFromTags, PREG_SET_ORDER);

		$linkTagsStrippedNo = 0;
        $clearCombinedCssCache = false;

		foreach ($matchesSourcesFromTags as $matchSourceFromTag) {
			$matchedSourceFromTag = (isset($matchSourceFromTag[0]) && strip_tags($matchSourceFromTag[0]) === '') ? trim($matchSourceFromTag[0]) : '';

			if (! $matchSourceFromTag) {
				continue;
			}

			// The DOMDocument is already checked if it's enabled in doCombine()
			$domTag = Misc::initDOMDocument();
			$domTag->loadHTML($matchedSourceFromTag);

			foreach ($domTag->getElementsByTagName('link') as $tagObject) {
				if (empty($tagObject->attributes)) { continue; }

				foreach ($tagObject->attributes as $tagAttrs) {
					if ($tagAttrs->nodeName === 'href') {
						$relNodeValue = trim(OptimizeCommon::getSourceRelPath($tagAttrs->nodeValue, true));

                        $indexKey = array_search($relNodeValue, $filesSources);

						if ($indexKey !== false) {
							// Check the SHA1 value of the file if it's in the original location (not within the caching directory)
                            if (strpos($indexKey, '_') !== false && strpos($relNodeValue, OptimizeCommon::getRelPathPluginCacheDir()) === false) {
                                list($sha1File) = explode('_', $indexKey);

                                if ($sha1File !== sha1_file(OptimizeCommon::getLocalAssetPath($relNodeValue, 'css'))) {
                                    // The contents of one of the files from the combined CSS were changed
                                    $clearCombinedCssCache = true;
                                    break 3;
                                }
                            }

							$htmlSourceBeforeLinkTagReplacement = $htmlSource;

							// 1) Strip inline code before/after it (if any)
							// 2) Finally, strip the actual tag
							$htmlSource = self::stripTagAndAnyInlineAssocCode( $matchedSourceFromTag, $wpacuRegisteredStyles, '', $htmlSource );

							if ($htmlSource !== $htmlSourceBeforeLinkTagReplacement) {
								$linkTagsStrippedNo++;
							}
							}
					}
				}
			}

			libxml_clear_errors();
		}

		// Aren't all the LINK tags stripped? They should be, otherwise, do not proceed with the HTML alteration (no combining will take place)
		// Minus the already combined tag
		if ($clearCombinedCssCache || (($linkTagsStrippedNo < 2) && (count($filesSources) !== $linkTagsStrippedNo))) {
            // It looks like one or more files are not loading on this page
            // It means the combined CSS is irrelevant as it should contain fewer files
            // Clear it, so it will be re-created!

            OptimizeCommon::clearAssetCachedData(self::$jsonStorageFile);
            return 'do_not_combine';
		}

		return $htmlSource;
	}

	/**
	 * @param $href
	 *
	 * @return bool
	 */
	public static function skipCombine($href)
	{
		$regExps = array(
			'#/wp-content/bs-booster-cache/#'
		);

		if (Main::instance()->settings['combine_loaded_css_exceptions'] !== '') {
			$loadedCssExceptionsPatterns = trim(Main::instance()->settings['combine_loaded_css_exceptions']);

			if (strpos($loadedCssExceptionsPatterns, "\n")) {
				// Multiple values (one per line)
				foreach (explode("\n", $loadedCssExceptionsPatterns) as $loadedCssExceptionPattern) {
					$regExps[] = '#'.trim($loadedCssExceptionPattern).'#';
				}
			} else {
				// Only one value?
				$regExps[] = '#'.trim($loadedCssExceptionsPatterns).'#';
			}
		}

		// No exceptions set? Do not skip combination
		if (empty($regExps)) {
			return false;
		}

		foreach ($regExps as $regExp) {
			if ( @preg_match( $regExp, $href ) || ( strpos($href, $regExp) !== false ) ) {
				// Skip combination
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $localAssetsPaths
	 * @param $linkHrefs
	 * @param $localAssetsExtra
	 * @param $docLocationTag
	 *
	 * @return array
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function maybeDoCssCombine($localAssetsPaths, $linkHrefs, $localAssetsExtra, $docLocationTag)
	{
		// Only combine if $shaOneCombinedUriPaths.css does not exist
		// If "?ver" value changes on any of the assets or the asset list changes in any way
		// then $shaOneCombinedUriPaths will change too and a new CSS file will be generated and loaded

		// Change $finalCombinedCssContent as paths to fonts and images that are relative (e.g. ../, ../../) have to be updated + other optimization changes
			$uriToFinalCssFile = $localFinalCssFile = $finalCombinedCssContent = '';

			foreach ($localAssetsPaths as $assetHref => $localAssetsPath) {
				if ($cssContent = trim(FileSystem::fileGetContents($localAssetsPath, 'combine_css_imports'))) {
					$pathToAssetDir = OptimizeCommon::getPathToAssetDir($assetHref);

					// Does it have a source map? Strip it
					if (strpos($cssContent, '/*# sourceMappingURL=') !== false) {
						$cssContent = OptimizeCommon::stripSourceMap($cssContent, 'css');
					}

					if (apply_filters('wpacu_print_info_comments_in_cached_assets', true)) {
						$finalCombinedCssContent .= '/*!' . str_replace( Misc::getWpRootDirPathBasedOnPath($localAssetsPath), '/', $localAssetsPath ) . "*/\n";
					}

					$finalCombinedCssContent .= OptimizeCss::maybeFixCssContent($cssContent, $pathToAssetDir . '/') . "\n";

					$finalCombinedCssContent = self::appendToCombineCss($localAssetsExtra, $assetHref, $pathToAssetDir, $finalCombinedCssContent);
				}
			}

			// Move any @imports to the top; This also strips any @imports to Google Fonts if the option is chosen
			$finalCombinedCssContent = trim(OptimizeCss::importsUpdate($finalCombinedCssContent));

			if (Main::instance()->settings['google_fonts_remove']) {
				$finalCombinedCssContent = FontsGoogleRemove::cleanFontFaceReferences($finalCombinedCssContent);
			}

			$finalCombinedCssContent = apply_filters('wpacu_local_fonts_display_css_output', $finalCombinedCssContent, Main::instance()->settings['local_fonts_display']);

			if ($finalCombinedCssContent) {
				$finalCombinedCssContent = trim($finalCombinedCssContent);
				$shaOneForCombinedCss = sha1($finalCombinedCssContent);

				$uriToFinalCssFile = $docLocationTag . '-' .$shaOneForCombinedCss . '.css';
				$localFinalCssFile = WP_CONTENT_DIR . OptimizeCss::getRelPathCssCacheDir() . $uriToFinalCssFile;

				if (! is_file($localFinalCssFile)) {
					FileSystem::filePutContents($localFinalCssFile, $finalCombinedCssContent);
				}
			}

		return array(
			'uri_final_css_file'   => $uriToFinalCssFile,
			'local_final_css_file' => $localFinalCssFile,
			'link_hrefs'           => $linkHrefs
		);
	}

	/**
	 * @param $localAssetsExtra
	 * @param $assetHref
	 * @param $pathToAssetDir
	 * @param $finalAssetsContents
	 *
	 * @return string
	 */
	public static function appendToCombineCss($localAssetsExtra, $assetHref, $pathToAssetDir, $finalAssetsContents)
	{
		if ( ! empty($localAssetsExtra[$assetHref]['after']) ) {
			$afterCssContent = '';

			foreach ($localAssetsExtra[$assetHref]['after'] as $afterData) {
				if (! is_bool($afterData)) {
					$afterCssContent .= $afterData."\n";
				}
			}

			if (trim($afterCssContent)) {
				if (MinifyCss::isMinifyCssEnabled() && in_array(Main::instance()->settings['minify_loaded_css_for'], array('inline', 'all'))) {
					$afterCssContent = MinifyCss::applyMinification( $afterCssContent );
				}

				$afterCssContent = OptimizeCss::maybeFixCssContent( $afterCssContent, $pathToAssetDir . '/' );

				$finalAssetsContents .= apply_filters('wpacu_print_info_comments_in_cached_assets', true) ? '/* [inline: after] */' : '';
				$finalAssetsContents .= $afterCssContent;
				$finalAssetsContents .= apply_filters('wpacu_print_info_comments_in_cached_assets', true) ? '/* [/inline: after] */' : '';
				$finalAssetsContents .= "\n";
			}
		}

		return $finalAssetsContents;
	}

	/**
	 * The targeted LINK tag (which was enqueued and has a handle) is replaced with $replaceWith
	 * along with any inline content that was added after it via wp_add_inline_style()
	 *
	 * @param $targetedLinkTag
	 * @param $wpacuRegisteredStyles
	 * @param $replaceWith
	 * @param $htmlSource
	 *
	 * @return mixed
	 */
	public static function stripTagAndAnyInlineAssocCode($targetedLinkTag, $wpacuRegisteredStyles, $replaceWith, $htmlSource)
	{
		if (OptimizeCommon::appendInlineCodeToCombineAssetType('css')) {
			$scriptExtrasHtml = OptimizeCss::getInlineAssociatedWithLinkHandle($targetedLinkTag, $wpacuRegisteredStyles, 'tag', 'html');
			$scriptExtraAfterHtml = (isset($scriptExtrasHtml['after']) && $scriptExtrasHtml['after']) ? "\n".$scriptExtrasHtml['after'] : '';

			$htmlSource = str_replace(
				array(
					$targetedLinkTag . $scriptExtraAfterHtml,
					$targetedLinkTag . trim($scriptExtraAfterHtml)
				),
				$replaceWith,
				$htmlSource
			);
		}

		return str_replace(
			array(
				$targetedLinkTag."\n",
				$targetedLinkTag
			),
			$replaceWith."\n",
			$htmlSource
		);
	}
}
