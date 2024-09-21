<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\MiscAdmin;

if (! isset($data)) {
	exit;
}
?>
<div style="margin: 0 0 15px;">
	<?php
	$loadSearchFormForPages = true;

    $searchPlaceholderText = sprintf(__('You can type a keyword or the ID to search the %s for which you want to manage its CSS/JS (e.g. unloading)', 'wp-asset-clean-up'), $data['post_type']);

	// Anything that is not within the array, is a custom post type
	if (isset($data['for'])) {
        if ($data['for'] === 'custom_post_types') {
            $postTypes = get_post_types( array( 'public' => true ) );

            if ( ! empty($postTypes) ) {
                $postTypesList = MiscAdmin::filterCustomPostTypesList($postTypes);
            ?>
                After you choose the custom post type, you can then search within all the posts that are within your choice:
                <select id="wpacu-custom-post-type-choice">
                    <?php foreach ($postTypesList as $listPostType => $listPostTypeLabel) { ?>
                        <option <?php if ($data['post_type'] === $listPostType) { echo 'selected="selected"'; } ?> value="<?php echo esc_attr($listPostType); ?>"><?php echo esc_html($listPostTypeLabel); ?></option>
                    <?php } ?>
                </select>
            <?php } else { ?>
                <div style="padding: 10px; background: white; border-radius: 10px; border: 1px solid #c3c4c7;">
                    <span class="dashicons dashicons-warning" style="color: #004567;"></span> You do not have any custom post types available. Thus, this area is not usable. Once you will use plugins that have custom post types (e.g. WooCommerce with its "product" post type), or you will add them within your (child) theme, they will show up here, so you can manage the pages belonging to specific post types.
                </div>
            <?php } ?>

            <div style="margin: 0 0 15px;"></div>
		<?php
        } elseif ($data['for'] === 'posts') {
           $posts = get_posts(array('post_type' => 'post', 'post_status' => 'publish,private'));
	        if (empty($posts)) {
	           $loadSearchFormForPages = false; // no posts added
               ?>
               <div style="padding: 10px; background: white; border-radius: 10px; border: 1px solid #c3c4c7;">
                   <span class="dashicons dashicons-warning" style="color: #004567;"></span> There aren't any posts added in <a style="text-decoration: none;" target="_blank" href="<?php echo admin_url('edit.php'); ?>"><span class="dashicons dashicons-admin-post"></span> "Posts" --&gt; "All Posts"</a>.
               </div>
               <?php
           }
        } elseif ($data['for'] === 'pages') {
	        $pages = get_pages(array('post_type' => 'page', 'post_status' => array('publish', 'private')));
	        if (empty($pages)) {
		        $loadSearchFormForPages = false; // no pages added
		        ?>
                <div style="padding: 10px; background: white; border-radius: 10px; border: 1px solid #c3c4c7;">
                    <span class="dashicons dashicons-warning" style="color: #004567;"></span> There aren't any pages added in <a style="text-decoration: none;" target="_blank" href="<?php echo admin_url('edit.php?post_type=page'); ?>"><span class="dashicons dashicons-admin-page"></span> "Pages" --&gt; "All Pages"</a>.
                </div>
		        <?php
	        }
        }
	}

    if (isset($postTypes) && empty($postTypes)) {
	    $loadSearchFormForPages = false; // no post types added
    }

    if ($loadSearchFormForPages) {
    ?>
        <form id="wpacu-search-form-assets-manager">
            Load assets manager for:
            <input type="text"
                   class="search-field"
                   value=""
                   placeholder="<?php echo esc_attr($searchPlaceholderText); ?>"
                   style="max-width: 800px; width: 100%; padding-right: 15px;" />
            * <small>Once the post is selected, the CSS &amp; JS manager will load to manage the assets for the chosen post</small>
            <div style="display: none; padding: 10px; color: #cc0000;" id="wpacu-search-form-assets-manager-no-results"><span class="dashicons dashicons-warning"></span> <?php _e('There are no results based on your search', 'wp-asset-clean-up'); ?>. <?php echo sprintf(__('Remember that you can also use the %s ID in the input', 'wp-asset-clean-up'), $data['post_type']); ?>.</div>
        </form>

        <div style="display: none;" id="wpacu-post-chosen-loading-assets">
            <img style="margin: 2px 0 4px;"
                 src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/loader-horizontal.svg?x=<?php echo time(); ?>"
                 align="top"
                 width="120"
                 alt="" />
        </div>
    <?php
    }
    ?>
</div>