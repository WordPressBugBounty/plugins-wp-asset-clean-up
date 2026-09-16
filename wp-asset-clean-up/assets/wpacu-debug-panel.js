(function () {
    'use strict';

    var script = document.currentScript;
    if (!script) { return; }

    var hostId = script.getAttribute('data-wpacu-debug-host');
    var cssUrl = script.getAttribute('data-wpacu-debug-css');
    var host = hostId ? document.getElementById(hostId) : null;
    if (!host) { return; }

    var content = host.querySelector('[data-wpacu-debug-content]');
    var configNode = content ? content.querySelector('[data-wpacu-debug-config]') : null;
    var config = {};
    try { config = configNode ? JSON.parse(configNode.textContent || '{}') : {}; } catch (e) { config = {}; }
    if (configNode && configNode.parentNode) { configNode.parentNode.removeChild(configNode); }

    var root = host;
    if (host.attachShadow) {
        root = host.attachShadow({mode: 'open'});
        if (cssUrl) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = cssUrl;
            root.appendChild(link);
        }
    }

    var app = document.createElement('section');
    app.className = 'wpacu-debug-app';
    var header = document.createElement('header');
    header.className = 'wpacu-debug-header';
    header.innerHTML = '<div class="wpacu-debug-heading"><div class="wpacu-debug-mark" aria-hidden="true">AC</div>' +
        '<div><h2 class="wpacu-debug-title"></h2><p class="wpacu-debug-subtitle"></p></div></div>' +
        '<div class="wpacu-debug-badges"><span class="wpacu-debug-badge" data-wpacu-request-badge></span><span class="wpacu-debug-badge" data-wpacu-active-badge>0</span></div>';
    var labels = config.labels || {};
    header.querySelector('.wpacu-debug-title').textContent = (config.productName || 'Asset CleanUp') + ' — ' + (labels.title || 'Live Debugging');
    header.querySelector('.wpacu-debug-subtitle').textContent = labels.subtitle || '';
    header.querySelector('[data-wpacu-request-badge]').textContent = labels.requestOnly || 'Temporary preview';

    var body = document.createElement('div');
    body.className = 'wpacu-debug-body';
    app.appendChild(header);
    app.appendChild(body);
    root.appendChild(app);

    if (config.previewApplied) {
        createPreviewAppliedAlert();
    }

    if (content) {
        content.style.display = 'block';
        while (content.firstChild) { body.appendChild(content.firstChild); }
        if (content.parentNode) { content.parentNode.removeChild(content); }
    }

    var form = body.querySelector('form');
    if (!form) { return; }

    var groupLabels = {css: labels.css || 'CSS', javascript: labels.javascript || 'JavaScript', other: labels.other || 'Other'};
    var controls = document.createElement('section');
    controls.className = 'wpacu-debug-controls';
    var groupLists = {};
    var unavailableLists = {};
    var unavailableDetails = {};
    var unavailableCounts = {};
    ['css', 'javascript', 'other'].forEach(function (group) {
        var section = document.createElement('section');
        section.className = 'wpacu-debug-control-group';
        var heading = document.createElement('h3');
        heading.appendChild(createGroupIcon(group));
        heading.appendChild(document.createTextNode(groupLabels[group]));
        var list = document.createElement('div');
        list.className = 'wpacu-debug-control-list';
        var unavailable = document.createElement('details');
        unavailable.className = 'wpacu-debug-unavailable';
        unavailable.hidden = true;
        var unavailableSummary = document.createElement('summary');
        var unavailableSummaryText = document.createElement('span');
        unavailableSummaryText.className = 'wpacu-debug-unavailable-title';
        unavailableSummaryText.textContent = labels.unavailableOptions || 'Unavailable options';
        var unavailableCount = document.createElement('span');
        unavailableCount.className = 'wpacu-debug-unavailable-count';
        unavailableCount.textContent = '0';
        unavailableSummary.appendChild(unavailableSummaryText);
        unavailableSummary.appendChild(unavailableCount);
        var unavailableList = document.createElement('div');
        unavailableList.className = 'wpacu-debug-unavailable-list';
        var unavailableListInner = document.createElement('div');
        unavailableListInner.className = 'wpacu-debug-unavailable-list-inner';
        unavailableList.appendChild(unavailableListInner);
        unavailable.appendChild(unavailableSummary);
        unavailable.appendChild(unavailableList);
        unavailableSummary.addEventListener('click', function (event) {
            event.preventDefault();
            if (!unavailable.open) {
                unavailable.open = true;
                unavailableList.offsetHeight;
                unavailable.classList.add('is-expanded');
                return;
            }

            unavailable.classList.remove('is-expanded');
            var closeAfterTransition = function (transitionEvent) {
                if (transitionEvent && transitionEvent.propertyName !== 'grid-template-rows') { return; }
                unavailableList.removeEventListener('transitionend', closeAfterTransition);
                if (!unavailable.classList.contains('is-expanded')) { unavailable.open = false; }
            };
            unavailableList.addEventListener('transitionend', closeAfterTransition);
            window.setTimeout(function () { closeAfterTransition(); }, 280);
        });
        section.appendChild(heading);
        section.appendChild(list);
        section.appendChild(unavailable);
        controls.appendChild(section);
        groupLists[group] = list;
        unavailableLists[group] = unavailableListInner;
        unavailableDetails[group] = unavailable;
        unavailableCounts[group] = unavailableCount;
    });

    var switches = Array.isArray(config.switches) ? config.switches : [];
    var selectedSwitches = Array.isArray(config.selectedSwitches) ? config.selectedSwitches : [];
    var selectedPlugins = Array.isArray(config.selectedPlugins) ? config.selectedPlugins : [];
    var switchControls = [];
    switches.forEach(function (item) {
        if (!item || !item.key) { return; }
        var selector = 'input[type="checkbox"][name="wpacu_debug_options[' + cssEscape(item.key) + ']"]';
        var input = form.querySelector(selector);
        if (!input) {
            input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'wpacu_debug_options[' + item.key + ']';
            input.value = '1';
        } else {
            var oldContainer = input.closest('label, li, p, tr, div');
            if (oldContainer) { oldContainer.classList.add('wpacu-debug-original-control--consumed'); }
        }
        if (selectedSwitches.indexOf(item.key) !== -1) { input.checked = true; }
        input.removeAttribute('style');
        if (item.key === 'wpacu_no_cache') { input.id = 'wpacu-debug-bypass-optimized-cache'; }
        var label = document.createElement('label');
        label.className = 'wpacu-debug-toggle';
        var copy = document.createElement('span');
        copy.className = 'wpacu-debug-toggle-copy';
        var title = document.createElement('span');
        title.className = 'wpacu-debug-toggle-title';
        title.textContent = item.label || item.key;
        if (item.notice) {
            var notice = document.createElement('span');
            notice.className = 'wpacu-debug-toggle-notice';
            notice.textContent = item.notice;
            title.appendChild(notice);
        }
        var description = document.createElement('span');
        description.className = 'wpacu-debug-toggle-description';
        description.textContent = item.description || '';
        copy.appendChild(title);
        copy.appendChild(description);
        label.appendChild(input);
        label.appendChild(copy);
        if (item.disabled) {
            input.checked = !!item.checked;
            input.disabled = true;
            label.classList.add('is-disabled');
        }
        function updateSelectedState() {
            label.classList.toggle('is-selected', input.checked);
        }
        input.addEventListener('change', updateSelectedState);
        updateSelectedState();
        var targetGroup = groupLists[item.group] ? item.group : 'other';
        if (item.disabled) {
            unavailableLists[targetGroup].appendChild(label);
            var disabledCount = unavailableLists[targetGroup].children.length;
            unavailableCounts[targetGroup].textContent = String(disabledCount);
            unavailableDetails[targetGroup].hidden = false;
        } else {
            groupLists[targetGroup].appendChild(label);
        }
        switchControls.push({item: item, input: input, label: label, title: title, description: description, group: targetGroup});
    });
    form.insertBefore(controls, form.firstChild);

    var pageOptionInputs = {};
    var pageOptionLabels = config.pageOptions || {};
    if (Object.keys(pageOptionLabels).length) {
        var pageOptions = document.createElement('section');
        pageOptions.className = 'wpacu-debug-control-group';
        pageOptions.style.margin = '18px 0';
        var pageHeading = document.createElement('h3');
        pageHeading.textContent = labels.pageOptionsTitle || 'Page Options';
        pageOptions.appendChild(pageHeading);
        var pageHelp = document.createElement('p');
        pageHelp.textContent = labels.pageOptionsHelp;
        pageHelp.style.padding = '10px 16px 0';
        pageOptions.appendChild(pageHelp);
        var present = document.createElement('input');
        present.type = 'hidden'; present.name = 'wpacu_debug_page_options_present'; present.value = '1';
        pageOptions.appendChild(present);
        var pageGrid = document.createElement('div');
        pageGrid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));';
        Object.keys(pageOptionLabels).forEach(function (key) {
            var label = document.createElement('label');
            label.className = 'wpacu-debug-toggle';
            var input = document.createElement('input');
            input.type = 'checkbox'; input.name = 'wpacu_debug_page_options[' + key + ']'; input.value = '1';
            input.checked = (config.selectedPageOptions || {})[key] === '1';
            pageOptionInputs[key] = input;
            var copy = document.createElement('span');
            copy.className = 'wpacu-debug-toggle-copy';
            var title = document.createElement('span');
            title.className = 'wpacu-debug-toggle-title';
            title.textContent = pageOptionLabels[key];
            copy.appendChild(title);
            if (key === 'no_wpacu_load') {
                var note = document.createElement('small');
                var warning = document.createElement('strong');
                warning.textContent = labels.pageOptionsNoLoadWarning || 'Warning:';
                warning.style.fontWeight = '700';
                note.appendChild(warning);
                note.appendChild(document.createTextNode(' ' + (labels.pageOptionsNoLoadHelp || '')));
                note.style.cssText = 'display:block;color:#cc0000;margin-top:6px;font-weight:400;';
                copy.appendChild(note);
            }
            label.appendChild(input); label.appendChild(copy); pageGrid.appendChild(label);
            label.addEventListener('click', function (event) {
                if (input.getAttribute('aria-disabled') === 'true') { event.preventDefault(); }
            });
        });
        function updatePageOptions() {
            Object.keys(pageOptionInputs).forEach(function (key) {
                var input = pageOptionInputs[key];
                var blocked = key !== 'no_wpacu_load' && (pageOptionInputs.no_wpacu_load.checked
                    || (key !== 'no_assets_settings' && pageOptionInputs.no_assets_settings.checked));
                input.setAttribute('aria-disabled', blocked ? 'true' : 'false');
                input.tabIndex = blocked ? -1 : 0;
                input.parentNode.classList.toggle('is-selected', input.checked);
                input.parentNode.style.opacity = blocked ? '0.45' : '';
                input.parentNode.style.cursor = blocked ? 'not-allowed' : '';
                input.parentNode.title = blocked ? labels.pageOptionsOverridden : '';
            });
            var individualOverrides = {
                wpacu_no_css_minify: 'no_css_minify',
                wpacu_no_css_combine: 'no_css_optimize',
                wpacu_no_js_minify: 'no_js_minify',
                wpacu_no_js_combine: 'no_js_optimize'
            };
            switchControls.forEach(function (control) {
                var key = individualOverrides[control.item.key];
                var pageBlocked = !control.item.disabled && (
                    (pageOptionInputs.no_wpacu_load.checked && (control.group === 'css' || control.group === 'javascript'))
                    || (pageOptionInputs.no_assets_settings.checked && (control.group === 'css' || control.group === 'javascript'))
                    || (key && pageOptionInputs[key].checked));
                control.input.disabled = !!control.item.disabled || !!pageBlocked;
                control.label.classList.toggle('is-disabled', control.input.disabled);
                var pageNotice = control.title.querySelector('[data-wpacu-page-option-notice]');
                if (pageBlocked) {
                    if (!pageNotice) {
                        pageNotice = document.createElement('span');
                        pageNotice.className = 'wpacu-debug-toggle-notice';
                        pageNotice.setAttribute('data-wpacu-page-option-notice', '');
                        control.title.appendChild(pageNotice);
                    }
                    pageNotice.textContent = labels.disabledByPageOptions || 'Disabled by Page Options';
                    control.description.textContent = labels.disabledByPageOptionsHelp || 'Remove the Page Options override below to use this debugging control.';
                } else {
                    if (pageNotice) { pageNotice.remove(); }
                    control.description.textContent = control.item.description || '';
                }
                (control.input.disabled ? unavailableLists[control.group] : groupLists[control.group]).appendChild(control.label);
            });
            Object.keys(unavailableLists).forEach(function (group) {
                var count = unavailableLists[group].children.length;
                unavailableCounts[group].textContent = String(count);
                unavailableDetails[group].hidden = count === 0;
            });
        }
        pageGrid.addEventListener('change', updatePageOptions);
        pageOptions.appendChild(pageGrid);
        controls.parentNode.insertBefore(pageOptions, controls.nextSibling);
        updatePageOptions();
    }

    var activeBadge = header.querySelector('[data-wpacu-active-badge]');
    function updateActiveCount() {
        var count = form.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)').length;
        activeBadge.textContent = count + ' ' + (labels.selected || 'selected');
    }
    form.addEventListener('change', updateActiveCount);
    updateActiveCount();

    var pluginInputs = Array.prototype.slice.call(form.querySelectorAll('input[type="checkbox"][name="wpacu_debug_filter_plugins[]"]'));
    if (pluginInputs.length) {
        pluginInputs.forEach(function (input) {
            if (selectedPlugins.indexOf(String(input.value || '')) !== -1) { input.checked = true; }
        });
        var pluginUnloadDisabled = false;
        var firstRow = closestRow(pluginInputs[0]);
        var toolbar = document.createElement('div');
        toolbar.className = 'wpacu-debug-plugin-toolbar';
        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'wpacu-debug-plugin-search';
        search.placeholder = labels.searchPlugins || 'Search active plugins';
        search.setAttribute('aria-label', search.placeholder);
        var count = document.createElement('span');
        count.className = 'wpacu-debug-plugin-count';
        var clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'wpacu-debug-button';
        clear.textContent = labels.clearSelection || 'Clear selected plugins';
        toolbar.appendChild(search); toolbar.appendChild(count); toolbar.appendChild(clear);
        var pluginTable = firstRow && firstRow.tagName && firstRow.tagName.toLowerCase() === 'tr' ? firstRow.closest('table') : null;
        if (pluginTable) { pluginTable.classList.add('wpacu-debug-plugin-table'); }
        if (pluginTable && pluginTable.parentNode) { pluginTable.parentNode.insertBefore(toolbar, pluginTable); }
        else if (firstRow && firstRow.parentNode) { firstRow.parentNode.insertBefore(toolbar, firstRow); }
        else { form.insertBefore(toolbar, form.firstChild.nextSibling); }
        var noMatches = document.createElement('p');
        noMatches.className = 'wpacu-debug-no-plugin-matches';
        noMatches.textContent = labels.noMatches || 'No plugins match this search.';
        toolbar.parentNode.insertBefore(noMatches, toolbar.nextSibling);
        var pluginSection = pluginTable ? pluginTable.closest('.wpacu-debug-plugin-section') : null;
        var disabledNotice = document.createElement('p');
        disabledNotice.className = 'wpacu-debug-plugin-disabled-notice';
        disabledNotice.textContent = labels.pluginsDisabled || 'Plugin unloading is disabled by the option above. The selections below will not apply to this preview.';
        disabledNotice.hidden = true;
        if (pluginSection) {
            var pluginHeader = pluginSection.querySelector('.wpacu-debug-plugin-section-header');
            if (pluginHeader && pluginHeader.nextSibling) { pluginSection.insertBefore(disabledNotice, pluginHeader.nextSibling); }
            else { pluginSection.insertBefore(disabledNotice, pluginSection.firstChild); }
        }

        function updatePlugins() {
            var q = (search.value || '').toLowerCase().trim();
            var selected = 0, visible = 0;
            pluginInputs.forEach(function (input) {
                var row = closestRow(input);
                if (!row) { return; }
                row.classList.add('wpacu-debug-plugin-row');
                var value = String(input.value || '').toLowerCase();
                var text = String(row.textContent || '').toLowerCase();
                highlightMatches(row.querySelector('.wpacu_plugin_title'), q);
                highlightMatches(row.querySelector('.wpacu_plugin_path'), q);
                var selfPlugin = value.indexOf('wp-asset-clean-up-pro') !== -1 || value.indexOf('wp-asset-clean-up/') !== -1;
                if (selfPlugin) {
                    input.checked = false; input.disabled = true; row.classList.add('is-self');
                }
                row.classList.toggle('is-selected', input.checked);
                if (input.checked && !input.disabled) { selected += 1; }
                var show = !q || text.indexOf(q) !== -1 || value.indexOf(q) !== -1;
                row.hidden = !show;
                if (show) { visible += 1; }
            });
            count.textContent = pluginUnloadDisabled
                ? (labels.disabledByOption || 'disabled by option')
                : selected + ' ' + (selected === 1
                    ? (labels.pluginSelected || 'plugin selected')
                    : (labels.pluginsSelected || 'plugins selected'));
            noMatches.classList.toggle('is-visible', visible === 0);
            updateActiveCount();
        }
        search.addEventListener('input', updatePlugins);
        pluginInputs.forEach(function (input) { input.addEventListener('change', updatePlugins); });
        clear.addEventListener('click', function () { pluginInputs.forEach(function (input) { if (!input.disabled) { input.checked = false; } }); updatePlugins(); });
        updatePlugins();

        var noPluginUnloadInput = form.querySelector('input[type="checkbox"][name="wpacu_debug_options[wpacu_no_plugin_unload]"]');
        function syncPluginUnloadAvailability() {
            pluginUnloadDisabled = !!(noPluginUnloadInput && noPluginUnloadInput.checked);
            pluginInputs.forEach(function (input) {
                var value = String(input.value || '');
                var selfPlugin = value.indexOf('wp-asset-clean-up-pro') !== -1 || value.indexOf('wp-asset-clean-up/') !== -1;
                input.disabled = pluginUnloadDisabled || selfPlugin;
            });
            if (pluginSection) { pluginSection.classList.toggle('is-disabled-by-option', pluginUnloadDisabled); }
            disabledNotice.hidden = !pluginUnloadDisabled;
            updatePlugins();
        }
        if (noPluginUnloadInput) { noPluginUnloadInput.addEventListener('change', syncPluginUnloadAvailability); }
        syncPluginUnloadAvailability();
    }

    // The legacy two-column layout table wraps the form. Searching only
    // inside the form picks the nested plugins table and breaks every plugin
    // row into layout columns.
    var layoutTable = form.closest('table') || findLayoutTable(body);
    if (layoutTable) {
        layoutTable.classList.add('wpacu-debug-layout-table');
        var firstBody = layoutTable.tBodies && layoutTable.tBodies.length ? layoutTable.tBodies[0] : null;
        var firstLayoutRow = firstBody && firstBody.rows.length ? firstBody.rows[0] : null;
        if (firstLayoutRow && firstLayoutRow.cells.length >= 2) {
            firstLayoutRow.cells[0].classList.add('wpacu-debug-primary-pane');
            firstLayoutRow.cells[1].classList.add('wpacu-debug-details-pane');
            var details = document.createElement('details');
            details.className = 'wpacu-debug-details';
            details.open = true;
            var summary = document.createElement('summary');
            summary.textContent = labels.details || 'Current request and performance details';
            summary.addEventListener('click', function (event) { event.preventDefault(); });
            details.appendChild(summary);
            if (config.htmlAlterationsDisabled) {
                var timingsNotice = document.createElement('div');
                timingsNotice.className = 'wpacu-debug-timings-notice';
                var timingsNoticeList = document.createElement('ul');
                var skippedNoticeItem = document.createElement('li');
                var skippedNoticeText = labels.timingsUnavailableBefore || 'Some timing values are marked “Skipped” because their operations are disabled by';
                var skippedWordAt = skippedNoticeText.indexOf('Skipped');
                if (skippedWordAt !== -1) {
                    skippedNoticeItem.appendChild(document.createTextNode(skippedNoticeText.slice(0, skippedWordAt)));
                    var skippedWord = document.createElement('strong');
                    skippedWord.textContent = 'Skipped';
                    skippedNoticeItem.appendChild(skippedWord);
                    skippedNoticeItem.appendChild(document.createTextNode(skippedNoticeText.slice(skippedWordAt + 7) + ' '));
                } else {
                    skippedNoticeItem.appendChild(document.createTextNode(skippedNoticeText + ' '));
                }
                var timingsOption = document.createElement('span');
                timingsOption.className = 'wpacu-debug-timings-option';
                timingsOption.textContent = labels.timingsUnavailableOption || 'Disable HTML source alterations';
                skippedNoticeItem.appendChild(timingsOption);
                var notApplicableNoticeItem = document.createElement('li');
                notApplicableNoticeItem.textContent = labels.timingsUnavailableAfter || 'Other values can show “N/A” when they do not apply to this request.';
                timingsNoticeList.appendChild(skippedNoticeItem);
                timingsNoticeList.appendChild(notApplicableNoticeItem);
                timingsNotice.appendChild(timingsNoticeList);
                details.appendChild(timingsNotice);
            }
            while (firstLayoutRow.cells[1].firstChild) { details.appendChild(firstLayoutRow.cells[1].firstChild); }
            firstLayoutRow.cells[1].appendChild(details);
            enhanceDetailsPanel(details, config, labels);
        }
    }

    var submit = form.querySelector('button[type="submit"], input[type="submit"]');
    if (submit) {
        if (submit.tagName.toLowerCase() === 'input') {
            var submitButton = document.createElement('button');
            submitButton.type = 'submit';
            submitButton.textContent = labels.submit || submit.value;
            submit.parentNode.replaceChild(submitButton, submit);
            submit = submitButton;
        }
        submit.classList.add('wpacu-debug-submit');
        submit.textContent = labels.submit || submit.textContent;
        var actions = document.createElement('div');
        actions.className = 'wpacu-debug-actions';
        var reset = document.createElement('button');
        reset.type = 'button'; reset.className = 'wpacu-debug-button'; reset.textContent = labels.reset || 'Reset all preview choices';
        reset.addEventListener('click', function () {
            Array.prototype.forEach.call(form.querySelectorAll('input[type="checkbox"]'), function (input) {
                var control = switchControls.filter(function (entry) { return entry.input === input; })[0];
                if (!input.disabled || (control && !control.item.disabled)) {
                    var pageKey = Object.keys(pageOptionInputs).filter(function (key) { return pageOptionInputs[key] === input; })[0];
                    input.checked = pageKey ? (config.savedPageOptions || {})[pageKey] === '1' : false;
                    input.dispatchEvent(new Event('change', {bubbles: true}));
                }
            });
            updateActiveCount();
        });
        actions.appendChild(reset); actions.appendChild(submit);
        var actionsDock = document.createElement('div');
        actionsDock.className = 'wpacu-debug-actions-dock';
        form.appendChild(actionsDock);
        form.appendChild(actions);
        var actionsPane = form.closest('.wpacu-debug-primary-pane') || form.parentElement;
        var positionActions = function () {
            if (!actionsPane || !actions.classList.contains('is-floating')) { return; }
            var paneRect = actionsPane.getBoundingClientRect();
            actions.style.left = Math.max(0, paneRect.left) + 'px';
            actions.style.width = Math.min(window.innerWidth - Math.max(0, paneRect.left), paneRect.width) + 'px';
        };
        var actionTriggers = Array.prototype.slice.call(form.querySelectorAll('.wpacu-debug-toggle:not(.is-disabled)'));
        actionTriggers.sort(function (first, second) { return first.getBoundingClientRect().top - second.getBoundingClientRect().top; });
        var actionTriggerRows = actionTriggers.filter(function (trigger, index, triggers) {
            return index === 0 || Math.abs(trigger.getBoundingClientRect().top - triggers[index - 1].getBoundingClientRect().top) > 10;
        });
        var actionTrigger = actionTriggerRows[3] || actionTriggerRows[actionTriggerRows.length - 1] || form.querySelector('.wpacu-debug-toggle');
        var actionsDockedHeight = actions.getBoundingClientRect().height || 70;
        var actionsTransitionTimer = null;
        var updateActionsMode = function () {
            if (!actionTrigger) { return; }
            if (!actions.classList.contains('is-floating')) {
                actionsDockedHeight = actions.getBoundingClientRect().height || actionsDockedHeight;
            }
            var triggerReached = actionTrigger.getBoundingClientRect().top <= window.innerHeight * 0.94;
            var dockReached = actionsDock.getBoundingClientRect().top <= window.innerHeight - actionsDockedHeight - 18;
            if (dockReached) {
                if (actionsTransitionTimer) {
                    window.clearTimeout(actionsTransitionTimer);
                    actionsTransitionTimer = null;
                }
                actions.classList.remove('is-visible', 'is-hiding', 'is-floating');
                actions.style.removeProperty('left');
                actions.style.removeProperty('width');
            } else if (triggerReached) {
                if (actionsTransitionTimer) {
                    window.clearTimeout(actionsTransitionTimer);
                    actionsTransitionTimer = null;
                }
                actions.classList.remove('is-hiding');
                var startsFloating = !actions.classList.contains('is-floating');
                if (startsFloating) {
                    actions.classList.add('is-floating');
                }
                positionActions();
                if (startsFloating) {
                    actions.getBoundingClientRect();
                }
                actions.classList.add('is-visible');
            } else if (actions.classList.contains('is-visible') && !actions.classList.contains('is-hiding')) {
                actions.classList.add('is-hiding');
                actionsTransitionTimer = window.setTimeout(function () {
                    actions.classList.remove('is-visible', 'is-hiding', 'is-floating');
                    actions.style.removeProperty('left');
                    actions.style.removeProperty('width');
                    actionsTransitionTimer = null;
                }, 180);
            }
        };
        updateActionsMode();
        window.addEventListener('scroll', updateActionsMode, {passive: true});
        window.addEventListener('resize', function () { updateActionsMode(); positionActions(); });
        if (window.ResizeObserver) {
            new ResizeObserver(function () { updateActionsMode(); positionActions(); }).observe(actionsPane);
        }
        form.addEventListener('submit', function () {
            submit.disabled = true;
            submit.setAttribute('aria-disabled', 'true');
            submit.classList.add('is-loading');
        });
    }

    function closestRow(input) {
        return input.closest('tr') || input.closest('.wpacu-plugin-row, li, label, div');
    }
    function enhanceDetailsPanel(details, panelConfig, panelLabels) {
        var timingRoot = details.querySelector('.wpacu-debug-timing-root');
        if (!timingRoot) { return; }

        var requestChanges = panelConfig.requestChanges || {};
        var originalNodes = Array.prototype.slice.call(details.children).filter(function (node) {
            return node.tagName && node.tagName.toLowerCase() !== 'summary' && !node.classList.contains('wpacu-debug-timings-notice');
        });
        var dashboard = document.createElement('div');
        dashboard.className = 'wpacu-debug-report';

        var requestSection = document.createElement('section');
        requestSection.className = 'wpacu-debug-report-section';
        requestSection.appendChild(createReportHeading(panelLabels.requestChanges || 'Request changes'));
        var requestList = document.createElement('div');
        requestList.className = 'wpacu-debug-request-list';
        requestList.appendChild(createRequestDisclosure(panelLabels.unloadedPlugins || 'Unloaded plugins', requestChanges.plugins, 'plugins', panelLabels));
        requestList.appendChild(createRequestDisclosure(panelLabels.cssHandles || 'Unloaded CSS handles', requestChanges.cssHandles, 'css', panelLabels));
        requestList.appendChild(createRequestDisclosure(panelLabels.jsHandles || 'Unloaded JS handles', requestChanges.jsHandles, 'js', panelLabels));
        requestSection.appendChild(requestList);
        dashboard.appendChild(requestSection);

        var timingData = collectTimingData(timingRoot, panelLabels);
        var performanceSection = document.createElement('section');
        performanceSection.className = 'wpacu-debug-report-section';
        performanceSection.appendChild(createReportHeading(panelLabels.performanceSummary || 'Performance summary'));
        var metrics = document.createElement('div');
        metrics.className = 'wpacu-debug-performance-metrics';
        metrics.appendChild(createMetric(panelLabels.totalRecorded || 'Total recorded', timingData.total, 'total'));
        metrics.appendChild(createMetric(panelLabels.cssProcessing || 'CSS processing', timingData.css.value, 'css'));
        metrics.appendChild(createMetric(panelLabels.jsProcessing || 'JS processing', timingData.javascript.value, 'js'));
        metrics.appendChild(createMetric(panelLabels.htmlProcessing || 'HTML processing', timingData.htmlValue, 'html'));
        performanceSection.appendChild(metrics);

        var timingGroups = document.createElement('div');
        timingGroups.className = 'wpacu-debug-timing-groups';
        timingData.groups.forEach(function (group) {
            timingGroups.appendChild(createTimingDisclosure(group));
        });
        performanceSection.appendChild(timingGroups);

        if (timingGroups.querySelector('.wpacu-debug-timing-row.is-secondary')) {
            var secondaryToggle = document.createElement('label');
            secondaryToggle.className = 'wpacu-debug-secondary-toggle';
            var secondaryInput = document.createElement('input');
            secondaryInput.type = 'checkbox';
            var secondaryCopy = document.createElement('span');
            secondaryCopy.textContent = panelLabels.showSecondaryTimings || 'Show unavailable and zero-value details';
            secondaryToggle.appendChild(secondaryInput);
            secondaryToggle.appendChild(secondaryCopy);
            secondaryInput.addEventListener('change', function () {
                dashboard.classList.toggle('show-secondary-timings', secondaryInput.checked);
                Array.prototype.forEach.call(timingGroups.querySelectorAll('details.wpacu-debug-timing-disclosure'), function (disclosure) {
                    if (!disclosure.querySelector('.wpacu-debug-timing-row.is-secondary')) { return; }
                    if (secondaryInput.checked) {
                        disclosure.dataset.wpacuOpenBeforeSecondary = disclosure.open ? '1' : '0';
                        disclosure.open = true;
                    } else {
                        disclosure.open = disclosure.dataset.wpacuOpenBeforeSecondary === '1';
                        delete disclosure.dataset.wpacuOpenBeforeSecondary;
                    }
                });
            });
            performanceSection.appendChild(secondaryToggle);
        }
        dashboard.appendChild(performanceSection);

        var optimizationSection = document.createElement('section');
        optimizationSection.className = 'wpacu-debug-report-section';
        optimizationSection.style.marginTop = '16px';
        optimizationSection.appendChild(createReportHeading(panelLabels.optimizationDetails || 'CSS/JS optimization details'));
        var optimizationHelp = document.createElement('p');
        optimizationHelp.style.cssText = 'padding:12px;margin:0;';
        optimizationHelp.textContent = panelLabels.optimizationDetailsHelp;
        optimizationSection.appendChild(optimizationHelp);
        var records = Array.isArray(config.optimizationDetails) ? config.optimizationDetails : [];
        ['css', 'js'].forEach(function (type) {
            var rows = records.filter(function (row) { return row.type === type; });
            var disclosure = document.createElement('details');
            disclosure.className = 'wpacu-debug-timing-disclosure';
            var summary = document.createElement('summary');
            var assets = Object.create(null);
            rows.forEach(function (row) {
                var key = JSON.stringify([row.handle, row.url]);
                if (!assets[key]) { assets[key] = []; }
                assets[key].push(row);
            });
            if (type === 'js') { disclosure.style.setProperty('margin-top', '16px', 'important'); }
            summary.textContent = (type === 'css' ? 'CSS' : 'JavaScript') + ' (' + Object.keys(assets).length + ')';
            summary.style.gridTemplateColumns = 'minmax(0, 1fr) auto';
            disclosure.appendChild(summary);
            Object.keys(assets).forEach(function (key) {
                var assetRows = assets[key];
                var row = assetRows[0];
                var entry = document.createElement('div');
                entry.style.cssText = 'padding:12px;border-top:1px solid #e5ecef;overflow-wrap:anywhere;';
                var handle = document.createElement('strong');
                handle.textContent = row.handle || panelLabels.optimizationUnregistered || 'Unregistered asset';
                var url = document.createElement('div');
                url.textContent = row.url;
                url.style.cssText = 'font-size:12px;margin:4px 0;';
                entry.appendChild(handle);
                if (assetRows.some(function (item) { return item.generatedHandle; })) {
                    var info = document.createElement('span');
                    info.textContent = 'ⓘ';
                    var explanation = panelLabels.optimizationGeneratedHandle || 'Asset CleanUp generated this identifier from the file URL because this asset was not found in the WordPress enqueue list. It is not a registered WordPress handle.';
                    info.setAttribute('aria-label', explanation);
                    info.setAttribute('role', 'img');
                    info.tabIndex = 0;
                    info.style.cssText = 'position:relative;display:inline-block;margin-left:6px;color:#607780;cursor:help;font-size:13px;font-weight:normal;';
                    var tooltip = document.createElement('span');
                    tooltip.setAttribute('role', 'tooltip');
                    tooltip.textContent = explanation;
                    tooltip.style.cssText = 'position:absolute;bottom:calc(100% + 9px);right:-8px;width:280px;max-width:70vw;padding:10px 12px;background:#263840;color:#fff;border-radius:5px;box-shadow:0 3px 12px #0003;font-size:12px;font-weight:normal;line-height:1.5;text-align:left;white-space:normal;z-index:1000;';
                    tooltip.style.setProperty('display', 'none', 'important');
                    var arrow = document.createElement('span');
                    arrow.style.cssText = 'position:absolute;top:100%;right:9px;width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;border-top:6px solid #263840;';
                    tooltip.appendChild(arrow);
                    info.appendChild(tooltip);
                    var hovered = false;
                    var focused = false;
                    function updateTooltip() {
                        tooltip.style.setProperty('display', hovered || focused ? 'block' : 'none', 'important');
                    }
                    info.addEventListener('mouseenter', function () { hovered = true; updateTooltip(); });
                    info.addEventListener('mouseleave', function () { hovered = false; updateTooltip(); });
                    info.addEventListener('focus', function () { focused = true; updateTooltip(); });
                    info.addEventListener('blur', function () { focused = false; updateTooltip(); });
                    info.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') { hovered = false; focused = false; updateTooltip(); }
                    });
                    entry.appendChild(info);
                }
                entry.appendChild(url);
                assetRows.forEach(function (row, index) {
                    var operationEntry = document.createElement('div');
                    if (index) { operationEntry.style.marginTop = '10px'; }
                    var outcome = document.createElement('strong');
                    var status = row.status === 'Failed' ? panelLabels.optimizationStatusFailed : row.status === 'No change needed' ? panelLabels.optimizationStatusUnchanged : panelLabels.optimizationStatusSkipped;
                    if (row.status === 'Content changed') { status = panelLabels.optimizationStatusChanged || 'Content changed'; }
                    if (row.status === 'Cached') { status = panelLabels.optimizationStatusCached || 'Cached'; }
                    var operation = row.operation === 'Minification' ? panelLabels.optimizationMinification : row.operation === 'File optimization' ? panelLabels.optimizationFile : (panelLabels.optimizationOperations || {})[row.operation];
                    outcome.textContent = (operation || row.operation) + ' — ' + (status || row.status);
                    outcome.style.color = row.status === 'Failed' ? '#c00' : row.status === 'No change needed' ? '#176b55' : '#805000';
                    var reason = document.createElement('div');
                    reason.textContent = row.reason;
                    var bypassControl = form.querySelector('input[name="wpacu_debug_options[wpacu_no_cache]"]');
                    var bypassLabel = switches.filter(function (item) { return item.key === 'wpacu_no_cache'; })[0];
                    var bypassText = bypassLabel ? bypassLabel.label : 'Bypass optimized-file cache';
                    var reasonText = String(row.reason || '');
                    var bypassAt = reasonText.indexOf(bypassText);
                    if (bypassControl && bypassText && bypassAt !== -1) {
                        reason.textContent = '';
                        reason.appendChild(document.createTextNode(reasonText.slice(0, bypassAt)));
                        var bypassLink = document.createElement('a');
                        bypassLink.href = '#wpacu-debug-bypass-optimized-cache';
                        bypassLink.textContent = bypassText;
                        bypassLink.style.cssText = 'color:#0073aa;text-decoration:underline;';
                        bypassLink.addEventListener('click', function (event) {
                            event.preventDefault();
                            var parent = bypassControl.parentElement;
                            while (parent && parent !== form) {
                                if (parent.tagName === 'DETAILS') { parent.open = true; }
                                parent = parent.parentElement;
                            }
                            bypassControl.focus({preventScroll: true});
                            var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                            bypassControl.scrollIntoView({behavior: reducedMotion ? 'auto' : 'smooth', block: 'center'});
                    });
                    reason.appendChild(bypassLink);
                    reason.appendChild(document.createTextNode(reasonText.slice(bypassAt + bypassText.length)));
                }
                operationEntry.appendChild(outcome); operationEntry.appendChild(reason);
                entry.appendChild(operationEntry);
                });
                disclosure.appendChild(entry);
            });
            if (!rows.length) {
                var empty = document.createElement('p');
                empty.style.padding = '12px';
                empty.textContent = panelLabels.optimizationDetailsEmpty || 'No file-level exceptions were recorded.';
                disclosure.appendChild(empty);
            }
            optimizationSection.appendChild(disclosure);
        });
        dashboard.appendChild(optimizationSection);

        originalNodes.forEach(function (node) { node.remove(); });
        details.appendChild(dashboard);
    }
    function createReportHeading(textValue) {
        var heading = document.createElement('h4');
        heading.className = 'wpacu-debug-report-heading';
        heading.textContent = textValue;
        return heading;
    }
    function createRequestDisclosure(titleText, items, type, panelLabels) {
        items = Array.isArray(items) ? items : [];
        if (!items.length) {
            var emptyRow = document.createElement('div');
            emptyRow.className = 'wpacu-debug-request-disclosure wpacu-debug-request-disclosure--' + type + ' is-empty';
            var emptyTitle = document.createElement('span');
            emptyTitle.textContent = titleText;
            var emptyCount = document.createElement('span');
            emptyCount.className = 'wpacu-debug-request-count';
            emptyCount.textContent = panelLabels.none || 'None';
            emptyRow.appendChild(emptyTitle);
            emptyRow.appendChild(emptyCount);
            return emptyRow;
        }
        var disclosure = document.createElement('details');
        disclosure.className = 'wpacu-debug-request-disclosure wpacu-debug-request-disclosure--' + type;
        var summary = document.createElement('summary');
        var title = document.createElement('span');
        title.textContent = titleText;
        var count = document.createElement('span');
        count.className = 'wpacu-debug-request-count';
        count.textContent = String(items.length);
        count.classList.add('has-items');
        summary.appendChild(title);
        summary.appendChild(count);
        disclosure.appendChild(summary);
        var list = document.createElement('ul');
        items.forEach(function (item) {
            var li = document.createElement('li');
            li.textContent = String(item);
            list.appendChild(li);
        });
        disclosure.appendChild(list);
        return disclosure;
    }
    function collectTimingData(root, panelLabels) {
        var outerItems = Array.prototype.slice.call(root.children).filter(function (node) { return node.tagName && node.tagName.toLowerCase() === 'li'; });
        var optimizeCommon = findTimingItem(outerItems, 'OptimizeCommon');
        var nestedRoot = optimizeCommon ? optimizeCommon.querySelector('#wpacu-debug-timing') : null;
        var nestedItems = nestedRoot ? Array.prototype.slice.call(nestedRoot.children).filter(isListItem) : [];
        var cssItem = findTimingItem(nestedItems, 'OptimizeCSS');
        var jsItem = findTimingItem(nestedItems, 'OptimizeJs');
        var hardcodedItem = findTimingItem(nestedItems, 'Hardcoded CSS/JS');
        var htmlCleanupItem = findTimingItem(nestedItems, 'HTML CleanUp');
        var preparationItems = outerItems.filter(function (item) { return /^(Dequeue|Prepare CSS|Prepare JS)/i.test(getDirectText(item)); });
        var loaderItems = outerItems.filter(function (item) { return /(?:loader_tag|Output CSS)/i.test(getDirectText(item)); });
        var htmlItems = nestedItems.filter(function (item) { return /^(Strip any references|Apply any Resource Loading)/i.test(getDirectText(item)); });
        if (htmlCleanupItem) { htmlItems = htmlItems.concat(childTimingItems(htmlCleanupItem)); }
        var total = 0;
        Array.prototype.forEach.call(root.querySelectorAll('[data-wpacu-count-it]'), function (item) {
            var number = parseFloat(item.getAttribute('data-wpacu-count-it') || '');
            if (isFinite(number)) { total += number; }
        });
        var css = timingGroup(panelLabels.cssProcessing || 'CSS processing', 'css', cssItem ? childTimingItems(cssItem) : [], timingValue(cssItem));
        var javascript = timingGroup(panelLabels.jsProcessing || 'JS processing', 'js', jsItem ? childTimingItems(jsItem) : [], timingValue(jsItem));
        var preparation = timingGroup(panelLabels.assetPreparation || 'Asset preparation', 'prepare', preparationItems, sumTimingItems(preparationItems));
        var loaders = timingGroup(panelLabels.loaderTagFilters || 'Loader tag filters', 'loader', loaderItems, sumTimingItems(loaderItems));
        var hardcoded = timingGroup(panelLabels.hardcodedAssets || 'Hardcoded assets', 'hardcoded', hardcodedItem ? childTimingItems(hardcodedItem) : [], timingValue(hardcodedItem));
        var html = timingGroup(panelLabels.htmlCleanup || 'HTML cleanup', 'html', flattenTimingItems(htmlItems), timingValue(htmlCleanupItem));
        css.open = true;
        return {
            total: formatMilliseconds(total),
            css: css,
            javascript: javascript,
            htmlValue: timingValue(optimizeCommon),
            groups: [preparation, loaders, css, javascript, hardcoded, html]
        };
    }
    function timingGroup(title, type, items, value) {
        return {title: title, type: type, items: items.map(timingLine), value: value || 'N/A', open: false};
    }
    function timingLine(item) {
        var textValue = getDirectText(item);
        var separator = textValue.lastIndexOf(':');
        return {
            label: separator === -1 ? textValue : textValue.slice(0, separator).trim(),
            value: separator === -1 ? '' : compactTimingValue(textValue.slice(separator + 1).trim())
        };
    }
    function compactTimingValue(value) {
        return String(value || '').replace(/\s*\([0-9.]+s\)\s*$/i, '').trim();
    }
    function createMetric(label, value, type) {
        var metric = document.createElement('div');
        metric.className = 'wpacu-debug-metric wpacu-debug-metric--' + type;
        var icon = document.createElement('span');
        icon.className = 'wpacu-debug-metric-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = reportIcon(type);
        var copy = document.createElement('span');
        var labelNode = document.createElement('small');
        labelNode.textContent = label;
        var valueNode = document.createElement('strong');
        valueNode.textContent = value || 'N/A';
        if (isStatusTiming(valueNode.textContent)) { valueNode.classList.add('is-status'); }
        copy.appendChild(labelNode);
        copy.appendChild(valueNode);
        metric.appendChild(icon);
        metric.appendChild(copy);
        return metric;
    }
    function createTimingDisclosure(group) {
        var hasExpandableTimings = group.items.some(function (item) { return !isSecondaryTiming(item.value); });
        if (!hasExpandableTimings) {
            var staticRow = document.createElement('div');
            staticRow.className = 'wpacu-debug-timing-disclosure is-static wpacu-debug-timing-disclosure--' + group.type;
            var staticIcon = document.createElement('span');
            staticIcon.className = 'wpacu-debug-timing-group-icon';
            staticIcon.innerHTML = reportIcon(group.type);
            var staticTitle = document.createElement('span');
            staticTitle.className = 'wpacu-debug-timing-group-title';
            staticTitle.textContent = group.title;
            var staticValue = document.createElement('span');
            staticValue.className = 'wpacu-debug-timing-group-value';
            staticValue.textContent = group.value;
            if (isStatusTiming(group.value)) { staticValue.classList.add('is-status'); }
            staticRow.appendChild(staticIcon);
            staticRow.appendChild(staticTitle);
            staticRow.appendChild(staticValue);
            return staticRow;
        }
        var disclosure = document.createElement('details');
        disclosure.className = 'wpacu-debug-timing-disclosure wpacu-debug-timing-disclosure--' + group.type;
        disclosure.open = !!group.open;
        var summary = document.createElement('summary');
        var icon = document.createElement('span');
        icon.className = 'wpacu-debug-timing-group-icon';
        icon.innerHTML = reportIcon(group.type);
        var title = document.createElement('span');
        title.className = 'wpacu-debug-timing-group-title';
        title.textContent = group.title;
        var value = document.createElement('span');
        value.className = 'wpacu-debug-timing-group-value';
        value.textContent = group.value;
        if (isStatusTiming(group.value)) { value.classList.add('is-status'); }
        summary.appendChild(icon); summary.appendChild(title); summary.appendChild(value);
        disclosure.appendChild(summary);
        var list = document.createElement('div');
        list.className = 'wpacu-debug-timing-group-list';
        group.items.forEach(function (item) {
            var row = document.createElement('div');
            row.className = 'wpacu-debug-timing-row';
            if (isSecondaryTiming(item.value)) { row.classList.add('is-secondary'); }
            var rowLabel = document.createElement('span'); rowLabel.textContent = item.label;
            var rowValue = document.createElement('span'); rowValue.textContent = item.value;
            if (isStatusTiming(item.value)) { rowValue.classList.add('is-status'); }
            row.appendChild(rowLabel); row.appendChild(rowValue); list.appendChild(row);
        });
        disclosure.appendChild(list);
        return disclosure;
    }
    function reportIcon(type) {
        var paths = {
            total: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/></svg>',
            css: '<svg viewBox="0 0 24 24"><path d="M7 3h10l4 4v14H7z"/><path d="M17 3v5h4M10 13h8M10 17h6"/></svg>',
            js: '<svg viewBox="0 0 24 24"><path d="m9 7-5 5 5 5M15 7l5 5-5 5M13 4l-2 16"/></svg>',
            html: '<svg viewBox="0 0 24 24"><path d="M7 3h10l4 4v14H7z"/><path d="M17 3v5h4M9 14h10M9 18h7"/></svg>',
            prepare: '<svg viewBox="0 0 24 24"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg>',
            loader: '<svg viewBox="0 0 24 24"><path d="m9 7-5 5 5 5M15 7l5 5-5 5"/></svg>',
            hardcoded: '<svg viewBox="0 0 24 24"><path d="M8 3v5M16 3v5M6 8h12v4a6 6 0 0 1-12 0zM12 18v3"/></svg>'
        };
        return paths[type] || paths.total;
    }
    function getDirectText(item) {
        if (!item) { return ''; }
        var clone = item.cloneNode(true);
        Array.prototype.forEach.call(clone.querySelectorAll('ul, ol, script, style'), function (child) { child.remove(); });
        return String(clone.textContent || '').replace(/\s+/g, ' ').trim();
    }
    function findTimingItem(items, prefix) {
        for (var i = 0; i < items.length; i += 1) {
            if (getDirectText(items[i]).indexOf(prefix) === 0) { return items[i]; }
        }
        return null;
    }
    function isListItem(node) { return !!(node && node.tagName && node.tagName.toLowerCase() === 'li'); }
    function childTimingItems(item) {
        var list = item ? item.querySelector(':scope > ul') : null;
        return list ? flattenTimingItems(Array.prototype.slice.call(list.children).filter(isListItem)) : [];
    }
    function flattenTimingItems(items) {
        var output = [];
        items.forEach(function (item) {
            output.push(item);
            var childList = item.querySelector(':scope > ul');
            if (childList) { output = output.concat(flattenTimingItems(Array.prototype.slice.call(childList.children).filter(isListItem))); }
        });
        return output;
    }
    function timingValue(item) {
        var line = timingLine(item || document.createElement('li'));
        return line.value || 'N/A';
    }
    function sumTimingItems(items) {
        var total = 0, found = false;
        items.forEach(function (item) {
            var match = timingValue(item).match(/([0-9]+(?:\.[0-9]+)?)ms/i);
            if (match) { total += parseFloat(match[1]); found = true; }
        });
        return found ? formatMilliseconds(total) : 'N/A';
    }
    function formatMilliseconds(number) {
        return (Math.round(number * 100) / 100).toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1') + 'ms';
    }
    function isSecondaryTiming(value) {
        if (isStatusTiming(value)) { return true; }
        var match = String(value || '').match(/^([0-9]+(?:\.[0-9]+)?)ms/i);
        return !!(match && parseFloat(match[1]) === 0);
    }
    function isStatusTiming(value) {
        return /^(Skipped|N\/A)$/i.test(String(value || '').trim());
    }
    function createGroupIcon(group) {
        var icons = {
            css: '<path d="M7 3h10l4 4v14H7z"/><path d="M17 3v5h5M10 13h8M10 17h6"/>',
            javascript: '<path d="m9 7-5 5 5 5M15 7l5 5-5 5M13 4l-2 16"/>',
            other: '<path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M7 14v6"/>'
        };
        var icon = document.createElement('span');
        icon.className = 'wpacu-debug-group-icon wpacu-debug-group-icon--' + group;
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = '<svg viewBox="0 0 24 24" focusable="false">' + (icons[group] || icons.other) + '</svg>';
        return icon;
    }
    function highlightMatches(element, query) {
        if (!element) { return; }
        if (typeof element._wpacuOriginalText === 'undefined') {
            element._wpacuOriginalText = String(element.textContent || '');
        }

        var original = element._wpacuOriginalText;
        while (element.firstChild) { element.removeChild(element.firstChild); }
        if (!query) {
            element.appendChild(document.createTextNode(original));
            return;
        }

        var lower = original.toLowerCase();
        var offset = 0;
        var matchAt;
        while ((matchAt = lower.indexOf(query, offset)) !== -1) {
            if (matchAt > offset) {
                element.appendChild(document.createTextNode(original.slice(offset, matchAt)));
            }
            var mark = document.createElement('mark');
            mark.className = 'wpacu-debug-search-match';
            mark.textContent = original.slice(matchAt, matchAt + query.length);
            element.appendChild(mark);
            offset = matchAt + query.length;
        }
        if (offset < original.length) {
            element.appendChild(document.createTextNode(original.slice(offset)));
        }
    }
    function findLayoutTable(scope) {
        var tables = scope.querySelectorAll('table');
        for (var i=0; i<tables.length; i+=1) {
            var body = tables[i].tBodies && tables[i].tBodies.length ? tables[i].tBodies[0] : null;
            var row = body && body.rows.length ? body.rows[0] : null;
            if (row && row.cells.length >= 2) { return tables[i]; }
        }
        return tables.length ? tables[0] : null;
    }
    function cssEscape(value) {
        if (window.CSS && window.CSS.escape) { return window.CSS.escape(value); }
        return String(value).replace(/(["\\\[\]])/g, '\\$1');
    }

    function createPreviewAppliedAlert() {
        if (document.querySelector('[data-wpacu-debug-preview-alert]')) { return; }
        if (cssUrl && !document.querySelector('link[data-wpacu-debug-page-css]')) {
            var pageCss = document.createElement('link');
            pageCss.rel = 'stylesheet';
            pageCss.href = cssUrl;
            pageCss.setAttribute('data-wpacu-debug-page-css', '');
            document.head.appendChild(pageCss);
        }

        var alert = document.createElement('div');
        alert.className = 'wpacu-debug-preview-alert';
        alert.setAttribute('data-wpacu-debug-preview-alert', '');
        alert.setAttribute('role', 'status');
        alert.innerHTML = '<span class="wpacu-debug-preview-alert__icon" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="m4 8 2.5 2.5L12 5"/></svg></span><span></span>';
        var copy = alert.lastChild;
        copy.appendChild(document.createTextNode((labels.previewApplied || 'Preview updated. Your temporary debugging choices are now active for this request.') + '\u00a0\u00a0'));
        var controlsLink = document.createElement('a');
        controlsLink.href = '#' + hostId;
        controlsLink.textContent = labels.viewDebugControls || 'View debugging controls';
        controlsLink.addEventListener('click', function (event) {
            event.preventDefault();
            host.style.scrollMarginTop = (alert.getBoundingClientRect().height + 16) + 'px';
            host.scrollIntoView({behavior: 'smooth', block: 'start'});
        });
        copy.appendChild(controlsLink);
        var topSeparator = document.createElement('span');
        topSeparator.className = 'wpacu-debug-preview-alert__separator';
        topSeparator.setAttribute('aria-hidden', 'true');
        topSeparator.textContent = '|';
        topSeparator.hidden = true;
        var topLink = document.createElement('a');
        topLink.href = '#';
        topLink.textContent = labels.goToPageTop || 'Go to page top';
        topLink.hidden = true;
        topLink.addEventListener('click', function (event) {
            event.preventDefault();
            window.scrollTo({top: 0, behavior: 'smooth'});
        });
        copy.appendChild(document.createTextNode(' '));
        copy.appendChild(topSeparator);
        copy.appendChild(document.createTextNode(' '));
        copy.appendChild(topLink);
        var updateTopLink = function () {
            var showTopLink = window.pageYOffset > 240;
            topSeparator.hidden = !showTopLink;
            topLink.hidden = !showTopLink;
        };
        window.addEventListener('scroll', updateTopLink, {passive: true});
        updateTopLink();

        var adminBar = document.getElementById('wpadminbar');
        var syncAlertWithAdminBar = function () {
            var adminBarZIndex = adminBar ? parseInt(window.getComputedStyle(adminBar).zIndex, 10) : NaN;
            alert.style.setProperty('z-index', String(isFinite(adminBarZIndex) ? Math.max(1, adminBarZIndex - 1) : 99998), 'important');
            alert.style.top = adminBar ? Math.max(0, adminBar.getBoundingClientRect().height) + 'px' : '0px';
        };
        syncAlertWithAdminBar();
        window.addEventListener('resize', syncAlertWithAdminBar);
        if (adminBar && window.ResizeObserver) { new ResizeObserver(syncAlertWithAdminBar).observe(adminBar); }
        if (adminBar && window.MutationObserver) {
            new MutationObserver(syncAlertWithAdminBar).observe(adminBar, {attributes: true, attributeFilter: ['class', 'style']});
        }
        if (adminBar && adminBar.parentNode) {
            adminBar.parentNode.insertBefore(alert, adminBar.nextSibling);
        } else {
            document.body.insertBefore(alert, document.body.firstChild);
        }
        var alertSpacer = document.createElement('div');
        alertSpacer.className = 'wpacu-debug-preview-alert-spacer';
        alert.parentNode.insertBefore(alertSpacer, alert.nextSibling);
        var sizeAlertSpacer = function () {
            alertSpacer.style.height = alert.getBoundingClientRect().height + 'px';
        };
        window.requestAnimationFrame(sizeAlertSpacer);
        if (window.ResizeObserver) { new ResizeObserver(sizeAlertSpacer).observe(alert); }
    }

    if (config.previewApplied) {
        if (window.history && 'scrollRestoration' in window.history) {
            window.history.scrollRestoration = 'manual';
        }
        var scrollToPageTop = function () { window.scrollTo(0, 0); };
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(scrollToPageTop);
        });
        window.setTimeout(scrollToPageTop, 80);
        window.addEventListener('pageshow', scrollToPageTop, {once: true});
    }
}());
