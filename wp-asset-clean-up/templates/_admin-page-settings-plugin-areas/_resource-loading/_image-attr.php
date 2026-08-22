<?php
if (! isset($data)) {
    exit;
}

use \WpAssetCleanUp\OptimiseAssets\ResourceLoading\ImageAttributes;

$settingKey    = $data['current_setting_key']; // e.g. resource_loading
$settingSubKey = 'attr';

$allowedResourceLoadingImageAttributes = $data[$settingKey.'_image_attr_allowed'];
$resourceLoadingImageAttributesRules   = $data[$settingKey.'_image_'.$settingSubKey.'_rules'];

$allowedResourceLoadingImageAttributeMatchBy    = ImageAttributes::getAllowedResourceLoadingImageAttributeMatchBy();
$allowedResourceLoadingImageAttributeMatchTypes = ImageAttributes::getAllowedResourceLoadingImageAttributeMatchTypes();

$allowedResourceLoadingAttributesJson = function_exists('wp_json_encode')
        ? wp_json_encode($allowedResourceLoadingImageAttributes)
        : json_encode($allowedResourceLoadingImageAttributes);

$showRemoveButton = count($data[$settingKey.'_image_'.$settingSubKey.'_rules']) > 1;

$allSources = array();

foreach ($resourceLoadingImageAttributesRules as $rule) {
    $matchBy    = isset($rule['match_by'])    ? trim($rule['match_by'])    : 'source';
    $matchType  = isset($rule['match_type'])  ? trim($rule['match_type'])  : '';
    $matchValue = isset($rule['match_value']) ? trim($rule['match_value']) : (isset($rule['source']) ? trim($rule['source']) : '');

    if ($matchType === '' && $matchValue !== '') {
        $matchType = \WpAssetCleanUp\OptimiseAssets\ResourceLoading::startsWithRegexDelimiter($matchValue) ? 'regex' : 'contains';
    }

    if ($matchValue !== '') {
        $allSources[] = $matchBy . '|' . $matchType . '|' . $matchValue;
    }
}

// If there are duplicate sources, all rows receive the same spacing class.
$hasSameSourceGroups = count($allSources) !== count(array_unique($allSources));
?>
<div id="wpacu-image-attributes-rules" class="wpacu-rules-wrap <?php if ($hasSameSourceGroups) { ?>wpacu-has-same-source-groups <?php } ?><?php if ( ! $data[$settingKey]['images'][$settingSubKey]['_enabled'] ) { ?>wpacu-disabled-area<?php } ?>">
    <div class="wpacu-resource-loading-tool-header">
        <div><span class="wpacu-resource-loading-tool-kicker"><?php esc_html_e('Targeted control', 'wp-asset-clean-up'); ?></span><h3><?php esc_html_e('Apply attributes to matching images', 'wp-asset-clean-up'); ?></h3><p><?php esc_html_e('Create precise rules for images that need a specific browser loading or decoding strategy.', 'wp-asset-clean-up'); ?></p></div>
        <a target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=2279"><span class="dashicons dashicons-external" aria-hidden="true"></span><?php esc_html_e('Read documentation', 'wp-asset-clean-up'); ?></a>
    </div>
    <div class="wpacu-resource-loading-explanation">
        Match images by source, CSS class, or the full <code>&lt;img&gt;</code> tag
        <span class="wpacu-tooltip">ⓘ
        <span class="wpacu-tooltip-text" style="max-width: 400px;">
            <strong>Image source:</strong> Checks <code>src</code>, <code>srcset</code>,
            <code>data-src</code>, and <code>data-srcset</code>.<br><br>

            <strong>CSS class:</strong> Checks the <code>class</code> attribute.<br><br>

            <strong>Whole Tag:</strong> Checks the entire <code>&lt;img&gt;</code> HTML markup.
        </span>
    </span>
        , then apply loading-related attributes such as
        <code>loading</code>, <code>fetchpriority</code>, and <code>decoding</code>.    </div>

    <div id="wpacu-resource-loading-images-attr-rules-area"
         class="wpacu-rules-table wpacu-image-attr-rule-builder-v2">
        <style>
            /* Applied to all rows only if at least one same-source group exists */
            .wpacu-has-same-source-groups .wpacu-rule-row {
                padding: 8px;
                border-left: 3px solid transparent;
                box-sizing: border-box;
            }

            .wpacu-has-same-source-groups button.wpacu-add-rule {
                margin: 6px 0;
            }

            /* Highlight rows belonging to the same source group */
            .wpacu-rule-row.wpacu-same-source-group {
                background: #f8fbfd;
                border-left-color: #008f9c;
                margin-bottom: 0px;
            }

            .wpacu-rule-row.wpacu-same-source-group + .wpacu-rule-row.wpacu-same-source-group {
                margin-top: 4px;
            }

            .wpacu-rule-row.wpacu-same-source-group.wpacu-is-first-in-same-source-group {
                margin-bottom: -4px;
            }

            .wpacu-rule-row.wpacu-same-source-group.wpacu-is-last-in-same-source-group {
                margin-top: 0 !important;
            }

            @media (max-width: 900px) {
                .wpacu-rule-row:not(.wpacu-same-source-group) {
                    padding: 8px;
                    border-left: 1px solid #ccd0d4 !important;
                }

                .wpacu-rule-row.wpacu-same-source-group {
                    border-bottom: 0px;
                    padding: 12px 8px 12px 8px !important;
                    margin-bottom: 0 !important;
                    border-left: 3px solid #008f9c;
                }

                .wpacu-rule-row.wpacu-same-source-group.wpacu-is-first-in-same-source-group {
                    .wpacu-attribute, .wpacu-value, button.button {
                        margin-top: 0;
                    }
                }

                .wpacu-rule-row.wpacu-same-source-group + .wpacu-rule-row.wpacu-same-source-group {
                    border-top: 0px;
                }

                .wpacu-rule-row.wpacu-is-last-in-same-source-group {
                    border-bottom: 1px solid #ccd0d4;
                }
            }

            .wpacu-source-field-wrap {
                width: 100%;
                min-width: 0;
            }

            .wpacu-source-field-wrap .wpacu-source-uri {
                width: 100%;
                min-width: 0;
                margin: 0;
            }

            .wpacu-source-field-wrap input {
                width: 100%;
            }

            .wpacu-rule-group-badge {
                display: inline-block;
                margin: 0px 0 7px 0px;
                padding: 2px 6px;
                border-radius: 10px;
                background: #e6f7fa;
                color: #007c89;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.4;
            }

            .wpacu-warning-badge {
                display: inline-block;
                align-items: center;
                gap: 3px;
                margin: 0 0 7px 5px;
                padding: 2px 7px;
                border: 0;
                border-radius: 10px;
                background: #fff4ce;
                color: #7a5600;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.4;
                cursor: pointer;
            }

            .wpacu-warning-badge .dashicons {
                width: 18px;
                height: 18px;
                font-size: 18px;
                line-height: 18px;
            }

            .wpacu-warning-badge:hover {
                background: #ffe8a3;
            }


            /* WPACU Image Attributes: sentence builder layout v2
             * Keep the rule readable while giving the match value input most of the available width.
             */
            .wpacu-image-attr-rule-builder-v2 .wpacu-rules-list {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-row {
                align-items: stretch;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-row:not(.wpacu-same-source-group) {
                padding: 10px;
                border: 1px solid #dcdcde;
                border-radius: 5px;
                background: #fff;
                box-sizing: border-box;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-source-field-wrap {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                box-sizing: border-box;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-sentence.wpacu-rule-sentence-v2 {
                display: grid;
                grid-template-columns: 1fr;
                gap: 9px;
                width: 100%;
                max-width: 100%;
                min-width: 0;
                box-sizing: border-box;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-condition,
            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-action {
                display: grid;
                gap: 8px;
                align-items: center;
                width: 100%;
                max-width: 100%;
                min-width: 0;
                box-sizing: border-box;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-condition {
                grid-template-columns: auto 155px 145px minmax(360px, 1fr);
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-action {
                grid-template-columns: auto 155px 120px auto;
                justify-content: start;
                padding-left: 47px;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-keyword {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 39px;
                padding: 4px 8px;
                border-radius: 999px;
                background: #f0f6fc;
                color: #1d2327;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: .02em;
                text-transform: uppercase;
                line-height: 1.4;
                box-sizing: border-box;
                white-space: nowrap;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-keyword.wpacu-rule-keyword-then {
                min-width: 88px;
                background: #f6f7f7;
                text-transform: none;
                letter-spacing: 0;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-match-field-wrap {
                display: contents;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-condition .wpacu-match-by,
            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-condition .wpacu-match-type,
            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-action .wpacu-attribute,
            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-action .wpacu-value {
                width: 100%;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-condition .wpacu-match-value {
                width: 100%;
                min-width: 0;
            }

            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-sentence select,
            .wpacu-image-attr-rule-builder-v2 .wpacu-rule-sentence input {
                max-width: 100%;
                min-width: 0;
                margin: 0;
            }

            /* New Rule */
            .wpacu-new-rule-fieldset {
                margin: 14px 0 10px;
                padding: 12px;
                border: 1px dashed #2271b1;
                border-radius: 6px;
                background: #f6faff;
            }

            .wpacu-new-rule-fieldset legend {
                padding: 0 7px;
                font-weight: 600;
                color: #2271b1;
            }

            .wpacu-new-rule-fieldset .wpacu-rule-row {
                margin: 0;
                padding: 0;
                border: 0;
                background: transparent;
            }

            @media (min-width: 1280px) {
                .wpacu-image-attr-rule-builder-v2 .wpacu-rule-condition {
                    grid-template-columns: auto 165px 155px minmax(500px, 1fr);
                }

                .wpacu-image-attr-rule-builder-v2 .wpacu-rule-action {
                    grid-template-columns: auto 160px 125px auto;
                }
            }

            @media (max-width: 1100px) {
                .wpacu-image-attr-rule-builder-v2 .wpacu-rule-condition {
                    grid-template-columns: auto 145px 140px minmax(240px, 1fr);
                }

                .wpacu-image-attr-rule-builder-v2 .wpacu-rule-row.wpacu-same-source-group.wpacu-is-first-in-same-source-group .wpacu-rule-action {
                    margin-top: 0;
                }
            }

            @media (max-width: 900px) {
                .wpacu-image-attr-rule-builder-v2 .wpacu-rule-condition,
                .wpacu-image-attr-rule-builder-v2 .wpacu-rule-action {
                    grid-template-columns: 1fr;
                    padding-left: 0;
                }

                .wpacu-image-attr-rule-builder-v2 .wpacu-rule-keyword,
                .wpacu-image-attr-rule-builder-v2 .wpacu-rule-keyword.wpacu-rule-keyword-then {
                    width: 100%;
                }
            }
        </style>
        <div class="wpacu-rules-list">
            <?php
            // [START] Source Flags
            // e.g. perhaps fetchpriority="high" and loading="lazy" are both applied to the same image
            // in this case, remind the user about potential downsides
            $sourceFlags = array();

            foreach ($resourceLoadingImageAttributesRules as $rule) {
                $matchBy    = isset($rule['match_by'])    ? trim($rule['match_by'])    : 'source';
                $matchType  = isset($rule['match_type'])  ? trim($rule['match_type'])  : '';
                $matchValue = isset($rule['match_value']) ? trim($rule['match_value']) : (isset($rule['source']) ? trim($rule['source']) : '');

                if (empty($matchValue) || empty($rule['attribute']) || ! isset($rule['value'])) {
                    continue;
                }

                if ($matchType === '') {
                    $matchType = \WpAssetCleanUp\OptimiseAssets\ResourceLoading::startsWithRegexDelimiter($matchValue) ? 'regex' : 'contains';
                }

                $source = $matchBy . '|' . $matchType . '|' . $matchValue;

                if ($source === '') {
                    continue;
                }

                if (! isset($sourceFlags[$source])) {
                    $sourceFlags[$source] = array(
                        'fetchpriority_high' => false,
                        'loading_lazy'       => false
                    );
                }

                if ($rule['attribute'] === 'fetchpriority' && $rule['value'] === 'high') {
                    $sourceFlags[$source]['fetchpriority_high'] = true;
                }

                if ($rule['attribute'] === 'loading' && $rule['value'] === 'lazy') {
                    $sourceFlags[$source]['loading_lazy'] = true;
                }
            }
            // [END] Source Flags

            $rulesCount = count($resourceLoadingImageAttributesRules);

            foreach ($resourceLoadingImageAttributesRules as $ruleIndex => $rule) :
                // No values in the database?
                $isNewRule  = ! empty($rule['_is_new_rule']);

                // [Source]
                $matchBy    = isset($rule['match_by'])    ? trim($rule['match_by'])    : 'source';
                $matchType  = isset($rule['match_type'])  ? trim($rule['match_type'])  : '';
                $matchValue = isset($rule['match_value']) ? trim($rule['match_value']) : (isset($rule['source']) ? trim($rule['source']) : '');

                if ( ! isset($allowedResourceLoadingImageAttributeMatchBy[$matchBy]) ) {
                    $matchBy = 'source';
                }

                if ($matchType === '' && $matchValue !== '') {
                    $matchType = \WpAssetCleanUp\OptimiseAssets\ResourceLoading::startsWithRegexDelimiter($matchValue) ? 'regex' : 'contains';
                }

                if ( ! isset($allowedResourceLoadingImageAttributeMatchTypes[$matchType]) ) {
                    $matchType = 'contains';
                }

                $source = $matchValue;
                $sourceGroupKey = $matchBy . '|' . $matchType . '|' . $matchValue;

                $prevSource = '';
                if ($ruleIndex > 0) {
                    $prevRuleMatchBy    = isset($resourceLoadingImageAttributesRules[$ruleIndex - 1]['match_by']) ? trim($resourceLoadingImageAttributesRules[$ruleIndex - 1]['match_by']) : 'source';
                    $prevRuleMatchType  = isset($resourceLoadingImageAttributesRules[$ruleIndex - 1]['match_type']) ? trim($resourceLoadingImageAttributesRules[$ruleIndex - 1]['match_type']) : '';
                    $prevRuleMatchValue = isset($resourceLoadingImageAttributesRules[$ruleIndex - 1]['match_value']) ? trim($resourceLoadingImageAttributesRules[$ruleIndex - 1]['match_value']) : (isset($resourceLoadingImageAttributesRules[$ruleIndex - 1]['source']) ? trim($resourceLoadingImageAttributesRules[$ruleIndex - 1]['source']) : '');

                    if ($prevRuleMatchType === '' && $prevRuleMatchValue !== '') {
                        $prevRuleMatchType = \WpAssetCleanUp\OptimiseAssets\ResourceLoading::startsWithRegexDelimiter($prevRuleMatchValue) ? 'regex' : 'contains';
                    }

                    $prevSource = $prevRuleMatchBy . '|' . $prevRuleMatchType . '|' . $prevRuleMatchValue;
                }

                $nextSource = '';
                if ($ruleIndex < ($rulesCount - 1)) {
                    $nextRuleMatchBy    = isset($resourceLoadingImageAttributesRules[$ruleIndex + 1]['match_by']) ? trim($resourceLoadingImageAttributesRules[$ruleIndex + 1]['match_by']) : 'source';
                    $nextRuleMatchType  = isset($resourceLoadingImageAttributesRules[$ruleIndex + 1]['match_type']) ? trim($resourceLoadingImageAttributesRules[$ruleIndex + 1]['match_type']) : '';
                    $nextRuleMatchValue = isset($resourceLoadingImageAttributesRules[$ruleIndex + 1]['match_value']) ? trim($resourceLoadingImageAttributesRules[$ruleIndex + 1]['match_value']) : (isset($resourceLoadingImageAttributesRules[$ruleIndex + 1]['source']) ? trim($resourceLoadingImageAttributesRules[$ruleIndex + 1]['source']) : '');

                    if ($nextRuleMatchType === '' && $nextRuleMatchValue !== '') {
                        $nextRuleMatchType = \WpAssetCleanUp\OptimiseAssets\ResourceLoading::startsWithRegexDelimiter($nextRuleMatchValue) ? 'regex' : 'contains';
                    }

                    $nextSource = $nextRuleMatchBy . '|' . $nextRuleMatchType . '|' . $nextRuleMatchValue;
                }

                $isSameSourceGroup = (
                    $sourceGroupKey !== '' &&
                    ($sourceGroupKey === $prevSource || $sourceGroupKey === $nextSource)
                );

                // First row in a same-source group.
                $isFirstInSameSourceGroup = (
                    $sourceGroupKey !== '' &&
                    $sourceGroupKey !== $prevSource &&
                    $sourceGroupKey === $nextSource
                );

                // Last row in a same-source group.
                $isLastInSameSourceGroup = (
                        $sourceGroupKey !== '' &&
                        $sourceGroupKey === $prevSource &&
                        $sourceGroupKey !== $nextSource
                );

                $sourceHasLazyAndHighPriority =
                        isset($sourceFlags[$sourceGroupKey]) &&
                        $sourceFlags[$sourceGroupKey]['fetchpriority_high'] &&
                        $sourceFlags[$sourceGroupKey]['loading_lazy'];

                $rowExtraClasses = array();

                if ($isSameSourceGroup) {
                    $rowExtraClasses[] = 'wpacu-same-source-group';
                }

                if ($isFirstInSameSourceGroup) {
                    $rowExtraClasses[] = 'wpacu-is-first-in-same-source-group';
                }

                if ($isLastInSameSourceGroup) {
                    $rowExtraClasses[] = 'wpacu-is-last-in-same-source-group';
                }
                // [/Source]

                // [Attribute]
                $selectedAttribute = (isset($rule['attribute']) && isset($allowedResourceLoadingImageAttributes[$rule['attribute']])) ? $rule['attribute'] : key($allowedResourceLoadingImageAttributes);
                // [/Attribute]

                // [Value]
                $allowedValues = isset($allowedResourceLoadingImageAttributes[$selectedAttribute]) ? $allowedResourceLoadingImageAttributes[$selectedAttribute] : array();
                $selectedValue = (isset($rule['value']) && in_array($rule['value'], $allowedValues, true)) ? $rule['value'] : (isset($allowedValues[0]) ? $allowedValues[0] : '');
                // [/Value]

                if ($isSameSourceGroup) {
                    $hasFetchPriorityHigh = $hasLoadingLazy = false; // default

                    if ($selectedAttribute === 'fetchpriority' && $selectedValue === 'high') {
                        $hasFetchPriorityHigh = true;
                    }

                    if ($selectedAttribute === 'loading' && $selectedValue === 'lazy') {
                        $hasLoadingLazy = true;
                    }

                    if ($hasFetchPriorityHigh && $hasLoadingLazy) {
                        $sourceHasLazyAndHighPriority = true;
                        break;
                    }
                }
                ?>

                <?php if ($isNewRule) { ?>
                <fieldset class="wpacu-new-rule-fieldset">
                    <legend><?php _e('New Rule', 'wp-asset-clean-up'); ?></legend>
                <?php } ?>

                <div class="wpacu-rule-row wpacu-rule-row-sentence-builder <?php echo esc_attr(implode(' ', $rowExtraClasses)); ?>" data-wpacu-layout="image-attr-rule-builder-v2">
                    <div class="wpacu-source-field-wrap">
                        <?php if ($isFirstInSameSourceGroup) { ?>
                            <span class="wpacu-rule-group-badge">Same image match</span>

                            <?php if ($sourceHasLazyAndHighPriority) { ?>
                                <button type="button"
                                        title="<?php esc_attr_e('Click for more details', 'wp-asset-clean-up'); ?>"
                                        class="wpacu-warning-badge wpacu-same-source-maybe-conflict-attributes-warning"
                                        data-source="<?php echo esc_attr($source); ?>">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Review', 'wp-asset-clean-up'); ?>
                                </button>
                            <?php } ?>
                        <?php } ?>

                        <div class="wpacu-rule-sentence wpacu-rule-sentence-v2">
                            <div class="wpacu-rule-condition">
                                <span class="wpacu-rule-keyword"><?php _e('IF', 'wp-asset-clean-up'); ?></span>

                                <div class="wpacu-match-field-wrap">
                                    <select name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][data][<?php echo (int)$ruleIndex; ?>][match_by]"
                                            class="wpacu-match-by">
                                        <?php foreach ($allowedResourceLoadingImageAttributeMatchBy as $matchByKey => $matchByLabel) : ?>
                                            <option value="<?php echo esc_attr($matchByKey); ?>" <?php selected($matchBy, $matchByKey); ?>><?php echo esc_html($matchByLabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <select name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][data][<?php echo (int)$ruleIndex; ?>][match_type]"
                                            class="wpacu-match-type">
                                        <?php foreach ($allowedResourceLoadingImageAttributeMatchTypes as $matchTypeKey => $matchTypeLabel) : ?>
                                            <option value="<?php echo esc_attr($matchTypeKey); ?>" <?php selected($matchType, $matchTypeKey); ?>><?php echo esc_html($matchTypeLabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <input type="text"
                                           name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][data][<?php echo (int)$ruleIndex; ?>][match_value]"
                                           class="wpacu-source-uri wpacu-match-value"
                                           placeholder="Example: /wp-content/uploads/hero.jpg"
                                           value="<?php echo esc_attr($matchValue); ?>" />
                                </div>
                            </div>

                            <div class="wpacu-rule-action">
                                <span class="wpacu-rule-keyword wpacu-rule-keyword-then"><?php _e('THEN apply', 'wp-asset-clean-up'); ?></span>

                                <select name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][data][<?php echo (int)$ruleIndex; ?>][attribute]"
                                        class="wpacu-attribute">
                                    <option value="" disabled>— <?php esc_html_e('Attribute', 'wp-asset-clean-up'); ?> —</option>
                                    <?php foreach ($allowedResourceLoadingImageAttributes as $attribute => $values) : ?>
                                        <option value="<?php echo esc_attr($attribute); ?>" <?php selected($selectedAttribute, $attribute); ?>><?php echo esc_html($attribute); ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][data][<?php echo (int)$ruleIndex; ?>][value]"
                                        class="wpacu-value">
                                    <option value="" disabled>— <?php esc_html_e('Value', 'wp-asset-clean-up'); ?> —</option>
                                    <?php foreach ($allowedValues as $value) : ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($selectedValue, $value); ?>><?php echo esc_html($value); ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <button style="<?php echo $showRemoveButton ? '' : 'display: none;'; ?>"
                                        type="button"
                                        class="button wpacu-remove-rule"><?php _e('Remove', 'wp-asset-clean-up'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($isNewRule) { ?>
                </fieldset>
                <?php } ?>

                <?php
                // Output separator after the group ends.
                if ($isLastInSameSourceGroup) {
                    echo '<hr class="wpacu-rule-separator" />';
                }
                ?>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button wpacu-add-rule">
            + <?php _e('Add New Rule', 'wp-asset-clean-up'); ?>
        </button>
    </div>
</div>

<script>
    var wpacuPluginId = '<?php echo esc_js(WPACU_PLUGIN_ID); ?>';

    jQuery(document).ready(function ($) {
        'use strict';

        var $wrap = $('#wpacu-image-attributes-rules');

        if (! $wrap.length) {
            return;
        }

        var $list = $wrap.find('.wpacu-rules-list');
        var attributeValues = <?php echo $allowedResourceLoadingAttributesJson ? $allowedResourceLoadingAttributesJson : '{}'; ?>;

        function updateMatchValuePlaceholder($row) {
            var matchBy     = $row.find('.wpacu-match-by').val() || 'source';
            var matchType   = $row.find('.wpacu-match-type').val() || 'contains';
            var placeholder = '/wp-content/uploads/hero.jpg';

            if (matchBy === 'class') {
                placeholder = matchType === 'regex' ? '#(^|\\s)hero-image($|\\s)#' : 'hero-image';
            } else if (matchBy === 'whole_tag') {
                placeholder = matchType === 'regex' ? '#<img[^>]+custom-value#i' : 'custom-value';
            } else if (matchType === 'regex') {
                placeholder = '#/wp-content/uploads/(.*?).jpg#';
            }

            $row.find('.wpacu-match-value').attr('placeholder', placeholder);
        }

        function getMatchKey($row) {
            var matchBy     = $row.find('.wpacu-match-by').val() || 'source';
            var matchType   = $row.find('.wpacu-match-type').val() || 'contains';
            var matchValue  = $.trim($row.find('.wpacu-match-value').val());

            if (! matchValue) {
                return '';
            }

            return matchBy + '|' + matchType + '|' + matchValue;
        }

        function getUsedAttributesForSourceBeforeRow(source, $currentRow) {
            var used = [];

            if (! source) {
                return used;
            }

            $currentRow.prevAll('.wpacu-rule-row').each(function () {
                var $row         = $(this);
                var rowSource    = getMatchKey($row);
                var rowAttribute = $row.find('.wpacu-attribute').val();

                if (rowSource === source && rowAttribute) {
                    used.push(rowAttribute);
                }
            });

            return used;
        }

        function populateValueSelect($attributeSelect, $valueSelect, selectedValue) {
            var attribute = $attributeSelect.val();
            var values = attributeValues[attribute] || [];

            $valueSelect.find('option:not(:disabled)').remove();

            $.each(values, function (index, value) {
                $valueSelect.append(
                    $('<option></option>').attr('value', value).text(value)
                );
            });

            if (selectedValue && $.inArray(selectedValue, values) !== -1) {
                $valueSelect.val(selectedValue);
            } else if (values.length) {
                $valueSelect.val(values[0]);
            }
        }

        function updateAttributeOptions($row) {
            var $attributeSelect = $row.find('.wpacu-attribute');
            var $valueSelect = $row.find('.wpacu-value');

            var source = getMatchKey($row);
            var currentAttribute = $attributeSelect.val() || 'fetchpriority';
            var currentValue = $valueSelect.val();

            var usedAttributes = getUsedAttributesForSourceBeforeRow(source, $row);
            var availableAttributes = [];

            $.each(attributeValues, function (attribute) {
                if ($.inArray(attribute, usedAttributes) === -1) {
                    availableAttributes.push(attribute);
                }
            });

            // If current attribute is already used for same source, force first available one
            if (source && $.inArray(currentAttribute, usedAttributes) !== -1) {
                currentAttribute = availableAttributes.length ? availableAttributes[0] : '';
                currentValue = '';
            }

            $attributeSelect.find('option:not(:disabled)').remove();

            $.each(attributeValues, function (attribute) {
                if ($.inArray(attribute, usedAttributes) === -1 || attribute === currentAttribute) {
                    $attributeSelect.append(
                        $('<option></option>').attr('value', attribute).text(attribute)
                    );
                }
            });

            if (currentAttribute && $attributeSelect.find('option[value="' + currentAttribute + '"]').length) {
                $attributeSelect.val(currentAttribute);
            } else if ($attributeSelect.find('option').length) {
                $attributeSelect.val($attributeSelect.find('option:first').val());
            }

            populateValueSelect($attributeSelect, $valueSelect, currentValue);
        }

        function updateAllAttributeOptions() {
            $list.find('.wpacu-rule-row').each(function () {
                updateAttributeOptions($(this));
            });
        }

        function resetRow($row) {
            $row.removeClass('wpacu-same-source-group wpacu-is-first-in-same-source-group wpacu-is-last-in-same-source-group');

            $row.find('.wpacu-rule-group-badge, .wpacu-same-source-maybe-conflict-attributes-warning').remove();
            $row.find('.wpacu-match-by').val('source');
            $row.find('.wpacu-match-type').val('contains');
            $row.find('.wpacu-source-uri').val('');
            $row.find('.wpacu-attribute').val('fetchpriority');

            populateValueSelect($row.find('.wpacu-attribute'), $row.find('.wpacu-value'), 'high');
            updateMatchValuePlaceholder($row);
        }

        function bindRowEvents($row) {
            $row.find('.wpacu-source-uri, .wpacu-match-by, .wpacu-match-type')
                .off('input.wpacu change.wpacu blur.wpacu paste.wpacu')
                .on('input.wpacu change.wpacu blur.wpacu paste.wpacu', function () {
                    var $currentRow = $(this).closest('.wpacu-rule-row');

                    // For paste, wait until the pasted value is actually in the input
                    setTimeout(function () {
                        updateMatchValuePlaceholder($currentRow);
                        updateAllAttributeOptions();
                    }, 0);
                });

            $row.find('.wpacu-attribute').off('change.wpacu').on('change.wpacu', function () {
                populateValueSelect($row.find('.wpacu-attribute'), $row.find('.wpacu-value'), '');
                updateAllAttributeOptions();
            });

            $row.find('.wpacu-remove-rule').off('click.wpacu').on('click.wpacu', function () {
                var $fieldset = $row.closest('.wpacu-new-rule-fieldset');

                if ($fieldset.length) {
                    $fieldset.remove();
                } else {
                    $row.remove();
                }

                reindexRules();
                updateAllAttributeOptions();
                updateRemoveButtons();
            });
        }

        function addRuleByClone() {
            var $clone = $list.find('.wpacu-rule-row:first').clone(false);

            resetRow($clone);

            $clone
                .removeClass('wpacu-same-source-group wpacu-is-first-in-same-source-group wpacu-is-last-in-same-source-group wpacu-new-rule');

            $clone.find('.wpacu-rule-group-badge, .wpacu-warning-badge').remove();

            bindRowEvents($clone);

            var $fieldset = $('<fieldset class="wpacu-new-rule-fieldset"><legend>New Rule</legend></fieldset>');

            $fieldset.append($clone);
            $list.append($fieldset);

            reindexRules();

            updateAllAttributeOptions();
            updateRemoveButtons();

            $clone.find('.wpacu-match-value').focus();
        }

        function reindexRules() {
            $list.find('.wpacu-rule-row').each(function (i) {
                var $row = $(this);

                $row.find('.wpacu-match-by')
                    .attr('name', '<?php echo WPACU_PLUGIN_ID; ?>_settings[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][data][' + i + '][match_by]');

                $row.find('.wpacu-match-type')
                    .attr('name', '<?php echo WPACU_PLUGIN_ID; ?>_settings[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][data][' + i + '][match_type]');

                $row.find('.wpacu-source-uri')
                    .attr('name', '<?php echo WPACU_PLUGIN_ID; ?>_settings[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][data][' + i + '][match_value]');

                $row.find('.wpacu-attribute')
                    .attr('name', '<?php echo WPACU_PLUGIN_ID; ?>_settings[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][data][' + i + '][attribute]');

                $row.find('.wpacu-value')
                    .attr('name', '<?php echo WPACU_PLUGIN_ID; ?>_settings[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][data][' + i + '][value]');
            });
        }

        function updateRemoveButtons() {
            var $rows = $list.find('.wpacu-rule-row');
            $rows.find('.wpacu-remove-rule').toggle($rows.length > 1);
        }

        function initRows() {
            $list.find('.wpacu-rule-row').each(function () {
                var $row = $(this);

                bindRowEvents($row);
                updateMatchValuePlaceholder($row);
                populateValueSelect($row.find('.wpacu-attribute'), $row.find('.wpacu-value'), $row.find('.wpacu-value').val());
            });

            updateAllAttributeOptions();
        }

        $wrap.find('.wpacu-add-rule').on('click', function () {
            addRuleByClone();
            reindexRules();
        });

        initRows();

        $(document).on('click', '.wpacu-same-source-maybe-conflict-attributes-warning', function() {
            var source = $(this).data('source') || '';

            wpacuSwal.fire({
                icon: 'warning',
                title: 'Review Image Loading Hints',
                html:
                    '<p>This image match has both ' +
                    '<code>fetchpriority="high"</code> and ' +
                    '<code>loading="lazy"</code> configured.</p>' +

                    '<p>This combination can be valid in rare cases, but in most situations ' +
                    'these attributes send opposite signals to the browser.</p>' +

                    '<p><strong>Match:</strong><br>' +
                    '<code>' + $('<div>').text(source).html() + '</code></p>' +

                    '<p style="margin-top: 12px;">' +
                    '<a href="https://www.assetcleanup.com/docs/?p=2344#wpacu-why-not-combine" ' +
                    'target="_blank" rel="noopener noreferrer">' +
                    'Read more in the documentation' +
                    '</a>' +
                    '</p>',

                confirmButtonText: 'I Understand',
                width: 640
            });
        });
    });
</script>
