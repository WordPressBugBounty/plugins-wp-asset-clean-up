<?php
/*
 * The file is included from /templates/meta-box-loaded-assets/_asset-single-row.php
*/

if ( ! isset($data, $assetType, $assetTypeS) ) {
    exit();
}

$handleNote = (isset($data['handle_notes'][$assetType][$data['row']['obj']->handle]) && $data['handle_notes'][$assetType][$data['row']['obj']->handle])
	? $data['handle_notes'][$assetType][$data['row']['obj']->handle]
	: false;
?>
<div class="wpacu-handle-notes">
    <?php if ($assetType === 'scripts') { ?>
        <?php if (! $handleNote) { ?>
            <p><small>No notes have been added about this JavaScript file (e.g. why you unloaded it or decided to keep it loaded) &#10230; <a data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>" href="#" class="wpacu-add-handle-note wpacu-for-<?php echo $assetTypeS; ?>"><span class="dashicons dashicons-welcome-write-blog"></span> <label for="wpacu_handle_note_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">Add Note</label></a></small></p>
            <?php } else { ?>
            <p><small>The following note has been added for this JavaScript file (<em>to have it removed on update, just leave the text area empty</em>):</small></p>
        <?php }
    } else { ?>
	    <?php if (! $handleNote) { ?>
            <p><small>No notes have been added about this stylesheet file (e.g. why you unloaded it or decided to keep it loaded) &#10230; <a data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>" href="#" class="wpacu-add-handle-note wpacu-for-<?php echo $assetTypeS; ?>"><span class="dashicons dashicons-welcome-write-blog"></span> <label for="wpacu_handle_note_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>">Add Note</label></a></small></p>
	        <?php } else { ?>
            <p><small>The following note has been added for this stylesheet file (<em>to have it removed on update, just leave the text area empty</em>):</small></p>
	    <?php }
	} ?>
	<div <?php if ($handleNote) { echo 'style="display: block;"'; } ?>
		data-<?php echo $assetTypeS; ?>-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
		class="wpacu-handle-notes-field">
                <textarea id="wpacu_handle_note_<?php echo $assetTypeS; ?>_<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                          style="min-height: 45px;"
                          data-wpacu-adapt-height="1"
                          data-wpacu-is-empty-on-page-load="<?php echo (! $handleNote) ? 'true' : 'false'; ?>"
                          <?php if (! $handleNote) { echo 'disabled="disabled"'; } ?>
                          placeholder="<?php echo ($assetType === 'scripts') ? esc_attr__('Add your note here about this JavaScript file', 'wp-asset-clean-up') : esc_attr__('Add your note here about this stylesheet file', 'wp-asset-clean-up'); ?>"
                          name="wpacu_handle_notes[<?php echo $assetType; ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>]"><?php echo esc_textarea($handleNote); ?></textarea>
	</div>
</div>