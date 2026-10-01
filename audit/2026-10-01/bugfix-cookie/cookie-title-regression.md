# Cookie Manager popup title regression

## Evidence

The popup is emitted by `includes/modules/cookie-manager/templates/cookie-banner.php:50-55`:

```html
<div id="marrison-cookie-modal" class="marrison-modal">
  <div class="marrison-modal-content" style="...; color: #333333;">
    <div class="marrison-modal-header">
      <h3>Personalizza Cookie</h3>
```

The module CSS sets the modal content background and inherited color at `assets/css/frontend.css:293-305`, but the title rule at `:335-340` sets margin/font only:

```css
.marrison-modal-header h3 { ... }
```

Elementor kit/theme heading rules can therefore set the `h3` color to white after the inline parent color is inherited, producing white title text on the white header gradient (`:326-333`). The same title selector is repeated in the mobile block at `:1012-1015` without a color declaration.

Other modal text has explicit colors: category headings `:428-433`, category descriptions `:435-439`, cookie list headings `:521-526`, cookie names `:566-569`, domains/empty states `:572-585`, and close button `:342-357`. The regression is isolated to the modal header `h3`.

## Minimal scoped fix recommendation

Add the color declaration to the existing scoped selector:

```css
#marrison-cookie-modal .marrison-modal-header h3 {
    color: #333;
}
```

This selector has ID specificity and targets only the Cookie Manager popup title. It prevails over ordinary Elementor kit heading selectors without affecting banner titles, category headings, or site headings. If the live theme uses an `!important` heading color, use `color: #333 !important` on this same selector; do not broaden the selector to all `h3` elements.

## Verification fixture

Set an Elementor Kit heading color to `#fff`, open the banner customization modal, and inspect `#marrison-cookie-modal .marrison-modal-header h3`. Expected computed color: `rgb(51, 51, 51)` and visible text `Personalizza Cookie`. Check category `h4`, close button, banner title and external page headings remain unaffected.

No production or plugin files were modified during this analysis.
