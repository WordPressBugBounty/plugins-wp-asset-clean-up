<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Misc;

/**
 * Class MiscAdmin
 * contains various common functions that are used by the plugin
 * @package WpAssetCleanUp
 */
class MiscAdmin
{
    /**
     * @var array
     */
    public $activeCachePlugins = array();

    /**
     * @var array
     */
    public static $potentialCachePlugins = array(
        'breeze/breeze.php', // Breeze WordPress Cache Plugin
        'cache-enabler/cache-enabler.php', // Cache Enabler
        'cachify/cachify.php', // Cachify
        'comet-cache/comet-cache.php', // Comet Cache
        'hyper-cache/plugin.php', // Hyper Cache
        'litespeed-cache/litespeed-cache.php', // LiteSpeed Cache
        'simple-cache/simple-cache.php', // Simple Cache
        'swift-performance-lite/performance.php', // Swift Performance Lite
        'w3-total-cache/w3-total-cache.php', // W3 Total Cache
        'wp-fastest-cache/wpFastestCache.php', // WP Fastest Cache
        'wp-rocket/wp-rocket.php', // WP Rocket
        'wp-super-cache/wp-cache.php' // WP Super Cache
    );

    /**
     *
     */
    public function getActiveCachePlugins()
    {
        if (empty($this->activeCachePlugins)) {
            $activePlugins = Misc::getActivePlugins();

            foreach ( self::$potentialCachePlugins as $cachePlugin ) {
                if ( in_array( $cachePlugin, $activePlugins ) ) {
                    $this->activeCachePlugins[] = $cachePlugin;
                }
            }
        }

        return $this->activeCachePlugins;
    }

    /**
     * @return array
     */
    public static function getAllActivePluginsIcons()
    {
        $popularPluginsIcons = array(
            'all-in-one-wp-migration-s3-extension' => WPACU_PLUGIN_URL . '/assets/icons/premium-plugins/all-in-one-wp-migration-s3-extension.png',
            'elementor'     => WPACU_PLUGIN_URL . '/assets/icons/premium-plugins/elementor.svg',
            'elementor-pro' => WPACU_PLUGIN_URL . '/assets/icons/premium-plugins/elementor-pro.jpg',
            'oxygen'        => WPACU_PLUGIN_URL . '/assets/icons/premium-plugins/oxygen.png',
            'gravityforms'  => WPACU_PLUGIN_URL . '/assets/icons/premium-plugins/gravityforms-blue.svg',
            'revslider'     => WPACU_PLUGIN_URL . '/assets/icons/premium-plugins/revslider.png',
            'LayerSlider'   => WPACU_PLUGIN_URL . '/assets/icons/premium-plugins/LayerSlider.jpg',
            'wpdatatables'  => WPACU_PLUGIN_URL . '/assets/icons/premium-plugins/wpdatatables.jpg',
            'monarch'       => WPACU_PLUGIN_URL . '/assets/icons/premium-plugins/monarch.jpg',
            'wp-rocket'     => WPACU_PLUGIN_URL . '/assets/icons/premium-plugins/wp-rocket.png'
        );

        $allActivePluginsIcons = self::getCachedActiveFreePluginsIcons();

        if ( ! is_array($allActivePluginsIcons) ) {
            $allActivePluginsIcons = array();
        }

        foreach (Misc::getActivePlugins() as $activePlugin) {
            if (strpos($activePlugin, '/') !== false) {
                list ($pluginSlug) = explode('/', $activePlugin);

                if (! array_key_exists($pluginSlug, $allActivePluginsIcons) && array_key_exists($pluginSlug, $popularPluginsIcons)) {
                    $allActivePluginsIcons[$pluginSlug] = $popularPluginsIcons[$pluginSlug];
                }
            }
        }

        return $allActivePluginsIcons;
    }

    /**
     * @return array
     */
    public static function getCachedActiveFreePluginsIcons()
    {
        $activePluginsIconsJson = get_transient(WPACU_PLUGIN_ID . '_active_plugins_icons' );

        if ( $activePluginsIconsJson ) {
            $activePluginsIcons = @json_decode( $activePluginsIconsJson, ARRAY_A );

            if ( ! empty( $activePluginsIcons ) && is_array( $activePluginsIcons ) ) {
                return $activePluginsIcons;
            }
        }

        return array(); // default
    }

    /**
     * @return array|bool|mixed|object
     */
    public static function fetchActiveFreePluginsIconsFromWordPressOrg()
    {
        $allActivePlugins = Misc::getActivePlugins();

        if (empty($allActivePlugins)) {
            return array();
        }

        foreach ($allActivePlugins as $activePlugin) {
            if (! is_string($activePlugin) || strpos($activePlugin, '/') === false) {
                continue;
            }

            list($pluginSlug) = explode('/', $activePlugin);
            $pluginSlug = trim($pluginSlug);

            if (! $pluginSlug) {
                continue;
            }

            // Avoid the calls to WordPress.org as much as possible
            // as it would decrease the resources and timing to fetch the data we need

            // not relevant to check Asset CleanUp's plugin info in this case
            if (in_array($pluginSlug, array('wp-asset-clean-up', 'wp-asset-clean-up-pro'))) {
                continue;
            }

            // no readme.txt file in the plugin's root folder? skip it
            if (! is_file(WP_PLUGIN_DIR.'/'.$pluginSlug.'/readme.txt')) {
                continue;
            }

            $payload = array(
                'action'  => 'plugin_information',
                'request' => serialize( (object) array(
                    'slug'   => $pluginSlug,
                    'fields' => array(
                        'tags'          => false,
                        'icons'         => true, // that's what will get fetched
                        'sections'      => false,
                        'description'   => false,
                        'tested'        => false,
                        'requires'      => false,
                        'rating'        => false,
                        'downloaded'    => false,
                        'downloadlink'  => false,
                        'last_updated'  => false,
                        'homepage'      => false,
                        'compatibility' => false,
                        'ratings'       => false,
                        'added'         => false,
                        'donate_link'   => false
                    ),
                ) ),
            );

            $body = @wp_remote_post('http://api.wordpress.org/plugins/info/1.0/', array('body' => $payload));

            if (is_wp_error($body) || (! (isset($body['body']) && is_serialized($body['body'])))) {
                continue;
            }

            $pluginInfo = @unserialize($body['body']);

            if (! isset($pluginInfo->name, $pluginInfo->icons)) {
                continue;
            }

            if (empty($pluginInfo->icons)) {
                continue;
            }

            $pluginIcon = array_shift($pluginInfo->icons);

            if ($pluginIcon !== '') {
                $activePluginsIcons[$pluginSlug] = $pluginIcon;
            }
        }

        if (empty($activePluginsIcons)) {
            return array();
        }

        $expiresInSeconds = 604800; // one week

        set_transient(WPACU_PLUGIN_ID . '_active_plugins_icons', wp_json_encode($activePluginsIcons), $expiresInSeconds);

        return $activePluginsIcons;
    }

    /**
     * @param $handleData
     *
     * @return bool
     */
    public static function isCoreFile($handleData)
    {
        $handleData = (object)$handleData;

        $part = str_replace(
            array(
                'http://',
                'https://',
                '//'
            ),
            '',
            $handleData->src
        );

        $parts     = explode('/', $part);
        $parentDir = isset($parts[1]) ? $parts[1] : '';

        // Loaded from WordPress directories (Core)
        return in_array( $parentDir, array( 'wp-includes', 'wp-admin' ) ) || strpos( $handleData->src,
                '/'.Misc::getPluginsDir('dir_name').'/jquery-updater/js/jquery-' ) !== false;
    }

    /**
     * @param $type
     * e.g. 'per_page' will fetch only per page rules, excluding the bulk ones
     * such as unload everywhere, on this post type etc.
     *
     * @return int
     */
    public static function getTotalUnloadedAssets($type = 'all')
    {
        if ($unloadedTotalAssets = get_transient(WPACU_PLUGIN_ID. '_total_unloaded_assets_'.$type)) {
            return $unloadedTotalAssets;
        }

        global $wpdb;

        $frontPageNoLoad      = get_option(WPACU_PLUGIN_ID . '_front_page_no_load');
        $frontPageNoLoadArray = json_decode($frontPageNoLoad, ARRAY_A);

        $unloadedTotalAssets = 0;

        // Home Page: Unloads
        if (isset($frontPageNoLoadArray['styles'])) {
            $unloadedTotalAssets += count($frontPageNoLoadArray['styles']);
        }

        if (isset($frontPageNoLoadArray['scripts'])) {
            $unloadedTotalAssets += count($frontPageNoLoadArray['scripts']);
        }

        // Posts, Pages, Custom Post Types: Individual Page Unloads
        $sqlPart = '_' . WPACU_PLUGIN_ID . '_no_load';
        $sqlQuery = <<<SQL
SELECT pm.meta_value FROM `{$wpdb->prefix}postmeta` pm
LEFT JOIN `{$wpdb->prefix}posts` p ON (p.ID = pm.post_id)
WHERE (p.post_status='publish' OR p.post_status='private') AND pm.meta_key='{$sqlPart}'
SQL;

        $sqlResults = $wpdb->get_results($sqlQuery, ARRAY_A);

        if (! empty($sqlResults)) {
            foreach ($sqlResults as $row) {
                $metaValue    = $row['meta_value'];
                $unloadedList = @json_decode($metaValue, ARRAY_A);

                if (empty($unloadedList)) {
                    continue;
                }

                foreach ($unloadedList as $assets) {
                    if (! empty($assets)) {
                        $unloadedTotalAssets += count($assets);
                    }
                }
            }
        }

        if ($type === 'all') {
            $unloadedTotalAssets += self::getTotalBulkUnloadsFor( 'all' );
        }

        // To avoid the complex SQL query next time
        set_transient(WPACU_PLUGIN_ID. '_total_unloaded_assets_'.$type, $unloadedTotalAssets, 28800);

        return $unloadedTotalAssets;
    }

    /**
     * @param string $for
     *
     * @return int
     */
    public static function getTotalBulkUnloadsFor($for)
    {
        $unloadedTotalAssets = 0;

        if (in_array($for, array('everywhere', 'all'))) {
            // Everywhere (Site-wide) unloads
            $globalUnloadListJson = get_option(WPACU_PLUGIN_ID . '_global_unload');
            $globalUnloadArray    = @json_decode($globalUnloadListJson, ARRAY_A);

            foreach (array('styles', 'scripts') as $assetType) {
                if ( ! empty( $globalUnloadArray[$assetType] ) ) {
                    $unloadedTotalAssets += count( $globalUnloadArray[$assetType] );
                }
            }
        }

        if (in_array($for, array('bulk', 'all'))) {
            // Any bulk unloads? e.g. unload specific CSS/JS on all pages of a specific post type
            $bulkUnloadListJson = get_option(WPACU_PLUGIN_ID . '_bulk_unload');
            $bulkUnloadArray  = @json_decode($bulkUnloadListJson, ARRAY_A);

            $bulkUnloadedAllTypes = array('search', 'date', '404', 'taxonomy', 'post_type', 'author');
            foreach (array('styles', 'scripts') as $assetType) {
                if ( isset( $bulkUnloadArray[ $assetType ] ) ) {
                    foreach ( array_keys( $bulkUnloadArray[ $assetType ] ) as $dataType ) {
                        if ( strpos( $dataType, 'custom_post_type_archive_' ) !== false ) {
                            $bulkUnloadedAllTypes[] = $dataType;
                        }
                    }
                }
            }

            foreach ( $bulkUnloadedAllTypes as $bulkUnloadedType ) {
                if (in_array($bulkUnloadedType, array('search', 'date', '404')) || (strpos($bulkUnloadedType, 'custom_post_type_archive_') !== false)) {
                    foreach (array('styles', 'scripts') as $assetType) {
                        if ( ! empty( $bulkUnloadArray[$assetType][ $bulkUnloadedType ] ) ) {
                            $unloadedTotalAssets += count( $bulkUnloadArray[$assetType][ $bulkUnloadedType ] );
                        }
                    }
                } elseif ($bulkUnloadedType === 'author') {
                    foreach (array('styles', 'scripts') as $assetType) {
                        if ( ! empty( $bulkUnloadArray[$assetType][ $bulkUnloadedType ]['all']) )  {
                            $unloadedTotalAssets += count( $bulkUnloadArray[$assetType][ $bulkUnloadedType ]['all'] );
                        }
                    }
                } elseif (in_array($bulkUnloadedType, array('post_type', 'taxonomy'))) {
                    foreach (array('styles', 'scripts') as $assetType) {
                        if ( ! empty( $bulkUnloadArray[$assetType][ $bulkUnloadedType ] ) ) {
                            foreach ( $bulkUnloadArray[$assetType][ $bulkUnloadedType ] as $objectValues ) {
                                $unloadedTotalAssets += count( $objectValues );
                            }
                        }
                    }
                }
            }
        }

        return $unloadedTotalAssets;
    }

    /**
     * @param $src
     *
     * @return bool|array
     */
    public static function maybeIsInactiveAsset($src)
    {
        $pluginsDirRel = Misc::getPluginsDir();

        $srcAlt = $src;

        if (strncmp($srcAlt, '//', 2) === 0 ) {
            $srcAlt = str_replace( str_replace( array( 'http://', 'https://' ), '//', site_url() ), '', $srcAlt );
        }

        $relSrc = str_replace( site_url(), '', $srcAlt );

        /*
         * [START] plugin path
         */
        if (strpos($src, $pluginsDirRel) !== false) {
            // Quickest way
            preg_match_all( '#/' . $pluginsDirRel . '/(.*?)/#', $src, $matches, PREG_PATTERN_ORDER );

            if ( isset( $matches[1][0] ) && $matches[1][0] ) {
                $pluginDirName = $matches[1][0];

                $activePlugins    = Misc::getActivePlugins();
                $activePluginsStr = implode( ',', $activePlugins );

                if ( strpos( $activePluginsStr, $pluginDirName . '/' ) === false ) {
                    return array(
                        'from' => 'plugin',
                        'name' => $pluginDirName
                    ); // it belongs to an inactive plugin
                }
            }

            $relPluginsUrl = str_replace( site_url(), '', plugins_url() );

            if ( strpos( $relSrc, '/' . $pluginsDirRel ) !== false ) {
                list ( , $relSrc ) = explode( '/' . $pluginsDirRel, $relSrc );
            }

            if ( strpos( $relSrc, $relPluginsUrl ) !== false ) {
                // Determine the plugin behind the $src
                $relSrc = trim( str_replace( $relPluginsUrl, '', $relSrc ), '/' );

                if ( strpos( $relSrc, '/' ) !== false ) {
                    list ( $pluginDirName, ) = explode( '/', $relSrc );

                    $activePlugins    = Misc::getActivePlugins();
                    $activePluginsStr = implode( ',', $activePlugins );

                    if ( strpos( $activePluginsStr, $pluginDirName . '/' ) === false ) {
                        return array(
                            'from' => 'plugin',
                            'name' => $pluginDirName
                        ); // it belongs to an inactive plugin
                    }
                }
            }
        }
        /*
         * [END] plugin path
         */

        /*
         * [START] theme path
         */
        $themesDirRel = Misc::getThemesDirRel();

        if (strpos($relSrc, $themesDirRel) !== false) {
            if ( strpos( $relSrc, $themesDirRel ) !== false ) {
                list ( , $relSrc ) = explode( $themesDirRel, $relSrc );
            }

            if ( strpos( $relSrc, '/' ) !== false ) {
                list ( $themeDirName, ) = explode( '/', $relSrc );
            }

            if (isset($themeDirName)) {
                $activeThemes = self::getActiveThemes();

                if ( ! empty( $activeThemes ) && ! in_array($themeDirName, $activeThemes) ) {
                    return array(
                        'from' => 'theme',
                        'name' => $themeDirName
                    );
                }
            }
        }
        /*
         * [END] theme path
         */

        return false;
    }

    /**
     * @param $themeName
     *
     * @return array|string
     */
    public static function getThemeIcon($themeName)
    {
        $themesIconsPathToDir = WPACU_PLUGIN_DIR.'/assets/icons/themes/';
        $themesIconsUrlDir    = WPACU_PLUGIN_URL.'/assets/icons/themes/';

        if (! is_dir($themesIconsPathToDir)) {
            return array();
        }

        $themeName = strtolower($themeName);

        $themesIcons = scandir($themesIconsPathToDir);

        foreach ($themesIcons as $themesIcon) {
            if (strpos($themesIcon, $themeName.'.') !== false) {
                return $themesIconsUrlDir . $themesIcon;
            }
        }

        return '';
    }

    /**
     * @return array
     */
    public static function getActiveThemes()
    {
        $activeThemes     = array();
        $currentThemeSlug = get_stylesheet();

        if ( current_user_can( 'switch_themes' ) ) {
            $themes = wp_get_themes( array( 'allowed' => true ) );
        } else {
            $themes = array( wp_get_theme() );
        }

        foreach ( $themes as $theme ) {
            $themeSlug = $theme->get_stylesheet();

            if ( $themeSlug === $currentThemeSlug ) {
                // Make sure both the parent and the child theme are in the list of active themes
                // in case there are references from
                $activeThemes[] = $currentThemeSlug;

                $childEndsWith = '-child';
                if ( Misc::endsWith( $currentThemeSlug, $childEndsWith ) ) {
                    $activeThemes[] = substr( $currentThemeSlug, 0, - strlen( $childEndsWith ) );
                } else {
                    $activeThemes[] = $currentThemeSlug . $childEndsWith;
                }
            }
        }

        return $activeThemes;
    }

    /**
     * Adapted from: https://stackoverflow.com/questions/2510434/format-bytes-to-kilobytes-megabytes-gigabytes
     *
     * @param $size
     * @param int $precision
     * @param string $getItIn
     * @param bool $includeHtmlTags
     *
     * @return string
     */
    public static function formatBytes($size, $precision = 2, $getItIn = '', $includeHtmlTags = true)
    {
        if ((int)$size === 0) {
            return (($includeHtmlTags) ? '<span style="vertical-align: middle;" class="dashicons dashicons-warning"></span> ' : '') .
                   __('The file appears to be empty', 'wp-asset-clean-up');
        }

        // In case a string is passed, make it to float
        $size = (float)$size;

        // Just for internal usage (no printing in nice format)
        if ($getItIn === 'bytes') {
            return $size;
        }

        if ($getItIn === 'KB') {
            return round(($size / 1024), $precision);
        }

        if ($getItIn === 'MB') {
            return round((($size / 1024) / 1024), $precision);
        }

        $base = log($size, 1024);

        $suffixes = array('bytes', 'KB', 'MB');

        $floorBase = floor($base);

        if ($floorBase > 2) {
            $floorBase = 2;
        }

        $result = round(
        // 1024 ** ($base - $floorBase) is available only from PHP 5.6+
            pow(1024, ($base - $floorBase)),
            $precision
        );

        $resultForPrint = $result;

        if ($includeHtmlTags && $suffixes[$floorBase] === 'KB' && $floorBase !== 1) {
            $resultForPrint = str_replace('.', '<span style="font-size: 80%; font-weight: 200;">.', $result).'</span>';
        }

        $output = $resultForPrint.' '. $suffixes[$floorBase];

        // If KB, also show the MB equivalent
        if ($floorBase === 1) {
            $output .= ' ('.number_format($result / 1024, 4).' MB)';
        }

        return wp_kses($output, array('span' => array('style' => array(), 'class' => array())));
    }

    /**
     * @param $postTypes
     *
     * @return mixed
     */
    public static function filterPostTypesList($postTypes)
    {
        foreach ($postTypes as $postTypeKey => $postTypeValue) {
            // Exclude irrelevant custom post types
            if (in_array($postTypeKey, MetaBoxes::$noMetaBoxesForPostTypes)) {
                unset($postTypes[$postTypeKey]);
            }

            // Polish existing values
            if ($postTypeKey === 'product' && wpacuIsPluginActive('woocommerce/woocommerce.php')) {
                $postTypes[$postTypeKey] = 'product &#10230; WooCommerce';
            }

            if ($postTypeKey === 'download' && wpacuIsPluginActive('easy-digital-downloads/easy-digital-downloads.php')) {
                $postTypes[$postTypeKey] = 'download &#10230; Easy Digital Downloads';
            }
        }

        return $postTypes;
    }

    /**
     * @return mixed
     */
    public static function getCustomPostTypesList()
    {
        $postTypes = get_post_types(array('public' => true, '_builtin' => false, 'rewrite' => true));
        return self::filterCustomPostTypesList($postTypes);
    }

    /**
     * Note: If plugins are disabled via "Plugins Manager" -> "IN THE DASHBOARD /wp-admin/"
     * where the target pages require this function, the list could be incomplete if those plugins registered custom post types
     *
     * @param $postTypes
     *
     * @return mixed
     */
    public static function filterCustomPostTypesList($postTypes)
    {
        foreach (array_keys($postTypes) as $postTypeKey) {
            if (in_array($postTypeKey, array('post', 'page', 'attachment'))) {
                unset($postTypes[$postTypeKey]); // no default post types
            }

            // Polish existing values
            if ($postTypeKey === 'product' && wpacuIsPluginActive('woocommerce/woocommerce.php')) {
                $postTypes[$postTypeKey] = 'product &#10230; WooCommerce';
            }

            if ($postTypeKey === 'download' && wpacuIsPluginActive('easy-digital-downloads/easy-digital-downloads.php')) {
                $postTypes[$postTypeKey] = 'download &#10230; Easy Digital Downloads';
            }
        }

        return $postTypes;
    }

    /**
     * @return mixed
     */
    public static function getCustomTaxonomyList()
    {
        $taxonomyList = get_taxonomies(array('public' => true, 'rewrite' => true, '_builtin' => false));
        return self::filterCustomTaxonomyList($taxonomyList);
    }

    /**
     * @param $postTypes
     *
     * @return mixed
     */
    public static function filterCustomTaxonomyList($taxonomyList)
    {
        foreach (array_keys($taxonomyList) as $taxonomy) {
            if (in_array($taxonomy, array('category', 'post_tag', 'post_format'))) {
                unset($taxonomyList[$taxonomy]); // no default post types
            }

            // Polish existing values
            if ($taxonomy === 'product_cat' && wpacuIsPluginActive('woocommerce/woocommerce.php')) {
                $taxonomyList[$taxonomy] = 'product_cat &#10230; Product\'s Category in WooCommerce';
            }
        }

        return $taxonomyList;
    }

    /**
     * @param $content
     *
     * @return array|string|string[]|null
     */
    public static function stripIrrelevantHtmlTags($content)
    {
        return preg_replace( '@<(script|style|iframe)[^>]*?>.*?</\\1>@si', '', $content );
    }

    /**
     * @param $value
     *
     * @return string
     */
    public static function sanitizeValueForHtmlAttr($value)
    {
        // Keep a standard that is used for specific HTML attributes such as "id" and "for"
        $value = str_replace(array('-', '/', '.'), array('_', '_', '_'), $value);

        return esc_attr(sanitize_title_for_query($value));
    }
}
