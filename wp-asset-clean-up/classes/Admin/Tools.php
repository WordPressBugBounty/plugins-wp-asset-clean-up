<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\CriticalCss;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;
use WpAssetCleanUp\OptimiseAssets\OptimizeCss;
use WpAssetCleanUp\OptimiseAssets\OptimizeJs;
use WpAssetCleanUp\Settings;
use WpAssetCleanUp\ThirdParty\Browser;
use WpAssetCleanUp\Update;

/**
 * Class Tools
 * @package WpAssetCleanUp
 */
class Tools
{
	/**
	 * @var string
	 */
	public $wpacuFor = 'system_info';

	/**
	 * Selected subsection within Tools -> Storage.
	 *
	 * @var string
	 */
	public $storageArea = 'generated_files';

	/**
	 * @var array
	 */
	public $errorLogsData = array();

	/**
	 * @var string
	 */
	public $resetChoice;

	/**
	 * @var bool
	 */
	public $licenseDataRemoved = false;

	/**
	 * @var bool
	 */
	public $cachedAssetsRemoved = false;

	/**
	 * Counts and errors collected during the latest reset operation.
	 *
	 * @var array
	 */
	public $resetResult = array();

	/**
	 * @var array
	 */
	public $data = array();

	/**
	 * Tools constructor.
	 */
	public function __construct()
	{
		$wpacuForRaw = Misc::getVar('request', 'wpacu_for', $this->wpacuFor);
		$wpacuFor = is_string($wpacuForRaw) ? sanitize_key(wp_unslash($wpacuForRaw)) : 'system_info';
		$allowedTools = array('reset', 'system_info', 'storage', 'debug', 'import_export');
		$this->wpacuFor = in_array($wpacuFor, $allowedTools, true) ? $wpacuFor : 'system_info';

		if ($this->wpacuFor === 'storage') {
			$storageAreaRaw = Misc::getVar('request', 'wpacu_storage_area', $this->storageArea);
			$storageArea = is_string($storageAreaRaw) ? sanitize_key(wp_unslash($storageAreaRaw)) : 'generated_files';
			$allowedStorageAreas = array('generated_files', 'database_map');
			$this->storageArea = in_array($storageArea, $allowedStorageAreas, true) ? $storageArea : 'generated_files';
		}

		if ($this->wpacuFor === 'debug') {
			$isLogPHPErrors       = @ini_get( 'log_errors' );
			$logPHPErrorsLocation = @ini_get( 'error_log' ) ?: 'none set';

			$this->errorLogsData['log_status'] = $isLogPHPErrors;
			$this->errorLogsData['log_file']   = $logPHPErrorsLocation;
		}
	}

	/**
	 *
	 */
	public function init()
    {
	    add_action('admin_init', array($this, 'onAdminInit'), 1);
    }

	/**
	 *
	 */
	public function onAdminInit()
	{
		if (Misc::getVar('post', 'wpacu-tools-reset')) {
			$this->doReset();
		}

		if (Misc::getVar('post', 'wpacu-get-system-info')) {
			$this->downloadSystemInfo();
		}

		if (Misc::getVar('post', 'wpacu-get-error-log')) {
			$this->downloadErrorLog();
		}

		if (! empty($_POST) && $this->wpacuFor === 'import_export') {
			$wpacuImportExport = new ImportExport();

			// Any import/export action taken? It will reload the page if action is successful
			$wpacuImportExport->doImport();

			// This will download the JSON through the right headers (the user will stay on the same page)
			$wpacuImportExport->doExport();
		}

		if (isset($_GET['page']) && $_GET['page'] === WPACU_PLUGIN_ID. '_tools') {
			// "Import" Failed
			if (Misc::getVar('get', 'wpacu_import_error')) {
				$importErrorMessage = get_transient(WPACU_PLUGIN_ID . '_import_error');

				if (is_string($importErrorMessage) && $importErrorMessage !== '') {
					$this->data['import_error_message'] = $importErrorMessage;
					add_action('wpacu_admin_notices', array($this, 'importError'));
				}

				delete_transient(WPACU_PLUGIN_ID . '_import_error');
			}

			// "Import" Completed
			if (Misc::getVar('get', 'wpacu_import_done') && $resetDoneListArray = get_transient(WPACU_PLUGIN_ID . '_import_done')) {
				if (! is_array($resetDoneListArray)) {
					return;
				}

				$this->data['import_done_list'] = $resetDoneListArray;

				delete_transient(WPACU_PLUGIN_ID . '_import_done');

				// Show the confirmation that the import was completed
				add_action('wpacu_admin_notices', array($this, 'importDone'));
			}

			// "Reset" Completed
			if (Misc::getVar('get', 'wpacu_reset_done') && $resetDoneInfo = get_transient(WPACU_PLUGIN_ID . '_reset_done')) {
				$resetDoneInfoArray = @json_decode($resetDoneInfo, ARRAY_A);

				if (! is_array($resetDoneInfoArray)) {
					return;
				}

				$this->resetChoice         = isset($resetDoneInfoArray['reset_choice']) ? $resetDoneInfoArray['reset_choice'] : '';
				$this->licenseDataRemoved  = isset($resetDoneInfoArray['license_data_removed']) ? $resetDoneInfoArray['license_data_removed'] : '';
				$this->cachedAssetsRemoved = isset($resetDoneInfoArray['cached_assets_removed']) ? $resetDoneInfoArray['cached_assets_removed'] : '';
				$this->resetResult         = isset($resetDoneInfoArray['result']) && is_array($resetDoneInfoArray['result']) ? $resetDoneInfoArray['result'] : array();

				delete_transient(WPACU_PLUGIN_ID . '_reset_done');

				// Show the confirmation that the reset was completed
				add_action('wpacu_admin_notices', array($this, 'resetDone'));
			}

		}
	}

	/**
	 *
	 */
	public function toolsPage()
	{
		$this->data['for'] = $this->wpacuFor;

		if ($this->data['for'] === 'storage') {
			$this->data['storage_area'] = $this->storageArea;

			// Keep the filesystem overview fast and side-effect free from database-map queries.
			// The aggregate database snapshot is built only when its subsection is opened.
			if ($this->storageArea === 'database_map') {
				$this->data['database_storage_map'] = DatabaseStorageMap::getPageData();
			}
		}

		if ($this->data['for'] === 'reset' && ! Misc::getVar('get', 'wpacu_uninstall_cleanup_done')) {
			$pluginsManagerRules = PluginsManagerAdmin::getPluginRulesFiltered(false, true);
			$this->data['has_plugins_manager_front_rules'] = ! empty($pluginsManagerRules['plugins']);
			$this->data['has_plugins_manager_dash_rules']  = ! empty($pluginsManagerRules['plugins_dash']);
		}

		if ($this->data['for'] === 'debug') {
			$this->data['error_log'] = $this->errorLogsData;
		}

		MainAdmin::instance()->parseTemplate('admin-page-tools', $this->data, true);
	}

	/**
	 * @return string
	 */
	public function maybeGetHost()
    {
        $dbHost     = defined('DB_HOST') ? DB_HOST : '';
        $serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';

        if (defined('WPE_APIKEY')) {
            $host = 'WP Engine';
        } elseif (defined('PAGELYBIN')) {
            $host = 'Pagely';
        } elseif ($dbHost === 'localhost:/tmp/mysql5.sock') {
            $host = 'ICDSoft';
        } elseif ($dbHost === 'mysqlv5') {
            $host = 'NetworkSolutions';
        } elseif (strpos($dbHost, 'ipagemysql.com') !== false) {
            $host = 'iPage';
        } elseif (strpos($dbHost, 'ipowermysql.com') !== false) {
            $host = 'IPower';
        } elseif (strpos($dbHost, '.gridserver.com') !== false) {
            $host = 'MediaTemple Grid';
        } elseif (strpos($dbHost, '.pair.com') !== false) {
            $host = 'pair Networks';
        } elseif (strpos($dbHost, '.stabletransit.com') !== false) {
            $host = 'Rackspace Cloud';
        } elseif (strpos($dbHost, '.sysfix.eu') !== false) {
            $host = 'SysFix.eu Power Hosting';
        } elseif (strpos($serverName, 'Flywheel') !== false) {
            $host = 'Flywheel';
        } else {
            // Fallback
            $host = 'DBH: ' . ($dbHost !== '' ? $dbHost : 'Not set') . ', SRV: ' . ($serverName !== '' ? $serverName : 'Not set');
        }

	    return $host;
    }

	/**
	 * @return string
     *
     * @noinspection PhpUndefinedConstantInspection
     * @noinspection PhpUndefinedFieldInspection
     */
	public function getSystemInfo($includeSensitiveData = false)
    {
	    global $wpdb;

	    $return = '### Begin System Info ###' . "\n";
	    $return .= 'Report mode:              ' . ($includeSensitiveData ? 'Detailed (contains sensitive environment data)' : 'Redacted (review before sharing)') . "\n";
		    $return .= 'Sharing notice:           Asset CleanUp rules and metadata are included and can contain custom URLs, paths or identifiers.' . "\n";
	    $return .= 'Generated:                ' . gmdate('c') . "\n";

	    $return .= "\n" . '# Site Info' . "\n";
	    $return .= 'Site URL:                  ' . self::redactUrl(site_url(), $includeSensitiveData) . "\n";
	    $return .= 'Home URL:                  ' . self::redactUrl(home_url(), $includeSensitiveData) . "\n";
	    $return .= 'Multisite:                 ' . ( is_multisite() ? 'Yes' : 'No' ) . "\n";

	    $host = $this->maybeGetHost();
	    $browser = new Browser();

	    if ($host) {
		    $return .= "\n" . '# Hosting Provider' . "\n";
		    $return .= 'Host: ' . ($includeSensitiveData ? $host : '[redacted]') . "\n";
	    }

	    if ($browser) {
		    $return .= "\n" . '# User Browser' . "\n";
		    $return .= 'Browser: ' . $browser->getBrowser() . ' ' . $browser->getVersion() . "\n";
		    $return .= 'Platform: ' . $browser->getPlatform() . "\n";
		    if ($includeSensitiveData) {
		        $return .= 'User Agent: ' . $browser->getUserAgent() . "\n";
		    }
        }

	    // WordPress' configuration.
	    // Get theme info.
        $themeData = wp_get_theme();
	    $theme      = $themeData->get('Name') . ' ' . $themeData->get('Version');

	    $return .= "\n" . '# WordPress Configuration' . "\n";
	    $return .= 'Version:                   ' . get_bloginfo( 'version' ) . "\n";

	    $return .= 'Language:                  ' . get_locale() . "\n";

        $return .= 'Permalink Structure:       ' . ( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'Default' ) . "\n";
	    $return .= 'Active Theme:              ' . $theme . "\n";
	    $return .= 'Show On Front:             ' . get_option( 'show_on_front' ) . "\n";

	    // Only show page specs if front page is set to 'page'.
	    if ( get_option( 'show_on_front' ) === 'page' ) {
		    $front_page_id = get_option( 'page_on_front' );
		    $blog_page_id  = get_option( 'page_for_posts' );

		    $return .= 'Page On Front:             ' . ( 0 != $front_page_id ? get_the_title( $front_page_id ) . ' (ID: ' . $front_page_id . ')' : 'Unset' ) . "\n";
		    $return .= 'Page For Posts:            ' . ( 0 != $blog_page_id ? get_the_title( $blog_page_id ) . ' (ID: ' . $blog_page_id . ')' : 'Unset' ) . "\n";
	    }

	    $return .= 'ABSPATH:                   ' . self::redactPath(ABSPATH, $includeSensitiveData) . "\n";
	    $return .= 'WP_DEBUG:                  ' . ( defined( 'WP_DEBUG' ) ? (WP_DEBUG ? 'Enabled' : 'Disabled') : 'Not set' ) . "\n";

        $wpMemoryLimit = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : ini_get('memory_limit');
        $return .= 'Memory Limit:              ' . $wpMemoryLimit . "\n";

	    $return .= "\n" . '# WordPress Uploads/Constants' . "\n";

        $wpContentUrl = function_exists('content_url') ? content_url() : 'Not set';
        $uploadDir    = function_exists('wp_get_upload_dir') ? wp_get_upload_dir() : array();

	    $return .= 'WP_CONTENT_DIR:               ' . ( defined('WP_CONTENT_DIR') && WP_CONTENT_DIR ? self::redactPath(WP_CONTENT_DIR, $includeSensitiveData) : 'Not set' ) . "\n";

	    $return .= 'content_url():                ' . self::redactUrl($wpContentUrl, $includeSensitiveData) . "\n";

	    $return .= 'wp_get_upload_dir()[basedir]: ' . (! empty($uploadDir['basedir']) ? self::redactPath($uploadDir['basedir'], $includeSensitiveData) : 'Not set') . "\n";
	    $return .= 'wp_get_upload_dir()[baseurl]: ' . (! empty($uploadDir['baseurl']) ? self::redactUrl($uploadDir['baseurl'], $includeSensitiveData) : 'Not set') . "\n";

	    $return .= 'FS_CHMOD_DIR:                 ' . (defined('FS_CHMOD_DIR') ? FS_CHMOD_DIR : 'Not set') . "\n";
	    $return .= 'FS_CHMOD_FILE:                ' . (defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 'Not set') . "\n";

	    $uploads_dir = wp_upload_dir();

	    $return .= 'wp_uploads_dir() path:     ' . self::redactPath(isset($uploads_dir['path']) ? $uploads_dir['path'] : 'Not set', $includeSensitiveData) . "\n";
	    $return .= 'wp_uploads_dir() url:      ' . self::redactUrl(isset($uploads_dir['url']) ? $uploads_dir['url'] : 'Not set', $includeSensitiveData) . "\n";
	    $return .= 'wp_uploads_dir() basedir:  ' . self::redactPath(isset($uploads_dir['basedir']) ? $uploads_dir['basedir'] : 'Not set', $includeSensitiveData) . "\n";
	    $return .= 'wp_uploads_dir() baseurl:  ' . self::redactUrl(isset($uploads_dir['baseurl']) ? $uploads_dir['baseurl'] : 'Not set', $includeSensitiveData) . "\n";

	    // Get plugins that have an update.
	    $updates = get_plugin_updates();

	    // Must-use plugins.
	    // NOTE: MU plugins can't show updates!
	    $muplugins = get_mu_plugins();
	    if ( ! empty( $muplugins ) && count( $muplugins ) > 0 ) {
		    $return .= "\n" . '# Must-Use Plugins ("mu-plugins" directory)' . "\n";

		    foreach ( $muplugins as $plugin_data ) {
			    $return .= $plugin_data['Name'] . ': ' . $plugin_data['Version'] . "\n";
		    }
	    }

	    // WordPress active plugins.
	    $return .= "\n" . '# Active Plugins ("plugins" directory)' . "\n";

	    $plugins        = get_plugins();
	    $active_plugins = get_option( 'active_plugins', array() );

	    foreach ( $plugins as $plugin_path => $plugin ) {
		    if ( ! in_array( $plugin_path, $active_plugins, true ) ) {
			    continue;
		    }
		    $update  = array_key_exists($plugin_path, $updates) ? ' (new version available - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
		    $return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
	    }

	    // WordPress inactive plugins.
	    $return .= "\n" . '# Inactive Plugins ("plugins" directory)' . "\n";

	    foreach ( $plugins as $plugin_path => $plugin ) {
		    if ( in_array( $plugin_path, $active_plugins, true ) ) {
			    continue;
		    }
		    $update  = array_key_exists($plugin_path, $updates) ? ' (new version available - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';
		    $return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
	    }

	    if ( is_multisite() ) {
		    // WordPress Multisite active plugins.
		    $return .= "\n" . '# Network Active Plugins' . "\n";

		    $plugins        = wp_get_active_network_plugins();
		    $active_plugins = get_site_option( 'active_sitewide_plugins', array() );

		    foreach ( $plugins as $plugin_path ) {
			    $plugin_base = plugin_basename( $plugin_path );
			    if ( ! array_key_exists( $plugin_base, $active_plugins ) ) {
				    continue;
			    }
			    $update  = array_key_exists($plugin_base, $updates) ? ' (new version available - ' . $updates[ $plugin_base ]->update->new_version . ')' : '';
			    $plugin  = get_plugin_data( $plugin_path );
			    $return .= $plugin['Name'] . ': ' . $plugin['Version'] . $update . "\n";
		    }
	    }

	    // Server configuration (really just versions).
	    $return .= "\n" . '# Webserver Configuration' . "\n";
	    $return .= 'PHP Version:              ' . PHP_VERSION . "\n";
	    $return .= 'MySQL Version:            ' . $wpdb->db_version() . "\n";
	    $return .= 'Webserver Info:           ' . (isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Not available') . "\n";
	    $return .= 'PHP SAPI:                 ' . PHP_SAPI . "\n";
	    $return .= 'HTTPS:                    ' . (is_ssl() ? 'Yes' : 'No') . "\n";

	    // PHP important configuration taken from php.ini
	    $return .= "\n" . '# PHP Configuration' . "\n";
	    $return .= 'Memory Limit:             ' . ini_get( 'memory_limit' ) . "\n";
	    $return .= 'Upload Max Size:          ' . ini_get( 'upload_max_filesize' ) . "\n";
	    $return .= 'Post Max Size:            ' . ini_get( 'post_max_size' ) . "\n";
	    $return .= 'Effective Upload Limit:   ' . size_format(wp_max_upload_size()) . "\n";
	    $return .= 'Time Limit:               ' . ini_get( 'max_execution_time' ) . "\n";
	    $return .= 'Max Input Vars:           ' . ini_get( 'max_input_vars' ) . "\n";
	    $return .= 'Display Errors:           ' . ( ini_get( 'display_errors' ) ? 'On (php.ini value: ' . ini_get( 'display_errors' ) . ')' : 'N/A' ) . "\n";

	    // PHP extensions and such.
	    $return .= "\n" . '# PHP Extensions' . "\n";
	    $return .= 'cURL:                     ' . ( function_exists( 'curl_init' ) ? 'Supported' : 'Not Supported' ) . "\n";
	    $return .= 'fsockopen:                ' . ( function_exists( 'fsockopen' ) ? 'Supported' : 'Not Supported' ) . "\n";
	    $return .= 'SOAP Client:              ' . ( class_exists( 'SoapClient' ) ? 'Installed' : 'Not Installed' ) . "\n";
	    $return .= 'OPcache:                  ' . ( extension_loaded('Zend OPcache') ? 'Installed' : 'Not Installed' ) . "\n";

	    // Session stuff.
	    $return .= "\n" . '# Session Configuration' . "\n";
	    $return .= 'Session:                  ' . ( isset( $_SESSION ) ? 'Enabled' : 'Disabled' ) . "\n";

	    // The rest of this is only relevant if session is enabled.
	    if ( isset( $_SESSION ) ) {
		    $return .= 'Session Name:             ' . esc_html( ini_get( 'session.name' ) ) . "\n";
		    $return .= 'Cookie Path:              ' . esc_html( ini_get( 'session.cookie_path' ) ) . "\n";
		    $return .= 'Save Path:                ' . esc_html( ini_get( 'session.save_path' ) ) . "\n";
		    $return .= 'Use Cookies:              ' . ( ini_get( 'session.use_cookies' ) ? 'On' : 'Off' ) . "\n";
		    $return .= 'Use Only Cookies:         ' . ( ini_get( 'session.use_only_cookies' ) ? 'On' : 'Off' ) . "\n";
	    }

	    $return .= "\n" . '# '.WPACU_PLUGIN_TITLE.' Configuration '. "\n";

	    $settingsClass = new Settings();
	    $settings = $settingsClass->getAll();

	    $globalUnloadList = Main::instance()->getGlobalUnload();

	    if (in_array('wp-block-library', $globalUnloadList['styles'])) {
		    $settings['disable_wp_block_library'] = 1;
	    }

	    if (in_array('jquery-migrate', $globalUnloadList['scripts'])) {
		    $settings['disable_jquery_migrate'] = 1;
	    }

	    if (in_array('comment-reply', $globalUnloadList['scripts'])) {
		    $settings['disable_comment_reply'] = 1;
	    }

	    $return .= 'Has read "Stripping the fat" text:   '. (($settings['wiki_read'] == 1) ? 'Yes' : 'No') . "\n\n";

	    $return .= 'Manage in the Dashboard:             '. (($settings['dashboard_show'] == 1) ? 'Yes ('.$settings['dom_get_type'].')' : 'No');

	    if ( ! (isset($settings['show_assets_meta_box']) && $settings['show_assets_meta_box']) ) {
		    $return .= ' - Assets Meta Box is Hidden';
	    }

	    if ( isset($settings['hide_options_meta_box']) && $settings['hide_options_meta_box'] ) {
		    $return .= ' - Side Options Meta Box is Hidden';
	    }

	    $return .= "\n";

	    $return .= 'Manage in the Front-end:             '. (($settings['frontend_show'] == 1) ? 'Yes' : 'No') . "\n";

	    if ($settings['frontend_show'] == 1 && $settings['frontend_show_exceptions']) {
		    $return .= 'Do not show front-end assets when the request URI contains: ' . ($includeSensitiveData ? "\n" . $settings['frontend_show_exceptions'] : '[value omitted]') . "\n\n";
	    }

	    $return .= 'Input Fields Style:                  '. ucfirst(Settings::getInputStyle($settings))."\n";
	    $return .= 'Hide WP Files (from managing):       '. (($settings['hide_core_files'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Enable "Test Mode"?                  '. (($settings['test_mode'] == 1) ? 'Yes' : 'No') . "\n\n";

	    $return .= 'Minify loaded CSS?                   '. (($settings['minify_loaded_css'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Minify loaded JS?                    '. (($settings['minify_loaded_js'] == 1) ? 'Yes' : 'No') . "\n";

	    $return .= 'Combine loaded CSS?                  '. (($settings['combine_loaded_css'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Combine loaded JS?                   '. (($settings['combine_loaded_js'] == 1) ? 'Yes' : 'No') . "\n";

	    $storageCssJsDir = WP_CONTENT_DIR . OptimizeCommon::getRelPathPluginCacheDir();
	    $return .= 'CSS/JS Storage Directory:            '. self::redactPath($storageCssJsDir, $includeSensitiveData) . ' ('.(is_writable($storageCssJsDir) ? 'writable' : 'NON WRITABLE').')' ."\n\n";

	    $return .= 'Disable Emojis (site-wide)?                       '. (($settings['disable_emojis'] == 1) ? 'Yes' : 'No') . "\n";
        $return .= 'Disable oEmbed (Embeds) (site-wide)?              '. (($settings['disable_oembed'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Disable Dashicons if Toolbar (top admin bar) is not showing (site-wide)?         '. (($settings['disable_dashicons_for_guests'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Disable Gutenberg CSS Block Editor (site-wide)?   '. (($settings['disable_wp_block_library'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Disable jQuery Migrate (site-wide)?               '. (($settings['disable_jquery_migrate'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Disable Comment Reply (site-wide)?                '. (($settings['disable_comment_reply'] == 1) ? 'Yes' : 'No') . "\n\n";

	    $return .= 'Remove "Really Simple Discovery (RSD)" link tag?  '. (($settings['remove_rsd_link'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Remove "Windows Live Writer" link tag?            '. (($settings['remove_wlw_link'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Remove "REST API" link tag?                       '. (($settings['remove_rest_api_link'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Remove Pages/Posts "Shortlink" tag?               '. (($settings['remove_shortlink'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Remove "Post\'s Relational Links" tag?             '. (($settings['remove_posts_rel_links'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Remove "WordPress version" meta tag?              '. (($settings['remove_wp_version'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Remove All "generator" meta tags?                 '. (($settings['remove_generator_tag'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Remove Main RSS Feed Link?                        '. (($settings['remove_main_feed_link'] == 1) ? 'Yes' : 'No') . "\n";
	    $return .= 'Remove Comment RSS Feed Link?                     '. (($settings['remove_comment_feed_link'] == 1) ? 'Yes' : 'No') . "\n";

	    $xmlProtocolStatus = 'Enabled (default)';

	    if ($settings['disable_xmlrpc'] === 'disable_pingback') {
		    $xmlProtocolStatus = 'Disable XML-RPC Pingback Only';
	    } elseif ($settings['disable_xmlrpc'] === 'disable_all') {
		    $xmlProtocolStatus = 'Disable XML-RPC Completely';
	    }

	    $return .= "\n" . 'XML-RPC protocol: '. $xmlProtocolStatus . "\n";

	    $return .= "\n" . '# '.WPACU_PLUGIN_TITLE.': CSS/JS Cache Storage'. "\n";

	    $storageStats = OptimizeCommon::getStorageStats(false);

	    if (isset($storageStats['total_size'], $storageStats['total_files'])) {
		    $return .= 'Total cached files: '.$storageStats['total_files'].' ('.$storageStats['total_size'].') of which '.$storageStats['total_files_assets'].' are CSS/JS assets ('.$storageStats['total_size_assets'].')';
	    } else {
		    $return .= 'Not used';
        }

	    $return .= "\n\n" . '# '.WPACU_PLUGIN_TITLE.': Database Storage';

	    $wpacuPluginId = WPACU_PLUGIN_ID;

	    $optionNamePattern = $wpdb->esc_like($wpacuPluginId . '_') . '%';
	    $licenseOptionName = $wpacuPluginId . '_pro_license_key';
	    $sqlQueryGetOptions = $wpdb->prepare(
	        "SELECT option_name, option_value FROM `{$wpdb->options}` WHERE option_name LIKE %s AND option_name <> %s ORDER BY option_name ASC",
	        $optionNamePattern,
	        $licenseOptionName
	    );
	    $wpacuOptions = $wpdb->get_results($sqlQueryGetOptions, ARRAY_A);

	    $return .= "\n" . 'Table: options'."\n";

	    if (! empty($wpacuOptions)) {
		    foreach ($wpacuOptions as $wpacuOption) {
		        $optionValue = self::redactSensitiveOptionValue($wpacuOption['option_name'], $wpacuOption['option_value']);
			    $return .= '-- Option Name: ' . $wpacuOption['option_name'] . ' / Option Value: ' . $optionValue . "\n";
		    }
        } else {
		    $return .= 'No records'."\n";
        }

	    // `usermeta` and `termmeta` might have traces from the Pro version (if ever used)
	    foreach (array('postmeta', 'usermeta', 'termmeta') as $tableBaseName) {
		    // Get all Asset CleanUp (Pro) meta keys from all WordPress meta tables where it can be possibly used
		    $tableName = $wpdb->prefix . $tableBaseName;
		    $metaKeyPattern = $wpdb->esc_like('_' . $wpacuPluginId . '_') . '%';
		    $wpacuMetaCount = (int) $wpdb->get_var($wpdb->prepare(
		        "SELECT COUNT(*) FROM `{$tableName}` WHERE meta_key LIKE %s",
		        $metaKeyPattern
		    ));
		    $wpacuMetaResults = array();

		    if ($wpacuMetaCount > 0) {
		        $wpacuMetaResults = $wpdb->get_results($wpdb->prepare(
		            "SELECT * FROM `{$tableName}` WHERE meta_key LIKE %s ORDER BY meta_id ASC",
		            $metaKeyPattern
		        ), ARRAY_A);
		    }

		    $return .= "\n" . 'Table: '.$tableBaseName.' / Matching records: '.$wpacuMetaCount."\n";

		    if (! empty($wpacuMetaResults)) {
			    foreach ($wpacuMetaResults as $metaResult) {
				    $rowIdVal = '';

			        if (isset($metaResult['post_id'])) {
				        $rowIdVal = 'Post ID: '.$metaResult['post_id'];
                    } elseif (isset($metaResult['user_id'])) {
				        $rowIdVal = 'User ID: '.$metaResult['user_id'];
			        } elseif (isset($metaResult['term_id']) && term_exists((int)$metaResult['term_id'])) {
			            $term = get_term($metaResult['term_id']);
				        $rowIdVal = 'Taxonomy Name: '.$term->taxonomy.'; Taxonomy ID: '.$metaResult['term_id'];
			        }

			        $metaValue = $metaResult['meta_value'];

			        if (trim($metaValue) === '[]') { // empty, not relevant
			            continue;
                    }

			    if (preg_match('/(?:license|secret|token|password|api[_-]?key)/i', $metaResult['meta_key'])) {
			        $metaValue = '[redacted]';
			    }
			    $return .= '-- ' . $rowIdVal . ' / Meta Key: ' . $metaResult['meta_key'] . ' / Meta Value: ' . $metaValue . "\n";
			    }
		    } else {
			    $return .= 'No records'."\n";
            }
	    }

	    $return .= "\n" . '### End System Info ###';

	    return $return;
    }

	private static function redactUrl($url, $includeSensitiveData)
	{
		if ($includeSensitiveData || $url === 'Not set') {
			return $url;
		}

		$parts = wp_parse_url($url);
		$path = isset($parts['path']) ? $parts['path'] : '/';

		return '[redacted-host]' . $path;
	}

	private static function redactPath($path, $includeSensitiveData)
	{
		if ($includeSensitiveData || $path === 'Not set') {
			return $path;
		}

		return '[redacted-path]/' . basename(untrailingslashit($path));
	}

	private static function redactSensitiveOptionValue($optionName, $optionValue)
	{
		if (preg_match('/(?:license|secret|token|password|api[_-]?key)/i', $optionName)) {
			return '[redacted]';
		}

		$decodedValue = json_decode($optionValue, true);

		if (! is_array($decodedValue)) {
			return $optionValue;
		}

		$decodedValue = self::redactSensitiveArrayValues($decodedValue);

		return wp_json_encode($decodedValue);
	}

	private static function redactSensitiveArrayValues($values)
	{
		foreach ($values as $key => $value) {
			if (preg_match('/(?:license|secret|token|password|api[_-]?key)/i', (string) $key)) {
				$values[$key] = '[redacted]';
			} elseif (is_array($value)) {
				$values[$key] = self::redactSensitiveArrayValues($value);
			}
		}

		return $values;
	}

	/**
	 * @param $maybeJsonValue
	 *
	 * @return false|mixed|string|void
	 */
	public static function stripKeysWithNoValues($maybeJsonValue)
    {
	    $arrayFromJson = @json_decode($maybeJsonValue, true);

	    if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
		    return $maybeJsonValue;
	    }

	    if (is_array($arrayFromJson) && ! empty($arrayFromJson)) {
	        foreach ($arrayFromJson as $key => $value) {
	            if (! $value && empty($value)) {
	                unset($arrayFromJson[$key]);
                }
            }
        }

	    return wp_json_encode($arrayFromJson);
    }

	/**
	 * Download the configured PHP error log after capability and nonce checks.
	 *
	 * @return void
	 */
	private function downloadErrorLog()
	{
		if (! Menu::userCanAccessPlugin()) {
			wp_die(esc_html__('You are not allowed to download the PHP error log.', 'wp-asset-clean-up'), '', array('response' => 403));
		}

		if (! Misc::getVar('post', 'wpacu_get_error_log_nonce')) {
			return;
		}

		\check_admin_referer('wpacu_get_error_log', 'wpacu_get_error_log_nonce');

		$configuredLogFile = isset($this->errorLogsData['log_file']) && is_string($this->errorLogsData['log_file'])
			? $this->errorLogsData['log_file']
			: (string) @ini_get('error_log');

		self::downloadFile($configuredLogFile);
	}

	/**
	 * e.g. error_log file for debugging purposes
	 *
	 * @param string $localPathToFile
	 *
	 * @return void
	 */
	public static function downloadFile($localPathToFile)
	{
		if (! Menu::userCanAccessPlugin()) {
			wp_die(esc_html__('You are not allowed to download this file.', 'wp-asset-clean-up'), '', array('response' => 403));
		}

		$localPathToFile = is_string($localPathToFile) ? trim($localPathToFile) : '';
		$realFilePath = $localPathToFile !== '' ? realpath($localPathToFile) : false;

		if ($realFilePath === false || ! is_file($realFilePath) || ! is_readable($realFilePath)) {
			wp_die(esc_html__('The PHP error log is not available or cannot be read.', 'wp-asset-clean-up'), '', array('response' => 404));
		}

		$date = gmdate('j-M-Y');
		$host = sanitize_file_name((string) wp_parse_url(site_url(), PHP_URL_HOST));

		nocache_headers();
		header('Content-Type: text/plain; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');
		header('Content-Disposition: attachment; filename="'.$host.'-website-errors-'.$date.'.log"');

		readfile($realFilePath);
		exit();
	}

	/**
	 *
	 */
	public function downloadSystemInfo()
    {
	    if (! Menu::userCanAccessPlugin()) {
		    exit();
	    }

	    if (! Misc::getVar('post', 'wpacu_get_system_info_nonce')) {
	        return;
        }

	    \check_admin_referer('wpacu_get_system_info', 'wpacu_get_system_info_nonce');

	    $includeSensitiveData = Misc::getVar('post', 'wpacu_include_sensitive_system_info') === '1';
	    $date = gmdate('j-M-Y');
	    $host = sanitize_file_name((string) wp_parse_url(site_url(), PHP_URL_HOST));

	    nocache_headers();
	    header('Content-Type: text/plain; charset=UTF-8');
	    header('X-Content-Type-Options: nosniff');
	    header('Content-Disposition: attachment; filename="'.str_replace(' ', '-', strtolower(WPACU_PLUGIN_TITLE)).'-system-info-'.$host.'-'.$date.'.txt"');

	    echo $this->getSystemInfo($includeSensitiveData);
	    exit();
    }

	/**
	 *
	 */
	public function doReset()
	{
		// Several security checks before proceeding with the chosen action
		if ( ! Menu::userCanAccessPlugin() ) {
			wp_die(esc_html__('You are not allowed to reset Asset CleanUp data.', 'wp-asset-clean-up'), '', array('response' => 403));
		}

		\check_admin_referer('wpacu_tools_reset', 'wpacu_tools_reset_nonce');

		$wpacuResetValueRaw = Misc::getVar('post', 'wpacu-reset', false);
		$wpacuResetValue = is_string($wpacuResetValueRaw) ? sanitize_key(wp_unslash($wpacuResetValueRaw)) : '';
		$allowedResetValues = array('reset_settings', 'reset_critical_css', 'reset_plugins_manager_front', 'reset_plugins_manager_dash', 'reset_everything_except_settings', 'reset_everything', 'remove_all_data_for_uninstall');

		if ( ! in_array($wpacuResetValue, $allowedResetValues, true) ) {
			wp_die(esc_html__('The selected reset action is not valid.', 'wp-asset-clean-up'), '', array('response' => 400));
		}

		// Has to be confirmed
		$wpacuConfirmedValue = Misc::getVar('post', 'wpacu-action-confirmed', false);

		if ( $wpacuConfirmedValue !== 'yes' ) {
			wp_die(esc_html__('The reset action needs to be confirmed.', 'wp-asset-clean-up'), '', array('response' => 400));
		}

		global $wpdb;

		$this->resetResult = array(
			'deleted_options'      => 0,
			'deleted_meta_keys'    => 0,
			'deleted_transients'   => 0,
			'deleted_cache_files'  => 0,
			'failed_cache_files'   => 0,
			'errors'               => array(),
		);

		$this->resetChoice = $wpacuResetValue;

		$wpacuPluginId = WPACU_PLUGIN_ID;
		$preservedCommonUnloads = array();

		if ($wpacuResetValue === 'reset_everything_except_settings') {
			$globalUnloadBeforeReset = Main::instance()->getGlobalUnload();
			$globalUnloadStyles = isset($globalUnloadBeforeReset['styles']) && is_array($globalUnloadBeforeReset['styles'])
				? $globalUnloadBeforeReset['styles']
				: array();
			$globalUnloadScripts = isset($globalUnloadBeforeReset['scripts']) && is_array($globalUnloadBeforeReset['scripts'])
				? $globalUnloadBeforeReset['scripts']
				: array();

			$preservedCommonUnloads = array(
				'wp_block_library' => in_array('wp-block-library', $globalUnloadStyles, true),
				'dashicons'        => in_array('dashicons', $globalUnloadStyles, true),
				'jquery_migrate'   => in_array('jquery-migrate', $globalUnloadScripts, true),
				'comment_reply'    => in_array('comment-reply', $globalUnloadScripts, true),
			);
		}

		if ($wpacuResetValue === 'reset_settings') {
			if (delete_option($wpacuPluginId.'_settings')) {
				$this->resetResult['deleted_options']++;
			}

	            $wpacuSettingsAdmin = new SettingsAdmin();
	            $wpacuSettingsAdmin->updateSettingsInDbWithDefaultValues();

			if (! get_option($wpacuPluginId.'_settings')) {
				$this->resetResult['errors'][] = __('The default settings could not be recreated.', 'wp-asset-clean-up');
			}
		}

        if ($wpacuResetValue === 'reset_critical_css') {
		    $criticalCssOptionPattern = $wpdb->esc_like($wpacuPluginId . '_critical_css_') . '%';
		    $wpacuGetAllCriticalCssOptions = $wpdb->prepare(
		        "SELECT option_name FROM `{$wpdb->options}` WHERE option_name LIKE %s",
		        $criticalCssOptionPattern
		    );
			$wpacuAnyCriticalCssOptions = $wpdb->get_col($wpacuGetAllCriticalCssOptions);

			if (! empty($wpacuAnyCriticalCssOptions)) {
			    foreach ($wpacuAnyCriticalCssOptions as $wpacuCriticalCssOption) {
			        if (delete_option($wpacuCriticalCssOption)) {
			            $this->resetResult['deleted_options']++;
			        }
                }
            }

            $criticalCssMetaKey = CriticalCss::getMetaKey();

            foreach (array('post', 'term', 'user') as $metaType) {
                if (delete_metadata($metaType, 0, $criticalCssMetaKey, '', true)) {
                    $this->resetResult['deleted_meta_keys']++;
                }
            }

            if (! empty($wpdb->get_col($wpacuGetAllCriticalCssOptions))) {
                $this->resetResult['errors'][] = __('Some Critical CSS options could not be removed.', 'wp-asset-clean-up');
            }

            foreach (array($wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta) as $metaTableName) {
                $remainingCriticalCssMeta = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$metaTableName}` WHERE meta_key = %s",
                    $criticalCssMetaKey
                ));
                if ($remainingCriticalCssMeta > 0) {
                    $this->resetResult['errors'][] = __('Some Critical CSS metadata could not be removed.', 'wp-asset-clean-up');
                    break;
                }
            }
        }

		if (in_array($wpacuResetValue, array('reset_plugins_manager_front', 'reset_plugins_manager_dash'), true)) {
			$locationKey = $wpacuResetValue === 'reset_plugins_manager_front' ? 'plugins' : 'plugins_dash';
			$globalData = wpacuGetGlobalData();
			$storedRules = isset($globalData[$locationKey]) && is_array($globalData[$locationKey])
				? $globalData[$locationKey]
				: array();

			unset($globalData[$locationKey]);
			Misc::addUpdateOption($wpacuPluginId . '_global_data', wp_json_encode($globalData));
			$this->resetResult['deleted_plugin_rules'] = count($storedRules);

			$updatedGlobalData = wpacuGetGlobalData();
			if ( ! empty($updatedGlobalData[$locationKey])) {
				$this->resetResult['errors'][] = __('Some Plugins Manager rules could not be removed.', 'wp-asset-clean-up');
			}
		}

		if (in_array($wpacuResetValue, array('reset_everything', 'reset_everything_except_settings', 'remove_all_data_for_uninstall'), true)) {
			$preserveNetworkSharedUserMeta = self::isPluginActiveElsewhereInNetwork();
			$metaTables = array(
				'postmeta' => $wpdb->postmeta,
				'usermeta' => $wpdb->usermeta,
				'termmeta' => $wpdb->termmeta,
			);

			// `usermeta` and `termmeta` might have traces from the Pro version (if ever used)
			foreach (array('postmeta', 'usermeta', 'termmeta') as $tableBaseName) {
				// User meta is shared by the whole network and WPACU's keys do not identify a blog.
				if ($tableBaseName === 'usermeta' && $preserveNetworkSharedUserMeta) {
					continue;
				}

			    // Get all Asset CleanUp (Pro) meta keys from all WordPress meta tables where it can be possibly used
				$tableName = $metaTables[$tableBaseName];
				$metaKeyPattern = $wpdb->esc_like('_' . $wpacuPluginId . '_') . '%';
				$wpacuGetMetaKeysQuery = $wpdb->prepare(
					"SELECT DISTINCT meta_key FROM `{$tableName}` WHERE meta_key LIKE %s",
					$metaKeyPattern
				);
				$wpacuMetaKeys = $wpdb->get_col($wpacuGetMetaKeysQuery);

				$metaTypeByTable = array('postmeta' => 'post', 'usermeta' => 'user', 'termmeta' => 'term');
				$metaType = $metaTypeByTable[$tableBaseName];

				foreach ($wpacuMetaKeys as $wpacuMetaKey) {
					if (delete_metadata($metaType, 0, $wpacuMetaKey, '', true)) {
						$this->resetResult['deleted_meta_keys']++;
					}
				}

                if (! empty($wpdb->get_col($wpacuGetMetaKeysQuery))) {
                    $this->resetResult['errors'][] = sprintf(
                        __('Some Asset CleanUp metadata could not be removed from the %s table.', 'wp-asset-clean-up'),
                        $tableBaseName
                    );
                }
			}

			$removeLicenseData = $wpacuResetValue === 'remove_all_data_for_uninstall'
				|| ($wpacuResetValue === 'reset_everything' && Misc::getVar('post', 'wpacu-remove-license-data') !== '');
			$optionNamePattern = $wpdb->esc_like($wpacuPluginId . '_') . '%';
			$sqlQueryGetOptions = $wpdb->prepare(
				"SELECT option_name FROM `{$wpdb->options}` WHERE option_name LIKE %s",
				$optionNamePattern
			);
			$wpacuOptionNames = $wpdb->get_col($sqlQueryGetOptions);

			foreach ($wpacuOptionNames as $wpacuOptionName) {
				$isSettingsOption = $wpacuOptionName === $wpacuPluginId . '_settings';
				$isLicenseOption = strpos($wpacuOptionName, $wpacuPluginId . '_pro_license_') === 0;

				if ($wpacuResetValue === 'reset_everything_except_settings' && $isSettingsOption) {
					continue;
				}

				if ($isLicenseOption && ! $removeLicenseData) {
					continue;
				}

			    if (delete_option($wpacuOptionName)) {
			    	$this->resetResult['deleted_options']++;

			    	if ($isLicenseOption) {
			    		$this->licenseDataRemoved = true;
			    	}
			    }
            }

            $remainingOptionNames = $wpdb->get_col($sqlQueryGetOptions);

            foreach ($remainingOptionNames as $remainingOptionName) {
                $isPreservedSettings = $wpacuResetValue === 'reset_everything_except_settings' && $remainingOptionName === $wpacuPluginId . '_settings';
                $isPreservedLicense = ! $removeLicenseData && strpos($remainingOptionName, $wpacuPluginId . '_pro_license_') === 0;

                if (! $isPreservedSettings && ! $isPreservedLicense) {
                    $this->resetResult['errors'][] = __('Some Asset CleanUp options could not be removed.', 'wp-asset-clean-up');
                    break;
                }
            }

			// Remove transients
			$transientPatterns = array(
				$wpdb->esc_like('_transient_' . $wpacuPluginId . '_') . '%',
				$wpdb->esc_like('_transient_timeout_' . $wpacuPluginId . '_') . '%',
				$wpdb->esc_like('_transient_wpacu_') . '%',
				$wpdb->esc_like('_transient_timeout_wpacu_') . '%',
			);

			$sqlQueryGetTransients = $wpdb->prepare(
				"SELECT option_name FROM `{$wpdb->options}` WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$transientPatterns[0],
				$transientPatterns[1],
				$transientPatterns[2],
				$transientPatterns[3]
			);

			$wpacuTransientNames = $wpdb->get_col($sqlQueryGetTransients);

			$wpacuTransientNames = array_unique(array_map(static function($optionName) {
				return str_replace(array('_transient_timeout_', '_transient_'), '', $optionName);
			}, $wpacuTransientNames));

			foreach ($wpacuTransientNames as $wpacuTransientName) {
				delete_transient($wpacuTransientName);
				wp_cache_delete($wpacuTransientName, 'transient');
				$this->resetResult['deleted_transients']++;
			}

			// Remove all cached CSS/JS files?
			if ($wpacuResetValue === 'remove_all_data_for_uninstall') {
				$cacheResult = $this->removePluginCacheDirectory();
				$this->resetResult['deleted_cache_files'] = $cacheResult['deleted'];
				$this->resetResult['failed_cache_files'] = $cacheResult['failed'];
				$this->cachedAssetsRemoved = $cacheResult['failed'] === 0;

				if ($cacheResult['failed'] > 0) {
					$this->resetResult['errors'][] = sprintf(
						_n('%d cached file or directory could not be removed.', '%d cached files or directories could not be removed.', $cacheResult['failed'], 'wp-asset-clean-up'),
						$cacheResult['failed']
					);
				}
			} elseif ($wpacuResetValue === 'reset_everything' && Misc::getVar('post', 'wpacu-remove-cache-assets') !== '') {
				$cacheResult = $this->removeCachedAssetFiles();
				$this->resetResult['deleted_cache_files'] = $cacheResult['deleted'];
				$this->resetResult['failed_cache_files'] = $cacheResult['failed'];
				$this->cachedAssetsRemoved = $cacheResult['deleted'] > 0 && $cacheResult['failed'] === 0;

				if ($cacheResult['failed'] > 0) {
					$this->resetResult['errors'][] = sprintf(
						_n('%d cached file could not be removed.', '%d cached files could not be removed.', $cacheResult['failed'], 'wp-asset-clean-up'),
						$cacheResult['failed']
					);
				}
            }

			// Remove Asset CleanUp (Pro)'s cache transients
            $this->resetResult['deleted_transients'] += $this->clearAllCacheTransients();

            if ( $wpacuResetValue === 'reset_everything' && ! get_option(WPACU_PLUGIN_ID . '_settings') ) {
                $wpacuSettingsAdmin = new SettingsAdmin();
                $wpacuSettingsAdmin->updateSettingsInDbWithDefaultValues();

                if (! get_option(WPACU_PLUGIN_ID . '_settings')) {
                    $this->resetResult['errors'][] = __('The default settings could not be recreated.', 'wp-asset-clean-up');
                }
            }
		}

		// Four Settings toggles are materialised in the global unload option.
		// Rebuild only those rules after deleting the rest of the plugin data.
		if ($wpacuResetValue === 'reset_everything_except_settings' && ! empty($preservedCommonUnloads)) {
			$wpacuSettingsAdmin = new SettingsAdmin();
			$wpacuSettingsAdmin->updateSiteWideRuleForCommonAssets($preservedCommonUnloads);
		}

		// These unloads are settings stored outside the main settings option.
		// Restore them only when the user explicitly resets Settings.
		if ($wpacuResetValue === 'reset_settings') {
			$wpacuUpdate = new Update();
			$wpacuUpdate->removeEverywhereUnloads(
				array('dashicons' => 'remove', 'wp-block-library' => 'remove'),
				array('jquery-migrate' => 'remove', 'comment-reply' => 'remove')
			);
		}

		if (in_array($wpacuResetValue, array('reset_settings', 'reset_critical_css', 'reset_plugins_manager_front', 'reset_plugins_manager_dash'), true)) {
			$this->resetResult['deleted_transients'] += $this->clearAllCacheTransients();
		}

		if ($wpacuResetValue === 'remove_all_data_for_uninstall') {
			$cleanupErrors = apply_filters('wpacu_internal_remove_all_data_for_uninstall_errors', array());
			if (is_array($cleanupErrors)) {
				$this->resetResult['errors'] = array_merge($this->resetResult['errors'], $cleanupErrors);
			}
		}

		$this->resetResult['errors'] = array_values(array_unique($this->resetResult['errors']));

		if ($wpacuResetValue === 'remove_all_data_for_uninstall') {
			wp_redirect(add_query_arg(array(
				'wpacu_uninstall_cleanup_done' => empty($this->resetResult['errors']) ? 'success' : 'partial',
				'wpacu_time'                   => time(),
			), admin_url('plugins.php')));
			exit;
		}

        set_transient(WPACU_PLUGIN_ID . '_reset_done',
            wp_json_encode(array(
                'reset_choice'          => $this->resetChoice,
                'license_data_removed'  => $this->licenseDataRemoved,
                'cached_assets_removed' => $this->cachedAssetsRemoved,
                'result'                => $this->resetResult
            )),
            30
        );

        wp_redirect(admin_url('admin.php?page=wpassetcleanup_tools&wpacu_reset_done=1&wpacu_time='.time()));
        exit;
	}

	/**
	 * Recursively remove generated CSS/JS cache files and report the real result.
	 *
	 * @return array
	 */
	private function removeCachedAssetFiles()
	{
		$result = array('deleted' => 0, 'failed' => 0);
		$relativeDirectories = array(
			OptimizeCss::getRelPathCssCacheDir(),
			OptimizeJs::getRelPathJsCacheDir(),
		);

		$contentDirectory = realpath(WP_CONTENT_DIR);
		$targetDirectories = array();

		foreach ($relativeDirectories as $relativeDirectory) {
			if ($relativeDirectory === '') {
				$result['failed']++;
				continue;
			}

			$targetDirectoryCandidate = WP_CONTENT_DIR . $relativeDirectory;

			if (! is_dir($targetDirectoryCandidate)) {
				continue;
			}

			$targetDirectory = realpath($targetDirectoryCandidate);
			if ($targetDirectory === false || $contentDirectory === false || strpos($targetDirectory, trailingslashit($contentDirectory)) !== 0) {
				$result['failed']++;
				continue;
			}

			$targetDirectories[] = $targetDirectory;
		}

		foreach (array_unique($targetDirectories) as $targetDirectory) {
			if (! is_dir($targetDirectory)) {
				continue;
			}

			try {
				$directoryIterator = new \RecursiveDirectoryIterator($targetDirectory, \RecursiveDirectoryIterator::SKIP_DOTS);
				$fileIterator = new \RecursiveIteratorIterator($directoryIterator, \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD);

				foreach ($fileIterator as $fileInfo) {
					if (! $fileInfo->isFile() || ! in_array(strtolower($fileInfo->getExtension()), array('css', 'js'), true)) {
						continue;
					}

					$filePath = $fileInfo->getPathname();
					if (@unlink($filePath) && ! is_file($filePath)) {
						$result['deleted']++;
					} else {
						$result['failed']++;
					}
				}
			} catch (\UnexpectedValueException $e) {
				$result['failed']++;
			}
		}

		return $result;
	}

	/**
	 * Remove the complete Asset CleanUp cache directory for an uninstall cleanup.
	 *
	 * @return array{deleted:int, failed:int}
	 */
	private function removePluginCacheDirectory()
	{
		$result = array('deleted' => 0, 'failed' => 0);
		$contentDirectory = realpath(WP_CONTENT_DIR);
		$cacheDirectoryCandidate = WP_CONTENT_DIR . OptimizeCommon::getRelPathPluginCacheDir();

		if (! is_dir($cacheDirectoryCandidate)) {
			return $result;
		}

		$cacheDirectory = realpath($cacheDirectoryCandidate);
		if ($cacheDirectory === false || $contentDirectory === false
			|| strpos($cacheDirectory, trailingslashit($contentDirectory)) !== 0) {
			$result['failed']++;
			return $result;
		}

		try {
			$directoryIterator = new \RecursiveDirectoryIterator($cacheDirectory, \RecursiveDirectoryIterator::SKIP_DOTS);
			$fileIterator = new \RecursiveIteratorIterator($directoryIterator, \RecursiveIteratorIterator::CHILD_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);

			foreach ($fileIterator as $fileInfo) {
				$filePath = $fileInfo->getPathname();

				if ($fileInfo->isLink() || $fileInfo->isFile()) {
					if (@unlink($filePath) && ! file_exists($filePath)) {
						$result['deleted']++;
					} else {
						$result['failed']++;
					}
					continue;
				}

				if ($fileInfo->isDir() && ! @rmdir($filePath) && is_dir($filePath)) {
					$result['failed']++;
				}
			}
		} catch (\UnexpectedValueException $e) {
			$result['failed']++;
		}

		if (! @rmdir($cacheDirectory) && is_dir($cacheDirectory)) {
			$result['failed']++;
		}

		return $result;
	}

	/**
	 * Whether this plugin is still active for other sites in the current network.
	 * Network-shared data must be preserved while another site can still use it.
	 *
	 * @return bool
	 */
	public static function isPluginActiveElsewhereInNetwork($ignoreNetworkActivation = false)
	{
		if (! is_multisite()) {
			return false;
		}

		if (! function_exists('is_plugin_active_for_network')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if (! $ignoreNetworkActivation && is_plugin_active_for_network(WPACU_PLUGIN_BASE)) {
			return true;
		}

		$currentBlogId = get_current_blog_id();
		$siteIds = get_sites(array('fields' => 'ids', 'number' => 0));

		foreach ($siteIds as $siteId) {
			if ((int) $siteId === (int) $currentBlogId) {
				continue;
			}

			switch_to_blog($siteId);
			$isActive = is_plugin_active(WPACU_PLUGIN_BASE);
			restore_current_blog();

			if ($isActive) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove Asset CleanUp (Pro)'s Cache Transients
	 */
	public function clearAllCacheTransients()
    {
        global $wpdb;

	    // Remove Asset CleanUp (Pro)'s cache transients
	    $transientLikes = array(
		    'wpacu_css_',
		    'wpacu_js_'
	    );

	    $sqlQuery = $wpdb->prepare(
	        "SELECT option_name FROM `{$wpdb->options}` WHERE option_name LIKE %s OR option_name LIKE %s",
	        $wpdb->esc_like('_transient_' . $transientLikes[0]) . '%',
	        $wpdb->esc_like('_transient_' . $transientLikes[1]) . '%'
	    );

	    $transientsToClear = $wpdb->get_col($sqlQuery);

	    $deletedCount = 0;

	    foreach (array_unique($transientsToClear) as $transientToClear) {
	        $transientNameToClear = str_replace('_transient_', '', $transientToClear);
		    delete_transient($transientNameToClear);
		    wp_cache_delete($transientNameToClear, 'transient');
		    $deletedCount++;
	    }

	    return $deletedCount;
    }

	/**
	 *
	 */
	public function resetDone()
	{
		$msg = '';
		$errors = isset($this->resetResult['errors']) && is_array($this->resetResult['errors']) ? $this->resetResult['errors'] : array();

		if ($this->resetChoice === 'reset_settings') {
			$msg = __('All the settings were reset to their default values.', 'wp-asset-clean-up');
		}

        if ($this->resetChoice === 'reset_critical_css') {
		    $msg = __('The critical CSS information has been removed and restored to the way it was in the beginning.', 'wp-asset-clean-up');
        }

		if ($this->resetChoice === 'reset_plugins_manager_front') {
			$msg = __('All front-end rules from Plugins Manager were removed.', 'wp-asset-clean-up');
		}

		if ($this->resetChoice === 'reset_plugins_manager_dash') {
			$msg = __('All /wp-admin/ rules from Plugins Manager were removed.', 'wp-asset-clean-up');
		}

        if ($this->resetChoice === 'reset_everything_except_settings') {
			$msg = __('Everything except the "Settings" was reset (including page &amp; bulk unloads, load exceptions).', 'wp-asset-clean-up');
        }

        if ($this->resetChoice === 'reset_everything') {
            $msg = __('Everything was reset (including settings, individual &amp; bulk unloads, load exceptions) to the same point it was when you first activated the plugin.', 'wp-asset-clean-up');

            if ($this->licenseDataRemoved) {
                $msg .= ' <span id="wpacu-license-data-removed-msg">'.__('The license information was also removed.', 'wp-asset-clean-up').'</span>';
            }

            if ($this->cachedAssetsRemoved) {
                $msg .= ' <span id="wpacu-cached-assets-removed-msg">'.__('The cached CSS/JS files were also removed.', 'wp-asset-clean-up').'</span>';
            }
		}

		$resultSummary = array();
		$resultLabels = array(
			'deleted_plugin_rules' => __('Plugins Manager rule sets removed: %d', 'wp-asset-clean-up'),
			'deleted_options'     => __('Options removed: %d', 'wp-asset-clean-up'),
			'deleted_meta_keys'   => __('Metadata keys removed: %d', 'wp-asset-clean-up'),
			'deleted_transients'  => __('Transients cleared: %d', 'wp-asset-clean-up'),
			'deleted_cache_files' => __('Cached files removed: %d', 'wp-asset-clean-up'),
		);

		foreach ($resultLabels as $resultKey => $resultLabel) {
			if (isset($this->resetResult[$resultKey])) {
				$resultSummary[] = sprintf($resultLabel, (int) $this->resetResult[$resultKey]);
			}
		}

		if (! empty($resultSummary)) {
			$msg .= '<br /><small>' . esc_html(implode(' · ', $resultSummary)) . '</small>';
		}

		if (! empty($errors)) {
			$msg .= '<ul class="wpacu-reset-result-errors">';
			foreach ($errors as $error) {
				$msg .= '<li>' . esc_html($error) . '</li>';
			}
			$msg .= '</ul>';
		}
		?>
		<div class="<?php echo empty($errors) ? 'updated' : 'notice-warning'; ?> notice wpacu-notice wpacu-reset-notice is-dismissible">
			<p><span class="dashicons <?php echo empty($errors) ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span> <?php echo wp_kses($msg, array('span' => array('id' => array()), 'br' => array(), 'small' => array(), 'ul' => array('class' => array()), 'li' => array())); ?></p>
		</div>
		<?php
	}

	/**
	 *
	 */
	public function importDone()
    {
        if (empty($this->data['import_done_list'])) {
            return;
        }

	    $importedMessage = __('The following were imported:', 'wp-asset-clean-up');

	    $importedMessage .= '<ul style="list-style: disc; padding-left: 30px; margin-bottom: 0;">';

	    foreach ($this->data['import_done_list'] as $importedKey) {
            if ($importedKey === 'settings') {
	            $importedMessage .= '<li>"'.esc_html__('Settings', 'wp-asset-clean-up').'"</li>';
            }

            if ($importedKey === 'homepage_unloads') {
	            $importedMessage .= '<li>'.esc_html__('Homepage Unload Rules', 'wp-asset-clean-up').'</li>';
            }

            if ($importedKey === 'homepage_exceptions') {
	            $importedMessage .= '<li>'.esc_html__('Homepage Load Exceptions (for site-wide and bulk unloads)', 'wp-asset-clean-up').'</li>';
            }

            if ($importedKey === 'sitewide_unloads') {
	            $importedMessage .= '<li>'.esc_html__('Site-wide unloads', 'wp-asset-clean-up').'</li>';
            }

            if (in_array($importedKey, array('bulk_unload', 'bulk_unloads'), true)) {
	            $importedMessage .= '<li>'.esc_html__('Bulk Unloads (e.g. for all pages of `post` post type)', 'wp-asset-clean-up').'</li>';
            }

            if (in_array($importedKey, array('post_type_load_exceptions', 'post_type_exceptions'), true)) {
	            $importedMessage .= '<li>'.esc_html__('Load exceptions for all pages belonging to specific post types', 'wp-asset-clean-up').'</li>';
            }

            if ($importedKey === 'posts_metas') {
	            $importedMessage .= '<li>'.esc_html__('Posts, Pages &amp; Custom Post Types: Rules &amp; Page Options (Side Meta Box)', 'wp-asset-clean-up').'</li>';
            }

            if ($importedKey === 'critical_css_options') {
                $importedMessage .= '<li>'.esc_html__('Critical CSS', 'wp-asset-clean-up').'</li>';
            }

            if ($importedKey === 'plugins_manager_frontend') {
                $importedMessage .= '<li>'.esc_html__('Plugins Manager — Front-end rules', 'wp-asset-clean-up').'</li>';
            }

            if ($importedKey === 'plugins_manager_dashboard') {
                $importedMessage .= '<li>'.esc_html__('Plugins Manager — /wp-admin/ rules', 'wp-asset-clean-up').'</li>';
            }

            $importedMessage = apply_filters(
                'wpacu_internal_tools_import_done_imported_message',
                $importedMessage,
                $importedKey,
                $this->data['import_done_list'],
                $this
            );
        }

	    $importedMessage .= '</ul>';
        ?>
        <div class="clearfix"></div>
        <div class="updated notice wpacu-notice wpacu-imported-notice is-dismissible">
            <p><span class="dashicons dashicons-yes"></span> <?php echo wp_kses($importedMessage, array('ul' => array('style' => array()), 'li' => array())); ?></p>
            <p>If you're using a caching plugin (e.g. WP Rocket, WP Fastest Cache, W3 Total Cache etc.) it's recommended to clear its cache if the website is working as you expect after this import, so the changes will take effect for every visitor.</p>
        </div>
        <?php
	    $this->data['import_done_list'] = array(); // reset it to avoid showing it twice
    }

	/**
	 * Show a clear error when the selected import file cannot be processed.
	 *
	 * @return void
	 */
	public function importError()
	{
		if (empty($this->data['import_error_message']) || ! is_string($this->data['import_error_message'])) {
			return;
		}
		?>
		<div class="notice notice-error wpacu-notice wpacu-imported-notice is-dismissible">
			<p><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php echo esc_html($this->data['import_error_message']); ?></p>
		</div>
		<?php
		$this->data['import_error_message'] = '';
	}
}
