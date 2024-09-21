<?php
/*
 * The file is included from /templates/meta-box-loaded-assets/_asset-single-row.php
*/
if ( ! isset($data, $assetType, $assetTypeS, $assetTypeAbbr) ) {
    exit(); // no direct access
}

// [Start] Any dependencies
if (isset($data['row']['obj']->deps) && ! empty($data['row']['obj']->deps)) {
    $depsOutput = $dependsOnText = '';

    if ($assetType === 'styles') {
        if (is_array($data['row']['obj']->deps)) {
            $dependsOnText = (count($data['row']['obj']->deps) === 1)
                ? esc_html__('"Child" of one "parent" CSS file:', 'wp-asset-clean-up')
                : sprintf(esc_html__('"Child" of %s CSS "parent" files:', 'wp-asset-clean-up'),
                    count($data['row']['obj']->deps));
        } else {
            $dependsOnText = esc_html__('"Child" of "parent" CSS file(s):', 'wp-asset-clean-up');
        }
    } elseif ($assetType === 'scripts') {
        if (is_array($data['row']['obj']->deps)) {
            $dependsOnText = (count($data['row']['obj']->deps) === 1)
                ? esc_html__('"Child" of one "parent" JS file:', 'wp-asset-clean-up')
                : sprintf(esc_html__('"Child" of %s JS "parent" files:', 'wp-asset-clean-up'),
                    count($data['row']['obj']->deps));
        } else {
            $dependsOnText = esc_html__('"Child" of "parent" JS file(s):', 'wp-asset-clean-up');
        }
    }

    $depsOutput .= $dependsOnText . ' ';

    foreach ($data['row']['obj']->deps as $depHandle) {
        $depHandleText = $depHandle;

        $color = in_array($depHandle, $data['unloaded_'.$assetTypeAbbr.'_handles']) ? '#cc0000' : 'green';

        if ( $assetType === 'scripts' &&
            isset($jqueryIconHtmlDepends) &&
            ($depHandle === 'jquery' || strncmp($depHandle, 'jquery-ui-', 10) === 0) ) {
            $depHandleText .= '&nbsp;' . $jqueryIconHtmlDepends;
        }

        $depsOutput .= '<a style="color:' . $color . ';font-weight:300;" href="#wpacu_' . $assetTypeS . '_row_' . $depHandle . '"><span>' . $depHandleText . '</span></a>, ';
    }

    $depsOutput = rtrim($depsOutput, ', ');

    $extraInfo[] = $depsOutput;
}
// [End] Any dependencies
