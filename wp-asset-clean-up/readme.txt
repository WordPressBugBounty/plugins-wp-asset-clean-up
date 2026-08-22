=== Asset CleanUp: Page Speed Booster ===
Contributors: gabelivan
Tags: unused css, critical css, page speed, minify css, minify javascript
Requires at least: 4.7
Tested up to: 7.1
Stable tag: 1.4.0.5
Requires PHP: 5.6
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Speed up your WordPress site by unloading unnecessary CSS and JavaScript, reducing page bloat, and managing your own Critical CSS per page type.

== Description ==

WordPress themes and plugins often load CSS and JavaScript on pages that do not use them. A contact form may load on the homepage, a slider may load on a plain article, or store-related files may load outside the shop.

Asset CleanUp shows you what each page loads and lets you unload what is not needed. This can reduce HTTP requests, page weight, and browser work while complementing your existing caching setup.

Use Test Mode to validate changes as an administrator before they affect visitors.

**Asset CleanUp gives you precise, testable control over every optimization.**

= A safer workflow, without giving up control =

The Dashboard keeps the technical detail experienced users need while guiding first-time users through **Getting Started**, **Test Mode**, and the central **Overview** page. Test changes before they affect visitors, then review and manage existing or leftover rules from one place.

= CSS and JavaScript management =

* View loaded stylesheets and scripts, organized by their source.
* Unload unnecessary CSS and JavaScript on the homepage and on individual posts, pages, and custom post types.
* Manage assets from the Dashboard, the post/page edit screen, or the front-end view.
* Review unload rules, exceptions, asset attributes, page options, and leftover entries from the Overview page.
* Minify and combine supported CSS and JavaScript, including supported inline code.
* Preload selected CSS and JavaScript files when they need to be discovered earlier.

= Granular manual Critical CSS management =

Add, edit, and remove your own Critical CSS directly from the Dashboard. Keep manual CSS organized by supported page context instead of maintaining one large global block.

Asset CleanUp does not automatically generate Critical CSS. It gives you a structured way to manage CSS that you prepare yourself.

= Fonts and resource loading =

* Combine, preload, asynchronously load, or remove Google Fonts requests, with `font-display` and preconnect controls.
* Preload local font files and apply `font-display`.
* Add `fetchpriority`, `loading`, and `decoding` attributes to images using simple matching or regular expressions.
* Lazy-load images where appropriate.

= WordPress cleanup options =

* Remove unused WordPress features such as Emojis, Dashicons for guests, and Comment Reply.
* Remove selected discovery links, metadata, and oEmbed resources from the document HEAD.
* Disable RSS feed links or feeds when they are not needed.
* Restrict or disable XML-RPC when your site does not rely on it.
* Strip supported HTML comments, with exceptions where required.

= Works with your caching setup, it does not replace it =

Asset CleanUp is not a page-caching plugin. It controls what WordPress outputs; a page cache, server cache, or CDN can then store and deliver the optimized result.

After changing rules, clear every active cache layer. Avoid enabling the same minify or combine feature in multiple optimization plugins at the same time.

= Asset CleanUp Lite and Pro =

Lite includes page-level asset management, Test Mode, manual Critical CSS for supported contexts, resource-loading controls, and WordPress cleanup options.

**Go beyond individual CSS and JavaScript files with the Plugins Manager.**

Lite includes an interactive preview of the **Plugins Manager** (rules can be explored, but saved only in Pro). In Pro, it can prevent entire plugins from running on frontend or Dashboard pages where they are not needed, not merely remove their CSS and JavaScript. This can reduce PHP work, database queries, and potential conflicts before the page is generated.

[Asset CleanUp Pro](https://www.gabelivan.com/items/wp-asset-cleanup-pro/?utm_source=wp_org_lite&utm_medium=description&utm_campaign=lite_vs_pro) also adds broader conditional rules, hardcoded asset management, device-specific rules, and advanced JavaScript attribute and placement controls.

= Documentation and support =

* Read the [Asset CleanUp documentation](https://www.assetcleanup.com/docs/).
* Ask a question in the [WordPress.org support forum](https://wordpress.org/support/plugin/wp-asset-clean-up/).

= External Services and Privacy =

**WordPress.org plugin icon service**

When an authorized administrator opens an Asset CleanUp Dashboard screen and the local plugin-icon cache is missing or incomplete, Asset CleanUp may request public plugin information from `https://api.wordpress.org/plugins/info/1.2/`. The request is used only to retrieve icons for active plugins displayed inside Asset CleanUp. It can include the corresponding WordPress.org plugin slugs and standard HTTP metadata, including the server IP address. Asset CleanUp does not intentionally include the site URL, administrator details, or site content. See the [WordPress.org Privacy Policy](https://wordpress.org/about/privacy/).

**Google Fonts preload audit**

When an authorized administrator explicitly runs the Google Fonts preload audit, Asset CleanUp may request discovered Google Fonts stylesheets from `https://fonts.googleapis.com/` and process font-file URLs from `https://fonts.gstatic.com/`. Requests can include the stylesheet URL and its font-family, variant, subset, or `text` parameters; the browser user-agent used by the audit; and standard HTTP metadata, including the server IP address. These requests are made only as part of the administrator-initiated audit. See the [Google Privacy Policy](https://policies.google.com/privacy).

The following two Asset CleanUp-operated services are optional. Both are disabled by default and require an administrator to opt in from `Asset CleanUp > Settings > Plugin Usage Preferences`.

**Dashboard announcements**

When an administrator explicitly enables announcements, Asset CleanUp periodically requests the Lite announcements JSON feed from `https://drm6aghn7w1h8.cloudfront.net/_wpacu-lite-announcements.json`. The feed is used to show maintenance information, important update notices, optimization guides, and occasional product offers in the WordPress Dashboard. Asset CleanUp does not intentionally add the site URL, administrator details, or site content to this request. As with any HTTP request, the service receives the server IP address and standard HTTP metadata. When announcements are disabled, this feed is not requested.

**Optional usage tracking**

When an administrator explicitly enables usage tracking, Asset CleanUp sends an initial technical check-in and then no more than one check-in per week to `https://www.assetcleanup.com/tracking/?wpacu_action=checkin`. The payload can include the PHP, WordPress, and Asset CleanUp versions; Asset CleanUp settings; first-use and review-notice state; server software; multisite status; the active theme name and version; active and inactive plugin file identifiers; and the WordPress locale. The site URL, administrator name, and administrator email are not intentionally included in the tracking payload. Disabling the setting stops future check-ins.

The announcements and usage-tracking services are operated by the Asset CleanUp developer. See the [privacy policy](https://www.gabelivan.com/privacy-policy/). CloudFront infrastructure is provided by Amazon Web Services and is also subject to the [AWS Privacy Notice](https://aws.amazon.com/privacy/).

== Installation ==

= Install Asset CleanUp Lite =

1. In the WordPress Dashboard, go to `Plugins > Add New`.
2. Search for `Asset CleanUp: Page Speed Booster`.
3. Click `Install Now`, then `Activate`.
4. Open `Asset CleanUp > Getting Started` and follow the guided setup.

= Make your first optimization safely =

1. Go to `Asset CleanUp > Settings > Test Mode` and enable Test Mode. While it is active, your optimization rules are applied only to you as a logged-in administrator.
2. Open `Asset CleanUp > CSS & JS Manager` and select the page you want to optimize.
3. Start with an asset that is clearly unnecessary there, such as contact-form files on a page without a form.
4. Save the rule and test the page carefully on desktop and mobile. Check menus, forms, popups, sliders, and other interactive elements.
5. Clear page, server, and CDN caches. Disable Test Mode only after everything works correctly, then verify the page once more as a logged-out visitor.

You can also manage assets from the post/page edit screen or directly from the front end after enabling front-end management in the plugin settings.

= Manual installation =

1. Download the plugin ZIP file from WordPress.org.
2. Go to `Plugins > Add New > Upload Plugin` in the WordPress Dashboard.
3. Select the ZIP file, click `Install Now`, then `Activate`.
4. Open `Asset CleanUp > Getting Started`.

== Frequently Asked Questions ==

= Is Asset CleanUp a caching plugin? =

No. Asset CleanUp controls which CSS, JavaScript, and other optimizations WordPress outputs. It works best alongside a page cache, server cache, or CDN.

= Does Asset CleanUp automatically remove unused CSS or generate Critical CSS? =

No. You decide which assets to unload and provide any Critical CSS yourself. This keeps each optimization inspectable and under your control, but it also means that every change should be tested.

= Can Asset CleanUp improve Core Web Vitals or PageSpeed scores? =

It can help when unnecessary CSS or JavaScript contributes to transfer size, render blocking, or browser work. Results still depend on your theme, plugins, hosting, caching, media, third-party scripts, and configuration. No plugin can guarantee a particular score.

= Can unloading a CSS or JavaScript file break the page? =

Yes, if that file is required for the page's layout or functionality. Use Test Mode, make one meaningful change at a time, and test desktop, mobile, forms, navigation, popups, and other interactive elements before publishing the rule to visitors.

= I am not sure which assets to unload. Where should I start? =

Start with obvious candidates:

* Contact-form assets on pages without a form.
* Slider or gallery assets on pages without a slider or gallery.
* Store-related assets on pages outside the shop flow.

Avoid unloading WordPress core files or dependency files unless you understand what relies on them.

= Why are my changes not taking effect? =

The most common cause is caching. Clear plugin, server, browser, and CDN caches, then test as a logged-out visitor. Also check whether Test Mode is still enabled and whether another optimization plugin is applying the same type of rule.

See the [detailed troubleshooting guide](https://www.assetcleanup.com/docs/changes-applied-not-taking-effect/).

= Why is a CSS or JavaScript file missing from the manager? =

WordPress-enqueued assets are the easiest to detect and manage. Some files may be hardcoded, injected dynamically after the page loads, removed earlier by another plugin, or hidden by a cached response. Try another asset-retrieval method or the front-end manager. Advanced hardcoded asset management is available in Asset CleanUp Pro.

= Can I use Asset CleanUp with another performance plugin? =

Yes. Asset CleanUp is commonly used with caching plugins and CDN services. Do not enable the same minify, combine, or related optimization in more than one tool, because overlapping transformations can cause conflicts or make troubleshooting difficult.

= Does Asset CleanUp support WordPress Multisite? =

Yes. Each site can be configured independently.

== Screenshots ==

1. CSS & JS Manager — inspect the assets loaded on a page and unload unneeded CSS or JavaScript by page or site-wide.
2. Overview — review active unload rules, load exceptions, async/defer attributes, and stale entries in one place.
3. Manual Critical CSS — add and manage your own Critical CSS for individual pages and other WordPress contexts.
4. Getting Started — follow a guided, safety-first workflow from Test Mode to your first optimization.
5. Settings — configure asset retrieval, cleanup, Test Mode, and plugin behavior from clearly organized sections.

== Changelog ==
= 1.4.0.5 =
* **Added — Resource Loading:** Added automatic lazy loading for images. Location: "Settings" -> "Resource Loading" -> "Lazy Load". [Read more](https://www.assetcleanup.com/docs/?p=2403)
* **Added — Font Preload Audit:** Added a browser-assisted audit for manually preloaded Local and Google Fonts. The audit checks representative pages in desktop and mobile viewports, identifies duplicate, invalid or unnecessary site-wide preloads and provides conservative cleanup recommendations without removing the fonts themselves.
* **Added — Critical CSS:** Added a global control directly in the Critical CSS Manager to temporarily pause or resume all Critical CSS output while preserving the existing rules.
* **Added — Tools / Storage:** Added a read-only Database Map showing where Asset CleanUp stores settings, optimization rules, metadata, transients and Pro-specific data.
* **Added — Tools / Uninstall:** Added a dedicated, explicitly confirmed action for removing all Asset CleanUp data before uninstalling the plugin, including settings, optimization rules, metadata, transients, license data and generated cache files.
* **Added — Overview:** Added Edit Mode, allowing unload/load rules, load exceptions, script attributes, Plugins Manager rules, page options, Critical CSS and other stored settings to be cleared from one central location.
* **Added — Overview:** Added detection and removal of leftover rules, including entries associated with inactive or deleted assets and plugins, as well as previously stored Pro-only settings that are no longer relevant.
* **Added — CSS/JS Manager Preview:** Added read-only Dashboard previews for categories, tags, custom taxonomies, author archives, date archives, search results, 404 pages and custom post type archives. Assets loaded on real URLs can be inspected, while the corresponding rule controls remain available only in Pro.
* **Improved — Resource Loading:** Improved how the `fetchpriority`, `loading`, and `decoding` attributes are applied to images and reorganized the stored image-attribute data. If you upgrade and later downgrade to an earlier plugin version, image-attribute rules might need to be configured again. [Read more](https://www.assetcleanup.com/docs/?p=2279)
* **Improved — Resource Loading:** Hardened image attribute and lazy-loading rule validation, improved compatibility with rules saved by older versions and fixed edge cases where attributes such as `src` could be confused with `data-src`.
* **Improved — JavaScript Optimization:** Improved handling of modern JavaScript, including module/nomodule scripts and scripts using integrity, nonce, crossorigin or referrerpolicy attributes, preventing unsafe combine or inline transformations.
* **Improved — Settings and CSS/JS Manager:** Added unsaved-change counters and persistent save areas, making modified settings and asset rules easier to review before saving.
* **Improved — Overview:** Added quick section navigation, an optional sticky navigation bar, back-to-navigation links and visibility for Plugins Manager rules whose targeted plugins are inactive or not installed.
* **Improved — Admin Bar:** Added hover details for unloaded CSS/JS assets and plugins, showing the matched unload rule and its relevant value when available. Nested submenu hover behavior was also refined to reduce accidental closing.
* **Improved — Critical CSS:** Manual Critical CSS can now be managed with granular page-type coverage, including the homepage, individual posts, pages, custom posts, media attachment pages, taxonomy terms, author archives, date archives, search results, 404 pages and custom post type archives.
* **Improved — Plugins Manager Preview:** Rebuilt the read-only frontend and Dashboard previews around the current Pro interface, including a Compact Grid presentation, plugin search and highlighting, rule/status counters, grouped plugin states and expand/collapse controls. The available Grouped and Classic layouts are also presented, while Pro-only settings remain protected from being saved in Lite.
* **Improved — Plugins Manager Preview:** Preserved Pro rules are now displayed with readable post titles, taxonomy term names and author names after downgrading to Lite, with clear fallbacks for deleted content.
* **Improved — Plugin Icons:** Improved WordPress.org plugin icon fetching and cache recovery so missing icons can be downloaded again instead of remaining permanently replaced by the default icon.
* **Improved — Admin Experience:** Major UI/UX refresh across Settings, Help, Getting Started, Critical CSS and multiple vertical and horizontal tab sections, including clearer layouts, spacing, typography, accessibility and content organization.
* **Improved — Interface Controls:** Added a choice between the enhanced WPACU controls and standard browser-native form controls across supported admin areas.
* **Improved — Dashboard Reliability:** Improved AJAX validation, authorization and failure recovery across several Dashboard actions, including safer handling of invalid, expired or interrupted requests.
* **Improved — Tools / Storage:** Added a detailed overview of generated CSS/JavaScript storage, including directory paths, file counts, disk usage, write status and filtering between optimized assets and supporting files.
* **Improved — Tools / Debugging:** Redesigned the troubleshooting area with clearer diagnostic modes, copyable test URLs and protected PHP error-log downloads.
* **Improved — Tools / Reset:** Added more granular reset options for Critical CSS, previously stored Plugins Manager rules and all plugin data except Settings, with clearer confirmation, deletion summaries and partial-failure reporting.
* **Improved — Tools / Uninstall:** Cleanup results are shown on the Plugins page and partial filesystem failures are reported instead of returning a false success. Plugin settings are not recreated after cleanup.
* **Improved — Dashboard CSS/JS Manager:** When a selected URL redirects to another allowed internal URL, explicit confirmation is now required before loading assets from the final destination. The redirected URL remains visible after loading. Redirects from non-homepage contexts to the homepage remain blocked.
* **Changed — Rule Matching:** Reworked internal RegEx handling and replaced the previous third-party RegEx library with a dedicated lightweight class. This reduces the plugin package size while retaining support for plain-text rules, RegEx rules and existing legacy patterns.
* **Fixed — Dashboard CSS/JS Manager:** No longer reports a false external redirect or fails to fetch assets when WordPress runs on a non-standard port (e.g. localhost:8888).
* **Fixed — Import/Export:** The "Everything" option now includes all supported rules and Critical CSS data.
* **Fixed — Import/Export:** Legacy or malformed array-based settings are normalized safely during import, allowing the remaining valid settings from older JSON exports to continue importing.
* **Fixed — Overview:** Prevented PHP warnings when saved rules reference deleted or unavailable posts, users or taxonomy terms, while keeping the remaining Overview data available.
* **Fixed — Settings:** Prevented undefined-setting warnings after upgrading from older versions and corrected the Google Fonts sub-tab controls and font preload safety notice when full Google Fonts removal is active.
* **Fixed — Upgrade Compatibility:** Recently introduced access-control settings now receive their correct disabled or empty-list defaults when upgrading from older versions, preventing blank fields or unexpectedly enabled checkboxes.
* **Compatibility — WordPress 7.0:** Refined admin styling to remain consistent with recent WordPress 7.0 layout changes.
* **Compatibility — Lite and Pro:** Prevented the Lite version constant from overriding the Pro version when Lite remains dormant.
* **Performance — Admin Assets:** The main Asset CleanUp admin stylesheet now loads only on plugin screens and supported editing screens instead of every WordPress Dashboard page.
* **Performance:** Reduced expensive processing during updates and moved stale asset-information cleanup to a scheduled maintenance task, particularly benefiting multilingual sites and installations with many saved rules.
* **Maintenance:** Reorganized Critical CSS and Plugins Manager styles.
* **Maintenance — Composer:** Corrected the Lite package identity in Composer's generated installation metadata.
* **Privacy — Dashboard Announcements:** Remote announcements are now disabled by default and require an explicit administrator opt-in.
* **Privacy — External Services:** Documented the announcements feed and optional usage tracking, including the data and HTTP metadata involved.
* **Security — Dashboard CSS/JS Manager:** Hardened asset retrieval against SSRF, including validation of AJAX-loaded URLs, redirect destinations, hosts, credentials and alternate ports. Remote DOM retrieval now uses `wp_safe_remote_post()`.
* **Security:** Hardened CSS optimization HTML cleanup so temporary WPACU attributes cannot affect user-controlled markup.

= 1.4.0.4 =
* **New:** Resource Loading – Control how images are loaded by adding attributes such as "fetchpriority", "loading" and "decoding" based on custom rules (supports simple matching and RegEx) / read more: https://www.assetcleanup.com/docs/?p=2279
* Improved asset minification stability (library update)
* **Security:** Improvement for method "clearItemStorageForPost" within "OptimizeCommon": Patchstack reported it as vulnerable to Broken Access Control
* **"Plugin Announcement" area:** Make sure it slides up when it's closed; The font-size has been increased; Fix: "Uncaught TypeError: usort(): Argument #1 ($array) must be of type array, string given in [...]/classes/Admin/PluginAnnouncements.php:492"
* **Multisite extra compatibility:** The structure of the caching directory has been updated, and now each site has its own directory. When the cache is cleared, it will only scan the cached CSS/JS files belonging to the current website, thus saving resources and not affecting in any way the files belonging to other websites. If a switch is made from a single site to a multisite, the structure will be updated and all cache files will be re-created, while the older ones will be cleared after 30 days (to ensure no static cached HTML pages are still referencing them).
* **Hardcoded assets:** Highlight the ones belonging to the 'Slider Revolution' plugin to make them easier to identify.
* **Optimize JS:** When the WordPress core file "wp-i18n" is unloaded, the SCRIPT tag is replaced with another tag (much lighter) keeping "setLocaleData" and other functions active to avoid JS errors being shown in the console (ideal for websites with just one language)
* **Optimize JS:** When a JS asset is unloaded and the option 'Ignore dependency rule and keep the "children" loaded' is used, make sure the inline JS associated with the asset is also cleared to avoid any JS errors in the console or even broken page functionality
* **Improvement:** Detect an attribute found in a tag without using RegEx (for faster PHP processing)
* **Fix:** When using the "Direct" assets retrieval method, any unloaded plugins for the homepage, would not show up as unloaded in the CSS/JS manager, confusing the admin that the rules might not be applied
* **Fix:** Avoid errors such as the following (in case plugin functions are called too early by external code): 'Asset CleanUp's object cache is not valid (from method "WpAssetCleanUp\ObjectCache::wpacu_cache_get").'

= 1.4.0.3 =
* **Fix:** "CSS & JS Manager" -- "Manage CSS/JS" -- "Custom Taxonomy" was not showing the guiding information
* **Fix:** When managing CSS/JS in the front-end view, plugin's core JS file was not loading, causing some lack of functionality in the CSS/JS manager from the bottom of the page

= 1.4.0.2 =
* **WPML compatibility (it works with other similar plugins as well):** Make sure that whenever CSS/JS manager is used in the Dashboard, if the domain/subdomain is different (e.g. es.domain.com instead de.domain.com), the assets will be fetched without getting blocked by the browser's CORS policy
* **Improvement for plugin's JavaScript files:** The main "script" file was split into two files, one containing the most common code that clears the caching and it's used on many pages (e.g. when clicking the clear caching link from the top admin bar), which weighs around 11% in comparison with the other file; This way, on many pages, less JavaScript code is loaded, thus reducing bandwidth for admin visits, and eliminating any potential conflicts with other JS files belonging to other plugins
* **Improvement (admin area):** Prevent SweetAlert and extra CSS from loading if there's no CSS/JS manager loaded (e.g. edit post area)
* **Google Fonts Combine Fix:** W3C Validator / 'Error: Bad value for attribute href on element link: Illegal character in query: | is not allowed.' - The character '|' is replaced with '%7C'
* **Fix:** "Warning realpath(): open_basedir restriction in effect. File(/) is not within the allowed path(s):" - Extra checks are made to be sure that the error won't be printed when features such as minify CSS are used
* **Fix:** "Bulk Changes" / When removing unload rules, a PHP error was showing up preventing the rule from being removed

= 1.4.0.1 =
* **New Feature For The Admin:** "Settings" -- "Plugin Usage Preferences" -- "Announcements" / The admin would be notified within the Dashboard (if he/she prefers) of critical updates, new features, usage tips, special offers / read more: https://www.assetcleanup.com/docs/?p=1946
* Make sure plugin generated STYLE/SCRIPT inline tags (e.g. from features such as "Inline CSS") have the "type" attribute (unless the theme supports HTML5) / read more: https://www.assetcleanup.com/docs/?p=2086
* Moved "CSS/JS Cache" tab into the "CSS/JS Manager" one and grouped options within the 'CSS/JS Manager' (for better readability)
* **Fix / Notice:** "Function _load_textdomain_just_in_time was called incorrectly. Translation loading for the 'wp-asset-clean-up' domain was triggered too early."

= 1.4 =
* **Fix - Error message:** Uncaught TypeError: in_array(): Argument #2 ($haystack) must be of type array, string given in [...]/templates/_admin-page-settings-plugin-areas/_plugin-usage-settings/_access.php:43
* **Fix - PHP Deprecated:** trim(): Passing null to parameter #1 ($string) of type string is deprecated in [...]/classes/OptimiseAssets/OptimizeCommon.php on line 903
* **Fix - PHP Warning:** Undefined global variable $wpassetcleanup_external_srcs_ref
* Updated the external links to the help pages

= 1.3.9.9 =
* **Fix:** Server Side Request Forgery (SSRF) has been discovered on an AJAX call within the CSS/JS manager; New parameters were added to the call to avoid any unsanitized input

= 1.3.9.8 =
* **Fix:** Avoid deprecated PHP notice if PHP version >= 8.1; A "null" parameter was passed to the native WordPress function add_submenu_page(), instead of an empty string ''
* **Fix:** When using Query Monitor, the "Update" button from the CSS/JS manager was showing up on top of the bottom Query Monitor data

= 1.3.9.7 =
* CSS assets can now be preloaded asynchronously via the CSS/JS Manager. [Read more](https://www.assetcleanup.com/docs/?p=202#preload-async-css)
* Reduce the total number of SQL queries used to obtain information
* Stop triggering PHP code and SQL queries on pages where they are not relevant
* Cache SQL queries that are time consuming, which is ideal for websites with a very large database (e.g. tens / hundred of thousands of users)

= 1.3.9.6 =
* **Fix:** The "usermeta" table is populated with duplicate entries, leading to a larger database, and sometimes, leading to a high CPU usage

= 1.3.9.5 =
* **New Option:** "Settings" -- "Plugin Usage Preferences" -- "Plugin Access" / Choose user roles or particular users, apart from administrators, that could have access to the plugin area / e.g. the admin could give Asset CleanUp Pro access within the Dashboard to a developer that is optimizing the website, but the developer does not have the "administrator" role for security reasons
* "wpacu_access_role" filter is no longer active (related to the option mentioned above), as it wasn't 100% effective into changing who accesses the Asset CleanUp Pro area
* "Nextend Social Login and Register" plugin compatibility / Make sure the homepage is still detected if the following query string is in the URI: "nsl_bypass_cache"
* **Fix:** When oEmbed is disabled, make sure the REST route is also inactive
* **Fix:** When the plugin's main menu is hidden from the left sidebar, make sure the following option stays selected whenever a plugin page is accessed: "Settings" -- "Asset CleanUp"

= 1.3.9.4 =
* Option to manage critical CSS (in "CSS & JS Manager" » "Manage Critical CSS") from the Dashboard (add/update/delete), while keeping the option to use the "wpacu_critical_css" hook for custom/singular pages
* **Preload CSS feature:** When a .css file is preloaded (Basic), the "media" attribute is preserved if it's not missing and different than "all"
* **Hardcoded assets' sorting:** The assets are now sorted based on the option chosen in "Assets List Layout:" (e.g. if you sort them by their size, you can view the hardcoded assets from the largest one to the the smallest)
* **"GTranslate" plugin compatibility:** The JavaScript handle starting from "gt_widget_script_" and having a random number on each page reload gets an alias ("gt_widget_script_gtranslate") to avoid misinterpretation that the asset is a different one on each page reload (this way it could be unloaded, preloaded, etc.)
* **Combined CSS/JS:** Whenever a file from a plugin or a theme is updated by the developer/admin, there's no need to clear the cache afterwards, as sometimes, users forget about this; the plugin automatically recognizes the change and a new combined CSS/JS is created and re-cached
* **CSS/JS manager:** When the "src" of a SCRIPT tag or "href" of a LINK tag starts with "data:text/javascript;base64," and "data:text/css;base64," respectively, a note will be shown with the option to view the decoded CSS/JS code
* If the menu from the sidebar is not showing up, make sure that "Asset CleanUp" from "Settings" (Dashboard sidebar) is always highlighted, whenever a plugin page is visited
* **Improvement:** When using specific themes, the navigation sub-tabs from the "CSS & JS Manager" were overwritten by the theme's style (added unique references to the HTML classes)
* **Improvement:** Make sure the red background is kept whenever a load exception is unchecked if there was already an unloading rule set (this is more for aesthetics reasons)
* **Improvement:** Backend Speed - The plugin processes its PHP code faster, thus reducing the total processing time by ~50 milliseconds for non-cached pages (e.g. backend speed testing plugins such as "Query Monitor" and "Code Profiler" were used to optimize the PHP code)
* **Improvement:** CSS Minifier - Specific "var()" statements were minified incorrectly in Bootstrap / more: https://github.com/matthiasmullie/minify/issues/422
* **Improvement:** Added the option to change the way the assets are retrieved ("Direct" as if the admin is visiting the page /  "WP Remote POST" as if a guest is visiting the page) from the CSS & JS manager within the Dashboard (for convenience, to avoid going through the "Settings" as it was the case so far)
* **Improvement:** Higher accuracy in detecting the "type" and "data-alt-type" attribute before determining if an inline SCRIPT tag has to be minified
* **Improvement:** In very rare cases in the "options" table, if "page_on_front" has a value and "show_on_front" is set to "posts" (this happens when there's an incomplete update of the settings in the database), it will confuse Asset CleanUp Pro and consider that "Your homepage displays" is actually set to "A static page" which is wrong
* **Improvement:** The plugin is optimised to load fewer functions than before (e.g. PHP classes that aren't required on the targeted page) in order to reduce the total front-end optimization time
* **Improvement:** Removed unused PHP code from specific files
* **Improvement:** CSS/JS Minifier - Prevent calling @is_file() when it's not the case to avoid on specific environments errors such as: "is_file(): open_basedir restriction in effect"
* **Improvement:** Whenever the following option is enabled, the META generator tags are stripped faster after being cached: 'HTML Source CleanUp' -- 'Remove All "generator" meta tags?'
* **Improvement:** Apply "font-display:" CSS property for Google Fonts when they are loaded via Web Font Loader (source: https://github.com/typekit/webfontloader)
* **Rank Math & other SEO plugins compatibility:** Prevent Asset CleanUp Pro from triggering, thus saving extra resources, whenever URIs such as /sitemap_index.xml are loaded to avoid altering the XML structure or generate 404 not found errors
* **"WooCommerce" plugin compatibility:** Avoid using extra resources in Asset CleanUp Pro to process specific CSS files (they are loading after the latest WooCommerce plugin release) that are already minified
* **"SiteGround Optimizer" plugin compatibility:** When enabled, on some environments, errors are triggering if Asset CleanUp's JavaScript minify option is turned on
* **"GiveWP" plugin compatibility:** Prevent Asset CleanUp Pro from loading whenever the URI is like /give/donation-form?giveDonationFormInIframe=1 as the page loaded within the iFrame is already optimized and there are users that had problems when Asset CleanUp Pro was triggering its rules there
* **"GiveWP" plugin compatibility:** Prevent CSS/JS minification as the files are already optimized and there's no point in wasting extra resources
* **"Settings":** Replaced text that sometimes caused confusion (e.g. some people didn't notice the small "if" and thought their caching directory is not writable)
* **"Settings" -- "Plugin Usage Preferences":** Re-organised the tab contents into multiple sub-tabs for easier access and understanding the options
* **"Settings" -- "Plugin Usage Preferences" - "Do not load on specific pages":** "Prevent features of Asset CleanUp Pro from triggering on specific pages"; This allows you to stop triggering specific plugin features on certain pages (e.g. you might want to prevent combining JavaScript files on all /product/ (WooCommerce) pages due to some broken functionality on those specific pages)
* **Fix:** In some environments, the tags with "as" attribute were not properly detected (e.g. when "DOMDocument" is not enabled by default in the PHP configuration)
* **Fix:** Sometimes the "src" value was detected incorrectly on hardcoded assets due to the fact that the string "src=" was inside document.write() within the <SCRIPT> tags (which had no "src" attribute at all) / e.g. <script type="text/javascript">console.log('test'); document.write('<scri' + 'pt src="//path-to-specific-file.js"></sc' + 'ript>');</script>
* **Fix:** When "WP Remote Post" was used as a fetch method of the CSS/JS assets within the Dashboard, information about the targeted URL was showing up twice (e.g. the admin could be confused of viewing redundant text printing out)
* **Fix:** Make sure 'post__in' is never empty when called within a WP_Query whenever a post search is made within "CSS & JS Manager" -- "Manage CSS/JS"
* **Fix:** On some environments, FS_CHMOD_DIR and FS_CHMOD_FILE weren't defined, triggering errors such as: Uncaught Error: Undefined constant "WpAssetCleanUp\FS_CHMOD_DIR"
* **Fix:** In specific environments that loaded similar code to the one from Asset CleanUp Pro, errors were showing up, thus more uniqueness had to be added to avoid conflicts such as unique PHP namespaces
* **Fix:** On some environments, the following error would show up when WP CLI is used: "PHP Fatal error: Uncaught Error: Call to a member function getScriptAttributesToApplyOnCurrentPage() on null"
* **Fix:** Combined CSS/JS - The preload and stylesheet LINK tags had the same "id" attribute which shouldn't be like that as the "id" should be unique for each HTML element
* **Fix:** After a theme is switched, there's sometimes a browser error showing up related to multiple failed redirects

= 1.3.9.3 =
* **WordPress 6.3 compatibility:** Updated the code to avoid the following notice: "Function WP_Scripts::print_inline_script is deprecated since version 6.3.0"
* **"WPML Multilingual CMS" plugin compatibility:** Syncing post changes on all its associated translated posts / e.g. if you unload an asset on a page level in /contact/ (English) page, it will also be unloaded (synced) in /contacto/ (Spanish) and /kontakt/ (German) pages
* **"WP Rocket" plugin compatibility:** "Settings" -- "Optimize JavaScript" -- "Combine loaded JS (JavaScript) into fewer files" is automatically disabled when the following option is turned on in "WP Rocket": "File Optimization" -- "JavaScript Files" -- "Delay JavaScript execution"
* **"Hide My WP Ghost – Security Plugin" plugin compatibility:** Asset CleanUp's HTML alteration is done before the one of the security plugin so minify/combine CSS/JS will work fine
* **"Site Kit by Google" plugin compatibility:** JavaScript files from this plugin are added to the ignore list to avoid minifying as they are already minified (with just a few extra comments) and minifying them again, due to their particular structure, resulted in JS errors in the browser's console
* **Improvement:** Changed the name of the cached files to make them more unique as sometimes, handles that had UNIX timestamps and random strings (developers use them for various reason, including debugging), were causing lots of redundant files to be generated in the assets' caching directory
* Added jQuery Migrate script to the ignore list to avoid minifying it (along with jQuery leave it as it is, if the developer decided to load the large versions of the files, for debugging purposes)
* **Front-end view:** In the "Asset CleanUp" top admin bar menu, a new link is added that goes directly to the manage CSS/JS area for the current visited page for convenience
* Remove the usage of "/wp-content/cache/storage/_recent_items" directory from the CSS/JS caching directory as it was redundant to the caching functionality
* Option to skip "Cache Enabler" cache clearing via using the "WPACU_DO_NOT_ALSO_CLEAR_CACHE_ENABLER_CACHE" constant (e.g. set to 'true' in wp-config.php) - read more: https://www.assetcleanup.com/docs/?p=1502#wpacu-cache-enabler
* **"Knowledge Base for Documents and FAQs" plugin:** Do not show the CSS/JS manager at the bottom of the page when "Edit KB Article Page" is ON
* **New "Brizy - Page Builder" setup:** Prevent Asset CleanUp from triggering when the editor is ON
* **Fix:** "Do not load Asset CleanUp on this page (this will disable any functionality of the plugin)" - if turned ON, make sure the hardcoded list loads fine in the front-end view (Manage CSS/JS)
* **Fix:** Use the same "chmod" values from FS_CHMOD_DIR and FS_CHMOD_FILE (WordPress constants) for all the files and directories from the assets' caching directory when attempting to create a file/directory to avoid permission errors on specific environments

= 1.3.9.2 =
* **New Option:** Contract / Expand All Assets within an area (e.g. from a plugin)
* **"Overview" area:** Added notifications about deleted posts, post types, taxonomies and users, making the admin aware that some rules might not be relevant anymore (e.g. the admin uninstalled WooCommerce, but unload rules about "product" post types or a specific product page remained in the database)
* Stopped using the "error" class (e.g. on HTML DIV elements) and renamed it to "wpacu-error" as some plugins/themes sometimes interfere with it (e.g. not showing the error at all, thus confusing the admin)
* **Keep the same strict standard for the values within the following HTML attributes:** "id", "for" to prevent any errors by avoiding any interferences with other plugins
* **Improvement:** Only print the notice (as an HTML comment) about the "photoswipe" unload to the administrator (it's a special case where the HTML has to be hidden in case the CSS file gets unloaded)
* **WPML Fix:** Prevent Asset CleanUp from triggering whenever /?wpml-app=ate-widget is loaded (in some environments, the content returned was empty and the automatic translation area was not loading)

= Previous versions =
Older releases: [View the complete changelog](https://plugins.trac.wordpress.org/export/3137290/wp-asset-clean-up/trunk/changelog.txt)
