<?php
/*
 * No direct access to this file
 * This content is placed inside #wpacu_meta_box_content meta box DIV element
 */

use WpAssetCleanUp\AssetsManager;
use WpAssetCleanUp\MainFront;
use WpAssetCleanUp\Settings;
use WpAssetCleanUp\Tips;

// [wpacu_pro]
use WpAssetCleanUp\Misc;
use WpAssetCleanUpPro\MainPro;
// [/wpacu_pro]

if (! isset($data)) {
    exit;
}

global $wp_version;

$data['wp_version'] = $wp_version; // in case there is no version of a CSS/JS WordPress appends its latest version to "ver"

$isLiteProPreview  = ! defined('WPACU_PRO_PLUGIN_VERSION') && ! empty($data['lite_pro_preview_mode']);
$metaBoxLoadedFine = ! (isset($data['is_dashboard_view'], $data['is_wp_error']) && $data['is_dashboard_view'] && $data['is_wp_error']);

if ( ! $metaBoxLoadedFine ) {
    // Errors for "WP Remote POST"? Print them out
    ?>
    <div class="ajax-wp-remote-post-call-error-area">
        <p><span class="dashicons dashicons-warning"></span> <?php _e('It looks like "WP Remote POST" method for retrieving assets via the Dashboard is not working in this environment.', 'wp-asset-clean-up'); ?></p>
        <p><?php esc_html_e('Since the server (from its IP) is making the call, it will not "behave" in the same way as the "Direct" method, which could bypass for instance any authentication request (you might use a staging website that is protected by login credentials).', 'wp-asset-clean-up'); ?></p>
        <p><?php esc_html_e('Consider using "Direct" method. If that doesn\'t work either, use the "Manage in the Front-end" option (which should always work in any instance) and submit a ticket regarding the problem you\'re having. Here\'s the output received by the call:', 'wp-asset-clean-up'); ?></p>
        <table class="table-data">
            <tr>
                <td><strong><?php _e('CODE', 'wp-asset-clean-up'); ?>:</strong></td>
                <td><?php echo htmlspecialchars($data['wp_remote_post']['response']['code']); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('MESSAGE', 'wp-asset-clean-up'); ?>:</strong></td>
                <td><?php echo htmlspecialchars($data['wp_remote_post']['response']['message']); ?></td>
            </tr>
            <tr>
                <td valign="top"><strong><?php _e('OUTPUT', 'wp-asset-clean-up'); ?>:</strong></td>
                <td><?php echo htmlspecialchars($data['wp_remote_post']['body']); ?></td>
            </tr>
        </table>
    </div>
    <?php
    exit();
}

include WPACU_PLUGIN_DIR . '/templates/meta-box-loaded-assets/_optimizations-disabled-notice.php';

$tipsClass = new Tips();
$data['tips'] = $tipsClass->list;

if ( ! empty($data['wp_remote_post']) ) {
    $statusCode = wp_remote_retrieve_response_code($data['wp_remote_post']);

    if ($statusCode === 404) {
        ?>
            <input type="hidden" id="wpacu_is_404_wp_remote_post_response" value="1" />
        <?php
    }
}

// Keep the page context and its type together when the URL is already shown above.
$calledFromOwnPluginAssetsManagerPage = isset($_POST['called_from_plugin_own_asset_manager']) && $_POST['called_from_plugin_own_asset_manager'];
$wpacuSummaryUrl                     = !empty($data['fetch_url']) ? $data['fetch_url'] : '';
if (!is_admin() && is_search() && !$calledFromOwnPluginAssetsManagerPage) {
    // Display the actual search request without changing the asset-fetch/save URL.
    $wpacuSummaryUrl = \WpAssetCleanUp\Misc::removeManagerQueryArgs(\WpAssetCleanUp\Misc::getCurrentPageUrl(false));
}
$wpacuShowSummaryUrl = !$calledFromOwnPluginAssetsManagerPage && $wpacuSummaryUrl !== '' && !is_404();
// The dedicated singular-page header already includes the type before fetching assets.
$wpacuTypeShownInHeader = $calledFromOwnPluginAssetsManagerPage && !empty($data['post_id'])
    && $data['bulk_unloaded_type'] === 'post_type';
$wpacuShowSummaryType       = $data['bulk_unloaded_type'] === 'post_type' && !$wpacuTypeShownInHeader;
$wpacuInlinePageSummary     = !$wpacuShowSummaryUrl && $wpacuShowSummaryType;
$wpacuUseFrontendSummary    = !is_admin() && ($wpacuShowSummaryUrl || $wpacuShowSummaryType);
$wpacuContextParagraphStyle = $wpacuInlinePageSummary ? ' style="margin: 0;"' : '';
if ($wpacuInlinePageSummary) {
    echo '<div class="wpacu-page-context" style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px 20px; margin: 0 0 14px;">';
}

if (is_admin()) {
    // Dashboard edit view
    // Archives have no post ID; zero must never match an unassigned Reading page.
    if (get_option('show_on_front') === 'page' && ! empty($data['post_id']) && (int)$data['post_id'] > 0) {
        if ((int)get_option('page_on_front') === (int)$data['post_id']) {
        ?>
            <p<?php echo $wpacuContextParagraphStyle; ?>><span style="color: #0f6cab;" class="dashicons dashicons-admin-home"></span> <?php echo sprintf(__('This page was set as the home page in %s"Settings" &#10141; "Reading"%s.', 'wp-asset-clean-up'), '<a target="_blank" href="'.esc_url(admin_url('options-reading.php')).'">', '</a>'); ?></p>
	    <?php
        } elseif ((int)get_option('page_for_posts') === (int)$data['post_id']) {
        ?>
            <p<?php echo $wpacuContextParagraphStyle; ?>><span style="color: #0f6cab;" class="dashicons dashicons-admin-post"></span> <?php echo sprintf(__('This page was set to show the latest posts in %s"Settings" &#10141; "Reading"%s.', 'wp-asset-clean-up'), '<a target="_blank" href="'.esc_url(admin_url('options-reading.php')).'">', '</a>'); ?></p>
        <?php
        }
    }
} elseif (!$wpacuUseFrontendSummary) {
    // Front-end view
    if (MainFront::isBlogPage()) {
        ?>
        <p<?php echo $wpacuContextParagraphStyle; ?>><span style="color: #0f6cab;" class="dashicons dashicons-admin-post"></span> <?php _e('You are currently viewing the page that shows your latest posts.', 'wp-asset-clean-up'); ?></p>
        <?php
    } elseif (MainFront::isHomePage()) {
        ?>
        <p<?php echo $wpacuContextParagraphStyle; ?>><span style="color: #0f6cab;" class="dashicons dashicons-admin-home"></span> <?php _e('You are currently viewing the home page.', 'wp-asset-clean-up'); ?></p>
        <?php
    }
}

if ($wpacuShowSummaryUrl || $wpacuShowSummaryType) {
    $wpacuPageSummary = array(
        'inline' => $wpacuInlinePageSummary,
        'frontend' => $wpacuUseFrontendSummary,
        'is_homepage' => $wpacuUseFrontendSummary && MainFront::isHomePage(),
        'is_blog_page' => $wpacuUseFrontendSummary && MainFront::isBlogPage(),
        'post_id' => $wpacuUseFrontendSummary && !empty($data['post_id']) ? (int)$data['post_id'] : 0,
        'url' => $wpacuShowSummaryUrl ? $wpacuSummaryUrl : '',
        'title' => $wpacuShowSummaryUrl && !empty($data['post_id']) ? get_the_title($data['post_id']) : '',
        'post_type' => $wpacuShowSummaryType ? $data['post_type'] : '',
        'redirected_url' => $wpacuShowSummaryUrl && !empty($data['redirected_to_url']) ? $data['redirected_to_url'] : '',
        'woocommerce' => (function_exists('is_woocommerce') && is_woocommerce())
            || (function_exists('is_cart') && is_cart())
            || (function_exists('is_product_tag') && is_product_tag())
            || (function_exists('is_product_category') && is_product_category())
            || (function_exists('is_checkout') && is_checkout())
    );
    include WPACU_PLUGIN_DIR.'/templates/_common/asset-manager/page-summary.php';
}
if ($wpacuInlinePageSummary) {
    echo '</div>';
}

include WPACU_PLUGIN_DIR.'/templates/_common/asset-manager/page-template.php';

// [wpacu_pro]
$wpacuQueriedObjForCustomPostType = defined('WPACU_PRO_PLUGIN_VERSION') ? MainPro::isCustomPostTypeArchivePage() : false;

if ($wpacuQueriedObjForCustomPostType) {
    ?>
    <p>
        <strong>Archive Page:</strong> "<?php echo esc_html($wpacuQueriedObjForCustomPostType->label); ?>" post type (Slug: <?php echo esc_html($wpacuQueriedObjForCustomPostType->query_var); ?>) * <small>the page rules applied here will take effect on this archive page only and the pagination pages (e.g. /<?php echo esc_html($wpacuQueriedObjForCustomPostType->query_var); ?>/page/2/), and not within the actual post type pages which have separate rules (e.g. /<?php echo esc_html($wpacuQueriedObjForCustomPostType->query_var); ?>/the-post-title-here/)</small>
    </p>
    <?php
    $wpacuChildThemeDir   = get_stylesheet_directory();
    $pathToArchiveFileRel = '';

    // Read more: https://developer.wordpress.org/themes/basics/template-hierarchy/#custom-post-types
    $pathToArchiveFileLevelOne   = $wpacuChildThemeDir . '/archive-' . $wpacuQueriedObjForCustomPostType->name . '.php';
    $pathToArchiveFileLevelTwo   = $wpacuChildThemeDir . '/archive.php';
    $pathToArchiveFileLevelThree = $wpacuChildThemeDir . '/index.php';

    if (is_file($pathToArchiveFileLevelOne)) {
        $pathToArchiveFileRel = '/' . str_replace( Misc::getWpRootDirPathBasedOnPath($pathToArchiveFileLevelOne), '', $pathToArchiveFileLevelOne );
    } elseif (is_file($pathToArchiveFileLevelTwo)) {
        $pathToArchiveFileRel = '/' . str_replace( Misc::getWpRootDirPathBasedOnPath($pathToArchiveFileLevelTwo), '', $pathToArchiveFileLevelTwo );
    } elseif (is_file($pathToArchiveFileLevelThree)) {
        $pathToArchiveFileRel = '/' . str_replace( Misc::getWpRootDirPathBasedOnPath($pathToArchiveFileLevelThree), '', $pathToArchiveFileLevelThree );
    }

    if ($pathToArchiveFileRel !== '') {
    ?>
        <p><strong>Template Page:</strong> <em><?php echo esc_html($pathToArchiveFileRel); ?></em> * <small><a rel="noopener noreferrer" href="https://developer.wordpress.org/themes/basics/template-hierarchy/#custom-post-types" target="_blank">Read more on the WordPress Codex</a></small></p>
    <?php
    }
    ?>
        <div style="margin-bottom: 26px;"></div>
    <?php
}
// [/wpacu_pro]

$viewAssetsMode = 'default'; // All Styles & All Scripts (two lists)

if ($data['plugin_settings']['assets_list_layout'] === 'all') {
	$viewAssetsMode = 'all'; // All Styles & Scripts (one list)
}

if ($data['plugin_settings']['assets_list_layout'] === 'by-location') {
	$viewAssetsMode = 'by-location'; // Plugins, Theme(s), Core Files, External etc.
}

if ($data['plugin_settings']['assets_list_layout'] === 'by-position') {
	$viewAssetsMode = 'by-position'; // <head> or <body> (two lists)
}

if ($data['plugin_settings']['assets_list_layout'] === 'by-preload') {
	$viewAssetsMode = 'by-preload'; // <head> or <body> (two lists)
}

if ($data['plugin_settings']['assets_list_layout'] === 'by-parents') {
    $viewAssetsMode = 'by-parents'; // Loaded & Unloaded (two lists)
}

if ($data['plugin_settings']['assets_list_layout'] === 'by-loaded-unloaded') {
	$viewAssetsMode = 'by-loaded-unloaded'; // Parent or Child/Independent (two lists)
}

if ($data['plugin_settings']['assets_list_layout'] === 'by-size') {
	$viewAssetsMode = 'by-size'; // Local Files (in descending order based on the size) and External Files (or files that can't have the size determined)
}

if ($data['plugin_settings']['assets_list_layout'] === 'by-rules') {
	$viewAssetsMode = 'by-rules'; // Enqueued Files with at least one rule & Enqueued Files with no rules
}

// Only specific for the front-end view
if ( ! is_admin() && \WpAssetCleanUp\MainFront::isSingularPage() ) {
    global $post;
    ?>
    <input type="hidden" name="wpacu_is_singular_page" value="<?php echo $post->ID; ?>" />
    <?php
}

// e.g. Unload on this page, Unload on this product page (depending on the page where the assets are managed)
$data       = AssetsManager::textRulesToShowInCssJsManager($data);
$inputStyle = Settings::getInputStyle($data['plugin_settings']);
?>
<div<?php if ($isLiteProPreview) { ?> data-wpacu-lite-lock-controls="1"<?php } ?>
     class="<?php if ($isLiteProPreview) { ?>wpacu-lite-pro-preview-dynamic <?php } ?><?php echo esc_attr(Settings::getInputStyleCssClasses($inputStyle)); ?>"
     data-wpacu-input-style="<?php echo esc_attr($inputStyle); ?>">
    <?php
    require WPACU_PLUGIN_DIR . '/templates/meta-box-loaded-assets/view-'.$viewAssetsMode.'.php';
    ?>
</div>
<?php
/*
 Bug Fix: Make sure that savePost() from Update class is triggered ONLY if the meta box is loaded
 Otherwise, an early form submit will erase any selected assets for unload by sending an empty $_POST[WPACU_PLUGIN_ID] request

 NOTE: In case no assets are retrieved, then it's likely that for some reason, fetching the assets from the Dashboard
 is not possible and the user will have to manage them in the front-end.
 We'll make sure that no existing assets (managed in the front-end) are removed when the user updates the post/page from the Dashboard
*/

// Check it again
if ($metaBoxLoadedFine && ! $isLiteProPreview) {
	$metaBoxLoadedFine = ( ! ( empty( $data['all']['styles'] ) && empty( $data['all']['scripts'] ) ) );
}

if ($metaBoxLoadedFine) {
    ?>
    <input type="hidden"
           id="wpacu_unload_assets_area_loaded"
           name="wpacu_unload_assets_area_loaded"
           value="1" />
    <?php
}

if (isset($data['external_srcs_ref']) && $data['external_srcs_ref']) {
    ?>
    <span data-wpacu-external-srcs-ref="<?php echo esc_attr($data['external_srcs_ref']); ?>" style="display: none;"></span>
    <?php
}
