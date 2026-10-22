# Verifica mirata Maps su CERIS

Eseguita su `https://demo.ceris-srl.it/contatti/`, senza modificare il sito,
creare contenuti, installare tag o aggiungere cookie di prova.

Risultato della chiamata Browserless diagnostica: HTTP 200, 16.1 secondi.
Il codice di produzione è stato eseguito con strumentazione aggiuntiva in
questa sola chiamata; lo script diagnostico non è stato distribuito su Commander.

- Google Maps effettivamente caricato nel frame `https://www.google.com/maps/embed`.
- 214 risposte HTTP osservate, con intestazioni lette attraverso Puppeteer.
- Le sole intestazioni Set-Cookie osservate appartengono a CERIS e impostano
  i tre cookie Marrison. Nessuna intestazione Set-Cookie Google osservata.
- 110 eventi CDP responseReceivedExtraInfo, con monitoraggio del target pagina
  e di due target separati. Nessun cookie segnalato come bloccato.
- Una seconda lettura dell'archivio tramite CDP Network.getAllCookies conferma
  gli stessi tre cookie: non risultano cookie esterni persi dal filtro di importazione.
- Consenso Marrison confermato e fase dopo il consenso completata; zero errori.

L'interfaccia live riporta Maps tra i servizi e nella tabella «Cookie documentati
dei servizi rilevati», con NID dichiarato dal fornitore. Non lo include tra i tre
cookie osservati, perché in questa visita non è stato creato. Il risultato non
stabilisce che Maps non utilizzi cookie in altre condizioni.

La precedente prova su dieci campioni dimostra il funzionamento del collegamento
e l'importazione dei cookie di CERIS; non dimostra ancora una rilevazione live di
cookie esterni realmente creati. Le fixture con cookie YouTube esterni e HttpOnly
restano test simulati, non equivalenti a quella prova live.

Prova grezza, senza valori dei cookie: `maps-diagnosis.json`.
