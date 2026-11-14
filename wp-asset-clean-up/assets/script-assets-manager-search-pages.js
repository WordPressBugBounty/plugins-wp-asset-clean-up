jQuery(function($) {
    var autoCompleteEl = '#wpacu-search-form-assets-manager .search-field';

    function getSearchContextType($input) {
        return $input.attr('data-wpacu-search-context-type') || wpacu_autocomplete_search_obj.context_type || '';
    }

    function getSearchContextValue($input) {
        return $input.attr('data-wpacu-search-context-value') || wpacu_autocomplete_search_obj.context_value || '';
    }

    function shouldShowAllResults($input) {
        if ($input.attr('data-wpacu-show-all-on-focus') === '1') {
            return true;
        }

        return false;
    }

    function initializeSearch(root) {
    $(root).find(autoCompleteEl).each(function () {
    if ($(this).data('ui-autocomplete')) { return; }
    $(this).autocomplete({
        minLength: 0,
        source: function(request, response) {
            var $input = $(this.element),
                wpacuSearchPayload = {
                    context_type:    getSearchContextType($input),
                    context_value:   getSearchContextValue($input),
                    keyword:         request.term,
                    show_all:        (shouldShowAllResults($input) ? 1 : 0),
                    show_all_limit:  $input.attr('data-wpacu-show-all-limit')
                };

            var previousRequest = $input.data('wpacuSearchRequest');
            if (previousRequest) { previousRequest.abort(); }
            $input.data('wpacuSearchRequest', $.ajax({
                dataType: 'json',
                url: wpacu_autocomplete_search_obj.ajax_url,
                cache: false,
                data: {
                    wpacu_search:   JSON.stringify(wpacuSearchPayload),
                    action:         wpacu_autocomplete_search_obj.ajax_action,
                    wpacu_security: wpacu_autocomplete_search_obj.ajax_nonce,
                    wpacu_time:     new Date().getTime()
                },
                success: function(data) {
                    if (!document.documentElement.contains($input[0])) { response([]); return; }
                    $('#wpacu-search-form-assets-manager-no-results').hide(); // in case it was ever shown
                    response(data);
                },
                error: function(jqXHR) {
                    if (document.documentElement.contains($input[0]) && jqXHR.responseText === 'no_results') {
                        $('#wpacu-search-form-assets-manager-no-results').show();
                    }
                    response([]);
                }
            }));
        },
        select: function(event, ui) {
            $('#wpacu-search-form-assets-manager').hide();
            $('#wpacu-post-chosen-loading-assets').show();

            var redirectTo = wpacu_autocomplete_search_obj.redirect_to.replace('=post_id_here', '=' + ui.item.id)
                .replace('=item_id_here', '=' + ui.item.id);

            var $picker = $(this).closest('.wpacu-assets-context-picker');
            var contextParameter = $picker.find('#wpacu-custom-post-type-choice').length ? 'wpacu_post_type'
                : ($picker.find('#wpacu-custom-taxonomy-choice').length ? 'wpacu_taxonomy' : '');
            if (contextParameter) {
                var contextUrl = new URL(redirectTo, window.location.href);
                contextUrl.searchParams.set(contextParameter, getSearchContextValue($(this)));
                redirectTo = contextUrl.href;
            }

            if (typeof ui.item.taxonomy !== 'undefined') {
                redirectTo = redirectTo.replace('taxonomy_here', ui.item.taxonomy);
            } else if ($('#wpacu-custom-taxonomy-choice').length > 0 && $('#wpacu-custom-taxonomy-choice').val()) {
                redirectTo = redirectTo.replace('taxonomy_here', $('#wpacu-custom-taxonomy-choice').val());
            }

            window.location.href = redirectTo;
        },
        close: function(el) {
            el.target.value = '';
        }
    }).data('ui-autocomplete')._renderItem = function (ul, item) {
        var displayUrl = String(item.link || '');
        try { displayUrl = decodeURI(displayUrl); } catch (error) { /* Keep malformed URLs readable as supplied. */ }
        var $label = $('<div>').html(item.label);
        $('<span>').css({display: 'block', color: 'green', fontSize: '11px'}).text(displayUrl).appendTo($label);
        return $('<li>')
            .append($label)
            .appendTo(ul);
    };
    });
    }

    initializeSearch(document);
    document.addEventListener('wpacu:page-context-loaded', function (event) { initializeSearch(event.detail); });

    $(document).on('focusin', autoCompleteEl, function () {
        var $input = $(this);

        if ( ! shouldShowAllResults($input) ) {
            return;
        }

        if ($input.val() !== '') {
            return;
        }

        setTimeout(function () {
            $input.autocomplete('search', '');
        }, 0);
    });
});
