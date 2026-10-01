# Piano remediation mirato — media/cache/cookie

Solo proposte: nessun file di produzione o test originale è stato modificato.

## Image Sizes

- `includes/modules/class-marrison-addon-image-sizes.php:749-758,1122-1125`: quando una size configurata non esiste in `$metadata['sizes']`, `generate_single_size()` crea il file ma `add_modern_source_to_metadata()` lo scarta perché richiede già la chiave metadata. Patch minima: dopo la generazione, creare/registrare la voce size con file, width, height, crop e mime restituiti dall'editor prima di aggiungere `sources`; in alternativa saltare la conversione moderna quando la size base non è registrabile. Preferibile la prima, con dimensioni lette dal risultato `save()` e fallback ai dati configurati. Aggiungere test fixture metadata senza slug e verificare file + metadata + downsize.

## Local Google Fonts

- `tests/local-google-fonts-parser-test.php:90`: il test usa `__DIR__ . '/../includes/...'`, ma il checkout ha plugin annidato in `marrison-addon/includes/...`; il clone temporaneo corretto passa pulitamente. Patch minima del test: usare lo stesso path già usato dal test Image Sizes (`../marrison-addon/includes/...`) oppure una costante root condivisa. Non cambiare il modulo.
- `includes/modules/class-marrison-addon-local-google-fonts.php:1797-1825,1854-1859`: flusso download già conserva il manifest attivo precedente in caso di errore; mantenere questo comportamento. Aggiungere solo test con `pre_http_request` che restituisce errore e verificare che `active` precedente resti invariato e che nessun file parziale venga considerato variante.
- `2157-2412`: parser CSS2 per assi `ital,wght` e intervalli verificato in LocalWP (18 varianti da 100..900 normal/italic); nessuna patch funzionale proposta.

## Browser Cache

- Il sito LocalWP usa Nginx e l'header `no-cache` osservato è coerente con configurazione manuale non applicata. La generazione Nginx e la diagnostica non mostrano un bug confermato; validare solo dopo inserimento configurazione nel server.

## Video Thumbnail

- `includes/modules/class-marrison-addon-video-thumbnail.php`: il percorso download/cleanup è già protetto da `wp_safe_remote_get()` dentro `download_url()` del core e da `file_is_valid_image()`. Non proporre una patch SSRF senza una riproduzione diversa; resta utile un test di errore HTTP che confermi rimozione del temporaneo.

## Dynamic SVG

- `includes/modules/class-marrison-addon-dynamic-svg.php:211-240`: cache correttamente separata per modalità/path/size/mtime. Rischio teorico di contenuto mutato con stesso size/mtime; nessuna patch minima senza introdurre hash o invalidazione più costosa. Non classificato come bug confermato.

## Custom Cursor

- `includes/modules/class-marrison-addon-cursor.php:167-173`: `hover_color` accetta solo `rgb/rgba`, mentre il controllo colore admin e il colore di default consentono valori esadecimali. Patch minima: accettare `sanitize_hex_color()` prima del fallback rgba esistente, preservando anche alpha rgba. Testare `#ff0000`, `rgb(...)`, `rgba(...)` e input invalido.

## Cookie Manager

- `includes/modules/cookie-manager/includes/class-cookie-consent.php:80-83,97-99`: le regex rimuovono `type`/trasformano `src` solo se l'attributo è quotato. Con `type=module` non quotato, il tag bloccato può conservare il tipo eseguibile dopo l'inserimento di `type="text/plain"`; con `src=https://...` non quotato, la sorgente può restare attiva. Patch minima: supportare attributi quotati e non quotati con un gruppo valore comune, rimuovere sempre `type` prima di aggiungere `type="text/plain"`, e trasformare sempre `src` in `data-marrison-blocked-src` usando escaping del valore. Aggiungere fixture per `<script type=module src=...>`, `<script type="module">` e `<iframe src=https://youtube.com/...>` con consenso negato.
