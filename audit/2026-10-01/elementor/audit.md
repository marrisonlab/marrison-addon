# Audit Elementor modules — runtime LocalWP 2026-10-01

Probe eseguito con PHP LocalWP 8.2.29, WordPress 7.1.2, Elementor 4.3.3 e WooCommerce 10.9.4. Il probe usa il checkout LocalWP, blocca le richieste HTTP esterne e apre una transazione DB con rollback a shutdown.

## Copertura runtime

- Tutti i moduli assegnati risultano abilitati e caricati.
- Widget registrati: `text-editor`, `jet-listing-dynamic-field`, `jet-listing-grid`, `ticker`, `marrison_steps`.
- Read More controls presenti sia su Text Editor sia su JetEngine Dynamic Field.
- Listing Title controls presenti sul widget JetEngine Listing Grid.
- `render_read_more()` ha prodotto wrapper `data-marrison-read-more` e pulsante toggle.
- `prepend_listing_title()` ha prodotto il titolo prima del markup Listing Grid.
- `mb_strlen()` è disponibile nel runtime LocalWP (`mbstring=true`): il rischio teorico del Ticker non è riprodotto.
- PHP lint, Node syntax check e contratti locali Read More/Horizontal Scroll/Liquid Background: tutti PASS.

## Risultati per modulo

Le voci “Fixture browser” nella mappatura iniziale indicano scenari previsti, non tutti eseguiti. Le prove effettivamente completate e i limiti sono elencati nell'aggiornamento browser alla fine di questo documento e nel [rapporto generale](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/REPORT.md).

### Wrapped Link

Hook registrati: `elementor/element/container/section_layout/after_section_end`, `elementor/element/common/_section_style/after_section_end`, `elementor/frontend/container/before_render`, `elementor/frontend/widget/before_render`.

La classe e l’asset `assets/js/marrison-addon.js` risultano caricati. Nessun errore PHP catturato. Fixture browser: Container e widget con URL interno, URL esterno, link annidato e click su SVG.

### Read More

Hook registrati: `elementor/element/after_section_end`, `elementor/widget/render_content`, `elementor/frontend/builder_content_data`, `wp_enqueue_scripts`.

Controls presenti su Text Editor e Dynamic Field. Render callback verificato con output Elementor: wrapper e button presenti. Contratti PHP/JS PASS. Da verificare nel browser: HTML annidato complesso e refresh AJAX Listing Grid.

### Horizontal Scroll

Hook registrati: `elementor/element/container/section_layout/after_section_end`, `elementor/frontend/container/before_render`, `elementor/frontend/builder_content_data`.

Contratti PHP/JS PASS. Fixture browser: overflow reale con `pin=true`, `snap=true`, background scale, edge escape wheel e reduced motion. Il runtime non ha creato una pagina Elementor nuova; la prova visiva resta a carico del browser.

### Liquid Background

Hook registrati: `elementor/element/container/section_layout/after_section_end`, `elementor/frontend/container/before_render`, `elementor/frontend/builder_content_data`, `elementor/preview/enqueue_scripts`.

Contratti PHP/JS PASS. Supporto runtime: WebP e AVIF disponibili, Imagick assente. Fixture browser: preset/custom colors, `__globals__`, blend responsive, mobile disable, reduced motion e WebGL fallback.

### Ticker

Widget registrato come `Marrison_Addon\\Modules\\Ticker\\Widgets\\Ticker_Widget`. `mb_strlen()` disponibile. Nessun fatal/error catturato. Fixture browser: repeater, Query JetEngine, query vuota, link `_blank`, pausa hover e direzione destra.

Nota: il codice chiama `mb_strlen()` a `includes/modules/ticker/widgets/ticker-widget.php:632`; la funzione è disponibile nel runtime attuale. Questa prova non dimostra un fatal negli ambienti privi dell'estensione, anche perché WordPress può fornire compatibilità per la funzione.

### Steps

Widget registrato come `Marrison_Steps_Widget`; stylesheet dichiarato tramite `get_style_depends()`. Nessun errore PHP. Fixture browser: 2, 8 e 9 step; tutte le varianti connector; orientamento desktop/tablet/mobile; marker image/icon/number.

### Header Animations

Hook e asset risultano registrati. La riflessione runtime ha confermato che `get_selected_animation()` sceglie il valore desktop se valorizzato; quando desktop è vuoto sceglie direttamente il valore tablet (`desktop_empty_tablet_mobile => marrisonDropSoft`). Nel browser a 1280 px l'Heading reale presenta già la classe e l'animation-name `marrisonDropSoft`: il difetto responsive è riprodotto.

Il letter splitting usa `textContent`. Nel DOM browser di un secondo Heading reale, `Prima <strong>evidenza</strong><br>Seconda <em>riga</em>`, elimina tutti e tre gli elementi strong/em/br e concatena “evidenzaSeconda”. La perdita del markup è riprodotta.

### Listing Grid Title

Controls presenti su `Elementor\\Jet_Listing_Grid_Widget`. Render callback verificato: titolo HTML prodotto e anteposto al markup `.jet-listing-grid__items`. Nessun errore PHP. Fixture browser: titolo con/ senza link, tag diversi, refresh AJAX e listing vuota.

### Anchor Offset

Hook `wp_enqueue_scripts` presente e asset registrato sul frontend pubblico. Nessun errore PHP. Fixture browser: hash iniziale, click same-page con query string, `hashchange`, header `#hdr` con altezza dinamica via ResizeObserver.

## Problemi confermati

Nessun fatal PHP o errore di render riprodotto nel runtime. Due bug P2 di Header Animations sono confermati dal browser: selezione dell'animazione tablet su desktop e perdita del markup nelle animazioni a lettere. Evidenza: [browser-evidence.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/browser-evidence.json).

## Fixture browser

- Generatore: `generate-browser-fixture.php`.
- Fixture: `browser-fixture.html`.
- La fixture è stata generata con `create_element_instance()` e `print_element()` per Heading, Text Editor, Wrapped Link Container, Ticker, Steps, Horizontal Scroll Container e Liquid Container.
- Include URL asset reali `http://localhost:10004/wp-content/plugins/marrison-addon/...` e Elementor frontend CSS, controlli Read More, link annidato Wrapped Link, Ticker repeater, 8 Steps, Horizontal Scroll pin/snap con tre Container figli `.elementor-element`, Liquid custom colors/blend e shape divider.
- Verifica CLI: nessun `AUDIT_JSON` nell’HTML; 9 URL asset presenti; marker Read More, Horizontal Scroll, Liquid Background e Steps presenti; heading non malformato.
- Il markup Heading proviene da `print_element()` reale con `_animation` desktop vuoto, `_animation_tablet` `marrisonDropSoft`, `_animation_mobile` `marrisonLettersFocus` e testo HTML inline.

## Aggiornamento dopo le prove browser

- Wrapped Link: click sul Container reale cambia l'hash al target previsto.
- Read More: altezza 58 → 90 → 58 px; label e ARIA cambiano correttamente.
- Horizontal Scroll: tre Container reali inizializzano pin e snap; un gesto rotella porta alla slide successiva con transform `translate3d(-1221px, 0px, 0px)`.
- Liquid Background: canvas WebGL creato; resa grafica completa su documento salvato e fallback non certificati.
- Ticker: repeater renderizzato e animazione CSS `ticker-infinite-left` osservata.
- Steps: otto step renderizzati in grid.
- Header Animations: entrambi i difetti descritti sono riprodotti su Heading reali.
- Anchor Offset: header misurato 88 px, variabile CSS 88 px, target del click sotto l'header.

La fixture finale include due Heading distinti, uno responsive e uno con animazione a lettere desktop. Non è stato creato un documento Elementor salvato. Il viewport osservato resta 1280×720: l'override mobile dello strumento non si è applicato e non viene conteggiato come verifica mobile. Gli altri scenari della mappatura iniziale, incluse Query JetEngine popolata, Listing AJAX, hover, Motion Effects Pro e varianti visuali, restano non certificati end-to-end.

