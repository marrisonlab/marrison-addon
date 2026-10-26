# Cookie Manager e PHP Snippets: aggiornamento per fasi

## Fase 2 — 1.3.80

La scansione dei servizi analizza localmente i contenuti pubblicati senza password, `_elementor_data`, script/stili registrati, widget, opzioni note e gateway abilitati. Usa batch di 20 ID con `WP_Query`, nessuna ricerca SQL LIKE non indicizzata, due secondi per batch e un limite di memoria. Il cursore è nel job già esistente: è possibile interrompere/riprendere; WP-Cron completa i batch avviati esplicitamente. Nessun contenuto viene trasmesso a un servizio remoto.

I cookie dei servizi entrano nello storage esistente con `source = catalogo`. `observed` è false (derivato dalla fonte nei record SQL, esplicito nei nuovi record JSON). All’interno dello stesso job un catalogo non sovrascrive una vera osservazione. Da 1.3.85, su richiesta esplicita, ogni nuova scansione cancella l’inventario precedente e conserva soltanto i risultati cumulativi del nuovo job, anche se parziale. I cookie documentati restano distinti nell'admin e nella policy.

L'iframe è opzionale, spento per default, e usa soltanto i campioni del sampler (default 3, massimo 10). Il tempo totale è configurabile tra 10 e 120 secondi, default 30; caricamento 8 secondi, agente 4 secondi. La verifica può contattare i servizi incorporati ma non vede i cookie di terze parti; non sostituisce prove su login, checkout o interazioni reali. Le rilevazioni statiche indicano una presenza potenziale, non l'effettiva esecuzione della risorsa.

Le vecchie impostazioni del metodo remoto sono trattate come locale. Un avviso amministrativo una tantum spiega la migrazione. La vecchia implementazione rimane deprecata e non raggiungibile; la UI non offre più la scelta remota.

**Rilevamento passivo:** l'option `marrison_cookie_passive_detection` è false per default. Se abilitata in Comportamento, il runtime segnala soltanto gli host bloccati (mai URL, parametri, IP salvati, valori cookie o identificatori del visitatore). Massimo 20 host per richiesta, 100 distinti salvati, 500 richieste al giorno, nonce e controllo origine. Gli host sono deduplicati e classificabili nello stesso catalogo. Questa funzione va descritta nell'informativa. I normali log del server sono gestiti dall'hosting separatamente.

Nuove opzioni: `marrison_cookie_local_iframe` e `marrison_cookie_local_max_seconds`; non rinominano impostazioni precedenti. Il rilevamento statico delle opzioni copre una lista esplicita di integrazioni comuni, non ogni plugin possibile. Percorsi personalizzati e configurazioni costruite dinamicamente richiedono regole amministrative e verifica manuale.

Verifiche: `php tests/cookie-static-scanner-php-test.php`, `php tests/cookie-local-engine-php-test.php`, `php tests/cookie-service-catalog-php-test.php`, `node tests/cookie-service-catalog-js-test.js`. Il vecchio test di orchestrazione remota non descrive più il percorso operativo. Gli altri test di osservazioni, consensi e policy restano applicabili.

## Fase 3 — 1.3.81

Il runtime inserisce i quattro default Google denied e wait_for_update prima dei tag, senza una richiesta di rete. Dopo la verifica della scelta aggiorna analytics_storage dalla categoria Analitici e ad_storage/ad_user_data/ad_personalization da Marketing. GA4 e GTM rimangono bloccati in modalità base; Meta attende Marketing e riceve consent/revoke alla revoca. La modalità avanzata è un opt-in amministrativo, spento per default, che permette ai tag Google di caricarsi con default denied: può inviare ping senza cookie e richiede valutazione legale e verifica dei tag nel contenitore.

Gli iframe YouTube bloccati mostrano un pulsante testuale: un clic autorizza soltanto quel video per la pagina corrente, senza abilitare la categoria Marketing o altri video. Il caricamento usa youtube-nocookie.com, non scarica miniature prima del clic e viene neutralizzato a un nuovo cambio di scelta. Il consenso per contenuto è in memoria e non viene presentato come ricevuta persistente. Le risorse generate dinamicamente vengono intercettate dal runtime e le anteprime sono aggiornate sul DOM.

Stripe/PayPal/Klarna classificati Necessari non sono neutralizzati dal blocco. L’addon non cambia dove i gateway decidono di caricare gli script: per evitare caricamenti superflui su prodotto/carrello occorre configurare e verificare il gateway stesso. Checkout reale, impostazioni GTM personalizzate e integrazioni non standard restano da verificare.

Fonti: [Google Consent Mode base](https://developers.google.com/tag-platform/security/guides/consent?consentmode=basic), [panoramica base/avanzata](https://developers.google.com/tag-platform/security/concepts/consent-mode). Test: node tests/cookie-consent-mode-js-test.js, oltre ai test catalogo/runtime PHP e Node.

## Fase 4 — 1.3.82

Accetta e Rifiuta mantengono gli stessi stili ed evidenza. I controlli granulari opzionali non sono preselezionati; lo scroll non registra consenso. Il link Cookie Policy usa la pagina configurata dal titolare e deve essere compilato. Widget flottante e shortcode mantengono l’accesso alle preferenze e alla revoca. Default e minimo del consenso: 180 giorni; massimo 365 giorni. Le revisioni sostanziali dell’informativa richiedono una nuova scelta.

Dopo una scansione statica completata, se non risultano servizi o cookie non necessari, host non classificati o regole opzionali, il banner si disattiva automaticamente. L’admin vede Solo cookie tecnici rilevati; non viene caricata la UI su pagine senza necessità di preferenze. Non è una certificazione dell’assenza di servizi nelle aree che la scansione non vede. Le pagine con shortcode conservano l’accesso alle preferenze.

In Comportamento è presente una checklist facoltativa su aree riservate/checkout, informativa, servizi manuali e revisione legale, con disclaimer sulla responsabilità del titolare. Nessuna casella è necessaria per salvare o pubblicare. Fonte di riferimento: [FAQ Cookie del Garante](https://www.garanteprivacy.it/faq/Cookie). Il test PHP/Chrome del banner è separabile con COOKIE_BANNER_ONLY=1; l’intera vecchia suite admin include selettori modificati dal lavoro preesistente su Info Azienda.

## Fase 5 — 1.3.83

Aggiunte le guardie ABSPATH mancanti nei PHP del modulo e nei template. Salvataggio consenso, preferenze, stato e lista cookie usano nonce e origine. Il rinnovo nonce non può richiedere un nonce ancora valido: è un bootstrap protetto da origine e limite globale. Non dà accesso alle ricevute. Le route scan-proof e worker remoto non vengono più registrate; i vecchi metodi rifiutano l’uso.

I salvataggi delle ricevute hanno un transient per visitatore e contatori su file con lock: massimo 30 richieste ogni dieci minuti e 10.000 ricevute globali al giorno. Il bucket anonimo usa un HMAC temporaneo dell’indirizzo di connessione; nessun IP in chiaro o user-agent viene salvato. Questa protezione è distinta dal rilevamento passivo, che non usa identificatori dei visitatori. I file hanno nomi HMAC non indovinabili, prefisso PHP exit, index.php e protezioni del server. Le ricevute precedenti vengono rinominate conservando contenuto e firme; la chiave delle ricevute resta quella salvata nell’option esistente. Limiti: 4 MiB giornalieri, 64 MiB e 2.000 file complessivi; a capienza raggiunta nessun consenso viene dichiarato salvato. La retention giornaliera elimina i dati scaduti, senza abbreviare automaticamente la retention scelta dal titolare.

Site Health verifica con un file di prova temporaneo se il server risponde 200: in quel caso segnala un archivio accessibile. 401/403/404 sono letti come diniego; errori e redirect restano da verificare. Configurare la regola nginx riportata nel README, adattata al percorso reale. Un test fixture non prova la configurazione di un hosting reale.

La revisione del consenso include categorie dei servizi e modalità Google, così una modifica sostanziale delle scelte richiede nuova verifica. Output cookie e descrizioni sono sottoposti a escaping; la lista HTML nelle preferenze viene ricostruita con un piccolo insieme di elementi e testo, senza attributi eseguibili. I sink usati soltanto per leggere testo già escapato o intercettare il DOM non erano vulnerabilità confermate e sono rimasti. Le query di statistiche e la verifica tabella usano prepare; nomi tabella derivano dal prefisso WordPress e le liste IN usano segnaposto.

Test aggiuntivo: php tests/cookie-security-php-test.php (migrazione file, 200/403/errore rete, rimozione probe, quote e limiti). Nessun nginx o sito remoto è stato modificato.

## Fase 6 — 1.3.84

### PHP Snippets

Lo schema aggiunge soltanto signature, senza riscrivere il codice. Gli snippet senza firma o con firma non valida non vengono eseguiti; quelli attivi vengono sospesi con un messaggio. La pagina distingue Attivo da Da firmare: non eseguibile. Apri il codice di ogni snippet preesistente, verificalo e usa Firma e riattiva: l’azione richiede entrambe le capability, nonce per operazione e autenticazione recente. Salvataggi e duplicazioni restano inattivi. Non esiste una firma automatica del codice trovato nel database.

La firma HMAC lega codice, ambito, revisione e ID. La chiave deriva dai secret/salt WordPress definiti in wp-config.php, disponibili già al bootstrap precoce; può includere anche MARRISON_ADDON_SNIPPETS_KEY (almeno 32 caratteri). In assenza di secret validi, il plugin rifiuta di firmare ed eseguire. Conserva i secret con i backup: ruotarli invalida le firme, senza eliminare gli snippet, e richiede nuova revisione e firma amministrativa. La firma non è una sandbox e non rende sicuro il PHP scelto dall’amministratore.

Il bypass delle pagine di recupero è limitato al vero script admin.php. admin-ajax.php e admin-post.php rinviano la decisione a init: utente autenticato, manage_options + unfiltered_html e nonce specifico sono necessari. Parametri page/action anonimi non disattivano più gli snippet. Sulle richieste normali il bootstrap resta prima di plugins_loaded; sulle richieste AJAX/admin-post gli snippet partono a init, dopo l’autenticazione, quindi gli hook già trascorsi non vengono rieseguiti.

Salvataggio, attivazione, firma e duplicazione richiedono una sessione autenticata negli ultimi 15 minuti, leggendo login da WP_Session_Tokens. In alternativa si verifica di nuovo la password, con conferma legata soltanto alla sessione corrente. Password non salvate o registrate. MARRISON_ADDON_SNIPPETS_REAUTH_MINUTES può impostare da 1 a 60 minuti. Limite di 20 tentativi/modifiche al minuto per utente, anche per tentativi di riautenticazione non riusciti; codice nuovo fino a 256 KiB. Non sono presenti import/export da URL remoti.

DISALLOW_FILE_EDIT o DISALLOW_FILE_MODS rendono l’editor e i comandi di modifica in sola lettura e bloccano i POST corrispondenti; gli snippet già firmati possono continuare a funzionare. Disattivazione e recupero restano disponibili. Il tokenizer controlla la sintassi; funzioni come eval/exec/system producono un avviso non bloccante, visibile anche durante la scrittura in CodeMirror. L’avviso non garantisce sicurezza e non tenta di sostituire una revisione del codice.

Il registro amministrativo conserva utente, data, azione, ID, hash SHA-256 del codice ed esito, mai il codice completo. Retention configurabile tra 7 e 365 giorni (default 90), massimo 2.000 eventi, ultimi 100 visibili. WP-Cron pulisce giornalmente quando il modulo è attivo; il job viene rimosso alla disattivazione. Gli aggiornamenti del registro hanno un lock con scadenza. Non è un registro indipendente dal database o a prova di manomissione.

Recupero: definisci MARRISON_ADDON_PHP_SNIPPETS_SAFE_MODE=true in wp-config.php oppure crea il file vuoto wp-content/uploads/marrison-snippets-off. Rimuovi il flag per ripristinare gli snippet validamente firmati. Il flag usa il percorso standard sotto WP_CONTENT_DIR, condiviso nei casi Multisite; percorsi uploads diversi non vengono dedotti dal frontend.

### Updater

Una release deve pubblicare nel corpo una riga nel formato esatto:

```text
Marrison-Archive-SHA256: <64 cifre esadecimali del pacchetto>
```

Il digest deve corrispondere ai byte scaricati dall’URL GitHub archive/refs/tags/vVERSION.zip già usato dall’updater, non allo ZIP allegato della sola directory plugin. Dopo il tag, scarica quell’archivio, calcola SHA-256 e pubblica il valore nella release. URL e cache versione di sei ore rimangono invariati. Anche metadati checksum ed esito del primo controllo hanno durata di sei ore. Un aggiornamento senza digest o con pacchetto differente non viene offerto e compare un avviso in admin; il pacchetto viene ricontrollato prima dell’installazione. I file temporanei verificati vengono eliminati dal controllo dell’offerta; il normale flusso WordPress gestisce quelli destinati all’installazione.

Un checksum pubblicato nello stesso account GitHub protegge l’integrità dei byte, non fornisce autenticità indipendente: chi compromette l’account potrebbe sostituire sia pacchetto sia checksum. Una firma con chiave esterna alla release sarebbe un ulteriore progetto; non viene dichiarata implementata qui. Nessuna release, tag o aggiornamento live è stato pubblicato da questa attività.

Test: php tests/php-snippets-security-test.php; php tests/php-snippets-auth-test.php; php tests/php-snippets-php-test.php; php tests/updater-checksum-test.php; php tests/updater-layout-test.php. Il test browser PHP Snippets usa il vero template e CodeMirror WordPress a 1280/390 px, verifica sincronizzazione del form e avviso prima del salvataggio; resta una fixture isolata.

## Correzione 1.3.85 — inventario nuovo e rapporti precedenti

I rapporti Commander o appartenenti al vecchio metodo sono indicati come precedenti, senza ripresa o avanzamento automatico. Il vecchio avviso sulle navigazioni fuori sito non viene attribuito al nuovo scanner locale. I dettagli del rapporto restano nel file fino alla prossima scansione, che lo sostituisce.

Ogni avvio di una nuova scansione svuota l’inventario cookie precedente, sia su tabella SQL sia nel fallback JSON, dopo avere conservato le categorie amministrative. Ogni batch salva soltanto i risultati cumulativi del job corrente: un esito parziale mostra i dati trovati fino a quel punto; un esito vuoto non recupera cookie precedenti. Cookie del catalogo e osservazioni del nuovo job mantengono etichette distinte. Ricevute di consenso e impostazioni non vengono cancellate. I file di ricevuta delle informative storiche conservano le prove originarie.

Test: cookie-engine-php-test.php, cookie-local-engine-php-test.php, cookie-catalog-storage-php-test.php e cookie-background-ui-js-test.js. Verificati reset SQL/JSON, osservazioni precedenti rimosse, risultati parziali cumulativi, scansione vuota e rifiuto della ripresa di un job remoto. La frase segnalata è generata dal vecchio worker in audit/2026-10-21/cookie-background/commander/app/Services/cookie-scan.js; il sito segnalato non è stato verificabile da questo ambiente per errore di risoluzione DNS.

## Correzione 1.3.86 — contatori e comandi browser

Il riepilogo distingue i contenuti analizzati dagli URL campione della verifica iframe. Con verifica browser disattivata non vengono selezionati campioni o generate avvertenze sul loro limite; il rapporto espone zero verifiche previste e mostra esplicitamente verifica browser disattivata. La UI non presenta zero cookie osservati come se fosse stata eseguita un’osservazione: spiega che i cookie effettivamente installati non sono stati verificati. Le dichiarazioni di catalogo rimangono separate.

Anche i job statici già salvati prima dell’aggiornamento sono visualizzati con contatori coerenti: campioni e vecchio avviso sul relativo limite non sono attribuiti alla scansione statica. Con iframe abilitato rimangono conteggi, campioni e avvisi effettivi. Non cambiano il formato del job salvato o il protocollo; i nuovi campi static_posts e local_iframe sono metadati della risposta amministrativa.

Nel rapporto fornito dal titolare, le quattro dichiarazioni provengono dal profilo WooCommerce, mentre il report conferma zero richieste remote e nessuna navigazione browser. Il numero dei cookie documentati non dimostra quanti cookie siano installati sul sito. Test PHP del motore e test Node del riepilogo coprono entrambe le modalità e la visualizzazione dei job preesistenti.

Nello Scanner e nel passaggio Cookie del wizard sono presenti due comandi diretti: Avvia analisi statica e Analisi statica + verifica browser. Il secondo avvia un nuovo job con iframe esplicitamente richiesto, limite campioni e tempo massimo scelti nella stessa schermata. La preferenza persistente per le scansioni automatiche non viene cambiata; il comando statico manuale esclude sempre il browser. Prima di creare un job con iframe, il client verifica il supporto credentialless; se assente spiega il requisito Chrome/Edge e non avvia nulla. I comandi restano disabilitati durante il lavoro. Il pannello deve restare aperto durante la verifica browser. Ogni nuovo avvio sostituisce l’inventario precedente, come richiesto dal titolare.

Verifica UI: cookie-scanner-controls-browser-test.js esegue le sezioni PHP reali dei template con jQuery e coordinator reali in Chrome a 1280/390 px, controlla visibilità dei comandi, invio dei parametri scelti e nessun avvio all’apertura. AJAX è isolato; non viene dichiarato un test completo del browser sul sito live.

## Correzione 1.3.87 — consenso completo e widget preesistenti

Accetta tutti e una scelta personalizzata con Funzionali, Analitici e Marketing autorizzano anche le risorse intercettate marcate unclassified. Prima dello stato valido verificato, con scelta parziale, rifiuto o consenso scaduto, queste risorse restano bloccate. Il runtime e il ripristino del frontend condividono lo stesso controllo; le categorie già riconosciute, comprese le mappe, mantengono il comportamento precedente. Non vengono modificati classificazioni salvate, firme/ricevute o filtro PHP preventivo compatibile con cache. Questa richiesta dell’utente sostituisce la precedente regola che lasciava gli host non classificati bloccati anche dopo consenso completo.

L’installazione del widget prima del Cookie Manager non richiede reinstallazioni. Il caso verificato è Widgets for Google Reviews di Trustindex (wp-reviews-plugin-for-google): il loader esterno era neutralizzato e il widget restava nascosto in attesa di inizializzazione. La correzione riattiva il loader dopo consenso completo e lascia che il plugin formatti il proprio widget, incluso il caricamento lazy quando il visitatore raggiunge la sezione. Non forza l’altezza o la visibilità del widget e non inserisce cookie inventati nel catalogo.

Verifica del 26 ottobre 2026: test Node del catalogo/runtime su consenso assente, completo, parziale, scaduto e non valido; regressioni Consent Mode, Meta, Maps e contenuto singolo YouTube. Test Chrome facoltativo cookie-reviews-live-browser-test.js sulla pagina fornita dal titolare: versione originale ancora bloccata dopo Accetta tutti; asset candidati sostituiti esclusivamente nel browser di prova; widget Trustindex reale visibile dopo Accetta tutti, ricaricamento con scelta persistente e Personalizza con tutte le categorie; blocco prima della scelta, dopo scelta parziale e rifiuto. Nessun file o impostazione amministrativa del sito è stato modificato. Il test usa normali scelte di consenso visitatore, con le relative ricevute. Test opzionale: node tests/cookie-reviews-live-browser-test.js URL_HTTPS [directory_screenshot].

Fonti primarie verificate: [plugin Trustindex](https://it.wordpress.org/plugins/wp-reviews-plugin-for-google/) e [caricatore ufficiale](https://cdn.trustindex.io/loader.js). Rimangono da verificare integrazioni diverse, richieste server-side e percorsi che il runtime non intercetta; il consenso completo non è una certificazione di conformità.
