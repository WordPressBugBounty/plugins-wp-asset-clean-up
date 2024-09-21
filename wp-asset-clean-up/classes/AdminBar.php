<?php
namespace WpAssetCleanUp;

use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

/**
 * Class AdminBar
 * @package WpAssetCleanUp
 */
class AdminBar
{
	/**
	 * This class is called within the WordPress 'init' hook when it's meant to be loaded
	 */
	public function __construct()
	{
		// Code for both the Dashboard and the Front-end view
		add_action('admin_head',     array($this, 'inlineCode'));
		add_action('wp_head',        array($this, 'inlineCode'));

		add_action('admin_bar_menu', array($this, 'topBarInfo'), 81);

		// Hide top WordPress admin bar on request for debugging purposes and a cleared view of the tested page
		// This is done in /early-triggers.php within assetCleanUpNoLoad() function
	}

	/**
	 *
	 */
	public function inlineCode()
	{
		?>
		<style <?php echo Misc::getStyleTypeAttribute(); ?> data-wpacu-own-inline-style="true">
            #wp-admin-bar-assetcleanup-asset-unload-rules-css-default,
            #wp-admin-bar-assetcleanup-asset-unload-rules-js-default,
            #wp-admin-bar-assetcleanup-plugin-unload-rules-notice-default {
                overflow-y: auto;
                max-height: calc(100vh - 250px);
            }

            #wp-admin-bar-assetcleanup-parent span.dashicons {
                width: 15px;
                height: 15px;
                font-family: 'Dashicons', Arial, "Times New Roman", "Bitstream Charter", Times, serif !important;
            }

            #wp-admin-bar-assetcleanup-parent > a:first-child strong {
                font-weight: bolder;
                color: #76f203;
            }

            #wp-admin-bar-assetcleanup-parent > a:first-child:hover {
                color: #00b9eb;
            }

            #wp-admin-bar-assetcleanup-parent > a:first-child:hover strong {
                color: #00b9eb;
            }

            #wp-admin-bar-assetcleanup-test-mode-info {
                margin-top: 5px !important;
                margin-bottom: -8px !important;
                padding-top: 3px !important;
                border-top: 1px solid #ffffff52;
            }

            /* Add some spacing below the last text */
            #wp-admin-bar-assetcleanup-test-mode-info-2 {
                padding-bottom: 3px !important;
            }
		</style>
		<?php
	}

	/**
	 * @param $wp_admin_bar
     *
     * @noinspection NestedAssignmentsUsageInspection
     * */
	public function topBarInfo($wp_admin_bar)
	{
		$topTitle = WPACU_PLUGIN_TITLE;

        $anyUnloadedItems = false;
        $markedCssListForUnload = $markedJsListForUnload = array();

		if (! is_admin()) {
			$markedCssListForUnload = isset(Main::instance()->allUnloadedAssets['styles'])  ? array_unique(Main::instance()->allUnloadedAssets['styles'])  : array();
			$markedJsListForUnload  = isset(Main::instance()->allUnloadedAssets['scripts']) ? array_unique(Main::instance()->allUnloadedAssets['scripts']) : array();

            // [wpacu_lite]
            // Do not print any irrelevant data from the Pro version such as hardcoded CSS/JS
            $markedCssListForUnload = array_filter($markedCssListForUnload, function ($value) {
                if (strpos($value, 'wpacu_hardcoded_style_') === 0) {
                    return false;
                }

                return $value;
            });

            $markedJsListForUnload = array_filter($markedJsListForUnload, function ($value) {
                if (strpos($value, 'wpacu_hardcoded_script_') === 0) {
                    return false;
                }

                return $value;
            });
            // [/wpacu_lite]

            $anyUnloadedItems = (count($markedCssListForUnload) + count($markedJsListForUnload)) > 0;
		}

		if ($anyUnloadedItems) {
		$styleAttrType = Misc::getStyleTypeAttribute();

        $cssStyle = <<<HTML
<style {$styleAttrType}>
#wpadminbar .wpacu-alert-sign-top-admin-bar {
    font-size: 20px;
    color: lightyellow;
    vertical-align: top;
    margin: -7px 0 0;
    display: inline-block;
    box-sizing: border-box;
}

#wp-admin-bar-assetcleanup-plugin-unload-rules-notice-default .ab-item {
	min-width: 250px !important;
}

#wp-admin-bar-assetcleanup-plugin-unload-rules-notice .ab-item > .dashicons-admin-plugins {
	width: 20px;
	height: 20px;
    font-size: 20px;
    line-height: normal;
    vertical-align: middle;
    margin-top: -2px;
}
</style>
HTML;
			$topTitle .= $cssStyle . '&nbsp;<span class="wpacu-alert-sign-top-admin-bar dashicons dashicons-filter"></span>';
		}

		if (Main::instance()->settings['test_mode']) {
			$topTitle .= '&nbsp; <span class="dashicons dashicons-admin-tools"></span> <strong>TEST MODE</strong> is <strong>ON</strong>';
		}

		$wp_admin_bar->add_menu(array(
			'id'    => 'assetcleanup-parent',
			'title' => $topTitle,
			'href'  => esc_url(admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_settings'))
		));

		$wp_admin_bar->add_menu(array(
			'parent' => 'assetcleanup-parent',
			'id'     => 'assetcleanup-settings',
			'title'  => __('Settings', 'wp-asset-clean-up'),
			'href'   => esc_url(admin_url( 'admin.php?page=' . WPACU_PLUGIN_ID . '_settings'))
		));

		$wp_admin_bar->add_menu(array(
			'parent' => 'assetcleanup-parent',
			'id'     => 'assetcleanup-clear-css-js-files-cache',
			'title'  => __('Clear CSS/JS Files Cache', 'wp-asset-clean-up'),
			'href'   => esc_url(OptimizeCommon::generateClearCachingUrl()),
            'meta'   => array('class' => 'wpacu-clear-cache-link')
		));

		// Only trigger in the front-end view
		if ( ! is_admin() ) {
            $manageAssetsTitle = $manageAssetsHref = false;

            if (AssetsManager::instance()->frontendShow()) {
                $manageAssetsTitle = esc_html__('Manage Current Page Assets', 'wp-asset-clean-up'); // default
                $manageAssetsHref = '#wpacu_wrap_assets'; // same for all (bottom of the page)
            }

            if (MainFront::isHomePage()) {
                $manageAssetsTitle = esc_html__('Manage Current Homepage Assets', 'wp-asset-clean-up');

                if ( ! $manageAssetsHref ) {
                    $manageAssetsHref = esc_url(admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager'));
                }
            } elseif (MainFront::isSingularPage()) {
                global $post;

                if ( isset($post->ID) ) {
                    $manageAssetsTitle = esc_html__('Manage Current Page Assets', 'wp-asset-clean-up');

                    if ( ! $manageAssetsHref ) {
                        $manageAssetsHref = esc_url(admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_post_id=' . $post->ID));
                    }
                }
            }

            if ($manageAssetsTitle && $manageAssetsHref) {
                if (AssetsManager::instance()->frontendShow()) {
                    $wp_admin_bar->add_menu(array(
                        'parent' => 'assetcleanup-parent',
                        'id'     => 'assetcleanup-jump-to-assets-list',
                        // language: alias of 'Manage Page Assets'
                        'title'  => $manageAssetsTitle . '&nbsp;<span style="vertical-align: sub;" class="dashicons dashicons-arrow-down-alt"></span>',
                        'href'   => $manageAssetsHref
                    ));
                } else {
                    $wp_admin_bar->add_menu(array(
                        'parent' => 'assetcleanup-parent',
                        'id'     => 'assetcleanup-manage-page-assets-dashboard',
                        // language: alias of 'Manage Page Assets'
                        'title'  => $manageAssetsTitle,
                        'href'   => $manageAssetsHref,
                        'meta'   => array('target' => '_blank')
                    ));
                }
            }
		}

		$wp_admin_bar->add_menu(array(
			'parent' => 'assetcleanup-parent',
			'id'     => 'assetcleanup-bulk-unloaded',
			'title'  => esc_html__('Bulk Changes', 'wp-asset-clean-up'),
			'href'   => esc_url(admin_url( 'admin.php?page=' . WPACU_PLUGIN_ID . '_bulk_unloads'))
		));

		$wp_admin_bar->add_menu( array(
			'parent' => 'assetcleanup-parent',
			'id'     => 'assetcleanup-overview',
			'title'  => esc_html__('Overview', 'wp-asset-clean-up'),
			'href'   => esc_url(admin_url( 'admin.php?page=' . WPACU_PLUGIN_ID . '_overview'))
		) );

        // [wapcu_lite]
		$wp_admin_bar->add_menu(array(
			'parent' => 'assetcleanup-parent',
			'id'     => 'assetcleanup-support-forum',
			'title'  => esc_html__('Support Forum', 'wp-asset-clean-up'),
			'href'   => 'https://wordpress.org/support/plugin/wp-asset-clean-up',
			'meta'   => array('target' => '_blank')
		));
        // [/wapcu_lite]

		// [START LISTING UNLOADED ASSETS]
		if (! is_admin()) { // Frontend view (show any unloaded handles)
			$totalUnloadedAssets = count($markedCssListForUnload) + count($markedJsListForUnload);

			if ($totalUnloadedAssets > 0) {
				$titleUnloadText = sprintf( _n( '%d unload asset rules took effect on this frontend page',
					'%d unload asset rules took effect on this frontend page', $totalUnloadedAssets, 'wp-asset-clean-up' ),
					$totalUnloadedAssets );

				$wp_admin_bar->add_menu( array(
					'parent' => 'assetcleanup-parent',
					'id'     => 'assetcleanup-asset-unload-rules-notice',
					'title'  => '<span style="margin: -10px 0 0;" class="wpacu-alert-sign-top-admin-bar dashicons dashicons-filter"></span> &nbsp; '. $titleUnloadText,
					'href'   => '#'
				) );

				if ( count( $markedCssListForUnload ) > 0 ) {
					$wp_admin_bar->add_menu(array(
						'parent' => 'assetcleanup-asset-unload-rules-notice',
						'id'     => 'assetcleanup-asset-unload-rules-css',
						'title'  => esc_html__('CSS', 'wp-asset-clean-up'). ' ('.count( $markedCssListForUnload ).')',
						'href'   => '#'
					));
					sort($markedCssListForUnload);

					foreach ($markedCssListForUnload as $cssHandle) {
						$wp_admin_bar->add_menu(array(
							'parent' => 'assetcleanup-asset-unload-rules-css',
							'id'     => 'assetcleanup-asset-unload-rules-css-'.$cssHandle,
							'title'  => $cssHandle,
							'href'   => esc_url(admin_url('admin.php?page=wpassetcleanup_overview#wpacu-overview-css-'.$cssHandle))
						));
					}
				}

				if ( count( $markedJsListForUnload ) > 0 ) {
					$wp_admin_bar->add_menu(array(
						'parent' => 'assetcleanup-asset-unload-rules-notice',
						'id'     => 'assetcleanup-asset-unload-rules-js',
						'title'  => esc_html__('JavaScript', 'wp-asset-clean-up'). ' ('.count( $markedJsListForUnload ).')',
						'href'   => '#'
					));
					sort($markedJsListForUnload);

					foreach ($markedJsListForUnload as $jsHandle) {
						$wp_admin_bar->add_menu(array(
							'parent' => 'assetcleanup-asset-unload-rules-js',
							'id'     => 'assetcleanup-asset-unload-rules-js-'.$jsHandle,
							'title'  => $jsHandle,
							'href'   => esc_url(admin_url('admin.php?page=wpassetcleanup_overview#wpacu-overview-js-'.$jsHandle))
						));
					}
					}
			}
		}
		// [END LISTING UNLOADED ASSETS]

		}
}
