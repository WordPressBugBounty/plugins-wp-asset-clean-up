<?php
if (! isset($showUnloadOnThisPageCheckUncheckAll, $showLoadItOnThisPageCheckUncheckAll, $locationMain, $locationChild)) {
	exit;
}
?>
<div class="wpacu-area-toggle-all-wrap">
    <div class="wpacu-area-toggle-all">
    <?php
    // Only show if there is at least one "Unload on this page" checkbox available
    // It won't be if there are only bulk unloads
    if ( $showUnloadOnThisPageCheckUncheckAll ) {
    ?>
        <div class="wpacu-left">
            &#10230; "Unload on this page" -
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
        </div>
    <?php
    }
    ?>

    <?php
    // Only show if there is at least one bulk unloaded asset
    // Otherwise, there is no load exception to make
    if ( $showLoadItOnThisPageCheckUncheckAll ) {
    ?>
        <div class="wpacu-left">
            &#10230; Make an exception from bulk unload, "Load it on this page" -
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

               href="#">Uncheck All</a> * <small>relevant if bulk unload rules (e.g. site-wide) are already applied</small>
        </div>
    <?php
    }
    ?>
        <div class="wpacu-right">
            <a class="wpacu-area-contract-all-assets wpacu_area_handles_row_expand_contract"

			    <?php if ($locationMain === 'plugins') { ?>
                    data-wpacu-plugin="<?php echo esc_html($locationChild); ?>"
			    <?php } ?>
               data-wpacu-area="<?php echo esc_html($locationChild); ?>_plugin"

               href="#">Contract</a>
            |
            <a class="wpacu-area-expand-all-assets wpacu_area_handles_row_expand_contract"

			    <?php if ($locationMain === 'plugins') { ?>
                    data-wpacu-plugin="<?php echo esc_html($locationChild); ?>"
			    <?php } ?>
               data-wpacu-area="<?php echo esc_html($locationChild); ?>_plugin"

               href="#">Expand</a>
            All Assets
        </div>
        <div class="wpacu_clearfix"></div>
    </div>
</div>
