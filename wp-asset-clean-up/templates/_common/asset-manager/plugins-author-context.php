<?php
if (!isset($context) || empty($context['author_id'])) { return; }
$wpacuContextAuthor = get_userdata((int)$context['author_id']);
if (!$wpacuContextAuthor) { return; }
?>
<p class="wpacu-pm-author-context"><strong><span style="color: #0f6cab;" class="dashicons dashicons-admin-users" aria-hidden="true"></span>
    <?php echo esc_html(sprintf(__('This is a WordPress "%s" Author archive page.', 'wp-asset-clean-up'), $wpacuContextAuthor->display_name)); ?>
    <?php echo defined('WPACU_PRO_PLUGIN_VERSION')
        ? esc_html__('The changes will also be applied on its pagination pages too.', 'wp-asset-clean-up')
        : esc_html__('In Pro, rules for this author also apply to the pagination pages of this archive.', 'wp-asset-clean-up'); ?>
</strong></p>
