# Rapporto finale: Cookie Manager e PHP Snippets 1.3.79–1.3.84

Verifiche del 24 ottobre 2026. Baseline: `4e9867e` (1.3.78); sei commit, uno per fase. Le estensioni additive e la prosecuzione sono state autorizzate dall’utente. Il codice e le impostazioni preesistenti sono conservati. Le modifiche già presenti a Info Azienda, Ticker, relativi test e ZIP non sono incluse nei commit di questa attività.

## Fase 1 — catalogo, 1.3.79

Commit `e4df069`. Il [rapporto della fase 1](cookie-manager-phase-1-1.3.79.md) riporta ogni requisito, fonti ufficiali, classificazioni e verifiche. Dieci profili, validazione JSON/schema/regex, confini host, regole personalizzate, pagamenti classificabili, host sconosciuti bloccati e badge Da verificare. Non vengono inventati cookie PayPal/Klarna o durate incerte. I dati del catalogo sono dichiarazioni del fornitore, non osservazioni del sito.

## Fase 2 — scansione locale, 1.3.80

Commit `19eb335`.

| Punto | Esito | Evidenza e intervento |
| --- | --- | --- |
| 2.1 Scanner statico locale | Confermato e corretto | Mancava l’inventario statico. `marrison-addon/includes/modules/cookie-manager/includes/class-static-scanner.php:5`: WP_Query di 20 ID pubblicati senza password, cursore pagina/offset, batch di due secondi e limite memoria. Contenuti, Elementor, registri script/stili, widget, tema e lista esplicita di opzioni/gateway. `marrison-addon/includes/modules/cookie-manager/includes/class-static-scanner.php:34`: limiti di byte/nodi/URL segnalano risultato parziale. Nessuna richiesta HTTP. |
| 2.2 Storage e distinzione dichiarato/osservato | Confermato e corretto | `marrison-addon/includes/modules/cookie-manager/includes/class-static-scanner.php:100` e `marrison-addon/includes/modules/cookie-manager/includes/class-cookie-scanner.php:949`: stesse tabelle/JSON, fonte catalogo, observed=false. I dati osservati precedenti prevalgono sulle dichiarazioni e vengono conservati. In SQL observed è derivato dalla fonte; non viene aggiunta una colonna ridondante. |
| 2.3 Iframe veloce opzionale | Confermato e corretto | `marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php:373` e `assets/js/scanner.js` del modulo: default spento, sampler default 3, massimo 10, budget totale 30 secondi (10–120), timeout caricamento 8 secondi, risposta 5 secondi, agente 4 secondi e finestra di quiete ridotta. Risultato e UI spiegano il limite sui cookie di terze parti. Gli script tardivi richiedono una prova manuale. |
| 2.4 Ritiro remoto | Confermato e corretto | `marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php:682`: percorso disabilitato, nessuna scelta remota nella UI. L’impostazione commander è trattata come local; avviso una tantum. Test dimostra che nuovi job non eseguono richieste remote. Codice storico deprecato mantenuto dove richiesto. README attuali aggiornati. |
| 2.5 Rilevamento passivo | Confermato e corretto | `marrison-addon/includes/modules/cookie-manager/includes/class-passive-detection.php:4` e `marrison-addon/includes/modules/cookie-manager/includes/class-request-limits.php:5`: opzione false per default, nonce/origine, solo host, massimo 20/richiesta, 100 host distinti, 500 richieste/giorno e deduplica. Nessun identificatore del visitatore o IP salvato da questa funzione. Il README richiede di descriverla nell’informativa. |

I registri WordPress possono indicare risorse registrate ma non effettivamente caricate. Configurazioni dinamiche, opzioni non incluse nella lista e aree protette non sono copertura garantita. La scansione parte da azione esplicita; l’automazione preesistente conserva il suo consenso esplicito separato.

## Fase 3 — consenso e blocco, 1.3.81

Commit `6086015`.

| Punto | Esito | Evidenza e intervento |
| --- | --- | --- |
| 3.1 Google Consent Mode v2 | Confermato e corretto | `marrison-addon/includes/modules/cookie-manager/assets/js/consent-runtime.js:14`: quattro default denied e wait_for_update prima dei tag; aggiornamenti per categorie dopo stato verificato. GA4/GTM restano bloccati in modalità base. `marrison-addon/includes/modules/cookie-manager/templates/admin-settings.php:396`: avanzata false per default e nota legale. Nessun ping Google nei test prima della scelta. |
| 3.2 Meta Pixel | Confermato e corretto | Stesso runtime: fbevents attende Marketing, fbq consent/revoke alla revoca. Verificato con richieste e fbq simulati; nessuna sessione Meta reale. |
| 3.3 GA4/GTM/Pixel/Maps/YouTube | Confermato e corretto | Pattern del catalogo e test PHP/JS prima/dopo consenso. `marrison-addon/includes/modules/cookie-manager/assets/js/frontend.js:93`: pulsante senza miniature remote, un solo video autorizzato in memoria e youtube-nocookie.com; non abilita globalmente Marketing e viene neutralizzato alla revoca. |
| 3.4 Gateway Necessari | Non confermato come difetto nel blocco; comportamento verificato e documentato | I test PHP/JS confermano il passaggio di Stripe/PayPal/Klarna classificati necessary. Dove il plugin del gateway li carica resta una sua impostazione; non sono state cambiate impostazioni WooCommerce. Caricamenti reali su prodotto/carrello e pagamento completo: da verificare sul sito. |

Fonti ufficiali: [Google, modalità base](https://developers.google.com/tag-platform/security/guides/consent?consentmode=basic), [base e avanzata](https://developers.google.com/tag-platform/security/concepts/consent-mode). I test provano il codice dell’addon, non il contenuto di qualsiasi contenitore GTM o codice di terzi che aggiri il runtime.

## Fase 4 — banner, 1.3.82

Commit `ccafec2`. Riferimento: [FAQ Cookie del Garante](https://www.garanteprivacy.it/faq/Cookie); nessuna certificazione di conformità.

| Punto | Esito | Evidenza e intervento |
| --- | --- | --- |
| Accetta/Rifiuta con pari evidenza | Non confermato come difetto; verificato | Test Chrome del vero template PHP a 1280 e 390 px confronta gli stili calcolati dei due pulsanti. Stessa evidenza; nessuna preferenza opzionale preselezionata. |
| Cookie policy e scelta granulare | Non confermato come difetto; verificato | Test browser di link configurato e apertura preferenze. Il titolare deve indicare una pagina policy valida. |
| Scroll non equivale a consenso | Non confermato come difetto; verificato | Il test scroll non crea uno stato accettato né una ricevuta. |
| Durata scelta | Confermato e corretto | `marrison-addon/includes/modules/cookie-manager/includes/class-consent-store.php:23`: default/minimo 180 giorni, massimo 365. Le modifiche sostanziali di informativa/classificazione invalidano la scelta. |
| Revoca accessibile | Non confermato come difetto; verificato | Widget flottante e shortcode preferenze già presenti, preservati. Test browser di revoca e test shortcode con parsing WordPress reale. |
| Solo cookie tecnici | Confermato e corretto | `marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php:632`: solo dopo scansione completa, senza servizi/cookie/regole opzionali o host sconosciuti. Banner/UI omessi dove non necessari, stato mostrato in admin; shortcode preferenze resta disponibile. Un risultato parziale non dà questa conclusione. |
| Checklist e disclaimer | Confermato e corretto | `marrison-addon/includes/modules/cookie-manager/templates/admin-settings.php:400`: quattro dichiarazioni facoltative, nessun blocco della pubblicazione. Responsabilità del titolare chiaramente indicata. |

## Fase 5 — sicurezza Cookie Manager, 1.3.83

Il commit della fase 5 include i correttivi emersi nei test integrati su limiti archivio, risultati statici troncati e timeout dell’agente.

| Punto | Esito | Evidenza e intervento |
| --- | --- | --- |
| 5.1 ABSPATH | Confermato e corretto | Rilevate 16 assenze nella baseline, anziché 15. Guardia presente ora in tutti gli includes/template del modulo, anche nei file nuovi. Alcune guardie erano già state introdotte nelle fasi precedenti. Esempio: `marrison-addon/includes/modules/cookie-manager/includes/class-consent-store.php:80` nel file protetto dalla riga 2. |
| 5.2 Endpoint e rate limit | Confermato e corretto per limiti/origine mancanti; nonce principali già presenti | `marrison-addon/includes/modules/cookie-manager/includes/class-consent-store.php:80`, `marrison-addon/includes/modules/cookie-manager/includes/class-cookie-banner.php:316`, `marrison-addon/includes/modules/cookie-manager/includes/class-consent-store.php:153`: nonce + origine per stato, salvataggio, preferenze e lista. 30 tentativi/10 minuti per bucket e 10.000/giorno globali, transient + contatori con lock. `marrison-addon/includes/modules/cookie-manager/includes/class-cookie-banner.php:369`: il rinnovo di un nonce scaduto è un bootstrap con origine e limite, non può richiedere un nonce ancora valido. Nessun accesso alle ricevute tramite questo bootstrap. |
| 5.3 Archivio ricevute | Confermato e corretto | `marrison-addon/includes/modules/cookie-manager/includes/class-consent-store.php:147`: 4 MiB/giorno, 64 MiB/2.000 file complessivi, controllando anche la scrittura prevista, retention esistente e fallimento senza applicare consenso. Nomi HMAC, prefisso PHP exit, index.php; rinomina dei file legacy conserva contenuti e firme. `marrison-addon/includes/modules/cookie-manager/includes/class-consent-store.php:288`: probe temporaneo e rimozione garantita; 200 critico, 401/403/404 protetto, errore rete/redirect non certificato. Regola nginx nel README. |
| 5.4 Input | Confermato e corretto per revision | `marrison-addon/includes/modules/cookie-manager/includes/class-consent-store.php:112`: controllo tipo, wp_unslash e sanitizzazione coerente. Cookie firmati e token restano byte esatti per hash_equals, con controllo del tipo; non si altera un formato firmato per applicare una sanitizzazione generica. Origin/host normalizzati e verificati. |
| 5.5 HTML/XSS | Non confermata una vulnerabilità in tutti i sink segnalati; difesa aggiunta alla lista | Template e tabelle usavano già escaping/text per i dati cookie. La lista di `marrison-addon/includes/modules/cookie-manager/includes/class-cookie-banner.php:316` viene ora ricostruita nel frontend con soli DIV/UL/LI/STRONG/SPAN, testo e classi note. I sink di lettura del testo già escapato e quelli usati per l’intercettazione del DOM non sono stati rimossi come vulnerabilità presunte. Test ricevute e policy con dati ostili superati. |
| 5.6 SQL | Non confermata SQL injection dai valori costanti segnalati; uniformato prepare | `marrison-addon/includes/modules/cookie-manager/includes/class-cookie-consent.php:353` e verifica tabella scanner preparano i valori; tabelle da prefix, liste IN con segnaposto. Non introdotte query LIKE sui contenuti. |
| 5.7 Proof/worker pubblico | Confermato e corretto riducendo superficie | `marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php:649` e `marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php:934`: hook/route remoti non registrati; metodi residui rifiutano l’uso. Nessun endpoint pubblico remoto al quale aggiungere un nuovo token. Il sampler locale conserva i propri token firmati con scadenza. |
| 5.8 Capability admin | Non confermata una mancanza generalizzata; preservato e verificato | `marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php:112` e `marrison-addon/includes/modules/cookie-manager/includes/class-setup-wizard.php:200`: manage_options e nonce specifico. Impostazioni, ricevute, scanner e wizard conservano controlli di autorizzazione. Test positivi/negativi di form/AJAX superati. |

Il bucket anonimo del salvataggio ricevute è un HMAC dell’indirizzo di connessione, mai l’IP in chiaro. Questo rate limit è separato dal rilevamento passivo senza identificatori. La chiave delle ricevute preesistenti è conservata; non vengono riscritte prove storiche. L’hosting deve comunque proteggere la directory: un test isolato non prova nginx reale.

## Fase 6 — PHP Snippets e updater, 1.3.84

| Punto | Esito | Evidenza e intervento |
| --- | --- | --- |
| 6.1 Bypass anonimo | Confermato e corretto | `marrison-addon/includes/modules/php-snippets/class-php-snippets-runtime.php:93`: pagina di recupero solo su vero admin.php. AJAX/admin-post rinviati a init e bypass solo con login, entrambe le capability e nonce. Test con richiesta anonima page=marrison_addon_panel e action contraffatta: snippet eseguito regolarmente. |
| 6.2 HMAC e migrazione | Confermato e corretto | `marrison-addon/includes/modules/php-snippets/class-php-snippets-security.php:20`, `marrison-addon/includes/modules/php-snippets/class-php-snippets-runtime.php:122`, `marrison-addon/includes/modules/php-snippets/class-php-snippets-store.php:128`: firma di ID/codice/scope/revision; codice alterato o senza firma non eseguito, quarantena. Schema 2 aggiunge signature senza perdita di codice; Firma e riattiva è esplicita, con nonce/capability/riautenticazione. Nessuna firma automatica di dati DB. Salt o chiave mancanti: fallimento sicuro. |
| 6.3 Costanti WordPress | Confermato e corretto | `marrison-addon/includes/modules/php-snippets/class-php-snippets-security.php:31` e `marrison-addon/includes/modules/php-snippets/class-php-snippets-admin.php:74`: DISALLOW_FILE_EDIT/MODS bloccano editor e richieste di modifica; disattivazione e recupero restano disponibili, esecuzione firmata possibile. Test con entrambe le costanti. |
| 6.4 Autenticazione recente | Confermato e corretto | `marrison-addon/includes/modules/php-snippets/class-php-snippets-security.php:40`: WP_Session_Tokens, 15 minuti default (1–60 configurabili), verifica password alternativa, conferma legata alla sessione. Test di sessione vecchia, password errata/corretta, cambio token e sessione assente. |
| 6.5 Audit | Confermato e corretto | `marrison-addon/includes/modules/php-snippets/class-php-snippets-security.php:74`: utente/data/azione/hash/ID/esito senza codice, lock, massimo 2.000 eventi e retention 7–365 giorni (default 90), pulizia giornaliera e pannello ultimi 100 eventi. Non è un audit indipendente dal database. |
| 6.6 Sintassi e avvisi | Non confermata assenza del controllo sintattico; avvisi mancanti aggiunti | `marrison-addon/includes/modules/php-snippets/class-php-snippets-store.php:63` usava già TOKEN_PARSE. `marrison-addon/includes/modules/php-snippets/class-php-snippets-security.php:63` aggiunge avvisi per funzioni rischiose/scrittura plugin e base64 con esecuzione. CodeMirror mostra l’avviso prima del salvataggio; nessuna denylist assoluta e nessuna promessa di sicurezza. |
| 6.7 Recupero via file | Confermato e corretto | `marrison-addon/includes/modules/php-snippets/class-php-snippets-security.php:35`: costante esistente o file vuoto wp-content/uploads/marrison-snippets-off. Test di attivazione/rimozione del file. Il percorso è sotto WP_CONTENT_DIR; personalizzazioni uploads e Multisite richiedono verifica operativa. |
| 6.8 Limiti/UI/import | Confermato e corretto per limiti mancanti; import remoto non confermato perché assente | `marrison-addon/includes/modules/php-snippets/class-php-snippets-admin.php:74`: 20 tentativi/minuto per utente prima della verifica password; codice fino a 256 KiB. Nessun import/export da URL aggiunto. Test UI CodeMirror a 1280/390, nessun overflow, sincronizzazione form verificata. |
| 6.9 SHA-256 updater | Confermato e corretto per verifica integrità; autenticità indipendente da decidere | `marrison-addon/includes/class-marrison-addon-updater.php:184`: digest nel campo Marrison-Archive-SHA256 della release, verifica prima dell’offerta e di nuovo prima dell’installazione. Mancanza/differenza blocca e mostra avviso. URL GitHub esistente e cache versione di sei ore conservati. Chi controlla l’account GitHub potrebbe modificare sia archivio sia checksum: questo requisito non risolve tale compromissione. Una firma con chiave esterna richiede una decisione distinta. |

La firma non è una sandbox. I secret WordPress devono essere conservati nei backup; la loro rotazione invalida le firme, senza eliminare il codice. Sulle richieste AJAX/admin-post il nuovo punto di esecuzione è init: gli hook precedenti già trascorsi non vengono rieseguiti. Salvataggio e duplicazione restano inattivi.

## Test e limiti della prova

Tutti i controlli elencati sotto sono passati. Runtime PHP disponibile: 8.5; compatibilità sintattica scritta per PHP >=7.4, ma nessun interprete 7.4 eseguito.

- Lint PHP/Node sui file del task: 61 file nel controllo complessivo; successivi controlli mirati sui correttivi finali. git diff --check superato.
- PHP: cookie-engine (include local-engine/catalog-storage), static-scanner, service-catalog, security, consent-store, auto-scan, policy, expiration, observed-scan e consent.
- PHP con parsing/hook/menu WordPress reali ma senza un DB WordPress: cookie-shortcodes, cookie-update-notice, module-lifecycle e php-snippets-menu (normal/no-unfiltered/no-manage/disabled).
- Node: cookie-runtime, service-catalog, consent-mode, passive, receipts-ui, scan-agent e background-ui. I due test con aspettative dei vecchi timeout sono stati aggiornati dopo la verifica del comportamento effettivo, quindi rieseguiti con successo.
- PHP Snippets: 100 controlli CRUD/capability/nonce/ambiti/bootstrap/fatali/recupero, security, auth, updater-checksum e updater-layout. Manomissione di ogni campo firmato, firma assente, bypass anonimo e salvataggi concorrenti con revisioni coperti dalle fixture.
- Chrome/PHP su localhost: banner a 1280/390, accetta/rifiuta/scelta granulare/revoca, stati firmati scaduti/alterati ed errori salvataggio; COOKIE_BANNER_ONLY=1. Il vecchio percorso completo admin contiene selettori modificati dal lavoro preesistente Info Azienda, pertanto non viene dichiarato tutto superato.
- Chrome/CodeMirror WordPress e template PHP reale, in fixture isolata: editor, evidenziazione PHP senza tag, textarea/form sincronizzati, avviso non bloccante prima del salvataggio, layout desktop/mobile. Catalogo admin verificato in browser nella fase 1.

Non verificati: installazione e aggiornamento su un WordPress completo con MySQL/MariaDB reale, dbDelta su DB remoto, nginx/IIS reali, Multisite, flussi login/checkout/pagamenti e servizi provider effettivi, scansione iframe su un sito live con cache/WAF/estensioni browser. Nessun tag, release GitHub o installazione live pubblicati; ZIP preesistente non rigenerato.

Procedura di migrazione e pubblicazione checksum: [guida operativa](../marrison-addon/docs/cookie-hardening.md). Le versioni e i changelog di root README, plugin README, readme.txt e bootstrap sono aggiornati per ogni fase.

## File modificati

L’elenco completo, limitato ai commit di questa attività, è nel file [manifest dei file](cookie-hardening-files.txt). La baseline delle modifiche utente è rimasta separata durante lo staging.
