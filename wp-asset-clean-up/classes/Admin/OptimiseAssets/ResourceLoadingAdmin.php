<?php
namespace WpAssetCleanUp\Admin\OptimiseAssets;

use WpAssetCleanUp\MiscArray;
use WpAssetCleanUp\OptimiseAssets\ResourceLoading;
use WpAssetCleanUp\Settings;

/**
 *
 */
class ResourceLoadingAdmin
{
    /**
     *
     */
    public function __construct()
    {
        add_filter(
            'wpacu_settings_form_submit_before_save',
            array('\WpAssetCleanUp\OptimiseAssets\ResourceLoading', 'normalizeResourceLoadingImagesSettings')
        );
    }

    /**
     * "Settings" -- "Resource Loading" -- "Image Attributes"
     *
     * @param $data
     *
     * @return array|array[]
     */
    public static function getImageAttributeRulesForAdmin($data = array())
    {
        if (empty($data)) {
            $settingsClass = new Settings();
            $data          = $settingsClass->getAll();
        }

        $allowedResourceLoadingAttrs = ResourceLoading\ImageAttributes::getAllowedResourceLoadingImageAttributes();

        $resourceLoadingImageAttrRules = MiscArray::getValue($data, 'resource_loading.images.attr.data', array());

        // Always the first one to show (no need to click "Add New Rule")
        if (empty($resourceLoadingImageAttrRules)) {
            $firstAttr = key($allowedResourceLoadingAttrs);
            $firstValues = isset($allowedResourceLoadingAttrs[$firstAttr]) ? $allowedResourceLoadingAttrs[$firstAttr] : array();

            $resourceLoadingImageAttrRules = array(
                array(
                    '_is_new_rule' => true,
                    'match_by'     => 'source',
                    'match_type'   => 'contains',
                    'match_value'  => '',
                    'attribute'    => $firstAttr,
                    'value'        => isset($firstValues[0]) ? $firstValues[0] : ''
                )
            );
        } elseif ( is_array($resourceLoadingImageAttrRules) ) {
            usort($resourceLoadingImageAttrRules, function ($a, $b) {
                $matchByOrder = array(
                    'source'    => 1,
                    'class'     => 2,
                    'whole_tag' => 3
                );

                $matchTypeOrder = array(
                    'contains' => 1,
                    'regex'    => 2
                );

                $aMatchBy = isset($a['match_by']) ? $a['match_by'] : 'source';
                $bMatchBy = isset($b['match_by']) ? $b['match_by'] : 'source';

                $aMatchByOrder = isset($matchByOrder[$aMatchBy]) ? $matchByOrder[$aMatchBy] : 999;
                $bMatchByOrder = isset($matchByOrder[$bMatchBy]) ? $matchByOrder[$bMatchBy] : 999;

                if ($aMatchByOrder !== $bMatchByOrder) {
                    return $aMatchByOrder - $bMatchByOrder;
                }

                $aMatchType = isset($a['match_type']) ? $a['match_type'] : 'contains';
                $bMatchType = isset($b['match_type']) ? $b['match_type'] : 'contains';

                $aMatchTypeOrder = isset($matchTypeOrder[$aMatchType]) ? $matchTypeOrder[$aMatchType] : 999;
                $bMatchTypeOrder = isset($matchTypeOrder[$bMatchType]) ? $matchTypeOrder[$bMatchType] : 999;

                if ($aMatchTypeOrder !== $bMatchTypeOrder) {
                    return $aMatchTypeOrder - $bMatchTypeOrder;
                }

                $aMatchValue = isset($a['match_value'])
                    ? strtolower($a['match_value'])
                    : (isset($a['source']) ? strtolower($a['source']) : '');

                $bMatchValue = isset($b['match_value'])
                    ? strtolower($b['match_value'])
                    : (isset($b['source']) ? strtolower($b['source']) : '');

                return strcmp($aMatchValue, $bMatchValue);
            });
        }

        return $resourceLoadingImageAttrRules;
    }

    /**
     * @param $data
     *
     * @return array
     */
    public static function getImageLazyLoadRulesForAdmin($data = array())
    {
        if (empty($data)) {
            $settingsClass = new Settings();
            $data          = $settingsClass->getAll();
        }

        $resourceLoadingImageLazyLoadRules = MiscArray::getValue($data, 'resource_loading.images.lazy_load', array());

        return $resourceLoadingImageLazyLoadRules;
    }
}
