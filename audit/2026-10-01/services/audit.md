# Audit servizi Marrison Addon - 2026-10-01

Runtime: LocalWP `localhost:10004`, PHP 8.2.29, WordPress 7.1.2, WooCommerce 10.9.4, Elementor 4.3.3. Il probe usa `wp-runtime-probe.php`: rete bloccata, transazione DB aperta e `ROLLBACK` allo shutdown. Nessun file del plugin o dato persistente e stato modificato.

## Product Discount

File: `includes/modules/class-marrison-addon-product-discount.php`, `includes/modules/product-discount/widgets/product-discount-widget.php`, `assets/js/admin-product-discount.js`.

Probe passati:

- prodotto semplice 100 -> 75: `25%`;
- prodotto semplice 100 -> 0: `100%`;
- guardie sui prezzi non validi e prezzo sopra il regolare: `0`;
- hook WooCommerce, Elementor widget, shutdown sync registrati.

Nella prima passata gli oggetti WooCommerce in memoria senza figli restituivano correttamente `0`, ma non esercitavano `get_variation_prices(false)`. Il caso con variazioni reali e il batch sono stati completati successivamente, come descritto sotto.

Seconda passata: una variabile temporanea con due variazioni persistite nel transaction rollback ha restituito il massimo corretto `50%`; il batch reale pagina 1 ha restituito `total=6`, `processed=6`, `totalPages=1`, `done=true`. Non sono emersi errori nel percorso batch. Mancano ancora una riproduzione di saldi programmati tramite cron reale e una verifica di concorrenza Action Scheduler.

## Recently Viewed Products

File: `includes/modules/class-marrison-addon-recently-viewed-products.php`, `includes/modules/recently-viewed-products/macros/recently-viewed-products.php`.

La prima verifica `class_exists()` era un falso positivo: JetEngine carica la classe base durante la lazy init di `jet_engine()->listings->macros->get_all()`. Dopo aver forzato questa routine, la macro `marrison_recently_viewed_products` compare nella lista reale. Con tre prodotti pubblicati esistenti (22, 27, 29), cookie `22|27|29` viene letto come `[29,27,22]`; con prodotto corrente 22 e `exclude_current=no`, il risultato limitato a 2 è `22,29`; con esclusione corrente è `29,27`. Il tracker reale, invocato dall’oggetto trovato nell’hook `template_redirect` priorità 20, ha aggiunto il prodotto corrente e ha mantenuto il cap di 50 posizioni. Nessun bug confermato. Manca la verifica browser/Query Builder.

## Calendar Sync

File: `includes/modules/class-marrison-addon-calendar-sync.php`.

Passati: formattazione UTC, hook `template_redirect`, shortcode con post inesistente che restituisce stringa vuota.

Bug confermato: `sanitize_settings()` conserva chiavi meta vuote (`start_meta`/`end_meta`), quindi l’amministratore può salvare impostazioni che fanno fallire tutti gli eventi. Il probe lo riproduce in `calendar_sync.txt`.

Bug confermato e riprodotto: `download_ics()` accetta un ID se `get_post()` restituisce un post, senza controllo di `post_status` o capacità. Il probe `calendar-ics-probe.php` ha inserito temporaneamente un post `draft` ID 100 con meta date e ha ottenuto un file ICS completo, inclusi titolo, excerpt e permalink, prima del rollback. Il percorso è pubblico su `template_redirect`; un ID di bozza/privato con meta evento può quindi essere richiesto senza autenticazione.

Robustezza: un valore `ics_timezone` preesistente e non valido causa `DateTimeZone::__construct(): Unknown or bad timezone` in `format_ics_datetime()`. Il probe lo riproduce su input legacy; il form attuale sanitizza il timezone, quindi non è stato riprodotto un guasto usando il normale input del form.

Bug P2: lo shortcode accetta `location`, ma l’ICS emesso scrive sempre `LOCATION:` vuoto; Google conserva `Roma` nella prova reale. Un secondo bug P2 riguarda `start_meta`/`end_meta`: lo shortcode genera il link usando le chiavi personalizzate, ma l'endpoint ICS rilegge le chiavi globali e risponde “Date evento non disponibili.”. Evidenza aggiuntiva: [calendar-shortcode-results.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/calendar-shortcode-results.json).

## Cookie Manager

File: `class-marrison-addon-cookie-manager.php`, `cookie-manager/includes/class-cookie-{scanner,banner,consent,admin-settings,setup-wizard}.php`, relative JS/CSS/template.

Passati: classificazione analytics, neutralizzazione di script esterni con `type=text/plain` e `data-marrison-blocked-src`, assenza degli endpoint scanner `nopriv`, tabella `{prefix}marrison_cookies` presente nel DB LocalWP. Nessun errore PHP registrato dal probe.

Il browser sul sito reale conferma categorie opzionali inizialmente non selezionate, categoria necessaria selezionata e disabilitata, rifiuto che nasconde il banner e persistenza dopo reload. Riattivazione di tracker esterni dopo consenso, script inline senza `src`, iframe, cookie con path/domain diversi e nonce dopo cache pagina restano da verificare. Il probe non dimostra il comportamento dei tag dinamici inseriti dopo il caricamento.

## Cursor

File: `class-marrison-addon-cursor.php`, `assets/js/marrison-cursor.js`, `assets/css/marrison-cursor.css`.

L’asset `marrison-cursor` è registrato nel frontend pubblico e non emergono errori PHP. Sul sito reale il browser conferma dot/follower e classe attiva. Restano da verificare movimento, pointer fine, reduced motion, esclusione Elementor/editor e comportamento del cursore su iframe.

## Preloader

File: `class-marrison-addon-preloader.php`, `assets/js/marrison-preloader.js`, `assets/css/marrison-preloader.css`.

L’asset `marrison-preloader` è registrato nel frontend pubblico e non emergono errori PHP. Sul sito reale dopo load, opacity è 0, visibility hidden e pointer-events none. Restano da verificare temi privi di `wp_body_open`, navigazione indietro/avanti, link hash e tutte le transizioni.

## Fast Logout

File: `class-marrison-addon-fast-logout.php`.

Il filtro `logout_redirect` è registrato e restituisce `http://localhost:10004` come previsto dal modulo. Il comportamento ignora deliberatamente il redirect richiesto; confermare che sia la policy desiderata anche per multisite.

## Limiti

Le verifiche browser aggiuntive sono riportate sopra e in [browser-evidence.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/browser-evidence.json). Non sono stati eseguiti i flussi completi degli editor Elementor/JetEngine, la concorrenza batch/cron o l'esecuzione di tracker esterni. Imagick risulta non caricabile dal PHP LocalWP (`php_imagick.dll` mancante), ma non impatta direttamente questi moduli. Il [rapporto generale](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/REPORT.md) contiene la copertura finale e le priorità.

