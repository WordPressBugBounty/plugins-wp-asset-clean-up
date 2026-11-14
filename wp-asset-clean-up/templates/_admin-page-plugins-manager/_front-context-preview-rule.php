<?php
use WpAssetCleanUpLite\FrontendPluginsPreviewData as PreviewData;
if (!isset($key, $definition, $rule, $context, $path)) { exit; }
$kind     = $definition['kind'];
$values   = PreviewData::selectedValues($rule, $key, $definition);
$selected = $kind === 'fixed' ? PreviewData::isSelected($rule, $key, $definition['values'])
    : in_array($key, PreviewData::statuses($rule), true);
if ($kind === 'taxonomies') { $selected = $selected && !empty($values); }
$id = 'wpacu-preview-rule-' . md5($path . '|' . $key);
?>
<div class="wpacu-pm-page-rule<?php echo $kind !== 'fixed' ? ' wpacu-pm-page-rule--expanded' : ''; ?>" data-rule-key="<?php echo esc_attr($key); ?>">
    <label for="<?php echo esc_attr($id); ?>">
        <input id="<?php echo esc_attr($id); ?>" type="checkbox" disabled aria-disabled="true" <?php checked($selected); ?>>
        <span><?php echo esc_html(PreviewData::controlLabel($key, $context)); ?></span>
        <?php if ($kind === 'fixed' && $definition['values'] !== null && $key !== $context[strpos($key, 'unload_') === 0 ? 'unload_key' : 'load_key']) { ?>
            <small>* <?php echo strpos($key, 'unload_') === 0 ? esc_html__('bulk unload', 'wp-asset-clean-up') : esc_html__('bulk rule', 'wp-asset-clean-up'); ?></small>
        <?php } ?>
    </label>
    <?php if ($kind !== 'fixed') { ?>
    <div class="wpacu-pm-page-rule-fields"<?php if (!$selected) { ?> hidden<?php } ?>>
        <?php if ($kind === 'text') { ?>
            <textarea disabled aria-disabled="true" rows="3" aria-label="<?php esc_attr_e('Request URI rules', 'wp-asset-clean-up'); ?>"><?php echo esc_textarea(isset($rule[$key]['value']) && is_scalar($rule[$key]['value']) ? $rule[$key]['value'] : ''); ?></textarea>
            <p class="description"><?php esc_html_e('One rule per line. Plain URI strings and RegEx patterns are supported. Applies across the website.', 'wp-asset-clean-up'); ?></p>
        <?php } else {
            $choices = $kind === 'taxonomies' ? $definition['choices'] : ($kind === 'roles' ? wp_roles()->get_names() : array());
            foreach ($values as $value) {
                if (isset($choices[$value])) { continue; }
                $term = $kind === 'terms' ? get_term_by('term_taxonomy_id', (int)$value) : false;
                $choices[$value] = $term && !is_wp_error($term) ? $term->name : (string)$value;
            }
            ?>
            <select multiple disabled aria-disabled="true" aria-label="<?php echo esc_attr(PreviewData::controlLabel($key, $context)); ?>">
                <?php foreach ($choices as $value => $label) { ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected(in_array((string)$value, array_map('strval', $values), true)); ?>><?php echo esc_html($label); ?></option>
                <?php } ?>
            </select>
        <?php } ?>
    </div>
    <?php } ?>
</div>
