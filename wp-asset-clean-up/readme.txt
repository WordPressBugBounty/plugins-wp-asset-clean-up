=== Asset CleanUp: Page Speed Booster ===
Contributors: gabelivan
Tags: minify css, minify javascript, defer css javascript, page speed, dequeue
Donate link: https://www.gabelivan.com/items/wp-asset-cleanup-pro/?utm_source=wp_org_lite&utm_medium=donate
Requires at least: 4.6
Tested up to: 6.7.1
Stable tag: 1.3.9.9
Requires PHP: 5.6
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Make your website load FASTER by stopping specific styles (.CSS) & scripts (.JS) from loading. It works best with a page caching plugin / service.

== Description ==
Don't just minify & combine CSS/JavaScript files ending up with large, bloated and slow loading pages: **Strip the "fat" first and get a faster website** :)

Faster page load = Happier Visitors = More Conversions = More Revenue

There are often times when you are using a theme and a number of plugins which are enabled and run on the same page. However, you don't need to use all of them and to improve the speed of your website and make the HTML source code cleaner (convenient for debugging purposes), it's better to prevent those styles and scripts from loading.

For instance, you might use a plugin that generates contact forms and it loads its assets (.CSS and .JS files) in every page of your website instead of doing it only in the /contact page (if that's the only place where you need it).

"Asset CleanUp" scans your page and detects all the assets that are loaded. All you have to do when editing a page/post is just to select the CSS/JS that are not necessary to load, this way reducing the bloat.

The plugin works best in combination with a cache plugin (e.g. WP Rocket, WP Fastest Cache, W3 Total Cache), a hosting company that offers packages with server-level caching available (e.g. WP Engine, Kinsta) or a service like Cloudflare that has page caching enabled.

= Main plugin's benefits include =
* Decreases the number of HTTP requests loaded and eliminate render-blocking resources (important for faster page load) by unloading useless CSS/JS
* Preload CSS/JS, Local Fonts & Google Fonts files to instruct the browser to download the chosen assets as soon as possible
* Minify CSS files (including inline code within STYLE tags)
* Minify JavaScript files (including inline code within SCRIPT tags)
* Combine remaining loaded CSS & JavaScript files
* Inline CSS Files (automatically & by specifying the path to the stylesheets)
* Defer combined JavaScript files by applying "defer" attribute to the SCRIPT tags
* Site-wide removal for Emojis, Dashicons for guest users and Comment Reply if they are not used
* Disable RSS Feeds
* Reduces the HTML code of the actual page (that's even better if GZIP compression is enabled)
* Makes source code easier to scan in case you're a developer and want to search for something
* Remove possible conflicts between plugins/theme (e.g. 2 JavaScript files that are loading from different plugins and they interfere one with another)
* Better performance score if you test your URL on websites such as GTmetrix, PageSpeed Insights, Pingdom Website Speed Test
* Google will love your website more as it would be faster and fast page load is nowadays a factor in search ranking
* Your server access log files (e.g the Apache ones) will be easier to scan and would take less space on your server

= Google Fonts Optimization / Removal =
* Combine all Google Font requests into fewer (usually one) requests, saving one round trip to the server for each additional font requested
* Choose between three methods of delivery: Render-blocking, Asynchronous via Web Font Loader (webfont.js) or Asynchronous by preloading the CSS stylesheet
* Option to preload Google Font Files from fonts.gstatic.com (e.g. ending in .woff2)
* Apply "font-display" CSS property to all loaded Google Font requests
* Enable preconnect resource hint for fonts.gstatic.com in case you use Google Fonts; don't let the browser wait until it fetches the CSS for loading the font files before it begins DNS/TCP/TLS
* Remove all Google Font requests including link/font preloads, @import/@font-face from CSS files & STYLE tags, resource hints

= Local Fonts Optimization =
* Preload local font files (ending in .woff, .woff2, .ttf, etc.)
* Apply "font-display:" CSS property to @font-face from existing to LINK / STYLE tags to improve the PageSpeed score for "Ensure text remains visible during webfont load"

= Critical CSS =
* You can add already generated critical CSS (e.g. via tools such as <a target="_blank" href="https://www.corewebvitals.io/tools/critical-css-generator">Advanced Critical CSS Generator</a> customly for the homepage, posts, pages, taxonomy pages (e.g. category), archive pages (e.g. date, author), search, 404 not found
* The critical CSS can be added within the Dashboard as well as via code / <a target="_blank" href="https://www.assetcleanup.com/docs/?p=608">Read More</a>

= Remove useless links, meta tags and HTML comments within the HEAD and BODY (footer) tags of the website =
* Really Simple Discovery (RSD) link tag
* REST API link tag
* Pages/Posts Shortlink tag
* WordPress version meta tag (also good for security reasons)
* All "generator" meta tags (also good for security reasons)
* RSS Feed Link Tags (usually they are not needed if your website is not used for blogging purposes)
* oEmbeds, if you do not need to embed videos (e.g. YouTube), tweets and audios
* Valid HTML Comments (exceptions from stripping can be added and Internet Explorer conditional comments are preserved)

Each option can be turned on/off depending on your needs. Instructions about each of them are given in the plugin's settings page.

= Disable partially or completely XML-RPC protocol =
This is an API service used by WordPress for 3rd party applications, such as mobile apps, communication between blogs and plugins such as Jetpack. If you use or are planning to use a remote system to post content to your website, you can keep this feature enabled (which it is by default). Many users do not use this function at all and if you’re one of them, you can disable it.

Plugin works with WordPress Multisite Network enabled!

> <strong>Asset CleanUp Pro</strong><br />
> This plugin is the lite version of Asset CleanUp Pro that comes with more benefits including managing assets (CSS & JS files) on all WordPress pages, unloading plugins site-wide or via Regex(es), apply "async" and "defer" attributes on loaded JavaScript files which would boost the speed score even higher, move the loading location of CSS/JS files (from HEAD to BODY to reduce render-blocking or vice-versa if you need specific files to trigger earlier) and premium support. <a href="https://www.gabelivan.com/items/wp-asset-cleanup-pro/?utm_source=wp_org_lite&utm_medium=inside_quote">Click here to purchase Asset CleanUp Pro!</a>

= NOTES =
People that have tested the plugin are so far happy with it and I want to keep a good reputation for it. In case something is not working for you or have any suggestions, please write to me on the forum and I will be happy to assist you. **BEFORE rating this plugin**, please check the following post http://chrislema.com/theres-wrong-way-give-plugin-feedback-wordpress-org/ and then use your common sense when writing the feedback :)

= GO PRO =
* Unload CSS/JS files on all WordPress pages including Categories, Tags, Custom Taxonomy (e.g. WooCommerce product category), 404 Not Found, Date & Author Archives, Search Results)
* Unload plugins in the frontend view (for guest visitors) * This will not just unload the CSS/JS files loaded from the plugins, but everything else related to them (e.g. slow database queries)
* Unload plugins within the Dashboard /wp-admin/ * Do you have any slow pages that are loading within the Dashboard? You can reduce seconds in page load for some bulky ones or fix plugin conflicts
* Instruct the browser to download a CSS/JS file based on the visitor's screen size (e.g. avoid downloading assets in mobile view when they are not needed, if the screen size is smaller than 768px)
* Defer CSS by appending it to the BODY to load it asynchronously (Render blocking CSS delays a web page from being visible in a timely manner)
* Move JavaScript files from HEAD to BODY and vice-versa (CSS files moved to the BODY are automatically deferred)
* Defer JavaScript loaded files (by applying "defer" attribute to any enqueued JS file)
* Async JavaScript loaded files (by applying "async" attribute to any enqueued JS file)
* Inline JavaScript files (automatically & by specifying the path to the stylesheets)
* Priority in releasing new features & other improvements (updates that are meant for both Lite and Pro plugins are first released to the Pro users)
* Premium support and updates within the Dashboard

Give Asset CleanUp a try! If you want to unlock all features, you can <a href="https://www.gabelivan.com/items/wp-asset-cleanup-pro/?utm_source=wp_org_lite&utm_medium=go_pro">Upgrade to the Pro version</a>.

== Installation ==
* If you're planning to use the Lite version of the plugin:

1. Go to "Plugins" -> "Add New" -> "Upload Plugin" and attach the downloaded ZIP archive from the plugin's page or use the "Search plugins..." form on the right side and look for "asset cleanup"
2. Install and activate the plugin (if server's PHP version is below 5.6, it will show you an error and activation will not be made).
3. Edit any Page / Post / Custom Post Type and you will see a meta box called "Asset CleanUp" which will load the list of all the loaded .CSS and .JS files. Alternatively, you will be able to manage the assets list in the front-end view as well (at the bottom of the pages) if you've enabled "Manage in the Front-end?" in plugin's settings page.
4. To unload the assets for the home page, go to "Asset CleanUp" menu on the left panel of the Dashboard and click on "CSS & JS MANAGER" ("Homepage" is the default tab).

* I have purchased the Pro version. How to do the upgrade?
1. Go to "Plugins" -> "Add New" -> "Upload Plugin"; You will notice an upload form and an "Install Now" submit button. Download the ZIP file you received in your purchase email receipt (example: wp-asset-clean-up-pro-v1.1.8.2.zip), attach it to the form and install the new upgraded plugin.
2. Finally, click "Activate Plugin"!
3. Once the plugin is activated, make sure to grab the license key from the purchase email receipt and activate it in the "License" (plugin's menu) page in order to be eligible for plugin updates from the Dashboard. That's it :)

== Frequently Asked Questions ==
= What PHP version is required for this plugin to work? =

5.6+ - I strongly recommend you to use PHP 7+, if you're website is fully compatible with it, as it's much faster than any PHP 5.* and it will make a big difference for your website's backend speed.

= If my website is hosted with WordPress.com (not self-hosted), can I still use Asset CleanUp to get a faster page load? =

Although Asset CleanUp is guaranteed to work with self-hosted (WordPress.org) websites, it can be used on WordPress.com ones too, but with limitations. Features such as minify/combine CSS/JS files or combine Google Fonts (if any loaded) aren't guaranteed to work (basically, anything requiring writing files to the caching directory). However, if you want to unload CSS/JS files, which is the key feature of the plugin, then this has always been tested & working for WordPress.com websites. Note that at the time of writing this (June 21, 2020), you can only install plugins on WordPress.com websites if you sign up for their Business plan: https://wordpress.com/pricing/

= How do I know if my website’s page loading speed is slow and needs improvement? =

There are various ways to check the speed of a website and this is in relation to the following: front-end (the part of the website visible to your visitors), back-end (PHP code, server-side optimization), hosting company, CDN (Content Delivery Network) setup, files loaded (making sure CSS, JS, Images, Fonts, and other elements are properly optimized when processed by the visitor’s browser).

Check out <a href="https://gtmetrix.com/" target="_blank">https://gtmetrix.com/</a> to do an analysis of your website and see the overall score your website gets in PageSpeed and YSlow.

= What is an asset and which are the assets this plugin is dealing with? =

Web assets are elements such as CSS, JavaScript, Fonts, and image files that make the front-end which is the look and functionality of your website that is processed by the browser you are using (e.g. Google Chrome. Mozilla Firefox, Safari, Internet Explorer, Opera etc.). Asset CleanUp deals with CSS and JavaScript assets which are enqueued in WordPress by your theme and other plugins.

= Is this plugin a caching one?

No, Asset CleanUp does not do any page caching. It just helps you unload .css and .js that you choose as not needed from specific pages (or all pages). This, combined with an existing caching plugin, will make your website pages load faster and get a higher score in speed checking tools such as GTMetrix (Google PageSpeed and YSlow).

= Has this plugin been tested with other caching plugins?

Yes, this plugin was tested with W3 Total Cache, WP Rocket and WP Fastest Caching and should work with any caching plugin as any page should be cached only after the page (HTML Source) was rendered and all the enqueueing / dequeueing was already completed (from either the plugins or the theme). Asset CleanUp comes with minify/combine files feature. Please do not also enable the same feature on a caching plugin. Example: If you already minified CSS/JS files with Asset CleanUp, do not enable Minify CSS/JS in WP Rocket or other caching plugins.

= I've noticed scripts and styles that are loaded on the page, but they do not show in the "Asset CleanUp" list when editing the page or no assets are showing at all. Why is that? =

There are a few known reasons why you might see different or no assets loading for management:

- Those assets weren't loaded properly into WordPress by the theme/plugin author as they were likely hardcoded and not enqueued the WordPress way. Here's a tutorial that will help you understand better the enqueuing process: http://www.wpbeginner.com/wp-tutorials/how-to-properly-add-javascripts-and-styles-in-wordpress/

- You're using a cache plugin that is caching pages even when you're logged in which is something I don't recommend as you might have conflicts with other plugins as well (e.g. in W3 Total Cache, you can enable/disable this) or that plugin is caching pages even when a POST request is made to them (which is not a good practice as there are many situations in which a page should not be cached). That could happen if you're using "WP Remote POST" method (from version 1.2.4.4) of retrieving the assets in the Dashboard.

- You might have other functions or plugins (e.g. Plugin Organizer) that are loading prior to Asset CleanUp. Note that Plugin Organizer has a file that is in “mu-plugins” which will load prior to any plugin you have in “plugins”, thus, if you have disabled specific plugins through “Plugin Organizer” in some pages, their assets will obviously not show in the assets list as they are not loaded at all in the first place.

If none of these apply to you and you just don't see assets that should definitely show there, please open a support ticket.

= Why are the unload settings that I've applied for CSS/JS files not taking any effect when I visit the page? =

Whenever you unload certain CSS/JS files, you expect to either see an immediate increase in the Google PageSpeed Insights / GTMetrix score or not loaded when you test the page in Incognito (visiting it as a guest, while you’re not logged-in). However, this doesn’t always happen. Pleae check this post to find out the possible reasons: https://assetcleanup.com/docs/changes-applied-not-taking-effect/

= How can I access all the features? =

You can get access to more features, priority support and automatic updates by <a href="https://www.gabelivan.com/items/wp-asset-cleanup-pro/?utm_source=wp_org_lite&utm_medium=inside_faq">upgrading to the Pro version</a>. It's strongly recommended to avoid using any <a href="https://www.gabelivan.com/asset-cleanup-pro-nulled-wordpress-plugin/?utm_source=wp_org_lite&utm_medium=inside_faq_nulled_area">Asset CleanUp Pro nulled</a> versions as they might contain malware and you will also not get any official support and access to plugin updates (e.g. bug fixes).

= jQuery and jQuery Migrate are often loading on pages/post. Are they always needed? =

The known jQuery library is being used by many themes and plugins so it's recommended to keep it on. jQuery Migrate was created to simplify the transition from older versions of jQuery. It restores deprecated features and behaviors so that older code will still run properly on jQuery 1.9 and later.

However, there are cases when you might not need jQuery at all on a page. If that's the case, feel free to unload it. Make sure you properly test the page afterward, including testing it for mobile view.

= Is the plugin working with WordPress Multisite Network? =

Yes, the plugin has been tested for WordPress Multisite and all its settings are applied correctly to any of the sites that you will be updating.

= When editing a post/page, I can see the message "We're getting the loaded scripts and styles for this page. Please wait...", but nothing loads! Why? =

The plugin makes AJAX calls to retrieve the data from the front-end page with 100% accuracy. Possible reasons why nothing is shown despite the wait might be:

- Your internet connection cut off after you loaded the edit post/post (before the AJAX calls were triggered). Make sure to check that and refresh the page if it's back on - it happened to me a few times

- There could be a conflict between plugins or your theme and something is interfering with the script that is retrieving the assets

- You are loading the WordPress Dashboard through HTTPS, but you are forcing the front-end to load via HTTP. Although Asset CleanUp auto-corrects the retrieval URL (e.g. if you're logged in the Dashboard securely via HTTPS, it will attempt to fetch the assets through HTTPS too), there could be cases where another plugin or .htaccess forces an HTTP connection only for the public view. Due to Same Origin Policy (read more here: https://developer.mozilla.org/En/Same_origin_policy_for_JavaScript), you can't make plain HTTP AJAX calls from HTTPS connections. If that's the case, try to enable "WP Remote POST" as a retrieval method in the plugin's settings if you want to manage the assets in the Dashboard.

- You're using plugins such as Wordfence that block the AJAX request. At this time, if that's the case, it's best to enable managing assets in the front-end view (Settings).

In this case, it's advisable to enable "Manage in the Front-end?" in "Settings" of "Asset CleanUp", thus making the list to show at the bottom of the posts, pages, and front-page only for the logged in users with admin privileges.

Although I've written the code to ensure maximum compatibility, there are factors which are not up to the quality of the plugin that could interfere with it.
In case the assets are not loading for you, please write to me on the forum and I will be happy to assist you!

= I do not know or I'm not sure which assets to unload on my pages. What should I do? =

With the recently released "Test Mode" feature, you can safely unload assets on your web pages without affecting the pages' functionality for the regular visitors. It will unload CSS & JavaScript files that you selected ONLY for yourself (logged-in administrator). That's recommended in case you have any doubts about whether you should applying a specific setting or unload any asset. Once you've been through the trial and error and your website is lighter, you can deactivate "Test Mode", clear cache (if using a caching plugin) and the changes will apply for everyone. Then, test the page speed score of your website :)

== Screenshots ==
1. When editing a page, a meta box will load with the list of loaded CSS & JS files from the active theme & plugins
2. Plugin Usage Preferences (From "Settings")
3. Combine CSS & JS files option
4. Homepage CSS & JS Management (List sorted by location)

== Changelog ==
= 1.3.9.9 =
* Fix: Server Side Request Forgery (SSRF) has been discovered on an AJAX call within the CSS/JS manager; New parameters were added to the call to avoid any unsanitized input

= 1.3.9.8 =
* Fix: Avoid deprecated PHP notice if PHP version >= 8.1; A "null" parameter was passed to the native WordPress function add_submenu_page(), instead of an empty string ''
* Fix: When using Query Monitor, the "Update" button from the CSS/JS manager was showing up on top of the bottom Query Monitor data

= 1.3.9.7 =
* CSS assets can now be preloaded (via the CSS/JS manager) in an async way as well * <a target="_blank" href="https://www.assetcleanup.com/docs/?p=202#preload-async-css">Read more</a>
* Reduce the total number of SQL queries used to obtain information
* Stop triggering PHP code and SQL queries on pages where they are not relevant
* Cache SQL queries that are time consuming, which is ideal for websites with a very large database (e.g. tens / hundred of thousands of users)

= 1.3.9.6 =
* Fix: The "usermeta" table is populated with duplicate entries, leading to a larger database, and sometimes, leading to a high CPU usage

= 1.3.9.5 =
* New Option: "Settings" -- "Plugin Usage Preferences" -- "Plugin Access" / Choose user roles or particular users, apart from administrators, that could have access to the plugin area * e.g. the admin could give Asset CleanUp Pro access within the Dashboard to a developer that is optimizing the website, but the developer does not have the "administrator" role for security reasons
* "wpacu_access_role" filter is no longer active (related to the option mentioned above), as it wasn't 100% effective into changing who accesses the Asset CleanUp Pro area
* "Nextend Social Login and Register" plugin compatibility / Make sure the homepage is still detected if the following query string is in the URI: "nsl_bypass_cache"
* Fix: When oEmbed is disabled, make sure the REST route is also inactive
* Fix: When the plugin's main menu is hidden from the left sidebar, make sure the following option stays selected whenever a plugin page is accessed: "Settings" -- "Asset CleanUp"

= 1.3.9.4 =
* Option to manage critical CSS (in "CSS & JS Manager" » "Manage Critical CSS") from the Dashboard (add/update/delete), while keeping the option to use the "wpacu_critical_css" hook for custom/singular pages
* Preload CSS feature: When a .css file is preloaded (Basic), the "media" attribute is preserved if it's not missing and different than "all"
* Hardcoded assets' sorting: The assets are now sorted based on the option chosen in "Assets List Layout:" (e.g. if you sort them by their size, you can view the hardcoded assets from the largest one to the the smallest)
* "GTranslate" plugin compatibility: The JavaScript handle starting from "gt_widget_script_" and having a random number on each page reload gets an alias ("gt_widget_script_gtranslate") to avoid misinterpretation that the asset is a different one on each page reload (this way it could be unloaded, preloaded, etc.)
* Combined CSS/JS: Whenever a file from a plugin or a theme is updated by the developer/admin, there's no need to clear the cache afterwards, as sometimes, users forget about this; the plugin automatically recognizes the change and a new combined CSS/JS is created and re-cached
* CSS/JS manager: When the "src" of a SCRIPT tag or "href" of a LINK tag starts with "data:text/javascript;base64," and "data:text/css;base64," respectively, a note will be shown with the option to view the decoded CSS/JS code
* If the menu from the sidebar is not showing up, make sure that "Asset CleanUp" from "Settings" (Dashboard sidebar) is always highlighted, whenever a plugin page is visited
* Improvement: When using specific themes, the navigation sub-tabs from the "CSS & JS Manager" were overwritten by the theme's style (added unique references to the HTML classes)
* Improvement: Make sure the red background is kept whenever a load exception is unchecked if there was already an unloading rule set (this is more for aesthetics reasons)
* Improvement: Backend Speed - The plugin processes its PHP code faster, thus reducing the total processing time by ~50 milliseconds for non-cached pages (e.g. backend speed testing plugins such as "Query Monitor" and "Code Profiler" were used to optimize the PHP code)
* Improvement: CSS Minifier - Specific "var()" statements were minified incorrectly in Bootstrap / more: https://github.com/matthiasmullie/minify/issues/422
* Improvement: Added the option to change the way the assets are retrieved ("Direct" as if the admin is visiting the page /  "WP Remote POST" as if a guest is visiting the page) from the CSS & JS manager within the Dashboard (for convenience, to avoid going through the "Settings" as it was the case so far)
* Improvement: Higher accuracy in detecting the "type" and "data-alt-type" attribute before determining if an inline SCRIPT tag has to be minified
* Improvement: In very rare cases in the "options" table, if "page_on_front" has a value and "show_on_front" is set to "posts" (this happens when there's an incomplete update of the settings in the database), it will confuse Asset CleanUp Pro and consider that "Your homepage displays" is actually set to "A static page" which is wrong
* Improvement: The plugin is optimised to load fewer functions then before (e.g. PHP classes that aren't required on the targeted page) in order to reduce the total front-end optimization time
* Improvement: Removed unused PHP code from specific files
* Improvement: CSS/JS Minifier - Prevent calling @is_file() when it's not the case to avoid on specific environments errors such as: "is_file(): open_basedir restriction in effect"
* Improvement: Whenever the following option is enabled, the META generator tags are stripped faster after being cached: 'HTML Source CleanUp' -- 'Remove All "generator" meta tags?'
* Improvement: Apply "font-display:" CSS property for Google Fonts when they are loaded via Web Font Loader (source: https://github.com/typekit/webfontloader)
* Rank Math & other SEO plugins compatibility: Prevent Asset CleanUp Pro from triggering, thus saving extra resources, whenever URIs such as /sitemap_index.xml are loaded to avoid altering the XML structure or generate 404 not found errors
* "WooCommerce" plugin compatibility: Avoid using extra resources in Asset CleanUp Pro to process specific CSS files (they are loading after the latest WooCommerce plugin release) that are already minified
* "SiteGround Optimizer" plugin compatibility: When enabled, on some environments, errors are triggering if Asset CleanUp's JavaScript minify option is turned on
* "GiveWP" plugin compatibility: Prevent Asset CleanUp Pro from loading whenever the URI is like /give/donation-form?giveDonationFormInIframe=1 as the page loaded within the iFrame is already optimized and there are users that had problems when Asset CleanUp Pro was triggering its rules there
* "GiveWP" plugin compatibility: Prevent CSS/JS minification as the files are already optimized and there's no point in wasting extra resources
* "Settings" -- Replaced text that sometimes caused confusion (e.g. some people didn't notice the small "if" and thought their caching directory is not writable)
* "Settings" -- "Plugin Usage Preferences": Re-organised the tab contents from into multiple sub-tabs for easier access and understanding the options
* "Settings" -- "Plugin Usage Preferences" - "Do not load on specific pages" -- "Prevent features of Asset CleanUp Pro from triggering on specific pages"; This allows you to stop triggering specific plugin features on certain pages (e.g. you might want to prevent combining JavaScript files on all /product/ (WooCommerce) pages due to some broken functionality on those specific pages)
* Fix: In some environments, the tags with "as" attribute were not properly detected (e.g. when "DOMDocument" is not enabled by default in the PHP configuration)
* Fix: Sometimes the "src" value was detected incorrectly on hardcoded assets due to the fact that the string "src=" was inside document.write() within the <SCRIPT> tags (which had no "src" attribute at all) / e.g. <script type="text/javascript">console.log('test'); document.write('<scri' + 'pt src="//path-to-specific-file.js"></sc' + 'ript>');</script>
* Fix: When "WP Remote Post" was used as a fetch method of the CSS/JS assets within the Dashboard, information about the targeted URL was showing up twice (e.g. the admin could be confused of viewing redundant text printing out)
* Fix: Make sure 'post__in' is never empty when called within a WP_Query whenever a post search is made within "CSS & JS Manager" -- "Manage CSS/JS"
* Fix: On some environments, FS_CHMOD_DIR and FS_CHMOD_FILE weren't defined, triggering errors such as: Uncaught Error: Undefined constant "WpAssetCleanUp\FS_CHMOD_DIR"
* Fix: In specific environments that loaded similar code to the one from Asset CleanUp Pro, errors were showing up, thus more uniqueness had to be added to avoid conflicts such as unique PHP namespaces
* Fix: On some environments, the following error would show up when WP CLI is used: "PHP Fatal error: Uncaught Error: Call to a member function getScriptAttributesToApplyOnCurrentPage() on null"
* Fix: Combined CSS/JS - The preload and stylesheet LINK tags had the same "id" attribute which shouldn't be like that as the "id" should be unique for each HTML element
* Fix: After a theme is switched, there's sometimes a browser error showing up related to multiple failed redirects

= 1.3.9.3 =
* WordPress 6.3 compatibility: Updated the code to avoid the following notice: "Function WP_Scripts::print_inline_script is deprecated since version 6.3.0"
* "WPML Multilingual CMS" plugin compatibility: Syncing post changes on all its associated translated posts / e.g. if you unload an asset on a page level in /contact/ (English) page, it will also be unloaded (synced) in /contacto/ (Spanish) and /kontakt/ (German) pages
* "WP Rocket" plugin compatibility: "Settings" -- "Optimize JavaScript" -- "Combine loaded JS (JavaScript) into fewer files" is automatically disabled when the following option is turned on in "WP Rocket": "File Optimization" -- "JavaScript Files" -- "Delay JavaScript execution"
* "Hide My WP Ghost – Security Plugin" plugin compatibility: Asset CleanUp's HTML alteration is done before the one of the security plugin so minify/combine CSS/JS will work fine
* "Site Kit by Google" plugin compatibility: JavaScript files from this plugin are added to the ignore list to avoid minifying as they are already minified (with just a few extra comments) and minifying them again, due to their particular structure, resulted in JS errors in the browser's console
* Improvement: Changed the name of the cached files to make them more unique as sometimes, handles that had UNIX timestamps and random strings (developers use them for various reason, including debugging), were causing lots of redundant files to be generated in the assets' caching directory
* Added jQuery Migrate script to the ignore list to avoid minifying it (along with jQuery leave it as it is, if the developer decided to load the large versions of the files, for debugging purposes)
* Front-end view: In the "Asset CleanUp" top admin bar menu, a new link is added that goes directly to the manage CSS/JS area for the current visited page for convenience
* Remove the usage of "/wp-content/cache/storage/_recent_items" directory from the CSS/JS caching directory as it was redundant to the caching functionality
* Option to skip "Cache Enabler" cache clearing via using the "WPACU_DO_NOT_ALSO_CLEAR_CACHE_ENABLER_CACHE" constant (e.g. set to 'true' in wp-config.php) - read more: https://www.assetcleanup.com/docs/?p=1502#wpacu-cache-enabler
* "Knowledge Base for Documents and FAQs" plugin: Do not show the CSS/JS manager at the bottom of the page when "Edit KB Article Page" is ON
* New "Brizy - Page Builder" setup: Prevent Asset CleanUp from triggering when the editor is ON
* Fix: "Do not load Asset CleanUp on this page (this will disable any functionality of the plugin)" - if turned ON, make sure the hardcoded list loads fine in the front-end view (Manage CSS/JS)
* Fix: Use the same "chmod" values from FS_CHMOD_DIR and FS_CHMOD_FILE (WordPress constants) for all the files and directories from the assets' caching directory when attempting to create a file/directory to avoid permission errors on specific environments

= 1.3.9.2 =
* New Option: Contract / Expand All Assets within an area (e.g. from a plugin)
* "Overview" area: Added notifications about deleted posts, post types, taxonomies and users, making the admin aware that some rules might not be relevant anymore (e.g. the admin uninstalled WooCommerce, but unload rules about "product" post types or a specific product page remained in the database)
* Stopped using the "error" class (e.g. on HTML DIV elements) and renamed it to "wpacu-error" as some plugins/themes sometimes interfere with it (e.g. not showing the error at all, thus confusing the admin)
* Keep the same strict standard for the values within the following HTML attributes: "id", "for" to prevent any errors by avoiding any interferences with other plugins
* Improvement: Only print the notice (as an HTML comment) about the "photoswipe" unload to the administrator (it's a special case where the HTML has to be hidden in case the CSS file gets unloaded)
* WPML Fix: Prevent Asset CleanUp from triggering whenever /?wpml-app=ate-widget is loaded (in some environments, the content returned was empty and the automatic translation area was not loading)

= Previous versions =
To check older logs, please refer to the separate <a target="_blank" href="https://plugins.trac.wordpress.org/export/3137290/wp-asset-clean-up/trunk/changelog.txt">changelog.txt file</a> within the root of the plugin directory!