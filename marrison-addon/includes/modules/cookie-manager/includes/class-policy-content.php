<?php
if (!defined('ABSPATH')) { exit; }
/** Configured policy content and stable evidence of the information offered to visitors. */
final class Marrison_Addon_Cookie_Policy_Content {
    public static function snapshot() {
        $snapshot = array('schema' => 1, 'revision_label' => (string) get_option('marrison_cookie_policy_revision', '1'));
        $snapshot['generator_version'] = 2;
        $snapshot['privacy_profile'] = self::profile();
        $snapshot['generated_privacy'] = self::content('privacy');
        $snapshot['generated_cookie'] = self::content('cookie');
        foreach (array('privacy_policy_content', 'cookie_policy_content', 'privacy_policy_url', 'cookie_policy_url', 'banner_title', 'banner_description', 'functional_sources', 'analytics_sources', 'marketing_sources') as $key) {
            $snapshot[$key] = (string) get_option('marrison_cookie_' . $key, '');
        }
        $snapshot['local_pages'] = array();
        foreach (array('privacy_policy_url', 'cookie_policy_url') as $key) {
            $url = $snapshot[$key];
            $id = $url && function_exists('url_to_postid') ? url_to_postid($url) : 0;
            $post = $id && function_exists('get_post') ? get_post($id) : null;
            if ($post && (!isset($post->post_status) || $post->post_status === 'publish')) {
                $snapshot['local_pages'][$key] = array('id' => (int) $id, 'content' => (string) $post->post_content,
                    'elementor' => function_exists('get_post_meta') ? (string) get_post_meta($id, '_elementor_data', true) : '');
            }
        }
        $snapshot['service_categories'] = (array) get_option('marrison_cookie_service_categories', array());
        ksort($snapshot['service_categories']);
        $snapshot['google_advanced'] = (bool) get_option('marrison_cookie_google_advanced', false);
        return $snapshot;
    }

    public static function revision($snapshot = null) {
        return hash('sha256', wp_json_encode($snapshot === null ? self::snapshot() : $snapshot, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public static function consent_version($snapshot = null) {
        $snapshot = $snapshot === null ? self::snapshot() : $snapshot;
        // The controller changes the revision for significant processing changes.
        // Formatting/layout corrections alone must not solicit a new choice.
        $contract = array('revision_label' => $snapshot['revision_label']);
        $contract['service_categories'] = $snapshot['service_categories'] ?? array();
        $contract['google_advanced'] = !empty($snapshot['google_advanced']);
        foreach (array('functional_sources', 'analytics_sources', 'marketing_sources') as $key) {
            $rules = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n]+/', strtolower($snapshot[$key]))))));
            sort($rules, SORT_STRING);
            $contract[$key] = $rules;
        }
        return hash('sha256', wp_json_encode($contract, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /** Structured inputs only: personal data are never inferred from the example site. */
    public static function fields() {
        return array(
            'controller_name' => array('label' => 'Titolare / ragione sociale', 'group' => 'Titolare e contatti'),
            'controller_address' => array('label' => 'Indirizzo completo e Paese', 'group' => 'Titolare e contatti'),
            'controller_email' => array('label' => 'Email per la privacy e i diritti', 'type' => 'email', 'group' => 'Titolare e contatti'),
            'controller_vat' => array('label' => 'Partita IVA / codice fiscale (facoltativo)', 'group' => 'Titolare e contatti'),
            'controller_phone' => array('label' => 'Telefono (facoltativo)', 'group' => 'Titolare e contatti'),
            'representative' => array('label' => 'Rappresentante nell’UE, se nominato: nome e contatti', 'group' => 'Titolare e contatti'),
            'dpo' => array('label' => 'Responsabile protezione dati (DPO), se nominato: nome e contatti', 'group' => 'Titolare e contatti'),
            'contact_enabled' => array('label' => 'Il sito riceve richieste tramite moduli o email', 'type' => 'checkbox', 'default' => true, 'group' => 'Moduli e richieste'),
            'contact_data' => array('label' => 'Dati richiesti nei moduli', 'default' => 'Nome, indirizzo email, eventuale telefono e contenuto della richiesta', 'group' => 'Moduli e richieste'),
            'contact_basis' => array('label' => 'Base giuridica delle richieste', 'type' => 'select', 'default' => 'contract', 'options' => array('contract' => 'Richieste contrattuali / preventivi (art. 6.1.b)', 'interest' => 'Legittimo interesse a rispondere (art. 6.1.f)', 'consent' => 'Consenso specifico (art. 6.1.a)'), 'group' => 'Moduli e richieste'),
            'contact_retention' => array('label' => 'Conservazione delle richieste: periodo o criterio', 'default' => 'Per il tempo necessario a rispondere e, di norma, fino a 12 mesi dalla chiusura della richiesta, salvo successivi rapporti contrattuali o obblighi di legge', 'group' => 'Moduli e richieste'),
            'logs_enabled' => array('label' => 'Il sito / hosting conserva log tecnici e di sicurezza', 'type' => 'checkbox', 'default' => true, 'group' => 'Log e newsletter'),
            'logs_retention' => array('label' => 'Conservazione dei log: periodo o criterio verificato con il fornitore', 'group' => 'Log e newsletter'),
            'marketing_enabled' => array('label' => 'È presente una newsletter o un servizio di marketing con consenso separato', 'type' => 'checkbox', 'default' => false, 'group' => 'Log e newsletter'),
            'marketing_retention' => array('label' => 'Conservazione dei dati marketing: periodo o criterio', 'default' => 'Fino alla revoca del consenso e comunque non oltre 24 mesi dall’ultima interazione utile', 'group' => 'Log e newsletter'),
            'recipients' => array('label' => 'Fornitori e destinatari: hosting, email, gestione moduli, antispam, altri servizi (nomi o categorie)', 'type' => 'textarea', 'group' => 'Destinatari e trasferimenti'),
            'transfers' => array('label' => 'Trasferimenti fuori dallo Spazio economico europeo', 'type' => 'select', 'options' => array('' => 'Seleziona dopo aver verificato i fornitori', 'none' => 'Non previsti', 'adequacy' => 'Paesi / destinatari coperti da decisione di adeguatezza', 'scc' => 'Clausole contrattuali standard o altre garanzie documentate'), 'group' => 'Destinatari e trasferimenti'),
            'transfer_details' => array('label' => 'Destinatari / Paesi, garanzie e come ottenere una copia o consultarle', 'type' => 'textarea', 'group' => 'Destinatari e trasferimenti'),
            'automated' => array('label' => 'Sono adottate decisioni automatizzate con effetti giuridici o analogamente significativi', 'type' => 'checkbox', 'default' => false, 'group' => 'Trattamenti particolari'),
            'automated_details' => array('label' => 'Logica, importanza, conseguenze e intervento umano delle decisioni automatizzate', 'type' => 'textarea', 'group' => 'Trattamenti particolari'),
            'other_processing' => array('label' => 'Ulteriori trattamenti effettivi: dati, finalità, base giuridica, obbligatorietà e conservazione (es. account, ordini, candidature)', 'type' => 'textarea', 'group' => 'Trattamenti particolari'),
        );
    }

    public static function sanitize_profile($input) {
        $input = is_array($input) ? $input : array(); $clean = array();
        foreach (self::fields() as $key => $field) {
            $value = isset($input[$key]) && is_scalar($input[$key]) ? (string) $input[$key] : '';
            $type = $field['type'] ?? 'text';
            if ($type === 'checkbox') { $clean[$key] = in_array($value, array('1', 'on', 'true'), true); }
            elseif ($type === 'select') { $clean[$key] = array_key_exists($value, $field['options']) ? $value : ($field['default'] ?? ''); }
            elseif ($type === 'email') { $clean[$key] = sanitize_email($value); }
            else { $clean[$key] = substr($type === 'textarea' ? sanitize_textarea_field($value) : sanitize_text_field($value), 0, $type === 'textarea' ? 6000 : 1500); }
        }
        return $clean;
    }

    public static function profile() {
        $saved = get_option('marrison_cookie_privacy_profile', array()); $defaults = array();
        foreach (self::fields() as $key => $field) { $defaults[$key] = $field['default'] ?? (($field['type'] ?? '') === 'checkbox' ? false : ''); }
        return array_replace($defaults, self::sanitize_profile(array_replace($defaults, is_array($saved) ? $saved : array())));
    }

    /** Kept for compatibility: every profile field is optional. */
    public static function required_fields($profile = null) {
        return array();
    }

    public static function missing_fields($profile = null) {
        $p = $profile === null ? self::profile() : $profile; $missing = array();
        foreach (self::required_fields($p) as $key => $label) { if (empty($p[$key])) { $missing[$key] = $label; } }
        return $missing;
    }

    public static function missing($profile = null) {
        return array_values(self::missing_fields($profile));
    }

    private static function paragraph($title, $text) { return trim($text) === '' ? '' : '<p><strong>' . esc_html($title) . '</strong></p><p>' . nl2br(esc_html($text)) . '</p>'; }

    public static function content($kind) {
        $p = self::profile();
        $t = function($it, $en) { return marrison_cookie_site_text($it, $en); };
        if ($kind === 'cookie') {
            $html = self::paragraph('Cookie Policy', $t('Questa informativa descrive l’uso di cookie e tecnologie analoghe sul sito, le scelte disponibili e le informazioni rilevate sui servizi presenti.', 'This notice describes cookies and similar technologies used on this website, available choices and observations about its services.'));
            if ($p['controller_name']) { $html .= self::paragraph($t('Titolare e contatti', 'Controller and contacts'), $p['controller_name'] . ($p['controller_address'] ? ' — ' . $p['controller_address'] : '') . ($p['controller_email'] ? ' — ' . $p['controller_email'] : '')); }
            $html .= self::paragraph($t('Cookie e dati del browser', 'Cookies and browser storage'), $t('I cookie sono piccoli file memorizzati dal browser. Possono essere di prima o terza parte, di sessione oppure persistenti. LocalStorage e sessionStorage conservano invece dati nell’archivio del browser: non sono cookie, ma possono svolgere funzioni analoghe.', 'Cookies are small files stored by the browser. They may be first or third party, session or persistent cookies. LocalStorage and sessionStorage are separate browser stores and can serve similar purposes.'));
            $html .= '<p><strong>' . esc_html($t('Categorie e consenso', 'Categories and consent')) . '</strong></p><ul>';
            foreach (array(
                $t('Necessari: permettono le funzioni essenziali, la sicurezza e il salvataggio delle preferenze. Non richiedono consenso quando sono strettamente necessari al servizio richiesto.', 'Necessary: essential functions, security and choice storage. Consent is not required where strictly necessary for the requested service.'),
                $t('Funzionali: abilitano funzioni aggiuntive e contenuti esterni. Se non strettamente necessari, vengono attivati solo dopo la scelta corrispondente.', 'Functional: extra functions and external content. Non-essential functions are enabled following the corresponding choice.'),
                $t('Analitici: misurano l’utilizzo del sito. Le tecnologie non esenti sono subordinate al consenso.', 'Analytics: measure website usage. Technologies that are not exempt require consent.'),
                $t('Marketing: eventuali tecnologie pubblicitarie o di profilazione richiedono un consenso specifico.', 'Marketing: advertising or profiling technologies, if present, require specific consent.')
            ) as $line) { $html .= '<li>' . esc_html($line) . '</li>'; }
            $html .= '</ul>' . self::paragraph($t('Come scegliere o cambiare idea', 'Managing your choice'), $t('Il banner consente di accettare tutti i cookie, rifiutare quelli opzionali oppure scegliere per categoria. È possibile modificare o revocare la scelta in ogni momento tramite il collegamento alle preferenze disponibile qui sotto o il comando cookie del sito. La revoca non pregiudica i trattamenti già effettuati lecitamente. Il browser permette inoltre di eliminare o bloccare i cookie: alcune funzionalità potrebbero non essere disponibili.', 'The banner allows all cookies, necessary cookies only, or a category selection. Use the preferences link below or the website cookie control to change or withdraw your choice at any time. Withdrawal does not affect prior lawful processing. Your browser can also delete or block cookies; some features may be unavailable.'));
            $html .= self::paragraph($t('Durata e prova della scelta', 'Duration and evidence of the choice'), sprintf($t('La scelta viene conservata per %d giorni, salvo modifiche sostanziali che richiedano una nuova scelta. Le ricevute sono conservate per %d giorni con identificativi casuali, data, categorie e versione informativa; il registro del modulo non raccoglie indirizzi IP o user agent. Per ogni tecnologia si applica la durata indicata nell’inventario quando disponibile.', 'The choice is stored for %d days, unless substantial changes require a new choice. Receipts are retained for %d days with random identifiers, date, categories and notice version; the module receipt log does not collect IP addresses or user agents. Individual technology durations are shown in the inventory when available.'), max(184, min(365, (int) get_option('marrison_cookie_consent_duration', 365))), max(365, min(1095, (int) get_option('marrison_cookie_receipt_retention', 365)))));
            $html .= self::paragraph($t('Servizi esterni e aggiornamento dell’elenco', 'External services and inventory updates'), $t('Contenuti come mappe, video e strumenti statistici possono comunicare con i rispettivi fornitori. L’elenco distingue cookie effettivamente osservati e informazioni documentate dai fornitori: la presenza del servizio non dimostra l’installazione di tutti i suoi cookie. Consenso, interazioni, browser e area geografica possono modificare il risultato. Per destinatari, trasferimenti, basi giuridiche e diritti consulta anche la Privacy Policy del sito.', 'Maps, videos and statistics tools may contact their providers. The inventory distinguishes actual observations from provider documentation: a service presence does not prove every cookie was installed. Consent, interactions, browser and location can change results. See also the website Privacy Policy for recipients, transfers, legal bases and rights.'));
            return $html;
        }
        $html = self::paragraph('Privacy Policy', $t('Informativa sul trattamento dei dati personali ai sensi dell’articolo 13 del Regolamento (UE) 2016/679 (GDPR), relativa alla navigazione e alle funzionalità del sito.', 'Information under Article 13 of Regulation (EU) 2016/679 (GDPR) about browsing and using this website.'));
        $identity = implode("\n", array_filter(array($p['controller_name'], $p['controller_address'], $p['controller_email'] ? $t('Contatto privacy: ', 'Privacy contact: ') . $p['controller_email'] : '')));
        foreach (array('controller_vat' => 'P. IVA / C.F.: ', 'controller_phone' => $t('Telefono: ', 'Phone: '), 'representative' => $t('Rappresentante UE: ', 'EU representative: '), 'dpo' => 'DPO: ') as $key => $label) { if ($p[$key]) { $identity .= "\n" . $label . $p[$key]; } }
        $html .= self::paragraph($t('1. Titolare e contatti', '1. Controller and contacts'), $identity);
        $html .= '<p><strong>' . esc_html($t('2. Dati, finalità, basi giuridiche e conservazione', '2. Data, purposes, legal bases and retention')) . '</strong></p><ul>';
        $items = array();
        if ($p['contact_enabled']) {
            $bases = array('contract' => $t('esecuzione di misure precontrattuali o di un contratto su richiesta dell’interessato (art. 6.1.b GDPR)', 'requested pre-contractual steps or contract performance (Article 6(1)(b) GDPR)'), 'interest' => $t('legittimo interesse a gestire e rispondere alle comunicazioni ricevute (art. 6.1.f GDPR), nel rispetto dei diritti dell’interessato', 'legitimate interest in handling and replying to communications (Article 6(1)(f) GDPR), respecting individual rights'), 'consent' => $t('consenso specifico e revocabile (art. 6.1.a GDPR)', 'specific, withdrawable consent (Article 6(1)(a) GDPR)'));
            $items[] = $t('Richieste tramite moduli o email. ', 'Form or email enquiries. ') . ($p['contact_data'] ? $t('Dati: ', 'Data: ') . $p['contact_data'] . '. ' : '') . $t('Finalità: rispondere, fornire informazioni e gestire la richiesta. Base giuridica: ', 'Purpose: reply, provide information and handle the enquiry. Legal basis: ') . $bases[$p['contact_basis']] . '. ' . ($p['contact_retention'] ? $t('Conservazione: ', 'Retention: ') . $p['contact_retention'] . '. ' : '') . $t('Il conferimento è facoltativo, ma i dati necessari alla risposta devono essere forniti per poter gestire la richiesta. Non inviare dati particolari o non pertinenti.', 'Providing data is optional, but information needed to reply is required to handle the enquiry. Do not send sensitive or unrelated data.');
        }
        if ($p['logs_enabled']) { $items[] = $t('Navigazione e sicurezza. I sistemi tecnici possono trattare indirizzo IP, data e ora, URL richiesti, informazioni sul browser e log di errore per erogare il sito, prevenire abusi e gestire incidenti. Base: legittimo interesse alla disponibilità e sicurezza (art. 6.1.f GDPR). Questi dati sono necessari alla comunicazione tecnica.', 'Browsing and security. Technical systems may process IP address, date and time, requested URLs, browser information and error logs to operate and secure the site and handle incidents. Basis: legitimate interest in availability and security (Article 6(1)(f) GDPR). These data are necessary for technical communication.') . ($p['logs_retention'] ? ' ' . $t('Conservazione: ', 'Retention: ') . $p['logs_retention'] : ''); }
        if ($p['marketing_enabled']) { $items[] = $t('Newsletter e marketing. Indirizzo email ed eventuali dati identificativi sono utilizzati per le comunicazioni cui ci si è iscritti, solo con consenso separato (art. 6.1.a GDPR). L’adesione è facoltativa e non condiziona la risposta alle richieste. Il consenso può essere revocato dal link di disiscrizione o contattando il titolare.', 'Newsletter and marketing. Email and identification data are used for subscribed communications only with separate consent (Article 6(1)(a) GDPR). Subscription is optional and not a condition for enquiry replies. Withdraw through the unsubscribe link or the controller contact.') . ($p['marketing_retention'] ? ' ' . $t('Conservazione: ', 'Retention: ') . $p['marketing_retention'] : ''); }
        $items[] = $t('Obblighi di legge e tutela dei diritti. I dati pertinenti possono essere conservati per obblighi normativi (art. 6.1.c GDPR) o per accertare, esercitare o difendere diritti (art. 6.1.f GDPR), limitatamente ai termini di legge, prescrizione e durata dell’eventuale contenzioso.', 'Legal duties and claims. Relevant data may be retained to comply with law (Article 6(1)(c) GDPR) or establish, exercise or defend claims (Article 6(1)(f) GDPR), limited to statutory periods and any proceedings.');
        foreach ($items as $item) { $html .= '<li>' . esc_html($item) . '</li>'; } $html .= '</ul>';
        if ($p['other_processing']) { $html .= self::paragraph($t('Ulteriori trattamenti', 'Other processing'), $p['other_processing']); }
        $html .= self::paragraph($t('3. Modalità e destinatari', '3. Processing and recipients'), $t('Il trattamento avviene con strumenti informatici e, quando necessario, manuali, da personale autorizzato e fornitori incaricati secondo i rispettivi ruoli. Sono adottate misure tecniche e organizzative proporzionate ai rischi. ', 'Data are processed electronically and, where necessary, manually by authorised personnel and providers in their respective roles, with technical and organisational safeguards proportionate to the risks. ') . ($p['recipients'] ? $t('Destinatari o categorie di destinatari: ', 'Recipients or recipient categories: ') . $p['recipients'] . '. ' : '') . $t('I dati possono inoltre essere comunicati alle autorità quando richiesto dalla legge. L’accesso è limitato alle finalità descritte.', 'Data may also be disclosed to authorities where required by law. Access is limited to the described purposes.'));
        $transfer = $p['transfers'] === 'none' ? $t('Secondo la configurazione dei servizi dichiarata dal titolare non sono previsti trasferimenti di dati personali fuori dallo Spazio economico europeo.', 'According to the service configuration declared by the controller, no transfers outside the European Economic Area are planned.') : ($p['transfers'] === 'adequacy' ? $t('I trasferimenti dichiarati si basano sulle decisioni di adeguatezza applicabili (art. 45 GDPR). ', 'Declared transfers rely on applicable adequacy decisions (Article 45 GDPR). ') : $t('I trasferimenti dichiarati si basano sulle garanzie indicate ai sensi degli artt. 46 e seguenti GDPR. ', 'Declared transfers rely on the stated safeguards under Articles 46 et seq. GDPR. ')) . $p['transfer_details'];
        $html .= self::paragraph($t('4. Trasferimenti internazionali', '4. International transfers'), $p['transfers'] ? $transfer : $p['transfer_details']);
        $html .= self::paragraph($t('5. Cookie e tecnologie analoghe', '5. Cookies and similar technologies'), $t('Il sito utilizza cookie e può integrare servizi esterni. La Cookie Policy descrive le tecnologie rilevate e le modalità di scelta. Le tecnologie opzionali soggette a consenso possono essere rifiutate o disattivate dalle preferenze cookie del sito.', 'This site uses cookies and may integrate external services. The Cookie Policy describes observed technologies and available choices. Optional technologies requiring consent can be rejected or disabled in the website cookie preferences.'));
        $html .= self::paragraph($t('6. Diritti e reclami', '6. Rights and complaints'), $t('Nei casi e nei limiti previsti dagli artt. 15–22 GDPR puoi chiedere accesso, rettifica, cancellazione, limitazione e portabilità dei dati e opporti al trattamento fondato sul legittimo interesse. Puoi revocare in ogni momento il consenso, senza pregiudicare la liceità del trattamento precedente. Per esercitare i diritti contatta il titolare del sito. Il titolare risponde di norma entro un mese, salvo le proroghe motivate previste dalla normativa. Puoi proporre reclamo al Garante per la protezione dei dati personali (www.garanteprivacy.it) o all’autorità di controllo competente, e ricorrere all’autorità giudiziaria.', 'Where applicable under Articles 15–22 GDPR, you may request access, rectification, erasure, restriction and portability and object to processing based on legitimate interests. Consent may be withdrawn at any time without affecting prior lawful processing. Contact the website controller to exercise your rights. Replies are normally provided within one month, subject to lawful justified extensions. You may complain to the Italian data protection authority (www.garanteprivacy.it) or the competent supervisory authority and seek a judicial remedy.'));
        $html .= self::paragraph($t('7. Decisioni automatizzate', '7. Automated decisions'), $p['automated'] ? $p['automated_details'] : $t('Il titolare dichiara di non adottare decisioni basate esclusivamente su trattamenti automatizzati che producano effetti giuridici o incidano in modo analogamente significativo sull’interessato.', 'The controller declares that no solely automated decisions producing legal or similarly significant effects are adopted.'));
        return $html . self::paragraph($t('8. Aggiornamenti', '8. Updates'), $t('L’informativa viene aggiornata quando cambiano i trattamenti o i servizi utilizzati. La versione pubblicata su questa pagina è quella corrente. Revisione: ', 'This notice is updated when processing or services change. This page contains the current version. Revision: ') . (string) get_option('marrison_cookie_policy_revision', '1'));
    }
}
