<?php
if (! isset($data)) { exit; }
$cssJsRulesEnabled  = \WpAssetCleanUp\Main::instance()->areAssetRulesEnabled();
$cssJsActiveMessage = \WpAssetCleanUp\Admin\SettingsAdmin::getCssJsGlobalStatusMessage('on');
$cssJsPausedMessage = \WpAssetCleanUp\Admin\SettingsAdmin::getCssJsGlobalStatusMessage('off');
?>
<section id="wpacu-css-js-global-control" class="wpacu-css-js-master-panel is-initializing<?php echo $cssJsRulesEnabled ? '' : ' is-paused'; ?>"
         aria-busy="true"
         data-enabled="<?php echo $cssJsRulesEnabled ? 'on' : 'off'; ?>"
         data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-action="<?php echo esc_attr(WPACU_PLUGIN_ID . '_update_css_js_global_status'); ?>"
         data-nonce="<?php echo esc_attr(wp_create_nonce('wpacu_update_css_js_global_status')); ?>"
         aria-labelledby="wpacuCssJsGlobalTitle">
    <span data-master-loading role="status" aria-label="<?php esc_attr_e('Loading rule controls…', 'wp-asset-clean-up'); ?>"></span>
    <span id="wpacuCssJsGlobalTitle"><?php esc_html_e('CSS/JS rules:', 'wp-asset-clean-up'); ?> <span id="wpacuCssJsGlobalState" aria-live="polite"><?php echo $cssJsRulesEnabled ? esc_html__('Active', 'wp-asset-clean-up') : esc_html__('Paused', 'wp-asset-clean-up'); ?></span></span>
    <button id="wpacu_css_js_global_status" class="wpacu-css-js-master-action" type="button" disabled
            aria-label="<?php echo $cssJsRulesEnabled ? esc_attr__('Pause CSS/JS rules', 'wp-asset-clean-up') : esc_attr__('Enable CSS/JS rules', 'wp-asset-clean-up'); ?>"><?php echo $cssJsRulesEnabled ? esc_html__('Pause', 'wp-asset-clean-up') : esc_html__('Enable', 'wp-asset-clean-up'); ?></button>
</section>
<script>
(function () {
    'use strict';
    function initCssJsGlobalControl() {
        var root = document.getElementById('wpacu-css-js-global-control');
        if (!root || root.getAttribute('data-initialized') === '1') { return; }
        root.setAttribute('data-initialized', '1');
        var toggle = document.getElementById('wpacu_css_js_global_status');
        var feedback = document.getElementById('wpacuCssJsGlobalFeedback');
        var savedEnabled = root.getAttribute('data-enabled') === 'on';
        var state = document.getElementById('wpacuCssJsGlobalState');
        var activeLabel = <?php echo wp_json_encode(__('Active', 'wp-asset-clean-up')); ?>;
        var pausedLabel = <?php echo wp_json_encode(__('Paused', 'wp-asset-clean-up')); ?>;
        var pauseLabel = <?php echo wp_json_encode(__('Pause', 'wp-asset-clean-up')); ?>;
        var enableLabel = <?php echo wp_json_encode(__('Enable', 'wp-asset-clean-up')); ?>;
        var pauseActionLabel = <?php echo wp_json_encode(__('Pause CSS/JS rules', 'wp-asset-clean-up')); ?>;
        var enableActionLabel = <?php echo wp_json_encode(__('Enable CSS/JS rules', 'wp-asset-clean-up')); ?>;
        var activeMessage = <?php echo wp_json_encode($cssJsActiveMessage); ?>;
        var pausedMessage = <?php echo wp_json_encode($cssJsPausedMessage); ?>;
        var errorMessage = <?php echo wp_json_encode(__('The CSS/JS rules status could not be updated. Please try again.', 'wp-asset-clean-up')); ?>;

        toggle.addEventListener('click', function () {
            var requestedStatus = savedEnabled ? 'off' : 'on';
            var request = new XMLHttpRequest();
            var finished = false;
            var spinner = document.getElementById('wpacu-main-loading-spinner');
            toggle.disabled = true;
            root.setAttribute('aria-busy', 'true');
            if (spinner) {
                var spinnerText = document.getElementById('wpacu-main-loading-spinner-text');
                var updatingText = spinner.querySelector('[data-wpacu-updating-text="1"]');
                if (spinnerText && updatingText) { spinnerText.textContent = updatingText.textContent.trim(); }
                spinner.classList.remove('wpacu_hide');
            }
            function finish(success, message) {
                if (finished) { return; }
                finished = true;
                if (success) { savedEnabled = requestedStatus === 'on'; }
                root.setAttribute('data-enabled', savedEnabled ? 'on' : 'off');
                root.classList.toggle('is-paused', !savedEnabled);
                state.textContent = savedEnabled ? activeLabel : pausedLabel;
                toggle.textContent = savedEnabled ? pauseLabel : enableLabel;
                toggle.setAttribute('aria-label', savedEnabled ? pauseActionLabel : enableActionLabel);
                toggle.disabled = false;
                feedback.textContent = message || (success ? (savedEnabled ? activeMessage : pausedMessage) : errorMessage);
                feedback.classList.toggle('is-error', !success);
                feedback.hidden = Boolean(success && savedEnabled);
                var content = document.getElementById('wpacu-css-js-content');
                if (content) { content.classList.toggle('is-paused', !savedEnabled); }
                root.setAttribute('aria-busy', 'false');
                if (spinner) { spinner.classList.add('wpacu_hide'); }
            }
            request.open('POST', root.getAttribute('data-ajax-url'), true);
            request.timeout = 15000;
            request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            request.onload = function () {
                var response;
                try { response = JSON.parse(request.responseText); } catch (error) { response = null; }
                var success = request.status >= 200 && request.status < 300 && response && response.success === true
                    && response.data && response.data.status === requestedStatus;
                finish(Boolean(success), response && response.data && response.data.message);
            };
            request.onerror = request.ontimeout = request.onabort = function () { finish(false); };
            request.send('action=' + encodeURIComponent(root.getAttribute('data-action'))
                + '&wpacu_nonce=' + encodeURIComponent(root.getAttribute('data-nonce'))
                + '&wpacu_status=' + encodeURIComponent(requestedStatus));
        });
        toggle.disabled = false;
        root.querySelector('[data-master-loading]').hidden = true;
        root.classList.remove('is-initializing');
        root.setAttribute('aria-busy', 'false');
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCssJsGlobalControl);
    } else { initCssJsGlobalControl(); }
}());
</script>
