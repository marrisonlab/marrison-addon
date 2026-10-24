<?php
/**
 * Template per la pagina delle impostazioni admin
 */
$settings = Marrison_Cookie_Admin_Settings::get_instance()->get_settings();
$scanner = Marrison_Cookie_Scanner::get_instance();
$categories = $scanner->get_categories();
?>

<div class="wrap marrison-cookie-settings">
    <div class="marrison-admin-header">
        <div class="marrison-admin-title">
            <h1><?php _e('Marrison Cookie Manager', 'marrison-cookie'); ?></h1>
            <p><?php _e('Gestisci i cookie e il banner di consenso del tuo sito WordPress.', 'marrison-cookie'); ?></p>
        </div>
        <button type="button" id="marrison-open-wizard" class="marrison-wizard-launch-btn">
            <?php _e('Avvia Wizard', 'marrison-cookie'); ?>
        </button>
    </div>
    
    <h2 class="nav-tab-wrapper marrison-nav-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-cookie&tab=banner')); ?>" class="nav-tab <?php echo $active_tab === 'banner' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Banner', 'marrison-cookie'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-cookie&tab=appearance')); ?>" class="nav-tab <?php echo $active_tab === 'appearance' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Aspetto', 'marrison-cookie'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-cookie&tab=behavior')); ?>" class="nav-tab <?php echo $active_tab === 'behavior' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Comportamento', 'marrison-cookie'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-cookie&tab=scanner')); ?>" class="nav-tab <?php echo $active_tab === 'scanner' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Scanner Cookie', 'marrison-cookie'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-cookie&tab=shortcodes')); ?>" class="nav-tab <?php echo $active_tab === 'shortcodes' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Shortcode', 'marrison-cookie'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-cookie&tab=policies')); ?>" class="nav-tab <?php echo $active_tab === 'policies' ? 'nav-tab-active' : ''; ?>"><?php _e('Privacy e policy', 'marrison-cookie'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=marrison-cookie&tab=receipts')); ?>" class="nav-tab <?php echo $active_tab === 'receipts' ? 'nav-tab-active' : ''; ?>"><?php _e('Ricevute consenso', 'marrison-cookie'); ?></a>
    </h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('marrison_cookie_settings_nonce'); ?>
        
        <?php if ($active_tab === 'banner'): ?>
            <div class="marrison-card">
                <div class="marrison-card-header">
                    <h2><?php _e('Impostazioni Banner', 'marrison-cookie'); ?></h2>
                    <p><?php _e('Personalizza testi e link del banner cookie.', 'marrison-cookie'); ?></p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="banner_title"><?php _e('Titolo Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="banner_title" 
                                   name="banner_title" 
                                   value="<?php echo esc_attr($settings['banner_title']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="banner_description"><?php _e('Descrizione Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <textarea id="banner_description" 
                                      name="banner_description" 
                                      rows="4" 
                                      class="large-text"><?php echo esc_textarea($settings['banner_description']); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="accept_button_text"><?php _e('Testo Bottone Accetta', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="accept_button_text" 
                                   name="accept_button_text" 
                                   value="<?php echo esc_attr($settings['accept_button_text']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="reject_button_text"><?php _e('Testo Bottone Rifiuta', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="reject_button_text" 
                                   name="reject_button_text" 
                                   value="<?php echo esc_attr($settings['reject_button_text']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="customize_button_text"><?php _e('Testo Bottone Personalizza', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="customize_button_text" 
                                   name="customize_button_text" 
                                   value="<?php echo esc_attr($settings['customize_button_text']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="privacy_policy_url"><?php _e('URL Privacy Policy', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="privacy_policy_url" 
                                   name="privacy_policy_url" 
                                   value="<?php echo esc_url($settings['privacy_policy_url']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cookie_policy_url"><?php _e('URL Cookie Policy', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="cookie_policy_url" 
                                   name="cookie_policy_url" 
                                   value="<?php echo esc_url($settings['cookie_policy_url']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>
            
        <?php elseif ($active_tab === 'appearance'): ?>
            <div class="marrison-card">
                <div class="marrison-card-header">
                    <h2><?php _e('Impostazioni Aspetto', 'marrison-cookie'); ?></h2>
                    <p><?php _e('Scegli colori e posizione del banner.', 'marrison-cookie'); ?></p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="banner_layout"><?php _e('Formato Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <select id="banner_layout" name="banner_layout">
                                <option value="bar" <?php selected($settings['banner_layout'], 'bar'); ?>>
                                    <?php _e('Barra', 'marrison-cookie'); ?>
                                </option>
                                <option value="box" <?php selected($settings['banner_layout'], 'box'); ?>>
                                    <?php _e('Box', 'marrison-cookie'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="banner_position"><?php _e('Posizione Barra', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <select id="banner_position" name="banner_position">
                                <option value="top" <?php selected($settings['banner_position'], 'top'); ?>>
                                    <?php _e('In alto', 'marrison-cookie'); ?>
                                </option>
                                <option value="bottom" <?php selected($settings['banner_position'], 'bottom'); ?>>
                                    <?php _e('In basso', 'marrison-cookie'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="box_position"><?php _e('Posizione Box', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <select id="box_position" name="box_position">
                                <option value="top-left" <?php selected($settings['box_position'], 'top-left'); ?>>
                                    <?php _e('In alto a sinistra', 'marrison-cookie'); ?>
                                </option>
                                <option value="top-right" <?php selected($settings['box_position'], 'top-right'); ?>>
                                    <?php _e('In alto a destra', 'marrison-cookie'); ?>
                                </option>
                                <option value="bottom-left" <?php selected($settings['box_position'], 'bottom-left'); ?>>
                                    <?php _e('In basso a sinistra', 'marrison-cookie'); ?>
                                </option>
                                <option value="bottom-right" <?php selected($settings['box_position'], 'bottom-right'); ?>>
                                    <?php _e('In basso a destra', 'marrison-cookie'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="banner_background_color"><?php _e('Colore Sfondo Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="banner_background_color" 
                                   name="banner_background_color" 
                                   value="<?php echo esc_attr($settings['banner_background_color']); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="banner_text_color"><?php _e('Colore Testo Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="banner_text_color" 
                                   name="banner_text_color" 
                                   value="<?php echo esc_attr($settings['banner_text_color']); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="button_background_color"><?php _e('Colore Sfondo Bottoni', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="button_background_color" 
                                   name="button_background_color" 
                                   value="<?php echo esc_attr($settings['button_background_color']); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="button_text_color"><?php _e('Colore Testo Bottoni', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="button_text_color" 
                                   name="button_text_color" 
                                   value="<?php echo esc_attr($settings['button_text_color']); ?>">
                        </td>
                    </tr>
                </table>
            </div>
            
        <?php elseif ($active_tab === 'behavior'): ?>
            <div class="marrison-card">
                <div class="marrison-card-header">
                    <h2><?php _e('Impostazioni Comportamento', 'marrison-cookie'); ?></h2>
                    <p><?php _e('Configura durata consenso e scansione automatica.', 'marrison-cookie'); ?></p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="show_banner"><?php _e('Mostra Banner', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <label class="marrison-admin-switch">
                                <input type="checkbox"
                                       id="show_banner"
                                       name="show_banner"
                                       value="1"
                                       <?php checked($settings['show_banner'], true); ?>>
                                <span class="marrison-admin-switch-slider"></span>
                                <span class="marrison-admin-switch-label"><?php _e('Attiva banner cookie', 'marrison-cookie'); ?></span>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('Accesso preferenze dopo consenso', 'marrison-cookie'); ?>
                        </th>
                        <td>
                            <?php
                            $closed_trigger_options = array(
                                'floating' => __('Icona flottante', 'marrison-cookie'),
                                'link'     => __('Link / pulsante', 'marrison-cookie'),
                            );
                            $closed_trigger_devices = array(
                                'desktop' => __('Desktop', 'marrison-cookie'),
                                'tablet'  => __('Tablet', 'marrison-cookie'),
                                'mobile'  => __('Mobile', 'marrison-cookie'),
                            );
                            ?>

                            <?php foreach ($closed_trigger_devices as $device_key => $device_label): ?>
                                <?php $field_id = 'closed_trigger_' . $device_key; ?>
                                <label for="<?php echo esc_attr($field_id); ?>" class="marrison-closed-trigger-row">
                                    <strong><?php echo esc_html($device_label); ?></strong>
                                    <select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_id); ?>">
                                        <?php foreach ($closed_trigger_options as $option_value => $option_label): ?>
                                            <option value="<?php echo esc_attr($option_value); ?>" <?php selected($settings[$field_id], $option_value); ?>>
                                                <?php echo esc_html($option_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endforeach; ?>

                            <p class="description">
                                <?php _e('Se scegli "Link / pulsante", il widget flottante non viene mostrato su quel dispositivo. Usa un link menu con URL', 'marrison-cookie'); ?>
                                <code>#marrison-cookie-preferences</code>
                                <?php _e('oppure aggiungi la classe', 'marrison-cookie'); ?>
                                <code>marrison-open-cookie-preferences</code>
                                <?php _e('a un pulsante o link.', 'marrison-cookie'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="consent_duration"><?php _e('Durata Consenso (giorni)', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="consent_duration" 
                                   name="consent_duration" 
                                   value="<?php echo esc_attr($settings['consent_duration']); ?>" 
                                   min="184"
                                   max="365">
                            <p class="description"><?php echo esc_html(marrison_cookie_site_text('Da 184 a 365 giorni. Il minimo mantiene anche il rifiuto per almeno sei mesi; la cancellazione dei cookie nel browser può rendere irriconoscibile la scelta precedente.', 'From 184 to 365 days. The minimum also preserves a refusal for at least six months; deleting browser cookies can make the previous choice unrecognizable.')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="receipt_retention"><?php _e('Conservazione ricevute (giorni)', 'marrison-cookie'); ?></label></th>
                        <td>
                            <input type="number" id="receipt_retention" name="receipt_retention" value="<?php echo esc_attr($settings['receipt_retention']); ?>" min="365" max="1095">
                            <p class="description"><?php echo esc_html(marrison_cookie_site_text('Definisci un periodo proporzionato e riportalo nell’informativa. Le ricevute contengono identificativi casuali, scelte, data e versione dell’informativa; non vengono raccolti IP o user agent. WP-Cron elimina le ricevute scadute.', 'Choose a proportionate period and disclose it in the notice. Receipts contain random identifiers, choices, date and notice version; IP addresses and user agents are not collected. WP-Cron deletes expired receipts.')); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto_scan"><?php _e('Scansione Automatica', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <label class="marrison-admin-switch">
                                <input type="checkbox"
                                       id="auto_scan"
                                       name="auto_scan"
                                       value="1"
                                       <?php checked($settings['auto_scan'], true); ?>>
                                <span class="marrison-admin-switch-slider"></span>
                                <span class="marrison-admin-switch-label"><?php _e('Attiva scansione automatica dei cookie', 'marrison-cookie'); ?></span>
                            </label>
                            <p class="description"><?php echo esc_html(marrison_cookie_site_text('Disattivata finché non la abiliti e salvi. Usa il metodo scelto nello scanner: quello remoto consuma crediti Browserless a ogni esecuzione. Le precedenti attivazioni predefinite richiedono una nuova conferma.', 'Disabled until you enable it and save. Uses the method selected in the scanner: remote runs consume Browserless credits each time. Previous default activations require confirmation again.')); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="scan_interval"><?php _e('Intervallo Scansione (giorni)', 'marrison-cookie'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="scan_interval" 
                                   name="scan_interval" 
                                   value="<?php echo esc_attr($settings['scan_interval']); ?>" 
                                   min="1" 
                                   max="30">
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="marrison-card">
                <div class="marrison-card-header">
                    <h2><?php echo esc_html(marrison_cookie_site_text('Blocco preventivo: servizi aggiuntivi', 'Prior blocking: additional services')); ?></h2>
                    <p><?php echo esc_html(marrison_cookie_site_text('I servizi noti vengono riconosciuti automaticamente. Per i tuoi servizi aggiungi un dominio o un percorso per riga, per esempio statistiche.example.it oppure /tracking/. Gli script, iframe, pixel e richieste corrispondenti attendono il consenso alla categoria.', 'Known services are recognized automatically. For your own services add one domain or path per line, for example stats.example.com or /tracking/. Matching scripts, iframes, pixels and requests wait for consent to the category.')); ?></p>
                </div>
                <table class="form-table">
                    <?php foreach (array('functional' => 'Funzionali', 'analytics' => 'Analitici', 'marketing' => 'Marketing') as $category => $label): ?>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($category); ?>_sources"><?php echo esc_html($categories[$category] ?? $label); ?></label></th>
                        <td><textarea class="large-text code" rows="3" id="<?php echo esc_attr($category); ?>_sources" name="<?php echo esc_attr($category); ?>_sources"><?php echo esc_textarea($settings[$category . '_sources']); ?></textarea></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php include MARRISON_COOKIE_PLUGIN_DIR . 'templates/service-catalog.php'; ?>
        <?php elseif ($active_tab === 'policies'): ?>
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
        <?php elseif ($active_tab === 'receipts'): ?>
            <div class="marrison-card">
                <div class="marrison-card-header"><h2><?php _e('Ricevute consenso', 'marrison-cookie'); ?></h2><p><?php echo esc_html(marrison_cookie_site_text('Storico delle scelte per dispositivo, incluse le revoche, con versione dell’informativa e verifica di integrità. L’archivio è privato e accessibile agli amministratori.', 'History of device choices, including withdrawals, with notice version and integrity verification. The archive is private and accessible to administrators.')); ?></p></div>
                <div class="marrison-shortcodes-panel">
                    <label for="marrison-receipt-date"><?php _e('Data (UTC)', 'marrison-cookie'); ?></label>
                    <input type="date" id="marrison-receipt-date" value="<?php echo esc_attr(gmdate('Y-m-d')); ?>">
                    <button type="button" class="button" id="marrison-receipts-load"><?php _e('Mostra ricevute', 'marrison-cookie'); ?></button>
                    <button type="button" class="button" id="marrison-receipts-export"><?php _e('Esporta dati tecnici (JSON)', 'marrison-cookie'); ?></button>
                    <p id="marrison-receipts-status" role="status" aria-live="polite"></p>
                    <div id="marrison-receipts-result" class="marrison-receipts-view"></div>
                    <button type="button" class="button" id="marrison-receipts-next" hidden><?php _e('Pagina successiva', 'marrison-cookie'); ?></button>
                </div>
            </div>
        <?php elseif ($active_tab === 'shortcodes'): ?>
            <div class="marrison-card">
                <div class="marrison-card-header">
                    <h2><?php _e('Shortcode', 'marrison-cookie'); ?></h2>
                    <p><?php echo esc_html(marrison_cookie_site_text('Copia il codice e inseriscilo nella pagina che hai creato.', 'Copy the code and insert it into the page you created.')); ?></p>
                </div>
                <div class="marrison-shortcodes-panel">
                    <?php include MARRISON_COOKIE_PLUGIN_DIR . 'templates/policy-shortcodes.php'; ?>
                    <p><?php echo esc_html(marrison_cookie_site_text('In Elementor usa il widget Shortcode; nell’editor WordPress usa il blocco Shortcode. Dopo aver pubblicato le pagine, imposta i loro URL nella scheda Banner per collegarle al banner cookie.', 'In Elementor use the Shortcode widget; in the WordPress editor use the Shortcode block. After publishing the pages, set their URLs in the Banner tab to link them from the cookie banner.')); ?></p>
                </div>
            </div>
        <?php elseif ($active_tab === 'scanner'): ?>
            <div class="marrison-card">
                <div class="marrison-card-header">
                    <h2><?php _e('Scanner Cookie', 'marrison-cookie'); ?></h2>
                    <p><?php _e('Scansiona e categorizza i cookie del tuo sito.', 'marrison-cookie'); ?></p>
                </div>
                
                <div class="marrison-scanner-controls">
                    <button type="button" id="marrison-repair-cookie-table" class="button">
                        <?php _e('Verifica/Ripara Tabella', 'marrison-cookie'); ?>
                    </button>

                    <button type="button" id="marrison-scan-cookies" class="button button-primary">
                        <?php _e('Avvia Scansione', 'marrison-cookie'); ?>
                    </button>
                    
                    <span id="marrison-scan-status"></span>
                    <span id="marrison-db-status"></span>
                </div>
                <div id="marrison-scan-engine" class="marrison-scanner-report" aria-live="polite">
                    <p><strong><?php echo esc_html(marrison_cookie_site_text('Motore di scansione', 'Scan engine')); ?></strong> <span id="marrison-engine-runtime"><?php echo esc_html(marrison_cookie_site_text('Verifica in corso…', 'Checking…')); ?></span></p>
                    <p>
                        <label for="marrison-scan-limit"><?php echo esc_html(marrison_cookie_site_text('Limite campioni', 'Sample limit')); ?></label>
                        <input type="number" id="marrison-scan-limit" min="1" max="5000" value="50" class="small-text">
                        <button type="button" id="marrison-scan-resume" class="button" hidden><?php echo esc_html(marrison_cookie_site_text('Riprendi scansione', 'Resume scan')); ?></button>
                        <button type="button" id="marrison-scan-cancel" class="button" hidden><?php echo esc_html(marrison_cookie_site_text('Interrompi', 'Stop')); ?></button>
                        <button type="button" id="marrison-scan-export" class="button"><?php echo esc_html(marrison_cookie_site_text('Esporta rapporto', 'Export report')); ?></button>
                    </p>
                    <p><label for="marrison-scan-mode"><?php echo esc_html(marrison_cookie_site_text('Metodo di scansione', 'Scan method')); ?></label>
                        <select id="marrison-scan-mode"><option value="commander" <?php selected(get_option('marrison_cookie_scan_mode', 'commander'), 'commander'); ?>><?php echo esc_html(marrison_cookie_site_text('Browser remoto tramite Commander', 'Remote browser through Commander')); ?></option><option value="local" <?php selected(get_option('marrison_cookie_scan_mode', 'commander'), 'local'); ?>><?php echo esc_html(marrison_cookie_site_text('Locale — solo cookie accessibili dal sito', 'Local — site-accessible cookies only')); ?></option></select></p>
                    <p class="description"><?php echo esc_html(marrison_cookie_site_text('La scansione parte premendo Avvia; quella periodica richiede un’abilitazione esplicita in Comportamento. Il metodo remoto consuma crediti Browserless e continua in background. Cambiare metodo interrompe il lavoro attivo senza avviarne un altro; una richiesta remota già inviata può comunque terminare. Il metodo locale richiede la pagina aperta e una verifica tramite iframe: se il sito la blocca, i risultati restano parziali.', 'Press Start to scan; periodic scans require explicit activation in Behavior. The remote method consumes Browserless credits and continues in the background. Changing method stops active work without starting another scan; an already submitted remote request may still finish. Local scanning requires this page open and iframe verification: if the site blocks it, results remain partial.')); ?></p>
                    <details class="marrison-scan-additional"><summary><?php echo esc_html(marrison_cookie_site_text('Pagine aggiuntive da verificare', 'Additional pages to test')); ?></summary>
                        <label for="marrison-scan-additional"><?php echo esc_html(marrison_cookie_site_text('URL dello stesso sito, uno per riga (massimo 50). Usali per pagine con condizioni o servizi particolari: saranno verificate anche se condividono il template.', 'Same-site URLs, one per line (up to 50). Use these for pages with special conditions or services: they will be checked even when sharing a template.')); ?></label>
                        <textarea id="marrison-scan-additional" rows="3" class="large-text" style="width:100%;box-sizing:border-box"></textarea>
                    </details>
                    <progress id="marrison-engine-progress" max="1" value="0" style="width:100%"></progress>
                    <p id="marrison-engine-summary" role="status" aria-live="polite"></p>
                    <p id="marrison-engine-current" style="overflow-wrap:anywhere"></p>
                    <p id="marrison-engine-sampling"></p>
                    <p id="marrison-engine-services" style="overflow-wrap:anywhere"></p>
                    <div id="marrison-browser-storage"><h3><?php echo esc_html(marrison_cookie_site_text('Dati del browser: localStorage e sessionStorage', 'Browser data: localStorage and sessionStorage')); ?></h3>
                        <p id="marrison-browser-storage-note"></p>
                        <table class="widefat striped"><thead><tr><th><?php esc_html_e('Chiave', 'marrison-cookie'); ?></th><th><?php esc_html_e('Tipo', 'marrison-cookie'); ?></th><th><?php esc_html_e('Origine', 'marrison-cookie'); ?></th><th><?php esc_html_e('Rilevazione', 'marrison-cookie'); ?></th></tr></thead><tbody id="marrison-browser-storage-rows"></tbody></table>
                    </div>
                    <div id="marrison-service-cookies" hidden>
                        <h3>Cookie documentati dei servizi rilevati</h3>
                        <p>Questi cookie provengono dalla documentazione dei fornitori. Il servizio è presente nel sito, ma l’installazione di ogni cookie dipende da consenso, utilizzo e browser. Non sono conteggiati come cookie osservati.</p>
                        <div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Servizio</th><th>Cookie</th><th>Dominio</th><th>Durata dichiarata</th><th>Finalità</th><th>Fonte</th></tr></thead><tbody id="marrison-service-cookie-rows"></tbody></table></div>
                    </div>
                    <details><summary><?php echo esc_html(marrison_cookie_site_text('Pagine campione e gruppi rappresentati', 'Sample pages and represented groups')); ?></summary><ul id="marrison-engine-samples" style="overflow-wrap:anywhere"></ul></details>
                    <details><summary><?php echo esc_html(marrison_cookie_site_text('Dettagli e problemi rilevati', 'Details and detected issues')); ?></summary><pre id="marrison-engine-details" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre></details>
                </div>

                <div class="marrison-scanner-filters">
                    <label for="marrison-category-filter"><?php _e('Filtra per categoria:', 'marrison-cookie'); ?></label>
                    <select id="marrison-category-filter">
                        <option value="all"><?php _e('Tutte', 'marrison-cookie'); ?></option>
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="marrison-cookie-list">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Nome Cookie', 'marrison-cookie'); ?></th>
                                <th><?php _e('Categoria', 'marrison-cookie'); ?></th>
                                <th><?php _e('Dominio', 'marrison-cookie'); ?></th>
                                <th><?php _e('Scadenza rilevata', 'marrison-cookie'); ?></th>
                                <th><?php _e('Fonte', 'marrison-cookie'); ?></th>
                                <th><?php _e('Data Scansione', 'marrison-cookie'); ?></th>
                                <th><?php _e('Azioni', 'marrison-cookie'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="marrison-cookie-table-body">
                            <tr>
                                <td colspan="7"><?php _e('Clicca su "Avvia Scansione" per vedere i cookie', 'marrison-cookie'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!in_array($active_tab, array('scanner', 'shortcodes', 'receipts'), true)): ?>
            <p class="submit">
                <input type="submit" 
                       name="marrison_save_settings" 
                       class="button button-primary" 
                       value="<?php echo esc_attr__('Salva Impostazioni', 'marrison-cookie'); ?>">
            </p>
        <?php endif; ?>
    </form>
</div>
