<?php
use WpAssetCleanUp\Settings;

/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}

$tabIdArea = 'wpacu-setting-disable-xml-rpc';

$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea)
    ? 'style="display: table-cell;"'
    : '';

$disableXmlRpc = isset($data['disable_xmlrpc'])
    ? (string) $data['disable_xmlrpc']
    : 'keep_it_on';

if (! in_array($disableXmlRpc, array('keep_it_on', 'disable_pingback', 'disable_all'), true)) {
    $disableXmlRpc = 'keep_it_on';
}

$settingsInputName = WPACU_PLUGIN_ID . '_settings';
$inputStyle = Settings::getInputStyle($data);
$useEnhancedInputs = Settings::useEnhancedInputs($inputStyle);
?>

<div id="<?php echo esc_attr($tabIdArea); ?>"
     class="wpacu-settings-tab-content"
    <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>

    <main id="wpacu-xmlrpc-settings" class="wpacu-xmlrpc-page">
        <section class="wpacu-xmlrpc-panel" aria-labelledby="wpacuXmlRpcTitle">

            <header class="wpacu-xmlrpc-header">
                <div>
                    <div class="wpacu-xmlrpc-eyebrow">
                        <?php esc_html_e('WordPress connections', 'wp-asset-clean-up'); ?>
                    </div>

                    <h2 id="wpacuXmlRpcTitle">
                        <?php esc_html_e('Choose how XML-RPC should be handled', 'wp-asset-clean-up'); ?>
                    </h2>

                    <p>
                        <?php esc_html_e('XML-RPC is used by some external apps, services, and integrations. Select the least restrictive option that supports how this site is used.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>

                <div class="wpacu-xmlrpc-header-badge">
                    <?php esc_html_e('Advanced setting', 'wp-asset-clean-up'); ?>
                </div>
            </header>

            <div class="wpacu-xmlrpc-body">

                <section class="wpacu-xmlrpc-intro" aria-labelledby="wpacuXmlRpcCompatibilityTitle">
                    <div class="wpacu-xmlrpc-intro-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M12 2v4"></path>
                            <path d="M12 18v4"></path>
                            <path d="m4.93 4.93 2.83 2.83"></path>
                            <path d="m16.24 16.24 2.83 2.83"></path>
                            <path d="M2 12h4"></path>
                            <path d="M18 12h4"></path>
                            <path d="m4.93 19.07 2.83-2.83"></path>
                            <path d="m16.24 7.76 2.83-2.83"></path>
                        </svg>
                    </div>

                    <div>
                        <h3 id="wpacuXmlRpcCompatibilityTitle">
                            <?php esc_html_e('Compatibility comes first', 'wp-asset-clean-up'); ?>
                        </h3>

                        <p>
                            <?php esc_html_e('Keep XML-RPC enabled when Jetpack, a remote publishing app, or another integration depends on it. If XML-RPC is needed but pingbacks are not, the middle option makes a narrower change.', 'wp-asset-clean-up'); ?>
                        </p>
                    </div>
                </section>

                <fieldset class="wpacu-xmlrpc-fieldset">
                    <legend>
                        <?php esc_html_e('XML-RPC mode', 'wp-asset-clean-up'); ?>
                    </legend>

                    <?php if ($useEnhancedInputs) { ?>

                        <div class="wpacu-xmlrpc-choice-grid">

                            <label class="wpacu-xmlrpc-choice" for="wpacu_xmlrpc_keep_it_on">
                                <input id="wpacu_xmlrpc_keep_it_on"
                                       type="radio"
                                       aria-labelledby="wpacuXmlRpcKeepTitle"
                                       aria-describedby="wpacuXmlRpcKeepSubtitle wpacuXmlRpcKeepDescription"
                                       name="<?php echo esc_attr($settingsInputName); ?>[disable_xmlrpc]"
                                       value="keep_it_on"
                                    <?php checked($disableXmlRpc, 'keep_it_on'); ?>>

                                <span class="wpacu-xmlrpc-choice-card">

                                    <span class="wpacu-xmlrpc-choice-top">
                                        <span class="wpacu-xmlrpc-choice-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 stroke-width="2"
                                                 stroke-linecap="round"
                                                 stroke-linejoin="round">
                                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                                            </svg>
                                        </span>

                                        <span class="wpacu-xmlrpc-choice-heading">
                                            <span class="wpacu-xmlrpc-choice-title"
                                                  id="wpacuXmlRpcKeepTitle">
                                                <?php esc_html_e('Keep XML-RPC enabled', 'wp-asset-clean-up'); ?>

                                                <span class="wpacu-xmlrpc-badge" aria-hidden="true">
                                                    <?php esc_html_e('Default', 'wp-asset-clean-up'); ?>
                                                </span>
                                            </span>

                                            <span class="wpacu-xmlrpc-choice-subtitle"
                                                  id="wpacuXmlRpcKeepSubtitle">
                                                <?php esc_html_e('Maximum compatibility', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>
                                    </span>

                                    <span class="wpacu-xmlrpc-choice-description"
                                          id="wpacuXmlRpcKeepDescription">
                                        <?php esc_html_e('Leaves WordPress XML-RPC unchanged. Choose this when an external app, Jetpack, or another service relies on it.', 'wp-asset-clean-up'); ?>
                                    </span>

                                    <span class="wpacu-xmlrpc-effects">

                                        <span class="wpacu-xmlrpc-effect">
                                            <span class="wpacu-xmlrpc-effect-label">
                                                <?php esc_html_e('Authenticated methods', 'wp-asset-clean-up'); ?>
                                            </span>

                                            <span class="wpacu-xmlrpc-effect-value">
                                                <?php esc_html_e('Available', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>

                                        <span class="wpacu-xmlrpc-effect">
                                            <span class="wpacu-xmlrpc-effect-label">
                                                <?php esc_html_e('Pingback methods', 'wp-asset-clean-up'); ?>
                                            </span>

                                            <span class="wpacu-xmlrpc-effect-value">
                                                <?php esc_html_e('Available', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>

                                        <span class="wpacu-xmlrpc-effect">
                                            <span class="wpacu-xmlrpc-effect-label">
                                                <?php esc_html_e('Pingback tag', 'wp-asset-clean-up'); ?>
                                            </span>

                                            <span class="wpacu-xmlrpc-effect-value">
                                                <?php esc_html_e('Kept', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>

                                    </span>

                                </span>
                            </label>

                            <label class="wpacu-xmlrpc-choice" for="wpacu_xmlrpc_disable_pingback">
                                <input id="wpacu_xmlrpc_disable_pingback"
                                       type="radio"
                                       aria-labelledby="wpacuXmlRpcPingbackTitle"
                                       aria-describedby="wpacuXmlRpcPingbackSubtitle wpacuXmlRpcPingbackDescription"
                                       name="<?php echo esc_attr($settingsInputName); ?>[disable_xmlrpc]"
                                       value="disable_pingback"
                                    <?php checked($disableXmlRpc, 'disable_pingback'); ?>>

                                <span class="wpacu-xmlrpc-choice-card">

                                    <span class="wpacu-xmlrpc-choice-top">
                                        <span class="wpacu-xmlrpc-choice-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 stroke-width="2"
                                                 stroke-linecap="round"
                                                 stroke-linejoin="round">
                                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>
                                                <path d="M9 12h6"></path>
                                            </svg>
                                        </span>

                                        <span class="wpacu-xmlrpc-choice-heading">
                                            <span class="wpacu-xmlrpc-choice-title"
                                                  id="wpacuXmlRpcPingbackTitle">
                                                <?php esc_html_e('Disable pingbacks only', 'wp-asset-clean-up'); ?>

                                                <span class="wpacu-xmlrpc-badge">
                                                    <?php esc_html_e('Partial', 'wp-asset-clean-up'); ?>
                                                </span>
                                            </span>

                                            <span class="wpacu-xmlrpc-choice-subtitle"
                                                  id="wpacuXmlRpcPingbackSubtitle">
                                                <?php esc_html_e('Keep XML-RPC, block pingback methods', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>
                                    </span>

                                    <span class="wpacu-xmlrpc-choice-description"
                                          id="wpacuXmlRpcPingbackDescription">
                                        <?php esc_html_e('Keeps other XML-RPC methods available, while disabling pingbacks and removing their discovery output.', 'wp-asset-clean-up'); ?>
                                    </span>

                                    <span class="wpacu-xmlrpc-effects">

                                        <span class="wpacu-xmlrpc-effect">
                                            <span class="wpacu-xmlrpc-effect-label">
                                                <?php esc_html_e('Authenticated methods', 'wp-asset-clean-up'); ?>
                                            </span>

                                            <span class="wpacu-xmlrpc-effect-value">
                                                <?php esc_html_e('Available', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>

                                        <span class="wpacu-xmlrpc-effect">
                                            <span class="wpacu-xmlrpc-effect-label">
                                                <?php esc_html_e('Pingback methods', 'wp-asset-clean-up'); ?>
                                            </span>

                                            <span class="wpacu-xmlrpc-effect-value is-disabled">
                                                <?php esc_html_e('Disabled', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>

                                        <span class="wpacu-xmlrpc-effect">
                                            <span class="wpacu-xmlrpc-effect-label">
                                                <?php esc_html_e('Pingback tag', 'wp-asset-clean-up'); ?>
                                            </span>

                                            <span class="wpacu-xmlrpc-effect-value is-removed">
                                                <?php esc_html_e('Removed', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>

                                    </span>

                                </span>
                            </label>

                            <label class="wpacu-xmlrpc-choice" for="wpacu_xmlrpc_disable_all">
                                <input id="wpacu_xmlrpc_disable_all"
                                       type="radio"
                                       aria-labelledby="wpacuXmlRpcDisableAllTitle"
                                       aria-describedby="wpacuXmlRpcDisableAllSubtitle wpacuXmlRpcDisableAllDescription"
                                       name="<?php echo esc_attr($settingsInputName); ?>[disable_xmlrpc]"
                                       value="disable_all"
                                    <?php checked($disableXmlRpc, 'disable_all'); ?>>

                                <span class="wpacu-xmlrpc-choice-card">

                                    <span class="wpacu-xmlrpc-choice-top">
                                        <span class="wpacu-xmlrpc-choice-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 stroke-width="2"
                                                 stroke-linecap="round"
                                                 stroke-linejoin="round">
                                                <rect x="3" y="11" width="18" height="10" rx="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </span>

                                        <span class="wpacu-xmlrpc-choice-heading">
                                            <span class="wpacu-xmlrpc-choice-title"
                                                  id="wpacuXmlRpcDisableAllTitle">
                                                <?php esc_html_e('Disable authenticated XML-RPC methods', 'wp-asset-clean-up'); ?>

                                                <span class="wpacu-xmlrpc-badge wpacu-xmlrpc-badge--warning">
                                                    <?php esc_html_e('Most restrictive', 'wp-asset-clean-up'); ?>
                                                </span>
                                            </span>

                                            <span class="wpacu-xmlrpc-choice-subtitle"
                                                  id="wpacuXmlRpcDisableAllSubtitle">
                                                <?php esc_html_e('Block remote publishing and built-in pingbacks', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>
                                    </span>

                                    <span class="wpacu-xmlrpc-choice-description"
                                          id="wpacuXmlRpcDisableAllDescription">
                                        <?php esc_html_e('Disables WordPress XML-RPC methods that require authentication, together with the built-in pingback methods and discovery output. Integrations that rely on XML-RPC may stop working.', 'wp-asset-clean-up'); ?>
                                    </span>

                                    <span class="wpacu-xmlrpc-effects">

                                        <span class="wpacu-xmlrpc-effect">
                                            <span class="wpacu-xmlrpc-effect-label">
                                                <?php esc_html_e('Authenticated methods', 'wp-asset-clean-up'); ?>
                                            </span>

                                            <span class="wpacu-xmlrpc-effect-value is-disabled">
                                                <?php esc_html_e('Disabled', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>

                                        <span class="wpacu-xmlrpc-effect">
                                            <span class="wpacu-xmlrpc-effect-label">
                                                <?php esc_html_e('Pingback methods', 'wp-asset-clean-up'); ?>
                                            </span>

                                            <span class="wpacu-xmlrpc-effect-value is-disabled">
                                                <?php esc_html_e('Disabled', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>

                                        <span class="wpacu-xmlrpc-effect">
                                            <span class="wpacu-xmlrpc-effect-label">
                                                <?php esc_html_e('Pingback tag', 'wp-asset-clean-up'); ?>
                                            </span>

                                            <span class="wpacu-xmlrpc-effect-value is-removed">
                                                <?php esc_html_e('Removed', 'wp-asset-clean-up'); ?>
                                            </span>
                                        </span>

                                    </span>

                                </span>
                            </label>

                        </div>

                    <?php } else { ?>

                        <div class="wpacu-xmlrpc-native-options">

                            <label class="wpacu-xmlrpc-native-option"
                                   for="wpacu_xmlrpc_keep_it_on">

                                <input id="wpacu_xmlrpc_keep_it_on"
                                       type="radio"
                                       name="<?php echo esc_attr($settingsInputName); ?>[disable_xmlrpc]"
                                       value="keep_it_on"
                                       aria-describedby="wpacuXmlRpcKeepNativeDescription"
                                    <?php checked($disableXmlRpc, 'keep_it_on'); ?>>

                                <span>
                                    <strong>
                                        <?php esc_html_e('Keep XML-RPC enabled', 'wp-asset-clean-up'); ?>
                                    </strong>

                                    <span class="wpacu-xmlrpc-native-meta">
                                        <?php esc_html_e('Default; maximum compatibility', 'wp-asset-clean-up'); ?>
                                    </span>

                                    <span id="wpacuXmlRpcKeepNativeDescription"
                                          class="description">
                                        <?php esc_html_e('Leaves WordPress XML-RPC unchanged. Choose this when an external app, Jetpack, or another service relies on it.', 'wp-asset-clean-up'); ?>
                                    </span>
                                </span>

                            </label>

                            <label class="wpacu-xmlrpc-native-option"
                                   for="wpacu_xmlrpc_disable_pingback">

                                <input id="wpacu_xmlrpc_disable_pingback"
                                       type="radio"
                                       name="<?php echo esc_attr($settingsInputName); ?>[disable_xmlrpc]"
                                       value="disable_pingback"
                                       aria-describedby="wpacuXmlRpcPingbackNativeDescription"
                                    <?php checked($disableXmlRpc, 'disable_pingback'); ?>>

                                <span>
                                    <strong>
                                        <?php esc_html_e('Disable pingbacks only', 'wp-asset-clean-up'); ?>
                                    </strong>

                                    <span class="wpacu-xmlrpc-native-meta">
                                        <?php esc_html_e('Keep authenticated XML-RPC methods available', 'wp-asset-clean-up'); ?>
                                    </span>

                                    <span id="wpacuXmlRpcPingbackNativeDescription"
                                          class="description">
                                        <?php esc_html_e('Disables pingback methods and removes their discovery output while keeping the other XML-RPC methods available.', 'wp-asset-clean-up'); ?>
                                    </span>
                                </span>

                            </label>

                            <label class="wpacu-xmlrpc-native-option"
                                   for="wpacu_xmlrpc_disable_all">

                                <input id="wpacu_xmlrpc_disable_all"
                                       type="radio"
                                       name="<?php echo esc_attr($settingsInputName); ?>[disable_xmlrpc]"
                                       value="disable_all"
                                       aria-describedby="wpacuXmlRpcDisableAllNativeDescription"
                                    <?php checked($disableXmlRpc, 'disable_all'); ?>>

                                <span>
                                    <strong>
                                        <?php esc_html_e('Disable authenticated XML-RPC methods', 'wp-asset-clean-up'); ?>
                                    </strong>

                                    <span class="wpacu-xmlrpc-native-meta">
                                        <?php esc_html_e('Most restrictive', 'wp-asset-clean-up'); ?>
                                    </span>

                                    <span id="wpacuXmlRpcDisableAllNativeDescription"
                                          class="description">
                                        <?php esc_html_e('Disables methods that require authentication together with built-in pingbacks. Integrations that rely on XML-RPC may stop working.', 'wp-asset-clean-up'); ?>
                                    </span>
                                </span>

                            </label>

                        </div>

                    <?php } ?>
                </fieldset>

                <section class="wpacu-xmlrpc-output"
                         aria-labelledby="wpacuXmlRpcOutputTitle">

                    <div>
                        <h3 id="wpacuXmlRpcOutputTitle">
                            <?php esc_html_e('Affected discovery tag', 'wp-asset-clean-up'); ?>
                        </h3>

                        <p>
                            <?php esc_html_e('Either disable option removes the pingback methods, the X-Pingback response header, and this tag from the page source.', 'wp-asset-clean-up'); ?>
                        </p>
                    </div>

                    <code>&lt;link rel=&quot;pingback&quot; href=&quot;https://www.yourwebsite.com/xmlrpc.php&quot; /&gt;</code>

                </section>

                <aside class="wpacu-xmlrpc-note">
                    <svg viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round"
                         aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 16v-4"></path>
                        <path d="M12 8h.01"></path>
                    </svg>

                    <p>
                        <strong>
                            <?php esc_html_e('Not sure which option to use?', 'wp-asset-clean-up'); ?>
                        </strong>

                        <?php esc_html_e('Keep XML-RPC enabled. This is the safest compatibility choice. Disable only pingbacks when XML-RPC is required but pingback functionality is not.', 'wp-asset-clean-up'); ?>
                    </p>
                </aside>

                <?php if (\WpAssetCleanUp\Misc::maybeIsSiteGround()) { ?>

                    <aside class="wpacu-xmlrpc-note wpacu-xmlrpc-note--warning">
                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="M10.3 2.9 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.9a2 2 0 0 0-3.4 0Z"></path>
                            <path d="M12 9v4"></path>
                            <path d="M12 17h.01"></path>
                        </svg>

                        <p>
                            <strong>
                                <?php esc_html_e('SiteGround hosting detected.', 'wp-asset-clean-up'); ?>
                            </strong>

                            <?php esc_html_e('SiteGround blocks direct browser access to WordPress xmlrpc.php at the hosting level. A direct browser visit is therefore not a reliable test of whether an app can connect.', 'wp-asset-clean-up'); ?>

                            <a target="_blank"
                               rel="noopener noreferrer"
                               href="https://www.siteground.com/kb/xmlrpc-direct-browser-access-blocked">
                                <?php esc_html_e('Read SiteGround’s XML-RPC guidance', 'wp-asset-clean-up'); ?>
                            </a>
                        </p>
                    </aside>

                <?php } ?>

            </div>
        </section>
    </main>
</div>