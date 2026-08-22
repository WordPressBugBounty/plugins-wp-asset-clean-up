<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}

$tabIdArea = 'wpacu-setting-disable-rss-feed';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea)
    ? 'style="display: table-cell;"'
    : '';

$disableRssFeed = ! empty($data['disable_rss_feed']);

$removeMainFeedLink = $disableRssFeed || ! empty($data['remove_main_feed_link']);
$removeCommentFeedLink = $disableRssFeed || ! empty($data['remove_comment_feed_link']);

$disableRssFeedMessage = isset($data['disable_rss_feed_message'])
    ? (string) $data['disable_rss_feed_message']
    : '';

if (trim($disableRssFeedMessage) === '') {
    $disableRssFeedMessage = __('There is no RSS feed available.', 'wp-asset-clean-up');
}

$settingsInputName = WPACU_PLUGIN_ID . '_settings';
$mainFeedUrl        = get_feed_link();
$commentsFeedUrl    = get_bloginfo('comments_rss2_url');
?>
<div id="<?php echo esc_attr($tabIdArea); ?>"
     class="wpacu-settings-tab-content"
     <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <main id="wpacu-rss-settings"
          class="wpacu-rss-page<?php echo $disableRssFeed ? ' is-feeds-disabled' : ''; ?>"
          data-feed-state="<?php echo $disableRssFeed ? 'disabled' : 'available'; ?>">
        <section class="wpacu-rss-panel" aria-labelledby="wpacuRssTitle">
            <header class="wpacu-rss-header">
                <div>
                    <div class="wpacu-rss-eyebrow">
                        <?php esc_html_e('WordPress publishing', 'wp-asset-clean-up'); ?>
                    </div>
                    <h2 id="wpacuRssTitle">
                        <?php esc_html_e('Control WordPress feeds and discovery links', 'wp-asset-clean-up'); ?>
                    </h2>
                    <p>
                        <?php esc_html_e('WordPress publishes feeds for posts, comments, and other content. Keep them available unless this site and its connected services do not use them.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>
                <div class="wpacu-rss-header-badge">
                    <?php esc_html_e('Advanced setting', 'wp-asset-clean-up'); ?>
                </div>
            </header>

            <div class="wpacu-rss-body">
                <section class="wpacu-rss-intro" aria-labelledby="wpacuRssIntroTitle">
                    <div class="wpacu-rss-intro-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 11a9 9 0 0 1 9 9"></path>
                            <path d="M4 4a16 16 0 0 1 16 16"></path>
                            <circle cx="5" cy="19" r="1"></circle>
                        </svg>
                    </div>
                    <div>
                        <h3 id="wpacuRssIntroTitle">
                            <?php esc_html_e('Feed availability and discovery are separate', 'wp-asset-clean-up'); ?>
                        </h3>
                        <p>
                            <?php esc_html_e('Disabling feeds blocks requests to feed URLs. Removing discovery links only stops advertising those URLs in the page source, while the feeds themselves remain available.', 'wp-asset-clean-up'); ?>
                        </p>
                    </div>
                </section>

                <fieldset class="wpacu-rss-fieldset wpacu-rss-fieldset--availability">
                    <legend><?php esc_html_e('Feed availability', 'wp-asset-clean-up'); ?></legend>

                    <label class="wpacu-rss-master-choice" for="wpacu_disable_rss_feed">
                        <input id="wpacu_disable_rss_feed"
                               type="checkbox"
                               name="<?php echo esc_attr($settingsInputName); ?>[disable_rss_feed]"
                               value="1"
                               aria-describedby="wpacuRssDisableDescription wpacuRssMasterState"
                            <?php checked($disableRssFeed); ?>>

                        <span class="wpacu-rss-master-card">
                            <span class="wpacu-rss-master-top">
                                <span class="wpacu-rss-master-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>
                                        <path d="M9 12h6"></path>
                                    </svg>
                                </span>

                                <span class="wpacu-rss-master-heading">
                                    <span class="wpacu-rss-master-title">
                                        <?php esc_html_e('Disable WordPress feeds', 'wp-asset-clean-up'); ?>
                                        <span class="wpacu-rss-badge wpacu-rss-badge--warning">
                                            <?php esc_html_e('Most restrictive', 'wp-asset-clean-up'); ?>
                                        </span>
                                    </span>
                                    <span class="wpacu-rss-master-subtitle">
                                        <?php esc_html_e('Block feed requests across the site', 'wp-asset-clean-up'); ?>
                                    </span>
                                </span>

                                <span class="wpacu-rss-switch" aria-hidden="true">
                                    <span class="wpacu-rss-switch-track">
                                        <span class="wpacu-rss-switch-text wpacu-rss-switch-text--available">
                                            <?php esc_html_e('Available', 'wp-asset-clean-up'); ?>
                                        </span>
                                        <span class="wpacu-rss-switch-text wpacu-rss-switch-text--disabled">
                                            <?php esc_html_e('Disabled', 'wp-asset-clean-up'); ?>
                                        </span>
                                        <span class="wpacu-rss-switch-thumb"></span>
                                    </span>
                                </span>
                            </span>

                            <span class="wpacu-rss-master-description" id="wpacuRssDisableDescription">
                                <?php esc_html_e('Visitors and external services requesting a feed URL will receive the custom message below instead of feed content. Feed readers and integrations that rely on these URLs may stop working.', 'wp-asset-clean-up'); ?>
                            </span>

                            <span class="wpacu-rss-effects">
                                <span class="wpacu-rss-effect-row">
                                    <span class="wpacu-rss-effect-label"><?php esc_html_e('Feed URLs', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-rss-effect-value">
                                        <span class="is-when-available"><?php esc_html_e('Available', 'wp-asset-clean-up'); ?></span>
                                        <span class="is-when-disabled is-restrictive"><?php esc_html_e('Blocked', 'wp-asset-clean-up'); ?></span>
                                    </span>
                                </span>
                                <span class="wpacu-rss-effect-row">
                                    <span class="wpacu-rss-effect-label"><?php esc_html_e('Custom message', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-rss-effect-value">
                                        <span class="is-when-available"><?php esc_html_e('Not used', 'wp-asset-clean-up'); ?></span>
                                        <span class="is-when-disabled"><?php esc_html_e('Returned', 'wp-asset-clean-up'); ?></span>
                                    </span>
                                </span>
                                <span class="wpacu-rss-effect-row">
                                    <span class="wpacu-rss-effect-label"><?php esc_html_e('Discovery links', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-rss-effect-value">
                                        <span class="is-when-available"><?php esc_html_e('Independent', 'wp-asset-clean-up'); ?></span>
                                        <span class="is-when-disabled"><?php esc_html_e('Removed automatically', 'wp-asset-clean-up'); ?></span>
                                    </span>
                                </span>
                            </span>

                            <span id="wpacuRssMasterState" class="wpacu-rss-screen-reader-state" aria-live="polite">
                                <span class="is-when-available"><?php esc_html_e('WordPress feeds are available.', 'wp-asset-clean-up'); ?></span>
                                <span class="is-when-disabled"><?php esc_html_e('WordPress feeds are disabled.', 'wp-asset-clean-up'); ?></span>
                            </span>
                        </span>
                    </label>

                    <section id="wpacu_disable_rss_feed_message_area"
                             class="wpacu-rss-message-card"
                             aria-labelledby="wpacuRssMessageTitle">
                        <div class="wpacu-rss-message-copy">
                            <div class="wpacu-rss-message-heading">
                                <h3 id="wpacuRssMessageTitle">
                                    <?php esc_html_e('Message shown for blocked feed requests', 'wp-asset-clean-up'); ?>
                                </h3>
                                <span class="wpacu-rss-message-state">
                                    <span class="wpacu-rss-message-state--inactive">
                                        <?php esc_html_e('Currently inactive', 'wp-asset-clean-up'); ?>
                                    </span>
                                    <span class="wpacu-rss-message-state--active">
                                        <?php esc_html_e('Currently in use', 'wp-asset-clean-up'); ?>
                                    </span>
                                </span>
                            </div>
                            <p>
                                <?php esc_html_e('This text is returned when someone opens a WordPress feed URL. It can be prepared and saved even while feeds remain available.', 'wp-asset-clean-up'); ?>
                            </p>
                            <code><?php echo esc_html($mainFeedUrl); ?></code>
                        </div>

                        <div class="wpacu-rss-message-field">
                            <label for="wpacu_disable_rss_feed_message">
                                <?php esc_html_e('Feed response message', 'wp-asset-clean-up'); ?>
                            </label>
                            <textarea id="wpacu_disable_rss_feed_message"
                                      name="<?php echo esc_attr($settingsInputName); ?>[disable_rss_feed_message]"
                                      rows="3"><?php echo esc_textarea($disableRssFeedMessage); ?></textarea>
                            <p>
                                <?php esc_html_e('Use a short, clear explanation. Plain text is recommended.', 'wp-asset-clean-up'); ?>
                            </p>
                        </div>
                    </section>
                </fieldset>

                <fieldset class="wpacu-rss-fieldset wpacu-rss-fieldset--discovery">
                    <legend><?php esc_html_e('Feed discovery links in the page source', 'wp-asset-clean-up'); ?></legend>

                    <div class="wpacu-rss-section-intro">
                        <p>
                            <?php esc_html_e('These options remove selected feed discovery tags from the document head. They do not block the corresponding feed URLs on their own.', 'wp-asset-clean-up'); ?>
                        </p>
                    </div>

                    <div id="wpacuRssManagedNotice"
                         class="wpacu-rss-managed-notice"
                         role="status"
                        <?php echo $disableRssFeed ? '' : ' hidden'; ?>>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 7h-9"></path>
                            <path d="M14 17H5"></path>
                            <circle cx="17" cy="17" r="3"></circle>
                            <circle cx="7" cy="7" r="3"></circle>
                        </svg>
                        <p>
                            <strong><?php esc_html_e('Managed by “Disable WordPress feeds”.', 'wp-asset-clean-up'); ?></strong>
                            <?php esc_html_e('Both discovery links stay selected while feeds are disabled. Turning feeds back on clears these two options so they can be chosen independently again.', 'wp-asset-clean-up'); ?>
                        </p>
                    </div>

                    <div class="wpacu-rss-discovery-grid">
                        <label class="wpacu-rss-discovery-choice" for="wpacu_remove_main_feed_link">
                            <input id="wpacu_remove_main_feed_link"
                                   type="checkbox"
                                   name="<?php echo esc_attr($settingsInputName); ?>[remove_main_feed_link]"
                                   value="1"
                                   aria-describedby="wpacuRssMainFeedDescription"
                                <?php checked($removeMainFeedLink); ?>>

                            <span class="wpacu-rss-discovery-card">
                                <span class="wpacu-rss-discovery-top">
                                    <span class="wpacu-rss-discovery-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4 11a9 9 0 0 1 9 9"></path>
                                            <path d="M4 4a16 16 0 0 1 16 16"></path>
                                            <circle cx="5" cy="19" r="1"></circle>
                                        </svg>
                                    </span>
                                    <span class="wpacu-rss-discovery-heading">
                                        <span class="wpacu-rss-discovery-title">
                                            <?php esc_html_e('Remove the main feed discovery link', 'wp-asset-clean-up'); ?>
                                        </span>
                                        <span class="wpacu-rss-discovery-subtitle">
                                            <?php esc_html_e('Posts and the primary site feed', 'wp-asset-clean-up'); ?>
                                        </span>
                                    </span>
                                    <span class="wpacu-rss-discovery-badge" aria-hidden="true">
                                        <span class="is-optional"><?php esc_html_e('Optional', 'wp-asset-clean-up'); ?></span>
                                        <span class="is-managed"><?php esc_html_e('Managed', 'wp-asset-clean-up'); ?></span>
                                    </span>
                                </span>

                                <span class="wpacu-rss-discovery-description" id="wpacuRssMainFeedDescription">
                                    <?php esc_html_e('Removes the main feed tag from the document head. The feed endpoint remains available unless feeds are disabled above.', 'wp-asset-clean-up'); ?>
                                </span>

                                <code>&lt;link rel=&quot;alternate&quot; type=&quot;application/rss+xml&quot; title=&quot;<?php esc_html_e('Main feed', 'wp-asset-clean-up'); ?>&quot; href=&quot;<?php echo esc_url($mainFeedUrl); ?>&quot; /&gt;</code>

                                <span class="wpacu-rss-effects wpacu-rss-effects--compact">
                                    <span class="wpacu-rss-effect-row">
                                        <span class="wpacu-rss-effect-label"><?php esc_html_e('Feed endpoint', 'wp-asset-clean-up'); ?></span>
                                        <span class="wpacu-rss-effect-value"><?php esc_html_e('Not changed by this option', 'wp-asset-clean-up'); ?></span>
                                    </span>
                                    <span class="wpacu-rss-effect-row">
                                        <span class="wpacu-rss-effect-label"><?php esc_html_e('Discovery tag', 'wp-asset-clean-up'); ?></span>
                                        <span class="wpacu-rss-effect-value">
                                            <span class="is-when-kept"><?php esc_html_e('Kept', 'wp-asset-clean-up'); ?></span>
                                            <span class="is-when-removed is-restrictive"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                                        </span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="wpacu-rss-discovery-choice" for="wpacu_remove_comment_feed_link">
                            <input id="wpacu_remove_comment_feed_link"
                                   type="checkbox"
                                   name="<?php echo esc_attr($settingsInputName); ?>[remove_comment_feed_link]"
                                   value="1"
                                   aria-describedby="wpacuRssCommentFeedDescription"
                                <?php checked($removeCommentFeedLink); ?>>

                            <span class="wpacu-rss-discovery-card">
                                <span class="wpacu-rss-discovery-top">
                                    <span class="wpacu-rss-discovery-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                                            <path d="M8 9h8"></path>
                                            <path d="M8 13h5"></path>
                                        </svg>
                                    </span>
                                    <span class="wpacu-rss-discovery-heading">
                                        <span class="wpacu-rss-discovery-title">
                                            <?php esc_html_e('Remove the comments feed discovery link', 'wp-asset-clean-up'); ?>
                                        </span>
                                        <span class="wpacu-rss-discovery-subtitle">
                                            <?php esc_html_e('The site-wide comments feed', 'wp-asset-clean-up'); ?>
                                        </span>
                                    </span>
                                    <span class="wpacu-rss-discovery-badge" aria-hidden="true">
                                        <span class="is-optional"><?php esc_html_e('Optional', 'wp-asset-clean-up'); ?></span>
                                        <span class="is-managed"><?php esc_html_e('Managed', 'wp-asset-clean-up'); ?></span>
                                    </span>
                                </span>

                                <span class="wpacu-rss-discovery-description" id="wpacuRssCommentFeedDescription">
                                    <?php esc_html_e('Removes the comments feed tag from the document head. The feed endpoint remains available unless feeds are disabled above.', 'wp-asset-clean-up'); ?>
                                </span>

                                <code>&lt;link rel=&quot;alternate&quot; type=&quot;application/rss+xml&quot; title=&quot;<?php esc_html_e('Comments feed', 'wp-asset-clean-up'); ?>&quot; href=&quot;<?php echo esc_url($commentsFeedUrl); ?>&quot; /&gt;</code>

                                <span class="wpacu-rss-effects wpacu-rss-effects--compact">
                                    <span class="wpacu-rss-effect-row">
                                        <span class="wpacu-rss-effect-label"><?php esc_html_e('Feed endpoint', 'wp-asset-clean-up'); ?></span>
                                        <span class="wpacu-rss-effect-value"><?php esc_html_e('Not changed by this option', 'wp-asset-clean-up'); ?></span>
                                    </span>
                                    <span class="wpacu-rss-effect-row">
                                        <span class="wpacu-rss-effect-label"><?php esc_html_e('Discovery tag', 'wp-asset-clean-up'); ?></span>
                                        <span class="wpacu-rss-effect-value">
                                            <span class="is-when-kept"><?php esc_html_e('Kept', 'wp-asset-clean-up'); ?></span>
                                            <span class="is-when-removed is-restrictive"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                                        </span>
                                    </span>
                                </span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <aside class="wpacu-rss-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 16v-4"></path>
                        <path d="M12 8h.01"></path>
                    </svg>
                    <p>
                        <strong><?php esc_html_e('Not sure which setting to use?', 'wp-asset-clean-up'); ?></strong>
                        <?php esc_html_e('Keep feeds available. Remove only the discovery links when the goal is a cleaner page source without blocking feed URLs.', 'wp-asset-clean-up'); ?>
                    </p>
                </aside>
            </div>
        </section>
    </main>

    <script>
    (function () {
        'use strict';

        var root = document.getElementById('wpacu-rss-settings');

        if (! root || root.getAttribute('data-wpacu-rss-initialized') === '1') {
            return;
        }

        root.setAttribute('data-wpacu-rss-initialized', '1');

        var master = document.getElementById('wpacu_disable_rss_feed');
        var managedNotice = document.getElementById('wpacuRssManagedNotice');
        var childInputs = [
            document.getElementById('wpacu_remove_main_feed_link'),
            document.getElementById('wpacu_remove_comment_feed_link')
        ];
        var managedAlertMessage = <?php echo wp_json_encode(esc_html__('This option cannot be unchecked while “Disable WordPress feeds” is enabled. Turn off the master option first.', 'wp-asset-clean-up')); ?>;

        if (! master || ! childInputs[0] || ! childInputs[1]) {
            return;
        }

        function getChoiceLabel(input) {
            return root.querySelector('label[for="' + input.id + '"]');
        }

        function updateManagedState(forceChildrenToMasterState) {
            var feedsDisabled = master.checked;

            if (forceChildrenToMasterState || feedsDisabled) {
                childInputs.forEach(function (input) {
                    input.checked = feedsDisabled;
                });
            }

            root.classList.toggle('is-feeds-disabled', feedsDisabled);
            root.setAttribute('data-feed-state', feedsDisabled ? 'disabled' : 'available');

            childInputs.forEach(function (input) {
                var label = getChoiceLabel(input);

                if (feedsDisabled) {
                    input.setAttribute('aria-disabled', 'true');
                    input.setAttribute('tabindex', '-1');
                } else {
                    input.removeAttribute('aria-disabled');
                    input.removeAttribute('tabindex');
                }

                if (label) {
                    label.classList.toggle('is-managed', feedsDisabled);

                    if (feedsDisabled) {
                        label.setAttribute('aria-disabled', 'true');
                    } else {
                        label.removeAttribute('aria-disabled');
                    }
                }
            });

            if (managedNotice) {
                managedNotice.hidden = ! feedsDisabled;
            }
        }

        master.addEventListener('change', function () {
            /*
             * The top setting is a strict master override:
             * ON  => both discovery-link options are selected and managed.
             * OFF => both are cleared and become independent again.
             */
            updateManagedState(true);
        });

        childInputs.forEach(function (input) {
            input.addEventListener('click', function (event) {
                if (! master.checked) {
                    return;
                }

                event.preventDefault();
                input.checked = true;
                window.alert(managedAlertMessage);
            });

            input.addEventListener('change', function () {
                if (master.checked) {
                    input.checked = true;
                }
            });
        });

        updateManagedState(false);
    })();
    </script>
</div>
