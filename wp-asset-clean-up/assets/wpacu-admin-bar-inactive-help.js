(function () {
    'use strict';
    ['inactive', 'savings'].forEach(function (kind) {
    var slot = document.getElementById('wpacu-' + kind + '-help-slot');
    var root = document.getElementById('wp-admin-bar-assetcleanup-parent');
    var row = document.getElementById(kind === 'inactive' ? 'wp-admin-bar-assetcleanup-inactive-unload-rules' : 'wp-admin-bar-assetcleanup-savings');
    if (!slot || !root || !row || slot.dataset.initialized) { return; }
    slot.dataset.initialized = 'true';
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'wpacu-inactive-help-button';
    button.textContent = '?';
    button.setAttribute('aria-label', slot.dataset.label);
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-controls', 'wpacu-' + kind + '-help-panel');
    var panel = document.createElement('div');
    panel.id = 'wpacu-' + kind + '-help-panel';
    panel.className = 'wpacu-adminbar-help-panel';
    panel.hidden = true;
    panel.setAttribute('role', 'note');
    panel.textContent = slot.dataset.explanation;
    row.appendChild(button);
    row.appendChild(panel);
    var suppressedTitles = [];
    function closeOtherSubmenus() {
        root.querySelectorAll('.menupop.hover').forEach(function (item) {
            if (item !== row) {
                item.classList.remove('hover');
                var link = item.querySelector('.ab-item');
                if (link) { link.setAttribute('aria-expanded', 'false'); }
            }
        });
    }
    function position() {
        var anchor = slot.getBoundingClientRect(), bounds = row.getBoundingClientRect();
        button.style.left = (anchor.left - bounds.left) + 'px';
        var menu = root.querySelector('.ab-sub-wrapper').getBoundingClientRect();
        panel.style.left = Math.max(8, Math.min(bounds.left, window.innerWidth - panel.offsetWidth - 8)) + 'px';
        panel.style.top = Math.max(8, Math.min(menu.bottom + 8, window.innerHeight - panel.offsetHeight - 8)) + 'px';
    }
    function close(restoreFocus, switchingRow) {
        if (!switchingRow) { closeOtherSubmenus(); }
        panel.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        root.classList.remove('wpacu-inactive-help-open');
        row.classList.remove('wpacu-help-row-open');
        suppressedTitles.forEach(function (entry) { entry.element.setAttribute('title', entry.title); });
        suppressedTitles = [];
        if (restoreFocus) {
            root.classList.add('hover');
            row.classList.add('hover');
            button.focus();
        }
    }
    button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (!panel.hidden) { close(false); return; }
        root.dispatchEvent(new CustomEvent('wpacu-close-help'));
        closeOtherSubmenus();
        root.querySelectorAll('[title]').forEach(function (element) {
            suppressedTitles.push({element: element, title: element.getAttribute('title')});
            element.removeAttribute('title');
        });
        panel.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        root.classList.add('wpacu-inactive-help-open');
        row.classList.add('wpacu-help-row-open');
        position();
    });
    document.addEventListener('click', function (event) {
        if (!panel.hidden && !row.contains(event.target)) { close(false); }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); close(true); }
    });
    function switchRow(event) {
        if (!panel.hidden && event.target.closest('.ab-item') && event.target.closest('li') !== row) {
            close(false, true);
        }
    }
    root.addEventListener('wpacu-close-help', function () { if (!panel.hidden) { close(false, true); } });
    root.addEventListener('mouseover', switchRow);
    root.addEventListener('focusin', switchRow);
    root.addEventListener('mouseenter', position);
    root.addEventListener('focusin', position);
    window.addEventListener('resize', position);
    window.addEventListener('scroll', function () { if (!panel.hidden) { position(); } }, true);
    if (window.MutationObserver) {
        new MutationObserver(position).observe(slot.parentElement, {childList: true, subtree: true, characterData: true});
    }
    position();
    });
}());
