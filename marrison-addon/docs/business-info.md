# Business Info e Cookie Manager

Attiva **Business Info** da AM Addon e apri **Info Azienda**. Il modulo funziona senza page builder. L’archivio unico è l’opzione `marrison_business_info`, non caricata automaticamente da WordPress; non vengono create tabelle, pagine o Option Page JetEngine.

## Compilazione

Le schede raccolgono Azienda, Contatti, Sedi, Social, Campi personalizzati, Orari di apertura e Privacy e titolare. Sono già predisposti email principale, telefono ufficio, cellulare, WhatsApp, fax e sede principale. Puoi aggiungere altri elementi senza un limite imposto dal modulo. Il logo usa la libreria media WordPress.

Lo **scudo** contrassegna i dati utilizzabili dal Cookie Manager, compresi quelli facoltativi. Il riepilogo segnala nome del titolare, indirizzo ed email mancanti; non impedisce di salvare né di pubblicare una policy. “Dati anagrafici essenziali presenti” non è una dichiarazione di completezza o conformità della policy: le impostazioni sui trattamenti devono essere verificate nel Cookie Manager. DPO e rappresentante UE sono da compilare solo se nominati.

**Privacy e titolare** contiene il campo **Titolare del trattamento — nome / ragione sociale**, sempre compilabile, i selettori di email, telefono e sede per la policy, DPO e rappresentante UE. Il nome compilato viene usato nella policy senza dover duplicare indirizzo e recapiti; se vuoto, segue ragione sociale o nome commerciale. **Usa dati dedicati per il titolare** permette di sostituire singoli dati: i campi dedicati vuoti mantengono i valori aziendali selezionati. I selettori memorizzano solo chiavi, mai copie dei recapiti. “Predefinito” segue il predefinito della categoria, oppure il primo elemento disponibile; un riferimento esplicito a un elemento eliminato rimane vuoto e non sceglie un altro recapito. La selezione predefinita del telefono preferisce i telefoni e poi i cellulari. Per usare WhatsApp o fax nella policy sceglili esplicitamente.

Ogni record ha un identificatore interno stabile (UUID per i nuovi record) e una chiave leggibile, generata dall’etichetta nei nuovi elementi e personalizzabile prima del primo salvataggio. La chiave salvata è bloccata anche dal server. Riordinare gli elementi non cambia i collegamenti. Le chiavi sono univoche nel gruppo; per nomi presenti in più gruppi usa sempre il percorso completo. Eliminare un record può lasciare vuoti i contenuti che lo richiamano. Il salvataggio trasmette un unico JSON, evitando il limite `max_input_vars` dei repeater; restano validi i limiti generali PHP come `post_max_size`. Una validazione o scrittura fallita mantiene l’ultima versione salvata e, quando l’archivio temporaneo è disponibile, conserva una bozza per lo stesso amministratore per dieci minuti.

## Shortcode e API PHP

```text
[marrison_info key="email_principale"]
[marrison_info key="contacts.email_principale" output="link"]
[marrison_info key="locations.sede_principale"]
[marrison_info key="locations.sede_principale.city"]
[marrison_info key="social.instagram" output="link"]
[marrison_info key="custom.campo_aggiuntivo" fallback=""]
[marrison_info key="privacy.controller_email" output="link"]
[marrison_info key="privacy.dpo"]
[marrison_info key="logo" output="html"]
[marrison_business_hours group_days="yes" hide_closed="yes" format="24"]
[marrison_business_hours layout="compact" short_days="yes" first_day="7"]
[marrison_business_status icons="no" closed_text="Chiuso"]
```

`output="value"` restituisce testo sicuro; `link` produce link URL, mailto, tel o WhatsApp secondo il tipo; `html` conserva solo gli a-capo, oppure rende l’immagine per il logo. Nessun HTML arbitrario viene eseguito dai valori. Il logo in modalità value restituisce l’URL. Campi mancanti e alias ambigui restituiscono vuoto o il fallback esplicito.

Per gli orari sono disponibili `layout="list|compact|status"`, `hide_closed`, `group_days`, `short_days`, `icons`, `separator`, `closed_text`, `first_day="1..7"` e `format="24|12"`. Gli switch accettano `yes/no` o `1/0`. La settimana mostra gli orari ordinari; le variazioni future per data sono elencate separatamente.

```php
if ( Marrison_Addon::is_module_enabled( 'business_info' )
    && class_exists( 'Marrison_Addon_Business_Info_Store', false ) ) {
    $email = Marrison_Addon_Business_Info_Store::get( 'contacts.email_principale' );
    $identity = Marrison_Addon_Business_Info_Store::controller();
    $state = Marrison_Addon_Business_Hours::status();
}
```

Le API PHP restituiscono valori non escapati: il chiamante deve effettuare l’escaping per il proprio contesto. Le Options API di WordPress evitano query ripetute durante la richiesta. L’hook `marrison_addon/business_info_updated` viene emesso solo dopo un salvataggio confermato e può essere usato per invalidazioni della cache del sito.

## Orari

Ogni giorno ammette più fasce e pause. “Applica a tutti” e “Applica ai feriali” copiano dati indipendenti e richiedono conferma se la destinazione è già configurata. Le eccezioni hanno una data univoca e prevalgono sulla settimana. Date, ore, fasce invertite o sovrapposte vengono rifiutate senza sovrascrivere i dati validi. Fasce adiacenti senza pausa sono unite al salvataggio.

Il calcolo usa il fuso configurato in WordPress, comprese le variazioni dell’ora legale. Gli intervalli sono semiaperti: all’ora di apertura l’attività risulta aperta, all’ora di chiusura è chiusa o in pausa. Gli intervalli oltre mezzanotte e la chiusura `24:00` non sono supportati: il controllo backend accetta ore tra `00:00` e `23:59` e una chiusura successiva all’apertura nello stesso giorno. La ricerca della prossima apertura considera anche aperture straordinarie lontane, senza un limite fisso di giorni.

## Elementor e JetEngine

Il widget **Marrison Business Hours** usa i controlli nativi Contenuto/Stile: layout verticale, compatto e solo stato; giorni, orari, righe, contenitore, stato e icone; font/colori globali, responsive, sfondi, bordi, radius e padding. Margini e Avanzato sono forniti da Elementor. Il widget salva solo la presentazione e dichiara il contenuto dinamico.

Nel gruppo **Marrison Business Info** trovi tag Testo, Link/email/telefono, Logo e Orari/stato. I campi sono scelti da elenchi leggibili. La funzione Dynamic Tags è definita nel core Elementor, ma l’editor gratuito non attiva tutti i controlli dinamici: il widget e gli shortcode funzionano anche senza Pro; i tag sono utilizzabili nei controlli effettivamente abilitati da Pro o dalle integrazioni presenti. Non viene aggirato questo limite del builder. Riferimento: [Dynamic Tags, Elementor Developers](https://developers.elementor.com/docs/dynamic-tags/).

Nel Dynamic Field JetEngine il menu **Sorgente** include direttamente `Business Info / etichetta del campo`. L’integrazione usa i filtri pubblici `jet-engine/listings/data/sources` e `jet-engine/listings/dynamic-field/field-value`, verificati nel sorgente locale JetEngine **3.8.15.4** e nell’[esempio ufficiale Crocoblock](https://gist.github.com/Crocoblock/4d3c3c77f741be82306b3e4103aa16e4). Non richiede Option Page e non sostituisce renderer o contesto del Listing. Il logo usa il tag immagine Elementor; non viene aggiunto al Dynamic Field testuale. Versioni JetEngine prive di questi hook possono usare gli shortcode o i Dynamic Tags nei controlli compatibili. L’integrazione non estende tutte le UI di Gutenberg/Bricks: dipende dai consumatori nativi di questi hook.

## Policy e consenso

Gli shortcode esistenti `[marrison_privacy_policy]`, `[marrison_cookie_policy_content]` e `[marrison_cookie_policy]` restano disponibili. In **Cookie Manager → Privacy e policy**, oppure nello step privacy del wizard, scegli l’origine dei dati: **Inserisci qui (Cookie Manager)** o **Usa Info Azienda**. Il profilo `marrison_cookie_privacy_profile` conserva la scelta (`identity_source`), i dati manuali e i trattamenti. La modalità manuale è quella predefinita e usa anche i dati manuali già salvati. La modalità Info Azienda legge i valori correnti al rendering, senza copiarli nei campi manuali. Cambiare sorgente conserva entrambi gli archivi. `[marrison_cookie_policy]` mantiene il significato di elenco cookie/preferenze.

Si può pubblicare la policy anche con dati incompleti: vengono omessi i dati mancanti, senza segnaposto tecnici né blocchi. Cookie Manager resta configurabile senza Business Info tramite i propri campi manuali. Se la sorgente scelta è Info Azienda e il modulo viene spento, un avviso invita a scegliere i dati manuali o a riattivarlo; non vengono mescolate identità di sorgenti diverse. Riattivare Business Info rende nuovamente disponibili i dati del suo archivio.

`snapshot()` fotografa l’identità effettiva corrente per nuove evidenze. I file storici e le ricevute già salvate non vengono modificati. Il contratto `consent_version()` rimane basato sulla revisione esplicita e sulle sorgenti dei servizi: modificare un recapito non forza una nuova scelta di consenso.

## Cache e aggiornamenti

Lo stato di apertura e i tag temporali non incorporano nel documento HTML uno stato che potrebbe essere vecchio. Mostrano un messaggio di verifica e richiedono l’endpoint pubblico `admin-ajax.php?action=marrison_business_hours`, senza nonce in scadenza e senza dati riservati. L’endpoint usa header no-cache; il browser usa `cache: no-store` e un parametro variabile. Lo stato si aggiorna entro un minuto e ai cambi di fascia; al ritorno sulla scheda viene richiesto nuovamente. Richieste simultanee uguali sono accorpate. In caso di errore viene rimosso lo stato precedente e compare “Stato non disponibile”. Anche gli orari settimanali/eccezioni del widget vengono aggiornati dalla risposta.

JavaScript e raggiungibilità dell’endpoint sono necessari per lo stato corrente. Senza JavaScript resta visibile la settimana renderizzata e una spiegazione, senza una falsa indicazione di apertura. Cache/CDN esterne devono escludere l’endpoint AJAX e non riscrivere la sua risposta. Per disponibilità oraria precisa configura un fuso geografico WordPress (es. `Europe/Rome`), non un offset fisso.

Per normale testo aziendale, tag Testo/URL/Logo e policy, una cache HTML esterna può continuare a servire l’intera pagina già generata. **La lettura dinamica PHP non può aggiornare un documento che non arriva a WordPress.** Configura invalidazione su `marrison_addon/business_info_updated` o escludi le pagine interessate dalla cache. Per i widget/Listing JetEngine evita cache del markup indipendenti che non invalidano i valori Business Info. Non vengono cancellate indiscriminatamente cache o documenti di altri moduli.

## Verifiche locali

```text
php tests/business-info-php-test.php <wordpress-root>
php tests/business-info-integrations-php-test.php <wordpress-root-con-elementor-e-jetengine>
php tests/business-hours-widget-controls-php-test.php
node tests/business-info-browser-test.js <wordpress-root>
php tests/cookie-shortcodes-php-test.php <wordpress-root>
php tests/module-lifecycle-php-test.php <wordpress-root>
```

I test usano Options API simulate e il dispatcher/shortcode parser WordPress reale, senza modificare database di siti locali o remoti. Coprono sanitizzazione, UUID/chiavi immutabili, contatti e sedi multiple, riordino/eliminazione, default, link, indirizzi, campi personalizzati, pausa e confini delle fasce, eccezioni, fuso/ora legale, prossime aperture, pubblicazione vuota, aggiornamento policy, snapshot/contratto del consenso e salvataggio protetto con nonce/capability e recupero di errori database.

L’integrazione esercita le classi reali Tag/Data_Tag e Widget_Base Elementor **4.3.3**, e il vero renderer Dynamic Field JetEngine **3.8.15.4**, con adattatori WordPress isolati. La cattura controlli verifica sezioni native, font/colori globali e responsive. Il browser verifica UI a 1280 e 390 px, selettori e icone privacy, primo salvataggio, copie indipendenti/conferma, serializzazione, risposta fresca, cambio stato ed errore di rete. Queste prove non equivalgono a un editor Elementor completo o a un collaudo su un sito distribuito.

Restano da verificare sul sito di destinazione: controlli e CSS effettivi nell’editor Elementor/Pro, Media Library, cache/CDN specifiche, salvataggio sul database reale, Listing complessi e pubblicazione finale delle policy con il profilo dei trattamenti effettivo.

## File introdotti e modificati

Nuovi file del plugin (percorsi relativi alla cartella `marrison-addon/`):

- `includes/modules/class-marrison-addon-business-info.php`: bootstrap e registrazioni condizionali.
- `includes/modules/business-info/class-business-info-store.php`: archivio, campi, chiavi, risoluzione identità e accesso PHP.
- `includes/modules/business-info/class-business-info-admin.php`: pagina, asset, nonce/capability, salvataggio e recupero errori.
- `includes/modules/business-info/class-business-hours.php`: validazione e calcolo condiviso degli orari.
- `includes/modules/business-info/class-business-info-display.php`: shortcode, markup e risposta pubblica senza cache.
- `includes/modules/business-info/class-business-info-tags.php`: tag Elementor testo, URL, immagine e orari.
- `includes/modules/business-info/class-business-info-jet-engine.php`: sorgenti e risoluzione Dynamic Field.
- `includes/modules/business-info/widgets/business-hours-widget.php`: widget e controlli Elementor.
- `includes/modules/business-info/assets/admin.js` e `admin.css`: schede, repeater, scudi, copie degli orari e serializzazione.
- `includes/modules/business-info/assets/frontend.js` e `frontend.css`: visualizzazione, aggiornamento dello stato e gestione errori di rete.
- `docs/business-info.md`: configurazione, API, limiti e verifiche.

File esistenti aggiornati:

- `marrison-addon.php`: registro Business Info e descrizione del plugin.
- `includes/modules/cookie-manager/includes/class-policy-content.php`: scelta tra identità manuale e Info Azienda, risoluzione al rendering e avviso dati mancanti.
- `includes/modules/cookie-manager/templates/privacy-fields.php`, `policy-shortcodes.php`, `setup-wizard.php`: indicazioni sul nuovo flusso, collegamento a Info Azienda e avvisi non bloccanti.
- `README.md`, `readme.txt` e `../README.md`: descrizione e changelog Unreleased.

Nuovi test nella cartella `tests/` del repository: `business-info-php-test.php`, `business-info-integrations-php-test.php`, `business-hours-widget-controls-php-test.php`, `business-info-browser-test.js`. Aggiornati `cookie-shortcodes-php-test.php` per la nuova fonte unica e `module-lifecycle-php-test.php` / `module-definitions-i18n-test.php` per il nuovo modulo.
