<?php
namespace WpAssetCleanUpLite\Admin;

use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Admin\Plugin;

/**
 * Class PluginLite
 * @package WpAssetCleanUpLite\Admin
 */
class PluginLite
{
    /**
     * @param array $links
     *
     * @return array
     */
    public function addGoProActionLink($links)
    {
        $allPlugins = get_plugins();

        // If the Pro version is not installed (active or not), show the upgrade link
        if ( ! array_key_exists('wp-asset-clean-up-pro/wpacu.php', $allPlugins) ) {
            $links['go_pro'] = '<a target="_blank" style="font-weight: bold;" href="' . apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL) . '">' . __('Go Pro', 'wp-asset-clean-up') . '</a>';
        }

        return $links;
    }

    /**
     * @param string $text
     *
     * @return string
     */
    public static function adminFooterText($text)
    {
        if (Menu::isPluginPage()) {
            $text = sprintf(__('Thank you for using %s', 'wp-asset-clean-up'), WPACU_PLUGIN_TITLE.' v'.WPACU_PLUGIN_VERSION)
                    . ' <span class="dashicons dashicons-smiley"></span> &nbsp;&nbsp;';

            $text .= sprintf(
                __('If you like it, please %s<strong>rate</strong> %s%s %s on WordPress.org to help me spread the word to the community.', 'wp-asset-clean-up'),
                '<a target="_blank" href="'.Plugin::RATE_URL.'">',
                WPACU_PLUGIN_TITLE,
                '</a>',
                '<a target="_blank" href="'.Plugin::RATE_URL.'"><span class="dashicons dashicons-wpacu dashicons-star-filled"></span><span class="dashicons dashicons-wpacu dashicons-star-filled"></span><span class="dashicons dashicons-wpacu dashicons-star-filled"></span><span class="dashicons dashicons-wpacu dashicons-star-filled"></span><span class="dashicons dashicons-wpacu dashicons-star-filled"></span></a>'
            );
        }

        return $text;
    }
}
