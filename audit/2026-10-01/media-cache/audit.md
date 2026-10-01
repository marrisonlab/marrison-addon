# Audit mirato media/cache — 2026-10-01

## Copertura

Moduli esaminati: Image Sizes, Local Google Fonts, Browser Cache, Video Thumbnail, Dynamic SVG; asset JS/CSS direttamente collegati e test PHP disponibili.

Il registry in `marrison-addon/marrison-addon.php` li dichiara tutti `boot => independent`: Image Sizes, Local Google Fonts, Browser Cache, Video Thumbnail; Dynamic SVG è `independent` ma con `requires_jet_engine => true`. Browser Cache carica le tre classi interne solo in admin, mentre le regole server sono applicate fuori dal normale ciclo frontend.

## Verifiche locali

- `php -l` senza errori su tutti i cinque entrypoint; anche sui tre file PHP Browser Cache.
- `node --check` senza errori su `admin-local-google-fonts.js`, `admin-image-sizes.js`, `admin-browser-cache.js`, `includes/modules/video-thumbnail/assets/script.js`.
- `tests/image-sizes-modern-upload-test.php`: PASS.
- `tests/dynamic-svg-php-test.php`: PASS.
- `tests/local-google-fonts-parser-test.php`: BLOCCATO dal path errato nel test: richiede `tests/../includes/modules/class-marrison-addon-local-google-fonts.php`, ma nel checkout il plugin è annidato in `marrison-addon/includes/...`; il file corretto esiste in `marrison-addon/includes/modules/...`. Non è una prova di bug del modulo, ma il test non è eseguibile così com'è.
- Una copia temporanea del test con il path corretto (`media-cache/local-google-fonts-parser-test.php`) termina con `Local Google Fonts parser tests passed.`. Con `-d sys_temp_dir=.../media-cache/php-temp` il pass è pulito; il test originale resta comunque non eseguibile per il path annidato errato.

## Rilievi confermati/statici

1. Video Thumbnail: `ajax_import_thumbnail()` accetta `image_url` direttamente dal POST e lo passa a `download_url()` (`includes/modules/class-marrison-addon-video-thumbnail.php:231-243`). Il controllo applicativo non limita l'URL ai candidati YouTube generati da `ajax_get_thumbnails()`. Tuttavia, nel core WordPress installato `download_url()` usa `wp_safe_remote_get()` (`wp-admin/includes/file.php:1177`), quindi il probe statico non dimostra SSRF verso host privati; resta da valutare il consumo di risorse/URL pubblici arbitrari e l'eventuale policy di allowlist.

2. Image Sizes: `assets/js/admin-image-sizes.js:3` contiene `console.log('Marrison Addon: Image Sizes Script Loaded');` e un commento/debug `alert` disabilitato. È codice diagnostico residuo in asset admin.

3. Image Sizes: `ajax_resolve_background_webp()` è registrata anche come `wp_ajax_nopriv_...` (`class-marrison-addon-image-sizes.php:55`) e non usa nonce/capability. Il payload è limitato a 50 elementi e vincolato a URL uploads, quindi non è una SSRF evidente; resta un endpoint pubblico che può forzare fino a 50 risoluzioni attachment/transient per richiesta. Verificare rate limiting/cache e comportamento con anonimo su LocalWP.

## Per-modulo stato e limiti

- Image Sizes: test moderno upload PASS; conversione reale GD WebP/AVIF con trasparenza PASS, come descritto nel runtime sotto. Upload dalla Media Library, srcset e rewrite Elementor/LCP restano non certificati end-to-end.
- Local Google Fonts: lint/JS syntax OK; test originale non parte per path errato, copia corretta PASS; copertura per variante verificata nel probe reale. Mancano scan/download WOFF2 e dequeue remote su documento Elementor salvato.
- Browser Cache: lint OK, pannello browser e header reali verificati. LocalWP usa Nginx 1.26.1 e il pannello richiede configurazione manuale; l'asset reale risponde no-cache. Applicazione/revoca su Apache/LiteSpeed non certificata qui.
- Video Thumbnail: lint/JS syntax e pannello admin OK. FFmpeg assente; import YouTube e deduplica cover non certificati. Il codice di download usa la API sicura WordPress; nessuna SSRF dimostrata.
- Dynamic SVG: lint e contratto PASS, callback JetEngine registrati; sanitizzazione di un SVG reale PASS, come descritto sotto. Editor/Listing salvata e cache persistente non certificati end-to-end.

## Runtime LocalWP

Il probe reale con il PHP di LocalWP (PHP 8.2.29, WordPress 7.1.2, Marrison 1.3.43, Elementor 4.3.3, WooCommerce 10.9.4) ha caricato tutti i cinque moduli senza errori PHP registrati. GD, WebP e AVIF risultano disponibili; Imagick non è disponibile per una DLL mancante, per cui il test AVIF va interpretato come percorso GD. I callback Dynamic SVG sono stati agganciati a `jet-engine/callbacks/register` e `jet-engine/listings/allowed-callbacks`; JetEngine installato è 3.8.15.4 (header/plugin source e metodo `get_version()`). L'editor/listing JetEngine resta da verificare con un contenuto reale.

Il probe `off` ha confermato `enabled=false` e `loaded=false` per i moduli verificati. La prima prova di isolamento di questa analisi non era conclusiva; il controllo centrale finale [isolation-summary.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/isolation-summary.json) verifica invece tutti i 21 moduli individualmente, con il solo modulo selezionato caricato e nessun errore catturato.

La risposta HTTP attuale di LocalWP è Nginx 1.26.1. L'asset `assets/css/admin.css?ver=1.3.43` risponde con `Cache-Control: no-cache, public, must-revalidate, proxy-revalidate`, quindi Browser Cache non risulta applicato a quell'asset nel sito corrente oppure la regola non copre il document root/asset interrogato. Evidenza completa in `runtime-inventory.json`, `runtime-stderr.txt`.

Il probe funzionale (`functional-probe.php`) ha convertito una PNG trasparente fixture in WebP e AVIF tramite `convert_to_format()` sotto reflection: entrambi i file decodificano correttamente e conservano alfa 127 sul pixel trasparente. FFmpeg risulta non disponibile nel runtime LocalWP, quindi la cover automatica non è eseguibile finché non viene configurato un binario. La sanitizzazione SVG ha rimosso script, handler evento e URL esterno, mantenendo `currentColor` e `stroke="none"`. La copertura font ha dato `covered=false` con Poppins 400+700 ma solo 400 locale, e `covered=true` quando entrambe le varianti sono presenti: la rimozione completa/parziale è coerente.

Il test Image Sizes automatic upload continua a passare e copre WebP/AVIF full-size, sorgenti metadata, formati separati, size disabilitate e rigenerazione manuale/upscaling. Non sono emerse regressioni confermate su questi percorsi; una prova frontend `srcset`/`image_downsize` con file serviti richiede una fixture attachment completa e non è stata inventata come pass.

## Seconda passata: rilievo Image Sizes da verificare/correggere

Quando una size configurata manca da `$metadata['sizes']`, `generate_modern_formats_for_metadata()` la genera su disco con `generate_single_size()` (`class-marrison-addon-image-sizes.php:749-758`), ma `add_modern_source_to_metadata()` rifiuta subito di aggiungere la sorgente perché la chiave size continua a mancare (`1122-1125`). In questo scenario il file resized e i suoi WebP/AVIF possono esistere ma non vengono registrati nel metadata e non sono servibili da `srcset`/`image_downsize`; il test esistente non copre il caso perché prepara sempre la size base nel metadata. È un bug funzionale potenziale concreto sul fallback “generate it first”; serve probe con metadata senza slug e rollback prima di patchare.

## Scenari residui non certificati end-to-end

1. **Video Thumbnail:** recupero e import di una cover YouTube; estrazione da video locale dopo configurazione di FFmpeg, con deduplica e verifiche di permessi/file.
2. **Browser Cache:** dopo applicazione della configurazione Nginx, controllare header con e senza query string; applicazione/revoca `.htaccess` su un ambiente Apache/LiteSpeed.
3. **Local Google Fonts:** eseguire Scan e Scan+Update dalla pagina admin; controllare manifest/CSS/WOFF2 in uploads, poi una pagina Elementor che carica una famiglia coperta e una variante non coperta. Devono restare le varianti non coperte e sparire solo i link Google coperti.
4. **Image Sizes:** provare AVIF support test, rigenerazione di un'immagine trasparente e output `srcset`/background Elementor; controllare Network per l'endpoint pubblico `marrison_resolve_background_webp` con sessione anonima e payload massimo.
5. **Dynamic SVG:** flusso editor Dynamic Field e rendering su Listing salvata. Registrazione callback, sanitizzazione/currentColor e isolamento sono già verificati dal probe centrale.

