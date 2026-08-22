<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}

$tabIdArea       = 'wpacu-setting-html-source-cleanup';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea)
    ? 'style="display: table-cell;"'
    : '';
$settingsName = WPACU_PLUGIN_ID . '_settings';

$removeRsdLink           = ! empty($data['remove_rsd_link']);
$removeRestApiLink       = ! empty($data['remove_rest_api_link']);
$removeShortlink         = ! empty($data['remove_shortlink']);
$removeWpVersion         = ! empty($data['remove_wp_version']);
$removeGeneratorTag      = ! empty($data['remove_generator_tag']);
$removePostsRelLinks     = ! empty($data['remove_posts_rel_links']);
$removeWlwLink           = ! empty($data['remove_wlw_link']);
$removeHtmlComments      = ! empty($data['remove_html_comments']);
$htmlCommentExceptions   = isset($data['remove_html_comments_exceptions'])
    ? (string) $data['remove_html_comments_exceptions']
    : '';
$xmlRpcDisabledCompletely = isset($data['disable_xmlrpc'])
    && ($data['disable_xmlrpc'] === 'disable_all');

global $wp_version;

$showPostsRelLinks = version_compare($wp_version, '5.6.0', '<');
$showWlwLink       = version_compare($wp_version, '6.3.0', '<');
$showLegacyGroup   = $showPostsRelLinks || $showWlwLink;
?>
<div id="<?php echo esc_attr($tabIdArea); ?>"
     class="wpacu-settings-tab-content"
     <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <main id="wpacu-html-source-cleanup-settings" class="wpacu-html-source-page">
        <section class="wpacu-html-source-panel" aria-labelledby="wpacuHtmlSourceTitle">
            <header class="wpacu-html-source-header">
                <div>
                    <div class="wpacu-html-source-eyebrow">
                        <?php esc_html_e('HTML output', 'wp-asset-clean-up'); ?>
                    </div>
                    <h2 id="wpacuHtmlSourceTitle">
                        <?php esc_html_e('Remove optional metadata from the HTML source', 'wp-asset-clean-up'); ?>
                    </h2>
                    <p>
                        <?php esc_html_e('Clean up selected discovery links, generator metadata, and HTML comments from the generated page source. Each option below explains what changes and what remains available.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>

                <div class="wpacu-html-source-header-badge">
                    <?php esc_html_e('Advanced', 'wp-asset-clean-up'); ?>
                </div>
            </header>

            <div class="wpacu-html-source-body">
                <section class="wpacu-html-source-intro" aria-labelledby="wpacuHtmlSourceIntroTitle">
                    <div class="wpacu-html-source-intro-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 9 4 12l4 3"></path>
                            <path d="m16 9 4 3-4 3"></path>
                            <path d="m14 5-4 14"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 id="wpacuHtmlSourceIntroTitle">
                            <?php esc_html_e('Clean up the source without confusing it with feature removal', 'wp-asset-clean-up'); ?>
                        </h3>
                        <p>
                            <?php esc_html_e('Most controls on this page remove optional markup only. For example, removing the REST API discovery link does not disable the REST API. These changes are also not security controls by themselves, so enable only the output cleanup you actually want.', 'wp-asset-clean-up'); ?>
                        </p>
                    </div>
                </section>

                <div class="wpacu-html-source-section-heading">
                    <h3><?php esc_html_e('Discovery links', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('These tags help applications or browsers discover related WordPress services and alternate URLs.', 'wp-asset-clean-up'); ?></p>
                </div>

                <div class="wpacu-html-source-list">
                    <article class="wpacu-html-source-card<?php echo $removeRsdLink ? ' is-removed' : ''; ?><?php echo $xmlRpcDisabledCompletely ? ' is-covered' : ''; ?>" id="wpacuHtmlSourceRsdCard">
                        <div class="wpacu-html-source-control">
                            <span class="wpacu-html-source-control-kicker">
                                <?php esc_html_e('HTML output', 'wp-asset-clean-up'); ?>
                            </span>

                            <?php if ($xmlRpcDisabledCompletely) { ?>
                                <span class="wpacu-html-source-static-state">
                                    <?php esc_html_e('Already removed', 'wp-asset-clean-up'); ?>
                                </span>
                                <span class="wpacu-html-source-control-help">
                                    <?php esc_html_e('Handled by the XML-RPC setting', 'wp-asset-clean-up'); ?>
                                </span>
                                <input type="hidden"
                                       name="<?php echo esc_attr($settingsName); ?>[remove_rsd_link]"
                                       value="<?php echo $removeRsdLink ? '1' : '0'; ?>" />
                            <?php } else { ?>
                                <label class="wpacu-html-source-toggle"
                                       data-visual-state="<?php echo $removeRsdLink ? 'changed' : 'default'; ?>"
                                       for="wpacu_remove_rsd_link">
                                    <input id="wpacu_remove_rsd_link"
                                           type="checkbox"
                                           <?php checked($removeRsdLink); ?>
                                           name="<?php echo esc_attr($settingsName); ?>[remove_rsd_link]"
                                           value="1" />
                                    <span class="wpacu-html-source-toggle__track" aria-hidden="true">
                                        <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--default"><?php esc_html_e('Present', 'wp-asset-clean-up'); ?></span>
                                        <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--changed"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                                        <span class="wpacu-html-source-toggle__thumb"></span>
                                    </span>
                                    <span class="wpacu-html-source-native-label"><?php esc_html_e('Remove from HTML', 'wp-asset-clean-up'); ?></span>
                                    <span class="screen-reader-text"><?php esc_html_e('Remove the RSD discovery link from the HTML source', 'wp-asset-clean-up'); ?></span>
                                </label>
                            <?php } ?>
                        </div>

                        <div class="wpacu-html-source-copy">
                            <div class="wpacu-html-source-title-row">
                                <div class="wpacu-html-source-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10 13a5 5 0 0 0 7.1.1l2-2A5 5 0 0 0 12 4l-1.1 1.1"></path>
                                        <path d="M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"></path>
                                    </svg>
                                </div>
                                <div>
                                    <span class="wpacu-html-source-subtitle"><?php esc_html_e('Legacy publishing discovery', 'wp-asset-clean-up'); ?></span>
                                    <h4><?php esc_html_e('RSD discovery link', 'wp-asset-clean-up'); ?></h4>
                                </div>
                                <span class="wpacu-html-source-risk-badge">
                                    <?php esc_html_e('Integration-dependent', 'wp-asset-clean-up'); ?>
                                </span>
                            </div>

                            <p>
                                <?php esc_html_e('Remove the Really Simple Discovery link used by some external XML-RPC publishing clients. Keep it if a connected service relies on RSD to discover the XML-RPC endpoint.', 'wp-asset-clean-up'); ?>
                            </p>

                            <?php if ($xmlRpcDisabledCompletely) { ?>
                                <div class="wpacu-html-source-dependency-note">
                                    <strong><?php esc_html_e('No additional action is required.', 'wp-asset-clean-up'); ?></strong>
                                    <?php esc_html_e('XML-RPC is disabled completely, so WordPress already removes this discovery link.', 'wp-asset-clean-up'); ?>
                                    <a data-wpacu-vertical-link-target="wpacu-setting-disable-xml-rpc" href="#wpacu-setting-disable-xml-rpc">
                                        <?php esc_html_e('Review XML-RPC settings', 'wp-asset-clean-up'); ?>
                                    </a>
                                </div>
                            <?php } ?>

                            <div class="wpacu-html-source-disclosure" data-wpacu-html-disclosure>
                                <button class="wpacu-html-source-disclosure-trigger"
                                        type="button"
                                        aria-expanded="false"
                                        aria-controls="wpacuHtmlSourceRsdMarkup"
                                        data-wpacu-html-disclosure-button>
                                    <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                                    <span class="wpacu-html-source-label-closed"><?php esc_html_e('View affected markup', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-label-open"><?php esc_html_e('Hide affected markup', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-disclosure-chevron" aria-hidden="true"></span>
                                </button>
                                <div class="wpacu-html-source-disclosure-panel" id="wpacuHtmlSourceRsdMarkup" aria-hidden="true">
                                    <div class="wpacu-html-source-disclosure-inner">
                                        <code>&lt;link rel=&quot;EditURI&quot; type=&quot;application/rsd+xml&quot; title=&quot;RSD&quot; href=&quot;https://example.com/xmlrpc.php?rsd&quot; /&gt;</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="wpacu-html-source-card<?php echo $removeRestApiLink ? ' is-removed' : ''; ?>">
                        <div class="wpacu-html-source-control">
                            <span class="wpacu-html-source-control-kicker"><?php esc_html_e('HTML output', 'wp-asset-clean-up'); ?></span>
                            <label class="wpacu-html-source-toggle"
                                   data-visual-state="<?php echo $removeRestApiLink ? 'changed' : 'default'; ?>"
                                   for="wpacu_remove_rest_api_link">
                                <input id="wpacu_remove_rest_api_link"
                                       type="checkbox"
                                       <?php checked($removeRestApiLink); ?>
                                       name="<?php echo esc_attr($settingsName); ?>[remove_rest_api_link]"
                                       value="1" />
                                <span class="wpacu-html-source-toggle__track" aria-hidden="true">
                                    <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--default"><?php esc_html_e('Present', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--changed"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-toggle__thumb"></span>
                                </span>
                                <span class="wpacu-html-source-native-label"><?php esc_html_e('Remove from HTML', 'wp-asset-clean-up'); ?></span>
                                <span class="screen-reader-text"><?php esc_html_e('Remove the REST API discovery link from the HTML source', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>

                        <div class="wpacu-html-source-copy">
                            <div class="wpacu-html-source-title-row">
                                <div class="wpacu-html-source-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M8 9 4 12l4 3"></path>
                                        <path d="m16 9 4 3-4 3"></path>
                                        <path d="m14 5-4 14"></path>
                                    </svg>
                                </div>
                                <div>
                                    <span class="wpacu-html-source-subtitle"><?php esc_html_e('HTML discovery tag only', 'wp-asset-clean-up'); ?></span>
                                    <h4><?php esc_html_e('REST API discovery link', 'wp-asset-clean-up'); ?></h4>
                                </div>
                                <span class="wpacu-html-source-risk-badge wpacu-html-source-risk-badge--neutral">
                                    <?php esc_html_e('API remains enabled', 'wp-asset-clean-up'); ?>
                                </span>
                            </div>
                            <p>
                                <?php esc_html_e('Remove the REST API discovery link from the document head. This does not disable the WordPress REST API or any of its endpoints.', 'wp-asset-clean-up'); ?>
                            </p>
                            <div class="wpacu-html-source-fact">
                                <strong><?php esc_html_e('What remains available:', 'wp-asset-clean-up'); ?></strong>
                                <?php esc_html_e('Requests to the WordPress REST API continue to work normally.', 'wp-asset-clean-up'); ?>
                            </div>

                            <div class="wpacu-html-source-disclosure" data-wpacu-html-disclosure>
                                <button class="wpacu-html-source-disclosure-trigger" type="button" aria-expanded="false" aria-controls="wpacuHtmlSourceRestMarkup" data-wpacu-html-disclosure-button>
                                    <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                                    <span class="wpacu-html-source-label-closed"><?php esc_html_e('View affected markup', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-label-open"><?php esc_html_e('Hide affected markup', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-disclosure-chevron" aria-hidden="true"></span>
                                </button>
                                <div class="wpacu-html-source-disclosure-panel" id="wpacuHtmlSourceRestMarkup" aria-hidden="true">
                                    <div class="wpacu-html-source-disclosure-inner">
                                        <code>&lt;link rel=&quot;https://api.w.org/&quot; href=&quot;https://example.com/wp-json/&quot; /&gt;</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="wpacu-html-source-card<?php echo $removeShortlink ? ' is-removed' : ''; ?>">
                        <div class="wpacu-html-source-control">
                            <span class="wpacu-html-source-control-kicker"><?php esc_html_e('HTML output', 'wp-asset-clean-up'); ?></span>
                            <label class="wpacu-html-source-toggle"
                                   data-visual-state="<?php echo $removeShortlink ? 'changed' : 'default'; ?>"
                                   for="wpacu_remove_shortlink">
                                <input id="wpacu_remove_shortlink"
                                       type="checkbox"
                                       <?php checked($removeShortlink); ?>
                                       name="<?php echo esc_attr($settingsName); ?>[remove_shortlink]"
                                       value="1" />
                                <span class="wpacu-html-source-toggle__track" aria-hidden="true">
                                    <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--default"><?php esc_html_e('Present', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--changed"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-toggle__thumb"></span>
                                </span>
                                <span class="wpacu-html-source-native-label"><?php esc_html_e('Remove from HTML', 'wp-asset-clean-up'); ?></span>
                                <span class="screen-reader-text"><?php esc_html_e('Remove WordPress shortlinks from the HTML source', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>

                        <div class="wpacu-html-source-copy">
                            <div class="wpacu-html-source-title-row">
                                <div class="wpacu-html-source-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10 13a5 5 0 0 0 7.1.1l2-2A5 5 0 0 0 12 4l-1.1 1.1"></path>
                                        <path d="M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"></path>
                                    </svg>
                                </div>
                                <div>
                                    <span class="wpacu-html-source-subtitle"><?php esc_html_e('Default short URL discovery', 'wp-asset-clean-up'); ?></span>
                                    <h4><?php esc_html_e('WordPress shortlink', 'wp-asset-clean-up'); ?></h4>
                                </div>
                            </div>
                            <p>
                                <?php esc_html_e('Remove the shortlink generated by WordPress for singular content. The normal permalink and the page itself remain unchanged.', 'wp-asset-clean-up'); ?>
                            </p>

                            <div class="wpacu-html-source-disclosure" data-wpacu-html-disclosure>
                                <button class="wpacu-html-source-disclosure-trigger" type="button" aria-expanded="false" aria-controls="wpacuHtmlSourceShortlinkMarkup" data-wpacu-html-disclosure-button>
                                    <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                                    <span class="wpacu-html-source-label-closed"><?php esc_html_e('View affected markup', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-label-open"><?php esc_html_e('Hide affected markup', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-disclosure-chevron" aria-hidden="true"></span>
                                </button>
                                <div class="wpacu-html-source-disclosure-panel" id="wpacuHtmlSourceShortlinkMarkup" aria-hidden="true">
                                    <div class="wpacu-html-source-disclosure-inner">
                                        <code>&lt;link rel=&quot;shortlink&quot; href=&quot;https://example.com/?p=123&quot; /&gt;</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="wpacu-html-source-section-heading">
                    <h3><?php esc_html_e('Generator metadata', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Generator tags identify software that contributed to the page output. Removing them is an output preference, not a security control.', 'wp-asset-clean-up'); ?></p>
                </div>

                <div class="wpacu-html-source-list">
                    <article class="wpacu-html-source-card<?php echo $removeWpVersion ? ' is-removed' : ''; ?><?php echo $removeGeneratorTag ? ' is-covered' : ''; ?>" id="wpacuHtmlSourceWpGeneratorCard">
                        <div class="wpacu-html-source-control">
                            <span class="wpacu-html-source-control-kicker"><?php esc_html_e('HTML output', 'wp-asset-clean-up'); ?></span>
                            <label class="wpacu-html-source-toggle"
                                   data-visual-state="<?php echo $removeWpVersion ? 'changed' : 'default'; ?>"
                                   for="wpacu_remove_wp_version">
                                <input id="wpacu_remove_wp_version"
                                       type="checkbox"
                                       <?php checked($removeWpVersion); ?>
                                       name="<?php echo esc_attr($settingsName); ?>[remove_wp_version]"
                                       value="1"
                                       aria-describedby="wpacuWpGeneratorCoverageNote" />
                                <span class="wpacu-html-source-toggle__track" aria-hidden="true">
                                    <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--default"><?php esc_html_e('Present', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--changed"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-toggle__thumb"></span>
                                </span>
                                <span class="wpacu-html-source-native-label"><?php esc_html_e('Remove from HTML', 'wp-asset-clean-up'); ?></span>
                                <span class="screen-reader-text"><?php esc_html_e('Remove the WordPress generator tag from the HTML source', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>

                        <div class="wpacu-html-source-copy">
                            <div class="wpacu-html-source-title-row">
                                <div class="wpacu-html-source-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 7h16"></path><path d="M4 12h12"></path><path d="M4 17h8"></path>
                                    </svg>
                                </div>
                                <div>
                                    <span class="wpacu-html-source-subtitle"><?php esc_html_e('WordPress version metadata', 'wp-asset-clean-up'); ?></span>
                                    <h4><?php esc_html_e('WordPress generator tag', 'wp-asset-clean-up'); ?></h4>
                                </div>
                                <span class="wpacu-html-source-risk-badge wpacu-html-source-risk-badge--neutral">
                                    <?php esc_html_e('Metadata only', 'wp-asset-clean-up'); ?>
                                </span>
                            </div>
                            <p>
                                <?php esc_html_e('Remove the WordPress generator tag from the HTML source. This changes optional metadata only and should not be treated as a security measure.', 'wp-asset-clean-up'); ?>
                            </p>
                            <div class="wpacu-html-source-coverage-note" id="wpacuWpGeneratorCoverageNote" aria-hidden="<?php echo $removeGeneratorTag ? 'false' : 'true'; ?>">
                                <strong><?php esc_html_e('Included by the broader rule below.', 'wp-asset-clean-up'); ?></strong>
                                <?php esc_html_e('All generator tags are currently set to be removed. This individual preference is kept in case the broader rule is disabled later.', 'wp-asset-clean-up'); ?>
                            </div>

                            <div class="wpacu-html-source-disclosure" data-wpacu-html-disclosure>
                                <button class="wpacu-html-source-disclosure-trigger" type="button" aria-expanded="false" aria-controls="wpacuHtmlSourceWpGeneratorMarkup" data-wpacu-html-disclosure-button>
                                    <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                                    <span class="wpacu-html-source-label-closed"><?php esc_html_e('View affected markup', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-label-open"><?php esc_html_e('Hide affected markup', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-disclosure-chevron" aria-hidden="true"></span>
                                </button>
                                <div class="wpacu-html-source-disclosure-panel" id="wpacuHtmlSourceWpGeneratorMarkup" aria-hidden="true">
                                    <div class="wpacu-html-source-disclosure-inner">
                                        <code>&lt;meta name=&quot;generator&quot; content=&quot;WordPress 6.x&quot; /&gt;</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="wpacu-html-source-card<?php echo $removeGeneratorTag ? ' is-removed' : ''; ?>" id="wpacuHtmlSourceAllGeneratorCard">
                        <div class="wpacu-html-source-control">
                            <span class="wpacu-html-source-control-kicker"><?php esc_html_e('HTML output', 'wp-asset-clean-up'); ?></span>
                            <label class="wpacu-html-source-toggle"
                                   data-visual-state="<?php echo $removeGeneratorTag ? 'changed' : 'default'; ?>"
                                   for="wpacu_remove_generator_tag">
                                <input id="wpacu_remove_generator_tag"
                                       type="checkbox"
                                       <?php checked($removeGeneratorTag); ?>
                                       name="<?php echo esc_attr($settingsName); ?>[remove_generator_tag]"
                                       value="1" />
                                <span class="wpacu-html-source-toggle__track" aria-hidden="true">
                                    <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--default"><?php esc_html_e('Present', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--changed"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-toggle__thumb"></span>
                                </span>
                                <span class="wpacu-html-source-native-label"><?php esc_html_e('Remove from HTML', 'wp-asset-clean-up'); ?></span>
                                <span class="screen-reader-text"><?php esc_html_e('Remove all generator tags from the HTML source', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>

                        <div class="wpacu-html-source-copy">
                            <div class="wpacu-html-source-title-row">
                                <div class="wpacu-html-source-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 6h18"></path><path d="M8 12h13"></path><path d="M13 18h8"></path><path d="M3 12h1"></path><path d="M3 18h6"></path>
                                    </svg>
                                </div>
                                <div>
                                    <span class="wpacu-html-source-subtitle"><?php esc_html_e('WordPress, themes, and plugins', 'wp-asset-clean-up'); ?></span>
                                    <h4><?php esc_html_e('All generator tags', 'wp-asset-clean-up'); ?></h4>
                                </div>
                                <span class="wpacu-html-source-risk-badge">
                                    <?php esc_html_e('Broader rule', 'wp-asset-clean-up'); ?>
                                </span>
                            </div>
                            <p>
                                <?php esc_html_e('Remove every meta tag whose name is "generator", including tags added by WordPress, themes, and plugins. This may also remove version information that can be useful during troubleshooting.', 'wp-asset-clean-up'); ?>
                            </p>
                            <div class="wpacu-html-source-fact wpacu-html-source-fact--warning">
                                <strong><?php esc_html_e('Important:', 'wp-asset-clean-up'); ?></strong>
                                <?php esc_html_e('Removing generator metadata is not a security measure and does not prevent software detection by other methods.', 'wp-asset-clean-up'); ?>
                            </div>

                            <div class="wpacu-html-source-disclosure" data-wpacu-html-disclosure>
                                <button class="wpacu-html-source-disclosure-trigger" type="button" aria-expanded="false" aria-controls="wpacuHtmlSourceAllGeneratorMarkup" data-wpacu-html-disclosure-button>
                                    <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                                    <span class="wpacu-html-source-label-closed"><?php esc_html_e('View affected markup', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-label-open"><?php esc_html_e('Hide affected markup', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-disclosure-chevron" aria-hidden="true"></span>
                                </button>
                                <div class="wpacu-html-source-disclosure-panel" id="wpacuHtmlSourceAllGeneratorMarkup" aria-hidden="true">
                                    <div class="wpacu-html-source-disclosure-inner">
                                        <code>&lt;meta name=&quot;generator&quot; content=&quot;Plugin or theme name 1.2.3&quot; /&gt;</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <?php if ($showLegacyGroup) { ?>
                    <div class="wpacu-html-source-section-heading">
                        <h3><?php esc_html_e('Legacy WordPress output', 'wp-asset-clean-up'); ?></h3>
                        <p><?php esc_html_e('These controls are shown only while the current WordPress version can still generate the related tags.', 'wp-asset-clean-up'); ?></p>
                    </div>

                    <div class="wpacu-html-source-list">
                        <?php if ($showPostsRelLinks) { ?>
                            <article class="wpacu-html-source-card<?php echo $removePostsRelLinks ? ' is-removed' : ''; ?>">
                                <div class="wpacu-html-source-control">
                                    <span class="wpacu-html-source-control-kicker"><?php esc_html_e('HTML output', 'wp-asset-clean-up'); ?></span>
                                    <label class="wpacu-html-source-toggle" data-visual-state="<?php echo $removePostsRelLinks ? 'changed' : 'default'; ?>" for="wpacu_remove_posts_rel_links">
                                        <input id="wpacu_remove_posts_rel_links" type="checkbox" <?php checked($removePostsRelLinks); ?> name="<?php echo esc_attr($settingsName); ?>[remove_posts_rel_links]" value="1" />
                                        <span class="wpacu-html-source-toggle__track" aria-hidden="true">
                                            <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--default"><?php esc_html_e('Present', 'wp-asset-clean-up'); ?></span>
                                            <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--changed"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                                            <span class="wpacu-html-source-toggle__thumb"></span>
                                        </span>
                                        <span class="wpacu-html-source-native-label"><?php esc_html_e('Remove from HTML', 'wp-asset-clean-up'); ?></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Remove post relational links from the HTML source', 'wp-asset-clean-up'); ?></span>
                                    </label>
                                </div>
                                <div class="wpacu-html-source-copy">
                                    <div class="wpacu-html-source-title-row">
                                        <div class="wpacu-html-source-icon" aria-hidden="true"><span class="dashicons dashicons-admin-links"></span></div>
                                        <div><span class="wpacu-html-source-subtitle"><?php esc_html_e('Adjacent post navigation metadata', 'wp-asset-clean-up'); ?></span><h4><?php esc_html_e('Post relational links', 'wp-asset-clean-up'); ?></h4></div>
                                        <span class="wpacu-html-source-risk-badge wpacu-html-source-risk-badge--neutral"><?php esc_html_e('Legacy', 'wp-asset-clean-up'); ?></span>
                                    </div>
                                    <p><?php esc_html_e('Remove the previous and next relational links generated for adjacent posts on singular post pages.', 'wp-asset-clean-up'); ?></p>
                                </div>
                            </article>
                        <?php } else { ?>
                            <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[remove_posts_rel_links]" value="<?php echo $removePostsRelLinks ? '1' : '0'; ?>" />
                        <?php } ?>

                        <?php if ($showWlwLink) { ?>
                            <article class="wpacu-html-source-card<?php echo $removeWlwLink ? ' is-removed' : ''; ?>">
                                <div class="wpacu-html-source-control">
                                    <span class="wpacu-html-source-control-kicker"><?php esc_html_e('HTML output', 'wp-asset-clean-up'); ?></span>
                                    <label class="wpacu-html-source-toggle" data-visual-state="<?php echo $removeWlwLink ? 'changed' : 'default'; ?>" for="wpacu_remove_wlw_link">
                                        <input id="wpacu_remove_wlw_link" type="checkbox" <?php checked($removeWlwLink); ?> name="<?php echo esc_attr($settingsName); ?>[remove_wlw_link]" value="1" />
                                        <span class="wpacu-html-source-toggle__track" aria-hidden="true">
                                            <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--default"><?php esc_html_e('Present', 'wp-asset-clean-up'); ?></span>
                                            <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--changed"><?php esc_html_e('Removed', 'wp-asset-clean-up'); ?></span>
                                            <span class="wpacu-html-source-toggle__thumb"></span>
                                        </span>
                                        <span class="wpacu-html-source-native-label"><?php esc_html_e('Remove from HTML', 'wp-asset-clean-up'); ?></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Remove the Windows Live Writer manifest link from the HTML source', 'wp-asset-clean-up'); ?></span>
                                    </label>
                                </div>
                                <div class="wpacu-html-source-copy">
                                    <div class="wpacu-html-source-title-row">
                                        <div class="wpacu-html-source-icon" aria-hidden="true"><span class="dashicons dashicons-edit-page"></span></div>
                                        <div><span class="wpacu-html-source-subtitle"><?php esc_html_e('Legacy remote-editing discovery', 'wp-asset-clean-up'); ?></span><h4><?php esc_html_e('Windows Live Writer manifest', 'wp-asset-clean-up'); ?></h4></div>
                                        <span class="wpacu-html-source-risk-badge wpacu-html-source-risk-badge--neutral"><?php esc_html_e('Legacy', 'wp-asset-clean-up'); ?></span>
                                    </div>
                                    <p><?php esc_html_e('Remove the manifest discovery link used by Windows Live Writer and compatible legacy publishing clients.', 'wp-asset-clean-up'); ?></p>
                                </div>
                            </article>
                        <?php } else { ?>
                            <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[remove_wlw_link]" value="<?php echo $removeWlwLink ? '1' : '0'; ?>" />
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[remove_posts_rel_links]" value="<?php echo $removePostsRelLinks ? '1' : '0'; ?>" />
                    <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[remove_wlw_link]" value="<?php echo $removeWlwLink ? '1' : '0'; ?>" />
                <?php } ?>

                <div class="wpacu-html-source-section-heading">
                    <h3><?php esc_html_e('HTML comments', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('This is a broader source transformation. Review the limitations and preserve any comments your workflow relies on.', 'wp-asset-clean-up'); ?></p>
                </div>

                <div class="wpacu-html-source-list">
                    <article class="wpacu-html-source-card wpacu-html-source-card--comments<?php echo $removeHtmlComments ? ' is-removed' : ''; ?>" id="wpacuHtmlSourceCommentsCard">
                        <div class="wpacu-html-source-control">
                            <span class="wpacu-html-source-control-kicker"><?php esc_html_e('Generated source', 'wp-asset-clean-up'); ?></span>
                            <label class="wpacu-html-source-toggle"
                                   data-visual-state="<?php echo $removeHtmlComments ? 'changed' : 'default'; ?>"
                                   for="wpacu_remove_html_comments">
                                <input id="wpacu_remove_html_comments"
                                       type="checkbox"
                                       <?php checked($removeHtmlComments); ?>
                                       name="<?php echo esc_attr($settingsName); ?>[remove_html_comments]"
                                       value="1" />
                                <span class="wpacu-html-source-toggle__track" aria-hidden="true">
                                    <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--default"><?php esc_html_e('Comments kept', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-toggle__text wpacu-html-source-toggle__text--changed"><?php esc_html_e('Comments removed', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-toggle__thumb"></span>
                                </span>
                                <span class="wpacu-html-source-native-label"><?php esc_html_e('Strip HTML comments', 'wp-asset-clean-up'); ?></span>
                                <span class="screen-reader-text"><?php esc_html_e('Strip HTML comments from the generated page source', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>

                        <div class="wpacu-html-source-copy">
                            <div class="wpacu-html-source-title-row">
                                <div class="wpacu-html-source-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path>
                                        <path d="M8 9h8"></path><path d="M8 13h5"></path>
                                    </svg>
                                </div>
                                <div>
                                    <span class="wpacu-html-source-subtitle"><?php esc_html_e('Generated page-source comments', 'wp-asset-clean-up'); ?></span>
                                    <h4><?php esc_html_e('Strip HTML comments', 'wp-asset-clean-up'); ?></h4>
                                </div>
                                <span class="wpacu-html-source-risk-badge">
                                    <?php esc_html_e('Test carefully', 'wp-asset-clean-up'); ?>
                                </span>
                            </div>
                            <p>
                                <?php esc_html_e('Remove HTML comments from the final generated source while preserving Internet Explorer conditional comments and any comments that match an exception pattern below.', 'wp-asset-clean-up'); ?>
                            </p>

                            <div class="wpacu-html-source-comments-config<?php echo $removeHtmlComments ? ' is-open' : ''; ?>" data-wpacu-html-disclosure id="wpacuHtmlSourceCommentsConfig">
                                <button class="wpacu-html-source-comments-trigger"
                                        type="button"
                                        aria-expanded="<?php echo $removeHtmlComments ? 'true' : 'false'; ?>"
                                        aria-controls="wpacuHtmlSourceCommentsPanel"
                                        data-wpacu-html-disclosure-button>
                                    <span class="dashicons dashicons-filter" aria-hidden="true"></span>
                                    <span class="wpacu-html-source-label-closed"><?php esc_html_e('Configure exceptions and review limitations', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-label-open"><?php esc_html_e('Hide exception settings', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-html-source-disclosure-chevron" aria-hidden="true"></span>
                                </button>

                                <div class="wpacu-html-source-disclosure-panel" id="wpacuHtmlSourceCommentsPanel" aria-hidden="<?php echo $removeHtmlComments ? 'false' : 'true'; ?>">
                                    <div class="wpacu-html-source-disclosure-inner wpacu-html-source-comments-panel-inner">
                                        <label for="wpacu_remove_html_comments_exceptions">
                                            <?php esc_html_e('Comments to preserve', 'wp-asset-clean-up'); ?>
                                        </label>
                                        <p class="wpacu-html-source-field-help">
                                            <?php esc_html_e('Add one case-insensitive text pattern per line. A comment containing one of these patterns will remain in the HTML source.', 'wp-asset-clean-up'); ?>
                                        </p>
                                        <textarea id="wpacu_remove_html_comments_exceptions"
                                                  name="<?php echo esc_attr($settingsName); ?>[remove_html_comments_exceptions]"
                                                  rows="5"
                                                  placeholder="Example:&#10;license&#10;keep-this-comment&#10;analytics-marker"><?php echo esc_textarea($htmlCommentExceptions); ?></textarea>

                                        <div class="wpacu-html-source-limitation-note">
                                            <div class="wpacu-html-source-limitation-icon" aria-hidden="true">
                                                <span class="dashicons dashicons-warning"></span>
                                            </div>
                                            <div>
                                                <strong><?php esc_html_e('Known limitation', 'wp-asset-clean-up'); ?></strong>
                                                <p>
                                                    <?php esc_html_e('A caching layer or another output-processing component can add comments after Asset CleanUp has processed the page. Those comments may remain in the final cached source.', 'wp-asset-clean-up'); ?>
                                                    <a target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=116">
                                                        <?php esc_html_e('Read the HTML comments documentation', 'wp-asset-clean-up'); ?>
                                                    </a>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <aside class="wpacu-html-source-footer-note">
                    <div class="wpacu-html-source-footer-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 1 1-2.64-6.36"></path>
                            <path d="M21 3v6h-6"></path>
                        </svg>
                    </div>
                    <div>
                        <strong><?php esc_html_e('Clear relevant caches before checking the final source', 'wp-asset-clean-up'); ?></strong>
                        <p><?php esc_html_e('After saving, clear page, plugin, server, and CDN caches as applicable. Then inspect the logged-out page source to confirm that the expected markup was removed and the site still works normally.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </aside>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    'use strict';

    if (typeof window.wpacuHtmlSourceCleanupUseEffects !== 'boolean') {
        window.wpacuHtmlSourceCleanupUseEffects = true;
    }

    var root = document.getElementById('wpacu-html-source-cleanup-settings');

    if (! root || root.getAttribute('data-wpacu-initialized') === '1') {
        return;
    }

    root.setAttribute('data-wpacu-initialized', '1');

    var toggles = root.querySelectorAll('.wpacu-html-source-toggle');
    var instances = [];
    var measurementNode = null;
    var resizeTimer = null;
    var reduceMotionQuery = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;
    var effectsEnabled = true;
    var fadeOutDelay = 65;
    var fadeInDelay = 18;

    function readPixelCustomProperty(name, fallback) {
        var value = window.getComputedStyle(root).getPropertyValue(name);
        var parsed = parseFloat(value);

        return isNaN(parsed) ? fallback : parsed;
    }

    function getMeasurementNode() {
        if (measurementNode) {
            return measurementNode;
        }

        measurementNode = document.createElement('span');
        measurementNode.setAttribute('aria-hidden', 'true');
        measurementNode.style.position = 'absolute';
        measurementNode.style.left = '-100000px';
        measurementNode.style.top = '-100000px';
        measurementNode.style.width = 'auto';
        measurementNode.style.maxWidth = 'none';
        measurementNode.style.margin = '0';
        measurementNode.style.padding = '0';
        measurementNode.style.border = '0';
        measurementNode.style.visibility = 'hidden';
        measurementNode.style.pointerEvents = 'none';
        measurementNode.style.whiteSpace = 'nowrap';
        measurementNode.style.boxSizing = 'content-box';
        document.body.appendChild(measurementNode);

        return measurementNode;
    }

    function measureLabel(label) {
        var node = getMeasurementNode();
        var style = window.getComputedStyle(label);

        node.style.fontFamily = style.fontFamily;
        node.style.fontSize = style.fontSize;
        node.style.fontStyle = style.fontStyle;
        node.style.fontWeight = style.fontWeight;
        node.style.letterSpacing = style.letterSpacing;
        node.style.textTransform = style.textTransform;
        node.style.lineHeight = style.lineHeight;
        node.textContent = label.textContent || '';

        return Math.ceil(node.getBoundingClientRect().width);
    }

    function getCurrentState(input) {
        return input.checked ? 'changed' : 'default';
    }

    function getStateLabel(instance, state) {
        return state === 'changed'
            ? instance.changedLabel
            : instance.defaultLabel;
    }

    function getTargetWidth(instance, state) {
        var minWidth = readPixelCustomProperty('--wpacu-html-toggle-min-width', 110);
        var maxWidth = readPixelCustomProperty('--wpacu-html-toggle-max-width', 330);
        var horizontalSpace = readPixelCustomProperty('--wpacu-html-toggle-horizontal-space', 66);
        var labelWidth = measureLabel(getStateLabel(instance, state));

        return Math.min(maxWidth, Math.max(minWidth, labelWidth + horizontalSpace));
    }

    function setTrackWidth(instance, state, immediate) {
        var targetWidth = getTargetWidth(instance, state);

        if (immediate) {
            instance.track.classList.add('is-measuring');
        }

        instance.track.style.setProperty(
            '--wpacu-html-toggle-current-width',
            targetWidth + 'px'
        );

        if (immediate) {
            instance.track.offsetWidth;
            instance.track.classList.remove('is-measuring');
        }
    }

    function clearTransition(instance) {
        window.clearTimeout(instance.fadeTimer);
        window.clearTimeout(instance.revealTimer);
        instance.fadeTimer = null;
        instance.revealTimer = null;
    }

    function updateCommentsConfiguration(input) {
        var wrapper = document.getElementById('wpacuHtmlSourceCommentsConfig');

        if (! wrapper || input.id !== 'wpacu_remove_html_comments') {
            return;
        }

        setDisclosure(wrapper, input.checked);
    }

    function applyImmediateState(instance) {
        var state = getCurrentState(instance.input);

        clearTransition(instance);
        instance.sequence += 1;
        instance.card.classList.toggle('is-removed', state === 'changed');
        instance.toggle.classList.remove('is-fading');
        instance.toggle.setAttribute('data-visual-state', state);
        setTrackWidth(instance, state, true);
        updateCommentsConfiguration(instance.input);
    }

    function applyAnimatedState(instance) {
        var state = getCurrentState(instance.input);
        var sequence;

        instance.card.classList.toggle('is-removed', state === 'changed');
        updateCommentsConfiguration(instance.input);

        if (! effectsEnabled) {
            applyImmediateState(instance);
            return;
        }

        clearTransition(instance);
        instance.sequence += 1;
        sequence = instance.sequence;
        instance.toggle.classList.add('is-fading');

        instance.fadeTimer = window.setTimeout(function () {
            if (sequence !== instance.sequence) {
                return;
            }

            instance.toggle.setAttribute('data-visual-state', state);
            setTrackWidth(instance, state, false);

            instance.revealTimer = window.setTimeout(function () {
                if (sequence === instance.sequence) {
                    instance.toggle.classList.remove('is-fading');
                }
            }, fadeInDelay);
        }, fadeOutDelay);
    }

    function updateGeneratorRelationship() {
        var allGeneratorInput = document.getElementById('wpacu_remove_generator_tag');
        var wpGeneratorCard = document.getElementById('wpacuHtmlSourceWpGeneratorCard');
        var coverageNote = document.getElementById('wpacuWpGeneratorCoverageNote');
        var covered;

        if (! allGeneratorInput || ! wpGeneratorCard || ! coverageNote) {
            return;
        }

        covered = allGeneratorInput.checked;
        wpGeneratorCard.classList.toggle('is-covered', covered);
        coverageNote.setAttribute('aria-hidden', covered ? 'false' : 'true');
    }

    function setDisclosure(wrapper, open) {
        var button = wrapper.querySelector('[data-wpacu-html-disclosure-button]');
        var panel;

        if (! button) {
            return;
        }

        panel = document.getElementById(button.getAttribute('aria-controls'));

        wrapper.classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (panel) {
            panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
    }

    function initialiseDisclosures() {
        var wrappers = root.querySelectorAll('[data-wpacu-html-disclosure]');
        var i;

        for (i = 0; i < wrappers.length; i++) {
            (function (wrapper) {
                var button = wrapper.querySelector('[data-wpacu-html-disclosure-button]');

                if (! button) {
                    return;
                }

                button.addEventListener('click', function () {
                    setDisclosure(
                        wrapper,
                        button.getAttribute('aria-expanded') !== 'true'
                    );
                });
            }(wrappers[i]));
        }
    }

    function updateEffectsSetting() {
        effectsEnabled = window.wpacuHtmlSourceCleanupUseEffects !== false
            && ! (reduceMotionQuery && reduceMotionQuery.matches);

        root.classList.toggle('wpacu-html-source-no-effects', ! effectsEnabled);
    }

    var i;

    for (i = 0; i < toggles.length; i++) {
        (function (toggle) {
            var input = toggle.querySelector('input[type="checkbox"]');
            var track = toggle.querySelector('.wpacu-html-source-toggle__track');
            var defaultLabel = toggle.querySelector('.wpacu-html-source-toggle__text--default');
            var changedLabel = toggle.querySelector('.wpacu-html-source-toggle__text--changed');
            var card = toggle.closest('.wpacu-html-source-card');
            var instance;

            if (! input || ! track || ! defaultLabel || ! changedLabel || ! card) {
                return;
            }

            instance = {
                input: input,
                card: card,
                toggle: toggle,
                track: track,
                defaultLabel: defaultLabel,
                changedLabel: changedLabel,
                sequence: 0,
                fadeTimer: null,
                revealTimer: null
            };

            instances.push(instance);

            input.addEventListener('change', function () {
                applyAnimatedState(instance);
                updateGeneratorRelationship();
            });
        }(toggles[i]));
    }

    function refreshAllToggleWidths() {
        var j;

        for (j = 0; j < instances.length; j++) {
            applyImmediateState(instances[j]);
        }

        updateGeneratorRelationship();
    }

    updateEffectsSetting();
    initialiseDisclosures();
    refreshAllToggleWidths();

    window.wpacuHtmlSourceCleanupRefreshToggleWidths = refreshAllToggleWidths;
    window.wpacuHtmlSourceCleanupSetEffects = function (enabled) {
        window.wpacuHtmlSourceCleanupUseEffects = !! enabled;
        updateEffectsSetting();
        refreshAllToggleWidths();
    };

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(refreshAllToggleWidths);
    }

    window.addEventListener('resize', function () {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(refreshAllToggleWidths, 100);
    });

    if (reduceMotionQuery) {
        if (typeof reduceMotionQuery.addEventListener === 'function') {
            reduceMotionQuery.addEventListener('change', function () {
                updateEffectsSetting();
                refreshAllToggleWidths();
            });
        } else if (typeof reduceMotionQuery.addListener === 'function') {
            reduceMotionQuery.addListener(function () {
                updateEffectsSetting();
                refreshAllToggleWidths();
            });
        }
    }
}());
</script>
