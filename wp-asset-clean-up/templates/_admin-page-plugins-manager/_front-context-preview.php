<?php
use WpAssetCleanUpLite\FrontendPluginsPreviewData as PreviewData;
use WpAssetCleanUpLite\Admin\ProPreview;
if (!isset($data, $context)) { exit; }
$plugins              = $data['active_plugins'];
$rules                = $data['plugins_preview_rules'];
$totalPlugins         = count($plugins);
$pluginsWithSavedRules = 0;
$isDashboardPreview   = false;
foreach ($plugins as $plugin) {
    if (!empty($rules[$plugin['path']]['status'])) { $pluginsWithSavedRules++; }
}
?>
<div class="wpacu-lite-pm wpacu-lite-pm-context-preview" data-wpacu-lite-context-preview data-wpacu-pro-alert="<?php echo esc_attr(wp_json_encode(ProPreview::getAlertSettings('frontend_plugins_manager'))); ?>">
    <?php include __DIR__ . '/_preview-overview.php'; ?>
    <section class="wpacu-pm-page" aria-label="<?php esc_attr_e('Plugins by page', 'wp-asset-clean-up'); ?>">
        <?php
        include WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/plugins-author-context.php';
        include WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/plugins-search-context.php';
        include WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/plugins-taxonomy-context.php';
        $wpacuPageSummary = array(
            'frontend' => true,
            'is_homepage' => !empty($context['is_homepage']),
            'is_blog_page' => is_home() && !is_front_page(),
            'url' => $context['url'],
            'post_id' => $context['post_id'],
            'post_type' => $context['post_type'],
            'title' => $context['label']
        );
        include WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/page-summary.php';
        include WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/page-template.php';
        ?>
        <p class="wpacu-pm-page-help"><?php esc_html_e('Preview the plugin rules for this page. Choose page, bulk or conditional unload rules in Pro; load exceptions take priority over any unload rule.', 'wp-asset-clean-up'); ?></p>
        <div class="wpacu-pm-frontend-guidance">
            <p><?php esc_html_e('In Pro, use Test Mode to try plugin rules with an administrator or Asset CleanUp manager account before applying them to visitors.', 'wp-asset-clean-up'); ?></p>
            <p class="wpacu-pm-plugin-caution"><?php esc_html_e('Unloading a plugin stops all of its functionality on matching requests, including its CSS and JavaScript. Keep it loaded wherever its features or another plugin depend on it.', 'wp-asset-clean-up'); ?></p>
            <p class="wpacu-pm-save-hint"><?php esc_html_e('* Read-only preview: Lite does not save or apply plugin rules.', 'wp-asset-clean-up'); ?></p>
        </div>
        <div class="wpacu-pm-page-list-toolbar">
            <label class="wpacu-pm-page-list-layout"><?php esc_html_e('Plugins’ List Layout:', 'wp-asset-clean-up'); ?>
                <select data-wpacu-preview-group>
                    <option value="all" <?php selected($data['plugins_list_layout'], 'all'); ?>><?php esc_html_e('All plugins in one list', 'wp-asset-clean-up'); ?></option>
                    <option value="by-unload-rules" <?php selected($data['plugins_list_layout'], 'by-unload-rules'); ?>><?php esc_html_e('Grouped by having at least one unload rule & no unload rules', 'wp-asset-clean-up'); ?></option>
                </select>
            </label>
            <label class="wpacu-pm-page-filter"><?php esc_html_e('Filter plugins', 'wp-asset-clean-up'); ?><input type="search" data-wpacu-preview-filter placeholder="<?php esc_attr_e('Plugin name or path…', 'wp-asset-clean-up'); ?>"></label>
            <div class="wpacu-pm-page-expansion-actions">
                <button type="button" class="wpacu_wp_button wpacu_wp_button_secondary" data-wpacu-preview-expand><span class="dashicons dashicons-editor-expand" aria-hidden="true"></span> <?php esc_html_e('Expand All', 'wp-asset-clean-up'); ?></button>
                <button type="button" class="wpacu_wp_button wpacu_wp_button_secondary" data-wpacu-preview-contract><span class="dashicons dashicons-editor-contract" aria-hidden="true"></span> <?php esc_html_e('Contract All', 'wp-asset-clean-up'); ?></button>
            </div>
        </div>
        <div class="wpacu-pm-page-rules">
            <div class="wpacu-pm-page-list" data-wpacu-preview-plugins>
            <?php foreach (array('with' => __('Plugins with at least one unload rule', 'wp-asset-clean-up'), 'without' => __('Plugins without unload rules', 'wp-asset-clean-up')) as $group => $label) { ?>
                <details class="wpacu-pm-page-rule-group" data-wpacu-preview-rule-group="<?php echo esc_attr($group); ?>" open hidden>
                    <summary><?php echo esc_html($label); ?> <span data-wpacu-preview-group-count></span></summary>
                    <div data-wpacu-group-plugins></div>
                </details>
                <div class="wpacu-pm-page-empty-group" data-wpacu-preview-group-empty hidden><span class="dashicons dashicons-info" aria-hidden="true"></span><span><?php echo $group === 'with'
                    ? esc_html__('No plugins have unload rules for this page. All listed plugins are in the group without unload rules.', 'wp-asset-clean-up')
                    : esc_html__('No plugins are in the group without unload rules. All listed plugins have unload rules for this page.', 'wp-asset-clean-up'); ?></span></div>
            <?php } ?>
            <?php
            $definitions = PreviewData::controls($context);
            foreach ($plugins as $index => $plugin) {
                $path          = $plugin['path'];
                $rule          = isset($rules[$path]) && is_array($rules[$path]) ? $rules[$path] : array();
                $counts        = PreviewData::counts($rule, $context);
                $additional    = PreviewData::additionalRules($rule, $context);
                $pluginDir     = explode('/', $path);
                $pluginRulesId = 'wpacu-preview-plugin-' . md5($path);
                $contracted    = array_key_exists($path, $data['plugins_contracted_list']);
                ?>
                <article class="wpacu-pm-page-plugin<?php echo $index % 2 ? ' is-even' : ''; ?><?php echo $index === 0 ? ' is-first-row' : ''; ?><?php echo $contracted ? ' is-contracted' : ''; ?>" data-plugin-path="<?php echo esc_attr($path); ?>" data-has-unload="<?php echo $counts['unload'] ? '1' : '0'; ?>" data-search="<?php echo esc_attr(strtolower(wp_strip_all_tags($plugin['title']) . ' ' . $path)); ?>">
                    <div class="wpacu-pm-page-plugin-title">
                        <span class="wpacu-pm-page-plugin-icon" aria-hidden="true"><span class="dashicons dashicons-admin-plugins"></span>
                        <?php if (!empty($data['plugins_icons'][$pluginDir[0]])) { ?><img width="44" height="44" alt="" src="<?php echo esc_url($data['plugins_icons'][$pluginDir[0]]); ?>"><?php } ?></span>
                        <div><div class="wpacu-pm-page-plugin-title-line"><h3><span class="wpacu-pm-page-plugin-name"><?php echo esc_html($plugin['title']); ?></span><?php if (!empty($plugin['version'])) { ?> <span class="wpacu-pm-plugin-version">v<?php echo esc_html($plugin['version']); ?></span><?php } ?></h3>
                            <?php if (!$counts['unload'] && !$counts['load']) { ?><span class="wpacu-pm-page-always-loaded"><span class="wpacu-pm-page-loaded-dot" aria-hidden="true"></span><?php esc_html_e('Loaded', 'wp-asset-clean-up'); ?></span><?php } ?>
                            <?php if ($counts['unload']) { ?><span class="wpacu-pm-page-count wpacu-pm-page-count--unload"><?php echo esc_html(sprintf(_n('%d unload rule', '%d unload rules', $counts['unload'], 'wp-asset-clean-up'), $counts['unload'])); ?></span><?php } ?>
                            <?php if ($counts['load']) { ?><span class="wpacu-pm-page-count wpacu-pm-page-count--load" data-count="<?php echo (int)$counts['load']; ?>"><?php echo esc_html(sprintf(_n('%d exception', '%d exceptions', $counts['load'], 'wp-asset-clean-up'), $counts['load'])); ?></span><?php } ?>
                        </div><code><?php echo esc_html($path); ?></code><?php if (!empty($plugin['network_activated'])) { ?><small><?php esc_html_e('Network activated', 'wp-asset-clean-up'); ?></small><?php } ?></div>
                        <button type="button" class="wpacu_wp_button wpacu_wp_button_secondary wpacu-pm-page-plugin-toggle" data-wpacu-preview-toggle aria-expanded="<?php echo $contracted ? 'false' : 'true'; ?>" aria-controls="<?php echo esc_attr($pluginRulesId); ?>" aria-label="<?php echo esc_attr(sprintf(__('Expand or contract rules for %s', 'wp-asset-clean-up'), wp_strip_all_tags($plugin['title']))); ?>"><span class="dashicons <?php echo $contracted ? 'dashicons-plus-alt2' : 'dashicons-minus'; ?>" aria-hidden="true"></span></button>
                    </div>
                    <div id="<?php echo esc_attr($pluginRulesId); ?>" class="wpacu-pm-page-plugin-body"<?php if ($contracted) { ?> hidden<?php } ?>>
                        <fieldset class="wpacu-pm-page-rule-options"><legend class="screen-reader-text"><?php esc_html_e('Unload rules', 'wp-asset-clean-up'); ?></legend>
                            <?php foreach ($definitions as $key => $definition) { if (strpos($key, 'unload_') !== 0) { continue; } include __DIR__ . '/_front-context-preview-rule.php'; } ?>
                        </fieldset>
                        <div class="wpacu-pm-page-exceptions-wrap"><details class="wpacu-pm-page-exceptions"<?php if ($counts['load']) { ?> open<?php } ?>>
                            <summary class="wpacu-pm-page-exceptions-toggle"><span class="wpacu-pm-page-exceptions-copy"><span class="wpacu-pm-page-exceptions-title-row"><strong><?php esc_html_e('Load Exceptions', 'wp-asset-clean-up'); ?></strong><span class="wpacu-pm-page-count wpacu-pm-page-count--load" data-count="<?php echo (int)$counts['load']; ?>"><?php echo esc_html(sprintf(_n('%d active exception', '%d active exceptions', $counts['load'], 'wp-asset-clean-up'), $counts['load'])); ?></span></span><span class="wpacu-pm-page-exceptions-description"><?php esc_html_e('Optional overrides. A matching exception always loads the plugin when an unload rule also matches.', 'wp-asset-clean-up'); ?></span></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></summary>
                            <div class="wpacu-pm-page-exceptions-content"><fieldset class="wpacu-pm-page-rule-options"><legend class="screen-reader-text"><?php esc_html_e('Load exception rules', 'wp-asset-clean-up'); ?></legend>
                                <?php foreach ($definitions as $key => $definition) { if (strpos($key, 'load_') !== 0) { continue; } include __DIR__ . '/_front-context-preview-rule.php'; } ?>
                            </fieldset></div>
                        </details></div>
                        <?php if ($additional) { ?><div class="wpacu-pm-page-plugin-details"><ul><?php foreach ($additional as $label) { ?><li><?php echo esc_html($label); ?></li><?php } ?></ul></div><?php } ?>
                    </div>
                </article>
            <?php } ?>
            </div>
            <p class="wpacu-pm-page-no-plugins" data-wpacu-preview-empty hidden><?php esc_html_e('No plugins match this filter.', 'wp-asset-clean-up'); ?></p>
            <div id="wpacu-update-front-settings-area" class="wpacu-pm-page-save">
                <div class="wpacu-save-actions">
                    <button type="button" class="button button-primary wpacu-lite-pm-locked-save" disabled aria-disabled="true"><span class="dashicons dashicons-lock" aria-hidden="true"></span> <?php esc_html_e('Save Plugin Rules', 'wp-asset-clean-up'); ?></button>
                </div>
                <div class="wpacu-save-summary" role="status">
                    <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                    <span><?php esc_html_e('Read-only Pro preview', 'wp-asset-clean-up'); ?></span>
                </div>
                <div class="wpacu-save-copy">
                    <strong><?php esc_html_e('Plugin rules are available in Pro', 'wp-asset-clean-up'); ?></strong>
                    <a href="<?php echo esc_url(ProPreview::getUpgradeUrl('frontend_plugins_by_page')); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(apply_filters('wpacu_internal_upgrade_label', __('Unlock plugin rules with Pro', 'wp-asset-clean-up'))); ?></a>
                </div>
            </div>
        </div>
    </section>
</div>
