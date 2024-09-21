<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
	exit;
}

$tabIdArea = 'wpacu-setting-disable-rss-feed';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea) ? 'style="display: table-cell;"' : '';

$disableRssFeedAreaStyle = ($data['disable_rss_feed'] == 1) ? 'opacity: 1;' : 'opacity: 0.4;';
?>
<div id="<?php echo esc_attr($tabIdArea); ?>" class="wpacu-settings-tab-content" <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
	<h2 class="wpacu-settings-area-title"><?php _e('Disable RSS Feed &amp; its references from &lt;HEAD&gt;', 'wp-asset-clean-up'); ?></h2>
    <p style="margin-top: 10px;">If you do not use WordPress for blogging purposes at all, and it doesn't have any blog posts (apart from the main pages that you added), then you can disable the RSS feeds, remove the main feed &amp; comments link frm the <code>&lt;HEAD&gt;</code> section of the HTML source code.</p>
    <table class="wpacu-form-table">
        <!-- Disable RSS Feed -->
        <tr valign="top">
            <th scope="row">
                <label for="wpacu_disable_rss_feed">Disable RSS Feed?</label>
            </th>
            <td>
                <label class="wpacu_switch wpacu_del_type">
                    <input id="wpacu_disable_rss_feed"
                           data-target-opacity="wpacu_disable_rss_feed_message_area"
                           type="checkbox"
					    <?php echo (($data['disable_rss_feed'] == 1) ? 'checked="checked"' : ''); ?>
                           name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[disable_rss_feed]"
                           value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>&nbsp;
                This will show an error message that you can customize below whenever someone is accessing the website's RSS links (e.g. <code><?php echo get_feed_link(); ?></code>). If this option is turned ON (disabling any RSS feed), the extra options below will also be turned ON as RSS links will not be relevant anymore.
            <div style="margin: 6px 0 0; <?php echo $disableRssFeedAreaStyle; ?>"
                 id="wpacu_disable_rss_feed_message_area">
                <textarea id="wpacu_disable_rss_feed_message"
                         name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[disable_rss_feed_message]"
                         rows="5"
                         style="width: 100%;"><?php echo esc_textarea($data['disable_rss_feed_message']); ?></textarea></div>
            </td>
        </tr>

        <!-- Remove Main RSS Feed Link -->
        <tr valign="top">
            <th scope="row">
                <label for="wpacu_remove_main_feed_link">Remove Main RSS Feed Link?</label>
            </th>
            <td>
                <label class="wpacu_switch wpacu_del_type">
                    <input id="wpacu_remove_main_feed_link"
                           type="checkbox"
					    <?php echo (($data['remove_main_feed_link'] == 1) ? 'checked="checked"' : ''); ?>
                           name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[remove_main_feed_link]"
                           value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
                &nbsp;
                <code>e.g. &lt;link rel=&quot;alternate&quot; type=&quot;application/rss xml&quot; title=&quot;Your Site Title &amp;raquo; Feed&quot; href=&quot;https://www.yourwebsite.com/feed/&quot; /&gt;</code>
            </td>
        </tr>

        <!-- Remove Comment Feeds Link -->
        <tr valign="top">
            <th scope="row">
                <label for="wpacu_remove_comment_feed_link">Remove Comment RSS Feed Link?</label>
            </th>
            <td>
                <label class="wpacu_switch wpacu_del_type">
                    <input id="wpacu_remove_comment_feed_link"
                           type="checkbox"
					    <?php echo (($data['remove_comment_feed_link'] == 1) ? 'checked="checked"' : ''); ?>
                           name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[remove_comment_feed_link]"
                           value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
                &nbsp;
                <code>e.g. &lt;link rel=&quot;alternate&quot; type=&quot;application/rss xml&quot; title=&quot;Your Website Title &amp;raquo; Comments Feed&quot; href=&quot;https://www.yourdomain.com/comments/feed/&quot; /&gt;</code>
            </td>
        </tr>
	</table>
</div>
