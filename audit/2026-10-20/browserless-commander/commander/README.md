# Marrison Commander — Standalone (PHP/MySQL)

Versione standalone (non WordPress) di Marrison Commander: backend PHP 8+/MySQL
per gestire licenze, siti autorizzati, quote giornaliere Gemini, log token,
white-label, webhook WooCommerce e manutenzione centralizzata dei siti MCU.

Versione corrente: `1.5.13`. Consulta `CHANGELOG.md` per le note di rilascio.

### Scanner cookie Browserless (1.5.13)

Servizio pubblico per Marrison Addon 1.3.72, senza licenze o registrazione dei siti.
Apri Impostazioni > Scanner cookie Browserless dopo avere caricato la patch.
La chiave viene cifrata solo su Commander. Guida: `docs/COOKIE-BROWSERLESS-1.5.13.md`.

### Verifica programmata MCU (1.5.12)

In **Impostazioni > Verifica programmata siti MCU** si sceglie l'orario giornaliero
(03:00 Europe/Rome iniziale). Ogni verifica forza la ricerca aggiornamenti prima
di leggerne l'esito. I siti sono distribuiti nei 15 minuti successivi e processati
a lotti; un errore viene contato e ritentato dopo 15 minuti. Per la chiusura degli
update, anche lo zero residuo deve provenire da una nuova sincronizzazione. Una
callback di completamento mantiene il sito in attesa del controllo finale.

Il runner deve essere avviato dal cron del server **ogni 5 minuti**, dalla
directory di Commander: `php bin/maintenance-monitor.php --limit=30`. Il limite
conta le operazioni, non tutti i siti in un'unica esecuzione: configurare il cron
solo una volta alle 03:00 non assicura di completare l'intera flotta. Il pannello
mostra l'ultima esecuzione del runner e il numero di errori; salvare l'orario non
installa automaticamente il cron del server.

MCU 9.8.24 offre anche il proprio orario in **Impostazioni > Generale**, con esiti
e ultimo successo persistenti. Per eseguirlo indipendentemente dalle visite serve
un cron dell'hosting che richiami `wp-cron.php` ogni 5 minuti. I controlli locale e
centrale sono separati. Nessuna installazione di plugin durante la sola verifica.

Verifica locale: `php tests/maintenance-verification-regression.php`. Questi test
usano risposte HTTP e database simulati; cron e siti live vanno verificati dopo
il deployment autorizzato.

## Correzione endpoint aggiornamenti plugin

`dist/commander-update-endpoint-fix-1.5.11.zip` e una patch per Commander 1.5.10:
sovrapporre tutti i file alla root dell'installazione esistente. Corregge il 500
`Call to undefined function App\Controllers\get_transient()` e le dipendenze
WordPress nel download ZIP. Usa la tabella impostazioni gia presente, senza
migrazioni o modifiche alla configurazione salvata. Cache metadati di 12 ore,
invalidata al cambio di repository, configurazione, token o URL Commander.

Il test pubblico e `GET /api/updates/marrison-assistant`: deve restituire HTTP 200,
`success: true`, versione e URL di download Commander. La verifica del download
deve produrre uno ZIP valido. La ricerca aggiornamenti WordPress puo mantenere
un errore in cache per 5 minuti; la presenza dei metadati non dimostra l'avvenuta
installazione. Procedura in `docs/UPDATE-ENDPOINT-FIX-1.5.11.md`.

Verifica locale: `php tests/update-controller-regression.php`, con Settings e
controller reali, database e risposte GitHub simulati, senza aggiornamenti live.

## Aggiornamento completo del collegamento AI

Usare `dist/commander-ai-license-1.5.7.zip`: include il parser dello streaming
`GeminiStreamResponse` e tutte le dipendenze AI e licenze. Il precedente ZIP 1.5.5
non comprendeva il parser: su un server che non lo aveva gia, il test immagini
poteva riuscire mentre la chat in streaming terminava con un errore 500.
Il pacchetto viene verificato anche dopo estrazione, caricando le classi soltanto
dall'archivio, con HTTP/provider e dati locali simulati. Il test riproduce separatamente
il 500 rimuovendo il solo parser da una copia temporanea del pacchetto.
Su installazioni gia migrate a schema 12 non si aggiungono altre migrazioni.
Conservare configurazione e storage del server; copiare tutti i file del pacchetto.

## Rifiuto Gemini HTTP 402

Il 402 viene mantenuto per immagini, testo e streaming con origine e motivo strutturati. Verificare configurazione e fatturazione del provider; il pacchetto corregge la classificazione dell’errore e non dichiara risolta la disponibilità del servizio. Per il widget usare Marrison Assistant 1.6.4 o successivo.

## Licenze e plugin autorizzati

In Licenze, crea o modifica la licenza e seleziona Domino (`domino`), Marrison Assistant
(`marrison-assistant`) e/o Marrison Optimizer (`marrison-optimizer`). In Siti associa
il dominio a quella licenza. La quota giornaliera del sito è condivisa fra i plugin:
0 significa illimitata; per un bulk di 1.500 immagini serve una quota adeguata.
Gli slug delle licenze precedenti restano selezionabili. Le vecchie licenze mantengono
il solo plugin già autorizzato fino a un salvataggio esplicito della nuova selezione.
WooCommerce conserva il proprio slug di prodotto e le licenze ricevute restano singole.
La migrazione automatica 12 aggiunge `licenses.plugin_slugs` nullable, senza riscrivere
le licenze. Distribuire insieme tutti i file della release e conservare un backup del DB.
La verifica del plugin vale anche senza chiave nel payload e nei controlli preliminari,
prima di quota o chiamate Gemini. Un blocco restituisce 403 con motivo strutturato;
la quota giornaliera esaurita restituisce 429 con `quota_exceeded`.
Il solo HTTP 402 non dimostra una licenza errata: può essere inoltrato dal provider o
provenire da una versione distribuita diversa. Optimizer 0.8.32 sospende il bulk anche
in questo caso, senza saltare tutto il catalogo o attivare API proprie.

## Struttura

AI testi immagini di Optimizer 0.8.21 può richiedere anche `title` tramite
`ai_media_text`: fino a quattro campi stringa (`title`, `alt`, `caption`,
`description`) con schema costruito sul server. Il titolo è quello WordPress
del media, non il filename. Stesso modello globale e conteggio token/costi.
Il contesto editoriale della pagina viene raccolto e validato da Optimizer
prima di essere incluso nel prompt. Nessuna migrazione database.

```
commander/
  public/            <- webroot effettivo (index.php, admin/, assets/)
  app/
    Controllers/      <- logica applicativa (Api, Legacy, Webhook, Admin*)
    Models/           <- accesso dati PDO (Admin, License, Site, TokenLog, ...)
    Services/         <- GeminiService, EmailService, RateLimiter
    Helpers/          <- config(), helpers generici, Router, auth
  config/
    config.example.php  <- copia in config.php (generato anche dall'installer)
  storage/logs/       <- log applicativi (errors.log, access.log)
  install/
    install.php       <- installer via browser
    schema.sql         <- schema tabelle
  CHANGELOG.md        <- note di rilascio
```

## Streaming chat

Con Commander **1.5.0** e Marrison Assistant **1.6.1**, le risposte testuali idonee possono arrivare progressivamente anche quando il provider scelto nel plugin è **Marrison Commander**, usando il modello Gemini configurato sul server, incluso `gemini-3.1-flash-lite`. Nel plugin lascia attiva **Widget → Risposte in streaming**. Prodotti, ordini, confronti e risposte con selezione delle fonti mantengono i rispettivi controlli prima della visualizzazione.

Occorre distribuire **entrambi gli aggiornamenti**: il solo plugin non rende progressivo un Commander precedente. Aggiorna i file nelle rispettive installazioni e svuota cache pagina/CDN/browser. Lo streaming non richiede una nuova scansione dell'indice Assistant né una nuova migrazione del database Commander. Hosting, FastCGI, compressione o proxy possono comunque accumulare i frammenti: una prova sul sito deve mostrare il primo testo prima della conclusione della risposta.

Le richieste a `/api/chat` e agli alias legacy possono aggiungere `"stream": true` allo stesso corpo JSON già usato per licenza, sito e prompt. Gli errori di autorizzazione, rate limit e quota precedono lo streaming e restano JSON con il loro stato HTTP. Dopo l'apertura, la risposta è `text/event-stream`:

```text
event: delta
data: {"delta":"Prima frase. "}

event: done
data: {"success":true,"message":"Prima frase. Risposta completa.","usageMetadata":{},"finishReason":"STOP","model":"gemini-3.1-flash-lite","call_type":"chat","contextCache":{}}
```

L'evento `done` contiene l'esito definitivo e i metadati; un errore dopo l'apertura usa l'evento `error` con `success=false`. Una connessione interrotta o priva dell'esito finale non è un successo e non viene reinviata automaticamente. I blocchi di ragionamento Gemini restano interni. Senza l'opzione streaming la risposta resta JSON. `/api/ping` espone versione e `capabilities.chat_streaming` del backend installato.

Verifica locale (nessuna API key o database reali):

```powershell
php tests/gemini-regression.php
php tests/streaming-regression.php
python tests/streaming-http-regression.py --plugin C:/Users/Angelo/Documents/GitHub/marrison-assistant
```

La prova HTTP avvia e chiude server locali temporanei e usa l'estensione cURL disponibile in `C:/php/ext`; su un altro ambiente adegua il percorso PHP/estensione nel test. Non distribuire `tests/` nel webroot pubblico. Sono passati 58 controlli Gemini, 34 controlli streaming e 33 controlli HTTP locali. Queste prove non confermano il buffering dell'hosting o il comportamento della chiave Gemini di produzione.

## Maintenance MCU

### Analisi plugin su comando

Con Commander **1.5.10** e MCU **9.8.23** o successivi, **Analizza i plugin**
valuta i metadati dei plugin sui siti selezionati oppure, senza selezione, su
tutti i siti mostrati dai filtri. Il comando lavora sugli inventari salvati:
dopo aver aggiornato MCU, eseguire **Verifica connessione** o **Verifica tutti
i siti**, quindi ricaricare Maintenance. Non avvia verifiche live implicite.

Il registro condiviso riusa le valutazioni gia salvate per percorso, versione
e metadati. **Whitelist** approva il percorso del plugin su tutti i siti e resta
valida anche nelle versioni successive, senza nuove analisi. Anche le whitelist
salvate con la 1.5.9 vengono riconosciute. **Rimuovi valutazione** revoca whitelist
e valutazioni di quel percorso globalmente. Il prossimo comando valuta solo
plugin nuovi o con versione/metadati cambiati e non gia nel registro; un cambio
del solo hash del file non richiede AI. L'analisi non parte da cron, monitor, refresh
o verifica connessione. Gemini usa chiave/modello globali e registra i consumi
nell'intent `maintenance_plugin_security`.

La riga del sito mostra **Possibile infezione** solo in presenza di plugin
classificati sospetti. Nessuna etichetta per siti senza sospetti; dubbi, inventari
incompleti e dati da analizzare restano nella tab **Sicurezza plugin**, accessibile
dai dettagli del sito. Gli errori del comando restano espliciti. Il criterio AI
cerca identita sospette e forti incoerenze, senza ipotizzare infezioni o vulnerabilita
di plugin legittimi. Le risposte AI incomplete non diventano valutazioni riuscite; le
valutazioni dei gruppi gia completati si conservano per riprendere manualmente.
La reputazione riguarda i metadati: non e una scansione antivirus o una
certificazione del codice. Il file hash e mantenuto solo per verificare la rimozione.

Le verifiche MCU salvano una lista di plugin per sito e rilevano i nuovi percorsi
installati, anche se gia in whitelist. **Nuovi plugin: N** e un avviso separato,
non una diagnosi di infezione. Nei dettagli mostra nome, versione, percorso e data;
**Segna le novita come lette** cancella gli avvisi mantenendo la lista di confronto.
Gli aggiornamenti dello stesso percorso non sono nuove installazioni. Il primo
inventario costituisce la base senza avvisi; per i siti esistenti il primo confronto
usa l'inventario gia salvato. Controlli falliti o privi di inventario non cancellano
la lista. Un'aggiunta non letta resta visibile anche se il plugin scompare in seguito.
Nessuna AI per questo confronto; i nuovi avvisi arrivano quando Commander riceve
un successivo inventario MCU valido, senza sorveglianza in tempo reale.

**Rimuovi dal sito** e disponibile per plugin sospetti/da verificare con MCU
compatibile. Richiede conferma di sito, nome e file, usa il protocollo HMAC e
il lock aggiornamenti MCU, controlla l'identita corrente ed elimina solo i
file del plugin senza eseguirne gli script di disattivazione/disinstallazione.
Conserva dati e opzioni. Blocca MCU stesso, multisito, dipendenze dichiarate,
directory condivise, link simbolici, percorsi esterni e modifiche file vietate.
Commander richiede la prova MCU e un inventario successivo che confermi
l'assenza; in mancanza riporta un errore, senza ritentare la rimozione.
Questo intervento non bonifica eventuali altre infezioni del sito.

Schema **14** automatico e idempotente: mantiene `maintenance_plugin_reviews`
e `maintenance_plugin_scans` e aggiunge `maintenance_plugin_inventories`;
non modifica licenze o dati dei client. Pacchetto completo della funzione:
`dist/commander-maintenance-plugin-security-1.5.10.zip` (sovrapporre alla root Commander).
Su database aggiornati il marker di schema avanza solo dopo tutti i DDL riusciti.

Verifiche locali: `php -d extension=pdo_sqlite tests/maintenance-security-regression.php`,
`php -d extension=pdo_sqlite tests/maintenance-plugin-inventory-regression.php`,
`php tests/mcu-plugin-security-regression.php` (richiede il repository MCU accanto
a `marrison-commander`), `php tests/maintenance-security-migration.php`.

### Funzioni Maintenance

La pagina admin **Maintenance** incorpora le funzionalita del precedente
Marrison Maintenance Master dentro Commander standalone:

- Maintenance e la prima pagina admin di Commander; la vecchia pagina Siti resta
  raggiungibile dal menu;
- import di uno o piu file JSON scaricati da MCU, inclusi nome sito WordPress,
  endpoint, chiave di connessione e one-click dashboard;
- elenco siti in ordine alfabetico crescente, con stato, ultime verifiche,
  frequenza MCU, ultimo update, prossimo update schedulato e conteggio update;
- filtro per plugin installato: elenco alfabetico senza duplicati ricavato dagli
  inventari MCU salvati in Commander, inclusi i plugin disattivati. Il filtro si
  combina con la categoria e conserva l'ordinamento scelto per i siti; riporta
  data ed eta dell'inventario e segnala i siti senza dati, esclusi dalla ricerca
  per plugin. Nessuna interrogazione ai client durante il filtraggio;
- verifica singola o sequenziale di tutti i siti, con barra avanzamento e
  pulsante Interrompi visibile solo durante la scansione;
- contatori Maintenance aggiornati via AJAX locale senza interrogare i client;
- sincronizzazione forzata MCU per aggiornare cache e conteggi update del
  client, disponibile sia per singola riga sia per siti selezionati, seguita da
  rilettura immediata dello stato;
- selezione dei siti per applicare verifiche o update a un gruppo specifico;
- richiesta update remoto `update_all` verso MCU: pulizia cache lato client,
  aggiornamento plugin, temi e traduzioni vengono eseguiti solo quando premi
  il comando di update;
- feedback immediato sugli update avviati da Commander: i job MCU vengono
  tracciati localmente, ripresi dopo refresh pagina, seguiti con polling
  adattivo e chiusi tramite callback firmato quando il client riesce a
  notificare l'esito; risultati operation e callback usano i blocchi
  strutturati `data` e `job_status`;
- cancellazione da Commander delle sole richieste update Master/Commander
  rimaste in coda sul client, senza toccare la programmazione schedulata MCU;
- aggiornamento multiplo sequenziale sui siti selezionati, una richiesta alla
  volta;
- stato viola quando Commander ha inviato la richiesta ma non ha ancora ricevuto
  una scansione successiva che confermi l'esito;
- riga dettagli con ambiente, stato MCU, lock update, plugin da aggiornare,
  errori leggibili e link ai backup database/hosting quando MCU li espone;
- riga dettagli organizzata a tab, con Sommario iniziale per update aperti,
  versioni WordPress/MCU e ultimo messaggio persistente delle azioni
  Maintenance;
- one-click access alla dashboard WordPress del client, con redirect consentito
  solo verso lo stesso host del sito registrato;
- report diagnostico JSON scaricabile senza segreti in chiaro;
- sezione read-only Diagnostica con manifest snapshot, moduli sincronizzati,
  probe esterni, confronto snapshot e integrazione MCP per Codex;
- monitoraggio proattivo leggero lato Commander, con heartbeat, stato MCU,
  sincronizzazione update a bassa frequenza, sync snapshot disponibile e
  scanner frontend esterno;
- motore segnali deterministico prima dell'AI, con anomalie normalizzate per
  disponibilita, cron, update, errori, REST API, WooCommerce e frontend;
- analisi AI opzionale sopra prove gia raccolte, senza azioni automatiche sui
  siti client.

Commander non installa daemon sui siti MCU. Il controllo proattivo gira sul
server Commander, a lotti piccoli, e il client resta coinvolto solo per le
chiamate firmate necessarie.

Gli update lanciati da Commander non aggiungono listener o processi residenti
sul client MCU: il sito accoda il lavoro con WP-Cron, espone lo stato nelle
risposte firmate `/status` e, se possibile, invia a Commander una callback
firmata alla chiusura del job.

### Monitoraggio proattivo leggero

Il runner CLI puo essere pianificato su Commander con cron/Task Scheduler:

```bash
php bin/maintenance-monitor.php --limit=30 --frontend
```

Frequenze interne predefinite:

- heartbeat homepage e REST API: circa 15 minuti;
- stato MCU firmato: circa 1 ora;
- sincronizzazione update disponibili: giornaliera all'orario scelto nelle Impostazioni, con ritardo distribuito fino a 15 minuti e retry dopo 15 minuti in caso di errore;
- sincronizzazione snapshot gia disponibile: circa 24 ore;
- scanner frontend esterno: circa 24 ore e solo sui siti con segnali seri, salvo
  forzatura.

L'AI non viene chiamata automaticamente dal pulsante "Monitor leggero". Dal CLI
puo essere abilitata con `--ai`; resta comunque limitata ai siti con segnali
warning/critical e non piu di una volta ogni 6 ore, salvo `--force-ai`.

## 1. Installazione su hosting Aruba (Linux, PHP 8+)

1. Crea un database MySQL/MariaDB e un utente da pannello Aruba.
2. Carica via FTP/SFTP l'intera cartella `commander/` dentro la webroot del tuo
   sito (es. dentro `public_html/`), così l'app sarà raggiungibile su
   `https://miosito.it/commander/`.
3. Assicurati che `mod_rewrite` sia attivo (di solito lo è su Aruba). I due
   file `.htaccess` (`commander/.htaccess` e `commander/public/.htaccess`)
   instradano automaticamente le richieste da `/commander/...` a
   `/commander/public/...` e da lì alle API.
4. Visita `https://miosito.it/commander/install/install.php` e compila:
   - credenziali database (host/nome/utente/password);
   - Base URL (`https://miosito.it/commander`);
   - App Secret e Webhook Secret (puoi lasciarli vuoti, verranno generati);
   - email e password del primo utente amministratore.
5. Al termine l'installer crea `config/config.php` e `storage/installed.lock`
   e si **autodisabilita** (rieseguirlo dà errore 403 finché non elimini
   manualmente `storage/installed.lock` e `config/config.php`).
6. Accedi al pannello: `https://miosito.it/commander/admin/login.php`.

### Permessi cartelle

`storage/` (e `storage/logs/`) deve essere scrivibile dal processo PHP
(tipicamente permessi 775 su Aruba). `config/config.php` viene scritto
automaticamente dall'installer: se la cartella `config/` non è scrivibile,
create manualmente il file copiando `config/config.example.php`.

### Fallback senza mod_rewrite

Se il tuo hosting non supporta `mod_rewrite`, il pannello admin funziona
comunque (sono file `.php` reali in `public/admin/`). Solo le API pubbliche
`/api/*` e gli alias `/wp-json/*` richiedono il rewrite; in alternativa puoi
richiamarle con:

```
https://miosito.it/commander/public/index.php?r=/api/chat
```

## 2. API Key Gemini globale

Nel pannello admin, pagina **⚙️ Impostazioni**, puoi impostare una **API Key
Gemini globale** e il **modello globale**. Tutti i siti e tutte le chiamate Gemini
gestite da Commander usano questa configurazione, inclusi Assistant, testi immagini
e gli endpoint legacy. Aggiunta, modifica e importazione dei siti non accettano chiavi
o modelli dedicati. Le colonne legacy nel database restano per compatibilità, ma non
vengono usate né esposte nel modulo di modifica. Se la chiave globale manca, le
richieste falliscono: nessun recupero delle vecchie chiavi dedicate. Quote e licenze
restano specifiche per sito. Anteprime e analytics riportano il modello effettivo
della risposta; i log precedenti non sono riscritti.

## 3. Configurazione webhook WooCommerce

Nel pannello admin vai su **WooCommerce**:
- copia **Webhook URL** (`https://miosito.it/commander/api/webhooks/woocommerce`);
- copia il **Secret** (generato dall'installer, salvato in `config.php` →
  `woocommerce.webhook_secret`);
- in WooCommerce → Impostazioni → Avanzate → Webhook, crea un nuovo webhook:
  - Argomento: **"Ordine aggiornato"** (Commander legge lo stato reale
    dell'ordine dal payload, quindi crea la licenza quando lo stato diventa
    `completed` o `processing`; puoi comunque aggiungere anche "Ordine
    completato" come extra, non è obbligatorio);
  - URL di consegna: l'URL copiato sopra;
  - Segreto: lo stesso secret copiato sopra;
  - Formato API: WP REST API v3 (JSON).
- Configura il **mapping prodotti → piano** nella stessa pagina (product_id,
  plugin_slug, quota giornaliera, durata in giorni).
- Usa il pulsante **🧪 Test Webhook** per verificare che il flusso funzioni
  (crea una licenza fittizia con order_id `TEST-...`).

Quando arriva un ordine `completed`, Commander:
1. verifica la firma HMAC (`X-Wc-Webhook-Signature`) o il secret semplice;
2. legge `line_items` e mappa ogni `product_id` al piano configurato;
3. genera una `license_key` univoca e crea il record in `licenses`;
4. salva il webhook in `webhook_logs`;
5. tenta l'invio email al cliente (se `mail.enabled = true` in config.php,
   altrimenti resta in `email_log` con status `pending` per copia manuale
   dal pannello).

## 4. WooCommerce Subscriptions (abbonamenti ricorrenti)

Se usi il plugin **WooCommerce Subscriptions**, oltre al webhook "Ordine
completato" già descritto sopra, configura in WooCommerce → Impostazioni →
Avanzate → Webhook anche:

- **Sottoscrizione creata** (topic `subscription.created`)
- **Sottoscrizione aggiornata** (topic `subscription.updated`)
- **Sottoscrizione eliminata** (topic `subscription.deleted`)

tutti puntati allo stesso **Webhook URL** e **Secret** già copiati dalla
pagina `admin/woocommerce.php`.

Comportamento automatico gestito dal `WebhookController`:

- **Ordine iniziale completato** → crea la licenza come per un acquisto normale.
- **`subscription.created`** → collega la licenza appena creata alla
  subscription (tramite `parent_id` dell'ordine iniziale), salvando
  `subscription_id` nella tabella `licenses`.
- **Ordine di rinnovo completato** (riconosciuto dal meta
  `_subscription_renewal` che WooCommerce Subscriptions aggiunge
  automaticamente) → **non crea una nuova licenza**, ma estende
  `expires_at` della licenza esistente in base a `duration_days` del
  mapping prodotto (o alla `next_payment_date` se disponibile).
- **`subscription.updated`** con stato `on-hold`/`cancelled`/`expired` →
  aggiorna lo `status` della licenza di conseguenza (`inactive`/`revoked`).
- **`subscription.deleted`** → licenza impostata su `revoked`.

**Migrazione da installazione esistente:** non serve fare nulla manualmente.
Se avevi già installato Commander prima di questa funzionalità, la colonna
`subscription_id` viene aggiunta **automaticamente** alla tabella `licenses`
al primo caricamento di una qualsiasi pagina/API dopo l'aggiornamento dei
file (vedi `App\Models\Database::runAutoMigrations()`), senza toccare
phpMyAdmin. Lo script `install/migrate_subscriptions.sql` resta disponibile
solo come fallback manuale nel caso la cartella `storage/` non sia
scrivibile dal server.

## 5. Migrazione dal vecchio plugin WordPress

1. Da phpMyAdmin (o strumento equivalente) esporta la tabella
   `wp_marrison_sites` in CSV, mantenendo le colonne:
   `site_url, max_quota, used_today, last_reset_date, white_label, created_at`.
2. Nel pannello admin standalone vai su **📥 Importa**, seleziona formato CSV
   (o JSON) e incolla il contenuto.
3. Per ogni riga viene creata una **licenza "legacy"** (`plugin_slug =
   marrison-assistant`, `status = active`, scadenza +1 anno, `license_key`
   generata automaticamente) e il sito corrispondente in `sites`, con
   `site_url`, `max_quota`, `used_today` e `white_label` riportati fedelmente.
   Le eventuali colonne `api_key` e `model` dei vecchi export sono ignorate.

## 6. Retrocompatibilità con il plugin client esistente

Se il plugin client (es. Marrison Assistant) chiama ancora
`/wp-json/marrison-commander/v1/chat` (senza `license_key`), la modalità
**legacy** (`config('legacy.enabled')`, default `true`) consente la richiesta
se il sito è già registrato e `status = active`, contabilizzando comunque
quota, token e costi nella dashboard. Disattivabile in `config/config.php`:

```php
'legacy' => ['enabled' => false],
```

## 7. Esempi curl

### Ping

```bash
curl https://miosito.it/commander/api/ping
```

### Activate (associare una licenza a un sito)

```bash
curl -X POST https://miosito.it/commander/api/activate \
  -H "Content-Type: application/json" \
  -d '{"license_key":"MC-XXXX-XXXX-XXXX-XXXX","site_url":"https://cliente.it","plugin_slug":"marrison-assistant"}'
```

### Validate

```bash
curl -X POST https://miosito.it/commander/api/validate \
  -H "Content-Type: application/json" \
  -d '{"license_key":"MC-XXXX-XXXX-XXXX-XXXX","site_url":"https://cliente.it","plugin_slug":"marrison-assistant"}'
```

### Chat

```bash
curl -X POST https://miosito.it/commander/api/chat \
  -H "Content-Type: application/json" \
  -d '{"site_url":"https://cliente.it","prompt":"Ciao, chi sei?","license_key":"MC-XXXX-XXXX-XXXX-XXXX"}'
```

### Chat con contesto cacheabile

Il plugin puo inviare il prompt in due blocchi: `static_context` contiene la
knowledge base del sito scansionata, mentre `dynamic_context` contiene storico e
messaggio utente. Commander decide se creare/riusare una cache Gemini esplicita
e passa a Gemini solo `cachedContent`, senza esporre questi dettagli al plugin.
Le regole fisse vanno in `system_instruction`.

```bash
curl -X POST https://miosito.it/commander/api/chat \
  -H "Content-Type: application/json" \
  -d '{
    "site_url":"https://cliente.it",
    "license_key":"MC-XXXX-XXXX-XXXX-XXXX",
    "system_instruction":"Rispondi come assistente del sito.",
    "static_context":"Knowledge base scansionata del sito...",
    "dynamic_context":"Utente: avete spedizione gratuita?",
    "call_type":"chat"
  }'
```

Per invalidare manualmente la cache dopo un rescan:

```bash
curl -X POST https://miosito.it/commander/api/context-cache/invalidate \
  -H "Content-Type: application/json" \
  -d '{"site_url":"https://cliente.it","license_key":"MC-XXXX-XXXX-XXXX-XXXX","plugin_slug":"marrison-assistant"}'
```

In alternativa una chiamata chat puo includere `"invalidate_context_cache":true`.
Per la classificazione business usare `"call_type":"config_scan"`: Commander usa
di default `gemini-2.5-flash-lite`, temperatura `0.25`, output massimo `320` e
risposta JSON.

### Log token

```bash
curl -X POST https://miosito.it/commander/api/log-token \
  -H "Content-Type: application/json" \
  -d '{"site_url":"https://cliente.it","log_data":{"intent":"general","prompt_bytes":120,"prompt_tokens_real":30,"output_tokens":80,"total_tokens":110}}'
```

### White label

```bash
curl "https://miosito.it/commander/api/white-label?site_url=https://cliente.it"
```

### Webhook WooCommerce (test manuale)

```bash
curl -X POST https://miosito.it/commander/api/webhooks/woocommerce \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Secret: IL-TUO-SECRET" \
  -d '{
    "id": "12345",
    "status": "completed",
    "billing": {"email":"cliente@example.com","first_name":"Mario","last_name":"Rossi"},
    "line_items": [{"product_id":"123","name":"Marrison Assistant","quantity":1}]
  }'
```

### Alias legacy (compatibili col vecchio plugin WordPress)

```bash
curl -X POST https://miosito.it/commander/wp-json/marrison-commander/v1/chat \
  -H "Content-Type: application/json" \
  -d '{"site_url":"https://cliente.it","prompt":"Ciao"}'
```

## 8. Sicurezza

- Password admin hashate con `password_hash` (bcrypt di default).
- Sessioni con cookie `httponly`, `secure` (se HTTPS) e `samesite=Lax`.
- CSRF token su tutti i form del pannello admin.
- Prepared statement PDO su tutte le query.
- Rate limit configurabile per IP su `/api/activate`, `/api/validate`,
  `/api/chat` (tabella `rate_limit_hits`).
- Webhook WooCommerce protetto da secret condiviso (con verifica HMAC se
  disponibile l'header `X-Wc-Webhook-Signature`).
- API key Gemini mai mostrate per intero in dashboard (mascherate, es.
  `AIzaSyXX••••ab12`).
- CORS configurabile in `config/config.php` (`cors.allowed_origins`).

## 9. Note MVP

Questa è una versione MVP/beta pensata per essere semplice, installabile e
comprensibile. Non è pensata come architettura enterprise: nessuna coda
asincrona, nessun ORM, nessun framework pesante — solo PDO, un router leggero
e classi PHP semplici organizzate in stile MVC.


## Rewrite AI — Marrison Optimizer 0.8.1

Commander 1.5.3 supporta `call_type`/`intent=ai_elementor_rewrite` su `api/chat`
con `plugin_slug=marrison-optimizer`, prompt entro 16 KB, `stream=false` e
`expected_model` uguale al modello Gemini Flash globale. Il server costruisce
lo schema JSON `segments` (1–24 stringhe). Immagini, campi/schema client,
contesto statico, cache e streaming non sono ammessi. Nessun retry del trasporto.
Ogni generazione e correzione lunghezza passa dal conteggio esistente di token,
ragionamento e costi con intento `ai_elementor_rewrite`, anche per risposte
incomplete con consumi. Il modello comunicato dal provider è usato nelle analytics.

La stessa richiesta con `operation=configuration` e prompt vuoto restituisce
`success`, `model`, `rewrite_schema=true`, senza chiamare Gemini o prenotare quota.
I controlli sito/licenza restano attivi; un modello cambiato viene respinto prima
della quota. Nessuna migrazione database. Aggiornare Commander prima di Optimizer.
Verifica: `tests/rewrite-commander-contract.php` in Marrison Optimizer, con
controller/servizi reali e rete/database simulati.

## Yoast AI Autofill — Marrison Optimizer 0.6.0

Commander 1.5.2 supporta `call_type`/`intent` `ai_yoast_seo` su `api/chat`, con
`plugin_slug=marrison-optimizer`, prompt testuale entro 16 KB, `stream=false`,
`output_fields` non vuoto scelto tra `seo_title`, `meta_description`,
`focus_keyphrase` e `expected_model` uguale al modello Gemini Flash globale.
Non sono ammessi immagine, cache/contesto statico, istruzioni di sistema o
schema JSON definito dal client. Il server costruisce uno schema delle sole
stringhe richieste. Errori di payload/modello sono respinti prima della quota.
Chiave e modello restano globali; i consumi effettivi vengono registrati come
`ai_yoast_seo`, una volta per generazione. Nessun retry automatico di queste
richieste e nessuna modifica ai dati WordPress da parte di Commander.

La stessa richiesta con `operation=configuration`, senza prompt o campi,
restituisce `success`, `model`, `seo_schema=true`, senza chiave API, chiamata
Gemini, prenotazione di quota o log token. Usa gli stessi controlli di sito e
licenza della chat. Il plugin verifica questa risposta prima di confermare
la generazione e non assume un modello locale. Nessuna migrazione database.

Verifica con `tests/yoast-commander-contract.php` nella cartella originale di
Marrison Optimizer: controller/servizi reali, rete e database simulati.
