<?php
if (! defined('WPACU_PLUGIN_ID')) {
    exit;
}
?>
<style data-wpacu-own-inline-style="true">
#wpadminbar #wp-admin-bar-assetcleanup-parent > .ab-item .wpacu-paused-icon {
    display: inline-block;
    width: 17px;
    height: 17px;
    margin-right: 2px;
    vertical-align: -3px;
    color: #f0b849;
}
#wpadminbar #wp-admin-bar-assetcleanup-optimizations-paused {
    position: relative;
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px solid #ffffff30;
}
#wpadminbar #wp-admin-bar-assetcleanup-optimizations-paused > .ab-item {
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    color: #f0b849;
}
#wpadminbar #wp-admin-bar-assetcleanup-optimizations-paused .wpacu-paused-icon {
    flex: 0 0 17px;
    width: 17px;
    height: 17px;
    margin-right: 2px;
    color: inherit;
}
#wpadminbar .wpacu-paused-chevron {
    margin-left: auto;
    padding-left: 8px;
    color: inherit;
}
#wpadminbar #wpacu-paused-optimizations-panel {
    position: fixed;
    box-sizing: border-box;
    width: 340px;
    max-width: calc(100vw - 24px);
    max-height: calc(100vh - 24px);
    overflow: auto;
    padding: 18px;
    border-left: 4px solid #dba617;
    border-radius: 4px;
    background: #2c3338;
    box-shadow: 0 4px 16px #0005;
    z-index: 10000005;
}
#wpadminbar #wpacu-paused-optimizations-panel[hidden] { display: none; }
#wpadminbar #wpacu-paused-optimizations-panel,
#wpadminbar #wpacu-paused-optimizations-panel * {
    white-space: normal;
    line-height: 1.5;
    height: auto;
    font-size: 13px;
}
#wpadminbar #wpacu-paused-optimizations-panel strong {
    display: block;
    color: #fff;
    font-weight: 600;
    font-size: 14px;
}
#wpadminbar #wpacu-paused-optimizations-panel p {
    margin: 10px 0 14px;
    padding: 0;
    color: #c3c4c7;
}
#wpadminbar #wpacu-paused-optimizations-panel .wpacu-disabled-features {
    margin: 8px 0 14px;
    padding: 0 0 0 20px;
    list-style: disc outside;
}
#wpadminbar #wpacu-paused-optimizations-panel .wpacu-disabled-features li {
    display: list-item;
    list-style: disc outside;
    margin: 4px 0;
    padding: 0;
    color: #c3c4c7;
}
#wpadminbar #wpacu-paused-optimizations-panel a {
    display: inline-block;
    padding: 0;
    color: #72aee6;
    text-decoration: none;
}
#wpadminbar #wpacu-paused-optimizations-panel a:hover,
#wpadminbar #wpacu-paused-optimizations-panel a:focus {
    color: #9ec8ef;
    text-decoration: underline;
}
#wpadminbar #wp-admin-bar-assetcleanup-parent:focus-within > .ab-sub-wrapper {
    display: block;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var item = document.getElementById('wp-admin-bar-assetcleanup-optimizations-paused');
    var panel = document.getElementById('wpacu-paused-optimizations-panel');
    if (!item || !panel) { return; }
    var trigger = item.querySelector('.ab-item');
    var closeTimer;
    trigger.setAttribute('aria-controls', panel.id);
    trigger.setAttribute('aria-expanded', 'false');

    function positionPanel() {
        var rect = trigger.getBoundingClientRect();
        var width = panel.offsetWidth;
        var height = panel.offsetHeight;
        var left = rect.right + 6;
        if (left + width > window.innerWidth - 12) {
            left = rect.left - width - 6;
        }
        var top = rect.top;
        // Narrow screens: place the details below the row rather than over it.
        if (left < 12) {
            left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
            top = rect.bottom + 6;
        }
        panel.style.left = left + 'px';
        panel.style.top = Math.max(12, Math.min(top, window.innerHeight - height - 12)) + 'px';
    }
    function openPanel() {
        clearTimeout(closeTimer);
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        positionPanel();
    }
    function closePanel() {
        clearTimeout(closeTimer);
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }
    item.addEventListener('mouseenter', openPanel);
    item.addEventListener('mouseleave', function() {
        closeTimer = setTimeout(closePanel, 180);
    });
    panel.addEventListener('mouseenter', function() { clearTimeout(closeTimer); });
    item.addEventListener('focusin', openPanel);
    item.addEventListener('focusout', function(event) {
        if (!item.contains(event.relatedTarget)) { closePanel(); }
    });
    item.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            trigger.focus();
            closePanel();
        }
    });
    document.addEventListener('click', function(event) {
        if (!item.contains(event.target)) { closePanel(); }
    });
    window.addEventListener('resize', function() { if (!panel.hidden) { positionPanel(); } });
    window.addEventListener('scroll', function() { if (!panel.hidden) { positionPanel(); } }, true);
});
</script>
