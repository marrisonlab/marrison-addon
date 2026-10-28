# Login Designer

Modulo indipendente, disattivato per default. Attivarlo in **AM Addon**, aprire le impostazioni della scheda e scegliere **Personalizza login**.

## Architettura e salvataggio

- Il registro esistente carica file e costruttore solo con il modulo attivo. Da spento non vengono registrati hook del modulo, letta la sua option o caricati asset.
- Il Customizer nativo gestisce capability, nonce, changeset, Pubblica e uscita senza pubblicazione. Non vengono alterati autenticazione, cookie, redirect o URL di login.
- Unica option persistente: `marrison_addon_login_designer`, con impostazioni multidimensionali native. I changeset sono quelli standard WordPress. Il selettore preset usa un setting UI con tipo personalizzato senza handler di persistenza e non crea un'altra option.
- Tutti i valori vengono normalizzati attraverso uno schema comune: colori esadecimali, enum chiuse, numeri limitati, URL HTTP/HTTPS e testo senza tag. Il CSS richiede `edit_css`; tutte le impostazioni richiedono `manage_options`, l'apertura del Customizer richiede anche `customize`.
- La pagina frontend `?marrison_login_preview=1` risponde solo agli utenti autorizzati, senza cache e con noindex. Non usa template del tema, non chiama hook di autenticazione e contiene esclusivamente campi dimostrativi senza credenziali o azioni di login.
- CSS statico e protocollo delle variabili condivisi fra anteprima e login reale; le modifiche `postMessage` aggiornano le variabili senza AJAX o refresh. Il CSS avanzato ha un elemento style separato, così anche cancellarlo è visibile immediatamente.
- Il login reale carica un solo foglio CSS locale e gli stili inline; nessun JavaScript del modulo. Gli hook nativi coprono anche recupero e reset password. Nessuna scrittura sul filesystem o modifica al core.

## Uso

Le sezioni coprono logo, sfondo/gradiente/overlay, quattro layout, area grafica split, form, input, pulsante, font e messaggi, CSS avanzato. Le immagini usano i controlli immagine e la Media Library nativi WordPress.

I sei preset sostituiscono le impostazioni dopo conferma e restano in bozza. **WordPress Classic / Ripristina predefiniti** ripristina esclusivamente Login Designer; verificare il risultato e premere **Pubblica** per applicarlo. Uscire dal Customizer e scartare le modifiche per annullarle. Non selezionare un changeset salvato in precedenza se si vuole ripartire dall'ultima pubblicazione.

Usare i selettori desktop/tablet/mobile del Customizer. Sotto 782px l'area grafica split scompare e il form torna centrato con margini fluidi.

## Verifiche locali

- `php tests/login-designer-php-test.php`: sanitizzazione e tentativi di iniezione, preset completi, option in cache, capability, hook e contratto dei setting.
- `node tests/login-designer-js-test.js`: aggiornamenti live layout, dimensioni, fallback logo, rimozione CSS personalizzato, pulsante.
- `php tests/module-definitions-i18n-test.php`: registro e bootstrap degli altri moduli.
- PHP lint e controllo sintattico JavaScript sui file coinvolti.

## Verifiche su installazione WordPress ancora necessarie

Questi controlli non sostituiscono un collaudo end-to-end con database e browser. Verificare su tema classico, tema a blocchi e Hello Elementor:

1. Attivare e disattivare, verificare che da spento la classe e gli asset non siano caricati.
2. Aprire il Customizer, cambiare valori senza pubblicare e verificare che il login in finestra anonima resti invariato.
3. Pubblicare e confrontare anteprima e login reale; uscire con altre modifiche e scartarle.
4. Provare tutti i layout alle tre larghezze e ciascun preset, modificare i valori e ripristinare Classic.
5. Eseguire login, recupero e reset password reali; verificare eventuali 2FA/CAPTCHA e campi aggiunti da plugin.
6. Verificare il rifiuto della preview da anonimo e la restrizione CSS senza `edit_css`.

L'anteprima riproduce il form standard; non esegue hook di plugin di autenticazione e quindi non mostra i loro campi/messaggi. Gli stili non nascondono quegli elementi sul login reale. Il preset Split parte con un colore locale: l'immagine può essere selezionata dalla propria libreria, senza risorse esterne. Il supporto ai temi è architetturale (nessun template o asset del tema), ma la matrice dei tre temi non è stata collaudata in questa sessione. La trasparenza usa colori RGBA generati nello stesso modo sul server e nell’anteprima. Ogni sfondo (pagina, gradiente, area grafica, form, input, pulsante/hover e messaggi) ha un controllo Opacità: 0% trasparente, 100% pieno. L’opacità riguarda solo il colore, senza rendere trasparenti testo e contenuti o le immagini di sfondo.

ZIP locale aggiornato; nessun rilascio o deployment effettuato.

Gli input hanno controlli separati per dimensione del testo e padding verticale/orizzontale. I colori vengono applicati anche allo stato di compilazione automatica del browser. I controlli dell’area grafica sono visibili solo con il layout Due colonne; sotto 782 px quell’area viene nascosta.

### Selettore lingua

In **Layout e posizione del form**, attivare **Nascondi selettore lingua** e premere **Pubblica**. Il modulo usa il filtro nativo `login_display_language_dropdown` e mantiene la scelta degli altri plugin quando l’opzione è disattivata. In anteprima il selettore è dimostrativo e compare solo se sono installate lingue aggiuntive. Disattivando Login Designer il filtro del modulo non viene registrato.

## Audit di isolamento e carico

- Modulo spento: il registro non include il file PHP e non istanzia la classe. Nessun hook o asset del modulo, nessuna lettura esplicita della sua option. Resta il registro condiviso del plugin.
- Modulo acceso, frontend ordinario: nessun asset, nessuna lettura delle impostazioni, nessun callback su template_redirect. Rimane un solo hook per la registrazione differita del Customizer, necessario anche alla pubblicazione dei changeset programmati.
- Backend ordinario: registrazione del submenu e dei gestori del modulo, senza lettura impostazioni o caricamento di asset specifici. Il pannello AM Addon conserva gli asset amministrativi condivisi già esistenti.
- Customizer di altri contenuti: nessuna registrazione dei controlli o delle impostazioni Login Designer. Gli aggiornamenti dei changeset del modulo restano supportati anche senza URL dell’editor.
- Login, recupero e reset: i soli hook login caricano CSS locale e inline; nessun JavaScript del modulo e una sola lettura esplicita dell’option per richiesta, poi cache nell’istanza.
- Anteprima: endpoint registrato solo quando richiesto, capability verificate prima di leggere le impostazioni o accodare asset. CSS avanzato emesso solo in questo documento o nel login.
- Option esclusa dall’autoload durante apertura delle impostazioni e salvataggio. Quando spegni il modulo, la sua unica option `marrison_addon_login_designer` viene eliminata tramite il ciclo di disattivazione condiviso. Anche la disattivazione di Marrison Addon la elimina, compresi eventuali residui quando il modulo è già spento e la classe non è caricata. Alla riattivazione si riparte dai predefiniti. Le opzioni degli altri moduli restano invariate. Nessun controllo o query di pulizia viene eseguito nelle richieste ordinarie con modulo spento.
- Per WordPress 6.0–6.3 la correzione autoload durante la configurazione usa una query limitata alla sola option, poiché manca l’API dedicata. La cancellazione usa sempre `delete_option` e controlla che sia riuscita.
- Gli asset del modulo usano versione e data di modifica del file per evitare CSS/JS obsoleti nella cache dopo gli aggiornamenti ZIP locali; il calcolo viene eseguito solo dove questi asset sono richiesti.

Verifiche: `php tests/login-designer-scope-php-test.php` esegue il bootstrap reale del plugin con API WordPress simulate e controlla file/classi/hook, letture delle option e asset nei vari contesti, rifiuto della preview non autorizzata e cancellazione dei dati del modulo durante spegnimento/disattivazione del plugin, gestione degli errori di cancellazione e conservazione delle opzioni estranee. I test non misurano tempi o query SQL su un sito live; non sostituiscono un profiling con WordPress/database reali.

### Ombra e glow

In **Contenitore form** sono disponibili colore, offset orizzontale/verticale, sfocatura, espansione e opacità. Per un glow visibile su sfondo scuro scegliere un colore luminoso, offset X/Y 0, sfocatura 28 px, espansione 4 px, opacità 85%. Il nero al 4% dei predefiniti è una normale ombra leggera e risulta quasi invisibile su uno sfondo scuro. Colore e alpha usano lo stesso protocollo RGBA su server e anteprima.

Test browser locale: `node tests/login-designer-glow-browser-test.js` (Playwright e Chrome) verifica il box-shadow calcolato e produce `audit/login-designer-glow.png` per il controllo visivo. È una fixture locale, senza autenticazione o installazione WordPress live.

### Correzioni 1.3.93

Le richieste AJAX native del Customizer vengono riconosciute anche quando i nuovi setting sono presenti solo in `customize_changeset_data`, prima che WordPress li integri nei valori del manager. Questo evita avvisi di impostazione non riconosciuta nei salvataggi automatici iniziali; restano invariati i controlli nativi di validazione e autorizzazione. Il logo, predefinito o personalizzato, rimanda alla home quando non è impostato un URL specifico per il logo personalizzato. I link dell’anteprima restano inerti per mantenere aperto l’editor.
