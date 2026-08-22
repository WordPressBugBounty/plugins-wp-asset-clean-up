<?php
/*
 * No direct access to this file
 */
use WpAssetCleanUp\PluginTracking;

if ( ! isset($data) ) {
    exit;
}

$usageDataEnabled = ! empty($data['allow_usage_tracking']);
$settingsName     = WPACU_PLUGIN_ID . '_settings';

$pluginTracking = new PluginTracking();
$pluginTracking->setupData();
$trackingData = is_array($pluginTracking->data) ? $pluginTracking->data : array();

$wpacuSettings   = isset($trackingData['wpacu_settings']) && is_array($trackingData['wpacu_settings'])
    ? $trackingData['wpacu_settings']
    : array();
$activePlugins   = isset($trackingData['active_plugins']) && is_array($trackingData['active_plugins'])
    ? $trackingData['active_plugins']
    : array();
$inactivePlugins = isset($trackingData['inactive_plugins']) && is_array($trackingData['inactive_plugins'])
    ? $trackingData['inactive_plugins']
    : array();

$settingsJson = wp_json_encode(
    $wpacuSettings,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

if (! is_string($settingsJson)) {
    $settingsJson = '{}';
}

$formatPayloadValue = static function ($value) {
    if ($value === false || $value === null || $value === '') {
        return __('Not set', 'wp-asset-clean-up');
    }

    if (is_array($value) || is_object($value)) {
        $encoded = wp_json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return is_string($encoded) ? $encoded : __('Not available', 'wp-asset-clean-up');
    }

    return (string) $value;
};

$firstUsageValue = $formatPayloadValue(
    isset($trackingData['wpacu_first_usage']) ? $trackingData['wpacu_first_usage'] : false
);
$reviewInfoValue = $formatPayloadValue(
    isset($trackingData['wpacu_review_info']) ? $trackingData['wpacu_review_info'] : false
);
?>
<main id="wpacu-usage-data-settings" class="wpacu-usage-data-page<?php echo $usageDataEnabled ? ' wpacu-is-enabled' : ''; ?>">
    <section class="wpacu-usage-data-panel" aria-labelledby="wpacuUsageDataTitle">
        <header class="wpacu-usage-data-header">
            <div>
                <div class="wpacu-usage-data-eyebrow">
                    <?php esc_html_e('Optional product feedback', 'wp-asset-clean-up'); ?>
                </div>

                <h2 id="wpacuUsageDataTitle">
                    <?php esc_html_e('Share usage data on your terms', 'wp-asset-clean-up'); ?>
                </h2>

                <p>
                    <?php esc_html_e('Choose whether Asset CleanUp may send a periodic technical check-in about this WordPress environment and the plugin configuration in use. The information helps guide compatibility work, testing, translations, and future improvements.', 'wp-asset-clean-up'); ?>
                </p>
            </div>

            <div class="wpacu-usage-data-header-badge">
                <?php esc_html_e('Optional', 'wp-asset-clean-up'); ?>
            </div>
        </header>

        <div class="wpacu-usage-data-body">
            <section class="wpacu-usage-data-intro" aria-labelledby="wpacuUsageDataIntroTitle">
                <div class="wpacu-usage-data-intro-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>
                        <path d="M9 12l2 2 4-4"></path>
                    </svg>
                </div>

                <div>
                    <h3 id="wpacuUsageDataIntroTitle">
                        <?php esc_html_e('Completely optional and easy to change', 'wp-asset-clean-up'); ?>
                    </h3>

                    <p>
                        <?php esc_html_e('Asset CleanUp works the same whether this setting is enabled or disabled. Turning it off and saving this page stops future usage-data check-ins; it does not change any optimization rules.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>
            </section>

            <section class="wpacu-usage-data-master" aria-labelledby="wpacuUsageDataToggleTitle">
                <div class="wpacu-usage-data-master-control">
                    <label class="wpacu_switch" for="wpacu_allow_usage_tracking">
                        <input id="wpacu_allow_usage_tracking"
                               type="checkbox"
                               name="<?php echo esc_attr($settingsName); ?>[allow_usage_tracking]"
                               value="1"
                               <?php checked($usageDataEnabled); ?> />
                        <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                    </label>

                    <label class="wpacu-usage-data-control-label" for="wpacu_allow_usage_tracking">
                        <strong><?php esc_html_e('Share usage data', 'wp-asset-clean-up'); ?></strong>

                        <span id="wpacuUsageDataState" aria-live="polite">
                            <?php echo $usageDataEnabled
                                ? esc_html__('Sharing enabled', 'wp-asset-clean-up')
                                : esc_html__('Not shared', 'wp-asset-clean-up'); ?>
                        </span>

                        <small><?php esc_html_e('Save this page after changing the switch.', 'wp-asset-clean-up'); ?></small>
                    </label>
                </div>

                <div class="wpacu-usage-data-master-copy">
                    <span class="wpacu-usage-data-master-kicker">
                        <?php esc_html_e('Optional setting', 'wp-asset-clean-up'); ?>
                    </span>

                    <h3 id="wpacuUsageDataToggleTitle">
                        <?php esc_html_e('Send the technical information shown below', 'wp-asset-clean-up'); ?>
                    </h3>

                    <p>
                        <?php esc_html_e('When enabled, an initial check-in may be sent after saving, followed by no more than one check-in per week while the setting remains active.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>
            </section>

            <div class="wpacu-usage-data-summary-grid">
                <article class="wpacu-usage-data-summary-card">
                    <div class="wpacu-usage-data-summary-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"></path>
                            <path d="m7 16 4-5 4 3 5-7"></path>
                        </svg>
                    </div>

                    <div>
                        <h3><?php esc_html_e('Why this helps', 'wp-asset-clean-up'); ?></h3>

                        <ul>
                            <li><?php esc_html_e('Prioritize compatibility testing for common themes and plugins', 'wp-asset-clean-up'); ?></li>
                            <li><?php esc_html_e('Understand which features are used most', 'wp-asset-clean-up'); ?></li>
                            <li><?php esc_html_e('Plan support for common WordPress, PHP, and server environments', 'wp-asset-clean-up'); ?></li>
                            <li><?php esc_html_e('Prioritize translations based on locale usage', 'wp-asset-clean-up'); ?></li>
                        </ul>
                    </div>
                </article>

                <article class="wpacu-usage-data-summary-card">
                    <div class="wpacu-usage-data-summary-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 16v-4"></path>
                            <path d="M12 8h.01"></path>
                        </svg>
                    </div>

                    <div>
                        <h3><?php esc_html_e('How it works', 'wp-asset-clean-up'); ?></h3>

                        <dl>
                            <div>
                                <dt><?php esc_html_e('Destination', 'wp-asset-clean-up'); ?></dt>
                                <dd><code>assetcleanup.com</code></dd>
                            </div>

                            <div>
                                <dt><?php esc_html_e('Frequency', 'wp-asset-clean-up'); ?></dt>
                                <dd><?php esc_html_e('After opt-in, then at most weekly', 'wp-asset-clean-up'); ?></dd>
                            </div>

                            <div>
                                <dt><?php esc_html_e('Opt out', 'wp-asset-clean-up'); ?></dt>
                                <dd><?php esc_html_e('Turn the switch off and save this page', 'wp-asset-clean-up'); ?></dd>
                            </div>
                        </dl>
                    </div>
                </article>
            </div>

            <div class="wpacu-usage-data-section-heading">
                <h3><?php esc_html_e('What is included in the check-in?', 'wp-asset-clean-up'); ?></h3>

                <p>
                    <?php esc_html_e('The categories and current values below are generated from this site, so you can review them before enabling the setting.', 'wp-asset-clean-up'); ?>
                </p>
            </div>

            <div class="wpacu-usage-data-groups">
                <section class="wpacu-usage-data-group" aria-labelledby="wpacuUsageEnvironmentTitle">
                    <div class="wpacu-usage-data-group-header">
                        <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
                        <h4 id="wpacuUsageEnvironmentTitle"><?php esc_html_e('WordPress environment', 'wp-asset-clean-up'); ?></h4>
                    </div>

                    <dl class="wpacu-usage-data-list">
                        <div>
                            <dt><?php esc_html_e('PHP version', 'wp-asset-clean-up'); ?></dt>
                            <dd><?php echo esc_html(isset($trackingData['php_version']) ? $trackingData['php_version'] : ''); ?></dd>
                        </div>

                        <div>
                            <dt><?php esc_html_e('WordPress version', 'wp-asset-clean-up'); ?></dt>
                            <dd><?php echo esc_html(isset($trackingData['wp_version']) ? $trackingData['wp_version'] : ''); ?></dd>
                        </div>

                        <div>
                            <dt><?php esc_html_e('Server software', 'wp-asset-clean-up'); ?></dt>
                            <dd><?php echo esc_html(isset($trackingData['server']) ? $trackingData['server'] : ''); ?></dd>
                        </div>

                        <div>
                            <dt><?php esc_html_e('Multisite', 'wp-asset-clean-up'); ?></dt>
                            <dd><?php echo esc_html(isset($trackingData['multisite']) ? $trackingData['multisite'] : ''); ?></dd>
                        </div>

                        <div>
                            <dt><?php esc_html_e('Locale', 'wp-asset-clean-up'); ?></dt>
                            <dd><?php echo esc_html(isset($trackingData['locale']) ? $trackingData['locale'] : ''); ?></dd>
                        </div>
                    </dl>
                </section>

                <section class="wpacu-usage-data-group" aria-labelledby="wpacuUsagePluginTitle">
                    <div class="wpacu-usage-data-group-header">
                        <span class="dashicons dashicons-performance" aria-hidden="true"></span>
                        <h4 id="wpacuUsagePluginTitle"><?php esc_html_e('Feature usage', 'wp-asset-clean-up'); ?></h4>
                    </div>

                    <dl class="wpacu-usage-data-list">
                        <div>
                            <dt><?php esc_html_e('Plugin version', 'wp-asset-clean-up'); ?></dt>
                            <dd><?php echo esc_html(isset($trackingData['wpacu_version']) ? $trackingData['wpacu_version'] : ''); ?></dd>
                        </div>

                        <div>
                            <dt><?php esc_html_e('Saved settings', 'wp-asset-clean-up'); ?></dt>
                            <dd>
                                <?php echo esc_html(sprintf(
                                    _n(
                                        '%s top-level setting',
                                        '%s top-level settings',
                                        count($wpacuSettings),
                                        'wp-asset-clean-up'
                                    ),
                                    number_format_i18n(count($wpacuSettings))
                                )); ?>
                            </dd>
                        </div>

                        <div>
                            <dt><?php esc_html_e('First usage information', 'wp-asset-clean-up'); ?></dt>
                            <dd><?php esc_html_e('Included', 'wp-asset-clean-up'); ?></dd>
                        </div>

                        <div>
                            <dt><?php esc_html_e('Review-notice status', 'wp-asset-clean-up'); ?></dt>
                            <dd><?php esc_html_e('Included', 'wp-asset-clean-up'); ?></dd>
                        </div>
                    </dl>
                </section>

                <section class="wpacu-usage-data-group" aria-labelledby="wpacuUsageSiteTitle">
                    <div class="wpacu-usage-data-group-header">
                        <span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
                        <h4 id="wpacuUsageSiteTitle"><?php esc_html_e('Site configuration', 'wp-asset-clean-up'); ?></h4>
                    </div>

                    <dl class="wpacu-usage-data-list">
                        <div>
                            <dt><?php esc_html_e('Active theme', 'wp-asset-clean-up'); ?></dt>
                            <dd><?php echo esc_html(isset($trackingData['theme']) ? $trackingData['theme'] : ''); ?></dd>
                        </div>

                        <div>
                            <dt><?php esc_html_e('Active plugins', 'wp-asset-clean-up'); ?></dt>
                            <dd><?php echo esc_html(number_format_i18n(count($activePlugins))); ?></dd>
                        </div>

                        <div>
                            <dt><?php esc_html_e('Inactive plugins', 'wp-asset-clean-up'); ?></dt>
                            <dd><?php echo esc_html(number_format_i18n(count($inactivePlugins))); ?></dd>
                        </div>
                    </dl>
                </section>
            </div>

            <aside class="wpacu-usage-data-transparency-note">
                <div class="wpacu-usage-data-transparency-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>
                        <path d="M12 8v4"></path>
                        <path d="M12 16h.01"></path>
                    </svg>
                </div>

                <div>
                    <strong><?php esc_html_e('Transparency note', 'wp-asset-clean-up'); ?></strong>

                    <p>
                        <?php esc_html_e('The site URL is not added as a dedicated field or request header, and the administrator name and email are not added as tracking fields. The complete saved Asset CleanUp settings array is included and may contain custom patterns, URLs, user identifiers, or other values entered in the plugin settings. As with any HTTP request, the receiving server and its infrastructure may process the request IP address in operational logs.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>
            </aside>

            <div class="wpacu-usage-data-disclosure" id="wpacuUsageDataDisclosure">
                <button class="wpacu-usage-data-disclosure-trigger"
                        id="wpacuUsageDataDisclosureTrigger"
                        type="button"
                        aria-expanded="false"
                        aria-controls="wpacuUsageDataDisclosurePanel">

                    <span class="wpacu-usage-data-details-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 6h16"></path>
                            <path d="M4 12h16"></path>
                            <path d="M4 18h16"></path>
                        </svg>
                    </span>

                    <span class="wpacu-usage-data-disclosure-copy">
                        <strong><?php esc_html_e('Review the detailed payload from this site', 'wp-asset-clean-up'); ?></strong>
                        <small><?php esc_html_e('Advanced: saved settings, tracking metadata and plugin identifiers', 'wp-asset-clean-up'); ?></small>
                    </span>

                    <span class="wpacu-usage-data-chevron" aria-hidden="true"></span>
                </button>

                <div class="wpacu-usage-data-disclosure-panel"
                     id="wpacuUsageDataDisclosurePanel"
                     role="region"
                     aria-labelledby="wpacuUsageDataDisclosureTrigger"
                     aria-hidden="true"
                     hidden>

                    <div class="wpacu-usage-data-details-content">
                        <section class="wpacu-usage-data-raw-section">
                            <h4><?php esc_html_e('Saved plugin settings', 'wp-asset-clean-up'); ?></h4>

                            <p>
                                <?php esc_html_e('This is the settings array prepared by the current tracking implementation.', 'wp-asset-clean-up'); ?>
                            </p>

                            <pre><code><?php echo esc_html($settingsJson); ?></code></pre>
                        </section>

                        <div class="wpacu-usage-data-raw-grid">
                            <section class="wpacu-usage-data-raw-section">
                                <h4><?php esc_html_e('Additional plugin metadata', 'wp-asset-clean-up'); ?></h4>

                                <dl class="wpacu-usage-data-list wpacu-usage-data-list--raw">
                                    <div>
                                        <dt><?php esc_html_e('First usage', 'wp-asset-clean-up'); ?></dt>
                                        <dd><?php echo esc_html($firstUsageValue); ?></dd>
                                    </div>

                                    <div>
                                        <dt><?php esc_html_e('Review notice', 'wp-asset-clean-up'); ?></dt>
                                        <dd><?php echo esc_html($reviewInfoValue); ?></dd>
                                    </div>
                                </dl>
                            </section>

                            <section class="wpacu-usage-data-raw-section">
                                <h4><?php esc_html_e('Plugin identifiers', 'wp-asset-clean-up'); ?></h4>

                                <div class="wpacu-usage-data-plugin-lists">
                                    <details>
                                        <summary>
                                            <?php echo esc_html(sprintf(
                                                __('Active plugins (%s)', 'wp-asset-clean-up'),
                                                number_format_i18n(count($activePlugins))
                                            )); ?>
                                        </summary>

                                        <ul>
                                            <?php if (empty($activePlugins)) { ?>
                                                <li><?php esc_html_e('None', 'wp-asset-clean-up'); ?></li>
                                            <?php } else { foreach ($activePlugins as $pluginFile) { ?>
                                                <li><code><?php echo esc_html($pluginFile); ?></code></li>
                                            <?php } } ?>
                                        </ul>
                                    </details>

                                    <details>
                                        <summary>
                                            <?php echo esc_html(sprintf(
                                                __('Inactive plugins (%s)', 'wp-asset-clean-up'),
                                                number_format_i18n(count($inactivePlugins))
                                            )); ?>
                                        </summary>

                                        <ul>
                                            <?php if (empty($inactivePlugins)) { ?>
                                                <li><?php esc_html_e('None', 'wp-asset-clean-up'); ?></li>
                                            <?php } else { foreach ($inactivePlugins as $pluginFile) { ?>
                                                <li><code><?php echo esc_html($pluginFile); ?></code></li>
                                            <?php } } ?>
                                        </ul>
                                    </details>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
(function () {
    'use strict';

    var root = document.getElementById('wpacu-usage-data-settings');
    var input = document.getElementById('wpacu_allow_usage_tracking');
    var state = document.getElementById('wpacuUsageDataState');

    if (! root || ! input || ! state) {
        return;
    }

    function renderUsageDataState() {
        root.classList.toggle('wpacu-is-enabled', input.checked);

        state.textContent = input.checked
            ? <?php echo wp_json_encode(__('Sharing enabled', 'wp-asset-clean-up')); ?>
            : <?php echo wp_json_encode(__('Not shared', 'wp-asset-clean-up')); ?>;
    }

    input.addEventListener('change', renderUsageDataState);
    renderUsageDataState();
}());

(function () {
    'use strict';

    /*
     * Set this to false before this script runs if you want
     * instant expand/collapse without animation.
     */
    if (typeof window.wpacuUsageDataDetailsUseEffects !== 'boolean') {
        window.wpacuUsageDataDetailsUseEffects = true;
    }

    var root = document.getElementById('wpacu-usage-data-settings');

    if (! root) {
        return;
    }

    var disclosure = document.getElementById('wpacuUsageDataDisclosure');
    var trigger = document.getElementById('wpacuUsageDataDisclosureTrigger');
    var panel = document.getElementById('wpacuUsageDataDisclosurePanel');

    if (! disclosure || ! trigger || ! panel) {
        return;
    }

    var heightAnimation = null;
    var targetOpen = false;

    var animationDuration = 190;
    var animationEasing = 'cubic-bezier(0.22, 0.61, 0.36, 1)';

    function userPrefersReducedMotion() {
        return window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function effectsAreEnabled() {
        return window.wpacuUsageDataDetailsUseEffects !== false &&
            ! userPrefersReducedMotion() &&
            typeof panel.animate === 'function';
    }

    function cancelCurrentAnimation() {
        var currentAnimation = heightAnimation;

        heightAnimation = null;

        if (currentAnimation) {
            currentAnimation.onfinish = null;
            currentAnimation.cancel();
        }
    }

    function setSemanticState(open) {
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        disclosure.classList.toggle('is-open', open);
    }

    function finishAnimation(open) {
        var finishedAnimation = heightAnimation;

        heightAnimation = null;
        targetOpen = open;

        disclosure.classList.remove('is-animating');

        setSemanticState(open);

        panel.style.height = '';
        panel.style.overflow = '';

        if (! open) {
            panel.hidden = true;
        }

        if (finishedAnimation) {
            finishedAnimation.onfinish = null;
            finishedAnimation.cancel();
        }
    }

    function setStateInstant(open) {
        cancelCurrentAnimation();

        targetOpen = open;
        setSemanticState(open);

        panel.style.height = '';
        panel.style.overflow = '';
        panel.hidden = ! open;
    }

    function animatePanel(open) {
        /*
         * The panel is a permanently width-constrained block.
         * We never toggle a native <details open> state, so the
         * large PRE cannot participate in sizing the outer layout
         * at the moment the animation starts.
         */
        if (open) {
            panel.hidden = false;
        }

        var startHeight = panel.getBoundingClientRect().height;

        /*
         * When opening from [hidden], the rendered height becomes the
         * natural content height immediately after removing [hidden].
         * Force the visual starting height back to zero before measuring.
         */
        if (open && startHeight > 0 && ! disclosure.classList.contains('is-open')) {
            startHeight = 0;
        }

        cancelCurrentAnimation();

        panel.style.height = startHeight + 'px';
        panel.style.overflow = 'hidden';

        disclosure.classList.add('is-animating');

        /*
         * Update the trigger/chevron immediately, but keep the panel
         * available in layout so scrollHeight can be measured.
         */
        setSemanticState(open);

        /*
         * Force the browser to commit the starting height.
         */
        panel.offsetHeight;

        var endHeight = open ? panel.scrollHeight : 0;

        heightAnimation = panel.animate(
            [
                { height: startHeight + 'px' },
                { height: endHeight + 'px' }
            ],
            {
                duration: animationDuration,
                easing: animationEasing,
                fill: 'forwards'
            }
        );

        heightAnimation.onfinish = function () {
            finishAnimation(open);
        };
    }

    trigger.addEventListener('click', function () {
        targetOpen = ! targetOpen;

        if (! effectsAreEnabled()) {
            setStateInstant(targetOpen);
            return;
        }

        /*
         * If clicked again during an animation, start from the
         * currently rendered panel height. This makes reversal smooth.
         */
        animatePanel(targetOpen);
    });

    window.addEventListener('resize', function () {
        if (heightAnimation) {
            finishAnimation(targetOpen);
        }
    });

    /*
     * Initial closed state.
     */
    setStateInstant(false);
}());
</script>
