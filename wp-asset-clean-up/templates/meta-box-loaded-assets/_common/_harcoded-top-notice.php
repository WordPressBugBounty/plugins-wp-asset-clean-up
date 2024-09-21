<?php
if ( ! isset($data) ) {
    exit();
}
?>
<div style="margin: 15px 0 0; padding: 0 10px;">
    <p><span style="color: #0073aa;" class="dashicons dashicons-info"></span> The following tags are NOT LOADED via the recommended
        <a target="_blank" href="https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/">wp_enqueue_scripts()</a>
        action hook (despite the name, it is used for enqueuing both scripts and styles) which is the proper one to use when enqueuing scripts and styles that are meant to appear on
        the front end. The standard functions that are used inside the hook to do an enqueuing are:
        <a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_enqueue_style/">wp_enqueue_style()</a>,
        <a target="_blank" href="https://codex.wordpress.org/Function_Reference/wp_add_inline_style">wp_add_inline_style()</a>,
        <a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_enqueue_script/">wp_enqueue_script()</a>
        &amp; <a target="_blank" href="https://developer.wordpress.org/reference/functions/wp_add_inline_script/">wp_add_inline_script()</a>. The tags could have been added via editing the PHP code (not using the right standard functions), directly inside posts content, widgets or via plugins such as "Insert Headers and Footers", "Head, Footer and Post Injections", etc. Be careful when unloading any of these tags as they might be related to Google Analytics/Google Ads, StatCounter, Facebook Pixel, etc.
    </p>
</div>
<!-- [wpacu_lite] -->
<div style="margin: 20px 0; border-left: solid 4px green; background: #f2faf2; padding: 10px;"><img width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" valign="top" alt="" /> &nbsp;Managing hardcoded LINK/STYLE/SCRIPT tags is an option <a target="_blank" href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=manage_hardcoded_assets&utm_medium=top_notice'); ?>">available in the Pro version</a>.</div>
<!-- [/wpacu_lite] -->
