# Cookie Manager: correzione barra — 1.3.69

## Difetto riprodotto sul sito di test

Nel formato Barra, il contenitore desktop usava flex senza ritorno a capo. Il messaggio di stato vuoto, con larghezza 100%, occupava spazio e riduceva a zero la larghezza disponibile al testo. L'avvertenza sulla chiusura non occupava una riga completa e aggravava l'impaginazione.

Alla larghezza originale dello screenshot (2514 px), prima della correzione la barra misurava 547,89 px in altezza e il testo aveva larghezza 0. Dopo la correzione: altezza 98,64 px e testo largo 624,81 px.

## Modifica

La modifica funzionale riguarda `marrison-addon/includes/modules/cookie-manager/assets/css/frontend.css`: griglia desktop con spazio esplicito per testo e azioni, stato vuoto nascosto, avvertenza su riga completa, testo mobile senza troncamento e contenimento dell'overflow. Restano i colori e i testi configurati dal sito.

Versione, README GitHub, README del plugin, readme WordPress e changelog aggiornati alla 1.3.69. ZIP ricostruito e installato tramite l'aggiornamento nativo WordPress sul sito di test.

## Verifiche reali nel browser

- Barra: 2514, 1440, 1280, 1025, 1024, 769, 768, 390 e 320 px.
- Box: 2514, 1280, 1024, 768, 390 e 320 px.
- Nei casi provati: testo leggibile e non troncato, nessun overflow orizzontale della pagina o del banner, pulsanti contenuti e senza sovrapposizioni.
- A 320 px: finestra Personalizza contenuta nel viewport; Escape chiude e restituisce il focus; Rifiuta tutti mantiene il banner nascosto anche dopo il ricaricamento.
- Ripristinato il formato Box originario del sito, con i colori originari. Rimosse le dimensioni simulate del browser al termine delle prove.

Evidenze: `before-2514.json`, `bar-responsive.json`, `box-responsive.json`, `modal-mobile.json`, `bar-1280.jpg`, `barra-mobile-390.jpg` e schermate delle altre larghezze nella stessa cartella.

## Verifiche locali

- PASS: `tests/cookie-policy-php-test.php`, `tests/cookie-consent-php-test.php`, `tests/module-lifecycle-php-test.php` con l'installazione WordPress locale.
- PASS: `git diff --check`.
- ZIP: 98 file, integrità CRC e corrispondenza byte per byte con i sorgenti; metadati e readme verificati. Risultati in `package-results.json`.

Nessun difetto residuo osservato nei casi di layout e interazione elencati. Queste verifiche non costituiscono una nuova analisi completa di tutti i moduli.
