<?php
/**
 * Asset CleanUp Lite — dedicated Lite vs. Pro comparison page.
 *
 * Intended route:
 * /wp-admin/admin.php?page=wpassetcleanup_getting_started&wpacu_for=lite-vs-pro
 *
 * Expected optional variable from the controller:
 *
 * $wpacu_lvp_config = array(
 *     'version' => WPACU_PLUGIN_VERSION,
 *     'urls'    => array(
 *         'getting_started' => '...',
 *         'manager'         => '...',
 *         'help'            => '...',
 *         'pro'             => '...',
 *         'pricing'         => '...',
 *         'pro_docs'        => '...',
 *     ),
 *     'commercial_notes' => array(
 *         'show'                => true,
 *         'refund'              => '30-day money-back guarantee',
 *         'license_expiry'      => 'Pro keeps working if the license expires',
 *         'updates_and_support' => 'Updates and premium support require an active license',
 *     ),
 * );
 *
 * PHP 5.6 compatible.
 */

if (! defined('ABSPATH')) {
    exit;
}

$wpacu_lvp_config = (isset($wpacu_lvp_config) && is_array($wpacu_lvp_config))
    ? $wpacu_lvp_config
    : array();

$wpacu_lvp_admin_url = function_exists('admin_url') ? admin_url('admin.php') : 'admin.php';
$wpacu_lvp_version = defined('WPACU_PLUGIN_VERSION') ? WPACU_PLUGIN_VERSION : '';

$wpacu_lvp_defaults = array(
    'version' => $wpacu_lvp_version,
    'urls'    => array(
        'getting_started' => $wpacu_lvp_admin_url . '?page=' . WPACU_PLUGIN_ID . '_getting_started',
        'manager'         => $wpacu_lvp_admin_url . '?page=' . WPACU_PLUGIN_ID . '_assets_manager',
        'help'            => $wpacu_lvp_admin_url . '?page=' . WPACU_PLUGIN_ID . '_get_help',
        'pro'             => 'https://www.gabelivan.com/items/wp-asset-cleanup-pro/',
        'pricing'         => 'https://www.gabelivan.com/items/wp-asset-cleanup-pro/',
        'pro_docs'        => 'https://www.assetcleanup.com/docs/',
        'logo_mark'       => trailingslashit(WPACU_PLUGIN_URL) . 'assets/images/wpacu-logo-transparent-bg-v1.png',
    ),
    'commercial_notes' => array(
        'show'                => true,
        'refund'              => esc_html__('30-day money-back guarantee', 'wp-asset-clean-up'),
        'license_expiry'      => esc_html__('Pro keeps working if the license expires', 'wp-asset-clean-up'),
        'updates_and_support' => esc_html__('Updates and premium support require an active license', 'wp-asset-clean-up'),
    ),
);

$wpacu_lvp_config = array_replace_recursive($wpacu_lvp_defaults, $wpacu_lvp_config);
$wpacu_lvp_urls = $wpacu_lvp_config['urls'];
$wpacu_lvp_commercial_notes = $wpacu_lvp_config['commercial_notes'];

/*
 * Comparison rows are intentionally explicit rather than marketing-only checkmarks.
 * This keeps the page useful even for a visitor who has never used an asset manager.
 *
 * Before a public release, review these rows against the exact Lite/Pro builds being shipped.
 */
$wpacu_lvp_feature_groups = array(
    array(
        'id'          => 'core-workflow',
        'number'      => '01',
        'kicker'      => esc_html__('Shared foundation', 'wp-asset-clean-up'),
        'title'       => esc_html__('Core asset workflow', 'wp-asset-clean-up'),
        'intro'       => esc_html__('Both editions use the same basic method: inspect what a page loads, create a targeted rule, test it, and publish only after the result is safe.', 'wp-asset-clean-up'),
        'icon'        => 'assets',
        'rows'        => array(
            array(
                'feature' => esc_html__('Inspect enqueued CSS and JavaScript', 'wp-asset-clean-up'),
                'detail'  => esc_html__('See the handles and sources loaded by WordPress, the active theme, and installed plugins on the selected page.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Page-level inspection and management.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('The same core asset inspection workflow.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Unload an individual asset on one page', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Prevent a selected stylesheet or script from loading when the related feature is absent from that page.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('A primary Lite use case.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Included, with additional rule scopes when needed.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Site-wide unload and page-level exceptions', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Unload an asset everywhere, then keep it loaded on the individual pages where it is required.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Useful when an asset is needed on only a small number of known pages.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'expanded', 'label' => esc_html__('Expanded', 'wp-asset-clean-up'), 'detail' => esc_html__('Adds broader contextual and RegEx-based exception options.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Post type-wide asset rules', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Apply an unload rule across all entries of a post type, such as posts, pages, products, or another custom post type.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Covers the common post type-wide rule for singular content.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Included alongside more granular contextual rules.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Test Mode', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Apply optimization rules only for the logged-in administrator while regular visitors continue to receive the normal site.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Recommended for every first optimization.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Also protects testing of Pro-only rule types.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Dashboard and optional front-end management', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Work from supported WordPress Dashboard screens or expose the manager at the bottom of the front-end for the logged-in administrator.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Choose the workflow that suits the page being inspected.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'expanded', 'label' => esc_html__('Expanded', 'wp-asset-clean-up'), 'detail' => esc_html__('Dashboard coverage extends to more WordPress page contexts.', 'wp-asset-clean-up')),
            ),
        ),
    ),
    array(
        'id'          => 'rule-coverage',
        'number'      => '02',
        'kicker'      => esc_html__('Where Pro begins to save time', 'wp-asset-clean-up'),
        'title'       => esc_html__('Rule coverage across the website', 'wp-asset-clean-up'),
        'intro'       => esc_html__('Lite handles targeted rules plus common site-wide and post type-wide rules. Pro becomes useful when the same decision must apply to taxonomies, archives, URL patterns, devices, or many related contexts.', 'wp-asset-clean-up'),
        'icon'        => 'scope',
        'rows'        => array(
            array(
                'feature' => esc_html__('Rules based on taxonomy terms assigned to singular content', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Example: unload an asset on product pages assigned to selected product categories, while leaving other products unchanged.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('Manage the relevant pages individually or use broader Lite rules.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'pro', 'label' => esc_html__('Pro rule', 'wp-asset-clean-up'), 'detail' => esc_html__('Target singular posts, pages, or custom post types by assigned taxonomy terms.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Taxonomy archive asset management', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Manage CSS and JavaScript on category, tag, product category, and other custom taxonomy archive pages.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('Lite focuses on homepage and singular content contexts.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'pro', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Built-in and custom taxonomy archives are supported.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Author, date, search, 404, and custom post type archive contexts', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Manage assets on WordPress-generated views that do not have a normal post editor screen.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('These advanced archive contexts are outside the Lite asset manager scope.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'pro', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Manage these contexts directly from the Dashboard.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Request URI rules using regular expressions', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Apply unload rules or load exceptions when the request URI matches one or more patterns.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('Use explicit page, site-wide, or post type-wide rules instead.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'advanced', 'label' => esc_html__('Advanced', 'wp-asset-clean-up'), 'detail' => esc_html__('Useful for URL structures that are not covered cleanly by standard WordPress contexts.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Conditional loading by screen size', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Keep or skip selected assets based on the visitor’s screen size, such as loading a desktop-only resource only on wider screens.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('No device or screen-size asset rule builder.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'advanced', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Apply conditional asset loading without writing custom code.', 'wp-asset-clean-up')),
            ),
        ),
    ),
    array(
        'id'          => 'plugin-control',
        'number'      => '03',
        'kicker'      => esc_html__('Beyond front-end files', 'wp-asset-clean-up'),
        'title'       => esc_html__('Plugin-level control', 'wp-asset-clean-up'),
        'intro'       => esc_html__('Unloading a plugin’s CSS or JavaScript is not the same as preventing the plugin itself from running. Plugins Manager is a Pro feature because it filters plugins early, before their normal PHP code is loaded.', 'wp-asset-clean-up'),
        'icon'        => 'plugin',
        'rows'        => array(
            array(
                'feature' => esc_html__('Unload CSS or JavaScript that belongs to a plugin', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Remove selected front-end files while the plugin itself continues to run normally.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Asset-level cleanup is available in Lite.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Use asset-level rules when the plugin still needs to run.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Prevent an entire plugin from loading in selected front-end contexts', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Stop the plugin’s PHP code and its front-end output where that plugin is not required, rather than removing only its individual assets.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('Lite manages assets, not whole-plugin execution.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'pro', 'label' => esc_html__('Plugins Manager', 'wp-asset-clean-up'), 'detail' => esc_html__('The defining Pro capability for plugin-level optimization.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Whole-plugin rules for exact entries, post types, taxonomies, and archives', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Target individual posts/pages/products, all entries of a post type, assigned taxonomy terms, taxonomy archives, search, author, date, 404, and custom post type archives.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('No whole-plugin rule engine.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'expanded', 'label' => esc_html__('Broad coverage', 'wp-asset-clean-up'), 'detail' => esc_html__('Use standard WordPress contexts instead of maintaining large RegEx lists.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Whole-plugin load exceptions', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Unload a plugin broadly, then keep it active in the smaller set of contexts where it is actually needed.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('Applies only to CSS/JavaScript asset exceptions in Lite.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'pro', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Available across supported Plugins Manager rule types.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Whole-plugin rules based on logged-in user role', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Keep or skip a plugin for selected roles when front-end requirements differ between administrators, subscribers, customers, or other users.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('No user-role plugin filtering.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'advanced', 'label' => esc_html__('Advanced', 'wp-asset-clean-up'), 'detail' => esc_html__('Useful for membership, account, editorial, or role-specific experiences.', 'wp-asset-clean-up')),
            ),
        ),
    ),
    array(
        'id'          => 'delivery',
        'number'      => '04',
        'kicker'      => esc_html__('How remaining resources are delivered', 'wp-asset-clean-up'),
        'title'       => esc_html__('Rendering and delivery controls', 'wp-asset-clean-up'),
        'intro'       => esc_html__('Both editions include useful front-end optimization settings. Pro adds per-asset controls that change where or how individual resources are delivered.', 'wp-asset-clean-up'),
        'icon'        => 'delivery',
        'rows'        => array(
            array(
                'feature' => esc_html__('Minify and combine remaining CSS and JavaScript', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Optimize files that remain loaded, with exclusions where needed. Combination should be enabled only when it benefits the site’s real setup.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Core file optimization settings are available.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Works alongside the additional per-asset delivery controls.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Google Fonts, local fonts, preloads, and Resource Loading rules', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Control font delivery, preload selected resources, and add image attributes such as fetchpriority, loading, and decoding through matching rules.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Available as part of the modern Lite settings.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('The same core controls are available in Pro.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('HTML source cleanup and optional WordPress feature cleanup', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Remove selected metadata, feed links, comments, emojis, oEmbed output, and other optional front-end output when the website does not use it.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Each cleanup option remains individually controlled.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('The same cleanup foundation is retained.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Inline CSS files', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Place the content of selected eligible stylesheets directly into the page when that trade-off is appropriate.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Available for eligible CSS files.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Included alongside additional delivery options.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Critical CSS Management', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Add and manage your own critical CSS so important above-the-fold styling can load before non-critical stylesheets.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Granular Critical CSS management is available from the Dashboard.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('The same granular Critical CSS management is available.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Inline JavaScript files', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Place selected eligible JavaScript file content directly into the page when the execution order and caching trade-offs have been tested.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('Lite does not inline external JavaScript files.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'advanced', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Use selectively after testing dependencies and execution order.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Per-asset async and defer for JavaScript', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Apply async or defer to selected scripts instead of changing every script globally.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('No per-script async/defer control in Lite.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'advanced', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Requires dependency-aware testing, especially around jQuery and inline code.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Move CSS or JavaScript between HEAD and BODY', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Change the location of selected resources when they need to load later or, in rarer cases, earlier.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('Resource placement remains unchanged.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'advanced', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Use only when the page behavior and dependency order are understood.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Manage hardcoded CSS and JavaScript tags', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Target eligible LINK, STYLE, and SCRIPT tags that were not registered through the standard WordPress enqueue system.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'not-included', 'label' => esc_html__('Not included', 'wp-asset-clean-up'), 'detail' => esc_html__('Lite focuses on normally enqueued assets.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'advanced', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Helpful when themes or third-party tools inject assets outside standard WordPress APIs.', 'wp-asset-clean-up')),
            )
        ),
    ),
    array(
        'id'          => 'workflow-support',
        'number'      => '05',
        'kicker'      => esc_html__('Ongoing management', 'wp-asset-clean-up'),
        'title'       => esc_html__('Audit, maintenance, and support', 'wp-asset-clean-up'),
        'intro'       => esc_html__('The right edition is not only about the number of features. It is also about how much time the site requires to maintain rules safely as it changes.', 'wp-asset-clean-up'),
        'icon'        => 'support',
        'rows'        => array(
            array(
                'feature' => esc_html__('Overview and centralized rule auditing', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Review saved rules in one place, understand their scope, edit supported entries, remove obsolete or dormant rules, and return to the original management context when needed.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Centralized auditing and management for the rule types available in Lite.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'expanded', 'label' => esc_html__('Expanded', 'wp-asset-clean-up'), 'detail' => esc_html__('Also covers the broader Pro contexts and plugin-level rules created through Plugins Manager.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('WordPress Multisite compatibility', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Use Asset CleanUp within a multisite installation while keeping settings and generated cache data separated per site.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Supported in Lite.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'included', 'label' => esc_html__('Included', 'wp-asset-clean-up'), 'detail' => esc_html__('Supported in Pro.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Support channel', 'wp-asset-clean-up'),
                'detail'  => esc_html__('Where to ask for help when the plugin itself behaves unexpectedly or a compatibility issue needs investigation.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'community', 'label' => esc_html__('Community support', 'wp-asset-clean-up'), 'detail' => esc_html__('Use the public WordPress.org support forum.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'pro', 'label' => esc_html__('Premium support', 'wp-asset-clean-up'), 'detail' => esc_html__('Direct customer support while the license is active.', 'wp-asset-clean-up')),
            ),
            array(
                'feature' => esc_html__('Release priority', 'wp-asset-clean-up'),
                'detail'  => esc_html__('How quickly compatible improvements and new functionality normally reach the edition.', 'wp-asset-clean-up'),
                'lite'    => array('state' => 'community', 'label' => esc_html__('Standard release path', 'wp-asset-clean-up'), 'detail' => esc_html__('Stable Lite updates are delivered through WordPress.org.', 'wp-asset-clean-up')),
                'pro'     => array('state' => 'pro', 'label' => esc_html__('Earlier access', 'wp-asset-clean-up'), 'detail' => esc_html__('Features shared by both editions are generally released to Pro first.', 'wp-asset-clean-up')),
            ),
        ),
    ),
);

$wpacu_lvp_status_icon = function ($state) {
    $with_check = array('included', 'expanded', 'pro', 'advanced', 'community');

    if ('caution' === $state) {
        return '!';
    }

    if (in_array($state, $with_check, true)) {
        return '✓';
    }

    return '—';
};
?>

<div id="wpacu-lite-vs-pro-page-wrap">
    <div class="wpacu-lvp-breadcrumbs" aria-label="<?php esc_attr_e('Getting Started breadcrumb', 'wp-asset-clean-up'); ?>">
        <a href="<?php echo esc_url($wpacu_lvp_urls['getting_started']); ?>">
            <?php esc_html_e('Getting Started', 'wp-asset-clean-up'); ?>
        </a>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?php esc_html_e('Lite vs. Pro', 'wp-asset-clean-up'); ?></span>
    </div>

    <section class="wpacu-lvp-hero" aria-labelledby="wpacu-lvp-page-title">
        <div class="wpacu-lvp-hero-copy">
            <div class="wpacu-lvp-hero-identity">
                <img
                    class="wpacu-lvp-logo-mark"
                    src="<?php echo esc_url($wpacu_lvp_urls['logo_mark']); ?>"
                    alt=""
                    aria-hidden="true"
                >
                <div class="wpacu-lvp-eyebrow">
                    <?php esc_html_e('Edition comparison · choose by workflow, not by pressure', 'wp-asset-clean-up'); ?>
                </div>
            </div>

            <h1 id="wpacu-lvp-page-title">
                <?php esc_html_e('Choose the level of control your website actually needs.', 'wp-asset-clean-up'); ?>
            </h1>

            <p class="wpacu-lvp-hero-description">
                <?php
                echo wp_kses_post(
                    __('Both editions are built around the same principle: <strong>load only what each page needs</strong>. Lite gives you a capable asset-level workflow. Pro adds broader rule scopes, plugin-level control, and advanced delivery options when manual management no longer scales.', 'wp-asset-clean-up')
                );
                ?>
            </p>

            <div class="wpacu-lvp-hero-actions">
                <a class="wpacu-lvp-button wpacu-lvp-button--primary" href="#wpacu-lvp-comparison">
                    <?php esc_html_e('Compare the editions', 'wp-asset-clean-up'); ?>
                </a>

                <a class="wpacu-lvp-button wpacu-lvp-button--secondary" href="<?php echo esc_url($wpacu_lvp_urls['manager']); ?>">
                    <?php esc_html_e('Continue with Lite', 'wp-asset-clean-up'); ?>
                </a>
            </div>

            <ul class="wpacu-lvp-hero-trust">
                <li><?php esc_html_e('Lite is enough to learn the workflow and make useful improvements', 'wp-asset-clean-up'); ?></li>
                <li><?php esc_html_e('Pro expands control; it does not remove the need to test', 'wp-asset-clean-up'); ?></li>
                <li><?php esc_html_e('Upgrade when rule coverage or maintenance time becomes the limitation', 'wp-asset-clean-up'); ?></li>
            </ul>
        </div>

        <div class="wpacu-lvp-scope-visual" aria-label="<?php esc_attr_e('Lite offers targeted control while Pro expands the same workflow across more website contexts', 'wp-asset-clean-up'); ?>">
            <article class="wpacu-lvp-scope-card is-lite">
                <span class="wpacu-lvp-scope-edition"><?php esc_html_e('LITE', 'wp-asset-clean-up'); ?></span>
                <strong><?php esc_html_e('Targeted asset control', 'wp-asset-clean-up'); ?></strong>
                <span><?php esc_html_e('One page', 'wp-asset-clean-up'); ?></span>
                <span><?php esc_html_e('Site-wide asset rule', 'wp-asset-clean-up'); ?></span>
                <span><?php esc_html_e('Post type', 'wp-asset-clean-up'); ?></span>
            </article>

            <div class="wpacu-lvp-scope-bridge" aria-hidden="true">
                <span><?php esc_html_e('Same core workflow', 'wp-asset-clean-up'); ?></span>
                <i></i>
            </div>

            <article class="wpacu-lvp-scope-card is-pro">
                <span class="wpacu-lvp-scope-edition"><?php esc_html_e('PRO', 'wp-asset-clean-up'); ?></span>
                <strong><?php esc_html_e('Broader contextual control', 'wp-asset-clean-up'); ?></strong>
                <span><?php esc_html_e('Taxonomies and archives', 'wp-asset-clean-up'); ?></span>
                <span><?php esc_html_e('RegEx and screen size', 'wp-asset-clean-up'); ?></span>
                <span><?php esc_html_e('Whole plugins', 'wp-asset-clean-up'); ?></span>
            </article>
        </div>
    </section>

    <div class="wpacu-lvp-page-nav-wrap">
        <nav class="wpacu-lvp-page-nav" data-wpacu-lvp-nav aria-label="<?php esc_attr_e('Lite vs. Pro page sections', 'wp-asset-clean-up'); ?>">
            <span class="wpacu-lvp-page-nav-label"><?php esc_html_e('On this page', 'wp-asset-clean-up'); ?></span>
            <a href="#wpacu-lvp-quick-answer"><?php esc_html_e('Quick answer', 'wp-asset-clean-up'); ?></a>
            <a href="#wpacu-lvp-shared"><?php esc_html_e('What Lite includes', 'wp-asset-clean-up'); ?></a>
            <a href="#wpacu-lvp-comparison"><?php esc_html_e('Full comparison', 'wp-asset-clean-up'); ?></a>
            <a href="#wpacu-lvp-fit"><?php esc_html_e('Which fits?', 'wp-asset-clean-up'); ?></a>
            <a href="#wpacu-lvp-questions"><?php esc_html_e('Questions', 'wp-asset-clean-up'); ?></a>
        </nav>
    </div>

    <main class="wpacu-lvp-main">
        <section id="wpacu-lvp-quick-answer" class="wpacu-lvp-section" aria-labelledby="wpacu-lvp-quick-title">
            <div class="wpacu-lvp-section-heading">
                <div class="wpacu-lvp-kicker"><?php esc_html_e('The honest recommendation', 'wp-asset-clean-up'); ?></div>
                <h2 id="wpacu-lvp-quick-title" class="wpacu-lvp-section-title">
                    <?php esc_html_e('Do not buy Pro before the scope of the work justifies it.', 'wp-asset-clean-up'); ?>
                </h2>
                <p class="wpacu-lvp-section-intro">
                    <?php esc_html_e('A successful Lite setup is better than an unused Pro license. Start with the edition that lets you make safe, understandable changes now.', 'wp-asset-clean-up'); ?>
                </p>
            </div>

            <div class="wpacu-lvp-answer-grid">
                <article class="wpacu-lvp-answer-card is-lite">
                    <div class="wpacu-lvp-answer-topline">
                        <span class="wpacu-lvp-edition-pill is-lite"><?php esc_html_e('STAY WITH LITE', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-lvp-icon wpacu-lvp-icon--target" aria-hidden="true"></span>
                    </div>

                    <h3><?php esc_html_e('Lite is the right fit when the work is targeted and manageable.', 'wp-asset-clean-up'); ?></h3>

                    <ul>
                        <li><?php esc_html_e('You are learning which assets belong on which pages.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('You want to optimize a few important pages or obvious site-wide assets.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('Homepage, singular pages, and post type rules cover most of your use cases.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('You do not need to stop an entire plugin from running.', 'wp-asset-clean-up'); ?></li>
                    </ul>

                    <a class="wpacu-lvp-text-link" href="<?php echo esc_url($wpacu_lvp_urls['manager']); ?>">
                        <?php esc_html_e('Use the CSS & JS Manager', 'wp-asset-clean-up'); ?>
                    </a>
                </article>

                <article class="wpacu-lvp-answer-card is-pro">
                    <div class="wpacu-lvp-answer-topline">
                        <span class="wpacu-lvp-edition-pill is-pro"><?php esc_html_e('CONSIDER PRO', 'wp-asset-clean-up'); ?></span>
                        <span class="wpacu-lvp-icon wpacu-lvp-icon--scale" aria-hidden="true"></span>
                    </div>

                    <h3><?php esc_html_e('Pro is the better fit when the same decisions repeat across a complex site.', 'wp-asset-clean-up'); ?></h3>

                    <ul>
                        <li><?php esc_html_e('You need taxonomy, archive, search, 404, RegEx, or screen-size rules.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('You need to prevent an entire plugin—not only its CSS/JS—from loading.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('You manage WooCommerce, membership, multilingual, publishing, or similarly varied contexts.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('Maintaining page-by-page rules is costing more time than the upgrade.', 'wp-asset-clean-up'); ?></li>
                    </ul>

                    <a class="wpacu-lvp-text-link" href="<?php echo esc_url($wpacu_lvp_urls['pricing']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('View Pro plans', 'wp-asset-clean-up'); ?>
                    </a>
                </article>
            </div>

            <div class="wpacu-lvp-principle-note">
                <span class="wpacu-lvp-icon wpacu-lvp-icon--shield" aria-hidden="true"></span>
                <div>
                    <strong><?php esc_html_e('Pro adds scope, not certainty.', 'wp-asset-clean-up'); ?></strong>
                    <p><?php esc_html_e('Neither edition can know every interaction inside your theme and plugins. Test Mode, small changes, and functional testing remain essential.', 'wp-asset-clean-up'); ?></p>
                </div>
            </div>
        </section>

        <section id="wpacu-lvp-shared" class="wpacu-lvp-section" aria-labelledby="wpacu-lvp-shared-title">
            <div class="wpacu-lvp-section-heading">
                <div class="wpacu-lvp-kicker"><?php esc_html_e('A capable free edition', 'wp-asset-clean-up'); ?></div>
                <h2 id="wpacu-lvp-shared-title" class="wpacu-lvp-section-title">
                    <?php esc_html_e('Lite already includes the workflow that matters most.', 'wp-asset-clean-up'); ?>
                </h2>
                <p class="wpacu-lvp-section-intro">
                    <?php esc_html_e('The comparison should make the difference clear without pretending that Lite is only a demo. You can make meaningful front-end improvements before an upgrade is necessary.', 'wp-asset-clean-up'); ?>
                </p>
            </div>

            <div class="wpacu-lvp-shared-grid">
                <article class="wpacu-lvp-shared-card">
                    <span class="wpacu-lvp-icon wpacu-lvp-icon--inspect" aria-hidden="true"></span>
                    <h3><?php esc_html_e('Inspect and unload assets', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('See the enqueued CSS and JavaScript on a page and prevent selected files from loading where their feature is absent.', 'wp-asset-clean-up'); ?></p>
                </article>

                <article class="wpacu-lvp-shared-card">
                    <span class="wpacu-lvp-icon wpacu-lvp-icon--test" aria-hidden="true"></span>
                    <h3><?php esc_html_e('Test before publishing', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Use Test Mode to keep visitors on the normal site while you validate layout and functionality as an administrator.', 'wp-asset-clean-up'); ?></p>
                </article>

                <article class="wpacu-lvp-shared-card">
                    <span class="wpacu-lvp-icon wpacu-lvp-icon--optimize" aria-hidden="true"></span>
                    <h3><?php esc_html_e('Optimize what remains', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Use minification, optional combination, font and preload controls, source cleanup, and Resource Loading rules where appropriate.', 'wp-asset-clean-up'); ?></p>
                </article>

                <article class="wpacu-lvp-shared-card">
                    <span class="wpacu-lvp-icon wpacu-lvp-icon--audit" aria-hidden="true"></span>
                    <h3><?php esc_html_e('Audit saved rules', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Use Overview to see where rules apply, edit supported entries, remove obsolete or dormant rules, and return to the original management context when needed.', 'wp-asset-clean-up'); ?></p>
                </article>
            </div>
        </section>

        <section id="wpacu-lvp-comparison" class="wpacu-lvp-section" aria-labelledby="wpacu-lvp-comparison-title">
            <div class="wpacu-lvp-section-heading wpacu-lvp-section-heading--with-actions">
                <div>
                    <div class="wpacu-lvp-kicker"><?php esc_html_e('Feature-by-feature', 'wp-asset-clean-up'); ?></div>
                    <h2 id="wpacu-lvp-comparison-title" class="wpacu-lvp-section-title">
                        <?php esc_html_e('Compare the control you receive, not just the number of checkmarks', 'wp-asset-clean-up'); ?>
                    </h2>
                    <p class="wpacu-lvp-section-intro">
                        <?php esc_html_e('Each row explains what the capability means in practice. “Advanced” does not mean automatically better; it means the option can change loading behavior more deeply and requires more careful testing.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>

                <div class="wpacu-lvp-comparison-actions" aria-label="<?php esc_attr_e('Comparison group controls', 'wp-asset-clean-up'); ?>">
                    <button type="button" class="wpacu-lvp-small-button" data-wpacu-lvp-expand-all>
                        <?php esc_html_e('Expand all', 'wp-asset-clean-up'); ?>
                    </button>
                    <button type="button" class="wpacu-lvp-small-button" data-wpacu-lvp-collapse-all>
                        <?php esc_html_e('Collapse all', 'wp-asset-clean-up'); ?>
                    </button>
                </div>
            </div>

            <div class="wpacu-lvp-legend" aria-label="<?php esc_attr_e('Comparison status legend', 'wp-asset-clean-up'); ?>">
                <span><i class="is-included">✓</i><?php esc_html_e('Included', 'wp-asset-clean-up'); ?></span>
                <span><i class="is-expanded">✓</i><?php esc_html_e('Expanded in Pro', 'wp-asset-clean-up'); ?></span>
                <span><i class="is-advanced">!</i><?php esc_html_e('Advanced / test carefully', 'wp-asset-clean-up'); ?></span>
                <span><i class="is-unavailable">—</i><?php esc_html_e('Not included in Lite', 'wp-asset-clean-up'); ?></span>
            </div>

            <div class="wpacu-lvp-comparison-groups" data-wpacu-lvp-groups>
                <?php foreach ($wpacu_lvp_feature_groups as $wpacu_lvp_group_index => $wpacu_lvp_group) : ?>
                    <details class="wpacu-lvp-comparison-group" data-wpacu-lvp-group<?php echo (0 === $wpacu_lvp_group_index) ? ' open' : ''; ?>>
                        <summary>
                            <span class="wpacu-lvp-group-number"><?php echo esc_html($wpacu_lvp_group['number']); ?></span>
                            <span class="wpacu-lvp-group-icon wpacu-lvp-group-icon--<?php echo esc_attr($wpacu_lvp_group['icon']); ?>" aria-hidden="true"></span>
                            <span class="wpacu-lvp-group-copy">
                                <span class="wpacu-lvp-group-kicker"><?php echo esc_html($wpacu_lvp_group['kicker']); ?></span>
                                <strong><?php echo esc_html($wpacu_lvp_group['title']); ?></strong>
                                <small><?php echo esc_html($wpacu_lvp_group['intro']); ?></small>
                            </span>
                            <span class="wpacu-lvp-group-count">
                                <?php
                                printf(
                                    esc_html__('%s comparisons', 'wp-asset-clean-up'),
                                    esc_html((string) count($wpacu_lvp_group['rows']))
                                );
                                ?>
                            </span>
                            <span class="wpacu-lvp-group-toggle" aria-hidden="true"></span>
                        </summary>

                        <div class="wpacu-lvp-comparison-table" role="table" aria-label="<?php echo esc_attr($wpacu_lvp_group['title']); ?>">
                            <div class="wpacu-lvp-comparison-head" role="row">
                                <div role="columnheader"><?php esc_html_e('Capability', 'wp-asset-clean-up'); ?></div>
                                <div role="columnheader">
                                    <span class="wpacu-lvp-edition-pill is-lite"><?php esc_html_e('LITE', 'wp-asset-clean-up'); ?></span>
                                </div>
                                <div role="columnheader" class="is-pro">
                                    <span class="wpacu-lvp-edition-pill is-pro"><?php esc_html_e('PRO', 'wp-asset-clean-up'); ?></span>
                                </div>
                            </div>

                            <?php foreach ($wpacu_lvp_group['rows'] as $wpacu_lvp_row) : ?>
                                <div class="wpacu-lvp-comparison-row" role="row">
                                    <div class="wpacu-lvp-feature-cell" role="rowheader">
                                        <strong><?php echo esc_html($wpacu_lvp_row['feature']); ?></strong>
                                        <p><?php echo esc_html($wpacu_lvp_row['detail']); ?></p>
                                    </div>

                                    <?php foreach (array('lite', 'pro') as $wpacu_lvp_edition_key) : ?>
                                        <?php
                                        $wpacu_lvp_cell = $wpacu_lvp_row[$wpacu_lvp_edition_key];
                                        $wpacu_lvp_is_pro_cell = ('pro' === $wpacu_lvp_edition_key) ? ' is-pro' : '';
                                        ?>
                                        <div class="wpacu-lvp-edition-cell<?php echo esc_attr($wpacu_lvp_is_pro_cell); ?>" role="cell">
                                            <span class="wpacu-lvp-mobile-edition-label">
                                                <?php echo ('pro' === $wpacu_lvp_edition_key) ? esc_html__('Pro', 'wp-asset-clean-up') : esc_html__('Lite', 'wp-asset-clean-up'); ?>
                                            </span>
                                            <span class="wpacu-lvp-status wpacu-lvp-status--<?php echo esc_attr($wpacu_lvp_cell['state']); ?>">
                                                <i aria-hidden="true"><?php echo esc_html($wpacu_lvp_status_icon($wpacu_lvp_cell['state'])); ?></i>
                                                <?php echo esc_html($wpacu_lvp_cell['label']); ?>
                                            </span>
                                            <p><?php echo esc_html($wpacu_lvp_cell['detail']); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="wpacu-lvp-fit" class="wpacu-lvp-section" aria-labelledby="wpacu-lvp-fit-title">
            <div class="wpacu-lvp-section-heading">
                <div class="wpacu-lvp-kicker"><?php esc_html_e('Use cases, not labels', 'wp-asset-clean-up'); ?></div>
                <h2 id="wpacu-lvp-fit-title" class="wpacu-lvp-section-title">
                    <?php esc_html_e('Which edition fits the way your site is built?', 'wp-asset-clean-up'); ?>
                </h2>
                <p class="wpacu-lvp-section-intro">
                    <?php esc_html_e('Website size alone is not the deciding factor. The important question is how varied the page contexts are and whether the same rules must be maintained repeatedly.', 'wp-asset-clean-up'); ?>
                </p>
            </div>

            <div class="wpacu-lvp-scenarios">
                <article class="wpacu-lvp-scenario">
                    <span class="wpacu-lvp-scenario-label is-lite"><?php esc_html_e('LITE IS OFTEN ENOUGH', 'wp-asset-clean-up'); ?></span>
                    <h3><?php esc_html_e('A focused blog or small marketing site', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('You know the handful of pages that matter, the same layout repeats predictably, and asset-level rules solve the visible bloat.', 'wp-asset-clean-up'); ?></p>
                    <div class="wpacu-lvp-scenario-signal">
                        <strong><?php esc_html_e('Typical need:', 'wp-asset-clean-up'); ?></strong>
                        <?php esc_html_e('Unload a contact-form, slider, block-library, or plugin asset where its feature is absent.', 'wp-asset-clean-up'); ?>
                    </div>
                </article>

                <article class="wpacu-lvp-scenario is-middle">
                    <span class="wpacu-lvp-scenario-label is-either"><?php esc_html_e('DEPENDS ON COMPLEXITY', 'wp-asset-clean-up'); ?></span>
                    <h3><?php esc_html_e('A content-heavy or multilingual website', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Lite can still work well, but Pro saves time when rules differ by taxonomy, author, language-aware permalink, archive type, or a large set of related entries.', 'wp-asset-clean-up'); ?></p>
                    <div class="wpacu-lvp-scenario-signal">
                        <strong><?php esc_html_e('Upgrade signal:', 'wp-asset-clean-up'); ?></strong>
                        <?php esc_html_e('You are recreating the same logical rule page by page or maintaining fragile URL workarounds.', 'wp-asset-clean-up'); ?>
                    </div>
                </article>

                <article class="wpacu-lvp-scenario is-pro">
                    <span class="wpacu-lvp-scenario-label is-pro"><?php esc_html_e('PRO IS OFTEN THE BETTER FIT', 'wp-asset-clean-up'); ?></span>
                    <h3><?php esc_html_e('WooCommerce, membership, directory, or agency-managed sites', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Products, account states, taxonomies, archives, user roles, and many active plugins create more contexts than page-by-page asset rules can comfortably maintain.', 'wp-asset-clean-up'); ?></p>
                    <div class="wpacu-lvp-scenario-signal">
                        <strong><?php esc_html_e('Typical need:', 'wp-asset-clean-up'); ?></strong>
                        <?php esc_html_e('Unload entire plugins or apply reusable rules across post types, taxonomies, archives, roles, or device conditions.', 'wp-asset-clean-up'); ?>
                    </div>
                </article>
            </div>

            <div class="wpacu-lvp-upgrade-signal">
                <div class="wpacu-lvp-upgrade-signal-icon" aria-hidden="true"></div>
                <div>
                    <span><?php esc_html_e('The clearest upgrade signal', 'wp-asset-clean-up'); ?></span>
                    <strong><?php esc_html_e('You already understand the rule you need, but expressing and maintaining it in Lite takes too much repetitive work.', 'wp-asset-clean-up'); ?></strong>
                </div>
            </div>
        </section>

        <section id="wpacu-lvp-questions" class="wpacu-lvp-section" aria-labelledby="wpacu-lvp-questions-title">
            <div class="wpacu-lvp-section-heading">
                <div class="wpacu-lvp-kicker"><?php esc_html_e('Before you decide', 'wp-asset-clean-up'); ?></div>
                <h2 id="wpacu-lvp-questions-title" class="wpacu-lvp-section-title">
                    <?php esc_html_e('Common questions about upgrading', 'wp-asset-clean-up'); ?>
                </h2>
            </div>

            <div class="wpacu-lvp-faq-grid">
                <details class="wpacu-lvp-faq" open>
                    <summary><?php esc_html_e('Do I need Pro to improve Core Web Vitals?', 'wp-asset-clean-up'); ?></summary>
                    <div class="wpacu-lvp-faq-content"><p><?php esc_html_e('No. Lite can reduce unnecessary CSS and JavaScript, page weight, render-blocking work, and browser processing. Pro becomes useful when the rules needed to achieve or maintain those improvements require broader contexts or advanced delivery controls.', 'wp-asset-clean-up'); ?></p></div>
                </details>

                <details class="wpacu-lvp-faq">
                    <summary><?php esc_html_e('Will Pro automatically know what is safe to unload?', 'wp-asset-clean-up'); ?></summary>
                    <div class="wpacu-lvp-faq-content"><p><?php esc_html_e('No. Pro gives you more ways to express a rule, but it cannot understand every theme, plugin, user interaction, or future content change. Test Mode and functional testing remain part of the workflow.', 'wp-asset-clean-up'); ?></p></div>
                </details>

                <details class="wpacu-lvp-faq">
                    <summary><?php esc_html_e('Does Pro replace a caching plugin or CDN?', 'wp-asset-clean-up'); ?></summary>
                    <div class="wpacu-lvp-faq-content"><p><?php esc_html_e('No. Asset CleanUp controls front-end resources and, in Pro, plugin execution in selected contexts. Page caching reduces server work, while a CDN improves delivery. These layers solve different problems and can complement each other.', 'wp-asset-clean-up'); ?></p></div>
                </details>

                <details class="wpacu-lvp-faq">
                    <summary><?php esc_html_e('Can I stay with Lite and upgrade later?', 'wp-asset-clean-up'); ?></summary>
                    <div class="wpacu-lvp-faq-content"><p><?php esc_html_e('Yes. There is no benefit in buying rule scopes you do not need yet. Upgrade when the site’s real contexts, maintenance time, or whole-plugin requirements make the added controls useful.', 'wp-asset-clean-up'); ?></p></div>
                </details>

                <details class="wpacu-lvp-faq">
                    <summary><?php esc_html_e('What happens if a Pro license expires?', 'wp-asset-clean-up'); ?></summary>
                    <div class="wpacu-lvp-faq-content"><p><?php esc_html_e('The installed Pro plugin can continue to work. An active license is required for future updates and premium support, so renewing is most important when you need current compatibility, fixes, new features, or help.', 'wp-asset-clean-up'); ?></p></div>
                </details>

                <details class="wpacu-lvp-faq">
                    <summary><?php esc_html_e('Does unloading a whole plugin do more than unloading its CSS and JavaScript?', 'wp-asset-clean-up'); ?></summary>
                    <div class="wpacu-lvp-faq-content"><p><?php esc_html_e('Yes. Asset-level rules remove selected front-end files while the plugin continues to run. Plugins Manager can prevent the plugin’s PHP code and front-end output from loading in selected contexts, which can save additional work but also requires broader functional testing.', 'wp-asset-clean-up'); ?></p></div>
                </details>
            </div>
        </section>

        <?php if (! empty($wpacu_lvp_commercial_notes['show'])) : ?>
            <div class="wpacu-lvp-commercial-notes" aria-label="<?php esc_attr_e('Asset CleanUp Pro purchase notes', 'wp-asset-clean-up'); ?>">
                <span><i aria-hidden="true">✓</i><?php echo esc_html($wpacu_lvp_commercial_notes['refund']); ?></span>
                <span><i aria-hidden="true">✓</i><?php echo esc_html($wpacu_lvp_commercial_notes['license_expiry']); ?></span>
                <span><i aria-hidden="true">✓</i><?php echo esc_html($wpacu_lvp_commercial_notes['updates_and_support']); ?></span>
            </div>
        <?php endif; ?>

        <section class="wpacu-lvp-final-choice" aria-labelledby="wpacu-lvp-final-title">
            <div class="wpacu-lvp-final-choice-copy">
                <div class="wpacu-lvp-kicker"><?php esc_html_e('A useful decision either way', 'wp-asset-clean-up'); ?></div>
                <h2 id="wpacu-lvp-final-title"><?php esc_html_e('Not sure yet? Keep using Lite until the missing control is obvious.', 'wp-asset-clean-up'); ?></h2>
                <p><?php esc_html_e('The best time to upgrade is when you can name the exact Pro rule that will save time or solve a limitation—not simply because a comparison page exists.', 'wp-asset-clean-up'); ?></p>
            </div>

            <div class="wpacu-lvp-final-choice-actions">
                <a class="wpacu-lvp-button wpacu-lvp-button--light" href="<?php echo esc_url($wpacu_lvp_urls['manager']); ?>">
                    <?php esc_html_e('Continue with Lite', 'wp-asset-clean-up'); ?>
                </a>
                <a class="wpacu-lvp-button wpacu-lvp-button--accent" href="<?php echo esc_url($wpacu_lvp_urls['pricing']); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('View Pro plans', 'wp-asset-clean-up'); ?>
                </a>
                <a class="wpacu-lvp-button wpacu-lvp-button--ghost-light" href="<?php echo esc_url($wpacu_lvp_urls['help']); ?>">
                    <?php esc_html_e('Open Help Center', 'wp-asset-clean-up'); ?>
                </a>
            </div>
        </section>

        <p class="wpacu-lvp-footnote">
            <?php esc_html_e('Feature availability can evolve. Keep this comparison synchronized with the exact Lite and Pro releases being distributed.', 'wp-asset-clean-up'); ?>
        </p>
    </main>
</div>

<script>
    /* global window, document */
    (function () {
        'use strict';

        var root = document.getElementById('wpacu-lite-vs-pro-page-wrap');

        if (!root) {
            return;
        }

        root.classList.add('has-js');

        var reducedMotion = false;

        if (window.matchMedia) {
            reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        /*
         * In-page navigation
         */
        var navigation = root.querySelector('[data-wpacu-lvp-nav]');
        var navigationLinks = navigation ? navigation.querySelectorAll('a[href^="#wpacu-lvp-"]') : [];
        var sections = [];

        Array.prototype.forEach.call(navigationLinks, function (link) {
            var targetId = link.getAttribute('href').slice(1);
            var target = document.getElementById(targetId);

            if (target) {
                sections.push({
                    id: targetId,
                    element: target,
                    link: link
                });
            }

            link.addEventListener('click', function (event) {
                var destination = document.getElementById(targetId);

                if (!destination) {
                    return;
                }

                event.preventDefault();

                destination.scrollIntoView({
                    behavior: reducedMotion ? 'auto' : 'smooth',
                    block: 'start'
                });

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', '#' + targetId);
                }
            });
        });

        function setActiveNavigationLink(activeId) {
            Array.prototype.forEach.call(navigationLinks, function (link) {
                var isActive = link.getAttribute('href') === '#' + activeId;

                link.classList.toggle('is-active', isActive);

                if (isActive) {
                    link.setAttribute('aria-current', 'location');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        }

        if ('IntersectionObserver' in window && sections.length) {
            var visibleSections = {};
            var observer = new window.IntersectionObserver(function (entries) {
                Array.prototype.forEach.call(entries, function (entry) {
                    visibleSections[entry.target.id] = entry.isIntersecting ? entry.intersectionRatio : 0;
                });

                var activeSection = null;
                var activeRatio = -1;

                sections.forEach(function (section) {
                    var ratio = visibleSections[section.id] || 0;

                    if (ratio > activeRatio) {
                        activeRatio = ratio;
                        activeSection = section;
                    }
                });

                if (activeSection && activeRatio > 0) {
                    setActiveNavigationLink(activeSection.id);
                }
            }, {
                root: null,
                rootMargin: '-18% 0px -65% 0px',
                threshold: [0, 0.1, 0.25, 0.5, 0.75]
            });

            sections.forEach(function (section) {
                observer.observe(section.element);
            });
        } else if (sections.length) {
            var ticking = false;

            window.addEventListener('scroll', function () {
                if (ticking) {
                    return;
                }

                ticking = true;

                window.requestAnimationFrame(function () {
                    var activeSection = sections[0];
                    var closestDistance = Number.POSITIVE_INFINITY;

                    sections.forEach(function (section) {
                        var distance = Math.abs(section.element.getBoundingClientRect().top - 130);

                        if (distance < closestDistance) {
                            closestDistance = distance;
                            activeSection = section;
                        }
                    });

                    setActiveNavigationLink(activeSection.id);
                    ticking = false;
                });
            }, { passive: true });
        }

        if (sections.length) {
            var initialHash = window.location.hash ? window.location.hash.slice(1) : '';
            var initialSectionExists = sections.some(function (section) {
                return section.id === initialHash;
            });

            setActiveNavigationLink(initialSectionExists ? initialHash : sections[0].id);
        }

        /*
         * FAQ accordion
         */
        var faqItems = Array.prototype.slice.call(root.querySelectorAll('.wpacu-lvp-faq'));

        function WpacuLvpFaq(details) {
            this.details = details;
            this.summary = details.querySelector('summary');
            this.content = details.querySelector('.wpacu-lvp-faq-content');
            this.animation = null;
            this.isClosing = false;
            this.isExpanding = false;

            if (!this.summary || !this.content) {
                return;
            }

            this.summary.addEventListener('click', this.onClick.bind(this));
        }

        WpacuLvpFaq.prototype.cancelAnimation = function () {
            if (!this.animation) {
                return;
            }

            this.animation.onfinish = null;
            this.animation.oncancel = null;
            this.animation.cancel();
            this.animation = null;
        };

        WpacuLvpFaq.prototype.onClick = function (event) {
            event.preventDefault();

            if ((this.details.open && !this.isClosing) || this.isExpanding) {
                this.close();
            } else {
                this.open();
            }
        };

        WpacuLvpFaq.prototype.open = function () {
            var self = this;

            faqControllers.forEach(function (other) {
                if (other !== self && (other.details.open || other.isExpanding)) {
                    other.close();
                }
            });

            this.cancelAnimation();
            this.isClosing = false;

            if (reducedMotion || typeof this.details.animate !== 'function') {
                this.details.open = true;
                this.finish(true);
                return;
            }

            var startHeight = this.details.offsetHeight;

            this.isExpanding = true;
            this.details.classList.remove('is-closing');
            this.details.classList.add('is-animating', 'is-expanding');
            this.details.style.height = startHeight + 'px';
            this.details.open = true;

            window.requestAnimationFrame(function () {
                var endHeight = self.summary.offsetHeight + self.content.offsetHeight;

                self.animation = self.details.animate(
                    { height: [startHeight + 'px', endHeight + 'px'] },
                    {
                        duration: 360,
                        easing: 'cubic-bezier(.22, 1, .36, 1)'
                    }
                );

                self.animation.onfinish = function () {
                    self.finish(true);
                };

                self.animation.oncancel = function () {
                    self.isExpanding = false;
                };
            });
        };

        WpacuLvpFaq.prototype.close = function () {
            var self = this;

            if (!this.details.open && !this.isExpanding) {
                return;
            }

            this.cancelAnimation();
            this.isClosing = true;
            this.isExpanding = false;

            if (reducedMotion || typeof this.details.animate !== 'function') {
                this.details.open = false;
                this.finish(false);
                return;
            }

            var startHeight = this.details.offsetHeight;
            var endHeight = this.summary.offsetHeight;

            this.details.classList.remove('is-expanding');
            this.details.classList.add('is-animating', 'is-closing');

            this.animation = this.details.animate(
                { height: [startHeight + 'px', endHeight + 'px'] },
                {
                    duration: 300,
                    easing: 'cubic-bezier(.4, 0, .2, 1)'
                }
            );

            this.animation.onfinish = function () {
                self.finish(false);
            };

            this.animation.oncancel = function () {
                self.isClosing = false;
            };
        };

        WpacuLvpFaq.prototype.finish = function (isOpen) {
            this.details.open = isOpen;
            this.details.classList.remove('is-animating', 'is-expanding', 'is-closing');
            this.details.style.height = '';
            this.isClosing = false;
            this.isExpanding = false;
            this.animation = null;
        };

        var faqControllers = faqItems.map(function (details) {
            return new WpacuLvpFaq(details);
        });

        /*
         * Comparison group controls
         */
        var groups = root.querySelectorAll('[data-wpacu-lvp-group]');
        var expandAllButton = root.querySelector('[data-wpacu-lvp-expand-all]');
        var collapseAllButton = root.querySelector('[data-wpacu-lvp-collapse-all]');

        function updateGroupControlStates() {
            var total = groups.length;
            var openCount = 0;

            Array.prototype.forEach.call(groups, function (group) {
                if (group.open) {
                    openCount += 1;
                }
            });

            if (expandAllButton) {
                expandAllButton.disabled = total > 0 && openCount === total;
                expandAllButton.setAttribute('aria-disabled', expandAllButton.disabled ? 'true' : 'false');
            }

            if (collapseAllButton) {
                collapseAllButton.disabled = openCount === 0;
                collapseAllButton.setAttribute('aria-disabled', collapseAllButton.disabled ? 'true' : 'false');
            }
        }

        Array.prototype.forEach.call(groups, function (group) {
            group.addEventListener('toggle', updateGroupControlStates);
        });

        if (expandAllButton) {
            expandAllButton.addEventListener('click', function () {
                Array.prototype.forEach.call(groups, function (group) {
                    group.open = true;
                });

                updateGroupControlStates();
            });
        }

        if (collapseAllButton) {
            collapseAllButton.addEventListener('click', function () {
                Array.prototype.forEach.call(groups, function (group) {
                    group.open = false;
                });

                updateGroupControlStates();
            });
        }

        updateGroupControlStates();
    }());
</script>
