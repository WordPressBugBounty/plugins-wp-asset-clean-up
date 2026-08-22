<?php
/*
 * No direct access to this file
 */
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;
use WpAssetCleanUp\OptimiseAssets\OptimizeCss;

if (! isset($data)) {
    exit;
}

global $wp_version;

$tabIdArea       = 'wpacu-setting-optimize-css';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea) ? 'style="display: table-cell;"' : '';
$settingsInputName = WPACU_PLUGIN_ID . '_settings';

$cssOptimizationOtherParties = ! empty($data['is_optimize_css_enabled_by_other_party'])
    ? (array) $data['is_optimize_css_enabled_by_other_party']
    : array();

$minifyCssDisabled = ! empty($cssOptimizationOtherParties);
$minifyCssEnabled  = ! $minifyCssDisabled && ($data['minify_loaded_css'] == 1);

$combineCssConfigured = in_array(
    isset($data['combine_loaded_css']) ? $data['combine_loaded_css'] : '',
    array('for_all', 1, '1', true),
    true
);
$combineCssDisabled = ! empty($cssOptimizationOtherParties);
$combineCssEnabled  = $combineCssConfigured && ! $combineCssDisabled;

$inlineCssEnabled          = ($data['inline_css_files'] == 1);
$cacheDynamicCssEnabled    = ($data['cache_dynamic_loaded_css'] == 1);
$cacheDynamicCssExceptions = isset($data['cache_dynamic_loaded_css_exceptions']) ? trim((string) $data['cache_dynamic_loaded_css_exceptions']) : '';
$advancedOptionsActive     = $combineCssEnabled || $cacheDynamicCssEnabled;
$advancedOptionsOpen       = $combineCssConfigured || $cacheDynamicCssEnabled;

$wpRocketIsEnabledWithRemoveUnusedCss = wpacuIsDefinedConstant('WPACU_WP_ROCKET_REMOVE_UNUSED_CSS_ENABLED');
$assetsManagerUrl     = admin_url('admin.php?page=wpassetcleanup_assets_manager');
$testModeSettingsUrl  = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_settings&wpacu_selected_tab_area=wpacu-setting-test-mode');
$deferCssLoadedBody   = isset($data['defer_css_loaded_body']) ? (string) $data['defer_css_loaded_body'] : '';

$minifyCssStatusText = $minifyCssDisabled
    ? __('Managed by another plugin', 'wp-asset-clean-up')
    : ($minifyCssEnabled ? __('Enabled', 'wp-asset-clean-up') : __('Disabled', 'wp-asset-clean-up'));
$minifyCssStatusClass = $minifyCssDisabled
    ? 'wpacu-opt-badge--locked'
    : ($minifyCssEnabled ? 'wpacu-opt-badge--on' : '');

$advancedActiveCount = (int) $combineCssEnabled + (int) $cacheDynamicCssEnabled;
$advancedStatusText = $advancedActiveCount > 0
    ? sprintf(
        _n('%s advanced option active', '%s advanced options active', $advancedActiveCount, 'wp-asset-clean-up'),
        number_format_i18n($advancedActiveCount)
    )
    : __('No advanced options active', 'wp-asset-clean-up');

?>
<div id="<?php echo esc_attr($tabIdArea); ?>" class="wpacu-settings-tab-content" <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <main id="wpacu-css-optimization-settings" class="wpacu-opt-panel">
        <header class="wpacu-opt-header">
            <div>
                <span class="wpacu-opt-eyebrow"><?php esc_html_e('CSS optimization', 'wp-asset-clean-up'); ?></span>
                <h2><?php esc_html_e('Optimize CSS Delivery', 'wp-asset-clean-up'); ?></h2>
                <p><?php esc_html_e('Unload unnecessary styles first, then minify what remains. Use delivery changes only when testing confirms a real improvement.', 'wp-asset-clean-up'); ?></p>
            </div>
            <span class="wpacu-opt-header-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <?php esc_html_e('Test delivery changes', 'wp-asset-clean-up'); ?>
            </span>
        </header>

        <div class="wpacu-opt-body">
            <div class="wpacu-opt-flow">
                <span class="wpacu-opt-flow-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M7 12h10M10 17h4"/></svg>
                </span>
                <div class="wpacu-opt-flow-copy">
                    <div>
                        <strong><?php esc_html_e('Use the lowest-risk order', 'wp-asset-clean-up'); ?></strong>
                        <p><?php esc_html_e('Remove unused CSS, minify the remainder, then test any change to when or where styles are applied.', 'wp-asset-clean-up'); ?></p>
                    </div>
                    <div class="wpacu-opt-flow-steps" aria-label="<?php esc_attr_e('Recommended optimization order', 'wp-asset-clean-up'); ?>">
                        <span class="wpacu-opt-flow-step"><b>1</b><?php esc_html_e('Unload', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-opt-flow-arrow" aria-hidden="true">→</span>
                        <span class="wpacu-opt-flow-step"><b>2</b><?php esc_html_e('Minify', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-opt-flow-arrow" aria-hidden="true">→</span>
                        <span class="wpacu-opt-flow-step"><b>3</b><?php esc_html_e('Test delivery', 'wp-asset-clean-up'); ?></span>
                    </div>
                </div>
            </div>

            <?php
            $wpRocketIssues = array();

            if (($wpRocketIssues['minify_html'] = Misc::isWpRocketMinifyHtmlEnabled())
                || ($wpRocketIssues['optimize_css_delivery'] = OptimizeCss::isWpRocketOptimizeCssDeliveryEnabled())) {
                ?>
                <div class="wpacu-opt-alert wpacu-opt-alert--warning">
                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                    <div>
                        <strong><?php esc_html_e('Overlapping WP Rocket CSS delivery detected', 'wp-asset-clean-up'); ?></strong>
                        <?php if (! empty($wpRocketIssues['minify_html'])) { ?>
                            <p><?php echo wp_kses_post(sprintf(
                                __('CSS combination and delayed BODY styles do not take effect while <strong>Minify HTML</strong> is active in WP Rocket. Let WP Rocket handle delivery and use %s to unload unnecessary CSS, or disable the overlapping option.', 'wp-asset-clean-up'),
                                esc_html(WPACU_PLUGIN_TITLE)
                            )); ?></p>
                        <?php } ?>
                        <?php if (! empty($wpRocketIssues['optimize_css_delivery'])) { ?>
                            <p><?php echo wp_kses_post(sprintf(
                                __('CSS combination and delayed BODY styles do not take effect while <strong>Optimize CSS Delivery</strong> is active in WP Rocket. Use one CSS-delivery system at a time; %s can still remove unneeded styles.', 'wp-asset-clean-up'),
                                esc_html(WPACU_PLUGIN_TITLE)
                            )); ?></p>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <section class="wpacu-opt-section wpacu-opt-section--primary" aria-labelledby="wpacuCssMinifySectionTitle">
                <header class="wpacu-opt-section-header">
                    <div class="wpacu-opt-section-heading">
                        <span class="wpacu-opt-section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M7 12h10M10 17h4"/></svg>
                        </span>
                        <span>
                            <span class="wpacu-opt-section-kicker"><?php esc_html_e('Recommended for most sites', 'wp-asset-clean-up'); ?></span>
                            <strong id="wpacuCssMinifySectionTitle" class="wpacu-opt-section-title"><?php esc_html_e('Minify the CSS that remains', 'wp-asset-clean-up'); ?></strong>
                            <span class="wpacu-opt-section-description"><?php esc_html_e('Reduces transfer size after unloading without changing when the stylesheet runs.', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </div>
                    <div class="wpacu-opt-section-meta">
                        <span id="wpacuCssMinifyStatus"
                              class="wpacu-opt-badge <?php echo esc_attr($minifyCssStatusClass); ?>"
                              data-enabled="<?php esc_attr_e('Enabled', 'wp-asset-clean-up'); ?>"
                              data-disabled="<?php esc_attr_e('Disabled', 'wp-asset-clean-up'); ?>"
                              data-locked="<?php esc_attr_e('Managed by another plugin', 'wp-asset-clean-up'); ?>"><?php echo esc_html($minifyCssStatusText); ?></span>
                    </div>
                </header>

                <div class="wpacu-opt-section-body">
                    <article class="wpacu-opt-card">
                        <header class="wpacu-opt-card-header wpacu-opt-card-header--switch-first">
                            <span class="wpacu-opt-card-control">
                                <label class="wpacu_switch <?php if ($minifyCssDisabled) { echo 'wpacu_disabled'; } ?>">
                                    <input id="wpacu_minify_css_enable"
                                           data-target-opacity="#wpacu_minify_css_area"
                                           data-status-locked="<?php echo $minifyCssDisabled ? '1' : '0'; ?>"
                                           type="checkbox"
                                        <?php
                                        if ($minifyCssDisabled) {
                                            echo 'disabled="disabled"';
                                        } else {
                                            checked($minifyCssEnabled);
                                        }
                                        ?>
                                           name="<?php echo esc_attr($settingsInputName); ?>[minify_loaded_css]"
                                           value="1" />
                                    <span class="wpacu_slider wpacu_round"></span>
                                </label>
                            </span>
                            <span class="wpacu-opt-card-copy">
                                <strong class="wpacu-opt-card-title"><?php esc_html_e('Minify CSS', 'wp-asset-clean-up'); ?></strong>
                                <span class="wpacu-opt-card-description"><?php esc_html_e('Minifies eligible stylesheet files and/or inline STYLE blocks, then serves optimized output from the Asset CleanUp cache.', 'wp-asset-clean-up'); ?></span>
                            </span>
                        </header>

                        <div class="wpacu-opt-card-body">
                            <?php if ($minifyCssDisabled) { ?>
                                <div class="wpacu-opt-alert wpacu-opt-alert--locked">
                                    <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                                    <div>
                                        <strong><?php echo wp_kses_post(sprintf(
                                            __('Minification is already managed by <em>%s</em>.', 'wp-asset-clean-up'),
                                            esc_html(implode(', ', $cssOptimizationOtherParties))
                                        )); ?></strong>
                                        <p><?php echo wp_kses_post(sprintf(
                                            __('Unload unnecessary styles in the <a href="%s">CSS &amp; JavaScript Manager</a>, then let the existing optimizer minify the remainder.', 'wp-asset-clean-up'),
                                            esc_url($assetsManagerUrl)
                                        )); ?></p>
                                    </div>
                                </div>
                            <?php } ?>

                            <div id="wpacu_minify_css_area" class="wpacu-opt-dependent" style="<?php echo esc_attr($minifyCssEnabled ? 'opacity: 1;' : 'opacity: 0.4;'); ?>">
                                <fieldset class="wpacu-opt-fieldset">
                                    <legend><?php esc_html_e('Minify', 'wp-asset-clean-up'); ?></legend>
                                    <div class="wpacu-opt-choice-grid">
                                        <label class="wpacu-opt-choice" for="minify_loaded_css_for_link_href_radio">
                                            <input id="minify_loaded_css_for_link_href_radio"
                                                <?php checked(in_array($data['minify_loaded_css_for'], array('href', ''), true)); ?>
                                                   type="radio"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[minify_loaded_css_for]"
                                                   value="href" />
                                            <span class="wpacu-opt-choice-copy">
                                                <strong><?php esc_html_e('Stylesheet files', 'wp-asset-clean-up'); ?></strong>
                                                <small><?php esc_html_e('LINK tags with an href attribute.', 'wp-asset-clean-up'); ?></small>
                                                <span class="wpacu-opt-choice-tag"><?php esc_html_e('Recommended', 'wp-asset-clean-up'); ?></span>
                                            </span>
                                        </label>
                                        <label class="wpacu-opt-choice is-aggressive" for="minify_loaded_css_for_style_inline_radio">
                                            <input id="minify_loaded_css_for_style_inline_radio"
                                                <?php checked($data['minify_loaded_css_for'] === 'inline'); ?>
                                                   type="radio"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[minify_loaded_css_for]"
                                                   value="inline" />
                                            <span class="wpacu-opt-choice-copy">
                                                <strong><?php esc_html_e('Inline CSS only', 'wp-asset-clean-up'); ?></strong>
                                                <small><?php esc_html_e('CSS inside STYLE tags.', 'wp-asset-clean-up'); ?></small>
                                            </span>
                                        </label>
                                        <label class="wpacu-opt-choice is-aggressive" for="minify_loaded_css_for_link_style_all_radio">
                                            <input id="minify_loaded_css_for_link_style_all_radio"
                                                <?php checked($data['minify_loaded_css_for'] === 'all'); ?>
                                                   type="radio"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[minify_loaded_css_for]"
                                                   value="all" />
                                            <span class="wpacu-opt-choice-copy">
                                                <strong><?php esc_html_e('Files and inline CSS', 'wp-asset-clean-up'); ?></strong>
                                                <small><?php esc_html_e('Processes both sources; test inline output carefully.', 'wp-asset-clean-up'); ?></small>
                                            </span>
                                        </label>
                                    </div>
                                </fieldset>

                                <div id="wpacu_minify_css_exceptions_area" class="wpacu-opt-field">
                                    <label class="wpacu-opt-field-label" for="wpacu_minify_css_exceptions">
                                        <span><?php esc_html_e('Minification exclusions', 'wp-asset-clean-up'); ?></span>
                                        <small><?php esc_html_e('One path or pattern per line', 'wp-asset-clean-up'); ?></small>
                                    </label>
                                    <textarea class="wpacu-opt-textarea"
                                              rows="4"
                                              id="wpacu_minify_css_exceptions"
                                              name="<?php echo esc_attr($settingsInputName); ?>[minify_loaded_css_exceptions]"
                                              placeholder="Example:&#10;/(.*?).min.css&#10;/wd-instagram-feed/(.*?).css"><?php echo esc_textarea($data['minify_loaded_css_exceptions']); ?></textarea>
                                </div>

                                <div class="wpacu-opt-note">
                                    <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
                                    <span><?php esc_html_e('Cached CSS is regenerated when the source version changes; that version is also appended to the optimized filename.', 'wp-asset-clean-up'); ?></span>
                                </div>

                                <details class="wpacu-opt-disclosure">
                                    <summary><?php esc_html_e('Files automatically skipped by the minifier', 'wp-asset-clean-up'); ?></summary>
                                    <div class="wpacu-opt-disclosure-content">
                                        <p><?php esc_html_e('Asset CleanUp avoids duplicate work for known files that are already optimized or generated in minified form.', 'wp-asset-clean-up'); ?></p>
                                        <ul>
                                            <li><?php echo wp_kses_post(__('WordPress core CSS ending in <code>.min.css</code>.', 'wp-asset-clean-up')); ?></li>
                                            <li><?php echo wp_kses_post(__('Generated CSS under <code>/wp-content/uploads/elementor/</code> or <code>/wp-content/uploads/oxygen/</code>.', 'wp-asset-clean-up')); ?></li>
                                            <li><?php esc_html_e('Selected known, already-optimized plugin stylesheets.', 'wp-asset-clean-up'); ?></li>
                                        </ul>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="wpacu-opt-section" aria-labelledby="wpacuCssDeliverySectionTitle">
                <header class="wpacu-opt-section-header">
                    <div class="wpacu-opt-section-heading">
                        <span class="wpacu-opt-section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18M12 3v18"/><path d="M7 7l10 10M17 7L7 17" opacity=".35"/></svg>
                        </span>
                        <span>
                            <span class="wpacu-opt-section-kicker"><?php esc_html_e('Situational options', 'wp-asset-clean-up'); ?></span>
                            <strong id="wpacuCssDeliverySectionTitle" class="wpacu-opt-section-title"><?php esc_html_e('Inline or delay selected stylesheets', 'wp-asset-clean-up'); ?></strong>
                            <span class="wpacu-opt-section-description"><?php esc_html_e('These settings can change caching or render timing. Test one at a time on representative pages.', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </div>
                    <div class="wpacu-opt-section-meta">
                        <span class="wpacu-opt-badge wpacu-opt-badge--warning"><?php esc_html_e('Use selectively', 'wp-asset-clean-up'); ?></span>
                    </div>
                </header>

                <div class="wpacu-opt-section-body">
                    <div class="wpacu-opt-card-grid wpacu-opt-card-grid--2">
                        <article class="wpacu-opt-card wpacu-opt-card--full">
                            <header class="wpacu-opt-card-header wpacu-opt-card-header--switch-first">
                                <span class="wpacu-opt-card-control">
                                    <label class="wpacu_switch">
                                        <input id="wpacu_inline_css_files_enable"
                                               data-target-opacity="#wpacu_inline_css_files_info_area"
                                               type="checkbox"
                                            <?php checked($inlineCssEnabled); ?>
                                               name="<?php echo esc_attr($settingsInputName); ?>[inline_css_files]"
                                               value="1" />
                                        <span class="wpacu_slider wpacu_round"></span>
                                    </label>
                                </span>
                                <span class="wpacu-opt-card-copy">
                                    <strong class="wpacu-opt-card-title"><?php esc_html_e('Inline small local CSS files', 'wp-asset-clean-up'); ?></strong>
                                    <span class="wpacu-opt-card-description"><?php esc_html_e('Removes a request, but increases HTML size and prevents separate browser caching.', 'wp-asset-clean-up'); ?></span>
                                </span>
                            </header>
                            <div class="wpacu-opt-card-body">
                                <div id="wpacu_inline_css_files_info_area" class="wpacu-opt-dependent" style="<?php echo esc_attr($inlineCssEnabled ? 'opacity: 1;' : 'opacity: 0.4;'); ?>">
                                    <div class="wpacu-opt-inline-control">
                                        <label for="wpacu_inline_css_files_below_size_checkbox">
                                            <input id="wpacu_inline_css_files_below_size_checkbox"
                                                <?php checked($data['inline_css_files_below_size'] == 1); ?>
                                                   type="checkbox"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[inline_css_files_below_size]"
                                                   value="1" />
                                            <span><?php esc_html_e('Automatically inline files smaller than', 'wp-asset-clean-up'); ?></span>
                                        </label>
                                        <input type="number"
                                               min="1"
                                               aria-label="<?php esc_attr_e('Maximum CSS file size in kilobytes', 'wp-asset-clean-up'); ?>"
                                               name="<?php echo esc_attr($settingsInputName); ?>[inline_css_files_below_size_input]"
                                               value="<?php echo esc_attr($data['inline_css_files_below_size_input']); ?>" />
                                        <span><?php esc_html_e('KB', 'wp-asset-clean-up'); ?></span>
                                    </div>

                                    <div id="wpacu_inline_css_files_list_area" class="wpacu-opt-field">
                                        <label class="wpacu-opt-field-label" for="wpacu_inline_css_files_list">
                                            <span><?php esc_html_e('Specific files or matching fragments', 'wp-asset-clean-up'); ?></span>
                                            <small><?php esc_html_e('Optional; one per line', 'wp-asset-clean-up'); ?></small>
                                        </label>
                                        <textarea class="wpacu-opt-textarea"
                                                  rows="4"
                                                  id="wpacu_inline_css_files_list"
                                                  name="<?php echo esc_attr($settingsInputName); ?>[inline_css_files_list]"
                                                  placeholder="Example:&#10;/wp-content/plugins/plugin-title/styles/small-file.css&#10;/wp-content/themes/my-theme/css/small.css"><?php echo esc_textarea($data['inline_css_files_list']); ?></textarea>
                                    </div>

                                    <details class="wpacu-opt-disclosure">
                                        <summary><?php esc_html_e('Path and RegEx guidance', 'wp-asset-clean-up'); ?></summary>
                                        <div class="wpacu-opt-disclosure-content">
                                            <p><?php echo wp_kses_post(sprintf(
                                                __('Enter original CSS sources, not cached files usually stored under <code>%s</code>. Relative paths are portable across staging and production. Regular expressions are accepted; the hash character is added automatically as the delimiter.', 'wp-asset-clean-up'),
                                                esc_html(wp_make_link_relative(content_url()) . OptimizeCommon::getRelPathPluginCacheDir())
                                            )); ?></p>
                                            <span class="wpacu-opt-code-examples">/wp-content/plugins/plugin-title/styles/small-file.css<br>/wp-content/themes/my-theme/css/small.css</span>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </article>

                        <article class="wpacu-opt-card wpacu-opt-card--full<?php echo ! $wpacuOptimizeCssIsPro ? ' wpacu-opt-card--pro-only' : ''; ?>">
                            <?php if (! $wpacuOptimizeCssIsPro) { ?>
                            <span class="wpacu-opt-badge wpacu-opt-badge--locked wpacu-opt-pro-only-legend"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e('Pro only', 'wp-asset-clean-up'); ?></span>
                            <?php } ?>
                            <header class="wpacu-opt-card-header">
                                <span class="wpacu-opt-card-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/></svg>
                                </span>
                                <span class="wpacu-opt-card-copy">
                                    <strong class="wpacu-opt-card-title"><?php esc_html_e('Delay stylesheets moved to the BODY', 'wp-asset-clean-up'); ?></strong>
                                    <span class="wpacu-opt-card-description"><?php esc_html_e('Applies selected non-critical CSS after the document is parsed and keeps a noscript fallback.', 'wp-asset-clean-up'); ?></span>
                                </span>
                                <?php if ($wpacuOptimizeCssIsPro) { ?>
                                <span class="wpacu-opt-card-control">
                                    <span class="wpacu-opt-badge wpacu-opt-badge--warning"><?php esc_html_e('Test carefully', 'wp-asset-clean-up'); ?></span>
                                </span>
                                <?php } ?>
                            </header>
                            <div class="wpacu-opt-card-body"<?php echo ! $wpacuOptimizeCssIsPro ? ' style="opacity: 0.4;"' : ''; ?>>
                                <?php if ($wpRocketIsEnabledWithRemoveUnusedCss) { ?>
                                    <div class="wpacu-opt-alert wpacu-opt-alert--locked">
                                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                                        <div>
                                            <strong><?php esc_html_e('WP Rocket is controlling CSS delivery', 'wp-asset-clean-up'); ?></strong>
                                            <p><?php echo wp_kses_post(sprintf(
                                                __('The two delayed modes are locked while <a target="_blank" rel="noopener" href="%s">Remove Unused CSS</a> is enabled in WP Rocket. Avoid mixing the delivery features.', 'wp-asset-clean-up'),
                                                esc_url(admin_url('options-general.php?page=wprocket#file_optimization'))
                                            )); ?></p>
                                        </div>
                                    </div>
                                <?php } ?>

                                <fieldset class="wpacu-opt-fieldset">
                                    <legend><?php esc_html_e('Choose which BODY stylesheets are delayed', 'wp-asset-clean-up'); ?></legend>
                                    <div class="wpacu-opt-choice-grid">
                                        <label class="wpacu-opt-choice<?php echo (! $wpacuOptimizeCssIsPro || $wpRocketIsEnabledWithRemoveUnusedCss) ? ' wpacu-locked wpacu-disabled-status' : ''; ?>" for="wpacu_defer_css_loaded_body_moved">
                                            <input id="wpacu_defer_css_loaded_body_moved"
                                                   type="radio"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[defer_css_loaded_body]"
                                                <?php checked(in_array($deferCssLoadedBody, array('moved', ''), true)); ?>
                                                   value="moved" <?php disabled(! $wpacuOptimizeCssIsPro || $wpRocketIsEnabledWithRemoveUnusedCss); ?> />
                                            <span class="wpacu-opt-choice-copy">
                                                <strong><?php esc_html_e('Only styles moved by Asset CleanUp', 'wp-asset-clean-up'); ?></strong>
                                                <small><?php echo wp_kses_post(__('Delays LINK tags moved from <code>&lt;head&gt;</code> to <code>&lt;body&gt;</code>.', 'wp-asset-clean-up')); ?></small>
                                                <span class="wpacu-opt-choice-tag"><?php esc_html_e('Default', 'wp-asset-clean-up'); ?></span>
                                            </span>
                                        </label>
                                        <label class="wpacu-opt-choice is-aggressive<?php echo (! $wpacuOptimizeCssIsPro || $wpRocketIsEnabledWithRemoveUnusedCss) ? ' wpacu-locked wpacu-disabled-status' : ''; ?>" for="wpacu_defer_css_loaded_body_all">
                                            <input id="wpacu_defer_css_loaded_body_all"
                                                   type="radio"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[defer_css_loaded_body]"
                                                <?php checked($deferCssLoadedBody === 'all'); ?>
                                                   value="all" <?php disabled(! $wpacuOptimizeCssIsPro || $wpRocketIsEnabledWithRemoveUnusedCss); ?> />
                                            <span class="wpacu-opt-choice-copy">
                                                <strong><?php esc_html_e('All BODY stylesheets', 'wp-asset-clean-up'); ?></strong>
                                                <small><?php esc_html_e('More aggressive; delays LINK tags already in or moved to BODY.', 'wp-asset-clean-up'); ?></small>
                                            </span>
                                        </label>
                                        <label class="wpacu-opt-choice is-off<?php echo ! $wpacuOptimizeCssIsPro ? ' wpacu-locked wpacu-disabled-status' : ''; ?>" for="wpacu_defer_css_loaded_body_no">
                                            <input id="wpacu_defer_css_loaded_body_no"
                                                   type="radio"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[defer_css_loaded_body]"
                                                <?php checked($deferCssLoadedBody === 'no'); ?>
                                                   value="no" <?php disabled(! $wpacuOptimizeCssIsPro); ?> />
                                            <span class="wpacu-opt-choice-copy">
                                                <strong><?php esc_html_e('Do not delay BODY stylesheets', 'wp-asset-clean-up'); ?></strong>
                                                <small><?php esc_html_e('Leaves stylesheet LINK tags in BODY unchanged.', 'wp-asset-clean-up'); ?></small>
                                            </span>
                                        </label>
                                    </div>
                                </fieldset>

                                <div class="wpacu-opt-note wpacu-opt-note--caution">
                                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                    <span><strong><?php esc_html_e('Non-critical CSS only.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Late styles can cause visible restyling or layout shifts. Check the first viewport, menus, forms, and interactive elements.', 'wp-asset-clean-up'); ?></span>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <?php require WPACU_PLUGIN_DIR . '/templates/_common/settings/critical-css-gateway.php'; ?>

            <details id="wpacu-css-advanced-options" class="wpacu-opt-details" <?php if ($advancedOptionsOpen) { echo 'open'; } ?>>
                <summary>
                    <span class="wpacu-opt-details-summary-copy">
                        <span class="wpacu-opt-section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9A1.7 1.7 0 0 0 21 10h.2v4H21a1.7 1.7 0 0 0-1.6 1z"/></svg>
                        </span>
                        <span>
                            <span class="wpacu-opt-section-kicker"><?php esc_html_e('Advanced & compatibility', 'wp-asset-clean-up'); ?></span>
                            <strong class="wpacu-opt-section-title"><?php esc_html_e('Request grouping and generated CSS caching', 'wp-asset-clean-up'); ?></strong>
                            <span class="wpacu-opt-section-description"><?php esc_html_e('Keep these off unless a specific environment or endpoint gives you a measured reason to use them.', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </span>
                    <span class="wpacu-opt-details-meta">
                        <span class="wpacu-opt-badge wpacu-opt-badge--advanced"><?php esc_html_e('Advanced', 'wp-asset-clean-up'); ?></span>
                        <span id="wpacu-css-advanced-state"
                              aria-live="polite"
                              class="wpacu-opt-badge <?php echo $advancedOptionsActive ? 'wpacu-opt-badge--warning' : ''; ?>"
                              data-active-count="<?php echo esc_attr($advancedActiveCount); ?>"><?php echo esc_html($advancedStatusText); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 wpacu-opt-details-arrow" aria-hidden="true"></span>
                    </span>
                </summary>

                <div class="wpacu-opt-details-body">
                    <div class="wpacu-opt-stack">
                        <article id="wpacu-combine-css-panel" class="wpacu-opt-card">
                            <header class="wpacu-opt-card-header wpacu-opt-card-header--switch-first">
                                <span class="wpacu-opt-card-control">
                                    <label class="wpacu_switch <?php if ($combineCssDisabled) { echo 'wpacu_disabled'; } ?>">
                                        <input id="wpacu_combine_loaded_css_enable"
                                               data-target-opacity="#wpacu_combine_loaded_css_info_area"
                                               data-status-locked="<?php echo $combineCssDisabled ? '1' : '0'; ?>"
                                               type="checkbox"
                                            <?php
                                            if ($combineCssDisabled) {
                                                echo 'disabled="disabled"';
                                            } else {
                                                checked($combineCssEnabled);
                                            }
                                            ?>
                                               name="<?php echo esc_attr($settingsInputName); ?>[combine_loaded_css]"
                                               value="1" />
                                        <span class="wpacu_slider wpacu_round"></span>
                                    </label>
                                </span>
                                <span class="wpacu-opt-card-copy">
                                    <strong class="wpacu-opt-card-title"><?php esc_html_e('Combine CSS files', 'wp-asset-clean-up'); ?></strong>
                                    <span id="wpacuCssCombineDescription"
                                          class="wpacu-opt-card-description"
                                          data-default="<?php esc_attr_e('Creates larger cached groups from eligible local CSS. Usually unnecessary on HTTP/2 or HTTP/3.', 'wp-asset-clean-up'); ?>"
                                          data-modern="<?php esc_attr_e('Optional on this server. Enable only after testing a measurable improvement.', 'wp-asset-clean-up'); ?>"><?php esc_html_e('Creates larger cached groups from eligible local CSS. Usually unnecessary on HTTP/2 or HTTP/3.', 'wp-asset-clean-up'); ?></span>
                                </span>
                                <span class="wpacu-opt-card-meta">
                                    <span id="wpacu-combine-css-state" class="wpacu-opt-badge <?php echo $combineCssDisabled ? 'wpacu-opt-badge--locked' : ($combineCssEnabled ? 'wpacu-opt-badge--on' : ''); ?>">
                                        <?php echo $combineCssDisabled ? esc_html__('Locked', 'wp-asset-clean-up') : ($combineCssEnabled ? esc_html__('Enabled', 'wp-asset-clean-up') : esc_html__('Situational', 'wp-asset-clean-up')); ?>
                                    </span>
                                </span>
                            </header>
                            <div class="wpacu-opt-card-body">
                                <div class="wpacu-combine-notice-default wpacu_hide wpacu-opt-note">
                                    <span class="dashicons dashicons-info" aria-hidden="true"></span>
                                    <span>
                                        <strong><?php esc_html_e('Modern connection guidance:', 'wp-asset-clean-up'); ?></strong>
                                        <?php esc_html_e('Combining CSS can reduce cache granularity. Leave it disabled unless repeatable before-and-after tests show a benefit.', 'wp-asset-clean-up'); ?>
                                        <a data-wpacu-modal-target="wpacu-http2-info-css-target" href="#wpacu-http2-info-css"><?php esc_html_e('Read more', 'wp-asset-clean-up'); ?></a>
                                        · <a class="wpacu_verify_http2_protocol" target="_blank" rel="noopener" href="https://tools.keycdn.com/http2-test"><?php esc_html_e('External HTTP/2 check', 'wp-asset-clean-up'); ?></a>
                                        <span class="wpacu-http-protocol-check-status" aria-live="polite"></span>
                                    </span>
                                </div>
                                <div class="wpacu-combine-notice-http-2-detected wpacu-opt-protocol-result wpacu_hide">
                                    <span class="wpacu-opt-protocol-badge"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <span class="wpacu-http-protocol-label">HTTP/2</span></span>
                                    <span><?php echo wp_kses_post(sprintf(__('<strong>Modern protocol detected.</strong> The server-side check for %s indicates that request-count reduction alone is unlikely to justify CSS combination.', 'wp-asset-clean-up'), esc_html(get_site_url()))); ?></span>
                                    <a class="wpacu-opt-protocol-link" data-wpacu-modal-target="wpacu-http2-info-css-target" href="#wpacu-http2-info-css"><?php esc_html_e('Why?', 'wp-asset-clean-up'); ?></a>
                                </div>

                                <?php if ($combineCssDisabled) { ?>
                                    <div class="wpacu-opt-alert wpacu-opt-alert--locked">
                                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                                        <div>
                                            <strong><?php echo wp_kses_post(sprintf(
                                                __('CSS optimization is already managed by <em>%s</em>.', 'wp-asset-clean-up'),
                                                esc_html(implode(', ', $cssOptimizationOtherParties))
                                            )); ?></strong>
                                            <p><?php esc_html_e('Unload unnecessary CSS with Asset CleanUp, then let the existing optimizer handle combination or delivery.', 'wp-asset-clean-up'); ?></p>
                                        </div>
                                    </div>
                                <?php } ?>

                                <div id="wpacu_combine_loaded_css_info_area" class="wpacu-opt-dependent" style="<?php echo esc_attr($combineCssEnabled ? 'opacity: 1;' : 'opacity: 0.4;'); ?>">
                                    <fieldset class="wpacu-opt-fieldset">
                                        <legend><?php esc_html_e('Apply combination to', 'wp-asset-clean-up'); ?></legend>
                                        <div class="wpacu-opt-choice-grid wpacu-opt-choice-grid--2">
                                            <label class="wpacu-opt-choice" for="combine_loaded_css_for_guests_radio">
                                                <input id="combine_loaded_css_for_guests_radio"
                                                    <?php checked(in_array($data['combine_loaded_css_for'], array('guests', ''), true)); ?>
                                                       type="radio"
                                                       name="<?php echo esc_attr($settingsInputName); ?>[combine_loaded_css_for]"
                                                       value="guests" />
                                                <span class="wpacu-opt-choice-copy">
                                                    <strong><?php esc_html_e('Guest visitors only', 'wp-asset-clean-up'); ?></strong>
                                                    <small><?php esc_html_e('Keeps logged-in traffic on the original files.', 'wp-asset-clean-up'); ?></small>
                                                    <span class="wpacu-opt-choice-tag"><?php esc_html_e('Default', 'wp-asset-clean-up'); ?></span>
                                                </span>
                                            </label>
                                            <label class="wpacu-opt-choice is-aggressive" for="combine_loaded_css_for_all_radio">
                                                <input id="combine_loaded_css_for_all_radio"
                                                    <?php checked($data['combine_loaded_css_for'] === 'all'); ?>
                                                       type="radio"
                                                       name="<?php echo esc_attr($settingsInputName); ?>[combine_loaded_css_for]"
                                                       value="all" />
                                                <span class="wpacu-opt-choice-copy">
                                                    <strong><?php esc_html_e('Logged-in and guest visitors', 'wp-asset-clean-up'); ?></strong>
                                                    <small><?php esc_html_e('Useful temporarily with Test Mode for private testing.', 'wp-asset-clean-up'); ?></small>
                                                </span>
                                            </label>
                                        </div>
                                    </fieldset>

                                    <div id="wpacu_combine_loaded_css_exceptions_area" class="wpacu-opt-field">
                                        <label class="wpacu-opt-field-label" for="combine_loaded_css_exceptions">
                                            <span><?php esc_html_e('Combination exclusions', 'wp-asset-clean-up'); ?></span>
                                            <small><?php esc_html_e('One path or pattern per line', 'wp-asset-clean-up'); ?></small>
                                        </label>
                                        <textarea class="wpacu-opt-textarea"
                                                  rows="4"
                                                  id="combine_loaded_css_exceptions"
                                                  name="<?php echo esc_attr($settingsInputName); ?>[combine_loaded_css_exceptions]"
                                                  placeholder="Example:&#10;/wp-includes/css/dashicons.min.css&#10;/wp-content/plugins/plugin-title/css/(.*?).css"><?php echo esc_textarea($data['combine_loaded_css_exceptions']); ?></textarea>
                                    </div>

                                    <div class="wpacu-opt-note wpacu-opt-note--caution">
                                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                        <span><strong><?php esc_html_e('Test before enabling for visitors.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('A change to one source can invalidate a larger bundle, and order or relative-URL issues may require exclusions.', 'wp-asset-clean-up'); ?></span>
                                    </div>

                                    <details class="wpacu-opt-disclosure">
                                        <summary><?php esc_html_e('How it works, cache location, and skipped requests', 'wp-asset-clean-up'); ?></summary>
                                        <div class="wpacu-opt-disclosure-content">
                                            <p><?php esc_html_e('Eligible local CSS remaining in HEAD and BODY is grouped separately. Associated wp_add_inline_style() content stays with its stylesheet so the original order is preserved.', 'wp-asset-clean-up'); ?></p>
                                            <p><?php echo wp_kses_post(sprintf(
                                                __('Generated files are stored under <code>%s</code>.', 'wp-asset-clean-up'),
                                                esc_html(str_replace(dirname(WP_CONTENT_DIR), '', WP_CONTENT_DIR) . OptimizeCommon::getRelPathPluginCacheDir() . 'css/')
                                            )); ?></p>
                                            <p><?php echo wp_kses_post(sprintf(
                                                __('For a private first pass, enable <a target="_blank" rel="noopener" href="%s">Test Mode</a> and temporarily apply the option to logged-in and guest visitors.', 'wp-asset-clean-up'),
                                                esc_url($testModeSettingsUrl)
                                            )); ?></p>
                                            <ul>
                                                <li><?php esc_html_e('Combination is skipped for unauthorized visitors while Test Mode is active.', 'wp-asset-clean-up'); ?></li>
                                                <li><?php esc_html_e('It is skipped for logged-in requests when guest-only mode is selected.', 'wp-asset-clean-up'); ?></li>
                                                <li><?php esc_html_e('Query-string, POST, Dashboard, and non-standard front-end requests can also be skipped.', 'wp-asset-clean-up'); ?></li>
                                            </ul>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </article>

                        <article id="wpacu-dynamic-css-panel" class="wpacu-opt-card">
                            <header class="wpacu-opt-card-header wpacu-opt-card-header--switch-first">
                                <span class="wpacu-opt-card-control">
                                    <label class="wpacu_switch">
                                        <input id="wpacu_cache_dynamic_loaded_css_enable"
                                               data-target-opacity="#wpacu_cache_dynamic_loaded_css_info_area"
                                               type="checkbox"
                                            <?php checked($cacheDynamicCssEnabled); ?>
                                               name="<?php echo esc_attr($settingsInputName); ?>[cache_dynamic_loaded_css]"
                                               value="1" />
                                        <span class="wpacu_slider wpacu_round"></span>
                                    </label>
                                </span>
                                <span class="wpacu-opt-card-copy">
                                    <strong class="wpacu-opt-card-title"><?php esc_html_e('Cache dynamically generated CSS', 'wp-asset-clean-up'); ?></strong>
                                    <span class="wpacu-opt-card-description"><?php esc_html_e('Avoids bootstrapping WordPress for repeated requests to eligible public CSS endpoints.', 'wp-asset-clean-up'); ?></span>
                                </span>
                                <span class="wpacu-opt-card-meta">
                                    <span id="wpacu-dynamic-css-state" class="wpacu-opt-badge <?php echo $cacheDynamicCssEnabled ? 'wpacu-opt-badge--warning' : ''; ?>"><?php echo $cacheDynamicCssEnabled ? esc_html__('Enabled', 'wp-asset-clean-up') : esc_html__('Specialized', 'wp-asset-clean-up'); ?></span>
                                </span>
                            </header>
                            <div class="wpacu-opt-card-body">
                                <div id="wpacu_cache_dynamic_loaded_css_info_area" class="wpacu-opt-dependent" style="<?php echo esc_attr($cacheDynamicCssEnabled ? 'opacity: 1;' : 'opacity: 0.4;'); ?>">
                                    <p class="wpacu-opt-card-lead"><?php echo wp_kses_post(sprintf(
                                        __('Example endpoint: <code>/wp-content/plugins/plugin-name/css/generate-style.php?ver=%s</code>.', 'wp-asset-clean-up'),
                                        esc_html($wp_version)
                                    )); ?></p>
                                    <div class="wpacu-opt-note wpacu-opt-note--caution">
                                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                        <span><strong><?php esc_html_e('Public output only.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Do not cache responses that vary by user, login state, language, cookie, nonce, permission, cart state, or another private condition.', 'wp-asset-clean-up'); ?></span>
                                    </div>

                                    <div class="wpacu-opt-field">
                                        <label class="wpacu-opt-field-label" for="wpacu_cache_dynamic_loaded_css_exceptions">
                                            <span><?php esc_html_e('Do not cache matching dynamic CSS URLs', 'wp-asset-clean-up'); ?></span>
                                            <small><?php esc_html_e('One URL fragment or RegEx per line', 'wp-asset-clean-up'); ?></small>
                                        </label>
                                        <textarea class="wpacu-opt-textarea"
                                                  rows="4"
                                                  id="wpacu_cache_dynamic_loaded_css_exceptions"
                                                  name="<?php echo esc_attr($settingsInputName); ?>[cache_dynamic_loaded_css_exceptions]"
                                                  placeholder="Example:&#10;/css-generator.php?user_id=&#10;#(?:user_id|nonce|token)=#i"><?php echo esc_textarea($cacheDynamicCssExceptions); ?></textarea>
                                        <p class="wpacu-opt-field-help"><?php esc_html_e('Use a stable fragment that matches every private variation, such as “/css-generator.php?user_id=”, rather than excluding only one specific user ID. Matching endpoints keep their original URL and are not fetched, minified, or served from the Asset CleanUp cache.', 'wp-asset-clean-up'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </details>
        </div>

        <script>
            (function () {
                'use strict';

                function initCssOptimizationStatusSync() {
                    var root = document.getElementById('wpacu-css-optimization-settings');

                    if (! root || root.getAttribute('data-status-sync-initialized') === '1') {
                        return;
                    }

                    root.setAttribute('data-status-sync-initialized', '1');

                    var minifyInput = document.getElementById('wpacu_minify_css_enable');
                    var minifyStatus = document.getElementById('wpacuCssMinifyStatus');
                    var inlineInput = document.getElementById('wpacu_inline_css_files_enable');
                    var combineInput = document.getElementById('wpacu_combine_loaded_css_enable');
                    var combineStatus = document.getElementById('wpacu-combine-css-state');
                    var combineDescription = document.getElementById('wpacuCssCombineDescription');
                    var dynamicInput = document.getElementById('wpacu_cache_dynamic_loaded_css_enable');
                    var dynamicStatus = document.getElementById('wpacu-dynamic-css-state');
                    var advancedPanel = document.getElementById('wpacu-css-advanced-options');
                    var advancedStatus = document.getElementById('wpacu-css-advanced-state');
                    var advancedMenuStatus = document.getElementById('wpacuOptimizeCssAdvancedMenuStatus');
                    var advancedMenuCircle = advancedMenuStatus ? advancedMenuStatus.querySelector('.wpacu-circle-status') : null;

                    var strings = {
                        enabled: <?php echo wp_json_encode(__('Enabled', 'wp-asset-clean-up')); ?>,
                        disabled: <?php echo wp_json_encode(__('Disabled', 'wp-asset-clean-up')); ?>,
                        locked: <?php echo wp_json_encode(__('Locked', 'wp-asset-clean-up')); ?>,
                        situational: <?php echo wp_json_encode(__('Situational', 'wp-asset-clean-up')); ?>,
                        usuallyUnnecessary: <?php echo wp_json_encode(__('Usually unnecessary', 'wp-asset-clean-up')); ?>,
                        enabledTestCarefully: <?php echo wp_json_encode(__('Enabled — test carefully', 'wp-asset-clean-up')); ?>,
                        specialized: <?php echo wp_json_encode(__('Specialized', 'wp-asset-clean-up')); ?>,
                        noAdvanced: <?php echo wp_json_encode(__('No advanced options active', 'wp-asset-clean-up')); ?>,
                        oneAdvanced: <?php echo wp_json_encode(__('1 advanced option active', 'wp-asset-clean-up')); ?>,
                        twoAdvanced: <?php echo wp_json_encode(__('2 advanced options active', 'wp-asset-clean-up')); ?>
                    };

                    function isEffective(input) {
                        return Boolean(input && ! input.disabled && input.getAttribute('data-status-locked') !== '1' && input.checked);
                    }

                    function setBadge(element, text, className) {
                        if (! element) {
                            return;
                        }

                        element.textContent = text;
                        element.classList.remove('wpacu-opt-badge--on', 'wpacu-opt-badge--warning', 'wpacu-opt-badge--locked');

                        if (className) {
                            element.classList.add(className);
                        }
                    }

                    function syncMinify() {
                        if (! minifyInput || ! minifyStatus) {
                            return;
                        }

                        if (minifyInput.getAttribute('data-status-locked') === '1' || minifyInput.disabled) {
                            setBadge(minifyStatus, minifyStatus.getAttribute('data-locked'), 'wpacu-opt-badge--locked');
                            return;
                        }

                        setBadge(
                            minifyStatus,
                            minifyInput.checked ? minifyStatus.getAttribute('data-enabled') : minifyStatus.getAttribute('data-disabled'),
                            minifyInput.checked ? 'wpacu-opt-badge--on' : ''
                        );
                    }

                    function syncAdvanced() {
                        var combineEnabled = isEffective(combineInput);
                        var dynamicEnabled = isEffective(dynamicInput);
                        var activeCount = (combineEnabled ? 1 : 0) + (dynamicEnabled ? 1 : 0);
                        var statusText = strings.noAdvanced;

                        if (activeCount === 1) {
                            statusText = strings.oneAdvanced;
                        } else if (activeCount === 2) {
                            statusText = strings.twoAdvanced;
                        }

                        setBadge(advancedStatus, statusText, activeCount > 0 ? 'wpacu-opt-badge--warning' : '');
                        advancedStatus.setAttribute('data-active-count', activeCount);

                        var modernProtocolDetected = root.getAttribute('data-modern-protocol-detected') === '1';
                        if (combineDescription) {
                            combineDescription.textContent = combineDescription.getAttribute(modernProtocolDetected ? 'data-modern' : 'data-default');
                        }

                        if (combineInput && (combineInput.disabled || combineInput.getAttribute('data-status-locked') === '1')) {
                            setBadge(combineStatus, strings.locked, 'wpacu-opt-badge--locked');
                        } else {
                            setBadge(
                                combineStatus,
                                combineEnabled
                                    ? (modernProtocolDetected ? strings.enabledTestCarefully : strings.enabled)
                                    : (modernProtocolDetected ? strings.usuallyUnnecessary : strings.situational),
                                modernProtocolDetected ? 'wpacu-opt-badge--warning' : (combineEnabled ? 'wpacu-opt-badge--on' : '')
                            );
                        }

                        setBadge(dynamicStatus, dynamicEnabled ? strings.enabled : strings.specialized, dynamicEnabled ? 'wpacu-opt-badge--warning' : '');

                        if (activeCount > 0 && advancedPanel) {
                            advancedPanel.open = true;
                        }
                    }

                    function syncAdvancedMenu() {
                        var hasActiveAdvancedSetting = isEffective(inlineInput) || isEffective(combineInput) || isEffective(dynamicInput);

                        if (! advancedMenuCircle) {
                            return;
                        }

                        advancedMenuCircle.classList.toggle('wpacu-advanced', hasActiveAdvancedSetting);
                        advancedMenuCircle.classList.toggle('wpacu-off', ! hasActiveAdvancedSetting);
                        advancedMenuStatus.setAttribute(
                            'title',
                            hasActiveAdvancedSetting
                                ? advancedMenuStatus.getAttribute('data-active-title')
                                : advancedMenuStatus.getAttribute('data-inactive-title')
                        );
                    }

                    [minifyInput, inlineInput, combineInput, dynamicInput].forEach(function (input) {
                        if (! input) {
                            return;
                        }

                        input.addEventListener('change', function () {
                            syncMinify();
                            syncAdvanced();
                            syncAdvancedMenu();
                        });
                    });

                    if (window.jQuery) {
                        window.jQuery(root).on('wpacuModernProtocolDetected.wpacuOptimizeCssStatus', function () {
                            syncAdvanced();
                        });
                        [minifyInput, inlineInput, combineInput, dynamicInput].forEach(function (input) {
                            if (input) {
                                window.jQuery(input).on('tick.wpacuOptimizeCssStatus', function () {
                                    syncMinify();
                                    syncAdvanced();
                                    syncAdvancedMenu();
                                });
                            }
                        });
                    }

                    syncMinify();
                    syncAdvanced();
                    syncAdvancedMenu();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initCssOptimizationStatusSync);
                } else {
                    initCssOptimizationStatusSync();
                }
            }());
        </script>
    </main>
</div>

<?php require WPACU_PLUGIN_DIR . '/templates/_common/modals/css-http-guidance.php'; ?>
