<?php
namespace WpAssetCleanUpLite\Admin;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Admin\PluginAnnouncements;
use WpAssetCleanUp\Admin\SettingsAdmin;

/**
 * The code is triggered only in Asset CleanUp Lite
 *
 * Class Lite
 * @package WpAssetCleanUp
 */
class UpgradeNotices
{
    /**
     * Read Lite's bundled export on the site Dashboard, Plugins list and Asset CleanUp screens.
     *
     * @param array      $announcements
     * @param \WP_Screen $screen
     *
     * @return array
     */
    public static function bundledAnnouncements($announcements, $screen)
    {
        if (PluginAnnouncements::getScreenLocation($screen) === '') {
            return $announcements;
        }

        if (SettingsAdmin::getStoredAnnouncementPreference() !== 'undecided') {
            return $announcements;
        }

        return array_merge($announcements, self::readBundledAnnouncements());
    }

    /**
     * Return the current public upgrade discount for the Lite sidebar link.
     *
     * @return int Percentage, or zero when no bundled offer applies.
     */
    public static function getBundledMenuDiscount()
    {
        $now = current_time('timestamp', true);

        foreach (self::readBundledAnnouncements() as $record) {
            if ( ! is_array($record) || ! isset($record['type'], $record['menu_discount_percent'], $record['start_date'], $record['end_date']) ) {
                continue;
            }

            $discount = $record['menu_discount_percent'];
            if ( ! isset($record['theme']) || $record['theme'] !== 'black-friday' || $record['type'] !== 'offer' || array_key_exists('conditions', $record)
                || ! is_int($discount) || $discount < 1 || $discount > 100) {
                continue;
            }

            $start = PluginAnnouncements::parseAnnouncementDate($record['start_date']);
            $end   = PluginAnnouncements::parseAnnouncementDate($record['end_date']);
            if ($start !== false && $end !== false && $start <= $now && $end - $now >= PluginAnnouncements::UPGRADE_LINK_MIN_TIME_REMAINING) {
                return $discount;
            }
        }

        return 0;
    }

    /**
     * @return int Current upgrade discount percentage, or zero outside an eligible campaign.
     */
    public static function getUpgradeDiscount()
    {
        static $discount;
        if ($discount === null) {
            if (PluginAnnouncements::isShowAnnouncementsEnabled()) {
                $announcements = new PluginAnnouncements();
                $discount      = $announcements->getBlackFridayUpgradeDiscount();
            } else {
                $discount = self::getBundledMenuDiscount();
            }
        }

        return $discount;
    }

    /**
     * @param string $label Existing Lite upgrade link text.
     *
     * @return string
     */
    public static function upgradeLabel($label)
    {
        $discount = self::getUpgradeDiscount();
        if ($discount === 0) {
            return $label;
        }

        /* translators: 1: upgrade action, 2: percentage discount. */
        return sprintf(__('%1$s · Black Friday: Save %2$d%%', 'wp-asset-clean-up'), $label, $discount);
    }

    /**
     * Read the packaged campaigns once per request for both the menu and notices.
     *
     * @return array
     */
    private static function readBundledAnnouncements()
    {
        static $records;
        if (isset($records)) {
            return $records;
        }

        $records = array();
        $file    = WPACU_PLUGIN_DIR . '/lite/assets/announcements.json';
        if ( ! is_file($file) || ! is_readable($file) ) {
            return $records;
        }

        // An update can replace this optional file between the checks and the read.
        $json = @file_get_contents($file, false, null, 0, 262145);
        if ($json === false || strlen($json) > 262144) {
            return $records;
        }

        $json = ltrim($json);
        if ($json === '' || $json[0] !== '[') {
            return $records;
        }

        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $records = $decoded;
        }

        return $records;
    }

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

                        <?php if (\WpAssetCleanUpLite\ArchiveRules::supports($tag->taxonomy)) { ?>
                            <a href="<?php echo esc_url(add_query_arg(array(
                                'page' => WPACU_PLUGIN_ID . '_assets_manager',
                                'wpacu_for' => $tag->taxonomy === 'category' ? 'category' : 'tag',
                                'wpacu_taxonomy' => $tag->taxonomy,
                                'wpacu_term_id' => (int)$tag->term_id
                            ), admin_url('admin.php'))); ?>"><?php esc_html_e('Manage CSS/JS for this archive', 'wp-asset-clean-up'); ?></a>
                        <?php } else { ?>
						<img width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" valign="top" alt="" /> &nbsp;
						<?php
						echo sprintf(
							__('Managing the loading of the styles &amp; scripts files for this <strong>%s</strong> taxonomy is %savailable in the Pro version%s', 'wp-asset-clean-up'),
							$tag->taxonomy,
							'<a href="'.apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=taxonomy_edit_page&utm_medium=upgrade_link').'" target="_blank">',
							'</a>'
						);
						?>
                        <?php } ?>
					</td>
				</tr>
				<?php
			});
		}
        // [/Edit Taxonomy]
	}

	}
