# Cookie Manager e PHP Snippets: aggiornamento per fasi

## Fase 2 — 1.3.80

La scansione dei servizi analizza localmente i contenuti pubblicati senza password, `_elementor_data`, script/stili registrati, widget, opzioni note e gateway abilitati. Usa batch di 20 ID con `WP_Query`, nessuna ricerca SQL LIKE non indicizzata, due secondi per batch e un limite di memoria. Il cursore è nel job già esistente: è possibile interrompere/riprendere; WP-Cron completa i batch avviati esplicitamente. Nessun contenuto viene trasmesso a un servizio remoto.

I cookie dei servizi entrano nello storage esistente con `source = catalogo`. `observed` è false (derivato dalla fonte nei record SQL, esplicito nei nuovi record JSON). Un catalogo non sovrascrive una vera osservazione e una scansione statica non cancella osservazioni precedenti. I cookie documentati restano distinti nell'admin e nella policy.

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
