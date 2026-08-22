<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}

$tabIdArea       = 'wpacu-setting-test-mode';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea)
    ? 'style="display: table-cell;"'
    : '';
$testModeEnabled = ! empty($data['test_mode']);
$settingsName    = WPACU_PLUGIN_ID . '_settings';
?>
<div id="<?php echo esc_attr($tabIdArea); ?>"
     class="wpacu-settings-tab-content"
     <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <main id="wpacu-test-mode-settings" class="wpacu-test-mode-page">
        <section class="wpacu-test-mode-panel" aria-labelledby="wpacuTestModeTitle">
            <header class="wpacu-test-mode-header">
                <div>
                    <div class="wpacu-test-mode-eyebrow">
                        <?php esc_html_e('Safe testing', 'wp-asset-clean-up'); ?>
                    </div>
                    <h2 id="wpacuTestModeTitle">
                        <?php esc_html_e('Test changes privately before visitors see them', 'wp-asset-clean-up'); ?>
                    </h2>
                    <p>
                        <?php esc_html_e('Use Test Mode while configuring Asset CleanUp. Your saved rules are applied only to logged-in users who can access the plugin, while visitors continue to receive the unchanged public version.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>
                <div class="wpacu-test-mode-header-badge">
                    <?php esc_html_e('Recommended during setup', 'wp-asset-clean-up'); ?>
                </div>
            </header>

            <div class="wpacu-test-mode-body">
                <section class="wpacu-test-mode-intro" aria-labelledby="wpacuTestModeIntroTitle">
                    <div class="wpacu-test-mode-intro-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>
                            <path d="m9 12 2 2 4-4"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 id="wpacuTestModeIntroTitle">
                            <?php esc_html_e('A safer workspace for optimization', 'wp-asset-clean-up'); ?>
                        </h3>
                        <p>
                            <?php esc_html_e('Experiment with unload rules and other Asset CleanUp optimizations without exposing unfinished changes to visitors. Test Mode does not delete rules, and turning it off does not reset your settings.', 'wp-asset-clean-up'); ?>
                        </p>
                    </div>
                </section>

                <section class="wpacu-test-mode-master" aria-labelledby="wpacuTestModeToggleTitle">
                    <div class="wpacu-test-mode-master-control">
                        <label class="wpacu_switch" for="wpacu_test_mode_enable">
                            <input id="wpacu_test_mode_enable"
                                   type="checkbox"
                                   name="<?php echo esc_attr($settingsName); ?>[test_mode]"
                                   value="1"
                                   aria-describedby="wpacuTestModeSaveHint"
                                   <?php checked($testModeEnabled); ?> />
                            <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                        </label>
                        <label class="wpacu-test-mode-control-label" for="wpacu_test_mode_enable">
                            <strong><?php esc_html_e('Test Mode', 'wp-asset-clean-up'); ?></strong>
                            <span id="wpacuTestModeSaveHint"><?php esc_html_e('Save this page after changing the switch.', 'wp-asset-clean-up'); ?></span>
                        </label>
                    </div>

                    <div class="wpacu-test-mode-master-copy">
                        <span class="wpacu-test-mode-master-kicker">
                            <?php esc_html_e('Main setting', 'wp-asset-clean-up'); ?>
                        </span>
                        <h3 id="wpacuTestModeToggleTitle">
                            <?php esc_html_e('Enable Test Mode', 'wp-asset-clean-up'); ?>
                        </h3>
                        <p>
                            <?php esc_html_e('Keep Asset CleanUp changes private while you configure and verify them. Disable Test Mode when the optimized version is ready to be shown to everyone.', 'wp-asset-clean-up'); ?>
                        </p>
                    </div>
                </section>

                <div class="wpacu-test-mode-section-heading">
                    <h3><?php esc_html_e('Who sees each version?', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Test Mode separates your private testing session from the public visitor experience.', 'wp-asset-clean-up'); ?></p>
                </div>

                <div class="wpacu-test-mode-audience-grid">
                    <article class="wpacu-test-mode-audience-card wpacu-test-mode-audience-card--private">
                        <div class="wpacu-test-mode-audience-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21a8 8 0 0 0-16 0"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                                <path d="m16 11 2 2 3-3"></path>
                            </svg>
                        </div>
                        <div>
                            <span class="wpacu-test-mode-audience-badge">
                                <?php esc_html_e('Optimized test version', 'wp-asset-clean-up'); ?>
                            </span>
                            <h4><?php esc_html_e('Your logged-in testing session', 'wp-asset-clean-up'); ?></h4>
                            <p>
                                <?php esc_html_e('You—and any other logged-in user granted access to Asset CleanUp—see the saved unload rules and optimization settings.', 'wp-asset-clean-up'); ?>
                            </p>
                        </div>
                    </article>

                    <article class="wpacu-test-mode-audience-card wpacu-test-mode-audience-card--public">
                        <div class="wpacu-test-mode-audience-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M2 12h20"></path>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"></path>
                            </svg>
                        </div>
                        <div>
                            <span class="wpacu-test-mode-audience-badge">
                                <?php esc_html_e('Current public version', 'wp-asset-clean-up'); ?>
                            </span>
                            <h4><?php esc_html_e('Visitors, bots, and external services', 'wp-asset-clean-up'); ?></h4>
                            <p>
                                <?php esc_html_e('Guest sessions receive the site without Asset CleanUp changes while Test Mode is enabled.', 'wp-asset-clean-up'); ?>
                            </p>
                        </div>
                    </article>
                </div>

                <div class="wpacu-test-mode-section-heading wpacu-test-mode-section-heading--workflow">
                    <h3><?php esc_html_e('Recommended workflow', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Follow these steps to test safely and avoid confusing the private and public versions.', 'wp-asset-clean-up'); ?></p>
                </div>

                <div class="wpacu-test-mode-steps">
                    <article class="wpacu-test-mode-step">
                        <span class="wpacu-test-mode-step-number" aria-hidden="true">1</span>
                        <div>
                            <h4><?php esc_html_e('Enable and save Test Mode', 'wp-asset-clean-up'); ?></h4>
                            <p><?php
                            printf(
                                /* translators: %s: "Save Changes" button label */
                                esc_html__('Turn on the setting above, then click %s before changing any unload or optimization rules.', 'wp-asset-clean-up'),
                                '"' . esc_html__('Save Changes', 'wp-asset-clean-up') . '"'
                            );
                            ?></p>
                        </div>
                    </article>

                    <article class="wpacu-test-mode-step">
                        <span class="wpacu-test-mode-step-number" aria-hidden="true">2</span>
                        <div>
                            <h4><?php esc_html_e('Change one thing at a time', 'wp-asset-clean-up'); ?></h4>
                            <p><?php esc_html_e('Make a small number of changes so that any layout or functionality problem is easy to identify and reverse.', 'wp-asset-clean-up'); ?></p>
                        </div>
                    </article>

                    <article class="wpacu-test-mode-step">
                        <span class="wpacu-test-mode-step-number" aria-hidden="true">3</span>
                        <div>
                            <h4><?php esc_html_e('Test while logged in', 'wp-asset-clean-up'); ?></h4>
                            <p><?php esc_html_e('Use your current browser session to check menus, forms, sliders, popups, product pages, add-to-cart actions, checkout, and other interactive elements.', 'wp-asset-clean-up'); ?></p>
                        </div>
                    </article>

                    <article class="wpacu-test-mode-step">
                        <span class="wpacu-test-mode-step-number" aria-hidden="true">4</span>
                        <div>
                            <h4><?php esc_html_e('Publish and verify', 'wp-asset-clean-up'); ?></h4>
                            <p><?php esc_html_e('Disable Test Mode, save the settings, clear relevant caches, and then verify the public page in a private browser window before running the final performance test.', 'wp-asset-clean-up'); ?></p>
                        </div>
                    </article>
                </div>

                <section class="wpacu-test-mode-notice wpacu-test-mode-notice--warning" aria-labelledby="wpacuTestModePerformanceTitle">
                    <div class="wpacu-test-mode-notice-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.3 2.9 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.9a2 2 0 0 0-3.4 0Z"></path>
                            <path d="M12 9v4"></path>
                            <path d="M12 17h.01"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 id="wpacuTestModePerformanceTitle">
                            <?php esc_html_e('External performance tools see the public version', 'wp-asset-clean-up'); ?>
                        </h3>
                        <p>
                            <?php esc_html_e('PageSpeed Insights, GTmetrix, Pingdom, and similar services visit as guests. While Test Mode is enabled, their reports will not include your private Asset CleanUp changes. Disable Test Mode and clear the relevant caches before the final measurement.', 'wp-asset-clean-up'); ?>
                        </p>
                    </div>
                </section>

                <div class="wpacu-test-mode-note-grid">
                    <aside class="wpacu-test-mode-note">
                        <div class="wpacu-test-mode-note-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="14" x="3" y="5" rx="2"></rect>
                                <path d="M8 3v4"></path>
                                <path d="M16 3v4"></path>
                                <path d="M8 11h.01"></path>
                                <path d="M12 11h.01"></path>
                                <path d="M16 11h.01"></path>
                            </svg>
                        </div>
                        <div>
                            <strong><?php esc_html_e('Using a private or incognito window', 'wp-asset-clean-up'); ?></strong>
                            <p><?php esc_html_e('While Test Mode is enabled, a private window shows the unchanged visitor version because you are not logged in. Use your logged-in browser to test the optimized version.', 'wp-asset-clean-up'); ?></p>
                        </div>
                    </aside>

                    <aside class="wpacu-test-mode-note">
                        <div class="wpacu-test-mode-note-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 3v18h18"></path>
                                <path d="m7 16 4-5 4 3 5-7"></path>
                            </svg>
                        </div>
                        <div>
                            <strong><?php esc_html_e('Test Mode is not a staging site', 'wp-asset-clean-up'); ?></strong>
                            <p><?php esc_html_e('It limits only Asset CleanUp front-end changes. Theme, plugin, content, database, and other WordPress changes are not isolated by this setting.', 'wp-asset-clean-up'); ?></p>
                        </div>
                    </aside>
                </div>

                <div class="wpacu-test-mode-footer-link">
                    <a href="https://www.assetcleanup.com/docs/?p=84" target="_blank" rel="noopener noreferrer">
                        <span class="dashicons dashicons-external" aria-hidden="true"></span>
                        <?php esc_html_e('Read the Test Mode documentation', 'wp-asset-clean-up'); ?>
                    </a>
                </div>
            </div>
        </section>
    </main>
</div>