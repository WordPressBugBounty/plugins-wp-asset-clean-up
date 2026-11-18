<?php
namespace WpAssetCleanUpLite\Admin;

use WpAssetCleanUp\AssetsManager;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\Menu;
use WpAssetCleanUpLite\ArchiveRules;

/**
 * Native WordPress category/tag editor integration for Lite.
 */
class ArchiveEditor
{
    /**
     * @return void
     */
    public static function registerHooks()
    {
        add_action('current_screen', array(__CLASS__, 'currentScreen'));
        add_action('edited_term', array(ArchiveRules::class, 'saveEditor'), 10, 3);
        add_filter('wpacu_internal_own_assets_load_script_core_js_admin', array(__CLASS__, 'loadAssets'), 10, 2);
        add_filter('wpacu_internal_own_assets_fetch_url_dashboard_view', array(__CLASS__, 'fetchUrl'));
    }

    /**
     * @return bool
     */
    public static function enabled()
    {
        return ! empty(Main::instance()->settings['show_assets_meta_box'])
            && ! empty(Main::instance()->settings['dashboard_show'])
            && Menu::userCanAccessPlugin() && AssetsManager::currentUserCanViewAssetsList();
    }

    /**
     * @return \WP_Term|false
     */
    public static function currentTerm()
    {
        global $pagenow;

        if ( ! is_admin() || $pagenow !== 'term.php' || ! self::enabled() ) {
            return false;
        }

        $term = ArchiveRules::term(
            isset($_GET['tag_ID']) ? $_GET['tag_ID'] : null,
            isset($_GET['taxonomy']) ? $_GET['taxonomy'] : null
        );

        return $term && current_user_can('edit_term', $term->term_id) ? $term : false;
    }

    /**
     * @param bool $load
     * @param bool $enabledOnEdit
     *
     * @return bool
     */
    public static function loadAssets($load, $enabledOnEdit)
    {
        return $load || ($enabledOnEdit && self::currentTerm());
    }

    /**
     * @param string $url
     *
     * @return string
     */
    public static function fetchUrl($url)
    {
        $term = self::currentTerm();

        if ( ! $term ) {
            return $url;
        }

        $termUrl = get_term_link($term, $term->taxonomy);

        return is_wp_error($termUrl) ? $url : $termUrl;
    }

    /**
     * @return void
     */
    public static function currentScreen()
    {
        $screen = get_current_screen();
        $term   = self::currentTerm();

        if ( ! $screen || $screen->base !== 'term' || ! $term ) {
            return;
        }

        add_action($term->taxonomy . '_edit_form_fields', array(__CLASS__, 'render'));
        add_action('admin_head', array(__CLASS__, 'editorStyle'));
    }

    /**
     * @return void
     */
    public static function editorStyle()
    {
        echo '<style data-wpacu-admin-inline-css="1">body.term-php #edittag {max-width:96%;} '
            . '#edittag .wpacu-archive-editor > td {min-width:0;} '
            . '@media (max-width:782px) {body.term-php #edittag {max-width:100%;}}</style>';
    }

    /**
     * @param \WP_Term $term
     *
     * @return void
     */
    public static function render($term)
    {
        if ( ! self::enabled() || ! ArchiveRules::term($term->term_id, $term->taxonomy)
            || ! current_user_can('edit_term', $term->term_id) ) {
            return;
        }

        $url = get_term_link($term, $term->taxonomy);

        if (is_wp_error($url)) {
            return;
        }

        echo '<tr class="form-field wpacu-archive-editor"><th scope="row">'
            . esc_html(WPACU_PLUGIN_TITLE . ': CSS & JavaScript Manager')
            . '</th><td data-wpacu-taxonomy="' . esc_attr($term->taxonomy) . '">';

        if (assetCleanUpHasNoLoadMatches($url)) {
            echo '<p>' . esc_html__('Asset CleanUp is excluded from this archive by your page exclusion settings. Remove the matching exclusion to manage its CSS/JS here.', 'wp-asset-clean-up') . '</p>';
        } else {
            wp_nonce_field('wpacu_archive_editor_' . $term->taxonomy . '_' . $term->term_id, 'wpacu_archive_editor_nonce', false);

            $data = array(
                'is_list_fetchable'    => true,
                'fetch_assets_on_click' => Main::instance()->settings['assets_list_show_status'] === 'fetch_on_click',
                'dom_get_type'         => Main::instance()->settings['dom_get_type'],
                'fetch_url'            => esc_url($url),
                'status'               => 1
            );

            include WPACU_PLUGIN_DIR . '/templates/meta-box.php';
        }

        echo '</td></tr>';
    }
}
