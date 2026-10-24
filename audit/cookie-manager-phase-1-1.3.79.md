# Cookie Manager — Fase 1, 1.3.79

## Perimetro e verifica preliminare

Implementata la Fase 1 del prompt, con commit separato. Le estensioni additive (option categorie, metadati dei profili e futura distinzione `observed`/fonte catalogo) sono state approvate esplicitamente dall'utente in chat. Nessun altro modulo viene modificato da questa fase. Le modifiche preesistenti del workspace, comprese Business Info, Ticker e policy, restano fuori dal commit.

Prima delle modifiche, nel commit `4e9867e`:

- `marrison-addon/includes/modules/cookie-manager/includes/class-scan-services.php:3` conteneva una sola funzione con tre servizi hardcoded (Maps, Analytics, YouTube), senza loader, fornitore, verifica o data di revisione.
- `marrison-addon/includes/modules/cookie-manager/includes/class-cookie-consent.php:217` applicava solo le liste note/personalizzate. Un host sconosciuto non trovava categoria e non veniva bloccato.
- `marrison-addon/includes/modules/cookie-manager/includes/class-scan-engine.php:434` conservava gli host esterni e i profili riconosciuti, senza inventario del catalogo estendibile dalle regole admin.
- `marrison-addon/includes/modules/cookie-manager/assets/js/scanner.js:62` etichettava tutte le dichiarazioni come documentate dal fornitore, senza uno stato Da verificare.

## Esito per punto della Fase 1

| Punto | Esito | Evidenza attuale |
| --- | --- | --- |
| Catalogo dichiarativo con tutti i campi richiesti | Confermato e corretto | `marrison-addon/includes/modules/cookie-manager/data/services.json:1`; loader e validatore in `includes/class-scan-services.php:10` e `:26` nello stesso modulo. |
| GA4 e GTM separati, niente cookie legacy dedotti | Confermato e corretto | Schede `google-analytics` e `google-tag-manager` nel JSON; test `tests/cookie-service-catalog-php-test.php:66`. `_gid` e `_gat` non sono dichiarati dal catalogo. |
| Meta, YouTube/nocookie, Maps embed/API | Confermato e corretto | Pattern nel JSON; riconoscimento `includes/class-scan-services.php:176`. Meta e attribuzione Maps segnalati Da verificare. |
| WooCommerce necessario e gateway pagamenti | Confermato e corretto | Schede WooCommerce/Stripe/WooPayments/PayPal/Klarna nel JSON; selettori in `templates/service-catalog.php:20`. Le categorie governano le risorse; la categoria dei cookie già osservati resta nel vecchio inventario. |
| Non inventare dati, badge delle informazioni incerte | Confermato e corretto | `templates/service-catalog.php:25`, `assets/js/scanner.js:64`, note delle schede. Meta senza durate dedotte; PayPal/Klarna senza cookie dedotti. |
| Categoria prudente dei pagamenti, modifica admin | Confermato e corretto | Default functional nel JSON; salvataggio form `includes/class-admin-settings.php:191` e AJAX `:251`. Nuova option non autoload `marrison_cookie_service_categories`; sanitize e whitelist in `includes/class-scan-services.php:78`. |
| Host sconosciuto non classificato e bloccato | Confermato e corretto | `includes/class-scan-services.php:131`, `includes/class-cookie-consent.php:201` e `:229`, `assets/js/consent-runtime.js:59`. Il consenso non rende ammissibile un host non classificato. L'admin può classificare gli host dell'ultimo inventario; le liste sorgenti permettono di aggiungerne altri. |
| Estensione senza modificare il JSON | Confermato e corretto | `includes/class-scan-services.php:157`; riutilizzate le tre option `marrison_cookie_{functional,analytics,marketing}_sources`. |
| Dichiarazioni separate dalle osservazioni | Non confermato come difetto preesistente; comportamento preservato | Il vecchio `identify()` marcava già `observed = false`. Il nuovo loader lo mantiene; nessun cookie del catalogo viene aggiunto alla tabella degli osservati in questa fase. |

I percorsi abbreviati nella colonna evidenza sono relativi a `marrison-addon/includes/modules/cookie-manager/`.

## Fonti e stato di verifica

Consultate il 24 ottobre 2026 le fonti ufficiali collegate dalle schede: [GA4](https://support.google.com/analytics/answer/11397207), [GTM](https://support.google.com/tagmanager/answer/9323295), [cookie Google](https://policies.google.com/technologies/cookies), [WooCommerce](https://woocommerce.com/document/woocommerce-cookies/), [Stripe](https://stripe.com/cookie-settings), [integrazioni pagamenti WooCommerce](https://woocommerce.com/document/privacy-payments/), [PayPal](https://www.paypal.com/uk/legalhub/paypal/cookie-full) e [Klarna SDK](https://docs.klarna.com/klarna-network-distribution/resources/legal-and-compliance/policies-and-term-of-service/terms-of-service/).

La documentazione [Meta fbp/fbc](https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/fbp-and-fbc/) non era leggibile (HTTP 429; pagina cookie rinviata al login). La scheda resta `verified: false`. I testi generali PayPal non permettono un elenco stabile per il gateway specifico; le chiavi dello storage Klarna non vengono trasformate in cookie. WooPayments rimane da confermare per integrazione. Maps mantiene da verificare l'associazione tra NID e l'embed/API specifica. Nessuno stato di verifica certifica il comportamento del sito.

## Verifiche eseguite

- `php -l` sui nove file PHP modificati/aggiunti della fase, compreso il bootstrap e il nuovo test: tutti superati.
- Nuovo test PHP catalogo: dieci servizi, JSON/schema/regex danneggiati, confini host, GA4/GTM, evidenza non osservata, regole personalizzate, host ignoti anche non scansionati, categorie gateway e salvataggio form sanitizzato senza perdere classificazioni assenti dal rapporto: superato.
- Nuovo test Node con configurazione generata dal loader PHP: blocco prima del consenso, consenso valido, host non classificati, fase after, pagamento necessario, URL WooCommerce relativo, precedenza custom e domini contraffatti: superato. In PHP e Node verificati anche srcset misti e conservazione dei dati inline JSON-LD, che non sono richieste di rete.
- Fixture admin renderizzata dal vero template in Chrome headless: dodici selettori etichettati, selezione salvata, modifica categoria, badge visibile e link senza schema JavaScript: superata. Screenshot locale fuori dal repository; CSS semplificato della fixture, non UI WordPress reale.
- Test esistenti: cookie engine, policy, consenso, storage ricevute, scansione automatica, notifica aggiornamento, shortcode, runtime JS, scan-agent, background UI e receipt UI: tutti superati. I test engine comprendono ancora Commander, rimasto operativo fino alla Fase 2.
- Test lifecycle con dispatcher hook del WordPress locale in `C:/Users/Angelo/Local Sites/tesy/app/public` e storage simulato: superato; i moduli disattivati restano inattivi. Non è un'esecuzione end-to-end su quel sito.
- Controlli sintassi JS e `git diff --check`: superati.

Comandi riproducibili in `marrison-addon/docs/cookie-service-catalog.md`. Per la fixture browser occorrono Playwright e un Chromium disponibile (`NODE_PATH` e, se necessario, `BROWSER_EXECUTABLE`).

## Limiti e fasi successive

**Da decidere/verificare nell'ambiente reale:** classificazione giuridica dei pagamenti, cookie effettivi dei fornitori, checkout/login, cache HTML esterne e configurazioni con percorsi plugin personalizzati. Il blocco intercetta le risorse supportate dal runtime; non è un firewall di rete e non copre ogni CSS/font/worker. I gateway non vengono configurati per limitare le pagine su cui caricano i loro script.

**Fasi 2–6 non implementate in questo commit:** scanner statico/locale veloce e dismissione Commander; Consent Mode v2 e placeholder; banner/checklist; hardening Cookie Manager; hardening PHP Snippets e checksum updater. I relativi rilievi del prompt non sono dichiarati confermati senza una successiva verifica del codice. Nessuna verifica live su WordPress completo, nginx, Multisite o provider remoto e nessun deploy eseguito. Non è stato ricostruito lo ZIP preesistente del workspace.
