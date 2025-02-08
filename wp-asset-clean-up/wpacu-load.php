<?php
// Exit if accessed directly
if (! defined('WPACU_PLUGIN_CLASSES_PATH')) {
    exit;
}

// Autoload Classes
function includeWpAssetCleanUpClassesAutoload($class)
{
	if ( ! (
		( function_exists( 'str_starts_with' ) && str_starts_with( $class, 'WpAssetCleanUp' ) ) ||
		( strncmp($class, 'WpAssetCleanUp', 14) === 0 )
	) ) {
		return;
	}

	// Reference Namespace
    if (strpos($class, '\\') === 14) {
	    $namespace = 'WpAssetCleanUp';
    }

	$classFilter = strtr($class, array(
		$namespace . '\\' => '',
		'\\'              => '/' // Can be directories such as "OptimiseAssets"
	));

	if ($namespace === 'WpAssetCleanUp') {
		include_once WPACU_PLUGIN_CLASSES_PATH . $classFilter . '.php';
	}

    }

spl_autoload_register('includeWpAssetCleanUpClassesAutoload');

\WpAssetCleanUp\ObjectCache::wpacu_cache_init();

if (isset($GLOBALS['wpacu_object_cache'])) {
    $wpacu_object_cache = $GLOBALS['wpacu_object_cache']; // just in case
}

// Menu
add_action('init', function() {
    if (is_admin()) {
        new \WpAssetCleanUp\Menu;
    }
});

// Main Class (common code for both the front-end and /wp-admin/ views)
\WpAssetCleanUp\Main::instance();
\WpAssetCleanUp\Main::instance()->loadAllSettings();

if (is_admin()) {
    \WpAssetCleanUp\Admin\MainAdmin::instance();
} else {
    // Situations when methods from MainAdmin are needed in the front-end view
    // e.g. when "wp_assetcleanup_load=1" is used or when the admin manages the assets in the front-end view (bottom of the page)
    add_action('init', function () {
        $isFrontEndEditView  = \WpAssetCleanUp\Main::instance()->isFrontendEditView;

        if ( $isFrontEndEditView || \WpAssetCleanUp\Main::instance()->isGetAssetsCall ) {
            \WpAssetCleanUp\Admin\MainAdmin::instance();
        }
    });
}

if ( ! is_admin() ) {
    \WpAssetCleanUp\MainFront::instance();
}

$wpacuSettingsClass = new \WpAssetCleanUp\Settings();

if (is_admin()) {
    $wpacuSettingsAdminClass = new \WpAssetCleanUp\Admin\SettingsAdmin();
    $wpacuSettingsAdminClass->init();

    $wpacuSettingsAdminOnlyForAdminClass = new \WpAssetCleanUp\Admin\SettingsAdminOnlyForAdmin();
    $wpacuSettingsAdminOnlyForAdminClass->init();
}

// The following are only relevant when you're logged in
add_action('init', function() {
	if (\WpAssetCleanUp\Menu::userCanAccessAssetCleanUp()) {
		\WpAssetCleanUp\AssetsManager::instance();

        $withinAdminAreaOrFrontendWithCssJsManagerOrClearCache = is_admin() ||
            (\WpAssetCleanUp\AssetsManager::instance()->frontendShow() || \WpAssetCleanUp\OwnAssets::isPluginClearCacheLinkAccessible());

		if ( $withinAdminAreaOrFrontendWithCssJsManagerOrClearCache ) {
            $wpacuOwnAssets = new \WpAssetCleanUp\OwnAssets;
            $wpacuOwnAssets->init();

			// Add / Update / Remove Settings
			$wpacuUpdate = new \WpAssetCleanUp\Update;
			$wpacuUpdate->init();

            // Relevant for the admin area or when the admin is using the CSS/JS manager in the front-end
            if (is_admin() || \WpAssetCleanUp\AssetsManager::instance()->frontendShow()) {
                // Initialize information (irrelevant for the guest visitor)
                new \WpAssetCleanUp\Admin\Info();
            }
		}
	}
});

if ( ! is_admin() ) {
	add_action( 'plugins_loaded', function() use ( $wpacuSettingsClass ) {
		$wpacuSettings = $wpacuSettingsClass->getAll();

		// If "Manage in the front-end" is enabled & the admin is logged-in, do not trigger any Autoptimize caching at all
		if ( $wpacuSettings['frontend_show'] && ! defined( 'AUTOPTIMIZE_NOBUFFER_OPTIMIZE' ) && \WpAssetCleanUp\Menu::userCanAccessAssetCleanUp() ) {
			define( 'AUTOPTIMIZE_NOBUFFER_OPTIMIZE', true );
		}
	}, - PHP_INT_MAX );
}

// Admin Bar (Top Area of the website when a user is logged in)
add_action('init', function() {
	if ( ( ! \WpAssetCleanUp\Main::instance()->settings['hide_from_admin_bar'] ) &&
		 is_admin_bar_showing() &&
         \WpAssetCleanUp\Menu::userCanAccessAssetCleanUp() ) {
		new WpAssetCleanUp\AdminBar();
	}
});

// Any debug?
if (isset($_GET['wpacu_debug']) ||
    isset($_GET['wpacu_get_cache_dir_size']) ||
	isset($_GET['wpacu_get_already_minified']) ||
    isset($_GET['wpacu_remove_already_minified']) ||
    isset($_GET['wpacu_limit_already_minified'])
) {
	new \WpAssetCleanUp\Debug();
}

// Maintenance
new \WpAssetCleanUp\Maintenance();

// Common functions for both CSS & JS combinations
// Clear CSS/JS caching functionality
$wpacuOptimizeCommon = new \WpAssetCleanUp\OptimiseAssets\OptimizeCommon();
$wpacuOptimizeCommon->init();

if (is_admin()) {
	/*
	 * Trigger only within the Dashboard view (e.g., within /wp-admin/)
	 */
	$wpacuPlugin = new \WpAssetCleanUp\Admin\Plugin;
	$wpacuPlugin->init();

    $adminPluginAnnouncementsClass = new \WpAssetCleanUp\Admin\PluginAnnouncements();
    $adminPluginAnnouncementsClass->init();

	new \WpAssetCleanUp\Admin\PluginReview();

	$wpacuPluginTracking = new \WpAssetCleanUp\PluginTracking();
	$wpacuPluginTracking->init();

	$wpacuTools = new \WpAssetCleanUp\Admin\Tools();
	$wpacuTools->init();

	new \WpAssetCleanUp\Admin\AjaxSearchPagesAutocomplete();

    \WpAssetCleanUp\Preloads::instance()->initAdmin();

    new \WpAssetCleanUp\Admin\CriticalCssAdmin();
} elseif ($wpacuOptimizeCommon::triggerFrontendOptimization()) {
	/*
	 * Trigger the CSS & JS combination only in the front-end view in certain conditions (not within the Dashboard)
	 */
	// Combine/Minify CSS Files Setup
	$wpacuOptimizeCss = new \WpAssetCleanUp\OptimiseAssets\OptimizeCss();
	$wpacuOptimizeCss->init();

	// Combine/Minify JS Files Setup
	$wpacuOptimizeJs = new \WpAssetCleanUp\OptimiseAssets\OptimizeJs();
	$wpacuOptimizeJs->init();

	/*
	 * Trigger only in the front-end view (e.g. Homepage URL, /contact/, /about/ etc.)
	 */
	$wpacuCleanUp = new \WpAssetCleanUp\CleanUp();
	$wpacuCleanUp->init();

	add_action('init', function() {
		$loadFontsLocalClass = ! (wpacuIsDefinedConstant('WPACU_ALLOW_ONLY_UNLOAD_RULES')
            || ( ! is_admin() && \WpAssetCleanUp\OptimiseAssets\OptimizeCommon::preventAnyFrontendOptimization() )
            || ( ! \WpAssetCleanUp\Main::instance()->settings['local_fonts_display'] && ! trim(\WpAssetCleanUp\Main::instance()->settings['local_fonts_preload_files']) ) );

		if ( $loadFontsLocalClass ) {
			$wpacuFontsLocal = new \WpAssetCleanUp\OptimiseAssets\FontsLocal();
			$wpacuFontsLocal->init();
		}
	}, 11);

    if ( \WpAssetCleanUp\Main::instance()->settings['google_fonts_combine'] ||
         \WpAssetCleanUp\Main::instance()->settings['google_fonts_display'] ||
         \WpAssetCleanUp\Main::instance()->settings['google_fonts_preconnect'] ||
         \WpAssetCleanUp\Main::instance()->settings['google_fonts_preload_files'] ||
         \WpAssetCleanUp\Main::instance()->settings['google_fonts_remove'] ) {
        $wpacuFontsGoogle = new \WpAssetCleanUp\OptimiseAssets\FontsGoogle();
        $wpacuFontsGoogle->init();
    }

    if ( ! isset($_GET['wpacu_no_critical_css_and_preload']) ) {
        new \WpAssetCleanUp\OptimiseAssets\CriticalCss();
    }
}
