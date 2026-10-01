# Horizontal Scroll: child collapse / white gap diagnosis

## Observed path

`start()` in `assets/js/horizontal-scroll.js:604-703` obtains direct `.elementor-element` children from either the Container's `.e-con-inner` or the root (`getContentHost()` at lines 512-521), inserts a new `.marrison-horizontal-scroll-mover`, and reparents each child into that mover.

The mover is `display:flex; flex-flow:row nowrap; width:max-content` in `assets/css/horizontal-scroll.css:30-45`. Its children receive only `flex-shrink:0` (and `flex:0 0 auto` only when snap is enabled, lines 56-58).

## Concrete cause

Elementor Container children are themselves `.e-con.e-flex` flex items. Elementor's frontend CSS defines `.e-con.e-flex` as `flex: var(--flex-grow) var(--flex-shrink) var(--flex-basis)` with default `flex-basis:auto`; generated per-element CSS can override `--flex-basis`, `width`, `min-width`, and responsive values. When a child configured with percentage/auto basis is moved under a `width:max-content` mover, percentage flex-basis is resolved against an indefinite/cyclic track width. The child can collapse to zero or lose its intended width after reparenting. The same applies to a direct child widget/container layout whose sizing rule depends on the original `.e-con-inner` parent.

This explains the reported pair: the mover/children contribute no usable viewport height, while the pinned scene still sets `height = viewport.offsetHeight + verticalDistance` at `horizontal-scroll.js:673-675`. With a collapsed viewport, the remaining scene becomes approximately `verticalDistance`, producing the white gap; the following container remains after the sticky scene until that distance is consumed.

## Why boxed/full width differ

- Boxed parent: `getContentHost()` selects `.e-con-inner`; reparenting changes each child from `.e-con-inner > .elementor-element` to `.e-con-inner > .marrison-horizontal-scroll-mover > .elementor-element`, so any generated parent-sensitive sizing selector and percentage basis changes containing block.
- Full width parent: `getContentHost()` falls back to the root; reparenting changes direct `.e-con > .elementor-element` to `.e-con > .marrison-horizontal-scroll-mover > .elementor-element`, with the same percentage/auto basis issue and additional root width constraints.
- A child row Container adds another `.e-con-inner` and can appear empty if its own inner flex item has no resolvable width after the outer child is moved.

## Minimal correction direction

Capture each child’s rendered width before reparenting, then give the moved child an explicit `flex:0 0 <measured-width>px` (or preserve an intentional Elementor pixel/viewport width) while it is inside the mover. Re-measure after fonts/images load and on resize; restore the original inline styles during teardown. Also force the mover/host to have a nonzero cross-axis size (`align-items:stretch`, and preserve the largest child height) before deciding pin eligibility. Do not solve this by changing scene height: that only masks the collapsed-content cause.

## Reproduction fixture

Use an actual saved Elementor Container with the module enabled and three direct child Containers:

1. Outer boxed Container: Horizontal Scroll enabled, Pin enabled, Snap disabled.
2. Child Containers with Elementor width settings `33%`, `50%`, and `80%`; each contains a Heading and an Image/inner row. Repeat with outer full-width Container.
3. Place a normal section immediately after it.

Expected before module: all children visible in one horizontal row with their configured widths. Observed after module init: inspect `.marrison-horizontal-scroll-mover > .elementor-element` and record `getBoundingClientRect().width`, `scrollHeight`, `viewport.offsetHeight`, and `scene.style.height`. The failing case has zero/near-zero child widths or collapsed viewport height while `scene.style.height` is approximately `verticalDistance`; the next section starts only after that gap.

## Relevant lines

- `horizontal-scroll.js:512-523` — host/child discovery.
- `horizontal-scroll.js:688-703` — reparenting.
- `horizontal-scroll.js:656-675` — overflow, pin decision, and scene height.
- `horizontal-scroll.css:30-45` — mover layout and only `flex-shrink:0` child rule.
- `horizontal-scroll.css:56-58` — explicit child flex sizing applies only in Snap mode.
- Elementor installed `frontend.min.css` — `.e-con.e-flex` default flex basis and `.e-con.e-flex > .e-con-inner` sizing rules.
