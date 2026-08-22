<?php
namespace WpAssetCleanUp;

use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

/**
 * Class AdminBar
 * @package WpAssetCleanUp
 */
class AdminBar
{
    /**
     * @var string
     */
    public static $assetUnloadGlobalKeyInfo = 'wpacu_filtered_assets_reasons';

    /**
	 * This class is called within the WordPress 'init' hook when it's meant to be loaded
	 */
	public function __construct()
	{
		// Code for both the Dashboard and the Front-end view
        add_action('wpacu_internal_admin_bar_inline_code_after_css', array($this, 'addExtraInlineCss'));

		add_action('admin_head',     array($this, 'inlineCode'));
		add_action('wp_head',        array($this, 'inlineCode'));

		add_action('admin_bar_menu', array($this, 'topBarInfo'), 81);

		// Hide top WordPress admin bar on request for debugging purposes and a cleared view of the tested page
		// This is done in /early-triggers.php within assetCleanUpNoLoad() function
	}

	/**
	 *
	 */
	public function inlineCode()
	{
		?>
		<style <?php echo Misc::getStyleTypeAttribute(); ?> data-wpacu-own-inline-style="true">
            #wpadminbar #wp-admin-bar-assetcleanup-parent {
                position: relative;
                overflow: visible;
            }

            /* Invisible area between the parent menu item and its submenu */
            #wpadminbar #wp-admin-bar-assetcleanup-parent::after {
                content: "";
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                height: 12px; /* Increase to 15-20px if needed */
                background: transparent;
                z-index: 99999;
            }

            #wpadminbar #wp-admin-bar-assetcleanup-asset-unload-rules-css-default,
            #wpadminbar #wp-admin-bar-assetcleanup-asset-unload-rules-js-default {
                max-height: calc(100vh - 80px);
                overflow-y: auto !important;
                overflow-x: visible !important;
            }

            #wp-admin-bar-assetcleanup-parent span.dashicons {
                width: 15px;
                height: 15px;
                font-family: 'Dashicons', Arial, "Times New Roman", "Bitstream Charter", Times, serif !important;
            }

            #wp-admin-bar-assetcleanup-parent > a:first-child strong {
                font-weight: bolder;
                color: #76f203;
            }

            #wp-admin-bar-assetcleanup-parent > a:first-child:hover {
                color: #00b9eb;
            }

            #wp-admin-bar-assetcleanup-parent > a:first-child:hover strong {
                color: #00b9eb;
            }

            #wp-admin-bar-assetcleanup-test-mode-info {
                margin-top: 5px !important;
                margin-bottom: -8px !important;
                padding-top: 3px !important;
                border-top: 1px solid #ffffff52;
            }

            /* Add some spacing below the last text */
            #wp-admin-bar-assetcleanup-test-mode-info-2 {
                padding-bottom: 3px !important;
            }

            /* When it's hovered, make sure it overlaps other possible menus nearby that didn't close it */
            #wpadminbar #wp-admin-bar-assetcleanup-parent.hover,
            #wpadminbar #wp-admin-bar-assetcleanup-parent:hover {
                z-index: 10000000;
            }

            #wpadminbar #wp-admin-bar-assetcleanup-parent.hover > .ab-sub-wrapper,
            #wpadminbar #wp-admin-bar-assetcleanup-parent:hover > .ab-sub-wrapper,
            #wpadminbar #wp-admin-bar-assetcleanup-parent .menupop.hover > .ab-sub-wrapper,
            #wpadminbar #wp-admin-bar-assetcleanup-parent .menupop:hover > .ab-sub-wrapper {
                position: absolute;
                z-index: 10000001;
                overflow: visible;
            }

            #wpadminbar #wp-admin-bar-assetcleanup-parent .ab-sub-wrapper,
            #wpadminbar #wp-admin-bar-assetcleanup-parent .ab-submenu {
                overflow: visible;
            }

            #wpadminbar #wp-admin-bar-assetcleanup-parent li.menupop {
                position: relative;
                overflow: visible;
            }

            #wpadminbar #wp-admin-bar-assetcleanup-parent li.menupop:hover,
            #wpadminbar #wp-admin-bar-assetcleanup-parent li.menupop.hover {
                z-index: 10000001;
            }

            #wpadminbar .wpacu-alert-sign-top-admin-bar {
                font-size: 20px;
                color: lightyellow;
                vertical-align: top;
                margin: -7px 0 0;
                display: inline-block;
                box-sizing: border-box;
            }
		</style>
		<script>
		document.addEventListener('click', function(event) {
			var clearCacheLink = event.target.closest('#wp-admin-bar-assetcleanup-clear-css-js-files-cache > a');

			if (! clearCacheLink) {
				return;
			}

			event.preventDefault();

			var form = document.createElement('form');
			form.method = 'post';
			form.action = <?php echo wp_json_encode(OptimizeCommon::generateClearCachingUrl()); ?>;

			var fields = <?php echo wp_json_encode(array(
				'action'           => 'assetcleanup_clear_assets_cache',
				'_wpnonce'         => wp_create_nonce('assetcleanup_clear_assets_cache'),
				'_wp_http_referer' => isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/',
				'wpacu_dash_area'  => is_admin() ? '1' : ''
			)); ?>;

			Object.keys(fields).forEach(function(name) {
				if (fields[name] === '') {
					return;
				}

				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = name;
				input.value = fields[name];
				form.appendChild(input);
			});

			document.body.appendChild(form);
			form.submit();
		});
		</script>

        <?php
        ?>

        <?php do_action('wpacu_internal_admin_bar_inline_code_after_css'); ?>

        <script <?php echo Misc::getScriptTypeAttribute(); ?> data-wpacu-own-inline-script="true">
            document.addEventListener('DOMContentLoaded', function () {
                if ( ! window.jQuery ) {
                    return;
                }

                // Make it easier to go through the menu and sub-menu
                (function ($) {
                    var rootSelector = '#wp-admin-bar-assetcleanup-parent';
                    var root = document.querySelector(rootSelector);

                    // No menu? Stop here!
                    if ( ! root ) {
                        return;
                    }

                    // No extra levels? Stop here!
                    if ( ! root.querySelector('.ab-sub-wrapper .menupop') ) {
                        return;
                    }

                    var closeTimer = null;

                    function openPath(menu) {
                        var $menu = $(menu);
                        var $root = $(rootSelector);

                        $root
                            .addClass('hover')
                            .children('.ab-item')
                            .attr('aria-expanded', 'true');

                        $menu
                            .addClass('hover')
                            .children('.ab-item')
                            .attr('aria-expanded', 'true');

                        $menu
                            .parentsUntil(rootSelector, '.menupop')
                            .addClass('hover')
                            .children('.ab-item')
                            .attr('aria-expanded', 'true');
                    }

                    function closeAll() {
                        var $root = $(rootSelector);

                        $root
                            .find('.menupop')
                            .addBack()
                            .removeClass('hover')
                            .children('.ab-item')
                            .attr('aria-expanded', 'false');
                    }

                    document.addEventListener('mouseout', function (event) {
                        var menu = event.target.closest(rootSelector + ', ' + rootSelector + ' .menupop');

                        if ( ! menu ) {
                            return;
                        }

                        // If hovered over Asset CleanUp's menu, close immediately other menu opened in the top admin bar
                        $('#wpadminbar .menupop.hover')
                            .not(rootSelector)
                            .not(rootSelector + ' .menupop')
                            .removeClass('hover')
                            .find('.ab-item')
                            .attr('aria-expanded', 'false');

                        clearTimeout(closeTimer);

                        var related = event.relatedTarget;

                        // If the mouse is moved inside the same menu, do nothing
                        if (related && menu.contains(related)) {
                            return;
                        }

                        // Stop the WordPress/hoverIntent handler to close immediatelly!
                        event.stopImmediatePropagation();

                        clearTimeout(closeTimer);

                        openPath(menu);

                        closeTimer = setTimeout(function () {
                            var root = document.querySelector(rootSelector);

                            if (root && !root.matches(':hover')) {
                                closeAll();
                            }
                        }, 1200);
                    }, true);

                    function closeSiblingMenus(menu) {
                        var $menu = $(menu);

                        $menu
                            .siblings('.menupop')
                            .removeClass('hover')
                            .children('.ab-item')
                            .attr('aria-expanded', 'false');

                        $menu
                            .siblings('.menupop')
                            .find('.menupop')
                            .removeClass('hover')
                            .children('.ab-item')
                            .attr('aria-expanded', 'false');
                    }

                    document.addEventListener('mouseover', function (event) {
                        var root = document.querySelector(rootSelector);

                        if ( ! root ) {
                            return;
                        }

                        var otherAdminBarMenu = event.target.closest('#wpadminbar > #wp-toolbar .menupop');

                        if (otherAdminBarMenu && !root.contains(otherAdminBarMenu) && otherAdminBarMenu !== root) {
                            clearTimeout(closeTimer);
                            closeAll();
                            return;
                        }

                        var menu = event.target.closest(rootSelector + ', ' + rootSelector + ' .menupop');

                        if ( ! menu ) {
                            return;
                        }

                        clearTimeout(closeTimer);

                        // If the mouse is on the main menu, and not on a sub-menu, close all the sub-menus
                        if (menu === root) {
                            $(root)
                                .find('.menupop')
                                .removeClass('hover')
                                .children('.ab-item')
                                .attr('aria-expanded', 'false');

                            $(root)
                                .addClass('hover')
                                .children('.ab-item')
                                .attr('aria-expanded', 'true');

                            return;
                        }

                        closeSiblingMenus(menu);
                        openPath(menu);
                    }, true);

                    })(jQuery);

                // [START] Move unload info tooltip to left if the space is limited on the right
                var tooltipItems = document.querySelectorAll(
                    '#wpadminbar .wpacu-admin-bar-rule-item'
                );

                if (! tooltipItems.length) {
                    return;
                }

                function updateTooltipDirection(item) {
                    var tooltip = item.querySelector('.wpacu-rule-tooltip');

                    if (! tooltip) {
                        return;
                    }

                    item.classList.remove('wpacu-tooltip-open-left');

                    /*
                     * The tooltip is display:none by default.
                     * Temporarily make it measurable without visually showing it.
                     */
                    var previousDisplay = tooltip.style.display;
                    var previousVisibility = tooltip.style.visibility;
                    var previousPointerEvents = tooltip.style.pointerEvents;

                    tooltip.style.display = 'block';
                    tooltip.style.visibility = 'hidden';
                    tooltip.style.pointerEvents = 'none';

                    var itemRect = item.getBoundingClientRect();
                    var tooltipRect = tooltip.getBoundingClientRect();
                    var viewportWidth = window.innerWidth || document.documentElement.clientWidth;

                    var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                    var viewportPadding = 12;
                    var gap = 8;

                    var openLeft = false;
                    var tooltipLeft = itemRect.right + gap;
                    var tooltipTop = itemRect.top - 3;

                    if ((tooltipLeft + tooltipRect.width) > (viewportWidth - viewportPadding)) {
                        openLeft = true;
                        tooltipLeft = itemRect.left - gap - tooltipRect.width;
                    }

                    if (tooltipLeft < viewportPadding) {
                        tooltipLeft = viewportPadding;
                    }

                    if ((tooltipTop + tooltipRect.height) > (viewportHeight - viewportPadding)) {
                        tooltipTop = viewportHeight - viewportPadding - tooltipRect.height;
                    }

                    if (tooltipTop < viewportPadding) {
                        tooltipTop = viewportPadding;
                    }

                    tooltip.style.left = tooltipLeft + 'px';
                    tooltip.style.top = tooltipTop + 'px';
                    tooltip.style.right = 'auto';

                    if (openLeft) {
                        item.classList.add('wpacu-tooltip-open-left');
                    }

                    tooltip.style.display = previousDisplay;
                    tooltip.style.visibility = previousVisibility;
                    tooltip.style.pointerEvents = previousPointerEvents;
                }

                tooltipItems.forEach(function (item) {
                    item.addEventListener('mouseenter', function () {
                        updateTooltipDirection(item);
                    });

                    item.addEventListener('focusin', function () {
                        updateTooltipDirection(item);
                    });
                });

                window.addEventListener('resize', function () {
                    tooltipItems.forEach(function (item) {
                        var tooltip = item.querySelector('.wpacu-rule-tooltip');

                        item.classList.remove('wpacu-tooltip-open-left');

                        if (tooltip) {
                            tooltip.style.left = '';
                            tooltip.style.top = '';
                            tooltip.style.right = '';
                        }
                    });
                });
                // [END] Move unload info tooltip to left if the space is limited on the right
            });
        </script>
		<?php
	}

    /**
     * @return void
     */
    public function addExtraInlineCss()
    {
        ?>
        <style <?php echo Misc::getStyleTypeAttribute(); ?> data-wpacu-own-inline-style="true">
            /* ===========================================================
               WPACU — Admin bar: shared tooltip for unload rules
               =========================================================== */

            #wpadminbar {
                --wpacu-tt-bg:         #1d2327;
                --wpacu-tt-text:       #c3c4c7;
                --wpacu-tt-border:     #3c434a;
                --wpacu-tt-accent:     #72aee6;
                --wpacu-tt-match-bg:   lightgray;
                --wpacu-tt-match-text: black;
                --wpacu-tt-arrow-top:  10px;
            }

            /* Tooltip anchor */
            #wpadminbar .wpacu-admin-bar-rule-item,
            #wpadminbar .wpacu-admin-bar-rule-item > .ab-item {
                position: relative;
                overflow: visible;
            }

            #wpadminbar .wpacu-admin-bar-rule-item:hover,
            #wpadminbar .wpacu-admin-bar-rule-item.hover {
                z-index: 10000002;
            }

            #wpadminbar .wpacu-admin-bar-rule-item:hover > .ab-item,
            #wpadminbar .wpacu-admin-bar-rule-item.hover > .ab-item {
                z-index: 10000003;
            }

            /* Tooltip box */
            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip {
                display: none;
                position: fixed;
                top: auto;
                left: auto;
                right: auto;
                z-index: 10000004;

                box-sizing: border-box;

                overflow: visible;

                width: max-content;
                max-width: 430px;
                margin-left: 0;
                padding: 9px 12px;

                background: var(--wpacu-tt-bg);
                color: var(--wpacu-tt-text);
                border: 1px solid var(--wpacu-tt-border);
                border-radius: 3px;
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.35);

                font-size: 12px;
                font-weight: 400;
                line-height: 1.3 !important;
                white-space: normal !important;
                overflow-wrap: anywhere;
                text-align: left;

                cursor: default;
                pointer-events: auto;
            }

            #wpadminbar .wpacu-admin-bar-rule-item:hover > .ab-item .wpacu-rule-tooltip,
            #wpadminbar .wpacu-admin-bar-rule-item.hover > .ab-item .wpacu-rule-tooltip {
                display: block;
            }

            /* Reset WP Admin Bar spacing inside WPACU tooltip */
            #wpadminbar .ab-item .wpacu-rule-tooltip,
            #wpadminbar .ab-item .wpacu-rule-tooltip *,
            #wpadminbar .ab-item .wpacu-rule-tooltip ul,
            #wpadminbar .ab-item .wpacu-rule-tooltip li,
            #wpadminbar .ab-item .wpacu-rule-tooltip span,
            #wpadminbar .ab-item .wpacu-rule-tooltip strong,
            #wpadminbar .ab-item .wpacu-rule-tooltip em {
                height: auto !important;
                min-height: 0 !important;
                line-height: 1.3 !important;
            }

            /* Main tooltip list */
            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul {
                display: block;
                margin: 0 !important;
                padding: 0 !important;
                list-style: none !important;

                max-height: calc(100vh - 100px);
                overflow-y: auto;
                overflow-x: hidden;
            }

            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li {
                display: block;
                margin: 0 0 7px 0 !important;
                padding: 0 !important;
                white-space: normal !important;
                line-height: 1.3 !important;
            }

            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li:last-child {
                margin-bottom: 0 !important;
            }

            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li:not(:last-child) {
                margin-bottom: 7px !important;
            }

            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li:last-child {
                margin-bottom: 0 !important;
            }

            /* "Rule:" / "Matched Value:" */
            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li > strong:first-child {
                color: var(--wpacu-tt-accent) !important;
                font-weight: 600;
            }

            /* Strong values: Categories, Farm, matched pattern, etc. */
            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li > strong:not(:first-child),

            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li > span > strong {
                color: rgba(240, 246, 252, .7) !important;
                text-decoration: underline;
            }

            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li > ul.wpacu-tooltip-matched-values > li > span > strong {
                color: rgba(240, 246, 252, .7) !important;
                font-weight: 600;
            }

            /* Matched values list */
            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li > ul.wpacu-tooltip-matched-values {
                margin: 7px 0 0 0 !important;
                padding: 0 !important;
                list-style: none !important;
            }

            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li > ul.wpacu-tooltip-matched-values > li {
                margin: 0 0 10px 0 !important;
                padding: 4px !important;
                border-radius: 4px;
                line-height: 1.3 !important;
            }

            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li > ul.wpacu-tooltip-matched-values > li:last-child {
                margin-bottom: 0 !important;
            }

            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li > ul.wpacu-tooltip-matched-values > li:not(.wpacu-matched-pattern) {
                color: var(--wpacu-tt-match-text);
                background-color: var(--wpacu-tt-match-bg);
            }

            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip > ul > li > ul.wpacu-tooltip-matched-values > li.wpacu-matched-pattern {
                border: 1px solid var(--wpacu-tt-match-bg);
            }

            /*
             * Tooltip arrow.
             *
             * The tooltip itself is position: fixed and its top/left are calculated via JS.
             * The arrow is still positioned relative to the tooltip box.
             */
            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip::before,
            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip::after {
                content: "";
                position: absolute;
                top: var(--wpacu-tt-arrow-top, 10px);
                border-style: solid;
                border-color: transparent;
                pointer-events: none;
            }

            /*
             * Default: tooltip opens to the right of the item.
             * Arrow appears on the left side of the tooltip and points to the menu item.
             */
            #wpadminbar .wpacu-admin-bar-rule-item:not(.wpacu-tooltip-open-left) .wpacu-rule-tooltip::before {
                right: 100%;
                left: auto;
                border-width: 6px;
                border-right-color: var(--wpacu-tt-border);
                border-left-color: transparent;
            }

            #wpadminbar .wpacu-admin-bar-rule-item:not(.wpacu-tooltip-open-left) .wpacu-rule-tooltip::after {
                right: 100%;
                left: auto;
                top: calc(var(--wpacu-tt-arrow-top, 10px) + 1px);
                border-width: 5px;
                border-right-color: var(--wpacu-tt-bg);
                border-left-color: transparent;
            }

            /*
             * Flipped: tooltip opens to the left of the item.
             * Arrow appears on the right side of the tooltip and points to the menu item.
             */
            #wpadminbar .wpacu-admin-bar-rule-item.wpacu-tooltip-open-left .wpacu-rule-tooltip::before {
                left: 100%;
                right: auto;
                border-width: 6px;
                border-left-color: var(--wpacu-tt-border);
                border-right-color: transparent;
            }

            #wpadminbar .wpacu-admin-bar-rule-item.wpacu-tooltip-open-left .wpacu-rule-tooltip::after {
                left: 100%;
                right: auto;
                top: calc(var(--wpacu-tt-arrow-top, 10px) + 1px);
                border-width: 5px;
                border-left-color: var(--wpacu-tt-bg);
                border-right-color: transparent;
            }

            #wpadminbar .wpacu-admin-bar-rule-item .wpacu-rule-tooltip {
                margin-left: 0;
                margin-right: 0;
            }

            /*
             * Arrow poiting to the actual tooltip (right after the asset/plugin)
             */
            #wpadminbar .wpacu-admin-bar-rule-item > .ab-item {
                position: relative;
                padding-right: 2em !important;
            }

            #wpadminbar .wpacu-admin-bar-rule-item > .ab-item .wpacu-admin-bar-arrow-to-tooltip {
                position: absolute !important;
                top: 1px;
                right: 10px;
                width: 17px;
                height: 26px;
                margin: 0 !important;
                padding: 0 !important;
                pointer-events: none;
            }

            #wpadminbar .wpacu-admin-bar-rule-item > .ab-item .wpacu-admin-bar-arrow-to-tooltip::before {
                content: "\f139";
                display: block;
                position: absolute;
                top: 4px;
                right: 0;
                font: normal 17px/1 dashicons;
                color: inherit;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            #wpadminbar .wpacu-admin-bar-rule-item.wpacu-tooltip-open-left > .ab-item {
                padding-left: 2em !important;
                padding-right: 1em !important;
            }

            #wpadminbar .wpacu-admin-bar-rule-item.wpacu-tooltip-open-left > .ab-item .wpacu-admin-bar-arrow-to-tooltip {
                right: auto;
                left: 8px;
            }

            #wpadminbar .wpacu-admin-bar-rule-item.wpacu-tooltip-open-left > .ab-item .wpacu-admin-bar-arrow-to-tooltip::before {
                content: "\f141";
                right: auto;
                left: 0;
            }
        </style>
        <?php
    }

	/**
	 * @param $wp_admin_bar
     *
     * @noinspection NestedAssignmentsUsageInspection
     * */
	public function topBarInfo($wp_admin_bar)
	{
		$topTitle = WPACU_PLUGIN_TITLE;

        $anyUnloadedItems = false;
        $markedCssListForUnload = $markedJsListForUnload = array();

        if (! is_admin()) {
            $markedCssListForUnload = isset(Main::instance()->allUnloadedAssets['styles'])  ? array_unique(Main::instance()->allUnloadedAssets['styles'])  : array();
            $markedJsListForUnload  = isset(Main::instance()->allUnloadedAssets['scripts']) ? array_unique(Main::instance()->allUnloadedAssets['scripts']) : array();

            $unloadedAssetsLists = apply_filters('wpacu_internal_admin_bar_unloaded_assets_lists', array(
                'styles'  => $markedCssListForUnload,
                'scripts' => $markedJsListForUnload
            ));

            $markedCssListForUnload = isset($unloadedAssetsLists['styles']) && is_array($unloadedAssetsLists['styles'])
                ? $unloadedAssetsLists['styles']
                : array();

            $markedJsListForUnload = isset($unloadedAssetsLists['scripts']) && is_array($unloadedAssetsLists['scripts'])
                ? $unloadedAssetsLists['scripts']
                : array();

            $anyUnloadedItems = (count($markedCssListForUnload) + count($markedJsListForUnload)) > 0;
        }

        $anyUnloadedItems = apply_filters('wpacu_internal_admin_bar_any_unloaded_items', $anyUnloadedItems);

		if ($anyUnloadedItems) {
			$topTitle .= '&nbsp;<span class="wpacu-alert-sign-top-admin-bar dashicons dashicons-filter"></span>';
		}

		if (Main::instance()->settings['test_mode']) {
			$topTitle .= '&nbsp; <span class="dashicons dashicons-admin-tools"></span> <strong>TEST MODE</strong> is <strong>ON</strong>';

            $topTitle = apply_filters('wpacu_internal_admin_bar_test_mode_top_title', $topTitle);
		}

		$wp_admin_bar->add_menu(array(
			'id'    => 'assetcleanup-parent',
			'title' => $topTitle,
			'href'  => esc_url(admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_settings'))
		));

		$wp_admin_bar->add_menu(array(
			'parent' => 'assetcleanup-parent',
			'id'     => 'assetcleanup-settings',
			'title'  => __('Settings', 'wp-asset-clean-up'),
			'href'   => esc_url(admin_url( 'admin.php?page=' . WPACU_PLUGIN_ID . '_settings'))
		));

		$wp_admin_bar->add_menu(array(
			'parent' => 'assetcleanup-parent',
			'id'     => 'assetcleanup-clear-css-js-files-cache',
			'title'  => __('Clear CSS/JS Files Cache', 'wp-asset-clean-up'),
			'href'   => '#',
            'meta'   => array('class' => 'wpacu-clear-cache-link')
		));

		// Only trigger in the front-end view
		if ( ! is_admin() ) {
            $manageAssetsTitle = $manageAssetsHref = false;

            if (Main::showAssetsManagerInFrontend()) {
                $manageAssetsTitle = esc_html__('Manage Current Page Assets', 'wp-asset-clean-up'); // default
                $manageAssetsHref = '#wpacu_wrap_assets'; // same for all (bottom of the page)
            }

            if (MainFront::isHomePage()) {
                $manageAssetsTitle = esc_html__('Manage Current Homepage Assets', 'wp-asset-clean-up');

                if ( ! $manageAssetsHref ) {
                    $manageAssetsHref = esc_url(admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager'));
                }
            } elseif (MainFront::isSingularPage()) {
                global $post;

                if ( isset($post->ID) ) {
                    $manageAssetsTitle = esc_html__('Manage Current Page Assets', 'wp-asset-clean-up');

                    if ( ! $manageAssetsHref ) {
                        $manageAssetsHref = esc_url(admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_post_id=' . $post->ID));
                    }
                }
            }

            $manageAssetsData = apply_filters('wpacu_internal_admin_bar_frontend_manage_assets_data', array(
                'title' => $manageAssetsTitle,
                'href'  => $manageAssetsHref
            ));

            $manageAssetsTitle = isset($manageAssetsData['title']) ? $manageAssetsData['title'] : false;
            $manageAssetsHref  = isset($manageAssetsData['href'])  ? $manageAssetsData['href']  : false;

            if ($manageAssetsTitle && $manageAssetsHref) {
                if (Main::showAssetsManagerInFrontend()) {
                    $wp_admin_bar->add_menu(array(
                        'parent' => 'assetcleanup-parent',
                        'id'     => 'assetcleanup-jump-to-assets-list',
                        // language: alias of 'Manage Page Assets'
                        'title'  => $manageAssetsTitle . '&nbsp;<span style="vertical-align: sub;" class="dashicons dashicons-arrow-down-alt"></span>',
                        'href'   => $manageAssetsHref
                    ));
                } else {
                    $wp_admin_bar->add_menu(array(
                        'parent' => 'assetcleanup-parent',
                        'id'     => 'assetcleanup-manage-page-assets-dashboard',
                        // language: alias of 'Manage Page Assets'
                        'title'  => $manageAssetsTitle,
                        'href'   => $manageAssetsHref,
                        'meta'   => array('target' => '_blank')
                    ));
                }
            }
		}

		$wp_admin_bar->add_menu(array(
			'parent' => 'assetcleanup-parent',
			'id'     => 'assetcleanup-bulk-unloaded',
			'title'  => esc_html__('Bulk Changes', 'wp-asset-clean-up'),
			'href'   => esc_url(admin_url( 'admin.php?page=' . WPACU_PLUGIN_ID . '_bulk_unloads'))
		));

		$wp_admin_bar->add_menu( array(
			'parent' => 'assetcleanup-parent',
			'id'     => 'assetcleanup-overview',
			'title'  => esc_html__('Overview', 'wp-asset-clean-up'),
			'href'   => esc_url(admin_url( 'admin.php?page=' . WPACU_PLUGIN_ID . '_overview'))
		) );

        do_action('wpacu_internal_admin_bar_after_overview', $wp_admin_bar);

		// [START LISTING UNLOADED ASSETS]
		if ( ! is_admin() ) { // Frontend view (show any unloaded handles)
            $totalUnloadedAssets = count($markedCssListForUnload) + count($markedJsListForUnload);

			if ($totalUnloadedAssets > 0) {
                $assetUnloadsWithMultipleLineValues = self::getAssetUnloadsWithMultipleLineValues();

                $titleUnloadText = sprintf( _n( '%d unload asset rules took effect on this frontend page',
					'%d unload asset rules took effect on this frontend page', $totalUnloadedAssets, 'wp-asset-clean-up' ),
					$totalUnloadedAssets );

				$wp_admin_bar->add_menu( array(
					'parent' => 'assetcleanup-parent',
					'id'     => 'assetcleanup-asset-unload-rules-notice',
					'title'  => '<span style="margin: -10px 0 0;" class="wpacu-alert-sign-top-admin-bar dashicons dashicons-filter"></span> &nbsp; '. $titleUnloadText,
					'href'   => '#'
				) );

				if ( count( $markedCssListForUnload ) > 0 ) {
                    $assetType = 'styles';

					$wp_admin_bar->add_menu(array(
						'parent' => 'assetcleanup-asset-unload-rules-notice',
						'id'     => 'assetcleanup-asset-unload-rules-css',
						'title'  => esc_html__('CSS', 'wp-asset-clean-up'). ' ('.count( $markedCssListForUnload ).')',
						'href'   => '#'
					));

					sort($markedCssListForUnload);

					foreach ($markedCssListForUnload as $cssHandle) {
                        $classToAddToAssetItem = ' wpacu-admin-bar-rule-item wpacu-admin-bar-asset-item '; // initial

                        if ( in_array($cssHandle, $assetUnloadsWithMultipleLineValues[$assetType]) ) {
                            $classToAddToAssetItem .= ' wpacu-rule-has-multiple-line-values wpacu-asset-has-multiple-line-values ';
                        }

						$wp_admin_bar->add_menu(array(
							'parent' => 'assetcleanup-asset-unload-rules-css',
							'id'     => 'assetcleanup-asset-unload-rules-css-'.$cssHandle,
							'title'  => $cssHandle . self::generateTooltipWithAssetUnloadInfo($assetType, $cssHandle),
							'href'   => esc_url(admin_url('admin.php?page=wpassetcleanup_overview#wpacu-overview-css-'.$cssHandle)),
                            'meta'   => array(
                                'class' => $classToAddToAssetItem
                            )
						));
					}
				}

				if ( count( $markedJsListForUnload ) > 0 ) {
                    $assetType = 'scripts';

					$wp_admin_bar->add_menu(array(
						'parent' => 'assetcleanup-asset-unload-rules-notice',
						'id'     => 'assetcleanup-asset-unload-rules-js',
						'title'  => esc_html__('JavaScript', 'wp-asset-clean-up'). ' ('.count( $markedJsListForUnload ).')',
						'href'   => '#'
					));

					sort($markedJsListForUnload);

					foreach ($markedJsListForUnload as $jsHandle) {
                        $classToAddToAssetItem = ' wpacu-admin-bar-rule-item wpacu-admin-bar-asset-item '; // initial

                        if ( in_array($jsHandle, $assetUnloadsWithMultipleLineValues[$assetType]) ) {
                            $classToAddToAssetItem .= ' wpacu-rule-has-multiple-line-values wpacu-asset-has-multiple-line-values ';
                        }

						$wp_admin_bar->add_menu(array(
							'parent' => 'assetcleanup-asset-unload-rules-js',
							'id'     => 'assetcleanup-asset-unload-rules-js-'.$jsHandle,
							'title'  => $jsHandle . self::generateTooltipWithAssetUnloadInfo($assetType, $jsHandle),
							'href'   => esc_url(admin_url('admin.php?page=wpassetcleanup_overview#wpacu-overview-js-'.$jsHandle)),
                            'meta'   => array(
                                'class' => $classToAddToAssetItem
                            )
						));
					}
					}
			}
		}

        do_action('wpacu_internal_admin_bar_after_unloaded_assets_list', $wp_admin_bar);
		// [END LISTING UNLOADED ASSETS]

		}

    /**
     * @return array
     */
    public static function getAssetUnloadsWithMultipleLineValues()
    {
        if ( empty($GLOBALS[self::$assetUnloadGlobalKeyInfo]) ) {
            return array();
        }

        $assetUnloadsWithMultipleLineValues = array('styles' => array(), 'scripts' => array());

        foreach (array_keys($assetUnloadsWithMultipleLineValues) as $assetType) {
            if (empty($GLOBALS[self::$assetUnloadGlobalKeyInfo][$assetType])) {
                continue;
            }

            foreach ($GLOBALS[self::$assetUnloadGlobalKeyInfo][$assetType] as $assetHandle => $ruleInfo) {
                if (isset($ruleInfo['matched_value']) && $ruleInfo['matched_value']) {
                    $matchedValue = trim((string)$ruleInfo['matched_value']);

                    if (strpos($matchedValue, "\n") === false && strpos($matchedValue, "\r") === false) {
                        continue;
                    }

                    $assetUnloadsWithMultipleLineValues[$assetType][] = $assetHandle;
                }
            }
        }

        return $assetUnloadsWithMultipleLineValues;
    }

    /**
     * @param string $assetType
     * @param string $assetHandle
     *
     * @return string
     */
    public static function generateTooltipWithAssetUnloadInfo($assetType, $assetHandle)
    {
        $globalsKey = self::$assetUnloadGlobalKeyInfo;

        // Now, add the information of the rule set for the unload to make effect
        $ruleContentArray = array();
        $ruleContent      = '';

        $ruleInfo = isset($GLOBALS[$globalsKey][$assetType][$assetHandle]) ? $GLOBALS[$globalsKey][$assetType][$assetHandle] : array();

        if ( isset($ruleInfo['rule_label']) && $ruleInfo['rule_label'] ) {
            $ruleContentArray[] = '<li data-wpacu-asset-rule-label="true">' .
                                      '<strong>Rule:</strong> ' . trim($ruleInfo['rule_label']) .
                                  '</li>';
        }

        if ( isset($ruleInfo['matched_value']) && $ruleInfo['matched_value'] ) {
            $matchedPattern = ''; // default

            // e.g. multiple RegEx(es) per line; Highlight the matched one for the current page (request URI)
            if (isset($ruleInfo['matched_pattern']) && $ruleInfo['matched_pattern']) {
                $matchedPattern = $ruleInfo['matched_pattern'];
            }

            $matchedValue = AdminBar::formatMatchedValueForTooltip($ruleInfo['matched_value'], $matchedPattern);

            $ruleContentArray[] = '<li data-wpacu-asset-rule-matched-value="true">'.
                                      '<strong>Matched Value:</strong> ' .  trim($matchedValue) .
                                  '</li>';
        }

        if ( ! empty($ruleContentArray) ) {
            $ruleContent = implode("\n", $ruleContentArray);
        }

        if ($ruleContent === '') {
            return '';
        }

        // Return the rule information
        return <<<HTML
&nbsp;&nbsp;
<span class="wpacu-admin-bar-arrow-to-tooltip" aria-hidden="true"></span>
<span class="wpacu-rule-tooltip wpacu-asset-tooltip">
    <ul>
        {$ruleContent}
    </ul>
</span>
HTML;
    }

    /**
     * @param string $matchedValue
     * @param string $matchedPattern
     *
     * @return string
     */
    public static function formatMatchedValueForTooltip($matchedValue, $matchedPattern = '')
    {
        $matchedValue = trim((string)$matchedValue);

        if ($matchedValue === '') {
            return '';
        }

        // Single value, no textarea-style multiple lines
        if (strpos($matchedValue, "\n") === false && strpos($matchedValue, "\r") === false) {
            return $matchedValue;
        }

        $lines = preg_split('/\r\n|\r|\n/', $matchedValue);

        $output = '<ul class="wpacu-tooltip-matched-values">';

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $classToAdd = '';
            $lineOutput = $line;

            if ($matchedPattern && $line === $matchedPattern) {
                $classToAdd = 'wpacu-matched-pattern';
                $lineOutput = '<strong>'.$line.'</strong>';
            }

            $output .= '<li class="'.$classToAdd.'"><span>' . $lineOutput . '</span></li>';
        }

        $output .= '</ul>';

        return $output;
    }

    }
