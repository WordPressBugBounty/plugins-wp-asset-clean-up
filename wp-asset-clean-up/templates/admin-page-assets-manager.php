<?php
/*
 * No direct access to this file
 */
if ( ! isset($data) ) {
	exit;
}

$wpacuSubPage = (isset($_GET['wpacu_sub_page']) && $_GET['wpacu_sub_page']) ? $_GET['wpacu_sub_page'] : 'manage_css_js';

include_once __DIR__ . '/_top-area.php';
?>
<div class="wpacu-wrap" style="margin: -12px 0 0;">
    <div class="wpacu-sub-page-tabs-wrap"> <!-- Sub-tabs wrap -->
        <!-- Sub-nav menu -->
        <label class="wpacu-sub-page-nav-label <?php if ($wpacuSubPage === 'manage_css_js') { ?>wpacu-selected<?php } ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_css_js')); ?>">
                <?php echo esc_attr('MANAGE CSS/JS', 'wp-asset-clean-up'); ?>
            </a>
        </label>
        <label class="wpacu-sub-page-nav-label <?php if ($wpacuSubPage === 'manage_critical_css') { ?>wpacu-selected<?php } ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css')); ?>">
                <?php echo esc_attr('MANAGE CRITICAL CSS', 'wp-asset-clean-up'); ?>
            </a>
        </label>
        <!-- /Sub-nav menu -->
    </div> <!-- /Sub-tabs wrap -->

    <?php
    if ($wpacuSubPage === 'manage_css_js') {
       include_once __DIR__ . '/admin-page-assets-manager-manage-css-js.php';
    }

    if ($wpacuSubPage === 'manage_critical_css') {
        include_once __DIR__ . '/admin-page-assets-manager-critical-css.php';
    }
    ?>
</div>
