# Review delle patch Elementor correnti

## Header Animations: verifica della classe native custom

In `assets/js/marrison-header-animations.js:29-44`, la classe custom viene
aggiunta solo se `started` è true:

```js
var started = element.classList.contains('animated') || (!nativeAnimation && custom);
if (custom && started && !element.classList.contains(animation)) {
    element.classList.add(animation);
}
```

Questo non è un bug confermato: nel frontend Elementor installato,
`GlobalHandler.animate` (`frontend.js:3555-3595`) aggiunge `animated` e il nome
dell'animazione dopo il relativo delay `_animation_delay`. La condizione evita
di anticipare l'animazione e il test browser reale ha confermato il percorso
per animazioni native custom e fallback. Non modificare questo timing senza
una prova di regressione.

## Header Animations: selezione responsive incompleta

La chiamata a `getCurrentDeviceSetting` è protetta anche da
`frontend.elements.$deviceMode` (`:20`). Nel frontend Elementor installato
`getCurrentDeviceMode` usa proprio `elements.$deviceMode` (`frontend.js:3883`),
quindi la guard evita di invocare l'helper prima dell'inizializzazione. Il test
browser reale ha passato desktop vuoto, tablet custom, mobile letters e resize
mobile→desktop. Restano non provati i breakpoint extra (widescreen/laptop/
tablet-extra/mobile-extra) e il re-render editor che sostituisce solo classi;
sono limiti di copertura, non bug confermati.

## Header Animations: markup e observer

Lo split ricorsivo ora conserva elementi inline e `br`. Il ripristino al cambio
da letter animation a non-letter estrae prima i `.marrison-heading-letter` e
poi i `.marrison-heading-word`, quindi il testo torna integro nei casi normali.
`MutationObserver` filtra le mutazioni di classe ma il callback le ignora;
questo evita un loop durante `selectResponsiveAnimation`, però significa che
un cambio di classe nativo Elementor non attiva `processHeading` fino al
successivo resize/load. Il cambio responsive tramite il data attribute resta
coperto. Va verificato con un re-render Elementor che sostituisce solo le
classi e non il nodo.

## Wrapped Link: helper installato e casi residui

L'API locale Elementor espone realmente
`Elementor\\Utils::parse_custom_attributes($attributes_string, $delimiter)` in
`elementor/includes/utils.php:717-746`; la chiamata PHP aggiunta è quindi
compatibile con l'installazione corrente e filtra `href`/event handler.

Il JS ora riconosce `on`, `1`, `true` e booleano per `is_external`, e ignora
`#`. Rimane una robustezza minore: `settings.url.trim()` presume che il valore
dinamico sia stringa; un dynamic tag malformato/non scalare causa TypeError nel
listener. Patch opzionale: normalizzare con `typeof settings.url === 'string'`
prima di `trim`.

## Preloader: verifica patch corrente

Il controllo iniziale di `event.defaultPrevented` è nel listener document e
precede `preventDefault`, quindi rispetta handler precedenti. La condizione
`url.hash || link.href.indexOf('#') !== -1` esclude anche `href="#"` senza
bloccare hash non vuoti. Non risultano regressioni statiche nei casi target
blank/download/modificatori già esclusi.

Test residuo: registrare un handler precedente che chiama `preventDefault()` e
verificare che non partano `showPreloaderForExit` né il timeout di navigazione;
poi provare `#`, `#target` e URL esterno.
