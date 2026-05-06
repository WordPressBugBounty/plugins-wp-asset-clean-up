<?php
namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Admin\SettingsAdmin;
use WpAssetCleanUp\Main;

/**
 *
 */
class ResourceLoading
{
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

        $resourceLoading = isset($settings['resource_loading']) && is_array($settings['resource_loading'])
            ? $settings['resource_loading']
            : array();

        if (empty($resourceLoading['_enabled'])) {
            return $htmlSource;
        }

        $rules = self::getImageRules($resourceLoading);

        if (empty($rules)) {
            return $htmlSource;
        }

        return self::applyImageRulesUsingRegex($htmlSource, $rules);
    }

    /**
     * @param $resourceLoading
     *
     * @return array
     */
    public static function getImageRules($resourceLoading)
    {
        $imageRules = isset($resourceLoading['images']) && is_array($resourceLoading['images'])
            ? $resourceLoading['images']
            : array();

        if ( empty($imageRules) ) {
            return array();
        }

        $allowedAttributes = SettingsAdmin::$allowedResourceLoadingAttributes;

        $rules = array();

        foreach ($imageRules as $rule) {
            if ( ! is_array($rule) ) {
                continue;
            }

            $source    = isset($rule['source'])    ? trim(wp_unslash($rule['source'])) : '';
            $attribute = isset($rule['attribute']) ? trim(wp_unslash($rule['attribute'])) : '';
            $value     = isset($rule['value'])     ? trim(wp_unslash($rule['value'])) : '';

            if ($source === '' || $attribute === '' || $value === '') {
                continue;
            }

            if (strlen($source) > 500) {
                continue;
            }

            if (! isset($allowedAttributes[$attribute])) {
                continue;
            }

            if (! in_array($value, $allowedAttributes[$attribute], true)) {
                continue;
            }

            $rules[] = array(
                'source'    => $source,
                'attribute' => $attribute,
                'value'     => $value
            );
        }

        return $rules;
    }

    /**
     * @param $htmlSource
     * @param $rules
     *
     * @return array|string|string[]|null
     */
    public static function applyImageRulesUsingRegex($htmlSource, $rules)
    {
        $regEx = <<<'REGEXP'
<img\b(?:\s+[a-z_:][-a-z0-9_:.]*(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s'"=<>`]+))?)*\s*/?>
REGEXP;

        return preg_replace_callback('#'.$regEx.'#i', function ($matches) use ($rules) {
            return self::applyRulesToImgTag($matches[0], $rules);
        }, $htmlSource);
    }

    /**
     * @param $imgTag
     * @param $rules
     *
     * @return array|mixed|string|string[]|null
     */
    public static function applyRulesToImgTag($imgTag, $rules)
    {
        if (self::imgTagHasDataUriSource($imgTag)) {
            return $imgTag;
        }

        foreach ($rules as $rule) {
            if (! self::ruleMatchesImgTag($imgTag, $rule['source'])) {
                continue;
            }

            $imgTag = self::addOrUpdateHtmlTagAttribute(
                $imgTag,
                $rule['attribute'],
                $rule['value']
            );
        }

        return $imgTag;
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

    /**
     * @param $imgTag
     * @param $source
     *
     * @return bool
     */
    public static function ruleMatchesImgTag($imgTag, $source)
    {
        if ($source === '') {
            return false;
        }

        // Fast path: string match
        if (stripos($imgTag, $source) !== false) {
            return true;
        }

        // Try regex only dacă e sigur
        if (! self::looksLikeRegex($source)) {
            return false;
        }

        set_error_handler(function () {}, E_WARNING);
        $isMatch = preg_match($source, $imgTag);
        restore_error_handler();

        return $isMatch === 1;
    }

    /**
     * @param $pattern
     *
     * @return bool
     */
    public static function looksLikeRegex($pattern)
    {
        if (! is_string($pattern) || strlen($pattern) < 3) {
            return false;
        }

        $delimiter = $pattern[0];

        // delimitatori comuni și siguri
        if (! in_array($delimiter, array('/', '#', '~', '%', '!'), true)) {
            return false;
        }

        // trebuie să se termine cu același delimitator (cu sau fără flags)
        $lastDelimiterPos = strrpos($pattern, $delimiter);

        if ($lastDelimiterPos === 0) {
            return false;
        }

        // ce vine după delimitator sunt flags (opțional)
        $flags = substr($pattern, $lastDelimiterPos + 1);

        if ($flags !== '' && ! preg_match('/^[imsxuADSUXJ]*$/', $flags)) {
            return false;
        }

        return true;
    }

    /**
     * @param $tag
     * @param $attribute
     * @param $value
     *
     * @return array|string|string[]|null
     */
    public static function addOrUpdateHtmlTagAttribute($tag, $attribute, $value)
    {
        if ($tag === '' || $attribute === '') {
            return $tag;
        }

        $attributeForRegEx = preg_quote($attribute, '#');
        $valueEscaped      = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        // 1. Replace attribute with quoted value (" or ')
        if (preg_match('#\s' . $attributeForRegEx . '\s*=\s*("|\')[^"\']*\1#i', $tag)) {
            return preg_replace(
                '#\s' . $attributeForRegEx . '\s*=\s*("|\')[^"\']*\1#i',
                ' ' . $attribute . '="' . $valueEscaped . '"',
                $tag,
                1
            );
        }

        // 2. Replace attribute with unquoted value
        if (preg_match('#\s' . $attributeForRegEx . '\s*=\s*([^\s>]+)#i', $tag)) {
            return preg_replace(
                '#\s' . $attributeForRegEx . '\s*=\s*([^\s>]+)#i',
                ' ' . $attribute . '="' . $valueEscaped . '"',
                $tag,
                1
            );
        }

        // 3. Add attribute before closing tag (safe placement)
        return preg_replace(
            '#\s*/?>$#',
            ' ' . $attribute . '="' . $valueEscaped . '"$0',
            $tag,
            1
        );
    }
}