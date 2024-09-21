<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\BulkChanges;

if (! isset($data)) {
	exit;
}

$possibleWpacuFor = array('everywhere', 'post_types', 'taxonomies', 'authors', 'search_results', 'dates', '404_not_found');

if ( ! in_array($data['for'], $possibleWpacuFor) ) {
    exit('Invalid request');
}

// [wpacu_lite]
$availableForPro = '<span class="wpacu-tooltip">'
        .esc_html__('Available for Pro users', 'wp-asset-clean-up').
        '<br />'.esc_html__('Click to upgrade!', 'wp-asset-clean-up')
  .'</span>'.
  ' <img style="opacity: 0.4;" width="20" height="20" src="'.WPACU_PLUGIN_URL.'/assets/icons/icon-lock.svg" valign="top" alt="" />';
// [/wpacu_lite]
?>
<nav class="wpacu-nav-tab-wrapper">
	<a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_bulk_unloads')); ?>" class="wpacu-nav-tab <?php if ($data['for'] === 'everywhere') { ?>wpacu-nav-tab-active<?php } ?>"><?php esc_html_e('Everywhere', 'wp-asset-clean-up'); ?></a>
	<a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_bulk_unloads&wpacu_for=post_types')); ?>" class="wpacu-nav-tab <?php if ($data['for'] === 'post_types') { ?>wpacu-nav-tab-active<?php } ?>">Posts, Pages &amp; Custom Post Types</a>
	<a href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=plugin_bulk_unloaded&utm_medium=tab_link'); ?>" target="_blank" class="wpacu-nav-tab go-pro-link-no-style no-transition"><?php echo wp_kses($availableForPro, array('span' => array('class' => array()), 'br' => array(), 'img' => array('style' => array(), 'width' => array(), 'height' => array(), 'src' => array(), 'valign' => array(), 'alt' => array()))); ?> &nbsp;Taxonomies</a>
	<a href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=plugin_bulk_unloaded&utm_medium=tab_link'); ?>" target="_blank" class="wpacu-nav-tab go-pro-link-no-style no-transition"><?php echo wp_kses($availableForPro, array('span' => array('class' => array()), 'br' => array(), 'img' => array('style' => array(), 'width' => array(), 'height' => array(), 'src' => array(), 'valign' => array(), 'alt' => array()))); ?> &nbsp;Authors</a>
	<a href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=plugin_bulk_unloaded&utm_medium=tab_link'); ?>" target="_blank" class="wpacu-nav-tab go-pro-link-no-style no-transition"><?php echo wp_kses($availableForPro, array('span' => array('class' => array()), 'br' => array(), 'img' => array('style' => array(), 'width' => array(), 'height' => array(), 'src' => array(), 'valign' => array(), 'alt' => array()))); ?> &nbsp;Search Results</a>
	<a href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=plugin_bulk_unloaded&utm_medium=tab_link'); ?>" target="_blank" class="wpacu-nav-tab go-pro-link-no-style no-transition"><?php echo wp_kses($availableForPro, array('span' => array('class' => array()), 'br' => array(), 'img' => array('style' => array(), 'width' => array(), 'height' => array(), 'src' => array(), 'valign' => array(), 'alt' => array()))); ?> &nbsp;Dates</a>
	<a href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=plugin_bulk_unloaded&utm_medium=tab_link'); ?>" target="_blank" class="wpacu-nav-tab go-pro-link-no-style no-transition"><?php echo wp_kses($availableForPro, array('span' => array('class' => array()), 'br' => array(), 'img' => array('style' => array(), 'width' => array(), 'height' => array(), 'src' => array(), 'valign' => array(), 'alt' => array()))); ?> &nbsp;404 Not Found</a>
</nav>

<div class="wpacu_clearfix"></div>

<?php
do_action('wpacu_admin_notices');

if ($data['for'] === 'post_types') {
	?>
    <div style="margin: 15px 0;">
        <form id="wpacu_post_type_form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="wpassetcleanup_bulk_unloads" />
            <input type="hidden" name="wpacu_for" value="post_types" />

            <div style="margin: 0 0 10px 0;">Select the page or post type (including custom ones) for which you want to see the unloaded scripts &amp; styles:</div>
            <?php BulkChanges::buildPostTypesListDd($data['post_types_list'], $data['post_type']); ?>
        </form>
    </div>
	<?php
}
?>

<form action="" method="post">
	<?php
	if ($data['for'] === 'everywhere') {
		?>
        <div class="wpacu_clearfix"></div>

        <div class="alert">
            <p>This is the list of the assets that are <strong>unloaded everywhere</strong> (site-wide) on all pages (including home page). &nbsp;&nbsp;<a data-wpacu-modal-target="wpacu-add-bulk-rules-info-target" href="#wpacu-add-bulk-rules-info" style="text-decoration: none;"><span class="dashicons dashicons-info"></span> How the list below gets filled with site-wide rules?</a></p>
            <p>If you want to remove this rule and have them loading, use the "Remove site-wide rule" checkbox.</p>
            <div style="margin: 0; background: white; padding: 10px; border: 1px solid #ccc; width: auto; display: inline-block;">
                <ul>
                    <li>This list fills once you select "<em>Unload everywhere</em>" when you edit posts/pages for the assets that you want to prevent from loading on every page.</li>
                    <li>On this page you can only remove the global rules that were added while editing the pages/posts.</li>
                </ul>
            </div>
        </div>

        <div class="wpacu_clearfix"></div>

		<div style="padding: 0 10px 0 0;">
			<p style="margin-bottom: 10px;"><strong><?php _e('Stylesheets (.css) Unloaded', 'wp-asset-clean-up'); ?></strong></p>
			<?php
			if (! empty($data['values']['styles'])) {
				?>
                <table class="wp-list-table widefat fixed striped">
                    <tr>
                        <td><strong>Handle</strong></td>
                        <td><strong>Actions</strong></td>
                    </tr>
					<?php
					foreach ($data['values']['styles'] as $handle) {
						?>
                        <tr class="wpacu_global_rule_row wpacu_bulk_change_row">
                            <td>
                                <?php wpacuRenderHandleTd($handle, 'styles', $data); ?>
                            </td>
                            <td>
                                <label><input type="checkbox"
                                              class="wpacu_bulk_rule_checkbox"
                                              name="wpacu_options_styles[<?php echo esc_attr($handle); ?>]"
                                              value="remove" /> Remove site-wide rule</label>
                            </td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
			} else {
				?>
				<p><?php _e('There are no site-wide unloaded styles.', 'wp-asset-clean-up'); ?></p>
				<?php
			}
			?>

            <hr style="margin: 15px 0;"/>

			<p style="margin-bottom: 10px;"><strong><?php _e('Scripts (.js) Unloaded', 'wp-asset-clean-up'); ?></strong></p>
			<?php
			if (! empty($data['values']['scripts'])) {
				?>
                <table class="wp-list-table widefat fixed striped">
                    <tr>
                        <td><strong>Handle</strong></td>
                        <td><strong>Actions</strong></td>
                    </tr>
					<?php
					foreach ($data['values']['scripts'] as $handle) {
						?>
                        <tr class="wpacu_global_rule_row wpacu_bulk_change_row">
                            <td>
	                            <?php wpacuRenderHandleTd($handle, 'scripts', $data); ?>
                            </td>
                            <td>
                                <label><input type="checkbox"
                                              class="wpacu_bulk_rule_checkbox"
                                              name="wpacu_options_scripts[<?php echo esc_attr($handle); ?>]"
                                              value="remove" /> Remove site-wide rule</label>
                            </td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
			} else {
				?>
				<p><?php _e('There are no site-wide unloaded scripts.', 'wp-asset-clean-up'); ?></p>
				<?php
			}
			?>
        </div>
		<?php
	}

	if ($data['for'] === 'post_types') {
		?>
        <div class="wpacu_clearfix"></div>

        <div class="alert">
            <p>This is the list of the assets that are <strong>unloaded</strong> on all pages belonging to the <strong><u><?php echo esc_html($data['post_type']); ?></u></strong> post type. &nbsp;&nbsp;<a data-wpacu-modal-target="wpacu-add-bulk-rules-info-target" href="#wpacu-add-bulk-rules-info" style="text-decoration: none;"><span class="dashicons dashicons-info"></span> How the list below gets filled with site-wide rules?</a></p>
            <p>If you want to make an asset load again, use the "Remove bulk rule" checkbox.</p>
            <div style="margin: 0; background: white; padding: 10px; border: 1px solid #ccc; width: auto; display: inline-block;">
                <ul>
                    <li>This list fills once you select "<em>Unload on All Pages of <strong><?php echo esc_html($data['post_type']); ?></strong> post type</em>" when you edit posts/pages for the assets that you want to prevent from loading.</li>
                    <li>On this page you can only remove the global rules that were added while editing <strong><?php echo esc_html($data['post_type']); ?></strong> post types.</li>
                </ul>
            </div>
        </div>

		<div class="wpacu_clearfix"></div>

		<div style="padding: 0 10px 0 0;">
            <p style="margin-bottom: 10px;"><strong><?php _e('Stylesheets (.css) Unloaded', 'wp-asset-clean-up'); ?></strong></p>
			<?php
			if ( ! empty($data['values']['styles']) ) {
				?>
                <table class="wp-list-table widefat fixed striped">
                    <tr>
                        <td><strong>Handle</strong></td>
                        <td><strong>Actions</strong></td>
                    </tr>
					<?php
					foreach ($data['values']['styles'] as $handle) {
						?>
                        <tr class="wpacu_global_rule_row wpacu_bulk_change_row">
                            <td><?php wpacuRenderHandleTd($handle, 'styles', $data); ?></td>
                            <td>
                                <label><input type="checkbox"
                                              class="wpacu_bulk_rule_checkbox"
                                              name="wpacu_options_post_type_styles[<?php echo esc_attr($handle); ?>]"
                                              value="remove" /> Remove bulk rule</label>
                            </td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
			} else {
				?>
                <p>There are no bulk unloaded styles for the <strong><?php echo esc_html($data['post_type']); ?></strong> post type.</p>
				<?php
			}
			?>

            <hr style="margin: 15px 0;"/>

            <p style="margin-bottom: 10px;"><strong><?php _e('Scripts (.js) Unloaded', 'wp-asset-clean-up'); ?></strong></p>

			<?php
			if ( ! empty($data['values']['scripts']) ) {
				?>
                <table class="wp-list-table widefat fixed striped">
                    <tr>
                        <td><strong>Handle</strong></td>
                        <td><strong>Actions</strong></td>
                    </tr>
					<?php
					foreach ($data['values']['scripts'] as $handle) {
						?>
                        <tr class="wpacu_global_rule_row wpacu_bulk_change_row">
                            <td><?php wpacuRenderHandleTd($handle, 'scripts', $data); ?></td>
                            <td>
                                <label><input type="checkbox"
                                              class="wpacu_bulk_rule_checkbox"
                                              name="wpacu_options_post_type_scripts[<?php echo esc_attr($handle); ?>]"
                                              value="remove" /> Remove bulk rule</label>
                            </td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
			} else {
				?>
                <p>There are no bulk unloaded scripts for the <strong><?php echo esc_html($data['post_type']); ?></strong> post type.</p>
				<?php
			}
			?>
        </div>
		<?php
	}

	$noAssetsToRemove = (empty($data['values']['styles']) && empty($data['values']['scripts']));
	?>
	<?php wp_nonce_field($data['nonce_action'], $data['nonce_name']); ?>

    <input type="hidden" name="wpacu_for" value="<?php echo esc_attr($data['for']); ?>" />
    <input type="hidden" name="wpacu_update" value="1" />

	<?php
	if ($data['for'] === 'post_types' && isset($data['post_type'])) {
		?>
        <input type="hidden" name="wpacu_post_type" value="<?php echo esc_attr($data['post_type']); ?>" />
		<?php
	}
	?>

    <div class="wpacu_clearfix"></div>

    <div id="wpacu-update-button-area" class="no-left-margin">
        <p class="submit">
			<?php
			wp_nonce_field('wpacu_bulk_unloads_update', 'wpacu_bulk_unloads_update_nonce' );
			?>
            <input type="submit"
                   name="submit"
                   id="submit"
				<?php if ($noAssetsToRemove) { ?>
                    disabled="disabled"
				<?php } ?>
                   class="button button-primary"
                   value="<?php esc_attr_e('Apply changes', 'wp-asset-clean-up'); ?>" />
			<?php
			if ($noAssetsToRemove) {
				?>
				&nbsp;<small><?php _e('Note: As there are no unloaded assets (scripts &amp; styles) to be managed, the button is disabled.', 'wp-asset-clean-up'); ?></small>
				<?php
			}
			?>
        </p>
        <div id="wpacu-updating-settings" style="margin-left: 150px;">
            <img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" align="top" width="20" height="20" alt="" />
        </div>
    </div>
</form>
<!-- Start Site-Wide Modal -->
<div id="wpacu-add-bulk-rules-info" class="wpacu-modal">
    <div class="wpacu-modal-content">
        <span class="wpacu-close">&times;</span>
        <h2><?php _e('Unloading CSS/JS site-wide or for a group of pages', 'wp-asset-clean-up'); ?></h2>
        <p>This is an overview of all the assets that have bulk changes applied. Anything you see on this page is filled the moment you go to edit a page via the "CSS/JS Load Manager" (e.g. homepage or a post) and use options such as:</p>

        <ul style="list-style: disc; margin-left: 20px;">
            <li>Unload site-wide (everywhere)</strong></li>
            <li>Unload on All Pages of `product` post type</li>
            <li>Unload on All Pages of `product_cat` taxonomy type etc.</li>
            <li>Unload on this `[custom post type name here]` post type archive page</li>
            <li>Unload on this page type (any 404 Not Found URL), etc.</li>
        </ul>

        <p>A bulk change is considered anything that is applied once, and it has effect on multiple pages of the same kind or site-wide.</p>
    </div>
</div>
<!-- End Site-Wide Modal -->