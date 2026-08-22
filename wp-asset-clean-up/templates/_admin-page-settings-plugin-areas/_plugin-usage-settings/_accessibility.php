<?php
use WpAssetCleanUp\Settings;

if (! isset($data)) {
	exit;
}

$inputStyle = Settings::getInputStyle($data);
$useEnhancedInputs = Settings::useEnhancedInputs($inputStyle);
?>
<header class="wpacu-page-header">
    <div>
        <div class="wpacu-eyebrow"><?php esc_html_e('Interface & Accessibility', 'wp-asset-clean-up'); ?></div>
        <h2 id="wpacuAccessibilityTitle"><?php esc_html_e('Choose the controls that work best for you', 'wp-asset-clean-up'); ?></h2>
        <p><?php esc_html_e("Asset CleanUp can use enhanced controls or the browser's native form elements throughout the Dashboard. This preference changes only how settings are displayed.", 'wp-asset-clean-up'); ?></p>
    </div>
</header>

<section class="wpacu-intro" aria-labelledby="wpacuSameSettingsTitle">
    <div class="wpacu-intro__icon" aria-hidden="true">
        <span class="dashicons dashicons-admin-settings"></span>
    </div>
    <div>
        <h3 id="wpacuSameSettingsTitle"><?php esc_html_e('Same settings, different controls', 'wp-asset-clean-up'); ?></h3>
        <p><?php esc_html_e('Switching the interface style does not reset saved rules, change optimization behavior, or affect the public-facing site.', 'wp-asset-clean-up'); ?></p>
    </div>
</section>

<fieldset class="wpacu-fieldset">
    <legend><?php esc_html_e('Input fields style', 'wp-asset-clean-up'); ?></legend>

    <?php if ($useEnhancedInputs) { ?>
        <div class="wpacu-choice-grid">
            <label class="wpacu-choice" for="wpacu_input_style_enhanced">
                <input id="wpacu_input_style_enhanced"
                       checked="checked"
                       type="radio"
                       aria-labelledby="wpacuInputStyleEnhancedTitle"
                       aria-describedby="wpacuInputStyleEnhancedSubtitle wpacuInputStyleEnhancedDescription"
                       name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[input_style]"
                       value="enhanced" />
                <span class="wpacu-choice-card">
                    <span class="wpacu-choice-top">
                        <span class="wpacu-choice-icon" aria-hidden="true"><span class="dashicons dashicons-controls-repeat"></span></span>
                        <span class="wpacu-choice-title-wrap">
                            <span class="wpacu-choice-title" id="wpacuInputStyleEnhancedTitle">
                                <?php esc_html_e('Enhanced controls', 'wp-asset-clean-up'); ?>
                                <span class="wpacu-badge" aria-hidden="true"><?php esc_html_e('Default', 'wp-asset-clean-up'); ?></span>
                            </span>
                            <span class="wpacu-choice-subtitle" id="wpacuInputStyleEnhancedSubtitle"><?php esc_html_e('Toggle switches and searchable dropdowns', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </span>
                    <span class="wpacu-choice-description" id="wpacuInputStyleEnhancedDescription"><?php esc_html_e('A more visual interface with clear on/off states and search inside supported option lists.', 'wp-asset-clean-up'); ?></span>
                    <span class="wpacu-choice-list">
                        <span><?php esc_html_e('Modern toggle switches', 'wp-asset-clean-up'); ?></span>
                        <span><?php esc_html_e('Search within supported dropdowns', 'wp-asset-clean-up'); ?></span>
                        <span><?php esc_html_e('Recommended for most users', 'wp-asset-clean-up'); ?></span>
                    </span>
                </span>
            </label>

            <label class="wpacu-choice" for="wpacu_input_style_standard">
                <input id="wpacu_input_style_standard"
                       type="radio"
                       aria-labelledby="wpacuInputStyleStandardTitle"
                       aria-describedby="wpacuInputStyleStandardSubtitle wpacuInputStyleStandardDescription"
                       name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[input_style]"
                       value="standard" />
                <span class="wpacu-choice-card">
                    <span class="wpacu-choice-top">
                        <span class="wpacu-choice-icon" aria-hidden="true"><span class="dashicons dashicons-forms"></span></span>
                        <span class="wpacu-choice-title-wrap">
                            <span class="wpacu-choice-title" id="wpacuInputStyleStandardTitle">
                                <?php esc_html_e('Native browser controls', 'wp-asset-clean-up'); ?>
                                <span class="wpacu-badge" aria-hidden="true"><?php esc_html_e('Compatibility', 'wp-asset-clean-up'); ?></span>
                            </span>
                            <span class="wpacu-choice-subtitle" id="wpacuInputStyleStandardSubtitle"><?php esc_html_e('Standard checkboxes and select fields for maximum compatibility', 'wp-asset-clean-up'); ?></span>
                        </span>
                    </span>
                    <span class="wpacu-choice-description" id="wpacuInputStyleStandardDescription"><?php esc_html_e("Uses the browser's built-in form controls without custom toggle or searchable-dropdown styling.", 'wp-asset-clean-up'); ?></span>
                    <span class="wpacu-choice-list">
                        <span><?php esc_html_e('Standard HTML checkboxes and selects', 'wp-asset-clean-up'); ?></span>
                        <span><?php esc_html_e('Native keyboard and browser behavior', 'wp-asset-clean-up'); ?></span>
                        <span><?php esc_html_e('Useful with some assistive technologies', 'wp-asset-clean-up'); ?></span>
                    </span>
                </span>
            </label>
        </div>
    <?php } else { ?>
        <div class="wpacu-native-input-style-choices">
            <label class="wpacu-native-input-style-choice" for="wpacu_input_style_enhanced">
                <input id="wpacu_input_style_enhanced"
                       type="radio"
                       name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[input_style]"
                       value="enhanced" />
                <span>
                    <strong><?php esc_html_e('Enhanced controls', 'wp-asset-clean-up'); ?></strong>
                    <span><?php esc_html_e('Toggle switches and searchable enhanced dropdowns. This is the default interface.', 'wp-asset-clean-up'); ?></span>
                </span>
            </label>

            <label class="wpacu-native-input-style-choice" for="wpacu_input_style_standard">
                <input id="wpacu_input_style_standard"
                       checked="checked"
                       type="radio"
                       name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[input_style]"
                       value="standard" />
                <span>
                    <strong><?php esc_html_e('Native browser controls', 'wp-asset-clean-up'); ?></strong>
                    <span><?php esc_html_e('Standard HTML controls with native keyboard and browser behavior.', 'wp-asset-clean-up'); ?></span>
                </span>
            </label>
        </div>
    <?php } ?>
</fieldset>

<section class="wpacu-impact" aria-labelledby="wpacuAccessibilityChangesTitle">
    <h3 id="wpacuAccessibilityChangesTitle"><?php esc_html_e('What changes when you switch?', 'wp-asset-clean-up'); ?></h3>
    <div class="wpacu-impact-grid">
        <div class="wpacu-impact-item"><strong><?php esc_html_e('Checkboxes', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Toggle switches or standard browser checkboxes', 'wp-asset-clean-up'); ?></span></div>
        <div class="wpacu-impact-item"><strong><?php esc_html_e('Dropdowns', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Searchable enhanced selects or native select fields', 'wp-asset-clean-up'); ?></span></div>
        <div class="wpacu-impact-item"><strong><?php esc_html_e('Saved settings', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Your existing values and optimization rules remain unchanged', 'wp-asset-clean-up'); ?></span></div>
    </div>
</section>

<aside class="wpacu-note">
    <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
    <p>
        <strong><?php esc_html_e('Not sure which option to use?', 'wp-asset-clean-up'); ?></strong>
        <?php esc_html_e('Keep Enhanced controls unless you prefer native form elements or experience difficulty with enhanced fields. When Native browser controls are selected, supported Chosen dropdowns are shown as standard HTML select fields instead.', 'wp-asset-clean-up'); ?>
        <a href="https://www.assetcleanup.com/docs/?p=95" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Read more about input field styles', 'wp-asset-clean-up'); ?></a>.
    </p>
</aside>
