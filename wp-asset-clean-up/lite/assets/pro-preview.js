(function ($) {
    'use strict';

    function removeWriteCapability(element) {
        var $element = $(element);

        $element.removeAttr('name');
        $element.removeAttr('formaction');
        $element.attr('aria-disabled', 'true');
        $element.addClass('wpacu-lite-pro-preview-control');

        if ($element.is('input, select, textarea, button')) {
            $element.prop('disabled', true);
        }
    }

    function lockPreviewControls(root) {
        var $root = $(root);

        if ($root.length < 1) {
            return;
        }

        $root.addClass('wpacu-lite-pro-preview-surface');

        $root.find('input, select, textarea, button').each(function () {
            var $control = $(this);

            // Search/filter and expand/contract controls only inspect already-rendered preview data.
            if (
                $control.is('input[type="search"]')
                || $control.is('[data-wpacu-lite-preview-allow="1"]')
                || $control.is('#wpacu-assets-contract-all, #wpacu-assets-expand-all')
            ) {
                $control.removeAttr('name');

                if ($control.is('button')) {
                    $control.attr('type', 'button');
                }

                return;
            }

            removeWriteCapability(this);
        });

        $root.find('.CodeMirror textarea').prop('readonly', true).attr('aria-readonly', 'true');
        $root.find('[contenteditable="true"]').attr('contenteditable', 'false');
    }

    function watchDynamicAssetPreview() {
        $('[data-wpacu-lite-archive-preview="1"]').each(function () {
            var form = this;
            var target = form.querySelector('#wpacu_meta_box_content');

            if (!target) {
                return;
            }

            lockPreviewControls(target);

            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(function () {
                    lockPreviewControls(target);
                });

                observer.observe(target, {
                    childList: true,
                    subtree: true
                });
            }
        });
    }

    function initPluginsManagerPreview() {
        $('[data-wpacu-lite-pm-root="1"]').each(function () {
            var $root = $(this);
            var $search = $root.find('[data-wpacu-lite-pm-search="1"]').first();
            var $cards = $root.find('[data-wpacu-lite-pm-card="1"]');
            var $noResults = $root.find('[data-wpacu-lite-pm-no-results="1"]').first();

            if ($search.length < 1 || $cards.length < 1) {
                return;
            }

            function filterPlugins() {
                var searchTerm = $.trim(String($search.val() || '')).toLowerCase();
                var visibleCount = 0;

                $cards.each(function () {
                    var $card = $(this);
                    var searchText = String($card.attr('data-wpacu-plugin-search') || '').toLowerCase();
                    var isVisible = searchTerm === '' || searchText.indexOf(searchTerm) !== -1;

                    $card.find('[data-wpacu-plugin-search-highlight]').each(function () {
                        highlightPluginSearchText(this, searchTerm);
                    });

                    $card.prop('hidden', ! isVisible);

                    if (isVisible) {
                        visibleCount++;
                    }
                });

                if ($noResults.length > 0) {
                    $noResults.prop('hidden', visibleCount > 0);
                }
            }

            $search.off('.wpacuLitePmSearch').on('input.wpacuLitePmSearch search.wpacuLitePmSearch', filterPlugins);
            filterPlugins();
        });
    }

    function initPluginsManagerProLayoutPreview() {
        $('[data-wpacu-plugins-manager-rules-ui]').each(function () {
            var $ui = $(this);
            var $root = $ui.find('#wpacu-plugins-load-manager-wrap.wpacu-pm-layout').first();

            if ($root.length < 1) {
                return;
            }

            var $search = $root.find('[data-wpacu-pm-search]').first();
            var $groups = $root.find('[data-wpacu-pm-search-group]');
            var $empty = $root.find('[data-wpacu-pm-search-empty]').first();

            function refreshSearch() {
                var searchTerm = $.trim(String($search.val() || '')).toLowerCase();
                var totalVisible = 0;

                $groups.each(function () {
                    var $group = $(this);
                    var visibleInGroup = 0;

                    $group.find('[data-wpacu-layout-plugin-row]').each(function () {
                        var $row = $(this);
                        var searchableText = '';

                        $row.find('[data-wpacu-plugin-search-highlight]').each(function () {
                            if (typeof this.wpacuSearchOriginalText !== 'string') {
                                this.wpacuSearchOriginalText = this.textContent || '';
                            }

                            searchableText += ' ' + this.wpacuSearchOriginalText.toLowerCase();
                            highlightPluginSearchText(this, searchTerm);
                        });

                        var isVisible = searchTerm === '' || searchableText.indexOf(searchTerm) !== -1;

                        $row.prop('hidden', ! isVisible);

                        if (isVisible) {
                            visibleInGroup++;
                            totalVisible++;
                        }
                    });

                    $group.prop('hidden', visibleInGroup === 0);
                    $group.find('.wpacu-pm-plugin-group-count').first().text(visibleInGroup);

                    var $label = $group.find('[data-wpacu-pm-search-group-label]').first();

                    if ($label.length > 0) {
                        $label.text($label.attr(visibleInGroup === 1 ? 'data-singular' : 'data-plural') || '');
                    }
                });

                if ($empty.length > 0) {
                    $empty.prop('hidden', totalVisible > 0);
                }
            }

            function setPluginAreaState($details, state) {
                if ($details.length < 1 || (state !== 'expanded' && state !== 'contracted')) {
                    return;
                }

                $details.attr('data-wpacu-status-area', state);
                $details
                    .find('.wpacu_plugin_expand_contract_area button')
                    .first()
                    .attr('aria-expanded', state === 'expanded' ? 'true' : 'false');

                refreshPluginGroupActions($details.closest('.wpacu-pm-plugin-group'));
            }

            function refreshPluginGroupActions($group) {
                if ($group.length < 1) {
                    return;
                }

                var $details = $group.find('.wpacu_plugin_details[data-wpacu-status-area]');
                var total = $details.length;
                var expandedCount = $details.filter('[data-wpacu-status-area="expanded"]').length;
                var contractedCount = $details.filter('[data-wpacu-status-area="contracted"]').length;

                $group.find('.wpacu_plugins_expand_all').prop('disabled', total === 0 || expandedCount === total);
                $group.find('.wpacu_plugins_contract_all').prop('disabled', total === 0 || contractedCount === total);

            }

            function setExceptionsState($exceptions, isOpen) {
                if ($exceptions.length < 1) {
                    return;
                }

                $exceptions.toggleClass('is-open', isOpen);
                $exceptions
                    .find('[data-wpacu-layout-exceptions-toggle]')
                    .first()
                    .attr('aria-expanded', isOpen ? 'true' : 'false');
                $exceptions
                    .find('.wpacu-pm-exceptions-content')
                    .first()
                    .attr('aria-hidden', isOpen ? 'false' : 'true');
            }

            $root
                .off('.wpacuLitePmProPreview')
                .on(
                    'click.wpacuLitePmProPreview',
                    '.wpacu_plugin_expand_contract_area button[data-wpacu-lite-preview-allow="1"]',
                    function (event) {
                        event.preventDefault();

                        var $details = $(this).closest('.wpacu_plugin_details[data-wpacu-status-area]');
                        var currentState = $details.attr('data-wpacu-status-area');

                        setPluginAreaState($details, currentState === 'contracted' ? 'expanded' : 'contracted');
                    }
                )
                .on(
                    'click.wpacuLitePmProPreview',
                    '.wpacu_plugins_contract_expand_all[data-wpacu-lite-preview-allow="1"]',
                    function (event) {
                        event.preventDefault();

                        var $button = $(this);
                        var targetArea = String($button.attr('data-wpacu-for-area') || '');
                        var targetState = String($button.attr('data-wpacu-target-state') || '');
                        var $table = $root.find('table[data-wpacu-area="' + targetArea.replace(/"/g, '\\"') + '"]').first();

                        if ($table.length < 1) {
                            return;
                        }

                        $table
                            .find('[data-wpacu-layout-plugin-row]:not([hidden]) .wpacu_plugin_details[data-wpacu-status-area]')
                            .each(function () {
                                setPluginAreaState($(this), targetState);
                            });
                    }
                )
                .on(
                    'click.wpacuLitePmProPreview',
                    '[data-wpacu-layout-exceptions-toggle][data-wpacu-lite-preview-allow="1"]',
                    function (event) {
                        event.preventDefault();

                        var $exceptions = $(this).closest('[data-wpacu-layout-exceptions]');

                        setExceptionsState($exceptions, ! $exceptions.hasClass('is-open'));
                    }
                );

            if ($search.length > 0) {
                $search
                    .off('.wpacuLitePmProSearch')
                    .on('input.wpacuLitePmProSearch search.wpacuLitePmProSearch', refreshSearch);
                refreshSearch();
            }

            $root.find('.wpacu_plugin_details[data-wpacu-status-area]').each(function () {
                var $details = $(this);
                setPluginAreaState($details, $details.attr('data-wpacu-status-area'));
            });

            $root.find('.wpacu-pm-plugin-group').each(function () {
                refreshPluginGroupActions($(this));
            });

            $root.find('[data-wpacu-layout-exceptions]').each(function () {
                var $exceptions = $(this);
                setExceptionsState($exceptions, $exceptions.hasClass('is-open'));
            });
        });
    }

    function highlightPluginSearchText(element, searchTerm) {
        if (typeof element.wpacuSearchOriginalText !== 'string') {
            element.wpacuSearchOriginalText = element.textContent || '';
        }

        var originalText = element.wpacuSearchOriginalText;
        var normalizedText = originalText.toLowerCase();
        var normalizedTerm = String(searchTerm || '').toLowerCase();
        var currentIndex = 0;
        var matchIndex = normalizedTerm === '' ? -1 : normalizedText.indexOf(normalizedTerm);

        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }

        while (matchIndex !== -1) {
            if (matchIndex > currentIndex) {
                element.appendChild(document.createTextNode(originalText.slice(currentIndex, matchIndex)));
            }

            var match = document.createElement('span');
            match.className = 'wpacu-pm-search-match';
            match.textContent = originalText.slice(matchIndex, matchIndex + normalizedTerm.length);
            element.appendChild(match);

            currentIndex = matchIndex + normalizedTerm.length;
            matchIndex = normalizedText.indexOf(normalizedTerm, currentIndex);
        }

        if (currentIndex < originalText.length) {
            element.appendChild(document.createTextNode(originalText.slice(currentIndex)));
        }
    }

    $(function () {
        lockPreviewControls('[data-wpacu-lite-lock-controls="1"]');
        watchDynamicAssetPreview();
        initPluginsManagerPreview();
        initPluginsManagerProLayoutPreview();

        $(document).on('submit', '[data-wpacu-lite-pro-preview-form="1"], [data-wpacu-lite-archive-preview="1"]', function (event) {
            event.preventDefault();
            return false;
        });

        $(document).on('click', '[data-wpacu-lite-block-action="1"]', function (event) {
            event.preventDefault();
            return false;
        });
    });
})(jQuery);
