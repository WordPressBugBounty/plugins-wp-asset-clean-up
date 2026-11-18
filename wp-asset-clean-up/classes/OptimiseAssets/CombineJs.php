<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\MainFront;
use WpAssetCleanUp\FileSystem;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\ObjectCache;
use WpAssetCleanUp\Regex;

/**
 * Class CombineJs
 * @package WpAssetCleanUp\OptimiseAssets
 */
class CombineJs
{
	/**
	 * @var string
	 */
	public static $jsonStorageFile = 'js-combined-v2{maybe-extra-info}.json';

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

		global $wp_scripts;
		$wpacuRegisteredScripts = $wp_scripts->registered;

		$combineLevel = 2;

		// $uriToFinalJsFile will always be relative ONLY within WP_CONTENT_DIR . self::getRelPathJsCacheDir()
		// which is usually "wp-content/cache/asset-cleanup/js/"

		// "true" would make it avoid checking the cache and always use the DOM Parser / RegExp
		// for DEV purposes ONLY as it uses more resources
		$finalCacheList = array();
		$skipCache = false;
        $sourceHashes = $inertSources = $sourceFiles = $registeredInline = array();
        // Resolve translations once for this operation, including missing payloads.
        // A subsequent combine starts fresh for file edits, locale or registry changes.
        $resolvedTranslations = array();
        $documentSignature = sha1('js-data-boundary-v1' . OptimizeCommon::getCombineDocumentSignature($htmlSource));

		if (isset($_GET['wpacu_no_cache']) || wpacuIsDefinedConstant('WPACU_NO_CACHE')) {
			$skipCache = true;
		}

        // If the cache is not skipped, read the information from the cache as it's much faster
        if (! $skipCache) {
			// Speed up processing by getting the already existing final JS file URI
			// Reuse bundle metadata only after validating the current document and source contents.
			$finalCacheList = OptimizeCommon::getAssetCachedData( self::$jsonStorageFile, OptimizeJs::getRelPathJsCacheDir(), 'js' );
		}

        // Forced body defer depends on current registered inline code and skip filters;
        // keep its live DOM analysis rather than caching that policy decision.
        if (Main::instance()->settings['combine_loaded_js_defer_body']
            || ! OptimizeCommon::combineCacheMatchesDocument($finalCacheList, $documentSignature, 'js', $sourceHashes, $resolvedTranslations)) {
            $domTag = OptimizeCommon::getDomLoadedTag($htmlSource, 'combineJs');
            $sourceSignature = sha1('js-data-boundary-v1' . OptimizeCommon::getCombineSourceSignature($domTag, 'js', $sourceHashes, $inertSources, $sourceFiles, $registeredInline, $resolvedTranslations));
            if (! OptimizeCommon::combineCacheMatchesSource($finalCacheList, $sourceSignature)) {
                $finalCacheList = array();
            }
        }

		if ( $skipCache || empty($finalCacheList) ) {
            $preserveInlineHandles = array();
            foreach ((new \DOMXPath($domTag))->query('//script[not(@src) and not(ancestor::template)]') as $inlineTag) {
                $inlineHandle = self::getInlineScriptHandle($inlineTag);
                if ($inlineHandle && isset($wpacuRegisteredScripts[$inlineHandle])
                    && ! self::isAttachedInlineScript($inlineTag, $wpacuRegisteredScripts, $resolvedTranslations)) {
                    $preserveInlineHandles[$inlineHandle] = true;
                }
            }
			/*
			 * NO CACHING TRANSIENT; Parse the DOM
			*/
			// Nothing in the database records or the retrieved cached file does not exist?
			OptimizeCommon::clearAssetCachedData(self::$jsonStorageFile);

			$combinableList = array();

			$jQueryMigrateInBody = false;
			$jQueryLibInBodyCount = 0;

			// Reuse the parsed document, including inline scripts which delimit groups.

			// Only keep combinable JS files
			foreach ( array( 'head', 'body' ) as $docLocationScript ) {
				$groupIndex = 1;
				$previousGroupType = null;

				$docLocationElements = $domTag->getElementsByTagName($docLocationScript)->item(0);
				if ($docLocationElements === null) { continue; }

				// High accuracy (e.g. it ignores tags inside HTML comments, conditional or not)
				$scriptTags = (new \DOMXPath($domTag))->query('.//script[not(ancestor::template)]', $docLocationElements);
				if ($scriptTags === null) { continue; }

				if (Main::instance()->settings['combine_loaded_js_defer_body']) {
					ObjectCache::wpacu_cache_set('wpacu_html_dom_body_tag_for_js', $docLocationElements);
				}

				foreach ($scriptTags as $tagObject) {
					$scriptAttributes = array();

					if ( ! empty($tagObject->attributes) ) {
						foreach ( $tagObject->attributes as $attrObj ) {
							$scriptAttributes[ $attrObj->nodeName ] = trim( $attrObj->nodeValue );
						}
					}

					$scriptNotCombinable = false; // default (usually, most of the SCRIPT tags can be optimized)

                    $scriptType = isset($scriptAttributes['type']) ? $scriptAttributes['type'] : '';

                    // Combining would convert modules to classic scripts and would drop nomodule semantics.
                    if ( ! Misc::isClassicScriptType($scriptType) || isset($scriptAttributes['nomodule']) ) {
                        $scriptNotCombinable = true;
                    }

                    // The combined tag does not retain per-file security/request attributes.
                    foreach (array('integrity', 'nonce', 'crossorigin', 'referrerpolicy') as $attributeToPreserve) {
                        if (isset($scriptAttributes[$attributeToPreserve])) {
                            $scriptNotCombinable = true;
                            break;
                        }
                    }

					// Check if the CSS file has any 'data-wpacu-skip' attribute; if it does, do not alter it
					if (isset($scriptAttributes['data-wpacu-skip']) || apply_filters('wpacu_internal_combine_js_skip_script_tag', false, $scriptAttributes)) {
						$scriptNotCombinable = true;
					}

					$handleToCheck  = isset($scriptAttributes['data-wpacu-script-handle']) ? $scriptAttributes['data-wpacu-script-handle'] : ''; // Maybe: JS Inline (Before, After)
                    if (isset($preserveInlineHandles[$handleToCheck])) { $scriptNotCombinable = true; }
                    $hasSrc         = isset($scriptAttributes['src']) && trim($scriptAttributes['src']); // No valid SRC attribute? It's not combinable (e.g. an inline tag)
                    $src            = $hasSrc ? (string)$scriptAttributes['src'] : '';
                    if ($hasSrc && isset($inertSources[OptimizeCommon::getCombineSourceKey($src)])) { $scriptNotCombinable = true; }
                    $isPluginScript = isset($scriptAttributes['data-wpacu-plugin-script']); // Only of the user is logged-in (skip it as it belongs to the Asset CleanUp (Pro) plugin)

					if (! $scriptNotCombinable && (! $hasSrc || $isPluginScript)) {
                        if (! $hasSrc && self::isAttachedInlineScript($tagObject, $wpacuRegisteredScripts, $resolvedTranslations)) {
                            continue;
                        }
                        // Independent inline code must run between the same external scripts.
                        $scriptNotCombinable = true;
                    }

					$isInGroupType = 'standard';
					$isJQueryLib = $isJQueryMigrate = false;

					// Has SRC and $isPluginScript is set to false OR it does not have "data-wpacu-skip" attribute
					if (! $scriptNotCombinable) {
						if (self::skipCombine($src, $handleToCheck)) {
							$scriptNotCombinable = true;
						}

						// Avoid any errors when code like the following one is used:
						// wp.i18n.setLocaleData( localeData, domain );
						// Because the inline JS is not appended to the combined JS, /wp-includes/js/dist/i18n.(min).js has to be called earlier (outside the combined JS file)
						if ( ! OptimizeCommon::appendInlineCodeToCombineAssetType('js') && (strpos($src, '/wp-includes/js/dist/i18n.') !== false) ) {
							$scriptNotCombinable = true;
						}

						if (isset($scriptAttributes['data-wpacu-to-be-preloaded-basic']) && $scriptAttributes['data-wpacu-to-be-preloaded-basic']) {
							$scriptNotCombinable = true;
						}

						// Was it optimized and has the URL updated? Check the Source URL
						if (! $scriptNotCombinable && isset($scriptAttributes['data-wpacu-script-rel-src-before']) && $scriptAttributes['data-wpacu-script-rel-src-before'] && self::skipCombine($scriptAttributes['data-wpacu-script-rel-src-before'], $handleToCheck)) {
							$scriptNotCombinable = true;
						}

						$isJQueryLib     = isset($scriptAttributes['data-wpacu-jquery-core-handle']);
						$isJQueryMigrate = isset($scriptAttributes['data-wpacu-jquery-migrate-handle']);

						if (isset($scriptAttributes['async'], $scriptAttributes['defer'])) { // Has both "async" and "defer"
							$isInGroupType = 'async_defer';
						} elseif (isset($scriptAttributes['async'])) { // Has only "async"
							$isInGroupType = 'async';
						} elseif (isset($scriptAttributes['defer'])) { // Has only "defer"
							// Preserve an original defer strategy while groups are built. BODY defer is applied
							// positionally only after the final HTML is known, so synchronous and deferred
							// scripts that precede a surviving inline boundary are never collapsed together.
							$isInGroupType = 'defer';
						}
					}

					if ( ! $scriptNotCombinable ) {
						// It also checks the domain name to make sure no external scripts would be added to the list
						if ( $localAssetPath = OptimizeCommon::getLocalAssetPath( $src, 'js' ) ) {
							// Keep program-level strict mode isolated; wrapping would change global bindings.
							if (self::hasStrictModeDirective(FileSystem::fileGetContents($localAssetPath))) {
								$groupIndex++;
								$previousGroupType = null;
								continue;
							}
							$scriptExtra = array();

							if ( isset( $scriptAttributes['data-wpacu-script-handle'], $wpacuRegisteredScripts[ $scriptAttributes['data-wpacu-script-handle'] ]->extra ) && OptimizeCommon::appendInlineCodeToCombineAssetType('js') ) {
								$scriptExtra = $wpacuRegisteredScripts[ $scriptAttributes['data-wpacu-script-handle'] ]->extra;

								$anyScriptTranslations = OptimizeJs::getScriptTranslations($scriptAttributes['data-wpacu-script-handle'], $resolvedTranslations);

								if ( $anyScriptTranslations ) {
									$scriptExtra['translations'] = $anyScriptTranslations;
								}
							}

							// Standard (could be multiple groups per $docLocationScript), Async & Defer, Async, Defer
							if ($previousGroupType !== $isInGroupType) { $groupIndex++; }
							$previousGroupType = $isInGroupType;
							$groupByType = $groupIndex.'|'.$isInGroupType;

							if ($docLocationScript === 'body') {
								if ($isJQueryLib || strpos($localAssetPath, '/wp-includes/js/jquery/jquery.js') !== false) {
									$jQueryLibInBodyCount++;
								}

								if ($isJQueryMigrate || strpos($localAssetPath, '/wp-includes/js/jquery/jquery-migrate') !== false) {
									$jQueryLibInBodyCount++;
									$jQueryMigrateInBody = true;
								}
							}

							$combinableList[$docLocationScript][$groupByType][] = array(
								'src'   => $src,
								'local' => $localAssetPath,
								'info'  => array(
									'is_jquery'         => $isJQueryLib,
									'is_jquery_migrate' => $isJQueryMigrate
								),
								'extra' => $scriptExtra
							);

							if ($docLocationScript === 'body' && $jQueryLibInBodyCount === 2) {
								$jQueryLibInBodyCount = 0; // reset it
								$groupIndex ++; // a new JS group will be created if jQuery & jQuery Migrate are combined in the BODY
							}
						} else {
							// A remote or unresolved script must keep its position between local groups.
							$groupIndex++;
							$previousGroupType = null;
						}
					} else {
						$groupIndex ++; // a new JS group will be created (applies to "standard" ones only)
						$previousGroupType = null;
					}
				}
			}

			// Could be pages such as maintenance mode with no external JavaScript files
			if (empty($combinableList)) {
				return $htmlSource;
			}

			$finalCacheList = array();

			foreach ($combinableList as $docLocationScript => $combinableListGroups) {
				$groupNo = 1;

				foreach ($combinableListGroups as $groupType => $groupFiles) {
					list(, $groupType) = explode('|', $groupType, 2);
					// Any groups having one file? Then it's not really a group and the file should load on its own
					// Could be one extra file besides the jQuery & jQuery Migrate group or the only JS file called within the HEAD
					if (count($groupFiles) < 2) {
						continue;
					}

					$localAssetsPaths = $groupScriptSrcs = array();
					$localAssetsExtra = array();
					$jQueryIsIncludedInGroup = false;

					foreach ($groupFiles as $groupFileData) {
						if ($groupFileData['info']['is_jquery'] || strpos($groupFileData['local'], '/wp-includes/js/jquery/jquery.js') !== false) {
							$jQueryIsIncludedInGroup = true;

							// Is jQuery in the BODY without jQuery Migrate loaded?
							// Isolate it as it needs to be the first to load in case there are inline scripts calling it before the combined group(s)
							if ($docLocationScript === 'body' && ! $jQueryMigrateInBody) {
								continue;
							}
						}

						$src                    = $groupFileData['src'];
						$groupScriptSrcs[]      = $src;
						$localAssetsPaths[$src] = $groupFileData['local'];
						$localAssetsExtra[$src] = $groupFileData['extra'];
					}

					$maybeDoJsCombine = self::maybeDoJsCombine(
						$localAssetsPaths,
						$localAssetsExtra,
						$docLocationScript
					);

					// Local path to combined CSS file
					$localFinalJsFile = $maybeDoJsCombine['local_final_js_file'];

					// URI (e.g. /wp-content/cache/asset-cleanup/[file-name-here.js]) to the combined JS file
					$uriToFinalJsFile = $maybeDoJsCombine['uri_final_js_file'];

					if (! is_file($localFinalJsFile)) {
						return $htmlSource; // something is not right as the file wasn't created, we will return the original HTML source
					}

                    foreach ($groupScriptSrcs as $originalIndexKey => $src) {
                        $indexKey = $originalIndexKey; // default

                        if (strpos($src, OptimizeCommon::getRelPathPluginCacheDir()) === false) {
                            $localFilePath = OptimizeCommon::getLocalAssetPath($src, 'js');

                            // Unique Mark: SHA1 of the file contents + a unique ID (in rare cases when a different file has the same content)
                            $indexKey      = OptimizeCommon::getCombineFileHash($localFilePath, $sourceHashes) . '_'. uniqid('', true);
                        }

                        $src = str_replace('{site_url}', '', OptimizeCommon::getSourceRelPath($src, true));

                        unset($groupScriptSrcs[$originalIndexKey]);
                        $groupScriptSrcs[$indexKey] = $src;
                    }

					$finalCacheList[$docLocationScript][$groupNo] = array(
						'source_signature' => $sourceSignature,
						'uri_to_final_js_file' => $uriToFinalJsFile,
						'script_srcs'          => $groupScriptSrcs
					);

					if (in_array($groupType, array('async_defer', 'async', 'defer'))) {
						if ($groupType === 'async_defer') {
							$finalCacheList[$docLocationScript][$groupNo]['extra_attributes'][] = 'async';
							$finalCacheList[$docLocationScript][$groupNo]['extra_attributes'][] = 'defer';
						} else {
							$finalCacheList[$docLocationScript][$groupNo]['extra_attributes'][] = $groupType;
						}
					}

					// BODY defer is applied after the groups have been materialized in the final HTML.
					// Keep a private marker for the jQuery group, which intentionally remains blocking.
					if ($docLocationScript === 'body' && $jQueryIsIncludedInGroup) {
						$finalCacheList[$docLocationScript][$groupNo]['skip_positional_body_defer'] = true;
					}

					$groupNo ++;
				}
			}

			OptimizeCommon::attachCombineValidation($finalCacheList, $documentSignature, $sourceFiles, $registeredInline);
			OptimizeCommon::setAssetCachedData(self::$jsonStorageFile, OptimizeJs::getRelPathJsCacheDir(), wp_json_encode($finalCacheList));
		}

		if (! empty($finalCacheList)) {
			$cdnUrls = OptimizeCommon::getAnyCdnUrls();
			$cdnUrlForJs = isset($cdnUrls['js']) ? $cdnUrls['js'] : false;

            $clearCombinedJsCache = false; // default

			foreach ( $finalCacheList as $docLocationScript => $cachedGroupsList ) {
				foreach ($cachedGroupsList as $groupNo => $cachedValues) {
					$htmlSourceBeforeGroupReplacement = $htmlSource;

					$uriToFinalJsFile = $cachedValues['uri_to_final_js_file'];
					$filesSources = $cachedValues['script_srcs'];

					// Basic Combining (1) -> replace "first" tag with the final combination tag (there would be most likely multiple groups)
					// Enhanced Combining (2) -> replace "last" tag with the final combination tag (most likely one group)
					$indexReplacement = ($combineLevel === 2) ? (count($filesSources) - 1) : 0;

					$finalTagUrl = OptimizeCommon::filterWpContentUrl($cdnUrlForJs) . OptimizeJs::getRelPathJsCacheDir() . $uriToFinalJsFile;

					$finalJsTagAttrsOutput = '';
					$extraAttrs = array();

					if (Main::instance()->settings['combine_loaded_js_defer_body'] && ! empty($cachedValues['skip_positional_body_defer']) ) {
						$finalJsTagAttrsOutput .= ' data-wpacu-skip-positional-body-defer=\'1\' ';
					}

					if ( ! empty($cachedValues['extra_attributes']) ) {
						$extraAttrs = $cachedValues['extra_attributes'];
						foreach ($extraAttrs as $finalJsTagAttr) {
							$finalJsTagAttrsOutput .= ' '.$finalJsTagAttr.'=\''.$finalJsTagAttr.'\' ';
						}
						$finalJsTagAttrsOutput = trim($finalJsTagAttrsOutput);
					}

					// No async or defer? Add the preloading for the combined JS from the BODY
					if (empty($extraAttrs) && $docLocationScript === 'body' ) {
						$finalJsTagAttrsOutput .= ' data-wpacu-to-be-preloaded-basic=\'1\' ';
                        wpacuDefineConstant('WPACU_REAPPLY_PRELOADING_FOR_COMBINED_JS');
					}

					// e.g. For developers that might want to add custom attributes such as data-cfasync="false"
					$finalJsTag = apply_filters(
						'wpacu_combined_js_tag',
						'<script '.$finalJsTagAttrsOutput.' '.Misc::getScriptTypeAttribute().' id=\'wpacu-combined-js-'.$docLocationScript.'-group-'.$groupNo.'\' src=\''.$finalTagUrl.'\'></script>',
						array(
							'attrs'        => $extraAttrs,
							'doc_location' => $docLocationScript,
							'group_no'     => $groupNo,
							'src'          => $finalTagUrl
						)
					);

					// Reference: https://stackoverflow.com/questions/2368539/php-replacing-multiple-spaces-with-a-single-space
					$finalJsTag = preg_replace('!\s+!', ' ', $finalJsTag);

					$scriptTagsStrippedNo = 0;

					$scriptTags = OptimizeJs::getScriptTagsFromSrcs($filesSources, $htmlSource);

					foreach ($scriptTags as $groupScriptTagIndex => $scriptTag) {
						$replaceWith = ($groupScriptTagIndex === $indexReplacement) ? $finalJsTag : '';
						$htmlSourceBeforeTagReplacement = $htmlSource;

                        $srcTagAttr = Misc::getValueFromTag($scriptTag, 'src');
                        $srcRelCleanPath = trim(OptimizeCommon::getSourceRelPath($srcTagAttr, true));

                        $indexKey = array_search($srcRelCleanPath, $filesSources);

                        // Check the SHA1 value of the file if it's in the original location (not within the caching directory)
                        if ($indexKey !== false &&
                            strpos($indexKey, '_') !== false &&
                            strpos($srcRelCleanPath, OptimizeCommon::getRelPathPluginCacheDir()) === false) {
                            list($sha1File) = explode('_', $indexKey);

                            if ($sha1File !== OptimizeCommon::getCombineFileHash(OptimizeCommon::getLocalAssetPath($srcRelCleanPath, 'js'), $sourceHashes)) {
                                // The contents of one of the files from the combined CSS were changed
                                $clearCombinedJsCache = true;
                                break 3;
                            }
                        }

						// 1) Strip any inline code associated with tag
						// 2) Finally, strip the actual tag
						$htmlSource = self::stripTagAndAnyInlineAssocCode( $scriptTag, $wpacuRegisteredScripts, $replaceWith, $htmlSource, $resolvedTranslations );

						if ($htmlSource !== $htmlSourceBeforeTagReplacement) {
							$scriptTagsStrippedNo ++;
						}
						}

					// At least two tags have to be stripped from the group to consider doing the group replacement
					// If the tags weren't replaced it's likely there were changes to their structure after they were cached for the group merging
					if (count($filesSources) !== $scriptTagsStrippedNo) {
						$htmlSource = $htmlSourceBeforeGroupReplacement;

                        $clearCombinedJsCache = true;
					}
				}
			}

            if ($clearCombinedJsCache) {
                OptimizeCommon::clearAssetCachedData(self::$jsonStorageFile);
                }
		}

		// Apply BODY defer only after bundle replacement, when we know which inline scripts
		// actually survived in the document. This preserves the original execution order
		// across synchronous inline boundaries without letting unrelated inline code disable
		// defer for the whole BODY.
		if ( ! empty($finalCacheList['body']) && Main::instance()->settings['combine_loaded_js_defer_body'] ) {
			$htmlSource = self::applyPositionalBodyDefer($htmlSource);
		}

		libxml_clear_errors();

		// Finally, return the HTML source
		return $htmlSource;
	}


    /**
     * Add defer only to eligible BODY scripts that occur after the last surviving
     * synchronous classic inline script. Existing defer/async strategies are preserved.
     *
     * The decision is deliberately made on the final HTML: registered inline code that
     * was absorbed into a real bundle is gone and cannot create a false boundary, while
     * an attached `after` block whose handle stayed standalone is still present and keeps
     * its external script blocking.
     *
     * @param string $htmlSource
     * @return string
     */
    public static function applyPositionalBodyDefer($htmlSource)
    {
        $scripts = HtmlCleanup::getBodyScriptTokens($htmlSource);
        if ($scripts === false) {
            return $htmlSource;
        }

        $lastBarrierEnd = 0;
        foreach ($scripts as $script) {
            if ($script['manager']) {
                continue;
            }
            $attributes = $script['attributes'];
            $src        = isset($attributes['src']) ? trim($attributes['src']) : '';
            $type       = isset($attributes['type']) ? trim($attributes['type'], "\x09\x0a\x0c\x0d\x20") : '';

            if ($src !== '') {
                // An explicitly synchronous skipped script must not overtake a dependency.
                if ( ! isset($attributes['defer']) && ! isset($attributes['async'])
                    && isset($attributes['data-wpacu-skip']) && ! isset($attributes['data-wpacu-plugin-script'])) {
                    $lastBarrierEnd = $script['offset'] + $script['length'];
                }
                continue;
            }
            if (isset($attributes['data-wpacu-own-inline-script']) || ! Misc::isClassicScriptType($type)) {
                continue;
            }
            if (trim($script['content']) !== '') {
                $lastBarrierEnd = $script['offset'] + $script['length'];
            }
        }

        $replacements = array();
        foreach ($scripts as $script) {
            if ($script['manager']) {
                continue;
            }
            $attributes = $script['attributes'];
            $src        = isset($attributes['src']) ? trim($attributes['src']) : '';
            $type       = isset($attributes['type']) ? trim($attributes['type'], "\x09\x0a\x0c\x0d\x20") : '';
            $addDefer   = $script['offset'] >= $lastBarrierEnd && $src !== '' && Misc::isClassicScriptType($type);
            foreach (array('defer', 'async', 'nomodule', 'data-wpacu-skip', 'data-wpacu-plugin-script', 'data-wpacu-skip-positional-body-defer') as $name) {
                if (isset($attributes[$name])) {
                    $addDefer = false;
                }
            }

            $opening = $script['opening'];
            $insert  = strlen($opening) - 1;
            $ranges  = $script['attribute_ranges'];
            $last    = $ranges ? end($ranges) : null;
            // A slash in an unquoted value belongs to that value, not to '/>'.
            if (substr($opening, -2) === '/>' && ( ! $last || $last['offset'] + $last['length'] <= $insert - 1)) {
                --$insert;
            }
            for ($i = count($ranges) - 1; $i >= 0; --$i) {
                $range = $ranges[$i];
                if ($range['name'] === 'data-wpacu-skip-positional-body-defer'
                    || ($addDefer && $range['name'] === 'data-wpacu-to-be-preloaded-basic')) {
                    $opening = substr_replace($opening, '', $range['offset'], $range['length']);
                    $insert -= $range['length'];
                }
            }
            if ($addDefer) {
                $opening = substr_replace($opening, " defer='defer'", $insert, 0);
            }
            if ($opening !== $script['opening']) {
                $replacements[] = array($script['offset'], strlen($script['opening']), $opening);
            }
        }

        // Replace only complete opening tags, from the end, preserving every other byte.
        for ($i = count($replacements) - 1; $i >= 0; --$i) {
            list($offset, $length, $opening) = $replacements[$i];
            $htmlSource = substr_replace($htmlSource, $opening, $offset, $length);
        }
        return $htmlSource;
    }

	/**
	 * @param string $src
	 * @param string $handle
     *
	 * @return bool
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function skipCombine($src, $handle = '')
    {
        $src = trim((string)$src);

        if ($src === '') {
            return false;
        }

        // In case the handle was appended
        if ($handle !== '' && in_array($handle, MainFront::instance()->getSkipAssets('scripts'))) {
            return true;
        }

        $rules = array(
            '/wp-content/bs-booster-cache/'
        );

        if (Main::instance()->settings['combine_loaded_js_exceptions'] !== '') {
            $rules = array_merge(
                $rules,
                Regex::splitRules(Main::instance()->settings['combine_loaded_js_exceptions'])
            );
        }

        return Regex::matchesAnyRule($rules, $src);
    }

	/**
	 * @param $localAssetsPaths
	 * @param $localAssetsExtra
	 * @param $docLocationScript
	 *
	 * @return array
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function maybeDoJsCombine($localAssetsPaths, $localAssetsExtra, $docLocationScript)
	{
		// Only combine if $shaOneCombinedUriPaths.js does not exist
		// If "?ver" value changes on any of the assets or the asset list changes in any way
		// then $shaOneCombinedUriPaths will change too and a new JS file will be generated and loaded

		// Change $assetsContents as paths to fonts and images that are relative (e.g. ../, ../../) have to be updated
		$uriToFinalJsFile = $localFinalJsFile = $finalJsContents = '';

		foreach ($localAssetsPaths as $assetHref => $localAssetsPath) {
			if ($jsContent = trim(FileSystem::fileGetContents($localAssetsPath))) {
				// Does it have a source map? Strip it
				if (strpos($jsContent, '//# sourceMappingURL=') !== false) {
					$jsContent = OptimizeCommon::stripSourceMap($jsContent, 'js');
				}

				$pathToAssetDir = OptimizeCommon::getPathToAssetDir($assetHref);

				$contentToAddToCombinedFile = '';

				if (apply_filters('wpacu_print_info_comments_in_cached_assets', true)) {
					$contentToAddToCombinedFile = '/*!' . str_replace( Misc::getWpRootDirPathBasedOnPath($localAssetsPath), '/', $localAssetsPath ) . "*/\n";
				}

				// This includes the extra from 'data' (CDATA added via wp_localize_script()) & 'before' as they are both printed BEFORE the SCRIPT tag
				$contentToAddToCombinedFile .= self::maybeWrapBetweenTryCatch(self::appendToCombineJs('translations', $localAssetsExtra, $assetHref, $pathToAssetDir), $assetHref);
				$contentToAddToCombinedFile .= self::maybeWrapBetweenTryCatch(self::appendToCombineJs('before', $localAssetsExtra, $assetHref, $pathToAssetDir), $assetHref);
				$contentToAddToCombinedFile .= self::maybeWrapBetweenTryCatch(OptimizeJs::maybeDoJsFixes($jsContent, $pathToAssetDir . '/'), $assetHref) . "\n";
				// This includes the inline 'after' the SCRIPT tag
				$contentToAddToCombinedFile .= self::maybeWrapBetweenTryCatch(self::appendToCombineJs('after', $localAssetsExtra, $assetHref, $pathToAssetDir), $assetHref);

				$finalJsContents .= $contentToAddToCombinedFile;
			}
		}

		if ($finalJsContents !== '') {
			$finalJsContents = trim($finalJsContents);
			$shaOneForCombinedJs = sha1($finalJsContents);

			$uriToFinalJsFile = $docLocationScript . '-' . $shaOneForCombinedJs . '.js';
			$localFinalJsFile  = WP_CONTENT_DIR . OptimizeJs::getRelPathJsCacheDir() . $uriToFinalJsFile;

			if (! is_file($localFinalJsFile)) {
				FileSystem::filePutContents( $localFinalJsFile, $finalJsContents );
			}
		}

		return array(
			'uri_final_js_file'   => $uriToFinalJsFile,
			'local_final_js_file' => $localFinalJsFile
		);
	}

	/**
	 * @param $addItLocation
	 * @param $localAssetsExtra
	 * @param $assetHref
	 * @param $pathToAssetDir
	 *
	 * @return string
	 */
	public static function appendToCombineJs($addItLocation, $localAssetsExtra, $assetHref, $pathToAssetDir)
	{
		$extraContentToAppend = '';
		$doJsMinifyInline = MinifyJs::isMinifyJsEnabled() && in_array(Main::instance()->settings['minify_loaded_js_for'], array('inline', 'all'));

		if ($addItLocation === 'before') {
			// [Before JS Content]
			if (isset($localAssetsExtra[$assetHref]['data']) && ($dataValue = $localAssetsExtra[$assetHref]['data'])
                && self::isInlineJsCombineable($dataValue) && trim($dataValue) !== '') {
                $cData = $doJsMinifyInline ? MinifyJs::applyMinification( $dataValue ) : $dataValue;
                $cData = OptimizeJs::maybeDoJsFixes( $cData, $pathToAssetDir . '/' );
                $extraContentToAppend .= apply_filters('wpacu_print_info_comments_in_cached_assets', true) ? '/* [inline: cdata] */' : '';
                $extraContentToAppend .= $cData;
                $extraContentToAppend .= apply_filters('wpacu_print_info_comments_in_cached_assets', true) ? '/* [/inline: cdata] */' : '';
                $extraContentToAppend .= "\n";
			}

			if ( ! empty($localAssetsExtra[$assetHref]['before']) ) {
				$inlineBeforeJsData = '';

				foreach ($localAssetsExtra[$assetHref]['before'] as $beforeData) {
					if (! is_bool($beforeData) && self::isInlineJsCombineable($beforeData)) {
						$inlineBeforeJsData .= $beforeData . "\n";
					}
				}

				if (trim($inlineBeforeJsData)) {
					$inlineBeforeJsData = OptimizeJs::maybeAlterContentForInlineScriptTag( $inlineBeforeJsData, $doJsMinifyInline );
					$inlineBeforeJsData = OptimizeJs::maybeDoJsFixes( $inlineBeforeJsData, $pathToAssetDir . '/' );
					$extraContentToAppend .= apply_filters('wpacu_print_info_comments_in_cached_assets', true) ? '/* [inline: before] */' : '';
					$extraContentToAppend .= $inlineBeforeJsData;
					$extraContentToAppend .= apply_filters('wpacu_print_info_comments_in_cached_assets', true) ? '/* [/inline: before] */' : '';
					$extraContentToAppend .= "\n";
				}
			}
			// [/Before JS Content]
		} elseif ($addItLocation === 'after') {
			// [After JS Content]
			if ( ! empty($localAssetsExtra[$assetHref]['after']) ) {
				$inlineAfterJsData = '';

				foreach ($localAssetsExtra[$assetHref]['after'] as $afterData) {
					if (! is_bool($afterData) && self::isInlineJsCombineable($afterData)) {
						$inlineAfterJsData .= $afterData."\n";
					}
				}

				if ( trim($inlineAfterJsData) ) {
					$inlineAfterJsData = OptimizeJs::maybeAlterContentForInlineScriptTag( $inlineAfterJsData, $doJsMinifyInline );
					$inlineAfterJsData = OptimizeJs::maybeDoJsFixes( $inlineAfterJsData, $pathToAssetDir . '/' );
					$extraContentToAppend .= apply_filters('wpacu_print_info_comments_in_cached_assets', true) ? '/* [inline: after] */' : '';
					$extraContentToAppend .= $inlineAfterJsData;
					$extraContentToAppend .= apply_filters('wpacu_print_info_comments_in_cached_assets', true) ? '/* [/inline: after] */' : '';
					$extraContentToAppend .= "\n";
				}
			}
			// [/After JS Content]
		} elseif ($addItLocation === 'translations' && isset($localAssetsExtra[$assetHref]['translations']) && $localAssetsExtra[$assetHref]['translations']) {
			$inlineAfterJsData = OptimizeJs::maybeAlterContentForInlineScriptTag( $localAssetsExtra[$assetHref]['translations'], $doJsMinifyInline );
			$inlineAfterJsData = OptimizeJs::maybeDoJsFixes( $inlineAfterJsData, $pathToAssetDir . '/' );
			$extraContentToAppend .= apply_filters('wpacu_print_info_comments_in_cached_assets', true) ? '/* [inline: translations] */' : '';
			$extraContentToAppend .= $inlineAfterJsData;
			$extraContentToAppend .= apply_filters('wpacu_print_info_comments_in_cached_assets', true) ? '/* [/inline: translations] */' : '';
			$extraContentToAppend .= "\n";
		}

		return $extraContentToAppend;
	}

	/**
	 * @param $jsCode
	 * @param $sourceUrl
	 *
	 * @return string
	 */
	public static function maybeWrapBetweenTryCatch($jsCode, $sourceUrl)
	{
		if ($jsCode && Main::instance()->settings['combine_loaded_js_try_catch']) {
			return <<<JS
try {
	{$jsCode}
} catch (err) {
	console.log("Asset CleanUp - There is a JavaScript error related to the following source: {$sourceUrl} - Error: " + err.message);
}
JS;
		}

		return $jsCode;
	}

	/**
	 * @param $scriptTag
	 * @param $wpacuRegisteredScripts
	 * @param $replaceWith
	 * @param $htmlSource
	 *
	 * @return mixed
	 */
	public static function stripTagAndAnyInlineAssocCode($scriptTag, $wpacuRegisteredScripts, $replaceWith, $htmlSource, &$resolvedTranslations = null)
	{
		if (OptimizeCommon::appendInlineCodeToCombineAssetType('js')) {
			$scriptExtrasValue      = OptimizeJs::getInlineAssociatedWithScriptHandle($scriptTag, $wpacuRegisteredScripts, 'tag', 'value', $resolvedTranslations);

			$scriptExtraTranslationsValue  = (isset($scriptExtrasValue['translations']) && $scriptExtrasValue['translations']) ? $scriptExtrasValue['translations']   : '';
			$scriptExtraCdataValue         = (isset($scriptExtrasValue['data'])   && $scriptExtrasValue['data'])   ? $scriptExtrasValue['data']   : '';
			$scriptExtraBeforeValue        = (isset($scriptExtrasValue['before']) && $scriptExtrasValue['before']) ? $scriptExtrasValue['before'] : '';
			$scriptExtraAfterValue         = (isset($scriptExtrasValue['after'])  && $scriptExtrasValue['after'])  ? $scriptExtrasValue['after']  : '';

			$scriptExtrasHtml       = OptimizeJs::getInlineAssociatedWithScriptHandle($scriptTag, $wpacuRegisteredScripts, 'tag', 'html', $resolvedTranslations);
			preg_match_all('#data-wpacu-script-handle=([\'])' . '(.*)' . '(\1)#Usmi', $scriptTag, $outputMatches);
			$scriptHandle = (isset($outputMatches[2][0]) && $outputMatches[2][0]) ? trim($outputMatches[2][0], '"\'') : '';

			$scriptExtraTranslationsHtml = (isset($scriptExtrasHtml['translations']) && $scriptExtrasHtml['translations']) ? $scriptExtrasHtml['translations'] : '';
			$scriptExtraCdataHtml        = (isset($scriptExtrasHtml['data'])   && $scriptExtrasHtml['data'])   ? $scriptExtrasHtml['data']   : '';
			$scriptExtraBeforeHtml       = (isset($scriptExtrasHtml['before']) && $scriptExtrasHtml['before']) ? $scriptExtrasHtml['before'] : '';
			$scriptExtraAfterHtml        = (isset($scriptExtrasHtml['after'])  && $scriptExtrasHtml['after'])  ? $scriptExtrasHtml['after']  : '';

			if ($scriptExtraTranslationsValue || $scriptExtraCdataValue || $scriptExtraBeforeValue || $scriptExtraAfterValue) {
				if ( $scriptExtraCdataValue && self::isInlineJsCombineable($scriptExtraCdataValue) ) {
					$htmlSource = str_replace($scriptExtraCdataHtml, '', $htmlSource );
				}

				if ($scriptExtraTranslationsValue) {
					$repsBefore = array(
						$scriptExtraTranslationsHtml => '',
						str_replace( '<script ', '<script data-wpacu-script-handle=\'' . $scriptHandle . '\' ', $scriptExtraTranslationsHtml ) => '',
						'>'."\n".$scriptExtraTranslationsValue."\n".'</script>' => '></script>',
						$scriptExtraTranslationsValue."\n" => ''
					);
					$htmlSource = str_replace(array_keys($repsBefore), array_values($repsBefore), $htmlSource );
				}

				if ($scriptExtraBeforeValue && self::isInlineJsCombineable($scriptExtraBeforeValue)) {
					$repsBefore = array(
						$scriptExtraBeforeHtml => '',
						str_replace( '<script ', '<script data-wpacu-script-handle=\'' . $scriptHandle . '\' ', $scriptExtraBeforeHtml ) => '',
						'>'."\n".$scriptExtraBeforeValue."\n".'</script>' => '></script>',
						$scriptExtraBeforeValue."\n" => ''
					);
					$htmlSource = str_replace(array_keys($repsBefore), array_values($repsBefore), $htmlSource );
				}

				if ($scriptExtraAfterValue && self::isInlineJsCombineable($scriptExtraAfterValue)) {
					$repsBefore = array(
						$scriptExtraAfterHtml => '',
						str_replace( '<script ', '<script data-wpacu-script-handle=\'' . $scriptHandle . '\' ', $scriptExtraAfterHtml ) => '',
						'>'."\n".$scriptExtraAfterValue."\n".'</script>' => '></script>',
						$scriptExtraAfterValue."\n" => ''
					);
					$htmlSource = str_replace(array_keys($repsBefore), array_values($repsBefore), $htmlSource);
				}
			}
		}

		// Finally, strip/replace the tag
		return str_replace( array($scriptTag."\n", $scriptTag), $replaceWith, $htmlSource );
	}

    /**
     * Registered extras may enter a bundle even if an HTML filter removes their inline
     * tag's handle/id. Match appendToCombineJs() eligibility, retaining translations too.
     * @return string
     */
    public static function getRegisteredInlineSignature($handle, $registeredScripts, &$resolvedTranslations = null)
    {
        $parts = array();
        if (OptimizeCommon::appendInlineCodeToCombineAssetType('js') && isset($registeredScripts[$handle]->extra)) {
            $extra = $registeredScripts[$handle]->extra;
            if (! empty($extra['data']) && self::isInlineJsCombineable($extra['data']) && trim($extra['data']) !== '') {
                $parts['data'] = $extra['data'];
            }
            foreach (array('before', 'after') as $position) {
                if (empty($extra[$position])) { continue; }
                foreach ($extra[$position] as $code) {
                    if (! is_bool($code) && self::isInlineJsCombineable($code)) {
                        $parts[$position][] = $code;
                    }
                }
            }
            $translations = isset($extra['translations']) ? $extra['translations'] : false;
            $printedTranslations = OptimizeJs::getScriptTranslations($handle, $resolvedTranslations);
            if ($printedTranslations) { $translations = $printedTranslations; }
            if ($translations) { $parts['translations'] = $translations; }
        }
        return sha1(wp_json_encode($parts));
    }

    /**
     * Inline tags are movable only when WordPress registered their content for a handle.
     * Nonces and other inline code explicitly excluded from combination remain boundaries.
     *
     * @param \DOMElement $tag
     * @param array $registeredScripts
     * @param array|null $resolvedTranslations
     *
     * @return bool
     */
    public static function isAttachedInlineScript($tag, $registeredScripts, &$resolvedTranslations = null)
    {
        if (! OptimizeCommon::appendInlineCodeToCombineAssetType('js') || $tag->hasAttribute('nonce')
            || $tag->hasAttribute('data-wpacu-skip')
            || ! Misc::isClassicScriptType($tag->getAttribute('type'))) { return false; }
        $attributes = array();
        foreach ($tag->attributes as $attribute) { $attributes[$attribute->nodeName] = trim($attribute->nodeValue); }
        if (apply_filters('wpacu_internal_combine_js_skip_script_tag', false, $attributes)) { return false; }
        $handle = self::getInlineScriptHandle($tag);
        if (! isset($registeredScripts[$handle])) { return false; }
        $extras = OptimizeJs::getInlineAssociatedWithScriptHandle($handle, $registeredScripts, 'handle', 'value', $resolvedTranslations);

        // Keep printed data with its original handle. Its serialization can differ
        // from the reconstructed tag (for example, WordPress sourceURL comments),
        // otherwise the same data can execute once here and again in the bundle.
        if ($tag->getAttribute('id') === $handle . '-js-extra' && ! empty($extras['data'])) {
            return false;
        }

        return (bool)array_filter($extras)
            && self::isInlineJsCombineable($tag->nodeValue)
            && ! self::hasStrictModeDirective($tag->nodeValue);
    }

    /** @return string */
    public static function getInlineScriptHandle($tag)
    {
        $handle = $tag->getAttribute('data-wpacu-script-handle');
        if ($handle) { return $handle; }
        $id = $tag->getAttribute('id');
        $handle = preg_replace('/-js-(before|after|extra|translations)$/', '', $id);
        return $handle !== $id ? $handle : '';
    }

    /**
     * Detect strict mode in a script's directive prologue, including leading comments
     * and preceding string directives. Function-local directives do not affect combining.
     */
    public static function hasStrictModeDirective($code)
    {
        $code = preg_replace('/\A\xEF\xBB\xBF/', '', (string)$code);
        while ($code !== '') {
            $code = preg_replace('~\A(?:\s+|/\*.*?\*/|//[^\r\n]*(?:\r\n|\r|\n|\z))+~s', '', $code);
            if (! preg_match('~\A(?:"((?:\\\\.|[^"\\\\\r\n])*)"|\'((?:\\\\.|[^\'\\\\\r\n])*)\')~s', $code, $match)) {
                return false;
            }
            $tail = substr($code, strlen($match[0]));
            preg_match('~\A(?:\s+|/\*.*?\*/|//[^\r\n]*(?:\r\n|\r|\n|\z))*~s', $tail, $spacing);
            $tail = substr($tail, strlen($spacing[0]));
            if ($tail !== '' && $tail[0] !== ';' && strpbrk($spacing[0], "\r\n") === false) {
                return false;
            }
            if ((isset($match[1]) && $match[1] === 'use strict') || (isset($match[2]) && $match[2] === 'use strict')) {
                return true;
            }
            $code = ($tail !== '' && $tail[0] === ';') ? substr($tail, 1) : $tail;
        }
        return false;
    }

	/**
	 * This is to prevent certain inline JS to be appended to the combined JS files in order to avoid lots of disk space (sometimes a few GB) of JS combined files
	 *
	 * @param $jsInlineValue
	 *
	 * @return bool
	 */
	public static function isInlineJsCombineable($jsInlineValue)
	{
		// The common WordPress nonce
		if (strpos($jsInlineValue, 'nonce') !== false) {
			return false;
		}

		// WooCommerce Cart Fragments
		if (strpos($jsInlineValue, 'wc_cart_hash_') !== false && strpos($jsInlineValue, 'cart_hash_key') !== false) {
			return false;
		}

		if (substr(trim($jsInlineValue), 0, 1) === '{' && substr(trim($jsInlineValue), -1, 1) === '}') {
			@json_decode($jsInlineValue);

			if (wpacuJsonLastError() === JSON_ERROR_NONE) {
				return false; // it's a JSON format (e.g. type="application/json" from "wordpress-popular-posts" plugin)
			}
		}

		return true; // default
	}
}
