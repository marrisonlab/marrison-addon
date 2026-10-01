# Scroll Orizzontale — ritaglio Boxed e ingresso Snap

Correzioni applicate al sorgente e agli asset installati in LocalWP, mantenendo la versione 1.3.43. Il sito online e lo ZIP di distribuzione non sono stati modificati.

## Cause confermate

1. **Slide successiva visibile senza Snap.** Nel viewport 2540 x 737 il parent occupa 2525 px, ma il suo `.e-con-inner` Boxed misura 1640 px. Il primo child misura correttamente 1640 px; il secondo inizia a x=2102,5, all'interno del parent esterno. Il contenuto interno aveva overflow visibile e quindi il ritaglio esterno lasciava comparire circa 423 px della slide successiva.
2. **Snap anticipato.** Snap forzava il contenuto Boxed a tutta larghezza: 2505 px, con parent alto circa 1287 px. Il pin risultava non applicabile nel viewport alto 737 px. Il percorso senza pin calcolava la progressione dall'ingresso della sezione: quando il parent arrivava in cima, il track era già a `translate3d(-2525px,0,0)`, cioè alla seconda slide.

Le copie temporanee usano il markup della [pagina segnalata](https://www.marrisonlab.com/elementor-4251/), i suoi CSS e il runtime Elementor, con solo gli asset Scroll Orizzontale sostituiti da quelli locali. Le misure dipendono anche dai CSS Elementor caricati dal runtime; sono registrate per ogni fase nel JSON delle prove.

## Comportamento corretto

- La classe attiva `marrison-horizontal-scroll-content-host` ritaglia orizzontalmente il track alla larghezza reale del contenuto. Le larghezze Elementor dei child e il Boxed vengono conservati in continuo e Snap. Il marker precedente di Snap resta disponibile; il suo allargamento forzato è stato rimosso.
- Snap conserva la slide iniziale durante l'ingresso. Se il pin non è applicabile, il fallback inizia dalla posizione superiore della sezione, con durata basata sull'altezza della scena. La progressione continua senza Snap conserva la formula esistente.
- Con Snap e Pin attivi, come richiesto dall'utente, le immagini vengono limitate all'altezza disponibile. Il budget tiene conto di padding, margini, bordi, altri contenuti verticali e righe Grid; `object-fit:contain` conserva le proporzioni. Non vengono alterate le larghezze impostate in Elementor.
- L'adattamento viene ricalcolato al resize e al caricamento dei contenuti. Classi e proprietà aggiunte alle immagini vengono ripristinate quando il modulo/Snap viene disattivato, oppure al breakpoint mobile escluso dalla configurazione.
- Se i contenuti diversi dalle immagini occupano già tutta l'altezza disponibile, l'immagine resta utilizzabile: non viene ridotta a 1 px e il pin continua a valutare l'altezza effettiva. Testo, min-height e altre impostazioni possono ancora impedire il pin in layout diversi dalla pagina provata.

## Prove

- **Snap, 2540 x 737:** Boxed 1640 px, parent alto 737 px, pin attivo, offset iniziale 0. Le immagini hanno limite proporzionato agli spazi effettivi, incluso il padding del widget.
- **Continuo, 2540 x 1200:** Boxed 1640 px, child 1640 px, track a 0, secondo child x=2102,5 e bordo del ritaglio x=2082,5. La slide successiva è fuori dal ritaglio.
- **Ingresso reale con rotella, 1280 x 720:** parent a y=719,44 e track a 0; un gesto verticale porta il parent a y=0 mantenendo il track a 0. Il gesto successivo porta il track a -1265 px, la seconda slide.
- **Ingresso nel frame largo:** con solo il primo pixel visibile, Snap resta a 0. Senza Snap, la progressione è minima (-2,94 px) e il secondo child resta fuori dal ritaglio.
- **Resize dello stesso documento 2540 → 390 → 2540:** wrapper e limiti delle immagini rimossi a 390 px; ripristinati al ritorno al desktop.
- **Contratti PHP e JavaScript:** PASS. Nuove regressioni per parent troppo alto, Pin disattivato, adattamento immagini e ripristino delle proprietà inline, budget impossibile. La prova del salto anticipato falliva prima della correzione.
- Sintassi JavaScript e controllo whitespace: PASS. Nessun documento WordPress è stato creato durante questa passata.

File modificati: [CSS](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/css/horizontal-scroll.css:48), [JavaScript](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/assets/js/horizontal-scroll.js:657), [test](C:/Users/Angelo/Documents/GitHub/marrison-addon/tests/horizontal-scroll-js-test.js), [README](C:/Users/Angelo/Documents/GitHub/marrison-addon/marrison-addon/README.md:128).

Prove complete: [browser-results.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/scroll-followup/browser-results.json). Le cinque fixture locali vengono rimosse dopo confronto hash; i sorgenti rimangono nell'audit. Le immagini seguenti mostrano frame reali ridotti visivamente, con viewport interno delle dimensioni dichiarate.

![Continuo Boxed](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/scroll-followup/continuous-boxed-after.jpg)

![Snap con immagini adattate](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/scroll-followup/snap-wide-after.jpg)
