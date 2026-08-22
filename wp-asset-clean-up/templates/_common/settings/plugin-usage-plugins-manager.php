<?php
if (! isset($data)) {
    exit;
}

$isLitePluginManagerPreview = ! defined('WPACU_PRO_PLUGIN_VERSION');
$settingsName = WPACU_PLUGIN_ID . '_settings';
$pluginsManagerFrontLayout = isset($data['plugins_manager_front_layout'])
    ? (string) $data['plugins_manager_front_layout']
    : 'tabs';
$pluginsManagerFrontLayouts = $isLitePluginManagerPreview
    ? array('tabs' => array('label' => __('Tabbed layout', 'wp-asset-clean-up')))
    : \WpAssetCleanUpPro\Admin\PluginsManagerFrontLayout::getRegisteredLayouts();
$pluginsManagerFrontDisable = $isLitePluginManagerPreview || ! wpacuPluginsManagerIsEnabled();
$pluginsManagerDashDisable  = $isLitePluginManagerPreview || ! wpacuPluginsManagerIsEnabled('dash');
$isDashboardFilteringAvailable = ! $isLitePluginManagerPreview && wpacuIsDefinedConstant('WPACU_ALLOW_DASH_PLUGIN_FILTER');
$restoreDashboardSidebar = ! empty($data['plugins_manage_dash_restore_left_sidebar']);
$dashboardLoadingType = isset($data['plugins_manage_dash_restore_left_sidebar_options']['loading_type'])
    ? (string) $data['plugins_manage_dash_restore_left_sidebar_options']['loading_type']
    : 'no_overlay';
?>

<main class="wpacu-pm-settings-page" data-wpacu-dashboard-filtering-available="<?php echo $isDashboardFilteringAvailable ? '1' : '0'; ?>">
    <?php if ($isLitePluginManagerPreview) { ?>
        <div class="wpacu-warning" style="font-size: inherit; padding: 12px; line-height: 22px; margin: 0 0 24px;">
            <img style="margin: 0 6px 0 0; opacity: .65; vertical-align: text-bottom;" width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg'); ?>" alt="" />
            <strong><?php esc_html_e('Plugins Manager rule delivery is available in Asset CleanUp Pro.', 'wp-asset-clean-up'); ?></strong>
            <a href="<?php echo esc_url(apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=plugin_usage_preferences&utm_medium=plugins_manager')); ?>"><?php esc_html_e('Unlock it', 'wp-asset-clean-up'); ?></a>
        </div>
        <fieldset disabled="disabled" style="border: 0; margin: 0; padding: 0; opacity: .55;">
    <?php } ?>
    <section class="wpacu-pm-settings-panel" aria-labelledby="wpacuPmSettingsTitle">
        <header class="wpacu-pm-settings-header">
            <div>
                <div class="wpacu-pm-settings-eyebrow"><?php esc_html_e('Plugin rule delivery', 'wp-asset-clean-up'); ?></div>
                <h2 id="wpacuPmSettingsTitle"><?php esc_html_e('Control where Plugins Manager unload rules take effect', 'wp-asset-clean-up'); ?></h2>
                <p><?php esc_html_e('Keep your configured rules active for visitors and, when explicitly allowed, inside the WordPress Dashboard. Each environment can be paused independently without deleting its saved rules.', 'wp-asset-clean-up'); ?></p>
            </div>
            <div class="wpacu-pm-settings-header-badge"><?php esc_html_e('Rules stay saved', 'wp-asset-clean-up'); ?></div>
        </header>

        <div class="wpacu-pm-settings-body">
            <section class="wpacu-pm-settings-intro" aria-labelledby="wpacuPmSettingsIntroTitle">
                <div class="wpacu-pm-settings-intro-icon" aria-hidden="true"><span class="dashicons dashicons-filter"></span></div>
                <div>
                    <h3 id="wpacuPmSettingsIntroTitle"><?php esc_html_e('Pause rules safely while troubleshooting', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('By default, Plugins Manager unload rules are applied wherever they were configured. If a page stops working after a plugin was unloaded, pause the relevant environment below, save the settings, and test again.', 'wp-asset-clean-up'); ?></p>
                </div>
            </section>

            <div class="wpacu-pm-settings-section-heading">
                <span class="wpacu-pm-settings-step">1</span>
                <div>
                    <h3><?php esc_html_e('Front-end rules', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Control plugin unload rules that affect public pages viewed by visitors and logged-in users.', 'wp-asset-clean-up'); ?></p>
                </div>
                <span class="wpacu-pm-settings-status <?php echo $pluginsManagerFrontDisable ? 'is-paused' : 'is-active'; ?>">
                    <?php echo $pluginsManagerFrontDisable ? esc_html__('Paused', 'wp-asset-clean-up') : esc_html__('Active', 'wp-asset-clean-up'); ?>
                </span>
            </div>

            <section class="wpacu-pm-settings-environment wpacu-pm-settings-environment--front" aria-labelledby="wpacuPmFrontTitle">
                <div class="wpacu-pm-settings-master">
                    <div class="wpacu-pm-settings-master-control">
                        <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[plugins_manager_front_disable]" value="1">
                        <label class="wpacu_switch" for="wpacu_plugins_manager_front_disable_checkbox">
                            <input id="wpacu_plugins_manager_front_disable_checkbox"
                                   type="checkbox"
                                   data-target-opacity="#wpacu-plugins-manager-front-options"
                                <?php checked(! $pluginsManagerFrontDisable); ?>
                                   name="<?php echo esc_attr($settingsName); ?>[plugins_manager_front_disable]"
                                   value="0" />
                            <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                        </label>
                        <label class="wpacu-pm-settings-control-label" for="wpacu_plugins_manager_front_disable_checkbox">
                            <strong><?php esc_html_e('Apply front-end rules', 'wp-asset-clean-up'); ?></strong>
                            <span><?php esc_html_e('Save after changing this switch.', 'wp-asset-clean-up'); ?></span>
                        </label>
                    </div>
                    <div class="wpacu-pm-settings-master-copy">
                        <span class="wpacu-pm-settings-kicker"><?php esc_html_e('Main setting', 'wp-asset-clean-up'); ?></span>
                        <h3 id="wpacuPmFrontTitle"><?php esc_html_e('Keep visitor-facing unload rules enabled', 'wp-asset-clean-up'); ?></h3>
                        <p><?php esc_html_e('Turn this off temporarily when checking whether a Plugins Manager rule is responsible for a front-end problem. The same switch is available on the Plugins Manager page.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </div>

                <div id="wpacu-plugins-manager-front-options" class="wpacu-pm-settings-dependent" style="<?php echo $pluginsManagerFrontDisable ? 'opacity: 0.4;' : 'opacity: 1;'; ?>">
                    <div class="wpacu-pm-settings-option-grid">
                        <div class="wpacu-pm-settings-option-copy">
                            <span class="dashicons dashicons-layout" aria-hidden="true"></span>
                            <div>
                                <h4><?php esc_html_e('Plugins Manager layout', 'wp-asset-clean-up'); ?></h4>
                                <p><?php esc_html_e('Choose how front-end plugin rules are organized while you work. This changes the management interface only, not the rules applied to visitors.', 'wp-asset-clean-up'); ?></p>
                            </div>
                        </div>
                        <div class="wpacu-pm-settings-field">
                            <label for="wpacu_plugins_manager_front_layout"><?php esc_html_e('Interface layout', 'wp-asset-clean-up'); ?></label>
                            <select id="wpacu_plugins_manager_front_layout" name="<?php echo esc_attr($settingsName); ?>[plugins_manager_front_layout]">
                                <?php foreach ($pluginsManagerFrontLayouts as $layoutSlug => $layoutData) {
                                    if (empty($layoutData['label'])) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr($layoutSlug); ?>" <?php selected($pluginsManagerFrontLayout, $layoutSlug); ?>><?php echo esc_html($layoutData['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="wpacu-pm-settings-query-card">
                        <div class="wpacu-pm-settings-query-heading">
                            <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
                            <div>
                                <span class="wpacu-pm-settings-kicker"><?php esc_html_e('Homepage detection in Asset CleanUp Pro', 'wp-asset-clean-up'); ?></span>
                                <h4><?php esc_html_e('Recognize the homepage when harmless URL parameters are present', 'wp-asset-clean-up'); ?></h4>
                                <p><?php esc_html_e('Tell Asset CleanUp which extra query parameters may be ignored when deciding whether a URL is still your homepage.', 'wp-asset-clean-up'); ?></p>
                            </div>
                        </div>

                        <div class="wpacu-pm-settings-callout wpacu-pm-settings-callout--info">
                            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                            <p><?php esc_html_e('Normally, an unfamiliar query parameter can make a homepage URL look like a different page. Add it here only if the parameter does not change the content or trigger an action. Asset CleanUp can then recognize that URL as the same homepage and can also ignore the parameter when comparing URLs before and after a redirect.', 'wp-asset-clean-up'); ?></p>
                        </div>

                        <div class="wpacu-pm-settings-example-grid">
                            <div><strong><?php esc_html_e('Same homepage — safe to ignore', 'wp-asset-clean-up'); ?></strong><span><code>/?cache_cleared=1</code><br><code>/?rand=12321432</code></span></div>
                            <div><strong><?php esc_html_e('Do not ignore', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('AJAX, filtering, preview, search, security, or action parameters', 'wp-asset-clean-up'); ?></span></div>
                        </div>

                        <label class="wpacu-pm-settings-textarea-label" for="wpacu_plugins_manager_extra_ignored_parameters">
                            <strong><?php esc_html_e('Extra parameters that still represent the homepage', 'wp-asset-clean-up'); ?></strong>
                            <span><?php esc_html_e('Enter only the parameter name, one per line. For /?cache_cleared=1, enter cache_cleared — without ?, =, or the value.', 'wp-asset-clean-up'); ?></span>
                        </label>
                        <textarea id="wpacu_plugins_manager_extra_ignored_parameters"
                                  name="<?php echo esc_attr($settingsName); ?>[plugins_manager_front_homepage_detect_extra_ignore_query_string_list]"
                                  placeholder="Example:&#10;cache_cleared&#10;rand"
                                  data-wpacu-adapt-height="1"><?php echo esc_textarea(isset($data['plugins_manager_front_homepage_detect_extra_ignore_query_string_list']) ? $data['plugins_manager_front_homepage_detect_extra_ignore_query_string_list'] : ''); ?></textarea>
                        <p class="wpacu-pm-settings-doc-link"><a class="wpacu-new-style-external-link" target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=2130"><span class="wpacu-new-style-external-link__text"><?php esc_html_e('See how homepage detection works and which parameters are already ignored', 'wp-asset-clean-up'); ?></span><span class="dashicons dashicons-external" aria-hidden="true"></span></a></p>
                    </div>
                </div>
            </section>

            <div class="wpacu-pm-settings-section-heading">
                <span class="wpacu-pm-settings-step">2</span>
                <div>
                    <h3><?php esc_html_e('WordPress Dashboard rules', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Control plugin unload rules configured specifically for admin pages under /wp-admin/.', 'wp-asset-clean-up'); ?></p>
                </div>
                <span class="wpacu-pm-settings-status <?php echo ! $isDashboardFilteringAvailable ? 'is-locked' : ($pluginsManagerDashDisable ? 'is-paused' : 'is-active'); ?>">
                    <?php echo ! $isDashboardFilteringAvailable ? esc_html__('Manual setup required', 'wp-asset-clean-up') : ($pluginsManagerDashDisable ? esc_html__('Paused', 'wp-asset-clean-up') : esc_html__('Active', 'wp-asset-clean-up')); ?>
                </span>
            </div>

            <section class="wpacu-pm-settings-environment wpacu-pm-settings-environment--dashboard" aria-labelledby="wpacuPmDashboardTitle">
                <?php if (! $isDashboardFilteringAvailable) { ?>
                    <div class="wpacu-pm-settings-callout wpacu-pm-settings-callout--warning">
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <div>
                            <strong><?php esc_html_e('Explicit access is required for Dashboard plugin filtering', 'wp-asset-clean-up'); ?></strong>
                            <p><?php esc_html_e('Unloading a required plugin inside /wp-admin/ can disrupt the Dashboard. Enable the WPACU_ALLOW_DASH_PLUGIN_FILTER constant in wp-config.php before the settings below can take effect.', 'wp-asset-clean-up'); ?></p>
                            <a class="wpacu-new-style-external-link" target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=1128"><span class="wpacu-new-style-external-link__text"><?php esc_html_e('Read the setup and safety instructions', 'wp-asset-clean-up'); ?></span><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
                        </div>
                    </div>
                <?php } ?>

                <div class="wpacu-pm-settings-dashboard-config" style="<?php echo ! $isDashboardFilteringAvailable ? 'opacity: 0.4;' : 'opacity: 1;'; ?>">
                    <div class="wpacu-pm-settings-master">
                        <div class="wpacu-pm-settings-master-control">
                            <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[plugins_manager_dash_disable]" value="1">
                            <label class="wpacu_switch" for="wpacu_plugins_manager_dash_enable_checkbox">
                                <input id="wpacu_plugins_manager_dash_enable_checkbox"
                                       data-target-opacity="#wpacu-plugins-manager-left-sidebar-options"
                                       type="checkbox"
                                    <?php checked(! $pluginsManagerDashDisable); ?>
                                       name="<?php echo esc_attr($settingsName); ?>[plugins_manager_dash_disable]"
                                       value="0" />
                                <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                            </label>
                            <label class="wpacu-pm-settings-control-label" for="wpacu_plugins_manager_dash_enable_checkbox">
                                <strong><?php esc_html_e('Apply Dashboard rules', 'wp-asset-clean-up'); ?></strong>
                                <span><?php esc_html_e('Save after changing this switch.', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>
                        <div class="wpacu-pm-settings-master-copy">
                            <span class="wpacu-pm-settings-kicker"><?php esc_html_e('Main setting', 'wp-asset-clean-up'); ?></span>
                            <h3 id="wpacuPmDashboardTitle"><?php esc_html_e('Keep /wp-admin/ unload rules enabled', 'wp-asset-clean-up'); ?></h3>
                            <p><?php esc_html_e('Pause these rules first if an admin screen becomes incomplete or stops responding. The same switch is available in the Dashboard area of Plugins Manager.', 'wp-asset-clean-up'); ?></p>
                        </div>
                    </div>

                    <div id="wpacu-plugins-manager-left-sidebar-options" class="wpacu-pm-settings-dependent" style="<?php echo $pluginsManagerDashDisable ? 'opacity: 0.4;' : 'opacity: 1;'; ?>">
                        <div class="wpacu-pm-settings-sidebar-card">
                            <div class="wpacu-pm-settings-option-copy">
                                <span class="wpacu-pm-settings-sidebar-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2.5" y="3" width="19" height="18" rx="2.5"></rect>
                                        <path d="M8.5 3v18"></path>
                                        <path d="M4.7 7h1.6M4.7 10.5h1.6M4.7 14h1.6"></path>
                                        <path d="M11.5 7.5h7M11.5 11h5M11.5 14.5h6"></path>
                                    </svg>
                                </span>
                                <div>
                                    <h4><?php esc_html_e('Preserve the WordPress admin sidebar', 'wp-asset-clean-up'); ?></h4>
                                    <p><?php esc_html_e('Restore the left navigation after plugins are unloaded, helping you retain access to Dashboard pages even when a plugin normally contributes menu items or styles.', 'wp-asset-clean-up'); ?></p>
                                    <a class="wpacu-new-style-external-link" target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=1923"><span class="wpacu-new-style-external-link__text"><?php esc_html_e('How sidebar restoration works', 'wp-asset-clean-up'); ?></span><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
                                </div>
                            </div>

                            <label class="wpacu-pm-settings-checkbox" for="<?php echo esc_attr(WPACU_PLUGIN_ID . '_plugins_manager_left_sidebar_restore'); ?>">
                                <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[plugins_manage_dash_restore_left_sidebar]" value="0" />
                                <input type="checkbox"
                                       data-target-opacity="#wpacu-left-sidebar-loading-options-area"
                                       name="<?php echo esc_attr($settingsName); ?>[plugins_manage_dash_restore_left_sidebar]"
                                    <?php checked($restoreDashboardSidebar); ?>
                                       id="<?php echo esc_attr(WPACU_PLUGIN_ID . '_plugins_manager_left_sidebar_restore'); ?>"
                                       value="1" />
                                <span><strong><?php esc_html_e('Restore the left sidebar', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Recommended when Dashboard rules are active', 'wp-asset-clean-up'); ?></small></span>
                            </label>

                            <fieldset id="wpacu-left-sidebar-loading-options-area" class="wpacu-pm-settings-loading-options" style="<?php echo ! $restoreDashboardSidebar ? 'opacity: 0.4;' : 'opacity: 1;'; ?>">
                                <legend><?php esc_html_e('Restoration feedback', 'wp-asset-clean-up'); ?></legend>
                                <p><?php esc_html_e('Choose what administrators see while Asset CleanUp reconstructs the sidebar.', 'wp-asset-clean-up'); ?></p>
                                <div class="wpacu-pm-settings-choice-grid">
                                    <?php
                                    $loadingChoices = array(
                                        'no_overlay'     => array(__('No overlay', 'wp-asset-clean-up'), __('Restore without covering the screen.', 'wp-asset-clean-up')),
                                        'overlay'        => array(__('Show overlay', 'wp-asset-clean-up'), __('Temporarily prevent interaction.', 'wp-asset-clean-up')),
                                        'overlay_loader' => array(__('Overlay and spinner', 'wp-asset-clean-up'), __('Show visible loading progress.', 'wp-asset-clean-up')),
                                    );
                                    foreach ($loadingChoices as $choiceValue => $choiceData) {
                                        $choiceId = 'plugins_manage_dash_restore_left_sidebar_options_loading_type_' . $choiceValue;
                                        ?>
                                        <label class="wpacu-pm-settings-choice" for="<?php echo esc_attr($choiceId); ?>">
                                            <input type="radio"
                                                   id="<?php echo esc_attr($choiceId); ?>"
                                                   name="<?php echo esc_attr($settingsName); ?>[plugins_manage_dash_restore_left_sidebar_options][loading_type]"
                                                <?php checked($dashboardLoadingType, $choiceValue); ?>
                                                   value="<?php echo esc_attr($choiceValue); ?>" />
                                            <span><strong><?php echo esc_html($choiceData[0]); ?></strong><small><?php echo esc_html($choiceData[1]); ?></small></span>
                                        </label>
                                    <?php } ?>
                                </div>
                            </fieldset>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="wpacu-pm-settings-footer-note">
                <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                <p><strong><?php esc_html_e('Pausing does not remove rules.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Your existing Plugins Manager configuration remains available and can be reactivated after troubleshooting.', 'wp-asset-clean-up'); ?></p>
            </aside>
        </div>
    </section>
    <?php if ($isLitePluginManagerPreview) { ?></fieldset><?php } ?>
</main>
