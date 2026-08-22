<?php
namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\MainAdmin;
use WpAssetCleanUp\Admin\MiscAdmin;

/**
 *
 * Class BulkChanges
 * @package WpAssetCleanUp
 */
class BulkChanges
{
    /**
     * @var string
     */
    public $wpacuFor = 'everywhere';

    /**
     * @var string
     */
    public $wpacuPostType = 'post';

    /**
     * @var array
     */
    public $data = array();

    /**
     * Includes bulk unload rules, RegEx unloads & load exceptions
     *
     * BulkChanges constructor.
     */
    public function __construct()
    {
	    $this->wpacuFor      = sanitize_text_field(Misc::getVar('request', 'wpacu_for',       $this->wpacuFor));
	    $this->wpacuPostType = sanitize_text_field(Misc::getVar('request', 'wpacu_post_type', $this->wpacuPostType));

        if (Misc::getVar('request', 'wpacu_update') == 1) {
            $this->update();
        }

        add_action('wpacu_below_menu_admin_notices', array($this, 'bulkChangesDeprecatedNotice')); // deprecated
    }

    // To be added when edit overview mode is completed as well
    /**
     * @return void
     */
    public function bulkChangesDeprecatedNotice()
    {
        ?>
        <div style="width: 95%; margin: 0 0 22px 0; padding: 18px; border-left: 4px solid #72aee6; background: #f0f6fc; border-radius: 6px; color: #333; line-height: 1.6;">
            <div style="font-size: 18px; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center;">
                <svg style="flex: 0 0 23px; width: 23px; height: 23px; margin-right: 8px;" viewBox="0 0 20 20" aria-hidden="true">
                    <rect x="1" y="1" width="18" height="18" rx="3" fill="#5f8faf"/>
                    <circle cx="10" cy="5.5" r="1.2" fill="#fff"/>
                    <rect x="9" y="8" width="2" height="7" rx="1" fill="#fff"/>
                </svg>
                <?php esc_html_e('Bulk Changes is now a legacy page', 'wp-asset-clean-up'); ?>
            </div>

            <div style="font-size: 14px;">
                <p style="max-width: 1050px; margin: 0 0 12px;">
                    <?php esc_html_e('Bulk rules can now be reviewed and managed more efficiently from Overview, including rules created through CSS/JS Manager or Plugins Manager and dormant rules left behind by deleted pages or deactivated plugins.', 'wp-asset-clean-up'); ?>
                </p>
                <p style="max-width: 1050px; margin: 0 0 15px;">
                    <?php esc_html_e('Bulk Changes will no longer appear in the plugin navigation at the top or in the WordPress sidebar menu. The page will remain available through its direct URL or an existing bookmark for users familiar with the legacy workflow. We recommend using Overview for ongoing rule management.', 'wp-asset-clean-up'); ?>
                </p>
                <p style="margin: 0;">
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_overview')); ?>"><?php esc_html_e('Open Overview', 'wp-asset-clean-up'); ?></a>
                    <span style="display: inline-block; margin-left: 10px; color: #52636b;"><?php esc_html_e('Direct URL access to Bulk Changes will remain available.', 'wp-asset-clean-up'); ?></span>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * @return array
     */
    public function getCount()
    {
        $values = array();

        if ($this->wpacuFor === 'everywhere') {
            $values = Main::instance()->getGlobalUnload();
        } elseif ($this->wpacuFor === 'post_types') {
            if (strpos($this->wpacuPostType, 'wpacu_custom_post_type_archive') === false) {
	            // For the singular page belonging to the post type (e.g. /news/the-post-title-here/)
	            $values = Main::instance()->getBulkUnload( 'post_type', $this->wpacuPostType );
            }
        }

        $values = apply_filters('wpacu_internal_bulk_changes_get_count', $values, $this);

	    if ( ! empty($values['styles']) ) {
		    sort($values['styles']);
	    }

	    if ( ! empty($values['scripts']) ) {
		    sort($values['scripts']);
	    }

        return $values;
    }

    /**
     *
     */
    public function pageBulkUnloads()
    {
	    $this->data['assets_info'] = Main::getHandlesInfo();
	    $this->data = apply_filters('wpacu_internal_bulk_changes_page_bulk_unloads_data', $this->data, $this);

        if ( ! isset($this->data['values']) ) {
            /*
             * Bulk Unloaded (page types)
             * e.g. Everywhere, Posts, Pages &amp; Custom Post Types, Taxonomies, etc.
            */
	        $this->data['for'] = $this->wpacuFor;

	        if ( $this->wpacuFor === 'post_types' ) {
		        $this->data['post_type'] = $this->wpacuPostType;

		        // Get All Public Post Types List
		        $postTypes                     = get_post_types( array( 'public' => true ) );
		        $this->data['post_types_list'] = MiscAdmin::filterPostTypesList( $postTypes );
	        }

            $this->data = apply_filters('wpacu_internal_bulk_changes_page_bulk_unloads_common_data', $this->data, $this);

            $this->data['values'] = $this->getCount();
        }

        $this->data['nonce_name'] = Update::NONCE_FIELD_NAME;
        $this->data['nonce_action'] = Update::NONCE_ACTION_NAME;

        $this->data['plugin_settings'] = Main::instance()->settings;

        MainAdmin::instance()->parseTemplate('admin-page-settings-bulk-changes', $this->data, true);
    }

	/**
	 * @param $postTypesList
	 * @param $currentPostType
	 */
	public static function buildPostTypesListDd($postTypesList, $currentPostType)
    {
        $ddList = array();

	    foreach ($postTypesList as $postTypeKey => $postTypeValue) {
	        if (in_array($postTypeKey, array('post', 'page', 'attachment'))) {
		        $ddList['WordPress (default)'][$postTypeKey] = $postTypeValue;
            } else {
		        $ddList['Custom Post Types (Singular pages)'][$postTypeKey] = $postTypeValue;

		        $list = Main::instance()->getBulkUnload('custom_post_type_archive_'.$postTypeKey);

		        // At least one of the buckets ('styles' or 'scripts') needs to contain something
		        if (! empty($list['styles']) || ! empty($list['scripts'])) {
			        $ddList['Custom Post Types (Archive pages)'][ 'wpacu_custom_post_type_archive_'.$postTypeKey ] = $postTypeValue. ' (archive page)';
		        }
            }
	    }
	    ?>
        <select id="wpacu_post_type_select" name="wpacu_post_type">
		    <?php
            foreach ($ddList as $groupLabel => $groupPostTypesList) {
                echo '<optgroup label="'.$groupLabel.'">';

                foreach ($groupPostTypesList as $postTypeKey => $postTypeValue) {
                    ?>
                    <option <?php if ($currentPostType === $postTypeKey) { echo 'selected="selected"'; } ?> value="<?php echo esc_attr($postTypeKey); ?>"><?php echo esc_html($postTypeValue); ?></option>
		            <?php
                }

	            echo '</optgroup>';
            }
            ?>
        </select>
        <?php
    }

    /**
     *
     */
    public function update()
    {
        if ( ! Misc::getVar('post', 'wpacu_bulk_unloads_update_nonce') ) {
            return;
        }

	    check_admin_referer('wpacu_bulk_unloads_update', 'wpacu_bulk_unloads_update_nonce');

        $wpacuUpdate = new Update;

        if ($this->wpacuFor === 'everywhere') {
            $removed = $wpacuUpdate->removeEverywhereUnloads(array(), array(), 'post');

            if ($removed) {
                add_action('wpacu_admin_notices', array($this, 'noticeGlobalsRemoved'));
            }
        }

	    if ($this->wpacuFor === 'post_types' && strpos($this->wpacuPostType, 'wpacu_custom_post_type_archive_') === false) {
		    $removed = $wpacuUpdate->removeBulkUnloads(array(), array(), 'post_type', $this->wpacuPostType, 'post');

		    if ($removed) {
			    add_action('wpacu_admin_notices', array($this, 'noticePostTypesRemoved'));
		    }
	    }

        do_action('wpacu_internal_bulk_changes_update', $this, $wpacuUpdate);
    }

    /**
     *
     */
    public function noticeGlobalsRemoved()
    {
    ?>
        <div class="updated notice wpacu-notice is-dismissible">
            <p><span class="dashicons dashicons-yes"></span>
                <?php
                _e('The selected styles/scripts were removed from the global unload list and they will now load in the pages/posts, unless you have other rules that would prevent them from loading.', 'wp-asset-clean-up');
                ?>
            </p>
        </div>
    <?php
    }

	/**
	 *
	 */
	public function noticePostTypesRemoved()
	{
		?>
        <div class="updated notice wpacu-notice is-dismissible">
            <p><span class="dashicons dashicons-yes"></span>
				<?php
				echo sprintf(
					__('The selected styles/scripts were removed from the unload list for <strong><u>%s</u></strong> post type and they will now load in the pages/posts, unless you have other rules that would prevent them from loading.', 'wp-asset-clean-up'),
					$this->wpacuPostType
				);
				?>
            </p>
        </div>
		<?php
	}
}
