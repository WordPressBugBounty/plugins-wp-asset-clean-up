/* Read-only contextual preview. All interactions stay in this document; no saved preferences or rules. */
(function () {
    'use strict';
    function ready() {
        var root = document.querySelector('[data-wpacu-lite-context-preview]');
        if (!root) { return; }
        var list = root.querySelector('[data-wpacu-preview-plugins]');
        var rows = Array.prototype.slice.call(root.querySelectorAll('.wpacu-pm-page-plugin'));
        var groups = Array.prototype.slice.call(root.querySelectorAll('[data-wpacu-preview-rule-group]'));
        var filter = root.querySelector('[data-wpacu-preview-filter]');
        var grouping = root.querySelector('[data-wpacu-preview-group]');
        var expand = root.querySelector('[data-wpacu-preview-expand]');
        var contract = root.querySelector('[data-wpacu-preview-contract]');
        function updateButtons() {
            var visible = rows.filter(function (row) { return !row.hidden; });
            expand.disabled = !visible.some(function (row) { return row.classList.contains('is-contracted'); });
            contract.disabled = !visible.some(function (row) { return !row.classList.contains('is-contracted'); });
        }
        function setExpanded(row, expanded) {
            var button = row.querySelector('[data-wpacu-preview-toggle]');
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            button.querySelector('.dashicons').className = 'dashicons ' + (expanded ? 'dashicons-minus' : 'dashicons-plus-alt2');
            row.classList.toggle('is-contracted', !expanded);
            row.querySelector('.wpacu-pm-page-plugin-body').hidden = !expanded;
        }
        function refresh() {
            var term = filter.value.trim().toLowerCase();
            var grouped = grouping.value === 'by-unload-rules';
            rows.forEach(function (row) {
                row.hidden = row.dataset.search.indexOf(term) === -1;
                var group = groups[row.dataset.hasUnload === '1' ? 0 : 1];
                var target = grouped ? group.querySelector('[data-wpacu-group-plugins]') : list;
                if (row.parentNode !== target) { target.appendChild(row); }
            });
            groups.forEach(function (group) {
                var visible = Array.prototype.filter.call(group.querySelectorAll('.wpacu-pm-page-plugin'), function (row) { return !row.hidden; });
                group.hidden = !grouped || !visible.length;
                group.querySelector('[data-wpacu-preview-group-count]').textContent = '(' + visible.length + ')';
                group.nextElementSibling.hidden = visible.length > 0 || !grouped || !!term || !rows.length;
            });
            [list].concat(groups.map(function (group) { return group.querySelector('[data-wpacu-group-plugins]'); })).forEach(function (container) {
                Array.prototype.filter.call(container.children, function (row) { return row.classList.contains('wpacu-pm-page-plugin') && !row.hidden; }).forEach(function (row, index) {
                    row.classList.toggle('is-first-row', index === 0);
                    row.classList.toggle('is-even', index % 2 === 1);
                });
            });
            root.querySelector('[data-wpacu-preview-empty]').hidden = rows.some(function (row) { return !row.hidden; });
            updateButtons();
        }
        rows.forEach(function (row) {
            row.querySelector('[data-wpacu-preview-toggle]').addEventListener('click', function () {
                setExpanded(row, row.classList.contains('is-contracted'));
                updateButtons();
            });
        });
        [expand, contract].forEach(function (button) {
            button.addEventListener('click', function () {
                rows.filter(function (row) { return !row.hidden; }).forEach(function (row) { setExpanded(row, button === expand); });
                if (button === expand) { groups.forEach(function (group) { if (!group.hidden) { group.open = true; } }); }
                updateButtons();
            });
        });
        filter.addEventListener('input', refresh);
        filter.addEventListener('search', refresh);
        grouping.addEventListener('change', refresh);
        refresh();
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', ready); }
    else { ready(); }
}());
