<?php
if (! isset($data)) {
    exit;
}

$adminAnnouncementsClass = new \WpAssetCleanUp\Admin\PluginAnnouncements();
$announcementsFromFeed   = $adminAnnouncementsClass->getAnnouncementsFromTheFeed();

$announcementsSavedSettings = $data['announcements'];

$showAnnouncements = ! (isset($announcementsSavedSettings['global']['never_show_any']) && $announcementsSavedSettings['global']['never_show_any']);

$isLoadedViaAjax = isset($data['is_loaded_via_ajax']) && $data['is_loaded_via_ajax'];

$currentTimeUnix = current_time('timestamp', true);

if ( ! $isLoadedViaAjax ) { ?>
    <div id="wpacu-settings-annoucements-container">
<?php } ?>

<style>
    #wpacu-settings-annoucements-container {
        position: relative;
    }

    #wpacu-settings-announcements {
        width: 100%;
        border-collapse: collapse;
        /* This can help columns adhere to assigned widths. */
        table-layout: fixed;
    }

    /* On desktop/larger screens, force the first column to not wrap */
    #wpacu-settings-announcements tr th {
        white-space: nowrap;
        width: 220px; /* or whatever fixed width you prefer */
    }

    /* On mobile (below 768px for example), allow wrapping and remove the fixed width */
    @media (max-width: 768px) {
        #wpacu-settings-announcements tr th {
            white-space: normal;
            width: auto;
        }
    }

    #wpacu-settings-announcements-wrap {
        position: relative;
        width: 100%;
        min-height: 250px;
        overflow: hidden;
    }

    #wpacu-settings-announcements-wrap .wpacu-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10;
        display: none; /* Hidden by default */
    }

    #wpacu-settings-announcements-wrap .wpacu-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid transparent;
        border-top: 4px solid #004567;
        border-radius: 50%;
        animation: wpacu-spin 1s linear infinite;
    }

    @keyframes wpacu-spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }
</style>

<div id="wpacu-settings-announcements-wrap">
    <div class="wpacu-overlay">
        <div class="wpacu-spinner"></div>
    </div>

    <table id="wpacu-settings-announcements" class="wpacu-form-table">
        <tr valign="top">
            <th scope="row">
                <label for="wpacu_announcements_show_checkbox"><?php _e('Show announcements', 'wp-asset-clean-up'); ?></label>
            </th>
            <td>
                <label class="wpacu_switch">
                    <!-- Default -->
                    <input type="hidden" name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[announcements][global][never_show_any]" value="1">

                    <input id="wpacu_announcements_show_checkbox"
                           data-target-opacity="wpacu-settings-announcements-list"
                           type="checkbox"
                        <?php echo ( $showAnnouncements ) ? 'checked="checked"' : ''; ?>
                           name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[announcements][global][never_show_any]"
                           value="0" /> <span class="wpacu_slider wpacu_round"></span> </label>
                &nbsp;
                Allow <?php echo WPACU_PLUGIN_TITLE; ?> to show announcements regarding plugin critical updates, maintenance notifications, useful tips and guides, and special offers (e.g. Black Friday). <span style="color: #004567; vertical-align: middle;" class="dashicons dashicons-info"></span> <a target="_blank" href="https://www.assetcleanup.com/docs/?p=1946">Read more</a>
            </td>
        </tr>
    </table>

    <?php
    $announcementsSettingsListStyle = ($showAnnouncements == 1) ? 'opacity: 1;' : 'opacity: 0.4;';
    ?>

    <table id="wpacu-settings-announcements-list"
           style="<?php echo $announcementsSettingsListStyle; ?>"
           class="wp-list-table widefat fixed striped table-view-list"
           cellspacing="0">
        <thead>
            <tr>
                <th style="font-weight: 500;">Title</th>
                <th style="font-weight: 500;">Message</th>
                <th style="font-weight: 500;">Showing interval (from, to)</th>
                <th style="font-weight: 500;">Mark as "Seen"?</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($announcementsFromFeed)) : ?>
            <tr>
                <td colspan="4">Currently, there are no plugin announcements to display. Stay tuned for future updates and information. If the option above is enabled, any new announcement will show up at the top of the page.</td>
            </tr>
        <?php else : ?>
            <?php foreach ($announcementsFromFeed as $announcement) : ?>
                <?php
                $annId      = isset($announcement['id']) ? $announcement['id']       : '';

                $titleRaw   = isset($announcement['title']) ? $announcement['title'] : '';
                $title      = wp_kses($titleRaw, $adminAnnouncementsClass->allowedTitleHtmlTags);

                $messageRaw = isset($announcement['message']) ? $announcement['message'] : '';
                $message    = wp_kses($messageRaw, $adminAnnouncementsClass->allowedMessageHtmlTags);

                $startDateRaw = isset($announcement['start_date']) ? $announcement['start_date'] : '';
                $endDateRaw   = isset($announcement['end_date'])   ? $announcement['end_date']   : '';

                // Show the "Start" and "End" date based on the WordPress site settings for a more professional appeareance
                // Otherwise, "UTC" will have to be appended to the dates (fallback in case classes such as "\DateTime" are not available)
                $startDateAndTimeUnix = strtotime($startDateRaw); // UTC
                $endDateAndTimeUnix   = strtotime($endDateRaw); // UTC

                if ( ! \WpAssetCleanUp\Admin\PluginAnnouncements::isSiteTimezoneUtc() ) {
                    // Site timezone
                    $startDateAndTimeUnixForSite = \WpAssetCleanUp\Admin\PluginAnnouncements::feedUnixToWordPressUnix($startDateAndTimeUnix);
                    $endDateAndTimeUnixForSite   = \WpAssetCleanUp\Admin\PluginAnnouncements::feedUnixToWordPressUnix($endDateAndTimeUnix);
                } else {
                    // UTC
                    $startDateAndTimeUnixForSite = $startDateAndTimeUnix;
                    $endDateAndTimeUnixForSite   = $endDateAndTimeUnix;
                }

                $startDateAndTime = date('M d, Y H:i:s', $startDateAndTimeUnixForSite);
                $endDateAndTime   = date('M d, Y H:i:s', $endDateAndTimeUnixForSite);

                $seen = ! empty($announcementsSavedSettings['list'][$annId]['seen']);

                $snoozeUntilRaw = isset($announcementsSavedSettings['list'][$annId]['snoozed']) ? $announcementsSavedSettings['list'][$annId]['snoozed'] : null;
                ?>
                <tr>
                    <td>
                        <?php echo esc_html($title); ?>
                        <?php if ($snoozeUntilRaw) :
                                $snoozeUntil = date('Y-m-d H:i:s', $snoozeUntilRaw);
                            ?>
                            <span style="color: #666; font-size: 12px;"> * <small>Snoozed until <?php echo $snoozeUntil; ?></small></span>
                            <input type="hidden"
                                   name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[announcements][list][<?php echo esc_attr($annId); ?>][snoozed]"
                                   value="<?php echo $snoozeUntilRaw; ?>">
                        <?php endif; ?>
                    </td>
                    <td><?php echo wp_kses($message, array(
                            'a' => array(
                                'href' => array(),
                                'target' => array()
                            )
                        )); ?></td>
                    <td>
                        <?php echo $startDateAndTime;?> - <?php echo $endDateAndTime; ?>

                        <?php if (\WpAssetCleanUp\Admin\PluginAnnouncements::isSiteTimezoneUtc()) { ?>
                             (UTC)
                        <?php } ?>

                        <?php if ($startDateAndTimeUnix > $currentTimeUnix) { echo '* <span style="color: green;">Coming up</span>'; } ?>

                        <?php if ($endDateAndTimeUnix < $currentTimeUnix) { echo '* <span style="color: darkred;">Expired</span>'; } ?>
                    </td>
                    <td>
                        <input type="checkbox"
                               <?php if ($seen) { ?> checked="checked" <?php } ?>
                               class="wpacu-announcement-seen"
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[announcements][list][<?php echo esc_attr($annId); ?>][seen]"
                               data-id="<?php echo esc_attr($annId); ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ( ! $isLoadedViaAjax ) { ?>
</div>
<?php
}
