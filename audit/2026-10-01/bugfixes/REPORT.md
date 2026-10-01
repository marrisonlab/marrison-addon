# Correzioni Cookie Manager e Scroll Orizzontale — 1 ottobre 2026

Le tre correzioni seguenti sono state applicate al sorgente del plugin e a LocalWP. La versione resta 1.3.43; il sito pubblico e lo ZIP di distribuzione non sono stati aggiornati.

## Cookie Manager: titolo bianco su bianco

Confermato sulla [pagina segnalata](https://www.marrisonlab.com/elementor-4251/): titolo `Personalizza Cookie` con colore calcolato `rgb(255,255,255)` e fondo bianco. Il titolo non dichiarava il colore; il Kit Elementor applicava il bianco direttamente agli h3 e prevaleva sul colore ereditato dal modal.

In `includes/modules/cookie-manager/assets/css/frontend.css` il selettore del titolo ora include `#marrison-cookie-modal` e dichiara `color:#333`, senza `!important`. La fixture usa il popup e JavaScript reali con una regola globale h3 bianca caricata dopo il CSS del plugin: titolo popup `rgb(51,51,51)`, fondo `rgb(255,255,255)`, titolo globale ancora bianco. Nessuna preferenza cookie è stata salvata durante questa prova.

![Titolo popup dopo la correzione](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/bugfixes/cookie-title-after.jpg)

## Scroll Orizzontale: dimensioni intrinseche, contenuti invisibili e spazio vuoto

La prima fixture dell'audit fissava esplicitamente la larghezza delle slide e non copriva questo caso. La pagina reale contiene quattro child con row annidate, immagini SVG, larghezze percentuali e animazioni di ingresso Elementor.

Prima: sulla pagina pubblica ogni child diventava circa 8.004 x 4.055 px, il parent alto 4.075 px, il pin disattivato. La stessa struttura, con runtime Elementor reale, è stata copiata in un HTML temporaneo su LocalWP e riproduceva child di circa 7.379 x 3.738 px. Il `width:max-content` del mover forniva una larghezza intrinseca crescente alle percentuali dei child e alle immagini annidate.

In `assets/css/horizontal-scroll.css` il mover ora ha `width:100%`: la larghezza percentuale è relativa al viewport del contenitore. La distanza di scroll continua a derivare da `scrollWidth`, quindi i child eccedenti restano parte della sequenza. Non sono stati aggiunti valori fissi alle slide né modificate le loro impostazioni.

Dopo, sulla copia locale della pagina: quattro child di 1.245 x 628 px, parent alto 707 px, track di 5.040 px e pin attivo. Scroll naturale verificato fino alla quarta slide; immagini e testi con animazioni compaiono; all'uscita il container successivo torna nel flusso e la sua traslazione viene rimossa. L'altezza della scena comprende ancora la distanza verticale necessaria alla progressione, ma la vista mantiene il contenuto fissato durante quel tratto.

![Sequenza visibile durante lo scroll](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/bugfixes/horizontal-after.jpg)

## Scroll Orizzontale: fermata Snap aggiuntiva con padding

La prova con rotella ha individuato un secondo difetto nel modulo. Tre slide con parent padding 10 px producevano le posizioni `[0,1255,2520,2530]`, quindi quattro fermate. `child.offsetLeft` era già nel sistema di coordinate del mover; sottrarre `mover.offsetLeft` applicava anche il padding del parent.

In `assets/js/horizontal-scroll.js` viene usato direttamente `child.offsetLeft`. Le posizioni risultano `[0,1265,2530]`. Il test aggiunto in `tests/horizontal-scroll-js-test.js` riproduce il padding: fallisce col calcolo precedente e passa con quello corretto. Le altre verifiche del contratto Snap continuano a passare.

## Verifiche e limiti

- Contratti PHP e JavaScript di Scroll Orizzontale: PASS; sintassi JS e diff whitespace: PASS.
- CSS generato da Elementor e rendering browser per Full/Boxed, continuo/Snap, child 33/50/80%, row annidata, direzione inversa e pin disabilitato: dimensioni e overflow coerenti, contenuti presenti.
- Documento reale in frame a 1024 x 900: child 989 x 380 px e pin attivo. Frame a 390 x 844: modulo disabilitato come da configurazione, wrapper rimossi, child 355 x 670 px nel flusso normale.
- La copia pubblica usa il runtime Elementor 4.3.3 e le animazioni di ingresso reali. Non certifica tutte le combinazioni di Motion Effects Pro, overflow degli antenati o impostazioni editor possibili.
- Le prove pubbliche sono state di sola lettura; la correzione online resta da distribuire. I file temporanei su LocalWP vengono rimossi dopo confronto hash, conservando i sorgenti nell'audit. I post Elementor temporanei vengono annullati tramite rollback e verificati assenti.

Prove strutturate: [browser-results.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/bugfixes/browser-results.json). Generatori: [pagina pubblica](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/bugfixes/clone-public-page.py), [varianti Elementor](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/bugfixes/variants-generator.php), [popup cookie](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/bugfixes/cookie-title-fixture.php).
