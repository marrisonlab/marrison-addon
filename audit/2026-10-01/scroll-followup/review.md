# Review fit immagini Snap + Pin

## Problema confermabile: budget incompleto per layout Grid

`fitSnapImages()` (`assets/js/horizontal-scroll.js:657-710`) sottrae le
altezze dei sibling e `rowGap` solo se il parent è `display:block` oppure un
flex column. Un parent Elementor/CSS `display:grid` entra comunque nel
percorso di misura, ma non contribuisce con le righe/sibling verticali.
Con un'immagine in una cella grid e una seconda riga alta, il budget diventa
`window.innerHeight - padding/bordi` invece di sottrarre la riga occupata; la
classe `marrison-horizontal-scroll-fit-image` può quindi lasciare il fondo
della slide oltre la viewport. Repro minima: Snap+Pin, immagine nella prima
cella di un grid a due righe, seconda riga con `height:400px`; confrontare
`image.getBoundingClientRect().bottom` con `window.innerHeight` e il caso flex
column equivalente.

## Problema confermabile: budget minimo artificiale di 1px

Quando margini, padding, bordi e sibling superano `window.innerHeight`, la
riga 708 forza `maxHeight` a `1px`. Questo può rendere invisibile quasi ogni
immagine in una composizione reale con header/righe alte; non c'è un controllo
che distingua overflow intenzionale da un budget impossibile. Il risultato è
una slide “fit” formalmente applicata ma visualmente vuota. Repro: Snap+Pin,
immagine annidata in una colonna con sibling verticale complessivamente più
alto della viewport; osservare la variabile inline
`--marrison-horizontal-scroll-image-max-height: 1px`.

## Rischio di lifecycle: classe host preesistente

`start()` aggiunge `marrison-horizontal-scroll-content-host` (`:792`)
e `stop()` la rimuove sempre (`:873`). Se markup o altro codice avevano
già quella classe, teardown la perde. Il rischio è riproducibile inserendo la
classe prima dell'avvio e verificando che manchi dopo un cambio breakpoint o
`prefers-reduced-motion`; va trattato come bug di ripristino al pari della Map
immagini.

## Casi che risultano corretti dal codice

- La Map salva una sola volta la variabile custom originale, incluse priorità,
  e `restoreSnapImages()` la ripristina; la classe viene rimossa solo se non
  era presente prima (`:645-654`).
- Il cap originale viene conservato solo quando il computed `max-height` è in
  pixel (`:678-684`); `none`, percentuali e `calc()` ricevono `Infinity`, ma
  non vengono sovrascritti: la proprietà originale resta nel CSS e il custom
  var viene eliminato al teardown.
- Il ricalcolo su resize è agganciato a ResizeObserver/load/font readiness e
  la Map impedisce di ricampionare il cap originale a ogni passaggio. Non ho
  trovato un loop certo: dopo il primo set, il budget resta stabile salvo
  variazioni reali di viewport/layout.
- La misura è indipendente da `direction`; `object-fit:contain` e max-height
  non cambiano l'ordine o il segno del transform.

## Test browser prioritario

Per il test root: eseguire per ciascuna variante Snap+Pin (LTR/RTL, boxed e
full width, immagine caricata prima/dopo init) questo probe:

```js
const i = document.querySelector('.marrison-horizontal-scroll-mover img');
({ fit: i?.classList.contains('marrison-horizontal-scroll-fit-image'),
   max: i?.style.getPropertyValue('--marrison-horizontal-scroll-image-max-height'),
   rect: i?.getBoundingClientRect().toJSON(),
   viewport: innerHeight });
```

Poi disattivare il modulo via breakpoint/reduced-motion e verificare che
classe e variabile tornino esattamente allo stato precedente. Il caso grid e
il budget `1px` sono i due controlli bloccanti prima di considerare il fit
affidabile.
