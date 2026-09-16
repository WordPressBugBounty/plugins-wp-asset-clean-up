<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\Overview;
use WpAssetCleanUp\Settings;

if ( ! isset($data) ) {
	exit;
}

include_once __DIR__ .  '/_top-area.php';

// Keep WordPress admin notices below the main navigation, outside the switchable views.
echo '<hr class="wp-header-end" />';

$isEditMode = Overview::isEditMode();
$inputStyle = Settings::getInputStyle(isset($data['input_style']) ? $data['input_style'] : Settings::INPUT_STYLE_ENHANCED);

if ($isEditMode) {
    $selectedOneText   = __('You have %s selection marked for deletion.',    'wp-asset-clean-up');
    $selectedMultiText = __('You have %s selections marked for deletion.',   'wp-asset-clean-up');
    $noneSelectedText  = __('You have not selected any rules for deletion.', 'wp-asset-clean-up');
    ?>
    <script>
    (function() {
        document.addEventListener('DOMContentLoaded', function () {
            const wpacuCheckboxes        = document.querySelectorAll('.wpacu-delete-checkbox');

            const wpacuNotice            = document.getElementById('wpacu-selection-notice');
            const wpacuClearAllBtn       = document.getElementById('wpacu-clear-all-rules-marked-for-deletion');

            const wpacuSelectedOneText   = <?php echo json_encode($selectedOneText); ?>;
            const wpacuSelectedMultiText = <?php echo json_encode($selectedMultiText); ?>;
            const wpacuNoneSelectedText  = <?php echo json_encode($noneSelectedText); ?>;

            function wpacuUpdateSelectionInfo() {
                const selected = Array.from(wpacuCheckboxes).filter(chk => chk.checked).length;

                if (selected > 0) {
                    const baseText            = selected === 1 ? wpacuSelectedOneText : wpacuSelectedMultiText;
                    const formatted           = baseText.replace('%s', '<strong>' + selected + '</strong>');

                    wpacuNotice.innerHTML     = formatted;
                    wpacuClearAllBtn.disabled = false;
                } else {
                    wpacuNotice.textContent    = wpacuNoneSelectedText;
                    wpacuClearAllBtn.disabled  = true;
                }
            }

            wpacuCheckboxes.forEach(chk => chk.addEventListener('change', wpacuUpdateSelectionInfo));

            wpacuClearAllBtn.addEventListener('click', function () {
                wpacuCheckboxes.forEach(chk => chk.checked = false);
                wpacuUpdateSelectionInfo();
            });

            wpacuUpdateSelectionInfo();

            /*
             * [START] On Main Overview Form Submit
             */
            var submitButton   = document.getElementById('wpacu-apply-changes');
            var loaderOnSubmit = document.getElementById('wpacu-apply-changes-loader');
            var mainForm       = document.getElementById('wpacu-overview-edit-form');

            if (!submitButton || !loaderOnSubmit || !mainForm) {
                return;
            }

            var lockMainSubmitButton = function () {
                loaderOnSubmit.classList.remove('wpacu_hide');

                submitButton.style.pointerEvents = 'none';
                submitButton.style.opacity = '0.7';

                setTimeout(function () {
                    submitButton.disabled = true;
                    submitButton.classList.add('disabled');
                }, 50);
            };

            submitButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                lockMainSubmitButton();

                if (mainForm.requestSubmit) {
                    mainForm.requestSubmit();
                } else {
                    mainForm.submit();
                }
            });

            mainForm.addEventListener('submit', function (event) {
                if (event.target !== mainForm) {
                    return;
                }

                lockMainSubmitButton();
            });
            /*
             * [END] On Main Overview Form Submit
             */
        });
    })();
    </script>
<?php } ?>

<style>
    .wpacu-script-attrs-overview {
        margin: 4px 0;
    }

    .wpacu-script-attrs-title {
        font-weight: 600;
        color: #004567;
    }

    .wpacu-script-attr-row {
        margin: 6px 0;
        padding: 5px 0;
        line-height: 1.7;
    }

    .wpacu-script-attr-row + .wpacu-script-attr-row {
        border-top: 1px dashed rgba(100, 105, 112, 0.35);
        padding-top: 7px;
    }

    .wpacu-script-attr-badge {
        display: inline-block;
        min-width: 42px;
        padding: 1px 6px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-align: center;
    }

    .wpacu-script-attr-defer {
        color: #5c3b00;
        background: #fff4cc;
        border: 1px solid #e1bd58;
    }

    .wpacu-script-attr-async {
        color: #034b63;
        background: #dff6ff;
        border: 1px solid #8ccde0;
    }

    .wpacu-script-attr-scope {
        font-weight: 600;
        margin-left: 4px;
    }

    .wpacu-script-attr-separator {
        color: #999;
        margin: 0 4px;
    }

    .wpacu-script-attr-exceptions {
        display: contents;
        margin-left: 6px;
        color: #555;
    }

    .wpacu-script-attr-except-badge {
        display: inline-block;
        padding: 1px 5px;
        margin-left: 4px;
        margin-right: 4px;
        border-radius: 4px;
        background: #f6f7f7;
        border: 1px solid #c3c4c7;
        color: #646970;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .wpacu-script-attr-toggle-wrap {
        color: #646970;
        white-space: nowrap;
    }

    .wpacu-script-attr-toggle-more {
        color: inherit;
        font-size: 12px;
        text-decoration: none;
    }

    .wpacu-script-attr-toggle-more:hover,
    .wpacu-script-attr-toggle-more:focus {
        color: #2271b1;
        text-decoration: underline;
    }
</style>

<script>
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('.wpacu-script-attr-toggle-more');

        if (! toggle) {
            return;
        }

        event.preventDefault();

        var target = document.getElementById(toggle.getAttribute('data-wpacu-target'));

        if (! target) {
            return;
        }

        var isHidden = target.style.display === 'none';

        target.style.display = isHidden ? 'inline' : 'none';
        toggle.textContent = isHidden
            ? toggle.getAttribute('data-wpacu-less-text')
            : toggle.getAttribute('data-wpacu-more-text');
    });
</script>

<div id="wpacu-overview-start"
     class="wrap wpacu-overview-wrap <?php echo esc_attr(Settings::getInputStyleCssClasses($inputStyle)); ?><?php if ( $isEditMode ) { echo ' wpacu-edit-mode'; } ?>"
     data-wpacu-input-style="<?php echo esc_attr($inputStyle); ?>">
    <div style="padding: 0 10px 10px 0; line-height: 22px;">
        <strong>Note:</strong> This overview contains all the changes of any kind (unload rules, load exceptions, preloads, notes, async/defer SCRIPT attributes, changed positions, etc.) made via Asset CleanUp to any of the loaded (enqueued) CSS/JS files as well as the plugins (e.g. unloaded on certain pages).
        To make any changes you need to the values below, please use the "CSS &amp; JS Manager" and "Plugins Manager".

        <?php
        if ( ! $isEditMode ) {
            $currentUrl          = \WpAssetCleanUp\Misc::getCurrentPageUrl(); // clean, without query strings
            $switchToEditModeUrl = add_query_arg(
                array(
                    'page'            => WPACU_PLUGIN_ID . '_overview',
                    'wpacu_edit_mode' => '1',
                ),
                $currentUrl
            );
        ?>
            You can also <a href="<?php echo $switchToEditModeUrl; ?>">switch to edit mode</a>, and you will be able to clear/edit most of the rules below (e.g. there are limitations in place in this "Overview" area).
        <?php } ?>

        <?php
        Overview::renderViewEditModeAreaToggleButton();
        ?>
    </div>
    <div class="wpacu-overview-filter-bar">
    <div class="wpacu-overview-filter-field"><label for="wpacu-overview-view"><strong><?php esc_html_e('View', 'wp-asset-clean-up'); ?></strong></label>
        <select id="wpacu-overview-view" data-shared-control="<?php esc_attr_e('Shared control', 'wp-asset-clean-up'); ?>" data-unavailable="<?php esc_attr_e('This view could not be prepared. The original view remains available.', 'wp-asset-clean-up'); ?>">
            <option value="assets"><?php esc_html_e('By Asset Type', 'wp-asset-clean-up'); ?></option>
            <option value="pages"><?php esc_html_e('Sort By Pages', 'wp-asset-clean-up'); ?></option>
        </select>
    </div>
    <div class="wpacu-overview-filter-field"><div class="wpacu-overview-filter-label">
        <span class="wpacu-overview-filter-help" tabindex="0" role="img" aria-label="<?php esc_attr_e('About rule status', 'wp-asset-clean-up'); ?>" aria-describedby="wpacu-overview-rule-state-help">
            <span aria-hidden="true">ⓘ</span>
            <span id="wpacu-overview-rule-state-help" class="wpacu-overview-filter-tooltip" role="tooltip">
                <span class="wpacu-overview-scope-description"><strong><?php esc_html_e('Active rules', 'wp-asset-clean-up'); ?></strong><?php esc_html_e('Enabled and relevant according to the current Overview checks. Their effect depends on the pages and conditions they target.', 'wp-asset-clean-up'); ?></span>
                <span class="wpacu-overview-scope-separator" role="separator"></span>
                <span class="wpacu-overview-scope-description"><strong><?php esc_html_e('Inactive rules', 'wp-asset-clean-up'); ?></strong><?php esc_html_e('Saved rules that currently have no effect, for example after a plugin or theme is deactivated, when a rule is disabled, or while Critical CSS delivery is paused.', 'wp-asset-clean-up'); ?></span>
                <span class="wpacu-overview-scope-separator" role="separator"></span>
                <span class="wpacu-overview-scope-description"><strong><?php esc_html_e('Orphaned load exceptions', 'wp-asset-clean-up'); ?></strong><?php esc_html_e('Also inactive: an asset or plugin has load exceptions but no unload rules left. It already loads normally, so those exceptions have nothing to override.', 'wp-asset-clean-up'); ?></span>
            </span>
        </span>
        <label for="wpacu-overview-rule-state"><strong><?php esc_html_e('Rule status', 'wp-asset-clean-up'); ?></strong></label></div>
        <select id="wpacu-overview-rule-state" aria-describedby="wpacu-overview-rule-state-help">
            <option value="all"><?php esc_html_e('Show all rules', 'wp-asset-clean-up'); ?></option>
            <option value="active"><?php esc_html_e('Show active rules', 'wp-asset-clean-up'); ?></option>
            <option value="inactive"><?php esc_html_e('Show inactive rules', 'wp-asset-clean-up'); ?></option>
        </select>
    </div>
    <div class="wpacu-overview-filter-field"><span class="wpacu-overview-filter-arrow" aria-hidden="true">→</span><div class="wpacu-overview-filter-label"><label for="wpacu-overview-rule-scope"><strong><?php esc_html_e('Rule scope', 'wp-asset-clean-up'); ?></strong></label>
        <span class="wpacu-overview-filter-help" tabindex="0" role="img" aria-label="<?php esc_attr_e('About rule scope', 'wp-asset-clean-up'); ?>" aria-describedby="wpacu-overview-rule-scope-help">
            <span aria-hidden="true">ⓘ</span>
        <span id="wpacu-overview-rule-scope-help" class="wpacu-overview-filter-tooltip" role="tooltip">
            <span class="wpacu-overview-scope-description"><strong><?php esc_html_e('Per-page rules', 'wp-asset-clean-up'); ?></strong><?php esc_html_e('Target a specific page, post, author or taxonomy term.', 'wp-asset-clean-up'); ?></span>
            <span class="wpacu-overview-scope-separator" role="separator"></span>
            <span class="wpacu-overview-scope-description"><strong><?php esc_html_e('Bulk changes', 'wp-asset-clean-up'); ?></strong><?php esc_html_e('Target multiple pages through shared conditions, including regex.', 'wp-asset-clean-up'); ?></span>
            <span class="wpacu-overview-scope-separator" role="separator"></span>
            <span class="wpacu-overview-scope-description"><strong><?php esc_html_e('Site-wide rules', 'wp-asset-clean-up'); ?></strong><?php esc_html_e('Apply globally, potentially with per-page exceptions.', 'wp-asset-clean-up'); ?></span>
            <span class="wpacu-overview-scope-separator" role="separator"></span>
            <span class="wpacu-overview-scope-footnote"><?php esc_html_e('Filtering does not clear selections or change what will be saved.', 'wp-asset-clean-up'); ?></span>
        </span>        </span></div>
        <select id="wpacu-overview-rule-scope" aria-describedby="wpacu-overview-rule-scope-help">
            <option value="all"><?php esc_html_e('Show all', 'wp-asset-clean-up'); ?></option>
            <option value="page"><?php esc_html_e('Per-page Rules (specific targets)', 'wp-asset-clean-up'); ?></option>
            <option value="bulk"><?php esc_html_e('Bulk Changes (multiple pages)', 'wp-asset-clean-up'); ?></option>
            <option value="sitewide"><?php esc_html_e('Site-wide Rules (all pages)', 'wp-asset-clean-up'); ?></option>
        </select>

    </div>
    <span class="wpacu-overview-filter-note"><?php esc_html_e('Filtering keeps your selections.', 'wp-asset-clean-up'); ?></span>
    </div>
    <p id="wpacu-overview-rule-scope-empty" hidden><?php esc_html_e('No rules match this filter.', 'wp-asset-clean-up'); ?></p>
    <link rel="stylesheet" href="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/wpacu-overview-views.min.css?ver=' . filemtime(WPACU_PLUGIN_DIR . '/assets/wpacu-overview-views.min.css')); ?>" />
    <script src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/wpacu-overview-views.min.js?ver=' . filemtime(WPACU_PLUGIN_DIR . '/assets/wpacu-overview-views.min.js')); ?>" defer></script>
    <div id="wpacu-overview-sub-wrap" style="padding: 0 10px 0 0;">
        <?php if ($isEditMode) { ?>
            <form id="wpacu-overview-edit-form" action="<?php echo admin_url('admin.php?page=wpassetcleanup_overview&wpacu_edit_mode=1'); ?>" method="post">
                <?php wp_nonce_field('wpacu_overview_edit_form', 'wpacu_overview_edit_form_nonce'); ?>
        <?php } ?>

        <?php if (isset($data['external_srcs_ref']) && $data['external_srcs_ref']) { ?>
            <span data-wpacu-external-srcs-ref="<?php echo esc_attr($data['external_srcs_ref']); ?>" style="display: none;"></span>
        <?php } ?>

        <?php
        $wpacuOverviewNavItems = array(
            'wpacu-overview-section-styles' => array(
                'label' => __('CSS', 'wp-asset-clean-up'),
                'count' => isset($data['handles']['styles']) ? count($data['handles']['styles']) : 0,
            ),
            'wpacu-overview-section-critical-css' => array(
                'label' => __('Critical CSS', 'wp-asset-clean-up'),
                'count' => isset($data['critical_css_overview']['rules_count']) ? (int)$data['critical_css_overview']['rules_count'] : 0,
            ),
            'wpacu-overview-section-scripts' => array(
                'label' => __('JavaScript', 'wp-asset-clean-up'),
                'count' => isset($data['handles']['scripts']) ? count($data['handles']['scripts']) : 0,
            ),
        );

        if ( ! empty($data['plugins_with_rules']['plugins']) ) {
            $wpacuOverviewNavItems['wpacu-overview-section-plugins-front'] = array(
                'label' => __('Plugins: Front-end', 'wp-asset-clean-up'),
                'count' => count($data['plugins_with_rules']['plugins']),
            );
        }

        if ( ! empty($data['plugins_with_rules']['plugins_dash']) ) {
            $wpacuOverviewNavItems['wpacu-overview-section-plugins-admin'] = array(
                'label' => __('Plugins: Dashboard', 'wp-asset-clean-up'),
                'count' => count($data['plugins_with_rules']['plugins_dash']),
            );
        }
        ?>

        <?php
        $wpacuPageOptionsCount = Overview::countPagesWithOptions(isset($data['page_options_results']) ? $data['page_options_results'] : array());
        if ($wpacuPageOptionsCount > 0) {
            $wpacuOverviewNavItems['wpacu-overview-section-page-options'] = array(
                'label' => __('Page Options', 'wp-asset-clean-up'),
                'count' => $wpacuPageOptionsCount,
                'warning_count' => true,
            );
        }
        $wpacuSpecialSettingsCount = count(array_filter(Overview::getSpecialSettings()));
        if ($wpacuSpecialSettingsCount > 0) {
            $wpacuOverviewNavItems['wpacu-overview-section-special-settings'] = array(
                'label' => __('Special Settings', 'wp-asset-clean-up'),
                'count' => $wpacuSpecialSettingsCount,
            );
        }
        ?>
        <nav id="wpacu-overview-navigation" class="wpacu-overview-navigation wpacu-overview-navigation-initializing" aria-label="<?php esc_attr_e('Overview sections', 'wp-asset-clean-up'); ?>">
            <div class="wpacu-overview-navigation-links">
                <?php foreach ($wpacuOverviewNavItems as $wpacuOverviewSectionId => $wpacuOverviewNavItem) { ?>
                    <a href="#<?php echo esc_attr($wpacuOverviewSectionId); ?>" data-wpacu-overview-nav-target="<?php echo esc_attr($wpacuOverviewSectionId); ?>">
                        <?php echo esc_html($wpacuOverviewNavItem['label']); ?>
                        <span class="wpacu-overview-navigation-count"<?php if (! empty($wpacuOverviewNavItem['warning_count'])) { ?> style="color: #cc0000;"<?php } ?>><?php echo (int)$wpacuOverviewNavItem['count']; ?></span>
                    </a>
                <?php } ?>
            </div>

            <label class="wpacu-overview-navigation-sticky-option">
                <input id="wpacu-overview-navigation-sticky-toggle" type="checkbox" checked="checked" />
                <span><?php esc_html_e('Keep navigation visible while scrolling', 'wp-asset-clean-up'); ?></span>
            </label>
        </nav>

        <script>
        (function() {
                var navigation = document.getElementById('wpacu-overview-navigation');
                var stickyToggle = document.getElementById('wpacu-overview-navigation-sticky-toggle');

                if (! navigation || ! stickyToggle) {
                    return;
                }

                var storageKey = 'wpacu_overview_navigation_sticky';
                var storedPreference = null;
                var scrollAnimationId = 0;

                function highlightArrival(target, reduceMotion) {
                    if (reduceMotion || ! target.classList || ! target.classList.contains('wpacu-overview-section-title')) {
                        return;
                    }

                    target.classList.remove('wpacu-overview-section-arrival');
                    void target.offsetWidth;
                    target.classList.add('wpacu-overview-section-arrival');

                    setTimeout(function() {
                        target.classList.remove('wpacu-overview-section-arrival');
                    }, 1200);
                }

                function animateScrollTo(target, instant) {
                    var animationId = ++scrollAnimationId;
                    var startY = window.pageYOffset;
                    var targetStyles = window.getComputedStyle ? window.getComputedStyle(target) : null;
                    var scrollMarginTop = targetStyles ? parseFloat(targetStyles.scrollMarginTop) || 0 : 0;
                    // The sticky navigation can wrap to any number of rows.
                    // Use its sticky inset, not its current document position before it sticks.
                    var navigationStyles = window.getComputedStyle ? window.getComputedStyle(navigation) : null;
                    if (navigationStyles && (navigationStyles.position === 'sticky' || navigationStyles.position === 'fixed')) {
                        var navigationInset = parseFloat(navigationStyles.top) || 0;
                        scrollMarginTop = Math.max(scrollMarginTop, navigationInset + navigation.getBoundingClientRect().height + 16);
                    }
                    var targetY = Math.max(0, startY + target.getBoundingClientRect().top - scrollMarginTop);
                    var distance = targetY - startY;
                    var duration = 280;
                    var startTime = window.performance.now();
                    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                    if (instant || reduceMotion || distance === 0) {
                        window.scrollTo(0, targetY);
                        highlightArrival(target, reduceMotion);
                        return;
                    }

                    function animate(currentTime) {
                        if (animationId !== scrollAnimationId) {
                            return;
                        }

                        var progress = Math.min((currentTime - startTime) / duration, 1);
                        var easedProgress = 1 - Math.pow(1 - progress, 3);

                        window.scrollTo(0, startY + (distance * easedProgress));

                        if (progress < 1) {
                            window.requestAnimationFrame(animate);
                        } else {
                            highlightArrival(target, reduceMotion);
                        }
                    }

                    window.requestAnimationFrame(animate);
                }

                try {
                    storedPreference = window.localStorage.getItem(storageKey);
                } catch (e) {}

                stickyToggle.checked = storedPreference !== '0';
                navigation.classList.toggle('wpacu-overview-navigation-sticky', stickyToggle.checked);
                navigation.classList.remove('wpacu-overview-navigation-initializing');

                stickyToggle.addEventListener('change', function() {
                    navigation.classList.toggle('wpacu-overview-navigation-sticky', stickyToggle.checked);
                    queueActiveNavigationUpdate();

                    try {
                        window.localStorage.setItem(storageKey, stickyToggle.checked ? '1' : '0');
                    } catch (e) {}
                });

                navigation.addEventListener('click', function(event) {
                    var link = event.target.closest('a[data-wpacu-overview-nav-target]');

                    if (! link) {
                        return;
                    }

                    var target = document.getElementById(link.getAttribute('data-wpacu-overview-nav-target'));

                    if (! target) {
                        return;
                    }

                    event.preventDefault();
                    setActiveNavigationLink(link);
                    animateScrollTo(target);

                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', '#' + target.id);
                    }
                });

                var navigationLinks = Array.prototype.slice.call(
                    navigation.querySelectorAll('a[data-wpacu-overview-nav-target]')
                );
                var navigationSections = [];
                var scrollUpdateQueued = false;

                function setActiveNavigationLink(activeLink) {
                    navigationLinks.forEach(function(link) {
                        var isActive = link === activeLink;
                        link.classList.toggle('is-active', isActive);

                        if (isActive) {
                            link.setAttribute('aria-current', 'location');
                        } else {
                            link.removeAttribute('aria-current');
                        }
                    });
                }

                function updateActiveNavigationLink() {
                    scrollUpdateQueued = false;

                    if (! navigationSections.length) {
                        return;
                    }

                    var activationLine = navigation.classList.contains('wpacu-overview-navigation-sticky')
                        ? navigation.getBoundingClientRect().bottom + 64
                        : 120;
                    var activeItem = null;

                    navigationSections.forEach(function(item) {
                        if (item.section.getBoundingClientRect().top <= activationLine) {
                            activeItem = item;
                        }
                    });

                    if (activeItem) {
                        setActiveNavigationLink(activeItem.link);
                    }
                }

                function queueActiveNavigationUpdate() {
                    if (scrollUpdateQueued) {
                        return;
                    }

                    scrollUpdateQueued = true;
                    window.requestAnimationFrame(updateActiveNavigationLink);
                }

                function initializeSectionTracking() {
                    navigationLinks = Array.prototype.slice.call(navigation.querySelectorAll('a[data-wpacu-overview-nav-target]'));
                    navigationSections = navigationLinks.map(function(link) {
                        return {
                            link: link,
                            section: document.getElementById(link.getAttribute('data-wpacu-overview-nav-target'))
                        };
                    }).filter(function(item) {
                        return item.section && item.section.getClientRects().length > 0;
                    });

                    updateActiveNavigationLink();
                    window.addEventListener('scroll', queueActiveNavigationUpdate, {passive: true});
                    window.addEventListener('resize', queueActiveNavigationUpdate);
                }

                document.addEventListener('wpacu:overview-view-change', initializeSectionTracking);
                document.addEventListener('wpacu:overview-filter-change', initializeSectionTracking);

                function alignUrlAnchor() {
                    var targetId;
                    try { targetId = decodeURIComponent(window.location.hash.slice(1)); }
                    catch (e) { return; }
                    if (targetId.indexOf('wpacu-overview-') !== 0) { return; }
                    // Let native fragment scrolling and the final navigation layout settle first.
                    window.requestAnimationFrame(function() {
                        window.requestAnimationFrame(function() {
                            var target = document.getElementById(targetId);
                            if (target && target.getClientRects().length) {
                                animateScrollTo(target, true);
                                queueActiveNavigationUpdate();
                            }
                        });
                    });
                }

                if (document.readyState === 'complete') { alignUrlAnchor(); }
                else { window.addEventListener('load', alignUrlAnchor, {once: true}); }
                window.addEventListener('hashchange', alignUrlAnchor);

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initializeSectionTracking);
                } else {
                    initializeSectionTracking();
                }

                document.addEventListener('click', function(event) {
                    var backLink = event.target.closest('.wpacu-overview-back-to-navigation');

                    if (! backLink) {
                        return;
                    }

                    var overviewStart = document.getElementById('wpacu-overview-start');

                    if (! overviewStart) {
                        return;
                    }

                    event.preventDefault();
                    animateScrollTo(overviewStart);

                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', '#wpacu-overview-start');
                    }
                });
        })();
        </script>

        <?php
        echo '<div id="wpacu-overview-display">';
        include_once __DIR__ .  '/_admin-page-overview-areas/_styles.php';
        include_once __DIR__ .  '/_admin-page-overview-areas/_critical-css.php';

        include_once __DIR__ .  '/_admin-page-overview-areas/_scripts.php';

        include_once __DIR__ .  '/_admin-page-overview-areas/_plugins-manager.php';

        include_once __DIR__ .  '/_admin-page-overview-areas/_page-options.php';

        include_once __DIR__ .  '/_admin-page-overview-areas/_special-settings.php';
        echo '</div>';
        echo '<template id="wpacu-overview-pages-template">';
        \WpAssetCleanUp\Admin\OverviewByPage::render($data);
        echo '</template>';
        ?>

        <?php if ($isEditMode) { ?>
            <div id="wpacu-sticky-bottom-bar">
                <div id="wpacu-selection-notice">You have not selected any rules for deletion</div>

                <div id="wpacu-sticky-buttom-bar-action-area">
                    <button type="button"
                            disabled="disabled"
                            id="wpacu-clear-all-rules-marked-for-deletion"
                            class="button button-link">
                        Clear all rules marked for deletion
                    </button>

                    <button id="wpacu-apply-changes"
                            type="submit"
                            name="wpacu_action_btn"
                            value="apply_changes"
                            form="wpacu-overview-edit-form"
                            class="button button-primary">
                        <span class="dashicons dashicons-update"></span> Apply Changes
                    </button>

                    <input type="hidden" name="wpacu-main-edit-form-submit" value="1" />

                    <span id="wpacu-apply-changes-loader" class="wpacu_hide">
                        <img width="20" height="20" src="<?php echo includes_url( 'images/spinner.gif' ); ?>" alt="<?php _e('Loading'); ?>..." />
                    </span>
                </div>
            </div>
        <?php } ?>

        <?php if ($isEditMode) { ?>
            </form>
        <?php } ?>
        <span class="wpacu-area-spinner-loader" aria-hidden="true"></span>
    </div>

    <script>
    document.getElementById('wpacu-overview-sub-wrap').classList.add('wpacu-area-spinner-not-ready', 'wpacu-spinner-position-visible-center');

    var wpacuSpinnerAreaElement = document.querySelector('.wpacu-area-spinner-not-ready');

    var wpacuSpinnerAreaStopCentering = wpacuCenterSpinnerInView(wpacuSpinnerAreaElement, {
        edgePadding: 8
    });

    document.addEventListener('DOMContentLoaded', function () {
        wpacuSpinnerAreaStopCentering();

        wpacuSpinnerAreaElement.classList.remove(
            'wpacu-area-spinner-not-ready',
            'wpacu-spinner-position-visible-center'
        );
    });
    </script>
</div>
