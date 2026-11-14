<?php
if ( ! defined('ABSPATH') || ! isset($formOutputMode)) {
    exit;
}
?>
<div class="wpacu-alt-assets__output">
    <div class="wpacu-alt-assets__output-heading">
        <span id="wpacu-alt-assets-output-label"><?php esc_html_e('Output', 'wp-asset-clean-up'); ?></span>
        <details class="wpacu-alt-assets__output-help">
            <summary aria-label="<?php esc_attr_e('About output options', 'wp-asset-clean-up'); ?>">
                <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
            </summary>
            <div class="wpacu-alt-assets__savings-explanation">
                <strong><?php esc_html_e('Editable source, separate output', 'wp-asset-clean-up'); ?></strong>
                <p><?php esc_html_e('Your code is saved exactly as it appears in the editor. For example, after using Beautify and saving, you will see the formatted code when you return.', 'wp-asset-clean-up'); ?></p>
                <p><strong><?php esc_html_e('Follow global settings', 'wp-asset-clean-up'); ?></strong> — <?php esc_html_e('Uses the plugin’s CSS or JavaScript minification settings, including file, handle and page exclusions.', 'wp-asset-clean-up'); ?></p>
                <p><strong><?php esc_html_e('Serve minified', 'wp-asset-clean-up'); ?></strong> — <?php esc_html_e('Minifies the generated file for this alternative, even when global minification is off or the original asset is excluded. The editor code stays unchanged.', 'wp-asset-clean-up'); ?></p>
                <p><strong><?php esc_html_e('Serve without minification', 'wp-asset-clean-up'); ?></strong> — <?php esc_html_e('Generates this alternative without minifying it, even when global minification is on. This does not expand code that is already minified; use Beautify in the editor if needed.', 'wp-asset-clean-up'); ?></p>
                <p><?php esc_html_e('This choice applies when you save. An OFF alternative continues to use the original asset. Click the info icon again, click outside this panel, or press Escape to close it.', 'wp-asset-clean-up'); ?></p>
            </div>
        </details>
    </div>
    <div class="wpacu-alt-assets__output-options" role="radiogroup" aria-labelledby="wpacu-alt-assets-output-label" aria-describedby="wpacu-alt-assets-output-description">
        <label class="wpacu-alt-assets__output-option">
            <input type="radio" name="output_mode" value="inherit" <?php checked($formOutputMode, 'inherit'); ?> />
            <span class="wpacu-alt-assets__output-option-body">
                <strong><?php esc_html_e('Follow global settings', 'wp-asset-clean-up'); ?></strong>
                <span><?php esc_html_e('Use the plugin’s minification settings and exclusions.', 'wp-asset-clean-up'); ?></span>
            </span>
        </label>
        <label class="wpacu-alt-assets__output-option">
            <input type="radio" name="output_mode" value="minified" <?php checked($formOutputMode, 'minified'); ?> />
            <span class="wpacu-alt-assets__output-option-body">
                <strong><?php esc_html_e('Serve minified', 'wp-asset-clean-up'); ?> <span class="wpacu-alt-assets__recommended"><?php esc_html_e('Recommended', 'wp-asset-clean-up'); ?></span></strong>
                <span><?php esc_html_e('Minify this alternative, overriding global settings and exclusions.', 'wp-asset-clean-up'); ?></span>
            </span>
        </label>
        <label class="wpacu-alt-assets__output-option">
            <input type="radio" name="output_mode" value="unminified" <?php checked($formOutputMode, 'unminified'); ?> />
            <span class="wpacu-alt-assets__output-option-body">
                <strong><?php esc_html_e('Serve without minification', 'wp-asset-clean-up'); ?></strong>
                <span><?php esc_html_e('Skip minification for this alternative, even when enabled globally.', 'wp-asset-clean-up'); ?></span>
            </span>
        </label>
    </div>
    <p class="description" id="wpacu-alt-assets-output-description"><?php esc_html_e('Your editor code is saved unchanged. This choice only affects the generated file.', 'wp-asset-clean-up'); ?></p>
</div>
