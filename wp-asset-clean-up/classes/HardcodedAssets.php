<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;
use WpAssetCleanUp\OptimiseAssets\OptimizeJs;

/**
 * Class HardcodedAssets
 * @package WpAssetCleanUp
 */
class HardcodedAssets
{
	/**
     * @var string
     */
    public static $handleLinkPrefix = 'wpacu_hardcoded_link_';

    /**
     * @var string
     */
    public static $handleNoScriptInlinePrefix = 'wpacu_hardcoded_noscript_inline_';

    /**
     * @var string
     */
    public static $handleStylePrefix = 'wpacu_hardcoded_style_';

    /**
     * @var string
     */
    public static $handleScriptSrcPrefix = 'wpacu_hardcoded_script_src_';

    /**
     * @var string
     */
    public static $handleScriptInlinePrefix = 'wpacu_hardcoded_script_inline_';

    /**
	 * @param $htmlSource
     * @param $encodeIt bool - if set to "false", it's mostly for fetching for the CSS/JS manager
	 * @params string $purpose ("fetch" - show the list in the CSS/JS manager or retrieve it all for alteration, "alter" - Fetch specific data)
     * @params array $anyHardCodedRules - useful to fetch only what's needed to save resources (usually when "alter" is used as the $purpose, unless debugging is done)
     *
	 * @return string|array
     *
     * @noinspection NestedAssignmentsUsageInspection
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
	public static function getAll($htmlSource, $encodeIt = true, $purpose = 'fetch', $toFetch = array()
    ) {
        $stickToRegEx = true;

        // Default
        if (empty($toFetch)) {
            $toFetch = array('wpacu_hardcoded_links', 'wpacu_hardcoded_styles', 'wpacu_hardcoded_scripts_src', 'wpacu_hardcoded_scripts_noscripts_inline');
        }

        if ( $purpose === 'fetch' ) {
            $collectLinkStyles = $collectScripts = true; // fetch only what's needed
        }

        $htmlSourceAlt = $htmlSource;

        if ( $purpose === 'fetch' ) {
            // It's relevant when all hardcoded assets are retrieved (e.g. to show in the CSS/JS manager list)
            $htmlSourceAlt = CleanUp::removeHtmlComments($htmlSource, true);
        }

        $hardCodedAssets = array(
			'link_and_style_tags'                           => array(), // LINK (rel="stylesheet") & STYLE (inline)
			'script_src_or_inline_and_noscript_inline_tags' => array(), // SCRIPT (with "src" attribute) & SCRIPT (inline), NOSCRIPT (inline)
		);

        $hardCodedAssetsBaseKeys = array_keys($hardCodedAssets);

		$matchesSourcesFromTags = array();

        if ($collectLinkStyles || $collectScripts) {
            preg_match_all('#<head[^>]*>(.*?)</head>(.*?)<body#Umsi', $htmlSourceAlt, $matchesContentsInHeadTag);
            preg_match_all('#<body[^>]*>(.*?)</body#Umsi', $htmlSourceAlt, $matchesContentsInBodyTag);
            }

        $headTagContent = isset($matchesContentsInHeadTag[1][0]) ? $matchesContentsInHeadTag[1][0] : '';
        $bodyTagContent = isset($matchesContentsInBodyTag[1][0]) ? $matchesContentsInBodyTag[1][0] : '';

		if ( $collectLinkStyles ) {
			if ( ! $stickToRegEx && Misc::isDOMDocumentOn() ) {
				$domDoc = Misc::initDOMDocument();
				$domDoc->loadHTML($htmlSourceAlt);

				$selector = new \DOMXPath($domDoc);

				$domTagQuery = $selector->query('//link[@rel="stylesheet"]|//style|//script|//noscript');

				if (count($domTagQuery) > 1) {
					foreach($domTagQuery as $tagFound) {
						$tagType = in_array($tagFound->nodeName, array('link', 'style')) ? 'css' : 'js';

						if (self::skipTagIfNotRelevant( Misc::getOuterHTML( $tagFound ), 'whole_tag', $tagType)) {
							continue; // no point in wasting more resources as the tag will never be shown, since it's irrelevant
						}

						if ( $tagFound->hasAttributes() ) {
							foreach ( $tagFound->attributes as $attr ) {
								if ( self::skipTagIfNotRelevant( $attr->nodeName, 'attribute', $tagType ) ) {
									continue 2;
								}
							}
						}

						if ($tagFound->nodeName === 'link') {
							if ( ! $tagFound->hasAttributes() ) {
								continue;
							}

							$linkTagParts = array();
							$linkTagParts[] = '<link ';

							foreach ($tagFound->attributes as $attr) {
								$attrName = $attr->nodeName;
								$attrValue = $attr->nodeValue;

								if ($attrName) {
									if ($attrValue !== '') {
										$linkTagParts[] = '(\s+|)' . preg_quote($attrName, '/') . '(\s+|)=(\s+|)(|"|\')' . preg_quote($attrValue, '/') . '(|"|\')(|\s+)';
									} else {
										$linkTagParts[] = '(\s+|)' . preg_quote($attrName, '/') . '(|((\s+|)=(\s+|)(|"|\')(|"|\')))';
									}
								}
							}

							$linkTagParts[] = '(|\s+)(|/)>';

							$linkTagFinalRegExPart = implode('', $linkTagParts);

							preg_match_all(
								'#'.$linkTagFinalRegExPart.'#Umi',
								$htmlSource,
								$matchSourceFromTag,
								PREG_SET_ORDER
							);

							// It always has to be a match from the DOM generated tag
							// Otherwise, default it to RegEx
							if ( empty($matchSourceFromTag) || empty( $matchSourceFromTag[0][0] ) ) {
								$stickToRegEx = true;
								break;
							}

							$matchesSourcesFromTags[] = array('link_tag' => $matchSourceFromTag[0][0]);
						}
					}

					if (! $stickToRegEx) {
						$shaOneToOriginal = array();

						$htmlSourceAltEncoded = $htmlSourceAlt;

						foreach($domTagQuery as $tagFound) {
							if ( $tagFound->nodeValue && in_array( $tagFound->nodeName, array( 'style', 'script', 'noscript' ) ) ) {
								if (strpos($htmlSourceAlt, $tagFound->nodeValue) === false) {
									$stickToRegEx = true;
									break;
								}

								$shaOneToOriginal[sha1($tagFound->nodeValue)] = $tagFound->nodeValue;

								$htmlSourceAltEncoded = str_replace(
									$tagFound->nodeValue,
									'/*[wpacu]*/' . sha1($tagFound->nodeValue) . '/*[/wpacu]*/',
									$htmlSourceAltEncoded
								);
							}
						}

						$domDocForTwo = Misc::initDOMDocument();

						$domDocForTwo->loadHTML($htmlSourceAltEncoded);

						$selectorTwo = new \DOMXPath($domDocForTwo);

						$domTagQueryTwo = $selectorTwo->query('//style|//script|//noscript');

						foreach($domTagQueryTwo as $tagFoundTwo) {
							$tagType = in_array($tagFoundTwo->nodeName, array('link', 'style')) ? 'css' : 'js';

							if ( $tagFoundTwo->hasAttributes() ) {
								foreach ( $tagFoundTwo->attributes as $attr ) {
									if ( self::skipTagIfNotRelevant( $attr->nodeName, 'attribute', $tagType ) ) {
										continue 2;
									}
								}
							}

							$tagParts = array();
							$tagParts[] = '<'.$tagFoundTwo->nodeName;

							foreach ($tagFoundTwo->attributes as $attr) {
								$attrName = $attr->nodeName;
								$attrValue = $attr->nodeValue;

								if ($attrName) {
									if ($attrValue !== '') {
										$tagParts[] = '(\s+|)' . preg_quote($attrName, '/') . '(\s+|)=(\s+|)(|"|\')' . preg_quote($attrValue, '/') . '(|"|\')(|\s+)';
									} else {
										$tagParts[] = '(\s+|)' . preg_quote($attrName, '/') . '(|((\s+|)=(\s+|)(|"|\')(|"|\')))';
									}
								}
							}

							$tagParts[] = '(|\s+)>';

							if ($tagFoundTwo->nodeValue) {
								$tagParts[] = preg_quote($tagFoundTwo->nodeValue, '/');
							}

							$tagParts[] = '</'.$tagFoundTwo->nodeName.'>';

							$tagFinalRegExPart = implode('', $tagParts);

							preg_match_all(
								'#'.$tagFinalRegExPart.'#Umi',
								$htmlSourceAltEncoded,
								$matchSourceFromTagTwo,
								PREG_SET_ORDER
							);

							// It always has to be a match from the DOM generated tag
							// Otherwise, default it to RegEx
							if ( empty($matchSourceFromTagTwo) || empty( $matchSourceFromTagTwo[0][0] ) ) {
								$stickToRegEx = true;
								break;
							}

							$encodedNodeValue = Misc::extractBetween($matchSourceFromTagTwo[0][0], '/*[wpacu]*/', '/*[/wpacu]*/');

							$matchedTag = str_replace('/*[wpacu]*/'.$encodedNodeValue.'/*[/wpacu]*/', $shaOneToOriginal[$encodedNodeValue], $matchSourceFromTagTwo[0][0]);

							$tagTypeForReference = ($tagFoundTwo->nodeName === 'style') ? 'style_tag' : 'script_noscript_tag';

							$matchesSourcesFromTags[] = array($tagTypeForReference => $matchedTag);
						}
					}
				}
			}

			/*
			* [START] Collect Hardcoded LINK (stylesheet) & STYLE tags
			*/
			if ($stickToRegEx || ! Misc::isDOMDocumentOn()) {
                $regExp = '#(?=(?P<link_tag><link[^>]*stylesheet[^>]*(>)))|(?=(?P<style_tag><style[^>]*?>.*</style>))#Umsi'; // default

                if ($purpose === 'alter') {
                    if (in_array('wpacu_hardcoded_links', $toFetch) && ! in_array('wpacu_hardcoded_styles', $toFetch)) {
                        $regExp = '#(?=(?P<link_tag><link[^>]*stylesheet[^>]*(>)))#Umi';
                    }

                    if (in_array('wpacu_hardcoded_styles', $toFetch) && ! in_array('wpacu_hardcoded_links', $toFetch)) {
                        $regExp = '#(?=(?P<style_tag><style[^>]*?>.*</style>))#Umsi';
                    }
                }

				preg_match_all(
                    $regExp,
					$htmlSourceAlt,
					$matchesSourcesFromTags,
                    PREG_SET_ORDER|PREG_OFFSET_CAPTURE
				);
			}

			if ( ! empty( $matchesSourcesFromTags ) ) {
				// Only the hashes are set
				// For instance, 'd1eae32c4e99d24573042dfbb71f5258a86e2a8e' is the hash for the following script:
				/*
				* <style media="print">#wpadminbar { display:none; }</style>
				 */
				$stripsSpecificStylesHashes = array(
					'5ead5f033961f3b8db362d2ede500051f659dd6d',
					'25bd090513716c34b48b0495c834d2070088ad24'
				);

				// Sometimes, the hash checking might fail (if there's a small change to the JS content)
				// Consider using fallback verification by checking the actual content
				$stripsSpecificStylesContaining = array(
					'<style media="print">#wpadminbar { display:none; }</style>',
					'id="edd-store-menu-styling"',
					'#wp-admin-bar-gform-forms'
				);

				foreach ( $matchesSourcesFromTags as $matchedTag ) {
					// LINK "stylesheet" tags (if any)
					if ( isset( $matchedTag['link_tag'][0], $matchedTag['link_tag'][1] ) && trim( $matchedTag['link_tag'][0] ) !== '' && ( trim( strip_tags( $matchedTag['link_tag'][0] ) ) === '' ) ) {
						$matchedTagOutput = trim( $matchedTag['link_tag'][0] );

						// Own plugin assets and enqueued ones since they aren't hardcoded
						if (self::skipTagIfNotRelevant($matchedTagOutput)) {
							continue;
						}

						$hardCodedAssets['link_and_style_tags'][] = $matchedTagOutput;
                        $hardCodedAssets['offset']['link_and_style_tags'][] = $matchedTag['link_tag'][1];
					}

					// STYLE inline tags (if any)
					if ( isset( $matchedTag['style_tag'][0], $matchedTag['style_tag'][1] ) && trim( $matchedTag['style_tag'][0] ) !== '' ) {
						$matchedTagOutput = trim( $matchedTag['style_tag'][0] );

                        if ($purpose === 'fetch') {
                            /*
                             * Strip certain STYLE tags irrelevant for the list (e.g., related to the WordPress Admin Bar, etc.)
                            */
                            if (in_array(self::determineHardcodedAssetSha1($matchedTagOutput),
                                $stripsSpecificStylesHashes)) {
                                continue;
                            }

                            foreach ($stripsSpecificStylesContaining as $cssContentTargeted) {
                                if (strpos($matchedTagOutput, $cssContentTargeted) !== false) {
                                    continue 2; // applies for this "foreach": ($matchesSourcesFromTags as $matchedTag)
                                }
                            }

                            // Own plugin assets and enqueued ones since they aren't hardcoded
                            if (self::skipTagIfNotRelevant($matchedTagOutput)) {
                                continue;
                            }

                            foreach (wp_styles()->done as $cssHandle) {
                                if (strpos($matchedTagOutput, '<style id=\'' . trim($cssHandle) . '-inline-css\'') !== false) {
                                    // Do not consider the STYLE added via WordPress with wp_add_inline_style() as it's not hardcoded
                                    continue 2;
                                }
                            }
                        }

						$hardCodedAssets['link_and_style_tags'][] = $matchedTagOutput;
                        $hardCodedAssets['offset']['link_and_style_tags'][] = $matchedTag['style_tag'][1];
					}
				}
			}
			/*
			* [END] Collect Hardcoded LINK (stylesheet) & STYLE tags
			*/
		}

		if ($collectScripts) {
			/*
			* [START] Collect Hardcoded SCRIPT (src/inline)
			*/
			if ($stickToRegEx || ! Misc::isDOMDocumentOn()) {
                $regExp = '@<script[^>]*?>.*?</script>|<noscript[^>]*?>.*?</noscript>@si'; // default

                if ($purpose === 'alter' && in_array('wpacu_hardcoded_scripts_src', $toFetch) && ! in_array('wpacu_hardcoded_scripts_noscripts_inline', $toFetch)) {
                    $regExp = '@<script[^>]*?>.*?</script>@si';
                }

				preg_match_all( $regExp, $htmlSourceAlt, $matchesScriptTags, PREG_SET_ORDER|PREG_OFFSET_CAPTURE );
			} else {
				$matchesScriptTags = array();

				if (! empty($matchesSourcesFromTags)) {
					foreach ($matchesSourcesFromTags as $matchedTag) {
						if ( ! empty($matchedTag['script_noscript_tag']) ) {
							$matchesScriptTags[][0][0] = $matchedTag['script_noscript_tag'];
						}
					}
				}
			}

            if ($purpose === 'fetch') {
                $allInlineAssocWithJsHandle = array();

                if ( ! empty(wp_scripts()->done) ) {
                    foreach ( wp_scripts()->done as $assetHandle ) {
                        // Now, go through the list of inline SCRIPTs associated with an enqueued SCRIPT (with "src" attribute)
                        // And make sure they do not show to the hardcoded list, since they are related to the handle, and they are stripped when the handle is dequeued
                        $anyInlineAssocWithJsHandle = OptimizeJs::getInlineAssociatedWithScriptHandle($assetHandle, wp_scripts()->registered, 'handle');
                        if ( ! empty($anyInlineAssocWithJsHandle) ) {
                            foreach ( $anyInlineAssocWithJsHandle as $jsInlineTagContent ) {
                                if ( trim($jsInlineTagContent) === '' ) {
                                    continue;
                                }

                                $allInlineAssocWithJsHandle[] = trim($jsInlineTagContent);
                            }
                        }
                    }

                    $allInlineAssocWithJsHandle = array_unique($allInlineAssocWithJsHandle);
                    }
            }

			// Go through the hardcoded SCRIPT tags
			if ( ! empty( $matchesScriptTags ) ) {
				// Only the hashes are set
				// For instance, 'd1eae32c4e99d24573042dfbb71f5258a86e2a8e' is the hash for the following script:
				/*
				 * <script>
				(function() {
					var request, b = document.body, c = 'className', cs = 'customize-support', rcs = new RegExp('(^|\\s+)(no-)?'+cs+'(\\s+|$)');
						request = true;
					b[c] = b[c].replace( rcs, ' ' );
					// The customizer requires postMessage and CORS (if the site is cross domain)
					b[c] += ( window.postMessage && request ? ' ' : ' no-' ) + cs;
				}());
				</script>
				 */
				$stripsSpecificScriptsHashes = array(
					'd1eae32c4e99d24573042dfbb71f5258a86e2a8e',
					'1a8f46f9f33e5d95919620df54781acbfa9efff7'
				);

				// Sometimes, the hash checking might fail (if there's a small change to the JS content)
				// Consider using fallback verification by checking the actual content
				$stripsSpecificScriptsContaining = array(
					'// The customizer requires postMessage and CORS (if the site is cross domain)',
					'b[c] += ( window.postMessage && request ? \' \' : \' no-\' ) + cs;',
					"(function(){var request,b=document.body,c='className',cs='customize-support',rcs=new RegExp('(^|\\s+)(no-)?'+cs+'(\\s+|$)');request=!0;b[c]=b[c].replace(rcs,' ');b[c]+=(window.postMessage&&request?' ':' no-')+cs}())",
					'document.body.className = document.body.className.replace( /(^|\s)(no-)?customize-support(?=\s|$)/, \'\' ) + \' no-customize-support\'',
					"c = c.replace(/woocommerce-no-js/, 'woocommerce-js');" // WooCommerce related
				);

				foreach ( $matchesScriptTags as $matchedTag ) {
					if ( isset( $matchedTag[0][0], $matchedTag[0][1] ) && $matchedTag[0][0]
					     && (strncasecmp($matchedTag[0][0], '<script', 7) === 0 || strncasecmp($matchedTag[0][0],
                                '<noscript', 9) === 0) ) {
						$matchedTagOutput = trim( $matchedTag[0][0] );

						// Own plugin assets and enqueued ones since they aren't hardcoded
						if ($purpose === 'fetch' && self::skipTagIfNotRelevant($matchedTagOutput, 'whole_tag', 'js', array('all_inline_assoc_with_js_handle' => $allInlineAssocWithJsHandle))) {
							continue;
						}

                        if ($purpose === 'fetch') {
                            /*
                             * Strip certain SCRIPT tags irrelevant for the list (e.g. related to WordPress Customiser, Admin Bar, etc.)
                            */
                            if ( in_array( self::determineHardcodedAssetSha1( $matchedTagOutput ), $stripsSpecificScriptsHashes ) ) {
                                continue;
                            }

                            foreach ( $stripsSpecificScriptsContaining as $jsContentTargeted ) {
                                if ( strpos( $matchedTagOutput, $jsContentTargeted ) !== false ) {
                                    continue 2; // applies for this "foreach": ($matchesScriptTags as $matchedTag)
                                }
                            }
                        }

						$hardCodedAssets['script_src_or_inline_and_noscript_inline_tags'][] = $matchedTagOutput;
                        $hardCodedAssets['offset']['script_src_or_inline_and_noscript_inline_tags'][] = $matchedTag[0][1];
					}
				}
			}
			/*
			* [END] Collect Hardcoded SCRIPT (src/inline)
			*/
		}

		if ($purpose === 'fetch' && $stickToRegEx && ! empty($hardCodedAssets['link_and_style_tags']) && ! empty($hardCodedAssets['script_src_or_inline_and_noscript_inline_tags'])) {
			$hardCodedAssets = self::removeAnyLinkTagsThatMightBeDetectedWithinScriptTags( $hardCodedAssets );
		}

        foreach ($hardCodedAssetsBaseKeys as $hKey) {
            if ( ! empty($hardCodedAssets[$hKey])) {
                foreach ($hardCodedAssets[$hKey] as $tagKey => $matchedTagOutput) {
                    if (strpos($headTagContent, $matchedTagOutput) !== false) {
                        $hardCodedAssets['positions'][$hKey]['head'][] = $tagKey;
                    } elseif (strpos($bodyTagContent, $matchedTagOutput) !== false) {
                        $hardCodedAssets['positions'][$hKey]['body'][] = $tagKey;
                    }
                }
            }
        }

		$tagsWithinConditionalComments = self::extractHtmlFromConditionalComments($htmlSourceAlt);

        $hardCodedAssets['within_conditional_comments'] = $tagsWithinConditionalComments;

		if ($encodeIt) {
			return base64_encode( wp_json_encode( $hardCodedAssets ) );
		}

        return $hardCodedAssets;
	}

	/**
	 * @param $value
	 * @param $via ('whole_tag', 'attribute')
	 * @param string $type ('css', 'js')
	 * @param array $extras ('all_inline_assoc_with_js_handle')
	 *
	 * @return bool
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
	public static function skipTagIfNotRelevant($value, $via = 'whole_tag', $type = 'css', $extras = array())
	{
		if ($via === 'whole_tag') {
			if ($type === 'css') {
				if ( strpos( $value, 'data-wpacu-style-handle=' ) !== false ) {
					// skip the SCRIPT with src that was enqueued properly and keep the hardcoded ones
					return true;
				}

				if ( ( strpos( $value, 'data-wpacu-own-inline-style=' ) !== false ) ||
				     ( strpos( $value, 'data-wpacu-inline-css-file=' ) !== false ) ) {
					// remove plugin's own STYLE tags as they are not relevant in this context
					return true;
				}

				// Do not add to the list elements such as Emojis (not relevant for hard-coded tags)
				if ( strpos( $value, 'img.wp-smiley' ) !== false
				     && strpos( $value, 'img.emoji' ) !== false
				     && strpos( $value, '!important;' ) !== false ) {
					return true;
				}
			}

			if ($type === 'js') {
				if ( strpos( $value, 'data-wpacu-script-handle=' ) !== false ) {
					// skip the SCRIPT with src that was enqueued properly and keep the hardcoded ones
					return true;
				}

				if ( ( strpos( $value, 'data-wpacu-own-inline-script=' ) !== false ) ||
				     ( strpos( $value, 'data-wpacu-inline-js-file=' ) !== false ) ) {
					// skip plugin's own SCRIPT tags as they are not relevant in this context
					return true;
				}

				if ( strpos( $value, 'wpacu-preload-async-css-fallback' ) !== false ) {
					// skip plugin's own SCRIPT tags as they are not relevant in this context
					return true;
				}

				if ( strpos( $value, 'window._wpemojiSettings' ) !== false
				     && strpos( $value, 'twemoji' ) !== false ) {
					return true;
				}

				// Check the type and only allow SCRIPT tags with type='text/javascript' or no type at all (it will default to 'text/javascript')
				$matchedTagInner    = strip_tags( $value );
				$matchedTagOnlyTags = str_replace( $matchedTagInner, '', $value );

				$scriptType = Misc::getValueFromTag($matchedTagOnlyTags, 'type') ?: 'text/javascript';

				if (  strpos( $scriptType, 'text/javascript' ) === false ) {
					return true;
				}

				$allInlineAssocWithJsHandle = isset($extras['all_inline_assoc_with_js_handle']) ? $extras['all_inline_assoc_with_js_handle'] : array();

				$hasSrc = false;

				if (strpos($matchedTagOnlyTags, ' src=') !== false) {
					$hasSrc = true;
				}

				if ( ! $hasSrc && ! empty( $allInlineAssocWithJsHandle ) ) {
					preg_match_all("'<script[^>]*?>(.*?)</script>'si", $value, $matchesFromTagOutput);
					$matchedTagOutputInner = isset($matchesFromTagOutput[1][0]) && trim($matchesFromTagOutput[1][0])
						? trim($matchesFromTagOutput[1][0]) : false;

					$matchedTagOutputInnerCleaner = $matchedTagOutputInner;

					$stripStrStart = '/* <![CDATA[ */';
					$stripStrEnd   = '/* ]]> */';

					if (strpos($matchedTagOutputInnerCleaner, $stripStrStart) === 0
					    && Misc::endsWith($matchedTagOutputInnerCleaner, '/* ]]> */')) {
						$matchedTagOutputInnerCleaner = substr($matchedTagOutputInnerCleaner, strlen($stripStrStart));
						$matchedTagOutputInnerCleaner = substr($matchedTagOutputInnerCleaner, 0, -strlen($stripStrEnd));
						$matchedTagOutputInnerCleaner = trim($matchedTagOutputInnerCleaner);
					}

					if (in_array($matchedTagOutputInnerCleaner, $allInlineAssocWithJsHandle)) {
						return true;
					}

					}
			}
		}

		if ($via === 'attribute') {
			if ( $type === 'css' ) {
				$possibleSignatures = array(
					'data-wpacu-style-handle',
					'data-wpacu-own-inline-style',
					'data-wpacu-inline-css-file'
				);
			} else {
				$possibleSignatures = array(
					'data-wpacu-script-handle',
					'data-wpacu-own-inline-script',
					'data-wpacu-inline-js-file',
					'wpacu-preload-async-css-fallback'
				);
			}

			if (in_array($value, $possibleSignatures)) {
				return true;
			}
		}

		return false; // default
	}

	/**
     * This is a fix in case the RegEx method is used to fetch the hardcoded assets,
     * and it detects LINK tags within SCRIPT ones, instead of detecting only the proper (independent) ones
	 *
	 * @param $hardcodedAssets
	 *
	 * @return mixed
	 */
	public static function removeAnyLinkTagsThatMightBeDetectedWithinScriptTags($hardcodedAssets)
	{
		foreach ($hardcodedAssets['link_and_style_tags'] as $cssTagIndex => $cssTag) {
			if ($cssTag) {
				foreach ($hardcodedAssets['script_src_or_inline_and_noscript_inline_tags'] as $scriptTag) {
					if (strpos($scriptTag, $cssTag) !== false) {
						// e.g., could be '<script>var linkToCss="<link href='[path_to_custom_css_file_here]'>";</script>'
                        unset(
                            $hardcodedAssets['link_and_style_tags'][$cssTagIndex],
                            $hardcodedAssets['offset']['link_and_style_tags'][$cssTagIndex]
                        );
                    }
				}
			}
		}

		return $hardcodedAssets;
	}

	/**
	 * @param $htmlSource
	 *
	 * @return array
	 */
	public static function extractHtmlFromConditionalComments($htmlSource)
	{
		preg_match_all('#<!--\[if(.*?)]>(<!-->|-->|\s|)(.*?)(<!--<!|<!)\[endif]-->#si', $htmlSource, $matchedContent);

		if ( ! empty($matchedContent[1]) && ! empty($matchedContent[3]) ) {
			$conditions = array_map('trim', $matchedContent[1]);
			$tags       = array_map('trim', $matchedContent[3]);

			return array(
				'conditions' => $conditions,
				'tags'       => $tags,
			);
		}

		return array();
	}

	/**
	 * @param $targetedTag
	 * @param $contentWithinConditionalComments
	 *
	 * @return bool
	 */
	public static function isWithinConditionalComment($targetedTag, $contentWithinConditionalComments)
	{
		if (empty($contentWithinConditionalComments)) {
			return false;
		}

		$targetedTag = trim($targetedTag);

        if (in_array($targetedTag, $contentWithinConditionalComments['tags'])) {
            $tagIndex = array_search($targetedTag, $contentWithinConditionalComments['tags']);
            return $contentWithinConditionalComments['conditions'][$tagIndex];
        }

		return false; // Not within a conditional comment (most cases)
	}

    /**
     * @param $targetedTag
     * @param $tagIndexNo
     * @param $allPositionsFromTagGroup
     *
     * @return string
     */
    public static function getTagPositionHeadOrBody($tagIndexNo, $allPositionsFromTagGroup)
    {
        if (isset($allPositionsFromTagGroup['head']) && in_array($tagIndexNo, $allPositionsFromTagGroup['head'])) {
            return 'head';
        }

        if (isset($allPositionsFromTagGroup['body']) && in_array($tagIndexNo, $allPositionsFromTagGroup['body'])) {
            return 'body';
        }

        return '';
    }

	/**
	 * @param $htmlTag
	 *
	 * @return bool|array
	 */
	public static function belongsTo($htmlTag)
	{
        $belongList = array(
            'wpcf7recaptcha.'                                                                    => array(
                'text' => '"Contact Form 7" plugin',
                'dir'  => 'contact-form-7'
            ),
            'c = c.replace(/woocommerce-no-js/, \'woocommerce-js\');'                            => array(
                'text' => '"WooCommerce" plugin',
                'dir'  => 'woocommerce'
            ),
            '.woocommerce-product-gallery{ opacity: 1 !important; }'                             => array(
                'text' => '"WooCommerce" plugin',
                'dir'  => 'woocommerce'
            ),
            '-ss-slider-3'                                                                       => array(
                'text' => '"Smart Slider 3" plugin',
                'dir'  => 'smart-slider-3'
            ),
            'N2R(["nextend-frontend","smartslider-frontend","smartslider-simple-type-frontend"]' => array(
                'text' => '"Smart Slider 3" plugin',
                'dir'  => 'smart-slider-3'
            ),
            'function setREVStartSize'                                                           => array(
                'text' => '"Smart Slider 3" plugin',
                'dir'  => 'smart-slider-3'
            ),
            'jQuery(\'.rev_slider_wrapper\')'                                                    => array(
                'text' => '"Smart Slider 3" plugin',
                'dir'  => 'smart-slider-3'
            ),
            'jQuery(\'#wp-admin-bar-revslider-default'                                           => array(
                'text' => '"Smart Slider 3" plugin',
                'dir'  => 'smart-slider-3'
            ),
        );

		foreach ($belongList as $ifContains => $isFromSourceArray) {
			if ( strpos( $htmlTag, $ifContains) !== false ) {
				return $isFromSourceArray;
			}
		}

		return false;
	}

    /**
     * @param $settings
     *
     * @return string
     */
    public static function viewHardcodedModeLayout($settings)
    {
        $l = $settings['assets_list_layout'];

        if ($l === 'by-location') {
            $viewHardcodedMode = 'by-location';
        } elseif ($l === 'by-position') {
            $viewHardcodedMode = 'by-position';
        } elseif ($l === 'by-preload') {
            $viewHardcodedMode = 'by-preload';
        } elseif ($l === 'by-rules') {
            $viewHardcodedMode = 'by-rules';
        } elseif ($l === 'by-loaded-unloaded') {
            $viewHardcodedMode = 'by-loaded-unloaded';
        } elseif ($l === 'by-size') {
            $viewHardcodedMode = 'by-size';
        } elseif ($l === 'all') {
            $viewHardcodedMode = 'all';
        } else {
            $viewHardcodedMode = 'default';
        }

        return $viewHardcodedMode;
    }

	/**
	 * @param $tagOutput
	 *
	 * @return string
	 */
	public static function determineHardcodedAssetSha1($tagOutput)
	{
		// Look if the "href" or "src" ends with '.css' or '.js'
		// Only hash the actual path to the file
		// In case the tag changes (e.g. an attribute will be added), the tag will be considered the same for the plugin rules
		// To avoid the rules from not working  / e.g. the file could have a dynamic "?ver=" at the end
		if ( !  (stripos($tagOutput, '<link') !== false   || stripos($tagOutput, '<style') !== false
		      || stripos($tagOutput, '<script') !== false || stripos($tagOutput, '<noscript') !== false) ) {
			return sha1( $tagOutput ); // default
		}

		if (Misc::getValueFromTag($tagOutput, 'href', 'dom_with_fallback') ||
            Misc::getValueFromTag($tagOutput, 'src', 'dom_with_fallback')) {
			return self::determineHardcodedAssetSha1ForAssetsWithSource($tagOutput);
		}

		if (stripos($tagOutput, '<style') !== false || stripos($tagOutput, '<script') !== false || stripos($tagOutput, '<noscript') !== false) {
			return self::determineHardcodedAssetSha1ForAssetsWithoutSource($tagOutput);
		}

		return sha1( $tagOutput ); // default
	}

	/**
	 // In case there are STYLE tags and SCRIPT tags without any SRC, make sure to consider only the content of the tag as a reference
	 // e.g. if the user updates <style type="text/css"> to <style> the tag should be considered the same if the content is the same
	 // also, do not consider any whitespaces from the beginning and ending of the tag's content
     * If there are just whitespaces in the content, then the content is not take into account as a unique reference; it will be the whole tag
	 *
	 * @param $tagOutput
	 *
	 * @return string
	 */
	public static function determineHardcodedAssetSha1ForAssetsWithoutSource($tagOutput)
    {
        $contentInsideTag = ''; // default

		if (stripos($tagOutput, '<style') !== false) {
			preg_match_all('@(<style[^>]*?>)(.*?)</style>@si', $tagOutput, $matches);

			if (isset($matches[0][0], $matches[2][0]) && strlen($tagOutput) === strlen($matches[0][0])) {
                $contentInsideTag = $matches[2][0];
			}
		} elseif (stripos($tagOutput, '<script') !== false) {
			preg_match_all('@(<script[^>]*?>)(.*?)</script>@si', $tagOutput, $matches);

			if (isset($matches[0][0], $matches[2][0]) && strlen($tagOutput) === strlen($matches[0][0])) {
                $contentInsideTag = $matches[2][0];
            }
		} elseif (stripos($tagOutput, '<noscript') !== false) {
			preg_match_all('@(<noscript[^>]*?>)(.*?)</noscript>@si', $tagOutput, $matches);

			if (isset($matches[0][0], $matches[2][0]) && strlen($tagOutput) === strlen($matches[0][0])) {
                $contentInsideTag = $matches[2][0];
			}
		}

        // e.g. <script id="value-here">alert('demo');</script> will be treated THE SAME as <script>alert('demo');</script>
        // e.g. <script id="value-here-2"></script> will be treated AS A DIFFERENT tag from <script></script> (they have empty content, but different attributes)

        if (trim($contentInsideTag)) {
            // there's content there (excluding the whitespace), thus use it as the unique reference for this type of tag
            return sha1($contentInsideTag);
        }

        // The tag doesn't have any valid content (e.g. just whitespace), thus make the uniqueness from the whole tag
		return sha1($tagOutput);
	}

	/**
	 * Only the LINK tags and SCRIPT tags with the "href" and "src" attributes would be considered
	 *
	 * @param $tagOutput
	 *
	 * @return string
	 */
	public static function determineHardcodedAssetSha1ForAssetsWithSource($tagOutput)
	{
		if ($finalCleanSource = self::getRelSourceFromTagOutputForReference($tagOutput)) {
			return sha1($finalCleanSource);
		}

		return sha1( $tagOutput ); // default
	}

	/**
	 * @param $tagOutput
	 *
	 * @return array|false|string|string[]
	 */
	public static function getRelSourceFromTagOutputForReference($tagOutput)
	{
		if (stripos($tagOutput, 'href') !== false && stripos($tagOutput, 'stylesheet') !== false && strncasecmp(trim($tagOutput),
                '<link', 5) === 0) {
			$attrToGet  = 'href';
			$extToCheck = 'css';
		} else {
			$attrToGet  = 'src';
			$extToCheck = 'js';
		}

		$sourceValue = Misc::getValueFromTag($tagOutput, $attrToGet, 'dom_with_fallback');

		if (! $sourceValue) {
			return false;
		}

		if ( stripos( $sourceValue, '.' . $extToCheck . '?' ) !== false ) {
			list( $cleanSource ) = explode( '.' . $extToCheck . '?', $sourceValue );
			$finalCleanSource = $cleanSource . '.' . $extToCheck;
		} else {
			$finalCleanSource = $sourceValue;
		}

		if ( $finalCleanSource ) {
			$localAssetPath = OptimizeCommon::getLocalAssetPath( $finalCleanSource, $extToCheck );

			if ( $localAssetPath ) {
				$sourceRelPath = OptimizeCommon::getSourceRelPath( $finalCleanSource );

				if ( $sourceRelPath ) {
					return $finalCleanSource;
				}
			} else {
				$finalCleanSource = str_ireplace( array( 'http://', 'https://' ), '', $finalCleanSource );
				$finalCleanSource = (strncmp($finalCleanSource, '//', 2) === 0 ) ? substr( $finalCleanSource, 2 ) : $finalCleanSource; // if the string starts with '//', remove it
			}
		}

		return $finalCleanSource;
	}

	/**
	 * @param $data
	 *
	 * @return string
	 */
	public static function getHardCodedManageAreaForFrontEndView($data)
	{
		$dataSettingsFrontEnd = ObjectCache::wpacu_cache_get('wpacu_settings_frontend_data') ?: array();
		$dataSettingsFrontEnd['page_unload_text'] = esc_html($data['page_unload_text']);
		// The following string will be replaced by the values got from the AJAX call to /?wpassetcleanup_load=1&wpacu_just_hardcoded
		$dataWpacuSettingsFrontend = base64_encode(wp_json_encode($dataSettingsFrontEnd));

		$currentHardcodedAssetRules = '';

		// When the form is submitted, it will clear some values if they are not sent anymore which can happen with a failed AJAX call to retrieve the list of hardcoded assets
		// Place the current values to the area in case the AJAX call fails, and it won't print the list
		// If the user presses "Update", it won't clear any existing rules
		// If the list is printed, obviously it will be with all the fields in place as they should be
		foreach (array('current_unloaded_page_level', 'load_exceptions', 'handle_unload_regex', 'handle_load_regex', 'handle_load_logged_in') as $ruleKey) {
			foreach ( array( 'styles', 'scripts' ) as $assetType ) {
				if ( ! empty( $dataSettingsFrontEnd[$ruleKey][$assetType] ) ) {
					// Go through the values, depending on how the array is structured
					// handle_unload_regex, handle_load_regex
					if (in_array($ruleKey, array('handle_unload_regex', 'handle_load_regex'))) {
						foreach ( $dataSettingsFrontEnd[ $ruleKey ][ $assetType ] as $assetHandle => $assetValues ) {
							if ( strpos( $assetHandle, 'wpacu_hardcoded_' ) !== false ) {
								if ($ruleKey === 'handle_unload_regex') {
									$enableValue                = isset( $assetValues['enable'] ) ? $assetValues['enable'] : '';
									$regExValue                 = isset( $assetValues['value'] ) ? $assetValues['value']   : '';
									$currentHardcodedAssetRules .= '<input type="hidden" name="wpacu_handle_unload_regex[' . $assetType . '][' . $assetHandle . '][enable]" value="' . $enableValue . '" />';
									$currentHardcodedAssetRules .= '<input type="hidden" name="wpacu_handle_unload_regex[' . $assetType . '][' . $assetHandle . '][value]"  value="' . esc_attr( $regExValue ) . '" />';
								} elseif ($ruleKey === 'handle_load_regex') {
									$enableValue                = isset( $assetValues['enable'] ) ? $assetValues['enable'] : '';
									$regExValue                 = isset( $assetValues['value'] ) ? $assetValues['value']   : '';
									$currentHardcodedAssetRules .= '<input type="hidden" name="wpacu_handle_load_regex[' . $assetType . '][' . $assetHandle . '][enable]" value="' . $enableValue . '" />';
									$currentHardcodedAssetRules .= '<input type="hidden" name="wpacu_handle_load_regex[' . $assetType . '][' . $assetHandle . '][value]"  value="' . esc_attr( $regExValue ) . '" />';
								}
							}
						}
					} else {
						// current unloaded on a page level, load_exceptions, handle_load_logged_in
						foreach ( $dataSettingsFrontEnd[ $ruleKey ][ $assetType ] as $assetHandle ) {
							if ( strpos( $assetHandle, 'wpacu_hardcoded_' ) !== false ) {
								if ( $ruleKey === 'current_unloaded_page_level' ) {
									$currentHardcodedAssetRules .= '<input type="hidden" name="wpassetcleanup[' . $assetType . '][]" value="' . $assetHandle . '" />';
								} elseif ( $ruleKey === 'load_exceptions' ) {
									$currentHardcodedAssetRules .= '<input type="hidden" name="wpacu_styles_load_it[]" value="' . $assetHandle . '" />';
								} elseif ($ruleKey === 'handle_load_logged_in') {
									$currentHardcodedAssetRules .= '<input type="hidden" name="wpacu_load_it_logged_in['.$assetType.']['.$assetHandle.']" value="1" />';
								}
							}
						}
					}
				}
			}
		}

        $appendClass = $dataSettingsFrontEnd['plugin_settings']['assets_list_layout'] === 'by-location' ? 'wpacu-by-location' : '';

		return '<div class="wpacu-assets-collapsible-wrap wpacu-wrap-area '.$appendClass.' wpacu-hardcoded" id="wpacu-assets-collapsible-wrap-hardcoded-list" data-wpacu-settings-frontend="'.esc_attr($dataWpacuSettingsFrontend).'">
    <a class="wpacu-assets-collapsible wpacu-assets-collapsible-active" href="#" style="padding: 15px 15px 15px 44px;"><span class="dashicons dashicons-code-standards"></span> Hardcoded (non-enqueued) Styles &amp; Scripts</a>
    <div class="wpacu-assets-collapsible-content" style="max-height: inherit;">
        <div style="padding: 20px 15px; margin: 0;"><img style="margin: 0;" src="'.esc_url(admin_url('images/spinner.gif')).'" align="top" width="20" height="20" alt="" />&nbsp; The list of hardcoded assets is fetched... Please wait...</div>
        '.wp_kses($currentHardcodedAssetRules, array('input' => array('type' => array(), 'name' => array(), 'value' => array()))).'
    </div>
</div>';
	}

    /**
     * @param $dataRowObj
     * @param $data
     * @param $assetType
     *
     * @return array
     */
    public static function wpacuGenerateHardcodedAssetData($dataRowObj, $data, $assetType)
    {
        $dataHH = $data;

        $dataHH['row']        = array();
        $dataHH['row']['obj'] = $dataRowObj;

        $dataHH['row']['class'] = $dataHH['row']['checked'] = ''; // default

        $classPart = ($assetType === 'styles') ? ' style_' : ' script_';
        $dataHH['row']['class'] .= $classPart . $dataHH['row']['obj']->handle;

        $dataHH['row']['asset_type'] = $assetType;

        $dataHH['row']['is_hardcoded'] = true;

        return $dataHH;
    }
}
