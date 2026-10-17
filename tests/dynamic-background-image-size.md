# Dynamic Background Image Size: verification

The installed source inspected for feasibility was Elementor **4.3.3**, WordPress
**7.1.2**, and JetEngine **3.8.15.4**. These dependencies are installed outside this
repository; the integration runner accepts their WordPress root through an
environment variable.

## Verified extension points

- Elementor `includes/controls/media.php`: the native resolution is media `size`;
  empty string represents Full. `get_style_value()` resolves an attachment ID again.
- Elementor `includes/base/controls-stack.php::parse_dynamic_settings()`: a data tag
  replaces the entire media value, discarding the saved control's `size`.
- Elementor `core/files/css/post.php::render_styles()`: real actions
  `elementor/element/before_parse_css` and `elementor/element/parse_css` bracket a
  specific element's CSS generation.
- Elementor `core/files/css/base.php::add_control_rules()`: the real filter
  `elementor/files/css/property` runs before `{{URL}}` is parsed by the media control.
- Elementor `core/dynamic-tags/dynamic-css.php` inherits that generation path and
  emits dynamic styles inline. Its `Post_Local_Cache` parent regenerates them for
  each request; context-dependent URLs are not written into shared external CSS.
- JetEngine `Jet_Engine_Elementor_Dynamic_CSS` inherits the same dynamic CSS path
  and adds a selector per listing object. The test reuses one saved template for
  two different objects and checks their respective selected attachment sizes.
- Elementor `core/files/manager.php::clear_cache()` removes CSS/document metadata
  and generated files. Dashboard toggles call it even when the new module was
  disabled at request start; enabled version changes invalidate once.
- Elementor `assets/js/editor.js`: `controls/base/behaviors` is the native filter
  used to attach a scoped Marionette behavior to existing Media controls. Container
  control options already contain the actual `container` and `model` when the
  behavior is constructed. `change:__dynamic__`, control value/placeholder changes
  and render/ready events update visibility and the existing description.
- Elementor `assets/css/editor.css` hides `.e-control-image-size` when the media
  has `.e-media-empty-placeholder`; editor-only module CSS overrides that one rule
  under a behavior-owned class on supported dynamic Container controls.
- Native Media `onMediaInputImageSizeChange()`, multiple-value `setValue()` and
  `setSettingsModel()` remain unchanged. They still issue
  `document/elements/settings`, including the native responsive placeholder logic.
  The behavior never writes the model or registers a replacement Media view.

The `marrison_addon/image_sizes/*` filters added in this change are internal Addon
integration points, not Elementor APIs. They reuse the existing format source/file
checks and preserve resolved CSS URLs through the existing HTML formatter. They do
not change ordinary image HTML format selection.

## Automated checks

Run with PHP and Node:

```text
php tests/module-definitions-i18n-test.php
php tests/image-sizes-frontend-regression-test.php
php tests/image-sizes-modern-upload-test.php
node tests/image-sizes-background-js-test.js
php tests/dynamic-background-editor-php-test.php
node tests/dynamic-background-editor-browser-test.js <wordpress-root>
```

The integration runner requires a **disposable** MySQL database ending in `_test`.
It uses its own content/uploads directory, blocks outbound WordPress HTTP, and
rolls back test posts/options. It never uses the installed site's wp-config or DB.
The defaults are local root with no password and TCP port 13379. Set
`MARRISON_TEST_DB_HOST` if different. Do not point it at a site's real database.

```powershell
$env:MARRISON_TEST_WP_ROOT = 'C:/path/to/wordpress'
$env:MARRISON_TEST_DB_NAME = 'marrison_dynamic_background_test'
php tests/dynamic-background-elementor-integration.php --install
php tests/dynamic-background-elementor-integration.php
php tests/dynamic-background-elementor-integration.php --formats-off
```

The frontend integration run for 1.3.57 passed **47 assertions with Image Sizes ON**
and **40 with it OFF**. The 1.3.58 editor change passed **44 browser assertions**
using Elementor's installed native Media/Multiple view code with the real
Backbone/Marionette libraries, plus **8 PHP editor-asset assertions** and reruns of
the four standalone regression/registry suites above. The native document command
is recorded by a fixture adapter; history restoration is simulated in this browser
test, not a full live Elementor editor Undo/Redo session. Playwright and a browser
are required; use `PLAYWRIGHT_BROWSER_PATH` if no bundled browser is available.
The integration
suite verifies saved post CSS discovery, actual frontend dynamic
CSS, native Medium/Full and missing-size fallback, a deleted intermediate file,
URL-only local/external tags, responsive/overlay selectors, cover/position,
unchanged stored settings, deterministic formats, final HTML rewriting, hero
preload settings, actual JetEngine tags/listing contexts, version invalidation and
the real dashboard AJAX endpoint for both ON/OFF cache invalidation paths.
The browser contract verifies that protected dynamic containers cause no automatic
size-selection request. Registry checks verify that a disabled module is not loaded.

## Remaining site checks

An actual ACF/Elementor Pro installation was unavailable; ID/URL data tag contracts
were tested through Elementor's real Data_Tag API. Server-side preview shares the
PHP path; native control rendering was browser-tested in an isolated fixture, while
a full live editor save/preview/Undo/Redo session remains unverified. Production
page/CDN caches and rendered images still require validation on the target site. The fixture image
files test existing-file selection, not rendering or image conversion.

Shared styles follow Image Sizes' deterministic WebP policy. Native WordPress AVIF
attachments remain usable; this module does not bake Accept-dependent generated
AVIF URLs into cacheable CSS or generate missing modern files.
