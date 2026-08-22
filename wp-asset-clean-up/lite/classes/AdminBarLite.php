<?php
namespace WpAssetCleanUpLite;

/**
 *
 */
class AdminBarLite
{
    /**
     * @param $unloadedAssetsLists
     *
     * @return array
     */
    public static function filterUnloadedAssetsLists($unloadedAssetsLists)
    {
        // Do not print any irrelevant data from the Pro version such as hardcoded CSS/JS
        if (isset($unloadedAssetsLists['styles']) && is_array($unloadedAssetsLists['styles'])) {
            $unloadedAssetsLists['styles'] = array_filter($unloadedAssetsLists['styles'], function ($value) {
                return strpos($value, 'wpacu_hardcoded_style_') !== 0;
            });
        }

        if (isset($unloadedAssetsLists['scripts']) && is_array($unloadedAssetsLists['scripts'])) {
            $unloadedAssetsLists['scripts'] = array_filter($unloadedAssetsLists['scripts'], function ($value) {
                return strpos($value, 'wpacu_hardcoded_script_') !== 0;
            });
        }

        return $unloadedAssetsLists;
    }

    /**
     * @param $wp_admin_bar
     *
     * @return void
     */
    public static function addSupportForumLink($wp_admin_bar)
    {
        $wp_admin_bar->add_menu(array(
            'parent' => 'assetcleanup-parent',
            'id'     => 'assetcleanup-support-forum',
            'title'  => esc_html__('Support Forum', 'wp-asset-clean-up'),
            'href'   => 'https://wordpress.org/support/plugin/wp-asset-clean-up',
            'meta'   => array('target' => '_blank')
        ));
    }
}
