<?php
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

if (! isset($data)) {
	exit;
}
?>
<p style="margin: 0 0 25px; line-height: 24px;">Whenever a CSS/JS file has to be altered in any way, in order to apply a change to it (e.g. minification, removing Google Fonts from the CSS content), the plugin has to cache that file. Next time, when a page is visited, the plugin will load the already optimized file from the caching. This way, resources are saved, especially when dealing with large files. <span style="color: #004567;" class="dashicons dashicons-info"></span> <a target="_blank" href="https://www.assetcleanup.com/docs/?p=526">Read more</a>.</p>
<table class="wpacu-form-table">
    <tr valign="top">
        <th scope="row">
            <label for="wpacu_fetch_cached_files_details_from"><?php _e('Fetch assets\' caching information from:', 'wp-asset-clean-up'); ?></label>
        </th>
        <td>
            <select id="wpacu_fetch_cached_files_details_from"
                    name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[fetch_cached_files_details_from]">
                <option <?php if ($data['fetch_cached_files_details_from'] === 'disk') { ?>selected="selected"<?php } ?> value="disk">Disk (default)</option>
                <option <?php if ($data['fetch_cached_files_details_from'] === 'db') { ?>selected="selected"<?php } ?> value="db">Database</option>
                <option <?php if ($data['fetch_cached_files_details_from'] === 'db_disk') { ?>selected="selected"<?php } ?> value="db_disk">Database &amp; Disk (50% / 50%)</option>
            </select> &nbsp; <span style="color: #004567; vertical-align: middle;" class="dashicons dashicons-info"></span> <a style="vertical-align: middle;" data-wpacu-modal-target="wpacu-fetch-assets-details-location-modal-target" href="#wpacu-fetch-assets-details-location-modal">Read more</a>
        </td>
    </tr>

    <tr valign="top">
        <th scope="row">
            <label for="wpacu_clear_cached_files_after"><?php _e('Clear previously cached CSS/JS files older than (x) days', 'wp-asset-clean-up'); ?></label>
        </th>
        <td>
            <input id="wpacu_clear_cached_files_after"
                   type="number"
                   min="1"
                   style="width: 60px; margin-bottom: 10px;"
                   name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[clear_cached_files_after]"
                   value="<?php echo esc_attr($data['clear_cached_files_after']); ?>" /> day(s)
            <p style="margin: 15px 0 0; line-height: 24px;">This is relevant in case there are alterations made to the content of the CSS/JS files via minification, combination or any other settings that would require an update to the content of a file (e.g. apply "font-display" to @font-face in stylesheets). When the caching is cleared, the previously cached CSS/JS files stored in <code><?php echo OptimizeCommon::getRelPathPluginCacheDir(); ?></code> that are older than (X) days will be deleted as they are outdated and likely not referenced anymore in any source code (e.g. old cached pages, Google Search cached version etc.). <span style="color: #004567;" class="dashicons dashicons-info"></span> <a href="https://assetcleanup.com/docs/?p=237" target="_blank">Read more</a></p>
        </td>
    </tr>
</table>