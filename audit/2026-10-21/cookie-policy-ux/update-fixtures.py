from pathlib import Path
p=Path('tests/cookie-shortcodes-php-test.php');s=p.read_text(encoding='utf8')
marker="$english = false;"
s=s.replace(marker,"""$options['marrison_cookie_privacy_profile'] = array(
    'controller_name' => 'Test <Site>', 'controller_address' => 'Via Esempio 1, Italia', 'controller_email' => 'privacy@example.test',
    'contact_enabled' => true, 'logs_enabled' => true, 'logs_retention' => '30 giorni', 'recipients' => 'Hosting ed email', 'transfers' => 'none');
"""+marker)
s=s.replace("verify(strpos($privacy, '<h1>Privacy Policy</h1>') !== false && strpos($privacy, 'Test &lt;Site&gt;') !== false, 'Privacy content or escaping missing');", "verify(strpos($privacy, '<strong>Privacy Policy</strong>') !== false && strpos($privacy, 'Test &lt;Site&gt;') !== false, 'Generated privacy or escaping missing');")
s=s.replace("verify(strpos($privacy, 'informativa approvata') !== false, 'Configured privacy content missing');", "verify(!preg_match('/<h[1-6]\\b/i', $privacy . $cookie), 'Shortcodes must not produce headings');")
s=s.replace("strpos($cookie, 'Testo cookie approvato') !== false", "strpos($cookie, 'localStorage') !== false" )
s=s.replace("strpos(do_shortcode('[marrison_privacy_policy]'), 'informativa approvata') !== false", "strpos(do_shortcode('[marrison_privacy_policy]'), 'Test &lt;Site&gt;') !== false")
s=s.replace("strpos(do_shortcode('[marrison_cookie_policy_content]'), 'Testo cookie approvato') !== false", "strpos(do_shortcode('[marrison_cookie_policy_content]'), 'localStorage') !== false")
# Extra behavioral coverage for the structured generator and optional wizard step.
s=s.replace('$can_manage = false;', """$saved_profile = $options['marrison_cookie_privacy_profile'];
$response = ajax('wp_ajax_marrison_wizard_save_step', array('nonce' => 'fixture', 'step' => 6, 'data' => array('current_step' => 6)));
verify($response->success && $options['marrison_cookie_privacy_profile'] === $saved_profile, 'Skipping privacy changed the saved profile');
$response = ajax('wp_ajax_marrison_wizard_save_step', array('nonce' => 'fixture', 'step' => 5, 'data' => array('privacy_profile' => $saved_profile + array('other_processing' => '<script>bad</script>Account: art. 6.1.b, 12 mesi'))));
verify($response->success && strpos($options['marrison_cookie_privacy_profile']['other_processing'], '<script>') === false, 'Wizard profile was not sanitized/persisted');
verify(Marrison_Setup_Wizard::get_instance()->get_total_steps() === 6, 'Optional privacy wizard step missing');
$can_manage = false;""")
# Snapshot includes new changes, retain the authorization assertion baseline.
s=s.replace("$can_manage = false;\n$response = ajax", "$before = $options;\n$can_manage = false;\n$response = ajax")
p.write_text(s,encoding='utf8',newline='\n')
p=Path('tests/cookie-consent-fixture.php');s=p.read_text(encoding='utf8')
s=s.replace("require MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-policy-content.php';", "require __DIR__ . '/cookie-test-support.php';")
s=s.replace("require MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-consent-store.php';", "require_once MARRISON_COOKIE_PLUGIN_DIR . 'includes/class-consent-store.php';")
p.write_text(s,encoding='utf8',newline='\n')
