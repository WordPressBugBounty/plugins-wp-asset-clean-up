//
// [START] Core file
//

/*
 * Shared interface helpers
 *
 * Keep all Chosen checks and lifecycle operations in one place. This prevents
 * dynamically inserted controls from calling jQuery Chosen while the native
 * interface is active or while the library is not loaded.
 */
(function (window) {
    'use strict';

    function getInputStyle() {
        // Read the interface that was used to render the current page. Changing
        // the preference takes effect after saving/reloading; applying only part
        // of the new mode before then would leave the page in a mixed state.
        var inputStyleContainer = document.querySelector('[data-wpacu-input-style]');

        if (inputStyleContainer) {
            var inputStyle = inputStyleContainer.getAttribute('data-wpacu-input-style');

            if (inputStyle === 'standard' || inputStyle === 'enhanced') {
                return inputStyle;
            }
        }

        if (typeof window.wpacu_object !== 'undefined' &&
            (window.wpacu_object.input_style === 'standard' || window.wpacu_object.input_style === 'enhanced')) {
            return window.wpacu_object.input_style;
        }

        if (document.querySelector('.wpacu-input-style-standard, .wpacu-switch-standard')) {
            return 'standard';
        }

        return 'enhanced';
    }

    window.wpacuGetInputStyle = getInputStyle;

    window.wpacuUsesEnhancedInputs = function () {
        return window.wpacuGetInputStyle() === 'enhanced';
    };

    window.wpacuInitChosen = function (elements, options) {
        var $ = window.jQuery;

        if ( ! window.wpacuUsesEnhancedInputs() || ! $ || typeof $.fn.chosen !== 'function') {
            return elements;
        }

        var $elements = elements && elements.jquery ? elements : $(elements || []);

        $elements.filter('select').each(function () {
            var $select = $(this);
            var defaultOptions = {};

            if ($select.hasClass('wpacu_access_via_specific_users_dd_search')) {
                defaultOptions = {
                    'width'                                       : '100%',
                    // Results are loaded asynchronously. Suppress Chosen's immediate
                    // "no results" message while the AJAX request is still pending.
                    'no_results_text'                             : '&nbsp;',
                    'reset_search_field_on_update'                : false,
                    'reset_multiple_search_field_on_focus_change' : false
                };
            } else if ($select.hasClass('wpacu_access_via_specific_users_dd')) {
                defaultOptions = {
                    'width' : '100%'
                };
            }

            if ($select.data('chosen') || $select.next('.chosen-container').length > 0) {
                return;
            }

            $select.chosen($.extend({}, defaultOptions, options || {}));
        });

        return $elements;
    };

    window.wpacuRefreshChosen = function (elements) {
        var $ = window.jQuery;

        if ( ! window.wpacuUsesEnhancedInputs() || ! $ || typeof $.fn.chosen !== 'function') {
            return elements;
        }

        var $elements = elements && elements.jquery ? elements : $(elements || []);

        $elements.filter('select').each(function () {
            var $select = $(this);

            if ($select.data('chosen') || $select.next('.chosen-container').length > 0) {
                $select.trigger('chosen:updated');
            }
        });

        return $elements;
    };

    window.wpacuGetChosenSearchInput = function (selectElement) {
        var $ = window.jQuery;

        if ( ! $) {
            return null;
        }

        var $select = selectElement && selectElement.jquery ? selectElement : $(selectElement || []);

        return $select.next('.chosen-container').find('.chosen-search-input').first();
    };
})(window);

/* Animate the optimization settings disclosures while preserving native <details> semantics. */
function wpacuHighlightSourceViewerCode(rootElement) {
    const root = rootElement || document;
    const codeElements = root.querySelectorAll('.wpacu-source-viewer-modal__body code:not([data-wpacu-highlighted])');

    codeElements.forEach(function(codeElement) {
        const source = codeElement.textContent || '';
        const lines = source.replace(/\r\n?/g, '\n').split('\n');
        const fragment = document.createDocumentFragment();

        lines.forEach(function(line, lineIndex) {
            const lineElement = document.createElement('span');
            const numberElement = document.createElement('span');
            const contentElement = document.createElement('span');
            const tokenPattern = /(\/\*[\s\S]*?\*\/|\/\/.*$|<!--[\s\S]*?-->|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|#[0-9a-fA-F]{3,8}\b|\b\d+(?:\.\d+)?(?:px|em|rem|vh|vw|%|s|ms)?\b|@[\w-]+|--[\w-]+|[.#]?[a-zA-Z_][\w-]*(?=\s*:)|[{}()[\];,:<>])/g;
            let cursor = 0;
            let match;

            lineElement.className = 'wpacu-code-line';
            numberElement.className = 'wpacu-code-line-number';
            numberElement.textContent = String(lineIndex + 1);
            contentElement.className = 'wpacu-code-line-content';

            while ((match = tokenPattern.exec(line)) !== null) {
                if (match.index > cursor) {
                    contentElement.appendChild(document.createTextNode(line.slice(cursor, match.index)));
                }

                const token = document.createElement('span');
                const value = match[0];
                let tokenType = 'punctuation';

                if (/^(?:\/\*|\/\/|<!--)/.test(value)) {
                    tokenType = 'comment';
                } else if (/^["']/.test(value)) {
                    tokenType = 'string';
                } else if (/^#(?:[0-9a-fA-F]{3,8})$|^\d/.test(value)) {
                    tokenType = 'number';
                } else if (/^@/.test(value)) {
                    tokenType = 'keyword';
                } else if (/^--/.test(value)) {
                    tokenType = 'variable';
                } else if (/^[.#a-zA-Z_]/.test(value)) {
                    tokenType = 'property';
                }

                token.className = 'wpacu-code-token wpacu-code-token--' + tokenType;
                token.textContent = value;
                contentElement.appendChild(token);
                cursor = match.index + value.length;
            }

            if (cursor < line.length) {
                contentElement.appendChild(document.createTextNode(line.slice(cursor)));
            }

            lineElement.appendChild(numberElement);
            lineElement.appendChild(contentElement);
            fragment.appendChild(lineElement);
        });

        codeElement.textContent = '';
        codeElement.appendChild(fragment);
        codeElement.setAttribute('data-wpacu-highlighted', '1');
    });
}

document.addEventListener('DOMContentLoaded', function () {
    wpacuHighlightSourceViewerCode(document);
    var $ = window.jQuery;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if ( ! $ || reduceMotion) {
        return;
    }

    $('.wpacu-opt-details > summary').on('click', function (event) {
        var details = this.parentNode;
        var $details = $(details);
        var $body = $details.children('.wpacu-opt-details-body');

        if ($body.length === 0 || $details.data('wpacuDetailsAnimating')) {
            return;
        }

        event.preventDefault();
        $details.data('wpacuDetailsAnimating', true);

        if (details.open) {
            $details.addClass('wpacu-is-closing');
            $body.slideUp(200, function () {
                details.open = false;
                $details.removeClass('wpacu-is-closing').removeData('wpacuDetailsAnimating');
                $body.removeAttr('style');
            });
            return;
        }

        details.open = true;
        $body.hide().slideDown(200, function () {
            $details.removeData('wpacuDetailsAnimating');
            $body.removeAttr('style');
        });
    });
});

document.addEventListener("DOMContentLoaded", function () {
    if (typeof jQuery === "undefined") {
        console.error("jQuery is not loaded. Asset CleanUp needs the jQuery library, so make sure it is loaded.");
        return;
    }

    (function ($) {
        $.fn.wpAssetCleanUp = function () {
            let metaBoxContent = '#wpacu_meta_box_content';

            return {
                getParameterByName: function (name, url = window.location.href) {
                    // Source: https://stackoverflow.com/questions/901115/how-can-i-get-query-string-values-in-javascript
                    name = name.replace(/[\[\]]/g, '\\$&');
                    var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
                        results = regex.exec(url);
                    if (!results) return null;
                    if (!results[2]) return '';
                    return decodeURIComponent(results[2].replace(/\+/g, ' '));
                },

                addExtraRequiredParamsToGetLoadedAssets: function(dataGetLoadedAssets) {
                    // Does it have a post ID? Singular page
                    if (typeof wpacu_object.post_id !== 'undefined') {
                        dataGetLoadedAssets['post_id'] = wpacu_object.post_id;
                    }


                    if ($.fn.wpAssetCleanUp().getParameterByName('page') === wpacu_object.plugin_prefix + '_assets_manager') {
                        // Called from plugin's own area: "CSS & JS MANAGER" -- "MANAGE CSS/JS"
                        dataGetLoadedAssets['called_from_plugin_own_asset_manager'] = true;
                    }

                    return dataGetLoadedAssets;
                },

                cssJsManagerActions: function () {

                    // When editing the CSS/JS manager in an edit post page (legacy), update the "wpacu_object" when the retrieval method is changed
                    // This is required, Ssnce the Gutenberrg editor is likely used, AJAX calls will be made to update the CSS/JS rules
                    // after the "Submit" button is clicked (without any standard page reloading)
                    $(document).on('click change', 'input.wpacu-dom-get-type-from-css-js-manager', function () {
                        if (typeof wpacu_object.dom_get_type !== 'undefined' && wpacu_object.dom_get_type) {
                            wpacu_object.dom_get_type = $(this).val();
                        }
                    });

                    let cbSelector = '.input-unload-on-this-page',
                        cbSelectorNotLocked = '.input-unload-on-this-page.wpacu-not-locked',
                        cbSelectorMakeExceptionOnPage = '.wpacu_load_it_option_on_this_page.wpacu_load_exception',
                        handle, handleFor, $targetedAssetRow;

                    // live() is deprecated and if used and jQuery Migrate is disabled
                    // it could break the website's front-end functionality
                    $(document).on('click change', cbSelector, function (event) {
                        handle = $(this).attr('data-handle');
                        handleFor = $(this).hasClass('wpacu_unload_rule_for_style') ? 'style' : 'script';

                        if ($(this).prop('checked')) {
                            if (event.type === 'click' && ( ! $.fn.wpAssetCleanUp().triggerAlertWhenAnyUnloadRuleIsChosen(handle, handleFor)) ) {
                                return false;
                            }

                            if ($('#wpacu_load_it_option_' + handleFor + '_' + handle).is(':checked')) {
                                $('#wpacu_load_it_option_' + handleFor + '_' + handle).prop('checked', false).trigger('change');
                            }

                            $.fn.wpAssetCleanUp().uncheckAllOtherBulkUnloadRules($(this), false); // skip Unload via RegEx as both can be used

                            // Show load exceptions area (for exceptions like load it if the user is logged in)
                            $.fn.wpAssetCleanUp().showHandleLoadExceptionArea(handleFor, handle);
                            $(this).closest('tr').addClass('wpacu_not_load');
                        } else {
                            $(this).closest('tr').removeClass('wpacu_not_load');
                            $targetedAssetRow = $(this).parents('.wpacu_asset_row');
                            $.fn.wpAssetCleanUp().hideHandleLoadExceptionArea($targetedAssetRow, handle, handleFor);
                        }
                    });

                    /*
                     * [Start] Unload on this page
                     */
                    // Check All
                    $('.wpacu-area-check-all').on('click', function (e) {
                        e.preventDefault();

                        let wpacuPluginTarget = $(this).attr('data-wpacu-plugin');
                        //console.log(wpacuPluginTarget);

                        $('table.wpacu_list_by_location[data-wpacu-plugin="' + wpacuPluginTarget + '"]')
                            .find(cbSelectorNotLocked)
                            .prop('checked', true).closest('tr').addClass('wpacu_not_load');
                    });

                    // Uncheck All
                    $('.wpacu-area-uncheck-all').on('click', function (e) {
                        e.preventDefault();

                        let wpacuPluginTarget = $(this).attr('data-wpacu-plugin');

                        $('table.wpacu_list_by_location[data-wpacu-plugin="' + wpacuPluginTarget + '"]')
                            .find(cbSelectorNotLocked)
                            .prop('checked', false).closest('tr').removeClass('wpacu_not_load');
                    });
                    /*
                     * [End] Unload on this page
                     */

                    /*
                    * [Start] Make exception, Load it on this page
                    */
                    // Check all, when the assets are sorted by their location
                    $('.wpacu-area-check-load-all').on('click change', function (e) {
                        e.preventDefault();

                        let wpacuPluginTarget = $(this).attr('data-wpacu-plugin');
                        let $wpacuPluginList = $('table.wpacu_list_by_location[data-wpacu-plugin="' + wpacuPluginTarget + '"]');

                        $wpacuPluginList
                            .find(cbSelectorMakeExceptionOnPage)
                            .prop('checked', true).closest('tr.wpacu_is_bulk_unloaded').removeClass('wpacu_not_load');

                        $wpacuPluginList.find(cbSelectorNotLocked).prop('checked', false).trigger('change');
                    });

                    // Uncheck all, when the assets are sorted by their location
                    $('.wpacu-area-uncheck-load-all').on('click change', function (e) {
                        e.preventDefault();

                        let wpacuPluginTarget = $(this).attr('data-wpacu-plugin');
                        let $wpacuPluginList = $('table.wpacu_list_by_location[data-wpacu-plugin="' + wpacuPluginTarget + '"]');

                        $wpacuPluginList
                            .find(cbSelectorMakeExceptionOnPage)
                            .prop('checked', false).closest('tr.wpacu_is_bulk_unloaded').addClass('wpacu_not_load');

                        $wpacuPluginList.find(cbSelectorNotLocked).prop('checked', false).trigger('change');
                    });
                    /*
                    * [End] Make exception, Load it on this page
                    */

                    $(document).on('click', '.wpacu_keep_bulk_rule', function () {
                        if ($(this).prop('checked')) {
                            $(this).parents('li').next().removeClass('remove_rule');
                        }
                    });

                    $(document).on('click', '.wpacu_remove_bulk_rule', function () {
                        if ($(this).prop('checked')) {
                            $(this).parents('li').addClass('remove_rule');
                        }
                    });

                    // Unload on All Pages of post/page/custom post type / site-wide (everywhere) / based on taxonomy
                    $(document).on('change', '.wpacu_bulk_unload', function (event) {
                        handle = $(this).attr('data-handle');
                        handleFor = $(this).attr('data-handle-for'); // 'style' or 'script' (e.g. 'contact-form-7' has the same name for both)
                        $targetedAssetRow = $('[data-' + handleFor + '-handle-row="' + handle + '"]');

                        let $parentLi = $(this).parents('li');

                        /**************************************************************
                         * STATE 1: The checkbox IS CHECKED (show multiple drop-down)
                         * ************************************************************
                         */
                        if ($(this).prop('checked')) {
                            if (event.type === 'click' && ( ! $.fn.wpAssetCleanUp().triggerAlertWhenAnyUnloadRuleIsChosen(handle, handleFor)) ) {
                                return false;
                            }

                            if ($(this).hasClass('wpacu_global_unload') ||
                                $(this).hasClass('wpacu_post_type_unload')
                            ) {
                                /*
                                 * Clicked: "Unload site-wide" (.wpacu_global_unload) or "Unload on all posts of the same [post_type]" (.wpacu_post_type_unload)
                                 */
                                $(this).parent('label').addClass('wpacu_input_load_checked');
                                $(this).closest('tr').addClass('wpacu_not_load');
                            }


                            // Show load exceptions area if Unload everywhere or other bulk unload rule is chosen
                            $.fn.wpAssetCleanUp().showHandleLoadExceptionArea(handleFor, handle);

                            if ($(this).hasClass('wpacu_global_unload')) {
                                // CSS/JS: Unload Site-Wide (Everywhere) was clicked
                                $.fn.wpAssetCleanUp().uncheckAllOtherBulkUnloadRules($(this), true);

                                // Obviously, "Unload on this page" should be unchecked as the rule overwrites it
                                $('.input-unload-on-this-page[data-handle-for="' + handleFor + '"][data-handle="' + handle + '"]')
                                    .prop('checked', false);

                            } else if ($(this).hasClass('wpacu_post_type_unload')) {
                                // Unload on All Pages of "[post_type_here]" post type
                                $.fn.wpAssetCleanUp().uncheckAllOtherBulkUnloadRules($(this), false);

                                // Obviously, "Unload on this page" should be unchecked as the rule overwrites it
                                $('.input-unload-on-this-page[data-handle-for="' + handleFor + '"][data-handle="' + handle + '"]')
                                    .prop('checked', false);
                            }
                            } else {
                            /***********************************************************************************
                             * STATE 2: The checkbox IS UNCHECKED / UNMARKED (the multiple drop-down is hidden)
                             ***********************************************************************************
                             */
                            if ( ! $(this).hasClass('wpacu_unload_it_regex_checkbox') && ! $(this).hasClass('wpacu_unload_it_post_type_via_tax_checkbox') ) {
                                /*
                                 * Clicked: "Unload site-wide" or "Unload on all posts of the same [post_type]"
                                 */
                                $(this).parent('label').removeClass('wpacu_input_load_checked');
                                $(this).closest('tr').removeClass('wpacu_not_load');
                            } else if ( $(this).hasClass('wpacu_unload_it_regex_checkbox') ) {
                                /*
                                 * "Unload via RegEx" is clicked
                                 */
                                $parentLi.find('label').removeClass('wpacu_unload_checked');
                                $parentLi.find('textarea')
                                    .blur() // lose focus
                                    .addClass('wpacu_disabled');

                                // Action taken if the input has no value
                                if ($parentLi.find('textarea').val().trim() === '') {
                                    $parentLi.find('textarea')
                                        .prop('disabled', true).val(''); // unchecked with no value added to the input

                                    $parentLi.find('.wpacu_handle_unload_regex_input_wrap')
                                        .addClass('wpacu_hide'); // Hide the input area
                                }
                            } else if ( $(this).hasClass('wpacu_unload_it_post_type_via_tax_checkbox') ) {
                                /*
                                 * "Unload via taxonomy" (post type) is clicked
                                 */
                                $parentLi.find('label').removeClass('wpacu_unload_checked');
                                $parentLi.find('.wpacu_handle_manage_post_type_via_tax_input_wrap').addClass('wpacu_hide'); // Hide the input area
                            }

                            // If it's NOT already unloaded (on page load)
                            // All bulk unloads are unchecked
                            // Then HIDE make exceptions area
                            $.fn.wpAssetCleanUp().hideHandleLoadExceptionArea($targetedAssetRow, handle, handleFor);
                        }

                        // No bulk rule already applied (red background) and none of the bulk unloads (except RegEx) checkboxes are checked
                        if (!$targetedAssetRow.hasClass('wpacu_is_bulk_unloaded') && !$('.wpacu_bulk_unload:not(.wpacu_unload_it_regex_checkbox)').is(':checked')) {
                            $(this).closest('tr').removeClass('wpacu_not_load');
                        }
                    });

                    // "Load it on this page" / "Load it on all pages of this post type" is clicked
                    $(document).on(
                        'click change', // when these actions are taken
                        cbSelectorMakeExceptionOnPage + ',' + '.wpacu_load_it_option_post_type', // on these elements
                        function () { // trigger the following function
                            let handle = $(this).attr('data-handle');

                            if ($(this).prop('checked')) {
                                $(this).parent('label').addClass('wpacu_global_unload_exception');

                                // Uncheck "Unload on this page" as it's not relevant anymore
                                let asset_type = '';

                                if ($(this).hasClass('wpacu_style')) {
                                    asset_type = 'style';
                                } else if ($(this).hasClass('wpacu_script')) {
                                    asset_type = 'script';
                                }

                                let unloadOnThisPageCurrentHandle = '#' + asset_type + '_' + handle,
                                    loadOnThisPageCurrentHandle = '#wpacu_load_it_option_' + asset_type + '_' + handle;

                                // "Load it on this page" was clicked, and it checked
                                if ($(this).hasClass('wpacu_load_it_option_on_this_page')) {
                                    // If "Unload on this page" is checked, and "Load it on this page" is checked as well (as it's the case if this area is reached)
                                    // Make sure both are turned off as they cancel each other (do not make any sense)
                                    if ( $(unloadOnThisPageCurrentHandle).is(':checked') ) {
                                        $(unloadOnThisPageCurrentHandle).prop('checked', false).trigger('change');
                                        $(this).prop('checked', false).trigger('change');
                                    }

                                    // e.g. If "On this page" (load exception) is clicked, 'On all pages of "post" post type' gets unchecked (if already checked)
                                    // As it doesn't make any sense to have both checked
                                    if ( $('#wpacu_load_it_option_post_type_' + asset_type + '_' + handle).is(':checked') ) {
                                        $('#wpacu_load_it_option_post_type_' + asset_type + '_' + handle).prop('checked', false).trigger('change');
                                    }

                                    $(this).closest('tr').removeClass('wpacu_not_load');
                                }

                                // e.g. If 'On all pages of "post" post type' is clicked, "On this page" gets unchecked (if already checked)
                                // As it doesn't make any sense to have both checked
                                else if ($(this).hasClass('wpacu_load_it_option_post_type')) {
                                    if ($(loadOnThisPageCurrentHandle).is(':checked')) {
                                        $(loadOnThisPageCurrentHandle).prop('checked', false).trigger('change');
                                    } else if ($('#wpacu_global_unload_post_type_' + asset_type + '_' + handle).is(':checked')) {
                                        $('#wpacu_global_unload_post_type_' + asset_type + '_' + handle).prop('checked', false).trigger('change');
                                        $(this).prop('checked', false).trigger('change');
                                    } else {
                                        $(this).closest('tr').removeClass('wpacu_not_load');
                                    }
                                }
                            } else {
                                $(this).parent('label').removeClass('wpacu_global_unload_exception');
                            }
                        }
                    );

                    // Put back the red background in case all load exceptions were unchecked
                    // and there's an unloading option there such as "site-wide" or "on all pages of [post] type"
                    $(document).on('click change', '.wpacu_load_exception', function() {
                        handle    = $(this).attr('data-handle');
                        handleFor = $(this).attr('data-handle-for'); // "style" or "script"

                        let targetedTr = 'tr.wpacu_asset_row.' + handleFor + '_' + handle;

                        if ( ! $(targetedTr).find('.wpacu_load_exception').is(':checked') ) {
                            // When the red background was already there (rule set before the CSS/JS manager was loaded)
                            if ($(targetedTr).hasClass('wpacu_is_bulk_unloaded')) {
                                $(targetedTr).addClass('wpacu_not_load');
                            }

                            // When the rule is set on the spot (after the CSS/JS manager loads), and the red background shows up
                            if ($(targetedTr).find('.wpacu_bulk_unload').is(':checked')) {
                                $(targetedTr).addClass('wpacu_not_load');
                            }
                        }
                    });

                    // Handle Notes
                    $(document).on('click', '.wpacu-add-handle-note', function (e) {
                        e.preventDefault();

                        let wpacuHandle = $(this).attr('data-handle'), $wpacuNotesFieldArea, $wpacuNoteInput;

                        if ($(this).hasClass('wpacu-for-script')) {
                            $wpacuNotesFieldArea = $('.wpacu-handle-notes-field[data-script-handle="' + wpacuHandle + '"]');
                        } else if ($(this).hasClass('wpacu-for-style')) {
                            $wpacuNotesFieldArea = $('.wpacu-handle-notes-field[data-style-handle="' + wpacuHandle + '"]');
                        }

                        if ($wpacuNotesFieldArea.length < 1) {
                            return;
                        }

                        $wpacuNoteInput = $wpacuNotesFieldArea.find(':input');

                        if ($wpacuNotesFieldArea.is(':hidden')) {
                            // When "Add Note" is clicked, mark the textarea as visible and not disabled
                            $wpacuNotesFieldArea.show();
                            $wpacuNoteInput.prop('disabled', false);
                        } else {
                            $wpacuNotesFieldArea.hide();

                            // Was the area hidden without any textarea value and the value was null on page load?
                            // Mark it as disabled (save total sent inputs for PHP processing)
                            // If there's ONLY space added (could be by mistake) to the textarea, ignore it as it's irrelevant
                            if ($wpacuNoteInput.val().trim() === '' && $wpacuNoteInput.attr('data-wpacu-is-empty-on-page-load') === 'true') {
                                $wpacuNoteInput.prop('disabled', true).val('');
                            }
                        }
                    });

                    // [Get external asset size]
                    $(document).on('click', '.wpacu-external-file-size', function (e) {
                        e.preventDefault();

                        let $wpacuCurrentTarget = $(this),
                            $wpacuFileSizeArea,
                            wpacuRemoteFile = $wpacuCurrentTarget.attr('data-src');

                        $wpacuCurrentTarget.hide();

                        $wpacuFileSizeArea = $wpacuCurrentTarget.next();
                        $wpacuFileSizeArea.show();

                        if (wpacu_object.current_host_same_as_host_from_target_url && wpacuRemoteFile.includes('/?')) { // Dynamic CSS/JS
                            $.get(wpacuRemoteFile, {}, function (output, textStatus, request) {
                                if (textStatus !== 'success') {
                                    return 'N/A';
                                }

                                $wpacuFileSizeArea.html($.fn.wpAssetCleanUp().wpacuBytesToSize(output.length));
                            });
                        } else {
                            $.post(wpacu_object.ajax_url, {
                                'action':             wpacu_object.plugin_prefix + '_get_external_file_size',
                                'wpacu_remote_file':  wpacuRemoteFile,
                                'wpacu_nonce':        wpacu_object.wpacu_ajax_check_remote_file_size_nonce
                            }, function (size) {
                                $wpacuFileSizeArea.html(size);
                            });
                        }
                    });
                    // [/Get external asset size]

                    // Note: Starting from July 24, 2021, AJAX is used to save the state
                    $(document).on('click', '.wpacu_handle_row_expand_contract', function (e) {
                        e.preventDefault();

                        let wpacuAssetHandle = $(this).attr('data-wpacu-handle'),
                            wpacuAssetHandleFor = $(this).attr('data-wpacu-handle-for'),
                            wpacuNewAssetRowState;

                        if ($(this).find('span').hasClass('dashicons-minus')) {
                            /*
                             * Already expanded when clicked (had minus sign)
                             */
                            wpacuNewAssetRowState = 'contracted';

                            $(this).parents('td').attr('data-wpacu-row-status', wpacuNewAssetRowState)
                                .find('.wpacu_handle_row_expanded_area').addClass('wpacu_hide');
                            $(this).find('span').removeClass('dashicons-minus').addClass('dashicons-plus');

                            } else if ($(this).find('span').hasClass('dashicons-plus')) {
                            /*
                             * Already contracted when clicked (had plus sign)
                             */
                            wpacuNewAssetRowState = 'expanded';

                            $(this).parents('td').attr('data-wpacu-row-status', wpacuNewAssetRowState).find('.wpacu_handle_row_expanded_area').removeClass('wpacu_hide');
                            $(this).find('span').removeClass('dashicons-plus').addClass('dashicons-minus');

                            }

                        $.fn.wpAssetCleanUp().wpacuAjaxUpdateKeepTheAssetRowState(wpacuNewAssetRowState, wpacuAssetHandle, wpacuAssetHandleFor, $(this));
                    });

                    $(document).on('click', '.wpacu_area_handles_row_expand_contract', function (e) {
                        e.preventDefault();

                        let wpacuAreaName = $(this).attr('data-wpacu-area'),
                            wpacuNewAreaAssetsRowState,
                            wpacuAllAreaHandles = [],
                            wpacuPreviousAreaAssetStates = [],
                            $areaWrap = $('table.wpacu_list_table[data-wpacu-area="' + wpacuAreaName + '"]');

                        if ($(this).hasClass('wpacu-area-contract-all-assets')) {
                            wpacuNewAreaAssetsRowState = 'contracted';
                        } else if ($(this).hasClass('wpacu-area-expand-all-assets')) {
                            wpacuNewAreaAssetsRowState = 'expanded';
                        }

                        // Get all plugin / area handles and wrap them in a list together with their type ("style" or "script")
                        $areaWrap.find('tr.wpacu_asset_row').each(function (index, value) {
                            var handleStyleAttr = $(this).attr('data-style-handle-row');
                            var handleScriptAttr = $(this).attr('data-script-handle-row');

                            if (typeof handleStyleAttr !== 'undefined' && handleStyleAttr !== false) {
                                wpacuAllAreaHandles[index] = handleStyleAttr + '_style';
                            } else if (typeof handleScriptAttr !== 'undefined' && handleScriptAttr !== false) {
                                wpacuAllAreaHandles[index] = handleScriptAttr + '_script';
                            }

                            var $tdAssetRow = $(this).find('td[data-wpacu-row-status]');

                            wpacuPreviousAreaAssetStates.push({
                                element: $tdAssetRow.get(0),
                                state: $tdAssetRow.attr('data-wpacu-row-status')
                            });

                            if (wpacuNewAreaAssetsRowState === 'contracted') {
                                $tdAssetRow.attr('data-wpacu-row-status', wpacuNewAreaAssetsRowState)
                                    .find('.wpacu_handle_row_expanded_area').addClass('wpacu_hide');
                                $tdAssetRow.find('a.wpacu_handle_row_expand_contract').find('span').removeClass('dashicons-minus').addClass('dashicons-plus');
                            } else if (wpacuNewAreaAssetsRowState === 'expanded') {
                                $tdAssetRow.attr('data-wpacu-row-status', wpacuNewAreaAssetsRowState)
                                    .find('.wpacu_handle_row_expanded_area').removeClass('wpacu_hide');
                                $tdAssetRow.find('a.wpacu_handle_row_expand_contract').find('span').removeClass('dashicons-plus').addClass('dashicons-minus');
                            }
                        });

                        $.fn.wpAssetCleanUp().wpacuAjaxUpdateAllAreaAssetsRowState(
                            wpacuNewAreaAssetsRowState,
                            wpacuAllAreaHandles,
                            $areaWrap,
                            wpacuPreviousAreaAssetStates
                        );
                    });
                },

                triggerAlertWhenAnyUnloadRuleIsChosen: function (handle, handleFor) {
                    // The moment the load exception area is shown, it means at least one unload rule was set
                    // There are cases when the admin needs to be alerted

                    // Dashicons
                    if (handle === 'dashicons' && handleFor === 'style') {
                        if ($('input[name="wpacu_ignore_child[styles][nf-display]').length > 0 && !confirm(wpacu_object.dashicons_unload_alert_ninja_forms_alert)) {
                            return false;
                        }
                    }

                    if (handleFor === 'script') {
                        // jQuery library
                        if ((handle === 'jquery' || handle === 'jquery-core')) {
                            if ($('#script_jquery_ignore_children').length > 0 && !confirm(wpacu_object.jquery_unload_alert)) {
                                return false;
                            }
                        }

                        // JavaScript Cookie (https://github.com/js-cookie/js-cookie)
                        // Parent of: wc-cart-fragments, woocommerce
                        if (handle === 'js-cookie') {
                            if ( ! confirm(wpacu_object.woo_js_cookie_unload_alert) ) {
                                return false;
                            }
                        }

                        // WooCommerce's "wc-cart-fragments" JS file
                        if (handle === 'wc-cart-fragments') {
                            if ( ! confirm(wpacu_object.woo_wc_cart_fragments_unload_alert) ) {
                                return false;
                            }
                        }

                        // Other JS files
                        if ((handle === 'backbone' || handle === 'underscore')) {
                            if (!confirm(wpacu_object.sensitive_library_unload_alert)) {
                                return false;
                            }
                        }
                    }

                    return true;
                },

                showHandleLoadExceptionArea: function (handleFor, handle) {
                    let $targetedLoadExceptionArea = $('div.wpacu_exception_options_area_wrap[data-' + handleFor + '-handle="' + handle + '"]');
                    $targetedLoadExceptionArea.parent('div').removeClass('wpacu_hide');
                    // Remove "disabled" attribute to any load exceptions checkboxes
                    $targetedLoadExceptionArea.find('input[type="checkbox"]')
                        // Except the locked ones if the Lite version is used
                        .not('.wpacu_lite_locked')
                        .prop('disabled', false);
                },
                hideHandleLoadExceptionArea: function ($targetedAssetRow, handle, handleFor) {
                    // If it's NOT already unloaded (on page load)
                    // All bulk unloads are unchecked
                    // Then HIDE make exceptions area
                    if ( ! $targetedAssetRow.hasClass('wpacu_is_bulk_unloaded') ) {
                        if ( ! $targetedAssetRow.find('.wpacu_bulk_unload').is(':checked') ) {
                            let $targetedLoadExceptionArea = $('div.wpacu_exception_options_area_wrap[data-' + handleFor + '-handle="' + handle + '"]');
                            $targetedLoadExceptionArea.parent('div').addClass('wpacu_hide');
                            // Set "disabled" attribute any load exceptions checkboxes as they are irrelevant in this instance
                            $targetedLoadExceptionArea.find('input[type="checkbox"]').prop('disabled', true);
                        }
                    }
                },

                uncheckAllOtherBulkUnloadRules: function ($targetInput, includingUnloadViaRegEx) {
                    let wpacuToFind = '.wpacu_bulk_unload';

                    if (includingUnloadViaRegEx === false) {
                        wpacuToFind = '.wpacu_bulk_unload:not(.wpacu_unload_it_regex_checkbox)';
                    }

                    $targetInput.closest('tr').find(wpacuToFind).not($targetInput) // all except the target one
                        // uncheck it
                        .prop('checked', false)
                        // remove the "checked" style from the label
                        .parent('label').removeClass('wpacu_input_load_checked')
                        .removeClass('wpacu_unload_checked');
                },

                limitSubmittedFields: function () {

                    let preloadTargetInput = '[data-wpacu-input="preload"]',
                        wpacuListToCheck = [];

                    // Edit post/page area (e.g. /wp-admin/post.php?post=[POST_ID_HERE]&action=edit)
                    // OR edit taxonomy area (e.g. /wp-admin/term.php?taxonomy=category&tag_ID=63&post_type=post)
                    if ($('body.wp-admin form#post').length > 0 || $('body.wp-admin form#edittag').length > 0) {
                        if ($('#wpacu_unload_assets_area_loaded').length < 1) {
                            return; // the CSS/JS area is not loaded on edit post/page area, thus no reason to continue
                        }

                        return true; // leave it always to true as the edit post/page/taxonomy form needs to always submit (might be edited later on)
                    }

                    if ($(preloadTargetInput).length > 0) {
                        wpacuListToCheck.push(preloadTargetInput);
                    }

                    if (wpacuListToCheck.length > 0) {
                        $(wpacuListToCheck.join()).each(function () {
                            let $thisEl = $(this);
                            if ( ! $thisEl.val() ) {
                                $thisEl.prop('disabled', 'disabled');

                                setTimeout(function () {
                                    $thisEl.prop('disabled', false);
                                }, 2000); // restore them in case the user pressed "Preview Changes"
                            }
                        });
                    }

                    return true;
                },

                wpacuUrlsPointToDifferentLocations: function (requestedUrl, finalUrl) {
                    if (!requestedUrl || !finalUrl) {
                        return false;
                    }

                    let requestedUrlObject, finalUrlObject;

                    try {
                        requestedUrlObject = new URL(requestedUrl, window.location.origin);
                        finalUrlObject = new URL(finalUrl, window.location.origin);
                    } catch (e) {
                        return false;
                    }

                    const normalizeHost = function (host) {
                        host = String(host || '').toLowerCase();

                        return host.indexOf('www.') === 0 ? host.substring(4) : host;
                    };

                    if (normalizeHost(requestedUrlObject.hostname) !== normalizeHost(finalUrlObject.hostname)) {
                        return true;
                    }

                    const getEffectivePort = function (urlObject) {
                        if (urlObject.port) {
                            return parseInt(urlObject.port, 10);
                        }

                        return urlObject.protocol === 'https:' ? 443 : 80;
                    };

                    const usesDefaultPort = function (urlObject) {
                        if (!urlObject.port) {
                            return true;
                        }

                        const port = parseInt(urlObject.port, 10);

                        return (urlObject.protocol === 'http:' && port === 80)
                            || (urlObject.protocol === 'https:' && port === 443);
                    };

                    const requestedPort = getEffectivePort(requestedUrlObject);
                    const finalPort = getEffectivePort(finalUrlObject);

                    if (
                        requestedPort !== finalPort
                        && !(usesDefaultPort(requestedUrlObject) && usesDefaultPort(finalUrlObject))
                    ) {
                        return true;
                    }

                    const normalizePath = function (path) {
                        try {
                            path = decodeURIComponent(path || '/');
                        } catch (e) {
                            path = path || '/';
                        }

                        if (path.length > 1) {
                            path = path.replace(/\/+$/, '');
                        }

                        return path || '/';
                    };

                    if (normalizePath(requestedUrlObject.pathname) !== normalizePath(finalUrlObject.pathname)) {
                        return true;
                    }

                    const ignoredParams = [
                        wpacu_object.plugin_prefix + '_load',
                        'wpacu_time_r',
                        'wpacu_manage_dash',
                        'force_manage_dash'
                    ];

                    ignoredParams.forEach(function (paramName) {
                        requestedUrlObject.searchParams.delete(paramName);
                        finalUrlObject.searchParams.delete(paramName);
                    });

                    requestedUrlObject.searchParams.sort();
                    finalUrlObject.searchParams.sort();

                    return requestedUrlObject.searchParams.toString() !== finalUrlObject.searchParams.toString();
                },

                wpacuShowRedirectedFetchUrlReminder: function (redirectedFetchUrl) {
                    $('.wpacu-redirected-fetch-url-reminder').remove();

                    if (!redirectedFetchUrl || redirectedFetchUrl === wpacu_object.page_url) {
                        return;
                    }

                    const escapeHtml = function (value) {
                        return $('<div>').text(value).html();
                    };

                    const escapedUrl = escapeHtml(redirectedFetchUrl);
                    const reminderHtml =
                        '<div class="wpacu-redirected-fetch-url-reminder" style="margin-top: 10px;">'
                        + '<span class="dashicons dashicons-migrate"></span> <strong>' + escapeHtml(wpacu_object.redirected_fetch_final_url) + '</strong> '
                        + '<a target="_blank" href="' + escapedUrl + '"><span>' + escapedUrl + '</span></a>'
                        + '</div>';

                    const $slot = $('.wpacu-redirected-fetch-url-reminder-slot').first();

                    if ($slot.length > 0) {
                        $slot.html(reminderHtml);
                    }
                },

                wpacuShowRedirectedFetchNotice: function (requestedUrl, finalUrl) {
                    const escapeHtml = function (value) {
                        return $('<div>').text(value).html();
                    };

                    const escapeAttribute = function (value) {
                        return escapeHtml(value).replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                    };

                    let canFetchRedirectedUrl = false;
                    let redirectedFetchUrl = finalUrl;
                    let redirectedUrlIsHomePage = false;

                    try {
                        const requestedUrlObject = new URL(requestedUrl, window.location.origin);
                        const finalUrlObject = new URL(finalUrl, window.location.origin);
                        const homeUrlObject = new URL(wpacu_object.home_url, window.location.origin);

                        [
                            wpacu_object.plugin_prefix + '_load',
                            'wpacu_time_r',
                            'wpacu_manage_dash',
                            'force_manage_dash'
                        ].forEach(function (paramName) {
                            finalUrlObject.searchParams.delete(paramName);
                        });

                        redirectedFetchUrl = finalUrlObject.toString();

                        canFetchRedirectedUrl =
                            (finalUrlObject.protocol === 'http:' || finalUrlObject.protocol === 'https:')
                            && requestedUrlObject.host.toLowerCase() === finalUrlObject.host.toLowerCase();

                        const normalizeHost = function (host) {
                            host = String(host || '').toLowerCase();

                            return host.indexOf('www.') === 0 ? host.substring(4) : host;
                        };

                        const normalizePath = function (path) {
                            try {
                                path = decodeURIComponent(path || '/');
                            } catch (e) {
                                path = path || '/';
                            }

                            if (path.length > 1) {
                                path = path.replace(/\/+$/, '');
                            }

                            return path || '/';
                        };

                        const homepageQueryStringsToIgnore = Array.isArray(wpacu_object.homepage_query_strings_to_ignore)
                            ? wpacu_object.homepage_query_strings_to_ignore
                            : [];

                        let finalUrlHasOnlyIgnoredHomepageQueryStrings = true;

                        finalUrlObject.searchParams.forEach(function (_value, paramName) {
                            if (homepageQueryStringsToIgnore.indexOf(paramName) === -1) {
                                finalUrlHasOnlyIgnoredHomepageQueryStrings = false;
                            }
                        });

                        redirectedUrlIsHomePage =
                            finalUrlHasOnlyIgnoredHomepageQueryStrings
                            && normalizeHost(finalUrlObject.host) === normalizeHost(homeUrlObject.host)
                            && normalizePath(finalUrlObject.pathname) === normalizePath(homeUrlObject.pathname);
                    } catch (e) {
                        canFetchRedirectedUrl = false;
                    }

                    const redirectedToHomePageFromDifferentContext =
                        canFetchRedirectedUrl
                        && redirectedUrlIsHomePage
                        && !wpacu_object.selected_context_is_homepage;

                    let redirectedUrlAction = '<p>' + escapeHtml(wpacu_object.redirected_fetch_external_url) + '</p>';

                    if (redirectedToHomePageFromDifferentContext) {
                        redirectedUrlAction = '<p>' + escapeHtml(wpacu_object.redirected_fetch_homepage_url) + '</p>';
                    } else if (canFetchRedirectedUrl) {
                        redirectedUrlAction =
                            '<p>' + escapeHtml(wpacu_object.redirected_fetch_suggestion) + '</p>'
                            + '<p><button type="button" class="button button-primary wpacu-manage-redirected-fetch-url"'
                            + ' data-wpacu-redirected-fetch-url="' + escapeAttribute(redirectedFetchUrl) + '">'
                            + escapeHtml(wpacu_object.redirected_fetch_button)
                            + '</button><span class="spinner" style="float: none; margin: 0 0 0 6px;"></span></p>'
                            + '<p><small>' + escapeHtml(wpacu_object.redirected_fetch_caution) + '</small></p>';
                    }

                    const output =
                        '<div class="wpacu-ajax-error" data-wpacu-ajax-error="1" data-wpacu-fetch-redirect-detected="1">'
                        + '<p><span class="dashicons dashicons-warning"></span> <strong>'
                        + escapeHtml(wpacu_object.redirected_fetch_title)
                        + '</strong></p>'
                        + '<p>' + escapeHtml(wpacu_object.redirected_fetch_message) + '</p>'
                        + '<p><strong>' + escapeHtml(wpacu_object.redirected_fetch_requested_url) + '</strong><br />'
                        + '<code>' + escapeHtml(requestedUrl) + '</code></p>'
                        + '<p><strong>' + escapeHtml(wpacu_object.redirected_fetch_final_url) + '</strong><br />'
                        + '<code>' + escapeHtml(redirectedFetchUrl) + '</code></p>'
                        + redirectedUrlAction
                        + '</div>';

                    $(metaBoxContent).html(output);

                    $('.wpacu_asset_row, .wpacu-page-options .wpacu-assets-collapsible-content')
                        .removeClass('wpacu_loading');

                    $('#wpacu-assets-reloading-in-edit-post-area').remove();
                },

                wpacuParseContentsForDirectCall: function (contents, statusCode, finalUrl, requestedFetchUrl) {
                    requestedFetchUrl = requestedFetchUrl || wpacu_object.page_url;

                    if (finalUrl && $.fn.wpAssetCleanUp().wpacuUrlsPointToDifferentLocations(requestedFetchUrl, finalUrl)) {
                        $.fn.wpAssetCleanUp().wpacuShowRedirectedFetchNotice(requestedFetchUrl, finalUrl);
                        return;
                    }
                    if (contents.lastIndexOf(wpacu_object.start_del_e) < 0
                        || contents.lastIndexOf(wpacu_object.end_del_e) < 0
                        || contents.lastIndexOf(wpacu_object.start_del_h) < 0
                        || contents.lastIndexOf(wpacu_object.end_del_h) < 0
                    ) {
                        // Sometimes, 200 OK (success) is returned, but due to an issue with the page, the assets list is not retrieved
                        // Do further checks if any of the markers are missing (even if there are no assets to manage, they should be printed)
                        let wpacuOutputError = wpacu_object.ajax_direct_fetch_error_with_success_response;

                        // Strip tags (Source: https://css-tricks.com/snippets/javascript/strip-html-tags-in-javascript/)
                        wpacuOutputError = wpacuOutputError.replace(
                            /{wpacu_output}/,
                            contents.replace(/(<([^>]+)>)/ig, '')
                        );

                        // htmlEntities() PHP equivalent: https://css-tricks.com/snippets/javascript/htmlentities-for-javascript/
                        try {
                            wpacuOutputError = String(wpacuOutputError).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                        } catch (e) {
                            console.log(e);
                        }

                        $(metaBoxContent).html(wpacuOutputError);
                        return;
                    }

                    let wpacuListE = contents.substring(
                        (contents.lastIndexOf(wpacu_object.start_del_e) + wpacu_object.start_del_e.length),
                        contents.lastIndexOf(wpacu_object.end_del_e)
                    );

                    /*
                     * IMPORTANT NOTE: It looks like UglifyJS jas issues preserving comments that are after consecutive "var"
                     */
                    let wpacuListH = contents.substring(
                        (contents.lastIndexOf(wpacu_object.start_del_h) + wpacu_object.start_del_h.length),
                        contents.lastIndexOf(wpacu_object.end_del_h)
                    );

                    let dataGetLoadedAssets = {
                        'action'            : wpacu_object.plugin_prefix + '_get_loaded_assets',
                        'wpacu_list_e'      : wpacuListE,
                        'wpacu_list_h'      : wpacuListH,
                        'page_url'          : wpacu_object.page_url,
                        'redirected_to_url' : requestedFetchUrl !== wpacu_object.page_url ? requestedFetchUrl : '',
                        'force_manage_dash' : wpacu_object.force_manage_dash,
                        'wpacu_nonce'       : wpacu_object.wpacu_ajax_get_loaded_assets_nonce,
                        'wpacu_time_r'      : new Date().getTime()
                    };

                    dataGetLoadedAssets = $.fn.wpAssetCleanUp().addExtraRequiredParamsToGetLoadedAssets(dataGetLoadedAssets);

                    $.post(wpacu_object.ajax_url, dataGetLoadedAssets, function (response) {
                        let $response = $('<div>').append(
                            $.parseHTML(String(response), document, true)
                        );

                        if ($response.find('[data-wpacu-ajax-error="1"]').length > 0) {
                            $(metaBoxContent).html(response);

                            $('.wpacu_asset_row, .wpacu-page-options .wpacu-assets-collapsible-content')
                                .removeClass('wpacu_loading');

                            return;
                        }

                        $(metaBoxContent).html(response);
                        $.fn.wpAssetCleanUp().wpacuShowRedirectedFetchUrlReminder(requestedFetchUrl);

                        const is404Response = Number(statusCode) === 404;

                        if (is404Response) {
                            if (wpacu_object.page_type === '404') {
                                $(metaBoxContent).prepend(
                                    '<p><span class="dashicons dashicons-yes"></span> '
                                    + wpacu_object.server_returned_404_not_found_in_404_tab
                                    + '</p><hr />'
                                );
                            } else {
                                $(metaBoxContent).prepend(
                                    '<p><span class="dashicons dashicons-warning"></span> '
                                    + wpacu_object.server_returned_404_not_found
                                    + '</p><hr />'
                                );
                            }
                        }

                        if ($('#wpacu_dash_assets_manager_form').length > 0) {
                            $('#wpacu-update-button-area .submit input').removeClass('hidden');
                        }

                        setTimeout(function () {
                            $.fn.wpAssetCleanUp().cssJsManagerActions();
                            $('.wpacu_asset_row, .wpacu-page-options .wpacu-assets-collapsible-content').removeClass('wpacu_loading'); // hide loading spinner after post is updated


                            $.fn.wpAssetCleanUp().wpacuCheckSourcesFor404Errors();
                        }, 200);
                    });
                },

                wpacuAjaxGetAssetsArea: function (forceFetch, requestedFetchUrl) {
                    // A confirmed redirect changes only the URL used to collect assets.
                    // The selected post/archive context is still sent separately with the AJAX request.
                    requestedFetchUrl = requestedFetchUrl || wpacu_object.page_url;

                    // Do not make any AJAX call unless force fetch is enabled
                    if (!forceFetch && !$('#wpacu_ajax_fetch_assets_list_dashboard_view').length) {
                        return false;
                    }

                    // Was "Do not load Asset CleanUp Pro on this page (this will disable any functionality of the plugin)" ticked?
                    // Do not load any list! Instead, make an AJAX call to load the restricted area mentioning that the restriction took effect

                    let pageOptionNoPluginLoadTarget = '#wpacu_page_options_no_wpacu_load';
                    if ($(pageOptionNoPluginLoadTarget).length > 0 && $(pageOptionNoPluginLoadTarget).prop('checked')) {
                        let dataLoadPageRestrictedArea = {
                            'action'      : wpacu_object.plugin_prefix + '_load_page_restricted_area',
                            'post_id'     : wpacu_object.post_id,
                            'page_type'   : wpacu_object.page_type,
                            'wpacu_nonce' : wpacu_object.wpacu_ajax_load_page_restricted_area_nonce,
                            'wpacu_time_r'      : new Date().getTime()
                        };

                        $.post(wpacu_object.ajax_url, dataLoadPageRestrictedArea, function (response) {
                            if (!response) {
                                return false;
                            }

                            $(metaBoxContent).html(response);

                            $('.wpacu_asset_row, .wpacu-page-options .wpacu-assets-collapsible-content').removeClass('wpacu_loading'); // hide loading spinner after post is updated
                            $('#wpacu-assets-reloading-in-edit-post-area').remove();
                        });

                        return;
                    }

                    let dataDirect = {};

                    if (wpacu_object.dom_get_type === 'direct') {
                        dataDirect[wpacu_object.plugin_prefix + '_load']   = 1;
                        dataDirect['wpacu_time_r'] = new Date().getTime();

                        $.ajax({
                            method: 'GET',
                            url: requestedFetchUrl,
                            data: dataDirect,
                            xhrFields: { withCredentials: true }, // Ensures authentication cookies are sent
                            cache: false,
                            complete: function (xhr, textStatus) {
                                if (xhr.statusText === 'error') {
                                    // Make exception for 404 errors as there could be plugin used such as "404page – your smart custom 404 error page"
                                    if (xhr.status === 404) {
                                        $.fn.wpAssetCleanUp().wpacuParseContentsForDirectCall(xhr.responseText, xhr.status, xhr.responseURL, requestedFetchUrl);
                                        return;
                                    }

                                    // Strip any tags (Source: https://css-tricks.com/snippets/javascript/strip-html-tags-in-javascript/)
                                    let errorTextOutput = xhr.responseText.replace(/(<([^>]+)>)/ig, '');

                                    // htmlEntities() PHP equivalent: https://css-tricks.com/snippets/javascript/htmlentities-for-javascript/
                                    try {
                                        errorTextOutput = String(errorTextOutput).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                                    } catch (e) {
                                        console.log(e);
                                    }

                                    let wpacuOutputError = wpacu_object.ajax_direct_fetch_error;
                                    wpacuOutputError = wpacuOutputError.replace(/{wpacu_output}/, errorTextOutput);
                                    wpacuOutputError = wpacuOutputError.replace(/{wpacu_status_code_error}/, xhr.status);

                                    $(metaBoxContent).html(wpacuOutputError);
                                }
                            }
                        }).done(function (contents, _textStatus, jqXHR) {
                            // "Step 1" (Fetch the assets from the home page) is now completed
                            $('#wpacu-fetch-list-step-1-wrap').addClass('wpacu-completed');
                            $('#wpacu-fetch-list-step-1-status').html($('#wpacu-list-step-completed-status').html());

                            // "Step 2" is in progress, mark it as such
                            $('#wpacu-fetch-list-step-2-status').html($('#wpacu-list-step-default-status').html());

                            $.fn.wpAssetCleanUp().wpacuParseContentsForDirectCall(contents, jqXHR.status, jqXHR.responseURL, requestedFetchUrl);
                        });
                    } else if (wpacu_object.dom_get_type === 'wp_remote_post') {
                        let dataGetLoadedAssets = {
                            'action':             wpacu_object.plugin_prefix + '_get_loaded_assets',
                            'page_url':           wpacu_object.page_url,
                            'redirected_to_url':  requestedFetchUrl !== wpacu_object.page_url ? requestedFetchUrl : '',
                            'force_manage_dash':  wpacu_object.force_manage_dash,
                            'wpacu_nonce':        wpacu_object.wpacu_ajax_get_loaded_assets_nonce,
                            'wpacu_time_r':       new Date().getTime()
                        };

                        dataGetLoadedAssets = $.fn.wpAssetCleanUp().addExtraRequiredParamsToGetLoadedAssets(dataGetLoadedAssets);

                        $.post(wpacu_object.ajax_url, dataGetLoadedAssets, function (response) {
                            if (!response) {
                                return false;
                            }

                            let $response = $('<div>').append(
                                $.parseHTML(String(response), document, true)
                            );

                            if ($response.find('[data-wpacu-ajax-error="1"]').length > 0) {
                                $(metaBoxContent).html(response);

                                $('.wpacu_asset_row, .wpacu-page-options .wpacu-assets-collapsible-content')
                                    .removeClass('wpacu_loading');

                                $('#wpacu-assets-reloading-in-edit-post-area').remove();
                                return;
                            }

                            $(metaBoxContent).html(response);
                            $.fn.wpAssetCleanUp().wpacuShowRedirectedFetchUrlReminder(requestedFetchUrl);

                            const is404Response = $(metaBoxContent).find('#wpacu_is_404_wp_remote_post_response').attr('value') === '1';
    
                            if (is404Response) {
                                if (wpacu_object.page_type === '404') {
                                    $(metaBoxContent).prepend(
                                        '<p><span class="dashicons dashicons-yes"></span> '
                                            + wpacu_object.server_returned_404_not_found_in_404_tab
                                        + '</p><hr />'
                                    );
                                } else {
                                    $(metaBoxContent).prepend(
                                        '<p><span class="dashicons dashicons-warning"></span> '
                                            + wpacu_object.server_returned_404_not_found
                                        + '</p><hr />'
                                    );
                                }
                            }

                            if ($('#wpacu_dash_assets_manager_form').length > 0) {
                                $('#wpacu-update-button-area .submit input').removeClass('hidden');
                            }

                            setTimeout(function () {
                                $.fn.wpAssetCleanUp().cssJsManagerActions();

                                if ($('#wpacu-assets-reloading-in-edit-post-area').length > 0) {
                                    $('#wpacu-assets-reloading-in-edit-post-area').remove();
                                }


                                $.fn.wpAssetCleanUp().wpacuCheckSourcesFor404Errors();
                            }, 200);
                        });
                    }
                },

                wpacuParseResultsForHarcodedAssets: function (contents) {
                    if (contents.lastIndexOf(wpacu_object.start_del_h) < 0 || contents.lastIndexOf(wpacu_object.end_del_h) < 0) {
                        // error in fetching the list
                    }

                    // IMPORTANT NOTE: It looks like UglifyJS has issues preserving comments that are after consecutive "var"
                    let wpacuListH = contents.substring(
                        (contents.lastIndexOf(wpacu_object.start_del_h) + wpacu_object.start_del_h.length),
                        contents.lastIndexOf(wpacu_object.end_del_h)
                    );

                    let wpacuSettings = $('#wpacu-assets-collapsible-wrap-hardcoded-list').attr('data-wpacu-settings-frontend');

                    let dataGetLoadedHardcodedAssets = {
                        'action'          : wpacu_object.plugin_prefix + '_print_loaded_hardcoded_assets',
                        'wpacu_list_h'    : wpacuListH,
                        'wpacu_settings'  : wpacuSettings, // includes $data values as well (with rules) to pass to the hardcoded list
                        'wpacu_time_r'    : new Date().getTime(),
                        'wpacu_nonce'     : wpacu_object.wpacu_print_loaded_hardcoded_assets_nonce
                    };

                    dataGetLoadedHardcodedAssets = $.fn.wpAssetCleanUp().addExtraRequiredParamsToGetLoadedAssets(dataGetLoadedHardcodedAssets);

                    if ($.fn.wpAssetCleanUp().getParameterByName('wpacu_ignore_no_load_option') !== null) {
                        dataGetLoadedHardcodedAssets['wpacu_ignore_no_load_option'] = 1;
                    }

                    $.post(wpacu_object.ajax_url, dataGetLoadedHardcodedAssets, function (response) {
                        let $mainJQuerySelector = '#wpacu-assets-collapsible-wrap-hardcoded-list';

                        if ( ! response ) {
                            return;
                        }

                        if (response.includes('The security nonce is not valid')) {
                            $($mainJQuerySelector).find('> .wpacu-assets-collapsible-content').html(response);
                            return;
                        }

                        let responseJson = JSON.parse(response);

                        $('[data-wpacu-external-srcs-ref]').attr('data-wpacu-external-srcs-ref', responseJson.external_srcs_ref);

                        $.fn.wpAssetCleanUp().wpacuCheckSourcesFor404Errors();

                        $($mainJQuerySelector).find('> .wpacu-assets-collapsible-content').html(responseJson.output);
                        $($mainJQuerySelector).find('a.wpacu-assets-collapsible').append(responseJson.after_hardcoded_title);

                    });
                },

                wpacuCheckSourcesFor404Errors: function () {
                    let targetExternalSrcsRefAttr = 'data-wpacu-external-srcs-ref';

                    if ($('[' + targetExternalSrcsRefAttr + ']').length < 1) {
                        return;
                    }

                    let externalSrcsRef = $('[' + targetExternalSrcsRefAttr + ']').attr(targetExternalSrcsRefAttr);

                    if (externalSrcsRef) {
                        $.post(wpacu_object.ajax_url, {
                            'action': wpacu_object.plugin_prefix + '_check_external_urls_for_status_code',
                            'wpacu_nonce': wpacu_object.wpacu_ajax_check_external_urls_nonce,
                            'wpacu_external_srcs_ref': externalSrcsRef
                        }, function (response) {
                            let urlsList = (typeof response === 'string') ? $.parseJSON(response) : response;

                            if (! Array.isArray(urlsList)) {
                                return;
                            }

                            $.each(urlsList, function (index, sourceToHi) {
                                $('[data-wpacu-external-source="' + sourceToHi + '"]')
                                    .css({'color': '#cc0000'})
                                    .parent('div')
                                    .find('[data-wpacu-external-source-status]')
                                    .html('<small>* <em style="font-weight: 600;">' + wpacu_object.source_load_error_msg + '</em></small>');
                            });
                        });
                    }

                    },

                wpacuBytesToSize: function (bytes) {
                    /**
                     * Inspired from: https://web.archive.org/web/20120507054320/http://codeaid.net/javascript/convert-size-in-bytes-to-human-readable-format-(javascript)
                     * Bytes to KB
                     */
                    if (bytes === 0) {
                        return 'N/A';
                    }

                    return (bytes / 1024).toFixed(4) + ' KB';
                },

                wpacuAjaxUpdateKeepTheGroupsState: function (newState, btnIdClicked) {
                    let $stateArea = $('#wpacu-assets-groups-change-state-area'),
                        dataCurrentState = $stateArea.attr('data-wpacu-groups-current-state'),
                        requestSucceeded = false;

                    if (dataCurrentState === newState) {
                        $('#' + btnIdClicked).prop('disabled', false);
                        return;
                    }

                    let dataUpdateSetting = {
                        'action'                       : wpacu_object.plugin_prefix + '_update_settings',
                        'wpacu_nonce'                  : wpacu_object.wpacu_update_specific_settings_nonce,
                        'wpacu_update_keep_the_groups' : 'yes',
                        'wpacu_keep_the_groups_state'  : newState, // "expanded" or "contracted"
                        'wpacu_time_r'                 : new Date().getTime() // avoid any caching
                    };

                    $.ajax({
                        url: wpacu_object.ajax_url,
                        method: 'POST',
                        data: dataUpdateSetting,
                        dataType: 'text',
                        timeout: 15000
                    }).done(function (response) {
                        requestSucceeded = String(response || '').trim() === 'done';

                        if (requestSucceeded) {
                            $stateArea.attr('data-wpacu-groups-current-state', newState);
                        }
                    }).fail(function (jqXHR, textStatus) {
                        console.warn('Asset CleanUp: the asset-group state could not be saved (' + textStatus + ').');
                    }).always(function () {
                        if ( ! requestSucceeded) {
                            if (dataCurrentState === 'contracted' && typeof window.wpacuContractAllMainAreas === 'function') {
                                window.wpacuContractAllMainAreas();
                            } else if (typeof window.wpacuExpandAllMainAreas === 'function') {
                                window.wpacuExpandAllMainAreas();
                            }
                        }

                        $('#' + btnIdClicked).prop('disabled', false);
                    });
                },

                wpacuAjaxUpdateKeepTheAssetRowState: function (newState, handle, handleFor, $currentElement) {
                    let dataUpdateSetting = {
                            'action'                       : wpacu_object.plugin_prefix + '_update_asset_row_state',
                            'wpacu_update_asset_row_state' : 'yes',
                            'wpacu_asset_row_state'        : newState, // "expanded" or "contracted"
                            'wpacu_handle'                 : handle,
                            'wpacu_handle_for'             : handleFor,
                            'wpacu_time_r'                 : new Date().getTime(), // avoid any caching
                            'wpacu_nonce'                  : wpacu_object.wpacu_update_asset_row_state_nonce
                        },
                        $assetCell = $currentElement.closest('td[data-wpacu-row-status]'),
                        previousState = (newState === 'expanded') ? 'contracted' : 'expanded',
                        requestSucceeded = false;

                    $currentElement.addClass('wpacu_hide');

                    $.ajax({
                        url: wpacu_object.ajax_url,
                        method: 'POST',
                        data: dataUpdateSetting,
                        dataType: 'json',
                        timeout: 15000
                    }).done(function (response) {
                        requestSucceeded = Boolean(response && response.success === true);
                    }).fail(function (jqXHR, textStatus) {
                        console.warn('Asset CleanUp: the asset row state could not be saved (' + textStatus + ').');
                    }).always(function () {
                        if ( ! requestSucceeded) {
                            $assetCell.attr('data-wpacu-row-status', previousState);

                            if (previousState === 'contracted') {
                                $assetCell.find('.wpacu_handle_row_expanded_area').addClass('wpacu_hide');
                                $currentElement.find('span').removeClass('dashicons-minus').addClass('dashicons-plus');
                            } else {
                                $assetCell.find('.wpacu_handle_row_expanded_area').removeClass('wpacu_hide');
                                $currentElement.find('span').removeClass('dashicons-plus').addClass('dashicons-minus');
                            }
                        }

                        $currentElement.removeClass('wpacu_hide');
                    });
                },

                // This triggers when all asset rows from an area are expanded or contracted.
                wpacuAjaxUpdateAllAreaAssetsRowState: function (newState, handles, $areaWrap, previousAreaAssetStates) {
                    let dataUpdateSetting = {
                            'action'                             : wpacu_object.plugin_prefix + '_area_update_assets_row_state',
                            'wpacu_area_update_assets_row_state' : 'yes',
                            'wpacu_area_assets_row_state'        : newState, // "expanded" or "contracted"
                            'wpacu_area_handles'                 : handles,
                            'wpacu_time_r'                       : new Date().getTime(), // avoid any caching
                            'wpacu_nonce'                        : wpacu_object.wpacu_area_update_assets_row_state_nonce
                        },
                        requestSucceeded = false;

                    $areaWrap.find('.wpacu_handle_row_expand_contract').addClass('wpacu_hide');

                    $.ajax({
                        url: wpacu_object.ajax_url,
                        method: 'POST',
                        data: dataUpdateSetting,
                        dataType: 'json',
                        timeout: 15000
                    }).done(function (response) {
                        requestSucceeded = Boolean(response && response.success === true);
                    }).fail(function (jqXHR, textStatus) {
                        console.warn('Asset CleanUp: the asset-area state could not be saved (' + textStatus + ').');
                    }).always(function () {
                        if ( ! requestSucceeded) {
                            $.each(previousAreaAssetStates || [], function (index, previousRowState) {
                                if ( ! previousRowState || ! previousRowState.element) {
                                    return;
                                }

                                let $assetCell = $(previousRowState.element),
                                    $toggle = $assetCell.find('.wpacu_handle_row_expand_contract');

                                $assetCell.attr('data-wpacu-row-status', previousRowState.state);

                                if (previousRowState.state === 'contracted') {
                                    $assetCell.find('.wpacu_handle_row_expanded_area').addClass('wpacu_hide');
                                    $toggle.find('span').removeClass('dashicons-minus').addClass('dashicons-plus');
                                } else {
                                    $assetCell.find('.wpacu_handle_row_expanded_area').removeClass('wpacu_hide');
                                    $toggle.find('span').removeClass('dashicons-plus').addClass('dashicons-minus');
                                }
                            });
                        }

                        $areaWrap.find('.wpacu_handle_row_expand_contract').removeClass('wpacu_hide');
                    });
                },

                wpacuTriggerAdjustTextAreaHeightAllTextareas: function () {
                    // We use the "data-wpacu-adapt-height" attribute as a marker
                    let wpacuTextAreas = [].slice.call(document.querySelectorAll('textarea[data-wpacu-adapt-height="1"]'));

                    // Iterate through all the textareas on the page
                    wpacuTextAreas.forEach(function (el) {
                        // we need box-sizing: border-box, if the textarea has padding
                        el.style.boxSizing = el.style.mozBoxSizing = 'border-box';

                        // we don't need any scrollbars, do we? :)
                        el.style.overflowY = 'hidden';

                        // the minimum height initiated through the "rows" attribute
                        let minHeight = el.scrollHeight;

                        el.addEventListener('input', function () {
                            $.fn.wpAssetCleanUp().wpacuAdjustTextareaHeight(el, minHeight);
                        });

                        // we have to readjust when window size changes (e.g. orientation change)
                        window.addEventListener('resize', function () {
                            $.fn.wpAssetCleanUp().wpacuAdjustTextareaHeight(el, minHeight);
                        });

                        // we adjust height to the initial content
                        $.fn.wpAssetCleanUp().wpacuAdjustTextareaHeight(el, minHeight);
                    });
                },

                wpacuAdjustTextareaHeight: function (el, minHeight) {
                    if (! el || ! el.offsetParent) {
                        return;
                    }

                    /* Original Source: http://bdadam.com/blog/automatically-adapting-the-height-textarea.html */
                    // compute the height difference which is caused by border and outline
                    let computedStyle = window.getComputedStyle(el);

                    let outerHeight = parseInt(computedStyle.height, 10);
                    let diff = outerHeight - el.clientHeight;

                    let lineHeight = parseInt(computedStyle.lineHeight, 10);

                    if (isNaN(lineHeight)) {
                        lineHeight = 18;
                    }

                    // Reset height so it can shrink
                    el.style.height = '0';

                    // Add one extra line after the actual content
                    let newHeight = el.scrollHeight + diff + lineHeight;

                    el.style.height = Math.max(minHeight, newHeight) + 'px';
                }
            }
        }
    })(jQuery);

    jQuery(document).ready(function ($) {
        /*
        * [START] "Settings" (menu)
         */
        $.fn.wpAssetCleanUpSettingsArea = function () {
            return {
                actions: function () {
                    /*
                    * Settings: A link is clicked that should trigger a vertical menu link from the plugin
                     */
                    $(document).on('click', 'a[data-wpacu-vertical-link-target]', function (e) {
                        e.preventDefault();
                        $.fn.wpAssetCleanUpSettingsArea().tabOpenSettingsArea(e, $(this).attr('data-wpacu-vertical-link-target'));
                    });

                    /*
                     * A vertical tab is clicked
                     */
                    $(document).on('click', 'a[data-wpacu-settings-tab-key]', function (e) {
                        e.preventDefault();
                        $.fn.wpAssetCleanUpSettingsArea().tabOpenSettingsArea(e, $(this).attr('data-wpacu-settings-tab-key'));
                    });

                    $(document).on('click', 'input[type="checkbox"]#wpacu_disable_rss_feed', function () {
                        if ($(this).is(':checked')) {
                            $('#wpacu_remove_main_feed_link, #wpacu_remove_comment_feed_link').prop('checked', true);
                        } else {
                            $('#wpacu_remove_main_feed_link, #wpacu_remove_comment_feed_link').prop('checked', false);
                        }
                    });

                    /*
                    * Settings: Sub-tab within tab clicked
                    */
                    $(document).on('click', 'input.wpacu-nav-input-sub-tab-area', function (e) {
                        if ($(this).closest('.wpacu-sub-tabs-wrap').hasClass('wpacu-tabs-not-ready')) {
                            e.preventDefault();
                            return false;
                        }

                        $('.wpacu-sub-tabs-item').removeClass('wpacu-visible');

                        if ($(this).is(':checked')) {
                            let refId = $(this).attr('id');
                            $('#' + refId + '-area').addClass('wpacu-visible');

                            let $mainTabArea = $(this).parent('.wpacu-sub-tabs-wrap').parents('.wpacu-settings-tab-content');
                            let mainTabAreaId = $mainTabArea.attr('id');

                            $.fn.wpAssetCleanUpSettingsArea().updateUriParamWithTabArea(mainTabAreaId);
                            $.fn.wpAssetCleanUpSettingsArea().updateUriParamWithSubTabArea($(this).val());

                            setTimeout(function () {
                                $.fn.wpAssetCleanUp().wpacuTriggerAdjustTextAreaHeightAllTextareas();
                            }, 0);
                        }
                    });

                    $('.wpacu-sub-tabs-wrap').removeClass('wpacu-tabs-not-ready');

                    /* [Start] Enable/Disable Status circles */
                    $(document).on('click', 'input[id$="_enable"], input[id$="_enabled"]', function () {
                        var $input = $(this);
                        var inputId = $input.attr('id');

                        // Safety check
                        if ( ! inputId ) {
                            return;
                        }

                        var $linkedElements = $('[data-linked-to="' + inputId + '"]');

                        // Nothing linked? Stop here
                        if (!$linkedElements.length) {
                            return;
                        }

                        var $statusCircle = $linkedElements.find('.wpacu-circle-status');

                        if (!$statusCircle.length) {
                            return;
                        }

                        $statusCircle
                            .toggleClass('wpacu-on', $input.prop('checked'))
                            .toggleClass('wpacu-off', !$input.prop('checked'));
                    });
                    /* [End] Enable/Disable Status circles */

                    /* [Start] Inline Stylesheet (.css) Files Smaller Than (x) KB */
                    $(document).on('click', '#wpacu_inline_css_files_below_size_checkbox', function () {
                        // The checkbox is not 'checked' and it was clicked
                        if ($(this).is(':checked')) {
                            $('#wpacu_inline_css_files_enable').prop('checked', true).trigger('tick');
                        } else {
                            if ($('#wpacu_inline_css_files_list').val() === '') {
                                $('#wpacu_inline_css_files_enable').prop('checked', false).trigger('tick');
                            }
                        }
                    });
                    /* [End] Inline Stylesheet (.css) Files Smaller Than (x) KB */

                    /* [Start] Inline JavaScript (.js) Files Smaller Than (x) KB */
                    $(document).on('click', '#wpacu_inline_js_files_below_size_checkbox', function () {
                        // The checkbox is not 'checked' and it was clicked
                        if ($(this).is(':checked')) {
                            if (!confirm(wpacu_object.inline_auto_js_files_confirm_msg)) {
                                return false;
                            }

                            $('#wpacu_inline_js_files_enable').prop('checked', true).trigger('tick');
                        } else {
                            if ($('#wpacu_inline_js_files_list').val() === '') {
                                $('#wpacu_inline_js_files_enable').prop('checked', false).trigger('tick');
                            }
                        }
                    });
                    /* [End] Inline JavaScript (.js) Files Smaller Than (x) KB */

                    // "Manage in the Dashboard?" Clicked
                    $(document).on('click', '#wpacu_dashboard', function () {
                        if ($(this).prop('checked')) {
                            $('#wpacu-settings-assets-retrieval-mode, .wpacu-cssjs-embedded-manager').show();
                            } else {
                            $('#wpacu-settings-assets-retrieval-mode, .wpacu-cssjs-embedded-manager').hide();
                            }
                    });

                    // "Manage in the Dashboard?" radio selection
                    $(document).on('change', '.wpacu-dom-get-type-selection', function () {
                        if ($(this).is(':checked')) {
                            $('.wpacu-dom-get-type-info').hide();
                            $('#' + $(this).attr('data-target')).fadeIn('fast');
                        }
                    });

                    // "Manage in the Front-end?" Clicked
                    $(document).on('click', '#wpacu_frontend', function () {
                        if ($(this).prop('checked')) {
                            $('#wpacu-settings-frontend-exceptions').show();
                        } else {
                            $('#wpacu-settings-frontend-exceptions').hide();
                        }
                    });

                    // Google Fonts: Load Optimizer (render-blocking or asynchronous)
                    $(document).on('change', '.google_fonts_combine_type', function () {
                        $('.wpacu_google_fonts_combine_type_area').hide();

                        if ($(this).val() === 'async') {
                            $('#wpacu_google_fonts_combine_type_async_info_area').fadeIn();
                        } else if ($(this).val() === 'async_preload') {
                            $('#wpacu_google_fonts_combine_type_async_preload_info_area').fadeIn();
                        } else {
                            $('#wpacu_google_fonts_combine_type_rb_info_area').fadeIn();
                        }
                    });

                    if (  $('#wpacu-allow-manage-assets-to-select-list-area').length > 0 &&
                        ! $('#wpacu-allow-manage-assets-to-select-list-area').hasClass('wpacu_hide') &&
                        $('#wpacu-allow-manage-assets-to-select-list').hasClass('wpacu_chosen_can_be_later_enabled')
                    ) {
                        setTimeout(function () {
                            window.wpacuInitChosen(jQuery('#wpacu-allow-manage-assets-to-select-list'));
                        }, 200);
                    }

                    $('#wpacu-allow-manage-assets-to-select').on('click change', function () {
                        if ($(this).val() === 'chosen') {
                            $('#wpacu-allow-manage-assets-to-select-list-area').removeClass('wpacu_hide');
                            setTimeout(function () {
                                if (jQuery('#wpacu-allow-manage-assets-to-select-list').hasClass('wpacu_chosen_can_be_later_enabled')) {
                                    window.wpacuInitChosen(jQuery('#wpacu-allow-manage-assets-to-select-list'));
                                }
                            }, 200);
                        } else {
                            $('#wpacu-allow-manage-assets-to-select-list-area').addClass('wpacu_hide');
                        }
                    });

                    $('#wpacu_assets_list_layout').on('click change', function () {
                        if ($(this).val() === 'by-location') {
                            $('#wpacu-assets-list-by-location-selected').fadeIn('fast');
                        } else {
                            $('#wpacu-assets-list-by-location-selected').fadeOut('fast');
                        }
                    });

                    $('#wpacu_disable_jquery_migrate').on('click', function () {
                        // It was checked and the user unchecked it
                        if ( ! $(this).is(':checked') ) {
                            return true;
                        }

                        // It was unchecked and the user checked it, needs confirmation
                        // Otherwise, it would be reversed as not checked
                        if ($(this).is(':checked') && confirm(wpacu_object.jquery_migration_disable_confirm_msg)) {
                            return true;
                        } else {
                            // Not confirmed?
                            $(this).prop('checked', false);
                            return false;
                        }
                    });

                    $('#wpacu_disable_comment_reply').on('click', function () {
                        // It was checked and the user unchecked it
                        if ( ! $(this).is(':checked') ) {
                            return true;
                        }

                        // It was unchecked and the user checked it, needs confirmation
                        // Otherwise, it would be reversed as not checked
                        if ($(this).is(':checked') && confirm(wpacu_object.comment_reply_disable_confirm_msg)) {
                            return true;
                        } else {
                            // Not confirmed?
                            $(this).prop('checked', false);
                            return false;
                        }
                    });

                    // "Settings" - When an option is enabled/disabled
                    $(document).on('click change tick', '[data-target-opacity]', function () {
                        var targetSelector = $(this).attr('data-target-opacity');

                        $(targetSelector).css({
                            'opacity': $(this).prop('checked') ? 1 : 0.4
                        });
                    });

                    $('#wpacu-show-assets-meta-box-checkbox').on('click change', function () {
                        if ($(this).prop('checked')) {
                            $('#wpacu-show-assets-enabled-area').show();
                            $('#wpacu-show-assets-disabled-area').hide();
                        } else {
                            $('#wpacu-show-assets-enabled-area').hide();
                            $('#wpacu-show-assets-disabled-area').show();
                        }
                    });

                    // "Combine JS Files" - "Select a combination method:"
                    $(document).on('change', '.wpacu-combine-loaded-js-level', function () {
                        if ($(this).is(':checked')) {
                            $('.wpacu_combine_loaded_js_level_area').removeClass('wpacu_active');
                            $('#' + $(this).attr('data-target')).addClass('wpacu_active');
                        }
                    });

                    $(document).on('click', '.wpacu-add-new-no-features-rule-row', function (e) {
                        e.preventDefault();

                        // Show the spinner
                        let $spinnerAfterLink = $(this).next('.wpacu-add-new-no-features-rule-row-loader');
                        $spinnerAfterLink.show();

                        $.post(wpacu_object.ajax_url, {
                            'action': wpacu_object.plugin_prefix + '_add_new_no_features_load_row',
                            'wpacu_nonce': wpacu_object.wpacu_add_new_no_features_load_row_nonce,
                            'wpacu_input_style': window.wpacuGetInputStyle(),
                            'wpacu_time_r': new Date().getTime()
                        }, function (newRowOutput) {
                            $('#wpacu-prevent-feature-rule-areas-wrap').append(newRowOutput);

                            let $lastNoFeatureAreaWithChosen = $('#wpacu-prevent-feature-rule-areas-wrap > .wpacu-prevent-feature-rule-area:last')
                                .find('.wpacu_chosen_can_be_later_enabled');

                            if ($lastNoFeatureAreaWithChosen.length > 0) {
                                window.wpacuInitChosen($lastNoFeatureAreaWithChosen);
                            }

                            // Hide the spinner
                            $spinnerAfterLink.hide();
                        });
                    });

                    $(document).on('click', '.wpacu-delete-no-features-rule-row', function (e) {
                        e.preventDefault();
                        let $lastNoFeatureArea = $(this).parent('.wpacu-prevent-feature-rule-area');
                        $lastNoFeatureArea.find(':input').prop('disabled', true);
                        $lastNoFeatureArea.remove();
                    });

                    // Submit button (Dashboard) is clicked
                    let settingSubmitBtn = '#wpacu-update-button-area input[type="submit"]';

                    // Show the loading spinner
                    $(document).on('submit', '#wpacu-settings-form, .wpacu_settings_form', function () {
                        $(settingSubmitBtn).attr('disabled', true);
                        $('#wpacu-updating-settings').addClass('wpacu-show').removeClass('wpacu-hide');
                    });

                    // Once the form is submitted, disable the submit button to prevent any double submission
                    // Settings & Homepage Buttons
                    $(document).on('submit', 'form#wpacu-settings-form, form#wpacu_dash_assets_manager_form', function () {
                        $(settingSubmitBtn).attr('disabled', true);
                        $('#wpacu-updating-settings').show();
                        return true;
                    });

                    // [START] AJAX user search (for plugin access)
                    // Enabled in "Settings" -> "Plugin Usage Preferences" -> "Plugin Access"
                    // when the site has too many non-administrator users for one full drop-down.
                    var $specificNonAdminUserSearchArea = $('[data-wpacu-specific-non-admin-user-search="1"]');

                    // Backward-compatible fallback for a build where the JavaScript master
                    // is compiled before the matching PHP template has been synchronized.
                    if ($specificNonAdminUserSearchArea.length < 1 && $('#wpacu-access-via-specific-users-dd-search').length > 0) {
                        $specificNonAdminUserSearchArea = $('#wpacu-area-option-give-access-specific-non-admin-users').first();
                    }

                    if ($specificNonAdminUserSearchArea.length > 0) {
                        var $enhancedUserSearchSelect = $('#wpacu-access-via-specific-users-dd-search');
                        var $nativeUserSearchInput = $('#wpacu-access-via-specific-users-native-search');
                        var $nativeUserSearchResults = $('#wpacu-access-via-specific-users-native-results');
                        var $nativeUserSearchResultsWrap = $('#wpacu-access-via-specific-users-native-results-wrap');
                        var $nativeUserSearchAddButton = $('#wpacu-access-via-specific-users-native-add');
                        var $userSearchStatus = $('#wpacu-access-via-specific-users-search-status');
                        var $userSearchingNotice = $('#wpacu-access-via-specific-user-searching-notice');
                        var $userAddingNotice = $('#wpacu-access-via-specific-user-adding-notice');
                        var useEnhancedUserSearch = window.wpacuUsesEnhancedInputs() && $enhancedUserSearchSelect.length > 0;
                        var minimumUserSearchLength = 2;
                        var userSearchTimer = null;
                        var userSearchRequest = null;
                        var latestUserSearchTerm = '';

                        function wpacuUserSearchMessage(objectKey, fallback) {
                            if (typeof wpacu_object !== 'undefined' && wpacu_object[objectKey]) {
                                return wpacu_object[objectKey];
                            }

                            return fallback;
                        }

                        function wpacuSetUserSearchStatus(message) {
                            if ($userSearchStatus.length > 0) {
                                $userSearchStatus.text(message || '');
                            }
                        }

                        function wpacuAbortNonAdminUserSearchRequest() {
                            if (userSearchRequest && typeof userSearchRequest.abort === 'function') {
                                var requestToAbort = userSearchRequest;
                                userSearchRequest = null;
                                requestToAbort.abort();
                            }
                        }

                        function wpacuGetSelectedNonAdminUserIds() {
                            var selectedUserIds = [];

                            $('[data-wpacu-non-admin-chosen-user-id]').each(function () {
                                selectedUserIds.push(String($(this).attr('data-wpacu-non-admin-chosen-user-id')));
                            });

                            return selectedUserIds;
                        }

                        function wpacuNormalizeNonAdminUserSearchResponse(response) {
                            if (response && response.success === true && response.data && $.isArray(response.data.results)) {
                                return response.data.results;
                            }

                            // Temporary compatibility with an older PHP endpoint that returns
                            // raw <option> elements instead of a WordPress JSON response.
                            if (typeof response === 'string') {
                                var results = [];
                                var $legacyOptions = $('<select>').html(response).find('option');

                                $legacyOptions.each(function () {
                                    var userId = parseInt($(this).val(), 10);

                                    if (userId) {
                                        results.push({
                                            id: userId,
                                            text: $(this).text()
                                        });
                                    }
                                });

                                return results;
                            }

                            return null;
                        }

                        function wpacuNormalizeAddNonAdminUserResponse(response) {
                            if (response && response.success === true && response.data && response.data.html) {
                                return String(response.data.html);
                            }

                            // Temporary compatibility with the previous raw-HTML add endpoint.
                            if (typeof response === 'string') {
                                var legacyHtml = $.trim(response);

                                if (legacyHtml && legacyHtml !== '0' && legacyHtml !== '-1') {
                                    return legacyHtml;
                                }
                            }

                            return '';
                        }

                        function wpacuBindEnhancedUserSearchInput() {
                            if ( ! useEnhancedUserSearch) {
                                return $();
                            }

                            window.wpacuInitChosen($enhancedUserSearchSelect);

                            var $chosenSearchInput = window.wpacuGetChosenSearchInput($enhancedUserSearchSelect);

                            $chosenSearchInput
                                .off('.wpacuNonAdminUserSearch')
                                .on('input.wpacuNonAdminUserSearch keyup.wpacuNonAdminUserSearch', function () {
                                    wpacuScheduleNonAdminUserSearch($(this).val());
                                });

                            return $chosenSearchInput;
                        }

                        function wpacuClearNonAdminUserSearchResults(preserveSearchTerm) {
                            if (useEnhancedUserSearch) {
                                $enhancedUserSearchSelect.empty().append($('<option>', { value: '' }));
                                window.wpacuRefreshChosen($enhancedUserSearchSelect);

                                var $chosenSearchInput = wpacuBindEnhancedUserSearchInput();

                                if (preserveSearchTerm) {
                                    $chosenSearchInput.val(latestUserSearchTerm);
                                }

                                return;
                            }

                            $nativeUserSearchResults.empty().prop('disabled', true);
                            $nativeUserSearchAddButton.prop('disabled', true);
                            $nativeUserSearchResultsWrap.addClass('wpacu_hide');
                        }

                        function wpacuRenderNonAdminUserSearchResults(results, searchTerm) {
                            var selectedUserIds = wpacuGetSelectedNonAdminUserIds();
                            var usableResults = [];

                            $.each(results || [], function (_index, result) {
                                if ( ! result || ! result.id || selectedUserIds.indexOf(String(result.id)) !== -1) {
                                    return;
                                }

                                usableResults.push(result);
                            });

                            if (useEnhancedUserSearch) {
                                $enhancedUserSearchSelect.empty().append($('<option>', { value: '' }));

                                $.each(usableResults, function (_index, result) {
                                    $enhancedUserSearchSelect.append(
                                        $('<option>', {
                                            value: String(result.id),
                                            text: String(result.text || '')
                                        })
                                    );
                                });

                                window.wpacuRefreshChosen($enhancedUserSearchSelect);
                                $enhancedUserSearchSelect.trigger('chosen:open');

                                var $chosenSearchInput = wpacuBindEnhancedUserSearchInput();
                                $chosenSearchInput.val(searchTerm).focus().trigger('keyup.chosen');
                            } else {
                                $nativeUserSearchResults.empty();

                                $.each(usableResults, function (_index, result) {
                                    $nativeUserSearchResults.append(
                                        $('<option>', {
                                            value: String(result.id),
                                            text: String(result.text || '')
                                        })
                                    );
                                });

                                if (usableResults.length > 0) {
                                    $nativeUserSearchResults
                                        .prop('disabled', false)
                                        .prop('selectedIndex', -1);
                                    $nativeUserSearchResultsWrap.removeClass('wpacu_hide');
                                    $nativeUserSearchAddButton.prop('disabled', true);
                                } else {
                                    $nativeUserSearchResults.prop('disabled', true);
                                    $nativeUserSearchResultsWrap.addClass('wpacu_hide');
                                    $nativeUserSearchAddButton.prop('disabled', true);
                                }
                            }

                            if (usableResults.length > 0) {
                                wpacuSetUserSearchStatus(
                                    wpacuUserSearchMessage('user_search_results_found', 'Matching users found: %d.')
                                        .replace('%d', usableResults.length)
                                );
                            } else {
                                wpacuSetUserSearchStatus(
                                    wpacuUserSearchMessage('user_search_no_results', 'No matching non-administrator users were found.')
                                );
                            }
                        }

                        function wpacuRequestNonAdminUserSearch(searchTerm) {
                            latestUserSearchTerm = $.trim(String(searchTerm || ''));

                            wpacuAbortNonAdminUserSearchRequest();

                            if (latestUserSearchTerm.length < minimumUserSearchLength) {
                                wpacuClearNonAdminUserSearchResults(true);
                                wpacuSetUserSearchStatus(
                                    latestUserSearchTerm.length > 0
                                        ? wpacuUserSearchMessage('user_search_min_characters', 'Type at least 2 characters to search.')
                                        : ''
                                );
                                $userSearchingNotice.addClass('wpacu_hide');
                                $specificNonAdminUserSearchArea.removeAttr('aria-busy');
                                return;
                            }

                            var requestedSearchTerm = latestUserSearchTerm;
                            wpacuClearNonAdminUserSearchResults(true);
                            wpacuSetUserSearchStatus('');
                            $userSearchingNotice.removeClass('wpacu_hide');
                            $specificNonAdminUserSearchArea.attr('aria-busy', 'true');

                            var userSearchRequestData = {
                                action: wpacu_object.plugin_prefix + '_search_non_admin_users_for_dd',
                                wpacu_query: requestedSearchTerm,
                                wpacu_exclude_user_ids: wpacuGetSelectedNonAdminUserIds(),
                                wpacu_time_r: new Date().getTime()
                            };

                            if (wpacu_object.wpacu_search_non_admin_users_for_dd_nonce) {
                                userSearchRequestData.wpacu_security = wpacu_object.wpacu_search_non_admin_users_for_dd_nonce;
                            }

                            var currentUserSearchRequest = $.ajax({
                                method: 'post',
                                url: wpacu_object.ajax_url,
                                data: userSearchRequestData,
                                cache: false
                            });

                            userSearchRequest = currentUserSearchRequest;

                            currentUserSearchRequest.done(function (response) {
                                if (requestedSearchTerm !== latestUserSearchTerm) {
                                    return;
                                }

                                var normalizedResults = wpacuNormalizeNonAdminUserSearchResponse(response);

                                if (normalizedResults === null) {
                                    wpacuClearNonAdminUserSearchResults(true);
                                    wpacuSetUserSearchStatus(
                                        wpacuUserSearchMessage('user_search_request_failed', 'The user search could not be completed. Please try again.')
                                    );
                                    return;
                                }

                                wpacuRenderNonAdminUserSearchResults(normalizedResults, requestedSearchTerm);
                            }).fail(function (_jqXhr, textStatus) {
                                if (textStatus === 'abort' || requestedSearchTerm !== latestUserSearchTerm) {
                                    return;
                                }

                                wpacuClearNonAdminUserSearchResults(true);
                                wpacuSetUserSearchStatus(
                                    wpacuUserSearchMessage('user_search_request_failed', 'The user search could not be completed. Please try again.')
                                );
                            }).always(function () {
                                if (userSearchRequest !== currentUserSearchRequest) {
                                    return;
                                }

                                $userSearchingNotice.addClass('wpacu_hide');
                                $specificNonAdminUserSearchArea.removeAttr('aria-busy');
                                userSearchRequest = null;
                            });
                        }

                        function wpacuScheduleNonAdminUserSearch(searchTerm) {
                            latestUserSearchTerm = $.trim(String(searchTerm || ''));
                            clearTimeout(userSearchTimer);

                            if (latestUserSearchTerm.length < minimumUserSearchLength) {
                                wpacuRequestNonAdminUserSearch(latestUserSearchTerm);
                                return;
                            }

                            userSearchTimer = setTimeout(function () {
                                wpacuRequestNonAdminUserSearch(latestUserSearchTerm);
                            }, 300);
                        }

                        function wpacuResetNonAdminUserSearch(focusSearchField) {
                            clearTimeout(userSearchTimer);
                            wpacuAbortNonAdminUserSearchRequest();
                            latestUserSearchTerm = '';
                            wpacuClearNonAdminUserSearchResults(false);
                            wpacuSetUserSearchStatus('');
                            $userSearchingNotice.addClass('wpacu_hide');
                            $specificNonAdminUserSearchArea.removeAttr('aria-busy');

                            if (useEnhancedUserSearch) {
                                var $chosenSearchInput = wpacuBindEnhancedUserSearchInput();
                                $chosenSearchInput.val('');

                                if (focusSearchField) {
                                    $chosenSearchInput.focus();
                                }
                            } else {
                                $nativeUserSearchInput.val('');

                                if (focusSearchField) {
                                    $nativeUserSearchInput.focus();
                                }
                            }
                        }

                        function wpacuSetAddNonAdminUserBusy(isBusy) {
                            $userAddingNotice.toggleClass('wpacu_hide', ! isBusy);
                            $specificNonAdminUserSearchArea.attr('aria-busy', isBusy ? 'true' : 'false');

                            if (useEnhancedUserSearch) {
                                $enhancedUserSearchSelect.prop('disabled', isBusy);
                                window.wpacuRefreshChosen($enhancedUserSearchSelect);
                            } else {
                                $nativeUserSearchInput.prop('disabled', isBusy);
                                $nativeUserSearchResults.prop('disabled', isBusy || $nativeUserSearchResults.find('option').length === 0);
                                $nativeUserSearchAddButton.prop('disabled', isBusy || ! $nativeUserSearchResults.val());
                            }
                        }

                        function wpacuAddNonAdminUserToAccessList(userId) {
                            userId = parseInt(userId, 10);

                            if ( ! userId) {
                                return;
                            }

                            if ($('[data-wpacu-non-admin-chosen-user-id="' + userId + '"]').length > 0) {
                                window.alert(
                                    wpacuUserSearchMessage('user_already_added', 'This non-administrator user is already in the access list.')
                                );
                                wpacuResetNonAdminUserSearch(true);
                                return;
                            }

                            clearTimeout(userSearchTimer);
                            wpacuAbortNonAdminUserSearchRequest();
                            $userSearchingNotice.addClass('wpacu_hide');
                            wpacuSetAddNonAdminUserBusy(true);

                            var addUserRequestData = {
                                action: wpacu_object.plugin_prefix + '_add_non_admin_users_to_chosen_list',
                                wpacu_user_id: userId,
                                wpacu_time_r: new Date().getTime()
                            };

                            if (wpacu_object.wpacu_search_non_admin_users_for_dd_nonce) {
                                addUserRequestData.wpacu_security = wpacu_object.wpacu_search_non_admin_users_for_dd_nonce;
                            }

                            $.ajax({
                                method: 'post',
                                url: wpacu_object.ajax_url,
                                data: addUserRequestData,
                                cache: false
                            }).done(function (response) {
                                var selectedUserHtml = wpacuNormalizeAddNonAdminUserResponse(response);

                                if ( ! selectedUserHtml) {
                                    var responseMessage = response && response.data && response.data.message
                                        ? response.data.message
                                        : wpacuUserSearchMessage('user_add_request_failed', 'The user could not be added. Please try again.');

                                    window.alert(responseMessage);
                                    return;
                                }

                                var $selectedUsersList = $('[data-wpacu-non-admin-chosen-users-list]');
                                $selectedUsersList.append(selectedUserHtml);

                                $selectedUsersList
                                    .children('[data-wpacu-non-admin-chosen-user-id]:last')
                                    .hide()
                                    .fadeIn(300);

                                // Re-enable the search controls before attempting to restore focus.
                                wpacuSetAddNonAdminUserBusy(false);
                                wpacuResetNonAdminUserSearch(true);
                            }).fail(function (jqXhr) {
                                var responseMessage = jqXhr.responseJSON && jqXhr.responseJSON.data && jqXhr.responseJSON.data.message
                                    ? jqXhr.responseJSON.data.message
                                    : wpacuUserSearchMessage('user_add_request_failed', 'The user could not be added. Please try again.');

                                window.alert(responseMessage);
                            }).always(function () {
                                wpacuSetAddNonAdminUserBusy(false);
                            });
                        }

                        if (useEnhancedUserSearch) {
                            window.wpacuInitChosen($enhancedUserSearchSelect);
                            wpacuBindEnhancedUserSearchInput();

                            $enhancedUserSearchSelect
                                .off('change.wpacuNonAdminUserSearch')
                                .on('change.wpacuNonAdminUserSearch', function () {
                                    wpacuAddNonAdminUserToAccessList($(this).val());
                                });
                        } else {
                            $nativeUserSearchInput
                                .off('.wpacuNonAdminUserSearch')
                                .on('input.wpacuNonAdminUserSearch search.wpacuNonAdminUserSearch', function () {
                                    wpacuScheduleNonAdminUserSearch($(this).val());
                                })
                                .on('keydown.wpacuNonAdminUserSearch', function (event) {
                                    if (event.key === 'ArrowDown' && ! $nativeUserSearchResults.prop('disabled')) {
                                        event.preventDefault();
                                        $nativeUserSearchResults.focus();
                                    }
                                });

                            $nativeUserSearchResults
                                .off('.wpacuNonAdminUserSearch')
                                .on('change.wpacuNonAdminUserSearch', function () {
                                    $nativeUserSearchAddButton.prop('disabled', ! $(this).val());
                                })
                                .on('dblclick.wpacuNonAdminUserSearch', function () {
                                    wpacuAddNonAdminUserToAccessList($(this).val());
                                })
                                .on('keydown.wpacuNonAdminUserSearch', function (event) {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        wpacuAddNonAdminUserToAccessList($(this).val());
                                    }
                                });

                            $nativeUserSearchAddButton
                                .off('click.wpacuNonAdminUserSearch')
                                .on('click.wpacuNonAdminUserSearch', function () {
                                    wpacuAddNonAdminUserToAccessList($nativeUserSearchResults.val());
                                });
                        }

                        // Remove a selected non-administrator user from the list.
                        $(document)
                            .off('click.wpacuNonAdminUserSearch', '[data-clear-wpacu-non-admin-chosen-user-id]')
                            .on('click.wpacuNonAdminUserSearch', '[data-clear-wpacu-non-admin-chosen-user-id]', function (event) {
                                event.preventDefault();

                                var selectedUserId = $(this).attr('data-clear-wpacu-non-admin-chosen-user-id');
                                var $selectedUserRow = $('[data-wpacu-non-admin-chosen-user-id="' + selectedUserId + '"]');

                                // Make the removal effective immediately for form submission,
                                // while keeping the short fade-out as visual feedback.
                                $selectedUserRow.find(':input').prop('disabled', true);
                                $selectedUserRow.fadeOut(300, function () {
                                    $(this).remove();

                                    if (latestUserSearchTerm.length >= minimumUserSearchLength) {
                                        wpacuScheduleNonAdminUserSearch(latestUserSearchTerm);
                                    }
                                });
                            });
                    }
                    // [END] AJAX user search (for plugin access)
                },

                tabOpenSettingsArea: function (evt, settingName) {
                    /*
                    * Only relevant in the "Settings" area
                    */
                    evt.preventDefault();

                    let i, wpacuVerticalTabContent, wpacuVerticalTabLinks;

                    wpacuVerticalTabContent = document.getElementsByClassName("wpacu-settings-tab-content");

                    for (i = 0; i < wpacuVerticalTabContent.length; i++) {
                        wpacuVerticalTabContent[i].style.display = "none";
                    }

                    wpacuVerticalTabLinks = document.getElementsByClassName("wpacu-settings-tab-link");

                    for (i = 0; i < wpacuVerticalTabLinks.length; i++) {
                        wpacuVerticalTabLinks[i].className = wpacuVerticalTabLinks[i].className.replace(" active", "");
                    }

                    document.getElementById(settingName).style.display = "table-cell";

                    $('a[href="#' + settingName + '"]').addClass('active');
                    $('#wpacu-selected-tab-area').val(settingName);

                    $.fn.wpAssetCleanUpSettingsArea().updateUriParamWithTabArea(settingName);

                    // Any sub-tabs within the tab area?
                    let $anyAlreadySelectedFirstSubTabInput = $('#' + settingName)
                        .find('.wpacu-sub-tabs-wrap .wpacu-nav-input')
                        .filter(':checked')
                        .first();

                    if ($anyAlreadySelectedFirstSubTabInput.length > 0) {
                        $('#' + $anyAlreadySelectedFirstSubTabInput.attr('id')).prop('checked', true);
                        $('#' + $anyAlreadySelectedFirstSubTabInput.attr('id') + '-area').addClass('wpacu-visible');
                        $.fn.wpAssetCleanUpSettingsArea().updateUriParamWithSubTabArea($anyAlreadySelectedFirstSubTabInput.val());
                    } else {
                        $.fn.wpAssetCleanUpSettingsArea().updateUriParamWithSubTabArea('');
                    }

                    setTimeout(function () {
                        $.fn.wpAssetCleanUp().wpacuTriggerAdjustTextAreaHeightAllTextareas();
                    }, 0);

                    },

                updateUriParamWithTabArea: function (selectedTabArea) {
                    // Construct URLSearchParams object instance from current URL querystring.
                    var queryParams = new URLSearchParams(window.location.search);

                    // Set new or modify existing parameter value.
                    queryParams.set('wpacu_selected_tab_area', selectedTabArea);

                    // Replace current querystring with the new one.
                    history.replaceState(null, null, '?' + queryParams.toString());
                },

                updateUriParamWithSubTabArea: function (selectedSubTabArea) {
                    // Construct URLSearchParams object instance from current URL querystring.
                    var queryParams = new URLSearchParams(window.location.search);

                    if (selectedSubTabArea !== '') {
                        // Set new or modify existing parameter value.
                        queryParams.set('wpacu_selected_sub_tab_area', selectedSubTabArea);
                    } else {
                        // Remove existing parameter value.
                        queryParams.delete('wpacu_selected_sub_tab_area');
                    }

                    // Replace current querystring with the new one.
                    history.replaceState(null, null, '?' + queryParams.toString());

                    // Old reference fallback | $_REQUEST is used to get the value in case the URI param fails to update
                    $('#wpacu-selected-sub-tab-area').val(selectedSubTabArea);
                }
            }
        }
        $.fn.wpAssetCleanUpSettingsArea().actions();
        /*
        * [END] "Settings" (menu)
         */

        /*
        * [START] "Tools" (menu)
         */
        $.fn.wpAssetCleanUpToolsArea = function () {
            return {
                actions: function () {
                    /*
                    * "Tools" -> "Reset"
                    */
                    let wpacuResetDdSelector = '#wpacu-reset-drop-down', $wpacuOptionSelected, wpacuMsgToShow;
                    let $wpacuResetSubmitButton = $('#wpacu-reset-submit-btn');
                    let $wpacuResetConfirm = $('#wpacu-reset-confirm');

                    function wpacuUpdateResetSubmitButton() {
                        let wpacuResetAction = $(wpacuResetDdSelector).val();
                        let wpacuNeedsExplicitConfirmation = wpacuResetAction === 'reset_everything' ||
                            wpacuResetAction === 'remove_all_data_for_uninstall';
                        let wpacuCanSubmit = wpacuResetAction !== '' &&
                            ( ! wpacuNeedsExplicitConfirmation || $wpacuResetConfirm.is(':checked'));

                        $wpacuResetSubmitButton.prop('disabled', ! wpacuCanSubmit)
                            .toggleClass('button-primary', wpacuCanSubmit && ! wpacuNeedsExplicitConfirmation)
                            .toggleClass('button-secondary', ! wpacuCanSubmit)
                            .toggleClass('wpacu-button-destructive', wpacuNeedsExplicitConfirmation);
                    }

                    $(wpacuResetDdSelector).on('change', function () {
                        let wpacuResetAction = $(this).val();
                        let wpacuIsResetEverything = wpacuResetAction === 'reset_everything';
                        let wpacuNeedsExplicitConfirmation = wpacuIsResetEverything ||
                            wpacuResetAction === 'remove_all_data_for_uninstall';

                        $wpacuOptionSelected = $(this).find('option:selected');
                        $('[data-wpacu-reset-button-label]').text(
                            $wpacuOptionSelected.attr('data-submit-label') || $wpacuOptionSelected.text()
                        );

                        if (wpacuResetAction === '') {
                            $('#wpacu-warning-read').removeClass('wpacu-visible');
                        } else {
                            $('#wpacu-warning-read').addClass('wpacu-visible');
                        }

                        $('#wpacu-reset-additional-options')
                            .toggleClass('wpacu-visible', wpacuIsResetEverything)
                            .attr('aria-hidden', wpacuIsResetEverything ? 'false' : 'true');
                        $('#wpacu-license-data-remove-area, #wpacu-cache-assets-remove-area')
                            .toggleClass('wpacu-visible', wpacuIsResetEverything);
                        $('#wpacu-reset-confirm-area').toggleClass('wpacu-visible', wpacuNeedsExplicitConfirmation);

                        // A confirmation belongs to the action that was reviewed.
                        // Require a fresh confirmation whenever the selected action changes.
                        $wpacuResetConfirm.prop('checked', false);

                        $('.wpacu-tools-area .wpacu-warning').hide();
                        $('#' + $wpacuOptionSelected.attr('data-id')).show();

                        wpacuUpdateResetSubmitButton();
                    }).trigger('change');

                    $wpacuResetConfirm.on('change', wpacuUpdateResetSubmitButton);

                    $('#wpacu-reset-submit-btn').on('click', function () {
                        let wpacuResetAction = $(wpacuResetDdSelector).val();

                        let wpacuNeedsExplicitConfirmation = wpacuResetAction === 'reset_everything' ||
                            wpacuResetAction === 'remove_all_data_for_uninstall';

                        if (wpacuNeedsExplicitConfirmation && ! $wpacuResetConfirm.is(':checked')) {
                            return false;
                        }

                        if (wpacuResetAction === 'reset_settings') {
                            wpacuMsgToShow = wpacu_object.reset_settings_confirm_msg;
                        } else if (wpacuResetAction === 'reset_critical_css') {
                            wpacuMsgToShow = wpacu_object.reset_critical_css_confirm_msg;
                        } else if (wpacuResetAction === 'reset_plugins_manager_front') {
                            wpacuMsgToShow = wpacu_object.reset_plugins_manager_front_confirm_msg;
                        } else if (wpacuResetAction === 'reset_plugins_manager_dash') {
                            wpacuMsgToShow = wpacu_object.reset_plugins_manager_dash_confirm_msg;
                        } else if (wpacuResetAction === 'reset_everything_except_settings') {
                            wpacuMsgToShow = wpacu_object.reset_everything_except_settings_confirm_msg;
                        }

                        // Destructive full cleanup actions already have an explicit confirmation checkbox.
                        if ( ! wpacuNeedsExplicitConfirmation && ! confirm(wpacuMsgToShow)) {
                            return false;
                        }

                        $('#wpacu-action-confirmed').val('yes');
                    });

                    $('#wpacu-tools-form').on('submit', function () {
                        if ($('#wpacu-action-confirmed').val() !== 'yes') {
                            return false;
                        }

                        $wpacuResetSubmitButton.prop('disabled', true).attr('aria-busy', 'true');
                    });

                    /*
                    * "Tools" -> "Import"
                    */
                    var $importForm = $('#wpacu-import-form');
                    var $importFile = $importForm.find('#wpacu-import-file');
                    var $importButton = $importForm.find('button[type="submit"]');
                    var $importFileStatus = $importForm.find('[data-wpacu-import-file-status]');

                    $importFile.on('change', function () {
                        var file = this.files && this.files.length ? this.files[0] : null;
                        var isValidJson = file && /\.json$/i.test(file.name);
                        var maxFileBytes = Number($importFile.attr('data-max-bytes')) || 0;
                        var isWithinSizeLimit = ! file || ! maxFileBytes || file.size <= maxFileBytes;
                        var isValidImportFile = file && isValidJson && isWithinSizeLimit;

                        $importButton.prop('disabled', ! isValidImportFile);

                        if ( ! file) {
                            $importFileStatus.text($importFileStatus.attr('data-empty-label'));
                        } else if ( ! isValidJson) {
                            $importFileStatus.text($importFileStatus.attr('data-invalid-label'));
                        } else if ( ! isWithinSizeLimit) {
                            $importFileStatus.text($importFileStatus.attr('data-too-large-label'));
                        } else {
                            $importFileStatus.text(file.name + ' · ' + Math.max(1, Math.round(file.size / 1024)) + ' KB');
                        }
                    });

                    $(document).on('submit', '#wpacu-import-form', function () {
                        if ( ! confirm(wpacu_object.import_confirm_msg) ) {
                            return false;
                        }

                        var $button = $(this).find('button[type="submit"]');
                        $button.find('[data-wpacu-button-label]').text($button.attr('data-busy-label'));
                        $button.addClass('wpacu-importing').prop('disabled', true);
                    });

                    /*
                     * "Tools" -> "Export"
                     */
                    var $exportForm = $('#wpacu-export-form');
                    var $exportSelection = $exportForm.find('#wpacu-export-selection');
                    var $exportButton = $exportForm.find('button[type="submit"]');
                    var $exportDescription = $exportForm.find('[data-wpacu-export-description]');

                    $exportSelection.on('change', function () {
                        var selectedOption = this.options[this.selectedIndex];
                        $exportButton.prop('disabled', ! this.value);
                        $exportDescription.text(selectedOption ? selectedOption.getAttribute('data-description') : '');
                    }).trigger('change');

                    $exportForm.on('submit', function () {
                        var $label = $exportButton.find('[data-wpacu-button-label]');
                        var defaultLabel = $label.text();

                        $label.text($exportButton.attr('data-busy-label'));
                        $exportButton.prop('disabled', true).attr('aria-busy', 'true');

                        window.setTimeout(function () {
                            $label.text(defaultLabel);
                            $exportButton.prop('disabled', ! $exportSelection.val()).removeAttr('aria-busy');
                        }, 1500);
                    });
                }
            }
        }
        $.fn.wpAssetCleanUpToolsArea().actions();
        /*
        * [END] "Tools" (menu)
         */

        /*
         * [START] Front-end CSS/JS Manager
         */
        $.fn.wpAssetCleanUpFrontendCssJsManagerArea = function () {
            return {
                actions: function () {
                    // "Update" button is clicked within front-end view
                    let $updateBtnFrontEnd = $('#wpacu-update-front-settings-area .wpacu_update_btn');

                    // Show the loading spinner
                    $(document).on('submit', '#wpacu-frontend-form', function () {
                        $updateBtnFrontEnd.attr('disabled', true).addClass('wpacu_submitting');
                        $('#wpacu-updating-front-settings').show();
                        return true;
                    });

                    // Asset Front-end Edit (if setting is enabled)
                    if ($('#wpacu_wrap_assets').length > 0) {
                        setTimeout(function () {
                            $.fn.wpAssetCleanUp().cssJsManagerActions();
                        }, 200);
                    }

                    // The code below is for the pages loaded in the front-end view
                    // Fetch hardcoded assets
                    if ($('#wpacu-assets-collapsible-wrap-hardcoded-list').length > 0) {
                        let dataFetchHardcodedList = {};
                        dataFetchHardcodedList[wpacu_object.plugin_prefix + '_load']   = 1;
                        dataFetchHardcodedList['wpacu_time_r'] = new Date().getTime();
                        dataFetchHardcodedList['wpacu_just_hardcoded']                 = 1;

                        if ($.fn.wpAssetCleanUp().getParameterByName('wpacu_ignore_no_load_option') !== null) {
                            dataFetchHardcodedList['wpacu_ignore_no_load_option']      = 1;
                        }

                        $.ajax({
                            method: 'GET',
                            url: wpacu_object.page_url,
                            data: dataFetchHardcodedList,
                            cache: false,
                            complete: function (xhr, textStatus) {
                                if (xhr.statusText === 'error') {
                                    $.fn.wpAssetCleanUp().wpacuParseResultsForHarcodedAssets(xhr.responseText);
                                    }
                            }
                        }).done(function (contents) {
                            $.fn.wpAssetCleanUp().wpacuParseResultsForHarcodedAssets(contents);
                        });
                    }
                }
            }
        }
        $.fn.wpAssetCleanUpFrontendCssJsManagerArea().actions();
        /*
         * [END] Front-end CSS/JS Manager
         */

        /*
        * [START] Dashboard CSS/JS Manager
        */
        $.fn.wpAssetCleanUpDashboardCssJsManagerArea = function () {
            return {
                actions: function () {
                    // Option #1: Fetch the assets automatically and show the list (Default) is chosen
                    // Or "Homepage" from "CSS & JavaScript Load Manager" is loaded
                    if (wpacu_object.list_show_status === 'default' ||
                        wpacu_object.list_show_status === '' ||
                        (typeof wpacu_object.override_assets_list_load !== 'undefined' && wpacu_object.override_assets_list_load)) {
                        $.fn.wpAssetCleanUp().wpacuAjaxGetAssetsArea(false);
                    }

                    $(document).on('click', '.wpacu-manage-redirected-fetch-url', function (event) {
                        event.preventDefault();

                        const $button = $(this);
                        const redirectedFetchUrl = $button.attr('data-wpacu-redirected-fetch-url');

                        if (!redirectedFetchUrl) {
                            return;
                        }

                        $button.prop('disabled', true);
                        $button.siblings('.spinner').addClass('is-active');

                        $.fn.wpAssetCleanUp().wpacuAjaxGetAssetsArea(true, redirectedFetchUrl);
                    });

                    // Option #2: Fetch the assets on button click
                    // This takes effect only when edit post/page is used - e.g. /wp-admin/post.php?post=[post_id_here]&action=edit
                    if (wpacu_object.list_show_status === 'fetch_on_click') {
                        $(document).on('click', '#wpacu_ajax_fetch_on_click_btn', function (e) {
                            e.preventDefault();
                            $(this).hide(); // Hide the button
                            $('#wpacu_fetching_assets_list_wrap').show(); // Show the loading information
                            $.fn.wpAssetCleanUp().wpacuAjaxGetAssetsArea(true); // Fetch the assets list
                        });
                    }

                    // Better compatibility with WordPress 5.0 as edit post/page is not refreshed after update
                    // Asset CleanUp meta box's content is refreshed to show the latest changes as if the page was refreshed
                    // This takes effect only when edit post/page is used and Gutenberg editor is used - e.g. /wp-admin/post.php?post=[post_id_here]&action=edit
                    $(document).on('click',
                        '.wp-admin.post-php .editor-header__settings button.is-primary, ' +
                        '.wp-admin.post-php .edit-post-header__settings button.is-primary',
                        function () {
                            let $thisUpdateBtn = $(this);

                            let parentClassElementIdentifier = '.editor-header__settings';
                            let isSavingIdentifier = '.is-busy';

                            // Fallback (old WordPress version)
                            if ($thisUpdateBtn.parent().hasClass('edit-post-header__settings')) {
                                parentClassElementIdentifier = '.edit-post-header__settings';
                                isSavingIdentifier = '.is-saving';
                            }

                            // Wait until triggering it around half a second after the "Update" button is clicked
                            setTimeout(function () {
                                let wpacuIntervalUpdateAction = function () {
                                    // If it's in the updating status, don't do anything
                                    if ($thisUpdateBtn.attr('aria-disabled') === 'true' || $('#editor').hasClass('is-validating')) {
                                        return;
                                    }

                                    // If the button "Fetch CSS & JavaScript Management List" is there, stop here as the list shouldn't be loaded
                                    // since the admin didn't use the button in the first place
                                    if ($('#wpacu_ajax_fetch_on_click_btn').length > 0) {
                                        return;
                                    }

                                    // Updating status is over. Reload the CSS/JS manager which would show the new list
                                    // (e.g. a site-wide rule could be applied, and it needs to show the removing "radio input" option)
                                    if ($(parentClassElementIdentifier + ' ' + isSavingIdentifier).length === 0) {
                                        let wpacuMetaBoxContentTarget = '#wpacu_meta_box_content';

                                        if ($(wpacuMetaBoxContentTarget).length > 0) {
                                            if ($('#wpacu-assets-reloading-in-edit-post-area').length === 0) {
                                                let wpacuAppendToPostWhileUpdating = '<span id="wpacu-assets-reloading-in-edit-post-area">' + wpacu_object.reload_icon + '&nbsp;<strong>' + wpacu_object.reload_msg + '</strong></span>';
                                                $('.wp-admin.post-php ' + parentClassElementIdentifier).prepend(wpacuAppendToPostWhileUpdating);
                                            }

                                            //console.log('.wp-admin.post-php ' + parentClassElementIdentifier);

                                            $('.wpacu_asset_row, .wpacu-page-options .wpacu-assets-collapsible-content').addClass('wpacu_loading'); // show loading spinner once "Update" is clicked

                                            $.fn.wpAssetCleanUp().wpacuAjaxGetAssetsArea(true);
                                            window.wpacuCacheManager.wpacuAjaxClearCache();

                                            // Finally, after the list is fetched and the caching is cleared,
                                            // do not keep checking any "saving" status as all the needed actions have been taken
                                            clearInterval(wpacuUpdateIntervalId);
                                        }
                                    }
                                };

                                let wpacuUpdateIntervalId = setInterval(wpacuIntervalUpdateAction, 900);
                            }, 500);
                        });

                    return $.fn.wpAssetCleanUp().limitSubmittedFields();
                }
            }
        }
        $.fn.wpAssetCleanUpDashboardCssJsManagerArea().actions();
        /*
        * [END] Dashboard CSS/JS Manager
        */

        /*
        * [START] Common CSS/JS Manager (Dashboard & Front-end)
        */
        $.fn.wpAssetCleanUpCommonCssJsManagerArea = function () {
            return {
                actions: function () {
                    // Mark specific inputs as disabled if they are not needed to further reduce the total PHP inputs
                    // if "max_input_vars" from php.ini is not set high enough
                    $(document).on('submit', 'form#wpacu-frontend-form, form#wpacu_dash_assets_manager_form, body.wp-admin form#post, body.wp-admin #edittag', function () {
                        return $.fn.wpAssetCleanUp().limitSubmittedFields();
                    });

                    // Source (updated)
                    $(document).on('click', '.wpacu-filter-handle', function (event) {
                        alert($(this).attr('data-wpacu-filter-handle-message'));
                        event.preventDefault();
                    });

                    // "Contract All Groups"
                    $(document).on('click', '#wpacu-assets-contract-all', function () {
                        $(this).prop('disabled', true); // avoid multiple clicks and AJAX calls
                        $.fn.wpAssetCleanUp().wpacuAjaxUpdateKeepTheGroupsState('contracted', $(this).attr('id'));
                    });

                    // "Expand All Groups"
                    $(document).on('click', '#wpacu-assets-expand-all', function () {
                        $(this).prop('disabled', true); // avoid multiple clicks and AJAX calls
                        $.fn.wpAssetCleanUp().wpacuAjaxUpdateKeepTheGroupsState('expanded', $(this).attr('id'));
                    });
                }
            }
        }
        $.fn.wpAssetCleanUpCommonCssJsManagerArea().actions();
        /*
        * [END] Common CSS/JS Manager (Dashboard & Front-end)
        */

        $.fn.wpAssetCleanUp().wpacuTriggerAdjustTextAreaHeightAllTextareas();

        /*
        * [START] Bulk Changes
        */
        $.fn.wpAssetCleanUpBulkChangesArea = function () {
            return {
                actions: function () {
                    // Items are marked for removal from the unload list
                    // from either "Everywhere" or "Post Type"
                    $(document).on('click', '.wpacu_bulk_rule_checkbox, .wpacu_remove_preload', function () {
                        let $wpacuBulkChangeRow = $(this).parents('.wpacu_bulk_change_row');

                        if ($(this).prop('checked')) {
                            $wpacuBulkChangeRow.addClass('wpacu_selected');
                        } else {
                            $wpacuBulkChangeRow.removeClass('wpacu_selected');
                        }
                    });

                    $(document).on('change', '#wpacu_post_type_select', function () {
                        $('#wpacu_post_type_form').trigger('submit');
                    });
                }
            }
        }
        $.fn.wpAssetCleanUpBulkChangesArea().actions();
        /*
        * [END] Bulk Changes
        */
    });

    (function ($) {
        $(window).on('load', function () {
            $.fn.wpAssetCleanUp().wpacuCheckSourcesFor404Errors();
        });
    })(jQuery);
});

/*
    * [START INFO MODAL BOX]
    */
document.addEventListener("DOMContentLoaded", () => {
    function wpacuShowHideInfoModal(targetedModalId) {
        const targetedModal = document.getElementById(targetedModalId);

        if (! targetedModal) {
            return;
        }

        wpacuHighlightSourceViewerCode(targetedModal);

        // Show the modal
        targetedModal.style.display = 'block';

        // Clicking outside the modal area (close it)
        targetedModal.addEventListener('click', function (event) {
            if (event.target.id === targetedModalId) {
                targetedModal.style.display = 'none';
            }
        });
    }

    document.body.addEventListener('click', function (event) {
        // Support clicks on icons or text nested inside the modal trigger link.
        const wpacuModalTrigger = event.target.closest('a[data-wpacu-modal-target]');

        if (wpacuModalTrigger) {
            const wpacuLinkRef = wpacuModalTrigger.getAttribute('data-wpacu-modal-target');

            if (wpacuLinkRef && wpacuLinkRef.startsWith('wpacu-')) {
                const wpacuPossibleModalId = wpacuLinkRef.replace('-target', '');

                if (document.getElementById(wpacuPossibleModalId)) {
                    wpacuShowHideInfoModal(wpacuPossibleModalId);
                    event.preventDefault();
                }
            }
        }

        // "x" is clicked within the modal box. Support both the legacy SPAN
        // control and modern BUTTON controls (including nested icons).
        const wpacuModalCloseControl = event.target.closest('.wpacu-close');

        if (wpacuModalCloseControl) {
            const wpacuModal = wpacuModalCloseControl.closest('.wpacu-modal');

            if (wpacuModal) {
                wpacuModal.style.display = 'none';
                event.preventDefault();
            }
        }
    });
});
/*
* [END INFO MODAL BOX]
*/

//
// [END] Core file
//

