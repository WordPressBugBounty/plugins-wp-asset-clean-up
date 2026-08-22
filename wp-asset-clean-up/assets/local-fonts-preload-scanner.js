/*
 * Settings > Local Fonts / Google Fonts > Legacy manual preload audit
 *
 * The asset name is retained for backward-compatible enqueue/minification
 * paths, while the implementation now initialises every shared scanner card.
 */
(function (window, document, $) {
    'use strict';

    if (!$) {
        return;
    }

    initialiseGoogleFontsDisplayDependency();

    Array.prototype.forEach.call(
        document.querySelectorAll('[data-wpacu-font-preload-scanner]'),
        initialiseScanner
    );

    function initialiseGoogleFontsDisplayDependency() {
        var displaySelect = document.getElementById('wpacu_google_fonts_display');
        var dependentArea = document.querySelector('.wpacu-google-fonts-display-dependent');

        if (!displaySelect || !dependentArea) {
            return;
        }

        function updateDependentOpacity() {
            dependentArea.classList.toggle('is-inactive', displaySelect.value === '');
        }

        displaySelect.addEventListener('change', updateDependentOpacity);
        updateDependentOpacity();
    }

    function initialiseScanner(root) {
        var configElement = root.querySelector('.js-wpacu-font-preload-config');
        var config;

        if (!configElement) {
            return;
        }

        try {
            config = JSON.parse(configElement.textContent || configElement.innerText || '{}');
        } catch (error) {
            return;
        }

        var strings = config.strings || {};
        var provider = config.provider || root.getAttribute('data-wpacu-font-preload-scanner') || 'local';
        var textarea = root.querySelector('.js-wpacu-font-preload-field');
        var extraUrls = root.querySelector('.js-wpacu-font-preload-extra-urls');
        var countElement = root.querySelector('.js-wpacu-font-preload-count');
        var startButton = root.querySelector('.js-wpacu-font-preload-start');
        var cancelButton = root.querySelector('.js-wpacu-font-preload-cancel');
        var retryFailedButton = root.querySelector('.js-wpacu-font-preload-retry-failed');
        var feedback = root.querySelector('.js-wpacu-font-preload-feedback');
        var progress = root.querySelector('.js-wpacu-font-preload-progress');
        var progressText = root.querySelector('.js-wpacu-font-preload-progress-text');
        var progressPercent = root.querySelector('.js-wpacu-font-preload-progress-percent');
        var progressBar = root.querySelector('.js-wpacu-font-preload-progress-bar');
        var pagesArea = root.querySelector('.js-wpacu-font-preload-pages');
        var checksDetails = root.querySelector('.js-wpacu-font-preload-checks-details');
        var checksSummary = root.querySelector('.js-wpacu-font-preload-checks-summary');
        var results = root.querySelector('.js-wpacu-font-preload-results');
        var summary = root.querySelector('.js-wpacu-font-preload-summary');
        var globalNotice = root.querySelector('.js-wpacu-font-preload-global-notice');
        var resultsList = root.querySelector('.js-wpacu-font-preload-results-list');
        var resultsFooter = root.querySelector('.js-wpacu-font-preload-results-footer');
        var removeButton = root.querySelector('.js-wpacu-font-preload-remove-selected');
        var selectedCount = root.querySelector('.js-wpacu-font-preload-selected-count');
        var undoNotice = root.querySelector('.js-wpacu-font-preload-undo-notice');
        var undoText = root.querySelector('.js-wpacu-font-preload-undo-text');
        var undoButton = root.querySelector('.js-wpacu-font-preload-undo');
        var framesArea = root.querySelector('.js-wpacu-font-preload-frames');
        var legacyBody = root.querySelector('.js-wpacu-font-preload-legacy-body');
        var riskModal = root.querySelector('.js-wpacu-font-risk-modal');
        var riskAcceptButton = root.querySelector('.js-wpacu-font-risk-accept');
        var riskCancelButton = root.querySelector('.js-wpacu-font-risk-cancel');
        var riskError = root.querySelector('.js-wpacu-font-risk-error');
        var taskIdPrefix = (root.id || ('wpacuFontPreload' + provider)) + 'Task';
        var activeScan = null;
        var prepareRequest = null;
        var resolveRequest = null;
        var prepareWasCancelled = false;
        var lastCompletedScan = null;
        var undoValue = null;
        var suppressTextareaInvalidation = false;
        var scannerPermanentlyDisabled = !!(startButton && startButton.disabled);
        var startButtonLabel = startButton ? startButton.querySelector('.wpacu-font-preload-scan__start-label') : null;
        var startButtonDefaultLabel = startButtonLabel ? startButtonLabel.textContent : '';

        initialiseRiskGate();

        if (!textarea || !startButton || !resultsList || !framesArea) {
            return;
        }

        function initialiseRiskGate() {
            if (!riskModal || !legacyBody || !riskAcceptButton || !riskCancelButton) {
                return;
            }

            // Keep the modal outside scanner cards that can be faded or disabled.
            // A fixed-position descendant still inherits its ancestor's opacity.
            if (riskModal.parentNode !== document.body) {
                document.body.appendChild(riskModal);
            }

            function closeRiskModal() {
                riskModal.hidden = true;
                document.documentElement.classList.remove('wpacu-font-risk-modal-open');
                root.open = false;
                root.querySelector('summary').focus();
            }

            function showRiskModal() {
                riskModal.hidden = false;
                document.documentElement.classList.add('wpacu-font-risk-modal-open');
                window.setTimeout(function () { riskCancelButton.focus(); }, 0);
            }

            root.addEventListener('toggle', function () {
                if (root.open && legacyBody.hidden) {
                    showRiskModal();
                }
            });

            riskCancelButton.addEventListener('click', closeRiskModal);
            riskModal.querySelector('.wpacu-font-risk-modal__backdrop').addEventListener('click', closeRiskModal);

            riskAcceptButton.addEventListener('click', function () {
                riskAcceptButton.disabled = true;
                riskCancelButton.disabled = true;
                riskError.hidden = true;

                $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: config.riskAckAction,
                        nonce: config.riskAckNonce,
                        provider: provider
                    }
                }).done(function (response) {
                    if (!response || !response.success) {
                        riskError.textContent = response && response.data && response.data.message
                            ? response.data.message
                            : 'The acknowledgement could not be saved. Please try again.';
                        riskError.hidden = false;
                        return;
                    }

                    riskModal.hidden = true;
                    legacyBody.hidden = false;
                    document.documentElement.classList.remove('wpacu-font-risk-modal-open');
                    config.riskAcknowledged = true;
                    textarea.focus();
                }).fail(function () {
                    riskError.textContent = 'The acknowledgement could not be saved. Please try again.';
                    riskError.hidden = false;
                }).always(function () {
                    riskAcceptButton.disabled = false;
                    riskCancelButton.disabled = false;
                });
            });

            riskModal.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeRiskModal();
                    return;
                }

                if (event.key === 'Tab') {
                    var focusable = [riskCancelButton, riskAcceptButton].filter(function (button) { return !button.disabled; });
                    var currentIndex = focusable.indexOf(document.activeElement);
                    if (event.shiftKey && currentIndex <= 0) {
                        event.preventDefault();
                        focusable[focusable.length - 1].focus();
                    } else if (!event.shiftKey && currentIndex === focusable.length - 1) {
                        event.preventDefault();
                        focusable[0].focus();
                    }
                }
            });

            if (root.open && legacyBody.hidden) {
                showRiskModal();
            }
        }

        function translate(key, replacements) {
            var value = Object.prototype.hasOwnProperty.call(strings, key) ? strings[key] : key;

            Object.keys(replacements || {}).forEach(function (replacementKey) {
                value = value.split('{' + replacementKey + '}').join(String(replacements[replacementKey]));
            });

            return value;
        }

        function createElement(tagName, className, text) {
            var element = document.createElement(tagName);

            if (className) {
                element.className = className;
            }

            if (typeof text !== 'undefined' && text !== null) {
                element.textContent = text;
            }

            return element;
        }

        function dispatchFieldEvent(element, eventName) {
            var event;

            if (typeof window.Event === 'function') {
                event = new Event(eventName, { bubbles: true });
            } else {
                event = document.createEvent('Event');
                event.initEvent(eventName, true, false);
            }

            element.dispatchEvent(event);
        }

        function parseNonEmptyLines(value) {
            var output = [];

            String(value || '').split(/\r\n|\r|\n/).forEach(function (line) {
                line = line.trim();

                if (line) {
                    output.push(line);
                }
            });

            return output;
        }

        function updateUrlCount() {
            if (!countElement) {
                return;
            }

            var count = parseNonEmptyLines(textarea.value).length;
            countElement.textContent = count + ' ' + (count === 1 ? translate('urlSingular') : translate('urlPlural'));
        }

        function showFeedback(message, type) {
            if (!feedback) {
                return;
            }

            feedback.hidden = !message;
            feedback.className = 'wpacu-font-preload-scan__feedback js-wpacu-font-preload-feedback' + (type ? ' is-' + type : '');
            feedback.textContent = message || '';
        }

        function setScanningState(isScanning) {
            startButton.disabled = scannerPermanentlyDisabled || isScanning;
            startButton.classList.toggle('is-scanning', isScanning);
            startButton.setAttribute('aria-busy', isScanning ? 'true' : 'false');

            if (startButtonLabel) {
                startButtonLabel.textContent = isScanning ? translate('auditing') : startButtonDefaultLabel;
            }

            if (cancelButton) {
                cancelButton.hidden = !isScanning;
            }

            textarea.readOnly = isScanning;

            if (extraUrls) {
                extraUrls.readOnly = isScanning;
            }

            if (retryFailedButton) {
                retryFailedButton.disabled = isScanning;
            }

            if (removeButton && isScanning) {
                removeButton.disabled = true;
            }
        }

        function clearVisualOutput(keepUndo) {
            showFeedback('', '');

            if (progress) {
                progress.hidden = true;
            }

            if (results) {
                results.hidden = true;
            }

            if (!keepUndo && undoNotice) {
                undoNotice.hidden = true;
            }

            if (pagesArea) {
                pagesArea.textContent = '';
            }

            if (summary) {
                summary.textContent = '';
            }

            if (globalNotice) {
                globalNotice.hidden = true;
                globalNotice.className = 'wpacu-font-preload-results__notice js-wpacu-font-preload-global-notice';
                globalNotice.textContent = '';
            }

            if (checksSummary) {
                checksSummary.textContent = translate('checksSummaryDefault');
            }

            if (checksDetails) {
                checksDetails.open = true;
            }

            resultsList.textContent = '';

            if (progressBar) {
                progressBar.style.width = '0%';
            }

            if (progressPercent) {
                progressPercent.textContent = '0%';
            }

            if (removeButton) {
                removeButton.disabled = true;
            }

            if (resultsFooter) {
                resultsFooter.hidden = true;
            }

            if (selectedCount) {
                selectedCount.textContent = '';
            }

            if (retryFailedButton) {
                retryFailedButton.hidden = true;
            }

            framesArea.textContent = '';
            lastCompletedScan = null;

            if (!keepUndo) {
                undoValue = null;
            }
        }

        function addQueryArguments(url, argumentsToAdd) {
            try {
                var parsed = new URL(url, window.location.href);

                Object.keys(argumentsToAdd).forEach(function (key) {
                    parsed.searchParams.set(key, argumentsToAdd[key]);
                });

                return parsed.href;
            } catch (error) {
                return url;
            }
        }

        function getVerificationUrl(task) {
            if (!task || !task.page || !task.page.scanUrl) {
                return '';
            }

            var queryArguments = {
                wpacu_font_scan_task: 'manual-' + String(Date.now()),
                wpacu_font_scan_attempt: 'manual'
            };
            queryArguments[config.viewQueryArg || 'wpacu_font_preload_scan_view'] = task.view && task.view.id
                ? task.view.id
                : 'desktop';
            queryArguments[config.verificationQueryArg || 'wpacu_font_scan_verify'] = '1';

            return addQueryArguments(task.page.scanUrl, queryArguments);
        }

        function uniqueValues(values) {
            var seen = Object.create(null);
            var output = [];

            (values || []).forEach(function (value) {
                if (!value || seen[value]) {
                    return;
                }

                seen[value] = true;
                output.push(value);
            });

            return output;
        }

        function collapseDescriptor(value) {
            return String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
        }

        function stripOuterQuotes(value) {
            value = String(value || '').trim();

            if (value.length > 1 && ((value.charAt(0) === '"' && value.charAt(value.length - 1) === '"') ||
                (value.charAt(0) === "'" && value.charAt(value.length - 1) === "'"))) {
                return value.substring(1, value.length - 1);
            }

            return value;
        }

        function normaliseFontFamily(value) {
            return collapseDescriptor(stripOuterQuotes(value));
        }

        function normaliseFontWeight(value) {
            value = collapseDescriptor(value || 'normal');

            if (value === 'normal' || value === 'regular') {
                return '400';
            }

            if (value === 'bold') {
                return '700';
            }

            return value;
        }

        function normaliseFontStretch(value) {
            value = collapseDescriptor(value || 'normal');
            return value === 'normal' ? '100%' : value;
        }

        function normaliseUnicodeRange(value) {
            value = collapseDescriptor(value).replace(/\s*,\s*/g, ',').replace(/\s+/g, '');

            if (value === 'u+0-10ffff' || value === 'u+0000-10ffff') {
                return '';
            }

            return value;
        }

        function normaliseVariationSettings(value) {
            return collapseDescriptor(value).replace(/\s*,\s*/g, ',');
        }

        function fontDescriptorsMatch(face, loadedFace) {
            if (!face || !loadedFace || collapseDescriptor(loadedFace.status) !== 'loaded') {
                return false;
            }

            if (!normaliseFontFamily(face.family) ||
                normaliseFontFamily(face.family) !== normaliseFontFamily(loadedFace.family)) {
                return false;
            }

            if (collapseDescriptor(face.style || 'normal') !== collapseDescriptor(loadedFace.style || 'normal')) {
                return false;
            }

            if (normaliseFontWeight(face.weight) !== normaliseFontWeight(loadedFace.weight)) {
                return false;
            }

            if (normaliseFontStretch(face.stretch) !== normaliseFontStretch(loadedFace.stretch)) {
                return false;
            }

            if (normaliseUnicodeRange(face.unicodeRange) !== normaliseUnicodeRange(loadedFace.unicodeRange)) {
                return false;
            }

            var faceVariation = normaliseVariationSettings(face.variationSettings);
            var loadedVariation = normaliseVariationSettings(loadedFace.variationSettings);

            if (faceVariation && faceVariation !== loadedVariation) {
                return false;
            }

            var faceFeatures = normaliseVariationSettings(face.featureSettings);
            var loadedFeatures = normaliseVariationSettings(loadedFace.featureSettings);

            return !faceFeatures || faceFeatures === loadedFeatures;
        }

        function normaliseComparableUrl(value) {
            try {
                var parsed = new URL(value, window.location.href);
                parsed.hash = '';
                return parsed.href;
            } catch (error) {
                return '';
            }
        }

        function googleFaceLoadedInPayload(face, payload) {
            if (!face || face.hasLocalSource || Number(face.sourceUrlCount || 0) !== 1 ||
                Number(face.descriptorSourceUrlCount || 0) !== 1 || !payload ||
                !Array.isArray(payload.loadedFontFaces) || !Array.isArray(payload.googleStylesheets)) {
                return false;
            }

            var sourceStylesheet = normaliseComparableUrl(face.sourceStylesheet);
            var stylesheetWasLoaded = sourceStylesheet && payload.googleStylesheets.some(function (stylesheetUrl) {
                return normaliseComparableUrl(stylesheetUrl) === sourceStylesheet;
            });

            if (!stylesheetWasLoaded) {
                return false;
            }

            return payload.loadedFontFaces.some(function (loadedFace) {
                return fontDescriptorsMatch(face, loadedFace);
            });
        }

        function isVersionOnlyReplacement(savedUrl, candidateUrl) {
            var versionKeys = { v: true, ver: true, version: true };

            try {
                var saved = new URL(savedUrl, window.location.href);
                var candidate = new URL(candidateUrl, window.location.href);

                if (saved.origin !== candidate.origin || saved.pathname !== candidate.pathname || saved.href === candidate.href) {
                    return false;
                }

                var allKeys = Object.create(null);
                var hasVersionDifference = false;

                saved.searchParams.forEach(function (value, key) {
                    allKeys[key] = true;
                });

                candidate.searchParams.forEach(function (value, key) {
                    allKeys[key] = true;
                });

                var keys = Object.keys(allKeys);

                if (!keys.length) {
                    return false;
                }

                for (var index = 0; index < keys.length; index++) {
                    var key = keys[index];
                    var savedValues = saved.searchParams.getAll(key).slice(0).sort().join('\u0000');
                    var candidateValues = candidate.searchParams.getAll(key).slice(0).sort().join('\u0000');

                    if (savedValues === candidateValues) {
                        continue;
                    }

                    if (!versionKeys[String(key).toLowerCase()]) {
                        return false;
                    }

                    hasVersionDifference = true;
                }

                return hasVersionDifference;
            } catch (error) {
                return false;
            }
        }

        function copyObject(source) {
            var output = {};

            Object.keys(source || {}).forEach(function (key) {
                output[key] = source[key];
            });

            return output;
        }

        function uniqueObjects(values) {
            var seen = Object.create(null);
            var output = [];

            (values || []).forEach(function (value) {
                var key;

                try {
                    key = JSON.stringify(value);
                } catch (error) {
                    key = String(value);
                }

                if (seen[key]) {
                    return;
                }

                seen[key] = true;
                output.push(value);
            });

            return output;
        }

        function fontEvidenceIsComplete(fontResult) {
            // The current collector always returns an explicit boolean. Treat a
            // missing value as incomplete so a stale cached collector cannot turn
            // an uncertain result into a browser-based keep/remove recommendation.
            return !!fontResult && fontResult.evidenceComplete === true;
        }

        function getIncompleteFontIndexes(payload) {
            var indexes = [];

            if (!payload || !Array.isArray(payload.fonts)) {
                return indexes;
            }

            payload.fonts.forEach(function (fontResult, fontIndex) {
                if (!fontEvidenceIsComplete(fontResult)) {
                    indexes.push(fontIndex);
                }
            });

            return indexes;
        }

        function mergeFontResults(previousFont, nextFont) {
            if (!previousFont) {
                return nextFont ? copyObject(nextFont) : null;
            }

            if (!nextFont) {
                return copyObject(previousFont);
            }

            var merged = copyObject(previousFont);
            var stringArrayKeys = [
                'requestStartTimes',
                'initiatorTypes',
                'matchingFaceStatuses',
                'samePathRequestedUrls',
                'fontFaceAttributionModes',
                'attributedSourceUrls'
            ];
            var objectArrayKeys = [
                'loadedFaceMatches',
                'computedStyleMatches',
                'cssFaces',
                'samePathCssFaces',
                'exactResourceEntries',
                'samePathResourceEntries'
            ];

            merged.requested = !!(previousFont.requested || nextFont.requested);
            merged.exactRequestObserved = !!(previousFont.exactRequestObserved || nextFont.exactRequestObserved);
            merged.preloadedElsewhere = !!(previousFont.preloadedElsewhere || nextFont.preloadedElsewhere);
            merged.ownPreloadObserved = !!(previousFont.ownPreloadObserved || previousFont.ownPreloadPresent ||
                nextFont.ownPreloadObserved || nextFont.ownPreloadPresent);
            merged.cssReferenced = !!(previousFont.cssReferenced || nextFont.cssReferenced);
            merged.appliedViaComputedStyle = !!(previousFont.appliedViaComputedStyle || nextFont.appliedViaComputedStyle);
            merged.loadedViaFontFace = !!(previousFont.loadedViaFontFace || nextFont.loadedViaFontFace);

            stringArrayKeys.forEach(function (key) {
                merged[key] = uniqueValues((previousFont[key] || []).concat(nextFont[key] || []));
            });

            objectArrayKeys.forEach(function (key) {
                merged[key] = uniqueObjects((previousFont[key] || []).concat(nextFont[key] || [])).slice(0, 12);
            });

            merged.evidenceComplete = merged.requested || merged.appliedViaComputedStyle || merged.loadedViaFontFace ||
                fontEvidenceIsComplete(previousFont) || fontEvidenceIsComplete(nextFont);
            merged.targetLoading = !merged.evidenceComplete && !!(previousFont.targetLoading || nextFont.targetLoading);
            merged.targetErrored = !merged.evidenceComplete && !!(previousFont.targetErrored || nextFont.targetErrored);
            merged.ownPreloadPresent = !merged.evidenceComplete && !!(previousFont.ownPreloadPresent || nextFont.ownPreloadPresent);
            merged.stableNotRequested = merged.evidenceComplete && !merged.requested &&
                !merged.appliedViaComputedStyle && !merged.loadedViaFontFace;

            if (merged.requested) {
                merged.evidenceState = 'exact_request';
            } else if (merged.appliedViaComputedStyle) {
                merged.evidenceState = 'rendered_font_usage';
            } else if (merged.loadedViaFontFace) {
                merged.evidenceState = 'font_face_loaded';
            } else if (merged.evidenceComplete) {
                merged.evidenceState = 'stable_not_requested';
            } else {
                merged.evidenceState = nextFont.evidenceState || previousFont.evidenceState || 'target_observation_incomplete';
            }

            merged.incompleteReason = merged.evidenceComplete
                ? ''
                : (nextFont.incompleteReason || previousFont.incompleteReason || merged.evidenceState);
            merged.diagnostic = merged.evidenceComplete
                ? (nextFont.diagnostic || previousFont.diagnostic || null)
                : (nextFont.diagnostic || previousFont.diagnostic || null);

            return merged;
        }

        function mergeTaskPayloads(previousPayload, nextPayload) {
            if (!previousPayload) {
                return nextPayload || null;
            }

            if (!nextPayload) {
                return previousPayload;
            }

            var merged = copyObject(previousPayload);
            var fontCount = Math.max(
                Array.isArray(previousPayload.fonts) ? previousPayload.fonts.length : 0,
                Array.isArray(nextPayload.fonts) ? nextPayload.fonts.length : 0
            );

            merged.fonts = [];

            for (var fontIndex = 0; fontIndex < fontCount; fontIndex++) {
                merged.fonts.push(mergeFontResults(
                    previousPayload.fonts ? previousPayload.fonts[fontIndex] : null,
                    nextPayload.fonts ? nextPayload.fonts[fontIndex] : null
                ));
            }

            merged.loadedFontFaces = uniqueObjects(
                (previousPayload.loadedFontFaces || []).concat(nextPayload.loadedFontFaces || [])
            ).slice(0, 250);
            merged.googleStylesheets = uniqueValues(
                (previousPayload.googleStylesheets || []).concat(nextPayload.googleStylesheets || [])
            ).slice(0, 30);
            merged.googleFontResources = uniqueValues(
                (previousPayload.googleFontResources || []).concat(nextPayload.googleFontResources || [])
            ).slice(0, 150);
            merged.inaccessibleStyleSheets = Math.max(
                Number(previousPayload.inaccessibleStyleSheets || 0),
                Number(nextPayload.inaccessibleStyleSheets || 0)
            );
            merged.resourceCount = Math.max(
                Number(previousPayload.resourceCount || 0),
                Number(nextPayload.resourceCount || 0)
            );
            merged.pendingGoogleStylesheets = Math.min(
                Number(previousPayload.pendingGoogleStylesheets || 0),
                Number(nextPayload.pendingGoogleStylesheets || 0)
            );
            merged.globalFontSetStatus = nextPayload.globalFontSetStatus || previousPayload.globalFontSetStatus || '';
            merged.finalPageUrl = nextPayload.finalPageUrl || previousPayload.finalPageUrl || '';
            merged.manualPreloadSuppressed = !!(previousPayload.manualPreloadSuppressed || nextPayload.manualPreloadSuppressed);
            merged.ownPreloadTagsPresent = Math.min(
                Number(previousPayload.ownPreloadTagsPresent || 0),
                Number(nextPayload.ownPreloadTagsPresent || 0)
            );
            merged.scanTrimmed = !!(previousPayload.scanTrimmed || nextPayload.scanTrimmed);

            return merged;
        }

        function getIncompleteReasonLabel(reason) {
            var map = {
                target_font_loading: 'targetReasonLoading',
                target_font_error: 'targetReasonError',
                google_stylesheet_pending: 'targetReasonGooglePending',
                target_activity_not_settled: 'targetReasonActivity',
                own_preload_present: 'targetReasonOwnPreload',
                optimized_fallback_negative: 'targetReasonOptimizedFallbackNegative'
            };

            return translate(map[reason] || 'targetReasonUnknown');
        }

        function getIncompleteEvidenceDetails(payload) {
            if (!payload || !Array.isArray(payload.fonts)) {
                return '';
            }

            var details = [];

            payload.fonts.forEach(function (fontResult) {
                if (fontEvidenceIsComplete(fontResult)) {
                    return;
                }

                var diagnostic = fontResult.diagnostic || {};
                var original = fontResult.original || fontResult.normalised || 'Font';
                var fontLabel = original;

                try {
                    fontLabel = decodeURIComponent(new URL(original, window.location.href).pathname.split('/').pop() || original);
                } catch (error) {
                    // Keep the original value when it is not parseable as a URL.
                }

                details.push(translate('targetEvidenceDiagnostic', {
                    font: fontLabel,
                    reason: getIncompleteReasonLabel(fontResult.incompleteReason || fontResult.evidenceState || ''),
                    exact: diagnostic.exactRequestObserved ? 'yes' : 'no',
                    css: diagnostic.cssReferenceFound ? 'yes' : 'no',
                    status: (diagnostic.matchingFaceStatuses || fontResult.matchingFaceStatuses || []).join(', ') || 'none'
                }));
            });

            return details.length ? ' ' + details.join(' ') : '';
        }

        function getIncompleteEvidenceMessage(count, afterRetry, payload) {
            var message;

            if (count === 1) {
                message = translate(afterRetry ? 'targetEvidenceAfterRetryOne' : 'targetEvidenceIncompleteOne');
            } else {
                message = translate(afterRetry ? 'targetEvidenceAfterRetryMany' : 'targetEvidenceIncompleteMany', {
                    count: count
                });
            }

            return message + getIncompleteEvidenceDetails(payload);
        }

        function renderTaskList(tasks) {
            if (!pagesArea) {
                return;
            }

            pagesArea.textContent = '';

            tasks.forEach(function (task, index) {
                var row = createElement('div', 'wpacu-font-preload-scan__page is-pending');
                row.id = taskIdPrefix + index;

                var icon = createElement('span', 'wpacu-font-preload-scan__page-icon', '•');
                icon.setAttribute('aria-hidden', 'true');

                var textWrap = createElement('span', 'wpacu-font-preload-scan__page-copy');
                textWrap.appendChild(createElement('strong', '', task.page.label));
                var pageMeta = createElement('small', 'wpacu-font-preload-scan__page-meta', task.view.label + ' · ');
                var pageLink = createElement('a', 'wpacu-font-preload-scan__page-link', task.page.displayUrl);
                pageLink.href = task.page.url;
                pageLink.target = '_blank';
                pageLink.rel = 'noopener noreferrer';
                pageMeta.appendChild(pageLink);
                textWrap.appendChild(pageMeta);
                var detail = createElement('small', 'wpacu-font-preload-scan__page-detail', '');
                detail.hidden = true;
                textWrap.appendChild(detail);

                row.appendChild(icon);
                row.appendChild(textWrap);
                pagesArea.appendChild(row);
            });
        }

        function markTask(index, state, detail) {
            var row = document.getElementById(taskIdPrefix + index);

            if (!row) {
                return;
            }

            row.className = 'wpacu-font-preload-scan__page is-' + state;

            var icon = row.querySelector('.wpacu-font-preload-scan__page-icon');
            if (icon) {
                icon.textContent = state === 'observed-all' ? '✓' :
                    (state === 'observed-some' ? '◐' : (state === 'observed-none' ? '—' :
                    ((state === 'failed' || state === 'warning') ? '!' : (state === 'retrying' ? '↻' : '…'))));
            }

            var detailElement = row.querySelector('.wpacu-font-preload-scan__page-detail');

            if (detailElement) {
                detailElement.hidden = !detail;
                detailElement.textContent = detail || '';
            }

            row.title = detail || '';
        }

        function getTaskObservation(taskResult) {
            var fontResults = taskResult && taskResult.payload && Array.isArray(taskResult.payload.fonts)
                ? taskResult.payload.fonts
                : [];
            var total = activeScan && Array.isArray(activeScan.fontEntries)
                ? activeScan.fontEntries.length
                : fontResults.length;
            var observed = fontResults.filter(function (fontResult) {
                return !!fontResult && !!(
                    fontResult.requested ||
                    fontResult.appliedViaComputedStyle ||
                    fontResult.loadedViaFontFace
                );
            }).length;

            return {
                observed: observed,
                total: total,
                state: total > 0 && observed === total
                    ? 'observed-all'
                    : (observed > 0 ? 'observed-some' : 'observed-none'),
                message: observed === 0
                    ? translate(total === 1 ? 'fontUrlNotObserved' : 'fontUrlsNotObserved', { total: total })
                    : translate('fontUrlsObserved', {
                        observed: observed,
                        total: total
                    })
            };
        }

        function updateProgress(position, total, task, taskIndex, isRetryQueue) {
            var current = position + 1;
            var percent = total > 0 ? Math.round((position / total) * 100) : 0;

            if (progress) {
                progress.hidden = false;
            }

            if (progressText) {
                progressText.textContent = translate(isRetryQueue ? 'retryChecking' : 'checking', {
                    current: current,
                    total: total,
                    page: task.page.label,
                    view: task.view.label
                });
            }

            if (progressPercent) {
                progressPercent.textContent = percent + '%';
            }

            if (progressBar) {
                progressBar.style.width = percent + '%';
            }

            markTask(taskIndex, 'running', isRetryQueue ? translate('manualRetryRunning') : '');
        }

        function getScanStats(scan) {
            var taskResults = scan && Array.isArray(scan.taskResults) ? scan.taskResults.filter(Boolean) : [];
            var successful = taskResults.filter(function (taskResult) {
                return !!taskResult.payload && !taskResult.incomplete;
            });
            var incomplete = taskResults.filter(function (taskResult) {
                return !!taskResult.payload && !!taskResult.incomplete;
            });
            var failed = taskResults.filter(function (taskResult) {
                return !taskResult.payload;
            });
            var recovered = successful.filter(function (taskResult) {
                return !!taskResult.recovered;
            });

            var googleStylesheetFailures = provider === 'google' && scan && scan.googleResolution &&
                Array.isArray(scan.googleResolution.failedStylesheets)
                ? scan.googleResolution.failedStylesheets.length
                : 0;
            var browserWarnings = failed.length + incomplete.length;

            return {
                total: scan && Array.isArray(scan.tasks) ? scan.tasks.length : taskResults.length,
                success: successful.length,
                failed: browserWarnings,
                browserWarnings: browserWarnings,
                warningCount: browserWarnings + (googleStylesheetFailures > 0 ? 1 : 0),
                googleStylesheetFailures: googleStylesheetFailures,
                hardFailed: failed.length,
                incomplete: incomplete.length,
                recovered: recovered.length,
                failedResults: failed.concat(incomplete)
            };
        }

        function getGoogleResolutionImpact(scan) {
            var failures = provider === 'google' && scan && scan.googleResolution &&
                Array.isArray(scan.googleResolution.failedStylesheets)
                ? scan.googleResolution.failedStylesheets
                : [];
            var impact = {
                total: failures.length,
                affected: 0,
                unrelated: 0,
                retryable: 0,
                permanent: 0,
                retryableAffected: 0,
                retryableUnrelated: 0,
                permanentAffected: 0,
                permanentUnrelated: 0
            };

            if (!failures.length) {
                return impact;
            }

            var compatibilityMap = scan.googleResolution.browserCompatibilityByUrl || {};
            var fontUrls = (scan.fontEntries || []).map(function (entry) {
                return entry && (entry.normalised || entry.original)
                    ? (entry.normalised || entry.original)
                    : '';
            }).filter(Boolean);
            var hasCompatibilityForEveryEntry = fontUrls.length > 0 && fontUrls.every(function (fontUrl) {
                return Object.prototype.hasOwnProperty.call(compatibilityMap, fontUrl);
            });

            failures.forEach(function (failure) {
                var failureUrl = failure && failure.url ? failure.url : '';
                var affectsConfiguredEntry = !failureUrl || !hasCompatibilityForEveryEntry;
                var retryable = isGoogleResolutionFailureRetryable(failure);

                if (!affectsConfiguredEntry) {
                    affectsConfiguredEntry = fontUrls.some(function (fontUrl) {
                        var compatibility = compatibilityMap[fontUrl] || {};
                        var relevantStylesheets = Array.isArray(compatibility.relevantStylesheets)
                            ? compatibility.relevantStylesheets
                            : [];

                        if (relevantStylesheets.indexOf(failureUrl) !== -1) {
                            return true;
                        }

                        // Older/partial resolver payloads do not expose a scoped
                        // stylesheet list. Keep those failures conservative.
                        return typeof compatibility.scopeKnown === 'undefined' && !relevantStylesheets.length;
                    });
                }

                if (affectsConfiguredEntry) {
                    impact.affected++;
                    if (retryable) {
                        impact.retryableAffected++;
                    } else {
                        impact.permanentAffected++;
                    }
                } else {
                    impact.unrelated++;
                    if (retryable) {
                        impact.retryableUnrelated++;
                    } else {
                        impact.permanentUnrelated++;
                    }
                }

                if (retryable) {
                    impact.retryable++;
                } else {
                    impact.permanent++;
                }
            });

            return impact;
        }

        function getCompletionMessage(scan) {
            var stats = getScanStats(scan);
            var message;

            if (stats.browserWarnings === 1) {
                message = translate('completeWithOneWarning', {
                    success: stats.success,
                    total: stats.total
                });
            } else if (stats.browserWarnings > 1) {
                message = translate('completeWithWarnings', {
                    failed: stats.browserWarnings,
                    success: stats.success,
                    total: stats.total
                });
            } else if (stats.recovered === 1) {
                message = translate('completeWithOneRetry', { total: stats.total });
            } else if (stats.recovered > 1) {
                message = translate('completeWithRetries', {
                    recovered: stats.recovered,
                    total: stats.total
                });
            } else {
                message = translate('complete');
            }

            return message;
        }

        function finishProgress(scan) {
            var stats = getScanStats(scan);

            if (progressText) {
                progressText.textContent = getCompletionMessage(scan);
            }

            if (progressPercent) {
                progressPercent.textContent = '100%';
            }

            if (progressBar) {
                progressBar.style.width = '100%';
            }

            if (checksSummary) {
                var checksSummaryKey = 'checksSummaryClean';

                if (stats.browserWarnings === 1) {
                    checksSummaryKey = 'checksSummaryWarningOne';
                } else if (stats.browserWarnings > 1) {
                    checksSummaryKey = 'checksSummaryWarnings';
                } else if (stats.recovered > 0) {
                    checksSummaryKey = 'checksSummaryRecovered';
                }

                checksSummary.textContent = translate(checksSummaryKey, {
                    success: stats.success,
                    total: stats.total,
                    warnings: stats.browserWarnings,
                    recovered: stats.recovered
                });
            }

            if (checksDetails) {
                checksDetails.open = stats.browserWarnings > 0;
            }
        }

        function getTimeoutMessage(code) {
            if (code === 'bootstrap_timeout') {
                return translate('bootstrapTimeout', {
                    seconds: Math.round(Number(config.bootstrapTimeout || 25000) / 1000)
                });
            }

            if (code === 'collector_missing') {
                return translate('collectorMissing');
            }

            if (code === 'evidence_timeout') {
                return translate('evidenceTimeout', {
                    seconds: Math.round(Number(config.evidenceTimeout || 18000) / 1000)
                });
            }

            if (code === 'hard_timeout') {
                return translate('hardTimeout');
            }

            if (code === 'invalid_result') {
                return translate('invalidResult');
            }

            if (code === 'iframe_error') {
                return translate('iframeError');
            }

            return translate('scanFailed');
        }

        function isRetryableFailure(failureCode) {
            return [
                'bootstrap_timeout',
                'collector_missing',
                'evidence_timeout',
                'hard_timeout',
                'target_incomplete',
                'collector_error',
                'iframe_error'
            ].indexOf(failureCode) !== -1;
        }

        function executeTaskAttempt(scan, task, taskIndex, attempt, isConfirmationAttempt) {
            return new Promise(function (resolve) {
                var iframe = document.createElement('iframe');
                var completed = false;
                var collectorReady = false;
                var bootstrapTimeoutId = null;
                var evidenceTimeoutId = null;
                var hardTimeoutId = null;
                var collectorMissingTimeoutId = null;
                var startedAt = Date.now();
                var maxAttempts = Math.max(1, Number(config.maxAttempts || 2));

                iframe.className = 'wpacu-font-preload-scan__frame';
                iframe.width = String(task.view.width);
                iframe.height = String(task.view.height);
                iframe.tabIndex = -1;
                iframe.setAttribute('aria-hidden', 'true');
                iframe.setAttribute('title', task.page.label + ' — ' + task.view.label);
                iframe.setAttribute('loading', 'eager');
                iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');

                function clearTimers() {
                    window.clearTimeout(bootstrapTimeoutId);
                    window.clearTimeout(evidenceTimeoutId);
                    window.clearTimeout(hardTimeoutId);
                    window.clearTimeout(collectorMissingTimeoutId);
                }

                function complete(payload, failedReason, failureCode) {
                    if (completed) {
                        return;
                    }

                    completed = true;
                    clearTimers();
                    window.removeEventListener('message', onMessage);

                    if (scan) {
                        scan.cancelCurrentTask = null;
                    }

                    if (iframe.parentNode) {
                        iframe.parentNode.removeChild(iframe);
                    }

                    resolve({
                        payload: payload || null,
                        error: failedReason || '',
                        failureCode: failureCode || '',
                        task: task,
                        taskIndex: taskIndex,
                        taskLabel: task.page.label + ' — ' + task.view.label,
                        attempt: attempt,
                        collectorReady: collectorReady,
                        elapsedMs: Date.now() - startedAt
                    });
                }

                function startEvidenceTimer() {
                    window.clearTimeout(evidenceTimeoutId);
                    evidenceTimeoutId = window.setTimeout(function () {
                        complete(null, getTimeoutMessage('evidence_timeout'), 'evidence_timeout');
                    }, Number(config.evidenceTimeout || 18000));
                }

                function onMessage(event) {
                    var message = event.data;
                    var allowedOrigins = Array.isArray(task.page.allowedOrigins) ? task.page.allowedOrigins : [];

                    if (event.source !== iframe.contentWindow || !message || message.token !== scan.token ||
                        (allowedOrigins.length && allowedOrigins.indexOf(event.origin) === -1)) {
                        return;
                    }

                    if (message.type === config.readyType && message.provider === provider) {
                        if (!collectorReady) {
                            collectorReady = true;
                            window.clearTimeout(bootstrapTimeoutId);
                            window.clearTimeout(collectorMissingTimeoutId);
                            startEvidenceTimer();
                            markTask(taskIndex, 'running', translate('collectorReady'));
                        }
                        return;
                    }

                    if (message.type === config.resultType && message.provider === provider) {
                        if (Array.isArray(message.fonts) && message.fonts.length === scan.fontEntries.length) {
                            var incompleteFontCount = getIncompleteFontIndexes(message).length;
                            complete(
                                message,
                                incompleteFontCount ? getIncompleteEvidenceMessage(incompleteFontCount, false, message) : '',
                                incompleteFontCount ? 'target_incomplete' : ''
                            );
                        } else {
                            complete(null, getTimeoutMessage('invalid_result'), 'invalid_result');
                        }
                        return;
                    }

                    if (message.type === config.errorType && message.provider === provider) {
                        complete(null, message.message || translate('collectorError'), 'collector_error');
                    }
                }

                window.addEventListener('message', onMessage);

                iframe.addEventListener('load', function () {
                    if (completed || collectorReady) {
                        return;
                    }

                    try {
                        if (iframe.contentWindow && iframe.contentWindow.location.href === 'about:blank') {
                            return;
                        }
                    } catch (locationError) {
                        // A redirect can make the frame temporarily cross-origin.
                        // The signed collector message still has to pass the strict
                        // source, token and allowed-origin checks below.
                    }

                    collectorMissingTimeoutId = window.setTimeout(function () {
                        if (!completed && !collectorReady) {
                            complete(null, getTimeoutMessage('collector_missing'), 'collector_missing');
                        }
                    }, Number(config.collectorMissingGrace || 1800));
                });

                iframe.addEventListener('error', function () {
                    complete(null, getTimeoutMessage('iframe_error'), 'iframe_error');
                });

                bootstrapTimeoutId = window.setTimeout(function () {
                    complete(null, getTimeoutMessage('bootstrap_timeout'), 'bootstrap_timeout');
                }, Number(config.bootstrapTimeout || 25000));

                hardTimeoutId = window.setTimeout(function () {
                    complete(null, getTimeoutMessage('hard_timeout'), 'hard_timeout');
                }, Number(config.hardTimeout || config.taskTimeout || 45000));

                scan.cancelCurrentTask = function () {
                    complete(null, translate('cancelled'), 'cancelled');
                };

                if (attempt > 1) {
                    markTask(taskIndex, 'running', translate(isConfirmationAttempt ? 'confirmationAttemptRunning' : 'retryAttemptRunning', {
                        attempt: attempt,
                        max: maxAttempts
                    }));
                }

                var queryArguments = {
                    wpacu_font_scan_task: String(taskIndex + 1) + '-' + String(Date.now()) + '-' + String(attempt),
                    wpacu_font_scan_attempt: String(attempt)
                };
                queryArguments[config.viewQueryArg || 'wpacu_font_preload_scan_view'] = task.view.id;

                // The first pass is fidelity-first: no scan-only HTML trimming.
                // Only an automatic retry may use the optimized fallback. Positive
                // evidence recovered there is useful, while negative evidence is
                // explicitly kept incomplete by the collector.
                if (attempt > 1 && config.optimizedFallbackQueryArg) {
                    queryArguments[config.optimizedFallbackQueryArg] = '1';
                }

                iframe.src = addQueryArguments(task.page.scanUrl, queryArguments);

                framesArea.appendChild(iframe);
            });
        }

        function executeTask(scan, task, taskIndex) {
            var maxAttempts = Math.max(1, Number(config.maxAttempts || 2));
            var retryDelay = Math.max(0, Number(config.retryDelay || 300));
            var attemptErrors = [];
            var mergedPayload = null;
            var confirmationRetry = false;

            function countObservedFonts(payload) {
                if (!payload || !Array.isArray(payload.fonts)) {
                    return 0;
                }

                return payload.fonts.filter(function (fontResult) {
                    return !!fontResult && !!(
                        fontResult.requested ||
                        fontResult.appliedViaComputedStyle ||
                        fontResult.loadedViaFontFace
                    );
                }).length;
            }

            function prepareFinalResult(taskResult, attempts, incomplete) {
                taskResult.payload = mergedPayload || taskResult.payload || null;
                taskResult.attempts = attempts;
                taskResult.confirmationRetried = confirmationRetry;
                taskResult.recovered = !incomplete && attempts > 1 && (
                    attemptErrors.length > 0 ||
                    (confirmationRetry && countObservedFonts(taskResult.payload) > 0)
                );
                taskResult.incomplete = !!incomplete;
                taskResult.attemptErrors = attemptErrors.slice(0);
                taskResult.incompleteFontIndexes = taskResult.payload
                    ? getIncompleteFontIndexes(taskResult.payload)
                    : [];
                return taskResult;
            }

            function waitForRetry(attempt, taskResult, isConfirmationAttempt) {
                markTask(taskIndex, 'retrying', translate(isConfirmationAttempt ? 'confirmingZeroObservation' : 'retryingTask', {
                    attempt: attempt + 1,
                    max: maxAttempts
                }));

                return new Promise(function (resolve) {
                    window.setTimeout(resolve, retryDelay);
                }).then(function () {
                    if (scan.cancelled) {
                        var incompleteCount = mergedPayload ? getIncompleteFontIndexes(mergedPayload).length : 0;
                        return prepareFinalResult(taskResult, attempt, incompleteCount > 0);
                    }

                    return runAttempt(attempt + 1, isConfirmationAttempt);
                });
            }

            function runAttempt(attempt, isConfirmationAttempt) {
                return executeTaskAttempt(scan, task, taskIndex, attempt, isConfirmationAttempt).then(function (taskResult) {
                    if (taskResult.payload) {
                        mergedPayload = mergeTaskPayloads(mergedPayload, taskResult.payload);
                    }

                    var incompleteFontCount = mergedPayload ? getIncompleteFontIndexes(mergedPayload).length : 0;
                    var currentFailureCode = taskResult.failureCode || '';

                    if (!taskResult.payload || currentFailureCode) {
                        attemptErrors.push({
                            attempt: attempt,
                            code: currentFailureCode,
                            message: taskResult.error
                        });
                    }

                    if (mergedPayload && incompleteFontCount === 0 && attempt === 1 && attempt < maxAttempts &&
                        countObservedFonts(mergedPayload) === 0) {
                        confirmationRetry = true;
                        return waitForRetry(attempt, taskResult, true);
                    }

                    if (mergedPayload && incompleteFontCount === 0) {
                        taskResult.payload = mergedPayload;
                        taskResult.error = '';
                        taskResult.failureCode = '';
                        return prepareFinalResult(taskResult, attempt, false);
                    }

                    if (!scan.cancelled && attempt < maxAttempts && isRetryableFailure(currentFailureCode)) {
                        return waitForRetry(attempt, taskResult, false);
                    }

                    if (mergedPayload) {
                        taskResult.payload = mergedPayload;
                        taskResult.failureCode = 'target_incomplete';
                        taskResult.error = getIncompleteEvidenceMessage(incompleteFontCount, attempt > 1, mergedPayload);
                        return prepareFinalResult(taskResult, attempt, true);
                    }

                    return prepareFinalResult(taskResult, attempt, false);
                });
            }

            return runAttempt(1, false);
        }

        function mergeRetriedTaskResult(previousResult, nextResult) {
            if (!previousResult || !previousResult.payload) {
                return nextResult;
            }

            nextResult.payload = mergeTaskPayloads(previousResult.payload, nextResult.payload);

            if (!nextResult.payload) {
                return nextResult;
            }

            nextResult.incompleteFontIndexes = getIncompleteFontIndexes(nextResult.payload);
            nextResult.incomplete = nextResult.incompleteFontIndexes.length > 0;
            nextResult.attemptErrors = (previousResult.attemptErrors || []).concat(nextResult.attemptErrors || []);
            nextResult.recovered = !nextResult.incomplete;

            if (nextResult.incomplete) {
                nextResult.failureCode = 'target_incomplete';
                nextResult.error = getIncompleteEvidenceMessage(nextResult.incompleteFontIndexes.length, true, nextResult.payload);
            } else {
                nextResult.failureCode = '';
                nextResult.error = '';
            }

            return nextResult;
        }

        function getFaceLabel(face) {
            if (!face) {
                return '';
            }

            var parts = [];

            if (face.family) {
                parts.push(face.family);
            }

            if (face.style && face.style !== 'normal') {
                parts.push(face.style);
            }

            if (face.weight) {
                parts.push(face.weight);
            }

            if (face.stretch && face.stretch !== 'normal') {
                parts.push(face.stretch);
            }

            if (face.subset) {
                parts.push(face.subset);
            }

            if (face.isVariable) {
                parts.push('variable');
            }

            if (face.isIconFont) {
                parts.push('icon font');
            }

            return parts.join(' · ');
        }

        function getCollectedGoogleStylesheetUrls(scan) {
            var stylesheetUrls = [];

            if (!scan || !Array.isArray(scan.taskResults)) {
                return stylesheetUrls;
            }

            scan.taskResults.forEach(function (taskResult) {
                if (!taskResult || !taskResult.payload || !Array.isArray(taskResult.payload.googleStylesheets)) {
                    return;
                }

                stylesheetUrls = stylesheetUrls.concat(taskResult.payload.googleStylesheets);
            });

            return uniqueValues(stylesheetUrls).slice(0, Number(config.maxStylesheets || 30));
        }

        function isGoogleResolutionFailureRetryable(failure) {
            failure = failure || {};

            if (typeof failure.retryable === 'boolean') {
                return failure.retryable;
            }

            var code = String(failure.code || '');
            var httpStatus = Math.max(0, Number(failure.httpStatus || 0));

            if (['unsafe_redirect', 'too_many_redirects', 'response_too_large', 'not_font_css'].indexOf(code) !== -1) {
                return false;
            }

            if (code === 'time_budget_exhausted') {
                return true;
            }

            if (httpStatus === 408 || httpStatus === 425 || httpStatus === 429 ||
                (httpStatus >= 500 && httpStatus <= 599)) {
                return true;
            }

            if (httpStatus >= 400) {
                return false;
            }

            return httpStatus === 0 || ['empty_response', 'request_failed'].indexOf(code) !== -1;
        }

        function normaliseGoogleResolutionFailure(failure, fallbackUrl) {
            failure = failure || {};

            return {
                url: failure.url || fallbackUrl || '',
                finalUrl: failure.finalUrl || failure.url || fallbackUrl || '',
                code: failure.code || 'request_failed',
                message: failure.message || translate('requestFailed'),
                attempts: Math.max(0, Number(failure.attempts || 0)),
                redirects: Math.max(0, Number(failure.redirects || 0)),
                httpStatus: Math.max(0, Number(failure.httpStatus || 0)),
                timeoutSeconds: Math.max(0, Number(failure.timeoutSeconds || 0)),
                retryable: isGoogleResolutionFailureRetryable(failure)
            };
        }

        function buildGoogleAjaxFailures(stylesheetUrls, message, code, httpStatus) {
            var urls = Array.isArray(stylesheetUrls) && stylesheetUrls.length ? stylesheetUrls : [''];

            return urls.map(function (stylesheetUrl) {
                return normaliseGoogleResolutionFailure({
                    url: stylesheetUrl,
                    code: code || 'resolver_ajax_failed',
                    message: message || translate('requestFailed'),
                    attempts: 1,
                    httpStatus: Number(httpStatus || 0)
                }, stylesheetUrl);
            });
        }

        function mergeGoogleResolutions(previousResolution, nextResolution, retriedUrls) {
            if (!previousResolution) {
                return nextResolution || {
                    fontFacesByUrl: {},
                    resolvedStylesheets: [],
                    failedStylesheets: []
                };
            }

            nextResolution = nextResolution || {};
            retriedUrls = uniqueValues(retriedUrls || []);

            var merged = {
                fontFacesByUrl: {},
                resolvedStylesheets: [],
                failedStylesheets: [],
                browserUserAgent: nextResolution.browserUserAgent || previousResolution.browserUserAgent || '',
                browserCompatibilityByUrl: {},
                comparisonProfiles: previousResolution.comparisonProfiles || [],
                budgetExhausted: !!(nextResolution.budgetExhausted || previousResolution.budgetExhausted)
            };

            [previousResolution.fontFacesByUrl || {}, nextResolution.fontFacesByUrl || {}].forEach(function (faceMap) {
                Object.keys(faceMap).forEach(function (fontUrl) {
                    merged.fontFacesByUrl[fontUrl] = uniqueObjects(
                        (merged.fontFacesByUrl[fontUrl] || []).concat(faceMap[fontUrl] || [])
                    );
                });
            });

            Object.keys(previousResolution.browserCompatibilityByUrl || {}).forEach(function (fontUrl) {
                merged.browserCompatibilityByUrl[fontUrl] = previousResolution.browserCompatibilityByUrl[fontUrl];
            });

            var hasMeaningfulCompatibilityUpdate = false;

            Object.keys(nextResolution.browserCompatibilityByUrl || {}).forEach(function (fontUrl) {
                var compatibility = nextResolution.browserCompatibilityByUrl[fontUrl];

                // A partial/evidence-free retry must not erase a valid browser
                // comparison collected during the full audit. Apply only maps
                // that still contain a known descriptor or a tested profile.
                if (!compatibility ||
                    (compatibility.descriptorKnown === false &&
                        Number(compatibility.testedProfileCount || 0) === 0)) {
                    return;
                }

                merged.browserCompatibilityByUrl[fontUrl] = compatibility;
                hasMeaningfulCompatibilityUpdate = true;
            });

            if (hasMeaningfulCompatibilityUpdate && Array.isArray(nextResolution.comparisonProfiles)) {
                merged.comparisonProfiles = nextResolution.comparisonProfiles;
            }

            var resolvedByUrl = Object.create(null);
            (previousResolution.resolvedStylesheets || []).concat(nextResolution.resolvedStylesheets || []).forEach(function (item) {
                if (!item || !item.url) {
                    return;
                }

                resolvedByUrl[item.url] = item;
            });
            merged.resolvedStylesheets = Object.keys(resolvedByUrl).map(function (url) {
                return resolvedByUrl[url];
            });

            var failuresByKey = Object.create(null);

            (previousResolution.failedStylesheets || []).forEach(function (failure) {
                failure = normaliseGoogleResolutionFailure(failure, '');

                if ((failure.url && retriedUrls.indexOf(failure.url) !== -1) || (!failure.url && retriedUrls.length)) {
                    return;
                }

                failuresByKey[(failure.url || '') + '\u0000' + failure.code] = failure;
            });

            (nextResolution.failedStylesheets || []).forEach(function (failure) {
                failure = normaliseGoogleResolutionFailure(failure, '');
                failuresByKey[(failure.url || '') + '\u0000' + failure.code] = failure;
            });

            Object.keys(failuresByKey).forEach(function (key) {
                var failure = failuresByKey[key];

                if (failure.url && resolvedByUrl[failure.url]) {
                    return;
                }

                merged.failedStylesheets.push(failure);
            });

            return merged;
        }

        function resolveGoogleStylesheets(scan, requestedStylesheetUrls) {
            if (provider !== 'google' || !config.resolveAction) {
                return Promise.resolve({
                    fontFacesByUrl: {},
                    resolvedStylesheets: [],
                    failedStylesheets: [],
                    browserCompatibilityByUrl: {},
                    comparisonProfiles: []
                });
            }

            var stylesheetUrls = Array.isArray(requestedStylesheetUrls) && requestedStylesheetUrls.length
                ? uniqueValues(requestedStylesheetUrls).slice(0, Number(config.maxStylesheets || 30))
                : getCollectedGoogleStylesheetUrls(scan);

            if (!stylesheetUrls.length) {
                return Promise.resolve({
                    fontFacesByUrl: {},
                    resolvedStylesheets: [],
                    failedStylesheets: [],
                    browserCompatibilityByUrl: {},
                    comparisonProfiles: []
                });
            }

            if (progressText) {
                progressText.textContent = translate('resolvingGoogleStylesheets');
            }

            return new Promise(function (resolve) {
                resolveRequest = $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: config.resolveAction,
                        nonce: config.nonce,
                        stylesheet_urls: JSON.stringify(stylesheetUrls),
                        font_urls: JSON.stringify((scan.fontEntries || []).map(function (entry) {
                            return entry && (entry.normalised || entry.original) ? (entry.normalised || entry.original) : '';
                        }).filter(Boolean))
                    }
                }).done(function (response) {
                    if (response && response.success && response.data) {
                        response.data.failedStylesheets = (Array.isArray(response.data.failedStylesheets)
                            ? response.data.failedStylesheets
                            : []).map(function (failure) {
                            return normaliseGoogleResolutionFailure(failure, '');
                        });
                        resolve(response.data);
                        return;
                    }

                    resolve({
                        fontFacesByUrl: {},
                        resolvedStylesheets: [],
                        failedStylesheets: buildGoogleAjaxFailures(
                            stylesheetUrls,
                            response && response.data && response.data.message ? response.data.message : translate('requestFailed'),
                            'resolver_ajax_response_error',
                            0
                        )
                    });
                }).fail(function (xhr, statusText) {
                    resolve({
                        fontFacesByUrl: {},
                        resolvedStylesheets: [],
                        failedStylesheets: buildGoogleAjaxFailures(
                            stylesheetUrls,
                            statusText || translate('requestFailed'),
                            'resolver_ajax_request_failed',
                            xhr && xhr.status ? xhr.status : 0
                        )
                    });
                }).always(function () {
                    resolveRequest = null;
                });
            });
        }

        function normaliseCoverageThreshold(value, fallback) {
            var parsed = Number(value);

            if (!isFinite(parsed) || parsed < 0 || parsed > 1) {
                return fallback;
            }

            return parsed;
        }

        var LIKELY_SITE_WIDE_MIN_CHECK_COVERAGE = normaliseCoverageThreshold(
            config.siteWideCandidateMinCheckCoverage,
            0.80
        );
        var BROAD_USAGE_MIN_PAGE_COVERAGE = normaliseCoverageThreshold(
            config.broadUsageMinPageCoverage,
            0.75
        );
        var BROAD_USAGE_MIN_CHECK_COVERAGE = normaliseCoverageThreshold(
            config.broadUsageMinCheckCoverage,
            0.60
        );

        function percentage(used, total) {
            return total > 0 ? Math.round((used / total) * 100) : 0;
        }

        function coverageText(used, total) {
            return translate('coverageValue', {
                used: used,
                total: total,
                percent: percentage(used, total)
            });
        }

        function aggregateResults(scan) {
            var scheduledChecks = scan && Array.isArray(scan.tasks) ? scan.tasks.length : 0;
            var completedResults = scan && Array.isArray(scan.taskResults)
                ? scan.taskResults.filter(Boolean)
                : [];

            return scan.fontEntries.map(function (entry, entryIndex) {
                var successes = [];
                var failedResults = [];
                var successByView = Object.create(null);

                completedResults.forEach(function (taskResult) {
                    var fontResult = taskResult.payload && taskResult.payload.fonts
                        ? taskResult.payload.fonts[entryIndex]
                        : null;

                    if (!taskResult.payload || !fontEvidenceIsComplete(fontResult)) {
                        failedResults.push({
                            task: taskResult.task,
                            taskIndex: taskResult.taskIndex,
                            taskLabel: taskResult.taskLabel,
                            error: fontResult && fontResult.incompleteReason
                                ? fontResult.incompleteReason
                                : taskResult.error,
                            failureCode: fontResult && fontResult.incompleteReason
                                ? fontResult.incompleteReason
                                : taskResult.failureCode,
                            payload: taskResult.payload || null
                        });
                        return;
                    }

                    successes.push(taskResult);
                    var viewId = taskResult.task.view.id || 'unknown';
                    successByView[viewId] = (successByView[viewId] || 0) + 1;
                });

                var failedCount = Math.max(failedResults.length, scheduledChecks - successes.length);
                var hasIncompletePayload = failedResults.some(function (failedResult) {
                    return !!failedResult.payload;
                });
                var successfulPageKeys = uniqueValues(successes.map(function (taskResult) {
                    return taskResult.task.page.url || taskResult.task.page.scanUrl || taskResult.task.page.label;
                }));
                var usageChecks = [];
                var directRequestChecks = [];
                var rawExactRequestChecks = [];
                var computedStyleChecks = [];
                var fontFaceChecks = [];
                var googleSemanticChecks = [];
                var stableExactNotRequestedChecks = [];
                var samePathRequestChecks = [];
                var ownPreloadChecks = [];
                var verificationChecks = [];
                var incompleteChecks = [];
                var usagePages = [];
                var usageByView = Object.create(null);
                var preloadChecks = [];
                var cssChecks = [];
                var alternatives = [];
                var versionAlternatives = [];
                var nonVersionAlternatives = [];
                var faceLabels = [];
                var sourceStylesheets = [];
                var googleFaces = [];
                var requestEvidence = [];
                var manualPreloadSuppressedChecks = [];
                var manualPreloadNotSuppressedChecks = [];
                var browserCompatibility = null;
                var browserSpecificAdvisory = false;

                if (provider === 'google' && scan.googleResolution && scan.googleResolution.browserCompatibilityByUrl && entry.normalised) {
                    browserCompatibility = scan.googleResolution.browserCompatibilityByUrl[entry.normalised] || null;
                    browserSpecificAdvisory = !!(browserCompatibility && browserCompatibility.browserSpecific);
                }

                if (provider === 'google' && scan.googleResolution && scan.googleResolution.fontFacesByUrl && entry.normalised) {
                    googleFaces = scan.googleResolution.fontFacesByUrl[entry.normalised] || [];
                    googleFaces.forEach(function (face) {
                        faceLabels.push(getFaceLabel(face));
                        if (face.sourceStylesheet) {
                            sourceStylesheets.push(face.sourceStylesheet);
                        }
                    });
                }

                successes.forEach(function (taskResult) {
                    var fontResult = taskResult.payload.fonts && taskResult.payload.fonts[entryIndex]
                        ? taskResult.payload.fonts[entryIndex]
                        : null;

                    if (!fontResult) {
                        return;
                    }

                    // A request forced by another compatible preload is not
                    // evidence that page CSS or content naturally needed the font.
                    // FontFaceSet may still confirm actual use on that check.
                    var directlyRequested = !!fontResult.requested && !fontResult.preloadedElsewhere;
                    var appliedFromComputedStyle = !directlyRequested && !!fontResult.appliedViaComputedStyle;
                    // For Google Fonts this is only a family/variant match: the provider
                    // may return another generated file URL in a different browser.
                    var loadedFromFontFaceSet = provider === 'local' && !directlyRequested && !appliedFromComputedStyle && !!fontResult.loadedViaFontFace;
                    var rawExactRequestObserved = !!(fontResult.exactRequestObserved || fontResult.requested);
                    var stableExactNotRequested = fontEvidenceIsComplete(fontResult) &&
                        !directlyRequested && !appliedFromComputedStyle && !loadedFromFontFaceSet &&
                        !fontResult.preloadedElsewhere;
                    var samePathRequestObserved = Array.isArray(fontResult.samePathRequestedUrls) &&
                        fontResult.samePathRequestedUrls.length > 0;
                    var manualPreloadSuppressed = taskResult.payload.manualPreloadSuppressed === true;

                    if (manualPreloadSuppressed) {
                        manualPreloadSuppressedChecks.push(taskResult.taskLabel);
                    } else {
                        manualPreloadNotSuppressedChecks.push(taskResult.taskLabel);
                    }

                    var googleSemanticMatch = false;

                    if (!directlyRequested && !loadedFromFontFaceSet && provider === 'google' && googleFaces.length) {
                        googleSemanticMatch = googleFaces.some(function (face) {
                            return googleFaceLoadedInPayload(face, taskResult.payload);
                        });
                    }

                    if (directlyRequested) {
                        directRequestChecks.push(taskResult.taskLabel);
                    }

                    if (rawExactRequestObserved) {
                        rawExactRequestChecks.push(taskResult.taskLabel);

                        var exactResourceEntries = Array.isArray(fontResult.exactResourceEntries)
                            ? fontResult.exactResourceEntries.slice(0, 8)
                            : [];
                        var requestInitiators = uniqueValues(
                            (fontResult.initiatorTypes || []).concat(exactResourceEntries.map(function (resourceEntry) {
                                return resourceEntry && resourceEntry.initiatorType ? resourceEntry.initiatorType : '';
                            }))
                        );
                        var requestCaptureSources = uniqueValues([].concat.apply([], exactResourceEntries.map(function (resourceEntry) {
                            return resourceEntry && Array.isArray(resourceEntry.captureSources)
                                ? resourceEntry.captureSources
                                : [];
                        })));

                        requestEvidence.push({
                            label: taskResult.taskLabel,
                            acceptedNaturalUse: directlyRequested,
                            exactRequestObserved: true,
                            preloadedElsewhere: !!fontResult.preloadedElsewhere,
                            ownPreloadPresent: !!(fontResult.ownPreloadObserved || fontResult.ownPreloadPresent),
                            manualPreloadSuppressed: manualPreloadSuppressed,
                            initiatorTypes: requestInitiators,
                            captureSources: requestCaptureSources,
                            resourceEntries: exactResourceEntries,
                            googleStylesheets: provider === 'google' && Array.isArray(taskResult.payload.googleStylesheets)
                                ? taskResult.payload.googleStylesheets.slice(0, 10)
                                : []
                        });
                    }

                    if (stableExactNotRequested) {
                        stableExactNotRequestedChecks.push(taskResult.taskLabel);
                        verificationChecks.push({
                            label: taskResult.taskLabel,
                            url: getVerificationUrl(taskResult.task)
                        });
                    }

                    if (samePathRequestObserved) {
                        samePathRequestChecks.push(taskResult.taskLabel);
                    }

                    if (fontResult.ownPreloadObserved || fontResult.ownPreloadPresent) {
                        ownPreloadChecks.push(taskResult.taskLabel);
                    }

                    if (appliedFromComputedStyle) {
                        computedStyleChecks.push(taskResult.taskLabel);
                    }

                    if (loadedFromFontFaceSet) {
                        fontFaceChecks.push(taskResult.taskLabel);
                    }

                    if (googleSemanticMatch) {
                        // A resolved Google @font-face and a loaded FontFace share
                        // descriptors, but FontFaceSet does not expose the source URL.
                        // Keep this as review evidence rather than exact URL usage.
                        googleSemanticChecks.push(taskResult.taskLabel);
                    }

                    if (directlyRequested || appliedFromComputedStyle || loadedFromFontFaceSet) {
                        usageChecks.push(taskResult.taskLabel);
                        usagePages.push(taskResult.task.page.url || taskResult.task.page.scanUrl || taskResult.task.page.label);
                        usageByView[taskResult.task.view.id] = (usageByView[taskResult.task.view.id] || 0) + 1;
                    }

                    if (fontResult.preloadedElsewhere) {
                        preloadChecks.push(taskResult.taskLabel);
                    }

                    if (fontResult.cssReferenced) {
                        cssChecks.push(taskResult.taskLabel);
                    }

                    (fontResult.samePathRequestedUrls || []).forEach(function (url) {
                        alternatives.push(url);

                        if (isVersionOnlyReplacement(fontResult.normalised || entry.normalised, url)) {
                            versionAlternatives.push(url);
                        } else {
                            nonVersionAlternatives.push(url);
                        }
                    });

                    (fontResult.cssFaces || []).concat(fontResult.samePathCssFaces || []).forEach(function (face) {
                        faceLabels.push(getFaceLabel(face));
                    });
                });

                usageChecks = uniqueValues(usageChecks);
                directRequestChecks = uniqueValues(directRequestChecks);
                rawExactRequestChecks = uniqueValues(rawExactRequestChecks);
                computedStyleChecks = uniqueValues(computedStyleChecks);
                fontFaceChecks = uniqueValues(fontFaceChecks);
                googleSemanticChecks = uniqueValues(googleSemanticChecks);
                stableExactNotRequestedChecks = uniqueValues(stableExactNotRequestedChecks);
                samePathRequestChecks = uniqueValues(samePathRequestChecks);
                ownPreloadChecks = uniqueValues(ownPreloadChecks);
                verificationChecks = uniqueObjects(verificationChecks);
                usagePages = uniqueValues(usagePages);
                preloadChecks = uniqueValues(preloadChecks);
                cssChecks = uniqueValues(cssChecks);
                alternatives = uniqueValues(alternatives);
                versionAlternatives = uniqueValues(versionAlternatives);
                nonVersionAlternatives = uniqueValues(nonVersionAlternatives);
                faceLabels = uniqueValues(faceLabels);
                sourceStylesheets = uniqueValues(sourceStylesheets);
                requestEvidence = uniqueObjects(requestEvidence);
                manualPreloadSuppressedChecks = uniqueValues(manualPreloadSuppressedChecks);
                manualPreloadNotSuppressedChecks = uniqueValues(manualPreloadNotSuppressedChecks);
                incompleteChecks = uniqueValues(failedResults.map(function (failedResult) {
                    return failedResult.taskLabel || '';
                }));

                var totalChecks = successes.length;
                var totalPages = successfulPageKeys.length;
                var usedChecks = usageChecks.length;
                var usedPages = usagePages.length;
                var desktopTotal = successByView.desktop || 0;
                var mobileTotal = successByView.mobile || 0;
                var desktopUsed = usageByView.desktop || 0;
                var mobileUsed = usageByView.mobile || 0;
                var pageCoverageRatio = totalPages > 0 ? usedPages / totalPages : 0;
                var checkCoverageRatio = totalChecks > 0 ? usedChecks / totalChecks : 0;
                var likelySiteWideCandidate = totalPages > 0 && usedPages === totalPages &&
                    checkCoverageRatio >= LIKELY_SITE_WIDE_MIN_CHECK_COVERAGE;
                var broadUsageCandidate = !likelySiteWideCandidate &&
                    pageCoverageRatio >= BROAD_USAGE_MIN_PAGE_COVERAGE &&
                    checkCoverageRatio >= BROAD_USAGE_MIN_CHECK_COVERAGE;
                var hasGoogleResolveFailures = provider === 'google' && scan.googleResolution &&
                    Array.isArray(scan.googleResolution.failedStylesheets) && scan.googleResolution.failedStylesheets.length > 0;
                var unresolvedGoogleStylesheets = provider === 'google' && hasGoogleResolveFailures && !googleFaces.length && usedChecks === 0
                    ? scan.googleResolution.failedStylesheets.map(function (failure) {
                        return normaliseGoogleResolutionFailure(failure, '');
                    })
                    : [];
                var secondaryEvidenceMessages = [];
                var secondaryEvidenceSuffix = '';
                var googleSemanticSuffix = '';

                if (computedStyleChecks.length === 1) {
                    secondaryEvidenceMessages.push(translate('computedStyleEvidenceOne'));
                } else if (computedStyleChecks.length > 1) {
                    secondaryEvidenceMessages.push(translate('computedStyleEvidenceMany', { count: computedStyleChecks.length }));
                }

                if (fontFaceChecks.length === 1) {
                    secondaryEvidenceMessages.push(translate('fontFaceEvidenceOne'));
                } else if (fontFaceChecks.length > 1) {
                    secondaryEvidenceMessages.push(translate('fontFaceEvidenceMany', { count: fontFaceChecks.length }));
                }

                secondaryEvidenceSuffix = secondaryEvidenceMessages.join(' ');

                if (googleSemanticChecks.length === 1) {
                    googleSemanticSuffix = translate('googleSemanticEvidenceOne');
                } else if (googleSemanticChecks.length > 1) {
                    googleSemanticSuffix = translate('googleSemanticEvidenceMany', { count: googleSemanticChecks.length });
                }

                var classification = {
                    entry: entry,
                    index: entryIndex,
                    status: 'review',
                    statusLabel: '',
                    reason: '',
                    selected: false,
                    removable: false,
                    removalType: '',
                    requestedChecks: usageChecks,
                    directRequestChecks: directRequestChecks,
                    rawExactRequestChecks: rawExactRequestChecks,
                    computedStyleChecks: computedStyleChecks,
                    fontFaceChecks: fontFaceChecks,
                    googleSemanticChecks: googleSemanticChecks,
                    stableExactNotRequestedChecks: stableExactNotRequestedChecks,
                    samePathRequestChecks: samePathRequestChecks,
                    ownPreloadChecks: ownPreloadChecks,
                    verificationChecks: verificationChecks,
                    incompleteChecks: incompleteChecks,
                    requestedPages: usagePages,
                    preloadChecks: preloadChecks,
                    cssChecks: cssChecks,
                    alternatives: alternatives,
                    versionAlternatives: versionAlternatives,
                    nonVersionAlternatives: nonVersionAlternatives,
                    faceLabels: faceLabels,
                    sourceStylesheets: sourceStylesheets,
                    googleFaces: googleFaces,
                    requestEvidence: requestEvidence,
                    manualPreloadSuppressedChecks: manualPreloadSuppressedChecks,
                    manualPreloadNotSuppressedChecks: manualPreloadNotSuppressedChecks,
                    unresolvedGoogleStylesheets: unresolvedGoogleStylesheets,
                    browserCompatibility: browserCompatibility,
                    browserSpecificAdvisory: browserSpecificAdvisory,
                    successCount: totalChecks,
                    failedCount: failedCount,
                    scheduledChecks: scheduledChecks,
                    failedResults: failedResults,
                    totalPages: totalPages,
                    usedPages: usedPages,
                    totalChecks: totalChecks,
                    usedChecks: usedChecks,
                    desktopTotal: desktopTotal,
                    desktopUsed: desktopUsed,
                    mobileTotal: mobileTotal,
                    mobileUsed: mobileUsed,
                    pageCoveragePercent: percentage(usedPages, totalPages),
                    checkCoveragePercent: percentage(usedChecks, totalChecks)
                };

                if (entry.duplicateOf !== null && typeof entry.duplicateOf !== 'undefined') {
                    classification.status = 'safe';
                    classification.reason = translate('duplicateEntryReason');
                    classification.removable = true;
                    classification.removalType = 'deterministic';
                    classification.selected = true;
                    return classification;
                }

                if (!entry.valid) {
                    if (entry.invalidCode === 'wrong_google_host') {
                        classification.status = 'review';
                        classification.reason = translate('wrongGoogleHostReason');
                        classification.selected = false;
                    } else {
                        classification.status = 'safe';
                        classification.reason = translate('invalidReason');
                        classification.removable = true;
                        classification.removalType = 'deterministic';
                        classification.selected = true;
                    }
                    return classification;
                }

                if (!totalChecks) {
                    if (hasIncompletePayload) {
                        classification.status = 'review';
                        classification.statusLabel = translate('incompleteReviewStatus');
                        classification.reason = translate('failedReason') + ' ' + translate('partialSuffix', {
                            failed: failedCount,
                            total: scheduledChecks
                        });
                    } else {
                        classification.status = 'unknown';
                        classification.reason = translate('failedReason');
                    }

                    classification.selected = false;
                    return classification;
                }

                // Any failed page/viewport check makes site-wide suitability
                // incomplete. Deterministic field-level findings above remain safe,
                // but browser-based findings are always left for review.
                if (failedCount) {
                    classification.status = 'review';
                    classification.statusLabel = translate('incompleteReviewStatus');
                    classification.selected = false;

                    if (preloadChecks.length === totalChecks) {
                        classification.reason = translate('duplicateAllReason');
                    } else if (preloadChecks.length > 0) {
                        classification.reason = translate('duplicateSomeReason');
                    } else if (usedChecks === totalChecks) {
                        classification.reason = translate('requestedAllReason', {
                            count: usedChecks,
                            total: totalChecks,
                            threshold: percentage(LIKELY_SITE_WIDE_MIN_CHECK_COVERAGE, 1)
                        });
                    } else if (usedChecks > 0) {
                        classification.reason = translate('requestedSelectiveReason', {
                            usedPages: usedPages,
                            totalPages: totalPages,
                            usedChecks: usedChecks,
                            totalChecks: totalChecks
                        });
                    } else if (provider === 'google' && googleFaces.length) {
                        classification.reason = translate('googleCssReason');
                    } else if (cssChecks.length) {
                        classification.reason = translate('cssReason');
                    } else {
                        classification.reason = provider === 'google'
                            ? translate('googleNotDetectedReason')
                            : translate('notDetectedReason');
                    }

                    if (secondaryEvidenceSuffix) {
                        classification.reason += ' ' + secondaryEvidenceSuffix;
                    }

                    if (googleSemanticSuffix) {
                        classification.reason += ' ' + googleSemanticSuffix;
                    }

                    classification.reason += ' ' + translate('partialSuffix', {
                        failed: failedCount,
                        total: scheduledChecks
                    });

                    return classification;
                }

                if (preloadChecks.length === totalChecks) {
                    classification.status = 'safe';
                    classification.reason = translate('duplicateAllReason');
                    classification.removable = true;
                    classification.removalType = 'deterministic';
                    classification.selected = true;
                    return classification;
                }

                if (preloadChecks.length > 0) {
                    classification.status = 'review';
                    classification.reason = translate('duplicateSomeReason');
                    classification.selected = false;
                    return classification;
                }

                if (usedChecks > 0) {
                    var missingChecks = totalChecks - usedChecks;
                    var usageReasonReplacements = {
                        usedPages: usedPages,
                        totalPages: totalPages,
                        usedChecks: usedChecks,
                        totalChecks: totalChecks,
                        missingChecks: missingChecks,
                        percent: percentage(usedChecks, totalChecks),
                        threshold: percentage(LIKELY_SITE_WIDE_MIN_CHECK_COVERAGE, 1)
                    };

                    if (likelySiteWideCandidate) {
                        classification.status = browserSpecificAdvisory ? 'broad' : 'keep';
                        if (browserSpecificAdvisory) {
                            classification.statusLabel = translate('broadBrowserSpecificStatus');
                        }
                        classification.removable = false;

                        if (usedChecks === totalChecks) {
                            classification.reason = translate('requestedAllReason', {
                                count: usedChecks,
                                total: totalChecks,
                                threshold: percentage(LIKELY_SITE_WIDE_MIN_CHECK_COVERAGE, 1)
                            });
                        } else {
                            classification.reason = translate(
                                missingChecks === 1
                                    ? 'requestedLikelyReasonOneMissing'
                                    : 'requestedLikelyReasonManyMissing',
                                usageReasonReplacements
                            );
                        }

                        if (secondaryEvidenceSuffix) {
                            classification.reason += ' ' + secondaryEvidenceSuffix;
                        }

                        if (googleSemanticSuffix) {
                            classification.reason += ' ' + googleSemanticSuffix;
                        }

                        if (samePathRequestChecks.length) {
                            classification.reason += ' ' + translate('samePathRequestSuffix', {
                                count: samePathRequestChecks.length,
                                total: totalChecks
                            });
                        }

                        if (browserSpecificAdvisory) {
                            classification.reason += ' ' + translate('browserSpecificAdvisoryReason', {
                                exact: browserCompatibility.exactProfileCount || 0,
                                total: browserCompatibility.testedProfileCount || 0
                            });
                        }

                        return classification;
                    }

                    if (broadUsageCandidate) {
                        classification.status = 'broad';
                        if (browserSpecificAdvisory) {
                            classification.statusLabel = translate('broadBrowserSpecificStatus');
                        }
                        classification.selected = false;
                        classification.reason = translate(
                            missingChecks === 1
                                ? 'requestedBroadReasonOneMissing'
                                : 'requestedBroadReasonManyMissing',
                            usageReasonReplacements
                        );

                        if (secondaryEvidenceSuffix) {
                            classification.reason += ' ' + secondaryEvidenceSuffix;
                        }

                        if (googleSemanticSuffix) {
                            classification.reason += ' ' + googleSemanticSuffix;
                        }

                        if (samePathRequestChecks.length) {
                            classification.reason += ' ' + translate('samePathRequestSuffix', {
                                count: samePathRequestChecks.length,
                                total: totalChecks
                            });
                        }

                        if (browserSpecificAdvisory) {
                            classification.reason += ' ' + translate('browserSpecificAdvisoryReason', {
                                exact: browserCompatibility.exactProfileCount || 0,
                                total: browserCompatibility.testedProfileCount || 0
                            });
                        }

                        return classification;
                    }

                    classification.status = 'selective';
                    classification.reason = translate('requestedSelectiveReason', usageReasonReplacements);

                    var lowCoverageCandidate = totalPages >= 4 && totalChecks >= 6 &&
                        usedPages === 1 && usedPages < totalPages &&
                        (usedPages / totalPages) <= 0.25 && (usedChecks / totalChecks) <= 0.25;
                    var expectedNegativeChecks = Math.max(0, totalChecks - usedChecks);
                    var strongPositiveChecks = directRequestChecks.length + computedStyleChecks.length;
                    var highConfidencePoorCandidate = provider === 'local' && lowCoverageCandidate &&
                        expectedNegativeChecks > 0 && stableExactNotRequestedChecks.length === expectedNegativeChecks &&
                        strongPositiveChecks === usedChecks && fontFaceChecks.length === 0 &&
                        googleSemanticChecks.length === 0 && samePathRequestChecks.length === 0 &&
                        ownPreloadChecks.length === 0 && preloadChecks.length === 0;

                    if (highConfidencePoorCandidate) {
                        // Coverage is advisory only. Even a strong one-page result
                        // can miss an interaction, language, logged-in state, or a
                        // later template change. Only deterministic field-level
                        // findings receive a cleanup checkbox.
                        classification.reason += ' ' + translate('poorCandidateReviewSuffix');
                    } else {
                        classification.reason += ' ' + translate('selectiveReviewSuffix');

                        if (lowCoverageCandidate && (fontFaceChecks.length > 0 || googleSemanticChecks.length > 0 ||
                            stableExactNotRequestedChecks.length !== expectedNegativeChecks)) {
                            classification.reason += ' ' + translate('fontFaceSelectionReviewSuffix');
                        }
                    }

                    if (secondaryEvidenceSuffix) {
                        classification.reason += ' ' + secondaryEvidenceSuffix;
                    }

                    if (googleSemanticSuffix) {
                        classification.reason += ' ' + googleSemanticSuffix;
                    }

                    if (samePathRequestChecks.length) {
                        classification.reason += ' ' + translate('samePathRequestSuffix', {
                            count: samePathRequestChecks.length,
                            total: totalChecks
                        });
                    }

                    if (browserSpecificAdvisory) {
                        classification.reason += ' ' + translate('browserSpecificAdvisoryReason', {
                            exact: browserCompatibility.exactProfileCount || 0,
                            total: browserCompatibility.testedProfileCount || 0
                        });
                    }

                    return classification;
                }

                if (provider === 'local' && entry.localFileStatus === 'missing' && !cssChecks.length) {
                    classification.status = 'safe';
                    classification.reason = translate('missingReason');
                    classification.removable = true;
                    classification.removalType = 'deterministic';
                    classification.selected = true;
                    return classification;
                }

                if (provider === 'local' && versionAlternatives.length && !nonVersionAlternatives.length) {
                    classification.status = 'safe';
                    classification.reason = translate('replacedReason');
                    classification.removable = true;
                    classification.removalType = 'deterministic';
                    classification.selected = true;
                    return classification;
                }

                if (provider === 'local' && alternatives.length) {
                    classification.status = 'review';
                    classification.reason = translate('queryVariantReason');
                    classification.selected = false;
                    return classification;
                }

                if (provider === 'google' && googleFaces.length) {
                    classification.status = 'review';
                    classification.reason = translate('googleCssReason');
                    classification.selected = false;

                    if (googleSemanticSuffix) {
                        classification.reason += ' ' + googleSemanticSuffix;
                    }

                    return classification;
                }

                if (cssChecks.length) {
                    classification.status = 'review';
                    classification.reason = translate('cssReason');
                    classification.selected = false;
                    return classification;
                }

                classification.status = 'review';
                classification.reason = provider === 'google'
                    ? translate('googleNotDetectedReason')
                    : translate('notDetectedReason');
                classification.selected = false;

                if (googleSemanticSuffix) {
                    classification.reason += ' ' + googleSemanticSuffix;
                }

                return classification;
            });
        }

        function addMetric(metrics, label, value) {
            if (!value) {
                return;
            }

            var metric = createElement('div', 'wpacu-font-preload-result__metric');
            metric.appendChild(createElement('span', '', label));
            metric.appendChild(createElement('strong', '', value));
            metrics.appendChild(metric);
        }

        function appendEvidenceGroup(container, label, values, extraClass) {
            values = uniqueValues(values || []).filter(Boolean);

            if (!values.length) {
                return;
            }

            var group = createElement(
                'div',
                'wpacu-font-preload-result__evidence' + (extraClass ? ' ' + extraClass : '')
            );
            group.appendChild(createElement('strong', '', label));
            var chips = createElement('div', 'wpacu-font-preload-result__chips');

            values.forEach(function (value) {
                chips.appendChild(createElement('span', '', value));
            });

            group.appendChild(chips);
            container.appendChild(group);
        }

        function getStatusLabel(classification) {
            return classification.statusLabel || {
                safe: translate('safeStatus'),
                keep: translate('keepStatus'),
                broad: translate('broadStatus'),
                selective: translate('selectiveStatus'),
                review: translate('reviewStatus'),
                unknown: translate('unknownStatus')
            }[classification.status];
        }

        function getCoverageSummary(classification) {
            if (!classification.totalChecks) {
                return translate('coverageUnavailable');
            }

            return translate('compactCoverage', {
                usedPages: classification.usedPages,
                totalPages: classification.totalPages,
                usedChecks: classification.usedChecks,
                totalChecks: classification.totalChecks
            });
        }

        function getCompactResultCopy(classification) {
            var replacements = {
                usedPages: classification.usedPages,
                totalPages: classification.totalPages,
                usedChecks: classification.usedChecks,
                totalChecks: classification.totalChecks,
                failed: classification.failedCount,
                scheduled: classification.scheduledChecks
            };

            if (classification.status === 'keep') {
                return translate('compactKeepReason', replacements);
            }

            if (classification.status === 'broad') {
                return translate(
                    classification.browserSpecificAdvisory
                        ? 'compactBroadBrowserSpecificReason'
                        : 'compactBroadReason',
                    replacements
                );
            }

            if (classification.status === 'selective') {
                return translate(
                    classification.browserSpecificAdvisory
                        ? 'compactSelectiveBrowserSpecificReason'
                        : 'compactSelectiveReason',
                    replacements
                );
            }

            if (classification.status === 'safe') {
                return translate('compactSafeReason');
            }

            if (classification.status === 'unknown') {
                return translate('compactUnknownReason');
            }

            if (classification.failedCount) {
                return translate('compactIncompleteReason', replacements);
            }

            return translate('compactReviewReason');
        }

        function getStatusIcon(classification) {
            return {
                safe: '−',
                keep: '✓',
                broad: '≈',
                selective: '↔',
                review: '!',
                unknown: '?'
            }[classification.status] || '•';
        }

        function getRequestSourceLabel(requestEvidence) {
            var initiators = (requestEvidence.initiatorTypes || []).map(function (initiator) {
                return String(initiator || '').toLowerCase();
            });

            if (requestEvidence.ownPreloadPresent) {
                return translate('requestSourceOwnPreload');
            }

            if (requestEvidence.preloadedElsewhere) {
                return translate('requestSourceOtherPreload');
            }

            if (initiators.indexOf('css') !== -1) {
                return translate('requestSourceCss');
            }

            if (initiators.indexOf('link') !== -1) {
                return translate('requestSourceLink');
            }

            if (initiators.some(function (initiator) {
                return ['script', 'fetch', 'xmlhttprequest'].indexOf(initiator) !== -1;
            })) {
                return translate('requestSourceScript');
            }

            return translate('requestSourceOther');
        }

        function getRequestTechnicalSummary(requestEvidence) {
            var parts = [];
            var initiators = uniqueValues(requestEvidence.initiatorTypes || []).filter(Boolean);
            var captureSources = uniqueValues(requestEvidence.captureSources || []).filter(Boolean);
            var firstResource = requestEvidence.resourceEntries && requestEvidence.resourceEntries.length
                ? requestEvidence.resourceEntries[0]
                : null;

            if (initiators.length) {
                parts.push(translate('requestInitiatorValue', { value: initiators.join(', ') }));
            }

            if (firstResource && firstResource.deliveryType) {
                parts.push(translate('requestDeliveryValue', { value: firstResource.deliveryType }));
            } else if (firstResource && Number(firstResource.transferSize) === 0) {
                parts.push(translate('requestCacheReuse'));
            }

            if (firstResource && firstResource.duration !== null && typeof firstResource.duration !== 'undefined') {
                parts.push(translate('requestDurationValue', { value: Math.max(0, Number(firstResource.duration || 0)) }));
            }

            if (captureSources.length) {
                parts.push(translate('requestCapturedViaValue', { value: captureSources.join(', ') }));
            }

            return parts.join(' · ');
        }

        function appendRequestProvenance(detailsBody, classification) {
            var evidence = Array.isArray(classification.requestEvidence) ? classification.requestEvidence : [];
            var suppressedChecks = classification.manualPreloadSuppressedChecks || [];
            var unsuppressedChecks = classification.manualPreloadNotSuppressedChecks || [];

            if (!classification.totalChecks) {
                return;
            }

            var section = createElement('section', 'wpacu-font-preload-result__provenance');
            section.appendChild(createElement('h6', '', translate('requestProvenance')));

            var suppressionCopy = unsuppressedChecks.length
                ? translate('manualPreloadSuppressionIncomplete', {
                    suppressed: suppressedChecks.length,
                    total: classification.totalChecks
                })
                : translate('manualPreloadSuppressionComplete', {
                    suppressed: suppressedChecks.length,
                    total: classification.totalChecks
                });
            section.appendChild(createElement(
                'p',
                'wpacu-font-preload-result__provenance-summary' + (unsuppressedChecks.length ? ' is-warning' : ' is-ok'),
                suppressionCopy
            ));

            if (evidence.length) {
                var rows = createElement('div', 'wpacu-font-preload-result__provenance-rows');

                evidence.forEach(function (requestEvidence) {
                    var row = createElement('div', 'wpacu-font-preload-result__provenance-row');
                    var check = createElement('div', 'wpacu-font-preload-result__provenance-check');
                    check.appendChild(createElement('strong', '', requestEvidence.label));

                    var technicalSummary = getRequestTechnicalSummary(requestEvidence);
                    if (technicalSummary) {
                        check.appendChild(createElement('small', '', technicalSummary));
                    }

                    var source = createElement('span', 'wpacu-font-preload-result__provenance-source', getRequestSourceLabel(requestEvidence));
                    var accepted = createElement(
                        'span',
                        'wpacu-font-preload-result__provenance-state ' + (requestEvidence.acceptedNaturalUse ? 'is-yes' : 'is-no'),
                        requestEvidence.acceptedNaturalUse
                            ? translate('naturalUseAccepted')
                            : translate('naturalUseNotAccepted')
                    );
                    var suppressed = createElement(
                        'span',
                        'wpacu-font-preload-result__provenance-state ' + (requestEvidence.manualPreloadSuppressed ? 'is-yes' : 'is-no'),
                        requestEvidence.manualPreloadSuppressed
                            ? translate('manualPreloadSuppressedYes')
                            : translate('manualPreloadSuppressedNo')
                    );

                    row.appendChild(check);
                    row.appendChild(source);
                    row.appendChild(accepted);
                    row.appendChild(suppressed);
                    rows.appendChild(row);
                });

                section.appendChild(rows);
            } else {
                section.appendChild(createElement('p', 'wpacu-font-preload-result__provenance-empty', translate('noExactRequestProvenance')));
            }

            detailsBody.appendChild(section);
        }

        function createGoogleResolutionFailureItem(failure) {
            failure = normaliseGoogleResolutionFailure(failure, '');
            var item = createElement('div', 'wpacu-font-preload-resolver-failure');
            item.appendChild(createElement(
                'code',
                'wpacu-font-preload-resolver-failure__url',
                failure.url || translate('googleResolverRequestLabel')
            ));
            item.appendChild(createElement('p', 'wpacu-font-preload-resolver-failure__message', failure.message));

            var metadata = createElement('div', 'wpacu-font-preload-resolver-failure__meta');
            metadata.appendChild(createElement('span', '', translate('resolverErrorCode', { value: failure.code })));

            if (failure.httpStatus) {
                metadata.appendChild(createElement('span', '', translate('resolverHttpStatus', { value: failure.httpStatus })));
            }

            metadata.appendChild(createElement('span', '', translate('resolverAttempts', { value: failure.attempts || 1 })));

            if (failure.redirects) {
                metadata.appendChild(createElement('span', '', translate('resolverRedirects', { value: failure.redirects })));
            }

            if (failure.timeoutSeconds) {
                metadata.appendChild(createElement('span', '', translate('resolverTimeout', { value: failure.timeoutSeconds })));
            }

            item.appendChild(metadata);

            if (failure.finalUrl && failure.finalUrl !== failure.url) {
                var finalUrl = createElement('div', 'wpacu-font-preload-resolver-failure__final');
                finalUrl.appendChild(createElement('strong', '', translate('resolverFinalUrlLabel')));
                finalUrl.appendChild(createElement('code', '', failure.finalUrl));
                item.appendChild(finalUrl);
            }

            return item;
        }

        function retryGoogleStylesheetResolution(scan, retryButton) {
            if (!scan || provider !== 'google' || resolveRequest) {
                return;
            }

            var failures = scan.googleResolution && Array.isArray(scan.googleResolution.failedStylesheets)
                ? scan.googleResolution.failedStylesheets
                : [];
            var retryableFailures = failures.filter(function (failure) {
                return isGoogleResolutionFailureRetryable(failure);
            });
            var failedUrls = uniqueValues(retryableFailures.map(function (failure) {
                return failure && failure.url ? failure.url : '';
            })).filter(Boolean);
            var urlsToRetry = failedUrls;
            var allStylesheetUrls = getCollectedGoogleStylesheetUrls(scan);
            var permanentFailedUrls = uniqueValues(failures.filter(function (failure) {
                return !isGoogleResolutionFailureRetryable(failure);
            }).map(function (failure) {
                return failure && failure.url ? failure.url : '';
            })).filter(Boolean);
            var urlsToResolve = allStylesheetUrls.filter(function (stylesheetUrl) {
                return permanentFailedUrls.indexOf(stylesheetUrl) === -1;
            });

            // A transport/AJAX failure can have no concrete URL attached. In
            // that case, retry the complete observed set except URLs already
            // known to be permanently invalid.
            if (!urlsToRetry.length && retryableFailures.length) {
                urlsToRetry = urlsToResolve.slice();
            }

            if (!retryableFailures.length || !urlsToResolve.length) {
                return;
            }

            retryButton.disabled = true;
            retryButton.textContent = translate('retryingGoogleResolution');

            // Recalculate compatibility from the complete usable stylesheet
            // set. Permanently invalid URLs are intentionally excluded; retrying
            // only one transient failure would otherwise produce a partial map
            // that could overwrite valid compatibility evidence for unrelated
            // Heebo/Playfair/etc. files.
            resolveGoogleStylesheets(scan, urlsToResolve).then(function (nextResolution) {
                scan.googleResolution = mergeGoogleResolutions(scan.googleResolution, nextResolution, urlsToRetry);
                scan.googleResolutionRetryCount = Number(scan.googleResolutionRetryCount || 0) + 1;
                finishProgress(scan);
                renderResults(scan);

                var remaining = scan.googleResolution && Array.isArray(scan.googleResolution.failedStylesheets)
                    ? scan.googleResolution.failedStylesheets.length
                    : 0;
                var resolutionImpact = getGoogleResolutionImpact(scan);
                var feedbackMessage = translate('googleResolutionRetrySucceeded');
                var feedbackType = 'success';

                if (remaining) {
                    if (resolutionImpact.affected === 0 && resolutionImpact.retryable === 0) {
                        feedbackMessage = translate('googleResolutionRetrySucceededIgnoredPermanent', { count: remaining });
                    } else {
                        feedbackMessage = resolutionImpact.affected === 0
                            ? translate('googleResolutionRetryUnrelatedStillFailed', { count: remaining })
                            : translate('googleResolutionRetryStillFailed', { count: remaining });
                        feedbackType = 'warning';
                    }
                }

                showFeedback(feedbackMessage, feedbackType);
            });
        }

        function appendGoogleResolutionDiagnostics(container, scan, includeRetry) {
            var resolution = scan && scan.googleResolution ? scan.googleResolution : null;
            var failures = resolution && Array.isArray(resolution.failedStylesheets)
                ? resolution.failedStylesheets
                : [];
            var hasRetryableFailures = failures.some(function (failure) {
                return isGoogleResolutionFailureRetryable(failure);
            });
            var hasPermanentFailures = failures.some(function (failure) {
                return !isGoogleResolutionFailureRetryable(failure);
            });

            if (!failures.length) {
                return;
            }

            var details = createElement('details', 'wpacu-font-preload-resolver-details');
            details.appendChild(createElement('summary', '', translate('failedStylesheetDetails')));
            var body = createElement('div', 'wpacu-font-preload-resolver-details__body');

            failures.forEach(function (failure) {
                body.appendChild(createGoogleResolutionFailureItem(failure));
            });

            if (resolution.browserUserAgent) {
                var userAgent = createElement('div', 'wpacu-font-preload-resolver-details__ua');
                userAgent.appendChild(createElement('strong', '', translate('resolverBrowserUserAgent')));
                userAgent.appendChild(createElement('code', '', resolution.browserUserAgent));
                body.appendChild(userAgent);
            }

            if (includeRetry && hasRetryableFailures) {
                var retryButton = createElement('button', 'button button-secondary wpacu-font-preload-resolver-details__retry', translate('retryGoogleResolution'));
                retryButton.type = 'button';
                retryButton.addEventListener('click', function () {
                    retryGoogleStylesheetResolution(scan, retryButton);
                });
                body.appendChild(retryButton);
            }

            if (includeRetry && hasPermanentFailures) {
                body.appendChild(createElement(
                    'p',
                    'wpacu-font-preload-resolver-failure__message',
                    translate('googleResolutionPermanentFailure')
                ));
            }

            details.appendChild(body);
            container.appendChild(details);
        }

        function renderGlobalResultNotice(scan, classifications) {
            if (!globalNotice) {
                return;
            }

            var stats = getScanStats(scan);
            var messages = [];

            if (stats.browserWarnings === 1) {
                messages.push(translate('globalIncompleteOne'));
            } else if (stats.browserWarnings > 1) {
                messages.push(translate('globalIncompleteMany', { count: stats.browserWarnings }));
            }

            var googleResolutionImpact = getGoogleResolutionImpact(scan);

            if (stats.googleStylesheetFailures === 1) {
                if (googleResolutionImpact.affected > 0) {
                    messages.push(translate('globalGoogleResolveOne'));
                } else if (googleResolutionImpact.permanent === googleResolutionImpact.total) {
                    messages.push(translate('globalGoogleResolveIgnoredOne'));
                } else {
                    messages.push(translate('globalGoogleResolveUnrelatedOne'));
                }
            } else if (stats.googleStylesheetFailures > 1) {
                if (googleResolutionImpact.affected > 0) {
                    messages.push(translate('globalGoogleResolveMany', {
                        count: stats.googleStylesheetFailures
                    }));
                } else if (googleResolutionImpact.permanent === googleResolutionImpact.total) {
                    messages.push(translate('globalGoogleResolveIgnoredMany', {
                        count: stats.googleStylesheetFailures
                    }));
                } else {
                    messages.push(translate('globalGoogleResolveUnrelatedMany', {
                        count: stats.googleStylesheetFailures
                    }));
                }
            }

            var protectedCount = classifications.filter(function (classification) {
                return !classification.removable;
            }).length;

            if (!messages.length && protectedCount === classifications.length && classifications.length) {
                messages.push(translate('globalNoRemovalRecommendation'));
            }

            globalNotice.hidden = !messages.length;
            globalNotice.className = 'wpacu-font-preload-results__notice js-wpacu-font-preload-global-notice' +
                ((stats.browserWarnings || googleResolutionImpact.affected || googleResolutionImpact.retryable)
                    ? ' is-warning'
                    : ' is-info');
            globalNotice.textContent = '';

            if (messages.length) {
                globalNotice.appendChild(createElement('div', 'wpacu-font-preload-results__notice-copy', messages.join(' ')));
            }

            if (stats.googleStylesheetFailures) {
                appendGoogleResolutionDiagnostics(globalNotice, scan, true);
            }
        }

        function appendTechnicalDetails(detailsBody, classification) {
            if (classification.totalChecks > 0) {
                var metrics = createElement('div', 'wpacu-font-preload-result__metrics');
                addMetric(metrics, translate('pageCoverage'), coverageText(classification.usedPages, classification.totalPages));
                addMetric(metrics, translate('checkCoverage'), coverageText(classification.usedChecks, classification.totalChecks));

                if (classification.desktopTotal) {
                    addMetric(metrics, translate('desktopCoverage'), coverageText(classification.desktopUsed, classification.desktopTotal));
                }

                if (classification.mobileTotal) {
                    addMetric(metrics, translate('mobileCoverage'), coverageText(classification.mobileUsed, classification.mobileTotal));
                }

                if (classification.failedCount) {
                    addMetric(metrics, translate('checksFailed'), translate('failedCoverageValue', {
                        failed: classification.failedCount,
                        total: classification.scheduledChecks
                    }));
                }

                detailsBody.appendChild(metrics);
            }

            appendRequestProvenance(detailsBody, classification);

            appendEvidenceGroup(detailsBody, translate('detectedOn'), classification.requestedChecks);
            appendEvidenceGroup(detailsBody, translate('exactRequestOn'), classification.directRequestChecks);
            appendEvidenceGroup(detailsBody, translate('computedStyleEvidence'), classification.computedStyleChecks);
            appendEvidenceGroup(detailsBody, translate('fontFaceEvidence'), classification.fontFaceChecks);
            appendEvidenceGroup(detailsBody, translate('googleSemanticEvidence'), classification.googleSemanticChecks);
            appendEvidenceGroup(detailsBody, translate('exactNotRequestedOn'), classification.stableExactNotRequestedChecks, 'is-negative');
            appendEvidenceGroup(detailsBody, translate('samePathRequestOn'), classification.samePathRequestChecks, 'is-review');
            appendEvidenceGroup(detailsBody, translate('ownPreloadUnexpectedOn'), classification.ownPreloadChecks, 'is-review');
            appendEvidenceGroup(detailsBody, translate('incompleteOn'), classification.incompleteChecks, 'is-review');
            appendEvidenceGroup(detailsBody, translate('preloadedElsewhereOn'), classification.preloadChecks);

            if (provider === 'google' && classification.browserCompatibility && Array.isArray(classification.browserCompatibility.profiles)) {
                var compatibilitySection = createElement('section', 'wpacu-font-preload-result__evidence is-source');
                compatibilitySection.appendChild(createElement('strong', '', translate('browserCssCompatibility')));
                compatibilitySection.appendChild(createElement('p', '', translate('browserCssCompatibilityHelp')));
                var compatibilityValues = createElement('div', 'wpacu-font-preload-result__sources');

                classification.browserCompatibility.profiles.forEach(function (profile) {
                    if (!profile) {
                        return;
                    }

                    var label = profile.label || profile.id || translate('currentBrowser');
                    var message = '';
                    if (profile.exactUrlReturned) {
                        message = translate('browserCssExactMatch');
                    } else if (profile.reliable === false) {
                        message = translate('browserCssUnknown');
                    } else if (profile.alternativeUrls && profile.alternativeUrls.length) {
                        message = translate('browserCssAlternativeMatch', { count: profile.alternativeUrls.length });
                    } else {
                        message = translate('browserCssNoMatch');
                    }

                    var row = createElement('div', 'wpacu-font-preload-result__source');
                    row.appendChild(createElement('strong', '', label + ': '));
                    row.appendChild(document.createTextNode(message));

                    if (profile.alternativeUrls && profile.alternativeUrls.length) {
                        var alternativesNode = createElement('div', 'wpacu-font-preload-result__sources');
                        profile.alternativeUrls.slice(0, 3).forEach(function (url) {
                            alternativesNode.appendChild(createElement('code', 'wpacu-font-preload-result__source', url));
                        });
                        row.appendChild(alternativesNode);
                    }

                    compatibilityValues.appendChild(row);
                });

                compatibilitySection.appendChild(compatibilityValues);
                detailsBody.appendChild(compatibilitySection);
            }

            if (classification.unresolvedGoogleStylesheets && classification.unresolvedGoogleStylesheets.length) {
                var unresolvedSection = createElement('section', 'wpacu-font-preload-result__resolver-impact');
                unresolvedSection.appendChild(createElement('h6', '', translate('unresolvedStylesheets')));
                unresolvedSection.appendChild(createElement('p', '', translate('unresolvedStylesheetHelp')));
                classification.unresolvedGoogleStylesheets.forEach(function (failure) {
                    unresolvedSection.appendChild(createGoogleResolutionFailureItem(failure));
                });
                detailsBody.appendChild(unresolvedSection);
            }

            if (classification.verificationChecks.length) {
                var verificationEvidence = createElement('section', 'wpacu-font-preload-result__verification');
                verificationEvidence.appendChild(createElement('h6', '', translate('manualVerificationSummary')));
                verificationEvidence.appendChild(createElement('p', '', translate('manualVerificationHelp')));
                var verificationLinks = createElement('div', 'wpacu-font-preload-result__verification-links');

                classification.verificationChecks.forEach(function (verificationCheck) {
                    if (!verificationCheck || !verificationCheck.url) {
                        return;
                    }

                    var verificationLink = createElement('a', 'button button-small', verificationCheck.label);
                    verificationLink.href = verificationCheck.url;
                    verificationLink.target = '_blank';
                    verificationLink.rel = 'noopener noreferrer';
                    verificationLink.title = translate('openVerificationPageTitle');
                    verificationLinks.appendChild(verificationLink);
                });

                verificationEvidence.appendChild(verificationLinks);
                detailsBody.appendChild(verificationEvidence);
            }

            if (classification.faceLabels.length) {
                var variations = createElement('div', 'wpacu-font-preload-result__evidence');
                variations.appendChild(createElement('strong', '', translate('variant')));
                var variationValues = createElement('div', 'wpacu-font-preload-result__chips');
                classification.faceLabels.slice(0, 8).forEach(function (faceLabel) {
                    variationValues.appendChild(createElement('span', 'wpacu-font-preload-result__face', faceLabel));
                });
                variations.appendChild(variationValues);
                detailsBody.appendChild(variations);
            }

            if (classification.sourceStylesheets.length) {
                var stylesheets = createElement('div', 'wpacu-font-preload-result__evidence is-source');
                stylesheets.appendChild(createElement('strong', '', translate('sourceStylesheet')));
                var stylesheetValues = createElement('div', 'wpacu-font-preload-result__sources');
                classification.sourceStylesheets.slice(0, 3).forEach(function (stylesheetUrl) {
                    stylesheetValues.appendChild(createElement('code', 'wpacu-font-preload-result__source', stylesheetUrl));
                });
                stylesheets.appendChild(stylesheetValues);
                detailsBody.appendChild(stylesheets);
            }

            if (classification.versionAlternatives.length) {
                var replacement = createElement('div', 'wpacu-font-preload-result__evidence is-source');
                replacement.appendChild(createElement('strong', '', translate('replacementUrl')));
                var replacementValues = createElement('div', 'wpacu-font-preload-result__sources');
                replacementValues.appendChild(createElement(
                    'code',
                    'wpacu-font-preload-result__source',
                    classification.versionAlternatives[0]
                ));
                replacement.appendChild(replacementValues);
                detailsBody.appendChild(replacement);
            }
        }

        function renderResults(scan) {
            var classifications = aggregateResults(scan);
            var counts = { safe: 0, keep: 0, broad: 0, selective: 0, review: 0, unknown: 0 };
            var removableCount = 0;

            classifications.forEach(function (classification) {
                counts[classification.status] = (counts[classification.status] || 0) + 1;
                if (classification.removable) {
                    removableCount++;
                }
            });

            if (summary) {
                summary.textContent = '';

                var broadSummaryLabels = uniqueValues(classifications.filter(function (classification) {
                    return classification.status === 'broad';
                }).map(function (classification) {
                    return getStatusLabel(classification);
                }));
                var broadSummaryLabel = broadSummaryLabels.length === 1
                    ? broadSummaryLabels[0]
                    : translate('broadStatus');

                [
                    ['keep', translate('keepStatus')],
                    ['broad', broadSummaryLabel],
                    ['selective', translate('selectiveStatus')],
                    ['safe', translate('safeStatus')],
                    ['review', translate('reviewStatus')],
                    ['unknown', translate('unknownStatus')]
                ].forEach(function (item) {
                    if (!counts[item[0]]) {
                        return;
                    }

                    var summaryItem = createElement('span', 'is-' + item[0]);
                    summaryItem.appendChild(createElement('strong', '', String(counts[item[0]])));
                    summaryItem.appendChild(document.createTextNode(' ' + item[1]));
                    summary.appendChild(summaryItem);
                });
            }

            renderGlobalResultNotice(scan, classifications);
            resultsList.textContent = '';

            classifications.forEach(function (classification) {
                var card = createElement('article', 'wpacu-font-preload-result is-' + classification.status);
                var header = createElement('div', 'wpacu-font-preload-result__header');
                var action = createElement('div', 'wpacu-font-preload-result__action');
                var statusLabel = getStatusLabel(classification);

                if (classification.removable) {
                    var checkboxLabel = createElement('label', 'wpacu-font-preload-result__selection');
                    var checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'js-wpacu-font-preload-result-checkbox';
                    checkbox.checked = !!classification.selected;
                    checkbox.setAttribute('data-line-index', String(classification.entry.lineIndex));
                    checkbox.setAttribute('aria-label', translate('selectForRemoval', {
                        status: statusLabel,
                        url: classification.entry.original
                    }));
                    checkboxLabel.appendChild(checkbox);
                    action.appendChild(checkboxLabel);
                } else {
                    var protectedIcon = createElement('span', 'wpacu-font-preload-result__state-icon', getStatusIcon(classification));
                    protectedIcon.setAttribute('aria-hidden', 'true');
                    action.appendChild(protectedIcon);
                }

                var main = createElement('div', 'wpacu-font-preload-result__main');
                var titleLine = createElement('div', 'wpacu-font-preload-result__title-line');
                titleLine.appendChild(createElement('span', 'wpacu-font-preload-result__status', statusLabel));
                titleLine.appendChild(createElement('span', 'wpacu-font-preload-result__coverage', getCoverageSummary(classification)));
                main.appendChild(titleLine);
                main.appendChild(createElement('code', 'wpacu-font-preload-result__url', classification.entry.original));
                main.appendChild(createElement('p', 'wpacu-font-preload-result__compact-reason', getCompactResultCopy(classification)));

                if (classification.removable) {
                    main.appendChild(createElement(
                        'small',
                        'wpacu-font-preload-result__removal-note is-' + (classification.removalType || 'manual'),
                        translate(classification.removalType === 'deterministic'
                            ? 'deterministicRemovalNote'
                            : 'manualRemovalNote')
                    ));
                } else {
                    main.appendChild(createElement('small', 'wpacu-font-preload-result__removal-note is-protected', translate('protectedRemovalNote')));
                }

                header.appendChild(action);
                header.appendChild(main);
                card.appendChild(header);

                var details = createElement('details', 'wpacu-font-preload-result__details');
                var detailsSummary = createElement('summary', 'wpacu-font-preload-result__details-summary');
                detailsSummary.appendChild(createElement('span', '', translate('viewEvidence')));
                detailsSummary.appendChild(createElement('small', '', translate('viewEvidenceHint')));
                details.appendChild(detailsSummary);

                var detailsBody = createElement('div', 'wpacu-font-preload-result__details-body');
                detailsBody.appendChild(createElement('p', 'wpacu-font-preload-result__reason', classification.reason));
                appendTechnicalDetails(detailsBody, classification);
                details.appendChild(detailsBody);
                card.appendChild(details);
                resultsList.appendChild(card);
            });

            lastCompletedScan = {
                sourceValue: scan.sourceValue,
                classifications: classifications,
                scan: scan
            };

            if (results) {
                results.hidden = false;
            }

            if (resultsFooter) {
                resultsFooter.hidden = removableCount === 0;
            }

            updateSelectedCount();
        }

        function updateSelectedCount() {
            var checkboxes = Array.prototype.slice.call(resultsList.querySelectorAll('.js-wpacu-font-preload-result-checkbox:not(:disabled)'));
            var count = checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;

            if (selectedCount) {
                selectedCount.textContent = count ? translate('selectedCount', { count: count }) : '';
            }

            if (removeButton) {
                removeButton.disabled = count === 0;
            }
        }

        function removeNonEmptyLineIndexes(value, indexesToRemove) {
            var selected = Object.create(null);
            var nonEmptyIndex = 0;
            var output = [];

            indexesToRemove.forEach(function (index) {
                selected[String(index)] = true;
            });

            String(value || '').split(/\r\n|\r|\n/).forEach(function (line) {
                if (!line.trim()) {
                    output.push(line);
                    return;
                }

                if (!selected[String(nonEmptyIndex)]) {
                    output.push(line);
                }

                nonEmptyIndex++;
            });

            while (output.length && !output[output.length - 1].trim()) {
                output.pop();
            }

            return output.join('\n');
        }

        function startScan() {
            var fontLines = parseNonEmptyLines(textarea.value);

            if (!fontLines.length) {
                showFeedback(translate('noUrls'), 'error');
                return;
            }

            if (fontLines.length > Number(config.maxFontUrls || 50)) {
                showFeedback(translate('tooManyUrls', { max: Number(config.maxFontUrls || 50) }), 'error');
                return;
            }

            clearVisualOutput(false);
            setScanningState(true);
            prepareWasCancelled = false;
            showFeedback(translate('preparing'), 'info');

            prepareRequest = $.ajax({
                url: config.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: config.action,
                    nonce: config.nonce,
                    font_urls: textarea.value,
                    extra_scan_urls: extraUrls ? extraUrls.value : '',
                    language_code: config.languageCode || ''
                }
            }).done(function (response) {
                if (prepareWasCancelled) {
                    return;
                }

                if (!response || !response.success || !response.data || !Array.isArray(response.data.pages) ||
                    !Array.isArray(response.data.fontEntries)) {
                    var responseMessage = response && response.data && response.data.message
                        ? response.data.message
                        : translate('requestFailed');
                    showFeedback(responseMessage, 'error');
                    setScanningState(false);
                    return;
                }

                var views = Array.isArray(response.data.views) && response.data.views.length
                    ? response.data.views
                    : (Array.isArray(config.views) ? config.views : []);
                var tasks = [];

                response.data.pages.forEach(function (page) {
                    views.forEach(function (view) {
                        tasks.push({ page: page, view: view });
                    });
                });

                if (!tasks.length) {
                    showFeedback(translate('requestFailed'), 'error');
                    setScanningState(false);
                    return;
                }

                activeScan = {
                    token: response.data.token,
                    sourceValue: textarea.value,
                    fontEntries: response.data.fontEntries,
                    tasks: tasks,
                    taskResults: new Array(tasks.length),
                    cancelled: false,
                    cancelCurrentTask: null,
                    googleResolution: null
                };

                showFeedback('', '');
                renderTaskList(tasks);
                runTasks(activeScan);
            }).fail(function (xhr, statusText) {
                if (prepareWasCancelled || statusText === 'abort') {
                    return;
                }

                showFeedback(translate('requestFailed'), 'error');
                setScanningState(false);
            }).always(function () {
                prepareRequest = null;
            });
        }

        function getFailedTaskIndexes(scan) {
            var indexes = [];

            if (!scan || !Array.isArray(scan.tasks) || !Array.isArray(scan.taskResults)) {
                return indexes;
            }

            scan.tasks.forEach(function (task, taskIndex) {
                var taskResult = scan.taskResults[taskIndex];

                if (!taskResult || !taskResult.payload || taskResult.incomplete) {
                    indexes.push(taskIndex);
                }
            });

            return indexes;
        }

        function updateRetryFailedButton(scan) {
            if (!retryFailedButton) {
                return;
            }

            var failedIndexes = getFailedTaskIndexes(scan);
            retryFailedButton.hidden = !failedIndexes.length;
            retryFailedButton.disabled = !failedIndexes.length || !!activeScan;
            retryFailedButton.textContent = failedIndexes.length === 1
                ? translate('retryOneFailedCheck')
                : translate('retryFailedChecks', { count: failedIndexes.length });
        }

        function finaliseScan(scan) {
            finishProgress(scan);

            resolveGoogleStylesheets(scan).then(function (googleResolution) {
                if (scan.cancelled) {
                    finishCancelledScan();
                    return;
                }

                scan.googleResolution = googleResolution;
                scan.hasCompletedOnce = true;
                finishProgress(scan);
                renderResults(scan);
                activeScan = null;
                setScanningState(false);
                updateRetryFailedButton(scan);

                showFeedback('', '');
            });
        }

        function runTaskQueue(scan, taskIndexes, queuePosition, isRetryQueue) {
            if (!scan || scan.cancelled) {
                finishCancelledScan();
                return;
            }

            if (queuePosition >= taskIndexes.length) {
                finaliseScan(scan);
                return;
            }

            var taskIndex = taskIndexes[queuePosition];
            var task = scan.tasks[taskIndex];
            var previousTaskResult = scan.taskResults[taskIndex] || null;
            updateProgress(queuePosition, taskIndexes.length, task, taskIndex, isRetryQueue);

            executeTask(scan, task, taskIndex).then(function (taskResult) {
                if (isRetryQueue) {
                    taskResult = mergeRetriedTaskResult(previousTaskResult, taskResult);
                }

                scan.taskResults[taskIndex] = taskResult;

                if (taskResult.payload && taskResult.incomplete) {
                    markTask(taskIndex, 'warning', getIncompleteEvidenceMessage(
                        Math.max(1, taskResult.incompleteFontIndexes ? taskResult.incompleteFontIndexes.length : 0),
                        true,
                        taskResult.payload
                    ));
                } else if (taskResult.payload) {
                    var observation = getTaskObservation(taskResult);
                    var successDetail = observation.message;

                    if (isRetryQueue) {
                        successDetail += ' ' + translate('manualRetrySucceeded');
                    } else if (taskResult.recovered) {
                        successDetail += ' ' + translate(taskResult.confirmationRetried
                            ? 'observedAfterConfirmation'
                            : 'succeededAfterRetry');
                    }

                    markTask(taskIndex, observation.state, successDetail);
                } else {
                    var attempts = taskResult.attempts || 1;
                    var failedPrefix = attempts === 1
                        ? translate('failedAfterOneAttempt')
                        : translate('failedAfterAttempts', { attempts: attempts });
                    markTask(taskIndex, 'failed', failedPrefix + (taskResult.error ? ' ' + taskResult.error : ''));
                }

                runTaskQueue(scan, taskIndexes, queuePosition + 1, isRetryQueue);
            });
        }

        function runTasks(scan) {
            var taskIndexes = scan.tasks.map(function (task, taskIndex) {
                return taskIndex;
            });

            runTaskQueue(scan, taskIndexes, 0, false);
        }

        function retryFailedChecks() {
            if (!lastCompletedScan || !lastCompletedScan.scan || lastCompletedScan.sourceValue !== textarea.value) {
                showFeedback(translate('listChanged'), 'error');
                return;
            }

            var scan = lastCompletedScan.scan;
            var failedIndexes = getFailedTaskIndexes(scan);

            if (!failedIndexes.length) {
                updateRetryFailedButton(scan);
                return;
            }

            scan.cancelled = false;
            scan.cancelCurrentTask = null;
            activeScan = scan;
            setScanningState(true);

            if (retryFailedButton) {
                retryFailedButton.hidden = true;
            }

            showFeedback(failedIndexes.length === 1
                ? translate('retryingOneFailedCheck')
                : translate('retryingFailedChecks', { count: failedIndexes.length }), 'info');

            runTaskQueue(scan, failedIndexes, 0, true);
        }

        function finishCancelledScan() {
            var cancelledScan = activeScan;
            activeScan = null;
            setScanningState(false);
            showFeedback(translate('cancelled'), 'info');

            if (progress) {
                progress.hidden = true;
            }

            framesArea.textContent = '';

            if (cancelledScan && cancelledScan.hasCompletedOnce) {
                renderResults(cancelledScan);
                updateRetryFailedButton(cancelledScan);
            }
        }

        function cancelScan() {
            prepareWasCancelled = true;

            if (prepareRequest && typeof prepareRequest.abort === 'function') {
                prepareRequest.abort();
                prepareRequest = null;
            }

            if (resolveRequest && typeof resolveRequest.abort === 'function') {
                resolveRequest.abort();
                resolveRequest = null;
            }

            if (activeScan) {
                activeScan.cancelled = true;

                if (typeof activeScan.cancelCurrentTask === 'function') {
                    activeScan.cancelCurrentTask();
                }
            } else {
                finishCancelledScan();
            }
        }

        function removeSelected() {
            if (!lastCompletedScan || lastCompletedScan.sourceValue !== textarea.value) {
                showFeedback(translate('listChanged'), 'error');
                return;
            }

            var selectedIndexes = Array.prototype.slice.call(
                resultsList.querySelectorAll('.js-wpacu-font-preload-result-checkbox:checked:not(:disabled)')
            ).map(function (checkbox) {
                return Number(checkbox.getAttribute('data-line-index'));
            }).filter(function (lineIndex) {
                return !isNaN(lineIndex);
            });

            if (!selectedIndexes.length) {
                showFeedback(translate('nothingSelected'), 'error');
                return;
            }

            undoValue = textarea.value;
            suppressTextareaInvalidation = true;
            textarea.value = removeNonEmptyLineIndexes(textarea.value, selectedIndexes);
            dispatchFieldEvent(textarea, 'input');
            dispatchFieldEvent(textarea, 'change');
            suppressTextareaInvalidation = false;
            updateUrlCount();

            if (results) {
                results.hidden = true;
            }

            lastCompletedScan = null;

            if (retryFailedButton) {
                retryFailedButton.hidden = true;
            }
            showFeedback(translate('fieldUpdated'), 'success');

            if (undoNotice) {
                undoNotice.hidden = false;
            }

            if (undoText) {
                undoText.textContent = translate('fieldUpdated');
            }

            if (undoButton) {
                undoButton.textContent = translate('undo');
            }
        }

        function undoRemoval() {
            if (undoValue === null) {
                return;
            }

            suppressTextareaInvalidation = true;
            textarea.value = undoValue;
            dispatchFieldEvent(textarea, 'input');
            dispatchFieldEvent(textarea, 'change');
            suppressTextareaInvalidation = false;
            undoValue = null;
            updateUrlCount();

            if (undoNotice) {
                undoNotice.hidden = true;
            }

            showFeedback('', '');
        }

        startButton.addEventListener('click', startScan);

        if (cancelButton) {
            cancelButton.addEventListener('click', cancelScan);
        }

        if (retryFailedButton) {
            retryFailedButton.addEventListener('click', retryFailedChecks);
        }

        if (removeButton) {
            removeButton.addEventListener('click', removeSelected);
        }

        if (undoButton) {
            undoButton.addEventListener('click', undoRemoval);
        }

        resultsList.addEventListener('change', function (event) {
            if (event.target && event.target.classList.contains('js-wpacu-font-preload-result-checkbox')) {
                updateSelectedCount();
            }
        });

        textarea.addEventListener('input', function () {
            updateUrlCount();

            if (suppressTextareaInvalidation || activeScan) {
                return;
            }

            if (lastCompletedScan && lastCompletedScan.sourceValue !== textarea.value) {
                if (results) {
                    results.hidden = true;
                }
                lastCompletedScan = null;

                if (retryFailedButton) {
                    retryFailedButton.hidden = true;
                }
                showFeedback(translate('listChanged'), 'info');
            }
        });

        updateUrlCount();
    }
}(window, document, window.jQuery));
