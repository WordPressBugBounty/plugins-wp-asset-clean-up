<?php
if ( ! isset($wpacuNoLoadInTargetPage)) {
    exit;
}

?>
<div id="wpacu-update-button-area" class="no-left-margin wpacu-assets-manager-save-dock wpacu-assets-loading">
    <div class="wpacu-assets-manager-save-actions">
        <p class="submit"><input type="submit" name="submit" class="button button-primary <?php if ( ! $wpacuNoLoadInTargetPage) { ?> hidden <?php } ?>" value="<?php echo esc_attr($wpacuNoLoadInTargetPage ? __('Save Page Options', 'wp-asset-clean-up') : __('Save Changes', 'wp-asset-clean-up')); ?>" data-saving-label="<?php esc_attr_e('Saving changes...', 'wp-asset-clean-up'); ?>"></p>
        <div id="wpacu-updating-settings" class="wpacu-assets-manager-save-spinner" aria-hidden="true">
            <img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" width="20" height="20" alt="" />
        </div>
    </div>
    <div class="wpacu-assets-manager-save-summary" aria-live="polite" aria-atomic="true">
        <span class="wpacu-assets-manager-unsaved-summary" data-wpacu-assets-unsaved-summary>
            <span class="wpacu-assets-manager-unsaved-dot" aria-hidden="true"></span>
            <span data-wpacu-assets-unsaved-text>(0) <?php esc_html_e('No unsaved changes', 'wp-asset-clean-up'); ?></span>
        </span>
    </div>
    <div class="wpacu-assets-manager-save-copy">
        <strong><?php esc_html_e("Don't forget to save changes", 'wp-asset-clean-up'); ?></strong>
        <span><?php echo esc_html($wpacuNoLoadInTargetPage ? __('Page Options are applied only after saving.', 'wp-asset-clean-up') : __('CSS/JS unload and load rules are applied only after saving.', 'wp-asset-clean-up')); ?></span>
    </div>
</div>
<script>
(function () {
    'use strict';
    var dock = document.currentScript.previousElementSibling;
    var form = dock ? dock.closest('form') : null;
    var submit = dock ? dock.querySelector('input[type="submit"]') : null;
    var spinner = dock ? dock.querySelector('.wpacu-assets-manager-save-spinner') : null;
    var summary = dock ? dock.querySelector('[data-wpacu-assets-unsaved-summary]') : null;
    var output = summary ? summary.querySelector('[data-wpacu-assets-unsaved-text]') : null;
    var initialState = null;
    var timer = 0;
    if (! form || ! submit || ! summary || ! output) { return; }
    function getState() {
        var state = {};
        Array.prototype.forEach.call(form.elements, function (control) {
            var type = (control.type || '').toLowerCase();
            if (! control.name || type === 'submit' || type === 'button' || type === 'reset' || type === 'file' || control.name.indexOf('nonce') !== -1 || control.name === '_wp_http_referer') { return; }
            if (/^wpacu_handle_(?:unload|load)_regex\[.+\]\[(?:enable|value)\]$/.test(control.name)) { return; }
            if (/\[unload_post_type_via_tax\].*\[(?:enable|values)\](?:\[\])?$/.test(control.name) || /\[media_query_load\]\[(?:enable|value)\]$/.test(control.name)) { return; }
            if (! state[control.name]) { state[control.name] = []; }
            if (type === 'checkbox' || type === 'radio') {
                if (control.checked) { state[control.name].push(String(control.value)); }
                return;
            }
            if (type === 'select-multiple') {
                Array.prototype.forEach.call(control.options, function (option) { if (option.selected) { state[control.name].push(String(option.value)); } });
                return;
            }
            state[control.name].push(String(control.value));
        });
        Object.keys(state).forEach(function (name) { state[name].sort(); });
        Array.prototype.forEach.call(form.querySelectorAll('textarea[name^="wpacu_handle_unload_regex["], textarea[name^="wpacu_handle_load_regex["]'), function (textarea) {
            var enableName = textarea.name.replace(/\[value\]$/, '[enable]');
            var checkbox = Array.prototype.filter.call(form.elements, function (control) { return control.name === enableName; })[0] || null;
            var value = String(textarea.value || '').trim();
            state['wpacu-logical-regex:' + textarea.name] = [checkbox && checkbox.checked && value !== '' ? '1' : '0', value];
        });
        Array.prototype.forEach.call(form.querySelectorAll('.wpacu_unload_it_post_type_via_tax_checkbox[name]'), function (checkbox) {
            var wrap = checkbox.closest('.wpacu_manage_via_tax_area_wrap');
            var select = wrap ? wrap.querySelector('select.wpacu_unload_post_type_via_tax_dd') : null;
            var values = [];
            if (select) { Array.prototype.forEach.call(select.options, function (option) { if (option.selected && String(option.value) !== '') { values.push(String(option.value)); } }); }
            values.sort();
            state['wpacu-logical-post-type-tax:' + checkbox.name] = [checkbox.checked && values.length > 0 ? '1' : '0'].concat(values);
        });
        Array.prototype.forEach.call(form.querySelectorAll('select.wpacu-screen-size-load[name]'), function (select) {
            var valueName = select.name.replace(/\[enable\]$/, '[value]');
            var textarea = Array.prototype.filter.call(form.elements, function (control) { return control.name === valueName; })[0] || null;
            var mode = String(select.value || '');
            var value = textarea ? String(textarea.value || '').trim() : '';
            if (mode === '1' && value === '') { mode = ''; }
            state['wpacu-logical-media-query:' + select.name] = [mode, value];
        });
        return state;
    }
    function refresh() {
        if (! initialState) { return; }
        var currentState = getState();
        var names = Object.keys(initialState).concat(Object.keys(currentState)).filter(function (name, index, all) { return all.indexOf(name) === index; });
        var count = names.reduce(function (total, name) { return total + (JSON.stringify(initialState[name] || []) === JSON.stringify(currentState[name] || []) ? 0 : 1); }, 0);
        output.textContent = count === 0 ? '(0) <?php echo esc_js(__('No unsaved changes', 'wp-asset-clean-up')); ?>' : '(' + count + ') ' + (count === 1 ? '<?php echo esc_js(__('unsaved change', 'wp-asset-clean-up')); ?>' : '<?php echo esc_js(__('unsaved changes', 'wp-asset-clean-up')); ?>');
        summary.classList.toggle('has-unsaved-changes', count > 0);
    }
    function scheduleRefresh() { window.clearTimeout(timer); timer = window.setTimeout(refresh, 40); }
    function syncDockVisibility() {
        dock.classList.toggle('wpacu-assets-loading', submit.classList.contains('hidden'));
    }
    function captureInitialState() {
        syncDockVisibility();
        if (submit.classList.contains('hidden')) { return; }
        window.setTimeout(function () { initialState = getState(); refresh(); }, 100);
    }
    form.addEventListener('input', scheduleRefresh);
    form.addEventListener('change', function () { window.setTimeout(scheduleRefresh, 0); });
    form.addEventListener('submit', function () {
        submit.value = submit.getAttribute('data-saving-label');
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        if (spinner) { spinner.setAttribute('aria-hidden', 'false'); }
    });
    if (window.jQuery) {
        window.jQuery(form).on('change.wpacuAssetsSaveDock input.wpacuAssetsSaveDock', ':input', function () {
            window.setTimeout(scheduleRefresh, 0);
        });
    }
    new MutationObserver(captureInitialState).observe(submit, { attributes: true, attributeFilter: ['class'] });
    syncDockVisibility();
    captureInitialState();
}());
</script>
