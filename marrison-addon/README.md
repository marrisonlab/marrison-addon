# Marrison Addon

**A comprehensive addon for Elementor and WordPress sites.**

*   **Plugin Name:** Marrison Addon
*   **Plugin URI:** https://github.com/marrisonlab/marrison-addon
*   **Author:** Angelo Marra
*   **Author URI:** https://marrisonlab.com
*   **Tags:** elementor, container, link, wrapper, read more, steps, anchor, ticker, discount, woocommerce, cursor, preloader, logout, video thumbnail, cookie, calendar, liquid background, dynamic svg, marrison
*   **Requires at least:** 6.0
*   **Tested up to:** 7.1.3
*   **Requires PHP:** 7.4
*   **PHP compatibility:** 8.5 (current stable patch: 8.5.11)
*   **Stable tag:** 1.3.85
*   **License:** GPL-3.0-or-later
*   **License URI:** https://www.gnu.org/licenses/gpl-3.0.html

## Description

**Marrison Addon** is a modular plugin designed to enhance your WordPress site and Elementor workflow. It provides a suite of essential tools that can be enabled or disabled individually to keep your site lightweight.

**Included Modules:**

**Query Fallback (JetEngine + Elementor):** adds a backup Posts Query directly after the Listing Grid Custom Query controls. Choose Only when empty or Fill missing items, with ordered duplicate-free results and preserved dynamic context. This first version is for single-page grids: public AJAX, Load More, Lazy Load and requests with applied filters have explicit safety gates. An installed JetSmartFilters plugin does not block unfiltered listings. See [configuration, compatibility and test procedure](docs/query-fallback.md).

The module dashboard always lists required active plugins. Missing dependencies keep the related switch off and locked and prevent server-side activation. Standalone modules remain available.

1.  **Wrapped Link (Elementor):**
    *   Make an entire Elementor Container or widget clickable.
    *   Adds a "Wrapped Link" section directly to the **Advanced** tab.
    *   Supports dynamic tags and custom attributes.

2.  **Content Ticker:**
    *   Create smooth, infinite scrolling text tickers.
    *   Customizable speed, direction, and styling.
    *   Pause on hover functionality.

3.  **Steps (Elementor):**
    *   Adds the Marrison Steps widget for 2 to 8 responsive steps.
    *   Supports numbers, images, native Elementor icons/SVGs, titles, text, and customizable connectors.
    *   Includes per-device horizontal/vertical orientation controls.

4.  **Product Discount (WooCommerce + Elementor):**
    *   Adds an Elementor widget that displays the current product discount percentage.
    *   Works in product pages and product listings that provide a WooCommerce product context.
    *   Includes typography, color, background, alignment, border, radius, padding, and shadow controls.

5.  **Header Animations (Elementor):**
    *   Adds 10 custom entrance animations to Elementor's Heading widget only.
    *   Uses Elementor's native entrance animation control and timing options.

6.  **Recently Viewed Products (WooCommerce + JetEngine):**
    *   Adds the "Visualizzati di recente (prodotti)" macro to JetEngine macro UIs.
    *   Returns recently viewed WooCommerce product IDs as a comma-separated list.
    *   Designed for dynamic query parameters and Elementor/JetEngine listing contexts.
    *   Tracks product views while the module is enabled, without requiring WooCommerce's native recently viewed widget.
    *   When used in Query Builder, disable "Cache Query" because results depend on the visitor cookie.

7.  **Listing Grid Title (JetEngine + Elementor):**
    *   Adds a "Titolo Listing" section directly to the JetEngine Listing Grid widget.
    *   Outputs an optional title before the Listing Grid output.
    *   Includes title text, optional link, HTML tag, typography, colors, alignment, background, border, radius, shadow, padding, and margin controls.
    *   To center Listing Grid items when a row has fewer elements, add `selector .jet-listing-grid__items { justify-content: center; }` to the Listing Grid custom CSS.

8.  **Dynamic SVG (JetEngine):**
    *   Adds Marrison callbacks to JetEngine Dynamic Field output filters for SVG media fields.
    *   Converts local SVG attachment IDs or upload URLs into sanitized inline SVG markup.
    *   Includes a Current Color callback that converts fill and stroke colors to `currentColor` while preserving `none`.
    *   Does not load frontend CSS or JavaScript.

9.  **Leggi di più (Elementor + JetEngine):**
    *   Adds a "Marrison — Leggi di più" section to Elementor Text Editor and JetEngine Dynamic Field widgets.
    *   Collapses long text to the selected number of visible lines and shows a `....` suffix while closed.
    *   Includes custom Leggi di più/Leggi di meno labels plus typography, color, alignment, background, border, radius, padding, and margin controls for the toggle.

10. **Anchor Offset:**
    *   Corrects same-page anchor scroll when the site header uses the `hdr` ID.
    *   Uses the live height of `#hdr` so linked sections are not hidden under a fixed or sticky header.
    *   Applies to anchor clicks, direct page loads with a hash, and hash changes.

11. **Preloader:**
    *   Add a professional loading screen to your site.
    *   **Animations:** Fade, Slide Up, Slide Left, Split (Curtain), Shutter (Vertical).
    *   **Spinners:** Circle, Dots, Double Ring, Wave, Pulse (Logo).
    *   **Customization:** Upload your logo, choose colors, and set transition duration.
    *   **Progress Bar:** Optional progress bar with percentage display.

12. **Custom Cursor:**
    *   Replace the default system cursor with a custom follower.
    *   Customizable colors, size, and hover effects (scale, magnetic).
    *   "Exclusion" blending mode for high visibility on any background.
    *   **Frontend Only:** Skips backend, preview, and Elementor Editor contexts.

13. **Image Sizes:**
    *   Define custom image sizes for your theme directly from the dashboard.
    *   Control cropping and dimensions without editing code.
    *   Regenerate all thumbnails or a single saved default/custom size across the Media Library. Single-size regeneration updates only that size and its enabled WebP/AVIF variants, preserving other sizes and original/full-size sources; disabled-size cleanup is available only for full regeneration.
    *   SVG attachments are skipped before thumbnail generation, cleanup and WebP/AVIF conversion. The regeneration report separates successful, skipped and failed images; automatic uploads preserve SVG metadata without conversion errors.
    *   Generate WebP/AVIF variants and use existing native modern-format sizes. Elementor CSS respects the selected media size; static CSS uses WebP independently of the generating request's Accept header.
    *   Mark an Elementor container under **Advanced > Ottimizzazione LCP > Imposta come Hero LCP** to preload its background once in the page head and prioritize its first internal image, including containers served from Elementor's document cache. After changing image settings or regenerating media, clear Elementor files/data and the page/CDN cache.

14. **Fast Logout:**
    *   Automatically redirects users to the home page after logging out, bypassing the default WordPress login screen.

15. **Calendar Sync:**
    *   Generate Google Calendar and ICS event links from post meta.
    *   Configurable meta keys for start and end dates.
    *   Includes shortcode support for templates and dynamic content.

16. **Cookie Manager:**
    *   Cookie banner, floating widget, preferences modal, and setup wizard. The wizard uses the shared WordPress/Marrison admin style; the floating widget inherits the configured button background and text colors.
    *   After a plugin update, administrators see a compact notice linking to the scanner while Cookie Manager is enabled. It remains until a new scan started with the updated version saves its report and finishes all planned browser measurements without browser errors. An HTTP failure such as a missing test policy page can leave the report partial without falsely claiming that no new scan was performed. Old, cancelled, failed and browser-incomplete jobs do not clear the notice. Turning the module off hides the notice without loading its files, hooks or assets; first installations retain the setup wizard.
    *   Background consent checks do not add technical diagnostics or retry buttons to the visitor banner. A failed save remains an explicit error and never enables optional tracking. Receipt storage installs the server's access protection (IIS web.config or Apache-compatible .htaccess); an unused IIS configuration cannot block a Unix host. Detailed storage errors remain available in the administrator receipt view.
    *   Every Privacy Policy profile field is optional. Saving and shortcode output work with empty or partial profiles; populated identity details remain visible. Empty details are omitted and unspecified transfers are not presented as declared. The form has no required markers or publication-block messages.
    *   The scanner selects representative samples before making HTTP/browser requests. It groups public posts, announcements and other CPTs by content type, assigned/classic WordPress template, Elementor widget/component structure and saved embedded services/forms/inline scripts. Text, widget IDs, pagination and image IDs alone do not create another visit. Home, configured policies, post-type archives and public taxonomy archives receive their own representatives; unique templates and services keep extra samples. WordPress APIs provide seed routes; internal links, sitemaps and robots.txt identify further candidates without visiting every equivalent page.
    *   The visible limit controls samples (default 50; maximum 5,000). Each successful sample receives two isolated browser passes, before/after simulated consent. The report lists the chosen URLs, selection reasons, represented URL counts and equivalent URLs avoided. Up to 50 same-site **Additional pages** can be explicitly included even when they share a template. Dynamic display conditions or integrations not identifiable from saved content require these targeted URLs; Elementor supports custom inclusion/exclusion rules ([developer documentation](https://developers.elementor.com/docs/theme-conditions/)). Non-WordPress routes are grouped by route family and identified as uncertain. Sampling completion means the representative plan finished, not that every URL or possible interaction was visited.
    *   Requests are incremental and progress is saved. Administrative/action URLs, external hosts and asset downloads are excluded. Discovery is bounded to 10,000 candidate URLs; limits, failed samples and incomplete coverage are explicit. Large/nested saved content that cannot be fully classified gets a dedicated sample rather than being silently merged. Scans from the previous exhaustive method cannot resume under the new grouping strategy.
    *   Scans analyze published content and configuration locally in resumable batches. Optional iframe checks use at most ten representative samples and a total time budget; they cannot observe third-party cookies. See [local scan and hardening limits](docs/cookie-hardening.md).
    *   Catalog declarations have source catalogo and observed=false. They never replace real observations. Provider-specific conditional cookies still require manual browser checks.
    *   Inventory and policy lists consolidate duplicates by name, domain and path, retaining manual categories. A successful complete scan removes cookies no longer observed; partial scans retain earlier observations. Legacy catalog-only records and duplicates are cleaned on the next scan. Database and JSON fallback storage remain supported. Reports can be exported and show runtime version, loaded scanner path and source fingerprint so stale or conflicting installations cannot silently report successful scans.
    *   Automatic local scans remain opt-in and respect the configured interval (1–30 days, default 7). WP-Cron advances the saved static cursor; optional browser measurements need an open panel.
    *   Policy codes are always available in **AM Addon > Cookie Manager > Shortcode**, each with a **Copy** button and Elementor instructions. `[marrison_privacy_policy]` renders the Privacy Policy; `[marrison_cookie_policy_content]` renders the full Cookie Policy with current scan results and a preferences link. Insert them in your own pages with Elementor's Shortcode widget or WordPress's Shortcode block, then configure their URLs in the **Banner** tab. The same copy controls appear in the wizard; no pages are created automatically.
    *   `[marrison_cookie_policy]` remains available for just the scanned cookie list and preferences link. Existing policy pages and configured URLs are preserved, and legacy Cookie Policy pages still receive current scan details without replacing saved content.
    *   The policy distinguishes detected expiration (in the site timezone), session cookies and unavailable expiration; lifetimes are never inferred from cookie names. Run a new scan after updating to collect metadata for existing records.
    *   Consent blocking covers known or explicitly categorized scripts, iframes, pixels and dynamic requests, plus optional JavaScript cookie writes. Additional service domains/paths can be configured per category in **Behavior**. Revoking a category removes accessible cookies at their detected scopes and reloads the page so already-executed integrations stop. Essential WordPress/WooCommerce sessions and the consent itself are preserved. Site-specific integrations and server-set cookies still require verification; unknown services cannot all be identified by their URL alone.
    *   Accept, reject and customize have equal visual prominence. Choices last 180–365 days (default 180; shorter legacy settings are raised), can be changed through the floating widget or preferences link, and must be renewed when the controller changes the revision for significant processing changes or adds/changes service rules. Minor text/layout corrections alone do not re-prompt visitors; each receipt retains the exact information available when saved. Old unsigned acceptances require a new choice; an existing refusal is preserved without inventing an affirmative consent record.
    *   Consent is verified by an uncached, same-origin, nonce-protected request against a signed HttpOnly/SameSite cookie. Optional resources stay inert in cacheable HTML even when the generating visitor has accepted. A failed verification or receipt write does not activate optional tracking; errors are visible and retryable.
    *   **Consent receipts** provides administrator-only, paginated viewing and daily JSON exports, including timestamp, categories, choice/withdrawal, linked previous receipt, policy revision and integrity results. Random per-device IDs replace the old IP-keyed transient storage; no IP address or user agent is recorded. Private append-only daily files and policy snapshots live in `wp-content/uploads/marrison-cookie/consent/`, protected by webserver rules and PHP exit prefixes. Back up this archive together with the database option `marrison_cookie_receipt_key`; historical integrity does not depend on WordPress authentication salts. Retention is configurable from 365 to 1,095 days (default 365), with daily WP-Cron cleanup; choose and disclose a proportionate retention period.
    *   **Privacy and policies** generates the Privacy Policy from structured controller details, actual processing, legal bases, retention and provider/transfer settings. The wizard step is optional; incomplete required information is flagged. Cookie Policy text, current scan inventory and preferences access are generated automatically. Shortcode content uses basic paragraphs/bold/lists without headings. Existing independently written Elementor pages remain user-managed. Receipts preserve the generator inputs/text and the configured local policy page sources; their admin view shows readable choices and integrity results.
    *   Frontend UI only loads when the module is active and in a real frontend context.

17. **Video Thumbnail:**
    *   Fetch YouTube thumbnails and import them directly into the WordPress Media Library.
    *   Automatically generate JPG covers from uploaded MP4/WebM videos when FFmpeg is already available on the server. Otherwise, show an optional-feature warning and skip cover generation; uploads and YouTube thumbnails remain available.
    *   Configure capture second, cover destination, and optional FFmpeg binary path from the admin page.
    *   Keeps the original admin workflow while living as a module inside Marrison Addon.

18. **Local Google Fonts:**
    *   Scan Elementor, generated CSS, theme/plugin CSS, theme/plugin source files, inline CSS, and WordPress custom CSS for Google Fonts candidates.
    *   Download Google Fonts WOFF2 files locally and generate a local `@font-face` stylesheet without modifying Elementor content or saved CSS.
    *   Reuse Elementor's local Google Fonts by family, weight, and style when Elementor already provides the requested local variant.
    *   Dequeue remote Google Fonts stylesheets only when the matching local families and variants are available.

19. **Browser Cache:**
    *   Configure browser cache headers for static CSS, JavaScript, fonts, and images while preserving WordPress `?ver=` cache busting.
    *   New configurations default to 7 days and no `immutable`. Longer TTLs and `immutable` require reliable URL changes whenever content changes; existing saved settings are preserved.
    *   Manages an isolated Apache 2.4-compatible `.htaccess` block when available; written rules remain unverified until public headers pass diagnostics. OpenLiteSpeed requires manual server configuration.
    *   Nginx instructions separate `http` maps from directives for the existing static location, preserving response policies and excluding errors/dynamic responses. A system administrator must apply and later remove these directives; disabling the WordPress module cannot remove manual Nginx configuration.
    *   Manual GET diagnostics check up to three assets per enabled category, including CSS backgrounds and fonts, with HTTP status, content type and cache directives. Missing categories and failed checks cannot retain a previous green status. The module does not configure HTTP/2, compression or page cache.

20. **Scroll Orizzontale (Elementor, Beta):**
    *   Adds **Marrison — Scroll Orizzontale** to the Advanced tab of ordinary Elementor Containers; no separate widget is required.
    *   Put the content in a child Container arranged as a horizontal row and give its children the widths you want in Elementor. The module measures the actual overflow and supports either movement direction, optional pin, and independent desktop/tablet/mobile switches (mobile is off by default).
    *   With Pin, vertical scroll distance equals horizontal overflow multiplied by **Durata scroll** (1 is natural distance). Without Pin, the duration scales the section's passage through the viewport.
    *   Optional **Snap per slide** shows one child Container at a time instead of moving continuously through partial slides.
    *   Boxed content clips the track at its own width in both continuous and Snap mode. Snap preserves Elementor's content width and starts after the parent reaches the viewport top, including when pin is unavailable.
    *   With Snap and Pin enabled, images automatically fit the available viewport height, accounting for container spacing and other vertical content; their proportions are preserved. Image constraints are restored when Snap or the module is disabled. Other content can still make a section too tall to pin.
    *   Optional **Scala sfondo** can scale the outer Container background image, video, or color from an initial percentage to a final percentage while the horizontal scroll progresses.
    *   The editor keeps a normal, editable layout. On the frontend, reduced-motion visitors get a regular horizontally scrollable container.
    *   Pin requires a section no taller than the viewport and a document path without an ancestor that clips or scrolls vertically; otherwise movement runs without pin to avoid an unusable clipped section.
    *   CSS and JavaScript are requested only when an enabled Container is actually rendered on the public frontend; the module's PHP and Elementor hooks are absent when its dashboard toggle is off.

21. **Liquid Background (Elementor):**
    *   Adds **Marrison — Liquid Background** to the Advanced tab of ordinary Elementor Containers; no separate widget is required.
    *   Generates a procedural WebGL background with soft organic masses, Deep Purple, Blue Ink, Monochrome, and Custom presets, plus responsive speed, scale, and opacity controls.
    *   Stacks Elementor's native background below Liquid and its overlay above Liquid, including video, slideshow, and background motion effects. Liquid opacity reveals the background underneath; overlay opacity remains independent.
    *   Includes an optional bottom blend gradient so the animated Container can fade into the color of the following section without a hard edge.
    *   Supports subtle mouse influence, stable seeds, reduced-motion behavior, mobile disable before WebGL initialization, WebGL context loss handling, and a static CSS fallback when WebGL is unavailable.
    *   CSS and JavaScript are requested only when an enabled Container is actually rendered on the public frontend; the module's PHP and Elementor hooks are absent when its dashboard toggle is off.

22. **CSS piccoli inline:**
    *   Opt-in module that uses WordPress's native CSS inliner for local styles already queued in the page head. Off by default; activate it in the main Marrison Addon module dashboard and purge page caches after toggling it.
    *   Eligible files are at most 16 KiB each, with at most 32 KiB selected per page; the native WordPress inline-size limit can reduce the selection further. The CSS stays in dependency order, before the handle's existing inline rules; relative asset URLs are rebased by WordPress.
    *   No frontend HTTP calls, script deferral, async CSS, or generated files. Large, remote, imported, conditional, alternate, RTL-replacement and non-default-media styles retain their normal delivery. Editor, preview, admin, login, AJAX and JSON requests are excluded.
    *   Embedded CSS travels with the cached HTML rather than a separate browser-cached stylesheet. Verify cold and repeat visits, rendering and real PageSpeed metrics before concluding that performance improved.

23. **Dynamic Background Image Size (Elementor):**
    *   Opt-in fix for Container dynamic backgrounds: the existing native **Image Resolution** field remains the only size selector. Medium requests Medium; Full retains full dimensions even when Image Sizes has a global replacement size.
    *   Works through Elementor's PHP dynamic CSS pipeline on the frontend and server-rendered preview, including responsive, hover and overlay image controls. It preserves Elementor's selectors, media queries, cover, position and other background styles; saved settings are untouched.
    *   Supports image dynamic tags exposing a WordPress attachment ID, including JetEngine and ACF integrations. URL-only tags use WordPress attachment lookup; URLs WordPress cannot resolve remain unchanged. Missing sizes or deleted local intermediates fall back to the original. Remote/offloaded URLs without a resolvable attachment ID cannot acquire a local size.
    *   Uses native WordPress image APIs and the existing Image Sizes format resolver. Shared CSS keeps the existing deterministic WebP policy (native AVIF images remain supported), without Accept-dependent AVIF URLs in cached styles. It only reads existing files and never starts image generation or conversion. The ordinary browser size selector skips these dynamic containers.
    *   The module works with Image Sizes disabled and adds no frontend assets or new Container controls. Editor-only assets keep Elementor's existing Image Resolution selector visible for supported dynamic backgrounds, even without a static image, and replace the generic ignored-size warning with an explanation of the module's behavior. The native media event handlers, settings command and history path remain intact; responsive controls display their saved dynamic size rather than an unrelated static placeholder size.
    *   Its file and hooks are absent when disabled. Dashboard toggles invalidate Elementor CSS/document caches; the first enabled request after an Addon/Elementor version change also invalidates them once. Purge any external page/CDN cache after toggling or changing dynamic content.
    *   Compatibility verified against the installed Elementor 4.3.3 and JetEngine 3.8.15.4 PHP CSS pipelines. The native Elementor Media view was also browser-tested in an isolated fixture. ACF's ID/URL contract is supported; a live ACF/Elementor Pro editor was not available for end-to-end verification. Full editor save/preview and production cache behavior still require site validation.

24. **PHP Snippets:**
    *   Optional module under **AM Addon > PHP Snippets**, off by default. Create, edit, duplicate, activate, deactivate and delete PHP snippets, with WordPress CodeMirror (plain textarea when syntax highlighting is disabled in the user profile). An opening PHP tag is optional; closing tags and non-PHP content are rejected.
    *   Access and every write require both `manage_options` and `unfiltered_html`, using native capability checks including Multisite restrictions. All mutations use POST and operation-specific nonces. Database writes check the revision to reject stale forms and prevent an older failing request from disabling a newer revision.
    *   A per-site database table stores snippets. Ordinary requests read only active rows with one indexed query, without a persistent code cache. Only this module starts during the Addon plugin file load, before `plugins_loaded`; other modules retain their bootstrap timing. Hook/filter/shortcode/JetEngine callback registration is supported. Hooks already fired by plugins loaded earlier cannot be intercepted: register integrations on their documented later hooks. User authentication/pluggable functions may not yet exist at snippet load time; call them from an appropriate later hook.
    *   Scopes: **Ovunque**, **Solo frontend** (non-admin requests, including REST/cron) and **Solo backend** (`wp-admin`, including admin AJAX). WordPress login, the main module dashboard, snippet management, module-toggle AJAX and native Recovery Mode always bypass snippets. Saving an edit or making a copy leaves it inactive; activation applies on subsequent ordinary requests. Disabling the module loads neither its classes nor its snippets.
    *   Code is included from a private read-only in-memory stream with a unique snippet/revision filename, without `eval()`, permanent PHP files, external packages or `allow_url_include`. Native parser validation runs before save/activation. It checks syntax, not runtime dependencies or every compiler/semantic error; failures during actual compilation/execution are quarantined too. The stream is not a sandbox: PHP has plugin-level privileges, `__FILE__`/`__DIR__` refer to virtual paths, and relative includes should use explicit WordPress paths.
    *   Catchable load errors and identifiable fatal errors, including deferred WordPress hook/shortcode/JetEngine callbacks, disable the failing revision and record its diagnostic. Shutdown recovery reserves 512 KiB for resource exhaustion. If memory/time exhaustion has no attributable trace, all snippets loaded in that request are conservatively suspended; the actual cause may be another component. The current request can still fail. PHP shutdown cannot guarantee recovery after a killed process, intentional `exit`, fatal errors in later shutdown handlers/destructors, unavailable database, or failures in external code without a snippet trace. See [PHP shutdown behavior](https://www.php.net/manual/en/function.register-shutdown-function.php).
    *   Recovery: open `/wp-admin/admin.php?page=marrison_addon_php_snippets` (or the module dashboard) and disable the offending entry or all snippets. If necessary add `define( 'MARRISON_ADDON_PHP_SNIPPETS_SAFE_MODE', true );` to `wp-config.php` before WordPress loads; remove it after correcting/deactivating the code. This bypass does not execute snippets or change stored activation states. Management still requires both capabilities and valid nonces.

25. **Listing Columns (JetEngine + Elementor):**
    * Enable **Colonne Listing** in AM Addon, then add the **Colonne Listing** widget in Elementor. Assign a CSS ID such as `catalogo` to the existing Listing Grid and set the widget target to `#catalogo`. The default target `.griglia-variabile` also supports the class used by the original HTML snippet; remove the old snippet to avoid competing scripts/styles.
    * Choose minimum, maximum and initial columns (1–12) separately for each active Elementor breakpoint using the native responsive controls. Optional laptop, mobile/tablet extra and widescreen devices use the site's actual breakpoint widths. Empty fields inherit from larger devices; widescreen inherits desktop. Invalid initial values are clamped to that breakpoint's enabled range. The default range is 2–4 with 3 selected. A target selector can control several grids; repeated toolbars with the same target share the choice.
    * Automatic two-row SVG icons depict each column count. Choose icons/numbers/both and icon position, or override individual labels/icons with Elementor's native icon library and SVG uploads.
    * Native responsive style controls cover alignment, direction, gaps, typography, padding, dimensions, radius, icon size/stroke and normal/hover/active/focus colors, backgrounds, borders and shadows. Elementor provides its usual Advanced tab according to the installed edition.
    * Optional browser storage is scoped to page path, target selector and breakpoint; blocked storage does not prevent selection. Returning to a breakpoint restores its own choice and buttons. Existing desktop preferences are preserved. Editor choices are not persisted. Below the activation width (default 768 px; set 0 to use the mobile settings too), the toolbar hides and the original Listing responsive layout returns.
    * Handles AJAX grid replacements, pagination and editor rerenders, leaving nested listings independent. Slider and masonry grids are excluded. Assets load only for pages rendering the widget; the module is disabled by default and requires Elementor and JetEngine.

## Installation

1.  Upload the `marrison-addon` folder to the `/wp-content/plugins/` directory of your site.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **AM Addon** to enable/disable specific modules.
4.  Configure each module's settings as needed.

## Compatibility and distribution

The minimum requirements remain WordPress 6.0 and PHP 7.4. Current compatibility targets are **WordPress 7.1.3** and **PHP 8.5.11**, the stable releases checked on 20 October 2026. PHP 8.6 release candidates are not the stable target. Elementor, WooCommerce and JetEngine are required only by the modules that integrate with them; unavailable dependencies are shown in the module dashboard. Disabled modules load no operational module classes, hooks, CSS or JavaScript.

GitHub uses this Markdown documentation and the repository-root overview. The installable plugin also includes a standard **readme.txt** for WordPress, with the same version, minimum requirements and current changelog. WordPress reads installation requirements from the main PHP header; `Requires PHP` is the minimum, not the newest supported release.

Updates use the latest published stable GitHub Release from `marrisonlab/marrison-addon`. Commit and push the updated plugin code before creating a lowercase `vX.Y.Z` tag and publishing its Release as Latest. The PHP header/constant and readme stable tags must match `X.Y.Z`. A tag alone does not publish a Release. The updater downloads the source archive at that tag, not an attached ZIP; its version check is cached for six hours. After updating, open **AM Addon > Cookie Manager > Scanner Cookie** when the rescan notice appears. The GitHub updater runs in admin/WP-Cron while the plugin is active.

## Cookie Manager service catalog

Version 1.3.79 (Phase 1) adds a validated local catalog for ten providers, source/review badges, service category overrides in Behavior, custom domain/path rules and default blocking for unclassified external resources. Payment classification must be confirmed by the privacy adviser. Catalog cookies are declarations, never observations; PayPal/Klarna lists remain empty where integration-specific data are unconfirmed. No scanner provider is replaced in this phase. See [configuration, limitations and tests](docs/cookie-service-catalog.md).

## Changelog

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
* **Listing Columns:** Make minimum, maximum and initial columns responsive across all active Elementor breakpoints, with inherited empty values and independent per-breakpoint browser preferences.
* **Listing Columns:** Add the optional Colonne Listing Elementor widget with configurable column range, SVG/native icons, responsive activation, browser preferences, AJAX replacement handling and native style controls.

### 1.3.79
* **Cookie Manager, Phase 1:** Add the validated declarative catalog, ten services, provider/privacy sources and review dates. Keep GA4 legacy cookies observation-only; flag uncertain provider information.
* Add service categories (including necessary payments), reuse custom source rules and block unclassified external resources until admin classification. Preserve cookie observations, receipts and existing settings.

### 1.3.78
* **Query Fallback UI:** Fix the native Choose control clipping stacked radio options. Use a full-width field with automatic height, a visible Select2 placeholder and stable selection/search without rebuilding the control on every setting change. Compact notices and identify the actual incompatible setting.
* **Query Fallback execution:** Remove the global block for installed JetSmartFilters. Use JetEngine native filter-request detection for the primary and backup queries; preserve applied filters while allowing unfiltered grids and requests targeting other queries. Verify empty main queries against 3,000 fallback products in both modes.

### 1.3.77
* **Query Fallback:** Fix the Elementor "Cannot add a control outside of a section" error by using the native Custom Query section before_section_end hook. Controls also register with JetEngine legacy queries disabled. Add a regression check using the real Listing Grid query control registration and a strict section fixture.

### 1.3.76
* **Module settings:** Show each required plugin with a compact green check when active or red cross when missing/inactive, with accessible status. Remove redundant requirement sentences.

### 1.3.75
* **Query Fallback:** Add a backup Posts Query directly to the native JetEngine Listing Grid controls. Support empty-only and fill modes with progressive deduplication, preserved dynamic context and per-widget isolation.
* **Compatibility:** Explicitly suspend fallback for pagination, Load More, Lazy Load, JetSmartFilters and public AJAX. Document integration hooks, local verification and reproducible manual tests in `docs/query-fallback.md`.
* **Module settings:** Always show required active plugins; keep unavailable switches off and locked, prevent operational module loading and validate new activations server-side.

### 1.3.74
* Skip trashed/private sample pages and record removed 404/410 routes separately, without marking a completed scan incomplete. Keep genuine network/server failures visible.
* Add a structured Privacy Policy generator in settings and an optional, skippable wizard step. Include controller/contact details, purposes and legal bases, retention, providers/transfers, DPO, rights and optional marketing/automated processing. Flag missing inputs.
* Generate fuller Cookie Policy text and observed browser-storage details. Use paragraphs, bold text and lists instead of headings. Keep existing user-created pages and shortcodes.
* Remove duplicate shortcode controls from Banner and remove the banner close X/note. Preserve compact mobile actions and explicit rejection.
* Hide the scanner progress bar on completion and show a clear final state; reduce explanatory text. Display readable consent receipts with integrity status; retain the technical JSON export.

### 1.3.73
* **Cookie Manager:** Server background processing continues after closing the scanner or wizard. Persistent leases, signed loopbacks, watchdog recovery, pause/resume and dashboard progress notice; wizard may advance immediately without reloading.

### 1.3.72
* **Reliability:** Expiring site proof, provider preflight, independent page requests, bounded quota/concurrency, idempotent retries and explicit errors. Local scanning remains available with limited coverage; disabled Cookie Manager loads no scanner or network hooks.

### 1.3.71

*   **Cookie Manager:** Captures actual Set-Cookie headers in signed anonymous scan iframes, including late HttpOnly cookies; uses the native after-consent choice without recording visitor receipts. Top-level and authenticated requests cannot enter the scan context. Gives delayed scripts time to run and includes external resources observed through browser performance entries.
*   **Cookie Manager:** Separates observed cookies from provider-documented cookies for detected Google Maps, Analytics and YouTube integrations, with evidence labels and source links in the scanner and policy. No remote scanning service is required. Desktop banner text is stacked with explicit spacing.

### 1.3.70
*   **Cookie scanner:** Removes visible page previews while preserving layout and lazy-resource checks. Ignores inert script/template links and Vue bindings, groups cosmetic Elementor differences and date archives, and batches short HTTP checks. Verifies cookie read/write access before accepting browser results, retains document-cookie fallback when Cookie Store returns no entries, explains incomplete/empty results in Italian and lists external service hosts without inventing cookies or storing resource queries.

### 1.3.69
*   **Fix:** Cookie acceptance bars no longer collapse their text to zero width on desktop: the content and actions occupy explicit grid columns, empty consent-status messages are hidden and nonempty messages have a separate full-width row. The close-control explanation spans the text area. Mobile descriptions and button labels wrap without clipping; banner height stays within the viewport.

### 1.3.68
*   **Cookie Manager:** Adds a compact administrator notice requesting a new cookie scan after an update, with a direct scanner link. It appears only while the module is enabled and clears after a saved scan from the updated version finishes its browser measurements. Fresh installs, older/incomplete jobs and storage failures are handled separately; no global notice assets are added.
*   **Compatibility:** Updates the WordPress compatibility metadata to 7.1.3 and checks PHP 8.5.11 support. Minimum requirements remain WordPress 6.0 and PHP 7.4; adds the native requirement/license headers to the main plugin file and aligns the GitHub updater metadata.
*   **Documentation:** Adds a standard WordPress readme.txt and a GitHub repository overview, names Angelo Marra as author, and documents the GitHub tag/Release workflow and post-update cookie scan.

### 1.3.67
*   **Module lifecycle:** Single, bulk, Settings API and direct option updates share cleanup, status notifications and Elementor CSS invalidation. Disabling the plugin removes its own scheduled tasks and server rules while preserving settings and data. Failed cleanup or persistence is reported instead of confirming an unsuccessful save. Disabled modules retain conditional loading; registered background callbacks also stop after an OFF transition in the same request.
*   **Cookie Manager:** Pending consent verification preserves existing optional cookies while blocking new optional writes/resources. Confirmed refusal, revocation and expiration retain cleanup. Inventory and manual categories no longer disappear after 30 days; database and JSON storage preserve observations on partial scans and identify older results. Preferences use a minimal layout, named dialog, trapped keyboard focus, Escape and restored trigger focus.
*   **Dashboard:** Adds compact settings icons to all ten modules with their own settings page and a Beta label to Horizontal Scroll. Links become available when their module and dependencies are enabled.
*   **Frontend:** Calendar strings without an offset use the WordPress timezone. Anchor links respect target/download. Preloader defaults to hidden without its JavaScript and handles browser history restoration. Cursor animation pauses when settled or invisible. Read More assets follow actual document/template usage; Ticker instances share static CSS.
*   **Media and performance:** Prevents image-size slug collisions and bounds public background URL resolution. Local Fonts distinguishes frontend observations from source-only candidates. YouTube imports validate the selected video and thumbnail host. Dynamic SVG limits DOM complexity. Large product-discount import queues run in batches of up to 50 using Action Scheduler or WP-Cron.

### 1.3.66
*   **Fix:** Cookie scanning now groups equivalent WordPress content before HTTP and browser work. Hundreds of posts or CPT entries using the same template no longer produce hundreds of repeated visits. Distinct templates, Elementor widgets/components, embedded providers, forms and inline scripts retain their own samples. Archives/taxonomies, home and configured policy pages are covered separately.
*   **Enhancement:** Adds a default budget of 50 representative samples, per-group URL counts, chosen pages/reasons and equivalent URLs avoided. Additional same-site URLs can be explicitly included for special service or display conditions. Limits and unresolved route families are visible; the report describes representative coverage rather than claiming every page was visited. Old exhaustive jobs cannot silently resume; automatic jobs use the same sampling strategy.
*   **Fix:** Frontend banner-enabled values now handle WordPress-localized empty strings correctly, preserving disabled-banner policy access. The X control retains compact dimensions under theme button padding.
*   **Validation:** A PHP integration fixture with 100 articles, 100 CPT records and distinct template/provider/archive variants groups 207 candidate URLs into 9 HTTP samples and 18 browser passes, avoiding 198 repeated URLs while retaining all declared variants. This is local fixture validation; the installed version must be verified separately on the target site.

### 1.3.65
*   **Fix:** Scanner compatibility checks normalize WordPress-localized protocol strings before comparison with numeric AJAX values. Valid installations no longer fail with a false PHP/scanner mismatch; real mismatches expose both expected and actual runtime details. Engine asset revision is refreshed to prevent stale clients.
*   **Enhancement:** Replaces IP-keyed consent transients and unsigned cookie authorization with signed per-device choices, private append-only receipts, policy snapshots, withdrawal chains and administrator-only daily exports with integrity results. Cookie activation requires a verified current choice and successful receipt persistence; cached HTML never embeds an optional tracking grant.
*   **Enhancement:** Adds **Policy texts** for approved site-specific content and explicit revision management for significant changes, removing generic assumed legal text. Minor text/layout corrections do not re-prompt visitors. Adds **Consent receipts** and configurable archive retention. Existing Elementor pages and shortcode semantics remain available.
*   **Fix:** Raises short legacy consent durations to a minimum of 184 days, preserves refusals during migration, prevents acceptance of an outdated notice, and handles verification/storage failures visibly. X records refusal; accept/reject/customize receive equal prominence. Revocation continues to remove accessible optional cookies and reload the page.
*   **UI:** Restyles the wizard with shared WordPress/Marrison colors, compact controls and responsive spacing; its step values correctly handle WordPress-localized strings. Floating widget background/icon colors follow the configured button colors. Adds scanner report padding and hides inactive resume/stop controls.

### 1.3.64
*   **Enhancement:** Cookie Manager adds a dedicated **Shortcode** tab with selectable codes, individual Copy buttons, copy-failure feedback and instructions for Elementor and WordPress. The shared controls also appear in the wizard and Banner tab. Viewing/copying codes does not submit or change settings.
*   **Enhancement:** Replaces the admin/wizard homepage scan with a resumable internal crawler and isolated Chrome/Edge browser measurements before/after simulated consent. Adds page/phase evidence, path and expiration in inventory, coverage/progress, cancellation/resume, report export and runtime fingerprints. Scheduled scans cover HTTP pages and leave browser work explicitly pending.
*   **Fix:** Complete scans replace stale inventory while preserving manual categories; partial scans retain prior observations. Legacy catalog records and duplicates are removed, cookie values are discarded, and old AJAX clients receive a reload instruction instead of a misleading homepage-only success.
*   **Enhancement:** Adds early consent enforcement for optional JavaScript cookies and known/custom dynamic scripts, frames, pixels and requests. Category-specific additional services can be configured in Behavior. Revocation clears accessible cookie scopes and reloads the page; signed scan phases never alter the administrator's consent or login session.
*   **Fix:** Saving another settings tab no longer disables the banner or automatic scanning because their Behavior checkboxes are absent.

### 1.3.63
*   **Enhancement:** Cookie Manager now provides `[marrison_privacy_policy]` and `[marrison_cookie_policy_content]` instead of creating WordPress pages. The wizard and banner settings show the shortcodes and let administrators configure their own policy URLs. Elementor Shortcode widgets are detected for consent assets; `[marrison_cookie_policy]` keeps its existing cookie-list behavior, and saved policy pages are preserved.
*   **Fix:** Cookie scans no longer insert the WordPress/third-party cookie catalogs as detected results. Only observed request cookies and homepage response headers are recorded, with administrator authentication/settings cookies excluded from request observations. Results are consolidated by name, domain and path, preserving manual categories and observed expiration metadata; a new scan removes legacy catalog-only records and duplicates from storage.
*   **Fix:** Empty scans complete successfully without invented cookie entries. Cleanup, observation and persistence failures return errors even when older records exist, while successful JSON fallback scans retain the database diagnostic and report their actual storage.
*   **Fix:** Cookie Manager removes the unsupported empty default from the `TEXT` description column in installation and scanner table definitions, including direct-query fallbacks, preventing database errors on affected MySQL/MariaDB servers.

### 1.3.62
*   **Fix:** PHP Snippets registers its admin submenu after the main Marrison Addon menu. This preserves WordPress's correct page hook and dashboard link, fixing the false permission-denied response for authorized users. Both capability checks and the early snippet runtime remain unchanged.

### 1.3.61
*   **New Module:** PHP Snippets adds database-backed PHP snippets, WordPress CodeMirror, individual activation and execution scopes, early hook/shortcode/JetEngine registration, native syntax validation and revision-aware fatal-error quarantine. Protected management requests and a wp-config safe-mode constant provide recovery without executing snippets. No external dependencies, eval or permanent snippet files.

### 1.3.60
*   **Fix:** Image Sizes skips SVG/SVGZ attachments by MIME type or extension before manual regeneration, disabled-size cleanup and automatic WebP/AVIF conversion. SVG files and metadata remain intact; the report marks them as skipped and counts them separately from regenerated images and errors. Full regeneration retains its selected scope when disabled-size cleanup is enabled.

### 1.3.59
*   **Enhancement:** Image Sizes adds a dimension selector to thumbnail regeneration. A single default/custom size can be regenerated across the Media Library using its saved dimensions, crop and WebP/AVIF quality settings, while retaining other sizes, full-size variants and attachment metadata. New filenames preserve shared existing files and avoid stale image URLs; disabled-size cleanup is limited to full regeneration. Invalid/disabled selections and partial conversion failures return errors. Queue controls remain locked until an interrupted request finishes.

### 1.3.58
*   **Fix:** Dynamic Background Image Size keeps the existing native Image Resolution selector accessible on Container dynamic backgrounds even when the static media field is empty. It updates Elementor's generic ignored-size warning only on supported dynamic background controls and displays the saved resolution for responsive/overlay/hover controls without replacing the native event handlers or settings/history commands. The added CSS and JavaScript load only in the editor while the module is enabled.

### 1.3.57
*   **New Module:** Dynamic Background Image Size restores the native Elementor Image Resolution selection for Container dynamic background images in frontend dynamic CSS, including responsive/hover/overlay controls. It supports attachment IDs and resolvable URL-only tags, uses existing Image Sizes variants without generation, preserves Full/missing-size fallbacks, and prevents automatic browser size selection on affected containers.
*   **Cache:** Module toggles and Addon/Elementor version changes invalidate Elementor CSS/document caches. Dynamic URLs stay in Elementor's request-specific inline CSS, including JetEngine listing contexts, and hero preloads share the selected size.

### 1.3.56
*   **Fix:** Video Thumbnail treats FFmpeg as an optional server capability. Unavailable execution shows a warning without installation requirements, disables the unavailable automatic-cover control while preserving saved preferences, and skips cover attempts on upload. Availability requires a successful FFmpeg version response; real generation failures still retain their existing diagnostics.

### 1.3.55
*   **New Module:** CSS piccoli inline reduces separate render-blocking requests for eligible small local head styles through WordPress's native bounded inliner. It preserves CSS order and URL resolution, has no frontend HTTP work and starts disabled.

### 1.3.54
*   **Fix:** Local Google Fonts now reads the public homepage during the administrative scan, detecting Google Fonts URLs generated at runtime or missed by the bounded source scan. Previously confirmed sources are retained only for font families still detected in current usage.
*   **Fix:** Local Google Fonts now preserves continuous variable weight ranges, weight 1000 and intermediate weights across scanning, downloads and local coverage checks. Downloads reuse complete original Google CSS requests to preserve their axes.
*   **Fix:** Unresolved PHP format placeholders are excluded from Google Fonts downloads. Remote stylesheets remain available when local coverage cannot be verified.

### 1.3.53
*   **Fix:** LCP background preloads follow Elementor's active responsive breakpoints, including tablet/mobile overrides, additional devices and widescreen inheritance. Disjoint media conditions select the appropriate high-priority image in both fresh and cached renders; repeated URLs are deduplicated and existing matching preloads retain their other attributes.
*   **Fix:** LCP media controls containing only an attachment ID retain their selected image size; unresolved dynamic responsive backgrounds do not preload stale saved URLs.

### 1.3.52
*   **Fix:** LCP background preloads are restored when Elementor serves cached containers with external CSS and skips render hooks. The selected image is resolved from the marked container's own document and emitted once in the head with high priority. Missing documents, inactive LCP settings and unresolved dynamic backgrounds do not produce guessed preloads.

### 1.3.51
*   **Fix:** Browser Cache Nginx rules use quoted regexes and a single Cache-Control policy, exclude error/HTML/private/dynamic responses, preserve existing Cache-Control and handle 304 validation. Instructions integrate with existing static locations and separate http/server contexts for Plesk.
*   **Fix:** Apache Browser Cache resolves query versioning through QUERY_STRING, restricts headers to existing static files and successful GET/HEAD responses, and preserves existing policies. OpenLiteSpeed is correctly treated as requiring manual configuration.
*   **Fix:** Browser Cache diagnostics use GET and check status, MIME, no-store/no-cache/private, duplicated max-age and missing categories; they discover fonts with CSS checks disabled and include CSS background images. A failed verification replaces earlier success; writing rules is marked as pending verification, and ordinary admin loads perform no HTTP diagnostics.
*   **Fix:** Browser Cache disabling reports managed-rule removal failures before changing the saved toggle, malformed marker blocks fail explicitly, and clipboard confirmation follows the real copy result. New settings default to 7-day TTLs without immutable, preserving existing selections.

### 1.3.50
*   **Fix:** Elementor generated CSS now retains the resolved WebP URL even when the media control resolves its attachment ID again in admin/AJAX. Selected media sizes are respected, and shared CSS remains independent of AVIF request negotiation.
*   **Fix:** LCP preloads use the same selected background as CSS and appear once in the head. Priority and background discovery stop at the marked container's closing tag; editor/preview remain excluded and LCP works with conversion disabled.
*   **Fix:** Missing variants keep original URLs rather than inventing WebP paths. Missing AVIF falls back to existing WebP, native WebP/AVIF metadata is recognized, and srcset retains each candidate's real dimensions and crop through both WordPress and final HTML rewriting.
*   **Fix:** Regeneration reports partial conversion failures even with WP_DEBUG disabled, preserving valid metadata and native originals. Media regeneration/settings changes invalidate server and browser background resolution caches; resolved hero backgrounds retain their preloaded URL. Format-negotiated frontend responses send Vary: Accept for both WebP and AVIF clients.

### 1.3.49
*   **Fix:** Cookie Manager automatic scans now respect the configured interval instead of scanning unconditionally every day. Normal requests restore scheduling, and the scan runs through WP-Cron when due, avoiding inline homepage requests and recursive automatic scans.
*   **Fix:** Automatic scan intervals are bounded to 1–30 days at runtime, and disabling automatic scans prevents execution and clears scheduled events. Manual scans remain immediately available.

### 1.3.48
*   **Fix:** Cookie Manager now saves expiration metadata from homepage `Set-Cookie` headers, honoring `Max-Age` before `Expires`, in both database and JSON storage. Existing records can receive updated metadata without losing manually assigned categories.
*   **Fix:** Cookie Policy shows detected expiration in the site's timezone, `Session` only when observed headers confirm a session cookie, and `Not detectable` when expiration is unavailable. Legacy records remain unknown until a new scan observes their attributes; no cookie lifetimes are invented.

### 1.3.47
*   **Fix:** Cookie Policy pages now show scanned cookies by category, with domain, detected expiration, and description, plus a link to reopen the preferences modal. Existing configured pages gain these details without overwriting their saved content.
*   **Fix:** Cookie banner button borders follow the configured color instead of theme defaults, and desktop policy links align with the start of the banner content. Preferences remain accessible from the policy when the banner is disabled.

### 1.3.46
*   **Fix:** Liquid Background now uses the order Elementor background → Liquid → Elementor overlay. Liquid opacity blends with the original background, and overlays remain above the animation even with video, slideshow, or motion effects, without duplicate overlays below Liquid.

### 1.3.44
*   **Audit fixes:** Calendar ICS downloads enforce WordPress read permissions and password protection, preserve signed shortcode date keys and location, and recover empty date keys or invalid legacy timezones.
*   **Audit fixes:** GitHub updates select and verify the actual plugin directory in nested repository archives, and report filesystem failures instead of returning an unusable directory.
*   **Audit fixes:** Cookie Manager blocks quoted and unquoted iframe/script sources, restores the original script type after consent, and keeps the preferences title readable under global heading colors.
*   **Audit fixes:** Header Animations follow Elementor's active responsive settings and preserve inline markup/line breaks in letter effects. Wrapped Link applies custom attributes; Preloader respects cancelled clicks and empty anchors.
*   **Audit fixes:** Image Sizes registers newly generated base sizes before adding WebP/AVIF metadata; Cursor accepts hex hover colors. Removed the image admin debug log and repaired the font parser test path.
*   **Audit fixes:** Horizontal Scroll clips Boxed slides, corrects Snap coordinates/entry, and fits images to keep Snap pinning available within the viewport.
*   **Robustness:** Ticker skips malformed non-scalar text values instead of raising a TypeError.

### 1.3.43
*   **Performance:** Image Sizes now limits dynamic background scans, reuses browser session cache, and caches background WebP/AVIF resolution server-side to avoid repeated metadata lookups.
*   **Fix:** GitHub updater now negative-caches failed version checks, clears the active release-info transient, and only runs in admin/cron contexts.
*   **Performance:** Header Animations assets now load on the frontend only when a Marrison Heading animation is rendered; static plugin asset versions now use the plugin version in production and file mtimes only in debug.
*   **Fix:** Cookie Manager can refresh stale frontend nonces from cached pages, and Product Discount clears scheduled jobs when the module is disabled.

### 1.3.42
*   **New Module:** Leggi di più adds collapsible long text controls to Elementor Text Editor and JetEngine Dynamic Field widgets, with selectable visible lines, a `....` collapsed suffix, and styled Leggi di più/Leggi di meno toggles.

### 1.3.41
*   **Fix:** Liquid Background bottom blend now resolves Elementor global color references saved in `__globals__`, including responsive global colors, instead of falling back when the visible color value is empty.

### 1.3.40
*   **Fix:** Liquid Background now gives the secondary liquid color its own visible WebGL structure, so custom palettes such as black background, purple primary, and lilac secondary render all three selected colors.

### 1.3.39
*   **Fix:** Liquid Background now applies the selected bottom blend color correctly, including Elementor global CSS colors, and makes the blend color responsive for desktop, tablet, and mobile.
*   **Fix:** Empty Liquid Background seeds now use one shared standard pattern, so Containers with identical settings render consistently across a site; enter a Seed number only when a deliberate variant is needed.

### 1.3.38
*   **Fix:** Liquid Background no longer changes Elementor shape divider positioning, preserving full-width separators on boxed and full-width Containers.

### 1.3.37
*   **New Module:** Dynamic SVG adds JetEngine Dynamic Field callbacks to output local SVG media fields as sanitized inline SVG, with an optional Current Color variant for CSS-controlled icon color.

### 1.3.36
*   **Fix:** Liquid Background bottom blend now reaches the selected color earlier and keeps a solid final band, preventing high-contrast fluid settings from tinting the section transition.

### 1.3.35
*   **Enhancement:** Liquid Background now includes an optional bottom blend gradient with color and responsive height controls, allowing smooth transitions into the following section.

### 1.3.34
*   **New Module:** Liquid Background adds procedural WebGL organic backgrounds to ordinary Elementor Containers, with presets, responsive controls, reduced-motion handling, mobile disable, and a lightweight CSS fallback.
*   **New Module:** Scroll Orizzontale adds scroll-linked horizontal movement to ordinary Elementor Containers, with optional pin, both directions, responsive switches, and a reduced-motion fallback.
*   **UI:** The Marrison Addon dashboard now displays module cards in alphabetical order by module title.
*   **Enhancement:** Scroll Orizzontale can now snap between child Containers instead of moving continuously.
*   **Enhancement:** Scroll Orizzontale can now scale the outer Container background image, video, or color during the scroll using an optional background layer.

### 1.3.33
*   **New Module:** Browser Cache configures browser cache headers for static assets without removing WordPress, Elementor, WooCommerce, or plugin version query strings.
*   **Enhancement:** Browser Cache separates Apache/LiteSpeed `.htaccess` management from copy-ready Nginx configuration and verifies real response headers only on manual diagnostic runs.

### 1.3.32
*   **Enhancement:** Local Google Fonts now reuses Elementor's local Google Fonts by family, weight, and style, avoiding duplicate Marrison downloads and duplicate `@font-face` output when Elementor already covers a requested variant.
*   **Fix:** Remote Google Fonts stylesheets can now be removed when coverage is split between Elementor local fonts and Marrison local fonts.

### 1.3.31
*   **Fix:** Local Google Fonts now scans active theme/plugin source files for `fonts.googleapis.com` URLs registered through code, so variants declared by `wp_enqueue_style()` Google Fonts URLs are downloaded locally.
*   **Fix:** Google Fonts URL variants are now added to the scan result even when no matching CSS declaration repeats every requested weight.

### 1.3.30
*   **Fix:** Local Google Fonts now also filters the final WordPress style tag output, suppressing covered Google Fonts links even when a dependency resolver still reaches the remote handle.
*   **Fix:** Local Google Fonts can process late style queues more than once per request, so styles discovered after the first print pass are still evaluated safely.
*   **Fix:** Local Google Fonts can verify coverage from the generated local CSS file when an older manifest is missing a variant entry.

### 1.3.29
*   **Fix:** Local Google Fonts now removes covered remote Google Fonts stylesheets even when they are dependencies of another enqueued stylesheet, while preserving the dependent stylesheet and all unrelated dependencies.
*   **Fix:** Local Google Fonts now requires complete local coverage for every family, weight, and style requested by a Google Fonts URL before removing the remote stylesheet.
*   **Enhancement:** Local Google Fonts diagnostics now report requested remote Google Fonts variants and whether local coverage is complete or incomplete.

### 1.3.28
*   **Fix:** Local Google Fonts now ignores revisions/autosaves, separates current font usage from local `@font-face` declarations, and downloads only Google Fonts confirmed by explicit Google sources or Elementor's Google font registry.
*   **Fix:** Local Google Fonts now parses `font-family` values more strictly, discarding CSS keywords and invalid fragments such as unbalanced quote values.
*   **Fix:** Local Google Fonts can dequeue covered Google Fonts stylesheets even when the remote URL declares broader variant ranges than the variants actually needed locally.

### 1.3.27
*   **New Module:** Local Google Fonts scans existing font usage, downloads Google Fonts WOFF2 files into uploads storage, generates local `@font-face` CSS, and safely removes covered Google Fonts stylesheets on the frontend.

### 1.3.26
*   **Enhancement:** Admin panels now share a unified Marrison Addon visual style based on the Image Sizes module, preserving existing module controls and workflows.
*   **Change:** Main Marrison Addon dashboard no longer shows the manual GitHub update check banner or button.

### 1.3.25
*   **New Module:** Steps adds the Marrison Steps Elementor widget as an optional module that only loads when enabled.

### 1.3.24
*   **Enhancement:** Cookie Manager now blocks common analytics and marketing scripts/iframes before consent, then activates them only when the visitor accepts the related category.
*   **Fix:** Cookie preference controls now start with only necessary cookies selected by default, avoiding preselected optional categories.

### 1.3.23
*   **Fix:** Cookie Manager now creates and verifies its cookie scan table more reliably, including a manual repair/check action in the scanner panel with diagnostic output.
*   **Fix:** Cookie Manager avoids a `DATETIME DEFAULT CURRENT_TIMESTAMP` table definition so older or stricter MySQL/MariaDB servers can create the scan table.
*   **Enhancement:** Cookie scans now report whether results are stored in the database table or the JSON fallback, and admin assets use file timestamps to avoid stale cached JavaScript/CSS.

### 1.3.22
*   **Enhancement:** Image Sizes adds optional AVIF generation alongside WebP for custom and registered WordPress image sizes, with separate quality settings and server support testing in the admin screen.
*   **Enhancement:** Frontend image replacement now prefers AVIF for browsers that advertise support, keeps WebP as fallback, and sends `Vary: Accept` when AVIF is selected.

### 1.3.21
*   **Fix:** Image Sizes now resolves already-generated full-size `.webp` background URLs from Elementor runtime slideshows back to the attachment metadata, so the configured default generated WebP size can be served instead.

### 1.3.20
*   **Fix:** Image Sizes now resolves Elementor slideshow and lightbox JSON image URLs through attachment metadata, so original JPG/PNG payloads use the configured generated WebP size instead of full-size `.webp` files.

### 1.3.19
*   **Enhancement:** Image Sizes adds an Elementor Container "Ottimizzazione LCP" control to preload the hero background or first slideshow image and prioritize the first internal image.
*   **Fix:** Image Sizes now rewrites Elementor lightbox `data-e-action-hash` payloads and slideshow `data-settings` image URLs from JPG/PNG to WebP in the frontend output buffer.

### 1.3.18
*   **Fix:** Image Sizes responsive background auto mode now considers both rendered width and height for `background-size: cover`, preserving cover behavior without choosing undersized WebP crops.

### 1.3.17
*   **Enhancement:** Image Sizes now includes a frontend auto mode for dynamic CSS background images, choosing the smallest suitable generated WebP size based on the rendered container width and device pixel ratio.

### 1.3.16
*   **Enhancement:** Image Sizes can now choose a WebP-enabled fallback size for original/full image URLs, so dynamic Elementor background images can be served as resized WebP files instead of full-size uploads.

### 1.3.15
*   **Enhancement:** Image Sizes can now edit existing custom sizes while preserving the original slug, so existing Elementor and gallery assignments remain valid.
*   **Enhancement:** Image Sizes can now enable WebP conversion and quality settings for registered WordPress, theme, and plugin image sizes.

### 1.3.14
*   **Fix:** Image Sizes now serves generated WebP files on the frontend for WordPress image URLs, srcset candidates, Elementor-generated CSS backgrounds, and inline/plugin-rendered upload URLs when a WebP counterpart exists.
*   **Enhancement:** WebP regeneration also creates a WebP counterpart for the original upload and clears Elementor generated CSS after thumbnail regeneration.

### 1.3.13
*   **New Module:** Anchor Offset adjusts same-page anchor scrolling using the live height of the header with ID `hdr`.

### 1.3.12
*   **Docs:** Added the recommended Listing Grid CSS snippet for centering items when a row has fewer elements.

### 1.3.11
*   **Fix:** Listing Grid Title is prepended to the Listing Grid widget content, so it appears in the Elementor preview and matches the widget-scoped style controls.

### 1.3.10
*   **New Module:** Listing Grid Title adds a title field and style controls directly to the JetEngine Listing Grid widget.
*   **Performance:** Listing Grid Title does not enqueue frontend JS or CSS; styling is generated by Elementor only when the Listing Grid title option is used.

### 1.3.8
*   **Fix:** Recently Viewed Products now tracks product views directly while the module is enabled, without depending on WooCommerce's native recently viewed widget.

### 1.3.7
*   **Fix:** Recently Viewed Products now returns a positive non-existing product ID when the list is empty, preventing JetEngine from discarding the Post In value and returning all products.
*   **Enhancement:** The macro can include the current product immediately when "Escludi prodotto corrente" is set to "No".

### 1.3.6
*   **Fix:** Recently Viewed Products now returns an empty-query-safe value when no product IDs are available, preventing JetEngine Posts Query from falling back to all products.

### 1.3.5
*   **New Module:** Recently Viewed Products adds a JetEngine macro for WooCommerce recently viewed product IDs.
*   **Performance:** The module only registers the macro when enabled and when WooCommerce and JetEngine are available.

### 1.3.4
*   **New Module:** Product Discount adds an Elementor widget for WooCommerce sale percentage badges.
*   **Performance:** Product Discount stays unavailable when WooCommerce is inactive and does not enqueue frontend JS or CSS.

### 1.3.3
*   **Enhancement:** Wrapped Link is now available for Elementor widgets as well as Containers.

### 1.3.2
*   **Enhancement:** Video Thumbnail now generates automatic covers for uploaded MP4/WebM videos.
*   **Enhancement:** Added local video settings for capture second, cover destination, and optional FFmpeg path.
*   **Security:** Added upload permission checks to Video Thumbnail AJAX actions.
*   **UI:** Added module icons and refined dashboard card spacing, toggle sizing, and module descriptions.

### 1.3.1
*   **Maintenance:** Updated plugin version, README stable tag, and WordPress compatibility to match the current WordPress release.

### 1.3.0
*   **Core:** Centralized module registry so disabled modules are no longer required or booted by the plugin.
*   **Enhancement:** Wrapped Link now enqueues its frontend script only when a page actually renders a wrapped container.
*   **Refactor:** Custom Cursor and Preloader now use a stricter frontend-context check and stay out of Elementor editor/preview contexts.
*   **Refactor:** Custom Cursor, Preloader, and Wrapped Link no longer depend on jQuery on the frontend.
*   **Enhancement:** Preloader styling now uses CSS variables instead of printing a page-level inline `<style>` block.
*   **New Module:** Calendar Sync.
*   **New Module:** Cookie Manager.
*   **New Module:** Video Thumbnail.

### 1.2.5
*   **Fix:** Header Animations - Added a Heading-only fallback control under the Advanced tab, applying Marrison animations on the frontend even if Elementor does not expose the additional animation group in the native control.

### 1.2.4
*   **Fix:** Header Animations - Register animation filters earlier so Elementor can include them while building editor controls.

### 1.2.3
*   **Fix:** Header Animations - Added Elementor's additional animations filter so the custom animations appear in the native entrance animation list.

### 1.2.2
*   **Fix:** Header Animations - Initialize Elementor modules reliably when Elementor loads after Marrison Addon.
*   **Fix:** Header Animations - Added a fallback hook for Elementor common motion controls while still limiting animations to the Heading widget.

### 1.2.1
*   **New Module:** Header Animations - Added 10 custom entrance animations to Elementor's Heading widget only.

### 1.2.0
*   **UI:** Admin Menu - Renamed menu item to "AM Addon" and repositioned it below "AM Updater" for better organization.
*   **Fix:** Plugin Details - Fixed missing information in the "View Details" popup by fetching README data directly from GitHub.
*   **Enhancement:** Preloader - Added customization options for progress bar width and height.
*   **Fix:** Custom Cursor - Restricted custom cursor to frontend only (disabled in Backend and Elementor Editor).

### 1.1.9
*   **Fix:** Updater - Fixed issue where GitHub updates would create a duplicate plugin folder. Implemented automatic renaming of the source folder during installation to match the plugin slug.

### 1.1.8
*   **Fix:** Updater - Fixed GitHub repository connection and version check issues.
*   **Fix:** Updater - Resolved incorrect version display in the dashboard.
*   **Fix:** Preloader - Disabled Preloader in Elementor Editor (Edit and Preview modes).
*   **Enhancement:** Preloader - Enabled Preloader on all frontend pages (removed Front Page restriction).

### 1.1.7
*   **Fix:** Custom Cursor - Fixed visibility issues on Admin Bar and Elementor Editor.
*   **Fix:** Custom Cursor - Restored pointer cursor for links in the Admin Bar.
*   **Fix:** Admin Menu - Restored "Marrison Addon" name and updated menu icon style using mask-image.

### 1.1.6
*   **Fix:** Preloader - Fixed logo size issue (switched from max-width to width) to ensure correct display dimensions.
*   **Fix:** Preloader - Added responsive safety for logo on mobile devices.
*   **Fix:** WPML - Corrected configuration syntax for Ticker widget translation.

### 1.1.5
*   **Fix:** Updater - Removed unnecessary GitHub Token requirement for public repositories.
*   **Improvement:** Updater - Added support for 'v' prefix in version tags (e.g., v1.1.5).

### 1.1.4
*   **Enhancement:** Content Ticker - Added WPML compatibility support.

### 1.1.3
*   **New Module:** Fast Logout - Added a module to automatically redirect users to the home page after logout.
*   **Update:** Updated plugin description and metadata.

### 1.1.2
*   **Fix:** Admin Menu - Resolved a slug conflict with the Marrison Installer plugin.
*   **Fix:** Plugin List - Corrected the "Settings" link to point to the correct Marrison Addon dashboard.

### 1.1.1
*   **New Feature:** GitHub Updater - Implemented automatic updates directly from GitHub.
*   **New Feature:** Settings - Added a settings section to the admin panel for GitHub Token (required for private repos or higher API limits).

### 1.1.0
*   **New Module:** Preloader - Added a fully customizable site preloader with advanced exit animations (Slide Up, Slide Left, Split, Shutter) and modern spinners.
*   **New Module:** Custom Cursor - Added a custom mouse cursor with hover effects.
*   **New Module:** Content Ticker - Added a scrolling text ticker widget.
*   **New Module:** Image Sizes - Added a tool to register custom image sizes.
*   **Core:** Implemented a modular architecture. You can now enable/disable features from the plugin settings page.
*   **Update:** Updated plugin description and metadata.

### 1.0.0
*   Initial release.
*   Added Wrapped Link functionality to Elementor Containers (Advanced Tab).
*   Implemented dependency check for Elementor.

## Protezione ricevute su nginx

Bloccare il percorso reale dell’archivio nel server nginx, adattando il prefisso se uploads o WordPress hanno un percorso diverso:

```nginx
location ^~ /wp-content/uploads/marrison-cookie/consent/ {
    deny all;
}
```

Il controllo Site Health prova a leggere un file temporaneo innocuo e lo elimina sempre: HTTP 200 segnala un problema, un errore di rete lascia la verifica da completare. I file esistenti vengono migrati a nomi con suffisso HMAC senza cancellare dati. Vedere [hardening e limiti](docs/cookie-hardening.md).

## PHP Snippets security and recovery

Version 1.3.84 requires HMAC signatures before execution. Existing unsigned snippets are preserved but do not execute: review each one and use **Firma e riattiva** with recent authentication. DISALLOW_FILE_EDIT/DISALLOW_FILE_MODS disable editing; signed execution can continue. Recover with MARRISON_ADDON_PHP_SNIPPETS_SAFE_MODE or the empty file wp-content/uploads/marrison-snippets-off. See [signing, authentication, audit retention and limits](docs/cookie-hardening.md#fase-6--1384).

GitHub releases must include **Marrison-Archive-SHA256: DIGEST** for the exact existing tag archive URL. Missing or mismatched digests block the update offer and installation. The six-hour cache and download URL are unchanged. This checks integrity, not independent authenticity of a compromised GitHub account. See the release procedure and tests in the same document.
