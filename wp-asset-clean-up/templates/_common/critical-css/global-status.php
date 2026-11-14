<?php
if (! isset($data)) { exit; }
$criticalCssIsGlobalDisabled = ! empty($data['critical_css_is_global_disabled']);
$criticalCssInitialMessage = $criticalCssIsGlobalDisabled
    ? __('Critical CSS output is paused. All saved rules remain available.', 'wp-asset-clean-up')
    : __('Critical CSS output is active for enabled matching rules.', 'wp-asset-clean-up');
?>
<section id="wpacu-critical-css-global-control"
         class="wpacu-critical-css-master-panel <?php echo esc_attr(\WpAssetCleanUp\Settings::getInputStyleCssClasses(\WpAssetCleanUp\Main::instance()->settings)); ?> <?php echo $criticalCssIsGlobalDisabled ? 'is-paused' : 'is-active'; ?>"
         data-wpacu-critical-css-global-control="1"
         data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-action="<?php echo esc_attr(WPACU_PLUGIN_ID . '_update_critical_css_global_status'); ?>"
         data-nonce="<?php echo esc_attr(wp_create_nonce('wpacu_update_critical_css_global_status')); ?>"
         aria-labelledby="wpacuCriticalCssGlobalTitle">
    <span id="wpacuCriticalCssGlobalTitle" hidden><?php esc_html_e('Critical CSS rules', 'wp-asset-clean-up'); ?></span>
    <input id="wpacu_critical_css_global_status" type="checkbox" data-wpacu-master-input hidden tabindex="-1" aria-hidden="true"
           value="on" data-saved-state="<?php echo $criticalCssIsGlobalDisabled ? 'off' : 'on'; ?>" <?php checked(! $criticalCssIsGlobalDisabled); ?> />
    <?php
    $wpacuMasterBox = array('input' => 'wpacu_critical_css_global_status', 'feedback' => 'wpacuCriticalCssGlobalFeedback',
        'label' => __('Critical CSS rules', 'wp-asset-clean-up'), 'enabled' => !$criticalCssIsGlobalDisabled);
    require dirname(__DIR__) . '/manager-status-box.php';
    ?>
    <span id="wpacuCriticalCssGlobalState" hidden
          data-active-label="<?php esc_attr_e('Active', 'wp-asset-clean-up'); ?>"
          data-paused-label="<?php esc_attr_e('Paused', 'wp-asset-clean-up'); ?>"
          data-saving-label="<?php esc_attr_e('Saving…', 'wp-asset-clean-up'); ?>"></span>
    <span id="wpacuCriticalCssGlobalFeedback" class="wpacu-critical-css-master-feedback" aria-live="polite"<?php if (!$criticalCssIsGlobalDisabled) { ?> hidden<?php } ?>>
        <span data-wpacu-critical-css-feedback-message="1"><?php echo esc_html($criticalCssInitialMessage); ?></span>
    </span>
    <a class="wpacu-critical-css-master-feedback__help" target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=608">
        <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
        <span class="wpacu-critical-css-master-feedback__help-text"><?php esc_html_e('About Critical CSS', 'wp-asset-clean-up'); ?></span>
    </a>
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

        var box = document.querySelector('[data-master-input="wpacu_critical_css_global_status"]');
        var help = root.querySelector('.wpacu-critical-css-master-feedback__help');
        var toolbar = document.querySelector('[data-wpacu-master-toolbar]');
        if (box && help && toolbar) {
            var tools = document.createElement('div');
            tools.className = 'wpacu-manager-status-tools';
            toolbar.insertBefore(tools, box);
            tools.appendChild(help);
            tools.appendChild(box);
            root.classList.add('wpacu-critical-css-control-relocated');
        }

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
            var content = document.getElementById('wpacu-critical-css-content');
            if (content) {
                content.classList.toggle('is-paused', ! isActive);
            }

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

            var spinner = document.getElementById('wpacu-main-loading-spinner');
            if (spinner) {
                spinner.classList.add('wpacu_hide');
            }
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
            var spinner = document.getElementById('wpacu-main-loading-spinner');
            if (spinner) {
                var spinnerText = document.getElementById('wpacu-main-loading-spinner-text');
                var updatingText = spinner.querySelector('[data-wpacu-updating-text="1"]');
                if (spinnerText && updatingText) {
                    spinnerText.textContent = updatingText.textContent.trim();
                }
                spinner.classList.remove('wpacu_hide');
            }

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
        toggle.setAttribute('data-wpacu-master-ready', '1');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCriticalCssGlobalControl);
    } else {
        initCriticalCssGlobalControl();
    }
}());
</script>
