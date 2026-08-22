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
    <div style="padding: 0 0 10px; line-height: 22px;">
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

        <nav id="wpacu-overview-navigation" class="wpacu-overview-navigation wpacu-overview-navigation-initializing" aria-label="<?php esc_attr_e('Overview sections', 'wp-asset-clean-up'); ?>">
            <div class="wpacu-overview-navigation-links">
                <?php foreach ($wpacuOverviewNavItems as $wpacuOverviewSectionId => $wpacuOverviewNavItem) { ?>
                    <a href="#<?php echo esc_attr($wpacuOverviewSectionId); ?>" data-wpacu-overview-nav-target="<?php echo esc_attr($wpacuOverviewSectionId); ?>">
                        <?php echo esc_html($wpacuOverviewNavItem['label']); ?>
                        <span class="wpacu-overview-navigation-count"><?php echo (int)$wpacuOverviewNavItem['count']; ?></span>
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
                    target.scrollIntoView({behavior: 'smooth', block: 'start'});

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
                    navigationSections = navigationLinks.map(function(link) {
                        return {
                            link: link,
                            section: document.getElementById(link.getAttribute('data-wpacu-overview-nav-target'))
                        };
                    }).filter(function(item) {
                        return item.section;
                    });

                    updateActiveNavigationLink();
                    window.addEventListener('scroll', queueActiveNavigationUpdate, {passive: true});
                    window.addEventListener('resize', queueActiveNavigationUpdate);
                }

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
                    overviewStart.scrollIntoView({behavior: 'smooth', block: 'start'});

                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', '#wpacu-overview-start');
                    }
                });
        })();
        </script>

        <?php
        include_once __DIR__ .  '/_admin-page-overview-areas/_styles.php';
        include_once __DIR__ .  '/_admin-page-overview-areas/_critical-css.php';

        include_once __DIR__ .  '/_admin-page-overview-areas/_scripts.php';

        include_once __DIR__ .  '/_admin-page-overview-areas/_plugins-manager.php';

        include_once __DIR__ .  '/_admin-page-overview-areas/_page-options.php';

        include_once __DIR__ .  '/_admin-page-overview-areas/_special-settings.php';
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
