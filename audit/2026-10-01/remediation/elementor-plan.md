# Piano remediation Elementor

## Header Animations

### Responsive selection

`includes/modules/class-marrison-addon-header-animations.php:225-250`
seleziona nell'ordine `marrison_header_animation`, `_animation`,
`_animation_tablet`, `_animation_mobile`. La prima impostazione non vuota vince
anche quando è solo desktop: la selezione tablet/mobile non può essere fatta
correttamente in PHP, perché `before_render` avviene una sola volta per la
pagina.

Patch minima: serializzare nel wrapper una mappa dei valori validi per
fallback/desktop/tablet/mobile; lasciare al JS la scelta con le media query
Elementor (`elementorFrontend.config.responsive.breakpoints` quando presente,
con fallback alle breakpoint CSS del sito). Il JS deve rimuovere solo le classi
`marrison-*` di questo modulo, aggiungere la classe scelta e aggiornare
`data-marrison-letter-animation`. Per evitare un cambio dopo il primo paint,
eseguire la scelta prima della scansione delle lettere e riapplicarla su
`resize`/`elementor/frontend/init`.

Test Elementor: Heading con desktop vuoto, tablet `marrisonDropSoft`, mobile
`marrisonFocusIn`; verificare a 1440/900/767px classe e animazione effettive.
Ripetere con fallback valorizzato e `_animation` vuoto per assicurare che il
fallback non sovrascriva una scelta responsive.

### Split lettere senza perdita markup

`assets/js/marrison-header-animations.js:39-78` legge `textContent`, azzera
`container.textContent` e ricrea solo testo. Questo elimina `strong`, `em`,
`a`, `<br>` e qualsiasi markup inline del titolo.

Patch minima: sostituire lo split piatto con un walker ricorsivo dei text node:
conservare i nodi elemento (`strong`, `em`, `a`, `br` e classi Elementor),
sostituire solo ogni segmento di testo con span lettera e lasciare invariati
spazi/newline. Applicare `data-marrisonLettersReady` al container e non
rieseguire sul markup già splittato. Il caso speciale di un Heading composto da
un solo link può restare, purché il walker operi dentro quel link.

Test: titolo `Prima <strong>evidenza</strong><br>Seconda <em>riga</em>`;
verificare che dopo init esistano ancora `STRONG`, `BR`, `EM`, che il testo
visibile sia identico e che ogni carattere abbia un solo span animato. Testare
anche Elementor editor/dynamic re-render, dove `MutationObserver` richiama
`processHeading`.

## Wrapped Link

### Valori URL Elementor e target esterno

`includes/modules/class-marrison-addon-wrapped-link.php:41-63` serializza
l'intero valore del controllo URL nel JSON; `assets/js/marrison-addon.js:25`
considera esterno solo `is_external === 'on'`. Il controllo URL Elementor usa
normalmente `on`, ma dati importati/legacy possono contenere `1`, `true` o
booleano. Normalizzare esplicitamente questi valori prima di scegliere
`window.open`; non basarsi su una fixture raw non prodotta dal controllo.

### Custom attributes

Il README dichiara il supporto del controllo URL, ma `marrison-addon.js` non
legge mai `custom_attributes`: oggi restano soltanto nel JSON e non raggiungono
il wrapper. Patch minima: usare l'helper Elementor installato
(`Elementor\\Utils::parse_custom_attributes`, verificandone la firma nella
versione locale) oppure un parser compatibile `key|value` separato da virgole,
validare i nomi attributo e applicare gli attributi con
`add_render_attribute( '_wrapper', key, value )`. Non inserire stringhe HTML
grezze. Gli attributi di controllo devono essere applicati al wrapper anche
quando il click su un link annidato viene lasciato al link nativo.

### URL vuoto/hash

`settings.url` con `'#'` è truthy e viene assegnato a `window.location.href`,
producendo un cambio hash/delay inutile. Normalizzare in PHP/JS URL vuoti e
`#` come “nessun wrapped link”; per hash non vuoti mantenere la navigazione
interna. Aggiungere un test per `''`, `'#'`, `'#target'` e URL assoluto.

## Preloader

`assets/js/marrison-preloader.js:86-115` intercetta ogni click valido a livello
documento. Deve uscire immediatamente se `event.defaultPrevented` è già true,
prima di chiamare `preventDefault()` o mostrare il preloader; questo evita di
riaprire/delaying azioni Elementor, menu e plugin che hanno già gestito il
click.

Patch minima nella callback: `if (event.defaultPrevented) return;` prima di
`shouldInterceptLink`. In `shouldInterceptLink`, trattare anche `href="#"` come
anchor locale senza destinazione e non intercettarlo; conservare l'eccezione
esistente per hash non vuoti sulla stessa pagina.

Test con tre listener: un listener precedente che chiama `preventDefault`, un
link Elementor con `href="#"`, e un link alla stessa pagina `#target`.
Verificare che nessuno dei primi due avvii delay/preloader, mentre il terzo
resti navigazione interna senza reload. Ripetere con ctrl/cmd-click, target
blank, download e link esterno per confermare le esclusioni già presenti.

## Ordine di applicazione e regressione

Applicare prima la selezione responsive/header e lo split DOM, poi Wrapped Link
e infine Preloader. Per ogni patch eseguire `php -l`, `node --check`, il probe
WP runtime con transazione rollback e una pagina Elementor reale con dynamic
content. La verifica conclusiva deve includere markup serializzato, classi
responsive, attributi custom presenti sul wrapper e stato di
`defaultPrevented` prima/dopo ogni listener.
