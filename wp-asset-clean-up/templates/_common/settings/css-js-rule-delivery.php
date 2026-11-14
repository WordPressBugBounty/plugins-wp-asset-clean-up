<?php
if (! isset($data, $settingsName)) {
    exit;
}
$cssJsRulesEnabled = ! isset($data['css_js_manager_rules_status']) || $data['css_js_manager_rules_status'] !== 'off';
?>
<section id="wpacu-cssjs-rule-delivery" class="wpacu-cssjs-section" aria-labelledby="wpacuCssJsRuleDeliveryTitle">
    <div class="wpacu-cssjs-scope-heading wpacu-cssjs-scope-heading--manager">
        <span class="wpacu-cssjs-scope-heading__icon" aria-hidden="true"><span class="dashicons dashicons-controls-play"></span></span>
        <div>
            <span class="wpacu-cssjs-scope-heading__eyebrow"><?php esc_html_e('Saved asset rules', 'wp-asset-clean-up'); ?></span>
            <h3 id="wpacuCssJsRuleDeliveryTitle"><?php esc_html_e('CSS/JS rule delivery', 'wp-asset-clean-up'); ?></h3>
            <p><?php esc_html_e('Pause saved asset rules while troubleshooting. The manager stays available and your rules stay saved.', 'wp-asset-clean-up'); ?></p>
        </div>
    </div>
    <article class="wpacu-cssjs-setting-card wpacu-cssjs-critical-css-card <?php echo $cssJsRulesEnabled ? 'is-active' : 'is-paused'; ?>">
        <div class="wpacu-cssjs-master-row wpacu-cssjs-critical-css-master-row">
            <div class="wpacu-cssjs-master-row__control">
                <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[css_js_manager_rules_status]" value="off" />
                <label class="wpacu_switch" for="wpacu_css_js_manager_rules_status">
                    <input id="wpacu_css_js_manager_rules_status" type="checkbox" name="<?php echo esc_attr($settingsName); ?>[css_js_manager_rules_status]" value="on" <?php checked($cssJsRulesEnabled); ?> aria-describedby="wpacuCssJsRulesHelp" />
                    <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                </label>
                <label class="wpacu-cssjs-control-label" for="wpacu_css_js_manager_rules_status"><strong><?php esc_html_e('Apply CSS/JS rules', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Enabled by default. Save after changing this switch.', 'wp-asset-clean-up'); ?></span></label>
            </div>
            <div class="wpacu-cssjs-master-row__copy">
                <span class="wpacu-cssjs-kicker"><?php esc_html_e('Saved asset rules', 'wp-asset-clean-up'); ?></span>
                <h4><?php esc_html_e('Keep CSS/JS asset rules enabled', 'wp-asset-clean-up'); ?></h4>
                <p id="wpacuCssJsRulesHelp"><?php esc_html_e('Controls unload rules and load exceptions, async/defer, preload, media-query loading and position changes. Applies to enqueued and hardcoded assets.', 'wp-asset-clean-up'); ?></p>
                <p><?php esc_html_e('General optimization settings such as minify/combine, Critical CSS, Alternative Assets and Plugins Manager keep their own controls.', 'wp-asset-clean-up'); ?></p>
                <div class="wpacu-cssjs-critical-css-meta">
                    <span class="wpacu-cssjs-critical-css-status <?php echo $cssJsRulesEnabled ? 'is-active' : 'is-paused'; ?>" aria-live="polite" data-active-label="<?php esc_attr_e('Active', 'wp-asset-clean-up'); ?>" data-paused-label="<?php esc_attr_e('Paused', 'wp-asset-clean-up'); ?>"><?php echo $cssJsRulesEnabled ? esc_html__('Active', 'wp-asset-clean-up') : esc_html__('Paused', 'wp-asset-clean-up'); ?></span>
                    <span class="wpacu-cssjs-critical-css-saved"><span class="dashicons dashicons-saved" aria-hidden="true"></span><?php esc_html_e('Rules stay saved', 'wp-asset-clean-up'); ?></span>
                </div>
            </div>
        </div>
    </article>
</section>
<script>
(function () {
    var section = document.getElementById('wpacu-cssjs-rule-delivery');
    var input = document.getElementById('wpacu_css_js_manager_rules_status');
    if (!section || !input) { return; }
    var card = section.querySelector('article');
    var status = section.querySelector('.wpacu-cssjs-critical-css-status');
    var statusLink = document.getElementById('wpacu-cssjs-rule-status-link');
    input.addEventListener('change', function () {
        [card, status].forEach(function (element) {
            element.classList.toggle('is-active', input.checked);
            element.classList.toggle('is-paused', !input.checked);
        });
        status.textContent = status.getAttribute(input.checked ? 'data-active-label' : 'data-paused-label');
        if (statusLink) {
            statusLink.classList.toggle('is-paused', !input.checked);
            var icon = statusLink.querySelector('.dashicons');
            var badge = statusLink.querySelector('.wpacu-cssjs-rule-status-badge');
            if (icon) {
                icon.classList.toggle('dashicons-controls-play', input.checked);
                icon.classList.toggle('dashicons-controls-pause', !input.checked);
            }
            if (badge) {
                badge.classList.toggle('is-active', input.checked);
                badge.textContent = badge.getAttribute(input.checked ? 'data-active-label' : 'data-paused-label');
            }
        }
    });
}());
</script>
