/* Switch the standalone CSS/JS post type or taxonomy search without reloading the Dashboard. */
jQuery(function ($) {
    'use strict';
    var root = document.querySelector('.wpacu-assets-context-picker');
    var formSelector = '#wpacu-custom-post-type-form, #wpacu-custom-taxonomy-form';
    var selectSelector = 'select[name="wpacu_post_type"], select[name="wpacu_taxonomy"]';
    if (!root || !root.querySelector(formSelector)) { return; }
    var contextTab = new URL(location.href).searchParams.get('wpacu_for');
    var config = window.wpacuAssetsContextNavigation;
    var request, sequence = 0, confirmation = 0, restoring = false;
    var index = Number((history.state || {}).wpacuAssetsContextIndex || 0);
    var restoreControls = function () {};
    var editorUrl = new URL(location.href);
    var editorForm = document.getElementById('wpacu_dash_assets_manager_form');
    var details = null;

    // Keep the live editor available for Back, including its handlers and pending asset fetch.
    // Only the search picker is replaced; another selected entry still opens its own page.
    if (editorForm && root.parentNode.contains(editorForm)) {
        details = document.createElement('fieldset');
        details.className = 'wpacu-assets-context-details';
        while (root.nextSibling) { details.appendChild(root.nextSibling); }
        root.parentNode.appendChild(details);
    }

    function originalEditor(url) {
        return !!details && ['wpacu_post_id', 'wpacu_term_id', 'wpacu_post_type', 'wpacu_taxonomy'].every(function (key) {
            return url.searchParams.get(key) === editorUrl.searchParams.get(key);
        });
    }

    function supported(url) {
        return url.origin === location.origin && url.pathname === location.pathname
            && url.searchParams.get('page') === 'wpassetcleanup_assets_manager'
            && ['custom_post_types', 'custom_taxonomies'].indexOf(contextTab) !== -1
            && url.searchParams.get('wpacu_for') === contextTab
            && (!url.searchParams.get('wpacu_sub_page') || url.searchParams.get('wpacu_sub_page') === 'manage_css_js')
            && url.searchParams.get('wpacu_post_type_view') !== 'archives'
            && ((!url.searchParams.get('wpacu_post_id') && !url.searchParams.get('wpacu_term_id')) || originalEditor(url));
    }

    function state(value) { return Object.assign({}, history.state || {}, {wpacuAssetsContextIndex: value}); }
    function restoreHistory() {
        var browserIndex = (history.state || {}).wpacuAssetsContextIndex;
        if (typeof browserIndex === 'number' && browserIndex !== index) {
            restoring = true;
            history.go(index - browserIndex);
        }
    }
    function allowNavigation() {
        if (!details || details.hidden || typeof editorForm.wpacuHasUnsavedChanges !== 'function'
            || !editorForm.wpacuHasUnsavedChanges()) {
            return true;
        }
        if (!window.wpacuSwal || typeof window.wpacuSwal.fire !== 'function') {
            return window.confirm(config.contextUnsaved);
        }
        return window.wpacuSwal.fire({
            icon: 'warning',
            width: '760px',
            titleText: config.contextUnsavedTitle,
            text: config.contextUnsaved,
            showCancelButton: true,
            confirmButtonText: config.contextContinue,
            cancelButtonText: config.contextStay,
            focusCancel: true,
            reverseButtons: true,
            allowOutsideClick: false
        }).then(function (result) { return result.isConfirmed === true; });
    }
    function cancelPendingNavigation(restoreUrl) {
        ++sequence;
        if (request) { request.abort(); request = null; }
        restoreControls();
        restoreSelection();
        var notice = root.querySelector('[data-wpacu-assets-context-status]');
        if (notice) { notice.remove(); }
        if (restoreUrl !== false) { restoreHistory(); }
    }
    function navigate(url, targetIndex) {
        var attempt = ++confirmation;
        // A pending response must not replace the editor while its confirmation is open.
        cancelPendingNavigation(false);
        var decision = allowNavigation();
        function finish(allowed) {
            if (attempt !== confirmation) { return; }
            if (allowed) { load(url, targetIndex); }
            else { cancelPendingNavigation(); }
        }
        if (decision && typeof decision.then === 'function') {
            decision.then(finish, function () { finish(false); });
        } else { finish(decision); }
    }
    function restoreSelection() {
        var select = root.querySelector(selectSelector);
        Array.prototype.forEach.call(select.options, function (option) { option.selected = option.defaultSelected; });
        if ($(select).data('ui-selectmenu')) { $(select).selectmenu('refresh'); }
    }
    function dispose(oldRoot) {
        $(oldRoot).find('.search-field').each(function () {
            var pending = $(this).data('wpacuSearchRequest');
            if (pending) { pending.abort(); }
            if ($(this).data('ui-autocomplete')) { $(this).autocomplete('destroy'); }
        });
        $(oldRoot).find('select').each(function () {
            if ($(this).data('ui-selectmenu')) { $(this).selectmenu('destroy'); }
        });
    }
    function load(url, targetIndex) {
        var token = ++sequence, oldRoot = root;
        if (request) { request.abort(); }
        restoreControls();
        var controller = new AbortController();
        request = controller;
        var timeout = setTimeout(function () { controller.abort(); }, 30000);
        var input = root.querySelector('.search-field');
        var loader = root.querySelector('.wpacu-assets-context-loader');
        var disabled = input && input.disabled;
        var detailsDisabled = details && details.disabled;
        if (details) {
            details.disabled = true;
            details.setAttribute('inert', '');
        }
        if (input) {
            var pending = $(input).data('wpacuSearchRequest');
            if (pending) { pending.abort(); }
            if ($(input).data('ui-autocomplete')) { $(input).autocomplete('close'); }
            input.disabled = true;
        }
        if (loader) { loader.hidden = false; }
        root.setAttribute('aria-busy', 'true');
        var notice = root.querySelector('[data-wpacu-assets-context-status]');
        if (!notice) {
            notice = document.createElement('p');
            notice.setAttribute('data-wpacu-assets-context-status', '');
            notice.setAttribute('role', 'status');
            root.appendChild(notice);
        }
        notice.className = loader ? 'screen-reader-text' : '';
        notice.textContent = config.loading;
        restoreControls = function () {
            oldRoot.removeAttribute('aria-busy');
            if (loader) { loader.hidden = true; }
            if (input) { input.disabled = disabled; }
            if (details) {
                details.disabled = detailsDisabled;
                details.removeAttribute('inert');
            }
        };
        fetch(url.href, {credentials: 'same-origin', signal: controller.signal, cache: 'no-store'})
            .then(function (response) {
                if (!response.ok || !supported(new URL(response.url))) { throw new Error('Context unavailable'); }
                return response.text();
            })
            .then(function (html) {
                if (token !== sequence) { return; }
                var parsed = new DOMParser().parseFromString(html, 'text/html');
                var replacement = parsed.querySelector('.wpacu-assets-context-picker');
                if (!replacement || !replacement.querySelector(formSelector)) { throw new Error('Context unavailable'); }
                Array.prototype.forEach.call(replacement.querySelectorAll('script'), function (script) { script.remove(); });
                replacement = document.importNode(replacement, true);
                dispose(oldRoot);
                restoreControls();
                $(oldRoot).replaceWith(replacement);
                root = replacement;
                if (details) { details.hidden = !originalEditor(url); }
                if (typeof targetIndex === 'number') { index = targetIndex; }
                else {
                    index = Number((history.state || {}).wpacuAssetsContextIndex || 0) + 1;
                    history.pushState(state(index), '', url.href);
                }
                bind();
                document.dispatchEvent(new CustomEvent('wpacu:page-context-loaded', {detail: root}));
                var select = root.querySelector(selectSelector);
                var widget = $(select).data('ui-selectmenu');
                if (widget) { widget.button[0].focus(); }
                else { select.focus(); }
            })
            .catch(function () {
                if (token !== sequence) { return; }
                restoreControls();
                restoreSelection();
                notice.className = 'wpacu-assets-context-error';
                notice.textContent = config.error;
                restoreHistory();
            })
            .then(function () { clearTimeout(timeout); if (token === sequence) { request = null; } });
    }
    function bind() {
        var form = root.querySelector(formSelector);
        var select = form.querySelector(selectSelector);
        form.addEventListener('submit', function (event) {
            if (!window.fetch || !window.AbortController || !history.pushState) { return; }
            var url = new URL(location.href);
            new FormData(form).forEach(function (value, key) { url.searchParams.set(key, value); });
            url.searchParams.delete('wpacu_post_id');
            url.searchParams.delete('wpacu_term_id');
            if (!supported(url)) { return; }
            event.preventDefault();
            if (restoring) { restoreSelection(); return; }
            navigate(url);
        });
        select.addEventListener('change', function (event) {
            var submitEvent = new Event('submit', {bubbles: true, cancelable: true});
            if (form.dispatchEvent(submitEvent)) {
                form.submit();
            } else {
                // Let the page-level loader know this change does not navigate away.
                event.wpacuContextNavigationHandled = true;
            }
        });
    }
    history.replaceState(state(index), '', location.href);
    window.addEventListener('popstate', function (event) {
        if (restoring) { restoring = false; return; }
        var targetIndex = event.state && event.state.wpacuAssetsContextIndex;
        var url = new URL(location.href);
        if (typeof targetIndex === 'number' && supported(url)) { navigate(url, targetIndex); }
    });
    bind();
});
