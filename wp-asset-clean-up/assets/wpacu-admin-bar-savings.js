(function () {
    'use strict';
    var config = window.wpacuAdminBarSavings;
    var menu = document.getElementById('wp-admin-bar-assetcleanup-parent');
    var label = document.getElementById('wpacu-savings-label');
    if (!config || !menu || !label || !window.fetch) { return; }
    var started = false;
    // Decimal KB; keep small savings readable without rounding away their bytes.
    function showSize(element, prefix, bytes, encoding) {
        element.textContent = prefix + (bytes < 1000 ? Math.round(bytes) : (bytes / 1000).toFixed(2));
        var unit = document.createElement('span');
        unit.className = 'wpacu-savings-unit';
        unit.textContent = (bytes < 1000 ? ' B' : ' KB') + (encoding || '');
        element.appendChild(unit);
    }
    function start() {
        if (started) { return; }
        started = true;
        var totals = {styles: 0, scripts: 0, inline: 0}, counts = {styles: 0, scripts: 0, inline: 0};
        var fileSizes = Object.create(null), counted = Object.create(null);
        var fileEncodings = Object.create(null), encodings = {styles: {}, scripts: {}, total: {}};
        function suffix(group) {
            var keys = Object.keys(group);
            return keys.length === 1 && keys[0] === 'br' ? ' · Brotli' : keys.length === 1 && keys[0] === 'gzip' ? ' · GZIP' : '';
        }
        var known = 0, unknown = 0;
        label.textContent = config.labels.loading;
        function finish() {
            var hasMeasurement = counts.styles + counts.scripts + counts.inline > 0;
            if (hasMeasurement) {
                showSize(label, config.labels.total + ': ~', totals.styles + totals.scripts + totals.inline, suffix(encodings.total));
            } else {
                label.textContent = config.labels.failed;
            }
            var prefixes = {styles: 'CSS files: ', scripts: 'JavaScript files: ', inline: 'Inline CSS/JS: '};
            ['styles', 'scripts', 'inline'].forEach(function (type) {
                var value = document.getElementById('wpacu-savings-' + type);
                if (counts[type]) {
                    showSize(value, prefixes[type] + '~', totals[type], type === 'inline' ? '' : suffix(encodings[type]));
                } else {
                    value.textContent = prefixes[type] + '—';
                }
                var row = value.closest('li') || value;
                row.style.display = totals[type] > 0 ? '' : 'none';
            });
            var coverage = known === config.items.length ? known + ' ' + (known === 1 ? config.labels.asset : config.labels.assets) : known + '/' + config.items.length + ' ' + config.labels.assets;
            document.getElementById('wpacu-savings-coverage').textContent = coverage +
                (unknown ? ' · ' + unknown + ' ' + config.labels.unknown : '');
        }
        function valid(bytes) { return typeof bytes === 'number' && isFinite(bytes) && bytes >= 0; }
        function record(item, result) {
            var complete = !!(item.url || item.inline);
            if (item.url) {
                fileSizes[item.url] = result.bytes;
                fileEncodings[item.url] = result.encoding || '';
                if (valid(result.bytes)) {
                    if (!counted[item.url]) {
                        // A shared URL still loaded elsewhere does not save a transfer.
                        var observed = window.performance && performance.getEntriesByName ? performance.getEntriesByName(item.url) : [];
                        totals[item.type] += observed.length ? 0 : result.bytes;
                        counts[item.type]++;
                        if (!observed.length && result.bytes > 0) {
                            encodings[item.type][result.encoding || 'identity'] = true;
                            encodings.total[result.encoding || 'identity'] = true;
                        }
                        counted[item.url] = true;
                    }
                } else { complete = false; }
            }
            if (item.inline) {
                if (valid(result.inlineBytes)) { totals.inline += result.inlineBytes; counts.inline++; encodings.total.inlineEstimate = true; }
                else { complete = false; }
            }
            if (complete) { known++; } else { unknown++; }
        }
        function requestBatch(items) {
            if (!items.length) { return Promise.resolve({}); }
            var body = new URLSearchParams({action: config.batchAction || config.action, nonce: config.nonce,
                items: JSON.stringify(items), inline_token: config.inlineToken || ''});
            return fetch(config.url, {method: 'POST', credentials: 'same-origin', body: body})
                .then(function (response) { if (!response.ok) { throw new Error('request'); } return response.json(); })
                .then(function (response) { return response.success && response.data && response.data.results ? response.data.results : {}; })
                .catch(function () { return {}; });
        }
        var localItems = [], externalItems = [];
        config.items.forEach(function (item) {
            var isExternal = false;
            if (item.url) {
                try { isExternal = new URL(item.url, window.location.href).host !== window.location.host; } catch (e) {}
            }
            (isExternal ? externalItems : localItems).push(item);
        });
        Promise.all([requestBatch(localItems), requestBatch(externalItems)]).then(function (groups) {
            config.items.forEach(function (item) {
                var result = groups[localItems.indexOf(item) !== -1 ? 0 : 1][String(item.id)] || {};
                record(item, result);
            });
            finish();
        });
    }
    menu.addEventListener('mouseenter', start);
    menu.addEventListener('focusin', start);
    menu.addEventListener('touchstart', start, {passive: true});
    var link = label.closest('a');
    if (link) { link.addEventListener('click', function (event) { event.preventDefault(); start(); }); }
    if (menu.matches(':hover') || menu.contains(document.activeElement)) { start(); }
}());
