<?php
// The original checkbox and AJAX handler remain responsible for persistence.
if (! isset($wpacuMasterBox)) { return; }
?>
<div class="wpacu-css-js-master-panel wpacu-manager-status-box is-initializing<?php echo $wpacuMasterBox['enabled'] ? '' : ' is-paused'; ?>"
     aria-busy="true"
     data-master-input="<?php echo esc_attr($wpacuMasterBox['input']); ?>"
     data-master-feedback="<?php echo esc_attr($wpacuMasterBox['feedback']); ?>">
    <span data-master-loading role="status" aria-label="<?php esc_attr_e('Loading rule controls…', 'wp-asset-clean-up'); ?>"></span>
    <span><?php echo esc_html($wpacuMasterBox['label']); ?>: <strong data-master-state aria-live="polite"><?php echo $wpacuMasterBox['enabled'] ? esc_html__('Active', 'wp-asset-clean-up') : esc_html__('Paused', 'wp-asset-clean-up'); ?></strong></span>
    <button class="wpacu-css-js-master-action" type="button" disabled><?php echo $wpacuMasterBox['enabled'] ? esc_html__('Pause', 'wp-asset-clean-up') : esc_html__('Enable', 'wp-asset-clean-up'); ?></button>
</div>
<script>
(function () {
    'use strict';
    var script = document.currentScript;
    var box = script.previousElementSibling;
    var toolbar = document.querySelector('[data-wpacu-master-toolbar]');
    if (toolbar) { toolbar.appendChild(box); }

    function init() {
        var input = document.getElementById(box.getAttribute('data-master-input'));
        var feedback = document.getElementById(box.getAttribute('data-master-feedback'));
        var button = box.querySelector('button');
        var state = box.querySelector('[data-master-state]');
        var spinner = document.getElementById('wpacu-main-loading-spinner');
        var pending = false;
        var ready = false;
        var requestedEnabled;
        var initialFeedback = feedback ? feedback.textContent : '';
        var isPluginsManager = input && input.hasAttribute('data-wpacu-setting');
        var labels = <?php echo wp_json_encode(array(
            'active' => __('Active', 'wp-asset-clean-up'),
            'paused' => __('Paused', 'wp-asset-clean-up'),
            'pause' => __('Pause', 'wp-asset-clean-up'),
            'enable' => __('Enable', 'wp-asset-clean-up'),
            'pauseAction' => sprintf(__('Pause %s', 'wp-asset-clean-up'), $wpacuMasterBox['label']),
            'enableAction' => sprintf(__('Enable %s', 'wp-asset-clean-up'), $wpacuMasterBox['label']),
            'error' => __('The setting could not be saved. Please try again.', 'wp-asset-clean-up')
        )); ?>;
        if (!input) { return; }

        function sync(isError) {
            var enabled = input.checked;
            box.classList.toggle('is-paused', !enabled);
            state.textContent = enabled ? labels.active : labels.paused;
            button.textContent = enabled ? labels.pause : labels.enable;
            button.setAttribute('aria-label', enabled ? labels.pauseAction : labels.enableAction);
            button.disabled = false;
            box.setAttribute('aria-busy', 'false');
            if (feedback) {
                if (isPluginsManager) { feedback.textContent = isError ? labels.error : initialFeedback; }
                feedback.hidden = enabled && !isError;
                feedback.classList.toggle('is-error', Boolean(isError));
            }
        }
        function showWhenReady() {
            if (ready || input.getAttribute('data-wpacu-master-ready') !== '1') { return; }
            ready = true;
            sync(false);
            box.querySelector('[data-master-loading]').hidden = true;
            box.classList.remove('is-initializing');
        }
        function finishWhenReady() {
            if (!pending || input.disabled || (spinner && !spinner.classList.contains('wpacu_hide'))) { return; }
            pending = false;
            sync(input.checked !== requestedEnabled);
        }
        // Each existing handler restores the checkbox on failure, then closes its loader.
        var observer = new MutationObserver(function () { showWhenReady(); finishWhenReady(); });
        observer.observe(input, {attributes: true, attributeFilter: ['disabled', 'data-wpacu-master-ready']});
        if (spinner) { observer.observe(spinner, {attributes: true, attributeFilter: ['class']}); }
        // Plugins Manager can also be rendered without the shared loader.
        if (isPluginsManager && window.jQuery) {
            window.jQuery(document).on('ajaxComplete.wpacuMasterStatus', function (event, xhr, settings) {
                var key = 'wpacu_setting_key=' + encodeURIComponent(input.getAttribute('data-wpacu-setting'));
                if (typeof settings.data === 'string' && settings.data.split('&').indexOf(key) !== -1) {
                    finishWhenReady();
                }
            });
        }
        button.addEventListener('click', function () {
            if (!ready || pending) { return; }
            pending = true;
            requestedEnabled = !input.checked;
            button.disabled = true;
            box.setAttribute('aria-busy', 'true');
            input.click();
        });
        showWhenReady();
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
    else { init(); }
}());
</script>
