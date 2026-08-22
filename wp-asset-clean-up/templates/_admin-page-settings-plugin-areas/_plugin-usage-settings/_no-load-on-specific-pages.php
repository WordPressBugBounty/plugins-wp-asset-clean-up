<?php
use WpAssetCleanUp\Admin\SettingsAdmin;
use WpAssetCleanUp\Regex;

if (! isset($data)) {
    exit;
}

$fullBypassValue = isset($data['do_not_load_plugin_patterns'])
    ? (string)$data['do_not_load_plugin_patterns']
    : '';
$fullBypassRules = Regex::splitRules($fullBypassValue);
$fullBypassCount = count($fullBypassRules);

$targetedRules = array();

if (! empty($data['do_not_load_plugin_features']) && is_array($data['do_not_load_plugin_features'])) {
    foreach ($data['do_not_load_plugin_features'] as $setValues) {
        if (! is_array($setValues)) {
            continue;
        }

        $pattern = isset($setValues['pattern']) ? trim((string)$setValues['pattern']) : '';
        $list    = isset($setValues['list']) && is_array($setValues['list'])
            ? array_values($setValues['list'])
            : array();

        if ($pattern !== '' || ! empty($list)) {
            $targetedRules[] = array(
                'pattern' => $pattern,
                'list'    => $list
            );
        }
    }
}

$featureGroups = SettingsAdmin::getNoLoadFeaturesOptionsGroups();

$featureDescriptions = array(
    'minify_css' => __('CSS files remain in their original, unminified form.', 'wp-asset-clean-up'),
    'inline_css' => __('Eligible CSS remains in external stylesheets instead of being inserted into the HTML.', 'wp-asset-clean-up'),
    'combine_css' => __('Stylesheets remain separate instead of being merged into fewer files.', 'wp-asset-clean-up'),
    'defer_css_body' => __('Stylesheets already loaded in the BODY or footer keep their original loading behavior.', 'wp-asset-clean-up'),
    'critical_css' => __('Critical CSS output is not applied on matching pages.', 'wp-asset-clean-up'),
    'cache_dynamic_loaded_css' => __('Dynamically loaded CSS is not cached by Asset CleanUp on matching pages.', 'wp-asset-clean-up'),
    'local_fonts_display' => __('Asset CleanUp does not update the font-display value for local fonts.', 'wp-asset-clean-up'),
    'local_fonts_preload' => __('Local font preload tags managed by Asset CleanUp are not added.', 'wp-asset-clean-up'),
    'google_fonts_combine' => __('Google Fonts requests remain separate.', 'wp-asset-clean-up'),
    'google_fonts_display' => __('Asset CleanUp does not update font-display for Google Fonts.', 'wp-asset-clean-up'),
    'google_fonts_preconnect' => __('Google Fonts preconnect hints are not added.', 'wp-asset-clean-up'),
    'google_fonts_preload' => __('Google font file preload hints are not added.', 'wp-asset-clean-up'),
    'google_fonts_remove' => __('Google Fonts remain available; the removal feature is skipped.', 'wp-asset-clean-up'),
    'minify_js' => __('JavaScript files remain in their original, unminified form.', 'wp-asset-clean-up'),
    'inline_js' => __('Eligible JavaScript remains in external files instead of being moved inline.', 'wp-asset-clean-up'),
    'combine_js' => __('JavaScript files remain separate instead of being merged into fewer files.', 'wp-asset-clean-up'),
    'move_inline_jquery_after_src_tag' => __('Inline jQuery code stays in its original position.', 'wp-asset-clean-up'),
    'move_scripts_to_body' => __('SCRIPT tags remain in their original HEAD or BODY locations.', 'wp-asset-clean-up'),
    'cache_dynamic_loaded_js' => __('Dynamically loaded JavaScript is not cached by Asset CleanUp on matching pages.', 'wp-asset-clean-up')
);
?>
<main id="wpacu-page-exclusions-settings" class="wpacu-page-exclusions-page">
    <header class="wpacu-page-exclusions-header">
        <div>
            <div class="wpacu-page-exclusions-eyebrow">
                <?php esc_html_e('Request control', 'wp-asset-clean-up'); ?>
            </div>
            <h2 id="wpacuPageExclusionsTitle">
                <?php
                echo esc_html(
                    sprintf(
                        __('Choose where %s should not apply', 'wp-asset-clean-up'),
                        WPACU_PLUGIN_TITLE
                    )
                );
                ?>
            </h2>
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        __('Exclude %1$s completely from selected front-end requests, or keep it active while skipping only the optimization features that cause a problem.', 'wp-asset-clean-up'),
                        WPACU_PLUGIN_TITLE
                    )
                );
                ?>
            </p>
        </div>
        <div class="wpacu-page-exclusions-header-badge">
            <?php esc_html_e('Front-end only', 'wp-asset-clean-up'); ?>
        </div>
    </header>

    <div class="wpacu-page-exclusions-body">
        <section class="wpacu-page-exclusions-intro" aria-labelledby="wpacuPageExclusionsIntroTitle">
            <div class="wpacu-page-exclusions-intro-icon" aria-hidden="true">
                <span class="dashicons dashicons-filter"></span>
            </div>
            <div>
                <h3 id="wpacuPageExclusionsIntroTitle">
                    <?php esc_html_e('Choose the narrowest exclusion that solves the problem', 'wp-asset-clean-up'); ?>
                </h3>
                <p>
                    <?php esc_html_e('Use a full bypass when a page must remain completely untouched. Use a targeted exception when only one or more CSS, font, or JavaScript optimizations should be skipped.', 'wp-asset-clean-up'); ?>
                </p>
            </div>
        </section>

        <div class="wpacu-page-exclusion-mode-grid" aria-label="<?php esc_attr_e('Exclusion types', 'wp-asset-clean-up'); ?>">
            <a class="wpacu-page-exclusion-mode wpacu-page-exclusion-mode--full"
               href="#wpacu-page-exclusions-level-1"
               data-wpacu-page-exclusion-jump>
                <span class="wpacu-page-exclusion-mode-number" aria-hidden="true">1</span>
                <div>
                    <span class="wpacu-page-exclusion-mode-kicker"><?php esc_html_e('Full page bypass', 'wp-asset-clean-up'); ?></span>
                    <h3><?php esc_html_e('Asset CleanUp does not run', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('No unload rules or optimization features are applied to a matching request. This is the broadest exclusion.', 'wp-asset-clean-up'); ?></p>
                </div>
            </a>

            <a class="wpacu-page-exclusion-mode wpacu-page-exclusion-mode--targeted"
               href="#wpacu-page-exclusions-level-2"
               data-wpacu-page-exclusion-jump>
                <span class="wpacu-page-exclusion-mode-number" aria-hidden="true">2</span>
                <div>
                    <span class="wpacu-page-exclusion-mode-kicker"><?php esc_html_e('Targeted feature exception', 'wp-asset-clean-up'); ?></span>
                    <h3><?php esc_html_e('Asset CleanUp keeps running', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Unload rules and all other enabled features continue to work; only the selected features are skipped.', 'wp-asset-clean-up'); ?></p>
                </div>
            </a>
        </div>

        <section id="wpacu-page-exclusions-level-1"
                 class="wpacu-page-exclusions-section wpacu-page-exclusions-section--full"
                 aria-labelledby="wpacuFullBypassTitle">
            <header class="wpacu-page-exclusions-section-header">
                <div>
                    <span class="wpacu-page-exclusions-section-kicker"><?php esc_html_e('Level 1 · Broadest exclusion', 'wp-asset-clean-up'); ?></span>
                    <h3 id="wpacuFullBypassTitle"><?php esc_html_e('Disable Asset CleanUp completely on matching pages', 'wp-asset-clean-up'); ?></h3>
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                __('When a rule matches, %s stops before applying front-end unload rules or optimization settings. Use this for strong compatibility bypasses or pages that must remain untouched.', 'wp-asset-clean-up'),
                                WPACU_PLUGIN_TITLE
                            )
                        );
                        ?>
                    </p>
                </div>
                <span class="wpacu-page-exclusions-section-badge wpacu-page-exclusions-section-badge--amber">
                    <?php esc_html_e('Takes priority', 'wp-asset-clean-up'); ?>
                </span>
            </header>

            <div class="wpacu-page-exclusions-editor">
                <div class="wpacu-page-exclusions-field-header">
                    <label for="wpacu_do_not_load_plugin_patterns">
                        <?php esc_html_e('Page URL patterns', 'wp-asset-clean-up'); ?>
                    </label>
                    <span class="wpacu-page-exclusions-live-count" id="wpacuFullBypassCount" aria-live="polite">
                        <?php
                        echo esc_html(
                            $fullBypassCount === 0
                                ? __('No full bypass patterns', 'wp-asset-clean-up')
                                : sprintf(
                                    _n('%s full bypass pattern', '%s full bypass patterns', $fullBypassCount, 'wp-asset-clean-up'),
                                    number_format_i18n($fullBypassCount)
                                )
                        );
                        ?>
                    </span>
                </div>

                <textarea id="wpacu_do_not_load_plugin_patterns"
                          name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[do_not_load_plugin_patterns]"
                          rows="6"
                          spellcheck="false"
                          placeholder="Example:&#10;/cart/&#10;/checkout/&#10;#/account/(login|register)/#"><?php echo esc_textarea($fullBypassValue); ?></textarea>

                <div class="wpacu-page-exclusions-editor-help">
                    <span>
                        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                        <?php esc_html_e('Enter one rule per line. Use only the path or request pattern; do not include the domain name.', 'wp-asset-clean-up'); ?>
                    </span>
                    <button type="button"
                            class="wpacu-page-exclusions-disclosure-trigger"
                            data-wpacu-page-exclusions-disclosure="wpacuFullMatchingGuide"
                            aria-expanded="false"
                            aria-controls="wpacuFullMatchingGuide">
                        <?php esc_html_e('How full-page matching works', 'wp-asset-clean-up'); ?>
                        <span class="wpacu-page-exclusions-chevron" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="wpacu-page-exclusions-disclosure-panel"
                     id="wpacuFullMatchingGuide"
                     aria-hidden="true">
                    <div class="wpacu-page-exclusions-disclosure-inner">
                        <div class="wpacu-page-exclusions-guide-grid">
                            <div>
                                <h4><?php esc_html_e('For most sites', 'wp-asset-clean-up'); ?></h4>
                                <p><?php esc_html_e('Use a plain path such as /checkout/. It matches when that text appears anywhere in the decoded request URI.', 'wp-asset-clean-up'); ?></p>
                            </div>
                            <div>
                                <h4><?php esc_html_e('For advanced matching', 'wp-asset-clean-up'); ?></h4>
                                <p><?php esc_html_e('Use an explicit PHP regular expression such as #/account/(login|register)/#i. The i modifier makes that example case-insensitive.', 'wp-asset-clean-up'); ?></p>
                            </div>
                            <div>
                                <h4><?php esc_html_e('What is evaluated', 'wp-asset-clean-up'); ?></h4>
                                <p><?php esc_html_e('The request path and query string are evaluated after URL decoding. The site hostname is not part of the match.', 'wp-asset-clean-up'); ?></p>
                            </div>
                            <div>
                                <h4><?php esc_html_e('Priority', 'wp-asset-clean-up'); ?></h4>
                                <p><?php esc_html_e('A full bypass is evaluated before targeted feature exceptions. When it matches, the targeted rules below are not relevant for that request.', 'wp-asset-clean-up'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wpacu-page-exclusions-examples" aria-label="<?php esc_attr_e('Full bypass examples', 'wp-asset-clean-up'); ?>">
                    <div><code>/checkout/</code><span><?php esc_html_e('Any request containing this path', 'wp-asset-clean-up'); ?></span></div>
                    <div><code>/product/(.*?)/</code><span><?php esc_html_e('Legacy loose RegEx for product-style paths', 'wp-asset-clean-up'); ?></span></div>
                    <div><code>#/account/(login|register)/#</code><span><?php esc_html_e('Explicit RegEx for either path', 'wp-asset-clean-up'); ?></span></div>
                </div>
            </div>
        </section>

        <section id="wpacu-page-exclusions-level-2"
                 class="wpacu-page-exclusions-section wpacu-page-exclusions-section--targeted"
                 aria-labelledby="wpacuTargetedExceptionsTitle">
            <header class="wpacu-page-exclusions-section-header">
                <div>
                    <span class="wpacu-page-exclusions-section-kicker"><?php esc_html_e('Level 2 · More targeted', 'wp-asset-clean-up'); ?></span>
                    <h3 id="wpacuTargetedExceptionsTitle"><?php esc_html_e('Disable selected features on matching pages', 'wp-asset-clean-up'); ?></h3>
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                __('%s remains active. Choose only the optimization features that should be skipped for each URL pattern.', 'wp-asset-clean-up'),
                                WPACU_PLUGIN_TITLE
                            )
                        );
                        ?>
                    </p>
                </div>
                <span class="wpacu-page-exclusions-section-badge">
                    <?php esc_html_e('Preferred when possible', 'wp-asset-clean-up'); ?>
                </span>
            </header>

            <div class="wpacu-page-exclusions-targeted-intro">
                <div>
                    <span class="dashicons dashicons-art" aria-hidden="true"></span>
                    <p>
                        <strong><?php esc_html_e('CSS example', 'wp-asset-clean-up'); ?></strong>
                        <?php esc_html_e('Skip Combine CSS on /course/ so stylesheets continue loading individually while other Asset CleanUp rules still apply.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>
                <div>
                    <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                    <p>
                        <strong><?php esc_html_e('JavaScript example', 'wp-asset-clean-up'); ?></strong>
                        <?php esc_html_e('Skip Combine JavaScript on /checkout/ so scripts continue loading individually while other Asset CleanUp rules still apply.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>
            </div>

            <div class="wpacu-page-exclusions-rules-heading">
                <div>
                    <h4><?php esc_html_e('Targeted exceptions', 'wp-asset-clean-up'); ?></h4>
                    <p><?php esc_html_e('Each complete rule needs one URL pattern and at least one selected feature.', 'wp-asset-clean-up'); ?></p>
                </div>
                <span id="wpacuTargetedRulesCount" class="wpacu-page-exclusions-live-count" aria-live="polite">
                    <?php
                    echo esc_html(
                        empty($targetedRules)
                            ? __('No complete exceptions yet', 'wp-asset-clean-up')
                            : sprintf(
                                _n('%s configured exception', '%s configured exceptions', count($targetedRules), 'wp-asset-clean-up'),
                                number_format_i18n(count($targetedRules))
                            )
                    );
                    ?>
                </span>
            </div>

            <div id="wpacu-prevent-feature-rule-areas-wrap" class="wpacu-page-exclusions-rule-list">
                <?php
                if (! empty($targetedRules)) {
                    foreach ($targetedRules as $setValues) {
                        echo SettingsAdmin::generateNewRuleNoFeatureAreaRow($data, $setValues, true);
                    }
                } else {
                    echo SettingsAdmin::generateNewRuleNoFeatureAreaRow(
                        $data,
                        array('pattern' => '', 'list' => array()),
                        true
                    );
                }
                ?>
            </div>

            <div class="wpacu-page-exclusions-empty-state" id="wpacuTargetedRulesEmptyState" hidden>
                <span class="dashicons dashicons-filter" aria-hidden="true"></span>
                <strong><?php esc_html_e('No targeted exception rows are open', 'wp-asset-clean-up'); ?></strong>
                <p><?php esc_html_e('Add an exception when a particular optimization should be skipped on selected pages.', 'wp-asset-clean-up'); ?></p>
            </div>

            <div class="wpacu-page-exclusions-rule-actions">
                <button type="button"
                        class="wpacu-page-exclusions-add-rule"
                        data-wpacu-add-page-exclusion-rule>
                    <span class="wpacu-page-exclusions-add-rule-visual" aria-hidden="true">
                        <svg class="wpacu-page-exclusions-add-rule-plus" viewBox="0 0 20 20" focusable="false">
                            <path d="M10 4v12M4 10h12"></path>
                        </svg>
                        <span class="wpacu-page-exclusions-button-spinner"></span>
                    </span>
                    <span class="wpacu-page-exclusions-add-rule-label"><?php esc_html_e('Add targeted exception', 'wp-asset-clean-up'); ?></span>
                </button>

                <button type="button"
                        class="wpacu-page-exclusions-disclosure-trigger wpacu-page-exclusions-disclosure-trigger--features"
                        data-wpacu-page-exclusions-disclosure="wpacuFeatureGuide"
                        aria-expanded="false"
                        aria-controls="wpacuFeatureGuide">
                    <?php esc_html_e('What happens when a feature is skipped?', 'wp-asset-clean-up'); ?>
                    <span class="wpacu-page-exclusions-chevron" aria-hidden="true"></span>
                </button>
            </div>

            <div class="wpacu-page-exclusions-disclosure-panel wpacu-page-exclusions-feature-guide"
                 id="wpacuFeatureGuide"
                 aria-hidden="true">
                <div class="wpacu-page-exclusions-disclosure-inner">
                    <div class="wpacu-page-exclusions-feature-guide-intro">
                        <strong><?php esc_html_e('A targeted exception does not unload the asset.', 'wp-asset-clean-up'); ?></strong>
                        <span><?php esc_html_e('It prevents the selected processing feature from changing the matching page.', 'wp-asset-clean-up'); ?></span>
                    </div>

                    <div class="wpacu-page-exclusions-feature-groups">
                        <?php foreach ($featureGroups as $groupLabel => $features) { ?>
                            <section>
                                <h4><?php echo wp_kses_post($groupLabel); ?></h4>
                                <dl>
                                    <?php foreach ($features as $featureKey => $featureLabel) { ?>
                                        <div>
                                            <dt><?php echo esc_html(wp_strip_all_tags($featureLabel)); ?></dt>
                                            <dd>
                                                <?php
                                                echo esc_html(
                                                    isset($featureDescriptions[$featureKey])
                                                        ? $featureDescriptions[$featureKey]
                                                        : __('This feature is skipped on matching pages.', 'wp-asset-clean-up')
                                                );
                                                ?>
                                            </dd>
                                        </div>
                                    <?php } ?>
                                </dl>
                            </section>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>

        <aside class="wpacu-page-exclusions-final-note">
            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
            <div>
                <strong><?php esc_html_e('After saving an exclusion', 'wp-asset-clean-up'); ?></strong>
                <p><?php esc_html_e('Clear relevant page, plugin, server, and CDN caches. Verify a matching page and another page that should remain unaffected. Test Mode can keep the change private while you check it in your logged-in session.', 'wp-asset-clean-up'); ?></p>
            </div>
        </aside>
    </div>
</main>

<script>
(function ($) {
    'use strict';

    if (typeof window.wpacuPageExclusionsUseEffects !== 'boolean') {
        window.wpacuPageExclusionsUseEffects = true;
    }

    var root = document.getElementById('wpacu-page-exclusions-settings');

    if (! root || ! $) {
        return;
    }

    var rulesWrap = document.getElementById('wpacu-prevent-feature-rule-areas-wrap');
    var emptyState = document.getElementById('wpacuTargetedRulesEmptyState');
    var addButtons = root.querySelectorAll('[data-wpacu-add-page-exclusion-rule]');
    var fullBypassTextarea = document.getElementById('wpacu_do_not_load_plugin_patterns');
    var fullBypassCount = document.getElementById('wpacuFullBypassCount');
    var targetedRulesCount = document.getElementById('wpacuTargetedRulesCount');

    var strings = {
        newException: <?php echo wp_json_encode(__('New targeted exception', 'wp-asset-clean-up')); ?>,
        ready: <?php echo wp_json_encode(__('Ready', 'wp-asset-clean-up')); ?>,
        chooseFeatures: <?php echo wp_json_encode(__('Choose features', 'wp-asset-clean-up')); ?>,
        addPattern: <?php echo wp_json_encode(__('Add a pattern', 'wp-asset-clean-up')); ?>,
        notConfigured: <?php echo wp_json_encode(__('Not configured', 'wp-asset-clean-up')); ?>,
        addPatternAndFeatures: <?php echo wp_json_encode(__('Add a URL pattern and select one or more features.', 'wp-asset-clean-up')); ?>,
        chooseAtLeastOneFeature: <?php echo wp_json_encode(__('The URL pattern is set; choose at least one feature to skip.', 'wp-asset-clean-up')); ?>,
        addPatternForFeatures: <?php echo wp_json_encode(__('Features are selected; add the page URL pattern where they should be skipped.', 'wp-asset-clean-up')); ?>,
        noFeaturesSelected: <?php echo wp_json_encode(__('No features selected yet.', 'wp-asset-clean-up')); ?>,
        oneFeatureSelected: <?php echo wp_json_encode(__('1 feature selected.', 'wp-asset-clean-up')); ?>,
        manyFeaturesSelected: <?php echo wp_json_encode(__('%d features selected.', 'wp-asset-clean-up')); ?>,
        oneFeatureSkipped: <?php echo wp_json_encode(__('1 feature will be skipped on matching pages.', 'wp-asset-clean-up')); ?>,
        manyFeaturesSkipped: <?php echo wp_json_encode(__('%d features will be skipped on matching pages.', 'wp-asset-clean-up')); ?>,
        effectPrefix: <?php echo wp_json_encode(__('Skip', 'wp-asset-clean-up')); ?>,
        moreFeatures: <?php echo wp_json_encode(__('+%d more', 'wp-asset-clean-up')); ?>,
        noFullPatterns: <?php echo wp_json_encode(__('No full bypass patterns', 'wp-asset-clean-up')); ?>,
        oneFullPattern: <?php echo wp_json_encode(__('1 full bypass pattern', 'wp-asset-clean-up')); ?>,
        manyFullPatterns: <?php echo wp_json_encode(__('%d full bypass patterns', 'wp-asset-clean-up')); ?>,
        noCompleteExceptions: <?php echo wp_json_encode(__('No complete exceptions yet', 'wp-asset-clean-up')); ?>,
        oneCompleteException: <?php echo wp_json_encode(__('1 complete exception', 'wp-asset-clean-up')); ?>,
        manyCompleteExceptions: <?php echo wp_json_encode(__('%d complete exceptions', 'wp-asset-clean-up')); ?>,
        addFailed: <?php echo wp_json_encode(__('The new exception could not be added. Please reload the page and try again.', 'wp-asset-clean-up')); ?>
    };

    function formatCount(template, count) {
        return template.replace('%d', String(count));
    }

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function effectsEnabled() {
        return window.wpacuPageExclusionsUseEffects !== false && ! prefersReducedMotion();
    }

    function setEffectsState() {
        root.classList.toggle('wpacu-page-exclusions-no-effects', ! effectsEnabled());
    }

    function getRules() {
        return rulesWrap
            ? Array.prototype.slice.call(rulesWrap.querySelectorAll('[data-wpacu-page-exclusion-rule]'))
            : [];
    }

    function getSelectedOptions(select) {
        if (! select) {
            return [];
        }

        return Array.prototype.filter.call(select.options, function (option) {
            return option.selected;
        });
    }

    function updateRule(rule) {
        var patternInput = rule.querySelector('[data-wpacu-page-exclusion-pattern]');
        var featureSelect = rule.querySelector('[data-wpacu-page-exclusion-features]');
        var title = rule.querySelector('[data-wpacu-rule-title]');
        var summary = rule.querySelector('[data-wpacu-rule-summary]');
        var status = rule.querySelector('[data-wpacu-rule-status]');
        var countOutput = rule.querySelector('[data-wpacu-selected-features-count]');
        var effectCopy = rule.querySelector('[data-wpacu-rule-effect-copy]');

        if (! patternInput || ! featureSelect || ! title || ! summary || ! status) {
            return false;
        }

        var pattern = patternInput.value.trim();
        var selectedOptions = getSelectedOptions(featureSelect);
        var selectedCount = selectedOptions.length;
        var complete = pattern !== '' && selectedCount > 0;
        var statusText = strings.notConfigured;
        var statusClass = 'is-empty';
        var summaryText = strings.addPatternAndFeatures;

        rule.classList.remove('is-ready', 'is-incomplete', 'is-empty');
        status.classList.remove('is-ready', 'is-incomplete', 'is-empty');

        if (complete) {
            statusText = strings.ready;
            statusClass = 'is-ready';
            summaryText = selectedCount === 1
                ? strings.oneFeatureSkipped
                : formatCount(strings.manyFeaturesSkipped, selectedCount);
        } else if (pattern !== '') {
            statusText = strings.chooseFeatures;
            statusClass = 'is-incomplete';
            summaryText = strings.chooseAtLeastOneFeature;
        } else if (selectedCount > 0) {
            statusText = strings.addPattern;
            statusClass = 'is-incomplete';
            summaryText = strings.addPatternForFeatures;
        }

        rule.classList.add(statusClass);
        status.classList.add(statusClass);
        status.textContent = statusText;
        title.textContent = pattern !== '' ? pattern : strings.newException;
        summary.textContent = summaryText;

        if (countOutput) {
            countOutput.textContent = selectedCount === 0
                ? strings.noFeaturesSelected
                : (selectedCount === 1
                    ? strings.oneFeatureSelected
                    : formatCount(strings.manyFeaturesSelected, selectedCount));
        }

        if (effectCopy) {
            if (selectedCount === 0) {
                effectCopy.textContent = strings.addPatternAndFeatures;
            } else {
                var labels = selectedOptions.slice(0, 3).map(function (option) {
                    return option.textContent.trim();
                });

                var effectText = strings.effectPrefix + ': ' + labels.join(', ');

                if (selectedCount > 3) {
                    effectText += ' ' + formatCount(strings.moreFeatures, selectedCount - 3);
                }

                effectCopy.textContent = effectText + '.';
            }
        }

        return complete;
    }

    function updateRuleList() {
        var rules = getRules();
        var completeCount = 0;

        rules.forEach(function (rule, index) {
            var number = rule.querySelector('[data-wpacu-rule-number]');

            if (number) {
                number.textContent = String(index + 1);
            }

            if (updateRule(rule)) {
                completeCount++;
            }
        });

        if (emptyState) {
            emptyState.hidden = rules.length !== 0;
        }

        if (targetedRulesCount) {
            targetedRulesCount.textContent = completeCount === 0
                ? strings.noCompleteExceptions
                : (completeCount === 1
                    ? strings.oneCompleteException
                    : formatCount(strings.manyCompleteExceptions, completeCount));
        }
    }

    function updateFullBypassCount() {
        if (! fullBypassTextarea || ! fullBypassCount) {
            return;
        }

        var count = fullBypassTextarea.value
            .replace(/\r\n|\r/g, '\n')
            .split('\n')
            .filter(function (line) {
                return line.trim() !== '';
            }).length;

        fullBypassCount.textContent = count === 0
            ? strings.noFullPatterns
            : (count === 1
                ? strings.oneFullPattern
                : formatCount(strings.manyFullPatterns, count));
    }

    function initializeChosenInRule(rule) {
        if (typeof window.wpacuInitChosen !== 'function') {
            return;
        }

        var select = rule.querySelector('.wpacu_chosen_can_be_later_enabled');

        if (select) {
            window.wpacuInitChosen($(select), {
                width: '100%',
                hide_results_on_select: true
            });
        }
    }


    function closeChosenAfterFeatureSelection(featureSelect) {
        if (! featureSelect) {
            return;
        }

        var $select = $(featureSelect);

        // Native browser controls do not have a Chosen instance.
        if (! $select.data('chosen')) {
            return;
        }

        /*
         * Important: Chosen triggers the select's `change` as a jQuery event
         * from inside its result mouseup handler. After that event returns,
         * Chosen explicitly focuses its internal search input again.
         *
         * This helper is therefore called from a jQuery-delegated change
         * handler and waits until the current call stack completes. At that
         * point Chosen has finished its own mouseup/focus work and we can
         * reliably close the instance and release focus.
         */
        window.setTimeout(function () {
            var chosen = $select.data('chosen');
            var $chosen = $select.next('.chosen-container');

            if (! chosen || ! $chosen.length) {
                return;
            }

            if (typeof chosen.close_field === 'function') {
                chosen.close_field();
            } else {
                $select.trigger('chosen:close');
            }

            // Defensive cleanup for the bundled Chosen version.
            $chosen.removeClass('chosen-container-active chosen-with-drop');

            $chosen
                .find('.chosen-search input, .search-field input')
                .each(function () {
                    if (typeof this.blur === 'function') {
                        this.blur();
                    }
                });

            if (
                document.activeElement &&
                $chosen.get(0).contains(document.activeElement) &&
                typeof document.activeElement.blur === 'function'
            ) {
                document.activeElement.blur();
            }
        }, 0);
    }

    function setAddButtonsLoading(loading) {
        Array.prototype.forEach.call(addButtons, function (button) {
            button.disabled = loading;
            button.classList.toggle('is-loading', loading);
            button.setAttribute('aria-busy', loading ? 'true' : 'false');
        });
    }

    function addRule() {
        if (! rulesWrap || typeof wpacu_object === 'undefined' || ! wpacu_object.ajax_url) {
            window.alert(strings.addFailed);
            return;
        }

        setAddButtonsLoading(true);

        $.post(wpacu_object.ajax_url, {
            action: <?php echo wp_json_encode(WPACU_PLUGIN_ID . '_add_new_no_features_load_row'); ?>,
            wpacu_nonce: wpacu_object.wpacu_add_new_no_features_load_row_nonce,
            wpacu_input_style: typeof window.wpacuGetInputStyle === 'function'
                ? window.wpacuGetInputStyle()
                : 'enhanced',
            wpacu_time_r: new Date().getTime()
        }).done(function (newRowOutput) {
            var $newRow = $(newRowOutput).filter('[data-wpacu-page-exclusion-rule]').first();

            if ($newRow.length < 1) {
                $newRow = $('<div></div>').html(newRowOutput).find('[data-wpacu-page-exclusion-rule]').first();
            }

            if ($newRow.length < 1) {
                window.alert(strings.addFailed);
                return;
            }

            rulesWrap.appendChild($newRow.get(0));
            initializeChosenInRule($newRow.get(0));
            updateRuleList();

            if (effectsEnabled() && typeof $newRow.get(0).animate === 'function') {
                $newRow.get(0).animate(
                    [
                        { opacity: 0, transform: 'translateY(-7px)' },
                        { opacity: 1, transform: 'translateY(0)' }
                    ],
                    {
                        duration: 170,
                        easing: 'cubic-bezier(0.22, 0.61, 0.36, 1)'
                    }
                );
            }

            var patternInput = $newRow.get(0).querySelector('[data-wpacu-page-exclusion-pattern]');

            if (patternInput) {
                patternInput.focus();
            }
        }).fail(function () {
            window.alert(strings.addFailed);
        }).always(function () {
            setAddButtonsLoading(false);
        });
    }

    function removeRule(rule) {
        function finishRemove() {
            var featureSelect = rule.querySelector('[data-wpacu-page-exclusion-features]');

            if (featureSelect && $(featureSelect).data('chosen')) {
                $(featureSelect).chosen('destroy');
            }

            if (rule.parentNode) {
                rule.parentNode.removeChild(rule);
            }

            updateRuleList();
        }

        if (! effectsEnabled() || typeof rule.animate !== 'function') {
            finishRemove();
            return;
        }

        var height = rule.getBoundingClientRect().height;
        var animation = rule.animate(
            [
                { opacity: 1, height: height + 'px', transform: 'translateY(0)' },
                { opacity: 0, height: '0px', transform: 'translateY(-6px)' }
            ],
            {
                duration: 160,
                easing: 'ease-in',
                fill: 'forwards'
            }
        );

        animation.onfinish = finishRemove;
    }

    function toggleDisclosure(button) {
        var panelId = button.getAttribute('data-wpacu-page-exclusions-disclosure');
        var panel = panelId ? document.getElementById(panelId) : null;

        if (! panel) {
            return;
        }

        var open = button.getAttribute('aria-expanded') === 'true';
        open = ! open;

        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        panel.classList.toggle('is-open', open);
    }

    root.addEventListener('click', function (event) {
        var jumpLink = event.target.closest('[data-wpacu-page-exclusion-jump]');

        if (jumpLink) {
            var jumpTarget = document.getElementById(jumpLink.hash.substring(1));

            // The href remains a native anchor fallback when smooth scrolling is unavailable.
            if (jumpTarget && typeof jumpTarget.scrollIntoView === 'function') {
                event.preventDefault();
                jumpTarget.scrollIntoView({
                    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                    block: 'start'
                });

                if (window.history && typeof window.history.pushState === 'function') {
                    window.history.pushState(null, '', jumpLink.hash);
                }
            }

            return;
        }

        var addButton = event.target.closest('[data-wpacu-add-page-exclusion-rule]');

        if (addButton) {
            event.preventDefault();
            addRule();
            return;
        }

        var removeButton = event.target.closest('[data-wpacu-remove-page-exclusion-rule]');

        if (removeButton) {
            event.preventDefault();
            var rule = removeButton.closest('[data-wpacu-page-exclusion-rule]');

            if (rule) {
                removeRule(rule);
            }
            return;
        }

        var disclosureButton = event.target.closest('[data-wpacu-page-exclusions-disclosure]');

        if (disclosureButton) {
            event.preventDefault();
            toggleDisclosure(disclosureButton);
        }
    });

    root.addEventListener('input', function (event) {
        if (event.target === fullBypassTextarea) {
            updateFullBypassCount();
            return;
        }

        var rule = event.target.closest('[data-wpacu-page-exclusion-rule]');

        if (rule && event.target.matches('[data-wpacu-page-exclusion-pattern]')) {
            updateRuleList();
        }
    });

    /*
     * Use jQuery delegation here, not addEventListener(). Chosen calls
     * `$select.trigger('change')`, which does not invoke a native DOM
     * addEventListener('change', ...) handler. This also covers rules added
     * later through the existing AJAX Add Targeted Exception flow.
     */
    $(root)
        .off('change.wpacuPageExclusionFeatures')
        .on(
            'change.wpacuPageExclusionFeatures',
            '[data-wpacu-page-exclusion-features]',
            function () {
                updateRuleList();
                closeChosenAfterFeatureSelection(this);
            }
        );

    window.wpacuPageExclusionsSetEffects = function (enabled) {
        window.wpacuPageExclusionsUseEffects = enabled !== false;
        setEffectsState();
    };

    window.wpacuPageExclusionsRefresh = function () {
        updateFullBypassCount();
        updateRuleList();
    };

    setEffectsState();
    updateFullBypassCount();
    updateRuleList();
}(window.jQuery));
</script>
