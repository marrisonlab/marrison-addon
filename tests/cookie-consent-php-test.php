<?php
/** Valid HTML attribute forms must be inert before consent. */
function add_action() {}
function add_shortcode() {}
function get_current_user_id() { return 0; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_key( $value ) { return strtolower( $value ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
require __DIR__.'/../marrison-addon/includes/modules/cookie-manager/includes/class-cookie-consent.php';
$consent=Marrison_Cookie_Consent::get_instance();
$_COOKIE=[];
foreach ([ '"/youtube.com?a=1&amp;b=2"', "'/youtube.com?a=1&amp;b=2'", '/youtube.com?a=1&amp;b=2' ] as $src) {
    $input='<iframe title="src=kept" SRC = '.$src.' allowfullscreen></iframe>';
    $output=$consent->filter_output($input);
    if (strpos($output,' SRC ')!==false || strpos($output,'data-marrison-blocked-src="/youtube.com?a=1&amp;b=2"')===false || strpos($output,'title="src=kept"')===false) {
        throw new RuntimeException('Failed iframe attribute form: '.$src);
    }
}
foreach ([ '"module"', "'module'", 'module' ] as $type) {
    $input='<script TYPE = '.$type.' SRC = /google-analytics.com.js nonce="preserved">export const value=1;</script>';
    $output=$consent->filter_output($input);
    if (substr_count($output,'type="text/plain"')!==1 || strpos($output,'data-marrison-script-type="module"')===false || strpos($output,'data-marrison-blocked-src="/google-analytics.com.js"')===false || strpos($output,'nonce="preserved"')===false || strpos($output,' TYPE ')!==false) {
        throw new RuntimeException('Failed script attribute form: '.$type);
    }
}
$_COOKIE=['marrison_cookie_consent'=>'accept_all'];
if ($consent->filter_output($input)!==$input) { throw new RuntimeException('Allowed script changed'); }
$_COOKIE=['marrison_cookie_consent'=>'custom','marrison_cookie_categories'=>'necessary|analytics'];
if ($consent->filter_output($input)!==$input || strpos($consent->filter_output('<iframe src=/youtube.com></iframe>'),'data-marrison-blocked-src')===false) { throw new RuntimeException('Selective consent failed'); }
echo "PASS cookie quoted/unquoted src and module type, entities, nonce and selective consent\n";
