=== Marrison Addon ===
Tags: elementor, woocommerce, cookies, images, performance
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.85
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Modular WordPress tools for Elementor layouts, WooCommerce, media, cookie consent and frontend performance.

== Description ==

Marrison Addon is developed by [Angelo Marra](https://marrisonlab.com).

Enable only the modules needed by your site from AM Addon. A disabled module loads no operational module files, classes, hooks, CSS or JavaScript. The central plugin registry and bootstrap remain available. Settings icons open each module's own page when that module and its dependencies are enabled.


Consent verification does not add technical diagnostics or retry buttons to the banner. Failed saves remain explicit and do not enable optional tracking. Receipt protection uses the server's configuration; unused IIS files are not required on Unix hosts. All Privacy Policy fields are optional; saved data render through the shortcode even when other fields are empty. Unspecified details are omitted without blocking output.

Included modules:

* Wrapped Link: clickable Elementor containers and widgets.
* Content Ticker: configurable scrolling text with shared styles.
* Steps: responsive Elementor step layouts.
* Product Discount: WooCommerce percentage output and bounded background recalculation.
* Heading Animations: additional native Elementor heading entrance animations.
* Recently Viewed Products: visitor-specific WooCommerce IDs for JetEngine queries.
* Listing Grid Title: configurable titles for JetEngine Listing Grid.
* Query Fallback: backup Posts Query in the native Listing Grid, with empty-only or fill mode, deduplication and dynamic context. Single-page grids only; pagination, Load More, Lazy Load, applied filters and public AJAX suspend fallback. Installing JetSmartFilters alone does not block unfiltered listings.
* Listing Columns: Elementor column picker for JetEngine grids, with SVG icons and native style controls.
* Dynamic SVG: sanitized local inline SVG and currentColor callbacks.
* Read More: collapsible text for Elementor and JetEngine, with conditional assets.
* Anchor Offset: same-page scrolling adjusted to the header height.
* Preloader: configurable loading screen with a no-JavaScript fallback.
* Custom Cursor: optional frontend pointer effects with paused idle animation.
* Image Sizes: custom sizes, selective regeneration and WebP/AVIF variants.
* Fast Logout: redirects to the homepage after logout.
* Calendar Sync: Google Calendar and ICS links from post metadata.
* Cookie Manager: banner, preferences, representative scans, signed consent receipts and policy shortcodes.
* Video Thumbnail: YouTube imports and optional FFmpeg cover generation for local videos.
* Local Google Fonts: observed-font inventory and local WOFF2 copies.
* Browser Cache: static-asset HTTP header rules and public diagnostics.
* Horizontal Scroll (Beta): horizontal Elementor container sections.
* Liquid Background: optional procedural WebGL container backgrounds.
* Inline CSS: WordPress-native inlining of small queued local styles.
* Dynamic Background Image Size: native resolution selection for dynamic Elementor backgrounds.
* PHP Snippets: scoped custom PHP with syntax checks and failure recovery.

Elementor, WooCommerce and JetEngine are needed only by their respective integrations. Independent modules remain available without them. PHP Snippets requires both manage_options and unfiltered_html; custom PHP has plugin-level privileges and is not sandboxed.

= Service catalog (Phase 1) =


= Compatibility =

Minimum requirements: WordPress 6.0 and PHP 7.4. Current compatibility targets checked on 20 October 2026: WordPress 7.1.3 and PHP 8.5.11. The standard Tested up to field uses the WordPress 7.1 series. Optional extensions and FFmpeg capabilities are checked by the corresponding module.

= Cookie scans after updates =

If Cookie Manager is enabled, the post-update notice clears after a current-version static scan completes and saves its results. Optional iframe checks are required only when explicitly enabled. Old, cancelled, failed or incomplete work does not confirm a completed scan.

Cookie observations never store values. The local scanner finds services in published content/configuration; their catalog cookies are declared, not observed. Optional iframe checks cannot see third-party cookies. Verify login, checkout and conditional interactions manually. Policy shortcodes do not create pages automatically.

= External services =

GitHub: the active plugin checks api.github.com for the latest stable release of marrisonlab/marrison-addon in admin/WP-Cron, with a six-hour version cache. It downloads the source archive of a newer tag from github.com. No GitHub account or token is required for this public repository. [GitHub terms](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service) and [privacy](https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement).

Google Fonts: when the Local Google Fonts module is enabled and its scan/download actions are used, it may request public font stylesheets from fonts.googleapis.com and font files from fonts.gstatic.com to prepare local copies. Remote sheets are removed only when a usable matching local variant exists. [Google terms](https://policies.google.com/terms) and [privacy](https://policies.google.com/privacy).

YouTube: when the Video Thumbnail module is enabled and an administrator requests an import, it downloads the selected video's public thumbnail from allowed YouTube thumbnail hosts. It does not upload the site's local videos to YouTube. Google terms and privacy links above apply.


Cookie scans request public same-site pages; browser passes can load the third-party services embedded in those pages. Run them only on a site you administer. Calendar links open Google Calendar only when followed by the visitor.

== Installation ==

1. Upload marrison-addon.zip through Plugins > Add New > Upload Plugin, or copy the marrison-addon directory to wp-content/plugins/.
2. Activate Marrison Addon.
3. Open AM Addon and enable the required modules.
4. Open each module's settings icon and configure its options. Cookie Manager offers a setup wizard.
5. After an update, follow the rescan notice when Cookie Manager is enabled.

== Frequently Asked Questions ==

= Do disabled modules load assets? =

No. Disabled modules are excluded from bootstrap. Their operational classes, hooks and frontend CSS/JavaScript are not loaded. Switching modules off also removes their own scheduled tasks and managed server rules where applicable; manually installed server directives remain the administrator's responsibility.

= How do GitHub updates work? =

Publish a stable GitHub Release as Latest with a lowercase vX.Y.Z tag and matching PHP version in the committed plugin sources. A plain Git tag is not sufficient. The active plugin uses the native WordPress update list and downloads the tagged sources, not an attached release ZIP. Version checks are cached for six hours.

= Does the plugin require Elementor? =

Only Elementor-specific modules require it. Other integration requirements are shown per module in AM Addon.

Every dependent module lists the plugins that must be present and active. Missing requirements keep its switch off and locked and block new activation server-side.

= Where is the complete documentation? =

[GitHub documentation and full changelog](https://github.com/marrisonlab/marrison-addon/blob/main/marrison-addon/README.md).

== Changelog ==

= 1.3.85 =
* Nuova scansione: elimina il vecchio inventario cookie e salva soltanto i risultati del job corrente, anche parziale. Ricevute e impostazioni invariate.
* Distingue i rapporti del vecchio metodo remoto e ne impedisce la ripresa.

= 1.3.84 =
* Fase 6: Firme e riautenticazione PHP Snippets, log, recupero file, costanti WordPress e verifica checksum updater.

= 1.3.83 =
* Fase 5: Hardening endpoint e output, rate limit, ricevute limitate e non indovinabili, controllo HTTP Site Health.

= 1.3.82 =
* Fase 4: Default consenso 180 giorni, modalità solo tecnici e checklist dichiarativa non bloccante.

= 1.3.81 =
* Fase 3: Consent Mode v2 base, opzione avanzata esplicita, revoca Meta e caricamento YouTube per singolo contenuto.

= 1.3.80 =
* Fase 2: Scansione statica locale riprendibile, verifica iframe opzionale limitata e rilevamento passivo degli host su esplicita attivazione.

= 1.3.79 =

* Cookie Manager, Phase 1: validated ten-service catalog, official source/privacy links and review badges. Preserve the separation between declared and observed cookies.
* Add editable service classifications, custom domain/path recognition and default blocking of unclassified external resources. Existing cookies, receipts and settings are preserved.

= 1.3.78 =
* Fix clipped Query Fallback radio options, Select2 placeholder and selection lifecycle. Show the actual incompatible setting in editor warnings.
* Allow unfiltered listings when JetSmartFilters is installed; suspend only requests with filters applied to the main or backup query.

= 1.3.77 =
* Fix Query Fallback control registration outside an Elementor section and missing controls when JetEngine legacy queries are disabled.

= 1.3.76 =
* Show required plugins with green checks or red crosses instead of requirement sentences; preserve accessible status and blocked unavailable switches.

= 1.3.75 =
* Add Query Fallback to the native JetEngine Listing Grid with empty-only/fill modes, progressive deduplication, dynamic context and isolated widget results.
* Suspend fallback for pagination, Load More, Lazy Load, JetSmartFilters and public AJAX; document limits and verification procedures.
* Show required active plugins on every dependent module card, keep unavailable toggles off and locked and validate activation server-side.

= 1.3.74 =
* Skip deleted 404/410 scan routes without false incomplete results; keep genuine failures visible.
* Add structured privacy generation and a skippable wizard step, richer Cookie Policy and heading-free shortcode content.
* Remove duplicate Banner shortcodes and banner close X/note. Show a clear scan completion state and readable consent receipts.

= 1.3.73 =
* Run remote scans in signed server background batches with leases and a cron watchdog. Continue the wizard or leave the scanner after starting; reopen to see saved progress. Show errors and support resume.


= 1.3.72 =
* Add expiring site proof, quota/concurrency controls, safe retries and explicit provider errors. No license or provider key on WordPress.

= 1.3.71 =
* Capture actual response cookies in isolated browser scans, including late HttpOnly headers; simulate native consent without visitor receipts.
* Detect dynamically requested services and wait for delayed scripts. Show provider-documented service cookies separately from observations, with source links in the scanner and policy. Refine desktop banner text spacing.


== Upgrade Notice ==

= 1.3.78 =
Fixes Query Fallback display and the global block caused by installed JetSmartFilters on unfiltered listings. Reload the Elementor editor after updating.

= 1.3.77 =
Fixes the Elementor section error when opening Listing Grid with Query Fallback enabled. Reload the Elementor editor after updating.

= 1.3.75 =
Enable Query Fallback in AM Addon to configure a backup Posts Query in Listing Grid. Read the single-page compatibility limits before use. Missing plugin dependencies now show an off, locked module switch.

= 1.3.74 =
Configure the new Privacy and policies fields to generate your notice. Custom policy textareas are replaced; independent policy pages remain user-managed.

= 1.3.73 =



= PHP Snippets security =

Unsigned snippets are preserved but not executed. Review them and use Firma e riattiva with both capabilities, nonce and recent authentication. The emergency file wp-content/uploads/marrison-snippets-off suspends execution. Read-only WordPress constants, code size/rate limits and a metadata-only audit log are respected. See docs/cookie-hardening.md.

= Release checksum =

Publish Marrison-Archive-SHA256: DIGEST in the GitHub release body for the exact existing tag archive URL. Missing/mismatched checksums block offers and installation; six-hour caching is unchanged. This is an integrity check, not an independent signature of the publisher.
