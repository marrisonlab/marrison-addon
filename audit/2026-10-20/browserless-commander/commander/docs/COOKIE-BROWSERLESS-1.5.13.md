# Scanner cookie Browserless — patch 1.5.13

Caricare ed estrarre il contenuto di `commander-cookie-browserless-1.5.13.zip`
nella cartella `/commander/` esistente, sovrapponendo i file. Le directory
`app/`, `public/` e `docs/` dello ZIP devono trovarsi dentro `/commander/`.
Non creare una seconda directory `commander/commander/`.

Non sono incluse configurazione privata, chiavi, database, storage o credenziali.
Nessuna migrazione database. La patch usa bootstrap, Settings, RateLimiter e
MaintenanceSecretVault già presenti in Commander 1.5.12.

1. Aprire `https://www.marrisonlab.com/commander/public/admin/cookie-scanner.php`.
2. Inserire la chiave Browserless nel campo dedicato, attivare il servizio e salvare.
   Il campo rimane vuoto dopo il salvataggio: non rispedisce il segreto al browser.
   La chiave è cifrata con il vault esistente. In alternativa usare BROWSERLESS_TOKEN
   nell'ambiente del server; l'ambiente ha precedenza sul campo salvato.
3. Il controllo pubblico
   `https://www.marrisonlab.com/commander/public/cookie-scan.php`
   deve restituire JSON con `protocol: 1`, `enabled: true`.
4. Installare Marrison Addon 1.3.72 sul sito WordPress. In Cookie Manager > Scanner
   scegliere “Browser remoto tramite Commander” e avviare la scansione.

L'endpoint pubblico non richiede account, licenza o registrazione dei siti. Prima
di usare Browserless verifica una prova temporanea servita dal sito WordPress
per la pagina della scansione attiva avviata da un amministratore. Non è possibile
usare l'endpoint come esecutore di codice arbitrario: il codice è fisso su Commander.

Limiti iniziali: una richiesta attiva, 45 secondi per pagina, due unità riservate
per pagina, 900 unità mensili complessive, 100 pagine/giorno/dominio e 200 per IP.
Il budget locale è prudenziale; le altre applicazioni dello stesso account
Browserless consumano la quota del fornitore. Proxy e CAPTCHA non vengono abilitati.
Un retry della stessa pagina/job entro un'ora usa la cache senza nuova chiamata.
Un errore di rete, quota o consenso non produce un completamento verde.

Il browser parte senza login WordPress, osserva prima del consenso, usa il pulsante
Marrison “Accetta tutti”, conferma il cookie di consenso, ricarica e osserva dopo
il consenso. Scorre per attivare contenuti differiti e tenta l'avvio dei video
YouTube già incorporati. Non compila moduli e non visita nuove pagine create per test.
Legge l'intero archivio del contesto browser, inclusi cookie esterni e HttpOnly.
Non garantisce cookie dipendenti da altre interazioni, geolocalizzazione o accessi.
Le definizioni documentate restano separate dai cookie realmente osservati.

Invia a Browserless gli URL pubblici e usa Google Public DNS per verificare i domini
delle risorse. I valori dei cookie sono esclusi dai risultati e dai file di stato.
I metadati dei risultati sono conservati in `storage/cookie-scanner/` per massimo
un giorno, rimossi progressivamente. I file iniziano con `<?php exit; ?>`.
La chiave Browserless non va nei plugin, nello ZIP o nei log.

Verifica locale: `php tests/cookie-browserless-regression.php` (HTTP/DNS/Settings
simulati). Prova reale prima del deployment: Browserless su `/contatti/` di CERIS,
HTTP 200, 18.7 secondi, consenso confermato, Maps caricato, tre cookie del sito,
nessun cookie esterno effettivamente creato in quella visita. Questa prova non
equivale alla verifica completa WordPress → Commander dopo il caricamento.

Per annullare: disabilitare il servizio nella pagina scanner e selezionare il metodo
locale in Cookie Manager; ripristinare la precedente `public/admin/settings.php`
se si rimuovono i nuovi file. Le altre funzioni Commander non dipendono dal servizio.

Documentazione: https://docs.browserless.io/rest-apis/function
DNS: https://developers.google.com/speed/public-dns/docs/doh/json
