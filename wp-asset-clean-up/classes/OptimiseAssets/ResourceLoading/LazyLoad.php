<?php
namespace WpAssetCleanUp\OptimiseAssets\ResourceLoading;

use WpAssetCleanUp\MiscArray;
use WpAssetCleanUp\OptimiseAssets\ResourceLoading;

/**
 *
 */
class LazyLoad
{
    /**
     * @var string
     */
    const LOADING_LAZY_SET_IN_TAG_REGEX = '/(^|[\s\/])loading\s*=\s*(?:"lazy"|\'lazy\'|lazy)(?=[\s>\/])/i';

    /**
     * @var string
     */
    const LOADING_LAZY_OR_EAGER_SET_IN_REGEX = '/(^|[\s\/])loading\s*=\s*(["\']?)(lazy|eager)\2(?=[\s>\/])/i';

    /**
     * @var string
     */
    const FETCHPRIORITY_HIGH_SET_IN_REGEX = '/(^|[\s\/])fetchpriority\s*=\s*(?:"high"|\'high\'|high)(?=[\s>\/])/i';

    /**
     * @param $settings
     *
     * @return array|mixed|null
     */
    public static function getOptions($settings)
    {
        // These are mostly static options that were sanitized prior to saving them in the database
        // This is an automatic way to apply lazy loading for images
        $options = MiscArray::getValue($settings, ResourceLoading::$settingKey . '.images.lazy_load', array());

        return is_array($options) ? $options : array();
    }

    /**
     * Checks whether the actual IMG tag has a real `loading` attribute.
     *
     * It ignores cases such as:
     * - alt="loading=eager"
     * - data-info="loading=auto"
     * - data-loading="eager"
     */
    public static function imgTagHasLoadingAttribute($imgTag)
    {
        return (bool) preg_match(
            '/(^|[\s\/])loading\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>\/]+)/i',
            $imgTag
        );
    }

    /**
     * Sets loading="lazy" on an IMG tag.
     *
     * If a real `loading` attribute already exists, its value is replaced.
     * If it does not exist, loading="lazy" is added before the closing `>`.
     *
     * It does NOT alter text inside other attributes, such as:
     * - alt="loading=eager"
     * - data-info="loading=auto"
     * - data-loading="eager"
     */
    public static function setImgTagLoadingLazy($imgTag)
    {
        // 1. Real loading attribute already exists: replace only its value.
        if (self::imgTagHasLoadingAttribute($imgTag)) {
            return preg_replace_callback(
                '/(^|[\s\/])(loading)(\s*=\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\s>\/]+))/i',
                static function ($matches) {
                    $leading    = $matches[1];
                    $attribute  = $matches[2];
                    $equalsPart = $matches[3];

                    // Preserve the original quote style.
                    if (isset($matches[4]) && $matches[4] !== '') {
                        return $leading . $attribute . $equalsPart . '"lazy"';
                    }

                    if (isset($matches[5]) && $matches[5] !== '') {
                        return $leading . $attribute . $equalsPart . "'lazy'";
                    }

                    return $leading . $attribute . $equalsPart . 'lazy';
                },
                $imgTag,
                1
            );
        }

        // 2. No real loading attribute exists: add loading="lazy" before the tag closes.
        return preg_replace(
            '/\s*\/?>$/',
            ' loading="lazy"$0',
            $imgTag,
            1
        );
    }

    /**
     * Add decoding="async" to an IMG tag only if:
     * - it has loading="lazy"
     * - it does not already have a decoding attribute
     *
     * @param string $imgTag
     * @param bool $checkIfLoadingLazyIsSet - sometimes, it's known the attribute is there
     * @return string
     */
    public static function maybeAddDecodingAsyncToLazyImg($imgTag, $checkIfLoadingLazyIsSet = true)
    {
        if ($checkIfLoadingLazyIsSet) {
            // Detect real loading="lazy" / loading='lazy' / loading=lazy attribute.
            // It avoids matching alt="loading=lazy", data-info="loading=lazy", data-loading="lazy", etc.
            $hasLoadingLazy = preg_match(self::LOADING_LAZY_SET_IN_TAG_REGEX, $imgTag);

            if ( ! $hasLoadingLazy) {
                return $imgTag;
            }
        }

        // If decoding="" or decoding='' exists, replace it with decoding="async".
        // This avoids touching data-decoding="" or alt="decoding=""".
        $hasEmptyDecodingAttr = preg_match(
            '/(^|[\s\/])decoding\s*=\s*(?:"\s*"|\'\s*\')/i',
            $imgTag
        );

        if ($hasEmptyDecodingAttr) {
            return preg_replace(
                '/(^|[\s\/])decoding\s*=\s*(?:"\s*"|\'\s*\')/i',
                '$1decoding="async"',
                $imgTag,
                1
            );
        }

        // Detect any real decoding attribute with a value:
        // decoding="async", decoding='sync', decoding=auto, etc.
        $hasDecodingAttr = preg_match(
            '/(^|[\s\/])decoding\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>\/]+)/i',
            $imgTag
        );

        if ($hasDecodingAttr) {
            return $imgTag;
        }

        // Add decoding="async" before the closing ">" or "/>".
        return preg_replace(
            '/\s*\/?>$/',
            ' decoding="async"$0',
            $imgTag,
            1
        );
    }
}
