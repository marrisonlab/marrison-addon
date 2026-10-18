# Marrison Addon

**A comprehensive addon for Elementor and WordPress sites.**

*   **Plugin Name:** Marrison Addon
*   **Plugin URI:** https://github.com/marrisonlab/marrison-addon
*   **Author:** Angelo Marra
*   **Author URI:** https://marrisonlab.com
*   **Tags:** elementor, container, link, wrapper, read more, steps, anchor, ticker, discount, woocommerce, cursor, preloader, logout, video thumbnail, cookie, calendar, liquid background, dynamic svg, marrison
*   **Requires at least:** 6.0
*   **Tested up to:** 7.0.1
*   **Requires PHP:** 7.4
*   **Stable tag:** 1.3.64
*   **License:** GPL-3.0+
*   **License URI:** https://www.gnu.org/licenses/gpl-3.0.txt

## Description

**Marrison Addon** is a modular plugin designed to enhance your WordPress site and Elementor workflow. It provides a suite of essential tools that can be enabled or disabled individually to keep your site lightweight.

**Included Modules:**

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
    *   Cookie banner, floating widget, preferences modal, and setup wizard.
    *   Automatic cookie scanning and category management.
    *   The scanner recursively crawls internal links, WordPress sitemaps and sitemap locations in robots.txt, with a visible page limit (default 500; maximum 5,000), incremental requests and saved progress. Administrative/action URLs, external hosts and asset downloads are excluded. The report exposes failed pages, limits and incomplete coverage; reaching a limit is a partial scan.
    *   HTTP response cookies are observed without forwarding the administrator's cookies. In current Chrome/Edge, the admin panel then visits discovered pages in credentialless browser frames, first before consent and then with consent simulated. JavaScript and embedded resources execute, and readable cookies are measured with domain, path, observed expiration and page/phase evidence. Starting a scan reloads the admin panel to create a fresh isolated session. Keep the panel open for browser measurements; interrupted work can be resumed. Missing browser isolation, blocked frames and timeouts are reported without pretending that browser coverage completed.
    *   The scanner records actual observations, never cookie catalog entries or cookie values. It can read same-site HTTP `Set-Cookie` headers (including HttpOnly) and browser-readable cookies; third-party HttpOnly cookies and interactions that require filling forms, signing in or clicking site controls need a separate browser audit. Page responses are capped at 2 MiB and browser observations at 200 cookies per page, with a maximum of 5,000 distinct cookies per report; exceeded limits mark coverage partial.
    *   Inventory and policy lists consolidate duplicates by name, domain and path, retaining manual categories. A successful complete scan removes cookies no longer observed; partial scans retain earlier observations. Legacy catalog-only records and duplicates are cleaned on the next scan. Database and JSON fallback storage remain supported. Reports can be exported and show runtime version, loaded scanner path and source fingerprint so stale or conflicting installations cannot silently report successful scans.
    *   Automatic scans use WP-Cron and respect the configured interval of 1–30 days (default: 7). They crawl HTTP pages incrementally; browser measurements remain pending until the administrator opens the scanner and resumes. Normal requests repair scheduling without crawling inline. Disabling the module removes scheduled events. WP-Cron execution depends on site traffic or a server cron invoking WordPress cron.
    *   Policy codes are always available in **AM Addon > Cookie Manager > Shortcode**, each with a **Copy** button and Elementor instructions. `[marrison_privacy_policy]` renders the Privacy Policy; `[marrison_cookie_policy_content]` renders the full Cookie Policy with current scan results and a preferences link. Insert them in your own pages with Elementor's Shortcode widget or WordPress's Shortcode block, then configure their URLs in the **Banner** tab. The same copy controls appear in the wizard and banner settings, without creating pages automatically.
    *   `[marrison_cookie_policy]` remains available for just the scanned cookie list and preferences link. Existing policy pages and configured URLs are preserved, and legacy Cookie Policy pages still receive current scan details without replacing saved content.
    *   The policy distinguishes detected expiration (in the site timezone), session cookies and unavailable expiration; lifetimes are never inferred from cookie names. Run a new scan after updating to collect metadata for existing records.
    *   Consent blocking covers known or explicitly categorized scripts, iframes, pixels and dynamic requests, plus optional JavaScript cookie writes. Additional service domains/paths can be configured per category in **Behavior**. Revoking a category removes accessible cookies at their detected scopes and reloads the page so already-executed integrations stop. Essential WordPress/WooCommerce sessions and the consent itself are preserved. Site-specific integrations and server-set cookies still require verification; unknown services cannot all be identified by their URL alone.
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

20. **Scroll Orizzontale (Elementor):**
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

## Installation

1.  Upload the `marrison-addon` folder to the `/wp-content/plugins/` directory of your site.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Settings > Marrison Addon** to enable/disable specific modules.
4.  Configure each module's settings as needed.

## Changelog

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
