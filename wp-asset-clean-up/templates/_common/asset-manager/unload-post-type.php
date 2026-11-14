<?php
/*
 * The file is included from /templates/meta-box-loaded-assets/_asset-single-row.php
*/

if (! isset($data, $assetType, $assetTypeS)) {
    exit(); // no direct access
}

// Only show it if "Unload site-wide" is NOT enabled
// Otherwise, there's no point to use an unload regex if the asset is unloaded site-wide
if (isset($data['row']['global_unloaded']) && $data['row']['global_unloaded']) {
    return;
}

// Set the unload post type text
if ($assetType === 'scripts') {
    if (isset($data['row']['obj']->tag_output) && strncasecmp($data['row']['obj']->tag_output, '<noscript', 9) === 0) {
        // NOSCRIPT tags / since v1.2.2.8
        switch ($data['post_type']) {
            case 'page':
                $unloadInPostTypeText = esc_html__('Unload this NOSCRIPT tag on all WordPress pages', 'wp-asset-clean-up');
                break;
            case 'post':
                $unloadInPostTypeText = esc_html__('Unload this NOSCRIPT tag on all blog posts', 'wp-asset-clean-up');
                break;
            case 'product':
                $unloadInPostTypeText = esc_html__('Unload this NOSCRIPT tag on all product pages', 'wp-asset-clean-up');
                break;
            default:
                $wpacuPostTypeObject = get_post_type_object($data['post_type']);
                $wpacuPostTypeLabel  = $wpacuPostTypeObject && !empty($wpacuPostTypeObject->labels->name)
                    ? $wpacuPostTypeObject->labels->name
                    : $data['post_type'];

                $unloadInPostTypeText = sprintf(
                    __('Unload this NOSCRIPT tag on all %1$s ("%2$s" post type)', 'wp-asset-clean-up'),
                    esc_html($wpacuPostTypeLabel),
                    esc_html($data['post_type'])
                );
        }
    } else {
        // Default
        switch ($data['post_type']) {
            case 'page':
                $unloadInPostTypeText = esc_html__('Unload this JS on all WordPress pages', 'wp-asset-clean-up');
                break;
            case 'post':
                $unloadInPostTypeText = esc_html__('Unload this JS on all blog posts', 'wp-asset-clean-up');
                break;
            case 'product':
                $unloadInPostTypeText = esc_html__('Unload this JS on all product pages', 'wp-asset-clean-up');
                break;
            default:
                $wpacuPostTypeObject = get_post_type_object($data['post_type']);
                $wpacuPostTypeLabel  = $wpacuPostTypeObject && !empty($wpacuPostTypeObject->labels->name)
                    ? $wpacuPostTypeObject->labels->name
                    : $data['post_type'];

                $unloadInPostTypeText = sprintf(
                    __('Unload this JS on all %1$s ("%2$s" post type)', 'wp-asset-clean-up'),
                    esc_html($wpacuPostTypeLabel),
                    esc_html($data['post_type'])
                );
        }
    }
} else {
    switch ($data['post_type']) {
        case 'page':
            $unloadInPostTypeText = esc_html__('Unload this CSS on all WordPress pages', 'wp-asset-clean-up');
            break;
        case 'post':
            $unloadInPostTypeText = esc_html__('Unload this CSS on all blog posts', 'wp-asset-clean-up');
            break;
        case 'product':
            $unloadInPostTypeText = esc_html__('Unload this CSS on all product pages', 'wp-asset-clean-up');
            break;
        default:
            $wpacuPostTypeObject = get_post_type_object($data['post_type']);
            $wpacuPostTypeLabel  = $wpacuPostTypeObject && !empty($wpacuPostTypeObject->labels->name)
                ? $wpacuPostTypeObject->labels->name
                : $data['post_type'];

            $unloadInPostTypeText = sprintf(
                __('Unload this CSS on all %1$s ("%2$s" post type)', 'wp-asset-clean-up'),
                esc_html($wpacuPostTypeLabel),
                esc_html($data['post_type'])
            );
    }
}

if (! defined('WPACU_PRO_PLUGIN_VERSION') && ! empty($data['row']['is_hardcoded'])) {
?>
    <div class="wpacu_asset_options_wrap">
        <ul class="wpacu_asset_options">
            <li>
                <label class="wpacu-manage-hardcoded-assets-requires-pro-popup">
                    <span style="color: #ccc;" class="wpacu-manage-hardcoded-assets-requires-pro-popup dashicons dashicons-lock"></span>
                    <?php echo wp_kses($unloadInPostTypeText, array('strong' => array(), 'em' => array(), 'u' => array())); ?>
                </label>
            </li>
        </ul>
    </div>
<?php
    return;
}

if ($data['bulk_unloaded_type'] === 'post_type') {
	?>
	<div class="wpacu_asset_options_wrap" <?php if ($data['row']['global_unloaded']) { echo 'style="display: none;"'; } ?>>
		<?php
		// Unloaded On All Pages Belonging to the page's Post Type
		if ($data['row']['is_post_type_unloaded']) {
		    if ($assetType === 'scripts') {
                if (isset($data['row']['obj']->tag_output) && strncasecmp($data['row']['obj']->tag_output, '<noscript', 9) === 0) {
                    // NOSCRIPT tags / since v1.2.2.8
	                switch ($data['post_type']) {
	                    case 'page':
	                        $alreadyUnloadedInPostTypeText = __('This NOSCRIPT tag is unloaded on all WordPress pages', 'wp-asset-clean-up');
	                        break;
	                    case 'post':
	                        $alreadyUnloadedInPostTypeText = __('This NOSCRIPT tag is unloaded on all blog posts', 'wp-asset-clean-up');
	                        break;
	                    case 'product':
	                        $alreadyUnloadedInPostTypeText = __('This NOSCRIPT tag is unloaded on all product pages', 'wp-asset-clean-up');
	                        break;
	                    default:
	                        $wpacuPostTypeObject = get_post_type_object($data['post_type']);
	                        $wpacuPostTypeLabel  = $wpacuPostTypeObject && !empty($wpacuPostTypeObject->labels->name)
	                            ? $wpacuPostTypeObject->labels->name
	                            : $data['post_type'];

	                        $alreadyUnloadedInPostTypeText = sprintf(
	                            __('This NOSCRIPT tag is unloaded on all %1$s ("%2$s" post type)', 'wp-asset-clean-up'),
	                            esc_html($wpacuPostTypeLabel),
	                            esc_html($data['post_type'])
	                        );
	                }
                } else {
                    // Default
	                switch ($data['post_type']) {
	                    case 'page':
	                        $alreadyUnloadedInPostTypeText = __('This JS is unloaded on all WordPress pages', 'wp-asset-clean-up');
	                        break;
	                    case 'post':
	                        $alreadyUnloadedInPostTypeText = __('This JS is unloaded on all blog posts', 'wp-asset-clean-up');
	                        break;
	                    case 'product':
	                        $alreadyUnloadedInPostTypeText = __('This JS is unloaded on all product pages', 'wp-asset-clean-up');
	                        break;
	                    default:
	                        $wpacuPostTypeObject = get_post_type_object($data['post_type']);
	                        $wpacuPostTypeLabel  = $wpacuPostTypeObject && !empty($wpacuPostTypeObject->labels->name)
	                            ? $wpacuPostTypeObject->labels->name
	                            : $data['post_type'];

	                        $alreadyUnloadedInPostTypeText = sprintf(
	                            __('This JS is unloaded on all %1$s ("%2$s" post type)', 'wp-asset-clean-up'),
	                            esc_html($wpacuPostTypeLabel),
	                            esc_html($data['post_type'])
	                        );
	                }
                }
		    } else {
			    switch ($data['post_type']) {
			        case 'page':
			            $alreadyUnloadedInPostTypeText = __('This CSS is unloaded on all WordPress pages', 'wp-asset-clean-up');
			            break;
			        case 'post':
			            $alreadyUnloadedInPostTypeText = __('This CSS is unloaded on all blog posts', 'wp-asset-clean-up');
			            break;
			        case 'product':
			            $alreadyUnloadedInPostTypeText = __('This CSS is unloaded on all product pages', 'wp-asset-clean-up');
			            break;
			        default:
			            $wpacuPostTypeObject = get_post_type_object($data['post_type']);
			            $wpacuPostTypeLabel  = $wpacuPostTypeObject && !empty($wpacuPostTypeObject->labels->name)
			                ? $wpacuPostTypeObject->labels->name
			                : $data['post_type'];

			            $alreadyUnloadedInPostTypeText = sprintf(
			                __('This CSS is unloaded on all %1$s ("%2$s" post type)', 'wp-asset-clean-up'),
			                esc_html($wpacuPostTypeLabel),
			                esc_html($data['post_type'])
			            );
			    }
		    }
			?>
			<div style="margin: 0 0 4px !important;"><strong style="color: #d54e21;"><?php echo wp_kses($alreadyUnloadedInPostTypeText, array('u' => array(), 'em' => array())); ?>.</strong></div>
			<?php
		}
		?>
		<ul class="wpacu_asset_options">
			<?php
			if ($data['row']['is_post_type_unloaded']) {
				?>
				<li>
					<label><input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
					              class="wpacu_post_type_option wpacu_post_type_<?php echo $assetTypeS; ?> wpacu_keep_bulk_rule"
					              type="radio"
					              name="wpacu_options_post_type_<?php echo $assetType; ?>[<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>]"
					              checked="checked"
					              value="default"/>
						<?php _e('Keep bulk rule', 'wp-asset-clean-up'); ?></label>
				</li>

				<li>
					<label><input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
					              class="wpacu_post_type_option wpacu_post_type_<?php echo $assetTypeS; ?> wpacu_remove_bulk_rule"
					              type="radio"
					              name="wpacu_options_post_type_<?php echo $assetType; ?>[<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>]"
					              value="remove"/>
						<?php esc_html_e('Remove bulk rule', 'wp-asset-clean-up'); ?></label>
				</li>
				<?php
			} else {
				?>
				<li>
					<label><input data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
					              data-handle-for="<?php echo $assetTypeS; ?>"
					              class="wpacu_bulk_unload wpacu_post_type_unload wpacu_post_type_<?php echo $assetTypeS; ?> wpacu_unload_rule_input wpacu_unload_rule_for_<?php echo $assetTypeS; ?>"
					              id="wpacu_global_unload_post_type_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
					              type="checkbox"
					              name="wpacu_bulk_unload_<?php echo $assetType; ?>[post_type][<?php echo esc_attr($data['post_type']); ?>][]"
					              value="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"/>
						<?php echo wp_kses($unloadInPostTypeText, array('strong' => array(), 'u' => array(), 'em' => array())); ?></label>
				</li>
				<?php
			}
			?>
		</ul>
	</div>
	<?php
}
