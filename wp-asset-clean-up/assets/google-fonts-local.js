(function () {
    'use strict';

    var root = document.querySelector('[data-wpacu-google-fonts-local-card]');
    var configNode = document.getElementById('wpacu-google-fonts-local-config');

    if (!root || !configNode) {
        return;
    }

    var config;
    try {
        config = JSON.parse(configNode.textContent || '{}');
    } catch (error) {
        return;
    }

    if (!config.ajaxUrl || !config.action || !config.nonce) {
        return;
    }

    var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-wpacu-google-fonts-local-operation]'));
    var progress = root.querySelector('[data-wpacu-google-fonts-local-progress]');
    var progressBar = root.querySelector('[data-wpacu-google-fonts-local-progress-bar]');
    var statusNode = root.querySelector('[data-wpacu-google-fonts-local-status]');
    var settingsForm = root.closest ? root.closest('form') : null;
    var saveArea = settingsForm ? settingsForm.querySelector('#wpacu-update-button-area') : null;
    var stateKeys = config.keys || {};
    var running = false;
    var pendingSettingsSubmit = null;
    var warmupStorageKey = 'wpacuGoogleFontsLocalWarmup';
    var masterToggle = settingsForm ? settingsForm.querySelector('#wpacu_google_fonts_local') : null;
    var localHostingSavedEnabled = root.getAttribute('data-wpacu-google-fonts-local-saved-enabled') === '1';
    var featurePreview = root.querySelector('[data-wpacu-google-fonts-local-feature-preview]');
    var featurePreviewCollapsedHeight = 112;
    var warmupRequested = false;
    var inventoryScrollAnimationId = 0;
    var scanStartButton = root.querySelector('[data-wpacu-google-fonts-scan-start]');
    var scanCancelButton = root.querySelector('[data-wpacu-google-fonts-scan-cancel]');
    var scanExtraUrls = root.querySelector('[data-wpacu-google-fonts-scan-extra-urls]');
    var keyPageScanner = root.querySelector('[data-wpacu-google-fonts-key-pages-scan]');
    var scanDisabledNotice = root.querySelector('[data-wpacu-google-fonts-scan-disabled-notice]');
    var keyPageScanCancelled = false;
    var activeKeyPageScanFrame = null;
    var activeKeyPageScanFrameComplete = null;

    function getInventoryScrollOffset() {
        var quickNav = document.querySelector('.wpacu-google-fonts-quick-nav');

        if (!quickNav || !window.getComputedStyle || window.getComputedStyle(quickNav).position !== 'sticky') {
            return 12;
        }

        return (parseFloat(window.getComputedStyle(quickNav).top) || 0) + quickNav.getBoundingClientRect().height + 12;
    }

    function animateInventoryScroll(target) {
        var startY = window.pageYOffset;
        var targetY = Math.max(0, startY + target.getBoundingClientRect().top - getInventoryScrollOffset());
        var distance = targetY - startY;
        var duration = 280;
        var startTime = window.performance.now();
        var animationId = ++inventoryScrollAnimationId;
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduceMotion || distance === 0) {
            window.scrollTo(0, targetY);
            return;
        }

        function animate(currentTime) {
            if (animationId !== inventoryScrollAnimationId) {
                return;
            }

            var progress = Math.min((currentTime - startTime) / duration, 1);
            var easedProgress = 1 - Math.pow(1 - progress, 3);
            window.scrollTo(0, startY + (distance * easedProgress));

            if (progress < 1) {
                window.requestAnimationFrame(animate);
            }
        }

        window.requestAnimationFrame(animate);
    }

    Array.prototype.forEach.call(root.querySelectorAll('[data-wpacu-google-fonts-local-summary-target]'), function (link) {
        link.addEventListener('click', function (event) {
            if (!link.hasAttribute('href')) {
                return;
            }
            var target = root.querySelector(link.getAttribute('href'));
            var inventory = root.querySelector('.wpacu-google-fonts-local-inventory');
            if (!target || !inventory) {
                return;
            }
            event.preventDefault();
            inventory.open = true;
            window.requestAnimationFrame(function () {
                animateInventoryScroll(target);
            });
        });
    });

    if (config.enabled && config.automaticProcessing) {
        try {
            warmupRequested = window.sessionStorage.getItem(warmupStorageKey) === '1';
            if (warmupRequested) {
                window.sessionStorage.removeItem(warmupStorageKey);
            }
        } catch (error) {}
    }

    function label(name, fallback) {
        return config.labels && config.labels[name] ? config.labels[name] : fallback;
    }

    function isKeyPageScannerAvailable() {
        var localHostingSelected = masterToggle ? masterToggle.checked : localHostingSavedEnabled;

        return localHostingSavedEnabled && localHostingSelected;
    }

    function revealScanDisabledNotice(animate) {
        if (!scanDisabledNotice) {
            return;
        }

        scanDisabledNotice.hidden = false;
        scanDisabledNotice.classList.remove('is-fading-in', 'is-visible');
        if (!animate) {
            return;
        }

        scanDisabledNotice.classList.add('is-fading-in');
        scanDisabledNotice.getBoundingClientRect();
        window.requestAnimationFrame(function () {
            scanDisabledNotice.classList.add('is-visible');
        });
    }

    function syncLocalHostingFeaturePreview(localHostingSelected) {
        if (!featurePreview) {
            return;
        }

        var shouldCollapse = !localHostingSelected;
        if (featurePreview.classList.contains('is-collapsed') === shouldCollapse) {
            return;
        }

        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var currentHeight = featurePreview.getBoundingClientRect().height;

        featurePreview.style.height = currentHeight + 'px';
        featurePreview.classList.toggle('is-collapsed', shouldCollapse);
        featurePreview.getBoundingClientRect();

        if (reduceMotion) {
            featurePreview.style.height = localHostingSelected ? 'auto' : featurePreviewCollapsedHeight + 'px';
            if (shouldCollapse) {
                revealScanDisabledNotice(false);
            }
            return;
        }

        window.requestAnimationFrame(function () {
            featurePreview.style.height = (localHostingSelected ? featurePreview.scrollHeight : featurePreviewCollapsedHeight) + 'px';
        });
    }

    function syncKeyPageScannerAvailability() {
        var localHostingSelected = masterToggle ? masterToggle.checked : localHostingSavedEnabled;
        var scannerAvailable = localHostingSavedEnabled && localHostingSelected;
        var previewWillCollapse = featurePreview
            && !localHostingSelected
            && !featurePreview.classList.contains('is-collapsed');

        root.classList.toggle('is-local-hosting-disabled', !localHostingSelected);
        if (scanDisabledNotice) {
            if (localHostingSelected || previewWillCollapse) {
                scanDisabledNotice.hidden = true;
                scanDisabledNotice.classList.remove('is-fading-in', 'is-visible');
            } else {
                revealScanDisabledNotice(false);
            }
        }
        syncLocalHostingFeaturePreview(localHostingSelected);
        if (keyPageScanner) {
            keyPageScanner.classList.toggle('is-disabled', !scannerAvailable);
            keyPageScanner.setAttribute('aria-disabled', scannerAvailable ? 'false' : 'true');
        }
        if (scanStartButton) {
            scanStartButton.disabled = !scannerAvailable || running;
        }
        if (scanExtraUrls) {
            scanExtraUrls.disabled = !scannerAvailable;
        }
    }

    function cancelKeyPageScan(message) {
        if (!root.classList.contains('is-key-page-scanning')) {
            return;
        }

        keyPageScanCancelled = true;
        if (scanCancelButton) {
            scanCancelButton.disabled = true;
        }
        showProgress(message || 'Cancelling scan…', 0, 1, false);

        if (activeKeyPageScanFrameComplete) {
            activeKeyPageScanFrameComplete();
        }
    }

    function showPendingSaveNotice() {
        if (!saveArea || saveArea.querySelector('.wpacu-google-fonts-local-save-waiting')) {
            return;
        }

        var notice = document.createElement('div');
        var spinner = document.createElement('span');
        var message = document.createElement('span');
        notice.className = 'wpacu-google-fonts-local-save-waiting';
        notice.setAttribute('role', 'status');
        spinner.className = 'dashicons dashicons-update';
        spinner.setAttribute('aria-hidden', 'true');
        message.textContent = label(
            'waitingToSaveDetails',
            'Saving is waiting for the Google Fonts operation to finish. Your changes will be saved automatically.'
        );
        notice.appendChild(spinner);
        notice.appendChild(message);
        saveArea.insertBefore(notice, saveArea.firstChild);
    }

    function deferSettingsSubmit(event) {
        if (!running || !settingsForm) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        if (!pendingSettingsSubmit) {
            var submitter = event.submitter && event.submitter.form === settingsForm
                ? event.submitter
                : settingsForm.querySelector('#wpacu-update-button-area [type="submit"]');
            pendingSettingsSubmit = {
                submitter: submitter,
                wasDisabled: submitter ? submitter.disabled : false
            };
        }

        if (pendingSettingsSubmit.submitter) {
            pendingSettingsSubmit.submitter.disabled = true;
        }
        showPendingSaveNotice();

        if (statusNode) {
            statusNode.textContent = label('waitingToSave', 'Finishing the current operation before saving changes…');
        }
    }

    function submitPendingSettingsForm() {
        if (!pendingSettingsSubmit || !settingsForm) {
            return;
        }

        var pendingSubmit = pendingSettingsSubmit;
        pendingSettingsSubmit = null;

        if (pendingSubmit.submitter) {
            pendingSubmit.submitter.disabled = pendingSubmit.wasDisabled;
        }

        if (typeof settingsForm.requestSubmit === 'function') {
            if (pendingSubmit.submitter) {
                settingsForm.requestSubmit(pendingSubmit.submitter);
            } else {
                settingsForm.requestSubmit();
            }
            return;
        }

        if (pendingSubmit.submitter) {
            pendingSubmit.submitter.click();
        } else {
            settingsForm.submit();
        }
    }

    if (settingsForm) {
        settingsForm.addEventListener('submit', function () {
            if (!config.enabled && masterToggle && masterToggle.checked) {
                try {
                    window.sessionStorage.setItem(warmupStorageKey, '1');
                } catch (error) {}
            }
        }, true);
        settingsForm.addEventListener('submit', deferSettingsSubmit, true);
    }

    function busyGroupForOperation(operation, busyScope) {
        if (operation === 'retry-failed' && busyScope === 'specific') {
            return 'specific-failed';
        }

        var groups = {
            'process-pending': 'pending',
            'refresh-ready': 'ready',
            'retry-failed': 'error'
        };

        return groups[operation] || '';
    }

    function setBusy(isBusy, operation, busyScope) {
        var busyGroup = isBusy ? busyGroupForOperation(operation, busyScope) : '';
        var specificRoot = document.querySelector('.wpacu-google-fonts-specific');
        var operationButtons = buttons.slice(0);
        if (settingsForm) {
            Array.prototype.forEach.call(settingsForm.querySelectorAll('[data-wpacu-google-fonts-specific-retry-failed]'), function (button) {
                operationButtons.push(button);
            });
        }
        running = isBusy;
        root.classList.toggle('is-ajax-loading', isBusy);
        if (busyGroup) {
            root.setAttribute('data-wpacu-google-fonts-loading-group', busyGroup);
        } else {
            root.removeAttribute('data-wpacu-google-fonts-loading-group');
        }
        if (specificRoot) {
            specificRoot.classList.toggle('is-ajax-loading', isBusy && busyScope === 'specific');
            if (isBusy && busyScope === 'specific') {
                specificRoot.setAttribute('data-wpacu-google-fonts-loading-group', 'failed');
            } else {
                specificRoot.removeAttribute('data-wpacu-google-fonts-loading-group');
            }
        }
        operationButtons.forEach(function (button) {
            if (isBusy) {
                button.setAttribute('data-wpacu-was-disabled', button.disabled ? '1' : '0');
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            } else {
                if (button.getAttribute('data-wpacu-was-disabled') !== '1') {
                    button.disabled = false;
                }
                button.removeAttribute('data-wpacu-was-disabled');
                button.removeAttribute('aria-busy');
            }
        });
    }

    function showProgress(message, current, total, isError) {
        if (progress) {
            progress.hidden = false;
            progress.classList.toggle('is-error', !!isError);
        }

        if (statusNode) {
            statusNode.textContent = pendingSettingsSubmit
                ? label('waitingToSave', 'Finishing the current operation before saving changes…')
                : (message || '');
        }

        if (progressBar) {
            var percent = total > 0 ? Math.min(100, Math.round((current / total) * 100)) : 8;
            progressBar.style.width = percent + '%';
        }
    }

    function formatNumber(value) {
        return Math.max(0, Number(value) || 0).toLocaleString();
    }

    function formatBytes(value) {
        var bytes = Math.max(0, Number(value) || 0);
        var units = ['B', 'KB', 'MB', 'GB'];
        var unitIndex = 0;

        while (bytes >= 1024 && unitIndex < units.length - 1) {
            bytes /= 1024;
            unitIndex++;
        }

        return (unitIndex === 0 ? Math.round(bytes) : Math.round(bytes * 10) / 10) + ' ' + units[unitIndex];
    }

    function appendDash(cell) {
        var dash = document.createElement('span');
        dash.setAttribute('aria-hidden', 'true');
        dash.textContent = '—';
        cell.appendChild(dash);
    }

    function renderInventoryCount(count) {
        var node = root.querySelector('[data-wpacu-google-fonts-local-inventory-count]');
        if (!node) {
            return;
        }

        var message = count === 1
            ? label('inventoryOne', '%s discovered configuration')
            : label('inventoryMany', '%s discovered configurations');
        node.textContent = message.replace('%s', formatNumber(count));
    }

    function syncSummaryLink(item, key, count) {
        if (!item) {
            return;
        }

        var target = item.getAttribute('data-wpacu-google-fonts-local-summary-target');
        if (!target) {
            return;
        }

        count = Math.max(0, Number(count) || 0);
        if (count > 0) {
            item.setAttribute('href', target);
            item.setAttribute('data-wpacu-google-fonts-local-summary-link', '');
        } else {
            item.removeAttribute('href');
            item.removeAttribute('data-wpacu-google-fonts-local-summary-link');
        }
    }

    function renderSummary(summary) {
        ['ready', 'pending', 'error', 'font_count'].forEach(function (key) {
            var node = root.querySelector('[data-wpacu-google-fonts-local-count="' + key + '"]');
            if (node) {
                node.textContent = formatNumber(summary[key]);
                syncSummaryLink(node.closest('.wpacu-google-fonts-local-summary__item'), key, summary[key]);
            }
        });

        var bytesNode = root.querySelector('[data-wpacu-google-fonts-local-count="total_bytes"]');
        if (bytesNode) {
            bytesNode.textContent = formatBytes(summary.total_bytes);
        }
    }

    function statusLabel(status) {
        var names = {
            ready: label('statusReady', 'Ready'),
            pending: label('statusPending', 'Pending'),
            processing: label('statusProcessing', 'Processing'),
            error: label('statusError', 'Needs attention')
        };
        return names[status] || names.pending;
    }

    function inventoryGroupForStatus(status) {
        if (status === 'ready') {
            return 'ready';
        }
        if (status === 'error') {
            return 'error';
        }
        return 'pending';
    }

    function renderInventory(entries) {
        var emptyState = root.querySelector('[data-wpacu-google-fonts-local-empty]');
        var tableWrap = root.querySelector('[data-wpacu-google-fonts-local-table-wrap]');
        var groupCounts = { pending: 0, ready: 0, error: 0 };
        renderInventoryCount(entries.length);

        if (emptyState) {
            emptyState.hidden = entries.length > 0;
        }
        if (tableWrap) {
            tableWrap.hidden = entries.length === 0;
        }
        Array.prototype.forEach.call(root.querySelectorAll('[data-wpacu-google-fonts-local-group-body]'), function (body) {
            body.textContent = '';
        });

        if (!entries.length) {
            updateInventoryGroups(groupCounts);
            return;
        }

        entries.forEach(function (entry) {
            var status = ['ready', 'pending', 'processing', 'error'].indexOf(entry.status) !== -1 ? entry.status : 'pending';
            var row = document.createElement('tr');
            row.className = 'is-status-' + status;
            row.setAttribute('data-wpacu-google-fonts-local-entry', entry.key || '');

            var statusCell = document.createElement('td');
            statusCell.setAttribute('data-colname', label('statusColumn', 'Status'));
            var badge = document.createElement('span');
            badge.className = 'wpacu-google-fonts-local-status is-' + status;
            badge.textContent = statusLabel(status);
            statusCell.appendChild(badge);
            if (status !== 'ready' && Number(entry.attempts) > 0) {
                var attempts = document.createElement('small');
                attempts.className = 'wpacu-google-fonts-local-attempts';
                attempts.textContent = label('attempts', 'Attempts: %s').replace('%s', formatNumber(entry.attempts));
                statusCell.appendChild(attempts);
            }
            row.appendChild(statusCell);

            var configurationCell = document.createElement('td');
            configurationCell.setAttribute('data-colname', label('configurationColumn', 'Configuration'));
            var url = document.createElement('code');
            url.className = 'wpacu-google-fonts-local-url';
            url.title = entry.url || '';
            url.textContent = entry.url || '';
            configurationCell.appendChild(url);
            if (entry.lastError) {
                var error = document.createElement('p');
                error.className = 'wpacu-google-fonts-local-error';
                var errorIcon = document.createElement('span');
                errorIcon.className = 'dashicons dashicons-warning';
                errorIcon.setAttribute('aria-hidden', 'true');
                error.appendChild(errorIcon);
                error.appendChild(document.createTextNode(entry.lastError));
                configurationCell.appendChild(error);
            }
            row.appendChild(configurationCell);

            var pathsCell = document.createElement('td');
            pathsCell.setAttribute('data-colname', label('seenOnColumn', 'Seen on'));
            if (entry.paths && entry.paths.length) {
                var paths = document.createElement('ul');
                paths.className = 'wpacu-google-fonts-local-paths';
                entry.paths.slice(0, 1).forEach(function (path) {
                    var pathItem = document.createElement('li');
                    var pathCode = document.createElement('code');
                    pathCode.textContent = path;
                    pathItem.appendChild(pathCode);
                    paths.appendChild(pathItem);
                });
                pathsCell.appendChild(paths);
                if (entry.paths.length > 1) {
                    var morePaths = document.createElement('details');
                    var morePathsSummary = document.createElement('summary');
                    var additionalPaths = document.createElement('ul');
                    morePaths.className = 'wpacu-google-fonts-local-paths-more';
                    morePathsSummary.textContent = '+' + formatNumber(entry.paths.length - 1) + ' more';
                    additionalPaths.className = 'wpacu-google-fonts-local-paths';
                    entry.paths.slice(1).forEach(function (path) {
                        var pathItem = document.createElement('li');
                        var pathCode = document.createElement('code');
                        pathCode.textContent = path;
                        pathItem.appendChild(pathCode);
                        additionalPaths.appendChild(pathItem);
                    });
                    morePaths.appendChild(morePathsSummary);
                    morePaths.appendChild(additionalPaths);
                    pathsCell.appendChild(morePaths);
                }
            } else {
                appendDash(pathsCell);
            }
            row.appendChild(pathsCell);

            var localCell = document.createElement('td');
            localCell.setAttribute('data-colname', label('localCopyColumn', 'Local copy'));
            if (status === 'ready') {
                var localSummary = document.createElement('strong');
                localSummary.textContent = formatNumber(entry.fontCount) + ' files · ' + formatBytes(entry.totalBytes);
                localCell.appendChild(localSummary);
                if (entry.localCssUrl) {
                    var openCss = document.createElement('a');
                    openCss.href = entry.localCssUrl;
                    openCss.target = '_blank';
                    openCss.rel = 'noopener noreferrer';
                    openCss.className = 'wpacu-google-fonts-local-open-css';
                    openCss.textContent = label('openCss', 'Open CSS');
                    var openCssIcon = document.createElement('span');
                    openCssIcon.className = 'dashicons dashicons-external';
                    openCssIcon.setAttribute('aria-hidden', 'true');
                    openCss.appendChild(openCssIcon);
                    localCell.appendChild(openCss);
                }
            } else {
                appendDash(localCell);
            }
            row.appendChild(localCell);
            var groupKey = inventoryGroupForStatus(status);
            var groupBody = root.querySelector('[data-wpacu-google-fonts-local-group-body="' + groupKey + '"]');
            groupCounts[groupKey]++;
            if (groupBody) {
                groupBody.appendChild(row);
            }
        });
        updateInventoryGroups(groupCounts);
    }

    function updateInventoryGroups(groupCounts) {
        Object.keys(groupCounts).forEach(function (groupKey) {
            var count = groupCounts[groupKey];
            var countNode = root.querySelector('[data-wpacu-google-fonts-local-group-count="' + groupKey + '"]');
            var emptyNode = root.querySelector('[data-wpacu-google-fonts-local-group-empty="' + groupKey + '"]');
            var table = root.querySelector('[data-wpacu-google-fonts-local-group-table="' + groupKey + '"]');
            var group = root.querySelector('[data-wpacu-google-fonts-local-group="' + groupKey + '"]');
            if (countNode) countNode.textContent = formatNumber(count);
            if (emptyNode) emptyNode.hidden = count > 0;
            if (table) table.hidden = count === 0;
            if (groupKey === 'pending' && group) group.hidden = count === 0;
        });
    }

    function updateButtonStates(summary) {
        buttons.forEach(function (button) {
            var operation = button.getAttribute('data-wpacu-google-fonts-local-operation');
            if (operation === 'process-pending') {
                button.disabled = !(stateKeys.pending || []).length;
            } else if (operation === 'retry-failed') {
                button.disabled = !(stateKeys.error || []).length;
            } else if (operation === 'refresh-ready') {
                button.disabled = !(stateKeys.ready || []).length;
            } else if (operation === 'reset') {
                button.disabled = !summary || !Number(summary.total);
            }
        });
    }

    function specificInventoryLabel(labels, name, fallback) {
        return labels && labels[name] ? labels[name] : fallback;
    }

    function countSpecificFamilies(readyFamilies, failedFamilies) {
        var familyKeys = {};
        var familyCount = 0;
        readyFamilies.concat(failedFamilies).forEach(function (familyData) {
            if (!familyData || !familyData.family) {
                return;
            }
            var familyKey = String(familyData.family).toLowerCase();
            if (!Object.prototype.hasOwnProperty.call(familyKeys, familyKey)) {
                familyKeys[familyKey] = true;
                familyCount++;
            }
        });
        return familyCount;
    }

    function renderSpecificFamilyCards(group, families, labels, isPro, checkboxName, selectedTokens) {
        families.forEach(function (familyData) {
            if (!familyData || !familyData.family || !Array.isArray(familyData.variants) || !familyData.variants.length) {
                return;
            }

            var familyName = String(familyData.family);
            var variants = familyData.variants;
            var family = document.createElement('section');
            var header = document.createElement('header');
            var summary = document.createElement('div');
            var name = document.createElement('strong');
            var count = document.createElement('span');
            var sourceLabels = [];
            var sources = Array.isArray(familyData.sources) ? familyData.sources : [];
            var urls = Array.isArray(familyData.urls) ? familyData.urls.filter(function (url, index, allUrls) {
                return typeof url === 'string'
                    && /^https:\/\/fonts\.googleapis\.com(?::443)?(?:\/|$)/i.test(url)
                    && allUrls.indexOf(url) === index;
            }) : [];
            var hasPreviouslyDiscovered = variants.some(function (variant) {
                return variant && variant.availability === 'previously_discovered';
            });

            family.className = 'wpacu-google-fonts-specific__family';
            family.setAttribute('data-family', familyName.toLowerCase());
            name.textContent = familyName;
            count.textContent = specificInventoryLabel(
                labels,
                variants.length === 1 ? 'variantOne' : 'variantMany',
                variants.length === 1 ? '%d variant' : '%d variants'
            ).replace('%d', variants.length);
            summary.appendChild(name);
            summary.appendChild(count);

            if (sources.indexOf('remote') !== -1) {
                sourceLabels.push(specificInventoryLabel(labels, 'deliveredByGoogle', 'Delivered by Google'));
            }
            if (sources.indexOf('local') !== -1) {
                sourceLabels.push(specificInventoryLabel(labels, 'localCopy', 'Local copy'));
            }
            if (hasPreviouslyDiscovered) {
                sourceLabels.push(specificInventoryLabel(labels, 'previouslyDiscovered', 'Previously discovered'));
            }
            if (sourceLabels.length || urls.length) {
                var provenance = document.createElement('small');
                provenance.className = 'wpacu-google-fonts-specific__provenance';
                provenance.textContent = sourceLabels.join(' · ');
                urls.forEach(function (url, urlIndex) {
                    var separator = document.createElement('span');
                    var stylesheetLink = document.createElement('a');
                    separator.textContent = sourceLabels.length || urlIndex > 0 ? ' · ' : '';
                    stylesheetLink.textContent = 'fonts.googleapis.com' + (urls.length > 1 ? ' #' + (urlIndex + 1) : '');
                    stylesheetLink.setAttribute('href', url);
                    stylesheetLink.setAttribute('target', '_blank');
                    stylesheetLink.setAttribute('rel', 'noopener noreferrer');
                    stylesheetLink.setAttribute('data-wpacu-google-fonts-specific-stylesheet-link', '');
                    provenance.appendChild(separator);
                    provenance.appendChild(stylesheetLink);
                });
                summary.appendChild(provenance);
            }

            header.appendChild(summary);
            if (isPro) {
                var actions = document.createElement('div');
                var selectAll = document.createElement('button');
                var separator = document.createElement('span');
                var clear = document.createElement('button');
                actions.className = 'wpacu-google-fonts-specific__family-actions';
                selectAll.type = 'button';
                selectAll.className = 'button-link wpacu-google-fonts-specific__family-select-all';
                selectAll.textContent = specificInventoryLabel(labels, 'selectAll', 'Select all');
                separator.setAttribute('aria-hidden', 'true');
                separator.textContent = '|';
                clear.type = 'button';
                clear.className = 'button-link wpacu-google-fonts-specific__family-clear';
                clear.textContent = specificInventoryLabel(labels, 'clearSelection', 'Clear selection');
                actions.appendChild(selectAll);
                actions.appendChild(separator);
                actions.appendChild(clear);
                header.appendChild(actions);
            }
            family.appendChild(header);

            var variantsNode = document.createElement('div');
            variantsNode.className = 'wpacu-google-fonts-specific__variants';
            variants.forEach(function (variant) {
                if (!variant || !variant.token || !variant.weight) {
                    return;
                }

                var style = variant.style === 'italic' ? 'italic' : 'normal';
                var row = document.createElement('label');
                var variantInput = document.createElement('input');
                var check = document.createElement('span');
                var variantName = document.createElement('span');
                var variantStrong = document.createElement('strong');
                var styleBadge = document.createElement('span');
                row.className = 'wpacu-google-fonts-specific__variant' + (isPro ? '' : ' is-locked');
                variantInput.type = 'checkbox';
                variantInput.value = variant.token;
                variantInput.checked = !!variant.selected || !!selectedTokens[variant.token];
                variantInput.disabled = !isPro;
                if (isPro && checkboxName) {
                    variantInput.name = checkboxName;
                }
                check.className = 'wpacu-google-fonts-specific__check';
                check.setAttribute('aria-hidden', 'true');
                variantName.className = 'wpacu-google-fonts-specific__variant-name';
                variantStrong.textContent = familyName + ' — ' + variant.weight;
                variantName.appendChild(variantStrong);
                styleBadge.className = 'wpacu-google-fonts-specific__style-badge is-' + style;
                styleBadge.textContent = specificInventoryLabel(labels, style === 'italic' ? 'italic' : 'regular', style === 'italic' ? 'Italic' : 'Regular');
                row.appendChild(variantInput);
                row.appendChild(check);
                row.appendChild(variantName);
                row.appendChild(styleBadge);
                if (!isPro) {
                    var lockedAction = document.createElement('span');
                    var lockIcon = document.createElement('span');
                    lockedAction.className = 'wpacu-google-fonts-specific__action';
                    lockIcon.className = 'dashicons dashicons-lock';
                    lockIcon.setAttribute('aria-hidden', 'true');
                    lockedAction.appendChild(lockIcon);
                    row.appendChild(lockedAction);
                }
                variantsNode.appendChild(row);
            });
            family.appendChild(variantsNode);
            group.appendChild(family);
        });
    }

    function renderSpecificGroup(content, groupName, families, labels, isPro, checkboxName, selectedTokens, failedConfigurationCount, showFailedAdvice) {
        var group = document.createElement('section');
        var header = document.createElement('header');
        var heading = document.createElement('h4');
        var count = document.createElement('span');
        var variantCount = families.reduce(function (total, family) {
            return total + (Array.isArray(family.variants) ? family.variants.length : 0);
        }, 0);
        group.className = 'wpacu-google-fonts-specific__group is-' + groupName;
        group.setAttribute('data-wpacu-google-fonts-specific-group', groupName);
        group.hidden = groupName === 'ready'
            ? families.length === 0
            : families.length === 0 && failedConfigurationCount <= 0;
        heading.textContent = groupName === 'ready'
            ? specificInventoryLabel(labels, 'readyVariants', 'Ready variants')
            : specificInventoryLabel(labels, 'failedVariants', 'Failed variants');
        header.className = 'wpacu-google-fonts-specific__group-header';
        count.className = 'wpacu-google-fonts-specific__group-count';
        count.textContent = String(variantCount);
        heading.appendChild(count);
        header.appendChild(heading);

        if (groupName === 'failed') {
            var retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'button';
            retry.setAttribute('data-wpacu-google-fonts-specific-retry-failed', '');
            retry.textContent = specificInventoryLabel(labels, 'retryFailed', 'Retry failed');
            retry.disabled = !isPro || running;
            header.appendChild(retry);
        }

        group.appendChild(header);
        if (groupName === 'failed') {
            var intro = document.createElement('div');
            var introIcon = document.createElement('span');
            var introCopy = document.createElement('span');
            var advice = document.createElement('div');
            var adviceIcon = document.createElement('span');
            var adviceCopy = document.createElement('span');
            intro.className = 'wpacu-google-fonts-specific__failed-intro';
            intro.setAttribute('data-wpacu-google-fonts-specific-failed-intro', '');
            introIcon.className = 'dashicons dashicons-info-outline';
            introIcon.setAttribute('aria-hidden', 'true');
            introCopy.textContent = specificInventoryLabel(labels, 'failedIntro', 'Some detected Google Fonts variants could not be loaded and may no longer be needed. Review them before deciding whether to keep or remove them.');
            intro.appendChild(introIcon);
            intro.appendChild(introCopy);
            advice.className = 'wpacu-google-fonts-specific__failed-advice';
            advice.setAttribute('data-wpacu-google-fonts-specific-failed-advice', '');
            advice.hidden = !(showFailedAdvice && failedConfigurationCount > 0);
            adviceIcon.className = 'dashicons dashicons-warning';
            adviceIcon.setAttribute('aria-hidden', 'true');
            adviceCopy.textContent = specificInventoryLabel(labels, 'failedAdvice', 'Retry completed, but some stylesheet URLs still failed. Open the Google APIs URLs to confirm they are valid, then select and remove any variants you no longer need.');
            advice.appendChild(adviceIcon);
            advice.appendChild(adviceCopy);
            group.appendChild(intro);
            group.appendChild(advice);
        }
        renderSpecificFamilyCards(group, families, labels, isPro, checkboxName, selectedTokens);
        content.appendChild(group);
    }

    function renderSpecificInventory(inventory, showFailedAdvice) {
        var specificRoot = document.querySelector('.wpacu-google-fonts-specific');
        if (!specificRoot || !inventory) {
            return;
        }

        var inventoryRoot = specificRoot.querySelector('[data-wpacu-google-fonts-specific-inventory]');
        if (!inventoryRoot) {
            return;
        }

        var labels = inventory.labels || {};
        var readyFamilies = Array.isArray(inventory.ready) ? inventory.ready : [];
        var failedFamilies = Array.isArray(inventory.failed) ? inventory.failed : [];
        var failedConfigurationCount = Math.max(0, Number(inventory.failedConfigurationCount) || 0);
        var existingSearch = specificRoot.querySelector('.wpacu-google-fonts-specific__search');
        var existingSearchScreenReader = specificRoot.querySelector('.wpacu-google-fonts-specific__toolbar .screen-reader-text');
        var existingBox = specificRoot.querySelector('.wpacu-google-fonts-specific__variant input[type="checkbox"]');
        var serialized = specificRoot.querySelector('.wpacu-google-fonts-specific__serialized');
        var selectedTokens = {};
        var isPro = specificRoot.classList.contains('is-pro');
        var checkboxName = existingBox && existingBox.name ? existingBox.name : '';

        Array.prototype.forEach.call(specificRoot.querySelectorAll('.wpacu-google-fonts-specific__variant input[type="checkbox"]'), function (box) {
            if (box.checked) {
                selectedTokens[box.value] = true;
            }
        });
        if (!checkboxName && serialized) {
            checkboxName = String(serialized.getAttribute('data-name') || '')
                .replace('[google_fonts_remove_specific_serialized]', '[google_fonts_remove_specific][]');
        }

        var empty = document.createElement('div');
        var emptyIcon = document.createElement('span');
        var emptyCopy = document.createElement('div');
        var emptyTitle = document.createElement('strong');
        var emptyDescription = document.createElement('p');
        empty.className = 'wpacu-google-fonts-specific__empty';
        empty.setAttribute('data-wpacu-google-fonts-specific-empty', '');
        empty.hidden = readyFamilies.length > 0 || failedFamilies.length > 0;
        emptyIcon.className = 'dashicons dashicons-search';
        emptyIcon.setAttribute('aria-hidden', 'true');
        emptyTitle.textContent = specificInventoryLabel(labels, 'emptyTitle', 'No Google Fonts variants have been discovered yet.');
        emptyDescription.textContent = specificInventoryLabel(labels, 'emptyDescription', 'Visit the front-end pages that use Google Fonts, then return here. Detected stylesheet configurations are used to build this list.');
        emptyCopy.appendChild(emptyTitle);
        emptyCopy.appendChild(emptyDescription);
        empty.appendChild(emptyIcon);
        empty.appendChild(emptyCopy);

        var content = document.createElement('div');
        var toolbar = document.createElement('div');
        var searchLabel = document.createElement('label');
        var searchScreenReader = document.createElement('span');
        var search = document.createElement('input');
        var familyCount = document.createElement('span');
        var familyTotal = countSpecificFamilies(readyFamilies, failedFamilies);
        content.setAttribute('data-wpacu-google-fonts-specific-content', '');
        content.hidden = familyTotal === 0 && failedConfigurationCount <= 0;
        toolbar.className = 'wpacu-google-fonts-specific__toolbar';
        searchScreenReader.className = 'screen-reader-text';
        searchScreenReader.textContent = existingSearchScreenReader
            ? existingSearchScreenReader.textContent
            : 'Search detected Google Fonts';
        search.type = 'search';
        search.className = 'wpacu-google-fonts-specific__search';
        search.placeholder = existingSearch ? existingSearch.placeholder : 'Search font families…';
        search.value = existingSearch ? existingSearch.value : '';
        search.disabled = !isPro;
        searchLabel.appendChild(searchScreenReader);
        searchLabel.appendChild(search);
        familyCount.textContent = specificInventoryLabel(
            labels,
            familyTotal === 1 ? 'familyOne' : 'familyMany',
            familyTotal === 1 ? '%d family detected' : '%d families detected'
        ).replace('%d', familyTotal);
        toolbar.appendChild(searchLabel);
        toolbar.appendChild(familyCount);
        content.appendChild(toolbar);

        var families = document.createElement('div');
        families.className = 'wpacu-google-fonts-specific__families';
        renderSpecificGroup(families, 'ready', readyFamilies, labels, isPro, checkboxName, selectedTokens, failedConfigurationCount, false);
        renderSpecificGroup(families, 'failed', failedFamilies, labels, isPro, checkboxName, selectedTokens, failedConfigurationCount, showFailedAdvice);
        content.appendChild(families);

        if (isPro) {
            var oldSaveNote = specificRoot.querySelector('.wpacu-google-fonts-specific__footer > span');
            var footer = document.createElement('div');
            var selection = document.createElement('div');
            var selectionCount = document.createElement('strong');
            var selectionLabel = document.createElement('span');
            var saveNote = document.createElement('span');
            footer.className = 'wpacu-google-fonts-specific__footer';
            selectionCount.className = 'wpacu-google-fonts-specific__selected-count';
            selectionCount.textContent = '0';
            selectionLabel.textContent = specificInventoryLabel(labels, 'selectionCount', 'variants selected for removal');
            saveNote.textContent = oldSaveNote ? oldSaveNote.textContent : 'Save Changes to apply the selection.';
            selection.appendChild(selectionCount);
            selection.appendChild(selectionLabel);
            footer.appendChild(selection);
            footer.appendChild(saveNote);
            content.appendChild(footer);
        }

        inventoryRoot.textContent = '';
        inventoryRoot.appendChild(empty);
        inventoryRoot.appendChild(content);

        var renderedEvent;
        if (typeof window.CustomEvent === 'function') {
            renderedEvent = new window.CustomEvent('wpacu:google-fonts-specific-rendered', { bubbles: true });
        } else {
            renderedEvent = document.createEvent('CustomEvent');
            renderedEvent.initCustomEvent('wpacu:google-fonts-specific-rendered', true, false, null);
        }
        specificRoot.dispatchEvent(renderedEvent);
    }

    function applySnapshot(data, showFailedAdvice) {
        if (!data) {
            return;
        }
        if (data.keys) {
            stateKeys = data.keys;
            config.keys = stateKeys;
        }
        if (data.summary) {
            renderSummary(data.summary);
        }
        if (Array.isArray(data.entries)) {
            renderInventory(data.entries);
        }
        if (data.specificInventory) {
            renderSpecificInventory(data.specificInventory, !!showFailedAdvice);
        }
        if (!running) {
            updateButtonStates(data.summary || {});
        }
    }

    function request(operation, key) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            var body = new FormData();
            body.append('action', config.action);
            body.append('nonce', config.nonce);
            body.append('operation', operation);
            if (key) {
                body.append('key', key);
            }

            xhr.open('POST', config.ajaxUrl, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) {
                    return;
                }

                var response;
                try {
                    response = JSON.parse(xhr.responseText || '{}');
                } catch (error) {
                    reject(new Error(label('network', 'The request failed.')));
                    return;
                }

                if (xhr.status < 200 || xhr.status >= 300 || !response.success) {
                    var message = response.data && response.data.message
                        ? response.data.message
                        : label('failed', 'The operation could not be completed.');
                    reject(new Error(message));
                    return;
                }

                resolve(response.data || {});
            };
            xhr.onerror = function () {
                reject(new Error(label('network', 'The request failed.')));
            };
            xhr.send(body);
        });
    }

    function runKeys(operation, keys, index, lastData) {
        if (index >= keys.length) {
            return Promise.resolve(lastData);
        }

        showProgress(label('processing', 'Processing local Google Fonts…') + ' ' + (index + 1) + '/' + keys.length, index, keys.length, false);
        return request(operation, keys[index]).then(function (data) {
            applySnapshot(data);
            if (data.result && data.result.success === false && data.message) {
                showProgress(data.message, index + 1, keys.length, true);
            }
            return runKeys(operation, keys, index + 1, data);
        });
    }

    function runPending(processed, total, guard, lastData) {
        if (guard > 200) {
            return Promise.reject(new Error('The pending queue exceeded its safety limit.'));
        }

        showProgress(label('processing', 'Processing local Google Fonts…') + (total ? ' ' + Math.min(processed + 1, total) + '/' + total : ''), processed, total, false);
        return request('process_next', '').then(function (data) {
            applySnapshot(data);
            if (!data.processed) {
                return data || lastData;
            }

            var nextProcessed = processed + 1;
            showProgress(data.message || label('processing', 'Processing local Google Fonts…'), nextProcessed, total, data.result && data.result.success === false);

            if (data.hasMore) {
                return runPending(nextProcessed, Math.max(total, nextProcessed + 1), guard + 1, data);
            }
            return data;
        });
    }

    function finishOperation(message, isError, data, showFailedAdvice) {
        setBusy(false);
        applySnapshot(data, showFailedAdvice);
        showProgress(message, 1, 1, isError);
        submitPendingSettingsForm();
    }

    function runWarmupPage(urls, index, processAfterDiscovery) {
        if (keyPageScanCancelled) {
            return Promise.resolve({ cancelled: true });
        }
        if (index >= urls.length) {
            return processAfterDiscovery ? runPending(0, 0, 0) : request('snapshot', '');
        }

        showProgress(
            label('discovering', 'Discovering Google Fonts on representative pages…') + ' ' + (index + 1) + '/' + urls.length,
            index,
            urls.length,
            false
        );

        return new Promise(function (resolve) {
            var iframe = document.createElement('iframe');
            var completed = false;
            var timeout;
            iframe.hidden = true;
            iframe.setAttribute('aria-hidden', 'true');

            function complete() {
                if (completed) {
                    return;
                }
                completed = true;
                window.clearTimeout(timeout);
                if (iframe.parentNode) {
                    iframe.parentNode.removeChild(iframe);
                }
                if (activeKeyPageScanFrame === iframe) {
                    activeKeyPageScanFrame = null;
                    activeKeyPageScanFrameComplete = null;
                }
                resolve();
            }

            activeKeyPageScanFrame = iframe;
            activeKeyPageScanFrameComplete = complete;

            iframe.onload = function () {
                window.setTimeout(complete, 3500);
            };
            iframe.onerror = complete;
            timeout = window.setTimeout(complete, 7000);
            iframe.src = urls[index];
            document.body.appendChild(iframe);
        }).then(function () {
            if (keyPageScanCancelled) {
                return { cancelled: true };
            }
            return request('snapshot', '').then(function (data) {
                applySnapshot(data);
            }).catch(function () {
                // A transient inventory refresh failure must not stop the next page.
            });
        }).then(function (data) {
            if (data && data.cancelled) {
                return data;
            }
            return runWarmupPage(urls, index + 1, processAfterDiscovery);
        });
    }

    function runWarmup() {
        var urls = Array.isArray(config.warmupUrls) ? config.warmupUrls.slice(0, 4) : [];
        var promise = urls.length ? runWarmupPage(urls, 0, true) : runPending(0, 0, 0);
        return promise.then(function (data) {
            data = data || {};
            data.message = label('warmupComplete', 'Discovery finished. Local copies were prepared.');
            return data;
        });
    }

    function collectKeyPageScanUrls() {
        var candidates = Array.isArray(config.warmupUrls) ? config.warmupUrls.slice(0) : [];
        if (scanExtraUrls && scanExtraUrls.value) {
            candidates = candidates.concat(scanExtraUrls.value.split(/\r?\n/));
        }

        var unique = {};
        return candidates.map(function (candidate) {
            try {
                var url = new URL(String(candidate).trim(), window.location.href);
                if (url.origin !== window.location.origin
                    || !/^https?:$/.test(url.protocol)
                    || /\/(?:wp-admin|wp-login\.php|wp-json|cart|checkout|my-account)(?:\/|$)/i.test(url.pathname)
                    || /(?:^|\/)feed\/?$/i.test(url.pathname)
                    || url.searchParams.has('preview')
                ) {
                    return '';
                }
                url.hash = '';
                return url.href;
            } catch (error) {
                return '';
            }
        }).filter(function (url) {
            if (!url || unique[url]) {
                return false;
            }
            unique[url] = true;
            return true;
        }).slice(0, 30);
    }

    function runKeyPageScan() {
        if (running) {
            return;
        }
        if (!isKeyPageScannerAvailable()) {
            return;
        }
        var urls = collectKeyPageScanUrls();
        if (!urls.length) {
            showProgress(label('failed', 'No eligible public pages were selected.'), 1, 1, true);
            return;
        }

        keyPageScanCancelled = false;
        root.classList.add('is-key-page-scanning');
        setBusy(true);
        if (scanStartButton) scanStartButton.disabled = true;
        if (scanCancelButton) {
            scanCancelButton.disabled = false;
            scanCancelButton.hidden = false;
        }

        runWarmupPage(urls, 0, !!config.enabled).then(function (data) {
            if (data && data.cancelled) {
                finishOperation('Scan cancelled.', false, data);
            } else {
                finishOperation(urls.length + ' key pages scanned. The Google Fonts inventory was updated.', false, data);
            }
        }).catch(function (error) {
            finishOperation(error && error.message ? error.message : label('failed', 'The scan could not be completed.'), true);
        }).then(function () {
            root.classList.remove('is-key-page-scanning');
            if (scanCancelButton) scanCancelButton.hidden = true;
            syncKeyPageScannerAvailability();
        }, function () {
            root.classList.remove('is-key-page-scanning');
            if (scanCancelButton) scanCancelButton.hidden = true;
            syncKeyPageScannerAvailability();
        });
    }

    function execute(operation, busyScope) {
        if (running) {
            return;
        }

        var promise;
        var keys = stateKeys;

        if (operation === 'reset' && !window.confirm(label('confirmReset', 'Reset active local Google Fonts copies safely?'))) {
            return;
        }

        if (operation === 'clear' && !window.confirm(label('confirmClear', 'Delete all local Google Fonts files immediately?'))) {
            return;
        }

        setBusy(true, operation, busyScope);
        showProgress(label('processing', 'Processing local Google Fonts…'), 0, 1, false);

        if (operation === 'process-pending') {
            promise = runPending(0, (keys.pending || []).length, 0);
        } else if (operation === 'auto-process') {
            promise = runKeys('refresh', (keys.outdated || []).slice(0), 0).then(function () {
                var pendingKeys = (keys.pending || []).slice(0);
                return pendingKeys.length ? runPending(0, pendingKeys.length, 0) : Promise.resolve();
            });
        } else if (operation === 'retry-failed') {
            promise = runKeys('retry', (keys.error || []).slice(0), 0);
        } else if (operation === 'refresh-ready') {
            promise = runKeys('refresh', (keys.ready || []).slice(0), 0);
        } else if (operation === 'cleanup' || operation === 'reset' || operation === 'clear') {
            promise = request(operation, '');
        } else {
            setBusy(false);
            return;
        }

        promise.then(function (data) {
            var message = data && data.message ? data.message : label('complete', 'The operation finished.');
            finishOperation(message, false, data, operation === 'retry-failed');
        }).catch(function (error) {
            finishOperation(error && error.message ? error.message : label('failed', 'The operation could not be completed.'), true);
        });
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            execute(button.getAttribute('data-wpacu-google-fonts-local-operation'));
        });
    });

    if (settingsForm) {
        settingsForm.addEventListener('click', function (event) {
            var retryButton = event.target.closest
                ? event.target.closest('[data-wpacu-google-fonts-specific-retry-failed]')
                : null;
            if (!retryButton) {
                return;
            }
            event.preventDefault();
            execute('retry-failed', 'specific');
        });
    }

    if (scanStartButton) {
        scanStartButton.addEventListener('click', runKeyPageScan);
    }
    if (scanCancelButton) {
        scanCancelButton.addEventListener('click', function () {
            cancelKeyPageScan('Cancelling scan…');
        });
    }
    if (masterToggle) {
        masterToggle.addEventListener('change', function () {
            if (!masterToggle.checked) {
                cancelKeyPageScan('Local hosting was switched off. The key-page scan was cancelled.');
            }
            syncKeyPageScannerAvailability();
        });
    }
    if (featurePreview) {
        featurePreview.addEventListener('transitionend', function (event) {
            if (event.propertyName !== 'height') {
                return;
            }
            if (featurePreview.classList.contains('is-collapsed')) {
                revealScanDisabledNotice(true);
            } else {
                featurePreview.style.height = 'auto';
            }
        });
    }

    syncKeyPageScannerAvailability();

    if (config.autoProcessPending && !warmupRequested) {
        window.setTimeout(function () {
            execute('auto-process');
        }, 150);
    }

    if (warmupRequested) {
        window.setTimeout(function () {
            setBusy(true);
            runWarmup().then(function (data) {
                finishOperation(label('warmupComplete', 'Discovery finished. Local copies were prepared.'), false, data);
            }).catch(function (error) {
                finishOperation(error && error.message ? error.message : label('failed', 'The operation could not be completed.'), true);
            });
        }, 200);
    }
}());
