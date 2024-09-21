<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\SettingsAdminOnlyForAdmin;

/**
 * Class Settings
 * @package WpAssetCleanUp
 */
class Settings
{
	/**
	 * @var array
	 */
	public $settingsKeys = array(
		// Stored in 'wpassetcleanup_settings'
		'wiki_read',

        // Dashboard Assets Management
        'dashboard_show',
        'dom_get_type',

		'show_assets_meta_box', // Asset CleanUp (Pro): CSS & JavaScript Manager & Options

        'hide_meta_boxes_for_post_types', // Hide all metaboxes for the chosen post types

		// Front-end View Assets Management
        'frontend_show',
        'frontend_show_exceptions',

        // [For Admnistrators Only]
		// Allow managing assets to:
		'allow_manage_assets_to',
		'allow_manage_assets_to_list',
        // [For Admnistrators Only]

		// Hide plugin's menus to make the top admin bar / left sidebar within the Dashboard cleaner (if the plugin is not used much)
		'hide_from_admin_bar',
		'hide_from_side_bar', // Since v1.1.7.1 (Pro)

		// The way the CSS/JS list is showing (various ways depending on the preference)
		'assets_list_layout',
		'assets_list_layout_areas_status',
		'assets_list_inline_code_status',

        'assets_list_layout_plugin_area_status',

		// Fetch automatically, Fetch on click
        'assets_list_show_status',

		'input_style',

		'hide_core_files',
		'test_mode',

		// Combine loaded CSS (remaining ones after unloading the useless ones) into fewer files
		'combine_loaded_css',
		'combine_loaded_css_exceptions',

		// Added since v1.1.7.3 (Pro) & v1.3.6.4 (Lite)
		'combine_loaded_css_for',
		'combine_loaded_js_for',

		// [CRITICAL CSS]
        'critical_css_status',
        // [/CRITICAL CSS]

        'cache_dynamic_loaded_css',
		'cache_dynamic_loaded_js',

        'inline_js_files_below_size', // Enable?
		'inline_js_files_below_size_input', // Actual size
        'inline_js_files_list',

        'move_inline_jquery_after_src_tag',

        'move_scripts_to_body_exceptions',

        'inline_css_files',
        'inline_css_files_below_size', // Enable?
        'inline_css_files_below_size_input', // Actual size
        'inline_css_files_list',

        // Combine loaded JS (remaining ones after unloading the useless ones) into fewer files
        'combine_loaded_js',
		'combine_loaded_js_exceptions',
        'combine_loaded_js_for_admin_only',
        'combine_loaded_js_defer_body', // Applies 'defer="defer"' to the combined file(s) within BODY tag
        'combine_loaded_js_try_catch', // try {} catch (e) {} for each individual file within a combined file

		// Minify each loaded CSS (remaining ones after unloading the useless ones)
		'minify_loaded_css',
		'minify_loaded_css_for',
		'minify_loaded_css_inline', // deprecated ("minify_loaded_css_for" is used instead)
		'minify_loaded_css_exceptions',

		// Minify each loaded JS (remaining ones after unloading the useless ones)
		'minify_loaded_js',
		'minify_loaded_js_for',
		'minify_loaded_js_inline', // deprecated ("minify_loaded_js_for" is used instead)
		'minify_loaded_js_exceptions',

        'cdn_rewrite_enable',
		'cdn_rewrite_url_css',
		'cdn_rewrite_url_js',

        'disable_emojis',

		// v1.2.1.2+ (Pro), v1.3.8.6 (Lite)
		'disable_rss_feed',
        'disable_rss_feed_message',

		'disable_oembed',

		// Stored in 'wpassetcleanup_global_unload' option
		'disable_dashicons_for_guests', // CSS
		'disable_wp_block_library', // CSS
        'disable_jquery_migrate', // JS
        'disable_comment_reply', // JS

		// <head> CleanUp
		'remove_rsd_link',
		'remove_wlw_link',
		'remove_rest_api_link',
		'remove_shortlink',
		'remove_posts_rel_links',
		'remove_wp_version',

		// all "generator" meta-tags including the WordPress version
		'remove_generator_tag',

		// RSS Feed Links
		'remove_main_feed_link',
		'remove_comment_feed_link',

		// Remove HTML comments
		'remove_html_comments',
		'remove_html_comments_exceptions',

		'disable_xmlrpc',

        // Allow Usage Tracking
        'allow_usage_tracking',

        // Serve cached CSS/JS details from: Database or Disk
        'fetch_cached_files_details_from',

        // Clear CSS/JS files cache after (x) days
        'clear_cached_files_after',

		// Do not load Asset CleanUp (Pro) if the URI is matched by the specified patterns
		'do_not_load_plugin_patterns',

        // Do not load specific Asset CleanUp (Pro) features if the URI is matched by the specified patterns
        // The previous feature prevents the plugin from loading while this one limits the optimizations on the targeted pages
        // Pro: v1.2.4.2+
        'do_not_load_plugin_features',

        // [For Admnistrators Only]
        'access_via_non_admin_user_roles',
        'access_via_specific_non_admin_users',
        // [/For Admnistrators Only]

        // Local Fonts: "font-display" CSS property
        'local_fonts_display',
        'local_fonts_display_overwrite',

        // Local Fonts: Preload Files
        'local_fonts_preload_files',

        // Google Fonts: Combine Into One Request
        'google_fonts_combine',
        'google_fonts_combine_type',

        // Google Fonts: "font-display" CSS property: LINK & STYLE tags, @import in CSS files
        'google_fonts_display',

        // Google Fonts: preconnect hint
        'google_fonts_preconnect',

        // Google Fonts: Preload Files
        'google_fonts_preload_files',

        // Google Fonts: Remove all traces
        'google_fonts_remove',

        // [wpacu_lite]
        // Do not trigger Feedback Popup on Deactivation
        'disable_freemius'
		// [/wpacu_lite]
    );

    /**
     * @var array
     */
    public $currentSettings = array();

	/**
	 * @var array
	 */
	public $defaultSettings = array();

	/**
	 * Settings constructor.
	 */
	public function __construct()
    {
        $this->defaultSettings = array(
	        // Show the assets' list within the Dashboard, while they are hidden in the front-end view
	        'dashboard_show' => '1',

	        // Direct AJAX call by default (not via WP Remote POST)
	        'dom_get_type'   => 'direct',

	        'show_assets_meta_box' => 1,

	        'hide_meta_boxes_for_post_types' => array(),

	        // Very good especially for page builders: Divi Visual Builder, Oxygen Builder, WPBakery, Beaver Builder etc.
	        // It is also hidden in preview mode (if query strings such as 'preview_nonce' are used)
	        'frontend_show_exceptions' =>  'et_fb=1'."\n"
	                                       .'ct_builder=true'."\n"
	                                       .'vc_editable=true'."\n"
	                                       .'preview_nonce='."\n",

            // [For Admnistrators Only]
	        'allow_manage_assets_to' => 'any_admin',
	        'allow_manage_assets_to_list' => array(),
            // [/For Admnistrators Only]

	        // Since v1.2.9.3 (Lite) and version 1.1.0.8 (Pro), the default value is "by-location" (All Styles & All Scripts - By Location (Theme, Plugins, Custom & External))
	        // Prior to that it's "two-lists" (All Styles & All Scripts - 2 separate lists)
	        'assets_list_layout'              => 'by-location',
	        'assets_list_layout_areas_status' => 'expanded',

	        'assets_list_layout_plugin_area_status' => 'expanded',

	        // "contracted" since 1.1.0.8 (Pro)
	        'assets_list_inline_code_status' => 'contracted', // takes less space overall

	        'minify_loaded_css_for' => 'href',
            'minify_loaded_js_for'  => 'src',

	        'minify_loaded_css_exceptions' => '(.*?)\.min.css'. "\n". '/wd-instagram-feed/(.*?).css',
	        'minify_loaded_js_exceptions'  => '(.*?)\.min.js' . "\n". '/wd-instagram-feed/(.*?).js',

	        'inline_css_files_below_size' => '1', // Enabled by default
	        'inline_css_files_below_size_input' => '3', // Size in KB

	        'inline_js_files_below_size_input' => '3', // Size in KB

            // Specific AMP scripts should always be in 'HEAD'
            'move_scripts_to_body_exceptions' => '//cdn.ampproject.org/',

	        // Since v1.1.7.3 (Pro) & v1.3.6.4 (Lite)
            'combine_loaded_css_for' => 'guests',
	        'combine_loaded_js_for'  => 'guests',

	        'combine_loaded_css_exceptions' => '/wd-instagram-feed/(.*?).css',
	        'combine_loaded_js_exceptions'  => '/wd-instagram-feed/(.*?).js',

	        // [CRITICAL CSS]
	        'critical_css_status' => 'on',
	        // [/CRITICAL CSS]

	        'input_style' => 'enhanced',

	        // Starting from v1.2.8.6 (lite), WordPress core files are hidden in the assets' list as a default setting
	        'hide_core_files' => '1',

            'fetch_cached_files_details_from' => 'disk', // Do not add more rows to the database by default (options table can become quite large)

            'clear_cached_files_after' => '14', // 2 weeks

            // Starting from v1.3.6.9 (Lite) & v1.1.7.9 (Pro), /cart/ & /checkout/ pages are added to the exclusion list by default
            'do_not_load_plugin_patterns' => '/cart/'. "\n". '/checkout/',

	        'disable_rss_feed_message' => __('There is no RSS feed available.', 'wp-asset-clean-up'),

	        // [Hidden Settings]
            // They are prefixed with underscore _
	        '_combine_loaded_css_append_handle_extra' => '1',
	        '_combine_loaded_js_append_handle_extra'  => '1'
	        // [/Hidden Settings]
        );

        // In case it's udpated within the CSS/JS manager, make sure it's updated in the settings no matter how early they will be triggered (before the actual update takes place, after the "Update" button is clicked)
        add_filter('wpacu_settings', function($settings) {
            if ( ! empty($_POST['wpacu_assets_list_layout']) ) {
                $settings['assets_list_layout'] = sanitize_text_field($_POST['wpacu_assets_list_layout']);
            }

            if ( ! empty($_POST['wpacu_dom_get_type']) ) {
                $settings['dom_get_type'] = sanitize_text_field($_POST['wpacu_dom_get_type']);
            }

            return $settings;
        });

        add_filter('admin_init', array($this, 'filterSettingsOnAdminInit'), 0);
    }

    /**
     * Due to "current_user_can", this will be called within an early "init" action
     *
     * @return void
     */
    public function filterSettingsOnAdminInit()
    {
        if (is_admin()) {
            $settings = Main::instance()->settings;

            $settings = self::filterSpecialSettings($settings);

            Main::instance()->settings = $settings;
        }
    }

	/**
	 * @param false $forceRefetch
	 *
	 * @return array|mixed
	 */
	public function getAll($forceRefetch = false)
    {
        if ($forceRefetch) {
	        $GLOBALS['wp_object_cache']->delete(WPACU_PLUGIN_ID . '_settings', 'options');
        } elseif ( ! empty( $this->currentSettings ) ) { // default check
            return apply_filters('wpacu_settings', $this->currentSettings);
        }

        $settingsOption = get_option(WPACU_PLUGIN_ID . '_settings');

	    $applyDefaultToNeverSaved = array(
		    'frontend_show_exceptions',
		    'minify_loaded_css_exceptions',
		    'inline_css_files_below_size_input',
		    'minify_loaded_js_exceptions',
		    'inline_js_files_below_size_input',
		    'clear_cached_files_after',
		    'hide_meta_boxes_for_post_types',
            'disable_rss_feed_message'
	    );

        // If there's already a record in the database
        if ($settingsOption !== '' && is_string($settingsOption)) {
            $settings = json_decode($settingsOption, ARRAY_A);

            if (wpacuJsonLastError() === JSON_ERROR_NONE) {
                // Make sure all the keys are there even if no value is attached to them
                // To avoid writing extra checks in other parts of the code and prevent PHP notice errors
                foreach ($this->settingsKeys as $settingsKey) {
                    if ( ! array_key_exists($settingsKey, $settings) ) {
                        $settings[$settingsKey] = '';

                        // If it doesn't exist, it was never saved (Exception: "show_assets_meta_box")
                        // Make sure the default value is added
	                    if ( in_array($settingsKey, $applyDefaultToNeverSaved) ) {
	                        $settings[ $settingsKey ] = isset( $this->defaultSettings[ $settingsKey ] ) ? $this->defaultSettings[ $settingsKey ] : '';
                        }
                    }
                }

                $this->currentSettings = $this->filterSettings($settings);

                return apply_filters('wpacu_settings', $this->currentSettings);
            }
        }

	    // No record in the database? Set the default values
	    // That could be because no changes were done on the "Settings" page
	    // OR a full reset of the plugin (via "Tools") was performed
        $finalDefaultSettings = $this->defaultSettings;

        foreach ($this->settingsKeys as $settingsKey) {
	        if (! array_key_exists($settingsKey, $finalDefaultSettings)) {
		        // Keep the keys with empty values to avoid notice errors
		        $finalDefaultSettings[$settingsKey] = '';
	        }
        }

	    return apply_filters('wpacu_settings', $this->filterSettings($finalDefaultSettings));
    }

	/**
	 * @param $settings
	 *
	 * @return mixed
     * @noinspection NestedAssignmentsUsageInspection
     *
     */
	public function filterSettings($settings)
	{
		// Renamed "hide_assets_meta_box" to "show_assets_meta_box" (legacy)
		if ( isset($settings['show_assets_meta_box'], $settings['hide_assets_meta_box']) && $settings['hide_assets_meta_box'] == 1 ) { // legacy
			$settings['show_assets_meta_box'] = '0';
		}

		// "show_assets_meta_box" is either 0 or 1
		// if it doesn't exist, it was never saved (the user didn't update the settings after updating to 1.2.0.1)
		// Thus it will be activated by default: 1
		if ( ! isset($settings['show_assets_meta_box']) || $settings['show_assets_meta_box'] === '' ) {
			$settings['show_assets_meta_box'] = 1;
		}

		// Oxygen Builder is triggered, and some users might want to trigger unload rules there to make the editor faster, especially plugin unload rules
        // We will prevent minify/combine and other functions that will require caching any files to avoid any errors
		if (wpacuIsDefinedConstant('WPACU_ALLOW_ONLY_UNLOAD_RULES')) {
		    $settings['minify_loaded_css']
                = $settings['minify_loaded_js']
                = $settings['combine_loaded_css']
                = $settings['combine_loaded_js']
                = $settings['inline_css_files']
                = $settings['google_fonts_combine']
                = $settings['google_fonts_remove']
                = false;
		}

		if ($settings['minify_loaded_css_inline']) {
			$settings['minify_loaded_css_for'] = 'all';
		}

		if ($settings['minify_loaded_js_inline']) {
		    $settings['minify_loaded_js_for'] = 'all';
		}

		// Google Fonts Removal is enabled; make sure other related settings are nullified
		if ($settings['google_fonts_remove']) {
            $settings['google_fonts_combine']
                = $settings['google_fonts_combine_type']
                = $settings['google_fonts_display']
                = $settings['google_fonts_preconnect']
                = $settings['google_fonts_preload_files']
                = '';
		}

        if ((int)$settings['clear_cached_files_after'] === 0) {
	        $settings['clear_cached_files_after'] = 1; // Starting from v1.2.3.6 (Pro)
        }

        if ( function_exists('get_rocket_option') && wpacuIsPluginActive('wp-rocket/wp-rocket.php') ) {
	        if ( ! defined( 'WPACU_WP_ROCKET_DELAY_JS_ENABLED' ) && get_rocket_option('delay_js') ) {
		        // When "File Optimization" -- "Delay JavaScript execution" is enabled in WP Rocket
                // Set "Settings" -- "Optimize JavaScript" -- "Combine loaded JS (JavaScript) into fewer files" to false
                $settings['combine_loaded_js'] = '';
                define( 'WPACU_WP_ROCKET_DELAY_JS_ENABLED', true );
	        }
        }

		// [START] Overwrite specific settings via query string
        // Ideally, either use /?wpacu_settings[...] OR /?wpacu_skip_test_mode (never both because they could interfere)
		if ( ! empty($_GET['wpacu_settings']) && is_array($_GET['wpacu_settings']) ) {
            foreach ($_GET['wpacu_settings'] as $settingKey => $settingValue) {
                if ($settingValue === 'true') {
	                $settingValue = true;
                }
	            if ($settingValue === 'false') {
		            $settingValue = false;
	            }
                $settings[$settingKey] = $settingValue;
            }
		}

		// /?wpacu_test_mode (will load the page with "Test Mode" enabled disregarding the value from the plugin's "Settings")
		// For debugging purposes (e.g. to make sure the HTML source is the same when a guest user accesses it as the one that is generated when the plugin is deactivated)
		if ( isset($_GET['wpacu_test_mode']) ) {
			$settings['test_mode'] = true;
		}

		if ( isset($_GET['wpacu_skip_test_mode']) ) {
			$settings['test_mode'] = false;
		}

		// /?wpacu_skip_inline_css
		if (isset($_GET['wpacu_skip_inline_css_files'])) {
			$settings['inline_css_files'] = false;
		}

		// /?wpacu_skip_inline_js
		if (isset($_GET['wpacu_skip_inline_js_files'])) {
			$settings['inline_js_files'] = false;
		}

		// /?wpacu_manage_front -> "Manage in the Front-end" via query string request
		// Useful when working for a client, and you prefer him to view the pages (while logged-in) without the CSS/JS list at the bottom
		if (isset($_GET['wpacu_manage_front'])) {
			$settings['frontend_show'] = true;
		}

		// /?wpacu_manage_dash -> "Manage in the Dashboard" via query string request
		// For debugging purposes
		if ( (isset($_REQUEST['wpacu_manage_dash']) || isset($_REQUEST['force_manage_dash'])) && is_admin() ) {
			$settings['dashboard_show'] = true;
		}
		// [END] Overwrite specific settings via query string

        // "Settings"
        // -- "Plugin Usage Preferences"
        // -- "Do not load on specific pages"
        // -- "Prevent features of Asset CleanUp Pro from triggering on certain pages"
        if ( ! empty($settings['do_not_load_plugin_features']) ) {
            $requestUriAsItIs = rawurldecode($_SERVER['REQUEST_URI']);

            foreach ( $settings['do_not_load_plugin_features'] as $setValues ) {
                if (trim($setValues['pattern']) === '{homepage}') {
                    $condToUse = wpacuIsHomePageUrl(rawurldecode($_SERVER['REQUEST_URI']));
                } else {
                    $condToUse = strpos($requestUriAsItIs, $setValues['pattern']) !== false;
                }

                $isToCheck = ( ! empty($setValues['pattern']) && ! empty($setValues['list']) ) &&
                           ($condToUse || @preg_match($setValues['pattern'], $requestUriAsItIs) );

                if ( ! $isToCheck ) {
                    continue;
                }

                foreach ($setValues['list'] as $featureToAvoid) {
                    // [CSS]
                    if ($featureToAvoid === 'minify_css') {
                        $settings['minify_loaded_css'] = false;
                    }

                    if ($featureToAvoid === 'inline_css') {
                        $settings['inline_css_files'] = false;
                    }

                    if ($featureToAvoid === 'combine_css') {
                        $settings['combine_loaded_css'] = false;
                    }

                    if ($featureToAvoid === 'critical_css') {
                        $settings['critical_css_status'] = 'off';
                    }

                    if ($featureToAvoid === 'cache_dynamic_loaded_css') {
                        $settings['cache_dynamic_loaded_css'] = false;
                    }
                    // [/CSS]

                    // [JavaScript]
                    if ($featureToAvoid === 'minify_js') {
                        $settings['minify_loaded_js'] = false;
                    }

                    if ($featureToAvoid === 'combine_js') {
                        $settings['combine_loaded_js'] = false;
                    }

                    if ($featureToAvoid === 'move_inline_jquery_after_src_tag') {
                        $settings['move_inline_jquery_after_src_tag'] = false;
                    }

                    if ($featureToAvoid === 'cache_dynamic_loaded_js') {
                        $settings['cache_dynamic_loaded_js'] = false;
                    }
                    // [/JavaScript]

                    // [Local Fonts]
                    if ($featureToAvoid === 'local_fonts_display') {
                        $settings['local_fonts_display'] = '';
                    }

                    if ($featureToAvoid === 'local_fonts_preload') {
                        $settings['local_fonts_preload_files'] = '';
                    }
                    // [/Local Fonts]

                    // [Google Fonts]
                    if ($featureToAvoid === 'google_fonts_combine') {
                        $settings['google_fonts_combine'] = '';
                    }

                    if ($featureToAvoid === 'google_fonts_display') {
                        $settings['google_fonts_display'] = '';
                    }

                    if ($featureToAvoid === 'google_fonts_preconnect') {
                        $settings['google_fonts_preconnect'] = '';
                    }

                    if ($featureToAvoid === 'google_fonts_preload') {
                        $settings['google_fonts_preload_files'] = '';
                    }
                    // [/Google Fonts]
                }
            }
        }

        // This will not triggered on the first getAll() call (very early, before other WordPress functions are set)
        // Instead, it will be triggered later on when the values below are relevant
        if (is_admin() && function_exists('wp_get_current_user')) {
            $settings = self::filterSpecialSettings($settings);
        }

        return $settings;
	}

    /**
     * @param $settings
     *
     * @return mixed
     */
    public static function filterSpecialSettings($settings)
    {
        if (current_user_can(Menu::$defaultAccessRole)) {
            if (self::triggerCssJsManagerCheck()) {
                $allowManageAssetsArray                  = SettingsAdminOnlyForAdmin::filterAnySpecifiedAdminsForAccessToAssetsManager($settings);
                $settings['allow_manage_assets_to']      = $allowManageAssetsArray['allow_manage_assets_to'];
                $settings['allow_manage_assets_to_list'] = $allowManageAssetsArray['allow_manage_assets_to_list'];

                // "only to the following administrator(s):" can not be empty; if that's the case, reset it to "any administrator"
                if ($settings['allow_manage_assets_to'] === 'chosen' && empty($settings['allow_manage_assets_to_list'])) {
                    $settings['allow_manage_assets_to'] = 'any_admin';
                }
            }

            if (Menu::isPluginPage() === 'settings') {
                $nonAdminRolesArray           = SettingsAdminOnlyForAdmin::getAllNonAdminUserRolesWithAnyPluginAccessCap();
                $nonAdminsWithPluginAccessCap = SettingsAdminOnlyForAdmin::getSpecificNonAdminUsersIdsWithPluginAccessCap();

                $settings['access_via_non_admin_user_roles']     = ! empty($nonAdminRolesArray['non_admin_role_slugs_with_cap']) ? $nonAdminRolesArray['non_admin_role_slugs_with_cap'] : array();
                $settings['access_via_specific_non_admin_users'] = $nonAdminsWithPluginAccessCap;
            }
        } else {
            // Non-admins have no business with these settings
            $settings['allow_manage_assets_to']              = 'any_admin';
            $settings['allow_manage_assets_to_list']         = array();
            $settings['access_via_non_admin_user_roles']     = array();
            $settings['access_via_specific_non_admin_users'] = array();
        }

        return $settings;
    }

    /**
     * This will return true:
     *
     * 1) In the "Settings" page
     * 2) In pages where the CSS/JS manager will show up
     * 3) In pages where the link to the CSS/JS manager will be printed such as a list of posts
     *
     * @return bool
     */
    public static function triggerCssJsManagerCheck()
    {
        if ( isset($_SERVER['REQUEST_URI']) &&
            ( strpos($_SERVER['REQUEST_URI'], '/edit.php') !== false ||
              strpos($_SERVER['REQUEST_URI'], '/post.php?post=') !== false ||
              strpos($_SERVER['REQUEST_URI'], 'term.php?taxonomy=') !== false
            ) ) {
            return true;
        }

        if ( Menu::isPluginPage() || ( ! is_admin() && AssetsManager::instance()->frontendShow() ) ) {
            return true;
        }

        return false;
    }
}
