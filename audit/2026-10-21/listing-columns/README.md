# Listing Columns — local verification

The PHP fixture renders the actual new widget against minimal Elementor/WordPress doubles. The browser fixture uses that markup and the module's real CSS/JavaScript in headless Chrome, with neutral sample listing cards. No production site or WordPress database was changed.

Verified: conditional asset declarations; registry dependencies and disabled-by-default behavior; column-range clamps (1–12); automatic SVGs and native icon delegation; escaped output; normal/hover/active/focus style controls; 2/3/4-column layouts; multiple synchronized toolbars (including differing enabled ranges); reload and per-page preferences; denied browser storage; mobile layout restoration; AJAX replacements; nested listings; invalid selectors; keyboard activation and focus; cleanup; editor-style rerenders and no editor preference writes; masonry/carousel/scroll-slider exclusions.

Commands:

```powershell
php tests/listing-columns-php-test.php
php tests/module-definitions-i18n-test.php
node tests/listing-columns-browser-test.js --screenshot
```

The browser test requires Playwright in Node's module search path and an installed Chrome. `desktop.png` and `mobile.png` were visually inspected. PHP lint, JavaScript syntax and `git diff --check` also passed on the affected sources. Installed Elementor/JetEngine sources were inspected for widget controls, asset hooks and grid/slider/masonry markup.

Responsive follow-up: min/max/initial columns now use Elementor's native responsive controls. PHP and browser tests also passed for inherited fields, independent ranges/defaults, site-defined breakpoint widths, exact boundaries, laptop/mobile-extra/widescreen, button visibility, reloads and separate preferences per breakpoint. The desktop preference retains its original storage key. Mobile layout settings require activation width 0. The screenshots above show the original fixture; the new responsive assertions run separately against `--responsive-fixture`.

Button visibility regression: reproduced an out-of-range button appearing despite `hidden` when a more specific site rule sets `display: inline-flex !important`. The JavaScript now applies inline `display: none !important` to disallowed options and removes that inline display when the option becomes allowed again. The browser regression failed before this fix and passed after it. It checks computed visibility, competing display rules, a cached stylesheet without the button-hidden rule, the reported desktop 2–4 range with mobile option 1, synchronized toolbars and breakpoint round trips. This reproduces the failure mechanism locally; the site's actual CSS was not inspected.

Limits: this is isolated local fixture evidence. Native Elementor editor registration/save/history, generated Elementor style sheets, real JetSmartFilters requests and the published WordPress frontend still need an integration check. The existing local site's HTTP server was unavailable during this run. No version/release/ZIP was generated.
