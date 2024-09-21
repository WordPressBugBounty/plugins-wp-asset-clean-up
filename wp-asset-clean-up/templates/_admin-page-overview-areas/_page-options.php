<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
	exit;
}
?>
<!-- [Page Options Area] -->
<?php
$hasPostsWithOptions    = ! empty($data['page_options_results']['posts']);
$hasHomepageWithOptions = ! empty( $data['page_options_results']['homepage']['options'] );
$hasAtLeastOneRecord    = $hasPostsWithOptions || $hasHomepageWithOptions;
?>
<div id="wpacu-page-options-wrap">
    <h3><span class="dashicons dashicons-admin-generic"></span> <?php _e('Page Options', 'wp-asset-clean-up'); ?></h3>
    <div style="padding: 10px; background: white; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
		<?php
		if ($hasAtLeastOneRecord) {
		?>
        <p>On the pages listed below, there are special options set. <a target="_blank" style="text-decoration: none;" href="https://www.assetcleanup.com/docs/?p=1318"><span class="dashicons dashicons-info"></span> Read more</a></p>
        <table class="wp-list-table wpacu-list-table widefat plugins striped" style="margin: 10px 0 0; width: 100%;">
			<?php
			}

			if ( $hasHomepageWithOptions ) {
				$optionsForCurrentPage = array();

				foreach ($data['page_options_results']['homepage']['options'] as $optionKey => $optionValue) {
					if (isset($data['page_options_to_text'][$optionKey]) && $optionValue) {
						$optionsForCurrentPage[] = $data['page_options_to_text'][$optionKey];
					}
				}
				?>
                <tr>
                    <td><span class="dashicons dashicons-admin-home"></span> Homepage (e.g. latest posts)<br /><small><a target="_blank" href="<?php echo get_site_url(); ?>"><?php echo get_site_url(); ?></a></small></td>
                    <td><?php echo implode (', ', $optionsForCurrentPage); ?></td>
                </tr>
				<?php
			}

			if ( $hasPostsWithOptions ) {
				foreach ($data['page_options_results']['posts'] as $results) {
					$rowStyle = '';
					$postExists = true;

                    if (get_post($results['post_id']) === null) {
                        $postExists = false;
	                    $postStatus = $postStatusText = '';
	                    $rowStyle   = 'style="opacity: 0.6;"';
                    } else {
	                    $postStatus = $postStatusText = get_post_status( $results['post_id'] );

	                    if ( ! in_array( $postStatus, array( 'publish', 'private' ) ) ) {
		                    $rowStyle       = 'style="opacity: 0.6;"';
		                    $postStatusText = '<span style="color: #cc0000;">' . $postStatus . '</span>';
	                    }
                    }
					?>
                    <tr <?php echo wp_kses($rowStyle, array('style' => array())); ?>>
                        <td>
                            <?php if ($postExists) { ?>
                                <?php echo get_the_title($results['post_id']); ?> / ID: <?php echo (int)$results['post_id']; ?>, Status: <?php echo wp_kses($postStatusText, array('span' => array('style' => array()))); ?><br /><small><a target="_blank" href="<?php echo get_permalink($results['post_id']); ?>"><?php echo get_permalink($results['post_id']); ?></a></small>
                            <?php } else { ?>
                                ID: <s style="color: #cc0000;" class="wpacu-tooltip" title="N/A (post deleted)"><?php echo (int)$results['post_id']; ?></s>
                            <?php } ?>
                        </td>
                        <td>
							<?php
							$optionsForCurrentPage = array();

							foreach ($results['options'] as $optionKey => $optionValue) {
								if ($optionKey === '_page_uri') {
									// Hidden and irrelevant
									continue;
								}

								if (isset($data['page_options_to_text'][$optionKey]) && $optionValue) {
									$optionsForCurrentPage[] = $data['page_options_to_text'][$optionKey];
								}
							}

							echo implode (', ', $optionsForCurrentPage);
							?>
                        </td>
                    </tr>
					<?php
				}
			}

			if ($hasAtLeastOneRecord) {
			?>
        </table>
	<?php
	}
	?>

		<?php if ( ! $hasAtLeastOneRecord ) { ?>
            There are no special options set for any page. <a style="text-decoration: none;" target="_blank" href="https://www.assetcleanup.com/docs/?p=1318"><span class="dashicons dashicons-info"></span> Read more</a>
		<?php } ?>
    </div>
</div>
<!-- [/Page Options Area] -->
