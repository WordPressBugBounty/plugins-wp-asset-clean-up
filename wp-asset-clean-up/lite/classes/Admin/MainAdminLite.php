<?php
namespace WpAssetCleanUpLite\Admin;

use WpAssetCleanUp\Admin\AssetsManagerAdmin;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\Misc;

/**
 * Class MainAdminLite
 *
 * Lite-only admin functionality that is intentionally kept outside the common
 * WpAssetCleanUp\Admin\MainAdmin class.
 *
 * @package WpAssetCleanUpLite
 */
class MainAdminLite
{
    /**
     * @param bool   $shouldStop
     * @param bool   $isFrontEndEditView
     * @param string $wpacuTimingName
     * @param object $mainAdmin
     *
     * @return bool
     */
    public static function filterShouldStopFrontendEditViewOutput($shouldStop, $isFrontEndEditView, $wpacuTimingName, $mainAdmin)
    {
        if ($shouldStop) {
            return true;
        }

        if ($isFrontEndEditView && ! Main::instance()->isUpdateable) {
            $data = array(
                'lite_template' => true
            );

            $mainAdmin->parseTemplate('settings-frontend-locked', $data, true);

            /* [wpacu_timing] */ Misc::scriptExecTimer($wpacuTimingName, 'end'); /* [/wpacu_timing] */

            return true;
        }

        return false;
    }

    /**
     * @param string $pathToTemplateFile
     * @param string $name
     * @param array  $data
     *
     * @return string
     */
    public static function filterTemplateFilePath($pathToTemplateFile, $name, $data)
    {
        if (isset($data['lite_template']) && $data['lite_template']) {
            return WPACU_PLUGIN_DIR . '/lite/templates/' . $name . '.php';
        }

        return $pathToTemplateFile;
    }

    /**
     * Resolve the real archive-like URL in Lite's Dashboard preview.
     * Without this bridge the common URL resolver falls back to the homepage,
     * because the original archive URL extension point was implemented only
     * by the Pro edition.
     *
     * @param string $pageUrl
     * @param int    $postId
     *
     * @return string
     */
    public static function filterAdminAreaPageUrl($pageUrl, $postId)
    {
        if ($pageUrl || (int)$postId > 0) {
            return $pageUrl;
        }

        if (Misc::getVar('get', 'page') !== WPACU_PLUGIN_ID . '_assets_manager') {
            return $pageUrl;
        }

        $wpacuFor = sanitize_key(Misc::getVar('get', 'wpacu_for', ''));

        if ($wpacuFor === '') {
            return $pageUrl;
        }

        $archiveData = AssetsManagerAdmin::getArchivePageDataFromRequest($wpacuFor);

        if (empty($archiveData['is_valid'])) {
            return $pageUrl;
        }

        if ( ! empty($archiveData['fetch_url'])) {
            return $archiveData['fetch_url'];
        }

        return ! empty($archiveData['url']) ? $archiveData['url'] : $pageUrl;
    }

    /**
     * Add the selected archive context to the localized JavaScript object.
     * Hidden form fields remain as a fallback, but the main object should
     * still describe the page being previewed accurately.
     *
     * @param array $objectData
     *
     * @return array
     */
    public static function filterObjectDataForArchivePreview($objectData)
    {
        if ( ! is_array($objectData) || ! is_admin()) {
            return $objectData;
        }

        if (Misc::getVar('get', 'page') !== WPACU_PLUGIN_ID . '_assets_manager') {
            return $objectData;
        }

        $wpacuFor = sanitize_key(Misc::getVar('get', 'wpacu_for', ''));

        if ($wpacuFor === '') {
            return $objectData;
        }

        $archiveData = AssetsManagerAdmin::getArchivePageDataFromRequest($wpacuFor);

        if (empty($archiveData['is_valid']) || empty($archiveData['type'])) {
            return $objectData;
        }

        $objectData['page_type'] = $archiveData['type'];
        $objectData['force_manage_dash'] = true;
        $objectData['selected_context_is_homepage'] = false;

        if ( ! empty($archiveData['fetch_url'])) {
            $objectData['page_url'] = $archiveData['fetch_url'];
        } elseif ( ! empty($archiveData['url'])) {
            $objectData['page_url'] = $archiveData['url'];
        }

        if ($archiveData['type'] === 'taxonomy') {
            $objectData['tag_id'] = isset($archiveData['term_id']) ? (int)$archiveData['term_id'] : 0;
            $objectData['wpacu_taxonomy'] = isset($archiveData['taxonomy']) ? $archiveData['taxonomy'] : '';
            $objectData['tax_name'] = $objectData['wpacu_taxonomy'];
            $objectData['is_tax_page'] = true;
        } elseif ($archiveData['type'] === 'author') {
            $objectData['author_id'] = isset($archiveData['author_id']) ? (int)$archiveData['author_id'] : 0;
            $objectData['is_author_page'] = true;
        } elseif ($archiveData['type'] === 'search') {
            $objectData['is_search_page'] = true;
        } elseif ($archiveData['type'] === 'date') {
            $objectData['is_date_page'] = true;
        } elseif ($archiveData['type'] === '404') {
            $objectData['is_404_page'] = true;
        } elseif ($archiveData['type'] === 'custom_post_type_archive') {
            $objectData['is_archive_page'] = true;
            $objectData['archive_name'] = isset($archiveData['post_type']) ? $archiveData['post_type'] : '';
        }

        return $objectData;
    }

    /**
     * Force the dedicated Dashboard preview URL to resolve through the active
     * theme's 404 template. The URL is already intentionally non-existent, but
     * this mirrors Pro's safeguard against redirects or unusual routing rules.
     *
     * @return void
     */
    public static function maybeForce404ForDashboardAssetsFetch()
    {
        if ( ! Main::instance()->isGetAssetsCall || ! isset($_GET['wpacu_force_404_template']) ) {
            return;
        }

        global $wp_query;

        if ($wp_query instanceof \WP_Query) {
            $wp_query->set_404();
        }

        status_header(404);
        nocache_headers();
    }

    /**
     * Use the common non-singular asset collection path only while Lite is
     * fetching an archive-like page for the read-only Dashboard preview.
     * Normal front-end requests and every singular Lite feature are untouched.
     *
     * @param string|false $type
     * @param int          $postId
     *
     * @return string|false
     */
    public static function filterGetAssetsType($type, $postId)
    {
        if ($type || (int)$postId > 0 || empty($_REQUEST[WPACU_LOAD_ASSETS_REQ_KEY])) {
            return $type;
        }

        if (
            is_search()
            || is_author()
            || is_date()
            || is_404()
            || is_category()
            || is_tag()
            || is_tax()
            || is_post_type_archive()
        ) {
            return 'for_pro';
        }

        return $type;
    }

    /**
     * Allow the common Dashboard fetcher to build a real assets list for
     * archive-like contexts. The matching forms remain read-only in Lite.
     *
     * @param string $type
     *
     * @return string
     */
    public static function filterIsDashboardAjaxCallForSpecificPageType($type)
    {
        $pageType = sanitize_key(Misc::getVar('post', 'page_type', ''));

        if (in_array($pageType, array('custom_post_type_archive', 'search', 'author', 'date', '404'), true)) {
            return 'for_pro';
        }

        $taxonomy = sanitize_key(Misc::getVar('post', 'wpacu_taxonomy', ''));
        $tagId    = (int)Misc::getVar('post', 'tag_id', 0);

        if ($taxonomy !== '' && $tagId > 0) {
            return 'for_pro';
        }

        return $type;
    }

    /**
     * Mark archive AJAX output so Lite templates and the preview script can
     * remove every write-capable control after the real list is rendered.
     *
     * @param array $data
     *
     * @return array
     */
    public static function filterDataVarTemplate($data)
    {
        if ( ! is_array($data) ) {
            return $data;
        }

        $pageType = sanitize_key(Misc::getVar('post', 'page_type', ''));
        $taxonomy = sanitize_key(Misc::getVar('post', 'wpacu_taxonomy', ''));
        $tagId    = (int)Misc::getVar('post', 'tag_id', 0);

        if (
            in_array($pageType, array('custom_post_type_archive', 'search', 'author', 'date', '404'), true)
            || ($taxonomy !== '' && $tagId > 0)
        ) {
            $data['lite_pro_preview_mode'] = true;

            if ($taxonomy !== '') {
                $data['tax_name'] = $taxonomy;
            }
        }

        return $data;
    }

    /**
     * Preserve the taxonomy label in the real read-only archive preview.
     *
     * @param array $data
     *
     * @return array
     */
    public static function filterDataForNonSingularAssetManagement($data)
    {
        global $wp_query;

        if ( ! is_array($data) || ! is_object($wp_query) ) {
            return $data;
        }

        $object = $wp_query->get_queried_object();

        if (isset($object->taxonomy) && $object->taxonomy) {
            $data['tax_name'] = $object->taxonomy;
        }

        return $data;
    }

}
