<?php
if ( ! isset($data, $assetTypeS) ) {
    exit(); // no direct access
}

?>
<div class="wrap_bulk_unload_options">
    <?php
    // Unload on this page
    include __DIR__ . '/_asset-single-row-unload-per-page.php';

    // Unload site-wide (everywhere)
    include __DIR__ . '/_asset-single-row-unload-site-wide.php';

    if (isset($data['post_type']) && $data['post_type']) {
        // Unload on all pages of [post] post type (if applicable)
        include __DIR__ . '/_asset-single-row-unload-post-type.php';
    }

    // Unload on all pages where this [post] post type has a certain taxonomy set for it (e.g. a Tag or a Category) (if applicable)
    // There has to be at least a taxonomy created for this [post] post type to show this option
    if (isset($data['post_type']) && $data['post_type'] !== 'attachment' && ! $data['row']['is_post_type_unloaded'] && ! empty($data['post_type_has_tax_assoc'])) {
        include __DIR__ . '/_asset-single-row-unload-post-type-taxonomy.php';
    }

    // Unload via RegEx (if site-wide is not already chosen)
    include __DIR__ . '/_asset-single-row-unload-via-regex.php';

    // If any bulk unload rule is set, show the load exceptions
    include __DIR__ . '/_asset-single-row-load-exceptions.php';
    ?>
    <div class="wpacu_clearfix"></div>
</div>