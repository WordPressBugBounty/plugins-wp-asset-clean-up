<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\MainAdmin;
use WpAssetCleanUp\Admin\PluginAnnouncements;
use WpAssetCleanUp\Admin\SettingsAdmin;

/**
 * Class PluginTracking
 * @package WpAssetCleanUp
 */
class PluginTracking
{
	/**
	 * The data to send to the Asset CleanUp site
	 *
	 * @access private
	 */
	public $data;

    /**
	 *
	 */
	public function init()
	{
		// Schedule
		add_action('wp',   array($this, 'scheduleEvents' ));
		add_action('init', array($this, 'scheduleSend' ));

		// Triggers when Buttons from the Top Notice are clicked and the page is reloaded (non-AJAX call)
        // This is a fallback in case there are JS errors and the AJAX call is not triggering
        if (isset($_GET['wpacu_is_page_reload']) && $_GET['wpacu_is_page_reload']) {
	        add_action('admin_init', array($this, 'optInOut' ));
        }

		// Before "Settings" are saved in the database, right after form submit
        // Check "Allow Usage Tracking" value and take action if it's enabled
		add_action('wpacu_before_save_settings', array($this, 'checkForSettingsOptIn' ));

        // Notice on the top screen within the Dashboard to get permission from the user to allow tracking
        add_action('admin_notices', array($this, 'adminNotice'), 2);

        add_action('admin_head',   array($this, 'noticeStyles' ));
        add_action('admin_footer', array($this, 'noticeScripts' ));

		// Close the notice when action is taken by AJAX call
		add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_close_tracking_notice', array($this, 'ajaxCloseTrackingNoticeCallback'));
	}

	/**
     * @param bool $isAjaxCall
	 * @return bool|string|void
	 */
	public function optInOut($isAjaxCall = false)
	{
	    if ( ! isset($_REQUEST['wpacu_action']) ) {
	        return false;
        }

		$response = '';
		$redirect = true;

	    if ($isAjaxCall) {
	        $redirect = false;
        }

	    $wpacuAction = $_REQUEST['wpacu_action'];

	    if ($wpacuAction === 'wpacu_opt_into_tracking') {
		    $response = $this->checkForOptIn();
	    }

	    if ($wpacuAction === 'wpacu_opt_out_of_tracking') {
            $response = $this->checkForOptOut();

            if ($redirect) {
	            // Reload the same page without the Asset CleanUp query action
	            wp_redirect(remove_query_arg(array('wpacu_action', 'wpacu_is_page_reload')));
	            exit();
            }
        }

        return $response;
	}

	/**
	 * Trigger scheduling
	 */
	public function scheduleEvents()
	{
		$this->_weeklyEvents();
	}

	/**
	 * Schedule weekly events
	 *
	 * @access private
	 * @since 1.6
	 * @return void
	 */
	private function _weeklyEvents()
	{
		if (! wp_next_scheduled('wpacu_weekly_scheduled_events')) {
			wp_schedule_event(current_time('timestamp', true), 'weekly', 'wpacu_weekly_scheduled_events');
		}
	}

	/**
	 * Check if the user has opted into tracking
	 *
	 * @access private
	 * @return bool
	 */
	private function _trackingAllowed()
	{
        $settingsAdminClass = new SettingsAdmin();
		$allowUsageTracking = $settingsAdminClass->getOption('allow_usage_tracking');
		return (bool) $allowUsageTracking;
	}
	/**
	 * Set up the data that is going to be tracked
	 *
	 * @access private
	 * @return void
	 */
	public function setupData()
	{
		$data = array();

		// Retrieve current theme info
		$themeData = wp_get_theme();
		$theme     = $themeData->get('Name') . ' ' . $themeData->get('Version');

		$settingsClass = new Settings();

		$data['php_version']       = PHP_VERSION;
		$data['wpacu_version']     = WPACU_PLUGIN_VERSION;
		$data['wpacu_settings']    = $settingsClass->getAll();
		$data['wpacu_first_usage'] = get_option(WPACU_PLUGIN_ID.'_first_usage');
		$data['wpacu_review_info'] = get_option(WPACU_PLUGIN_ID.'_review_notice_status');
		$data['wp_version']        = get_bloginfo('version');
		$data['server']            = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';
		$data['multisite']         = is_multisite() ? 'Yes' : 'No';
		$data['theme']             = $theme;

		// Retrieve current plugin information
		$adminPluginFile = ABSPATH . '/wp-admin/includes/plugin.php';
		if (! function_exists( 'get_plugins') && is_file($adminPluginFile)) {
			include $adminPluginFile;
		}

		$plugins        = array_keys(get_plugins());
		$active_plugins = Misc::getActivePlugins();

		foreach ($plugins as $key => $plugin) {
			if (in_array($plugin, $active_plugins)) {
				// Remove active plugins from list, so we can show active and inactive separately
				unset($plugins[$key]);
			}
		}

		$data['active_plugins']   = $active_plugins;
		$data['inactive_plugins'] = $plugins;
		$data['locale']           = get_locale();

		$this->data = $data;
	}

	/**
	 * Send the data to the Asset CleanUp server
	 *
	 * @access private
	 *
	 * @param  bool $override If we should override the tracking setting.
	 * @param  bool $ignoreLastCheckIn If we should ignore when the last check in was.
	 *
	 * @return bool
	 */
	public function sendCheckIn($override = false, $ignoreLastCheckIn = false)
	{
		// Allows us to stop the plugin's own site from checking in, and a filter for any related sites
		if (apply_filters('wpacu_disable_tracking_checkin', false)) {
			return false;
		}

		if (! $override && ! $this->_trackingAllowed()) {
			return false;
		}

		// Send a maximum of once per week
		$lastSend = $this->_getLastSend();

		if ( ! $ignoreLastCheckIn && is_numeric($lastSend) && $lastSend > strtotime('-1 week')) {
			return 'Not Sent: Only Weekly';
		}

		$this->setupData();

		$response = wp_remote_post('https://www.assetcleanup.com/tracking/?wpacu_action=checkin', array(
			'method'      => 'POST',
			'timeout'     => 8,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => false,
			'body'        => $this->data,
			'user-agent'  => 'WPACU/' . WPACU_PLUGIN_VERSION . '; ' . get_bloginfo('url')
		));

		Misc::addUpdateOption(WPACU_PLUGIN_ID.'_tracking_last_send', time());

		return wp_remote_retrieve_body($response);
	}

	/**
	 * @param $savedSettings
	 *
	 * @return array
	 */
	public function checkForSettingsOptIn($savedSettings)
	{
		// Send an initial check in when "Settings" are saved
		if (isset($savedSettings['allow_usage_tracking']) && $savedSettings['allow_usage_tracking'] == 1) {
			$this->sendCheckIn( true );
		}

		return $savedSettings;
	}

	/**
     * Check for a new opt-in via the admin notice or after "Settings" is saved
     *
	 * @return bool|void
	 */
	public function checkForOptIn()
	{
		if (! Menu::userCanAccessAssetCleanUp()) {
			return;
		}

        $settingsAdminClass = new SettingsAdmin();

		// Update the value in the "Settings" area
        $settingsAdminClass->updateOption('allow_usage_tracking', 1);

		// Send the tracking data
		$response = $this->sendCheckIn(true);

		// Mark the notice to be hidden
		Misc::addUpdateOption(WPACU_PLUGIN_ID . '_hide_tracking_notice', 1);

		return $response;
	}

	/**
	 * @return string
	 */
	public function checkForOptOut()
	{
		if (! Menu::userCanAccessAssetCleanUp()) {
			return 'Unauthorized';
		}

		// Disable tracking option from "Settings" and mark the notice as hidden (to not show again)
        $settingsAdminClass = new SettingsAdmin();
        $settingsAdminClass->deleteOption('allow_usage_tracking');
		Misc::addUpdateOption(WPACU_PLUGIN_ID . '_hide_tracking_notice', 1);

		return 'success';
	}

	/**
	 * Get the last time a checkin was sent
	 *
	 * @access private
	 * @return false|string
	 */
	private function _getLastSend()
	{
		return get_option(WPACU_PLUGIN_ID . '_tracking_last_send');
	}

	/**
	 * Schedule a weekly checkin
	 *
	 * We send once a week (while tracking is allowed) to check in, which can be
	 * used to determine active sites.
	 *
	 * @return void
	 */
	public function scheduleSend()
	{
		if (Misc::doingCron()) {
			add_action('wpacu_weekly_scheduled_events', array($this, 'sendCheckIn' ));
		}
	}

	/**
	 * Returns true or false for showing the top tracking notice
	 *
	 * @return bool
	 */
	public function showTrackingNotice()
	{
	    // On URL request (for debugging)
		if ( isset($_GET['wpacu_show_tracking_notice']) ) {
			return true;
		}

		// If another Asset CleanUp notice (e.g. for plugin review) is already shown,
        // don't also show this one below/above it
		if (MainAdmin::isTopAdminNoticeDisplayed()) {
		    return false;
        }

        $settingsAdminClass = new SettingsAdmin();

		if ($settingsAdminClass->getOption('allow_usage_tracking')) {
			return false;
		}

		if (get_option(WPACU_PLUGIN_ID . '_hide_tracking_notice')) {
			return false;
		}

		if ( ! Menu::userCanAccessAssetCleanUp() ) {
			return false;
		}

        $pluginAdminAnnouncements = new PluginAnnouncements();

        if ($pluginAdminAnnouncements->isCurrentTimeBetweenAnyEnabledAnnouncementTime()) {
            return false; // Announcements have priority; Show the tracking notice when no announcements are shown
        }

		return true;
	}

	/**
	 *
	 */
	public function ajaxCloseTrackingNoticeCallback()
	{
		check_ajax_referer('wpacu_plugin_tracking_nonce', 'wpacu_security');

		$action = isset($_POST['action']) ? $_POST['action'] : false;

		if ($action !== WPACU_PLUGIN_ID . '_close_tracking_notice' || ! $action) {
			exit('Invalid Action');
		}

		$wpacuAction = isset($_POST['wpacu_action']) ? $_POST['wpacu_action'] : false;

		if (! $wpacuAction) {
			exit('Invalid Asset CleanUp Action');
		}

		// Allow to Disallow (depending on the action chosen)
		$response = $this->optInOut(true);
		echo esc_html($response);

		exit();
	}

	/**
	 *
	 */
	public function noticeStyles()
	{
        if ( ! $this->showTrackingNotice() ) {
            return;
        }
		?>
        <style <?php echo Misc::getStyleTypeAttribute(); ?>>
            .wpacu-tracking-notice {
                border-left-color: #008f9c;
            }

            .wpacu-tracking-notice a {
                color: #2271b1;
            }

            .wpacu-tracking-notice .wpacu-action-links {
                margin: 0 0 8px;
            }

            .wpacu-tracking-notice .wpacu-action-links ul {
                list-style: none;
                margin: 0;
            }

            .wpacu-tracking-notice .wpacu-action-links ul li.wpacu-optin {
                float: left;
                margin-right: 10px;
            }

            .wpacu-tracking-notice .wpacu-action-links ul li.wpacu-optout {
                float: left;
                margin-right: 5px;
            }

            .wpacu-tracking-notice .wpacu-action-links ul li.wpacu-more-info {
                float: left;
                margin-top: 5px;
                margin-left: 5px;
            }

            #wpacu-tracked-data-list {
                margin: 14px 0;
            }

            #wpacu-tracked-data-list .table-striped {
                border: none;
                border-spacing: 0;
            }

            #wpacu-tracked-data-list .wpacu_table_wrap .table.table-striped th,
            #wpacu-tracked-data-list .wpacu_table_wrap .table.table-striped td {
                padding: 0.62rem;
                vertical-align: top;
                border-top: 1px solid #eceeef;
            }

            #wpacu-tracked-data-list .table-striped tbody tr:nth-of-type(even) {
                background-color: rgba(0, 143, 156, 0.05);
            }

            #wpacu-tracked-data-list .table-striped tbody tr td:first-child {
                font-weight: bold;
            }
        </style>
		<?php
	}

	/**
	 *
	 */
	public function noticeScripts()
	{
        if ( ! $this->showTrackingNotice() ) {
            return;
        }
		?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var $wpacuTrackedDataList = $('#wpacu-tracked-data-list');

                // Tracking Info Link Clicked
                $('#wpacu-show-tracked-data').on('click', function(e) {
                    e.preventDefault();

                    if ($wpacuTrackedDataList.is(':hidden')) {
                        $wpacuTrackedDataList.slideDown('fast');
                    } else {
                        $wpacuTrackedDataList.slideUp('fast');
                    }
                });

                // 'x' click from the top right of the notice
                $(document).on('click', '.wpacu-tracking-notice .notice-dismiss', function(event) {
                    $('[data-wpacu-close-action="wpacu_opt_out_of_tracking"]').trigger('click');
                });

                // button click
                $('.wpacu-close-tracking-notice').on('click', function(e) {
                    e.preventDefault();

                    $('.wpacu-tracking-notice').fadeOut('fast');

                    var wpacuXhr = new XMLHttpRequest(),
                        wpacuCloseAction = $(this).attr('data-wpacu-close-action'),
                        wpacuSecurityNonce = '<?php echo wp_create_nonce('wpacu_plugin_tracking_nonce'); ?>';

                    wpacuXhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>');
                    wpacuXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    wpacuXhr.onload = function () {
                        if (wpacuXhr.status === 200) {
                            } else if (wpacuXhr.status !== 200) {
                            }
                    };

                    wpacuXhr.send(encodeURI('action=<?php echo WPACU_PLUGIN_ID . '_close_tracking_notice'; ?>&wpacu_action=' + wpacuCloseAction + '&wpacu_security='+ wpacuSecurityNonce));
                });
            });
        </script>
		<?php
	}

	/**
	 * Display the admin notice to users that have not opted-in or out
	 *
	 * @return void
	 */
	public function adminNotice()
	{
	    if ( ! $this->showTrackingNotice() ) {
		    return;
        }

		$this->setupData();

		$optin_url  = add_query_arg(array('wpacu_action' => 'wpacu_opt_into_tracking',   'wpacu_is_page_reload' => true));
		$optout_url = add_query_arg(array('wpacu_action' => 'wpacu_opt_out_of_tracking', 'wpacu_is_page_reload' => true));

		?>
		<div class="wpacu-tracking-notice notice is-dismissible">
			<p><?php _e('Allow Asset CleanUp to anonymously track plugin usage in order to help us make the plugin better? No sensitive or personal data is collected.', 'wp-asset-clean-up'); ?></p>
			<div class="wpacu-action-links">
				<ul>
					<li class="wpacu-optin">
                        <a href="<?php echo esc_url($optin_url); ?>"
                           data-wpacu-close-action="wpacu_opt_into_tracking"
                           class="wpacu-close-tracking-notice button-primary"><img style="vertical-align: sub;" width="16" height="16" src="<?php echo WPACU_PLUGIN_URL; ?>/assets/icons/icon-check-white.svg" alt="" />&nbsp;<?php _e('Allow, I\'m happy to help', 'easy-digital-downloads'); ?></a>
                    </li>
					<li class="wpacu-optout">
                        <a href="<?php echo esc_url($optout_url); ?>"
                           data-wpacu-close-action="wpacu_opt_out_of_tracking"
                           class="wpacu-close-tracking-notice button-secondary"><img style="vertical-align: sub; margin-right: 2px;" width="16" height="16" src="<?php echo WPACU_PLUGIN_URL; ?>/assets/icons/icon-block.svg" alt="" />&nbsp;<?php _e('No, do not allow', 'easy-digital-downloads'); ?></a></li>
			        <li class="wpacu-more-info"><span style="color: #004567;" class="dashicons dashicons-info"></span> <a id="wpacu-show-tracked-data" href="#">What kind of data will be sent for the tracking?</a></li>
				</ul>
				<div style="clear: both;"></div>
                <div style="display: none;" id="wpacu-tracked-data-list">
					<?php self::showSentInfoDataTable($this->data); ?>
                </div>
                <hr />
                <p style="font-size: 12px; font-style: italic; margin: 10px 0 10px;"><strong>Note:</strong> This option can always be turned ON &amp; OFF in <a style="text-decoration: none;" target="_blank" href="<?php echo admin_url('admin.php?page=wpassetcleanup_settings&wpacu_selected_tab_area=wpacu-setting-plugin-usage-settings&wpacu_selected_sub_tab_area=wpacu-plugin-usage-settings-analytics'); ?>">"Settings" &rarr; "Plugin Usage Preferences" &rarr; "Analytics"</a></p>
			</div>
		</div>
		<?php
        MainAdmin::instance()->setTopAdminNoticeDisplayed();
	}

	/**
	 * @param $data
	 */
	public static function showSentInfoDataTable($data)
    {
        ?>
        <div class="wpacu_table_wrap">
            <table class="table table-striped">
                <tr>
                    <td style="width: 182px;">PHP Version:</td>
                    <td><?php echo esc_html($data['php_version']); ?></td>
                </tr>
                <tr>
                    <td>Asset CleanUp Info:</td>
                    <td>Version: <?php echo esc_html($data['wpacu_version']); ?>, Settings &amp; Usage Information</td>
                </tr>
                <tr>
                    <td>WordPress Version:</td>
                    <td><?php echo esc_html($data['wp_version']); ?></td>
                </tr>
                <tr>
                    <td>Server:</td>
                    <td><?php echo esc_html($data['server']); ?></td>
                </tr>
                <tr>
                    <td>Multisite:</td>
                    <td><?php echo esc_html($data['multisite']); ?></td>
                </tr>
                <tr>
                    <td>Theme:</td>
                    <td><?php echo esc_html($data['theme']); ?></td>
                </tr>
                <tr>
                    <td>Locale:</td>
                    <td><?php echo esc_html($data['locale']); ?></td>
                </tr>
                <tr>
                    <td>Plugins:</td>
                    <td>The list of active &amp; inactive plugins</td>
                </tr>
            </table>
        </div>
        <?php
    }
}
