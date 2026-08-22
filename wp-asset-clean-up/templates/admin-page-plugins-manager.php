<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\AssetsManager;
use WpAssetCleanUpLite\Admin\ProPreview;

if (! isset($data)) {
	exit;
}

include_once __DIR__ . '/_top-area.php';

do_action('wpacu_admin_notices');

if ( ! AssetsManager::instance()->currentUserCanViewAssetsList() ) {
	?>
    <div class="wpacu-error" style="padding: 10px;">
		<?php echo sprintf(esc_html__('Only the administrators listed here can manage plugins: %s"Settings" &#10141; "Plugin Usage Preferences" &#10141; "Allow managing assets to:"%s. If you believe you should have access to managing plugins, you can add yourself to that list.', 'wp-asset-clean-up'), '<a target="_blank" href="'.esc_url(admin_url('admin.php?page=wpassetcleanup_settings&wpacu_selected_tab_area=wpacu-setting-plugin-usage-settings')).'">', '</a>'); ?></div>
	<?php
	return;
}
?>
<?php
ProPreview::renderNotice(
    __('Plugins Manager is available as a read-only preview in Lite', 'wp-asset-clean-up'),
    __('Open any active plugin below to see the unload rules and load exceptions available in Pro. Lite does not execute or save any of these rules.', 'wp-asset-clean-up'),
    'plugins_manager_top'
);
?>
<div class="wpacu-sub-page-tabs-wrap"> <!-- Sub-tabs wrap -->
    <!-- Sub-nav menu -->
    <label id="wpacu-sub-page-nav-plugins-manager-front"
           class="wpacu-sub-page-nav-label
<?php if ( $data['wpacu_sub_page'] === 'manage_plugins_front' ) { ?>wpacu-selected<?php } ?>
">
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_plugins_manager&wpacu_sub_page=manage_plugins_front')); ?>"><span class="dashicons dashicons-admin-home"></span> IN FRONTEND VIEW (your visitors)</a>
    </label>
    <label id="wpacu-sub-page-nav-plugins-manager-dash"
           <?php if ( wpacuIsDefinedConstant('WPACU_ALLOW_DASH_PLUGIN_FILTER') ) { ?>data-wpacu-activated-via-code="1"<?php } ?>
           class="wpacu-sub-page-nav-label
<?php if ( $data['wpacu_sub_page'] === 'manage_plugins_dash' ) { ?>wpacu-selected<?php } ?>
">
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_plugins_manager&wpacu_sub_page=manage_plugins_dash')); ?>"><span class="dashicons dashicons-dashboard"></span> IN THE DASHBOARD /wp-admin/</a>
    </label>
    <!-- /Sub-nav menu -->
</div> <!-- /Sub-tabs wrap -->

<?php if ($data['wpacu_sub_page'] === 'manage_plugins_front') { ?>
    <div id="wpacu-plugins-manage-front-notice-top">
        <p style="margin-top: 0;"><strong><?php esc_html_e('What this Pro feature controls:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Unloading a plugin removes more than its CSS and JavaScript. Its PHP execution, HTML output, hooks, cookies and other front-end behaviour are also skipped on matching requests, much like deactivating it only for those pages.', 'wp-asset-clean-up'); ?> <a style="text-decoration: none; color: #004567;" target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=372"><span class="dashicons dashicons-info"></span>&nbsp;<?php esc_html_e('Read more', 'wp-asset-clean-up'); ?></a></p>
        <p style="margin-bottom: 0;"><?php esc_html_e('The controls below are a read-only Lite preview. In Pro, front-end rules do not affect Dashboard requests, and Test Mode can be used before exposing changes to visitors.', 'wp-asset-clean-up'); ?></p>
    </div>
<?php
    include_once __DIR__.'/_admin-page-plugins-manager/_front.php';
} elseif ($data['wpacu_sub_page'] === 'manage_plugins_dash') {
    ?>
    <div id="wpacu-plugins-manage-dash-notice-top">
        <p style="margin-top: 0;"><strong><?php esc_html_e('Advanced Pro feature:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Dashboard plugin unloading can reduce a slow admin page or isolate a conflict, but it also skips the plugin’s PHP code, admin hooks, HTML output and other behaviour on every matching request.', 'wp-asset-clean-up'); ?></p>
        <p style="margin-top: 0;"><?php esc_html_e('The controls below are a read-only Lite preview. In Pro, the emergency query string', 'wp-asset-clean-up'); ?> <code>&amp;wpacu_no_dash_plugin_unload</code> <?php esc_html_e('temporarily bypasses Dashboard unload rules so a mistaken rule can be corrected.', 'wp-asset-clean-up'); ?> <a style="text-decoration: none; color: #004567;" target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=1128"><span class="dashicons dashicons-info"></span>&nbsp;<?php esc_html_e('Read more', 'wp-asset-clean-up'); ?></a></p>
        <p style="margin-bottom: 0;"><?php esc_html_e('To stop using a plugin everywhere, deactivate it from Plugins → Installed Plugins instead of creating conditional rules.', 'wp-asset-clean-up'); ?></p>
    </div>
<?php
	include_once __DIR__.'/_admin-page-plugins-manager/_dash.php';
}
