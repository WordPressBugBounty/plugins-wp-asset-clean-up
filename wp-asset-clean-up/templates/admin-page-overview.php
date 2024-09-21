<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
	exit;
}

include_once __DIR__ .  '/_top-area.php';
?>
<div class="wrap wpacu-overview-wrap">
    <div style="padding: 0 0 10px; line-height: 22px;"><strong>Note:</strong> This overview contains all the changes of any kind (unload rules, load exceptions, preloads, notes, async/defer SCRIPT attributes, changed positions, etc.) made via Asset CleanUp to any of the loaded (enqueued) CSS/JS files. To make any changes to the values below, please use the "CSS &amp; JS Manager" or "Bulk Changes" tabs.</div>
    <div style="padding: 0 10px 0 0;">
        <?php
        include_once __DIR__ .  '/_admin-page-overview-areas/_styles.php';
        include_once __DIR__ .  '/_admin-page-overview-areas/_critical-css.php';
        include_once __DIR__ .  '/_admin-page-overview-areas/_scripts.php';
        include_once __DIR__ .  '/_admin-page-overview-areas/_page-options.php';
        include_once __DIR__ .  '/_admin-page-overview-areas/_special-settings.php';
        ?>
    </div>
</div>