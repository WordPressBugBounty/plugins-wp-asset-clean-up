<?php
namespace WpAssetCleanUpLite;

use WpAssetCleanUp\Menu;

class MenuLite
{
    /**
     * The Lite edition does not need a license page. Keep the old URL working
     * by sending anyone who bookmarked it to the relevant Help section.
     *
     * @return void
     */
    public static function maybeRedirectLicensePage()
    {
        $currentPage = isset($_GET['page']) && is_string($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';

        if ($currentPage !== WPACU_PLUGIN_ID . '_license' || ! Menu::userCanAccessPlugin()) {
            return;
        }

        $helpUrl = add_query_arg(
            array(
                'page'                  => WPACU_PLUGIN_ID . '_get_help',
                'wpacu_redirected_from' => 'license'
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($helpUrl . '#wpacu-switch-to-pro');
        exit();
    }

    /**
     * @return void
     */
    public static function maybeRedirectGoProPage()
    {
        if ( ! (isset($_GET['page']) && $_GET['page'] === WPACU_PLUGIN_ID . '_go_pro') ) {
            return;
        }

        wp_redirect(apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=plugin_go_pro'));
        exit();
    }

    /**
     * @param array $menuPages
     *
     * @return array
     */
    public static function filterAllMenuPages($menuPages)
    {
        $menuPages[] = WPACU_PLUGIN_ID . '_go_pro';

        return array_values(array_unique($menuPages));
    }

    /**
     * @param array $topAreaLinks
     *
     * @return array
     */
    public static function filterTopAreaLinks($topAreaLinks)
    {
        unset($topAreaLinks['admin.php?page=' . WPACU_PLUGIN_ID . '_license']);

        return $topAreaLinks;
    }

    /**
     * Keep the page registered until WordPress completes its admin-page access
     * check, then remove only the visible sidebar entry.
     *
     * @param string $parentSlug
     *
     * @return void
     */
    public static function removeLicenseSubmenuPage($parentSlug)
    {
        add_action('admin_init', static function() use ($parentSlug) {
            remove_submenu_page($parentSlug, WPACU_PLUGIN_ID . '_license');
        }, 20);
    }

    /**
     * @param string $parentSlug
     *
     * @return void
     */
    public static function addGoProSubmenuPage($parentSlug)
    {
        add_submenu_page(
            $parentSlug,
            __('Go Pro', 'wp-asset-clean-up'),
            __('Go Pro', 'wp-asset-clean-up') . ' <span style="font-size: 16px; color: inherit;" class="dashicons dashicons-star-filled"></span>',
            Menu::getAccessCapability(),
            WPACU_PLUGIN_ID . '_go_pro',
            function() {}
        );
    }

}
