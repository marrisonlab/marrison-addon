# Audit Marrison Addon — 19 ottobre 2026

Sito: [ambiente di test](https://lightskyblue-ape-529924.hostingersite.com/). Versione finale verificata: **1.3.66**. Revisione del codice locale e verifiche nel browser autenticato.

**Aggiornamento 20 ottobre:** questo documento conserva la fotografia dell'audit precedente agli interventi. Le correzioni della 1.3.67 e le relative verifiche sono riportate in [IMPLEMENTAZIONE.md](../2026-10-20/IMPLEMENTAZIONE.md). Il 404 Privacy Policy è intenzionale nell'ambiente di test, come chiarito dal proprietario, ed è escluso dai difetti e dagli interventi.

## Esito

Il caricamento dei moduli rispetta il principio fondamentale nelle prove eseguite sulla richiesta successiva alla disattivazione: **le fixture locali con tutti i 24 moduli OFF caricano zero file PHP e zero classi dei moduli; la homepage remota con tutti i 24 moduli OFF non carica CSS/JS Marrison**. Il costo minimo del registro e del bootstrap del plugin rimane naturalmente presente.

Il punto da completare è la disattivazione degli **effetti persistenti**: CSS Elementor già generati, regole del server e attività pianificate devono essere gestiti con lo stesso rigore del caricamento PHP. Sono emersi difetti in questi percorsi, oltre a problemi funzionali descritti sotto.

Il rilievo più urgente riguarda il Cookie Manager: il runtime cancella cookie facoltativi preesistenti prima di verificare un consenso che potrebbe essere ancora valido. Seguono il lifecycle di disattivazione, la conservazione delle categorie cookie e la conversione delle date degli eventi.

**Non sono stati applicati fix ai sorgenti.** Le modifiche locali già presenti sono state preservate. I 24 moduli sono stati ripristinati attivi, come all'inizio della verifica.

## Ambiente e attendibilità delle prove

| Componente | Versione/stato osservato |
|---|---|
| Marrison Addon remoto | 1.3.64 all'inizio; aggiornato dall'utente e ricontrollato alla 1.3.66 |
| Marrison Addon locale | 1.3.66, con modifiche preesistenti non committate |
| WordPress | 7.1.3, come dichiarato dalla bacheca del sito |
| Elementor | 4.3.5 |
| JetEngine | 3.9.0 |
| WooCommerce | 11.2.1 |
| Server rilevato dal modulo cache | LiteSpeed, gestione tramite .htaccess |
| AVIF | Test del modulo riuscito: WP_Image_Editor e GD disponibili, trasparenza verificata |
| FFmpeg | Il server non consente l'esecuzione: generazione cover locali non disponibile |
| Stato moduli iniziale/finale | 24 attivi / 24 attivi |

Le prove sono classificate così:

- **LIVE:** comportamento o risposta verificati sul sito.
- **LOCALE:** esecuzione di test/fixture contro il codice corrente, con API simulate dove indicato.
- **CODICE:** percorso verificato nel sorgente, senza riproduzione completa sul sito.
- **PROPOSTA:** miglioramento o verifica da programmare; non è un bug dimostrato.

Le verifiche locali non certificano un'esecuzione equivalente su ogni browser, tema o configurazione del sito. Gli SHA256 di scanner ed engine esposti dalla diagnostica remota corrispondono ai rispettivi file locali; la stessa versione dichiarata non dimostra la parità degli altri file del pacchetto.

## Interventi ordinati per priorità

P1 = alta, da affrontare prima di usare la funzione interessata su siti con dati persistenti. P2 = correzione funzionale o completamento del contratto di disattivazione. P3 = miglioramento o approfondimento. L'ordine all'interno di ciascuna fascia costituisce l'ordine operativo suggerito.

| Ordine | Priorità | ID | Modulo/area | Intervento | Prova |
|---:|---|---|---|---|---|
| 1 | P1 | R01 | Cookie Manager | Non cancellare cookie già consentiti durante la verifica iniziale ancora pendente | LOCALE + CODICE |
| 2 | P2 | R02 | Core / tutti i moduli | Applicare cleanup e invalidazioni anche al salvataggio cumulativo dell'option | LOCALE |
| 3 | P2 | R03 | Browser Cache / Cookie Manager / Sconto Prodotto | Pulire effetti propri alla disattivazione dell'intero plugin | CODICE |
| 4 | P2 | R04 | Dimensioni Immagini | Invalidare il CSS Elementor anche al toggle ON/OFF del modulo | LOCALE + CODICE |
| 5 | P2 | R05 | Cookie Manager | Conservare inventario e classificazioni manuali oltre i 30 giorni | LOCALE |
| 6 | P2 | R06 | Calendar Sync | Interpretare le date senza offset nel fuso WordPress | LOCALE |
| 7 | P2 | R07 | Anchor Offset | Rispettare target e download dei link | LOCALE |
| 8 | P2 | R08 | Preloader | Evitare un overlay permanente se il suo JavaScript non parte | CODICE |
| 9 | P2 | R09 | Cookie Manager | Rendere accessibile da tastiera e screen reader la finestra preferenze | LIVE + CODICE |
| 10 | P2 | R10 | Dimensioni Immagini | Impedire slug duplicati o coincidenti con dimensioni esistenti | CODICE |
| 11 | P2 | R11 | Leggi di più | Caricare gli asset soltanto quando la funzione è usata nella pagina | LIVE + CODICE |
| 13 | P2, configurazione | S02 | Browser Cache / hosting | Individuare perché WooCommerce.woff non riceve Cache-Control | LIVE |
| 14 | P3 | M01 | Preloader | Gestire il ritorno dalla cache di navigazione con pageshow | LOCALE, browser reale da verificare |
| 15 | P3 | M02 | Cursore Animato | Sospendere il ciclo di animazione quando il cursore è invisibile o fermo | LOCALE |
| 16 | P3 | M03 | Local Google Fonts | Separare font osservati nel frontend da candidati trovati nei sorgenti | LIVE + PROPOSTA |
| 17 | P3 | M04 | Video Thumbnail | Vincolare l'importazione agli URL delle miniature del video selezionato | CODICE |
| 18 | P3 | M05 | Ticker | Condividere il CSS statico anziché ripeterlo per ogni istanza | CODICE |
| 19 | P3 | M06 | Sconto Prodotto | Misurare e, se necessario, differire code di sincronizzazione molto grandi | PROPOSTA |
| 20 | P3 | M07 | Dynamic SVG | Valutare un limite di complessità del DOM SVG oltre al limite in byte | PROPOSTA |

## Dettaglio dei difetti e criteri di accettazione

### R01 — Cancellazione prima della verifica del consenso

**File:** [consent-runtime.js:9](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/cookie-manager/assets/js/consent-runtime.js:9), stesso file alle righe 123–143; [frontend.js:131](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/cookie-manager/assets/js/frontend.js:131).

Il runtime parte con `verifiedState = null`. In questo stato ammette solo la categoria necessaria ed esegue immediatamente `enforce()`, che scade i cookie leggibili da JavaScript delle altre categorie. La verifica AJAX del consenso arriva dopo.

La fixture Node ha caricato il sorgente reale con un cookie `_ga` preesistente: sono state emesse cancellazioni `Max-Age=0` prima della verifica. Una successiva risposta positiva, con categoria analytics ammessa, non può ricostruire l'identificativo cancellato. Il caso vale anche per preferenze funzionali; la sessione WooCommerce controllata nella fixture è stata preservata.

**Impatto:** perdita di preferenze e frammentazione di identificativi/sessioni Analytics durante la navigazione di visitatori che avevano già accettato. Non è una dimostrazione di tracciamento senza consenso.

**Intervento minimo:** mantenere le risorse facoltative bloccate nello stato pendente, ma rinviare l'eliminazione dei cookie finché è noto uno stato negativo, scaduto o revocato. Definire esplicitamente il comportamento in caso di verifica fallita.

**Accettazione:** con consenso firmato valido un cookie facoltativo mantiene il valore dopo una nuova navigazione; con rifiuto/revoca viene eliminato; durante la verifica nessun nuovo tracker è avviato. Ripetere sul sito con un'integrazione reale: questa riproduzione è locale.

### R02 — Salvataggio cumulativo senza lifecycle

**File:** [class-marrison-addon-admin.php:109](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/admin/class-marrison-addon-admin.php:109), righe 109–132.

Il ramo AJAX con `key` esegue la pulizia Browser Cache, svuota le cache del registro e invia `marrison_addon/module_status_changed`. Il ramo senza `key` aggiorna direttamente l'intero array e termina.

La fixture autorizzata ha salvato quattro moduli OFF: risposta di successo, option aggiornata, nessun evento di cambio stato. Nella stessa richiesta la cache del registro conservava ancora il vecchio valore. Anche gli aggiornamenti dell'option attraverso Settings API non hanno un osservatore centrale equivalente.

**Impatto:** un percorso di salvataggio supportato può lasciare cron, regole server o cache del modulo dopo OFF. La UI ordinaria usa il ramo per singola chiave: non è stata osservata questa anomalia nel suo normale toggle.

**Intervento minimo:** un unico gestore delle differenze vecchio/nuovo stato dell'option. Eseguire una sola volta cleanup e invalidazioni per ciascun modulo cambiato, conservando controlli di capability e gestione degli errori.

**Accettazione:** toggle singolo, salvataggio cumulativo e Settings API producono lo stesso stato finale e gli stessi cleanup. Un errore di rimozione non deve essere presentato come OFF completato.

### R03 — Disattivazione dell'intero plugin

**File:** [marrison-addon.php](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/marrison-addon.php), [class-browser-cache-server.php:334](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/browser-cache/class-browser-cache-server.php:334), [class-marrison-addon-cookie-manager.php:243](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-cookie-manager.php:243).

Non è registrato un `register_deactivation_hook`. I cleanup presenti sono collegati ai toggle dei singoli moduli. Disattivare Addon dalla pagina Plugin non percorre quei toggle.

**Impatto:** le regole .htaccess già scritte continuano a influenzare le risposte statiche; gli eventi cron propri possono restare nel pianificatore. L'assenza dei callback impedisce l'esecuzione del lavoro del modulo, ma non rimuove gli eventi e i loro eventuali risvegli.

**Intervento minimo:** cleanup di disattivazione limitato a blocco server gestito e schedulazioni proprie, compresi gli eventuali job Action Scheduler. Conservare dati, media, snippet e impostazioni.

**Accettazione:** controllare lo stato del server e del pianificatore dopo disattivazione e successiva riattivazione. Questo caso è stato verificato nel codice, senza disattivare l'intero plugin remoto.

### R04 — Riscritture Image Sizes nei CSS generati

**File:** [class-marrison-addon-image-sizes.php:1513](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-image-sizes.php:1513), [class-marrison-addon-admin.php:120](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/admin/class-marrison-addon-admin.php:120).

Image Sizes modifica gli URL delle immagini prima che Elementor scriva i CSS statici. Il toggle centrale invalida i CSS solo per Dynamic Background Image Size.

**Prova locale:** toggle Image Sizes OFF riuscito con zero chiamate a `clear_cache()`; toggle Dynamic Background OFF con una chiamata.

**Impatto:** il PHP di Image Sizes cessa di caricarsi, ma i CSS preesistenti possono continuare a usare gli URL riscritti. È persistenza di un risultato generato; non prova di asset PHP/JS ancora eseguiti da OFF.

**Intervento minimo:** invalidare file/dati CSS Elementor al cambio ON/OFF di Image Sizes, conservando le varianti immagine già generate.

**Accettazione:** generare prima un CSS con sostituzione moderna; spegnere soltanto Image Sizes e verificare la rigenerazione coerente. La prova remota con tutti OFF ha incluso anche Dynamic Background, quindi non isola questo specifico difetto.

### R05 — Scadenza implicita dell'inventario cookie

**File:** [class-cookie-scanner.php:247](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/cookie-manager/includes/class-cookie-scanner.php:247), stesso file alle righe 904–908 e 1075–1077.

La lettura SQL esclude osservazioni più vecchie di 30 giorni. All'avvio del motore vengono eliminate prima che le categorie siano conservate. Il fallback JSON non applica la stessa scadenza.

**Prova locale:** record osservato 31 giorni prima, categoria manuale `necessary`: un record iniziale, zero record restituiti all'inventario, categorie salvate vuote dopo la preparazione del motore.

**Impatto:** con scansione automatica OFF, o cron ritardato, la policy può perdere l'inventario e una nuova scansione può perdere classificazioni manuali.

**Intervento minimo:** separare anzianità dell'osservazione e classificazione amministrativa; indicare dati obsoleti senza cancellarli soltanto per età. Preservare le categorie prima della pulizia e uniformare DB/JSON.

**Accettazione:** oltre 30 giorni le categorie restano recuperabili; una scansione parziale non cancella conoscenza precedente per sola anzianità.

### R06 — Date locali interpretate come UTC

**File:** [class-marrison-addon-calendar-sync.php:311](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-calendar-sync.php:311), in particolare riga 326.

`strtotime()` interpreta le stringhe prive di offset nel fuso PHP. La successiva formattazione usa il fuso WordPress: i due passaggi non hanno la stessa semantica.

**Prova locale:** PHP UTC, WordPress Europe/Rome, meta `2026-10-19 10:00:00`: ICS alle 12:00 anziché alle 10:00. A dicembre lo scarto è un'ora. ISO con offset esplicito funziona. Anche il link Google usa il timestamp errato.

**Intervento minimo:** interpretare le stringhe locali usando `wp_timezone()`, preservando timestamp numerici e offset espliciti.

**Accettazione:** verificare ora legale/solare, timestamp in secondi/millisecondi e ISO con offset. Sul sito non è stato creato un evento di prova: il difetto dipende dal formato dei meta utilizzati.

### R07 — Anchor Offset annulla target/download

**File:** [marrison-anchor-offset.js:95](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/js/marrison-anchor-offset.js:95), righe 95–128.

Il gestore di un anchor della stessa pagina controlla modificatori e pulsante, ma non `target` o `download`. Esegue `preventDefault()`, scroll e `pushState()` anche nei due casi.

**Prova locale:** link `#target` con `target="_blank"` e link con `download` entrambi intercettati.

**Intervento minimo:** escludere i link che devono conservare navigazione/download nativi, analogamente alle esclusioni già presenti nel Preloader.

**Accettazione:** anchor normale corretto con header; nuova scheda, download e modificatori mantengono il comportamento nativo. Nessun doppio offset è stato dimostrato.

### R08 — Preloader dipendente dal proprio JS anche per il recupero

**File:** [class-marrison-addon-preloader.php:98](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-preloader.php:98), [marrison-preloader.css:9](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/css/marrison-preloader.css:9), [marrison-preloader.js:77](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/js/marrison-preloader.js:77).

L'overlay è visibile per default, fisso a tutto schermo e sopra il contenuto. La classe che lo nasconde viene aggiunta soltanto dal JS; anche il timeout di recupero a otto secondi è nello stesso file.

**Impatto condizionato:** se quel JS non viene scaricato o eseguito, il contenuto resta coperto. Il timeout attuale gestisce il caricamento lento della pagina soltanto se lo script è già partito.

**Intervento minimo:** attivazione progressiva o uscita di sicurezza indipendente dal JS principale.

**Accettazione:** la pagina resta utilizzabile con JS disabilitato o file preloader non disponibile, oltre al normale scenario di caricamento lento. Il guasto di rete del file non è stato simulato sul sito.

### R09 — Finestra cookie senza gestione del focus

**File:** [cookie-banner.php:53](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/cookie-manager/templates/cookie-banner.php:53), [frontend.js](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/cookie-manager/assets/js/frontend.js).

**Prova LIVE:** dopo Personalizza il focus rimane sul pulsante dietro la finestra; il contenitore non ha ruolo dialog né nome associato. Escape lascia la finestra aperta. Il pulsante di chiusura del modal ha solo il carattere ×, mentre la X del banner principale possiede già un'etichetta descrittiva.

**Intervento minimo:** nome e semantica del dialog, focus iniziale e contenuto nel modal, Escape, restituzione del focus e nome accessibile della chiusura.

**Accettazione:** aprire, cambiare preferenze, salvare e chiudere usando solo tastiera; verificare l'annuncio dello screen reader. [Evidenza visiva](/C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-19/evidence/cookie-preferences.jpg).

### R10 — Dimensioni personalizzate con slug duplicato

**File:** [class-marrison-addon-image-sizes.php:3437](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-image-sizes.php:3437), righe 3437–3454; registrazione a 2940–2951.

L'aggiunta deriva lo slug dal nome e lo accoda senza verificare collisioni. Due nomi uguali o normalizzati allo stesso slug producono definizioni che vengono registrate più volte sotto la stessa chiave. Anche uno slug uguale a una dimensione core/tema/plugin richiede un conflitto esplicito.

**Intervento minimo:** validare l'unicità prima del salvataggio e mostrare un errore utilizzabile. Non modificare silenziosamente dimensioni esistenti.

**Accettazione:** doppio nome, nomi che convergono con `sanitize_title()`, collisione con `thumbnail` e normale creazione/modifica. Verifica nel codice; nessuna dimensione temporanea è stata aggiunta al sito.

### R11 — Asset Read More globali quando il modulo è ON

**File:** [class-marrison-addon-read-more.php:18](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-read-more.php:18), metodo `enqueue_assets()` alle righe 366–390.

**Prova LIVE:** homepage senza istanze Read More, ma con `marrison-addon-read-more-js` e 929 caratteri di CSS inline dedicato. Spegnendo soltanto il modulo entrambi scompaiono.

**Intervento minimo:** accodare gli asset dopo aver individuato un'istanza attiva, gestendo anche cache documenti Elementor e contenuti JetEngine/AJAX.

**Accettazione:** nessun asset su pagina senza funzione; CSS e JS presenti e toggle funzionante con istanze reali e listing dinamici. **OFF rispetta già il requisito**: questo intervento riguarda ON senza utilizzo.

## Problemi del sito e limiti dell'hosting

### S01 — Privacy Policy: assenza intenzionale nel sito di test, nessun intervento

Il banner punta a [Privacy Policy configurata](https://lightskyblue-ape-529924.hostingersite.com/?page_id=3), che il motore HTTP ha ricevuto con stato 404. La scansione 1.3.66 termina esplicitamente **parziale**, senza presentarla come completa.

Il proprietario ha chiarito che la pagina non esiste volutamente in questo sito di test. **Non è un errore del plugin e non richiede correzioni.** URL, pagine e testi editoriali non vengono modificati. Il risultato parziale dello scanner resta una descrizione corretta della copertura effettivamente ottenuta.

### S02 — Un font privo di Cache-Control

La diagnostica ripetuta dopo il ripristino dei moduli mostra **11 asset su 12 corretti** con TTL 604800; [WooCommerce.woff](https://lightskyblue-ape-529924.hostingersite.com/wp-content/plugins/woocommerce/assets/fonts/WooCommerce.woff) risponde 200 ma senza `Cache-Control` ed `Expires`. WOFF2 e TTF passano.

Il generatore Marrison include già l'estensione woff. Occorre isolare handler, regole hosting e cache intermedia prima di attribuire la causa al plugin. Il modulo espone correttamente ERRORE CONFIGURAZIONE. [Diagnostica completa](/C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-19/evidence/browser-cache-diagnostic.txt).

### FFmpeg

Il modulo Video Thumbnail segnala correttamente che il server non permette di eseguire FFmpeg. Questo impedisce la generazione automatica da MP4/WebM, senza bloccare caricamento video o miniature YouTube. Non è emerso un errore del modulo da correggere per questo avviso.

## Analisi individuale dei 24 moduli

Per tutti vale il guard del registro, verificato anche con fixture OFF. La prova remota di tutti OFF dimostra assenza di asset Marrison nella homepage osservata; non equivale a una misura delle query PHP su ogni pagina del sito.

| Modulo | Esito individuale | Intervento o verifica successiva |
|---|---|---|
| **Wrapped Link** | Classe/hook isolati da OFF; flusso attributi e navigazione esaminato. Nessun bug concreto confermato. | P3: verificare tastiera, controlli interattivi annidati e link dinamici nell'editor reale. Nessuna istanza configurata è stata esercitata nel sito. |
| **Leggi di più** | Test PHP/JS passati; asset globali anche senza istanza, confermati LIVE. OFF elimina entrambi. | **R11.** Conservare ricalcolo di altezza e integrazione listing/masonry quando si rende condizionale l'enqueue. |
| **Scroll Orizzontale** | Test PHP/JS passati; enqueue condizionato ai container abilitati. Nessun nuovo bug dimostrato. | P3: resize, più istanze e Motion Effects in una pagina dedicata. Lo spazio delle sezioni corte nella modalità classic sticky-pin accettata non è classificato come bug. |
| **Liquid Background** | Controlli, livelli, fallback WebGL, breakpoint e reduced motion presenti; test PHP/JS passati. | P3: misurare GPU con più istanze, verificare sovrapposizioni native Elementor e dispositivi senza WebGL. Non eseguito un test GPU remoto. |
| **Ticker** | Widget visibile nella homepage; il render ripete il CSS inline per istanza. | **M05:** estrarre regole/keyframes statici; lasciare variabili dell'istanza. Verificare pause e testi dinamici da JetEngine. |
| **Steps** | Widget renderizzato nella homepage; CSS sparito da OFF e ripristinato da ON. Escaping e dipendenza stile esaminati. | P3: semantica lista/step e test responsive con icone, immagini e connettori. Nessun guasto funzionale confermato. |
| **Sconto Prodotto** | Nonce/capability, calcolo prodotti variabili, hook WooCommerce e cleanup del toggle presenti; schermata admin funzionante. | **R02/R03** per percorsi alternativi di disattivazione. **M06:** misurare code `shutdown` durante import grandi prima di introdurre un batch. Nessun ricalcolo massivo remoto eseguito. |
| **Visualizzati di recente** | Macro JetEngine e cookie WooCommerce esaminati; il cookie è stato osservato dallo scanner. | P3: testare isolamento tra visitatori, Query Builder con cache disattivata e anteprime non pubblicate. La macro filtra gli ID pubblicati; evitare scritture inutili nelle anteprime. |
| **Titolo Listing** | Escaping di testo/tag/link presente; dipendenze JetEngine/Elementor e isolamento OFF corretti nel flusso esaminato. | P3: testare filtri e paginazione AJAX con heading dinamico e assenza di duplicazioni. Nessun listing configurato è stato esercitato. |
| **Dynamic SVG** | Path uploads, host, `LIBXML_NONET`, attributi ed URL sanitizzati; suite PHP passata. Nessuna XSS dimostrata. | **M07:** limite nodi/complessità se i file reali giustificano il costo. Verificare cache e SVG con text/tspan. |
| **Animazioni Header** | Boot separato protetto dallo stato modulo; suite JS su breakpoint/ereditarietà passata. | P3: titoli con link/markup, reduced motion e salvataggio editor. Nessun errore concreto individuato. |
| **Anchor Offset** | Nessun asset dopo OFF; errore target/download riprodotto in fixture. | **R07.** Aggiungere test dedicati prima della correzione. |
| **Dimensioni Immagini** | Suite PHP/JS passate; test AVIF remoto riuscito. Restano cache CSS al toggle e collisioni slug. | **R04/R10.** P3: valutare limiti contro abuso del resolver pubblico da 50 elementi, senza definirlo bypass autorizzativo. Rigenerazione massiva non eseguita. |
| **Dynamic Background Image Size** | Integrazione con risoluzione nativa esaminata; invalidazione CSS al toggle già presente; test editor PHP passato. | P3: completare prova reale con tag dinamico, CSS rigenerato, dispositivi, salvataggio e Undo/Redo. La fixture non sostituisce questo test editor. |
| **Local Google Fonts** | Parser locale passato; scansione remota completata con diagnostica esplicita sui limiti. Nessun download automatico dal frontend. | **M03:** distinguere utilizzo frontend da candidati nei sorgenti. La scansione ha letto 600 CSS e 600 sorgenti, circa 12,35 MB totali, trovando 14 famiglie candidate, 5 Google confermate. Molte fonti sono file admin/editor. Nessun download font avviato. |
| **CSS piccoli inline** | Suite PHP passata con 51 controlli; origine/path/query e budget esaminati. | P3: compatibilità CSS con symlink e rebasing di font/background. OFF rimuove l'elaborazione; il ritorno a stylesheet esterni è comportamento atteso. |
| **Browser Cache** | Diagnostica LIVE utile e veritiera; toggle OFF riuscito, con ripristino successivo. Nessun CSS/JS frontend proprio da attendersi. | **R02/R03/S02.** La prova degli header dopo OFF, a cache intermedie svuotate, resta distinta dalla sola assenza di asset frontend. |
| **Cursore Animato** | Contesto frontend, pointer fine e reduced motion gestiti; nodi/asset assenti da OFF e ripristinati. | **M02:** RAF continua anche invisibile. Fixture: 242 scritture transform in 120 frame senza movimento, altre 240 dopo pointerleave. È costo da ON, non violazione OFF. |
| **Preloader** | Suite JS passata per link normali, download, target e modificatori; asset/markup isolati da OFF. | **R08/M01.** La fixture beforeunload → pageshow(persisted) lascia l'overlay visibile; verificare l'effettivo impiego della bfcache nel browser prima di dichiarare riproduzione remota. |
| **Fast Logout** | Filtro semplice verso home, coerente con descrizione; nessun asset proprio e nessun difetto individuato. | Nessuna implementazione necessaria emersa. Eventuali eccezioni di redirect devono derivare da un requisito esplicito. |
| **Calendar Sync** | Sanitizzazione, accesso a post privati/password e firma del contesto ICS esaminati; schermata admin raggiungibile. | **R06.** Poi import ICS reale su calendari di destinazione e test della semantica fusi/DST. |
| **Cookie Manager** | Scanner 1.3.66 operativo; 24/24 prove browser, 8 cookie osservati. Rifiuto salvato, persistente al reload e ricevuta con integrità valida. | **R01/R05/R09**, oltre ai cleanup **R02/R03**. S01 escluso: pagina assente intenzionalmente nel test. Suite PHP passate; nessuna criticità aggiuntiva dimostrata in firme/nonce/capability. |
| **PHP Snippets** | UI raggiungibile, nessuno snippet preesistente. Test runtime passati con 100 controlli, inclusi errori fatali reali in sottoprocessi. | Conservare capability doppie, revisioni, quarantena, safe mode e recovery. Nessun nuovo snippet inserito/attivato sul sito; prova editor/menu nativo completa resta da separare dal test runtime. |
| **Video Thumbnail** | UI funzionante e avviso FFmpeg corretto; suite PHP passata con 25 controlli. | **M04:** allowlist host/path miniature e coerenza video_id. `download_url()` segue il percorso HTTP sicuro WordPress: non è stata dimostrata una SSRF. P3: valutare background job/timeout per cover locali solo su server compatibili. |

## Approfondimenti P3

- **M01, Preloader:** [marrison-preloader.js:145](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/js/marrison-preloader.js:145). Ripristinare lo stato su `pageshow` quando la pagina torna dalla cache di navigazione.
- **M02, cursore:** [marrison-cursor.js:97](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/js/marrison-cursor.js:97). Fermare RAF invisibile/stabilizzato e riavviarlo su movimento; mantenere reduced motion e gestione del puntatore.
- **M03, font:** aggiungere provenienza/grado di certezza all'elenco e dare precedenza ai fogli effettivamente usati. Il limite di 600 file è esposto correttamente; raggiungerlo non dimostra che il sito abbia tutti quei font nel frontend.
- **M04, miniature:** [class-marrison-addon-video-thumbnail.php:230](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-video-thumbnail.php:230). Non fidarsi del solo image_url inviato dal client; accettare il candidato coerente con YouTube. È hardening del contratto di importazione per utenti con upload_files.
- **M05, ticker:** [ticker-widget.php:686](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/ticker/widgets/ticker-widget.php:686). Condividere regole e keyframes, mantenendo la configurazione per istanza.
- **M06, sconti:** [class-marrison-addon-product-discount.php:483](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-product-discount.php:483). Profilare la coda prima di spostare il lavoro fuori richiesta; la registrazione di un hook shutdown da sola non dimostra query costose in ogni richiesta.
- **M07, SVG:** [class-marrison-addon-dynamic-svg.php:211](/C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-dynamic-svg.php:211). Il limite in byte è già presente; un ulteriore limite di complessità va calibrato su file reali, senza degradare SVG legittimi.

Non considero difetti: altezza zero delle dimensioni proporzionali WordPress; `mb_strlen()` del Ticker, perché WordPress fornisce già il fallback di compatibilità; assenza di nonce come prova automatica di vulnerabilità su un resolver intenzionalmente pubblico; spazio corto dello sticky pin già accettato.

## Verifiche eseguite e prove salvate

### Isolamento OFF

1. Fixture PHP in memoria, frontend e admin: tutti i 24 moduli OFF → **0 file sotto includes/modules, 0 classi modulo**. Restano i file del motore centrale.
2. Toggle Read More remoto → nessuno script o stile Read More nella homepage successiva.
3. Tutti i 24 toggle remoti OFF, stato ricontrollato dopo reload del pannello.
4. Homepage remota ricaricata → **0 script Marrison, 0 stylesheet/style con identificativo Marrison**, nessun banner/cursore/preloader. Solo il widget Heading resta tra i widget della homepage; Ticker e Steps non sono renderizzati.
5. Tutti i toggle ripristinati e verificati dopo ulteriore reload → **24/24 ON**.

[Evidenza JSON](/C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-19/evidence/live-isolation.json) · [Pannello tutti OFF](/C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-19/evidence/all-modules-off.jpg) · [Stato finale ripristinato](/C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-19/evidence/modules-restored.jpg).

### Verifiche remote delle funzioni

- Tutte le dieci schermate amministrative dei moduli raggiunte e lette, Cookie Manager incluso.
- Scanner: runtime 1.3.66, protocollo compatibile; 26 URL candidati, 14 campioni selezionati, 12 equivalenti evitati, 12 campioni HTTP e 24/24 prove browser, 8 cookie distinti. Copertura dichiarata parziale per Privacy Policy 404. [Rapporto osservato](/C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-19/evidence/cookie-scan.txt).
- Preferenze cookie: apertura, Escape, chiusura, rifiuto, persistenza al reload; una ricevuta di rifiuto letta con `integrity_valid=true`. Nessun identificativo o prova crittografica della ricevuta è riportato qui.
- Browser Cache: 12 asset verificati, 11 passati, WOFF senza header; seconda verifica dopo il ripristino delle regole con stesso problema.
- Local Google Fonts: scansione richiesta manualmente, limiti e candidati resi visibili.
- Test server AVIF riuscito.
- Homepage: render di Ticker e Steps; scomparsa dei loro contributi nella prova OFF e ripristino.

### Suite locali passate

| Gruppo | Suite |
|---|---|
| Core / snippet | module-definitions-i18n-test.php; updater-layout-test.php; php-snippets-php-test.php |
| Cookie | cookie-auto-scan-php-test.php; cookie-consent-php-test.php; cookie-consent-store-php-test.php; cookie-engine-php-test.php; cookie-expiration-php-test.php; cookie-observed-scan-php-test.php; cookie-policy-php-test.php; cookie-shortcodes-php-test.php |
| Media / prestazioni | image-sizes-modern-upload-test.php; image-sizes-frontend-regression-test.php; inline-css-php-test.php; video-thumbnail-php-test.php; browser-cache-php-test.php; local-google-fonts-parser-test.php |
| Elementor PHP | read-more-php-test.php; horizontal-scroll-php-test.php; liquid-background-php-test.php; dynamic-svg-php-test.php; dynamic-background-editor-php-test.php |
| JavaScript | read-more-js-test.js; horizontal-scroll-js-test.js; liquid-background-js-test.js; header-animations-js-test.js; image-sizes-background-js-test.js; image-sizes-admin-js-test.js; browser-cache-copy-js-test.js; preloader-js-test.js |

**30 suite distinte passate**, più lint e fixture mirate. Lo shortcode Cookie è stato controllato con le API WordPress native dell'installazione locale; non costituisce prova di rendering su tutte le pagine remote. La fixture di campionamento include 207 URL, 9 campioni HTTP e 18 passaggi browser simulati.

Lint PHP sui file esaminati e controlli sintattici JS pertinenti superati. `git diff --check` senza errori di whitespace; sono presenti avvisi Git di normalizzazione delle terminazioni di riga in file già modificati. [Output aggiuntivi delle 13 suite lanciate dal coordinamento](/C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-19/evidence/additional-unit-tests.txt).

Le nuove riproduzioni mirate hanno esaminato consenso pendente, inventario di 31 giorni, lifecycle bulk, invalidazione CSS, fusi calendario, target/download Anchor, ripristino Preloader e RAF Cursor. Il successo delle suite esistenti non smentisce i casi nuovi non ancora coperti.

## Limiti e stato lasciato

Non sono stati completati: un ciclo editor/salvataggio/frontend per ogni combinazione Elementor/JetEngine; rigenerazione massiva dei media; ricalcolo sconti sull'intero catalogo; download effettivo dei font; estrazione reale FFmpeg; confronto header a server/cache completamente svuotati dopo OFF; esecuzione server-side misurata con profiler/query monitor in tutte le pagine.

Non ho modificato plugin o temi, testi delle pagine, impostazioni dei tagli o snippet. Le prove hanno aggiornato inventario/rapporto dello scanner, diagnostica cache e scansione dei font; hanno generato una ricevuta di rifiuto per il browser di test. Il browser mantiene quel rifiuto. I 24 moduli e le impostazioni preesistenti sono stati ripristinati; i CSS Elementor possono essere stati invalidati dal toggle già previsto di Dynamic Background.

L'aggiornamento da 1.3.64 a 1.3.66 ha risolto il precedente messaggio di incompatibilità del motore scanner: quel messaggio non è elencato tra i problemi attuali.

