<?php
/*
 * No direct access to this file
 */
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

if (! isset($data)) {
    exit;
}

global $wp_version;

$tabIdArea       = 'wpacu-setting-optimize-js';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea) ? 'style="display: table-cell;"' : '';
$settingsInputName = WPACU_PLUGIN_ID . '_settings';

$isOptimizeJsEnabledByOtherParty = ! empty($data['is_optimize_js_enabled_by_other_party']);
$jsOptimizationOtherParties      = $isOptimizeJsEnabledByOtherParty ? (array) $data['is_optimize_js_enabled_by_other_party'] : array();
$wpRocketIsEnabledWithDelayJs     = wpacuIsDefinedConstant('WPACU_WP_ROCKET_DELAY_JS_ENABLED');
$minifyJsIsLocked                 = $isOptimizeJsEnabledByOtherParty;
$inlineJsIsLocked                 = ! $wpacuOptimizeJsIsPro || $isOptimizeJsEnabledByOtherParty;
$combineJsIsDisabled              = $isOptimizeJsEnabledByOtherParty || $wpRocketIsEnabledWithDelayJs;
$combineJsHasStoredValue          = in_array($data['combine_loaded_js'], array('for_admin', 'for_all', 1, '1'), true);
$combineJsEnabled                 = ! $combineJsIsDisabled && $combineJsHasStoredValue;
$minifyJsEnabled                  = ((int) $data['minify_loaded_js'] === 1);
$inlineJsFilesEnabled             = $wpacuOptimizeJsIsPro && ((int) $data['inline_js_files'] === 1);
$moveInlineJqueryEnabled          = ((int) $data['move_inline_jquery_after_src_tag'] === 1);
$moveScriptsToBodyEnabled         = $wpacuOptimizeJsIsPro && ((int) $data['move_scripts_to_body'] === 1);
$cacheDynamicJsEnabled            = ((int) $data['cache_dynamic_loaded_js'] === 1);
$cacheDynamicJsExceptions         = isset($data['cache_dynamic_loaded_js_exceptions']) ? trim((string) $data['cache_dynamic_loaded_js_exceptions']) : '';
$advancedDeliveryOpen             = $combineJsHasStoredValue || $inlineJsFilesEnabled;
$compatibilityActiveCount         = (int) $moveInlineJqueryEnabled + (int) $moveScriptsToBodyEnabled;
$compatibilityOpen                = ($compatibilityActiveCount > 0);
$testModeSettingsUrl              = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_settings&wpacu_selected_tab_area=wpacu-setting-test-mode');
$assetsManagerUrl                 = admin_url('admin.php?page=wpassetcleanup_assets_manager');

if ($isOptimizeJsEnabledByOtherParty) {
    $advancedStatusText  = __('Advanced delivery is managed by another optimization plugin', 'wp-asset-clean-up');
    $advancedStatusClass = 'wpacu-opt-badge--locked';
} elseif ($combineJsEnabled && $inlineJsFilesEnabled) {
    $advancedStatusText  = __('Combine and inlining are enabled', 'wp-asset-clean-up');
    $advancedStatusClass = 'wpacu-opt-badge--warning';
} elseif ($combineJsEnabled) {
    $advancedStatusText  = __('Combine is enabled', 'wp-asset-clean-up');
    $advancedStatusClass = 'wpacu-opt-badge--warning';
} elseif ($inlineJsFilesEnabled) {
    $advancedStatusText  = $combineJsIsDisabled
        ? __('Inlining is enabled; Combine is locked', 'wp-asset-clean-up')
        : __('Inlining is enabled', 'wp-asset-clean-up');
    $advancedStatusClass = 'wpacu-opt-badge--warning';
} elseif ($combineJsIsDisabled) {
    $advancedStatusText  = __('Combine is currently locked', 'wp-asset-clean-up');
    $advancedStatusClass = 'wpacu-opt-badge--locked';
} else {
    $advancedStatusText  = __('No advanced delivery changes active', 'wp-asset-clean-up');
    $advancedStatusClass = '';
}

$minifyStatusText = $minifyJsIsLocked
    ? __('Managed by another plugin', 'wp-asset-clean-up')
    : ($minifyJsEnabled ? __('Enabled', 'wp-asset-clean-up') : __('Disabled', 'wp-asset-clean-up'));
$minifyStatusClass = $minifyJsIsLocked
    ? 'wpacu-opt-badge--locked'
    : ($minifyJsEnabled ? 'wpacu-opt-badge--on' : '');

$compatibilityStatusText = $compatibilityActiveCount > 0
    ? sprintf(
        _n('%d override active', '%d overrides active', $compatibilityActiveCount, 'wp-asset-clean-up'),
        $compatibilityActiveCount
    )
    : __('No compatibility overrides active', 'wp-asset-clean-up');
?>
<div id="<?php echo esc_attr($tabIdArea); ?>" class="wpacu-settings-tab-content" <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <main id="wpacu-js-optimization-settings" class="wpacu-opt-panel">
        <header class="wpacu-opt-header">
            <div>
                <span class="wpacu-opt-eyebrow"><?php esc_html_e('JavaScript optimization', 'wp-asset-clean-up'); ?></span>
                <h2><?php esc_html_e('Optimize JavaScript Delivery', 'wp-asset-clean-up'); ?></h2>
                <p><?php esc_html_e('Unload unnecessary scripts first, then minify what remains. Combining, inlining, and relocation should be enabled only for a measured benefit or a verified compatibility fix.', 'wp-asset-clean-up'); ?></p>
            </div>
            <span class="wpacu-opt-header-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <?php esc_html_e('Test advanced changes', 'wp-asset-clean-up'); ?>
            </span>
        </header>

        <div class="wpacu-opt-body">
            <div class="wpacu-opt-flow">
                <span class="wpacu-opt-flow-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4l6 8-6 8M13 4h6M13 12h6M13 20h6"/></svg>
                </span>
                <div class="wpacu-opt-flow-copy">
                    <div>
                        <strong><?php esc_html_e('Use the lowest-risk order', 'wp-asset-clean-up'); ?></strong>
                        <p><?php esc_html_e('Remove unused JavaScript, minify the remainder, then test execution-sensitive delivery changes separately.', 'wp-asset-clean-up'); ?></p>
                    </div>
                    <div class="wpacu-opt-flow-steps" aria-label="<?php esc_attr_e('Recommended optimization order', 'wp-asset-clean-up'); ?>">
                        <span class="wpacu-opt-flow-step"><b>1</b><?php esc_html_e('Unload', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-opt-flow-arrow" aria-hidden="true">→</span>
                        <span class="wpacu-opt-flow-step"><b>2</b><?php esc_html_e('Minify', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-opt-flow-arrow" aria-hidden="true">→</span>
                        <span class="wpacu-opt-flow-step"><b>3</b><?php esc_html_e('Test execution', 'wp-asset-clean-up'); ?></span>
                    </div>
                </div>
            </div>

            <section class="wpacu-opt-section wpacu-opt-section--primary" aria-labelledby="wpacuJsMinifySectionTitle">
                <header class="wpacu-opt-section-header">
                    <div class="wpacu-opt-section-heading">
                        <span class="wpacu-opt-section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h9M8 18h5"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>
                        </span>
                        <span>
                            <span class="wpacu-opt-section-kicker"><?php esc_html_e('Recommended for most sites', 'wp-asset-clean-up'); ?></span>
                            <strong id="wpacuJsMinifySectionTitle" class="wpacu-opt-section-title"><?php esc_html_e('Minify the JavaScript that remains', 'wp-asset-clean-up'); ?></strong>
                            <span class="wpacu-opt-section-description"><?php esc_html_e('A comparatively low-risk transfer-size reduction after unnecessary scripts have been unloaded.', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </div>
                    <div class="wpacu-opt-section-meta">
                        <span id="wpacuJsMinifyStatus"
                              class="wpacu-opt-badge <?php echo esc_attr($minifyStatusClass); ?>"
                              data-enabled="<?php esc_attr_e('Enabled', 'wp-asset-clean-up'); ?>"
                              data-disabled="<?php esc_attr_e('Disabled', 'wp-asset-clean-up'); ?>"
                              data-locked="<?php esc_attr_e('Managed by another plugin', 'wp-asset-clean-up'); ?>"><?php echo esc_html($minifyStatusText); ?></span>
                    </div>
                </header>

                <div class="wpacu-opt-section-body">
                    <article class="wpacu-opt-card">
                        <header class="wpacu-opt-card-header wpacu-opt-card-header--switch-first">
                            <span class="wpacu-opt-card-control">
                                <label class="wpacu_switch <?php if ($minifyJsIsLocked) { echo 'wpacu_disabled'; } ?>">
                                    <input id="wpacu_minify_js_enable"
                                           data-target-opacity="#wpacu_minify_js_area"
                                           data-status-locked="<?php echo $minifyJsIsLocked ? '1' : '0'; ?>"
                                           type="checkbox"
                                        <?php checked($minifyJsEnabled); ?>
                                           name="<?php echo esc_attr($settingsInputName); ?>[minify_loaded_js]"
                                           value="1" />
                                    <span class="wpacu_slider wpacu_round"></span>
                                </label>
                            </span>
                            <span class="wpacu-opt-card-copy">
                                <strong class="wpacu-opt-card-title"><?php esc_html_e('Minify JavaScript', 'wp-asset-clean-up'); ?></strong>
                                <span class="wpacu-opt-card-description"><?php esc_html_e('Minifies remaining enqueued scripts and serves eligible output from the Asset CleanUp cache.', 'wp-asset-clean-up'); ?></span>
                            </span>
                        </header>

                        <div class="wpacu-opt-card-body">
                            <?php if ($minifyJsIsLocked) { ?>
                                <div class="wpacu-opt-alert wpacu-opt-alert--locked">
                                    <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                                    <div>
                                        <strong><?php echo wp_kses_post(sprintf(
                                            __('JavaScript optimization is already managed by <em>%s</em>.', 'wp-asset-clean-up'),
                                            esc_html(implode(', ', $jsOptimizationOtherParties))
                                        )); ?></strong>
                                        <p><?php echo wp_kses_post(sprintf(
                                            __('Unload unnecessary scripts in the <a href="%s">CSS &amp; JavaScript Manager</a>, then let the existing optimizer minify what remains.', 'wp-asset-clean-up'),
                                            esc_url($assetsManagerUrl)
                                        )); ?></p>
                                    </div>
                                </div>
                            <?php } ?>

                            <div id="wpacu_minify_js_area" class="wpacu-opt-dependent" style="<?php echo esc_attr(! $minifyJsIsLocked && $minifyJsEnabled ? 'opacity: 1;' : 'opacity: 0.4;'); ?>">
                                <fieldset class="wpacu-opt-fieldset">
                                    <legend><?php esc_html_e('Minify', 'wp-asset-clean-up'); ?></legend>
                                    <div class="wpacu-opt-choice-grid">
                                        <label class="wpacu-opt-choice" for="minify_loaded_js_for_script_src_radio">
                                            <input id="minify_loaded_js_for_script_src_radio"
                                                <?php checked(in_array($data['minify_loaded_js_for'], array('src', ''), true)); ?>
                                                   type="radio"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[minify_loaded_js_for]"
                                                   value="src" />
                                            <span class="wpacu-opt-choice-copy">
                                                <strong><?php esc_html_e('External JavaScript files', 'wp-asset-clean-up'); ?></strong>
                                                <small><?php esc_html_e('SCRIPT tags with a src attribute.', 'wp-asset-clean-up'); ?></small>
                                                <span class="wpacu-opt-choice-tag"><?php esc_html_e('Recommended', 'wp-asset-clean-up'); ?></span>
                                            </span>
                                        </label>
                                        <label class="wpacu-opt-choice is-aggressive" for="minify_loaded_js_for_script_inline_radio">
                                            <input id="minify_loaded_js_for_script_inline_radio"
                                                <?php checked($data['minify_loaded_js_for'] === 'inline'); ?>
                                                   type="radio"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[minify_loaded_js_for]"
                                                   value="inline" />
                                            <span class="wpacu-opt-choice-copy">
                                                <strong><?php esc_html_e('Inline JavaScript only', 'wp-asset-clean-up'); ?></strong>
                                                <small><?php esc_html_e('Code inside SCRIPT tags; more compatibility-sensitive.', 'wp-asset-clean-up'); ?></small>
                                            </span>
                                        </label>
                                        <label class="wpacu-opt-choice is-aggressive" for="minify_loaded_js_for_script_all_radio">
                                            <input id="minify_loaded_js_for_script_all_radio"
                                                <?php checked($data['minify_loaded_js_for'] === 'all'); ?>
                                                   type="radio"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[minify_loaded_js_for]"
                                                   value="all" />
                                            <span class="wpacu-opt-choice-copy">
                                                <strong><?php esc_html_e('Files and inline JavaScript', 'wp-asset-clean-up'); ?></strong>
                                                <small><?php esc_html_e('Processes both sources; test interactive behavior and the console.', 'wp-asset-clean-up'); ?></small>
                                            </span>
                                        </label>
                                    </div>
                                </fieldset>

                                <div id="wpacu_minify_js_exceptions_area" class="wpacu-opt-field">
                                    <label class="wpacu-opt-field-label" for="wpacu_minify_js_exceptions">
                                        <span><?php esc_html_e('Minification exclusions', 'wp-asset-clean-up'); ?></span>
                                        <small><?php esc_html_e('One path or pattern per line', 'wp-asset-clean-up'); ?></small>
                                    </label>
                                    <textarea class="wpacu-opt-textarea"
                                              rows="4"
                                              id="wpacu_minify_js_exceptions"
                                              name="<?php echo esc_attr($settingsInputName); ?>[minify_loaded_js_exceptions]"
                                              placeholder="Example:&#10;/(.*?).min.js&#10;/wd-instagram-feed/(.*?).js"><?php echo esc_textarea($data['minify_loaded_js_exceptions']); ?></textarea>
                                </div>

                                <div class="wpacu-opt-note">
                                    <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
                                    <span><?php echo wp_kses_post(__('Cached JavaScript is regenerated when the source version changes, and the <code>?ver=</code> value is appended to the generated filename.', 'wp-asset-clean-up')); ?></span>
                                </div>

                                <details class="wpacu-opt-disclosure">
                                    <summary><?php esc_html_e('Files automatically skipped by the minifier', 'wp-asset-clean-up'); ?></summary>
                                    <div class="wpacu-opt-disclosure-content">
                                        <p><?php esc_html_e('Asset CleanUp avoids duplicate work for known WordPress files that are already optimized.', 'wp-asset-clean-up'); ?></p>
                                        <ul>
                                            <li><?php echo wp_kses_post(__('WordPress core files ending in <code>.min.js</code>, such as <code>jquery-migrate.min.js</code> and UI components.', 'wp-asset-clean-up')); ?></li>
                                            <li><?php echo wp_kses_post(__('The WordPress jQuery library from <code>/wp-includes/js/jquery/jquery.js</code>.', 'wp-asset-clean-up')); ?></li>
                                        </ul>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <details id="wpacu-js-advanced-delivery" class="wpacu-opt-details" <?php if ($advancedDeliveryOpen) { echo 'open'; } ?>>
                <summary>
                    <span class="wpacu-opt-details-summary-copy">
                        <span class="wpacu-opt-section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h10M4 18h7"/><path d="M18 10v8M14 14h8"/></svg>
                        </span>
                        <span>
                            <span class="wpacu-opt-section-kicker"><?php esc_html_e('Advanced delivery', 'wp-asset-clean-up'); ?></span>
                            <strong class="wpacu-opt-section-title"><?php esc_html_e('Combine or inline selected JavaScript', 'wp-asset-clean-up'); ?></strong>
                            <span class="wpacu-opt-section-description"><?php esc_html_e('These options may reduce requests, but can reduce cache reuse or alter execution. Test one change at a time.', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </span>
                    <span class="wpacu-opt-details-meta">
                        <span class="wpacu-opt-badge wpacu-opt-badge--advanced"><?php esc_html_e('Advanced', 'wp-asset-clean-up'); ?></span>
                        <span id="wpacuJsAdvancedStatus"
                              class="wpacu-opt-badge <?php echo esc_attr($advancedStatusClass); ?>"
                              data-none="<?php esc_attr_e('No advanced delivery changes active', 'wp-asset-clean-up'); ?>"
                              data-combine="<?php esc_attr_e('Combine is enabled', 'wp-asset-clean-up'); ?>"
                              data-inline="<?php esc_attr_e('Inlining is enabled', 'wp-asset-clean-up'); ?>"
                              data-both="<?php esc_attr_e('Combine and inlining are enabled', 'wp-asset-clean-up'); ?>"
                              data-locked="<?php esc_attr_e('Combine is currently locked', 'wp-asset-clean-up'); ?>"
                              data-managed="<?php esc_attr_e('Advanced delivery is managed by another optimization plugin', 'wp-asset-clean-up'); ?>"
                              data-inline-locked="<?php esc_attr_e('Inlining is enabled; Combine is locked', 'wp-asset-clean-up'); ?>"><?php echo esc_html($advancedStatusText); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 wpacu-opt-details-arrow" aria-hidden="true"></span>
                    </span>
                </summary>

                <div class="wpacu-opt-details-body">
                    <div class="wpacu-opt-stack">
                        <?php if (Misc::isWpRocketMinifyHtmlEnabled()) { ?>
                            <div class="wpacu-opt-alert wpacu-opt-alert--warning">
                                <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                <div>
                                    <strong><?php esc_html_e('Overlapping WP Rocket setting detected', 'wp-asset-clean-up'); ?></strong>
                                    <p><?php echo wp_kses_post(__('Combine JavaScript does not take effect while <strong>Minify HTML</strong> is active in WP Rocket. Keep one JavaScript optimization layer and use Asset CleanUp to unload unnecessary files.', 'wp-asset-clean-up')); ?></p>
                                </div>
                            </div>
                        <?php } ?>

                        <article class="wpacu-opt-card">
                            <header class="wpacu-opt-card-header wpacu-opt-card-header--switch-first">
                                <span class="wpacu-opt-card-control">
                                    <label class="wpacu_switch <?php if ($combineJsIsDisabled) { echo 'wpacu_disabled'; } ?>">
                                        <input id="wpacu_combine_loaded_js_enable"
                                               data-target-opacity="#wpacu_combine_loaded_js_info_area"
                                               data-status-locked="<?php echo $combineJsIsDisabled ? '1' : '0'; ?>"
                                               type="checkbox"
                                            <?php
                                            if ($combineJsIsDisabled) {
                                                echo 'disabled="disabled"';
                                            } else {
                                                checked($combineJsHasStoredValue);
                                            }
                                            ?>
                                               name="<?php echo esc_attr($settingsInputName); ?>[combine_loaded_js]"
                                               value="1" />
                                        <span class="wpacu_slider wpacu_round"></span>
                                    </label>
                                </span>
                                <span class="wpacu-opt-card-copy">
                                    <strong class="wpacu-opt-card-title"><?php esc_html_e('Combine JavaScript files', 'wp-asset-clean-up'); ?></strong>
                                    <span id="wpacuJsCombineDescription"
                                          class="wpacu-opt-card-description"
                                          data-default="<?php esc_attr_e('Creates larger cached groups while preserving compatible HEAD/BODY and loading-strategy boundaries.', 'wp-asset-clean-up'); ?>"
                                          data-modern="<?php esc_attr_e('Optional on this server. Enable only after testing a measurable improvement.', 'wp-asset-clean-up'); ?>"><?php esc_html_e('Creates larger cached groups while preserving compatible HEAD/BODY and loading-strategy boundaries.', 'wp-asset-clean-up'); ?></span>
                                </span>
                                <span class="wpacu-opt-card-meta">
                                    <span id="wpacuJsCombineState" class="wpacu-opt-badge <?php echo $combineJsIsDisabled ? 'wpacu-opt-badge--locked' : ($combineJsEnabled ? 'wpacu-opt-badge--warning' : ''); ?>">
                                        <?php echo $combineJsIsDisabled ? esc_html__('Locked', 'wp-asset-clean-up') : ($combineJsEnabled ? esc_html__('Enabled', 'wp-asset-clean-up') : esc_html__('Situational', 'wp-asset-clean-up')); ?>
                                    </span>
                                </span>
                            </header>
                            <div class="wpacu-opt-card-body">
                                <div class="wpacu-combine-notice-default wpacu_hide wpacu-opt-note">
                                    <span class="dashicons dashicons-info" aria-hidden="true"></span>
                                    <span>
                                        <strong><?php esc_html_e('Modern connection guidance:', 'wp-asset-clean-up'); ?></strong>
                                        <?php esc_html_e('Combining JavaScript is usually unnecessary on HTTP/2 or HTTP/3 and can reduce browser cache reuse.', 'wp-asset-clean-up'); ?>
                                        <a data-wpacu-modal-target="wpacu-http2-info-js-target" href="#wpacu-http2-info-js"><?php esc_html_e('Read more', 'wp-asset-clean-up'); ?></a>
                                        · <a class="wpacu_verify_http2_protocol" target="_blank" rel="noopener" href="https://tools.keycdn.com/http2-test"><?php esc_html_e('External HTTP/2 check', 'wp-asset-clean-up'); ?></a>
                                        <span class="wpacu-http-protocol-check-status" aria-live="polite"></span>
                                    </span>
                                </div>
                                <div class="wpacu-combine-notice-http-2-detected wpacu-opt-protocol-result wpacu_hide">
                                    <span class="wpacu-opt-protocol-badge"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <span class="wpacu-http-protocol-label">HTTP/2</span></span>
                                    <span><?php echo wp_kses_post(sprintf(__('<strong>Modern protocol detected.</strong> The server-side check for %s indicates that request-count reduction alone is unlikely to justify JavaScript combination.', 'wp-asset-clean-up'), esc_html(get_site_url()))); ?></span>
                                    <a class="wpacu-opt-protocol-link" data-wpacu-modal-target="wpacu-http2-info-js-target" href="#wpacu-http2-info-js"><?php esc_html_e('Why?', 'wp-asset-clean-up'); ?></a>
                                </div>

                                <?php if ($isOptimizeJsEnabledByOtherParty) { ?>
                                    <div class="wpacu-opt-alert wpacu-opt-alert--locked">
                                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                                        <div>
                                            <strong><?php echo wp_kses_post(sprintf(
                                                __('JavaScript optimization is already managed by <em>%s</em>.', 'wp-asset-clean-up'),
                                                esc_html(implode(', ', $jsOptimizationOtherParties))
                                            )); ?></strong>
                                            <p><?php esc_html_e('Unload unnecessary JavaScript with Asset CleanUp, then use one optimization layer for combination or delivery.', 'wp-asset-clean-up'); ?></p>
                                        </div>
                                    </div>
                                <?php } elseif ($wpRocketIsEnabledWithDelayJs) { ?>
                                    <div class="wpacu-opt-alert wpacu-opt-alert--locked">
                                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                                        <div>
                                            <strong><?php esc_html_e('Combine is locked to protect execution order', 'wp-asset-clean-up'); ?></strong>
                                            <p><?php echo wp_kses_post(sprintf(
                                                __('WP Rocket <a target="_blank" rel="noopener" href="%s">Delay JavaScript execution</a> is active. Avoid mixing that feature with JavaScript combination.', 'wp-asset-clean-up'),
                                                esc_url(admin_url('options-general.php?page=wprocket#file_optimization'))
                                            )); ?></p>
                                        </div>
                                    </div>
                                <?php } ?>

                                <div id="wpacu_combine_loaded_js_info_area" class="wpacu-opt-dependent" style="<?php echo esc_attr($combineJsEnabled ? 'opacity: 1;' : 'opacity: 0.4;'); ?>">
                                    <fieldset class="wpacu-opt-fieldset">
                                        <legend><?php esc_html_e('Apply combination to', 'wp-asset-clean-up'); ?></legend>
                                        <div class="wpacu-opt-choice-grid wpacu-opt-choice-grid--2">
                                            <label class="wpacu-opt-choice" for="combine_loaded_js_for_guests_radio">
                                                <input id="combine_loaded_js_for_guests_radio"
                                                    <?php checked(in_array($data['combine_loaded_js_for'], array('guests', ''), true)); ?>
                                                       type="radio"
                                                       name="<?php echo esc_attr($settingsInputName); ?>[combine_loaded_js_for]"
                                                       value="guests" />
                                                <span class="wpacu-opt-choice-copy">
                                                    <strong><?php esc_html_e('Guest visitors only', 'wp-asset-clean-up'); ?></strong>
                                                    <small><?php esc_html_e('Keeps logged-in traffic on the original files.', 'wp-asset-clean-up'); ?></small>
                                                    <span class="wpacu-opt-choice-tag"><?php esc_html_e('Default', 'wp-asset-clean-up'); ?></span>
                                                </span>
                                            </label>
                                            <label class="wpacu-opt-choice is-aggressive" for="combine_loaded_js_for_all_radio">
                                                <input id="combine_loaded_js_for_all_radio"
                                                    <?php checked($data['combine_loaded_js_for'] === 'all'); ?>
                                                       type="radio"
                                                       name="<?php echo esc_attr($settingsInputName); ?>[combine_loaded_js_for]"
                                                       value="all" />
                                                <span class="wpacu-opt-choice-copy">
                                                    <strong><?php esc_html_e('Logged-in and guest visitors', 'wp-asset-clean-up'); ?></strong>
                                                    <small><?php esc_html_e('Use temporarily with Test Mode for private testing.', 'wp-asset-clean-up'); ?></small>
                                                </span>
                                            </label>
                                        </div>
                                    </fieldset>

                                    <div class="wpacu-opt-check-grid">
                                        <label class="wpacu-opt-check" for="wpacu_combine_loaded_js_defer_body_checkbox">
                                            <input id="wpacu_combine_loaded_js_defer_body_checkbox"
                                                <?php checked($data['combine_loaded_js_defer_body'] == 1); ?>
                                                   type="checkbox"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[combine_loaded_js_defer_body]"
                                                   value="1" />
                                            <span><strong><?php esc_html_e('Defer BODY groups', 'wp-asset-clean-up'); ?></strong><?php echo wp_kses_post(__('Adds <code>defer</code> to generated groups created from BODY scripts.', 'wp-asset-clean-up')); ?></span>
                                        </label>
                                        <label class="wpacu-opt-check" for="combine_loaded_js_try_catch_checkbox">
                                            <input id="combine_loaded_js_try_catch_checkbox"
                                                <?php checked($data['combine_loaded_js_try_catch'] == 1); ?>
                                                   type="checkbox"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[combine_loaded_js_try_catch]"
                                                   value="1" />
                                            <span><strong><?php esc_html_e('Troubleshooting wrapper', 'wp-asset-clean-up'); ?></strong><?php esc_html_e('Wraps each source in try/catch. It can hide the original error, so use it only while diagnosing.', 'wp-asset-clean-up'); ?></span>
                                        </label>
                                    </div>

                                    <div id="wpacu_combine_loaded_js_exceptions_area" class="wpacu-opt-field">
                                        <label class="wpacu-opt-field-label" for="combine_loaded_js_exceptions">
                                            <span><?php esc_html_e('Combination exclusions', 'wp-asset-clean-up'); ?></span>
                                            <small><?php esc_html_e('One path or pattern per line', 'wp-asset-clean-up'); ?></small>
                                        </label>
                                        <textarea class="wpacu-opt-textarea"
                                                  rows="4"
                                                  id="combine_loaded_js_exceptions"
                                                  name="<?php echo esc_attr($settingsInputName); ?>[combine_loaded_js_exceptions]"
                                                  placeholder="Example:&#10;/wp-includes/js/admin-bar.min.js&#10;/wp-content/plugins/plugin-title/js/(.*?).js"><?php echo esc_textarea($data['combine_loaded_js_exceptions']); ?></textarea>
                                    </div>

                                    <div class="wpacu-opt-note wpacu-opt-note--caution">
                                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                        <span><strong><?php esc_html_e('Test interactive functionality and the browser console.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Associated inline code is moved with its script group, and larger bundles can reduce cache reuse.', 'wp-asset-clean-up'); ?></span>
                                    </div>

                                    <div class="wpacu-opt-actions">
                                        <a class="wpacu-opt-action-link" data-wpacu-modal-target="wpacu-combine-js-method-info-target" href="#wpacu-combine-js-method-info">
                                            <span class="dashicons dashicons-info" aria-hidden="true"></span>
                                            <?php esc_html_e('How files are grouped', 'wp-asset-clean-up'); ?>
                                        </a>
                                        <a class="wpacu-opt-action-link" target="_blank" rel="noopener" href="<?php echo esc_url($testModeSettingsUrl); ?>">
                                            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                            <?php esc_html_e('Open Test Mode', 'wp-asset-clean-up'); ?>
                                        </a>
                                    </div>

                                    <details class="wpacu-opt-disclosure">
                                        <summary><?php esc_html_e('Cache location, patterns, and skipped requests', 'wp-asset-clean-up'); ?></summary>
                                        <div class="wpacu-opt-disclosure-content">
                                            <p><?php echo wp_kses_post(sprintf(
                                                __('Generated files are stored under <code>%s</code>.', 'wp-asset-clean-up'),
                                                esc_html(str_replace(dirname(WP_CONTENT_DIR), '', WP_CONTENT_DIR) . OptimizeCommon::getRelPathPluginCacheDir() . 'js/')
                                            )); ?></p>
                                            <span class="wpacu-opt-code-examples">/wp-includes/js/admin-bar.min.js<br>/wp-includes/js/masonry.min.js<br>/wp-content/plugins/plugin-title/js/(.*?).js</span>
                                            <ul>
                                                <li><?php esc_html_e('Combine is skipped for unauthorized visitors while Test Mode is active.', 'wp-asset-clean-up'); ?></li>
                                                <li><?php esc_html_e('It can be skipped for query-string URLs, POST requests, Dashboard requests, and non-standard front-end requests.', 'wp-asset-clean-up'); ?></li>
                                                <li><?php esc_html_e('Scripts with defer or async remain separated from ordinary render-blocking scripts.', 'wp-asset-clean-up'); ?></li>
                                            </ul>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </article>

                        <article class="wpacu-opt-card<?php echo ! $wpacuOptimizeJsIsPro ? ' wpacu-opt-card--pro-only' : ''; ?>">
                            <?php if (! $wpacuOptimizeJsIsPro) { ?>
                            <span class="wpacu-opt-badge wpacu-opt-badge--locked wpacu-opt-pro-only-legend"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e('Pro only', 'wp-asset-clean-up'); ?></span>
                            <?php } ?>
                            <header class="wpacu-opt-card-header wpacu-opt-card-header--switch-first">
                                <span class="wpacu-opt-card-control">
                                    <label class="wpacu_switch <?php if ($inlineJsIsLocked) { echo 'wpacu_disabled'; } ?>">
                                        <input id="wpacu_inline_js_files_enable"
                                               data-target-opacity="#wpacu_inline_js_files_info_area"
                                               data-status-locked="<?php echo $inlineJsIsLocked ? '1' : '0'; ?>"
                                               type="checkbox"
                                            <?php
                                            if ($inlineJsIsLocked) {
                                                echo 'disabled="disabled"';
                                            } else {
                                                checked($inlineJsFilesEnabled);
                                            }
                                            ?>
                                               name="<?php echo esc_attr($settingsInputName); ?>[inline_js_files]"
                                               value="1" />
                                        <span class="wpacu_slider wpacu_round"></span>
                                    </label>
                                </span>
                                <span class="wpacu-opt-card-copy">
                                    <strong class="wpacu-opt-card-title"><?php esc_html_e('Inline local JavaScript files', 'wp-asset-clean-up'); ?></strong>
                                    <span class="wpacu-opt-card-description"><?php esc_html_e('Removes a request for selected small same-origin scripts, but increases HTML and prevents cross-page cache reuse.', 'wp-asset-clean-up'); ?></span>
                                </span>
                                <?php if ($wpacuOptimizeJsIsPro) { ?>
                                <span class="wpacu-opt-card-meta">
                                    <span id="wpacuJsInlineState" class="wpacu-opt-badge <?php echo $inlineJsIsLocked ? 'wpacu-opt-badge--locked' : ($inlineJsFilesEnabled ? 'wpacu-opt-badge--warning' : ''); ?>">
                                        <?php echo $inlineJsIsLocked ? esc_html__('Locked', 'wp-asset-clean-up') : ($inlineJsFilesEnabled ? esc_html__('Enabled', 'wp-asset-clean-up') : esc_html__('Advanced', 'wp-asset-clean-up')); ?>
                                    </span>
                                </span>
                                <?php } ?>
                            </header>
                            <div class="wpacu-opt-card-body">
                                <?php if ($wpacuOptimizeJsIsPro && $inlineJsIsLocked) { ?>
                                    <div class="wpacu-opt-alert wpacu-opt-alert--locked">
                                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                                        <div>
                                            <strong><?php echo wp_kses_post(sprintf(
                                                __('JavaScript optimization is already managed by <em>%s</em>.', 'wp-asset-clean-up'),
                                                esc_html(implode(', ', $jsOptimizationOtherParties))
                                            )); ?></strong>
                                            <p><?php esc_html_e('Use one JavaScript optimization layer after unloading unnecessary scripts.', 'wp-asset-clean-up'); ?></p>
                                        </div>
                                    </div>
                                <?php } ?>

                                <div id="wpacu_inline_js_files_info_area" class="wpacu-opt-dependent" style="<?php echo esc_attr(! $inlineJsIsLocked && $inlineJsFilesEnabled ? 'opacity: 1;' : 'opacity: 0.4;'); ?>">
                                    <div class="wpacu-opt-note wpacu-opt-note--caution">
                                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                        <span><?php esc_html_e('Inlining JavaScript is more compatibility-sensitive than inlining CSS. Async/defer behavior, execution order, and Content Security Policy can change the result.', 'wp-asset-clean-up'); ?></span>
                                    </div>

                                    <div class="wpacu-opt-inline-control">
                                        <label for="wpacu_inline_js_files_below_size_checkbox">
                                            <input id="wpacu_inline_js_files_below_size_checkbox"
                                                <?php checked($data['inline_js_files_below_size'] == 1); ?>
                                                   type="checkbox"
                                                   name="<?php echo esc_attr($settingsInputName); ?>[inline_js_files_below_size]"
                                                   value="1" />
                                            <span><?php esc_html_e('Automatically inline files smaller than', 'wp-asset-clean-up'); ?></span>
                                        </label>
                                        <input type="number"
                                               min="1"
                                               aria-label="<?php esc_attr_e('Maximum JavaScript file size in kilobytes', 'wp-asset-clean-up'); ?>"
                                               name="<?php echo esc_attr($settingsInputName); ?>[inline_js_files_below_size_input]"
                                               value="<?php echo esc_attr($data['inline_js_files_below_size_input']); ?>" />
                                        <span><?php esc_html_e('KB', 'wp-asset-clean-up'); ?></span>
                                    </div>

                                    <div id="wpacu_inline_js_files_list_area" class="wpacu-opt-field">
                                        <label class="wpacu-opt-field-label" for="wpacu_inline_js_files_list">
                                            <span><?php esc_html_e('Specific files or matching fragments', 'wp-asset-clean-up'); ?></span>
                                            <small><?php esc_html_e('Optional; one per line', 'wp-asset-clean-up'); ?></small>
                                        </label>
                                        <textarea class="wpacu-opt-textarea"
                                                  rows="4"
                                                  id="wpacu_inline_js_files_list"
                                                  name="<?php echo esc_attr($settingsInputName); ?>[inline_js_files_list]"
                                                  placeholder="Example:&#10;/wp-content/plugins/plugin-title/scripts/small-file.js&#10;/wp-content/themes/my-theme/js/small.js"><?php echo esc_textarea($data['inline_js_files_list']); ?></textarea>
                                    </div>

                                    <details class="wpacu-opt-disclosure">
                                        <summary><?php esc_html_e('Path, RegEx, and caching guidance', 'wp-asset-clean-up'); ?></summary>
                                        <div class="wpacu-opt-disclosure-content">
                                            <p><?php echo wp_kses_post(sprintf(
                                                __('Enter original JavaScript sources, not cached files usually stored under <code>%s</code>. Relative paths are recommended. Regular expressions are accepted; the hash character is added automatically as the delimiter.', 'wp-asset-clean-up'),
                                                esc_html(wp_make_link_relative(content_url()) . OptimizeCommon::getRelPathPluginCacheDir())
                                            )); ?></p>
                                            <p><?php esc_html_e('Files using async or defer are wrapped to run after DOMContentLoaded. Test execution order and browser-console output.', 'wp-asset-clean-up'); ?></p>
                                            <span class="wpacu-opt-code-examples">/wp-content/plugins/plugin-title/scripts/small-file.js<br>/wp-content/themes/my-theme/js/small.js</span>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </details>

            <details id="wpacu-js-compatibility" class="wpacu-opt-details" <?php if ($compatibilityOpen) { echo 'open'; } ?>>
                <summary>
                    <span class="wpacu-opt-details-summary-copy">
                        <span class="wpacu-opt-section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.7 5.7L3 18v3h3l6-6a4 4 0 0 0 5.7-5.7l-2.4 2.4-3-3z"/></svg>
                        </span>
                        <span>
                            <span class="wpacu-opt-section-kicker"><?php esc_html_e('Compatibility & troubleshooting', 'wp-asset-clean-up'); ?></span>
                            <strong class="wpacu-opt-section-title"><?php esc_html_e('Execution-order overrides', 'wp-asset-clean-up'); ?></strong>
                            <span class="wpacu-opt-section-description"><?php esc_html_e('Use these only to correct a verified problem, then retest after plugin or theme updates.', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </span>
                    <span class="wpacu-opt-details-meta">
                        <span id="wpacuJsCompatibilityStatus"
                              class="wpacu-opt-badge <?php echo $compatibilityActiveCount > 0 ? 'wpacu-opt-badge--warning' : ''; ?>"
                              data-zero="<?php esc_attr_e('No compatibility overrides active', 'wp-asset-clean-up'); ?>"
                              data-one="<?php esc_attr_e('1 override active', 'wp-asset-clean-up'); ?>"
                              data-two="<?php esc_attr_e('2 overrides active', 'wp-asset-clean-up'); ?>"><?php echo esc_html($compatibilityStatusText); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 wpacu-opt-details-arrow" aria-hidden="true"></span>
                    </span>
                </summary>

                <div class="wpacu-opt-details-body">
                    <div class="wpacu-opt-card-grid wpacu-opt-card-grid--2">
                        <article class="wpacu-opt-card">
                            <header class="wpacu-opt-card-header wpacu-opt-card-header--switch-first">
                                <span class="wpacu-opt-card-control">
                                    <label class="wpacu_switch">
                                        <input id="wpacu_move_inline_jquery_after_src_tag_enable"
                                               data-target-opacity="#wpacu_move_inline_jquery_after_src_tag_info_area"
                                               type="checkbox"
                                            <?php checked($moveInlineJqueryEnabled); ?>
                                               name="<?php echo esc_attr($settingsInputName); ?>[move_inline_jquery_after_src_tag]"
                                               value="1" />
                                        <span class="wpacu_slider wpacu_round"></span>
                                    </label>
                                </span>
                                <span class="wpacu-opt-card-copy">
                                    <strong class="wpacu-opt-card-title"><?php esc_html_e('Move inline jQuery after the library', 'wp-asset-clean-up'); ?></strong>
                                    <span class="wpacu-opt-card-description"><?php esc_html_e('Compatibility fix for a verified “jQuery is not defined” execution-order error.', 'wp-asset-clean-up'); ?></span>
                                </span>
                            </header>
                            <div class="wpacu-opt-card-body">
                                <div class="wpacu-opt-actions">
                                    <a class="wpacu-opt-action-link" data-wpacu-modal-target="wpacu-move-inline-jquery-target" href="#wpacu-move-inline-jquery">
                                        <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                        <?php esc_html_e('View before/after examples', 'wp-asset-clean-up'); ?>
                                    </a>
                                </div>
                                <div id="wpacu_move_inline_jquery_after_src_tag_info_area" class="wpacu-opt-dependent" style="<?php echo esc_attr($moveInlineJqueryEnabled ? 'opacity: 1;' : 'opacity: 0.4;'); ?>">
                                    <details class="wpacu-opt-disclosure">
                                        <summary><?php esc_html_e('When this override can help', 'wp-asset-clean-up'); ?></summary>
                                        <div class="wpacu-opt-disclosure-content">
                                            <p><?php echo wp_kses_post(__('It moves qualifying inline <code>&lt;SCRIPT&gt;</code> tags after the tag generated for the <code>jquery-core</code> handle.', 'wp-asset-clean-up')); ?></p>
                                            <ul>
                                                <li><?php esc_html_e('The jquery-core handle was moved to BODY and hardcoded inline jQuery runs too early.', 'wp-asset-clean-up'); ?></li>
                                                <li><?php echo wp_kses_post(__('A plugin or theme prints inline jQuery before the library instead of using <code>wp_add_inline_script()</code> with the correct dependency.', 'wp-asset-clean-up')); ?></li>
                                            </ul>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </article>

                        <article class="wpacu-opt-card<?php echo ! $wpacuOptimizeJsIsPro ? ' wpacu-opt-card--pro-only' : ''; ?>">
                            <?php if (! $wpacuOptimizeJsIsPro) { ?>
                            <span class="wpacu-opt-badge wpacu-opt-badge--locked wpacu-opt-pro-only-legend"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e('Pro only', 'wp-asset-clean-up'); ?></span>
                            <?php } ?>
                            <header class="wpacu-opt-card-header wpacu-opt-card-header--switch-first">
                                <span class="wpacu-opt-card-control">
                                    <label class="wpacu_switch <?php if (! $wpacuOptimizeJsIsPro) { echo 'wpacu_disabled'; } ?>">
                                        <input id="wpacu_move_scripts_to_body_enable"
                                               data-target-opacity="#wpacu_move_scripts_to_body_info_area"
                                               data-status-locked="<?php echo $wpacuOptimizeJsIsPro ? '0' : '1'; ?>"
                                               type="checkbox"
                                            <?php
                                            if (! $wpacuOptimizeJsIsPro) {
                                                echo 'disabled="disabled"';
                                            } else {
                                                checked($moveScriptsToBodyEnabled);
                                            }
                                            ?>
                                               name="<?php echo esc_attr($settingsInputName); ?>[move_scripts_to_body]"
                                               value="1" />
                                        <span class="wpacu_slider wpacu_round"></span>
                                    </label>
                                </span>
                                <span class="wpacu-opt-card-copy">
                                    <strong class="wpacu-opt-card-title"><?php echo wp_kses_post(__('Move <code>&lt;SCRIPT&gt;</code> tags from HEAD to BODY', 'wp-asset-clean-up')); ?></strong>
                                    <span class="wpacu-opt-card-description"><?php esc_html_e('Aggressive relocation. It is not equivalent to defer and can change execution order.', 'wp-asset-clean-up'); ?></span>
                                </span>
                            </header>
                            <div class="wpacu-opt-card-body">
                                <div class="wpacu-opt-actions">
                                    <a class="wpacu-opt-action-link" data-wpacu-modal-target="wpacu-move-scripts-to-body-examples-target" href="#wpacu-move-scripts-to-body-examples">
                                        <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                        <?php esc_html_e('View before/after examples', 'wp-asset-clean-up'); ?>
                                    </a>
                                </div>
                                <div id="wpacu_move_scripts_to_body_info_area" class="wpacu-opt-dependent" style="<?php echo esc_attr($moveScriptsToBodyEnabled ? 'opacity: 1;' : 'opacity: 0.4;'); ?>">
                                    <div class="wpacu-opt-field">
                                        <label class="wpacu-opt-field-label" for="wpacu_move_scripts_to_body_exceptions">
                                            <span><?php esc_html_e('Keep matching scripts in HEAD', 'wp-asset-clean-up'); ?></span>
                                            <small><?php esc_html_e('One unique string per line', 'wp-asset-clean-up'); ?></small>
                                        </label>
                                        <textarea class="wpacu-opt-textarea"
                                                  rows="4"
                                                  id="wpacu_move_scripts_to_body_exceptions"
                                                  name="<?php echo esc_attr($settingsInputName); ?>[move_scripts_to_body_exceptions]"
                                                  placeholder="Example:&#10;//cdn.ampproject.org/"><?php echo esc_textarea($data['move_scripts_to_body_exceptions']); ?></textarea>
                                    </div>
                                    <div class="wpacu-opt-note wpacu-opt-note--caution">
                                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                        <span><?php esc_html_e('Some scripts must remain in HEAD. Use a unique fragment from the original SCRIPT tag to exclude them.', 'wp-asset-clean-up'); ?></span>
                                    </div>
                                    <details class="wpacu-opt-disclosure">
                                        <summary><?php esc_html_e('When this override can help', 'wp-asset-clean-up'); ?></summary>
                                        <div class="wpacu-opt-disclosure-content">
                                            <ul>
                                                <li><?php esc_html_e('A small number of hardcoded SCRIPT tags cannot be managed through the CSS/JS Manager and have been confirmed safe to relocate.', 'wp-asset-clean-up'); ?></li>
                                                <li><?php esc_html_e('A theme or plugin hardcodes or re-enqueues jQuery in HEAD incorrectly and ordinary WordPress loading controls cannot be used reliably.', 'wp-asset-clean-up'); ?></li>
                                            </ul>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </details>

            <details id="wpacu-js-generated-cache" class="wpacu-opt-details" <?php if ($cacheDynamicJsEnabled) { echo 'open'; } ?>>
                <summary>
                    <span class="wpacu-opt-details-summary-copy">
                        <span class="wpacu-opt-section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16v10H4z"/><path d="M8 3v4M16 3v4M8 17v4M16 17v4"/></svg>
                        </span>
                        <span>
                            <span class="wpacu-opt-section-kicker"><?php esc_html_e('Specialized caching', 'wp-asset-clean-up'); ?></span>
                            <strong class="wpacu-opt-section-title"><?php esc_html_e('Generated JavaScript cache', 'wp-asset-clean-up'); ?></strong>
                            <span class="wpacu-opt-section-description"><?php esc_html_e('For public JavaScript responses generated by WordPress or plugin PHP endpoints, not ordinary .js files.', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </span>
                    <span class="wpacu-opt-details-meta">
                        <span id="wpacuJsCacheStatus"
                              class="wpacu-opt-badge <?php echo $cacheDynamicJsEnabled ? 'wpacu-opt-badge--warning' : ''; ?>"
                              data-enabled="<?php esc_attr_e('Enabled — verify cache safety', 'wp-asset-clean-up'); ?>"
                              data-disabled="<?php esc_attr_e('Disabled', 'wp-asset-clean-up'); ?>"
                              data-locked=""><?php echo $cacheDynamicJsEnabled ? esc_html__('Enabled — verify cache safety', 'wp-asset-clean-up') : esc_html__('Disabled', 'wp-asset-clean-up'); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2 wpacu-opt-details-arrow" aria-hidden="true"></span>
                    </span>
                </summary>

                <div class="wpacu-opt-details-body">
                    <article class="wpacu-opt-card">
                        <header class="wpacu-opt-card-header wpacu-opt-card-header--switch-first">
                            <span class="wpacu-opt-card-control">
                                <label class="wpacu_switch">
                                    <input id="wpacu_cache_dynamic_loaded_js_enable"
                                           data-target-opacity="#wpacu_cache_dynamic_loaded_js_info_area"
                                           type="checkbox"
                                        <?php checked($cacheDynamicJsEnabled); ?>
                                           name="<?php echo esc_attr($settingsInputName); ?>[cache_dynamic_loaded_js]"
                                           value="1" />
                                    <span class="wpacu_slider wpacu_round"></span>
                                </label>
                            </span>
                            <span class="wpacu-opt-card-copy">
                                <strong class="wpacu-opt-card-title"><?php esc_html_e('Cache dynamic JavaScript responses', 'wp-asset-clean-up'); ?></strong>
                                <span class="wpacu-opt-card-description"><?php esc_html_e('Avoids bootstrapping WordPress for repeated requests to eligible public JavaScript endpoints.', 'wp-asset-clean-up'); ?></span>
                            </span>
                        </header>
                        <div class="wpacu-opt-card-body">
                            <div id="wpacu_cache_dynamic_loaded_js_info_area" class="wpacu-opt-dependent" style="<?php echo esc_attr($cacheDynamicJsEnabled ? 'opacity: 1;' : 'opacity: 0.4;'); ?>">
                                <p class="wpacu-opt-card-lead"><?php echo wp_kses_post(sprintf(
                                    __('Example endpoint: <code>/wp-content/plugins/plugin-name/js/generate-script-output.php?ver=%s</code>.', 'wp-asset-clean-up'),
                                    esc_html($wp_version)
                                )); ?></p>
                                <div class="wpacu-opt-note wpacu-opt-note--caution">
                                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                    <span><strong><?php esc_html_e('Public output only.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Do not cache responses that vary by user, login state, cookie, nonce, permission, cart state, or another private condition.', 'wp-asset-clean-up'); ?></span>
                                </div>

                                <div class="wpacu-opt-field">
                                    <label class="wpacu-opt-field-label" for="wpacu_cache_dynamic_loaded_js_exceptions">
                                        <span><?php esc_html_e('Do not cache matching dynamic JavaScript URLs', 'wp-asset-clean-up'); ?></span>
                                        <small><?php esc_html_e('One URL fragment or RegEx per line', 'wp-asset-clean-up'); ?></small>
                                    </label>
                                    <textarea class="wpacu-opt-textarea"
                                              rows="4"
                                              id="wpacu_cache_dynamic_loaded_js_exceptions"
                                              name="<?php echo esc_attr($settingsInputName); ?>[cache_dynamic_loaded_js_exceptions]"
                                              placeholder="Example:
/js-generator.php?user_id=
#(?:user_id|nonce|token)=#i"><?php echo esc_textarea($cacheDynamicJsExceptions); ?></textarea>
                                    <p class="wpacu-opt-field-help"><?php esc_html_e('Use a stable fragment that matches every private variation, such as “/js-generator.php?user_id=”, rather than excluding only one specific user ID. Matching endpoints keep their original URL and are not fetched, minified, or served from the Asset CleanUp cache.', 'wp-asset-clean-up'); ?></p>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </details>
        </div>

        <script>
            (function () {
                'use strict';

                function initJavaScriptOptimizationStatusSync() {
                    var root = document.getElementById('wpacu-js-optimization-settings');

                    if (! root || root.getAttribute('data-status-sync-initialized') === '1') {
                        return;
                    }

                    root.setAttribute('data-status-sync-initialized', '1');

                    var minifyToggle = document.getElementById('wpacu_minify_js_enable');
                    var minifyStatus = document.getElementById('wpacuJsMinifyStatus');
                    var combineToggle = document.getElementById('wpacu_combine_loaded_js_enable');
                    var combineState = document.getElementById('wpacuJsCombineState');
                    var combineDescription = document.getElementById('wpacuJsCombineDescription');
                    var inlineToggle = document.getElementById('wpacu_inline_js_files_enable');
                    var inlineState = document.getElementById('wpacuJsInlineState');
                    var advancedStatus = document.getElementById('wpacuJsAdvancedStatus');
                    var advancedPanel = document.getElementById('wpacu-js-advanced-delivery');
                    var moveJqueryToggle = document.getElementById('wpacu_move_inline_jquery_after_src_tag_enable');
                    var moveBodyToggle = document.getElementById('wpacu_move_scripts_to_body_enable');
                    var compatibilityStatus = document.getElementById('wpacuJsCompatibilityStatus');
                    var compatibilityPanel = document.getElementById('wpacu-js-compatibility');
                    var cacheToggle = document.getElementById('wpacu_cache_dynamic_loaded_js_enable');
                    var cacheStatus = document.getElementById('wpacuJsCacheStatus');
                    var cachePanel = document.getElementById('wpacu-js-generated-cache');
                    var advancedMenuStatus = document.getElementById('wpacuOptimizeJsAdvancedMenuStatus');
                    var advancedMenuCircle = advancedMenuStatus ? advancedMenuStatus.querySelector('.wpacu-circle-status') : null;

                    var strings = {
                        enabled: <?php echo wp_json_encode(__('Enabled', 'wp-asset-clean-up')); ?>,
                        disabled: <?php echo wp_json_encode(__('Disabled', 'wp-asset-clean-up')); ?>,
                        locked: <?php echo wp_json_encode(__('Locked', 'wp-asset-clean-up')); ?>,
                        situational: <?php echo wp_json_encode(__('Situational', 'wp-asset-clean-up')); ?>,
                        usuallyUnnecessary: <?php echo wp_json_encode(__('Usually unnecessary', 'wp-asset-clean-up')); ?>,
                        enabledTestCarefully: <?php echo wp_json_encode(__('Enabled — test carefully', 'wp-asset-clean-up')); ?>,
                        advanced: <?php echo wp_json_encode(__('Advanced', 'wp-asset-clean-up')); ?>
                    };

                    function isLocked(toggle) {
                        return Boolean(toggle && (toggle.disabled || toggle.getAttribute('data-status-locked') === '1'));
                    }

                    function isEffective(toggle) {
                        return Boolean(toggle && ! isLocked(toggle) && toggle.checked);
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

                    function syncSimple(toggle, status, enabledClass) {
                        if (! toggle || ! status) {
                            return;
                        }

                        if (isLocked(toggle) && status.getAttribute('data-locked')) {
                            setBadge(status, status.getAttribute('data-locked'), 'wpacu-opt-badge--locked');
                            return;
                        }

                        setBadge(
                            status,
                            toggle.checked ? status.getAttribute('data-enabled') : status.getAttribute('data-disabled'),
                            toggle.checked ? enabledClass : ''
                        );
                    }

                    function syncAdvanced() {
                        if (! advancedStatus) {
                            return;
                        }

                        var combineEnabled = isEffective(combineToggle);
                        var combineLocked = isLocked(combineToggle);
                        var inlineLocked = isLocked(inlineToggle);
                        var inlineEnabled = isEffective(inlineToggle);
                        var text;
                        var statusClass;

                        if (inlineLocked) {
                            text = advancedStatus.getAttribute('data-managed');
                            statusClass = 'wpacu-opt-badge--locked';
                        } else if (combineEnabled && inlineEnabled) {
                            text = advancedStatus.getAttribute('data-both');
                            statusClass = 'wpacu-opt-badge--warning';
                        } else if (combineEnabled) {
                            text = advancedStatus.getAttribute('data-combine');
                            statusClass = 'wpacu-opt-badge--warning';
                        } else if (inlineEnabled && combineLocked) {
                            text = advancedStatus.getAttribute('data-inline-locked');
                            statusClass = 'wpacu-opt-badge--warning';
                        } else if (inlineEnabled) {
                            text = advancedStatus.getAttribute('data-inline');
                            statusClass = 'wpacu-opt-badge--warning';
                        } else if (combineLocked) {
                            text = advancedStatus.getAttribute('data-locked');
                            statusClass = 'wpacu-opt-badge--locked';
                        } else {
                            text = advancedStatus.getAttribute('data-none');
                            statusClass = '';
                        }

                        setBadge(advancedStatus, text, statusClass);
                        var modernProtocolDetected = root.getAttribute('data-modern-protocol-detected') === '1';
                        var combineText = combineEnabled
                            ? (modernProtocolDetected ? strings.enabledTestCarefully : strings.enabled)
                            : (modernProtocolDetected ? strings.usuallyUnnecessary : strings.situational);

                        setBadge(combineState, combineLocked ? strings.locked : combineText, combineLocked ? 'wpacu-opt-badge--locked' : (modernProtocolDetected || combineEnabled ? 'wpacu-opt-badge--warning' : ''));
                        if (combineDescription) {
                            combineDescription.textContent = combineDescription.getAttribute(modernProtocolDetected ? 'data-modern' : 'data-default');
                        }
                        setBadge(inlineState, inlineLocked ? strings.locked : (inlineEnabled ? strings.enabled : strings.advanced), inlineLocked ? 'wpacu-opt-badge--locked' : (inlineEnabled ? 'wpacu-opt-badge--warning' : ''));

                        if ((combineEnabled || inlineEnabled) && advancedPanel) {
                            advancedPanel.open = true;
                        }
                    }

                    function syncCompatibility() {
                        if (! compatibilityStatus) {
                            return;
                        }

                        var activeCount = (moveJqueryToggle && moveJqueryToggle.checked ? 1 : 0)
                            + (moveBodyToggle && moveBodyToggle.checked ? 1 : 0);
                        var text = compatibilityStatus.getAttribute('data-zero');

                        if (activeCount === 1) {
                            text = compatibilityStatus.getAttribute('data-one');
                        } else if (activeCount === 2) {
                            text = compatibilityStatus.getAttribute('data-two');
                        }

                        setBadge(compatibilityStatus, text, activeCount > 0 ? 'wpacu-opt-badge--warning' : '');

                        if (activeCount > 0 && compatibilityPanel) {
                            compatibilityPanel.open = true;
                        }
                    }

                    function syncAdvancedMenu() {
                        var hasActiveAdvancedSetting = [combineToggle, inlineToggle, moveJqueryToggle, moveBodyToggle, cacheToggle].some(isEffective);

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

                    function syncAll() {
                        syncSimple(minifyToggle, minifyStatus, 'wpacu-opt-badge--on');
                        syncSimple(cacheToggle, cacheStatus, 'wpacu-opt-badge--warning');
                        syncAdvanced();
                        syncCompatibility();
                        syncAdvancedMenu();

                        if (cacheToggle && cacheToggle.checked && cachePanel) {
                            cachePanel.open = true;
                        }
                    }

                    [minifyToggle, combineToggle, inlineToggle, moveJqueryToggle, moveBodyToggle, cacheToggle].forEach(function (toggle) {
                        if (toggle) {
                            toggle.addEventListener('change', syncAll);
                        }
                    });

                    if (window.jQuery) {
                        window.jQuery(root).on('wpacuModernProtocolDetected.wpacuOptimizeJsStatus', syncAll);
                        [minifyToggle, combineToggle, inlineToggle, moveJqueryToggle, moveBodyToggle, cacheToggle].forEach(function (toggle) {
                            if (toggle) {
                                window.jQuery(toggle).on('tick.wpacuOptimizeJsStatus', syncAll);
                            }
                        });
                    }

                    syncAll();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initJavaScriptOptimizationStatusSync);
                } else {
                    initJavaScriptOptimizationStatusSync();
                }
            }());
        </script>
    </main>
</div>

<?php require WPACU_PLUGIN_DIR . '/templates/_common/modals/combine-js-method.php'; ?>

<?php require WPACU_PLUGIN_DIR . '/templates/_common/modals/js-http-guidance.php'; ?>

<?php require WPACU_PLUGIN_DIR . '/templates/_common/modals/move-inline-jquery.php'; ?>

<?php require WPACU_PLUGIN_DIR . '/templates/_common/modals/move-scripts-to-body.php'; ?>
