# Marrison Addon

**A comprehensive addon for Elementor and WordPress sites.**

*   **Plugin Name:** Marrison Addon
*   **Plugin URI:** https://github.com/marrisonlab/marrison-addon
*   **Author:** Angelo Marra
*   **Author URI:** https://marrisonlab.com
*   **Tags:** elementor, container, link, wrapper, ticker, cursor, preloader, marrison
*   **Requires at least:** 6.0
*   **Tested up to:** 6.9
*   **Requires PHP:** 7.4
*   **Stable tag:** 1.1.1
*   **License:** GPL-3.0+
*   **License URI:** https://www.gnu.org/licenses/gpl-3.0.txt

## Description

**Marrison Addon** is a modular plugin designed to enhance your WordPress site and Elementor workflow. It provides a suite of essential tools that can be enabled or disabled individually to keep your site lightweight.

**Included Modules:**

1.  **Wrapped Link (Elementor):**
    *   Make an entire Elementor Container clickable.
    *   Adds a "Wrapped Link" section directly to the **Advanced** tab.
    *   Supports dynamic tags and custom attributes.

2.  **Content Ticker:**
    *   Create smooth, infinite scrolling text tickers.
    *   Customizable speed, direction, and styling.
    *   Pause on hover functionality.

3.  **Preloader:**
    *   Add a professional loading screen to your site.
    *   **Animations:** Fade, Slide Up, Slide Left, Split (Curtain), Shutter (Vertical).
    *   **Spinners:** Circle, Dots, Double Ring, Wave, Pulse (Logo).
    *   **Customization:** Upload your logo, choose colors, and set transition duration.
    *   **Progress Bar:** Optional progress bar with percentage display.

4.  **Custom Cursor:**
    *   Replace the default system cursor with a custom follower.
    *   Customizable colors, size, and hover effects (scale, magnetic).
    *   "Exclusion" blending mode for high visibility on any background.

5.  **Image Sizes:**
    *   Define custom image sizes for your theme directly from the dashboard.
    *   Control cropping and dimensions without editing code.

## Installation

1.  Upload the `marrison-addon` folder to the `/wp-content/plugins/` directory of your site.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Settings > Marrison Addon** to enable/disable specific modules.
4.  Configure each module's settings as needed.

## Changelog

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
