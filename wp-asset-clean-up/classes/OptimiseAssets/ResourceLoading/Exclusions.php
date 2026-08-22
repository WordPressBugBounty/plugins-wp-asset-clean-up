<?php
namespace WpAssetCleanUp\OptimiseAssets\ResourceLoading;

use WpAssetCleanUp\OptimiseAssets\ResourceLoading;

/**
 *
 */
class Exclusions
{
    /**
     * @param $imgTag
     * @param $imageIndex
     * @param $lazyLoadSettings
     *
     * @return bool
     */
    public static function excludeMatchedImgTag($imgTag, $imageIndex, $lazyLoadSettings)
    {
        if ( ! is_array($lazyLoadSettings)) {
            return false;
        }

        $excludeFirst = isset($lazyLoadSettings['exclude_first']) && is_scalar($lazyLoadSettings['exclude_first'])
            ? max(0, (int)$lazyLoadSettings['exclude_first'])
            : 0;

        // Note: $imageIndex starts from 0
        // e.g. if "Exclude First" is set to 2 images, then anything below 2 (0 and 1) is exluded
        if ($excludeFirst > 0 && $imageIndex < $excludeFirst) {
            return true;
        }

        // Exclude via "CSS Classes"
        $skipViaCssClassesTextarea = isset($lazyLoadSettings['skip_via_css_classes']) && is_string($lazyLoadSettings['skip_via_css_classes'])
            ? trim($lazyLoadSettings['skip_via_css_classes'])
            : '';

        if ( $skipViaCssClassesTextarea ) {
            $imgTagClassValue = trim(ImageAttributes::getAttributeValueFromImgTag($imgTag, 'class'));

            if ($imgTagClassValue &&
                Exclusions::excludeImgHasClassInSkippedClassesTextarea($imgTagClassValue, $skipViaCssClassesTextarea)) {
                return true;
            }
        }

        // Exclude via "URL Keywords"
        $skipViaSourceKeywordsTextarea = isset($lazyLoadSettings['skip_via_source_keywords']) && is_string($lazyLoadSettings['skip_via_source_keywords'])
            ? trim($lazyLoadSettings['skip_via_source_keywords'])
            : '';

        if ($skipViaSourceKeywordsTextarea &&
            Exclusions::excludeImgHasSourceMatchesFromSkippedUrlKeywords($imgTag, $skipViaSourceKeywordsTextarea)) {
            return true;
        }

        return false;
    }

    /**
     * @param $imgTag
     * @param $skipViaSourceKeywordsTextarea
     *
     * @return bool
     */
    public static function excludeImgHasSourceMatchesFromSkippedUrlKeywords($imgTag, $skipViaSourceKeywordsTextarea)
    {
        $allPossbileSourcesKeywordsToExclude = array();

        if (strpos($skipViaSourceKeywordsTextarea, "\n") === false) {
            $allPossbileSourcesKeywordsToExclude[] = $skipViaSourceKeywordsTextarea;
        } else {
            foreach (explode("\n", $skipViaSourceKeywordsTextarea) as $possibleSourceLine) {
                if (trim($possibleSourceLine)) {
                    $allPossbileSourcesKeywordsToExclude[] = $possibleSourceLine;
                }
            }
        }

        $matchBy = 'source';

        $allSourceValuesFromImgTag = ResourceLoading::getImgTagValuesToMatchAgainst($imgTag, $matchBy);

        if (empty($allSourceValuesFromImgTag[$matchBy]) || ! is_array($allSourceValuesFromImgTag[$matchBy])) {
            return false;
        }

        foreach ($allPossbileSourcesKeywordsToExclude as $possibleSourceKeywordToExclude) {
            // On each line from the textarea (e.g. if there are multiple lines)
            // Go through all the possible sources found: 'src', 'srcset', 'data-src', 'data-srcset'
            foreach ($allSourceValuesFromImgTag[$matchBy] as $sourceValueFromImgTag) {
                if (ResourceLoading::isMatchViaKeyword($sourceValueFromImgTag, $possibleSourceKeywordToExclude)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $imgTagClassValue
     * @param $skipViaCssClassesTextarea
     *
     * @return bool
     */
    public static function excludeImgHasClassInSkippedClassesTextarea($imgTagClassValue, $skipViaCssClassesTextarea)
    {
        $allSkipCssClassesPerLine = array();

        $skipViaCssClassesTextarea = trim($skipViaCssClassesTextarea);

        if (strpos($skipViaCssClassesTextarea, "\n") === false) {
            $allSkipCssClassesPerLine[] = $skipViaCssClassesTextarea;
        } else {
            foreach (explode("\n", $skipViaCssClassesTextarea) as $skipViaCssLine) {
                $allSkipCssClassesPerLine[] = trim($skipViaCssLine);
            }
        }

        foreach ($allSkipCssClassesPerLine as $skipThisCssClass) {
            if (ResourceLoading::imgClassAttrIsMatchedFromClassSelector($imgTagClassValue, $skipThisCssClass)) {
                return true; // exclude image from lazy load
            }
        }

        return false;
    }

}
