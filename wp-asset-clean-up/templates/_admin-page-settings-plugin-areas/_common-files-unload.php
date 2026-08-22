<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Tips;

if (! isset($data)) {
    exit;
}

$tabIdArea       = 'wpacu-setting-common-files-unload';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea)
    ? 'style="display: table-cell;"'
    : '';

$settingsName      = WPACU_PLUGIN_ID . '_settings';
$globalUnloadsName = WPACU_PLUGIN_ID . '_global_unloads';

$disableEmojis             = ! empty($data['disable_emojis']);
$disableOembed             = ! empty($data['disable_oembed']);
$disableDashiconsForGuests = ! empty($data['disable_dashicons_for_guests']);
$disableBlockLibrary       = ! empty($data['disable_wp_block_library']);
$disableJqueryMigrate      = ! empty($data['disable_jquery_migrate']);
$disableCommentReply       = ! empty($data['disable_comment_reply']);
$blockLibraryExtraTip      = Tips::ceGutenbergCssLibraryBlockTip();
?>
<div id="<?php echo esc_attr($tabIdArea); ?>"
     class="wpacu-settings-tab-content"
     <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <main id="wpacu-common-unloads-settings" class="wpacu-common-unloads-page">
        <section class="wpacu-common-unloads-panel" aria-labelledby="wpacuCommonUnloadsTitle">
            <header class="wpacu-common-unloads-header">
                <div>
                    <div class="wpacu-common-unloads-eyebrow">
                        <?php esc_html_e('Site-wide optimization', 'wp-asset-clean-up'); ?>
                    </div>
                    <h2 id="wpacuCommonUnloadsTitle">
                        <?php esc_html_e('Remove common WordPress assets you do not need', 'wp-asset-clean-up'); ?>
                    </h2>
                    <p>
                        <?php esc_html_e('These controls create site-wide rules for selected WordPress features, stylesheets, and scripts. Keep the default state unless you have confirmed that the site does not rely on the item.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>

                <div class="wpacu-common-unloads-header-badge">
                    <?php esc_html_e('Applies site-wide', 'wp-asset-clean-up'); ?>
                </div>
            </header>

            <div class="wpacu-common-unloads-body">
                <section class="wpacu-common-unloads-intro" aria-labelledby="wpacuCommonUnloadsIntroTitle">
                    <div class="wpacu-common-unloads-intro-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>
                            <path d="M12 8v4"></path>
                            <path d="M12 16h.01"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 id="wpacuCommonUnloadsIntroTitle">
                            <?php esc_html_e('Test every change before publishing it', 'wp-asset-clean-up'); ?>
                        </h3>
                        <p>
                            <?php esc_html_e('A site-wide rule can affect every public page where the feature or asset would otherwise load. Use Test Mode, change one option at a time, and check representative pages, menus, forms, product flows, and logged-out views.', 'wp-asset-clean-up'); ?>
                        </p>
                    </div>
                </section>

                <div class="wpacu-common-unloads-section-heading">
                    <h3><?php esc_html_e('WordPress features', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('These controls disable built-in features together with their related front-end output.', 'wp-asset-clean-up'); ?></p>
                </div>

                <div class="wpacu-common-unloads-list">
                    <article class="wpacu-common-unload-card<?php echo $disableEmojis ? ' is-unloaded' : ''; ?>">
                        <div class="wpacu-common-unload-control">
                            <span class="wpacu-common-unload-control-kicker"><?php esc_html_e('Current behavior', 'wp-asset-clean-up'); ?></span>
                            <label class="wpacu-common-unload-toggle" data-visual-state="<?php echo $disableEmojis ? 'changed' : 'default'; ?>" for="wpacu_disable_emojis">
                                <input id="wpacu_disable_emojis" type="checkbox" <?php checked($disableEmojis); ?> name="<?php echo esc_attr($settingsName); ?>[disable_emojis]" value="1" />
                                <span class="wpacu-common-unload-toggle__track" aria-hidden="true">
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--default"><?php esc_html_e('Enabled', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--changed"><?php esc_html_e('Disabled', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__thumb"></span>
                                </span>
                                <span class="wpacu-common-unload-native-label"><?php esc_html_e('Disable site-wide', 'wp-asset-clean-up'); ?></span>
                                <span class="screen-reader-text"><?php esc_html_e('Disable WordPress Emojis site-wide', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>
                        <div class="wpacu-common-unload-copy">
                            <div class="wpacu-common-unload-title-row">
                                <div class="wpacu-common-unload-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><path d="M9 9h.01"></path><path d="M15 9h.01"></path></svg></div>
                                <div><span class="wpacu-common-unload-subtitle"><?php esc_html_e('Emoji detection and compatibility', 'wp-asset-clean-up'); ?></span><h4><?php esc_html_e('WordPress Emojis', 'wp-asset-clean-up'); ?></h4></div>
                            </div>
                            <p><?php esc_html_e('Disable WordPress\'s additional emoji detection and compatibility output. Browsers can still render the emoji characters they support natively.', 'wp-asset-clean-up'); ?></p>
                            <div class="wpacu-common-unload-meta"><strong><?php esc_html_e('Affected output:', 'wp-asset-clean-up'); ?></strong><code>wp-emoji-release.min.js</code><span><?php esc_html_e('plus related styles and conversion filters', 'wp-asset-clean-up'); ?></span></div>
                        </div>
                    </article>

                    <article class="wpacu-common-unload-card<?php echo $disableOembed ? ' is-unloaded' : ''; ?>">
                        <div class="wpacu-common-unload-control">
                            <span class="wpacu-common-unload-control-kicker"><?php esc_html_e('Current behavior', 'wp-asset-clean-up'); ?></span>
                            <label class="wpacu-common-unload-toggle" data-visual-state="<?php echo $disableOembed ? 'changed' : 'default'; ?>" for="wpacu_disable_wp_embed">
                                <input id="wpacu_disable_wp_embed" type="checkbox" <?php checked($disableOembed); ?> name="<?php echo esc_attr($settingsName); ?>[disable_oembed]" value="1" />
                                <span class="wpacu-common-unload-toggle__track" aria-hidden="true">
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--default"><?php esc_html_e('Enabled', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--changed"><?php esc_html_e('Disabled', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__thumb"></span>
                                </span>
                                <span class="wpacu-common-unload-native-label"><?php esc_html_e('Disable site-wide', 'wp-asset-clean-up'); ?></span>
                                <span class="screen-reader-text"><?php esc_html_e('Disable WordPress oEmbed site-wide', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>
                        <div class="wpacu-common-unload-copy">
                            <div class="wpacu-common-unload-title-row">
                                <div class="wpacu-common-unload-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"></path><path d="M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"></path></svg></div>
                                <div><span class="wpacu-common-unload-subtitle"><?php esc_html_e('Embeds and discovery', 'wp-asset-clean-up'); ?></span><h4><?php esc_html_e('WordPress oEmbed', 'wp-asset-clean-up'); ?></h4></div>
                            </div>
                            <p><?php esc_html_e('Disable WordPress oEmbed discovery, its REST endpoint, the front-end embed script, embed rewrite rules, and the editor integration.', 'wp-asset-clean-up'); ?></p>
                            <div class="wpacu-common-unload-advice"><strong><?php esc_html_e('Keep it enabled if:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('you embed videos or posts by pasting URLs, or you allow other sites to embed your WordPress posts.', 'wp-asset-clean-up'); ?></div>
                            <div class="wpacu-common-unload-meta"><strong><?php esc_html_e('Affected output:', 'wp-asset-clean-up'); ?></strong><code>wp-embed.min.js</code><a href="https://wordpress.org/documentation/article/embeds/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('WordPress embed documentation', 'wp-asset-clean-up'); ?></a></div>
                        </div>
                    </article>

                    <article class="wpacu-common-unload-card<?php echo $disableCommentReply ? ' is-unloaded' : ''; ?>">
                        <div class="wpacu-common-unload-control">
                            <span class="wpacu-common-unload-control-kicker"><?php esc_html_e('Current behavior', 'wp-asset-clean-up'); ?></span>
                            <label class="wpacu-common-unload-toggle" data-visual-state="<?php echo $disableCommentReply ? 'changed' : 'default'; ?>" for="wpacu_disable_comment_reply">
                                <input id="wpacu_disable_comment_reply" type="checkbox" <?php checked($disableCommentReply); ?> name="<?php echo esc_attr($globalUnloadsName); ?>[disable_comment_reply]" value="1" />
                                <span class="wpacu-common-unload-toggle__track" aria-hidden="true">
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--default"><?php esc_html_e('Loaded', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--changed"><?php esc_html_e('Unloaded', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__thumb"></span>
                                </span>
                                <span class="wpacu-common-unload-native-label"><?php esc_html_e('Unload site-wide', 'wp-asset-clean-up'); ?></span>
                                <span class="screen-reader-text"><?php esc_html_e('Unload the WordPress Comment Reply script site-wide', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>
                        <div class="wpacu-common-unload-copy">
                            <div class="wpacu-common-unload-title-row">
                                <div class="wpacu-common-unload-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path></svg></div>
                                <div><span class="wpacu-common-unload-subtitle"><?php esc_html_e('Native threaded comments', 'wp-asset-clean-up'); ?></span><h4><?php esc_html_e('Comment Reply', 'wp-asset-clean-up'); ?></h4></div>
                            </div>
                            <p><?php esc_html_e('Unload the script that powers native threaded comment replies. This is a common candidate when comments are disabled or a third-party commenting platform is used.', 'wp-asset-clean-up'); ?></p>
                            <div class="wpacu-common-unload-meta"><strong><?php esc_html_e('Affected asset:', 'wp-asset-clean-up'); ?></strong><code>comment-reply(.min).js</code></div>
                        </div>
                    </article>
                </div>

                <div class="wpacu-common-unloads-section-heading wpacu-common-unloads-section-heading--dependencies">
                    <h3><?php esc_html_e('Front-end assets and compatibility', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Themes and plugins may depend on these files. Test representative logged-out pages and interactive flows carefully.', 'wp-asset-clean-up'); ?></p>
                </div>

                <div class="wpacu-common-unloads-list">
                    <article class="wpacu-common-unload-card wpacu-common-unload-card--caution<?php echo $disableDashiconsForGuests ? ' is-unloaded' : ''; ?>">
                        <div class="wpacu-common-unload-control">
                            <span class="wpacu-common-unload-control-kicker"><?php esc_html_e('Current behavior', 'wp-asset-clean-up'); ?></span>
                            <label class="wpacu-common-unload-toggle" data-visual-state="<?php echo $disableDashiconsForGuests ? 'changed' : 'default'; ?>" for="wpacu_disable_dashicons_for_guests">
                                <input id="wpacu_disable_dashicons_for_guests" type="checkbox" <?php checked($disableDashiconsForGuests); ?> name="<?php echo esc_attr($globalUnloadsName); ?>[disable_dashicons_for_guests]" value="1" />
                                <span class="wpacu-common-unload-toggle__track" aria-hidden="true">
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--default"><?php esc_html_e('Loaded for guests', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--changed"><?php esc_html_e('Unloaded for guests', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__thumb"></span>
                                </span>
                                <span class="wpacu-common-unload-native-label"><?php esc_html_e('Unload for logged-out visitors', 'wp-asset-clean-up'); ?></span>
                                <span class="screen-reader-text"><?php esc_html_e('Unload Dashicons for logged-out visitors', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>
                        <div class="wpacu-common-unload-copy">
                            <div class="wpacu-common-unload-title-row">
                                <div class="wpacu-common-unload-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg></div>
                                <div><span class="wpacu-common-unload-subtitle"><?php esc_html_e('WordPress icon font', 'wp-asset-clean-up'); ?></span><h4><?php esc_html_e('Dashicons for logged-out visitors', 'wp-asset-clean-up'); ?></h4></div>
                                <span class="wpacu-common-unload-risk-badge"><?php esc_html_e('Check front-end icons', 'wp-asset-clean-up'); ?></span>
                            </div>
                            <p><?php esc_html_e('Unload the Dashicons stylesheet for logged-out visitors only. Logged-in users with the WordPress admin toolbar continue to receive it.', 'wp-asset-clean-up'); ?></p>
                            <div class="wpacu-common-unload-advice wpacu-common-unload-advice--warning"><strong><?php esc_html_e('Test carefully:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('some themes and plugins use Dashicons in public menus, widgets, account areas, or custom controls.', 'wp-asset-clean-up'); ?></div>
                            <div class="wpacu-common-unload-meta"><strong><?php esc_html_e('Affected asset:', 'wp-asset-clean-up'); ?></strong><code>dashicons.min.css</code><span><?php esc_html_e('and the Dashicons font', 'wp-asset-clean-up'); ?></span></div>
                        </div>
                    </article>

                    <article class="wpacu-common-unload-card wpacu-common-unload-card--caution<?php echo $disableBlockLibrary ? ' is-unloaded' : ''; ?>">
                        <div class="wpacu-common-unload-control">
                            <span class="wpacu-common-unload-control-kicker"><?php esc_html_e('Current behavior', 'wp-asset-clean-up'); ?></span>
                            <label class="wpacu-common-unload-toggle" data-visual-state="<?php echo $disableBlockLibrary ? 'changed' : 'default'; ?>" for="wpacu_disable_wp_block_library">
                                <input id="wpacu_disable_wp_block_library" type="checkbox" <?php checked($disableBlockLibrary); ?> name="<?php echo esc_attr($globalUnloadsName); ?>[disable_wp_block_library]" value="1" />
                                <span class="wpacu-common-unload-toggle__track" aria-hidden="true">
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--default"><?php esc_html_e('Loaded', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--changed"><?php esc_html_e('Unloaded', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__thumb"></span>
                                </span>
                                <span class="wpacu-common-unload-native-label"><?php esc_html_e('Unload site-wide', 'wp-asset-clean-up'); ?></span>
                                <span class="screen-reader-text"><?php esc_html_e('Unload the WordPress Block Library stylesheet site-wide', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>
                        <div class="wpacu-common-unload-copy">
                            <div class="wpacu-common-unload-title-row">
                                <div class="wpacu-common-unload-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="8" height="8" rx="1"></rect><rect x="13" y="3" width="8" height="5" rx="1"></rect><rect x="13" y="10" width="8" height="11" rx="1"></rect><rect x="3" y="13" width="8" height="8" rx="1"></rect></svg></div>
                                <div><span class="wpacu-common-unload-subtitle"><?php esc_html_e('Core block styles', 'wp-asset-clean-up'); ?></span><h4><?php esc_html_e('WordPress Block Library CSS', 'wp-asset-clean-up'); ?></h4></div>
                                <span class="wpacu-common-unload-risk-badge"><?php esc_html_e('Test carefully', 'wp-asset-clean-up'); ?></span>
                            </div>
                            <p><?php esc_html_e('Unload the core WordPress block library stylesheet across the site. Using the Classic Editor alone does not guarantee that block styles are unused; templates, widgets, WooCommerce, or plugins may still need them.', 'wp-asset-clean-up'); ?></p>
                            <?php if ($blockLibraryExtraTip) { ?><div class="wpacu-common-unload-advice wpacu-common-unload-advice--warning"><strong><?php esc_html_e('Extra tip:', 'wp-asset-clean-up'); ?></strong> <?php echo wp_kses_post($blockLibraryExtraTip); ?></div><?php } ?>
                            <div class="wpacu-common-unload-meta"><strong><?php esc_html_e('Affected asset:', 'wp-asset-clean-up'); ?></strong><code>wp-block-library</code><a href="https://www.assetcleanup.com/docs/?p=713" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Check usage with Chrome DevTools Coverage', 'wp-asset-clean-up'); ?></a></div>
                        </div>
                    </article>

                    <article class="wpacu-common-unload-card wpacu-common-unload-card--caution<?php echo $disableJqueryMigrate ? ' is-unloaded' : ''; ?>">
                        <div class="wpacu-common-unload-control">
                            <span class="wpacu-common-unload-control-kicker"><?php esc_html_e('Current behavior', 'wp-asset-clean-up'); ?></span>
                            <label class="wpacu-common-unload-toggle" data-visual-state="<?php echo $disableJqueryMigrate ? 'changed' : 'default'; ?>" for="wpacu_disable_jquery_migrate">
                                <input id="wpacu_disable_jquery_migrate" type="checkbox" <?php checked($disableJqueryMigrate); ?> name="<?php echo esc_attr($globalUnloadsName); ?>[disable_jquery_migrate]" value="1" />
                                <span class="wpacu-common-unload-toggle__track" aria-hidden="true">
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--default"><?php esc_html_e('Loaded', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__text wpacu-common-unload-toggle__text--changed"><?php esc_html_e('Unloaded', 'wp-asset-clean-up'); ?></span>
                                    <span class="wpacu-common-unload-toggle__thumb"></span>
                                </span>
                                <span class="wpacu-common-unload-native-label"><?php esc_html_e('Unload site-wide', 'wp-asset-clean-up'); ?></span>
                                <span class="screen-reader-text"><?php esc_html_e('Unload jQuery Migrate site-wide', 'wp-asset-clean-up'); ?></span>
                            </label>
                        </div>
                        <div class="wpacu-common-unload-copy">
                            <div class="wpacu-common-unload-title-row">
                                <div class="wpacu-common-unload-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8 9-4 3 4 3"></path><path d="m16 9 4 3-4 3"></path><path d="m14 5-4 14"></path></svg></div>
                                <div><span class="wpacu-common-unload-subtitle"><?php esc_html_e('Legacy jQuery compatibility', 'wp-asset-clean-up'); ?></span><h4><?php esc_html_e('jQuery Migrate', 'wp-asset-clean-up'); ?></h4></div>
                                <span class="wpacu-common-unload-risk-badge"><?php esc_html_e('Compatibility-sensitive', 'wp-asset-clean-up'); ?></span>
                            </div>
                            <p><?php esc_html_e('Unload the compatibility layer used by older jQuery code. Test legacy themes, sliders, forms, menus, page builders, account areas, and checkout flows before enabling this rule.', 'wp-asset-clean-up'); ?></p>
                            <div class="wpacu-common-unload-advice wpacu-common-unload-advice--warning"><strong><?php esc_html_e('Why it matters:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('when WordPress jQuery is requested, jQuery Migrate can be loaded as a dependency to support older code.', 'wp-asset-clean-up'); ?></div>
                            <div class="wpacu-common-unload-meta"><strong><?php esc_html_e('Affected asset:', 'wp-asset-clean-up'); ?></strong><code>jquery-migrate(.min).js</code></div>
                        </div>
                    </article>
                </div>

                <aside class="wpacu-common-unloads-footer-note">
                    <div class="wpacu-common-unloads-footer-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"></path><path d="M6 7V5h12v2"></path><path d="M8 11v6"></path><path d="M12 11v6"></path><path d="M16 11v6"></path><path d="M5 7l1 14h12l1-14"></path></svg></div>
                    <div><strong><?php esc_html_e('No WordPress core files are edited or deleted.', 'wp-asset-clean-up'); ?></strong><p><?php esc_html_e('Asset CleanUp changes what WordPress loads. Turn a rule off and save the page to restore the default behavior.', 'wp-asset-clean-up'); ?></p></div>
                </aside>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    'use strict';

    if (typeof window.wpacuCommonUnloadsUseEffects !== 'boolean') {
        window.wpacuCommonUnloadsUseEffects = true;
    }

    var root = document.getElementById('wpacu-common-unloads-settings');

    if (! root || root.getAttribute('data-wpacu-initialized') === '1') {
        return;
    }

    root.setAttribute('data-wpacu-initialized', '1');

    var toggles = root.querySelectorAll('.wpacu-common-unload-toggle');
    var instances = [];
    var measurementNode = null;
    var resizeTimer = null;
    var reduceMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
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
        return state === 'changed' ? instance.changedLabel : instance.defaultLabel;
    }

    function getTargetWidth(instance, state) {
        var minWidth = readPixelCustomProperty('--wpacu-common-toggle-min-width', 110);
        var maxWidth = readPixelCustomProperty('--wpacu-common-toggle-max-width', 320);
        var horizontalSpace = readPixelCustomProperty('--wpacu-common-toggle-horizontal-space', 66);
        var labelWidth = measureLabel(getStateLabel(instance, state));
        return Math.min(maxWidth, Math.max(minWidth, labelWidth + horizontalSpace));
    }

    function setTrackWidth(instance, state, immediate) {
        var targetWidth = getTargetWidth(instance, state);
        if (immediate) {
            instance.track.classList.add('is-measuring');
        }
        instance.track.style.setProperty('--wpacu-common-toggle-current-width', targetWidth + 'px');
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

    function applyImmediateState(instance) {
        var state = getCurrentState(instance.input);
        clearTransition(instance);
        instance.sequence += 1;
        instance.card.classList.toggle('is-unloaded', state === 'changed');
        instance.toggle.classList.remove('is-fading');
        instance.toggle.setAttribute('data-visual-state', state);
        setTrackWidth(instance, state, true);
    }

    function applyAnimatedState(instance) {
        var state = getCurrentState(instance.input);
        var sequence;
        instance.card.classList.toggle('is-unloaded', state === 'changed');
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

    function updateEffectsSetting() {
        effectsEnabled = window.wpacuCommonUnloadsUseEffects !== false && ! (reduceMotionQuery && reduceMotionQuery.matches);
        root.classList.toggle('wpacu-common-unloads-no-effects', ! effectsEnabled);
    }

    var i;
    for (i = 0; i < toggles.length; i++) {
        (function (toggle) {
            var input = toggle.querySelector('input[type="checkbox"]');
            var track = toggle.querySelector('.wpacu-common-unload-toggle__track');
            var defaultLabel = toggle.querySelector('.wpacu-common-unload-toggle__text--default');
            var changedLabel = toggle.querySelector('.wpacu-common-unload-toggle__text--changed');
            var card = toggle.closest('.wpacu-common-unload-card');
            var instance;
            if (! input || ! track || ! defaultLabel || ! changedLabel || ! card) {
                return;
            }
            instance = {input: input, card: card, toggle: toggle, track: track, defaultLabel: defaultLabel, changedLabel: changedLabel, sequence: 0, fadeTimer: null, revealTimer: null};
            instances.push(instance);
            input.addEventListener('change', function () { applyAnimatedState(instance); });
        }(toggles[i]));
    }

    function refreshAllToggleWidths() {
        var j;
        for (j = 0; j < instances.length; j++) {
            applyImmediateState(instances[j]);
        }
    }

    updateEffectsSetting();
    refreshAllToggleWidths();

    window.wpacuCommonUnloadsRefreshToggleWidths = refreshAllToggleWidths;
    window.wpacuCommonUnloadsSetEffects = function (enabled) {
        window.wpacuCommonUnloadsUseEffects = !! enabled;
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
            reduceMotionQuery.addEventListener('change', function () { updateEffectsSetting(); refreshAllToggleWidths(); });
        } else if (typeof reduceMotionQuery.addListener === 'function') {
            reduceMotionQuery.addListener(function () { updateEffectsSetting(); refreshAllToggleWidths(); });
        }
    }
}());
</script>
