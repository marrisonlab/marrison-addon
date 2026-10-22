# Confronto con Lightweight Cookie Notice Free 1.21

Analisi statica del pacchetto indicato dall'utente: `C:/Users/Angelo/Desktop/lightweight-cookie-notice-free`. Il pacchetto di riferimento non è stato modificato, installato o eseguito. Questa verifica non costituisce una correzione o una verifica live di Marrison su CERIS.

## Risultato

Questa edizione contiene un banner, un pannello preferenze, categorie e un inventario di cookie compilato dall'amministratore. Non contiene un crawler o un motore che misuri i cookie dei servizi esterni. Il termine "cookie audit tables" del readme indica la pubblicazione dell'inventario configurato, non una scansione automatica.

## Percorsi verificati

| Funzione | Implementazione nel riferimento | Conseguenza per Marrison |
| --- | --- | --- |
| Inventario | `admin/inc/menu/child/class-daextlwcnf-cookies-menu-elements.php`, `process_form()` legge nome, fornitore, dominio, finalità e durata dal modulo POST e li salva nella tabella dedicata. | L'elenco dei cookie visualizzati non prova che siano stati rilevati nel browser. |
| Dati pubblici | `shared/class-daextlwcnf-shared.php`, `get_cookie_notice_data()` legge cookie, categorie e sezioni dal database. | Il pannello mostra i dati precedentemente configurati. |
| Barra | `public/assets/js/daextlwcnf-cookie-notice.js` genera HTML e CSS: contenitore con larghezza massima, testo e pulsanti in due aree, regole esplicite per font, interlinea, padding e breakpoint. | Utile come riferimento di struttura e isolamento dal tema; non occorre copiarne i calcoli a larghezze fisse. |
| Mobile | Lo stesso file passa il contenitore in colonna e assegna tutta la larghezza a testo e pulsanti sotto il breakpoint. | Il layout deve riservare spazio leggibile ai testi e ai pulsanti, senza restringere il testo in colonne minime. |
| Preferenze | `public/assets/js/daextlwcnf-cookie-settings.js` presenta sezioni/categorie e le tabelle dei cookie configurati. | La parte visuale è separata dall'acquisizione dell'inventario. |
| Salvataggio | `public/assets/js/daextlwcnf-utility.js`, `acceptCookies()` e `setDefaultCategoryCookie()` salvano accettazione e categorie; `saveCategoryAcceptance()` registra la scelta tramite endpoint del sito. | Un cookie di consenso non dimostra il rilevamento di cookie esterni. |
| Script | `functions.php`, `daextlwcnf_print_scripts()` emette gli script configurati nella categoria soltanto quando accettazione e relativa categoria sono abilitate; gli hook pubblici richiamano questa funzione in head/footer. | Il codice dimostra un'integrazione esplicita per categoria, non il riconoscimento automatico di qualsiasi servizio già inserito dal tema o da altri plugin. |
| Risorse | `public/class-daextlwcnf-public.php`, `enqueue_scripts()` carica un bundle in produzione oppure i sorgenti separati; `wr_public_footer()` riutilizza un transient per la configurazione. | Sono scelte utili per contenere richieste e lavoro ripetuto. Il requisito Marrison del modulo disattivato resta distinto e deve continuare a valere. |

La lettura di `document.cookie` nell'utility serve a leggere le preferenze del visitatore. Non è un inventario trasversale ai domini. Le chiamate di rete applicative trovate riguardano configurazione, consenso e geolocalizzazione; non un servizio di scansione remoto.

## Applicazione al problema CERIS

Il confronto offre indicazioni per semplificare la barra e il pannello preferenze, ma non fornisce una soluzione al rilevamento effettivo dei cookie installati nei domini dei fornitori esterni. Le osservazioni del browser, il riconoscimento di un servizio e un inventario dichiarato devono restare distinti. Aggiungere cookie documentati di Google all'elenco dei cookie osservati, oppure contare soltanto i cookie del banner, non risolverebbe la contestazione dell'utente.

Non sono stati cambiati codice di Marrison, versione o ZIP durante questo confronto. Le modifiche locali già presenti nel workspace e la versione installata su CERIS non sono state alterate da questa analisi.
