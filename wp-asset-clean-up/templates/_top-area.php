<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
	exit;
}

use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

$wpacuTopAreaLinks = array(
	'admin.php?page=wpassetcleanup_settings' => array(
		'icon' => '<span class="dashicons dashicons-admin-generic"></span>',
		'title' => esc_html__('Settings', 'wp-asset-clean-up'),
		'page' => 'wpassetcleanup_settings'
	),

	'admin.php?page=wpassetcleanup_assets_manager' => array(
		'icon' => '<span class="dashicons dashicons-media-code"></span>',
		'title' => esc_html__('CSS &amp; JS Manager', 'wp-asset-clean-up'),
		'page'  => 'wpassetcleanup_assets_manager',
	),

	'admin.php?page=wpassetcleanup_plugins_manager' => array(
		'icon' => '<span class="dashicons dashicons-admin-plugins"></span>',
		'title' => esc_html__('Plugins Manager', 'wp-asset-clean-up'),
		'page'  => 'wpassetcleanup_plugins_manager',
	),

	'admin.php?page=wpassetcleanup_bulk_unloads' => array(
		'icon' => '<span class="dashicons dashicons-networking"></span>',
		'title' => esc_html__('Bulk Changes', 'wp-asset-clean-up'),
		'page'  => 'wpassetcleanup_bulk_unloads'
	),

	'admin.php?page=wpassetcleanup_overview' => array(
		'icon' => '<span class="dashicons dashicons-media-text"></span>',
		'title' => esc_html__('Overview', 'wp-asset-clean-up'),
		'page'  => 'wpassetcleanup_overview'
	),

	'admin.php?page=wpassetcleanup_tools' => array(
		'icon' => '<span class="dashicons dashicons-admin-tools"></span>',
		'title' => esc_html__('Tools', 'wp-asset-clean-up'),
		'page' => 'wpassetcleanup_tools'
	),
	'admin.php?page=wpassetcleanup_license' => array(
		'icon' => '<span class="dashicons dashicons-awards"></span>',
		'title' => esc_html__('License', 'wp-asset-clean-up'),
		'page' => 'wpassetcleanup_license'
	),
	'admin.php?page=wpassetcleanup_get_help' => array(
		'icon' => '<span class="dashicons dashicons-sos"></span>',
		'title' => esc_html__('Help', 'wp-asset-clean-up'),
		'page' => 'wpassetcleanup_get_help'
	),
    // [wpacu_lite]
    'admin.php?page=wpassetcleanup_go_pro' => array(
	    'icon' => '<span class="dashicons dashicons-star-filled" style="color: inherit;"></span>',
	    'title' => __('Go Pro', 'wp-asset-clean-up'),
	    'page' => 'wpassetcleanup_go_pro',
        'target' => '_blank'
    )
	// [/wpacu_lite]
);

global $current_screen;

$wpacuCurrentPage = isset($data['page']) ? $data['page'] : false;

if (! $wpacuCurrentPage) {
	$wpacuCurrentPage = str_replace(
		array(str_replace(' ', '-', strtolower(WPACU_PLUGIN_TITLE)) . '_page_', 'toplevel_page_', 'admin_page_'),
		'',
		$current_screen->base
	);
}

$wpacuDefaultPageUrl = esc_url(admin_url(Misc::arrayKeyFirst($wpacuTopAreaLinks)));

$goBackToCurrentUrl = '&_wp_http_referer=' . urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) );

$isSettingsCurrentPage = ($wpacuCurrentPage !== WPACU_PLUGIN_ID . '_settings');
?>
<div id="wpacu-top-area">
    <div id="wpacu-logo-wrap">
        <a href="<?php echo esc_url($wpacuDefaultPageUrl); ?>">
            <img alt="" src="<?php echo WPACU_PLUGIN_URL; ?>/assets/images/asset-cleanup-logo.png" />
            <div class="wpacu-version-sign wpacu-lite">
                <div>
                    LITE<div class="wpacu-version-text">v<?php echo WPACU_PLUGIN_VERSION; ?></div>
                </div>
            </div>
        </a>
    </div>

    <div id="wpacu-quick-actions">
        <span class="wpacu-actions-title"><?php _e('QUICK ACTIONS', 'wp-asset-clean-up'); ?>:</span>
        <a class="wpacu-clear-cache-link" href="<?php echo esc_url(OptimizeCommon::generateClearCachingUrl()); ?>">
            <span class="dashicons dashicons-update"></span> <?php _e('Clear CSS/JS Files Cache', 'wp-asset-clean-up'); ?>
        </a>
        |
        <?php
        if ($isSettingsCurrentPage) {
        ?>
        <a style="text-decoration: none; color: #74777b;" href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_settings&wpacu_selected_tab_area=wpacu-setting-test-mode')); ?>">
        <?php
        }

        echo esc_html__('TEST MODE', 'wp-asset-clean-up').': ';

        if (Main::instance()->settings['test_mode']) {
            echo '<strong style="color: green;">ON</strong> ';
            echo '<span style="font-weight: 300; font-style: italic;">* settings only apply to you (logged-in admin)</span>';
        } else {
            echo 'OFF ';
	        echo '<span style="font-weight: 300; font-style: italic;">* settings apply to any visitor</span>';
        }

        if ($isSettingsCurrentPage) {
        ?>
        </a>
        <?php
        }
        ?>
    </div>

    <div class="wpacu_clearfix"></div>
</div>

<div class="wpacu-tabs wpacu-tabs-style-topline">
    <nav>
        <ul>
			<?php
            foreach ($wpacuTopAreaLinks as $wpacuLink => $wpacuInfo) {
                $wpacuIsCurrentPage            = ($wpacuCurrentPage  === $wpacuInfo['page']);
	            $wpacuIsAssetsManagerPageLink  = ($wpacuInfo['page'] === 'wpassetcleanup_assets_manager');
	            $wpacuIsPluginsManagerPageLink = ($wpacuInfo['page'] === 'wpassetcleanup_plugins_manager');
	            $wpacuIsBulkUnloadsPageLink    = ($wpacuInfo['page'] === 'wpassetcleanup_bulk_unloads');
                $wpacuIsLicensePageLink        = ($wpacuInfo['page'] === 'wpassetcleanup_license');
                ?>
                <li data-wpacu-top-menu-tab-item="<?php echo $wpacuInfo['page']; ?>"
                    class="<?php if ($wpacuIsCurrentPage) { echo ' wpacu-tab-current '; }
                    ?>">
                    <?php
                    if ($wpacuIsAssetsManagerPageLink) {
                        $totalUnloadedAssets = MiscAdmin::getTotalUnloadedAssets('per_page');

                        if ($totalUnloadedAssets === 0) {
	                        ?>
                            <span class="extra-info assets-unloaded-false"><span class="dashicons dashicons-warning"></span> No unloads per page</span>
	                        <?php
                        } elseif ($totalUnloadedAssets > 0) {
                            ?>
                            <span class="extra-info assets-unloaded-true"><strong><?php echo (int)$totalUnloadedAssets; ?></strong> page unloads</span>
                            <?php
                        }
                    }

                    if ($wpacuIsPluginsManagerPageLink) {
                    // [wpacu_lite]
                    ?>
                    <span class="extra-info assets-unloaded-false"><span class="dashicons dashicons-lock"></span> Premium Feature</span>
                    <?php
                    // [/wpacu_lite]
                    }

                    if ($wpacuIsBulkUnloadsPageLink) {
                        $totalBulkUnloadRules = MiscAdmin::getTotalBulkUnloadsFor('all');

                        if ($totalBulkUnloadRules === 0) {
                            ?>
                            <span class="extra-info no-bulk-unloads assets-unloaded-false"><span class="dashicons dashicons-warning"></span> No bulk unloads</span>
	                        <?php
                        } elseif ($totalBulkUnloadRules > 0) {
                            ?>
                            <span class="extra-info has-bulk-unloads assets-unloaded-true"><strong><?php echo $totalBulkUnloadRules; ?></strong> bulk unload<?php echo ($totalBulkUnloadRules > 1) ? 's' : ''; ?></span>
	                        <?php
                        }
                    }
                    ?>
                    <a <?php if (isset($wpacuInfo['target']) && $wpacuInfo['target'] === '_blank') { ?> target="_blank" <?php } ?>
                            href="<?php echo esc_url(admin_url($wpacuLink)); ?>">
                        <?php echo wp_kses($wpacuInfo['icon'], array('span' => array('class' => array()))); ?> <span><?php echo esc_html($wpacuInfo['title']); ?></span>
                    </a>
                </li>
			<?php } ?>
        </ul>
    </nav>
</div><!-- /tabs -->
