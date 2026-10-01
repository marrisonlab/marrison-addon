# Review delle patch applicate

## Cookie Manager

La nuova `get_blocked_attributes()` gestisce valori quotati e non quotati, sostituisce `src` con `data-marrison-blocked-src` e conserva il tipo script in `data-marrison-script-type`. `activateBlockedScript()` ripristina il tipo originale dopo il consenso. Non risultano regressioni statiche: il tipo viene rimosso dal tag bloccato e reimpostato solo sul nuovo elemento.

## Image Sizes

`generate_single_size()` ora riceve metadata per riferimento, registra il file generato nella size mancante e gestisce `WP_Error` da `resize()`. La chiamata esistente resta compatibile grazie al quarto argomento opzionale. Il flusso manuale sospende temporaneamente il filtro automatico per evitare ricorsione e ripristina il flag in `finally`; non risultano regressioni statiche.

## Cursor

La sanitizzazione accetta ora sia esadecimali validi sia `rgb/rgba`, coerentemente con il color picker e il CSS/JS frontend. Input non valido continua a ricadere sul default. Nessuna regressione concreta rilevata.

## Verifiche

- PHP lint superato per i tre file PHP modificati.
- Il test Image Sizes automatic upload precedente resta PASS.
- Il file JS Cookie Manager mantiene il ripristino del tipo originale e la trasformazione della sorgente dopo consenso.

Non sono state modificate sorgenti di produzione o test da questo audit.
