from pathlib import Path
import re
base = Path('marrison-addon/includes/modules/cookie-manager')

def edit(path, change):
    p = base / path
    old = p.read_text(encoding='utf8')
    new = change(old)
    assert new != old, path
    p.write_text(new, encoding='utf8', newline='\n')

def settings(s):
    s = s.replace("_e('Testi policy',", "_e('Privacy e policy',")
    s, n = re.subn(r'\s*<tr>\s*<th scope="row"><\?php echo esc_html\(marrison_cookie_site_text\(\'Shortcode policy\'.*?</tr>', '', s, count=1, flags=re.S)
    assert n == 1
    start = s.index("        <?php elseif ($active_tab === 'policies'): ?>")
    end = s.index("        <?php elseif ($active_tab === 'receipts'): ?>", start)
    s = s[:start] + '''        <?php elseif ($active_tab === 'policies'): ?>
            <div class="marrison-card">
                <div class="marrison-card-header"><h2>Generatore Privacy Policy</h2><p>I dati compilati alimentano lo shortcode della Privacy Policy. La Cookie Policy è generata automaticamente e include l’inventario della scansione.</p></div>
                <div class="marrison-shortcodes-panel">
                    <?php $privacy_prefix = 'privacy-profile-'; include MARRISON_COOKIE_PLUGIN_DIR . 'templates/privacy-fields.php'; ?>
                    <p><label for="policy_revision"><strong>Revisione informativa</strong></label></p>
                    <input type="text" id="policy_revision" name="policy_revision" maxlength="80" value="<?php echo esc_attr($settings['policy_revision']); ?>">
                    <p class="description">Aggiorna la revisione quando cambiano sostanzialmente trattamenti o destinatari: verrà richiesta una nuova scelta. Le semplici correzioni di impaginazione non la richiedono.</p>
                    <details><summary>Anteprima Privacy Policy salvata</summary><?php echo Marrison_Addon_Cookie_Policy_Content::content('privacy'); ?></details>
                    <details><summary>Anteprima testo Cookie Policy</summary><?php echo Marrison_Addon_Cookie_Policy_Content::content('cookie'); ?></details>
                </div>
            </div>
''' + s[end:]
    s = s.replace('<pre id="marrison-receipts-result" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre>', '<div id="marrison-receipts-result" class="marrison-receipts-view"></div>')
    s = s.replace("_e('Esporta giornata',", "_e('Esporta dati tecnici (JSON)',")
    long = "La scansione remota lavora sul server in background: dopo l’avvio puoi tornare alla bacheca o chiudere la pagina. Avanzamento e risultati restano salvati; riapri lo scanner per consultarli. Visita pagine campione prima e dopo il consenso e verifica anche i domini esterni. Non invia credenziali WordPress o valori dei cookie. Il metodo locale richiede invece questa pagina aperta e ha copertura limitata."
    s = s.replace(long, 'Scansione remota in background: puoi lasciare questa pagina. Il metodo locale richiede la pagina aperta.')
    s = s.replace('<p id="marrison-engine-coverage"></p>', '')
    s = s.replace('<p id="marrison-engine-summary">', '<p id="marrison-engine-summary" role="status" aria-live="polite">')
    return s
edit('templates/admin-settings.php', settings)

def wizard(s):
    s = s.replace("5 => __('Shortcode', 'marrison-cookie')", "5 => __('Privacy', 'marrison-cookie'),\n                                6 => __('Shortcode', 'marrison-cookie')")
    marker = '<div class="marrison-wizard-step <?php echo $current_step === 5 ? \'active\' : \'\'; ?>" data-step="5">'
    assert marker in s
    s = s.replace(marker, '''<div class="marrison-wizard-step <?php echo $current_step === 5 ? 'active' : ''; ?>" data-step="5">
                <h2 class="marrison-step-title">Genera la Privacy Policy</h2>
                <p class="marrison-step-description">Passaggio facoltativo. Puoi compilare i dati ora oppure scegliere Salta e completarli in Privacy e policy.</p>
                <?php $privacy_prefix = 'wizard-privacy-'; include MARRISON_COOKIE_PLUGIN_DIR . 'templates/privacy-fields.php'; ?>
            </div>
            <div class="marrison-wizard-step <?php echo $current_step === 6 ? 'active' : ''; ?>" data-step="6">''')
    marker = '<button type="button" id="wizard_next"'
    s = s.replace(marker, '<button type="button" id="wizard_skip_privacy" class="marrison-btn marrison-btn-secondary" style="<?php echo $current_step === 5 ? \'\' : \'display:none;\'; ?>">Salta</button>\n                ' + marker)
    return s
edit('templates/setup-wizard.php', wizard)

def wizard_js(s):
    marker = "            $(document).on('click', '#wizard_next', function(e) {"
    s = s.replace(marker, """            $(document).on('click', '#wizard_skip_privacy', function(e) {
                e.preventDefault();
                if (MarrisonWizard.currentStep !== 5) { return; }
                MarrisonWizard.currentStep = 6;
                MarrisonWizard.showStep(6); MarrisonWizard.updateProgress(); MarrisonWizard.saveCurrentStep();
            });
""" + marker)
    s = s.replace("            $('#wizard_next').text(step === this.totalSteps", "            $('#wizard_skip_privacy').toggle(step === 5);\n            $('#wizard_next').text(step === this.totalSteps")
    marker = "            // Invia dati al server"
    s = s.replace(marker, """            if (step === 5) {
                data.privacy_profile = {};
                $('.marrison-wizard-step[data-step="5"] [data-privacy-field]').each(function() {
                    var field = $(this);
                    data.privacy_profile[field.attr('data-privacy-field')] = field.is(':checkbox') ? (field.prop('checked') ? '1' : '0') : field.val();
                });
            }
""" + marker)
    return s
edit('assets/js/wizard.js', wizard_js)

def banner(s):
    s, n = re.subn(r'    <button type="button" id="marrison-close-banner".*?</button>\n', '', s, count=1); assert n == 1
    s, n = re.subn(r'            <p class="marrison-banner-close-info">.*?</p>\n', '', s, count=1); assert n == 1
    return s
edit('templates/cookie-banner.php', banner)
edit('assets/js/frontend.js', lambda s: s.replace("#marrison-reject-all, #marrison-close-banner", "#marrison-reject-all").replace(', #marrison-close-banner', ''))
edit('assets/css/frontend.css', lambda s: re.sub(r'\n#marrison-cookie-banner \.marrison-close-banner[^\n]*\n#marrison-cookie-banner \.marrison-banner-container[^\n]*\n?', '\n', re.sub(r'\n\.marrison-banner-close-info \{[^}]*\}\n', '\n', s)))
edit('templates/cookie-policy.php', lambda s: re.sub(r'<h[1-6]>(.*?)</h[1-6]>', r'<p><strong>\1</strong></p>', s))
