<?php
if (! isset($data)) {
    exit;
}

use WpAssetCleanUp\Admin\PluginAnnouncements;

$adminAnnouncementsClass = new PluginAnnouncements();
$announcementsSavedSettings = $data['announcements'];
$showAnnouncements = PluginAnnouncements::isShowAnnouncementsEnabled();
$announcementsFromFeed = $showAnnouncements
    ? $adminAnnouncementsClass->getAnnouncementsFromTheFeed()
    : array();

$isLoadedViaAjax = isset($data['is_loaded_via_ajax']) && $data['is_loaded_via_ajax'];

$currentTimeUnix = current_time('timestamp', true);

ob_start();

if ( ! $isLoadedViaAjax ) { ?>
    <div id="pluginann-settings-annoucements-container">
<?php } ?>

<div id="pluginann-settings-announcements-wrap">
    <div class="pluginann-overlay">
        <div class="pluginann-spinner"></div>
    </div>

    <main class="wpacu-announcements-page">
        <section class="wpacu-announcements-panel" aria-labelledby="pluginannAnnouncementsTitle">
            <header class="wpacu-announcements-header">
                <div>
                    <div class="wpacu-announcements-eyebrow"><?php esc_html_e('Plugin communications', 'wp-asset-clean-up'); ?></div>
                    <h2 id="pluginannAnnouncementsTitle"><?php esc_html_e('Choose which announcements you see', 'wp-asset-clean-up'); ?></h2>
                    <p><?php esc_html_e('Receive important maintenance information, critical update notices, practical optimization guides, and occasional product offers directly in the WordPress Dashboard.', 'wp-asset-clean-up'); ?></p>
                </div>
                <div class="wpacu-announcements-header-badge"><?php esc_html_e('You stay in control', 'wp-asset-clean-up'); ?></div>
            </header>

            <div class="wpacu-announcements-body">
                <section class="wpacu-announcements-intro" aria-labelledby="pluginannAnnouncementsIntroTitle">
                    <div class="wpacu-announcements-intro-icon" aria-hidden="true"><span class="dashicons dashicons-megaphone"></span></div>
                    <div>
                        <h3 id="pluginannAnnouncementsIntroTitle"><?php esc_html_e('Useful notices without permanent clutter', 'wp-asset-clean-up'); ?></h3>
                        <p><?php esc_html_e('Announcements appear as dismissible notices at the top of eligible Dashboard pages. You can snooze an individual message, mark it as seen, or disable all future announcements here.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </section>

                <section class="wpacu-announcements-master" aria-labelledby="pluginannAnnouncementsToggleTitle">
                    <div class="wpacu-announcements-master-control">
                        <input type="hidden" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][global][enabled]" value="0">
                        <label class="wpacu_switch" for="wpacu_announcements_show_checkbox">
                            <input id="wpacu_announcements_show_checkbox"
                                   data-target-opacity="#wpacu-settings-announcements-dependent"
                                   type="checkbox"
                                <?php checked($showAnnouncements); ?>
                                   name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][global][enabled]"
                                   value="1" />
                            <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                        </label>
                        <span class="wpacu-announcements-control-label">
                            <strong><?php echo $showAnnouncements ? esc_html__('Opted in', 'wp-asset-clean-up') : esc_html__('Not opted in', 'wp-asset-clean-up'); ?></strong>
                            <small><?php esc_html_e('Save changes after toggling.', 'wp-asset-clean-up'); ?></small>
                        </span>
                    </div>
                    <div class="wpacu-announcements-master-copy">
                        <span class="wpacu-announcements-master-kicker"><?php esc_html_e('Main setting', 'wp-asset-clean-up'); ?></span>
                        <h3 id="pluginannAnnouncementsToggleTitle"><?php esc_html_e('Show plugin announcements', 'wp-asset-clean-up'); ?></h3>
                        <p><?php esc_html_e('When enabled, Asset CleanUp may contact its CloudFront announcement feed. When disabled, the feed is not requested and no Asset CleanUp announcement notices are shown. Your optimization settings and normal WordPress update checks are not affected.', 'wp-asset-clean-up'); ?></p>
                        <p><small><?php esc_html_e('The request necessarily exposes the server IP address and standard HTTP metadata to the feed provider. Asset CleanUp does not intentionally add the site URL, administrator details, or site content.', 'wp-asset-clean-up'); ?> <a href="https://www.gabelivan.com/privacy-policy/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Privacy policy', 'wp-asset-clean-up'); ?></a></small></p>
                    </div>
                </section>

    <?php
    $announcementsDependentStyle = ($showAnnouncements == 1) ? 'opacity: 1;' : 'opacity: 0.4;';
    ?>

                <div id="wpacu-settings-announcements-dependent" class="wpacu-announcements-dependent" style="<?php echo esc_attr($announcementsDependentStyle); ?>">
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
                        <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                        <h4><?php esc_html_e('You are all caught up', 'wp-asset-clean-up'); ?></h4>
                        <p><?php esc_html_e('There are no announcements to review right now. New eligible messages will appear here and, when enabled, as dismissible Dashboard notices.', 'wp-asset-clean-up'); ?></p>
                    </div>
        <?php else : ?>
            <?php foreach ($announcementsFromFeed as $announcement) : ?>
                <?php
                $annId      = isset($announcement['id']) ? $announcement['id']       : '';

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
                                   name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][list][<?php echo esc_attr($annId); ?>][snoozed]"
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
                                           name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][list][<?php echo esc_attr($annId); ?>][seen]"
                                           value="1">
                                <?php } ?>
                            <?php } else { ?>
                                <label class="wpacu-announcement-seen-control">
                                    <input type="checkbox"
                                           <?php checked($seen); ?>
                                           class="wpacu-announcement-seen"
                                           name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[announcements][list][<?php echo esc_attr($annId); ?>][seen]"
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
echo $adminAnnouncementsClass->filterOutputForUniquePrefix(ob_get_clean());
?>

<?php if ( ! $isLoadedViaAjax ) { ?>
</div>
<?php
}
