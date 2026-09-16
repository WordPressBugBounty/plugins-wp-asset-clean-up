<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\Overview;

if (! isset($data)) {
	exit;
}
?>
<!-- [Page Options Area] -->
<?php
$hasPostsWithOptions    = ! empty($data['page_options_results']['posts']);
$hasHomepageWithOptions = ! empty( $data['page_options_results']['homepage']['options'] );
$hasAtLeastOneRecord    = $hasPostsWithOptions || $hasHomepageWithOptions;

$pageOptionLabels = array(
    'no_css_minify'      => __('CSS minification', 'wp-asset-clean-up'),
    'no_css_optimize'    => __('CSS combination', 'wp-asset-clean-up'),
    'no_js_minify'       => __('JavaScript minification', 'wp-asset-clean-up'),
    'no_js_optimize'     => __('JavaScript combination', 'wp-asset-clean-up'),
    'no_assets_settings' => __('All front-end optimizations', 'wp-asset-clean-up'),
    'no_wpacu_load'      => __('Asset CleanUp functionality', 'wp-asset-clean-up'),
);
$renderPageOption = static function ($optionKey, $optionValue, $infoData, $pageOptions) use ($pageOptionLabels, $data) {
    $overridden = ($optionKey !== 'no_wpacu_load' && !empty($pageOptions['no_wpacu_load']))
        || (!in_array($optionKey, array('no_assets_settings', 'no_wpacu_load'), true) && !empty($pageOptions['no_assets_settings']));
    $label = isset($pageOptionLabels[$optionKey]) ? $pageOptionLabels[$optionKey] : $data['page_options_to_text'][$optionKey];
    $output = Overview::renderNoWrapRuleOutput(
        '<span style="color: #cc0000; font-weight: 400;">' . esc_html($label) . '</span>',
        $infoData,
        $optionKey,
        $optionValue
    );
    $description = '';
    if ($optionKey === 'no_assets_settings') {
        $description = __('CSS/JS unload rules and other optimization settings are saved but will not take effect on this page.', 'wp-asset-clean-up');
    } elseif ($optionKey === 'no_wpacu_load') {
        $description = __('Asset CleanUp does not load on this page, so none of its front-end functionality or optimization rules will apply.', 'wp-asset-clean-up');
    }
    if ($description !== '') {
        $output .= '<small style="display: block; margin-top: 4px; color: #50575e; line-height: 1.5;">' . esc_html($description) . '</small>';
    }
    return '<div data-wpacu-rule-state="' . ($overridden ? 'inactive' : 'active') . '" style="margin: 6px 0;' . ($overridden ? ' opacity: 0.45;' : '') . '">' . $output . '</div>';
};
?>
<div id="wpacu-page-options-wrap">
    <h3 id="wpacu-overview-section-page-options" class="wpacu-overview-section-title"><span class="wpacu-overview-section-title-content"><span class="dashicons dashicons-admin-generic"></span> <?php _e('Page Options', 'wp-asset-clean-up'); ?></span> <a class="wpacu-overview-back-to-navigation" href="#wpacu-overview-start" aria-label="<?php esc_attr_e('Back to Overview navigation', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></a></h3>
    <div style="padding: 10px; background: white; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
		<?php
		if ($hasAtLeastOneRecord) {
		?>
        <p style="margin: 8px 0;"><strong><?php esc_html_e('Optimizations disabled on specific pages', 'wp-asset-clean-up'); ?></strong></p>
        <p style="margin: 0 0 8px; line-height: 1.6;"><em><?php esc_html_e('Pages appear in this list because one or more options were checked and saved under "Page Options" in the CSS/JS Manager for each page.', 'wp-asset-clean-up'); ?></em></p>
        <p style="margin: 0 0 14px; line-height: 1.6;"><?php esc_html_e('These page options override your Asset CleanUp settings. They can disable minification, combination, or all optimizations — including CSS/JS unload rules — on individual pages.', 'wp-asset-clean-up'); ?>
            <a target="_blank" rel="noopener noreferrer" style="text-decoration: none; white-space: nowrap;" href="https://www.assetcleanup.com/docs/?p=1318"><span class="dashicons dashicons-info" aria-hidden="true"></span> <?php esc_html_e('Read more', 'wp-asset-clean-up'); ?></a>
        </p>
        <?php if (Overview::isEditMode()) { ?>
            <p style="padding: 10px 12px; margin: 0 0 14px; background: #fff8e5; border-left: 3px solid #dba617; line-height: 1.6;">
                <strong><?php esc_html_e('Select page overrides to remove', 'wp-asset-clean-up'); ?></strong><br />
                <?php esc_html_e('Select the page overrides you want to remove, then apply your changes. Removing an override allows the corresponding settings and rules to apply again. These checkboxes select overrides for removal; they do not enable or disable optimizations directly.', 'wp-asset-clean-up'); ?>
            </p>
        <?php } ?>
        <table class="wp-list-table wpacu-list-table widefat fixed plugins striped" style="margin: 10px 0 0; width: 100%; overflow-wrap: anywhere;">
            <thead><tr>
                <th scope="col" style="width: 45%;"><span class="wpacu-overview-homepage-label"><strong><?php esc_html_e('Page', 'wp-asset-clean-up'); ?></strong> <span class="wpacu-overview-homepage-help" style="font-size: 17px; line-height: 17px; vertical-align: middle; margin: -3px 0 0 -1px; display: inline-flex; align-items: center;" tabindex="0" role="img" aria-label="<?php esc_attr_e('About page targets', 'wp-asset-clean-up'); ?>" aria-describedby="wpacu-page-options-page-help"><span aria-hidden="true">ⓘ</span><span id="wpacu-page-options-page-help" class="wpacu-overview-homepage-tooltip" role="tooltip"><strong><?php esc_html_e('Page targets', 'wp-asset-clean-up'); ?></strong><?php esc_html_e('This column identifies the page context targeted by the saved Page Options. It can include posts, standard WordPress pages, and custom post types. Each row lists the options disabled for that specific page.', 'wp-asset-clean-up'); ?></span></span></span></th>
                <th scope="col"><span class="dashicons dashicons-dismiss" style="width: 20px; height: 20px; color: #cc0000; font-size: 20px; line-height: 20px; vertical-align: middle; margin: -2px 0 0;" aria-hidden="true"></span> <strong style="color: #cc0000;"> <?php esc_html_e('Disabled options', 'wp-asset-clean-up'); ?></strong></th>
            </tr></thead>
            <tbody>
			<?php
			}

			if ( $hasHomepageWithOptions ) {
				$optionsForCurrentPage = array();

				foreach ($data['page_options_results']['homepage']['options'] as $optionKey => $optionValue) {
					if (isset($data['page_options_to_text'][$optionKey]) && $optionValue) {
                        $infoData = array(
                            'page_type'   => 'homepage',
                            'page_option' => $optionKey,
                        );

                        $optionsForCurrentPage[] = $renderPageOption(
                            $optionKey,
                            $optionValue,
                            $infoData,
                            $data['page_options_results']['homepage']['options']
                        );
					}
				}
				?>
                <tr>
                    <td><span class="dashicons dashicons-admin-home"></span> Homepage (e.g. latest posts)<br /><small><a target="_blank" href="<?php echo get_site_url(); ?>"><?php echo get_site_url(); ?></a></small></td>
                    <td>
                    <?php
                    if (Overview::isViewMode()) {
                        $optionsForCurrentPage = array_map(static function ($value) {
                            return $value;
                        }, $optionsForCurrentPage);

                        echo implode('', $optionsForCurrentPage);
                    } else {
                        echo implode('', $optionsForCurrentPage);
                    }
                    ?>
                    </td>
                </tr>
				<?php
			}

			if ( $hasPostsWithOptions ) {
				foreach ($data['page_options_results']['posts'] as $results) {
					$rowStyle = '';
					$postExists = true;

                    if (get_post($results['post_id']) === null) {
                        $postExists = false;
	                    $postStatus = $postStatusText = '';
	                    $rowStyle   = 'style="opacity: 0.6;"';
                    } else {
	                    $postStatus = $postStatusText = get_post_status( $results['post_id'] );

	                    if ( ! in_array( $postStatus, array( 'publish', 'private' ) ) ) {
		                    $rowStyle       = 'style="opacity: 0.6;"';
		                    $postStatusText = '<span style="color: #cc0000;">' . $postStatus . '</span>';
	                    }
                    }
					?>
                    <tr data-wpacu-rule-state="<?php echo $rowStyle !== '' ? 'inactive' : 'active'; ?>" <?php echo wp_kses($rowStyle, array('style' => array())); ?>>
                        <td>
                            <?php if ($postExists) { ?>
                                <?php echo get_the_title($results['post_id']); ?> / ID: <?php echo (int)$results['post_id']; ?>, Status: <?php echo wp_kses($postStatusText, array('span' => array('style' => array()))); ?>
                                <?php if (get_option('show_on_front') === 'page' && (int)get_option('page_on_front') === (int)$results['post_id']) { ?>
                                    <span class="wpacu-overview-homepage-label" style="display: inline-flex; align-items: center; gap: 4px; margin-left: 8px; color: #006b78; vertical-align: middle; white-space: nowrap;"><span class="dashicons dashicons-admin-home" aria-hidden="true" style="font-size: 16px; width: 16px; height: 16px;"></span><strong><?php esc_html_e('Homepage', 'wp-asset-clean-up'); ?></strong>
                                        <span class="wpacu-overview-homepage-help" tabindex="0" role="img" aria-label="<?php esc_attr_e('About this homepage', 'wp-asset-clean-up'); ?>" aria-describedby="wpacu-overview-homepage-help-<?php echo (int)$results['post_id']; ?>">
                                            <span aria-hidden="true">ⓘ</span>
                                            <span id="wpacu-overview-homepage-help-<?php echo (int)$results['post_id']; ?>" class="wpacu-overview-homepage-tooltip" role="tooltip"><strong><?php esc_html_e('Static homepage', 'wp-asset-clean-up'); ?></strong><?php esc_html_e('This is a regular WordPress page selected as the site’s homepage under Settings → Reading → Your homepage displays → A static page → Homepage.', 'wp-asset-clean-up'); ?></span>
                                        </span>
                                    </span>
                                <?php } ?>
                                <br /><small><a target="_blank" href="<?php echo get_permalink($results['post_id']); ?>"><?php echo get_permalink($results['post_id']); ?></a></small>
                            <?php } else { ?>
                                ID: <s style="color: #cc0000;" class="wpacu-tooltip" title="N/A (post deleted)"><?php echo (int)$results['post_id']; ?></s>
                            <?php } ?>
                        </td>
                        <td>
							<?php
							$optionsForCurrentPage = array();

                            foreach ($results['options'] as $optionKey => $optionValue) {
								if ($optionKey === '_page_uri') {
									// Hidden and irrelevant
									continue;
								}

								if (isset($data['page_options_to_text'][$optionKey]) && $optionValue) {
                                    $infoData = array(
                                        'page_type'   => 'post',
                                        'post_id'     => $results['post_id'],
                                        'page_option' => $optionKey,
                                    );

                                    $optionsForCurrentPage[] = $renderPageOption(
                                        $optionKey,
                                        $optionValue,
                                        $infoData,
                                        $results['options']
                                    );
								}
							}

                            if (Overview::isViewMode()) {
                                $optionsForCurrentPage = array_map(static function ($value) {
                                    return $value;
                                }, $optionsForCurrentPage);

                        echo implode('', $optionsForCurrentPage);
                            } else {
                        echo implode('', $optionsForCurrentPage);
                            }
                            ?>
                        </td>
                    </tr>
					<?php
				}
			}

			if ($hasAtLeastOneRecord) {
			?>
            </tbody>
        </table>
	<?php
	}
	?>

		<?php if ( ! $hasAtLeastOneRecord ) { ?>
            <?php esc_html_e('No page-specific overrides are disabling optimizations.', 'wp-asset-clean-up'); ?> <a style="text-decoration: none;" target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=1318"><span class="dashicons dashicons-info" aria-hidden="true"></span> <?php esc_html_e('Read more', 'wp-asset-clean-up'); ?></a>
		<?php } ?>
    </div>
</div>
<!-- [/Page Options Area] -->
