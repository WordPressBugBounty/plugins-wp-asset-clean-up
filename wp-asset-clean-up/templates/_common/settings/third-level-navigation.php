<?php if (! isset($data)) { return; } ?>
<script>
(function () {
    'use strict';
    var initialHash = window.location.hash;
    function init() {
        var field = document.getElementById('wpacu-selected-sub-sub-tab-area');
        var topField = document.getElementById('wpacu-selected-tab-area');
        var secondField = document.getElementById('wpacu-selected-sub-tab-area');
        if (!field || !topField || !secondField) { return; }
        var groups = Array.prototype.map.call(document.querySelectorAll('[data-wpacu-third-tabs-parent]'), function (nav) {
            var parent = document.getElementById(nav.getAttribute('data-wpacu-third-tabs-parent') + '-tab-item');
            var tabs = Array.prototype.slice.call(nav.querySelectorAll('[data-wpacu-third-tab]'));
            var selected = tabs.filter(function (tab) { return tab.getAttribute('aria-selected') === 'true'; })[0];
            return {nav: nav, parent: parent, tabs: tabs, selected: selected ? selected.getAttribute('data-wpacu-third-tab') : ''};
        }).filter(function (group) { return group.parent && group.tabs.length; });
        function activeGroup() {
            if (topField.value !== 'wpacu-setting-plugin-usage-settings') { return null; }
            return groups.filter(function (group) { return group.parent.checked; })[0] || null;
        }
        function syncLocation() {
            var group = activeGroup();
            var url = new URL(window.location.href);
            field.value = group ? group.selected : '';
            if (group) {
                secondField.value = group.parent.value;
                url.searchParams.set('wpacu_selected_tab_area', topField.value);
                url.searchParams.set('wpacu_selected_sub_tab_area', group.parent.value);
                url.searchParams.set('wpacu_selected_sub_sub_tab_area', group.selected);
            } else {
                url.searchParams.delete('wpacu_selected_sub_sub_tab_area');
            }
            var next = url.pathname + url.search + url.hash;
            if (next !== window.location.pathname + window.location.search + window.location.hash) {
                window.history.replaceState(null, '', next);
            }
        }
        function select(group, key) {
            if (!group.tabs.some(function (tab) { return tab.getAttribute('data-wpacu-third-tab') === key; })) { return; }
            group.selected = key;
            group.tabs.forEach(function (tab) {
                var active = tab.getAttribute('data-wpacu-third-tab') === key;
                var panel = document.getElementById(tab.getAttribute('aria-controls'));
                if (panel) { panel.hidden = !active; }
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.tabIndex = active ? 0 : -1;
            });
            syncLocation();
            window.dispatchEvent(new Event('resize'));
            if (jQuery.fn.wpAssetCleanUp) { jQuery.fn.wpAssetCleanUp().wpacuTriggerAdjustTextAreaHeightAllTextareas(); }
        }
        function openHash(hash, scroll) {
            var target;
            try { target = hash ? document.getElementById(decodeURIComponent(hash.slice(1))) : null; } catch (ignore) { return; }
            var group = activeGroup();
            if (!target || !group) { return; }
            group.tabs.some(function (tab) {
                var panel = document.getElementById(tab.getAttribute('aria-controls'));
                if (!panel || !panel.contains(target)) { return false; }
                select(group, tab.getAttribute('data-wpacu-third-tab'));
                if (scroll) { window.requestAnimationFrame(function () { target.scrollIntoView({block: 'start'}); }); }
                return true;
            });
        }
        groups.forEach(function (group) {
            group.tabs.forEach(function (tab, index) {
                tab.addEventListener('click', function (event) {
                    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) { return; }
                    event.preventDefault();
                    if (window.location.hash) { window.history.replaceState(null, '', window.location.pathname + window.location.search); }
                    select(group, tab.getAttribute('data-wpacu-third-tab'));
                });
                tab.addEventListener('keydown', function (event) {
                    var next = event.key === 'ArrowRight' ? (index + 1) % group.tabs.length
                        : event.key === 'ArrowLeft' ? (index + group.tabs.length - 1) % group.tabs.length
                        : event.key === 'Home' ? 0 : event.key === 'End' ? group.tabs.length - 1 : -1;
                    if (next < 0) { return; }
                    event.preventDefault(); group.tabs[next].click(); group.tabs[next].focus();
                });
                // Apply the server-selected state without modifying the other group's selection.
            });
            select(group, group.selected);
        });
        // Run after the existing level 1/2 handlers, including synthetic clicks.
        jQuery(document).on('click.wpacuThirdLevelSettings', 'a[data-wpacu-settings-tab-key], a[data-wpacu-vertical-link-target], input.wpacu-nav-input-sub-tab-area', function () {
            window.setTimeout(syncLocation, 0);
        });
        // Reveal hidden destinations before an existing quick-nav scroll handler runs.
        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[href^="#"]');
            if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.button === 0) {
                openHash(link.getAttribute('href'), false);
            }
        }, true);
        if (field.form) { field.form.addEventListener('submit', syncLocation, true); }
        window.addEventListener('hashchange', function () { openHash(window.location.hash, true); });
        syncLocation(); openHash(initialHash, true);
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
}());
</script>
