# Cookie Manager 1.3.71 — verifica su CERIS

## Ambito

Motore interno al plugin: nessun servizio cloud, abbonamento, API esterna di scansione o modifica a Commander. Le richieste ai servizi incorporati nel sito continuano a essere quelle delle pagine analizzate.

L'utente ha chiesto di usare soltanto il sito esistente. Nessuna pagina di prova è stata pubblicata e nessun tag Analytics di prova è stato aggiunto. La proposta di una pagina temporanea è stata bloccata dal controllo automatico e poi rifiutata dall'utente. Le policy configurate e i contenuti del sito non sono stati sostituiti.

## Problemi corretti

1. La fase dopo il consenso autorizzava gli script, ma non esercitava il salvataggio nativo della scelta. Ora imposta realmente i cookie firmati del consenso nella sola sessione anonima di scansione, senza aggiungere ricevute di visitatori.
2. La raccolta browser non acquisiva gli header `Set-Cookie` della risposta finale. Ora raccoglie gli header reali alla chiusura della richiesta, inclusi HttpOnly e cookie emessi tardi da altri componenti. I valori sono eliminati prima del salvataggio. Non vengono generate osservazioni da un elenco di nomi attesi.
3. La prima prova reale ha rilevato il caso `do_action('shutdown')`: WordPress passa una stringa vuota quando non ci sono argomenti. La raccolta ora usa `headers_list()` anche in questo caso; il test di regressione riproduce questo argomento.
4. Gli script ritardati potevano essere preceduti dalla conclusione della misura su una pagina momentaneamente inattiva. La finestra minima è ora di quattro secondi, con raccolta limitata a dodici secondi e attesa di quiete delle risorse.
5. La lista delle risorse comprende anche richieste rilevate da Performance API, conservando il percorso necessario al riconoscimento del servizio ed eliminando query e frammenti. Fogli stile, font e richieste non vengono etichettati come elementi script.
6. I servizi Maps, Analytics e YouTube hanno profili documentali separati. I cookie documentati hanno `observed: false` ed `evidence: provider_documentation`; non entrano nel database dei cookie osservati e non ne aumentano il conteggio. La policy mostra le due fonti in sezioni distinte.

Le URL firmate vengono accettate solo come richieste iframe anonime. La navigazione principale e la sessione amministrativa non possono applicare il consenso simulato. L'ordine esistente (tutte le pagine prima del consenso, poi tutte dopo) era già corretto ed è stato mantenuto.

## Prove reali

Il rapporto originale `before-scan.json` identifica 1.3.70 / 20261020.2: 10 pagine campione, 20 verifiche, zero cookie e Maps presente.

La prima prova 1.3.71 / 20261020.4 ha prodotto due cookie leggibili; ha permesso di individuare il difetto dell'argomento dell'hook.

La seconda prova 1.3.71 / 20261020.5 è conservata in `live-second-report.json` e `live-second-run.json`: scansione completata, 10 pagine, 20 verifiche, 20 risposte raccolte, nessun problema e tre cookie osservati:

- `marrison_cookie_state`: HttpOnly, osservato negli header delle risposte dopo il consenso;
- `marrison_cookie_consent`: osservato negli header e dal browser;
- `marrison_cookie_categories`: osservato negli header e dal browser.

Questi sono i cookie di consenso di Marrison, non cookie di terze parti. Maps è riconosciuto nella pagina Contatti. `NID` è documentato dal fornitore e non è stato osservato sul dominio Google: questo rapporto non attesta che Google lo abbia installato. I font esterni sono riconosciuti senza attribuirgli cookie inventati.

La ripetizione finale 1.3.71 / 20261020.6 è conservata in `live-final-report.json` e `live-final-evidence.json`: stesso risultato, 20/20 verifiche, 20 risposte raccolte, tre record senza duplicati e zero problemi. L'hash del motore PHP installato coincide con il file locale. `live-policy.txt` conferma i tre cookie nella policy esistente e Maps nella sezione documentale separata. `scanner-verificato.png` mostra il risultato effettivo nel pannello.

`verify-evidence.py` confronta i due rapporti reali, le osservazioni dopo consenso, HttpOnly, assenza di valori, separazione di NID, policy pubblica e parità del pacchetto con i sorgenti. L'esito è registrato in `verification-results.json`.

## Verifiche locali

Eseguite con PHP CLI 8.5.5 e Node:

- `tests/cookie-engine-php-test.php`: header, HttpOnly, scadenze, dati senza valori, contesto firmato/anonimo, URL e limiti, JSON/DB, campionamento, servizi documentati separati e stringa vuota dell'hook WordPress;
- `tests/cookie-scan-agent-js-test.js`: simulazione JavaScript della lettura/scrittura, prima/dopo consenso, scrittura a tre secondi, storage bloccato, richieste dinamiche e rimozione delle query;
- `tests/cookie-runtime-js-test.js`;
- `tests/cookie-consent-store-php-test.php`;
- `tests/cookie-consent-php-test.php`;
- `tests/cookie-policy-php-test.php`;
- `tests/cookie-shortcodes-php-test.php` con gli hook dell'installazione WordPress locale;
- `tests/cookie-auto-scan-php-test.php`;
- `tests/cookie-update-notice-php-test.php` con WordPress locale;
- `tests/module-lifecycle-php-test.php` con WordPress locale: moduli disattivati senza caricamenti operativi e pulizia degli eventi pianificati;
- lint PHP, sintassi JavaScript e controllo differenze (rispettando le terminazioni CRLF del checkout).

Le simulazioni locali non sono presentate come prove browser su servizi esterni. I precedenti due tentativi dei test che richiedono il percorso WordPress erano stati avviati senza argomento; sono stati rieseguiti correttamente con `C:/Users/Angelo/Local Sites/tesy/app/public` e sono passati.

## Limiti rimasti

Il plugin non può leggere dal documento del sito i cookie degli iframe su domini esterni. Ripetere la scansione non elimina questa restrizione del browser. Il catalogo non sostituisce un'osservazione. Interazioni con moduli, account o servizi non presenti nei campioni restano fuori dalla misura.

Le misure isolate richiedono un browser con supporto iframe credentialless, come Chrome/Edge. In altri browser la raccolta HTTP rimane disponibile e la fase JavaScript segnala la necessità di un browser compatibile.

## Pacchetto

Versione, changelog e readme GitHub/WordPress aggiornati a 1.3.71, autore Angelo Marra. `package-results.json` riporta hash SHA-256 e verifica di integrità/parità dei 99 file del pacchetto. Nessun commit, push o release GitHub è stato creato.
