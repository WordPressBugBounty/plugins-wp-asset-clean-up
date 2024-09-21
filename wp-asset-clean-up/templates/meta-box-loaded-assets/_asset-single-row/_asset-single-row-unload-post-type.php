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
        switch ( $data['post_type'] ) {
            case 'product':
                $unloadInPostTypeText = esc_html__( 'Unload NOSCRIPT tag on all WooCommerce "Product" pages', 'wp-asset-clean-up' );
                break;
            case 'download':
                $unloadInPostTypeText = esc_html__( 'Unload NOSCRIPT tag on all Easy Digital Downloads "Download" pages', 'wp-asset-clean-up' );
                break;
            default:
                $unloadInPostTypeText = sprintf( __( 'Unload NOSCRIPT tag on all pages of "<strong>%s</strong>" post type', 'wp-asset-clean-up' ), $data['post_type'] );
        }
    } else {
        // Default
        switch ( $data['post_type'] ) {
            case 'product':
                $unloadInPostTypeText = esc_html__( 'Unload JS on all WooCommerce "Product" pages', 'wp-asset-clean-up' );
                break;
            case 'download':
                $unloadInPostTypeText = esc_html__( 'Unload JS on all Easy Digital Downloads "Download" pages', 'wp-asset-clean-up' );
                break;
            default:
                $unloadInPostTypeText = sprintf( __( 'Unload JS on all pages of "<strong>%s</strong>" post type', 'wp-asset-clean-up' ), $data['post_type'] );
        }
    }
} else {
    switch ( $data['post_type'] ) {
        case 'product':
            $unloadInPostTypeText = esc_html__( 'Unload CSS on all WooCommerce "Product" pages', 'wp-asset-clean-up' );
            break;
        case 'download':
            $unloadInPostTypeText = esc_html__( 'Unload CSS on all Easy Digital Downloads "Download" pages', 'wp-asset-clean-up' );
            break;
        default:
            $unloadInPostTypeText = sprintf( __( 'Unload CSS on all pages of "<strong>%s</strong>" post type', 'wp-asset-clean-up' ), $data['post_type'] );
    }
}

// [wpacu_lite]
if (isset($data['row']['is_hardcoded']) && $data['row']['is_hardcoded']) {
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
    return; // Stop here as the code below is for not meant for hardcoded assets
}
// [/wpacu_lite]

if ($data['bulk_unloaded_type'] === 'post_type') {
	?>
	<div class="wpacu_asset_options_wrap" <?php if ($data['row']['global_unloaded']) { echo 'style="display: none;"'; } ?>>
		<?php
		// Unloaded On All Pages Belonging to the page's Post Type
		if ($data['row']['is_post_type_unloaded']) {
		    if ($assetType === 'scripts') {
                if (isset($data['row']['obj']->tag_output) && strncasecmp($data['row']['obj']->tag_output, '<noscript', 9) === 0) {
                    // NOSCRIPT tags / since v1.2.2.8
	                switch ( $data['post_type'] ) {
		                case 'product':
			                $alreadyUnloadedInPostTypeText = __( 'This NOSCRIPT tag is unloaded on all WooCommerce "Product" pages', 'wp-asset-clean-up-pro' );
			                break;
		                case 'download':
			                $alreadyUnloadedInPostTypeText = __( 'This NOSCRIPT tag is unloaded on all Easy Digital Downloads "Download" pages', 'wp-asset-clean-up-pro' );
			                break;
		                default:
			                $alreadyUnloadedInPostTypeText = sprintf( __( 'This NOSCRIPT tag is unloaded on all <u>%s</u> post types', 'wp-asset-clean-up-pro' ), $data['post_type'] );
	                }
                } else {
                    // Default
	                switch ( $data['post_type'] ) {
		                case 'product':
			                $alreadyUnloadedInPostTypeText = __( 'This JS is unloaded on all WooCommerce "Product" pages', 'wp-asset-clean-up' );
			                break;
		                case 'download':
			                $alreadyUnloadedInPostTypeText = __( 'This JS is unloaded on all Easy Digital Downloads "Download" pages', 'wp-asset-clean-up' );
			                break;
		                default:
			                $alreadyUnloadedInPostTypeText = sprintf( __( 'This JS is unloaded on all <u>%s</u> post types', 'wp-asset-clean-up' ), $data['post_type'] );
	                }
                }
		    } else {
			    switch ($data['post_type']) {
				    case 'product':
					    $alreadyUnloadedInPostTypeText = __('This CSS is unloaded on all WooCommerce "Product" pages', 'wp-asset-clean-up');
					    break;
				    case 'download':
					    $alreadyUnloadedInPostTypeText = __('This CSS is unloaded on all Easy Digital Downloads "Download" pages', 'wp-asset-clean-up');
					    break;
				    default:
					    $alreadyUnloadedInPostTypeText = sprintf(__('This CSS is unloaded on all <u>%s</u> post types', 'wp-asset-clean-up'), $data['post_type']);
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
						<?php echo wp_kses($unloadInPostTypeText, array('strong' => array(), 'u' => array(), 'em' => array())); ?> <small>* <?php _e('bulk unload', 'wp-asset-clean-up'); ?></small></label>
				</li>
				<?php
			}
			?>
		</ul>
	</div>
	<?php
}
