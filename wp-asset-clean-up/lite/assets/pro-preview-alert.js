/* Explain locked Pro controls without enabling them or requiring jQuery. */
(function () {
    'use strict';
    var selector = '.wpacu-lite-pro-preview-control:disabled:not([type="hidden"]), '
        + '[data-wpacu-lite-context-preview] .wpacu-pm-page-rule input:disabled, '
        + '[data-wpacu-lite-context-preview] .wpacu-pm-page-rule select:disabled, '
        + '[data-wpacu-lite-context-preview] .wpacu-pm-page-rule textarea:disabled, '
        + '[data-wpacu-lite-context-preview] .wpacu-lite-pm-locked-save:disabled';

    function settings(target) {
        var root = target.closest('[data-wpacu-pro-alert]');
        if (root) {
            try { return JSON.parse(root.getAttribute('data-wpacu-pro-alert')); } catch (error) { return null; }
        }
        return window.wpacuLiteProPreviewAlertSettings || null;
    }

    function lockParentScroll() {
        var saved = [];
        var restored = false;
        function restore() {
            if (restored) { return; }
            restored = true;
            saved.forEach(function (entry) {
                if (entry.value) { entry.style.setProperty(entry.name, entry.value, entry.priority); }
                else { entry.style.removeProperty(entry.name); }
            });
            window.removeEventListener('pagehide', restore);
        }
        function set(element, name, value) {
            saved.push({style: element.style, name: name, value: element.style.getPropertyValue(name), priority: element.style.getPropertyPriority(name)});
            element.style.setProperty(name, value, 'important');
        }
        try {
            if (!window.frameElement || window.frameElement.id !== 'wpacu-frontend-manager-frame') { return restore; }
            var parent = window.parent;
            var root = parent.document.documentElement;
            var body = parent.document.body;
            var gap = Math.max(0, parent.innerWidth - root.clientWidth);
            if (gap) { set(root, 'padding-right', ((parseFloat(parent.getComputedStyle(root).paddingRight) || 0) + gap) + 'px'); }
            [root, body].forEach(function (element) {
                set(element, 'overflow-x', 'hidden');
                set(element, 'overflow-y', 'hidden');
            });
            window.addEventListener('pagehide', restore);
        } catch (error) {
            // A separately navigated/cross-origin frame must not leave partial locks.
            restore();
        }
        return restore;
    }

    function explain(target) {
        var copy = settings(target);
        if (!copy) { return; }
        if (!window.wpacuSwal || typeof window.wpacuSwal.fire !== 'function') {
            window.alert(copy.title + '\n\n' + copy.message);
            return;
        }
        if (window.wpacuSwal.isVisible()) { return; }
        var upgradeLink = document.createElement('a');
        // Only a deliberate click on this link navigates; dismissing keeps the preview open.
        if (/^https?:\/\//i.test(copy.url || '')) {
            upgradeLink.href = copy.url;
            upgradeLink.target = '_blank';
            upgradeLink.rel = 'noopener noreferrer';
            upgradeLink.textContent = copy.link;
            upgradeLink.className = 'wpacu-lite-pro-alert-upgrade';
        }
        var restoreParentScroll = function () {};
        window.wpacuSwal.fire({
            titleText: copy.title,
            text: copy.message,
            icon: 'info',
            confirmButtonText: copy.confirm,
            confirmButtonColor: '#52616b',
            customClass: {popup: 'wpacu-lite-pro-alert'},
            showCloseButton: true,
            heightAuto: false,
            width: '560px',
            willOpen: function (popup) {
                restoreParentScroll = lockParentScroll();
                if (upgradeLink.hasAttribute('href')) {
                    popup.querySelector('.wpacu-swal2-actions').appendChild(upgradeLink);
                }
            },
            didClose: function () { restoreParentScroll(); },
            didDestroy: function () { restoreParentScroll(); }
        });
    }

    function prepare() {
        Array.prototype.forEach.call(document.querySelectorAll(selector), function (control) {
            // Native disabled inputs do not reliably emit click events. Their label/card
            // receives the interaction while the actual input stays disabled and unnamed.
            var target = control.closest('.wpacu-pm-page-rule') || control.closest('label, .wpacu-save-actions') || control.parentElement;
            if (!target || !settings(target)) { return; }
            control.classList.add('wpacu-lite-pro-alert-control');
            if (target.hasAttribute('data-wpacu-pro-alert-trigger')) { return; }
            target.setAttribute('data-wpacu-pro-alert-trigger', '');
            target.setAttribute('tabindex', '0');
            target.setAttribute('role', 'button');
            target.setAttribute('aria-haspopup', 'dialog');
            var label = control.getAttribute('aria-label') || (target.textContent || '').replace(/\s+/g, ' ').trim();
            target.setAttribute('aria-label', (label ? label.slice(0, 160) + '. ' : '') + settings(target).title);
            // An inline label's hit area can miss the middle of a tall textarea.
            if (window.getComputedStyle(target).display === 'inline') {
                target.classList.add('wpacu-lite-pro-alert-inline');
            }
        });
    }

    function ready() {
        prepare();
        document.addEventListener('click', function (event) {
            var target = event.target.closest('[data-wpacu-pro-alert-trigger]');
            if (!target || !target.querySelector(selector)) { return; }
            if (event.target.closest('a, button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled)')) { return; }
            event.preventDefault();
            event.stopPropagation();
            explain(target);
        }, true);
        document.addEventListener('keydown', function (event) {
            if ((event.key !== 'Enter' && event.key !== ' ') || !event.target.matches('[data-wpacu-pro-alert-trigger]')) { return; }
            if (!event.target.querySelector(selector)) { return; }
            event.preventDefault();
            explain(event.target);
        });
        if (window.MutationObserver) {
            new MutationObserver(prepare).observe(document.body, {childList: true, subtree: true, attributes: true, attributeFilter: ['disabled']});
        }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', ready); }
    else { ready(); }
}());
