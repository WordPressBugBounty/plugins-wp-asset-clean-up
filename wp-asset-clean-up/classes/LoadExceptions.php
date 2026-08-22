<?php
namespace WpAssetCleanUp;

/**
 * Class LoadExceptions
 * @package WpAssetCleanUp
 */
class LoadExceptions
{
    /**
    * Case 1: If $postType is not mentioned, it will get all post types
    * Case 2: If $postType is set and $assetType & $handle are not set, it will get all rules for $postType
    * Case 3: If all parameters are set, it will get any terms set for the CSS/JS handle loaded within $postType pages
    *
     * @param string $postType
     * @param string $assetType
     * @param string $handle
     *
     * @return array|\array[][]|mixed
     */
    public static function getTaxonomyValuesAssocToPostTypeLoadExceptions($postType = '', $assetType = '', $handle = '')
    {
        $exceptionsListDefault = array();

        if ($postType) {
            if ($assetType === '' && $handle === '') {
                // Default for all results for this $postType
                $exceptionsListDefault = array($postType => array('styles' => array(), 'scripts' => array()));
            } else {
                // Default for the terms list for the specific $handle of $assetType ("styles" or "scripts")
                $exceptionsListDefault = array();
            }
        }

        $exceptionsListJson = get_option(WPACU_PLUGIN_ID . '_post_type_via_tax_load_exceptions');
        $exceptionsList = @json_decode($exceptionsListJson, true);

        // Issues with decoding the JSON file? Return an empty list
        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            return $exceptionsListDefault;
        }

        // Return any handles added as load exceptions for the requested $postType
        if ($postType !== '' && isset($exceptionsList[$postType])) {
            /*
             * Fetch load exceptions for a certain handle (either a CSS or a JS)
             */
            if ( ! empty($exceptionsList[$postType][$assetType][$handle]['values'])) {
                return $exceptionsList[$postType] [$assetType] [$handle] ['values'];
            }

            if ($assetType === '' && $handle === '') {
                /*
                 * Fetch all load exceptions (CSS & JS)
                 */
                return $exceptionsList[$postType];
            }
        } elseif (is_array($exceptionsList) && ! empty($exceptionsList)) {
            return $exceptionsList;
        }

        return $exceptionsListDefault;
    }

    /**
     * e.g. When an asset is unloaded, site-wide
     * exceptions to the rule can be added, for the asset to load on all pages of [taxonomy] type
     * You might need a CSS/JS to be unloaded site-wide, but on /category/food/, /category/other/
     * you can make an exception, and have the CSS/JS loaded
     *
     * @param $taxonomy (optional, if it's not there, all load exceptions will load for all taxonomies)
     *
     * @return array|array[]|mixed
     */
    public static function getLoadExceptionsViaTaxType($taxonomy = '')
    {
        if ($taxonomy) {
            // Default for all results for this $taxonomy
            $exceptionsListDefault = array($taxonomy => array('styles' => array(), 'scripts' => array()));
        } else {
            // Default for the asset list for the specific $taxonomy ("styles" / "scripts")
            $exceptionsListDefault = array();
        }

        $exceptionsListJson = get_option(WPACU_PLUGIN_ID . '_tax_type_load_exceptions');

        $exceptionsList = @json_decode($exceptionsListJson, true);

        // Issues with decoding the JSON file? Return an empty list
        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            return $exceptionsListDefault;
        }

        // Return any handles added as load exceptions for the requested $taxonomy
        if ($taxonomy !== '' && isset($exceptionsList[$taxonomy])) {
            return $exceptionsList[$taxonomy];
        }

        if (is_array($exceptionsList) && ! empty($exceptionsList)) {
            return $exceptionsList;
        }

        return $exceptionsListDefault;
    }

    /**
     * e.g. When an asset is unloaded, site-wide
     * * exceptions to the rule can be added, for the asset to load on all archive pages of any author
     * * You might need a CSS/JS to be unloaded site-wide, but on /author/[any_author_title_slug_here]/
     * * you can make an exception, and have the CSS/JS loaded
     *
     * @return array|array[]
     */
    public static function getLoadExceptionsViaAuthorType()
    {
        $exceptionsListDefault = array('styles' => array(), 'scripts' => array());

        $exceptionsListJson = get_option(WPACU_PLUGIN_ID . '_author_type_load_exceptions');

        $exceptionsList = @json_decode($exceptionsListJson, true);

        // Issues with decoding the JSON file? Return an empty list
        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            return $exceptionsListDefault;
        }

        if (is_array($exceptionsList) && ! empty($exceptionsList)) {
            return $exceptionsList;
        }

        return $exceptionsListDefault;
    }
}