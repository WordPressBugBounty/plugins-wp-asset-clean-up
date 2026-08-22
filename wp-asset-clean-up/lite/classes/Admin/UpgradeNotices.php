<?php
namespace WpAssetCleanUpLite\Admin;

use WpAssetCleanUp\Main;

/**
 * The code is triggered only in Asset CleanUp Lite
 *
 * Class Lite
 * @package WpAssetCleanUp
 */
class UpgradeNotices
{
    /**
	 *
	 */
	public static function currentScreen()
	{
        // Location: 'Settings' -- 'Plugin Usage Preferences' -- 'Show "Asset CleanUp: CSS & JS Manager" meta box in edit post/page/taxonomy area?'
        if (Main::instance()->settings['show_assets_meta_box']) {
            return;
        }

		$current_screen = \get_current_screen();

        // [Edit Taxonomy]
		if ($current_screen->base === 'term' && isset($current_screen->taxonomy) && $current_screen->taxonomy != '') {
			add_action ($current_screen->taxonomy . '_edit_form_fields', static function ($tag) {
				?>
				<tr class="form-field">
					<th scope="row" valign="top"><label for="wpassetcleanup_list"><?php echo WPACU_PLUGIN_TITLE; ?>: <?php _e('CSS &amp; JavaScript Load Manager', 'wp-asset-clean-up'); ?></label></th>
					<td data-wpacu-taxonomy="<?php echo esc_attr($tag->taxonomy); ?>">
						<img width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" valign="top" alt="" /> &nbsp;
						<?php
						echo sprintf(
							__('Managing the loading of the styles &amp; scripts files for this <strong>%s</strong> taxonomy is %savailable in the Pro version%s', 'wp-asset-clean-up'),
							$tag->taxonomy,
							'<a href="'.apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=taxonomy_edit_page&utm_medium=upgrade_link').'" target="_blank">',
							'</a>'
						);
						?>
					</td>
				</tr>
				<?php
			});
		}
        // [/Edit Taxonomy]
	}

	}
