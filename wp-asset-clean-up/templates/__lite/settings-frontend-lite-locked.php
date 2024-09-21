<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
	exit;
}
?>
<div id="wpacu-frontend-form">
    <div id="wpacu_wrap_assets">
		<?php
        // Category, Tag, Search, 404, Author, Date pages (not supported by Lite version)
        $contentUnlockFeature = ' <p class="pro-page-unlock-notice">'.__('To unlock this feature, you can upgrade to the Pro version.', 'wp-asset-clean-up').'</p>';
        $utm_medium = 'n_a'; // not available

        if (\WpAssetCleanUp\Main::isWpDefaultSearchPage()) {
            echo '<span class="dashicons dashicons-search"></span> '.__('This is a <strong>WordPress Search Page</strong> and managing CSS &amp; JS (unload, defer, async, etc.) for this page type can be done in Asset CleanUp Pro.', 'wp-asset-clean-up') . $contentUnlockFeature;
            $utm_medium = 'search_page';
        } elseif (is_404()) {
            echo '<span class="dashicons dashicons-warning"></span> '.__('This is a <strong>404 (Not Found) Page</strong> and managing CSS &amp; JS (unload, defer, async, etc.) for this page type can be done in Asset CleanUp Pro.', 'wp-asset-clean-up') . $contentUnlockFeature;
            $utm_medium = '404_not_found_page';
        } elseif (is_author()) {
            echo '<span class="dashicons dashicons-admin-users"></span> '.__('This is an <strong>Author Page</strong> and managing CSS &amp; JS (unload, defer, async, etc.) for this page type can be done in Asset CleanUp Pro.', 'wp-asset-clean-up') . $contentUnlockFeature;
            $utm_medium = 'author_page';
        } elseif (is_category()) {
            echo '<span class="dashicons dashicons-category"></span> '.__('This is a <strong>Category (Taxonomy) Page</strong> and managing CSS &amp; JS (unload, defer, async, etc.) for this page type can be done in Asset CleanUp Pro.', 'wp-asset-clean-up') . $contentUnlockFeature;
            $utm_medium = 'category_page';
        } elseif (function_exists('is_product_category') && is_product_category()) {
            echo '<img src="'.WPACU_PLUGIN_URL . '/assets/icons/woocommerce-icon-logo.svg'.'" alt="" style="height: 40px !important; margin-top: -6px; margin-right: 5px;" align="middle" /> '.__('This is a <strong>WooCommerce Product Category (Taxonomy) Page</strong> and managing CSS &amp; JS (unload, defer, async, etc.) for this page type can be done in Asset CleanUp Pro.', 'wp-asset-clean-up') . $contentUnlockFeature;
            $utm_medium = 'woo_product_category_page';
        } elseif (is_date()) {
            echo '<span class="dashicons dashicons-calendar-alt"></span> '.__('This is a <strong>Date (Archive) Page</strong> and managing (unload, defer, async etc.) CSS &amp; JS for this page type can be done in Asset CleanUp Pro.', 'wp-asset-clean-up') . $contentUnlockFeature;
            $utm_medium = 'date_page';
        } elseif (is_tag()) {
            echo '<span class="dashicons dashicons-tag"></span> '.__('This is a <strong>Tag (Archive) Page</strong> and managing CSS &amp; JS (unload, defer, async, etc.) for this page type can be done in Asset CleanUp Pro.', 'wp-asset-clean-up') . $contentUnlockFeature;
            $utm_medium = 'tag_page';
        } elseif (is_tax()) {
            echo '<span class="dashicons dashicons-tag"></span> '.__('This is a <strong>Taxonomy Page</strong> and managing CSS &amp; JS (unload, defer, async, etc.) for this page type can be done in Asset CleanUp Pro.', 'wp-asset-clean-up') . $contentUnlockFeature;
            $utm_medium = 'taxonomy_page';
        } elseif (\WpAssetCleanUp\MainFront::isHomePage()) {
            $utm_medium = 'home_page';
        }
        ?>
        <p>
            <a class="go-pro-button" target="_blank" href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=front_end_manage&utm_medium='.esc_attr($utm_medium)); ?>">
                <span class="dashicons dashicons-cart"></span>&nbsp; <?php _e('Upgrade to Asset CleanUp Pro', 'wp-asset-clean-up'); ?></a>
        </p>
    </div>
</div>