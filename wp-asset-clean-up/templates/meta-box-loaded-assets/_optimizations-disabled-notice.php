<?php
if (! isset($data)) {
    exit; // no direct access
}

if (empty($data['show_page_options'])) {
    return;
}
?>
<div id="wpacu-optimizations-disabled-notice" role="status"
     style="<?php if (empty($data['page_options']['no_assets_settings'])) { echo 'display: none; '; } ?>margin: 12px 0 18px; padding: 14px 18px; border: 1px solid #dba617; border-left-width: 4px; border-radius: 4px; background: #fff8e5; color: #3c434a;">
    <div style="display: flex; align-items: flex-start; gap: 18px;">
    <svg aria-hidden="true" focusable="false" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#996800" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex: 0 0 34px; margin-top: 2px;">
        <path d="M10.3 4.1 2.2 18.2A2 2 0 0 0 3.9 21h16.2a2 2 0 0 0 1.7-2.8L13.7 4.1a2 2 0 0 0-3.4 0Z" />
        <path d="M12 9v4" />
        <circle cx="12" cy="17" r=".9" fill="#996800" stroke="none" />
    </svg>
    <div style="flex: 1; min-width: 0;">
    <strong><?php esc_html_e('CSS/JS unload rules and other optimizations are paused on this page.', 'wp-asset-clean-up'); ?></strong>
    <p style="margin: 6px 0 0;"><?php
        /* translators: 1: opening Page Options link, 2: closing link, 3: opening option emphasis, 4: closing emphasis. */
        echo sprintf(
            esc_html__('Your unload rules and other optimization settings will be saved when you update, but will not take effect on this page while "%3$sDisable all front-end optimizations%4$s" is enabled in %1$sPage Options%2$s.', 'wp-asset-clean-up'),
            '<a href="#wpacu_page_options_no_assets_settings" data-wpacu-disabled-optimizations-link style="text-decoration: none;"><span style="text-decoration: underline;">',
            '</span> <svg aria-hidden="true" focusable="false" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: -2px;"><circle cx="12" cy="5" r="2" /><path d="M12 7v14M5 12H2a10 10 0 0 0 20 0h-3M2 12l3 3m17-3-3 3" /></svg></a>',
            '<em style="color: #c00; font-style: italic;">',
            '</em>'
        );
    ?></p>
    </div>
    </div>
</div>
<script>
(function($) {
    'use strict';

    // Delegation also covers the manager being replaced after an AJAX update.
    $(document)
        .off('change.wpacuDisabledOptimizations', '#wpacu_page_options_no_assets_settings')
        .on('change.wpacuDisabledOptimizations', '#wpacu_page_options_no_assets_settings', function() {
            $('#wpacu-optimizations-disabled-notice').toggle(this.checked);
        })
        .off('click.wpacuDisabledOptimizations', '[data-wpacu-disabled-optimizations-link]')
        .on('click.wpacuDisabledOptimizations', '[data-wpacu-disabled-optimizations-link]', function(event) {
            var $option = $('#wpacu_page_options_no_assets_settings');
            if (! $option.length) {
                return;
            }

            event.preventDefault();
            var $section = $option.closest('.wpacu-page-options');
            $section.children('.wpacu-assets-collapsible').addClass('wpacu-assets-collapsible-active');
            $section.children('.wpacu-assets-collapsible-content').addClass('wpacu-open');

            var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var toolbarHeight = $('#wpadminbar').outerHeight() || 0;
            $option[0].focus({preventScroll: true});
            // Gutenberg can scroll the editor or meta-box area independently of the document.
            var scrollContainer = $option[0].parentElement;
            while (scrollContainer && scrollContainer !== document.body) {
                var overflowY = window.getComputedStyle(scrollContainer).overflowY;
                if (/(auto|scroll|overlay)/.test(overflowY)
                    && scrollContainer.scrollHeight > scrollContainer.clientHeight) {
                    break;
                }
                scrollContainer = scrollContainer.parentElement;
            }
            var nestedScroll = scrollContainer && scrollContainer !== document.body;
            var targetTop = nestedScroll
                ? scrollContainer.scrollTop + $option.closest('li')[0].getBoundingClientRect().top
                    - scrollContainer.getBoundingClientRect().top - scrollContainer.clientTop - 24
                : $option.closest('li').offset().top - toolbarHeight - 24;
            (nestedScroll ? $(scrollContainer) : $('html, body')).stop(true).animate({
                scrollTop: Math.max(0, targetTop)
            }, reducedMotion ? 0 : 220);
        });
})(jQuery);
</script>
