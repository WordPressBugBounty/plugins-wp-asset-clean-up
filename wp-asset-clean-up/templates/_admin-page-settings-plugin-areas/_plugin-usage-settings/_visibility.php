<?php
if (! isset($data)) {
    exit;
}

$settingsName      = WPACU_PLUGIN_ID . '_settings';
$hideFromAdminBar = isset($data['hide_from_admin_bar']) && ((int) $data['hide_from_admin_bar'] === 1);
$hideFromSideBar  = isset($data['hide_from_side_bar']) && ((int) $data['hide_from_side_bar'] === 1);
?>

<div id="wpacu-menu-visibility-settings" class="wpacu-settings-modern-area">
    <header class="wpacu-menu-visibility-header">
        <div class="wpacu-eyebrow"><?php esc_html_e('Dashboard interface', 'wp-asset-clean-up'); ?></div>

        <h1><?php esc_html_e('Choose where the plugin appears in WordPress', 'wp-asset-clean-up'); ?></h1>

        <p class="wpacu-lead"><?php esc_html_e('Hide shortcuts you rarely use to keep the WordPress admin interface cleaner. These options affect navigation only — they do not disable Asset CleanUp or change any optimization settings.', 'wp-asset-clean-up'); ?></p>
    </header>

    <div class="wpacu-modern-note wpacu-modern-note-info">
        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
        <div>
            <strong><?php esc_html_e('Your settings and optimization rules stay active.', 'wp-asset-clean-up'); ?></strong>
            <?php esc_html_e('Hiding a menu entry only removes that shortcut from the WordPress admin interface. You can change these options again at any time.', 'wp-asset-clean-up'); ?>
        </div>
    </div>

    <h2 class="wpacu-section-title"><?php esc_html_e('Admin menu shortcuts', 'wp-asset-clean-up'); ?></h2>

    <div class="wpacu-menu-visibility-grid">
        <section id="wpacu-menu-visibility-toolbar-card"
                 class="wpacu-menu-visibility-card<?php echo $hideFromAdminBar ? ' is-hidden' : ''; ?>">
            <div class="wpacu-menu-visibility-card__header">
                <div>
                    <h2><?php esc_html_e('Admin toolbar', 'wp-asset-clean-up'); ?></h2>
                    <span class="wpacu-card-subtitle"><?php esc_html_e('Top toolbar shortcut', 'wp-asset-clean-up'); ?></span>
                </div>

                <label class="wpacu-menu-visibility-toggle"
                       data-visual-state="<?php echo $hideFromAdminBar ? 'hidden' : 'visible'; ?>"
                       for="wpacu_hide_from_admin_bar">
                    <input id="wpacu_hide_from_admin_bar"
                           type="checkbox"
                           aria-describedby="wpacu-menu-visibility-toolbar-description"
                           <?php checked($hideFromAdminBar); ?>
                           name="<?php echo esc_attr($settingsName); ?>[hide_from_admin_bar]"
                           value="1" />

                    <span class="wpacu-menu-visibility-toggle__track" aria-hidden="true">
                        <span class="wpacu-menu-visibility-toggle__text wpacu-menu-visibility-toggle__text--visible"><?php esc_html_e('Visible', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-menu-visibility-toggle__text wpacu-menu-visibility-toggle__text--hidden"><?php esc_html_e('Hidden', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-menu-visibility-toggle__thumb"></span>
                    </span>

                    <span class="wpacu-menu-visibility-toggle__label"><?php esc_html_e('Hide Asset CleanUp from the admin toolbar', 'wp-asset-clean-up'); ?></span>
                </label>
            </div>

            <p id="wpacu-menu-visibility-toolbar-description"><?php esc_html_e('Hide the Asset CleanUp shortcut from the toolbar at the top of WordPress Admin. Useful when the toolbar is crowded or you rarely open the plugin from there.', 'wp-asset-clean-up'); ?></p>

            <div class="wpacu-menu-visibility-card__meta">
                <strong><?php esc_html_e('What changes:', 'wp-asset-clean-up'); ?></strong>
                <?php esc_html_e('only the Asset CleanUp toolbar entry is removed.', 'wp-asset-clean-up'); ?>
            </div>
        </section>

        <section id="wpacu-menu-visibility-sidebar-card"
                 class="wpacu-menu-visibility-card<?php echo $hideFromSideBar ? ' is-hidden' : ''; ?>">
            <div class="wpacu-menu-visibility-card__header">
                <div>
                    <h2><?php esc_html_e('Dashboard sidebar', 'wp-asset-clean-up'); ?></h2>
                    <span class="wpacu-card-subtitle"><?php esc_html_e('Main admin menu', 'wp-asset-clean-up'); ?></span>
                </div>

                <label class="wpacu-menu-visibility-toggle"
                       data-visual-state="<?php echo $hideFromSideBar ? 'hidden' : 'visible'; ?>"
                       for="wpacu_hide_from_side_bar">
                    <input id="wpacu_hide_from_side_bar"
                           type="checkbox"
                           aria-describedby="wpacu-menu-visibility-sidebar-description"
                           <?php checked($hideFromSideBar); ?>
                           name="<?php echo esc_attr($settingsName); ?>[hide_from_side_bar]"
                           value="1" />

                    <span class="wpacu-menu-visibility-toggle__track" aria-hidden="true">
                        <span class="wpacu-menu-visibility-toggle__text wpacu-menu-visibility-toggle__text--visible"><?php esc_html_e('Visible', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-menu-visibility-toggle__text wpacu-menu-visibility-toggle__text--hidden"><?php esc_html_e('Hidden', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-menu-visibility-toggle__thumb"></span>
                    </span>

                    <span class="wpacu-menu-visibility-toggle__label"><?php esc_html_e('Hide Asset CleanUp from the Dashboard sidebar', 'wp-asset-clean-up'); ?></span>
                </label>
            </div>

            <p id="wpacu-menu-visibility-sidebar-description"><?php echo wp_kses_post(sprintf(
                __('Hide the dedicated Asset CleanUp entry from the left WordPress Dashboard menu. The plugin remains accessible from %s.', 'wp-asset-clean-up'),
                '<strong>Settings &rarr; ' . esc_html(WPACU_PLUGIN_TITLE) . '</strong>'
            )); ?></p>

            <div class="wpacu-menu-visibility-card__meta">
                <strong><?php esc_html_e('Access remains available:', 'wp-asset-clean-up'); ?></strong>
                <?php echo esc_html__('Settings', 'wp-asset-clean-up') . ' &rarr; ' . esc_html(WPACU_PLUGIN_TITLE); ?>.
            </div>
        </section>
    </div>

    <div class="wpacu-menu-visibility-footer-note">
        <strong><?php esc_html_e('If you hide both shortcuts:', 'wp-asset-clean-up'); ?></strong>
        <?php echo wp_kses_post(sprintf(
            __('Asset CleanUp remains enabled and its saved rules continue to work. Open it later from %s.', 'wp-asset-clean-up'),
            '<code>Settings &rarr; ' . esc_html(WPACU_PLUGIN_TITLE) . '</code>'
        )); ?>
    </div>
</div>

<script>
(function () {
    'use strict';

    /*
     * Set this global to false before this script runs (or change the
     * fallback below) to disable the fast fade and width animations.
     * Dynamic width calculation will still work, but changes are immediate.
     */
    if (typeof window.wpacuMenuVisibilityUseEffects !== 'boolean') {
        window.wpacuMenuVisibilityUseEffects = true;
    }

    var root = document.getElementById('wpacu-menu-visibility-settings');

    if (! root) {
        return;
    }

    var controls = [
        {
            inputId: 'wpacu_hide_from_admin_bar',
            cardId: 'wpacu-menu-visibility-toolbar-card'
        },
        {
            inputId: 'wpacu_hide_from_side_bar',
            cardId: 'wpacu-menu-visibility-sidebar-card'
        }
    ];
    var inputStyleRoot = root.closest('[data-wpacu-input-style]');
    var enhancedControls = ! inputStyleRoot
        || inputStyleRoot.getAttribute('data-wpacu-input-style') !== 'standard';

    function refreshNativeStates() {
        controls.forEach(function (control) {
            var input = document.getElementById(control.inputId);
            var card = document.getElementById(control.cardId);

            if (input && card) {
                card.classList.toggle('is-hidden', input.checked);
            }
        });
    }

    if (! enhancedControls) {
        controls.forEach(function (control) {
            var input = document.getElementById(control.inputId);

            if (input) {
                input.addEventListener('change', refreshNativeStates);
            }
        });

        refreshNativeStates();

        /* Keep the existing public integration points harmless in Native mode. */
        window.wpacuMenuVisibilityRefreshToggleWidths = refreshNativeStates;
        window.wpacuMenuVisibilitySetEffects = refreshNativeStates;
        return;
    }

    var instances = [];
    var measurementNode = null;
    var resizeTimer = null;
    var reduceMotionQuery = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;
    var effectsEnabled = true;

    // Deliberately short: fade out, resize/swap, then fade the new label in.
    var fadeOutDelay = 65;
    var fadeInDelay  = 18;

    function readPixelCustomProperty(name, fallback) {
        var value = window.getComputedStyle(root).getPropertyValue(name);
        var parsed = parseFloat(value);

        return isNaN(parsed) ? fallback : parsed;
    }

    function getMeasurementNode() {
        if (measurementNode) {
            return measurementNode;
        }

        measurementNode = document.createElement('span');
        measurementNode.setAttribute('aria-hidden', 'true');
        measurementNode.style.position = 'absolute';
        measurementNode.style.left = '-100000px';
        measurementNode.style.top = '-100000px';
        measurementNode.style.width = 'auto';
        measurementNode.style.maxWidth = 'none';
        measurementNode.style.margin = '0';
        measurementNode.style.padding = '0';
        measurementNode.style.border = '0';
        measurementNode.style.visibility = 'hidden';
        measurementNode.style.pointerEvents = 'none';
        measurementNode.style.whiteSpace = 'nowrap';
        measurementNode.style.boxSizing = 'content-box';
        document.body.appendChild(measurementNode);

        return measurementNode;
    }

    function measureTranslatedLabel(label) {
        var node = getMeasurementNode();
        var style = window.getComputedStyle(label);

        node.style.fontFamily = style.fontFamily;
        node.style.fontSize = style.fontSize;
        node.style.fontStyle = style.fontStyle;
        node.style.fontWeight = style.fontWeight;
        node.style.fontStretch = style.fontStretch;
        node.style.fontVariant = style.fontVariant;
        node.style.fontFeatureSettings = style.fontFeatureSettings;
        node.style.fontKerning = style.fontKerning;
        node.style.letterSpacing = style.letterSpacing;
        node.style.textTransform = style.textTransform;
        node.style.lineHeight = style.lineHeight;
        node.textContent = label.textContent || '';

        return Math.ceil(node.getBoundingClientRect().width);
    }

    function getCurrentState(input) {
        return input.checked ? 'hidden' : 'visible';
    }

    function getStateLabel(instance, state) {
        return state === 'hidden'
            ? instance.hiddenLabel
            : instance.visibleLabel;
    }

    function getTargetWidth(instance, state) {
        var minWidth = readPixelCustomProperty('--wpacu-menu-toggle-min-width', 110);
        var horizontalSpace = readPixelCustomProperty('--wpacu-menu-toggle-horizontal-space', 66);
        var labelWidth = measureTranslatedLabel(getStateLabel(instance, state));

        return Math.max(minWidth, labelWidth + horizontalSpace);
    }

    function setTrackWidth(instance, state, immediate) {
        var targetWidth = getTargetWidth(instance, state);

        if (immediate) {
            instance.track.classList.add('is-measuring');
        }

        instance.track.style.setProperty(
            '--wpacu-menu-toggle-current-width',
            targetWidth + 'px'
        );

        if (immediate) {
            // Apply the initial width without animating from the CSS fallback.
            instance.track.offsetWidth;
            instance.track.classList.remove('is-measuring');
        }
    }

    function clearTransition(instance) {
        window.clearTimeout(instance.fadeTimer);
        window.clearTimeout(instance.revealTimer);
        instance.fadeTimer = null;
        instance.revealTimer = null;
    }

    function applyImmediateState(instance) {
        var state = getCurrentState(instance.input);

        clearTransition(instance);
        instance.sequence += 1;
        instance.card.classList.toggle('is-hidden', state === 'hidden');
        instance.toggle.classList.remove('is-fading');
        instance.toggle.setAttribute('data-visual-state', state);
        setTrackWidth(instance, state, true);
    }

    function applyAnimatedState(instance) {
        var state = getCurrentState(instance.input);
        var sequence;

        instance.card.classList.toggle('is-hidden', state === 'hidden');

        if (! effectsEnabled) {
            applyImmediateState(instance);
            return;
        }

        clearTransition(instance);
        instance.sequence += 1;
        sequence = instance.sequence;
        instance.toggle.classList.add('is-fading');

        instance.fadeTimer = window.setTimeout(function () {
            if (sequence !== instance.sequence) {
                return;
            }

            instance.toggle.setAttribute('data-visual-state', state);
            setTrackWidth(instance, state, false);

            instance.revealTimer = window.setTimeout(function () {
                if (sequence !== instance.sequence) {
                    return;
                }

                instance.toggle.classList.remove('is-fading');
            }, fadeInDelay);
        }, fadeOutDelay);
    }

    function updateEffectsSetting() {
        effectsEnabled = window.wpacuMenuVisibilityUseEffects !== false
            && ! (reduceMotionQuery && reduceMotionQuery.matches);

        root.classList.toggle(
            'wpacu-menu-visibility-no-effects',
            ! effectsEnabled
        );
    }

    controls.forEach(function (control) {
        var input = document.getElementById(control.inputId);
        var card = document.getElementById(control.cardId);
        var toggle;
        var track;
        var visibleLabel;
        var hiddenLabel;
        var instance;

        if (! input || ! card) {
            return;
        }

        toggle = input.closest('.wpacu-menu-visibility-toggle');

        if (! toggle) {
            return;
        }

        track = toggle.querySelector('.wpacu-menu-visibility-toggle__track');
        visibleLabel = toggle.querySelector('.wpacu-menu-visibility-toggle__text--visible');
        hiddenLabel = toggle.querySelector('.wpacu-menu-visibility-toggle__text--hidden');

        if (! track || ! visibleLabel || ! hiddenLabel) {
            return;
        }

        instance = {
            input: input,
            card: card,
            toggle: toggle,
            track: track,
            visibleLabel: visibleLabel,
            hiddenLabel: hiddenLabel,
            sequence: 0,
            fadeTimer: null,
            revealTimer: null
        };

        instances.push(instance);
        input.addEventListener('change', function () {
            applyAnimatedState(instance);
        });
    });

    function refreshAllToggleWidths() {
        instances.forEach(function (instance) {
            applyImmediateState(instance);
        });
    }

    updateEffectsSetting();
    refreshAllToggleWidths();

    /* Public helpers: useful for previews, custom integrations, or tests. */
    window.wpacuMenuVisibilityRefreshToggleWidths = refreshAllToggleWidths;
    window.wpacuMenuVisibilitySetEffects = function (enabled) {
        window.wpacuMenuVisibilityUseEffects = !! enabled;
        updateEffectsSetting();
        refreshAllToggleWidths();
    };

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(refreshAllToggleWidths);
    }

    window.addEventListener('resize', function () {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(refreshAllToggleWidths, 100);
    });

    if (reduceMotionQuery) {
        if (typeof reduceMotionQuery.addEventListener === 'function') {
            reduceMotionQuery.addEventListener('change', function () {
                updateEffectsSetting();
                refreshAllToggleWidths();
            });
        } else if (typeof reduceMotionQuery.addListener === 'function') {
            reduceMotionQuery.addListener(function () {
                updateEffectsSetting();
                refreshAllToggleWidths();
            });
        }
    }
})();
</script>
