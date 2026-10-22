# Cookie Manager: scansione in background e inventario del browser

Versioni: Marrison Addon **1.3.73**, patch scanner Commander **1.5.14**.
Data: 21 ottobre 2026.

## Correzioni

- La scansione remota è eseguita da richieste interne firmate del server WordPress. La pagina amministrativa legge lo stato e non guida più le singole visite. Lavoro, avanzamento e risultati sono persistenti; una nuova scheda recupera il lavoro esistente.
- Lotti limitati, lease persistenti, annullamento e ripresa, controllo WP-Cron di recupero. Una chiamata accettata non viene considerata una scansione conclusa. Gli errori restano visibili e il risultato non diventa completo in caso di fallimento.
- Avviso esplicito nelle impostazioni e nel wizard: dopo l'avvio remoto si può tornare alla bacheca, proseguire nel wizard o chiudere la pagina. Avviso anche nella bacheca dell'amministratore che ha avviato il lavoro.
- Inventario separato di localStorage e sessionStorage, anche nei frame esterni accessibili al browser remoto. Sono raccolti solo nome, tipo, origine e prove di osservazione; i valori non vengono letti né trasmessi. Queste voci non aumentano il conteggio dei cookie.
- Aggiornati versione, changelog, README GitHub e readme WordPress. Autore: Angelo Marra. I requisiti minimi rimangono PHP 7.4 e WordPress 6.0.

## Perché mancavano le voci dello screenshot

La singola prova autorizzata sulla homepage pubblica di urbevigilanza.it ha osservato realmente:

| Chiave | Archivio |
|---|---|
| elementor | localStorage |
| elementor | sessionStorage |
| wpEmojiSettingsSupports | sessionStorage |

La stessa visita ha osservato cinque cookie: i tre Marrison e due Google Analytics. Cookie e Web Storage sono archivi diversi: il vecchio risultato dei soli cookie escludeva queste chiavi. La nuova versione mostra entrambi gli inventari separatamente.

Prova salvata in `urbe-storage-observations.json`: HTTP 200, circa 15 secondi, nessun problema riportato. Nessuna pagina o tag è stato modificato. Nessun aggiornamento è stato installato su URBE.

## Verifiche locali

- Motore PHP: osservazioni remote e HttpOnly, importazione storage senza valori, validazione origine, mancata duplicazione nel conteggio cookie, scadenze, timeout/ripresa, annullamento e risposte tardive.
- Background PHP: intera catena completata senza polling della pagina né sessione amministrativa; firma errata rifiutata; lease concorrenti e scaduti; recupero cron; errore loopback e ripresa; nessun token worker persistito; nessun lavoro dopo il termine.
- Interfaccia JS: avvio remoto senza reload/sessionStorage, prosecuzione wizard, solo letture dello stato, recupero in nuova scheda, aggiornamento inventario al termine, ripresa dopo errore.
- Funzione browser remota: cookie esterni/HttpOnly, fasi consenso, storage nei frame, assenza valori, chiusura del browser e blocco risorse private.
- Ciclo di vita: tutti i 24 moduli disattivati superano il controllo; Cookie Manager disattivato non registra i nuovi worker né carica gli asset operativi. Verificati anche scansione automatica e avviso dopo aggiornamento.
- Sintassi PHP/JS, `git diff --check`, CRC degli ZIP e corrispondenza byte per byte dei file distribuiti con i sorgenti: superati.

I test automatici del background e del browser usano fixture; la prova distribuita su CERIS è riportata separatamente qui sotto.

## Verifica distribuita CERIS

Addon 1.3.73 installato, revisione motore 20261021.1. Hash SHA256 del motore PHP e dello scanner distribuiti identici ai sorgenti locali. Commander restituisce l'inventario storage, incluso wpEmojiSettingsSupports.

La scansione è stata avviata dalle impostazioni alle **05:27:06 UTC**; la pagina è stata lasciata per la bacheca, dove compariva l'avviso background. Al ritorno il lavoro era avanzato. Il wizard è passato dal punto 3 al punto 4 durante la scansione, senza attendere il risultato.

La scheda CERIS è stata chiusa completamente alle **05:27:56 UTC** con **2/20** verifiche concluse. Il rapporto persistente registra il completamento alle **05:30:31 UTC**, prima dell'apertura della nuova scheda alle **05:31:01 UTC**. Il lavoro è quindi terminato mentre la pagina non era aperta, senza polling amministrativo.

Risultato: **10 pagine campione, 20/20 verifiche, 3 cookie osservati, 1 chiave sessionStorage, 0 problemi**. Worker terminato, lease azzerato, nessun errore. Sono distinti i tre cookie reali e il cookie Google Maps documentato dal fornitore; quest'ultimo non viene contato tra le osservazioni. Il risultato esistente è stato recuperato nella nuova scheda senza marker sessionStorage.

Prove: `ceris-background-final.json`, `ceris-background-timeline.json`, `live-verification.json` e screenshot `ceris-dashboard-background.png`, `ceris-wizard-background.png`, `ceris-wizard-next-during-scan.png`, `ceris-background-final.png`. La verifica ripetibile locale è `python audit/2026-10-21/cookie-background/verify-live-report.py`.

Al termine della prova il wizard è stato nuovamente completato con testi, colori, categorie e URL già presenti, senza creare pagine o tag. La scheda viene lasciata sul risultato della scansione.

## File operativi coinvolti

- Addon: `includes/modules/cookie-manager/includes/class-scan-engine.php`, `assets/js/scanner.js`, `templates/admin-settings.php`, `templates/setup-wizard.php`.
- Commander: `app/Services/cookie-scan.js`.
- Documentazione e metadati delle due versioni; test del motore e nuova verifica dell'interfaccia background.

## Limiti pratici

Il background qui descritto riguarda il metodo remoto tramite Commander. Il metodo locale mantiene i limiti del browser amministrativo e richiede la pagina aperta, come indicato nell'interfaccia. Il server WordPress deve permettere le proprie richieste loopback; il controllo WP-Cron è una rete di recupero, non la catena principale del lavoro.

La conclusione riguarda i campioni selezionati e le condizioni realmente provate. Nessuna singola scansione dimostra la presenza di tutti i cookie possibili: consenso, interazioni, geografia e decisioni dei servizi esterni possono cambiare il risultato. Le voci documentate dai fornitori restano distinte da quelle osservate.

ZIP Addon: `../../../marrison-addon.zip`. Patch Commander: `commander-cookie-storage-1.5.14.zip`. Integrità e hash: `package-results.json` e `commander-package-results.json`.
