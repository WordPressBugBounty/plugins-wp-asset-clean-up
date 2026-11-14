<?php
/* Alternative replacement details belong to the handle column, not unload rules. */
if ( ! isset($handleData['alternative_assets'])) {
    exit;
}

use WpAssetCleanUp\OptimiseAssets\AlternativeAssets;

$canManageAlternatives      = \WpAssetCleanUp\Menu::userCanManageAlternativeAssets();
$alternativesGloballyPaused = ! AlternativeAssets::isEnabled();
foreach ($handleData['alternative_assets'] as $alternativeRule) {
    if ( ! AlternativeAssets::supportsAssetType($alternativeRule['type'])) {
        continue;
    }
    $alternativeEnabled = ! empty($alternativeRule['enabled']);
    $alternativeTargets = AlternativeAssets::getRuleTargets($alternativeRule);
    $alternativeEditUrl = add_query_arg(array(
        'page' => WPACU_PLUGIN_ID . '_assets_manager',
        'wpacu_sub_page' => 'manage_alternative_assets',
        'asset_type' => $alternativeRule['type'],
        'edit_rule' => (int)$alternativeRule['id']
    ), admin_url('admin.php'));
    ?>
    <div class="wpacu-overview-alternative-asset<?php echo $alternativesGloballyPaused ? ' is-globally-paused' : ''; ?>" data-wpacu-rule-state="<?php echo $alternativeEnabled ? 'active' : 'inactive'; ?>">
        <div class="wpacu-overview-alternative-asset__heading">
            <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
            <strong><?php echo $alternativeRule['type'] === 'css' ? esc_html__('Alternative CSS', 'wp-asset-clean-up') : esc_html__('Alternative JavaScript', 'wp-asset-clean-up'); ?></strong>
            <span class="wpacu-overview-alternative-asset__status"><?php echo $alternativesGloballyPaused ? esc_html__('Paused', 'wp-asset-clean-up') : ($alternativeEnabled ? esc_html__('ON', 'wp-asset-clean-up') : esc_html__('OFF', 'wp-asset-clean-up')); ?></span>
            <?php if ($canManageAlternatives) { ?>
                <a href="<?php echo esc_url($alternativeEditUrl); ?>" aria-label="<?php echo esc_attr(sprintf(__('Edit alternative #%d for %s', 'wp-asset-clean-up'), (int)$alternativeRule['id'], $alternativeRule['handle'])); ?>"><?php esc_html_e('Edit', 'wp-asset-clean-up'); ?></a>
            <?php } ?>
        </div>
        <p class="wpacu-overview-alternative-asset__description"><?php
            if ($alternativesGloballyPaused) {
                esc_html_e('Alternative Assets is paused globally. These saved replacements are not applied.', 'wp-asset-clean-up');
            } else {
                echo $alternativeEnabled
                ? esc_html__('Replaces the original asset when any selected condition matches, provided this handle is loaded.', 'wp-asset-clean-up')
                : esc_html__('Disabled. These saved replacement conditions do not apply.', 'wp-asset-clean-up');
            }
        ?></p>
        <ul class="wpacu-overview-alternative-asset__conditions">
            <?php foreach ($alternativeTargets as $alternativeTarget => $alternativeValue) {
                $alternativeScope = $alternativeTarget === 'everywhere' ? 'sitewide'
                    : (in_array($alternativeTarget, array('homepage', 'posts_page', 'post_ids', 'taxonomy_term', 'author'), true) ? 'page' : 'bulk');
                ?>
                <li data-wpacu-rule-scope="<?php echo esc_attr($alternativeScope); ?>"><?php echo esc_html(AlternativeAssets::getTargetLabel(array('targets' => array($alternativeTarget => $alternativeValue)))); ?></li>
            <?php } ?>
        </ul>
        <small><?php echo esc_html(sprintf(__('Priority %d', 'wp-asset-clean-up'), (int)$alternativeRule['priority'])); ?></small>
        <?php if ($canManageAlternatives && \WpAssetCleanUp\Admin\Overview::isEditMode()) { ?>
            <p class="wpacu-overview-alternative-asset__description"><?php
                echo \WpAssetCleanUp\Admin\OverviewEdit::renderMaybeEditSettingChangesWrapOutputRule(
                    esc_html__('Clear this alternative and its saved code', 'wp-asset-clean-up'),
                    array('handle' => $handleData['handle'], 'asset_type' => $handleData['asset_type'], 'no_wrap' => true),
                    'alternative_asset',
                    (int)$alternativeRule['id']
                );
            ?></p>
        <?php } ?>
    </div>
    <?php
}
