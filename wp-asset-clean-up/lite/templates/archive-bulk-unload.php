<?php
if ( ! defined('ABSPATH') || ! isset($term, $obj, $assetTypeS) ) {
    exit;
}

$type     = $assetTypeS === 'style' ? 'styles' : 'scripts';
$unloaded = isset($data['bulk_unloaded']['taxonomy'][$type]) ? $data['bulk_unloaded']['taxonomy'][$type] : array();
$hasRule  = in_array($obj->handle, $unloaded, true);
$scope    = $term->taxonomy === 'category' ? __('category', 'wp-asset-clean-up') : __('tag', 'wp-asset-clean-up');
?>
<div class="wpacu_asset_options_wrap">
    <?php if ($hasRule) { ?>
        <p><strong style="color: #d54e21;"><?php echo sprintf(esc_html__('This asset is unloaded on all %s archive pages.', 'wp-asset-clean-up'), '<u>' . esc_html($scope) . '</u>'); ?></strong></p>
    <?php } ?>
    <ul class="wpacu_asset_options">
        <?php if ($hasRule) { ?>
            <?php foreach (array('default' => __('Keep bulk rule', 'wp-asset-clean-up'), 'remove' => __('Remove bulk rule', 'wp-asset-clean-up')) as $value => $label) { ?>
                <li><label><input type="radio" data-handle="<?php echo esc_attr($obj->handle); ?>"
                    class="wpacu_bulk_option wpacu_<?php echo esc_attr($assetTypeS); ?> wpacu_<?php echo $value === 'remove' ? 'remove' : 'keep'; ?>_bulk_rule"
                    name="wpacu_options_taxonomy_<?php echo esc_attr($type); ?>[<?php echo esc_attr($obj->handle); ?>]"
                    value="<?php echo esc_attr($value); ?>" <?php checked($value, 'default'); ?> /> <?php echo esc_html($label); ?></label></li>
            <?php } ?>
        <?php } else { ?>
            <li><label><input type="checkbox" data-handle="<?php echo esc_attr($obj->handle); ?>" data-handle-for="<?php echo esc_attr($assetTypeS); ?>"
                class="wpacu_bulk_unload wpacu_unload_rule_input wpacu_taxonomy_unload wpacu_taxonomy_<?php echo esc_attr($assetTypeS); ?>"
                id="wpacu_bulk_unload_taxonomy_<?php echo esc_attr($assetTypeS . '_' . $obj->handle); ?>"
                name="wpacu_bulk_unload_<?php echo esc_attr($type); ?>[taxonomy][<?php echo esc_attr($term->taxonomy); ?>][]"
                value="<?php echo esc_attr($obj->handle); ?>" />
                <?php echo esc_html(sprintf(__('Unload on all %s archive pages', 'wp-asset-clean-up'), $scope)); ?></label></li>
        <?php } ?>
    </ul>
</div>
