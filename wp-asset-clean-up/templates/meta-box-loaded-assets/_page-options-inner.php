<?php
if (! isset($data)) {
	exit; // no direct access
}
?>
<div class="wpacu-page-options-intro">
	<strong><?php esc_html_e('Page-specific optimization controls', 'wp-asset-clean-up'); ?></strong>
	<p><?php echo sprintf(esc_html__('Use these options only when an optimization causes an issue on this page. You can disable individual CSS or JavaScript operations, pause all front-end optimizations, or prevent %s from loading entirely.', 'wp-asset-clean-up'), WPACU_PLUGIN_TITLE); ?></p>
</div>
<ul id="wpacu-page-options-ul">
	<li>
		<label for="wpacu_page_options_no_css_minify">
			<input type="checkbox" <?php if (isset($data['page_options']['no_css_minify']) && $data['page_options']['no_css_minify']) { echo 'checked="checked"'; } ?> id="wpacu_page_options_no_css_minify" name="<?php echo WPACU_PLUGIN_ID; ?>_page_options[no_css_minify]" value="1" />
			<span><strong><?php esc_html_e('Disable CSS minification', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Keep CSS files unminified on this page.', 'wp-asset-clean-up'); ?></small></span>
		</label>
	</li>
	<li>
		<label for="wpacu_page_options_no_css_optimize">
			<input type="checkbox" <?php if (isset($data['page_options']['no_css_optimize']) && $data['page_options']['no_css_optimize']) { echo 'checked="checked"'; } ?> id="wpacu_page_options_no_css_optimize" name="<?php echo WPACU_PLUGIN_ID; ?>_page_options[no_css_optimize]" value="1" />
			<span><strong><?php esc_html_e('Disable CSS combination', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Load CSS files separately on this page.', 'wp-asset-clean-up'); ?></small></span>
		</label>
	</li>
	<li>
		<label for="wpacu_page_options_no_js_minify">
			<input type="checkbox" <?php if (isset($data['page_options']['no_js_minify']) && $data['page_options']['no_js_minify']) { echo 'checked="checked"'; } ?> id="wpacu_page_options_no_js_minify" name="<?php echo WPACU_PLUGIN_ID; ?>_page_options[no_js_minify]" value="1" />
			<span><strong><?php esc_html_e('Disable JavaScript minification', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Keep JavaScript files unminified on this page.', 'wp-asset-clean-up'); ?></small></span>
		</label>
	</li>
	<li>
		<label for="wpacu_page_options_no_js_optimize">
			<input type="checkbox" <?php if (isset($data['page_options']['no_js_optimize']) && $data['page_options']['no_js_optimize']) { echo 'checked="checked"'; } ?> id="wpacu_page_options_no_js_optimize" name="<?php echo WPACU_PLUGIN_ID; ?>_page_options[no_js_optimize]" value="1" />
			<span><strong><?php esc_html_e('Disable JavaScript combination', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Load JavaScript files separately on this page.', 'wp-asset-clean-up'); ?></small></span>
		</label>
	</li>
	<li class="wpacu-page-option-wide">
		<label for="wpacu_page_options_no_assets_settings">
			<input type="checkbox" <?php if (isset($data['page_options']['no_assets_settings']) && $data['page_options']['no_assets_settings']) { echo 'checked="checked"'; } ?> id="wpacu_page_options_no_assets_settings" name="<?php echo WPACU_PLUGIN_ID; ?>_page_options[no_assets_settings]" value="1" />
			<span><strong><?php esc_html_e('Disable all front-end optimizations', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Skip every Asset CleanUp optimization on this page, including all CSS and JavaScript changes.', 'wp-asset-clean-up'); ?></small></span>
		</label>
	</li>
	<li class="wpacu-page-option-wide wpacu-page-option-critical">
		<label for="wpacu_page_options_no_wpacu_load">
			<input type="checkbox" <?php if (isset($data['page_options']['no_wpacu_load']) && $data['page_options']['no_wpacu_load']) { echo 'checked="checked"'; } ?> id="wpacu_page_options_no_wpacu_load" name="<?php echo WPACU_PLUGIN_ID; ?>_page_options[no_wpacu_load]" value="1" />
			<span><strong><?php echo sprintf(esc_html__('Do not load %s on this page', 'wp-asset-clean-up'), WPACU_PLUGIN_TITLE); ?></strong><small><?php esc_html_e('Disable all plugin functionality for this page. Use this only to troubleshoot a plugin conflict.', 'wp-asset-clean-up'); ?></small></span>
		</label>
	</li>
</ul>
<p class="wpacu-page-options-help">
	<span class="dashicons dashicons-lightbulb"></span>
	<?php echo sprintf(esc_html__('If you are not sure how these options work, you can %sread more about them%s in the documentation.', 'wp-asset-clean-up'), '<a target="_blank" href="https://www.assetcleanup.com/docs/?p=1318">', '</a>'); ?>
</p>
<script>
(function($) {
	'use strict';

	function wpacuUpdatePageOptionsState() {
		$('#wpacu-page-options-ul').each(function() {
			var $options = $(this);
			var $disablePlugin = $options.find('#wpacu_page_options_no_wpacu_load');
			var disableOtherOptions = $disablePlugin.prop('checked');

			$options.find('input[type="checkbox"]').not($disablePlugin).each(function() {
				var $input = $(this);
				var $option = $input.closest('li');

				$input.attr('aria-disabled', disableOtherOptions ? 'true' : 'false');
				$option.toggleClass('wpacu-page-option-disabled', disableOtherOptions)
					.attr('aria-disabled', disableOtherOptions ? 'true' : 'false');

				if (disableOtherOptions) {
					if (typeof $input.attr('tabindex') !== 'undefined') {
						$input.attr('data-wpacu-original-tabindex', $input.attr('tabindex'));
					}
					$input.attr('tabindex', '-1');
				} else if (typeof $input.attr('data-wpacu-original-tabindex') !== 'undefined') {
					$input.attr('tabindex', $input.attr('data-wpacu-original-tabindex'))
						.removeAttr('data-wpacu-original-tabindex');
				} else {
					$input.removeAttr('tabindex');
				}
			});

			$options.find('input[type="checkbox"]').each(function() {
				$(this).closest('li').toggleClass('wpacu-page-option-selected', $(this).prop('checked'));
			});
		});
	}

	$(document)
		.off('change.wpacuPageOptions', '#wpacu-page-options-ul input[type="checkbox"]')
		.on('change.wpacuPageOptions', '#wpacu-page-options-ul input[type="checkbox"]', wpacuUpdatePageOptionsState);

	wpacuUpdatePageOptionsState();
})(jQuery);
</script>
<input type="hidden" name="wpacu_page_options_area_loaded" value="1" />
