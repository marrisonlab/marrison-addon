# Patch scanner archivio browser 1.5.14

Sovrapporre il contenuto dello ZIP alla directory `/commander/` esistente.
Il solo file operativo aggiornato è `app/Services/cookie-scan.js`; le chiavi e
le impostazioni già salvate restano valide. Nessuna migrazione o nuova credenziale.

Il motore restituisce `storage` con nomi delle chiavi, tipo (localStorage o
sessionStorage), origine e fase prima/dopo consenso. Non legge né invia valori.
Massimo 30 frame, 200 chiavi per tipo/frame e 500 osservazioni per pagina.
Le voci non aumentano il conteggio dei cookie e sono mostrate separatamente da
Marrison Addon 1.3.73. Il vecchio endpoint PHP 1.5.13 inoltra già questi campi.

Il background non richiede un servizio Node o un cron aggiuntivo su Commander:
Addon 1.3.73 invia richieste interne firmate al sito WordPress, una per lotto,
con lease persistenti e controllo di recupero WP-Cron. Il server deve consentire
le richieste loopback del sito; un errore viene mostrato e il lavoro resta salvato.
Nel metodo locale le verifiche iframe richiedono ancora una pagina aperta.

Per tornare indietro basta ripristinare `app/Services/cookie-scan.js` della 1.5.13:
il nuovo Addon segnalerà che l'inventario storage non è disponibile, mantenendo
la scansione cookie remota e il lavoro in background.
