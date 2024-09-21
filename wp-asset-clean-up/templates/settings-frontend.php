<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\MainFront;
use WpAssetCleanUp\Update;

if (! isset($data)) {
    exit;
}
?>
<span data-wpacu-delimiter-frontend-form-start="1" style="display:none;"></span>

<form id="wpacu-frontend-form" action="#wpacu_wrap_assets" method="post">
    <div id="wpacu_wrap_assets">
        <?php
        if ($data['wpacu_frontend_assets_manager_just_updated']) {
            $updateClass = new Update;
            ?>
            <div class="wpacu-updated-frontend"><em>
                    <?php if (MainFront::isHomePage()) {
                        echo wp_kses($updateClass->afterSubmitMsg['homepage'], array('span' => array('class' => array())));
                    } else {
                        echo wp_kses($updateClass->afterSubmitMsg['page'], array('span' => array('class' => array())));
                    } ?>
                </em>
            </div>
            <?php
        }

        $wpacuMiscAdmin = new MiscAdmin();
        $activeCachePlugins = $wpacuMiscAdmin->getActiveCachePlugins();

        if (in_array('wp-rocket/wp-rocket.php', $activeCachePlugins)) {
	        // Get WP Rocket Settings
	        $wpRocketSettings = get_option('wp_rocket_settings');

	        if (isset($wpRocketSettings['cache_logged_user']) && $wpRocketSettings['cache_logged_user'] == 1) {
		        ?>
                <div class="wpacu-warning">
                    <strong><span class="dashicons dashicons-warning"></span> <?php _e('Important', 'wp-asset-clean-up'); ?>:</strong> <?php echo sprintf(__('You have enabled "%s" in WP Rocket\'s Cache area.', 'wp-asset-clean-up'), '<em>Enable caching for logged-in WordPress users</em>'); ?>
			        <?php _e('This could cause some issues with Asset CleanUp retrieving an outdated (cached) asset list below.', 'wp-asset-clean-up'); ?>
			        <?php _e('If you experience issues such as unsaved settings or viewing assets from plugins that are disabled, consider using Asset CleanUp only in the Dashboard area (option "Manage in the Dashboard" has to be enabled in plugin\'s settings).', 'wp-asset-clean-up'); ?>
                    <!--
					-->
                </div>
                <div class="clearfix"></div>
		        <?php
	        }
        }

        if (wpacuIsPluginActive('perfmatters/perfmatters.php')) {
	        $perfmattersExtras = get_option('perfmatters_extras');

	        if (isset($perfmattersExtras['script_manager']) && (int)$perfmattersExtras['script_manager'] === 1) {
		        ?>
                <div class="wpacu-warning">
                    <span class="dashicons dashicons-warning" style="color: #cc0000;"></span> <?php _e('You\'ve enabled "Script Manager" within "Options" -&gt; "Assets" from Perfmatters plugin.', 'wp-asset-clean-up'); ?> <?php _e('You\'re already using Asset CleanUp to manage the CSS/JS.', 'wp-asset-clean-up'); ?> <strong><?php _e('Try not to use both plugins for the same feature as you could end up with broken functionality on either end.', 'wp-asset-clean-up'); ?></strong>
                </div>
		        <?php
	        }
        }
        ?>
        <p><small><?php _e('This area is shown only for the admin users and if "Manage in the Front-end?" was selected in the plugin\'s settings. Handles such as \'admin-bar\' and \'wpassetcleanup-style\' are not included as they are irrelevant since they are used by the plugin for this area.', 'wp-asset-clean-up'); ?></small></p>

        <?php
        if ($data['is_wp_recognizable']) {
            if ($data['is_woo_shop_page']) {
            ?>
                <p><strong><span style="color: #0f6cab;" class="dashicons dashicons-cart"></span> <?php _e('This a WooCommerce shop page (\'product\' type archive).', 'wp-asset-clean-up'); ?> <?php _e('Unloading CSS/JS will also take effect for the pagination/sorting pages', 'wp-asset-clean-up'); ?>(e.g. /2, /3, /?orderby=popularity etc.).</strong></p>
                <?php
            }

            if (isset($data['vars']['woo_url_not_match'])) {
                ?>
                <div class="wpacu_note wpacu_warning">
                    <p><?php _e('Although this page is detected as the home page, its URL is not the same as the one from "General Settings" &#187; "Site Address (URL)" and the WooCommerce plugin is not active anymore. This could be the "Shop" page that is no longer active.', 'wp-asset-clean-up'); ?></p>
                </div>
                <?php
            }

            // Perhaps "Do not load Asset CleanUp Pro on this page (this will disable any functionality of the plugin)" is set for this page
            // Or it's matched from "Settings" -> "Plugin Usage Preferences" -> "Do not load the plugin on certain pages"
	        if (isset($data['status']) && in_array($data['status'], array(5, 6)) && in_array($data['wpacu_type'], array('post', 'front_page'))) {
		        $data['page_options_with_assets_manager_no_load'] = true;
		        include __DIR__ . '/meta-box-restricted-page-load.php';
	        } else {
		        require_once __DIR__ . '/meta-box-loaded.php';
	        }
            ?>
            <div id="wpacu-update-front-settings-area">
                <button class="wpacu_update_btn"
                        type="submit"
                        name="submit"><span class="dashicons dashicons-update"></span> <?php esc_attr_e('UPDATE', 'wp-asset-clean-up'); ?></button>

                <div id="wpacu-updating-front-settings" style="display: none;">
                    <img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" align="top" width="20" height="20" alt="" />
                </div>
            </div>

            <p align="right"><small><?php echo sprintf(
				        __('Powered by %1$s, version %2$s', 'wp-asset-clean-up'),
				        WPACU_PLUGIN_TITLE,
			            WPACU_PLUGIN_VERSION);
            ?></small></p>
        <?php } else { ?>
            <p>This page is not a recognisable WordPress one. If you believe you should see a list of assets (CSS &amp; JavaScript) showing up here, please <a href="https://www.gabelivan.com/contact/">contact me</a>.</p>
        <?php
        }
        ?>
    </div>

    <?php
    if ($data['is_wp_recognizable']) {
    ?>
    <?php wp_nonce_field($data['nonce_action'], $data['nonce_name']); ?>
    <input type="hidden" name="wpacu_update_asset_frontend" value="1" />
    <?php } ?>
</form>

<span data-wpacu-delimiter-frontend-form-end="1" style="display:none;"></span>