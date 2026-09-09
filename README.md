# Marrison Addon

**A comprehensive addon for Elementor and WordPress sites.**

*   **Plugin Name:** Marrison Addon
*   **Plugin URI:** https://github.com/marrisonlab/marrison-addon
*   **Author:** Angelo Marra
*   **Author URI:** https://marrisonlab.com
*   **Tags:** elementor, container, link, wrapper, ticker, discount, woocommerce, cursor, preloader, logout, video thumbnail, cookie, calendar, marrison
*   **Requires at least:** 6.0
*   **Tested up to:** 7.0.1
*   **Requires PHP:** 7.4
*   **Stable tag:** 1.3.12
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

3.  **Product Discount (WooCommerce + Elementor):**
    *   Adds an Elementor widget that displays the current product discount percentage.
    *   Works in product pages and product listings that provide a WooCommerce product context.
    *   Includes typography, color, background, alignment, border, radius, padding, and shadow controls.

4.  **Header Animations (Elementor):**
    *   Adds 10 custom entrance animations to Elementor's Heading widget only.
    *   Uses Elementor's native entrance animation control and timing options.

5.  **Recently Viewed Products (WooCommerce + JetEngine):**
    *   Adds the "Visualizzati di recente (prodotti)" macro to JetEngine macro UIs.
    *   Returns recently viewed WooCommerce product IDs as a comma-separated list.
    *   Designed for dynamic query parameters and Elementor/JetEngine listing contexts.
    *   Tracks product views while the module is enabled, without requiring WooCommerce's native recently viewed widget.
    *   When used in Query Builder, disable "Cache Query" because results depend on the visitor cookie.

6.  **Listing Grid Title (JetEngine + Elementor):**
    *   Adds a "Titolo Listing" section directly to the JetEngine Listing Grid widget.
    *   Outputs an optional title before the Listing Grid output.
    *   Includes title text, optional link, HTML tag, typography, colors, alignment, background, border, radius, shadow, padding, and margin controls.
    *   To center Listing Grid items when a row has fewer elements, add `selector .jet-listing-grid__items { justify-content: center; }` to the Listing Grid custom CSS.

7.  **Preloader:**
    *   Add a professional loading screen to your site.
    *   **Animations:** Fade, Slide Up, Slide Left, Split (Curtain), Shutter (Vertical).
    *   **Spinners:** Circle, Dots, Double Ring, Wave, Pulse (Logo).
    *   **Customization:** Upload your logo, choose colors, and set transition duration.
    *   **Progress Bar:** Optional progress bar with percentage display.

8.  **Custom Cursor:**
    *   Replace the default system cursor with a custom follower.
    *   Customizable colors, size, and hover effects (scale, magnetic).
    *   "Exclusion" blending mode for high visibility on any background.
    *   **Frontend Only:** Skips backend, preview, and Elementor Editor contexts.

9.  **Image Sizes:**
    *   Define custom image sizes for your theme directly from the dashboard.
    *   Control cropping and dimensions without editing code.

10. **Fast Logout:**
    *   Automatically redirects users to the home page after logging out, bypassing the default WordPress login screen.

11. **Calendar Sync:**
    *   Generate Google Calendar and ICS event links from post meta.
    *   Configurable meta keys for start and end dates.
    *   Includes shortcode support for templates and dynamic content.

12. **Cookie Manager:**
    *   Cookie banner, floating widget, preferences modal, and setup wizard.
    *   Automatic cookie scanning and category management.
    *   Frontend UI only loads when the module is active and in a real frontend context.

13. **Video Thumbnail:**
    *   Fetch YouTube thumbnails and import them directly into the WordPress Media Library.
    *   Automatically generate JPG covers from uploaded MP4/WebM videos using FFmpeg.
    *   Configure capture second, cover destination, and optional FFmpeg binary path from the admin page.
    *   Keeps the original admin workflow while living as a module inside Marrison Addon.

## Installation

1.  Upload the `marrison-addon` folder to the `/wp-content/plugins/` directory of your site.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Settings > Marrison Addon** to enable/disable specific modules.
4.  Configure each module's settings as needed.

## Changelog

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
