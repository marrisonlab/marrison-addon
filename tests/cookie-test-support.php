<?php
/** Minimal WP service fixtures shared by focused Cookie Manager tests. */
if (!defined('DAY_IN_SECONDS')) { define('DAY_IN_SECONDS', 86400); }
if (!function_exists('wp_json_encode')) { function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); } }
if (!function_exists('wp_kses_post')) { function wp_kses_post($value) { return preg_replace('#<script\b[^>]*>.*?</script>#is', '', $value); } }
if (!function_exists('wpautop')) { function wpautop($value) { return $value; } }
if (!function_exists('sanitize_email')) { function sanitize_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : ''; } }
if (!function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($value) { return trim(strip_tags($value)); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($value) { return trim(strip_tags($value)); } }
if (!function_exists('esc_html')) { function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('marrison_cookie_site_text')) { function marrison_cookie_site_text($it, $en) { return $it; } }
require_once __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-policy-content.php';
require_once __DIR__ . '/../marrison-addon/includes/modules/cookie-manager/includes/class-consent-store.php';
