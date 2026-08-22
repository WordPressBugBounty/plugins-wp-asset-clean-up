<?php
if (! defined('ABSPATH')) {
    exit;
}

$wpacuArchiveIsPro = defined('WPACU_PRO_PLUGIN_VERSION');

if ($wpacuArchiveHeaderSection === 'notice' && $wpacuArchiveIsPro) {
    if (! empty($archiveData['notice'])) { ?>
        <p style="margin: 15px 0 15px;"><?php echo esc_html($archiveData['notice']); ?></p>
    <?php }
} elseif ($wpacuArchiveHeaderSection === 'notice') {
    $wpacuArchiveScopeMessages = array(
        'custom_post_type_archive' => __('The sample URL is used to retrieve the real assets. In Pro, rules configured here apply to this custom post type archive and its pagination pages, not to individual entries.', 'wp-asset-clean-up'),
        'author'                   => __('The sample URL is used to retrieve the real assets. In Pro, rules configured here apply to author archive pages.', 'wp-asset-clean-up'),
        'date'                     => __('The latest valid date archive is used as the sample URL. In Pro, rules configured here apply to all date archive pages.', 'wp-asset-clean-up'),
        '404'                      => __('The sample URL triggers the active theme’s 404 template. In Pro, rules configured here apply to all 404 Not Found pages.', 'wp-asset-clean-up'),
        'taxonomy'                 => __('The selected taxonomy term URL is used to retrieve the real assets. In Pro, rules configured here apply to this term archive and its pagination pages.', 'wp-asset-clean-up'),
        'search'                   => __('The selected keyword is used only to build a real sample URL. In Pro, rules configured here apply to all WordPress search results pages.', 'wp-asset-clean-up'),
    );

    if (isset($wpacuArchiveScopeMessages[$archiveData['type']])) { ?>
        <p style="margin: 15px 0 15px;"><?php echo esc_html($wpacuArchiveScopeMessages[$archiveData['type']]); ?></p>
    <?php }

    \WpAssetCleanUpLite\Admin\ProPreview::renderNotice(
        __('Preview the real assets loaded in this context', 'wp-asset-clean-up'),
        __('Lite can inspect the CSS and JavaScript files below. Creating or changing unload and load-exception rules for archive-like pages requires Asset CleanUp Pro.', 'wp-asset-clean-up'),
        'css_js_manager_' . $archiveData['type']
    );
} elseif ($wpacuArchiveHeaderSection === 'context') { ?>
    <div class="wpacu_verified">
        <strong><?php esc_html_e('Page URL', 'wp-asset-clean-up'); ?>:</strong>
        <a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($displayUrl); ?>"><span><?php echo esc_url($displayUrl); ?></span></a>
        | <strong><?php esc_html_e('Context', 'wp-asset-clean-up'); ?>:</strong> <?php echo esc_html($archiveData['label']); ?>
    </div>
    <div class="wpacu-redirected-fetch-url-reminder-slot"></div>
<?php }

unset($wpacuArchiveScopeMessages, $wpacuArchiveIsPro, $wpacuArchiveHeaderSection);
