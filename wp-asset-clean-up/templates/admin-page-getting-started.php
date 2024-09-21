<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}
?>
<div class="wpacu-wrap">
    <div class="about-wrap wpacu-about-wrap">
        <h1><?php echo sprintf(__('Welcome to %s %s', 'wp-asset-clean-up'), 'Asset CleanUp', WPACU_PLUGIN_VERSION); ?></h1>
        <p class="about-text wpacu-about-text">
            <?php
            _e('Thank you for installing this page speed booster plugin', 'wp-asset-clean-up'); ?>! <?php _e('Prepare to make your WordPress website faster &amp; lighter by removing the useless CSS &amp; JavaScript files from your pages.', 'wp-asset-clean-up');

            echo sprintf(
                    __('For maximum performance, %s works best when used with either a %scaching plugin%s, the in-built hosting caching (e.g. via %sWPEngine%s, Kinsta, etc.) or something like Varnish.', 'wp-asset-clean-up'),
                     'Asset CleanUp',
                    '<a style="text-decoration: none; color: #555d66;" href="https://wordpress.org/plugins/wp-fastest-cache/">', '</a>',
                    '<a style="text-decoration: none; color: #555d66;" href="https://www.gabelivan.com/visit/wp-engine">', '</a>'
            );
            ?>
            <img src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/images/wpacu-logo-transparent-bg-v1.png" alt="" />
        </p>

        <h2 class="wpacu-nav-tab-wrapper wpacu-getting-started wp-clearfix">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_getting_started&wpacu_for=how-it-works')); ?>" class="wpacu-nav-tab <?php if ($data['for'] === 'how-it-works') { ?>wpacu-nav-tab-active<?php } ?>"><?php _e('How it works', 'wp-asset-clean-up'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_getting_started&wpacu_for=benefits-fast-pages')); ?>" class="wpacu-nav-tab <?php if ($data['for'] === 'benefits-fast-pages') { ?>wpacu-nav-tab-active<?php } ?>"><?php _e('Benefits of a Fast Website', 'wp-asset-clean-up'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_getting_started&wpacu_for=start-optimization')); ?>" class="wpacu-nav-tab <?php if ($data['for'] === 'start-optimization') { ?>wpacu-nav-tab-active<?php } ?>"><?php _e('Start Optimization', 'wp-asset-clean-up'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_getting_started&wpacu_for=video-tutorials')); ?>" class="wpacu-nav-tab <?php if ($data['for'] === 'video-tutorials') { ?>wpacu-nav-tab-active<?php } ?>"><span class="dashicons dashicons-video-alt3" style="color: #ff0000;"></span> <?php _e('Video Tutorials', 'wp-asset-clean-up'); ?></a>
            <!-- [wpacu_lite] -->
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_getting_started&wpacu_for=lite-vs-pro')); ?>" class="wpacu-nav-tab <?php if ($data['for'] === 'lite-vs-pro') { ?>wpacu-nav-tab-active<?php } ?>"><span class="dashicons dashicons-awards"></span> <?php _e('Lite vs Pro', 'wp-asset-clean-up'); ?></a>
            <!-- [/wpacu_lite] -->
        </h2>

        <div class="about-wrap-content">
	        <?php
	        if ($data['for'] === 'how-it-works') {
		        include_once __DIR__ .  '/_admin-page-getting-started-areas/_how-it-works.php';
	        } elseif ($data['for'] === 'benefits-fast-pages') {
		        include_once __DIR__ .  '/_admin-page-getting-started-areas/_benefits-fast-pages.php';
	        } elseif ($data['for'] === 'start-optimization') {
		        include_once __DIR__ .  '/_admin-page-getting-started-areas/_start-optimization.php';
	        } elseif ($data['for'] === 'video-tutorials') {
		        include_once __DIR__ .  '/_admin-page-getting-started-areas/_video-tutorials.php';
	        }
            // [wpacu_lite]
            elseif ($data['for'] === 'lite-vs-pro') {
                include_once WPACU_PLUGIN_DIR . '/templates/__lite/_admin-page-getting-started-areas/_lite-vs-pro.php';
            }
            // [/wpacu_lite]
            ?>
        </div>
    </div>
</div>