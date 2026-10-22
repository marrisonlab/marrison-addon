# Integrazione Cookie Manager → Commander → Browserless

Pacchetti: Commander scanner 1.5.13 e Marrison Addon 1.3.72.
Il caricamento della patch Commander è stato confermato dall'utente. Dopo il
nuovo accesso è stato attivato il servizio: endpoint pubblico HTTP 200,
`enabled: true`; prova falsa respinta con HTTP 403 prima di usare Browserless.
Addon 1.3.72 è stato installato su CERIS e la scansione completa è conclusa:
10 campioni HTTP, 20 verifiche, 10 richieste remote, consenso confermato su ogni
pagina e zero errori. Provenienza di tutti i cookie osservati: `remote_browser`.
Tre cookie Marrison, incluso HttpOnly; nessun cookie esterno osservato in questa
visita. I file PHP installati coincidono per SHA256 con i sorgenti locali.

## Modifiche

- Commander: endpoint pubblico con prova automatica della scansione WordPress
  attiva, senza licenze o registrazione siti. Codice browser fisso sul server;
  non accetta script inviati dai client. Chiave cifrata nel vault già presente,
  campo amministrativo vuoto dopo il salvataggio, mai distribuita a WordPress.
- Browserless: archivio cookie dell'intero contesto, inclusi domini esterni e
  HttpOnly; prima/dopo il consenso effettivo, scroll e avvio dei video YouTube
  incorporati. Restituisce metadati e risorse, esclude i valori dei cookie.
- Limiti: 45 secondi/pagina, una richiesta attiva, due unità riservate/pagina,
  budget mensile iniziale 900, 100 pagine/giorno/dominio e 200/IP. Nessun proxy
  o CAPTCHA abilitato. Retry pagina/job entro un'ora servito dalla cache.
- Cookie Manager: selezione remoto/locale, verifica iniziale disponibilità,
  prove temporanee, richieste server-to-server senza login WordPress, importazione
  delle osservazioni esterne, errori espliciti, ripresa e annullamento sicuro.
  Catalogo documentato sempre distinto dalle osservazioni reali.
- Modulo disabilitato: nessuna classe operativa, hook REST, cron o asset scanner.
- Versione, changelog, README GitHub e WordPress aggiornati; servizi esterni
  dichiarati. Minimo PHP 7.4/WP 6.0 preservato. Nessuna migrazione Commander.

## Prove

1. API Browserless reale: HTTP 200 su example.com.
2. Motore reale sulla sola pagina esistente CERIS `/contatti/`: HTTP 200,
   18.7 secondi, consenso confermato, Google Maps e relative risorse caricati,
   tre cookie Marrison osservati (incluso quello HttpOnly), nessun errore.
   **Cookie esterni realmente osservati in questa visita: zero.** Questo dato
   non viene sostituito con nomi del catalogo. Non è una prova live YouTube.
3. Test PHP engine: flussi precedenti e nuovo contratto remoto, preflight,
   prova sito, timeout/ripresa, cookie esterno HttpOnly simulato, dominio non
   associato respinto, nessun valore/prova privata esportato, annullamento
   durante la chiamata. PASS.
4. Test PHP Commander: HTTP/DNS/Settings simulati, IP privati, TLS/DNS pinning,
   redirect bloccati, prova sito, cache retry, budget, servizio spento e
   assenza valori/token nei file. PASS nel pacchetto e nel repository Commander.
5. Test JavaScript VM: contesto con cookie esterni HttpOnly simulati,
   consenso prima/dopo, avvio YouTube simulato, valori/query esclusi,
   chiusura pagina e risorse private bloccate. PASS. Non è un browser live.
6. Regressioni consenso, policy, agente locale, scansione automatica, avviso
   aggiornamento e isolamento 24 moduli spenti: PASS. PHP lint/JS syntax e
   diff-check dei file interessati: PASS.
7. Entrambi gli ZIP: CRC, parità byte sorgenti, ricerca in memoria della chiave
   reale per escluderla da ogni membro; nessun segreto presente. README WordPress
   9.766 byte. Repository Commander locale uguale alla patch consegnata.

La CLI PHP Windows non include cURL: il test provider usa cURL simulato;
la chiamata reale al fornitore usa Python. La prima directory temporanea del
test Commander ha incontrato una limitazione rename del sandbox Windows;
il test ora usa la propria directory sotto tests e la rimuove al termine.

## Limiti

Le condizioni del servizio esterno, il consenso Google/YouTube, la regione, le
interazioni non eseguite e il blocco cookie del browser possono cambiare il
risultato. Non si compilano moduli e non si effettuano login. L'API DNS pubblica
è una dipendenza del browser worker. Il budget locale non misura altri consumi
dello stesso account Browserless. Un servizio pubblico ha quota condivisa.
Nessuna nuova pagina, tag Analytics o fixture è stata pubblicata su CERIS.

## Controllo operativo

Attivazione Commander, installazione Addon e verifica end-to-end completate.
Rapporto: `ceris-remote-final-report.json`; screenshot: `ceris-remoto-completato.png`
e `commander-attivo.png`. Nessuna release GitHub, tag o push eseguito.
