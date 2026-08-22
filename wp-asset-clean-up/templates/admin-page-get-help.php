<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}

include_once __DIR__ . '/_top-area.php';
?>

<main id="wpacu-get-help-page-wrap" class="wpacu-help-page wpacu-help-page-lite" aria-labelledby="wpacu-help-title">
    <svg class="wpacu-help-page__sprite" aria-hidden="true" focusable="false">
        <symbol id="wpacu-icon-arrow-right" viewBox="0 0 24 24">
            <path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-book" viewBox="0 0 24 24">
            <path d="M4.5 5.5A2.5 2.5 0 0 1 7 3h5v17H7a2.5 2.5 0 0 0-2.5 2V5.5Zm15 0A2.5 2.5 0 0 0 17 3h-5v17h5a2.5 2.5 0 0 1 2.5 2V5.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-wrench" viewBox="0 0 24 24">
            <path d="M14.7 6.3a4.6 4.6 0 0 0-5.8 5.8L3.7 17.3a2.1 2.1 0 1 0 3 3l5.2-5.2a4.6 4.6 0 0 0 5.8-5.8l-2.8 2.8-3-3 2.8-2.8Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-bug" viewBox="0 0 24 24">
            <path d="M8 9V7a4 4 0 0 1 8 0v2m-9 2h10v5a5 5 0 0 1-10 0v-5Zm-3 1h3m10 0h3M4.5 17H7m10 0h2.5M8 4 6.5 2.5M16 4l1.5-1.5M12 11v9" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-briefcase" viewBox="0 0 24 24">
            <path d="M4 7h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Zm4 0V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m-14 5h20M10 12v2h4v-2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-check" viewBox="0 0 24 24">
            <path d="m5 12 4 4L19 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-shield" viewBox="0 0 24 24">
            <path d="M12 2.7 20 6v5.4c0 5.1-3.2 8.2-8 10.1-4.8-1.9-8-5-8-10.1V6l8-3.3Zm-3.5 9 2.2 2.2 4.8-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-compass" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"/>
            <path d="m15.5 8.5-2.1 4.9-4.9 2.1 2.1-4.9 4.9-2.1Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-info" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"/>
            <path d="M12 10v6m0-9.3v.1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </symbol>
        <symbol id="wpacu-icon-file" viewBox="0 0 24 24">
            <path d="M6 2.5h8l4 4V21H6V2.5Zm8 0v4h4M9 12h6m-6 4h6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-life-buoy" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"/>
            <circle cx="12" cy="12" r="3.5" fill="none" stroke="currentColor" stroke-width="1.7"/>
            <path d="m5.6 5.6 3.9 3.9m5 5 3.9 3.9m0-12.8-3.9 3.9m-5 5-3.9 3.9" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
        </symbol>
        <symbol id="wpacu-icon-external" viewBox="0 0 24 24">
            <path d="M14 4h6v6m0-6-9 9M19 13v7H4V5h7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-warning" viewBox="0 0 24 24">
            <path d="M12 3 2.7 20h18.6L12 3Zm0 6v5m0 3v.1" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-spark" viewBox="0 0 24 24">
            <path d="m12 2 1.5 5.5L19 9l-5.5 1.5L12 16l-1.5-5.5L5 9l5.5-1.5L12 2Zm7 12 .8 2.7 2.7.8-2.7.8L19 21l-.8-2.7-2.7-.8 2.7-.8L19 14Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        </symbol>
        <symbol id="wpacu-icon-ticket" viewBox="0 0 24 24">
            <path d="M4 5h16v4a3 3 0 0 0 0 6v4H4v-4a3 3 0 0 0 0-6V5Zm8 3v1m0 2v2m0 2v1" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
    </svg>

    <header class="wpacu-help-hero">
        <div class="wpacu-help-hero__copy">
            <div class="wpacu-help-eyebrow">Help center · no jargon required</div>
            <h1 id="wpacu-help-title">What would you like help with?</h1>
            <p class="wpacu-help-hero__intro">
                You do not need to be a developer or know every technical term. Choose the closest match below and we’ll point you to the safest next step.
            </p>

            <div class="wpacu-help-actions">
                <a class="wpacu-help-button wpacu-help-button--primary" href="#plain-english">
                    <svg class="wpacu-help-button__icon" aria-hidden="true"><use href="#wpacu-icon-book"/></svg>
                    I’m new — show me the basics
                </a>
                <a class="wpacu-help-button wpacu-help-button--secondary" href="#choose-a-path" data-find-path>
                    <svg class="wpacu-help-button__icon" aria-hidden="true"><use href="#wpacu-icon-compass"/></svg>
                    Help me choose
                </a>
            </div>

            <ul class="wpacu-help-quick-links" aria-label="Quick help links">
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_getting_started')); ?>"><svg aria-hidden="true"><use href="#wpacu-icon-book"/></svg>Getting Started</a></li>
                <li><a href="#quick-answers"><svg aria-hidden="true"><use href="#wpacu-icon-file"/></svg>Common questions</a></li>
                <li><a href="#support-options"><svg aria-hidden="true"><use href="#wpacu-icon-life-buoy"/></svg>Product support</a></li>
                <li><a href="#expert-help"><svg aria-hidden="true"><use href="#wpacu-icon-briefcase"/></svg>Hands-on help</a></li>
            </ul>
        </div>

        <aside class="wpacu-help-hero__aside" aria-label="Safe testing guidance">
            <div class="wpacu-help-hero__aside-title">
                <svg aria-hidden="true"><use href="#wpacu-icon-shield"/></svg>
                A safer way to begin
            </div>
            <p>
                Turn on <strong>Test Mode</strong> before changing anything. Your visitors keep seeing the normal site while you check your optimization changes as an administrator.
            </p>
            <ul class="wpacu-help-hero__aside-list">
                <li><svg aria-hidden="true"><use href="#wpacu-icon-check"/></svg><span>Make one small change at a time.</span></li>
                <li><svg aria-hidden="true"><use href="#wpacu-icon-check"/></svg><span>Check the page on desktop and mobile.</span></li>
                <li><svg aria-hidden="true"><use href="#wpacu-icon-check"/></svg><span>If something looks wrong, undo the latest rule.</span></li>
            </ul>
        </aside>
    </header>

    <section class="wpacu-help-section" id="choose-a-path" aria-labelledby="wpacu-choose-title">
        <div class="wpacu-help-section__heading">
            <div class="wpacu-help-eyebrow">Find the right route</div>
            <h2 id="wpacu-choose-title">Choose the closest match</h2>
            <p>It is fine if you are not completely sure. Pick the option that sounds most like your situation.</p>
        </div>

        <div class="wpacu-help-route-grid">
            <a class="wpacu-help-route-card" href="#plain-english" data-help-route="learn">
                <span class="wpacu-help-route-card__icon" aria-hidden="true"><svg><use href="#wpacu-icon-book"/></svg></span>
                <h3>I’m new to Asset CleanUp</h3>
                <p>I want to understand what an “asset” is, how unloading works, and how to test changes without risking the live site.</p>
                <span class="wpacu-help-route-card__footer">Best first step <svg aria-hidden="true"><use href="#wpacu-icon-arrow-right"/></svg></span>
            </a>

            <a class="wpacu-help-route-card" href="#quick-answers" data-help-route="troubleshoot">
                <span class="wpacu-help-route-card__icon" aria-hidden="true"><svg><use href="#wpacu-icon-wrench"/></svg></span>
                <h3>A page looks wrong or nothing changed</h3>
                <p>Something broke after a change, or the CSS/JavaScript file still appears to load.</p>
                <span class="wpacu-help-route-card__footer">Troubleshooting <svg aria-hidden="true"><use href="#wpacu-icon-arrow-right"/></svg></span>
            </a>

            <a class="wpacu-help-route-card" href="#support-options" data-help-route="support">
                <span class="wpacu-help-route-card__icon" aria-hidden="true"><svg><use href="#wpacu-icon-bug"/></svg></span>
                <h3>I think I found a plugin problem</h3>
                <p>Asset CleanUp may be causing an error, not following a saved rule, or conflicting with another plugin or theme.</p>
                <span class="wpacu-help-route-card__footer">Product support <svg aria-hidden="true"><use href="#wpacu-icon-arrow-right"/></svg></span>
            </a>

            <a class="wpacu-help-route-card" href="#expert-help" data-help-route="expert">
                <span class="wpacu-help-badge"><svg aria-hidden="true"><use href="#wpacu-icon-spark"/></svg>Optional paid service</span>
                <span class="wpacu-help-route-card__icon" aria-hidden="true"><svg><use href="#wpacu-icon-briefcase"/></svg></span>
                <h3>I’d rather have an expert do it</h3>
                <p>I want someone to inspect my site, configure the rules and test the important pages for me.</p>
                <span class="wpacu-help-route-card__footer">Hands-on help <svg aria-hidden="true"><use href="#wpacu-icon-arrow-right"/></svg></span>
            </a>
        </div>

        <div class="wpacu-help-recommendation" data-recommendation hidden aria-live="polite">
            <div class="wpacu-help-recommendation__icon" aria-hidden="true"><svg><use href="#wpacu-icon-compass"/></svg></div>
            <div>
                <div class="wpacu-help-recommendation__label">Recommended next step</div>
                <h3 data-recommendation-title></h3>
                <p data-recommendation-description></p>
                <ol data-recommendation-steps></ol>
            </div>
            <a class="wpacu-help-button wpacu-help-button--primary wpacu-help-recommendation__action" href="#plain-english" data-recommendation-action>Continue</a>
        </div>

        <noscript><div class="wpacu-help-noscript">JavaScript is disabled, but every option above still links directly to the relevant section.</div></noscript>
    </section>

    <section class="wpacu-help-section" id="plain-english" aria-labelledby="wpacu-basics-title">
        <div class="wpacu-help-plain-grid">
            <article class="wpacu-help-panel wpacu-help-panel--padded">
                <div class="wpacu-help-eyebrow">Plain-English basics</div>
                <h2 id="wpacu-basics-title">Three words that make the rest easier</h2>
                <p>You only need a basic idea of these terms. You do not need to learn how to write code.</p>

                <div class="wpacu-help-glossary">
                    <div class="wpacu-help-term">
                        <div class="wpacu-help-term__name">Asset</div>
                        <p>A file a page loads. A CSS file usually controls appearance; a JavaScript file usually adds behaviour.</p>
                    </div>
                    <div class="wpacu-help-term">
                        <div class="wpacu-help-term__name">Unload</div>
                        <p>Tell WordPress not to load a file on a page where that file is not needed.</p>
                    </div>
                    <div class="wpacu-help-term">
                        <div class="wpacu-help-term__name">Plugin</div>
                        <p>An add-on that gives WordPress extra features, such as a form, shop, gallery or page builder.</p>
                    </div>
                </div>

                <div class="wpacu-help-safety-note">
                    <svg aria-hidden="true"><use href="#wpacu-icon-warning"/></svg>
                    <div><strong>Do not unload a file only because its name looks unfamiliar.</strong> Start with files from a plugin that clearly is not used on the page you are testing.</div>
                </div>
            </article>

            <article class="wpacu-help-steps">
                <div class="wpacu-help-eyebrow">Begin safely</div>
                <h2>A simple four-step workflow</h2>
                <p>This keeps the process predictable and makes mistakes easy to reverse.</p>

                <ol class="wpacu-help-steps__list">
                    <li>
                        <div><strong>Turn on Test Mode</strong><p>Only you see the optimization changes while logged in. Visitors continue to see the normal site.</p></div>
                    </li>
                    <li>
                        <div><strong>Start with one page</strong><p>Choose a simple page you understand, rather than changing rules across the whole site.</p></div>
                    </li>
                    <li>
                        <div><strong>Unload one clear candidate</strong><p>For example, form files on a page that does not contain a form. Save, then test before doing more.</p></div>
                    </li>
                    <li>
                        <div><strong>Check and clear caches</strong><p>Test menus, forms and layout on desktop and mobile. Clear page cache or CDN cache before judging the result.</p></div>
                    </li>
                </ol>
            </article>
        </div>
    </section>

    <section class="wpacu-help-section" id="quick-answers" aria-labelledby="wpacu-faq-title">
        <div class="wpacu-help-section__heading">
            <div class="wpacu-help-eyebrow">Quick troubleshooting</div>
            <h2 id="wpacu-faq-title">Answers to the most common questions</h2>
            <p>Open the question that is closest to what you see on your site.</p>
        </div>

        <div class="wpacu-help-faq" data-accordion-group>
            <details>
                <summary>What should I try unloading first?</summary>
                <div class="wpacu-help-faq__answer">
                    <p>Start with files from a plugin that is clearly not used on that page. A common example is a contact-form plugin loading its files on a page with no form. Change one item at a time and test after every change.</p>
                </div>
            </details>

            <details>
                <summary>I saved a rule, but nothing changed. Why?</summary>
                <div class="wpacu-help-faq__answer">
                    <p>A cached copy of the page may still be displayed. Clear the cache from your caching plugin, hosting panel and CDN, then check the page in a private browser window.</p>
                </div>
            </details>

            <details>
                <summary>The page looks broken after I unloaded a file. What now?</summary>
                <div class="wpacu-help-faq__answer">
                    <p>Load the most recently unloaded file again, save the rule and clear the caches. If you used Test Mode, normal visitors should not have seen the broken version.</p>
                </div>
            </details>

            <details>
                <summary>Can Asset CleanUp fix every speed problem?</summary>
                <div class="wpacu-help-faq__answer">
                    <p>No single plugin can fix every bottleneck. Hosting, images, page cache, the active theme, database work and third-party scripts can also affect loading speed.</p>
                </div>
            </details>

            <details>
                <summary>Do I need to understand CSS or JavaScript?</summary>
                <div class="wpacu-help-faq__answer">
                    <p>No. Basic recognition is enough for many safe rules. You should know which plugin provides a feature and whether that feature is present on the page you are testing.</p>
                </div>
            </details>

            <details>
                <summary>Do I need to hire an expert to use Asset CleanUp?</summary>
                <div class="wpacu-help-faq__answer">
                    <p>No. Expert help is optional. It is useful when the site is complex, you are short on time, or you want someone else to perform and test the configuration.</p>
                </div>
            </details>
        </div>
    </section>

    <section class="wpacu-help-section" id="support-options" aria-labelledby="wpacu-support-title">
        <div class="wpacu-help-section__heading">
            <div class="wpacu-help-eyebrow">Two different kinds of help</div>
            <h2 id="wpacu-support-title">Product problem or hands-on optimization?</h2>
            <p>Choosing the right route helps you get a useful answer faster.</p>
        </div>

        <div class="wpacu-help-support-grid">
            <article class="wpacu-help-support-card">
                <div class="wpacu-help-support-card__label"><svg aria-hidden="true"><use href="#wpacu-icon-ticket"/></svg>Product support</div>
                <h2>Report a bug or compatibility issue</h2>
                <p>Use product support when Asset CleanUp itself appears to behave incorrectly.</p>

                <ul class="wpacu-help-check-list">
                    <li><svg aria-hidden="true"><use href="#wpacu-icon-check"/></svg><span>A JavaScript or PHP error appears to be generated by Asset CleanUp.</span></li>
                    <li><svg aria-hidden="true"><use href="#wpacu-icon-check"/></svg><span>A saved rule does not work as documented.</span></li>
                    <li><svg aria-hidden="true"><use href="#wpacu-icon-check"/></svg><span>There appears to be a conflict with a theme or another plugin.</span></li>
                </ul>

                <div class="wpacu-help-boundary">
                    Product support covers bugs and compatibility issues. It does not include a full audit of your website or deciding every optimization rule for you.
                </div>

                <div class="wpacu-help-support-card__footer">
                    <a class="wpacu-help-button wpacu-help-button--secondary" href="https://wordpress.org/support/plugin/wp-asset-clean-up/">
                        <svg class="wpacu-help-button__icon" aria-hidden="true"><use href="#wpacu-icon-life-buoy"/></svg>
                        Open a support ticket
                    </a>
                    <p class="wpacu-help-card-note">Include the affected page URL, what you expected, what happened and any visible error message.</p>
                </div>
            </article>

            <article class="wpacu-help-support-card wpacu-help-support-card--expert" id="expert-help">
                <div class="wpacu-help-support-card__label wpacu-help-support-card__label--paid"><svg aria-hidden="true"><use href="#wpacu-icon-spark"/></svg>Optional paid service</div>
                <h2>Prefer someone to configure and test it for you?</h2>
                <p>A vetted WordPress expert from Codeable can provide hands-on help for a site-specific optimization project.</p>

                <ul class="wpacu-help-check-list">
                    <li><svg aria-hidden="true"><use href="#wpacu-icon-check"/></svg><span>Inspect the files loaded on your most important pages.</span></li>
                    <li><svg aria-hidden="true"><use href="#wpacu-icon-check"/></svg><span>Configure Asset CleanUp rules carefully and test site functionality.</span></li>
                    <li><svg aria-hidden="true"><use href="#wpacu-icon-check"/></svg><span>Identify performance bottlenecks that are outside Asset CleanUp.</span></li>
                    <li><svg aria-hidden="true"><use href="#wpacu-icon-check"/></svg><span>Focus on practical improvements rather than promising an artificial 100/100 score.</span></li>
                </ul>

                <div class="wpacu-help-confidence">
                    <strong>You do not need to hire an expert in order to use Asset CleanUp.</strong>
                    <span>This route is for people who prefer to save time or have a more complex website. You review the project details and estimate before deciding.</span>
                </div>

                <div class="wpacu-help-support-card__footer">
                    <a class="wpacu-help-button wpacu-help-button--primary" href="https://www.gabelivan.com/visit/codeable/apply/from-lite/" target="_blank" rel="noopener noreferrer">
                        <svg class="wpacu-help-button__icon" aria-hidden="true"><use href="#wpacu-icon-external"/></svg>
                        Request an estimate on Codeable
                    </a>
                    <p class="wpacu-help-card-note">Opens an external website. The service is separate from Asset CleanUp product support.</p>
                </div>
            </article>
        </div>
    </section>

    <section class="wpacu-help-section">
        <div class="wpacu-help-resource-band">
            <div>
                <h2>Still not sure where to begin?</h2>
                <p>Start with the plain-English basics and Test Mode. That is the safest route for a new user.</p>
            </div>
            <div class="wpacu-help-actions">
                <a class="wpacu-help-button wpacu-help-button--primary wpacu-help-button--small" href="#plain-english">Start with the basics</a>
                <a class="wpacu-help-button wpacu-help-button--secondary wpacu-help-button--small" href="https://www.assetcleanup.com/docs/">Browse documentation</a>
            </div>
        </div>
    </section>
</main>

<script>
(function () {
    'use strict';

    var root = document.getElementById('wpacu-get-help-page-wrap');

    if (!root) {
        return;
    }

    var routeData = {
        learn: {
            title: 'Start with the safe, beginner-friendly setup',
            description: 'Learn the few terms you need, turn on Test Mode, and try one small change on one page.',
            steps: [
                'Read the plain-English basics',
                'Enable Test Mode',
                'Test one rule at a time'
            ],
            action: 'Show me the basics',
            href: '#plain-english'
        },
        troubleshoot: {
            title: 'Undo the latest change, then rule out caching',
            description: 'Most problems are solved by loading the last file again and clearing every cache before testing.',
            steps: [
                'Reverse the latest unload rule',
                'Clear page, plugin and CDN caches',
                'Check again in a private browser window'
            ],
            action: 'Open quick troubleshooting',
            href: '#quick-answers'
        },
        support: {
            title: 'Check whether this is a product bug or incompatibility',
            description: 'Reproduce the problem, note the exact page and error, then send a focused support report.',
            steps: [
                'Confirm the problem is repeatable',
                'Save the URL and error details',
                'Open a product support ticket'
            ],
            action: 'See product support',
            href: '#support-options'
        },
        expert: {
            title: 'Use hands-on help when you want the work done for you',
            description: 'A vetted WordPress expert can inspect important pages, configure rules and test the result with you.',
            steps: [
                'Describe your site and goals',
                'Answer a few project questions',
                'Review the estimate before deciding'
            ],
            action: 'See expert help',
            href: '#expert-help'
        }
    };

    var routeGrid = root.querySelector('.wpacu-help-route-grid');
    var routeCards = Array.prototype.slice.call(root.querySelectorAll('[data-help-route]'));
    var recommendation = root.querySelector('[data-recommendation]');
    var recommendationTitle = root.querySelector('[data-recommendation-title]');
    var recommendationDescription = root.querySelector('[data-recommendation-description]');
    var recommendationSteps = root.querySelector('[data-recommendation-steps]');
    var recommendationAction = root.querySelector('[data-recommendation-action]');

    if (routeGrid) {
        routeGrid.setAttribute('role', 'radiogroup');
        routeGrid.setAttribute('aria-labelledby', 'wpacu-choose-title');
    }

    routeCards.forEach(function (card, index) {
        card.setAttribute('role', 'radio');
        card.setAttribute('aria-checked', 'false');
        card.setAttribute('tabindex', index === 0 ? '0' : '-1');
    });

    function scrollRecommendationIntoView() {
        var recommendationRect = recommendation.getBoundingClientRect();
        var pageOffset = window.pageYOffset || document.documentElement.scrollTop || 0;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        var targetScroll = Math.max(0, pageOffset + recommendationRect.bottom - viewportHeight + 20);

        window.scrollTo({
            top: targetScroll,
            behavior: prefersReducedMotion() ? 'auto' : 'smooth'
        });
    }

    function selectRoute(routeName, shouldScroll) {
        var data = routeData[routeName];

        if (!data || !recommendation) {
            return;
        }

        routeCards.forEach(function (card) {
            var isSelected = card.getAttribute('data-help-route') === routeName;
            card.classList.toggle('is-selected', isSelected);

            if (isSelected) {
                card.setAttribute('aria-current', 'step');
                card.setAttribute('aria-checked', 'true');
                card.setAttribute('tabindex', '0');
            } else {
                card.removeAttribute('aria-current');
                card.setAttribute('aria-checked', 'false');
                card.setAttribute('tabindex', '-1');
            }
        });

        recommendationTitle.textContent = data.title;
        recommendationDescription.textContent = data.description;
        recommendationSteps.innerHTML = '';

        data.steps.forEach(function (step) {
            var item = document.createElement('li');
            item.textContent = step;
            recommendationSteps.appendChild(item);
        });

        recommendationAction.textContent = data.action;
        recommendationAction.setAttribute('href', data.href);
        recommendation.setAttribute('data-selected-route', routeName);
        recommendation.hidden = false;

        if (shouldScroll) {
            window.requestAnimationFrame(function () {
                scrollRecommendationIntoView();
            });
        }
    }

    routeCards.forEach(function (card) {
        card.addEventListener('click', function (event) {
            var routeName = card.getAttribute('data-help-route');

            if (!routeData[routeName]) {
                return;
            }

            event.preventDefault();
            selectRoute(routeName, true);
        });

        card.addEventListener('keydown', function (event) {
            var currentIndex = routeCards.indexOf(card);
            var nextIndex = currentIndex;

            if (event.key === ' ' || event.key === 'Spacebar') {
                event.preventDefault();
                selectRoute(card.getAttribute('data-help-route'), true);
                return;
            }

            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                nextIndex = currentIndex > 0 ? currentIndex - 1 : routeCards.length - 1;
            } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                nextIndex = currentIndex < routeCards.length - 1 ? currentIndex + 1 : 0;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = routeCards.length - 1;
            } else {
                return;
            }

            event.preventDefault();
            routeCards[nextIndex].focus();
            selectRoute(routeCards[nextIndex].getAttribute('data-help-route'), false);
        });
    });

    selectRoute('learn', false);

    var findPathButton = root.querySelector('[data-find-path]');

    if (findPathButton) {
        findPathButton.addEventListener('click', function (event) {
            event.preventDefault();
            var chooser = root.querySelector('#choose-a-path');

            if (chooser) {
                chooser.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }


    Array.prototype.slice.call(root.querySelectorAll('a[href^="#"]')).forEach(function (control) {
        control.addEventListener('click', function (event) {
            var targetId = control.getAttribute('href');

            if (!targetId || targetId === '#') {
                return;
            }

            var target = root.querySelector(targetId);

            if (!target) {
                return;
            }

            // Route cards have their own recommendation behaviour.
            if (control.hasAttribute('data-help-route') || control.hasAttribute('data-find-path')) {
                return;
            }

            event.preventDefault();
            target.scrollIntoView({
                behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                block: 'start'
            });

            if (window.history && typeof window.history.replaceState === 'function') {
                window.history.replaceState(null, '', targetId);
            }
        });
    });

    function SmoothDetails(details) {
        this.details = details;
        this.summary = details.querySelector('summary');
        this.content = details.querySelector('.wpacu-help-faq__answer');
        this.animation = null;
        this.isClosing = false;
        this.isExpanding = false;

        if (!this.summary || !this.content) {
            return;
        }

        this.summary.addEventListener('click', this.onClick.bind(this));
    }

    SmoothDetails.prototype.cancelAnimation = function () {
        if (!this.animation) {
            return;
        }

        this.animation.onfinish = null;
        this.animation.oncancel = null;
        this.animation.cancel();
        this.animation = null;
    };

    SmoothDetails.prototype.onClick = function (event) {
        event.preventDefault();

        if ((this.details.open && !this.isClosing) || this.isExpanding) {
            this.close();
        } else {
            this.open();
        }
    };

    SmoothDetails.prototype.open = function () {
        var self = this;

        accordionControllers.forEach(function (other) {
            if (other !== self && (other.details.open || other.isExpanding)) {
                other.close();
            }
        });

        if (prefersReducedMotion() || typeof this.details.animate !== 'function') {
            this.cancelAnimation();
            this.details.open = true;
            this.finish(true);
            return;
        }

        var startHeight = this.details.offsetHeight;

        this.cancelAnimation();
        this.isClosing = false;
        this.isExpanding = true;
        this.details.classList.remove('is-closing');
        this.details.classList.add('is-animating', 'is-expanding');
        this.details.style.height = startHeight + 'px';
        this.details.open = true;

        window.requestAnimationFrame(function () {
            var endHeight = self.summary.offsetHeight + self.content.offsetHeight;

            self.animation = self.details.animate(
                { height: [startHeight + 'px', endHeight + 'px'] },
                {
                    duration: 360,
                    easing: 'cubic-bezier(.22, 1, .36, 1)'
                }
            );

            self.animation.onfinish = function () {
                self.finish(true);
            };

            self.animation.oncancel = function () {
                self.isExpanding = false;
            };
        });
    };

    SmoothDetails.prototype.close = function () {
        var self = this;

        if (!this.details.open && !this.isExpanding) {
            return;
        }

        if (prefersReducedMotion() || typeof this.details.animate !== 'function') {
            this.cancelAnimation();
            this.details.open = false;
            this.finish(false);
            return;
        }

        var startHeight = this.details.offsetHeight;
        var endHeight = this.summary.offsetHeight;

        this.cancelAnimation();
        this.isClosing = true;
        this.isExpanding = false;
        this.details.classList.remove('is-expanding');
        this.details.classList.add('is-animating', 'is-closing');

        this.animation = this.details.animate(
            { height: [startHeight + 'px', endHeight + 'px'] },
            {
                duration: 300,
                easing: 'cubic-bezier(.4, 0, .2, 1)'
            }
        );

        this.animation.onfinish = function () {
            self.finish(false);
        };

        this.animation.oncancel = function () {
            self.isClosing = false;
        };
    };

    SmoothDetails.prototype.finish = function (isOpen) {
        this.details.open = isOpen;
        this.details.classList.remove('is-animating', 'is-expanding', 'is-closing');
        this.details.style.height = '';
        this.isClosing = false;
        this.isExpanding = false;
        this.animation = null;
    };

    var accordionControllers = Array.prototype.slice
        .call(root.querySelectorAll('[data-accordion-group] details'))
        .map(function (details) {
            return new SmoothDetails(details);
        });
})();
</script>
