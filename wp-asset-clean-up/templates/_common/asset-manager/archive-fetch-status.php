<?php
if (! defined('ABSPATH')) {
    exit;
}

$wpacuArchiveIsPro = defined('WPACU_PRO_PLUGIN_VERSION');
?>
<div id="wpacu_meta_box_content"<?php echo $wpacuArchiveIsPro ? '' : ' class="wpacu-lite-pro-preview-dynamic"'; ?>>
    <?php if ($data['wpacu_settings']['dom_get_type'] === 'direct') { ?>
        <div id="wpacu-list-step-default-status" style="display: none;"><img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" align="top" width="20" height="20" alt="" />&nbsp; <?php esc_html_e('Please wait...', 'wp-asset-clean-up'); ?></div>
        <div id="wpacu-list-step-completed-status" style="display: none;"><span style="color: green;" class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Completed', 'wp-asset-clean-up'); ?></div>
        <div>
            <ul class="wpacu_meta_box_content_fetch_steps">
                <li id="wpacu-fetch-list-step-1-wrap"><strong><?php esc_html_e('Step 1', 'wp-asset-clean-up'); ?></strong>: <?php esc_html_e('Fetch the assets from the targeted page...', 'wp-asset-clean-up'); ?> <span id="wpacu-fetch-list-step-1-status"><img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" align="top" width="20" height="20" alt="" />&nbsp; <?php esc_html_e('Please wait...', 'wp-asset-clean-up'); ?></span></li>
                <li id="wpacu-fetch-list-step-2-wrap"><strong><?php esc_html_e('Step 2', 'wp-asset-clean-up'); ?></strong>: <?php esc_html_e('Build and display the fetched assets list...', 'wp-asset-clean-up'); ?> <span id="wpacu-fetch-list-step-2-status"></span></li>
            </ul>
        </div>
    <?php } else { ?>
        <img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" align="top" width="20" height="20" alt="" />&nbsp;
        <?php esc_html_e('Retrieving the loaded scripts and styles for the selected archive page. Please wait...', 'wp-asset-clean-up'); ?>
    <?php } ?>

    <?php if ($wpacuArchiveIsPro) { ?>
        <p><?php echo sprintf(
                wp_kses(__('If fetching the page takes too long and the assets should have loaded by now, you can also %smanage the assets in the front-end%s.', 'wp-asset-clean-up'), array('a' => array('href' => array()))),
                '<a href="' . esc_url($displayUrl) . '#wpacu_wrap_assets">',
                '</a>'
            ); ?></p>
    <?php } else { ?>
        <p><?php esc_html_e('The list will remain fully readable, while every rule-changing control is kept read-only in Lite.', 'wp-asset-clean-up'); ?></p>
    <?php } ?>
</div>
<?php unset($wpacuArchiveIsPro); ?>
