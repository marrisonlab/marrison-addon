# Marrison Addon

Modular tools for WordPress, Elementor, WooCommerce and JetEngine.

**Author:** [Angelo Marra](https://marrisonlab.com)  
**Version:** 1.3.93
**WordPress:** 6.0 or later; tested with 7.1.3  
**PHP:** 7.4 or later; current compatibility target 8.5.11  
**License:** [GPL-3.0-or-later](https://www.gnu.org/licenses/gpl-3.0.html)

## Description

Marrison Addon provides 28 independently enabled modules for page layouts, media, frontend behavior and administration. Disabled modules load no operational module classes, hooks, CSS or JavaScript. The core registry and plugin bootstrap remain available.

Query Fallback adds a backup Posts Query to the native JetEngine Listing Grid, with empty-only and fill modes. Module cards always show required active plugins and keep unavailable modules off and locked. See [Query Fallback configuration and limitations](marrison-addon/docs/query-fallback.md).

Modules include Wrapped Link, Content Ticker, Steps, Product Discount, Heading Animations, Recently Viewed Products, Listing Grid Title, Listing Columns, Dynamic SVG, Read More, Anchor Offset, Preloader, Custom Cursor, Image Sizes, Fast Logout, Calendar Sync, Cookie Manager, Video Thumbnail, Local Google Fonts, Browser Cache, Horizontal Scroll (Beta), Liquid Background, Inline CSS, Dynamic Background Image Size, Login Designer and PHP Snippets.

Elementor, WooCommerce and JetEngine are required only for their related modules. Cookie Manager provides representative HTTP/browser scans, signed consent choices and policy shortcodes. Manual browser scans cover every discovered group before and after consent by default; sample and total-time limits are optional. PHP Snippets requires both WordPress `manage_options` and `unfiltered_html` capabilities.

Business Info adds **Info Azienda**: a shared company/contact/location directory, custom fields, privacy identities (including DPO and EU representative), opening hours, Elementor tags/widget, JetEngine Dynamic Field sources and universal shortcodes. Shield icons identify Cookie Manager inputs. Cookie Manager can use its own manual identity fields or explicitly read current values from Info Azienda, and remains configurable with Business Info disabled. See [setup, cache limits and validation](marrison-addon/docs/business-info.md).


Cookie consent checks keep technical diagnostics out of the visitor banner; failed saves remain visible and do not enable tracking. Receipt protection uses the actual server's configuration. All Privacy Policy profile fields are optional. The shortcode renders saved data even when other fields are empty, omitting unspecified details without blocking publication.

[Full documentation and release history](marrison-addon/README.md) · [WordPress-format readme](marrison-addon/readme.txt)

Cookie Manager 1.3.79 adds a local declarative service catalog, editable service categories and default blocking for unclassified external resources. Catalog declarations remain separate from observed cookies. See [configuration and limits](marrison-addon/docs/cookie-service-catalog.md). Scanning uses the local static service inventory. Each new run replaces the previous cookie inventory; consent receipts and settings remain unchanged.

Login Designer aggiunge un editor nativo Customizer con anteprima riservata, quattro layout responsive e sei preset. Le modifiche si applicano al login soltanto dopo Pubblica. Spegnendo il modulo o disattivando Marrison Addon, le impostazioni di Login Designer vengono eliminate. [Architettura, uso e verifiche](marrison-addon/docs/login-designer.md).

## Installation

1. Download [marrison-addon.zip](marrison-addon.zip), or obtain the code for the desired [published release](https://github.com/marrisonlab/marrison-addon/releases).
2. In WordPress, open **Plugins > Add New > Upload Plugin** and upload the installable ZIP. A source checkout contains the plugin in the `marrison-addon/` subdirectory.
3. Activate Marrison Addon and open **AM Addon** to enable the desired modules.
4. Open each module's compact settings icon where available. Plugin updates do not require a new Cookie Manager scan; existing scan results are preserved.

## GitHub releases and updates

The active plugin checks the latest published stable release of this repository in WordPress admin/WP-Cron. A newer release appears in the native WordPress plugin update list; version checks are cached for six hours.

For a release:

1. Align the PHP version header/constant, both readmes' stable version and changelog.
2. Build `marrison-addon.zip` from the plugin directory, excluding development files, and commit/push the updated sources.
3. Create a lowercase tag such as `v1.3.68` on that commit and publish a stable GitHub Release as **Latest**.

A tag without a Release is not detected. The updater downloads the source code at the tag, not an attached ZIP. Keep the tagged PHP version consistent with the tag to avoid repeating the same update.

## Compatibility

Current stable versions checked on 20 October 2026: [WordPress 7.1.3](https://wordpress.org/download/releases/) and [PHP 8.5.11](https://www.php.net/). Minimum requirements remain WordPress 6.0 and PHP 7.4. Release candidates are excluded from the stable target.

Optional server features are reported by their modules: FFmpeg is needed for local video cover extraction; browser cache rules depend on the web server. These features are not prerequisites for unrelated modules.

## Changelog

### 1.3.93

* Login Designer: corretta la registrazione dei controlli nelle richieste AJAX di salvataggio automatico e pubblicazione del Customizer, evitando gli avvisi di impostazione inesistente o non riconosciuta.
* Login Designer: il logo del login punta alla home del sito quando non è configurata una destinazione personalizzata; aggiornata l’etichetta del controllo.
* Mantenuti isolamento dagli altri Customizer, controlli di autorizzazione e sanitizzazione; aggiunti test sulle richieste AJAX e sulle destinazioni del logo.

### 1.3.92

* Login Designer: nuovo modulo indipendente con Customizer nativo, anteprima live riservata, pubblicazione esplicita, quattro layout responsive e sei preset.
* Controlli per logo, sfondi con opacità, form, input, pulsante, tipografia e CSS avanzato; aggiunti font-size e padding input, ombra/glow colorato con espansione e opzione per nascondere il selettore lingua.
* Colori input applicati anche alla compilazione automatica del browser; focus discreto e controlli dell’area grafica visibili soltanto nel layout Due colonne.
* Asset limitati a login e relativo editor; nessun JavaScript del modulo sul login reale. Impostazioni escluse dall’autoload e nessun caricamento operativo del modulo quando spento.
* Spegnendo Login Designer o disattivando Marrison Addon vengono eliminate tutte le impostazioni di Login Designer: alla riattivazione si riparte dai predefiniti. Le impostazioni degli altri moduli restano invariate.
* Corretta la disattivazione del plugin senza Elementor; aggiornamento della versione degli asset per evitare cache obsolete nei pacchetti locali.

### 1.3.91

* Browser Cache: conserva un solo backup automatico di `.htaccess`, aggiornato a ogni modifica delle regole. Elimina i precedenti backup del modulo soltanto dopo la scrittura riuscita.
* Alla successiva apertura del pannello AM Addon elimina anche i backup accumulati dalle versioni precedenti, conservando il più recente. Gli altri file e backup restano invariati; eventuali errori di pulizia vengono segnalati.

### 1.3.90

* PHP Snippets: ripristina gestione e attivazione con DISALLOW_FILE_EDIT=true e login WordPress già valido, senza password aggiuntive o firma manuale.
* Recupera una sola volta, al primo accesso amministrativo autorizzato, gli snippet senza firma sospesi dal precedente aggiornamento; conserva le disattivazioni manuali, gli errori PHP reali e i controlli di integrità successivi.
* Corretto il campo Nome con lo stile nativo WordPress; pulsanti allineati. DISALLOW_FILE_MODS e modalità di recupero restano rispettati.

### 1.3.89

* PHP Snippets: allinea i pulsanti e mostra una sola conferma password quando necessaria; nessun campo password con autenticazione recente o configurazione in sola lettura.
* Conserva la bozza se la conferma scade durante la scrittura e indica esattamente le costanti WordPress che bloccano le modifiche. I blocchi DISALLOW_FILE_EDIT/DISALLOW_FILE_MODS restano rispettati.

### 1.3.88
* Aggiunto Business Info / Info Azienda: archivio condiviso di dati aziendali, contatti, sedi, campi personalizzati, identità privacy e orari, con shortcode e integrazioni Elementor e JetEngine.
* Titolare del trattamento sempre compilabile in Info Azienda, con riutilizzo di indirizzo e recapiti selezionati. Sistemata l’impaginazione della scheda Privacy e titolare su desktop e mobile.
* Cookie Manager: scelta tra dati manuali nelle impostazioni e dati aggiornati da Info Azienda, anche nel wizard. La modalità manuale funziona con Business Info disattivato; il cambio di sorgente conserva i dati manuali e le ricevute storiche.

### 1.3.87
* Consenso completo coerente: Accetta tutti e Personalizza con tutte le categorie abilitano anche le risorse non classificate, compreso il widget Trustindex. Blocco conservato prima del consenso, con scelta parziale e alla revoca/scadenza.
* Verificato il widget reale con override dei file nel solo browser di prova; nessuna modifica ai file del sito live.

### 1.3.86
* Comandi diretti per analisi statica e verifica browser in Scanner e wizard, con campioni/tempo per singolo avvio e controllo del supporto del browser.
* Riepilogo scanner coerente con analisi statica e iframe opzionale: contenuti analizzati, verifica browser disattivata e cookie documentati separati. Nessun falso limite dei campioni nella modalità statica.

### 1.3.85
* Ogni nuova scansione sostituisce il vecchio inventario cookie, anche con risultati parziali o vuoti. Ricevute e impostazioni restano invariate.
* Rapporti remoti precedenti distinti e non riprendibili.

### 1.3.84
* Fase 6: Firme e riautenticazione PHP Snippets, log, recupero file, costanti WordPress e verifica checksum updater.

### 1.3.83
* Fase 5: Hardening endpoint e output, rate limit, ricevute limitate e non indovinabili, controllo HTTP Site Health.

### 1.3.82
* Fase 4: Default consenso 180 giorni, modalità solo tecnici e checklist dichiarativa non bloccante.

### 1.3.81
* Fase 3: Consent Mode v2 base, opzione avanzata esplicita, revoca Meta e caricamento YouTube per singolo contenuto.

### 1.3.80
* Fase 2: Scansione statica locale riprendibile, verifica iframe opzionale limitata e rilevamento passivo degli host su esplicita attivazione.

### Unreleased
* Add Listing Columns, an optional Elementor widget for JetEngine grids with a 1–12 column range, CSS target selector, browser preference, responsive activation, SVG/native icons and Elementor style controls.

### 1.3.79
* Cookie Manager, Phase 1: validated local catalog for ten services, provider review badges, editable categories and unclassified-host blocking. Preserve observed-cookie counts and custom source rules.

### 1.3.78
* Fix clipped Query Fallback radio controls and Select2 placeholder/lifecycle; show the actual incompatible setting in editor warnings.
* Allow unfiltered listings to use fallback while JetSmartFilters is installed. Suspend only requests with filters applied to the primary or backup query, using the native JetEngine check.

### 1.3.77
* Fix Query Fallback controls being added outside an Elementor section. Register them before the native Custom Query section closes, including when JetEngine legacy queries are disabled.

### 1.3.76
* Replace dependency requirement sentences with compact per-plugin green checks and red crosses; retain accessible status and disabled switches.

### 1.3.75
* Add Query Fallback to the native JetEngine Listing Grid: empty-only or fill modes, progressive deduplication, dynamic context and per-widget isolation.
* Explicitly suspend fallback for pagination, Load More, Lazy Load, JetSmartFilters and public AJAX; document compatibility limits and manual verification.
* Always display required active plugins per module; keep unavailable toggles off and locked and validate activation server-side.

### 1.3.74
* Skip trashed/private sample pages and record removed 404/410 routes separately, without marking a completed scan incomplete. Keep genuine network/server failures visible.
* Add a structured Privacy Policy generator in settings and an optional, skippable wizard step. Include controller/contact details, purposes and legal bases, retention, providers/transfers, DPO, rights and optional marketing/automated processing. Flag missing inputs.
* Generate fuller Cookie Policy text and observed browser-storage details. Use paragraphs, bold text and lists instead of headings. Keep existing user-created pages and shortcodes.
* Remove duplicate shortcode controls from Banner and remove the banner close X/note. Preserve compact mobile actions and explicit rejection.
* Hide the scanner progress bar on completion and show a clear final state; reduce explanatory text. Display readable consent receipts with integrity status; retain the technical JSON export.

### 1.3.73
* Remote Cookie Manager scans continue in server background batches after closing the page or continuing the wizard. Persistent progress, signed workers, pause/resume and a cron watchdog.

### 1.3.72
* **Reliability:** Expiring site proof, provider preflight, independent page requests, bounded quota/concurrency, idempotent retries and explicit errors. Local scanning remains available with limited coverage; disabled Cookie Manager loads no scanner or network hooks.

### 1.3.71

- Captures actual response cookies during isolated browser checks, including late HttpOnly headers. Tests native consent in the isolated session without visitor receipts, waits for delayed scripts, and includes dynamically requested external services.
- Lists provider-documented cookies for detected Maps, Analytics and YouTube services separately from observed cookies, with documentation links in the scanner and Cookie Policy. Refines the desktop banner text layout.

### 1.3.70

- Runs cookie checks without visible page previews, skips inert menu/template links and groups cosmetic Elementor differences and date archives. Verifies browser cookie access, explains incomplete/empty results in Italian and lists external service hosts separately from observed cookies.

### 1.3.69

- Fixes the desktop cookie acceptance bar collapsing its text when an empty status element consumes the available width. Content, actions and messages now have explicit space; mobile descriptions and labels wrap without clipping.

### 1.3.68

- Adds a compact post-update rescan notice for administrators while Cookie Manager is enabled. It links to the scanner and clears after a new scan saves its report and completes its browser measurements without browser errors.
- Updates WordPress compatibility metadata to 7.1.3 and checks PHP 8.5.11 support, preserving the existing minimum requirements.
- Adds the WordPress-format readme, aligns version/license/requirement metadata, and documents the release workflow.

[Complete changelog](marrison-addon/README.md#changelog)

Cookie Manager and PHP Snippets hardening is documented in [the phase guide](marrison-addon/docs/cookie-hardening.md): local service scans, Consent Mode v2, banner controls, private receipt limits and snippet signatures. PHP Snippets 1.3.90 repairs the legacy signature migration during an authorized admin request, preserving manual deactivations and real PHP-error quarantines. Release bodies must publish the SHA-256 of the exact tag archive before the updater offers it.
