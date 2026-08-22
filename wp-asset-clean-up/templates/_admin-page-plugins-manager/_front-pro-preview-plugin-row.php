<?php
use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUpLite\Admin\PluginsManagerPreview;

if ( ! isset($wpacuPluginView, $wpacuLitePageData)) {
    exit;
}

$wpacuViewData = $wpacuPluginView['data'];
$wpacuPluginData = $wpacuPluginView['plugin_data'];
$wpacuPluginPath = $wpacuPluginView['plugin_path'];
$wpacuPluginDir = $wpacuPluginView['plugin_dir'];
$wpacuPluginAreaState = $wpacuPluginView['plugin_area_state'];
$wpacuPluginId = MiscAdmin::sanitizeValueForHtmlAttr($wpacuPluginPath);
$wpacuIsAlwaysLoaded = $wpacuPluginView['unload_rules_count'] === 0
    && $wpacuPluginView['load_exceptions_count'] === 0;
$wpacuPostTypes = PluginsManagerPreview::getPublicPostTypes();
$wpacuTaxonomies = PluginsManagerPreview::getPublicTaxonomies();
$wpacuArchiveTypes = PluginsManagerPreview::getArchiveTypes();
$wpacuRoles = PluginsManagerPreview::getUserRoles();
$wpacuEntityLabelMaps = isset($wpacuLitePageData['entity_label_maps']) && is_array($wpacuLitePageData['entity_label_maps'])
    ? $wpacuLitePageData['entity_label_maps']
    : array();

$wpacuRenderRuleItem = static function ($mode, $ruleKey, $label, $controlType, $controlData) use (
    $wpacuViewData,
    $wpacuPluginPath,
    $wpacuPluginId,
    $wpacuEntityLabelMaps
) {
    $isActiveKey = 'is_' . $ruleKey;
    $isActive = ! empty($wpacuViewData[$isActiveKey]);
    $inputClass = $mode === 'unload'
        ? 'wpacu_plugin_unload_rule_input'
        : 'wpacu_plugin_load_rule_input';
    $activeClass = $mode === 'unload'
        ? 'wpacu-pm-rule-is-active-unload'
        : 'wpacu-pm-rule-is-active-load';
    $checkedLabelClass = $mode === 'unload'
        ? 'wpacu_plugin_unload_rule_input_checked'
        : 'wpacu_plugin_load_rule_input_checked';
    $controlId = 'wpacu-lite-preview-' . $ruleKey . '-' . $wpacuPluginId;
    $valuesKey = $ruleKey . '_chosen';
    $selectedValues = isset($wpacuViewData[$valuesKey]) && is_array($wpacuViewData[$valuesKey])
        ? array_map('strval', $wpacuViewData[$valuesKey])
        : array();
    $options = isset($controlData['options']) && is_array($controlData['options'])
        ? $controlData['options']
        : array();
    $placeholder = isset($controlData['placeholder']) ? (string)$controlData['placeholder'] : '';
    $textValueKey = $ruleKey . '_value';
    $textValue = isset($wpacuViewData[$textValueKey]) && ! is_array($wpacuViewData[$textValueKey])
        ? (string)$wpacuViewData[$textValueKey]
        : '';

    if ($controlType === 'text' && ! empty($selectedValues)) {
        $textValue = implode(', ', PluginsManagerPreview::getRuleEntityLabels(
            $ruleKey,
            $selectedValues,
            $wpacuEntityLabelMaps
        ));
    }
    ?>
    <li class="wpacu_plugin_rule_item<?php echo $isActive ? ' ' . esc_attr($activeClass) : ''; ?>">
        <label for="<?php echo esc_attr($controlId); ?>"<?php echo $isActive ? ' class="' . esc_attr($checkedLabelClass) . '"' : ''; ?>>
            <input data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
                   id="<?php echo esc_attr($controlId); ?>"
                   class="<?php echo esc_attr($inputClass . ' wpacu-lite-pm-preview-rule-input'); ?>"
                   type="checkbox"
                   value="<?php echo esc_attr($ruleKey); ?>"
                   disabled="disabled"
                   aria-disabled="true"
                <?php echo $isActive ? 'checked="checked"' : ''; ?> />
            <span><?php echo esc_html($label); ?></span>
        </label>

        <?php if ($controlType !== '') { ?>
            <div class="wpacu-lite-pm-preview-associated-control<?php echo $isActive ? '' : ' wpacu_hide'; ?>"
                 data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>">
                <?php if ($controlType === 'select') { ?>
                    <select multiple="multiple"
                            disabled="disabled"
                            aria-disabled="true"
                            aria-label="<?php echo esc_attr($label); ?>">
                        <?php if (empty($options) && ! empty($selectedValues)) {
                            foreach ($selectedValues as $selectedValue) { ?>
                                <option selected="selected" value="<?php echo esc_attr($selectedValue); ?>"><?php
                                    echo esc_html('#' . $selectedValue);
                                ?></option>
                            <?php }
                        } else {
                            foreach ($options as $optionValue => $optionLabel) {
                                $optionValue = (string)$optionValue;
                                ?>
                                <option value="<?php echo esc_attr($optionValue); ?>"<?php
                                    echo in_array($optionValue, $selectedValues, true) ? ' selected="selected"' : '';
                                ?>><?php echo esc_html($optionLabel); ?></option>
                            <?php }
                        } ?>
                    </select>
                <?php } elseif ($controlType === 'textarea') { ?>
                    <textarea class="wpacu_regex_rule_textarea"
                              disabled="disabled"
                              aria-disabled="true"
                              placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_textarea($textValue); ?></textarea>
                    <p><small><strong><?php esc_html_e('Note:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Enter one rule per line. Plain URI strings and RegEx patterns are supported.', 'wp-asset-clean-up'); ?></small></p>
                <?php } else { ?>
                    <input type="text"
                           disabled="disabled"
                           aria-disabled="true"
                           value="<?php echo esc_attr($textValue); ?>"
                           placeholder="<?php echo esc_attr($placeholder); ?>" />
                <?php } ?>
            </div>
        <?php } ?>
    </li>
    <?php
};

$wpacuRenderRuleCard = static function ($mode, $groupKey, $title, $helpText, $rules, $isSiteScope) use (
    $wpacuPluginView,
    $wpacuRenderRuleItem
) {
    $countsKey = $mode === 'unload' ? 'unload_counts' : 'load_counts';
    $count = isset($wpacuPluginView[$countsKey][$groupKey])
        ? (int)$wpacuPluginView[$countsKey][$groupKey]
        : 0;
    $sectionClasses = $isSiteScope
        ? 'wpacu-pm-site-scope' . ($mode === 'load' ? ' wpacu-pm-site-scope--load' : '')
        : 'wpacu-pm-rule-card';

    if ($count > 0) {
        $sectionClasses .= ' has-active-rules';
    }
    ?>
    <section class="<?php echo esc_attr($sectionClasses); ?>"
             data-wpacu-layout-rule-card="<?php echo esc_attr($mode); ?>"
             data-wpacu-layout-group="<?php echo esc_attr($groupKey); ?>">
        <header class="wpacu-pm-rule-card-header">
            <div class="wpacu-pm-rule-card-title">
                <?php echo esc_html($title); ?>
                <?php if ($helpText !== '') { ?>
                    <span class="dashicons dashicons-info-outline wpacu_plugin_rule_group_help"
                          tabindex="0"
                          role="img"
                          aria-label="<?php echo esc_attr($helpText); ?>"
                          data-wpacu-tooltip="<?php echo esc_attr($helpText); ?>"></span>
                <?php } ?>
            </div>
            <span class="wpacu-pm-card-count"
                  data-wpacu-layout-card-count
                  data-wpacu-active-label="<?php echo esc_attr__('active', 'wp-asset-clean-up'); ?>"
                  data-wpacu-no-active-label="<?php echo esc_attr__('No active rules', 'wp-asset-clean-up'); ?>"><?php
                echo $count > 0
                    ? esc_html($count . ' ' . __('active', 'wp-asset-clean-up'))
                    : esc_html__('No active rules', 'wp-asset-clean-up');
            ?></span>
        </header>

        <?php if ($isSiteScope) { ?><div class="wpacu-pm-site-scope-body"><?php } ?>
            <ul class="wpacu_plugin_rules wpacu_plugin_rules_grouped wpacu-pm-rule-list<?php echo $isSiteScope ? ' wpacu-pm-rule-list--site' : ''; ?><?php echo $mode === 'load' ? ' wpacu_exception_options_area' : ''; ?>">
                <?php foreach ($rules as $ruleData) {
                    $wpacuRenderRuleItem(
                        $mode,
                        $ruleData['key'],
                        $ruleData['label'],
                        isset($ruleData['control']) ? $ruleData['control'] : '',
                        isset($ruleData['control_data']) ? $ruleData['control_data'] : array()
                    );
                } ?>
            </ul>

            <?php if ($isSiteScope) { ?>
                <p class="wpacu-pm-site-scope-note">
                    <?php if ($mode === 'unload') { ?>
                        <strong><?php esc_html_e('Exclusive rule:', 'wp-asset-clean-up'); ?></strong>
                        <?php esc_html_e('“On all pages” supersedes and clears narrower unload rules for this plugin.', 'wp-asset-clean-up'); ?>
                    <?php } else { ?>
                        <?php esc_html_e('Use an exception only where a broader unload rule should not apply.', 'wp-asset-clean-up'); ?>
                    <?php } ?>
                </p>
            </div><?php } ?>
    </section>
    <?php
};

$wpacuUnloadRules = array(
    'site' => array(
        array('key' => 'unload_site_wide', 'label' => __('On all pages', 'wp-asset-clean-up')),
        array('key' => 'unload_homepage', 'label' => __('On the homepage', 'wp-asset-clean-up'))
    ),
    'singular' => array(
        array(
            'key' => 'unload_via_post',
            'label' => __('On specific posts, pages, products, or other entries:', 'wp-asset-clean-up'),
            'control' => 'text',
            'control_data' => array('placeholder' => __('Search individual entries…', 'wp-asset-clean-up'))
        ),
        array(
            'key' => 'unload_via_post_type',
            'label' => __('On all singular entries of these post types:', 'wp-asset-clean-up'),
            'control' => 'select',
            'control_data' => array('options' => $wpacuPostTypes)
        ),
        array(
            'key' => 'unload_via_post_tax_term',
            'label' => __('On singular entries assigned to selected taxonomy terms:', 'wp-asset-clean-up'),
            'control' => 'text',
            'control_data' => array('placeholder' => __('Search categories, tags, product terms…', 'wp-asset-clean-up'))
        )
    ),
    'archives' => array(
        array(
            'key' => 'unload_via_tax_term',
            'label' => __('On specific taxonomy term archive pages:', 'wp-asset-clean-up'),
            'control' => 'text',
            'control_data' => array('placeholder' => __('Search taxonomy terms…', 'wp-asset-clean-up'))
        ),
        array(
            'key' => 'unload_via_tax',
            'label' => __('On all term archive pages from these taxonomies:', 'wp-asset-clean-up'),
            'control' => 'select',
            'control_data' => array('options' => $wpacuTaxonomies)
        ),
        array(
            'key' => 'unload_via_archive',
            'label' => __('On these archive and listing page types:', 'wp-asset-clean-up'),
            'control' => 'select',
            'control_data' => array('options' => $wpacuArchiveTypes)
        ),
        array(
            'key' => 'unload_via_author_archive',
            'label' => __('On the archive pages of these authors:', 'wp-asset-clean-up'),
            'control' => 'text',
            'control_data' => array('placeholder' => __('Search authors…', 'wp-asset-clean-up'))
        )
    ),
    'conditions' => array(
        array(
            'key' => 'unload_via_regex',
            'label' => __('If the request URI matches any of these rules:', 'wp-asset-clean-up'),
            'control' => 'textarea',
            'control_data' => array('placeholder' => "/checkout/*\n#^/members/#")
        ),
        array('key' => 'unload_if_logged_in', 'label' => __('If the user is logged in', 'wp-asset-clean-up')),
        array(
            'key' => 'unload_logged_in_via_role',
            'label' => __('If the logged-in user has any of these roles:', 'wp-asset-clean-up'),
            'control' => 'select',
            'control_data' => array('options' => $wpacuRoles)
        )
    )
);

$wpacuLoadRules = array(
    'site' => array(
        array('key' => 'load_homepage', 'label' => __('On the homepage', 'wp-asset-clean-up'))
    ),
    'singular' => array(
        array(
            'key' => 'load_via_post',
            'label' => __('On specific posts, pages, products, or other entries:', 'wp-asset-clean-up'),
            'control' => 'text',
            'control_data' => array('placeholder' => __('Search individual entries…', 'wp-asset-clean-up'))
        ),
        array(
            'key' => 'load_via_post_type',
            'label' => __('On all singular entries of these post types:', 'wp-asset-clean-up'),
            'control' => 'select',
            'control_data' => array('options' => $wpacuPostTypes)
        ),
        array(
            'key' => 'load_via_post_tax_term',
            'label' => __('On singular entries assigned to selected taxonomy terms:', 'wp-asset-clean-up'),
            'control' => 'text',
            'control_data' => array('placeholder' => __('Search categories, tags, product terms…', 'wp-asset-clean-up'))
        )
    ),
    'archives' => array(
        array(
            'key' => 'load_via_tax_term',
            'label' => __('On specific taxonomy term archive pages:', 'wp-asset-clean-up'),
            'control' => 'text',
            'control_data' => array('placeholder' => __('Search taxonomy terms…', 'wp-asset-clean-up'))
        ),
        array(
            'key' => 'load_via_tax',
            'label' => __('On all term archive pages from these taxonomies:', 'wp-asset-clean-up'),
            'control' => 'select',
            'control_data' => array('options' => $wpacuTaxonomies)
        ),
        array(
            'key' => 'load_via_archive',
            'label' => __('On these archive and listing page types:', 'wp-asset-clean-up'),
            'control' => 'select',
            'control_data' => array('options' => $wpacuArchiveTypes)
        ),
        array(
            'key' => 'load_via_author_archive',
            'label' => __('On the archive pages of these authors:', 'wp-asset-clean-up'),
            'control' => 'text',
            'control_data' => array('placeholder' => __('Search authors…', 'wp-asset-clean-up'))
        )
    ),
    'conditions' => array(
        array(
            'key' => 'load_via_regex',
            'label' => __('If the request URI matches any of these rules:', 'wp-asset-clean-up'),
            'control' => 'textarea',
            'control_data' => array('placeholder' => "/checkout/*\n#^/members/#")
        ),
        array('key' => 'load_if_logged_in', 'label' => __('If the user is logged in', 'wp-asset-clean-up')),
        array(
            'key' => 'load_logged_in_via_role',
            'label' => __('If the logged-in user has any of these roles:', 'wp-asset-clean-up'),
            'control' => 'select',
            'control_data' => array('options' => $wpacuRoles)
        )
    )
);
?>
<tr class="wpacu-pm-plugin-row"
    data-wpacu-layout-plugin-row
    data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>">
    <td class="wpacu_plugin_icon" width="46">
        <?php if (isset($wpacuLitePageData['plugins_icons'][$wpacuPluginDir])) { ?>
            <img width="44"
                 height="44"
                 alt=""
                 src="<?php echo esc_url($wpacuLitePageData['plugins_icons'][$wpacuPluginDir]); ?>" />
        <?php } else { ?>
            <div><span class="dashicons dashicons-admin-plugins"></span></div>
        <?php } ?>
    </td>
    <td class="wpacu_plugin_details"
        data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
        data-wpacu-status-area="<?php echo esc_attr($wpacuPluginAreaState); ?>"
        id="wpacu-front-manage-<?php echo esc_attr($wpacuPluginId); ?>">
        <div class="wpacu_plugin_details_top_area wpacu-pm-plugin-header wpacu-pm-plugin-header--compact-grid">
            <div class="wpacu_plugin_expand_contract_area">
                <button type="button"
                        class="wpacu_wp_button wpacu_wp_button_secondary"
                        data-wpacu-lite-preview-allow="1"
                        aria-expanded="<?php echo $wpacuPluginAreaState === 'expanded' ? 'true' : 'false'; ?>"
                        aria-label="<?php echo esc_attr__('Expand or collapse this plugin', 'wp-asset-clean-up'); ?>">
                    <span class="dashicons" aria-hidden="true"></span>
                </button>
            </div>

            <div class="wpacu-pm-plugin-identity">
                <div class="wpacu-pm-plugin-title-line">
                    <span class="wpacu_plugin_title" data-wpacu-plugin-search-highlight><?php echo esc_html($wpacuPluginData['title']); ?></span>

                    <?php if ( ! empty($wpacuPluginData['network_activated'])) { ?>
                        <span title="<?php echo esc_attr__('Network Activated', 'wp-asset-clean-up'); ?>"
                              class="dashicons dashicons-admin-multisite wpacu-tooltip wpacu-pm-network-activated"></span>
                    <?php } ?>

                    <span class="wpacu-pm-plugin-statuses"
                          data-wpacu-layout-plugin-statuses
                          aria-live="polite">
                        <span data-wpacu-layout-always-loaded-status<?php echo $wpacuIsAlwaysLoaded ? '' : ' hidden'; ?>><?php
                            echo PluginsManagerPreview::getAlwaysLoadedStatus();
                        ?></span>

                        <span class="wpacu-pm-status-badge wpacu-pm-status-badge--unload"
                              data-wpacu-layout-header-count="unload"
                              data-wpacu-count-singular="<?php echo esc_attr__('unload rule', 'wp-asset-clean-up'); ?>"
                              data-wpacu-count-plural="<?php echo esc_attr__('unload rules', 'wp-asset-clean-up'); ?>"
                            <?php echo $wpacuIsAlwaysLoaded ? 'hidden' : ''; ?>><?php
                            printf(
                                esc_html(_n('%d unload rule', '%d unload rules', $wpacuPluginView['unload_rules_count'], 'wp-asset-clean-up')),
                                (int)$wpacuPluginView['unload_rules_count']
                            );
                        ?></span>

                        <span class="wpacu-pm-status-badge wpacu-pm-status-badge--load"
                              data-wpacu-layout-header-count="load"
                              data-wpacu-count-singular="<?php echo esc_attr__('exception', 'wp-asset-clean-up'); ?>"
                              data-wpacu-count-plural="<?php echo esc_attr__('exceptions', 'wp-asset-clean-up'); ?>"
                            <?php echo $wpacuIsAlwaysLoaded ? 'hidden' : ''; ?>><?php
                            printf(
                                esc_html(_n('%d exception', '%d exceptions', $wpacuPluginView['load_exceptions_count'], 'wp-asset-clean-up')),
                                (int)$wpacuPluginView['load_exceptions_count']
                            );
                        ?></span>
                    </span>
                </div>

                <div class="wpacu_plugin_path" data-wpacu-plugin-search-highlight><?php echo esc_html($wpacuPluginPath); ?></div>
            </div>
        </div>

        <div class="wpacu-pm-plugin-editor"
             data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>">
            <div data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
                 class="wpacu_plugin_unload_rules_options_wrap wpacu-pm-unload-rules">
                <section class="wpacu-pm-rules-panel wpacu-pm-rules-panel--unload"
                         aria-labelledby="wpacu-pm-unload-title-<?php echo esc_attr($wpacuPluginId); ?>">
                    <header class="wpacu-pm-panel-header">
                        <div class="wpacu-pm-panel-heading">
                            <h4 id="wpacu-pm-unload-title-<?php echo esc_attr($wpacuPluginId); ?>">
                                <strong><?php esc_html_e('Unload this plugin', 'wp-asset-clean-up'); ?></strong>
                                <?php esc_html_e('in the front-end', 'wp-asset-clean-up'); ?>
                            </h4>
                            <p><?php esc_html_e('Choose the front-end requests where WordPress should skip loading this plugin.', 'wp-asset-clean-up'); ?></p>
                        </div>
                        <span class="wpacu-pm-count-badge wpacu-pm-count-badge--unload"
                              data-wpacu-layout-total-count="unload"
                              data-wpacu-count-singular="<?php echo esc_attr__('active rule', 'wp-asset-clean-up'); ?>"
                              data-wpacu-count-plural="<?php echo esc_attr__('active rules', 'wp-asset-clean-up'); ?>"><?php
                            printf(
                                esc_html(_n('%d active rule', '%d active rules', $wpacuPluginView['unload_rules_count'], 'wp-asset-clean-up')),
                                (int)$wpacuPluginView['unload_rules_count']
                            );
                        ?></span>
                    </header>

                    <?php
                    $wpacuRenderRuleCard(
                        'unload',
                        'site',
                        __('Site scope', 'wp-asset-clean-up'),
                        __('Broad rules that target the entire site or only the homepage.', 'wp-asset-clean-up'),
                        $wpacuUnloadRules['site'],
                        true
                    );
                    ?>

                    <div class="wpacu-pm-rule-grid">
                        <?php
                        $wpacuRenderRuleCard(
                            'unload',
                            'singular',
                            __('Posts, pages and individual content', 'wp-asset-clean-up'),
                            __('Individual URLs that display one post, page, product or another content item.', 'wp-asset-clean-up'),
                            $wpacuUnloadRules['singular'],
                            false
                        );
                        $wpacuRenderRuleCard(
                            'unload',
                            'archives',
                            __('Archives and listings', 'wp-asset-clean-up'),
                            __('Taxonomy, author, date, search and custom post type archive requests.', 'wp-asset-clean-up'),
                            $wpacuUnloadRules['archives'],
                            false
                        );
                        $wpacuRenderRuleCard(
                            'unload',
                            'conditions',
                            __('Request and user conditions', 'wp-asset-clean-up'),
                            __('Rules based on the requested URI or the current visitor’s login status and role.', 'wp-asset-clean-up'),
                            $wpacuUnloadRules['conditions'],
                            false
                        );
                        ?>
                    </div>
                </section>
            </div>

            <?php
            $wpacuExceptionsOpen = $wpacuPluginView['load_exceptions_count'] > 0;
            $wpacuAccordionId = 'wpacu-pm-load-exceptions-' . $wpacuPluginId;
            ?>
            <div data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
                 data-wpacu-layout-exceptions
                 class="wpacu_plugin_load_exception_options_wrap wpacu-pm-load-exceptions wpacu-pm-load-exceptions--lite-preview<?php echo $wpacuExceptionsOpen ? ' is-open' : ''; ?>">
                <button type="button"
                        class="wpacu-pm-exceptions-toggle"
                        data-wpacu-layout-exceptions-toggle
                        data-wpacu-lite-preview-allow="1"
                        aria-expanded="<?php echo $wpacuExceptionsOpen ? 'true' : 'false'; ?>"
                        aria-controls="<?php echo esc_attr($wpacuAccordionId); ?>">
                    <span class="wpacu-pm-exceptions-toggle-copy">
                        <span class="wpacu-pm-exceptions-title-row">
                            <span class="wpacu-pm-exceptions-title"><?php esc_html_e('Load Exceptions', 'wp-asset-clean-up'); ?></span>
                            <span class="wpacu-pm-count-badge wpacu-pm-count-badge--load"
                                  data-wpacu-layout-total-count="load"
                                  data-wpacu-count-singular="<?php echo esc_attr__('active exception', 'wp-asset-clean-up'); ?>"
                                  data-wpacu-count-plural="<?php echo esc_attr__('active exceptions', 'wp-asset-clean-up'); ?>"><?php
                                printf(
                                    esc_html(_n('%d active exception', '%d active exceptions', $wpacuPluginView['load_exceptions_count'], 'wp-asset-clean-up')),
                                    (int)$wpacuPluginView['load_exceptions_count']
                                );
                            ?></span>
                        </span>
                        <span class="wpacu-pm-exceptions-description"><?php
                            esc_html_e('Optional overrides. A matching exception always loads the plugin when an unload rule also matches.', 'wp-asset-clean-up');
                        ?></span>
                    </span>
                    <span class="dashicons dashicons-arrow-right-alt2 wpacu-pm-exceptions-chevron" aria-hidden="true"></span>
                </button>

                <div id="<?php echo esc_attr($wpacuAccordionId); ?>"
                     class="wpacu-pm-exceptions-content"
                     aria-hidden="<?php echo $wpacuExceptionsOpen ? 'false' : 'true'; ?>">
                    <section class="wpacu-pm-rules-panel wpacu-pm-rules-panel--load"
                             aria-label="<?php echo esc_attr__('Load exception rules', 'wp-asset-clean-up'); ?>">
                        <?php
                        $wpacuRenderRuleCard(
                            'load',
                            'site',
                            __('Site scope', 'wp-asset-clean-up'),
                            '',
                            $wpacuLoadRules['site'],
                            true
                        );
                        ?>

                        <div class="wpacu-pm-rule-grid">
                            <?php
                            $wpacuRenderRuleCard(
                                'load',
                                'singular',
                                __('Posts, pages and individual content', 'wp-asset-clean-up'),
                                __('Individual URLs that display one post, page, product or another content item.', 'wp-asset-clean-up'),
                                $wpacuLoadRules['singular'],
                                false
                            );
                            $wpacuRenderRuleCard(
                                'load',
                                'archives',
                                __('Archives and listings', 'wp-asset-clean-up'),
                                __('Taxonomy, author, date, search and custom post type archive requests.', 'wp-asset-clean-up'),
                                $wpacuLoadRules['archives'],
                                false
                            );
                            $wpacuRenderRuleCard(
                                'load',
                                'conditions',
                                __('Request and user conditions', 'wp-asset-clean-up'),
                                __('Rules based on the requested URI or the current visitor’s login status and role.', 'wp-asset-clean-up'),
                                $wpacuLoadRules['conditions'],
                                false
                            );
                            ?>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </td>
</tr>
