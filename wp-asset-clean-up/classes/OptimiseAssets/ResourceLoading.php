<?php
namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\MiscArray;
use WpAssetCleanUp\OptimiseAssets\ResourceLoading\Exclusions;
use WpAssetCleanUp\OptimiseAssets\ResourceLoading\LazyLoad;

/**
 *
 */
class ResourceLoading
{
    /**
     * @var string
     */
    public static $settingKey = 'resource_loading'; // e.g. wpassetcleanup_settings[resource_loading]

    /**
     * It has the option to be wrapped for instance like this: #(the_regex_value_below)#i
     *
     * @return string
     */
    public static function getMainRegExFetchImgTags()
    {
        $regEx = <<<'REGEXP'
<img\b(?:\s+[a-z_:][-a-z0-9_:.]*(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s'"=<>`]+))?)*\s*/?>
REGEXP;

        return $regEx;
    }

    /**
     * @param $settings
     * @param $for
     *
     * e.g. if $for equals "image.attr" and $checkRules is set to true,
     * make sure to also check if there are any rules set, and if there is none set, consider it disabled
     * when set to true, $for has to be one of the following: "images.attr"
     *
     * @param bool $checkRules
     *
     * @return bool
     */
    public static function isEnabled($settings, $for = 'main', $checkRules = false)
    {
        $paths = array(
            'main' => array(
                self::$settingKey,
                '_enabled'
            ),

            'images.attr' => array(
                self::$settingKey,
                'images',
                'attr',
                '_enabled'
            ),

            'images.lazy_load' => array(
                self::$settingKey,
                'images',
                'lazy_load',
                '_enabled'
            )
        );

        if ( ! isset($paths[$for]) ) {
            return false;
        }

        $value = $settings;

        foreach ($paths[$for] as $key) {
            if ( ! isset($value[$key]) ) {
                return false;
            }

            $value = $value[$key];
        }

        $isMaybeEnabled = (int)$value === 1;

        if ( ! $isMaybeEnabled ) {
            return false; // it's not "Enabled", no further checks!
        }

        // $checkRules is irrelevant here
        if ( in_array($for, array('main', 'images.lazy_load')) ) {
            return $isMaybeEnabled;
        }

        // Enabled! Check also the rules, and if they are empty, return false
        if ($isMaybeEnabled && $checkRules && $for === 'images.attr') {
           $rules = ResourceLoading\ImageAttributes::getImageAttrRules($settings);

           if ( ! empty($rules) ) {
               return true;
           }
        }

        return false;
    }

    /**
     * @param $htmlSource
     * @param array $forMainOptions | possible values: "attr" (from "Image Attributes); "lazy_load" (from "Lazy Load")
     *
     * @return array|string|string[]|null
     */
    public static function applyImageChangesToHtmlSource($htmlSource, $forMainOptions)
    {
        $settings = Main::instance()->settings;

        $allChanges = array();

        foreach ($forMainOptions as $mainRuleKey) {
            if ($mainRuleKey === 'attr') {
                $allChanges[$mainRuleKey] = ResourceLoading\ImageAttributes::getImageAttrRules($settings);
            } else {
                $allChanges[$mainRuleKey] = ResourceLoading\LazyLoad::getOptions($settings);
            }
        }

        $allImgTagsFound = array();

        $regEx = self::getMainRegExFetchImgTags();

        if (preg_match_all('#' . $regEx . '#i', $htmlSource, $matches, PREG_OFFSET_CAPTURE)) {
            $allImgTagsFound = $matches[0];
        }

        if (empty($allImgTagsFound)) {
            return $htmlSource;
        }

        $allImgTagsFound = array_reverse($allImgTagsFound, true);

        foreach ($allImgTagsFound as $imageIndex => $imgTagData) {
            $imgTag       = isset($imgTagData[0]) ? $imgTagData[0] : '';
            $imgTagOffset = isset($imgTagData[1]) ? $imgTagData[1] : '';

            // The image has to contain at least the following source attrbutes: 'src', 'srcset', 'data-src', 'data-srcset'
            if ( ! preg_match('/(^|[\s\/])(?:data-srcset|data-src|srcset|src)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>\/]+)/i', $imgTag) ) {
                continue;
            }

            // Continue with images that have proper external file (no HTML embedded)
            if (self::imgTagHasDataUriSource($imgTag)) {
                continue;
            }

            $extraReferences = array();

            $updatedImgTag = self::applyChangesToMatchedImgTag($imgTag, $imageIndex, $allChanges, $extraReferences);

            if ($updatedImgTag === $imgTag) {
                continue;
            }

            $htmlSource = substr_replace(
                $htmlSource,
                $updatedImgTag,
                $imgTagOffset,
                strlen($imgTag)
            );
        }

        return $htmlSource;
    }

    /**
     * @param string $imgTag
     * @param int $imageIndex
     * @param array $allChanges (could be from 'attr', 'lazy_load', one or more)
     * @param array $extraReferences
     *
     * @return array|mixed|string|string[]|null
     */
    public static function applyChangesToMatchedImgTag($imgTag, $imageIndex, $allChanges, $extraReferences = array())
    {
        if ( ! is_array($allChanges)) {
            return $imgTag;
        }

        foreach ($allChanges as $mainRuleKey => $rules) {
            /*
             * [START] "Image Attributes" sub-tab changes
            */
            if ($mainRuleKey === 'attr') {
                if ( ! is_array($rules)) {
                    continue;
                }

                // These have priority / e.g. if loading="lazy" was applied automatically to an image (via the "Lazy Load" area)
                // but the user specifically had reasons set for this image loading="eager" even if the image seems to be needed later
                // then loading="lazy" will be replaced by loading="eager"
                // The granular control in "Image Attributes" will always be priorital over any automatic rule
                foreach ($rules as $rule) {
                    if ( ! is_array($rule)) {
                        continue;
                    }

                    $attribute = isset($rule['attribute']) && is_string($rule['attribute']) ? $rule['attribute'] : '';
                    $value     = isset($rule['value']) && is_string($rule['value']) ? $rule['value'] : '';

                    if ($attribute === '' || $value === '') {
                        continue;
                    }

                    if ( ! self::imageAttributeRuleMatchesImgTag($imgTag, $rule) ) {
                        continue;
                    }

                    $imgTag = ResourceLoading\ImageAttributes::addOrUpdateHtmlImgTag(
                        $imgTag,
                        $attribute,
                        $value
                    );
                }
            }
            /*
            * [END] "Image Attributes" sub-tab changes
            */

            /*
            * [START] "Lazy Load" sub-tab changes
            */
            if ($mainRuleKey === 'lazy_load') {
                if ( ! is_array($rules)) {
                    continue;
                }

                $lazyLoadSettings = array_merge(array(
                    'exclude_first'  => 0,
                    'decoding_async' => 0
                ), $rules);

                // Is the image lazy loadable?
                // If attribute rules have already applied, we have to take them into account,
                // as they have priority over the automatic lazy loading

                // loading="eager" or loading="lazy" already set? Skip it!
                if ( stripos($imgTag, 'loading') !== false ) {
                    preg_match(LazyLoad::LOADING_LAZY_OR_EAGER_SET_IN_REGEX, $imgTag, $matches);

                    $loadingValue = isset($matches[3]) && $matches[3] ? strtolower($matches[3]) : '';

                    if ($loadingValue === 'lazy') {
                        if (isset($lazyLoadSettings['decoding_async']) && $lazyLoadSettings['decoding_async']) {
                            $imgTag = LazyLoad::maybeAddDecodingAsyncToLazyImg($imgTag, false);
                        }

                        return $imgTag;
                    } elseif ($loadingValue === 'eager') {
                        return $imgTag;
                    }

                    // If "loading" attribute exists but its value is neither "lazy" nor "eager" (e.g. "auto"),
                    // fall through and let setImgTagLoadingLazy() replace it with "lazy" (if there are no forther exclusions applied)
                }

                if ( stripos($imgTag, 'fetchpriority') !== false && preg_match(LazyLoad::FETCHPRIORITY_HIGH_SET_IN_REGEX, $imgTag, $matches) ) {
                    return $imgTag;
                }

                // Any explicit exclusions? Return the same IMG tag
                if (Exclusions::excludeMatchedImgTag($imgTag, $imageIndex, $lazyLoadSettings)) {
                    return $imgTag;
                }

                // Apply loading="lazy" by replacing any hardcoded (e.g. not set in "Image Attributes") "loading" attributes already set (e.g. "auto")
                // If the attribute doesn't already exist, it will be added
                $imgTag = LazyLoad::setImgTagLoadingLazy($imgTag);

                // loading="lazy" set: Apply decoding="async" if there's no decoding attribute with a value set
                if ( isset($lazyLoadSettings['decoding_async']) && $lazyLoadSettings['decoding_async'] ) {
                    $imgTag = LazyLoad::maybeAddDecodingAsyncToLazyImg($imgTag, false);
                }
            }
            /*
            * [END] "Lazy Load" sub-tab changes
            */
        }

        return $imgTag;
    }

    /**
     *
     * @param $string
     * @param $find
     *
     * @return bool
     */
    public static function isMatchViaKeyword($string, $find)
    {
        if ($find === '') {
            return false;
        }

        // Fast path: string match
        if (stripos($string, $find) !== false ) {
            return true;
        }

        if ( ResourceLoading::startsWithRegexDelimiter($find) ) {
            if ( ! ResourceLoading::isValidRegex($find) ) {
                return false; // Invalid RegEx (stop here)
            }

            $maybeIsMatch = @preg_match($find, $string);

            return $maybeIsMatch === 1;
        }

        return false; // No matches
    }

    /**
     * @param $imgTag
     * @param array $rule
     *
     * @return bool
     */
    public static function imageAttributeRuleMatchesImgTag($imgTag, $rule)
    {
        if ( ! is_array($rule)) {
            return false;
        }

        $matchBy   = isset($rule['match_by']) && is_string($rule['match_by']) ? $rule['match_by'] : 'source';
        $matchType = isset($rule['match_type']) && is_string($rule['match_type']) ? $rule['match_type'] : '';

        if (isset($rule['match_value']) && is_string($rule['match_value'])) {
            $matchValue = $rule['match_value'];
        } elseif (isset($rule['source']) && is_string($rule['source'])) {
            $matchValue = $rule['source'];
        } else {
            $matchValue = '';
        }

        if ($matchValue === '') {
            return false;
        }

        if ($matchType === '') {
            $matchType = ResourceLoading::startsWithRegexDelimiter($matchValue) ? 'regex' : 'contains';
        }

        $valuesToMatchAgainst = self::getImgTagValuesToMatchAgainst($imgTag, $matchBy);

        if (empty($valuesToMatchAgainst)) {
            return false;
        }

        foreach ($valuesToMatchAgainst[$matchBy] as $valueToMatchAgainst) {
            if ($valueToMatchAgainst === '') {
                continue;
            }

            if ($matchType === 'contains') {
                // "Class" is a special case
                // We can have in the input: "classOne" or ".classOne" or ".classOne.classTwo", etc.
                if ($matchBy === 'class' && self::imgClassAttrIsMatchedFromClassSelector($valueToMatchAgainst, $matchValue)) {
                    return true;
                } elseif (stripos($valueToMatchAgainst, $matchValue) !== false) {
                    return true;
                }
            }

            if ($matchType === 'regex') {
                $maybeIsMatch = @preg_match($matchValue, $valueToMatchAgainst);

                if ($maybeIsMatch === 1) {
                    return true;
                }
            }
        }

        return false; // No matches
    }

    /**
     * @param $imgTag
     * @param $matchBy
     *
     * @return array
     */
    public static function getImgTagValuesToMatchAgainst($imgTag, $matchBy)
    {
        $valuesToMatchAgainst = array();

        if ($matchBy === 'whole_tag') {
            $valuesToMatchAgainst[$matchBy] = array($imgTag);
        } elseif ($matchBy === 'class' && stripos($imgTag, 'class') !== false) {
            $classValue = trim(ResourceLoading\ImageAttributes::getAttributeValueFromImgTag($imgTag, 'class'));

            if ($classValue !== '') {
                $valuesToMatchAgainst[$matchBy] = array($classValue);
            }
        } elseif ($matchBy === 'source') {
            $imageSourceAttributes = array('src', 'srcset', 'data-src', 'data-srcset');

            $valuesToMatchAgainst = array();

            foreach ($imageSourceAttributes as $imageSourceAttribute) {
                $imageSourceAttributeValue = trim(ResourceLoading\ImageAttributes::getAttributeValueFromImgTag($imgTag, $imageSourceAttribute));

                if ($imageSourceAttributeValue === '') {
                    continue;
                }

                $valuesToMatchAgainst[$matchBy][$imageSourceAttribute] = $imageSourceAttributeValue;
            }
        }

        return $valuesToMatchAgainst;
    }

    /**
     * This has to be on the IMG tag
     *
     * $classAttr = "hero banner featured dark";
     *
     * self::imgClassAttrIsMatchedFromClassSelector($classAttr, '.hero');         // true
     * self::imgClassAttrIsMatchedFromClassSelector($classAttr, '.hero.banner');  // true
     * self::imgClassAttrIsMatchedFromClassSelector($classAttr, 'hero');          // true
     * self::imgClassAttrIsMatchedFromClassSelector($classAttr, 'hero banner');   // true
     * self::imgClassAttrIsMatchedFromClassSelector($classAttr, 'hero missing');  // false
     * self::imgClassAttrIsMatchedFromClassSelector($classAttr, ''');             // false
     *
     * @param $imgClassAttribute
     * @param $inputClass
     *
     * @return bool
     */
    public static function imgClassAttrIsMatchedFromClassSelector($imgClassAttribute, $inputClass)
    {
        $inputClass = trim(strtolower($inputClass));

        if (empty($inputClass)) {
            return false;
        }

        // Split după spații și/sau puncte, indiferent de combinație
        $requiredClasses = array_filter(
            preg_split('/[\s.]+/', $inputClass),
            function($part) {
                return $part !== '';
            }
        );

        if (empty($requiredClasses)) {
            return false;
        }

        $existingClasses = preg_split('/\s+/', trim(strtolower($imgClassAttribute)), -1, PREG_SPLIT_NO_EMPTY);

        foreach ( $requiredClasses as $class ) {
            if ( ! in_array($class, $existingClasses, true) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $pattern
     *
     * @return bool
     */
    public static function startsWithRegexDelimiter($pattern)
    {
        if ( ! is_string($pattern) || $pattern === '' ) {
            return false;
        }

        return in_array($pattern[0], array('#', '~', '!', '%'), true);
    }

    /**
     * @param $pattern
     *
     * @return bool
     */
    public static function isValidRegex($pattern)
    {
        if ( ! self::startsWithRegexDelimiter($pattern) || strlen($pattern) < 3 ) {
            return false;
        }

        $delimiter = $pattern[0];
        $lastDelimiterPos = self::findLastUnescapedDelimiter($pattern, $delimiter);

        if ($lastDelimiterPos === false || $lastDelimiterPos === 0) {
            return false;
        }

        $flags = substr($pattern, $lastDelimiterPos + 1);

        if ($flags !== '' && ! @preg_match('/^[imsxuADSUXJ]*$/', $flags)) {
            return false;
        }

        return @preg_match($pattern, '') !== false;
    }

    /**
     * @param $pattern
     * @param $delimiter
     *
     * @return false|int
     */
    private static function findLastUnescapedDelimiter($pattern, $delimiter)
    {
        $length = strlen($pattern);

        for ($i = $length - 1; $i > 0; $i--) {
            if ($pattern[$i] !== $delimiter) {
                continue;
            }

            $backslashes = 0;

            for ($j = $i - 1; $j >= 0 && $pattern[$j] === '\\'; $j--) {
                $backslashes++;
            }

            if ($backslashes % 2 === 0) {
                return $i;
            }
        }

        return false;
    }

    /**
     * Array Structure Update
     *
     * Lite: from v1.4.0.4 to v1.4.0.5
     * Pro: from v1.2.7.1 to v1.2.7.2
     *
     * @param $settings
     *
     * @return array|mixed
     */
    public static function normalizeResourceLoadingImagesSettings($settings)
    {
        // No values yet
        if (MiscArray::isEmpty($settings, self::$settingKey.'.images', true)) {
            return $settings;
        }

        // Already migrated
        if ( MiscArray::isValid($settings, self::$settingKey.'.images.attr', true) ) {
            return $settings;
        }

        $maybeOldImagesEnabled = MiscArray::getValue($settings, self::$settingKey.'._enabled', true);

        $oldImagesData = array();

        foreach ($settings[self::$settingKey]['images'] as $key => $imageData) {
            if ( $key === '_enabled' ) {
                continue;
            }

            if ( ! is_array($imageData) ) {
                continue;
            }

            $source = isset($imageData['source']) && is_string($imageData['source'])
                ? trim(wp_unslash($imageData['source']))
                : '';

            $attrName = isset($imageData['attribute']) && is_string($imageData['attribute'])
                ? trim(wp_unslash($imageData['attribute']))
                : '';
            $attrValue = isset($imageData['value']) && is_string($imageData['value'])
                ? trim(wp_unslash($imageData['value']))
                : '';

            if ($source === '' || $attrName === '' || $attrValue === '') {
                continue;
            }

            $oldImagesData[] = array(
                'match_by'    => 'source',
                'match_type'  => self::startsWithRegexDelimiter($source) ? 'regex' : 'contains',
                'match_value' => $source,

                'attribute'   => $attrName,
                'value'       => $attrValue
            );
        }

        if ( empty($oldImagesData) ) {
            return $settings;
        }

        $settings[self::$settingKey]['images'] = array(
            'attr' => array(
                '_enabled' => $maybeOldImagesEnabled,
                'data'     => $oldImagesData
            )
        );

        return $settings;
    }

    /**
     * @param $htmlSource
     *
     * @return array|mixed|string|string[]|null
     */
    public static function alterHtmlSource($htmlSource)
    {
        if (! $htmlSource || stripos($htmlSource, '<img') === false) {
            return $htmlSource;
        }

        $settings = Main::instance()->settings;

        // Main "Resource Loading": Active/Inactive
        if ( ! self::isEnabled($settings) ) {
            return $htmlSource;
        }

        $isEnabledImagesAttr     = self::isEnabled($settings, 'images.attr', true);   // Image Attributes
        $isEnabledImagesLazyLoad = self::isEnabled($settings, 'images.lazy_load');              // Lazy Load

        if ( $isEnabledImagesAttr || $isEnabledImagesLazyLoad ) {
            $forMainOptions = array();

            if ($isEnabledImagesAttr) {
                $forMainOptions[] = 'attr';
            }

            if ($isEnabledImagesLazyLoad) {
                $forMainOptions[] = 'lazy_load';
            }

            $htmlSource = self::applyImageChangesToHtmlSource($htmlSource, $forMainOptions);
        }

        return $htmlSource;
    }

    /**
     * @param $imgTag
     *
     * @return bool
     */
    public static function imgTagHasDataUriSource($imgTag)
    {
        return preg_match('#\s(?:src|data-src)\s*=\s*("|\')\s*data:image/#i', $imgTag) === 1;
    }
}
