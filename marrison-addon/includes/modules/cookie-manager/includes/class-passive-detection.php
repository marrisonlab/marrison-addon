<?php
/** Optional host-only reporting. No URLs, cookie values or visitor identifiers. */
if (!defined('ABSPATH')) { exit; }
final class Marrison_Addon_Cookie_Passive_Detection {
    public function __construct() {
        if (!get_option('marrison_cookie_passive_detection', false)) { return; }
        add_action('wp_ajax_marrison_cookie_blocked_hosts', array($this, 'receive'));
        add_action('wp_ajax_nopriv_marrison_cookie_blocked_hosts', array($this, 'receive'));
    }
    public function receive() {
        Marrison_Addon_Cookie_Consent_Store::get_instance()->authorize_public();
        if (!get_option('marrison_cookie_passive_detection', false)) { wp_send_json_error(array('message' => 'Rilevamento disattivato.'), 403); }
        $hosts = isset($_POST['hosts']) && is_array($_POST['hosts']) ? wp_unslash($_POST['hosts']) : array();
        if (count($hosts) > 20 || !Marrison_Addon_Cookie_Request_Limits::allow('passive:' . gmdate('Y-m-d'), 500, DAY_IN_SECONDS)) { wp_send_json_error(array('message' => 'Limite raggiunto.'), 429); }
        $stored = get_option('marrison_cookie_passive_hosts', array());
        if (!is_array($stored)) { $stored = array(); }
        foreach ($hosts as $host) {
            if (!is_string($host)) { continue; }
            $host = strtolower(trim($host));
            if (strlen($host) > 253 || !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || strpos($host, '.') === false) { continue; }
            $stored[$host] = true;
            if (count($stored) >= 100) { break; }
        }
        update_option('marrison_cookie_passive_hosts', array_slice($stored, 0, 100, true), false);
        wp_send_json_success(array('message' => 'Host registrati.'));
    }
}
