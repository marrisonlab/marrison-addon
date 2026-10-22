# Query Fallback e dipendenze dei moduli

## Configurazione

1. Attivare Elementor e JetEngine, poi **AM Addon > Query Fallback**.
2. In Elementor aprire un **Listing Grid > Custom Query**, abilitare **Use Custom Query** e selezionare una Posts Query.
3. Impostare il numero di elementi per pagina nella query principale: con Custom Query è questo il limite usato da JetEngine, anche se il campo generale Posts Number del widget ha un valore diverso. Per gli esempi seguenti usare **4**.
4. Abilitare **Enable Query Fallback**, scegliere **Fallback Query** e la modalità radio **Only when empty** oppure **Fill missing items**.
5. Le opzioni provengono dal registro Query Builder nativo. L'elenco contiene solo Posts Query; il controllo editor esclude la principale e segnala selezioni incompatibili, circolari o eliminate. Dopo aver creato/eliminato query in un'altra scheda ricaricare l'editor, come per il selettore nativo.

Only when empty conserva qualsiasi risultato principale e consulta il fallback solo con zero risultati. Fill missing items conserva ordine e precedenza della principale, poi aggiunge oggetti WP_Post unici dal fallback fino al limite. Post, pagine, CPT e prodotti rientrano in Posts Query; il modulo non combina Terms, Users, CCT o query WooCommerce di altro tipo.

Le esclusioni per ID della principale si applicano anche al fallback. Restano attive tutte le condizioni della query fallback, incluse esclusioni, post type, stato, tassonomie, ordinamento e offset. L'intersezione con `post__in` viene corretta esplicitamente perché WordPress altrimenti ignora `post__not_in`. Le condizioni di categoria della principale non vengono copiate: il fallback può così recuperare, per esempio, gli ultimi prodotti di altre categorie.

## Integrazione e limiti espliciti

Analisi effettuata sul sorgente **JetEngine 3.9.0** fornito dall'utente. Nessun file JetEngine, Elementor o WooCommerce è modificato.

- `elementor/element/jet-listing-grid/section_custom_query/before_section_end`: controlli subito dopo Use Custom Query e Custom Query, mentre la stessa sezione è ancora aperta. L'hook JetEngine `jet-engine/listing/custom-query-settings` viene emesso dopo la chiusura delle sezioni e solo con query legacy abilitate, quindi non è adatto ad aggiungere questi controlli.
- `jet-engine/listing/grid/query/query`, priorità 20 e tre argomenti: risultati del singolo renderer dopo il callback Query Builder nativo. La sorgente JetEngine è `query`, anche per una Posts Query.
- `elementor/editor/after_enqueue_scripts`: solo CSS/JS editor per radio testuali, esclusione della query principale e avvisi. Nessun asset frontend.
- API: `Manager::get_queries_for_options(false, 'posts')`, `get_query_by_id_for_context`, `Posts_Query::setup_query()`, `get_items()` e `get_query_args()`.

Il fallback viene eseguito su un clone con parametri dinamici ricalcolati nel Current Object originale. La query principale e il singleton del fallback restano invariati, anche con più griglie. La cache della principale rimane nativa; il clone fallback non legge né scrive cache condivise. L'array restituito contiene WP_Post e viene renderizzato dal Listing Grid originale, che gestisce link, dati dinamici, markup e slider. Quando i risultati cambiano viene copiata anche la WP_Query del renderer, aggiornando conteggio e singola pagina localmente. I contatori Query Builder esterni continuano a descrivere la query originale.

Il recupero avanza in blocchi da 20 a 200 risultati, conservando l'offset e filtrando duplicati anche fra blocchi. Massimo 50 blocchi per rendering; se i risultati sono esauriti o si raggiunge questo limite protettivo vengono mostrati quelli disponibili. Nessuna seconda query se la principale è sufficiente o Only when empty ha almeno un risultato.

**Prima versione:** fallback sospeso per Load More, Lazy Load, uso come archivio, numero casuale di elementi, pagine successive e richieste pubbliche AJAX/JSON/REST. **JetSmartFilters installato o attivo non blocca i listing non filtrati.** Il modulo interroga `Query_Builder\Listings\Filters::is_filters_request()` sulle query principale e fallback e sospende il completamento solo se JetEngine segnala filtri applicati a una delle due. Una richiesta filtrata destinata ad altre query non blocca il listing. Se questa API manca, il blocco conservativo riguarda soltanto richieste che trasportano parametri di filtro. Il filtro applicato può quindi lasciare volutamente zero risultati, senza essere riempito da prodotti fuori filtro. Il comportamento nativo della principale resta operativo. La paginazione del dataset composto non è implementata; usare griglie a pagina singola senza widget di paginazione esterni. La paginazione dello slider fra gli elementi già caricati è distinta dalla paginazione delle query.

L'AJAX autenticato dell'editor Elementor è ammesso quando Elementor conferma la modalità di modifica. Se JetEngine non espone `get_query_by_id_for_context`, il modulo non esegue il fallback e lo segnala nell'editor. Non viene aggirato il filtro nativo di autorizzazione del contesto. Il clone usa proprietà pubbliche `final_query`, `final_query_raw`, `parsed_macros`, `cache_query` e lo stato WP_Query del renderer: sono dettagli d'integrazione da ricontrollare dopo aggiornamenti JetEngine.

## Impostazioni dei moduli

Il pannello mostra il nome di ogni plugin richiesto con una spunta verde se attivo o una croce rossa se assente/non attivo, senza diciture aggiuntive. Lo stato è disponibile anche come tooltip e per gli screen reader. Senza dipendenze il toggle è spento e bloccato, e il modulo non è effettivamente abilitato né caricato. Il salvataggio server impedisce nuove attivazioni anche fuori dalla UI. Le preferenze già salvate vengono conservate: riattivando i plugin richiesti il modulo torna disponibile con la preferenza precedente. I moduli autonomi restano utilizzabili; Read More richiede Elementor e usa JetEngine solo per la funzione aggiuntiva Dynamic Field.

## Verifiche locali eseguite

| Verifica | Esito e confine della prova |
| --- | --- |
| Query Fallback PHP | 94 controlli con le classi reali Base_Query/Posts_Query e la registrazione Custom Query del Listing Grid fornito, con dispatcher hook WordPress; database, renderer e stack sezioni simulati. Verificati ordine, sezione ancora aperta e assenza di registrazione sul vecchio hook; principale vuota con 3.000 prodotti fallback in entrambe le modalità e JetSmartFilters installato. Verificati filtri applicati e filtri diretti ad altre query tramite il codice reale JetEngine Filters |
| Dipendenze/pannello PHP | 667 controlli in cinque configurazioni: nessun plugin, solo Elementor, solo WooCommerce, solo JetEngine, tutti |
| Editor JS | Modelli simulati: esclusione principale e cambio ID, placeholder, query eliminata/incompatibile, ciclo di vita Select2, avvisi precisi e API assente. Il solo plugin filtri installato non produce un avviso di sospensione |
| Chrome editor | Template PHP, tema chiaro, CSS e libreria Select2 reali di Elementor; modelli editor simulati. Pannello da 240/280 px, radio leggibili e cliccabili, nessuna sovrapposizione, ricerca, placeholder, selezione e avviso Load More |
| Chrome pannello moduli | HTML reale del pannello prodotto dal fixture PHP: 26 schede, requisiti, toggle spenti/bloccati, moduli autonomi e viewport da 400 px |
| Regressioni | Lifecycle moduli, bootstrap senza traduzioni premature, Dynamic SVG e Read More |
| Sintassi | PHP lint, Node syntax check e diff whitespace sui file coinvolti |

Non è una verifica di un sito WordPress completo con database, editor Elementor, Single Product o slider in esecuzione. Le prove manuali sotto restano necessarie prima dell'uso in produzione; AJAX pubblico, filtri e paginazione composta hanno invece blocchi espliciti in questa versione.

Comandi riproducibili dalla radice repository (sostituire i percorsi):

```powershell
php tests/query-fallback-php-test.php "C:\percorso\jet-engine" "C:\percorso\wordpress"
foreach ($scenario in @('none','elementor','woocommerce','jetengine','all')) {
    php tests/module-dependencies-php-test.php "C:\percorso\wordpress" $scenario
}
php tests/module-lifecycle-php-test.php "C:\percorso\wordpress"
php tests/module-definitions-i18n-test.php
php tests/dynamic-svg-php-test.php
php tests/read-more-php-test.php
node tests/query-fallback-editor-js-test.js
node tests/module-dependencies-browser-test.js "C:\percorso\wordpress"
node tests/query-fallback-editor-browser-test.js "C:\percorso\elementor" "C:\percorso\wordpress"
```

Il test Chrome richiede Playwright disponibile a Node e Chrome installato.

## Procedura manuale su sito di prova

Creare almeno sei prodotti pubblicati con due categorie. Nel template Single Product inserire un Listing Grid con un Listing Item che mostri titolo, immagine e link del prodotto. La principale deve recuperare prodotti della stessa categoria escludendo il Current Object; il fallback gli ultimi prodotti. Impostare 4 elementi per pagina nella principale, disattivare Load More e Lazy Load, e provare anche con JetSmartFilters attivo senza applicare filtri al listing.

| Caso | Operazione e risultato atteso |
| --- | --- |
| 1. Principale sufficiente | Con 4 prodotti principali, entrambe le modalità restituiscono quei 4 e nessuna SQL del fallback |
| 2. Principale vuota | Con zero prodotti principali, entrambe le modalità mostrano fino a 4 elementi fallback |
| 3. Principale insufficiente | Ripetere con 1, 2, 3 prodotti: Only when empty li conserva; Fill arriva a 4 quando possibile |
| 4. Fallback vuoto | Nessun elemento aggiunto, nessun errore, messaggio nativo quando anche la principale è vuota |
| 5. Sovrapposizioni | Mettere i prodotti principali ai primi posti del fallback; il completamento deve proseguire e arrivare a 4 senza duplicati |
| 6. Query eliminata | Salvare il widget, eliminare il fallback e ricaricare editor/frontend: avviso editor e soli risultati principali |
| 7. Modulo spento | Disattivare Query Fallback: nessun controllo/asset/hook operativo del modulo e comportamento JetEngine originale |
| 8. Griglie isolate | Duplicare il widget, usare la stessa principale e due modalità/fallback differenti; verificare che ciascuna conservi la propria configurazione |
| 9. Single Product | Aprire due prodotti di categorie diverse: macro ed esclusione seguono il prodotto corrente; titoli e link appartengono alla scheda corretta |
| 10. Editor/frontend/cache | Confrontare editor, anteprima e frontend anonimo; ripetere con Cache Query principale on/off, query fallback già usata altrove e cache pagina svuotata |
| 11. Slider | Abilitare slider e verificare 4 schede uniche, frecce, swipe, layout responsive e link in entrambe le modalità |
| 12. Combinazioni bloccate | Abilitare a turno Load More, Lazy Load, archivio, applicare un filtro alla query con JetSmartFilters e richiedere pagina 2: resta la principale nativa; nessuna esecuzione fallback, avviso editor ove configurabile |
| 13. Dipendenze | Disattivare JetEngine/WooCommerce/Elementor a turno: ogni scheda interessata dichiara il requisito e mostra toggle OFF bloccato; provare anche un salvataggio AJAX manuale |

Usare Query Monitor o logging temporaneo del sito di prova per distinguere SQL principali e fallback. Il modulo non aggiunge log permanenti né diagnostica frontend.

## File di questa modifica

- `marrison-addon.php`: registrazione Query Fallback, controllo effettivo dipendenze e validazione attivazioni.
- `includes/admin/class-marrison-addon-admin.php`, `assets/css/admin.css`: requisiti sempre visibili e toggle coerenti.
- `includes/modules/class-marrison-addon-query-fallback.php`: nuovo modulo.
- `assets/js/marrison-query-fallback-editor.js`, `assets/css/marrison-query-fallback-editor.css`: soli controlli editor.
- `README.md`, `docs/query-fallback.md`: istruzioni e limiti.
- Repository `tests/`: nuovi test Query Fallback, fixture di template editor nativi, editor JS/browser, dipendenze e browser; conteggio moduli aggiornato nel test lifecycle.
