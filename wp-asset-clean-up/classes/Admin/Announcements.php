<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\Settings;

/**
 * Main class for handling announcements.
 */
class Announcements
{
    // [wpacu_lite]
    /**
     * URL to the JSON feed of announcements.
     * @var string
     */
    private $feedUrl = 'https://wpacu-p.s3.eu-west-2.amazonaws.com/_wpacu-lite-annoucements.json';

    /**
     * Key used for transient storage.
     * @var string
     */
    private $transientKey = 'wpacu_lite_announcements';
    // [/wpacu_lite]

    /**
     * How long to cache announcements (in seconds)
     * Once the cache expires the feed will be refetched
     * e.g. 12 hours
     *
     * @var int
     */
    private $transientTime = 12 * HOUR_IN_SECONDS;

    /**
     * Snooze duration for "Remind me later" feature
     * @var int
     */
    private $snoozeTime = 86400; // 24 hours in seconds

    /**
     * Allowed HTML tags for announcement titles.
     *
     * @var array
     */
    public $allowedTitleHtmlTags = array(
        'em' => array(),
        'i'  => array(),
        'u'  => array(),
    );

    /**
     * Allowed HTML tags for announcement messages.
     *
     * @var array
     */
    public $allowedMessageHtmlTags = array(
        'strong' => array(),
        'em'     => array(),
        'b'      => array(),
        'i'      => array(),
        'u'      => array(),
        'a'      => array(
            'href'   => array(),
            'title'  => array(),
            'target' => array(),
            'rel'    => array(),
        ),
        'br'     => array(),
        'p'      => array(),
    );

    /**
     * @var string
     */
    private static $queryStringAction = 'wpacu_announcement_action';

    /**
     * Priority levels mapped to numerical values for sorting.
     * @var array
     */
    private $priorityLevels = array(
        'high'   => 3,
        'medium' => 2,
        'low'    => 1,
    );

    /**
     * "ajax" - It shows the notice in AJAX after page load
     * "regular" - It shows the notice instantly on page load
     *
     * @var string
     */
    private $showAnnouncementWay = 'ajax';

    /**
     * "ajax" - It closes the notice without page reload
     * "regular" - It closes the notice by page reload
     *
     * @var string
     */
    private $closeAnnouncementWay = 'ajax';

    /**
     * Add action hooks.
     *
     * @return void
     */
    public function init()
    {
        add_action('admin_head', array($this, 'adminHead'));

        if ($this->showAnnouncementWay === 'ajax') {
            // Show announcements (via AJAX)
            add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_fill_announcement_container', array($this, 'fillAnnouncementContainerAjax'));
        }

        // Show the container within "admin_notices" either with the whole content or the DIV to be filled via AJAX
        add_action( 'admin_notices', array( $this, 'renderAnnouncementsContainer' ) );

        if ($this->closeAnnouncementWay === 'ajax') {
            add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_announcements_action', array($this, 'handleAjaxActionRequest')); // AJAX way when triggering an action
        }

        // Regular way fallback (page reload); This will always load regarding the value of {closeAnnouncementWay} because even if AJAX is used, a fallback is always needed
        add_action('admin_init', array($this, 'handleFallbackActions'));

        // Print the jQuery code (e.g. AJAX) that handles the functionality for "Remind me later", "Mark as seen" and "Never show any"
        add_action('admin_footer', array($this, 'displayJsFooter'));

        // Mostly for debugging
        // Handle cache clearing via query string
        add_action('admin_init', array($this, 'handleCacheClearingOnRequest'));

        // Handle settings clearing via query string
        add_action('admin_init', array($this, 'handleSettingsClearingOnRequest'));

        // Reload via AJAX the list of announcements from the area: "Settings" -- "Plugin Usage Preferences" -- "Announcements"
        // In case there are action taken (e.g. from the top announcement shown)
        add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_reload_announcements_settings_tab', array($this, 'reloadAnnouncementsSettingsTab'));
    }

    /**
     * @return bool
     */
    public function _showOnCurrentAdminPage()
    {
        // Check if in the announcements' settings there's "never_show_any" set
        $settingsAdmin         = new SettingsAdmin();
        $announcementsSettings = $settingsAdmin->getOption('announcements');

        // The reference 'global' refers to the fact that it affects all announcements
        if ( isset($announcementsSettings['global']['never_show_any']) && $announcementsSettings['global']['never_show_any'] ) {
            return false;
        }

        // Now determine in which pages to show it if it's enabled

        if (Menu::isPluginPage()) {
            $doNotShowSubTab = isset($_GET['wpacu_selected_sub_tab_area']) && $_GET['wpacu_selected_sub_tab_area'] === 'wpacu-plugin-usage-settings-announcements';

            if ($doNotShowSubTab) {
                return false; // It will be redundant (on the top and in the tab): "Settings" -- "Plugin Usage Preferences" -- "Announcement"
            }

            // Any other page and tab
            return true;
        }

        $currentScreen = get_current_screen();

        if (isset($currentScreen->base) && $currentScreen->base) {
            /**
             * Check if we're on allowed screens:
             *
             * - Dashboard (dashboard)
             * - Plugins (plugins)
             * - General Settings (options-general)
             *
             */

            // Allowed exact screen IDs
            $allowedScreens = array('dashboard', 'plugins', 'options-general');

            if (in_array($currentScreen->base, $allowedScreens)) {
                return true;
            }
        }

        // Finally, none of the conditions were met
        return false;
    }

    /**
     * @return void
     */
    public function adminHead()
    {
        // Not relevant for this page
        if ( ! $this->_showOnCurrentAdminPage() ) {
            return;
        }
        ?>
        <style>
            #wpacu-announcements-container {
                margin: 20px 0 0 0;
            }

            #wpacu-announcements-container .notice-info {
                border-left-color: #008f9c;
                border-top: 1px solid rgba(40, 44, 42, .3);
                border-right: 1px solid rgba(40, 44, 42, .3);
                border-bottom: 1px solid rgba(40, 44, 42, .3);
            }

            ul#wpacu-announcement-action-links {

            }

            ul#wpacu-announcement-action-links li {
                display: inline-block;
                float: none;
                margin-right: 20px;
            }

            ul#wpacu-announcement-action-links li a {
                color: #004567;
            }
        </style>
        <?php
    }

    /**
     * Fetch announcements from cache or remote feed.
     *
     * @return array
     */
    public function getAnnouncementsFromTheFeed()
    {
        $announcements = get_transient( $this->transientKey );

        if ( false !== $announcements ) {
            return $announcements;
        }

        $fetchUrl = add_query_arg( 'wpacu', wp_rand(), $this->feedUrl );

        $response = wp_remote_get( $fetchUrl, array(
            'headers' => array(
                'User-Agent'    => 'WordPress-Plugin',
                'Cache-Control' => 'no-cache',
                'Pragma'        => 'no-cache',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $body          = wp_remote_retrieve_body( $response );
        $announcements = json_decode( $body, true );

        if ( ! is_array( $announcements ) ) {
            return array();
        }

        set_transient( $this->transientKey, $announcements, $this->transientTime );

        return $announcements;
    }

    /**
     * Sanitize announcements to ensure valid structure and priorities.
     *
     * @param array $announcements List of announcements.
     * @return array
     */
    private function sanitizeAnnouncements($announcements)
    {
        // Ensure each announcement has a valid priority, default to 'low' if missing or invalid
        foreach ($announcements as $annId => $ann) {
            if ( ! isset($ann['priority']) || ! array_key_exists(strtolower($ann['priority']), $this->priorityLevels) ) {
                $announcements[$annId]['priority'] = 'low';
            } else {
                $announcements[$annId]['priority'] = strtolower($ann['priority']);
            }
        }

        return $announcements;
    }

    /**
     * @param bool $isRegularCall
     *
     * Display only ONE highest priority unsnoozed and unseen announcement
     *
     * @return void
     */
    public function displayOneAnnouncement($isCallType = 'regular')
    {
        // Get announcements
        $announcements = $this->getAnnouncementsFromTheFeed();

        if ( empty( $announcements ) ) {
            return;
        }

        // Sort announcements by priority descending ('high' first)
        usort( $announcements, array( $this, '_comparePriority' ) );

        // Get all announcements saved settings
        $settingsAdmin = new SettingsAdmin();
        $announcementsSettings = $settingsAdmin->getOption('announcements');

        $currentTime = time();

        foreach ( $announcements as $ann ) {
            $annId = isset( $ann['id'] ) ? $ann['id'] : null;

            if ( empty( $annId ) ) {
                // It always needs to have an "id" (any string, including numerical)
                continue;
            }

            // Seen?
            if ( isset($announcementsSettings['list'][$annId]['seen']) && $announcementsSettings['list'][$annId]['seen'] ) {
                continue;
            }

            // Snoozed?

            // Current time has to be < than the snooze time (the time it was at the moment the action was taken + the snoozing period)
            if ( isset($announcementsSettings['list'][$annId]['snoozed']) &&
                 ($snoozeTime = $announcementsSettings['list'][$annId]['snoozed']) &&
                 $currentTime < $snoozeTime ) {
                continue;
            }

            $titleRaw    = isset( $ann['title'] )      ? $ann['title']    : 'Announcement';
            $messageRaw  = isset( $ann['message'] )    ? $ann['message']  : '';

            // The annoucements always need to have a start and end time
            $startDate   = isset( $ann['start_date'] ) ? $ann['start_date'] : '';
            $endDate     = isset( $ann['end_date'] )   ? $ann['end_date']   : '';

            if ( ! $startDate || ! $endDate ) {
                continue;
            }

            // Convert start/end dates to timestamps (returns false on failure)
            $startTime = strtotime( $startDate ) ?: false;
            $endTime   = strtotime( $endDate )   ?: false;

            if ( ! $startTime || ! $endTime ) {
                continue;
            }

            $priority   = isset( $ann['priority'] ) ? $ann['priority'] : 'low';

            // It always has to be within the "start" and the "end" time
            $isWithinTheTimePeriod = $currentTime >= $startTime && $currentTime <= $endTime;

            if ( ! $isWithinTheTimePeriod ) {
                continue;
            }

            $sanitizedTitle = wp_kses( $titleRaw,   $this->allowedTitleHtmlTags );
            $sanitizedMsg   = wp_kses( $messageRaw, $this->allowedMessageHtmlTags );

            // /?{$queryStringAction}=snooze&announcement_id={id}
            $fallbackUrlRemindLater  = add_query_arg( array( self::$queryStringAction => 'snoozed', 'announcement_id' => $annId ) );

            // /?{$queryStringAction}=seen&announcement_id={id}
            $fallbackUrlMarkAsSeen   = add_query_arg( array( self::$queryStringAction => 'seen', 'announcement_id' => $annId ) );

            // /?{$queryStringAction}=never_show_any&announcement_id={id}
            $fallbackUrlNeverShowAny = add_query_arg( array( self::$queryStringAction => 'never_show_any' ) );
            ?>
                    <?php if ($isCallType === 'regular') { ?>
                        <div id="wpacu-announcements-container">
                    <?php } ?>
                            <div class="notice notice-info wpacu-announcement"
                                 data-wpacu-annoucement-priority="<?php echo $priority; ?>"
                                 data-wpacu-announcement-id="<?php echo esc_attr( $annId ); ?>">
                                <p><strong><?php echo $sanitizedTitle; ?></strong></p>
                                <p><?php echo $sanitizedMsg; ?></p>

                                <!-- [Action links] -->
                                <ul id="wpacu-announcement-action-links">
                                    <li><a href="<?php echo esc_url( $fallbackUrlRemindLater ); ?>"  class="wpacu-snooze-it">Remind Me Later</a></li>
                                    <li><a href="<?php echo esc_url( $fallbackUrlMarkAsSeen ); ?>"   class="wpacu-mark-it-as-seen">Mark as Seen</a></li>
                                    <li><a href="<?php echo esc_url( $fallbackUrlNeverShowAny ); ?>" class="wpacu-never-show-any">Never show plugin announcements</a></li>
                                </ul>
                                <!-- [/Action links] -->
                            </div>
                    <?php if ($isCallType === 'regular') { ?>
                        </div>
                    <?php } ?>

            <?php
            // Only show one announcement
            break;
        }
    }

    /**
     * @return array|void
     */
    public function renderAnnouncementsContainer()
    {
        // Not relevant for this page
        if ( ! $this->_showOnCurrentAdminPage() ) {
            return;
        }

        if ($this->showAnnouncementWay === 'ajax') {
            echo '<div id="wpacu-announcements-container" class="wpacu_hide"></div>'; // This will be filled by the AJAX call
            return;
        }

        // Regular show? Output everything
        echo $this->displayOneAnnouncement();
    }

    /**
     * @return void
     */
    public function fillAnnouncementContainerAjax()
    {
        check_ajax_referer(WPACU_PLUGIN_ID . '_announcements_nonce', 'nonce');

        $announcements = $this->getAnnouncementsFromTheFeed();

        if (empty($announcements)) {
            wp_send_json_error(['message' => 'No announcements available.']);
        }

        ob_start();
        $this->displayOneAnnouncement('ajax');
        $output = ob_get_clean();

        wp_send_json_success(array('html' => $output));
    }

    /**
     * This applies only to the specified announcement
     *
     * e.g. mark it as seen, snooze it
     *
     * @param $announcementId
     * @param $state
     * @param $value
     *
     * @return void
     */
    private function updateAnnouncementState($announcementId, $state, $value)
    {
        $settingsAdminClass = new SettingsAdmin();

        $currentAnnouncements = $settingsAdminClass->getOption('announcements') ?: array();

        $currentAnnouncements['list'][$announcementId][$state] = $value;

        if ( $state === 'seen' && isset($currentAnnouncements['list'][$announcementId]['snoozed']) ) {
            // "snoozed" (if any) is not relevant anymore
            unset($currentAnnouncements['list'][$announcementId]['snoozed']);
        }

        $settingsAdminClass->updateOption('announcements', Misc::filterList($currentAnnouncements));
    }

    /**
     * This applies to all announcements
     *
     * e.g. Never show any of them
     *
     * @param $settingName
     * @param $settingValue
     *
     * @return void
     */
    public function updateAnnouncementsSettings($settingName, $settingValue)
    {
        $settingsAdminClass = new SettingsAdmin();

        $currentAnnouncements = $settingsAdminClass->getOption('announcements') ?: array();

        $currentAnnouncements['global'][$settingName] = $settingValue;

        $settingsAdminClass->updateOption('announcements', Misc::filterList($currentAnnouncements));
    }

    /**
     * @param $actionType
     * @param $announcementId (if empty, then the $actionType is likely "never_show_any" for all announcements)
     * @param string $updateMode ("regular" - page reloads | "ajax")
     *
     * @return void
     */
    public function updateAnnouncementsViaActionType($actionType, $announcementId = '', $updateMode = 'regular')
    {
        if (in_array($actionType, array('seen', 'snoozed')) && empty($announcementId)) {
            if ($updateMode === 'ajax') {
                wp_send_json_error(['message' => 'Invalid announcement ID.']);
            }

            return;
        }

        // Individual announcement action
        if ($actionType === 'snoozed') {
            $snoozeUntil = current_time('timestamp') + $this->snoozeTime;
            $this->updateAnnouncementState($announcementId, 'snoozed', $snoozeUntil);

            if ($updateMode === 'ajax') {
                wp_send_json_success(['message' => 'Announcement snoozed for 24 hours.']);
            }
        }

        // Individual announcement action
        if ($actionType === 'seen') {
            $this->updateAnnouncementState($announcementId, 'seen', true);

            if ($updateMode === 'ajax') {
                wp_send_json_success(['message' => 'Announcement marked as seen.']);
            }
        }

        // All announcements setting
        if ($actionType === 'never_show_any') {
            $this->updateAnnouncementsSettings('never_show_any', 1);

            if ($updateMode === 'ajax') {
                wp_send_json_success(['message' => 'User will never see announcements again.']);
            }
        }

        // No action type triggered during the AJAX call? Invalid request!
        if ($updateMode === 'ajax') {
            wp_send_json_error(['message' => 'Unknown action type.']);
        }
    }

    /**
     * This works for all actions (e.g. snooze, seen, never show any)
     *
     * In case the AJAX call is not made (e.g. due to JavaScript errors or AJAX calls are disabled), then a fallback is in place
     *  e.g. a "href" in the link that is clicked to reload the page and perform the action
     *
     * @return void
     */
    public function handleFallbackActions()
    {
        $actionType     = isset($_GET[self::$queryStringAction]) ? sanitize_text_field($_GET[self::$queryStringAction]) : '';
        $announcementId = isset($_GET['announcement_id'])        ? sanitize_text_field($_GET['announcement_id']) : '';

        if ( empty($actionType) ) {
            return;
        }

        self::updateAnnouncementsViaActionType($actionType, $announcementId);

        // Redirect to the previous URL
        // Clear any irrelevant query strings from the action fallback URL that might cause conflicts
        wp_safe_redirect(
            remove_query_arg(
                array(self::$queryStringAction, 'announcement_id', 'wpacu_clear_announcements_cache', 'wpacu_clear_announcements_settings')
            )
        );

        exit();
    }

    /**
     * @return void
     */
    public function handleCacheClearingOnRequest()
    {
        $query = 'wpacu_clear_announcements_cache';

        $proceed = isset( $_GET[$query] ) && Menu::userCanAccessAssetCleanUp();

        if ( ! $proceed ) {
            return;
        }

        // Delete the transient cache
        delete_transient( $this->transientKey );
    }

    /**
     * @return void
     */
    public static function handleSettingsClearingOnRequest()
    {
        $query = 'wpacu_clear_announcements_settings';

        $proceed = isset( $_GET[$query] ) && Menu::userCanAccessAssetCleanUp();

        if ( ! $proceed ) {
            return;
        }

        $settingsAdmin         = new SettingsAdmin();
        $settingsAdmin->updateOption('announcements', array());
    }

    /**
     * Compare two announcements based on priority.
     * Higher priority announcements come first.
     *
     * @param array $a First announcement.
     * @param array $b Second announcement.
     * @return int Comparison result.
     */
    private function _comparePriority( $a, $b )
    {
        $priorityA = isset( $a['priority'] ) && isset( $this->priorityLevels[ $a['priority'] ] ) ? $this->priorityLevels[ $a['priority'] ] : $this->priorityLevels['low'];
        $priorityB = isset( $b['priority'] ) && isset( $this->priorityLevels[ $b['priority'] ] ) ? $this->priorityLevels[ $b['priority'] ] : $this->priorityLevels['low'];

        if ( $priorityA === $priorityB ) {
            return 0;
        }

        return ( $priorityA > $priorityB ) ? -1 : 1;
    }

    /**
     * THis is valid for all actions (e.g. snooze, seen, never show any)
     *
     * @return void
     */
    public function handleAjaxActionRequest()
    {
        check_ajax_referer(WPACU_PLUGIN_ID . '_announcements_nonce', 'nonce');

        $actionType     = isset($_POST['action_type'])     ? sanitize_text_field($_POST['action_type']) : '';
        $announcementId = isset($_POST['announcement_id']) ? sanitize_text_field($_POST['announcement_id']) : '';

        self::updateAnnouncementsViaActionType($actionType, $announcementId, 'ajax');
    }

    /**
     * @return void
     */
    public function reloadAnnouncementsSettingsTab()
    {
        check_ajax_referer(WPACU_PLUGIN_ID . '_announcements_nonce', 'nonce');

        $wpacuSettings = new Settings;
        $data = $wpacuSettings->getAll(); // It will be used in the inclusion

        $data['is_loaded_via_ajax'] = true;

        include_once WPACU_PLUGIN_DIR.'/templates/_admin-page-settings-plugin-areas/_plugin-usage-settings/_announcements.php';

        exit();
    }

    /**
     * This is for the following actions: snooze, seen, nevershow any
     *
     * @return void
     */
    public function displayJsFooter()
    {
        // Not relevant for this page
        if ( ! $this->_showOnCurrentAdminPage() ) {
            return;
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>',
                    nonce   = '<?php echo wp_create_nonce(WPACU_PLUGIN_ID . '_announcements_nonce'); ?>';

                <?php
                if ($this->showAnnouncementWay === 'ajax') {
                ?>
                    // Fill announcement container dinamically
                    $.ajax({
                        url: ajaxUrl,
                        method: 'POST',
                        data: {
                            action: '<?php echo WPACU_PLUGIN_ID; ?>_fill_announcement_container',
                            nonce: nonce
                        }
                    }).done(function(response) {
                        if (response.success && response.data.html) {
                            $('#wpacu-announcements-container').removeClass('wpacu_hide').html(response.data.html);
                        } else {
                            console.error('Failed to fetch announcements:', response);
                        }
                    }).fail(function() {
                        console.error('Error fetching announcements.');
                    });
                <?php
                }

                if ($this->closeAnnouncementWay === 'ajax') {
                ?>
                    // Fill settings announcements container dinamically (if the admin is on the "Settings" page)
                    // If it gets saved, it should save properly, since the settings were changed after this action
                    function wpacuRefillSettingsAnnouncementsArea()
                    {
                        if ($('#wpacu-settings-annoucements-container').length === 0) {
                            return;
                        }

                        $.ajax({
                            url: ajaxUrl,
                            method: 'POST',
                            data: {
                                action: '<?php echo WPACU_PLUGIN_ID; ?>_reload_announcements_settings_tab',
                                nonce: nonce
                            }
                        }).done(function (response) {
                            $('#wpacu-settings-annoucements-container').removeClass('wpacu_hide').html(response);
                            $('#submit').prop('disabled', false);
                        }).fail(function () {
                            console.error('AJAX Reload Failed: The announcements\' settings were not refetched!');
                        });
                    }

                    // Send request on link click (e.g. snooze, seen, never show any)
                    function wpacuSendAnnouncementRequest(actionType, announcementId) {
                        var requestData = {
                            action: '<?php echo WPACU_PLUGIN_ID; ?>_announcements_action',
                            nonce: nonce,
                            action_type: actionType
                        };

                        if (announcementId) {
                            requestData.announcement_id = announcementId;
                        }

                        $('#submit').prop('disabled', true);

                        return $.ajax({
                            url: ajaxUrl,
                            method: 'POST',
                            data: requestData
                        }).done(function(response) {
                            wpacuRefillSettingsAnnouncementsArea();
                        }).fail(function () {
                            console.log('Error processing request. Please try again later!');
                        });
                    }

                    /*
                     * "Remind me Later" click
                     */
                    $(document).on('click', '.wpacu-snooze-it', function(e) {
                        e.preventDefault();

                        var $announcement  = $(this).closest('[data-wpacu-announcement-id]');
                        var announcementId = $announcement.data('wpacu-announcement-id');

                        wpacuSendAnnouncementRequest('snoozed', announcementId).done(function(response) {
                            if (response.success) {
                                $announcement.parent().fadeOut();
                            } else {
                                console.log(response.data.message || 'Error snoozing announcement.');
                            }
                        }).fail(function() {
                            console.log('Error processing request. Please try again later!');
                        });
                    });

                    /*
                     * "Mark as seen" click
                     */
                    $(document).on('click', '.wpacu-mark-it-as-seen', function(e) {
                        e.preventDefault();

                        var $announcement  = $(this).closest('[data-wpacu-announcement-id]');
                        var announcementId = $announcement.data('wpacu-announcement-id');

                        wpacuSendAnnouncementRequest('seen', announcementId).done(function(response) {
                            if (response.success) {
                                $announcement.parent().fadeOut();
                            } else {
                                alert(response.data.message || 'Error marking announcement as seen.');
                            }
                        }).fail(function() {
                            console.log('Error processing request. Please try again later!');
                        });
                    });

                    /*
                     * "Never show any" click
                     */
                    $(document).on('click', '.wpacu-never-show-any', function(e) {
                        e.preventDefault();

                        var $announcement  = $(this).closest('[data-wpacu-announcement-id]');

                        wpacuSendAnnouncementRequest('never_show_any').done(function(response) {
                            if (response.success) {
                                $announcement.parent().fadeOut();
                            } else {
                                console.log(response.data.message || 'Error disabling announcements.');
                            }
                        }).fail(function() {
                            console.log('Error processing request. Please try again later!');
                        });
                    });
                <?php
                }
                ?>
            });
        </script>
        <?php
    }
}
