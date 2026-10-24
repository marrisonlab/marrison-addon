# Cookie Manager: catalogo servizi (Fase 1, 1.3.79)

Il catalogo locale `includes/modules/cookie-manager/data/services.json` descrive dieci servizi: Google Analytics 4, Google Tag Manager, Meta Pixel, YouTube, Google Maps, WooCommerce, Stripe, WooPayments, PayPal e Klarna. Il caricamento non effettua richieste HTTP. Le fonti ufficiali sono collegamenti consultabili dall'amministratore, non servizi interrogati dal plugin.

Ogni scheda contiene identificativo stabile, nome, categoria, pattern host/percorso/espressione regolare, possibili cookie con dominio/durata/finalità, fornitore e informativa, fonte ufficiale, stato di verifica e data di revisione. Il documento è validato e memorizzato solo nella memoria della richiesta. Un JSON non valido viene rifiutato interamente e l'admin mostra l'errore.

## Configurazione

In **Cookie Manager > Comportamento > Catalogo servizi e classificazione** puoi scegliere Necessari, Funzionali, Analitici o Marketing per ogni servizio. Le categorie sono salvate nell'option aggiuntiva `marrison_cookie_service_categories`, senza rinominare le option esistenti. Il valore predefinito rimane nel catalogo. L'option non viene caricata automaticamente da WordPress quando il modulo è disabilitato.

Stripe, WooPayments, PayPal e Klarna hanno inizialmente categoria **Funzionali**: le risorse intercettate attendono quel consenso. La loro classificazione va confermata dal consulente privacy secondo l'integrazione e la pagina. Scegli **Necessari** soltanto dopo questa verifica. La categoria governa il blocco delle risorse del servizio; le categorie dei singoli cookie già osservati restano gestite dall'inventario esistente. Questa fase non modifica le impostazioni dei gateway WooCommerce né decide su quali pagine i gateway debbano caricarsi.

Le tre liste esistenti `marrison_cookie_functional_sources`, `marrison_cookie_analytics_sources` e `marrison_cookie_marketing_sources` estendono il riconoscimento. Inserisci un dominio o un percorso per riga: `statistiche.example.it`, `statistiche.example.it/tracking/` oppure `/tracking/`. Un dominio include i suoi sottodomini, con verifica del confine: `statistiche.example.it.evil.test` non corrisponde. Le regole personalizzate hanno precedenza sulla categoria del catalogo; elimina eventuali regole in conflitto prima di cambiare la classificazione di un pagamento. I cookie di un servizio personalizzato restano da verificare e non vengono inventati.

Un host esterno non riconosciuto è **Servizio non classificato** e resta bloccato nelle risorse intercettate anche dopo Accetta tutti. Quando compare nell'ultima scansione lo trovi qui e puoi classificarlo, anche come Necessario (per esempio un CDN tecnico verificato). Per un host ancora assente dal rapporto puoi già inserire una regola nelle liste esistenti. Il blocco riguarda script, iframe, immagini e chiamate JavaScript intercettate; non costituisce un filtro di rete completo per CSS, font, worker o codice eseguito prima del runtime. La verifica del blocco richiesta dalla Fase 3 resta separata. Dopo modifiche alle categorie ricarica le pagine e svuota eventuali cache HTML esterne.

## Dati e limiti

- **Dichiarato dal catalogo / non osservato** non significa cookie installato. Nei profili ogni cookie mantiene `observed = false` ed `evidence = provider_documentation`; non viene aggiunto alla tabella dei cookie osservati in questa fase.
- **Fonte verificata** significa che la fonte è stata consultata per la scheda; non certifica l'installazione sul sito. **Da verificare** segnala informazioni incomplete o condizioni d'uso non confermate. La data di revisione è inclusa nella scheda.
- GA4 dichiara solo `_ga` e `_ga_*`. `_gid` e `_gat` compaiono nell'inventario soltanto quando osservati. GTM non dichiara cookie propri; i tag del contenitore richiedono verifiche autonome.
- YouTube comprende gli embed su `youtube-nocookie.com`: non vengono attribuiti automaticamente cookie al dominio nocookie. La presenza di Maps non prova l'installazione di NID.
- Le durate Meta non confermate restano **Da verificare**. Per PayPal e Klarna il catalogo non deduce nomi o durate dalle liste di altri siti. Le chiavi local storage di Klarna non vengono presentate come cookie.
- Rimangono intatti cookie, ricevute, categorie manuali, impostazioni e rapporti precedenti. I nuovi profili arricchiscono il job esistente; i rapporti vecchi ricevono in visualizzazione i metadati di revisione disponibili. Una nuova scansione è necessaria per individuare i servizi aggiunti.
- Questa fase non cambia ancora il metodo di scansione: la dismissione di Commander/Browserless e lo scanner statico appartengono alla Fase 2. Nessun nuovo servizio remoto è stato introdotto.

Il plugin fornisce strumenti tecnici; la conformità resta responsabilità del titolare del sito.

## Verifiche riproducibili

Dal repository, con PHP e Node.js disponibili:

```text
php tests/cookie-service-catalog-php-test.php
node tests/cookie-service-catalog-js-test.js
php tests/cookie-engine-php-test.php
php tests/cookie-consent-php-test.php
php tests/cookie-policy-php-test.php
node tests/cookie-runtime-js-test.js
node tests/cookie-service-catalog-browser-test.js
```

Il primo test non usa WordPress o la rete: verifica schema/JSON danneggiati, dieci servizi, confini host, GA4 senza cookie legacy, dichiarazioni non osservate, regole custom, categorie dei pagamenti, host sconosciuti e salvataggio sanitizzato. Il test JavaScript usa la configurazione generata dal vero loader PHP per verificare gli stessi percorsi nel runtime. I test esistenti controllano la separazione dagli osservati, lo storage DB/JSON, i consensi, l'escaping e il ciclo dello scanner.

Per la fixture HTML admin: `php tests/cookie-service-catalog-php-test.php --admin-html`. Il test browser richiede Playwright (`NODE_PATH`) e Chromium; puoi indicare un browser già installato con `BROWSER_EXECUTABLE`. La fixture non dimostra il comportamento su un sito WordPress reale. Firma snippet, bypass anonimo e sicurezza dell'updater appartengono alla Fase 6 e non sono stati modificati qui.
