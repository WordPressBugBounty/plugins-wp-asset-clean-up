<?php
/*
 * No direct access to this file
 */
if ( ! isset($data) ) {
    exit;
}

$criticalCssIsGlobalDisabled = ! empty($data['critical_css_is_global_disabled']);
$criticalCssRuleStats = isset($data['critical_css_rule_stats']) && is_array($data['critical_css_rule_stats'])
    ? $data['critical_css_rule_stats']
    : array();
$criticalCssSavedRules   = isset($criticalCssRuleStats['total_count']) ? (int)$criticalCssRuleStats['total_count'] : 0;
$criticalCssEnabledRules = isset($criticalCssRuleStats['enabled_count']) ? (int)$criticalCssRuleStats['enabled_count'] : 0;
$criticalCssSettingsUrl  = admin_url(
    'admin.php?page=' . WPACU_PLUGIN_ID . '_settings'
    . '&wpacu_selected_tab_area=wpacu-setting-plugin-usage-settings'
    . '&wpacu_selected_sub_tab_area=wpacu-plugin-usage-settings-assets-management'
    . '#wpacu-cssjs-critical-css'
);

if ($criticalCssSavedRules > 0) {
    $criticalCssRulesSummary = sprintf(
        __('%1$s saved · %2$s enabled', 'wp-asset-clean-up'),
        number_format_i18n($criticalCssSavedRules),
        number_format_i18n($criticalCssEnabledRules)
    );
} else {
    $criticalCssRulesSummary = __('No saved rules yet', 'wp-asset-clean-up');
}

$criticalCssInitialMessage = $criticalCssIsGlobalDisabled
    ? __('Critical CSS output is paused. All saved rules remain available.', 'wp-asset-clean-up')
    : __('Critical CSS output is active for enabled matching rules.', 'wp-asset-clean-up');
?>
<section id="wpacu-critical-css-global-control"
         class="wpacu-critical-css-master-panel <?php echo $criticalCssIsGlobalDisabled ? 'is-paused' : 'is-active'; ?>"
         data-wpacu-critical-css-global-control="1"
         data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-action="<?php echo esc_attr(WPACU_PLUGIN_ID . '_update_critical_css_global_status'); ?>"
         data-nonce="<?php echo esc_attr(wp_create_nonce('wpacu_update_critical_css_global_status')); ?>"
         aria-labelledby="wpacuCriticalCssGlobalTitle">
    <div class="wpacu-critical-css-master-panel__top">
        <div class="wpacu-critical-css-master-panel__control">
            <label class="wpacu_switch" for="wpacu_critical_css_global_status">
                <input id="wpacu_critical_css_global_status"
                       type="checkbox"
                       value="on"
                       aria-describedby="wpacuCriticalCssGlobalFeedback"
                       data-saved-state="<?php echo $criticalCssIsGlobalDisabled ? 'off' : 'on'; ?>"
                    <?php checked( ! $criticalCssIsGlobalDisabled); ?> />
                <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
            </label>

            <label class="wpacu-critical-css-master-panel__control-copy" for="wpacu_critical_css_global_status">
                <strong id="wpacuCriticalCssGlobalTitle"><?php esc_html_e('Enable all Critical CSS rules', 'wp-asset-clean-up'); ?></strong>
                <span><?php esc_html_e('Changes save immediately.', 'wp-asset-clean-up'); ?></span>
            </label>
        </div>

        <div class="wpacu-critical-css-master-panel__copy">
            <p><?php esc_html_e('Turn this off temporarily while troubleshooting first-render or layout problems. Existing rules can still be viewed and edited while output is paused.', 'wp-asset-clean-up'); ?></p>
        </div>

        <div class="wpacu-critical-css-master-panel__meta">
            <span class="wpacu-critical-css-master-panel__saved-badge"><?php esc_html_e('Rules stay saved', 'wp-asset-clean-up'); ?></span>
            <span id="wpacuCriticalCssGlobalState"
                  class="wpacu-critical-css-master-status <?php echo $criticalCssIsGlobalDisabled ? 'is-paused' : 'is-active'; ?>"
                  aria-live="polite"
                  data-active-label="<?php esc_attr_e('Active', 'wp-asset-clean-up'); ?>"
                  data-paused-label="<?php esc_attr_e('Paused', 'wp-asset-clean-up'); ?>"
                  data-saving-label="<?php esc_attr_e('Saving…', 'wp-asset-clean-up'); ?>">
                <?php echo $criticalCssIsGlobalDisabled ? esc_html__('Paused', 'wp-asset-clean-up') : esc_html__('Active', 'wp-asset-clean-up'); ?>
            </span>
            <span class="wpacu-critical-css-master-panel__rule-count"><?php echo esc_html($criticalCssRulesSummary); ?></span>
            <a href="<?php echo esc_url($criticalCssSettingsUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('View global preference', 'wp-asset-clean-up'); ?>">
                <span class="dashicons dashicons-external" aria-hidden="true"></span>
            </a>
        </div>
    </div>

    <p id="wpacuCriticalCssGlobalFeedback"
       class="wpacu-critical-css-master-feedback"
       aria-live="polite">
        <span data-wpacu-critical-css-feedback-message="1"><?php echo esc_html($criticalCssInitialMessage); ?></span>
        <a class="wpacu-critical-css-master-feedback__help" target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=608">
            <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
            <?php esc_html_e("What's critical CSS & and how to implement it?", 'wp-asset-clean-up'); ?>
        </a>
    </p>
</section>

<script>
(function () {
    'use strict';

    function initCriticalCssGlobalControl() {
        var root = document.querySelector('[data-wpacu-critical-css-global-control="1"]');

        if (! root || root.getAttribute('data-initialized') === '1') {
            return;
        }

        var toggle = document.getElementById('wpacu_critical_css_global_status');
        var badge = document.getElementById('wpacuCriticalCssGlobalState');
        var feedbackRow = document.getElementById('wpacuCriticalCssGlobalFeedback');
        var feedback = root.querySelector('[data-wpacu-critical-css-feedback-message="1"]');

        if (! toggle || ! badge || ! feedbackRow || ! feedback) {
            return;
        }

        root.setAttribute('data-initialized', '1');

        var strings = {
            active: badge.getAttribute('data-active-label'),
            paused: badge.getAttribute('data-paused-label'),
            saving: badge.getAttribute('data-saving-label'),
            activeMessage: <?php echo wp_json_encode(__('Critical CSS output is active for enabled matching rules.', 'wp-asset-clean-up')); ?>,
            pausedMessage: <?php echo wp_json_encode(__('Critical CSS output is paused. All saved rules remain available.', 'wp-asset-clean-up')); ?>,
            errorMessage: <?php echo wp_json_encode(__('The Critical CSS status could not be updated. Refresh the page and try again.', 'wp-asset-clean-up')); ?>
        };

        function setVisualState(status, message, isError) {
            var isActive = status === 'on';

            root.classList.toggle('is-active', isActive);
            root.classList.toggle('is-paused', ! isActive);
            root.classList.remove('is-saving');
            root.setAttribute('aria-busy', 'false');

            badge.classList.toggle('is-active', isActive);
            badge.classList.toggle('is-paused', ! isActive);
            badge.classList.remove('is-saving');
            badge.textContent = isActive ? strings.active : strings.paused;

            feedbackRow.classList.toggle('is-error', Boolean(isError));
            feedback.textContent = message || (isActive ? strings.activeMessage : strings.pausedMessage);
        }

        function restoreSavedState(message) {
            var savedState = toggle.getAttribute('data-saved-state') === 'off' ? 'off' : 'on';

            toggle.checked = savedState === 'on';
            setVisualState(savedState, message || strings.errorMessage, true);
        }

        toggle.addEventListener('change', function () {
            var requestedStatus = toggle.checked ? 'on' : 'off';
            var request = new XMLHttpRequest();
            var requestBody = [
                'action=' + encodeURIComponent(root.getAttribute('data-action') || ''),
                'wpacu_nonce=' + encodeURIComponent(root.getAttribute('data-nonce') || ''),
                'wpacu_status=' + encodeURIComponent(requestedStatus)
            ].join('&');

            toggle.disabled = true;
            root.classList.add('is-saving');
            root.setAttribute('aria-busy', 'true');
            feedbackRow.classList.remove('is-error');
            feedback.textContent = strings.saving;
            badge.classList.remove('is-active', 'is-paused');
            badge.classList.add('is-saving');
            badge.textContent = strings.saving;

            request.open('POST', root.getAttribute('data-ajax-url'), true);
            request.timeout = 15000;
            request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

            request.onreadystatechange = function () {
                var response;
                var responseMessage = '';

                if (request.readyState !== 4) {
                    return;
                }

                toggle.disabled = false;

                try {
                    response = JSON.parse(request.responseText);
                } catch (error) {
                    response = null;
                }

                if (request.status >= 200 && request.status < 300 && response && response.success) {
                    var savedStatus = response.data && response.data.status === 'off' ? 'off' : 'on';

                    responseMessage = response.data && response.data.message ? response.data.message : '';
                    toggle.checked = savedStatus === 'on';
                    toggle.setAttribute('data-saved-state', savedStatus);
                    setVisualState(savedStatus, responseMessage, false);
                    return;
                }

                if (response && response.data && response.data.message) {
                    responseMessage = response.data.message;
                }

                restoreSavedState(responseMessage || strings.errorMessage);
            };

            request.onerror = function () {
                toggle.disabled = false;
                restoreSavedState(strings.errorMessage);
            };
            request.ontimeout = request.onerror;

            request.send(requestBody);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCriticalCssGlobalControl);
    } else {
        initCriticalCssGlobalControl();
    }
}());
</script>
