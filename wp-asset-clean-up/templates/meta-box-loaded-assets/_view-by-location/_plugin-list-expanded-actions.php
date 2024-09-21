<?php
if (! isset($showUnloadOnThisPageCheckUncheckAll, $showLoadItOnThisPageCheckUncheckAll, $locationMain, $locationChild)) {
	exit;
}
?>
<div class="wpacu-area-toggle-all-wrap">
	<?php
	// Only show if there is at least one "Unload on this page" checkbox available
	// It won't be if there are only bulk unloads
	if ( $showUnloadOnThisPageCheckUncheckAll ) { ?>
        <div class="wpacu-area-toggle-all">
            <ul>
                <li>"Unload on this page"</li>
                <li>
                    <a class="wpacu-area-check-all"

	                    <?php if ($locationMain === 'plugins') { ?>
                            data-wpacu-plugin="<?php echo esc_html($locationChild); ?>"
	                    <?php } ?>
                       data-wpacu-area="<?php echo esc_html($locationChild); ?>_plugin"

                       href="#">Check All</a>
                    |
                    <a class="wpacu-area-uncheck-all"

	                    <?php if ($locationMain === 'plugins') { ?>
                            data-wpacu-plugin="<?php echo esc_html($locationChild); ?>"
	                    <?php } ?>
                       data-wpacu-area="<?php echo esc_html($locationChild); ?>_plugin"

                       href="#">Uncheck All</a>
                </li>
            </ul>
        </div>
	<?php } ?>
	<?php
	// Only show if there is at least one bulk unloaded asset
	// Otherwise, there is no load exception to make
	if ( $showLoadItOnThisPageCheckUncheckAll ) {
		?>
        <div class="wpacu-area-toggle-all" style="min-width: 390px;">
            <ul>
                <li>Make an exception from bulk unload, "Load it on this page"</li>
                <li>
                    <a class="wpacu-area-check-load-all"

	                    <?php if ($locationMain === 'plugins') { ?>
                            data-wpacu-plugin="<?php echo esc_html($locationChild); ?>"
	                    <?php } ?>
                       data-wpacu-area="<?php echo esc_html($locationChild); ?>_plugin"

                       href="#">Check All</a>
                    |
                    <a class="wpacu-area-uncheck-load-all"

	                    <?php if ($locationMain === 'plugins') { ?>
                            data-wpacu-plugin="<?php echo esc_html($locationChild); ?>"
	                    <?php } ?>
                       data-wpacu-area="<?php echo esc_html($locationChild); ?>_plugin"

                       href="#">Uncheck All</a>
                </li>
        </div>
		<?php
	}
	?>
</div>
