<?php
if (!isset($totalPlugins, $pluginsWithSavedRules, $isDashboardPreview)) { exit; }
?>
    <section class="wpacu-lite-pm-overview" aria-labelledby="wpacuLitePmOverviewTitle">
        <div class="wpacu-lite-pm-overview-copy">
            <span class="wpacu-lite-pm-eyebrow"><?php esc_html_e('Interactive Pro preview', 'wp-asset-clean-up'); ?></span>
            <h2 id="wpacuLitePmOverviewTitle"><?php echo $isDashboardPreview
                    ? esc_html__('Control plugin execution on selected Dashboard requests', 'wp-asset-clean-up')
                    : esc_html__('Load each plugin only where its functionality is needed', 'wp-asset-clean-up'); ?></h2>
            <p><?php echo $isDashboardPreview
                    ? esc_html__('Explore how Pro can conditionally skip complete plugins on matching admin pages, while preserving explicit exceptions for trusted users and workflows.', 'wp-asset-clean-up')
                    : esc_html__('Explore how Pro can prevent a complete plugin from running on selected public requests—not only its CSS and JavaScript—and add precise exceptions where it must remain active.', 'wp-asset-clean-up'); ?></p>
        </div>

        <div class="wpacu-lite-pm-overview-stats" aria-label="<?php echo esc_attr__('Preview summary', 'wp-asset-clean-up'); ?>">
            <div><strong><?php echo (int)$totalPlugins; ?></strong><span><?php esc_html_e('active plugins', 'wp-asset-clean-up'); ?></span></div>
            <div><strong><?php echo (int)$pluginsWithSavedRules; ?></strong><span><?php esc_html_e('with preserved Pro rules', 'wp-asset-clean-up'); ?></span></div>
            <div><strong>0</strong><span><?php esc_html_e('rules executed by Lite', 'wp-asset-clean-up'); ?></span></div>
        </div>
    </section>
