<?php
use WpAssetCleanUp\Admin\SettingsAdmin;
use WpAssetCleanUp\Admin\SettingsAdminOnlyForAdmin;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;
use WpAssetCleanUp\Settings;

if (! isset($data)) {
	exit;
}

$settingsName                = WPACU_PLUGIN_ID . '_settings';
$postTypesList               = isset($data['post_types_list']) && is_array($data['post_types_list']) ? $data['post_types_list'] : array();
$useEnhancedInputs           = Settings::useEnhancedInputs($data);
$dashboardEnabled            = ! empty($data['dashboard_show']);
$frontendEnabled             = ! empty($data['frontend_show']);
$showAssetsMetaBox           = ! empty($data['show_assets_meta_box']);
$hideCoreFiles               = ! empty($data['hide_core_files']);
$canConfigureAdminAccess     = current_user_can(Menu::$defaultAccessRole);
$domGetType                  = isset($data['dom_get_type']) && in_array($data['dom_get_type'], array('direct', 'wp_remote_post'), true) ? $data['dom_get_type'] : 'direct';
$assetsListShowStatus        = isset($data['assets_list_show_status']) && $data['assets_list_show_status'] === 'fetch_on_click' ? 'fetch_on_click' : 'default';
$hideMetaBoxesForPostTypes   = isset($data['hide_meta_boxes_for_post_types']) && is_array($data['hide_meta_boxes_for_post_types']) ? $data['hide_meta_boxes_for_post_types'] : array();
$frontendShowExceptions      = isset($data['frontend_show_exceptions']) ? (string) $data['frontend_show_exceptions'] : '';
$allowManageAssetsTo         = isset($data['allow_manage_assets_to']) && in_array($data['allow_manage_assets_to'], array('selected', 'chosen', 'selected_roles'), true) ? 'selected' : 'any_admin';
$allowManageAssetsToList     = isset($data['allow_manage_assets_to_list']) && is_array($data['allow_manage_assets_to_list']) ? array_map('intval', $data['allow_manage_assets_to_list']) : array();
$allowManageAssetsToRoles    = isset($data['allow_manage_assets_to_roles']) && is_array($data['allow_manage_assets_to_roles']) ? array_map('sanitize_key', $data['allow_manage_assets_to_roles']) : array();
$rolesVisibilityEnabled      = ! empty($data['allow_manage_assets_via_roles']) || in_array($allowManageAssetsTo, array('selected', 'selected_roles'), true);
$usersVisibilityEnabled      = ! empty($data['allow_manage_assets_via_users']) || in_array($allowManageAssetsTo, array('selected', 'chosen'), true);
$assetsListLayout            = isset($data['assets_list_layout']) ? (string) $data['assets_list_layout'] : 'by-location';
$pluginAreaStatus            = isset($data['assets_list_layout_plugin_area_status']) && $data['assets_list_layout_plugin_area_status'] === 'contracted' ? 'contracted' : 'expanded';
$groupsStatus                = isset($data['assets_list_layout_areas_status']) && $data['assets_list_layout_areas_status'] === 'contracted' ? 'contracted' : 'expanded';
$inlineCodeStatus            = isset($data['assets_list_inline_code_status']) && $data['assets_list_inline_code_status'] === 'contracted' ? 'contracted' : 'expanded';
$fetchCachedFilesDetailsFrom = isset($data['fetch_cached_files_details_from']) && in_array($data['fetch_cached_files_details_from'], array('disk', 'db', 'db_disk'), true) ? $data['fetch_cached_files_details_from'] : 'disk';
$clearCachedFilesAfter       = isset($data['clear_cached_files_after']) ? max(1, (int) $data['clear_cached_files_after']) : 14;
$criticalCssEnabled          = ! isset($data['critical_css_status']) || $data['critical_css_status'] !== 'off';
$criticalCssRuleStats        = isset($data['critical_css_rule_stats']) && is_array($data['critical_css_rule_stats'])
    ? $data['critical_css_rule_stats']
    : array();
$criticalCssSavedRules       = isset($criticalCssRuleStats['total_count']) ? (int)$criticalCssRuleStats['total_count'] : 0;
$criticalCssEnabledRules     = isset($criticalCssRuleStats['enabled_count']) ? (int)$criticalCssRuleStats['enabled_count'] : 0;
$manageCriticalCssUrl        = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css');
$criticalCssRulesSummary     = $criticalCssSavedRules > 0
    ? sprintf(
        __('%1$s saved · %2$s enabled', 'wp-asset-clean-up'),
        number_format_i18n($criticalCssSavedRules),
        number_format_i18n($criticalCssEnabledRules)
    )
    : __('No saved rules yet', 'wp-asset-clean-up');
?>

<main id="wpacu-css-js-manager-settings" class="wpacu-cssjs-page">
    <section class="wpacu-cssjs-panel" aria-labelledby="wpacuCssJsTitle">
        <header class="wpacu-cssjs-header">
            <div class="wpacu-cssjs-header__copy">
                <div class="wpacu-cssjs-eyebrow"><?php esc_html_e('CSS/JS Manager setup', 'wp-asset-clean-up'); ?></div>
                <h2 id="wpacuCssJsTitle"><?php esc_html_e('Choose where and how you manage page assets', 'wp-asset-clean-up'); ?></h2>
                <p><?php esc_html_e('Configure the Dashboard and front-end workspaces, Critical CSS rule delivery, who can use the manager, how asset lists open, and where optimized-file lookup data is stored. These preferences do not delete existing rules.', 'wp-asset-clean-up'); ?></p>
            </div>
            <div class="wpacu-cssjs-header__actions">
                <span class="wpacu-cssjs-header__badge"><?php esc_html_e('Manager preferences', 'wp-asset-clean-up'); ?></span>
                <a class="wpacu-cssjs-header__action wpacu-new-style-external-link" href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_css_js')); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="wpacu-new-style-external-link__text"><?php esc_html_e('Open CSS/JS Manager', 'wp-asset-clean-up'); ?></span>
                    <span class="dashicons dashicons-external" aria-hidden="true"></span>
                </a>
            </div>
        </header>

        <div class="wpacu-cssjs-body">
            <section class="wpacu-cssjs-intro" aria-labelledby="wpacuCssJsIntroTitle">
                <span class="wpacu-cssjs-intro__icon" aria-hidden="true"><span class="dashicons dashicons-admin-generic"></span></span>
                <div>
                    <h3 id="wpacuCssJsIntroTitle"><?php esc_html_e('Use the Dashboard for complete page-type coverage', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('The Dashboard CSS/JS Manager can load singular pages, taxonomy archives, author and date archives, search results, and the active theme’s 404 template. Front-end management is optional and is useful when you want to inspect a page in place.', 'wp-asset-clean-up'); ?></p>
                </div>
            </section>

            <nav class="wpacu-cssjs-quick-nav" aria-label="<?php esc_attr_e('CSS/JS Manager settings sections', 'wp-asset-clean-up'); ?>">
                <span class="wpacu-cssjs-quick-nav__label wpacu-cssjs-quick-nav__label--manager"><?php esc_html_e('Manage CSS/JS', 'wp-asset-clean-up'); ?></span>
                <a href="#wpacu-cssjs-management-locations"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php esc_html_e('Workspaces', 'wp-asset-clean-up'); ?></a>
                <?php if ($canConfigureAdminAccess) { ?>
                    <a href="#wpacu-cssjs-access"><span class="dashicons dashicons-visibility" aria-hidden="true"></span><?php esc_html_e('Visibility', 'wp-asset-clean-up'); ?></a>
                <?php } ?>
                <a href="#wpacu-cssjs-list-appearance"><span class="dashicons dashicons-layout" aria-hidden="true"></span><?php esc_html_e('List appearance', 'wp-asset-clean-up'); ?></a>
                <a href="#wpacu-cssjs-optimized-cache"><span class="dashicons dashicons-database" aria-hidden="true"></span><?php esc_html_e('Optimized cache', 'wp-asset-clean-up'); ?></a>
                <span class="wpacu-cssjs-quick-nav__divider" aria-hidden="true"></span>
                <span class="wpacu-cssjs-quick-nav__label wpacu-cssjs-quick-nav__label--critical"><?php esc_html_e('Critical CSS', 'wp-asset-clean-up'); ?></span>
                <a class="wpacu-cssjs-quick-nav__link--critical" href="#wpacu-cssjs-critical-css"><span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span><?php esc_html_e('Delivery', 'wp-asset-clean-up'); ?></a>
            </nav>

            <div class="wpacu-cssjs-manager-wrap">
            <section class="wpacu-cssjs-scope-heading wpacu-cssjs-scope-heading--manager" aria-labelledby="wpacuCssJsManagerPreferencesTitle">
                <span class="wpacu-cssjs-scope-heading__icon" aria-hidden="true"><span class="dashicons dashicons-admin-settings"></span></span>
                <div>
                    <span class="wpacu-cssjs-scope-heading__eyebrow"><?php esc_html_e('Manage CSS/JS', 'wp-asset-clean-up'); ?></span>
                    <h3 id="wpacuCssJsManagerPreferencesTitle"><?php esc_html_e('CSS/JS management preferences', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Configure the workspaces, access, list presentation, and optimized-file storage used while managing stylesheet and script assets.', 'wp-asset-clean-up'); ?></p>
                </div>
            </section>

            <section id="wpacu-cssjs-management-locations" class="wpacu-cssjs-section" aria-labelledby="wpacuCssJsLocationsTitle">
                <div class="wpacu-cssjs-section__heading">
                    <span class="wpacu-cssjs-section__number" aria-hidden="true">1</span>
                    <div>
                        <h3 id="wpacuCssJsLocationsTitle"><?php esc_html_e('Management locations', 'wp-asset-clean-up'); ?></h3>
                        <p><?php esc_html_e('Enable one or both workspaces. Both manage the same saved asset rules, and disabling a view does not delete those rules.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </div>

                <article class="wpacu-cssjs-workspace" aria-labelledby="wpacuCssJsDashboardTitle">
                    <div class="wpacu-cssjs-master-row">
                        <div class="wpacu-cssjs-master-row__control">
                            <label class="wpacu_switch" for="wpacu_dashboard">
                                <input id="wpacu_dashboard"
                                       data-target-opacity="#wpacu_manage_dashboard_assets_list"
                                       type="checkbox"
                                       name="<?php echo esc_attr($settingsName); ?>[dashboard_show]"
                                       value="1"
                                    <?php checked($dashboardEnabled); ?> />
                                <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                            </label>
                            <label class="wpacu-cssjs-control-label" for="wpacu_dashboard">
                                <strong><?php esc_html_e('Enable Dashboard management', 'wp-asset-clean-up'); ?></strong>
                                <span><?php esc_html_e('Required for the dedicated wp-admin manager.', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>
                        <div class="wpacu-cssjs-master-row__copy">
                            <span class="wpacu-cssjs-kicker"><?php esc_html_e('Recommended workspace', 'wp-asset-clean-up'); ?></span>
                            <h4 id="wpacuCssJsDashboardTitle"><?php esc_html_e('Dashboard CSS/JS Manager', 'wp-asset-clean-up'); ?></h4>
                            <p><?php esc_html_e('Manage assets from the dedicated CSS/JS Manager page in wp-admin. This setting also allows the embedded manager on eligible edit screens when that option is enabled below.', 'wp-asset-clean-up'); ?></p>
                        </div>
                    </div>

                    <div id="wpacu_manage_dashboard_assets_list" class="wpacu-cssjs-workspace__configuration" style="opacity: <?php echo $dashboardEnabled ? '1' : '0.4'; ?>;">
                        <section id="wpacu-settings-assets-retrieval-mode" class="wpacu-cssjs-config-block" aria-labelledby="wpacuCssJsRetrievalTitle"<?php echo $dashboardEnabled ? '' : ' style="display: none;"'; ?>>
                            <div class="wpacu-cssjs-block-heading">
                                <span class="wpacu-cssjs-block-heading__icon" aria-hidden="true"><span class="dashicons dashicons-download"></span></span>
                                <div>
                                    <h5 id="wpacuCssJsRetrievalTitle"><?php esc_html_e('How should the Dashboard retrieve the asset list?', 'wp-asset-clean-up'); ?></h5>
                                    <p><?php esc_html_e('This changes only how Asset CleanUp inspects the selected front-end URL.', 'wp-asset-clean-up'); ?></p>
                                </div>
                            </div>

                            <fieldset id="wpacu-dom-get-type-selections" class="wpacu-cssjs-choice-group">
                                <legend class="screen-reader-text"><?php esc_html_e('Dashboard asset retrieval method', 'wp-asset-clean-up'); ?></legend>
                                <label class="wpacu-cssjs-choice" for="wpacu-dom-get-type-direct">
                                    <input id="wpacu-dom-get-type-direct"
                                           class="wpacu-dom-get-type-selection"
                                           data-target="wpacu-dom-get-type-direct-info"
                                           type="radio"
                                           name="<?php echo esc_attr($settingsName); ?>[dom_get_type]"
                                           value="direct"
                                        <?php checked($domGetType, 'direct'); ?> />
                                    <span class="wpacu-cssjs-choice__body">
                                        <span class="wpacu-cssjs-choice__top"><strong><?php esc_html_e('Direct browser request', 'wp-asset-clean-up'); ?></strong><span class="wpacu-cssjs-choice__badge"><?php esc_html_e('Default', 'wp-asset-clean-up'); ?></span></span>
                                        <small><?php esc_html_e('Your logged-in browser opens the URL and reports the detected assets. It can inspect private or session-specific output.', 'wp-asset-clean-up'); ?></small>
                                    </span>
                                </label>

                                <label class="wpacu-cssjs-choice" for="wpacu-dom-get-type-wp-remote-post">
                                    <input id="wpacu-dom-get-type-wp-remote-post"
                                           class="wpacu-dom-get-type-selection"
                                           data-target="wpacu-dom-get-type-wp-remote-post-info"
                                           type="radio"
                                           name="<?php echo esc_attr($settingsName); ?>[dom_get_type]"
                                           value="wp_remote_post"
                                        <?php checked($domGetType, 'wp_remote_post'); ?> />
                                    <span class="wpacu-cssjs-choice__body">
                                        <span class="wpacu-cssjs-choice__top"><strong><?php esc_html_e('WordPress HTTP request', 'wp-asset-clean-up'); ?></strong><span class="wpacu-cssjs-choice__badge"><?php esc_html_e('Guest view', 'wp-asset-clean-up'); ?></span></span>
                                        <small><?php esc_html_e('WordPress requests the URL from the server and reads the guest-facing HTML. Try this when the browser request is blocked.', 'wp-asset-clean-up'); ?></small>
                                    </span>
                                </label>
                            </fieldset>

                            <div id="wpacu-dom-get-type-infos" class="wpacu-cssjs-retrieval-notes" aria-live="polite">
                                <div id="wpacu-dom-get-type-direct-info" class="wpacu-dom-get-type-info wpacu-cssjs-callout wpacu-cssjs-callout--info"<?php echo $domGetType === 'direct' ? '' : ' style="display: none;"'; ?>>
                                    <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                                    <p><strong><?php esc_html_e('Direct browser request:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('best first choice when the page requires your login or other browser-session state. Browser protocol rules, a web application firewall, mod_security, or a security plugin can block it.', 'wp-asset-clean-up'); ?></p>
                                </div>
                                <div id="wpacu-dom-get-type-wp-remote-post-info" class="wpacu-dom-get-type-info wpacu-cssjs-callout wpacu-cssjs-callout--info"<?php echo $domGetType === 'wp_remote_post' ? '' : ' style="display: none;"'; ?>>
                                    <span class="dashicons dashicons-cloud" aria-hidden="true"></span>
                                    <p><strong><?php esc_html_e('WordPress HTTP request:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('avoids browser mixed-protocol restrictions. Server loopback restrictions, authentication, firewalls, reverse proxies, or load-balancer rules can prevent it from working.', 'wp-asset-clean-up'); ?></p>
                                </div>
                            </div>

                            <div class="wpacu-cssjs-inline-notes">
                                <span><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e('Private posts always use the Direct method.', 'wp-asset-clean-up'); ?></span>
                                <span><span class="dashicons dashicons-randomize" aria-hidden="true"></span><?php esc_html_e('Redirect destinations are validated and a different internal URL can require confirmation.', 'wp-asset-clean-up'); ?></span>
                            </div>

                            <details class="wpacu-cssjs-details">
                                <summary><?php esc_html_e('If the asset list cannot be retrieved', 'wp-asset-clean-up'); ?></summary>
                                <div><p><?php esc_html_e('Confirm that the selected URL loads normally, then try the other retrieval method. Security rules, server loopback restrictions, authentication, proxies, and load balancers can block one method while the other still works.', 'wp-asset-clean-up'); ?></p></div>
                            </details>
                        </section>

                        <fieldset class="wpacu-cssjs-embedded-manager"<?php echo $dashboardEnabled ? '' : ' style="display: none;"'; ?>>
                            <legend class="screen-reader-text"><?php esc_html_e('Manager on edit screens', 'wp-asset-clean-up'); ?></legend>
                            <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[show_assets_meta_box]" value="0" />

                            <div class="wpacu-cssjs-master-row wpacu-cssjs-master-row--nested">
                                <div class="wpacu-cssjs-master-row__control">
                                    <label class="wpacu_switch" for="wpacu-show-assets-meta-box-checkbox">
                                        <input id="wpacu-show-assets-meta-box-checkbox"
                                               type="checkbox"
                                               name="<?php echo esc_attr($settingsName); ?>[show_assets_meta_box]"
                                               value="1"
                                            <?php checked($showAssetsMetaBox); ?> />
                                        <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                                    </label>
                                    <label class="wpacu-cssjs-control-label" for="wpacu-show-assets-meta-box-checkbox">
                                        <strong><?php esc_html_e('Show the manager on edit screens', 'wp-asset-clean-up'); ?></strong>
                                        <span><?php esc_html_e('Interface only; saved rules remain active.', 'wp-asset-clean-up'); ?></span>
                                    </label>
                                </div>
                                <div class="wpacu-cssjs-master-row__copy">
                                    <span class="wpacu-cssjs-kicker"><?php esc_html_e('Dashboard integration', 'wp-asset-clean-up'); ?></span>
                                    <h5><?php esc_html_e('Manager on eligible content edit screens', 'wp-asset-clean-up'); ?></h5>
                                    <p><?php esc_html_e('Show an Asset CleanUp CSS/JS Manager panel while editing eligible posts, pages, public custom post types, categories, tags, and custom taxonomy terms. Turn this off if you work only from the dedicated CSS/JS Manager page.', 'wp-asset-clean-up'); ?></p>
                                </div>
                            </div>

                            <div id="wpacu-show-assets-enabled-area" class="wpacu-cssjs-embedded-manager__options" style="<?php echo $showAssetsMetaBox ? '' : 'display: none;'; ?>">
                                <div class="wpacu-cssjs-field-group">
                                    <div class="wpacu-cssjs-field-heading">
                                        <strong><?php esc_html_e('Asset list loading', 'wp-asset-clean-up'); ?></strong>
                                        <span><?php esc_html_e('Choose when the embedded panel retrieves the page assets.', 'wp-asset-clean-up'); ?></span>
                                    </div>
                                    <fieldset class="wpacu-cssjs-choice-group wpacu-cssjs-choice-group--compact">
                                        <legend class="screen-reader-text"><?php esc_html_e('Embedded asset list loading behavior', 'wp-asset-clean-up'); ?></legend>
                                        <label class="wpacu-cssjs-choice" for="assets_list_show_status_default">
                                            <input id="assets_list_show_status_default" type="radio" name="<?php echo esc_attr($settingsName); ?>[assets_list_show_status]" value="default" <?php checked($assetsListShowStatus, 'default'); ?> />
                                            <span class="wpacu-cssjs-choice__body"><span class="wpacu-cssjs-choice__top"><strong><?php esc_html_e('Load automatically', 'wp-asset-clean-up'); ?></strong><span class="wpacu-cssjs-choice__badge"><?php esc_html_e('Default', 'wp-asset-clean-up'); ?></span></span><small><?php esc_html_e('Fetch the asset list when the edit screen opens.', 'wp-asset-clean-up'); ?></small></span>
                                        </label>
                                        <label class="wpacu-cssjs-choice" for="assets_list_show_status_fetch_on_click">
                                            <input id="assets_list_show_status_fetch_on_click" type="radio" name="<?php echo esc_attr($settingsName); ?>[assets_list_show_status]" value="fetch_on_click" <?php checked($assetsListShowStatus, 'fetch_on_click'); ?> />
                                            <span class="wpacu-cssjs-choice__body"><span class="wpacu-cssjs-choice__top"><strong><?php esc_html_e('Load on demand', 'wp-asset-clean-up'); ?></strong></span><small><?php esc_html_e('Wait until you click the fetch button. This reduces initial requests and keeps edit screens lighter.', 'wp-asset-clean-up'); ?></small></span>
                                        </label>
                                    </fieldset>
                                </div>

                                <div id="wpacu-settings-hide-meta-boxes" class="wpacu-cssjs-field-group">
                                    <label class="wpacu-cssjs-field-label" for="wpacu-hide-meta-boxes-for-post-types">
                                        <strong><?php esc_html_e('Hide on selected post types', 'wp-asset-clean-up'); ?></strong>
                                        <span><?php esc_html_e('Hide only the embedded panel. These post types remain available in the dedicated CSS/JS Manager.', 'wp-asset-clean-up'); ?></span>
                                    </label>
                                    <select id="wpacu-hide-meta-boxes-for-post-types"
                                        <?php if ($useEnhancedInputs) { ?>
                                            class="wpacu_chosen_select"
                                            data-placeholder="<?php esc_attr_e('Choose post types', 'wp-asset-clean-up'); ?>"
                                        <?php } ?>
                                            multiple="multiple"
                                            name="<?php echo esc_attr($settingsName); ?>[hide_meta_boxes_for_post_types][]">
                                        <?php foreach ($postTypesList as $postTypeKey => $postTypeValue) {
                                            $postTypeObject = get_post_type_object($postTypeKey);
                                            $postTypeLabel  = isset($postTypeObject->labels->singular_name) ? $postTypeObject->labels->singular_name : $postTypeValue;
                                            $postTypeOption = sprintf('%1$s (%2$s)', $postTypeLabel, $postTypeKey);
                                            ?>
                                            <option value="<?php echo esc_attr($postTypeKey); ?>" <?php selected(in_array($postTypeKey, $hideMetaBoxesForPostTypes, true)); ?>><?php echo esc_html($postTypeOption); ?></option>
                                        <?php } ?>
                                    </select>
                                    <p id="wpacu-hide-meta-boxes-for-post-types-info" class="wpacu-cssjs-field-help"><?php esc_html_e('No selection keeps the embedded manager available for every supported public post type.', 'wp-asset-clean-up'); ?></p>
                                </div>
                            </div>

                            <div id="wpacu-show-assets-disabled-area" class="wpacu-cssjs-empty-state" style="<?php echo $showAssetsMetaBox ? 'display: none;' : ''; ?>">
                                <span class="dashicons dashicons-hidden" aria-hidden="true"></span>
                                <p><strong><?php esc_html_e('The edit-screen manager is hidden.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Enable it above to configure loading and post-type exclusions. The dedicated Dashboard manager remains available.', 'wp-asset-clean-up'); ?></p>
                            </div>
                        </fieldset>
                    </div>
                </article>

                <article class="wpacu-cssjs-workspace" aria-labelledby="wpacuCssJsFrontendTitle">
                    <div class="wpacu-cssjs-master-row">
                        <div class="wpacu-cssjs-master-row__control">
                            <label class="wpacu_switch" for="wpacu_frontend">
                                <input id="wpacu_frontend"
                                       data-target-opacity="#wpacu_frontend_manage_assets_list"
                                       type="checkbox"
                                       name="<?php echo esc_attr($settingsName); ?>[frontend_show]"
                                       value="1"
                                    <?php checked($frontendEnabled); ?> />
                                <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                            </label>
                            <label class="wpacu-cssjs-control-label" for="wpacu_frontend">
                                <strong><?php esc_html_e('Enable front-end management', 'wp-asset-clean-up'); ?></strong>
                                <span><?php esc_html_e('Useful for inspecting a page in place.', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>
                        <div class="wpacu-cssjs-master-row__copy">
                            <span class="wpacu-cssjs-kicker"><?php esc_html_e('Optional workspace', 'wp-asset-clean-up'); ?></span>
                            <h4 id="wpacuCssJsFrontendTitle"><?php esc_html_e('Front-end CSS/JS Manager', 'wp-asset-clean-up'); ?></h4>
                            <p><?php esc_html_e('Append the manager below the current public page for authorized logged-in users. Regular visitors never see it.', 'wp-asset-clean-up'); ?></p>
                        </div>
                    </div>

                    <aside class="wpacu-cssjs-callout wpacu-cssjs-callout--success">
                        <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                        <p><strong><?php esc_html_e('Not required for special page types.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Search results, author archives, date archives, and 404 pages can be managed from the Dashboard CSS/JS Manager.', 'wp-asset-clean-up'); ?></p>
                    </aside>

                    <div id="wpacu_frontend_manage_assets_list" class="wpacu-cssjs-workspace__configuration" style="opacity: <?php echo $frontendEnabled ? '1' : '0.4'; ?>;">
                        <details class="wpacu-cssjs-details wpacu-cssjs-details--standalone">
                            <summary><?php esc_html_e('If the front-end panel does not appear', 'wp-asset-clean-up'); ?></summary>
                            <div>
                                <p><?php esc_html_e('The panel is inserted through wp_footer. Confirm that the active theme calls wp_footer() before the closing body tag. Security rules and full-page caching can also affect logged-in output.', 'wp-asset-clean-up'); ?></p>
                                <a href="<?php echo esc_url('https://developer.wordpress.org/reference/functions/wp_footer/'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View the WordPress wp_footer() reference', 'wp-asset-clean-up'); ?></a>
                            </div>
                        </details>

                        <section id="wpacu-settings-frontend-exceptions" class="wpacu-cssjs-config-block wpacu-cssjs-config-block--exceptions" aria-labelledby="wpacuCssJsFrontendExceptionsTitle"<?php echo $frontendEnabled ? '' : ' style="display: none;"'; ?>>
                            <div class="wpacu-cssjs-block-heading">
                                <span class="wpacu-cssjs-block-heading__icon" aria-hidden="true"><span class="dashicons dashicons-filter"></span></span>
                                <div>
                                    <h5 id="wpacuCssJsFrontendExceptionsTitle"><?php esc_html_e('Hide the front-end manager when the URI contains', 'wp-asset-clean-up'); ?></h5>
                                    <p><?php esc_html_e('Enter one non-empty, case-sensitive substring per line. On a matching page, saved optimizations still run; only the management panel is hidden.', 'wp-asset-clean-up'); ?></p>
                                </div>
                            </div>

                            <label class="wpacu-cssjs-field-label" for="wpacu_frontend_show_exceptions">
                                <strong><?php esc_html_e('URI substrings', 'wp-asset-clean-up'); ?></strong>
                                <span><?php esc_html_e('The defaults cover common builder and preview URLs, including Divi, Oxygen, WPBakery, and preview links.', 'wp-asset-clean-up'); ?></span>
                            </label>
                            <textarea id="wpacu_frontend_show_exceptions" name="<?php echo esc_attr($settingsName); ?>[frontend_show_exceptions]" rows="6" placeholder="Example:&#10;et_fb=1&#10;preview_nonce="><?php echo esc_textarea($frontendShowExceptions); ?></textarea>
                            <p class="wpacu-cssjs-example"><strong><?php esc_html_e('Example:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('For /sample-page/?et_fb=1, enter et_fb=1.', 'wp-asset-clean-up'); ?></p>
                        </section>
                    </div>
                </article>
            </section>

            <?php if ($canConfigureAdminAccess) {
                $currentUserId = get_current_user_id();
                $allEligibleUsers = SettingsAdminOnlyForAdmin::getAllUsersWithPluginAccess();
                $allEligibleRoles = SettingsAdminOnlyForAdmin::getAllRolesWithPluginAccess();
                ?>
                <section id="wpacu-cssjs-access" class="wpacu-cssjs-section" aria-labelledby="wpacuCssJsAccessTitle">
                    <div class="wpacu-cssjs-section__heading">
                        <span class="wpacu-cssjs-section__number" aria-hidden="true">2</span>
                        <div>
                            <h3 id="wpacuCssJsAccessTitle"><?php esc_html_e('Manager visibility', 'wp-asset-clean-up'); ?></h3>
                            <p><?php esc_html_e('Keep the CSS/JS Manager out of the workflow of administrators who only edit content and do not work on performance optimization.', 'wp-asset-clean-up'); ?></p>
                        </div>
                    </div>

                    <article class="wpacu-cssjs-setting-card wpacu-cssjs-access-card">
                        <div class="wpacu-cssjs-card-heading">
                            <span class="wpacu-cssjs-card-heading__icon" aria-hidden="true"><span class="dashicons dashicons-visibility"></span></span>
                            <div><span class="wpacu-cssjs-kicker"><?php esc_html_e('Optimization workflow', 'wp-asset-clean-up'); ?></span><h4><?php esc_html_e('Who should see and use the CSS/JS Manager?', 'wp-asset-clean-up'); ?></h4><p><?php esc_html_e('This preference applies to the manager in wp-admin, edit screens, and the front-end view.', 'wp-asset-clean-up'); ?></p></div>
                        </div>

                        <div class="wpacu-cssjs-access-fields">
                            <input id="wpacu-allow-manage-assets-to-select" type="hidden" name="<?php echo esc_attr($settingsName); ?>[allow_manage_assets_to]" value="<?php echo esc_attr($allowManageAssetsTo); ?>" />
                            <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[allow_manage_assets_via_roles]" value="0" />
                            <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[allow_manage_assets_via_users]" value="0" />

                            <section class="wpacu-cssjs-visibility-panel">
                                <label class="wpacu-cssjs-visibility-checkbox" for="wpacu-allow-manage-assets-via-roles">
                                    <input id="wpacu-allow-manage-assets-via-roles" type="checkbox" name="<?php echo esc_attr($settingsName); ?>[allow_manage_assets_via_roles]" value="1" <?php checked($rolesVisibilityEnabled); ?> />
                                    <span><strong><?php esc_html_e('Selected roles', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Include everyone with one of the roles selected below.', 'wp-asset-clean-up'); ?></small></span>
                                </label>
                                <div id="wpacu-allow-manage-assets-to-roles-area" class="wpacu-cssjs-visibility-options<?php echo $rolesVisibilityEnabled ? '' : ' wpacu_hide'; ?>">
                                    <label for="wpacu-allow-manage-assets-to-roles"><?php esc_html_e('Roles responsible for optimization', 'wp-asset-clean-up'); ?></label>
                                    <select id="wpacu-allow-manage-assets-to-roles" name="<?php echo esc_attr($settingsName); ?>[allow_manage_assets_to_roles][]" multiple="multiple"<?php if ($useEnhancedInputs) { ?> class="wpacu_chosen_select" data-placeholder="<?php esc_attr_e('Choose roles', 'wp-asset-clean-up'); ?>"<?php } ?>>
                                        <?php foreach ($allEligibleRoles as $roleSlug => $roleName) { ?>
                                            <option value="<?php echo esc_attr($roleSlug); ?>" <?php selected(in_array($roleSlug, $allowManageAssetsToRoles, true)); ?>><?php echo esc_html($roleName); ?></option>
                                        <?php } ?>
                                    </select>
                                    <p class="wpacu-cssjs-field-help"><?php esc_html_e('Only roles already granted Asset CleanUp access are listed. This selection controls manager visibility and does not grant new plugin access.', 'wp-asset-clean-up'); ?></p>
                                </div>
                            </section>

                            <section class="wpacu-cssjs-visibility-panel">
                                <label class="wpacu-cssjs-visibility-checkbox" for="wpacu-allow-manage-assets-via-users">
                                    <input id="wpacu-allow-manage-assets-via-users" type="checkbox" name="<?php echo esc_attr($settingsName); ?>[allow_manage_assets_via_users]" value="1" <?php checked($usersVisibilityEnabled); ?> />
                                    <span><strong><?php esc_html_e('Selected users', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Add individual accounts not already covered by a selected role.', 'wp-asset-clean-up'); ?></small></span>
                                </label>
                                <div id="wpacu-allow-manage-assets-to-select-list-area" class="wpacu-cssjs-visibility-options wpacu-cssjs-field--admins<?php echo $usersVisibilityEnabled ? '' : ' wpacu_hide'; ?>">
                                    <label for="wpacu-allow-manage-assets-to-select-list"><?php esc_html_e('Users responsible for optimization', 'wp-asset-clean-up'); ?></label>
                                <select id="wpacu-allow-manage-assets-to-select-list"
                                        name="<?php echo esc_attr($settingsName); ?>[allow_manage_assets_to_list][]"
                                    <?php if ($useEnhancedInputs) { ?>
                                        class="wpacu_chosen_can_be_later_enabled"
                                        data-placeholder="<?php esc_attr_e('Choose users', 'wp-asset-clean-up'); ?>"
                                    <?php } ?>
                                        multiple="multiple">
                                    <?php foreach ($allEligibleUsers as $user) {
                                        $userLabel = sprintf('%1$s (%2$s)', $user->display_name, $user->user_email);
                                        if ($currentUserId === (int) $user->ID) {
                                            $userLabel .= ' — ' . __('You', 'wp-asset-clean-up');
                                        }
                                        $userRoleLabels = array();
                                        foreach ((array)$user->roles as $userRoleSlug) {
                                            if (isset(wp_roles()->roles[$userRoleSlug]['name'])) {
                                                $userRoleLabels[$userRoleSlug] = translate_user_role(wp_roles()->roles[$userRoleSlug]['name']);
                                            }
                                        }
                                        $userRolesLabel = implode('/', array_values($userRoleLabels));
                                        $userLabelWithRoles = $userLabel . ($userRolesLabel !== '' ? ' — ' . $userRolesLabel : '');
                                        ?>
                                        <option value="<?php echo esc_attr($user->ID); ?>"
                                                data-wpacu-user-roles="<?php echo esc_attr(implode(' ', (array)$user->roles)); ?>"
                                                data-wpacu-user-base-label="<?php echo esc_attr($userLabelWithRoles); ?>"
                                            <?php selected(in_array((int) $user->ID, $allowManageAssetsToList, true)); ?>><?php echo esc_html($userLabelWithRoles); ?></option>
                                    <?php } ?>
                                </select>
                                <p class="wpacu-cssjs-field-help"><?php esc_html_e('Users already covered by a selected role are omitted from this list because they already have visibility. If no role or user is selected, visibility falls back to everyone with Asset CleanUp access.', 'wp-asset-clean-up'); ?></p>
                                <p id="wpacu-all-roles-selected-notice" class="wpacu-cssjs-field-help wpacu_hide"><strong><?php esc_html_e('All eligible users are covered by the selected roles.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Individual user selection is not needed.', 'wp-asset-clean-up'); ?></p>
                                </div>
                            </section>

                            <p class="wpacu-cssjs-field-help wpacu-cssjs-visibility-summary"><?php esc_html_e('Leave both options unchecked to show the manager to everyone with Asset CleanUp access.', 'wp-asset-clean-up'); ?></p>
                        </div>

                        <aside class="wpacu-cssjs-callout wpacu-cssjs-callout--neutral">
                            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                            <p><strong><?php esc_html_e('Workflow preference.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Use this setting to reduce interface clutter and help prevent accidental CSS/JS changes. It is not a separate security boundary: administrators who can open Asset CleanUp Settings can change this preference. Use the Access Control sub-tab to grant plugin access to non-administrators.', 'wp-asset-clean-up'); ?></p>
                        </aside>
                    </article>
                </section>
                <script>
                    jQuery(function ($) {
                        var $policy = $('#wpacu-allow-manage-assets-to-select');
                        var $roles = $('#wpacu-allow-manage-assets-to-roles');
                        var $users = $('#wpacu-allow-manage-assets-to-select-list');
                        var $rolesCheckbox = $('#wpacu-allow-manage-assets-via-roles');
                        var $usersCheckbox = $('#wpacu-allow-manage-assets-via-users');
                        var $usersCheckboxLabel = $usersCheckbox.closest('.wpacu-cssjs-visibility-checkbox');
                        var $usersArea = $('#wpacu-allow-manage-assets-to-select-list-area');
                        var $allRolesNotice = $('#wpacu-all-roles-selected-notice');

                        function updateSelectedVisibility() {
                            var rolesEnabled = $rolesCheckbox.is(':checked');
                            var usersEnabled = $usersCheckbox.is(':checked');
                            var selectedRoles = $roles.val() || [];
                            var $userOptions = $users.find('option');
                            var allRolesSelected = rolesEnabled && $userOptions.length > 0 && $userOptions.toArray().every(function (option) {
                                var userRoles = String($(option).attr('data-wpacu-user-roles') || '').split(/\s+/);

                                return selectedRoles.some(function (role) {
                                    return userRoles.indexOf(role) !== -1;
                                });
                            });

                            if (allRolesSelected) {
                                $usersCheckbox.prop('checked', false);
                                usersEnabled = false;
                            }

                            $usersCheckboxLabel.toggleClass('is-visually-disabled', allRolesSelected).attr('aria-disabled', allRolesSelected ? 'true' : 'false');
                            $allRolesNotice.toggleClass('wpacu_hide', ! allRolesSelected);
                            $('#wpacu-allow-manage-assets-to-roles-area').toggleClass('wpacu_hide', ! rolesEnabled);
                            $usersArea.toggleClass('wpacu_hide', ! usersEnabled && ! allRolesSelected).toggleClass('is-visually-disabled', allRolesSelected);

                            if (usersEnabled && $users.hasClass('wpacu_chosen_can_be_later_enabled') && ! $users.next().hasClass('chosen-container')) {
                                window.wpacuInitChosen($users);
                            }

                            if (rolesEnabled && usersEnabled) {
                                $policy.val('selected');
                            } else if (rolesEnabled) {
                                $policy.val('selected_roles');
                            } else if (usersEnabled) {
                                $policy.val('chosen');
                            } else {
                                $policy.val('any_admin');
                            }

                            $users.find('option').each(function () {
                                var $option = $(this);
                                var userRoles = String($option.attr('data-wpacu-user-roles') || '').split(/\s+/);
                                var coveredByRole = rolesEnabled && selectedRoles.some(function (role) {
                                    return userRoles.indexOf(role) !== -1;
                                });
                                var baseLabel = String($option.attr('data-wpacu-user-base-label') || $option.text());

                                if (coveredByRole) {
                                    $option.prop('selected', false);
                                }

                                $option.text(baseLabel + (coveredByRole ? ' · bulk' : ''));
                                $option.prop('disabled', coveredByRole || allRolesSelected).prop('hidden', false);
                            });

                            $users.trigger('chosen:updated');
                        }

                        $rolesCheckbox.on('change.wpacuSelectedVisibility', updateSelectedVisibility);
                        $usersCheckbox.on('click.wpacuSelectedVisibility', function (event) {
                            if ($usersCheckboxLabel.hasClass('is-visually-disabled')) {
                                event.preventDefault();
                            }
                        });
                        $usersCheckbox.on('change.wpacuSelectedVisibility', updateSelectedVisibility);
                        $roles.on('change.wpacuSelectedVisibility', updateSelectedVisibility);
                        updateSelectedVisibility();
                    });
                </script>
            <?php } ?>

            <section id="wpacu-cssjs-list-appearance" class="wpacu-cssjs-section" aria-labelledby="wpacuCssJsAppearanceTitle">
                <div class="wpacu-cssjs-section__heading">
                    <span class="wpacu-cssjs-section__number" aria-hidden="true"><?php echo $canConfigureAdminAccess ? '3' : '2'; ?></span>
                    <div><h3 id="wpacuCssJsAppearanceTitle"><?php esc_html_e('List appearance', 'wp-asset-clean-up'); ?></h3><p><?php esc_html_e('Make large asset lists easier to scan without changing any rules.', 'wp-asset-clean-up'); ?></p></div>
                </div>

                <article class="wpacu-cssjs-setting-card wpacu-cssjs-layout-card">
                    <div class="wpacu-cssjs-setting-row wpacu-cssjs-setting-row--layout">
                        <div class="wpacu-cssjs-setting-row__copy"><span class="wpacu-cssjs-card-heading__icon" aria-hidden="true"><span class="dashicons dashicons-screenoptions"></span></span><div><h4><?php esc_html_e('Group and sort assets', 'wp-asset-clean-up'); ?></h4><p><?php esc_html_e('Choose how CSS and JavaScript handles are arranged whenever the manager opens.', 'wp-asset-clean-up'); ?></p></div></div>
                        <div class="wpacu-cssjs-field"><label for="wpacu_assets_list_layout"><?php esc_html_e('Grouping strategy', 'wp-asset-clean-up'); ?></label><?php echo SettingsAdmin::generateAssetsListLayoutDropDown($assetsListLayout, $settingsName . '[assets_list_layout]'); ?></div>
                    </div>

                    <div id="wpacu-assets-list-by-location-selected" class="wpacu-cssjs-setting-row wpacu-cssjs-setting-row--conditional" style="<?php echo $assetsListLayout === 'by-location' ? '' : 'display: none;'; ?>">
                        <div class="wpacu-cssjs-setting-row__copy"><span class="wpacu-cssjs-card-heading__icon" aria-hidden="true"><span class="dashicons dashicons-admin-plugins"></span></span><div><h4><?php esc_html_e('Plugin sections', 'wp-asset-clean-up'); ?></h4><p><?php esc_html_e('When assets are grouped by location, choose whether each plugin section starts open.', 'wp-asset-clean-up'); ?></p></div></div>
                        <fieldset class="assets_list_layout_areas_status_choices wpacu-cssjs-segmented-options"><legend class="screen-reader-text"><?php esc_html_e('Plugin sections on initial load', 'wp-asset-clean-up'); ?></legend>
                            <label for="assets_list_layout_plugin_area_status_expanded"><input id="assets_list_layout_plugin_area_status_expanded" type="radio" name="<?php echo esc_attr($settingsName); ?>[assets_list_layout_plugin_area_status]" value="expanded" <?php checked($pluginAreaStatus, 'expanded'); ?> /><span><?php esc_html_e('Expanded', 'wp-asset-clean-up'); ?></span></label>
                            <label for="assets_list_layout_plugin_area_status_contracted"><input id="assets_list_layout_plugin_area_status_contracted" type="radio" name="<?php echo esc_attr($settingsName); ?>[assets_list_layout_plugin_area_status]" value="contracted" <?php checked($pluginAreaStatus, 'contracted'); ?> /><span><?php esc_html_e('Collapsed', 'wp-asset-clean-up'); ?></span></label>
                        </fieldset>
                    </div>

                    <div class="wpacu-cssjs-setting-row">
                        <div class="wpacu-cssjs-setting-row__copy"><span class="wpacu-cssjs-card-heading__icon" aria-hidden="true"><span class="dashicons dashicons-editor-expand"></span></span><div><h4><?php esc_html_e('Top-level groups', 'wp-asset-clean-up'); ?></h4><p><?php esc_html_e('Choose whether the main sections in the selected layout start open or collapsed. Individual asset rows keep their own state.', 'wp-asset-clean-up'); ?></p></div></div>
                        <fieldset class="assets_list_layout_areas_status_choices wpacu-cssjs-segmented-options"><legend class="screen-reader-text"><?php esc_html_e('Top-level groups on initial load', 'wp-asset-clean-up'); ?></legend>
                            <label for="assets_list_layout_areas_status_expanded"><input id="assets_list_layout_areas_status_expanded" type="radio" name="<?php echo esc_attr($settingsName); ?>[assets_list_layout_areas_status]" value="expanded" <?php checked($groupsStatus, 'expanded'); ?> /><span><?php esc_html_e('Expanded', 'wp-asset-clean-up'); ?></span></label>
                            <label for="assets_list_layout_areas_status_contracted"><input id="assets_list_layout_areas_status_contracted" type="radio" name="<?php echo esc_attr($settingsName); ?>[assets_list_layout_areas_status]" value="contracted" <?php checked($groupsStatus, 'contracted'); ?> /><span><?php esc_html_e('Collapsed', 'wp-asset-clean-up'); ?></span></label>
                        </fieldset>
                    </div>

                    <div class="wpacu-cssjs-setting-row">
                        <div class="wpacu-cssjs-setting-row__copy"><span class="wpacu-cssjs-card-heading__icon" aria-hidden="true"><span class="dashicons dashicons-editor-code"></span></span><div><h4><?php esc_html_e('Inline code details', 'wp-asset-clean-up'); ?></h4><p><?php esc_html_e('Some handles include attached inline CSS or JavaScript. Collapsing this content keeps rows shorter; it remains available from the row toggle.', 'wp-asset-clean-up'); ?></p></div></div>
                        <fieldset class="assets_list_inline_code_status_choices wpacu-cssjs-segmented-options"><legend class="screen-reader-text"><?php esc_html_e('Inline code details on initial load', 'wp-asset-clean-up'); ?></legend>
                            <label for="assets_list_inline_code_status_contracted"><input id="assets_list_inline_code_status_contracted" type="radio" name="<?php echo esc_attr($settingsName); ?>[assets_list_inline_code_status]" value="contracted" <?php checked($inlineCodeStatus, 'contracted'); ?> /><span><?php esc_html_e('Collapsed', 'wp-asset-clean-up'); ?><small><?php esc_html_e('Default', 'wp-asset-clean-up'); ?></small></span></label>
                            <label for="assets_list_inline_code_status_expanded"><input id="assets_list_inline_code_status_expanded" type="radio" name="<?php echo esc_attr($settingsName); ?>[assets_list_inline_code_status]" value="expanded" <?php checked($inlineCodeStatus, 'expanded'); ?> /><span><?php esc_html_e('Expanded', 'wp-asset-clean-up'); ?></span></label>
                        </fieldset>
                    </div>

                    <div class="wpacu-cssjs-setting-row wpacu-cssjs-setting-row--core-assets">
                        <div class="wpacu-cssjs-setting-row__copy"><span class="wpacu-cssjs-card-heading__icon" aria-hidden="true"><span class="dashicons dashicons-wordpress-alt"></span></span><div><h4><?php esc_html_e('WordPress core assets', 'wp-asset-clean-up'); ?></h4><p><?php esc_html_e('Hide core handles such as jquery, wp-embed, comment-reply, and dashicons to reduce list clutter. Asset loading and saved rules remain unchanged.', 'wp-asset-clean-up'); ?></p></div></div>
                        <fieldset class="wpacu-cssjs-segmented-options"><legend class="screen-reader-text"><?php esc_html_e('WordPress core assets visibility', 'wp-asset-clean-up'); ?></legend>
                            <label for="wpacu_core_files_visible"><input id="wpacu_core_files_visible" type="radio" name="<?php echo esc_attr($settingsName); ?>[hide_core_files]" value="0" <?php checked( ! $hideCoreFiles); ?> /><span><?php esc_html_e('Visible', 'wp-asset-clean-up'); ?></span></label>
                            <label class="wpacu-cssjs-segmented-option--restrictive" for="wpacu_core_files_hidden"><input id="wpacu_core_files_hidden" type="radio" name="<?php echo esc_attr($settingsName); ?>[hide_core_files]" value="1" <?php checked($hideCoreFiles); ?> /><span><?php esc_html_e('Hidden', 'wp-asset-clean-up'); ?><small><?php esc_html_e('Default', 'wp-asset-clean-up'); ?></small></span></label>
                        </fieldset>
                    </div>
                </article>
            </section>

            <section id="wpacu-cssjs-optimized-cache" class="wpacu-cssjs-section" aria-labelledby="wpacuCssJsCacheTitle">
                <div class="wpacu-cssjs-section__heading">
                    <span class="wpacu-cssjs-section__number" aria-hidden="true"><?php echo $canConfigureAdminAccess ? '4' : '3'; ?></span>
                    <div><h3 id="wpacuCssJsCacheTitle"><?php esc_html_e('Optimized-file cache', 'wp-asset-clean-up'); ?></h3><p><?php esc_html_e('Control the lookup storage used by optimized CSS/JS and how long old generated files are retained.', 'wp-asset-clean-up'); ?></p></div>
                </div>

                <article class="wpacu-cssjs-setting-card wpacu-cssjs-cache-card">
                    <div class="wpacu-cssjs-card-heading">
                        <span class="wpacu-cssjs-card-heading__icon" aria-hidden="true"><span class="dashicons dashicons-database"></span></span>
                        <div><span class="wpacu-cssjs-kicker"><?php esc_html_e('Generated assets', 'wp-asset-clean-up'); ?></span><h4><?php esc_html_e('Lookup storage and file retention', 'wp-asset-clean-up'); ?></h4><p><?php esc_html_e('These settings matter when Asset CleanUp creates an altered file—for example after minification, combination, or a content rewrite. They do not configure your page-cache plugin.', 'wp-asset-clean-up'); ?></p></div>
                    </div>

                    <div class="wpacu-cssjs-cache-fields">
                        <div class="wpacu-cssjs-field">
                            <label for="wpacu_fetch_cached_files_details_from"><?php esc_html_e('Cache lookup storage', 'wp-asset-clean-up'); ?></label>
                            <select id="wpacu_fetch_cached_files_details_from" name="<?php echo esc_attr($settingsName); ?>[fetch_cached_files_details_from]">
                                <option value="disk" <?php selected($fetchCachedFilesDetailsFrom, 'disk'); ?>><?php esc_html_e('Disk — recommended', 'wp-asset-clean-up'); ?></option>
                                <option value="db" <?php selected($fetchCachedFilesDetailsFrom, 'db'); ?>><?php esc_html_e('WordPress transients', 'wp-asset-clean-up'); ?></option>
                                <option value="db_disk" <?php selected($fetchCachedFilesDetailsFrom, 'db_disk'); ?>><?php esc_html_e('Disk and WordPress transients', 'wp-asset-clean-up'); ?></option>
                            </select>
                            <p class="wpacu-cssjs-field-help"><?php esc_html_e('This controls the mapping between an original asset and its optimized cached file. The optimized CSS/JS files themselves remain on disk.', 'wp-asset-clean-up'); ?></p>
                        </div>

                        <details class="wpacu-cssjs-details wpacu-cssjs-details--storage">
                            <summary><?php esc_html_e('Compare the three lookup modes', 'wp-asset-clean-up'); ?></summary>
                            <div class="wpacu-cssjs-storage-grid">
                                <div><strong><?php esc_html_e('Disk', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Stores lookup records as small files and avoids adding transient records to the database. Best for most sites.', 'wp-asset-clean-up'); ?></span></div>
                                <div><strong><?php esc_html_e('WordPress transients', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Stores lookup records through the Transients API, which may use the database or a persistent object cache.', 'wp-asset-clean-up'); ?></span></div>
                                <div><strong><?php esc_html_e('Disk and transients', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Writes lookup records to both locations and alternates reads approximately 50/50. Use it only when deliberately balancing both resources.', 'wp-asset-clean-up'); ?></span></div>
                            </div>
                        </details>

                    </div>

                    <div class="wpacu-cssjs-cache-summary-fields">
                        <div class="wpacu-cssjs-field wpacu-cssjs-field--retention">
                            <label for="wpacu_clear_cached_files_after"><?php esc_html_e('Retain generated files for', 'wp-asset-clean-up'); ?></label>
                            <div class="wpacu-cssjs-number-field"><input id="wpacu_clear_cached_files_after" type="number" min="1" name="<?php echo esc_attr($settingsName); ?>[clear_cached_files_after]" value="<?php echo esc_attr($clearCachedFilesAfter); ?>" /><span><?php esc_html_e('days', 'wp-asset-clean-up'); ?></span></div>
                            <p class="wpacu-cssjs-field-help"><?php esc_html_e('When cache cleanup runs, generated CSS/JS files older than this value are removed and can be rebuilt if needed. Keep this period longer than the longest page or CDN cache lifetime.', 'wp-asset-clean-up'); ?></p>
                        </div>

                        <div class="wpacu-cssjs-cache-path"><span class="dashicons dashicons-open-folder" aria-hidden="true"></span><span><strong><?php esc_html_e('Generated files directory', 'wp-asset-clean-up'); ?></strong><code><?php echo esc_html(OptimizeCommon::getRelPathPluginCacheDir()); ?></code></span></div>
                    </div>
                    <p class="wpacu-cssjs-doc-links"><a href="<?php echo esc_url('https://www.assetcleanup.com/docs/how-css-js-are-created-within-the-caching-directory/'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('How optimized-file caching works', 'wp-asset-clean-up'); ?></a><span aria-hidden="true">·</span><a href="<?php echo esc_url('https://www.assetcleanup.com/docs/clearing-css-js-files-caching/'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Cache cleanup documentation', 'wp-asset-clean-up'); ?></a></p>
                </article>
            </section>
            </div>

            <div class="wpacu-cssjs-critical-css-wrap">
            <section class="wpacu-cssjs-scope-heading wpacu-cssjs-scope-heading--critical" aria-labelledby="wpacuCriticalCssPreferencesTitle">
                <span class="wpacu-cssjs-scope-heading__icon" aria-hidden="true"><span class="dashicons dashicons-admin-appearance"></span></span>
                <div>
                    <span class="wpacu-cssjs-scope-heading__eyebrow"><?php esc_html_e('Manage Critical CSS', 'wp-asset-clean-up'); ?></span>
                    <h3 id="wpacuCriticalCssPreferencesTitle"><?php esc_html_e('Critical CSS preferences', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Control whether saved Critical CSS rules are delivered. Create and edit the rules from the dedicated Critical CSS manager.', 'wp-asset-clean-up'); ?></p>
                </div>
            </section>

            <section id="wpacu-cssjs-critical-css" class="wpacu-cssjs-section wpacu-cssjs-section--scope-child" aria-labelledby="wpacuCssJsCriticalCssTitle">
                <div class="wpacu-cssjs-section__heading wpacu-cssjs-section__heading--unnumbered">
                    <div>
                        <h3 id="wpacuCssJsCriticalCssTitle"><?php esc_html_e('Rule delivery', 'wp-asset-clean-up'); ?></h3>
                        <p><?php esc_html_e('Pause all Critical CSS output without deleting or changing saved rules.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </div>

                <article class="wpacu-cssjs-setting-card wpacu-cssjs-critical-css-card <?php echo $criticalCssEnabled ? 'is-active' : 'is-paused'; ?>">
                    <div class="wpacu-cssjs-master-row wpacu-cssjs-critical-css-master-row">
                        <div class="wpacu-cssjs-master-row__control">
                            <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[critical_css_status]" value="off" />
                            <label class="wpacu_switch" for="wpacu_plugin_usage_critical_css_status">
                                <input id="wpacu_plugin_usage_critical_css_status"
                                       type="checkbox"
                                       name="<?php echo esc_attr($settingsName); ?>[critical_css_status]"
                                       value="on"
                                    <?php checked($criticalCssEnabled); ?> />
                                <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                            </label>
                            <label class="wpacu-cssjs-control-label" for="wpacu_plugin_usage_critical_css_status">
                                <strong><?php esc_html_e('Apply Critical CSS rules', 'wp-asset-clean-up'); ?></strong>
                                <span><?php esc_html_e('Save after changing this switch.', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>

                        <div class="wpacu-cssjs-master-row__copy">
                            <span class="wpacu-cssjs-kicker"><?php esc_html_e('Main setting', 'wp-asset-clean-up'); ?></span>
                            <h4><?php esc_html_e('Keep saved Critical CSS rules enabled', 'wp-asset-clean-up'); ?></h4>
                            <p><?php esc_html_e('Turn this off temporarily while checking first-render, layout, or styling problems. The same switch appears above the rules in Manage Critical CSS.', 'wp-asset-clean-up'); ?></p>

                            <div class="wpacu-cssjs-critical-css-meta">
                                <span id="wpacuPluginUsageCriticalCssStatus"
                                      class="wpacu-cssjs-critical-css-status <?php echo $criticalCssEnabled ? 'is-active' : 'is-paused'; ?>"
                                      aria-live="polite"
                                      data-active-label="<?php esc_attr_e('Active', 'wp-asset-clean-up'); ?>"
                                      data-paused-label="<?php esc_attr_e('Paused', 'wp-asset-clean-up'); ?>">
                                    <?php echo $criticalCssEnabled ? esc_html__('Active', 'wp-asset-clean-up') : esc_html__('Paused', 'wp-asset-clean-up'); ?>
                                </span>
                                <span class="wpacu-cssjs-critical-css-count"><?php echo esc_html($criticalCssRulesSummary); ?></span>
                                <span class="wpacu-cssjs-critical-css-saved"><span class="dashicons dashicons-saved" aria-hidden="true"></span><?php esc_html_e('Rules stay saved', 'wp-asset-clean-up'); ?></span>
                                <a class="wpacu-new-style-external-link" href="<?php echo esc_url($manageCriticalCssUrl); ?>" target="_blank" rel="noopener noreferrer">
                                    <span class="wpacu-new-style-external-link__text"><?php echo $criticalCssSavedRules > 0 ? esc_html__('Manage Critical CSS rules', 'wp-asset-clean-up') : esc_html__('Set up Critical CSS', 'wp-asset-clean-up'); ?></span>
                                    <span class="dashicons dashicons-external" aria-hidden="true"></span>
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
            </div>

            <aside class="wpacu-cssjs-footer-note">
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <p><strong><?php esc_html_e('These preferences do not delete saved asset rules.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Save the page, then open the CSS/JS Manager to confirm that the selected workspace and list appearance match the way you intend to work.', 'wp-asset-clean-up'); ?></p>
            </aside>
        </div>
    </section>

    <script>
    (function () {
        'use strict';

        function initCriticalCssPreferenceStatus() {
            var card = document.querySelector('#wpacu-cssjs-critical-css .wpacu-cssjs-critical-css-card');
            var input = document.getElementById('wpacu_plugin_usage_critical_css_status');
            var status = document.getElementById('wpacuPluginUsageCriticalCssStatus');

            if (! card || ! input || ! status || card.getAttribute('data-status-sync-initialized') === '1') {
                return;
            }

            card.setAttribute('data-status-sync-initialized', '1');

            function syncStatus() {
                var isActive = input.checked;

                card.classList.toggle('is-active', isActive);
                card.classList.toggle('is-paused', ! isActive);
                status.classList.toggle('is-active', isActive);
                status.classList.toggle('is-paused', ! isActive);
                status.textContent = isActive
                    ? status.getAttribute('data-active-label')
                    : status.getAttribute('data-paused-label');
            }

            input.addEventListener('change', syncStatus);
            syncStatus();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCriticalCssPreferenceStatus);
        } else {
            initCriticalCssPreferenceStatus();
        }
    }());
    </script>
</main>
