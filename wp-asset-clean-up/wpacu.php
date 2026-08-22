<?php
/*
 * Plugin Name: Asset CleanUp: Page Speed Booster
 * Plugin URI: https://wordpress.org/plugins/wp-asset-clean-up/
 * Version: 1.4.0.5
 * Requires at least: 4.7
 * Requires PHP: 5.6
 * Description: Unload Chosen Scripts & Styles from Posts/Pages to reduce HTTP Requests, Combine/Minify CSS/JS files
 * Author: Gabe Livan
 * Author URI: https://www.gabelivan.com/
 * Text Domain: wp-asset-clean-up
*/

// Keep the Lite version available without claiming the shared runtime constant.
// On Multisite, a network-active Lite can load before a site-active Pro and
// must be allowed to become dormant without leaving its version behind.
if ( ! defined('WPACU_LITE_PLUGIN_VERSION') ) {
    define('WPACU_LITE_PLUGIN_VERSION', '1.4.0.5');
}


// Exit if accessed directly
if ( ! defined('ABSPATH') ) {
    exit;
}

// [wpacu_lite]
// Premium plugin version already exists, is it active?
// This action is valid starting from LITE version 1.2.6.8
// Since 1.0.3, the PRO version works independently (does not need anymore LITE to be active and act as a parent plugin)
// However, it's good to have both versions active for compatibility with plugins such as "WP Cloudflare Super Page Cache"

if ( ! defined('WPACU_PRO_PLUGIN_TO_CHECK') ) {
    define('WPACU_PRO_PLUGIN_TO_CHECK', 'wp-asset-clean-up-pro/wpacu.php');
}

if ( ! defined('WPACU_PRO_PLUGIN_TO_CHECK_BASE') ) {
    list($wpacuProPluginToCheckBase) = explode('/', WPACU_PRO_PLUGIN_TO_CHECK);

    define('WPACU_PRO_PLUGIN_TO_CHECK_BASE', $wpacuProPluginToCheckBase);
}

if ( ! function_exists('assetCleanUpIsProPluginBasename') ) {
    /**
     * Check whether the given plugin basename belongs to Asset CleanUp Pro.
     *
     * @param mixed $pluginTargetedBasename
     *
     * @return bool
     */
    function assetCleanUpIsProPluginBasename($pluginTargetedBasename)
    {
        if ( ! is_string($pluginTargetedBasename)) {
            return false;
        }

        $wpacuProPluginBase     = preg_quote(WPACU_PRO_PLUGIN_TO_CHECK_BASE, '#');
        $pluginTargetedBasename = wp_normalize_path($pluginTargetedBasename);

        return preg_match('#^'.$wpacuProPluginBase.'(?:[-_][a-z0-9._-]+)?/wpacu\.php$#i', $pluginTargetedBasename) === 1;
    }
}

if ( ! function_exists('assetCleanUpLiteShouldStayDormant') ) {
    /**
     * Determine whether Asset CleanUp Lite should stay dormant because Pro is active.
     * LITE parent plugin does not need to be triggered anymore if the Pro version is active (since 1.0.3)
     *
     * @return bool
     */
    function assetCleanUpLiteShouldStayDormant()
    {
        // Pro was loaded before Lite
        if (defined('WPACU_PRO_NO_LITE_NEEDED') && WPACU_PRO_NO_LITE_NEEDED !== false) {
            return true;
        }

        // Pro is active, even if it has not been loaded yet.
        $activePlugins = get_option('active_plugins', array());

        if (is_array($activePlugins)) {
            if (in_array(WPACU_PRO_PLUGIN_TO_CHECK, $activePlugins, true)) {
                return true;
            }

            foreach ($activePlugins as $activePlugin) {
                if (assetCleanUpIsProPluginBasename($activePlugin)) {
                    return true;
                }
            }
        }

        // Pro is network-active on multisite.
        if (is_multisite()) {
            $networkActivePlugins = get_site_option('active_sitewide_plugins', array());

            if (is_array($networkActivePlugins)) {
                if (isset($networkActivePlugins[WPACU_PRO_PLUGIN_TO_CHECK])) {
                    return true;
                }

                foreach (array_keys($networkActivePlugins) as $activePlugin) {
                    if (assetCleanUpIsProPluginBasename($activePlugin)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

// Check this at the earliest time the plugin loads to avoid defining any further code that could interfere with the Pro version
if (assetCleanUpLiteShouldStayDormant()) {
    return;
}
// [/wpacu_lite]

// Lite is the edition that will continue loading. Define the shared constant
// only now, preserving compatibility with integrations that rely on it.
if ( ! defined('WPACU_PLUGIN_VERSION') ) {
    define('WPACU_PLUGIN_VERSION', WPACU_LITE_PLUGIN_VERSION);
}

$wpacuPluginTitle = 'Asset CleanUp';

if ( ! defined('WPACU_PLUGIN_TITLE') ) {
    define('WPACU_PLUGIN_TITLE', $wpacuPluginTitle); // a short version of the plugin name
}

if ( ! defined('WPACU_PLUGIN_ID') ) {
	define( 'WPACU_PLUGIN_ID', 'wpassetcleanup' ); // unique prefix (same plugin ID name for 'lite' and 'pro')
}

if ( ! defined('WPACU_PLUGIN_SLUG') ) {
    define('WPACU_PLUGIN_SLUG', 'wp-asset-clean-up'); // useful to detect which functions to trigger (e.g. JS files)
}

if ( ! defined('WPACU_PLUGIN_FILE') ) {
    define('WPACU_PLUGIN_FILE', __FILE__);
}

if ( ! defined('WPACU_PLUGIN_BASE') ) {
    define('WPACU_PLUGIN_BASE', plugin_basename(WPACU_PLUGIN_FILE));
}

if ( ! defined('WPACU_PLUGIN_DIR') ) {
    define('WPACU_PLUGIN_DIR', __DIR__);
}

if ( ! defined('WPACU_PLUGIN_CLASSES_PATH') ) {
    define('WPACU_PLUGIN_CLASSES_PATH', WPACU_PLUGIN_DIR . '/classes/');
}

if ( ! defined('WPACU_PLUGIN_URL') ) {
    define('WPACU_PLUGIN_URL', plugins_url('', WPACU_PLUGIN_FILE));
}

if ( ! defined('WPACU_EARLY_TRIGGERS_CALLED') ) {
    // [wpacu_lite]
    add_filter('wpacu_plugin_no_load', function() {
        // There's no point in loading the plugin on a REST API call
        // This is valid for the Lite version as the Pro version could work differently  / read more: https://www.assetcleanup.com/docs/?p=1469

        // Make exception and leave the oEmbed in case the feature is disabled
        // In "Settings" -- "Site-Wide Common Unloads" -- "Disable oEmbed (Embeds) Site-Wide"
        // Some functions has to be processed
        $restUrlPrefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $isOembedRequest = strpos($requestUri, '/' . $restUrlPrefix . '/oembed/') !== false;

        if ( ! $isOembedRequest && function_exists('assetCleanUpIsRestCall') && assetCleanUpIsRestCall() ) {
            return true;
        }

        return false;
    });
    // [/wpacu_lite]

    require_once __DIR__ . '/early-triggers.php';
}

if (assetCleanUpNoLoad()) {
    return; // do not continue
}

define('WPACU_ADMIN_PAGE_ID_START', WPACU_PLUGIN_ID . '_getting_started');

// Do not load the plugin if the PHP version is below 5.6
// If PHP_VERSION_ID is not defined, then the PHP version is below 5.2.7, thus the plugin is not usable
$wpacuWrongPhp = ((! defined('PHP_VERSION_ID')) || (defined('PHP_VERSION_ID') && PHP_VERSION_ID < 50600));

wpacuDefineConstant( 'WPACU_WRONG_PHP_VERSION', ( ( $wpacuWrongPhp ) ? 'true' : 'false' ) );

if ($wpacuWrongPhp && is_admin()) { // Dashboard
    add_action('admin_notices', function() {
	    /**
	     * Print the message to the user after the plugin was deactivated
	     */
	    echo '<div class="wpacu-error is-dismissible"><p>'.

	         sprintf(
		         esc_html__('%1$s requires %2$s PHP version installed. You have %3$s.', 'wp-asset-clean-up'),
		         '<strong>'.WPACU_PLUGIN_TITLE.'</strong>',
		         '<span style="color: green;"><strong>5.6+</strong></span>',
		         '<strong>'.PHP_VERSION.'</strong>'
	         ) . ' '.
	         esc_html__('If your website is compatible with PHP 7+ (e.g. you can check with your developers or contact the hosting company), it\'s strongly recommended to upgrade to a newer PHP version for a better performance.', 'wp-asset-clean-up').' '.
	         esc_html__('Thus, the plugin will not trigger on the front-end view to avoid any possible errors.', 'wp-asset-clean-up').

	         '</p></div>';

	    if (isset($_GET['active'])) {
		    unset($_GET['activate']);
	    }
    });
} elseif ($wpacuWrongPhp) { // Front
    return;
}

// Global Values
define('WPACU_LOAD_ASSETS_REQ_KEY',  WPACU_PLUGIN_ID . '_load');
define('WPACU_FORM_ASSETS_POST_KEY', WPACU_PLUGIN_ID.'_form_assets'); // starting from Pro version 1.1.9.9 & Lite version 1.3.8.1

$wpacuGetLoadedAssetsAction = ((isset($_REQUEST[WPACU_LOAD_ASSETS_REQ_KEY]) && $_REQUEST[WPACU_LOAD_ASSETS_REQ_KEY])
                            || (isset($_REQUEST['action']) && $_REQUEST['action'] === WPACU_PLUGIN_ID.'_get_loaded_assets'));
define('WPACU_GET_LOADED_ASSETS_ACTION', $wpacuGetLoadedAssetsAction);

// [wpacu_lite]
if ( ! defined('WPACU_LITE_DIR') ) {
    define('WPACU_LITE_DIR', WPACU_PLUGIN_DIR . '/lite/');
}

if ( ! defined('WPACU_LITE_CLASSES_PATH') ) {
    define('WPACU_LITE_CLASSES_PATH', WPACU_LITE_DIR . 'classes/');
}

if ( ! defined('WPACU_PLUGIN_GO_PRO_URL') ) {
    define('WPACU_PLUGIN_GO_PRO_URL', 'https://www.gabelivan.com/items/wp-asset-cleanup-pro/'); // no query strings to be added
}

include_once WPACU_LITE_CLASSES_PATH . '_BootstrapLite.php';
\WpAssetCleanUpLite\_BootstrapLite::registerEarlyHooks();
// [/wpacu_lite]

require_once WPACU_PLUGIN_DIR.'/wpacu-load.php';

$isDashboardManageAssets    = isset( $_GET['page'] ) && ( $_GET['page'] === WPACU_PLUGIN_ID . '_assets_manager' );
$isDashboardCriticalCssPage = isset( $_GET['wpacu_sub_page'] ) && ( $_GET['wpacu_sub_page'] === 'manage_critical_css' );
$isDashboardPluginsPage     = isset( $_GET['wpacu_sub_page'] ) && ( strpos( $_GET['wpacu_sub_page'], 'manage_plugins_' ) === 0 );

// In which situations should the composer libraries be loaded?
// Only load them when necessary
$wpacuIsWpacuAjaxRequest = ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest' )
	&& ( strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' ) !== false ) // The request URI contains 'admin-ajax.php'
	&& isset ($_POST['action']) && $_POST['action'] && strpos($_POST['action'], WPACU_PLUGIN_ID.'_') === 0;

if (WPACU_GET_LOADED_ASSETS_ACTION === true ||
    ! is_admin() ||
    (is_admin() && ($wpacuIsWpacuAjaxRequest || $isDashboardManageAssets || $isDashboardCriticalCssPage || $isDashboardPluginsPage))) {
	add_action('init', static function() {
		// "Smart Slider 3" & "WP Rocket" compatibility fix | triggered ONLY when the assets are fetched
		if ( ! function_exists('get_rocket_option') && class_exists( 'NextendSmartSliderWPRocket' ) ) {
			function get_rocket_option($option) { return ''; }
		}
	});

	add_action('parse_query', static function() { // very early triggering to set WPACU_ALL_ACTIVE_PLUGINS_LOADED
		if (defined('WPACU_ALL_ACTIVE_PLUGINS_LOADED')) { return; } // only trigger it once in this action
		define('WPACU_ALL_ACTIVE_PLUGINS_LOADED', true);
		\WpAssetCleanUp\OptimiseAssets\OptimizeCommon::preventAnyFrontendOptimization('parse_query');
	}, 1);
}

// No plugin changes are needed when a feed is loaded
// Only in the front-end view and when a request URI is there (e.g. not triggering the WP environment via an SSH terminal)
if ( isset($_SERVER['REQUEST_URI']) && ! is_admin() ) {
    add_action('setup_theme', static function () {
        global $wp_rewrite;

        if (isset($wp_rewrite->feed_base) &&
            $wp_rewrite->feed_base &&
            strpos($_SERVER['REQUEST_URI'], '/' . $wp_rewrite->feed_base) !== false) {
            $currentPageUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . parse_url(site_url(),
                    PHP_URL_HOST) . $_SERVER['REQUEST_URI'];

            $cleanCurrentPageUrl = $currentPageUrl;
            if (strpos($currentPageUrl, '?') !== false) {
                list($cleanCurrentPageUrl) = explode('?', $currentPageUrl);
            }

            // /{feed_slug_here}/ or /{feed_slug_here}/atom/
            if ($cleanCurrentPageUrl === site_url() . '/' . $wp_rewrite->feed_base . '/'
                || $cleanCurrentPageUrl === site_url() . '/' . $wp_rewrite->feed_base . '/atom/') {
                \WpAssetCleanUp\OptimiseAssets\OptimizeCommon::preventAnyFrontendOptimization();
            }
        }
    });
}

// Make sure the plugin doesn't load when the editor of either "X" theme or "Pro" website creator (theme.co) is ON
add_action('init', static function() {
    if (is_admin()) {
        return; // Not relevant for the Dashboard view, stop here!
    }

    if ( ! is_user_logged_in() || ! \WpAssetCleanUp\Menu::userCanAccessPlugin() ) {
        return; // Not relevant if the logged-in user does not have full rights
    }

    if (method_exists('Cornerstone_Common', 'get_app_slug') && in_array(get_stylesheet(), array('x', 'pro'))) {
        $customAppSlug = get_stylesheet(); // default one ('x' or 'pro')

        // Is there any custom slug set in "/wp-admin/admin.php?page=cornerstone-settings"?
        // "Settings" -> "Custom Path" (check it out below)
        $cornerStoneSettings = get_option('cornerstone_settings');
        if (isset($cornerStoneSettings['custom_app_slug']) && $cornerStoneSettings['custom_app_slug'] !== '') {
            $customAppSlug = $cornerStoneSettings['custom_app_slug'];
        }

        $lengthToUse = strlen($customAppSlug) + 2; // add the slashes to the count

        if (substr($_SERVER['REQUEST_URI'], -$lengthToUse) === '/'.$customAppSlug.'/') {
            add_filter( 'wpacu_prevent_any_frontend_optimization', '__return_true' );
        }
    }
}, PHP_INT_MAX);

// "Transliterator - WordPress Transliteration" breaks the HTML content in Asset CleanUp's admin pages
// by converting characters such as &lt; (that should stay as they are) to < thus, a fix is attempted to be made here
if (isset($_GET['page']) && is_string($_GET['page']) && (strpos($_GET['page'], WPACU_PLUGIN_ID.'_') !== false) && is_admin()) {
    $serbianTransliterationCacheClass = 'Serbian_Transliteration_Cache';

    if (class_exists($serbianTransliterationCacheClass) && method_exists($serbianTransliterationCacheClass, 'set')) {
        $serbianTransliterationCacheClass::set('is_editor', true);
    }
}

