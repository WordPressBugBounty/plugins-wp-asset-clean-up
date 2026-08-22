<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\AssetsManagerAdmin;
use WpAssetCleanUp\Admin\SettingsAdminOnlyForAdmin;

/**
 * Class OwnAssets
 *
 * These are plugin's own assets (CSS, JS etc.),
 * and they are used only when you're logged in and do not show in the list for unloading
 *
 * @package WpAssetCleanUp
 */
class OwnAssets
{
	/**
	 * @var array[]
	 */
	public static $ownAssets = array('styles' => array(), 'scripts' => array());

	/**
	 *
	 */
	public function __construct()
    {
        self::prepareVars();
    }

	/**
	 *
	 */
	public static function prepareVars()
    {
        self::$ownAssets['styles'] = array(
            'style_core' => array(
	            'handle'   => WPACU_PLUGIN_ID . '-style',
	            'rel_path' => '/assets/style.min.css'
            ),

            'local_fonts_preload_scanner' => array(
                'handle'   => WPACU_PLUGIN_ID . '-local-fonts-preload-scanner',
                'rel_path' => '/assets/local-fonts-preload-scanner.min.css'
            ),

            'critical_css_admin' => array(
                'handle'   => WPACU_PLUGIN_ID . '-critical-css-admin',
                'rel_path' => '/assets/critical-css-admin.min.css'
            ),

            'critical_css_admin_classic' => array(
                'handle'   => WPACU_PLUGIN_ID . '-critical-css-admin-classic',
                'rel_path' => '/assets/critical-css-admin-classic.min.css'
            ),

            'chosen' => array(
                'handle'   => WPACU_PLUGIN_ID . '-chosen-style',
                'rel_path' => '/assets/vendor/chosen/chosen.min.css'
            ),

            'tooltipster' => array(
                'handle'   => WPACU_PLUGIN_ID . '-tooltipster-style',
                'rel_path' => '/assets/vendor/tooltipster/tooltipster.bundle.min.css'
            ),

            'sweetalert2' => array(
                'handle'   => WPACU_PLUGIN_ID . '-sweetalert2-style',
                'rel_path' => '/assets/vendor/sweetalert2/dist/sweetalert2.min.css'
            ),

            'autocomplete_search_jquery_ui_custom' => array(
                'handle' => WPACU_PLUGIN_ID.'-autocomplete-jquery-ui-custom',
                'rel_path' => '/assets/vendor/auto-complete/smoothness/jquery-ui-custom.min.css'
            )
        );

        self::$ownAssets['scripts'] = array(
            'script_core' => array(
                'handle'   => WPACU_PLUGIN_ID . '-script',
                'rel_path' => '/assets/script.min.js'
            ),

            'local_fonts_preload_scanner' => array(
                'handle'   => WPACU_PLUGIN_ID . '-local-fonts-preload-scanner',
                'rel_path' => '/assets/local-fonts-preload-scanner.min.js'
            ),

            'script_cache_manager' => array(
                'handle'   => WPACU_PLUGIN_ID . '-script-cache-manager',
                'rel_path' => '/assets/script-cache-manager.min.js'
            ),

            'chosen' => array(
                'handle'   => WPACU_PLUGIN_ID . '-chosen-script',
                'rel_path' => '/assets/vendor/chosen/chosen.jquery.min.js'
            ),

            'tooltipster' => array(
	            'handle'   => WPACU_PLUGIN_ID . '-tooltipster-script',
	            'rel_path' => '/assets/vendor/tooltipster/tooltipster.bundle.min.js'
            ),

            'sweetalert2' => array(
	            'handle'   => WPACU_PLUGIN_ID . '-sweetalert2-js',
	            'rel_path' => '/assets/vendor/sweetalert2/dist/sweetalert2.min.js'
            ),

            'autocomplete_search' => array(
                'handle'   => WPACU_PLUGIN_ID . '-autocomplete-search',
                'rel_path' => '/assets/script-assets-manager-search-pages.min.js'
            )
        );

        // If script debugging is enabled, load the non-minified versions of the plugin's assets
        // Read more: https://wordpress.org/support/article/debugging-in-wordpress/#script_debug
	    if ( (defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG) || isset($_GET['wpacu_debug']) ) {
		    self::$ownAssets['styles']['style_core']['rel_path']   = '/assets/style.css';
            self::$ownAssets['styles']['local_fonts_preload_scanner']['rel_path'] = '/assets/local-fonts-preload-scanner.css';
		    self::$ownAssets['styles']['critical_css_admin']['rel_path'] = '/assets/critical-css-admin.css';
		    self::$ownAssets['styles']['critical_css_admin_classic']['rel_path'] = '/assets/critical-css-admin-classic.css';
		    self::$ownAssets['scripts']['script_core']['rel_path'] = '/assets/script.js';
            self::$ownAssets['scripts']['local_fonts_preload_scanner']['rel_path'] = '/assets/local-fonts-preload-scanner.js';

            self::$ownAssets['scripts']['script_cache_manager']['rel_path'] = '/assets/script-cache-manager.js';

		    self::$ownAssets['styles']['chosen']['rel_path']       = '/assets/vendor/chosen/chosen.css';
		    self::$ownAssets['scripts']['chosen']['rel_path']      = '/assets/vendor/chosen/chosen.jquery.js';

		    self::$ownAssets['styles']['tooltipster']['rel_path']  = '/assets/vendor/tooltipster/tooltipster.bundle.css';
		    self::$ownAssets['scripts']['tooltipster']['rel_path'] = '/assets/vendor/tooltipster/tooltipster.bundle.js';

		    self::$ownAssets['styles']['sweetalert2']['rel_path']  = '/assets/vendor/sweetalert2/dist/sweetalert2.css';
		    self::$ownAssets['scripts']['sweetalert2']['rel_path'] = '/assets/vendor/sweetalert2/dist/sweetalert2.js';

		    self::$ownAssets['styles']
             ['autocomplete_search_jquery_ui_custom']['rel_path']  = '/assets/vendor/auto-complete/smoothness/jquery-ui-custom.css';
		    self::$ownAssets['scripts']
             ['autocomplete_search']['rel_path']                   = '/assets/script-assets-manager-search-pages.js';
	    }
    }

    /**
     * @return void
     */
    public static function adminChosenScriptInline()
    {
        // Only in specific plugin's pages
        if ( ! (isset($_GET['page']) && $_GET['page'] && is_string($_GET['page']) && strpos($_GET['page'], 'wpassetcleanup_') !== false) ) {
            return;
        }

        // Only in "Settings" and "Plugins Manager" plugin pages
        if ( ! in_array($_GET['page'], array('wpassetcleanup_settings', 'wpassetcleanup_plugins_manager')) ) {
            return;
        }

        $chosenScriptInlineExtraBranches = apply_filters(
            'wpacu_internal_own_assets_chosen_script_inline_extra_branches',
            ''
        );

        $chosenScriptInline = <<<JS
jQuery(document).ready(function($) {
    if (typeof window.wpacuInitChosen !== 'function') {
        return;
    }

    $('.wpacu_chosen_select').each(function() {
        var \$select = $(this);

        if (\$select.hasClass('wpacu_access_via_specific_users_dd_search')) {
            /*
            * [Access via specific users search DD]
            */
            window.wpacuInitChosen(\$select, {
                'width'                                       : '100%',
                'no_results_text'                             : '&nbsp;',
                'reset_search_field_on_update'                : false,
                'reset_multiple_search_field_on_focus_change' : false
            });
            /*
            * [/Access via specific users search DD]
            */
        } else if (\$select.hasClass('wpacu_access_via_specific_users_dd')) {
            /*
            * [Access via specific users DD]
            */
            window.wpacuInitChosen(\$select, {
                'width' : '100%'
            });
            /*
            * [/Access via specific users DD]
            */
        } {$chosenScriptInlineExtraBranches} else {
            /*
            * Default (only having the class "wpacu_chosen_select")
             */
            window.wpacuInitChosen(\$select);
        }
    });
});
JS;
        wp_add_inline_script(OwnAssets::$ownAssets['scripts']['chosen']['handle'], $chosenScriptInline);
    }

    /**
	 * @return array[]
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function getOwnAssetsHandles($assetType = '')
    {
        if ( ! Menu::userCanAccessPlugin() ) {
            return array();
        }

        self::prepareVars();

	    $allPluginStyleHandles = $allPluginScriptHandles = array();

        foreach (self::$ownAssets['styles'] as $assetValues) {
            if (isset($assetValues['handle']) && $assetValues['handle']) {
	            $allPluginStyleHandles[] = $assetValues['handle'];
            }
        }

	    foreach (self::$ownAssets['scripts'] as $assetValues) {
		    if (isset($assetValues['handle']) && $assetValues['handle']) {
			    $allPluginScriptHandles[] = $assetValues['handle'];
		    }
	    }

	    if ($assetType !== '') {
            if ($assetType === 'styles') {
                return $allPluginStyleHandles;
            }

            return $allPluginScriptHandles;
	    }

        return array_merge($allPluginStyleHandles, $allPluginScriptHandles);
    }


    /**
     * Apply a new internal filter and then the previous public filter only if somebody still uses it.
     * This keeps backwards compatibility while allowing all internal hooks to use the wpacu_internal_ prefix.
     *
     * @return mixed
     */
    public static function applyInternalFilterWithFallback()
    {
        $args = func_get_args();

        if (count($args) < 3) {
            return isset($args[2]) ? $args[2] : null;
        }

        $internalHookName = array_shift($args);
        $fallbackHookName = array_shift($args);

        $internalArgs = array_merge(array($internalHookName), $args);
        $filteredValue = call_user_func_array('apply_filters', $internalArgs);

        if (has_filter($fallbackHookName)) {
            $args[0] = $filteredValue;
            $fallbackArgs = array_merge(array($fallbackHookName), $args);
            $filteredValue = call_user_func_array('apply_filters', $fallbackArgs);
        }

        return $filteredValue;
    }

	/**
	 *
	 */
	public function init()
    {
        add_filter('wpacu_internal_object_data', array($this, 'objectData'));

        add_action('admin_enqueue_scripts',      array($this, 'stylesAndScriptsForAdmin'));
        add_action('wp_enqueue_scripts',         array($this, 'stylesAndScriptsForPublic'));

        // Query strings for debugging purpuses: 'wpacu_unload_own_style_assets', 'wpacu_unload_own_script_assets'
        // e.g. wpacu_unload_own_style_assets=handle | wpacu_unload_own_script_assets=handle1,handle2
        if (isset($_GET['wpacu_unload_own_style_assets']) || isset($_GET['wpacu_unload_own_script_assets'])) {
            add_action('admin_enqueue_scripts',  array($this, 'unloadOwnAssetsForDebuggingPurposes'), 20);
            add_action('wp_enqueue_scripts',     array($this, 'unloadOwnAssetsForDebuggingPurposes'), 20);
        }

	    // Code only for the Dashboard
	    add_action('admin_head',       array($this, 'inlineAdminHeadCode'));
	    add_action('admin_footer',     array($this, 'inlineAdminFooterCode'));

	    // Code for both the Dashboard and the Front-end view
	    add_action('admin_head',       array($this, 'inlineCodeHead'));
	    add_action('wp_head',          array($this, 'inlineCodeHead'));

        add_action('admin_footer',     array($this, 'inlineCommonCodeFooter'), PHP_INT_MAX);
        add_action('wp_footer',        array($this, 'inlineCommonCodeFooter'), PHP_INT_MAX);

	    // Rename ?ver= to ?wpacuversion to prevent other plugins from stripping "ver"
	    // This is valid in the front-end and the Dashboard
	    add_filter('script_loader_src', array($this, 'ownAssetLoaderSrc'), 10, 2);
	    add_filter('style_loader_src',  array($this, 'ownAssetLoaderSrc'), 10, 2);

        add_filter('style_loader_tag',  array($this, 'ownAssetLoaderTag'), 10, 2);
	    add_filter('script_loader_tag', array($this, 'ownAssetLoaderTag'), 10, 2);
    }

    /**
     * @param $wpacuObjectData
     *
     * @return mixed
     */
    public function objectData($wpacuObjectData)
    {
        if (isset($wpacuObjectData['page']) && $wpacuObjectData['page'] === WPACU_PLUGIN_ID . '_assets_manager') {
            // page_request_for => page_type
            $pageTypeMap = array(
                'posts'             => 'post',
                'pages'             => 'page',
                'custom_post_types' => 'custom_post_type',
                '404_not_found'     => '404',
                'custom_taxonomies' => 'taxonomy',
                'media_attachment'  => 'media'
            );

            if ($wpacuObjectData['page_request_for'] === 'custom_post_types' && isset($_GET['wpacu_post_type_view']) && $_GET['wpacu_post_type_view'] === 'archives') {
                $wpacuObjectData['page_type'] = 'custom_post_type_archive';
            } else {
                $wpacuObjectData['page_type'] = isset($pageTypeMap[$wpacuObjectData['page_request_for']])
                        ? $pageTypeMap[$wpacuObjectData['page_request_for']]
                        : $wpacuObjectData['page_request_for'];
            }
        }

        return $wpacuObjectData;
    }

    /**
	 * @return void
	 */
	public function inlineCodeHead()
    {
	    if (wp_style_is(self::$ownAssets['styles']['style_core']['handle'])) {
		    echo Misc::preloadAsyncCssFallbackOutput();
	    }
    }

    /**
     * This method is used for both front-end and /wp-admin/ view as the top admin bar can be loaded on both sides
     *
     * @return void
     */
    public function inlineCommonCodeFooter()
    {
        if (Main::isPluginClearCacheLinkAccessible()) {
            global $wp_styles, $wp_scripts;

            if ( ! in_array(self::$ownAssets['styles']['style_core']['handle'], array_keys($wp_styles->registered)) ||
                 ! in_array(self::$ownAssets['scripts']['script_cache_manager']['handle'], array_keys($wp_scripts->registered)) ) {
                return;
            }
            ?>
            <div id="wpacu-main-loading-spinner" class="wpacu_hide">
                <div id="wpacu-main-loading-spinner-content">
                    <div>
                        <img src="<?php echo WPACU_PLUGIN_URL; ?>/assets/icons/loader-horizontal.svg" alt="" />
                        <!-- Depending on the situation, the text will be shown from one of the DIVs below -->
                        <div id="wpacu-main-loading-spinner-text"></div>

                        <div data-wpacu-clear-cache-text="1" class="wpacu_hide">
                            <?php esc_attr_e('Clearing CSS/JS assets\' cache'); ?>... <?php esc_attr_e('Please wait until this notice disappears'); ?>...
                        </div>
                        <div data-wpacu-updating-text="1" class="wpacu_hide">
                            <?php _e('Updating'); ?>... <?php _e('Please wait'); ?>...
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }

	/**
	 *
	 */
	public function inlineAdminHeadCode()
	{
		?>
        <style <?php echo Misc::getStyleTypeAttribute(); ?> data-wpacu-own-inline-style="true">
            <?php
            // For the main languages, leave the style it was always set as it worked well
            $applyDefaultStyleForCurrentLang = (strpos(get_locale(), 'en_') !== false)
                || (strpos(get_locale(), 'es_') !== false)
                || (strpos(get_locale(), 'fr_') !== false)
                || (strpos(get_locale(), 'de_') !== false);

            if ( (! $applyDefaultStyleForCurrentLang) || wpacuIsPluginActive('WPShapere/wpshapere.php') ) {
                // This would also work well if the language is Arabic (the text shown right to left)
            ?>
                /* Compatibility with "Wordpress Admin Theme - WPShapere" plugin - make sure Asset CleanUp's icon is not misaligned */
                .menu-top.toplevel_page_wpassetcleanup_getting_started .wp-menu-image > img { width: 26px; height: auto; }
            <?php
            } else {
            ?>
                .menu-top.toplevel_page_wpassetcleanup_getting_started .wp-menu-image > img { width: 26px; height: auto; position: absolute; left: 8px; top: -4px; }
            <?php
            }

            if (Main::instance()->settings['hide_from_side_bar']) {
                // Just hide the menu without removing any of its pages from the menu (for sidebar cleanup purposes)
                ?>
                #toplevel_page_wpassetcleanup_getting_started { display: none !important; }
                <?php
            } elseif (Menu::isPluginPage()) {
                // The menu is shown: make the sidebar area a bit larger so the whole "Asset CleanUp Pro" menu text is seen properly when viewing its pages
                ?>
                #adminmenuback, #adminmenuwrap, #adminmenu, #adminmenu .wp-submenu { width: 172px; }
                #wpcontent, #wpfooter { margin-left: 172px; }
                <?php
            }
            ?>

            .wpacu-manage-redirected-fetch-url .dashicons {
                width: 18px;
                height: 18px;
                margin-right: 4px;
                font-size: 18px;
                line-height: 22px !important;
                vertical-align: text-bottom !important;
            }
        </style>

        <script>
        (function (window, document) {
            'use strict';

            var controllers = [];
            var animationFrameId = null;
            var globalListenersActive = false;

            function getViewportHeight() {
                return window.innerHeight || document.documentElement.clientHeight;
            }

            function getNumericOption(value, fallback) {
                var resolvedValue = (typeof value === 'function') ? value() : value;
                var numericValue = Number(resolvedValue);

                return Number.isFinite(numericValue) ? numericValue : fallback;
            }

            function resolveElement(value) {
                if (!value) {
                    return null;
                }

                if (typeof value === 'string') {
                    return document.querySelector(value);
                }

                return value.nodeType === 1 ? value : null;
            }

            function resolveElements(values) {
                var resolvedElements = [];
                var valuesList = Array.isArray(values) ? values : [values];

                valuesList.forEach(function (value) {
                    if (typeof value === 'string') {
                        document.querySelectorAll(value).forEach(function (element) {
                            if (resolvedElements.indexOf(element) === -1) {
                                resolvedElements.push(element);
                            }
                        });

                        return;
                    }

                    var element = resolveElement(value);

                    if (element && resolvedElements.indexOf(element) === -1) {
                        resolvedElements.push(element);
                    }
                });

                return resolvedElements;
            }

            function isRendered(element) {
                if (!element || !element.isConnected) {
                    return false;
                }

                var styles = window.getComputedStyle(element);

                return styles.display !== 'none' &&
                    styles.visibility !== 'hidden' &&
                    element.getClientRects().length > 0;
            }

            function getDefaultFixedTopElements() {
                return resolveElements([
                    '#wpadminbar',
                    '[data-wpacu-fixed-top-offset]'
                ]);
            }

            function getDefaultFixedBottomElements() {
                return resolveElements('[data-wpacu-fixed-bottom-offset]');
            }

            function getViewportBounds(options) {
                var visualViewport = window.visualViewport;
                var viewportTop = visualViewport ? visualViewport.offsetTop : 0;
                var viewportBottom = viewportTop + (visualViewport ? visualViewport.height : getViewportHeight());

                viewportTop += getNumericOption(options.viewportOffsetTop, 0);
                viewportBottom -= getNumericOption(options.viewportOffsetBottom, 0);

                var fixedTopElements = options.fixedTopElements === false
                    ? []
                    : getDefaultFixedTopElements().concat(resolveElements(options.fixedTopElements || []));

                fixedTopElements.forEach(function (element) {
                    if (!isRendered(element)) {
                        return;
                    }

                    var styles = window.getComputedStyle(element);

                    if (styles.position !== 'fixed' && styles.position !== 'sticky') {
                        return;
                    }

                    var rect = element.getBoundingClientRect();

                    if (rect.bottom > viewportTop && rect.top <= viewportTop + 2) {
                        viewportTop = Math.max(viewportTop, rect.bottom);
                    }
                });

                var fixedBottomElements = options.fixedBottomElements === false
                    ? []
                    : getDefaultFixedBottomElements().concat(resolveElements(options.fixedBottomElements || []));

                fixedBottomElements.forEach(function (element) {
                    if (!isRendered(element)) {
                        return;
                    }

                    var styles = window.getComputedStyle(element);

                    if (styles.position !== 'fixed' && styles.position !== 'sticky') {
                        return;
                    }

                    var rect = element.getBoundingClientRect();

                    if (rect.top < viewportBottom && rect.bottom >= viewportBottom - 2) {
                        viewportBottom = Math.min(viewportBottom, rect.top);
                    }
                });

                return {
                    top: viewportTop,
                    bottom: viewportBottom
                };
            }

            function isClippingElement(element) {
                var styles = window.getComputedStyle(element);
                var overflowY = styles.overflowY;

                return overflowY === 'auto' ||
                    overflowY === 'scroll' ||
                    overflowY === 'hidden' ||
                    overflowY === 'clip';
            }

            function findClippingAncestors(areaElement) {
                var clippingAncestors = [];
                var currentElement = areaElement.parentElement;

                while (currentElement && currentElement !== document.documentElement) {
                    if (isClippingElement(currentElement)) {
                        clippingAncestors.push(currentElement);
                    }

                    currentElement = currentElement.parentElement;
                }

                return clippingAncestors;
            }

            function getElementInnerBounds(element) {
                var rect = element.getBoundingClientRect();
                var innerTop = rect.top + element.clientTop;

                return {
                    top: innerTop,
                    bottom: innerTop + element.clientHeight
                };
            }

            function clamp(value, minimum, maximum) {
                return Math.max(minimum, Math.min(value, maximum));
            }

            function scheduleAllUpdates() {
                if (animationFrameId !== null) {
                    return;
                }

                animationFrameId = window.requestAnimationFrame(function () {
                    animationFrameId = null;

                    controllers.slice().forEach(function (controller) {
                        controller.update();
                    });
                });
            }

            function startGlobalListeners() {
                if (globalListenersActive) {
                    return;
                }

                globalListenersActive = true;

                /* Capture is intentional: native scroll events do not bubble. */
                document.addEventListener('scroll', scheduleAllUpdates, true);
                window.addEventListener('resize', scheduleAllUpdates, { passive: true });

                if (window.visualViewport) {
                    window.visualViewport.addEventListener('resize', scheduleAllUpdates, { passive: true });
                    window.visualViewport.addEventListener('scroll', scheduleAllUpdates, { passive: true });
                }
            }

            function stopGlobalListenersIfUnused() {
                if (!globalListenersActive || controllers.length > 0) {
                    return;
                }

                globalListenersActive = false;

                document.removeEventListener('scroll', scheduleAllUpdates, true);
                window.removeEventListener('resize', scheduleAllUpdates);

                if (window.visualViewport) {
                    window.visualViewport.removeEventListener('resize', scheduleAllUpdates);
                    window.visualViewport.removeEventListener('scroll', scheduleAllUpdates);
                }

                if (animationFrameId !== null) {
                    window.cancelAnimationFrame(animationFrameId);
                    animationFrameId = null;
                }
            }

            /**
             * Keeps a WPACU loader centred inside the currently visible part of an area.
             *
             * @param {HTMLElement} areaElement Loading area with position: relative.
             * @param {Object} [options]
             * @returns {Function} Cleanup function. It also exposes update() and refresh().
             */
            function wpacuCenterSpinnerInView(areaElement, options) {
                options = options || {};

                if (!areaElement || areaElement.nodeType !== 1) {
                    return function () {};
                }

                var loaderElement = areaElement.querySelector(
                    options.loaderSelector || '.wpacu-area-spinner-loader'
                );

                if (!loaderElement) {
                    return function () {};
                }

                var clippingElements = [];
                var resizeObserver = null;
                var destroyed = false;

                var controller = {
                    refresh: function () {
                        var explicitClippingElements = resolveElements(
                            options.scrollContainers || options.scrollContainer || []
                        );

                        var detectedClippingElements = options.detectClippingAncestors === false
                            ? []
                            : findClippingAncestors(areaElement);

                        clippingElements = explicitClippingElements.concat(detectedClippingElements)
                            .filter(function (element, index, allElements) {
                                return element !== areaElement && allElements.indexOf(element) === index;
                            });

                        if (resizeObserver) {
                            resizeObserver.disconnect();
                            resizeObserver.observe(areaElement);
                            resizeObserver.observe(loaderElement);

                            clippingElements.forEach(function (element) {
                                resizeObserver.observe(element);
                            });
                        }

                        scheduleAllUpdates();
                    },

                    update: function () {
                        if (destroyed || !areaElement.isConnected || !loaderElement.isConnected) {
                            return;
                        }

                        var areaBounds = getElementInnerBounds(areaElement);
                        var areaHeight = areaElement.clientHeight;

                        if (areaHeight <= 0) {
                            return;
                        }

                        var visibleBounds = getViewportBounds(options);
                        var visibleTop = Math.max(areaBounds.top, visibleBounds.top);
                        var visibleBottom = Math.min(areaBounds.bottom, visibleBounds.bottom);

                        clippingElements.forEach(function (clippingElement) {
                            if (!isRendered(clippingElement)) {
                                return;
                            }

                            var clippingBounds = getElementInnerBounds(clippingElement);
                            visibleTop = Math.max(visibleTop, clippingBounds.top);
                            visibleBottom = Math.min(visibleBottom, clippingBounds.bottom);
                        });

                        var isVisible = visibleBottom > visibleTop;
                        areaElement.setAttribute('data-wpacu-spinner-area-visible', isVisible ? '1' : '0');

                        if (!isVisible) {
                            return;
                        }

                        var loaderHeight = loaderElement.getBoundingClientRect().height || loaderElement.offsetHeight;
                        var edgePadding = Math.max(0, getNumericOption(options.edgePadding, 6));
                        var desiredCenter = ((visibleTop + visibleBottom) / 2) - areaBounds.top;
                        var minimumCenter = (loaderHeight / 2) + edgePadding;
                        var maximumCenter = areaHeight - (loaderHeight / 2) - edgePadding;
                        var finalCenter;

                        if (maximumCenter < minimumCenter) {
                            finalCenter = areaHeight / 2;
                        } else {
                            finalCenter = clamp(desiredCenter, minimumCenter, maximumCenter);
                        }

                        areaElement.style.setProperty(
                            '--wpacu-spinner-visible-center-y',
                            finalCenter.toFixed(2) + 'px'
                        );
                    },

                    destroy: function () {
                        if (destroyed) {
                            return;
                        }

                        destroyed = true;

                        var controllerIndex = controllers.indexOf(controller);

                        if (controllerIndex !== -1) {
                            controllers.splice(controllerIndex, 1);
                        }

                        if (resizeObserver) {
                            resizeObserver.disconnect();
                        }

                        areaElement.style.removeProperty('--wpacu-spinner-visible-center-y');
                        areaElement.removeAttribute('data-wpacu-spinner-area-visible');

                        stopGlobalListenersIfUnused();
                    }
                };

                if (typeof window.ResizeObserver === 'function') {
                    resizeObserver = new window.ResizeObserver(scheduleAllUpdates);
                }

                controllers.push(controller);
                startGlobalListeners();
                controller.refresh();
                controller.update();

                var cleanup = function () {
                    controller.destroy();
                };

                cleanup.update = controller.update;
                cleanup.refresh = controller.refresh;
                cleanup.destroy = controller.destroy;

                return cleanup;
            }

            window.wpacuCenterSpinnerInView = wpacuCenterSpinnerInView;
        }(window, document));

        /**
         * Add and control a WPACU loading spinner.
         *
         * @param {string|Element} target Element or CSS selector.
         * @param {Object} options Spinner options.
         *
         * @return {Function} Function that stops and removes the spinner.
         */
        function wpacuShowAreaSpinner(target, options)
        {
            var defaults = {
                position: 'top', // 'top' or 'center'
                //top: '-32px',
                edgePadding: 8,
                removeSpinnerOnStop: true
            };

            options = Object.assign({}, defaults, options || {});

            var areaElement = typeof target === 'string'
                ? document.querySelector(target)
                : target;

            if (!areaElement) {
                console.warn(
                    'WPACU spinner target element was not found:',
                    target
                );

                return function () {};
            }

            if (
                options.position !== 'top' &&
                options.position !== 'center'
            ) {
                console.warn(
                    'Invalid WPACU spinner position:',
                    options.position
                );

                options.position = 'top';
            }

            /*
             * Prevent duplicate spinner elements.
             */
            var spinnerElement = areaElement.querySelector(
                ':scope > .wpacu-area-spinner-loader'
            );

            if (!spinnerElement) {
                spinnerElement = document.createElement('span');

                spinnerElement.className = 'wpacu-area-spinner-loader';
                spinnerElement.setAttribute('aria-hidden', 'true');

                areaElement.appendChild(spinnerElement);
            }

            areaElement.classList.add(
                'wpacu-area-spinner-not-ready'
            );

            areaElement.setAttribute('aria-busy', 'true');

            var stopCentering = null;
            var hasStopped = false;

            if (options.position === 'center') {
                areaElement.classList.add(
                    'wpacu-spinner-position-visible-center'
                );

                /*
                 * Remove a previous inline top value that might have been
                 * applied when the loader was positioned at the top.
                 */
                spinnerElement.style.removeProperty('top');

                stopCentering = wpacuCenterSpinnerInView(
                    areaElement,
                    {
                        edgePadding: options.edgePadding
                    }
                );
            } else {
                areaElement.classList.remove(
                    'wpacu-spinner-position-visible-center'
                );

                spinnerElement.style.top = options.top;
            }

            return function wpacuStopAreaSpinner() {
                if (hasStopped) {
                    return;
                }

                hasStopped = true;

                if (typeof stopCentering === 'function') {
                    stopCentering();
                }

                areaElement.classList.remove(
                    'wpacu-area-spinner-not-ready',
                    'wpacu-spinner-position-visible-center'
                );

                areaElement.removeAttribute('aria-busy');

                spinnerElement.style.removeProperty('top');

                if (
                    options.removeSpinnerOnStop &&
                    spinnerElement.parentNode === areaElement
                ) {
                    spinnerElement.remove();
                }
            };
        }
        </script>
        <?php
    }

	/**
	 *
	 */
	public function inlineAdminFooterCode()
	{
		if (isset($_GET['page']) && $_GET['page'] === WPACU_PLUGIN_ID.'_settings') {
			// Only relevant in the "Settings" area
			?>
            <script type="text/javascript">
                // Tab Area | Keep selected tab after page reload
                if (location.href.indexOf('#') !== -1) {
                    var hashFromUrl = location.href.substr(location.href.indexOf('#'));
                    //wpacuTabOpenSettingsArea(event, hashFromUrl.substring(1));
                    //console.log(hashFromUrl);
                    jQuery('a[href="'+ hashFromUrl +'"]').trigger('click');
                    //console.log(hashFromUrl.substring(1));
                }
            </script>
			<?php
		}

        do_action('wpacu_internal_own_assets_inline_admin_footer_code');
	}


    /**
     * Stop loading own plugin assets for debugging purposes
     *
     * @return void
     */
    public static function unloadOwnAssetsForDebuggingPurposes()
    {
        foreach (array('wpacu_unload_own_style_assets', 'wpacu_unload_own_script_assets') as $debugQueryString) {
            if ( ! (isset($_GET[$debugQueryString]) && $_GET[$debugQueryString]) ) {
                continue;
            }

            $allAssetsToClear = array();

            $wpacuUnloadOwnAssets = Misc::getVar('get', $debugQueryString);

            if (strpos($wpacuUnloadOwnAssets, ',') === false) { // No comma, just one asset targeted
                $allAssetsToClear[] = $wpacuUnloadOwnAssets;
            } else {
                foreach (explode(',', $wpacuUnloadOwnAssets) as $wpacuUnloadOwnAsset) {
                    $allAssetsToClear[] = $wpacuUnloadOwnAsset;
                }
            }

            foreach ($allAssetsToClear as $assetToClear) {
                if ($debugQueryString === 'wpacu_unload_own_style_assets' && in_array($assetToClear, self::getOwnAssetsHandles('styles'))) {
                    wp_deregister_style($assetToClear);
                    wp_dequeue_style($assetToClear);
                } elseif (in_array($assetToClear, self::getOwnAssetsHandles('scripts'))) {
                    wp_deregister_script($assetToClear);
                    wp_dequeue_script($assetToClear);
                }
            }
        }
    }

    /**
     *
     */
    public function stylesAndScriptsForAdmin()
    {
		if (! Menu::userCanAccessPlugin()) {
			return;
		}

        $this->_enqueueAdminStyles();
		$this->_enqueueAdminScripts();
	}

	/**
	 *
	 */
	public function stylesAndScriptsForPublic()
    {
		// Do not print it when an AJAX call is made from the Dashboard
		if (WPACU_GET_LOADED_ASSETS_ACTION === true) {
			return;
		}

		// Only for the administrator with the right permission
		if ( ! Menu::userCanAccessPlugin() ) {
			return;
		}

	    // Do not load any CSS & JS belonging to Asset CleanUp if in "Elementor" preview
	    if (isset($_GET['elementor-preview']) && $_GET['elementor-preview'] && Main::instance()->isFrontendEditView) {
	        return;
	    }

	    if ( isset($_GET['wpacu_clean_load']) ) {
	        return;
        }

        $this->enqueuePublicStyles();
        $this->enqueuePublicScripts();

        do_action('wpacu_internal_own_assets_after_enqueue_public_assets', $this);
    }

	/**
	 *
	 */
	private function _enqueueAdminStyles()
    {
        if ( ! self::shouldLoadCoreAssets()) {
            return;
        }

        wp_enqueue_style(
            self::$ownAssets['styles']['style_core']['handle'],
            plugins_url(self::$ownAssets['styles']['style_core']['rel_path'], WPACU_PLUGIN_FILE),
            array(),
            self::assetVer(self::$ownAssets['styles']['style_core']['rel_path'])
        );

        if (Misc::getVar('get', 'page') === WPACU_PLUGIN_ID . '_settings') {
            wp_enqueue_style(
                self::$ownAssets['styles']['local_fonts_preload_scanner']['handle'],
                plugins_url(self::$ownAssets['styles']['local_fonts_preload_scanner']['rel_path'], WPACU_PLUGIN_FILE),
                array(self::$ownAssets['styles']['style_core']['handle']),
                self::assetVer(self::$ownAssets['styles']['local_fonts_preload_scanner']['rel_path'])
            );
        }
    }

	/**
	 *
     * @noinspection NestedAssignmentsUsageInspection
     */
	private function _enqueueAdminScripts()
    {
        // Cache Manager will always load if any of the plugin's assets have to load
        wp_register_script(
            self::$ownAssets['scripts']['script_cache_manager']['handle'],
            plugins_url(self::$ownAssets['scripts']['script_cache_manager']['rel_path'], WPACU_PLUGIN_FILE),
            array('jquery'),
            self::assetVer(self::$ownAssets['scripts']['script_cache_manager']['rel_path'])
        );

        wp_localize_script(
            self::$ownAssets['scripts']['script_cache_manager']['handle'],
            'wpacu_object',
            self::applyInternalFilterWithFallback('wpacu_internal_object_data', 'wpacu_object_data', self::generateObjectData())
        );

        wp_enqueue_script(self::$ownAssets['scripts']['script_cache_manager']['handle']);

        if ( ! self::shouldLoadCoreAssets() ) {
            // Only load everything below in specific pages (e.g. own plugin page, edit post page with CSS/JS manager shown, .etc)
            return;
        }

        wp_register_script(
	        self::$ownAssets['scripts']['script_core']['handle'],
            plugins_url(self::$ownAssets['scripts']['script_core']['rel_path'], WPACU_PLUGIN_FILE),
            array('jquery', self::$ownAssets['scripts']['script_cache_manager']['handle']),
            self::assetVer(self::$ownAssets['scripts']['script_core']['rel_path'])
        );

		wp_enqueue_script(self::$ownAssets['scripts']['script_core']['handle']);

        if (Misc::getVar('get', 'page') === WPACU_PLUGIN_ID . '_settings') {
            wp_enqueue_script(
                self::$ownAssets['scripts']['local_fonts_preload_scanner']['handle'],
                plugins_url(self::$ownAssets['scripts']['local_fonts_preload_scanner']['rel_path'], WPACU_PLUGIN_FILE),
                array('jquery', self::$ownAssets['scripts']['script_core']['handle']),
                self::assetVer(self::$ownAssets['scripts']['local_fonts_preload_scanner']['rel_path']),
                true
            );
        }

		// Load jQuery Chosen on "Settings", "CSS & JS Manager" -> "Manage CSS/JS" (homepage & any post type page)
	    $isDashManageAssetsPage = false;

        $page = Misc::getVar('get', 'page');

        if ($page === WPACU_PLUGIN_ID . '_assets_manager') {
	        $manageCssJsSubPage     = ( isset( $_GET['wpacu_sub_page'] ) && $_GET['wpacu_sub_page'] ) ? $_GET['wpacu_sub_page'] : 'manage_css_js';
	        $isDashManageAssetsPage = ( $manageCssJsSubPage === 'manage_css_js' ) &&
              // if 'wpacu_for' is not used, it will be defaulted to either homepage or single post page
              // if it's used, it has to be in the list specified below, other jQuery Chosen would be irrelevant
              ( ! isset( $_GET['wpacu_for'] )
                || ( isset( $_GET['wpacu_for'] ) && in_array( $_GET['wpacu_for'], array(
                    'homepage',
                    'pages',
                    'posts',
                    'custom_post_types',
                    'media_attachment'
              ) ) ) );
        }

		// Standard edit post page
	    global $pagenow;

	    $isEditPostAreaWithCssJsManagerEnabled = ($pagenow === 'post.php' && Misc::getVar('get', 'post')
            && Misc::getVar('get', 'action') === 'edit')
            && Main::instance()->settings['dashboard_show'] == 1
            && Main::instance()->settings['show_assets_meta_box'];

        $useEnhancedInputs = Settings::useEnhancedInputs(Main::instance()->settings);

        $loadjQueryChosenForCurrentPage = ($page === WPACU_PLUGIN_ID . '_settings' || $isDashManageAssetsPage || $isEditPostAreaWithCssJsManagerEnabled);

        $loadjQueryChosenForCurrentPage = (bool) apply_filters(
            'wpacu_internal_own_assets_load_jquery_chosen_admin',
            $loadjQueryChosenForCurrentPage,
            $page,
            $isDashManageAssetsPage,
            $isEditPostAreaWithCssJsManagerEnabled,
            $useEnhancedInputs
        );

        // A page/extension can request Chosen through the filter, but the native
        // interface remains authoritative and never loads the replacement control.
        $loadjQueryChosen = $useEnhancedInputs && $loadjQueryChosenForCurrentPage;

		if ($loadjQueryChosen) {
		    $this->loadjQueryChosen();
        }

        if ($isEditPostAreaWithCssJsManagerEnabled ||
            in_array($page, array(WPACU_PLUGIN_ID . '_settings', WPACU_PLUGIN_ID . '_assets_manager', WPACU_PLUGIN_ID . '_plugins_manager'))) {
			// [Start] SweetAlert
			wp_enqueue_style(
				self::$ownAssets['styles']['sweetalert2']['handle'],
				plugins_url(self::$ownAssets['styles']['sweetalert2']['rel_path'], WPACU_PLUGIN_FILE),
				array(),
				2
			);

			add_action('admin_head', static function() {
			?>
				<style <?php echo Misc::getStyleTypeAttribute(); ?> data-wpacu-own-inline-style="true">
                body[class*='asset-cleanup'] .swal2-container {
                    z-index: 1000000;
                }

                <?php echo apply_filters('wpacu_internal_own_assets_sweetalert_inline_style', ''); ?>

				.wpacu-swal2-overlay {
					z-index: 10000000;
				}

                .wpacu-swal2-container {
                    z-index: 100000000;
                }

                .wpacu-swal2-html-container {
                    line-height: 30px;
                }

                .wpacu-swal2-title {
                    margin: 0 0 20px;
                    font-size: 1.2em;
                }

				.wpacu-swal2-text {
					line-height: 24px;
				}

				.wpacu-swal2-footer {
					text-align: center;
					padding: 13px 16px 20px;
				}

				.wpacu-swal2-button.wpacu-swal2-button--confirm {
					background-color: #008f9c;
				}

				.wpacu-swal2-button.wpacu-swal2-button--confirm:hover {
					background-color: #006e78;
				}
				</style>
			<?php
			});

			// Changed "Swal" to "wpacuSwal" to avoid conflicts with other plugins using SweetAlert
			wp_enqueue_script(
				self::$ownAssets['scripts']['sweetalert2']['handle'],
				plugins_url(self::$ownAssets['scripts']['sweetalert2']['rel_path'], WPACU_PLUGIN_FILE),
				array('jquery'),
				1.2
			);

			do_action('wpacu_internal_own_assets_after_sweetalert_enqueue', $page, $isEditPostAreaWithCssJsManagerEnabled);
			// [End] SweetAlert
        }

		if (in_array($page, array(WPACU_PLUGIN_ID . '_plugins_manager', WPACU_PLUGIN_ID . '_overview', WPACU_PLUGIN_ID . '_bulk_unloads'))) {
			// [Start] Tooltipster Style
			wp_enqueue_style(
				self::$ownAssets['styles']['tooltipster']['handle'],
				plugins_url(self::$ownAssets['styles']['tooltipster']['rel_path'], WPACU_PLUGIN_FILE),
				array(),
				1
			);
			// [End] Tooltipster Style

			// [Start] Tooltipster Script
			wp_enqueue_script(
				self::$ownAssets['scripts']['tooltipster']['handle'],
				plugins_url(self::$ownAssets['scripts']['tooltipster']['rel_path'], WPACU_PLUGIN_FILE),
				array('jquery'),
				1
			);

			$tooltipsterScriptInline = <<<JS
jQuery(document).ready(function($) { $('.wpacu-tooltip').tooltipster({ contentCloning: true, delay: 0 }); });
JS;
			wp_add_inline_script(self::$ownAssets['scripts']['tooltipster']['handle'], $tooltipsterScriptInline);
			// [End] Tooltipster Script
        }

        /*
         * [START] Critical CSS
         */
        if (isset($_GET['page'], $_GET['wpacu_sub_page']) &&
            $_GET['page'] === WPACU_PLUGIN_ID . '_assets_manager' &&
            $_GET['wpacu_sub_page'] === 'manage_critical_css') {
            wp_enqueue_script( 'wp-theme-plugin-editor' );
            wp_enqueue_style( 'wp-codemirror' );

            $cm_settings = array();

            if (function_exists('wp_enqueue_code_editor')) {
                $cm_settings['codeEditor'] = wp_enqueue_code_editor(
                    array('type' => 'text/css')
                );
            }

            $customPagesInlineJS = ''; // only fills if the "Custom Pages" tab is used

            if (isset($_GET['wpacu_for']) && $_GET['wpacu_for'] === 'custom-pages') {
                $cm_settings_custom_pages = array();

                if (function_exists('wp_enqueue_code_editor')) {
                    $cm_settings_custom_pages['codeEditor'] = wp_enqueue_code_editor(
                        array('type' => 'text/x-php')
                    );
                }

                wp_localize_script( 'jquery', 'wpacu_cm_settings_custom_pages', $cm_settings_custom_pages );

                $customPagesInlineJS = <<<JS
// Custom Pages
wp.codeEditor.initialize($('#wpacu-php-editor-textarea'), wpacu_cm_settings_custom_pages);
JS;
            }

            wp_localize_script( 'jquery', 'wpacu_cm_settings', $cm_settings );

            $wpacuCodeMirrorInlineScript = <<<JS
jQuery(document).ready(function($) {
  // Editable CSS
  var \$wpacuCssEditorTextarea = $('#wpacu-css-editor-textarea'),
      wpacuEditor = false;

  if (\$wpacuCssEditorTextarea.length && typeof wp !== 'undefined' && wp.codeEditor && typeof wp.codeEditor.initialize === 'function') {
      wpacuEditor = wp.codeEditor.initialize(\$wpacuCssEditorTextarea, wpacu_cm_settings);
  }
  
  {$customPagesInlineJS}
  
  function wpacuUpdateCriticalCssRuleUi(\$statusInput) {
      var isEnabled = \$statusInput.prop('checked'),
          \$optionsArea = $('#wpacu-critical-css-options-area'),
          \$statusText = $('#wpacu-critical-css-rule-status-text');

      \$optionsArea.toggleClass('wpacu-faded', ! isEnabled);
      \$statusText
          .toggleClass('wpacu-enabled', isEnabled)
          .toggleClass('wpacu-disabled', ! isEnabled)
          .text(isEnabled ? 'Enabled' : 'Disabled');
  }

  $(document).on('change', '#wpacu_critical_css_status', function() {
      wpacuUpdateCriticalCssRuleUi($(this));
  });

  $(document).on('change', '#wpacu-critical-css-context-choice', function() {
      if (this.value) {
          window.location.href = this.value;
      }
  });

  $(document).on('click', '[data-wpacu-critical-css-show-search]', function() {
      var \$searchPanel = $('#wpacu-critical-css-object-search-panel'),
          \$searchInput = $('#wpacu-critical-css-object-search');

      if (! \$searchPanel.length) {
          return;
      }

      \$searchPanel.stop(true, true).slideDown(120, function() {
          \$searchInput.trigger('focus');
      });
  });

  $(document).on('click', '[data-wpacu-critical-css-hide-search]', function() {
      $('#wpacu-critical-css-object-search-panel').stop(true, true).slideUp(120);
  });

  $(document).on('input', '#wpacu-critical-css-existing-rules-filter', function() {
      var keyword = $.trim($(this).val()).toLowerCase(),
          visibleRows = 0;

      $('.wpacu-critical-css-rule-row').each(function() {
          var \$row = $(this),
              searchText = String(\$row.attr('data-wpacu-rule-search') || '').toLowerCase(),
              showRow = keyword === '' || searchText.indexOf(keyword) !== -1;

          \$row.toggle(showRow);

          if (showRow) {
              visibleRows++;
          }
      });

      $('#wpacu-critical-css-existing-rules-no-match').toggle(visibleRows === 0);
  });

  $(document).on('change', '.wpacu-critical-css-output-choice input[type="radio"]', function() {
      $('.wpacu-critical-css-output-choice label').removeClass('wpacu-active');
      $(this).closest('label').addClass('wpacu-active');
  });
  
  $(document).on('submit', '#wpacu-critical-css-form', function() {
      var wpacuCriticalCssContent = '';

      if (wpacuEditor && wpacuEditor.codemirror) {
          wpacuCriticalCssContent = wpacuEditor.codemirror.getValue();
      } else if (\$wpacuCssEditorTextarea.length) {
          wpacuCriticalCssContent = \$wpacuCssEditorTextarea.val();
      }

      if ($.trim(wpacuCriticalCssContent) === '' && $('#wpacu_critical_css_status').prop('checked')) {
          alert('You have chosen to activate the critical CSS. You need to provide the CSS content before submitting this form.');
          return false;
      }
      
      $('#wpacu-updating-critical-css').addClass('wpacu-show').removeClass('wpacu-hide');
      $('#wpacu-update-critical-css-button-area').find('.button').prop('disabled', true).attr('value', 'SAVING...');
  });
})
JS;
            $wpacuCodeMirrorInlineStyle = <<<CSS
.CodeMirror {
  border: 1px solid #ddd;
}

/* "CSS & JS Manager" -- "Manage Critical CSS" -- "Custom Pages" */
#wpacu-critical-css-custom-pages .CodeMirror {
    height: auto;
}

#wpacu-critical-css-options-area.wpacu-faded {
    opacity: 0.58;
}

#wpacu-css-editor-textarea {
    width: 100%;
    min-height: 600px;
}

#wpacu-update-critical-css-button-area {
    display: inline-block;
    margin: 14px 0 0 0;
}

#wpacu-update-critical-css-button-area input {
    padding: 5px 18px;
    height: 45px;
    font-size: 15px;
}

body[class*=" version-7-"] #wpacu-update-critical-css-button-area input {
    height: inherit;
}

#wpacu-updating-critical-css.wpacu-hide {
    display: none;
}

#wpacu-updating-critical-css.wpacu-show {
    display: inline-block;
    margin: 13px 0 0 8px;
}
CSS;
            wp_add_inline_script('wp-theme-plugin-editor', $wpacuCodeMirrorInlineScript);
            wp_add_inline_style('wp-codemirror', $wpacuCodeMirrorInlineStyle);
        }
        /*
         * [END] Critical CSS
         */
    }

    /**
     * @param array $data
     *
     * @return array
     * @noinspection NestedAssignmentsUsageInspection
     */
    public static function generateObjectData($data = array())
    {
        $page            = Misc::getVar('get', 'page');
        $pageRequestFor  = Misc::getVar('get', 'wpacu_for');

        if ( ! $pageRequestFor ) {
            $postIdRequested = isset( $_GET['wpacu_post_id'] ) && $_GET['wpacu_post_id'] ? (int)$_GET['wpacu_post_id'] : 0;

            // e.g. /wp-admin/admin.php?page=wpassetcleanup_assets_manager&wpacu_post_id=17193 (no "wpacu_for" was mentioned)
            if ($postIdRequested) {
                $pageRequestFor = AssetsManagerAdmin::detectPostTypeTypeFromRequestedPostId($postIdRequested);
            } else {
                $pageRequestFor = 'homepage';
            }
        }

        if ( isset($data['post_id']) ) {
            $postId = $data['post_id'];
        } else {
            // If 'post_id' is not set, then it's a front-end view
            $postId = AssetsManager::getCurrentPostIdForCssJsManager($page, $pageRequestFor);
        }
        
        $postId = (int) $postId;

        $currentHostSameAsHostFromTargetUrl = true;

        if (is_admin()) {
            // Dashboard View
            if (self::checkForFetchUrlInDashboardView()) {
                // e.g. edit category page (where the CSS/JS manager is loading)
                $pageUrl = self::getFetchUrlDashboardView($postId);

                if ($pageUrl && Misc::isHttpsSecure()) {
                    $pageUrl = str_replace('http://', 'https://', $pageUrl);
                }
            } else {
                // The cache is cleared most likely outside the CSS/JS manager
                // e.g. after a theme switch
                $pageUrl = home_url('/'); // get the public homepage URL, which can differ from the WordPress installation URL

                // In a multilingual setup, the public homepage can include a language directory
                // (e.g. /en/). Avoid preloading the unprefixed URL as it can redirect indefinitely.
                $localizedHomeUrl = apply_filters('wpml_home_url', $pageUrl);

                if (is_string($localizedHomeUrl) && esc_url_raw($localizedHomeUrl) !== '') {
                    $pageUrl = $localizedHomeUrl;
                }
            }

            // Get the current hostname from the current URL request
            $currentHost          = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

            $hostFromPageUrlParse = parse_url($pageUrl);
            $hostFromPageUrl      = isset($hostFromPageUrlParse['host']) && $hostFromPageUrlParse['host'] ? $hostFromPageUrlParse['host'] : '';

            // Normalize both hostnames (convert to lowercase for case-insensitive comparison)
            // If it returns false, the current host could be "domain.com" and the host from the target page could be "es.domain.com"
            $currentHostSameAsHostFromTargetUrl = $currentHost && $hostFromPageUrl
                                                  && strtolower($currentHost) === strtolower($hostFromPageUrl);
        } else {
            // Front-end view
            // Get the post ID if not is set (it will be '0' if not a singular page)
            $pageUrl = Misc::getCurrentPageUrl();
        }

        $isForHomePage = false;

        if (isset($data['page_request_for']) && $data['page_request_for'] === 'homepage') {
            // Dashboard
            $isForHomePage = true;
        } elseif (MainFront::isHomePage()) {
            // Front-end view
            $isForHomePage = true;
        }

        // If the post status is 'private' only direct method can be used to fetch the assets
        // as the remote post one will return a 404 error since the page is accessed as a guest visitor
        $postStatus      = $postId > 0 ? get_post_status($postId) : false;
        $wpacuDomGetType = ($postStatus === 'private') ? 'direct' : Main::$domGetType;

        $svgReloadIcon = <<<HTML
<svg aria-hidden="true" role="img" focusable="false" class="dashicon dashicons-cloud" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M14.9 9c1.8.2 3.1 1.7 3.1 3.5 0 1.9-1.6 3.5-3.5 3.5h-10C2.6 16 1 14.4 1 12.5 1 10.7 2.3 9.3 4.1 9 4 8.9 4 8.7 4 8.5 4 7.1 5.1 6 6.5 6c.3 0 .7.1.9.2C8.1 4.9 9.4 4 11 4c2.2 0 4 1.8 4 4 0 .4-.1.7-.1 1z"></path></svg>
HTML;

        $pageUrlParts      = wp_parse_url($pageUrl);
        $pageUrlRequestUri = is_array($pageUrlParts) && isset($pageUrlParts['path']) && $pageUrlParts['path'] !== ''
                             ? $pageUrlParts['path']
                             : '/';

        if (is_array($pageUrlParts) && isset($pageUrlParts['query']) && $pageUrlParts['query'] !== '') {
            $pageUrlRequestUri .= '?' . $pageUrlParts['query'];
        }

        $homepageQueryStringsToIgnore = wpacuGetQueryStringsToBeIgnoredPredefinedList();

        if ( ! is_array($homepageQueryStringsToIgnore) ) {
            $homepageQueryStringsToIgnore = array();
        }

        $extraHomepageQueryStringsToIgnore = isset(Main::instance()->settings['plugins_manager_front_homepage_detect_extra_ignore_query_string_list'])
            ? trim((string)Main::instance()->settings['plugins_manager_front_homepage_detect_extra_ignore_query_string_list'])
            : '';

        if ($extraHomepageQueryStringsToIgnore !== '') {
            $extraHomepageQueryStringsToIgnoreList = strpos($extraHomepageQueryStringsToIgnore, "\n") !== false
                ? explode("\n", $extraHomepageQueryStringsToIgnore)
                : array($extraHomepageQueryStringsToIgnore);

            foreach ($extraHomepageQueryStringsToIgnoreList as $queryStringToIgnore) {
                $queryStringToIgnore = trim($queryStringToIgnore);

                if ($queryStringToIgnore !== '') {
                    $homepageQueryStringsToIgnore[] = $queryStringToIgnore;
                }
            }
        }

        $homepageQueryStringsToIgnore = array_values(array_unique($homepageQueryStringsToIgnore));

        $wpacuObjectData = array(
            'plugin_prefix'    => WPACU_PLUGIN_ID, // the same for both Lite & Pro
            'plugin_slug'      => WPACU_PLUGIN_SLUG,
            'plugin_title'     => WPACU_PLUGIN_TITLE,
            'input_style'      => Settings::getInputStyle(Main::instance()->settings),

            'reload_icon'      => $svgReloadIcon,
            'reload_msg'       => sprintf(__('Reloading %s area', 'wp-asset-clean-up'), '<strong style="margin: 0 4px;">' . WPACU_PLUGIN_TITLE . '</strong>'),
            'dom_get_type'     => $wpacuDomGetType,
            'list_show_status' => Main::instance()->settings['assets_list_show_status'],

            'start_del_e'      => Main::START_DEL_ENQUEUED,
            'end_del_e'        => Main::END_DEL_ENQUEUED,

            'start_del_h'      => Main::START_DEL_HARDCODED,
            'end_del_h'        => Main::END_DEL_HARDCODED,

            'ajax_url'         => esc_url( admin_url( 'admin-ajax.php' ) ),
            'page_url'         => $pageUrl, // post, page, custom post type, homepage etc.
            'home_url'         => home_url('/'),
            'homepage_query_strings_to_ignore' => $homepageQueryStringsToIgnore,
            'selected_context_is_homepage' => $isForHomePage
                                                || $pageRequestFor === 'homepage'
                                                || wpacuIsHomePageUrl($pageUrlRequestUri)
        );

        if ($postId > 0) {
            // Singular page
            $wpacuObjectData['post_id'] = $postId;

            // Could be a 'post', 'page' (the actual home page if it was set as a page) or custom post type / e.g. WooCommerce product
            $wpacuObjectData['page_type'] = 'post';
        } elseif ($isForHomePage) {
            $wpacuObjectData['post_id']   = 0;
            $wpacuObjectData['page_type'] = 'home'; // e.g. latest posts
        }

        $wpacuObjectData['page'] = $page;
        $wpacuObjectData['page_request_for'] = $pageRequestFor;

        $wpacuObjectData['current_host_same_as_host_from_target_url'] = $currentHostSameAsHostFromTargetUrl;

        if ( ! is_admin() ) {
            $wpacuObjectData['is_frontend_view'] = true;
        } else {
            // Assets' List Show Status only applies for edit post/page/custom post type/category/custom taxonomy
            // Dashboard pages such as "Homepage" from plugin's "CSS/JavaScript Load Manager" will fetch the list on loading
            if ($page === WPACU_PLUGIN_ID.'_assets_manager' && in_array($pageRequestFor, self::getDashboardAssetsManagerFetchPageTypes())) {
                $wpacuObjectData['override_assets_list_load'] = true;
            }

            if ( isset($_GET['wpacu_manage_dash']) || ($page === WPACU_PLUGIN_ID.'_assets_manager' && in_array($pageRequestFor, array('category', 'tag', 'custom_taxonomies', 'search', 'author', 'date', '404_not_found'))) ) {
                $wpacuObjectData['force_manage_dash'] = true;
            }

            if (isset($_GET['wpacu_term_id'], $_GET['wpacu_taxonomy']) && in_array($pageRequestFor, array('category', 'tag', 'custom_taxonomies'))) {
                $wpacuObjectData['tag_id']         = (int)$_GET['wpacu_term_id'];
                $wpacuObjectData['wpacu_taxonomy'] = sanitize_text_field($_GET['wpacu_taxonomy']);
            }

            if (get_transient(WPACU_PLUGIN_ID.'_clear_assets_cache')) {
                $wpacuObjectData['clear_cache'] = true;
                delete_transient(WPACU_PLUGIN_ID.'_clear_assets_cache');
            }
        }

        $wpacuObjectData['source_load_error_msg'] = __('The source might not be reachable', 'wp-asset-clean-up');

        $wpacuObjectData['current_post_type'] = false;

        if ( $postId > 0 ) {
            $wpacuObjectData['current_post_type']                    = get_post_type($postId);
            $wpacuObjectData['wpacu_ajax_get_post_type_terms_nonce'] = wp_create_nonce('wpacu_ajax_get_post_type_terms_nonce');
        }

        // After homepage/post/page is saved and the page is reloaded, clear the cache
        // Cache clearing default values
        $wpacuObjectData['clear_cache_via_ajax'] = $wpacuObjectData['clear_other_caches'] = false; // default

        /*
         * [Start] Trigger plugin cache and other plugins'/system caches
         */
        if (self::clearCacheViaAjax()) {
            // Instruct the script to trigger clearing the cache via AJAX
            $wpacuObjectData['clear_cache_via_ajax'] = true;
        }
        /*
         * [End] Trigger plugin cache and other plugins'/system caches
         */

        /*
         * [Start] Trigger ONLY other plugins'/system caches
         */
        // When click the "Clear CSS/JS Files Cache" link within the Dashboard (e.g. toolbar or quick action areas)
        // Cache was already cleared; Do not clear it again (save resources); Clear other caches
        // Make sure the referrer (it needs to have one) is the same URI as the currently loaded one (without any extra parameters)
        $wpacuClearOtherCaches = false;
        $wpacuReferrer         = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        if ($wpacuReferrer) {
            list(,$wpacuUriFromReferrer ) = explode('//' . parse_url($wpacuReferrer, PHP_URL_HOST), $wpacuReferrer);
            $wpacuRequestUri              = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $wpacuClearOtherCaches        = ($wpacuUriFromReferrer === $wpacuRequestUri);
        }

        if ($wpacuClearOtherCaches && get_transient(WPACU_PLUGIN_ID . '_clear_assets_cache_via_link')) {
            delete_transient(WPACU_PLUGIN_ID . '_clear_assets_cache_via_link');
            $wpacuObjectData['clear_other_caches'] = true;
        }
        /*
         * [End] Trigger ONLY other plugins'/system caches
         */

        $wpacuObjectData['redirected_fetch_title'] = __('The selected page redirects to a different URL.', 'wp-asset-clean-up');
        $wpacuObjectData['redirected_fetch_message'] = __('Asset CleanUp stopped the fetch because the destination may load a different set of CSS/JS files.', 'wp-asset-clean-up');
        $wpacuObjectData['redirected_fetch_requested_url'] = __('Requested URL:', 'wp-asset-clean-up');
        $wpacuObjectData['redirected_fetch_final_url'] = __('Redirected to:', 'wp-asset-clean-up');
        $wpacuObjectData['redirected_fetch_suggestion'] = __('If the redirect is expected and both URLs represent the same page (for example, a multilingual plugin added a language prefix), you can continue using the redirected URL.', 'wp-asset-clean-up');
        $wpacuObjectData['redirected_fetch_button'] = __('Manage assets from the redirected URL', 'wp-asset-clean-up');
        $wpacuObjectData['redirected_fetch_caution'] = __('Continue only if the redirected URL represents the page you intended to manage.', 'wp-asset-clean-up');
        $wpacuObjectData['redirected_fetch_homepage_url'] = __('The redirected URL is the homepage. To prevent homepage assets from being managed by mistake, the bypass option is not available for this redirect.', 'wp-asset-clean-up');
        $wpacuObjectData['redirected_fetch_external_url'] = __('The redirected URL is outside the current site and cannot be fetched from this area.', 'wp-asset-clean-up');

        // "Settings" -> "Plugin Usage Preferences" -> "Plugin Access"
        $wpacuObjectData['user_search_min_characters'] = __('Type at least 2 characters to search.', 'wp-asset-clean-up');
        $wpacuObjectData['user_search_no_results'] = __('No matching non-administrator users were found.', 'wp-asset-clean-up');
        $wpacuObjectData['user_search_request_failed'] = __('The user search could not be completed. Please try again.', 'wp-asset-clean-up');
        $wpacuObjectData['user_search_results_found'] = __('Matching users found: %d.', 'wp-asset-clean-up');
        $wpacuObjectData['user_already_added'] = __('This non-administrator user is already in the access list.', 'wp-asset-clean-up');
        $wpacuObjectData['user_add_request_failed'] = __('The user could not be added. Please try again.', 'wp-asset-clean-up');

        $wpacuObjectData['server_returned_404_not_found'] = sprintf(
            __('When accessing this page the server responded with a status of %s404 (Not Found)%s. If this page is meant to return this status, you can ignore this message, otherwise you might have a problem with this page if it is meant to return a standard 200 OK status.', 'wp-asset-clean-up'),
            '<strong>',
            '</strong>'
        );

        if ($pageRequestFor === '404_not_found') {
            $wpacuObjectData['server_returned_404_not_found_in_404_tab'] = sprintf(
                __('The server responded with a status of %s404 (Not Found)%s, which is expected for the requested page.', 'wp-asset-clean-up'),
                '<strong>',
                '</strong>'
            );
        }

        /*
         * Whether to clear Autoptimize Cache or not (if the plugin is enabled)
         */
        if ( ! wpacuIsPluginActive('autoptimize/autoptimize.php') ) {
            $wpacuObjectData['autoptimize_not_active'] = 1;
        } else {
            $wpacuObjectData['clear_autoptimize_cache'] = assetCleanUpClearAutoptimizeCache() ? 'true' : 'false';
        }

        /*
         * Whether to clear "Cache Enabler" Cache or not (if the plugin is enabled)
         */
        if ( ! wpacuIsPluginActive('cache-enabler/cache-enabler.php') ) {
            $wpacuObjectData['cache_enabler_not_active'] = 1;
        } else {
            $wpacuObjectData['clear_cache_enabler_cache'] = assetCleanUpClearCacheEnablerCache() ? 'true' : 'false';

            if ( assetCleanUpClearCacheEnablerCache() ) {
                $wpacuObjectData['wpacu_ajax_clear_cache_enabler_cache_nonce'] = wp_create_nonce( 'wpacu_ajax_clear_cache_enabler_cache_nonce' );
            }
        }

        $wpacuObjectData = self::wpacuObjectSecurityNonces($wpacuObjectData);
        $wpacuObjectData = self::wpacuObjectDataFetchErrors($wpacuObjectData);
        $wpacuObjectData = self::wpacuObjectDataConfirmsAlertsMsg($wpacuObjectData);

        return $wpacuObjectData;
    }

    /**
     * @return bool
     */
    public static function clearCacheViaAjax()
    {
        // After editing post/page within the Dashboard
        $dashUnloadAssetsSubmit = (isset($_POST['wpacu_unload_assets_area_loaded']) && $_POST['wpacu_unload_assets_area_loaded']);
        if ($dashUnloadAssetsSubmit) {
            return true;
        }

        // After updating the CSS/JS manager within the front-end view (when "Manage in the front-end" is enabled)
        $frontendViewPageAssetsJustUpdated = ! is_admin() &&
                                             (isset($_GET['wpacu_time']) && $_GET['wpacu_time']) &&
                                             get_transient(WPACU_PLUGIN_ID . '_frontend_assets_manager_just_updated');
        if ($frontendViewPageAssetsJustUpdated) {
            return true;
        }

        // After updating the "Settings" within the Dashboard
        $wpacuSettingsWithinDashboardJustUpdated = is_admin() &&
                                                   Misc::getVar('request', 'page') === WPACU_PLUGIN_ID . '_settings' &&
                                                   Misc::getVar('get', 'wpacu_selected_tab_area') &&
                                                   get_transient(WPACU_PLUGIN_ID . '_settings_updated');
        if ($wpacuSettingsWithinDashboardJustUpdated) {
            return true;
        }

        if ((bool) apply_filters('wpacu_internal_own_assets_clear_cache_via_ajax', false)) {
            return true;
        }

        return false;
    }

	/**
	 *
	 */
	public function loadjQueryChosen()
    {
        // [Start] Chosen Style
		wp_register_style(
			self::$ownAssets['styles']['chosen']['handle'],
			plugins_url(self::$ownAssets['styles']['chosen']['rel_path'], WPACU_PLUGIN_FILE),
			array(),
			'1.8.7'
		);

	    wp_enqueue_style(self::$ownAssets['styles']['chosen']['handle']);

		$chosenStyleInline = <<<CSS
#wpacu_hide_meta_boxes_for_post_types_chosen { margin-top: 5px; min-width: 320px; }
CSS;
		wp_add_inline_style(self::$ownAssets['styles']['chosen']['handle'], $chosenStyleInline);
		// [End] Chosen Style

		// [Start] Chosen Script
		wp_register_script(
			self::$ownAssets['scripts']['chosen']['handle'],
			plugins_url(self::$ownAssets['scripts']['chosen']['rel_path'], WPACU_PLUGIN_FILE),
			array('jquery', self::$ownAssets['scripts']['script_core']['handle']),
			'1.8.7'
		);

		wp_enqueue_script(self::$ownAssets['scripts']['chosen']['handle']);

        if (is_admin()) {
            self::adminChosenScriptInline();
        }
		// [End] Chosen Script
	}

    /**
     *
     */
    private function enqueuePublicStyles()
    {
        wp_enqueue_style(
            self::$ownAssets['styles']['style_core']['handle'],
            plugins_url(self::$ownAssets['styles']['style_core']['rel_path'], WPACU_PLUGIN_FILE),
            array(),
            self::assetVer(self::$ownAssets['styles']['style_core']['rel_path'])
        );
    }

    /**
     *
     */
    public function enqueuePublicScripts()
    {
        // Cache Manager will always load if any of the plugin's assets have to load
        wp_register_script(
            self::$ownAssets['scripts']['script_cache_manager']['handle'],
            plugins_url(self::$ownAssets['scripts']['script_cache_manager']['rel_path'], WPACU_PLUGIN_FILE),
            array('jquery'),
            self::assetVer(self::$ownAssets['scripts']['script_cache_manager']['rel_path']),
            true
        );

        wp_localize_script(
            self::$ownAssets['scripts']['script_cache_manager']['handle'],
            'wpacu_object',
            self::applyInternalFilterWithFallback('wpacu_internal_object_data', 'wpacu_object_data', self::generateObjectData())
        );

        wp_enqueue_script(self::$ownAssets['scripts']['script_cache_manager']['handle']);

        if (self::shouldLoadCoreAssets()) {
            // Core file (it also calls the cache manager)
            wp_register_script(
                self::$ownAssets['scripts']['script_core']['handle'],
                plugins_url(self::$ownAssets['scripts']['script_core']['rel_path'], WPACU_PLUGIN_FILE),
                array('jquery', self::$ownAssets['scripts']['script_cache_manager']['handle']),
                self::assetVer(self::$ownAssets['scripts']['script_core']['rel_path']),
                true
            );

            wp_enqueue_script(self::$ownAssets['scripts']['script_core']['handle']);
        }
    }

    /**
     * @return bool
     */
    public static function shouldLoadCoreAssets()
    {
        if (is_admin()) {
            // Only related to the front-end view

            /*
             * Load it on any plugin page
             */
            if (Menu::isPluginPage()) {
                return true;
            }

            $isManageInTheDashboardEnabledOnEditPostOrTaxonomy =
                Main::instance()->settings['dashboard_show'] == 1 &&
                Main::instance()->settings['show_assets_meta_box'];

            /*
             * Load it whenever an edit post/page/custom post type (e.g. WooCommerce product) is opened
             * and CSS/JS is loaded (shown there as a meta box)
             */
            global $pagenow;

            $isEditPostTaxAreaWithCssJsManagerEnabled = ($pagenow === 'post.php' && Misc::getVar('get', 'post')
                                                         && Misc::getVar('get', 'action') === 'edit')
                                                        && $isManageInTheDashboardEnabledOnEditPostOrTaxonomy;

            if ($isEditPostTaxAreaWithCssJsManagerEnabled) {
                return true;
            }

            if ( (bool) apply_filters(
                'wpacu_internal_own_assets_load_script_core_js_admin',
                false,
                $isManageInTheDashboardEnabledOnEditPostOrTaxonomy )) {
                return true;
            }
        } elseif (Main::instance()->isFrontendEditView) {
            // Front-end view is enabled
            return true;
        }

        return false;
    }

    /**
     * Kept for backward compatibility with code that calls the old method name.
     *
     * @deprecated Use shouldLoadCoreAssets() instead.
     * @return bool
     */
    public static function loadScriptCoreJs()
    {
        return self::shouldLoadCoreAssets();
    }

	/**
	 * @param $relativePath
	 *
	 * @return false|string
	 */
	public static function assetVer($relativePath)
    {
		return @filemtime(dirname(WPACU_PLUGIN_FILE) . $relativePath) ?: date('dmYHi');
	}

	/**
	 * Prevent "?ver=" or "&ver=" from being stripped when loading plugin's own assets
	 * It will force them to refresh whenever there's a change in either of the files
	 *
	 * @param $src
	 * @param $handle
	 *
	 * @return mixed
	 */
	public function ownAssetLoaderSrc($src, $handle)
	{
	    if (in_array($handle, self::getOwnAssetsHandles())) {
			$src = str_replace(
				array('?ver=',          '&ver='),
				array('?wpacuversion=', '&wpacuversion='),
                $src
            );
		}

		return $src;
	}

	/**
	 * @param $tag
	 * @param $handle
	 *
	 * @return mixed
	 */
	public function ownAssetLoaderTag($tag, $handle)
    {
        // "data-wpacu-skip": Prevent any asset alteration by any option set in "Settings"
        if (in_array($handle, self::getOwnAssetsHandles('styles'))) {
            $tag = str_replace(' href=', ' data-wpacu-skip href=', $tag);
        }

		// "data-wpacu-plugin-script": Useful in case jQuery library is deferred too (rare situations)
		if (in_array($handle, self::getOwnAssetsHandles('scripts'))) {
			$tag = str_replace(' src=', ' data-wpacu-skip data-wpacu-plugin-script src=', $tag);

            if ($handle === WPACU_PLUGIN_ID . '-chosen-script') {
                $tag = str_replace(' src=', ' defer="defer" src=', $tag);
            }
		}

		return $tag;
	}

    /**
     * What's the URL to be checked for the assets if CSS/JS manager is loaded within the Dashboard?
     *
     * @param $postId - could be empty if it's not for a post page (e.g. a "category" page)
     *
     * @return string
     */
    public static function getFetchUrlDashboardView($postId)
    {

        $fetchUrlDashboardView = apply_filters('wpacu_internal_own_assets_fetch_url_dashboard_view', '', $postId);

        if ($fetchUrlDashboardView) {
            return $fetchUrlDashboardView;
        }

        // A post/page/custom post type, or it can also be the front page URL ("Settings" -- "Reading" -- "Your homepage displays" -- "A static page")
        if ( $postId > 0 )  {
            return Misc::getPageUrl($postId);
        }

        // Homepage, last possible option for the Dashboard view
        return Misc::getPageUrl(0);
    }

    /**
     * @return bool
     */
    public static function checkForFetchUrlInDashboardView()
    {
        global $pagenow;

        // Edit taxonomy
        if ($pagenow === 'term.php' && Misc::getVar('get', 'taxonomy') && Misc::getVar('get', 'tag_ID')) {
            return true;
        }

        // Edit post/page/custom post type
        // 1) Edit it in its edit page
        if ($pagenow === 'post.php' && isset($_GET['post'], $_GET['action']) && $_GET['post'] && $_GET['action'] === 'edit') {
            return true;
        }

        // 2) Edit it the plugin's CSS/JS manager area ("CSS & JS MANAGER" -- "MANAGE CSS/JS")
        $wpacuFor = Misc::getVar('get', 'wpacu_for') ?: 'homepage';

        if (
            Misc::getVar('get', 'page') === WPACU_PLUGIN_ID . '_assets_manager'
            && in_array($wpacuFor, self::getDashboardAssetsManagerFetchPageTypes(), true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return string[]
     */
    private static function getDashboardAssetsManagerFetchPageTypes()
    {
        return array(
            'homepage',
            'posts',
            'pages',
            'custom_post_types',
            'media_attachment',
            'category',
            'tag',
            'custom_taxonomies',
            'search',
            'author',
            'date',
            '404_not_found'
        );
    }

    /**
     * @param $wpacuObjectData
     *
     * @return mixed
     */
    public static function wpacuObjectDataFetchErrors($wpacuObjectData)
    {
        $submitTicketLink = apply_filters(
            'wpacu_internal_own_assets_ajax_direct_fetch_error_submit_ticket_link',
            'https://wordpress.org/support/plugin/wp-asset-clean-up'
        );

        $wpacuObjectData['ajax_direct_fetch_error'] = <<<HTML
<div class="ajax-direct-call-error-area">
    <p class="note"><strong>Note:</strong> The checked URL returned an error when fetching the assets via AJAX call. This could be because of a firewall that is blocking the AJAX call, a redirect loop or an error in the script that is retrieving the output which could be due to an incompatibility between the plugin and the WordPress setup you are using.</p>
    <p>Here is the response from the call:</p>

    <table>
        <tr>
            <td width="135"><strong>Status Code Error:</strong></td>
            <td><span class="error-code">{wpacu_status_code_error}</span> * for more information about client and server errors, <a target="_blank" href="https://en.wikipedia.org/wiki/List_of_HTTP_status_codes">check this link</a></td>
        </tr>
        <tr>
            <td valign="top"><span class="dashicons dashicons-lightbulb" style="color: orange;"></span> <strong>Suggestion:</strong></td>
            <td>Select "WP Remote POST" as a method of retrieving the assets from the "Settings" page. If that doesn't fix the issue, just use "Manage in Front-end" option which should always work and <a target="_blank" href="{$submitTicketLink}">submit a ticket</a> about your problem.</td>
        </tr>
        <tr>
            <td valign="top"><strong>Output:</strong></td>
            <td valign="top">{wpacu_output}</td>
        </tr>
    </table>
</div>
HTML;

        // Sometimes, 200 OK (success) is returned, but due to an issue with the page, the assets list is not retrieved
        $wpacuObjectData['ajax_direct_fetch_error_with_success_response'] = <<<HTML
<div style="overflow-y: scroll; max-height: 290px;" class="ajax-direct-call-error-area">
    <p class="note"><strong>Note:</strong> The assets could not be fetched via the AJAX call. Here is the response:</p>
    <table>
        <tr>
            <td valign="top"><strong>Suggestion:</strong></td>
            <td>Select "WP Remote POST" as a method of retrieving the assets from the "Settings" page. If that doesn't fix the issue, just use "Manage in Front-end" option which should always work and <a target="_blank" href="{$submitTicketLink}">submit a ticket</a> about your problem.</td>
        </tr>
        <tr>
            <td valign="top"><strong>Output:</strong></td>
            <td valign="top">{wpacu_output}</td>
        </tr>
    </table>
</div>
HTML;
        return $wpacuObjectData;
    }

    /**
     * @param $wpacuObjectData
     *
     * @return array
     */
    public static function wpacuObjectDataConfirmsAlertsMsg($wpacuObjectData)
    {
        $wpacuObjectData['jquery_migration_disable_confirm_msg'] =
            __('Make sure to properly test your website if you unload the jQuery migration library.', 'wp-asset-clean-up')."\n\n".
            __('In some cases, due to old jQuery code triggered from plugins or the theme, unloading this migration library could cause those scripts not to function anymore and break some of the front-end functionality.', 'wp-asset-clean-up')."\n\n".
            __('If you are not sure about whether activating this option is right or not, it is better to leave it as it is (to be loaded by default) and consult with a developer.', 'wp-asset-clean-up')."\n\n".
            __('Confirm this action to enable the unloading or cancel to leave it loaded by default.', 'wp-asset-clean-up');

        $wpacuObjectData['comment_reply_disable_confirm_msg'] =
            __('This is worth disabling if you are NOT using the default WordPress comment system (e.g. you are using the website for business purposes, to showcase your products and you are not using it as a blog where people leave comments to your posts).', 'wp-asset-clean-up')."\n\n".
            __('If you are not sure about whether activating this option is right or not, it is better to leave it as it is (to be loaded by default).', 'wp-asset-clean-up')."\n\n".
            __('Confirm this action to enable the unloading or cancel to leave it loaded by default.', 'wp-asset-clean-up');

        // "Tools" - "Reset"
        $wpacuObjectData['reset_settings_confirm_msg'] =
            __('Are you sure you want to reset the settings to their default values?', 'wp-asset-clean-up')."\n\n".
            __('This is an irreversible action.', 'wp-asset-clean-up')."\n\n".
            __('Please confirm to continue or "Cancel" to abort it', 'wp-asset-clean-up');

        $wpacuObjectData['reset_critical_css_confirm_msg'] =
            __('Are you sure you want to remove all the critical CSS information?', 'wp-asset-clean-up')."\n\n".
            __('This is an irreversible action.', 'wp-asset-clean-up')."\n\n".
            __('Please confirm to continue or "Cancel" to abort it', 'wp-asset-clean-up');

		$wpacuObjectData['reset_plugins_manager_front_confirm_msg'] =
			__('Are you sure you want to remove all front-end unload rules and load exceptions from Plugins Manager?', 'wp-asset-clean-up')."\n\n".
			__('Rules configured for /wp-admin/ will be preserved. This action cannot be undone.', 'wp-asset-clean-up')."\n\n".
			__('Please confirm to continue or "Cancel" to abort it.', 'wp-asset-clean-up');

		$wpacuObjectData['reset_plugins_manager_dash_confirm_msg'] =
			__('Are you sure you want to remove all /wp-admin/ unload rules and load exceptions from Plugins Manager?', 'wp-asset-clean-up')."\n\n".
			__('Front-end rules will be preserved. This action cannot be undone.', 'wp-asset-clean-up')."\n\n".
			__('Please confirm to continue or "Cancel" to abort it.', 'wp-asset-clean-up');

	    $wpacuObjectData['reset_everything_except_settings_confirm_msg'] =
	            __('Are you sure you want to remove all Asset CleanUp data except Settings and Pro license data?', 'wp-asset-clean-up')."\n\n".
            __('This is an irreversible action.', 'wp-asset-clean-up')."\n\n".
            __('Please confirm to continue or "Cancel" to abort it.', 'wp-asset-clean-up');

        $wpacuObjectData['reset_everything_confirm_msg'] =
            __('Are you sure you want to reset everything (settings, unloads, load exceptions etc.) to the same point it was when you first activated the plugin?', 'wp-asset-clean-up')."\n\n".
            __('This is an irreversible action.', 'wp-asset-clean-up')."\n\n".
            __('Please confirm to continue or "Cancel" to abort it.', 'wp-asset-clean-up');

        // "Tools" - "Import & Export"
        $wpacuObjectData['import_confirm_msg'] =
            __('This process is NOT reversible.', 'wp-asset-clean-up')."\n\n".
            __('Please make sure you have a backup (e.g. an exported JSON file) before proceeding.', 'wp-asset-clean-up')."\n\n".
            __('Please confirm to continue or "Cancel" to abort it.', 'wp-asset-clean-up');

        $wpacuObjectData = apply_filters('wpacu_internal_own_assets_confirm_alert_messages', $wpacuObjectData);

        $wpacuObjectData['jquery_unload_alert'] = 'jQuery library is a WordPress library that it is used in WordPress plugins/themes most of the time.' . "\n\n" .
                                                  'There are currently other JavaScript "children" files connected to it, that will stop working, if this library is unloaded' . "\n\n" .
                                                  'If you are positive this page does not require jQuery (very rare cases), then you can continue by pressing "OK"' . "\n\n" .
                                                  'Otherwise, it is strongly recommended to keep this library loaded by pressing "Cancel" to avoid breaking the functionality of the website.';
        // js-cookie
        $wpacuObjectData['woo_js_cookie_unload_alert'] = 'Please be careful when unloading "js-cookie" as there are other JS files that depend on it which will also be unloaded, including "wc-cart-fragments" which is required for the functionality of the WooCommerce mini cart.' . "\n\n" .
                                                         'Click "OK" to continue or "Cancel" if you have any doubts about unloading this file';

        // wc-cart-fragments
        $wpacuObjectData['woo_wc_cart_fragments_unload_alert'] = 'Please be careful when unloading "wc-cart-fragments" as it\'s required for the functionality of the WooCommerce mini cart. Unless you are sure you do not need it on this page, it is advisable to leave it loaded.' . "\n\n" .
                                                                 'Click "OK" to continue or "Cancel" if you have any doubts about unloading this file.';

        // backbone, underscore, etc.
        $wpacuObjectData['sensitive_library_unload_alert'] = 'Please make sure to properly test this page after this particular JavaScript file is unloaded as it is usually loaded for a reason.' . "\n\n" .
                                                             'If you are not sure whether it is used or not, then consider using the "Cancel" button to avoid taking ay chances in breaking the website\'s functionality.' . "\n\n" .
                                                             'It is advised to check the browser\'s console via right-click and "Inspect" to check for any reported errors.';

        $wpacuObjectData['dashicons_unload_alert_ninja_forms_alert'] = 'It looks like you are using "Ninja Forms" plugin which is sometimes loading Dashicons for the forms\' styling.' . "\n\n" .
                                                                       'If you are sure your forms do not use Dashicons, please use the following option \'Ignore dependency rule and keep the "children" loaded\' to avoid the unloading of the "nf-display" handle.' . "\n\n" .
                                                                       'Click "OK" to continue or "Cancel" if you have any doubts about unloading the Dashicons. It is better to have Dashicons loaded, then take a chance and break the forms\' layout.';
        return $wpacuObjectData;
    }

    /**
     * @param $wpacuObjectData
     *
     * @return mixed
     */
    public static function wpacuObjectSecurityNonces($wpacuObjectData)
    {
        // Security nonces for AJAX calls
        $wpacuObjectData['wpacu_update_specific_settings_nonce']       = wp_create_nonce('wpacu_update_specific_settings_nonce');
        $wpacuObjectData['wpacu_update_asset_row_state_nonce']         = wp_create_nonce('wpacu_update_asset_row_state_nonce');
        $wpacuObjectData['wpacu_area_update_assets_row_state_nonce']   = wp_create_nonce('wpacu_area_update_assets_row_state_nonce');
        $wpacuObjectData['wpacu_print_loaded_hardcoded_assets_nonce']  = wp_create_nonce('wpacu_print_loaded_hardcoded_assets_nonce');
        $wpacuObjectData['wpacu_ajax_check_remote_file_size_nonce']    = wp_create_nonce('wpacu_ajax_check_remote_file_size_nonce');
        $wpacuObjectData['wpacu_ajax_check_external_urls_nonce']       = wp_create_nonce('wpacu_ajax_check_external_urls_nonce');
        $wpacuObjectData['wpacu_ajax_get_loaded_assets_nonce']         = wp_create_nonce('wpacu_ajax_get_loaded_assets_nonce');
        $wpacuObjectData['wpacu_ajax_load_page_restricted_area_nonce'] = wp_create_nonce('wpacu_ajax_load_page_restricted_area_nonce');
        $wpacuObjectData['wpacu_ajax_clear_cache_nonce']               = wp_create_nonce('wpacu_ajax_clear_cache_nonce');
        $wpacuObjectData['wpacu_ajax_preload_url_nonce']               = wp_create_nonce('wpacu_ajax_preload_url_nonce'); // After the CSS/JS manager's form is submitted (e.g. on an edit post/page)
        $wpacuObjectData['wpacu_add_new_no_features_load_row_nonce']    = wp_create_nonce('wpacu_add_new_no_features_load_row_nonce');

        if (Menu::isPluginPage() === 'settings' && SettingsAdminOnlyForAdmin::useAutoCompleteSearchForNonAdminUsersDd()) {
            $wpacuObjectData['wpacu_search_non_admin_users_for_dd_nonce'] = wp_create_nonce('wpacu_search_non_admin_users_for_dd_nonce');
        }

        $wpacuObjectData = apply_filters('wpacu_internal_own_assets_security_nonces', $wpacuObjectData);

        return $wpacuObjectData;
    }
}
