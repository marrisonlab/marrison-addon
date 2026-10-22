# Changelog

Tutte le modifiche rilevanti di Marrison Commander sono documentate in questo file.

## [1.5.14] - 2026-10-21

- Il worker Browserless restituisce le chiavi localStorage/sessionStorage osservate nei frame, prima/dopo consenso. Solo nomi, tipo e origine: nessun valore. Inventario distinto dai cookie, senza ampliare i tempi di attesa.
- Compatibile con l’endpoint scanner 1.5.13, nessuna migrazione o nuova chiave. Il background è gestito da Addon 1.3.73 sul sito WordPress.

## [1.5.13] - 2026-10-20

- Servizio pubblico Browserless per Cookie Manager: verifica automatica della scansione attiva, nessuna licenza, chiave cifrata centralmente e solo cookie osservati, senza valori.
- Una pagina per chiamata, prima/dopo consenso, video YouTube incorporati, timeout 45 secondi, riserva massima di due unità per pagina, budget mensile e limiti giornalieri/concorrenza.
- Retry della stessa pagina dalla cache senza una seconda chiamata al fornitore, URL pubblici, DNS/TLS verificati, redirect bloccati e JSON protetto. Nessuna migrazione database.

## [1.5.12] - 2026-10-18

- Verifica giornaliera dei siti MCU con attivazione e orario Europe/Rome nelle Impostazioni (03:00 iniziale); avvii distribuiti in 15 minuti, cron del server indipendente dalle visite e ultima esecuzione del runner visibile.
- Verifica manuale e verifiche finali degli update eseguono sempre la sincronizzazione forzata prima di leggere lo stato. Cache mancanti o dati non verificati non producono un pallino verde.
- Il monitor propaga gli errori della sincronizzazione e riprova dopo 15 minuti, senza segnare riuscito il tentativo fallito e rinviarlo di sei ore.
- I callback terminali mantengono il sito in attesa di verifica; il polling controlla lo stato fresco prima del completamento. Richiede MCU 9.8.24 per gli esiti completi delle fonti e la programmazione locale.
- Nessuna migrazione database. Test locali con HTTP/database simulati, inclusi tre update seguiti da un residuo, errori delle fonti, retry e cambio ora legale.

## [1.5.11] - 2026-10-17

- Corretto HTTP 500 dell'endpoint aggiornamenti dopo il salvataggio della configurazione: cache dei metadati nella tabella impostazioni di Commander, senza `get_transient`, `set_transient` o costanti WordPress. Scadenza 12 ore e invalidazione su modifica della configurazione o del token.
- Download ZIP senza funzioni WordPress o proprieta plugin inesistenti, con nome file e header corretti, lettura dello stato finale dopo i redirect GitHub ed errore esplicito per risposte non ZIP.
- Nessuna migrazione database; nessun aggiornamento Assistant o MCU richiesto per il fix server. Verifiche locali dei metadati, della cache e del download con GitHub simulato.

## [1.5.10] - 2026-10-17

- Whitelist condivisa per percorso del plugin, valida anche nelle versioni successive; compatibile con le approvazioni gia salvate. Revoca globale di tutte le valutazioni del percorso.
- Analisi incrementale dei soli metadati/versioni nuovi nel registro, senza ricontrollare differenze del solo hash. Criteri AI concentrati su identita sospette, con meno segnalazioni dovute a plugin privati, premium o poco documentati.
- Badge di sicurezza solo per plugin sospetti; nessuna etichetta nelle righe senza sospetti. Dubbi ed errori restano consultabili nei dettagli.
- Lista di riferimento per ogni sito e avviso separato per nuovi plugin installati, con data e comando per segnare le novita come lette. Le normali verifiche MCU aggiornano il confronto senza AI; aggiornamenti di versione e controlli falliti non generano nuove installazioni.
- Migrazione automatica 14 per gli inventari persistenti; MCU 9.8.23 resta compatibile senza aggiornamenti aggiuntivi.

## [1.5.9] - 2026-10-17

- Analisi plugin solo su comando, con registro condiviso delle impronte gia valutate, whitelist manuale revocabile e consumi Gemini registrati. Nessuna analisi AI nei controlli di connessione o nel monitor.
- Segnalazioni per sito e tab Sicurezza plugin con motivazioni, data dei dati ed errori espliciti. Risposte AI incomplete o incoerenti rifiutate; nuova impronta/versione richiede una nuova valutazione.
- Rimozione esplicita dei plugin segnalati tramite MCU 9.8.23, con conferma, HMAC, controlli di identita/percorso e verifica finale dell'assenza. Nessuna disinstallazione eseguita dal plugin, nessun retry automatico, dati e opzioni conservati.
- Migrazione automatica 13 e schema iniziale per registro/analisi. L'analisi riguarda metadati e impronta del file principale, non certifica tutti i file e non sostituisce una bonifica.

## [1.5.8] - 2026-10-17

- Maintenance: filtro per plugin installato con elenco alfabetico ricavato dagli inventari MCU gia salvati, inclusi i plugin disattivati. Combinabile con la categoria, conserva l'ordinamento scelto dei siti.
- Select Categoria e Plugin installato piu ampi e con altezza, bordi e spaziatura coerenti con i pulsanti della barra filtri.
- Data ed eta dell'inventario nei risultati e copertura esplicita per i siti senza inventario. La ricerca non interroga i client e non confonde gli aggiornamenti disponibili con i plugin installati; nessuna migrazione o modifica a MCU.

## [1.5.7] - 2026-10-16

- Il rifiuto Gemini HTTP 402 resta un errore esplicito con origine `gemini` e motivo `provider_payment_required` anche per le chiamate testuali e lo streaming; nessuna conversione in un generico 500 di comunicazione.
- Il test API invita a verificare configurazione e fatturazione in caso di 402. Nessuna risposta grezza del provider, chiave o prompt viene restituita nell’errore.
- Nessun retry del 402, rinnovo della cache o registrazione di consumi non confermati. La correzione non sblocca il servizio né cambia quote, pagamenti o connessione.

## [1.5.6] - 2026-10-16

- Corretto il contenuto del pacchetto AI/licenze: inclusi `GeminiStreamResponse` e le dipendenze di modelli, cache e contabilizzazione del runtime AI. Nessun cambiamento al provider o alla scelta delle connessioni.
- Build riproducibile e verifica del pacchetto estratto, senza caricare le classi AI dal checkout completo. Riproduzione separata di JSON riuscito e chat SSE in errore 500 quando manca il parser.
- Nessuna nuova migrazione rispetto alla 1.5.5; licenze multiple e controlli di autorizzazione conservati. Nessuna modifica a Marrison Assistant o ai siti live.

## [1.5.5] - 2026-10-16

- Licenze con più plugin sullo stesso sito; selezione pronta di Domino, Marrison Assistant e Marrison Optimizer in creazione/modifica e riepilogo nella pagina Siti.
- Migrazione automatica 12 per la lista autorizzata, compatibile con licenze precedenti e slug commerciali WooCommerce. Nessun ampliamento automatico dei permessi.
- Controllo della licenza associata anche nelle chiamate AI senza chiave e nelle configurazioni preliminari; API attuali e legacy condividono la verifica. Motivi strutturati per blocchi e quota.
- Optimizer 0.8.32 mette in pausa il bulk su HTTP 402 e sui blocchi di autorizzazione, mantenendo i progressi.

## [1.5.4] - 2026-10-08

- Il contratto `ai_media_text` accetta anche il titolo WordPress del media, con un massimo di quattro campi noti e schema server-side.
- Chiave/modello globali e conteggio esistente di token e costi conservati; nessuna migrazione o modifica a file/URL WordPress da parte di Commander.

## [1.5.3] - 2026-10-07

- Contratto testuale dedicato `ai_elementor_rewrite` per Rewrite AI di Marrison Optimizer 0.8.1, con schema JSON dei frammenti definito sul server.
- Chiave e modello Gemini Flash globali, token inclusi quelli di ragionamento e costi registrati una volta per generazione nelle analytics esistenti. Anche risposte incomplete con consumi restano contabilizzate.
- Controllo preliminare del supporto/modello senza Gemini o quota; cambio modello e payload incompatibili respinti prima della quota. Nessun retry automatico del trasporto, nessuna migrazione database.
- Contratti immagini, Yoast e streaming conservati.

## [1.5.2] - 2026-10-06

- Contratto testuale `ai_yoast_seo` per Yoast AI Autofill di Marrison Optimizer 0.6.0: schema server-side dei soli campi SEO richiesti, Gemini Flash globale e consumi registrati una volta.
- Lettura del modello globale prima del batch senza chiamata Gemini o consumo di quota. Le richieste con modello cambiato o campi arbitrari sono rifiutate prima della quota.
- Limite ragionevole di prompt/output, nessun retry automatico per generazioni SEO, errori Gemini/quota e completamento inoltrati al plugin. Contratti immagini e streaming preservati.
- Nessuna migrazione o modifica ai dati SEO WordPress da parte del server.

## [1.5.1] - 2026-10-06

- Chiave API e modello Gemini globali per tutti i siti, Assistant, testi immagini ed endpoint legacy. Rimossi i campi dedicati dalla creazione/modifica dei siti; le vecchie colonne ricevute tramite form o import non vengono usate o salvate.
- I siti legacy con chiavi dedicate usano la configurazione globale; senza chiave globale la chiamata fallisce. Quote, licenze, dati storici e colonne compatibili restano conservati.
- La pagina Siti mostra il modello globale corrente e non inserisce vecchie credenziali nella pagina. Impostazioni e importazione riflettono la configurazione unica.
- Gli endpoint legacy registrano il modello comunicato dal provider/client, senza attribuirlo al vecchio modello del sito.
- Include il contratto JSON dei campi effettivamente mancanti e i profili di analisi dei testi immagini introdotti dalle patch precedenti.
- Verifiche locali: 140 controlli Gemini, 13 controlli su Site/Settings e amministrazione/import reali con database simulato, 34 controlli streaming, 33 controlli Optimizer/Commander e browser del modulo Siti. Nessuna distribuzione o chiamata Gemini reale.

## [1.5.0] - 2026-10-05

### Aggiunto

- Streaming facoltativo per `/api/chat` e gli alias legacy: con `stream=true`, Commander utilizza `streamGenerateContent` di Gemini e inoltra eventi SSE `delta`, seguiti da `done` con testo finale, modello, motivo di conclusione, token e metadati della cache. Compatibile con Marrison Assistant 1.6.1.
- Parser incrementale con gestione dei frammenti UTF-8 e dei delimitatori SSE; i blocchi di ragionamento non vengono inoltrati. Header e commenti SSE di riempimento limitano il buffering dei piccoli pacchetti.
- `/api/ping` riporta la versione corrente e la capacità `chat_streaming`.

### Corretto

- Un'interruzione, un errore Gemini o uno stream senza conclusione producono un evento di errore, senza risposta di successo né rigenerazione automatica. La cache può essere ritentata una sola volta senza il riferimento scaduto soltanto dopo un rifiuto esplicito e prima del primo testo.
- Licenza, rate limit e quota restano verificati prima dell'apertura dello stream. Token, costi e cache usano lo stesso percorso delle risposte complete e vengono contabilizzati una sola volta al completamento; `finishReason` raggiunge anche i controlli del plugin.
- Le richieste senza `stream=true` mantengono la risposta JSON completa. Nessuna modifica allo schema del database richiesta dallo streaming.

### Verifica

- Passati 58 controlli Gemini esistenti, 34 nuovi controlli di streaming e 33 controlli con connessioni HTTP/cURL reali locali sulla catena Gemini simulato → Commander → trasporto Marrison Assistant. Dati di licenza/database e superficie API WordPress simulati; nessuna distribuzione sul server di produzione o prova con API key Gemini reale.

## [1.4.4] - 2026-09-15

### Aggiunto

- La riga espansa di Maintenance usa una navigazione a tab per separare Sommario, Stato, Update, Diagnostica, Debug e Backup.
- La tab Sommario mostra conteggi e liste update per plugin, temi e traduzioni quando presenti nello snapshot, oltre a versione WordPress e versione client MCU.
- Ogni azione Maintenance registra l'ultimo messaggio persistente sul sito, cosi errori e conferme restano visibili nel Sommario anche dopo la scomparsa della barra progresso.

## [1.4.3] - 2026-09-15

### Cambiato

- Il client Maintenance legge il risultato delle operation MCU dal payload strutturato `data`, rimuovendo la dipendenza dai campi duplicati top-level usati per compatibilita.
- Le callback job MCU vengono registrate dal blocco strutturato `job_status`, senza fallback sul vecchio payload piatto.

## [1.4.2] - 2026-09-15

### Corretto

- Gli update plugin privati avviati da Maintenance preservano gli slug MCU con punti, evitando errori "Aggiornamento non trovato" su pacchetti come `jupiter-core-v1.1.0`.

## [1.4.1] - 2026-09-15

### Aggiunto

- Tracking locale dei job `update_all` MCU con tabella dedicata, stato pubblico per riga Maintenance e ripresa automatica del polling dopo refresh pagina.
- Endpoint admin `maintenance-job-status.php` per seguire i job accodati su MCU con polling adattivo e senza chiamate continue.
- Endpoint callback firmato per ricevere da MCU la chiusura del job quando WordPress riesce a inviare subito l'esito finale.

### Cambiato

- La richiesta "Aggiorna tutto" invia a MCU l'URL di callback Commander quando `APP_BASE_URL` e configurato.
- La riga Maintenance espansa usa fondo scuro solo mentre i dettagli sono aperti, mantenendo bianchi i blocchi interni.

## [1.4.0] - 2026-09-08

### Aggiunto

- Impostazioni globali per gli URL dei repository privati MCU di plugin e temi.
- Distribuzione della configurazione ai client MCU tramite le richieste firmate di stato e operazione.
- Revoca della configurazione privata prima della rimozione di un sito MCU da Commander.

### Corretto

- La rimozione di un sito MCU non usa piu una operation di revoca non documentata: quando necessario disattiva la configurazione repository tramite una sincronizzazione MCU supportata, e mostra il motivo se la chiamata fallisce.
- Se un client MCU non espone ancora la route `/action`, la rimozione usa come fallback l'endpoint `/status` firmato con configurazione repository vuota.
- Dopo un errore MCU `HTTP 404`, la pagina Maintenance mostra una rimozione solo locale esplicita per togliere da Commander un sito i cui endpoint MCU non sono piu raggiungibili.
- Aggiunti tooltip ai comandi della pagina Maintenance e alle azioni nei dettagli riga.
- Le colonne "Prossimo update schedulato" e "Update" sono ordinabili cliccando il titolo.
- La colonna "Nome" e ordinabile cliccando il titolo.
- La lettura del prossimo update schedulato riconosce piu campi MCU e date testuali, oltre ai timestamp numerici.
- Se MCU dichiara gli aggiornamenti automatici attivi ma non espone una prossima esecuzione, Commander mostra "Schedulazione da rigenerare" invece di "Non programmato".
- Il pulsante "Interrompi" ferma tutte le azioni generali sequenziali della toolbar e interrompe l'applicazione dei risultati del monitor leggero.

## [1.3.0] - 2026-08-04

### Aggiunto

- Storage diagnostico Maintenance con snapshot, moduli, probe esterni e audit MCP.
- Server MCP remoto read-only per Codex, con bearer token, Origin allowlist, rate limit e tool solo diagnostici.
- Sincronizzazione lazy dei manifest e dei moduli MCU protocollo 2.
- Pagina admin Diagnostica e azione "Sincronizza snapshot disponibile".
- Probe esterni sicuri da Commander con redirect consentiti solo sullo stesso host.
- Monitoraggio proattivo leggero lato Commander con runner CLI `bin/maintenance-monitor.php`.
- Motore segnali deterministico Maintenance e colonna "Segnali" nella tabella admin.
- Scanner frontend esterno leggero e analisi AI opzionale sopra prove gia raccolte.

### Cambiato

- `MaintenanceHttpClient::VERSION` portato a `1.3.0` e refactoring delle operazioni HMAC tramite `requestOperation()`.
- Le richieste firmate verso MCU non seguono redirect.
- La cifratura dei segreti Maintenance non salva piu nuovi valori in fallback base64/plain.

## [1.2.8] - 2026-08-01

### Aggiunto

- Pulsante riga "Cancella cron Master/Commander" nella pagina Maintenance.
- Endpoint admin `maintenance-cancel-master-update.php`: invia `cancel_master_update` al client MCU e rilegge subito lo stato aggiornato.

### Cambiato

- La colonna Azioni Maintenance e stata ampliata per ospitare il nuovo pulsante senza comprimere le icone.

## [1.2.7] - 2026-08-01

### Cambiato

- Il pulsante riga "Sincronizza update MCU" usa una semplice icona fulmine e lo stesso stile neutro degli altri pulsanti azione.

## [1.2.6] - 2026-08-01

### Cambiato

- Maintenance e ora la prima pagina admin di Commander; la pagina Siti resta disponibile dal menu.
- I dettagli riga Maintenance si comportano come accordion: aprendo una riga si chiudono le altre.

### Aggiunto

- Endpoint AJAX locale `maintenance-stats.php` per aggiornare in tempo reale i contatori Maintenance senza interrogare i client MCU.

## [1.2.5] - 2026-08-01

### Cambiato

- Il pulsante riga "Sincronizza update MCU" usa ora un'icona sync/fulmine colorata, distinta da verifica, dettagli e aggiornamento.

## [1.2.4] - 2026-08-01

### Cambiato

- Il comando "Forza sincronizzazione update" e ora visibile anche nella toolbar come "Sincronizza selezionati", oltre all'icona sulla singola riga.

## [1.2.3] - 2026-08-01

### Aggiunto

- Pulsante riga "Forza sincronizzazione update" nella pagina Maintenance.
- Endpoint admin `maintenance-force-sync.php`: invia `force_sync` al client MCU e rilegge subito lo stato aggiornato.

### Cambiato

- Colonna azioni Maintenance allargata per ospitare il nuovo comando senza comprimere le icone.

## [1.2.2] - 2026-08-01

### Corretto

- Le statistiche della pagina Maintenance, incluso `Update aperti`, ora si aggiornano dopo verifiche e update senza ricaricare la pagina.

## [1.2.1] - 2026-08-01

### Aggiunto

- Import multiplo dei file JSON configurazione MCU dalla pagina Maintenance.
- Selezione/deselezione dei singoli siti MCU e selezione globale dalla tabella.
- Verifica dei soli siti selezionati.
- Aggiornamento multiplo dei siti selezionati con esecuzione sequenziale, non parallela.

## [1.2.0] - 2026-08-01

### Aggiunto

- Modulo admin Maintenance per gestire da Commander i siti con MCU client.
- Tabella `maintenance_sites` e auto-migrazione schema v5.
- Import del JSON configurazione MCU, incluse chiavi connessione e one-click dashboard.
- Verifica stato firmata HMAC, richiesta update remoto `update_all`, one-click access e report diagnostico JSON.
- Scheda dettagli con stato MCU, ambiente, backup disponibili e plugin da aggiornare.

### Cambiato

- Il ping pubblico dichiara la versione `1.2.0`.
- L'installer inizializza `schema_version.txt` alla versione schema 5.

## [1.1.0] - 2026-07-28

### Aggiunto

- Chat con contesto separato tra `static_context`, `dynamic_context` e `system_instruction`.
- Cache esplicita Gemini per riusare il contesto statico dei siti e ridurre token/costi.
- Endpoint `/api/context-cache/invalidate` e alias legacy WordPress per invalidare la cache dopo un rescan.
- Tabella `gemini_context_caches` e auto-migrazione schema v4.
- Metriche token cache nella dashboard analytics, nei log token e nei calcoli di costo/risparmio.
- Configurazione per retry Gemini, cache context e profili `call_type`, incluso `config_scan`.

### Cambiato

- Le chiamate chat passano da un flusso diretto a `GeminiService` a `ChatCompletionService`, che centralizza modello, temperatura, cache, logging e contabilita.
- `/api/chat` e gli alias legacy accettano anche payload camelCase compatibili con i client esistenti.
- L'installer inizializza le nuove opzioni Gemini e imposta `schema_version.txt` alla versione schema 4.

## [1.0.0] - 2026-07-01

### Aggiunto

- Prima versione standalone PHP/MySQL con gestione licenze, siti, quote Gemini, log token, white-label e webhook WooCommerce.
