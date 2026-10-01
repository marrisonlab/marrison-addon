# Verifica finale runtime isolata

Data: 2026-10-01. Harness: `wp-runtime-probe.php`, PHP LocalWP 8.2.29, database in transazione con rollback allo shutdown.

- 21 moduli eseguiti singolarmente: tutti hanno caricato esclusivamente il modulo richiesto, con `AUDIT_JSON.errors = 0`.
- Modalità `all-off`: completata con `errors = 0`.
- Dipendenze assenti: `no-elementor`, `no-jetengine` e `no-woocommerce` completate con `errors = 0`.
- Tutti i record risultano `public = true` nel probe; nessuna scrittura persistente è stata lasciata.
- `cleanliness.php`: `PASS no temporary WordPress records remain`.

Gli stdout completi sono nei file `.txt` della directory. Ogni esecuzione contiene l’avviso di startup LocalWP relativo a `php_imagick.dll` mancante; è un warning dell’ambiente PHP e non un errore del plugin.

Il conteggio esatto è 21 moduli + `all-off` + 3 scenari di dipendenza, per 25 esecuzioni, come riportato in `summary.json`.

## Verifica release corrente

Un inventory eseguito dopo l’installazione della 1.3.44 riporta `addon: 1.3.44`, tutti i moduli abilitati/caricati e `errors: []`. Il probe Ticker della stessa release riporta `errors: []`; i relativi stdout sono `inventory-1.3.44.txt` e `ticker-1.3.44.txt`.
