<?php
namespace WpAssetCleanUpLite\Admin;

use WpAssetCleanUp\Menu;

/**
 * Optional, contextual email-course invitation for Asset CleanUp Lite.
 */
class CourseOptIn
{
    /**
     *
     */
    const CONSENT_VERSION = '2026-10-25-v1';

    /**
     * Quick Setup offers both the course and subsequent Asset CleanUp emails.
     */
    const UPDATES_CONSENT_VERSION = '2026-11-21-v2';

    /**
     *
     */
    const META_KEY = '_' . WPACU_PLUGIN_ID . '_lite_course_optin';

    /**
     *
     */
    const INVITATION_SEEN_KEY = '_' . WPACU_PLUGIN_ID . '_lite_course_invitation_seen';

    /**
     *
     */
    const ONBOARDING_KEY = '_' . WPACU_PLUGIN_ID . '_lite_course_onboarding';

    /**
     *
     */
    const ENDPOINT = 'https://www.gabelivan.com/wp-json/wpacu-mailster/v1/course-subscribe';

    /**
     * @var bool
     */
    private $showContextInvitation = false;

    /**
     * Keep the small, Lite-only stylesheet local to the screen that uses it.
     */
    public function __construct()
    {
        add_action('admin_head', array($this, 'maybeOutputStyles'));

        }

    /**
     * @return bool
     */
    private function canUseCourse()
    {
        if ($this->isQuickSetupPost()) {
            // A form left open in DEV must not submit or change consent in a release build.
            return false;
        }

        return defined('WPACU_LITE_PLUGIN_VERSION') && ! defined('WPACU_PRO_PLUGIN_VERSION')
            && is_user_logged_in() && Menu::userCanAccessPlugin();
    }

    /**
     * @return bool
     */
    private function shouldShowOnboarding()
    {
        if ( ! $this->canUseCourse()) {
            return false;
        }

        $user_id = get_current_user_id();
        $state   = get_user_meta($user_id, self::META_KEY, true);

        return get_user_meta($user_id, self::ONBOARDING_KEY, true) === 'offered'
            && ! (is_array($state) && ! empty($state['status']));
    }

    /**
     * Offer the ordinary Getting Started course invitation to the activating administrator.
     *
     * @return void
     */
    public static function offerOnFirstActivation()
    {
        $user_id = get_current_user_id();
        $state   = get_user_meta($user_id, self::META_KEY, true);

        if (get_user_meta($user_id, self::ONBOARDING_KEY, true) || (is_array($state) && ! empty($state['status']))) {
            return;
        }

        update_user_meta($user_id, self::ONBOARDING_KEY, 'offered');
    }

    /**
     * @param array $config
     *
     * @return void
     */
    public function renderOnboarding($config = array())
    {
        if ($this->shouldShowOnboarding()) {
            $config['onboarding'] = true;
            $this->render($config);
        }
    }

    /**
     * Keep the ordinary invitation or confirmation reminder below the guide.
     *
     * @param array $config
     *
     * @return void
     */
    public function renderGettingStarted($config = array())
    {
        if ( ! $this->shouldShowOnboarding()) {
            $this->render($config);
        }
    }

    /**
     * @return void
     */
    public function maybeOutputStyles()
    {
        $page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : '';
        if ($this->canUseCourse() && in_array($page, array(
            WPACU_PLUGIN_ID . '_getting_started', WPACU_PLUGIN_ID . '_get_help', WPACU_PLUGIN_ID . '_assets_manager'
        ), true)) {
            $this->outputStyles();
        }
    }

    /**
     * Print styles scoped to the Lite course and contextual invitation.
     *
     * @return void
     */
    public function outputStyles()
    {
        ?>
<style id="wpacu-lite-course-optin-css">
#wpacu-getting-started-page-wrap {
  scroll-margin-top: 48px;
}

#wpacu-getting-started-page-wrap > .wpacu-lite-course--onboarding ~ :not(script):not(style) {
  opacity: 0.4;
}

#wpacu-getting-started-page-wrap > .wpacu-lite-course--onboarding ~ :focus-within {
  opacity: 1;
}

.wpacu-lite-course .wpacu-gs-course-optin {
  margin: 28px 0 18px;
  padding: 21px 23px;
  overflow-wrap: anywhere;
  color: #183f52;
  background: linear-gradient(135deg, #f3fbfb, #f7fafc);
  border: 1px solid #c9dde2;
  border-radius: 12px;
  box-shadow: 0 6px 18px rgba(0, 69, 103, 0.06);
}

.wpacu-lite-course .wpacu-gs-course-optin-status:empty {
  display: none;
}

.wpacu-lite-course .wpacu-gs-course-optin-message {
  display: grid;
  gap: 3px;
  padding: 13px 15px;
  color: #245f4c;
  line-height: 1.5;
  background: #eef9f4;
  border: 1px solid #cae6da;
  border-radius: 8px;
}

.wpacu-lite-course .wpacu-gs-course-optin-message.is-error {
  color: #7a3b20;
  background: #fff8f2;
  border-color: #efd5c5;
}

.wpacu-lite-course .wpacu-gs-course-optin-heading {
  display: grid;
  gap: 5px;
}

.wpacu-lite-course .wpacu-gs-course-optin-eyebrow {
  color: #007783;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.wpacu-lite-course .wpacu-gs-course-optin h2 {
  margin: 0;
  color: #024567;
  font-size: 20px;
  line-height: 1.35;
}

.wpacu-lite-course .wpacu-gs-course-optin-heading p {
  margin: 0;
  color: #526772;
  font-size: 14px;
  line-height: 1.6;
}

.wpacu-lite-course .wpacu-gs-course-optin-disclosure {
  margin-top: 13px;
}

.wpacu-lite-course .wpacu-gs-course-optin-disclosure summary {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 15px;
  color: #006584;
  font-weight: 650;
  cursor: pointer;
  background: #ffffff;
  border: 1px solid #a9cbd2;
  border-radius: 7px;
}

.wpacu-lite-course .wpacu-gs-course-optin-disclosure summary:hover {
  color: #024567;
  border-color: #008f9d;
}

.wpacu-lite-course .wpacu-gs-course-optin-disclosure summary:focus-visible,
.wpacu-lite-course .wpacu-gs-course-optin input:focus-visible,
.wpacu-lite-course .wpacu-gs-course-optin button:focus-visible,
.wpacu-lite-course .wpacu-gs-course-optin a:focus-visible {
  outline: 3px solid rgba(0, 143, 157, 0.32);
  outline-offset: 2px;
}

.wpacu-lite-course .wpacu-gs-course-optin-body {
  margin-top: 16px;
  padding-top: 17px;
  border-top: 1px solid #d7e6e9;
}

.wpacu-lite-course .wpacu-gs-course-optin-optional {
  margin: 0 0 15px;
  color: #526772;
  font-size: 13px;
}

.wpacu-lite-course .wpacu-gs-course-optin-heading .wpacu-gs-course-optin-optional {
  margin-top: 10px;
  font-size: 13px;
}

.wpacu-lite-course .wpacu-gs-course-optin-content {
  min-width: 0;
}

@media screen and (min-width: 1100px) {
  .wpacu-lite-course .wpacu-gs-course-optin-layout--split {
    display: grid;
    grid-template-columns: minmax(240px, 0.75fr) minmax(0, 1.75fr);
    gap: 30px;
    align-items: start;
  }

  .wpacu-lite-course .wpacu-gs-course-optin-layout--split .wpacu-gs-course-optin-content {
    padding-left: 28px;
    border-left: 1px solid #d7e6e9;
  }

  .wpacu-lite-course .wpacu-gs-course-optin-layout--split .wpacu-gs-course-optin-body {
    margin-top: 0;
    padding-top: 0;
    border-top: 0;
  }
}

.wpacu-lite-course .wpacu-gs-course-optin-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 15px;
}

.wpacu-lite-course .wpacu-gs-course-optin-field {
  display: grid;
  align-content: start;
  gap: 6px;
}

.wpacu-lite-course .wpacu-gs-course-optin-field label {
  color: #183f52;
  font-weight: 650;
}

.wpacu-lite-course .wpacu-gs-course-optin-field label span,
.wpacu-lite-course .wpacu-gs-course-optin-description {
  color: #657781;
  font-size: 12px;
  font-weight: 400;
}

.wpacu-lite-course .wpacu-gs-course-optin-field input[type="text"],
.wpacu-lite-course .wpacu-gs-course-optin-field input[type="email"] {
  width: 100%;
  min-height: 44px;
  margin: 0;
  color: #183f52;
  background: #ffffff;
  border: 1px solid #a9bbc3;
  border-radius: 6px;
}

.wpacu-lite-course .wpacu-gs-course-optin-mx-warning {
  color: #b32d2e;
  font-size: 12px;
  line-height: 1.5;
}

.wpacu-lite-course .wpacu-gs-course-optin-consent {
  display: grid;
  grid-template-columns: 20px minmax(0, 1fr);
  gap: 9px;
  align-items: start;
  min-height: 44px;
  margin-top: 17px;
  color: #425964;
  font-size: 13px;
  line-height: 1.55;
}

.wpacu-lite-course .wpacu-gs-course-optin-consent input {
  margin-top: 3px;
}

.wpacu-lite-course .wpacu-gs-course-optin-consent a {
  white-space: nowrap;
}

.wpacu-lite-course .wpacu-gs-course-optin-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 9px;
  margin-top: 15px;
}

#wpacu-lite-course-invitation .wpacu-gs-course-optin-actions .wpacu-gs-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 44px;
  padding: 10px 16px;
  border: 1px solid #008f9d;
  border-radius: 7px;
  background: #008f9d;
  color: #fff;
  font: inherit;
  font-weight: 650;
  cursor: pointer;
}

#wpacu-lite-course-invitation .wpacu-gs-course-optin-actions .wpacu-gs-button:hover {
  background: #006f7b;
  border-color: #006f7b;
}

#wpacu-lite-course-invitation .wpacu-gs-course-optin-actions button.is-loading {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: progress;
}

#wpacu-lite-course-invitation .wpacu-course-submit-icon { width: 18px; height: 18px; font-size: 18px; }
#wpacu-lite-course-invitation .is-loading .wpacu-course-submit-icon { display: none; }

#wpacu-lite-course-invitation .wpacu-gs-course-optin-actions button.is-loading::before {
  content: "";
  width: 14px;
  height: 14px;
  flex: 0 0 14px;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: wpacu-course-submit-spin 0.7s linear infinite;
}

@keyframes wpacu-course-submit-spin {
  to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
  #wpacu-lite-course-invitation .wpacu-gs-course-optin-actions button.is-loading::before {
    animation: none;
  }
}

#wpacu-lite-course-invitation .wpacu-gs-course-optin-actions .wpacu-gs-course-optin-continue {
  color: #006780;
  background: #fff;
  border-color: #a9bbc3;
}

#wpacu-lite-course-invitation .wpacu-gs-course-optin-actions .wpacu-gs-course-optin-continue:hover {
  color: #004967;
  background: #eaf6f8;
  border-color: #008f9d;
}

.wpacu-lite-course .wpacu-gs-course-optin-dismiss {
  margin-top: 8px;
}

.wpacu-lite-course .wpacu-gs-course-optin-hide {
  min-height: 44px;
  padding: 0 4px;
  color: #526772;
  text-decoration: underline;
  cursor: pointer;
  background: transparent;
  border: 0;
}

.wpacu-lite-course .wpacu-gs-course-optin-hide:hover {
  color: #024567;
}

.wpacu-lite-course .wpacu-gs-course-optin-honeypot {
  position: absolute;
  left: -10000px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}

.wpacu-lite-course-context {
  display: flex;
  box-sizing: border-box;
  width: fit-content;
  max-width: 100%;
  flex-wrap: wrap;
  gap: 12px 18px;
  align-items: center;
  margin: 20px 0;
  padding: 14px 18px;
  border: 1px solid #c9dde2;
  border-radius: 8px;
  background: #f3fbfb;
  color: #183f52;
}

.wpacu-lite-course-context[hidden] { display: none; }
.wpacu-lite-course-context > div { flex: 0 1 auto; max-width: 560px; min-width: 0; }
.wpacu-lite-course-context p { margin: 4px 0 0; }
.wpacu-lite-course-context .wpacu-lite-course-context__cta {
  display: inline-flex;
  box-sizing: border-box;
  flex: 0 0 auto;
  align-items: center;
  gap: 8px;
  min-height: 42px;
  padding: 9px 15px;
  border: 1px solid #b9d2db;
  border-radius: 6px;
  background: linear-gradient(180deg, #fff, #f0f8fa);
  color: #006584;
  font-weight: 650;
  line-height: 1.4;
  text-decoration: none;
  box-shadow: 0 1px 2px rgba(0, 69, 103, .08);
}
.wpacu-lite-course-context .wpacu-lite-course-context__cta:hover {
  border-color: #7eb4c2;
  background: #e9f5f8;
  color: #004967;
}
.wpacu-lite-course-context .wpacu-lite-course-context__cta:focus-visible {
  outline: 2px solid #006584;
  outline-offset: 2px;
}
.wpacu-lite-course-context__cta .dashicons { width: 18px; height: 18px; font-size: 18px; }
.wpacu-lite-course-context__dismiss {
  border: 0;
  padding: 8px;
  background: transparent;
  color: #526772;
  text-decoration: underline;
  cursor: pointer;
}

@media screen and (max-width: 620px) {
  .wpacu-lite-course-context { width: 100%; }
  .wpacu-lite-course-context > div { flex-basis: 100%; }
  .wpacu-lite-course .wpacu-gs-course-optin-consent {
    column-gap: 12px;
  }

  .wpacu-lite-course .wpacu-gs-course-optin-consent input[type="checkbox"] {
    box-sizing: border-box;
    width: 20px;
    min-width: 20px;
    height: 20px;
    margin: 1px 0 0;
  }

  .wpacu-lite-course .wpacu-gs-course-optin-consent input[type="checkbox"]:checked::before {
    width: 24px;
    height: 24px;
    margin: -3px;
  }

  .wpacu-lite-course .wpacu-gs-course-optin {
    padding: 19px;
  }

  .wpacu-lite-course .wpacu-gs-course-optin-fields,
  .wpacu-lite-course .wpacu-gs-course-optin-actions {
    display: grid;
    grid-template-columns: 1fr;
  }

  .wpacu-lite-course .wpacu-gs-course-optin-fields {
    gap: 13px;
  }

  .wpacu-lite-course .wpacu-gs-course-optin-disclosure summary,
  #wpacu-lite-course-invitation .wpacu-gs-course-optin-actions .wpacu-gs-button {
    box-sizing: border-box;
    justify-content: center;
    width: 100%;
  }
}
</style>
        <?php
    }

    /**
     * Render the shared form on an approved Lite screen.
     *
     * @param array $config
     *
     * @return void
     */
    public function render($config = array())
    {
        if ( ! $this->canUseCourse()) {
            return;
        }

        $user_id = get_current_user_id();
        $state   = get_user_meta($user_id, self::META_KEY, true);
        $notice  = null;

        if ($notice === null) {
            $notice = $this->pullNotice($user_id);
        }

        $status                   = is_array($state) && isset($state['status']) ? $state['status'] : '';
        $wpacu_course_quick_setup = false;
        if ($status === 'subscribed' && ( ! $wpacu_course_quick_setup
            || (isset($state['consent_version']) && $state['consent_version'] === self::UPDATES_CONSENT_VERSION) )) {
            return;
        }

        $wpacu_course_onboarding = ! empty($config['onboarding']);
        $wpacu_course_placement  = isset($config['placement']) && $config['placement'] === 'help'
            ? 'help' : 'getting_started';
        if ($wpacu_course_onboarding) {
            $wpacu_course_placement = 'first_activation';
        } elseif ($wpacu_course_placement === 'getting_started'
            && isset($_GET['wpacu_course_source']) && $_GET['wpacu_course_source'] === 'first_rule') {
            $wpacu_course_placement = 'first_rule';
        }

        $wpacu_course_status      = $status;
        $wpacu_course_notice      = $notice;
        $wpacu_course_firstname   = (string)get_user_meta($user_id, 'first_name', true);
        $wpacu_course_email       = (string)get_option('admin_email', '');
        $wpacu_course_privacy_url = 'https://www.gabelivan.com/privacy-policy/';

        $wpacu_course_submitted_email = is_array($state) && isset($state['email'])
            && is_string($state['email']) && is_email($state['email']) ? $state['email'] : '';
        if ($wpacu_course_submitted_email !== '') {
            $wpacu_course_email = $wpacu_course_submitted_email;
        }

        $wpacu_course_include_script = ! isset($config['include_script']) || $config['include_script'];

        echo '<div class="wpacu-lite-course' . ($wpacu_course_onboarding ? ' wpacu-lite-course--onboarding' : '')
            . '" id="wpacu-lite-course-invitation">';
        include WPACU_PLUGIN_DIR . '/templates/_admin-page-getting-started-areas/_course-opt-in.php';
        echo '</div>';
    }

    /**
     * @return void
     */
    public function renderHelp()
    {
        if ( ! $this->canUseCourse()) { return; }
        $state = get_user_meta(get_current_user_id(), self::META_KEY, true);
        if (is_array($state) && isset($state['status'])
            && in_array($state['status'], array('pending', 'dismissed', 'subscribed'), true)) {
            return;
        }
        $this->render(array('placement' => 'help'));
    }

    /**
     * Called only after the existing, authorized CSS/JS save workflow completes.
     * Empty saves, layout preferences and removals do not qualify.
     *
     * @return void
     */
    public function afterRulesSaved()
    {
        if ( ! $this->canUseCourse() || ! isset($_GET['page'])
            || $_GET['page'] !== WPACU_PLUGIN_ID . '_assets_manager'
            || (isset($_GET['wpacu_sub_page']) && $_GET['wpacu_sub_page'] !== 'manage_css_js')
            || get_user_meta(get_current_user_id(), self::INVITATION_SEEN_KEY, true)) {
            return;
        }

        $state = get_user_meta(get_current_user_id(), self::META_KEY, true);
        if (is_array($state) && ! empty($state['status'])) { return; }

        $lists = array();
        foreach (array('styles', 'scripts') as $type) {
            if (isset($_POST[WPACU_PLUGIN_ID][$type]) && is_array($_POST[WPACU_PLUGIN_ID][$type])) {
                $lists[] = $_POST[WPACU_PLUGIN_ID][$type];
            }
            foreach (array('wpacu_global_unload_', 'wpacu_bulk_unload_') as $prefix) {
                if (isset($_POST[$prefix . $type]) && is_array($_POST[$prefix . $type])) {
                    $lists[] = $_POST[$prefix . $type];
                }
            }
        }

        $hasHandle = false;
        array_walk_recursive($lists, static function ($handle) use (&$hasHandle) {
            if (is_string($handle) && $handle !== '') { $hasHandle = true; }
        });
        $this->showContextInvitation = $hasHandle;
    }

    /**
     * Display once, after the ordinary save notice, outside the asset rule form.
     *
     * @return void
     */
    public function renderContextInvitation()
    {
        if ( ! $this->showContextInvitation || ! $this->canUseCourse()) { return; }
        $this->showContextInvitation = false;

        $user_id = get_current_user_id();
        $state   = get_user_meta($user_id, self::META_KEY, true);
        if ((is_array($state) && ! empty($state['status']))
            || get_user_meta($user_id, self::INVITATION_SEEN_KEY, true)) { return; }

        // Remember the display itself so neither reloads nor later saves repeat it.
        update_user_meta($user_id, self::INVITATION_SEEN_KEY, 1);
        $courseUrl = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_getting_started&wpacu_course_source=first_rule')
            . '#wpacu-lite-course-invitation';
        include WPACU_PLUGIN_DIR . '/lite/templates/_course-after-first-rule.php';
    }

    /**
     * Provide an advisory MX check without validating or storing the mailbox.
     *
     * @return void
     */
    public function checkEmailDomain()
    {
        $this->authorizePost('wpacu_lite_course_optin_mx', 'wpacu_lite_course_optin_mx_nonce');

        $domain = isset($_POST['domain']) && is_string($_POST['domain'])
            ? strtolower(trim(wp_unslash($_POST['domain'])))
            : '';

        if (strlen($domain) > 253 || ! is_email('check@' . $domain)
            || filter_var($domain, FILTER_VALIDATE_IP)) {
            wp_send_json_error(array('status' => 'unknown'), 400);
            return;
        }

        $key    = '_' . WPACU_PLUGIN_ID . '_lite_course_mx_' . md5($domain);
        $status = get_transient($key);

        if ( ! in_array($status, array('valid', 'missing'), true)) {
            $status = 'unknown';

            if (function_exists('dns_get_record') && defined('DNS_MX')) {
                // A resolver failure is inconclusive, not proof of a bad domain.
                $records = @dns_get_record($domain . '.', DNS_MX);

                if (is_array($records)) {
                    $status = 'missing';
                    foreach ($records as $record) {
                        // A null MX (".") explicitly declares no mail service.
                        if (isset($record['type'], $record['target']) && $record['type'] === 'MX'
                            && trim($record['target'], ". \t\r\n") !== '') {
                            $status = 'valid';
                            break;
                        }
                    }
                    set_transient($key, $status, 5 * MINUTE_IN_SECONDS);
                }
            }
        }

        wp_send_json_success(array('status' => $status));
    }

    /**
     * Handle the explicit course subscription POST.
     *
     * @return void
     */
    public function handleSubscribe()
    {
        $this->authorizePost('wpacu_lite_course_optin', 'wpacu_lite_course_optin_nonce');
        $this->redirectWithNotice($this->subscribe() ? 'success' : 'error');
    }

    /**
     * Handle the same explicit opt-in without navigating away from the form.
     *
     * @return void
     */
    public function handleSubscribeAjax()
    {
        if ( ! $this->authorizeAjax('wpacu_lite_course_optin', 'wpacu_lite_course_optin_nonce') ) {
            return;
        }

        if ( ! $this->subscribe() ) {
            wp_send_json_error(array('message' => __('The request could not be completed. Please review the fields or try again later.', 'wp-asset-clean-up')), 422);
            return;
        }

        $this->sendAjaxPanel();
    }

    /**
     * Validate local course AJAX actions before reading or changing user state.
     *
     * @param string $nonce_action
     * @param string $nonce_name
     *
     * @return bool
     */
    private function authorizeAjax($nonce_action, $nonce_name)
    {
        if ( ! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(array('message' => __('Invalid request method.', 'wp-asset-clean-up')), 405);
            return false;
        }

        if ( ! $this->canUseCourse()) {
            wp_send_json_error(array('message' => __('You are not allowed to perform this action.', 'wp-asset-clean-up')), 403);
            return false;
        }

        $nonce = isset($_POST[$nonce_name]) && is_string($_POST[$nonce_name])
            ? wp_unslash($_POST[$nonce_name]) : '';
        if ( ! wp_verify_nonce($nonce, $nonce_action) ) {
            wp_send_json_error(array('message' => __('Your session has expired. Reload this page and try again.', 'wp-asset-clean-up')), 403);
            return false;
        }

        return true;
    }

    /**
     * Return the updated local panel without executing a second initializer script.
     *
     * @return void
     */
    private function sendAjaxPanel()
    {
        delete_transient($this->noticeKey(get_current_user_id()));
        ob_start();
        $this->render(array('include_script' => false, 'quick_setup' => $this->isQuickSetupPost()));
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }

    /**
     * Shared subscription logic, called only after transport-specific authorization.
     *
     * @return bool
     */
    private function subscribe()
    {

        $company = isset($_POST['company']) && is_string($_POST['company'])
            ? trim(wp_unslash($_POST['company']))
            : '';

        if ($company !== '') {
            $this->storeState('pending');
            return true;
        }

        $email = isset($_POST['email']) && is_string($_POST['email'])
            ? sanitize_email(wp_unslash($_POST['email']))
            : '';
        $firstname = isset($_POST['firstname']) && is_string($_POST['firstname'])
            ? sanitize_text_field(wp_unslash($_POST['firstname']))
            : '';
        $consent = isset($_POST['consent']) && is_string($_POST['consent'])
            ? wp_unslash($_POST['consent'])
            : '';

        if ( ! is_email($email) || $consent !== '1' ) {
            return false;
        }

        $consentVersion = self::CONSENT_VERSION;
        $endpoint = apply_filters('wpacu_lite_course_optin_endpoint', self::ENDPOINT);

        if ( ! is_string($endpoint) || strtolower((string)wp_parse_url($endpoint, PHP_URL_SCHEME)) !== 'https' ) {
            return false;
        }

        $payload = array(
            'email'           => $email,
            'firstname'       => $firstname,
            'accepted'        => true,
            'source'          => 'asset-cleanup-lite',
            'placement'       => isset($_POST['wpacu_course_placement'])
                && in_array($_POST['wpacu_course_placement'], array('getting_started', 'help', 'first_rule', 'first_activation'), true)
                    ? $_POST['wpacu_course_placement'] : 'getting_started',
            'consent_version' => $consentVersion,
            'plugin_version'  => WPACU_LITE_PLUGIN_VERSION
        );

        $response = wp_safe_remote_post($endpoint, array(
            'timeout'            => 8,
            'redirection'        => 0,
            'sslverify'          => true,
            'reject_unsafe_urls' => true,
            'user-agent'         => 'Asset-CleanUp-Lite/' . WPACU_LITE_PLUGIN_VERSION,
            'headers'            => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json'
            ),
            'body'               => wp_json_encode($payload),
            'data_format'        => 'body'
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = (int)wp_remote_retrieve_response_code($response);
        $decoded     = json_decode(wp_remote_retrieve_body($response), true);

        if ( ! in_array($status_code, array(200, 202), true)
            || ! is_array($decoded)
            || ! isset($decoded['accepted'])
            || true !== $decoded['accepted']) {
            return false;
        }

        $this->storeState('pending', $email, $consentVersion);
        if (get_user_meta(get_current_user_id(), self::ONBOARDING_KEY, true) === 'offered') {
            update_user_meta(get_current_user_id(), self::ONBOARDING_KEY, 'completed');
        }
        return true;
    }

    /**
     * Continue locally without submitting the course fields or changing consent.
     *
     * @return void
     */
    public function handleContinue()
    {
        $this->authorizePost('wpacu_lite_course_continue', 'wpacu_lite_course_continue_nonce');

        $user_id = get_current_user_id();
        if (get_user_meta($user_id, self::ONBOARDING_KEY, true) === 'offered') {
            update_user_meta($user_id, self::ONBOARDING_KEY, 'continued');
        }

        $this->redirect('wpacu-gs-concept');
    }

    /**
     * Dismiss this invitation for the current user.
     *
     * @return void
     */
    public function handleDismiss()
    {
        $this->authorizePost('wpacu_lite_course_optin_dismiss', 'wpacu_lite_course_optin_dismiss_nonce');
        $this->storeState('dismissed');
        $this->redirect();
    }

    /**
     * Hide the reminder locally without navigating or contacting the course service.
     *
     * @return void
     */
    public function handleDismissAjax()
    {
        if ( ! $this->authorizeAjax('wpacu_lite_course_optin_dismiss', 'wpacu_lite_course_optin_dismiss_nonce') ) {
            return;
        }

        $this->storeState('dismissed');
        $this->sendAjaxPanel();
    }

    /**
     * Restore a dismissed invitation without subscribing or sending email.
     *
     * @return void
     */
    public function handleRestore()
    {
        $this->authorizePost('wpacu_lite_course_optin_restore', 'wpacu_lite_course_optin_restore_nonce');
        $this->restoreInvitation();
        $this->redirect();
    }

    /**
     * Restore the local reminder without re-subscribing or sending a confirmation.
     *
     * @return void
     */
    public function handleRestoreAjax()
    {
        if ( ! $this->authorizeAjax('wpacu_lite_course_optin_restore', 'wpacu_lite_course_optin_restore_nonce') ) {
            return;
        }

        $this->restoreInvitation();
        $this->sendAjaxPanel();
    }

    /**
     * Preserve the original pending state for either AJAX or ordinary form requests.
     *
     * @return void
     */
    private function restoreInvitation()
    {
        $user_id = get_current_user_id();
        $state   = get_user_meta($user_id, self::META_KEY, true);

        if (is_array($state) && isset($state['status']) && $state['status'] === 'dismissed') {
            if (isset($state['previous_status']) && $state['previous_status'] === 'pending') {
                $this->storeState('pending');
            } else {
                delete_user_meta($user_id, self::META_KEY);
            }
            delete_transient($this->noticeKey($user_id));
        }
    }

    /**
     * @param string $nonce_action
     * @param string $nonce_name
     *
     * @return void
     */
    private function authorizePost($nonce_action, $nonce_name)
    {
        if ( ! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            wp_die(esc_html__('Invalid request method.', 'wp-asset-clean-up'), '', array('response' => 405));
        }

        if ( ! $this->canUseCourse()) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'wp-asset-clean-up'), '', array('response' => 403));
        }

        check_admin_referer($nonce_action, $nonce_name);
    }

    /**
     * Keep the accepted address for the reminder and correction form, without storing the name.
     *
     * @param string $status
     * @param string $email
     * @param string $consentVersion Version accepted on a new submission. Reminder actions retain the previous version.
     *
     * @return void
     */
    private function storeState($status, $email = '', $consentVersion = '')
    {
        if ( ! in_array($status, array('pending', 'dismissed'), true) ) {
            return;
        }

        $user_id = get_current_user_id();
        $state   = get_user_meta($user_id, self::META_KEY, true);
        if ($consentVersion === '') {
            $consentVersion = isset($state['consent_version']) && $state['consent_version'] === self::UPDATES_CONSENT_VERSION
                ? self::UPDATES_CONSENT_VERSION : self::CONSENT_VERSION;
        }

        $value   = array(
            'status'          => $status,
            'timestamp'       => time(),
            'consent_version' => $consentVersion
        );

        if ($email !== '' && is_email($email)) {
            $value['email'] = $email;
        } elseif (is_array($state) && isset($state['email']) && is_string($state['email']) && is_email($state['email'])) {
            $value['email'] = $state['email'];
        }

        if ($status === 'dismissed' && is_array($state)
            && ((isset($state['status']) && $state['status'] === 'pending')
                || (isset($state['previous_status']) && $state['previous_status'] === 'pending'))) {
            $value['previous_status'] = 'pending';
        }

        update_user_meta($user_id, self::META_KEY, $value);
    }

    /**
     * @param int $user_id
     *
     * @return string
     */
    private function pullNotice($user_id)
    {
        $key    = $this->noticeKey($user_id);
        $notice = get_transient($key);
        delete_transient($key);

        return in_array($notice, array('success', 'error'), true) ? $notice : '';
    }

    /**
     * @param int $user_id
     *
     * @return string
     */
    private function noticeKey($user_id)
    {
        return '_' . WPACU_PLUGIN_ID . '_lite_course_optin_notice_' . absint($user_id);
    }

    /**
     * @param string $notice
     *
     * @return void
     */
    private function redirectWithNotice($notice)
    {
        if (in_array($notice, array('success', 'error'), true)) {
            set_transient($this->noticeKey(get_current_user_id()), $notice, 2 * MINUTE_IN_SECONDS);
        }

        $this->redirect();
    }

    /**
     * Return only to the local Getting Started page.
     *
     * @param string $fragment
     *
     * @return void
     */
    private function redirect($fragment = '')
    {
        $url = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_getting_started');
        if ($fragment !== '') {
            $url .= '#' . $fragment;
        }
        wp_safe_redirect($url, 303);
        exit();
    }

    /**
     * @return bool
     */
    private function isQuickSetupPost()
    {
        return isset($_POST['wpacu_course_return_to']) && $_POST['wpacu_course_return_to'] === 'quick_setup';
    }
}
