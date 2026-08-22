<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
	exit;
}

use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\Admin\ImportExport;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

include_once __DIR__ . '/_top-area.php';

do_action('wpacu_admin_notices');
?>
<div class="wpacu-wrap wpacu-tools-area">
    <nav class="wpacu-tab-nav-wrapper wpacu-nav-tab-wrapper wpacu-tools-nav" aria-label="<?php esc_attr_e('Tools sections', 'wp-asset-clean-up'); ?>">
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_tools&wpacu_for=system_info')); ?>" class="wpacu-nav-tab wpacu-tools-nav__system-info <?php if ($data['for'] === 'system_info') { ?>wpacu-nav-tab-active<?php } ?>" <?php if ($data['for'] === 'system_info') { ?>aria-current="page"<?php } ?>><span class="dashicons dashicons-media-text" aria-hidden="true"></span><span><?php esc_html_e('System Info', 'wp-asset-clean-up'); ?></span></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_tools&wpacu_for=storage')); ?>" class="wpacu-nav-tab wpacu-tools-nav__storage <?php if ($data['for'] === 'storage') { ?>wpacu-nav-tab-active<?php } ?>" <?php if ($data['for'] === 'storage') { ?>aria-current="page"<?php } ?>><span class="dashicons dashicons-database" aria-hidden="true"></span><span><?php esc_html_e('Storage', 'wp-asset-clean-up'); ?></span></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_tools&wpacu_for=debug')); ?>" class="wpacu-nav-tab wpacu-tools-nav__debug <?php if ($data['for'] === 'debug') { ?>wpacu-nav-tab-active<?php } ?>" <?php if ($data['for'] === 'debug') { ?>aria-current="page"<?php } ?>><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span><span><?php esc_html_e('Debugging', 'wp-asset-clean-up'); ?></span></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_tools&wpacu_for=import_export')); ?>" class="wpacu-nav-tab wpacu-tools-nav__import-export <?php if ($data['for'] === 'import_export') { ?>wpacu-nav-tab-active<?php } ?>" <?php if ($data['for'] === 'import_export') { ?>aria-current="page"<?php } ?>><span class="dashicons dashicons-migrate" aria-hidden="true"></span><span><?php esc_html_e('Import & Export', 'wp-asset-clean-up'); ?></span></a>
        <span class="wpacu-tools-nav__separator" aria-hidden="true"></span>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_tools&wpacu_for=reset')); ?>" class="wpacu-nav-tab wpacu-tools-nav__reset <?php if ($data['for'] === 'reset') { ?>wpacu-nav-tab-active<?php } ?>" <?php if ($data['for'] === 'reset') { ?>aria-current="page"<?php } ?>><span class="dashicons dashicons-image-rotate" aria-hidden="true"></span><span><?php esc_html_e('Reset', 'wp-asset-clean-up'); ?></span></a>
    </nav>

	<div class="wpacu-tools-container">
		<form id="wpacu-tools-form" action="<?php echo esc_url(admin_url('admin.php?page='.WPACU_PLUGIN_ID.'_tools')); ?>" method="post">
            <?php if ($data['for'] === 'reset') { ?>
                <section class="wpacu-reset-panel">
                <div class="wpacu-reset-panel__intro wpacu-tools-intro">
                    <span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
                    <div>
                        <h2><?php esc_html_e('Reset plugin data', 'wp-asset-clean-up'); ?></h2>
                        <p><?php esc_html_e('Choose what you want to reset. Review the details carefully before continuing.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </div>

                <div class="wpacu-reset-field"><label for="wpacu-reset-drop-down"><?php esc_html_e('What would you like to reset?', 'wp-asset-clean-up'); ?></label></div>

                <select name="wpacu-reset" id="wpacu-reset-drop-down">
                    <option value=""><?php esc_html_e('Select an option', 'wp-asset-clean-up'); ?>...</option>
                    <option data-id="wpacu-warning-reset-settings" data-submit-label="<?php esc_attr_e('Reset Settings', 'wp-asset-clean-up'); ?>" value="reset_settings"><?php esc_html_e('Reset Settings', 'wp-asset-clean-up'); ?></option>

                    <?php
                    // [CRITICAL CSS]
                    ?>
                        <option data-id="wpacu-warning-reset-critical-css" data-submit-label="<?php esc_attr_e('Reset Critical CSS', 'wp-asset-clean-up'); ?>" value="reset_critical_css"><?php esc_html_e('Reset Critical CSS', 'wp-asset-clean-up'); ?></option>
                    <?php
                    // [/CRITICAL CSS]
                    ?>

                    <?php if ( ! empty($data['has_plugins_manager_front_rules'])) { ?>
                        <option data-id="wpacu-warning-reset-plugins-manager-front" data-submit-label="<?php esc_attr_e('Reset front-end plugin rules', 'wp-asset-clean-up'); ?>" value="reset_plugins_manager_front"><?php esc_html_e('Reset Plugins Manager rules — Front-end', 'wp-asset-clean-up'); ?></option>
                    <?php } ?>

                    <?php if ( ! empty($data['has_plugins_manager_dash_rules'])) { ?>
                        <option data-id="wpacu-warning-reset-plugins-manager-dash" data-submit-label="<?php esc_attr_e('Reset /wp-admin/ plugin rules', 'wp-asset-clean-up'); ?>" value="reset_plugins_manager_dash"><?php esc_html_e('Reset Plugins Manager rules — /wp-admin/', 'wp-asset-clean-up'); ?></option>
                    <?php } ?>

                    <option data-id="wpacu-warning-reset-everything-except-settings" data-submit-label="<?php esc_attr_e('Reset all data except Settings', 'wp-asset-clean-up'); ?>" value="reset_everything_except_settings"><?php esc_html_e('Reset all plugin data except Settings', 'wp-asset-clean-up'); ?></option>
                    <option data-id="wpacu-warning-reset-everything" data-submit-label="<?php esc_attr_e('Reset everything', 'wp-asset-clean-up'); ?>" value="reset_everything"><?php esc_html_e('Reset everything', 'wp-asset-clean-up'); ?></option>
                    <option data-id="wpacu-warning-remove-all-data-for-uninstall" data-submit-label="<?php esc_attr_e('Remove all data for uninstall', 'wp-asset-clean-up'); ?>" value="remove_all_data_for_uninstall"><?php esc_html_e('Remove all data for uninstall', 'wp-asset-clean-up'); ?></option>
                </select>

				<div id="wpacu-reset-additional-options" aria-hidden="true">
					<h3><?php esc_html_e('Additional data to remove', 'wp-asset-clean-up'); ?></h3>
				<div id="wpacu-license-data-remove-area">
					<label for="wpacu-remove-license-data">
						<input id="wpacu-remove-license-data" type="checkbox" name="wpacu-remove-license-data" value="1" /> <?php esc_html_e('Also remove all Pro license data. Leave this unchecked if you plan to use Pro again.', 'wp-asset-clean-up'); ?>
					</label>
				</div>

                <div id="wpacu-cache-assets-remove-area">
                    <label for="wpacu-remove-cache-assets">
                        <input id="wpacu-remove-cache-assets" type="checkbox" name="wpacu-remove-cache-assets" value="1" /> <?php echo sprintf(__('Also remove any cached CSS/JS files from %s', 'wp-asset-clean-up'), '<code>/'.basename(WP_CONTENT_DIR) . OptimizeCommon::getRelPathPluginCacheDir().'</code>'); ?> (please be careful as there might be cached pages - e.g. people previewing your page via Google Cache - still making reference to the CSS/JS files, you can leave it unchecked if you are not sure about it)
                    </label>
                </div>
				</div>

                <div id="wpacu-reset-review" aria-live="polite">
                <div id="wpacu-warning-read"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <strong><?php esc_html_e('This action cannot be undone. Review what will be removed before continuing.', 'wp-asset-clean-up'); ?></strong></div>

                <div id="wpacu-warning-reset-settings" class="wpacu-warning">
                    <p><?php _e('This will reset every option from the "Settings" page/tab to the same state it was in when you first activated the plugin.', 'wp-asset-clean-up'); ?></p>
                </div>

                <?php
                // [CRITICAL CSS]
                ?>
                <div id="wpacu-warning-reset-critical-css" class="wpacu-warning">
                    <p><?php _e('This will remove all the critical CSS information from <em>"CSS &amp; JS Manager" -&gt; "Manage Critical CSS"</em> and restore it the way it was by default. This is useful, for instance, when you redesign your website &amp; a new critical CSS needs to be added on all pages, rather then having any unneeded leftovers from the old CSS printing on certain pages.', 'wp-asset-clean-up'); ?></p>
                </div>
                <?php
                // [/CRITICAL CSS]
                ?>

                <div id="wpacu-warning-reset-plugins-manager-front" class="wpacu-warning">
                    <p><?php esc_html_e('This will remove every unload rule and load exception configured in Plugins Manager for the public-facing website. Rules configured for /wp-admin/ will be preserved.', 'wp-asset-clean-up'); ?></p>
                </div>

                <div id="wpacu-warning-reset-plugins-manager-dash" class="wpacu-warning">
                    <p><?php esc_html_e('This will remove every unload rule and load exception configured in Plugins Manager for WordPress administration pages. Front-end rules will be preserved.', 'wp-asset-clean-up'); ?></p>
                </div>

                <div id="wpacu-warning-reset-everything-except-settings" class="wpacu-warning">
                    <p><?php esc_html_e('This removes unload rules, load exceptions, Critical CSS, page-level metadata, transients and other Asset CleanUp data while preserving the values from Settings and all Pro license data.', 'wp-asset-clean-up'); ?></p>
                    <p><?php esc_html_e('Use this option when you want a clean rules database without changing the plugin preferences configured on the Settings page.', 'wp-asset-clean-up'); ?></p>
                </div>

                <div id="wpacu-warning-reset-everything" class="wpacu-warning">
                    <p><?php _e('This will reset all settings, unload rules and load exceptions to their initial state. All of the plugin\'s database records will be removed. For your website, this has the same effect as deactivating the plugin.', 'wp-asset-clean-up'); ?></p>

                    <p><?php _e('Use this action when:', 'wp-asset-clean-up'); ?></p>
                    <ul>
                        <li><?php echo sprintf(__('You believe you have applied some changes (such as unloading the wrong CSS / JavaScript file(s)) that broke the website and you need a quick fix to make it work the way it used to. Note that for this option, you can also enable "Test Mode" from the plugin\'s settings which will only apply the changes to you (logged-in administrator), while the regular visitors will view the website as if %s is deactivated.', 'wp-asset-clean-up'), WPACU_PLUGIN_TITLE); ?></li>
                    </ul>
                </div>

                <div id="wpacu-warning-remove-all-data-for-uninstall" class="wpacu-warning">
                    <p><?php esc_html_e('This permanently removes all Asset CleanUp settings, rules, metadata, transients, Pro license data and generated cache files. Settings will not be recreated.', 'wp-asset-clean-up'); ?></p>
                    <p><?php esc_html_e('If Asset CleanUp Pro is active, its MU-plugin loader will also be removed. On Multisite, network-shared user metadata and the Pro MU-plugin loader are preserved while this plugin is still active for another site. After this cleanup finishes, deactivate and delete the plugin files from the Plugins page.', 'wp-asset-clean-up'); ?></p>
                </div>

                <label id="wpacu-reset-confirm-area" for="wpacu-reset-confirm">
                    <input id="wpacu-reset-confirm" type="checkbox" value="1" />
                    <span><?php esc_html_e('I understand that this action is permanent and cannot be undone.', 'wp-asset-clean-up'); ?></span>
                </label>
                </div>

                <?php
                wp_nonce_field('wpacu_tools_reset', 'wpacu_tools_reset_nonce');
                ?>

                <input type="hidden" name="wpacu-tools-reset" value="1" />
                <input type="hidden" name="wpacu-action-confirmed" id="wpacu-action-confirmed" value="" />

                <div id="wpacu-reset-submit-area">
                    <button name="submit"
                            disabled="disabled"
                            id="wpacu-reset-submit-btn"
                            class="button button-secondary"><span data-wpacu-reset-button-label><?php esc_html_e('Select an option', 'wp-asset-clean-up'); ?></span></button>
                </div>
                </section>
            <?php } elseif ($data['for'] === 'system_info') {
	            wp_nonce_field('wpacu_get_system_info', 'wpacu_get_system_info_nonce');
	            ?>
                <input type="hidden" name="wpacu-get-system-info" value="1" />

                <section class="wpacu-system-info-intro wpacu-tools-intro">
                    <span class="dashicons dashicons-shield" aria-hidden="true"></span>
                    <div>
                        <h2><?php esc_html_e('Create a System Info report', 'wp-asset-clean-up'); ?></h2>
                        <p><?php esc_html_e('Generate a diagnostic report for support without making the page scan the database every time it is opened.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </section>

                <div class="wpacu-system-info-card">
                    <div class="wpacu-system-info-card__header">
                        <span class="dashicons dashicons-privacy" aria-hidden="true"></span>
                        <div>
                            <h3><?php esc_html_e('Redacted report', 'wp-asset-clean-up'); ?></h3>
                            <p><?php esc_html_e('Recommended for support requests. Recognised domains, server paths, licenses and credential-like values are redacted. Asset CleanUp rules and metadata are still included and can contain custom URLs, paths or identifiers, so review the file before sharing it.', 'wp-asset-clean-up'); ?></p>
                        </div>
                        <span class="wpacu-system-info-badge"><?php esc_html_e('Recommended', 'wp-asset-clean-up'); ?></span>
                    </div>

                    <label class="wpacu-system-info-detail-option" for="wpacu-include-sensitive-system-info">
                        <input id="wpacu-include-sensitive-system-info" type="checkbox" name="wpacu_include_sensitive_system_info" value="1" />
                        <span>
                            <strong><?php esc_html_e('Include sensitive environment details', 'wp-asset-clean-up'); ?></strong>
                            <small><?php esc_html_e('Reveals site domains, full filesystem paths and the browser user-agent. Asset CleanUp database rules and metadata are always included; recognised licenses, passwords, tokens and API keys remain redacted. Review the report before sharing it.', 'wp-asset-clean-up'); ?></small>
                        </span>
                    </label>

                    <div class="wpacu-system-info-actions">
                        <button name="submit" id="wpacu-download-system-info-btn" class="button button-primary">
                            <span class="dashicons dashicons-download" aria-hidden="true"></span>
                            <?php esc_html_e('Generate and download report', 'wp-asset-clean-up'); ?>
                        </button>
                        <span class="wpacu-system-info-action-note"><?php esc_html_e('The report is generated only after you click the button.', 'wp-asset-clean-up'); ?></span>
                    </div>
                </div>
            <?php } ?>
		</form>

        <?php
        if ($data['for'] === 'storage') {
            $storageArea = isset($data['storage_area']) && in_array($data['storage_area'], array('generated_files', 'database_map'), true)
                ? $data['storage_area']
                : 'generated_files';
            $generatedFilesUrl = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_tools&wpacu_for=storage&wpacu_storage_area=generated_files');
            $databaseMapUrl = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_tools&wpacu_for=storage&wpacu_storage_area=database_map');
            ?>
            <nav class="wpacu-storage-subnav" aria-label="<?php esc_attr_e('Storage sections', 'wp-asset-clean-up'); ?>">
                <a href="<?php echo esc_url($generatedFilesUrl); ?>" class="<?php echo $storageArea === 'generated_files' ? 'is-active' : ''; ?>" <?php if ($storageArea === 'generated_files') { ?>aria-current="page"<?php } ?>>
                    <span class="dashicons dashicons-media-code" aria-hidden="true"></span>
                    <span><?php esc_html_e('Generated Files', 'wp-asset-clean-up'); ?></span>
                </a>
                <a href="<?php echo esc_url($databaseMapUrl); ?>" class="<?php echo $storageArea === 'database_map' ? 'is-active' : ''; ?>" <?php if ($storageArea === 'database_map') { ?>aria-current="page"<?php } ?>>
                    <span class="dashicons dashicons-database" aria-hidden="true"></span>
                    <span><?php esc_html_e('Database Map', 'wp-asset-clean-up'); ?></span>
                </a>
            </nav>

            <?php if ($storageArea === 'generated_files') {
                $currentStorageDirRel  = OptimizeCommon::getRelPathPluginCacheDir();
                $currentStorageDirFull = WP_CONTENT_DIR . $currentStorageDirRel;

                if ( ! is_dir($currentStorageDirFull) ) {
                    wp_mkdir_p($currentStorageDirFull);
                }

                $currentStorageDirIsWritable = is_writable($currentStorageDirFull);
                ?>
                <section class="wpacu-storage-intro wpacu-tools-intro">
                    <span class="dashicons dashicons-media-code" aria-hidden="true"></span>
                    <div class="wpacu-storage-intro__copy">
                        <h2><?php esc_html_e('Generated CSS/JS files', 'wp-asset-clean-up'); ?></h2>
                        <p><?php esc_html_e('Review generated-file directories, file counts, disk usage and write access.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </section>
                <?php

                if ( ! $currentStorageDirIsWritable ) {
                    ?>
                    <div class="wpacu-warning" style="width: 98%;">
                        <p style="margin: 0;">
                            <span style="color: #cc0000;" class="dashicons dashicons-warning"></span>
                            <?php echo sprintf(
                                __('The system detected the storage directory as non-writable, thus the minify &amp; combine CSS/JS files feature will not work. Please %smake it writable%s or raise a ticket with your hosting company about this matter.', 'wp-asset-clean-up'),
                                '<a href="https://wordpress.org/support/article/changing-file-permissions/">',
                                '</a>'
                            ); ?>
                        </p>
                    </div>
                    <?php
                }

                $storageStats = OptimizeCommon::getStorageStats();
                include __DIR__ . '/_admin-page-tools-storage.php';
            } else {
                ?>
                <section class="wpacu-storage-intro wpacu-tools-intro wpacu-storage-intro--database">
                    <span class="dashicons dashicons-database" aria-hidden="true"></span>
                    <div class="wpacu-storage-intro__copy">
                        <h2><?php esc_html_e('Database & data architecture', 'wp-asset-clean-up'); ?></h2>
                        <p><?php esc_html_e('See where settings, optimization rules, temporary state and Pro-specific data are stored.', 'wp-asset-clean-up'); ?></p>
                    </div>
                    <div class="wpacu-storage-intro__badges" aria-label="<?php esc_attr_e('Database map characteristics', 'wp-asset-clean-up'); ?>">
                        <span><?php esc_html_e('Developer tool', 'wp-asset-clean-up'); ?></span>
                        <span><?php esc_html_e('Read-only', 'wp-asset-clean-up'); ?></span>
                    </div>
                </section>
                <?php
                $databaseStorageMap = isset($data['database_storage_map']) && is_array($data['database_storage_map'])
                    ? $data['database_storage_map']
                    : array();
                include __DIR__ . '/_admin-page-tools-database-map.php';
            }
        }

        if ($data['for'] === 'debug') {
	        $logPHPErrorsLocationFileSize = false;

            $isLogPHPErrors       = $data['error_log']['log_status'];
	        $logPHPErrorsLocation = $data['error_log']['log_file'];

	        $logPHPErrorsLocationFileSizeFormatted = '';

	        if ($logPHPErrorsLocation !== 'none set' && is_file($logPHPErrorsLocation)) {
		        $logPHPErrorsLocationFileSize = filesize($logPHPErrorsLocation);
		        $logPHPErrorsLocationFileSizeFormatted = MiscAdmin::formatBytes($logPHPErrorsLocationFileSize);
            }

            $debugModes = array(
                array(
                    'parameter'   => 'wpacu_debug',
                    'icon'        => 'admin-tools',
                    'title'       => __('Interactive debug', 'wp-asset-clean-up'),
                    'description' => __('Adds a troubleshooting panel to the bottom of the page. Use it to temporarily disable selected optimizations and inspect unloaded assets.', 'wp-asset-clean-up'),
                    'impact'      => __('Best for investigating a specific Asset CleanUp rule.', 'wp-asset-clean-up'),
                ),
                array(
                    'parameter'   => 'wpacu_no_load',
                    'icon'        => 'controls-pause',
                    'title'       => __('Disable Asset CleanUp', 'wp-asset-clean-up'),
                    'description' => sprintf(__('Loads the page as if %s were deactivated. The plugin menu in the top admin bar is also hidden.', 'wp-asset-clean-up'), WPACU_PLUGIN_TITLE),
                    'impact'      => __('Only Asset CleanUp optimizations are bypassed.', 'wp-asset-clean-up'),
                ),
                array(
                    'parameter'   => 'wpacu_clean_load',
                    'icon'        => 'visibility',
                    'title'       => __('Disable all optimizations', 'wp-asset-clean-up'),
                    'description' => __('Attempts to show the original CSS and JavaScript files before they are combined or altered by Asset CleanUp or another performance plugin.', 'wp-asset-clean-up'),
                    'impact'      => __('Use this when another optimization plugin may affect the result.', 'wp-asset-clean-up'),
                ),
            );
            ?>
            <div class="wpacu-debug-intro wpacu-tools-intro">
                <span class="wpacu-debug-intro-icon dashicons dashicons-sos" aria-hidden="true"></span>
                <div>
                    <h2><?php esc_html_e('Troubleshooting tools', 'wp-asset-clean-up'); ?></h2>
                    <p><?php esc_html_e('Check PHP errors or open the site with optimizations temporarily bypassed. These tools do not change your saved settings.', 'wp-asset-clean-up'); ?></p>
                </div>
            </div>

            <section class="wpacu-debug-log-card" aria-labelledby="wpacu-debug-log-title">
                <header class="wpacu-debug-section-header">
                    <span class="dashicons dashicons-media-text" aria-hidden="true"></span>
                    <div>
                        <h3 id="wpacu-debug-log-title"><?php esc_html_e('PHP error log', 'wp-asset-clean-up'); ?></h3>
                        <p><?php esc_html_e('PHP errors can explain timeouts, blank screens and internal server errors caused by any active plugin or theme.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </header>
                <div class="wpacu-debug-log-details">
                    <div class="wpacu-debug-log-detail">
                        <span><?php esc_html_e('Status', 'wp-asset-clean-up'); ?></span>
                        <strong class="wpacu-debug-status wpacu-debug-status--<?php echo $isLogPHPErrors ? 'enabled' : 'disabled'; ?>">
                            <?php $isLogPHPErrors ? esc_html_e('Enabled', 'wp-asset-clean-up') : esc_html_e('Disabled', 'wp-asset-clean-up'); ?>
                        </strong>
                    </div>
                    <div class="wpacu-debug-log-detail wpacu-debug-log-detail--path">
                        <span><?php esc_html_e('Location', 'wp-asset-clean-up'); ?></span>
                        <code><?php echo esc_html($logPHPErrorsLocation); ?></code>
                    </div>
                    <?php if ($logPHPErrorsLocationFileSize) { ?>
                        <div class="wpacu-debug-log-detail">
                            <span><?php esc_html_e('File size', 'wp-asset-clean-up'); ?></span>
                            <strong><?php echo wp_kses($logPHPErrorsLocationFileSizeFormatted, array('span' => array('style' => array(), 'class' => array()))); ?></strong>
                        </div>
                        <form class="wpacu-debug-log-download" method="post" action="">
                            <?php wp_nonce_field('wpacu_get_error_log', 'wpacu_get_error_log_nonce'); ?>
                            <input type="hidden" name="wpacu-get-error-log" value="1" />
                            <button type="submit" class="button button-primary">
                                <span class="dashicons dashicons-download" aria-hidden="true"></span>
                                <?php esc_html_e('Download error log', 'wp-asset-clean-up'); ?>
                            </button>
                        </form>
                    <?php } ?>
                </div>
            </section>

            <section class="wpacu-debug-modes" aria-labelledby="wpacu-debug-modes-title">
                <div class="wpacu-debug-modes-heading">
                    <h3 id="wpacu-debug-modes-title"><?php esc_html_e('Troubleshooting modes', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Open the homepage with one temporary diagnostic parameter. You can also add the same parameter to another URL on this site.', 'wp-asset-clean-up'); ?></p>
                </div>
                <div class="wpacu-debug-modes-grid">
                    <?php foreach ($debugModes as $debugMode) {
                        $debugModeUrl = add_query_arg($debugMode['parameter'], '', home_url('/'));
                        ?>
                        <article class="wpacu-debug-mode-card">
                            <header>
                                <span class="dashicons dashicons-<?php echo esc_attr($debugMode['icon']); ?>" aria-hidden="true"></span>
                                <div>
                                    <h4><?php echo esc_html($debugMode['title']); ?></h4>
                                    <code>?<?php echo esc_html($debugMode['parameter']); ?></code>
                                </div>
                            </header>
                            <p><?php echo esc_html($debugMode['description']); ?></p>
                            <div class="wpacu-debug-impact"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php echo esc_html($debugMode['impact']); ?></div>
                            <div class="wpacu-debug-mode-actions">
                                <a class="button button-primary" href="<?php echo esc_url($debugModeUrl); ?>" target="_blank" rel="noopener noreferrer">
                                    <span class="dashicons dashicons-external" aria-hidden="true"></span>
                                    <?php esc_html_e('Open', 'wp-asset-clean-up'); ?>
                                </a>
                                <button type="button"
                                        class="button wpacu-debug-copy-url"
                                        data-wpacu-copy-url="<?php echo esc_attr($debugModeUrl); ?>"
                                        data-copy-label="<?php esc_attr_e('Copy URL', 'wp-asset-clean-up'); ?>"
                                        data-copied-label="<?php esc_attr_e('Copied!', 'wp-asset-clean-up'); ?>">
                                    <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
                                    <span data-wpacu-copy-label><?php esc_html_e('Copy URL', 'wp-asset-clean-up'); ?></span>
                                </button>
                            </div>
                        </article>
                    <?php } ?>
                </div>
                <p class="screen-reader-text" data-wpacu-copy-status aria-live="polite"></p>
            </section>
            <script <?php echo Misc::getScriptTypeAttribute(); ?>>
                document.addEventListener('click', function (event) {
                    var button = event.target.closest('.wpacu-debug-copy-url');
                    if (!button) { return; }

                    var url = button.getAttribute('data-wpacu-copy-url');
                    var label = button.querySelector('[data-wpacu-copy-label]');
                    var status = document.querySelector('[data-wpacu-copy-status]');
                    var markAsCopied = function () {
                        label.textContent = button.getAttribute('data-copied-label');
                        status.textContent = button.getAttribute('data-copied-label') + ' ' + url;
                        window.setTimeout(function () {
                            label.textContent = button.getAttribute('data-copy-label');
                        }, 1800);
                    };

                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(url).then(markAsCopied);
                        return;
                    }

                    var input = document.createElement('textarea');
                    input.value = url;
                    input.setAttribute('readonly', 'readonly');
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    if (document.execCommand('copy')) { markAsCopied(); }
                    document.body.removeChild(input);
                });
            </script>
            <?php
        }

        if ($data['for'] === 'import_export') {
            $pluginsManagerFrontendRules  = ImportExport::getPluginsManagerRulesArray('plugins');
            $pluginsManagerDashboardRules = ImportExport::getPluginsManagerRulesArray('plugins_dash');
	            $maxImportFileBytes = ImportExport::getMaxImportFileBytes();
	            $maxImportFileSizeLabel = size_format($maxImportFileBytes);
            ?>
            <div class="wpacu-transfer-intro wpacu-tools-intro">
                <span class="wpacu-transfer-intro-icon dashicons dashicons-migrate" aria-hidden="true"></span>
                <div>
                    <h2><?php esc_html_e('Transfer configuration', 'wp-asset-clean-up'); ?></h2>
                    <p><?php esc_html_e('Move configuration between websites or download a JSON snapshot before making significant changes.', 'wp-asset-clean-up'); ?></p>
                </div>
            </div>

            <div class="wpacu-transfer-grid">
            <section id="wpacu-import-area" class="wpacu-export-import-area wpacu-transfer-card" aria-labelledby="wpacu-import-title">
                <header class="wpacu-transfer-card-header">
                    <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                    <div><h3 id="wpacu-import-title"><?php esc_html_e('Import configuration', 'wp-asset-clean-up'); ?></h3><p><?php esc_html_e('Apply settings and rules from a previously exported JSON file.', 'wp-asset-clean-up'); ?></p></div>
                </header>
                <form id="wpacu-import-form"
                      action="<?php echo esc_url(admin_url('admin.php?page='.WPACU_PLUGIN_ID.'_tools&wpacu_for='.$data['for'])); ?>"
                      method="post"
                      enctype="multipart/form-data">
                    <label class="wpacu-transfer-field-label" for="wpacu-import-file"><?php esc_html_e('Configuration file', 'wp-asset-clean-up'); ?></label>
                    <div class="wpacu-transfer-file-field">
	                        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) $maxImportFileBytes; ?>" />
	                        <input required="required"
	                               type="file"
	                               id="wpacu-import-file"
	                               name="wpacu_import_file"
	                               accept="application/json,.json"
	                               aria-describedby="wpacu-import-file-status"
	                               data-max-bytes="<?php echo (int) $maxImportFileBytes; ?>" />
	                        <small id="wpacu-import-file-status" aria-live="polite" data-wpacu-import-file-status
	                               data-empty-label="<?php echo esc_attr(sprintf(__('JSON files only · Maximum %s', 'wp-asset-clean-up'), $maxImportFileSizeLabel)); ?>"
	                               data-invalid-label="<?php esc_attr_e('Please select a valid .json file.', 'wp-asset-clean-up'); ?>"
	                               data-too-large-label="<?php echo esc_attr(sprintf(__('The selected file is larger than the allowed limit of %s.', 'wp-asset-clean-up'), $maxImportFileSizeLabel)); ?>"><?php echo esc_html(sprintf(__('JSON files only · Maximum %s', 'wp-asset-clean-up'), $maxImportFileSizeLabel)); ?></small>
                    </div>
                    <div class="wpacu-transfer-actions">
                        <button type="submit" class="button button-primary" disabled data-busy-label="<?php esc_attr_e('Importing...', 'wp-asset-clean-up'); ?>">
                            <span class="dashicons dashicons-upload" aria-hidden="true"></span>
					        <span data-wpacu-button-label><?php esc_html_e('Import configuration', 'wp-asset-clean-up'); ?></span>
                            <img class="wpacu-spinner" src="<?php echo includes_url('images/wpspin-2x.gif'); ?>" alt="" />
                        </button>
                    </div>
			        <?php wp_nonce_field('wpacu_do_import', 'wpacu_do_import_nonce'); ?>
                </form>

                <div class="wpacu-transfer-notice"><span class="dashicons dashicons-warning" aria-hidden="true"></span><p><strong><?php esc_html_e('Import behavior', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Matching option groups are replaced and object-level metadata is updated. Existing data that is not represented in the JSON file is not automatically removed. Test the website after importing; CSS/JS caches will be rebuilt.', 'wp-asset-clean-up'); ?></span></p></div>
            </section>

            <section id="wpacu-export-area" class="wpacu-export-import-area wpacu-transfer-card" aria-labelledby="wpacu-export-title">
                <header class="wpacu-transfer-card-header">
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    <div><h3 id="wpacu-export-title"><?php esc_html_e('Export configuration', 'wp-asset-clean-up'); ?></h3><p><?php esc_html_e('Choose one configuration group to download as JSON.', 'wp-asset-clean-up'); ?></p></div>
                </header>
                <form id="wpacu-export-form"
                      action="<?php echo esc_url(admin_url('admin.php?page='.WPACU_PLUGIN_ID.'_tools&wpacu_for='.$data['for'])); ?>"
                      method="post">
                    <label class="wpacu-transfer-field-label" for="wpacu-export-selection"><?php esc_html_e('Content to export', 'wp-asset-clean-up'); ?></label>
                    <select required="required" id="wpacu-export-selection" name="wpacu_export_for">
                            <option value="" data-description="<?php esc_attr_e('Select a configuration group to see what the export contains.', 'wp-asset-clean-up'); ?>"><?php esc_html_e('Select a configuration group...', 'wp-asset-clean-up'); ?></option>
                            <option value="settings" data-description="<?php esc_attr_e('Plugin preferences and optimization settings.', 'wp-asset-clean-up'); ?>"><?php esc_html_e('Settings', 'wp-asset-clean-up'); ?></option>

	                        <?php
	                        // [CRITICAL CSS]
	                        ?>
                                <option value="critical_css" data-description="<?php esc_attr_e('All saved Critical CSS rules and their configuration.', 'wp-asset-clean-up'); ?>"><?php esc_html_e('Critical CSS', 'wp-asset-clean-up'); ?></option>
	                        <?php
	                        // [/CRITICAL CSS]
	                        ?>

                            <option value="plugins_manager_frontend" <?php disabled(empty($pluginsManagerFrontendRules)); ?> data-description="<?php echo esc_attr(empty($pluginsManagerFrontendRules) ? __('No front-end rules are currently available to export.', 'wp-asset-clean-up') : __('Rules configured in Plugins Manager for the public-facing website.', 'wp-asset-clean-up')); ?>"><?php echo esc_html(empty($pluginsManagerFrontendRules) ? __('Plugins Manager — Front-end rules (No rules available)', 'wp-asset-clean-up') : __('Plugins Manager — Front-end rules', 'wp-asset-clean-up')); ?></option>

                            <?php if ( ! empty($pluginsManagerDashboardRules)) { ?>
                                <option value="plugins_manager_dashboard" data-description="<?php esc_attr_e('Rules configured in Plugins Manager for WordPress administration pages.', 'wp-asset-clean-up'); ?>"><?php esc_html_e('Plugins Manager — /wp-admin/ rules', 'wp-asset-clean-up'); ?></option>
                            <?php } ?>

                            <option value="everything" data-description="<?php esc_attr_e('All supported settings, rules and metadata in one JSON snapshot. Importing it updates matching data but does not remove every current record that is absent from the file.', 'wp-asset-clean-up'); ?>"><?php esc_html_e('Everything', 'wp-asset-clean-up'); ?></option>
                    </select>
                    <p class="wpacu-transfer-selection-description" data-wpacu-export-description aria-live="polite"><?php esc_html_e('Select a configuration group to see what the export contains.', 'wp-asset-clean-up'); ?></p>
                    <div class="wpacu-transfer-actions">
                        <button type="submit" class="button button-primary" disabled data-busy-label="<?php esc_attr_e('Preparing export...', 'wp-asset-clean-up'); ?>">
                            <span class="dashicons dashicons-download" aria-hidden="true"></span>
                            <span data-wpacu-button-label><?php esc_html_e('Download JSON', 'wp-asset-clean-up'); ?></span>
                        </button>
                    </div>
                    <?php wp_nonce_field('wpacu_do_export', 'wpacu_do_export_nonce'); ?>
                </form>
            </section>
            </div>
        <?php
        }
        ?>
	</div>
</div>
