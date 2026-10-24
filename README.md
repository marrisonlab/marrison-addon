# Marrison Addon

Modular tools for WordPress, Elementor, WooCommerce and JetEngine.

**Author:** [Angelo Marra](https://marrisonlab.com)  
**Version:** 1.3.81
**WordPress:** 6.0 or later; tested with 7.1.3  
**PHP:** 7.4 or later; current compatibility target 8.5.11  
**License:** [GPL-3.0-or-later](https://www.gnu.org/licenses/gpl-3.0.html)

## Description

Marrison Addon provides 26 independently enabled modules for page layouts, media, frontend behavior and administration. Disabled modules load no operational module classes, hooks, CSS or JavaScript. The core registry and plugin bootstrap remain available.

Query Fallback adds a backup Posts Query to the native JetEngine Listing Grid, with empty-only and fill modes. Module cards always show required active plugins and keep unavailable modules off and locked. See [Query Fallback configuration and limitations](marrison-addon/docs/query-fallback.md).

Modules include Wrapped Link, Content Ticker, Steps, Product Discount, Heading Animations, Recently Viewed Products, Listing Grid Title, Listing Columns, Dynamic SVG, Read More, Anchor Offset, Preloader, Custom Cursor, Image Sizes, Fast Logout, Calendar Sync, Cookie Manager, Video Thumbnail, Local Google Fonts, Browser Cache, Horizontal Scroll (Beta), Liquid Background, Inline CSS, Dynamic Background Image Size and PHP Snippets.

Elementor, WooCommerce and JetEngine are required only for their related modules. Cookie Manager provides representative HTTP/browser scans, signed consent choices and policy shortcodes. PHP Snippets requires both WordPress `manage_options` and `unfiltered_html` capabilities.


Cookie consent checks keep technical diagnostics out of the visitor banner; failed saves remain visible and do not enable tracking. Receipt protection uses the actual server's configuration. All Privacy Policy profile fields are optional. The shortcode renders saved data even when other fields are empty, omitting unspecified details without blocking publication.

[Full documentation and release history](marrison-addon/README.md) · [WordPress-format readme](marrison-addon/readme.txt)

Cookie Manager 1.3.79 adds a local declarative service catalog, editable service categories and default blocking for unclassified external resources. Catalog declarations remain separate from observed cookies. See [configuration and limits](marrison-addon/docs/cookie-service-catalog.md). Scanner replacement is scheduled for Phase 2.

## Installation

1. Download [marrison-addon.zip](marrison-addon.zip), or obtain the code for the desired [published release](https://github.com/marrisonlab/marrison-addon/releases).
2. In WordPress, open **Plugins > Add New > Upload Plugin** and upload the installable ZIP. A source checkout contains the plugin in the `marrison-addon/` subdirectory.
3. Activate Marrison Addon and open **AM Addon** to enable the desired modules.
4. Open each module's compact settings icon where available. After an update, follow the Cookie Manager rescan notice if that module is enabled.

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
