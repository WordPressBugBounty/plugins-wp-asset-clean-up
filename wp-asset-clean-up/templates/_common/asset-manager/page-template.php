<?php
if (!isset($data)) { exit; }
if (isset($data['page_template'])) {
	?>
    <p class="wpacu-page-template">
        <strong><?php if ($data['post_type'] === 'page') { echo 'Page'; } elseif ($data['post_type'] === 'post') { echo 'Post'; } ?>
            Template:</strong>
		<?php
		if (isset($data['all_page_templates'][$data['page_template']])) { ?>
            <u><?php echo esc_html($data['all_page_templates'][$data['page_template']]); ?></u>
		<?php } ?>

        (<?php echo esc_html($data['page_template']);

		if (isset($data['page_template_path'])) {
			echo '&nbsp; &#10230; &nbsp;<em>'.esc_html($data['page_template_path']).'</em>';
		}
		?>)
    </p>
	<?php
}

