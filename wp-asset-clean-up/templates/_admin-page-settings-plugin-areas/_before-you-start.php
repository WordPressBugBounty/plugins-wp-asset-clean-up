<?php
use WpAssetCleanUp\Settings;

/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}

$tabIdArea     = 'wpacu-setting-before-you-start';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea) ? 'style="display: table-cell;"' : '';
$wikiRead      = isset($data['wiki_read']) && ((int) $data['wiki_read'] === 1);
$inputStyle    = Settings::getInputStyle($data);
$useEnhancedInputs = Settings::useEnhancedInputs($inputStyle);
?>
<div id="<?php echo esc_attr($tabIdArea); ?>" class="wpacu-settings-tab-content <?php if ($selectedTabArea === $tabIdArea) { echo 'wpacu-area-shown'; } ?>" <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <main class="wpacu-settings-main" id="wpacu-before-you-start-area">
        <section class="wpacu-panel" aria-labelledby="pageTitle">
            <header class="wpacu-panel-header">
                <div>
                    <div class="wpacu-eyebrow">Recommended first step</div>
                    <h1 id="pageTitle">Optimize safely, one change at a time</h1>
                    <p>Review this short workflow before changing which CSS and JavaScript files load on the site.</p>
                </div>
            </header>

            <div class="wpacu-panel-body">
                <section class="wpacu-intro" aria-labelledby="introTitle">
                    <div class="wpacu-intro__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>
                            <path d="m9 12 2 2 4-4"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 id="introTitle">Unload only what the page does not need</h2>
                        <p>Asset CleanUp prevents selected stylesheets and scripts from loading on pages where they are unnecessary. This can reduce page weight and HTTP requests. Unloading a required file, however, may affect the page layout or functionality.</p>
                    </div>
                </section>

                <h2 class="wpacu-section-title">Recommended workflow</h2>
                <div class="wpacu-steps">
                    <article class="wpacu-step">
                        <div class="wpacu-step__number" aria-hidden="true">1</div>
                        <div>
                            <h3>Start with one familiar page</h3>
                            <p>Choose a page you know well, such as the homepage, and unload only assets you can confidently identify.</p>
                        </div>
                    </article>

                    <article class="wpacu-step">
                        <div class="wpacu-step__number" aria-hidden="true">2</div>
                        <div>
                            <h3>Use Test Mode</h3>
                            <p>Apply optimization rules only to your logged-in administrator session while visitors continue to receive the unchanged site.</p>
                        </div>
                    </article>

                    <article class="wpacu-step">
                        <div class="wpacu-step__number" aria-hidden="true">3</div>
                        <div>
                            <h3>Test the optimized page while logged in</h3>
                            <p>With Test Mode enabled, browse the page in your current administrator session and check menus, forms, sliders, popups, carts, and other interactive elements.</p>
                        </div>
                    </article>

                    <article class="wpacu-step">
                        <div class="wpacu-step__number" aria-hidden="true">4</div>
                        <div>
                            <h3>Make the changes public, then test again</h3>
                            <p>When the page works correctly, disable Test Mode and clear page, plugin, server, and CDN caches. Open a private or incognito browser window to verify the visitor-facing page, then run the final external performance test.</p>
                        </div>
                    </article>
                </div>

                <aside class="wpacu-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 16v-4"></path>
                        <path d="M12 8h.01"></path>
                    </svg>
                    <div>
                        <strong>Keep each optimization task in one place.</strong>
                        Asset CleanUp can handle asset unloading while a caching or optimization plugin handles page caching, minification, or file combination. Do not enable the same minification or combination feature in multiple plugins.
                    </div>
                </aside>

                <div class="wpacu-accordions" data-accordion-group>
                    <section class="wpacu-accordion">
                        <h2 style="margin: 0; padding: 0; border-bottom: 0;">
                            <button class="wpacu-accordion__button" type="button" aria-expanded="false" aria-controls="accordionCompatibility">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M20 7h-9"></path><path d="M14 17H5"></path><circle cx="17" cy="17" r="3"></circle><circle cx="7" cy="7" r="3"></circle>
                                </svg>
                                Using Asset CleanUp with caching and optimization plugins
                                <svg class="wpacu-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                            </button>
                        </h2>
                        <div class="wpacu-accordion__panel" id="accordionCompatibility" aria-hidden="true">
                            <div class="wpacu-accordion__content">
                                <p>A clean setup gives each plugin one clear responsibility. Asset CleanUp first prevents unnecessary files from loading; the remaining files can then be cached or optimized by the plugin responsible for those tasks.</p>
                                <div class="wpacu-responsibility-grid">
                                    <div class="wpacu-responsibility"><strong>Asset CleanUp</strong><span>Unload CSS and JavaScript where they are not required.</span></div>
                                    <div class="wpacu-responsibility"><strong>Caching plugin</strong><span>Create and serve the page cache.</span></div>
                                    <div class="wpacu-responsibility"><strong>One optimizer only</strong><span>Minify or combine the remaining files, when testing shows a benefit.</span></div>
                                </div>
                                <div class="wpacu-warning">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 2.9 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.9a2 2 0 0 0-3.4 0Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>
                                    <div>Enabling the same minification or file-combination feature in more than one plugin can create duplicated processing, conflicts, or larger output.</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="wpacu-accordion">
                        <h2 style="margin: 0; padding: 0; border-bottom: 0;">
                            <button class="wpacu-accordion__button" type="button" aria-expanded="false" aria-controls="accordionResults">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"></path><path d="m7 16 4-5 4 3 5-7"></path></svg>
                                What results should I expect?
                                <svg class="wpacu-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                            </button>
                        </h2>
                        <div class="wpacu-accordion__panel" id="accordionResults" aria-hidden="true">
                            <div class="wpacu-accordion__content">
                                <p>Removing unnecessary requests normally reduces transferred data and page-processing work. A specific loading-time improvement or performance score is not guaranteed because hosting, images, third-party scripts, caching, network conditions, and the testing service also affect the result.</p>
                                <p>Compare the page before and after the change, and keep a rule only when it provides a clear benefit without affecting the page.</p>
                            </div>
                        </div>
                    </section>

                    <section class="wpacu-accordion">
                        <h2 style="margin: 0; padding: 0; border-bottom: 0;">
                            <button class="wpacu-accordion__button" type="button" aria-expanded="false" aria-controls="accordionRisk">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4"></path><path d="M12 17h.01"></path><circle cx="12" cy="12" r="10"></circle></svg>
                                Can a change slow down or break a page?
                                <svg class="wpacu-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                            </button>
                        </h2>
                        <div class="wpacu-accordion__panel" id="accordionRisk" aria-hidden="true">
                            <div class="wpacu-accordion__content">
                                <p>A problem can occur when a required stylesheet or script is unloaded, or when multiple plugins process the same files. That is why changes should be made gradually and tested in Test Mode first.</p>
                                <p>Asset CleanUp does not edit or delete the original CSS and JavaScript files supplied by WordPress, themes, or plugins. Generated optimized files are stored separately in the cache.</p>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="wpacu-acknowledgement" aria-labelledby="acknowledgementTitle">
                    <div>
                        <span class="wpacu-acknowledgement__eyebrow">Safety acknowledgement</span>
                        <h2 id="acknowledgementTitle">I have reviewed these recommendations</h2>
                        <p id="wpacuReviewAcknowledgementDescription">I understand that unloading required CSS or JavaScript may affect the layout or functionality of a page. I will test my changes—using Test Mode when appropriate—before making them visible to visitors.</p>
                    </div>
                    <div class="wpacu-switch-row">
                        <?php if ($useEnhancedInputs) { ?>
                            <label class="wpacu-switch-control" for="wpacu_wiki_read">
                                <input class="wpacu-switch-input"
                                       id="wpacu_wiki_read"
                                       type="checkbox"
                                       aria-labelledby="acknowledgementTitle"
                                       aria-describedby="wpacuReviewAcknowledgementDescription"
                                       <?php checked($wikiRead); ?>
                                       name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[wiki_read]"
                                       value="1" />
                                <span class="wpacu-switch-visual" aria-hidden="true"></span>
                                <span class="wpacu-switch-label" id="wpacuReviewSwitchLabel" aria-hidden="true"><?php echo $wikiRead ? 'Reviewed' : 'Mark as reviewed'; ?></span>
                            </label>
                        <?php } else { ?>
                            <label class="wpacu-native-control-line wpacu-before-start-native-review" for="wpacu_wiki_read">
                                <input class="wpacu-native-control-input"
                                       id="wpacu_wiki_read"
                                       type="checkbox"
                                       aria-describedby="wpacuReviewAcknowledgementDescription"
                                       <?php checked($wikiRead); ?>
                                       name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[wiki_read]"
                                       value="1" />
                                <span id="wpacuReviewSwitchLabel"><?php echo $wikiRead ? 'Reviewed' : 'Mark as reviewed'; ?></span>
                            </label>
                        <?php } ?>
                    </div>
                </section>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    'use strict';

    function initBeforeYouStart() {
        var root = document.getElementById('wpacu-before-you-start-area');

        if (! root || root.getAttribute('data-wpacu-initialized') === '1') {
            return;
        }

        root.setAttribute('data-wpacu-initialized', '1');

        var accordionButtons = root.querySelectorAll('.wpacu-accordion__button');
        var i;

        function getPanel(button) {
            var panelId = button.getAttribute('aria-controls');
            return panelId ? document.getElementById(panelId) : null;
        }

        function setAccordionState(button, expanded, animate) {
            var panel = getPanel(button);

            if (! panel) {
                return;
            }

            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            panel.setAttribute('aria-hidden', expanded ? 'false' : 'true');

            if (! animate) {
                panel.style.transition = 'none';
                panel.style.height = expanded ? 'auto' : '0px';
                panel.offsetHeight;
                panel.style.transition = '';
                return;
            }

            if (expanded) {
                panel.style.height = '0px';
                panel.offsetHeight;
                panel.style.height = panel.scrollHeight + 'px';
            } else {
                panel.style.height = panel.scrollHeight + 'px';
                panel.offsetHeight;
                panel.style.height = '0px';
            }
        }

        for (i = 0; i < accordionButtons.length; i++) {
            (function (button) {
                var panel = getPanel(button);

                setAccordionState(button, button.getAttribute('aria-expanded') === 'true', false);

                button.addEventListener('click', function () {
                    var expanded = button.getAttribute('aria-expanded') === 'true';
                    setAccordionState(button, ! expanded, true);
                });

                if (panel) {
                    panel.addEventListener('transitionend', function (event) {
                        if (event.propertyName === 'height' && button.getAttribute('aria-expanded') === 'true') {
                            panel.style.height = 'auto';
                        }
                    });
                }
            }(accordionButtons[i]));
        }

        window.addEventListener('resize', function () {
            var expandedButtons = root.querySelectorAll('.wpacu-accordion__button[aria-expanded="true"]');
            var j;

            for (j = 0; j < expandedButtons.length; j++) {
                var panel = getPanel(expandedButtons[j]);

                if (panel) {
                    panel.style.height = 'auto';
                }
            }
        });

        const reviewSwitch = document.getElementById('wpacu_wiki_read');
        const switchLabel  = document.getElementById('wpacuReviewSwitchLabel');
        const sidebarBadge = document.getElementById('wpacuSidebarReviewBadge');
        const headerStatus = document.getElementById('wpacuHeaderReviewStatus');

        function renderReviewState() {
            var reviewed;

            if (! reviewSwitch) {
                return;
            }

            reviewed = reviewSwitch.checked;

            if (switchLabel) {
                switchLabel.textContent = reviewed ? 'Reviewed' : 'Mark as reviewed';
            }

            if (headerStatus) {
                headerStatus.textContent = reviewed ? 'Reviewed' : 'Not reviewed';

                if (reviewed) {
                    headerStatus.classList.add('wpacu-is-reviewed');
                } else {
                    headerStatus.classList.remove('wpacu-is-reviewed');
                }
            }

            if (sidebarBadge) {
                sidebarBadge.textContent = reviewed ? 'Reviewed' : 'Review';
                sidebarBadge.classList.toggle('wpacu-is-reviewed', reviewed);
            }
        }

        if (reviewSwitch) {
            reviewSwitch.addEventListener('change', renderReviewState);
            renderReviewState();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBeforeYouStart);
    } else {
        initBeforeYouStart();
    }
}());

var wpacuStopSpinner = wpacuShowAreaSpinner(
    '#wpacu-before-you-start-area',
    {
        position: 'center',
        edgePadding: 8
    }
);

document.addEventListener(
    'DOMContentLoaded',
    wpacuStopSpinner,
    {
        once: true
    }
);
</script>