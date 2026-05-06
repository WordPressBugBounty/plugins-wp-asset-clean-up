<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\Settings;

/**
 * Main class for handling announcements.
 */
class PluginAnnouncements
{
    /**
     * Key used for transient storage.
     * @var string
     */
    private $transientKey = 'pluginann_announcements';

    /**
     * @var string
     */
    private $queryStringAction = 'pluginann_announcement_action';

    /**
     * How long to cache announcements (in seconds)
     * Once the cache expires the feed will be refetched
     *
     * e.g. 12 hours
     *
     * @var int
     */
    private $transientTime = 12 * HOUR_IN_SECONDS;

    /**
     * Snooze duration for "Remind me later" feature (for the currently shown annoucement)
     *
     * @var int
     */
    private $snoozeTimeCurrent = 86400; // 24 hours in seconds

    /**
     * When there are multiple annoucements to show
     * Make sure the next one shows a bit later to avoid annoying the admins
     *
     * @var int
     */
    private $snoozeTimeForNext = 3600; // 1 hour

    /**
     * Allowed HTML tags for announcement titles.
     *
     * @var array
     */
    public $allowedTitleHtmlTags = array(
        'em'   => array(),
        'i'    => array(),
        'u'    => array(),
        'span' => array(),
    );

    /**
     * Allowed HTML tags for announcement messages.
     *
     * @var array
     */
    public $allowedMessageHtmlTags = array(
        'strong' => array(),
        'span'   => array(),
        'em'     => array(),
        'b'      => array(),
        'i'      => array(),
        'u'      => array(),
        'a'      => array(
            'href'   => array(),
            'title'  => array(),
            'target' => array(),
            'rel'    => array(),
            'class'  => array(),
        ),
        'br'     => array(),
        'p'      => array(),
    );

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
     * @param bool $justBase | if true, it will return "plugin" instead of "plugin_lite" or "plugin_pro"
     *
     * @return string
     */
    private function getAnnPrefix($justBase = false)
    {
        if (WPACU_PLUGIN_SLUG === 'wp-asset-clean-up') {
            $annPrefix = 'wpacu_lite';
        } elseif (WPACU_PLUGIN_SLUG === 'wp-asset-clean-up-pro') {
            $annPrefix = 'wpacu_pro';
        } else {
            $annPrefix = 'wpacu_'; // something's funny
        }

        if ($justBase && strpos($annPrefix, '_') !== false) {
            list($base) = explode('_', $annPrefix);
            return $base;
        }

        return $annPrefix;
    }

    /**
     * @return string
     */
    private function getFeedUrl()
    {
        if (WPACU_PLUGIN_SLUG === 'wp-asset-clean-up') {
            $feedUrl = 'http://drm6aghn7w1h8.cloudfront.net/_wpacu-lite-announcements.json';
        } elseif (WPACU_PLUGIN_SLUG === 'wp-asset-clean-up-pro') {
            $feedUrl = 'http://drm6aghn7w1h8.cloudfront.net/_wpacu-pro-announcements.json';
        } else {
            return ''; // something's funny
        }

        return $feedUrl;
    }

    /**
     * @return array|string|string[]
     */
    private function getQueryStringAction()
    {
        return str_replace('pluginann', $this->getAnnPrefix(), $this->queryStringAction);
    }

    /**
     * @return string
     */
    private function getAnnIdQuery()
    {
        return $this->getAnnPrefix() . '_announcement_id';
    }

    /**
     * @return array|string|string[]
     */
    private function getTransientKey()
    {
        return str_replace('pluginann', $this->getAnnPrefix(), $this->transientKey);
    }

    /**
     * Add action hooks.
     *
     * @return void
     */
    public function init()
    {
        add_action('init', function () {
            if ( ! Menu::userCanAccessPlugin() ) {
                return;
            }

            // Print the CSS code for the announcements
            add_action('admin_head', array($this, 'adminHead'));

            // Print the jQuery code (e.g. AJAX) that handles the functionality for "Remind me later", "Mark as seen" and "Never show any"
            add_action('admin_footer', array($this, 'displayJsFooter'));

            // Show the container within "admin_notices" either with the whole content or the DIV to be filled via AJAX
            add_action( 'admin_notices', array( $this, 'renderAnnouncementsContainer' ), 1 );

            // Regular way fallback (page reload); This will always load regarding the value of {closeAnnouncementWay} because even if AJAX is used, a fallback is always needed
            add_action('admin_init', array($this, 'handleFallbackActions'));

            // Mostly for debugging
            // Handle cache clearing via query string
            add_action('admin_init', array($this, 'handleCacheClearingOnRequest'));

            // Handle settings clearing via query string
            add_action('admin_init', array($this, 'handleSettingsClearingOnRequest'));

            if ($this->showAnnouncementWay === 'ajax') {
                // Show announcements (via AJAX)
                add_action('wp_ajax_' . Plugin::getConfig('id') . '_fill_announcement_container', array($this, 'fillAnnouncementContainerAjax'));
            }

            if ($this->closeAnnouncementWay === 'ajax') {
                // Close announcements (via AJAX), after using any of the actions: snooze, seen, never show any
                add_action('wp_ajax_' . Plugin::getConfig('id') . '_announcements_action', array($this, 'handleAjaxActionRequest'));
            }

            // Reload via AJAX the list of announcements from the area: "Settings" -- "Plugin Usage Preferences" -- "Announcements"
            // In case there are action taken (e.g. from the top announcement shown)
            add_action('wp_ajax_' . Plugin::getConfig('id') . '_reload_announcements_settings_tab', array($this, 'reloadAnnouncementsSettingsTab'));
        });
    }

    /**
     * @return false|void
     */
    public static function isShowAnnouncementsEnabled()
    {
        // Check if in the announcements' settings there's "never_show_any" set
        $settingsAdmin         = new SettingsAdmin();
        $announcementsSettings = $settingsAdmin->getOption('announcements');

        // The reference 'global' refers to the fact that it affects all announcements
        if ( isset($announcementsSettings['global']['never_show_any']) && $announcementsSettings['global']['never_show_any'] ) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function _showOnCurrentAdminPage()
    {
        if ( ! self::isShowAnnouncementsEnabled() ) {
            return false;
        }

        // Now determine in which pages to show it if it's enabled

        if (Menu::isPluginPage()) {
            $getKey = $this->getAnnPrefix(true) . '_selected_sub_tab_area';

            $doNotShowSubTab = isset($_GET[$getKey]) && $_GET[$getKey] === $this->getAnnPrefix(true) . '-plugin-usage-settings-announcements';

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

        $iconsDir = Plugin::getConfig('url') . '/assets/icons/';

        ob_start();
        ?>
        <style>
            #pluginann-announcements-container {
                margin: 20px 0 0 0;
            }

            #pluginann-announcements-container .notice-info {
                border-left-color: #00a7a7;
                border-top: 1px solid rgba(40, 44, 42, .3);
                border-right: 1px solid rgba(40, 44, 42, .3);
                border-bottom: 1px solid rgba(40, 44, 42, .3);
            }

            #pluginann-announcements-container .notice-info .pluginann-ann-title {
                font-size: 15px;
                margin: 12px 0 10px;
            }

            #pluginann-announcements-container .notice-info .pluginann-ann-message {
                font-size: 14px;
                margin: 12px 0 16px;
            }

            #pluginann-announcements-container .notice-info .pluginann-ann-message a.button-primary,
            #pluginann-announcements-container .notice-info .pluginann-ann-message a.button-secondary {
                font-size: 14px;
                vertical-align: baseline;
            }

            ul#pluginann-announcement-action-links {
                margin: 0 0 10px;
            }

            ul#pluginann-announcement-action-links li {
                display: inline-block;
                float: none;
                margin-right: 20px;
            }

            ul#pluginann-announcement-action-links li a {
                color: #2271b1;
                display: inline-flex;
                transition: color 0.3s ease;
            }

            ul#pluginann-announcement-action-links li a:hover {
                color: #004567;
            }

            ul#pluginann-announcement-action-links li a .pluginann-icon {
                display: inline-block;
                vertical-align: middle;
                width: 18px;
                height: 18px;
                margin-right: 5px;
                background-size: contain;
                background-repeat: no-repeat;
            }

            ul#pluginann-announcement-action-links li a .pluginann-icon.pluginann-snooze {
                background-image: url('<?php echo $iconsDir; ?>icon-snooze.svg');
            }

            ul#pluginann-announcement-action-links li a .pluginann-icon.pluginann-seen {
                background-image: url('<?php echo $iconsDir; ?>icon-eye.svg');
            }

            ul#pluginann-announcement-action-links li a .pluginann-icon.pluginann-block {
                background-image: url('<?php echo $iconsDir; ?>icon-block.svg');
            }
        </style>
        <?php
        echo $this->filterOutputForUniquePrefix(ob_get_clean());
    }

    /**
     * Fetch announcements from cache or remote feed.
     *
     * @return array
     */
    public function getAnnouncementsFromTheFeed()
    {
        $announcements = get_transient( $this->getTransientKey() );

        // Already in the cache? Make sure it's read correctly
        if ( false !== $announcements ) {
            // If something stored a malformed value (e.g. string), avoid fatal errors later
            if ( is_array( $announcements ) ) {
                return $this->sanitizeAnnouncements($announcements);
            }

            // Try to recover if it's JSON
            if ( is_string( $announcements ) ) {
                $maybeArrayFromJson = json_decode( $announcements, true );

                if ( is_array( $maybeArrayFromJson ) && ! empty($maybeArrayFromJson) ) {
                    set_transient( $this->getTransientKey(), $maybeArrayFromJson, $this->transientTime );
                    return $this->sanitizeAnnouncements( $maybeArrayFromJson );
                }

                // Try to recover if it's serialized
                $maybeUnserialized = maybe_unserialize($announcements);

                if ( is_array( $maybeUnserialized ) && ! empty( $maybeUnserialized ) ) {
                    set_transient( $this->getTransientKey(), $maybeUnserialized, $this->transientTime );
                    return $this->sanitizeAnnouncements( $maybeUnserialized );
                }
            }

            // Nothing usable -> wipe the transient and move on
            delete_transient( $this->transientKey );

            return array();
        }

        $fetchUrl = add_query_arg( $this->getAnnPrefix(), wp_rand(), $this->getFeedUrl() );

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

        set_transient( $this->getTransientKey(), $announcements, $this->transientTime );

        return $this->sanitizeAnnouncements($announcements);
    }

    /**
     * Sanitize announcements to ensure valid structure and priorities.
     *
     * @param array $announcements List of announcements.
     * @return array
     */
    private function sanitizeAnnouncements($announcements)
    {
        if (empty($announcements)) {
            return array();
        }

        // Ensure each announcement has a valid priority, default to 'low' if missing or invalid
        foreach ($announcements as $annId => $ann) {
            if ( ! is_array($ann) ) {
                continue;
            }

            if ( ! isset($ann['priority']) || ! array_key_exists(strtolower($ann['priority']), $this->priorityLevels) ) {
                $announcements[$annId]['priority'] = 'low';
            } else {
                $announcements[$annId]['priority'] = strtolower($ann['priority']);
            }
        }

        return $announcements;
    }

    /**
     * It will check the "From" and "To" showing date for each announcement (the option to "Show announcements" must be enabled)
     * If it will match at least one, it will return true
     *
     * @return bool
     */
    public function isCurrentTimeBetweenAnyEnabledAnnouncementTime()
    {
        if ( ! self::isShowAnnouncementsEnabled() ) {
            return false;
        }

        $currentTime = current_time('timestamp', true);

        // Get announcements
        $feedAnnouncements = $this->getAnnouncementsFromTheFeed();

        foreach ( $feedAnnouncements as $ann ) {
            // The annoucements always need to have a start and end time
            $startDate = isset($ann['start_date']) ? $ann['start_date'] : '';
            $endDate   = isset($ann['end_date'])   ? $ann['end_date'] : '';

            if ( ! $startDate || ! $endDate) {
                continue;
            }

            // Convert start/end dates to timestamps (returns false on failure)
            $startTime = strtotime($startDate) ?: false;
            $endTime   = strtotime($endDate)   ?: false;

            if ( ! $startTime || ! $endTime) {
                continue;
            }

            // It always has to be within the "start" and the "end" time
            $isWithinTheTimePeriod = $currentTime >= $startTime && $currentTime <= $endTime;

            if ( $isWithinTheTimePeriod ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function isSiteTimezoneUtc()
    {
        $timezone_string = get_option('timezone_string'); // Gets named timezone (e.g., 'Europe/London')
        $gmt_offset = get_option('gmt_offset'); // Gets numeric offset (e.g., 0, 2, -5.5)

        // If timezone_string (is empty or 'Europe/London') and gmt_offset is exactly 0, it's UTC
        if ((empty($timezone_string) || $timezone_string === 'Europe/London') && $gmt_offset == 0) {
            return true; // Site is in UTC
        }

        // If the timezone string is explicitly set to 'UTC'
        if ($timezone_string === 'UTC') {
            return true;
        }

        return false; // Site is not in UTC
    }

    /**
     * @param $feedUnix
     *
     * @return int
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public static function feedUnixToWordPressUnix($feedUnix)
    {
        if ( ! class_exists('\DateTime') || ! class_exists('\DateTimeZone') ) {
            return $feedUnix;
        }

        // Get the WordPress timezone setting
        $timezoneString = get_option('timezone_string');

        // If timezone_string is empty, fallback to GMT offset
        if (empty($timezoneString)) {
            $gmtOffset = get_option('gmt_offset'); // Offset in hours
            $timezoneString = timezone_name_from_abbr('', $gmtOffset * 3600, 0);
        }

        // Create DateTime object from JSON timestamp (Assume it's UTC)
        $date = new \DateTime('@' . $feedUnix, new \DateTimeZone('UTC')); // '@' forces UTC
        $date->setTimezone(new \DateTimeZone($timezoneString)); // Convert to WP timezone

        // Adjust the timestamp by the timezone offset (Manual correction)
        $wpOffsetSeconds = $date->getOffset(); // Offset in seconds

        // Get the Unix timestamp in WordPress timezone
        return $feedUnix + $wpOffsetSeconds;
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
        $feedAnnouncements = $this->getAnnouncementsFromTheFeed();

        if ( empty( $feedAnnouncements ) ) {
            return;
        }

        // Sort announcements by priority descending ('high' first)
        usort( $feedAnnouncements, array( $this, '_comparePriority' ) );

        // Get all announcements saved settings
        $settingsAdmin = new SettingsAdmin();
        $announcementsSettings = $settingsAdmin->getOption('announcements');

        $currentTime = current_time('timestamp', true);

        // Prepare the announcements that can be shown
        $showableAnnouncementsNow = $showableAnnouncementsIncludingAnySnoozed = array();

        foreach ( $feedAnnouncements as $ann ) {
            $annId = isset($ann['id']) ? $ann['id'] : null;

            if (empty($annId)) {
                // It always needs to have an "id" (any string, including numerical)
                continue;
            }

            // It needs to have a title or a message (either of them, or both)
            $annTitle = isset($ann['title'])   ? $ann['title']   : null;
            $annMsg   = isset($ann['message']) ? $ann['message'] : null;

            if ( empty($annTitle) && empty($annMsg) ) {
                continue;
            }

            // Seen?
            if (isset($announcementsSettings['list'][$annId]['seen']) && $announcementsSettings['list'][$annId]['seen']) {
                continue;
            }

            // The annoucements always need to have a start and end time
            $startDate = isset($ann['start_date']) ? $ann['start_date'] : '';
            $endDate   = isset($ann['end_date'])   ? $ann['end_date'] : '';

            if ( ! $startDate || ! $endDate) {
                continue;
            }

            // Convert start/end dates to timestamps (returns false on failure)
            $startTime = strtotime($startDate) ?: false;
            $endTime   = strtotime($endDate)   ?: false;

            if ( ! $startTime || ! $endTime) {
                continue;
            }

            // e.g. useful to hide "Remind me later" if the ending time is closed for an announcement
            // as it won't show up anyway later on and the admin could decide to mark it as "Seen"
            $ann['start_time_unix'] = $startTime;
            $ann['end_time_unix']   = $endTime;

            // It always has to be within the "start" and the "end" time
            $isWithinTheTimePeriod = $currentTime >= $startTime && $currentTime <= $endTime;

            if ( ! $isWithinTheTimePeriod) {
                continue;
            }

            // Does it have extra conditions? Check them!
            // e.g. at least a few days have to pass since plugin activation (first usage)
            $conditions = isset($ann['conditions']) && is_array($ann['conditions']) ? $ann['conditions'] : array();

            $pluginUsageData = Plugin::getPluginUsageData($ann['conditions']);

            if ( ! self::isMatchForExtraConditions($conditions, $pluginUsageData) ) {
                continue;
            }

            // Snoozed?

            // Current time has to be < than the snooze time (the time it was at the moment the action was taken + the snoozing period)
            if (isset($announcementsSettings['list'][$annId]['snoozed']) &&
                ($snoozeTime = $announcementsSettings['list'][$annId]['snoozed']) &&
                $currentTime < $snoozeTime) {
                $ann['snoozed'] = $snoozeTime;
                $showableAnnouncementsIncludingAnySnoozed[] = $ann;
                continue;
            } else {
                $showableAnnouncementsIncludingAnySnoozed[] = $ann;
            }

            // Final list (all that could be shown)
            $showableAnnouncementsNow[] = $ann;
        }

        ob_start();

        foreach ( $showableAnnouncementsNow as $ann ) {
            $annId          = isset($ann['id'])       ? $ann['id']       : null;

            $priority       = isset($ann['priority']) ? $ann['priority'] : 'low';

            $titleRaw       = (isset($ann['title'])   && $ann['title'])   ? $this->filterOutputForUniquePrefix($ann['title'])   : '';
            $messageRaw     = (isset($ann['message']) && $ann['message']) ? $this->filterOutputForUniquePrefix($ann['message']) : '';

            $sanitizedTitle = $sanitizedMsg = '';

            if ($titleRaw) {
                $sanitizedTitle = wp_kses($titleRaw, $this->allowedTitleHtmlTags);
            }

            if ($messageRaw) {
                $sanitizedMsg = wp_kses($messageRaw, $this->allowedMessageHtmlTags);
            }

            $showRemindMeLaterAction = true;

            if ( (current_time('timestamp', true) + $this->snoozeTimeCurrent) > $ann['end_time_unix'] ) {
                // By the time it should technically show up, it will expire
                // The admin can view it in the "Settings" -- "Plugin Usage Preferences" -- "Announcements"
                $showRemindMeLaterAction = false;
            }

            if ($showRemindMeLaterAction) {
                // /?{$queryStringAction}=snooze&pluginann_announcement_id={id}
                $fallbackUrlRemindLater = add_query_arg( array( $this->getQueryStringAction() => 'snoozed', 'pluginann_announcement_id' => $annId ) );
            }

            // /?{$queryStringAction}=seen&pluginann_announcement_id={id}
            $fallbackUrlMarkAsSeen = add_query_arg( array( $this->getQueryStringAction() => 'seen', 'pluginann_announcement_id' => $annId ) );

            // /?{$queryStringAction}=never_show_any&pluginann_announcement_id={id}
            $fallbackUrlNeverShowAny = add_query_arg( array( $this->getQueryStringAction() => 'never_show_any' ) );
            ?>
                    <?php if ($isCallType === 'regular') { ?>
                        <div id="pluginann-announcements-container">
                    <?php } ?>
                            <div class="notice notice-info is-dismissible pluginann-announcement"
                                 data-pluginann-annoucement-priority="<?php echo $priority; ?>"
                                 data-pluginann-announcement-id="<?php echo esc_attr( $annId ); ?>">

                                <?php
                                if ($sanitizedTitle !== '') {
                                ?>
                                    <p class="pluginann-ann-title"><strong><?php echo $sanitizedTitle; ?></strong></p>
                                <?php
                                }

                                if ($sanitizedMsg !== '') {
                                ?>
                                    <p class="pluginann-ann-message"><?php echo $sanitizedMsg; ?></p>
                                <?php
                                }
                                ?>

                                <!-- [Action links] -->
                                <ul id="pluginann-announcement-action-links">
                                    <?php if ($showRemindMeLaterAction) { ?>
                                        <li><a href="<?php echo esc_url( $fallbackUrlRemindLater ); ?>"  class="pluginann-snooze-it"><span class="pluginann-icon pluginann-snooze" aria-hidden="true"></span> Remind Me Later</a></li>
                                    <?php } ?>

                                    <li><a href="<?php echo esc_url( $fallbackUrlMarkAsSeen ); ?>"   class="pluginann-mark-it-as-seen pluginann-main-action-link"><span class="pluginann-icon pluginann-seen" aria-hidden="true"></span> Mark as Seen</a></li>
                                    <li><a href="<?php echo esc_url( $fallbackUrlNeverShowAny ); ?>" class="pluginann-never-show-any"><span class="pluginann-icon pluginann-block" aria-hidden="true"></span> Never show plugin announcements</a></li>
                                </ul>
                                <!-- [/Action links] -->
                                <hr />
                                <p style="font-size: 12px; font-style: italic; margin: 10px 0 10px;"><strong>Note:</strong> <?php echo Plugin::getConfig('title'); ?>'s annoucements can always be managed in <a style="text-decoration: none;" target="_blank" href="<?php echo admin_url('admin.php?page='.Plugin::getConfig('id').'_settings&pluginann_selected_tab_area=pluginann-setting-plugin-usage-settings&pluginann_selected_sub_tab_area=pluginann-plugin-usage-settings-announcements'); ?>">"Settings" &rarr; "Plugin Usage Preferences" &rarr; "Announcements"</a></p>
                            </div>
                    <?php if ($isCallType === 'regular') { ?>
                        </div>
                    <?php } ?>

            <?php
            // For regular view (to avoid showing any other notices at the same time)
            MainAdmin::instance()->setTopAdminNoticeDisplayed();

            if (count($showableAnnouncementsIncludingAnySnoozed) > 1) {
                $this->snoozeNextAnnouncementsAfterCurrentOne($annId, $showableAnnouncementsIncludingAnySnoozed);
            }

            echo $this->filterOutputForUniquePrefix(ob_get_clean());

            // Only show one announcement
            break;
        }
    }

    /**
     * @param $conditions
     * @param $pluginUsageData
     *
     * @return bool
     */
    public static function isMatchForExtraConditions($conditions, $pluginUsageData)
    {
        // Check if the condition format is valid
        if ( ! isset($conditions['operator'], $conditions['rules']) || ! is_array($conditions['rules']) ) {
            return false; // Invalid structure, return false
        }

        $operator         = $conditions['operator'];  // "and" or "or"
        $rules            = $conditions['rules'];     // List of conditions

        $conditionResults = array();                  // Array to store evaluation results

        // Loop through each rule and evaluate it
        foreach ( $rules as $key => $expectedValue ) {
            // If the user data does not have this key, consider it a failed condition
            if ( ! isset($pluginUsageData[$key]) ) {
                if ($operator === 'and') {
                    return false; // At least a condition failed, and the "and" operator is used, thus return false directly
                }

                $conditionResults[] = false;

                continue; // Move to the next rule
            }

            $actualValue   = $pluginUsageData[$key]; // The value from the usage data
            $actualValue   = is_numeric($actualValue)   ? (int) $actualValue   : $actualValue;

            $expectedValue = is_numeric($expectedValue) ? (int) $expectedValue : $expectedValue;

            // Evaluate the condition based on comparison
            $conditionResults[] = $actualValue >= $expectedValue;
        }

        // Determine the final result based on the operator
        if ($operator === 'and') {
            return ! in_array(false, $conditionResults); // No `false` values → true
        } elseif ($operator === 'or') {
            return in_array(true, $conditionResults);    // At least one `true` → true
        }

        return false; // Default to false if operator is invalid
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
            $output = '<div id="pluginann-announcements-container" class="pluginann_hide"></div>'; // This will be filled by the AJAX call
            echo $this->filterOutputForUniquePrefix($output);
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
        check_ajax_referer(Plugin::getConfig('id') . '_announcements_nonce', 'nonce');

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
            $snoozeUntil = current_time('timestamp', true) + $this->snoozeTimeCurrent;
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
     * Purpose: When there are multiple annoucements to be shown at the same time, the moment one is shown
     * Make sure that the next one will not be shown at the next page load to avoid annoying the admins. Instead, snooze it for one hour.
     * If the next annoucement was already snoozed, and there is less than one hour to show up, make sure that the snooze is set to one hour
     *
     * @param $currentShownAnnouncementId
     * @param $showableAnnouncementsIncludingAnySnoozed
     *
     * @return void
     */
    public function snoozeNextAnnouncementsAfterCurrentOne($currentShownAnnouncementId, $showableAnnouncementsIncludingAnySnoozed)
    {
        $currentTime = current_time('timestamp', true);
        $snoozeExtraTimeInSeconds = $this->snoozeTimeForNext;

        foreach ($showableAnnouncementsIncludingAnySnoozed as $ann) {
            $annId = $ann['id'];

            if ($annId === $currentShownAnnouncementId) {
                // No business with the one already shown
                continue;
            }

            $snoozed = isset($ann['snoozed']) && (int)$ann['snoozed'] > 0 ? (int)$ann['snoozed'] : 0;

            if ( ($snoozed > 0 && ($snoozed - $currentTime) < $snoozeExtraTimeInSeconds) || $snoozed === 0 ) {
                $this->updateAnnouncementState($annId, 'snoozed', ($currentTime + $snoozeExtraTimeInSeconds));
            }
        }
    }

    /**
     * This works for all actions (e.g. snooze, seen, never show any)
     *
     * In case the AJAX call is not made (e.g. due to JavaScript errors or AJAX calls are disabled), then a fallback is in place
     * e.g. a "href" in the link that is clicked to reload the page and perform the action
     *
     * @return void
     */
    public function handleFallbackActions()
    {
        $queryStringAction = $this->getQueryStringAction();
        $annIdQuery        = $this->getQueryStringAction() . '_announcement_id';

        $actionType     = isset($_GET[$queryStringAction]) ? sanitize_text_field($_GET[$queryStringAction]) : '';
        $announcementId = isset($_GET[$annIdQuery])        ? sanitize_text_field($_GET[$annIdQuery])        : '';

        if ( empty($actionType) ) {
            return;
        }

        self::updateAnnouncementsViaActionType($actionType, $announcementId);

        // Redirect to the previous URL
        // Clear any irrelevant query strings from the action fallback URL that might cause conflicts
        wp_safe_redirect(
            remove_query_arg(
                array(
                    $annIdQuery,
                    $this->getAnnPrefix(true) . '_clear_announcements_cache',
                    $this->getAnnPrefix(true) . '_clear_announcements_settings'
                )
            )
        );

        exit();
    }

    /**
     * @return void
     */
    public function handleCacheClearingOnRequest()
    {
        $query = $this->getAnnPrefix(true) . '_clear_announcements_cache';

        $proceed = isset( $_GET[$query] ) && Menu::userCanAccessPlugin();

        if ( ! $proceed ) {
            return;
        }

        // Delete the transient cache
        delete_transient( $this->getTransientKey() );
    }

    /**
     * @return void
     */
    public function handleSettingsClearingOnRequest()
    {
        $query = $this->getAnnPrefix(true) . '_clear_announcements_settings';

        $proceed = isset( $_GET[$query] ) && Menu::userCanAccessPlugin();

        if ( ! $proceed ) {
            return;
        }

        $settingsAdmin = new SettingsAdmin();
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
        check_ajax_referer(Plugin::getConfig('id') . '_announcements_nonce', 'nonce');

        $annIdQuery = $this->getAnnIdQuery();

        $actionType     = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        $announcementId = isset($_POST[$annIdQuery])   ? sanitize_text_field($_POST[$annIdQuery]) : '';

        self::updateAnnouncementsViaActionType($actionType, $announcementId, 'ajax');
    }

    /**
     * @return void
     */
    public function reloadAnnouncementsSettingsTab()
    {
        check_ajax_referer(Plugin::getConfig('id') . '_announcements_nonce', 'nonce');

        $settings = new Settings;
        $data = $settings->getAll(); // It will be used in the inclusion

        $data['is_loaded_via_ajax'] = true;

        include_once Plugin::getConfig('dir').'/templates/_admin-page-settings-plugin-areas/_plugin-usage-settings/_announcements.php';

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

        ob_start();
        ?>
            <style>
                .pluginann-custom-tooltip {
                    position: absolute;
                    background-color: #004567; /* Tooltip background */
                    color: #fff; /* Text color */
                    padding: 5px 10px; /* Tooltip padding */
                    border-radius: 4px; /* Rounded corners */
                    font-size: 12px; /* Text size */
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); /* Subtle shadow */
                    white-space: nowrap; /* Prevent text wrapping */
                    z-index: 1000; /* Ensure it appears above other elements */
                    pointer-events: none; /* Prevent interaction */
                    opacity: 0; /* Hidden initially */
                    transition: opacity 0.2s ease-in-out; /* Smooth fade effect */
                }

                /* Show the tooltip */
                .pluginann-custom-tooltip.show {
                    opacity: 1; /* Fully visible */
                }

                /* Add the arrow */
                .pluginann-custom-tooltip::after {
                    content: ''; /* Empty content for the arrow */
                    position: absolute;
                    top: -16px; /* Position the arrow above the tooltip */
                    right: 10px; /* Align the arrow near the top-right corner */
                    border-width: 8px; /* Arrow size */
                    border-style: solid;
                    border-color: transparent transparent #004567 transparent; /* Transparent sides, black bottom */
                }
            </style>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>',
                        nonce   = '<?php echo wp_create_nonce(Plugin::getConfig('id') . '_announcements_nonce'); ?>';

                    <?php
                    if ($this->showAnnouncementWay === 'ajax') {
                    ?>
                        // Create tooltip dynamically
                        $(document).on('mouseenter', '#pluginann-announcements-container .notice-dismiss', function () {
                            const tooltipText = 'Click to mark as Seen';
                            const tooltip = $('<div class="pluginann-custom-tooltip"></div>').text(tooltipText);
                            $('body').append(tooltip);

                            // Position the tooltip below the button, aligned to the left
                            const buttonOffset = $(this).offset();
                            tooltip.css({
                                top: buttonOffset.top + $(this).outerHeight() + 5, // Position below button
                                left: buttonOffset.left - 104, // Align with the left edge of the button
                            }).addClass('show');

                            // Add a fade-in effect
                            tooltip.hide().fadeIn(200);

                            // Store the tooltip reference for later removal
                            $(this).data('tooltip', tooltip);
                        }).on('mouseleave', '#pluginann-announcements-container .notice-dismiss', function () {
                            const tooltip = $(this).data('tooltip');
                            if (tooltip) {
                                tooltip.fadeOut(200, function () {
                                    $(this).remove();
                                });
                            }
                        });

                        $(window).on('resize', function () {
                            $('#pluginann-announcements-container .notice-dismiss .pluginann-custom-tooltip').remove();
                        });

                        // Fill announcement container dinamically
                        $.ajax({
                            url: ajaxUrl,
                            method: 'POST',
                            data: {
                                action: '<?php echo Plugin::getConfig('id'); ?>_fill_announcement_container',
                                nonce: nonce
                            }
                        }).done(function(response) {
                            if (response.success && response.data.html) {
                                $('#pluginann-announcements-container').css({'display': 'none'}).removeClass('pluginann_hide').html(response.data.html).slideDown();

                                // If in the plugin's "Settings" area (other announcements are likely snoozed for one hour after this one was shown)
                                pluginannRefillSettingsAnnouncementsArea();
                            }

                            // Reinitialize dismissible notice functionality
                            $('.pluginann-announcement.is-dismissible').each(function () {
                                var $announcement = $(this);
                                var $buttonAnn = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Mark this announcement as seen.</span></button>');

                                $announcement.append($buttonAnn);

                                // the "X" is clicked on the top right
                                $buttonAnn.on('click', function (event) {
                                    event.preventDefault();

                                    var announcementId = $announcement.attr('data-pluginann-announcement-id');

                                    // Mark it as seen
                                    pluginannSendAnnouncementRequest('seen', announcementId).done(function(response) {
                                        if (response.success) {
                                            $announcement.slideUp();
                                        } else {
                                            console.error(response.data.message || 'Error marking announcement as seen.');
                                        }
                                    }).fail(function() {
                                        console.log('Error processing request. Please try again later!');
                                    });

                                    $announcement.slideUp();
                                });
                            });

                        }).fail(function() {
                            console.error('Error fetching announcements.');
                        });
                    <?php
                    }

                    if ($this->closeAnnouncementWay === 'ajax') {
                    ?>
                        // Fill settings announcements container dinamically (if the admin is on the "Settings" page)
                        // If it gets saved, it should save properly, since the settings were changed after this action
                        function pluginannRefillSettingsAnnouncementsArea()
                        {
                            if ($('#pluginann-settings-annoucements-container').length === 0) {
                                return;
                            }

                            $('#pluginann-settings-announcements-wrap .pluginann-overlay').css({'display':'flex'});

                            $.ajax({
                                url: ajaxUrl,
                                method: 'POST',
                                data: {
                                    action: '<?php echo Plugin::getConfig('id'); ?>_reload_announcements_settings_tab',
                                    nonce: nonce
                                }
                            }).done(function (response) {
                                $('#pluginann-settings-annoucements-container').removeClass('pluginann_hide').html(response);
                                $('#submit').prop('disabled', false);

                                $('#pluginann-settings-announcements-wrap .pluginann-overlay').css({'display':'none'});
                            }).fail(function () {
                                console.error('AJAX Reload Failed: The announcements\' settings were not refetched!');
                            });
                        }

                        // Send request on link click (e.g. snooze, seen, never show any)
                        function pluginannSendAnnouncementRequest(actionType, announcementId) {
                            var requestData = {
                                action: '<?php echo Plugin::getConfig('id'); ?>_announcements_action',
                                nonce: nonce,
                                action_type: actionType
                            };

                            if (announcementId) {
                                requestData.pluginann_announcement_id = announcementId;
                            }

                            $('#submit').prop('disabled', true);

                            return $.ajax({
                                url:    ajaxUrl,
                                method: 'POST',
                                data:   requestData
                            }).done(function(response) {
                                pluginannRefillSettingsAnnouncementsArea(); // if in the plugin's "Settings" area
                            }).fail(function () {
                                console.log('Error processing request. Please try again later!');
                            });
                        }

                        /*
                         * "Remind me Later" click
                         */
                        $(document).on('click', '.pluginann-snooze-it', function(e) {
                            e.preventDefault();

                            var $announcement  = $(this).closest('[data-pluginann-announcement-id]');
                            var announcementId = $announcement.data('pluginann-announcement-id');

                            pluginannSendAnnouncementRequest('snoozed', announcementId).done(function(response) {
                                if (response.success) {
                                    $announcement.slideUp();
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
                        $(document).on('click', '.pluginann-mark-it-as-seen', function(e) {
                            // Case 1: If the actual "Mark as Seen" is clicked that also has the class "pluginann-main-action-link",
                            // prevent its default behaviour (empty link anyway, it acts as a button)

                            // Case 2: If one of the links from the message is clicked with the same "pluginann-mark-it-as-seen" class,
                            // then keep its default behaviour (e.g. opening the link in a new tab), and also trigger the action to mark it as seen
                            if ($(this).hasClass('pluginann-main-action-link')) {
                                e.preventDefault();
                            }

                            var $announcement  = $(this).closest('[data-pluginann-announcement-id]');
                            var announcementId = $announcement.data('pluginann-announcement-id');

                            pluginannSendAnnouncementRequest('seen', announcementId).done(function(response) {
                                if (response.success) {
                                    $announcement.slideUp();
                                } else {
                                    console.log(response.data.message || 'Error marking announcement as seen.');
                                }
                            }).fail(function() {
                                console.log('Error processing request. Please try again later!');
                            });
                        });

                        /*
                         * "Never show any" click
                         */
                        $(document).on('click', '.pluginann-never-show-any', function(e) {
                            e.preventDefault();

                            var $announcement = $(this).closest('[data-pluginann-announcement-id]');

                            pluginannSendAnnouncementRequest('never_show_any').done(function(response) {
                                if (response.success) {
                                    $announcement.slideUp();
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
        echo $this->filterOutputForUniquePrefix(ob_get_clean());
    }

    /**
     * @param $output
     *
     * @return array|string|string[]
     */
    public function filterOutputForUniquePrefix($output)
    {
        return str_replace('pluginann', $this->getAnnPrefix(), $output);
    }
}
