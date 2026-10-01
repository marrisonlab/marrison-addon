# Horizontal Scroll Snap: coordinate-origin regression

## Esito

Bug riproducibile nella costruzione delle posizioni Snap. Con un host full-width
con `padding: 10px`, il browser ha restituito:

- `mover.offsetLeft = 10` (il mover è `position: relative`, quindi è spostato
  rispetto all'host);
- figli diretti del mover: `child.offsetLeft = 0, 1265, 2530`;
- `child.getBoundingClientRect().left - mover.getBoundingClientRect().left =
  0, 1265, 2530`.

Il codice in
[`marrison-addon/assets/js/horizontal-scroll.js:638`](../../../marrison-addon/assets/js/horizontal-scroll.js:638)
usa invece `child.offsetLeft - instance.mover.offsetLeft` alla riga 641.
Nel caso osservato produce `-10, 1255, 2520`, poi il clamp porta il primo a
`0` e la funzione aggiunge sempre `overflow = 2530` alla riga 644. Il risultato
è quindi `[0, 1255, 2520, 2530]`: quattro stop per tre slide, con uno stop
parziale vicino alla fine.

## Impatto osservato

`scrollToSnapIndex()` calcola l'indice in base alla lunghezza dell'array
(`horizontal-scroll.js:45-62`) e `handleSnapWheel()` percorre quegli indici
(`horizontal-scroll.js:150-159`). Il quarto valore allunga la distanza verticale
percepita e il gesto verso il basso si ferma prima della normale ultima slide;
nel browser la posizione finale osservata era circa `sceneTop + 2530 / 3`.

## Correzione minima suggerita

Poiché i figli sono figli diretti del mover, usare `child.offsetLeft` senza
sottrarre `mover.offsetLeft`; in alternativa misurare esplicitamente la
differenza tra i due `getBoundingClientRect().left`. Con tre slide l'array
dovrebbe essere `[0, 1265, 2530]` (l'ultimo valore viene poi deduplicato dal
passaggio di `uniqueSnapPositions`).

## Limiti della prova

La prova è stata eseguita sul markup Elementor reale della pagina locale, dopo
la correzione CSS già applicata dal parent (`mover` width 100%). Non attribuisce
la causa al solo `width:max-content`; il difetto qui isolato è la sottrazione di
coordinate con origini diverse. Verificare anche RTL e gap non nullo dopo la
correzione, mantenendo un caso con host senza padding come controllo.
