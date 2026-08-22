<?php
namespace WpAssetCleanUp\OptimiseAssets\ResourceLoading;

use WpAssetCleanUp\MiscArray;
use WpAssetCleanUp\ObjectCache;
use WpAssetCleanUp\OptimiseAssets\ResourceLoading;

class ImageAttributes
{
    /**
     * @param array $settings
     *
     * @return array
     */
    public static function getImageAttrRules($settings)
    {
        $rules = ObjectCache::wpacu_cache_get('wpacu_' . ResourceLoading::$settingKey . '_image_attr_rules');

        if ($rules !== false) {
            return $rules;
        }

        $imageRules = MiscArray::getValue($settings, ResourceLoading::$settingKey . '.images.attr.data', array());

        if (empty($imageRules)) {
            return array();
        }

        $allowedAttributes = self::getAllowedResourceLoadingImageAttributes();

        $rules = array();

        foreach ($imageRules as $rule) {
            if ( ! is_array($rule)) {
                continue;
            }

            $matchBy    = isset($rule['match_by']) && is_string($rule['match_by']) ? trim(wp_unslash($rule['match_by'])) : 'source';
            $matchValue = isset($rule['match_value']) && is_string($rule['match_value']) ? trim(wp_unslash($rule['match_value'])) : '';

            // Backward compatibility with rules saved before match_by, match_type and match_value were introduced.
            if ($matchValue === '' && isset($rule['source']) && is_string($rule['source'])) {
                $matchBy    = 'source';
                $matchValue = trim(wp_unslash($rule['source']));
            }

            $matchType = isset($rule['match_type']) && is_string($rule['match_type']) ? trim(wp_unslash($rule['match_type'])) : '';

            if ($matchType === '') {
                $matchType = ResourceLoading::startsWithRegexDelimiter($matchValue) ? 'regex' : 'contains';
            }

            $attribute  = isset($rule['attribute']) && is_string($rule['attribute']) ? trim(wp_unslash($rule['attribute'])) : '';
            $value      = isset($rule['value']) && is_string($rule['value']) ? trim(wp_unslash($rule['value'])) : '';

            if ($matchValue === '' || $attribute === '' || $value === '') {
                continue;
            }

            if (strlen($matchValue) > 500) {
                continue;
            }

            if ( ! isset($allowedAttributes[$attribute]) || ! in_array($value, $allowedAttributes[$attribute], true) ) {
                continue;
            }

            $allowedMatchBy = self::getAllowedResourceLoadingImageAttributeMatchBy();

            if ( ! isset($allowedMatchBy[$matchBy]) ) {
                continue;
            }

            $allowedMatchTypes = self::getAllowedResourceLoadingImageAttributeMatchTypes();

            if ( ! isset($allowedMatchTypes[$matchType]) ) {
                continue;
            }

            if ($matchType === 'regex' && ! ResourceLoading::isValidRegex($matchValue)) {
                continue;
            }

            $rules[] = array(
                'match_by'    => $matchBy,
                'match_type'  => $matchType,
                'match_value' => $matchValue,
                'attribute'   => $attribute,
                'value'       => $value
            );
        }

        ObjectCache::wpacu_cache_add('wpacu_' . ResourceLoading::$settingKey . '_image_attr_rules', $rules);

        return $rules;
    }

    /**
     * Extracts an exact attribute from an IMG tag without confusing it with a data-* attribute.
     *
     * @param string $imgTag
     * @param string $attribute
     *
     * @return string
     */
    public static function getAttributeValueFromImgTag($imgTag, $attribute)
    {
        if ( ! is_string($imgTag) || $imgTag === '' || ! is_string($attribute) || $attribute === '') {
            return '';
        }

        $attributeForRegEx = preg_quote($attribute, '#');
        $whiteSpace        = '\\x20\\t\\r\\n\\f';

        if ( ! preg_match(
            '#(?:^|[' . $whiteSpace . '])' . $attributeForRegEx . '[' . $whiteSpace . ']*=[' . $whiteSpace . ']*(?:(["\'])(.*?)\\1|([^' . $whiteSpace . '>]+))#is',
            $imgTag,
            $matches
        ) ) {
            return '';
        }

        return isset($matches[1]) && $matches[1] !== ''
            ? (isset($matches[2]) ? $matches[2] : '')
            : (isset($matches[3]) ? $matches[3] : '');
    }

    /**
     * @param $imgTag
     * @param $attribute
     * @param $value
     *
     * @return array|string|string[]|null
     */
    public static function addOrUpdateHtmlImgTag($imgTag, $attribute, $value)
    {
        if ($imgTag === '' || $attribute === '') {
            return $imgTag;
        }

        $attributeForRegEx = preg_quote($attribute, '#');
        $valueEscaped      = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        //$extraSignature = ''; // useful for debugging
        //  ' '.$extraSignature.' ' . $attribute . '="' . $valueEscaped . '"',
        if (stripos($imgTag, $attribute) !== false) {
            // 1. Replace attribute with quoted value (" or ')
            if (preg_match('#\s' . $attributeForRegEx . '\s*=\s*("|\')[^"\']*\1#i', $imgTag)) {
                return preg_replace(
                    '#\s' . $attributeForRegEx . '\s*=\s*("|\')[^"\']*\1#i',
                    ' ' . $attribute . '="' . $valueEscaped . '"',
                    $imgTag,
                    1
                );
            }

            // 2. Replace attribute with unquoted value
            if (preg_match('#\s' . $attributeForRegEx . '\s*=\s*([^\s>]+)#i', $imgTag)) {
                return preg_replace(
                    '#\s' . $attributeForRegEx . '\s*=\s*([^\s>]+)#i',
                    ' ' . $attribute . '="' . $valueEscaped . '"',
                    $imgTag,
                    1
                );
            }
        }

        // The attribute doesn't exist
        // Add it before closing tag (safe placement)
        return preg_replace(
            '#\s*/?>$#',
            ' ' . $attribute . '="' . $valueEscaped . '"$0',
            $imgTag,
            1
        );
    }

    /**
     * @return array[]
     */
    public static function getAllowedResourceLoadingImageAttributes()
    {
        return array(
            'fetchpriority' => array('high', 'low', 'auto'),
            'loading'       => array('eager', 'lazy'),
            'decoding'      => array('async', 'sync', 'auto')
        );
    }

    /**
     * @return string[]
     */
    public static function getAllowedResourceLoadingImageAttributeMatchBy()
    {
        return array(
            'source'    => __('Image source', 'wp-asset-clean-up'),
            'class'     => __('CSS class', 'wp-asset-clean-up'),
            'whole_tag' => __('Whole IMG tag', 'wp-asset-clean-up')
        );
    }

    /**
     * @return string[]
     */
    public static function getAllowedResourceLoadingImageAttributeMatchTypes()
    {
        return array(
            'contains' => __('Contains', 'wp-asset-clean-up'),
            'regex'    => __('Matches RegEx', 'wp-asset-clean-up')
        );
    }
}
