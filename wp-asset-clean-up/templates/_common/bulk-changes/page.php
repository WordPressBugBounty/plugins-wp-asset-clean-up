<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\AssetsManager;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\Settings;

if (! isset($data)) {
	exit;
}

require WPACU_PLUGIN_DIR . '/templates/_top-area.php';

$wpacuTabList = array(
    'bulk_unloaded'         => __('Bulk Unloaded (page types)', 'wp-asset-clean-up'),
    'regex_unloads'         => __('RegEx Unloads', 'wp-asset-clean-up'),
    'regex_load_exceptions' => __('RegEx Load Exceptions', 'wp-asset-clean-up'),
    'preloaded_assets'      => __('Preloaded CSS/JS', 'wp-asset-clean-up'),
    'script_attrs'          => __('Defer &amp; Async (site-wide)', 'wp-asset-clean-up'),
    'assets_positions'      => __('Updated CSS/JS positions', 'wp-asset-clean-up')
);

$wpacuTabCurrent = isset($_REQUEST['wpacu_bulk_menu_tab']) && array_key_exists( $_REQUEST['wpacu_bulk_menu_tab'], $wpacuTabList ) ? sanitize_text_field($_REQUEST['wpacu_bulk_menu_tab']) : 'bulk_unloaded';
$inputStyle = Settings::getInputStyle($data['plugin_settings']);

do_action('wpacu_below_menu_admin_notices');
?>
<div class="wpacu-wrap <?php echo esc_attr(Settings::getInputStyleCssClasses($inputStyle)); ?>"
     data-wpacu-input-style="<?php echo esc_attr($inputStyle); ?>">
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
		require WPACU_PLUGIN_DIR . '/templates/_admin-page-settings-bulk-changes/_bulk-unloaded.php';
	} elseif($wpacuTabCurrent === 'regex_unloads') {
		require WPACU_PLUGIN_DIR . '/templates/_admin-page-settings-bulk-changes/_regex-unloads.php';
	} elseif($wpacuTabCurrent === 'regex_load_exceptions') {
		require WPACU_PLUGIN_DIR . '/templates/_admin-page-settings-bulk-changes/_regex-load-exceptions.php';
	} elseif ($wpacuTabCurrent === 'preloaded_assets') {
		require WPACU_PLUGIN_DIR . '/templates/_admin-page-settings-bulk-changes/_preloaded-assets.php';
	} elseif ($wpacuTabCurrent === 'script_attrs') {
		require WPACU_PLUGIN_DIR . '/templates/_admin-page-settings-bulk-changes/_script-attrs.php';
	} elseif ($wpacuTabCurrent === 'assets_positions') {
		require WPACU_PLUGIN_DIR . '/templates/_admin-page-settings-bulk-changes/_assets-positions.php';
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

	    // [wpacu_pro]
	    $isHardcoded = defined('WPACU_PRO_PLUGIN_VERSION') && (strncmp($handle, 'wpacu_hardcoded_', 16) === 0);
	    $hardcodedTagOutput = false;
        // [/wpacu_pro]

		if ( $for === 'default' ) {
			// [wpacu_pro]
			if ( $isHardcoded
			     && isset( $data['assets_info'][ $assetType ][ $handle ]['output'] )
			     && ( $hardcodedTagOutput = $data['assets_info'][ $assetType ][ $handle ]['output'] )
                 && ( $sourceValue = Misc::getValueFromTag($hardcodedTagOutput) )
            ) {
                $data['assets_info'][ $assetType ][ $handle ]['src'] = $sourceValue;
			}
			// [/wpacu_pro]

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

			if (! $isHardcoded) {
				?>
				<strong><span style="color: green;"><?php echo esc_html($handle); ?></span></strong>
				<?php
				// Only valid if the asset is enqueued
				?>
				<small><em>v<?php echo esc_html($verToPrint); ?></em></small>
				<?php
			} else {
				// [wpacu_pro]
				// Hardcoded Link/Style/Script
				$hardcodedTitle = '';

				if (strpos($handle, '_link_') !== false) {
					$hardcodedTitle = 'Hardcoded LINK (rel="stylesheet")';
				} elseif (strpos($handle, '_style_') !== false) {
					$hardcodedTitle = 'Hardcoded inline STYLE';
				} elseif (strpos($handle, '_script_inline_') !== false) {
					$hardcodedTitle = 'Hardcoded inline SCRIPT';
				} elseif (strpos($handle, '_script_') !== false) {
					$hardcodedTitle = 'Hardcoded SCRIPT (with "src")';
				}
				?>
				<strong><?php echo esc_html($hardcodedTitle); ?></strong>
				<?php
				if ( $hardcodedTagOutput ) {
					$maxCharsToShow = 400;

					if (strlen($hardcodedTagOutput) > $maxCharsToShow) {
						echo '<code><small>' . htmlentities2( substr($hardcodedTagOutput, 0, $maxCharsToShow) ) . '</small></code>... &nbsp;<a data-wpacu-modal-target="wpacu-'.$handle.'-modal-target" href="#wpacu-'.$handle.'-modal" class="button button-secondary">View All</a>';
						?>
						<div id="<?php echo 'wpacu-'.$handle.'-modal'; ?>" class="wpacu-modal wpacu-source-viewer-modal">
							<div class="wpacu-modal-content wpacu-source-viewer-modal__content">
								<button type="button" class="wpacu-close wpacu-source-viewer-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
								<header class="wpacu-source-viewer-modal__header">
									<span class="wpacu-source-viewer-modal__eyebrow"><?php esc_html_e('Source viewer', 'wp-asset-clean-up'); ?></span>
									<div class="wpacu-source-viewer-modal__title-row">
										<h2><?php echo esc_html($hardcodedTitle); ?></h2>
										<span class="wpacu-source-viewer-modal__badge"><?php esc_html_e('HTML tag', 'wp-asset-clean-up'); ?></span>
									</div>
									<p class="wpacu-source-viewer-modal__description"><?php esc_html_e('Complete hardcoded markup detected for this asset.', 'wp-asset-clean-up'); ?></p>
								</header>
								<div class="wpacu-source-viewer-modal__body"><pre><code><?php echo htmlentities2($hardcodedTagOutput); ?></code></pre></div>
							</div>
						</div>
						<?php
					} else {
						// Under the limit? Show everything
						echo '<code><small>' . htmlentities2( $hardcodedTagOutput ) . '</small></code>';
					}
				}
				// [/wpacu_pro]
			}

			if ($isCoreFile) {
				?>
                <span title="WordPress Core File" style="font-size: 15px; vertical-align: middle;" class="dashicons dashicons-wordpress-alt wpacu-tooltip"></span>
				<?php
			}
			?>
            <?php
			// [wpacu_pro]
			$preloadedStatus = isset($data['assets_info'][ $assetType ][ $handle ]['preloaded_status']) ? $data['assets_info'][ $assetType ][ $handle ]['preloaded_status'] : false;
			if ($preloadedStatus === 'async') { echo '&nbsp;(<strong><em>'.$preloadedStatus.'</em></strong>)'; }
			// [/wpacu_pro]

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
<!-- [wpacu_pro] -->
<script type="text/javascript">
	jQuery(document).ready(function($) {
        $('.wpacu-modal').appendTo(document.body);
	});
</script>
<!-- [/wpacu_pro] -->

