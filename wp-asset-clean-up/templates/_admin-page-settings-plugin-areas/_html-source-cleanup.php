<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
	exit;
}

$tabIdArea = 'wpacu-setting-html-source-cleanup';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea) ? 'style="display: table-cell;"' : '';
?>
<div id="<?php echo esc_attr($tabIdArea); ?>" class="wpacu-settings-tab-content" <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <fieldset class="wpacu-options-grouped-in-settings" style="margin-bottom: 30px;">
        <legend class="wpacu-larger"><?php _e('Remove unused elements from the &lthead&gt; section', 'wp-asset-clean-up'); ?></legend>
        <p>There are elements that are enabled by default in many WordPress environments, but not necessary to be enabled. Cleanup the unnecessary code between <code>&lt;head&gt;</code> and <code>&lt;/head&gt;</code>.</p>
        <table class="wpacu-form-table">
            <!-- Remove "Really Simple Discovery (RSD)" link? -->
            <tr valign="top">
                <th scope="row">
                    <label for="wpacu_remove_rsd_link">Remove "Really Simple Discovery (RSD)" link tag?</label>
                </th>
                <td>
                    <?php
                    $opacityStyle = '';

                    if ($data['disable_xmlrpc'] === 'disable_all') {
                        $opacityStyle = 'opacity: 0.4;';
                    }
                    ?>
                    <label class="wpacu_switch wpacu_del_type" style="<?php echo $opacityStyle; ?>">
                        <input id="wpacu_remove_rsd_link" type="checkbox"
                            <?php echo (($data['remove_rsd_link'] == 1) ? 'checked="checked"' : ''); ?>
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[remove_rsd_link]"
                               value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
                    &nbsp;
                    <code style="<?php echo $opacityStyle; ?>">&lt;link rel=&quot;EditURI&quot; type=&quot;application/rsd xml&quot; title=&quot;RSD&quot; href=&quot;http://yourwebsite.com/xmlrpc.php?rsd&quot; /&gt;</code>
                    <p style="margin-top: 10px; <?php echo $opacityStyle; ?>">XML-RPC clients use this discovery method. If you do not know what this is and don't use service integrations such as <a href="http://www.flickr.com/services/api/request.xmlrpc.html">Flickr</a> on your WordPress website, you can remove it.</p>
                    <?php if ($data['disable_xmlrpc'] === 'disable_all') { ?>
                        <p style="margin-top: 10px; color: #cc0000;"><strong>Note:</strong> As you already chosen to completely disable "<a data-wpacu-vertical-link-target="wpacu-setting-disable-xml-rpc" href="#wpacu-setting-disable-xml-rpc">Disable XML-RPC</a>", the "Really Simple Discovery (RSD)" link tag is already removed.</p>
                    <?php } ?>
                </td>
            </tr>

            <!-- Remove "REST API" link? -->
            <tr valign="top">
                <th scope="row">
                    <label for="wpacu_remove_rest_api_link">Remove "REST API" link tag?</label>
                </th>
                <td>
                    <label class="wpacu_switch wpacu_del_type">
                        <input id="wpacu_remove_rest_api_link" type="checkbox"
                            <?php echo (($data['remove_rest_api_link'] == 1) ? 'checked="checked"' : ''); ?>
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[remove_rest_api_link]"
                               value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
                    &nbsp;
                    <code>&lt;link rel=&#39;https://api.w.org/&#39; href=&#39;https://yourwebsite.com/wp-json/&#39; /&gt;</code>
                    <p style="margin-top: 10px;">Are you accessing your content through endpoints (e.g. https://yourwebsite.com/wp-json/, https://yourwebsite.com/wp-json/wp/v2/posts/1 - <em>1</em> in this example is the POST ID)? If not, you can remove this.</p>
                </td>
            </tr>

            <!-- Remove "Shortlink"? -->
            <tr valign="top">
                <th scope="row">
                    <label for="wpacu_remove_shortlink">Remove Pages/Posts "Shortlink" tag?</label>
                </th>
                <td>
                    <label class="wpacu_switch wpacu_del_type">
                        <input id="wpacu_remove_shortlink" type="checkbox"
                            <?php echo (($data['remove_shortlink'] == 1) ? 'checked="checked"' : ''); ?>
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[remove_shortlink]"
                               value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
                    &nbsp;
                    <code>&lt;link rel=&#39;shortlink&#39; href=&quot;https://yourdomain.com/?p=1&quot;&gt;</code>
                    <p style="margin-top: 10px;">Are you using SEO-friendly URLs and do not need the default WordPress shortlink? You can just remove this as it bulks out the head section of your website.</p>
                </td>
            </tr>

            <!-- Remove "WordPress version" meta tag? -->
            <tr valign="top">
                <th scope="row">
                    <label for="wpacu_remove_wp_version">Remove "WordPress version" meta tag?</label>
                </th>
                <td>
                    <label class="wpacu_switch wpacu_del_type">
                        <input id="wpacu_remove_wp_version" type="checkbox"
                            <?php echo (($data['remove_wp_version'] == 1) ? 'checked="checked"' : ''); ?>
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[remove_wp_version]"
                               value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
                    &nbsp;
                    <code>&lt;meta name=&quot;generator&quot; content=&quot;WordPress 4.9.8&quot; /&gt;</code>
                    <p style="margin-top: 10px;">This is good for security purposes as well, since it hides the WordPress version you're using (in case of hacking attempts).</p>
                </td>
            </tr>

            <!-- Remove "WordPress version" meta tag and all other tags? -->
            <tr valign="top">
                <th scope="row">
                    <label for="wpacu_remove_generator_tag">Remove All "generator" meta tags?</label>
                </th>
                <td>
                    <label class="wpacu_switch wpacu_del_type">
                        <input id="wpacu_remove_generator_tag"
                               type="checkbox"
                            <?php echo (($data['remove_generator_tag'] == 1) ? 'checked="checked"' : ''); ?>
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[remove_generator_tag]"
                               value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
                    &nbsp;
                    <code>e.g. &lt;meta name=&quot;generator&quot; content=&quot;Easy Digital Downloads v2.9.8&quot; /&gt;</code>
                    <p style="margin-top: 10px;">This will remove all meta tags with the "generator" name, including the "WordPress version" meta tag. You could use a plugin or a theme that has added a generator notice, but you do not need to have it there. Moreover, it will hide the version of the plugins and theme you're using which is good for security reasons.</p>
                </td>
            </tr>

            <?php
            global $wp_version;

            $noRelevanceForCurrentWpVersion = false;

            if (version_compare($wp_version, '5.6.0') >= 0 && version_compare($wp_version, '6.3.0') >= 0) {
                $noRelevanceForCurrentWpVersion = true;
            }
            ?>
            <tr valign="top">
                <td colspan="2">
                    <div class="wpacu-notice wpacu-warning" style="font-size: inherit; margin-top: 0 !important; line-height: 1.4rem;">
                        <span class="dashicons dashicons-flag" style="font-size: 22px; color: #ff9800 !important;"></span>
                        The following options are deprecated, and as time goes by, they will lose relevance. <?php if ($noRelevanceForCurrentWpVersion) { ?>Your current WordPress version is <strong><?php echo $wp_version; ?></strong>, and none of the options below are in its core anymore.<?php } ?>
                    </div>
                </td>
            </tr>

            <!-- Remove "Post's Relational Links" tag? -->
            <!-- Since WP 5.6.0, no longer used in core -->
            <?php
            $opacityStyle = 1;

            if (version_compare($wp_version, '5.6.0') >= 0) {
                $opacityStyle = '0.66';
            }
            ?>
            <tr valign="top" style="opacity: <?php echo $opacityStyle; ?>;">
                <th scope="row" style="padding-left: 8px;">
                    <label for="wpacu_remove_posts_rel_links">Remove "Post's Relational Links" tag?</label>
                    <br /><div style="margin: 4px 0 0; font-style: italic;"><small style="font-weight: 400;">No longer used in core since WordPress 5.6.0</small></div>
                </th>
                <td>
                    <label class="wpacu_switch wpacu_del_type">
                        <input id="wpacu_remove_posts_rel_links" type="checkbox"
                            <?php echo (($data['remove_posts_rel_links'] == 1) ? 'checked="checked"' : ''); ?>
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[remove_posts_rel_links]"
                               value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
                    &nbsp;
                    <code>&lt;link rel=&#39;prev&#39; title=&#39;Post title&#39; href=&#39;https://yourdomain.com/prev-post-slug-here/&#39; /&gt;</code> <strong>&amp;</strong> <code>&lt;link rel=&#39;next&#39; title=&#39;Post title&#39; href=&#39;https://yourdomain.com/next-post-slug-here/&#39; /&gt;</code>
                    <p style="margin-top: 10px;">This removes relational links for the posts adjacent to the current post for single post pages.</p>
                </td>
            </tr>

            <!-- Remove "Windows Live Writer" link? -->
            <!-- Since WP 6.3.0, no longer used in core -->
            <?php
            $opacityStyle = 1;

            if (version_compare($wp_version, '6.3.0') >= 0) {
                $opacityStyle = '0.66';
            }
            ?>
            <tr valign="top" style="opacity: <?php echo $opacityStyle; ?>;">
                <th scope="row" style="padding-left: 8px;">
                    <label for="wpacu_remove_wlw_link">Remove "Windows Live Writer" link tag?</label>
                    <br /><div style="margin: 4px 0 0; font-style: italic;"><small style="font-weight: 400;">No longer used in core since WordPress 6.3.0</small></div>
                </th>
                <td>
                    <label class="wpacu_switch wpacu_del_type">
                        <input id="wpacu_remove_wlw_link" type="checkbox"
                            <?php echo (($data['remove_wlw_link'] == 1) ? 'checked="checked"' : ''); ?>
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[remove_wlw_link]"
                               value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
                    &nbsp;
                    <code>&lt;link rel=&quot;wlwmanifest&quot; type=&quot;application/wlwmanifest xml&quot; href=&quot;https://yourwebsite.com/wp-includes/wlwmanifest.xml&quot; /&gt;</code>
                    <p style="margin-top: 10px;">If you do not use Windows Live Writer to edit your blog contents, then it's safe to remove this.</p>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset class="wpacu-options-grouped-in-settings">
        <legend class="wpacu-larger"><?php _e('Remove extra elements from the generated page source', 'wp-asset-clean-up'); ?></legend>
        <table class="wpacu-form-table">
            <tr valign="top">
                <th scope="row">
                    <label for="wpacu_remove_html_comments">Strip HTML comments?</label>
                </th>
                <td>
                    <label class="wpacu_switch wpacu_del_type">
                        <input id="wpacu_remove_html_comments"
                               data-target-opacity="wpacu_remove_html_comments_area"
                               type="checkbox"
                            <?php echo (($data['remove_html_comments'] == 1) ? 'checked="checked"' : ''); ?>
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[remove_html_comments]"
                               value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>

                    &nbsp; This feature will strip all comments except the Internet Explorer conditional ones. If you wish to keep specific comments, use the textarea below to add exception patterns (one per line).

                    <?php
                    $removeHtmlCommentsAreaStyle = ($data['remove_html_comments'] == 1) ? 'opacity: 1;' : 'opacity: 0.4;';
                    ?>
                    <div id="wpacu_remove_html_comments_area" style="<?php echo esc_attr($removeHtmlCommentsAreaStyle); ?>">
                        <div style="margin: 14px 0 8px;"><label for="wpacu_remove_html_comments_exceptions">Do not remove comments containing the following (case-insensitive) text:</label></div>
                        <textarea id="wpacu_remove_html_comments_exceptions"
                                  name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[remove_html_comments_exceptions]"
                                  rows="4"
                                  style="width: 100%;"><?php echo esc_textarea($data['remove_html_comments_exceptions']); ?></textarea>
                        <div class="wpacu-notice wpacu-warning" style="font-size: inherit; line-height: 1.4rem;">
                            <span class="dashicons dashicons-warning" style="font-size: 22px; color: #ff9800 !important;"></span>
                            There are comments which might not be stripped from the final HTML source and this is due to the source being updated outside the WordPress environment or by caching plugins that add their own signatures before rendering the cached pages. <a target="_blank" href="https://assetcleanup.com/docs/?p=116">Read more</a> about how you can strip those comments too!
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </fieldset>
</div>
