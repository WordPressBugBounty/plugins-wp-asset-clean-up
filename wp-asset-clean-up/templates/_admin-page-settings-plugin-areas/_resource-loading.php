<?php
/*
 * No direct access to this file
 */

use \WpAssetCleanUp\Admin\OptimiseAssets\ResourceLoadingAdmin;
use WpAssetCleanUp\OptimiseAssets\ResourceLoading;

if (! isset($data)) {
    exit;
}

global $wp_version;

$settingKey = ResourceLoading::$settingKey;
$data['current_setting_key'] = $settingKey;

$tabIdArea = 'wpacu-setting-resource-loading';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea) ? 'style="display: table-cell;"' : '';

$data[$settingKey]             = isset($data[$settingKey]) && is_array($data[$settingKey]) ? $data[$settingKey] : array();

$data[$settingKey]['_enabled'] = isset($data[$settingKey]['_enabled']) && (int)$data[$settingKey]['_enabled'] === 1;

$resourceLoadingEnabled = $data[$settingKey]['_enabled'];
$resourceLoadingImageAttrEnabled = ! empty($data[$settingKey]['images']['attr']['_enabled']);
$resourceLoadingImageLazyLoadEnabled = ! empty($data[$settingKey]['images']['lazy_load']['_enabled']);
$resourceLoadingHasEnabledFeatures = $resourceLoadingImageAttrEnabled || $resourceLoadingImageLazyLoadEnabled;
$resourceLoadingSelectedSubTab = isset($selectedSubTabArea)
    && in_array($selectedSubTabArea, array('wpacu-resource-loading-image-attr', 'wpacu-resource-loading-image-lazy-load'), true)
        ? $selectedSubTabArea
        : 'wpacu-resource-loading-image-attr';
$resourceLoadingImageAttrSelected = $resourceLoadingSelectedSubTab === 'wpacu-resource-loading-image-attr';

if ($resourceLoadingEnabled && $resourceLoadingHasEnabledFeatures) {
    $resourceLoadingUiState = 'active';
} elseif ($resourceLoadingEnabled) {
    $resourceLoadingUiState = 'enabled-empty';
} elseif ($resourceLoadingHasEnabledFeatures) {
    $resourceLoadingUiState = 'paused';
} else {
    $resourceLoadingUiState = 'off';
}

// [Image Attributes]
$data[$settingKey.'_image_attr_rules']   = ResourceLoadingAdmin::getImageAttributeRulesForAdmin($data);
$data[$settingKey.'_image_attr_allowed'] = ResourceLoading\ImageAttributes::getAllowedResourceLoadingImageAttributes();
// [/Image Attributes]

// [Lazy Load]
$data[$settingKey.'_image_lazy_load_rules'] = ResourceLoadingAdmin::getImageLazyLoadRulesForAdmin($data); // Lazy Loading
// [/Lazy Load]

?>
<div id="<?php echo esc_attr($tabIdArea); ?>" class="wpacu-settings-tab-content" <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <main id="wpacu-resource-loading-settings" class="wpacu-resource-loading-page" data-wpacu-resource-loading-state="<?php echo esc_attr($resourceLoadingUiState); ?>">
        <section class="wpacu-resource-loading-panel" aria-labelledby="wpacuResourceLoadingTitle">
            <header class="wpacu-resource-loading-header">
                <div>
                    <div class="wpacu-resource-loading-eyebrow"><?php esc_html_e('Browser loading strategy', 'wp-asset-clean-up'); ?></div>
                    <h2 id="wpacuResourceLoadingTitle"><?php esc_html_e('Improve image loading, rendering priority, and LCP', 'wp-asset-clean-up'); ?></h2>
                    <p><?php esc_html_e('Control how eligible images are requested and decoded. Add precise loading attributes to selected images, or automate native lazy loading with exclusions for critical content.', 'wp-asset-clean-up'); ?></p>
                </div>
                <div id="wpacu-resource-loading-page-badge" class="wpacu-resource-loading-header-badge is-<?php echo esc_attr($resourceLoadingUiState); ?>">
                    <?php
                    if ($resourceLoadingUiState === 'active') {
                        esc_html_e('Changes are active', 'wp-asset-clean-up');
                    } elseif ($resourceLoadingUiState === 'paused') {
                        esc_html_e('Configured, currently paused', 'wp-asset-clean-up');
                    } elseif ($resourceLoadingUiState === 'enabled-empty') {
                        esc_html_e('Ready for configuration', 'wp-asset-clean-up');
                    } else {
                        esc_html_e('Not configured', 'wp-asset-clean-up');
                    }
                    ?>
                </div>
            </header>

            <div class="wpacu-resource-loading-body">
                <section class="wpacu-resource-loading-intro" aria-labelledby="wpacuResourceLoadingIntroTitle">
                    <div class="wpacu-resource-loading-intro-icon" aria-hidden="true"><span class="dashicons dashicons-performance"></span></div>
                    <div>
                        <h3 id="wpacuResourceLoadingIntroTitle"><?php esc_html_e('Use the right strategy for each image', 'wp-asset-clean-up'); ?></h3>
                        <p><?php esc_html_e('Above-the-fold and LCP images often need eager loading or high fetch priority, while below-the-fold images can usually be lazy loaded. These tools let you handle both cases without editing theme templates.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </section>

                <input type="hidden" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[<?php echo esc_attr($settingKey); ?>][_enabled]" value="0">

                <section class="wpacu-resource-loading-master" aria-labelledby="wpacuResourceLoadingMasterTitle">
                    <div class="wpacu-resource-loading-master-control">
                        <label class="wpacu_switch" for="wpacu_<?php echo esc_attr($settingKey); ?>_enabled">
                            <input type="checkbox"
                                   data-target-opacity="#wpacu-resource-loading-configuration-area"
                                   id="wpacu_<?php echo esc_attr($settingKey); ?>_enabled"
                                   name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[<?php echo esc_attr($settingKey); ?>][_enabled]"
                                   value="1"
                                <?php checked($data[$settingKey]['_enabled']); ?>>
                            <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                        </label>
                        <span class="wpacu-resource-loading-master-label">
                            <strong id="wpacu-resource-loading-master-status"><?php echo $resourceLoadingEnabled ? esc_html__('Enabled', 'wp-asset-clean-up') : esc_html__('Paused', 'wp-asset-clean-up'); ?></strong>
                            <small><?php esc_html_e('Save changes after toggling.', 'wp-asset-clean-up'); ?></small>
                        </span>
                    </div>
                    <div class="wpacu-resource-loading-master-copy">
                        <span class="wpacu-resource-loading-master-kicker"><?php esc_html_e('Main setting', 'wp-asset-clean-up'); ?></span>
                        <h3 id="wpacuResourceLoadingMasterTitle"><?php esc_html_e('Enable Resource Loading', 'wp-asset-clean-up'); ?></h3>
                        <p><?php esc_html_e('This master switch applies or pauses all Resource Loading features. Turning it off keeps every saved rule and exclusion ready for later.', 'wp-asset-clean-up'); ?></p>
                        <p class="wpacu-resource-loading-master-note"><?php esc_html_e('The master switch must be enabled for the configured options below to affect the front end.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </section>

                <div id="wpacu-resource-loading-configuration-area" class="<?php if ( ! $data[$settingKey]['_enabled'] ) { ?>wpacu-disabled-area<?php } ?>">
                <section class="wpacu-resource-loading-strategy-picker" aria-labelledby="wpacuResourceLoadingWorkspaceTitle">
                    <div class="wpacu-resource-loading-section-heading">
                        <div><span><?php esc_html_e('Configuration', 'wp-asset-clean-up'); ?></span><h3 id="wpacuResourceLoadingWorkspaceTitle"><?php esc_html_e('Choose a loading strategy', 'wp-asset-clean-up'); ?></h3></div>
                    </div>
                    <div class="wpacu-resource-loading-feature-grid" role="tablist" aria-label="<?php esc_attr_e('Resource Loading configuration sections', 'wp-asset-clean-up'); ?>">
                        <div class="wpacu-resource-loading-feature-card<?php echo $resourceLoadingImageAttrSelected ? ' is-selected' : ''; ?>" data-wpacu-resource-loading-card="image-attributes">
                            <button type="button" class="wpacu-resource-loading-feature-tab" data-wpacu-resource-loading-tab-target="wpacu-resource-loading-image-attr-tab-item" role="tab" aria-selected="<?php echo $resourceLoadingImageAttrSelected ? 'true' : 'false'; ?>" aria-controls="wpacu-resource-loading-image-attr-tab-item-area">
                                <span class="wpacu-resource-loading-feature-icon" aria-hidden="true"><span class="dashicons dashicons-format-image"></span></span>
                                <span class="wpacu-resource-loading-feature-copy"><strong><?php esc_html_e('Image Attributes', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Match specific images and apply loading, fetchpriority, or decoding attributes with granular rules.', 'wp-asset-clean-up'); ?></span></span>
                                <span class="dashicons dashicons-arrow-right-alt2 wpacu-resource-loading-feature-arrow" aria-hidden="true"></span>
                            </button>
                            <div class="wpacu-resource-loading-card-toggle" role="group" aria-label="<?php esc_attr_e('Enable Image Attributes', 'wp-asset-clean-up'); ?>">
                                <input type="hidden" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[<?php echo esc_attr($settingKey); ?>][images][attr][_enabled]" value="0">
                                <label class="wpacu_switch_medium" for="wpacu_<?php echo esc_attr($settingKey); ?>_images_attr_enabled">
                                    <input type="checkbox" id="wpacu_<?php echo esc_attr($settingKey); ?>_images_attr_enabled" data-target-opacity="#wpacu-image-attributes-rules" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[<?php echo esc_attr($settingKey); ?>][images][attr][_enabled]" value="1" <?php checked($resourceLoadingImageAttrEnabled); ?>><span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                                </label>
                                <label for="wpacu_<?php echo esc_attr($settingKey); ?>_images_attr_enabled"><strong><?php esc_html_e('Enable', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Apply matching rules', 'wp-asset-clean-up'); ?></span></label>
                            </div>
                        </div>
                        <div class="wpacu-resource-loading-feature-card<?php echo ! $resourceLoadingImageAttrSelected ? ' is-selected' : ''; ?>" data-wpacu-resource-loading-card="lazy-load">
                            <button type="button" class="wpacu-resource-loading-feature-tab" data-wpacu-resource-loading-tab-target="wpacu-resource-loading-image-lazy-load-tab-item" role="tab" aria-selected="<?php echo ! $resourceLoadingImageAttrSelected ? 'true' : 'false'; ?>" aria-controls="wpacu-resource-loading-image-lazy-load-tab-item-area">
                                <span class="wpacu-resource-loading-feature-icon" aria-hidden="true"><span class="dashicons dashicons-images-alt2"></span></span>
                                <span class="wpacu-resource-loading-feature-copy"><strong><?php esc_html_e('Automatic Lazy Load', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Add native lazy loading to eligible images and exclude logos, hero images, sliders, or other critical content.', 'wp-asset-clean-up'); ?></span></span>
                                <span class="dashicons dashicons-arrow-right-alt2 wpacu-resource-loading-feature-arrow" aria-hidden="true"></span>
                            </button>
                            <div class="wpacu-resource-loading-card-toggle" role="group" aria-label="<?php esc_attr_e('Enable Automatic Lazy Load', 'wp-asset-clean-up'); ?>">
                                <input type="hidden" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[<?php echo esc_attr($settingKey); ?>][images][lazy_load][_enabled]" value="0">
                                <label class="wpacu_switch_medium" for="wpacu_<?php echo esc_attr($settingKey); ?>_images_lazy_load_enabled">
                                    <input type="checkbox" id="wpacu_<?php echo esc_attr($settingKey); ?>_images_lazy_load_enabled" data-target-opacity="#wpacu-image-lazy-load-rules" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[<?php echo esc_attr($settingKey); ?>][images][lazy_load][_enabled]" value="1" <?php checked($resourceLoadingImageLazyLoadEnabled); ?>><span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                                </label>
                                <label for="wpacu_<?php echo esc_attr($settingKey); ?>_images_lazy_load_enabled"><strong><?php esc_html_e('Enable', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Apply automatic lazy loading', 'wp-asset-clean-up'); ?></span></label>
                            </div>
                        </div>
                    </div>
                </section>

                <div id="wpacu-resource-loading-state-notice"
                     class="wpacu-resource-loading-state-notice <?php echo ($resourceLoadingUiState === 'enabled-empty') ? 'is-enabled-empty' : 'is-paused'; ?>"
                     aria-live="polite"
                    <?php if ( ! in_array($resourceLoadingUiState, array('paused', 'enabled-empty'), true) ) { ?> hidden<?php } ?>>
                    <span class="dashicons <?php echo ($resourceLoadingUiState === 'enabled-empty') ? 'dashicons-info' : 'dashicons-controls-pause'; ?>" aria-hidden="true"></span>
                    <div>
                        <strong id="wpacu-resource-loading-state-notice-title"><?php echo ($resourceLoadingUiState === 'enabled-empty') ? esc_html__('Resource Loading is enabled, but no feature is active', 'wp-asset-clean-up') : esc_html__('Resource Loading is paused', 'wp-asset-clean-up'); ?></strong>
                        <span id="wpacu-resource-loading-state-notice-text"><?php echo ($resourceLoadingUiState === 'enabled-empty') ? esc_html__('Enable Image Attributes or Lazy Load below to apply Resource Loading changes.', 'wp-asset-clean-up') : esc_html__('The options below remain configured, but they are not applied while the main switch is off. Turn Resource Loading back on to reactivate them.', 'wp-asset-clean-up'); ?></span>
                    </div>
                </div>

                <section class="wpacu-resource-loading-workspace" aria-label="<?php esc_attr_e('Selected loading strategy settings', 'wp-asset-clean-up'); ?>">
                    <div id="wpacu-resource-loading-sub-tabs-area">
                        <?php \WpAssetCleanUp\Admin\SettingsAdmin::printSubTabsOutput(__DIR__, $data, __FILE__); ?>
                    </div>
                </section>

                <div class="wpacu-resource-loading-note-grid">
                    <aside><span class="dashicons dashicons-warning" aria-hidden="true"></span><div><strong><?php esc_html_e('Protect the LCP image', 'wp-asset-clean-up'); ?></strong><p><?php esc_html_e('Do not lazy load the main above-the-fold image. Exclude it or use an Image Attributes rule that gives it the appropriate loading priority.', 'wp-asset-clean-up'); ?></p></div></aside>
                    <aside><span class="dashicons dashicons-search" aria-hidden="true"></span><div><strong><?php esc_html_e('Verify the generated markup', 'wp-asset-clean-up'); ?></strong><p><?php esc_html_e('After saving, inspect representative pages and confirm that attributes are applied only to the intended images. Clear page, server, and CDN caches when necessary.', 'wp-asset-clean-up'); ?></p></div></aside>
                </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script type="text/javascript">
(function ($) {
    'use strict';

    var selectors = [
        '#wpacu_resource_loading_enabled',
        '#wpacu_resource_loading_images_attr_enabled',
        '#wpacu_resource_loading_images_lazy_load_enabled'
    ].join(', ');

    var stateLabels = {
        active: <?php echo wp_json_encode(__('Active', 'wp-asset-clean-up')); ?>,
        'enabled-empty': <?php echo wp_json_encode(__('Enabled', 'wp-asset-clean-up')); ?>,
        paused: <?php echo wp_json_encode(__('Paused', 'wp-asset-clean-up')); ?>,
        off: ''
    };

    var stateDescriptions = {
        active: <?php echo wp_json_encode(__('At least one option is enabled', 'wp-asset-clean-up')); ?>,
        'enabled-empty': <?php echo wp_json_encode(__('No options active', 'wp-asset-clean-up')); ?>,
        paused: <?php echo wp_json_encode(__('Configured options are currently inactive', 'wp-asset-clean-up')); ?>,
        off: ''
    };

    var featureStatusTitles = {
        active: <?php echo wp_json_encode(__('Active', 'wp-asset-clean-up')); ?>,
        paused: <?php echo wp_json_encode(__('Configured, but inactive while Resource Loading is paused', 'wp-asset-clean-up')); ?>,
        off: ''
    };

    var noticeText = {
        pausedTitle: <?php echo wp_json_encode(__('Resource Loading is paused', 'wp-asset-clean-up')); ?>,
        pausedBody: <?php echo wp_json_encode(__('The options below remain configured, but they are not applied while the main switch is off. Turn Resource Loading back on to reactivate them.', 'wp-asset-clean-up')); ?>,
        emptyTitle: <?php echo wp_json_encode(__('Resource Loading is enabled, but no feature is active', 'wp-asset-clean-up')); ?>,
        emptyBody: <?php echo wp_json_encode(__('Enable Image Attributes or Lazy Load below to apply Resource Loading changes.', 'wp-asset-clean-up')); ?>
    };

    var pageStateLabels = {
        active: <?php echo wp_json_encode(__('Changes are active', 'wp-asset-clean-up')); ?>,
        'enabled-empty': <?php echo wp_json_encode(__('Ready for configuration', 'wp-asset-clean-up')); ?>,
        paused: <?php echo wp_json_encode(__('Configured, currently paused', 'wp-asset-clean-up')); ?>,
        off: <?php echo wp_json_encode(__('Not configured', 'wp-asset-clean-up')); ?>
    };

    function setFeatureStatus(feature, configured, masterEnabled) {
        var $featureStatus = $('[data-resource-loading-feature="' + feature + '"]');
        var $circle = $featureStatus.find('.wpacu-circle-status');
        var state = configured ? (masterEnabled ? 'active' : 'paused') : 'off';
        var circleClass = state === 'active' ? 'wpacu-on' : (state === 'paused' ? 'wpacu-paused' : 'wpacu-off');

        $circle
            .removeClass('wpacu-on wpacu-off wpacu-paused')
            .addClass(circleClass);

        $featureStatus.attr('title', featureStatusTitles[state]);

        $(feature === 'image-attributes' ? '#wpacu-image-attributes-rules' : '#wpacu-image-lazy-load-rules')
            .toggleClass('wpacu-disabled-area', ! configured);

    }

    function syncStrategyCards() {
        $('.wpacu-resource-loading-feature-tab[data-wpacu-resource-loading-tab-target]').each(function () {
            var $tab = $(this);
            var selected = $('#' + $tab.attr('data-wpacu-resource-loading-tab-target')).prop('checked');
            $tab.closest('.wpacu-resource-loading-feature-card').toggleClass('is-selected', selected);
            $tab.attr('aria-selected', selected ? 'true' : 'false');
        });
    }

    function updateResourceLoadingState() {
        var masterEnabled = $('#wpacu_resource_loading_enabled').prop('checked');
        var imageAttrEnabled = $('#wpacu_resource_loading_images_attr_enabled').prop('checked');
        var lazyLoadEnabled = $('#wpacu_resource_loading_images_lazy_load_enabled').prop('checked');
        var hasEnabledFeatures = imageAttrEnabled || lazyLoadEnabled;
        var state;

        if (masterEnabled && hasEnabledFeatures) {
            state = 'active';
        } else if (masterEnabled) {
            state = 'enabled-empty';
        } else if (hasEnabledFeatures) {
            state = 'paused';
        } else {
            state = 'off';
        }

        var $stateBadge = $('#wpacu-resource-loading-state-badge');

        $('#wpacu-resource-loading-settings').attr('data-wpacu-resource-loading-state', state);
        $('#wpacu-resource-loading-configuration-area').toggleClass('wpacu-disabled-area', ! masterEnabled);

        $('#wpacu-resource-loading-page-badge')
            .removeClass('is-active is-enabled-empty is-paused is-off')
            .addClass('is-' + state)
            .text(pageStateLabels[state]);

        $('#wpacu-resource-loading-master-status').text(
            masterEnabled
                ? <?php echo wp_json_encode(__('Enabled', 'wp-asset-clean-up')); ?>
                : <?php echo wp_json_encode(__('Paused', 'wp-asset-clean-up')); ?>
        );

        $stateBadge
            .removeClass('is-active is-enabled-empty is-paused is-off')
            .addClass('is-' + state)
            .attr('data-state', state)
            .text(stateLabels[state])
            .toggle(state !== 'off');

        $('#wpacu-resource-loading-state-description').text(stateDescriptions[state]);

        var hideStateSummary = state === 'off';
        $('.wpacu-resource-loading-state-row').toggle(! hideStateSummary);
        $('#wpacu-resource-loading-vertical-tab-area').show();

        setFeatureStatus('image-attributes', imageAttrEnabled, masterEnabled);
        setFeatureStatus('lazy-load', lazyLoadEnabled, masterEnabled);

        var $notice = $('#wpacu-resource-loading-state-notice');
        var $noticeIcon = $notice.find('.dashicons');

        if (state === 'paused') {
            $notice
                .removeAttr('hidden')
                .removeClass('is-enabled-empty')
                .addClass('is-paused');

            $noticeIcon
                .removeClass('dashicons-info')
                .addClass('dashicons-controls-pause');

            $('#wpacu-resource-loading-state-notice-title').text(noticeText.pausedTitle);
            $('#wpacu-resource-loading-state-notice-text').text(noticeText.pausedBody);
        } else if (state === 'enabled-empty') {
            $notice
                .removeAttr('hidden')
                .removeClass('is-paused')
                .addClass('is-enabled-empty');

            $noticeIcon
                .removeClass('dashicons-controls-pause')
                .addClass('dashicons-info');

            $('#wpacu-resource-loading-state-notice-title').text(noticeText.emptyTitle);
            $('#wpacu-resource-loading-state-notice-text').text(noticeText.emptyBody);
        } else {
            $notice.attr('hidden', 'hidden');
        }
    }

    $(document).on('click change tick', selectors, function () {
        // Run after the existing generic status-circle handler.
        window.setTimeout(updateResourceLoadingState, 0);
    });

    $(document).on('click', '.wpacu-resource-loading-feature-tab[data-wpacu-resource-loading-tab-target]', function () {
        var $target = $('#' + $(this).attr('data-wpacu-resource-loading-tab-target'));
        if ($target.length && ! $target.prop('checked')) {
            $target.trigger('click');
        }
        syncStrategyCards();
    });

    $(document).on('click change', '#wpacu-resource-loading-sub-tabs-area .wpacu-nav-input-sub-tab-area', syncStrategyCards);

    $(document).on('change', '.wpacu-resource-loading-card-toggle input[type="checkbox"]', function () {
        $(this).closest('.wpacu-resource-loading-feature-card').find('.wpacu-resource-loading-feature-tab').trigger('click');
    });

    $(document).on('click', '.wpacu-resource-loading-card-toggle', function (event) {
        if ($(event.target).closest('input, label').length) {
            return;
        }

        $(this).find('input[type="checkbox"]').trigger('click');
    });

    $(document).on('click', '.wpacu-resource-loading-feature-card', function (event) {
        if ($(event.target).closest('.wpacu-resource-loading-card-toggle, .wpacu-resource-loading-feature-tab').length) {
            return;
        }

        $(this).find('.wpacu-resource-loading-feature-tab').trigger('click');
    });

    $(function () { updateResourceLoadingState(); syncStrategyCards(); });
})(jQuery);
</script>
