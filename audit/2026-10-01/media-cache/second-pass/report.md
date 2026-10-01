# Second pass — Dynamic SVG e Local Google Fonts

## Dynamic SVG

- La risoluzione attachment/URL resta confinata a SVG reali dentro `wp_upload_dir()` (`class-marrison-addon-dynamic-svg.php:179-208`). URL esterni, traversal, file non SVG e host non locali sono rifiutati. Il callback usa i due hook JetEngine già verificati nel probe runtime.
- La sanitizzazione reale eseguita in LocalWP ha rimosso script, handler evento e URL esterno, conservando `currentColor` e `none`; evidenza nel probe principale.
- La cache distingue modalità, path, dimensione e mtime (`211-240`). Rischio residuo: contenuto modificato mantenendo stesso size/mtime può restare in object cache fino all'invalidazione; non riprodotto con file uploads per il vincolo di non scrivere nel sito. Non classificato come bug confermato.

## Local Google Fonts

- Probe LocalWP su CSS2 `Roboto:ital,wght@0,100..900;1,100..900` ha prodotto 18 varianti (9 normal + 9 italic), quindi l'espansione degli assi e degli intervalli è coerente. URL costruiti per combinazioni normal/italic sono validi CSS2 (`build_google_css_url`, `2239-2293`).
- La copertura parziale/completa è stata verificata nel probe principale: una variante mancante lascia `covered=false`; tutte le varianti presenti danno `covered=true`.
- Il download verifica HTTP 200, corpo non vuoto e firma `wOF2` prima di scrivere (`1919-1952`). Un errore di download accumula `fatal_errors`; `handle_update_from_scan()` salva solo log/errore e mantiene il manifest attivo precedente (`444-470`). Non risultano manifest parziali confermati.
- Elementor local fonts vengono accettati solo se CSS e file WOFF2 esistono e sono leggibili (`1954-1990`); le varianti già coperte vengono escluse dal download (`1699-1715`, `1793-1795`).
- Rischio residuo: la scansione delle directory CSS Elementor usa `glob('*.css')` e verifica dimensioni individuali; non è stato eseguito un test con directory uploads corrotte perché avrebbe scritto nel sito. Nessun bug confermato.

## Evidenze

- `fonts-dynamic-probe-output.json`: parser CSS2 e URL generati.
- `fonts-dynamic-probe-stderr.txt`: warning DLL Imagick del PHP LocalWP, non correlato ai parser.
