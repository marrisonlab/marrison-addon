# Second pass findings

## Robustness risk: Ticker non-scalar item crashes render

Probe: `ticker-array.php` creates the real registered Ticker widget with repeater `item_text` equal to an array. LocalWP output:

```text
Warning: Array to string conversion ... wp-includes/formatting.php
render_error=TypeError:mb_strlen(): Argument #1 ($string) must be of type string, array given
```

Cause: `includes/modules/ticker/widgets/ticker-widget.php:623` passes `$item_text` directly to `esc_html()`, and line 632 passes it directly to `mb_strlen()`. The JetEngine path can produce the same shape when a selected field returns an array, because `resolve_dynamic_value()` returns object properties without scalar normalization at lines 545-553. This is a confirmed fatal edge for repeater/filter/dynamic fields returning arrays.

## Wrapped Link value check

Probe: `probe.php` uses the real Elementor Container element and URL control. The control definition default is empty string; when raw settings are supplied with `is_external=1`, Elementor preserves `is_external:"1"` and the module outputs it in `data-marrison-addon`. The repository JS tests strictly `settings.is_external === 'on'` at `assets/js/marrison-addon.js:29`.

No saved Wrapped Link settings exist in the local database, so the actual editor checkbox serialization could not be confirmed from a saved document. Therefore the `1` versus `on` mismatch remains an unconfirmed compatibility risk, not a promoted bug.

## Steps breakpoint check

The installed Steps control is responsive (`layout_direction` reports responsive max desktop and tablet/mobile inheritors). The shipped stylesheet has fixed media queries `max-width:1024px` and `max-width:767px`. The local Elementor active breakpoints currently use those defaults; no custom breakpoint configuration was present in the local runtime. The mismatch is therefore a future configuration risk, not a confirmed current bug.

## Classification update

The Ticker probe uses an artificially non-scalar repeater value. The normal Elementor text control is scalar and the local database has no JetEngine Query Builder configuration returning an array field, so classify this as robustness risk until a real checkbox/repeater/media-multiple field reproduces it.

Wrapped Link custom attributes are a confirmed feature gap: the real Container probe supplied `custom_attributes:"data-test|wrapped"`; rendered output contained the JSON inside `data-marrison-addon`, but no `data-test` or `wrapped` HTML attribute. Elementor's URL control exposes this setting and the README promises support.
