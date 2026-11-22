<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Menu;
use WpAssetCleanUp\MiscArray;
use WpAssetCleanUp\Settings;

/**
 * Main class for handling announcements.
 * Keep notices inline so WordPress leaves them inside their styled announcement container.
 */
class PluginAnnouncements
{
    const UPGRADE_LINK_MIN_TIME_REMAINING = 2 * HOUR_IN_SECONDS;

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
     * How long to suppress repeated requests after a feed failure.
     *
     * @var int
     */
    private $failureTransientTime = 10 * MINUTE_IN_SECONDS;

    /**
     * Maximum number of feed entries sanitized during one request.
     *
     * @var int
     */
    private $maxAnnouncementsToProcess = 50;

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
     * Edition-provided campaign for this admin screen, rendered locally.
     * @var array
     */
    private $bundledAnnouncement = array();

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
            $feedUrl = 'https://drm6aghn7w1h8.cloudfront.net/_wpacu-lite-announcements.json';
        } elseif (WPACU_PLUGIN_SLUG === 'wp-asset-clean-up-pro') {
            $feedUrl = 'https://drm6aghn7w1h8.cloudfront.net/_wpacu-pro-announcements.json';
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
     * @return string
     */
    private function getFallbackNonceAction()
    {
        return Plugin::getConfig('id') . '_announcements_fallback_action';
    }

    /**
     * @return string
     */
    private function getAnnouncementsSettingsUrl()
    {
        return add_query_arg(array(
            'page'                        => Plugin::getConfig('id') . '_settings',
            'wpacu_selected_tab_area'     => 'wpacu-setting-plugin-usage-settings',
            'wpacu_selected_sub_tab_area' => 'wpacu-plugin-usage-settings-announcements',
        ), admin_url('admin.php'));
    }

    /**
     * @return array|string|string[]
     */
    private function getTransientKey()
    {
        return str_replace('pluginann', $this->getAnnPrefix(), $this->transientKey);
    }

    /**
     * Only these built-in campaign themes may be selected by the feed.
     *
     * @return array
     */
    private function campaignThemes()
    {
        return array(
            'black-friday' => 'Black Friday',
            'cyber-monday' => 'Cyber Monday',
            'christmas'    => 'Christmas',
            'anniversary'  => 'Anniversary',
            'spring-sale'  => 'Spring Sale',
            'new-year'     => 'New Year',
            'major-release' => 'Major Release',
            'halloween'    => 'Halloween'
        );
    }

    /**
     * @param array  $ann Sanitized announcement data.
     * @param string $previewLabel Optional local preview label.
     *
     * @return void
     */
    private function renderCampaignContent($ann, $previewLabel = '')
    {
        $themes = $this->campaignThemes();
        $prefix = esc_attr($this->getAnnPrefix());
        ?>
        <?php foreach ($ann['type'] === 'important' ? array() : array('left', 'right') as $side) { ?>
            <span class="<?php echo $prefix; ?>-campaign-decoration <?php echo $prefix; ?>-campaign-decoration-<?php echo esc_attr($side); ?>" aria-hidden="true">
                <span class="<?php echo $prefix; ?>-campaign-decoration-art">
                    <span class="<?php echo $prefix; ?>-campaign-percent"><?php echo $ann['theme'] === 'christmas' ? '✦' : '%'; ?></span>
                    <span class="<?php echo $prefix; ?>-campaign-tag">
                        <?php if ($ann['theme'] === 'halloween') { ?>
                            <svg class="<?php echo $prefix; ?>-pumpkin" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
                                <path d="M29 20 C30 12 34 7 40 8 L41 14 C36 13 35 16 35 21 Z" fill="#65723e"/>
                                <path d="M32 20 C19 10 5 23 7 39 C8 52 18 58 32 55 C46 58 56 52 57 39 C59 23 45 10 32 20 Z" fill="#ed8e32" stroke="#ba632b" stroke-width="2"/>
                                <ellipse cx="32" cy="37" rx="12" ry="19" fill="#f2a247" stroke="#ce762e" stroke-width="2"/>
                                <path d="M20 28 L14 37 L27 35 Z M44 28 L50 37 L37 35 Z M29 40 L35 40 L32 35 Z M17 43 L25 46 L25 43 L30 44 L30 48 L35 48 L35 44 L40 43 L40 46 L47 43 Q32 61 17 43 Z" fill="#4c303b"/>
                            </svg>
                        <?php } ?>
                    </span>
                </span>
            </span>
        <?php } ?>
        <div class="<?php echo $prefix; ?>-campaign-layout">
            <div class="<?php echo $prefix; ?>-campaign-content">
                <span class="<?php echo $prefix; ?>-campaign-label"><?php echo esc_html(strtoupper($themes[$ann['theme']])); ?><?php if ($ann['theme'] === 'anniversary') { ?> · AUGUST 4<?php } ?><?php if ($previewLabel !== '') { ?> <span>· <?php echo esc_html($previewLabel); ?></span><?php } ?></span>
                <?php if ($ann['title'] !== '') { ?>
                    <p class="<?php echo $prefix; ?>-ann-title"><strong><?php echo wp_kses($ann['title'], $this->allowedTitleHtmlTags); ?></strong></p>
                <?php } ?>
                <?php if ($ann['message'] !== '') { ?>
                    <p class="<?php echo $prefix; ?>-ann-message"><?php echo wp_kses($ann['message'], $this->allowedMessageHtmlTags); ?></p>
                <?php } ?>
            </div>
            <?php if ( ! empty($ann['link']) && ! empty($ann['link_label']) ) { ?>
                <div class="<?php echo $prefix; ?>-campaign-action">
                    <a class="button button-primary" href="<?php echo esc_url($ann['link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($ann['link_label']); ?><span aria-hidden="true"> →</span></a>
                </div>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * Add action hooks.
     *
     * @return void
     */
    public function init()
    {
        add_action('init', function () {
            if ( ! Menu::userCanAccessPlugin() || apply_filters('wpacu_internal_hide_communication_notices', false) ) {
                return;
            }

            add_action('current_screen', function ($screen) {
                $records  = apply_filters('wpacu_internal_bundled_announcements', array(), $screen);
                $showable = $this->getShowableAnnouncements($this->sanitizeAnnouncements($records), $includingSnoozed);

                $this->bundledAnnouncement = $showable ? reset($showable) : array();
            });

            // Print the CSS code for the announcements
            add_action('admin_head', array($this, 'adminHead'));

            // Print the scripts for individual announcement actions.
            add_action('admin_footer', array($this, 'displayJsFooter'));

            // Show the container within "admin_notices" either with the whole content or the DIV to be filled via AJAX
            add_action( 'admin_notices', array( $this, 'renderAnnouncementsContainer' ), 1 );

            // Regular way fallback (page reload); This will always load regarding the value of {closeAnnouncementWay} because even if AJAX is used, a fallback is always needed
            add_action('admin_init', array($this, 'handleFallbackActions'));

            if ($this->showAnnouncementWay === 'ajax') {
                // Show announcements (via AJAX)
                add_action('wp_ajax_' . Plugin::getConfig('id') . '_fill_announcement_container', array($this, 'fillAnnouncementContainerAjax'));
            }

            if ($this->closeAnnouncementWay === 'ajax') {
                // Handle individual announcement actions via AJAX.
                add_action('wp_ajax_' . Plugin::getConfig('id') . '_announcements_action', array($this, 'handleAjaxActionRequest'));
            }

            // Reload via AJAX the list of announcements from the area: "Settings" -- "Plugin Usage Preferences" -- "Announcements"
            // In case there are action taken (e.g. from the top announcement shown)
            add_action('wp_ajax_' . Plugin::getConfig('id') . '_reload_announcements_settings_tab', array($this, 'reloadAnnouncementsSettingsTab'));
        });
    }

    /**
     * @param string|null $location Optional display location for a Dashboard notice.
     *
     * @return bool
     */
    public static function isShowAnnouncementsEnabled($location = null)
    {
        $preference = SettingsAdmin::getStoredAnnouncementPreference();
        if ($preference === 'off') {
            return false;
        }

        if ($preference === 'undecided' && ! apply_filters('wpacu_internal_announcements_enabled_by_default', false)) {
            return false;
        }

        if ($location === null) {
            return true;
        }

        $settingsAdmin = new SettingsAdmin();
        return in_array($location, SettingsAdmin::getAnnouncementLocations($settingsAdmin->getOption('announcements')), true);
    }

    /**
     * Identify one of the three site admin areas that can display a notice.
     *
     * @param \WP_Screen|null $screen
     *
     * @return string An empty string for unrelated screens.
     */
    public static function getScreenLocation($screen)
    {
        if ( ! isset($screen->base) ) {
            return '';
        }

        if (in_array($screen->base, array('dashboard', 'plugins'), true)) {
            return $screen->base;
        }

        $page = Menu::isPluginPage();
        if ( ! $page ) {
            return '';
        }

        if ($page === 'settings' && isset($_GET['wpacu_selected_sub_tab_area'])
            && $_GET['wpacu_selected_sub_tab_area'] === 'wpacu-plugin-usage-settings-announcements') {
            return '';
        }

        $suffix = '_page_' . WPACU_PLUGIN_ID . '_' . $page;

        return substr($screen->base, -strlen($suffix)) === $suffix ? 'plugin_pages' : '';
    }

    /**
     * @return bool
     */
    public function _showOnCurrentAdminPage()
    {
        $location = self::getScreenLocation(get_current_screen());
        return $location !== '' && self::isShowAnnouncementsEnabled($location);
    }

    /**
     * @return void
     */
    public function adminHead()
    {
        // Not relevant for this page
        if ( empty($this->bundledAnnouncement) && ! $this->_showOnCurrentAdminPage() ) {
            return;
        }

        $iconsDir = Plugin::getConfig('url') . '/assets/icons/';

        ob_start();
        ?>
        <style>
            /* Settings switches tabs in place, including while a feed request is pending. */
            body:has(.wpacu-settings-tab-link.active[data-wpacu-settings-tab-key="wpacu-setting-plugin-usage-settings"]):has(#wpacu-plugin-usage-settings-announcements-tab-item:checked) #pluginann-announcements-container {
                display: none !important;
            }

            #pluginann-announcements-container {
                margin: 20px 0 0 0;
            }

            #pluginann-announcements-container .notice-info {
                border-left-color: #00a7a7;
                border-top: 1px solid rgba(40, 44, 42, .3);
                border-right: 1px solid rgba(40, 44, 42, .3);
                border-bottom: 1px solid rgba(40, 44, 42, .3);
            }

            #pluginann-announcements-container a.notice-dismiss {
                text-decoration: none;
            }

            #pluginann-announcements-container .notice-info .pluginann-ann-title {
                font-size: 15px;
                margin: 12px 0 10px;
            }

            #pluginann-announcements-container .notice-info .pluginann-ann-message {
                font-size: 14px;
                margin: 12px 0 16px;
            }

            #pluginann-announcements-container .pluginann-ann-title a,
            #pluginann-announcements-container .pluginann-ann-message a {
                text-decoration: underline !important;
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
                display: inline-flex;
                align-items: center;
                float: none;
                margin-right: 10px;
            }

            ul#pluginann-announcement-action-links li + li::before {
                content: "";
                width: 3px;
                height: 3px;
                flex: none;
                margin-inline-end: 10px;
                border-radius: 50%;
                background-color: currentColor;
            }

            ul#pluginann-announcement-action-links li a {
                color: #2271b1;
                display: inline-flex;
                align-items: center;
                gap: 0;
                transition: color 0.3s ease;
            }

            #pluginann-announcements-container #pluginann-announcement-action-links a,
            #pluginann-announcements-container #pluginann-announcement-action-links a span {
                text-decoration: none !important;
            }

            ul#pluginann-announcement-action-links li a .dashicons {
                width: 18px;
                height: 18px;
                margin-right: 5px;
                font-size: 18px;
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

            #pluginann-announcements-container .pluginann-campaign {
                position: relative;
                box-sizing: border-box;
                width: fit-content;
                max-width: calc(100% - 16px);
                margin: 20px 16px 20px 0;
                padding: 20px 32px 20px 20px;
                border: 1px solid #39404c;
                border-left: 4px solid #f4c66a;
                border-right-color: #687181;
                border-radius: 8px;
                background: radial-gradient(ellipse at right center, rgba(244, 198, 106, .18), transparent 65%), linear-gradient(110deg, #171c26, #202633 60%, #343b48);
                color: #eef1f6;
                box-shadow: 0 4px 16px rgba(0, 0, 0, .12);
            }
            #pluginann-announcements-container .pluginann-campaign p {
                color: #eef1f6;
            }
            #pluginann-announcements-container .pluginann-campaign-layout {
                display: grid;
                width: fit-content;
                max-width: 1200px;
                margin: 0;
                grid-template-columns: minmax(0, max-content) auto;
                align-items: center;
                gap: 16px 28px;
            }
            #pluginann-announcements-container .pluginann-campaign-content { min-width: 0; }
            #pluginann-announcements-container .pluginann-campaign-action { max-width: 280px; }
            #pluginann-announcements-container .pluginann-campaign .pluginann-campaign-label {
                display: inline-block;
                margin: 0 0 4px;
                color: #f4c66a;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 1.5px;
            }
            #pluginann-announcements-container .pluginann-campaign .pluginann-ann-title {
                margin: 6px 0 8px;
                color: #fff;
                font-size: 21px;
                line-height: 1.35;
            }
            #pluginann-announcements-container .pluginann-campaign .pluginann-ann-message {
                margin: 0;
                max-width: none;
                line-height: 1.65;
                overflow-wrap: break-word;
            }
            #pluginann-announcements-container .pluginann-campaign a,
            #pluginann-announcements-container .pluginann-campaign .button-link,
            #pluginann-announcements-container .pluginann-campaign .notice-dismiss:before {
                color: #e4e9f1;
            }
            #pluginann-announcements-container .pluginann-campaign a:hover,
            #pluginann-announcements-container .pluginann-campaign .button-link:hover,
            #pluginann-announcements-container .pluginann-campaign .notice-dismiss:hover:before {
                color: #f4c66a;
            }
            #pluginann-announcements-container .pluginann-campaign a.button {
                display: inline-flex;
                gap: 8px;
                justify-content: center;
                align-items: center;
                box-sizing: border-box;
                max-width: 100%;
                min-height: 46px;
                padding: 9px 24px;
                border: 1px solid #f4c66a;
                border-radius: 5px;
                background: #f4c66a;
                color: #171c26;
                font-size: 15px;
                font-weight: 600;
                line-height: 1.5;
                white-space: normal;
                text-align: center;
                text-decoration: none;
                box-shadow: 0 3px 10px rgba(0, 0, 0, .15), inset 0 1px rgba(255, 255, 255, .25);
                text-shadow: none;
                transition: background-color .15s ease, transform .15s ease;
            }
            #pluginann-announcements-container .pluginann-campaign a.button:hover {
                background: #ffdb93;
                border-color: #ffdb93;
                transform: translateY(-1px);
            }
            #pluginann-announcements-container .pluginann-campaign a:focus,
            #pluginann-announcements-container .pluginann-campaign button:focus {
                outline: 2px solid #f4c66a;
                outline-offset: 3px;
                box-shadow: none;
            }
            #pluginann-announcements-container .pluginann-campaign #pluginann-announcement-action-links {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin: 0 0 16px;
            }
            #pluginann-announcements-container .pluginann-campaign #pluginann-announcement-action-links li {
                margin: 0;
            }
            #pluginann-announcements-container .pluginann-campaign .pluginann-icon {
                filter: brightness(0) invert(1);
                opacity: .8;
            }
            #pluginann-announcements-container .pluginann-campaign hr {
                border: 0;
                border-top: 1px solid #465061;
                margin: 16px 0;
            }
            #pluginann-announcements-container .pluginann-campaign .pluginann-preview-note {
                margin: 0;
                color: #bcc5d3;
                font-size: 12px;
            }
            #pluginann-announcements-container .pluginann-preview-toolbar {
                display: flex;
                max-width: 1200px;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 8px 16px;
                margin: 12px 0 0;
                padding-top: 10px;
                border-top: 1px solid rgba(188, 197, 211, .2);
            }
            #pluginann-announcements-container .pluginann-preview-toolbar .button-link { font-size: 12px; }
            #pluginann-announcements-container .pluginann-campaign #pluginann-announcement-action-links { margin: 14px 0 10px; }
            #pluginann-announcements-container hr.pluginann-bundled-divider {
                margin: 12px 0 8px;
                border: 0;
                border-top: 1px solid currentColor;
                color: inherit;
                opacity: .25;
            }
            #pluginann-announcements-container #pluginann-announcement-action-links.pluginann-bundled-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 4px 10px;
                margin: 0;
            }
            #pluginann-announcements-container .pluginann-bundled-actions li { margin: 0; }
            #pluginann-announcements-container .pluginann-bundled-actions a {
                align-items: center;
                gap: 0;
                min-height: 24px;
            }
            #pluginann-announcements-container .pluginann-bundled-actions .dashicons {
                flex: none;
                width: 16px;
                height: 16px;
                margin-right: 5px;
                font-size: 16px;
            }
            #pluginann-announcements-container .pluginann-campaign-decoration { display: none; }
            @supports (container-type: inline-size) and selector(:has(*)) {
                #pluginann-announcements-container:has(.pluginann-campaign) { container-type: inline-size; }
                #pluginann-announcements-container .pluginann-campaign { margin-top: 0; }
                @media (min-width: 1201px) {
                    #pluginann-announcements-container .pluginann-campaign-decoration {
                        display: block;
                        position: absolute;
                        top: 50%;
                        width: clamp(0px, calc(50cqw - 50% - 28px), 200px);
                        height: 140px;
                        transform: translateY(-50%);
                        container-type: inline-size;
                        container-name: pluginann-campaign-wing;
                        overflow: hidden;
                        pointer-events: none;
                        user-select: none;
                    }
                    #pluginann-announcements-container .pluginann-campaign-decoration-left {
                        right: calc(100% + 20px);
                        -webkit-mask-image: linear-gradient(to right, transparent, #000 45%);
                        mask-image: linear-gradient(to right, transparent, #000 45%);
                    }
                    #pluginann-announcements-container .pluginann-campaign-decoration-right {
                        left: calc(100% + 20px);
                        -webkit-mask-image: linear-gradient(to left, transparent, #000 45%);
                        mask-image: linear-gradient(to left, transparent, #000 45%);
                    }
                    #pluginann-announcements-container .pluginann-campaign-decoration-art {
                        display: none;
                        position: absolute;
                        inset: 0;
                        background: radial-gradient(ellipse at center, rgba(244, 198, 106, .12), transparent 70%);
                    }
                    #pluginann-announcements-container .pluginann-campaign-percent {
                        position: absolute;
                        top: 8px;
                        left: 28%;
                        color: rgba(173, 127, 41, .3);
                        font: 700 72px/1.2 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                        transform: rotate(-14deg);
                    }
                    #pluginann-announcements-container .pluginann-campaign-tag {
                        position: absolute;
                        bottom: 12px;
                        right: 18%;
                        width: 42px;
                        height: 28px;
                        border: 2px solid rgba(67, 80, 100, .2);
                        border-radius: 4px 10px 10px 4px;
                        transform: rotate(16deg);
                    }
                    #pluginann-announcements-container .pluginann-campaign-tag:before {
                        content: "";
                        position: absolute;
                        top: 10px;
                        left: 5px;
                        width: 4px;
                        height: 4px;
                        border: 1px solid rgba(67, 80, 100, .25);
                        border-radius: 50%;
                    }
                    #pluginann-announcements-container .pluginann-campaign-decoration-right .pluginann-campaign-percent { transform: rotate(12deg); }
                    #pluginann-announcements-container .pluginann-campaign-decoration-right .pluginann-campaign-tag { right: auto; left: 18%; transform: rotate(-16deg); }
                    @container pluginann-campaign-wing (min-width: 112px) {
                        #pluginann-announcements-container .pluginann-campaign-decoration-art { display: block; }
                    }
                }
            }
            @media (min-width: 1201px) {
                #pluginann-announcements-container .pluginann-campaign { margin-left: auto; margin-right: auto; }
            }
            @media (max-width: 960px) {
                #pluginann-announcements-container .pluginann-campaign { width: calc(100% - 16px); }
                #pluginann-announcements-container .pluginann-campaign-layout { width: auto; grid-template-columns: minmax(0, 1fr); }
                #pluginann-announcements-container .pluginann-campaign-action { max-width: 100%; }
            }
            @media (prefers-reduced-motion: reduce) {
                #pluginann-announcements-container .pluginann-campaign a.button { transition: none; }
                #pluginann-announcements-container .pluginann-campaign a.button:hover { transform: none; }
            }
            @media (max-width: 600px) {
                #pluginann-announcements-container .pluginann-campaign { padding: 16px; }
                #pluginann-announcements-container .pluginann-campaign .pluginann-ann-title { font-size: 20px; }
            }
            #pluginann-announcements-container .pluginann-theme-cyber-monday { border-left-color: #8beaf4; border-right-color: #6578ae; background: radial-gradient(ellipse at right center, rgba(124, 98, 235, .32), transparent 65%), linear-gradient(115deg, #121d32, #182944 60%, #30335c); }
            #pluginann-announcements-container .pluginann-theme-cyber-monday .pluginann-campaign-label { color: #8beaf4; }
            #pluginann-announcements-container .pluginann-theme-cyber-monday a.button { background: #8beaf4; border-color: #8beaf4; color: #102334; }
            #pluginann-announcements-container .pluginann-theme-cyber-monday a.button:hover { background: #b3f4fa; border-color: #b3f4fa; }
            #pluginann-announcements-container .pluginann-theme-cyber-monday a:focus,
            #pluginann-announcements-container .pluginann-theme-cyber-monday button:focus { outline-color: #8beaf4; }
            #pluginann-announcements-container .pluginann-theme-christmas {
                border-left-color: #e9c58b;
                border-right-color: #ae6263;
                background: radial-gradient(ellipse at right top, rgba(218, 91, 74, .3), transparent 65%), linear-gradient(115deg, #480f1d, #761e2b 60%, #903039);
            }
            #pluginann-announcements-container .pluginann-theme-christmas .pluginann-campaign-label { color: #ffe3ad; }
            #pluginann-announcements-container .pluginann-theme-christmas a.button { background: #174b36; border-color: #72aa84; color: #fff4dd; }
            #pluginann-announcements-container .pluginann-theme-christmas a.button:hover { background: #216448; border-color: #9ac9a8; color: #fff8eb; }
            #pluginann-announcements-container .pluginann-theme-christmas a:focus,
            #pluginann-announcements-container .pluginann-theme-christmas button:focus { outline-color: #f4d99f; }
            #pluginann-announcements-container .pluginann-theme-cyber-monday .pluginann-campaign-percent { color: rgba(59, 111, 180, .3); }
            #pluginann-announcements-container .pluginann-theme-cyber-monday .pluginann-campaign-decoration-art { background: radial-gradient(ellipse at center, rgba(112, 124, 232, .12), transparent 70%); }
            #pluginann-announcements-container .pluginann-theme-christmas .pluginann-campaign-percent { color: rgba(157, 42, 52, .32); }
            #pluginann-announcements-container .pluginann-theme-christmas .pluginann-campaign-decoration-art { background: radial-gradient(ellipse at center, rgba(177, 59, 63, .1), transparent 70%); }
            #pluginann-announcements-container .pluginann-theme-christmas .pluginann-campaign-tag { border-radius: 3px; border-color: rgba(36, 104, 67, .4); }
            #pluginann-announcements-container .pluginann-theme-christmas .pluginann-campaign-tag:before { top: -2px; left: 17px; width: 5px; height: 28px; border: solid rgba(157, 42, 52, .4); border-width: 0 2px; border-radius: 0; }
            #pluginann-announcements-container .pluginann-theme-christmas .pluginann-campaign-tag:after { content: ""; position: absolute; top: 7px; left: 0; right: 0; border-top: 3px solid rgba(157, 42, 52, .4); }
            #pluginann-announcements-container .pluginann-theme-anniversary {
                border-left-color: #f2ce83;
                border-right-color: #8c76a9;
                background: radial-gradient(ellipse at right center, rgba(242, 206, 131, .2), transparent 65%), linear-gradient(115deg, #251b38, #3b2856 60%, #514362);
            }
            #pluginann-announcements-container .pluginann-theme-anniversary .pluginann-campaign-label { color: #f2ce83; }
            #pluginann-announcements-container .pluginann-theme-anniversary a.button { background: #f2ce83; border-color: #f2ce83; color: #291d3b; }
            #pluginann-announcements-container .pluginann-theme-anniversary a.button:hover { background: #ffe3a7; border-color: #ffe3a7; }
            #pluginann-announcements-container .pluginann-theme-anniversary a:focus,
            #pluginann-announcements-container .pluginann-theme-anniversary button:focus { outline-color: #f2ce83; }
            #pluginann-announcements-container .pluginann-theme-anniversary .pluginann-campaign-percent { color: rgba(122, 83, 170, .3); }
            #pluginann-announcements-container .pluginann-theme-anniversary .pluginann-campaign-decoration-art { background: radial-gradient(ellipse at center, rgba(176, 139, 221, .12), transparent 70%); }
            #pluginann-announcements-container .pluginann-theme-anniversary .pluginann-campaign-tag { border: 0; }
            #pluginann-announcements-container .pluginann-theme-anniversary .pluginann-campaign-tag:before { content: "✦"; top: -7px; left: 0; width: auto; height: auto; border: 0; color: rgba(173, 127, 41, .3); font-size: 36px; line-height: 1; }
            #pluginann-announcements-container .pluginann-theme-spring-sale { border-left-color: #b9ecae; border-right-color: #74a485; background: radial-gradient(ellipse at right top, rgba(169, 213, 101, .25), transparent 65%), linear-gradient(115deg, #12382c, #235740 60%, #426b41); }
            #pluginann-announcements-container .pluginann-theme-spring-sale .pluginann-campaign-label { color: #b9ecae; }
            #pluginann-announcements-container .pluginann-theme-spring-sale a.button { background: #b9ecae; border-color: #b9ecae; color: #173923; }
            #pluginann-announcements-container .pluginann-theme-spring-sale a.button:hover { background: #d4f6cb; border-color: #d4f6cb; }
            #pluginann-announcements-container .pluginann-theme-spring-sale a:focus,
            #pluginann-announcements-container .pluginann-theme-spring-sale button:focus { outline-color: #b9ecae; }
            #pluginann-announcements-container .pluginann-theme-spring-sale .pluginann-campaign-percent { color: rgba(75, 134, 69, .3); }
            #pluginann-announcements-container .pluginann-theme-spring-sale .pluginann-campaign-decoration-art { background: radial-gradient(ellipse at center, rgba(75, 134, 69, .12), transparent 70%); }
            #pluginann-announcements-container .pluginann-theme-new-year { border-left-color: #f8e4bd; border-right-color: #7788ac; background: radial-gradient(ellipse at right top, rgba(212, 185, 121, .23), transparent 65%), linear-gradient(115deg, #111f3a, #243b5f 60%, #435574); }
            #pluginann-announcements-container .pluginann-theme-new-year .pluginann-campaign-label { color: #f8e4bd; }
            #pluginann-announcements-container .pluginann-theme-new-year a.button { background: #f8e4bd; border-color: #f8e4bd; color: #19243e; }
            #pluginann-announcements-container .pluginann-theme-new-year a.button:hover { background: #fff1d8; border-color: #fff1d8; }
            #pluginann-announcements-container .pluginann-theme-new-year a:focus,
            #pluginann-announcements-container .pluginann-theme-new-year button:focus { outline-color: #f8e4bd; }
            #pluginann-announcements-container .pluginann-theme-new-year .pluginann-campaign-percent { color: rgba(97, 117, 158, .3); }
            #pluginann-announcements-container .pluginann-theme-new-year .pluginann-campaign-decoration-art { background: radial-gradient(ellipse at center, rgba(97, 117, 158, .12), transparent 70%); }
            #pluginann-announcements-container .pluginann-theme-major-release { border-left-color: #86e3cf; border-right-color: #589b9a; background: radial-gradient(ellipse at right center, rgba(50, 171, 170, .23), transparent 65%), linear-gradient(115deg, #12343e, #1b5360 60%, #286c72); }
            #pluginann-announcements-container .pluginann-theme-major-release .pluginann-campaign-label { color: #86e3cf; }
            #pluginann-announcements-container .pluginann-theme-major-release a.button { background: #86e3cf; border-color: #86e3cf; color: #123b39; }
            #pluginann-announcements-container .pluginann-theme-major-release a.button:hover { background: #b4f5e5; border-color: #b4f5e5; }
            #pluginann-announcements-container .pluginann-theme-major-release a:focus,
            #pluginann-announcements-container .pluginann-theme-major-release button:focus { outline-color: #86e3cf; }
            #pluginann-announcements-container .pluginann-theme-major-release .pluginann-campaign-percent { color: rgba(41, 135, 138, .3); }
            #pluginann-announcements-container .pluginann-theme-major-release .pluginann-campaign-decoration-art { background: radial-gradient(ellipse at center, rgba(41, 135, 138, .12), transparent 70%); }
            #pluginann-announcements-container .pluginann-theme-halloween { border-left-color: #ffb267; border-right-color: #9d704e; background: radial-gradient(ellipse at right center, rgba(220, 109, 35, .28), transparent 65%), linear-gradient(115deg, #281b32, #432544 60%, #603b3c); }
            #pluginann-announcements-container .pluginann-theme-halloween .pluginann-campaign-label { color: #ffb267; }
            #pluginann-announcements-container .pluginann-theme-halloween a.button { background: #ffb267; border-color: #ffb267; color: #352015; }
            #pluginann-announcements-container .pluginann-theme-halloween a.button:hover { background: #ffd09a; border-color: #ffd09a; }
            #pluginann-announcements-container .pluginann-theme-halloween a:focus,
            #pluginann-announcements-container .pluginann-theme-halloween button:focus { outline-color: #ffb267; }
            #pluginann-announcements-container .pluginann-theme-halloween .pluginann-campaign-percent { color: rgba(190, 108, 43, .3); }
            #pluginann-announcements-container .pluginann-theme-halloween .pluginann-campaign-decoration-art { background: radial-gradient(ellipse at center, rgba(190, 108, 43, .12), transparent 70%); }
            #pluginann-announcements-container .pluginann-theme-halloween .pluginann-campaign-tag { width: 58px; height: 58px; bottom: 2px; border: 0; }
            #pluginann-announcements-container .pluginann-theme-halloween .pluginann-campaign-tag:before { display: none; }
            #pluginann-announcements-container .pluginann-pumpkin { display: block; width: 100%; height: 100%; opacity: .45; }
        </style>
        <?php
        echo $this->filterOutputForUniquePrefix(ob_get_clean());
    }

    /**
     * Return the feed records allowed by the site's current announcement preference.
     *
     * @return array
     */
    public function getAnnouncementsFromTheFeed()
    {
        // Check the site's announcement preference before accessing the cache or feed.
        if ( ! self::isShowAnnouncementsEnabled() ) {
            return array();
        }

        $announcements = $this->readAnnouncementFeed(true);
        if (SettingsAdmin::getStoredAnnouncementPreference() === 'important') {
            return array_values(array_filter($announcements, function ($ann) {
                return $ann['type'] === 'important';
            }));
        }

        return $announcements;
    }

    /**
     * Read the public upgrade offer independently of banner audience and dismissal.
     * Upgrade links use the cached feed without initiating an HTTP request.
     *
     * @return int
     */
    public function getBlackFridayUpgradeDiscount()
    {
        if ( ! self::isShowAnnouncementsEnabled() || ! Menu::userCanAccessPlugin() ) {
            return 0;
        }

        $now = current_time('timestamp', true);
        foreach ($this->readAnnouncementFeed(false) as $record) {
            if ( ! isset($record['theme']) || $record['theme'] !== 'black-friday'
                || $record['type'] === 'important'
                || $record['start_time_unix'] > $now || $record['end_time_unix'] - $now < self::UPGRADE_LINK_MIN_TIME_REMAINING ) {
                continue;
            }

            if (isset($record['conditions']) && ! self::isMatchForExtraConditions($record['conditions'], Plugin::getPluginUsageData($record['conditions']))) {
                continue;
            }

            if (isset($record['menu_discount_percent'])) {
                return $record['menu_discount_percent'];
            }

            // Older published feeds carry the exact discount in the upgrade button.
            if (isset($record['link_label']) && preg_match('/^Upgrade\s+(?:&|and)\s+Save\s+([1-9][0-9]?|100)%$/i', $record['link_label'], $match)) {
                return (int)$match[1];
            }
        }

        return 0;
    }

    /**
     * Cache the full, sanitized feed so changing the preference takes effect
     * immediately without a second download.
     *
     * @param bool $allowFetch Whether to request the feed on a cache miss.
     *
     * @return array
     */
    private function readAnnouncementFeed($allowFetch)
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
                    return $this->sanitizeAnnouncements($maybeArrayFromJson);
                }

                // Try to recover if it's serialized
                $maybeUnserialized = maybe_unserialize($announcements);

                if ( is_array( $maybeUnserialized ) && ! empty( $maybeUnserialized ) ) {
                    return $this->sanitizeAnnouncements($maybeUnserialized);
                }
            }

            if ($allowFetch) {
                delete_transient( $this->getTransientKey() );
            }
        }

        if ( ! $allowFetch ) {
            return array();
        }

        $fetchUrl = add_query_arg( $this->getAnnPrefix(), wp_rand(), $this->getFeedUrl() );

        $response = wp_safe_remote_get( $fetchUrl, array(
            'timeout'             => 8,
            'limit_response_size' => 262144,
            'headers' => array(
                'User-Agent'    => 'WordPress-Plugin',
                'Cache-Control' => 'no-cache',
                'Pragma'        => 'no-cache',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            $this->cacheFeedFailure();
            return array();
        }

        $responseCode = (int)wp_remote_retrieve_response_code($response);

        if ($responseCode < 200 || $responseCode >= 300) {
            $this->cacheFeedFailure();
            return array();
        }

        $body          = wp_remote_retrieve_body( $response );
        $announcements = json_decode( $body, true );

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array( $announcements )) {
            $this->cacheFeedFailure();
            return array();
        }

        $announcements = $this->sanitizeAnnouncements($announcements);

        set_transient($this->getTransientKey(), $announcements, $this->transientTime);

        return $announcements;
    }

    /**
     * Cache an empty result briefly so an unavailable or invalid feed does not
     * delay every eligible Dashboard request.
     *
     * @return void
     */
    private function cacheFeedFailure()
    {
        set_transient($this->getTransientKey(), array(), $this->failureTransientTime);
    }

    /**
     * Sanitize announcements to ensure valid structure and priorities.
     *
     * @param array $announcements List of announcements.
     * @return array
     */
    private function sanitizeAnnouncements($announcements)
    {
        if ( ! is_array($announcements) || empty($announcements)) {
            return array();
        }

        $sanitizedAnnouncements = array();
        $usedIds = array();

        $announcements = array_slice($announcements, 0, $this->maxAnnouncementsToProcess);

        foreach ($announcements as $ann) {
            if ( ! is_array($ann) ) {
                continue;
            }

            if ( ! isset($ann['id']) || ! is_scalar($ann['id'])) {
                continue;
            }

            $id = $this->limitAnnouncementString(sanitize_text_field((string)$ann['id']), 191);
            if ($id === '' || isset($usedIds[$id])) {
                continue;
            }

            $startTime = isset($ann['start_date']) ? self::parseAnnouncementDate($ann['start_date']) : false;
            $endTime   = isset($ann['end_date']) ? self::parseAnnouncementDate($ann['end_date']) : false;
            if ($startTime === false || $endTime === false || $endTime < $startTime) {
                continue;
            }

            $title = isset($ann['title']) && is_string($ann['title']) ? force_balance_tags(wp_kses($this->limitAnnouncementString($ann['title'], 500), $this->allowedTitleHtmlTags)) : '';
            $message = isset($ann['message']) && is_string($ann['message']) ? force_balance_tags(wp_kses($this->limitAnnouncementString($ann['message'], 20000), $this->allowedMessageHtmlTags)) : '';
            if (trim(wp_strip_all_tags($title)) === '' && trim(wp_strip_all_tags($message)) === '') {
                continue;
            }

            $priority = isset($ann['priority']) && is_string($ann['priority']) ? strtolower(trim($ann['priority'])) : 'low';
            $priority = isset($this->priorityLevels[$priority]) ? $priority : 'low';
            $type     = isset($ann['type']) && in_array($ann['type'], array('important', 'offer'), true) ? $ann['type'] : 'general';
            $sanitized = array(
                'id' => $id,
                'title' => $title,
                'message' => $message,
                'priority' => $priority,
                'type' => $type,
                'start_date' => gmdate('Y-m-d H:i:s', $startTime),
                'end_date' => gmdate('Y-m-d H:i:s', $endTime),
                'start_time_unix' => $startTime,
                'end_time_unix' => $endTime,
            );

            if (isset($ann['menu_discount_percent']) && is_int($ann['menu_discount_percent'])
                && $ann['menu_discount_percent'] >= 1 && $ann['menu_discount_percent'] <= 100) {
                $sanitized['menu_discount_percent'] = $ann['menu_discount_percent'];
            }

            if (isset($ann['theme']) && is_string($ann['theme']) && array_key_exists($ann['theme'], $this->campaignThemes())) {
                $sanitized['theme'] = $ann['theme'];
            }

            if (isset($ann['link']) && is_string($ann['link'])) {
                $link = esc_url_raw($this->limitAnnouncementString(trim($ann['link']), 2048));
                if ($link !== '') {
                    $sanitized['link'] = $link;
                }
            }
            if ( ! empty($sanitized['link']) && isset($ann['link_label']) && is_string($ann['link_label']) ) {
                $sanitized['link_label'] = sanitize_text_field($this->limitAnnouncementString($ann['link_label'], 120));
            }
            if (array_key_exists('conditions', $ann)) {
                if ( ! is_array($ann['conditions'])) {
                    continue;
                }

                $operator = isset($ann['conditions']['operator']) && is_string($ann['conditions']['operator'])
                    ? strtolower(trim($ann['conditions']['operator']))
                    : '';
                $rules = array();

                if (in_array($operator, array('and', 'or'), true) && isset($ann['conditions']['rules']) && is_array($ann['conditions']['rules'])) {
                    foreach ($ann['conditions']['rules'] as $ruleKey => $ruleValue) {
                        if (is_string($ruleKey) && is_scalar($ruleValue) && is_numeric($ruleValue)) {
                            $rules[sanitize_key($ruleKey)] = (int)$ruleValue;
                        }
                    }
                }

                if ( ! in_array($operator, array('and', 'or'), true) || empty($rules)) {
                    continue;
                }

                $sanitized['conditions'] = array('operator' => $operator, 'rules' => $rules);
            }

            $usedIds[$id] = true;
            $sanitizedAnnouncements[] = $sanitized;
        }

        return $sanitizedAnnouncements;
    }

    /**
     * Parse a UTC campaign date from a bundled or online announcement.
     *
     * @param mixed $value
     *
     * @return int|false
     */
    public static function parseAnnouncementDate($value)
    {
        if ( ! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d H:i:s') === $value ? $date->getTimestamp() : false;
    }

    private function limitAnnouncementString($value, $maxLength)
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
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
            $startTime = isset($ann['start_time_unix']) ? (int)$ann['start_time_unix'] : 0;
            $endTime   = isset($ann['end_time_unix']) ? (int)$ann['end_time_unix'] : 0;

            if ($startTime <= 0 || $endTime <= 0 || $endTime < $startTime) {
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
     */
    public static function feedUnixToWordPressUnix($feedUnix)
    {
        $feedUnix = (int)$feedUnix;

        if (function_exists('wp_timezone') && class_exists('\DateTimeImmutable')) {
            $date = new \DateTimeImmutable('@' . $feedUnix);
            return $feedUnix + wp_timezone()->getOffset($date);
        }

        // WordPress versions older than 5.3 do not provide wp_timezone().
        // Use the numeric offset directly; this also supports fractional offsets safely.
        return $feedUnix + (int)round((float)get_option('gmt_offset') * HOUR_IN_SECONDS);
    }

    /**
     * Formats a UTC feed timestamp in the timezone configured for the WordPress site.
     *
     * @param string $format
     * @param int    $feedUnix
     *
     * @return string
     */
    public static function formatFeedUnixForSite($format, $feedUnix)
    {
        $feedUnix = (int)$feedUnix;

        if (function_exists('wp_date') && function_exists('wp_timezone')) {
            return wp_date($format, $feedUnix, wp_timezone());
        }

        return date_i18n($format, self::feedUnixToWordPressUnix($feedUnix));
    }

    /**
     * Use edition-specific classes around the publisher's sanitized content.
     *
     * @param array $ann Sanitized announcement from the local or remote feed.
     *
     * @return void
     */
    private function renderAnnouncementBody($ann)
    {
        if (isset($ann['theme']) && array_key_exists($ann['theme'], $this->campaignThemes())) {
            $this->renderCampaignContent($ann);
            return;
        }

        $prefix = esc_attr($this->getAnnPrefix());
        if ( ! empty($ann['title']) ) {
            ?><p class="<?php echo $prefix; ?>-ann-title"><strong><?php echo wp_kses($ann['title'], $this->allowedTitleHtmlTags); ?></strong></p><?php
        }
        if ( ! empty($ann['message']) ) {
            ?><p class="<?php echo $prefix; ?>-ann-message"><?php echo wp_kses($ann['message'], $this->allowedMessageHtmlTags); ?></p><?php
        }
    }

    /**
     * Select active, eligible records in priority order. Records of equal
     * priority retain their order in the export, including on PHP 5.6.
     *
     * @param array $announcements
     * @param array $includingSnoozed Eligible records used to stagger subsequent online notices.
     *
     * @return array
     */
    private function getShowableAnnouncements($announcements, &$includingSnoozed)
    {
        $includingSnoozed = array();
        if ( empty($announcements) ) {
            return array();
        }

        $settingsAdmin = new SettingsAdmin();
        $settings      = $settingsAdmin->getOption('announcements');
        $now           = current_time('timestamp', true);
        $byPriority    = array('high' => array(), 'medium' => array(), 'low' => array());

        foreach ($announcements as $ann) {
            $id = isset($ann['id']) ? (string)$ann['id'] : '';
            if ($id === '' || (empty($ann['title']) && empty($ann['message'])) || ! empty($settings['list'][$id]['seen'])) {
                continue;
            }

            if (isset($_COOKIE[self::getSnoozeCookieName($id)])) {
                continue;
            }

            $start = isset($ann['start_time_unix']) ? (int)$ann['start_time_unix'] : 0;
            $end   = isset($ann['end_time_unix']) ? (int)$ann['end_time_unix'] : 0;
            if ($start <= 0 || $now < $start || $now > $end) {
                continue;
            }

            $conditions = isset($ann['conditions']) ? $ann['conditions'] : array();
            if ( ! self::isMatchForExtraConditions($conditions, Plugin::getPluginUsageData($conditions)) ) {
                continue;
            }

            $snoozed = isset($settings['list'][$id]['snoozed']) ? (int)$settings['list'][$id]['snoozed'] : 0;
            if ($snoozed > $now) {
                $ann['snoozed']     = $snoozed;
                $includingSnoozed[] = $ann;
                continue;
            }

            $priority                = isset($ann['priority'], $byPriority[$ann['priority']]) ? $ann['priority'] : 'low';
            $byPriority[$priority][] = $ann;
            $includingSnoozed[]      = $ann;
        }

        return array_merge($byPriority['high'], $byPriority['medium'], $byPriority['low']);
    }

    /**
     * @param string $isCallType
     * @param string $fallbackBaseUrl URL of the admin page to use for non-AJAX fallback links.
     *
     * Display only ONE highest priority unsnoozed and unseen announcement
     *
     * @return void
     */
    public function displayOneAnnouncement($isCallType = 'regular', $fallbackBaseUrl = '')
    {
        $showableAnnouncementsNow = $this->getShowableAnnouncements($this->getAnnouncementsFromTheFeed(), $showableAnnouncementsIncludingAnySnoozed);
        if ( empty($showableAnnouncementsNow) ) {
            return;
        }

        $ann      = reset($showableAnnouncementsNow);
        $annId    = (string)$ann['id'];
        $prefix   = esc_attr($this->getAnnPrefix());
        $priority = isset($ann['priority']) ? $ann['priority'] : 'low';

        $showRemindMeLaterAction = true;

        if ( (current_time('timestamp', true) + $this->snoozeTimeCurrent) > $ann['end_time_unix'] ) {
            // By the time it should technically show up, it will expire
            // The admin can view it in the "Settings" -- "Plugin Usage Preferences" -- "Announcements"
            $showRemindMeLaterAction = false;
        }

        $queryStringAction = $this->getQueryStringAction();
        $annIdQuery        = $this->getAnnIdQuery();
        $fallbackNonce     = wp_create_nonce($this->getFallbackNonceAction());

        // When the announcement HTML is generated through admin-ajax.php, add_query_arg()
        // would otherwise use the AJAX endpoint itself as the link base. Passing the page
        // URL explicitly keeps these links usable as true non-JavaScript/AJAX fallbacks.
        $fallbackUrlBase = $fallbackBaseUrl !== '' ? $fallbackBaseUrl : false;

        if ($showRemindMeLaterAction) {
            // /?{$queryStringAction}=snoozed&{$annIdQuery}={id}&_wpnonce={nonce}
            $fallbackUrlRemindLater = add_query_arg(
                array(
                    $queryStringAction => 'snoozed',
                    $annIdQuery        => rawurlencode($annId),
                    '_wpnonce'         => $fallbackNonce,
                ),
                $fallbackUrlBase
            );
        }

        // /?{$queryStringAction}=seen&{$annIdQuery}={id}&_wpnonce={nonce}
        $fallbackUrlMarkAsSeen = add_query_arg(
            array(
                $queryStringAction => 'seen',
                $annIdQuery        => rawurlencode($annId),
                '_wpnonce'         => $fallbackNonce,
            ),
            $fallbackUrlBase
        );

        ?>
                <?php if ($isCallType === 'regular') { ?>
                    <div id="<?php echo $prefix; ?>-announcements-container">
                <?php } ?>
                        <div class="notice notice-info inline is-dismissible <?php echo $prefix; ?>-announcement<?php if (isset($ann['theme']) && array_key_exists($ann['theme'], $this->campaignThemes())) { ?> <?php echo $prefix; ?>-campaign <?php echo $prefix; ?>-theme-<?php echo esc_attr($ann['theme']); ?><?php } ?>"
                             data-<?php echo $prefix; ?>-annoucement-priority="<?php echo esc_attr($priority); ?>"
                             data-<?php echo $prefix; ?>-announcement-id="<?php echo esc_attr( $annId ); ?>">

                            <?php $this->renderAnnouncementBody($ann); ?>

                            <!-- [Action links] -->
                            <ul id="<?php echo $prefix; ?>-announcement-action-links">
                                <?php if ($showRemindMeLaterAction) { ?>
                                    <li><a href="<?php echo esc_url( $fallbackUrlRemindLater ); ?>"  class="<?php echo $prefix; ?>-snooze-it"><span class="<?php echo $prefix; ?>-icon <?php echo $prefix; ?>-snooze" aria-hidden="true"></span> <?php esc_html_e('Snooze for 24h', 'wp-asset-clean-up'); ?></a></li>
                                <?php } ?>

                                <li><a href="<?php echo esc_url( $fallbackUrlMarkAsSeen ); ?>"   class="<?php echo $prefix; ?>-mark-it-as-seen <?php echo $prefix; ?>-main-action-link"><span class="<?php echo $prefix; ?>-icon <?php echo $prefix; ?>-seen" aria-hidden="true"></span> <?php esc_html_e('Dismiss this announcement', 'wp-asset-clean-up'); ?></a></li>
                                <li><a class="wpacu-announcement-preferences" href="<?php echo esc_url($this->getAnnouncementsSettingsUrl()); ?>"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span><span><?php esc_html_e('Preferences', 'wp-asset-clean-up'); ?></span></a></li>
                            </ul>
                            <!-- [/Action links] -->
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

    }

    /**
     * @param $conditions
     * @param $pluginUsageData
     *
     * @return bool
     */
    public static function isMatchForExtraConditions($conditions, $pluginUsageData)
    {
        // No conditions means that the announcement has no additional usage
        // restrictions. A present but malformed block is rejected below.
        if (empty($conditions)) {
            return true;
        }

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
     * @param string $announcementId
     *
     * @return string Browser fallback scoped to this site, user and campaign.
     */
    public static function getSnoozeCookieName($announcementId)
    {
        return 'wpacu_snooze_' . md5(admin_url() . '|' . get_current_user_id() . '|' . $announcementId);
    }

    /**
     * Render the bundled announcement and its actions, including snoozing without JavaScript.
     *
     * @param array $ann
     *
     * @return void
     */
    private function renderBundledAnnouncement($ann)
    {
        $prefix     = esc_attr($this->getAnnPrefix());
        $actionKey  = $this->getQueryStringAction();
        $actionArgs = array(
            $this->getAnnIdQuery() => rawurlencode($ann['id']),
            '_wpnonce'             => wp_create_nonce($this->getFallbackNonceAction()),
        );
        $snoozeUrl    = add_query_arg(array($actionKey => 'snoozed') + $actionArgs);
        $dismissUrl   = add_query_arg(array($actionKey => 'seen') + $actionArgs);

        ?>
        <div id="<?php echo $prefix; ?>-announcements-container">
            <div class="notice notice-info inline <?php echo $prefix; ?>-announcement<?php if (isset($ann['theme'])) { ?> <?php echo $prefix; ?>-campaign <?php echo $prefix; ?>-theme-<?php echo esc_attr($ann['theme']); ?><?php } ?>" data-<?php echo $prefix; ?>-announcement-id="<?php echo esc_attr($ann['id']); ?>">
                <?php $this->renderAnnouncementBody($ann); ?>
                <a class="notice-dismiss" data-wpacu-notice-action="seen" href="<?php echo esc_url($dismissUrl); ?>"><span class="screen-reader-text"><?php esc_html_e('Dismiss this announcement permanently', 'wp-asset-clean-up'); ?></span></a>
                <hr class="<?php echo $prefix; ?>-bundled-divider" aria-hidden="true">
                <ul id="<?php echo $prefix; ?>-announcement-action-links" class="<?php echo $prefix; ?>-bundled-actions">
                    <?php if (current_time('timestamp', true) + $this->snoozeTimeCurrent <= $ann['end_time_unix']) { ?>
                        <li><a data-wpacu-notice-action="snoozed" href="<?php echo esc_url($snoozeUrl); ?>"><span class="dashicons dashicons-clock" aria-hidden="true"></span><?php esc_html_e('Snooze for 24h', 'wp-asset-clean-up'); ?></a></li>
                    <?php } ?>
                    <li><a data-wpacu-notice-action="seen" href="<?php echo esc_url($dismissUrl); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span><?php esc_html_e('Dismiss this announcement', 'wp-asset-clean-up'); ?></a></li>
                    <li><a class="wpacu-announcement-preferences" href="<?php echo esc_url($this->getAnnouncementsSettingsUrl()); ?>"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span><span><?php esc_html_e('Preferences', 'wp-asset-clean-up'); ?></span></a></li>
                </ul>
                <span role="alert" class="wpacu-bundled-action-error"></span>
            </div>
        </div>
        <?php if ($this->closeAnnouncementWay === 'ajax') { ?>
        <script>
        (function () {
            var container = document.getElementById('<?php echo $prefix; ?>-announcements-container');
            var error = container.querySelector('.wpacu-bundled-action-error');
            var cookieName = <?php echo wp_json_encode(self::getSnoozeCookieName($ann['id'])); ?>;
            var cookieScope = '; Path=' + <?php echo wp_json_encode(parse_url(admin_url(), PHP_URL_PATH)); ?> + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');

            container.addEventListener('click', function (event) {
                var link = event.target.closest('a[data-wpacu-notice-action]');
                if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) { return; }
                if (container.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
                var action = link.getAttribute('data-wpacu-notice-action');
                var snoozedHere = false;
                if (action === 'snoozed') {
                    try {
                        document.cookie = cookieName + '=1; Max-Age=<?php echo (int)$this->snoozeTimeCurrent; ?>' + cookieScope;
                        snoozedHere = document.cookie.split('; ').indexOf(cookieName + '=1') !== -1;
                    } catch (ignore) {}
                }
                if (!window.fetch || !window.FormData) { return; }
                event.preventDefault();
                container.setAttribute('aria-busy', 'true');
                error.textContent = '';

                var data = new FormData();
                data.append('action', '<?php echo esc_js(Plugin::getConfig('id')); ?>_announcements_action');
                data.append('nonce', '<?php echo esc_js(wp_create_nonce(Plugin::getConfig('id') . '_announcements_nonce')); ?>');
                data.append('action_type', action);
                data.append('<?php echo esc_js($this->getAnnIdQuery()); ?>', container.querySelector('[data-<?php echo $prefix; ?>-announcement-id]').getAttribute('data-<?php echo $prefix; ?>-announcement-id'));

                if (snoozedHere) { container.remove(); }
                fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, {
                    method: 'POST', credentials: 'same-origin', body: data
                }).then(function (response) {
                    return response.json();
                }).then(function (result) {
                    if (!result || result.success !== true) { throw new Error(); }
                    if (snoozedHere) { document.cookie = cookieName + '=; Max-Age=0' + cookieScope; }
                    container.remove();
                }).catch(function () {
                    if (snoozedHere) { return; }
                    container.removeAttribute('aria-busy');
                    error.textContent = <?php echo wp_json_encode(__('Could not save your choice. Please try again.', 'wp-asset-clean-up'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                });
            });
        }());
        </script>
        <?php }
        MainAdmin::setTopAdminNoticeDisplayed();
    }

    /**
     * @return void
     */
    public function renderAnnouncementsContainer()
    {
        if ( ! empty($this->bundledAnnouncement) ) {
            $this->renderBundledAnnouncement($this->bundledAnnouncement);
            return;
        }

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
        $this->displayOneAnnouncement();
    }

    /**
     * @return void
     */
    public function fillAnnouncementContainerAjax()
    {
        check_ajax_referer(Plugin::getConfig('id') . '_announcements_nonce', 'nonce');

        $location = isset($_POST['location']) && is_string($_POST['location']) ? $_POST['location'] : '';
        if ( ! self::isShowAnnouncementsEnabled($location) ) {
            wp_send_json_success(array('html' => ''));
        }

        $announcements = $this->getAnnouncementsFromTheFeed();

        if (empty($announcements)) {
            wp_send_json_error(['message' => 'No announcements available.']);
        }

        $fallbackBaseUrl = isset($_POST['fallback_url'])
            ? esc_url_raw(wp_unslash($_POST['fallback_url']))
            : '';

        if ($fallbackBaseUrl === '') {
            $fallbackBaseUrl = wp_get_referer();
        }

        $adminBaseUrl    = self_admin_url();
        $fallbackBaseUrl = wp_validate_redirect($fallbackBaseUrl, $adminBaseUrl);

        // Keep the fallback on the same WordPress admin area even if another host was
        // whitelisted through allowed_redirect_hosts.
        $adminUrlParts    = wp_parse_url($adminBaseUrl);
        $fallbackUrlParts = wp_parse_url($fallbackBaseUrl);
        $adminHost        = isset($adminUrlParts['host']) ? strtolower($adminUrlParts['host']) : '';
        $fallbackHost     = isset($fallbackUrlParts['host']) ? strtolower($fallbackUrlParts['host']) : '';
        $adminPath        = isset($adminUrlParts['path']) ? trailingslashit($adminUrlParts['path']) : '';
        $fallbackPath     = isset($fallbackUrlParts['path']) ? $fallbackUrlParts['path'] : '';

        if ( $adminHost === ''
            || $fallbackHost !== $adminHost
            || ($adminPath !== '' && strpos($fallbackPath, $adminPath) !== 0)
        ) {
            $fallbackBaseUrl = $adminBaseUrl;
        }

        $fallbackBaseUrl = remove_query_arg(
            array(
                $this->getQueryStringAction(),
                $this->getAnnIdQuery(),
                '_wpnonce',
            ),
            $fallbackBaseUrl
        );

        ob_start();
        $this->displayOneAnnouncement('ajax', $fallbackBaseUrl);
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

        $settingsAdminClass->updateOption('announcements', MiscArray::filterList($currentAnnouncements));
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

        if ($settingName === 'enabled') {
            $currentAnnouncements['global']['never_show_any'] = (int)$settingValue === 1 ? 0 : 1;
        }

        if ( empty($currentAnnouncements['global']['never_show_any']) ) {
            unset($currentAnnouncements['global']['off_source']);
        }

        $settingsAdminClass->updateOption('announcements', MiscArray::filterList($currentAnnouncements));
    }

    /**
     * @param $actionType
     * @param string $announcementId Current announcement, when provided by the notice.
     * @param string $updateMode ("regular" - page reloads | "ajax")
     *
     * @return void
     */
    public function updateAnnouncementsViaActionType($actionType, $announcementId = '', $updateMode = 'regular')
    {
        if (in_array($actionType, array('seen', 'snoozed'), true) && (string)$announcementId === '') {
            if ($updateMode === 'ajax') {
                wp_send_json_error(['message' => 'Invalid announcement ID.']);
            }

            return;
        }

        // Individual announcement action
        if ($actionType === 'snoozed') {
            $snoozeUntil = current_time('timestamp', true) + $this->snoozeTimeCurrent;
            $this->updateAnnouncementState($announcementId, 'snoozed', $snoozeUntil);

            $option   = get_option(WPACU_PLUGIN_ID . '_settings');
            $settings = is_string($option) ? json_decode($option, true) : $option;
            if (empty($settings['announcements']['list'][$announcementId]['snoozed'])
                || (int)$settings['announcements']['list'][$announcementId]['snoozed'] < $snoozeUntil) {
                if ($updateMode === 'ajax') {
                    wp_send_json_error(array('message' => 'Could not save the announcement snooze.'));
                }

                setcookie(self::getSnoozeCookieName($announcementId), '1', $snoozeUntil, parse_url(admin_url(), PHP_URL_PATH), '', is_ssl());
                return;
            }

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
     * Handle individual snooze and dismiss links without AJAX.
     *
     * In case the AJAX call is not made (e.g. due to JavaScript errors or AJAX calls are disabled), then a fallback is in place
     * e.g. a "href" in the link that is clicked to reload the page and perform the action
     *
     * @return void
     */
    public function handleFallbackActions()
    {
        if ( ! Menu::userCanAccessPlugin() ) {
            return;
        }

        $queryStringAction = $this->getQueryStringAction();
        $annIdQuery        = $this->getAnnIdQuery();

        $actionType = isset($_GET[$queryStringAction])
            ? sanitize_key(wp_unslash($_GET[$queryStringAction]))
            : '';

        $announcementId = isset($_GET[$annIdQuery])
            ? sanitize_text_field(wp_unslash($_GET[$annIdQuery]))
            : '';

        if ( ! in_array($actionType, array('seen', 'snoozed'), true) ) {
            return;
        }

        $fallbackNonce = isset($_GET['_wpnonce'])
            ? sanitize_text_field(wp_unslash($_GET['_wpnonce']))
            : '';

        if ( ! wp_verify_nonce($fallbackNonce, $this->getFallbackNonceAction()) ) {
            return;
        }

        self::updateAnnouncementsViaActionType($actionType, $announcementId);

        // Redirect to the previous URL and remove all fallback-action arguments.
        wp_safe_redirect(
            remove_query_arg(
                array(
                    $queryStringAction,
                    $annIdQuery,
                    '_wpnonce'
                )
            )
        );

        exit();
    }

    /**
     * Handle snooze and dismiss actions for individual announcements.
     *
     * @return void
     */
    public function handleAjaxActionRequest()
    {
        check_ajax_referer(Plugin::getConfig('id') . '_announcements_nonce', 'nonce');

        $annIdQuery = $this->getAnnIdQuery();

        $actionType     = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        $announcementId = isset($_POST[$annIdQuery])   ? sanitize_text_field(wp_unslash($_POST[$annIdQuery])) : '';

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
        if ( ! empty($this->bundledAnnouncement) || ! $this->_showOnCurrentAdminPage() ) {
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
                                nonce: nonce,
                                location: <?php echo wp_json_encode(self::getScreenLocation(get_current_screen())); ?>,
                                fallback_url: window.location.href
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

                        // Send the snooze or dismiss request.
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
                            var announcementId = $announcement.attr('data-pluginann-announcement-id');

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
                        // A feed can include the original unprefixed action class on a message link.
                        // Keep that literal intact when the script template expands the edition prefix.
                        var feedSeenSelector = '.plugin' + 'ann-mark-it-as-seen';
                        $(document).on('click', '.pluginann-mark-it-as-seen, #pluginann-announcements-container ' + feedSeenSelector, function(e) {
                            // Case 1: If the actual "Mark as Seen" is clicked that also has the class "pluginann-main-action-link",
                            // prevent its default behaviour (empty link anyway, it acts as a button)

                            // Case 2: If one of the links from the message is clicked with the same "pluginann-mark-it-as-seen" class,
                            // then keep its default behaviour (e.g. opening the link in a new tab), and also trigger the action to mark it as seen
                            if ($(this).hasClass('pluginann-main-action-link')) {
                                e.preventDefault();
                            }

                            var $announcement  = $(this).closest('[data-pluginann-announcement-id]');
                            var announcementId = $announcement.attr('data-pluginann-announcement-id');

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
