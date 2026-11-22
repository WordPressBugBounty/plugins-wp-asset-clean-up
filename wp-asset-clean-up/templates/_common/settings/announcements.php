<?php
if (! isset($data)) {
    exit;
}

use WpAssetCleanUp\Admin\PluginAnnouncements;
use WpAssetCleanUp\Admin\SettingsAdmin;

$adminAnnouncementsClass    = new PluginAnnouncements();
$announcementsSavedSettings = $data['announcements'];
$preference                 = SettingsAdmin::getStoredAnnouncementPreference();
$selectedLocations          = SettingsAdmin::getAnnouncementLocations($announcementsSavedSettings);
$showAnnouncements          = PluginAnnouncements::isShowAnnouncementsEnabled();
$selectedPreference         = $preference === 'undecided' && $showAnnouncements ? 'on' : $preference;
$announcementsFromFeed      = $showAnnouncements
    ? $adminAnnouncementsClass->getAnnouncementsFromTheFeed()
    : array();

$isLoadedViaAjax = isset($data['is_loaded_via_ajax']) && $data['is_loaded_via_ajax'];

$currentTimeUnix = current_time('timestamp', true);

$announcementPrefix = esc_attr($adminAnnouncementsClass->filterOutputForUniquePrefix('pluginann'));

if ( ! $isLoadedViaAjax ) { ?>
    <div id="<?php echo $announcementPrefix; ?>-settings-annoucements-container">
<?php } ?>

<div id="<?php echo $announcementPrefix; ?>-settings-announcements-wrap">
    <div class="<?php echo $announcementPrefix; ?>-overlay">
        <div class="<?php echo $announcementPrefix; ?>-spinner"></div>
    </div>

    <main class="wpacu-announcements-page">
        <section class="wpacu-announcements-panel" aria-labelledby="<?php echo $announcementPrefix; ?>AnnouncementsTitle">
            <header class="wpacu-announcements-header">
                <div>
                    <div class="wpacu-announcements-eyebrow"><?php esc_html_e('Plugin communications', 'wp-asset-clean-up'); ?></div>
                    <h2 id="<?php echo $announcementPrefix; ?>AnnouncementsTitle"><?php esc_html_e('Choose which Dashboard notices you receive', 'wp-asset-clean-up'); ?></h2>
                    <p><?php esc_html_e('Receive important maintenance information, critical update notices, practical optimization guides, and occasional product offers directly in the WordPress Dashboard.', 'wp-asset-clean-up'); ?></p>
                </div>
                <div class="wpacu-announcements-header-badge"><?php esc_html_e('You stay in control', 'wp-asset-clean-up'); ?></div>
            </header>

            <div class="wpacu-announcements-body">
                <section class="wpacu-announcements-intro" aria-labelledby="<?php echo $announcementPrefix; ?>AnnouncementsIntroTitle">
                    <div class="wpacu-announcements-intro-icon" aria-hidden="true"><span class="dashicons dashicons-megaphone"></span></div>
                    <div>
                        <h3 id="<?php echo $announcementPrefix; ?>AnnouncementsIntroTitle"><?php esc_html_e('Useful notices without permanent clutter', 'wp-asset-clean-up'); ?></h3>
                        <p><?php esc_html_e('Announcements appear as dismissible notices at the top of eligible Dashboard pages. You can snooze an individual notice, mark it as seen, or turn off future Dashboard notices here.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </section>

                <section class="wpacu-announcements-master" aria-labelledby="<?php echo $announcementPrefix; ?>AnnouncementsToggleTitle">
                    <h3 id="<?php echo $announcementPrefix; ?>AnnouncementsToggleTitle"><?php esc_html_e('Show plugin announcements', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Asset CleanUp checks its online announcement feed while updates are enabled.', 'wp-asset-clean-up'); ?></p>
                    <fieldset class="wpacu-announcements-choices" aria-labelledby="<?php echo $announcementPrefix; ?>AnnouncementsToggleTitle">
                        <input type="hidden" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][previous_preference]" value="<?php echo esc_attr($preference); ?>">
                        <?php
                        $announcementChoices = array(
                            'on' => array(
                                __('All updates and offers', 'wp-asset-clean-up'),
                                __('Receive important fixes, release news, optimization tips, and occasional Asset CleanUp offers.', 'wp-asset-clean-up'),
                            ),
                            'important' => array(
                                __('Security & release updates only', 'wp-asset-clean-up'),
                                __('Receive security notices, key fixes, and major compatibility updates.', 'wp-asset-clean-up'),
                            ),
                            'off' => array(
                                __('Keep announcements off', 'wp-asset-clean-up'),
                                __('Skip Dashboard announcements. Asset CleanUp won’t request the announcement feed.', 'wp-asset-clean-up'),
                            ),
                        );
                        foreach ($announcementChoices as $value => $choice) {
                            $choiceId = $announcementPrefix . '-announcements-' . $value;
                            ?>
                            <label class="wpacu-announcements-choice">
                                <input type="radio"
                                       name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][preference]"
                                       value="<?php echo esc_attr($value); ?>"
                                       aria-labelledby="<?php echo esc_attr($choiceId . '-label'); ?>"
                                       aria-describedby="<?php echo esc_attr(($value === 'on' ? $choiceId . '-badge ' : '') . $choiceId . '-description'); ?>"
                                       <?php checked($selectedPreference, $value); ?>>
                                <span class="wpacu-announcements-choice-copy">
                                    <span>
                                        <strong id="<?php echo esc_attr($choiceId . '-label'); ?>"><?php echo esc_html($choice[0]); ?></strong>
                                        <?php if ($value === 'on') { ?>
                                            <span class="wpacu-status-badge" id="<?php echo esc_attr($choiceId . '-badge'); ?>"><?php esc_html_e('Recommended', 'wp-asset-clean-up'); ?></span>
                                        <?php } ?>
                                    </span>
                                    <span id="<?php echo esc_attr($choiceId . '-description'); ?>"><?php echo esc_html($choice[1]); ?></span>
                                </span>
                            </label>
                        <?php } ?>
                    </fieldset>
                    <input type="hidden" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][previous_locations]" value="<?php echo esc_attr(implode(',', $selectedLocations)); ?>">
                    <fieldset class="wpacu-announcement-locations" aria-describedby="<?php echo $announcementPrefix; ?>LocationsError">
                        <legend><?php esc_html_e('Where to show announcements', 'wp-asset-clean-up'); ?></legend>
                        <div class="wpacu-announcement-locations-options">
                            <?php
                            $locationLabels = array(
                                'dashboard'    => __('Dashboard Home', 'wp-asset-clean-up'),
                                'plugins'      => __('Installed Plugins', 'wp-asset-clean-up'),
                                'plugin_pages' => __('Asset CleanUp pages', 'wp-asset-clean-up'),
                            );
                            foreach ($locationLabels as $location => $label) { ?>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][locations][]"
                                           value="<?php echo esc_attr($location); ?>" <?php checked(in_array($location, $selectedLocations, true)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php } ?>
                        </div>
                        <p id="<?php echo $announcementPrefix; ?>LocationsError" class="wpacu-announcement-locations-error" role="alert"
                           data-message="<?php esc_attr_e('Choose at least one place to show announcements, or select “Keep announcements off”.', 'wp-asset-clean-up'); ?>"></p>
                    </fieldset>
                    <?php if ($preference === 'undecided' && $showAnnouncements) { ?>
                        <p><?php esc_html_e('All updates and offers are enabled by default. Choose another option here to change which notices you receive.', 'wp-asset-clean-up'); ?></p>
                    <?php } elseif ($preference === 'undecided') { ?>
                        <p><?php esc_html_e('No announcement preference is recorded. The feed is off.', 'wp-asset-clean-up'); ?></p>
                    <?php } elseif ($preference === 'off' && isset($announcementsSavedSettings['global']['off_source'])
                        && $announcementsSavedSettings['global']['off_source'] === 'notice') { ?>
                        <p><?php esc_html_e('Announcements were turned off for this site using “Never show plugin announcements”.', 'wp-asset-clean-up'); ?></p>
                    <?php } ?>
                    <p><?php esc_html_e('Save changes to apply your choice.', 'wp-asset-clean-up'); ?> <a href="https://www.gabelivan.com/privacy-policy/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Privacy details', 'wp-asset-clean-up'); ?></a><?php
                        ?></p>
                </section>

                <div id="wpacu-settings-announcements-dependent" class="wpacu-announcements-dependent">
                <div class="wpacu-announcements-explainer">
                    <article><span class="dashicons dashicons-visibility" aria-hidden="true"></span><div><h4><?php esc_html_e('Shown selectively', 'wp-asset-clean-up'); ?></h4><p><?php esc_html_e('Only announcements valid for your plugin edition, version, and current date are eligible to appear.', 'wp-asset-clean-up'); ?></p></div></article>
                    <article><span class="dashicons dashicons-clock" aria-hidden="true"></span><div><h4><?php esc_html_e('Snooze when busy', 'wp-asset-clean-up'); ?></h4><p><?php esc_html_e('Temporarily hide a notice and let it return later while it is still relevant.', 'wp-asset-clean-up'); ?></p></div></article>
                    <article><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><div><h4><?php esc_html_e('Mark as seen', 'wp-asset-clean-up'); ?></h4><p><?php esc_html_e('Permanently stop an individual announcement from appearing as a Dashboard notice.', 'wp-asset-clean-up'); ?></p></div></article>
                </div>

                <div class="wpacu-announcements-list-heading">
                    <div><h3><?php esc_html_e('Announcement history and controls', 'wp-asset-clean-up'); ?></h3><p><?php esc_html_e('Review available messages and control whether each one may appear again.', 'wp-asset-clean-up'); ?></p></div>
                    <a target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=1946"><span class="dashicons dashicons-external" aria-hidden="true"></span><?php esc_html_e('Documentation', 'wp-asset-clean-up'); ?></a>
                </div>

                <div id="wpacu-settings-announcements-list" class="wpacu-announcements-list">
        <?php if (empty($announcementsFromFeed)) : ?>
                    <div class="wpacu-announcements-empty">
                    <?php if ( ! $showAnnouncements ) { ?>
                        <span class="dashicons dashicons-hidden" aria-hidden="true"></span>
                        <h4><?php esc_html_e('Announcement feed is off', 'wp-asset-clean-up'); ?></h4>
                        <p><?php esc_html_e('To receive messages from the announcement feed, choose “All updates and offers” or “Security & release updates only” above and save your changes.', 'wp-asset-clean-up'); ?></p>
                    <?php } else { ?>
                        <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                        <h4><?php esc_html_e('You are all caught up', 'wp-asset-clean-up'); ?></h4>
                        <p><?php esc_html_e('There are no announcements to review right now. New eligible messages will appear here and, when enabled, as dismissible Dashboard notices.', 'wp-asset-clean-up'); ?></p>
                    <?php } ?>
                    </div>
        <?php else : ?>
            <?php foreach ($announcementsFromFeed as $announcement) : ?>
                <?php
                $annId     = isset($announcement['id']) ? $announcement['id'] : '';
                $formId    = rawurlencode($annId);

                $titleRaw   = isset($announcement['title']) ? $announcement['title'] : '';
                $title      = wp_kses($titleRaw, $adminAnnouncementsClass->allowedTitleHtmlTags);

                $messageRaw = isset($announcement['message']) ? $announcement['message'] : '';
                $message    = wp_kses($messageRaw, $adminAnnouncementsClass->allowedMessageHtmlTags);

                // Show the "Start" and "End" date based on the WordPress site settings for a more professional appeareance
                // Otherwise, "UTC" will have to be appended to the dates (fallback in case classes such as "\DateTime" are not available)
                $startDateAndTimeUnix = (int)$announcement['start_time_unix']; // UTC
                $endDateAndTimeUnix   = (int)$announcement['end_time_unix']; // UTC

                $startDateAndTime = PluginAnnouncements::formatFeedUnixForSite('M d, Y H:i:s', $startDateAndTimeUnix);
                $endDateAndTime   = PluginAnnouncements::formatFeedUnixForSite('M d, Y H:i:s', $endDateAndTimeUnix);

                $seen = ! empty($announcementsSavedSettings['list'][$annId]['seen']);

                $snoozeUntilRaw = isset($announcementsSavedSettings['list'][$annId]['snoozed']) ? $announcementsSavedSettings['list'][$annId]['snoozed'] : null;
                $priority = isset($announcement['priority']) ? strtolower((string)$announcement['priority']) : 'low';
                $priority = in_array($priority, array('high', 'medium', 'low'), true) ? $priority : 'low';
                $timingStatus = 'active';
                $timingLabel  = __('Active', 'wp-asset-clean-up');

                if ($startDateAndTimeUnix > $currentTimeUnix) {
                    $timingStatus = 'upcoming';
                    $timingLabel  = __('Upcoming', 'wp-asset-clean-up');
                } elseif ($endDateAndTimeUnix < $currentTimeUnix) {
                    $timingStatus = 'expired';
                    $timingLabel  = __('Expired', 'wp-asset-clean-up');
                }
                ?>
                    <article class="wpacu-announcement-card wpacu-announcement-card--<?php echo esc_attr($timingStatus); ?>">
                        <header class="wpacu-announcement-card-header">
                            <div class="wpacu-announcement-title-wrap">
                                <div class="wpacu-announcement-badges">
                                    <span class="wpacu-announcement-status wpacu-announcement-status--<?php echo esc_attr($timingStatus); ?>"><?php echo esc_html($timingLabel); ?></span>
                                    <span class="wpacu-announcement-priority wpacu-announcement-priority--<?php echo esc_attr($priority); ?>"><?php echo esc_html(sprintf(__('%s priority', 'wp-asset-clean-up'), ucfirst($priority))); ?></span>
                                </div>
                                <h4><?php echo wp_kses($title, $adminAnnouncementsClass->allowedTitleHtmlTags); ?></h4>
                        <?php if ($snoozeUntilRaw) :
                                $snoozeUntil = PluginAnnouncements::formatFeedUnixForSite('M d, Y H:i:s', $snoozeUntilRaw);
                            ?>
                                <p class="wpacu-announcement-snoozed"><span class="dashicons dashicons-clock" aria-hidden="true"></span><?php printf(esc_html__('Snoozed until %s', 'wp-asset-clean-up'), esc_html($snoozeUntil)); ?></p>
                            <input type="hidden"
                                   name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][list][<?php echo esc_attr($formId); ?>][snoozed]"
                                   value="<?php echo esc_attr($snoozeUntilRaw); ?>">
                        <?php endif; ?>
                            </div>
                            <?php if ($timingStatus === 'expired') { ?>
                                <div class="wpacu-announcement-ended-control">
                                    <span class="dashicons dashicons-archive" aria-hidden="true"></span>
                                    <span><strong><?php esc_html_e('No longer shown', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('This announcement has expired', 'wp-asset-clean-up'); ?></small></span>
                                </div>
                                <?php if ($seen) { ?>
                                    <input type="hidden"
                                           name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][list][<?php echo esc_attr($formId); ?>][seen]"
                                           value="1">
                                <?php } ?>
                            <?php } else { ?>
                                <label class="wpacu-announcement-seen-control">
                                    <input type="hidden"
                                           name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][list][<?php echo esc_attr($formId); ?>][was_seen]"
                                           value="<?php echo $seen ? '1' : '0'; ?>">
                                    <input type="checkbox"
                                           <?php checked($seen); ?>
                                           class="wpacu-announcement-seen"
                                           value="1"
                                           name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][list][<?php echo esc_attr($formId); ?>][seen]"
                                           data-id="<?php echo esc_attr($annId); ?>">
                                    <span><strong><?php esc_html_e('Mark as seen', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Do not show again', 'wp-asset-clean-up'); ?></small></span>
                                </label>
                            <?php } ?>
                        </header>
                        <div class="wpacu-announcement-message"><?php echo wp_kses($message, array(
                            'a' => array(
                                'href' => array(),
                                'target' => array(),
                                'rel' => array()
                            )
                        )); ?></div>
                        <footer class="wpacu-announcement-meta">
                            <span><strong><?php esc_html_e('Available from', 'wp-asset-clean-up'); ?></strong><?php echo esc_html($startDateAndTime); ?></span>
                            <span><strong><?php esc_html_e('Until', 'wp-asset-clean-up'); ?></strong><?php echo esc_html($endDateAndTime); ?><?php echo PluginAnnouncements::isSiteTimezoneUtc() ? ' ' . esc_html__('(UTC)', 'wp-asset-clean-up') : ''; ?></span>
                        </footer>
                    </article>
            <?php endforeach; ?>
        <?php endif; ?>
                </div>
                </div>
            </div>
        </section>
    </main>
</div>

<?php
if ( ! $isLoadedViaAjax ) { ?>
</div>
<script>
(function () {
    var container = document.getElementById('<?php echo $announcementPrefix; ?>-settings-annoucements-container');
    var form = container.closest('form');
    if (!form) {
        return;
    }

    function validateLocations() {
        var mode = container.querySelector('.wpacu-announcements-choices input:checked');
        var locations = container.querySelector('.wpacu-announcement-locations');
        if (!locations) {
            return true;
        }
        var enabled = mode && (mode.value === 'on' || mode.value === 'important');
        var invalid = enabled && !locations.querySelector('input:checked');
        var error = locations.querySelector('[role="alert"]');
        var message = invalid ? error.getAttribute('data-message') : '';

        locations.hidden = !enabled;
        if (error.textContent !== message) {
            error.textContent = message;
        }
        return !invalid;
    }

    validateLocations();

    // The notice actions can replace the settings panel via AJAX.
    container.addEventListener('change', function (event) {
        if (event.target.closest('.wpacu-announcements-master')) {
            validateLocations();
        }
    });

    form.addEventListener('submit', function (event) {
        if (validateLocations()) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();

        var checkbox = container.querySelector('.wpacu-announcement-locations input');
        if (!checkbox.getClientRects().length) {
            form.querySelector('[data-wpacu-settings-tab-key="wpacu-setting-plugin-usage-settings"]').click();
            document.getElementById('wpacu-plugin-usage-settings-announcements-tab-item').click();
        }
        checkbox.focus();
    }, true);
})();
</script>
<?php
}
