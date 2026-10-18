<?php
/** Shortcode disponibili nel wizard e nelle impostazioni. */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="marrison-policy-shortcodes">
<p><?php echo esc_html(marrison_cookie_site_text('Inserisci questi shortcode nelle tue pagine, anche nel widget Shortcode di Elementor. Il layout resta quello della pagina.', 'Insert these shortcodes into your pages, including the Elementor Shortcode widget. The page keeps its own layout.')); ?></p>
<?php
$policy_shortcodes = array(
    array('Privacy Policy', '[marrison_privacy_policy]', marrison_cookie_site_text('Testo completo della Privacy Policy.', 'Full Privacy Policy text.')),
    array('Cookie Policy', '[marrison_cookie_policy_content]', marrison_cookie_site_text('Testo completo della Cookie Policy, lista dei cookie rilevati e link alle preferenze.', 'Full Cookie Policy text, detected cookie list and preferences link.')),
    array(marrison_cookie_site_text('Lista cookie e preferenze', 'Cookie list and preferences'), '[marrison_cookie_policy]', marrison_cookie_site_text('Solo la lista dei cookie rilevati e il link alle preferenze, da inserire in una policy già scritta.', 'Only the detected cookie list and preferences link, to insert into an existing policy.')),
);
foreach ($policy_shortcodes as $policy_shortcode): ?>
    <div class="marrison-shortcode-item">
        <p><strong><?php echo esc_html($policy_shortcode[0]); ?></strong><br><?php echo esc_html($policy_shortcode[2]); ?></p>
        <div class="marrison-shortcode-copy-row">
            <input type="text" class="marrison-shortcode-value" value="<?php echo esc_attr($policy_shortcode[1]); ?>" readonly aria-label="<?php echo esc_attr($policy_shortcode[0] . ' shortcode'); ?>" spellcheck="false">
            <button type="button" class="button marrison-copy-shortcode"
                data-copied-text="<?php echo esc_attr(marrison_cookie_site_text('Copiato!', 'Copied!')); ?>"
                data-copy-error-text="<?php echo esc_attr(marrison_cookie_site_text('Copia non riuscita. Il codice è selezionato: usa Ctrl+C o Cmd+C.', 'Copy failed. The code is selected: use Ctrl+C or Cmd+C.')); ?>"><?php echo esc_html(marrison_cookie_site_text('Copia', 'Copy')); ?></button>
        </div>
        <span class="marrison-shortcode-copy-status" role="status" aria-live="polite"></span>
    </div>
<?php endforeach; ?>
</div>
