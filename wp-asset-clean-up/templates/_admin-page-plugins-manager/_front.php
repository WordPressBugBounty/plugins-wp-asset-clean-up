<?php
use WpAssetCleanUp\Admin\MiscAdmin;

if (! isset($data)) {
	exit;
}
?>
<div data-wpacu-sub-page-area="<?php echo $data['wpacu_sub_page']; ?>"
     class="wpacu-wrap"
     id="wpacu-plugins-load-manager-wrap">
		<?php
		$pluginsRows = array();

		foreach ($data['active_plugins'] as $pluginData) {
			$data['plugin_path'] = $pluginPath = $pluginData['path'];
			list($pluginDir) = explode('/', $pluginPath);

			ob_start();
			?>
			<tr>
				<td class="wpacu_plugin_icon" width="46">
					<?php if (isset($data['plugins_icons'][$pluginDir])) { ?>
						<img width="44" height="44" alt="" src="<?php echo esc_url($data['plugins_icons'][$pluginDir]); ?>" />
					<?php } else { ?>
						<div><span class="dashicons dashicons-admin-plugins"></span></div>
					<?php } ?>
				</td>
				<td class="wpacu_plugin_details"
                    id="wpacu-front-manage-<?php echo esc_attr($pluginData['path']); ?>">
                    <div class="wpacu_plugin_details_top_area">
                        <span class="wpacu_plugin_title"><?php echo esc_html($pluginData['title']); ?></span>
                        <span class="wpacu_plugin_path">&nbsp;<?php echo esc_html($pluginData['path']); ?></span>
                    </div>
					<?php
                    if ($pluginData['network_activated']) {
						echo '&nbsp;<span title="Network Activated" class="dashicons dashicons-admin-multisite wpacu-tooltip"></span>';
					}
                    ?>
					<div class="wpacu_clearfix"></div>

                    <!-- [Start] Unload Rules -->
					<?php
                    include __DIR__ . '/_front-areas/_unloads.php';
                    ?>
                    <!-- [End] Unload Rules -->
                </td>
            </tr>
			<?php
			$trOutput = ob_get_clean();
			$pluginsRows['always_loaded'][] = $trOutput;
		}

		if ( ! empty($pluginsRows['always_loaded']) ) {
			if (isset($pluginsRows['has_unload_rules']) && count($pluginsRows['has_unload_rules']) > 0) {
				?>
				<div style="margin-top: 35px;"></div>
				<?php
			}

			$totalAlwaysLoadedPlugins = count($pluginsRows['always_loaded']);
			?>
            <div class="wpacu_contract_expand_plugins_area">
                <div class="wpacu_col_left">
                    <h3><span style="color: green;" class="dashicons dashicons-admin-plugins"></span> <span style="color: green;"><?php echo (int)$totalAlwaysLoadedPlugins; ?></span> plugin<?php echo ($totalAlwaysLoadedPlugins > 1) ? 's' : ''; ?> with no active unload rules (loaded by default)</h3>
                </div>
                <div class="wpacu_clearfix"></div>
            </div>
            <table data-wpacu-area="plugins-loaded-by-default"
                   class="wp-list-table wpacu-list-table widefat plugins striped">
				<?php
				foreach ( $pluginsRows['always_loaded'] as $pluginRowOutput ) {
					echo MiscAdmin::stripIrrelevantHtmlTags($pluginRowOutput) . "\n";
				}
				?>
			</table>
			<?php
		}
		?>
    <div id="wpacu-update-button-area" style="margin-left: 0;">
        <p class="submit"><a target="_blank" disabled="disabled" class="go-pro-link-no-style button button-primary"
                             href="https://www.gabelivan.com/items/wp-asset-cleanup-pro/?utm_source=manage_asset&utm_medium=plugins_manager_area_front_tab_submit_button"
                             style="cursor: pointer; font-style: normal; padding-top: 5px;" id="submit"><span
                        class="wpacu-tooltip" style="width: 200px; margin-left: -108px;">This feature is locked for Pro users<br/>Click here to upgrade!</span>
                <img width="20" height="20" src="<?php echo esc_url( WPACU_PLUGIN_URL ); ?>/assets/icons/icon-lock.svg"
                     valign="middle" alt=""/> &nbsp;<?php echo esc_attr( __( 'Apply changes within frontend view',
					'wp-asset-clean-up' ) ); ?></a></p>
    </div>
</div>