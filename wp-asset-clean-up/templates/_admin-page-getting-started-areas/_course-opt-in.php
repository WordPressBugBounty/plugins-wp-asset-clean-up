<?php
if ( ! defined('ABSPATH') ) {
    exit;
}

$wpacu_course_status      = isset($wpacu_course_status) ? $wpacu_course_status : '';
$wpacu_course_notice      = isset($wpacu_course_notice) ? $wpacu_course_notice : '';
$wpacu_course_placement   = isset($wpacu_course_placement) ? $wpacu_course_placement : 'getting_started';
$wpacu_course_onboarding  = ! empty($wpacu_course_onboarding);
$wpacu_course_firstname   = isset($wpacu_course_firstname) ? $wpacu_course_firstname : '';
$wpacu_course_email       = isset($wpacu_course_email) ? $wpacu_course_email : '';
$wpacu_course_privacy_url = 'https://www.gabelivan.com/privacy-policy/';

$wpacu_course_include_script = ! isset($wpacu_course_include_script) || $wpacu_course_include_script;
$wpacu_course_quick_setup    = ! empty($wpacu_course_quick_setup);
$wpacu_course_already_joined = $wpacu_course_quick_setup && $wpacu_course_status === 'subscribed';
if ($wpacu_course_quick_setup) {
    $wpacu_course_manager_url = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager');
}
?>
<section class="wpacu-gs-course-optin<?php if ($wpacu_course_quick_setup) { echo ' wpacu-setup-course'; } ?>" aria-label="<?php
    if ($wpacu_course_quick_setup) {
        esc_attr_e('Asset CleanUp email course and updates', 'wp-asset-clean-up');
    } else {
        esc_attr_e('Asset CleanUp email course', 'wp-asset-clean-up');
    }
?>">
    <?php if ($wpacu_course_quick_setup && $wpacu_course_status === 'dismissed') : ?>
        <h2><?php esc_html_e('Email course & updates', 'wp-asset-clean-up'); ?></h2>
    <?php endif; ?>
    <div class="wpacu-gs-course-optin-status" role="status" aria-live="polite" aria-atomic="true">
        <?php if ($wpacu_course_status === 'pending' && $wpacu_course_quick_setup) : ?>
            <div class="wpacu-setup-confirmation">
                <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                <div>
                    <span class="wpacu-setup-confirmation-step"><?php esc_html_e('One more step', 'wp-asset-clean-up'); ?></span>
                    <h2><?php esc_html_e('Confirm your email address', 'wp-asset-clean-up'); ?></h2>
                    <p><?php
                        if ( ! empty($wpacu_course_submitted_email) ) {
                            /* translators: %s is the email address the visitor submitted. */
                            printf(
                                esc_html__('Check the inbox for %s and follow the confirmation link to start receiving the course and updates.', 'wp-asset-clean-up'),
                                '<strong><bdi>' . esc_html($wpacu_course_submitted_email) . '</bdi></strong>'
                            );
                        } else {
                            esc_html_e('Open your confirmation email and click the link to start receiving the course and updates.', 'wp-asset-clean-up');
                        }
                    ?></p>
                    <p class="wpacu-setup-confirmation-help"><?php esc_html_e('Check your spam folder if you don’t see the message. If you already receive both the course and updates, you’re all set.', 'wp-asset-clean-up'); ?></p>
                </div>
            </div>
        <?php elseif ($wpacu_course_status === 'pending') : ?>
            <div class="wpacu-gs-course-optin-message is-success">
                <strong><?php esc_html_e('Your course request has been received.', 'wp-asset-clean-up'); ?></strong>
                <?php if ( ! empty($wpacu_course_submitted_email)) : ?>
                    <span><?php esc_html_e('Email address:', 'wp-asset-clean-up'); ?> <strong><?php echo esc_html($wpacu_course_submitted_email); ?></strong></span>
                <?php endif; ?>
                <span><?php esc_html_e('If confirmation is needed, you’ll receive an email shortly. Check your inbox and spam folder. If you’re already subscribed, no further action is needed. You can hide this reminder below.', 'wp-asset-clean-up'); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($wpacu_course_notice === 'error') : ?>
            <div class="wpacu-gs-course-optin-message is-error">
                <strong><?php esc_html_e('The request could not be completed.', 'wp-asset-clean-up'); ?></strong>
                <span><?php esc_html_e('Please review the fields or try again later.', 'wp-asset-clean-up'); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($wpacu_course_quick_setup && $wpacu_course_status === 'dismissed') : ?>
        <a class="button button-primary wpacu-setup-course-manager" href="<?php echo esc_url($wpacu_course_manager_url); ?>"><?php esc_html_e('Open CSS/JS Manager', 'wp-asset-clean-up'); ?></a>
    <?php endif; ?>
    <?php if ($wpacu_course_status === 'dismissed') : ?>
        <p><?php esc_html_e('The course invitation is hidden. You can bring it back at any time.', 'wp-asset-clean-up'); ?></p>
        <form class="wpacu-gs-course-optin-restore" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
              data-sending-text="<?php esc_attr_e('Showing…', 'wp-asset-clean-up'); ?>"
              data-error-text="<?php esc_attr_e('The request could not be completed. Please try again.', 'wp-asset-clean-up'); ?>">
            <input type="hidden" name="action" value="wpacu_lite_course_optin_restore" />
            <?php if ( ! empty($wpacu_course_quick_setup) ) { ?><input type="hidden" name="wpacu_course_return_to" value="quick_setup"><?php } ?>
            <?php wp_nonce_field('wpacu_lite_course_optin_restore', 'wpacu_lite_course_optin_restore_nonce'); ?>
            <button type="submit" class="wpacu-gs-course-optin-hide"><?php esc_html_e('Show invitation', 'wp-asset-clean-up'); ?></button>
        </form>
    <?php else : ?>
        <div class="wpacu-gs-course-optin-layout<?php echo $wpacu_course_status !== 'pending' && ! $wpacu_course_quick_setup ? ' wpacu-gs-course-optin-layout--split' : ''; ?>">
        <?php if ($wpacu_course_status !== 'pending') : ?>
        <div class="wpacu-gs-course-optin-heading">
            <?php if ($wpacu_course_quick_setup) : ?>
            <h2 id="wpacu-gs-course-optin-title"><?php echo $wpacu_course_already_joined
                ? esc_html__('Get Asset CleanUp news and offers', 'wp-asset-clean-up')
                : esc_html__('Get Asset CleanUp tips, updates and offers', 'wp-asset-clean-up'); ?></h2>
            <ul class="wpacu-setup-course-benefits">
                <?php if ($wpacu_course_already_joined) : ?>
                <li><span class="dashicons dashicons-yes" aria-hidden="true"></span><span><?php esc_html_e('Use the same email address to add updates without restarting your course. A new address can receive all seven lessons.', 'wp-asset-clean-up'); ?></span></li>
                <?php else : ?>
                <li><span class="dashicons dashicons-yes" aria-hidden="true"></span><span><strong><?php esc_html_e('Start with seven practical lessons.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Learn which CSS and JavaScript files to unload and how to test each change.', 'wp-asset-clean-up'); ?></span></li>
                <?php endif; ?>
                <li><span class="dashicons dashicons-yes" aria-hidden="true"></span><span><strong><?php esc_html_e('Important fixes and security updates.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Hear when a release addresses an issue that may affect your site.', 'wp-asset-clean-up'); ?></span></li>
                <li><span class="dashicons dashicons-yes" aria-hidden="true"></span><span><?php esc_html_e('Get news of major Asset CleanUp releases and occasional offers.', 'wp-asset-clean-up'); ?></span></li>
            </ul>
            <?php else : ?>
            <span class="wpacu-gs-course-optin-eyebrow"><?php esc_html_e('Optional email guidance', 'wp-asset-clean-up'); ?></span>
            <h2 id="wpacu-gs-course-optin-title"><?php esc_html_e('Not sure which CSS/JS files you can safely unload?', 'wp-asset-clean-up'); ?></h2>
            <p><?php esc_html_e('Learn a step-by-step workflow with seven free emails over 18 days, starting with Test Mode and one safe unload.', 'wp-asset-clean-up'); ?></p>
            <p class="wpacu-gs-course-optin-optional"><?php esc_html_e('This course is optional. Asset CleanUp Lite works fully without subscribing.', 'wp-asset-clean-up'); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="wpacu-gs-course-optin-content">
        <?php if ($wpacu_course_status === 'pending') : ?>
        <?php if ($wpacu_course_quick_setup) : ?><div class="wpacu-setup-receipt-actions"><?php endif; ?>
        <details class="wpacu-gs-course-optin-disclosure"<?php if ($wpacu_course_notice === 'error') { echo ' open'; } ?>>
            <summary><span><?php esc_html_e('Correct email or request another confirmation', 'wp-asset-clean-up'); ?></span></summary>
        <?php endif; ?>

            <div class="wpacu-gs-course-optin-body">
                <?php if ($wpacu_course_status === 'pending') : ?>
                    <p class="wpacu-gs-course-optin-optional"><?php esc_html_e('This course is optional. Asset CleanUp Lite works fully without subscribing.', 'wp-asset-clean-up'); ?></p>
                    <p><?php esc_html_e('Enter the correct email address, or use the same address to request another confirmation. Then confirm your consent again. A new request does not cancel an earlier subscription.', 'wp-asset-clean-up'); ?></p>
                <?php endif; ?>

                <form class="wpacu-gs-course-optin-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                      data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                      data-sending-text="<?php esc_attr_e('Sending…', 'wp-asset-clean-up'); ?>"
                      data-error-text="<?php esc_attr_e('The request could not be completed. Please try again.', 'wp-asset-clean-up'); ?>">
                    <input type="hidden" name="action" value="wpacu_lite_course_optin" />
                    <?php if ($wpacu_course_quick_setup) : ?>
                        <input type="hidden" name="wpacu_course_return_to" value="quick_setup">
                        <?php wp_nonce_field('wpacu_lite_course_updates_consent', 'wpacu_lite_course_updates_nonce', false); ?>
                    <?php endif; ?>
                    <input type="hidden" name="wpacu_course_placement" value="<?php echo esc_attr($wpacu_course_placement); ?>" />
                    <?php wp_nonce_field('wpacu_lite_course_optin', 'wpacu_lite_course_optin_nonce'); ?>

                    <div class="wpacu-gs-course-optin-fields">
                        <div class="wpacu-gs-course-optin-field">
                            <label for="wpacu-course-firstname"><?php esc_html_e('First name', 'wp-asset-clean-up'); ?> <span><?php esc_html_e('(optional)', 'wp-asset-clean-up'); ?></span></label>
                            <input id="wpacu-course-firstname" name="firstname" type="text" value="<?php echo esc_attr($wpacu_course_firstname); ?>" autocomplete="given-name" maxlength="100" aria-describedby="wpacu-course-firstname-description" />
                            <span id="wpacu-course-firstname-description" class="wpacu-gs-course-optin-description"><?php echo $wpacu_course_quick_setup ? esc_html__('Used to personalize your emails.', 'wp-asset-clean-up') : esc_html__('Used only to personalize the course emails.', 'wp-asset-clean-up'); ?></span>
                        </div>

                        <div class="wpacu-gs-course-optin-field">
                            <label for="wpacu-course-email"><?php esc_html_e('Email address', 'wp-asset-clean-up'); ?></label>
                            <input id="wpacu-course-email" name="email" type="email" value="<?php echo esc_attr($wpacu_course_email); ?>" autocomplete="email" required aria-describedby="wpacu-course-email-description wpacu-course-email-mx-warning" data-mx-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-mx-nonce="<?php echo esc_attr(wp_create_nonce('wpacu_lite_course_optin_mx')); ?>" />
                            <span id="wpacu-course-email-description" class="wpacu-gs-course-optin-description"><?php echo $wpacu_course_quick_setup ? esc_html__('New subscriptions start after you confirm by email.', 'wp-asset-clean-up') : esc_html__('A confirmation link is sent before any lesson.', 'wp-asset-clean-up'); ?></span>
                            <?php
                            ?>
                            <span id="wpacu-course-email-mx-warning" class="wpacu-gs-course-optin-mx-warning" role="status" aria-live="polite" hidden><?php esc_html_e('We could not find a mail server for this email domain. Check for a typo. You can still submit if the address is correct.', 'wp-asset-clean-up'); ?></span>
                        </div>
                    </div>

                    <div class="wpacu-gs-course-optin-honeypot" aria-hidden="true">
                        <label for="wpacu-course-company"><?php esc_html_e('Company', 'wp-asset-clean-up'); ?></label>
                        <input id="wpacu-course-company" name="company" type="text" tabindex="-1" autocomplete="off" />
                    </div>

                    <?php if ($wpacu_course_quick_setup) : ?>
                    <p class="wpacu-setup-course-consent" id="wpacu-setup-course-consent">
                        <?php esc_html_e('By signing up, you agree to receive the seven lessons and occasional Asset CleanUp news, security updates and offers by email. You can change your preferences or unsubscribe at any time.', 'wp-asset-clean-up'); ?>
                        <a href="<?php echo esc_url($wpacu_course_privacy_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Privacy Policy', 'wp-asset-clean-up'); ?></a>
                    </p>
                    <?php else : ?>
                    <div class="wpacu-gs-course-optin-consent">
                        <input id="wpacu-course-consent" name="consent" type="checkbox" value="1" required />
                        <label for="wpacu-course-consent">
                            <?php esc_html_e('Send me seven practical emails over 18 days. They include occasional information about Asset CleanUp Pro. I can unsubscribe at any time.', 'wp-asset-clean-up'); ?>
                            <a href="<?php echo esc_url($wpacu_course_privacy_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Privacy Policy', 'wp-asset-clean-up'); ?></a>
                        </label>
                    </div>
                    <?php endif; ?>

                    <div class="wpacu-gs-course-optin-actions<?php if ($wpacu_course_quick_setup) { echo ' wpacu-setup-course-actions'; } ?>">
                        <?php if ($wpacu_course_quick_setup && $wpacu_course_status !== 'pending') : ?>
                            <button class="button button-primary" type="submit" name="consent" value="1" aria-describedby="wpacu-setup-course-consent">
                                <span class="dashicons dashicons-email-alt wpacu-course-submit-icon" aria-hidden="true"></span>
                                <span class="wpacu-course-submit-label"><?php esc_html_e('Count me in for updates', 'wp-asset-clean-up'); ?></span>
                            </button>
                            <a class="wpacu-setup-course-decline" href="<?php echo esc_url($wpacu_course_manager_url); ?>"><?php esc_html_e('No thanks, open CSS/JS Manager', 'wp-asset-clean-up'); ?></a>
                        <?php else : ?>
                            <button class="wpacu-gs-button wpacu-gs-button--primary" type="submit"<?php if ($wpacu_course_quick_setup) { ?> name="consent" value="1" aria-describedby="wpacu-setup-course-consent"<?php } ?>><span class="dashicons dashicons-welcome-learn-more wpacu-course-submit-icon" aria-hidden="true"></span><span class="wpacu-course-submit-label"><?php echo $wpacu_course_status === 'pending' ? esc_html__('Send confirmation email', 'wp-asset-clean-up') : esc_html__('Get the free course', 'wp-asset-clean-up'); ?></span></button>
                        <?php endif; ?>
                        <?php if ($wpacu_course_onboarding) : ?>
                            <button class="wpacu-gs-button wpacu-gs-course-optin-continue" type="submit" form="wpacu-course-continue-form"><?php esc_html_e('Continue to setup', 'wp-asset-clean-up'); ?></button>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if ($wpacu_course_onboarding) : ?>
                    <form id="wpacu-course-continue-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="wpacu_lite_course_continue" />
                        <?php wp_nonce_field('wpacu_lite_course_continue', 'wpacu_lite_course_continue_nonce'); ?>
                    </form>
                <?php endif; ?>

            </div>
        <?php if ($wpacu_course_status === 'pending') : ?>
        </details>
        <?php if ($wpacu_course_quick_setup) : ?>
            <a class="wpacu-setup-course-manager" href="<?php echo esc_url($wpacu_course_manager_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open CSS/JS Manager', 'wp-asset-clean-up'); ?></a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($wpacu_course_status === 'pending') : ?>
        <form class="wpacu-gs-course-optin-dismiss" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
              data-sending-text="<?php esc_attr_e('Hiding…', 'wp-asset-clean-up'); ?>"
              data-error-text="<?php esc_attr_e('The request could not be completed. Please try again.', 'wp-asset-clean-up'); ?>">
            <input type="hidden" name="action" value="wpacu_lite_course_optin_dismiss" />
            <?php if ( ! empty($wpacu_course_quick_setup) ) { ?><input type="hidden" name="wpacu_course_return_to" value="quick_setup"><?php } ?>
            <?php wp_nonce_field('wpacu_lite_course_optin_dismiss', 'wpacu_lite_course_optin_dismiss_nonce'); ?>
            <button type="submit" class="wpacu-gs-course-optin-hide"><?php esc_html_e('Hide this reminder', 'wp-asset-clean-up'); ?></button>
        </form>
        <?php endif; ?>
        </div>
        </div>
    <?php endif; ?>
    <?php
    ?>
</section>
<?php
?>
<?php if ($wpacu_course_include_script) : ?>
<script>
(function () {
    function initializeEmailCheck(root) {
        var field = root.querySelector('#wpacu-course-email');
        var warning = root.querySelector('#wpacu-course-email-mx-warning');
        if (!field || !warning) { return; }

        var timer;
        var request;
        var sequence = 0;
        var cache = Object.create(null);

        function getDomain() {
            var email = field.value.trim();
            if (!email || !field.validity.valid) { return ''; }
            return email.slice(email.lastIndexOf('@') + 1).toLowerCase();
        }

        function scheduleCheck(delay) {
            window.clearTimeout(timer);
            sequence++;
            if (request) { request.abort(); request = null; }
            warning.hidden = true;

            var domain = getDomain();
            if (!domain) { return; }
            if (Object.prototype.hasOwnProperty.call(cache, domain)) {
                warning.hidden = cache[domain] !== 'missing';
                return;
            }

            var current = sequence;
            timer = window.setTimeout(function () {
                var xhr = new XMLHttpRequest();
                request = xhr;
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4 || current !== sequence || domain !== getDomain()) { return; }
                    if (xhr.status < 200 || xhr.status >= 300) { return; }
                    try {
                        var result = JSON.parse(xhr.responseText);
                        var status = result && result.success && result.data ? result.data.status : 'unknown';
                        if (status === 'valid' || status === 'missing') {
                            cache[domain] = status;
                            warning.hidden = status !== 'missing';
                        }
                    } catch (error) { /* An unavailable check must never prevent submission. */ }
                };
                try {
                    xhr.open('POST', field.getAttribute('data-mx-url'), true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                    xhr.timeout = 6000;
                    xhr.send('action=wpacu_lite_course_optin_mx'
                        + '&wpacu_lite_course_optin_mx_nonce=' + encodeURIComponent(field.getAttribute('data-mx-nonce'))
                        + '&domain=' + encodeURIComponent(domain));
                } catch (error) { /* Keep the normal form usable if AJAX is unavailable. */ }
            }, delay);
        }

        field.addEventListener('input', function () { scheduleCheck(600); });
        field.addEventListener('change', function () { scheduleCheck(0); });
        if (!root.querySelector('input[name="wpacu_course_return_to"][value="quick_setup"]')) {
            field.addEventListener('blur', function () { scheduleCheck(0); });
            scheduleCheck(0);
        }

        return function () {
            window.clearTimeout(timer);
            sequence++;
            if (request) { request.abort(); }
    };
    }

    function initializeCourse(root) {
        if (!root || root.getAttribute('data-course-initialized') === '1') { return; }
        if (!window.FormData || !window.XMLHttpRequest) { return; }
        root.setAttribute('data-course-initialized', '1');

        var cancelEmailCheck = initializeEmailCheck(root);
        Array.prototype.forEach.call(root.querySelectorAll('form[data-ajax-url]'), function (form) {
            initializeCourseForm(root, form, cancelEmailCheck);
        });
    }

    function initializeCourseForm(root, form, cancelEmailCheck) {
        var button = form.querySelector('button[type="submit"]');
        // Browsers without submitter support use the native POST, including the clicked button.
        if (button.name === 'consent' && (!window.SubmitEvent || !('submitter' in window.SubmitEvent.prototype))) { return; }
        var status = root.querySelector('.wpacu-gs-course-optin-status');
        var labelElement = button.querySelector('.wpacu-course-submit-label') || button;
        var label = labelElement.textContent;
        var busy = false;
        var controls = [];

        function setBusy(value) {
            busy = value;
            if (value) {
                controls = Array.prototype.map.call(root.querySelectorAll('input, button'), function (control) {
                    var previous = {element: control, disabled: control.disabled};
                    control.disabled = true;
                    return previous;
                });
                form.setAttribute('aria-busy', 'true');
                root.setAttribute('aria-busy', 'true');
                button.classList.add('is-loading');
                labelElement.textContent = form.getAttribute('data-sending-text');
            } else {
                controls.forEach(function (control) { control.element.disabled = control.disabled; });
                form.removeAttribute('aria-busy');
                root.removeAttribute('aria-busy');
                button.classList.remove('is-loading');
                labelElement.textContent = label;
            }
        }

        function clearErrors() {
            Array.prototype.forEach.call(status.querySelectorAll('.is-error'), function (error) {
                error.parentNode.removeChild(error);
            });
        }

        function showError(message) {
            clearErrors();
            var error = document.createElement('div');
            error.className = 'wpacu-gs-course-optin-message is-error';
            error.setAttribute('role', 'alert');
            error.textContent = message || form.getAttribute('data-error-text');
            status.appendChild(error);
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (busy || root.getAttribute('aria-busy') === 'true') { return; }
            if (button.name === 'consent' && event.submitter !== button) { return; }
            if (!form.checkValidity()) {
                if (form.reportValidity) { form.reportValidity(); }
                return;
            }

            // Capture values before disabling fields; Continue belongs to a separate form.
            var data = new FormData(form);
            if (button.name === 'consent') {
                data.append(button.name, button.value);
            }
            var xhr = new XMLHttpRequest();
            var finished = false;
            clearErrors();
            setBusy(true);
            if (cancelEmailCheck) { cancelEmailCheck(); }

            function fail(message) {
                if (finished) { return; }
                finished = true;
                setBusy(false);
                showError(message);
            }

            xhr.onload = function () {
                if (finished) { return; }
                var result;
                try { result = JSON.parse(xhr.responseText); } catch (error) { fail(); return; }
                if (xhr.status < 200 || xhr.status >= 300 || !result || result.success !== true
                    || !result.data || typeof result.data.html !== 'string') {
                    fail(result && result.data && typeof result.data.message === 'string' ? result.data.message : '');
                    return;
                }

                // The authenticated local endpoint renders this HTML with escaped fields.
                var container = document.createElement('div');
                container.innerHTML = result.data.html;
                var replacement = container.querySelector('#wpacu-lite-course-invitation');
                if (!replacement) { fail(); return; }

                finished = true;
                setBusy(false);
                if (!root.parentNode) { return; }
                var scrollX = window.pageXOffset;
                var scrollY = window.pageYOffset;
                root.parentNode.replaceChild(replacement, root);
                initializeCourse(replacement);

                // Keep keyboard focus in the updated panel without moving the viewport
                // to the beginning of Getting Started (or away from the Help form).
                var target = replacement.querySelector('.wpacu-setup-confirmation h2, .wpacu-gs-course-optin-message, .wpacu-gs-course-optin-restore button, #wpacu-gs-course-optin-title');
                if (target) {
                    if (target.tagName !== 'BUTTON') { target.setAttribute('tabindex', '-1'); }
                    try { target.focus({preventScroll: true}); } catch (error) { target.focus(); }
                }
                var scrollBehavior = document.documentElement.style.scrollBehavior;
                document.documentElement.style.scrollBehavior = 'auto';
                window.scrollTo(scrollX, scrollY);
                document.documentElement.style.scrollBehavior = scrollBehavior;
            };
            xhr.onerror = function () { fail(); };
            xhr.ontimeout = function () { fail(); };
            xhr.onabort = function () { fail(); };

            try {
                xhr.open('POST', form.getAttribute('data-ajax-url'), true);
                xhr.timeout = 20000;
                xhr.send(data);
            } catch (error) { fail(); }
        });
    }

    initializeCourse(document.getElementById('wpacu-lite-course-invitation'));
}());
</script>
<?php endif; ?>
