<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
	exit;
}
?>
<hr style="margin: 15px 0;"/>
<!-- [Special Settings Area] -->
<?php
$specialSettings = array(
    // Cache plugins caching: clear it or not after Asset CleanUp Lite/Pro caching is cleared
	'do_not_also_clear_autoptimize_cache'   => wpacuIsDefinedConstant('WPACU_DO_NOT_ALSO_CLEAR_AUTOPTIMIZE_CACHE'),
	'do_not_also_clear_cache_enabler_cache' => wpacuIsDefinedConstant('WPACU_DO_NOT_ALSO_CLEAR_CACHE_ENABLER_CACHE'),

	'load_on_oxygen_builder_edit'           => wpacuIsDefinedConstant('WPACU_LOAD_ON_OXYGEN_BUILDER_EDIT'),
	'load_on_divi_builder_edit'             => wpacuIsDefinedConstant('WPACU_LOAD_ON_DIVI_BUILDER_EDIT'),
	'load_on_bricks_builder'                => wpacuIsDefinedConstant('WPACU_LOAD_ON_BRICKS_BUILDER'),
    'load_on_elementor_builder'             => wpacuIsDefinedConstant('WPACU_LOAD_ON_ELEMENTOR_BUILDER')
);

$noSpecialSettings = empty(array_filter($specialSettings));
?>
<div id="wpacu-special-settings-wrap">
	<h3><span class="dashicons dashicons-admin-generic"></span> <?php _e('Special Settings', 'wp-asset-clean-up'); ?></h3>
	<div style="padding: 10px; background: white; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
		<div>To avoid broken functionality, Asset CleanUp Pro does not load by default when certain URLs are loading (e.g. on REST Calls, when using specific Page Builders). Some experienced users would want to change this behaviour and allow the plugin to load and trigger its unload rules. Through special settings, you can do that. <a target="_blank" style="text-decoration: none;" href="https://www.assetcleanup.com/docs/?p=1495"><span class="dashicons dashicons-info"></span> Read more</a></div>

		<?php
		if ($noSpecialSettings) {
			?>
			<p style="margin: 15px 0 0;">There are no special settings set.</p>
			<?php
		} else {
			?>
			<div style="margin: 15px 0 0;">
				<table class="wp-list-table widefat fixed striped">
					<thead>
                        <tr class="wpacu-top">
                            <td><strong>Setting</strong></td>
                            <td><strong>Description</strong></td>
                        </tr>
					</thead>
					<tbody>
					<?php
					if ($specialSettings['do_not_also_clear_autoptimize_cache']) {
						?>
						<tr>
							<td><span style="color: green;">Do not also clear Autoptimize cache after <?php echo WPACU_PLUGIN_TITLE; ?> caching is cleared</span></td>
							<td>The constant <code>WPACU_DO_NOT_ALSO_CLEAR_AUTOPTIMIZE_CACHE</code> is set to <code style="color: blue;">true</code>. <a style="text-decoration: none; white-space: nowrap;" target="_blank" href="https://www.assetcleanup.com/docs/?p=1502#wpacu-autoptimize"><span class="dashicons dashicons-info"></span> Read more</a></td>
						</tr>
						<?php
					}

					if ($specialSettings['do_not_also_clear_cache_enabler_cache']) {
						?>
                        <tr>
                            <td><span style="color: green;">Do not also clear "Cache Enabler" cache after <?php echo WPACU_PLUGIN_TITLE; ?> caching is cleared</span></td>
                            <td>The constant <code>WPACU_DO_NOT_ALSO_CLEAR_CACHE_ENABLER_CACHE</code> is set to <code style="color: blue;">true</code>. <a style="text-decoration: none; white-space: nowrap;" target="_blank" href="https://www.assetcleanup.com/docs/?p=1502#wpacu-cache-enabler"><span class="dashicons dashicons-info"></span> Read more</a></td>
                        </tr>
						<?php
					}

					// [Page Builders]
					if ($specialSettings['load_on_oxygen_builder_edit']) {
						?>
						<tr>
							<td><span style="color: green;">Load plugin unload rules when using Oxygen Builder</span></td>
							<td>The constant <code>WPACU_LOAD_ON_OXYGEN_BUILDER_EDIT</code> is set to <code style="color: blue;">true</code>. Whenever you're editing a page using Oxygen Builder, any matching unload rules set using the following options will take effect: 1) <em>"Plugins Manager" -- "IN FRONTEND VIEW (your visitors)"</em> / 2) <em>"CSS &amp; JS MANAGER" -- "MANAGE CSS/JS"</em>. <a style="text-decoration: none; white-space: nowrap;" target="_blank" href="https://www.assetcleanup.com/docs/?p=1200"><span class="dashicons dashicons-info"></span> Read more</a></td>
						</tr>
						<?php
					}

					if ($specialSettings['load_on_divi_builder_edit']) {
						?>
						<tr>
							<td><span style="color: green;">Load plugin unload rules when using Divi Builder</span></td>
							<td>The constant <code>WPACU_LOAD_ON_DIVI_BUILDER_EDIT</code> is set to <code style="color: blue;">true</code>. Whenever you're editing a page using Divi Builder, any matching unload rules set using the following options will take effect: 1) <em>"Plugins Manager" -- "IN FRONTEND VIEW (your visitors)"</em> / 2) <em>"CSS &amp; JS MANAGER" -- "MANAGE CSS/JS"</em>. <a style="text-decoration: none; white-space: nowrap;" target="_blank" href="https://www.assetcleanup.com/docs/?p=1260"><span class="dashicons dashicons-info"></span> Read more</a></td>
						</tr>
						<?php
					}

					if ($specialSettings['load_on_bricks_builder']) {
						?>
						<tr>
							<td><span style="color: green;">Load plugin unload rules when using Bricks Builder</span></td>
							<td>The constant <code>WPACU_LOAD_ON_BRICKS_BUILDER</code> is set to <code style="color: blue;">true</code>. Whenever you're editing a page using Bricks Builder, any matching unload rules set using the following options will take effect: 1) <em>"Plugins Manager" -- "IN FRONTEND VIEW (your visitors)"</em> / 2) <em>"CSS &amp; JS MANAGER" -- "MANAGE CSS/JS"</em>. <a style="text-decoration: none; white-space: nowrap;" target="_blank" href="https://www.assetcleanup.com/docs/?p=1450"><span class="dashicons dashicons-info"></span> Read more</a></td>
						</tr>
						<?php
					}

                    if ($specialSettings['load_on_elementor_builder']) {
                        ?>
                        <tr>
                            <td><span style="color: green;">Load plugin unload rules when using Elementor Builder</span></td>
                            <td>The constant <code>WPACU_LOAD_ON_ELEMENTOR_BUILDER</code> is set to <code style="color: blue;">true</code>. Whenever you're editing a page using the Elementor plugin, any matching unloading rules set using the following option will take effect: <em>"Plugins Manager" -- "IN THE DASHBOARD /wp-admin/"</em>. <a style="text-decoration: none; white-space: nowrap;" target="_blank" href="https://www.assetcleanup.com/docs/?p=1789"><span class="dashicons dashicons-info"></span> Read more</a></td>
                        </tr>
                        <?php
                    }
					// [/Page Builders]
					?>
					</tbody>
				</table>
			</div>
			<?php
		}
		?>
	</div>
</div>
<!-- [/Special Settings Area] -->
