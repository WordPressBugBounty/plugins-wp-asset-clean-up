<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\AssetsManager;
use WpAssetCleanUp\Misc;

if (! isset($data)) {
	exit;
}

include_once __DIR__ . '/_top-area.php';

$wpacuTabList = array(
    'bulk_unloaded'         => __('Bulk Unloaded (page types)', 'wp-asset-clean-up'),
    'regex_unloads'         => __('RegEx Unloads', 'wp-asset-clean-up'),
    'regex_load_exceptions' => __('RegEx Load Exceptions', 'wp-asset-clean-up'),
    'preloaded_assets'      => __('Preloaded CSS/JS', 'wp-asset-clean-up'),
    'script_attrs'          => __('Defer &amp; Async (site-wide)', 'wp-asset-clean-up'),
    'assets_positions'      => __('Updated CSS/JS positions', 'wp-asset-clean-up')
);

$wpacuTabCurrent = isset($_REQUEST['wpacu_bulk_menu_tab']) && array_key_exists( $_REQUEST['wpacu_bulk_menu_tab'], $wpacuTabList ) ? sanitize_text_field($_REQUEST['wpacu_bulk_menu_tab']) : 'bulk_unloaded';
?>
<div class="wpacu-wrap <?php if ($data['plugin_settings']['input_style'] !== 'standard') { echo 'wpacu-switch-enhanced'; } ?>">
    <ul class="wpacu-bulk-changes-tabs">
		<?php
		foreach ($wpacuTabList as $wpacuTabKey => $wpacuTabValue) {
			?>
            <li <?php if ($wpacuTabKey === $wpacuTabCurrent) { ?>class="current"<?php } ?>>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_bulk_unloads&wpacu_bulk_menu_tab='.$wpacuTabKey)); ?>"><?php echo esc_html($wpacuTabValue); ?></a>
            </li>
			<?php
		}
		?>
    </ul>
	<?php
	if ($wpacuTabCurrent === 'bulk_unloaded') {
		include_once __DIR__ . '/_admin-page-settings-bulk-changes/_bulk-unloaded.php';
	} elseif($wpacuTabCurrent === 'regex_unloads') {
		include_once __DIR__ . '/_admin-page-settings-bulk-changes/_regex-unloads.php';
	} elseif($wpacuTabCurrent === 'regex_load_exceptions') {
		include_once __DIR__ . '/_admin-page-settings-bulk-changes/_regex-load-exceptions.php';
	} elseif ($wpacuTabCurrent === 'preloaded_assets') {
		include_once __DIR__ . '/_admin-page-settings-bulk-changes/_preloaded-assets.php';
	} elseif ($wpacuTabCurrent === 'script_attrs') {
		include_once __DIR__ . '/_admin-page-settings-bulk-changes/_script-attrs.php';
	} elseif ($wpacuTabCurrent === 'assets_positions') {
		include_once __DIR__ . '/_admin-page-settings-bulk-changes/_assets-positions.php';
	}

	/**
	 * @param $handle
	 * @param $assetType
	 * @param $data
	 * @param string $for ('default': bulk unloads, regex unloads)
	 */
	function wpacuRenderHandleTd($handle, $assetType, $data, $for = 'default')
    {
	    global $wp_version;

	    $isCoreFile = false; // default

		if ( $for === 'default' ) {
			// Show the original "src" and "ver, not the altered one
			// (in case filters such as "wpacu_{$handle}_(css|js)_handle_obj" were used to load alternative versions of the file, depending on the situation)
			$srcKey = isset($data['assets_info'][ $assetType ][ $handle ]['src_origin']) ? 'src_origin' : 'src';
			$verKey = isset($data['assets_info'][ $assetType ][ $handle ]['ver_origin']) ? 'ver_origin' : 'ver';

			$src = (isset( $data['assets_info'][ $assetType ][ $handle ][$srcKey] ) && $data['assets_info'][ $assetType ][ $handle ][$srcKey]) ? $data['assets_info'][ $assetType ][ $handle ][$srcKey] : false;

			$isExternalSrc = true;

			if (Misc::getLocalSrcIfExist($src)
                || strpos($src, '/?') !== false // Dynamic Local URL
                || strncmp(str_replace(site_url(), '', $src), '?', 1) === 0 // Starts with ? right after the site url (it's a local URL)
			) {
				$isExternalSrc = false;
				$isCoreFile = MiscAdmin::isCoreFile($data['assets_info'][$assetType][$handle]);
			}

            if ( $src && $isExternalSrc ) {
                if ( ! isset($GLOBALS['wpacu_external_srcs_bulk_changes']) ) {
                    $GLOBALS['wpacu_external_srcs_bulk_changes'] = array();
                }

                $GLOBALS['wpacu_external_srcs_bulk_changes'][] = $src;
            }

            $src = Misc::getHrefFromSource($src);

			if (isset($data['assets_info'][ $assetType ][ $handle ][$verKey]) && $data['assets_info'][ $assetType ][ $handle ][$verKey]) {
				$verToPrint = is_array($data['assets_info'][ $assetType ][ $handle ][$verKey])
					? implode(',', $data['assets_info'][ $assetType ][ $handle ][$verKey])
					: $data['assets_info'][ $assetType ][ $handle ][$verKey];
				$verToAppend = is_array($data['assets_info'][ $assetType ][ $handle ][$verKey])
                    ? http_build_query(array('ver' => $data['assets_info'][ $assetType ][ $handle ][$verKey]))
                    : 'ver='.$data['assets_info'][ $assetType ][ $handle ][$verKey];
			} else {
				$verToAppend = 'ver='.$wp_version;
                $verToPrint = $wp_version;
            }
			?>
				<strong><span style="color: green;"><?php echo esc_html($handle); ?></span></strong>
				<small><em>v<?php echo esc_html($verToPrint); ?></em></small>
			<?php
			if ($isCoreFile) {
				?>
                <span title="WordPress Core File" style="font-size: 15px; vertical-align: middle;" class="dashicons dashicons-wordpress-alt wpacu-tooltip"></span>
				<?php
			}
			?>
            <?php
            if ( $src ) {
			    $appendAfterSrc = strpos($src, '?') === false ? '?'.$verToAppend : '&'.$verToAppend;
			    ?>
                <div><a <?php if ($isExternalSrc) { ?> data-wpacu-external-source="<?php echo esc_attr($src . $appendAfterSrc); ?>" <?php } ?> href="<?php echo esc_html($src . $appendAfterSrc); ?>" target="_blank"><small><?php echo str_replace( site_url(), '', $src ); ?></small></a> <?php if ($isExternalSrc) { ?><span data-wpacu-external-source-status></span><?php } ?></div>
                <?php
			    $maybeInactiveAsset = MiscAdmin::maybeIsInactiveAsset($src);

			    if (is_array($maybeInactiveAsset) && ! empty($maybeInactiveAsset)) {
			        if ($maybeInactiveAsset['from'] === 'plugin') { ?>
                        <small><strong>Note:</strong> <span style="color: darkred;">The plugin `<strong><?php echo esc_html($maybeInactiveAsset['name']); ?></strong>` seems to be inactive, thus any rules set are also inactive &amp; irrelevant, unless you re-activate the plugin.</span></small>
				    <?php } elseif ($maybeInactiveAsset['from'] === 'theme') { ?>
                        <small><strong>Note:</strong> <span style="color: darkred;">The theme `<strong><?php echo esc_html($maybeInactiveAsset['name']); ?></strong>` seems to be inactive, thus any rules set are also inactive &amp; irrelevant, unless you re-activate the theme.</span></small>
				    <?php }
				}
			}
		}
	}

    if ( ! empty($GLOBALS['wpacu_external_srcs_bulk_changes']) ) {
        $externalSrcsRef = AssetsManager::setExternalSrcsRef($GLOBALS['wpacu_external_srcs_bulk_changes'], 'bulk_changes');
    ?>
        <span data-wpacu-external-srcs-ref="<?php echo esc_attr($externalSrcsRef); ?>" style="display: none;"></span>
    <?php } ?>
</div>