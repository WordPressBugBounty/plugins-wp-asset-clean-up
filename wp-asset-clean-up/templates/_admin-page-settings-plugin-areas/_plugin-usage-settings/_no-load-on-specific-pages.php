<?php
use WpAssetCleanUp\Admin\SettingsAdmin;

if (! isset($data)) {
    exit;
}

$optionsAlreadySet = ! empty($data['do_not_load_plugin_features']);
?>
<fieldset class="wpacu-options-grouped-in-settings" style="margin-bottom: 30px;">
    <legend>Prevent <?php echo WPACU_PLUGIN_TITLE; ?> from triggering on certain pages</legend>
    <p style="margin-top: 5px;">This option is useful if you have issues with the plugin on specific pages (e.g. incompatibility with another plugin). You can specify some URI patterns in the following textarea (one per line), just like the examples shown below:</p>
    <div style="margin: 8px 0 5px;">
        <textarea id="wpacu_do_not_load_plugin_patterns"
                  name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[do_not_load_plugin_patterns]"
                  rows="4"
                  style="width: 100%;"><?php echo esc_textarea($data['do_not_load_plugin_patterns']); ?></textarea>
    </div>
    <div>
        <p>You can either use specific strings or patterns (the # delimiter will be automatically applied to the <code>preg_match()</code> PHP function that would check if the requested URI is matched). Please do not include the domain name. Here are a few examples:</p>
        <ul style="margin-bottom: 0;">
            <li><code>/checkout/</code> - if it contains the string</li>
            <li><code>/product/(.*?)/</code> - any product page (most likely from WooCommerce)</li>
        </ul>
    </div>
</fieldset>

<fieldset class="wpacu-options-grouped-in-settings">
    <legend>Prevent features of <?php echo WPACU_PLUGIN_TITLE; ?> from triggering on certain pages</legend>
    <p style="margin-top: 5px;">Sometimes, specific features that you have enabled might not work well on certain pages (or group of pages). For instance, on most pages, the feature to combine CSS would work fine, but you noticed that on a page and its subpages (e.g. /course/, /course/title-here/), this feature doesn't work so well. You have the option to prevent it from triggering (in this example, it would keep the files loading individually, not combined).</p>

    <div class="wpacu-warning">
        <p style="margin: 0;">If the page URI contains OR matches the RegEx (e.g. you can just use "<strong>/contact</strong>" if you know the page is like <em>https://www.yourwebsite.com/contact</em> OR a RegEx such as "<strong>#/contact|/about#</strong>", if you are comfortable using Regular Expressions that will match the URIs that contain either "/contact" or "/about"), then decide which features should <strong>NOT BE ENABLED</strong>.</p>
        <ul style="font-size: 13px; line-height: 1.5; margin-bottom: 0;">
            <li>Tip #1: If you want to match the actual homepage, please use the <strong>{homepage}</strong> string (it's treated as a special string).</li>
            <li>Tip #2: If you want to match any WooCommerce product page, you can use the <strong>/product/</strong> string (Example: You might want to avoid minifying CSS files on these types of pages).</li>
        </ul>
    </div>

    <hr />

    <div id="wpacu-prevent-feature-rule-areas-wrap">
        <?php
        if ($optionsAlreadySet) {
            foreach ($data['do_not_load_plugin_features'] as $rowKey => $setValues) {
                if (! empty($setValues['pattern']) && ! empty($setValues['list'])) {
                    $setValues = array('pattern' => $setValues['pattern'], 'list' => $setValues['list']);
                    echo str_replace(' wpacu_chosen_can_be_later_enabled ', ' wpacu_chosen_select ', SettingsAdmin::generateNewRuleNoFeatureAreaRow($data, $setValues));
                }
            }
        } else {
            // Nothing was set; Show one rule area (default)
            // If the chosen marker is set, replace it so that "Chosen jQuery" will trigger
            echo str_replace(' wpacu_chosen_can_be_later_enabled ', ' wpacu_chosen_select ', SettingsAdmin::generateNewRuleNoFeatureAreaRow($data));
        }
        ?>
    </div>

    <div style="margin: 0 0 20px;"></div>
</fieldset>