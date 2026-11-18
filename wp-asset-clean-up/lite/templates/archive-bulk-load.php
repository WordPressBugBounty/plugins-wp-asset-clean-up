<?php
if ( ! defined('ABSPATH') || ! isset($term, $obj, $assetTypeS) ) {
    exit;
}

$type       = $assetTypeS === 'style' ? 'styles' : 'scripts';
$exceptions = isset($allExceptions[$term->taxonomy][$type]) && is_array($allExceptions[$term->taxonomy][$type]) ? $allExceptions[$term->taxonomy][$type] : array();
$scope      = $term->taxonomy === 'category' ? __('category', 'wp-asset-clean-up') : __('tag', 'wp-asset-clean-up');
?>
<li><label><input type="checkbox"
    class="wpacu_load_it_option_tax_type wpacu_<?php echo esc_attr($assetTypeS); ?> wpacu_load_exception"
    id="wpacu_load_it_option_tax_type_<?php echo esc_attr($assetTypeS . '_' . $obj->handle); ?>"
    data-handle-for="<?php echo esc_attr($assetTypeS); ?>"
    data-handle="<?php echo esc_attr($obj->handle); ?>"
    name="<?php echo esc_attr(WPACU_FORM_ASSETS_POST_KEY); ?>[load_it_tax_type][<?php echo esc_attr($type); ?>][]"
    value="<?php echo esc_attr($obj->handle); ?>" <?php checked(in_array($obj->handle, $exceptions, true)); ?> />
    <?php echo esc_html(sprintf(__('On all %s archive pages', 'wp-asset-clean-up'), $scope)); ?></label></li>
