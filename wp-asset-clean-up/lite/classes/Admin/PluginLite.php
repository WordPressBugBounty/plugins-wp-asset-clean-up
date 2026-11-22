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
     * Use WordPress's successful single-plugin activation response to open Getting Started.
     * No pending redirect is left behind for another request or administrator.
     *
     * @param bool $useDefaultRedirect
     * @param bool $networkWide
     * @param bool $firstUsageMissing
     *
     * @return bool
     */
    public static function prepareGettingStartedOnActivation($useDefaultRedirect, $networkWide, $firstUsageMissing)
    {
        if ($firstUsageMissing && ! $networkWide && self::canOpenGettingStarted()) {
            add_filter('wp_redirect', array(__CLASS__, 'redirectSingleActivationToGettingStarted'));
        }

        return false;
    }

    /**
     * @param string $location
     *
     * @return string
     */
    public static function redirectSingleActivationToGettingStarted($location)
    {
        if (strpos($location, admin_url('plugins.php?')) !== 0) {
            return $location;
        }

        wp_parse_str((string) wp_parse_url($location, PHP_URL_QUERY), $query);
        if (isset($query['activate']) && $query['activate'] === 'true' && ! isset($query['error'])) {
            remove_filter('wp_redirect', array(__CLASS__, 'redirectSingleActivationToGettingStarted'));
            CourseOptIn::offerOnFirstActivation();
            return admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_getting_started');
        }

        return $location;
    }

    /**
     * Record a new first-use date after a full reset, including while Lite remains active.
     *
     * @param string $redirectUrl
     * @param string $choice
     * @param array $result
     *
     * @return string
     */
    public static function gettingStartedAfterReset($redirectUrl, $choice, $result)
    {
        if ($choice !== 'reset_everything' || get_option(WPACU_PLUGIN_ID . '_first_usage', null) !== null) {
            return $redirectUrl;
        }

        Plugin::triggerFirstUsage();
        if (empty($result['errors']) && self::canOpenGettingStarted()) {
            return admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_getting_started&wpacu_reset_done=1');
        }

        return $redirectUrl;
    }

    /**
     * @return bool
     */
    private static function canOpenGettingStarted()
    {
        return is_admin() && ! is_network_admin() && ! wp_doing_ajax() && ! (defined('WP_CLI') && WP_CLI)
            && defined('WPACU_LITE_PLUGIN_VERSION') && ! defined('WPACU_PRO_PLUGIN_VERSION')
            && is_user_logged_in() && current_user_can('manage_options') && Menu::userCanAccessPlugin();
    }

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
            $links['go_pro'] = '<a target="_blank" style="font-weight: bold;" href="' . apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL) . '">' . esc_html(apply_filters('wpacu_internal_upgrade_label', __('Go Pro', 'wp-asset-clean-up'))) . '</a>';
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
