(function ($) {
    'use strict';

    var config = window.wpacuAlternativeAssetsData || {};
    var registry = Array.isArray(config.registry) ? config.registry : [];
    var strings = config.strings || {};
    var $root = $('.wpacu-alt-assets');
    var sourceEditor = null;
    var beautifyUndo = null;
    var appendUndo = null;
    var sourceLoading = false;
    var sourceRequest = null;
    var sourceRequestId = 0;
    var importedOriginalAssets = {};

    if (!$root.length) {
        return;
    }

    var $globalStatus = $('#wpacu-alt-global-status');
    var $globalFeedback = $('#wpacu-alt-global-feedback');
    var savedGlobalEnabled = $globalStatus.prop('checked');
    $root.toggleClass('is-globally-paused', !savedGlobalEnabled);
    $globalStatus.on('change', function () {
        var requestedStatus = this.checked ? 'on' : 'off';
        var $savingSpinner = $('#wpacu-main-loading-spinner');
        $globalStatus.prop('disabled', true);
        $root.attr('aria-busy', 'true');
        if ($savingSpinner.length) {
            $('#wpacu-main-loading-spinner-text').text($savingSpinner.find('[data-wpacu-updating-text="1"]').text().trim());
            $savingSpinner.removeClass('wpacu_hide');
        }
        function restoreGlobalStatus(message) {
            $globalStatus.prop('checked', savedGlobalEnabled);
            $globalFeedback.text(message || strings.globalStatusFailed);
        }
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            timeout: 20000,
            data: { action: config.globalStatusAction, nonce: config.globalStatusNonce, status: requestedStatus }
        }).done(function (response) {
            if (!response || !response.success || !response.data || response.data.status !== requestedStatus) {
                restoreGlobalStatus(response && response.data && response.data.message);
                return;
            }
            savedGlobalEnabled = response.data.status === 'on';
            $globalStatus.prop('checked', savedGlobalEnabled);
            $root.toggleClass('is-globally-paused', !savedGlobalEnabled);
            $globalFeedback.text(response.data.message);
        }).fail(function (xhr) {
            restoreGlobalStatus(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message);
        }).always(function () {
            $globalStatus.prop('disabled', false);
            $root.attr('aria-busy', 'false');
            $savingSpinner.addClass('wpacu_hide');
        });
    });

    var explanationSelector = '.wpacu-alt-assets__savings, .wpacu-alt-assets__output-help';
    $globalStatus.attr('data-wpacu-master-ready', '1');

    // Native details supplies click/keyboard behavior; dismiss explanations like popovers.
    $(document).on('click', function (event) {
        $(explanationSelector).filter('[open]').not($(event.target).closest(explanationSelector)).removeAttr('open');
    }).on('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        var $explanations = $(explanationSelector).filter('[open]');
        if ($explanations.length) {
            $explanations.removeAttr('open').find('summary').first().trigger('focus');
        }
    });

    $('.wpacu-alt-assets__form input[name="enabled"]').on('change', function () {
        $(this).closest('form').toggleClass('is-inactive', !this.checked);
    }).each(function () {
        $(this).closest('form').toggleClass('is-inactive', !this.checked);
    });

    var $guide = $('#wpacu-alt-assets-guide').appendTo('body');
    var guideOpener = null;
    var guideHadScrollLock = false;
    var guideBackground = document.getElementById('wpwrap');
    var guideBackgroundInert = null;

    function closeGuide() {
        $guide.prop('hidden', true);
        $('[data-wpacu-alt-guide-open]').attr('aria-expanded', 'false');
        if (!guideHadScrollLock) { $('body').removeClass('wpacu-singular-modal-open'); }
        if (guideBackground) {
            if (guideBackgroundInert === null) { guideBackground.removeAttribute('inert'); }
            else { guideBackground.setAttribute('inert', guideBackgroundInert); }
        }
        if (guideOpener) { $(guideOpener).focus(); }
    }

    $('[data-wpacu-alt-guide-open]').on('click', function () {
        if (!$guide.length || !$guide.prop('hidden')) { return; }
        guideOpener = this;
        guideHadScrollLock = $('body').hasClass('wpacu-singular-modal-open');
        if (guideBackground) {
            guideBackgroundInert = guideBackground.getAttribute('inert');
            guideBackground.setAttribute('inert', '');
        }
        $(this).attr('aria-expanded', 'true');
        $('body').addClass('wpacu-singular-modal-open');
        $guide.prop('hidden', false).find('[role="dialog"]').focus();
    });

    $guide.on('click', '[data-wpacu-alt-guide-close]', closeGuide).on('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            closeGuide();
        } else if (event.key === 'Tab') {
            var $focusable = $guide.find('button, summary, a[href], [tabindex]')
                .filter(':visible').not(':disabled, [tabindex="-1"]');
            var first = $focusable[0];
            var last = $focusable[$focusable.length - 1];
            if (event.shiftKey && (document.activeElement === first || document.activeElement === $guide.find('[role="dialog"]')[0])) {
                event.preventDefault(); $(last).focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); $(first).focus();
            }
        }
    });

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function formatBytes(bytes) {
        bytes = parseInt(bytes, 10) || 0;
        if (bytes <= 0) {
            return '';
        }
        if (bytes >= 1024 * 1024) {
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }
        return (bytes / 1024).toFixed(1) + ' KB';
    }

    function currentFormType() {
        var $select = $('#wpacu-alt-asset-type');
        var value = $select.length ? $select.val() : $root.attr('data-wpacu-current-asset-type');
        return value === 'js' ? 'js' : 'css';
    }

    function sourceEditorMode() {
        return currentFormType() === 'js' ? 'javascript' : 'text/css';
    }

    function sourceLintOptions() {
        if (currentFormType() !== 'css' || !window.wpacuAlternativeAssetsCssLint) { return false; }
        return {
            delay: 500,
            getAnnotations: function (text) {
                return window.wpacuAlternativeAssetsCssLint(text, config.cssLintMessages);
            }
        };
    }

    $(function () {
        var $source = $('#wpacu-alt-assets-source');
        if (!$source.length || !config.codeEditor || !window.wp || !wp.codeEditor) {
            return;
        }

        var settings = $.extend(true, {}, config.codeEditor, {
            codemirror: {mode: sourceEditorMode(), lint: sourceLintOptions(), gutters: currentFormType() === 'css' ? ['CodeMirror-lint-markers'] : []}
        });
        sourceEditor = wp.codeEditor.initialize($source[0], settings).codemirror;

        // The hidden textarea cannot receive browser validation focus. PHP validates empty source.
        $source.prop('required', false);
        sourceEditor.on('change', function () {
            sourceEditor.save();
            updateLoadSourceButton();
        });
        $source.closest('form').on('submit', function () {
            sourceEditor.save();
        });
        $('label[for="wpacu-alt-assets-source"]').on('click', function (event) {
            event.preventDefault();
            sourceEditor.focus();
        });
        updateLoadSourceButton();
    });

    function findRegistryEntry(type, handle) {
        handle = String(handle || '');
        for (var i = 0; i < registry.length; i++) {
            var entry = registry[i] || {};
            if (entry.type === type && String(entry.handle || '') === handle) {
                return entry;
            }
        }
        return null;
    }

    function renderSelectedInfo(entry) {
        updateLoadSourceButton();
        var $info = $('#wpacu-alt-assets-selected-info');
        if (!$info.length) {
            return;
        }

        if (!entry) {
            // An existing rule keeps its printed identity until the exact handle is detected.
            if ($('#wpacu-alt-asset-handle').attr('type') === 'hidden') {
                return;
            }
            $info.prop('hidden', true).empty();
            return;
        }

        var size = formatBytes(entry.size);
        var owner = entry.owner_name || strings.unknownSource || 'Unknown source';
        var meta = owner + (size ? ' · ' + size : '');
        var fileLink = '';
        if (entry.src) {
            var url = document.createElement('a');
            url.href = String(entry.src);
            if (url.protocol === 'http:' || url.protocol === 'https:') {
                var linkLabel = strings.openOriginalFile || 'Open original file in a new tab';
                fileLink = $('<a>', {
                    href: url.href,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    'class': 'wpacu-alt-assets__file-link',
                    'aria-label': linkLabel,
                    title: linkLabel
                }).append($('<span>', {
                    'class': 'dashicons dashicons-external',
                    'aria-hidden': 'true'
                })).prop('outerHTML');
            }
        }
        var html = '<strong>' + escapeHtml(entry.handle) + '</strong> — ' + escapeHtml(meta) +
            fileLink + '<code>' + escapeHtml(entry.src || '') + '</code>';

        $info.html(html).prop('hidden', false);
    }

    function selectEntry(entry) {
        $('#wpacu-alt-asset-handle').val(entry.handle || '').trigger('change');
        $('#wpacu-alt-assets-search-results').prop('hidden', true).empty();
        renderSelectedInfo(entry);
        var ruleId = parseInt($('#wpacu-alt-assets-source').closest('form').find('input[name="rule_id"]').val(), 10) || 0;
        if (ruleId === 0 && !currentSource().trim()) {
            loadOriginalSource(true);
        }
    }

    function renderSearchResults() {
        var $input = $('#wpacu-alt-asset-handle');
        var $results = $('#wpacu-alt-assets-search-results');
        if (!$input.length || !$results.length) {
            return;
        }

        var query = $.trim($input.val()).toLowerCase();
        var type = currentFormType();

        if (!query) {
            $results.prop('hidden', true).empty();
            renderSelectedInfo(null);
            return;
        }

        var matches = registry.filter(function (entry) {
            if (!entry || entry.type !== type) {
                return false;
            }
            var haystack = [entry.handle, entry.src, entry.owner_name].join(' ').toLowerCase();
            return haystack.indexOf(query) !== -1;
        }).slice(0, 8);

        var html = '';

        matches.forEach(function (entry, index) {
            var size = formatBytes(entry.size);
            var meta = (entry.owner_name || '') + (size ? ' · ' + size : '');
            html += '<button type="button" class="wpacu-alt-assets__search-result" data-registry-index="' + index + '">' +
                '<strong>' + escapeHtml(entry.handle || '') + '</strong>' +
                '<small>' + escapeHtml(meta) + (entry.src ? ' · ' + escapeHtml(entry.src) : '') + '</small>' +
                '</button>';
        });

        if (!matches.length) {
            html = '<button type="button" class="wpacu-alt-assets__use-handle">' +
                '<strong>' + escapeHtml(strings.useHandlePrefix || 'Use this handle:') + ' ' + escapeHtml($input.val()) + '</strong>' +
                '<small>' + escapeHtml(strings.ruleCanStillApply || 'The rule will apply whenever this WordPress handle is enqueued.') + '</small>' +
                '</button>';
        }

        $results.data('matches', matches).html(html).prop('hidden', false);
    }

    $('#wpacu-alt-asset-handle:not([type="hidden"])').on('input focus', renderSearchResults).on('input change', function () {
        cancelSourceRequest();
        renderSelectedInfo(findRegistryEntry(currentFormType(), $(this).val()));
    });

    $('#wpacu-alt-assets-search-results').on('click', '.wpacu-alt-assets__search-result', function () {
        var matches = $('#wpacu-alt-assets-search-results').data('matches') || [];
        var entry = matches[parseInt($(this).attr('data-registry-index'), 10)];
        if (entry) {
            selectEntry(entry);
        }
    }).on('click', '.wpacu-alt-assets__use-handle', function () {
        $('#wpacu-alt-assets-search-results').prop('hidden', true).empty();
        renderSelectedInfo(null);
        $('#wpacu-alt-asset-handle').focus();
    });

    $(document).on('click', function (event) {
        if (!$(event.target).closest('.wpacu-alt-assets__field--handle').length) {
            $('#wpacu-alt-assets-search-results').prop('hidden', true);
        }
    });

    function updateTypeDependentUi() {
        cancelSourceRequest();
        var type = currentFormType();
        var $sourceHelp = $('.wpacu-alt-assets__source-help');
        var $sourceLabel = $('label[for="wpacu-alt-assets-source"]');
        var $warning = $('.wpacu-alt-assets__warning');

        $root.attr('data-wpacu-current-asset-type', type);
        $sourceLabel.text(type === 'js' ? (strings.alternativeJs || 'Alternative JavaScript') : (strings.alternativeCss || 'Alternative CSS'));

        if (sourceEditor) {
            sourceEditor.setOption('mode', sourceEditorMode());
            var lintOptions = sourceLintOptions();
            var gutters = sourceEditor.getOption('gutters').filter(function (gutter) { return gutter !== 'CodeMirror-lint-markers'; });
            if (lintOptions) { gutters.push('CodeMirror-lint-markers'); }
            sourceEditor.setOption('gutters', gutters);
            sourceEditor.setOption('lint', lintOptions);
        }

        if ($sourceHelp.length) {
            $sourceHelp.text(type === 'js' ? $sourceHelp.attr('data-js-text') : $sourceHelp.attr('data-css-text'));
        }

        if ($warning.length) {
            $warning.prop('hidden', type !== 'js');
        }

        if ($('#wpacu-alt-asset-handle').is(':focus')) {
            renderSearchResults();
        } else {
            $('#wpacu-alt-assets-search-results').prop('hidden', true);
        }
        renderSelectedInfo(findRegistryEntry(type, $('#wpacu-alt-asset-handle').val()));
    }

    $('#wpacu-alt-asset-type').on('change', updateTypeDependentUi);

    function currentSource() {
        return sourceEditor ? sourceEditor.getValue() : String($('#wpacu-alt-assets-source').val() || '');
    }

    function sourceBeautifier() {
        return currentFormType() === 'js' ? window.js_beautify : window.css_beautify;
    }

    function canUndoBeautify() {
        return !!beautifyUndo && currentSource() === beautifyUndo.formatted
            && sourceAssetKey(currentFormType(), $('#wpacu-alt-asset-handle').val()) === beautifyUndo.assetKey;
    }

    function canUndoAppend() {
        return !!appendUndo && currentSource() === appendUndo.appended
            && sourceAssetKey(currentFormType(), $('#wpacu-alt-asset-handle').val()) === appendUndo.assetKey;
    }

    function replaceEditorSource(source, origin) {
        if (sourceEditor) {
            var scroll = sourceEditor.getScrollInfo();
            // A single replacement keeps previous edits in the editor's undo history.
            sourceEditor.replaceRange(source, {line: 0, ch: 0}, {
                line: sourceEditor.lastLine(), ch: sourceEditor.getLine(sourceEditor.lastLine()).length
            }, origin);
            sourceEditor.save();
            sourceEditor.focus();
            sourceEditor.scrollTo(scroll.left, scroll.top);
        } else {
            $('#wpacu-alt-assets-source').val(source).trigger('change').focus();
        }
    }

    $('#wpacu-alt-assets-beautify').on('click', function () {
        var source = currentSource();
        var beautifier = sourceBeautifier();
        if (sourceLoading || !source.trim() || typeof beautifier !== 'function') { return; }

        var formatted;
        try {
            formatted = beautifier(source, {
                indent_size: 4,
                preserve_newlines: true,
                max_preserve_newlines: 2,
                end_with_newline: true,
                unescape_strings: false
            });
            if (typeof formatted !== 'string' || !formatted.trim()) { throw new Error('Empty formatted source'); }
        } catch (error) {
            showSourceMessage(strings.beautifyFailed || 'The code could not be formatted. Your code has been kept unchanged.', true);
            return;
        }

        if (formatted === source) {
            showSourceMessage(strings.beautifyUnchanged || 'The code is already formatted.', false);
            return;
        }

        replaceEditorSource(formatted, 'beautify');
        beautifyUndo = {
            source: source,
            formatted: currentSource(),
            assetKey: sourceAssetKey(currentFormType(), $('#wpacu-alt-asset-handle').val())
        };
        updateLoadSourceButton();
        showSourceMessage(strings.beautified || 'Code formatted. Review it, then save your alternative to keep the changes.', false);
    });

    $('#wpacu-alt-assets-undo-beautify').on('click', function () {
        // Also check the current text in case it changed without an input event.
        updateLoadSourceButton();
        if (sourceLoading || !canUndoBeautify()) { return; }
        var source = beautifyUndo.source;
        beautifyUndo = null;
        replaceEditorSource(source, 'undoBeautify');
        updateLoadSourceButton();
        showSourceMessage(strings.beautifyUndone || 'Beautify undone. The code before formatting has been restored.', false);
    });

    function sourceAssetKey(type, handle) {
        return JSON.stringify([type, String(handle || '')]);
    }

    $('#wpacu-alt-assets-undo-append').on('click', function () {
        updateLoadSourceButton();
        if (sourceLoading || !canUndoAppend()) { return; }
        var previous = appendUndo;
        appendUndo = null;
        if (previous.originalImported) {
            importedOriginalAssets[previous.assetKey] = true;
        } else {
            delete importedOriginalAssets[previous.assetKey];
        }
        replaceEditorSource(previous.source, 'undoAppend');
        updateLoadSourceButton();
        showSourceMessage(strings.appendUndone || 'Append undone. The code before adding the original source has been restored.', false);
    });

    function updateLoadSourceButton() {
        var entry = findRegistryEntry(currentFormType(), $('#wpacu-alt-asset-handle').val());
        var available = !!(entry && entry.src);
        var hasCode = !!currentSource().trim();
        if (beautifyUndo && sourceAssetKey(currentFormType(), $('#wpacu-alt-asset-handle').val()) !== beautifyUndo.assetKey) {
            beautifyUndo = null;
        }
        if (appendUndo && sourceAssetKey(currentFormType(), $('#wpacu-alt-asset-handle').val()) !== appendUndo.assetKey) {
            appendUndo = null;
        }
        var canUndoOriginalAppend = canUndoAppend();
        $('#wpacu-alt-assets-undo-append')
            .prop('hidden', !canUndoOriginalAppend)
            .prop('disabled', sourceLoading || !canUndoOriginalAppend);
        // Keep the snapshot while editing so returning to the formatted text re-enables Undo.
        var canUndo = canUndoBeautify();
        $('#wpacu-alt-assets-undo-beautify')
            .prop('hidden', !canUndo)
            .prop('disabled', sourceLoading || !canUndo);
        $('#wpacu-alt-assets-beautify')
            .prop('disabled', sourceLoading || !hasCode || typeof sourceBeautifier() !== 'function')
            .text(currentFormType() === 'js' ? (strings.beautifyJs || 'Beautify JavaScript') : (strings.beautifyCss || 'Beautify CSS'));
        if (!hasCode) { importedOriginalAssets = {}; }
        var originalCodeImported = !!importedOriginalAssets[sourceAssetKey(currentFormType(), $('#wpacu-alt-asset-handle').val())];
        var label = hasCode ? (strings.appendOriginal || 'Append original code') : (strings.loadOriginal || 'Copy original code');
        var tooltip = hasCode
            ? (strings.appendOriginalHelp || 'Add the original file’s code below your existing code. Nothing in the editor is replaced.')
            : (strings.loadOriginalHelp || 'Copy the original file’s code into this editor, then remove what you don’t need. The original file stays unchanged.');
        $('#wpacu-alt-assets-load-source')
            .prop('hidden', !available || (originalCodeImported && hasCode && !sourceLoading))
            .prop('disabled', sourceLoading || !available)
            .attr('title', available ? tooltip : (strings.originalUnavailable || 'Choose a detected handle, or use Find more assets to locate its original file.'))
            .text(sourceLoading ? (strings.loadingOriginal || 'Loading original code…') : label);
        $('#wpacu-alt-assets-source-spinner').toggleClass('is-active', sourceLoading);
        $('#wpacu-alt-assets-source').prop('disabled', sourceLoading).attr('aria-busy', sourceLoading ? 'true' : 'false');
        if (sourceEditor) {
            var readOnly = sourceLoading ? 'nocursor' : false;
            if (sourceEditor.getOption('readOnly') !== readOnly) {
                sourceEditor.setOption('readOnly', readOnly);
            }
            $(sourceEditor.getWrapperElement()).attr({
                'aria-busy': sourceLoading ? 'true' : 'false',
                'aria-disabled': sourceLoading ? 'true' : 'false'
            });
        }
    }

    $('#wpacu-alt-assets-source').on('input change', updateLoadSourceButton);
    $('#wpacu-alt-assets-load-source').on('click', function () { loadOriginalSource(false); });

    function showSourceMessage(message, isError) {
        var $message = $('#wpacu-alt-assets-source-message');
        $message.find('.wpacu-alt-assets__source-message-text').text(message);
        $message.find('.dashicons').toggleClass('dashicons-warning', !!isError).toggleClass('dashicons-info', !isError);
        $message.toggleClass('is-error', !!isError).prop('hidden', false);
    }

    function cancelSourceRequest() {
        sourceRequestId++;
        if (sourceRequest) { sourceRequest.abort(); sourceRequest = null; }
        sourceLoading = false;
        updateLoadSourceButton();
        $('#wpacu-alt-assets-source-message').prop('hidden', true);
    }

    function loadOriginalSource(automatic) {
        var type = currentFormType();
        var handle = String($('#wpacu-alt-asset-handle').val() || '');
        var entry = findRegistryEntry(type, handle);
        if (sourceLoading || !entry || !entry.src) { return; }
        var requestId = ++sourceRequestId;
        var focusAfterLoad = false;
        showSourceMessage(strings.loadingOriginal || 'Loading original code…', false);
        sourceLoading = true;
        updateLoadSourceButton();
        sourceRequest = $.post(config.ajaxUrl, {action: config.sourceAction, nonce: config.sourceNonce, asset_type: type, handle: handle})
            .done(function (response) {
                if (requestId !== sourceRequestId) { return; }
                if (currentFormType() !== type || String($('#wpacu-alt-asset-handle').val() || '') !== handle) {
                    showSourceMessage(strings.originalChanged || 'The selected asset changed. Load the original content for the new selection.', false);
                    return;
                }
                if (!response || !response.success || !response.data || typeof response.data.source !== 'string') {
                    showSourceMessage(response && response.data && response.data.message ? response.data.message : (strings.originalFailed || 'The original content could not be loaded. Please try again.'), true);
                    return;
                }
                // Preserve any programmatic content changes made while the editor was locked.
                var existing = currentSource();
                if (automatic && existing.trim()) {
                    showSourceMessage(strings.originalKeptEdits || 'Your code was kept because you edited it while the original was loading. Use “Append original code” to add the original below it.', false);
                    return;
                }
                var separator = existing.trim() ? (type === 'js' ? '\n;\n' : '\n\n') : '';
                var assetKey = sourceAssetKey(type, handle);
                var originalImported = !!importedOriginalAssets[assetKey];
                importedOriginalAssets[assetKey] = true;
                if (sourceEditor) {
                    var lastLine = sourceEditor.lastLine();
                    sourceEditor.replaceRange(separator + response.data.source, {line: lastLine, ch: sourceEditor.getLine(lastLine).length});
                    sourceEditor.save();
                } else {
                    $('#wpacu-alt-assets-source').val(existing + separator + response.data.source).trigger('change');
                }
                appendUndo = existing.trim() ? {
                    source: existing,
                    appended: currentSource(),
                    assetKey: assetKey,
                    originalImported: originalImported
                } : null;
                focusAfterLoad = !automatic;
                showSourceMessage(existing.trim()
                    ? (strings.originalAppended || 'Original code appended below your existing code. Review it, then save your alternative.')
                    : (strings.originalLoaded || 'Original code copied into the editor. Remove what you don’t need, then save your alternative.'), false);
            })
            .fail(function (xhr) {
                if (requestId !== sourceRequestId) { return; }
                var response = xhr.responseJSON;
                showSourceMessage(response && response.data && response.data.message ? response.data.message : (strings.originalFailed || 'The original content could not be loaded. Please try again.'), true);
            })
            .always(function () {
                if (requestId !== sourceRequestId) { return; }
                sourceRequest = null;
                sourceLoading = false;
                updateLoadSourceButton();
                if (focusAfterLoad) {
                    if (sourceEditor) { sourceEditor.focus(); }
                    else { $('#wpacu-alt-assets-source').focus(); }
                }
            });
    }

    var targetHelp = strings.targetHelp || {
        everywhere: 'No value is required.',
        homepage: 'The WordPress front page only.',
        posts_page: 'The posts index selected in Settings → Reading.',
        post_type: 'Choose one or more detected post types.',
        post_ids: 'Choose individual entries by title or ID. Save the rule to keep your selection.',
        taxonomy: 'Choose one or more detected taxonomies.',
        taxonomy_term: 'Choose term archive pages from any of the available taxonomies. This does not select posts assigned to those terms.',
        author: 'Optional: enter author IDs separated by commas. Leave empty for all author archives.',
        search: 'All search result pages. No value is required.',
        date: 'All date archives. No value is required.',
        '404': 'All 404 pages. No value is required.',
        cpt_archive: 'Choose one or more available custom post type archives.',
        regex: 'Enter a URI string or an explicit RegEx such as #^/landing/#.'
    };

    $(function () {
        // OwnAssets defers Chosen; wait until deferred scripts have executed.
        if ($.fn.chosen) {
            $('.wpacu-alt-assets__condition-select').chosen({width: '100%'});
        }
        $('.wpacu-alt-assets__scope-field').prop('disabled', false).attr('aria-busy', 'false');
        updateTargetUi();
        $('.wpacu-alt-assets__scope-loading').prop('hidden', true);
    });

    function setFormActionsBusy($form, busy, message) {
        $form.attr('aria-busy', busy ? 'true' : 'false');
        var $actions = $form.find('.wpacu-alt-assets__form-actions');
        $actions.find(':submit').prop('disabled', busy);
        var $cancel = $actions.find('a');
        $cancel.toggleClass('disabled', busy);
        if (busy) {
            $cancel.attr({'aria-disabled': 'true', tabindex: '-1'});
        } else {
            $cancel.removeAttr('aria-disabled tabindex');
        }
        $actions.find('.wpacu-alt-assets__action-spinner')
            .prop('hidden', !busy).toggleClass('is-active', busy)
            .find('.screen-reader-text').text(busy ? message : '');
    }

    $('.wpacu-alt-assets__form').on('submit', function (event) {
        var $form = $(this);
        if ($form.attr('aria-busy') === 'true' || sourceLoading || $form.find('.wpacu-alt-assets__scope-field[aria-busy="true"]').length) {
            event.preventDefault();
            return;
        }
        if (!event.isDefaultPrevented()) {
            setFormActionsBusy($form, true, strings.saving || 'Saving…');
        }
    });

    $('.wpacu-alt-assets__form-actions a').on('click', function (event) {
        var $form = $(this).closest('form');
        if ($form.attr('aria-busy') === 'true') {
            event.preventDefault();
            return;
        }
        if (event.isDefaultPrevented() || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || (event.which && event.which !== 1)) {
            return;
        }
        setFormActionsBusy($form, true, strings.returningToList || 'Returning to the list…');
    });

    $(window).on('pageshow', function (event) {
        if (event.originalEvent && event.originalEvent.persisted) {
            $('.wpacu-alt-assets__form').each(function () {
                setFormActionsBusy($(this), false, '');
            });
        }
    });

    function updateTargetUi() {
        $('.wpacu-alt-assets__target').each(function () {
            var $row = $(this);
            var target = $row.attr('data-target');
            var selected = $row.find('input[type="checkbox"]').prop('checked');
            var required = ['post_type', 'post_ids', 'taxonomy', 'taxonomy_term', 'cpt_archive', 'regex', 'post_tax_term', 'archive_types', 'user_role', 'author'].indexOf(target) !== -1;
            $row.find('.wpacu-alt-assets__target-details').prop('hidden', !selected);
            $row.find('input[type="text"], textarea').prop('disabled', !selected).prop('required', selected && required);
            $row.find('input[type="hidden"]').prop('disabled', !selected);
            $row.find('.wpacu-alt-assets__target-choice input').prop('disabled', !selected);
            $row.find('.wpacu-alt-assets__condition-select').prop('disabled', !selected).trigger('chosen:updated');
            $row.find('.wpacu-alt-assets__target-help').text(targetHelp[target] || '');
        });
        $('.wpacu-alt-assets__target-group').each(function () {
            var count = $(this).find('input[name="targets[]"]:checked').length;
            var label = strings.noConditions || 'No conditions selected';
            if (count === 1) {
                label = strings.oneCondition || '1 condition selected';
            } else if (count > 1) {
                label = (strings.multipleConditions || '%d conditions selected').replace('%d', count);
            }
            $(this).find('.wpacu-alt-assets__scope-count').text(label);
        });
    }

    $('.wpacu-alt-assets__targets input[name="targets[]"]').on('change', function () {
        if (this.checked) {
            var $others = $('.wpacu-alt-assets__targets input[name="targets[]"]').not(this);
            if (this.value !== 'everywhere') {
                $others = $others.filter('[value="everywhere"]');
            }
            $others.prop('checked', false);
        }
        updateTargetUi();
        if (this.checked && entryPickers[this.value]) {
            entryPickers[this.value](this);
        }
    });

    var entryPickers = {};
    ['posts', 'terms', 'assigned-terms', 'authors', 'post-types', 'taxonomies', 'archives', 'roles', 'uris'].forEach(function (kind) {
        var target = {posts: 'post_ids', terms: 'taxonomy_term', 'assigned-terms': 'post_tax_term', authors: 'author', 'post-types': 'post_type', taxonomies: 'taxonomy', archives: 'archive_types', roles: 'user_role', uris: 'regex'}[kind];
        var isUriPicker = kind === 'uris';
        var isChoicePicker = ['post-types', 'taxonomies', 'archives', 'roles'].indexOf(kind) !== -1;
        var isTermPicker = kind === 'terms' || kind === 'assigned-terms';
        var searchKind = isTermPicker ? 'term' : (kind === 'authors' ? 'author' : 'post');
        var strings = $.extend({}, config.strings || {}, isUriPicker ? config.uriStrings || {} : (isChoicePicker ? config.choiceStrings || {} : (isTermPicker ? config.termsStrings || {} : (kind === 'authors' ? config.authorsStrings || {} : {}))));
        var $modal = $('#wpacu-alt-assets-' + kind + '-modal').appendTo('body');
        if (!$modal.length) { return; }
        var $select = $('#wpacu-alt-assets-' + kind + '-select');
        var $valueInput = $('#wpacu-alt-assets-target-' + target);
        var openerElement = null;
        var selectedValue = String($valueInput.val() || '');
        if (isTermPicker) { selectedValue = selectedValue.replace(/^tt:/, ''); }
        var selectedIds = selectedValue.split(isUriPicker ? /\r\n|\r|\n/ : ',').filter(function (id) { return id !== ''; });

        function refreshSummary() {
            var $summary = $('#wpacu-alt-assets-' + kind + '-summary').empty();
            if (!selectedIds.length) {
                $('<li>').text(strings.noEntries || 'No entries selected.').appendTo($summary);
            }
            if (isUriPicker) {
                selectedIds.forEach(function (rule) { $('<li>').text(rule).appendTo($summary); });
            }
            $select.find('option').each(function () {
                if (selectedIds.indexOf(this.value) !== -1) {
                    $('<li>').text($(this).text()).appendTo($summary);
                }
            });
            $('.wpacu-alt-assets__choose-' + kind).text(selectedIds.length ?
                (strings.editEntries || 'Edit selected entries') : (strings.chooseEntries || 'Choose entries'));
        }

        function openModal(opener) {
            if (!$modal.length || (!isUriPicker && !$.fn.select2)) {
                return;
            }
            openerElement = opener;
            $modal.prop('hidden', false);
            $('body').addClass('wpacu-singular-modal-open');
            $('.wpacu-alt-assets__choose-' + kind).attr('aria-expanded', 'true');
            if (isUriPicker) {
                $select.val(selectedIds.join('\n')).focus();
                return;
            }
            if (!$select.data('select2')) {
                $select.select2({
                    width: '100%',
                    dropdownParent: $modal.find('[role="dialog"]'),
                    minimumInputLength: isChoicePicker ? 0 : (kind === 'posts' ? 2 : 1),
                    placeholder: strings.searchEntries || 'Search posts, pages, products and other entries',
                    ajax: isChoicePicker ? null : {
                        url: config.ajaxUrl,
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {action: config[searchKind + 'SearchAction'], wpacu_security: config[searchKind + 'SearchNonce'],
                                keyword: params.term || '', page_number: params.page || 1};
                        },
                        processResults: function (response) { return response; }
                    },
                    language: {
                        inputTooShort: function () { return strings.searchMinimum || 'Type at least two characters to search.'; },
                        searching: function () { return strings.searchingEntries || 'Searching entries…'; },
                        noResults: function () { return strings.noMatchingEntries || 'No matching entries found.'; },
                        errorLoading: function () { return strings.entriesSearchFailed || 'The search could not be completed. Please try again.'; },
                        loadingMore: function () { return strings.loadingMoreEntries || 'Loading more entries…'; }
                    }
                });
            }
            $select.val(selectedIds).trigger('change');
            $modal.find('[role="dialog"]').focus();
            $select.select2('open');
            $modal.find('.select2-search__field').focus();
        }

        function closeModal(applySelection) {
            if (applySelection) {
                selectedIds = isUriPicker ? String($select.val() || '').split(/\r\n|\r|\n/).map(function (line) { return line.trim(); }).filter(Boolean) : ($select.val() || []).map(String);
                $valueInput.val((isTermPicker && selectedIds.length ? 'tt:' : '') + selectedIds.join(isUriPicker ? '\n' : ',')).trigger('change');
                refreshSummary();
            } else {
                $select.val(isUriPicker ? selectedIds.join('\n') : selectedIds).trigger('change');
            }
            if ($select.data('select2')) {
                $select.select2('close');
            }
            $modal.prop('hidden', true);
            $('body').removeClass('wpacu-singular-modal-open');
            $('.wpacu-alt-assets__choose-' + kind).attr('aria-expanded', 'false');
            if (openerElement) { $(openerElement).focus(); }
        }

        $('.wpacu-alt-assets__choose-' + kind).on('click', function () { openModal(this); });
        $modal.on('click', '[data-wpacu-alt-' + kind + '-cancel]', function () { closeModal(false); });
        $('#wpacu-alt-assets-' + kind + '-apply').on('click', function () { closeModal(true); });
        $modal.on('keydown', function (event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                event.preventDefault();
                event.stopPropagation();
                closeModal(false);
            } else if (event.key === 'Tab' || event.keyCode === 9) {
                var $focusable = $modal.find('button, input, select, textarea, [tabindex]')
                    .filter(':visible').not(':disabled, [tabindex="-1"]');
                var first = $focusable[0];
                var last = $focusable[$focusable.length - 1];
                if (event.shiftKey && (document.activeElement === first || document.activeElement === $modal.find('[role="dialog"]')[0])) {
                    event.preventDefault(); $(last).focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault(); $(first).focus();
                }
            }
        });
        refreshSummary();

        entryPickers[target] = openModal;
    });

    function showStatus(message, isError) {
        var $message = $('#wpacu-alt-assets-status-message');
        if (!$message.length) {
            return;
        }
        $message.text(message || '').toggleClass('is-error', !!isError).prop('hidden', false);
    }

    var $activeAssetsCount = $('#wpacu-alternative-assets-active-count');
    var $ruleRows = $('.wpacu-alt-assets__toggle').closest('.wpacu-alt-assets__rule');
    var otherActiveAssetsCount = parseInt($activeAssetsCount.attr('data-active-count'), 10);
    if (isNaN(otherActiveAssetsCount)) {
        otherActiveAssetsCount = $ruleRows.not('.is-inactive').length;
    }
    // The badge includes both asset types, but this list only shows one type.
    otherActiveAssetsCount -= $ruleRows.not('.is-inactive').length;

    $('.wpacu-alt-assets__toggle').on('change', function () {
        var $toggle = $(this);
        var $row = $toggle.closest('.wpacu-alt-assets__rule');
        var enabled = $toggle.is(':checked');
        var previous = !enabled;

        $toggle.prop('disabled', true);
        $row.addClass('is-saving');
        showStatus(strings.saving || 'Saving…', false);

        $.post(config.ajaxUrl, {
            action: config.toggleAction,
            nonce: config.toggleNonce,
            rule_id: $toggle.attr('data-rule-id'),
            enabled: enabled ? 1 : 0
        }).done(function (response) {
            if (!response || !response.success) {
                $toggle.prop('checked', previous);
                var responseMessage = response && response.data && response.data.message
                    ? response.data.message
                    : (strings.toggleFailed || 'The rule status could not be updated.');
                showStatus(responseMessage, true);
                return;
            }

            $row.toggleClass('is-inactive', !enabled);
            if (response.data && typeof response.data.active_count === 'number') {
                // Row classes represent accepted states; checkboxes can still be pending.
                // An older response must not replace the count with its stale snapshot.
                var activeCount = otherActiveAssetsCount + $ruleRows.not('.is-inactive').length;
                var countText = response.data.active_count === activeCount ? response.data.active_count_text :
                    String(activeCount).replace(/\B(?=(\d{3})+(?!\d))/g, $activeAssetsCount.attr('data-thousands-separator') || '');
                $activeAssetsCount.attr('data-active-count', activeCount)
                    .text(activeCount > 0 ? '(' + countText + ')' : '')
                    .prop('hidden', activeCount === 0);
            }
            showStatus(enabled ? strings.enabled : strings.disabled, false);
        }).fail(function (xhr) {
            $toggle.prop('checked', previous);
            var message = strings.toggleFailed || 'The rule status could not be updated.';
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                message = xhr.responseJSON.data.message;
            }
            showStatus(message, true);
        }).always(function () {
            $toggle.prop('disabled', false);
            $row.removeClass('is-saving');
        });
    });

    $('#wpacu-alt-assets-rule-filter').on('input', function () {
        var query = $.trim($(this).val()).toLowerCase();
        $('.wpacu-alt-assets__rule').each(function () {
            var haystack = String($(this).attr('data-search') || '').toLowerCase();
            $(this).toggle(!query || haystack.indexOf(query) !== -1);
        });
    });

    $('.wpacu-alt-assets__delete-form').on('submit', function (event) {
        if (!window.confirm($(this).attr('data-confirmation') || strings.deleteConfirmation || 'Permanently delete this alternative asset and its stored source code?')) {
            event.preventDefault();
        }
    });

    $('#wpacu-alt-assets-find-more').on('click', function () {
        var expanded = $(this).attr('aria-expanded') !== 'true';
        $(this).attr('aria-expanded', String(expanded));
        $('#wpacu-alt-assets-fetch-box').prop('hidden', !expanded);
    });

    var scanRunning = false;
    var stopScan = false;

    function scanText(template, first, second) {
        return template.replace('%1$s', String(first)).replace('%2$s', String(second));
    }

    $('#wpacu-alt-assets-fetch-stop').on('click', function () {
        stopScan = true;
        $(this).prop('disabled', true);
        $('#wpacu-alt-assets-fetch-message').text(strings.scanStopping || 'Stopping after the current page…');
    });

    $('#wpacu-alt-assets-fetch-button').on('click', function () {
        if (scanRunning) {
            return;
        }
        var $button = $(this);
        var $spinner = $('#wpacu-alt-assets-fetch-spinner');
        var $message = $('#wpacu-alt-assets-fetch-message');
        var $stop = $('#wpacu-alt-assets-fetch-stop');
        var $inputs = $('.wpacu-alt-assets__scan-page, #wpacu-alt-assets-custom-urls');
        var $results = $('#wpacu-alt-assets-fetch-results');
        var urls = [];
        var invalid = false;
        var candidates = $('.wpacu-alt-assets__scan-page:checked').map(function () { return this.value; }).get();
        candidates = candidates.concat(String($('#wpacu-alt-assets-custom-urls').val() || '').split(/\r?\n/));
        candidates.forEach(function (candidate) {
            var value = $.trim(candidate);
            if (!value) {
                return;
            }
            var parsed = document.createElement('a');
            parsed.href = value;
            if (!/^https?:\/\//i.test(value) || !parsed.hostname || parsed.username || parsed.password) {
                invalid = true;
                return;
            }
            parsed.hash = '';
            if (urls.indexOf(parsed.href) === -1) {
                urls.push(parsed.href);
            }
        });

        if (invalid || !urls.length || urls.length > 20) {
            $message.text(invalid ? (strings.invalidPageUrl || 'Enter complete http:// or https:// URLs, one per line.') :
                (urls.length > 20 ? (strings.tooManyPages || 'Scan up to 20 pages at a time.') :
                    (strings.selectPages || 'Select a suggested page or enter a custom URL.')));
            return;
        }

        scanRunning = true;
        stopScan = false;
        var completed = 0;
        var succeeded = 0;
        var failed = 0;
        $button.prop('disabled', true);
        $inputs.prop('disabled', true);
        $stop.prop('hidden', false).prop('disabled', false);
        $results.empty();
        $spinner.addClass('is-active');

        function nextPage() {
            if (stopScan || completed >= urls.length) {
                scanRunning = false;
                $button.prop('disabled', false);
                $inputs.prop('disabled', false);
                $stop.prop('hidden', true);
                $spinner.removeClass('is-active');
                var summary = stopScan ? (strings.scanStopped || 'Scan stopped: %1$s successful, %2$s failed.') :
                    (strings.scanComplete || 'Scan complete: %1$s successful, %2$s failed.');
                $message.text(scanText(summary, succeeded, failed));
                return;
            }

            var pageUrl = urls[completed];
            $message.text(scanText(strings.scanProgress || 'Scanning page %1$s of %2$s…', completed + 1, urls.length));
            var $result = $('<li>').text(pageUrl + ' — ' + (strings.fetching || 'Fetching assets…')).appendTo($results);

            function showFailure(message) {
                failed++;
                $result.addClass('is-error').text(pageUrl + ' — ' + (message || strings.fetchFailed || 'The page assets could not be fetched.'));
            }

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                timeout: 120000,
                data: {action: config.fetchAction, nonce: config.fetchNonce, page_url: pageUrl}
            }).done(function (response) {
                if (!response || !response.success || !response.data || !Array.isArray(response.data.registry)) {
                    showFailure(response && response.data && response.data.message);
                    return;
                }
                succeeded++;
                registry = response.data.registry;
                $result.text(pageUrl + ' — CSS: ' + (parseInt(response.data.styles, 10) || 0) +
                    ' · JS: ' + (parseInt(response.data.scripts, 10) || 0));
                renderSelectedInfo(findRegistryEntry(currentFormType(), $('#wpacu-alt-asset-handle').val()));
                if ($('#wpacu-alt-asset-handle').is(':focus')) {
                    renderSearchResults();
                }
            }).fail(function (xhr) {
                showFailure(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message);
            }).always(function () {
                completed++;
                nextPage();
            });
        }

        nextPage();
    });

    updateTargetUi();
    updateTypeDependentUi();
})(jQuery);
