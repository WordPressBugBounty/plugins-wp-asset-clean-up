<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\FileSystem;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\ObjectCache;
use WpAssetCleanUp\Preloads;
use WpAssetCleanUp\Regex;

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
        $sourceHashes = $inertSources = $sourceFiles = array();
        $registeredInline = array();

        if (OptimizeCommon::appendInlineCodeToCombineAssetType('css')) {
            foreach ($wpacuRegisteredStyles as $handle => $style) {
                if ( ! empty($style->extra['after']) ) {
                    $registeredInline[$handle] = $style->extra['after'];
                }
            }
        }

        $registeredInlineSignature = sha1('combine-tag-identity-v1' . wp_json_encode($registeredInline));
        // Older indices may have moved attached inline CSS without removing its real tag.
        $documentSignature = sha1('css-import-order-url-v3' . $registeredInlineSignature . OptimizeCommon::getCombineDocumentSignature($htmlSource));

		if (isset($_GET['wpacu_no_cache']) || wpacuIsDefinedConstant('WPACU_NO_CACHE')) {
			$skipCache = true;
		}

		// If the cache is not skipped, read the information from the cache as it's much faster
		if (! $skipCache) {
			// Speed up processing by getting the already existing final CSS file URI
			// Reuse bundle metadata only after validating the current document and source contents.
			$storageJsonContents = OptimizeCommon::getAssetCachedData( self::$jsonStorageFile, OptimizeCss::getRelPathCssCacheDir(), 'css' );
		}

        if (! OptimizeCommon::combineCacheMatchesDocument($storageJsonContents, $documentSignature, 'css', $sourceHashes)) {
            $domTag = OptimizeCommon::getDomLoadedTag($htmlSource, 'combineCss');
            // Raw serialization controls whether attached inline CSS can be removed safely.
            $preserveInlineHandles = array();
            $inlineTagCounts      = array();
            $linkTagCounts        = array();
            $inlineTags           = (new \DOMXPath($domTag))->query('//style[not(ancestor::template)]');

            foreach ($domTag->getElementsByTagName('link') as $linkTag) {
                foreach (array('href', 'data-wpacu-style-handle') as $attribute) {
                    $key = $attribute . ':' . $linkTag->getAttribute($attribute);
                    $linkTagCounts[$key] = isset($linkTagCounts[$key]) ? $linkTagCounts[$key] + 1 : 1;
                }
            }

            foreach ($inlineTags as $inlineTag) {
                $id = $inlineTag->getAttribute('id');
                $inlineTagCounts[$id] = isset($inlineTagCounts[$id]) ? $inlineTagCounts[$id] + 1 : 1;
            }

            foreach ($inlineTags as $inlineTag) {
                $id = $inlineTag->getAttribute('id');

                if (substr($id, -11) !== '-inline-css') {
                    continue;
                }

                $handle = substr($id, 0, -11);

                // WordPress and HTML filters can change quotes, whitespace or sourceURL
                // comments. Preserve the original position unless removal is exact.
                if ( OptimizeCss::hasImportRule($inlineTag->textContent) || ! self::canRemoveAttachedInlineStyle($inlineTag, $htmlSource, $wpacuRegisteredStyles, $inlineTagCounts, $linkTagCounts) ) {
                    $preserveInlineHandles[$handle] = true;
                }

                foreach (array('nonce', 'media', 'type', 'data-wpacu-skip') as $attribute) {
                    if ($inlineTag->hasAttribute($attribute)
                        && ! ($attribute === 'type' && $inlineTag->getAttribute('type') === 'text/css')) {
                        $preserveInlineHandles[$handle] = true;
                        break;
                    }
                }
            }
            $sourceSignature = sha1('css-import-order-url-v3' . $registeredInlineSignature . wp_json_encode(array_keys($preserveInlineHandles)) . OptimizeCommon::getCombineSourceSignature($domTag, 'css', $sourceHashes, $inertSources, $sourceFiles));
            if (! OptimizeCommon::combineCacheMatchesSource($storageJsonContents, $sourceSignature)) {
                $storageJsonContents = array();
            }
        }

		// $uriToFinalCssFile will always be relative ONLY within WP_CONTENT_DIR . self::getRelPathCssCacheDir()
		// which is usually "wp-content/cache/asset-cleanup/css/"

		if ( $skipCache || empty($storageJsonContents) ) {

			$storageJsonContentsToSave = array();
            $duplicateKeys = CombineTagIndex::getDuplicateKeys($domTag, 'link');

			/*
			 * NO CACHING? Parse the DOM
			*/
			// Nothing in the database records or the retrieved cached file does not exist?
			OptimizeCommon::clearAssetCachedData(self::$jsonStorageFile);

			$storageJsonContents = array();

			foreach (array('head', 'body') as $docLocationTag) {
				$combinedUriPathsGroup = $localAssetsPathsGroup = $linkHrefsGroup = array();
				$localAssetsExtraGroup = array();
                $tagKeysByHref = array();
				$groupMedia = array();
				$groupIndex = 0;
				$previousMedia = null;
				$lastStyleHandle = '';

				$docLocationElements = $domTag->getElementsByTagName($docLocationTag)->item(0);

				if ($docLocationElements === null) { continue; }

				$xpath = new \DOMXpath($domTag);
				$linkTags = $xpath->query('.//link[(@rel="stylesheet" or @rel="preload") and not(ancestor::template)] | .//style[not(ancestor::template)]', $docLocationElements);
				if ($linkTags === null) { continue; }

				foreach ($linkTags as $tagObject) {
					// Hints may be hoisted to the start of HEAD. Only stylesheet positions define order.
					if ($tagObject->nodeName === 'link' && $tagObject->getAttribute('rel') === 'preload') { continue; }
					if ($tagObject->nodeName === 'style') {
						// Only attached inline CSS which is moved with its handle can share the group.
						if ($lastStyleHandle && OptimizeCommon::appendInlineCodeToCombineAssetType('css')
						    && $tagObject->getAttribute('id') === $lastStyleHandle.'-inline-css'
						    && ! $tagObject->hasAttribute('nonce')) { continue; }
						$previousMedia = null;
						$lastStyleHandle = '';
						continue;
					}
					$precedingMedia = $previousMedia;
					$previousMedia = null; // Any skipped stylesheet is a boundary, including remote sources.
					$lastStyleHandle = '';
					$linkAttributes = array();
					foreach ($tagObject->attributes as $attrObj) { $linkAttributes[$attrObj->nodeName] = trim($attrObj->nodeValue); }

					// Only rel="stylesheet" (with no rel="preload" associated with it) gets prepared for combining as links with rel="preload" (if any) are never combined into a standard render-blocking CSS file
					// rel="preload" is there for a reason to make sure the CSS code is made available earlier prior to the one from rel="stylesheet" which is render-blocking
					if (isset($linkAttributes['rel'], $linkAttributes['href']) && $linkAttributes['href']) {
						$href = (string) $linkAttributes['href'];
                        $tagKey = CombineTagIndex::key($linkAttributes);
                        if (isset($duplicateKeys[$tagKey])) {
                            continue;
                        }
                        if (isset($inertSources[OptimizeCommon::getCombineSourceKey($href)])) { continue; }

						if (self::skipCombine($linkAttributes['href'])) {
							continue;
						}

						// e.g. for 'admin-bar' (keep it as standalone when critical CSS is used)
						if (isset($linkAttributes['data-wpacu-skip-preload']) && has_filter('wpacu_critical_css')) {
							continue;
						}

						// Check if the CSS file has any 'data-wpacu-skip' attribute; if it does, do not alter it
						$skipLinkTag = isset($linkAttributes['data-wpacu-skip']);

						$skipLinkTag = apply_filters(
							'wpacu_internal_combine_css_skip_link_tag',
							$skipLinkTag,
							$linkAttributes
						);
						foreach (array('nonce', 'integrity', 'crossorigin', 'referrerpolicy', 'disabled', 'title', 'onload') as $attribute) {
							if (array_key_exists($attribute, $linkAttributes)) { $skipLinkTag = true; break; }
						}
						if (isset($linkAttributes['data-wpacu-style-handle'], $preserveInlineHandles[$linkAttributes['data-wpacu-style-handle']])) {
							$skipLinkTag = true;
						}

						if ($skipLinkTag) {
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
								$preloadMediaValue = apply_filters(
									'wpacu_internal_combine_css_preload_media_value',
									false,
									$mediaValue,
									$linkAttributes
								);

								if ($preloadMediaValue === false || $preloadMediaValue === '') {
									continue;
								}

								$mediaValue = $preloadMediaValue;
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

						$mediaValue = apply_filters(
							'wpacu_internal_combine_css_media_value',
							$mediaValue,
							$linkAttributes
						);

						$localAssetPath = OptimizeCommon::getLocalAssetPath($href, 'css');

						// It will skip external stylesheets (from a different domain)
						if ( $localAssetPath ) {
                            if (isset($sourceHashes['imports:' . $localAssetPath]) && $sourceHashes['imports:' . $localAssetPath] === false) {
                                continue;
                            }
							if ($precedingMedia !== $mediaValue) { $groupIndex++; }
							$groupKey = (string)$groupIndex;
							$groupMedia[$groupKey] = $mediaValue;
							$previousMedia = $mediaValue;
							$lastStyleHandle = isset($linkAttributes['data-wpacu-style-handle']) ? $linkAttributes['data-wpacu-style-handle'] : '';
							$styleExtra = array();

							if (isset($linkAttributes['data-wpacu-style-handle'], $wpacuRegisteredStyles[$linkAttributes['data-wpacu-style-handle']]->extra) && OptimizeCommon::appendInlineCodeToCombineAssetType('css')) {
								$styleExtra = $wpacuRegisteredStyles[$linkAttributes['data-wpacu-style-handle']]->extra;
							}

							$sourceRelPath = OptimizeCommon::getSourceRelPath($href);

							$alreadyAddedSourceRelPath = isset($combinedUriPathsGroup[$groupKey]) && in_array($sourceRelPath, $combinedUriPathsGroup[$groupKey]);
							if (! $alreadyAddedSourceRelPath) {
								$combinedUriPathsGroup[$groupKey][] = $sourceRelPath;
							}

							$localAssetsPathsGroup[$groupKey][$href] = $localAssetPath;
                            $tagKeysByHref[$groupKey][$href][] = $tagKey;

							$alreadyAddedHref = isset($linkHrefsGroup[$groupKey]) && in_array($href, $linkHrefsGroup[$groupKey]);
							if (! $alreadyAddedHref) {
								$linkHrefsGroup[$groupKey][] = $href;
							}

							$localAssetsExtraGroup[$groupKey][$href] = $styleExtra;
						}
					}
				}

				// No Link Tags or only one tag in the combined group? Do not proceed with any combining
				if ( empty( $combinedUriPathsGroup ) ) {
					continue;
				}

				foreach ($combinedUriPathsGroup as $groupKey => $combinedUriPaths) {
					$mediaValue = $groupMedia[$groupKey];
					// There have to be at least two CSS files to create a combined CSS file
					if (count($combinedUriPaths) < 2) {
						continue;
					}

					$localAssetsPaths = $localAssetsPathsGroup[$groupKey];
					$linkHrefs = $linkHrefsGroup[$groupKey];
					$localAssetsExtra = array_filter($localAssetsExtraGroup[$groupKey]);

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
                        $tagKeys = array();
                        foreach ($linkHrefs as $originalIndexKey => $href) {
                            $indexKey = $originalIndexKey; // default

                            if (strpos($href, OptimizeCommon::getRelPathPluginCacheDir()) === false) {
                                $localFilePath = OptimizeCommon::getLocalAssetPath($href, 'css');

                                // Unique Mark: SHA1 of the file contents + a unique ID (in rare cases when a different file has the same content)
                                $indexKey      = OptimizeCommon::getCombineFileHash($localFilePath, $sourceHashes) . '_'. uniqid('', true);
                            }

                            // CSS generation deduplicates identical URLs. Preserve repeated live stylesheets.
                            $keysForHref = $tagKeysByHref[$groupKey][$href];
                            $tagKeys[$indexKey] = count($keysForHref) === 1 ? $keysForHref[0] : '';
                            $href = str_replace('{site_url}', '', OptimizeCommon::getSourceRelPath($href, true));

                            unset($linkHrefs[$originalIndexKey]);
                            $linkHrefs[$indexKey] = $href;
                        }

                        $storageJsonContents[$docLocationTag][$groupKey] = array(
							'media' => $mediaValue,
							'source_signature' => $sourceSignature,
							'uri_to_final_css_file' => $uriToFinalCssFile,
							'tag_keys'              => $tagKeys,
							'link_hrefs'            => $linkHrefs
						);

						$storageJsonContentsToSave[$docLocationTag][$groupKey] = array(
							'media' => $mediaValue,
							'source_signature' => $sourceSignature,
							'uri_to_final_css_file' => $uriToFinalCssFile,
							'tag_keys'              => $tagKeys,
							'link_hrefs'            => $linkHrefs
						);
					}
				}
			}

			libxml_clear_errors();


            OptimizeCommon::attachCombineValidation($storageJsonContentsToSave, $documentSignature, $sourceFiles);
			OptimizeCommon::setAssetCachedData(
				self::$jsonStorageFile,
				OptimizeCss::getRelPathCssCacheDir(),
				wp_json_encode($storageJsonContentsToSave)
			);
		}

		$cdnUrls = OptimizeCommon::getAnyCdnUrls();
		$cdnUrlForCss = isset($cdnUrls['css']) ? $cdnUrls['css'] : false;

		if ( ! empty($storageJsonContents) ) {
            $tagIndex = new CombineTagIndex($htmlSource, 'css');
            $edits    = array();
            $preloads = array();
			foreach ($storageJsonContents as $docLocationTag => $mediaValues) {
				$groupLocation = 1;

				foreach ($mediaValues as $mediaValue => $storageJsonContentLocation) {
					$mediaValue = $storageJsonContentLocation['media'];
					if (empty($storageJsonContentLocation['tag_keys']) || empty($storageJsonContentLocation['link_hrefs'])
                        || ! is_array($storageJsonContentLocation['link_hrefs'])) {
                        continue;
					}

					// Irrelevant to have only one CSS file in a combine CSS group
					if (count($storageJsonContentLocation['link_hrefs']) < 2) {
						continue;
					}

					$group = $tagIndex->getGroup(
                        $storageJsonContentLocation['link_hrefs'], $storageJsonContentLocation['tag_keys'], 'css', $sourceHashes
                    );
                    if ( ! $group ) {
                        continue;
                    }

                    $finalTagUrl = OptimizeCommon::filterWpContentUrl($cdnUrlForCss) . OptimizeCss::getRelPathCssCacheDir() . $storageJsonContentLocation['uri_to_final_css_file'];

					$finalCssTagAttrs = array();

                    $isBasicPreload = strncmp($mediaValue, 'wpacu_preload_basic_', 20) === 0;
                    if ($isBasicPreload) {
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
					} else {
						// Render-blocking CSS
						$finalCssTag = <<<HTML
<link rel='stylesheet' id='wpacu-combined-css-{$docLocationTag}-{$groupLocation}' href='{$finalTagUrl}' type='text/css' media='{$mediaValue}' />
HTML;
						$finalCssTagAttrs['rel']   = 'stylesheet';
						$finalCssTagAttrs['media'] = $mediaValue;
					}

					$customFinalCssTagData = apply_filters(
						'wpacu_internal_combine_css_final_tag_data',
						array(
							'final_css_tag'       => $finalCssTag,
							'final_css_tag_attrs' => $finalCssTagAttrs,
							'media_value'         => $mediaValue
						),
						array(
							'media_value'    => $mediaValue,
							'doc_location'   => $docLocationTag,
							'group_location' => $groupLocation,
							'final_tag_url'  => $finalTagUrl
						)
					);

					if (is_array($customFinalCssTagData)) {
						if (isset($customFinalCssTagData['final_css_tag'])) {
							$finalCssTag = $customFinalCssTagData['final_css_tag'];
						}

						if (isset($customFinalCssTagData['final_css_tag_attrs'])) {
							$finalCssTagAttrs = $customFinalCssTagData['final_css_tag_attrs'];
						}

						if (isset($customFinalCssTagData['media_value'])) {
							$mediaValue = $customFinalCssTagData['media_value'];
						}
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

                    $groupEdits = array();
                    foreach ($group as $index => $link) {
                        $groupEdits[$link['offset']] = array($link['length'], $index === 0 ? $finalCssTag : '');
                        $attributes = $link['attributes'];
                        if ($isBasicPreload) {
                            foreach ($tagIndex->getBasicPreloads($attributes['href']) as $preload) {
                                $groupEdits[$preload['offset']] = array($preload['length'], '');
                            }
                        }

                        if ( ! isset($attributes['data-wpacu-style-handle']) || ! OptimizeCommon::appendInlineCodeToCombineAssetType('css') ) {
                            continue;
                        }
                        $handle = $attributes['data-wpacu-style-handle'];
                        foreach ($tagIndex->getInlineStyles($handle) as $inline) {
                            $end      = $link['offset'] + $link['length'];
                            $gap      = $inline['offset'] - $end;
                            $expected = trim(OptimizeCss::generateInlineAssocHtmlForHandle($handle));
                            if (($gap !== 0 && ($gap !== 1 || $htmlSource[$end] !== "\n"))
                                || $expected !== substr($htmlSource, $inline['offset'], $inline['length'])) {
                                continue 3;
                            }
                            $groupEdits[$inline['offset']] = array($inline['length'], '');
                        }
                    }

                    $edits += $groupEdits;
                    if ($isBasicPreload) {
                        $preloads[] = $finalCssTagRelPreload;
                    }
                    $groupLocation++;
				}
			}
            $htmlSource = CombineTagIndex::applyEdits($htmlSource, $edits);
            if ($preloads) {
                $htmlSource = str_replace(Preloads::DEL_STYLES_PRELOADS, implode("\n", $preloads) . "\n" . Preloads::DEL_STYLES_PRELOADS, $htmlSource);
            }
		}

		return $htmlSource;
	}

	/**
	 * @param $filesSources
	 * @param $wpacuRegisteredStyles
	 * @param $htmlSource
     * @param array $alreadyReplacedSources Sources replaced by the combined tag before this pass.
	 *
	 * @return mixed
	 */
	public static function stripJustCombinedLinkTags($filesSources, $wpacuRegisteredStyles, $htmlSource, $alreadyReplacedSources = array(), &$sourceHashes = array())
	{
		preg_match_all('#<link[^>]*(stylesheet|preload)[^>]*(>)#Umi', $htmlSource, $matchesSourcesFromTags, PREG_SET_ORDER);

		$strippedSources = array_fill_keys($alreadyReplacedSources, true);
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

                                if ($sha1File !== OptimizeCommon::getCombineFileHash(OptimizeCommon::getLocalAssetPath($relNodeValue, 'css'), $sourceHashes)) {
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
								$strippedSources[$relNodeValue] = true;
							}
							}
					}
				}
			}

			libxml_clear_errors();
		}

		// Count source URLs, including the first tag already replaced by the caller.
        // A preload and stylesheet for the same source must not mask a missing file.
		if ($clearCombinedCssCache || array_diff($filesSources, array_keys($strippedSources))) {
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
        $href = trim((string)$href);

        if ($href === '') {
            return false;
        }

        $rules = array(
            '/wp-content/bs-booster-cache/'
        );

        if (Main::instance()->settings['combine_loaded_css_exceptions'] !== '') {
            $rules = array_merge(
                $rules,
                Regex::splitRules(Main::instance()->settings['combine_loaded_css_exceptions'])
            );
        }

        return Regex::matchesAnyRule($rules, $href);
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

                    if (OptimizeCss::hasImportRule($cssContent)) {
                        // A source changed during generation, or a direct caller supplied an unresolved import.
                        return array('uri_final_css_file' => '', 'local_final_css_file' => '', 'link_hrefs' => array());
                    }

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

			// Apply the configured Google Fonts import removal.
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
     * Only move attached CSS when the existing tag-removal path can remove its exact bytes.
     *
     * @param \DOMElement $inlineTag
     * @param string $htmlSource
     * @param array $registeredStyles
     * @param array $inlineTagCounts
     * @param array $linkTagCounts
     *
     * @return bool
     */
    private static function canRemoveAttachedInlineStyle($inlineTag, $htmlSource, $registeredStyles, $inlineTagCounts = array(), $linkTagCounts = array())
    {
        $handle  = substr($inlineTag->getAttribute('id'), 0, -11);
        $linkTag = $inlineTag->previousSibling;

        if ( ! $inlineTagCounts ) {
            foreach ($inlineTag->ownerDocument->getElementsByTagName('style') as $styleTag) {
                $id = $styleTag->getAttribute('id');
                $inlineTagCounts[$id] = isset($inlineTagCounts[$id]) ? $inlineTagCounts[$id] + 1 : 1;
            }
        }

        if ($inlineTagCounts[$inlineTag->getAttribute('id')] !== 1) {
            return false;
        }

        while ($linkTag && $linkTag->nodeType === XML_TEXT_NODE && trim($linkTag->nodeValue) === '') {
            $linkTag = $linkTag->previousSibling;
        }

        if ( ! $linkTag || $linkTag->nodeName !== 'link'
             || $linkTag->getAttribute('data-wpacu-style-handle') !== $handle ) {
            return false;
        }

        if ( ! $linkTagCounts ) {
            foreach ($inlineTag->ownerDocument->getElementsByTagName('link') as $sourceTag) {
                foreach (array('href', 'data-wpacu-style-handle') as $attribute) {
                    $key = $attribute . ':' . $sourceTag->getAttribute($attribute);
                    $linkTagCounts[$key] = isset($linkTagCounts[$key]) ? $linkTagCounts[$key] + 1 : 1;
                }
            }
        }

        if ($linkTagCounts['href:' . $linkTag->getAttribute('href')] !== 1
            || $linkTagCounts['data-wpacu-style-handle:' . $handle] !== 1) {
            return false;
        }

        $linkHtml = OptimizeCss::getFirstLinkTag($linkTag->getAttribute('href'), $htmlSource);

        if ( ! $linkHtml ) {
            return false;
        }

        $inline = OptimizeCss::getInlineAssociatedWithLinkHandle($linkHtml, $registeredStyles, 'tag', 'html');

        if ( empty($inline['after']) || $inline['after'] !== OptimizeCss::generateInlineAssocHtmlForHandle($handle) ) {
            return false;
        }

        return strpos($htmlSource, $linkHtml . "\n" . $inline['after']) !== false
               || strpos($htmlSource, $linkHtml . trim($inline['after'])) !== false;
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
