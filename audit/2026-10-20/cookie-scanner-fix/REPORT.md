# Cookie Manager — correzione scanner 1.3.70

## Problemi confermati dal report allegato

Il report originale di CERIS, versione 1.3.68, documenta 14 pagine HTTP, 10 errori HTTP, 28 verifiche browser senza errori dichiarati e zero cookie. I dieci errori riguardano indirizzi inesistenti costruiti dal segnaposto `itemDataObject.url` del menu JetMenu. Il parser leggeva anche il contenuto degli script template e gli attributi Vue `:href`, trattandoli come collegamenti reali. Questi errori rendevano il risultato `partial`.

L'anteprima alta 500 px era inserita esplicitamente nel pannello per ogni verifica browser: la navigazione visibile era prodotta dal nostro codice.

Il vecchio report non registrava un controllo effettivo dell'accesso ai cookie. Pertanto non consente di stabilire retroattivamente se lo zero fosse una reale assenza o un problema di lettura. Il nuovo motore verifica la lettura/scrittura prima di accettare un risultato e segnala gli accessi bloccati. Non aggiunge cookie presunti per far salire il conteggio.

## Interventi applicati

- Rimosse le anteprime visibili. Le pagine campione vengono ancora caricate per eseguire JavaScript, in una sessione isolata senza mostrare la pagina nel pannello. Il contesto mantiene dimensioni e intersezioni necessarie ai contenuti caricati durante lo scorrimento.
- Esclusi script template, commenti, template HTML, attributi dinamici e segnaposto dalla scoperta dei collegamenti.
- Raggruppate differenze puramente grafiche dei widget Elementor e archivi per data; restano campioni distinti per template, integrazioni e servizi differenti. Fino a tre richieste HTTP brevi per aggiornamento del pannello.
- Verificato l'accesso ai cookie tramite una prova temporanea nella sola sessione di scansione; la prova viene eliminata ed esclusa dall'inventario. Un accesso bloccato produce un errore esplicito.
- Mantenuta la lettura di `document.cookie` anche quando Cookie Store restituisce una lista vuota; l'intercettazione delle scritture preserva le regole di consenso.
- Mostrati gli host delle risorse esterne separatamente dai cookie osservati. Gli URL delle risorse non conservano query o valori identificativi.
- Sostituito il messaggio finale inglese `partial` con una spiegazione italiana e il primo errore concreto. Uno zero verificato viene spiegato senza dichiarare il sito privo di qualsiasi cookie.
- Versione, changelog e README aggiornati alla 1.3.70; autore Angelo Marra; ZIP verificato e installato sul sito di test Hostinger.

## Prova reale sul sito di test

Creata e poi spostata nel cestino una pagina temporanea, con due cookie JavaScript controllati: uno immediato e uno scritto solo quando il contenuto entra nel viewport durante lo scorrimento. Le scritture avvenivano esclusivamente nelle richieste firmate del motore di scansione.

La scansione ha terminato 26/26 verifiche browser, senza errori browser, in 89 secondi complessivi, rilevando 10 cookie: 8 del sito e i 2 della prova. Entrambi i cookie della prova sono stati osservati dopo il consenso, con percorso `/` e durata persistente. Nessun cookie sintetico di verifica è presente nell'inventario.

L'unico errore HTTP rimasto nella prova era la pagina privacy `?page_id=3`, già dichiarata assente intenzionalmente dall'utente. Non è stato interpretato come un nuovo bug del plugin. Il pannello indica esattamente quell'indirizzo invece del solo termine `partial`.

Evidenze: `live-scan.json`, `live-evidence.json`, `scansione-verificata.jpg`. Il report originale dell'utente non è stato sovrascritto.

### Ripristino dei dati di prova

La pagina temporanea è nel cestino. Per eliminare anche i due cookie di prova dall'inventario è stata eseguita una seconda scansione completa, dopo la rimozione della pagina. Solo durante questa scansione di pulizia l'indirizzo privacy è stato impostato su un URL esistente del sito, permettendo al motore di confermare una copertura completa e rimuovere le vecchie osservazioni. Al termine è stato ripristinato e salvato l'indirizzo originale `?page_id=3`; non è stata creata una pagina privacy.

L'inventario finale contiene gli 8 cookie del sito, senza `marrison_fixture_js`, `marrison_fixture_lazy` o il cookie di verifica. La seconda scansione ha concluso 24/24 verifiche su 12 pagine, senza errori, nella configurazione temporaneamente valida descritta sopra. Questo esito non implica che la pagina privacy originale sia stata riparata. Evidenza: `cleanup-evidence.json`.

## File della correzione

- `assets/js/scanner.js`: interfaccia, messaggi e contesto invisibile di scansione.
- `assets/js/scan-agent.js` e `assets/js/consent-runtime.js`: verifica accesso, lettura e osservazioni dei cookie nella sessione isolata.
- `includes/class-scan-engine.php` e `includes/class-scan-sampler.php`: collegamenti reali, campionamento, risorse esterne e validazione risultati.
- `templates/admin-settings.php`: descrizione e risultati della scansione.
- `marrison-addon.php`, README del repository/plugin, `readme.txt` e ZIP: versione e documentazione 1.3.70.

I percorsi dei file funzionali sono relativi a `marrison-addon/includes/modules/cookie-manager/`, salvo quelli di rilascio.

## Verifiche locali

Passati i test PHP del motore, consenso, avviso dopo aggiornamento e ciclo di attivazione/disattivazione dei 24 moduli; passati i test JavaScript del runtime e dell'agente. Il test del motore riproduce i falsi collegamenti JetMenu e verifica campionamento, accesso bloccato, risorse esterne, inventario realmente vuoto, privacy e autorizzazioni. Il test dell'agente verifica anche il fallback da Cookie Store vuoto.

Controlli sintattici PHP/JS e `git diff --check` superati. ZIP: 98 file, controllo CRC e corrispondenza byte per byte con i sorgenti; risultati e SHA-256 in `package-results.json`.

Il server di fixture locale è stato arrestato dopo che il browser non riusciva a raggiungerlo. Le prove browser qui dichiarate sono quelle eseguite sul vero sito di test, non una simulazione locale presentata come verifica del sito.

## Limite della verifica

La 1.3.70 è stata installata e verificata sul sito Hostinger autorizzato. CERIS è stato consultato soltanto sulle pagine pubbliche: non è stato aggiornato né è stata eseguita una nuova scansione autenticata su quel dominio. Il suo risultato dopo aggiornamento resta da verificare.
