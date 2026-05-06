<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}

global $wp_version;

$tabIdArea = 'wpacu-setting-resource-loading';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea) ? 'style="display: table-cell;"' : '';

$resourceLoadingData       = isset($data['resource_loading']) && is_array($data['resource_loading']) ? $data['resource_loading'] : array();
$resourceLoadingEnabled    = isset($resourceLoadingData['_enabled']) && (int)$resourceLoadingData['_enabled'] === 1;
$resourceLoadingImageRules = isset($resourceLoadingData['images']) && is_array($resourceLoadingData['images']) ? $resourceLoadingData['images'] : array();

$allowedResourceLoadingAttributes = \WpAssetCleanUp\Admin\SettingsAdmin::$allowedResourceLoadingAttributes;

// Always the first one to show (no need to click "Add New Rule")
if (empty($resourceLoadingImageRules)) {
    $firstAttribute = key($allowedResourceLoadingAttributes);
    $firstValues = isset($allowedResourceLoadingAttributes[$firstAttribute]) ? $allowedResourceLoadingAttributes[$firstAttribute] : array();

    $resourceLoadingImageRules = array(
        array(
            'source'    => '',
            'attribute' => $firstAttribute,
            'value'     => isset($firstValues[0]) ? $firstValues[0] : ''
        )
    );
}

$allowedResourceLoadingAttributesJson = function_exists('wp_json_encode')
    ? wp_json_encode($allowedResourceLoadingAttributes)
    : json_encode($allowedResourceLoadingAttributes);

$showRemoveButton = count($resourceLoadingImageRules) > 1;

?>
<style>
    .wpacu-rules-header,
    .wpacu-rule-row {
        display: grid;
        grid-template-columns: 400px 180px 180px max-content;
        gap: 8px;
        align-items: center;
    }

    .wpacu-rules-header {
        font-weight: 600;
        margin-bottom: 6px;
    }

    .wpacu-rules-header > div {
        line-height: 30px;
    }

    .wpacu-rule-row {
        margin-bottom: 8px;
    }

    .wpacu-rule-row input,
    .wpacu-rule-row select {
        width: 100%;
        min-width: 0;
        height: 30px;
        box-sizing: border-box;
    }

    .wpacu-remove-rule {
        color: #b32d2e !important;
    }

    .wpacu-disabled-area {
        opacity: 0.5;
    }

    .wpacu-tooltip {
        position: relative;
        display: inline-block;
        margin-left: 4px;
        cursor: help;
        color: #646970;
        font-size: 13px;
        font-weight: normal;
        vertical-align: top;
    }

    .wpacu-tooltip:hover {
        color: #2271b1;
    }

    .wpacu-tooltip-text {
        position: absolute;
        bottom: 125%;
        left: 0;
        z-index: 9999;
        width: max-content;
        max-width: 260px;
        padding: 6px 8px;
        background: #004567;
        color: #fff;
        border-radius: 6px;
        font-size: 12px;
        line-height: 1.4;
        white-space: normal;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: translateY(3px);
        transition: opacity 0.12s ease, transform 0.12s ease, visibility 0.12s ease;
    }

    .wpacu-tooltip-text code {
        color: #004567;
        border-radius: 3px;
        display: inline-block;
        background: lightgrey;
    }

    .wpacu-tooltip-text::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 8px;
        border-width: 7px;
        border-style: solid;
        border-color: #004567 transparent transparent transparent;
    }

    .wpacu-tooltip:hover .wpacu-tooltip-text {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .wpacu-hide {
        display: none !important;
    }

    @media (max-width: 900px) {
        .wpacu-rules-header {
            display: none;
        }

        .wpacu-rule-row {
            grid-template-columns: 1fr;
            gap: 6px;
            padding: 10px;
            border: 1px solid #ccd0d4;
            background: #fff;
        }

        .wpacu-rule-row input,
        .wpacu-rule-row select,
        .wpacu-rule-row .button {
            width: 100%;
        }

        .wpacu-rule-row .button {
            text-align: center;
        }
    }
</style>

<div id="<?php echo esc_attr($tabIdArea); ?>" class="wpacu-settings-tab-content" <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <h2 class="wpacu-settings-area-title"><?php _e('Optimize how resources are loaded by the browser to improve page speed and LCP', 'wp-asset-clean-up'); ?></h2>
    <p>Prioritize critical images, preload key resources, and reduce delays during page load. &nbsp; <a target="_blank" style="text-decoration: none;" href="http://www.assetcleanup.com/docs/?p=2279"><span class="dashicons dashicons-info"></span> Read more</a></p>
    <div style="background: #f2faf2; border-left: 3px solid #4caf50; padding: 10px; margin: 0 0 18px;">This feature matches image URIs and applies attributes to the corresponding <code>&lt;img&gt;</code> tags.</div>

    <div style="margin: 0 0 18px;">
        <label class="wpacu_switch">
            <input type="checkbox"
                   id="wpacu_resource_loading_enabled"
                   name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[resource_loading][_enabled]"
                   value="1"
                   <?php checked($resourceLoadingEnabled); ?>>
            <span class="wpacu_slider wpacu_round"></span>
        </label> &nbsp; Enable Resource Loading rules
    </div>

    <div id="wpacu-image-attributes-rules" class="wpacu-rules-wrap <?php if ( ! $resourceLoadingEnabled ) { ?>wpacu-disabled-area<?php } ?>">
        <div class="wpacu-rules-table">
            <div class="wpacu-rules-header">
                <div>
                    <?php _e('Image URI (full or partial) / RegEx', 'wp-asset-clean-up'); ?>
                    <span class="wpacu-tooltip">ⓘ
                        <span class="wpacu-tooltip-text" style="max-width: 340px;"><?php _e('Match full or partial image URIs such as <code><strong>/hero.jpg</strong></code> or RegEx such as <code><strong>#/wp-content/uploads/(.*?).jpg#</strong></code>', 'wp-asset-clean-up'); ?></span>
                    </span>
                </div>
                <div>
                    <?php _e('Attribute', 'wp-asset-clean-up'); ?>
                    <span class="wpacu-tooltip">ⓘ
                        <span class="wpacu-tooltip-text"><?php _e('Defines which attribute will be added to matching <img> tags.', 'wp-asset-clean-up'); ?></span>
                    </span>
                </div>
                <div>
                    <?php _e('Value', 'wp-asset-clean-up'); ?>
                    <span class="wpacu-tooltip">ⓘ
                        <span class="wpacu-tooltip-text"><?php _e('Select the value for the chosen attribute.', 'wp-asset-clean-up'); ?></span>
                    </span>
                </div>
                <div></div>
            </div>

            <div class="wpacu-rules-list">
                <?php foreach ($resourceLoadingImageRules as $ruleIndex => $rule) :
                    $source = isset($rule['source']) ? $rule['source'] : '';
                    $selectedAttribute = (isset($rule['attribute']) && isset($allowedResourceLoadingAttributes[$rule['attribute']])) ? $rule['attribute'] : key($allowedResourceLoadingAttributes);
                    $allowedValues = isset($allowedResourceLoadingAttributes[$selectedAttribute]) ? $allowedResourceLoadingAttributes[$selectedAttribute] : array();
                    $selectedValue = (isset($rule['value']) && in_array($rule['value'], $allowedValues, true)) ? $rule['value'] : (isset($allowedValues[0]) ? $allowedValues[0] : '');
                    ?>
                    <div class="wpacu-rule-row">
                        <input type="text"
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[resource_loading][images][<?php echo (int)$ruleIndex; ?>][source]"
                               class="wpacu-source-uri"
                               placeholder="/wp-content/uploads/hero.jpg"
                               value="<?php echo esc_attr($source); ?>">

                        <select name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[resource_loading][images][<?php echo (int)$ruleIndex; ?>][attribute]"
                                class="wpacu-attribute">
                            <option value="" disabled>— <?php esc_html_e('Attribute', 'wp-asset-clean-up'); ?> —</option>
                            <?php foreach ($allowedResourceLoadingAttributes as $attribute => $values) : ?>
                                <option value="<?php echo esc_attr($attribute); ?>" <?php selected($selectedAttribute, $attribute); ?>><?php echo esc_html($attribute); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[resource_loading][images][<?php echo (int)$ruleIndex; ?>][value]"
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
                <?php endforeach; ?>
            </div>
        </div>

        <button type="button" class="button wpacu-add-rule">
            + <?php _e('Add New Rule', 'wp-asset-clean-up'); ?>
        </button>
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

            function updateResourceLoadingRulesState() {
                var isEnabled = $('#wpacu_resource_loading_enabled').is(':checked');
                var $rulesWrap = $('#wpacu-image-attributes-rules');

                $rulesWrap
                    .css('opacity', isEnabled ? '1' : '0.5')
                    .toggleClass('wpacu-disabled-area', ! isEnabled);
            }

            $('#wpacu_resource_loading_enabled').on('change.wpacu', function () {
                updateResourceLoadingRulesState();
            });

            updateResourceLoadingRulesState();

            function getUsedAttributesForSourceBeforeRow(source, $currentRow) {
                var used = [];

                if (! source) {
                    return used;
                }

                $currentRow.prevAll('.wpacu-rule-row').each(function () {
                    var $row = $(this);
                    var rowSource = $.trim($row.find('.wpacu-source-uri').val());
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
                var $sourceInput = $row.find('.wpacu-source-uri');
                var $attributeSelect = $row.find('.wpacu-attribute');
                var $valueSelect = $row.find('.wpacu-value');

                var source = $.trim($sourceInput.val());
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
                $row.find('.wpacu-source-uri').val('');
                $row.find('.wpacu-attribute').val('fetchpriority');
                populateValueSelect($row.find('.wpacu-attribute'), $row.find('.wpacu-value'), 'high');
            }

            function bindRowEvents($row) {
                $row.find('.wpacu-source-uri')
                    .off('input.wpacu change.wpacu blur.wpacu paste.wpacu')
                    .on('input.wpacu change.wpacu blur.wpacu paste.wpacu', function () {
                        var $currentRow = $(this).closest('.wpacu-rule-row');

                        // For paste, wait until the pasted value is actually in the input
                        setTimeout(function () {
                            updateAllAttributeOptions();
                        }, 0);
                    });

                $row.find('.wpacu-attribute').off('change.wpacu').on('change.wpacu', function () {
                    populateValueSelect($row.find('.wpacu-attribute'), $row.find('.wpacu-value'), '');
                    updateAllAttributeOptions();
                });

                $row.find('.wpacu-remove-rule').off('click.wpacu').on('click.wpacu', function () {
                    $row.remove();

                    reindexRules();

                    updateAllAttributeOptions();
                    updateRemoveButtons();
                });
            }

            function addRuleByClone() {
                var $clone = $list.find('.wpacu-rule-row:first').clone(false);

                resetRow($clone);
                bindRowEvents($clone);

                $list.append($clone);

                reindexRules();

                updateAllAttributeOptions();
                updateRemoveButtons();
            }

            function reindexRules() {
                $list.find('.wpacu-rule-row').each(function (i) {
                    var $row = $(this);

                    $row.find('.wpacu-source-uri')
                        .attr('name', '<?php echo WPACU_PLUGIN_ID; ?>_settings[resource_loading][images][' + i + '][source]');

                    $row.find('.wpacu-attribute')
                        .attr('name', '<?php echo WPACU_PLUGIN_ID; ?>_settings[resource_loading][images][' + i + '][attribute]');

                    $row.find('.wpacu-value')
                        .attr('name', '<?php echo WPACU_PLUGIN_ID; ?>_settings[resource_loading][images][' + i + '][value]');
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
                    populateValueSelect($row.find('.wpacu-attribute'), $row.find('.wpacu-value'), $row.find('.wpacu-value').val());
                });

                updateAllAttributeOptions();
            }

            $wrap.find('.wpacu-add-rule').on('click', function () {
                addRuleByClone();
                reindexRules();
            });

            initRows();
        });
    </script>
</div>
