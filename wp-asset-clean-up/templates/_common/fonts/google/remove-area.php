<?php

use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

if (! isset($data)) {
    exit;
}

$settingsName   = WPACU_PLUGIN_ID . '_settings';
$removalEnabled = ! empty($data['google_fonts_remove']);
$cacheRelPath   = str_replace(dirname(WP_CONTENT_DIR), '', WP_CONTENT_DIR) . OptimizeCommon::getRelPathPluginCacheDir();
?>
<div id="wpacuGoogleFontsRemove"
     class="wpacu-google-fonts-remove<?php echo $removalEnabled ? ' is-removal-enabled' : ''; ?>">
    <section class="wpacu-google-fonts-remove-intro" aria-labelledby="wpacuGoogleFontsRemoveIntroTitle">
        <div class="wpacu-google-fonts-remove-intro__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19 10.5 5h3L20 19"></path>
                <path d="M6.5 14h11"></path>
                <path d="m3 3 18 18"></path>
            </svg>
        </div>
        <div>
            <h3 id="wpacuGoogleFontsRemoveIntroTitle">
                <?php esc_html_e('Stop Google-hosted font delivery at the source', 'wp-asset-clean-up'); ?>
            </h3>
            <p>
                <?php esc_html_e('Use this when a privacy requirement or a complete local-font setup calls for no Google Fonts traffic. It is not a generic speed switch: without an equivalent local font, the browser will use the next fallback declared by the site.', 'wp-asset-clean-up'); ?>
            </p>
        </div>
    </section>

    <fieldset class="wpacu-google-fonts-remove-fieldset">
        <legend><?php esc_html_e('Removal status', 'wp-asset-clean-up'); ?></legend>

        <label class="wpacu-google-fonts-remove-choice" for="wpacu_google_fonts_remove">
            <input id="wpacu_google_fonts_remove"
                   type="checkbox"
                   name="<?php echo esc_attr($settingsName); ?>[google_fonts_remove]"
                   value="1"
                   aria-describedby="wpacuGoogleFontsRemoveDescription wpacuGoogleFontsIconWarning"
                <?php checked($removalEnabled); ?>>

            <span class="wpacu-google-fonts-remove-card">
                <span class="wpacu-google-fonts-remove-card__top">
                    <span class="wpacu-google-fonts-remove-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>
                            <path d="M8.5 12h7"></path>
                        </svg>
                    </span>

                    <span class="wpacu-google-fonts-remove-card__heading">
                        <span class="wpacu-google-fonts-remove-card__title">
                            <?php esc_html_e('Remove all Google Fonts requests', 'wp-asset-clean-up'); ?>
                            <span class="wpacu-google-fonts-remove-badge">
                                <?php esc_html_e('Most restrictive', 'wp-asset-clean-up'); ?>
                            </span>
                        </span>
                        <span class="wpacu-google-fonts-remove-card__subtitle">
                            <?php esc_html_e('Block stylesheets, font files, connection hints and recognized JavaScript loaders', 'wp-asset-clean-up'); ?>
                        </span>
                    </span>

                    <span class="wpacu-google-fonts-remove-switch" aria-hidden="true">
                        <span class="wpacu-google-fonts-remove-switch__track">
                            <span class="wpacu-google-fonts-remove-switch__text wpacu-google-fonts-remove-switch__text--kept">
                                <?php esc_html_e('Kept', 'wp-asset-clean-up'); ?>
                            </span>
                            <span class="wpacu-google-fonts-remove-switch__text wpacu-google-fonts-remove-switch__text--removed">
                                <?php esc_html_e('Removed', 'wp-asset-clean-up'); ?>
                            </span>
                            <span class="wpacu-google-fonts-remove-switch__thumb"></span>
                        </span>
                    </span>
                </span>

                <span id="wpacuGoogleFontsRemoveDescription" class="wpacu-google-fonts-remove-card__description">
                    <?php esc_html_e('After saving, Asset CleanUp removes matching references from the generated HTML and from cached copies of eligible local CSS and JavaScript. Original theme and plugin files are never edited.', 'wp-asset-clean-up'); ?>
                </span>

                <span class="wpacu-google-fonts-remove-effects" aria-hidden="true">
                    <span class="wpacu-google-fonts-remove-effect">
                        <span class="wpacu-google-fonts-remove-effect__label">fonts.googleapis.com</span>
                        <span class="wpacu-google-fonts-remove-effect__value">
                            <span class="is-when-kept"><?php esc_html_e('Allowed', 'wp-asset-clean-up'); ?></span>
                            <span class="is-when-removed"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </span>
                    <span class="wpacu-google-fonts-remove-effect">
                        <span class="wpacu-google-fonts-remove-effect__label">fonts.gstatic.com</span>
                        <span class="wpacu-google-fonts-remove-effect__value">
                            <span class="is-when-kept"><?php esc_html_e('Allowed', 'wp-asset-clean-up'); ?></span>
                            <span class="is-when-removed"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </span>
                    <span class="wpacu-google-fonts-remove-effect">
                        <span class="wpacu-google-fonts-remove-effect__label"><?php esc_html_e('Preconnect and DNS hints', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-google-fonts-remove-effect__value">
                            <span class="is-when-kept"><?php esc_html_e('Allowed', 'wp-asset-clean-up'); ?></span>
                            <span class="is-when-removed"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </span>
                    <span class="wpacu-google-fonts-remove-effect">
                        <span class="wpacu-google-fonts-remove-effect__label"><?php esc_html_e('Delivery preferences', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-google-fonts-remove-effect__value">
                            <span class="is-when-kept"><?php esc_html_e('Active', 'wp-asset-clean-up'); ?></span>
                            <span class="is-when-removed"><?php esc_html_e('Saved, inactive', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </span>
                </span>

                <span class="screen-reader-text" aria-live="polite">
                    <span class="is-when-kept"><?php esc_html_e('Google Fonts removal is disabled.', 'wp-asset-clean-up'); ?></span>
                    <span class="is-when-removed"><?php esc_html_e('Google Fonts removal is enabled.', 'wp-asset-clean-up'); ?></span>
                </span>
            </span>
        </label>
    </fieldset>

    <section class="wpacu-google-fonts-remove-guidance" aria-label="<?php esc_attr_e('When to use Google Fonts removal', 'wp-asset-clean-up'); ?>">
        <article class="wpacu-google-fonts-remove-guidance__card">
            <div class="wpacu-google-fonts-remove-guidance__icon" aria-hidden="true">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div>
                <h3><?php esc_html_e('A good fit when', 'wp-asset-clean-up'); ?></h3>
                <ul>
                    <li><?php esc_html_e('The required families and variants are already self-hosted.', 'wp-asset-clean-up'); ?></li>
                    <li><?php esc_html_e('The site intentionally uses system fonts or another font provider.', 'wp-asset-clean-up'); ?></li>
                    <li><?php esc_html_e('A privacy policy requires removing Google-hosted font traffic.', 'wp-asset-clean-up'); ?></li>
                </ul>
            </div>
        </article>

        <article class="wpacu-google-fonts-remove-guidance__card wpacu-google-fonts-remove-guidance__card--caution">
            <div class="wpacu-google-fonts-remove-guidance__icon" aria-hidden="true">
                <span class="dashicons dashicons-search"></span>
            </div>
            <div>
                <h3><?php esc_html_e('Check before enabling', 'wp-asset-clean-up'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Headings, body text and brand typography can fall back to a different face.', 'wp-asset-clean-up'); ?></li>
                    <li><?php esc_html_e('Icon fonts can become blank squares or text labels.', 'wp-asset-clean-up'); ?></li>
                    <li><?php esc_html_e('A dynamically assembled or obfuscated loader can require a manual cleanup.', 'wp-asset-clean-up'); ?></li>
                </ul>
            </div>
        </article>
    </section>

    <aside id="wpacuGoogleFontsIconWarning" class="wpacu-google-fonts-remove-icon-warning">
        <div class="wpacu-google-fonts-remove-icon-warning__icon" aria-hidden="true">
            <span class="dashicons dashicons-warning"></span>
        </div>
        <div>
            <strong><?php esc_html_e('Google-hosted icon fonts are removed too', 'wp-asset-clean-up'); ?></strong>
            <p>
                <?php esc_html_e('Material Icons, Material Symbols and similar sets delivered through Google Fonts are part of this option. Replace or self-host them first when the front end depends on those icons.', 'wp-asset-clean-up'); ?>
            </p>
        </div>
    </aside>

    <section class="wpacu-google-fonts-remove-scope" aria-labelledby="wpacuGoogleFontsRemoveScopeTitle">
        <div class="wpacu-google-fonts-remove-section-heading">
            <div>
                <span><?php esc_html_e('Removal coverage', 'wp-asset-clean-up'); ?></span>
                <h3 id="wpacuGoogleFontsRemoveScopeTitle"><?php esc_html_e('What gets removed', 'wp-asset-clean-up'); ?></h3>
            </div>
            <p><?php esc_html_e('The cleanup targets Google Fonts delivery references, not every CSS rule that mentions a font-family name.', 'wp-asset-clean-up'); ?></p>
        </div>

        <div class="wpacu-google-fonts-remove-scope__grid">
            <article class="wpacu-google-fonts-remove-scope__item">
                <span class="wpacu-google-fonts-remove-scope__icon" aria-hidden="true">
                    <span class="dashicons dashicons-admin-links"></span>
                </span>
                <h4><?php esc_html_e('HTML link elements', 'wp-asset-clean-up'); ?></h4>
                <p><?php esc_html_e('Stylesheets, style and font preloads, preconnect and DNS-prefetch hints that point to Google Fonts hosts.', 'wp-asset-clean-up'); ?></p>
            </article>

            <article class="wpacu-google-fonts-remove-scope__item">
                <span class="wpacu-google-fonts-remove-scope__icon" aria-hidden="true">
                    <span class="dashicons dashicons-editor-code"></span>
                </span>
                <h4><?php esc_html_e('CSS references', 'wp-asset-clean-up'); ?></h4>
                <p><?php esc_html_e('Google Fonts @import rules and matching @font-face declarations inside inline styles or eligible local stylesheets.', 'wp-asset-clean-up'); ?></p>
            </article>

            <article class="wpacu-google-fonts-remove-scope__item">
                <span class="wpacu-google-fonts-remove-scope__icon" aria-hidden="true">
                    <span class="dashicons dashicons-media-code"></span>
                </span>
                <h4><?php esc_html_e('Recognized JavaScript loaders', 'wp-asset-clean-up'); ?></h4>
                <p><?php esc_html_e('Direct Google stylesheet URLs plus common WebFontConfig and Web Font Loader patterns where they can be changed safely.', 'wp-asset-clean-up'); ?></p>
            </article>

            <article class="wpacu-google-fonts-remove-scope__item">
                <span class="wpacu-google-fonts-remove-scope__icon" aria-hidden="true">
                    <span class="dashicons dashicons-database-remove"></span>
                </span>
                <h4><?php esc_html_e('Cached optimized copies', 'wp-asset-clean-up'); ?></h4>
                <p><?php esc_html_e('When a local CSS or JavaScript file needs alteration, Asset CleanUp writes a cleaned copy to its cache and serves that copy instead.', 'wp-asset-clean-up'); ?></p>
            </article>
        </div>
    </section>

    <section class="wpacu-google-fonts-remove-workflow" aria-labelledby="wpacuGoogleFontsRemoveWorkflowTitle">
        <div class="wpacu-google-fonts-remove-section-heading">
            <div>
                <span><?php esc_html_e('Safe rollout', 'wp-asset-clean-up'); ?></span>
                <h3 id="wpacuGoogleFontsRemoveWorkflowTitle"><?php esc_html_e('Recommended verification', 'wp-asset-clean-up'); ?></h3>
            </div>
            <p><?php esc_html_e('Treat removal as a visual and functional change, not only as a request-count change.', 'wp-asset-clean-up'); ?></p>
        </div>

        <ol class="wpacu-google-fonts-remove-steps">
            <li>
                <span class="wpacu-google-fonts-remove-step__number">1</span>
                <div>
                    <strong><?php esc_html_e('Test privately first', 'wp-asset-clean-up'); ?></strong>
                    <p><?php esc_html_e('On a live site, enable and save Test Mode before changing this option.', 'wp-asset-clean-up'); ?></p>
                </div>
            </li>
            <li>
                <span class="wpacu-google-fonts-remove-step__number">2</span>
                <div>
                    <strong><?php esc_html_e('Inspect typography and icons', 'wp-asset-clean-up'); ?></strong>
                    <p><?php esc_html_e('Check menus, headings, forms, sliders, popups, icon buttons and responsive layouts.', 'wp-asset-clean-up'); ?></p>
                </div>
            </li>
            <li>
                <span class="wpacu-google-fonts-remove-step__number">3</span>
                <div>
                    <strong><?php esc_html_e('Publish, clear caches and verify', 'wp-asset-clean-up'); ?></strong>
                    <p><?php esc_html_e('Turn Test Mode off, clear page or CDN caches, then confirm the public Network panel has no unintended Google Fonts requests.', 'wp-asset-clean-up'); ?></p>
                </div>
            </li>
        </ol>
    </section>

    <details class="wpacu-google-fonts-remove-technical">
        <summary>
            <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
            <?php esc_html_e('Technical scope, preserved settings and known limits', 'wp-asset-clean-up'); ?>
        </summary>

        <div class="wpacu-google-fonts-remove-technical__body">
            <div class="wpacu-google-fonts-remove-technical__grid">
                <section>
                    <h4><?php esc_html_e('Preserved', 'wp-asset-clean-up'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Original theme and plugin files remain unchanged.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('Non-Google @import rules are left in place.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('Optimize Font Delivery preferences stay saved and become active again after removal is disabled.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('A LINK or STYLE element marked with data-wpacu-skip is intentionally left alone.', 'wp-asset-clean-up'); ?></li>
                    </ul>
                </section>

                <section>
                    <h4><?php esc_html_e('Known limits', 'wp-asset-clean-up'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Code assembled at runtime, encrypted or heavily obfuscated cannot always be removed by a universal pattern.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('The option does not install replacement fonts or rewrite font-family declarations.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('Third-party content inside cross-origin iframes is outside the page source controlled by WordPress.', 'wp-asset-clean-up'); ?></li>
                    </ul>
                </section>
            </div>

            <div class="wpacu-google-fonts-remove-technical__patterns" aria-label="<?php esc_attr_e('Examples of targeted references', 'wp-asset-clean-up'); ?>">
                <code>&lt;link href="https://fonts.googleapis.com/css2?..."&gt;</code>
                <code>&lt;link rel="preload" as="font" href="https://fonts.gstatic.com/..."&gt;</code>
                <code>@import url("https://fonts.googleapis.com/css2?...");</code>
                <code>WebFontConfig = { google: { families: [...] } };</code>
            </div>

            <p class="wpacu-google-fonts-remove-technical__cache">
                <span class="dashicons dashicons-open-folder" aria-hidden="true"></span>
                <?php
                esc_html_e('Generated cleaned files are stored under', 'wp-asset-clean-up');
                echo ' <code>' . esc_html($cacheRelPath) . '</code>. ';
                esc_html_e('The source files are not overwritten.', 'wp-asset-clean-up');
                ?>
            </p>
        </div>
    </details>
</div>
<script>
(function() {
    var root = document.getElementById('wpacuGoogleFontsRemove');

    if (! root) {
        return;
    }

    var input = root.querySelector('#wpacu_google_fonts_remove');
    var track = root.querySelector('.wpacu-google-fonts-remove-switch__track');
    var keptLabel = root.querySelector('.wpacu-google-fonts-remove-switch__text--kept');
    var removedLabel = root.querySelector('.wpacu-google-fonts-remove-switch__text--removed');

    if (! input || ! track || ! keptLabel || ! removedLabel) {
        return;
    }

    function getTargetWidth() {
        var label = input.checked ? removedLabel : keptLabel;
        var labelWidth = label.getBoundingClientRect().width;

        return labelWidth > 0 ? Math.ceil(labelWidth) + 47 : 0;
    }

    function updateWidth(immediate) {
        var targetWidth = getTargetWidth();

        // Keep the CSS fallback while this sub-tab is hidden. Hidden labels
        // have no measurable width and would otherwise collapse the switch.
        if (! targetWidth) {
            return;
        }

        if (immediate) {
            track.classList.add('is-measuring');
        }

        track.style.setProperty('--wpacu-gfr-switch-width', targetWidth + 'px');

        if (immediate) {
            track.offsetWidth;
            track.classList.remove('is-measuring');
        }
    }

    updateWidth(true);
    input.addEventListener('change', function() {
        updateWidth(false);
    });

    document.addEventListener('click', function(event) {
        var subTabInput = event.target;

        if (! subTabInput
            || ! subTabInput.matches
            || ! subTabInput.matches('input.wpacu-nav-input-sub-tab-area[value="wpacu-google-fonts-remove"]')) {
            return;
        }

        // The delegated sub-tab handler makes the panel visible during this
        // click. Measure on the next task, after its visible class is applied.
        window.setTimeout(function() {
            updateWidth(true);
        }, 0);
    });
})();
</script>
