<?php
if ( ! defined('ABSPATH') || ! isset($courseUrl) || defined('WPACU_PRO_PLUGIN_VERSION')) { exit; }
?>
<aside id="wpacu-lite-course-after-save" class="wpacu-lite-course-context" aria-label="<?php esc_attr_e('Optional email course', 'wp-asset-clean-up'); ?>">
    <div>
        <strong><?php esc_html_e('Want help choosing your next unload?', 'wp-asset-clean-up'); ?></strong>
        <p><?php esc_html_e('Get the free seven-lesson course and follow a step-by-step optimization workflow.', 'wp-asset-clean-up'); ?></p>
    </div>
    <a class="button wpacu-lite-course-context__cta" href="<?php echo esc_url($courseUrl); ?>"><span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span><?php esc_html_e('Get the free course', 'wp-asset-clean-up'); ?></a>
    <button type="button" class="wpacu-lite-course-context__dismiss"><?php esc_html_e('Dismiss', 'wp-asset-clean-up'); ?></button>
</aside>
<script>
(function () {
    var invitation = document.getElementById('wpacu-lite-course-after-save');
    if (!invitation) { return; }
    invitation.querySelector('button').addEventListener('click', function () {
        invitation.hidden = true;
    });
}());
</script>
