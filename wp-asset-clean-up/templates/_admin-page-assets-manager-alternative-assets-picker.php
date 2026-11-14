<?php
if ( ! defined('ABSPATH') ) { exit; }
$pickerTitle = __('Select posts, pages and other entries', 'wp-asset-clean-up');
$pickerDescription = __('Choose the individual entries where this alternative asset should load.', 'wp-asset-clean-up');
$pickerSearchLabel = __('Search by title or ID', 'wp-asset-clean-up');
$pickerHelp = __('Type at least two characters. Results are loaded as you search, grouped by content type.', 'wp-asset-clean-up');
$pickerChoose = __('Choose entries', 'wp-asset-clean-up');
$pickerApply = __('Use selected entries', 'wp-asset-clean-up');
$pickerMissing = __('Entry #%d (not currently available)', 'wp-asset-clean-up');
if ($isTermPicker) {
    $pickerTitle = $targetValue === 'post_tax_term' ? __('Select terms assigned to singular entries', 'wp-asset-clean-up') : __('Select taxonomy term archive pages', 'wp-asset-clean-up');
    $pickerDescription = $targetValue === 'post_tax_term' ? __('Choose terms whose assigned posts, pages or other singular entries should use this alternative asset.', 'wp-asset-clean-up') : __('Choose category, tag and other taxonomy archive pages. This does not select posts assigned to those terms.', 'wp-asset-clean-up');
    $pickerSearchLabel = __('Search by term name or ID', 'wp-asset-clean-up');
    $pickerHelp = __('Type at least one character. Results are loaded as you search, grouped by taxonomy.', 'wp-asset-clean-up');
    $pickerChoose = __('Choose taxonomy terms', 'wp-asset-clean-up');
    $pickerApply = __('Use selected terms', 'wp-asset-clean-up');
    $pickerMissing = __('Term #%d (not currently available)', 'wp-asset-clean-up');
} elseif ($targetValue === 'author') {
    $pickerTitle = __('Select author archive pages', 'wp-asset-clean-up');
    $pickerDescription = __('Choose the authors whose archive pages should use this alternative asset.', 'wp-asset-clean-up');
    $pickerSearchLabel = __('Search by name, username or ID', 'wp-asset-clean-up');
    $pickerHelp = __('Type at least one character. Results are loaded as you search.', 'wp-asset-clean-up');
    $pickerChoose = __('Choose authors', 'wp-asset-clean-up');
    $pickerApply = __('Use selected authors', 'wp-asset-clean-up');
    $pickerMissing = __('Author #%d (not currently available)', 'wp-asset-clean-up');
}
if ($isChoicePicker) {
    $pickerTitle = $targetLabel;
    $pickerDescription = __('Select one or more options where this alternative asset should load.', 'wp-asset-clean-up');
    $pickerSearchLabel = __('Available options', 'wp-asset-clean-up');
    $pickerHelp = __('These options are detected from your current WordPress installation.', 'wp-asset-clean-up');
    $pickerChoose = __('Choose options', 'wp-asset-clean-up');
    $pickerApply = __('Use selected options', 'wp-asset-clean-up');
}
if ($isUriPicker) {
    $pickerTitle = __('Request URI rules', 'wp-asset-clean-up');
    $pickerDescription = __('Use the alternative asset when the request URI matches any of these rules.', 'wp-asset-clean-up');
    $pickerSearchLabel = __('One URI string or regular expression per line', 'wp-asset-clean-up');
    $pickerHelp = __('For example: /shop/ or #^/landing/#. Any matching line applies.', 'wp-asset-clean-up');
    $pickerChoose = __('Edit URI rules', 'wp-asset-clean-up');
    $pickerApply = __('Use these rules', 'wp-asset-clean-up');
}
?>
<input id="wpacu-alt-assets-target-<?php echo esc_attr($targetValue); ?>" type="hidden" name="target_values[<?php echo esc_attr($targetValue); ?>]" value="<?php echo esc_attr($isUriPicker ? $selectedValue : $pickerValuePrefix . implode(',', $selectedPostIds)); ?>" <?php disabled( ! $targetSelected ); ?> />
<ul id="wpacu-alt-assets-<?php echo esc_attr($pickerKind); ?>-summary" class="wpacu-alt-assets__posts-summary" aria-live="polite"></ul>
<button type="button" class="button wpacu-alt-assets__choose-<?php echo esc_attr($pickerKind); ?>" aria-haspopup="dialog" aria-controls="wpacu-alt-assets-<?php echo esc_attr($pickerKind); ?>-modal" aria-expanded="false"><?php echo esc_html($pickerChoose); ?></button>
<div id="wpacu-alt-assets-<?php echo esc_attr($pickerKind); ?>-modal" class="wpacu-singular-posts-modal wpacu-alt-assets__entry-modal" hidden>
    <div class="wpacu-singular-posts-modal-backdrop" data-wpacu-alt-<?php echo esc_attr($pickerKind); ?>-cancel aria-hidden="true"></div>
    <div class="wpacu-singular-posts-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wpacu-alt-assets-<?php echo esc_attr($pickerKind); ?>-title" aria-describedby="wpacu-alt-assets-<?php echo esc_attr($pickerKind); ?>-description" tabindex="-1">
        <div class="wpacu-singular-posts-modal-header">
            <h2 id="wpacu-alt-assets-<?php echo esc_attr($pickerKind); ?>-title"><?php echo esc_html($pickerTitle); ?></h2>
            <p id="wpacu-alt-assets-<?php echo esc_attr($pickerKind); ?>-description"><?php echo esc_html($pickerDescription); ?></p>
            <button type="button" class="button-link wpacu-singular-posts-modal-close" data-wpacu-alt-<?php echo esc_attr($pickerKind); ?>-cancel aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
        </div>
        <div class="wpacu-singular-posts-modal-body">
            <label for="wpacu-alt-assets-<?php echo esc_attr($pickerKind); ?>-select"><?php echo esc_html($pickerSearchLabel); ?></label>
            <?php if ($isUriPicker) { ?>
                <textarea id="wpacu-alt-assets-<?php echo esc_attr($pickerKind); ?>-select" rows="7" spellcheck="false"><?php echo esc_textarea($selectedValue); ?></textarea>
            <?php } else { ?>
            <select id="wpacu-alt-assets-<?php echo esc_attr($pickerKind); ?>-select" multiple>
                <?php foreach ($isChoicePicker ? array_keys($selectedPosts) : $selectedPostIds as $selectedPostId) {
                    $postLabel = isset($selectedPosts[$selectedPostId]) ? $selectedPosts[$selectedPostId]['option_label'] : sprintf($pickerMissing, $selectedPostId);
                    ?>
                    <option value="<?php echo esc_attr($selectedPostId); ?>" <?php echo in_array($selectedPostId, $selectedPostIds, true) ? 'selected' : ''; ?>><?php echo esc_html($postLabel); ?></option>
                <?php } ?>
            </select>
            <?php } ?>
            <p class="description"><?php echo esc_html($pickerHelp); ?></p>
        </div>
        <div class="wpacu-singular-posts-modal-footer">
            <button type="button" class="button" data-wpacu-alt-<?php echo esc_attr($pickerKind); ?>-cancel><?php esc_html_e('Cancel', 'wp-asset-clean-up'); ?></button>
            <button type="button" class="button button-primary" id="wpacu-alt-assets-<?php echo esc_attr($pickerKind); ?>-apply"><?php echo esc_html($pickerApply); ?></button>
        </div>
    </div>
</div>
