<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Misc;

if ( ! isset($data) ) {
    exit;
}

// The target panel already describes both the general and individual scope for these page types.
if (in_array($data['for'], array(
    'posts', 'pages', 'media_attachment', 'custom_post_types',
    'category', 'tag', 'custom_taxonomies', 'author'
), true)) {
    return;
}

$scopeTitle = '';
$scopeText  = '';
$exampleUrl   = '';
$openLinkText = __('Open example', 'wp-asset-clean-up');

if ($data['for'] === 'homepage') {
    $scopeTitle = __('Homepage', 'wp-asset-clean-up');
    $scopeText  = __('The main page of the website.', 'wp-asset-clean-up');
    $exampleUrl = $data['site_url'];
} elseif ($data['for'] === 'custom_post_type_archives') {
    $postType       = isset($data['chosen_post_type']) ? $data['chosen_post_type'] : '';
    $postTypeObject = $postType !== '' ? get_post_type_object($postType) : false;
    $postTypeLabel  = ($postTypeObject && isset($postTypeObject->labels->name))
        ? $postTypeObject->labels->name
        : $postType;
    $singularLabel  = ($postTypeObject && isset($postTypeObject->labels->singular_name))
        ? $postTypeObject->labels->singular_name
        : $postType;

    $scopeTitle = sprintf(__('%s Archive', 'wp-asset-clean-up'), $postTypeLabel);
    $scopeText  = sprintf(
        __('Used for the main %1$s archive and its pagination pages, not for individual %2$s entries.', 'wp-asset-clean-up'),
        $postTypeLabel,
        $singularLabel
    );
    $exampleUrl   = $postType !== '' ? get_post_type_archive_link($postType) : '';
    $openLinkText = __('Open archive', 'wp-asset-clean-up');
} elseif ($data['for'] === 'search') {
    $scopeTitle = __('All search results pages', 'wp-asset-clean-up');
    $scopeText  = __('Used whenever WordPress displays search results.', 'wp-asset-clean-up');
    $exampleUrl = add_query_arg('s', 'keyword-here', get_site_url('/'));
} elseif ($data['for'] === 'date') {
    $scopeTitle = __('All date archive pages', 'wp-asset-clean-up');
    $scopeText  = __('Used for yearly, monthly and daily post archives.', 'wp-asset-clean-up');

    $dateArchivesOutput = trim(wp_get_archives(array(
        'echo'            => 0,
        'post_type'       => 'post',
        'show_post_count' => true,
        'format'          => ''
    )));

    if (strpos($dateArchivesOutput, '<a href=') !== false) {
        $allLinks = strpos($dateArchivesOutput, "\n") !== false
            ? explode("\n", $dateArchivesOutput)
            : array($dateArchivesOutput);
        $bestLink = isset($allLinks[0]) ? $allLinks[0] : '';

        if (count($allLinks) > 1) {
            $allCounts = array();

            foreach ($allLinks as $linkKey => $link) {
                $linkParts = explode('&nbsp;', $link);
                $allCounts[$linkKey] = isset($linkParts[1]) ? (int)trim($linkParts[1], '()') : 0;
            }

            arsort($allCounts);
            $bestLinkKey = Misc::arrayKeyFirst($allCounts);
            $bestLink    = isset($allLinks[$bestLinkKey]) ? $allLinks[$bestLinkKey] : $bestLink;
        }

        $exampleUrl = Misc::extractBetween($bestLink, '<a href=', '>');
        $exampleUrl = trim($exampleUrl, "'\"");
    }
} elseif ($data['for'] === '404_not_found') {
    $scopeTitle = __('All 404 Not Found pages', 'wp-asset-clean-up');
    $scopeText  = __('Used whenever the requested URL does not exist.', 'wp-asset-clean-up');
    $exampleUrl = trailingslashit(get_site_url()) . 'page-that-does-not-exist-' . wp_rand(1000, 9999);
}

if ($scopeTitle === '') {
    return;
}
?>
<div class="wpacu-critical-css-scope-summary">
    <span class="dashicons dashicons-location" aria-hidden="true"></span>

    <div>
        <strong><?php echo esc_html($scopeTitle); ?></strong>
        <span><?php echo esc_html($scopeText); ?></span>
    </div>

    <?php if ($exampleUrl) { ?>
        <a target="_blank" href="<?php echo esc_url($exampleUrl); ?>">
            <?php echo esc_html($openLinkText); ?>
            <span class="dashicons dashicons-external" aria-hidden="true"></span>
        </a>
    <?php } ?>
</div>
