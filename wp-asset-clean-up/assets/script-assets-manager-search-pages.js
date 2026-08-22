jQuery(function($) {
    var autoCompleteEl = '#wpacu-search-form-assets-manager .search-field';

    function getSearchContextType() {
        return wpacu_autocomplete_search_obj.context_type || '';
    }

    function getSearchContextValue() {
        return wpacu_autocomplete_search_obj.context_value || '';
    }

    function shouldShowAllResults($input) {
        if ($input.attr('data-wpacu-show-all-on-focus') === '1') {
            return true;
        }

        return false;
    }

    $(autoCompleteEl).autocomplete({
        minLength: 0,
        source: function(request, response) {
            var $input = $(this.element),
                wpacuSearchPayload = {
                    context_type:    getSearchContextType(),
                    context_value:   getSearchContextValue(),
                    keyword:         request.term,
                    show_all:        (shouldShowAllResults($input) ? 1 : 0),
                    show_all_limit:  $input.attr('data-wpacu-show-all-limit')
                };

            $.ajax({
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
                    $('#wpacu-search-form-assets-manager-no-results').hide(); // in case it was ever shown
                    response(data);
                },
                complete: function(jqXHR) {
                    if (jqXHR.responseText === 'no_results') {
                        $('#wpacu-search-form-assets-manager-no-results').show();
                        response([]);
                    }
                }
            });
        },
        select: function(event, ui) {
            $('#wpacu-search-form-assets-manager').hide();
            $('#wpacu-post-chosen-loading-assets').show();

            var redirectTo = wpacu_autocomplete_search_obj.redirect_to.replace('=post_id_here', '=' + ui.item.id)
                .replace('=item_id_here', '=' + ui.item.id);

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
        return $('<li>')
            .append('<div>' + item.label + '<span style="display:block;color:green;font-size:11px;">' + item.link + '</span></div>')
            .appendTo(ul);
    };

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
