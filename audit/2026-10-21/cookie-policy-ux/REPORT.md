# Cookie Manager 1.3.74 — policy e interfaccia

Data: 21 ottobre 2026. Aggiornamento installato su **demo.ceris-srl.it**. ZIP aggiornato nella radice del repository. Nessuna pagina o tag aggiunto ai siti online; nessun aggiornamento installato sul sito Hostinger.

## Interventi richiesti

1. **Pagine eliminate e cestino.** Il campionatore esclude contenuti WordPress non pubblicati o protetti anche quando indicati come policy. Le risposte 404/410 dei percorsi rimossi vengono registrate in `skipped_missing` e non rendono incompleta una scansione altrimenti conclusa. La homepage non viene esclusa da questa regola. Timeout, errori di rete, HTTP 5xx e limiti reali restano problemi di copertura. Prove locali: policy nel cestino, collegamenti eliminati 404/410, nessuna visita browser agli URL mancanti e stato finale completato. Nessun contenuto del sito Hostinger modificato per riprodurre il problema.

2. **Shortcode senza titoli HTML.** Privacy Policy, Cookie Policy e inventario dinamico usano paragrafi, grassetti e liste; nessun H1–H6 nei contenuti generati. Rimangono le tabelle accessibili per l’inventario dei cookie. I titoli propri delle pagine WordPress/Elementor non vengono modificati. Verificato in PHP, nel browser locale e nella Cookie Policy pubblicata su CERIS.

3. **Generatore privacy.** La scheda “Privacy e policy” sostituisce le textarea dei testi informativi con campi strutturati: titolare, sede/Paese, email privacy, partita IVA/codice fiscale, telefono, rappresentante UE e DPO se nominati; moduli, dati raccolti, base giuridica e conservazione; log e relativa conservazione; newsletter facoltativa; fornitori/destinatari; trasferimenti e garanzie; decisioni automatizzate e altri trattamenti effettivi. I campi essenziali mancanti sono segnalati e lo shortcode non presenta come completa una privacy incompleta. Il wizard ha un nuovo passaggio 5 facoltativo con **Salta**; gli shortcode passano al punto 6. Il salto non sovrascrive il profilo. Nessun dato del sito di esempio è stato precompilato nei siti client. La Cookie Policy viene generata e arricchita automaticamente, includendo inventario e archivi browser osservati.

4. **Shortcode duplicati.** Rimossi i controlli dalla scheda Banner; conservati nella scheda Shortcode e nel wizard. Gli URL delle pagine rimangono nella scheda Banner. Le pagine restano create e gestite dall’utente.

5. **Fine scansione.** La barra di avanzamento è nascosta negli stati terminali; la conclusione resta evidenziata in verde. Stati incompleti/interrotti restano distinti. Il messaggio finale viene mostrato anche riaprendo il pannello. Ridotte le spiegazioni principali; copertura e dettagli tecnici restano nel rapporto espandibile/esportabile.

6. **Barra consenso.** Rimossi X, testo di chiusura, relativo binding e spazio laterale riservato. Restano Accetta tutti, Rifiuta tutti e Personalizza. Verifica reale su CERIS senza overflow; verifica locale a viewport 375 px con i tre pulsanti affiancati, testo a capo e nessun controllo troncato. Non ridotte le dimensioni dei pulsanti mobili già previste.

7. **Permissions-Policy: probabile origine nell’estensione adblock, confronto in Chrome ancora necessario.** Nel codice Addon non sono presenti le quattro direttive indicate. Le risposte HTTP pubbliche delle homepage CERIS e Hostinger e la risposta finale dell’iframe Maps osservato non contengono `Permissions-Policy` nella prova effettuata; il browser disponibile non ha riportato quei messaggi, prima o dopo l’attivazione della mappa. Le [regole privacy ufficiali uBlock](https://github.com/uBlockOrigin/uAssets/blob/master/filters/privacy.txt) aggiungono proprio `attribution-reporting`, `private-aggregation`, `join-ad-interest-group` e `run-ad-auction` agli header. L’utente conferma un’estensione “adblock” attiva in Chrome: è quindi concreta l’ipotesi di direttive aggiunte localmente dall’estensione, pur non essendo identificata la sua esatta implementazione. La conferma definitiva è il confronto della stessa pagina con un profilo Chrome privo di estensioni. Non applicati filtri della console o modifiche arbitrarie agli header del sito. Prove: `headers.json`, `check-headers.py`.

8. **Ricevute leggibili.** Tabella con data/ora, scelta tradotta, categorie, scadenza e integrità della ricevuta/informativa. Identificativi e revisione sono raccolti nei dettagli della singola ricevuta; nessuna stampa JSON nella vista ordinaria. Rimane un’esportazione JSON esplicitamente indicata come tecnica. Verificati ricevute precedenti e nuovo rifiuto su CERIS: firme e informative risultano integre.

## Fonti e impostazione dei testi

La [policy Marrisonlab](https://www.marrisonlab.com/privacy-policy/) è stata letta come riferimento di struttura. Identità e contatti non sono copiati nei modelli dei siti. Aggiunte conservazione, trasferimenti, natura del conferimento, reclami e trattamenti condizionali facendo riferimento all’[articolo 13 GDPR](https://eur-lex.europa.eu/legal-content/EN/TXT/?qid=1526560528451&uri=CELEX%3A32016R0679) e alle [indicazioni del Garante](https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/8981258). Il titolare deve inserire dati e configurazione effettivi; il generatore non certifica la conformità di trattamenti non dichiarati.

Per gli avvisi del punto 7: [stato ufficiale Privacy Sandbox](https://privacysandbox.google.com/overview/status), aggiornato il 14 agosto 2026, [note Chrome 144](https://developer.chrome.com/release-notes/144?hl=en) e regole ufficiali uBlock collegate sopra. La dismissione delle API e la capacità delle estensioni di aggiungere quelle direttive sono documentate; l’attribuzione al profilo Chrome dell’utente richiede il confronto descritto.

## Verifiche

- **33/33 controlli locali mirati superati**, inclusi sintassi PHP/JS, crawler, firme, nonce e permessi, persistenza/sanitizzazione del profilo, passaggio privacy skippabile, assenza di heading, ricevute leggibili e integrità degli archivi, stati finali dello scanner, cron e pulizia. Il test di ciclo di vita conferma i 24 moduli disattivati senza hook/asset operativi aggiunti. Esiti: `local-verification.json`.
- **Prova CERIS reale:** 10 campioni, 20/20 verifiche, 3 cookie osservati, 1 voce storage, 0 problemi; worker terminato. Una prima interruzione di collegamento a Commander è stata mostrata correttamente; la ripresa ha portato il lavoro al termine. Non è stato salvato un falso completamento al primo errore. Esportazioni prima/dopo: `ceris-scan-attempt.json`, `ceris-scan-completed.json`.
- Hash del motore PHP installato uguale al sorgente locale. Barra nascosta e riepilogo verde verificati dopo completamento. Campi privacy e pulsante Salta verificati nel wizard reale. I dati aziendali del profilo CERIS sono rimasti vuoti: non inseriti dati fittizi online.
- ZIP: 100 file, CRC e corrispondenza sorgenti verificati. Versione, changelog e README aggiornati; readme WordPress sotto 10.000 byte. Autore Angelo Marra. `git diff --check` superato; gli avvisi Git sui fine-riga CRLF non sono errori del pacchetto.

## File coinvolti

Solo Cookie Manager: motore/campionatore, generatore policy, impostazioni e wizard, template privacy condiviso, output policy/banner e JS/CSS associati. Aggiornati i test pertinenti, i metadati versione, README GitHub/WordPress e lo ZIP. Nessuna modifica funzionale a Commander o ad altri moduli.

## Prove visive

- `privacy-settings-live.png`, `privacy-wizard-live.png`
- `scanner-completed-live.png`, `receipts-live.png`
- `banner-live.png`, `banner-mobile-375-fixture.png`, `cookie-policy-live.png`

Il test mobile e il profilo completo di prova sono locali. Le schermate con suffisso `live` provengono da CERIS. Resta aperta solo l’attribuzione puntuale degli avvisi del punto 7 nel Chrome dell’utente.
