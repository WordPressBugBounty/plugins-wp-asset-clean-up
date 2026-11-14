/* CSS-styled taxonomy picker; the native select remains the form value and fallback. */
jQuery(function ($) {
    function initializePickers(root) {
    $(root).find('[data-wpacu-taxonomy-picker]').each(function () {
        var select = this, $select = $(select);
        if (!$.fn.selectmenu || $select.data('ui-selectmenu')) { return; }
        var $wrapper = $('<span class="wpacu-taxonomy-picker">');
        $select.wrap($wrapper);
        $wrapper = $select.parent();
        function updateSelectedStyle() {
            var option = select.options[select.selectedIndex];
            $select.selectmenu('widget').toggleClass('wpacu-taxonomy-picker-empty', !!option && option.getAttribute('data-empty') === '1');
        }
        $select.selectmenu({
            appendTo: $wrapper,
            width: false,
            change: function () {
                updateSelectedStyle();
                // Keep both managers' existing navigation and unsaved-change checks.
                select.dispatchEvent(new Event('change', {bubbles: true}));
            }
        });
        var instance = $select.data('ui-selectmenu'), renderItem = instance._renderItem;
        instance._renderItem = function (ul, item) {
            return renderItem.call(this, ul, item)
                .attr('title', item.label)
                .toggleClass('wpacu-taxonomy-picker-empty', item.element.attr('data-empty') === '1');
        };
        instance._resizeMenu = function () {
            this.menuWrap.outerWidth(this.button.outerWidth());
        };
        $select.on('change', function () {
            $select.selectmenu('refresh');
            updateSelectedStyle();
        });
        updateSelectedStyle();
    });
    }
    initializePickers(document);
    document.addEventListener('wpacu:page-context-loaded', function (event) {
        initializePickers(event.detail);
    });
});

