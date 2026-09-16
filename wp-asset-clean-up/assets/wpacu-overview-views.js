(function () {
    'use strict';
    function initializeScopeFilter() {
        var select = document.getElementById('wpacu-overview-rule-scope');
        var stateSelect = document.getElementById('wpacu-overview-rule-state');
        var root = document.getElementById('wpacu-overview-display');
        if (!select || !root) { return; }
        var hidden = new Map();
        var storageKey = 'wpacu_overview_rule_scope:' + window.location.pathname;
        function hide(node) {
            if (!node || hidden.has(node)) { return; }
            hidden.set(node, [node.style.getPropertyValue('display'), node.style.getPropertyPriority('display')]);
            node.style.setProperty('display', 'none', 'important');
        }
        function restore() {
            hidden.forEach(function (style, node) {
                if (style[0]) { node.style.setProperty('display', style[0], style[1]); }
                else { node.style.removeProperty('display'); }
            });
            hidden.clear();
        }
        function matches(node) {
            var scope = node.getAttribute('data-wpacu-rule-scope');
            if (select.value !== 'all' && scope !== select.value) { return false; }
            var state = stateSelect ? stateSelect.value : 'all';
            if (state === 'all') { return true; }
            // Shared cleanup controls are not separate saved rules.
            if (scope === 'shared') { return false; }
            var row = node.closest('tr');
            var inactive = !!node.closest('[data-wpacu-rule-state="inactive"]')
                || !!(row && row.querySelector('[data-wpacu-asset-inactive]'));
            return state === (inactive ? 'inactive' : 'active');
        }
        function apply() {
            restore();
            var records = Array.prototype.slice.call(root.querySelectorAll('[data-wpacu-rule-scope]'));
            var empty = document.getElementById('wpacu-overview-rule-scope-empty');
            var filtering = select.value !== 'all' || (stateSelect && stateSelect.value !== 'all');
            if (empty) { empty.hidden = !filtering || records.some(matches); }
            if (filtering) {
                records.forEach(function (node) { if (!matches(node)) { hide(node); } });
                // Remove empty context and rows, while retaining asset names and source links.
                var candidates = new Set();
                records.forEach(function (node) {
                    var parent = node.parentElement;
                    while (parent && parent !== root) {
                        if (parent.matches('ul, tr, table, [data-wpacu-page-group], .wpacu-script-attr-row, .wpacu-script-attrs-overview, .wpacu-script-attr-exceptions, .wpacu-critical-css-overview-rule, #wpacu-critical-css-overview, [data-wpacu-overview-original-id="wpacu-critical-css-overview"], #wpacu-page-options-wrap, [data-wpacu-overview-original-id="wpacu-page-options-wrap"]')
                            || (parent.tagName === 'DIV' && parent.parentElement && parent.parentElement.tagName === 'TD'
                                && !parent.parentElement.hasAttribute('data-wpacu-item-data'))
                            || parent.hasAttribute('data-wpacu-rule-state')) { candidates.add(parent); }
                        parent = parent.parentElement;
                    }
                });
                candidates.forEach(function (node) {
                    if (!Array.prototype.some.call(node.querySelectorAll('[data-wpacu-rule-scope]'), matches)) { hide(node); }
                });
                root.querySelectorAll('.wpacu-overview-section-title').forEach(function (heading) {
                    var content = heading.nextElementSibling;
                    if (content && (hidden.has(content) || (content.querySelector('[data-wpacu-rule-scope]') && !Array.prototype.some.call(content.querySelectorAll('[data-wpacu-rule-scope]'), matches)))) {
                        hide(heading);
                        if (heading.previousElementSibling && heading.previousElementSibling.tagName === 'HR') { hide(heading.previousElementSibling); }
                    }
                });
                document.querySelectorAll('#wpacu-overview-navigation a[data-wpacu-overview-nav-target]').forEach(function (link) {
                    var heading = document.getElementById(link.getAttribute('data-wpacu-overview-nav-target'));
                    if (heading && (hidden.has(heading) || !heading.getClientRects().length)) { hide(link); }
                });
            }
            document.dispatchEvent(new Event('wpacu:overview-filter-change'));
        }
        select.addEventListener('change', function () {
            try { window.sessionStorage.setItem(storageKey, select.value); } catch (error) {}
            apply();
        });
        document.addEventListener('wpacu:overview-view-change', apply);
        if (stateSelect) {
            stateSelect.addEventListener('change', function () {
                try { window.sessionStorage.setItem(storageKey + ':state', stateSelect.value); } catch (error) {}
                apply();
            });
        }
        try {
            var saved = window.sessionStorage.getItem(storageKey);
            if (saved === 'page' || saved === 'bulk' || saved === 'sitewide') { select.value = saved; }
            var savedState = window.sessionStorage.getItem(storageKey + ':state');
            if (stateSelect && (savedState === 'active' || savedState === 'inactive')) { stateSelect.value = savedState; }
        } catch (error) {}
        apply();
    }
    function initialize() {
        var selector = document.getElementById('wpacu-overview-view');
        var display = document.getElementById('wpacu-overview-display');
        var template = document.getElementById('wpacu-overview-pages-template');
        if (!selector || !display || !template) { return; }
        var views = {assets: document.createDocumentFragment(), pages: template.content};
        var current = 'assets';
        var storageKey = 'wpacu_overview_view:' + window.location.pathname;
        var fields = [];
        var originals = Object.create(null);
        function fieldKey(field) {
            return JSON.stringify([field.name, field.type, /^(checkbox|radio)$/.test(field.type) ? field.value : '']);
        }
        display.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
            var key = fieldKey(field);
            if (!originals[key]) { originals[key] = []; }
            var slot = document.createComment('Overview control');
            field.parentNode.insertBefore(slot, field);
            var item = {field: field, assets: slot, assetsId: field.id};
            originals[key].push(item);
            fields.push(item);
        });
        var used = Object.create(null);
        var valid = true;
        views.pages.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
            var key = fieldKey(field);
            var index = used[key] || 0;
            var item = originals[key] && originals[key][index];
            if (!item) {
                // Aggregate actions can appear under several target groups. Keep one real control.
                if (originals[key] && originals[key][0] && originals[key][0].pages) {
                    var shared = document.createElement('a');
                    shared.textContent = selector.getAttribute('data-shared-control') || 'Shared control';
                    shared.href = '#' + originals[key][0].pagesId;
                    field.replaceWith(shared);
                    return;
                }
                valid = false;
                return;
            }
            used[key] = index + 1;
            item.pages = document.createComment('Overview control');
            item.pagesId = field.id;
            field.replaceWith(item.pages);
        });
        if (fields.some(function (item) { return !item.pages; })) { valid = false; }
        if (!valid) {
            selector.querySelector('[value="pages"]').disabled = true;
            var notice = document.createElement('span');
            notice.textContent = selector.getAttribute('data-unavailable') || 'This view could not be prepared. The original view remains available.';
            notice.setAttribute('role', 'status');
            selector.after(notice);
            console.error('Asset CleanUp: Overview view controls do not match. The original form is preserved.');
            return;
        }
        var nav = document.querySelector('#wpacu-overview-navigation .wpacu-overview-navigation-links');
        var assetNav = document.createDocumentFragment();
        var pageNav = document.createDocumentFragment();
        views.pages.querySelectorAll('[data-wpacu-page-group]').forEach(function (section, index) {
            var heading = section.querySelector('h2');
            heading.id = 'wpacu-overview-page-group-' + index;
            heading.classList.add('wpacu-overview-section-title');
            var count = parseInt(section.getAttribute('data-wpacu-group-count'), 10) || 0;
            if (count < 1) { return; }
            var link = document.createElement('a');
            link.href = '#' + heading.id;
            link.setAttribute('data-wpacu-overview-nav-target', heading.id);
            link.textContent = heading.getAttribute('data-wpacu-group-label') || heading.textContent;
            var badge = document.createElement('span');
            badge.className = 'wpacu-overview-navigation-count';
            badge.textContent = String(count);
            link.appendChild(document.createTextNode(' '));
            link.appendChild(badge);
            pageNav.appendChild(link);
        });
        function changeView(next) {
            if (next !== 'pages') { next = 'assets'; }
            if (next === current) { return; }
            while (display.firstChild) { views[current].appendChild(display.firstChild); }
            fields.forEach(function (item) {
                item.field.id = item[next + 'Id'];
                item[next].parentNode.insertBefore(item.field, item[next].nextSibling);
            });
            display.appendChild(views[next]);
            if (nav) {
                var storeNav = current === 'assets' ? assetNav : pageNav;
                while (nav.firstChild) { storeNav.appendChild(nav.firstChild); }
                nav.appendChild(next === 'assets' ? assetNav : pageNav);
            }
            current = next;
            try { window.sessionStorage.setItem(storageKey, next); } catch (error) {}
            selector.value = next;
            var url = new URL(window.location.href);
            if (next === 'pages') { url.searchParams.set('wpacu_overview_view', 'pages'); }
            else { url.searchParams.delete('wpacu_overview_view'); }
            window.history.replaceState(null, '', url.href);
            var form = document.getElementById('wpacu-overview-edit-form');
            if (form) {
                var action = new URL(form.action, window.location.href);
                if (next === 'pages') { action.searchParams.set('wpacu_overview_view', 'pages'); }
                else { action.searchParams.delete('wpacu_overview_view'); }
                form.action = action.href;
            }
            document.dispatchEvent(new Event('wpacu:overview-view-change'));
        }
        selector.addEventListener('change', function () { changeView(selector.value); });
        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[href]');
            if (!link) { return; }
            var url = new URL(link.href, window.location.href);
            if (url.origin === window.location.origin && url.searchParams.get('page') === 'wpassetcleanup_overview' && (url.searchParams.has('wpacu_edit_mode') || link.closest('#wpacu-overview-render-edit-mode-toggle-area'))) {
                if (current === 'pages') { url.searchParams.set('wpacu_overview_view', 'pages'); }
                else { url.searchParams.delete('wpacu_overview_view'); }
                link.href = url.href;
            }
        });
        var initialView = new URL(window.location.href).searchParams.get('wpacu_overview_view');
        if (!initialView) { try { initialView = window.sessionStorage.getItem(storageKey); } catch (error) {} }
        if (initialView === 'pages') { changeView('pages'); }
    }
    function start() { initialize(); initializeScopeFilter(); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); }
    else { start(); }
}());
