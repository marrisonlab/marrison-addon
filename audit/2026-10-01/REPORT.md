# Audit Marrison Addon 1.3.43 e correzioni 1.3.44 — 1 ottobre 2026

**Stato finale: tutti i 21 rilievi confermati sono corretti nella versione 1.3.44, nei sorgenti e in LocalWP.** Passano 14 suite, 63 controlli sintattici e l'avvio isolato dei 21 moduli. Il pacchetto installabile e le prove per ogni correzione sono nel [rapporto finale](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/remediation/REPORT.md). Il sito online non è stato aggiornato.

**Storico dell'indagine:** la seconda passata ha confermato 16 rilievi in Calendar Sync, Header Animations, Wrapped Link, Image Sizes, Cursore, Preloader, Cookie Manager, updater e test/debug. Le segnalazioni dell'utente e le riproduzioni successive hanno aggiunto cinque rilievi Cookie/Scroll, per **21 problemi complessivi in 8 moduli funzionali e nei componenti trasversali**. Il primo riepilogo era troppo netto: le prove iniziali parziali non permettevano di considerare i moduli privi di bug.

Le prime tre correzioni Cookie/Scroll sono documentate nel [rapporto bugfixes](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/bugfixes/REPORT.md); ritaglio Boxed, ingresso Snap e adattamento immagini richiesto dall'utente sono nel [rapporto scroll-followup](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/scroll-followup/REPORT.md).

Le diagnosi e le riproduzioni sotto conservano lo stato **prima delle correzioni**. Le indicazioni “correzione suggerita” descrivono l'analisi originale e sono state attuate come documentato nel rapporto finale. Un test superato copre il caso indicato, non ogni configurazione possibile.

## Ambiente e metodo

- LocalWP: `http://localhost:10004/`, WordPress **7.1.2**, PHP **8.2.29**, Elementor **4.3.3**, JetEngine **3.8.15.4**, WooCommerce **10.9.4**.
- Dopo le correzioni, **78/78 file distribuibili** del plugin installato coincidono con il repository; `AGENTS.md` è escluso dal pacchetto. Nell'audit iniziale il confronto di 79 file includeva anche quel documento.
- Analisi delle classi, hook, controlli, JS/CSS, richieste AJAX, sanitizzazione ed escaping; verifica delle API effettivamente installate.
- **43 PHP + 20 JavaScript**: controlli sintattici superati. Il lint generale usa PHP CLI 8.5.5; le prove WordPress usano il PHP 8.2.29 di LocalWP.
- **21 avvii individuali**, tutti spenti, e avvio senza Elementor / JetEngine / WooCommerce: nessun fatal o errore del plugin catturato. Con tutti i moduli spenti le 21 classi operative risultano assenti. Restano i soli hook del bootstrap.
- **14 suite finali** superate. Il test Local Google Fonts ora parte direttamente dal checkout; aggiunti contratti Updater/Cookie/Preloader/Header ed estese le regressioni immagini/Scroll.
- Pannello generale e **9 pannelli amministrativi** verificati nel browser, senza errori console o pagine fatali osservati.
- **28 azioni AJAX** interrogate senza autenticazione/nonce: gli endpoint amministrativi negano l'accesso. Le azioni pubbliche previste di refresh nonce e risoluzione background rimangono disponibili; le altre azioni pubbliche richiedono un nonce valido.
- Probe DB in transazione, con rollback dopo lo shutdown WordPress. Verificata l'assenza dei record temporanei di prodotti ed eventi. La fixture HTML è stata rimossa dal sito locale dopo la verifica dell'hash; il suo sorgente è conservato nell'audit.
- Seconda passata: CSS reale generato da un documento Elementor temporaneo salvato in transazione; frame con viewport effettivi 1280 e 390 px; navigazioni Preloader; colori del sanitizer Cursore; pipeline immagini con size mancante/presente; rifiuto/consenso completo/selettivo Cookie Manager con tracker locali innocui e nonce volutamente invalido. Lo stato del consenso del test è stato ripristinato; tutte le sei fixture di questa passata sono state rimosse dopo verifica hash e i record temporanei risultano assenti.

## Problemi confermati e priorità

### P1 — Calendar Sync esporta eventi non pubblici

In [class-marrison-addon-calendar-sync.php](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-calendar-sync.php:199), `download_ics()` verifica soltanto che il post esista. Non controlla stato, password o capacità di lettura prima di emettere titolo, descrizione e date.

**Riproduzione:** un post temporaneo `draft`, con date valide, viene esportato eseguendo il percorso dell'endpoint `?marrison_event_ics=ID` con utente corrente non autenticato. L'ICS contiene `SUMMARY:Audit draft calendar event` e la descrizione del contenuto non pubblicato. Il post è stato rollbackato. La prova usa il metodo reale che gestisce la richiesta; non è una richiesta HTTP a un post persistente.

**Correzione suggerita:** applicare una politica di lettura esplicita prima dell'esportazione, includendo post privati, bozze e contenuti protetti da password. Non affidarsi alla conoscenza dell'ID.

Evidenza: [calendar-ics-output.txt](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/services/calendar-ics-output.txt).

### P1 — Updater GitHub seleziona la cartella sbagliata

L'updater scarica l'archivio automatico del tag, mentre il repository contiene il plugin nella sottocartella `marrison-addon`. [fix_folder_name()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/class-marrison-addon-updater.php:35) rinomina la cartella esterna senza selezionare il plugin interno.

**Riproduzione locale:** su una struttura equivalente all'archivio del repository, il percorso restituito non contiene `marrison-addon.php`; il file si trova un livello sotto, in `marrison-addon/marrison-addon.php`. Un aggiornamento con questo layout può rendere il plugin non caricabile. Non è stato eseguito un aggiornamento reale né scaricato un tag remoto.

**Correzione suggerita:** selezionare e verificare la cartella che contiene il file principale, oppure usare un asset di release con il layout installabile; verificare anche il risultato dell'operazione filesystem.

Evidenza: [core-runtime-results.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/core-runtime-results.json).

### P2 — Calendar Sync perde i meta personalizzati dello shortcode

Lo shortcode usa `start_meta` ed `end_meta` per validare e generare il collegamento, ma [get_event_links()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-calendar-sync.php:411) passa al link ICS soltanto l'ID. Il download rilegge le chiavi globali.

**Riproduzione:** con date valide in `audit_start` / `audit_end`, il link Google funziona e il link ICS viene generato; il download ICS risponde **“Date evento non disponibili.”** Con altre date globali potrebbe esportare quelle sbagliate.

Evidenza: [calendar-shortcode-results.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/calendar-shortcode-results.json). La correzione deve mantenere una validazione sicura delle chiavi utilizzate.

### P2 — Calendar Sync perde il luogo nell'ICS

Lo shortcode accetta `location`, utilizzato nel link Google. Il download scrive sempre `LOCATION:` vuoto in [class-marrison-addon-calendar-sync.php](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-calendar-sync.php:251). La prova del link Google conserva `Roma`, mentre l'ICS reale conferma il campo vuoto.

### P2 — Header Animations applica l'animazione tablet sul desktop

[get_selected_animation()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-header-animations.php:219) sceglie il primo valore custom tra desktop, tablet e mobile; `before_render()` lo aggiunge come classe comune al widget.

**Riproduzione:** desktop vuoto, tablet `marrisonDropSoft`, mobile `marrisonLettersFocus`. Nel browser a **1280 px**, la classe e l'`animation-name` sono già `marrisonDropSoft`. Inoltre la scelta comune non distingue animazioni custom differenti tra breakpoint.

**Correzione suggerita:** mantenere la selezione per dispositivo secondo le impostazioni responsive di Elementor.

### P2 — Le animazioni a lettere eliminano markup e a capo

[splitIntoLetters()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/js/marrison-header-animations.js:33) ricostruisce tutto da `textContent`.

**Riproduzione browser con Heading reale:** `Prima <strong>evidenza</strong><br>Seconda <em>riga</em>`. Dopo l'inizializzazione spariscono `strong`, `em` e `br`; “evidenza” e “Seconda” vengono concatenate. La perdita riguarda anche la struttura dei nodi, non soltanto lo stile.

**Correzione suggerita:** elaborare i nodi di testo conservando elementi inline, collegamenti e interruzioni di riga.

Evidenza dei due difetti Header: [browser-evidence.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/browser-evidence.json).

### P3 — Calendar Sync permette chiavi meta vuote

[sanitize_settings()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-calendar-sync.php:82) conserva `start_meta=''` e `end_meta=''`. Salvare il form così può rendere inutilizzabili i collegamenti evento. Il probe del sanitizer lo conferma. Conviene rifiutare valori vuoti o ripristinare i default con una diagnostica chiara.

### P3 — Il test Local Google Fonts non parte dal checkout attuale

[local-google-fonts-parser-test.php](C:/Users/Angelo/Documents/GitHub/marrison-addon/tests/local-google-fonts-parser-test.php:90) cerca `../includes/modules/...`, mentre il plugin è annidato in `../marrison-addon/includes/modules/...`. Il test originale termina con exit **255**. La copia corretta nell'audit passa: questo è un problema del test, non una prova di malfunzionamento del parser.

### P3 — Log di debug residuo

[admin-image-sizes.js](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/js/admin-image-sizes.js:2) emette sempre `console.log('Marrison Addon: Image Sizes Script Loaded')`. È rumore da sviluppo, senza impatto funzionale osservato.

### P2 — Wrapped Link ignora gli attributi personalizzati

Il controllo URL Elementor espone `custom_attributes` e il README dichiara il supporto. [before_render()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-wrapped-link.php:47) conserva l'array URL nel JSON; il [JS del modulo](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/js/marrison-addon.js:16) usa soltanto URL e nuova finestra.

**Riproduzione:** un Container reale con `data-audit|yes,aria-label|Go to target` mantiene quei valori nel JSON, ma nel DOM non esistono né `data-audit` né `aria-label`. Sono ignorati sia nel render PHP sia dopo il caricamento JS. Il click al target funziona.

### P2 — Cursore: il selettore hover non conserva i colori esadecimali

Il campo hover usa il color picker WordPress, che permette valori come `#ff0000`. [sanitize_settings()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-cursor.php:172) accetta soltanto sintassi `rgb(...)` / `rgba(...)` per quel campo.

**Riproduzione con il sanitizer reale:** `#ff0000` diventa `rgba(17,17,17,0.12)`, mentre `rgb(255,0,0)` e `rgba(255,0,0,0.5)` rimangono. Il colore scelto in formato hex viene quindi perso al salvataggio. Correzione: accettare anche un hex valido o uniformare il controllo al formato previsto.

### P2 — Image Sizes crea una size mancante senza registrarne i metadata

In [generate_modern_formats_for_metadata()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-image-sizes.php:749), quando la size manca dai metadata, viene generato il file ridimensionato e poi WebP/AVIF. [add_modern_source_to_metadata()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/class-marrison-addon-image-sizes.php:1122) ritorna immediatamente se la size metadata manca.

**Riproduzione con il filtro pubblico reale:** PNG 320×240, size richiesta 120×90, metadata iniziali con `sizes=[]`. Su disco esistono PNG/WebP/AVIF 120×90; i metadata restituiti mantengono `sizes=[]`. Ripetendo con la size di base già presente vengono invece registrate entrambe le sorgenti moderne. I file del ramo di fallback non sono quindi utilizzabili tramite il normale lookup metadata della size / srcset.

Il file fixture è nella directory audit e viene mappato soltanto all'attachment temporaneo con `get_attached_file`; l'attachment viene rollbackato. Questo caso riguarda metadata incompleti/aggiornati e non afferma che tutti gli upload normali siano difettosi.

Evidenza Cursore e Image Sizes: [runtime-functional-results.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/second-pass/runtime-functional-results.json).

### P2 — Preloader blocca la pagina sui link con hash vuoto

[shouldInterceptLink()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/js/marrison-preloader.js:115) esclude un anchor solo se `url.hash` è valorizzato. Per `href="#"` il parser URL restituisce hash vuoto: il modulo mostra l'overlay e assegna la location, ma non avviene un nuovo caricamento che lo nasconda.

**Riproduzione browser:** dopo il click, URL termina con `#`, `readyState=complete`, overlay opacity 1, visibility visible, pointer-events auto. La pagina resta coperta fino a reload. Un anchor normale `#target` passa senza overlay.

### P2 — Preloader forza navigazioni già annullate da altri script

Il [listener click](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/js/marrison-preloader.js:122) non verifica `event.defaultPrevented`.

**Riproduzione browser:** un handler sul link chiama `preventDefault()` e aggiorna il testo “action handled; navigation cancelled”. Il Preloader esegue comunque la navigazione dopo il timeout, ricarica la pagina e perde lo stato dell'azione. Può interferire con link che avviano azioni AJAX o altre interazioni. La navigazione normale passa.

### P2 — Cookie Manager non blocca iframe con src non quotato

[filter_iframe_tag()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/cookie-manager/includes/class-cookie-consent.php:97) sostituisce `src` soltanto se è tra virgolette. Il formato non quotato è HTML valido.

**Riproduzione PHP e browser senza consenso:** un iframe riconosciuto come marketing viene marcato `data-marrison-cookie-blocked`, ma conserva `src` e carica il documento. Un iframe equivalente con src quotato rimane about:blank fino al consenso. La prova usa una destinazione locale innocua contenente `youtube.com` nel nome per attivare la stessa classificazione, senza contattare YouTube. Anche dopo Reject all l'iframe non quotato è già stato caricato.

### P2 — Cookie Manager perde type=module durante la riattivazione

[activateBlockedScript()](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/includes/modules/cookie-manager/assets/js/frontend.js:398) scarta sempre `type` copiando gli attributi e crea uno script classico.

**Riproduzione browser:** script analytics valido `type="module"` con `export`, inizialmente correttamente bloccato; dopo Accept all i due script classici incrementano i contatori a 1, il modulo resta a 0 e la console segnala **Unexpected token 'export'** da `activateBlockedScript()` linea 411. La stessa perdita si riproduce con consenso selettivo Analytics. Il tipo originale deve essere conservato separatamente durante il blocco e ripristinato al consenso.

Evidenza browser della seconda passata: [browser-results.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/second-pass/browser-results.json).

## Verifica individuale dei 21 moduli

Tutti superano avvio isolato e controllo di sintassi. Nella colonna finale sono indicati i percorsi che non sono stati certificati end-to-end.

| Modulo | Prova aggiuntiva eseguita | Esito | Limite residuo |
|---|---|---|---|
| Wrapped Link | Container reale, click/cambio hash e DOM degli attributi custom | **Corretto in 1.3.44** | Nuova finestra e tastiera non certificati; gestione is_external=1/on uniformata |
| Leggi di più | Controlli Text Editor/Dynamic Field, contratti e toggle/ARIA; HTML complesso conserva strong, em, liste e link durante expand/collapse | Passa nei casi provati | Listing AJAX non provato end-to-end |
| Scroll Orizzontale | Documento pubblico riprodotto con runtime Elementor; Full/Boxed, percentuali, row, pin; rotella Snap/padding; ingresso e resize desktop/mobile | **Quattro rilievi corretti in 1.3.44** | Online da distribuire; Motion Effects Pro/antenati non certificati in tutte le combinazioni; altri contenuti troppo alti possono impedire il pin |
| Liquid Background | Contratti; documento Elementor temporaneo con CSS reale; resa WebGL desktop/mobile: canvas altezza 360 e larghezza 1265/375 px | Passa nel caso custom provato | Context loss, fallback, tutte le varianti palette/gradient e touch non certificati |
| Ticker | Widget e API query reali; repeater/animazione CSS; probe con testo non scalare | Passa; aggiunto controllo difensivo in 1.3.44 | Query JetEngine popolata e pausa hover non provate end-to-end |
| Steps | 8 step; documento/CSS reale con 2 step: desktop grid e mobile 390 px flex column | Passa nei casi provati | Icone/immagini e breakpoint globali personalizzati non certificati; CSS usa soglie fisse |
| Sconto Prodotto | Semplice, prezzo gratuito/limiti, variabile reale = 50%; batch reale total/processed=6, done=true | Passa nei casi provati | Concorrenza cron/batch e saldi programmati non certificati |
| Visualizzati di recente | Lazy init reale; ordine, limite/inclusione/esclusione corrente, tracker e cap 50 | Passa nei casi provati | Query Builder con cache attiva non provato; cache query da disabilitare come dichiarato |
| Titolo Listing | Controlli sulla Listing Grid installata; callback render antepone il titolo correttamente | Passa | Refresh AJAX e stili di una Listing salvata non certificati |
| Dynamic SVG | Contratto PHP; callback JetEngine; sanitizzazione reale rimuove script, eventi e URL esterni, conserva currentColor/none | Passa | Campo media in editor e resa della Dynamic Field su pagina salvata non certificati |
| Animazioni Header | Heading reali, responsive, resize, markup, ritardo nativo e fallback; contratti breakpoint aggiuntivi | **Due rilievi corretti in 1.3.44** | Altre animazioni/editor rerender e ogni combinazione responsive non certificati |
| Anchor Offset | Script frontend, misura `#hdr` di 88 px, variabile CSS 88 px e navigazione al target | Passa nel caso provato | ResizeObserver dinamico e caricamento iniziale con hash non provati nel browser |
| Dimensioni Immagini | GD WebP/AVIF e alfa 127; pipeline con size mancante/presente, metadata e lookup della size WordPress | **Corretto in 1.3.44** | Upload Media Library e frontend srcset/downsize completi non certificati |
| Local Google Fonts | Parser/coverage; CSS2 ital+wght 100..900 restituisce 18 varianti; gestione manifest/errori | Logica provata passa; **test originale corretto** | Scan/download reale non eseguito; pannello indica “Mai eseguita / Non configurato” |
| Browser Cache | Codice generatore/regole, admin, server Nginx rilevato; header HTTP dell'asset reale | Diagnostica coerente | Cache **non applicata**: pannello richiede configurazione manuale Nginx; header attuale `no-cache`. Apache/LiteSpeed non testabili qui |
| Cursore Animato | Enqueue/pannello; dot/follower; sanitizer hex/rgb/rgba reale | **Corretto in 1.3.44** | Movimento, touch e reduced motion non certificati |
| Preloader | Load, link normale/anchor, hash vuoto, preventDefault e ritorno browser; contratti JS | **Due rilievi corretti in 1.3.44** | BFCache non esercitato (pageshow.persisted=false); temi senza wp_body_open non certificati |
| Fast Logout | Filtro reale restituisce la home anche con redirect richiesto diverso | Passa secondo comportamento dichiarato | Logout completo nel browser e multisite non eseguiti |
| Calendar Sync | Shortcode, sanitizer, 8 casi endpoint ICS: visibilità, password, meta/luogo firmati e timezone legacy | **Quattro rilievi corretti in 1.3.44** | Import in client calendario e DST non certificati |
| Cookie Manager | Scanner/AJAX; rifiuto/accept/custom; iframe quoted/unquoted e script module; nonce invalido; titolo con h3 globale bianco | **Tre rilievi corretti in 1.3.44** | Online da distribuire; tracker esterni/dinamici e cookie cross-domain non certificati; log browser non attribuito discusso nel rapporto finale |
| Video Thumbnail | Admin/AJAX, controlli capacità/nonce, download tramite API sicura WordPress, diagnostica FFmpeg | Avvio/admin passano | **FFmpeg assente**: estrazione cover non testabile. Recupero/import YouTube esterno non eseguito |

## Codice e condizioni dell'ambiente

- Nessun fatal del plugin osservato; gli endpoint amministrativi provati senza credenziali non eseguono le operazioni.
- Il download thumbnail usa `download_url()` → `wp_safe_remote_get()`: non è stata dimostrata una SSRF verso host privati. Un URL amministrativo arbitrario, da solo, non costituisce questa prova.
- La macro JetEngine viene caricata dopo l'inizializzazione lazy: controllare soltanto `class_exists()` subito dopo il bootstrap avrebbe prodotto un falso positivo.
- LocalWP segnala `php_imagick.dll` non caricabile. È un problema dell'ambiente; GD ha comunque superato entrambe le conversioni moderne con trasparenza.
- Il rischio di eccezione con timezone ICS legacy invalido è stato gestito in 1.3.44 con fallback; l'export reale passa. Rimane escluso dal conteggio dei 21 difetti del normale flusso confermati.
- Image Sizes e Local Google Fonts concentrano molte responsabilità in classi grandi: questo aumenta il costo delle regressioni e suggerisce test più estesi; non giustifica un refactoring generale durante questo audit.
- PHP **7.4** non è stato eseguito. La ricerca statica non ha individuato i comuni costrutti/API esclusivi PHP 8 cercati, ma non certifica l'intera compatibilità dichiarata.
- Il viewport mobile richiesto allo strumento non è stato applicato: l'osservazione rimane 1280×720. Le prove responsive automatiche non vengono presentate come verifiche visuali mobile.
- La seconda passata supera il limite del viewport con iframe realmente larghi 390 px: certifica il layout in quel viewport, non hardware touch, user-agent mobile o ogni combinazione responsive.
- Il rischio Ticker con `item_text` array è stato gestito in 1.3.44 saltando i valori non scalari; il probe completa il render. La prova usa dati raw, senza un normale flusso editor locale dimostrato, e resta esclusa dal conteggio dei bug funzionali confermati.

## Evidenze e ripetibilità

- [Sintassi](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/syntax-checks.json), [suite esistenti](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/contract-tests.json), [isolamento](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/isolation-summary.json), [AJAX](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/ajax-unauthenticated-checks.json), [browser](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/browser-evidence.json).
- Rapporti di dettaglio: [Elementor](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/elementor/audit.md), [media/cache](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/media-cache/audit.md), [servizi](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/services/audit.md).
- [Harness WordPress](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/wp-runtime-probe.php): rete esterna bloccata, moduli isolabili e rollback. Gli script restano nella cartella di audit; la fixture conserva il sorgente per ripetere le prove, ma non resta esposta in LocalWP.
- [Verifica finale di pulizia e corrispondenza dei 79 file](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/final-cleanup.json).
- Seconda passata: [prove browser](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/second-pass/browser-results.json), [pipeline immagini e sanitizer](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/second-pass/runtime-functional-results.json), [Cookie Manager](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/services/second-pass/cookie-report.md), [Elementor](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/elementor/second-pass/findings.md), [font/SVG](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/media-cache/second-pass/report.md).
- Pulizia seconda passata: [fixture rimosse](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/second-pass/cleanup.json), [consenso ripristinato](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/second-pass/cookie-state-restored.json), [record temporanei assenti](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/test-record-cleanliness.txt).

**Correzioni concluse:** tutti i 21 rilievi confermati sono chiusi in 1.3.44, insieme ai due controlli difensivi legacy/Ticker. [Prove finali e pacchetto](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/remediation/REPORT.md). Restano i limiti delle prove indicati sopra, la configurazione manuale Nginx e le dipendenze locali mancanti; non sono presentati come difetti del codice risolti.
