<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
	exit;
}
?>
<hr style="margin: 15px 0;"/>
<h3><span class="dashicons dashicons-admin-appearance"></span> <?php _e('Critical CSS', 'wp-asset-clean-up'); ?></h3>

<div style="padding: 10px; background: white; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
<?php
if (isset($data['critical_css_disabled']) && $data['critical_css_disabled']) {
	echo '<p style="margin-top: 0;">This feature is globally disabled based on the option set in <a style="text-decoration: underline; color: #cc0000;" target="_blank" href="'.esc_url(admin_url('admin.php?page=wpassetcleanup_settings&wpacu_selected_tab_area=wpacu-setting-optimize-css#wpacu-critical-css-status')).'"><strong>"Settings" -&gt; "Optimize CSS" -&gt; "Critical CSS Status"</strong></a>, thus any of the critical CSS content set within <a target="_blank" href="'.esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css')).'"><strong>"CSS &amp; JS Manager" -&gt; "Manage Critical CSS"</strong></a> is not taking effect in the front-end view.</p>';
}

$pageTypeText = '';

if ( ! empty($data['critical_css_config']) ) {
	$atLeastOneSet = false;
	$pageGroups = $customPostTypes = $customTaxonomies = array();

	foreach ($data['critical_css_config'] as $pageType => $pageTypeValues) {
		$pageType = trim($pageType);

		if (isset($pageTypeValues['enable']) && $pageTypeValues['enable']) {
			if (in_array($pageType, array('homepage', 'posts', 'pages', '404_not_found', 'date', 'author', 'search', 'tag', 'category', 'media'))) {
				$pageGroups[] = $pageType;
				$atLeastOneSet = true;
			} elseif (strncmp($pageType, 'custom_post_type_', 17) === 0) {
				$customPostTypes[] = str_replace('custom_post_type_', '', $pageType);
				$atLeastOneSet = true;
			} elseif (strncmp($pageType, 'custom_taxonomy_', 16) === 0) {
				$customTaxonomies[] = str_replace('custom_taxonomy_', '', $pageType);
				$atLeastOneSet = true;
			}
		}
	}

	if ($atLeastOneSet) {
		$pageTypeText .= 'There is critical CSS content applied for the following <strong>page types / groups</strong>: ';
		if ( ! empty( $pageGroups ) ) {
			$pageTypeText .= implode( ', ', array_map(function($value) {
				$value = str_replace('404_not_found', '404 Not Found', $value);
				return ucfirst($value);
			}, $pageGroups) );
		}

		if ( ! empty( $customPostTypes ) ) {
			$pageTypeText .= ' / <strong>Custom Post Types:</strong> '.implode(', ', $customPostTypes);
		}

		if ( ! empty( $customTaxonomies ) ) {
			$pageTypeText .= ' / <strong>Custom Taxonomies:</strong> '.implode(', ', $customTaxonomies);
		}
	}
}

if ( ! (isset($data['critical_css_disabled']) && $data['critical_css_disabled']) ) { // not disabled
	$pageTypeText .= ' / <a target="_blank" href="'.esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css')).'">Manage it</a>';
}

$opacityLevel = (isset($data['critical_css_disabled']) && $data['critical_css_disabled']) ? '0.5' : 1;
echo '<p style="opacity: '.$opacityLevel.'; margin-top: 0; margin-bottom: 0;">'.trim($pageTypeText, ' / ').'</p>';
?>
</div>
