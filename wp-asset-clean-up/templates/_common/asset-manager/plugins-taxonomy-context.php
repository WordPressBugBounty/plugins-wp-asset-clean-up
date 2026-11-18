<?php
if ( ! defined('ABSPATH') || ! isset($context['taxonomy'])
    || ! in_array($context['taxonomy'], array('category', 'post_tag'), true) ) {
    return;
}

$wpacuContextIsTag = $context['taxonomy'] === 'post_tag';
$wpacuContextIcon  = $wpacuContextIsTag ? 'dashicons-tag' : 'dashicons-category';
$wpacuContextText  = $wpacuContextIsTag
    ? sprintf(__('This is a WordPress "%s" <u>Tag</u> archive page.', 'wp-asset-clean-up'), esc_html(isset($context['term_name']) ? $context['term_name'] : ''))
    : __('This is a WordPress "category" <u>Taxonomy</u> page.', 'wp-asset-clean-up');
?>
<p class="wpacu-pm-taxonomy-context"><strong><span style="color: #0f6cab;" class="dashicons <?php echo esc_attr($wpacuContextIcon); ?>" aria-hidden="true"></span>
    <?php echo wp_kses($wpacuContextText, array('u' => array())); ?>
    <?php echo defined('WPACU_PRO_PLUGIN_VERSION')
        ? esc_html__('The changes will also be applied on its pagination pages too.', 'wp-asset-clean-up')
        : esc_html__('In Pro, rules for this archive also apply to its pagination pages.', 'wp-asset-clean-up'); ?>
</strong></p>
<?php
unset($wpacuContextIsTag, $wpacuContextIcon, $wpacuContextText);
