# Horizontal Scroll follow-up: boxed parent, early movement and Snap

## Diagnosi verificata

Il percorso non-pinned è attivo quando `pin` è disabilitato oppure quando il
contenitore è più alto della viewport (`horizontal-scroll.js:662-665`):

```js
instance.pinned = !! instance.config.pin && instance.overflow > 1 &&
    instance.viewport.offsetHeight <= window.innerHeight + 1 &&
    ! hasStickyBlockingAncestor( instance.scene );
```

In quel caso `paint()` non aspetta che il pannello arrivi in cima alla
viewport. A `horizontal-scroll.js:559-566` usa:

```js
naturalRange = sceneHeight + window.innerHeight;
range = naturalRange * speed;
center = sceneTop + (sceneHeight - window.innerHeight) / 2;
progress = (scrollY - (center - range / 2)) / range;
```

Con `speed=1`, la progressione inizia a `sceneTop - window.innerHeight`,
cioè quando il bordo inferiore del pannello entra nella viewport. Quando il
bordo superiore arriva a `sceneTop`, il valore è già
`window.innerHeight / (sceneHeight + window.innerHeight)`. Se il parent boxed
ha un'altezza vicina a metà viewport, questo è circa 2/3: spiega il caso
osservato in cui il parent è completamente visibile ma il mover ha già
avanzato circa due slide su tre. La larghezza boxed (ad esempio 1140px in un
frame molto più largo) determina inoltre l'overflow e la larghezza effettiva
dei figli `100%`, ma non modifica questa formula verticale.

Snap non cambia la progressione: in `paint()` (`horizontal-scroll.js:570-572`)
usa lo stesso `progress` non-pinned e seleziona soltanto l'elemento di
`instance.snapPositions`. Di conseguenza anche `snap=yes` può iniziare il
movimento al primo pixel di intersezione del pannello se `pinned=false`.

## Pin e indicatore runtime

Il valore realmente usato è quello serializzato nel JSON dell'attributo
`data-marrison-horizontal-scroll`: PHP costruisce `pin` con
`'yes' === $settings['marrison_horizontal_scroll_pin']` in
`includes/modules/class-marrison-addon-horizontal-scroll.php:188-193`.
Il DOM espone lo stato risultante tramite la classe
`.marrison-horizontal-scroll-pinned`, aggiunta in JS alle righe 673-676; non
esiste però un attributo diagnostico che distingua `pin:false` da una
disqualifica per altezza/sticky ancestor. Per la prova browser registrare
entrambi:

```js
const root = document.querySelector('[data-marrison-horizontal-scroll]');
const scene = root?.closest('.marrison-horizontal-scroll-scene');
({ config: JSON.parse(root.dataset.marrisonHorizontalScroll),
   pinned: scene?.classList.contains('marrison-horizontal-scroll-pinned'),
   viewportHeight: scene?.querySelector('.marrison-horizontal-scroll-viewport')?.offsetHeight,
   innerHeight: window.innerHeight,
   sceneTop: scene?.getBoundingClientRect().top + scrollY });
```

Se `config.pin` è true ma `viewportHeight > innerHeight + 1`, oppure esiste
un ancestor `position: fixed|sticky|absolute`, il risultato `pinned=false` è
atteso dal codice e produce il comportamento sopra.

## Cache `sceneTop` e più istanze

`measureSceneTop()` salva la coordinata documentale in `instance.sceneTop`
(`horizontal-scroll.js:28-39`); `measure()` la aggiorna e i ResizeObserver di
root/host/mover/contenuti la ri-programmano (`652-661`, `737-752`). Un cambio
di altezza del contenuto precedente nella pagina può però spostare la scena
senza ridimensionare questa istanza, lasciando la cache vecchia. Inoltre
`updateFollowingShift()` applica `translate` ai sibling successivi
(`366-397`): il layout documentale non cambia, ma una seconda scena contenuta
nel sibling può muoversi visivamente mentre il suo `sceneTop` resta quello
documentale. Questo è un edge case separato da riprodurre con due istanze
pinned e `revealFollowing=true`; non è necessario per spiegare il singolo
parent boxed.

## Prova minima e correzione da valutare

Usare lo stesso container reale in quattro varianti: boxed 1140px/full frame,
`pin=yes/no`, `snap=yes/no`, e viewport con altezza inferiore/superiore al
viewport del container. Registrare `config.pin`, classe pinned, le tre
coordinate slide e il primo `scrollY` in cui cambia `transform`. Il controllo
atteso è: `pin=yes` e viewport non più alta della finestra devono mantenere
offset 0 finché la scena raggiunge il bordo superiore; `pin=false` o viewport
tall devono invece mostrare il passaggio anticipato documentato sopra.

Se il comportamento desiderato è “inizia solo quando il parent è in cima”
anche senza pin, la correzione minima è rivedere la formula non-pinned per
usare `sceneTop` come inizio della progressione, invece di
`center - range / 2`. È una modifica di comportamento e va applicata solo
dopo avere deciso esplicitamente la semantica di `pin=false`; per il caso
`pin=true` tall viewport serve prima rendere evidente nel markup che il pin è
stato disqualificato oppure evitare di usare la modalità sticky in quel caso.
